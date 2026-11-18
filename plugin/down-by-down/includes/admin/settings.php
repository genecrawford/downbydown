<?php
/**
 * Down by Down in the admin (2.11.0, SCOPE §50): six tabs, each its own page in the sidebar under Down by Down.
 *   Overview        what needs a look, then a summary of each area with its main button
 *   Week in review  the weekly post, its weeks, the Tuesday email and its send log
 *   Team news       the fetch status and the sources
 *   Site            photos and logos, addresses, the home description, notes for the theme
 *   Data            the current data and Sync now, the sync log, seasons, share cards, the data source and GitHub push
 *   Growth          visits by source, Google search, titles to tune, landing pages, email (2.25.0, SCOPE §65; assets/growth.js)
 *   Share pack      five things to post each week, with their cards and texts (2.27.0, SCOPE §68; assets/pack.js)
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
	/* team news (SCOPE §49): the source groups switched on; an empty save switches every source off */
	/* team videos and highlights (SCOPE §51): on by default */
	register_setting( 'dbd_videos', DBD_Videos::OPT_ON, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );
	register_setting( 'dbd_news', DBD_News::OPT_SRC, array( 'type' => 'array', 'sanitize_callback' => function ( $v ) { return array_values( array_intersect( array_map( 'strval', (array) $v ), array_keys( DBD_News::groups() ) ) ); } ) );
} );

/* ------------------------------------------------------------------ the buttons that do something */

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
		.dbd-admin .sp-head{max-width:1180px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin:0 0 16px}.dbd-admin .sp-head form{margin:0}.dbd-admin .sp-head p{margin:0;color:#50575e;flex:1;min-width:260px}
		.dbd-admin .sp-item{max-width:1180px}
		.dbd-admin .sp-body{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px;padding:16px}
		.dbd-admin .sp-card img{display:block;width:100%;height:auto;aspect-ratio:1200/630;border:1px solid #dcdcde;background:#f4f1ea}
		.dbd-admin .sp-acts{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
		.dbd-admin .sp-fits{margin:8px 0 0;color:#50575e}
		.dbd-admin .sp-f{margin:0 0 14px}
		.dbd-admin .sp-fh{display:flex;align-items:center;gap:10px;margin-bottom:4px}.dbd-admin .sp-fh b{flex:1}.dbd-admin .sp-fh .cnt{color:#646970;font-size:12px}.dbd-admin .sp-fh .cnt.over{color:#b32d2e}
		.dbd-admin .sp-f textarea{width:100%;box-sizing:border-box;padding:6px 8px;font-size:13px;line-height:1.5;resize:vertical;field-sizing:content;min-height:3.2em}
		.dbd-admin .sp-cp.ok{border-color:#00a32a;color:#007017}
		.dbd-admin .sp-keys{padding:0 16px}
		.dbd-admin .sp-g{display:grid;grid-template-columns:260px minmax(0,1fr);gap:20px;padding:16px 0;border-top:1px solid #f0f0f1}.dbd-admin .sp-g:first-child{border-top:0}
		.dbd-admin .sp-gc img{display:block;width:100%;height:auto;aspect-ratio:1200/630;border:1px solid #dcdcde;background:#f4f1ea;margin-bottom:8px}
		.dbd-admin .sp-gs{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-content:start}.dbd-admin .sp-gt{grid-column:1/-1;font-size:14px}
		.dbd-admin .sp-kh{display:flex;align-items:center;gap:6px}.dbd-admin .sp-kh span{margin-left:auto;color:#646970}
		.dbd-admin .sp-k p{margin:6px 0 8px;line-height:1.5}
		.dbd-admin .sp-sub{max-width:1180px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0 0 16px}.dbd-admin .sp-sub .description{color:#646970}
		.dbd-admin .sp-rd{border-color:#cb2026;color:#cb2026}
		.dbd-admin .sp-kb{display:flex;flex-wrap:wrap;gap:6px}.dbd-admin .sp-kb .button{font-size:12px;min-height:26px;line-height:24px}
		@media (max-width:1000px){.dbd-admin .sp-body,.dbd-admin .sp-g{grid-template-columns:minmax(0,1fr)}.dbd-admin .sp-gc img{max-width:360px}}
		@media (max-width:640px){.dbd-admin .sp-gs{grid-template-columns:minmax(0,1fr)}.dbd-admin .sp-body{padding:12px}.dbd-admin .sp-keys{padding:0 12px}}
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
	if ( isset( $_GET['pack'] ) ) { $pk = (string) $_GET['pack']; $pn = (int) ( $_GET['pack_n'] ?? 0 ); $n[] = $pk === 'sent' ? array( 'success', 'The pack went to ' . $pn . ( $pn === 1 ? ' person.' : ' people.' ) ) : ( $pk === 'again' ? array( 'success', 'The pack email is going again.' ) : array( 'error', 'The pack email didn’t send: ' . sanitize_text_field( wp_unslash( rawurldecode( (string) ( $_GET['pack_err'] ?? '' ) ) ) ) ) ); }
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

/* ------------------------------------------------------------------ Share pack (2.27.0, SCOPE §68) */

/** The Tuesday email to the people who post (2.28.0, SCOPE §69): on/off, who gets it, send the week shown now, and how the last sends went. */
function dbd_pack_mail_box( $n ) {
	$ps = DBD_Pack_Mail::settings(); $key = (string) DBD_Email::get( 'key' ) !== '';
	?>
	<div class="dbd-box" id="dbd-pack-mail" style="max-width:1180px"><h2><span>The Tuesday email</span><small>Each new pack, Tuesday 7:00 AM Eastern, through Brevo, one copy to each person below</small></h2>
		<form method="post" action="options.php"><?php settings_fields( 'dbd_pack' ); ?>
		<table class="dbd-rows"><tbody>
			<tr><td>Send it</td><td><label><input type="checkbox" name="<?php echo esc_attr( DBD_Pack_Mail::OPT_SET ); ?>[on]" value="1"<?php echo $ps['on'] ? ' checked' : ''; ?>> Email each week’s pack</label></td></tr>
			<tr><td><label for="dbd-pack-to">Send to</label></td><td><textarea id="dbd-pack-to" name="<?php echo esc_attr( DBD_Pack_Mail::OPT_SET ); ?>[to]" rows="3" class="regular-text" style="width:340px;max-width:100%"><?php echo esc_textarea( implode( "\n", $ps['to'] ) ); ?></textarea>
				<p class="description" style="margin:4px 0 0">One address per line, up to <?php echo (int) DBD_Pack_Mail::MAX_TO; ?>. Each person gets their own copy.</p></td></tr>
			<tr><td>Status</td><td><?php
				if ( ! $key ) { echo '<span class="dbd-dot bad"></span>Brevo isn’t set up. <a href="' . esc_url( dbd_admin_url( 'wk' ) ) . '">Add the API key on Week in review</a>.'; }
				else { $st = DBD_Pack_Mail::status();
					if ( ! $st ) { echo '<span class="dbd-dot' . ( $ps['on'] && $ps['to'] ? ' ok' : '' ) . '"></span>' . ( $ps['on'] && $ps['to'] ? 'The next finished week’s pack will be booked for its Tuesday.' : 'Off.' ); }
					foreach ( $st as $i => $l ) { echo ( $i ? '<br>' : '' ) . '<span class="dbd-dot ' . esc_attr( $l[0] ) . '"></span>' . esc_html( $l[1] ) . ( $l[2] !== '' ? ' <a class="button button-small" href="' . esc_url( DBD_Pack_Mail::again_url( $l[2] ) ) . '">Try again</a>' : '' ); } }
			?></td></tr>
		</tbody></table>
		<div class="dbd-foot"><?php submit_button( 'Save', 'primary', 'submit', false ); ?></div>
		</form>
		<?php if ( $key && $ps['to'] && $n ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dbd-foot" style="border-top:0;padding-top:0"><input type="hidden" name="action" value="dbd_pack_now"><input type="hidden" name="week" value="<?php echo (int) $n; ?>"><?php wp_nonce_field( 'dbd_pack_now' ); ?>
			<button class="button"><?php echo esc_html( 'Send the Week ' . $n . ' pack now' ); ?></button><span class="description"><?php echo esc_html( 'To ' . count( $ps['to'] ) . ( count( $ps['to'] ) === 1 ? ' person' : ' people' ) . ', right away. Save any change to the list first.' ); ?></span></form>
		<?php endif; ?>
	</div>
	<?php
}

/** Your subreddit (SCOPE §71). Shown before Week 1 too (Greptile, PR #130). The box takes a pasted address or r/name; sanitize_sub() keeps the name, up to 21. */
function dbd_pack_sub_form() {
	$sub = DBD_Pack::our_sub();
	?>
	<form method="post" action="options.php" class="sp-sub"><?php settings_fields( 'dbd_pack_sub' ); ?>
		<label for="dbd-pack-sub">Your subreddit</label> r/<input id="dbd-pack-sub" name="<?php echo esc_attr( DBD_Pack::OPT_SUB ); ?>" value="<?php echo esc_attr( $sub ); ?>" maxlength="200" class="regular-text" style="width:180px">
		<?php submit_button( 'Save', 'secondary', 'submit', false ); ?><span class="description"><?php echo esc_html( $sub !== '' ? 'Each item’s Post button opens Reddit’s new-post form for r/' . $sub . ', filled in. Clear it to hide the buttons.' : 'Set one to get a Post button on each item.' ); ?></span>
	</form>
	<?php
}

/** The Post to r/… button (SCOPE §71): Reddit's new-post form in a new tab, title and link filled in. Nothing when no subreddit is set. */
function dbd_pack_reddit( $title, $link ) {
	$u = DBD_Pack::reddit_url( $title, $link ); if ( $u === '' ) { return ''; }
	return '<a class="button sp-rd" href="' . esc_url( $u ) . '" target="_blank" rel="noopener">' . esc_html( 'Post to r/' . DBD_Pack::our_sub() ) . '</a>';
}

/** One text with its Copy button; X and Bluesky shows its length against 280. */
function dbd_pack_field( $lab, $text, $count = false ) {
	$n = $count ? DBD_Pack::xlen( $text ) : 0;
	return '<div class="sp-f"><div class="sp-fh"><b>' . esc_html( $lab ) . '</b>' . ( $count ? '<span class="cnt' . ( $n > DBD_Pack::X_MAX ? ' over' : '' ) . '">' . (int) $n . ' of ' . DBD_Pack::X_MAX . '</span>' : '' )
		. '<button type="button" class="button sp-cp">Copy</button></div><textarea readonly rows="2" class="large-text">' . esc_textarea( $text ) . '</textarea></div>';
}

function dbd_tab_share() {
	$weeks = DBD_Pack::weeks();
	if ( ! $weeks ) { dbd_pack_mail_box( 0 ); dbd_pack_sub_form(); echo '<div class="dbd-box"><h2>Share pack</h2><p class="dbd-in" style="padding:12px 16px;margin:0">The pack starts after Week 1’s games are all in.</p></div>'; return; }  /* the email's settings show before Week 1 too (Greptile, PR #127) */
	$want = (int) ( $_GET['week'] ?? 0 ); $n = in_array( $want, $weeks, true ) ? $want : $weeks[0];
	$items = DBD_Pack::items( $n ); $keys = DBD_Pack::keys( $n );
	$tue = DBD_Post::tuesday( $n );
	dbd_pack_mail_box( $n );
	dbd_pack_sub_form();
	?>
	<div class="sp-head">
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="down-by-down-share">
			<select name="week" aria-label="Week" onchange="this.form.submit()"><?php foreach ( $weeks as $w ) { echo '<option value="' . (int) $w . '"' . ( $w === $n ? ' selected' : '' ) . '>Week ' . (int) $w . '</option>'; } ?></select>
			<noscript><button class="button">Show</button></noscript></form>
		<p><?php echo esc_html( 'Week ' . $n . ' pack' . ( $tue ? ', for ' . wp_date( 'l, M j', $tue ) : '' ) . '. Post two or three a week by hand: the image first, and the link in a comment where a subreddit asks for that. Visits from reddit.com, x.com and bsky.app show on the Growth tab.' ); ?></p>
	</div>
	<?php
	foreach ( $items as $it ) {
		$i = DBD_Pack::NUM[ $it['k'] ] ?? 0; $img = DBD_Card::url( $it['card'] );
		echo '<div class="dbd-box sp-item" data-k="' . esc_attr( $it['k'] ) . '"><h2><span>' . (int) $i . '. ' . esc_html( $it['h'] ) . '</span><small>' . esc_html( $it['why'] ) . '</small></h2><div class="sp-body">'
			. '<div class="sp-l"><div class="sp-card"><img src="' . esc_url( $img ) . '" width="1200" height="630" alt="" loading="lazy"></div>'
			. '<div class="sp-acts"><a class="button button-primary" href="' . esc_url( $img ) . '" download="' . esc_attr( $it['file'] ) . '">Download image</a><a class="button" href="' . esc_url( $it['link'] ) . '" target="_blank" rel="noopener">Open the page</a>'
			. dbd_pack_reddit( $it['title'], DBD_Pack::share_link( $it ) ) . '</div>'
			. '<p class="sp-fits">Fits: ' . esc_html( implode( ' · ', $it['fits'] ) ) . '</p></div>'
			. '<div class="sp-r">' . dbd_pack_field( 'Reddit title', $it['title'] ) . dbd_pack_field( 'Reddit comment, with the link', $it['comment'] ) . dbd_pack_field( 'X and Bluesky', $it['post'], true ) . '</div></div></div>';
	}
	if ( $keys ) {
		$i = DBD_Pack::NUM['keys'];
		echo '<div class="dbd-box sp-item" data-k="keys"><h2><span>' . (int) $i . '. Keys to the game, Week ' . (int) ( $n + 1 ) . '</span><small>Each team’s biggest edge against its next opponent, for its own subreddit. The image is the game’s card, which shows both sides.</small></h2><div class="sp-keys">';
		foreach ( $keys as $kg ) {
			$g = $kg['g']; $img = DBD_Card::url( $kg['card'] ); $nk = function ( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; };
			echo '<div class="sp-g"><div class="sp-gc"><img src="' . esc_url( $img ) . '" width="1200" height="630" alt="" loading="lazy"><a class="button" href="' . esc_url( $img ) . '" download="' . esc_attr( $kg['file'] ) . '">Download</a></div>'
				. '<div class="sp-gs"><b class="sp-gt"><a href="' . esc_url( $kg['link'] ) . '" target="_blank" rel="noopener">' . esc_html( $nk( $g['away'] ) . ' at ' . $nk( $g['home'] ) ) . '</a></b>';
			foreach ( $kg['sides'] as $sd ) {
				echo '<div class="sp-k"><div class="sp-kh">' . DBD_Marks::tile_svg( $sd['t'], 22 ) . '<b>' . esc_html( $nk( $sd['t'] ) . ' offense' ) . '</b><span>' . esc_html( $sd['fits'] ) . '</span></div>'
					. '<p><b>' . esc_html( rtrim( $sd['head'], '.' ) ) . '.</b> ' . esc_html( $sd['say'] ) . '</p><div class="sp-kb">'
					. '<button type="button" class="button sp-cp" data-t="' . esc_attr( $sd['title'] ) . '">Copy title</button>'
					. '<button type="button" class="button sp-cp" data-t="' . esc_attr( $sd['comment'] ) . '">Copy comment</button>'
					. '<button type="button" class="button sp-cp" data-t="' . esc_attr( $sd['post'] ) . '">Copy for X and Bluesky</button>' . dbd_pack_reddit( $sd['title'], $sd['link'] ) . '</div></div>';
			}
			echo '</div></div>';
		}
		echo '</div></div>';
	}
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
