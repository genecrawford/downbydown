<?php
/**
 * Bluesky posting for Auto publish (2.38.0, SCOPE §81), through the AT Protocol with an app password Gene makes in Bluesky and pastes once.
 *   - A post is the pack's Bluesky text with its link shortened for reading and made clickable (a link facet, UTF-8 byte offsets), and the
 *     item's card as the link preview (app.bsky.embed.external, the card uploaded as its thumb). A card over Bluesky's 1 MB goes without the image.
 *   - Each item posts once: a record per item ('dbd_bsky_job_2026-w10-rush'). The post's record key is chosen before it's sent and kept, so
 *     when a reply is lost, the next press finds the post that went out instead of posting it again.
 *   - The session (createSession) is kept for an hour; a refused one is made again once.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Bluesky {

	const OPT_CONN = 'dbd_bsky_conn';    /* the connection: pass, handle, did, checked (not autoloaded) */
	const OPT_JOB  = 'dbd_bsky_job_';    /* one record per item */
	const SESSION  = 'dbd_bsky_session'; /* the session, an hour */
	const API      = 'https://bsky.social/xrpc/';
	const ALPHA    = '234567abcdefghijklmnopqrstuvwxyz';  /* a record key's letters (TID) */

	/** The largest card Bluesky takes as a thumb; the harness lowers it. */
	public static $max_blob = 1000000;

	/* ------------------------------------------------------------------ the connection */

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

	/** Post an item now: '' when it's out (or was already), or what went wrong (kept on its record for the item's line). */
	public static function post( $key, $it ) {
		$j = self::job( $key ); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
		$c = self::conn(); if ( ! self::connected() ) { return 'Bluesky isn’t connected.'; }
		$lock = 'dbd_bsky_lock_' . $key;
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 300 ) { return 'it’s already posting'; } update_option( $lock, time(), false ); }
		try {
			/* read the record again now the lock is ours, from the database itself: a press that finished meanwhile has posted it, and this
			   request's option cache (which remembers a missing option too) wouldn't show that (Greptile, PR #149) */
			$j = self::fresh_job( $key ); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
			$did = (string) $c['did']; $col = 'app.bsky.feed.post'; $rkey = (string) ( $j['rkey'] ?? '' );
			if ( $rkey !== '' ) {  /* an earlier try: did it go out? (a lost reply is never posted twice) */
				list( $gc, $gj ) = self::call( 'com.atproto.repo.getRecord', array( 'repo' => $did, 'collection' => $col, 'rkey' => $rkey ), '', '', 'GET' );
				if ( $gc === 200 && ! empty( $gj['uri'] ) ) { return self::sent( $key, (string) $gj['uri'], (string) $c['handle'] ); }
				if ( $gc !== 400 && $gc !== 404 ) { return self::fail( $key, 'Bluesky didn’t say whether the last try went out; try again in a minute' ); }
			} else { $rkey = self::tid(); self::job_set( $key, array( 'rkey' => $rkey ) ); }
			$thumb = null; $card = (string) ( $it['card'] ?? '' );
			if ( $card !== '' ) {
				list( $f ) = DBD_Card::ensure( $card );
				if ( $f !== '' && filesize( $f ) <= self::$max_blob ) {
					list( , $bj, $err ) = self::authed( 'com.atproto.repo.uploadBlob', (string) file_get_contents( $f ), 'image/png' );
					if ( $err !== '' ) { return self::fail( $key, $err ); }
					$thumb = $bj['blob'] ?? null;
				}
			}
			list( , $rj, $err ) = self::authed( 'com.atproto.repo.createRecord', array( 'repo' => $did, 'collection' => $col, 'rkey' => $rkey, 'record' => self::record( $it, $thumb ) ) );
			if ( $err !== '' || empty( $rj['uri'] ) ) { return self::fail( $key, $err !== '' ? $err : 'Bluesky made no post' ); }
			return self::sent( $key, (string) $rj['uri'], (string) $c['handle'] );
		} finally { delete_option( $lock ); }
	}
	private static function sent( $key, $uri, $handle ) { self::job_set( $key, array( 'state' => 'sent', 'uri' => $uri, 'link' => self::web( $uri, $handle ), 'sent_at' => time(), 'err' => '' ) ); return ''; }
	private static function fail( $key, $err ) { self::job_set( $key, array( 'state' => 'failed', 'err' => $err, 'at' => time() ) ); return $err; }
}
