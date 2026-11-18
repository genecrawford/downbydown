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
function wp_remote_get() { return null; } function wp_remote_retrieve_response_code( $r = null ) { return is_array( $r ) ? (int) ( $r['response']['code'] ?? 0 ) : 0; } function wp_remote_retrieve_body( $r = null ) { return is_array( $r ) ? (string) ( $r['body'] ?? '' ) : ''; }
function shortcode_atts( $d, $a ) { return array_merge( $d, (array) $a ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function __( $s ) { return $s; } function _e( $s ) { echo $s; }
function wp_schedule_single_event() {} function wp_next_scheduled() { return false; } function wp_schedule_event() {} function register_activation_hook() {} function register_deactivation_hook() {}

/* ---- the plugin, minus the bootstrap and admin. The SEO class extends a WordPress sitemap base class; an empty stand-in lets it load. */
if ( ! class_exists( 'WP_Sitemaps_Provider' ) ) { class WP_Sitemaps_Provider { public $name = ''; public $object_type = ''; } }
foreach ( array( 'class-dbd-data', 'class-dbd-marks', 'class-dbd-grades', 'class-dbd-metrics', 'class-dbd-takeaways', 'class-dbd-lineup', 'class-dbd-fantasy', 'class-dbd-myteam', 'class-dbd-leaders', 'class-dbd-kicker', 'class-dbd-defense', 'class-dbd-week', 'class-dbd-hub', 'class-dbd-compare', 'class-dbd-season', 'class-dbd-boards', 'class-dbd-sync', 'class-dbd-card', 'class-dbd-urls', 'class-dbd-router', 'class-dbd-seo', 'class-dbd-blocks', 'class-dbd-post', 'class-dbd-news', 'class-dbd-email', 'class-dbd-videos', 'class-dbd-growth', 'class-dbd-signup', 'class-dbd-pack', 'class-dbd-pack-mail', 'class-dbd-board-mail' ) as $c ) { $f = DBD_PATH . 'includes/' . $c . '.php'; if ( is_readable( $f ) ) { require $f; } }

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
	$want = strpos( (string) $arg, 'ig-' ) === 0 ? array( 1080, 1080 ) : array( 1200, 630 );  /* the Instagram squares (SCOPE §73) */
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
/* ---- the share pack email (2.28.0, SCOPE §69): booking, the email, one copy per person, a retry for only the one Brevo refused */
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
		foreach ( array( '1 · The biggest swing', '3 · Best and worst offense', '5 · Keys to the game, Week ' . ( $n + 1 ), 'Reddit title', 'Reddit comment, with the link', 'X and Bluesky', ' of 280', 'Open the Share pack', 'page=down-by-down-share&#038;week=' . $n ) as $w ) {
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
		$ck( 'Share on Facebook and Square for Instagram on every item and keys side (2.31.0)', substr_count( $h, '>Share on Facebook</a>' ) === count( DBD_Pack::items( $n ) ) + $nk && substr_count( $h, '>Square for Instagram</a>' ) === count( DBD_Pack::items( $n ) ) + $nk, substr_count( $h, '>Share on Facebook</a>' ) . ' Facebook, ' . substr_count( $h, '>Square for Instagram</a>' ) . ' squares' );
		$ck( 'an Instagram caption for every item and keys side', substr_count( $h, 'Full chart: link in bio' ) === count( DBD_Pack::items( $n ) ) + $nk && substr_count( $h, 'Instagram caption, with the square' ) === count( DBD_Pack::items( $n ) ) && strpos( $h, 'card=ig-week-' . $n . '-' ) !== false && strpos( $h, 'sharer.php?u=' ) !== false );
		$ck( 'the X and Bluesky links carry the share link', strpos( $h, 'https://x.com/intent/post?text=' ) !== false && strpos( $h, 'https://bsky.app/intent/compose?text=' ) !== false && strpos( $h, 'share%3Dpack-week-' . $n . '-snaps' ) !== false );
		$keep = $GLOBALS['dbd_opt']; $GLOBALS['dbd_opt'][ DBD_Pack::OPT_X ] = ''; $GLOBALS['dbd_opt'][ DBD_Pack::OPT_BSKY ] = '';
		$hx = DBD_Pack_Mail::build( $n )['html'] ?? ''; $ck( 'cleared accounts: no X or Bluesky links', strpos( $hx, 'Post on X' ) === false && strpos( $hx, 'Post on Bluesky' ) === false );
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
	$ck( $ks === array_values( array_intersect( array( 'swing', 'snaps', 'offense', 'targets' ), $ks ) ) && in_array( 'swing', $ks, true ) && in_array( 'offense', $ks, true ), 'the items in their fixed order, got ' . implode( ',', $ks ) );
	foreach ( $items as $it ) { $ck( isset( DBD_Pack::NUM[ $it['k'] ] ), $it['k'] . ': a fixed number' ); }
	$ck( DBD_Pack::NUM === array( 'swing' => 1, 'snaps' => 2, 'offense' => 3, 'targets' => 4, 'keys' => 5 ), 'the fixed numbers' );
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
	) as $fn => $body ) { if ( ! function_exists( $fn ) ) { $GLOBALS['hfn'][ $fn ] = $body; eval( 'function ' . $fn . '( ...$a ) { return $GLOBALS["hfn"]["' . $fn . '"]( ...$a ); }' ); } }
	require DBD_PATH . 'includes/admin/settings.php';
	$want = array( 'ov' => array( 'Needs a look', 'The last data sync failed', 'needs a Brevo API key', 'The last signup didn’t reach Brevo', 'Its send log row has Try again', 'Open Week in review', 'Sync now', 'Fetch news and videos now', 'news feed' ),
		'wk' => array( 'The weekly post', 'Weeks', 'The Tuesday email', 'Send log', 'sent to 198', 'won’t send until Brevo is set up', 'Waiting for the confirmation template', 'action=dbd_email_again&post=103', 'Try again', 'id="dbd-boards"', 'Board emails', 'name="dbd_email[boards]"', 'Add the Brevo API key above to send a test.' ),
		'nw' => array( 'Fetching', 'Sources', 'HTTP 503', 'Fetch news and videos now', 'Videos', 'Game highlights' ),
		'st' => array( 'Photos and logos', 'Addresses', 'Search and sharing', 'Using it in the theme', 'value="dbd"' ),
		'dt' => array( 'Current data', 'Sync log', 'Seasons', 'Share cards', 'value="dbd_data"', 'harness-token' ),
		'gr' => array( 'Visits by source', 'data-g="sources"', 'Google search', 'data-g="search"', 'Titles to tune', 'data-g="tune"', 'data-g="copy"', 'Top landing pages', 'data-g="landing"', 'Brevo isn’t set up', 'data-g="shares"', 'data-g="signups"', 'analytics.google.com/analytics/web/#/p123456/', 'resource_id=https%3A%2F%2Fexample.test%2F', 'page=googlesitekit-dashboard' ) );
	$want['sp'] = array( 'Week ', ' pack</h2>', 'name="week"', 'Click where you want to post.', 'Change the list', 'class="sp-open"',
		'The biggest swing', 'A usage riser', 'Best and worst offense', 'A target-share mover', 'Keys to the game, Week ', 'Post it', 'Also fits r/',
		'<details class="sp-tx"><summary>Texts to copy</summary>', 'Reddit title', 'Reddit first comment, with the link', 'X and Bluesky', ' of 280', 'Instagram caption', 'class="button button-small sp-cp"',
		'download="dbd-week-', 'card=pack-week-', 'Download square for Instagram', 'card=ig-week-', 'Download the game card',
		'class="button sp-rd" aria-label="Post to r/downbydown"', 'https://www.reddit.com/r/downbydown/submit?url=', 'share%3Dpack-week-', '>r/downbydown</a>',
		'class="button sp-x" aria-label="Post on X"', 'https://x.com/intent/post?text=', 'class="button sp-bs" aria-label="Post on Bluesky"', 'https://bsky.app/intent/compose?text=',
		'class="button sp-fb"', 'sharer.php?u=', '>Facebook</a>', 'class="button sp-cp sp-ig"', '>Instagram</button>', 'aria-label="Download square for Instagram">Square</a>',
		'<details class="dbd-box sp-set" id="dbd-pack-settings"', 'Settings: the Tuesday email and your accounts', 'value="dbd_pack_settings"', 'name="dbd_pack_mail_set[on]"', 'name="dbd_pack_mail_set[to]"', 'Brevo isn’t set up',
		'name="dbd_pack_sub"', 'name="dbd_pack_x"', 'name="dbd_pack_bsky"', 'name="dbd_pack_fb"', 'name="dbd_pack_ig"', 'Open the group', 'Save settings', 'id="dbd-pack-settings"><summary>' );  /* SCOPE §68, §71–74; the settings box starts closed */
	$want['ov'][] = 'Open Growth'; $want['ov'][] = 'data-g="ov-visits"'; $want['ov'][] = 'data-g="ov-clicks"';
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
	/* the Share pack's one Save (SCOPE §74): every field through its own sanitizer, the list kept apart from the accounts */
	if ( ! $arg || $arg === 'all' || $arg === 'sp' ) {
		$to = dbd_pack_settings_save( array( 'week' => '9', 'dbd_pack_mail_set' => array( 'on' => '1', 'to' => "Erik@Example.test\nbad\ngene@example.test, gene@example.test" ), 'dbd_pack_sub' => 'https://www.reddit.com/r/DownByDown/', 'dbd_pack_x' => '@dxd_NFLfootball', 'dbd_pack_bsky' => '@DownByDown.bsky.social', 'dbd_pack_fb' => 'facebook.com/groups/42/', 'dbd_pack_ig' => 'https://instagram.com/down_by_down/' ) );
		$got = array( get_option( 'dbd_pack_mail_set' ), get_option( 'dbd_pack_sub' ), get_option( 'dbd_pack_x' ), get_option( 'dbd_pack_bsky' ), get_option( 'dbd_pack_fb' ), get_option( 'dbd_pack_ig' ) );
		$ok = $got === array( array( 'on' => true, 'to' => array( 'erik@example.test', 'gene@example.test' ) ), 'DownByDown', 'dxd_NFLfootball', 'downbydown.bsky.social', 'https://www.facebook.com/groups/42', 'down_by_down' );
		$ok2 = strpos( $to, 'page=down-by-down-share' ) !== false && strpos( $to, 'week=9' ) !== false && strpos( $to, 'pack=saved' ) !== false && substr( $to, -strlen( '#dbd-pack-settings' ) ) === '#dbd-pack-settings';
		dbd_pack_settings_save( array( 'dbd_pack_mail_set' => array( 'to' => 'a@example.test' ), 'dbd_pack_sub' => '', 'dbd_pack_fb' => 'https://evil.test/' ) );
		$ok3 = get_option( 'dbd_pack_mail_set' ) === array( 'on' => false, 'to' => array( 'a@example.test' ) ) && get_option( 'dbd_pack_sub' ) === '' && get_option( 'dbd_pack_fb' ) === '';
		echo '  sp save: ' . ( $ok && $ok2 && $ok3 ? 'ok' : 'WRONG ' . json_encode( array( $ok, $ok2, $ok3, $got ) ) . ' ' . $to ) . "\n"; $bad += $ok && $ok2 && $ok3 ? 0 : 1;
		$_GET = array( 'page' => 'down-by-down-share', 'pack' => 'saved' ); ob_start(); dbd_settings_page(); $h2 = ob_get_clean();
		$open = strpos( $h2, 'id="dbd-pack-settings" open>' ) !== false && strpos( $h2, 'Settings saved.' ) !== false;
		echo '  sp after a save: ' . ( $open ? 'the settings box is open with its notice' : 'WRONG: the settings box should open with "Settings saved."' ) . "\n"; $bad += $open ? 0 : 1;
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

/* ---- route the request the way the page would */
$_GET = array();
if ( $view === 'path' ) {  /* a readable path, routed the way a request for it would be */
	$rp = new ReflectionProperty( 'DBD_Router', 'page_url' ); $rp->setAccessible( true ); $rp->setValue( null, 'https://example.test/' );
	DBD_Router::$route = DBD_Router::route_path( $arg ); $routed = true;
}
if ( $view === 'player' ) { $_GET['player'] = $arg; } elseif ( $view === 'players' ) { $_GET['view'] = 'players'; } elseif ( $view === 'team' ) { $_GET['team'] = $arg; } elseif ( $view === 'game' ) { $_GET['game'] = $arg; } elseif ( $view === 'teamplayers' ) { $_GET['view'] = 'players'; $_GET['team'] = $arg; } elseif ( $view === 'matchups' ) { $_GET['view'] = 'matchups'; if ( $arg ) { $_GET['week'] = $arg; } } elseif ( $view === 'teams' ) { $_GET['view'] = 'teams'; } elseif ( $view === 'compare' ) { $_GET['compare'] = $arg; } elseif ( $view === 'defense' ) { $_GET['team'] = $arg; $_GET['side'] = 'def'; } elseif ( $view === 'leaders' ) { $_GET['view'] = 'leaders'; if ( $arg && strpos( $arg, '.' ) !== false ) { $_GET['b'] = $arg; } elseif ( $arg ) { $_GET['g'] = $arg; } } elseif ( $view === 'myteam' ) { if ( $arg ) { $_GET['myteam'] = $arg; } else { $_GET['view'] = 'myteam'; } } elseif ( $view === 'week' ) { $_GET['view'] = 'week'; if ( $arg ) { $_GET['week'] = $arg; } } elseif ( $view === 'draft' || $view === 'combine' ) { $_GET['view'] = $view; if ( $arg ) { $_GET['y'] = $arg; } }  /* the season boards (SCOPE §63) */
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
