<?php
/**
 * The Tuesday email (SCOPE §48): each week post, as it publishes, goes to the "Week in review" list in Brevo.
 *
 *   - Signing up: the home form (WPForms #63) hands its address to Brevo's double opt-in. Brevo sends the confirmation and adds the
 *     contact only after the click; the click lands on the newest week post with a "You're in" note. WordPress keeps no addresses.
 *   - Sending: the plugin's own week post going to published queues one send. The plugin builds the email from the post (every style
 *     inline, tables for layout), creates a Brevo campaign to the list and sends it. The campaign is stored on the post, so the post
 *     never sends twice. A post published more than a day after its date, a skipped post and anything published while the email is
 *     switched off never send. A failed call retries every 10 minutes for an hour, then tells Gene by email.
 *
 * compose() is pure (post HTML in, email HTML out), so the view harness renders the email without WordPress.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Email {

	const OPT     = 'dbd_email';         /* the settings, one array */
	const OPT_LOG = 'dbd_email_log';     /* each week's send: newest first, the last 30 */
	const OPT_SUB = 'dbd_email_signups'; /* signup counts and the last error: no addresses */
	const HOOK    = 'dbd_email_send';
	const API     = 'https://api.brevo.com/v3/';
	const M_SKIP  = '_dbd_email_skip';   /* "Don't email this one" */
	const M_CAMP  = '_dbd_email_campaign';
	const M_STATE = '_dbd_email_state';  /* queued, retrying: …, sent, failed: …, not sent: … (set once a post publishes; never reset) */
	const M_TRIES = '_dbd_email_tries';
	const M_AGAIN = '_dbd_email_again';  /* Gene pressed Try again on a failed send: it goes even though it's more than a day late (2.11.1) */
	const WINDOW  = 86400;               /* a post sends only within a day of its date: backfilled weeks and past seasons never email */
	const RETRY   = 600;
	const TRIES   = 7;                   /* the first try and six retries 10 minutes apart: an hour */

	/* the email's look: the site's ink, red and chalk, with system fonts (web fonts don't load in Gmail) */
	const INK = '#121417'; const MUT = '#5d616a'; const RED = '#cb2026'; const REDINK = '#b4261f'; const LINE = '#e3dfd6'; const PANEL = '#fffdf8'; const BG = '#efebe2';
	const SANS  = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
	const HEAVY = "'Arial Black','Helvetica Neue',Arial,sans-serif";
	const COND  = "'Arial Narrow','Helvetica Neue',Arial,sans-serif";

	public static function defaults() {
		return array(
			'on' => false, 'key' => '', 'list' => 0, 'tpl' => 0,
			'from_name' => 'Down by Down', 'from_email' => 'week@downbydown.com', 'reply' => 'week@downbydown.com',
			'address' => '808 Lady Street, Suite D, Columbia, SC 29201', 'form' => 63, 'test' => '',
		);
	}
	public static function opts() { $o = get_option( self::OPT, array() ); return array_merge( self::defaults(), is_array( $o ) ? $o : array() ); }
	public static function get( $k ) { return self::opts()[ $k ] ?? null; }
	/** Brevo is connected and a list is picked (sending); signups also need the confirmation template. */
	public static function ready() { $o = self::opts(); return ! empty( $o['on'] ) && $o['key'] !== '' && (int) $o['list'] > 0; }
	public static function test_to() { $o = self::opts(); return is_email( $o['test'] ) ? $o['test'] : ( is_email( $o['reply'] ) ? $o['reply'] : get_option( 'admin_email' ) ); }

	public static function init() {
		add_action( 'wpforms_process_complete', array( __CLASS__, 'signup' ), 10, 4 );
		add_action( 'transition_post_status', array( __CLASS__, 'published' ), 10, 3 );
		add_action( self::HOOK, array( __CLASS__, 'send' ) );
		add_action( 'template_redirect', array( __CLASS__, 'landing' ) );
		add_filter( 'the_content', array( __CLASS__, 'joined' ), 8 );
		add_action( 'add_meta_boxes', array( __CLASS__, 'box' ), 10, 2 );
		add_action( 'save_post_post', array( __CLASS__, 'save_box' ), 10, 2 );
		add_action( 'admin_post_dbd_email_test', array( __CLASS__, 'test_action' ) );
		add_action( 'admin_post_dbd_email_again', array( __CLASS__, 'again_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
	}

	/* ------------------------------------------------------------------ Brevo */

	/** One Brevo API call: array( HTTP code, decoded body, error text or '' ). */
	public static function api( $method, $path, $body = null, $key = null ) {
		$args = array( 'method' => $method, 'timeout' => 15, 'headers' => array( 'api-key' => (string) ( $key ?? self::get( 'key' ) ), 'accept' => 'application/json', 'content-type' => 'application/json' ) );
		if ( $body !== null ) { $args['body'] = wp_json_encode( $body ); }
		$r = wp_remote_request( self::API . $path, $args );
		if ( is_wp_error( $r ) ) { return array( 0, null, 'Brevo could not be reached: ' . $r->get_error_message() ); }
		$code = (int) wp_remote_retrieve_response_code( $r ); $j = json_decode( (string) wp_remote_retrieve_body( $r ), true );
		$err = ( $code >= 200 && $code < 300 ) ? '' : 'Brevo answered ' . $code . ( is_array( $j ) && ! empty( $j['message'] ) ? ': ' . $j['message'] : '' );
		return array( $code, $j, $err );
	}

	const READS = array( 'account', 'contacts/lists?limit=50', 'smtp/templates?templateStatus=true&limit=100' );  /* what the settings page reads */
	private static function ckey( $path, $key ) { return 'dbd_brevo_' . md5( $path . '|' . $key ); }

	/**
	 * A GET kept for 10 minutes, for the settings page (the account, the lists, the templates). Only a good answer with something in
	 * it is kept: an error or an empty list (before Gene has made the list or the template) is asked again on the next load (2.10.1).
	 */
	public static function cached( $path ) {
		$k = self::ckey( $path, (string) self::get( 'key' ) );
		$v = get_transient( $k ); if ( is_array( $v ) ) { return $v; }
		$r = self::api( 'GET', $path );
		if ( $r[2] === '' && is_array( $r[1] ) && ( ! isset( $r[1]['count'] ) || (int) $r[1]['count'] > 0 ) ) { set_transient( $k, $r, 600 ); }
		return $r;
	}

	/** Saving the settings asks Brevo again, so a list or template made a minute ago shows at once. */
	public static function forget_reads( $keys ) {
		foreach ( array_unique( array_filter( (array) $keys ) ) as $key ) { foreach ( self::READS as $path ) { delete_transient( self::ckey( $path, (string) $key ) ); } }
	}

	/* ------------------------------------------------------------------ signing up */

	/** WPForms has saved a submission: the signup form's address goes to Brevo's double opt-in. The form carries on either way. */
	public static function signup( $fields, $entry, $form_data, $entry_id ) {
		$o = self::opts();
		if ( ! self::ready() || (int) $o['tpl'] < 1 || (int) ( $form_data['id'] ?? 0 ) !== (int) $o['form'] ) { return; }
		$email = ''; $other = false;
		foreach ( (array) $fields as $f ) {
			$v = trim( (string) ( $f['value'] ?? '' ) );
			if ( ( $f['type'] ?? '' ) === 'email' && $email === '' ) { $email = $v; } elseif ( $v !== '' ) { $other = true; }
		}
		if ( $other || ! is_email( $email ) ) { return; }  /* the form is one email field: anything else filled in is the spam trap */
		list( , $j, $err ) = self::api( 'POST', 'contacts/doubleOptinConfirmation', array(
			'email' => $email, 'includeListIds' => array( (int) $o['list'] ), 'templateId' => (int) $o['tpl'], 'redirectionUrl' => home_url( '/?dbd_joined=1' ),
		) );
		$s = get_option( self::OPT_SUB, array() ); $s = is_array( $s ) ? $s : array();
		if ( $err === '' ) { $s['ok'] = (int) ( $s['ok'] ?? 0 ) + 1; $s['last'] = time(); }
		else { $s['err'] = (int) ( $s['err'] ?? 0 ) + 1; $s['last_err'] = array( time(), $err ); }
		update_option( self::OPT_SUB, $s, false );
	}

	/** The confirm link goes to the home page with ?dbd_joined=1, which forwards to the newest week post, so the landing is never stale. */
	public static function landing() {
		if ( empty( $_GET['dbd_joined'] ) || is_singular( 'post' ) || is_admin() ) { return; }
		$p = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'category_name' => DBD_Post::CAT, 'fields' => 'ids' ) );
		if ( $p ) { wp_safe_redirect( add_query_arg( 'dbd_joined', '1', get_permalink( (int) $p[0] ) ), 302 ); exit; }
	}

	/** The note on top of the post a new subscriber lands on. */
	public static function joined( $content ) {
		/* Divi's Theme Builder draws the post with its Post Content module, outside the loop, so the test is the post being viewed and
		   its week-in-review body, once (2.10.2: in_the_loop() left the note off the live site) */
		if ( empty( $_GET['dbd_joined'] ) || is_feed() || ! is_singular( 'post' ) || (int) get_the_ID() !== (int) get_queried_object_id() ) { return $content; }
		if ( strpos( (string) $content, 'dbd-post' ) === false || strpos( (string) $content, 'dbd-joined' ) !== false ) { return $content; }
		$w = get_post_meta( get_the_ID(), DBD_Post::M_WEEK, true ); if ( $w === '' ) { return $content; }
		return self::joined_note( (int) $w ) . $content;
	}
	public static function joined_note( $w ) { return '<p class="dbd-joined"><b>You’re in.</b> The next one arrives Tuesday morning. This is the latest, ' . esc_html( DBD_Router::week_label( (int) $w ) ) . '.</p>'; }

	/* ------------------------------------------------------------------ sending */

	/** Whether a post dated $date_gmt (MySQL GMT) is too old to email at $now: more than a day past its date. */
	public static function late( $date_gmt, $now ) {
		$t = strtotime( $date_gmt . ' UTC' );
		return ! $t || $now - $t > self::WINDOW;
	}

	/** Why a week post that just published won't send, or '' when it will. */
	private static function why_not( $p, $now ) {
		if ( ! self::get( 'on' ) ) { return 'the email was switched off'; }
		if ( ! self::ready() ) { return 'Brevo isn’t set up'; }
		if ( get_post_meta( $p->ID, self::M_SKIP, true ) ) { return 'you chose not to email this one'; }
		if ( self::late( $p->post_date_gmt, $now ) ) { return 'it published more than a day after its date'; }
		return '';
	}

	/** A week post going live: decide once whether it sends, and queue it. Unpublishing and publishing again never sends again. */
	public static function published( $new, $old, $p ) {
		if ( $new !== 'publish' || $old === 'publish' || ! $p || $p->post_type !== 'post' ) { return; }
		if ( get_post_meta( $p->ID, DBD_Post::M_WEEK, true ) === '' || get_post_meta( $p->ID, self::M_STATE, true ) !== '' ) { return; }
		$why = self::why_not( $p, time() );
		if ( $why !== '' ) { update_post_meta( $p->ID, self::M_STATE, 'not sent: ' . $why ); return; }
		update_post_meta( $p->ID, self::M_STATE, 'queued' ); update_post_meta( $p->ID, self::M_TRIES, 0 );
		wp_schedule_single_event( time() + 5, self::HOOK, array( (int) $p->ID ) );  /* in the background, so publishing never waits on Brevo */
	}

	/** Send a queued post: create the campaign once, then send it. Runs from cron, and again on a retry. */
	public static function send( $id ) {
		$id = (int) $id; $p = get_post( $id ); if ( ! $p ) { return; }
		$state = (string) get_post_meta( $id, self::M_STATE, true );
		if ( $state !== 'queued' && strpos( $state, 'retrying' ) !== 0 ) { return; }
		$camp = (int) get_post_meta( $id, self::M_CAMP, true );
		$stop = $p->post_status !== 'publish' ? 'it was unpublished before it went' : ( get_post_meta( $id, self::M_SKIP, true ) ? 'you chose not to email this one' : ( ! self::ready() ? 'the email was switched off' : ( ! $camp && ! get_post_meta( $id, self::M_AGAIN, true ) && self::late( $p->post_date_gmt, time() ) ? 'it was more than a day late (the site’s scheduled tasks may not have run)' : '' ) ) );
		if ( $stop !== '' ) { update_post_meta( $id, self::M_STATE, 'not sent: ' . $stop ); self::log( $p, 'not sent: ' . $stop ); return; }
		$lock = 'dbd_email_lock_' . $id;  /* one sender per post: a retry and a slow first try can overlap */
		if ( ! add_option( $lock, time(), '', false ) ) { if ( time() - (int) get_option( $lock, 0 ) < 300 ) { return; } update_option( $lock, time(), false ); }
		try {
			$o = self::opts();
			if ( ! $camp ) {
				$doc = self::build( $p );
				list( , $j, $err ) = self::api( 'POST', 'emailCampaigns', array(
					'name' => $doc['subject'] . ' (' . (int) get_post_meta( $id, DBD_Post::M_SEASON, true ) . ')', 'subject' => $doc['subject'], 'previewText' => $doc['pre'],
					'sender' => array( 'name' => $o['from_name'], 'email' => $o['from_email'] ), 'replyTo' => $o['reply'], 'htmlContent' => $doc['html'],
					'recipients' => array( 'listIds' => array( (int) $o['list'] ) ), 'inlineImageActivation' => false,  /* no campaign tag: Brevo's free plan refuses it (405, Week 9, 2.11.1) */
				) );
				if ( $err !== '' || empty( $j['id'] ) ) { return self::retry( $p, $err ?: 'Brevo didn’t return a campaign' ); }
				$camp = (int) $j['id']; update_post_meta( $id, self::M_CAMP, $camp );
			}
			list( , , $err ) = self::api( 'POST', 'emailCampaigns/' . $camp . '/sendNow' );
			if ( $err !== '' ) {  /* a retry after a send that went through but timed out: Brevo already has it */
				list( , $cj ) = self::api( 'GET', 'emailCampaigns/' . $camp );
				if ( in_array( (string) ( $cj['status'] ?? '' ), array( 'sent', 'queued', 'in_process' ), true ) ) { $err = ''; }
			}
			if ( $err !== '' ) { return self::retry( $p, $err ); }
			list( , $lj ) = self::api( 'GET', 'contacts/lists/' . (int) $o['list'] );
			$n = (int) ( $lj['uniqueSubscribers'] ?? $lj['totalSubscribers'] ?? 0 );
			update_post_meta( $id, self::M_STATE, 'sent' );
			self::log( $p, 'sent', $camp, $n );
		} finally { delete_option( $lock ); }
	}

	private static function retry( $p, $err ) {
		$t = (int) get_post_meta( $p->ID, self::M_TRIES, true ) + 1; update_post_meta( $p->ID, self::M_TRIES, $t );
		if ( $t < self::TRIES ) {
			update_post_meta( $p->ID, self::M_STATE, 'retrying: ' . $err );
			wp_schedule_single_event( time() + self::RETRY, self::HOOK, array( (int) $p->ID ) );
			return;
		}
		update_post_meta( $p->ID, self::M_STATE, 'failed: ' . $err );
		self::log( $p, 'failed: ' . $err, (int) get_post_meta( $p->ID, self::M_CAMP, true ) );
		$title = html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' );
		wp_mail( self::test_to(), 'Down by Down: the email for "' . $title . '" didn’t send',
			"The post published, but Brevo didn't take the email after " . self::TRIES . " tries over an hour.\n\nThe last error: $err\n\n"
			. "The post: " . get_permalink( $p ) . "\nThe email settings and send log: " . admin_url( 'admin.php?page=down-by-down-week#dbd-email' ) . "\n\n"
			. "It won't try again on its own. If you want it to go out, send it from Brevo (Campaigns), or tell Claude." );
	}

	private static function log( $p, $result, $camp = 0, $n = 0 ) {
		$l = get_option( self::OPT_LOG, array() ); $l = is_array( $l ) ? $l : array();
		array_unshift( $l, array( 'at' => time(), 'post' => (int) $p->ID, 'title' => html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ), 'result' => $result, 'camp' => (int) $camp, 'n' => (int) $n ) );
		update_option( self::OPT_LOG, array_slice( $l, 0, 30 ), false );
	}

	/* ------------------------------------------------------------------ the email */

	/** A post's email: array( subject, pre, html ). Built at send time, so Gene's edits to the post are in it. */
	public static function build( $p ) {
		$c = (string) preg_replace( '/<!--.*?-->/s', '', (string) $p->post_content );  /* block editor comments, if the post was opened in it */
		if ( stripos( $c, '<p' ) === false ) { $c = wpautop( $c ); }
		$et = new DateTimeZone( 'America/New_York' ); $t = strtotime( $p->post_date_gmt . ' UTC' );
		$date = $t && $t > 0 ? wp_date( 'l, F j', $t, $et ) : '';
		/* each game's recap, keyed by its address as the post wrote it (SCOPE §54). An archived season's post (a test of an old week) reads that
		   season's games and recaps, then the addresses are made back in the current season, the way the post's links were (Greptile, PR #91). */
		$wk = (int) get_post_meta( $p->ID, DBD_Post::M_WEEK, true ); $hl = array(); $ids = array();
		$ps = (int) get_post_meta( $p->ID, DBD_Post::M_SEASON, true ); $sw = $ps && $ps !== DBD_Data::current_season() && DBD_Data::use_season( $ps );
		foreach ( DBD_Data::week_games( $wk ) as $g ) { $r = DBD_Videos::recap( $g ); if ( $r ) { $ids[ $g['id'] ] = DBD_Videos::url( $r ); } }
		if ( $sw ) { DBD_Data::use_season( null ); }
		foreach ( $ids as $gid => $u ) { $hl[ DBD_Router::url( array( 'game' => $gid ) ) ] = $u; }
		$doc = self::compose( html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ), $c, get_permalink( $p ), (string) get_the_post_thumbnail_url( $p, 'full' ), $date, $wk, null, $hl );
		/* an archived season's post (a test of an old week): every app link in the finished email, the added section links and the
		   week button included, opens that season's pages (Greptile, PR #75) */
		$s = (int) get_post_meta( $p->ID, DBD_Post::M_SEASON, true );
		if ( $s && $s !== DBD_Data::current_season() ) { $doc['html'] = DBD_Post::with_season( $doc['html'], $s ); }
		return $doc;
	}

	/** Links back to the site at the end of a section, by its heading (a heading Gene renames simply gets none). */
	private static function ctas( $n ) {
		$wk = DBD_Router::week_label( $n );
		return array(
			'The scores'          => array( 'Every play of every ' . $wk . ' game, with the win chances', array( 'view' => 'week', 'week' => $n ) ),
			'Players of the week' => array( 'Every player’s season, by position', array( 'view' => 'players' ) ),
			'Fantasy'             => array( 'Fantasy leaders all season, by position', array( 'view' => 'leaders', 'g' => 'fan' ) ),
			'Rising'              => array( 'Follow your own players on My team', array( 'view' => 'myteam' ) ),
		);
	}

	private static function s( $css ) { return ' style="' . $css . '"'; }

	/** The post's HTML with every style inline and its tables rebuilt for email: what Brevo sends. */
	/**
	 * $hl: a game's address on the site => its NFL recap on YouTube. Each score with one gets a "Highlights" link (SCOPE §54); a game whose recap
	 * isn't posted when the email goes (Monday night's, sometimes) has none.
	 */
	public static function body( $c, $n, &$week, $hl = array() ) {
		$INK = self::INK; $MUT = self::MUT; $LINE = self::LINE; $SANS = self::SANS;
		$week = preg_match( '#<p class="full">.*?href="([^"]+)"#s', $c, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : '';
		$c = (string) preg_replace( '#<p class="full">.*?</p>#s', '', $c );
		$c = (string) preg_replace( '#</?div[^>]*>#', '', $c );

		/* each section's link back to the site, added at its end */
		$ctas = self::ctas( $n ); $parts = preg_split( '#(<h2[^>]*>.*?</h2>)#s', $c, -1, PREG_SPLIT_DELIM_CAPTURE ); $c = ''; $head = '';
		foreach ( $parts as $i => $part ) {
			if ( $i % 2 ) { $head = trim( wp_strip_all_tags( $part ) ); $c .= $part; continue; }
			$c .= $part;
			if ( $head !== '' && isset( $ctas[ $head ] ) ) { $c .= '<p class="cta"><a href="' . esc_url( DBD_Router::url( $ctas[ $head ][1] ) ) . '">' . esc_html( $ctas[ $head ][0] ) . '</a></p>'; }
		}

		$table = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . self::s( "border-collapse:collapse;margin:0 0 14px;border-bottom:1px solid $LINE" ) . '>';
		/* the scores: one cell a game, the note under the score */
		$c = (string) preg_replace_callback( '#<table class="sc">.*?</table>#s', function ( $m ) use ( $table, $INK, $MUT, $LINE, $SANS, $hl ) {
			preg_match_all( '#<tr>\s*<td>(.*?)</td>\s*<td class="tg">(.*?)</td>\s*</tr>#s', $m[0], $rows, PREG_SET_ORDER ); $out = '';
			foreach ( $rows as $r ) {
				$a = (string) preg_replace( '#<img[^>]*src="([^"]*)"[^>]*>#', '<img src="$1" width="22" height="22" alt=""' . self::s( 'vertical-align:-6px;margin-right:8px;border:0' ) . '>', $r[1] );
				$hu = preg_match( '#href="([^"]+)"#', $r[1], $hm ) ? ( $hl[ html_entity_decode( $hm[1], ENT_QUOTES ) ] ?? '' ) : '';
				$a = str_replace( '<a ', '<a' . self::s( "color:$INK;text-decoration:none;font:400 15px/1.4 $SANS" ) . ' ', $a )
					. ( $hu !== '' ? ' <a href="' . esc_url( $hu ) . '"' . self::s( 'margin-left:6px;font:600 13px/1.4 ' . $SANS . ';color:' . self::REDINK . ';text-decoration:underline' ) . '>Highlights</a>' : '' );
				$out .= '<tr><td' . self::s( "padding:9px 0;border-top:1px solid $LINE" ) . '>' . $a . ( trim( $r[2] ) !== '' ? '<div' . self::s( "margin:2px 0 0 30px;font:13px/1.4 $SANS;color:$MUT" ) . '>' . $r[2] . '</div>' : '' ) . '</td></tr>';
			}
			return $table . $out . '</table>';
		}, $c );
		/* player rows: the photo, then name, context and line stacked */
		$c = (string) preg_replace_callback( '#<table class="pl">.*?</table>#s', function ( $m ) use ( $table, $INK, $MUT, $LINE, $SANS ) {
			preg_match_all( '#<tr>\s*<td class="ph">(.*?)</td>\s*<td>(.*?)</td>\s*<td class="ln">(.*?)</td>\s*</tr>#s', $m[0], $rows, PREG_SET_ORDER ); $out = '';
			foreach ( $rows as $r ) {
				$img = preg_match( '#src="([^"]*)"#', $r[1], $sm ) ? '<img src="' . $sm[1] . '" width="40" height="40" alt=""' . self::s( 'display:block;border-radius:20px;background:#efebe2;border:0' ) . '>' : '';
				$nm = str_replace( array( '<a ', '<small>', '</small>' ), array( '<a' . self::s( "color:$INK;text-decoration:none" ) . ' ', '<br><span' . self::s( "font:12px/1.4 $SANS;color:$MUT" ) . '>', '</span>' ), $r[2] );
				$ln = (string) preg_replace( '#<span>(.*?)</span>#', ' <b' . self::s( "color:$INK" ) . '>$1</b>', $r[3] );
				$out .= '<tr><td width="52" valign="top"' . self::s( "padding:10px 0;border-top:1px solid $LINE" ) . '>' . $img . '</td>'
					. '<td valign="top"' . self::s( "padding:10px 0;border-top:1px solid $LINE;font:600 15px/1.4 $SANS;color:$INK" ) . '>' . $nm
					. '<div' . self::s( "margin-top:3px;font:400 14px/1.4 $SANS;color:#3b4255" ) . '>' . $ln . '</div></td></tr>';
			}
			return $table . $out . '</table>';
		}, $c );
		/* next week's matchup line, with its logos */
		$c = (string) preg_replace_callback( '#<p class="next">(.*?)</p>#s', function ( $m ) use ( $INK, $SANS ) {
			return '<p' . self::s( "margin:0 0 8px;font:700 17px/1.4 $SANS;color:$INK" ) . '>' . preg_replace( '#<img[^>]*src="([^"]*)"[^>]*>#', '<img src="$1" width="26" height="26" alt=""' . self::s( 'vertical-align:-7px;margin:0 6px;border:0' ) . '>', $m[1] ) . '</p>';
		}, $c );
		$c = (string) preg_replace( '#<p class="cta">#', '<p' . self::s( "margin:0 0 6px;font:600 14px/1.5 $SANS" ) . '>', $c );
		$c = (string) preg_replace( '#<h2[^>]*>#', '<h2' . self::s( "margin:26px 0 10px;padding-top:14px;border-top:2px solid $INK;font:700 19px/1.2 " . self::COND . ";letter-spacing:.05em;text-transform:uppercase;color:$INK" ) . '>', $c );
		$c = (string) preg_replace( '#<h3[^>]*>#', '<h3' . self::s( "margin:20px 0 8px;font:700 16px/1.3 $SANS;color:$INK" ) . '>', $c );
		$c = (string) preg_replace( '#<p class="open">#', '<p' . self::s( "margin:0 0 14px;font:17px/1.55 $SANS;color:$INK" ) . '>', $c );
		$c = (string) preg_replace( '#<p(?![^>]*style=)(\s[^>]*)?>#', '<p' . self::s( "margin:0 0 14px;font:16px/1.6 $SANS;color:$INK" ) . '>', $c );
		$c = (string) preg_replace( '#<(ul|ol)(?![^>]*style=)(\s[^>]*)?>#', '<$1' . self::s( "margin:0 0 14px;padding-left:22px;font:16px/1.6 $SANS;color:$INK" ) . '>', $c );
		$c = (string) preg_replace( '#<img(?![^>]*style=)#', '<img' . self::s( 'max-width:100%;height:auto;border:0' ), $c );  /* an image Gene adds */
		$c = (string) preg_replace( '#<a (?![^>]*style=)#', '<a' . self::s( 'color:' . self::REDINK . ';text-decoration:underline' ) . ' ', $c );
		$c = (string) preg_replace( '#href="/(?!/)#', 'href="' . home_url( '/' ), $c );  /* a link Gene adds to one of his own pages */
		$c = (string) preg_replace( '#\s(?:class|loading)="[^"]*"#', '', $c );
		return $c;
	}

	/** The whole email. $n is the week, $card the share card, $date the day it goes. Returns array( subject, pre, html ). */
	public static function compose( $title, $content, $link, $card, $date, $n, $o = null, $hl = array() ) {
		$o = $o ?: self::opts(); $INK = self::INK; $MUT = self::MUT; $SANS = self::SANS; $e = 'esc_html';
		$week = ''; $body = self::body( (string) $content, (int) $n, $week, (array) $hl );
		$week = $week !== '' ? $week : DBD_Router::url( array( 'view' => 'week', 'week' => (int) $n ) );
		$open = preg_match( '#<p class="open">(.*?)</p>#s', (string) $content, $m ) ? trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) ) : '';
		$pre = preg_match( '/^(.+?[.!?])(\s|$)/u', $open, $m ) ? $m[1] : $open;
		$wk = DBD_Router::week_label( (int) $n );
		$html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting">'
			. '<title>' . $e( $title ) . '</title><style>@media (max-width:620px){.dxp{padding-left:18px!important;padding-right:18px!important}.dxh{font-size:22px!important}}</style></head>'
			. '<body' . self::s( 'margin:0;padding:0;background:' . self::BG ) . '>'
			. '<div' . self::s( 'display:none;max-height:0;overflow:hidden;mso-hide:all' ) . '>' . $e( $pre ) . '</div>'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . self::s( 'background:' . self::BG ) . '><tr><td align="center"' . self::s( 'padding:20px 10px' ) . '>'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0"' . self::s( 'width:100%;max-width:600px;background:' . self::PANEL . ';border:1px solid #dcd8ce' ) . '>'
			. '<tr><td class="dxp"' . self::s( 'background:#0f1311;padding:16px 24px' ) . '><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
			. '<td' . self::s( 'font:900 20px/1 ' . self::HEAVY . ';letter-spacing:.03em;color:#ffffff' ) . '><a href="' . esc_url( home_url( '/' ) ) . '"' . self::s( 'color:#ffffff;text-decoration:none' ) . '>DOWN <span' . self::s( 'color:' . self::RED ) . '>X</span> DOWN</a></td>'
			. '<td align="right"' . self::s( "font:12px/1.2 $SANS;color:#b9bcc2" ) . '>' . $e( $date ) . '</td></tr></table></td></tr>'
			. ( $card !== '' ? '<tr><td' . self::s( 'padding:0' ) . '><a href="' . esc_url( $link ) . '"><img src="' . esc_url( $card ) . '" width="600" alt="' . esc_attr( $title ) . '"' . self::s( 'display:block;width:100%;height:auto;border:0' ) . '></a></td></tr>' : '' )
			. '<tr><td class="dxp"' . self::s( 'padding:26px 28px 8px' ) . '>'
			. '<h1 class="dxh"' . self::s( "margin:0 0 16px;font:900 26px/1.15 " . self::HEAVY . ";text-transform:uppercase;color:$INK" ) . '>' . $e( $title ) . '</h1>'
			. $body
			. '<table role="presentation" cellpadding="0" cellspacing="0"' . self::s( 'margin:22px 0 26px' ) . '><tr><td' . self::s( 'background:' . self::RED . ';border-radius:4px' ) . '>'
			. '<a href="' . esc_url( $week ) . '"' . self::s( "display:inline-block;padding:13px 22px;font:700 15px/1 $SANS;color:#ffffff;text-decoration:none" ) . '>Every play from ' . $e( $wk ) . '</a></td></tr></table>'
			. '</td></tr>'
			. '<tr><td class="dxp"' . self::s( 'background:#f4f1ea;padding:20px 28px;border-top:1px solid ' . self::LINE . ";font:12px/1.6 $SANS;color:$MUT" ) . '>'
			. 'You’re getting this because you signed up for the week in review at <a href="' . esc_url( home_url( '/' ) ) . '"' . self::s( "color:$MUT" ) . '>downbydown.com</a>.<br>'
			. '<a href="' . esc_url( $link ) . '"' . self::s( "color:$MUT" ) . '>View in browser</a> · <a href="{{ unsubscribe }}"' . self::s( "color:$MUT" ) . '>Unsubscribe</a><br>'
			. 'Down by Down · ' . $e( $o['address'] ) . '<br>Not affiliated with the NFL. Data: nflverse.</td></tr>'
			. '</table></td></tr></table></body></html>';
		return array( 'subject' => $title, 'pre' => $pre, 'html' => $html );
	}

	/** The confirmation email Brevo sends a new signup, as HTML to paste into a Brevo template tagged "optin". */
	public static function confirm_html() {
		$INK = self::INK; $MUT = self::MUT; $SANS = self::SANS;
		return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Confirm your email</title></head>'
			. '<body' . self::s( 'margin:0;padding:0;background:' . self::BG ) . '><table role="presentation" width="100%" cellpadding="0" cellspacing="0"' . self::s( 'background:' . self::BG ) . '><tr><td align="center"' . self::s( 'padding:20px 10px' ) . '>'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0"' . self::s( 'width:100%;max-width:600px;background:' . self::PANEL . ';border:1px solid #dcd8ce' ) . '>'
			. '<tr><td' . self::s( 'background:#0f1311;padding:16px 24px;font:900 20px/1 ' . self::HEAVY . ';letter-spacing:.03em;color:#ffffff' ) . '>DOWN <span' . self::s( 'color:' . self::RED ) . '>X</span> DOWN</td></tr>'
			. '<tr><td' . self::s( 'padding:28px 28px 30px' ) . '><h1' . self::s( "margin:0 0 14px;font:900 24px/1.15 " . self::HEAVY . ";text-transform:uppercase;color:$INK" ) . '>One click and you’re in</h1>'
			. '<p' . self::s( "margin:0 0 18px;font:16px/1.6 $SANS;color:$INK" ) . '>Confirm your email to get the week in review every Tuesday morning: the upsets, the swing that turned a game, the best play and the players whose roles are growing.</p>'
			. '<table role="presentation" cellpadding="0" cellspacing="0"' . self::s( 'margin:0 0 20px' ) . '><tr><td' . self::s( 'background:' . self::RED . ';border-radius:4px' ) . '>'
			. '<a href="{{ doubleoptin }}"' . self::s( "display:inline-block;padding:13px 22px;font:700 15px/1 $SANS;color:#ffffff;text-decoration:none" ) . '>Confirm my email</a></td></tr></table>'
			. '<p' . self::s( "margin:0;font:14px/1.6 $SANS;color:$MUT" ) . '>If you didn’t sign up, ignore this email and you won’t hear from us.</p></td></tr>'
			. '<tr><td' . self::s( 'background:#f4f1ea;padding:20px 28px;border-top:1px solid ' . self::LINE . ";font:12px/1.6 $SANS;color:$MUT" ) . '>Someone, hopefully you, signed up for the week in review at downbydown.com.<br>Down by Down · ' . esc_html( self::get( 'address' ) ) . '</td></tr>'
			. '</table></td></tr></table></body></html>';
	}

	/* ------------------------------------------------------------------ tests, the post's box */

	/** Email $p to the test address only, any time, through Brevo's transactional email. Returns '' or the error. */
	public static function test( $p ) {
		if ( (string) self::get( 'key' ) === '' ) { return 'Add the Brevo API key first.'; }
		$o = self::opts(); $doc = self::build( $p );
		list( , , $err ) = self::api( 'POST', 'smtp/email', array(
			'sender' => array( 'name' => $o['from_name'], 'email' => $o['from_email'] ), 'to' => array( array( 'email' => self::test_to() ) ), 'replyTo' => array( 'email' => $o['reply'] ),
			'subject' => '[Test] ' . $doc['subject'], 'htmlContent' => str_replace( '{{ unsubscribe }}', esc_url( home_url( '/' ) ), $doc['html'] ),
		) );
		return $err;
	}

	/** The newest week post, scheduled or published: what "Send a test" on the settings page sends. */
	public static function latest() {
		$p = get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'future', 'draft' ), 'numberposts' => 1, 'category_name' => DBD_Post::CAT, 'orderby' => 'date', 'order' => 'DESC' ) );
		return $p ? $p[0] : null;
	}

	public static function test_action() {
		check_admin_referer( 'dbd_email_test' );
		$pid = (int) ( $_GET['post'] ?? 0 );
		if ( ! current_user_can( $pid ? 'edit_post' : 'manage_options', $pid ) ) { wp_die( 'No.' ); }
		$p = $pid ? get_post( $pid ) : self::latest();
		$err = $p ? self::test( $p ) : 'There’s no week post to send yet.';
		set_transient( 'dbd_email_test_' . get_current_user_id(), $err === '' ? array( 'ok', 'Test sent to ' . self::test_to() . ': "' . html_entity_decode( $p->post_title, ENT_QUOTES, 'UTF-8' ) . '".' ) : array( 'error', 'The test didn’t send. ' . $err ), 120 );
		wp_safe_redirect( $pid ? get_edit_post_link( $pid, 'url' ) : admin_url( 'admin.php?page=down-by-down-week#dbd-email' ) );
		exit;
	}

	public static function notice() {
		$k = 'dbd_email_test_' . get_current_user_id(); $v = get_transient( $k ); if ( ! is_array( $v ) ) { return; }
		delete_transient( $k );
		echo '<div class="notice notice-' . ( $v[0] === 'ok' ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $v[1] ) . '</p></div>';
	}

	public static function box( $type, $p ) {
		if ( $type !== 'post' || ! $p || get_post_meta( $p->ID, DBD_Post::M_WEEK, true ) === '' ) { return; }
		add_meta_box( 'dbd-email', 'Tuesday email', array( __CLASS__, 'box_html' ), 'post', 'side', 'high' );
	}

	/** Whether a post's send failed for good, so Try again can start it over. */
	public static function failed( $id ) { return strpos( (string) get_post_meta( (int) $id, self::M_STATE, true ), 'failed' ) === 0 && get_post_status( (int) $id ) === 'publish'; }
	public static function again_url( $id ) { return wp_nonce_url( admin_url( 'admin-post.php?action=dbd_email_again&post=' . (int) $id ), 'dbd_email_again_' . (int) $id ); }

	/**
	 * Try again: a failed send starts over now (six retries again if Brevo still refuses), whatever its date. Returns array( 'ok'|'error', notice ).
	 * Nothing changes unless it can go: the email set up, and no send of this post running (Greptile, PR #82).
	 */
	public static function again( $pid ) {
		$pid = (int) $pid;
		if ( ! self::failed( $pid ) ) { return array( 'error', 'That email isn’t a failed send, so there was nothing to try again.' ); }
		if ( ! self::ready() ) { return array( 'error', 'Switch the email on and finish the Brevo setup (a key and a list) first. The email is still waiting for Try again.' ); }
		$lock = 'dbd_email_lock_' . $pid; $held = get_option( $lock, 0 );
		if ( $held && time() - (int) $held < 300 ) { return array( 'error', 'A send of this email is still finishing. Try again in a minute.' ); }
		update_post_meta( $pid, self::M_STATE, 'queued' ); update_post_meta( $pid, self::M_TRIES, 0 ); update_post_meta( $pid, self::M_AGAIN, 1 );
		self::send( $pid );
		$st = (string) get_post_meta( $pid, self::M_STATE, true );
		if ( $st === 'queued' ) {  /* another send took the lock between the check and the send: the background picks it up */
			wp_schedule_single_event( time() + 60, self::HOOK, array( $pid ) );
			return array( 'ok', 'Queued: it sends within a couple of minutes.' );
		}
		if ( $st === 'sent' ) { return array( 'ok', 'The email went to the list.' ); }
		if ( strpos( $st, 'retrying' ) === 0 ) { return array( 'error', 'Brevo still didn’t take it (' . substr( $st, 10 ) . '). It retries every 10 minutes for an hour.' ); }
		return array( 'error', ucfirst( $st ) . '.' );
	}

	public static function again_action() {
		$pid = (int) ( $_GET['post'] ?? 0 );
		check_admin_referer( 'dbd_email_again_' . $pid );
		if ( ! $pid || ! current_user_can( 'edit_post', $pid ) || ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		set_transient( 'dbd_email_test_' . get_current_user_id(), self::again( $pid ), 120 );
		wp_safe_redirect( wp_get_referer() ?: (string) get_edit_post_link( $pid, 'url' ) );
		exit;
	}

	public static function box_html( $p ) {
		$state = (string) get_post_meta( $p->ID, self::M_STATE, true ); $done = ! in_array( $state, array( '', 'queued' ), true );  /* a queued send can still be skipped */
		wp_nonce_field( 'dbd_email_box', 'dbd_email_box' );
		$camp = (int) get_post_meta( $p->ID, self::M_CAMP, true );
		echo '<p>' . ( $state !== '' ? esc_html( ucfirst( $state ) . '.' ) : ( self::ready() ? 'Emails the list when it publishes.' : 'The email is off or not set up, so this post won’t send.' ) ) . ( $camp ? ' Brevo campaign ' . (int) $camp . '.' : '' ) . '</p>';
		if ( ! $done ) { echo '<input type="hidden" name="dbd_email_skip_shown" value="1"><p><label><input type="checkbox" name="dbd_email_skip" value="1" ' . checked( (bool) get_post_meta( $p->ID, self::M_SKIP, true ), true, false ) . '> Don’t email this one</label></p>'; }
		if ( self::failed( $p->ID ) ) { echo '<p><a class="button button-primary" href="' . esc_url( self::again_url( $p->ID ) ) . '">Try again</a></p><p class="description">Sends it to the list now, even though it’s late.</p>'; }
		$u = wp_nonce_url( admin_url( 'admin-post.php?action=dbd_email_test&post=' . (int) $p->ID ), 'dbd_email_test' );
		echo '<p><a class="button" href="' . esc_url( $u ) . '">Send a test to ' . esc_html( self::test_to() ) . '</a></p><p class="description">Save your edits first: the test is built from the saved post.</p>';
	}

	public static function save_box( $id, $p ) {
		if ( ! isset( $_POST['dbd_email_box'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dbd_email_box'] ) ), 'dbd_email_box' ) ) { return; }
		/* WordPress decides the send (transition_post_status) before it saves the box (save_post), so a tick made while clicking Publish
		   arrives after the post is queued: it still counts until the send runs, which checks it again (Greptile, PR #75) */
		$state = (string) get_post_meta( $id, self::M_STATE, true );
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $id ) || ! in_array( $state, array( '', 'queued' ), true ) ) { return; }
		if ( empty( $_POST['dbd_email_skip_shown'] ) ) { return; }  /* only a form that showed the checkbox can change it */
		if ( ! empty( $_POST['dbd_email_skip'] ) ) { update_post_meta( $id, self::M_SKIP, 1 ); } else { delete_post_meta( $id, self::M_SKIP ); }
	}

	/** The settings form's values: a blank key keeps the saved one. */
	public static function sanitize( $v ) {
		$v = is_array( $v ) ? $v : array(); $old = self::opts(); $d = self::defaults();
		$key = trim( sanitize_text_field( (string) ( $v['key'] ?? '' ) ) );
		$out = array(
			'on' => ! empty( $v['on'] ), 'key' => $key !== '' ? $key : $old['key'], 'list' => absint( $v['list'] ?? 0 ), 'tpl' => absint( $v['tpl'] ?? 0 ),
			'from_name' => sanitize_text_field( (string) ( $v['from_name'] ?? '' ) ) ?: $d['from_name'],
			'from_email' => sanitize_email( (string) ( $v['from_email'] ?? '' ) ) ?: $d['from_email'],
			'reply' => sanitize_email( (string) ( $v['reply'] ?? '' ) ) ?: $d['reply'],
			'address' => sanitize_text_field( (string) ( $v['address'] ?? '' ) ) ?: $d['address'],
			'form' => absint( $v['form'] ?? 0 ) ?: $d['form'], 'test' => sanitize_email( (string) ( $v['test'] ?? '' ) ),
		);
		if ( ! empty( $v['forget'] ) ) { $out['key'] = ''; $out['on'] = false; }
		self::forget_reads( array( $old['key'], $out['key'] ) );
		return $out;
	}
}
