<?php
/**
 * Board emails (SCOPE §66): twice a year the Tuesday email's list hears about a new board. When a new year's combine results or draft board
 * land and stop changing for two days, the next Tuesday at 8:00 AM Eastern the list gets a short email: the board's share card, its three
 * headline numbers, the top ten and a button to the board. The board page is its "View in browser".
 *   - The years already on the site when this first runs are old news: they're marked sent, never emailed.
 *   - Each sync notes the newest year's data and when it last changed; the Tuesday job sends what has settled.
 *   - The send is the Tuesday email's: a Brevo campaign to the list, retried every 10 minutes for an hour, then a note to Gene; its rows go in
 *     the same send log, with Try again. "Board emails" on the Week in review tab switches them off.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Board_Mail {

	const OPT       = 'dbd_board_mail';  /* array( 'sent' => kind => year, 'seen' => kind => array( year, data hash, since ), 'jobs' => key => job ) */
	const HOOK_WEEK = 'dbd_board_mail_tuesday';
	const HOOK_SEND = 'dbd_board_mail_send';
	const KINDS     = array( 'combine', 'draft' );
	const MIN       = array( 'combine' => 100, 'draft' => 200 );  /* fewer rows than this is a board still filling in */
	const SETTLE    = 2 * 86400;

	public static function init() {
		add_action( 'dbd_synced', array( __CLASS__, 'note' ) );
		add_action( self::HOOK_WEEK, array( __CLASS__, 'tuesday' ) );
		add_action( self::HOOK_SEND, array( __CLASS__, 'send' ) );
		add_action( 'admin_post_dbd_board_test', array( __CLASS__, 'test_action' ) );
		add_action( 'admin_post_dbd_board_again', array( __CLASS__, 'again_action' ) );
		if ( ! wp_next_scheduled( self::HOOK_WEEK ) ) { wp_schedule_event( self::next_tuesday(), 'weekly', self::HOOK_WEEK ); }
	}
	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK_WEEK ); wp_clear_scheduled_hook( self::HOOK_SEND ); }

	/** The next Tuesday, 8:00 AM Eastern. */
	public static function next_tuesday( $now = null ) {
		$d = new DateTime( '@' . ( $now ?? time() ) ); $d->setTimezone( new DateTimeZone( 'America/New_York' ) );
		$d->modify( 'next tuesday' )->setTime( 8, 0 );
		return $d->getTimestamp();
	}

	/** Board emails are on (the Week in review tab's switch, on by default) and the Tuesday email is set up. */
	public static function on() { return DBD_Email::get( 'boards' ) !== false && DBD_Email::ready(); }

	/* ------------------------------------------------------------------ the record */

	public static function state() {
		$s = get_option( self::OPT, array() ); $s = is_array( $s ) ? $s : array();
		return array_merge( array( 'sent' => array(), 'seen' => array(), 'jobs' => array() ), $s );
	}
	private static function save( $s ) { update_option( self::OPT, $s, false ); }
	private static function job_set( $key, $fields ) { $s = self::state(); $s['jobs'][ $key ] = array_merge( $s['jobs'][ $key ] ?? array(), $fields ); self::save( $s ); }
	public static function job( $key ) { return self::state()['jobs'][ $key ] ?? null; }

	/**
	 * One kind's step, with no side effects: the record after seeing year $y of $kind with data hash $hash, and whether it's ready to email
	 * at $now ($rows rows). The first look marks the newest year sent; a newer year waits until its data hasn't changed for two days.
	 */
	public static function decide( $s, $kind, $y, $hash, $rows, $now ) {
		$y = (int) $y; if ( ! $y ) { return array( $s, false ); }
		if ( ! isset( $s['sent'][ $kind ] ) ) { $s['sent'][ $kind ] = $y; return array( $s, false ); }
		if ( $y <= (int) $s['sent'][ $kind ] ) { return array( $s, false ); }
		$seen = $s['seen'][ $kind ] ?? null;
		if ( ! $seen || (int) $seen[0] !== $y || (string) $seen[1] !== (string) $hash ) { $s['seen'][ $kind ] = array( $y, (string) $hash, (int) $now ); return array( $s, false ); }
		$job = $s['jobs'][ $kind . '-' . $y ] ?? null;
		return array( $s, ! $job && (int) $rows >= self::MIN[ $kind ] && $now - (int) $seen[2] >= self::SETTLE );
	}

	/** The newest year of $kind on the site: array( year, data hash, rows ). */
	private static function newest( $kind ) {
		$y = DBD_Boards::years( $kind )[0] ?? 0; if ( ! $y ) { return array( 0, '', 0 ); }
		$f = DBD_Data::root_dir() . '/' . $kind . '/' . (int) $y . '.json'; $B = DBD_Boards::get( $kind, $y );
		return array( (int) $y, is_readable( $f ) ? (string) md5_file( $f ) : '', $B ? count( $B['rows'] ) : 0 );
	}

	/** After each sync, and before each Tuesday's check: note the newest years. Returns the keys ready to email. */
	public static function note( $unused = null, $now = null ) {
		$now = $now ?? time(); $s0 = self::state(); $s = $s0; $due = array();
		foreach ( self::KINDS as $k ) { list( $y, $h, $n ) = self::newest( $k ); list( $s, $ok ) = self::decide( $s, $k, $y, $h, $n, $now ); if ( $ok ) { $due[] = $k . '-' . $y; } }
		if ( $s !== $s0 ) { self::save( $s ); }
		return $due;
	}

	/** Tuesday, 8:00 AM Eastern: queue what has settled. */
	public static function tuesday() {
		$due = self::note(); if ( ! $due || ! self::on() ) { return; }
		foreach ( $due as $key ) { self::queue( $key ); }
	}
	private static function queue( $key ) {
		self::job_set( $key, array( 'state' => 'queued', 'tries' => 0, 'camp' => (int) ( self::job( $key )['camp'] ?? 0 ), 'at' => time() ) );
		wp_schedule_single_event( time() + 5, self::HOOK_SEND, array( $key ) );
	}
	private static function parse( $key ) { return preg_match( '/^(combine|draft)-(\d{4})$/', (string) $key, $m ) ? array( $m[1], (int) $m[2] ) : null; }

	/* ------------------------------------------------------------------ sending */

	public static function send( $key ) {
		$kp = self::parse( $key ); $job = self::job( $key ); if ( ! $kp || ! $job ) { return; }
		$state = (string) $job['state']; if ( $state !== 'queued' && strpos( $state, 'retrying' ) !== 0 ) { return; }
		list( $kind, $y ) = $kp; $title = self::title( $kind, $y );
		$stop = DBD_Email::get( 'boards' ) === false ? 'board emails are switched off' : ( ! DBD_Email::ready() ? 'the email was switched off' : '' );
		if ( $stop !== '' ) { self::job_set( $key, array( 'state' => 'not sent: ' . $stop ) ); DBD_Email::log_row( $title, 'not sent: ' . $stop, 0, 0, 0, $key ); return; }
		$lock = 'dbd_board_lock_' . $key;
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 300 ) { return; } update_option( $lock, time(), false ); }
		try {
			$o = DBD_Email::opts(); $camp = (int) ( $job['camp'] ?? 0 );
			if ( ! $camp ) {
				$doc = self::build( $kind, $y ); if ( ! $doc ) { return self::retry( $key, 'the ' . $kind . ' board for ' . $y . ' is missing' ); }
				list( , $j, $err ) = DBD_Email::api( 'POST', 'emailCampaigns', array(
					'name' => $doc['subject'] . ' (board)', 'subject' => $doc['subject'], 'previewText' => $doc['pre'],
					'sender' => array( 'name' => $o['from_name'], 'email' => $o['from_email'] ), 'replyTo' => $o['reply'], 'htmlContent' => $doc['html'],
					'recipients' => array( 'listIds' => array( (int) $o['list'] ) ), 'inlineImageActivation' => false,  /* no campaign tag (Brevo's free plan, 2.11.1) */
				) );
				if ( $err !== '' || empty( $j['id'] ) ) { return self::retry( $key, $err ?: 'Brevo didn’t return a campaign' ); }
				$camp = (int) $j['id']; self::job_set( $key, array( 'camp' => $camp ) );
			}
			list( , , $err ) = DBD_Email::api( 'POST', 'emailCampaigns/' . $camp . '/sendNow' );
			if ( $err !== '' ) {  /* a retry after a send that went through but timed out */
				list( , $cj ) = DBD_Email::api( 'GET', 'emailCampaigns/' . $camp );
				if ( in_array( (string) ( $cj['status'] ?? '' ), array( 'sent', 'queued', 'in_process' ), true ) ) { $err = ''; }
			}
			if ( $err !== '' ) { return self::retry( $key, $err ); }
			list( , $lj ) = DBD_Email::api( 'GET', 'contacts/lists/' . (int) $o['list'] );
			$n = (int) ( $lj['uniqueSubscribers'] ?? $lj['totalSubscribers'] ?? 0 );
			$s = self::state(); $s['jobs'][ $key ]['state'] = 'sent'; $s['sent'][ $kind ] = max( $y, (int) ( $s['sent'][ $kind ] ?? 0 ) ); self::save( $s );
			DBD_Email::log_row( $title, 'sent', $camp, $n, 0, $key );
		} finally { delete_option( $lock ); }
	}
	private static function retry( $key, $err ) {
		$job = self::job( $key ) ?: array(); $t = (int) ( $job['tries'] ?? 0 ) + 1; list( $kind, $y ) = self::parse( $key );
		if ( $t < DBD_Email::TRIES ) { self::job_set( $key, array( 'tries' => $t, 'state' => 'retrying: ' . $err ) ); wp_schedule_single_event( time() + DBD_Email::RETRY, self::HOOK_SEND, array( $key ) ); return; }
		self::job_set( $key, array( 'tries' => $t, 'state' => 'failed: ' . $err ) );
		DBD_Email::log_row( self::title( $kind, $y ), 'failed: ' . $err, (int) ( $job['camp'] ?? 0 ), 0, 0, $key );
		wp_mail( DBD_Email::test_to(), 'Down by Down: the ' . $y . ' ' . $kind . ' email didn’t send',
			"Brevo didn't take the email after " . DBD_Email::TRIES . " tries over an hour.\n\nThe last error: $err\n\n"
			. "The send log, with Try again: " . admin_url( 'admin.php?page=down-by-down-week#dbd-boards' ) . "\n" );
	}
	public static function failed( $key ) { return strpos( (string) ( self::job( $key )['state'] ?? '' ), 'failed' ) === 0; }
	public static function again_url( $key ) { return wp_nonce_url( admin_url( 'admin-post.php?action=dbd_board_again&key=' . rawurlencode( $key ) ), 'dbd_board_again_' . $key ); }
	public static function again_action() {
		$key = sanitize_key( (string) ( $_GET['key'] ?? '' ) ); check_admin_referer( 'dbd_board_again_' . $key );
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		if ( self::parse( $key ) && self::failed( $key ) ) { self::queue( $key ); }
		wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down-week', 'board' => 'again' ), admin_url( 'admin.php' ) ) . '#dbd-boards' ); exit;
	}

	/** Email the newest $kind board to the test address only. Returns '' or the error. */
	public static function test( $kind ) {
		if ( (string) DBD_Email::get( 'key' ) === '' ) { return 'Add the Brevo API key first.'; }
		$y = DBD_Boards::years( $kind )[0] ?? 0; $doc = $y ? self::build( $kind, $y ) : null; if ( ! $doc ) { return 'There’s no ' . $kind . ' board yet.'; }
		$o = DBD_Email::opts();
		list( , , $err ) = DBD_Email::api( 'POST', 'smtp/email', array(
			'sender' => array( 'name' => $o['from_name'], 'email' => $o['from_email'] ), 'to' => array( array( 'email' => DBD_Email::test_to() ) ), 'replyTo' => array( 'email' => $o['reply'] ),
			'subject' => '[Test] ' . $doc['subject'], 'htmlContent' => str_replace( '{{ unsubscribe }}', esc_url( home_url( '/' ) ), $doc['html'] ),
		) );
		return $err;
	}
	public static function test_action() {
		check_admin_referer( 'dbd_board_test' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		$kind = in_array( (string) ( $_GET['kind'] ?? '' ), self::KINDS, true ) ? (string) $_GET['kind'] : 'combine';
		$err = self::test( $kind );
		wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down-week', 'board' => $err === '' ? 'tested' : 'error', 'board_err' => rawurlencode( $err ) ), admin_url( 'admin.php' ) ) . '#dbd-boards' ); exit;
	}

	/* ------------------------------------------------------------------ the email */

	public static function title( $kind, $y ) { return $kind === 'draft' ? 'The ' . (int) $y . ' draft, every pick' : 'The ' . (int) $y . ' combine results'; }

	/** The board's share card saved under uploads (data in the name, so a corrected board draws a new one): its URL, or ''. */
	public static function card_url( $kind, $y ) {
		if ( ! class_exists( 'DBD_Card' ) || ! DBD_Card::available() ) { return ''; }
		$f = DBD_Data::root_dir() . '/' . $kind . '/' . (int) $y . '.json'; $h = is_readable( $f ) ? substr( (string) md5_file( $f ), 0, 8 ) : 'x';
		$up = wp_upload_dir(); $rel = 'down-by-down/boards/' . $kind . '-' . (int) $y . '-' . $h . '.png';
		$path = trailingslashit( $up['basedir'] ) . $rel;
		if ( ! file_exists( $path ) ) {
			$fetch = function ( $url ) { $r = wp_remote_get( $url, array( 'timeout' => 5 ) ); return ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) ? wp_remote_retrieve_body( $r ) : null; };
			$im = DBD_Card::render( 'page-' . $kind . '-' . (int) $y, $fetch ); if ( ! $im ) { return ''; }
			wp_mkdir_p( dirname( $path ) ); $tmp = $path . '.' . wp_generate_password( 8, false ) . '.tmp'; $ok = imagepng( $im, $tmp, 6 ); imagedestroy( $im );
			if ( ! $ok || ! @rename( $tmp, $path ) ) { @unlink( $tmp ); return ''; }
		}
		return trailingslashit( $up['baseurl'] ) . $rel;
	}

	/** The email for year $y's $kind board: array( subject, pre, html ), or null without the board. */
	public static function build( $kind, $y, $date = null, $card = null ) {
		$B = DBD_Boards::get( $kind, $y ); $s = class_exists( 'DBD_Card' ) ? DBD_Card::spec_board( $kind, $y ) : null;
		if ( ! $B || ! $B['rows'] || ! $s ) { return null; }
		$INK = DBD_Email::INK; $MUT = DBD_Email::MUT; $LINE = DBD_Email::LINE; $SANS = DBD_Email::SANS; $HEAVY = DBD_Email::HEAVY;
		$st = function ( $css ) { return ' style="' . $css . '"'; }; $e = 'esc_html';
		$R = $B['rows']; $n = count( $R ); $url = DBD_Router::url( array( 'view' => $kind, 'y' => (int) $y ) );
		$nick = function ( $t ) { return DBD_Data::team( (string) $t )['nick'] ?? (string) $t; };
		$title = self::title( $kind, $y );
		if ( $kind === 'draft' ) {
			usort( $R, function ( $a, $b ) { return (int) $a['p'] <=> (int) $b['p']; } );
			$rounds = max( array_map( function ( $x ) { return (int) $x['r']; }, $R ) );
			$intro = $n . ' picks over ' . $rounds . ' rounds. The board has every one, with the player’s school, his combine score and, as the season goes, what he has done since.';
			$pre = 'The first pick: ' . $R[0]['n'] . ', ' . $R[0]['pos'] . ', to the ' . $nick( $R[0]['t'] ) . '.';
			$cap = 'The first ten picks'; $btn = 'See every pick';
			$rows = array(); foreach ( array_slice( $R, 0, 10 ) as $x ) { $rows[] = array( (string) (int) $x['p'], (string) $x['n'], $x['pos'] . ', ' . $x['sc'], $nick( $x['t'] ) ); }
		} else {
			$intro = $n . ' players tested. Each drill is ranked against every player at the position since 2000, and the combine score puts them together.';
			$pre = $s['tiles'] ? $s['tiles'][0][0] . ': ' . $s['tiles'][0][1] . ', ' . $s['tiles'][0][2] . '.' : $intro;
			$cap = 'The top ten by combine score'; $btn = 'See every drill';
			$sc = array_values( array_filter( $R, function ( $x ) { return $x['cs'] !== null; } ) );
			usort( $sc, function ( $a, $b ) { return (float) $b['cs'] <=> (float) $a['cs']; } );
			$rows = array(); foreach ( array_slice( $sc, 0, 10 ) as $i => $x ) { $rows[] = array( (string) ( $i + 1 ), (string) $x['n'], $x['pos'] . ', ' . $x['sc'], (string) (int) round( (float) $x['cs'] ) ); }
		}
		$tiles = ''; foreach ( array_slice( $s['tiles'], 0, 3 ) as $t ) {
			$v = $kind === 'draft' && ! empty( $t[3] ) ? $nick( $t[1] ) : $t[1];  /* a team's tile shows its name, not its abbreviation */
			$tiles .= '<td width="33%" valign="top"' . $st( "padding:12px 10px;border:1px solid $LINE;background:#ffffff" ) . '><div' . $st( "font:600 11px $SANS;letter-spacing:.08em;text-transform:uppercase;color:$MUT" ) . '>' . $e( $t[0] ) . '</div>'
				. '<div' . $st( "margin:4px 0 2px;font:900 20px/1.1 $HEAVY;color:$INK" ) . '>' . $e( $v ) . '</div><div' . $st( "font:13px/1.35 $SANS;color:$MUT" ) . '>' . $e( $t[2] ) . '</div></td>';
		}
		$list = ''; foreach ( $rows as $r ) {
			$td = "padding:7px 0;border-top:1px solid $LINE";
			$list .= '<tr><td width="30"' . $st( "$td;font:13px $SANS;color:$MUT" ) . '>' . $e( $r[0] ) . '</td><td' . $st( "$td;font:600 15px/1.35 $SANS;color:$INK" ) . '>' . $e( $r[1] ) . '<span' . $st( "font-weight:400;color:$MUT" ) . '> · ' . $e( $r[2] ) . '</span></td>'
				. '<td align="right"' . $st( "$td;font:700 15px $SANS;color:$INK;white-space:nowrap" ) . '>' . $e( $r[3] ) . '</td></tr>';
		}
		$inner = '<h1 class="dxh"' . $st( "margin:0 0 12px;font:900 26px/1.15 $HEAVY;text-transform:uppercase;color:$INK" ) . '>' . $e( $title ) . '</h1>'
			. '<p' . $st( "margin:0 0 14px;font:16px/1.55 $SANS;color:$INK" ) . '>' . $e( $intro ) . '</p>'
			. ( $tiles !== '' ? '<table role="presentation" width="100%" cellpadding="0" cellspacing="6"' . $st( 'margin:0 0 8px' ) . '><tr>' . $tiles . '</tr></table>' : '' )
			. '<h2' . $st( "margin:22px 0 8px;padding-top:14px;border-top:2px solid $INK;font:700 19px/1.2 " . DBD_Email::COND . ";letter-spacing:.05em;text-transform:uppercase;color:$INK" ) . '>' . $e( $cap ) . '</h2>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . $st( "border-collapse:collapse;border-bottom:1px solid $LINE" ) . '>' . $list . '</table>'
			. DBD_Email::button( $url, $btn ) . DBD_Email::forward( $url . '#dbd-sign' );
		$card = $card ?? self::card_url( $kind, $y );
		$top = $card !== '' ? '<tr><td' . $st( 'padding:0' ) . '><a href="' . esc_url( $url ) . '"><img src="' . esc_url( $card ) . '" width="600" alt="' . esc_attr( $title ) . '"' . $st( 'display:block;width:100%;height:auto;border:0' ) . '></a></td></tr>' : '';
		$date = $date ?? wp_date( 'l, F j', null, new DateTimeZone( 'America/New_York' ) );
		return array( 'subject' => $title, 'pre' => $pre, 'html' => DBD_Email::frame( $title, $pre, $date, $top, $inner, $url ) );
	}
}
