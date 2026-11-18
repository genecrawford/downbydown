<?php
/**
 * Instagram posts itself (2.34.0, SCOPE §76), through the Instagram API with Instagram Login (instagram_business_content_publish).
 * Each finished week: Tuesday 9:00 AM Eastern a carousel of the pack's item squares with one caption, then one item a day,
 * Wednesday to Sunday at 9:00 AM (swing, usage riser, best and worst offense, target-share mover, week leaders), each its square
 * and its caption. The squares go as JPEG (DBD_Card serves them with &fmt=jpg), fetched by Instagram from their public address.
 *   - A sync that brings in a finished week books its six posts once. A slot more than a day past is skipped, not posted late.
 *   - Publishing the way Meta asks: containers (a carousel's children first), then media_publish. A container is kept on its job, so a
 *     retry first asks whether it already went out (no double posts) or is ready, before making a new one.
 *   - A failed post retries every 10 minutes, 7 tries; then a note to the test address and Try again on the Share pack tab.
 *   - The token (pasted once on the Share pack tab) lasts 60 days; it's renewed every week, and a failed renewal shows on the tab
 *     and the Overview.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Instagram {

	const OPT       = 'dbd_ig_post';   /* the record: array( 'jobs' => 'season-wN-slot' => job ) */
	const OPT_SET   = 'dbd_ig_set';    /* the switch: array( 'on' => bool ) */
	const OPT_TOKEN = 'dbd_ig_token';  /* the connection: token, user_id, username, expires, checked, err (not autoloaded) */
	const HOOK      = 'dbd_ig_send';
	const HOOK_REFRESH = 'dbd_ig_refresh';
	const API       = 'https://graph.instagram.com/';
	const VER       = 'v24.0';
	const LATE      = 86400;          /* more than a day past its slot, a post is skipped */
	const TRIES     = 7;
	const RETRY     = 600;
	const LIFE      = 60 * 86400;     /* a long-lived token's life */
	const TZ        = 'America/New_York';
	/* each slot and its day after the week's Tuesday */
	const SLOTS = array( 'carousel' => 0, 'swing' => 1, 'snaps' => 2, 'offense' => 3, 'targets' => 4, 'leaders' => 5 );
	const LABEL = array( 'carousel' => 'the carousel', 'swing' => 'the biggest swing', 'snaps' => 'the usage riser', 'offense' => 'best and worst offense', 'targets' => 'the target-share mover', 'leaders' => 'week leaders' );

	/** How to wait between status checks; the harness swaps it for one that doesn't. */
	public static $wait = 'sleep';

	public static function init() {
		add_action( 'dbd_synced', array( __CLASS__, 'note' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_action( self::HOOK_REFRESH, array( __CLASS__, 'refresh' ) );
		add_action( 'admin_post_dbd_ig_now', array( __CLASS__, 'now_action' ) );
		add_action( 'admin_post_dbd_ig_again', array( __CLASS__, 'again_action' ) );
		add_action( 'init', array( __CLASS__, 'keep_refreshing' ) );
	}
	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK ); wp_clear_scheduled_hook( self::HOOK_REFRESH ); }

	/** The weekly renewal runs while a token is kept. */
	public static function keep_refreshing() {
		if ( self::token() && ! wp_next_scheduled( self::HOOK_REFRESH ) ) { wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', self::HOOK_REFRESH ); }
	}

	/* ------------------------------------------------------------------ settings and the connection */

	public static function settings() { $o = get_option( self::OPT_SET, null ); return array( 'on' => is_array( $o ) ? ! empty( $o['on'] ) : true ); }
	public static function sanitize( $v ) { return array( 'on' => is_array( $v ) && ! empty( $v['on'] ) ); }
	/** The kept connection, or null: array( token, user_id, username, expires, checked, err ). */
	public static function token() { $t = get_option( self::OPT_TOKEN, null ); return is_array( $t ) && ! empty( $t['token'] ) ? $t : null; }
	public static function connected() { $t = self::token(); return $t && ! empty( $t['user_id'] ) && (int) ( $t['expires'] ?? 0 ) > time(); }
	public static function on() { return self::settings()['on'] && self::connected(); }

	/** One call to the API: array( HTTP code, JSON, error text ('' when fine) ). GETs carry their fields in the address, POSTs in the body. */
	public static function api( $method, $path, $args = array(), $token = null, $versioned = true ) {
		$args['access_token'] = (string) ( $token ?? ( self::token()['token'] ?? '' ) );
		$url = self::API . ( $versioned ? self::VER . '/' : '' ) . ltrim( $path, '/' );
		$req = array( 'method' => $method, 'timeout' => 30 );
		if ( $method === 'GET' ) { $url = add_query_arg( array_map( 'rawurlencode', $args ), $url ); } else { $req['body'] = $args; }
		$r = wp_remote_request( $url, $req );
		if ( is_wp_error( $r ) ) { return array( 0, null, 'Instagram could not be reached: ' . $r->get_error_message() ); }
		$code = (int) wp_remote_retrieve_response_code( $r ); $j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$err = ( $code >= 200 && $code < 300 && empty( $j['error'] ) ) ? '' : 'Instagram answered ' . $code . ( ! empty( $j['error']['message'] ) ? ': ' . $j['error']['message'] : '' );
		return array( $code, $j, $err );
	}

	/** A pasted token: checked against the API (who it is), then kept. Returns '' or what went wrong. */
	public static function connect( $token ) {
		$token = trim( (string) $token ); if ( $token === '' ) { return ''; }
		list( , $j, $err ) = self::api( 'GET', 'me', array( 'fields' => 'user_id,username' ), $token );
		if ( $err !== '' ) { return 'That token didn’t work. ' . $err; }
		$uid = (string) ( $j['user_id'] ?? $j['id'] ?? '' ); if ( $uid === '' ) { return 'Instagram didn’t say which account that token is for.'; }
		update_option( self::OPT_TOKEN, array( 'token' => $token, 'user_id' => $uid, 'username' => (string) ( $j['username'] ?? '' ), 'expires' => time() + self::LIFE, 'checked' => time(), 'err' => '' ), false );
		self::keep_refreshing();
		return '';
	}
	public static function disconnect() { delete_option( self::OPT_TOKEN ); wp_clear_scheduled_hook( self::HOOK_REFRESH ); }

	/** The weekly renewal: a fresh 60 days. A failure is kept on the connection, and the tab and the Overview say so. */
	public static function refresh() {
		$t = self::token(); if ( ! $t ) { return false; }
		list( , $j, $err ) = self::api( 'GET', 'refresh_access_token', array( 'grant_type' => 'ig_refresh_token' ), $t['token'], false );
		if ( $err === '' && ! empty( $j['access_token'] ) ) {
			$t = array_merge( $t, array( 'token' => (string) $j['access_token'], 'expires' => time() + (int) ( $j['expires_in'] ?? self::LIFE ), 'checked' => time(), 'err' => '' ) );
			update_option( self::OPT_TOKEN, $t, false ); return true;
		}
		$t['err'] = $err !== '' ? $err : 'Instagram didn’t send a new token.'; $t['checked'] = time(); update_option( self::OPT_TOKEN, $t, false ); return false;
	}

	/** A line for the Overview's "Needs a look", or '': a failed renewal, or a token within a week of running out. */
	public static function warning() {
		$t = self::token(); if ( ! $t ) { return ''; }
		$left = (int) ( $t['expires'] ?? 0 ) - time();
		if ( $left <= 0 ) { return 'The Instagram token ran out. Paste a new one on the Share pack tab.'; }
		if ( ! empty( $t['err'] ) ) { return 'The Instagram token didn’t renew (' . $t['err'] . '). It runs out ' . wp_date( 'M j', (int) $t['expires'] ) . '.'; }
		if ( $left < 7 * DAY_IN_SECONDS ) { return 'The Instagram token runs out ' . wp_date( 'M j', (int) $t['expires'] ) . '. Paste a new one on the Share pack tab.'; }
		return '';
	}

	/* ------------------------------------------------------------------ the week's posts */

	public static function key( $season, $n, $slot ) { return (int) $season . '-w' . (int) $n . '-' . $slot; }
	public static function parse( $key ) { return preg_match( '/^(\d{4})-w(\d{1,2})-(carousel|swing|snaps|offense|targets|leaders)$/', (string) $key, $m ) ? array( (int) $m[1], (int) $m[2], $m[3] ) : null; }

	/** When a slot of week $n posts: 9:00 AM Eastern, its days after the week's Tuesday (the date counted in Eastern time, so DST can't move it). */
	public static function slot_time( $n, $slot ) {
		$tue = DBD_Post::tuesday( $n ); if ( ! $tue || ! isset( self::SLOTS[ $slot ] ) ) { return 0; }
		$d = new DateTime( '@' . (int) $tue ); $d->setTimezone( new DateTimeZone( self::TZ ) ); $d->setTime( 9, 0 );
		if ( self::SLOTS[ $slot ] ) { $d->modify( '+' . self::SLOTS[ $slot ] . ' days' ); $d->setTime( 9, 0 ); }
		return $d->getTimestamp();
	}

	/** Week $n's posts: slot => the items it shows (the carousel, all of them with a square; a daily slot, its own item). Missing items leave their day out. */
	public static function plan( $n ) {
		$items = array_values( array_filter( DBD_Pack::items( $n ), function ( $it ) { return ! empty( $it['sq'] ); } ) ); $by = array();
		foreach ( $items as $it ) { $by[ $it['k'] ] = $it; }
		$out = array(); if ( count( $items ) >= 2 ) { $out['carousel'] = $items; }
		foreach ( array_keys( self::SLOTS ) as $sl ) { if ( isset( $by[ $sl ] ) ) { $out[ $sl ] = array( $by[ $sl ] ); } }
		return $out;
	}

	/** The carousel's caption: "Week 10 in five charts:", a line a slide, the bio line, then #NFL, #FantasyFootball and the teams (15 tags at most). */
	public static function carousel_caption( $n, $items ) {
		$words = array( 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven' ); $c = count( $items );
		$lines = array(); $tags = array( '#NFL', '#FantasyFootball' );
		foreach ( $items as $i => $it ) { $lines[] = ( $i + 1 ) . '. ' . (string) ( $it['ts'] ?? $it['title'] ); foreach ( (array) ( $it['teams'] ?? array() ) as $t ) { $tags[] = DBD_Pack::tag( $t ); } }
		$host = (string) preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( home_url( '/' ) ) );
		return 'Week ' . (int) $n . ' in ' . ( $words[ $c ] ?? (string) $c ) . " charts:\n\n" . implode( "\n", $lines ) . "\n\nFull charts: link in bio (" . $host . ")\n\n" . implode( ' ', array_slice( array_values( array_unique( $tags ) ), 0, 15 ) );
	}
	public static function caption( $n, $slot, $items ) { return $slot === 'carousel' ? self::carousel_caption( $n, $items ) : DBD_Pack::ig_caption( $items[0], true ); }

	public static function state() { $s = get_option( self::OPT, array() ); return is_array( $s ) ? $s + array( 'jobs' => array() ) : array( 'jobs' => array() ); }
	private static function save( $s ) { update_option( self::OPT, $s, false ); }
	public static function job( $key ) { return self::state()['jobs'][ $key ] ?? null; }
	private static function job_set( $key, $fields ) { $s = self::state(); $s['jobs'][ $key ] = array_merge( $s['jobs'][ $key ] ?? array(), $fields ); self::save( $s ); }

	/**
	 * One step, with no side effects: the record after seeing week $n at $now, and the bookings to make (key => when). Each post books
	 * once; one more than a day past its slot is marked skipped. $times maps slot => its time (slot_time() unless a test gives them).
	 */
	public static function decide( $s, $season, $n, $slots, $times, $now ) {
		$book = array();
		foreach ( $slots as $sl ) {
			$key = self::key( $season, $n, $sl ); if ( isset( $s['jobs'][ $key ] ) ) { continue; }
			$t = (int) ( $times[ $sl ] ?? 0 ); if ( ! $t ) { continue; }
			if ( $now > $t + self::LATE ) { $s['jobs'][ $key ] = array( 'state' => 'skipped: more than a day late', 'at' => $t ); continue; }
			$at = max( (int) $now + 60, $t ); $s['jobs'][ $key ] = array( 'state' => 'booked', 'at' => $at, 'slot_at' => $t, 'tries' => 0 ); $book[ $key ] = $at;
		}
		return array( $s, $book );
	}

	/** After each sync: book the newest week's posts, and book again any whose event went missing. */
	public static function note( $unused = null, $now = null ) {
		if ( ! self::on() || DBD_Data::archived() ) { return array(); }
		$n = DBD_Pack::newest(); if ( ! $n ) { return array(); }
		$now = $now ?? time(); $season = (int) DBD_Data::season(); $slots = array_keys( self::plan( $n ) ); $times = array();
		foreach ( $slots as $sl ) { $times[ $sl ] = self::slot_time( $n, $sl ); }
		$s0 = self::state(); list( $s, $book ) = self::decide( $s0, $season, $n, $slots, $times, $now );
		if ( $s !== $s0 ) { self::save( $s ); }
		foreach ( $book as $key => $at ) { wp_schedule_single_event( $at, self::HOOK, array( $key ) ); }
		foreach ( $slots as $sl ) {
			$key = self::key( $season, $n, $sl ); $st = (string) ( $s['jobs'][ $key ]['state'] ?? '' );
			if ( ! isset( $book[ $key ] ) && ( $st === 'booked' || strpos( $st, 'retrying' ) === 0 ) && ! wp_next_scheduled( self::HOOK, array( $key ) ) ) {
				$at = max( (int) $now + 60, (int) ( $s['jobs'][ $key ]['at'] ?? 0 ) ); wp_schedule_single_event( $at, self::HOOK, array( $key ) ); $book[ $key ] = $at;
			}
		}
		return $book;
	}

	/* ------------------------------------------------------------------ posting */

	/** A container's status_code (FINISHED, IN_PROGRESS, ERROR, EXPIRED, PUBLISHED), or '' when Instagram didn't say. */
	private static function status_of( $id ) { list( , $j, $err ) = self::api( 'GET', (string) $id, array( 'fields' => 'status_code' ) ); return $err === '' ? (string) ( $j['status_code'] ?? '' ) : ''; }

	/** Wait for a container to finish: up to six checks, five seconds apart. Returns its last status. */
	private static function ready( $id ) {
		$st = '';
		for ( $i = 0; $i < 6; $i++ ) { $st = self::status_of( $id ); if ( $st !== 'IN_PROGRESS' && $st !== '' ) { return $st; } call_user_func( self::$wait, 5 ); }
		return $st;
	}

	/**
	 * Post one slot: array( media id or '', error '' ). Reuses the job's container when Instagram still has it: PUBLISHED means it already
	 * went out (a lost reply), FINISHED means publish it; anything else makes new ones.
	 */
	public static function publish( $key ) {
		$kp = self::parse( $key ); if ( ! $kp ) { return array( '', 'not a post key' ); }
		list( , $n, $slot ) = $kp; $t = self::token(); $uid = (string) ( $t['user_id'] ?? '' );
		$plan = self::plan( $n ); if ( empty( $plan[ $slot ] ) ) { return array( '', 'there’s no ' . self::LABEL[ $slot ] . ' for Week ' . $n ); }
		$items = $plan[ $slot ]; $job = self::job( $key ) ?: array(); $cid = (string) ( $job['container'] ?? '' );
		if ( $cid !== '' ) {
			$st = self::status_of( $cid );
			if ( $st === 'PUBLISHED' ) { return array( (string) ( $job['media'] ?? '' ), '' ); }
			if ( $st !== 'FINISHED' && $st !== 'IN_PROGRESS' ) { $cid = ''; }
		}
		if ( $cid === '' ) {
			$cap = self::caption( $n, $slot, $items );
			if ( $slot === 'carousel' ) {
				$kids = array();
				foreach ( $items as $it ) {
					list( , $j, $err ) = self::api( 'POST', $uid . '/media', array( 'image_url' => DBD_Card::jpg_url( $it['sq'] ), 'is_carousel_item' => 'true' ) );
					if ( $err !== '' || empty( $j['id'] ) ) { return array( '', $err !== '' ? $err : 'Instagram made no slide' ); }
					$kids[] = (string) $j['id'];
				}
				foreach ( $kids as $k ) { $st = self::ready( $k ); if ( $st !== 'FINISHED' ) { return array( '', 'a slide isn’t ready (' . ( $st !== '' ? $st : 'no answer' ) . ')' ); } }
				list( , $j, $err ) = self::api( 'POST', $uid . '/media', array( 'media_type' => 'CAROUSEL', 'children' => implode( ',', $kids ), 'caption' => $cap ) );
			} else {
				list( , $j, $err ) = self::api( 'POST', $uid . '/media', array( 'image_url' => DBD_Card::jpg_url( $items[0]['sq'] ), 'caption' => $cap ) );
			}
			if ( $err !== '' || empty( $j['id'] ) ) { return array( '', $err !== '' ? $err : 'Instagram made no post' ); }
			$cid = (string) $j['id']; self::job_set( $key, array( 'container' => $cid ) );
		}
		$st = self::ready( $cid );
		if ( $st === 'PUBLISHED' ) { return array( '', '' ); }
		if ( $st !== 'FINISHED' ) { return array( '', 'the post isn’t ready (' . ( $st !== '' ? $st : 'no answer' ) . ')' ); }
		list( , $j, $err ) = self::api( 'POST', $uid . '/media_publish', array( 'creation_id' => $cid ) );
		if ( $err === '' && ! empty( $j['id'] ) ) { return array( (string) $j['id'], '' ); }
		if ( self::status_of( $cid ) === 'PUBLISHED' ) { return array( '', '' ); }  /* the reply was lost but the post went out */
		return array( '', $err !== '' ? $err : 'Instagram didn’t publish it' );
	}

	/** The booked post: published once, retried on failure. $force posts now whatever the time (Post now, Try again). */
	public static function send( $key, $force = false ) {
		$kp = self::parse( $key ); $job = self::job( $key ); if ( ! $kp || ! $job ) { return; }
		$st = (string) $job['state']; if ( ! $force && $st !== 'booked' && strpos( $st, 'retrying' ) !== 0 ) { return; }
		if ( $st === 'sent' ) { return; }
		if ( ! self::on() ) { self::job_set( $key, array( 'state' => 'not posted: Instagram posting is off or not connected' ) ); return; }
		list( $season, $n, $slot ) = $kp;
		if ( (int) DBD_Data::season() !== $season ) { self::job_set( $key, array( 'state' => 'not posted: a new season started' ) ); return; }
		if ( ! $force && $st === 'booked' && time() > (int) ( $job['slot_at'] ?? $job['at'] ?? 0 ) + self::LATE ) { self::job_set( $key, array( 'state' => 'not posted: more than a day late' ) ); return; }
		$lock = 'dbd_ig_lock_' . $key;
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 600 ) { return; } update_option( $lock, time(), false ); }
		try {
			list( $media, $err ) = self::publish( $key );
			if ( $err !== '' ) { return self::retry( $key, $err ); }
			$link = ''; if ( $media !== '' ) { list( , $j ) = self::api( 'GET', $media, array( 'fields' => 'permalink' ) ); $link = (string) ( $j['permalink'] ?? '' ); }
			self::job_set( $key, array( 'state' => 'sent', 'media' => $media, 'link' => $link, 'sent_at' => time() ) );
		} finally { delete_option( $lock ); }
	}
	private static function retry( $key, $err ) {
		$job = self::job( $key ) ?: array(); $t = (int) ( $job['tries'] ?? 0 ) + 1;
		if ( $t < self::TRIES ) { self::job_set( $key, array( 'tries' => $t, 'state' => 'retrying: ' . $err ) ); wp_schedule_single_event( time() + self::RETRY, self::HOOK, array( $key ) ); return; }
		self::job_set( $key, array( 'tries' => $t, 'state' => 'failed: ' . $err ) );
		$kp = self::parse( $key );
		wp_mail( DBD_Email::test_to(), 'Down by Down: Instagram didn’t take ' . self::LABEL[ $kp[2] ?? 'carousel' ] . ' for Week ' . (int) ( $kp[1] ?? 0 ),
			'It failed ' . self::TRIES . " times over an hour.\n\nThe last error: $err\n\nTry again on the Share pack tab: " . admin_url( 'admin.php?page=down-by-down-share' ) . "\n" );
	}

	/* ------------------------------------------------------------------ the tab */

	/** Week $n's posts for the settings box: slot => array( dot, text, link, try-again key ). */
	public static function week_rows( $n ) {
		$season = (int) DBD_Data::season(); $out = array(); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); };
		foreach ( array_keys( self::plan( $n ) ) as $sl ) {
			$key = self::key( $season, $n, $sl ); $j = self::job( $key ); $lab = ucfirst( self::LABEL[ $sl ] ); $st = (string) ( $j['state'] ?? '' );
			if ( ! $j ) { $out[ $sl ] = array( '', $lab . ': ' . $when( self::slot_time( $n, $sl ) ) . ', not booked yet', '', '' ); }
			elseif ( $st === 'sent' ) { $out[ $sl ] = array( 'ok', $lab . ': posted ' . $when( $j['sent_at'] ?? $j['at'] ), (string) ( $j['link'] ?? '' ), '' ); }
			elseif ( $st === 'booked' ) { $out[ $sl ] = array( 'ok', $lab . ': booked for ' . $when( $j['at'] ), '', '' ); }
			elseif ( strpos( $st, 'retrying' ) === 0 ) { $out[ $sl ] = array( 'warn', $lab . ': retrying, ' . substr( $st, 10 ), '', '' ); }
			elseif ( strpos( $st, 'failed' ) === 0 ) { $out[ $sl ] = array( 'bad', $lab . ': didn’t post, ' . substr( $st, 8 ), '', $key ); }
			else { $out[ $sl ] = array( '', $lab . ': ' . $st, '', '' ); }
		}
		return $out;
	}

	/** The status line at the top of the tab: array( dot, text, link, try-again key ), or null when posting is off. */
	public static function line( $n ) {
		if ( ! self::settings()['on'] ) { return null; }
		if ( ! self::connected() ) { return self::token() ? array( 'bad', 'Instagram: the token ran out. Paste a new one in the settings.', '', '' ) : array( '', 'Instagram isn’t connected yet. Paste a token in the settings.', '', '' ); }
		$season = (int) DBD_Data::season(); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); }; $last = null; $next = null;
		foreach ( array_keys( self::plan( $n ) ) as $sl ) {
			$key = self::key( $season, $n, $sl ); $j = self::job( $key ); $st = (string) ( $j['state'] ?? '' );
			if ( strpos( $st, 'failed' ) === 0 ) { return array( 'bad', 'Instagram didn’t take ' . self::LABEL[ $sl ] . ': ' . substr( $st, 8 ), '', $key ); }
			if ( $st === 'sent' ) { $last = array( $sl, $j ); }
			if ( ! $next && ( $st === 'booked' || strpos( $st, 'retrying' ) === 0 ) ) { $next = array( $sl, $j ); }
		}
		$txt = 'Instagram: ' . ( $last ? 'posted ' . self::LABEL[ $last[0] ] . ' ' . $when( $last[1]['sent_at'] ?? $last[1]['at'] ) . '.' : 'nothing posted for Week ' . (int) $n . ' yet.' )
			. ( $next ? ' Next: ' . self::LABEL[ $next[0] ] . ', ' . $when( $next[1]['at'] ) . '.' : '' );
		$link = (string) ( $last[1]['link'] ?? '' ); $warn = self::warning();
		return $warn !== '' ? array( 'warn', $txt . ' ' . $warn, $link, '' ) : array( 'ok', $txt, $link, '' );
	}

	private static function back( $args ) { wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'down-by-down-share' ), $args ), admin_url( 'admin.php' ) ) . '#dbd-pack-settings' ); exit; }

	/** "Post the next one now": the newest week's first post not yet out, right away. */
	public static function now_action() {
		check_admin_referer( 'dbd_ig_now' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		if ( ! self::on() ) { self::back( array( 'pack' => 'error', 'pack_err' => rawurlencode( 'Switch Instagram posting on and connect it first.' ) ) ); }
		$key = self::next_key(); if ( ! $key ) { self::back( array( 'pack' => 'error', 'pack_err' => rawurlencode( 'Every post for this week is out.' ) ) ); }
		if ( ! self::job( $key ) ) { self::job_set( $key, array( 'state' => 'booked', 'at' => time(), 'slot_at' => time(), 'tries' => 0 ) ); }
		self::send( $key, true ); $j = self::job( $key );
		self::back( ( $j['state'] ?? '' ) === 'sent' ? array( 'pack' => 'igposted' ) : array( 'pack' => 'error', 'pack_err' => rawurlencode( 'Instagram didn’t take it: ' . (string) ( $j['state'] ?? '' ) ) ) );
	}
	/** The newest week's first post that isn't out yet, or ''. */
	public static function next_key() {
		$n = DBD_Pack::newest(); if ( ! $n ) { return ''; } $season = (int) DBD_Data::season();
		foreach ( array_keys( self::plan( $n ) ) as $sl ) { $k = self::key( $season, $n, $sl ); if ( ( self::job( $k )['state'] ?? '' ) !== 'sent' ) { return $k; } }
		return '';
	}
	public static function again_url( $key ) { return wp_nonce_url( admin_url( 'admin-post.php?action=dbd_ig_again&key=' . rawurlencode( $key ) ), 'dbd_ig_again_' . $key ); }
	public static function again_action() {
		$key = sanitize_text_field( wp_unslash( (string) ( $_GET['key'] ?? '' ) ) ); check_admin_referer( 'dbd_ig_again_' . $key ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		if ( ! self::parse( $key ) || ! self::job( $key ) ) { self::back( array( 'pack' => 'error', 'pack_err' => rawurlencode( 'That post isn’t there.' ) ) ); }
		self::job_set( $key, array( 'state' => 'retrying: again', 'tries' => 0 ) ); wp_schedule_single_event( time(), self::HOOK, array( $key ) );
		self::back( array( 'pack' => 'igagain' ) );
	}
}
