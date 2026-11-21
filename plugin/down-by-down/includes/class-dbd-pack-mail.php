<?php
/**
 * The share pack by email (2.28.0, SCOPE §69): each new pack goes to the people who post it (Gene, Erik), Tuesday at 7:00 AM Eastern,
 * an hour before the weekly post and the Tuesday email. Through Brevo's transactional email with the Tuesday email's key, one copy per
 * person (nobody sees the others' addresses), never the subscriber list.
 *   - A sync that brings in a finished week books its send once: that week's Tuesday at 7:00, or a minute later when that's passed.
 *     A week more than two days past its Tuesday is old news (the plugin was installed late, or the data came in late): marked, not sent.
 *   - A failed send retries every 10 minutes for an hour, for the people it didn't reach only; then a note to the test address and
 *     Try again on the Share pack tab.
 *   - The Share pack tab sets who gets it (one address per line), switches it off, and sends the week shown now.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Pack_Mail {

	const OPT      = 'dbd_pack_mail';      /* the record: array( 'sent' => season => week, 'jobs' => 'season-wN' => job ) */
	const OPT_SET  = 'dbd_pack_mail_set';  /* the settings: array( 'on' => bool, 'to' => list of addresses ) */
	const HOOK     = 'dbd_pack_mail_send';
	const LATE     = 2 * 86400;            /* more than this past its Tuesday, a week isn't emailed */
	const MAX_TO   = 10;

	public static function init() {
		add_action( 'dbd_synced', array( __CLASS__, 'note' ) );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_action( 'admin_post_dbd_pack_now', array( __CLASS__, 'now_action' ) );
		add_action( 'admin_post_dbd_pack_again', array( __CLASS__, 'again_action' ) );
	}
	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK ); }

	/* ------------------------------------------------------------------ settings */

	public static function settings() {
		$o = get_option( self::OPT_SET, null );
		if ( ! is_array( $o ) ) { return array( 'on' => true, 'to' => array( DBD_Email::test_to() ) ); }  /* before it's saved: on, to the test address */
		return array( 'on' => ! empty( $o['on'] ), 'to' => array_values( array_filter( (array) ( $o['to'] ?? array() ), 'is_email' ) ) );
	}
	/** The addresses from the box: one per line (commas and spaces work too), valid ones only, no repeats, at most ten. */
	public static function parse_to( $text ) {
		$out = array();
		foreach ( preg_split( '/[\s,;]+/', (string) $text, -1, PREG_SPLIT_NO_EMPTY ) as $a ) { $a = strtolower( sanitize_email( $a ) ); if ( is_email( $a ) && ! in_array( $a, $out, true ) ) { $out[] = $a; } }
		return array_slice( $out, 0, self::MAX_TO );
	}
	public static function sanitize( $v ) {
		$v = is_array( $v ) ? $v : array();
		return array( 'on' => ! empty( $v['on'] ), 'to' => self::parse_to( is_array( $v['to'] ?? '' ) ? implode( "\n", $v['to'] ) : (string) ( $v['to'] ?? '' ) ) );
	}
	public static function to() { return self::settings()['to']; }
	/** Switched on, someone to send to, and Brevo's key in place. */
	public static function on() { $s = self::settings(); return $s['on'] && $s['to'] && (string) DBD_Email::get( 'key' ) !== ''; }

	/* ------------------------------------------------------------------ the record */

	public static function state() {
		$s = get_option( self::OPT, array() ); $s = is_array( $s ) ? $s : array();
		return array_merge( array( 'sent' => array(), 'jobs' => array() ), $s );
	}
	private static function save( $s ) { update_option( self::OPT, $s, false ); }
	public static function job( $key ) { return self::state()['jobs'][ $key ] ?? null; }
	private static function job_set( $key, $fields ) { $s = self::state(); $s['jobs'][ $key ] = array_merge( $s['jobs'][ $key ] ?? array(), $fields ); self::save( $s ); }
	public static function key( $season, $n ) { return (int) $season . '-w' . (int) $n; }
	private static function parse( $key ) { return preg_match( '/^(\d{4})-w(\d{1,2})$/', (string) $key, $m ) ? array( (int) $m[1], (int) $m[2] ) : null; }

	/** More than two days past week $n's Tuesday send time. */
	public static function late( $n, $now ) { return (int) $now > self::tuesday( $n ) + self::LATE; }

	/** Week $n's send time: 7:00 AM Eastern on its Tuesday (the weekly post's Tuesday, an hour earlier). */
	public static function tuesday( $n ) { return DBD_Post::tuesday( $n ) - HOUR_IN_SECONDS; }

	/**
	 * One step, with no side effects: the record after seeing pack week $n of $season at $now, whose Tuesday send time is $tue, and when to
	 * send it (null for not at all). Each week books once; a week the record already passed, or one more than two days late, never books.
	 */
	public static function decide( $s, $season, $n, $tue, $now ) {
		$n = (int) $n; if ( ! $n ) { return array( $s, null ); }
		$key = self::key( $season, $n );
		if ( isset( $s['jobs'][ $key ] ) || $n <= (int) ( $s['sent'][ $season ] ?? 0 ) ) { return array( $s, null ); }
		if ( $now > $tue + self::LATE ) { $s['sent'][ $season ] = $n; $s['jobs'][ $key ] = array( 'state' => 'skipped: more than two days late', 'at' => (int) $now ); return array( $s, null ); }
		$at = max( (int) $now + 60, (int) $tue );
		$s['jobs'][ $key ] = array( 'state' => 'booked', 'at' => $at, 'tries' => 0, 'done' => array() );
		return array( $s, $at );
	}

	/** After each sync: book the newest pack's send. */
	public static function note( $unused = null, $now = null ) {
		if ( ! self::on() || DBD_Data::archived() ) { return null; }
		$n = DBD_Pack::newest(); if ( ! $n ) { return null; }
		$now = $now ?? time(); $season = (int) DBD_Data::season();
		$s0 = self::state(); list( $s, $at ) = self::decide( $s0, $season, $n, self::tuesday( $n ), $now );
		if ( $s !== $s0 ) { self::save( $s ); }
		$key = self::key( $season, $n );
		if ( $at ) { wp_schedule_single_event( $at, self::HOOK, array( $key ) ); return $at; }
		/* a booking whose event is gone (the plugin was off when it came due): book it again, and send() decides if it's too late (Greptile, PR #127) */
		$st = (string) ( $s['jobs'][ $key ]['state'] ?? '' );
		if ( ( $st === 'booked' || strpos( $st, 'retrying' ) === 0 ) && ! wp_next_scheduled( self::HOOK, array( $key ) ) ) {
			$at = max( (int) $now + 60, (int) ( $s['jobs'][ $key ]['at'] ?? 0 ) ); wp_schedule_single_event( $at, self::HOOK, array( $key ) ); return $at;
		}
		return null;
	}

	/* ------------------------------------------------------------------ the email */

	private static function e( $s ) { return esc_html( (string) $s ); }

	/** Week $n's email: array( subject, html ), or null when there's no pack for it. */
	public static function build( $n ) {
		$items = DBD_Pack::items( $n ); if ( ! $items ) { return null; }
		$keys = DBD_Pack::keys( $n );
		$leads = array(); foreach ( $items as $it ) { if ( in_array( $it['k'], array( 'swing', 'snaps' ), true ) && ! empty( $it['lead'] ) ) { $leads[] = $it['lead']; } }
		if ( count( $leads ) < 2 ) { foreach ( $items as $it ) { if ( count( $leads ) < 2 && ! empty( $it['lead'] ) && ! in_array( $it['lead'], $leads, true ) ) { $leads[] = $it['lead']; } } }
		$subject = 'Week ' . (int) $n . ' share pack' . ( $leads ? ': ' . implode( ', ', $leads ) : '' );

		$INK = '#121417'; $MUTED = '#6b6f78'; $RED = '#cb2026'; $BG = '#f4f1ea'; $LINE = '#e3dfd5';
		$font = "font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
		$block = function ( $lab, $text, $extra = '' ) use ( $font, $MUTED, $INK, $LINE ) {
			return '<tr><td style="padding:0 0 10px"><div style="' . $font . ';font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:' . $MUTED . ';padding:0 0 4px">' . self::e( $lab ) . $extra . '</div>'
				. '<div style="' . $font . ';font-size:15px;line-height:1.5;color:' . $INK . ';background:#f6f5f1;border:1px solid ' . $LINE . ';border-radius:6px;padding:10px 12px">' . nl2br( self::e( $text ) ) . '</div></td></tr>';
		};
		/* Post to r/… (SCOPE §71): Reddit's new-post form, filled in, so whoever posts can do it from the email */
		$sub = DBD_Pack::our_sub();
		$post = function ( $title, $link, $to = null ) use ( $sub, $RED ) { $u = DBD_Pack::reddit_url( $title, $link, $to ); return $u === '' ? '' : ' · <a href="' . esc_url( $u ) . '" style="color:' . $RED . ';font-weight:600">Post to r/' . self::e( $to === null ? $sub : DBD_Pack::sanitize_sub( $to ) ) . '</a>'; };
		/* Post on X · Post on Bluesky (SCOPE §72) */
		$soc = function ( $it ) use ( $RED ) { $t = DBD_Pack::social( $it ); $o = '';
			if ( ( $u = DBD_Pack::x_url( $t['x'] ) ) !== '' ) { $o .= ' · <a href="' . esc_url( $u ) . '" style="color:' . $RED . ';font-weight:600">Post on X</a>'; }
			if ( ( $u = DBD_Pack::bsky_url( $t['bsky'] ) ) !== '' ) { $o .= ' · <a href="' . esc_url( $u ) . '" style="color:' . $RED . ';font-weight:600">Post on Bluesky</a>'; }
			if ( ( $u = DBD_Pack::fb_url( $it ) ) !== '' ) { $o .= ' · <a href="' . esc_url( $u ) . '" style="color:' . $RED . ';font-weight:600">Share on Facebook</a>'; }  /* SCOPE §73: opens the share box; paste the Reddit title there */
			if ( DBD_Pack::our_ig() !== '' && ! empty( $it['sq'] ) ) { $gu = preg_match( '/^ig-week-(\d+)-/', (string) $it['sq'], $gm ) ? DBD_Tall::preview( (int) $gm[1], $it ) : DBD_Card::url( $it['sq'] ); $o .= ' · <a href="' . esc_url( $gu ) . '" style="color:' . $RED . ';font-weight:600">Graphic for Instagram</a>'; }
			if ( ( $u = DBD_Pack::th_url( $it ) ) !== '' ) { $o .= ' · <a href="' . esc_url( $u ) . '" style="color:' . $RED . ';font-weight:600">Post on Threads</a>'; }  /* 2.42.0 */
			return $o; };
		$rows = '';
		foreach ( $items as $it ) {
			$img = esc_url( DBD_Card::url( $it['card'] ) ); $no = DBD_Pack::NUM[ $it['k'] ] ?? 0;
			$rows .= '<tr><td style="padding:28px 28px 8px;border-top:1px solid ' . $LINE . '"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
				. '<tr><td style="' . $font . ';font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:' . $RED . ';padding:0 0 10px">' . (int) $no . ' · ' . self::e( $it['h'] ) . '</td></tr>'
				. '<tr><td style="padding:0 0 14px"><a href="' . $img . '"><img src="' . $img . '" width="544" alt="' . esc_attr( $it['h'] ) . '" style="display:block;width:100%;max-width:544px;height:auto;border:1px solid ' . $LINE . '"></a></td></tr>'
				. $block( 'Reddit title', $it['title'] ) . $block( 'Reddit comment, with the link', $it['comment'] )
				. $block( 'X and Bluesky', $it['post'], ' <span style="font-weight:400;letter-spacing:0;text-transform:none">· ' . DBD_Pack::xlen( $it['post'] ) . ' of ' . DBD_Pack::X_MAX . '</span>' )
				. ( ( $cap = DBD_Pack::ig_caption( $it ) ) !== '' ? $block( 'Instagram caption, with the graphic', $cap ) : '' )
				. '<tr><td style="' . $font . ';font-size:14px;color:' . $MUTED . ';padding:2px 0 0">Fits: ' . self::e( implode( ' · ', $it['fits'] ) ) . ' · <a href="' . esc_url( $it['link'] ) . '" style="color:' . $RED . ';font-weight:600">Open the page</a>'
				. $post( $it['title'], DBD_Pack::share_link( $it ) ) . $soc( $it ) . '</td></tr></table></td></tr>';
		}
		$kh = '';
		foreach ( $keys as $kg ) {
			$g = $kg['g']; $lines = '';
			foreach ( $kg['sides'] as $sd ) {
				$lines .= '<div style="' . $font . ';font-size:14px;line-height:1.5;color:' . $INK . ';padding:6px 0 0"><span style="color:' . $MUTED . '">' . self::e( $sd['fits'] ) . '</span> · <b>' . self::e( rtrim( $sd['head'], '.' ) ) . '.</b> ' . self::e( $sd['say'] )
					. ' <a href="' . esc_url( $sd['link'] ) . '" style="color:' . $RED . ';font-weight:600">Link</a>' . $post( $sd['title'], $sd['link'] ) . $post( $sd['title'], $sd['link'], $sd['fits'] ) . $soc( $sd )
					. ( ( $cap = DBD_Pack::ig_caption( $sd ) ) !== '' ? '<div style="' . $font . ';font-size:13px;line-height:1.5;color:' . $MUTED . ';background:#f6f5f1;border:1px solid ' . $LINE . ';border-radius:6px;padding:6px 10px;margin-top:6px">' . nl2br( self::e( $cap ) ) . '</div>' : '' ) . '</div>';  /* ours, then the team's (2.29.1) */
			}
			$nk = function ( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; };
			$kh .= '<div style="padding:12px 0;border-top:1px solid ' . $LINE . '"><div style="' . $font . ';font-size:15px;font-weight:700;color:' . $INK . '">' . self::e( $nk( $g['away'] ) . ' at ' . $nk( $g['home'] ) ) . '</div>' . $lines . '</div>';
		}
		if ( $kh !== '' ) {
			$kh = '<tr><td style="padding:28px 28px 16px;border-top:1px solid ' . $LINE . '"><div style="' . $font . ';font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:' . $RED . ';padding:0 0 4px">' . (int) DBD_Pack::NUM['keys'] . ' · Keys to the game, Week ' . ( (int) $n + 1 ) . '</div>'
				. '<div style="' . $font . ';font-size:14px;line-height:1.5;color:#3b4255;padding:0 0 4px">Each team’s biggest edge against its next opponent, for its own subreddit. The game cards are on the Share pack tab.</div>' . $kh . '</td></tr>';
		}
		$admin = esc_url( admin_url( 'admin.php?page=down-by-down-share&week=' . (int) $n ) );
		$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . self::e( $subject ) . '</title></head><body style="margin:0;background:' . $BG . '">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $BG . '"><tr><td align="center" style="padding:24px 12px">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid ' . $LINE . '">'
			. '<tr><td style="padding:22px 28px 16px;border-bottom:5px solid ' . $RED . '"><img src="' . esc_url( DBD_URL . 'assets/card-logo.png' ) . '" height="30" alt="Down x Down" style="display:block;height:30px"></td></tr>'
			. '<tr><td style="padding:24px 28px 20px"><div style="' . $font . ';font-size:26px;font-weight:800;line-height:1.15;color:' . $INK . '">Week ' . (int) $n . ' share pack</div>'
			. '<div style="' . $font . ';font-size:15px;line-height:1.55;color:#3b4255;padding-top:8px">' . esc_html( ucfirst( array( 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight' )[ count( $items ) + ( $keys ? 1 : 0 ) ] ?? (string) ( count( $items ) + ( $keys ? 1 : 0 ) ) ) ) . ' things to post this week. Long-press any text to copy it; tap an image to open it full size and save it. On Reddit, post the image with the title, then the comment with the link.' . ( $sub !== '' ? ' For r/' . self::e( $sub ) . ', each Post link opens Reddit with the title and link filled in.' : '' ) . '</div></td></tr>'
			. $rows . $kh
			. '<tr><td style="padding:20px 28px 28px;border-top:1px solid ' . $LINE . '"><a href="' . $admin . '" style="display:inline-block;background:' . $RED . ';color:#ffffff;' . $font . ';font-size:15px;font-weight:700;text-decoration:none;padding:12px 20px;border-radius:6px">Open the Share pack</a>'
			. '<div style="' . $font . ';font-size:13px;color:' . $MUTED . ';padding-top:14px">Sent to the people who post for Down by Down. Change who gets it, or turn it off, on the Share pack tab.</div></td></tr>'
			. '</table></td></tr></table></body></html>';
		return array( 'subject' => $subject, 'html' => $html );
	}

	/** Send week $n's email to $to (a list). Returns array( reached, errors by address ). */
	public static function deliver( $n, $to ) {
		$doc = self::build( $n ); if ( ! $doc ) { return array( array(), array( '*' => 'there’s no pack for Week ' . (int) $n ) ); }
		$o = DBD_Email::opts(); $ok = array(); $bad = array();
		foreach ( $to as $a ) {
			list( , , $err ) = DBD_Email::api( 'POST', 'smtp/email', array(
				'sender' => array( 'name' => $o['from_name'], 'email' => $o['from_email'] ), 'to' => array( array( 'email' => $a ) ),
				'replyTo' => array( 'email' => is_email( $o['reply'] ) ? $o['reply'] : $o['from_email'] ), 'subject' => $doc['subject'], 'htmlContent' => $doc['html'],
			) );
			if ( $err === '' ) { $ok[] = $a; } else { $bad[ $a ] = $err; }
		}
		return array( $ok, $bad );
	}

	/** The booked send: each person not yet reached; retries for the rest. */
	public static function send( $key ) {
		$kp = self::parse( $key ); $job = self::job( $key ); if ( ! $kp || ! $job ) { return; }
		$st = (string) $job['state']; if ( $st !== 'booked' && strpos( $st, 'retrying' ) !== 0 ) { return; }
		if ( ! self::on() ) { self::job_set( $key, array( 'state' => 'not sent: the email is switched off or Brevo isn’t set up' ) ); return; }
		list( $season, $n ) = $kp;
		if ( (int) DBD_Data::season() !== $season ) { self::job_set( $key, array( 'state' => 'not sent: a new season started' ) ); return; }
		/* the first automatic send, run more than two days after its Tuesday (a late cron): old news, not sent; Try again still sends (Greptile, PR #127) */
		if ( $st === 'booked' && self::late( $n, time() ) ) { self::job_set( $key, array( 'state' => 'not sent: more than two days late' ) ); return; }
		$lock = 'dbd_pack_lock_' . $key;
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 300 ) { return; } update_option( $lock, time(), false ); }
		try {
			$done = (array) ( $job['done'] ?? array() ); $left = array_values( array_diff( self::to(), $done ) );
			list( $ok, $bad ) = $left ? self::deliver( $n, $left ) : array( array(), array() );
			$done = array_values( array_unique( array_merge( $done, $ok ) ) );
			if ( $bad ) { self::job_set( $key, array( 'done' => $done ) ); return self::retry( $key, implode( '; ', array_map( function ( $a, $e ) { return ( $a === '*' ? '' : $a . ': ' ) . $e; }, array_keys( $bad ), $bad ) ) ); }
			$s = self::state(); $s['jobs'][ $key ] = array_merge( $s['jobs'][ $key ], array( 'state' => 'sent', 'done' => $done, 'sent_at' => time() ) );
			$s['sent'][ $season ] = max( $n, (int) ( $s['sent'][ $season ] ?? 0 ) ); self::save( $s );
		} finally { delete_option( $lock ); }
	}
	private static function retry( $key, $err ) {
		$job = self::job( $key ) ?: array(); $t = (int) ( $job['tries'] ?? 0 ) + 1;
		if ( $t < DBD_Email::TRIES ) { self::job_set( $key, array( 'tries' => $t, 'state' => 'retrying: ' . $err ) ); wp_schedule_single_event( time() + DBD_Email::RETRY, self::HOOK, array( $key ) ); return; }
		self::job_set( $key, array( 'tries' => $t, 'state' => 'failed: ' . $err ) );
		$kp = self::parse( $key );
		wp_mail( DBD_Email::test_to(), 'Down by Down: the Week ' . (int) ( $kp[1] ?? 0 ) . ' share pack email didn’t send',
			"Brevo didn't take it after " . DBD_Email::TRIES . " tries over an hour.\n\nThe last error: $err\n\nTry again on the Share pack tab: " . admin_url( 'admin.php?page=down-by-down-share' ) . "\n" );
	}
	public static function failed( $key ) { return strpos( (string) ( self::job( $key )['state'] ?? '' ), 'failed' ) === 0; }

	/** The newest job's line for the tab: array( dot class, text, try-again key or '' ). */
	public static function status() {
		$s = self::state(); $season = (int) DBD_Data::season(); $out = array();
		$mine = array(); foreach ( $s['jobs'] as $k => $j ) { $p = self::parse( $k ); if ( $p && $p[0] === $season ) { $mine[ $p[1] ] = array( $k, $j ); } }
		krsort( $mine ); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); };
		foreach ( array_slice( $mine, 0, 2, true ) as $n => $kj ) {
			list( $k, $j ) = $kj; $st = (string) ( $j['state'] ?? '' ); $who = count( (array) ( $j['done'] ?? array() ) );
			if ( $st === 'sent' ) { $out[] = array( 'ok', 'Week ' . $n . ' pack sent ' . $when( $j['sent_at'] ?? $j['at'] ) . ' to ' . $who . ( $who === 1 ? ' person' : ' people' ) . '.', '' ); }
			elseif ( $st === 'booked' ) { $out[] = array( 'ok', 'Week ' . $n . ' pack booked for ' . $when( $j['at'] ) . '.', '' ); }
			elseif ( strpos( $st, 'retrying' ) === 0 ) { $out[] = array( 'warn', 'Week ' . $n . ' pack is retrying: ' . substr( $st, 10 ), '' ); }
			elseif ( strpos( $st, 'failed' ) === 0 ) { $out[] = array( 'bad', 'Week ' . $n . ' pack didn’t send: ' . substr( $st, 8 ), $k ); }
			else { $out[] = array( '', 'Week ' . $n . ' pack ' . $st . '.', '' ); }
		}
		return array_reverse( $out );
	}

	/* ------------------------------------------------------------------ the tab's buttons */

	private static function back( $args ) { wp_safe_redirect( add_query_arg( array_merge( array( 'page' => 'down-by-down-share' ), $args ), admin_url( 'admin.php' ) ) . '#dbd-pack-settings' ); exit; }  /* the notice shows in the settings box (SCOPE §74) */

	/** "Send this week's pack now": the week shown, to everyone on the list, right away. It doesn't change the Tuesday booking. */
	public static function now_action() {
		check_admin_referer( 'dbd_pack_now' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		$n = (int) ( $_POST['week'] ?? 0 ); $back = array( 'week' => $n );
		if ( ! in_array( $n, DBD_Pack::weeks(), true ) ) { self::back( array( 'pack' => 'error', 'pack_err' => rawurlencode( 'There’s no pack for that week.' ) ) ); }
		if ( (string) DBD_Email::get( 'key' ) === '' ) { self::back( $back + array( 'pack' => 'error', 'pack_err' => rawurlencode( 'Add the Brevo API key on Week in review first.' ) ) ); }
		$to = self::to(); if ( ! $to ) { self::back( $back + array( 'pack' => 'error', 'pack_err' => rawurlencode( 'Add at least one address first.' ) ) ); }
		list( $ok, $bad ) = self::deliver( $n, $to );
		if ( $bad ) { self::back( $back + array( 'pack' => 'error', 'pack_err' => rawurlencode( ( $ok ? 'Sent to ' . count( $ok ) . ', but not to ' : '' ) . implode( '; ', array_map( function ( $a, $e ) { return ( $a === '*' ? '' : $a . ': ' ) . $e; }, array_keys( $bad ), $bad ) ) ) ) ); }
		self::back( $back + array( 'pack' => 'sent', 'pack_n' => count( $ok ) ) );
	}
	public static function again_url( $key ) { return wp_nonce_url( admin_url( 'admin-post.php?action=dbd_pack_again&key=' . rawurlencode( $key ) ), 'dbd_pack_again_' . $key ); }
	public static function again_action() {
		$key = (string) ( $_GET['key'] ?? '' ); if ( ! self::parse( $key ) ) { wp_die( 'No.' ); }
		check_admin_referer( 'dbd_pack_again_' . $key ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		if ( self::failed( $key ) ) { self::job_set( $key, array( 'state' => 'retrying: again', 'tries' => 0 ) ); wp_schedule_single_event( time() + 5, self::HOOK, array( $key ) ); }
		self::back( array( 'pack' => 'again' ) );
	}
}
