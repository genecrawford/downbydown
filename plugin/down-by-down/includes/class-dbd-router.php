<?php
/**
 * The shortcode, the query-arg router, asset loading, body classes and SEO tags.
 *
 *   /                      hub
 *   /?team=MIN             team play explorer
 *   /?game=2026_09_MIN_DET matchup (away offense vs home defense; &side=home flips it)
 *   /?view=matchups[&week=5]  every week's slate
 *   /?view=teams           team picker
 *   /?view=players[&team=MIN&pos=WR&q=jeff]  players index (1.2.0)
 *   /?player=00-0036322    player page (1.2.0)
 *   /?compare=id,id[,id]   compare two or three players of one position group (1.3.1)
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Router {

	public static $active = false;   // true when the current singular page carries the shortcode
	public static $route  = null;    // array: view, team, game, side, week, title, description
	private static $page_url = '';

	public static function init() {
		add_shortcode( 'down_by_down', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'play_explorer', array( __CLASS__, 'shortcode' ) );  // alias from the brief
		add_shortcode( 'dbd_count', array( __CLASS__, 'count_shortcode' ) );
		add_action( 'wp', array( __CLASS__, 'detect' ) );
		add_filter( 'theme_page_templates', array( __CLASS__, 'page_templates' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
	}

	/* ------------------------------------------------------------------ routing */

	public static function detect() {
		if ( ! is_singular() ) { return; }
		$post = get_queried_object();
		if ( ! $post || ! has_shortcode( (string) $post->post_content, 'down_by_down' ) && ! has_shortcode( (string) $post->post_content, 'play_explorer' ) ) { return; }
		self::$active   = true;
		self::$page_url = get_permalink( $post );
		self::$route    = self::route();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title_string' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		remove_action( 'wp_head', 'rel_canonical' );
	}

	private static function q( $key ) {
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	public static function route() {
		$r = array( 'view' => 'hub', 'team' => '', 'game' => null, 'side' => 'away', 'week' => DBD_Data::current_week() );
		if ( ! DBD_Data::has_data() ) { $r['view'] = 'nodata'; return $r; }
		$teams = DBD_Data::teams();
		$team  = strtoupper( self::q( 'team' ) );
		$game  = self::q( 'game' );
		$view  = strtolower( self::q( 'view' ) );
		$week  = (int) self::q( 'week' );
		$pid   = self::q( 'player' );
		if ( $pid !== '' ) {
			$row = preg_match( '/^\d{2}-\d{7}$/', $pid ) ? DBD_Data::player( $pid ) : null;
			$r['view']   = $row ? 'player' : 'notfound';
			$r['player'] = $row;
			$r['team']   = $row ? $row['team'] : '';
		} elseif ( self::q( 'compare' ) !== '' ) {
			$rows = array(); $grp = ''; $mixed = false;
			foreach ( array_unique( array_map( 'trim', explode( ',', self::q( 'compare' ) ) ) ) as $id ) {
				if ( ! preg_match( '/^\d{2}-\d{7}$/', $id ) ) { continue; }
				$row = DBD_Data::player( $id ); if ( ! $row ) { continue; }
				$g = DBD_Grades::group_of( $row['pos'] ); if ( ! $g ) { continue; }
				if ( ! $grp ) { $grp = $g; } elseif ( $g !== $grp ) { $mixed = true; continue; }
				$rows[] = $row; if ( count( $rows ) >= 3 ) { break; }
			}
			$r['view'] = 'compare'; $r['compare'] = $rows; $r['cgroup'] = $grp; $r['cmixed'] = $mixed;
		} elseif ( $view === 'players' ) {
			$r['view']  = 'players';
			$r['pteam'] = isset( $teams[ $team ] ) ? $team : '';
			$pos        = strtoupper( self::q( 'pos' ) );
			$r['pos']   = in_array( $pos, array( 'QB', 'RB', 'WR', 'TE' ), true ) ? $pos : '';
			$r['q']     = mb_substr( self::q( 'q' ), 0, 40 );
		} elseif ( $team !== '' ) {
			$r['view'] = isset( $teams[ $team ] ) ? 'team' : 'notfound';
			$r['team'] = $team;
		} elseif ( $game !== '' ) {
			$g = preg_match( '/^\d{4}_\d{2}_[A-Z]{2,3}_[A-Z]{2,3}$/', $game ) ? DBD_Data::game( $game ) : null;
			$r['view'] = $g ? 'game' : 'notfound';
			$r['game'] = $g;
			$r['side'] = self::q( 'side' ) === 'home' ? 'home' : 'away';
		} elseif ( $view === 'matchups' ) {
			$r['view'] = 'matchups';
			if ( $week && in_array( $week, DBD_Data::weeks(), true ) ) { $r['week'] = $week; }
		} elseif ( $view === 'teams' ) {
			$r['view'] = 'teams';
		}
		return $r;
	}

	/** URL of an app view: self::url( array( 'team' => 'MIN' ) ). No args = the hub. */
	public static function url( $args = array() ) {
		$base = self::$page_url ?: home_url( '/' );
		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $base ) : $base;
	}

	public static function body_class( $classes ) {
		$classes[] = 'dbd-app';
		$classes[] = 'dbd-view-' . ( self::$route['view'] ?? 'hub' );
		return $classes;
	}

	/* ------------------------------------------------------------------ SEO */

	/** Title and description for the current route. */
	public static function seo() {
		$r = self::$route; $site = 'Down by Down'; $season = DBD_Data::season();
		switch ( $r['view'] ?? 'hub' ) {
			case 'team':
				$t = DBD_Data::team( $r['team'] );
				return array( $t['nick'] . ' offense play explorer', "Every {$t['name']} offensive snap of the $season season, drawn on the field. Filter by down, play type, field zone, formation, quarter and player." );
			case 'game':
				$g = $r['game']; $O = $r['side'] === 'away' ? $g['away'] : $g['home']; $D = $r['side'] === 'away' ? $g['home'] : $g['away'];
				$o = DBD_Data::team( $O )['nick']; $d = DBD_Data::team( $D )['nick'];
				return array( "$o offense vs $d defense, Week {$g['week']}", "How the $o offense matches up against the $d defense: EPA, success rate, explosive plays, third downs, red zone and where the ball goes, with the biggest edge and the risk." );
			case 'matchups':
				return array( "Week {$r['week']} matchups", "Every game of the $season NFL season with lines, totals, scores and a matchup read for each one." );
			case 'teams':
				return array( 'Pick a team', "All 32 NFL offenses ranked by EPA per play, each with a play explorer showing every snap of the $season season." );
			case 'player':
				$p = $r['player']; $t = DBD_Data::team( $p['team'] );
				return array( "{$p['name']}, {$t['nick']} {$p['pos']}", "Where the ball finds {$p['name']}: every target and carry of the $season season on the field, the game log, snap share, usage by down, quarter and score, and the latest injury report." );
			case 'compare':
				$names = array_map( function ( $p ) { return $p['name']; }, $r['compare'] );
				return array( $names ? implode( ' vs ', $names ) : 'Compare players', $names ? 'Side by side: where each grades against the league, where the ball finds each of them, and how their weeks line up.' : 'Pick two or three players of one position and see them side by side.' );
			case 'players':
				if ( $r['pteam'] ) { $t = DBD_Data::team( $r['pteam'] ); return array( "{$t['nick']} players", "Every {$t['name']} skill player who has taken an offensive snap in $season, with snap share, targets, carries, yards, EPA and this week's injury status." ); }
				return array( 'Pick a player', "Every NFL skill player who has taken an offensive snap in $season: snap share, targets, carries, yards, touchdowns, EPA and injury status, by team and position." );
			case 'notfound':
				return array( 'Not found', '' );
			default:
				return array( "Every snap, drawn on the field", "How every NFL offense actually plays, from public play-by-play, updated every night. This week's slate, every matchup, every team." );
		}
	}

	public static function title( $parts ) {
		list( $t ) = self::seo();
		$parts['title'] = $t;
		return $parts;
	}

	public static function title_string( $title ) {
		list( $t ) = self::seo();
		return $t . ' | Down by Down';
	}

	public static function head() {
		list( $t, $d ) = self::seo();
		$r = self::$route; $args = array();
		if ( $r['view'] === 'team' ) { $args = array( 'team' => $r['team'] ); }
		if ( $r['view'] === 'game' ) { $args = array( 'game' => $r['game']['id'] ); if ( $r['side'] === 'home' ) { $args['side'] = 'home'; } }
		if ( $r['view'] === 'matchups' ) { $args = array( 'view' => 'matchups' ); if ( $r['week'] !== DBD_Data::current_week() ) { $args['week'] = $r['week']; } }
		if ( $r['view'] === 'teams' ) { $args = array( 'view' => 'teams' ); }
		if ( $r['view'] === 'player' ) { $args = array( 'player' => $r['player']['id'] ); }
		if ( $r['view'] === 'players' ) { $args = array( 'view' => 'players' ); if ( $r['pteam'] ) { $args['team'] = $r['pteam']; } if ( $r['pos'] ) { $args['pos'] = $r['pos']; } }
		if ( $r['view'] === 'compare' ) { $ids = array_map( function ( $p ) { return $p['id']; }, $r['compare'] ); sort( $ids ); $args = $ids ? array( 'compare' => implode( ',', $ids ) ) : array( 'view' => 'players' ); }
		$url = self::url( $args );
		echo "\n" . '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $t . ' | Down by Down' ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $d ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
		if ( $r['view'] === 'notfound' ) { echo '<meta name="robots" content="noindex">' . "\n"; }
		if ( $r['view'] === 'game' && class_exists( 'DBD_Seo' ) ) { echo DBD_Seo::game_jsonld( $r['game'] ); }
		if ( $r['view'] === 'player' && class_exists( 'DBD_Seo' ) ) { echo DBD_Seo::player_jsonld( $r['player'] ); }
		if ( $r['view'] === 'players' && $r['q'] !== '' ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }
		if ( $r['view'] === 'compare' ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }
	}

	/* ------------------------------------------------------------------ assets */

	public static function assets() {
		$r = self::$route;
		wp_enqueue_style( 'dbd-fonts', 'https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap', array(), null );
		wp_enqueue_style( 'dbd', DBD_URL . 'assets/dbd.css', array( 'dbd-fonts' ), DBD_VERSION );
		wp_enqueue_style( 'dbd-transitions', DBD_URL . 'assets/transitions.css', array( 'dbd' ), DBD_VERSION );
		wp_enqueue_script( 'dbd-site', DBD_URL . 'assets/dbd-site.js', array(), DBD_VERSION, true );
		if ( $r['view'] === 'team' ) {
			$t = DBD_Data::team( $r['team'] ); $rel = 'plays/' . $r['team'] . '.json';
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_style( 'dbd-chartist', DBD_URL . 'assets/vendor/chartist.css', array( 'dbd' ), '1.3.0' );
			wp_enqueue_script( 'dbd-chartist', DBD_URL . 'assets/vendor/chartist.umd.js', array(), '1.3.0', true );
			wp_enqueue_script( 'dbd-explorer', DBD_URL . 'assets/team-explorer.js', array( 'dbd-marks', 'dbd-chartist' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-explorer', 'DBD_TEAM', array(
				'abbr'      => $r['team'],
				'team'      => $t,
				'teams'     => DBD_Data::teams(),
				'season'    => DBD_Data::season(),
				'dataUrl'   => DBD_Data::url() . '/' . $rel . '?v=' . DBD_Data::file_version( $rel ),
				'passrate'  => DBD_Metrics::league( 'passrate' ),
				'tend'      => DBD_Metrics::league( 'tend' ),
				'through'   => DBD_Data::through_week(),
				'accent'    => DBD_Marks::accent( $t ),
				'base'      => self::url(),
			) );
		}
		if ( $r['view'] === 'player' ) {
			$p = $r['player']; $t = DBD_Data::team( $p['team'] ); $rel = 'plays/' . $p['team'] . '.json'; $rec = DBD_Data::player_record( $p['team'], $p['id'] );
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_script( 'dbd-player', DBD_URL . 'assets/player.js', array( 'dbd-marks' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-player', 'DBD_PLAYER', array(
				'id'      => $p['id'],
				'short'   => $rec['short'] ?? '',
				'name'    => $p['name'],
				'pos'     => $p['pos'],
				'team'    => $p['team'],
				'teams'   => DBD_Data::teams(),
				'season'  => DBD_Data::season(),
				'dataUrl' => DBD_Data::url() . '/' . $rel . '?v=' . DBD_Data::file_version( $rel ),
				'accent'  => DBD_Marks::accent( $t ),
				'base'    => self::url(),
			) );
		}
		if ( $r['view'] === 'compare' ) {
			$files = array(); $pl = array();
			foreach ( $r['compare'] as $row ) { $rel = 'plays/' . $row['team'] . '.json'; $files[ $row['team'] ] = DBD_Data::url() . '/' . $rel . '?v=' . DBD_Data::file_version( $rel ); $rec = DBD_Data::player_record( $row['team'], $row['id'] ); $pl[] = array( 'id' => $row['id'], 'short' => $rec['short'] ?? '', 'name' => $row['name'], 'team' => $row['team'], 'pos' => $row['pos'] ); }
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_script( 'dbd-compare', DBD_URL . 'assets/compare.js', array( 'dbd-marks' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-compare', 'DBD_CMP', array( 'base' => self::url(), 'group' => $r['cgroup'], 'players' => $pl, 'files' => $files, 'teams' => DBD_Data::teams(), 'heads' => DBD_Data::headshots() ) );
		}
		if ( $r['view'] === 'players' ) {
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_script( 'dbd-players', DBD_URL . 'assets/players.js', array( 'dbd-marks' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-players', 'DBD_PLIST', array( 'base' => self::url(), 'team' => $r['pteam'], 'pos' => $r['pos'], 'q' => $r['q'], 'teams' => DBD_Data::teams(), 'heads' => DBD_Data::headshots() ) );
		}
	}

	/* ------------------------------------------------------------------ rendering */

	public static function shortcode() {
		if ( ! self::$active ) {  // shortcode inside a loop or a non-singular context: route anyway
			self::$active = true; self::$page_url = get_permalink(); self::$route = self::route();
		}
		$r = self::$route;
		$file = DBD_PATH . 'includes/views/' . $r['view'] . '.php';
		if ( ! file_exists( $file ) ) { $file = DBD_PATH . 'includes/views/notfound.php'; }
		ob_start();
		echo '<div class="dbd dbd-view-' . esc_attr( $r['view'] ) . '">';  // dbd-view-* so it can never collide with component classes like .dbd-teams or .dbd-game
		include $file;
		echo '</div>';
		return ob_get_clean();
	}

	/** [dbd_count games] or [dbd_count teams] for the Divi menu. */
	public static function count_shortcode( $atts ) {
		$what = strtolower( trim( is_array( $atts ) ? ( $atts[0] ?? 'games' ) : (string) $atts ) );
		if ( $what === 'teams' ) { return (string) count( DBD_Data::teams() ); }
		return (string) count( DBD_Data::week_games( DBD_Data::current_week() ) );
	}

	/* ------------------------------------------------------------------ view helpers */

	/** The top bar: breadcrumb trail plus the Updated stamp. $crumbs = array( 'label' => url|null ) */
	public static function topbar( $crumbs ) {
		$out = '<div class="dbd-top"><div class="dbd-crumb"><a href="' . esc_url( self::url() ) . '" class="dbd-brand">DOWN X DOWN</a>';
		foreach ( $crumbs as $label => $href ) {
			$out .= ' <span>›</span> ' . ( $href ? '<a href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>' : '<b>' . esc_html( $label ) . '</b>' );
		}
		$out .= '</div><div class="dbd-r"><span class="dbd-upd">' . esc_html( DBD_Data::updated_text() ) . '</span></div></div>';
		return $out;
	}

	public static function line_text( $g ) {
		if ( ! isset( $g['spread'] ) || $g['spread'] === null ) { return ''; }
		$s = (float) $g['spread'];
		if ( $s == 0 ) { return 'PK'; }
		return $s > 0 ? $g['home'] . ' -' . rtrim( rtrim( number_format( $s, 1 ), '0' ), '.' ) : $g['away'] . ' -' . rtrim( rtrim( number_format( -$s, 1 ), '0' ), '.' );
	}

	public static function kickoff_html( $g ) {
		$k = DBD_Data::kickoff( $g );
		return '<time class="dbd-kick" datetime="' . esc_attr( $k['utc'] ) . '" data-fmt="time">' . esc_html( $k['et'] ) . '</time>';
	}

	public static function is_final( $g ) {
		return isset( $g['home_score'] ) && $g['home_score'] !== null && isset( $g['away_score'] ) && $g['away_score'] !== null;
	}

	public static function week_label( $week ) {
		$games = DBD_Data::week_games( $week );
		$type  = $games[0]['game_type'] ?? 'REG';
		$names = array( 'WC' => 'Wild Card', 'DIV' => 'Divisional', 'CON' => 'Conference', 'SB' => 'Super Bowl' );
		return $names[ $type ] ?? 'Week ' . (int) $week;
	}

	/* ------------------------------------------------------------------ standalone template (non-Divi installs) */

	public static function page_templates( $templates ) {
		$templates['dbd-app-standalone'] = 'Down by Down: app page (no theme header)';
		return $templates;
	}

	public static function template_include( $template ) {
		if ( is_singular() && get_page_template_slug() === 'dbd-app-standalone' ) {
			return DBD_PATH . 'templates/app-standalone.php';
		}
		return $template;
	}
}
