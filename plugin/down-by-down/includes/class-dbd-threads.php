<?php
/**
 * Threads posting (2.41.0, SCOPE §84), through Meta's Threads API (graph.threads.net) with a token Gene makes in Meta's Graph API Explorer
 * and pastes once with the app's Threads App secret. Treated just like the other networks (Gene, 2026-11-20):
 *   - Each post is the item's long takeaway with the page as its preview card (link_attachment); no link in the text. Tuesday is a thread
 *     (a first post naming the week, then each item with a square as a numbered reply), Wednesday to Sunday one item a day, 9:00 AM Eastern.
 *     The button (Auto publish, §81) posts to Threads too.
 *   - Posting is Instagram's two steps (a container, then threads_publish). The container is kept on the item's record, so a retry first asks
 *     whether it already went out, and finds the post among the account's latest posts or replies instead of posting it again.
 *   - The pasted token is turned into a 60-day one and renewed every week; a failed renewal shows on the tab and the Overview.
 *   - Booking, the cutoff, retries, the note and Try again copy the others', with the record locked from its first read to its last write.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Threads {

	const OPT_CONN = 'dbd_th_conn';  /* the connection: token, secret, id, username, expires, checked, err (not autoloaded) */
	const OPT_SET  = 'dbd_th_set';   /* the weekly switch: array( 'on' => bool ), on unless unticked */
	const OPT_JOB  = 'dbd_th_job_';  /* one record per item */
	const HOOK     = 'dbd_th_send';
	const HOOK_REFRESH = 'dbd_th_refresh';
	const HOST     = 'https://graph.threads.net/';
	const VER      = 'v1.0';
	const MAX      = 500;            /* a post's characters */

	/** How to wait between status checks; the harness swaps it for one that doesn't. */
	public static $wait = 'sleep';

	public static function init() {
		add_action( 'dbd_synced', array( __CLASS__, 'note' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_action( self::HOOK_REFRESH, array( __CLASS__, 'refresh' ) );
		add_action( 'admin_post_dbd_th_again', array( __CLASS__, 'again_action' ) );
		add_action( 'init', array( __CLASS__, 'keep_refreshing' ) );
	}
	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK ); wp_clear_scheduled_hook( self::HOOK_REFRESH ); }
	/** The weekly renewal runs while a token is kept. */
	public static function keep_refreshing() { if ( self::conn() && ! wp_next_scheduled( self::HOOK_REFRESH ) ) { wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::HOOK_REFRESH ); } }

	/* ------------------------------------------------------------------ the connection */

	public static function settings() { $o = get_option( self::OPT_SET, null ); return array( 'on' => is_array( $o ) ? ! empty( $o['on'] ) : true ); }
	public static function sanitize( $v ) { return array( 'on' => is_array( $v ) && ! empty( $v['on'] ) ); }
	public static function conn() { $c = get_option( self::OPT_CONN, null ); return is_array( $c ) && ! empty( $c['token'] ) ? $c : null; }
	public static function connected() { $c = self::conn(); return $c && ! empty( $c['id'] ) && (int) ( $c['expires'] ?? 0 ) > time(); }
	public static function on() { return self::settings()['on'] && self::connected(); }

	/**
	 * One call: array( HTTP code, JSON, error text ('' when fine) ). GETs carry their fields in the address, POSTs in the body. $versioned
	 * false is for the token endpoints, which sit outside the version.
	 */
	public static function call( $method, $path, $args = array(), $token = null, $versioned = true ) {
		$args = (array) $args; $args['access_token'] = (string) ( $token ?? ( self::conn()['token'] ?? '' ) );
		$url = self::HOST . ( $versioned ? self::VER . '/' : '' ) . ltrim( $path, '/' ); $req = array( 'method' => $method, 'timeout' => 30 );
		if ( $method === 'GET' ) { $url = add_query_arg( array_map( 'rawurlencode', $args ), $url ); } else { $req['body'] = $args; }
		$r = wp_remote_request( $url, $req );
		if ( is_wp_error( $r ) ) { return array( 0, array(), 'Threads could not be reached' . ( is_object( $r ) && method_exists( $r, 'get_error_message' ) ? ': ' . $r->get_error_message() : '' ) ); }
		$code = (int) wp_remote_retrieve_response_code( $r ); $j = json_decode( (string) wp_remote_retrieve_body( $r ), true ); $j = is_array( $j ) ? $j : array();
		$err = ( $code >= 200 && $code < 300 && empty( $j['error'] ) ) ? '' : 'Threads answered ' . $code . ( ! empty( $j['error']['message'] ) ? ': ' . $j['error']['message'] : '' );
		return array( $code, $j, $err );
	}

	/**
	 * A pasted token and the app's Threads App secret: checked for the account under Your accounts, made into a 60-day token, then kept.
	 * A token from the app's User Token Generator is a 60-day one already and can't be made into one (Threads answers 400): it works (checked
	 * just above), so it's kept as it is, good for 60 days and renewed every week like any other (2.41.1). Returns '' or what went wrong.
	 */
	public static function connect( $token, $secret ) {
		$token = trim( (string) $token ); $secret = trim( (string) $secret );
		$h = DBD_Pack::our_th(); if ( $h === '' ) { return 'Put the Threads account under Your accounts first.'; }
		list( , $j, $err ) = self::call( 'GET', 'me', array( 'fields' => 'id,username' ), $token );
		if ( $err !== '' || empty( $j['id'] ) ) { return 'That Threads token didn’t work. ' . ( $err !== '' ? $err : 'Threads didn’t say whose it is.' ); }
		$u = (string) ( $j['username'] ?? '' );
		if ( strtolower( $u ) !== strtolower( $h ) ) { return 'That Threads token is for @' . $u . ', not @' . $h . '. Generate it as @' . $h . '.'; }
		list( , $x, $err ) = self::call( 'GET', 'access_token', array( 'grant_type' => 'th_exchange_token', 'client_secret' => $secret ), $token, false );
		$long = $err === '' && ! empty( $x['access_token'] );
		update_option( self::OPT_CONN, array( 'token' => $long ? (string) $x['access_token'] : $token, 'secret' => $secret, 'id' => (string) $j['id'], 'username' => $u,
			'expires' => time() + ( $long ? (int) ( $x['expires_in'] ?? 60 * DAY_IN_SECONDS ) : 60 * DAY_IN_SECONDS ), 'checked' => time(), 'err' => '' ), false );
		self::keep_refreshing();
		return '';
	}
	public static function disconnect() { delete_option( self::OPT_CONN ); wp_clear_scheduled_hook( self::HOOK_REFRESH ); }

	/**
	 * The settings box's Threads part: the switch, then disconnect, or a token pasted (with the App secret, or the one kept), or forgetting a
	 * token for an account that's no longer the one under Your accounts (checked first, whatever else was pasted). Returns '' or what went wrong.
	 */
	public static function save( $p ) {
		update_option( self::OPT_SET, self::sanitize( is_array( $p[ self::OPT_SET ] ?? null ) ? $p[ self::OPT_SET ] : array() ) );
		if ( ! empty( $p['dbd_th_disconnect'] ) ) { self::disconnect(); return ''; }
		$c = self::conn(); $gone = '';
		if ( $c && strtolower( (string) $c['username'] ) !== strtolower( DBD_Pack::our_th() ) ) {
			self::disconnect(); $gone = DBD_Pack::our_th() !== '' ? 'The Threads account changed, so its token was forgotten. Paste one for @' . DBD_Pack::our_th() . '.' : ''; $c = null;
		}
		$tok = is_scalar( $p['dbd_th_token_new'] ?? null ) ? trim( (string) $p['dbd_th_token_new'] ) : '';
		$sec = is_scalar( $p['dbd_th_secret_new'] ?? null ) ? trim( (string) $p['dbd_th_secret_new'] ) : '';
		if ( $tok !== '' ) {
			$sec = $sec !== '' ? $sec : (string) ( $c['secret'] ?? '' );
			if ( $sec === '' ) { return 'Paste the app’s Threads App secret with the token.'; }
			return self::connect( $tok, $sec );
		}
		if ( $sec !== '' && $c ) { $c['secret'] = $sec; update_option( self::OPT_CONN, $c, false ); }
		return $gone;
	}

	/** The weekly renewal: a fresh 60 days. A failure is kept on the connection, and the tab and the Overview say so. */
	public static function refresh() {
		$c = self::conn(); if ( ! $c ) { return false; }
		list( , $j, $err ) = self::call( 'GET', 'refresh_access_token', array( 'grant_type' => 'th_refresh_token' ), $c['token'], false );
		if ( $err === '' && ! empty( $j['access_token'] ) ) {
			update_option( self::OPT_CONN, array_merge( $c, array( 'token' => (string) $j['access_token'], 'expires' => time() + (int) ( $j['expires_in'] ?? 60 * DAY_IN_SECONDS ), 'checked' => time(), 'err' => '' ) ), false );
			return true;
		}
		$c['err'] = $err !== '' ? $err : 'Threads didn’t send a new token.'; $c['checked'] = time(); update_option( self::OPT_CONN, $c, false ); return false;
	}

	/** A line for the Overview's "Needs a look", or '': a failed renewal, or a token within a week of running out. */
	public static function warning() {
		$c = self::conn(); if ( ! $c ) { return ''; }
		$left = (int) ( $c['expires'] ?? 0 ) - time();
		if ( $left <= 0 ) { return 'The Threads token ran out. Paste a new one on the Share pack tab.'; }
		if ( ! empty( $c['err'] ) ) { return 'The Threads token didn’t renew (' . $c['err'] . '). It runs out ' . wp_date( 'M j', (int) $c['expires'] ) . '.'; }
		if ( $left < 7 * DAY_IN_SECONDS ) { return 'The Threads token runs out ' . wp_date( 'M j', (int) $c['expires'] ) . '. Paste a new one on the Share pack tab.'; }
		return '';
	}

	/* ------------------------------------------------------------------ the posts */

	/** An item's post text: its long takeaway (cut at a word if it ever runs past 500). */
	public static function text( $it, $pre = '' ) {
		$t = $pre . (string) ( $it['tl'] ?? $it['title'] ?? '' ); if ( mb_strlen( $t ) <= self::MAX ) { return $t; }
		return rtrim( (string) preg_replace( '/\s+\S*$/u', '', mb_substr( $t, 0, self::MAX - 1 ) ), ' ,;:' ) . '…';
	}
	/** The thread's first post: "Week 10 in five charts, one in each reply below. …", the week page as its card. */
	public static function intro( $n, $count ) {
		$words = array( 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven' );
		return 'Week ' . (int) $n . ' in ' . ( $words[ $count ] ?? (string) $count ) . ' charts, one in each reply below. Every chart and the numbers behind it:';
	}

	/**
	 * What a post sends (SCOPE §85): the tall graphic as an image with the link in the text, or, when it can't be drawn, the text with the page
	 * as its preview card, as before. $pre: a thread reply's "1/5 ".
	 */
	public static function params( $n, $it, $pre = '' ) {
		$img = DBD_Tall::url( $n, $it ); $link = DBD_Pack::share_link( $it );
		if ( $img === '' ) { return array( 'media_type' => 'TEXT', 'text' => self::text( $it, $pre ), 'link_attachment' => $link ); }
		$room = self::MAX - mb_strlen( $link ) - 1; $t = $pre . (string) ( $it['tl'] ?? $it['title'] ?? '' );
		if ( mb_strlen( $t ) > $room ) { $t = rtrim( (string) preg_replace( '/\s+\S*$/u', '', mb_substr( $t, 0, $room - 1 ) ), ' ,;:' ) . '…'; }
		return array( 'media_type' => 'IMAGE', 'image_url' => $img, 'text' => $t . ' ' . $link );
	}

	/** A container's status (FINISHED, IN_PROGRESS, ERROR, EXPIRED, PUBLISHED), or '' when Threads didn't say. */
	private static function status_of( $id ) { list( , $j, $err ) = self::call( 'GET', (string) $id, array( 'fields' => 'status' ) ); return $err === '' ? (string) ( $j['status'] ?? '' ) : ''; }
	/** Wait for a container to finish: up to six checks, five seconds apart. */
	private static function ready( $id ) {
		$st = '';
		for ( $i = 0; $i < 6; $i++ ) { $st = self::status_of( $id ); if ( $st !== 'IN_PROGRESS' && $st !== '' ) { return $st; } call_user_func( self::$wait, 5 ); }
		return $st;
	}
	/** The account's latest post (or reply) with this text: array( post ID or '', error ). */
	public static function find( $text, $reply ) {
		$c = self::conn(); if ( ! $c ) { return array( '', 'Threads isn’t connected.' ); }
		list( , $j, $err ) = self::call( 'GET', $c['id'] . '/' . ( $reply ? 'replies' : 'threads' ), array( 'fields' => 'id,text', 'limit' => '10' ) );
		if ( $err !== '' ) { return array( '', $err ); }
		foreach ( (array) ( $j['data'] ?? array() ) as $p ) { if ( DBD_X::norm( $p['text'] ?? '' ) === DBD_X::norm( $text ) && ! empty( $p['id'] ) ) { return array( (string) $p['id'], '' ); } }
		return array( '', '' );
	}

	/**
	 * One post that may have gone out before: array( post ID, error ). $p is its part of the record: the container a try made is kept ($keep),
	 * so the next try asks Threads about it first. Published means it went out (found among the latest posts, never posted again); finished
	 * means publish it; an error or an expired one is replaced; no answer waits for the next try.
	 */
	private static function part( $p, $params, $keep ) {
		$c = self::conn(); $uid = (string) $c['id']; $cid = (string) ( $p['container'] ?? '' ); $reply = isset( $params['reply_to_id'] );
		$found = function () use ( $params, $reply ) {
			list( $id, $err ) = self::find( $params['text'], $reply );
			return $id !== '' ? array( $id, '' ) : array( '', 'Threads says the last try went out but doesn’t list it yet' . ( $err !== '' ? ' (' . $err . ')' : '' ) . '; checking again later' );
		};
		if ( $cid !== '' ) {
			$st = self::status_of( $cid );
			if ( $st === 'PUBLISHED' ) { return $found(); }
			if ( $st === '' ) { return array( '', 'Threads didn’t say whether the last try went out; checking again later' ); }
			if ( $st === 'ERROR' || $st === 'EXPIRED' ) { $cid = ''; }
		}
		if ( $cid === '' ) {
			list( , $j, $err ) = self::call( 'POST', $uid . '/threads', $params );
			if ( $err !== '' || empty( $j['id'] ) ) { return array( '', $err !== '' ? $err : 'Threads made no post' ); }
			$cid = (string) $j['id']; $keep( $cid );
		}
		$st = self::ready( $cid );
		if ( $st === 'PUBLISHED' ) { return $found(); }
		if ( $st !== 'FINISHED' ) { return array( '', 'the post isn’t ready (' . ( $st !== '' ? $st : 'no answer' ) . ')' ); }
		list( , $j, $err ) = self::call( 'POST', $uid . '/threads_publish', array( 'creation_id' => $cid ) );
		if ( $err === '' && ! empty( $j['id'] ) ) { return array( (string) $j['id'], '' ); }
		if ( self::status_of( $cid ) === 'PUBLISHED' ) { return $found(); }  /* the reply was lost but it went out */
		return array( '', $err !== '' ? $err : 'Threads didn’t publish it' );
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
	private static function lock( $key ) {
		$lock = 'dbd_th_lock_' . $key;
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 600 ) { return false; } update_option( $lock, time(), false ); }
		return true;
	}
	private static function unlock( $key ) { delete_option( 'dbd_th_lock_' . $key ); }

	/** Post an item now: '' when it's out (or was already), or what went wrong (kept on its record). $auto: the button's press. $held: send() has the lock. */
	public static function post( $key, $it, $auto = true, $held = false ) {
		$j = self::job( $key ); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
		if ( ! self::connected() ) { return 'Threads isn’t connected.'; }
		if ( ! $held && ! self::lock( $key ) ) { return 'it’s already posting'; }
		try {
			$j = self::fresh_job( $key ) ?: array(); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
			/* the text and card a try used are kept with its container, so a retry asks about and looks for the same post */
			$kp = DBD_Instagram::parse( $key ); $params = (array) ( $j['params'] ?? self::params( (int) ( $kp[1] ?? 0 ), $it ) );
			list( $id, $err ) = self::part( $j, $params, function ( $cid ) use ( $key, $params ) { self::job_set( $key, array( 'container' => $cid, 'params' => $params ) ); } );
			if ( $err !== '' ) { return self::fail( $key, $err ); }
			return self::sent( $key, $id, $auto );
		} finally { if ( ! $held ) { self::unlock( $key ); } }
	}
	private static function sent( $key, $id, $auto = false ) {
		list( , $j ) = self::call( 'GET', $id, array( 'fields' => 'permalink' ) );
		$f = array( 'state' => 'sent', 'id' => $id, 'link' => (string) ( $j['permalink'] ?? '' ), 'sent_at' => time(), 'err' => '', 'force' => false ); if ( $auto ) { $f['auto'] = true; }
		self::job_set( $key, $f ); return '';
	}
	private static function fail( $key, $err ) { self::job_set( $key, array( 'state' => 'failed: ' . $err, 'err' => $err ) ); return $err; }

	/* ------------------------------------------------------------------ the week's posts */

	public static function label( $slot ) { return $slot === 'carousel' ? 'the thread' : DBD_Instagram::label( $slot ); }
	public static function by_button( $j ) { return ! empty( $j['auto'] ) || ! isset( $j['slot_at'] ); }
	public static function why( $j ) { $st = (string) ( $j['state'] ?? '' ); return (string) ( ( $j['err'] ?? '' ) !== '' ? $j['err'] : ( strpos( $st, 'failed: ' ) === 0 ? substr( $st, 8 ) : $st ) ); }
	public static function state_for( $season, $n, $slots ) { $s = array( 'jobs' => array() ); foreach ( $slots as $sl ) { $k = DBD_Instagram::key( $season, $n, $sl ); if ( ( $j = self::job( $k ) ) ) { $s['jobs'][ $k ] = $j; } } return $s; }

	/** After each sync: book the newest week's posts on Instagram's days and times, and book again any whose event went missing. */
	public static function note( $unused = null, $now = null ) {
		if ( ! self::on() || DBD_Data::archived() ) { return array(); }
		$n = DBD_Pack::newest(); if ( ! $n ) { return array(); }
		$now = $now ?? time(); $season = (int) DBD_Data::season(); $slots = array_keys( DBD_Instagram::plan( $n ) ); $times = array();
		foreach ( $slots as $sl ) { $times[ $sl ] = DBD_Instagram::slot_time( $n, $sl ); }
		$s0 = self::state_for( $season, $n, $slots ); list( $s, $book ) = DBD_Instagram::decide( $s0, $season, $n, $slots, $times, $now );
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

	/** The booked post: sent once, retried on failure, the record locked from its first read to its last write. $force: Try again. */
	public static function send( $key, $force = false ) {
		$kp = DBD_Instagram::parse( $key ); if ( ! $kp || ! self::job( $key ) ) { return; }
		if ( ! self::lock( $key ) ) { return; }
		try {
			$job = self::fresh_job( $key ) ?: array(); $st = (string) ( $job['state'] ?? '' ); if ( $st === 'sent' ) { return; }
			if ( ! $force && $st !== 'booked' && strpos( $st, 'retrying' ) !== 0 ) { return; }
			$force = $force || ! empty( $job['force'] );
			if ( ! self::on() ) { self::job_set( $key, array( 'state' => 'not posted: Threads posting is off or not connected' ) ); return; }
			list( $season, $n, $slot ) = $kp;
			if ( (int) DBD_Data::season() !== $season ) { self::job_set( $key, array( 'state' => 'not posted: a new season started' ) ); return; }
			if ( ! $force && time() > (int) ( $job['slot_at'] ?? $job['at'] ?? 0 ) + DBD_Instagram::LATE ) { self::job_set( $key, array( 'state' => 'not posted: more than a day late' ) ); return; }
			if ( $slot === 'carousel' ) { $err = self::thread( $key, $n, true ); }
			else { $it = DBD_Pack::find( $n, $slot ); $err = $it ? self::post( $key, $it, false, true ) : 'there’s no ' . self::label( $slot ) . ' for Week ' . $n; }
			if ( $err !== '' ) { self::retry( $key, $err ); }
		} finally { self::unlock( $key ); }
	}
	private static function retry( $key, $err ) {
		$job = self::job( $key ) ?: array(); $t = (int) ( $job['tries'] ?? 0 ) + 1;
		if ( $t < DBD_Instagram::TRIES ) { self::job_set( $key, array( 'tries' => $t, 'state' => 'retrying: ' . $err, 'err' => $err ) ); wp_schedule_single_event( time() + DBD_Instagram::RETRY, self::HOOK, array( $key ) ); return; }
		self::job_set( $key, array( 'tries' => $t, 'state' => 'failed: ' . $err, 'err' => $err ) );
		$kp = DBD_Instagram::parse( $key );
		wp_mail( DBD_Email::test_to(), 'Down by Down: Threads didn’t take ' . self::label( $kp[2] ?? 'carousel' ) . ' for Week ' . (int) ( $kp[1] ?? 0 ),
			'It failed ' . DBD_Instagram::TRIES . " times over an hour.\n\nThe last error: $err\n\nTry again on the Share pack tab: " . admin_url( 'admin.php?page=down-by-down-share' ) . "\n" );
	}

	/**
	 * Tuesday's thread: the first post (the week page as its card), then each item with a square as a numbered reply to the post before it;
	 * if Threads won't take a reply to a reply, that one goes under the first post. The items are chosen on the first try and kept, and each
	 * part's container, text and post ID are kept as it goes, so a retry carries on where the last one stopped.
	 */
	public static function thread( $key, $n, $held = false ) {
		if ( ! self::connected() ) { return 'Threads isn’t connected.'; }
		if ( ! $held && ! self::lock( $key ) ) { return 'it’s already posting'; }
		try {
			$j = self::fresh_job( $key ) ?: array(); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
			if ( isset( $j['items'] ) && is_array( $j['items'] ) ) {
				$items = array(); foreach ( $j['items'] as $k ) { $it = DBD_Pack::find( $n, (string) $k ); if ( ! $it ) { return self::fail( $key, self::label( (string) $k ) . ' isn’t in the Week ' . (int) $n . ' pack any more' ); } $items[] = $it; }
			} else {
				$items = DBD_Instagram::plan( $n )['carousel'] ?? array();
				if ( count( $items ) >= 2 ) { self::job_set( $key, array( 'items' => array_map( function ( $it ) { return (string) $it['k']; }, $items ) ) ); }
			}
			if ( count( $items ) < 2 ) { return self::fail( $key, 'there’s no thread for Week ' . (int) $n ); }
			$parts = array_values( (array) ( $j['parts'] ?? array() ) ); $count = count( $items ); $root = ''; $prev = '';
			for ( $i = 0; $i <= $count; $i++ ) {
				$p = (array) ( $parts[ $i ] ?? array() );
				if ( empty( $p['id'] ) ) {
					$params = (array) ( $p['params'] ?? ( $i
						? self::params( $n, $items[ $i - 1 ], $i . '/' . $count . ' ' ) + array( 'reply_to_id' => $prev )
						: array( 'media_type' => 'TEXT', 'text' => self::intro( $n, $count ), 'link_attachment' => DBD_Router::url( array( 'view' => 'week', 'week' => (int) $n ) ) ) ) );
					$keep = function ( $cid ) use ( $key, &$parts, $i, &$params ) { $parts[ $i ] = array( 'container' => $cid, 'params' => $params ); self::job_set( $key, array( 'parts' => $parts ) ); };
					list( $id, $err ) = self::part( $p, $params, $keep );
					/* refused (a 400, no container made) when replying to a reply: it goes under the first post instead; a server error just retries */
					if ( $err !== '' && strpos( $err, 'Threads answered 400' ) === 0 && $i > 1 && empty( $p['container'] ) && empty( $parts[ $i ]['container'] ) && ( $params['reply_to_id'] ?? '' ) !== $root ) {
						$params['reply_to_id'] = $root; list( $id, $err ) = self::part( array(), $params, $keep );
					}
					if ( $err !== '' ) { return self::fail( $key, $err ); }
					$parts[ $i ] = array( 'id' => $id ); self::job_set( $key, array( 'parts' => $parts ) );
				}
				$prev = (string) $parts[ $i ]['id']; if ( ! $i ) { $root = $prev; }
			}
			return self::sent( $key, (string) $parts[0]['id'] );
		} finally { if ( ! $held ) { self::unlock( $key ); } }
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

	/** The status line at the top of the tab: array( dot, text, link, try-again key ), or null when the weekly posts are off or Threads isn't connected. */
	public static function line( $n ) {
		if ( ! self::settings()['on'] || ! self::conn() ) { return null; }
		if ( ! self::connected() ) { return array( 'bad', 'Threads: the token ran out. Paste a new one in the settings.', '', '' ); }
		$season = (int) DBD_Data::season(); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); }; $last = null; $next = null;
		foreach ( array_keys( DBD_Instagram::plan( $n ) ) as $sl ) {
			$key = DBD_Instagram::key( $season, $n, $sl ); $j = self::job( $key ); $st = (string) ( $j['state'] ?? '' );
			if ( strpos( $st, 'failed' ) === 0 && ! self::by_button( $j ) ) { return array( 'bad', 'Threads didn’t take ' . self::label( $sl ) . ': ' . self::why( $j ), '', $key ); }
			if ( $st === 'sent' && ( ! $last || (int) ( $j['sent_at'] ?? 0 ) >= (int) ( $last[1]['sent_at'] ?? 0 ) ) ) { $last = array( $sl, $j ); }
			if ( ! $next && ( $st === 'booked' || strpos( $st, 'retrying' ) === 0 ) ) { $next = array( $sl, $j ); }
		}
		$txt = 'Threads: ' . ( $last ? 'posted ' . self::label( $last[0] ) . ' ' . $when( $last[1]['sent_at'] ?? 0 ) . '.' : 'nothing posted for Week ' . (int) $n . ' yet.' )
			. ( $next ? ' Next: ' . self::label( $next[0] ) . ', ' . $when( $next[1]['at'] ) . '.' : '' );
		$warn = self::warning();
		return $warn !== '' ? array( 'warn', $txt . ' ' . $warn, (string) ( $last[1]['link'] ?? '' ), '' ) : array( 'ok', $txt, (string) ( $last[1]['link'] ?? '' ), '' );
	}

	public static function again_url( $key ) { return wp_nonce_url( admin_url( 'admin-post.php?action=dbd_th_again&key=' . rawurlencode( $key ) ), 'dbd_th_again_' . $key ); }
	public static function again_action() {
		$key = sanitize_text_field( wp_unslash( (string) ( $_GET['key'] ?? '' ) ) ); check_admin_referer( 'dbd_th_again_' . $key ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		$back = function ( $args ) { wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'down-by-down-share' ), $args ), admin_url( 'admin.php' ) ) . '#dbd-pack-settings' ); exit; };
		if ( ! DBD_Instagram::parse( $key ) || ! self::job( $key ) ) { $back( array( 'pack' => 'igerror', 'pack_err' => rawurlencode( 'That post isn’t there.' ) ) ); }
		self::job_set( $key, array( 'state' => 'retrying: again', 'tries' => 0, 'force' => true ) );
		wp_clear_scheduled_hook( self::HOOK, array( $key ) ); wp_schedule_single_event( time() + 1, self::HOOK, array( $key ) );
		$back( array( 'pack' => 'thagain' ) );
	}
}
