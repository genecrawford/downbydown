<?php
/**
 * X posting (2.40.0, SCOPE §83), through X's pay-per-use API (api.x.com/2) with user-context OAuth 1.0a keys Gene makes in X's Developer
 * Console for the account under Your accounts and pastes once. Each post is the item's X text from the pack with its link, X building the
 * card preview from the page's own tags. X charges per post from prepaid credits, so the settings count this month's posts.
 *   - The days are Instagram's and Bluesky's (§76, §82): Tuesday a thread (a first post naming the week, then each item with a square as a
 *     numbered reply), Wednesday to Sunday one item a day, 9:00 AM Eastern. The button (Auto publish, §81) posts to X too.
 *   - Each item posts once: a record per item (dbd_x_job_2026-w10-swing), shared by its day and the button. X picks a post's ID itself, so
 *     a try is marked on its record before it's sent; the next try first reads the account's latest posts for the same text, and X's own
 *     refusal of a repeat is a second guard. The thread's replies are numbered ("1/5 …"), so they never read as repeats of the daily posts.
 *   - Booking, the cutoff, retries, the note and Try again copy Bluesky's, with the record locked from the first read to the last write.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_X {

	const OPT_CONN  = 'dbd_x_conn';   /* the connection: ck, cs, at, as (the four keys), id, username, checked (not autoloaded) */
	const OPT_SET   = 'dbd_x_set';    /* the weekly switch: array( 'on' => bool ), on unless unticked */
	const OPT_JOB   = 'dbd_x_job_';   /* one record per item */
	const OPT_COUNT = 'dbd_x_count_'; /* a month's posts, 'dbd_x_count_2026-11' => count */
	const HOOK      = 'dbd_x_send';
	const API       = 'https://api.x.com/2/';
	const PRICE     = 0.20;           /* about what X charges for a post with a link (third-party guides, 2026) */
	const KEYS      = array( 'ck' => 'API key', 'cs' => 'API key secret', 'at' => 'Access token', 'as' => 'Access token secret' );

	public static function init() {
		add_action( 'dbd_synced', array( __CLASS__, 'note' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_action( 'admin_post_dbd_x_again', array( __CLASS__, 'again_action' ) );
	}
	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK ); }

	/* ------------------------------------------------------------------ the connection */

	/** 'img': attach the tall graphic (SCOPE §85). Off until the first upload's charge is checked, since X's rate card lists no price for it. */
	public static function settings() { $o = get_option( self::OPT_SET, null ); return array( 'on' => is_array( $o ) ? ! empty( $o['on'] ) : true, 'img' => is_array( $o ) && ! empty( $o['img'] ) ); }
	public static function sanitize( $v ) { return array( 'on' => is_array( $v ) && ! empty( $v['on'] ), 'img' => is_array( $v ) && ! empty( $v['img'] ) ); }
	public static function conn() { $c = get_option( self::OPT_CONN, null ); return is_array( $c ) && ! empty( $c['ck'] ) && ! empty( $c['at'] ) ? $c : null; }
	public static function connected() { $c = self::conn(); return $c && ! empty( $c['id'] ); }
	public static function on() { return self::settings()['on'] && self::connected(); }

	/** RFC 3986 encoding, as OAuth 1.0a signs. */
	public static function enc( $s ) { return rawurlencode( (string) $s ); }

	/**
	 * The OAuth 1.0a Authorization header for a request: HMAC-SHA1 over the method, the address without its query, and every query and
	 * form parameter with the oauth ones (a JSON body isn't signed). $nonce and $ts are for the test against X's published example.
	 */
	public static function sign( $method, $url, $params, $ck, $cs, $at, $as, $nonce = null, $ts = null ) {
		$o = array( 'oauth_consumer_key' => (string) $ck, 'oauth_nonce' => (string) ( $nonce ?? bin2hex( random_bytes( 16 ) ) ), 'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp' => (string) ( $ts ?? time() ), 'oauth_token' => (string) $at, 'oauth_version' => '1.0' );
		$pairs = array(); foreach ( array_merge( (array) $params, $o ) as $k => $v ) { $pairs[] = array( self::enc( $k ), self::enc( $v ) ); }
		usort( $pairs, function ( $a, $b ) { return $a[0] === $b[0] ? strcmp( $a[1], $b[1] ) : strcmp( $a[0], $b[0] ); } );
		$base = strtoupper( $method ) . '&' . self::enc( $url ) . '&' . self::enc( implode( '&', array_map( function ( $p ) { return $p[0] . '=' . $p[1]; }, $pairs ) ) );
		$o['oauth_signature'] = base64_encode( hash_hmac( 'sha1', $base, self::enc( $cs ) . '&' . self::enc( $as ), true ) );
		ksort( $o );
		return 'OAuth ' . implode( ', ', array_map( function ( $k ) use ( $o ) { return self::enc( $k ) . '="' . self::enc( $o[ $k ] ) . '"'; }, array_keys( $o ) ) );
	}

	/** One call: array( HTTP code, JSON, error text ('' when fine) ). $c: the keys to sign with (the kept ones unless given). */
	public static function call( $method, $path, $body = null, $query = array(), $c = null ) {
		$c = $c ?? self::conn(); if ( ! $c ) { return array( 0, array(), 'X isn’t connected.' ); }
		$url = self::API . ltrim( $path, '/' ); $query = (array) $query;
		$req = array( 'method' => $method, 'timeout' => 30, 'headers' => array( 'Authorization' => self::sign( $method, $url, $query, $c['ck'], $c['cs'], $c['at'], $c['as'] ) ) );
		if ( $query ) { $url = add_query_arg( array_map( 'rawurlencode', $query ), $url ); }
		if ( $body !== null ) { $req['headers']['Content-Type'] = 'application/json'; $req['body'] = wp_json_encode( $body ); }
		$r = wp_remote_request( $url, $req );
		if ( is_wp_error( $r ) ) { return array( 0, array(), 'X could not be reached' . ( is_object( $r ) && method_exists( $r, 'get_error_message' ) ? ': ' . $r->get_error_message() : '' ) ); }
		$code = (int) wp_remote_retrieve_response_code( $r ); $j = json_decode( (string) wp_remote_retrieve_body( $r ), true ); $j = is_array( $j ) ? $j : array();
		$why = (string) ( $j['detail'] ?? $j['errors'][0]['message'] ?? $j['title'] ?? '' );
		return array( $code, $j, ( $code >= 200 && $code < 300 ) ? '' : 'X answered ' . $code . ( $why !== '' ? ': ' . $why : '' ) );
	}

	/** Four pasted keys: checked with users/me for the account under Your accounts, then kept. Returns '' or what went wrong. */
	public static function connect( $k ) {
		$h = DBD_Pack::our_x(); if ( $h === '' ) { return 'Put the X account under Your accounts first.'; }
		list( , $j, $err ) = self::call( 'GET', 'users/me', null, array(), $k );
		if ( $err !== '' || empty( $j['data']['id'] ) ) { return 'Those X keys didn’t work. ' . ( $err !== '' ? $err : 'X didn’t say whose they are.' ); }
		$u = (string) ( $j['data']['username'] ?? '' );
		if ( strtolower( $u ) !== strtolower( $h ) ) { return 'Those X keys are for @' . $u . ', not @' . $h . '. Make the access token while signed in as @' . $h . '.'; }
		update_option( self::OPT_CONN, array_merge( $k, array( 'id' => (string) $j['data']['id'], 'username' => $u, 'checked' => time() ) ), false );
		return '';
	}
	public static function disconnect() { delete_option( self::OPT_CONN ); }

	/**
	 * The settings box's X part: the switch, then disconnect, or four new keys checked (all four together), or forgetting keys for an account
	 * that's no longer the one under Your accounts. Returns '' or what went wrong.
	 */
	public static function save( $p ) {
		update_option( self::OPT_SET, self::sanitize( is_array( $p[ self::OPT_SET ] ?? null ) ? $p[ self::OPT_SET ] : array() ) );
		if ( ! empty( $p['dbd_x_disconnect'] ) ) { self::disconnect(); return ''; }
		/* keys kept for an account that's no longer the one under Your accounts are forgotten first, whatever else was pasted (Greptile, PR #151) */
		$c = self::conn(); $gone = '';
		if ( $c && strtolower( (string) $c['username'] ) !== strtolower( DBD_Pack::our_x() ) ) {
			self::disconnect(); $gone = DBD_Pack::our_x() !== '' ? 'The X account changed, so its keys were forgotten. Paste keys for @' . DBD_Pack::our_x() . '.' : '';
		}
		$k = array(); foreach ( array_keys( self::KEYS ) as $f ) { $k[ $f ] = is_scalar( $p[ 'dbd_x_' . $f . '_new' ] ?? null ) ? trim( (string) $p[ 'dbd_x_' . $f . '_new' ] ) : ''; }
		if ( array_filter( $k ) ) {
			if ( count( array_filter( $k ) ) < 4 ) { return 'Paste all four X keys together: the API key and its secret, the access token and its secret.'; }
			return self::connect( $k );
		}
		return $gone;
	}

	/* ------------------------------------------------------------------ the posts */

	/** This month's posts, for the settings box. */
	public static function month() { return (int) get_option( self::OPT_COUNT . wp_date( 'Y-m' ), 0 ); }
	/** One more post this month, added in the database itself, so two posts at once both count (Greptile, PR #151). */
	private static function count_one() {
		$o = self::OPT_COUNT . wp_date( 'Y-m' ); if ( add_option( $o, 1, '', false ) ) { return; }
		global $wpdb;
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) ) { $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s", $o ) ); wp_cache_delete( $o, 'options' ); return; }
		update_option( $o, (int) get_option( $o, 0 ) + 1, false );
	}

	/** A post's text as X shows it back, for finding it again: no links (X turns them into t.co ones), entities decoded, spaces as one. */
	public static function norm( $t ) { return trim( (string) preg_replace( '/\s+/u', ' ', (string) preg_replace( '#https?://\S+#', '', html_entity_decode( (string) $t, ENT_QUOTES, 'UTF-8' ) ) ) ); }

	/** The account's latest post with this text: array( post ID or '', error ). Ten posts read (a fraction of a cent). */
	public static function find( $text ) {
		$c = self::conn(); if ( ! $c ) { return array( '', 'X isn’t connected.' ); }
		list( , $j, $err ) = self::call( 'GET', 'users/' . $c['id'] . '/tweets', null, array( 'max_results' => '10' ) );
		if ( $err !== '' ) { return array( '', $err ); }
		$want = self::norm( $text );
		foreach ( (array) ( $j['data'] ?? array() ) as $p ) { if ( self::norm( $p['text'] ?? '' ) === $want && ! empty( $p['id'] ) ) { return array( (string) $p['id'], '' ); } }
		return array( '', '' );
	}

	/** The item's tall graphic uploaded to X: array( media ID or '', error ). No graphic to send is no error: the post goes as text and link. */
	private static function upload( $n, $it ) {
		$f = ! empty( $it['_file'] ) ? (string) $it['_file'] : DBD_Tall::file( $n, $it ); if ( $f === '' || ! is_readable( $f ) ) { return array( '', '' ); }  /* a player's share graphic comes as a file (SCOPE §103) */
		list( , $j, $err ) = self::call( 'POST', 'media/upload', array( 'media' => base64_encode( (string) file_get_contents( $f ) ), 'media_category' => 'tweet_image' ) );
		if ( $err !== '' || empty( $j['data']['id'] ) ) { return array( '', $err !== '' ? $err : 'X took no image' ); }
		return array( (string) $j['data']['id'], '' );
	}

	/**
	 * One post that may have gone out before: array( post ID, error ). A record already tried ($p['tried']) is looked for among the latest
	 * posts first; a new try is marked ($mark) before it's sent. A refusal as a repeat is looked for again, so it's taken as the post it repeats.
	 * With the graphic on, $it's image goes with it; a retry within 12 hours reuses the upload rather than paying for another.
	 */
	private static function part( $p, $text, $reply, $mark, $n = 0, $it = null ) {
		$text = (string) ( $p['text'] ?? $text );  /* a retry sends and looks for the text tried first, even when a sync has changed the item since (Greptile, PR #151) */
		if ( ! empty( $p['tried'] ) ) {
			list( $id, $err ) = self::find( $text );
			if ( $err !== '' ) { return array( '', 'X didn’t say whether the last try went out (' . $err . ')' ); }
			if ( $id !== '' ) { self::count_one(); return array( $id, '' ); }  /* it went out on that try, uncounted (its reply was lost), and X charged it */
		}
		$mid = ''; $mat = 0;
		if ( $it && self::settings()['img'] ) {
			if ( ! empty( $p['mid'] ) && (int) ( $p['mid_at'] ?? 0 ) > time() - 12 * HOUR_IN_SECONDS ) { $mid = (string) $p['mid']; $mat = (int) $p['mid_at']; }
			else { list( $mid, $merr ) = self::upload( $n, $it ); if ( $merr !== '' ) { return array( '', $merr ); } $mat = time(); }
		}
		$mark( $text, $mid, $mat );
		$body = array( 'text' => (string) $text ); if ( $reply !== '' ) { $body['reply'] = array( 'in_reply_to_tweet_id' => (string) $reply ); }
		if ( $mid !== '' ) { $body['media'] = array( 'media_ids' => array( $mid ) ); }
		list( $code, $j, $err ) = self::call( 'POST', 'tweets', $body );
		if ( $err === '' && ! empty( $j['data']['id'] ) ) { self::count_one(); return array( (string) $j['data']['id'], '' ); }
		if ( $code === 403 && stripos( $err, 'duplicate' ) !== false ) { list( $id ) = self::find( $text ); if ( $id !== '' ) { self::count_one(); return array( $id, '' ); } }  /* a repeat of a try whose reply was lost */
		return array( '', $err !== '' ? $err : 'X made no post' );
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
		$lock = 'dbd_x_lock_' . $key;
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 300 ) { return false; } update_option( $lock, time(), false ); }
		return true;
	}
	private static function unlock( $key ) { delete_option( 'dbd_x_lock_' . $key ); }

	/** Post an item now: '' when it's out (or was already), or what went wrong (kept on its record). $auto: the button's press. $held: send() has the lock. */
	public static function post( $key, $it, $auto = true, $held = false ) {
		$j = self::job( $key ); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
		if ( ! self::connected() ) { return 'X isn’t connected.'; }
		if ( ! $held && ! self::lock( $key ) ) { return 'it’s already posting'; }
		try {
			$j = self::fresh_job( $key ) ?: array(); if ( ( $j['state'] ?? '' ) === 'sent' ) { return ''; }
			$kp = DBD_Instagram::parse( $key );
			list( $id, $err ) = self::part( $j, (string) DBD_Pack::social( $it )['x'], '', function ( $text, $mid = '', $mat = 0 ) use ( $key ) { self::job_set( $key, array( 'tried' => time(), 'text' => $text, 'mid' => $mid, 'mid_at' => $mat ) ); }, (int) ( $kp[1] ?? 0 ), $it );
			if ( $err !== '' ) { return self::fail( $key, $err ); }
			return self::sent( $key, $id, $auto );
		} finally { if ( ! $held ) { self::unlock( $key ); } }
	}
	/**
	 * A player's share (SCOPE §103): one post of $text, with the graphic $file when "Attach the graphic" is on. array( link, error ).
	 * One post per $key (a player-week): a key already sent returns its link, and a retry looks for the try before it as post() does.
	 */
	public static function share( $key, $text, $file ) {
		$j = self::job( $key ); if ( ( $j['state'] ?? '' ) === 'sent' ) { return array( (string) ( $j['link'] ?? '' ), '' ); }
		if ( ! self::connected() ) { return array( '', 'X isn’t connected.' ); }
		if ( ! self::lock( $key ) ) { return array( '', 'it’s already posting' ); }
		try {
			$j = self::fresh_job( $key ) ?: array(); if ( ( $j['state'] ?? '' ) === 'sent' ) { return array( (string) ( $j['link'] ?? '' ), '' ); }
			list( $id, $err ) = self::part( $j, (string) $text, '', function ( $text, $mid = '', $mat = 0 ) use ( $key ) { self::job_set( $key, array( 'tried' => time(), 'text' => $text, 'mid' => $mid, 'mid_at' => $mat ) ); }, 0, array( '_file' => (string) $file ) );
			if ( $err !== '' ) { return array( '', self::fail( $key, $err ) ); }
			self::sent( $key, $id, true ); return array( self::web( $id ), '' );
		} finally { self::unlock( $key ); }
	}
	public static function web( $id ) { $c = self::conn(); return $id !== '' ? 'https://x.com/' . ( $c['username'] ?? 'i' ) . '/status/' . $id : ''; }
	private static function sent( $key, $id, $auto = false ) {
		$f = array( 'state' => 'sent', 'id' => $id, 'link' => self::web( $id ), 'sent_at' => time(), 'err' => '', 'force' => false ); if ( $auto ) { $f['auto'] = true; }
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
		$all = array();
		foreach ( DBD_Instagram::book_weeks() as $n ) {  /* the newest pack week, then any playoff week with posts (SCOPE §89) */
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
			$all += $book;
		}
		return $all;
	}

	/** The booked post: sent once, retried on failure, the record locked from its first read to its last write. $force: Try again. */
	public static function send( $key, $force = false ) {
		$kp = DBD_Instagram::parse( $key ); if ( ! $kp || ! self::job( $key ) ) { return; }
		if ( ! self::lock( $key ) ) { return; }
		try {
			$job = self::fresh_job( $key ) ?: array(); $st = (string) ( $job['state'] ?? '' ); if ( $st === 'sent' ) { return; }
			if ( ! $force && $st !== 'booked' && strpos( $st, 'retrying' ) !== 0 ) { return; }
			$force = $force || ! empty( $job['force'] );
			if ( ! self::on() ) { self::job_set( $key, array( 'state' => 'not posted: X posting is off or not connected' ) ); return; }
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
		wp_mail( DBD_Email::test_to(), 'Down by Down: X didn’t take ' . self::label( $kp[2] ?? 'carousel' ) . ' for Week ' . (int) ( $kp[1] ?? 0 ),
			'It failed ' . DBD_Instagram::TRIES . " times over an hour.\n\nThe last error: $err\n\nTry again on the Share pack tab: " . admin_url( 'admin.php?page=down-by-down-share' ) . "\n" );
	}

	/** The thread's first post: "Week 10 in five charts, one in each reply below. …" and the week page's link. */
	public static function intro( $n, $count ) {
		$words = array( 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven' );
		return 'Week ' . (int) $n . ' in ' . ( $words[ $count ] ?? (string) $count ) . ' charts, one in each reply below. Every chart and the numbers behind it: ' . DBD_Router::url( array( 'view' => 'week', 'week' => (int) $n ) );
	}
	/** A thread reply: "1/5 " and the item's X text, fitted to 280 the way the pack fits it (the long takeaway, the short one, then cut at a word). */
	public static function reply_text( $it, $i, $count ) {
		$l = DBD_Pack::share_link( $it ); $tl = (string) ( $it['tl'] ?? $it['title'] ?? '' ); $ts = (string) ( $it['ts'] ?? $tl ); $pre = (int) $i . '/' . (int) $count . ' ';
		foreach ( array( $tl, $ts ) as $t ) { $p = $pre . $t . ' ' . $l; if ( DBD_Pack::xlen( $p ) <= DBD_Pack::X_MAX ) { return $p; } }
		$t = mb_substr( $ts, 0, DBD_Pack::X_MAX - DBD_Pack::X_LINK - 2 - mb_strlen( $pre ) ); $t = (string) preg_replace( '/\s+\S*$/u', '', $t );
		return $pre . rtrim( $t, ' ,;:' ) . '… ' . $l;
	}

	/**
	 * Tuesday's thread: the first post, then each item with a square as a numbered reply to the post before it. The items are chosen on the
	 * first try and kept, and each part's post ID is kept as it goes, so a retry carries on where the last one stopped.
	 */
	public static function thread( $key, $n, $held = false ) {
		if ( ! self::connected() ) { return 'X isn’t connected.'; }
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
			$parts = array_values( (array) ( $j['parts'] ?? array() ) ); $prev = ''; $count = count( $items );
			for ( $i = 0; $i <= $count; $i++ ) {
				$p = (array) ( $parts[ $i ] ?? array() );
				if ( empty( $p['id'] ) ) {
					$text = $i ? self::reply_text( $items[ $i - 1 ], $i, $count ) : self::intro( $n, $count );
					$mark = function ( $text, $mid = '', $mat = 0 ) use ( $key, &$parts, $i ) { $parts[ $i ] = array( 'tried' => time(), 'text' => $text, 'mid' => $mid, 'mid_at' => $mat ); self::job_set( $key, array( 'parts' => $parts ) ); };
					list( $id, $err ) = self::part( $p, $text, $prev, $mark, $n, $i ? $items[ $i - 1 ] : null );
					if ( $err !== '' ) { return self::fail( $key, $err ); }
					$parts[ $i ] = array( 'id' => $id ); self::job_set( $key, array( 'parts' => $parts ) );
				}
				$prev = (string) $parts[ $i ]['id'];
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

	/** The status line at the top of the tab: array( dot, text, link, try-again key ), or null when the weekly posts are off or X isn't connected. */
	public static function line( $n ) {
		if ( ! self::on() ) { return null; }
		$season = (int) DBD_Data::season(); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); }; $last = null; $next = null;
		foreach ( array_keys( DBD_Instagram::plan( $n ) ) as $sl ) {
			$key = DBD_Instagram::key( $season, $n, $sl ); $j = self::job( $key ); $st = (string) ( $j['state'] ?? '' );
			if ( strpos( $st, 'failed' ) === 0 && ! self::by_button( $j ) ) { return array( 'bad', 'X didn’t take ' . self::label( $sl ) . ': ' . self::why( $j ), '', $key ); }
			if ( $st === 'sent' && ( ! $last || (int) ( $j['sent_at'] ?? 0 ) >= (int) ( $last[1]['sent_at'] ?? 0 ) ) ) { $last = array( $sl, $j ); }
			if ( ! $next && ( $st === 'booked' || strpos( $st, 'retrying' ) === 0 ) ) { $next = array( $sl, $j ); }
		}
		$txt = 'X: ' . ( $last ? 'posted ' . self::label( $last[0] ) . ' ' . $when( $last[1]['sent_at'] ?? 0 ) . '.' : 'nothing posted for Week ' . (int) $n . ' yet.' )
			. ( $next ? ' Next: ' . self::label( $next[0] ) . ', ' . $when( $next[1]['at'] ) . '.' : '' );
		return array( 'ok', $txt, (string) ( $last[1]['link'] ?? '' ), '' );
	}

	public static function again_url( $key ) { return wp_nonce_url( admin_url( 'admin-post.php?action=dbd_x_again&key=' . rawurlencode( $key ) ), 'dbd_x_again_' . $key ); }
	public static function again_action() {
		$key = sanitize_text_field( wp_unslash( (string) ( $_GET['key'] ?? '' ) ) ); check_admin_referer( 'dbd_x_again_' . $key ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		$back = function ( $args ) { wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'down-by-down-share' ), $args ), admin_url( 'admin.php' ) ) . '#dbd-pack-settings' ); exit; };
		if ( ! DBD_Instagram::parse( $key ) || ! self::job( $key ) ) { $back( array( 'pack' => 'igerror', 'pack_err' => rawurlencode( 'That post isn’t there.' ) ) ); }
		self::job_set( $key, array( 'state' => 'retrying: again', 'tries' => 0, 'force' => true ) );
		wp_clear_scheduled_hook( self::HOOK, array( $key ) ); wp_schedule_single_event( time() + 1, self::HOOK, array( $key ) );
		$back( array( 'pack' => 'xagain' ) );
	}
}
