<?php
/**
 * Bluesky posting for Auto publish (2.38.0, SCOPE §81), through the AT Protocol with an app password Gene makes in Bluesky and pastes once.
 *   - A post is the pack's Bluesky text with its link shortened for reading and made clickable (a link facet, UTF-8 byte offsets), and the
 *     item's card as the link preview (app.bsky.embed.external, the card uploaded as its thumb). A card over Bluesky's 1 MB goes without the image.
 *   - Each item posts once: a record per item ('dbd_bsky_job_2026-w10-rush'). The post's record key is chosen before it's sent and kept, so
 *     when a reply is lost, the next press finds the post that went out instead of posting it again.
 *   - The session (createSession) is kept for an hour; a refused one is made again once.
 *   - 2.39.0 (SCOPE §82): Bluesky on Instagram's days, 9:00 AM Eastern. Tuesday is a thread (a first post naming the week, then each item with a
 *     square replying in pack order), Wednesday to Sunday one item a day. Booking, cutoffs, retries and the note copy Instagram's (§76), and
 *     a day uses the item's same record as Auto publish, so an item already sent with the button is skipped on its day. The thread keeps
 *     each part's record key and address as it goes, so a retry carries on from the last part that went out.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Bluesky {

	const OPT_CONN = 'dbd_bsky_conn';    /* the connection: pass, handle, did, checked (not autoloaded) */
	const OPT_JOB  = 'dbd_bsky_job_';    /* one record per item */
	const SESSION  = 'dbd_bsky_session'; /* the session, an hour */
	const API      = 'https://bsky.social/xrpc/';
	const ALPHA    = '234567abcdefghijklmnopqrstuvwxyz';  /* a record key's letters (TID) */
	const OPT_SET  = 'dbd_bsky_set';     /* the weekly switch: array( 'on' => bool ), on unless unticked (SCOPE §82) */
	const HOOK     = 'dbd_bsky_send';
	const COL      = 'app.bsky.feed.post';

	/** The largest card Bluesky takes as a thumb; the harness lowers it. */
	public static $max_blob = 1000000;

	public static function init() {
		add_action( 'dbd_synced', array( __CLASS__, 'note' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_action( 'admin_post_dbd_bsky_again', array( __CLASS__, 'again_action' ) );
	}
	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK ); }

	/* ------------------------------------------------------------------ the connection */

	public static function settings() { $o = get_option( self::OPT_SET, null ); return array( 'on' => is_array( $o ) ? ! empty( $o['on'] ) : true ); }
	public static function sanitize( $v ) { return array( 'on' => is_array( $v ) && ! empty( $v['on'] ) ); }
	/** Posting on the weekly days: switched on and connected. */
	public static function on() { return self::settings()['on'] && self::connected(); }

	public static function conn() { $c = get_option( self::OPT_CONN, null ); return is_array( $c ) && ! empty( $c['pass'] ) ? $c : null; }
	public static function connected() { $c = self::conn(); return $c && ! empty( $c['did'] ); }

	/** One call: array( HTTP code, JSON, error text ('' when fine) ). A POST sends JSON, or raw bytes with their type; a GET its fields in the address. */
	public static function call( $nsid, $body = array(), $jwt = '', $type = 'application/json', $method = 'POST' ) {
		$h = array(); if ( $jwt !== '' ) { $h['Authorization'] = 'Bearer ' . $jwt; }
		$req = array( 'method' => $method, 'timeout' => 30, 'headers' => $h ); $url = self::API . $nsid;
		if ( $method === 'GET' ) { $url = add_query_arg( array_map( 'rawurlencode', (array) $body ), $url ); }
		else { $req['headers']['Content-Type'] = $type; $req['body'] = $type === 'application/json' ? wp_json_encode( $body ) : $body; }
		$r = wp_remote_request( $url, $req );
		if ( is_wp_error( $r ) ) { return array( 0, null, 'Bluesky could not be reached' . ( is_object( $r ) && method_exists( $r, 'get_error_message' ) ? ': ' . $r->get_error_message() : '' ) ); }
		$code = (int) wp_remote_retrieve_response_code( $r ); $j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$err = ( $code >= 200 && $code < 300 ) ? '' : 'Bluesky answered ' . $code . ( ! empty( $j['message'] ) ? ': ' . $j['message'] : ( ! empty( $j['error'] ) ? ': ' . $j['error'] : '' ) );
		return array( $code, is_array( $j ) ? $j : array(), $err );
	}

	/** A session: array( access token, did, error ). Kept for an hour unless $fresh. */
	public static function session( $fresh = false ) {
		$c = self::conn(); if ( ! $c ) { return array( '', '', 'Bluesky isn’t connected.' ); }
		if ( ! $fresh ) { $s = get_transient( self::SESSION ); if ( is_array( $s ) && ! empty( $s['jwt'] ) ) { return array( (string) $s['jwt'], (string) $s['did'], '' ); } }
		list( , $j, $err ) = self::call( 'com.atproto.server.createSession', array( 'identifier' => (string) $c['handle'], 'password' => (string) $c['pass'] ) );
		if ( $err !== '' || empty( $j['accessJwt'] ) ) { return array( '', '', $err !== '' ? $err : 'Bluesky gave no session.' ); }
		set_transient( self::SESSION, array( 'jwt' => (string) $j['accessJwt'], 'did' => (string) $j['did'] ), HOUR_IN_SECONDS );
		return array( (string) $j['accessJwt'], (string) $j['did'], '' );
	}

	/** A call with the session; a refused session (expired, or revoked and pasted again) is made fresh once. */
	private static function authed( $nsid, $body, $type = 'application/json' ) {
		list( $jwt, , $err ) = self::session(); if ( $err !== '' ) { return array( 0, array(), $err ); }
		$r = self::call( $nsid, $body, $jwt, $type );
		if ( $r[0] === 401 || ( $r[0] === 400 && in_array( (string) ( $r[1]['error'] ?? '' ), array( 'ExpiredToken', 'InvalidToken' ), true ) ) ) {
			list( $jwt, , $err ) = self::session( true ); if ( $err !== '' ) { return array( 0, array(), $err ); }
			$r = self::call( $nsid, $body, $jwt, $type );
		}
		return $r;
	}

	/** A pasted app password: checked with createSession for the account under Your accounts, then kept. Returns '' or what went wrong. */
	public static function connect( $pass ) {
		$pass = trim( (string) $pass ); if ( $pass === '' ) { return ''; }
		$h = DBD_Pack::our_bsky(); if ( $h === '' ) { return 'Put the Bluesky account under Your accounts first.'; }
		list( , $j, $err ) = self::call( 'com.atproto.server.createSession', array( 'identifier' => $h, 'password' => $pass ) );
		if ( $err !== '' || empty( $j['did'] ) ) { return 'That app password didn’t work for @' . $h . '. ' . ( $err !== '' ? $err : 'Bluesky didn’t say whose it is.' ); }
		update_option( self::OPT_CONN, array( 'pass' => $pass, 'handle' => (string) ( $j['handle'] ?? $h ), 'did' => (string) $j['did'], 'checked' => time() ), false );
		if ( ! empty( $j['accessJwt'] ) ) { set_transient( self::SESSION, array( 'jwt' => (string) $j['accessJwt'], 'did' => (string) $j['did'] ), HOUR_IN_SECONDS ); }
		return '';
	}
	public static function disconnect() { delete_option( self::OPT_CONN ); delete_transient( self::SESSION ); }

	/**
	 * The settings box's Bluesky part: disconnect, or a new app password checked. A kept connection for an account that's no longer the one
	 * under Your accounts is forgotten, so the button never posts to an account the settings don't name. Returns '' or what went wrong.
	 */
	public static function save( $p ) {
		update_option( self::OPT_SET, self::sanitize( is_array( $p[ self::OPT_SET ] ?? null ) ? $p[ self::OPT_SET ] : array() ) );
		if ( ! empty( $p['dbd_bsky_disconnect'] ) ) { self::disconnect(); return ''; }
		$new = is_scalar( $p['dbd_bsky_pass_new'] ?? null ) ? trim( (string) $p['dbd_bsky_pass_new'] ) : '';
		if ( $new !== '' ) { $c0 = self::conn(); $err = self::connect( $new ); if ( $err !== '' && $c0 && strtolower( (string) $c0['handle'] ) !== strtolower( DBD_Pack::our_bsky() ) ) { self::disconnect(); } return $err; }
		$c = self::conn();
		if ( $c && strtolower( (string) $c['handle'] ) !== strtolower( DBD_Pack::our_bsky() ) ) {
			self::disconnect(); return DBD_Pack::our_bsky() !== '' ? 'The Bluesky account changed, so its app password was forgotten. Paste one for @' . DBD_Pack::our_bsky() . '.' : '';
		}
		return '';
	}

	/* ------------------------------------------------------------------ the post */

	/** A link as the post shows it: host and path, no query, 30 characters at most. */
	public static function short_link( $url ) {
		$s = (string) preg_replace( '#^https?://(www\.)?#', '', (string) $url ); $s = rtrim( (string) preg_replace( '/[?#].*$/', '', $s ), '/' );
		return mb_strlen( $s ) > 30 ? rtrim( mb_substr( $s, 0, 29 ), '/' ) . '…' : $s;
	}

	/** The post's text and its link facet: array( text, facets ). The pack's Bluesky text, its link shown short; the facet carries the full link. */
	public static function text( $it ) {
		$t = (string) DBD_Pack::social( $it )['bsky']; $link = DBD_Pack::share_link( $it ); $p = $link !== '' ? strrpos( $t, $link ) : false;
		if ( $p === false ) { return array( $t, array() ); }
		$show = self::short_link( $link ); $t = substr( $t, 0, $p ) . $show . substr( $t, $p + strlen( $link ) );  /* byte offsets, as Bluesky counts them */
		return array( $t, array( array( 'index' => array( 'byteStart' => $p, 'byteEnd' => $p + strlen( $show ) ), 'features' => array( array( '$type' => 'app.bsky.richtext.facet#link', 'uri' => $link ) ) ) ) );
	}

	/** The record Bluesky keeps: the text, its facet, English, and the link preview (the item's title, its why or its say, and the card when given). */
	public static function record( $it, $thumb = null, $now = null ) {
		list( $t, $f ) = self::text( $it );
		$ext = array( 'uri' => DBD_Pack::share_link( $it ), 'title' => (string) ( $it['title'] ?? '' ), 'description' => (string) ( $it['why'] ?? $it['say'] ?? '' ) );
		if ( $thumb ) { $ext['thumb'] = $thumb; }
		$r = array( '$type' => 'app.bsky.feed.post', 'text' => $t, 'createdAt' => gmdate( 'Y-m-d\TH:i:s.000\Z', (int) ( $now ?? time() ) ), 'langs' => array( 'en' ) );
		if ( $f ) { $r['facets'] = $f; }
		$r['embed'] = array( '$type' => 'app.bsky.embed.external', 'external' => $ext );
		return $r;
	}

	/** A record key (TID): microseconds and a random clock id, 13 letters. Chosen once per post, before it's sent. */
	public static function tid( $us = null ) {
		$v = ( (int) ( $us ?? floor( microtime( true ) * 1000000 ) ) << 10 ) | random_int( 0, 1023 ); $s = '';
		for ( $i = 0; $i < 13; $i++ ) { $s = self::ALPHA[ $v & 31 ] . $s; $v >>= 5; }
		return $s;
	}

	/** Where a post lives on the web, from its at:// address. */
	public static function web( $uri, $handle ) {
		return preg_match( '#^at://[^/]+/app\.bsky\.feed\.post/([a-z0-9]+)$#', (string) $uri, $m ) ? 'https://bsky.app/profile/' . $handle . '/post/' . $m[1] : '';
	}

	public static function job( $key ) { $j = get_option( self::OPT_JOB . $key, null ); return is_array( $j ) ? $j : null; }
	/** The record as the database has it now, past this request's option cache; the plain read where there's no database (the harness). */
	private static function fresh_job( $key ) {
		global $wpdb;
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) ) { return self::job( $key ); }
		$v = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", self::OPT_JOB . $key ) );
		$j = $v === null ? null : maybe_unserialize( $v ); return is_array( $j ) ? $j : null;
	}
	private static function job_set( $key, $fields ) { update_option( self::OPT_JOB . $key, array_merge( self::job( $key ) ?: array(), $fields ), false ); }

	/** Post an item now: '' when it's out (or was already), or what went wrong (kept on its record for the item's line). $auto: the button's press. */
	public static function post( $key, $it, $auto = true ) {
		$j = self::job( $key ); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
		$c = self::conn(); if ( ! self::connected() ) { return 'Bluesky isn’t connected.'; }
		if ( ! self::lock( $key ) ) { return 'it’s already posting'; }
		try {
			/* read the record again now the lock is ours, from the database itself: a press that finished meanwhile has posted it, and this
			   request's option cache (which remembers a missing option too) wouldn't show that (Greptile, PR #149) */
			$j = self::fresh_job( $key ); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
			list( $p, $err ) = self::part( $key, (array) ( $j ?? array() ), function ( $thumb ) use ( $it ) { return self::record( $it, $thumb ); }, $it, 'rkey' );
			if ( $err !== '' ) { return self::fail( $key, $err ); }
			return self::sent( $key, $p['uri'], (string) $c['handle'], $auto );
		} finally { self::unlock( $key ); }
	}

	/** The lock on a record while it posts: false when another request holds it (one older than 5 minutes is taken over). */
	private static function lock( $key ) {
		$lock = 'dbd_bsky_lock_' . $key;
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 300 ) { return false; } update_option( $lock, time(), false ); }
		return true;
	}
	private static function unlock( $key ) { delete_option( 'dbd_bsky_lock_' . $key ); }

	/**
	 * One post that may have gone out before: array( array( uri, cid, rkey ), error ). Its record key sits in $j[$rk] (a thread part's, or the
	 * item's): when there is one, Bluesky is asked whether it went out (a lost reply is never posted twice); otherwise a new key is chosen and kept
	 * on the record first. $make( $thumb ) builds the record; $it's card, when it has one, is uploaded as the thumb.
	 */
	private static function part( $key, $j, $make, $it, $rk, $save = null ) {
		$c = self::conn(); $did = (string) $c['did']; $rkey = (string) ( $j[ $rk ] ?? '' );
		$save = $save ?? function ( $rkey ) use ( $key, $rk ) { self::job_set( $key, array( $rk => $rkey ) ); };
		if ( $rkey !== '' ) {
			list( $gc, $gj ) = self::call( 'com.atproto.repo.getRecord', array( 'repo' => $did, 'collection' => self::COL, 'rkey' => $rkey ), '', '', 'GET' );
			if ( $gc === 200 && ! empty( $gj['uri'] ) ) { return array( array( 'uri' => (string) $gj['uri'], 'cid' => (string) ( $gj['cid'] ?? '' ), 'rkey' => $rkey ), '' ); }
			if ( $gc !== 400 && $gc !== 404 ) { return array( null, 'Bluesky didn’t say whether the last try went out; try again in a minute' ); }
		} else { $rkey = self::tid(); $save( $rkey ); }
		$thumb = null; $card = (string) ( $it['card'] ?? '' );
		if ( $card !== '' ) {
			list( $f ) = DBD_Card::ensure( $card );
			if ( $f !== '' && filesize( $f ) <= self::$max_blob ) {
				list( , $bj, $err ) = self::authed( 'com.atproto.repo.uploadBlob', (string) file_get_contents( $f ), 'image/png' );
				if ( $err !== '' ) { return array( null, $err ); }
				$thumb = $bj['blob'] ?? null;
			}
		}
		list( , $rj, $err ) = self::authed( 'com.atproto.repo.createRecord', array( 'repo' => $did, 'collection' => self::COL, 'rkey' => $rkey, 'record' => $make( $thumb ) ) );
		if ( $err !== '' || empty( $rj['uri'] ) ) { return array( null, $err !== '' ? $err : 'Bluesky made no post' ); }
		return array( array( 'uri' => (string) $rj['uri'], 'cid' => (string) ( $rj['cid'] ?? '' ), 'rkey' => $rkey ), '' );
	}

	private static function sent( $key, $uri, $handle, $auto = false ) {
		$f = array( 'state' => 'sent', 'uri' => $uri, 'link' => self::web( $uri, $handle ), 'sent_at' => time(), 'err' => '', 'force' => false ); if ( $auto ) { $f['auto'] = true; }
		self::job_set( $key, $f ); return '';
	}
	private static function fail( $key, $err ) { self::job_set( $key, array( 'state' => 'failed: ' . $err, 'err' => $err ) ); return $err; }

	/* ------------------------------------------------------------------ the week's posts (SCOPE §82) */

	/** A slot's name in a sentence: Tuesday's is the thread; the rest as Instagram names them. */
	public static function label( $slot ) { return $slot === 'carousel' ? 'the thread' : DBD_Instagram::label( $slot ); }
	/** Sent with the button rather than on its day (a record from before 2.39.0 has no day either). */
	public static function by_button( $j ) { return ! empty( $j['auto'] ) || ! isset( $j['slot_at'] ); }
	public static function state_for( $season, $n, $slots ) { $s = array( 'jobs' => array() ); foreach ( $slots as $sl ) { $k = DBD_Instagram::key( $season, $n, $sl ); if ( ( $j = self::job( $k ) ) ) { $s['jobs'][ $k ] = $j; } } return $s; }

	/** After each sync: book the newest week's posts on Instagram's days and times, and book again any whose event went missing. */
	public static function note( $unused = null, $now = null ) {
		if ( ! self::on() || DBD_Data::archived() ) { return array(); }
		$n = DBD_Pack::newest(); if ( ! $n ) { return array(); }
		$now = $now ?? time(); $season = (int) DBD_Data::season(); $slots = array_keys( DBD_Instagram::plan( $n ) ); $times = array();
		foreach ( $slots as $sl ) { $times[ $sl ] = DBD_Instagram::slot_time( $n, $sl ); }
		$s0 = self::state_for( $season, $n, $slots ); list( $s, $book ) = DBD_Instagram::decide( $s0, $season, $n, $slots, $times, $now );
		/* each new record is added once (add_option fails when another request got there first), and only then booked; an item already sent
		   with Auto publish has its record, so its day is never booked */
		foreach ( $s['jobs'] as $key => $j ) { if ( ! isset( $s0['jobs'][ $key ] ) && ! add_option( self::OPT_JOB . $key, $j, '', false ) ) { unset( $book[ $key ] ); } }
		foreach ( $book as $key => $at ) { wp_schedule_single_event( $at, self::HOOK, array( $key ) ); }
		foreach ( $slots as $sl ) {
			$key = DBD_Instagram::key( $season, $n, $sl ); $j = $s['jobs'][ $key ] ?? array(); $st = (string) ( $j['state'] ?? '' );
			$late = empty( $j['force'] ) && $now > (int) ( $j['slot_at'] ?? $j['at'] ?? 0 ) + DBD_Instagram::LATE;
			if ( ! isset( $book[ $key ] ) && ! $late && ( $st === 'booked' || strpos( $st, 'retrying' ) === 0 ) && ! wp_next_scheduled( self::HOOK, array( $key ) ) ) {
				$at = max( (int) $now + 60, (int) ( $j['at'] ?? 0 ) ); wp_schedule_single_event( $at, self::HOOK, array( $key ) ); $book[ $key ] = $at;
			}
		}
		return $book;
	}

	/** The booked post: sent once, retried on failure. $force posts now whatever the time (Try again). */
	public static function send( $key, $force = false ) {
		$kp = DBD_Instagram::parse( $key ); $job = self::job( $key ); if ( ! $kp || ! $job ) { return; }
		$st = (string) ( $job['state'] ?? '' ); if ( $st === 'sent' ) { return; }
		if ( ! $force && $st !== 'booked' && strpos( $st, 'retrying' ) !== 0 ) { return; }  /* a press that failed took the day over */
		$force = $force || ! empty( $job['force'] );
		if ( ! self::on() ) { self::job_set( $key, array( 'state' => 'not posted: Bluesky posting is off or not connected' ) ); return; }
		list( $season, $n, $slot ) = $kp;
		if ( (int) DBD_Data::season() !== $season ) { self::job_set( $key, array( 'state' => 'not posted: a new season started' ) ); return; }
		if ( ! $force && time() > (int) ( $job['slot_at'] ?? $job['at'] ?? 0 ) + DBD_Instagram::LATE ) { self::job_set( $key, array( 'state' => 'not posted: more than a day late' ) ); return; }
		if ( $slot === 'carousel' ) { $err = self::thread( $key, $n ); }
		else { $it = DBD_Pack::find( $n, $slot ); $err = $it ? self::post( $key, $it, false ) : 'there’s no ' . self::label( $slot ) . ' for Week ' . $n; }
		if ( $err === '' || $err === 'it’s already posting' ) { return; }  /* another request has it */
		self::retry( $key, $err );
	}
	private static function retry( $key, $err ) {
		$job = self::job( $key ) ?: array(); $t = (int) ( $job['tries'] ?? 0 ) + 1;
		if ( $t < DBD_Instagram::TRIES ) { self::job_set( $key, array( 'tries' => $t, 'state' => 'retrying: ' . $err, 'err' => $err ) ); wp_schedule_single_event( time() + DBD_Instagram::RETRY, self::HOOK, array( $key ) ); return; }
		self::job_set( $key, array( 'tries' => $t, 'state' => 'failed: ' . $err, 'err' => $err ) );
		$kp = DBD_Instagram::parse( $key );
		wp_mail( DBD_Email::test_to(), 'Down by Down: Bluesky didn’t take ' . self::label( $kp[2] ?? 'carousel' ) . ' for Week ' . (int) ( $kp[1] ?? 0 ),
			'It failed ' . DBD_Instagram::TRIES . " times over an hour.\n\nThe last error: $err\n\nTry again on the Share pack tab: " . admin_url( 'admin.php?page=down-by-down-share' ) . "\n" );
	}

	/** The thread's first post: "Week 10 in five charts, one in each reply below. …" and the week page's link, short and clickable. */
	public static function intro( $n, $count, $now = null ) {
		$words = array( 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven' ); $link = DBD_Router::url( array( 'view' => 'week', 'week' => (int) $n ) );
		$t = 'Week ' . (int) $n . ' in ' . ( $words[ $count ] ?? (string) $count ) . ' charts, one in each reply below. Every chart and the numbers behind it: '; $p = strlen( $t ); $show = self::short_link( $link );
		return array( '$type' => self::COL, 'text' => $t . $show, 'createdAt' => gmdate( 'Y-m-d\TH:i:s.000\Z', (int) ( $now ?? time() ) ), 'langs' => array( 'en' ),
			'facets' => array( array( 'index' => array( 'byteStart' => $p, 'byteEnd' => $p + strlen( $show ) ), 'features' => array( array( '$type' => 'app.bsky.richtext.facet#link', 'uri' => $link ) ) ) ) );
	}

	/**
	 * Tuesday's thread: the first post, then each item with a square replying in pack order (each reply's parent the post before it, its root
	 * the first). Each part's record key, address and cid are kept on the record as it goes, so a retry carries on where the last one stopped.
	 */
	public static function thread( $key, $n ) {
		$items = DBD_Instagram::plan( $n )['carousel'] ?? array(); if ( count( $items ) < 2 ) { return 'there’s no thread for Week ' . (int) $n; }
		$c = self::conn(); if ( ! self::connected() ) { return 'Bluesky isn’t connected.'; }
		if ( ! self::lock( $key ) ) { return 'it’s already posting'; }
		try {
			$j = self::fresh_job( $key ) ?: array(); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
			$parts = array_values( (array) ( $j['parts'] ?? array() ) ); $root = null; $parent = null;
			for ( $i = 0; $i <= count( $items ); $i++ ) {
				$p = (array) ( $parts[ $i ] ?? array() );
				if ( empty( $p['uri'] ) || empty( $p['cid'] ) ) {
					$it = $i ? $items[ $i - 1 ] : array(); $r0 = $root; $pa = $parent;
					$make = function ( $thumb ) use ( $i, $n, $items, $it, $r0, $pa ) {
						if ( ! $i ) { return self::intro( $n, count( $items ) ); }
						return self::record( $it, $thumb ) + array( 'reply' => array( 'root' => $r0, 'parent' => $pa ) );
					};
					$save = function ( $rkey ) use ( $key, &$parts, $i ) { $parts[ $i ] = array( 'rkey' => $rkey ); self::job_set( $key, array( 'parts' => $parts ) ); };
					list( $got, $err ) = self::part( $key, $p, $make, $it, 'rkey', $save );
					if ( $err !== '' ) { return self::fail( $key, $err ); }
					if ( $got['cid'] === '' ) { return self::fail( $key, 'Bluesky didn’t give the post’s cid' ); }
					$parts[ $i ] = $p = $got; self::job_set( $key, array( 'parts' => $parts ) );
				}
				$ref = array( 'uri' => (string) $p['uri'], 'cid' => (string) $p['cid'] );
				if ( ! $i ) { $root = $ref; } $parent = $ref;
			}
			return self::sent( $key, (string) $parts[0]['uri'], (string) $c['handle'] );
		} finally { self::unlock( $key ); }
	}

	/* ------------------------------------------------------------------ the tab */

	/** Week $n's posts for the settings box: slot => array( dot, text, link, try-again key ). */
	public static function week_rows( $n ) {
		$season = (int) DBD_Data::season(); $out = array(); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); };
		foreach ( array_keys( DBD_Instagram::plan( $n ) ) as $sl ) {
			$key = DBD_Instagram::key( $season, $n, $sl ); $j = self::job( $key ); $lab = ucfirst( self::label( $sl ) ); $st = (string) ( $j['state'] ?? '' );
			if ( ! $j ) { $out[ $sl ] = array( '', $lab . ': ' . $when( DBD_Instagram::slot_time( $n, $sl ) ) . ', not booked yet', '', '' ); }
			elseif ( $st === 'sent' ) { $out[ $sl ] = array( 'ok', $lab . ': ' . ( self::by_button( $j ) ? 'sent with Auto publish ' : 'posted ' ) . $when( $j['sent_at'] ?? 0 ), (string) ( $j['link'] ?? '' ), '' ); }
			elseif ( $st === 'booked' ) { $out[ $sl ] = array( 'ok', $lab . ': booked for ' . $when( $j['at'] ), '', '' ); }
			elseif ( strpos( $st, 'retrying' ) === 0 ) { $out[ $sl ] = array( 'warn', $lab . ': retrying, ' . substr( $st, 10 ), '', '' ); }
			elseif ( strpos( $st, 'failed' ) === 0 ) { $out[ $sl ] = array( 'bad', $lab . ': didn’t post, ' . self::why( $j ), '', $key ); }
			else { $out[ $sl ] = array( '', $lab . ': ' . ( $st !== '' ? $st : 'posting…' ), '', '' ); }
		}
		return $out;
	}
	/** What went wrong with a failed record: its err, or the state's words. */
	public static function why( $j ) { $st = (string) ( $j['state'] ?? '' ); return (string) ( ( $j['err'] ?? '' ) !== '' ? $j['err'] : ( strpos( $st, 'failed: ' ) === 0 ? substr( $st, 8 ) : $st ) ); }

	/** The status line at the top of the tab: array( dot, text, link, try-again key ), or null when the weekly posts are off or Bluesky isn't connected. */
	public static function line( $n ) {
		if ( ! self::on() ) { return null; }
		$season = (int) DBD_Data::season(); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); }; $last = null; $next = null;
		foreach ( array_keys( DBD_Instagram::plan( $n ) ) as $sl ) {
			$key = DBD_Instagram::key( $season, $n, $sl ); $j = self::job( $key ); $st = (string) ( $j['state'] ?? '' );
			if ( strpos( $st, 'failed' ) === 0 && ! self::by_button( $j ) ) { return array( 'bad', 'Bluesky didn’t take ' . self::label( $sl ) . ': ' . self::why( $j ), '', $key ); }
			if ( $st === 'sent' && ( ! $last || (int) ( $j['sent_at'] ?? 0 ) >= (int) ( $last[1]['sent_at'] ?? 0 ) ) ) { $last = array( $sl, $j ); }
			if ( ! $next && ( $st === 'booked' || strpos( $st, 'retrying' ) === 0 ) ) { $next = array( $sl, $j ); }
		}
		$txt = 'Bluesky: ' . ( $last ? 'posted ' . self::label( $last[0] ) . ' ' . $when( $last[1]['sent_at'] ?? 0 ) . '.' : 'nothing posted for Week ' . (int) $n . ' yet.' )
			. ( $next ? ' Next: ' . self::label( $next[0] ) . ', ' . $when( $next[1]['at'] ) . '.' : '' );
		return array( 'ok', $txt, (string) ( $last[1]['link'] ?? '' ), '' );
	}

	public static function again_url( $key ) { return wp_nonce_url( admin_url( 'admin-post.php?action=dbd_bsky_again&key=' . rawurlencode( $key ) ), 'dbd_bsky_again_' . $key ); }
	public static function again_action() {
		$key = sanitize_text_field( wp_unslash( (string) ( $_GET['key'] ?? '' ) ) ); check_admin_referer( 'dbd_bsky_again_' . $key ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		$back = function ( $args ) { wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'down-by-down-share' ), $args ), admin_url( 'admin.php' ) ) . '#dbd-pack-settings' ); exit; };
		if ( ! DBD_Instagram::parse( $key ) || ! self::job( $key ) ) { $back( array( 'pack' => 'igerror', 'pack_err' => rawurlencode( 'That post isn’t there.' ) ) ); }
		self::job_set( $key, array( 'state' => 'retrying: again', 'tries' => 0, 'force' => true ) );
		wp_clear_scheduled_hook( self::HOOK, array( $key ) ); wp_schedule_single_event( time(), self::HOOK, array( $key ) );
		$back( array( 'pack' => 'bsagain' ) );
	}
}
