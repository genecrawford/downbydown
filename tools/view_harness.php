<?php
/**
 * Render one plugin view outside WordPress, from the repo's data files, with a thin shim for the handful of
 * WordPress functions the plugin calls. Catches the fatal that a live page would hide behind "critical error".
 *   php tools/view_harness.php <data dir> <view> [arg]
 *   views: player <gsis id> | players | team <ABBR> | hub | game <game id> | teamplayers <ABBR> | matchups [N] | teams | compare <id,id> | myteam [id,id,…] | leaders [g or board key] | defense <ABBR> | week [N] | fantasy [N]
 *   DBD_HARNESS_ROOT=1: the app page is /hub/, not the front page, with paths at the root
 *   live blocks (class-dbd-blocks.php): block <type> ['k=v&k=v' attributes] | page <html file> (a theme page with its [dbd_block] shortcodes drawn)
 *   the weekly post (class-dbd-post.php, SCOPE §46): post <N> (DBD_HARNESS_AHEAD=0 leaves out next week, as a backfilled post does) | sitemap (every app URL)
 *   the Tuesday email (class-dbd-email.php, SCOPE §48): email <N> builds week N's email from its post, as Brevo would get it, into DBD_HARNESS_HTML
 *   admin <tab|all>: the admin page's tabs (2.11.0, SCOPE §50) drawn with stored settings and two week posts, into DBD_HARNESS_HTML
 *   send: the Tuesday email's send path against a fake Brevo (2.11.1): the campaign body, the one-day window, Try again and its guards
 *   joined: the "You're in" note's checks (2.10.2), with the post drawn outside the loop the way Divi's Theme Builder draws it
 *   team videos (class-dbd-videos.php, SCOPE §51): videos <fixture dir> matches the fixture channel feeds and the recap playlist and writes the stored
 *   lists to DBD_HARNESS_VIDEOS, which the teamplayers and game views then read
 *   team news (class-dbd-news.php, SCOPE §49): news <fixture dir> matches the fixture feeds and writes the stored lists to DBD_HARNESS_NEWS,
 *   which the team and teamplayers views then read; DBD_HARNESS_NOW pins the clock
 */
if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }
list( , $data, $view, $arg ) = $argv + array( null, 'data', 'player', '00-0036322' );
error_reporting( E_ALL ); ini_set( 'display_errors', 'stderr' );
$warnings = 0; set_error_handler( function ( $no, $str, $file, $line ) use ( &$warnings ) { $warnings++; fwrite( STDERR, "  warning: $str at " . basename( $file ) . ":$line\n" ); return true; } );

/* ---- a data folder laid out like uploads/down-by-down: a symlink to the repo's data dir */
$tmp = sys_get_temp_dir() . '/dbd-harness'; if ( ! is_dir( $tmp ) ) { mkdir( $tmp ); } $link = $tmp . '/down-by-down';
$want = realpath( $data ); if ( ! $want ) { fwrite( STDERR, "no data dir $data\n" ); exit( 2 ); }
if ( is_link( $link ) && readlink( $link ) !== $want ) { unlink( $link ); }  /* a run on another checkout left its link behind */
if ( ! file_exists( $link ) ) { symlink( $want, $link ); }

/* ---- WordPress shim: only what the plugin touches while rendering */
define( 'ABSPATH', __DIR__ . '/' ); define( 'DBD_VERSION', 'harness' ); define( 'DBD_PATH', realpath( __DIR__ . '/../plugin/down-by-down' ) . '/' ); define( 'DBD_URL', 'https://example.test/wp-content/plugins/down-by-down/' ); define( 'DBD_DEFAULT_SOURCE', '' ); define( 'DBD_FILE', DBD_PATH . 'down-by-down.php' );
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return (string) $s; }
function esc_js( $s ) { return (string) $s; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function trailingslashit( $s ) { return rtrim( $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || @mkdir( $d, 0777, true ); }
function do_shortcode( $s ) { return '<div class="wpforms-container">[form ' . htmlspecialchars( (string) $s, ENT_QUOTES ) . ']</div>'; }  /* the signup strip's form (SCOPE §66) */
function wp_upload_dir() { global $tmp; return array( 'basedir' => $tmp, 'baseurl' => 'https://example.test/uploads' ); }
function get_option( $k, $d = false ) { if ( isset( $GLOBALS['dbd_opt'] ) && array_key_exists( $k, $GLOBALS['dbd_opt'] ) ) { return $GLOBALS['dbd_opt'][ $k ]; }  /* the admin mode's stored settings */
	if ( $k === 'dbd_headshots' || $k === 'dbd_logos' ) { return (bool) getenv( 'DBD_HARNESS_MEDIA' ); }
	if ( getenv( 'DBD_HARNESS_PRETTY' ) ) { if ( $k === 'permalink_structure' ) { return '/%postname%/'; } if ( $k === 'dbd_app_page' ) { return 10; } if ( $k === 'page_on_front' ) { return getenv( 'DBD_HARNESS_ROOT' ) ? 5 : 10; } if ( $k === 'show_on_front' ) { return 'page'; } }  /* readable URLs (SCOPE §43) */
	$vk = array( 'dbd_highlights' => 'hl', 'dbd_previews' => 'pv', 'dbd_player_hl' => 'ph', 'dbd_defense_hl' => 'dh' );
	if ( ( strpos( $k, 'dbd_videos_' ) === 0 || isset( $vk[ $k ] ) ) && ( $vf = getenv( 'DBD_HARNESS_VIDEOS' ) ) && is_readable( $vf ) ) { $vj = json_decode( (string) file_get_contents( $vf ), true ); return isset( $vk[ $k ] ) ? ( $vj[ $vk[ $k ] ] ?? $d ) : ( $vj['videos'][ substr( $k, 11 ) ] ?? $d ); }  /* team videos (SCOPE §51) */
	if ( strpos( $k, 'dbd_news_' ) === 0 && ( $nf = getenv( 'DBD_HARNESS_NEWS' ) ) && is_readable( $nf ) ) { $nj = json_decode( (string) file_get_contents( $nf ), true ); return $nj[ substr( $k, 9 ) ] ?? $d; }  /* team news (SCOPE §49) */
	return $d; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function get_query_var( $k ) { return ''; }
function add_rewrite_rule() {} function flush_rewrite_rules() {}
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); }
function update_option( $k, $v, $al = null ) { if ( isset( $GLOBALS['dbd_opt'] ) ) { $GLOBALS['dbd_opt'][ $k ] = $v; } return true; }  /* stored only in the admin and send modes, which read it back */
function get_transient( $k ) { return $GLOBALS['htr'][ $k ] ?? false; }  /* kept only where a mode sets $GLOBALS['htr'] (autopub) */
function set_transient( $k, $v, $e = 0 ) { if ( isset( $GLOBALS['htr'] ) ) { $GLOBALS['htr'][ $k ] = $v; } return true; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_permalink( $p = null ) { return getenv( 'DBD_HARNESS_ROOT' ) ? 'https://example.test/hub/' : 'https://example.test/app/'; }
function is_front_page() { return false; } function add_shortcode() {}
function add_query_arg( $args, $url = '' ) { if ( ! is_array( $args ) ) { return $url; } $q = implode( '&', array_map( function ( $k, $v ) { return $k . '=' . $v; }, array_keys( $args ), $args ) ); return $url . ( $q ? ( strpos( $url, '?' ) === false ? '?' : '&' ) . $q : '' ); }  /* like WordPress: values go in as given (the plugin encodes them first) */
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function wp_date( $f, $ts = null, $tz = null ) { $d = new DateTime( '@' . ( $ts ?? time() ) ); $d->setTimezone( $tz ?: new DateTimeZone( 'UTC' ) ); return $d->format( $f ); }  /* UTC by default, like a site left on GMT: the page must pass Eastern itself */
function wp_timezone() { return new DateTimeZone( 'America/New_York' ); }
function status_header( $c ) {} function nocache_headers() {}
function add_action() {} function add_filter() {} function remove_action() {} function do_action() {} function apply_filters( $t, $v ) { return $v; }
function wp_enqueue_script() {} function wp_enqueue_style() {} function wp_localize_script() {} function wp_register_script() {} function wp_register_style() {}
function has_shortcode() { return true; } function is_singular() { return true; } function get_queried_object() { return null; } function is_admin() { return false; }
function selected() {} function checked() {}
function wp_generate_password( $n = 12 ) { return substr( bin2hex( random_bytes( 8 ) ), 0, $n ); }
function is_wp_error( $x ) { return $x === null; }
function wp_remote_get() { return null; } function wp_remote_retrieve_response_code( $r = null ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; } function wp_remote_retrieve_body( $r = null ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function __( $s ) { return $s; } function _e( $s ) { echo $s; }
function wp_schedule_single_event() {} function wp_next_scheduled() { return false; } function wp_schedule_event() {} function register_activation_hook() {} function register_deactivation_hook() {}

/* ---- the plugin, minus the bootstrap and admin. The SEO class extends a WordPress sitemap base class; an empty stand-in lets it load. */
if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) { class WP_Sitemaps_Provider { public $name = ''; public $object_type = ''; } }
foreach ( array( 'class-dbd-data', 'class-dbd-marks', 'class-dbd-grades', 'class-dbd-metrics', 'class-dbd-takeaways', 'class-dbd-lineup', 'class-dbd-fantasy', 'class-dbd-myteam', 'class-dbd-leaders', 'class-dbd-kicker', 'class-dbd-defense', 'class-dbd-scatter', 'class-dbd-rush', 'class-dbd-week', 'class-dbd-fanweek', 'class-dbd-playoffs', 'class-dbd-po-posts', 'class-dbd-throws', 'class-dbd-hub', 'class-dbd-compare', 'class-dbd-season', 'class-dbd-boards', 'class-dbd-sync', 'class-dbd-tall', 'class-dbd-card', 'class-dbd-urls', 'class-dbd-router', 'class-dbd-seo', 'class-dbd-blocks', 'class-dbd-post', 'class-dbd-news', 'class-dbd-email', 'class-dbd-videos', 'class-dbd-growth', 'class-dbd-signup', 'class-dbd-pack', 'class-dbd-pack-mail', 'class-dbd-instagram', 'class-dbd-bluesky', 'class-dbd-x', 'class-dbd-threads', 'class-dbd-autopub', 'class-dbd-board-mail' ) as $c ) { $f = DBD_PATH . 'includes/' . $c . '.php'; if ( is_readable( $f ) ) { require $f; } }

if ( getenv( 'DBD_HARNESS_SIGN' ) ) { $GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'harness', 'list' => 4, 'tpl' => 5 ) ); }  /* the email set up, so pages draw the signup strip (SCOPE §66) */
if ( getenv( 'DBD_HARNESS_NOW' ) && class_exists( 'DBD_News' ) ) { DBD_News::$now = (int) getenv( 'DBD_HARNESS_NOW' ); }

/* ---- team news (SCOPE §49): the fixture feeds through the matching step, with no network. A feed key's file is the key with ':' and spaces as '-';
   no file reads as an empty feed, a key in fail.txt as a failed fetch, and prev.json is the previous pass's stored lists. */
if ( $view === 'news' ) {
	$nd = rtrim( (string) ( $arg ?: 'tools/fixtures/news' ), '/' );
	$fail = is_readable( "$nd/fail.txt" ) ? array_filter( array_map( 'trim', file( "$nd/fail.txt" ) ) ) : array();
	$bodies = array();
	foreach ( DBD_News::feeds() as $k => $x ) { $fn = $nd . '/' . preg_replace( '/[: ]+/', '-', $k ) . '.xml'; $bodies[ $k ] = in_array( $k, $fail, true ) ? array( 'error' => 'HTTP 503' ) : ( is_readable( $fn ) ? (string) file_get_contents( $fn ) : '' ); }
	$prev = is_readable( "$nd/prev.json" ) ? json_decode( (string) file_get_contents( "$nd/prev.json" ), true ) : array();
	try { list( $stores, $status ) = DBD_News::collect( DBD_News::feeds(), $bodies, $prev ); }
	catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in news: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	$nout = getenv( 'DBD_HARNESS_NEWS' ) ?: $tmp . '/news.json'; file_put_contents( $nout, json_encode( $stores ) );
	echo json_encode( array( 'stores' => array_filter( $stores, function ( $x ) { return $x['t'] || $x['l']; } ), 'status' => array_filter( $status, function ( $x ) { return empty( $x['ok'] ) || ! empty( $x['n'] ); } ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( $warnings ? 1 : 0 );
}

/* ---- team videos and highlights (SCOPE §51): the fixture channel feeds and recap playlist through the matching, no network */
if ( $view === 'videos' ) {
	$vd = rtrim( (string) ( $arg ?: 'tools/fixtures/videos' ), '/' );
	$fail = is_readable( "$vd/fail.txt" ) ? array_filter( array_map( 'trim', file( "$vd/fail.txt" ) ) ) : array();
	$bodies = array();
	foreach ( DBD_Videos::feeds() as $k => $x ) { $fn = $vd . '/' . str_replace( ':', '-', $k ) . '.xml'; $bodies[ $k ] = in_array( $k, $fail, true ) ? array( 'error' => 'HTTP 503' ) : ( is_readable( $fn ) ? (string) file_get_contents( $fn ) : '' ); }
	$prev = is_readable( "$vd/prev.json" ) ? json_decode( (string) file_get_contents( "$vd/prev.json" ), true ) : array();
	try {
		list( $stores, $status ) = DBD_Videos::collect( DBD_Videos::feeds(), $bodies, $prev );
		$season = DBD_Data::season(); $L = DBD_Videos::playlists_all( (string) @file_get_contents( "$vd/playlists.html" ), $season ); $lists = $L['recap'];
		$pb = array();  /* a playlist without a file stands for one that failed */
		foreach ( array( 'recap' => 'pl', 'preview' => 'pv', 'player' => 'ph' ) as $kind => $pre ) { foreach ( $L[ $kind ] as $w => $pid ) { $pf = "$vd/pl-$pid.xml"; $pb[ $pre . ':' . $w ] = is_readable( $pf ) ? (string) file_get_contents( $pf ) : array( 'error' => 'HTTP 503' ); } }
		list( $hl, $found, $plerr ) = DBD_Videos::apply_recaps( $lists, $pb, array() );
		list( $pv, $pvN, $pverr ) = DBD_Videos::apply_previews( $L['preview'], $pb, array() );
		list( $ph, $dh, $phN, $pherr ) = DBD_Videos::apply_players( $L['player'], $pb, array(), array() );
		$miss_all = DBD_Videos::weeks_missing( array() ); $miss_some = DBD_Videos::weeks_missing( $hl );
	} catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in videos: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	$vout = getenv( 'DBD_HARNESS_VIDEOS' ) ?: $tmp . '/videos.json'; file_put_contents( $vout, json_encode( array( 'videos' => $stores, 'hl' => array( $season => $hl ), 'pv' => array( $season => $pv ), 'ph' => array( $season => $ph ), 'dh' => array( $season => $dh ) ) ) );
	echo json_encode( array( 'videos' => array_filter( $stores, function ( $x ) { return $x['v']; } ), 'status' => array_filter( $status, function ( $x ) { return empty( $x['ok'] ) || ! empty( $x['n'] ); } ), 'playlists' => $lists, 'highlights' => $hl, 'missing_first' => $miss_all, 'missing_after' => $miss_some, 'found' => $found, 'playlist_errors' => $plerr, 'all_playlists' => $L, 'previews' => $pv, 'players' => $ph, 'defense' => $dh, 'other_errors' => array_merge( $pverr, $pherr ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	exit( $warnings ? 1 : 0 );
}

/* ---- readable URLs (SCOPE §43): every old link to a path and back to the same route */
if ( $view === 'urls' ) {
	$hub = getenv( 'DBD_HARNESS_ROOT' ) ? 'https://example.test/hub/' : 'https://example.test/';
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, $hub );
	if ( ! DBD_Urls::on() ) { fwrite( STDERR, "readable URLs are off: set DBD_HARNESS_PRETTY=1\n" ); exit( 2 ); }
	$cases = array( array(), array( 'view' => 'players' ), array( 'view' => 'players', 'pos' => 'WR', 'pt' => 'MIN' ), array( 'view' => 'teams' ), array( 'view' => 'myteam' ), array( 'myteam' => '00-0036322,00-0036900' ), array( 'compare' => 'none' ),
		array( 'view' => 'leaders' ), array( 'view' => 'leaders', 'b' => 'wr.tsh' ), array( 'view' => 'matchups' ), array( 'view' => 'week' ), array( 'compare' => '00-0036322,00-0036900,00-0039075' ) );
	foreach ( array_keys( DBD_Leaders::GROUPS ) as $g ) { $cases[] = array( 'view' => 'leaders', 'g' => $g ); }
	foreach ( DBD_Data::weeks() as $w ) { $cases[] = array( 'view' => 'matchups', 'week' => $w ); }
	foreach ( DBD_Week::weeks() as $w ) { $cases[] = array( 'view' => 'week', 'week' => $w ); }
	if ( DBD_FanWeek::weeks() ) { $cases[] = array( 'view' => 'fantasy' ); } foreach ( DBD_FanWeek::weeks() as $w ) { $cases[] = array( 'view' => 'fantasy', 'week' => $w ); }  /* SCOPE §78 */
	foreach ( array_keys( DBD_Data::teams() ) as $t ) { $cases[] = array( 'team' => $t ); $cases[] = array( 'team' => $t, 'side' => 'def' ); $cases[] = array( 'view' => 'players', 'team' => $t ); }
	foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( $gl as $g ) { $cases[] = array( 'game' => $g['id'] ); $cases[] = array( 'game' => $g['id'], 'side' => 'home' ); } }
	foreach ( DBD_Data::player_index() as $p ) { $cases[] = array( 'player' => $p['id'] ); }
	$key = function ( $r ) { return json_encode( array( $r['view'], $r['team'] ?? '', $r['game']['id'] ?? '', $r['side'] ?? '', $r['tside'] ?? '', $r['week'] ?? '', $r['player']['id'] ?? '', array_map( function ( $p ) { return $p['id']; }, $r['compare'] ?? array() ), $r['lgroup'] ?? '', $r['lboard'] ?? '', $r['pteam'] ?? '', $r['pos'] ?? '', array_map( function ( $p ) { return $p['id']; }, $r['myteam'] ?? array() ) ) ); };
	$bad = 0; $paths = 0; $seen = array();
	foreach ( $cases as $args ) {
		$_GET = $args; $want = $key( DBD_Router::route() );
		$u = DBD_Router::url( $args ); $pq = parse_url( $u ); $path = substr( $pq['path'] ?? '/', 1 );
		if ( ! $args && $u !== $hub ) { $bad++; fwrite( STDERR, "the hub is $u, not $hub\n" ); }
		if ( $u === $hub ) { $path = ''; }  /* the hub is the app page itself */
		parse_str( $pq['query'] ?? '', $rest );
		if ( array_intersect( array_keys( $rest ), array( 'team', 'game', 'player', 'compare', 'view' ) ) ) { $bad++; fwrite( STDERR, "no path for " . json_encode( $args ) . ": $u\n" ); continue; }
		if ( isset( $seen[ $u ] ) && $seen[ $u ] !== $want ) { $bad++; fwrite( STDERR, "two routes share $u\n" ); }
		$seen[ $u ] = $want;
		$_GET = $rest; $got = $path === '' ? $key( DBD_Router::route() ) : $key( DBD_Router::route_path( $path ) ); $paths += $path !== '';
		if ( $got !== $want ) { $bad++; fwrite( STDERR, "round trip differs for " . json_encode( $args ) . " via $u\n  want $want\n  got  $got\n" ); }
	}
	/* archived seasons (SCOPE §44): the same round trip with the season in every link, and the switcher's same-page links */
	foreach ( DBD_Data::archived_seasons() as $as ) {
		DBD_Data::use_season( $as ); $ac = array( array(), array( 'view' => 'leaders', 'g' => 'rb' ), array( 'view' => 'teams' ), array( 'view' => 'players' ), array( 'view' => 'matchups' ) );
		foreach ( array_keys( DBD_Data::teams() ) as $t ) { $ac[] = array( 'team' => $t ); $ac[] = array( 'team' => $t, 'side' => 'def' ); }
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( $gl as $g ) { $ac[] = array( 'game' => $g['id'] ); } }
		foreach ( array_slice( DBD_Data::player_index(), 0, 150 ) as $p ) { $ac[] = array( 'player' => $p['id'] ); }
		foreach ( DBD_Week::weeks() as $w ) { $ac[] = array( 'view' => 'week', 'week' => $w ); }
		$an = 0;
		foreach ( $ac as $args ) {
			DBD_Data::use_season( $as ); $_GET = $args; $want = $key( DBD_Router::route() ); $u = DBD_Router::url( $args ); $pq = parse_url( $u ); parse_str( $pq['query'] ?? '', $rest );
			if ( ! isset( $args['game'] ) && strpos( (string) ( $pq['path'] ?? '' ), '/' . $as . '/' ) !== 0 ) { $bad++; fwrite( STDERR, "$as link without its season: " . json_encode( $args ) . " -> $u\n" ); continue; }
			$_GET = $rest; $got = $key( DBD_Router::route_path( substr( $pq['path'], 1 ) ) ); $an++;
			if ( $got !== $want || DBD_Data::season() !== $as ) { $bad++; fwrite( STDERR, "$as round trip differs for " . json_encode( $args ) . " via $u\n  want $want\n  got  $got\n" ); }
		}
		DBD_Data::use_season( $as ); $_GET = array( 'team' => 'MIN' ); DBD_Router::$route = DBD_Router::route();
		echo "  $as: $an archived links round-trip; switcher from its Vikings page: " . DBD_Router::season_url( DBD_Data::current_season() ) . "\n";
		DBD_Data::use_season( null ); $_GET = array( 'team' => 'MIN' ); DBD_Router::$route = DBD_Router::route();
		echo "  " . DBD_Data::current_season() . " Vikings page to $as: " . DBD_Router::season_url( $as ) . "\n";
	}
	DBD_Data::use_season( null );
	foreach ( array( 'team/zzz/', 'game/1999-week-1-a-at-b/', 'player/nobody-at-all/', 'week/x/', 'teams/extra/', '1999/team/min/', 'fantasy/week/99/', 'fantasy/x/' ) as $odd ) { $_GET = array(); $r = DBD_Router::route_path( $odd ); echo "  $odd -> " . $r['view'] . ( $r['view'] === 'players' ? ' (search "' . $r['q'] . '")' : '' ) . "\n"; }
	echo "urls" . ( getenv( 'DBD_HARNESS_ROOT' ) ? ' (app on /hub/, paths at the root)' : '' ) . ": " . count( $cases ) . " links, $paths paths, $bad problems, $warnings warnings\n";
	foreach ( array( array( 'player' => '00-0036322' ), array( 'game' => '2026_08_MIN_DET', 'side' => 'home' ), array( 'team' => 'MIN', 'side' => 'def' ), array( 'view' => 'players', 'team' => 'MIN' ), array( 'view' => 'leaders', 'g' => 'rb' ), array( 'compare' => '00-0036322,00-0036900' ), array( 'view' => 'players', 'pt' => 'MIN', 'q' => 'jeff' ) ) as $ex ) { echo '  ' . json_encode( $ex ) . ' -> ' . DBD_Router::url( $ex ) . "\n"; }
	exit( $bad || $warnings ? 1 : 0 );
}

/* ---- an archived season (SCOPE §44): DBD_HARNESS_SEASON=2025 shows that season for the card and view modes */
if ( getenv( 'DBD_HARNESS_SEASON' ) ) { if ( ! DBD_Data::use_season( (int) getenv( 'DBD_HARNESS_SEASON' ) ) ) { fwrite( STDERR, 'no archived season ' . getenv( 'DBD_HARNESS_SEASON' ) . "\n" ); exit( 2 ); } }

/* ---- a share card (SCOPE §26, §43): php tools/view_harness.php data card <key> <out.png>. Logos and photos only with DBD_HARNESS_LOGOS. */
DBD_Tall::$off = $view !== 'tall';  /* the modes below test the text-and-card fallback; 'tall' and the end of 'autopub' test the graphics (SCOPE §85) */
if ( $view === 'card' ) {
	$out = $argv[4] ?? 'card.png';
	if ( ! DBD_Card::available() ) { fwrite( STDERR, "GD with FreeType is not available\n" ); exit( 3 ); }
	$fetch = getenv( 'DBD_HARNESS_LOGOS' ) ? function ( $u ) { $b = @file_get_contents( $u, false, stream_context_create( array( 'http' => array( 'timeout' => 10 ) ) ) ); return $b ?: null; } : null;
	$t0 = microtime( true ); $im = DBD_Card::render( $arg, $fetch );
	if ( ! $im ) { fwrite( STDERR, "render( $arg ) returned nothing\n" ); exit( 4 ); }
	imagepng( $im, $out, 6 ); imagedestroy( $im ); $sz = getimagesize( $out );
	echo "card $arg: {$sz[0]}x{$sz[1]}, " . round( microtime( true ) - $t0, 2 ) . "s, $warnings warnings -> $out\n";
	$want = strpos( (string) $arg, 'tall-' ) === 0 ? array( 1080, 1350 ) : ( strpos( (string) $arg, 'ig-' ) === 0 ? array( 1080, 1080 ) : array( 1200, 630 ) );  /* the Instagram squares (SCOPE §73) */
	exit( $sz[0] === $want[0] && $sz[1] === $want[1] && ! $warnings ? 0 : 5 );
}

/* ---- an old link's redirect (SCOPE §43): php tools/view_harness.php data redirect '/?player=…&utm_source=x' prints "redirect 301 <to>" or "stays" */
if ( $view === 'redirect' ) {
	function wp_safe_redirect( $to, $status = 302 ) { echo "redirect $status $to\n"; return true; }
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	$u = parse_url( $arg ); parse_str( $u['query'] ?? '', $_GET ); $_SERVER['REQUEST_URI'] = $arg; $_SERVER['REQUEST_METHOD'] = 'GET';
	$ap = (string) ( $u['path'] ?? '/' );
	if ( getenv( 'DBD_HARNESS_ROOT' ) && $ap !== '/' ) {  /* a path request on the app page: /hub/team/min/ forwards to the root */
		$rp->setValue( null, 'https://example.test/hub/' );
		DBD_Router::$route = DBD_Router::route_path( preg_replace( '#^/hub/#', '', $ap ) ); DBD_Router::redirect_prefixed();  /* route_path() applies a /2025/ season itself */
	} elseif ( getenv( 'DBD_HARNESS_ROOT' ) ) { $rp->setValue( null, '' ); DBD_Router::front(); }  /* the front page answers old links */
	else { DBD_Router::$route = DBD_Router::route(); DBD_Router::redirect_old(); }
	echo "stays ($warnings warnings)\n"; exit( $warnings ? 1 : 0 );
}

/* ---- the weekly post: week N composed as the plugin would write it, inside a stand-in for Divi's post column */
/* the swing card (SCOPE §66): DBD_HARNESS_SWING stands in a drawn card, so the post and email show it */
if ( ( $view === 'post' || $view === 'email' ) && getenv( 'DBD_HARNESS_SWING' ) ) { $sw = DBD_Week::file( (int) $arg )['swings'][0]['g'] ?? ''; list( $swp ) = DBD_Post::swing_file( (int) $arg, $sw ); if ( $swp !== '' ) { wp_mkdir_p( dirname( $swp ) ); file_put_contents( $swp, 'png' ); } }
if ( $view === 'post' ) {
	try { $doc = DBD_Post::compose( (int) $arg, null, getenv( 'DBD_HARNESS_AHEAD' ) !== '0' ); }
	catch ( Throwable $e ) { fwrite( STDERR, "FATAL in post $arg: " . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	if ( ! $doc ) { echo "post $arg: none (the week isn't complete), $warnings warnings\n"; exit( $warnings ? 1 : 0 ); }
	if ( getenv( 'DBD_HARNESS_OLD_SEASON' ) ) { $doc['html'] = DBD_Post::with_season( $doc['html'] . '<p class="gene">An added link: <a href="https://example.test/about/">About</a></p>', (int) getenv( 'DBD_HARNESS_OLD_SEASON' ) ); }  /* how the post reads once its season is archived */
	echo "post $arg: " . $doc['title'] . ' · ' . strlen( $doc['html'] ) . " bytes, $warnings warnings\n";
	if ( strlen( $doc['html'] ) < 3000 || $doc['excerpt'] === '' ) { fwrite( STDERR, "post $arg: suspiciously short\n" ); exit( 3 ); }
	$dir = getenv( 'DBD_HARNESS_HTML' );
	if ( $dir ) {
		if ( ! is_dir( $dir . '/assets' ) ) { mkdir( $dir . '/assets', 0777, true ); } copy( DBD_PATH . 'assets/post.css', $dir . '/assets/post.css' );
		$fonts = '<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">';
		$divi = '<style>body{margin:0;background:#fff;font:400 16px/1.7 "Open Sans",Arial,sans-serif;color:#666}.entry-content tr td{padding:6px 24px;border-top:1px solid #eee}a{color:#2ea3f2}h1{color:#333;font-size:30px;padding-bottom:10px}p{padding-bottom:1em}article{max-width:760px;margin:0 auto;padding:48px 20px}</style>';  /* stand-ins for Divi's post rules, which the post must hold up against */
		file_put_contents( $dir . '/post-w' . (int) $arg . '.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html( $doc['title'] ) . '</title>' . $fonts . $divi . '<link rel="stylesheet" href="assets/post.css"></head><body><article class="entry-content"><h1>' . esc_html( $doc['title'] ) . '</h1>' . $doc['html'] . '</article></body></html>' );
	}
	exit( $warnings ? 1 : 0 );
}
/* ---- the Tuesday email (SCOPE §48): week N's post as the email Brevo sends, and the send-window rule */
if ( $view === 'email' ) {
	try {
		$doc = DBD_Post::compose( (int) $arg, null, getenv( 'DBD_HARNESS_AHEAD' ) !== '0' );
		if ( ! $doc ) { echo "email $arg: none (the week isn't complete), $warnings warnings\n"; exit( $warnings ? 1 : 0 ); }
		$hlm = array(); foreach ( DBD_Data::week_games( (int) $arg ) as $g ) { $r = DBD_Videos::recap( $g ); if ( $r ) { $hlm[ DBD_Router::url( array( 'game' => $g['id'] ) ) ] = DBD_Videos::url( $r ); } }  /* SCOPE §54, with DBD_HARNESS_VIDEOS */
		$m = DBD_Email::compose( $doc['title'], $doc['html'], 'https://example.test/week-' . (int) $arg . '-in-review/', 'https://example.test/uploads/week-' . (int) $arg . '.png', 'Tuesday, November 3', (int) $arg, null, $hlm );
	} catch ( Throwable $e ) { fwrite( STDERR, "FATAL in email $arg: " . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	$late = array( DBD_Email::late( '2026-11-03 13:00:00', strtotime( '2026-11-03 13:00:05 UTC' ) ), DBD_Email::late( '2026-11-03 13:00:00', strtotime( '2026-11-04 12:59:00 UTC' ) ), DBD_Email::late( '2026-11-03 13:00:00', strtotime( '2026-11-04 13:01:00 UTC' ) ), DBD_Email::late( '2025-11-04 13:00:00', strtotime( '2026-11-03 13:00:00 UTC' ) ) );
	echo "email $arg: " . $m['subject'] . ' · ' . strlen( $m['html'] ) . ' bytes · preview "' . $m['pre'] . '" · late at 5s/23h59/24h01/a year: ' . implode( '/', array_map( function ( $x ) { return $x ? 'yes' : 'no'; }, $late ) ) . ", $warnings warnings\n";
	if ( ( $dir = getenv( 'DBD_HARNESS_HTML' ) ) ) { if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); } file_put_contents( $dir . '/email-w' . (int) $arg . '.html', $m['html'] ); file_put_contents( $dir . '/confirm.html', DBD_Email::confirm_html() ); }
	exit( $warnings || $late !== array( false, false, true, true ) ? 1 : 0 );
}
/* ---- the send path (2.11.1): DBD_Email::send() and again() against a fake Brevo that records each call */
if ( $view === 'send' ) {
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'xkeysib-test', 'list' => 3, 'tpl' => 5 ) );
	$GLOBALS['hmeta'] = array(); $GLOBALS['hcalls'] = array(); $GLOBALS['brevo'] = array( 'create' => 201 ); $GLOBALS['hmail'] = array();
	$wk = ctype_digit( (string) $arg ) ? (int) $arg : 8; $doc = DBD_Post::compose( $wk, null, false ); if ( ! $doc ) { fwrite( STDERR, "no week $wk\n" ); exit( 2 ); }
	$mk = function ( $id, $days, $state ) use ( $doc, $wk ) {
		$GLOBALS['hposts'][ $id ] = (object) array( 'ID' => $id, 'post_title' => $doc['title'], 'post_content' => $doc['html'], 'post_status' => 'publish', 'post_type' => 'post', 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - (int) ( $days * 86400 ) ) );
		$GLOBALS['hmeta'][ $id ] = array( '_dbd_week' => $wk, '_dbd_season' => (int) DBD_Data::current_season(), '_dbd_email_state' => $state );
	};
	foreach ( array(
		'get_post' => function ( $id ) { return $GLOBALS['hposts'][ (int) $id ] ?? null; },
		'get_post_status' => function ( $id ) { return $GLOBALS['hposts'][ (int) $id ]->post_status ?? false; },
		'get_post_meta' => function ( $id, $k = '', $one = false ) { return $GLOBALS['hmeta'][ (int) $id ][ $k ] ?? ''; },
		'update_post_meta' => function ( $id, $k, $v ) { $GLOBALS['hmeta'][ (int) $id ][ $k ] = $v; return true; },
		'add_option' => function ( $k, $v = '' ) { if ( isset( $GLOBALS['dbd_opt'][ $k ] ) ) { return false; } $GLOBALS['dbd_opt'][ $k ] = $v; return true; },
		'delete_option' => function ( $k ) { unset( $GLOBALS['dbd_opt'][ $k ] ); return true; },
		'wp_remote_request' => function ( $url, $a ) {
			$path = substr( $url, strlen( DBD_Email::API ) ); $m = $a['method']; $GLOBALS['hcalls'][] = array( $m, $path, isset( $a['body'] ) ? json_decode( $a['body'], true ) : null );
			if ( $m === 'POST' && $path === 'emailCampaigns' ) { $c = $GLOBALS['brevo']['create']; return array( 'response' => array( 'code' => $c ), 'body' => $c === 201 ? '{"id":7}' : '{"code":"method_not_allowed","message":"You are not allowed to avail tag option for your campaign"}' ); }
			if ( preg_match( '#^emailCampaigns/\d+/sendNow$#', $path ) ) { return array( 'response' => array( 'code' => 204 ), 'body' => '' ); }
			if ( strpos( $path, 'contacts/lists/' ) === 0 ) { return array( 'response' => array( 'code' => 200 ), 'body' => '{"uniqueSubscribers":198}' ); }
			return array( 'response' => array( 'code' => 404 ), 'body' => '{"message":"not faked"}' );
		},
		'get_the_post_thumbnail_url' => function () { return 'https://example.test/card.png'; },
		'wpautop' => function ( $s ) { return $s; },
		'wp_mail' => function ( $to, $sub ) { $GLOBALS['hmail'][] = $sub; return true; },
		'is_email' => function ( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); },
		'admin_url' => function ( $p = '' ) { return 'https://example.test/wp-admin/' . $p; },
	) as $fn => $body ) { if ( ! function_exists( $fn ) ) { $GLOBALS['hfn'][ $fn ] = $body; eval( 'function ' . $fn . '( ...$a ) { return $GLOBALS["hfn"]["' . $fn . '"]( ...$a ); }' ); } }
	$st = function ( $id ) { return (string) ( $GLOBALS['hmeta'][ $id ]['_dbd_email_state'] ?? '' ); };
	$bad = 0; $check = function ( $name, $ok, $got = '' ) use ( &$bad ) { echo '  ' . ( $ok ? 'ok' : 'WRONG' ) . ": $name" . ( $ok ? '' : " (got: $got)" ) . "\n"; $bad += ! $ok; };
	try {
		/* on time: the campaign is made without a tag and sent */
		$mk( 1, 0.1, 'queued' ); DBD_Email::send( 1 );
		$create = array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) { return $c[0] === 'POST' && $c[1] === 'emailCampaigns'; } ) )[0][2] ?? array();
		$check( 'an on-time post sends', $st( 1 ) === 'sent', $st( 1 ) );
		$check( 'the campaign has no tag (Brevo free plan)', ! array_key_exists( 'tag', $create ), implode( ',', array_keys( $create ) ) );
		$check( 'the campaign goes to the list, with the unsubscribe link', ( $create['recipients']['listIds'] ?? null ) === array( 3 ) && strpos( (string) ( $create['htmlContent'] ?? '' ), '{{ unsubscribe }}' ) !== false );
		$check( 'the campaign is stored on the post', (int) ( $GLOBALS['hmeta'][1]['_dbd_email_campaign'] ?? 0 ) === 7 );
		$check( 'the send log counts the list', ( $GLOBALS['dbd_opt']['dbd_email_log'][0]['n'] ?? 0 ) === 198 );
		/* late without Try again: never sends */
		$mk( 2, 2, 'queued' ); DBD_Email::send( 2 );
		$check( 'a post two days late does not send on its own', strpos( $st( 2 ), 'not sent: it was more than a day late' ) === 0, $st( 2 ) );
		/* Try again on a failed late post: sends */
		$mk( 3, 1.5, 'failed: Brevo answered 405' ); $r = DBD_Email::again( 3 );
		$check( 'Try again sends a failed post a day and a half late', $st( 3 ) === 'sent' && $r[0] === 'ok', $st( 3 ) . ' / ' . $r[1] );
		/* Brevo still refusing: retrying, the tries counted */
		$GLOBALS['brevo']['create'] = 405; $mk( 4, 1.5, 'failed: x' ); $r = DBD_Email::again( 4 );
		$check( 'Try again that Brevo refuses goes back to retrying', strpos( $st( 4 ), 'retrying: Brevo answered 405' ) === 0 && (int) $GLOBALS['hmeta'][4]['_dbd_email_tries'] === 1 && $r[0] === 'error', $st( 4 ) );
		$GLOBALS['brevo']['create'] = 201;
		/* the guards: not set up, a send running, not a failed post */
		$mk( 5, 1.5, 'failed: x' ); $GLOBALS['dbd_opt']['dbd_email']['key'] = ''; $r = DBD_Email::again( 5 ); $GLOBALS['dbd_opt']['dbd_email']['key'] = 'xkeysib-test';
		$check( 'Try again before Brevo is set up changes nothing', $st( 5 ) === 'failed: x' && DBD_Email::failed( 5 ), $st( 5 ) . ' / ' . $r[1] );
		$mk( 6, 1.5, 'failed: x' ); $GLOBALS['dbd_opt']['dbd_email_lock_6'] = time(); $r = DBD_Email::again( 6 ); unset( $GLOBALS['dbd_opt']['dbd_email_lock_6'] );
		$check( 'Try again while a send is finishing changes nothing', $st( 6 ) === 'failed: x', $st( 6 ) . ' / ' . $r[1] );
		$mk( 7, 0.1, 'sent' ); $n = count( $GLOBALS['hcalls'] ); $r = DBD_Email::again( 7 );
		$check( 'Try again on a sent post does nothing', $st( 7 ) === 'sent' && count( $GLOBALS['hcalls'] ) === $n, $st( 7 ) );
		/* the last automatic try fails: failed, and Gene is told */
		$GLOBALS['brevo']['create'] = 405; $mk( 8, 0.1, 'retrying: x' ); $GLOBALS['hmeta'][8]['_dbd_email_tries'] = DBD_Email::TRIES - 1; DBD_Email::send( 8 );
		$check( 'the seventh refusal is final and emails the owner', strpos( $st( 8 ), 'failed: ' ) === 0 && count( $GLOBALS['hmail'] ) === 1 && DBD_Email::failed( 8 ), $st( 8 ) );
	} catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in send: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	echo "send: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
/* ---- the signup strip and board emails (2.26.0, SCOPE §66) */
if ( $view === 'signup' ) {
	$fails = 0; $t = function ( $what, $ok, $got = '' ) use ( &$fails ) { if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $what . ( $ok ? '' : ': ' . $got ) . "\n"; };
	$at = function ( $d ) { return strtotime( $d . ' 15:00 UTC' ); };
	foreach ( array( '2026-02-14' => 'The week in review|every Tuesday', '2026-02-15' => 'The combine and draft boards', '2026-04-30' => 'The combine and draft boards', '2026-05-01' => 'The week in review|every Tuesday from September', '2026-08-31' => 'The week in review|every Tuesday from September', '2026-09-01' => 'The week in review|every Tuesday', '2026-11-15' => 'The week in review|every Tuesday' ) as $d => $want ) {
		$L = DBD_Signup::line( $at( $d ) ); $got = $L[0] . '|' . $L[1];
		$t( "the line on $d", strpos( $got, $want ) === 0 && ( strpos( $want, '|' ) === false || $got === $want ), $got );
	}
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => false, 'key' => 'k', 'list' => 4 ) );
	$t( 'no strip while the email is off', DBD_Signup::strip( 'game' ) === '' );
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'k', 'list' => 4, 'tpl' => 0 ) );
	$t( 'no strip without the confirmation template', DBD_Signup::strip( 'game' ) === '' );
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'k', 'list' => 4, 'tpl' => 5 ), 'dbd_post_form' => '' );
	$t( 'no strip without a form', DBD_Signup::strip( 'game' ) === '' );
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'k', 'list' => 4, 'tpl' => 5 ) );
	$h = DBD_Signup::strip( 'draft' );
	$t( 'the strip: id, type, the form, the on-the-list line', strpos( $h, 'id="dbd-sign" data-type="draft"' ) !== false && strpos( $h, '[form [wpforms id=&quot;63&quot; title=&quot;false&quot;]]' ) !== false && strpos( $h, 'class="dbd-sg-on"' ) !== false, $h );
	/* board emails: the decision, step by step */
	$s = array( 'sent' => array(), 'seen' => array(), 'jobs' => array() ); $D = 86400; $now = 1800000000;
	list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'combine', 2026, 'h1', 319, $now ); $t( 'first look: 2026 is old news', ! $ok && $s['sent']['combine'] === 2026 && ! $s['seen'] );
	list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'combine', 2026, 'h1', 319, $now + 9 * $D ); $t( 'the same year again: nothing', ! $ok );
	list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'combine', 2027, 'a', 150, $now ); $t( '2027 arrives: noted, not yet', ! $ok && $s['seen']['combine'] === array( 2027, 'a', $now ) );
	list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'combine', 2027, 'b', 320, $now + $D ); $t( 'it changed: the clock restarts', ! $ok && $s['seen']['combine'][2] === $now + $D );
	list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'combine', 2027, 'b', 320, $now + 2 * $D ); $t( 'a day unchanged: not yet', ! $ok );
	list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'combine', 2027, 'b', 320, $now + 3 * $D ); $t( 'two days unchanged: ready', $ok );
	list( , $ok ) = DBD_Board_Mail::decide( $s, 'draft', 2027, 'd', 120, $now ); $t( 'a draft board noted first', ! $ok );
	$s['sent']['draft'] = 2026; list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'draft', 2027, 'd', 120, $now ); list( $s, $ok ) = DBD_Board_Mail::decide( $s, 'draft', 2027, 'd', 120, $now + 5 * $D );
	$t( 'a draft still filling in (120 picks): not yet', ! $ok );
	$s['jobs']['combine-2027'] = array( 'state' => 'sent' ); list( , $ok ) = DBD_Board_Mail::decide( $s, 'combine', 2027, 'b', 320, $now + 9 * $D ); $t( 'already sent: never twice', ! $ok );
	$nt = DBD_Board_Mail::next_tuesday( strtotime( '2027-03-04 12:00 UTC' ) ); $d = new DateTime( '@' . $nt ); $d->setTimezone( new DateTimeZone( 'America/New_York' ) );
	$t( 'the next Tuesday, 8:00 AM Eastern', $d->format( 'D Y-m-d H:i' ) === 'Tue 2027-03-09 08:00', $d->format( 'D Y-m-d H:i' ) );
	/* the two emails, from this year's boards */
	$dir = getenv( 'DBD_HARNESS_HTML' );
	foreach ( array( 'combine' => array( 'The 2026 combine results', 'Fastest 40', 'The top ten by combine score', 'See every drill' ), 'draft' => array( 'The 2026 draft, every pick', 'First pick', 'The first ten picks', 'See every pick' ) ) as $k => $want ) {
		$y = DBD_Boards::years( $k )[0] ?? 0; $m = $y ? DBD_Board_Mail::build( $k, $y, 'Tuesday, March 3', 'https://example.test/uploads/board.png' ) : null;
		if ( ! $m ) { $t( "the $k email", false, 'no board' ); continue; }
		$h = $m['html']; $u = esc_url( DBD_Router::url( array( 'view' => $k, 'y' => $y ) ) );
		$miss = array_filter( $want, function ( $w ) use ( $h ) { return strpos( $h, $w ) === false; } );
		$t( "the $k email: its pieces", ! $miss, implode( ' | ', $miss ) );
		$t( "the $k email: ten rows", substr_count( $h, '<td width="30"' ) === 10, substr_count( $h, '<td width="30"' ) );
		$t( "the $k email: the button and View in browser open the board, the forward line its strip", strpos( $h, 'href="' . $u . '"' ) !== false && strpos( $h, '>View in browser</a>' ) !== false && strpos( $h, 'href="' . $u . '#dbd-sign"' ) !== false && strpos( $h, 'Know someone who’d like this?' ) !== false, $u );
		$t( "the $k email: preview text", $m['pre'] !== '' && strpos( $m['pre'], '.' ) !== false, $m['pre'] );
		if ( $dir ) { if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); } file_put_contents( "$dir/board-$k-$y.html", $h ); }
	}
	echo "signup: $fails failed\n"; exit( $fails ? 1 : 0 );
}

/* ---- Growth (2.25.0, SCOPE §65): the list-size change from the saved daily sizes, and the last email's line */
if ( $view === 'growth' ) {
	$fails = 0; $t = function ( $what, $got, $want ) use ( &$fails ) { $ok = $got === $want; if ( ! $ok ) { $fails++; } echo ( $ok ? '  ok   ' : '  FAIL ' ) . $what . ': ' . json_encode( $got ) . ( $ok ? '' : ' (want ' . json_encode( $want ) . ')' ) . "\n"; };
	$GLOBALS['dbd_opt'] = array();
	$t( 'nothing saved', DBD_Growth::size_change( 10, 28, '2026-11-15', 4 ), null );
	DBD_Growth::save_size( '2026-11-15', 10, 4 );
	$t( 'only today saved', DBD_Growth::size_change( 10, 28, '2026-11-15', 4 ), null );
	DBD_Growth::save_size( '2026-11-10', 4, 4 ); DBD_Growth::save_size( '2026-11-12', 6, 4 );
	$t( 'younger than 28 days: from the oldest', DBD_Growth::size_change( 10, 28, '2026-11-15', 4 ), array( 6, '2026-11-10' ) );
	DBD_Growth::save_size( '2026-10-01', 1, 4 ); DBD_Growth::save_size( '2026-10-17', 2, 4 ); DBD_Growth::save_size( '2026-10-19', 3, 4 );
	$t( 'the newest day at least 28 back', DBD_Growth::size_change( 10, 28, '2026-11-15', 4 ), array( 8, '2026-10-17' ) );
	DBD_Growth::save_size( '2026-11-10', 4, 4 ); $t( 'the same size saves nothing new', count( $GLOBALS['dbd_opt'][ DBD_Growth::OPT_SIZES ]['days'] ), 6 );
	for ( $i = 0; $i < 120; $i++ ) { DBD_Growth::save_size( gmdate( 'Y-m-d', strtotime( '2026-01-01 +' . $i . ' days' ) ), $i, 4 ); }
	$S = $GLOBALS['dbd_opt'][ DBD_Growth::OPT_SIZES ]['days']; $t( 'keeps the newest 90', array( count( $S ), array_key_last( $S ) ), array( 90, '2026-11-15' ) );
	$t( 'another list has no record yet', DBD_Growth::size_change( 500, 28, '2026-11-15', 7 ), null );
	DBD_Growth::save_size( '2026-11-15', 500, 7 );
	$t( 'switching lists starts over', array( $GLOBALS['dbd_opt'][ DBD_Growth::OPT_SIZES ]['list'], count( $GLOBALS['dbd_opt'][ DBD_Growth::OPT_SIZES ]['days'] ), DBD_Growth::size_change( 500, 28, '2026-11-16', 7 ) ), array( 7, 1, array( 0, '2026-11-15' ) ) );
	if ( ! function_exists( 'dbd_admin_when' ) ) { function dbd_admin_when( $ts, $f = 'M j, g:i A' ) { return gmdate( $f, (int) $ts ); } }
	$row = array( 'title' => 'Week 9 in review', 'at' => 1794409200, 'n' => 139, 'camp' => 2, 'result' => 'sent' );
	$t( 'email line with stats', DBD_Growth::email_line( $row, array( 'delivered' => 139, 'uniqueViews' => 81, 'uniqueClicks' => 29 ) ), 'Week 9 in review, Nov 11: delivered to 139, 58% opened, 21% clicked' );
	$t( 'email line without stats', DBD_Growth::email_line( $row, null ), 'Week 9 in review, Nov 11: sent to 139' );
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'key' => '', 'list' => 4 ) );
	$t( 'no Brevo key: no call', DBD_Growth::list_size(), array( null, 'Brevo isn’t set up' ) );
	echo "growth: $fails failed\n"; exit( $fails ? 1 : 0 );
}

/* ---- the admin page (2.11.0, SCOPE §50): every tab drawn with stored settings, a sync that failed, a feed to check, an email sent and two week posts */
/* ---- the share pack (2.27.0, SCOPE §68): the newest pack's items and keys, the X length, the play cleanup, the mover picks, both card specs */
/* ---- Instagram posts itself (2.34.0, SCOPE §76): the week's slots, the captions, the booking, posting a carousel and a single image through a fake
   Instagram API (no double post after a lost reply), retries and the note, the token's connect and renewal, the JPEG squares */
if ( $view === 'igpost' ) {
	$now0 = time();
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'k', 'list' => 3, 'tpl' => 5, 'test' => 'gene@example.test' ),
		'dbd_ig_token' => array( 'token' => 'tok', 'user_id' => '178', 'username' => 'down_by_down', 'expires' => $now0 + 50 * 86400, 'checked' => $now0, 'err' => '' ) );
	$GLOBALS['hcalls'] = array(); $GLOBALS['hmail'] = array(); $GLOBALS['ig'] = array( 'status' => array(), 'failpub' => false, 'n' => 0, 'refresh' => 'ok', 'fbcheck' => 'ok' );
	foreach ( array(
		'is_email' => function ( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); },
		'admin_url' => function ( $p = '' ) { return 'https://example.test/wp-admin/' . $p; },
		'add_option' => function ( $k, $v = '' ) { if ( isset( $GLOBALS['dbd_opt'][ $k ] ) ) { return false; } $GLOBALS['dbd_opt'][ $k ] = $v; return true; },
		'delete_option' => function ( $k ) { unset( $GLOBALS['dbd_opt'][ $k ] ); return true; },
		'wp_mail' => function ( $to, $sub ) { $GLOBALS['hmail'][] = $sub; return true; },
		'wp_clear_scheduled_hook' => function () { return 0; },
		'wp_remote_request' => function ( $url, $a ) {
			$u = parse_url( $url ); parse_str( (string) ( $u['query'] ?? '' ), $q ); $b = (array) ( $a['body'] ?? array() ); $path = preg_replace( '#^/(v\d+\.\d+/)?#', '', $u['path'] );
			$GLOBALS['hcalls'][] = array( $a['method'], $path, $a['method'] === 'GET' ? $q : $b, (string) ( $u['host'] ?? '' ) );
			$ok = function ( $j ) { return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $j ) ); };
			$no = function ( $m ) { return array( 'response' => array( 'code' => 400 ), 'body' => json_encode( array( 'error' => array( 'message' => $m ) ) ) ); };
			$tok = $a['method'] === 'GET' ? ( $q['access_token'] ?? '' ) : ( $b['access_token'] ?? '' );
			if ( ( $q['fields'] ?? '' ) === 'id,username' ) { return ( $tok === 'EAAbad' || $GLOBALS['ig']['fbcheck'] === 'no' ) ? $no( 'Error validating access token: the session has been invalidated' ) : ( $path === '404' ? $no( 'Unsupported get request' ) : $ok( array( 'id' => $path, 'username' => 'down_by_down' ) ) ); }  /* Unified login's account check (2.34.1) */
			if ( $path === 'me' ) { return $tok === 'bad' ? $no( 'Invalid OAuth access token' ) : $ok( array( 'user_id' => '178', 'username' => 'down_by_down', 'id' => '99' ) ); }
			if ( $path === 'refresh_access_token' ) { return $GLOBALS['ig']['refresh'] === 'ok' ? $ok( array( 'access_token' => 'tok2', 'token_type' => 'bearer', 'expires_in' => 5184000 ) ) : $no( 'Session has expired' ); }
			if ( $path === '178/media' ) { $GLOBALS['ig']['n']++; return $ok( array( 'id' => ( ( $b['media_type'] ?? '' ) === 'CAROUSEL' ? 'car' : 'c' ) . $GLOBALS['ig']['n'] ) ); }
			if ( $path === '178/media_publish' ) { return $GLOBALS['ig']['failpub'] ? $no( 'Fatal' ) : $ok( array( 'id' => 'm' . $b['creation_id'] ) ); }
			if ( ( $q['fields'] ?? '' ) === 'status_code' ) { return $ok( array( 'status_code' => $GLOBALS['ig']['status'][ $path ] ?? 'FINISHED' ) ); }
			if ( ( $q['fields'] ?? '' ) === 'permalink' ) { return $ok( array( 'permalink' => 'https://www.instagram.com/p/' . $path . '/' ) ); }
			return $no( 'unknown ' . $path );
		},
	) as $fn => $body ) { if ( ! function_exists( $fn ) ) { $GLOBALS['hfn'][ $fn ] = $body; eval( 'function ' . $fn . '( ...$a ) { return $GLOBALS["hfn"]["' . $fn . '"]( ...$a ); }' ); } }
	DBD_Instagram::$wait = function () {};
	$bad = 0; $ck = function ( $what, $ok, $got = '' ) use ( &$bad ) { echo '  ' . ( $ok ? 'ok' : 'WRONG' ) . ": $what" . ( $ok ? '' : " (got: $got)" ) . "\n"; $bad += ! $ok; };
	$calls = function ( $m, $p ) { return array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) use ( $m, $p ) { return $c[0] === $m && $c[1] === $p; } ) ); };
	try {
		$n = DBD_Pack::newest(); $season = (int) DBD_Data::season(); $ny = new DateTimeZone( 'America/New_York' );
		/* the slots: Tuesday 9:00 AM Eastern, then a day each */
		$day = function ( $sl ) use ( $n, $ny ) { $d = new DateTime( '@' . DBD_Instagram::slot_time( $n, $sl ) ); $d->setTimezone( $ny ); return $d->format( 'D H:i' ); };
		$ck( 'the slots fall Tuesday to Sunday at 9:00 AM Eastern', $day( 'carousel' ) === 'Tue 09:00' && $day( 'swing' ) === 'Wed 09:00' && $day( 'snaps' ) === 'Thu 09:00' && $day( 'offense' ) === 'Fri 09:00' && $day( 'targets' ) === 'Sat 09:00' && $day( 'leaders' ) === 'Sun 09:00', $day( 'carousel' ) . ' / ' . $day( 'leaders' ) );
		$plan = DBD_Instagram::plan( $n ); $items = array_values( array_filter( DBD_Pack::items( $n ), function ( $i ) { return ! empty( $i['sq'] ); } ) );
		$ck( 'the plan: the carousel with every item, then each item on its own', isset( $plan['carousel'] ) && count( $plan['carousel'] ) === count( $items ) && count( $plan ) === 1 + count( array_filter( $items, function ( $i ) { return isset( DBD_Instagram::SLOTS[ $i['k'] ] ); } ) ), implode( ',', array_keys( $plan ) ) );  /* pass rushers ride the carousel only (SCOPE §79) */
		$cap = DBD_Instagram::carousel_caption( $n, $plan['carousel'] ); preg_match_all( '/#\w+/', $cap, $tg );
		$ck( 'the carousel caption', strpos( $cap, 'Week ' . $n . ' in ' ) === 0 && strpos( $cap, "\n1. " ) !== false && strpos( $cap, 'Full charts: link in bio (' ) !== false && mb_strlen( $cap ) <= 2200 && count( $tg[0] ) <= 15 && in_array( '#NFL', $tg[0], true ), str_replace( "\n", ' / ', $cap ) );
		$c1 = DBD_Instagram::caption( $n, 'swing', $plan['swing'] ); $ck( 'a daily caption is the item’s Instagram caption', strpos( $c1, 'Full chart: link in bio' ) !== false && strpos( $c1, '#NFL' ) !== false, $c1 );
		/* booking */
		$tm = array(); foreach ( array_keys( $plan ) as $sl ) { $tm[ $sl ] = 1800000000 + DBD_Instagram::SLOTS[ $sl ] * 86400; }
		list( $s1, $b1 ) = DBD_Instagram::decide( array( 'jobs' => array() ), $season, $n, array_keys( $plan ), $tm, 1800000000 - 3600 );
		$ck( 'a new week books each post at its slot', count( $b1 ) === count( $plan ) && reset( $b1 ) === 1800000000, json_encode( $b1 ) );
		list( $s2, $b2 ) = DBD_Instagram::decide( $s1, $season, $n, array_keys( $plan ), $tm, 1800000000 ); $ck( 'it books once', ! $b2 && $s2 === $s1 );
		list( $s3, $b3 ) = DBD_Instagram::decide( array( 'jobs' => array() ), $season, $n, array_keys( $plan ), $tm, 1800000000 + 2 * 86400 + 60 );
		$ck( 'posts more than a day past their slot are skipped, the rest booked', strpos( $s3['jobs'][ DBD_Instagram::key( $season, $n, 'carousel' ) ]['state'], 'skipped' ) === 0 && ! isset( $b3[ DBD_Instagram::key( $season, $n, 'swing' ) ] ) && isset( $b3[ DBD_Instagram::key( $season, $n, 'snaps' ) ] ), json_encode( $b3 ) );
		$bk = DBD_Instagram::note( null, DBD_Instagram::slot_time( $n, 'carousel' ) - 600 ); $ck( 'after a sync, the newest week’s posts are booked, each in its own record', count( $bk ) === count( $plan ) && isset( $GLOBALS['dbd_opt'][ 'dbd_ig_job_' . DBD_Instagram::key( $season, $n, 'leaders' ) ] ), json_encode( $bk ) );
		$nj = function () { return count( array_filter( array_keys( $GLOBALS['dbd_opt'] ), function ( $k ) { return strpos( $k, 'dbd_ig_job_' ) === 0; } ) ); }; $before = $nj();
		$at0 = $GLOBALS['dbd_opt'][ 'dbd_ig_job_' . DBD_Instagram::key( $season, $n, 'carousel' ) ]['at'];
		DBD_Instagram::note( null, DBD_Instagram::slot_time( $n, 'carousel' ) - 300 );
		$ck( 'a second sync adds no records and keeps the bookings', $nj() === $before && $GLOBALS['dbd_opt'][ 'dbd_ig_job_' . DBD_Instagram::key( $season, $n, 'carousel' ) ]['at'] === $at0 );
		/* posting the carousel */
		$kc = DBD_Instagram::key( $season, $n, 'carousel' ); $GLOBALS['hcalls'] = array();
		DBD_Instagram::send( $kc, true ); $j = DBD_Instagram::job( $kc ); $kids = array_values( array_filter( $calls( 'POST', '178/media' ), function ( $c ) { return ( $c[2]['is_carousel_item'] ?? '' ) === 'true'; } ) );
		$car = array_values( array_filter( $calls( 'POST', '178/media' ), function ( $c ) { return ( $c[2]['media_type'] ?? '' ) === 'CAROUSEL'; } ) );
		$ck( 'the carousel: a JPEG slide per square, then the carousel with its caption, then publish', count( $kids ) === count( $plan['carousel'] ) && strpos( $kids[0][2]['image_url'], 'fmt=jpg' ) !== false && strpos( $kids[0][2]['image_url'], 'card=ig-week-' . $n . '-' ) !== false
			&& count( $car ) === 1 && $car[0][2]['children'] === implode( ',', array_map( function ( $i ) { return 'c' . $i; }, range( 1, count( $kids ) ) ) ) && $car[0][2]['caption'] === $cap && count( $calls( 'POST', '178/media_publish' ) ) === 1 && ( $car[0][2]['access_token'] ?? '' ) === 'tok', json_encode( $car ) );
		$ck( 'it’s marked posted with its link', ( $j['state'] ?? '' ) === 'sent' && strpos( (string) ( $j['link'] ?? '' ), 'https://www.instagram.com/p/' ) === 0, json_encode( $j ) );
		$GLOBALS['hcalls'] = array(); DBD_Instagram::send( $kc, true ); $ck( 'a posted carousel never posts again', ! $GLOBALS['hcalls'] );
		/* a daily post: one image; a lost publish reply is not posted twice */
		$ks = DBD_Instagram::key( $season, $n, 'swing' ); $GLOBALS['ig']['failpub'] = true; $GLOBALS['hcalls'] = array();
		$GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $ks ]['at'] = $GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $ks ]['slot_at'] = time();  /* due now, whatever day CI runs on: a slot more than a day old is rightly skipped (2.36.0) */
		DBD_Instagram::send( $ks ); $j = DBD_Instagram::job( $ks );
		$ck( 'a failed publish retries and keeps its container', strpos( (string) $j['state'], 'retrying: Instagram answered 400: Fatal' ) === 0 && ! empty( $j['container'] ) && count( $calls( 'POST', '178/media' ) ) === 1 && strpos( $calls( 'POST', '178/media' )[0][2]['image_url'], 'card=ig-week-' . $n . '-swing' ) !== false, json_encode( $j ) );
		$GLOBALS['ig']['failpub'] = false; $GLOBALS['ig']['status'][ $j['container'] ] = 'PUBLISHED'; $GLOBALS['hcalls'] = array();
		DBD_Instagram::send( $ks ); $j = DBD_Instagram::job( $ks );
		$ck( 'when the container already went out, the retry marks it posted without posting again', ( $j['state'] ?? '' ) === 'sent' && ! $calls( 'POST', '178/media' ) && ! $calls( 'POST', '178/media_publish' ), json_encode( array( $j, $GLOBALS['hcalls'] ) ) );
		/* a container whose status Instagram won't give: kept, nothing new made (Greptile, PR #144) */
		$kt = DBD_Instagram::key( $season, $n, 'targets' ); $GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $kt ] = array( 'state' => 'retrying: x', 'at' => time(), 'slot_at' => time(), 'tries' => 1, 'container' => 'mystery' );
		$GLOBALS['ig']['status']['mystery'] = ''; $GLOBALS['hcalls'] = array(); DBD_Instagram::send( $kt ); $j = DBD_Instagram::job( $kt );
		$ck( 'an unknown status keeps the container and makes nothing new', $j['container'] === 'mystery' && strpos( $j['state'], 'retrying: Instagram didn’t say' ) === 0 && ! $calls( 'POST', '178/media' ), json_encode( $j ) );
		$GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $kt ] = array( 'state' => 'retrying: x', 'at' => 1000, 'slot_at' => 1000, 'tries' => 1 ); $GLOBALS['hcalls'] = array(); DBD_Instagram::send( $kt );
		$ck( 'an automatic retry past the cutoff isn’t posted', DBD_Instagram::job( $kt )['state'] === 'not posted: more than a day late' && ! $GLOBALS['hcalls'], DBD_Instagram::job( $kt )['state'] );
		$GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $kt ] = array( 'state' => 'retrying: again', 'at' => 1000, 'slot_at' => 1000, 'tries' => 0, 'force' => true ); DBD_Instagram::send( $kt );
		$ck( 'Try again on the tab overrides the cutoff', DBD_Instagram::job( $kt )['state'] === 'sent' && DBD_Instagram::job( $kt )['force'] === false, json_encode( DBD_Instagram::job( $kt ) ) );
		/* late, failing, Try again */
		$kn = DBD_Instagram::key( $season, $n, 'snaps' ); $GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $kn ] = array( 'state' => 'booked', 'at' => 1000, 'slot_at' => 1000, 'tries' => 0 );
		$GLOBALS['hcalls'] = array(); DBD_Instagram::send( $kn ); $ck( 'a booked post run more than a day late is not posted', DBD_Instagram::job( $kn )['state'] === 'not posted: more than a day late' && ! $GLOBALS['hcalls'], DBD_Instagram::job( $kn )['state'] );
		$GLOBALS['ig']['failpub'] = true; $GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $kn ] = array( 'state' => 'retrying: x', 'at' => time(), 'slot_at' => time(), 'tries' => DBD_Instagram::TRIES - 1, 'container' => 'gone' );
		$GLOBALS['ig']['status']['gone'] = 'EXPIRED'; DBD_Instagram::send( $kn ); $j = DBD_Instagram::job( $kn );
		$ck( 'the last try fails it, makes a new container for an expired one, and sends the note', strpos( $j['state'], 'failed: ' ) === 0 && $j['container'] !== 'gone' && count( $GLOBALS['hmail'] ) === 1 && strpos( $GLOBALS['hmail'][0], 'Instagram didn’t take the usage riser' ) !== false, json_encode( array( $j, $GLOBALS['hmail'] ) ) );
		$GLOBALS['ig']['failpub'] = false;
		$line = DBD_Instagram::line( $n ); $ck( 'the status line names a failed post with Try again', $line[0] === 'bad' && $line[3] === $kn && strpos( $line[1], 'Instagram didn’t take the usage riser' ) === 0, json_encode( $line ) );
		$GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $kn ]['state'] = 'sent';
		$line = DBD_Instagram::line( $n ); $ck( 'the status line: the last post and the next one', $line[0] === 'ok' && strpos( $line[1], 'Instagram: posted the target-share mover ' ) === 0 && strpos( $line[1], ' Next: best and worst offense, ' ) !== false, $line[1] );
		$ck( 'the next post for Post now', DBD_Instagram::next_key() === DBD_Instagram::key( $season, $n, 'offense' ), DBD_Instagram::next_key() );
		/* a lost booking past its cutoff isn't brought back (Greptile, PR #144) */
		$GLOBALS['dbd_opt'][ 'dbd_ig_job_' . DBD_Instagram::key( $season, $n, 'leaders' ) ] = array( 'state' => 'retrying: x', 'at' => 1000, 'slot_at' => 1000, 'tries' => 1 );
		$ck( 'a lost retry past its cutoff isn’t booked again', ! isset( DBD_Instagram::note()[ DBD_Instagram::key( $season, $n, 'leaders' ) ] ) );
		/* switched off: nothing posts */
		$GLOBALS['dbd_opt']['dbd_ig_set'] = array( 'on' => false ); $ko = DBD_Instagram::key( $season, $n, 'offense' ); $GLOBALS['hcalls'] = array();
		DBD_Instagram::send( $ko ); $ck( 'switched off, a booked post isn’t posted', strpos( (string) ( DBD_Instagram::job( $ko )['state'] ?? '' ), 'not posted: Instagram posting is off' ) === 0 && ! $GLOBALS['hcalls'], (string) ( DBD_Instagram::job( $ko )['state'] ?? '' ) );
		$ck( 'switched off, the status line is gone', DBD_Instagram::line( $n ) === null );
		$GLOBALS['dbd_opt']['dbd_ig_set'] = array( 'on' => true );
		/* the token */
		$ck( 'a bad token isn’t kept', strpos( DBD_Instagram::connect( 'bad' ), 'That token didn’t work. Instagram answered 400: Invalid OAuth access token' ) === 0 && DBD_Instagram::token()['token'] === 'tok' );
		$ck( 'a good token is kept with its account', DBD_Instagram::connect( ' good ' ) === '' && DBD_Instagram::token()['token'] === 'good' && DBD_Instagram::token()['user_id'] === '178' && DBD_Instagram::token()['username'] === 'down_by_down' );
		$ck( 'the renewal keeps the new token for 60 days', DBD_Instagram::refresh() && DBD_Instagram::token()['token'] === 'tok2' && abs( DBD_Instagram::token()['expires'] - ( time() + 5184000 ) ) < 5 && $calls( 'GET', 'refresh_access_token' )[0][2]['grant_type'] === 'ig_refresh_token' );
		$GLOBALS['ig']['refresh'] = 'no'; $ck( 'a failed renewal is kept and warned about', ! DBD_Instagram::refresh() && DBD_Instagram::token()['err'] !== '' && strpos( DBD_Instagram::warning(), 'The Instagram token didn’t renew' ) === 0, DBD_Instagram::warning() );
		$GLOBALS['dbd_opt']['dbd_ig_token']['expires'] = time() - 10; $ck( 'a token that ran out: not connected, nothing posts', ! DBD_Instagram::connected() && ! DBD_Instagram::on() && strpos( DBD_Instagram::warning(), 'ran out' ) !== false );
		DBD_Instagram::disconnect(); $ck( 'disconnect forgets it', DBD_Instagram::token() === null && DBD_Instagram::warning() === '' );
		/* Unified login (2.34.1, SCOPE §77): an EAA… token goes to graph.facebook.com with the account ID, never runs out, and is checked weekly */
		$ck( 'the token kinds', DBD_Instagram::kind( 'EAAxyz' ) === 'fb' && DBD_Instagram::kind( 'IGAAxyz' ) === 'ig' && DBD_Instagram::kind( 'tok' ) === 'ig' );
		$ck( 'the account ID keeps digits only', DBD_Instagram::sanitize( array( 'on' => '1', 'account' => ' 1784-1433 054101334 ' ) ) === array( 'on' => true, 'account' => '17841433054101334' ) );
		$ck( 'an EAA token without an account ID isn’t kept', strpos( DBD_Instagram::connect( 'EAAgood' ), 'That token needs the Instagram account ID' ) === 0 && DBD_Instagram::token() === null );
		$GLOBALS['hcalls'] = array(); $GLOBALS['dbd_opt']['dbd_ig_set'] = array( 'on' => true, 'account' => '178' );
		$ck( 'an EAA token that Instagram turns down isn’t kept', strpos( DBD_Instagram::connect( 'EAAbad' ), 'That token didn’t work with account 178. Instagram answered 400' ) === 0 && DBD_Instagram::token() === null );
		$ck( 'an unknown account isn’t kept', strpos( DBD_Instagram::connect( 'EAAgood', '404' ), 'That token didn’t work with account 404.' ) === 0 && DBD_Instagram::token() === null );
		$GLOBALS['hcalls'] = array(); $c = DBD_Instagram::connect( ' EAAgood ' ); $tk = DBD_Instagram::token();
		$ck( 'an EAA token is kept with its account, never running out', $c === '' && $tk['token'] === 'EAAgood' && $tk['user_id'] === '178' && $tk['username'] === 'down_by_down' && $tk['expires'] === 0 && DBD_Instagram::lasting() && DBD_Instagram::connected() && DBD_Instagram::warning() === '', $c . json_encode( $tk ) );
		$ck( 'it was checked on graph.facebook.com', ( $GLOBALS['hcalls'][0][3] ?? '' ) === 'graph.facebook.com' && $GLOBALS['hcalls'][0][1] === '178', json_encode( $GLOBALS['hcalls'] ) );
		$GLOBALS['hcalls'] = array(); DBD_Instagram::api( 'POST', '178/media', array( 'image_url' => 'x' ) );
		$ck( 'its posts go to graph.facebook.com', ( $GLOBALS['hcalls'][0][3] ?? '' ) === 'graph.facebook.com' && $GLOBALS['hcalls'][0][1] === '178/media' );
		$GLOBALS['hcalls'] = array(); $ck( 'the weekly check isn’t a renewal', DBD_Instagram::refresh() && ! $calls( 'GET', 'refresh_access_token' ) && DBD_Instagram::token()['token'] === 'EAAgood' && DBD_Instagram::token()['expires'] === 0 );
		$GLOBALS['ig']['fbcheck'] = 'no'; $ck( 'a failed check is warned about, and it still counts as connected', ! DBD_Instagram::refresh() && strpos( DBD_Instagram::warning(), 'Instagram stopped taking the token' ) === 0 && DBD_Instagram::connected(), DBD_Instagram::warning() );
		$GLOBALS['ig']['fbcheck'] = 'ok'; $ck( 'a passing check clears the warning', DBD_Instagram::refresh() && DBD_Instagram::warning() === '' );
		$GLOBALS['hcalls'] = array(); DBD_Instagram::api( 'GET', 'me', array(), 'IGAAx' ); $ck( 'an IG token still goes to graph.instagram.com', ( $GLOBALS['hcalls'][0][3] ?? '' ) === 'graph.instagram.com' );
		/* the settings box's save (Greptile, PR #145): a new account ID that fails its check goes back, and the connection keeps posting where it did */
		$GLOBALS['dbd_opt']['dbd_ig_set'] = array( 'on' => true, 'account' => '178' ); DBD_Instagram::connect( 'EAAgood' ); $GLOBALS['ig']['fbcheck'] = 'no';
		$e1 = DBD_Instagram::save( array( 'dbd_ig_set' => array( 'on' => '1', 'account' => '555' ) ) );
		$ck( 'a new account ID that fails its check is put back', strpos( $e1, 'That token didn’t work with account 555.' ) === 0 && strpos( $e1, 'put back to 178' ) !== false && DBD_Instagram::settings()['account'] === '178' && DBD_Instagram::token()['user_id'] === '178', $e1 . json_encode( DBD_Instagram::settings() ) );
		$e2 = DBD_Instagram::save( array( 'dbd_ig_set' => array( 'on' => '1', 'account' => '555' ), 'dbd_ig_token_new' => 'EAAbad' ) );
		$ck( 'a new token and ID that fail together put the ID back too', $e2 !== '' && DBD_Instagram::settings()['account'] === '178' && DBD_Instagram::token()['token'] === 'EAAgood', $e2 );
		$GLOBALS['ig']['fbcheck'] = 'ok'; $e3 = DBD_Instagram::save( array( 'dbd_ig_set' => array( 'on' => '1', 'account' => '555' ) ) );
		$ck( 'a new account ID that passes moves the connection', $e3 === '' && DBD_Instagram::settings()['account'] === '555' && DBD_Instagram::token()['user_id'] === '555', $e3 );
		$ck( 'saving with nothing new checks nothing', ( function () { $GLOBALS['hcalls'] = array(); return DBD_Instagram::save( array( 'dbd_ig_set' => array( 'on' => '1', 'account' => '555' ) ) ) === '' && ! $GLOBALS['hcalls']; } )() );
		DBD_Instagram::save( array( 'dbd_ig_set' => array( 'on' => '1', 'account' => '555' ), 'dbd_ig_disconnect' => '1' ) ); $ck( 'Disconnect in the box forgets the token', DBD_Instagram::token() === null );
		DBD_Instagram::disconnect(); $GLOBALS['dbd_opt']['dbd_ig_set'] = array( 'on' => true );
		/* the JPEG square */
		$ck( 'a square asks for JPEG with &fmt=jpg', strpos( DBD_Card::jpg_url( 'ig-week-' . $n . '-swing' ), 'card=ig-week-' . $n . '-swing' ) !== false && substr( DBD_Card::jpg_url( 'ig-week-' . $n . '-swing' ), -8 ) === '&fmt=jpg' );
		$_GET = array( 'fmt' => 'jpg' ); $ck( 'only the squares come as JPEG', DBD_Card::wants_jpg( 'ig-week-' . $n . '-swing' ) && ! DBD_Card::wants_jpg( 'pack-week-' . $n . '-snaps' ) ); $_GET = array();
		if ( DBD_Card::available() ) { $im = DBD_Card::render( 'ig-week-' . $n . '-swing' ); ob_start(); imagejpeg( $im, null, 90 ); $jb = ob_get_clean(); imagedestroy( $im ); $sz = getimagesizefromstring( $jb );
			$ck( 'the JPEG square is 1080x1080', $sz && $sz[0] === 1080 && $sz[1] === 1080 && $sz['mime'] === 'image/jpeg' ); }
	} catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in igpost: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	echo "igpost week $n: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
/* ---- the share pack email (2.28.0, SCOPE §69): booking, the email, one copy per person, a retry for only the one Brevo refused */
/* ---- Auto publish (2.38.0, SCOPE §81): the Bluesky text, its link facet's byte offsets and the preview; connecting; posting through a fake
   Bluesky (session, card upload, record), once only, a lost reply found instead of posted again, an expired session made again, a card too big
   for a thumb; then the button's networks and states, an Instagram day taken over and dropped, pass rushers and a keys side on Instagram */
if ( $view === 'autopub' ) {
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'k', 'list' => 3, 'tpl' => 5, 'test' => 'gene@example.test' ),
		'dbd_ig_token' => array( 'token' => 'tok', 'user_id' => '178', 'username' => 'down_by_down', 'expires' => time() + 50 * 86400, 'checked' => time(), 'err' => '' ) );
	$GLOBALS['hcalls'] = array(); $GLOBALS['hmail'] = array(); $GLOBALS['htr'] = array();
	$GLOBALS['bs'] = array( 'n' => 0, 'records' => array(), 'lose' => false, 'expire' => 0, 'sessions' => 0, 'failn' => 0 ); $GLOBALS['xs'] = array( 'posts' => array(), 'failn' => 0, 'lose' => false ); $GLOBALS['thf'] = array( 'c' => array(), 'posts' => array(), 'failn' => 0, 'lose' => false, 'norr' => false, 'refail' => false ); $GLOBALS['ig'] = array( 'n' => 0 );
	foreach ( array(
		'is_email' => function ( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); },
		'admin_url' => function ( $p = '' ) { return 'https://example.test/wp-admin/' . $p; },
		'add_option' => function ( $k, $v = '' ) { if ( isset( $GLOBALS['dbd_opt'][ $k ] ) ) { return false; } $GLOBALS['dbd_opt'][ $k ] = $v; return true; },
		'delete_option' => function ( $k ) { unset( $GLOBALS['dbd_opt'][ $k ] ); return true; },
		'wp_nonce_url' => function ( $u, $a = '' ) { return $u . ( strpos( $u, '?' ) === false ? '?' : '&' ) . '_wpnonce=n'; },
		'delete_transient' => function ( $k ) { unset( $GLOBALS['htr'][ $k ] ); return true; },
		'wp_mail' => function ( $to, $sub ) { $GLOBALS['hmail'][] = $sub; return true; },
		'wp_clear_scheduled_hook' => function () { return 0; },
		'wp_remote_request' => function ( $url, $a ) {
			$u = parse_url( $url ); parse_str( (string) ( $u['query'] ?? '' ), $q ); $host = (string) ( $u['host'] ?? '' );
			$ok = function ( $j ) { return array( 'response' => array( 'code' => 200 ), 'body' => json_encode( $j ) ); };
			$no = function ( $c, $e, $m = '' ) { return array( 'response' => array( 'code' => $c ), 'body' => json_encode( array( 'error' => $e, 'message' => $m ) ) ); };
			if ( $host === 'bsky.social' ) {
				$nsid = substr( $u['path'], 6 ); $raw = (string) ( $a['body'] ?? '' ); $b = json_decode( $raw, true );
				$GLOBALS['hcalls'][] = array( 'bs', $a['method'], $nsid, $a['method'] === 'GET' ? $q : ( is_array( $b ) ? $b : strlen( $raw ) ), $a['headers'] ?? array() );
				$auth = (string) ( $a['headers']['Authorization'] ?? '' );
				if ( $nsid === 'com.atproto.server.createSession' ) { if ( ( $b['password'] ?? '' ) !== 'good-pass' ) { return $no( 401, 'AuthenticationRequired', 'Invalid identifier or password' ); } $GLOBALS['bs']['sessions']++; return $ok( array( 'did' => 'did:plc:dbd', 'handle' => 'downbydown.bsky.social', 'accessJwt' => 'jwt' . $GLOBALS['bs']['sessions'] ) ); }
				if ( $nsid === 'com.atproto.repo.getRecord' ) { $r = $GLOBALS['bs']['records'][ $q['rkey'] ?? '' ] ?? null; return $r ? $ok( array( 'uri' => 'at://did:plc:dbd/app.bsky.feed.post/' . $q['rkey'], 'cid' => 'bafyrei' . $q['rkey'], 'value' => $r ) ) : $no( 400, 'RecordNotFound', 'Could not locate record' ); }
				if ( $GLOBALS['bs']['expire'] > 0 && $auth === 'Bearer jwt1' ) { $GLOBALS['bs']['expire']--; return $no( 400, 'ExpiredToken', 'Token has expired' ); }
				if ( strpos( $auth, 'Bearer jwt' ) !== 0 ) { return $no( 401, 'AuthMissing', 'Authentication Required' ); }
				if ( $nsid === 'com.atproto.repo.uploadBlob' ) { $bt = (string) ( $a['headers']['Content-Type'] ?? '' ); return ( $bt === 'image/png' && substr( $raw, 1, 3 ) === 'PNG' ) || ( $bt === 'image/jpeg' && substr( $raw, 0, 2 ) === "\xFF\xD8" ) ? $ok( array( 'blob' => array( '$type' => 'blob', 'ref' => array( '$link' => 'bafy' ), 'mimeType' => $bt, 'size' => strlen( $raw ) ) ) ) : $no( 400, 'InvalidRequest', 'not a png' ); }
				if ( $nsid === 'com.atproto.repo.createRecord' ) {
					if ( ! empty( $GLOBALS['bs']['failn'] ) && --$GLOBALS['bs']['failn'] === 0 ) { return $no( 500, 'InternalServerError', 'Internal Server Error' ); }  /* refused: nothing kept */
					if ( isset( $GLOBALS['bs']['records'][ $b['rkey'] ] ) ) { return $no( 400, 'InvalidRequest', 'Record already exists' ); }
					$GLOBALS['bs']['records'][ $b['rkey'] ] = $b['record'];
					if ( $GLOBALS['bs']['lose'] ) { $GLOBALS['bs']['lose'] = false; return $no( 502, 'UpstreamFailure', 'Bad gateway' ); }  /* it went out, but the reply was lost */
					return $ok( array( 'uri' => 'at://did:plc:dbd/app.bsky.feed.post/' . $b['rkey'], 'cid' => 'bafyrei' . $b['rkey'] ) );
				}
				return $no( 400, 'MethodNotImplemented', $nsid );
			}
			if ( $host === 'graph.threads.net' ) {  /* Threads (SCOPE §84): token short1 for @down_by_down (id th99), other for someone else; App secret sec1 */
				$path = preg_replace( '#^/(v1\.0/)?#', '', $u['path'] ); $b = (array) ( $a['body'] ?? array() ); $tok = (string) ( $a['method'] === 'GET' ? ( $q['access_token'] ?? '' ) : ( $b['access_token'] ?? '' ) ); $F =& $GLOBALS['thf'];
				$GLOBALS['hcalls'][] = array( 'th', $a['method'], $path, $a['method'] === 'GET' ? $q : $b );
				$tno = function ( $c, $m ) { return array( 'response' => array( 'code' => $c ), 'body' => json_encode( array( 'error' => array( 'message' => $m, 'code' => 100 ) ) ) ); };
				if ( $path === 'me' ) { return $tok === 'short1' || $tok === 'long-gen' ? $ok( array( 'id' => 'th99', 'username' => 'down_by_down' ) ) : ( $tok === 'other' ? $ok( array( 'id' => 'th77', 'username' => 'someone_else' ) ) : $tno( 400, 'Invalid OAuth access token' ) ); }
				if ( $path === 'access_token' ) { return $tok === 'short1' && ( $q['client_secret'] ?? '' ) === 'sec1' && ( $q['grant_type'] ?? '' ) === 'th_exchange_token' ? $ok( array( 'access_token' => 'long1', 'token_type' => 'bearer', 'expires_in' => 5184000 ) ) : $tno( 400, 'Error validating client secret' ); }
				if ( $path === 'refresh_access_token' ) { return ! $F['refail'] && strpos( $tok, 'long' ) === 0 && ( $q['grant_type'] ?? '' ) === 'th_refresh_token' ? $ok( array( 'access_token' => 'long2', 'token_type' => 'bearer', 'expires_in' => 5184000 ) ) : $tno( 400, 'Session has expired' ); }
				if ( strpos( $tok, 'long' ) !== 0 ) { return $tno( 400, 'Invalid OAuth access token' ); }
				if ( $path === 'th99/threads' && $a['method'] === 'POST' ) {
					if ( ! empty( $F['failn'] ) && --$F['failn'] === 0 ) { return $tno( 500, 'An unknown error occurred' ); }
					$to = (string) ( $b['reply_to_id'] ?? '' ); if ( $F['norr'] && $to !== '' && ! empty( $F['posts'][ $to ]['reply'] ) ) { return $tno( 400, 'Cannot reply to a reply' ); }
					$cid = 'c' . ( count( $F['c'] ) + 1 ); $F['c'][ $cid ] = array( 'p' => $b, 'status' => 'FINISHED', 'post' => '' ); return $ok( array( 'id' => $cid ) );
				}
				if ( $path === 'th99/threads_publish' ) {
					$cid = (string) ( $b['creation_id'] ?? '' ); if ( ! isset( $F['c'][ $cid ] ) || $F['c'][ $cid ]['status'] !== 'FINISHED' ) { return $tno( 400, 'Media not found' ); }
					$pid = 'p' . ( count( $F['posts'] ) + 1 ); $F['posts'][ $pid ] = array( 'text' => $F['c'][ $cid ]['p']['text'], 'reply' => (string) ( $F['c'][ $cid ]['p']['reply_to_id'] ?? '' ), 'link' => (string) ( $F['c'][ $cid ]['p']['link_attachment'] ?? '' ) );
					$F['c'][ $cid ]['status'] = 'PUBLISHED'; $F['c'][ $cid ]['post'] = $pid;
					if ( $F['lose'] ) { $F['lose'] = false; return $tno( 502, 'Bad gateway' ); }  /* it went out, but the reply was lost */
					return $ok( array( 'id' => $pid ) );
				}
				if ( $path === 'th99/threads' || $path === 'th99/replies' ) {
					$o = array(); foreach ( array_reverse( $F['posts'], true ) as $pid => $p0 ) { if ( ( $p0['reply'] !== '' ) === ( $path === 'th99/replies' ) ) { $o[] = array( 'id' => $pid, 'text' => $p0['text'] ); } }
					return $ok( array( 'data' => array_slice( $o, 0, (int) ( $q['limit'] ?? 25 ) ) ) );
				}
				if ( isset( $F['c'][ $path ] ) ) { return $ok( array( 'id' => $path, 'status' => $F['c'][ $path ]['status'] ) ); }
				if ( isset( $F['posts'][ $path ] ) ) { return $ok( array( 'id' => $path, 'permalink' => 'https://www.threads.com/@down_by_down/post/' . $path ) ); }
				return $tno( 404, $path );
			}
			if ( $host === 'api.x.com' ) {  /* X (SCOPE §83): keys ck1/at1 for @dxd_NFLfootball (id 99), at-other for someone else */
				$path = substr( $u['path'], 3 ); $b = json_decode( (string) ( $a['body'] ?? '' ), true ); $auth = (string) ( $a['headers']['Authorization'] ?? '' );
				$GLOBALS['hcalls'][] = array( 'x', $a['method'], $path, $a['method'] === 'GET' ? $q : $b, $a['headers'] ?? array() );
				$xno = function ( $c, $d ) { return array( 'response' => array( 'code' => $c ), 'body' => json_encode( array( 'title' => 'Error', 'detail' => $d, 'status' => $c ) ) ); };
				if ( strpos( $auth, 'OAuth ' ) !== 0 || strpos( $auth, 'oauth_consumer_key="ck1"' ) === false || ! preg_match( '/oauth_signature="[^"]{20,}"/', $auth ) ) { return $xno( 401, 'Unauthorized' ); }
				if ( $path === 'users/me' ) { return strpos( $auth, 'oauth_token="at-other"' ) !== false ? $ok( array( 'data' => array( 'id' => '77', 'username' => 'someone_else' ) ) ) : $ok( array( 'data' => array( 'id' => '99', 'username' => 'dxd_NFLfootball' ) ) ); }
				if ( $path === 'media/upload' ) { $GLOBALS['xs']['up'] = (int) ( $GLOBALS['xs']['up'] ?? 0 ) + 1; return ( $b['media_category'] ?? '' ) === 'tweet_image' && substr( (string) base64_decode( (string) ( $b['media'] ?? '' ) ), 0, 2 ) === "\xFF\xD8" ? $ok( array( 'data' => array( 'id' => 'mid' . $GLOBALS['xs']['up'] ) ) ) : $xno( 400, 'bad media' ); }
				if ( $path === 'tweets' && $a['method'] === 'POST' ) {
					if ( ! empty( $GLOBALS['xs']['failn'] ) && --$GLOBALS['xs']['failn'] === 0 ) { return $xno( 503, 'Service Unavailable' ); }
					foreach ( $GLOBALS['xs']['posts'] as $p0 ) { if ( $p0['text'] === $b['text'] ) { return $xno( 403, 'You are not allowed to create a Tweet with duplicate content.' ); } }
					$id = (string) ( 1000 + count( $GLOBALS['xs']['posts'] ) ); $GLOBALS['xs']['posts'][] = array( 'id' => $id, 'text' => $b['text'], 'reply' => (string) ( $b['reply']['in_reply_to_tweet_id'] ?? '' ), 'media' => (array) ( $b['media']['media_ids'] ?? array() ) );
					if ( $GLOBALS['xs']['lose'] ) { $GLOBALS['xs']['lose'] = false; return $xno( 502, 'Bad Gateway' ); }  /* it went out, but the reply was lost */
					return array( 'response' => array( 'code' => 201 ), 'body' => json_encode( array( 'data' => array( 'id' => $id, 'text' => $b['text'] ) ) ) );
				}
				if ( $path === 'users/99/tweets' ) {  /* the latest first, links as t.co ones and & as &amp;, the way X gives them back */
					$o = array(); foreach ( array_slice( array_reverse( $GLOBALS['xs']['posts'] ), 0, (int) ( $q['max_results'] ?? 10 ) ) as $p0 ) { $o[] = array( 'id' => $p0['id'], 'text' => str_replace( '&', '&amp;', preg_replace( '#https?://\S+#', 'https://t.co/AbC123', $p0['text'] ) ) ); }
					return $ok( array( 'data' => $o ) );
				}
				return $xno( 404, $path );
			}
			$b = (array) ( $a['body'] ?? array() ); $path = preg_replace( '#^/(v\d+\.\d+/)?#', '', $u['path'] );
			$GLOBALS['hcalls'][] = array( 'ig', $a['method'], $path, $a['method'] === 'GET' ? $q : $b );
			if ( $path === '178/media' ) { $GLOBALS['ig']['n']++; return $ok( array( 'id' => 'c' . $GLOBALS['ig']['n'] ) ); }
			if ( $path === '178/media_publish' ) { return $ok( array( 'id' => 'm' . $b['creation_id'] ) ); }
			if ( ( $q['fields'] ?? '' ) === 'status_code' ) { return $ok( array( 'status_code' => 'FINISHED' ) ); }
			if ( ( $q['fields'] ?? '' ) === 'permalink' ) { return $ok( array( 'permalink' => 'https://www.instagram.com/p/' . $path . '/' ) ); }
			return $no( 400, 'unknown', $path );
		},
	) as $fn => $body ) { if ( ! function_exists( $fn ) ) { $GLOBALS['hfn'][ $fn ] = $body; eval( 'function ' . $fn . '( ...$a ) { return $GLOBALS["hfn"]["' . $fn . '"]( ...$a ); }' ); } }
	DBD_Instagram::$wait = function () {};
	$bad = 0; $ck = function ( $what, $ok, $got = '' ) use ( &$bad ) { echo '  ' . ( $ok ? 'ok' : 'WRONG' ) . ": $what" . ( $ok ? '' : " (got: $got)" ) . "\n"; $bad += ! $ok; };
	$bcalls = function ( $nsid ) { return array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) use ( $nsid ) { return $c[0] === 'bs' && $c[2] === $nsid; } ) ); };
	$igcalls = function ( $p ) { return array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) use ( $p ) { return $c[0] === 'ig' && $c[2] === $p; } ) ); };
	try {
		$n = DBD_Pack::newest(); $season = (int) DBD_Data::season(); $items = DBD_Pack::items( $n ); $sw = DBD_Pack::find( $n, 'swing' ) ?: $items[0]; $ks = (string) $sw['k'];
		$ck( 'find: an item by its key, nothing for a key that isn’t there', ( $sw['k'] ?? '' ) === $ks && DBD_Pack::find( $n, 'nope' ) === null && DBD_Pack::find( $n, 'keys-2026_99_AAA_BBB-AAA' ) === null );
		/* the text: shortened link, the facet's byte offsets over the shortened link, the full link in the facet */
		$all = array_merge( $items, array_merge( ...array_map( function ( $kg ) { return $kg['sides']; }, DBD_Pack::keys( $n ) ?: array( array( 'sides' => array() ) ) ) ) );
		$tx_ok = true; $tx_bad = '';
		foreach ( $all as $it ) {
			list( $t, $f ) = DBD_Bluesky::text( $it ); $link = DBD_Pack::share_link( $it ); $show = DBD_Bluesky::short_link( $link );
			$ix = $f[0]['index'] ?? array(); $span = substr( $t, (int) ( $ix['byteStart'] ?? 0 ), (int) ( $ix['byteEnd'] ?? 0 ) - (int) ( $ix['byteStart'] ?? 0 ) );
			if ( count( $f ) !== 1 || $span !== $show || ( $f[0]['features'][0]['uri'] ?? '' ) !== $link || strpos( $t, $link ) !== false || mb_strlen( $t ) > 300 || mb_strlen( $show ) > 30 || ! mb_check_encoding( $t, 'UTF-8' ) ) { $tx_ok = false; $tx_bad = $t . ' | ' . json_encode( $f ) . ' | ' . $span; break; }
		}
		$ck( 'every item’s Bluesky text: the link shown short, its facet’s bytes over exactly that, the full link in the facet, 300 at most (' . count( $all ) . ' texts)', $tx_ok, $tx_bad );
		list( $tw ) = DBD_Bluesky::text( array( 'title' => 'Week 10’s swing: the Commanders’ win, −0.52 a play', 'link' => 'https://downbydown.com/game/2026-week-10-commanders-at-giants/' ) );
		list( , $fw ) = DBD_Bluesky::text( array( 'title' => 'Week 10’s swing: the Commanders’ win, −0.52 a play', 'link' => 'https://downbydown.com/game/2026-week-10-commanders-at-giants/' ) ); $at = (int) ( $fw[0]['index']['byteStart'] ?? -1 );
		$ck( 'curly quotes and a minus sign count in bytes, not letters', strpos( $tw, ' downbydown.com/game/2026-week…' ) !== false && $at === strpos( $tw, 'downbydown.com/game/' ) && $at > mb_strlen( substr( $tw, 0, $at ) ) && substr( $tw, $at, $fw[0]['index']['byteEnd'] - $at ) === 'downbydown.com/game/2026-week…', $tw . ' ' . json_encode( $fw ) );
		$ck( 'a short link drops the address’s query and slash', DBD_Bluesky::short_link( 'https://downbydown.com/week/10/?share=pack-week-10-offense' ) === 'downbydown.com/week/10' && DBD_Bluesky::short_link( 'https://www.downbydown.com/leaders/rush/' ) === 'downbydown.com/leaders/rush', DBD_Bluesky::short_link( 'https://downbydown.com/week/10/?share=pack-week-10-offense' ) );
		$rec = DBD_Bluesky::record( $sw, array( '$type' => 'blob' ), 1800000000 );
		$ck( 'the record: a post in English with the link preview (the item’s title and card)', $rec['$type'] === 'app.bsky.feed.post' && $rec['langs'] === array( 'en' ) && $rec['createdAt'] === '2027-01-15T08:00:00.000Z' && $rec['embed']['$type'] === 'app.bsky.embed.external'
			&& $rec['embed']['external']['uri'] === DBD_Pack::share_link( $sw ) && $rec['embed']['external']['title'] === $sw['title'] && isset( $rec['embed']['external']['thumb'] ) && ! empty( $rec['facets'] ), json_encode( $rec ) );
		$t1 = DBD_Bluesky::tid( 1800000000000000 ); $t2 = DBD_Bluesky::tid( 1800000000000001 );
		$ck( 'a record key: 13 letters of the TID alphabet, later ones sorting after', preg_match( '/^[234567a-j][234567a-z]{12}$/', $t1 ) && strcmp( $t2, $t1 ) > 0 && strlen( DBD_Bluesky::tid() ) === 13, "$t1 $t2" );
		$ck( 'a post’s web link from its at:// address', DBD_Bluesky::web( 'at://did:plc:dbd/app.bsky.feed.post/3kabc', 'downbydown.bsky.social' ) === 'https://bsky.app/profile/downbydown.bsky.social/post/3kabc' && DBD_Bluesky::web( 'at://x/other/1', 'h' ) === '' );
		/* connecting */
		$ck( 'not connected: nothing to post to', ! DBD_Bluesky::connected() && DBD_AutoPub::nets() === array( 'ig' ) );
		$e1 = DBD_Bluesky::connect( 'bad-pass' ); $ck( 'a bad app password keeps nothing', strpos( $e1, 'That app password didn’t work for @downbydown.bsky.social. Bluesky answered 401: Invalid identifier or password' ) === 0 && ! DBD_Bluesky::conn(), $e1 );
		$ck( 'a good one is kept with the account it’s for', DBD_Bluesky::connect( ' good-pass ' ) === '' && DBD_Bluesky::conn()['did'] === 'did:plc:dbd' && DBD_Bluesky::conn()['pass'] === 'good-pass' && $bcalls( 'com.atproto.server.createSession' )[0][3]['identifier'] === 'downbydown.bsky.social' );
		$ck( 'both networks are on the button', DBD_AutoPub::nets() === array( 'ig', 'bs' ) );
		$GLOBALS['dbd_opt']['dbd_ap_set'] = array( 'ig' => false, 'bs' => true ); $ck( 'untick Instagram and the button leaves it out', DBD_AutoPub::nets() === array( 'bs' ) ); unset( $GLOBALS['dbd_opt']['dbd_ap_set'] );
		/* posting to Bluesky through Auto publish: the item's card as the thumb */
		$key = DBD_AutoPub::key( $n, $ks ); $GLOBALS['hcalls'] = array();
		$st0 = DBD_AutoPub::states( $n, $ks ); $ck( 'before: both ready', ( $st0['ig'][0] ?? '' ) === 'ready' && ( $st0['bs'][0] ?? '' ) === 'ready' && DBD_AutoPub::ready( $n, $ks ) === array( 'ig', 'bs' ), json_encode( $st0 ) );
		$errs = DBD_AutoPub::publish( $n, $ks ); $bj = DBD_Bluesky::job( $key ); $cr = $bcalls( 'com.atproto.repo.createRecord' ); $up = $bcalls( 'com.atproto.repo.uploadBlob' );
		$ck( 'Bluesky: the card uploaded as a PNG, then one post with it', ! $errs && count( $up ) === 1 && $up[0][3] > 1000 && count( $cr ) === 1 && $cr[0][3]['repo'] === 'did:plc:dbd' && $cr[0][3]['collection'] === 'app.bsky.feed.post' && ! empty( $cr[0][3]['record']['embed']['external']['thumb'] ) && $cr[0][4]['Authorization'] === 'Bearer jwt1', json_encode( array( $errs, $cr ) ) );
		$ck( 'it’s kept as posted with its link', ( $bj['state'] ?? '' ) === 'sent' && $bj['link'] === 'https://bsky.app/profile/downbydown.bsky.social/post/' . $bj['rkey'], json_encode( $bj ) );
		$ij = DBD_Instagram::job( $key ); $ck( 'Instagram: queued to post now, as Auto publish', ( $ij['state'] ?? '' ) === 'booked' && ! empty( $ij['auto'] ) && ! empty( $ij['force'] ) && abs( (int) $ij['at'] - time() ) < 5, json_encode( $ij ) );
		$st1 = DBD_AutoPub::states( $n, $ks ); $ck( 'the lines: Instagram posting…, Bluesky posted; nothing left for the button', $st1['ig'][0] === 'busy' && $st1['ig'][1] === 'Instagram: posting…' && $st1['bs'][0] === 'done' && strpos( $st1['bs'][1], 'Bluesky: posted ' ) === 0 && $st1['bs'][2] === $bj['link'] && ! DBD_AutoPub::ready( $n, $ks ), json_encode( $st1 ) );
		$GLOBALS['hcalls'] = array(); DBD_Instagram::send( $key ); $ij = DBD_Instagram::job( $key ); $med = $igcalls( '178/media' );
		$ck( 'Instagram: one image post, the item’s square and caption', ( $ij['state'] ?? '' ) === 'sent' && count( $med ) === 1 && strpos( $med[0][3]['image_url'], 'card=' . $sw['sq'] ) !== false && strpos( $med[0][3]['image_url'], 'fmt=jpg' ) !== false && $med[0][3]['caption'] === DBD_Pack::ig_caption( $sw, true ) && count( $igcalls( '178/media_publish' ) ) === 1, json_encode( array( $ij, $med ) ) );
		$GLOBALS['hcalls'] = array(); $errs = DBD_AutoPub::publish( $n, $ks );
		$ck( 'pressed again: nothing posts twice', ! $errs && ! $GLOBALS['hcalls'] && DBD_AutoPub::states( $n, $ks )['ig'][0] === 'done', json_encode( $GLOBALS['hcalls'] ) );
		DBD_Instagram::send( $key ); $ck( 'the item’s own day later finds it sent and posts nothing', ! $GLOBALS['hcalls'] );
		/* an Instagram day still to come is taken over: booked for later, posted now, the day dropped */
		$k2 = null; foreach ( $items as $it ) { if ( isset( DBD_Instagram::SLOTS[ $it['k'] ] ) && $it['k'] !== $ks ) { $k2 = $it['k']; break; } }
		if ( $k2 ) {
			$key2 = DBD_AutoPub::key( $n, $k2 ); $later = time() + 3 * 86400;
			$GLOBALS['dbd_opt'][ 'dbd_ig_job_' . $key2 ] = array( 'state' => 'booked', 'at' => $later, 'slot_at' => $later, 'tries' => 0 );
			$s2 = DBD_AutoPub::states( $n, $k2 ); $ck( 'a booked day: ready, and the line says Auto publish posts it now instead', $s2['ig'][0] === 'ready' && strpos( $s2['ig'][1], 'Instagram: booked for ' ) === 0 && strpos( $s2['ig'][1], 'Auto publish posts it now instead.' ) !== false, json_encode( $s2 ) );
			DBD_AutoPub::publish( $n, $k2 ); $j2 = DBD_Instagram::job( $key2 );
			$ck( 'pressed: the day is taken over to post now', $j2['state'] === 'booked' && ! empty( $j2['auto'] ) && abs( (int) $j2['at'] - time() ) < 5 && (int) $j2['slot_at'] === $later, json_encode( $j2 ) );
			DBD_Instagram::send( $key2 ); $ck( 'it posts now', DBD_Instagram::job( $key2 )['state'] === 'sent' );
			$GLOBALS['hcalls'] = array(); DBD_Instagram::send( $key2 ); $ck( 'and its day posts nothing', ! $GLOBALS['hcalls'] && DBD_Instagram::job( $key2 )['state'] === 'sent' );
			$ck( 'a day taken by Auto publish is never booked again by a sync', ! isset( DBD_Instagram::note()[ $key2 ] ) );
		} else { echo "  (no second daily item this week; skipped the booked-day checks)\n"; }
		/* the weekly switch off: Auto publish still posts to Instagram */
		$GLOBALS['dbd_opt']['dbd_ig_set'] = array( 'on' => false ); $ck( 'with weekly posting off, the button still posts to Instagram', DBD_AutoPub::nets() === array( 'ig', 'bs' ) && ! DBD_Instagram::on() );
		/* pass rushers and a keys side: outside the weekly days, posted on Instagram by key */
		$extra = array(); if ( DBD_Pack::find( $n, 'rush' ) ) { $extra[] = 'rush'; }
		foreach ( DBD_Pack::keys( $n ) as $kg ) { if ( ! empty( $kg['sides'][0] ) ) { $extra[] = DBD_AutoPub::slot( $n, $kg['sides'][0] ); break; } }
		foreach ( $extra as $sl ) {
			$k3 = DBD_AutoPub::key( $n, $sl ); $it3 = DBD_Pack::find( $n, $sl ); $GLOBALS['hcalls'] = array();
			$ck( "$sl: its post key parses, and it has a card", DBD_Instagram::parse( $k3 ) === array( $season, $n, $sl ) && ! empty( $it3['card'] ) && DBD_Card::valid( $it3['card'] ), $k3 );
			DBD_AutoPub::publish( $n, $sl ); DBD_Instagram::send( $k3 ); $med = $igcalls( '178/media' ); $cr = $bcalls( 'com.atproto.repo.createRecord' );
			$ck( "$sl: Instagram posts its square, Bluesky its text with the card", DBD_Instagram::job( $k3 )['state'] === 'sent' && strpos( $med[0][3]['image_url'] ?? '', 'card=' . $it3['sq'] ) !== false && DBD_Bluesky::job( $k3 )['state'] === 'sent' && count( $cr ) === 1 && count( $bcalls( 'com.atproto.repo.uploadBlob' ) ) === 1, json_encode( array( DBD_Instagram::job( $k3 ), DBD_Bluesky::job( $k3 ) ) ) );
			$ck( "$sl: the label", DBD_Instagram::label( $sl ) === ( $sl === 'rush' ? 'pass rushers' : 'the ' . ( DBD_Data::team( substr( $sl, -3 ) )['nick'] ?? substr( $sl, -3 ) ) . ' keys' ) || ( strpos( $sl, 'keys-' ) === 0 && preg_match( '/^the .+ keys$/', DBD_Instagram::label( $sl ) ) ), DBD_Instagram::label( $sl ) );
		}
		if ( ! $extra ) { echo "  (no pass rushers or keys this week; skipped those checks)\n"; }
		/* Bluesky, the hard parts: a lost reply, an expired session, a card too big */
		$other = array_values( array_filter( $items, function ( $it ) use ( $ks, $k2, $extra ) { return ! in_array( $it['k'], array_merge( array( $ks, $k2 ), $extra ), true ); } ) );
		if ( count( $other ) >= 1 ) {
			$kl = DBD_AutoPub::key( $n, $other[0]['k'] ); $GLOBALS['bs']['lose'] = true; $GLOBALS['hcalls'] = array();
			$e = DBD_Bluesky::post( $kl, $other[0] ); $jl = DBD_Bluesky::job( $kl );
			$ck( 'a lost reply: kept as failed, with the record key it used', strpos( $e, 'Bluesky answered 502' ) === 0 && strpos( $jl['state'], 'failed: Bluesky answered 502' ) === 0 && strlen( $jl['rkey'] ) === 13 && DBD_AutoPub::states( $n, $other[0]['k'] )['bs'][0] === 'ready' && strpos( DBD_AutoPub::states( $n, $other[0]['k'] )['bs'][1], 'Bluesky didn’t post: Bluesky answered 502' ) === 0, json_encode( array( $e, $jl ) ) );
			$GLOBALS['hcalls'] = array(); $e = DBD_Bluesky::post( $kl, $other[0] );
			$ck( 'pressed again: it finds the post that went out, and posts nothing new', $e === '' && DBD_Bluesky::job( $kl )['state'] === 'sent' && count( $bcalls( 'com.atproto.repo.getRecord' ) ) === 1 && ! $bcalls( 'com.atproto.repo.createRecord' ) && strpos( DBD_Bluesky::job( $kl )['link'], '/post/' . $jl['rkey'] ) !== false, json_encode( $GLOBALS['hcalls'] ) );
		}
		if ( count( $other ) >= 2 ) {
			$ke = DBD_AutoPub::key( $n, $other[1]['k'] ); $GLOBALS['htr'] = array( 'dbd_bsky_session' => array( 'jwt' => 'jwt1', 'did' => 'did:plc:dbd' ) ); $GLOBALS['bs']['expire'] = 1; $GLOBALS['hcalls'] = array(); DBD_Bluesky::$max_blob = 10;
			$e = DBD_Bluesky::post( $ke, $other[1] ); $cr = $bcalls( 'com.atproto.repo.createRecord' );
			$ck( 'an expired session is made again once; a card too big posts without its image', $e === '' && DBD_Bluesky::job( $ke )['state'] === 'sent' && count( $cr ) === 2 && $cr[0][4]['Authorization'] === 'Bearer jwt1' && $cr[1][4]['Authorization'] !== 'Bearer jwt1' && ! $bcalls( 'com.atproto.repo.uploadBlob' ) && ! isset( $cr[1][3]['record']['embed']['external']['thumb'] ) && $cr[1][3]['rkey'] === $cr[0][3]['rkey'], json_encode( $GLOBALS['hcalls'] ) );
			DBD_Bluesky::$max_blob = 1000000;
		}
		/* the button's last state: every network done reads Published (checked in the admin harness by its markup); settings and saving */
		$GLOBALS['dbd_opt']['dbd_pack_bsky'] = 'someone.else.social'; $e = DBD_Bluesky::save( array() );
		$ck( 'a changed Bluesky account forgets the app password', ! DBD_Bluesky::conn() && strpos( $e, 'The Bluesky account changed' ) === 0 && DBD_AutoPub::nets() === array( 'ig' ), $e );
		$GLOBALS['dbd_opt']['dbd_pack_bsky'] = 'downbydown.bsky.social'; DBD_Bluesky::save( array( 'dbd_bsky_pass_new' => 'good-pass' ) ); $ck( 'pasting one connects it again', DBD_Bluesky::connected() );
		DBD_Bluesky::save( array( 'dbd_bsky_disconnect' => '1' ) ); $ck( 'disconnect forgets it', ! DBD_Bluesky::conn() );
		$ck( 'the networks setting: unticked means off, missing means on', DBD_AutoPub::sanitize( array( 'ig' => '1' ) ) === array( 'ig' => true, 'bs' => false, 'x' => false, 'th' => false ) && DBD_AutoPub::sanitize( 'x' ) === array( 'ig' => false, 'bs' => false, 'x' => false, 'th' => false ) );

		/* ---- Bluesky on Instagram's days (2.39.0, SCOPE §82): booking, the Tuesday thread, a daily post, the button's records, retries */
		echo "  -- Bluesky's days\n";
		foreach ( array_keys( $GLOBALS['dbd_opt'] ) as $o ) { if ( strpos( $o, 'dbd_bsky_job_' ) === 0 && $o !== 'dbd_bsky_job_' . $key ) { unset( $GLOBALS['dbd_opt'][ $o ] ); } }  /* only the swing stays, sent with Auto publish */
		unset( $GLOBALS['dbd_opt']['dbd_bsky_set'] ); $ck( 'the weekly switch is on unless unticked', DBD_Bluesky::settings()['on'] && ! DBD_Bluesky::on() && DBD_Bluesky::sanitize( array() ) === array( 'on' => false ) && DBD_Bluesky::note() === array() );
		DBD_Bluesky::save( array( 'dbd_bsky_pass_new' => 'good-pass', 'dbd_bsky_set' => array( 'on' => '1' ) ) ); $ck( 'saved ticked and connected: on', DBD_Bluesky::on() );
		$plan = DBD_Instagram::plan( $n ); $slots = array_keys( $plan ); $daily = array_values( array_diff( $slots, array( 'carousel', $ks ) ) ); $t0 = DBD_Instagram::slot_time( $n, 'carousel' ); $bkey = function ( $sl ) use ( $season, $n ) { return DBD_Instagram::key( $season, $n, $sl ); };
		$bk = DBD_Bluesky::note( null, $t0 - 600 ); $want = array(); foreach ( $slots as $sl ) { if ( $sl !== $ks ) { $want[ $bkey( $sl ) ] = DBD_Instagram::slot_time( $n, $sl ); } }
		$ck( 'a sync books the week on Instagram’s days and times, each in its own record, and not the item already sent with Auto publish', $bk === $want && in_array( 'carousel', $slots, true ) && DBD_Bluesky::job( $bkey( 'carousel' ) )['state'] === 'booked' && DBD_Bluesky::job( $key )['state'] === 'sent', json_encode( array( $bk, $want ) ) );
		$snap = array_filter( $GLOBALS['dbd_opt'], function ( $k ) { return strpos( $k, 'dbd_bsky_job_' ) === 0; }, ARRAY_FILTER_USE_KEY ); DBD_Bluesky::note( null, $t0 - 300 );
		$ck( 'a second sync books nothing new', $snap === array_filter( $GLOBALS['dbd_opt'], function ( $k ) { return strpos( $k, 'dbd_bsky_job_' ) === 0; }, ARRAY_FILTER_USE_KEY ) );
		$rows = DBD_Bluesky::week_rows( $n ); $ck( 'This week: the thread booked, the swing sent with Auto publish', strpos( $rows['carousel'][1], 'The thread: booked for ' ) === 0 && strpos( $rows[ $ks ][1] ?? 'sent with Auto publish', 'sent with Auto publish' ) !== false, json_encode( $rows ) );
		$GLOBALS['hcalls'] = array(); DBD_Bluesky::send( $key ); $ck( 'the item sent with Auto publish posts nothing on its day', ! $GLOBALS['hcalls'] );
		if ( $daily ) {
			$last = end( $daily ); unset( $GLOBALS['dbd_opt'][ 'dbd_bsky_job_' . $bkey( $last ) ] ); DBD_Bluesky::note( null, DBD_Instagram::slot_time( $n, $last ) + DBD_Instagram::LATE + 60 );
			$ck( 'a day more than a day past is skipped, not posted late', strpos( DBD_Bluesky::job( $bkey( $last ) )['state'], 'skipped' ) === 0, json_encode( DBD_Bluesky::job( $bkey( $last ) ) ) );
		}
		$now_job = function ( $sl, $extra = array() ) use ( $bkey ) { $GLOBALS['dbd_opt'][ 'dbd_bsky_job_' . $bkey( $sl ) ] = $extra + array( 'state' => 'booked', 'at' => time(), 'slot_at' => time(), 'tries' => 0 ); };
		/* the thread: the first post, then each item a reply in pack order */
		$its = $plan['carousel']; $ck_ = $bkey( 'carousel' ); $now_job( 'carousel' ); $GLOBALS['hcalls'] = array(); $GLOBALS['bs']['records'] = array();
		DBD_Bluesky::send( $ck_ ); $cr = $bcalls( 'com.atproto.repo.createRecord' ); $tj = DBD_Bluesky::job( $ck_ ); $wk = DBD_Router::url( array( 'view' => 'week', 'week' => $n ) );
		$first = $cr[0][3]['record'] ?? array(); $fi = $first['facets'][0]['index'] ?? array();
		$ck( 'the thread’s first post names the week, the week page’s link short and clickable', count( $cr ) === count( $its ) + 1 && strpos( $first['text'] ?? '', 'Week ' . $n . ' in ' ) === 0 && strpos( $first['text'], ' charts, one in each reply below. Every chart and the numbers behind it: ' . DBD_Bluesky::short_link( $wk ) ) !== false
			&& ( $first['facets'][0]['features'][0]['uri'] ?? '' ) === $wk && substr( $first['text'], $fi['byteStart'] ?? 0, ( $fi['byteEnd'] ?? 0 ) - ( $fi['byteStart'] ?? 0 ) ) === DBD_Bluesky::short_link( $wk ) && ! isset( $first['reply'] ) && ! isset( $first['embed'] ), json_encode( $first ) );
		$chain = true; $why = '';
		for ( $i = 1; $i < count( $cr ); $i++ ) {
			$r = $cr[ $i ][3]['record']; $prev = 'at://did:plc:dbd/app.bsky.feed.post/' . $cr[ $i - 1 ][3]['rkey']; $root = 'at://did:plc:dbd/app.bsky.feed.post/' . $cr[0][3]['rkey'];
			if ( ( $r['reply']['root']['uri'] ?? '' ) !== $root || ( $r['reply']['parent']['uri'] ?? '' ) !== $prev || ( $r['reply']['parent']['cid'] ?? '' ) !== 'bafyrei' . $cr[ $i - 1 ][3]['rkey'] || ( $r['embed']['external']['uri'] ?? '' ) !== DBD_Pack::share_link( $its[ $i - 1 ] ) || $r['text'] !== DBD_Bluesky::text( $its[ $i - 1 ] )[0] ) { $chain = false; $why = json_encode( $r ); break; }
		}
		$ck( 'each item replies in pack order, its parent the post before it, its root the first, with its own text and card', $chain && count( $bcalls( 'com.atproto.repo.uploadBlob' ) ) === count( $its ), $why );
		$ck( 'the thread is kept as posted, its View link the first post', $tj['state'] === 'sent' && $tj['link'] === 'https://bsky.app/profile/downbydown.bsky.social/post/' . $cr[0][3]['rkey'] && count( $tj['parts'] ) === count( $its ) + 1 && ! DBD_Bluesky::by_button( $tj ), json_encode( $tj ) );
		$ck( 'the thread keeps the items it chose on its first try', ( $tj['items'] ?? null ) === array_map( function ( $it ) { return (string) $it['k']; }, $its ), json_encode( $tj['items'] ?? null ) );
		$GLOBALS['hcalls'] = array(); DBD_Bluesky::send( $ck_, true ); $ck( 'sent again: nothing posts twice', ! $bcalls( 'com.atproto.repo.createRecord' ) );
		$ck( 'the thread’s replies don’t count as the items’ own posts', ! $daily || ( DBD_Bluesky::job( $bkey( $daily[0] ) )['state'] ?? '' ) === 'booked' );
		/* a thread that fails partway carries on from the last part that went out; a lost reply is found, not posted again */
		$now_job( 'carousel' ); $GLOBALS['bs']['records'] = array(); $GLOBALS['bs']['failn'] = 3; $GLOBALS['hcalls'] = array();
		DBD_Bluesky::send( $ck_ ); $tj = DBD_Bluesky::job( $ck_ );
		$ck( 'the third post refused: retrying, the first two kept, the third’s key kept', strpos( $tj['state'], 'retrying: Bluesky answered 500' ) === 0 && (int) $tj['tries'] === 1 && ! empty( $tj['parts'][1]['uri'] ) && empty( $tj['parts'][2]['uri'] ) && strlen( $tj['parts'][2]['rkey'] ?? '' ) === 13 && count( $GLOBALS['bs']['records'] ) === 2, json_encode( $tj ) );
		$GLOBALS['bs']['lose'] = true; DBD_Bluesky::send( $ck_ ); $tj = DBD_Bluesky::job( $ck_ );
		$ck( 'the retry carries on at the third (which goes out, its reply lost): retrying again', strpos( $tj['state'], 'retrying' ) === 0 && count( $GLOBALS['bs']['records'] ) === 3, json_encode( $tj ) );
		DBD_Bluesky::send( $ck_ ); $tj = DBD_Bluesky::job( $ck_ ); $recs = $GLOBALS['bs']['records']; $rk = array_keys( $recs );
		$ok = $tj['state'] === 'sent' && count( $recs ) === count( $its ) + 1 && strpos( $recs[ $rk[0] ]['text'], 'Week ' ) === 0 && count( array_filter( $bcalls( 'com.atproto.repo.createRecord' ), function ( $c ) use ( $rk ) { return $c[3]['rkey'] === $rk[0]; } ) ) === 1;
		for ( $i = 1; $ok && $i < count( $rk ); $i++ ) { $ok = ( $recs[ $rk[ $i ] ]['reply']['parent']['uri'] ?? '' ) === 'at://did:plc:dbd/app.bsky.feed.post/' . $rk[ $i - 1 ] && ( $recs[ $rk[ $i ] ]['reply']['root']['uri'] ?? '' ) === 'at://did:plc:dbd/app.bsky.feed.post/' . $rk[0]; }
		$ck( 'then finishes: one first post, every item once, each chained to the one before', $ok, json_encode( array( $tj, $recs ) ) );
		$now_job( 'carousel', array( 'items' => array( (string) $its[0]['k'], 'gone' ) ) ); $GLOBALS['hcalls'] = array(); DBD_Bluesky::send( $ck_ ); $tj = DBD_Bluesky::job( $ck_ );
		$ck( 'a retry whose item has left the pack fails rather than posting a short thread', strpos( $tj['state'], 'retrying: gone isn’t in the Week ' . $n . ' pack any more' ) === 0 && ! $bcalls( 'com.atproto.repo.createRecord' ), json_encode( $tj ) );
		/* a daily post: the item's Bluesky text and card, on the same record as Auto publish */
		if ( count( $daily ) >= 3 ) {
			$d1 = $daily[0]; $it1 = DBD_Pack::find( $n, $d1 ); $now_job( $d1 ); $GLOBALS['hcalls'] = array();
			DBD_Bluesky::send( $bkey( $d1 ) ); $cr = $bcalls( 'com.atproto.repo.createRecord' ); $dj = DBD_Bluesky::job( $bkey( $d1 ) );
			$ck( 'a day posts its item as Auto publish would, and the button then reads posted', $dj['state'] === 'sent' && count( $cr ) === 1 && $cr[0][3]['record']['text'] === DBD_Bluesky::text( $it1 )[0] && ! isset( $cr[0][3]['record']['reply'] ) && DBD_AutoPub::states( $n, $d1 )['bs'][0] === 'done' && ! DBD_Bluesky::by_button( $dj ), json_encode( $dj ) );
			$d2 = $daily[1]; $s2 = DBD_AutoPub::states( $n, $d2 )['bs'];
			$ck( 'a booked day: the button’s line says it posts now instead', $s2[0] === 'ready' && strpos( $s2[1], 'Bluesky: booked for ' ) === 0 && strpos( $s2[1], 'Auto publish posts it now instead.' ) !== false, json_encode( $s2 ) );
			DBD_AutoPub::publish( $n, $d2 ); $GLOBALS['hcalls'] = array(); DBD_Bluesky::send( $bkey( $d2 ) );
			$ck( 'pressed: posted now, sent with Auto publish, and its day posts nothing', DBD_Bluesky::job( $bkey( $d2 ) )['state'] === 'sent' && DBD_Bluesky::by_button( DBD_Bluesky::job( $bkey( $d2 ) ) ) && ! $GLOBALS['hcalls'] && strpos( DBD_Bluesky::week_rows( $n )[ $d2 ][1], 'sent with Auto publish' ) !== false );
			$d3 = $daily[2]; $now_job( $d3, array( 'tries' => 6 ) ); $GLOBALS['bs']['failn'] = 1; $GLOBALS['hmail'] = array(); DBD_Bluesky::send( $bkey( $d3 ) ); $fj = DBD_Bluesky::job( $bkey( $d3 ) ); $ln = DBD_Bluesky::line( $n ); $wr = DBD_Bluesky::week_rows( $n )[ $d3 ];
			$ck( 'the seventh failure: failed, a note to the test address, Try again on the line and the list', strpos( $fj['state'], 'failed: Bluesky answered 500' ) === 0 && count( $GLOBALS['hmail'] ) === 1 && strpos( $GLOBALS['hmail'][0], 'Bluesky didn’t take ' . DBD_Instagram::label( $d3 ) . ' for Week ' . $n ) !== false
				&& $ln[0] === 'bad' && $ln[3] === $bkey( $d3 ) && $wr[0] === 'bad' && $wr[3] === $bkey( $d3 ) && strpos( DBD_Bluesky::again_url( $bkey( $d3 ) ), 'action=dbd_bsky_again' ) !== false, json_encode( array( $fj, $ln, $wr, $GLOBALS['hmail'] ) ) );
			$GLOBALS['dbd_opt'][ 'dbd_bsky_job_' . $bkey( $d3 ) ] = array_merge( $fj, array( 'state' => 'retrying: again', 'tries' => 0, 'force' => true, 'slot_at' => time() - 3 * 86400 ) ); DBD_Bluesky::send( $bkey( $d3 ) );
			$ln = DBD_Bluesky::line( $n );
			$ck( 'Try again posts it, even past its cutoff; the line says what posted last', DBD_Bluesky::job( $bkey( $d3 ) )['state'] === 'sent' && $ln[0] === 'ok' && strpos( $ln[1], 'Bluesky: posted ' . DBD_Instagram::label( $d3 ) . ' ' ) === 0 && $ln[2] !== '', json_encode( $ln ) );
		} else { echo "  (fewer than three daily items this week; skipped the daily checks)\n"; }
		/* switched off: nothing books or posts on a day; the button still posts */
		$GLOBALS['dbd_opt']['dbd_bsky_set'] = array( 'on' => false ); $ox = $bkey( 'carousel' ); $now_job( 'carousel' ); $GLOBALS['hcalls'] = array(); DBD_Bluesky::send( $ox );
		unset( $GLOBALS['dbd_opt'][ 'dbd_bsky_job_' . $ox ] );
		$ck( 'switched off: a booked day posts nothing, a sync books nothing, there’s no line, and the button still posts to Bluesky', ! $GLOBALS['hcalls'] && DBD_Bluesky::note( null, $t0 - 600 ) === array() && ! DBD_Bluesky::job( $ox ) && DBD_Bluesky::line( $n ) === null && in_array( 'bs', DBD_AutoPub::nets(), true ) );
		unset( $GLOBALS['dbd_opt']['dbd_bsky_set'] );

		/* ---- X (2.40.0, SCOPE §83): the signature, connecting, Auto publish, its days, the thread, a lost reply, a repeat, retries */
		echo "  -- X\n";
		$xsig = DBD_X::sign( 'POST', 'https://api.twitter.com/1.1/statuses/update.json', array( 'include_entities' => 'true', 'status' => 'Hello Ladies + Gentlemen, a signed OAuth request!' ), 'xvz1evFS4wEEPTGEFPHBog', 'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3Z7kBw',
			'370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb', 'LswwdoUaIvS8ltyTt5jkRh4J50vUPVVHtR2YPi5kE', 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg', 1318622958 );
		$ck( 'the OAuth 1.0a signature matches X’s published example', strpos( $xsig, 'oauth_signature="hCtSmYh%2BiHYCEqBWrE7C7hYmtUk%3D"' ) !== false && strpos( $xsig, 'OAuth oauth_consumer_key="xvz1evFS4wEEPTGEFPHBog", oauth_nonce=' ) === 0, $xsig );
		$ck( 'not connected: X isn’t on the button', ! DBD_X::connected() && ! in_array( 'x', DBD_AutoPub::nets(), true ) );
		$xk = function ( $at ) { return array( 'dbd_x_set' => array( 'on' => '1' ), 'dbd_x_ck_new' => 'ck1', 'dbd_x_cs_new' => 'cs1', 'dbd_x_at_new' => $at, 'dbd_x_as_new' => 'as1' ); };
		$e = DBD_X::save( array( 'dbd_x_ck_new' => 'ck1', 'dbd_x_at_new' => 'at1' ) ); $ck( 'two keys of four: asked for all four, nothing kept', strpos( $e, 'Paste all four X keys together' ) === 0 && ! DBD_X::conn(), $e );
		$e = DBD_X::save( $xk( 'at-other' ) ); $ck( 'keys for another account: refused, nothing kept', strpos( $e, 'Those X keys are for @someone_else, not @dxd_NFLfootball.' ) === 0 && ! DBD_X::conn(), $e );
		$e = DBD_X::save( $xk( 'at1' ) ); $xc = DBD_X::conn();
		$ck( 'the right keys: kept with the account’s id; the button posts to all three', $e === '' && DBD_X::connected() && $xc['id'] === '99' && $xc['as'] === 'as1' && DBD_AutoPub::nets() === array( 'ig', 'bs', 'x' ), json_encode( array( $e, $xc ) ) );
		$ck( 'the ask names the networks in a sentence', DBD_AutoPub::names( array( 'ig', 'bs', 'x' ) ) === 'Instagram, Bluesky and X' && DBD_AutoPub::names( array( 'ig', 'x' ) ) === 'Instagram and X' && DBD_AutoPub::names( array( 'bs' ) ) === 'Bluesky' );
		$xcalls = function () { return array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) { return $c[0] === 'x' && $c[2] === 'tweets'; } ) ); };
		$xkey = function ( $sl ) use ( $season, $n ) { return DBD_Instagram::key( $season, $n, $sl ); };
		$xnow = function ( $sl, $extra = array() ) use ( $xkey ) { $GLOBALS['dbd_opt'][ 'dbd_x_job_' . $xkey( $sl ) ] = $extra + array( 'state' => 'booked', 'at' => time(), 'slot_at' => time(), 'tries' => 0 ); };
		/* Auto publish to X */
		$GLOBALS['hcalls'] = array(); $xs0 = DBD_AutoPub::states( $n, $ks )['x'] ?? array(); DBD_AutoPub::publish( $n, $ks ); $xj = DBD_X::job( $key ); $tw = $xcalls();
		$ck( 'Auto publish posts the item’s X text to X, sent with the button', ( $xs0[0] ?? '' ) === 'ready' && count( $tw ) === 1 && $tw[0][3]['text'] === DBD_Pack::social( $sw )['x'] && ! isset( $tw[0][3]['reply'] ) && ( $xj['state'] ?? '' ) === 'sent' && DBD_X::by_button( $xj ) && $xj['link'] === 'https://x.com/dxd_NFLfootball/status/' . $xj['id'] && DBD_X::month() === 1, json_encode( array( $xs0, $xj, $tw ) ) );
		$GLOBALS['hcalls'] = array(); DBD_AutoPub::publish( $n, $ks ); $ck( 'pressed again: nothing posts twice', ! $xcalls() && DBD_AutoPub::states( $n, $ks )['x'][0] === 'done' );
		/* the days */
		$bk = DBD_X::note( null, $t0 - 600 ); $want = array(); foreach ( $slots as $sl ) { if ( $sl !== $ks ) { $want[ $xkey( $sl ) ] = DBD_Instagram::slot_time( $n, $sl ); } }
		$ck( 'a sync books X on the same days and times, not the item already sent with the button', $bk === $want, json_encode( array( $bk, $want ) ) );
		/* the thread: the first post, then each item a numbered reply to the one before */
		$GLOBALS['xs']['posts'] = array(); $xnow( 'carousel' ); $GLOBALS['hcalls'] = array(); DBD_X::send( $xkey( 'carousel' ) ); $tw = $xcalls(); $tj = DBD_X::job( $xkey( 'carousel' ) ); $c = count( $its );
		$ok = count( $tw ) === $c + 1 && $tw[0][3]['text'] === DBD_X::intro( $n, $c ) && strpos( $tw[0][3]['text'], 'one in each reply below. Every chart and the numbers behind it: ' . $wk ) !== false && ! isset( $tw[0][3]['reply'] ); $why = json_encode( $tw[0][3] ?? null );
		for ( $i = 1; $ok && $i <= $c; $i++ ) { $t = $tw[ $i ][3]; $ok = strpos( $t['text'], $i . '/' . $c . ' ' ) === 0 && DBD_Pack::xlen( $t['text'] ) <= 280 && strpos( $t['text'], DBD_Pack::share_link( $its[ $i - 1 ] ) ) !== false && ( $t['reply']['in_reply_to_tweet_id'] ?? '' ) === $GLOBALS['xs']['posts'][ $i - 1 ]['id']; if ( ! $ok ) { $why = json_encode( $t ); } }
		$ck( 'the thread: the week’s first post, then each item numbered, replying to the one before, 280 at most', $ok, $why );
		$ck( 'kept as posted, its View link the first post, its items kept', $tj['state'] === 'sent' && $tj['link'] === 'https://x.com/dxd_NFLfootball/status/' . $GLOBALS['xs']['posts'][0]['id'] && count( $tj['parts'] ) === $c + 1 && ( $tj['items'] ?? null ) === array_map( function ( $it ) { return (string) $it['k']; }, $its ), json_encode( $tj ) );
		/* a refused third post, then a lost reply: the retry carries on and finds the lost one among the latest posts */
		$GLOBALS['xs']['posts'] = array(); $xnow( 'carousel' ); $GLOBALS['xs']['failn'] = 3; DBD_X::send( $xkey( 'carousel' ) ); $tj = DBD_X::job( $xkey( 'carousel' ) );
		$ck( 'the third post refused: retrying, two kept, the third marked tried', strpos( $tj['state'], 'retrying: X answered 503' ) === 0 && count( $GLOBALS['xs']['posts'] ) === 2 && ! empty( $tj['parts'][2]['tried'] ) && empty( $tj['parts'][2]['id'] ), json_encode( $tj ) );
		$GLOBALS['xs']['lose'] = true; DBD_X::send( $xkey( 'carousel' ) ); $ck( 'the third goes out but its reply is lost: retrying', strpos( DBD_X::job( $xkey( 'carousel' ) )['state'], 'retrying: X answered 502' ) === 0 && count( $GLOBALS['xs']['posts'] ) === 3 );
		$GLOBALS['hcalls'] = array(); DBD_X::send( $xkey( 'carousel' ) ); $tj = DBD_X::job( $xkey( 'carousel' ) ); $P = $GLOBALS['xs']['posts'];
		$ok = $tj['state'] === 'sent' && count( $P ) === $c + 1 && count( array_unique( array_column( $P, 'text' ) ) ) === $c + 1 && $tj['parts'][2]['id'] === $P[2]['id'];
		for ( $i = 1; $ok && $i <= $c; $i++ ) { $ok = $P[ $i ]['reply'] === $P[ $i - 1 ]['id']; }
		$ck( 'then it finds the third among the latest posts and finishes: every post once, each replying to the one before', $ok && count( array_filter( $GLOBALS['hcalls'], function ( $x ) { return $x[0] === 'x' && $x[2] === 'users/99/tweets'; } ) ) === 1, json_encode( array( $tj, $P ) ) );
		if ( count( $daily ) >= 3 ) {
			list( $d1, $d2, $d3 ) = $daily; $it1 = DBD_Pack::find( $n, $d1 );
			$xnow( $d1 ); $GLOBALS['hcalls'] = array(); DBD_X::send( $xkey( $d1 ) ); $tw = $xcalls(); $dj = DBD_X::job( $xkey( $d1 ) );
			$ck( 'a day posts its item’s X text, not the thread’s numbered reply', $dj['state'] === 'sent' && count( $tw ) === 1 && $tw[0][3]['text'] === DBD_Pack::social( $it1 )['x'] && ! DBD_X::by_button( $dj ) && DBD_AutoPub::states( $n, $d1 )['x'][0] === 'done', json_encode( array( $dj, $tw ) ) );
			/* X refuses a repeat: the post it repeats is taken as this one */
			$it2 = DBD_Pack::find( $n, $d2 ); $GLOBALS['xs']['posts'][] = array( 'id' => '555', 'text' => DBD_Pack::social( $it2 )['x'], 'reply' => '' ); $xnow( $d2 ); $GLOBALS['hcalls'] = array(); DBD_X::send( $xkey( $d2 ) );
			$ck( 'refused as a repeat: found among the latest posts and kept as sent, nothing new', DBD_X::job( $xkey( $d2 ) )['state'] === 'sent' && DBD_X::job( $xkey( $d2 ) )['id'] === '555' && count( $xcalls() ) === 1, json_encode( DBD_X::job( $xkey( $d2 ) ) ) );
			$xnow( $d3, array( 'tries' => 6 ) ); $GLOBALS['xs']['failn'] = 1; $GLOBALS['hmail'] = array(); DBD_X::send( $xkey( $d3 ) ); $fj = DBD_X::job( $xkey( $d3 ) ); $ln = DBD_X::line( $n ); $wr = DBD_X::week_rows( $n )[ $d3 ];
			$ck( 'the seventh failure: failed, the note, Try again on the line and the list', strpos( $fj['state'], 'failed: X answered 503' ) === 0 && strpos( $GLOBALS['hmail'][0] ?? '', 'X didn’t take ' . DBD_Instagram::label( $d3 ) ) !== false && $ln[3] === $xkey( $d3 ) && $wr[3] === $xkey( $d3 ) && strpos( DBD_X::again_url( $xkey( $d3 ) ), 'action=dbd_x_again' ) !== false, json_encode( array( $fj, $ln, $wr ) ) );
			$GLOBALS['dbd_opt'][ 'dbd_x_job_' . $xkey( $d3 ) ] = array_merge( $fj, array( 'state' => 'retrying: again', 'tries' => 0, 'force' => true ) ); DBD_X::send( $xkey( $d3 ) ); $ln = DBD_X::line( $n );
			$ck( 'Try again posts it; the line says what posted last', DBD_X::job( $xkey( $d3 ) )['state'] === 'sent' && strpos( $ln[1], 'X: posted ' . DBD_Instagram::label( $d3 ) . ' ' ) === 0, json_encode( $ln ) );
			$ck( 'this month’s count: every post that went out, those whose reply was lost too', DBD_X::month() === 2 * $c + 6, DBD_X::month() . ' (want ' . ( 2 * $c + 6 ) . ')' );
		} else { echo "  (fewer than three daily items this week; skipped the X daily checks)\n"; }
		$GLOBALS['dbd_opt']['dbd_x_set'] = array( 'on' => false ); $xnow( 'carousel' ); $GLOBALS['hcalls'] = array(); DBD_X::send( $xkey( 'carousel' ) ); unset( $GLOBALS['dbd_opt'][ 'dbd_x_job_' . $xkey( 'carousel' ) ] );
		$ck( 'switched off: a booked day posts nothing, a sync books nothing, no line; the button still posts to X', ! $xcalls() && DBD_X::note( null, $t0 - 600 ) === array() && DBD_X::line( $n ) === null && in_array( 'x', DBD_AutoPub::nets(), true ) );
		unset( $GLOBALS['dbd_opt']['dbd_x_set'] );
		if ( count( $daily ) >= 4 ) {  /* a retry keeps the text it tried, though a sync changed the item since: the lost post is found, not posted again in new words */
			$d4 = $daily[3]; $it4 = DBD_Pack::find( $n, $d4 ); $old = DBD_Pack::social( $it4 )['x'];
			$xnow( $d4, array( 'tried' => time() - 600, 'text' => $old ) ); $GLOBALS['xs']['posts'][] = array( 'id' => '777', 'text' => $old, 'reply' => '' );
			$GLOBALS['hcalls'] = array(); DBD_X::send( $xkey( $d4 ) ); $j4 = DBD_X::job( $xkey( $d4 ) );
			$ck( 'a retry looks for the text it tried and finds the lost post', $j4['state'] === 'sent' && $j4['id'] === '777' && ! $xcalls(), json_encode( $j4 ) );
		}
		$GLOBALS['dbd_opt']['dbd_pack_x'] = 'someone_else'; $e = DBD_X::save( array( 'dbd_x_set' => array( 'on' => '1' ), 'dbd_x_ck_new' => 'ck1' ) );
		$ck( 'the account changed and only some keys pasted: the old keys are forgotten anyway', ! DBD_X::conn() && strpos( $e, 'Paste all four X keys together' ) === 0, $e );
		unset( $GLOBALS['dbd_opt']['dbd_pack_x'] ); DBD_X::save( $xk( 'at1' ) );
		$GLOBALS['dbd_opt']['dbd_pack_x'] = 'someone_else'; $e = DBD_X::save( array( 'dbd_x_set' => array( 'on' => '1' ) ) );
		$ck( 'a changed X account forgets the keys', ! DBD_X::conn() && strpos( $e, 'The X account changed' ) === 0, $e ); unset( $GLOBALS['dbd_opt']['dbd_pack_x'] );

		/* ---- Threads (2.41.0, SCOPE §84): connecting, renewing, Auto publish, its days, the thread, a lost reply, retries */
		echo "  -- Threads\n"; DBD_Threads::$wait = function () {}; $F =& $GLOBALS['thf'];
		$tcalls = function ( $p ) { return array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) use ( $p ) { return $c[0] === 'th' && $c[2] === $p && $c[1] === 'POST'; } ) ); };
		$tkey = function ( $sl ) use ( $season, $n ) { return DBD_Instagram::key( $season, $n, $sl ); };
		$tnow = function ( $sl, $extra = array() ) use ( $tkey ) { $GLOBALS['dbd_opt'][ 'dbd_th_job_' . $tkey( $sl ) ] = $extra + array( 'state' => 'booked', 'at' => time(), 'slot_at' => time(), 'tries' => 0 ); };
		$ck( 'Your accounts: Threads is the Instagram name by default; a pasted address gives the name', DBD_Pack::our_th() === 'down_by_down' && DBD_Pack::sanitize_th( 'https://www.threads.com/@down_by_down' ) === 'down_by_down' && DBD_Pack::sanitize_th( '@Some.One' ) === 'Some.One' );
		$ck( 'not connected: Threads isn’t on the button', ! DBD_Threads::connected() && ! in_array( 'th', DBD_AutoPub::nets(), true ) );
		$tk = function ( $tok, $sec = 'sec1' ) { return array( 'dbd_th_set' => array( 'on' => '1' ), 'dbd_th_token_new' => $tok, 'dbd_th_secret_new' => $sec ); };
		$e = DBD_Threads::save( $tk( 'short1', '' ) ); $ck( 'a token without the App secret: asked for it', strpos( $e, 'Paste the app’s Threads App secret' ) === 0 && ! DBD_Threads::conn(), $e );
		$e = DBD_Threads::save( $tk( 'other' ) ); $ck( 'a token for another account: refused', strpos( $e, 'That Threads token is for @someone_else, not @down_by_down.' ) === 0 && ! DBD_Threads::conn(), $e );
		$e = DBD_Threads::save( $tk( 'long-gen' ) ); $tg = DBD_Threads::conn();
		$ck( 'a token from the User Token Generator (60 days already, can’t be exchanged): kept as it is, good for 60 days', $e === '' && $tg['token'] === 'long-gen' && abs( (int) $tg['expires'] - time() - 60 * 86400 ) < 5 && DBD_Threads::connected(), json_encode( array( $e, $tg ) ) );
		DBD_Threads::disconnect();
		$e = DBD_Threads::save( $tk( 'short1' ) ); $tc = DBD_Threads::conn();
		$ck( 'the right token and secret: made a 60-day token, kept; the button posts to Threads (X was disconnected above)', $e === '' && $tc['token'] === 'long1' && $tc['id'] === 'th99' && abs( (int) $tc['expires'] - time() - 5184000 ) < 5 && DBD_AutoPub::nets() === array( 'ig', 'bs', 'th' ) && DBD_AutoPub::names( array( 'ig', 'bs', 'x', 'th' ) ) === 'Instagram, Bluesky, X and Threads', json_encode( array( $e, $tc, DBD_AutoPub::nets() ) ) );
		$ck( 'the weekly renewal: a new token, 60 more days', DBD_Threads::refresh() && DBD_Threads::conn()['token'] === 'long2' && DBD_Threads::warning() === '' );
		$F['refail'] = true; $ck( 'a failed renewal is kept and warned about', ! DBD_Threads::refresh() && strpos( DBD_Threads::warning(), 'The Threads token didn’t renew (Threads answered 400: Session has expired)' ) === 0 && DBD_Threads::conn()['token'] === 'long2', DBD_Threads::warning() ); $F['refail'] = false; DBD_Threads::refresh();
		/* Auto publish to Threads: the long takeaway, the page as the card, no link in the text */
		$GLOBALS['hcalls'] = array(); DBD_AutoPub::publish( $n, $ks ); $cr = $tcalls( 'th99/threads' ); $tj = DBD_Threads::job( $key );
		$ck( 'Auto publish posts the long takeaway with the page as its card, sent with the button', count( $cr ) === 1 && $cr[0][3]['media_type'] === 'TEXT' && $cr[0][3]['text'] === (string) ( $sw['tl'] ?? $sw['title'] ) && $cr[0][3]['link_attachment'] === DBD_Pack::share_link( $sw ) && strpos( $cr[0][3]['text'], 'http' ) === false && count( $tcalls( 'th99/threads_publish' ) ) === 1
			&& ( $tj['state'] ?? '' ) === 'sent' && DBD_Threads::by_button( $tj ) && $tj['link'] === 'https://www.threads.com/@down_by_down/post/' . $tj['id'], json_encode( array( $cr, $tj ) ) );
		$GLOBALS['hcalls'] = array(); DBD_AutoPub::publish( $n, $ks ); $ck( 'pressed again: nothing posts twice', ! $tcalls( 'th99/threads' ) && DBD_AutoPub::states( $n, $ks )['th'][0] === 'done' );
		$bk = DBD_Threads::note( null, $t0 - 600 ); $want = array(); foreach ( $slots as $sl ) { if ( $sl !== $ks ) { $want[ $tkey( $sl ) ] = DBD_Instagram::slot_time( $n, $sl ); } }
		$ck( 'a sync books Threads on the same days and times, not the item already sent with the button', $bk === $want, json_encode( array( $bk, $want ) ) );
		/* the thread */
		$F['posts'] = array(); $F['c'] = array(); $tnow( 'carousel' ); $GLOBALS['hcalls'] = array(); DBD_Threads::send( $tkey( 'carousel' ) ); $cr = $tcalls( 'th99/threads' ); $tj = DBD_Threads::job( $tkey( 'carousel' ) ); $P = array_keys( $F['posts'] ); $c = count( $its );
		$ok = count( $cr ) === $c + 1 && $cr[0][3]['text'] === DBD_Threads::intro( $n, $c ) && $cr[0][3]['link_attachment'] === $wk && ! isset( $cr[0][3]['reply_to_id'] ); $why = json_encode( $cr[0][3] ?? null );
		for ( $i = 1; $ok && $i <= $c; $i++ ) { $t = $cr[ $i ][3]; $ok = strpos( $t['text'], $i . '/' . $c . ' ' ) === 0 && mb_strlen( $t['text'] ) <= 500 && $t['link_attachment'] === DBD_Pack::share_link( $its[ $i - 1 ] ) && $t['reply_to_id'] === $P[ $i - 1 ]; if ( ! $ok ) { $why = json_encode( $t ); } }
		$ck( 'the thread: the week’s first post with the week page as its card, then each item numbered, replying to the one before', $ok, $why );
		$ck( 'kept as posted, its View link the first post, its items kept', $tj['state'] === 'sent' && $tj['link'] === 'https://www.threads.com/@down_by_down/post/' . $P[0] && count( $tj['parts'] ) === $c + 1 && ( $tj['items'] ?? null ) === array_map( function ( $it ) { return (string) $it['k']; }, $its ), json_encode( $tj ) );
		/* no reply to a reply: the replies go under the first post */
		$F['posts'] = array(); $F['c'] = array(); $F['norr'] = true; $tnow( 'carousel' ); DBD_Threads::send( $tkey( 'carousel' ) ); $P = $F['posts']; $k0 = array_keys( $P )[0];
		$ck( 'when Threads won’t take a reply to a reply, each goes under the first post', DBD_Threads::job( $tkey( 'carousel' ) )['state'] === 'sent' && count( $P ) === $c + 1 && count( array_filter( $P, function ( $p0 ) use ( $k0 ) { return $p0['reply'] === $k0; } ) ) === $c, json_encode( $P ) ); $F['norr'] = false;
		/* a refused third part, then a lost reply: carries on, finds the published one, posts nothing twice */
		$F['posts'] = array(); $F['c'] = array(); $tnow( 'carousel' ); $F['failn'] = 3; DBD_Threads::send( $tkey( 'carousel' ) ); $tj = DBD_Threads::job( $tkey( 'carousel' ) );
		$ck( 'the third refused: retrying, two kept', strpos( $tj['state'], 'retrying: Threads answered 500' ) === 0 && count( $F['posts'] ) === 2 && empty( $tj['parts'][2]['id'] ), json_encode( $tj ) );
		$F['lose'] = true; $GLOBALS['hcalls'] = array(); DBD_Threads::send( $tkey( 'carousel' ) ); $tj = DBD_Threads::job( $tkey( 'carousel' ) ); $P = $F['posts']; $pk = array_keys( $P );
		$ok = $tj['state'] === 'sent' && count( $P ) === $c + 1 && count( array_unique( array_column( $P, 'text' ) ) ) === $c + 1 && $tj['parts'][2]['id'] === $pk[2];
		for ( $i = 1; $ok && $i <= $c; $i++ ) { $ok = $P[ $pk[ $i ] ]['reply'] === $pk[ $i - 1 ]; }
		$ck( 'the retry carries on at the third, whose publish reply is lost: Threads says published, it’s found among the replies, and the thread finishes with every post once', $ok && count( $tcalls( 'th99/threads' ) ) === $c - 1 && count( $tcalls( 'th99/threads_publish' ) ) === $c - 1, json_encode( array( $tj, $P ) ) );
		if ( count( $daily ) >= 3 ) {
			list( $d1, $d2, $d3 ) = $daily; $it1 = DBD_Pack::find( $n, $d1 );
			$tnow( $d1 ); $GLOBALS['hcalls'] = array(); DBD_Threads::send( $tkey( $d1 ) ); $cr = $tcalls( 'th99/threads' ); $dj = DBD_Threads::job( $tkey( $d1 ) );
			$ck( 'a day posts its item’s long takeaway, not the thread’s numbered reply', $dj['state'] === 'sent' && count( $cr ) === 1 && $cr[0][3]['text'] === (string) ( $it1['tl'] ?? $it1['title'] ) && ! isset( $cr[0][3]['reply_to_id'] ) && ! DBD_Threads::by_button( $dj ) && DBD_AutoPub::states( $n, $d1 )['th'][0] === 'done', json_encode( array( $dj, $cr ) ) );
			$before = count( $F['posts'] ); $tnow( $d2 ); $F['lose'] = true; DBD_Threads::send( $tkey( $d2 ) ); $j2 = DBD_Threads::job( $tkey( $d2 ) );
			$ck( 'a daily post whose publish reply was lost is found among the latest posts: sent, once', $j2['state'] === 'sent' && count( $F['posts'] ) === $before + 1 && isset( $F['posts'][ $j2['id'] ] ) && $F['posts'][ $j2['id'] ]['reply'] === '', json_encode( $j2 ) );
			if ( count( $daily ) >= 4 ) {  /* a container Threads already published on a try whose answer never came: the next try asks about it first and finds the post */
				$d4 = $daily[3]; $it4 = DBD_Pack::find( $n, $d4 ); $t4 = DBD_Threads::text( $it4 );
				$F['c']['cZ'] = array( 'p' => array( 'text' => $t4 ), 'status' => 'PUBLISHED', 'post' => 'pZ' ); $F['posts']['pZ'] = array( 'text' => $t4, 'reply' => '', 'link' => '' );
				$tnow( $d4, array( 'container' => 'cZ', 'params' => array( 'media_type' => 'TEXT', 'text' => $t4, 'link_attachment' => DBD_Pack::share_link( $it4 ) ), 'tries' => 1 ) ); $GLOBALS['hcalls'] = array(); DBD_Threads::send( $tkey( $d4 ) ); $j4 = DBD_Threads::job( $tkey( $d4 ) );
				$ck( 'a try already published: asked about first, found, nothing posted again', $j4['state'] === 'sent' && $j4['id'] === 'pZ' && ! $tcalls( 'th99/threads' ) && ! $tcalls( 'th99/threads_publish' ), json_encode( $j4 ) );
			}
			$tnow( $d3, array( 'tries' => 6 ) ); $F['failn'] = 1; $GLOBALS['hmail'] = array(); DBD_Threads::send( $tkey( $d3 ) ); $fj = DBD_Threads::job( $tkey( $d3 ) ); $ln = DBD_Threads::line( $n ); $wr = DBD_Threads::week_rows( $n )[ $d3 ];
			$ck( 'the seventh failure: failed, the note, Try again on the line and the list', strpos( $fj['state'], 'failed: Threads answered 500' ) === 0 && strpos( $GLOBALS['hmail'][0] ?? '', 'Threads didn’t take ' . DBD_Instagram::label( $d3 ) ) !== false && $ln[3] === $tkey( $d3 ) && $wr[3] === $tkey( $d3 ) && strpos( DBD_Threads::again_url( $tkey( $d3 ) ), 'action=dbd_th_again' ) !== false, json_encode( array( $fj, $ln, $wr ) ) );
			$GLOBALS['dbd_opt'][ 'dbd_th_job_' . $tkey( $d3 ) ] = array_merge( $fj, array( 'state' => 'retrying: again', 'tries' => 0, 'force' => true ) ); DBD_Threads::send( $tkey( $d3 ) ); $ln = DBD_Threads::line( $n );
			$ck( 'Try again posts it; the line says what posted last', DBD_Threads::job( $tkey( $d3 ) )['state'] === 'sent' && $ln[0] === 'ok' && strpos( $ln[1], 'Threads: posted ' ) === 0 && $ln[2] !== '', json_encode( $ln ) );  /* several post in the same second here, so any of them may be "last" */
		} else { echo "  (fewer than three daily items this week; skipped the Threads daily checks)\n"; }
		$GLOBALS['dbd_opt']['dbd_th_set'] = array( 'on' => false ); $tnow( 'carousel' ); $GLOBALS['hcalls'] = array(); DBD_Threads::send( $tkey( 'carousel' ) ); unset( $GLOBALS['dbd_opt'][ 'dbd_th_job_' . $tkey( 'carousel' ) ] );
		$ck( 'switched off: a booked day posts nothing, a sync books nothing, no line; the button still posts to Threads', ! $tcalls( 'th99/threads' ) && DBD_Threads::note( null, $t0 - 600 ) === array() && DBD_Threads::line( $n ) === null && in_array( 'th', DBD_AutoPub::nets(), true ) );
		unset( $GLOBALS['dbd_opt']['dbd_th_set'] );
		$GLOBALS['dbd_opt']['dbd_pack_th'] = 'someone_else'; $e = DBD_Threads::save( array( 'dbd_th_set' => array( 'on' => '1' ), 'dbd_th_token_new' => 'short1' ) );
		$ck( 'a changed Threads account forgets the token first, whatever else was pasted', ! DBD_Threads::conn() && strpos( $e, 'Paste the app’s Threads App secret' ) === 0, $e ); unset( $GLOBALS['dbd_opt']['dbd_pack_th'] );
		/* ---- the tall graphics on every network (SCOPE §85) */
		DBD_Bluesky::$max_blob = 1000000; DBD_Tall::$off = false; DBD_Pack::$now = 0; $GLOBALS['xs']['posts'] = array(); $GLOBALS['thf']['posts'] = array(); $GLOBALS['thf']['c'] = array(); $GLOBALS['bs']['records'] = array();
		$GLOBALS['dbd_opt']['dbd_bsky_conn'] = array( 'pass' => 'good-pass', 'handle' => 'downbydown.bsky.social', 'did' => 'did:plc:dbd', 'checked' => time() );
		$GLOBALS['dbd_opt']['dbd_x_conn'] = array( 'ck' => 'ck1', 'cs' => 'cs1', 'at' => 'at1', 'as' => 'as1', 'id' => '99', 'username' => 'dxd_NFLfootball', 'checked' => time() ); $GLOBALS['dbd_opt']['dbd_pack_x'] = 'dxd_NFLfootball';
		$GLOBALS['dbd_opt']['dbd_th_conn'] = array( 'token' => 'long1', 'secret' => 'sec1', 'id' => 'th99', 'username' => 'down_by_down', 'expires' => time() + 40 * 86400, 'checked' => time(), 'err' => '' );
		unset( $GLOBALS['dbd_opt']['dbd_ap_set'] ); $bi = DBD_Pack::find( $n, 'board' ); $bk = DBD_Instagram::key( $season, $n, 'board' );
		$ck( 'League Leaders: on the plan for Monday 9:00 AM, not in the carousel', $bi && isset( DBD_Instagram::plan( $n )['board'] ) && ! in_array( 'board', array_column( DBD_Instagram::plan( $n )['carousel'] ?? array(), 'k' ), true ) && wp_date( 'D G:i', DBD_Instagram::slot_time( $n, 'board' ), new DateTimeZone( 'America/New_York' ) ) === 'Mon 9:00' );
		foreach ( array( 'dbd_ig_job_', 'dbd_bsky_job_', 'dbd_x_job_', 'dbd_th_job_' ) as $o ) { unset( $GLOBALS['dbd_opt'][ $o . $bk ] ); } $GLOBALS['hcalls'] = array();
		$errs = DBD_AutoPub::publish( $n, 'board' ); DBD_Instagram::send( $bk, true ); $im = $igcalls( '178/media' ); $rc = $bcalls( 'com.atproto.repo.createRecord' ); $em = $rc[0][3]['record']['embed'] ?? array();
		$tc = array_values( array_filter( $GLOBALS['hcalls'], function ( $x ) { return $x[0] === 'th' && $x[1] === 'POST' && $x[2] === 'th99/threads'; } ) ); $tp = $tc[0][3] ?? array(); $tkey = 'tall-week-' . $n . '-black-board';
		$ck( 'Auto publish reaches all four, no errors', ! $errs && DBD_Instagram::job( $bk )['state'] === 'sent' && DBD_Bluesky::job( $bk )['state'] === 'sent' && DBD_X::job( $bk )['state'] === 'sent' && DBD_Threads::job( $bk )['state'] === 'sent', json_encode( $errs ) );
		$ck( 'Instagram gets the tall JPEG', strpos( $im[0][3]['image_url'] ?? '', 'card=' . $tkey ) !== false && strpos( $im[0][3]['image_url'], 'fmt=jpg' ) !== false, json_encode( $im[0][3] ?? null ) );
		$ck( 'Bluesky: an image embed at 4:5 with alt text, the link still in a facet', ( $em['$type'] ?? '' ) === 'app.bsky.embed.images' && ( $em['images'][0]['aspectRatio'] ?? null ) === array( 'width' => 1080, 'height' => 1350 ) && ( $em['images'][0]['alt'] ?? '' ) === $bi['tl'] && ( $em['images'][0]['image']['mimeType'] ?? '' ) === 'image/jpeg' && ! empty( $rc[0][3]['record']['facets'] ), json_encode( $em ) );
		$ck( 'X stays text and link until its switch is on', empty( $GLOBALS['xs']['up'] ) && $GLOBALS['xs']['posts'][0]['media'] === array(), json_encode( $GLOBALS['xs']['posts'] ) );
		$ck( 'Threads: an IMAGE post with the link in its text, 500 at most', ( $tp['media_type'] ?? '' ) === 'IMAGE' && strpos( $tp['image_url'] ?? '', 'card=' . $tkey ) !== false && strpos( $tp['text'] ?? '', DBD_Pack::share_link( $bi ) ) !== false && mb_strlen( $tp['text'] ) <= 500 && ! isset( $tp['link_attachment'] ), json_encode( $tp ) );
		$GLOBALS['dbd_opt']['dbd_x_set'] = array( 'on' => true, 'img' => true ); unset( $GLOBALS['dbd_opt'][ 'dbd_x_job_' . $bk ] ); $GLOBALS['xs']['posts'] = array(); $GLOBALS['xs']['failn'] = 1; $GLOBALS['hcalls'] = array();
		$e1 = DBD_X::post( $bk, $bi ); $j1 = DBD_X::job( $bk ); $e2 = DBD_X::post( $bk, $bi ); $xup = array_filter( $GLOBALS['hcalls'], function ( $x ) { return $x[0] === 'x' && $x[2] === 'media/upload'; } );
		$ck( 'X with the switch on: uploads once, retries with the same upload, the post carries it', $e1 !== '' && ( $j1['mid'] ?? '' ) !== '' && $e2 === '' && count( $xup ) === 1 && $GLOBALS['xs']['posts'][0]['media'] === array( $j1['mid'] ), json_encode( $GLOBALS['xs']['posts'] ) );
		$kg0 = null; foreach ( DBD_Pack::keys( $n ) as $kg ) { if ( ! DBD_Router::is_final( $kg['g'] ) ) { $kg0 = $kg; break; } }
		if ( $kg0 ) {
			$sd = $kg0['sides'][0]; $sl = DBD_AutoPub::slot( $n, $sd ); $tick = strtotime( DBD_Data::kickoff( $kg0['g'] )['utc'] );
			foreach ( array( 'dbd_ig_job_', 'dbd_bsky_job_', 'dbd_x_job_', 'dbd_th_job_' ) as $o ) { unset( $GLOBALS['dbd_opt'][ $o . DBD_AutoPub::key( $n, $sl ) ] ); }  /* a clean side, whatever the checks above posted */
			DBD_Pack::$now = $tick - 60; $before = DBD_Pack::find( $n, $sl ) !== null && DBD_AutoPub::ready( $n, $sl, $sd ) !== array() && DBD_Card::render( DBD_Tall::key( $n, $sl ) ) !== null;
			DBD_Pack::$now = $tick; $after = DBD_Pack::find( $n, $sl ) === null && DBD_AutoPub::ready( $n, $sl, $sd ) === array() && DBD_Card::render( DBD_Tall::key( $n, $sl ) ) === null && isset( DBD_AutoPub::publish( $n, $sl )['item'] );
			$ck( 'a keys side: offered and drawn before kickoff; no button, no graphic, no post from kickoff on', $before && $after );
		} else { echo "  (no game left to kick off this week; skipped the keys check)\n"; }
		/* the three weekly boards (SCOPE §86): 5:00 PM on their days, in time order, on all four networks */
		$from0 = DBD_Tall::$from; DBD_Tall::$from = array(); DBD_Pack::$now = null; $ny = new DateTimeZone( 'America/New_York' ); $hm = array(); foreach ( array( 'rtd', 'recs', 'sacks' ) as $sl ) { $hm[] = wp_date( 'D G:i', DBD_Instagram::slot_time( $n, $sl ), $ny ); }
		$ck( 'the weekly boards: Wednesday, Thursday and Friday at 5:00 PM', $hm === array( 'Wed 17:00', 'Thu 17:00', 'Fri 17:00' ), json_encode( $hm ) );
		$tp0 = array_map( function ( $sl ) use ( $n ) { return DBD_Instagram::slot_time( $n, $sl ); }, array_keys( DBD_Instagram::plan( $n ) ) ); $tp1 = $tp0; sort( $tp1 ); $ck( 'the plan runs in time order, so the tab’s Next is right', $tp0 === $tp1 && isset( DBD_Instagram::plan( $n )['sacks'] ) && ! array_intersect( array( 'rtd', 'recs', 'sacks' ), array_column( DBD_Instagram::plan( $n )['carousel'] ?? array(), 'k' ) ) );
		foreach ( array( 'rtd', 'recs', 'sacks' ) as $sl ) {
			$wk = DBD_Instagram::key( $season, $n, $sl ); foreach ( array( 'dbd_ig_job_', 'dbd_bsky_job_', 'dbd_x_job_', 'dbd_th_job_' ) as $o ) { unset( $GLOBALS['dbd_opt'][ $o . $wk ] ); }
			$errs = DBD_AutoPub::publish( $n, $sl ); DBD_Instagram::send( $wk, true );
			$ck( "the $sl board reaches all four networks", ! $errs && DBD_Instagram::job( $wk )['state'] === 'sent' && DBD_Bluesky::job( $wk )['state'] === 'sent' && DBD_X::job( $wk )['state'] === 'sent' && DBD_Threads::job( $wk )['state'] === 'sent', json_encode( $errs ) );
		}
		DBD_Tall::$from = $from0;
		DBD_Tall::$off = true; DBD_Pack::$now = null;
	} catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in autopub: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	echo "autopub week $n: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
/* ---- the tall graphics (SCOPE §85): every post in both looks at 1080×1350 under 1 MB, long names, the fallbacks, League Leaders and the weekly look. Images land in $arg/ */
if ( $view === 'tall' ) {
	$GLOBALS['dbd_opt'] = array(); $dir = $arg ?: 'tall'; if ( ! is_dir( $dir ) ) { mkdir( $dir ); } $bad = 0; $ck = function ( $what, $ok, $got = '' ) use ( &$bad ) { echo '  ' . ( $ok ? 'ok' : 'WRONG' ) . ": $what" . ( $ok ? '' : " (got: $got)" ) . "\n"; $bad += ! $ok; };
	$fetch = getenv( 'DBD_HARNESS_LOGOS' ) ? function ( $u ) { $b = @file_get_contents( $u, false, stream_context_create( array( 'http' => array( 'timeout' => 10 ) ) ) ); return $b ?: null; } : null;
	$save = function ( $im, $name ) use ( $dir, $ck ) { if ( ! $im ) { $ck( "$name draws", false ); return; } $f = "$dir/$name.jpg"; imagejpeg( $im, $f, 90 ); $w = imagesx( $im ); $h = imagesy( $im ); imagedestroy( $im ); $sz = filesize( $f ); if ( $w !== 1080 || $h !== 1350 || $sz >= 1000000 ) { $ck( "$name: 1080×1350 under 1 MB", false, "$w x $h, $sz" ); } };
	try {
		$n = DBD_Pack::newest(); $W = DBD_Week::file( $n ); $rush = (int) DBD_Rush::through(); $nk = array(); foreach ( DBD_Pack::keys( $n ) as $kg ) { if ( ! DBD_Router::is_final( $kg['g'] ) ) { $nk[] = $kg; } }
		$t0 = PHP_INT_MAX; foreach ( $nk as $kg ) { $t0 = min( $t0, (int) strtotime( DBD_Data::kickoff( $kg['g'] )['utc'] ) ); } DBD_Pack::$now = $t0 - 60;
		$slots = array( 'board', 'swing', 'snaps', 'offense', 'targets', 'leaders' ); foreach ( $nk as $kg ) { foreach ( $kg['sides'] as $sd ) { $slots[] = DBD_AutoPub::slot( $n, $sd ); } } $c = 0; $sec = microtime( true );
		foreach ( array( 'black', 'paper' ) as $look ) { foreach ( $slots as $i => $sl ) { $k = DBD_Tall::key( $n, $sl, $look ); $ck0 = DBD_Card::valid( $k ); if ( ! $ck0 ) { $ck( "$k is a valid key", false ); } $im = DBD_Card::render( $k, $fetch ); $c++; if ( strpos( $sl, 'keys-' ) === 0 && $i > 7 && $look === 'paper' ) { if ( $im ) { imagedestroy( $im ); } continue; } $save( $im, $look . '-' . str_replace( '_', '-', $sl ) ); } }
		$dr = new ReflectionMethod( 'DBD_Tall', 'draw_rush' ); $dr->setAccessible( true );
		foreach ( array( 'black', 'paper' ) as $look ) { $save( $dr->invoke( null, $rush, $look, $fetch ), $look . '-rush' ); }
		echo "  drew $c graphics in " . round( microtime( true ) - $sec, 1 ) . "s\n";
		$GLOBALS['dbd_opt']['dbd_headshots'] = false; $save( DBD_Card::render( DBD_Tall::key( $n, 'board', 'black' ), $fetch ), 'nophoto-board' ); $save( DBD_Card::render( DBD_Tall::key( $n, 'snaps', 'paper' ), $fetch ), 'nophoto-snaps' ); $save( DBD_Card::render( DBD_Tall::key( $n, 'leaders', 'black' ), $fetch ), 'nophoto-leaders' ); unset( $GLOBALS['dbd_opt']['dbd_headshots'] );
		$bd = new ReflectionMethod( 'DBD_Tall', 'board' ); $bd->setAccessible( true ); $long = array(); for ( $i = 0; $i < 11; $i++ ) { $long[] = array( 'id' => '00-00000' . sprintf( '%02d', $i ), 'full' => 'Christopher Oluwaseun Amon-Ra St. Brown-Montgomery III', 't' => 'JAX', 'num' => 88, 'vs' => '12,345', 'x' => '12.5 SACKS' ); }
		$save( $bd->invoke( null, new DBD_Tall_Canvas( 'black', $fetch ), array( 'LEAGUE LEADERS', 'RECEIVING YARDS' ), 'THROUGH WEEK 18', $long, 'RECEIVING YARDS' ), 'long-names' );
		foreach ( array( 'tall-week-99-black-swing', 'tall-week-99-paper-board', 'tall-week-' . $n . '-paper-keys-2026_99_AAA_BBB-AAA' ) as $k ) { $ck( "$k draws nothing", DBD_Card::render( $k ) === null ); }
		foreach ( array( 'tall-week-1-red-swing', 'tall-week-1-black-nope', 'tall-week-123-black-swing' ) as $k ) { $ck( "$k isn't a valid key", ! DBD_Card::valid( $k ) ); }
		/* League Leaders: the rotation, the order, the top 11 */
		$seen = array(); for ( $w = 3; $w <= 10; $w++ ) { $seen[ DBD_Tall::stat( $w )[0] ] = 1; } $ck( 'Weeks 3 to 10 of 2026 keep all eight stats', (int) DBD_Data::season() !== 2026 || count( $seen ) === 8 ); $ck( 'Week 10 of 2026 is still rushing TDs', (int) DBD_Data::season() !== 2026 || DBD_Tall::stat( 10 )[0] === 'rushing_tds' );
		$rot = array(); for ( $w = 11; $w <= 18; $w++ ) { $rot[] = DBD_Tall::stat( $w )[0]; } $ck( 'from Week 11, Monday rotates the six stats without rushing TDs or receptions', $rot === array( 'receiving_yards', 'receiving_tds', 'sk', 'passing_yards', 'passing_tds', 'rushing_yards', 'receiving_yards', 'receiving_tds' ), json_encode( $rot ) );
		foreach ( range( max( 1, $n - 7 ), $n ) as $w ) { $s = DBD_Tall::board_spec( $w ); if ( ! $s ) { echo "  (Week $w: no " . DBD_Tall::stat( $w )[1] . " board from this data)\n"; continue; } $R = $s['rows']; $o = count( $R ) <= 13; foreach ( $R as $i => $r ) { if ( $i && ( $R[ $i - 1 ]['v'] < $r['v'] || ( $R[ $i - 1 ]['v'] == $r['v'] && strcmp( DBD_Pack::last( $R[ $i - 1 ]['full'] ), DBD_Pack::last( $r['full'] ) ) > 0 ) ) ) { $o = false; } } $ck( 'Week ' . $w . ' ' . $s['stat'][1] . ': 13 rows at most, most first, ties by last name (' . $R[0]['full'] . ' ' . $R[0]['vs'] . ')', $o ); }
		$it = DBD_Pack::board_item( $n ); $ck( 'its post: names the stat and the week, fits X', $it && strpos( $it['tl'], 'through Week ' . $n ) !== false && DBD_Pack::xlen( $it['post'] ) <= 280 && DBD_Card::valid( $it['sq'] ), json_encode( $it['tl'] ?? null ) ); echo '  ' . ( $it['tl'] ?? '' ) . "\n";
		/* the three weekly boards (SCOPE §86) */
		$from0 = DBD_Tall::$from; DBD_Tall::$from = array( (int) DBD_Data::season() => $n + 1 ); $none = array_keys( DBD_Pack::board_items( $n ) ) === array( 'board' ) && DBD_Pack::find( $n, 'rtd' ) === null && DBD_Card::render( DBD_Tall::key( $n, 'sacks', 'black' ), $fetch ) === null;
		$ck( 'a week before the first one has only Monday’s board', $none ); DBD_Tall::$from = array();
		$ck( 'a week from the first one has all four', array_keys( DBD_Pack::board_items( $n ) ) === array( 'board', 'rtd', 'recs', 'sacks' ) );
		foreach ( array( 'black', 'paper' ) as $look ) { foreach ( array( 'rtd', 'recs', 'sacks' ) as $sl ) { $k = DBD_Tall::key( $n, $sl, $look ); $ck( "$k is a valid key", DBD_Card::valid( $k ) ); $save( DBD_Card::render( $k, $fetch ), $look . '-board-' . $sl ); } }
		$pl = array(); foreach ( glob( DBD_Data::dir() . '/plays/def/*.json' ) ?: array() as $f ) { $d = json_decode( (string) file_get_contents( $f ), true ); $K = array_flip( $d['keys'] ); foreach ( $d['plays'] as $x ) { if ( $x[ $K['k'] ] === 'sack' && (int) explode( '_', $x[ $K['g'] ] )[1] <= $n ) { $pl[ $d['team'] ] = ( $pl[ $d['team'] ] ?? 0 ) + 1; } } }
		$st = DBD_Tall::board_spec( $n, 'sacks' ); $off = array(); foreach ( $st['rows'] as $r ) { if ( (int) $r['v'] !== (int) ( $pl[ $r['t'] ] ?? -1 ) ) { $off[] = $r['t']; } } $ck( 'Most Sacks matches the play-by-play for every team shown', $pl && ! $off, json_encode( $off ) );
		$ko = true; foreach ( $st['rows'] as $i => $r ) { if ( $i && ( $st['rows'][ $i - 1 ]['v'] < $r['v'] || ( $st['rows'][ $i - 1 ]['v'] == $r['v'] && strcmp( $st['rows'][ $i - 1 ]['full'], $r['full'] ) > 0 ) ) ) { $ko = false; } if ( $r['city'] . ' ' . $r['nick'] !== $r['full'] ) { $ko = false; } } $ck( 'teams in order, each split into city and name', $ko );
		foreach ( array( 'rtd', 'recs', 'sacks' ) as $sl ) { $it = DBD_Pack::board_item( $n, $sl ); $ck( "the $sl post names the week and fits X", $it && $it['k'] === $sl && strpos( $it['tl'], 'through Week ' . $n ) !== false && DBD_Pack::xlen( $it['post'] ) <= 280 && DBD_Card::valid( $it['sq'] ), json_encode( $it['tl'] ?? null ) ); echo '  ' . ( $it['tl'] ?? '' ) . "\n"; }
		$cutm = new ReflectionMethod( 'DBD_Tall', 'cut' ); $cutm->setAccessible( true ); $mk = function ( $v ) { return array_map( function ( $x ) { return array( 'v' => (float) $x ); }, $v ); }; $len = function ( $v ) use ( $cutm, $mk ) { return count( $cutm->invoke( null, $mk( $v ) ) ); };
		$ck( 'the cut: no tie at 11', $len( array( 20, 19, 18, 17, 16, 15, 14, 13, 12, 11, 10, 9, 8 ) ) === 11 );
		$ck( 'the cut: a tie across 11 keeps all of it, to 13', $len( array( 20, 19, 18, 17, 16, 15, 14, 13, 12, 10, 10, 10, 10, 9 ) ) === 13 );
		$ck( 'the cut: a longer tie stops above it', $len( array( 20, 19, 18, 17, 16, 15, 14, 10, 10, 10, 10, 10, 10, 10, 9 ) ) === 7 );
		$ck( 'the cut: never fewer than 5 rows from a tie', $len( array( 20, 19, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 9 ) ) === 11 );
		DBD_Tall::$from = $from0;
		/* the weekly look */
		$ws = DBD_Pack::weeks(); $a = $ws[0]; $b = $ws[1] ?? $ws[0];
		$ck( 'Black the first time', DBD_Tall::look( $a ) === 'black' && DBD_Tall::key( $a, 'swing' ) === "tall-week-$a-black-swing" );
		DBD_Tall::set_look( $a, 'paper' ); $ck( 'a pick changes only its week; the others keep what they had', DBD_Tall::look( $a ) === 'paper' && ( $a === $b || DBD_Tall::look( $b ) === 'black' ) && DBD_Tall::key( $a, 'swing' ) === "tall-week-$a-paper-swing" );
		$ck( 'a new week starts with the last pick', DBD_Tall::look( 99 ) === 'paper' ); DBD_Tall::set_look( 99, 'black' ); $ck( 'and the week before keeps its own', DBD_Tall::look( $a ) === 'paper' && DBD_Tall::look( 98 ) === 'black' );
		$ck( 'a look that doesn’t exist is refused', DBD_Tall::set_look( $a, 'neon' ) === false && DBD_Tall::look( $a ) === 'paper' );
	} catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in tall: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	echo "tall week $n: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
if ( $view === 'packmail' ) {
	$GLOBALS['dbd_opt'] = array( 'dbd_email' => array( 'on' => true, 'key' => 'xkeysib-test', 'list' => 3, 'tpl' => 5, 'test' => 'gene@example.test' ) );
	$GLOBALS['hcalls'] = array(); $GLOBALS['hmail'] = array(); $GLOBALS['refuse'] = array();
	foreach ( array(
		'is_email' => function ( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); },
		'sanitize_email' => function ( $e ) { return preg_replace( '/[^a-z0-9@._+\-]/i', '', (string) $e ); },
		'admin_url' => function ( $p = '' ) { return 'https://example.test/wp-admin/' . $p; },
		'add_option' => function ( $k, $v = '' ) { if ( isset( $GLOBALS['dbd_opt'][ $k ] ) ) { return false; } $GLOBALS['dbd_opt'][ $k ] = $v; return true; },
		'delete_option' => function ( $k ) { unset( $GLOBALS['dbd_opt'][ $k ] ); return true; },
		'wp_mail' => function ( $to, $sub ) { $GLOBALS['hmail'][] = $sub; return true; },
		'wp_remote_request' => function ( $url, $a ) {
			$path = substr( $url, strlen( DBD_Email::API ) ); $b = isset( $a['body'] ) ? json_decode( $a['body'], true ) : null; $GLOBALS['hcalls'][] = array( $a['method'], $path, $b );
			$to = $b['to'][0]['email'] ?? ''; if ( in_array( $to, $GLOBALS['refuse'], true ) ) { return array( 'response' => array( 'code' => 400 ), 'body' => '{"message":"invalid email"}' ); }
			return array( 'response' => array( 'code' => 201 ), 'body' => '{"messageId":"<x@smtp>"}' );
		},
	) as $fn => $body ) { if ( ! function_exists( $fn ) ) { $GLOBALS['hfn'][ $fn ] = $body; eval( 'function ' . $fn . '( ...$a ) { return $GLOBALS["hfn"]["' . $fn . '"]( ...$a ); }' ); } }
	$bad = 0; $ck = function ( $what, $ok, $got = '' ) use ( &$bad ) { echo '  ' . ( $ok ? 'ok' : 'WRONG' ) . ": $what" . ( $ok ? '' : " (got: $got)" ) . "\n"; $bad += ! $ok; };
	try {
		/* who gets it */
		$ck( 'before saving: on, to the test address', DBD_Pack_Mail::settings() === array( 'on' => true, 'to' => array( 'gene@example.test' ) ), json_encode( DBD_Pack_Mail::settings() ) );
		$to = DBD_Pack_Mail::parse_to( " Gene@Example.test\nerik@example.test, not-an-address;erik@example.test\n\n" );
		$ck( 'addresses: one per line or commas, valid only, no repeats', $to === array( 'gene@example.test', 'erik@example.test' ), json_encode( $to ) );
		$ck( 'at most ten', count( DBD_Pack_Mail::parse_to( implode( "\n", array_map( function ( $i ) { return "p$i@example.test"; }, range( 1, 14 ) ) ) ) ) === 10 );
		$ck( 'unchecked switch saves off', DBD_Pack_Mail::sanitize( array( 'to' => 'a@example.test' ) ) === array( 'on' => false, 'to' => array( 'a@example.test' ) ) );
		/* booking (decide: no side effects) */
		$tue = 1795000000; $s0 = array( 'sent' => array(), 'jobs' => array() );
		list( $s1, $at ) = DBD_Pack_Mail::decide( $s0, 2026, 9, $tue, $tue - 86400 ); $ck( 'a new week books its Tuesday', $at === $tue && ( $s1['jobs']['2026-w9']['state'] ?? '' ) === 'booked' );
		list( , $at2 ) = DBD_Pack_Mail::decide( $s1, 2026, 9, $tue, $tue - 3600 ); $ck( 'a booked week never books again', $at2 === null );
		list( , $at3 ) = DBD_Pack_Mail::decide( $s0, 2026, 9, $tue, $tue + 3600 ); $ck( 'after its Tuesday: a minute from now', $at3 === $tue + 3600 + 60, (string) $at3 );
		list( $s4, $at4 ) = DBD_Pack_Mail::decide( $s0, 2026, 9, $tue, $tue + 3 * 86400 ); $ck( 'more than two days late: marked, not sent', $at4 === null && ( $s4['sent'][2026] ?? 0 ) === 9 && strpos( $s4['jobs']['2026-w9']['state'], 'skipped' ) === 0 );
		list( , $at5 ) = DBD_Pack_Mail::decide( array( 'sent' => array( 2026 => 10 ), 'jobs' => array() ), 2026, 9, $tue, $tue - 60 ); $ck( 'an older week than the last sent never books', $at5 === null );
		list( , $at6 ) = DBD_Pack_Mail::decide( $s0, 2026, 0, $tue, $tue ); $ck( 'no pack, no booking', $at6 === null );
		$n = DBD_Pack::newest(); $t7 = DBD_Pack_Mail::tuesday( $n ); $d = new DateTime( '@' . $t7 ); $d->setTimezone( new DateTimeZone( 'America/New_York' ) );
		$ck( 'the send time is Tuesday 7:00 AM Eastern', $d->format( 'D H:i' ) === 'Tue 07:00', $d->format( 'D H:i' ) );
		/* the email */
		$doc = DBD_Pack_Mail::build( $n ); $h = $doc['html'] ?? '';
		$ck( 'the subject leads with the swing and the riser', (bool) preg_match( '/^Week ' . $n . ' share pack: .+-point swing, .+’s \d+%$/u', $doc['subject'] ?? '' ), $doc['subject'] ?? '' );
		$hasr = in_array( 'rush', array_column( DBD_Pack::items( $n ), 'k' ), true );  /* pass rushers only when the defense files are at week $n (Greptile, PR #147) */
		$ck( 'the count of things to post', strpos( $h, ucfirst( array( 5 => 'five', 6 => 'six', 7 => 'seven' )[ count( DBD_Pack::items( $n ) ) + ( DBD_Pack::keys( $n ) ? 1 : 0 ) ] ?? '?' ) . ' things to post this week.' ) !== false );
		foreach ( array_merge( $hasr ? array( '6 · Pass rushers' ) : array(), array( '1 · The biggest swing', '3 · Best and worst offense', '5 · Week leaders', '7 · Keys to the game, Week ' . ( $n + 1 ), 'Reddit title', 'Reddit comment, with the link', 'X and Bluesky', ' of 280', 'Open the Share pack', 'page=down-by-down-share&#038;week=' . $n ) ) as $w ) {
			$ck( 'the email has ' . $w, strpos( $h, $w ) !== false || strpos( $h, str_replace( '&#038;', '&', $w ) ) !== false ); }
		preg_match_all( '#(?:href|src)="([^"]*)"#', $h, $m ); $rel = array_filter( $m[1], function ( $u ) { return ! preg_match( '#^https?://#', $u ); } );
		$ck( 'every link and image is absolute', ! $rel, implode( ' ', $rel ) );
		$ck( 'no raw angle brackets from the data', ! preg_match( '/<(?!\/?(?:html|head|meta|title|body|table|tr|td|div|span|a|img|b|br)\b|!doctype)/i', $h ) );
		$ck( 'no pack, no email', DBD_Pack_Mail::build( 99 ) === null );
		/* Post to r/downbydown links (SCOPE §71): one per item and keys side, with ?share= on the pack's own cards; none when no subreddit is set */
		$nk = 0; foreach ( DBD_Pack::keys( $n ) as $kg ) { $nk += count( $kg['sides'] ); }
		$ck( 'a Post link for every item and keys side', substr_count( $h, '>Post to r/downbydown</a>' ) === count( DBD_Pack::items( $n ) ) + $nk, substr_count( $h, '>Post to r/downbydown</a>' ) . ' vs ' . ( count( DBD_Pack::items( $n ) ) + $nk ) );
		$nt = 0; foreach ( DBD_Pack::keys( $n ) as $kg ) { foreach ( $kg['sides'] as $sd ) { $nt += strpos( $h, '>Post to ' . $sd['fits'] . '</a>' ) !== false ? 1 : 0; } }
		$ck( 'a team-subreddit Post link on every keys side (2.29.1)', $nt === $nk && substr_count( $h, '>Post to r/' ) === count( DBD_Pack::items( $n ) ) + 2 * $nk, $nt . ' of ' . $nk );
		$ck( 'the email\'s Post links carry ?share= for the pack cards', strpos( $h, 'reddit.com/r/downbydown/submit?url=' ) !== false && strpos( $h, 'share%3Dpack-week-' . $n . '-' ) !== false );
		$ck( 'a Post on X and a Post on Bluesky link for every item and keys side (2.30.0)', substr_count( $h, '>Post on X</a>' ) === count( DBD_Pack::items( $n ) ) + $nk && substr_count( $h, '>Post on Bluesky</a>' ) === count( DBD_Pack::items( $n ) ) + $nk, substr_count( $h, '>Post on X</a>' ) . ' X, ' . substr_count( $h, '>Post on Bluesky</a>' ) . ' Bluesky' );
		$ck( 'Share on Facebook and Graphic for Instagram on every item and keys side (2.31.0)', substr_count( $h, '>Share on Facebook</a>' ) === count( DBD_Pack::items( $n ) ) + $nk && substr_count( $h, '>Graphic for Instagram</a>' ) === count( DBD_Pack::items( $n ) ) + $nk, substr_count( $h, '>Share on Facebook</a>' ) . ' Facebook, ' . substr_count( $h, '>Graphic for Instagram</a>' ) . ' graphics' );
		$ck( 'an Instagram caption for every item and keys side', substr_count( $h, 'Full chart: link in bio' ) === count( DBD_Pack::items( $n ) ) + $nk && substr_count( $h, 'Instagram caption, with the graphic' ) === count( DBD_Pack::items( $n ) ) && strpos( $h, 'card=tall-week-' . $n . '-' ) !== false && strpos( $h, 'sharer.php?u=' ) !== false );
		$ck( 'the X and Bluesky links carry the share link', strpos( $h, 'https://x.com/intent/post?text=' ) !== false && strpos( $h, 'https://bsky.app/intent/compose?text=' ) !== false && strpos( $h, 'share%3Dpack-week-' . $n . '-snaps' ) !== false );
		$ck( 'a Post on Threads link for every item and keys side, with the page as its card (2.42.0)', substr_count( $h, '>Post on Threads</a>' ) === count( DBD_Pack::items( $n ) ) + $nk && strpos( $h, 'https://www.threads.com/intent/post?text=' ) !== false && preg_match( '/intent\/post\?text=[^"]+&(amp;)?url=https%3A%2F%2F/', $h ), substr_count( $h, '>Post on Threads</a>' ) . ' Threads' );
		$keep = $GLOBALS['dbd_opt']; $GLOBALS['dbd_opt'][ DBD_Pack::OPT_X ] = ''; $GLOBALS['dbd_opt'][ DBD_Pack::OPT_BSKY ] = ''; $GLOBALS['dbd_opt'][ DBD_Pack::OPT_TH ] = '';
		$hx = DBD_Pack_Mail::build( $n )['html'] ?? ''; $ck( 'cleared accounts: no X, Bluesky or Threads links', strpos( $hx, 'Post on X' ) === false && strpos( $hx, 'Post on Bluesky' ) === false && strpos( $hx, 'Post on Threads' ) === false );
		$GLOBALS['dbd_opt'] = $keep;
		$keep = $GLOBALS['dbd_opt']; $GLOBALS['dbd_opt'][ DBD_Pack::OPT_SUB ] = '';
		$h0 = DBD_Pack_Mail::build( $n )['html'] ?? ''; $ck( 'no subreddit of ours, no r/downbydown links (the team ones stay)', strpos( $h0, 'Post to r/downbydown' ) === false && strpos( $h0, 'reddit.com/r/downbydown/' ) === false && substr_count( $h0, '>Post to r/' ) === $nk );
		$GLOBALS['dbd_opt'] = $keep;
		/* sending: one copy each; Erik refused, retried alone; then sent */
		$key = DBD_Pack_Mail::key( DBD_Data::season(), $n );
		/* a booked first send that runs more than two days after its Tuesday: old news (Greptile, PR #127) */
		$GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT ] = array( 'sent' => array(), 'jobs' => array( $key => array( 'state' => 'booked', 'at' => time(), 'tries' => 0, 'done' => array() ) ) );
		$late = DBD_Pack_Mail::late( $n, time() ); DBD_Pack_Mail::send( $key );
		$ck( 'a booked send run late is not sent; on time it is', $late ? ( ! $GLOBALS['hcalls'] && DBD_Pack_Mail::job( $key )['state'] === 'not sent: more than two days late' ) : DBD_Pack_Mail::job( $key )['state'] === 'sent', DBD_Pack_Mail::job( $key )['state'] );
		$ck( 'late() is two days past Tuesday 7:00', ! DBD_Pack_Mail::late( $n, DBD_Pack_Mail::tuesday( $n ) + 2 * 86400 ) && DBD_Pack_Mail::late( $n, DBD_Pack_Mail::tuesday( $n ) + 2 * 86400 + 1 ) );
		/* a booking whose event is gone is booked again on the next sync (Greptile, PR #127) */
		$GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT ] = array( 'sent' => array(), 'jobs' => array( $key => array( 'state' => 'booked', 'at' => 1000, 'tries' => 0, 'done' => array() ) ) );
		$GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT_SET ] = array( 'on' => true, 'to' => array( 'gene@example.test' ) ); $rb = DBD_Pack_Mail::note( null, 5000 );
		$ck( 'a lost booking is booked again', $rb === 5060, (string) $rb );
		/* retries (and Try again) send whatever the date */
		$GLOBALS['hcalls'] = array();
		$GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT ] = array( 'sent' => array(), 'jobs' => array( $key => array( 'state' => 'retrying: again', 'at' => time(), 'tries' => 0, 'done' => array() ) ) );
		$GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT_SET ] = array( 'on' => true, 'to' => array( 'gene@example.test', 'erik@example.test' ) );
		$GLOBALS['refuse'] = array( 'erik@example.test' ); DBD_Pack_Mail::send( $key ); $j = DBD_Pack_Mail::job( $key );
		$smtp = array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) { return $c[1] === 'smtp/email'; } ) );
		$ck( 'one copy per person, each to one address', count( $smtp ) === 2 && count( $smtp[0][2]['to'] ) === 1 && $smtp[0][2]['to'][0]['email'] === 'gene@example.test', (string) count( $smtp ) );
		$ck( 'never the list, no campaign', ! array_filter( $GLOBALS['hcalls'], function ( $c ) { return strpos( $c[1], 'emailCampaigns' ) === 0; } ) && ! isset( $smtp[0][2]['recipients'] ) );
		$ck( 'a refusal retries, Gene marked reached', strpos( $j['state'], 'retrying: erik@example.test: Brevo answered 400' ) === 0 && $j['done'] === array( 'gene@example.test' ) && (int) $j['tries'] === 1, json_encode( $j ) );
		$GLOBALS['refuse'] = array(); $GLOBALS['hcalls'] = array(); DBD_Pack_Mail::send( $key ); $j = DBD_Pack_Mail::job( $key );
		$smtp = array_values( array_filter( $GLOBALS['hcalls'], function ( $c ) { return $c[1] === 'smtp/email'; } ) );
		$ck( 'the retry goes to Erik only', count( $smtp ) === 1 && $smtp[0][2]['to'][0]['email'] === 'erik@example.test' );
		$ck( 'then sent, and the week recorded', $j['state'] === 'sent' && count( $j['done'] ) === 2 && (int) DBD_Pack_Mail::state()['sent'][ DBD_Data::season() ] === $n );
		$GLOBALS['hcalls'] = array(); DBD_Pack_Mail::send( $key ); $ck( 'a sent week never sends again', ! $GLOBALS['hcalls'] );
		$st = DBD_Pack_Mail::status(); $ck( 'the status line says sent to 2 people', strpos( end( $st )[1] ?? '', 'to 2 people' ) !== false, json_encode( $st ) );
		/* the last try fails: failed, Try again, and a note */
		$GLOBALS['refuse'] = array( 'erik@example.test' ); $GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT ]['jobs'][ $key ] = array( 'state' => 'retrying: x', 'at' => time(), 'tries' => DBD_Email::TRIES - 1, 'done' => array( 'gene@example.test' ) );
		DBD_Pack_Mail::send( $key ); $ck( 'the seventh refusal is final, with a note', DBD_Pack_Mail::failed( $key ) && count( $GLOBALS['hmail'] ) === 1 );
		$st = DBD_Pack_Mail::status(); $ck( 'the status offers Try again', ( end( $st )[2] ?? '' ) === $key, json_encode( $st ) );
		/* switched off: nothing sends */
		$GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT_SET ]['on'] = false; $GLOBALS['dbd_opt'][ DBD_Pack_Mail::OPT ]['jobs'][ $key ] = array( 'state' => 'booked', 'at' => time(), 'tries' => 0, 'done' => array() ); $GLOBALS['hcalls'] = array();
		DBD_Pack_Mail::send( $key ); $ck( 'switched off: not sent', ! $GLOBALS['hcalls'] && strpos( DBD_Pack_Mail::job( $key )['state'], 'not sent' ) === 0 );
		if ( ( $dir = getenv( 'DBD_HARNESS_HTML' ) ) ) { if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); } file_put_contents( $dir . '/pack-email.html', $h ); }
	} catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in packmail: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	echo "packmail week $n: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
if ( $view === 'pack_cards' ) { $n = DBD_Pack::newest(); echo implode( ' ', DBD_Pack::cards( $n ) ), "\n"; exit( $n ? 0 : 1 ); }  /* the newest pack's cards, for CI to draw */
if ( $view === 'pack' ) {
	$bad = 0; $ck = function ( $ok, $what ) use ( &$bad ) { if ( ! $ok ) { $bad++; echo "  FAIL: $what\n"; } };
	$n = DBD_Pack::newest(); $ck( $n > 0, 'a finished week with movers' );
	$items = DBD_Pack::items( $n ); $ks = array_map( function ( $x ) { return $x['k']; }, $items );
	/* a week may lack a mover (items() leaves it out), so the items must be the fixed order minus any missing mover, never the swing or offense (Greptile, PR #125) */
	$ck( $ks === array_values( array_intersect( array( 'swing', 'snaps', 'offense', 'targets', 'leaders', 'rush' ), $ks ) ) && in_array( 'swing', $ks, true ) && in_array( 'offense', $ks, true ) && in_array( 'leaders', $ks, true ), 'the items in their fixed order, got ' . implode( ',', $ks ) );
	$ck( in_array( 'rush', $ks, true ) === ( $n === DBD_Rush::through() ), 'pass rushers only for the week the defense files run through (SCOPE §79)' );
	foreach ( $items as $it ) { if ( $it['k'] === 'rush' ) { $ck( strpos( $it['link'], 'rush' ) !== false && strpos( $it['link'], 'def' ) === false, 'pass rushers link to their own tab (SCOPE §80), got ' . $it['link'] ); } }
	foreach ( $items as $it ) { $ck( isset( DBD_Pack::NUM[ $it['k'] ] ), $it['k'] . ': a fixed number' ); }
	$ck( DBD_Pack::NUM === array( 'swing' => 1, 'snaps' => 2, 'offense' => 3, 'targets' => 4, 'leaders' => 5, 'rush' => 6, 'keys' => 7, 'board' => 8, 'rtd' => 9, 'recs' => 10, 'sacks' => 11 ), 'the fixed numbers' );
	/* Week leaders (2.33.0, SCOPE §75): each panel's leader is the box score's top, worked out here on its own */
	$bx = DBD_Data::get( 'box/w' . $n . '.json' ); $br = array();
	foreach ( (array) ( $bx['games'] ?? array() ) as $g ) { foreach ( array( 'away', 'home' ) as $sd ) { foreach ( (array) ( $g[ $sd ]['off'] ?? array() ) as $r ) { $x = array_combine( $bx['keys'], $r ); $x['td'] = $x['rushing_tds'] + $x['receiving_tds']; $x['t'] = $g[ $sd ]['team']; $br[] = $x; } } }
	$mx = function ( $k ) use ( $br ) { $m = 0; foreach ( $br as $x ) { $m = max( $m, (float) $x[ $k ] ); } return $m; };
	$SL = DBD_Pack::spec_leaders( $n ); $ck( (bool) $SL && count( $SL['panels'] ) === 5, 'spec_leaders(): five panels' );
	if ( $SL ) {
		$PN = $SL['panels'];
		$ck( (float) $PN['pass'][3] === (float) $mx( 'passing_yards' ) && (float) $PN['rush'][3] === (float) $mx( 'rushing_yards' ) && (float) $PN['rec'][3] === (float) $mx( 'receiving_yards' ) && (float) $PN['td'][3] === (float) $mx( 'td' ), 'the leaders are the box score\'s tops (' . $PN['pass'][3] . ', ' . $PN['rush'][3] . ', ' . $PN['rec'][3] . ', ' . $PN['td'][3] . ')' );
		$dmin = null; foreach ( (array) DBD_Week::file( $n )['teams'] as $x ) { if ( isset( $x['depa'] ) && ( $dmin === null || $x['depa'] < $dmin['depa'] ) ) { $dmin = $x; } }
		$ck( $dmin && $PN['def'][1] === $dmin['t'], 'the defense is the team that allowed the fewest EPA a play (' . $PN['def'][1] . ')' );
		foreach ( $PN as $pk => $pp ) { $ck( count( $pp[6] ) <= 2 && $pp[2] !== '' && strpos( $pp[2], '.' ) !== 1, $pk . ': a full name and at most two more (' . $pp[2] . ')' ); }
		$ck( DBD_Pack::spec_leaders( 99 ) === null, 'no leaders for a week that isn\'t there' );
	}
	$LI = null; foreach ( $items as $it0 ) { if ( $it0['k'] === 'leaders' ) { $LI = $it0; } } if ( $LI ) { $ck( strpos( $LI['title'], 'Week ' . $n . ' leaders: ' ) === 0 && mb_strlen( $LI['title'] ) <= 300 && strpos( $LI['title'], ' held the ' ) !== false && $LI['card'] === 'pack-week-' . $n . '-leaders' && $LI['sq'] === 'ig-week-' . $n . '-leaders', 'the leaders item (' . $LI['title'] . ')' );
		$ck( strpos( $LI['post'], $LI['ts'] ) === 0 && strpos( DBD_Pack::social( $LI )['x'], $LI['ts'] ) === 0 && strpos( $LI['ts'], ' scored ' ) === false, 'X and Bluesky take the short version (passing, rushing, receiving, defense)' ); }
	foreach ( $items as $it ) {
		$ck( $it['title'] !== '' && strpos( $it['title'], 'http' ) === false, $it['k'] . ': a title without a link' );
		$ck( strpos( $it['comment'], $it['link'] ) !== false && strpos( $it['comment'], '[' ) === false, $it['k'] . ': the comment carries the link and no bracketed note' );
		$ck( strpos( $it['post'], $it['link'] ) !== false && DBD_Pack::xlen( $it['post'] ) <= 280, $it['k'] . ': the post carries the link within 280 (' . DBD_Pack::xlen( $it['post'] ) . ')' );
		$ck( DBD_Card::valid( $it['card'] ) && preg_match( '/^dbd-week-\d+-[a-z0-9-]+\.png$/', $it['file'] ), $it['k'] . ': a valid card and file name' );
		$ck( count( $it['fits'] ) >= 2, $it['k'] . ': where it fits' );
		echo '  ' . $it['k'] . ' (' . DBD_Pack::xlen( $it['post'] ) . '): ' . $it['title'] . "\n";
	}
	$by = array(); foreach ( $items as $it ) { $by[ $it['k'] ] = $it; } $ck( ! isset( $by['snaps'], $by['targets'] ) || $by['snaps']['link'] !== $by['targets']['link'], 'the target mover is not the usage riser' );
	$keys = DBD_Pack::keys( $n ); $sides = 0;
	foreach ( $keys as $kg ) { foreach ( $kg['sides'] as $sd ) { $sides++; $home = $sd['t'] === $kg['g']['home'];
		$ck( DBD_Pack::xlen( $sd['post'] ) <= 280 && strpos( $sd['post'], $sd['link'] ) !== false && strpos( $sd['comment'], $sd['link'] ) !== false, 'keys ' . $kg['g']['id'] . ' ' . $sd['t'] );
		$ck( $sd['link'] === DBD_Router::url( $home ? array( 'game' => $kg['g']['id'], 'side' => 'home' ) : array( 'game' => $kg['g']['id'] ) ), 'keys ' . $sd['t'] . ': the link opens its own offense' ); $ck( strpos( $sd['fits'], 'r/' ) === 0, 'keys ' . $sd['t'] . ': a subreddit' ); } }
	echo '  keys for week ' . ( $n + 1 ) . ': ' . count( $keys ) . ' games, ' . $sides . " sides\n";
	/* the helpers */
	$ck( DBD_Pack::play( 'B.Young sacked at DEN 44 for -8 yards (N.Bonitto). FUMBLES (N.Bonitto) [N.Bonitto], RECOVERED by DEN-N.Bonitto at DEN 44. The Replay Official…' ) === 'B.Young sacked at DEN 44 for -8 yards (N.Bonitto). FUMBLES (N.Bonitto), RECOVERED by DEN-N.Bonitto at DEN 44.', 'play(): brackets and the cut-off sentence out' );
	$ck( DBD_Pack::play( 'J.Allen pass deep left to K.Coleman for 40 yards, TOUCHDOWN' ) === 'J.Allen pass deep left to K.Coleman for 40 yards, TOUCHDOWN.', 'play(): a period at the end' );
	$L = 'https://example.test/game/a-very-long-address-that-counts-as-twenty-three/';
	$ck( DBD_Pack::xlen( 'abc ' . $L ) === 27, 'xlen(): a link counts 23' );
	$p = DBD_Pack::post( str_repeat( 'long words here ', 20 ), 'The short one.', $L ); $ck( $p === 'The short one. ' . $L, 'post(): the short takeaway when the long runs over' );
	$p = DBD_Pack::post( str_repeat( 'long words here ', 20 ), str_repeat( 'still too long ', 20 ), $L ); $ck( DBD_Pack::xlen( $p ) <= 280 && strpos( $p, '… ' . $L ) !== false, 'post(): cut at a word with an ellipsis when both run over' );
	$W = array( 'movers' => array( 'snaps' => array( 'up' => array( array( 'id' => 'q', 'pos' => 'QB' ), array( 'id' => 'r', 'pos' => 'RB' ) ) ), 'targets' => array( 'up' => array( array( 'id' => 'r', 'pos' => 'RB' ), array( 'id' => 'w', 'pos' => 'WR' ) ) ) ) );
	$ck( ( DBD_Pack::mover( $W, 'snaps' )['id'] ?? '' ) === 'r', 'mover(): quarterbacks out of snaps' );
	$ck( ( DBD_Pack::mover( $W, 'targets', 'r' )['id'] ?? '' ) === 'w', 'mover(): the target mover skips the usage riser' );
	$ck( DBD_Pack::mover( array( 'movers' => array( 'snaps' => array( 'up' => array( array( 'id' => 'q', 'pos' => 'QB' ) ) ) ) ), 'snaps' ) === null, 'mover(): none left means no item' );
	$ck( DBD_Pack::sub( 'MIN' ) === 'r/minnesotavikings' && count( DBD_Pack::SUBS ) === 32, 'the 32 subreddits' );
	$sr = DBD_Pack::spec_riser( $n, 'snaps' ); $so = DBD_Pack::spec_offense( $n );
	$ck( ! isset( $by['snaps'] ) || ( $sr && $sr['series'] && $sr['now'] > $sr['before'] ), 'spec_riser(): a rise with its series' );
	$ck( isset( $by['snaps'] ) === (bool) $sr && isset( $by['targets'] ) === (bool) DBD_Pack::spec_riser( $n, 'targets' ), 'a riser card exactly when its item is there' );
	$ck( $so && $so['rows'][0][1] >= $so['rows'][ count( $so['rows'] ) - 1 ][1], 'spec_offense(): best first' );
	$ck( DBD_Pack::spec_riser( 99, 'snaps' ) === null && DBD_Pack::items( 99 ) === array(), 'a week that isn\'t there gives nothing' );
	/* Post to r/downbydown (2.29.0, SCOPE §71) */
	$ck( DBD_Pack::our_sub() === 'downbydown', 'the subreddit defaults to downbydown' );
	foreach ( array( 'r/DownByDown' => 'DownByDown', 'https://www.reddit.com/r/downbydown/' => 'downbydown', ' down by-down! ' => 'downbydown', '' => '', 'a_very_long_name_over_twenty_one' => 'a_very_long_name_over' ) as $in => $want ) { $ck( DBD_Pack::sanitize_sub( $in ) === $want, "sanitize_sub('$in') gives '$want', got '" . DBD_Pack::sanitize_sub( $in ) . "'" ); }
	foreach ( $items as $it ) {
		$sl = DBD_Pack::share_link( $it ); $pack = strpos( $it['card'], 'pack-' ) === 0;
		$ck( $pack ? $sl === $it['link'] . ( strpos( $it['link'], '?' ) === false ? '?' : '&' ) . 'share=' . $it['card'] : $sl === $it['link'], $it['k'] . ': ?share= only on the pack\'s own cards (' . $sl . ')' );
		$u = DBD_Pack::reddit_url( $it['title'], $sl );
		$ck( strpos( $u, 'https://www.reddit.com/r/downbydown/submit?url=' . rawurlencode( $sl ) . '&title=' ) === 0, $it['k'] . ': the Reddit address carries the link' );
		parse_str( (string) parse_url( $u, PHP_URL_QUERY ), $q ); $ck( ( $q['title'] ?? '' ) === $it['title'] && ( $q['url'] ?? '' ) === $sl, $it['k'] . ': title and link decode back exactly' );
	}
	foreach ( $keys as $kg ) { foreach ( $kg['sides'] as $sd ) { parse_str( (string) parse_url( DBD_Pack::reddit_url( $sd['title'], $sd['link'] ), PHP_URL_QUERY ), $q ); $ck( ( $q['url'] ?? '' ) === $sd['link'] && ( $q['title'] ?? '' ) === $sd['title'], 'keys ' . $sd['t'] . ': its Reddit address' ); } }
	/* 2.29.1: each keys side also posts to its team's own subreddit */
	foreach ( $keys as $kg ) { foreach ( $kg['sides'] as $sd ) { $tu = DBD_Pack::reddit_url( $sd['title'], $sd['link'], $sd['fits'] ); parse_str( (string) parse_url( $tu, PHP_URL_QUERY ), $q );
		$ck( strpos( $tu, 'https://www.reddit.com/' . $sd['fits'] . '/submit?url=' ) === 0 && ( $q['url'] ?? '' ) === $sd['link'] && ( $q['title'] ?? '' ) === $sd['title'], 'keys ' . $sd['t'] . ': its team subreddit address (' . substr( $tu, 0, 60 ) . ')' ); } }
	$ck( DBD_Pack::reddit_url( 'x', 'https://example.test/', '' ) === '' && strpos( DBD_Pack::reddit_url( 'x', 'https://example.test/', 'r/raiders' ), 'https://www.reddit.com/r/raiders/submit?' ) === 0, 'a team subreddit as given, none when empty' );
	/* Post on X and Post on Bluesky (2.30.0, SCOPE §72) */
	$ck( DBD_Pack::our_x() === 'dxd_NFLfootball' && DBD_Pack::our_bsky() === 'downbydown.bsky.social', 'the account defaults' );
	foreach ( array( '@dxd_NFLfootball' => 'dxd_NFLfootball', 'https://x.com/dxd_NFLfootball?s=21' => 'dxd_NFLfootball', 'twitter.com/Foo_Bar/' => 'Foo_Bar', '' => '' ) as $in => $want ) { $ck( DBD_Pack::sanitize_x( $in ) === $want, "sanitize_x('$in') gives '$want', got '" . DBD_Pack::sanitize_x( $in ) . "'" ); }
	foreach ( array( '@DownByDown.bsky.social' => 'downbydown.bsky.social', 'https://bsky.app/profile/downbydown.bsky.social' => 'downbydown.bsky.social', 'downbydown.com' => 'downbydown.com', '' => '' ) as $in => $want ) { $ck( DBD_Pack::sanitize_bsky( $in ) === $want, "sanitize_bsky('$in') gives '$want', got '" . DBD_Pack::sanitize_bsky( $in ) . "'" ); }
	$all = $items; foreach ( $keys as $kg ) { foreach ( $kg['sides'] as $sd ) { $all[] = $sd; } }
	foreach ( $all as $it ) {
		$so = DBD_Pack::social( $it ); $sl = DBD_Pack::share_link( $it ); $nm = $it['k'] ?? ( 'keys ' . $it['t'] );
		$ck( DBD_Pack::xlen( $so['x'] ) <= 280 && substr( $so['x'], -strlen( $sl ) ) === $sl, $nm . ': the X text ends with its share link within 280' );
		$ck( mb_strlen( $so['bsky'] ) <= 300 && substr( $so['bsky'], -strlen( $sl ) ) === $sl, $nm . ': the Bluesky text ends with its share link within 300 counting the whole link (' . mb_strlen( $so['bsky'] ) . ')' );
		parse_str( (string) parse_url( DBD_Pack::x_url( $so['x'] ), PHP_URL_QUERY ), $q ); $ck( strpos( DBD_Pack::x_url( $so['x'] ), 'https://x.com/intent/post?text=' ) === 0 && ( $q['text'] ?? '' ) === $so['x'], $nm . ': the X address decodes back' );
		parse_str( (string) parse_url( DBD_Pack::bsky_url( $so['bsky'] ), PHP_URL_QUERY ), $q ); $ck( strpos( DBD_Pack::bsky_url( $so['bsky'] ), 'https://bsky.app/intent/compose?text=' ) === 0 && ( $q['text'] ?? '' ) === $so['bsky'], $nm . ': the Bluesky address decodes back' );
		parse_str( (string) parse_url( DBD_Pack::th_url( $it ), PHP_URL_QUERY ), $q ); $ck( strpos( DBD_Pack::th_url( $it ), 'https://www.threads.com/intent/post?text=' ) === 0 && ( $q['text'] ?? '' ) === DBD_Threads::text( $it ) && mb_strlen( $q['text'] ?? '' ) <= 500 && ( $q['url'] ?? '' ) === $sl, $nm . ': the Threads address decodes back to the long takeaway and the share link (2.42.0)' );
	}
	$L2 = 'https://example.test/player/a-long-name/?share=pack-week-10-snaps&and=a-much-longer-tail-to-push-it-over';
	$bp = DBD_Pack::bpost( str_repeat( 'long words here ', 20 ), str_repeat( 'still too long ', 20 ), $L2 ); $ck( mb_strlen( $bp ) <= 300 && strpos( $bp, '… ' . $L2 ) !== false, 'bpost(): cut at a word when both run over 300 with the whole link' );
	$ck( DBD_Pack::bpost( 'Short.', 'Short.', $L2 ) === 'Short. ' . $L2, 'bpost(): the long text when it fits' );
	$ck( DBD_Pack::twitter_site_tag() === '<meta name="twitter:site" content="@dxd_NFLfootball">' . "\n", 'twitter:site' );
	/* Share on Facebook, the Instagram caption and the square (2.31.0, SCOPE §73) */
	$ck( DBD_Pack::our_fb() === 'https://www.facebook.com/groups/988685080499981' && DBD_Pack::our_ig() === 'down_by_down', 'the Facebook and Instagram defaults' );
	foreach ( array( 'facebook.com/groups/988685080499981/' => 'https://www.facebook.com/groups/988685080499981', 'https://m.facebook.com/groups/12?ref=share' => 'https://www.facebook.com/groups/12', 'https://evil.test/facebook.com/x' => '', 'https://www.facebook.com/' => '', '' => '' ) as $in => $want ) { $ck( DBD_Pack::sanitize_fb( $in ) === $want, "sanitize_fb('$in') gives '$want', got '" . DBD_Pack::sanitize_fb( $in ) . "'" ); }
	foreach ( array( '@down_by_down' => 'down_by_down', 'https://www.instagram.com/down_by_down/' => 'down_by_down', 'instagram.com/a.b_c?igsh=1' => 'a.b_c', '' => '' ) as $in => $want ) { $ck( DBD_Pack::sanitize_ig( $in ) === $want, "sanitize_ig('$in') gives '$want', got '" . DBD_Pack::sanitize_ig( $in ) . "'" ); }
	foreach ( $all as $it ) {
		$nm = $it['k'] ?? ( 'keys ' . $it['t'] );
		parse_str( (string) parse_url( DBD_Pack::fb_url( $it ), PHP_URL_QUERY ), $q ); $ck( strpos( DBD_Pack::fb_url( $it ), 'https://www.facebook.com/sharer/sharer.php?u=' ) === 0 && ( $q['u'] ?? '' ) === DBD_Pack::share_link( $it ), $nm . ': the Facebook share box gets its share link' );
		$ft = DBD_Pack::fb_text( $it ); $ck( $ft !== '' && strpos( $ft, 'http' ) === false, $nm . ': the Facebook text has no link' );
		$cap = DBD_Pack::ig_caption( $it ); $tags = array( '#NFL' ); foreach ( $it['teams'] as $t ) { $tags[] = DBD_Pack::tag( $t ); }
		$ck( strpos( $cap, $ft . "\n\nFull chart: link in bio (" ) === 0 && substr( $cap, -strlen( implode( ' ', array_unique( array_merge( $tags, empty( $it['ff'] ) ? array() : array( '#FantasyFootball' ) ) ) ) ) ) === implode( ' ', array_unique( array_merge( $tags, empty( $it['ff'] ) ? array() : array( '#FantasyFootball' ) ) ) ), $nm . ': the caption (' . str_replace( "\n", ' / ', $cap ) . ')' );
		$ck( ( strpos( $cap, '#FantasyFootball' ) !== false ) === in_array( $it['k'] ?? '', array( 'snaps', 'targets' ), true ), $nm . ': #FantasyFootball only on the usage items' );
		$sq = DBD_Pack::spec_square( $it['sq'] ); $ck( DBD_Card::valid( $it['sq'] ) && $sq && $sq['head'] === $it['ts'] && DBD_Card::valid( $sq['card'] ) && preg_match( '/-square\.png$/', $it['sqfile'] ), $nm . ': its square key (' . $it['sq'] . ')' );
	}
	$ck( DBD_Pack::tag( 'SF' ) === '#49ers' && DBD_Pack::tag( 'WAS' ) === '#Commanders', 'team hashtags' );
	foreach ( array( 'ig-week-10-nope', 'ig-week-123-swing', 'ig-week-10-keys-2026_11_IND_HOU', 'ig-week-10-keys-x-IND' ) as $bk ) { $ck( ! DBD_Card::valid( $bk ), "card key $bk is not valid" ); }
	$ck( DBD_Pack::spec_square( 'ig-week-99-swing' ) === null && DBD_Pack::spec_square( 'ig-week-' . $n . '-keys-2026_01_XXX_YYY-XXX' ) === null, 'a square for nothing gives nothing' );
	if ( DBD_Card::available() ) {
		$draw = array( $items[0]['sq'], $items[ count( $items ) - 1 ]['sq'] ); if ( $keys ) { $draw[] = $keys[0]['sides'][0]['sq']; }
		foreach ( $draw as $sk ) { $im = DBD_Card::render( $sk ); $ck( $im && imagesx( $im ) === 1080 && imagesy( $im ) === 1080, "the square $sk draws at 1080x1080" ); if ( $im ) { imagedestroy( $im ); } }
		$ck( DBD_Card::render( 'ig-week-99-swing' ) === null, 'a square for a week that isn\'t there draws nothing' );
	}
	$GLOBALS['dbd_opt'] = array( DBD_Pack::OPT_FB => '', DBD_Pack::OPT_IG => '' );
	$ck( DBD_Pack::fb_url( $items[0] ) === '' && DBD_Pack::ig_caption( $items[0] ) === '', 'cleared Facebook and Instagram: no share box, no caption' );
	$GLOBALS['dbd_opt'] = array( DBD_Pack::OPT_X => '', DBD_Pack::OPT_BSKY => '' );
	$ck( DBD_Pack::x_url( 'x' ) === '' && DBD_Pack::bsky_url( 'x' ) === '' && DBD_Pack::twitter_site_tag() === '', 'cleared accounts: no buttons, no twitter:site' );
	unset( $GLOBALS['dbd_opt'] );
	$long = DBD_Pack::reddit_url( str_repeat( 'word ', 80 ), 'https://example.test/x/' ); parse_str( (string) parse_url( $long, PHP_URL_QUERY ), $q );
	$ck( mb_strlen( $q['title'] ?? '' ) <= 300 && substr( $q['title'] ?? '', -3 ) === '…', 'a title over 300 is cut at a word with an ellipsis' );
	foreach ( array( 'pack-week-10-snaps' => 'pack-week-10-snaps', 'pack-week-3-offense' => 'pack-week-3-offense', 'team-MIN' => '', 'pack-week-1-nope' => '', '<x>' => '', 'pack-week-123-snaps' => '' ) as $in => $want ) { $ck( DBD_Pack::share_key( $in ) === $want, "share_key('$in')" ); }
	$GLOBALS['dbd_opt'] = array( DBD_Pack::OPT_SUB => '' );
	$ck( DBD_Pack::reddit_url( 'x', 'https://example.test/' ) === '', 'no subreddit, no Reddit address' );
	$GLOBALS['dbd_opt'] = array( DBD_Pack::OPT_SUB => 'r/Other_Sub' );
	$ck( strpos( DBD_Pack::reddit_url( 'x', 'https://example.test/' ), 'https://www.reddit.com/r/Other_Sub/submit?' ) === 0, 'another subreddit is used as set' );
	unset( $GLOBALS['dbd_opt'] );
	/* a page asked for with ?share= shows that pack card as its preview; the canonical stays clean; a bad key changes nothing */
	if ( DBD_Card::available() && isset( $by['snaps'] ) ) {
		$head = function ( $get ) { $_GET = $get; DBD_Router::$route = DBD_Router::route(); ob_start(); DBD_Router::head(); return ob_get_clean(); };
		$pid = $by['snaps']['card'];
		$pl = (string) ( DBD_Pack::mover( DBD_Week::file( $n ), 'snaps' )['id'] ?? '' );
		$plain = $head( array( 'player' => $pl ) ); $with = $head( array( 'player' => $pl, 'share' => $pid ) ); $bad2 = $head( array( 'player' => $pl, 'share' => 'team-MIN' ) );
		$ck( $pl !== '' && strpos( $with, 'og:image" content="' ) !== false && strpos( $with, 'card=' . $pid ) !== false, 'share=: og:image is the pack card' );
		$ck( strpos( $with, 'twitter:image" content="' ) !== false && substr_count( $with, 'card=' . $pid ) === 2, 'share=: twitter:image too' );
		$ck( (bool) preg_match( '/og:url" content="[^"]*share=' . preg_quote( $pid, '/' ) . '"/', $with ), 'share=: og:url carries it' );
		preg_match( '/rel="canonical" href="([^"]*)"/', $with, $c1 ); preg_match( '/rel="canonical" href="([^"]*)"/', $plain, $c2 );
		$ck( ( $c1[1] ?? 'x' ) === ( $c2[1] ?? 'y' ) && strpos( $c1[1] ?? '', 'share' ) === false, 'share=: the canonical stays clean' );
		$ck( $bad2 === $plain, 'a share key that isn\'t a pack card changes nothing' );
		$ck( strpos( $plain, '<meta name="twitter:site" content="@dxd_NFLfootball">' ) !== false, 'app pages carry twitter:site' );
		$_GET = array();
	} else { echo "  (no GD or no riser: ?share= head checks skipped)\n"; }
	echo "pack week $n: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
if ( $view === 'admin' ) {
	$season = (int) ( DBD_Data::root_manifest()['season'] ?? 2026 );
	$wks = array_values( array_filter( DBD_Week::weeks(), function ( $w ) { $f = DBD_Week::file( $w ); return $f && ! empty( $f['complete'] ); } ) );
	$last = $wks ? max( $wks ) : 1;
	$GLOBALS['dbd_opt'] = array(
		'dbd_posts' => array( $season => array( $last - 1 => 102, $last => 101 ) ),
		'dbd_email' => array( 'on' => true, 'key' => '', 'list' => 3, 'tpl' => 0 ),
		'dbd_email_log' => array( array( 'at' => 1793800000, 'post' => 103, 'title' => 'Week 0 in review', 'result' => 'failed: Brevo answered 405: You are not allowed to avail tag option for your campaign', 'camp' => 0, 'n' => 0 ), array( 'at' => 1793700000, 'post' => 102, 'title' => 'Week ' . ( $last - 1 ) . ' in review', 'result' => 'sent', 'camp' => 4, 'n' => 198 ) ),
		'dbd_email_signups' => array( 'ok' => 16, 'last' => 1793900000, 'err' => 1, 'last_err' => array( 1793950000, 'Brevo answered 400: invalid email' ) ),
		'dbd_sync_log' => array( array( 'time' => '2026-11-11 11:04:00', 'trigger' => 'push', 'ok' => false, 'error' => 'manifest.json: HTTP 502', 'changed' => array(), 'seconds' => 2.1 ), array( 'time' => '2026-11-10 23:05:00', 'trigger' => 'push', 'ok' => true, 'error' => '', 'changed' => array( 'a', 'b' ), 'seconds' => 4.1 ) ),
		'dbd_news_status' => array( 'at' => 1793900000, 'secs' => 7, 'feeds' => array( 'site:MIN' => array( 'ok' => true, 'n' => 5, 'err' => '' ), 'fan:WAS' => array( 'ok' => false, 'err' => 'HTTP 503', 'last' => 1793800000 ) ) ),
		'dbd_sync_token' => 'harness-token',
		'dbd_bsky_conn' => array( 'pass' => 'app-pass', 'handle' => 'downbydown.bsky.social', 'did' => 'did:plc:harness', 'checked' => 1793900000 ),  /* Auto publish (SCOPE §81): Bluesky connected, Instagram not */
		'googlesitekit_analytics-4_settings' => array( 'propertyID' => '123456' ),
		'googlesitekit_search-console_settings' => array( 'propertyID' => 'https://example.test/' ),
	);
	$GLOBALS['hposts'] = array( 101 => (object) array( 'ID' => 101, 'post_title' => 'Week ' . $last . ' in review', 'post_status' => 'future', 'post_date_gmt' => '2026-11-17 13:00:00', 'post_type' => 'post' ), 102 => (object) array( 'ID' => 102, 'post_title' => 'Week ' . ( $last - 1 ) . ' in review', 'post_status' => 'publish', 'post_date_gmt' => '2026-11-10 13:00:00', 'post_type' => 'post' ), 103 => (object) array( 'ID' => 103, 'post_title' => 'Week 0 in review', 'post_status' => 'publish', 'post_date_gmt' => '2026-11-03 13:00:00', 'post_type' => 'post' ) );
	$GLOBALS['hmeta'] = array( 101 => array( '_dbd_week' => $last ), 102 => array( '_dbd_week' => $last - 1, '_dbd_email_state' => 'sent' ), 103 => array( '_dbd_week' => 0, '_dbd_email_state' => 'failed: Brevo answered 405' ) );
	foreach ( array(
		'get_posts' => function ( $a ) { $st = (array) ( $a['post_status'] ?? 'publish' ); $o = array_values( array_filter( $GLOBALS['hposts'], function ( $p ) use ( $st ) { return in_array( $p->post_status, $st, true ); } ) ); return ( $a['fields'] ?? '' ) === 'ids' ? array_map( function ( $p ) { return $p->ID; }, $o ) : $o; },
		'get_post' => function ( $id ) { return $GLOBALS['hposts'][ (int) $id ] ?? null; },
		'get_post_meta' => function ( $id, $k = '', $one = false ) { return $GLOBALS['hmeta'][ (int) $id ][ $k ] ?? ''; },
		'get_post_status' => function ( $id ) { return $GLOBALS['hposts'][ (int) $id ]->post_status ?? false; },
		'get_the_title' => function ( $p ) { return is_object( $p ) ? $p->post_title : ( $GLOBALS['hposts'][ (int) $p ]->post_title ?? '' ); },
		'get_edit_post_link' => function ( $id, $c = 'display' ) { return 'https://example.test/wp-admin/post.php?post=' . (int) $id . '&action=edit'; },
		'get_date_from_gmt' => function ( $d, $f = 'Y-m-d H:i:s' ) { $x = new DateTime( $d, new DateTimeZone( 'UTC' ) ); $x->setTimezone( new DateTimeZone( 'America/New_York' ) ); return $x->format( $f ); },
		'admin_url' => function ( $p = '' ) { return 'https://example.test/wp-admin/' . $p; },
		'settings_fields' => function ( $g ) { echo '<input type="hidden" name="option_page" value="' . esc_attr( $g ) . '">'; },
		'submit_button' => function ( $t = 'Save', $c = 'primary', $n = 'submit', $w = true ) { echo '<input type="submit" name="' . esc_attr( $n ) . '" class="button button-' . esc_attr( $c ) . '" value="' . esc_attr( $t ) . '">'; },
		'wp_nonce_field' => function ( $a = -1, $n = '_wpnonce', $r = true, $e = true ) { $h = '<input type="hidden" name="' . esc_attr( $n ) . '" value="nonce">'; if ( $e ) { echo $h; } return $h; },
		'wp_nonce_url' => function ( $u, $a = -1 ) { return $u . '&_wpnonce=nonce'; },
		'esc_textarea' => function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); },
		'size_format' => function ( $b, $d = 0 ) { return round( $b / 1048576, $d ) . ' MB'; },
		'current_user_can' => function () { return true; },
		'sanitize_key' => function ( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); },
		'is_email' => function ( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); },
		'sanitize_email' => function ( $e ) { return preg_replace( '/[^a-z0-9@._+\-]/i', '', (string) $e ); },  /* the Share pack's one Save (SCOPE §74) */
		'add_menu_page' => function () {}, 'add_submenu_page' => function () {}, 'register_setting' => function () {},
		'delete_option' => function ( $k ) { unset( $GLOBALS['dbd_opt'][ $k ] ); return true; }, 'delete_transient' => function () { return true; },  /* Bluesky's settings (SCOPE §81) */
	) as $fn => $body ) { if ( ! function_exists( $fn ) ) { $GLOBALS['hfn'][ $fn ] = $body; eval( 'function ' . $fn . '( ...$a ) { return $GLOBALS["hfn"]["' . $fn . '"]( ...$a ); }' ); } }
	require DBD_PATH . 'includes/admin/settings.php';
	$want = array( 'ov' => array( 'Needs a look', 'The last data sync failed', 'needs a Brevo API key', 'The last signup didn’t reach Brevo', 'Its send log row has Try again', 'Open Week in review', 'Sync now', 'Fetch news and videos now', 'news feed' ),
		'wk' => array( 'The weekly post', 'Weeks', 'The Tuesday email', 'Send log', 'sent to 198', 'won’t send until Brevo is set up', 'Waiting for the confirmation template', 'action=dbd_email_again&post=103', 'Try again', 'id="dbd-boards"', 'Board emails', 'name="dbd_email[boards]"', 'Add the Brevo API key above to send a test.' ),
		'nw' => array( 'Fetching', 'Sources', 'HTTP 503', 'Fetch news and videos now', 'Videos', 'Game highlights' ),
		'st' => array( 'Photos and logos', 'Addresses', 'Search and sharing', 'Using it in the theme', 'value="dbd"' ),
		'dt' => array( 'Current data', 'Sync log', 'Seasons', 'Share cards', 'value="dbd_data"', 'harness-token' ),
		'gr' => array( 'Visits by source', 'data-g="sources"', 'Google search', 'data-g="search"', 'Titles to tune', 'data-g="tune"', 'data-g="copy"', 'Top landing pages', 'data-g="landing"', 'Brevo isn’t set up', 'data-g="shares"', 'data-g="signups"', 'analytics.google.com/analytics/web/#/p123456/', 'resource_id=https%3A%2F%2Fexample.test%2F', 'page=googlesitekit-dashboard' ) );
	$want['sp'] = array( 'class="sp-po"', 'Playoff posts', 'The Playoffs switch is off, so none will post.', 'Not ready yet', 'class="sp-views"', 'class="sp-cal"', 'class="sp-post"', 'class="sp-legend"', 'class="sp-next"', 'class="sp-d on"', 'Where it goes', '9:00 AM', 'name="week"', 'Reddit, X, Bluesky and Threads open with the post filled in.',
		'The biggest swing', 'A usage riser', 'Best and worst offense', 'A target-share mover', 'Week leaders', '-leaders.png"', 'Keys to the game, Week ', 'class="sp-game"', 'Post by hand', 'Also fits r/',
		'<details class="sp-tx"><summary>Texts to copy</summary>', 'Reddit title', 'Reddit first comment, with the link', 'X and Bluesky', ' of 280', 'Instagram caption', 'class="button button-small sp-cp"',
		'download="dbd-week-', 'card=pack-week-', 'Download graphic', 'card=tall-week-', 'Download the game card',
		'class="button sp-rd" aria-label="Post to r/downbydown"', 'https://www.reddit.com/r/downbydown/submit?url=', 'share%3Dpack-week-', '>r/downbydown</a>',
		'class="button sp-x" aria-label="Post on X"', 'https://x.com/intent/post?text=', 'class="button sp-bs" aria-label="Post on Bluesky"', 'https://bsky.app/intent/compose?text=', 'class="button sp-th" aria-label="Post on Threads"', 'https://www.threads.com/intent/post?text=', '>Threads</a>',
		'class="button sp-fb"', 'sharer.php?u=', '>Facebook</a>', 'class="button sp-cp sp-ig"', '>Instagram caption</button>', 'aria-label="Download the graphic">Graphic</a>',
		'<form id="dbd-pack-settings"', 'Posting accounts', 'Posted by hand', 'The Tuesday email', 'class="sp-acct"', 'class="button sp-chg"', 'value="dbd_pack_settings"', 'name="dbd_pack_mail_set[on]"', 'name="dbd_pack_mail_set[to]"', 'Brevo isn’t set up',
		'name="dbd_pack_sub"', 'name="dbd_pack_x"', 'name="dbd_pack_bsky"', 'name="dbd_pack_fb"', 'name="dbd_pack_ig"', 'Open the group', 'Save settings', 'name="dbd_ig_set[on]"', 'name="dbd_ig_set[account]"', 'name="dbd_ig_token_new"', 'Paste the token from your Meta app', 'Not connected' );  /* SCOPE §68, §71–74, §87 */
	array_push( $want['sp'], 'class="sp-ap"', 'class="sp-apd"', '<summary class="button button-primary">Post now to ', 'value="dbd_auto_publish"', 'name="slot" value="swing"', 'to Bluesky now?</b>', 'class="button button-primary sp-apgo"', 'class="button sp-apx"', 'id="sp-swing"', 'id="sp-keys-', 'name="slot" value="keys-',
		'name="dbd_bsky_pass_new"', 'Connected as @downbydown.bsky.social', 'name="dbd_bsky_disconnect"', 'name="dbd_ap_set[ig]"', 'name="dbd_ap_set[bs]"', 'Reddit and Facebook can’t post on their own.' );  /* Auto publish (SCOPE §81) */
	array_push( $want['sp'], 'name="dbd_x_set[on]" value="1" checked', 'name="dbd_x_ck_new"', 'name="dbd_x_cs_new"', 'name="dbd_x_at_new"', 'name="dbd_x_as_new"', 'Paste all four together.', 'name="dbd_ap_set[x]" value="1" checked' );  /* X posting (SCOPE §83): not connected in this harness */
	array_push( $want['sp'], 'name="dbd_pack_th"', 'value="down_by_down"', 'name="dbd_th_set[on]" value="1" checked', 'name="dbd_th_secret_new"', 'name="dbd_th_token_new"', 'threads_manage_replies', 'User Token Generator', 'name="dbd_ap_set[th]" value="1" checked' );  /* Threads posting (SCOPE §84) */
	array_push( $want['sp'], 'name="dbd_bsky_set[on]" value="1" checked' );  /* Bluesky's days (SCOPE §82) */
	if ( in_array( 'rush', array_column( DBD_Pack::items( DBD_Pack::newest() ), 'k' ), true ) ) { array_push( $want['sp'], 'Pass rushers', '-pass-rushers.png"' ); }  /* SCOPE §79, only for the week the defense files run through */
	$want['po'] = array( 'Playoff coverage', 'Turn on', 'value="dbd_playoffs_switch"', 'What it controls' );  /* the Playoffs switch (SCOPE §88) */
	$want['ov'][] = 'Open Growth'; $want['ov'][] = 'data-g="ov-visits"'; $want['ov'][] = 'data-g="ov-clicks"';
	$bad = 0; $dir = getenv( 'DBD_HARNESS_HTML' ); if ( $dir && ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
	foreach ( dbd_admin_tabs() as $k => $t ) {
		if ( $arg && $arg !== 'all' && $arg !== $k ) { continue; }
		$h = '';  /* the Share pack's three views, one page each (SCOPE §87) */
		foreach ( $k === 'sp' ? array( 'schedule', 'keys', 'accounts' ) : array( '' ) as $spv ) {
			$_GET = array( 'page' => $t[0] ) + ( $spv !== '' ? array( 'view' => $spv ) : array() );
			ob_start(); try { dbd_settings_page(); } catch ( Throwable $e ) { ob_end_clean(); fwrite( STDERR, "FATAL in admin $k: " . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
			$h .= ob_get_clean();
		}
		$miss = array_filter( $want[ $k ], function ( $x ) use ( $h ) { return strpos( $h, $x ) === false; } );
		$forms = substr_count( $h, '<form' ); $nested = preg_match( '#<form(?:(?!</form>).)*<form#s', $h );
		echo "  admin $k (" . $t[1] . '): ' . strlen( $h ) . " bytes, $forms forms" . ( $miss ? ', MISSING: ' . implode( ' | ', $miss ) : '' ) . ( $nested ? ', A FORM INSIDE A FORM' : '' ) . "\n";
		$bad += count( $miss ) + $nested;
		if ( $dir ) { file_put_contents( "$dir/admin-$k.html", '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="https://example.test/wp-admin/css/common.css">' . $h ); }
	}
	/* the Share pack's one Save (SCOPE §74): every field through its own sanitizer, the list kept apart from the accounts */
	if ( ! $arg || $arg === 'all' || $arg === 'sp' ) {
		$to = dbd_pack_settings_save( array( 'week' => '9', 'dbd_pack_mail_set' => array( 'on' => '1', 'to' => "Erik@Example.test\nbad\ngene@example.test, gene@example.test" ), 'dbd_pack_sub' => 'https://www.reddit.com/r/DownByDown/', 'dbd_pack_x' => '@dxd_NFLfootball', 'dbd_pack_bsky' => '@DownByDown.bsky.social', 'dbd_pack_fb' => 'facebook.com/groups/42/', 'dbd_pack_ig' => 'https://instagram.com/down_by_down/' ) );
		$got = array( get_option( 'dbd_pack_mail_set' ), get_option( 'dbd_pack_sub' ), get_option( 'dbd_pack_x' ), get_option( 'dbd_pack_bsky' ), get_option( 'dbd_pack_fb' ), get_option( 'dbd_pack_ig' ) );
		$ok = $got === array( array( 'on' => true, 'to' => array( 'erik@example.test', 'gene@example.test' ) ), 'DownByDown', 'dxd_NFLfootball', 'downbydown.bsky.social', 'https://www.facebook.com/groups/42', 'down_by_down' );
		$ok2 = strpos( $to, 'page=down-by-down-share' ) !== false && strpos( $to, 'week=9' ) !== false && strpos( $to, 'pack=saved' ) !== false && strpos( $to, 'view=accounts' ) !== false && strpos( $to, '#' ) === false;
		dbd_pack_settings_save( array( 'dbd_pack_mail_set' => array( 'to' => 'a@example.test' ), 'dbd_pack_sub' => '', 'dbd_pack_fb' => 'https://evil.test/' ) );
		$ok3 = get_option( 'dbd_pack_mail_set' ) === array( 'on' => false, 'to' => array( 'a@example.test' ) ) && get_option( 'dbd_pack_sub' ) === '' && get_option( 'dbd_pack_fb' ) === '';
		echo '  sp save: ' . ( $ok && $ok2 && $ok3 ? 'ok' : 'WRONG ' . json_encode( array( $ok, $ok2, $ok3, $got ) ) . ' ' . $to ) . "\n"; $bad += $ok && $ok2 && $ok3 ? 0 : 1;
		$_GET = array( 'page' => 'down-by-down-share', 'pack' => 'saved' ); ob_start(); dbd_settings_page(); $h2 = ob_get_clean();
		$open = strpos( $h2, '<form id="dbd-pack-settings"' ) !== false && strpos( $h2, 'Settings saved.' ) !== false;
		echo '  sp after a save: ' . ( $open ? 'Accounts and email opens with its notice' : 'WRONG: a save should come back to Accounts and email with "Settings saved."' ) . "\n"; $bad += $open ? 0 : 1;
		/* the calendar (SCOPE §87): what each record shows, which view opens, and the clock it reads */
		$cal = array(); $cc = function ( $what, $ok ) use ( &$cal, &$bad ) { if ( ! $ok ) { $cal[] = $what; $bad++; } };
		$sn = DBD_Pack::newest(); $key = DBD_AutoPub::key( $sn, 'swing' ); $jk = 'dbd_bsky_job_' . $key; $was = $GLOBALS['dbd_opt'][ $jk ] ?? null; $at = gmmktime( 14, 0, 0, 11, 17, 2026 );
		$wc = $GLOBALS['dbd_opt']['dbd_bsky_conn'] ?? null; $ws = $GLOBALS['dbd_opt']['dbd_bsky_set'] ?? null; $GLOBALS['dbd_opt']['dbd_bsky_conn'] = array( 'pass' => 'p', 'handle' => 'downbydown.bsky.social', 'did' => 'did:plc:test', 'checked' => time() ); $GLOBALS['dbd_opt']['dbd_bsky_set'] = array( 'on' => true );
		foreach ( array( array( 'booked', 'scheduled', 'Scheduled Tue 9:00 AM' ), array( 'sent', 'posted', 'Posted Tue 9:00 AM' ), array( 'failed: nope', 'failed', 'Didn’t post: nope' ), array( 'skipped: more than a day late', 'skipped', 'Skipped: more than a day late' ), array( 'not posted: off', 'skipped', 'Not posted: off' ), array( 'retrying: slow', 'scheduled', 'Retrying: slow' ) ) as $c ) {
			$GLOBALS['dbd_opt'][ $jk ] = array( 'state' => $c[0], 'at' => $at, 'slot_at' => $at, 'sent_at' => $at, 'link' => 'https://example.test/p' ); $x = dbd_share_state( $key, 'bs', $at );
			$cc( 'state ' . $c[0] . ' => ' . json_encode( $x ), $x['class'] === $c[1] && $x['line'] === $c[2] );
			if ( $c[1] === 'failed' ) { $cc( 'a failed post has Try again', strpos( $x['again'], 'dbd_bsky_again' ) !== false ); }
			if ( $c[1] === 'posted' ) { $cc( 'a sent post has its link', $x['link'] === 'https://example.test/p' ); }
		}
		$GLOBALS['dbd_opt']['dbd_bsky_set'] = array( 'on' => false ); $GLOBALS['dbd_opt'][ $jk ] = array( 'state' => 'booked', 'at' => $at, 'slot_at' => $at ); $x = dbd_share_state( $key, 'bs', $at ); $cc( 'a booked post with the switch off reads Off', $x['class'] === 'idle' && $x['word'] === 'Off' );
		foreach ( array( 'dbd_bsky_conn' => $wc, 'dbd_bsky_set' => $ws, $jk => $was ) as $ok_k => $ok_v ) { if ( $ok_v === null ) { unset( $GLOBALS['dbd_opt'][ $ok_k ] ); } else { $GLOBALS['dbd_opt'][ $ok_k ] = $ok_v; } }
		$cc( 'no record, item off the schedule', dbd_share_state( DBD_AutoPub::key( $sn, 'rush' ), 'bs', 0, 'extra' )['class'] === 'idle' );
		foreach ( array( array( array( 'view' => 'keys' ), 'keys' ), array( array( 'view' => 'accounts' ), 'accounts' ), array( array( 'view' => 'nope' ), 'schedule' ), array( array( 'pack' => 'saved' ), 'accounts' ), array( array( 'pack' => 'sent' ), 'accounts' ), array( array( 'pack' => 'igagain' ), 'schedule' ), array( array( 'pack' => 'igerror' ), 'schedule' ), array( array(), 'schedule' ) ) as $c ) { $_GET = $c[0]; $cc( 'view for ' . json_encode( $c[0] ), dbd_share_view() === $c[1] ); }
		$ps = dbd_share_posts( $sn, true ); $ts = array_column( $ps, 't' ); $st2 = $ts; sort( $st2 ); $cc( 'posts in time order', $ts === $st2 && $ps );
		foreach ( $ps as $p ) { $cc( $p['slot'] . ' sits on its clock', dbd_share_et( $p['t'], 'D g:i A' ) === array( 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun', 'Mon' )[ $p['day'] ] . ( $p['eve'] ? ' 5:00 PM' : ' 9:00 AM' ) ); }
		$cc( 'after Week 18 nothing is coming up', dbd_share_upcoming( array( 18 ) ) === 0 && dbd_share_upcoming( array() ) === 0 );
		$fu = DBD_Tall::$from; DBD_Tall::$from = array( (int) DBD_Data::season() => 1 ); $pl = dbd_share_posts( $sn, false ); DBD_Tall::$from = array( (int) DBD_Data::season() => 99 ); $pl2 = dbd_share_posts( $sn, false ); DBD_Tall::$from = $fu;
		$cc( 'a coming week lists all ten posts, unposted', count( $pl ) === 10 && ! array_filter( $pl, function ( $p ) { return $p['pic'] !== ''; } ) );
		$cc( 'a week before the boards has no evening posts', count( $pl2 ) === 7 && ! array_filter( $pl2, function ( $p ) { return $p['eve']; } ) );
		echo '  sp calendar: ' . ( $cal ? 'WRONG ' . implode( ' | ', $cal ) : 'ok' ) . "\n";
	}
	echo "admin: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
/* ---- the "You're in" note (2.10.2): on the viewed week post's body only, once, and with no loop (Divi's Post Content module) */
if ( $view === 'joined_js' ) { echo DBD_Email::joined_js(), "\n"; exit( 0 ); }  /* the sign_up event's script (2.25.0) */
if ( $view === 'joined' ) {
	$GLOBALS['hj'] = array( 'id' => 7, 'viewed' => 7, 'week' => '8' );
	function get_the_ID() { return $GLOBALS['hj']['id']; } function get_queried_object_id() { return $GLOBALS['hj']['viewed']; }
	function get_post_meta( $id, $k, $one = false ) { return $k === '_dbd_week' ? $GLOBALS['hj']['week'] : ''; } function is_feed() { return false; }
	$body = '<div class="dbd-post"><p class="open">Three upsets.</p></div>';
	$cases = array(  /* name, query, viewed post, post being drawn, its week, content, note expected */
		array( 'the landing, outside the loop', 1, 7, 7, '8', $body, true ),
		array( 'no ?dbd_joined', 0, 7, 7, '8', $body, false ),
		array( 'another post drawn on the page (a blog grid)', 1, 7, 9, '8', $body, false ),
		array( 'content that is not the week body', 1, 7, 7, '8', '<p>A sidebar text.</p>', false ),
		array( 'already has the note', 1, 7, 7, '8', DBD_Email::joined_note( 8 ) . $body, false ),
		array( 'a post that is not a week post', 1, 7, 7, '', $body, false ),
	);
	$bad = 0;
	foreach ( $cases as $c ) {
		$_GET = $c[1] ? array( 'dbd_joined' => '1' ) : array(); $GLOBALS['hj'] = array( 'viewed' => $c[2], 'id' => $c[3], 'week' => $c[4] );
		$out = DBD_Email::joined( $c[5] ); $has = substr_count( $out, 'dbd-joined' ) - substr_count( $c[5], 'dbd-joined' ) === 1;
		echo '  ' . ( $has === $c[6] ? 'ok' : 'WRONG' ) . ': ' . $c[0] . ( $has ? ' (note added)' : '' ) . "\n"; $bad += $has !== $c[6];
	}
	if ( strpos( DBD_Email::joined( $body ), 'Week 8' ) === false && $bad === 0 ) { $_GET = array( 'dbd_joined' => '1' ); $GLOBALS['hj'] = array( 'viewed' => 7, 'id' => 7, 'week' => '8' ); if ( strpos( DBD_Email::joined( $body ), 'Week 8' ) === false ) { echo "  WRONG: the note names the week\n"; $bad++; } }
	echo "joined: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
if ( $view === 'sitemap' ) {
	try { foreach ( DBD_Seo::urls() as $u ) { echo $u['loc'] . ( getenv( 'DBD_HARNESS_LASTMOD' ) ? ' ' . ( $u['lastmod'] ?? '-' ) : '' ) . "\n"; } } catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in sitemap: ' . $e->getMessage() . "\n" ); exit( 1 ); }
	exit( $warnings ? 1 : 0 );
}

/* ---- live blocks: one block, or a theme page (divi/home-hero.html) with its [dbd_block] shortcodes drawn, written to DBD_HARNESS_HTML */
if ( $view === 'block' || $view === 'page' ) {
	try {
		if ( $view === 'block' ) {
			parse_str( (string) ( $argv[4] ?? '' ), $atts ); $html = DBD_Blocks::render( $arg, $atts ); $name = 'block-' . $arg;
			echo "block $arg: " . strlen( $html ) . " bytes, $warnings warnings\n";
			$min = array( 'counts' => 100, 'player-card' => 150 )[ $arg ] ?? 600;  /* counts is one line; player-card is one linked image */
			if ( strlen( $html ) < $min ) { fwrite( STDERR, "block $arg: suspiciously short\n" ); exit( 3 ); }
		} else {
			$src = (string) @file_get_contents( $arg ); if ( $src === '' ) { fwrite( STDERR, "no page file $arg\n" ); exit( 2 ); }
			$n = 0; $html = preg_replace_callback( '/\[dbd_block\s+([^\]]*)\]/', function ( $m ) use ( &$n ) { $n++; preg_match_all( '/(\w+)="([^"]*)"/', $m[1], $kv, PREG_SET_ORDER ); $a = array(); foreach ( $kv as $p ) { $a[ strtolower( $p[1] ) ] = $p[2]; } return DBD_Blocks::render( strtolower( $a['type'] ?? '' ), $a ); }, $src );
			$name = 'page-' . basename( $arg, '.html' );
			echo "page $arg: $n blocks, " . strlen( $html ) . " bytes, $warnings warnings\n";
			if ( preg_match( '/\[dbd_block\s/', $html ) || strpos( $html, 'class="dbd-hb' ) === false ) { fwrite( STDERR, "page $arg: a block did not render\n" ); exit( 3 ); }
		}
	} catch ( Throwable $e ) { fwrite( STDERR, "FATAL in $view $arg: " . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	$dir = getenv( 'DBD_HARNESS_HTML' );
	if ( $dir ) {
		if ( ! is_dir( $dir . '/assets' ) ) { mkdir( $dir . '/assets', 0777, true ); } copy( DBD_PATH . 'assets/blocks.css', $dir . '/assets/blocks.css' );
		$fonts = '<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">';
		$divi = '<style>body{margin:0;background:#f4f1ea}p{padding-bottom:1em}ul,ol{list-style:disc;padding:0 0 23px 1em}a{color:#2ea3f2}</style>';  /* stand-ins for Divi's own rules, which the blocks must hold up against */
		file_put_contents( $dir . '/' . $name . '.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $fonts . $divi . '<link rel="stylesheet" href="assets/blocks.css"></head><body>' . $html . '</body></html>' );
	}
	exit( $warnings ? 1 : 0 );
}

/* ---- every throw (SCOPE §90): a finished game's throws team by team, a quarterback's games from every team he threw for, and the panel's embedded data */
if ( $view === 'throws' ) {
	$fail = function ( $m ) { fwrite( STDERR, "throws: $m\n" ); exit( 1 ); };
	$g = DBD_Data::game( '2026_11_MIN_SF' ); if ( ! $g ) { $fail( 'no Week 11 Vikings at 49ers' ); }
	$d = DBD_Throws::game( $g ); if ( ! $d || count( $d['sets'] ) !== 2 ) { $fail( 'the game should have both teams’ throws' ); }
	if ( $d['sets'][0]['t'] !== 'MIN' || count( $d['sets'][0]['rows'] ) !== 43 || $d['sets'][1]['t'] !== 'SF' || count( $d['sets'][1]['rows'] ) !== 27 ) { $fail( 'Vikings 43 and 49ers 27 throws, away first; got ' . $d['sets'][0]['t'] . ' ' . count( $d['sets'][0]['rows'] ) . ', ' . $d['sets'][1]['t'] . ' ' . count( $d['sets'][1]['rows'] ) ); }
	if ( ( $d['names']['00-0029604'] ?? null ) !== array( 'Kirk Cousins', '8' ) ) { $fail( 'Cousins’ name and number' ); }
	foreach ( $d['sets'] as $s ) { foreach ( $s['rows'] as $r ) { if ( count( $r ) !== 13 || ( $r[12] !== null && ! is_int( $r[12] ) ) || ! in_array( $r[4], array( 'c', 'i', 'x' ), true ) || $r[7] < -1 || $r[7] > 2 || ! isset( $d['names'][ $r[10] ] ) ) { $fail( 'a bad row: ' . json_encode( $r ) ); } } }
	$h = DBD_Throws::game_panel( $g ); if ( strpos( $h, 'class="dbd-panel dbd-thr" data-kind="game"' ) === false || ! preg_match( '#<script type="application/json" class="dbd-thr-d">(.*)</script>#s', $h, $m ) || ! is_array( json_decode( $m[1], true ) ) ) { $fail( 'the game panel or its data' ); }
	foreach ( DBD_Data::week_games( 18 ) as $x ) { if ( ! DBD_Router::is_final( $x ) && DBD_Throws::game_panel( $x ) !== '' ) { $fail( 'a game still to play has no throws panel' ); } }
	$rec = DBD_Data::player_record( 'MIN', '00-0029604' ); $q = DBD_Throws::qb( $rec + array( 'team' => 'MIN' ) ); if ( ! $q ) { $fail( 'Cousins has no games' ); }
	$wk = array_column( $q['sets'], 'week' ); $tm = array_unique( array_column( $q['sets'], 't' ) ); $n = 0; foreach ( $q['sets'] as $s ) { $n += count( $s['rows'] ); }
	if ( count( array_unique( array_column( $q['sets'], 'g' ) ) ) !== count( $q['sets'] ) ) { $fail( 'one entry a game' ); }
	$sorted = $wk; rsort( $sorted ); if ( $wk !== $sorted || ! in_array( 'LV', $tm, true ) || ! in_array( 'MIN', $tm, true ) || ! in_array( 1, $wk, true ) || $n < 381 ) { $fail( 'Cousins’ games, newest first, Raiders and Vikings, 381+ throws; got ' . implode( ',', $wk ) . ' / ' . implode( ',', $tm ) . " / $n" ); }
	if ( DBD_Throws::qb( array( 'id' => '00-0000000', 'team' => 'MIN' ) ) !== null ) { $fail( 'a player with no throws has no panel' ); }
	/* field use (SCOPE §91): the league's spreads, sorted and between 0 and 1, and the replay's score margin */
	$lg = DBD_Throws::league(); $sg = $lg['g']; $ss = $lg['s']; $s2 = $sg; sort( $s2 ); $s3 = $ss; sort( $s3 );
	if ( count( $sg ) < 200 || count( $ss ) < 20 || $s2 !== $sg || $s3 !== $ss || min( $sg ) < 0 || max( $sg ) > 1 || min( $ss ) < 0 || max( $ss ) > 1 ) { $fail( 'the league spreads: ' . count( $sg ) . ' games, ' . count( $ss ) . ' seasons' ); }
	if ( DBD_Throws::spread( array( 5, 5 ) ) !== round( log( 2 ) / log( 12 ), 4 ) || DBD_Throws::spread( array( 9 ) ) !== 0.0 || DBD_Throws::spread( array_fill( 0, 12, 3 ) ) !== 1.0 ) { $fail( 'the spread measure' ); }
	if ( DBD_Throws::band( -1 ) !== 0 || DBD_Throws::band( 0 ) !== 1 || DBD_Throws::band( 10 ) !== 2 || DBD_Throws::band( 20 ) !== 3 ) { $fail( 'the depth bands' ); }
	if ( $d['sets'][0]['rows'][3][12] !== -7 ) { $fail( 'the Vikings were down 7 on their fourth throw, not ' . var_export( $d['sets'][0]['rows'][3][12], true ) ); }
	$pj = json_decode( preg_replace( '#^.*<script type="application/json" class="dbd-thr-d">(.*)</script>.*$#s', '$1', DBD_Throws::qb_panel( $rec + array( 'team' => 'MIN' ) ) ), true );
	if ( empty( $pj['lg']['g'] ) || ( $pj['lg']['min'] ?? null ) !== array( 20, 150 ) ) { $fail( 'the panel should carry the league spreads' ); }
	if ( $out = getenv( 'DBD_HARNESS_HTML' ) ) { file_put_contents( $out . '/throws-game.html', $h ); file_put_contents( $out . '/throws-qb.html', DBD_Throws::qb_panel( $rec + array( 'team' => 'MIN' ) ) ); }
	if ( $warnings ) { $fail( "$warnings warnings" ); }
	echo 'throws: the game (' . count( $d['sets'][0]['rows'] ) . ' and ' . count( $d['sets'][1]['rows'] ) . ' throws), Cousins (' . count( $q['sets'] ) . " games, $n throws), field use (" . count( $sg ) . ' games, ' . count( $ss ) . " seasons) and the panels all pass\n"; exit( 0 );
}
/* ---- the playoffs (SCOPE §88): the switch, every stage replayed from the 2025 archive, each game's preview, and the weekly pack stopping at Week 18 */
if ( $view === 'playoffs' ) {
	$fail = function ( $m ) { fwrite( STDERR, "playoffs: $m\n" ); exit( 1 ); };
	$out = getenv( 'DBD_HARNESS_HTML' ); $GLOBALS['dbd_opt'] = array();
	if ( ! function_exists( 'is_admin' ) ) { eval( 'function is_admin() { return false; }' ); }
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	$save = function ( $name, $body ) use ( $out ) {
		if ( ! $out ) { return; } if ( ! is_dir( $out . '/assets' ) ) { mkdir( $out . '/assets', 0777, true ); } foreach ( array( 'dbd.css', 'blocks.css', 'playoffs.css', 'playoffs.js' ) as $f ) { copy( DBD_PATH . 'assets/' . $f, $out . '/assets/' . $f ); }
		file_put_contents( $out . '/' . $name . '.html', '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/dbd.css"><link rel="stylesheet" href="assets/blocks.css"><link rel="stylesheet" href="assets/playoffs.css"><style>body{margin:0;background:#f4f1ea}.wrap{max-width:1320px;margin:0 auto;padding:0 24px}</style></head><body>' . $body . '<script src="assets/playoffs.js"></script></body></html>' );
	};
	/* the switch, on this season's real seeds */
	if ( DBD_Playoffs::ready() ) {
		if ( DBD_Router::route_path( 'playoffs/' )['view'] !== 'notfound' ) { $fail( '/playoffs/ should be hidden while the switch is off' ); }
		$GLOBALS['dbd_opt'][ DBD_Playoffs::OPT ] = 1;
		if ( DBD_Router::route_path( 'playoffs/' )['view'] !== 'playoffs' ) { $fail( '/playoffs/ should open with the switch on' ); }
		if ( DBD_Router::route_path( 'playoffs/nope/' )['view'] !== 'notfound' ) { $fail( 'an unknown game should be not found' ); }
		$h0 = DBD_Playoffs::hero(); if ( preg_match_all( '/class="po-g[ "]/', $h0 ) !== 15 || substr_count( $h0, 'data-t=' ) < 14 ) { $fail( 'today’s bracket hero should draw 15 cards and the 14 teams; got ' . preg_match_all( '/class="po-g[ "]/', $h0 ) . ' cards, ' . substr_count( $h0, 'data-t=' ) . ' teams, ' . strlen( $h0 ) . ' bytes' ); }
		$save( 'po-today', DBD_Playoffs::hero() );
		unset( $GLOBALS['dbd_opt'][ DBD_Playoffs::OPT ] );
	}
	/* every stage, replayed from the 2025 archive */
	if ( ! DBD_Data::use_season( 2025 ) ) { $fail( 'no 2025 archive' ); }
	DBD_Playoffs::$seeds = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/playoffs-2025.json' ), true )['seeds'];
	/* the bracket hero (SCOPE §92): stage => solid chalk lines (a winner carried on), gold ones (the champion's), cards still to be set, the title */
	$want = array( 0 => array( 2, 0, 5, 'The road to Super Bowl LX' ), 1 => array( 2, 0, 5, 'Wild Card weekend' ), 2 => array( 8, 0, 3, 'The Divisional round' ), 3 => array( 12, 0, 1, 'The conference championships' ), 4 => array( 14, 0, 0, 'Super Bowl LX is set' ), 5 => array( 11, 3, 0, 'The Seahawks win Super Bowl LX' ) );
	foreach ( $want as $st => $w ) {
		DBD_Playoffs::$replay = $st; DBD_Playoffs::reset();
		if ( DBD_Playoffs::stage() !== $st ) { $fail( "replay $st reads as stage " . DBD_Playoffs::stage() ); }
		$h = DBD_Playoffs::hero(); $pg = DBD_Playoffs::page();
		if ( preg_match_all( '/class="po-g[ "]/', $h ) !== 15 ) { $fail( "stage $st draws " . preg_match_all( '/class="po-g[ "]/', $h ) . ' cards' ); }
		if ( ! preg_match( '/data-ln="([^"]*)"/', $h, $lm ) || ! is_array( $L = json_decode( html_entity_decode( $lm[1], ENT_QUOTES ), true ) ) || count( $L ) !== 14 ) { $fail( "stage $st’s chalk" ); }
		$cl = array_count_values( array_column( $L, 4 ) ); $tb = substr_count( $h, ' tbd' );
		if ( ( $cl['w'] ?? 0 ) !== $w[0] || ( $cl['g'] ?? 0 ) !== $w[1] || $tb !== $w[2] ) { $fail( "stage $st: " . ( $cl['w'] ?? 0 ) . ' winner lines, ' . ( $cl['g'] ?? 0 ) . " gold, $tb to be set" ); }
		if ( strpos( $h, '<h1>' . $w[3] . '</h1>' ) === false ) { $fail( "stage $st is missing $w[3]" ); }
		foreach ( $L as $l ) { foreach ( array( 0, 2 ) as $e ) { if ( strpos( $h, 'id="pob-' . $l[ $e ] . '"' ) === false ) { $fail( "stage $st: a line to a card that isn’t there, {$l[ $e ]}" ); } } }
		/* 2.50.1: the 1 seeds wait in their Divisional cards, and every team links to its own page */
		foreach ( array( 'AFC' => 'DEN', 'NFC' => 'SEA' ) as $cf => $one ) { if ( ! preg_match( '#id="pob-' . $cf . '-dv\d"[^>]*>(?:(?!id="pob-).)*data-t="' . $one . '"#s', $h ) ) { $fail( "stage $st: the $cf 1 seed should be in a Divisional card" ); } }
		preg_match_all( '#<a class="po-r[^"]*" data-t="([A-Z]{2,3})" href="([^"]*)"#', $h, $am, PREG_SET_ORDER );
		if ( count( $am ) < 16 ) { $fail( "stage $st: only " . count( $am ) . ' team rows' ); }
		foreach ( $am as $a ) { if ( $a[2] !== DBD_Router::url( array( 'team' => $a[1] ) ) ) { $fail( "stage $st: {$a[1]} links to {$a[2]}, not its team page" ); } }
		if ( preg_match( '/Week (19|2\d)/', $h . $pg ) ) { $fail( "stage $st calls a playoff round a week" ); }
		$save( 'po-hero-' . $st, $h . '<div class="wrap">' . $pg . '</div>' );
	}
	$games = DBD_Playoffs::games(); if ( count( $games ) !== 13 ) { $fail( count( $games ) . ' playoff games' ); }
	foreach ( $games as $g ) {
		DBD_Playoffs::$replay = 5; DBD_Playoffs::reset(); $sl = DBD_Playoffs::slug( $g ); if ( DBD_Playoffs::id_of( $sl ) !== $g['id'] ) { $fail( "slug $sl" ); }
		foreach ( array( 5, DBD_Playoffs::IDX[ $g['r'] ] ) as $st ) {
			DBD_Playoffs::$replay = $st; DBD_Playoffs::reset(); $x = DBD_Playoffs::game_page( $g['id'] );
			foreach ( array( 'can win it', 'going in' ) as $i => $need ) { if ( ( $st === 5 ) === (bool) $i && strpos( $x, $need ) === false ) { $fail( $g['id'] . " at stage $st is missing “{$need}”" ); } }
			if ( strpos( $x, ' road</h3>' ) === false || strpos( $x, 'series before' ) === false ) { $fail( $g['id'] . ' is missing a panel' ); }
		}
	}
	DBD_Playoffs::$replay = 5; DBD_Playoffs::reset(); $sb = DBD_Playoffs::super_bowl(); $save( 'po-game-sb', '<div class="wrap">' . DBD_Playoffs::game_page( $sb['id'] ) . '</div>' );
	DBD_Playoffs::$replay = 3; DBD_Playoffs::reset(); $save( 'po-game-con', '<div class="wrap">' . DBD_Playoffs::game_page( DBD_Playoffs::games()[10]['id'] ) . '</div>' );
	/* the playoff posts (SCOPE §89): which exist at each stage, when they go, their texts, the switch, and every graphic at 1080 × 1350 in both looks */
	$GLOBALS['dbd_opt'][ DBD_Playoffs::OPT ] = 1; DBD_Tall::$off = false; DBD_Pack::$now = strtotime( '2026-01-01 12:00:00 UTC' ); $tz = new DateTimeZone( 'America/New_York' );
	$at = array( '19-pset' => 'Mon Jan 5', '19-pprev' => 'Thu Jan 8', '19-pres' => 'Tue Jan 13', '20-pprev' => 'Thu Jan 15', '20-pres' => 'Mon Jan 19', '21-pprev' => 'Thu Jan 22', '21-pres' => 'Mon Jan 26',
		'22-proad' => 'Wed Jan 28', '22-plead' => 'Wed Feb 4', '22-pprev' => 'Fri Feb 6', '22-pres' => 'Mon Feb 9' );
	$has = array( 0 => array( '19-pset' ), 1 => array( '19-pset', '19-pprev' ), 2 => array( '19-pset', '19-pres', '20-pprev' ), 3 => array( '19-pset', '19-pres', '20-pres', '21-pprev' ),
		4 => array( '19-pset', '19-pres', '20-pres', '21-pres', '22-proad', '22-plead', '22-pprev' ), 5 => array( '19-pset', '19-pres', '20-pres', '21-pres', '22-proad', '22-plead', '22-pres' ) );
	$noop = function () { return null; }; $drawn = 0;
	foreach ( $has as $st => $want_po ) {
		DBD_Playoffs::$replay = $st; DBD_Playoffs::reset(); $got = array();
		foreach ( DBD_PoPosts::PLAN as $n => $sls ) { foreach ( $sls as $sl ) {
			$it = DBD_PoPosts::item( $n, $sl ); if ( ! $it ) { if ( DBD_PoPosts::waiting( $n, $sl ) === '' ) { $fail( "$n-$sl has no item and nothing it waits for" ); } continue; } $got[] = $n . '-' . $sl;
			if ( DBD_Pack::xlen( $it['post'] ) > DBD_Pack::X_MAX || $it['tl'] === '' || strpos( $it['link'], 'playoffs' ) === false || $it['sq'] !== DBD_Tall::key( $n, $sl ) ) { $fail( "$n-$sl’s text or link at stage $st" ); }
			if ( DBD_Pack::find( $n, $sl ) === null ) { $fail( "the pack can’t find $n-$sl" ); }
			if ( $st === 5 ) { $w = wp_date( 'D M j G:i', DBD_PoPosts::time( $n, $sl ), $tz ); if ( $w !== $at[ $n . '-' . $sl ] . ' 12:00' ) { $fail( "$n-$sl goes $w, not {$at[ $n . '-' . $sl ]} at noon" ); } }
			foreach ( array( 'black', 'paper' ) as $lk ) {
				$key = DBD_Tall::key( $n, $sl, $lk ); if ( ! DBD_Tall::parse( $key ) || ! DBD_Card::valid( $key ) ) { $fail( "$key isn’t a graphic key" ); }
				$im = DBD_Tall::render( $key, $noop ); if ( ! $im || imagesx( $im ) !== 1080 || imagesy( $im ) !== 1350 ) { $fail( "$key didn’t draw at 1080 × 1350" ); } $drawn++;
				if ( $out ) { imagejpeg( $im, $out . '/po-post-' . $st . '-' . $n . '-' . $sl . '-' . $lk . '.jpg', 88 ); } imagedestroy( $im );
			}
		} }
		if ( $got !== $want_po ) { $fail( "stage $st has " . implode( ', ', $got ) . ', not ' . implode( ', ', $want_po ) ); }
	}
	if ( DBD_PoPosts::item( 22, 'pres' )['tl'] !== 'The Seahawks win Super Bowl LX, 29-13 over the Patriots.' ) { $fail( 'the champions post reads ' . DBD_PoPosts::item( 22, 'pres' )['tl'] ); }
	if ( DBD_PoPosts::name( 21, 'pres' ) !== 'Super Bowl LX is set' ) { $fail( 'the Super Bowl set post’s name' ); }
	DBD_Playoffs::$replay = 2; DBD_Playoffs::reset();
	if ( array_keys( DBD_Instagram::plan( 19 ) ) !== array( 'pset', 'pres' ) || DBD_Instagram::book_weeks() !== array( 18, 19, 20 ) ) { $fail( 'with the switch on, Weeks 19 and 20 should book after Week 18' ); }
	if ( DBD_Instagram::slot_time( 19, 'pres' ) !== DBD_PoPosts::time( 19, 'pres' ) ) { $fail( 'the networks should use the playoff times' ); }
	unset( $GLOBALS['dbd_opt'][ DBD_Playoffs::OPT ] );
	if ( DBD_Instagram::plan( 19 ) || DBD_Instagram::book_weeks() !== array( 18 ) || DBD_AutoPub::ready( 19, 'pset' ) ) { $fail( 'with the switch off, no playoff post should book or offer Auto publish' ); }
	if ( DBD_Tall::parse( 'tall-week-20-black-pset' ) !== null || DBD_Tall::parse( 'tall-week-19-black-proad' ) !== null || DBD_Instagram::parse( '2025-w22-plead' ) === null ) { $fail( 'a playoff post’s key in the wrong week' ); }
	DBD_Pack::$now = null; DBD_Tall::$off = true;
	/* the weekly pack and its boards stop at Week 18 */
	if ( max( DBD_Pack::weeks() ) > 18 ) { $fail( 'the pack holds a playoff week' ); }
	if ( DBD_Tall::parse( 'tall-week-19-black-board' ) !== null || DBD_Tall::parse( 'tall-week-18-black-board' ) === null ) { $fail( 'the boards should stop at Week 18' ); }
	if ( $warnings ) { $fail( "$warnings warnings" ); }
	echo "playoffs: every stage, " . count( $games ) . " previews, " . $drawn . " playoff post graphics, the switch and the Week 18 stop all pass\n"; exit( 0 );
}

/* ---- route the request the way the page would */
$_GET = array();
if ( $view === 'path' ) {  /* a readable path, routed the way a request for it would be */
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	DBD_Router::$route = DBD_Router::route_path( $arg ); $routed = true;
}
if ( $view === 'player' ) { $_GET['player'] = $arg; } elseif ( $view === 'players' ) { $_GET['view'] = 'players'; } elseif ( $view === 'team' ) { $_GET['team'] = $arg; } elseif ( $view === 'game' ) { $_GET['game'] = $arg; } elseif ( $view === 'teamplayers' ) { $_GET['view'] = 'players'; $_GET['team'] = $arg; } elseif ( $view === 'matchups' ) { $_GET['view'] = 'matchups'; if ( $arg ) { $_GET['week'] = $arg; } } elseif ( $view === 'teams' ) { $_GET['view'] = 'teams'; } elseif ( $view === 'compare' ) { $_GET['compare'] = $arg; } elseif ( $view === 'defense' ) { $_GET['team'] = $arg; $_GET['side'] = 'def'; } elseif ( $view === 'leaders' ) { $_GET['view'] = 'leaders'; if ( $arg && strpos( $arg, '.' ) !== false ) { $_GET['b'] = $arg; } elseif ( $arg ) { $_GET['g'] = $arg; } } elseif ( $view === 'myteam' ) { if ( $arg ) { $_GET['myteam'] = $arg; } else { $_GET['view'] = 'myteam'; } } elseif ( $view === 'week' ) { $_GET['view'] = 'week'; if ( $arg ) { $_GET['week'] = $arg; } } elseif ( $view === 'fantasy' ) { $_GET['view'] = 'fantasy'; if ( $arg ) { $_GET['week'] = $arg; } }  /* SCOPE §78 */ elseif ( $view === 'draft' || $view === 'combine' ) { $_GET['view'] = $view; if ( $arg ) { $_GET['y'] = $arg; } }  /* the season boards (SCOPE §63) */
if ( empty( $routed ) ) {
$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/app/' );
DBD_Router::$route = DBD_Router::route();
} else { $view = DBD_Router::$route['view']; $arg = str_replace( '/', '_', trim( (string) $arg, '/' ) ); }
$file = method_exists( 'DBD_Router', 'view_file' ) ? DBD_Router::view_file( DBD_Router::$route ) : DBD_PATH . 'includes/views/' . DBD_Router::$route['view'] . '.php';  /* the router's choice: a kicker's page for a K (SCOPE §47) */
if ( ! is_readable( $file ) ) { fwrite( STDERR, "no view file for route '" . DBD_Router::$route['view'] . "'\n" ); exit( 2 ); }
ob_start();
try { include $file; $html = ob_get_clean(); }
catch ( Throwable $e ) { $partial = ob_get_clean(); fwrite( STDERR, "FATAL in $view: " . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n  output before the error: " . strlen( $partial ) . " bytes, last tag: " . ( preg_match_all( '/<[a-z0-9-]+/i', $partial, $mm ) ? end( $mm[0] ) : '-' ) . "\n" ); exit( 1 ); }
/* the page's head tags (title, description, share card, structured data; SCOPE §64), so CI can check them in the written page */
ob_start(); try { echo '<title>' . esc_html( DBD_Router::title_string( '' ) ) . '</title>'; DBD_Router::head(); $headHtml = ob_get_clean(); } catch ( Throwable $e ) { ob_end_clean(); fwrite( STDERR, 'FATAL in head: ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
echo "rendered $view " . ( $arg ?: '' ) . ": " . strlen( $html ) . " bytes, route " . DBD_Router::$route['view'] . ", $warnings warning" . ( $warnings === 1 ? '' : 's' ) . "\n";
$out = getenv( 'DBD_HARNESS_HTML' );  /* a directory: write a standalone page that loads the plugin's stylesheet, with Divi's inline table rule on, for looking at */
if ( $out ) { if ( ! is_dir( $out . '/assets' ) ) { mkdir( $out . '/assets', 0777, true ); } foreach ( array( 'dbd.css', 'transitions.css', 'marks.js' ) as $af ) { copy( DBD_PATH . 'assets/' . $af, $out . '/assets/' . $af ); } $name = $out . '/' . $view . ( $arg ? '-' . $arg : '' ) . '.html';
	$fonts = '<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">';
	file_put_contents( $name, '<!doctype html><html class="no-js"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $fonts . '<style>.entry-content tr td{padding:6px 24px 24px}</style>' . $headHtml . '<link rel="stylesheet" href="assets/dbd.css"><link rel="stylesheet" href="assets/transitions.css"></head><body class="dbd-app"><div class="entry-content"><div class="dbd dbd-view-' . DBD_Router::$route['view'] . '">' . $html . '</div></div></body></html>' ); }
/* the team and hub views are shells that the explorer script fills; the player and players views carry their tables */
$floor = in_array( $view, array( 'player', 'players' ), true ) ? 20000 : 800;
if ( strlen( $html ) < $floor ) { fwrite( STDERR, "suspiciously short output (under $floor bytes)\n" ); exit( 3 ); }
