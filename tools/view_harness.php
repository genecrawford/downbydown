<?php
/**
 * Render one plugin view outside WordPress, from the repo's data files, with a thin shim for the handful of
 * WordPress functions the plugin calls. Catches the fatal that a live page would hide behind "critical error".
 *   php tools/view_harness.php <data dir> <view> [arg]
 *   views: player <gsis id> | players | team <ABBR> | hub | game <game id> | teamplayers <ABBR> | matchups [N] | teams | compare <id,id> | myteam [id,id,…] | leaders [g or board key] | defense <ABBR> | week [N]
 *   DBD_HARNESS_ROOT=1: the app page is /hub/, not the front page, with paths at the root
 *   live blocks (class-dbd-blocks.php): block <type> ['k=v&k=v' attributes] | page <html file> (a theme page with its [dbd_block] shortcodes drawn)
 *   the weekly post (class-dbd-post.php, SCOPE §46): post <N> (DBD_HARNESS_AHEAD=0 leaves out next week, as a backfilled post does) | sitemap (every app URL)
 *   the Tuesday email (class-dbd-email.php, SCOPE §48): email <N> builds week N's email from its post, as Brevo would get it, into DBD_HARNESS_HTML
 *   admin <tab|all>: the admin page's tabs (2.11.0, SCOPE §50) drawn with stored settings and two week posts, into DBD_HARNESS_HTML
 *   joined: the "You're in" note's checks (2.10.2), with the post drawn outside the loop the way Divi's Theme Builder draws it
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
function wp_upload_dir() { global $tmp; return array( 'basedir' => $tmp, 'baseurl' => 'https://example.test/uploads' ); }
function get_option( $k, $d = false ) { if ( isset( $GLOBALS['dbd_opt'] ) && array_key_exists( $k, $GLOBALS['dbd_opt'] ) ) { return $GLOBALS['dbd_opt'][ $k ]; }  /* the admin mode's stored settings */
	if ( $k === 'dbd_headshots' || $k === 'dbd_logos' ) { return (bool) getenv( 'DBD_HARNESS_MEDIA' ); }
	if ( getenv( 'DBD_HARNESS_PRETTY' ) ) { if ( $k === 'permalink_structure' ) { return '/%postname%/'; } if ( $k === 'dbd_app_page' ) { return 10; } if ( $k === 'page_on_front' ) { return getenv( 'DBD_HARNESS_ROOT' ) ? 5 : 10; } if ( $k === 'show_on_front' ) { return 'page'; } }  /* readable URLs (SCOPE §43) */
	if ( strpos( $k, 'dbd_news_' ) === 0 && ( $nf = getenv( 'DBD_HARNESS_NEWS' ) ) && is_readable( $nf ) ) { $nj = json_decode( (string) file_get_contents( $nf ), true ); return $nj[ substr( $k, 9 ) ] ?? $d; }  /* team news (SCOPE §49) */
	return $d; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function get_query_var( $k ) { return ''; }
function add_rewrite_rule() {} function flush_rewrite_rules() {}
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); define( 'HOUR_IN_SECONDS', 3600 ); }
function update_option( $k, $v ) { return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { return true; }
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
function wp_remote_get() { return null; } function wp_remote_retrieve_response_code() { return 0; } function wp_remote_retrieve_body() { return ''; }
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function __( $s ) { return $s; } function _e( $s ) { echo $s; }
function wp_schedule_single_event() {} function wp_next_scheduled() { return false; } function wp_schedule_event() {} function register_activation_hook() {} function register_deactivation_hook() {}

/* ---- the plugin, minus the bootstrap and admin. The SEO class extends a WordPress sitemap base class; an empty stand-in lets it load. */
if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) { class WP_Sitemaps_Provider { public $name = ''; public $object_type = ''; } }
foreach ( array( 'class-dbd-data', 'class-dbd-marks', 'class-dbd-grades', 'class-dbd-metrics', 'class-dbd-takeaways', 'class-dbd-lineup', 'class-dbd-fantasy', 'class-dbd-myteam', 'class-dbd-leaders', 'class-dbd-kicker', 'class-dbd-defense', 'class-dbd-week', 'class-dbd-hub', 'class-dbd-compare', 'class-dbd-season', 'class-dbd-sync', 'class-dbd-card', 'class-dbd-urls', 'class-dbd-router', 'class-dbd-seo', 'class-dbd-blocks', 'class-dbd-post', 'class-dbd-news', 'class-dbd-email' ) as $c ) { $f = DBD_PATH . 'includes/' . $c . '.php'; if ( is_readable( $f ) ) { require $f; } }

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
	foreach ( array( 'team/zzz/', 'game/1999-week-1-a-at-b/', 'player/nobody-at-all/', 'week/x/', 'teams/extra/', '1999/team/min/' ) as $odd ) { $_GET = array(); $r = DBD_Router::route_path( $odd ); echo "  $odd -> " . $r['view'] . ( $r['view'] === 'players' ? ' (search "' . $r['q'] . '")' : '' ) . "\n"; }
	echo "urls" . ( getenv( 'DBD_HARNESS_ROOT' ) ? ' (app on /hub/, paths at the root)' : '' ) . ": " . count( $cases ) . " links, $paths paths, $bad problems, $warnings warnings\n";
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
	$ap = (string) ( $u['path'] ?? '/' );
	if ( getenv( 'DBD_HARNESS_ROOT' ) && $ap !== '/' ) {  /* a path request on the app page: /hub/team/min/ forwards to the root */
		$rp->setValue( null, 'https://example.test/hub/' );
		DBD_Router::$route = DBD_Router::route_path( preg_replace( '#^/hub/#', '', $ap ) ); DBD_Router::redirect_prefixed();  /* route_path() applies a /2025/ season itself */
	} elseif ( getenv( 'DBD_HARNESS_ROOT' ) ) { $rp->setValue( null, '' ); DBD_Router::front(); }  /* the front page answers old links */
	else { DBD_Router::$route = DBD_Router::route(); DBD_Router::redirect_old(); }
	echo "stays ($warnings warnings)\n"; exit( $warnings ? 1 : 0 );
}

/* ---- the weekly post: week N composed as the plugin would write it, inside a stand-in for Divi's post column */
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
		$m = DBD_Email::compose( $doc['title'], $doc['html'], 'https://example.test/week-' . (int) $arg . '-in-review/', 'https://example.test/uploads/week-' . (int) $arg . '.png', 'Tuesday, November 3', (int) $arg );
	} catch ( Throwable $e ) { fwrite( STDERR, "FATAL in email $arg: " . get_class( $e ) . ': ' . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
	$late = array( DBD_Email::late( '2026-11-03 13:00:00', strtotime( '2026-11-03 13:00:05 UTC' ) ), DBD_Email::late( '2026-11-03 13:00:00', strtotime( '2026-11-04 12:59:00 UTC' ) ), DBD_Email::late( '2026-11-03 13:00:00', strtotime( '2026-11-04 13:01:00 UTC' ) ), DBD_Email::late( '2025-11-04 13:00:00', strtotime( '2026-11-03 13:00:00 UTC' ) ) );
	echo "email $arg: " . $m['subject'] . ' · ' . strlen( $m['html'] ) . ' bytes · preview "' . $m['pre'] . '" · late at 5s/23h59/24h01/a year: ' . implode( '/', array_map( function ( $x ) { return $x ? 'yes' : 'no'; }, $late ) ) . ", $warnings warnings\n";
	if ( ( $dir = getenv( 'DBD_HARNESS_HTML' ) ) ) { if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); } file_put_contents( $dir . '/email-w' . (int) $arg . '.html', $m['html'] ); file_put_contents( $dir . '/confirm.html', DBD_Email::confirm_html() ); }
	exit( $warnings || $late !== array( false, false, true, true ) ? 1 : 0 );
}
/* ---- the admin page (2.11.0, SCOPE §50): every tab drawn with stored settings, a sync that failed, a feed to check, an email sent and two week posts */
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
		'add_menu_page' => function () {}, 'add_submenu_page' => function () {}, 'register_setting' => function () {},
	) as $fn => $body ) { if ( ! function_exists( $fn ) ) { $GLOBALS['hfn'][ $fn ] = $body; eval( 'function ' . $fn . '( ...$a ) { return $GLOBALS["hfn"]["' . $fn . '"]( ...$a ); }' ); } }
	require DBD_PATH . 'includes/admin/settings.php';
	$want = array( 'ov' => array( 'Needs a look', 'The last data sync failed', 'needs a Brevo API key', 'The last signup didn’t reach Brevo', 'Its send log row has Try again', 'Open Week in review', 'Sync now', 'Fetch news now', 'news feed' ),
		'wk' => array( 'The weekly post', 'Weeks', 'The Tuesday email', 'Send log', 'sent to 198', 'won’t send until Brevo is set up', 'Waiting for the confirmation template', 'action=dbd_email_again&post=103', 'Try again' ),
		'nw' => array( 'Fetching', 'Sources', 'HTTP 503', 'Fetch news now' ),
		'st' => array( 'Photos and logos', 'Addresses', 'Search and sharing', 'Using it in the theme', 'value="dbd"' ),
		'dt' => array( 'Current data', 'Sync log', 'Seasons', 'Share cards', 'value="dbd_data"', 'harness-token' ) );
	$bad = 0; $dir = getenv( 'DBD_HARNESS_HTML' ); if ( $dir && ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }
	foreach ( dbd_admin_tabs() as $k => $t ) {
		if ( $arg && $arg !== 'all' && $arg !== $k ) { continue; }
		$_GET = array( 'page' => $t[0] );
		ob_start(); try { dbd_settings_page(); } catch ( Throwable $e ) { ob_end_clean(); fwrite( STDERR, "FATAL in admin $k: " . $e->getMessage() . ' at ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n" ); exit( 1 ); }
		$h = ob_get_clean(); $miss = array_filter( $want[ $k ], function ( $x ) use ( $h ) { return strpos( $h, $x ) === false; } );
		$forms = substr_count( $h, '<form' ); $nested = preg_match( '#<form(?:(?!</form>).)*<form#s', $h );
		echo "  admin $k (" . $t[1] . '): ' . strlen( $h ) . " bytes, $forms forms" . ( $miss ? ', MISSING: ' . implode( ' | ', $miss ) : '' ) . ( $nested ? ', A FORM INSIDE A FORM' : '' ) . "\n";
		$bad += count( $miss ) + $nested;
		if ( $dir ) { file_put_contents( "$dir/admin-$k.html", '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="https://example.test/wp-admin/css/common.css">' . $h ); }
	}
	echo "admin: $bad problems, $warnings warnings\n"; exit( $bad || $warnings ? 1 : 0 );
}
/* ---- the "You're in" note (2.10.2): on the viewed week post's body only, once, and with no loop (Divi's Post Content module) */
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
	try { foreach ( DBD_Seo::urls() as $u ) { echo $u['loc'] . "\n"; } } catch ( Throwable $e ) { fwrite( STDERR, 'FATAL in sitemap: ' . $e->getMessage() . "\n" ); exit( 1 ); }
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

/* ---- route the request the way the page would */
$_GET = array();
if ( $view === 'path' ) {  /* a readable path, routed the way a request for it would be */
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	DBD_Router::$route = DBD_Router::route_path( $arg ); $routed = true;
}
if ( $view === 'player' ) { $_GET['player'] = $arg; } elseif ( $view === 'players' ) { $_GET['view'] = 'players'; } elseif ( $view === 'team' ) { $_GET['team'] = $arg; } elseif ( $view === 'game' ) { $_GET['game'] = $arg; } elseif ( $view === 'teamplayers' ) { $_GET['view'] = 'players'; $_GET['team'] = $arg; } elseif ( $view === 'matchups' ) { $_GET['view'] = 'matchups'; if ( $arg ) { $_GET['week'] = $arg; } } elseif ( $view === 'teams' ) { $_GET['view'] = 'teams'; } elseif ( $view === 'compare' ) { $_GET['compare'] = $arg; } elseif ( $view === 'defense' ) { $_GET['team'] = $arg; $_GET['side'] = 'def'; } elseif ( $view === 'leaders' ) { $_GET['view'] = 'leaders'; if ( $arg && strpos( $arg, '.' ) !== false ) { $_GET['b'] = $arg; } elseif ( $arg ) { $_GET['g'] = $arg; } } elseif ( $view === 'myteam' ) { if ( $arg ) { $_GET['myteam'] = $arg; } else { $_GET['view'] = 'myteam'; } } elseif ( $view === 'week' ) { $_GET['view'] = 'week'; if ( $arg ) { $_GET['week'] = $arg; } }
if ( empty( $routed ) ) {
$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/app/' );
DBD_Router::$route = DBD_Router::route();
} else { $view = DBD_Router::$route['view']; $arg = str_replace( '/', '_', trim( (string) $arg, '/' ) ); }
$file = method_exists( 'DBD_Router', 'view_file' ) ? DBD_Router::view_file( DBD_Router::$route ) : DBD_PATH . 'includes/views/' . DBD_Router::$route['view'] . '.php';  /* the router's choice: a kicker's page for a K (SCOPE §47) */
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
