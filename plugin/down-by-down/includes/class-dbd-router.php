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
 *
 * Since 1.19.0 these are paths (/team/min/, /player/justin-jefferson/, …; DBD_Urls). The query strings still work and
 * answer with a 301 to the path.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Router {

	public static $active = false;   // true when the current singular page carries the shortcode
	public static $route  = null;    // array: view, team, game, side, week, title, description
	private static $page_url = '';
	private static $P = null;        // the values a readable path carries (DBD_Urls::parse), null on a query-string request
	private static $nf = false;      // a season the site doesn't have (SCOPE §44)

	public static function init() {
		add_shortcode( 'down_by_down', array( __CLASS__, 'shortcode' ) );
		add_shortcode( 'play_explorer', array( __CLASS__, 'shortcode' ) );  // alias from the brief
		add_shortcode( 'dbd_count', array( __CLASS__, 'count_shortcode' ) );
		add_action( 'wp', array( __CLASS__, 'detect' ) );
		add_filter( 'theme_page_templates', array( __CLASS__, 'page_templates' ) );
		add_filter( 'template_include', array( __CLASS__, 'template_include' ), 99 );
		DBD_Urls::init();
	}

	/* ------------------------------------------------------------------ routing */

	public static function detect() {
		if ( ! is_singular() ) { if ( is_front_page() ) { self::front(); } return; }
		$post = get_queried_object();
		if ( ! $post || ! has_shortcode( (string) $post->post_content, 'down_by_down' ) && ! has_shortcode( (string) $post->post_content, 'play_explorer' ) ) { if ( is_front_page() ) { self::front(); } return; }
		self::$active   = true;
		self::$page_url = get_permalink( $post );
		/* the app page is the one last saved with the shortcode (DBD_Urls::init); a visit only fills it in when none is known, so two pages
		   holding the shortcode can't flip the site's paths back and forth */
		if ( ! DBD_Urls::page_id() && $post->post_status === 'publish' ) { update_option( DBD_Urls::OPT_PAGE, (int) $post->ID ); }
		$path = (string) get_query_var( 'dbd_path' ); $sv = (string) get_query_var( 'dbd_season' );
		self::apply_season( DBD_Urls::season_of( $sv, $path, $_GET ), $path === '' ? self::my_team_query() : strpos( $path, 'my-team' ) === 0 );
		if ( $path !== '' ) { self::$P = DBD_Urls::parse( $path ); } elseif ( $sv !== '' ) { self::$P = array(); }  /* /2025/: that season's page */
		if ( self::$nf ) { self::$P = array( '__nf' => 1 ); }
		self::$route    = self::route();
		if ( self::q( 'card' ) !== '' ) { DBD_Card::serve( self::q( 'card' ) ); }  /* the share card PNG (SCOPE §26); serve() exits */
		if ( $path === '' ) { self::redirect_old(); }
		if ( $path !== '' || $sv !== '' ) { self::redirect_prefixed(); }  /* /hub/team/min/ -> /team/min/ */
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'title' ) );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title_string' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		remove_action( 'wp_head', 'rel_canonical' );
	}

	/**
	 * The front page when the app lives on its own page (/hub/): an old /?team=MIN link answers with a 301 to its path, and an
	 * old /?card= share image is still served, since cards shared before the move point at the site root. A bare / is the home.
	 */
	public static function front() {
		$keys = array( 'card', 'team', 'game', 'player', 'compare', 'view', 'myteam', 'season' );
		if ( ! array_intersect( $keys, array_keys( $_GET ) ) || ! DBD_Data::has_data() ) { return; }
		self::$P = null;
		self::apply_season( DBD_Urls::season_of( '', '', $_GET ), self::my_team_query() );
		if ( self::$nf ) { self::$nf = false; DBD_Data::use_season( null ); return; }  /* a season the site doesn't hold: stay home, never another year's page (Greptile, PR #59) */
		self::$route = self::route();
		if ( self::q( 'card' ) !== '' ) { DBD_Card::serve( self::q( 'card' ) ); }  /* serve() exits */
		self::redirect_old();
		self::$route = null; DBD_Data::use_season( null );  /* nothing to send: the home renders as itself */
	}

	private static function q( $key ) {
		if ( self::$P !== null ) {  /* a path decides the route; the query string only adds the filters a path doesn't carry */
			if ( isset( self::$P[ $key ] ) ) { return (string) self::$P[ $key ]; }
			if ( in_array( $key, DBD_Urls::ROUTE_KEYS, true ) ) { return ''; }
		}
		return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
	}

	/** Route a path the way a request for it would (the view harness and the URL checks use this). A leading /2025/ shows that season. */
	public static function route_path( $path ) {
		list( $s, $rest ) = DBD_Urls::split_season( $path );
		self::apply_season( DBD_Urls::season_of( $s, $rest, array() ), strpos( $rest, 'my-team' ) === 0 );
		self::$P = self::$nf ? array( '__nf' => 1 ) : DBD_Urls::parse( $rest ); $r = self::route(); self::$P = null; return $r;
	}

	/** Show the season a request names (SCOPE §44). My team is always this season's; a season the site doesn't hold is not found. */
	public static function apply_season( $s, $myteam = false ) {
		self::$nf = false; $s = (int) $s;
		if ( ! $s || $myteam ) { DBD_Data::use_season( null ); return; }
		if ( $s === DBD_Data::current_season() ) { DBD_Data::use_season( null ); return; }
		if ( ! DBD_Data::use_season( $s ) ) { self::$nf = true; }
	}

	private static function my_team_query() { return isset( $_GET['myteam'] ) || ( isset( $_GET['view'] ) && $_GET['view'] === 'myteam' ); }

	/** The same page in another season: the same team, week or board; the same player if he played that year, else that season's players search; a game's week. */
	public static function season_url( $s ) {
		$r = self::$route; $was = DBD_Data::archived() ? DBD_Data::season() : null; $s = (int) $s;
		$args = self::args_of( $r );
		if ( $r['view'] === 'matchups' ) { $args['week'] = (int) $r['week']; }  /* args_of() leaves out the current week; the other season may default elsewhere (Greptile, PR #56) */
		$target = $s === DBD_Data::current_season() ? null : $s;
		DBD_Data::use_season( $target );
		switch ( $r['view'] ) {
			case 'player': if ( ! DBD_Data::player( $r['player']['id'] ) ) { $args = array( 'view' => 'players', 'q' => $r['player']['name'] ); } break;
			case 'compare': foreach ( $r['compare'] as $p ) { if ( ! DBD_Data::player( $p['id'] ) ) { $args = array( 'view' => 'players' ); break; } } break;
			case 'game': $args = array( 'view' => 'matchups', 'week' => (int) $r['game']['week'] ); break;
			case 'week': case 'matchups': if ( ! empty( $args['week'] ) && ! in_array( (int) $args['week'], $r['view'] === 'week' ? DBD_Week::weeks() : DBD_Data::weeks(), true ) ) { unset( $args['week'] ); } break;
			case 'myteam': case 'notfound': case 'nodata': $args = array(); break;
		}
		$u = self::url( $args );
		DBD_Data::use_season( $was );
		return $u;
	}

	/** An old query-string link: 301 to its path (redirect_target()). */
	public static function redirect_old() {
		$to = self::redirect_target(); if ( $to === null ) { return; }
		wp_safe_redirect( $to, 301, 'Down by Down' ); exit;
	}

	/** Where an old query-string request belongs: its path, keeping the parameters the route doesn't use (utm_*, fbclid). Null when it stays. */
	public static function redirect_target() {
		if ( ! DBD_Urls::on() || ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) { return null; }
		$keys = array( 'team', 'game', 'player', 'compare', 'view', 'myteam', 'season' );
		if ( ! array_intersect( $keys, array_keys( $_GET ) ) ) { return null; }
		$r = self::$route; if ( in_array( $r['view'], array( 'notfound', 'nodata' ), true ) ) { return null; }
		$to = self::url( self::args_of( $r, true ) );
		$used = array_merge( DBD_Urls::ROUTE_KEYS, array( 'myteam', 'pt', 'pos', 'q', 'b', 'season' ) );
		$keep = array(); foreach ( $_GET as $k => $v ) { if ( ! in_array( $k, $used, true ) && is_string( $v ) ) { $keep[ $k ] = sanitize_text_field( wp_unslash( $v ) ); } }
		if ( $keep ) { $to = add_query_arg( array_map( 'rawurlencode', $keep ), $to ); }
		$here = wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH ); $there = wp_parse_url( $to, PHP_URL_PATH );
		if ( ! $there || untrailingslashit( (string) $here ) === untrailingslashit( (string) $there ) ) { return null; }  /* no path for it: stay */
		return $to;
	}

	/**
	 * With root paths on and the app on its own page, a page-prefixed request (/hub/team/min/, /hub/2025/: the links 2.0.1 drew while the
	 * app sat on /hub/) answers with a 301 to the same page at the root, keeping the query string. Exits when it redirects.
	 */
	public static function redirect_prefixed() {
		$to = self::prefixed_target(); if ( $to === null ) { return; }
		wp_safe_redirect( $to, 301, 'Down by Down' ); exit;
	}

	public static function prefixed_target() {
		if ( ! DBD_Urls::on() || DBD_Urls::prefix() !== '' || ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) { return null; }
		$own = DBD_Urls::page_path(); if ( $own === '' || ! self::$route || in_array( self::$route['view'], array( 'notfound', 'nodata' ), true ) ) { return null; }
		$here = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		$rel = ltrim( $here, '/' ); if ( $home !== '' && strpos( $rel, $home . '/' ) === 0 ) { $rel = substr( $rel, strlen( $home ) + 1 ); }
		if ( strpos( trailingslashit( $rel ), $own ) !== 0 || trailingslashit( $rel ) === $own ) { return null; }  /* not prefixed, or the hub itself */
		$to = self::url( self::args_of( self::$route, true ) );
		parse_str( (string) wp_parse_url( $to, PHP_URL_QUERY ), $has );
		$keep = array(); foreach ( $_GET as $k => $v ) { if ( is_string( $v ) && ! isset( $has[ $k ] ) && ! in_array( $k, DBD_Urls::ROUTE_KEYS, true ) ) { $keep[ $k ] = sanitize_text_field( wp_unslash( $v ) ); } }
		if ( $keep ) { $to = add_query_arg( array_map( 'rawurlencode', $keep ), $to ); }
		return untrailingslashit( (string) wp_parse_url( $to, PHP_URL_PATH ) ) === untrailingslashit( $here ) ? null : $to;
	}

	public static function route() {
		$r = array( 'view' => 'hub', 'team' => '', 'game' => null, 'side' => 'away', 'week' => DBD_Data::current_week() );
		if ( ! DBD_Data::has_data() ) { $r['view'] = 'nodata'; return $r; }
		if ( isset( self::$P['__nf'] ) ) { $r['view'] = 'notfound'; return $r; }  /* a path nothing answers to */
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
				$g = DBD_Grades::pool_of( $row['pos'] ); if ( ! $g ) { continue; }  /* SCOPE §38: a wide receiver compares with wide receivers, a tight end with tight ends */
				if ( ! $grp ) { $grp = $g; } elseif ( $g !== $grp ) { $mixed = true; continue; }
				$rows[] = $row; if ( count( $rows ) >= 3 ) { break; }
			}
			$r['view'] = 'compare'; $r['compare'] = $rows; $r['cgroup'] = $grp; $r['cmixed'] = $mixed;
		} elseif ( $view === 'leaders' ) {  /* Leaders (SCOPE §37): a tab (g=) or one full board (b=) */
			$r['view'] = 'leaders'; $g = strtolower( self::q( 'g' ) ); $b = self::q( 'b' );
			if ( $g === 'rec' ) { $g = 'wr'; }  /* 1.13.0's combined tab */
			$r['lgroup'] = isset( DBD_Leaders::GROUPS[ $g ] ) ? $g : 'fan';
			$r['lboard'] = preg_match( '/^[a-z]{2,3}\.[A-Za-z0-9_]{1,10}(\.[A-Za-z0-9_]{1,10})?$/', $b ) ? $b : '';
		} elseif ( $view === 'week' ) {  /* the week in review (SCOPE §40): a week with a final game, the latest by default */
			$r['view'] = 'week'; $ok = DBD_Week::weeks();
			$r['week'] = ( $week && in_array( $week, $ok, true ) ) ? $week : DBD_Week::default_week();
			if ( ! $r['week'] ) { $r['week'] = DBD_Data::current_week(); }
		} elseif ( self::q( 'myteam' ) !== '' || $view === 'myteam' ) {  /* My team (SCOPE §36): the team is the address; an empty one restores from the device in myteam.js */
			$r['view'] = 'myteam'; $r['myteam'] = DBD_MyTeam::parse( self::q( 'myteam' ) );
		} elseif ( $view === 'players' ) {
			$r['view']  = 'players';
			$pt = strtoupper( self::q( 'pt' ) );  /* the index's own team filter; team= opens that team's page */
			$r['pteam'] = isset( $teams[ $team ] ) ? $team : ( isset( $teams[ $pt ] ) ? $pt : '' ); $r['ptfilter'] = ! isset( $teams[ $team ] ) && isset( $teams[ $pt ] );
			$pos        = strtoupper( self::q( 'pos' ) );
			$r['pos']   = in_array( $pos, array( 'QB', 'RB', 'WR', 'TE' ), true ) ? $pos : '';
			$r['q']     = mb_substr( self::q( 'q' ), 0, 40 );
			if ( $r['pteam'] && ! $r['ptfilter'] && $r['q'] === '' ) { $r['view'] = 'teamplayers'; }  /* a team's players is its own page (SCOPE §32); the league index filters by team in place */
		} elseif ( $team !== '' ) {
			$r['view'] = isset( $teams[ $team ] ) ? 'team' : 'notfound';
			$r['team'] = $team;
			$r['tside'] = self::q( 'side' ) === 'def' ? 'def' : 'off';  /* SCOPE §39: the team's defense tab */
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
		if ( $r['view'] === 'hub' && DBD_Season::show() ) { $r['view'] = 'season'; }  /* SCOPE §44: a past season, or this one after the Super Bowl */
		return $r;
	}

	/** URL of an app view: self::url( array( 'team' => 'MIN' ) ). No args = the hub. */
	/**
	 * Column tiers (SCOPE §20): add a class to the Nth cell of every row so narrow containers can hide it.
	 * $tiers is a list indexed by cell position: null, 'dbd-t2' or 'dbd-t3'. Header and body rows alike.
	 */
	public static function tierize( $html, array $tiers ) {
		if ( ! array_filter( $tiers ) ) { return $html; }
		return preg_replace_callback( '/<tr\b[^>]*>.*?<\/tr>/s', function ( $row ) use ( $tiers ) {
			$i = -1;
			return preg_replace_callback( '/<t([dh])\b([^>]*)>/', function ( $c ) use ( &$i, $tiers ) {
				$i++; $t = $tiers[ $i ] ?? null; if ( ! $t ) { return $c[0]; }
				$attrs = $c[2];
				if ( preg_match( '/\bclass="([^"]*)"/', $attrs, $m ) ) { $attrs = str_replace( $m[0], 'class="' . $t . ' ' . $m[1] . '"', $attrs ); } else { $attrs .= ' class="' . $t . '"'; }
				return '<t' . $c[1] . $attrs . '>';
			}, $row[0] );
		}, $html );
	}

	public static function url( $args = array() ) {
		$base = self::base( (bool) $args || DBD_Data::archived() );  /* the hub is the app page's address; every other view is a path */
		/* a past season (SCOPE §44): its links carry it, as a /2025/ prefix or ?season=; My team is always this season's, a game's slug names its own */
		if ( DBD_Data::archived() && ! isset( $args['myteam'] ) && ( $args['view'] ?? '' ) !== 'myteam' ) {
			$s = DBD_Data::season();
			if ( isset( $args['card'] ) || ! DBD_Urls::on() ) { $args['season'] = $s; }
			elseif ( ! isset( $args['game'] ) ) { $base = trailingslashit( $base ) . $s . '/'; }
		}
		if ( $args && DBD_Urls::on() ) { $u = DBD_Urls::build( $base, $args ); if ( $u !== null ) { return $u; } }
		return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $base ) : $base;
	}

	/**
	 * The base of an app link. $paths: a view other than the hub, which with readable URLs starts where paths start (the site root
	 * since 2.1.0, wherever the app page is). Otherwise the app page's URL: the page being served, else the page that holds the
	 * shortcode (the sitemap, the cards and the home's blocks run outside it).
	 */
	public static function base( $paths = false ) {
		if ( $paths && DBD_Urls::on() ) { return DBD_Urls::path_base(); }
		if ( self::$page_url ) { return self::$page_url; }
		$id = DBD_Urls::page_id(); if ( $id ) { return get_permalink( $id ); }  /* the remembered app page (cards and the sitemap run outside it) */
		return class_exists( 'DBD_Seo' ) && class_exists( 'WP_Query' ) ? DBD_Seo::app_url() : home_url( '/' );
	}

	/** The link arguments of a route: the canonical page ($full false) or everything the request carried ($full true, for the redirect). */
	public static function args_of( $r, $full = false ) {
		$args = array();
		if ( $r['view'] === 'team' ) { $args = array( 'team' => $r['team'] ); if ( ( $r['tside'] ?? 'off' ) === 'def' ) { $args['side'] = 'def'; } }
		if ( $r['view'] === 'game' ) { $args = array( 'game' => $r['game']['id'] ); if ( $r['side'] === 'home' ) { $args['side'] = 'home'; } }
		if ( $r['view'] === 'matchups' ) { $args = array( 'view' => 'matchups' ); if ( $r['week'] !== DBD_Data::current_week() ) { $args['week'] = $r['week']; } }
		if ( $r['view'] === 'teams' ) { $args = array( 'view' => 'teams' ); }
		if ( $r['view'] === 'myteam' ) { $args = ( $full && self::q( 'myteam' ) !== '' ) ? array( 'myteam' => self::q( 'myteam' ) ) : array( 'view' => 'myteam' ); }
		if ( $r['view'] === 'week' ) { $args = array( 'view' => 'week', 'week' => $r['week'] ); }
		if ( $r['view'] === 'leaders' ) { $args = array( 'view' => 'leaders' ); if ( $r['lboard'] !== '' ) { $args['b'] = $r['lboard']; } elseif ( $r['lgroup'] !== 'fan' ) { $args['g'] = $r['lgroup']; } }
		if ( $r['view'] === 'player' ) { $args = array( 'player' => $r['player']['id'] ); }
		if ( $r['view'] === 'players' ) { $args = array( 'view' => 'players' ); if ( $r['pteam'] ) { $args['pt'] = $r['pteam']; } if ( $r['pos'] ) { $args['pos'] = $r['pos']; } if ( $full && $r['q'] !== '' ) { $args['q'] = $r['q']; } }
		if ( $r['view'] === 'teamplayers' ) { $args = array( 'view' => 'players', 'team' => $r['pteam'] ); }
		if ( $r['view'] === 'compare' ) { $ids = array_map( function ( $p ) { return $p['id']; }, $r['compare'] ); if ( ! $full ) { sort( $ids ); } $args = $ids ? array( 'compare' => implode( ',', $ids ) ) : ( $full ? array( 'compare' => 'none' ) : array( 'view' => 'players' ) ); }
		return $args;
	}

	public static function body_class( $classes ) {
		$classes[] = 'dbd-app';
		$classes[] = 'dbd-view-' . ( self::$route['view'] ?? 'hub' );
		return $classes;
	}

	/* ------------------------------------------------------------------ SEO */

	/** Title and description for the current route. */
	/** Title and description; a past season's pages say which season (SCOPE §44). */
	public static function seo() {
		list( $t, $d ) = self::seo_base();
		if ( DBD_Data::archived() && ( self::$route['view'] ?? '' ) !== 'season' && strpos( $t, (string) DBD_Data::season() ) === false ) { $t .= ' · ' . DBD_Data::season(); }
		return array( $t, $d );
	}

	private static function seo_base() {
		$r = self::$route; $site = 'Down by Down'; $season = DBD_Data::season();
		switch ( $r['view'] ?? 'hub' ) {
			case 'season':
				$sb = DBD_Season::super_bowl(); $T = DBD_Data::teams(); $w = $sb ? ( $sb['home_score'] > $sb['away_score'] ? $sb['home'] : $sb['away'] ) : null;
				return array( "The $season NFL season", "How the $season NFL season ended" . ( $w ? ': the ' . ( $T[ $w ]['name'] ?? $w ) . ' won the Super Bowl' : '' ) . '. The playoffs, final standings, who led, and every team, player and week, kept as it finished.' );
			case 'team':
				$t = DBD_Data::team( $r['team'] );
				if ( ( $r['tside'] ?? 'off' ) === 'def' ) { return array( $t['nick'] . ' defense', "How the {$t['name']} defense plays in $season: EPA and success allowed, pressure and blitz rate, where offenses find room by zone, run lane and position, situations, and every defender's season." ); }
				return array( $t['nick'] . ' offense play explorer', "Every {$t['name']} offensive snap of the $season season, drawn on the field. Filter by down, play type, field zone, formation, quarter and player." );
			case 'game':
				$g = $r['game']; $O = $r['side'] === 'away' ? $g['away'] : $g['home']; $D = $r['side'] === 'away' ? $g['home'] : $g['away'];
				$o = DBD_Data::team( $O )['nick']; $d = DBD_Data::team( $D )['nick'];
				return array( "$o offense vs $d defense, " . self::week_label( $g['week'] ), "How the $o offense matches up against the $d defense: EPA, success rate, explosive plays, third downs, red zone and where the ball goes, with the biggest edge and the risk." );
			case 'matchups':
				return array( self::week_label( $r['week'] ) . " matchups", "Every game of the $season NFL season with lines, totals, scores and a matchup read for each one." );
			case 'teams':
				return array( 'Pick a team', "All 32 NFL offenses ranked by EPA per play, each with a play explorer showing every snap of the $season season." );
			case 'player':
				$p = $r['player']; $t = DBD_Data::team( $p['team'] );
				return array( "{$p['name']}, {$t['nick']} {$p['pos']}", "Where the ball finds {$p['name']}: every target and carry of the $season season on the field, the game log, snap share, usage by down, quarter and score, and the latest injury report." );
			case 'compare':
				$names = array_map( function ( $p ) { return $p['name']; }, $r['compare'] );
				return array( $names ? implode( ' vs ', $names ) : 'Compare players', $names ? 'Side by side: where each grades against the league, where the ball finds each of them, and how their weeks line up.' : 'Pick two or three players of one position and see them side by side.' );
			case 'teamplayers':
			case 'players':
				if ( $r['pteam'] ) { $t = DBD_Data::team( $r['pteam'] ); return array( "{$t['nick']} players", "Every {$t['name']} skill player who has taken an offensive snap in $season, with snap share, targets, carries, yards, EPA and this week's injury status." ); }
				return array( 'Pick a player', "Every NFL skill player who has taken an offensive snap in $season: snap share, targets, carries, yards, touchdowns, EPA and injury status, by team and position." );
			case 'leaders':
				if ( $r['lboard'] !== '' && ( $B = DBD_Leaders::board( $r['lboard'] ) ) ) { return array( $B['title'] . ' leaders', "Every NFL player ranked by {$B['title']} in $season" . ( $B['sub'] !== '' ? " ({$B['sub']})" : '' ) . ', with position and minimum-usage filters.' ); }
				return array( ( DBD_Leaders::GROUPS[ $r['lgroup'] ] ?? 'League' ) . ' leaders', "Who leads the NFL in $season: fantasy points by position, target share, EPA, yards after the catch, sack rate and every other stat on Down by Down, the top ten on each board." );
			case 'week':
				$wl = DBD_Router::week_label( $r['week'] );
				return array( "$wl in review", "What happened in $wl of the $season NFL season: every game's win-probability swings, the biggest plays by WPA and EPA, the best offenses, defenses and players, fantasy leaders and the week's movers." );
			case 'myteam':
				return array( 'My team', 'Your fantasy lineup on one page: this week\'s matchup, injury status, snap trend and points a game for every player you follow.' );
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
		$r = self::$route;
		$url = self::url( self::args_of( $r ) );
		echo "\n" . '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $t . ' | Down by Down' ) . '">' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $d ) . '">' . "\n";
		$ck = DBD_Card::available() ? DBD_Card::key_for( $r ) : null;
		if ( $ck && DBD_Card::valid( $ck ) ) {  /* the share card: the player's (SCOPE §26), or since 1.19.0 the game's, the team's or the page's (§43) */
			$img = DBD_Card::url( $ck );
			echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n" . '<meta property="og:image:width" content="1200">' . "\n" . '<meta property="og:image:height" content="630">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n" . '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
		}
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
		echo '<meta property="og:type" content="website">' . "\n";
		if ( $r['view'] === 'notfound' ) { echo '<meta name="robots" content="noindex">' . "\n"; }
		if ( $r['view'] === 'game' && class_exists( 'DBD_Seo' ) ) { echo DBD_Seo::game_jsonld( $r['game'] ); }
		if ( $r['view'] === 'player' && class_exists( 'DBD_Seo' ) ) { echo DBD_Seo::player_jsonld( $r['player'] ); }
		if ( $r['view'] === 'players' && $r['q'] !== '' ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }
		if ( $r['view'] === 'compare' ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }
		if ( $r['view'] === 'myteam' ) { echo '<meta name="robots" content="noindex,follow">' . "\n"; }  /* personal */
	}

	/* ------------------------------------------------------------------ assets */

	public static function assets() {
		$r = self::$route;
		wp_enqueue_style( 'dbd-fonts', 'https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap', array(), null );
		wp_enqueue_style( 'dbd', DBD_URL . 'assets/dbd.css', array( 'dbd-fonts' ), DBD_VERSION );
		wp_enqueue_style( 'dbd-transitions', DBD_URL . 'assets/transitions.css', array( 'dbd' ), DBD_VERSION );
		wp_enqueue_script( 'dbd-site', DBD_URL . 'assets/dbd-site.js', array(), DBD_VERSION, true );
		/* links the scripts draw (dbdUrl() in dbd-site.js, SCOPE §43): the paths' base, and the slugs of the players this page links to */
		$ids = array();
		if ( in_array( $r['view'], array( 'team', 'teamplayers' ), true ) || $r['view'] === 'player' ) { foreach ( DBD_Data::team_players( $r['view'] === 'teamplayers' ? $r['pteam'] : $r['team'] ) as $x ) { if ( ! empty( $x['id'] ) ) { $ids[] = $x['id']; } } }
		if ( $r['view'] === 'compare' ) { foreach ( $r['compare'] as $x ) { $ids[] = $x['id']; } }
		if ( $r['view'] === 'myteam' ) { foreach ( $r['myteam'] as $x ) { $ids[] = $x['id']; } }
		$slugs = DBD_Urls::on() && $ids ? array_intersect_key( DBD_Urls::players()['id'], array_flip( $ids ) ) : array();
		$jsbase = DBD_Urls::on() && ! DBD_Data::archived() ? DBD_Urls::path_base() : self::url();  /* where the scripts' paths start: the root, or a past season's /2025/ */
		wp_add_inline_script( 'dbd-site', 'window.DBD_URLS=' . wp_json_encode( DBD_Urls::js_config( $jsbase, $slugs ) ) . ';', 'before' );
		if ( $r['view'] === 'team' ) {
			$def = ( $r['tside'] ?? 'off' ) === 'def'; $t = DBD_Data::team( $r['team'] ); $rel = $def ? 'plays/def/' . $r['team'] . '.json' : 'plays/' . $r['team'] . '.json';  /* SCOPE §39: the explorer on the plays the team defended */
			if ( $def ) { wp_enqueue_script( 'dbd-defense', DBD_URL . 'assets/defense.js', array(), DBD_VERSION, true ); }
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
				'side'      => $def ? 'def' : 'off',
			) );
		}
		if ( $r['view'] === 'player' ) {
			$p = $r['player']; $t = DBD_Data::team( $p['team'] ); $rel = 'plays/' . $p['team'] . '.json'; $rec = DBD_Data::player_record( $p['team'], $p['id'] );
			$roster = array();
			foreach ( DBD_Data::team_players( $p['team'] ) as $x ) {
				if ( empty( $x['id'] ) ) { continue; }
				$roster[ $x['id'] ] = array( 'name' => $x['name'] ?? '', 'short' => $x['short'] ?? '', 'pos' => $x['pos'] ?? '', 'num' => $x['num'] ?? null, 'photo' => $x['photo'] ?? null );
			}
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_script( 'dbd-player', DBD_URL . 'assets/player.js', array( 'dbd-marks' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-player', 'DBD_PLAYER', array(
				'id'      => $p['id'],
				'short'   => $rec['short'] ?? '',
				'name'    => $p['name'],
				'pos'     => $p['pos'],
				'num'     => $p['num'],
				'team'    => $p['team'],
				'teams'   => DBD_Data::teams(),
				'season'  => DBD_Data::season(),
				'dataUrl' => DBD_Data::url() . '/' . $rel . '?v=' . DBD_Data::file_version( $rel ),
				'accent'  => DBD_Marks::accent( $t ),
				'base'    => self::url(),
				'heads'   => DBD_Data::headshots(),
				'through' => DBD_Data::through_week(),
				'roster'  => $roster,  /* id => name, short, pos, num, photo: names and tiles for the connection panel */
			) );
		}
		if ( $r['view'] === 'compare' ) {
			$files = array(); $pl = array();
			foreach ( $r['compare'] as $row ) { $rel = 'plays/' . $row['team'] . '.json'; $files[ $row['team'] ] = DBD_Data::url() . '/' . $rel . '?v=' . DBD_Data::file_version( $rel ); $rec = DBD_Data::player_record( $row['team'], $row['id'] ); $pl[] = array( 'id' => $row['id'], 'short' => $rec['short'] ?? '', 'name' => $row['name'], 'team' => $row['team'], 'pos' => $row['pos'], 'num' => $row['num'] ); }
			$maxW = max( 1, (int) DBD_Data::through_week() ); $series = array(); $labels = array();
			foreach ( $r['compare'] as $row ) { $series[] = DBD_Grades::week_series( DBD_Data::player_record( $row['team'], $row['id'] ) ?: array(), $r['cgroup'], $maxW ); }
			for ( $wk = 1; $wk <= $maxW; $wk++ ) { $labels[] = 'Wk ' . $wk; }
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_style( 'dbd-chartist', DBD_URL . 'assets/vendor/chartist.css', array( 'dbd' ), '1.3.0' );
			wp_enqueue_script( 'dbd-chartist', DBD_URL . 'assets/vendor/chartist.umd.js', array(), '1.3.0', true );
			wp_enqueue_script( 'dbd-compare', DBD_URL . 'assets/compare.js', array( 'dbd-marks', 'dbd-chartist' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-compare', 'DBD_CMP', array( 'base' => self::url(), 'group' => $r['cgroup'], 'players' => $pl, 'files' => $files, 'teams' => DBD_Data::teams(), 'heads' => DBD_Data::headshots(), 'series' => $series, 'labels' => $labels, 'yds' => array( 'rec' => 'Receiving yards', 'rb' => 'Scrimmage yards', 'qb' => 'Passing yards' )[ DBD_Grades::kind( $r['cgroup'] ) ] ?? 'Yards' ) );
		}
		if ( $r['view'] === 'leaders' ) { wp_enqueue_script( 'dbd-leaders', DBD_URL . 'assets/leaders.js', array(), DBD_VERSION, true ); }
		if ( $r['view'] === 'week' ) { wp_enqueue_script( 'dbd-week', DBD_URL . 'assets/week.js', array(), DBD_VERSION, true ); }
		if ( $r['view'] === 'hub' ) {  /* SCOPE §41: the My team card. Always loaded: right after an update the player list may not have synced yet, and a cached page without the script would keep the card empty; hub.js does nothing if the list is missing */
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_script( 'dbd-hub', DBD_URL . 'assets/hub.js', array( 'dbd-marks' ), DBD_VERSION, true );
			$wk = DBD_Data::current_week();
			wp_localize_script( 'dbd-hub', 'DBD_HUB', array( 'mini' => DBD_Data::url() . '/players/mini.json?v=' . DBD_Data::file_version( 'players/mini.json' ), 'teams' => DBD_Data::teams(), 'heads' => DBD_Data::headshots(),
				'opp' => DBD_Hub::opponents( $wk ), 'week' => DBD_Router::week_label( $wk ), 'base' => self::url(), 'myteam' => self::url( array( 'view' => 'myteam' ) ), 'max' => DBD_MyTeam::MAX ) );
		}
		if ( $r['view'] === 'myteam' ) {
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_script( 'dbd-myteam', DBD_URL . 'assets/myteam.js', array( 'dbd-marks' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-myteam', 'DBD_MT', array( 'base' => self::url(), 'ids' => array_map( function ( $p ) { return $p['id']; }, $r['myteam'] ), 'max' => DBD_MyTeam::MAX, 'given' => self::q( 'myteam' ) !== '', 'teams' => DBD_Data::teams(), 'heads' => DBD_Data::headshots() ) );
		}
		if ( $r['view'] === 'game' ) { wp_enqueue_script( 'dbd-game', DBD_URL . 'assets/game.js', array(), DBD_VERSION, true ); }  /* the win probability scrubber */
		if ( $r['view'] === 'players' ) {
			wp_enqueue_script( 'dbd-marks', DBD_URL . 'assets/marks.js', array(), DBD_VERSION, true );
			wp_enqueue_script( 'dbd-players', DBD_URL . 'assets/players.js', array( 'dbd-marks' ), DBD_VERSION, true );
			wp_localize_script( 'dbd-players', 'DBD_PLIST', array( 'base' => self::url(), 'team' => $r['pteam'], 'pos' => $r['pos'], 'q' => $r['q'], 'teams' => DBD_Data::teams(), 'heads' => DBD_Data::headshots() ) );
		}
		if ( DBD_Data::logos() && wp_script_is( 'dbd-marks', 'enqueued' ) ) { wp_add_inline_script( 'dbd-marks', 'window.DBD_LOGOS_ON=true;', 'before' ); }  /* team logos (SCOPE §28) */
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
		echo '<div class="dbd dbd-view-' . esc_attr( $r['view'] ) . ( DBD_Data::archived() ? ' dbd-archived' : '' ) . '">';  // dbd-view-* so it can never collide with component classes like .dbd-teams or .dbd-game
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
	/** The season switcher (SCOPE §44): every season the site holds, each link the same page in that season. Nothing with one season. */
	public static function switcher() {
		$all = DBD_Data::seasons(); if ( count( $all ) < 2 || ! self::$route ) { return ''; }
		$now = (int) DBD_Data::season(); $cur = DBD_Data::current_season(); $items = '';
		foreach ( $all as $s ) {
			$sub = $s === $cur ? 'this season' : 'final';
			$items .= '<a href="' . esc_url( self::season_url( $s ) ) . '"' . ( $s === $now ? ' aria-current="true" class="on"' : '' ) . '><b>' . (int) $s . '</b><small>' . esc_html( $sub ) . '</small></a>';
		}
		return '<details class="dbd-season"><summary aria-label="Season">' . $now . ' <span aria-hidden="true">▾</span></summary><div class="dbd-season-menu">' . $items . '</div></details>';
	}

	public static function topbar( $crumbs ) {
		$out = '<div class="dbd-top"><div class="dbd-crumb"><a href="' . esc_url( self::url() ) . '" class="dbd-brand">DOWN X DOWN</a>';
		foreach ( $crumbs as $label => $href ) {
			$out .= ' <span>›</span> ' . ( $href ? '<a href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>' : '<b>' . esc_html( $label ) . '</b>' );
		}
		$out .= '</div><div class="dbd-r"><span class="dbd-upd">' . esc_html( DBD_Data::archived() ? 'Season over · final' : DBD_Data::updated_text() ) . '</span>' . self::switcher() . '</div></div>';
		if ( DBD_Data::archived() ) {  /* SCOPE §44: the archive banner */
			$cur = DBD_Data::current_season();
			$out .= '<div class="dbd-arch"><span><b>' . (int) DBD_Data::season() . ' season · final</b> Every snap of the ' . (int) DBD_Data::season() . ' season, kept as it ended.</span><a href="' . esc_url( self::season_url( $cur ) ) . '">Back to ' . (int) $cur . ' →</a></div>';
		}
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
