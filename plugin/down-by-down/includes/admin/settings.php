<?php
/**
 * Down by Down in the admin (2.11.0, SCOPE §50): six tabs, each its own page in the sidebar under Down by Down.
 *   Overview        what needs a look, then a summary of each area with its main button
 *   Week in review  the weekly post, its weeks, the Tuesday email and its send log
 *   Team news       the fetch status and the sources
 *   Site            photos and logos, addresses, the home description, notes for the theme
 *   Data            the current data and Sync now, the sync log, seasons, share cards, the data source and GitHub push
 *   Growth          visits by source, Google search, titles to tune, landing pages, email (2.25.0, SCOPE §65; assets/growth.js)
 *   Share pack      six things to post each week, with their cards and texts (2.27.0, SCOPE §68; assets/pack.js)
 * Each box that saves is its own form (its own settings group), so saving one tab never touches another's settings.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** tab => array( page slug, label ) */
function dbd_admin_tabs() {
	return array(
		'ov' => array( 'down-by-down', 'Overview' ),
		'wk' => array( 'down-by-down-week', 'Week in review' ),
		'nw' => array( 'down-by-down-news', 'News and videos' ),
		'st' => array( 'down-by-down-site', 'Site' ),
		'dt' => array( 'down-by-down-data', 'Data' ),
		'gr' => array( 'down-by-down-growth', 'Growth' ),
		'sp' => array( 'down-by-down-share', 'Share pack' ),
	);
}
function dbd_admin_url( $tab = 'ov', $args = array(), $hash = '' ) {
	$t = dbd_admin_tabs(); $slug = $t[ $tab ][0] ?? 'down-by-down';
	return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) ) . ( $hash !== '' ? '#' . $hash : '' );
}
function dbd_admin_tab() {
	$page = sanitize_key( (string) ( $_GET['page'] ?? '' ) );
	foreach ( dbd_admin_tabs() as $k => $t ) { if ( $t[0] === $page ) { return $k; } }
	return 'ov';
}
/** A Unix time in the site's time zone. */
function dbd_admin_when( $ts, $fmt = 'M j, g:i A' ) { return $ts ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $ts ), $fmt ) : ''; }

add_action( 'admin_menu', function () {
	// Its own entry in the admin sidebar, just below Comments, with a pennant icon; each tab is also listed under it.
	add_menu_page( 'Down by Down', 'Down by Down', 'manage_options', 'down-by-down', 'dbd_settings_page', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBvbHlnb24gcG9pbnRzPSIyLDIgMTgsMTAgMiwxOCIgZmlsbD0iYmxhY2siLz48cmVjdCB4PSIyIiB5PSIyIiB3aWR0aD0iMyIgaGVpZ2h0PSIxNiIgZmlsbD0iYmxhY2siIG9wYWNpdHk9Ii41NSIvPjwvc3ZnPg==', 26 );
	foreach ( dbd_admin_tabs() as $t ) { add_submenu_page( 'down-by-down', 'Down by Down: ' . $t[1], $t[1], 'manage_options', $t[0], 'dbd_settings_page' ); }
} );

/* the Growth numbers (SCOPE §65): growth.js on the Overview and the Growth tab, with Site Kit's REST root and a nonce */
add_action( 'admin_enqueue_scripts', function () {
	$page = sanitize_key( (string) ( $_GET['page'] ?? '' ) );
	if ( $page !== 'down-by-down' && $page !== 'down-by-down-growth' ) { return; }
	wp_enqueue_script( 'dbd-growth', DBD_URL . 'assets/growth.js', array(), DBD_VERSION, true );
	wp_add_inline_script( 'dbd-growth', 'window.DBD_GROWTH=' . wp_json_encode( DBD_Growth::js_config() ) . ';', 'before' );
} );
/* the Share pack's copy buttons (SCOPE §68) */
add_action( 'admin_enqueue_scripts', function () {
	if ( sanitize_key( (string) ( $_GET['page'] ?? '' ) ) !== 'down-by-down-share' ) { return; }
	wp_enqueue_script( 'dbd-pack', DBD_URL . 'assets/pack.js', array(), DBD_VERSION, true );
} );

add_action( 'admin_init', function () {
	/* Site */
	register_setting( 'dbd', 'dbd_headshots', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
	register_setting( 'dbd', 'dbd_logos', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
	register_setting( 'dbd', DBD_Urls::OPT, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );  /* readable URLs (SCOPE §43) */
	register_setting( 'dbd', DBD_Urls::OPT_ROOT, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );  /* app paths at the root while the app sits on its own page */
	register_setting( 'dbd', DBD_Seo::OPT_HOME_DESC, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ) );  /* the home page's description (2.7.0) */
	/* Data: the source folder saves on its own (2.11.0), so saving the Site tab can't blank it */
	register_setting( 'dbd_data', DBD_Sync::OPT_SOURCE, array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => DBD_DEFAULT_SOURCE ) );
	/* Week in review */
	register_setting( 'dbd_posts', DBD_Post::OPT_ON, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );  /* the weekly post (SCOPE §46) */
	register_setting( 'dbd_posts', DBD_Post::OPT_FORM, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => DBD_Post::FORM ) );
	/* the Tuesday email (SCOPE §48): one array; a blank API key keeps the saved one */
	register_setting( 'dbd_email', DBD_Email::OPT, array( 'type' => 'array', 'sanitize_callback' => array( 'DBD_Email', 'sanitize' ) ) );
	register_setting( 'dbd_pack', DBD_Pack_Mail::OPT_SET, array( 'type' => 'array', 'sanitize_callback' => array( 'DBD_Pack_Mail', 'sanitize' ) ) );  /* the share pack email (SCOPE §69) */
	register_setting( 'dbd_pack_sub', DBD_Pack::OPT_SUB, array( 'type' => 'string', 'default' => DBD_Pack::SUB_DEFAULT, 'sanitize_callback' => array( 'DBD_Pack', 'sanitize_sub' ) ) );  /* Post to r/… (SCOPE §71); its own group, so saving it leaves the email list alone */
	register_setting( 'dbd_pack_sub', DBD_Pack::OPT_X, array( 'type' => 'string', 'default' => DBD_Pack::X_DEFAULT, 'sanitize_callback' => array( 'DBD_Pack', 'sanitize_x' ) ) );  /* Post on X (SCOPE §72), saved with the subreddit */
	register_setting( 'dbd_pack_sub', DBD_Pack::OPT_BSKY, array( 'type' => 'string', 'default' => DBD_Pack::BSKY_DEFAULT, 'sanitize_callback' => array( 'DBD_Pack', 'sanitize_bsky' ) ) );  /* Post on Bluesky (SCOPE §72) */
	register_setting( 'dbd_pack_sub', DBD_Pack::OPT_FB, array( 'type' => 'string', 'default' => DBD_Pack::FB_DEFAULT, 'sanitize_callback' => array( 'DBD_Pack', 'sanitize_fb' ) ) );  /* Share on Facebook (SCOPE §73) */
	register_setting( 'dbd_pack_sub', DBD_Pack::OPT_IG, array( 'type' => 'string', 'default' => DBD_Pack::IG_DEFAULT, 'sanitize_callback' => array( 'DBD_Pack', 'sanitize_ig' ) ) );  /* the Instagram caption (SCOPE §73) */
	/* team news (SCOPE §49): the source groups switched on; an empty save switches every source off */
	/* team videos and highlights (SCOPE §51): on by default */
	register_setting( 'dbd_videos', DBD_Videos::OPT_ON, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );
	register_setting( 'dbd_news', DBD_News::OPT_SRC, array( 'type' => 'array', 'sanitize_callback' => function ( $v ) { return array_values( array_intersect( array_map( 'strval', (array) $v ), array_keys( DBD_News::groups() ) ) ); } ) );
} );

/* ------------------------------------------------------------------ the buttons that do something */

/* The Share pack's settings, all in one form (SCOPE §74): the Tuesday email's list (dbd_pack) and the five accounts (dbd_pack_sub), each with its own sanitizer. */
function dbd_pack_settings_save( $p ) {
	$m = is_array( $p[ DBD_Pack_Mail::OPT_SET ] ?? null ) ? $p[ DBD_Pack_Mail::OPT_SET ] : array();
	update_option( DBD_Pack_Mail::OPT_SET, DBD_Pack_Mail::sanitize( array( 'on' => ! empty( $m['on'] ), 'to' => (string) ( $m['to'] ?? '' ) ) ) );
	foreach ( array( DBD_Pack::OPT_SUB => 'sanitize_sub', DBD_Pack::OPT_X => 'sanitize_x', DBD_Pack::OPT_BSKY => 'sanitize_bsky', DBD_Pack::OPT_FB => 'sanitize_fb', DBD_Pack::OPT_IG => 'sanitize_ig' ) as $o => $fn ) {
		update_option( $o, call_user_func( array( 'DBD_Pack', $fn ), is_scalar( $p[ $o ] ?? '' ) ? (string) ( $p[ $o ] ?? '' ) : '' ) );
	}
	/* Instagram posting (SCOPE §76): the switch, a new token checked against the API, or disconnecting */
	update_option( DBD_Instagram::OPT_SET, DBD_Instagram::sanitize( is_array( $p[ DBD_Instagram::OPT_SET ] ?? null ) ? $p[ DBD_Instagram::OPT_SET ] : array() ) );
	$ierr = '';
	if ( ! empty( $p['dbd_ig_disconnect'] ) ) { DBD_Instagram::disconnect(); }
	elseif ( is_scalar( $p['dbd_ig_token_new'] ?? null ) && trim( (string) $p['dbd_ig_token_new'] ) !== '' ) { $ierr = DBD_Instagram::connect( (string) $p['dbd_ig_token_new'] ); }
	$w = (int) ( $p['week'] ?? 0 );
	return add_query_arg( array_filter( array( 'page' => 'down-by-down-share', 'week' => $w ?: null, 'pack' => $ierr === '' ? 'saved' : 'error', 'pack_err' => $ierr === '' ? null : rawurlencode( 'Settings saved, but ' . lcfirst( $ierr ) ) ) ), admin_url( 'admin.php' ) ) . '#dbd-pack-settings';
}
add_action( 'admin_post_dbd_pack_settings', function () {
	check_admin_referer( 'dbd_pack_settings' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	wp_safe_redirect( dbd_pack_settings_save( wp_unslash( $_POST ) ) ); exit;
} );

add_action( 'admin_post_dbd_news_now', function () {
	check_admin_referer( 'dbd_news_now' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	delete_transient( 'dbd_news_lock' ); delete_transient( 'dbd_videos_lock' );
	DBD_News::run();
	DBD_Videos::run();  /* the videos ride on the same fetch (SCOPE §51) */
	wp_safe_redirect( dbd_admin_url( dbd_admin_back( 'nw' ), array( 'news' => 1 ) ) );
	exit;
} );

add_action( 'admin_post_dbd_clear_cards', function () {
	check_admin_referer( 'dbd_clear_cards' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	$r = DBD_Card::prune( true );
	wp_safe_redirect( dbd_admin_url( 'dt', array( 'cleared' => (int) $r[0] ) ) );
	exit;
} );

add_action( 'admin_post_dbd_write_past', function () {
	check_admin_referer( 'dbd_write_past' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); }  /* each post draws its week's share card */
	$r = DBD_Post::backfill();
	$new = count( array_filter( $r, function ( $x ) { return $x === 'new'; } ) );
	wp_safe_redirect( dbd_admin_url( 'wk', array( 'written' => $new ) ) );
	exit;
} );

add_action( 'admin_post_dbd_sync_now', function () {
	check_admin_referer( 'dbd_sync_now' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	$r = DBD_Sync::sync( 'manual' );
	wp_safe_redirect( dbd_admin_url( dbd_admin_back( 'dt' ), array( 'synced' => $r['ok'] ? 1 : 0 ) ) );
	exit;
} );

add_action( 'admin_post_dbd_new_token', function () {
	check_admin_referer( 'dbd_new_token' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	update_option( DBD_Sync::OPT_TOKEN, wp_generate_password( 40, false, false ), false );
	wp_safe_redirect( dbd_admin_url( 'dt', array( 'token' => 1 ), 'dbd-advanced' ) );
	exit;
} );

/** The tab a button was pressed on (Sync now and Fetch news now sit on the Overview too), or $default. */
function dbd_admin_back( $default ) {
	$b = sanitize_key( (string) ( $_POST['dbd_back'] ?? '' ) );
	return isset( dbd_admin_tabs()[ $b ] ) ? $b : $default;
}
/** A one-button form: an admin-post action with its nonce. */
function dbd_admin_button( $action, $label, $primary = false, $confirm = '', $back = '' ) {
	$o = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm !== '' ? ' onsubmit="return confirm(' . esc_attr( wp_json_encode( $confirm ) ) . ');"' : '' ) . '>'
		. wp_nonce_field( $action, '_wpnonce', true, false ) . '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">'
		. ( $back !== '' ? '<input type="hidden" name="dbd_back" value="' . esc_attr( $back ) . '">' : '' )
		. '<button class="button' . ( $primary ? ' button-primary' : '' ) . '">' . esc_html( $label ) . '</button></form>';
	return $o;
}

/* ------------------------------------------------------------------ what the page knows */

/** The sync log's newest row, the news status and its problem feeds, the email's state: read once per page. */
function dbd_admin_state() {
	static $s = null; if ( $s !== null ) { return $s; }
	$ns = get_option( DBD_News::OPT_STATUS ); $nst = is_array( $ns ) ? (array) $ns['feeds'] : array();
	$s = array(
		'log'   => (array) DBD_Sync::log(),
		'news'  => $ns,
		'feeds' => $nst,
		'nok'   => count( array_filter( $nst, function ( $x ) { return ! empty( $x['ok'] ); } ) ),
		'nprob' => array_filter( $nst, function ( $x ) { return empty( $x['ok'] ) || ! empty( $x['err'] ); } ),
		'eo'    => DBD_Email::opts(),
		'elog'  => (array) get_option( DBD_Email::OPT_LOG, array() ),
		'esub'  => get_option( DBD_Email::OPT_SUB ),
		'pmap'  => DBD_Post::map(),
		'weeks' => array_values( array_filter( DBD_Week::weeks(), function ( $w ) { $f = DBD_Week::file( $w ); return $f && ! empty( $f['complete'] ); } ) ),
	);
	return $s;
}

/** Only what isn't right: array of array( 'bad'|'warn', text, tab ). */
function dbd_admin_attention() {
	$s = dbd_admin_state(); $a = array();
	$last = $s['log'][0] ?? null;
	if ( $last && empty( $last['ok'] ) ) { $a[] = array( 'bad', 'The last data sync failed: ' . ( $last['error'] ?: 'no reason given' ) . '.', 'dt' ); }
	if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) { $a[] = array( 'warn', 'WP-Cron is switched off on this site, so the scheduled pull, the news fetch and the email only run if a real cron calls wp-cron.php.', 'dt' ); }
	$elast = $s['elog'][0] ?? null;
	if ( $elast && strpos( (string) $elast['result'], 'failed' ) === 0 ) { $a[] = array( 'bad', 'The email for "' . $elast['title'] . '" didn’t send: ' . substr( (string) $elast['result'], 8 ) . ( DBD_Email::failed( $elast['post'] ) ? '. Its send log row has Try again.' : '' ), 'wk' ); }
	$eo = $s['eo'];
	if ( ! empty( $eo['on'] ) && ! DBD_Email::ready() ) { $a[] = array( 'warn', 'The email is switched on but needs a Brevo API key and a list.', 'wk' ); }
	if ( DBD_Email::ready() && (int) $eo['tpl'] < 1 ) { $a[] = array( 'warn', 'New signups aren’t passed to Brevo until a confirmation template is picked.', 'wk' ); }
	$es = $s['esub'];
	if ( is_array( $es ) && ! empty( $es['last_err'] ) && (int) $es['last_err'][0] > (int) ( $es['last'] ?? 0 ) ) { $a[] = array( 'warn', 'The last signup didn’t reach Brevo: ' . $es['last_err'][1], 'wk' ); }
	$miss = count( array_filter( $s['weeks'], function ( $w ) use ( $s ) { return ! isset( $s['pmap'][ $w ] ); } ) );
	if ( $miss && DBD_Post::on() ) { $a[] = array( 'warn', $miss . ' finished week' . ( $miss === 1 ? ' has' : 's have' ) . ' no post.', 'wk' ); }
	if ( $s['nprob'] ) { $n = count( $s['nprob'] ); $a[] = array( 'warn', $n . ' news feed' . ( $n === 1 ? '' : 's' ) . ' didn’t answer properly on the last fetch.', 'nw' ); }
	if ( class_exists( 'DBD_Instagram' ) && ( $iw = DBD_Instagram::warning() ) !== '' ) { $a[] = array( 'warn', $iw, 'sp' ); }  /* SCOPE §76 */
	if ( DBD_Videos::on() ) {
		$vs = get_option( DBD_Videos::OPT_STATUS ); $vp = is_array( $vs ) ? array_filter( (array) $vs['feeds'], function ( $x ) { return empty( $x['ok'] ) || ! empty( $x['err'] ); } ) : array();
		if ( $vp ) { $n = count( $vp ); $a[] = array( 'warn', $n . ' YouTube channel' . ( $n === 1 ? '' : 's' ) . ' to check after the last fetch.', 'nw' ); }
		if ( is_array( $vs ) && empty( $vs['pl']['ok'] ) ) { $a[] = array( 'warn', 'Game highlights couldn’t be found: ' . ( $vs['pl']['err'] ?? 'unknown' ) . '.', 'nw' ); }
	}
	return $a;
}

/** This season's finished games with highlights, and all its finished games. */
function dbd_admin_highlights() {
	$have = (array) ( ( (array) get_option( DBD_Videos::OPT_HL, array() ) )[ DBD_Data::season() ] ?? array() ); $n = 0; $f = 0;
	foreach ( DBD_Data::weeks() as $w ) { foreach ( DBD_Data::week_games( $w ) as $g ) { if ( DBD_Router::is_final( $g ) ) { $f++; $n += isset( $have[ $g['id'] ] ) ? 1 : 0; } } }
	return array( $n, $f );
}

/** The week posts next and last: the one scheduled soonest, else the newest published. */
function dbd_admin_next_post() {
	$f = get_posts( array( 'post_type' => 'post', 'post_status' => 'future', 'numberposts' => 1, 'category_name' => DBD_Post::CAT, 'orderby' => 'date', 'order' => 'ASC' ) );
	if ( $f ) { return array( 'Next post', $f[0], 'scheduled ' . get_date_from_gmt( $f[0]->post_date_gmt, 'D M j, g:i A' ) ); }
	$p = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'category_name' => DBD_Post::CAT ) );
	return $p ? array( 'Latest post', $p[0], 'published ' . get_date_from_gmt( $p[0]->post_date_gmt, 'M j' ) ) : null;
}

/** A week post's email, in a few words, for the Weeks table. */
function dbd_admin_email_word( $p ) {
	$st = (string) get_post_meta( $p->ID, DBD_Email::M_STATE, true );
	if ( $st === 'sent' ) {
		foreach ( (array) get_option( DBD_Email::OPT_LOG, array() ) as $r ) { if ( (int) $r['post'] === (int) $p->ID && $r['result'] === 'sent' ) { return 'sent' . ( $r['n'] ? ' to ' . (int) $r['n'] : '' ); } }
		return 'sent';
	}
	if ( $st !== '' ) { return $st; }
	if ( $p->post_status === 'publish' ) { return 'not emailed (published before the email)'; }
	if ( get_post_meta( $p->ID, DBD_Email::M_SKIP, true ) ) { return 'won’t send: you chose not to email it'; }
	if ( DBD_Email::ready() ) { return 'sends when it publishes'; }
	return DBD_Email::get( 'on' ) ? 'won’t send until Brevo is set up (a key and a list)' : 'won’t send while the email is off';
}

/* ------------------------------------------------------------------ the page */

function dbd_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$tab = dbd_admin_tab(); $m = DBD_Data::manifest(); $s = dbd_admin_state();
	$flag = count( $s['nprob'] );
	?>
	<div class="wrap dbd-admin">
		<style>
		.dbd-admin .dbd-lede{margin:2px 0 12px;color:#50575e}
		.dbd-admin .nav-tab-wrapper{margin-bottom:20px}
		.dbd-admin .dbd-flag{display:inline-block;margin-left:6px;padding:0 6px;border-radius:9px;background:#d63638;color:#fff;font-size:11px;line-height:17px;vertical-align:1px}
		.dbd-admin .dbd-box{max-width:980px;background:#fff;border:1px solid #c3c4c7;margin:0 0 20px}
		.dbd-admin .dbd-box>h2{margin:0;padding:10px 16px;border-bottom:1px solid #c3c4c7;font-size:14px;line-height:1.4;display:flex;justify-content:space-between;align-items:baseline;gap:12px;flex-wrap:wrap}
		.dbd-admin .dbd-box>h2 small{font-weight:400;color:#50575e;font-size:13px}
		.dbd-admin .dbd-in{padding:12px 16px}
		.dbd-admin .dbd-in>:first-child{margin-top:0}.dbd-admin .dbd-in>:last-child{margin-bottom:0}
		.dbd-admin .dbd-rows{width:100%;border-collapse:collapse}
		.dbd-admin .dbd-rows td{padding:8px 16px;border-top:1px solid #f0f0f1;vertical-align:top}
		.dbd-admin .dbd-rows tr:first-child td{border-top:0}
		.dbd-admin .dbd-rows td:first-child{width:180px;color:#50575e}
		.dbd-admin .dbd-box .form-table{margin:0}
		.dbd-admin .dbd-box .form-table th{padding-left:16px;width:200px}
		.dbd-admin .dbd-box .form-table td{padding-right:16px}
		.dbd-admin .dbd-box .widefat{border:0;box-shadow:none}
		.dbd-admin .dbd-box .widefat th,.dbd-admin .dbd-box .widefat td{padding-left:16px}
		.dbd-admin .dbd-foot{padding:12px 16px;border-top:1px solid #c3c4c7;background:#f6f7f7;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
		.dbd-admin .dbd-foot form{margin:0}.dbd-admin .dbd-foot .submit{margin:0;padding:0}
		.dbd-admin .dbd-foot .description{color:#646970}
		.dbd-admin .dbd-grid{max-width:980px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;margin:0 0 20px}
		.dbd-admin .dbd-grid .dbd-box{margin:0;display:flex;flex-direction:column}
		.dbd-admin .dbd-grid .dbd-rows{flex:1}
		.dbd-admin .dbd-grid .dbd-rows td:first-child{width:110px}
		.dbd-admin .dbd-dot{display:inline-block;width:8px;height:8px;border-radius:4px;margin-right:6px;background:#a7aaad}
		.dbd-admin .dbd-dot.ok{background:#00a32a}.dbd-admin .dbd-dot.warn{background:#dba617}.dbd-admin .dbd-dot.bad{background:#d63638}
		.dbd-admin .dbd-red{color:#b32d2e}
		.dbd-admin details>summary{cursor:pointer}
		.dbd-admin details.dbd-box>summary{padding:10px 16px;font-weight:600;font-size:14px}
		.dbd-admin details.dbd-box[open]>summary{border-bottom:1px solid #c3c4c7}
		.dbd-admin .dbd-help details{margin:0 0 10px}.dbd-admin .dbd-help details>summary{font-weight:600}
		.dbd-admin .dbd-help ul{list-style:disc;padding-left:20px}
		.dbd-admin .up{color:#007017}.dbd-admin .down{color:#b32d2e}.dbd-admin .flat{color:#646970}
		.dbd-admin .g-t{width:100%;border-collapse:collapse}
		.dbd-admin .g-t th{padding:8px 16px;text-align:left;font-weight:400;color:#50575e;border-bottom:1px solid #f0f0f1;white-space:nowrap}
		.dbd-admin .g-t td{padding:8px 16px;border-top:1px solid #f0f0f1;vertical-align:middle}
		.dbd-admin .g-t tbody tr:first-child td{border-top:0}
		.dbd-admin .g-t .n{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
		.dbd-admin .g-t .w{width:100%}
		.dbd-admin .bar-c{display:block;height:8px;background:#f0f0f1;min-width:80px}.dbd-admin .bar-c i{display:block;height:8px;background:#2271b1}
		.dbd-admin .g-t td.pg{max-width:0;width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.dbd-admin .g-t td.pg a{text-decoration:none}
		.dbd-admin .g-t td.pg small{display:block;color:#646970;font-size:12px;overflow:hidden;text-overflow:ellipsis}
		.dbd-admin .g-wait{color:#646970}
		@media (max-width:782px){.dbd-admin .dbd-grid{grid-template-columns:minmax(0,1fr)}.dbd-admin .dbd-rows td:first-child{width:110px}.dbd-admin .g-t th,.dbd-admin .g-t td{padding-left:10px;padding-right:10px}.dbd-admin .hide-sm{display:none}}
		/* the Share pack (SCOPE §68) */
		/* the Share pack (SCOPE §68–74): one row of places to post per item, texts folded, settings at the bottom */
		.dbd-admin .sp-top{max-width:1100px;display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;margin:0 0 6px}.dbd-admin .sp-top h2{font-size:18px;margin:0}.dbd-admin .sp-top form{margin:0}.dbd-admin .sp-when{color:#50575e}
		.dbd-admin .sp-how{max-width:820px;margin:0 0 6px;color:#3c434a;line-height:1.55}
		.dbd-admin .sp-mail{margin:0 0 18px;color:#50575e}
		.dbd-admin .sp-item{max-width:1100px}
		.dbd-admin .sp-ih{display:flex;align-items:baseline;gap:10px;padding:12px 16px 0}.dbd-admin .sp-ih h2{font-size:15px;margin:0;padding:0;border:0}.dbd-admin .sp-no{font-weight:600;color:#646970}
		.dbd-admin .sp-body{display:grid;grid-template-columns:400px minmax(0,1fr);gap:20px;padding:12px 16px 16px}
		.dbd-admin .sp-card{display:block}.dbd-admin .sp-card img{display:block;width:100%;height:auto;aspect-ratio:1200/630;border:1px solid #dcdcde;background:#f4f1ea}
		.dbd-admin .sp-dl{display:flex;flex-wrap:wrap;gap:6px 14px;margin:8px 0 0}
		.dbd-admin .sp-tk{font-size:15px;line-height:1.5;margin:0 0 14px}
		.dbd-admin .sp-pl{margin:0 0 6px;font-weight:600}
		.dbd-admin .sp-row{display:flex;flex-wrap:wrap;gap:6px}
		.dbd-admin .sp-rd{border-color:#cb2026;color:#cb2026}.dbd-admin .sp-x{border-color:#0f1419;color:#0f1419}.dbd-admin .sp-bs{border-color:#1185fe;color:#1185fe}.dbd-admin .sp-fb{border-color:#0866ff;color:#0866ff}.dbd-admin .sp-ig{border-color:#c13584;color:#c13584}
		.dbd-admin .sp-row .button.ok{border-color:#00a32a;color:#007017}
		.dbd-admin .sp-fits{margin:10px 0 0;color:#646970}
		.dbd-admin .sp-tx{margin-top:12px;border-top:1px solid #f0f0f1;padding-top:10px}.dbd-admin .sp-tx summary{cursor:pointer;color:#2271b1;font-weight:600}
		.dbd-admin .sp-f{margin:10px 0 0}
		.dbd-admin .sp-fh{display:flex;align-items:center;gap:10px;margin-bottom:4px}.dbd-admin .sp-fh b{flex:1}.dbd-admin .sp-fh .cnt{color:#646970;font-size:12px}.dbd-admin .sp-fh .cnt.over{color:#b32d2e;font-weight:600}
		.dbd-admin .sp-f textarea{width:100%;box-sizing:border-box;padding:6px 8px;font-size:13px;line-height:1.5;resize:vertical;field-sizing:content;min-height:3.2em}
		.dbd-admin .sp-cp.ok{border-color:#00a32a;color:#007017}
		.dbd-admin .sp-kl{margin:6px 16px 4px;color:#50575e}
		.dbd-admin .sp-kg{margin:0 16px;padding:12px 0;border-top:1px solid #f0f0f1}.dbd-admin .sp-kl+.sp-kg{border-top:0}.dbd-admin .sp-kg:last-child{padding-bottom:16px}
		.dbd-admin .sp-kgh{display:flex;gap:14px;align-items:baseline;flex-wrap:wrap;margin-bottom:4px}.dbd-admin .sp-kgh b{font-size:14px}
		.dbd-admin .sp-ks{display:grid;grid-template-columns:24px minmax(0,1fr);gap:4px 10px;padding:6px 0}.dbd-admin .sp-ks svg{width:24px;height:24px}.dbd-admin .sp-kt{line-height:1.5}.dbd-admin .sp-kt span{color:#50575e}.dbd-admin .sp-ks .sp-row{grid-column:2}
		.dbd-admin .sp-ks .sp-row .button{min-height:26px;line-height:24px;padding:0 9px;font-size:12px}
		.dbd-admin .sp-set{max-width:1100px;margin-top:24px}.dbd-admin .sp-set>summary{padding:12px 16px;font-size:14px;font-weight:600;cursor:pointer}.dbd-admin .sp-set[open]>summary{border-bottom:1px solid #c3c4c7}
		.dbd-admin .sp-note{margin:12px 16px 0}
		.dbd-admin .sp-sg{display:grid;grid-template-columns:200px minmax(0,1fr);gap:12px 16px;padding:16px;align-items:start}.dbd-admin .sp-sg>label,.dbd-admin .sp-sg>span{color:#50575e;padding-top:5px}.dbd-admin .sp-sg .description{margin:4px 0 0}
		.dbd-admin .sp-sh{grid-column:1/-1;font-weight:600;font-size:14px;margin-top:6px;padding-top:12px;border-top:1px solid #f0f0f1}.dbd-admin .sp-sg>.sp-sh:first-child{margin-top:0;padding-top:0;border-top:0}
		.dbd-admin .sp-last{border-top:1px solid #c3c4c7}
		@media (max-width:1000px){.dbd-admin .sp-body{grid-template-columns:minmax(0,1fr)}.dbd-admin .sp-l{max-width:420px}}
		@media (max-width:782px){.dbd-admin .sp-sg{grid-template-columns:minmax(0,1fr)}.dbd-admin .sp-sg>span:empty{display:none}}
		</style>
		<h1>Down by Down</h1>
		<p class="dbd-lede"><?php echo esc_html( 'Season ' . ( $m['season'] ?? '–' ) . ( isset( $m['week'] ) ? ' · Week ' . $m['week'] : '' ) . ( DBD_Data::updated_text() ? ' · data ' . lcfirst( DBD_Data::updated_text() ) : '' ) . ' · version ' . DBD_VERSION ); ?></p>
		<?php dbd_admin_notices(); ?>
		<nav class="nav-tab-wrapper" aria-label="Down by Down sections">
			<?php foreach ( dbd_admin_tabs() as $k => $t ) : ?>
			<a href="<?php echo esc_url( dbd_admin_url( $k ) ); ?>" class="nav-tab<?php echo $k === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $k === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $t[1] ); ?><?php if ( $k === 'nw' && $flag ) : ?><span class="dbd-flag" title="<?php echo esc_attr( $flag . ' feeds to check' ); ?>"><?php echo (int) $flag; ?></span><?php endif; ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
		switch ( $tab ) {
			case 'wk': dbd_tab_week(); break;
			case 'nw': dbd_tab_news(); break;
			case 'st': dbd_tab_site(); break;
			case 'dt': dbd_tab_data(); break;
			case 'gr': dbd_tab_growth(); break;
			case 'sp': dbd_tab_share(); break;
			default:   dbd_tab_overview();
		}
		?>
	</div>
	<?php
}

function dbd_admin_notices() {
	$n = array();
	if ( isset( $_GET['settings-updated'] ) ) { $n[] = array( 'success', 'Saved.' ); }
	if ( isset( $_GET['synced'] ) ) { $n[] = $_GET['synced'] ? array( 'success', 'Synced.' ) : array( 'error', 'The sync failed. The sync log on the Data tab says why.' ); }
	if ( isset( $_GET['cleared'] ) ) { $n[] = array( 'success', (int) $_GET['cleared'] . ' cached cards cleared. They are drawn again the next time a page is shared.' ); }
	if ( isset( $_GET['written'] ) ) { $wn = (int) $_GET['written']; $n[] = array( 'success', $wn ? $wn . ' weekly post' . ( $wn === 1 ? '' : 's' ) . ' written.' : 'No new posts: every finished week already has one, or was deleted.' ); }
	if ( isset( $_GET['news'] ) ) { $ns = get_option( DBD_News::OPT_STATUS ); $n[] = array( 'success', is_array( $ns ) ? 'News fetched in ' . $ns['secs'] . ' seconds.' : 'News fetched.' ); }
	if ( isset( $_GET['board'] ) ) { $bw = (string) $_GET['board']; $n[] = $bw === 'tested' ? array( 'success', 'Board email test sent to ' . DBD_Email::test_to() . '.' ) : ( $bw === 'again' ? array( 'success', 'The board email is going again.' ) : array( 'error', 'The board email test didn’t send: ' . sanitize_text_field( rawurldecode( (string) ( $_GET['board_err'] ?? '' ) ) ) ) ); }
	/* the Share pack's own notices show inside its settings box (SCOPE §74): dbd_pack_notices() */
	if ( isset( $_GET['token'] ) ) { $n[] = array( 'warning', 'New token made. Update the DBD_SYNC_TOKEN secret in the GitHub repo or the nightly push will stop working.' ); }
	foreach ( $n as $x ) { echo '<div class="notice notice-' . esc_attr( $x[0] ) . ' is-dismissible"><p>' . esc_html( $x[1] ) . '</p></div>'; }
}

/* ------------------------------------------------------------------ Overview */

function dbd_tab_overview() {
	$s = dbd_admin_state(); $m = DBD_Data::manifest(); $eo = $s['eo'];
	$att = dbd_admin_attention(); $tabs = dbd_admin_tabs();
	if ( $att ) : ?>
	<div class="dbd-box"><h2>Needs a look</h2><table class="dbd-rows"><tbody>
		<?php foreach ( $att as $x ) : ?>
		<tr><td><span class="dbd-dot <?php echo esc_attr( $x[0] ); ?>"></span><?php echo esc_html( $tabs[ $x[2] ][1] ); ?></td><td><?php echo esc_html( $x[1] ); ?> <a href="<?php echo esc_url( dbd_admin_url( $x[2] ) ); ?>">Open <?php echo esc_html( $tabs[ $x[2] ][1] ); ?></a></td></tr>
		<?php endforeach; ?>
	</tbody></table></div>
	<?php endif; ?>
	<div class="dbd-grid">
		<div class="dbd-box"><h2>Week in review</h2><table class="dbd-rows"><tbody>
			<?php $np = dbd_admin_next_post(); ?>
			<tr><td><?php echo esc_html( $np ? $np[0] : 'Posts' ); ?></td><td><?php echo $np ? '<a href="' . esc_url( (string) get_edit_post_link( $np[1]->ID ) ) . '">' . esc_html( get_the_title( $np[1] ) ) . '</a>, ' . esc_html( $np[2] ) : ( DBD_Post::on() ? 'None yet.' : 'Switched off.' ); ?></td></tr>
			<tr><td>Email</td><td><?php echo DBD_Email::ready() ? '<span class="dbd-dot ok"></span>On' : ( ! empty( $eo['on'] ) ? '<span class="dbd-dot warn"></span>On, but not set up' : '<span class="dbd-dot"></span>Off' ); ?></td></tr>
			<?php $el = $s['elog'][0] ?? null; ?>
			<tr><td>Last email</td><td><?php echo $el ? esc_html( $el['title'] . ', ' . dbd_admin_when( $el['at'], 'M j' ) . ': ' . $el['result'] . ( $el['n'] ? ' to ' . (int) $el['n'] : '' ) ) : 'None yet.'; ?></td></tr>
			<tr><td>Signups</td><td><?php $es = $s['esub']; echo esc_html( is_array( $es ) && ! empty( $es['ok'] ) ? (int) $es['ok'] . ' confirmation email' . ( (int) $es['ok'] === 1 ? '' : 's' ) . ' sent, the last ' . dbd_admin_when( $es['last'] ?? 0 ) : 'None yet.' ); ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><a class="button" href="<?php echo esc_url( dbd_admin_url( 'wk' ) ); ?>">Open Week in review</a></div></div>

		<div class="dbd-box"><h2>Data</h2><table class="dbd-rows"><tbody>
			<tr><td>Built</td><td><?php echo esc_html( DBD_Data::updated_text() ?: 'never' ); ?></td></tr>
			<tr><td>Play-by-play</td><td><?php echo esc_html( isset( $m['through_week'] ) ? 'Through Week ' . $m['through_week'] : '–' ); ?></td></tr>
			<tr><td>Next pull</td><td><?php $nx = wp_next_scheduled( DBD_Sync::HOOK ); echo esc_html( $nx ? dbd_admin_when( $nx ) : 'not scheduled' ); ?></td></tr>
			<?php $lg = $s['log'][0] ?? null; ?>
			<tr><td>Last sync</td><td><?php echo $lg ? ( $lg['ok'] ? '<span class="dbd-dot ok"></span>' . esc_html( 'ok, ' . ( $lg['changed'] ? count( $lg['changed'] ) . ' files changed' : 'nothing new' ) ) : '<span class="dbd-dot bad"></span>' . esc_html( $lg['error'] ?: 'failed' ) ) : 'No syncs yet.'; ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo dbd_admin_button( 'dbd_sync_now', 'Sync now', true, '', 'ov' ); ?><a class="button" href="<?php echo esc_url( dbd_admin_url( 'dt' ) ); ?>">Open Data</a></div></div>

		<div class="dbd-box"><h2>News and videos</h2><table class="dbd-rows"><tbody>
			<tr><td>Last fetch</td><td><?php echo is_array( $s['news'] ) ? esc_html( dbd_admin_when( $s['news']['at'] ) . ': ' . $s['nok'] . ' of ' . count( $s['feeds'] ) . ' feeds' ) : 'not yet'; ?></td></tr>
			<tr><td>Next fetch</td><td><?php $nn = wp_next_scheduled( DBD_News::HOOK ); echo esc_html( $nn ? dbd_admin_when( $nn ) : 'not scheduled' ); ?></td></tr>
			<tr><td>Videos</td><td><?php $hs = dbd_admin_highlights(); echo esc_html( DBD_Videos::on() ? ( get_option( DBD_Videos::OPT_STATUS ) ? $hs[0] . ' of ' . $hs[1] . ' finished games have highlights' : 'the first fetch hasn’t run yet' ) : 'switched off' ); ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo dbd_admin_button( 'dbd_news_now', 'Fetch news and videos now', false, '', 'ov' ); ?><a class="button" href="<?php echo esc_url( dbd_admin_url( 'nw' ) ); ?>">Open News and videos</a></div></div>

		<div class="dbd-box"><h2>Site</h2><table class="dbd-rows"><tbody>
			<?php $aid = DBD_Urls::page_id(); ?>
			<tr><td>App page</td><td><?php echo $aid ? '<a href="' . esc_url( get_permalink( $aid ) ) . '">' . esc_html( preg_replace( '#^https?://#', '', (string) get_permalink( $aid ) ) ) . '</a>' . ( DBD_Urls::is_front() ? ' (the front page)' : '' ) : 'No page holds <code>[down_by_down]</code> yet.'; ?></td></tr>
			<tr><td>Photos, logos</td><td><?php echo esc_html( 'Headshots ' . ( DBD_Data::headshots() ? 'on' : 'off' ) . ' · logos ' . ( DBD_Data::logos() ? 'on' : 'off' ) ); ?></td></tr>
			<tr><td>Addresses</td><td><?php echo DBD_Urls::on() ? 'Readable: <code>/team/min/</code>' : 'Query strings: <code>?team=MIN</code>'; ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><a class="button" href="<?php echo esc_url( dbd_admin_url( 'st' ) ); ?>">Open Site</a></div></div>

		<div class="dbd-box"><h2>Growth <small>last 28 days</small></h2><table class="dbd-rows"><tbody>
			<tr><td>Visits</td><td data-g="ov-visits"><span class="g-wait">Asking Site Kit…</span></td></tr>
			<tr><td>Search clicks</td><td data-g="ov-clicks"><span class="g-wait">Asking Site Kit…</span></td></tr>
			<tr><td>Subscribers</td><td><?php echo dbd_growth_subscribers(); ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><a class="button" href="<?php echo esc_url( dbd_admin_url( 'gr' ) ); ?>">Open Growth</a></div></div>
	</div>
	<?php
}

/* ------------------------------------------------------------------ Growth (2.25.0, SCOPE §65) */

/** "142 +37 in 28 days" from Brevo, or why not. */
function dbd_growth_subscribers() {
	list( $n, $err ) = DBD_Growth::list_size();
	if ( $n === null ) { return '<span class="g-wait">' . esc_html( $err ) . '.</span>' . ( DBD_Growth::email_on() ? '' : ' <a href="' . esc_url( dbd_admin_url( 'wk' ) ) . '">Set it up on Week in review</a>' ); }
	$c = DBD_Growth::size_change( $n );
	if ( ! $c ) { return esc_html( number_format( $n ) ) . ' <span class="flat">(the change shows from tomorrow)</span>'; }
	$cls = $c[0] > 0 ? 'up' : ( $c[0] < 0 ? 'down' : 'flat' );
	$since = $c[1] <= gmdate( 'Y-m-d', strtotime( wp_date( 'Y-m-d' ) . ' -' . DBD_Growth::DAYS . ' days' ) ) ? 'in 28 days' : 'since ' . wp_date( 'M j', strtotime( $c[1] . ' 12:00' ) );
	return esc_html( number_format( $n ) ) . ' <span class="' . $cls . '">' . ( $c[0] > 0 ? '+' : ( $c[0] < 0 ? '−' : '±' ) ) . abs( (int) $c[0] ) . ' ' . esc_html( $since ) . '</span>';
}

function dbd_tab_growth() {
	$L = DBD_Growth::links();
	$a = function ( $k, $t ) use ( $L ) { return '<a class="button" href="' . esc_url( $L[ $k ] ) . '"' . ( $k === 'sitekit' ? '' : ' target="_blank" rel="noopener"' ) . '>' . esc_html( $t ) . '</a>'; };
	$wait = '<span class="g-wait">Asking Site Kit…</span>';
	$le = DBD_Growth::last_email();
	?>
	<p class="dbd-lede" style="max-width:980px">The last 28 days against the 28 before. Analytics and Search Console numbers come from Site Kit, read in your browser as you; email numbers come from Brevo. Shares and signups are counted from version 2.25.0.</p>
	<div class="dbd-box"><h2>Visits by source <small data-g="range"></small></h2>
		<div data-g="sources"><p class="dbd-in"><?php echo $wait; ?></p></div>
		<div class="dbd-foot"><?php echo $a( 'analytics', 'Open in Analytics' ) . $a( 'sitekit', 'Open Site Kit' ); ?></div></div>

	<div class="dbd-grid">
		<div class="dbd-box"><h2>Google search</h2><table class="dbd-rows"><tbody data-g="search">
			<tr><td>Clicks</td><td><?php echo $wait; ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo $a( 'search', 'Open in Search Console' ); ?></div></div>

		<div class="dbd-box"><h2>Email</h2><table class="dbd-rows"><tbody>
			<tr><td>Subscribers</td><td><?php echo dbd_growth_subscribers(); ?></td></tr>
			<tr><td>Signups</td><td data-g="signups"><?php echo $wait; ?></td></tr>
			<tr><td>Last email</td><td><?php echo $le ? esc_html( DBD_Growth::email_line( $le[0], $le[1] ) ) . ( $le[2] !== '' ? ' <span class="dbd-red">(' . esc_html( $le[2] ) . ')</span>' : '' ) : 'None sent yet.'; ?></td></tr>
			<tr><td>Shares</td><td data-g="shares"><?php echo $wait; ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo $a( 'brevo', 'Open in Brevo' ); ?></div></div>
	</div>

	<div class="dbd-box"><h2>Titles to tune <small>seen often in Google, rarely clicked</small></h2>
		<table class="g-t"><thead><tr><th>Page and its title</th><th class="n">Seen</th><th class="n">Clicks</th><th class="n">Rate</th></tr></thead><tbody data-g="tune">
			<tr><td colspan="4"><?php echo $wait; ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><button type="button" class="button" data-g="copy" disabled>Copy the list for Claude</button><span class="description">Pages Google showed at least 100 times, with a click rate under the site's.</span></div></div>

	<div class="dbd-box"><h2>Top landing pages <small>where visits start</small></h2>
		<table class="g-t"><thead><tr><th>Page</th><th class="n">Visits</th><th class="n">Change</th></tr></thead><tbody data-g="landing">
			<tr><td colspan="3"><?php echo $wait; ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo $a( 'analytics', 'Open in Analytics' ) . $a( 'sitekit', 'Open Site Kit' ); ?></div></div>
	<?php
}

/* ------------------------------------------------------------------ Share pack (2.27.0, SCOPE §68; laid out simply in 2.32.0, SCOPE §74) */

/** The email's state in one line for the top of the tab (SCOPE §74): [ dot class, text, Try-again job key or '' ]. */
function dbd_pack_mail_line() {
	if ( (string) DBD_Email::get( 'key' ) === '' ) { return array( 'bad', 'The Tuesday email isn’t set up: Brevo needs its API key on Week in review.', '' ); }
	$ps = DBD_Pack_Mail::settings();
	if ( ! $ps['on'] ) { return array( 'bad', 'The Tuesday email is switched off.', '' ); }
	if ( ! $ps['to'] ) { return array( 'bad', 'The Tuesday email has no one to send to.', '' ); }
	$st = DBD_Pack_Mail::status(); $l = $st ? $st[ count( $st ) - 1 ] : null;
	if ( ! $l ) { return array( 'ok', 'Each new pack is emailed on its Tuesday at 7:00 AM to ' . count( $ps['to'] ) . ( count( $ps['to'] ) === 1 ? ' person.' : ' people.' ), '' ); }
	return array( $l[0], $l[1], $l[2] );
}

/** Every setting on the tab in one box at the bottom, one Save (SCOPE §74): the Tuesday email (SCOPE §69) and the five accounts (§71–73). */
function dbd_pack_settings_box( $n ) {
	$ps = DBD_Pack_Mail::settings(); $key = (string) DBD_Email::get( 'key' ) !== '';
	$open = isset( $_GET['pack'] ) || isset( $_GET['settings-updated'] ) || ! $n;
	$fld = function ( $id, $name, $value, $pre = '', $w = 240, $note = '' ) {
		return '<div>' . ( $pre !== '' ? esc_html( $pre ) . ' ' : '' ) . '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" maxlength="300" style="width:' . (int) $w . 'px;max-width:100%">'
			. ( $note !== '' ? '<p class="description">' . $note . '</p>' : '' ) . '</div>';
	};
	?>
	<details class="dbd-box sp-set" id="dbd-pack-settings"<?php echo $open ? ' open' : ''; ?>><summary>Settings: the Tuesday email, your accounts and Instagram posting</summary>
		<?php dbd_pack_notices(); ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dbd_pack_settings"><input type="hidden" name="week" value="<?php echo (int) $n; ?>"><?php wp_nonce_field( 'dbd_pack_settings' ); ?>
		<div class="sp-sg">
			<div class="sp-sh">The Tuesday email</div>
			<span>Send it</span><div><label><input type="checkbox" name="<?php echo esc_attr( DBD_Pack_Mail::OPT_SET ); ?>[on]" value="1"<?php echo $ps['on'] ? ' checked' : ''; ?>> Email each week’s pack, Tuesday 7:00 AM Eastern</label>
				<?php if ( ! $key ) { echo '<p class="description"><span class="dbd-dot bad"></span>Brevo isn’t set up. <a href="' . esc_url( dbd_admin_url( 'wk' ) ) . '">Add the API key on Week in review</a>.</p>'; } ?></div>
			<label for="dbd-pack-to">Send to</label><div><textarea id="dbd-pack-to" name="<?php echo esc_attr( DBD_Pack_Mail::OPT_SET ); ?>[to]" rows="3" style="width:420px;max-width:100%"><?php echo esc_textarea( implode( "\n", $ps['to'] ) ); ?></textarea>
				<p class="description">One address per line, up to <?php echo (int) DBD_Pack_Mail::MAX_TO; ?>. Each person gets their own copy.</p></div>
			<div class="sp-sh">Your accounts</div>
			<label for="dbd-pack-sub">Reddit</label><?php echo $fld( 'dbd-pack-sub', DBD_Pack::OPT_SUB, DBD_Pack::our_sub(), 'r/' ); ?>
			<label for="dbd-pack-x">X</label><?php echo $fld( 'dbd-pack-x', DBD_Pack::OPT_X, DBD_Pack::our_x(), '@', 240, 'Also named in every page’s share tags.' ); ?>
			<label for="dbd-pack-bsky">Bluesky</label><?php echo $fld( 'dbd-pack-bsky', DBD_Pack::OPT_BSKY, DBD_Pack::our_bsky(), '', 260 ); ?>
			<label for="dbd-pack-fb">Facebook group</label><?php echo $fld( 'dbd-pack-fb', DBD_Pack::OPT_FB, DBD_Pack::our_fb(), '', 420, DBD_Pack::our_fb() !== '' ? '<a href="' . esc_url( DBD_Pack::our_fb() ) . '" target="_blank" rel="noopener">Open the group</a>' : '' ); ?>
			<label for="dbd-pack-ig">Instagram</label><?php echo $fld( 'dbd-pack-ig', DBD_Pack::OPT_IG, DBD_Pack::our_ig(), '@' ); ?>
			<span></span><p class="description" style="margin:0">Clear an account to hide its button.</p>
			<div class="sp-sh">Instagram posting</div>
			<span>Post it</span><div><label><input type="checkbox" name="<?php echo esc_attr( DBD_Instagram::OPT_SET ); ?>[on]" value="1"<?php echo DBD_Instagram::settings()['on'] ? ' checked' : ''; ?>> Post the carousel on Tuesday, then one item a day Wednesday to Sunday, 9:00 AM Eastern</label></div>
			<label for="dbd-ig-token">Access token</label><div><input type="password" id="dbd-ig-token" name="dbd_ig_token_new" value="" autocomplete="off" placeholder="<?php echo esc_attr( DBD_Instagram::token() ? 'Paste a new token to replace it' : 'Paste the token from your Meta app' ); ?>" style="width:420px;max-width:100%">
				<?php $it = DBD_Instagram::token();
				if ( $it ) {
					echo '<p class="description">' . ( DBD_Instagram::connected() ? '<span class="dbd-dot ok"></span>Connected as @' . esc_html( $it['username'] !== '' ? $it['username'] : $it['user_id'] ) . '. Good until ' . esc_html( wp_date( 'M j, Y', (int) $it['expires'] ) ) . '; renewed every week.' : '<span class="dbd-dot bad"></span>The token ran out on ' . esc_html( wp_date( 'M j', (int) $it['expires'] ) ) . '. Paste a new one.' )
						. ( ! empty( $it['err'] ) ? ' <br><span class="dbd-dot warn"></span>The last renewal failed: ' . esc_html( $it['err'] ) : '' ) . '</p>';
					echo '<label class="description" style="display:block;margin-top:4px"><input type="checkbox" name="dbd_ig_disconnect" value="1"> Disconnect (forget the token)</label>';
				} else {
					echo '<p class="description">In your Meta app (Instagram, API setup with Instagram login), add @' . esc_html( DBD_Pack::our_ig() !== '' ? DBD_Pack::our_ig() : 'your account' ) . ' as a tester, then Generate token and paste it here. It’s checked when you save, and never shown again.</p>';
				} ?></div>
		</div>
		<div class="dbd-foot"><button class="button button-primary">Save settings</button></div>
		</form>
		<?php if ( $key ) : $st = DBD_Pack_Mail::status(); ?>
		<div class="sp-sg sp-last"><div class="sp-sh">Last sends</div><span></span><div><?php
			if ( ! $st ) { echo 'None yet.'; }
			foreach ( $st as $i => $l ) { echo ( $i ? '<br>' : '' ) . '<span class="dbd-dot ' . esc_attr( $l[0] ) . '"></span>' . esc_html( $l[1] ) . ( $l[2] !== '' ? ' <a class="button button-small" href="' . esc_url( DBD_Pack_Mail::again_url( $l[2] ) ) . '">Try again</a>' : '' ); }
			if ( $ps['to'] && $n ) { ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px"><input type="hidden" name="action" value="dbd_pack_now"><input type="hidden" name="week" value="<?php echo (int) $n; ?>"><?php wp_nonce_field( 'dbd_pack_now' ); ?>
				<button class="button"><?php echo esc_html( 'Send the Week ' . $n . ' pack now' ); ?></button> <span class="description"><?php echo esc_html( 'To ' . count( $ps['to'] ) . ( count( $ps['to'] ) === 1 ? ' person' : ' people' ) . ', right away. Save any change to the list first.' ); ?></span></form>
			<?php } ?></div></div>
		<?php endif; ?>
		<?php if ( $n && DBD_Instagram::connected() ) : ?>
		<div class="sp-sg sp-last"><div class="sp-sh">Instagram this week</div><span></span><div><?php
			foreach ( DBD_Instagram::week_rows( $n ) as $r ) { echo '<span class="dbd-dot ' . esc_attr( $r[0] ) . '"></span>' . esc_html( $r[1] ) . ( $r[2] !== '' ? ' <a href="' . esc_url( $r[2] ) . '" target="_blank" rel="noopener">View</a>' : '' ) . ( $r[3] !== '' ? ' <a class="button button-small" href="' . esc_url( DBD_Instagram::again_url( $r[3] ) ) . '">Try again</a>' : '' ) . '<br>'; }
			if ( DBD_Instagram::on() && DBD_Instagram::next_key() !== '' ) { ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:10px"><input type="hidden" name="action" value="dbd_ig_now"><?php wp_nonce_field( 'dbd_ig_now' ); ?>
				<button class="button">Post the next one now</button> <span class="description">Posts the first one not out yet, right away.</span></form>
			<?php } ?></div></div>
		<?php endif; ?>
	</details>
	<?php
}

/** The tab's own notices, inside the settings box (SCOPE §74): saved, sent, going again, or what went wrong. */
function dbd_pack_notices() {
	if ( ! isset( $_GET['pack'] ) ) { return; }
	$pk = (string) $_GET['pack']; $pn = (int) ( $_GET['pack_n'] ?? 0 );
	if ( $pk === 'igposted' ) { $x = array( 'success', 'Posted to Instagram.' ); } elseif ( $pk === 'igagain' ) { $x = array( 'success', 'Instagram is trying that post again.' ); } else {
	$x = $pk === 'saved' ? array( 'success', 'Settings saved.' ) : ( $pk === 'sent' ? array( 'success', 'The pack went to ' . $pn . ( $pn === 1 ? ' person.' : ' people.' ) ) : ( $pk === 'again' ? array( 'success', 'The pack email is going again.' ) : array( 'error', 'The pack email didn’t send: ' . sanitize_text_field( wp_unslash( rawurldecode( (string) ( $_GET['pack_err'] ?? '' ) ) ) ) ) ) );
	}
	echo '<div class="notice notice-' . esc_attr( $x[0] ) . ' inline sp-note"><p>' . esc_html( $x[1] ) . '</p></div>';
}

/** One place to post (SCOPE §74): the site's name as the label, its own color, and the full phrase for screen readers. */
function dbd_pack_btn( $cls, $label, $aria, $href = '', $data = '' ) {
	if ( $href === '' && $data === '' ) { return ''; }
	$a = ' class="button ' . esc_attr( $cls ) . '" aria-label="' . esc_attr( $aria ) . '"' . ( $data !== '' ? ' data-t="' . esc_attr( $data ) . '"' : '' );
	return $href !== '' ? '<a' . $a . ' href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>' : '<button type="button"' . $a . '>' . esc_html( $label ) . '</button>';
}

/** The row of places to post, always in this order: r/ours, r/the team's (keys), X, Bluesky, Facebook, Instagram, then Square (keys). */
function dbd_pack_row( $it, $link, $team_sub = '', $square = false ) {
	$o = ''; $sub = DBD_Pack::our_sub(); $t = DBD_Pack::social( $it );
	if ( $sub !== '' ) { $o .= dbd_pack_btn( 'sp-rd', 'r/' . $sub, 'Post to r/' . $sub, DBD_Pack::reddit_url( $it['title'], $link ) ); }
	if ( $team_sub !== '' && ( $ts = DBD_Pack::sanitize_sub( $team_sub ) ) !== '' ) { $o .= dbd_pack_btn( 'sp-rd', 'r/' . $ts, 'Post to r/' . $ts, DBD_Pack::reddit_url( $it['title'], $link, $ts ) ); }
	$o .= dbd_pack_btn( 'sp-x', 'X', 'Post on X', DBD_Pack::x_url( $t['x'] ) );
	$o .= dbd_pack_btn( 'sp-bs', 'Bluesky', 'Post on Bluesky', DBD_Pack::bsky_url( $t['bsky'] ) );
	if ( ( $fu = DBD_Pack::fb_url( $it ) ) !== '' ) { $o .= dbd_pack_btn( 'sp-fb', 'Facebook', 'Share on Facebook: copies the text, then opens the share box', $fu, DBD_Pack::fb_text( $it ) ); }  /* pack.js copies data-t */
	if ( ( $cap = DBD_Pack::ig_caption( $it ) ) !== '' ) { $o .= dbd_pack_btn( 'sp-cp sp-ig', 'Instagram', 'Copy for Instagram: the caption', '', $cap );
		if ( $square && ! empty( $it['sq'] ) ) { $o .= '<a class="button sp-ig" href="' . esc_url( DBD_Card::url( $it['sq'] ) ) . '" download="' . esc_attr( $it['sqfile'] ?? 'dbd-square.png' ) . '" aria-label="Download square for Instagram">Square</a>'; } }
	return '<div class="sp-row">' . $o . '</div>';
}

/** One text with its Copy button; X and Bluesky shows its length against 280. */
function dbd_pack_field( $lab, $text, $count = false, $rows = 2 ) {
	$n = $count ? DBD_Pack::xlen( $text ) : 0;
	return '<div class="sp-f"><div class="sp-fh"><b>' . esc_html( $lab ) . '</b>' . ( $count ? '<span class="cnt' . ( $n > DBD_Pack::X_MAX ? ' over' : '' ) . '">' . (int) $n . ' of ' . DBD_Pack::X_MAX . '</span>' : '' )
		. '<button type="button" class="button button-small sp-cp">Copy</button></div><textarea readonly rows="' . (int) $rows . '" class="large-text">' . esc_textarea( $text ) . '</textarea></div>';
}

/** The four texts for an item or a keys side, folded away (SCOPE §74). $pre names the side on a keys game. */
function dbd_pack_texts( $it, $pre = '' ) {
	$o = dbd_pack_field( $pre . 'Reddit title', $it['title'] ) . dbd_pack_field( $pre . 'Reddit first comment, with the link', $it['comment'] ) . dbd_pack_field( $pre . 'X and Bluesky', $it['post'], true );
	if ( ( $cap = DBD_Pack::ig_caption( $it ) ) !== '' ) { $o .= dbd_pack_field( $pre . 'Instagram caption', $cap, false, 5 ); }
	return $o;
}

function dbd_tab_share() {
	$weeks = DBD_Pack::weeks();
	if ( ! $weeks ) { echo '<div class="dbd-box"><h2>Share pack</h2><p class="dbd-in" style="margin:0">The pack starts after Week 1’s games are all in.</p></div>'; dbd_pack_settings_box( 0 ); return; }  /* the settings show before Week 1 too (Greptile, PR #127, #130) */
	$want = (int) ( $_GET['week'] ?? 0 ); $n = in_array( $want, $weeks, true ) ? $want : $weeks[0];
	$items = DBD_Pack::items( $n ); $keys = DBD_Pack::keys( $n ); $tue = DBD_Post::tuesday( $n ); $ml = dbd_pack_mail_line();
	?>
	<div class="sp-top">
		<h2><?php echo esc_html( 'Week ' . $n . ' pack' ); ?></h2>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="down-by-down-share">
			<select name="week" aria-label="Week" onchange="this.form.submit()"><?php foreach ( $weeks as $w ) { echo '<option value="' . (int) $w . '"' . ( $w === $n ? ' selected' : '' ) . '>Week ' . (int) $w . '</option>'; } ?></select>
			<noscript><button class="button">Show</button></noscript></form>
		<?php if ( $tue ) { echo '<span class="sp-when">ready ' . esc_html( wp_date( 'l, M j', $tue ) ) . '</span>'; } ?>
	</div>
	<p class="sp-how">Click where you want to post. Reddit, X and Bluesky open with the post filled in. Facebook copies the text and opens its share box: choose Share to a group, then paste. Instagram copies the caption: post it with the square.</p>
	<p class="sp-mail"><span class="dbd-dot <?php echo esc_attr( $ml[0] ); ?>"></span><?php echo esc_html( $ml[1] ); ?><?php if ( $ml[2] !== '' ) { echo ' <a class="button button-small" href="' . esc_url( DBD_Pack_Mail::again_url( $ml[2] ) ) . '">Try again</a>'; } ?> <a href="#dbd-pack-settings" class="sp-open">Change the list</a>
		<?php $il = DBD_Instagram::line( $n ); if ( $il ) { echo '<br><span class="dbd-dot ' . esc_attr( $il[0] ) . '"></span>' . esc_html( $il[1] ) . ( $il[2] !== '' ? ' <a href="' . esc_url( $il[2] ) . '" target="_blank" rel="noopener">View on Instagram</a>' : '' ) . ( $il[3] !== '' ? ' <a class="button button-small" href="' . esc_url( DBD_Instagram::again_url( $il[3] ) ) . '">Try again</a>' : '' ) . ( ! DBD_Instagram::connected() ? ' <a href="#dbd-pack-settings" class="sp-open">Connect it</a>' : '' ); } ?></p>
	<?php
	foreach ( $items as $it ) {
		$i = DBD_Pack::NUM[ $it['k'] ] ?? 0; $img = DBD_Card::url( $it['card'] );
		$also = array_values( array_filter( (array) $it['fits'] ) );
		echo '<section class="dbd-box sp-item" data-k="' . esc_attr( $it['k'] ) . '"><div class="sp-ih"><span class="sp-no">' . (int) $i . '</span><h2>' . esc_html( $it['h'] ) . '</h2></div><div class="sp-body">'
			. '<div class="sp-l"><a class="sp-card" href="' . esc_url( $it['link'] ) . '" target="_blank" rel="noopener" aria-label="Open the page"><img src="' . esc_url( $img ) . '" width="1200" height="630" alt="" loading="lazy"></a>'
			. '<p class="sp-dl"><a href="' . esc_url( $img ) . '" download="' . esc_attr( $it['file'] ) . '">Download image</a>'
			. ( DBD_Pack::our_ig() !== '' && ! empty( $it['sq'] ) ? '<a href="' . esc_url( DBD_Card::url( $it['sq'] ) ) . '" download="' . esc_attr( $it['sqfile'] ) . '">Download square for Instagram</a>' : '' ) . '</p></div>'
			. '<div class="sp-r"><p class="sp-tk">' . esc_html( $it['title'] ) . '</p><p class="sp-pl">Post it</p>' . dbd_pack_row( $it, DBD_Pack::share_link( $it ) )
			. ( $also ? '<p class="sp-fits">Also fits ' . esc_html( implode( ', ', $also ) ) . ': post there by hand with the texts below.</p>' : '' )
			. '<details class="sp-tx"><summary>Texts to copy</summary>' . dbd_pack_texts( $it ) . '</details></div></div></section>';
	}
	if ( $keys ) {
		$nk = function ( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; };
		echo '<section class="dbd-box sp-item" data-k="keys"><div class="sp-ih"><span class="sp-no">' . (int) DBD_Pack::NUM['keys'] . '</span><h2>' . esc_html( 'Keys to the game, Week ' . ( $n + 1 ) ) . '</h2></div>'
			. '<p class="sp-kl">' . esc_html( 'Each team’s biggest edge in its next game, for its own fans. ' . count( $keys ) . ( count( $keys ) === 1 ? ' game' : ' games' ) . ' this week.' ) . '</p>';
		foreach ( $keys as $kg ) {
			$g = $kg['g']; $img = DBD_Card::url( $kg['card'] ); $tx = '';
			echo '<div class="sp-kg"><div class="sp-kgh"><b><a href="' . esc_url( $kg['link'] ) . '" target="_blank" rel="noopener">' . esc_html( $nk( $g['away'] ) . ' at ' . $nk( $g['home'] ) ) . '</a></b><a href="' . esc_url( $img ) . '" download="' . esc_attr( $kg['file'] ) . '">Download the game card</a></div>';
			foreach ( $kg['sides'] as $sd ) {
				echo '<div class="sp-ks">' . DBD_Marks::tile_svg( $sd['t'], 24 ) . '<div class="sp-kt"><b>' . esc_html( $nk( $sd['t'] ) . ' offense.' ) . '</b> ' . esc_html( rtrim( $sd['head'], '.' ) ) . '. <span>' . esc_html( $sd['say'] ) . '</span></div>'
					. dbd_pack_row( $sd, $sd['link'], $sd['fits'], true ) . '</div>';  /* ours, then the team's own subreddit (2.29.1) */
				$tx .= dbd_pack_texts( $sd, $nk( $sd['t'] ) . ': ' );
			}
			echo '<details class="sp-tx"><summary>Texts to copy</summary>' . $tx . '</details></div>';
		}
		echo '</section>';
	}
	dbd_pack_settings_box( $n );
}

/* ------------------------------------------------------------------ Week in review */

function dbd_tab_week() {
	$s = dbd_admin_state(); $pm = $s['pmap']; $cw = $s['weeks'];
	?>
	<form method="post" action="options.php" class="dbd-box">
		<h2>The weekly post <small>written after each finished week, scheduled for the Tuesday after it at 8:00 AM Eastern</small></h2>
		<?php settings_fields( 'dbd_posts' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Write the weekly post</th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( DBD_Post::OPT_ON ); ?>" value="1" <?php checked( DBD_Post::on() ); ?>> Write each finished week's post after the sync that finishes it</label>
				<p class="description">Until it publishes, a later sync refreshes its numbers. Once you edit it or it publishes, it's never changed again. A deleted post is never written again.</p></td></tr>
			<tr><th scope="row"><label for="dbd_post_form">Signup under each post</label></th>
				<td><input name="<?php echo esc_attr( DBD_Post::OPT_FORM ); ?>" id="dbd_post_form" type="text" class="regular-text code" value="<?php echo esc_attr( get_option( DBD_Post::OPT_FORM, DBD_Post::FORM ) ); ?>">
				<p class="description">A form shortcode shown under each weekly post on the site, not in the feed. Empty for none.</p></td></tr>
		</table>
		<div class="dbd-foot"><?php submit_button( 'Save', 'primary', 'submit', false ); ?></div>
	</form>

	<div class="dbd-box"><h2>Weeks <small>the newest first</small></h2>
		<table class="widefat striped"><thead><tr><th>Week</th><th>Post</th><th>State</th><th>Email</th></tr></thead><tbody>
			<?php foreach ( array_reverse( $cw ) as $w ) : $pp = isset( $pm[ $w ] ) ? get_post( (int) $pm[ $w ] ) : null; ?>
			<tr><td><?php echo esc_html( DBD_Router::week_label( $w ) ); ?></td>
				<td><?php echo $pp ? '<a href="' . esc_url( (string) get_edit_post_link( $pp->ID ) ) . '">' . esc_html( get_the_title( $pp ) ) . '</a>' : '–'; ?></td>
				<td><?php
				if ( ! isset( $pm[ $w ] ) ) { echo 'not written'; }
				elseif ( ! $pp || $pp->post_status === 'trash' ) { echo 'deleted (won\'t be written again)'; }
				elseif ( $pp->post_status === 'future' ) { echo esc_html( 'scheduled ' . get_date_from_gmt( $pp->post_date_gmt, 'D M j, g:i A' ) ); }
				else { echo esc_html( $pp->post_status === 'publish' ? 'published ' . get_date_from_gmt( $pp->post_date_gmt, 'M j' ) : $pp->post_status ); }
				?></td>
				<td><?php echo $pp && $pp->post_status !== 'trash' ? esc_html( dbd_admin_email_word( $pp ) ) : '–'; ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $cw ) : ?><tr><td colspan="4">No finished week yet this season.</td></tr><?php endif; ?>
		</tbody></table>
		<?php $missing = count( array_filter( $cw, function ( $w ) use ( $pm ) { return ! isset( $pm[ $w ] ); } ) ); ?>
		<div class="dbd-foot">
			<?php if ( $missing ) : echo dbd_admin_button( 'dbd_write_past', 'Write past weeks (' . $missing . ')', true, 'Write a post for each of the ' . $missing . ' finished week(s) without one? Each is dated the Tuesday after its week and publishes with that date, and none of them is emailed.' ); ?>
			<?php else : ?><span class="description"><?php echo $cw ? 'Every finished week has a post.' : 'Posts start after the first finished week.'; ?></span><?php endif; ?>
		</div>
	</div>

	<?php
	$eo = $s['eo']; $acct = $eo['key'] !== '' ? DBD_Email::cached( 'account' ) : null;
	$lists = $eo['key'] !== '' ? DBD_Email::cached( DBD_Email::READS[1] ) : null; $tpls = $eo['key'] !== '' ? DBD_Email::cached( DBD_Email::READS[2] ) : null;
	$ll = is_array( $lists[1]['lists'] ?? null ) ? $lists[1]['lists'] : array(); $tl = is_array( $tpls[1]['templates'] ?? null ) ? $tpls[1]['templates'] : array();
	usort( $tl, function ( $x, $y ) { return array( ( $y['tag'] ?? '' ) === 'optin', $y['id'] ?? 0 ) <=> array( ( $x['tag'] ?? '' ) === 'optin', $x['id'] ?? 0 ); } );
	$es = $s['esub']; $el = $s['elog']; $lp = DBD_Email::latest(); $opt = DBD_Email::OPT;
	?>
	<form method="post" action="options.php" class="dbd-box" id="dbd-email">
		<h2>The Tuesday email <small><?php echo DBD_Email::ready() ? '<span class="dbd-dot ok"></span>On' : ( ! empty( $eo['on'] ) ? '<span class="dbd-dot warn"></span>On, but not set up' : '<span class="dbd-dot"></span>Off' ); ?></small></h2>
		<?php settings_fields( 'dbd_email' ); ?>
		<div class="dbd-in"><p>Each week post, as it publishes on its Tuesday, is emailed to your Brevo list: the whole post, styled for email, with links back to the site. It sends once, and a post published more than a day after its date never sends. Each week post has a <b>Tuesday email</b> box with "Don't email this one" and a test button. New signups get Brevo's confirmation email and join the list after they click it. WordPress keeps no addresses.</p></div>
		<table class="dbd-rows" style="border-top:1px solid #f0f0f1"><tbody>
			<tr><td>Brevo</td><td><?php
			if ( $eo['key'] === '' ) { echo 'Not connected. Paste an API key below (Brevo › your name › SMTP &amp; API › API keys).'; }
			elseif ( $acct[2] ) { echo '<span class="dbd-red">' . esc_html( $acct[2] ) . '</span>'; }
			else { echo esc_html( 'Connected as ' . ( $acct[1]['email'] ?? '?' ) . ( ! empty( $acct[1]['plan'][0]['type'] ) ? ' (' . $acct[1]['plan'][0]['type'] . ' plan)' : '' ) ); }
			?></td></tr>
			<tr><td>Signups</td><td><?php
			if ( (int) $eo['tpl'] < 1 ) { echo 'Waiting for the confirmation template, so new signups aren\'t passed to Brevo yet.'; }
			else { echo esc_html( is_array( $es ) && ! empty( $es['ok'] ) ? (int) $es['ok'] . ' confirmation email' . ( (int) $es['ok'] === 1 ? '' : 's' ) . ' sent, the last ' . dbd_admin_when( $es['last'] ?? 0 ) . '.' : 'None yet.' ); }
			if ( is_array( $es ) && ! empty( $es['last_err'] ) ) { echo '<br><span class="dbd-red">' . esc_html( (int) $es['err'] . ' failed. The last, ' . dbd_admin_when( $es['last_err'][0] ) . ': ' . $es['last_err'][1] ) . '</span>'; }
			?></td></tr>
		</tbody></table>
		<table class="form-table" role="presentation" style="border-top:1px solid #f0f0f1">
			<tr><th scope="row">Send the email</th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[on]" value="1" <?php checked( (bool) $eo['on'] ); ?>> Email each week post to the list when it publishes, and pass signups to Brevo</label></td></tr>
			<tr><th scope="row"><label for="dbd_e_key">Brevo API key</label></th>
				<td><input name="<?php echo esc_attr( $opt ); ?>[key]" id="dbd_e_key" type="password" class="regular-text code" autocomplete="off" value="" placeholder="<?php echo esc_attr( $eo['key'] !== '' ? 'saved, ending ' . substr( $eo['key'], -4 ) : 'xkeysib-…' ); ?>">
				<?php if ( $eo['key'] !== '' ) : ?><label style="margin-left:8px"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[forget]" value="1"> Remove the key</label><?php endif; ?>
				<p class="description">Leave it empty to keep the saved key.</p></td></tr>
			<tr><th scope="row"><label for="dbd_e_list">List</label></th>
				<td><?php if ( $ll ) : ?><select name="<?php echo esc_attr( $opt ); ?>[list]" id="dbd_e_list"><option value="0">Choose a list</option><?php foreach ( $ll as $l ) : ?><option value="<?php echo (int) $l['id']; ?>" <?php selected( (int) $eo['list'], (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] . ' (' . (int) ( $l['uniqueSubscribers'] ?? $l['totalSubscribers'] ?? 0 ) . ')' ); ?></option><?php endforeach; ?></select>
				<?php else : ?><input name="<?php echo esc_attr( $opt ); ?>[list]" id="dbd_e_list" type="number" min="0" class="small-text" value="<?php echo (int) $eo['list']; ?>"><?php endif; ?>
				<p class="description">The Brevo list the email goes to and signups join.</p></td></tr>
			<tr><th scope="row"><label for="dbd_e_tpl">Confirmation template</label></th>
				<td><?php if ( $tl ) : ?><select name="<?php echo esc_attr( $opt ); ?>[tpl]" id="dbd_e_tpl"><option value="0">Choose a template</option><?php foreach ( $tl as $t ) : ?><option value="<?php echo (int) $t['id']; ?>" <?php selected( (int) $eo['tpl'], (int) $t['id'] ); ?>><?php echo esc_html( $t['name'] . ( ( $t['tag'] ?? '' ) === 'optin' ? ' (optin)' : '' ) ); ?></option><?php endforeach; ?></select>
				<?php else : ?><input name="<?php echo esc_attr( $opt ); ?>[tpl]" id="dbd_e_tpl" type="number" min="0" class="small-text" value="<?php echo (int) $eo['tpl']; ?>"><?php endif; ?>
				<p class="description">The "Confirm your email" message Brevo sends each signup. Only active templates are listed; one made in the last minute shows after you save here.</p>
				<details style="margin-top:8px"><summary>The confirmation email's HTML</summary>
				<p class="description">In Brevo: Campaigns › Templates › New template, choose to paste your own code and paste this. Give the template the tag <code>optin</code>, then save and activate it. Its button links to <code>{{ doubleoptin }}</code>, Brevo's confirm link.</p>
				<textarea readonly rows="8" class="large-text code" onclick="this.select()"><?php echo esc_textarea( DBD_Email::confirm_html() ); ?></textarea></details></td></tr>
			<tr><th scope="row"><label for="dbd_e_fn">From</label></th>
				<td><input name="<?php echo esc_attr( $opt ); ?>[from_name]" id="dbd_e_fn" type="text" value="<?php echo esc_attr( $eo['from_name'] ); ?>"> <input name="<?php echo esc_attr( $opt ); ?>[from_email]" type="email" class="regular-text" value="<?php echo esc_attr( $eo['from_email'] ); ?>" aria-label="From address">
				<p class="description">The address must be a sender in Brevo (Senders, Domains &amp; Dedicated IPs).</p></td></tr>
			<tr><th scope="row"><label for="dbd_e_re">Reply-to</label></th>
				<td><input name="<?php echo esc_attr( $opt ); ?>[reply]" id="dbd_e_re" type="email" class="regular-text" value="<?php echo esc_attr( $eo['reply'] ); ?>"></td></tr>
			<tr><th scope="row"><label for="dbd_e_ad">Postal address</label></th>
				<td><input name="<?php echo esc_attr( $opt ); ?>[address]" id="dbd_e_ad" type="text" class="large-text" value="<?php echo esc_attr( $eo['address'] ); ?>"><p class="description">In every email's footer, as US law requires.</p></td></tr>
			<tr><th scope="row"><label for="dbd_e_fm">Signup form</label></th>
				<td><input name="<?php echo esc_attr( $opt ); ?>[form]" id="dbd_e_fm" type="number" min="1" class="small-text" value="<?php echo (int) $eo['form']; ?>"> <span class="description">The WPForms form's ID.</span></td></tr>
			<tr><th scope="row">Board emails</th>
				<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[boards]" value="1" <?php checked( $eo['boards'] !== false ); ?>> Email the list when a new combine's results or draft board lands</label>
				<p class="description">The Tuesday after its data stops changing for two days, at 8:00 AM Eastern. Twice a year; the boards already on the site are never emailed.</p></td></tr>
			<tr><th scope="row"><label for="dbd_e_te">Tests go to</label></th>
				<td><input name="<?php echo esc_attr( $opt ); ?>[test]" id="dbd_e_te" type="email" class="regular-text" value="<?php echo esc_attr( $eo['test'] ); ?>" placeholder="<?php echo esc_attr( DBD_Email::test_to() ); ?>"> <span class="description">Empty uses the reply-to.</span></td></tr>
		</table>
		<div class="dbd-foot"><?php submit_button( 'Save', 'primary', 'submit', false ); ?>
			<?php if ( $lp && $eo['key'] !== '' ) : ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbd_email_test' ), 'dbd_email_test' ) ); ?>">Send a test of <?php echo esc_html( DBD_Router::week_label( (int) get_post_meta( $lp->ID, DBD_Post::M_WEEK, true ) ) ); ?> to <?php echo esc_html( DBD_Email::test_to() ); ?></a><?php endif; ?></div>
	</form>

	<?php $bm = DBD_Board_Mail::state(); ?>
	<div class="dbd-box" id="dbd-boards"><h2>Board emails <small>the combine and the draft, twice a year (SCOPE §66)</small></h2>
		<table class="dbd-rows"><tbody>
		<?php foreach ( DBD_Board_Mail::KINDS as $bk ) :
			$bys = DBD_Boards::years( $bk ); $bnew = $bys[0] ?? 0; $bsent = (int) ( $bm['sent'][ $bk ] ?? 0 ); $bseen = $bm['seen'][ $bk ] ?? null; $bjob = $bnew ? ( $bm['jobs'][ $bk . '-' . $bnew ] ?? null ) : null;
			if ( ! $bnew ) { $btxt = 'No board yet.'; }
			elseif ( $bjob ) { $btxt = 'The ' . $bnew . ' email: ' . $bjob['state'] . '.'; }
			elseif ( $bnew > $bsent && $bseen && (int) $bseen[0] === $bnew ) { $btxt = 'The ' . $bnew . ' ' . $bk . ' is in, unchanged since ' . dbd_admin_when( $bseen[2] ) . '. It goes the first Tuesday after two days unchanged.'; }
			elseif ( $bsent ) { $btxt = 'Up to date through ' . $bsent . '. The next one goes the Tuesday after the ' . ( $bsent + 1 ) . ' ' . ( $bk === 'draft' ? 'draft' : 'combine results' ) . ' land.'; }
			else { $btxt = 'Waiting for the first sync to note the boards on the site.'; }
		?>
			<tr><td><?php echo esc_html( ucfirst( $bk ) ); ?></td><td><?php echo esc_html( $btxt ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<div class="dbd-foot"><?php if ( $eo['key'] !== '' ) : foreach ( DBD_Board_Mail::KINDS as $bk ) : $bys = DBD_Boards::years( $bk ); if ( ! $bys ) { continue; } ?><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbd_board_test&kind=' . $bk ), 'dbd_board_test' ) ); ?>">Send a test of the <?php echo (int) $bys[0] . ' ' . esc_html( $bk ); ?> email</a><?php endforeach; ?><span class="description">Tests go to <?php echo esc_html( DBD_Email::test_to() ); ?>.</span><?php else : ?><span class="description">Add the Brevo API key above to send a test.</span><?php endif; ?></div></div>

	<?php $again = array(); ?>
	<div class="dbd-box"><h2>Send log</h2>
		<table class="widefat striped"><thead><tr><th>When</th><th>Post</th><th>Result</th><th>To</th></tr></thead><tbody>
			<?php foreach ( $el as $r ) : ?>
			<tr><td><?php echo esc_html( dbd_admin_when( $r['at'] ) ); ?></td>
				<?php $rb = (string) ( $r['board'] ?? '' ); $rbk = $rb !== '' ? explode( '-', $rb ) : null; ?>
				<td><a href="<?php echo esc_url( $rbk ? DBD_Router::url( array( 'view' => $rbk[0], 'y' => (int) $rbk[1] ) ) : (string) get_edit_post_link( (int) $r['post'] ) ); ?>"><?php echo esc_html( $r['title'] ); ?></a></td>
				<td><?php echo esc_html( $r['result'] . ( $r['camp'] ? ' (Brevo campaign ' . $r['camp'] . ')' : '' ) ); ?><?php if ( strpos( (string) $r['result'], 'failed' ) === 0 ) : $ak = $rb !== '' ? $rb : 'p' . (int) $r['post']; if ( ! isset( $again[ $ak ] ) && ( $rb !== '' ? DBD_Board_Mail::failed( $rb ) : DBD_Email::failed( $r['post'] ) ) ) : $again[ $ak ] = 1; ?> <a class="button button-small" href="<?php echo esc_url( $rb !== '' ? DBD_Board_Mail::again_url( $rb ) : DBD_Email::again_url( $r['post'] ) ); ?>">Try again</a><?php endif; endif; ?></td><td><?php echo $r['n'] ? (int) $r['n'] : '–'; ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $el ) : ?><tr><td colspan="4">Nothing sent yet.</td></tr><?php endif; ?>
		</tbody></table></div>
	<?php
}

/* ------------------------------------------------------------------ Team news */

function dbd_tab_news() {
	$s = dbd_admin_state(); $ns = $s['news']; $nf = DBD_News::feeds(); $nnext = wp_next_scheduled( DBD_News::HOOK );
	?>
	<div class="dbd-box"><h2>Fetching <small>every 6 hours, about 12 and 6 Eastern, morning and evening</small></h2>
		<table class="dbd-rows"><tbody>
			<tr><td>Last fetch</td><td><?php echo is_array( $ns ) ? esc_html( dbd_admin_when( $ns['at'] ) . ': ' . $s['nok'] . ' of ' . count( $s['feeds'] ) . ' feeds read in ' . $ns['secs'] . ' seconds' ) : 'not yet'; ?></td></tr>
			<tr><td>Next fetch</td><td><?php echo esc_html( $nnext ? dbd_admin_when( $nnext ) : 'not scheduled' ); ?></td></tr>
			<tr><td><?php echo $s['nprob'] ? '<span class="dbd-dot warn"></span>' : ''; ?>Feeds to check</td><td><?php
			if ( ! $s['nprob'] ) { echo is_array( $ns ) ? 'None: every feed answered with recent stories.' : '–'; }
			else { $li = array(); foreach ( $s['nprob'] as $k => $x ) { $li[] = '<a href="' . esc_url( $nf[ $k ][0] ?? '' ) . '">' . esc_html( ( $nf[ $k ][3] ?? $k ) . ( ! empty( $nf[ $k ][2] ) && ( $nf[ $k ][1] ?? '' ) !== 'site' ? ' (' . $nf[ $k ][2] . ')' : '' ) ) . '</a>: ' . esc_html( $x['err'] ?: 'failed' ) . ( empty( $x['ok'] ) && ! empty( $x['last'] ) ? esc_html( ', last read ' . dbd_admin_when( $x['last'], 'M j' ) ) : '' ); } echo implode( '<br>', $li ); }
			?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo dbd_admin_button( 'dbd_news_now', 'Fetch news and videos now', true ); ?><span class="description">Each fetch adds to the stories kept from the last 7 days, so a feed that fails or sends an old copy of itself loses nothing.</span></div>
	</div>
	<?php
	$vs = get_option( DBD_Videos::OPT_STATUS ); $vf = DBD_Videos::feeds(); $vst = is_array( $vs ) ? (array) $vs['feeds'] : array();
	$vok = count( array_filter( $vst, function ( $x ) { return ! empty( $x['ok'] ); } ) ); $vprob = array_filter( $vst, function ( $x ) { return empty( $x['ok'] ) || ! empty( $x['err'] ); } );
	$hs = dbd_admin_highlights(); $pl = is_array( $vs ) ? (array) ( $vs['pl'] ?? array() ) : array();
	?>
	<form method="post" action="options.php" class="dbd-box" id="dbd-videos"><h2>Videos <small>each team's YouTube channel and the NFL's, with the same fetch</small></h2>
		<?php settings_fields( 'dbd_videos' ); ?>
		<table class="dbd-rows"><tbody>
			<tr><td>Last fetch</td><td><?php echo is_array( $vs ) ? esc_html( dbd_admin_when( $vs['at'] ) . ': ' . $vok . ' of ' . count( $vst ) . ' channels read in ' . $vs['secs'] . ' seconds' ) : ( DBD_Videos::on() ? 'not yet: the first fetch runs a couple of minutes after the update' : 'switched off' ); ?></td></tr>
			<tr><td><?php echo $vprob ? '<span class="dbd-dot warn"></span>' : ''; ?>Channels to check</td><td><?php
			if ( ! $vprob ) { echo is_array( $vs ) ? 'None: every channel answered.' : '–'; }
			else { $li = array(); foreach ( $vprob as $k => $x ) { $li[] = '<a href="' . esc_url( 'https://www.youtube.com/channel/' . ( $k === 'yt:NFL' ? DBD_Videos::NFL : ( DBD_Videos::CH[ substr( $k, 3 ) ] ?? '' ) ) ) . '">' . esc_html( $vf[ $k ][3] ?? $k ) . '</a>: ' . esc_html( $x['err'] ?: 'failed' ); } echo implode( '<br>', $li ); }
			?></td></tr>
			<tr><td><?php echo $pl && empty( $pl['ok'] ) ? '<span class="dbd-dot warn"></span>' : ''; ?>Game highlights</td><td><?php
			echo esc_html( $hs[0] . ' of ' . $hs[1] . ' finished games this season' );
			if ( $pl && empty( $pl['ok'] ) ) { echo '<br><span class="dbd-red">' . esc_html( 'The last fetch couldn’t find them: ' . $pl['err'] . '.' ) . '</span>'; }
			elseif ( ! empty( $pl['found'] ) ) { echo esc_html( '; the last fetch found ' . (int) $pl['found'] . '.' ); }
			?><p class="description">From the NFL's weekly "Game Recaps" playlists, which its YouTube page lists for about the last five weeks; earlier weeks may have none.</p></td></tr>
			<?php $sn = DBD_Data::season(); $pvC = count( (array) ( ( (array) get_option( DBD_Videos::OPT_PV, array() ) )[ $sn ] ?? array() ) ); $phS = (array) ( ( (array) get_option( DBD_Videos::OPT_PH, array() ) )[ $sn ] ?? array() ); $phC = array_sum( array_map( 'count', $phS ) ); $dhC = array_sum( array_map( 'count', (array) ( ( (array) get_option( DBD_Videos::OPT_DH, array() ) )[ $sn ] ?? array() ) ) ); ?>
			<tr><td>Previews and players</td><td><?php echo esc_html( $pvC . ' game previews, ' . $phC . ' player highlight videos (' . count( $phS ) . ' players) and ' . $dhC . ' team defense videos this season' . ( ! empty( $pl['pv'] ) || ! empty( $pl['ph'] ) ? '; the last fetch found ' . (int) ( $pl['pv'] ?? 0 ) . ' previews and ' . (int) ( $pl['ph'] ?? 0 ) . ' player videos' : '' ) . '.' ); ?><p class="description">From the NFL's weekly "Game Previews" and "Player Highlights" playlists (SCOPE §54).</p></td></tr>
		</tbody></table>
		<table class="form-table" role="presentation" style="border-top:1px solid #f0f0f1">
			<tr><th scope="row">Show videos</th><td><label><input type="checkbox" name="<?php echo esc_attr( DBD_Videos::OPT_ON ); ?>" value="1" <?php checked( DBD_Videos::on() ); ?>> Show each team's videos on its players page and each finished game's highlights</label>
			<p class="description">Shorts are left out. A click opens the video on YouTube in a new tab: the NFL and the teams don't allow their videos to play on other sites.</p></td></tr>
		</table>
		<div class="dbd-foot"><?php submit_button( 'Save', 'primary', 'submit', false ); ?></div>
	</form>
	<form method="post" action="options.php" class="dbd-box"><h2>Sources <small>shown on each team's players page, with the newest on its offense and defense pages</small></h2>
		<?php settings_fields( 'dbd_news' ); ?>
		<table class="form-table" role="presentation">
			<tr><th scope="row">Show stories from</th><td>
			<?php foreach ( DBD_News::groups() as $g => $lab ) : ?>
				<label style="display:block;margin:0 0 4px"><input type="checkbox" name="<?php echo esc_attr( DBD_News::OPT_SRC ); ?>[]" value="<?php echo esc_attr( $g ); ?>" <?php checked( DBD_News::on( $g ) ); ?>> <?php echo esc_html( $lab ); ?></label>
			<?php endforeach; ?>
			<p class="description">Headline, source and time only, linking out. A source switched off leaves the pages at the next fetch. The Athletic's links say "Subscriber". Switch everything off to hide the news.</p></td></tr>
		</table>
		<div class="dbd-foot"><?php submit_button( 'Save', 'primary', 'submit', false ); ?></div>
	</form>
	<?php
}

/* ------------------------------------------------------------------ Site */

function dbd_tab_site() {
	$plain = ! get_option( 'permalink_structure' ); $aid = DBD_Urls::page_id();
	?>
	<form method="post" action="options.php">
		<?php settings_fields( 'dbd' ); ?>
		<div class="dbd-box"><h2>Photos and logos</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Player photos</th>
					<td><label><input type="checkbox" name="dbd_headshots" value="1" <?php checked( DBD_Data::headshots() ); ?>> Show NFL headshots on player pages and the Players index</label>
					<p class="description">Hotlinked from the NFL's image CDN using the URLs nflverse publishes; league property, not licensed to this site. Each photo loads over the player's jersey tile and falls back to it. Off removes every photo at once.</p></td></tr>
				<tr><th scope="row">Team logos</th>
					<td><label><input type="checkbox" name="dbd_logos" value="1" <?php checked( DBD_Data::logos() ); ?>> Show NFL team logos in place of the drawn team tiles</label>
					<p class="description">Hotlinked from ESPN's image CDN; league and team property, not licensed to this site. The drawn tile stays if a logo fails to load.</p></td></tr>
			</table></div>
		<div class="dbd-box"><h2>Addresses</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Readable URLs</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( DBD_Urls::OPT ); ?>" value="1" <?php checked( (bool) get_option( DBD_Urls::OPT, true ) ); ?>> Use paths like <code>/team/min/</code> and <code>/player/justin-jefferson/</code></label>
					<p class="description">Old <code>?team=</code> and <code>?player=</code> links redirect to the paths. Off goes back to query strings.
					<?php if ( $plain ) : ?><strong>This site uses plain permalinks, so the app keeps query strings.</strong> Choose another structure under Settings › Permalinks to use paths.<?php else : ?>Permalinks: <?php echo esc_html( get_option( 'permalink_structure' ) ); ?>, so paths work here<?php echo $aid ? '' : ', once a page holds <code>[down_by_down]</code>'; ?>.<?php endif; ?></p></td></tr>
				<tr><th scope="row">Paths at the site root</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( DBD_Urls::OPT_ROOT ); ?>" value="1" <?php checked( DBD_Urls::root() ); ?>> Keep <code>/team/min/</code>, <code>/player/…/</code> and <code>/2025/</code> at the root when the app page isn't the front page</label>
					<p class="description"><?php
					if ( ! $aid ) { echo 'No page holds <code>[down_by_down]</code> yet.'; }
					elseif ( DBD_Urls::is_front() ) { echo 'The app is the front page, so its paths are at the root either way.'; }
					else { echo 'The app page is <a href="' . esc_url( get_permalink( $aid ) ) . '">' . esc_html( get_permalink( $aid ) ) . '</a>. Leave this on so links shared before the move keep working; a real page named teams, players, leaders, week or matchups would be hidden behind the app\'s paths.'; }
					?></p></td></tr>
			</table></div>
		<div class="dbd-box"><h2>Search and sharing</h2>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="dbd_home_desc">Home page description</label></th>
					<td><textarea name="<?php echo esc_attr( DBD_Seo::OPT_HOME_DESC ); ?>" id="dbd_home_desc" rows="3" class="large-text" placeholder="<?php echo esc_attr( DBD_Seo::HOME_DESC ); ?>"><?php echo esc_textarea( (string) get_option( DBD_Seo::OPT_HOME_DESC, '' ) ); ?></textarea>
					<p class="description">Shown under the home page's title in search results and shared links, when the home is a page you built. Empty uses the text shown. An SEO plugin, if you add one, takes over.</p></td></tr>
			</table>
			<div class="dbd-foot"><?php submit_button( 'Save', 'primary', 'submit', false ); ?><span class="description">Saves the three boxes on this tab.</span></div></div>
	</form>

	<div class="dbd-box dbd-help"><h2>Using it in the theme</h2><div class="dbd-in">
		<details open><summary>The app page and its addresses</summary><ul>
			<?php if ( DBD_Urls::on() ) : ?>
			<li>Put <code>[down_by_down]</code> on one page. That page becomes the app: the hub at its URL, <code>team/min/</code> for a team (<code>team/min/defense/</code>, <code>team/min/players/</code>), <code>game/2026-week-8-vikings-at-lions/</code> for a matchup, <code>player/justin-jefferson/</code> for a player, <code>compare/a/b/</code> to compare players, and <code>matchups/</code>, <code>teams/</code>, <code>players/</code>, <code>leaders/</code>, <code>week/8/</code> and <code>my-team/</code>. Link the sidebar menu to these.</li>
			<?php else : ?>
			<li>Put <code>[down_by_down]</code> on one page. That page becomes the app: the hub at its URL, <code>?team=MIN</code> for a team, <code>?game=…</code> for a matchup, <code>?view=matchups</code>, <code>?view=teams</code> and <code>?view=players</code> for the index views, <code>?player=…</code> for a player, <code>?compare=id,id</code> to compare players.</li>
			<?php endif; ?>
			<li>The app renders only the content column. Build the sidebar in the theme (Divi Theme Builder) and link its menu to the addresses above.</li>
			<li>Without a page-builder theme, assign the page template "Down by Down: app page (no theme header)".</li>
		</ul></details>
		<details><summary>Live blocks for pages you build</summary><ul>
			<li><code>[dbd_block type="hero-map"]</code>: the week's most-targeted player; <code>player="00-0036322"</code> pins one.</li>
			<li><code>[dbd_block type="games"]</code>: this week's games. <code>[dbd_block type="counts"]</code>: the small counts.</li>
			<li><code>[dbd_block type="week-highlights"]</code>: the latest week in three cards. <code>[dbd_block type="matchup"]</code>: the game of the week, offense against defense.</li>
			<li><code>[dbd_block type="player-card"]</code>: the week's biggest riser's share card. <code>[dbd_block type="movers"]</code>: the week's risers in target share.</li>
			<li><code>[dbd_block type="fantasy"]</code>: the softest defense at each position. <code>[dbd_block type="leaders"]</code>: leader boards (<code>boards="ppr"</code> for PPR points).</li>
			<li><code>[dbd_block type="teams"]</code>: all 32 teams by division. <code>[dbd_block type="newsletter-preview"]</code>: the latest finished week as the Tuesday email.</li>
			<li>Each shows this week's data and updates with every sync.</li>
		</ul></details>
		<details><summary>Body classes and menu counts</summary><ul>
			<li>Body classes say which view is showing: <code>dbd-view-hub</code>, <code>dbd-view-team</code>, <code>dbd-view-game</code>, <code>dbd-view-matchups</code>, <code>dbd-view-teams</code>, <code>dbd-view-players</code>, <code>dbd-view-player</code>, <code>dbd-view-compare</code>.</li>
			<li><code>[dbd_count games]</code> and <code>[dbd_count teams]</code> print the small numbers for menu items.</li>
		</ul></details>
	</div></div>
	<?php
}

/* ------------------------------------------------------------------ Data */

function dbd_tab_data() {
	$s = dbd_admin_state(); $m = DBD_Data::manifest(); $log = $s['log']; $token = DBD_Sync::ensure_token();
	$next = wp_next_scheduled( DBD_Sync::HOOK ); $cron_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	?>
	<div class="dbd-box"><h2>Current data</h2>
		<table class="dbd-rows"><tbody>
			<tr><td>Season</td><td><?php echo esc_html( ( $m['season'] ?? '–' ) . ( isset( $m['week'] ) ? ', week ' . $m['week'] : '' ) ); ?></td></tr>
			<tr><td>Play-by-play through</td><td><?php echo esc_html( isset( $m['through_week'] ) ? 'Week ' . $m['through_week'] : '–' ); ?></td></tr>
			<tr><td>Built</td><td><?php echo esc_html( ( $m['built_at'] ?? 'never' ) . ( DBD_Data::updated_text() ? ' (' . DBD_Data::updated_text() . ')' : '' ) ); ?></td></tr>
			<tr><td>Files</td><td><?php echo count( $m['files'] ?? array() ); ?> in <code><?php echo esc_html( DBD_Data::dir() ); ?></code></td></tr>
			<tr><td>Next scheduled pull</td><td><?php echo esc_html( $next ? dbd_admin_when( $next ) : 'not scheduled' ); ?><?php if ( $cron_off ) : ?> <strong>WP-Cron is disabled on this site.</strong> Make sure a real cron hits <code>wp-cron.php</code> or runs <code>wp cron event run <?php echo esc_html( DBD_Sync::HOOK ); ?></code> daily.<?php endif; ?></td></tr>
			<tr><td>After an update</td><td><?php $sv = (string) get_option( DBD_Sync::OPT_VER ); $su = wp_next_scheduled( DBD_Sync::HOOK_UPDATE ); echo $sv === DBD_VERSION ? 'This version (' . esc_html( DBD_VERSION ) . ') has synced.' : ( $su ? 'A sync for ' . esc_html( DBD_VERSION ) . ' runs at ' . esc_html( dbd_admin_when( $su, 'g:i A' ) ) . '.' : 'Not synced on ' . esc_html( DBD_VERSION ) . ' yet.' ); ?> A new version pulls the data on its own about two minutes after it's installed.</td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo dbd_admin_button( 'dbd_sync_now', 'Sync now', true ); ?></div>
	</div>

	<div class="dbd-box"><h2>Sync log <small>times in UTC</small></h2>
		<?php $row = function ( $r ) { return '<tr><td>' . esc_html( $r['time'] ) . '</td><td>' . esc_html( $r['trigger'] ) . '</td><td>' . ( $r['ok'] ? 'ok' : '<span class="dbd-red">' . esc_html( $r['error'] ?: 'failed' ) . '</span>' ) . '</td><td>' . esc_html( ( $r['changed'] ? count( $r['changed'] ) . ' files' : 'nothing' ) . ( ! empty( $r['archive'] ) ? ' · archive ' . $r['archive'] : '' ) ) . '</td><td>' . esc_html( $r['seconds'] ) . '</td></tr>'; }; ?>
		<table class="widefat striped"><thead><tr><th>When</th><th>Trigger</th><th>Result</th><th>Changed</th><th>Seconds</th></tr></thead><tbody>
			<?php foreach ( array_slice( $log, 0, 6 ) as $r ) { echo $row( $r ); } ?>
			<?php if ( ! $log ) : ?><tr><td colspan="5">No syncs yet.</td></tr><?php endif; ?>
		</tbody></table>
		<?php if ( count( $log ) > 6 ) : ?>
		<details><summary class="dbd-foot" style="border-bottom:0">Show all <?php echo count( $log ); ?></summary>
			<table class="widefat striped"><tbody><?php foreach ( array_slice( $log, 6 ) as $r ) { echo $row( $r ); } ?></tbody></table></details>
		<?php endif; ?>
	</div>

	<div class="dbd-box"><h2>Seasons</h2>
		<?php $cs = DBD_Data::current_season(); $am = (array) ( DBD_Data::root_manifest()['archive'] ?? array() ); $ah = (array) get_option( DBD_Sync::OPT_ARCH, array() ); ?>
		<table class="widefat striped"><thead><tr><th>Season</th><th>State</th><th>Files</th><th>Built</th></tr></thead><tbody>
			<tr><td><?php echo (int) $cs; ?></td><td>current</td><td><?php echo count( DBD_Data::root_manifest()['files'] ?? array() ); ?></td><td><?php echo esc_html( DBD_Data::root_manifest()['built_at'] ?? '' ); ?></td></tr>
			<?php foreach ( $am as $sn => $h ) : $mf = DBD_Data::root_dir() . '/archive/' . (int) $sn . '/manifest.json'; $mj = is_readable( $mf ) ? json_decode( (string) file_get_contents( $mf ), true ) : null; ?>
			<tr><td><?php echo (int) $sn; ?></td><td><?php echo $mj && ( $ah[ $sn ] ?? '' ) === $h ? 'archived' : 'syncing'; ?></td><td><?php echo $mj ? count( $mj['files'] ?? array() ) : '–'; ?></td><td><?php echo esc_html( $mj['built_at'] ?? '' ); ?></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<div class="dbd-foot"><span class="description">A finished season is set aside when the next one's first build runs. To add an earlier season (2022 or later), run <b>Build an archived season</b> in the repo's GitHub Actions with that year; the site pulls it on the next sync.</span></div>
	</div>

	<div class="dbd-box"><h2>Share cards</h2>
		<?php list( $cn, $cb ) = DBD_Card::stats(); $cp = get_option( 'dbd_card_prune' ); ?>
		<table class="dbd-rows"><tbody>
			<tr><td>Cached</td><td><?php echo esc_html( number_format( $cn ) . ' card' . ( $cn === 1 ? '' : 's' ) . ', ' . size_format( $cb, 1 ) ); ?></td></tr>
			<tr><td>Kept</td><td>This data week's cards for <?php echo esc_html( DBD_VERSION ); ?> only. After each sync, cards from earlier weeks or versions, and any older than 14 days, are deleted.</td></tr>
			<tr><td>Last cleanup</td><td><?php echo is_array( $cp ) ? esc_html( dbd_admin_when( $cp['time'] ) . ': removed ' . number_format( (int) $cp['n'] ) . ' card' . ( (int) $cp['n'] === 1 ? '' : 's' ) . ' (' . size_format( (int) $cp['bytes'], 1 ) . ')' ) : 'not yet'; ?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo dbd_admin_button( 'dbd_clear_cards', 'Clear card cache', false, 'Clear every cached card? Each is drawn again the next time its page is shared.' ); ?></div>
	</div>

	<details class="dbd-box" id="dbd-advanced"<?php echo isset( $_GET['token'] ) ? ' open' : ''; ?>><summary>Advanced: the data source and the push from GitHub</summary>
		<form method="post" action="options.php">
			<?php settings_fields( 'dbd_data' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="dbd_source_url">Data folder URL</label></th>
					<td><input name="<?php echo esc_attr( DBD_Sync::OPT_SOURCE ); ?>" id="dbd_source_url" type="url" class="large-text code" value="<?php echo esc_attr( get_option( DBD_Sync::OPT_SOURCE, DBD_DEFAULT_SOURCE ) ); ?>">
					<p class="description">The <code>data/</code> folder of the GitHub repo, as a raw URL. Must contain <code>manifest.json</code>.</p></td></tr>
			</table>
			<div class="dbd-foot"><?php submit_button( 'Save', 'primary', 'submit', false ); ?></div>
		</form>
		<div class="dbd-in" style="border-top:1px solid #c3c4c7"><p>After each build the GitHub Action calls this site so new data lands within a minute. In the repo, set a variable <code>DBD_SITE_URL</code> and a secret <code>DBD_SYNC_TOKEN</code>:</p></div>
		<table class="dbd-rows"><tbody>
			<tr><td><code>DBD_SITE_URL</code> (variable)</td><td><code><?php echo esc_html( home_url( '/' ) ); ?></code></td></tr>
			<tr><td><code>DBD_SYNC_TOKEN</code> (secret)</td><td><details><summary>Show the token</summary><code><?php echo esc_html( $token ); ?></code></details></td></tr>
			<tr><td>Endpoint</td><td><code>POST <?php echo esc_html( home_url( '/?rest_route=/dbd/v1/sync' ) ); ?></code> with header <code>X-DBD-Token</code></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php echo dbd_admin_button( 'dbd_new_token', 'Make a new token', false, 'Make a new token? The old one stops working immediately, and the GitHub secret must be updated.' ); ?></div>
	</details>
	<?php
}
