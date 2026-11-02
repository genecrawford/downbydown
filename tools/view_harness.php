<?php
/**
 * Render one plugin view outside WordPress, from the repo's data files, with a thin shim for the handful of
 * WordPress functions the plugin calls. Catches the fatal that a live page would hide behind "critical error".
 *   php tools/view_harness.php <data dir> <view> [arg]
 *   views: player <gsis id> | players | team <ABBR> | hub | game <game id> | teamplayers <ABBR> | matchups | teams | compare <id,id> | myteam [id,id,…] | leaders [g or board key] | defense <ABBR> | week [N]
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
function wp_upload_dir() { global $tmp; return array( 'basedir' => $tmp, 'baseurl' => 'https://example.test/uploads' ); }
function get_option( $k, $d = false ) { if ( $k === 'dbd_headshots' || $k === 'dbd_logos' ) { return (bool) getenv( 'DBD_HARNESS_MEDIA' ); }
	if ( getenv( 'DBD_HARNESS_PRETTY' ) ) { if ( $k === 'permalink_structure' ) { return '/%postname%/'; } if ( $k === 'dbd_app_page' || $k === 'page_on_front' ) { return 10; } if ( $k === 'show_on_front' ) { return 'page'; } }  /* readable URLs (SCOPE §43) */
	return $d; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function get_query_var( $k ) { return ''; }
function add_rewrite_rule() {} function flush_rewrite_rules() {}
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); }
function update_option( $k, $v ) { return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_permalink( $p = null ) { return 'https://example.test/app/'; }
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
function wp_remote_get() { return null; } function wp_remote_retrieve_response_code() { return 0; } function wp_remote_retrieve_body() { return ''; }
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }
function __( $s ) { return $s; } function _e( $s ) { echo $s; }
function wp_schedule_single_event() {} function wp_next_scheduled() { return false; } function wp_schedule_event() {} function register_activation_hook() {} function register_deactivation_hook() {}

/* ---- the plugin, minus the bootstrap and admin. The SEO class extends a WordPress sitemap base class; an empty stand-in lets it load. */
if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) { class WP_Sitemaps_Provider { public $name = ''; public $object_type = ''; } }
foreach ( array( 'class-dbd-data', 'class-dbd-marks', 'class-dbd-grades', 'class-dbd-metrics', 'class-dbd-takeaways', 'class-dbd-lineup', 'class-dbd-fantasy', 'class-dbd-myteam', 'class-dbd-leaders', 'class-dbd-defense', 'class-dbd-week', 'class-dbd-hub', 'class-dbd-compare', 'class-dbd-season', 'class-dbd-sync', 'class-dbd-card', 'class-dbd-urls', 'class-dbd-router', 'class-dbd-seo' ) as $c ) { $f = DBD_PATH . 'includes/' . $c . '.php'; if ( is_readable( $f ) ) { require $f; } }

/* ---- readable URLs (SCOPE §43): every old link to a path and back to the same route */
if ( $view === 'urls' ) {
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	if ( ! DBD_Urls::on() ) { fwrite( STDERR, "readable URLs are off: set DBD_HARNESS_PRETTY=1\n" ); exit( 2 ); }
	$cases = array( array(), array( 'view' => 'players' ), array( 'view' => 'players', 'pos' => 'WR', 'pt' => 'MIN' ), array( 'view' => 'teams' ), array( 'view' => 'myteam' ), array( 'myteam' => '00-0036322,00-0036900' ), array( 'compare' => 'none' ),
		array( 'view' => 'leaders' ), array( 'view' => 'leaders', 'b' => 'wr.tsh' ), array( 'view' => 'matchups' ), array( 'view' => 'week' ), array( 'compare' => '00-0036322,00-0036900,00-0039075' ) );
	foreach ( array_keys( DBD_Leaders::GROUPS ) as $g ) { $cases[] = array( 'view' => 'leaders', 'g' => $g ); }
	foreach ( DBD_Data::weeks() as $w ) { $cases[] = array( 'view' => 'matchups', 'week' => $w ); }
	foreach ( DBD_Week::weeks() as $w ) { $cases[] = array( 'view' => 'week', 'week' => $w ); }
	foreach ( array_keys( DBD_Data::teams() ) as $t ) { $cases[] = array( 'team' => $t ); $cases[] = array( 'team' => $t, 'side' => 'def' ); $cases[] = array( 'view' => 'players', 'team' => $t ); }
	foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( $gl as $g ) { $cases[] = array( 'game' => $g['id'] ); $cases[] = array( 'game' => $g['id'], 'side' => 'home' ); } }
	foreach ( DBD_Data::player_index() as $p ) { $cases[] = array( 'player' => $p['id'] ); }
	$key = function ( $r ) { return json_encode( array( $r['view'], $r['team'] ?? '', $r['game']['id'] ?? '', $r['side'] ?? '', $r['tside'] ?? '', $r['week'] ?? '', $r['player']['id'] ?? '', array_map( function ( $p ) { return $p['id']; }, $r['compare'] ?? array() ), $r['lgroup'] ?? '', $r['lboard'] ?? '', $r['pteam'] ?? '', $r['pos'] ?? '', array_map( function ( $p ) { return $p['id']; }, $r['myteam'] ?? array() ) ) ); };
	$bad = 0; $paths = 0; $seen = array();
	foreach ( $cases as $args ) {
		$_GET = $args; $want = $key( DBD_Router::route() );
		$u = DBD_Router::url( $args ); $pq = parse_url( $u ); $path = substr( $pq['path'] ?? '/', 1 );
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
	foreach ( array( 'team/zzz/', 'game/1999-week-1-a-at-b/', 'player/nobody-at-all/', 'week/x/', 'teams/extra/', '1999/team/min/' ) as $odd ) { $_GET = array(); $r = DBD_Router::route_path( $odd ); echo "  $odd -> " . $r['view'] . ( $r['view'] === 'players' ? ' (search "' . $r['q'] . '")' : '' ) . "\n"; }
	echo "urls: " . count( $cases ) . " links, $paths paths, $bad problems, $warnings warnings\n";
	foreach ( array( array( 'player' => '00-0036322' ), array( 'game' => '2026_08_MIN_DET', 'side' => 'home' ), array( 'team' => 'MIN', 'side' => 'def' ), array( 'view' => 'players', 'team' => 'MIN' ), array( 'view' => 'leaders', 'g' => 'rb' ), array( 'compare' => '00-0036322,00-0036900' ), array( 'view' => 'players', 'pt' => 'MIN', 'q' => 'jeff' ) ) as $ex ) { echo '  ' . json_encode( $ex ) . ' -> ' . DBD_Router::url( $ex ) . "\n"; }
	exit( $bad || $warnings ? 1 : 0 );
}

/* ---- an archived season (SCOPE §44): DBD_HARNESS_SEASON=2025 shows that season for the card and view modes */
if ( getenv( 'DBD_HARNESS_SEASON' ) ) { if ( ! DBD_Data::use_season( (int) getenv( 'DBD_HARNESS_SEASON' ) ) ) { fwrite( STDERR, 'no archived season ' . getenv( 'DBD_HARNESS_SEASON' ) . "\n" ); exit( 2 ); } }

/* ---- a share card (SCOPE §26, §43): php tools/view_harness.php data card <key> <out.png>. Logos and photos only with DBD_HARNESS_LOGOS. */
if ( $view === 'card' ) {
	$out = $argv[4] ?? 'card.png';
	if ( ! DBD_Card::available() ) { fwrite( STDERR, "GD with FreeType is not available\n" ); exit( 3 ); }
	$fetch = getenv( 'DBD_HARNESS_LOGOS' ) ? function ( $u ) { $b = @file_get_contents( $u, false, stream_context_create( array( 'http' => array( 'timeout' => 10 ) ) ) ); return $b ?: null; } : null;
	$t0 = microtime( true ); $im = DBD_Card::render( $arg, $fetch );
	if ( ! $im ) { fwrite( STDERR, "render( $arg ) returned nothing\n" ); exit( 4 ); }
	imagepng( $im, $out, 6 ); imagedestroy( $im ); $sz = getimagesize( $out );
	echo "card $arg: {$sz[0]}x{$sz[1]}, " . round( microtime( true ) - $t0, 2 ) . "s, $warnings warnings -> $out\n";
	exit( $sz[0] === 1200 && $sz[1] === 630 && ! $warnings ? 0 : 5 );
}

/* ---- an old link's redirect (SCOPE §43): php tools/view_harness.php data redirect '/?player=…&utm_source=x' prints "redirect 301 <to>" or "stays" */
if ( $view === 'redirect' ) {
	function wp_safe_redirect( $to, $status = 302 ) { echo "redirect $status $to\n"; return true; }
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	$u = parse_url( $arg ); parse_str( $u['query'] ?? '', $_GET ); $_SERVER['REQUEST_URI'] = $arg; $_SERVER['REQUEST_METHOD'] = 'GET';
	DBD_Router::$route = DBD_Router::route(); DBD_Router::redirect_old();
	echo "stays ($warnings warnings)\n"; exit( $warnings ? 1 : 0 );
}

/* ---- route the request the way the page would */
$_GET = array();
if ( $view === 'path' ) {  /* a readable path, routed the way a request for it would be */
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	DBD_Router::$route = DBD_Router::route_path( $arg ); $routed = true;
}
if ( $view === 'player' ) { $_GET['player'] = $arg; } elseif ( $view === 'players' ) { $_GET['view'] = 'players'; } elseif ( $view === 'team' ) { $_GET['team'] = $arg; } elseif ( $view === 'game' ) { $_GET['game'] = $arg; } elseif ( $view === 'teamplayers' ) { $_GET['view'] = 'players'; $_GET['team'] = $arg; } elseif ( $view === 'matchups' ) { $_GET['view'] = 'matchups'; } elseif ( $view === 'teams' ) { $_GET['view'] = 'teams'; } elseif ( $view === 'compare' ) { $_GET['compare'] = $arg; } elseif ( $view === 'defense' ) { $_GET['team'] = $arg; $_GET['side'] = 'def'; } elseif ( $view === 'leaders' ) { $_GET['view'] = 'leaders'; if ( $arg && strpos( $arg, '.' ) !== false ) { $_GET['b'] = $arg; } elseif ( $arg ) { $_GET['g'] = $arg; } } elseif ( $view === 'myteam' ) { if ( $arg ) { $_GET['myteam'] = $arg; } else { $_GET['view'] = 'myteam'; } } elseif ( $view === 'week' ) { $_GET['view'] = 'week'; if ( $arg ) { $_GET['week'] = $arg; } }
if ( empty( $routed ) ) {
$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/app/' );
DBD_Router::$route = DBD_Router::route();
} else { $view = DBD_Router::$route['view']; $arg = str_replace( '/', '_', trim( (string) $arg, '/' ) ); }
$file = DBD_PATH . 'includes/views/' . DBD_Router::$route['view'] . '.php';
if ( ! is_readable( $file ) ) { fwrite( STDERR, "no view file for route '" . DBD_Router::$route['view'] . "'\n" ); exit( 2 ); }
ob_start();
try { include $file; $html = ob_get_clean(); }
catch ( Throwable $e ) { $partial = ob_get_clean(); fwrite( STDERR, "FATAL in $view: " . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n  output before the error: " . strlen( $partial ) . " bytes, last tag: " . ( preg_match_all( '/<[a-z0-9-]+/i', $partial, $mm ) ? end( $mm[0] ) : '-' ) . "\n" ); exit( 1 ); }
echo "rendered $view " . ( $arg ?: '' ) . ": " . strlen( $html ) . " bytes, route " . DBD_Router::$route['view'] . ", $warnings warning" . ( $warnings === 1 ? '' : 's' ) . "\n";
$out = getenv( 'DBD_HARNESS_HTML' );  /* a directory: write a standalone page that loads the plugin's stylesheet, with Divi's inline table rule on, for looking at */
if ( $out ) { if ( ! is_dir( $out . '/assets' ) ) { mkdir( $out . '/assets', 0777, true ); } foreach ( array( 'dbd.css', 'transitions.css', 'marks.js' ) as $af ) { copy( DBD_PATH . 'assets/' . $af, $out . '/assets/' . $af ); } $name = $out . '/' . $view . ( $arg ? '-' . $arg : '' ) . '.html';
	$fonts = '<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">';
	file_put_contents( $name, '<!doctype html><html class="no-js"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $fonts . '<style>.entry-content tr td{padding:6px 24px 24px}</style><link rel="stylesheet" href="assets/dbd.css"><link rel="stylesheet" href="assets/transitions.css"></head><body class="dbd-app"><div class="entry-content"><div class="dbd dbd-view-' . DBD_Router::$route['view'] . '">' . $html . '</div></div></body></html>' ); }
/* the team and hub views are shells that the explorer script fills; the player and players views carry their tables */
$floor = in_array( $view, array( 'player', 'players' ), true ) ? 20000 : 800;
if ( strlen( $html ) < $floor ) { fwrite( STDERR, "suspiciously short output (under $floor bytes)\n" ); exit( 3 ); }
