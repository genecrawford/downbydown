<?php
/**
 * Render one plugin view outside WordPress, from the repo's data files, with a thin shim for the handful of
 * WordPress functions the plugin calls. Catches the fatal that a live page would hide behind "critical error".
 *   php tools/view_harness.php <data dir> <view> [arg]
 *   views: player <gsis id> | players | team <ABBR> | hub | game <game id>
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
function get_option( $k, $d = false ) { if ( $k === 'dbd_headshots' || $k === 'dbd_logos' ) { return (bool) getenv( 'DBD_HARNESS_MEDIA' ); } return $d; }
function update_option( $k, $v ) { return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { return true; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function get_permalink( $p = null ) { return 'https://example.test/app/'; }
function add_query_arg( $args, $url = '' ) { if ( ! is_array( $args ) ) { return $url; } $q = http_build_query( $args ); return $url . ( $q ? ( strpos( $url, '?' ) === false ? '?' : '&' ) . $q : '' ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function wp_date( $f, $ts = null ) { return date( $f, $ts ?? time() ); }
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
foreach ( array( 'class-dbd-data', 'class-dbd-marks', 'class-dbd-grades', 'class-dbd-metrics', 'class-dbd-takeaways', 'class-dbd-sync', 'class-dbd-card', 'class-dbd-router', 'class-dbd-seo' ) as $c ) { $f = DBD_PATH . 'includes/' . $c . '.php'; if ( is_readable( $f ) ) { require $f; } }

/* ---- route the request the way the page would */
$_GET = array();
if ( $view === 'player' ) { $_GET['player'] = $arg; } elseif ( $view === 'players' ) { $_GET['view'] = 'players'; } elseif ( $view === 'team' ) { $_GET['team'] = $arg; } elseif ( $view === 'game' ) { $_GET['game'] = $arg; } elseif ( $view === 'matchups' ) { $_GET['view'] = 'matchups'; } elseif ( $view === 'teams' ) { $_GET['view'] = 'teams'; }
$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/app/' );
DBD_Router::$route = DBD_Router::route();
$file = DBD_PATH . 'includes/views/' . DBD_Router::$route['view'] . '.php';
if ( ! is_readable( $file ) ) { fwrite( STDERR, "no view file for route '" . DBD_Router::$route['view'] . "'\n" ); exit( 2 ); }
ob_start();
try { include $file; $html = ob_get_clean(); }
catch ( Throwable $e ) { $partial = ob_get_clean(); fwrite( STDERR, "FATAL in $view: " . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n  output before the error: " . strlen( $partial ) . " bytes, last tag: " . ( preg_match_all( '/<[a-z0-9-]+/i', $partial, $mm ) ? end( $mm[0] ) : '-' ) . "\n" ); exit( 1 ); }
echo "rendered $view " . ( $arg ?: '' ) . ": " . strlen( $html ) . " bytes, route " . DBD_Router::$route['view'] . ", $warnings warning" . ( $warnings === 1 ? '' : 's' ) . "\n";
$out = getenv( 'DBD_HARNESS_HTML' );  /* a directory: write a standalone page that loads the plugin's stylesheet, with Divi's inline table rule on, for looking at */
if ( $out ) { @mkdir( $out, 0777, true ); $name = $out . '/' . $view . ( $arg ? '-' . $arg : '' ) . '.html';
	$fonts = '<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">';
	file_put_contents( $name, '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $fonts . '<style>.entry-content tr td{padding:6px 24px 24px}</style><link rel="stylesheet" href="assets/dbd.css"><link rel="stylesheet" href="assets/transitions.css"></head><body class="dbd-app"><div class="entry-content"><div class="dbd dbd-view-' . DBD_Router::$route['view'] . '">' . $html . '</div></div></body></html>' ); }
/* the team and hub views are shells that the explorer script fills; the player and players views carry their tables */
$floor = in_array( $view, array( 'player', 'players' ), true ) ? 20000 : 800;
if ( strlen( $html ) < $floor ) { fwrite( STDERR, "suspiciously short output (under $floor bytes)\n" ); exit( 3 ); }
