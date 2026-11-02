<?php
/**
 * Readable URLs (SCOPE §43): /team/min/, /player/justin-jefferson/, /game/2026-week-8-vikings-at-lions/ and the rest.
 *
 * One rewrite rule sends every app path to the page that holds the shortcode, with the path in the dbd_path query var.
 * parse() turns that path back into the same values the router has always read from the query string, so the views
 * do not change; build() turns the router's link arguments into a path. The old query-string links keep working:
 * DBD_Router::detect() answers them with a 301 to the path. Share cards stay on ?card=, since some hosts serve any
 * .png path as a static file and never reach WordPress.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Urls {
	const OPT      = 'dbd_pretty';        /* the admin setting, on by default */
	const OPT_PAGE = 'dbd_app_page';      /* the page that holds the shortcode */
	const OPT_SIG  = 'dbd_rewrite_sig';   /* what the rewrite rules were last flushed for */
	const BASES    = 'team|game|player|compare|players|leaders|week|matchups|teams|my-team';
	const ROUTE_KEYS = array( 'team', 'game', 'player', 'compare', 'view', 'side', 'week', 'g' );  /* a path decides these; the query string can't override them */

	private static $on = null; private static $page = null; private static $players = null; private static $games = null;

	public static function init() {
		add_filter( 'query_vars', function ( $v ) { $v[] = 'dbd_path'; $v[] = 'dbd_season'; return $v; } );
		add_action( 'init', array( __CLASS__, 'rules' ), 20 );
		/* the app is usually the front page: without this, WordPress sends /team/min/ back to / */
		add_filter( 'redirect_canonical', function ( $to ) { return ( get_query_var( 'dbd_path' ) || get_query_var( 'dbd_season' ) ) ? false : $to; } );
		add_action( 'save_post_page', function ( $id, $post ) { if ( $post && $post->post_status === 'publish' && self::holds_app( $post ) ) { update_option( self::OPT_PAGE, (int) $id ); } }, 10, 2 );
	}

	/** Forget the slug maps: the season showing changed (DBD_Data::use_season). */
	public static function reset() { self::$players = null; self::$games = null; }

	/** Paths are on when the site has pretty permalinks, the setting is on and the app page is known. */
	public static function on() {
		if ( self::$on === null ) { self::$on = (bool) get_option( 'permalink_structure' ) && (bool) get_option( self::OPT, true ) && self::page_id() > 0; }
		return self::$on;
	}

	public static function holds_app( $post ) {
		$c = (string) ( $post->post_content ?? '' );
		return has_shortcode( $c, 'down_by_down' ) || has_shortcode( $c, 'play_explorer' );
	}

	/** The published page that holds the shortcode: remembered, checked, and looked up again if it moved. */
	public static function page_id() {
		if ( self::$page !== null ) { return self::$page; }
		$id = (int) get_option( self::OPT_PAGE );
		if ( $id && function_exists( 'get_post' ) ) { $p = get_post( $id ); if ( ! $p || $p->post_status !== 'publish' || ! self::holds_app( $p ) ) { $id = 0; } }
		if ( ! $id && class_exists( 'WP_Query' ) ) {
			$q = new WP_Query( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 's' => '[down_by_down', 'fields' => 'ids', 'no_found_rows' => true ) );
			$id = $q->posts ? (int) $q->posts[0] : 0;
			if ( $id ) { update_option( self::OPT_PAGE, $id ); }
		}
		return self::$page = $id;
	}

	/** The app page's path under home, with a trailing slash; empty when it is the front page. */
	public static function prefix() {
		$id = self::page_id(); if ( ! $id ) { return ''; }
		if ( get_option( 'show_on_front' ) === 'page' && (int) get_option( 'page_on_front' ) === $id ) { return ''; }
		$path = trim( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ), '/' );
		$home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( $home !== '' && strpos( $path, $home ) === 0 ) { $path = trim( substr( $path, strlen( $home ) ), '/' ); }
		return $path === '' ? '' : $path . '/';
	}

	/** The rewrite rule, and a flush whenever the version, the setting, the page or the permalink structure changed. */
	public static function rules() {
		$on = self::on(); $id = self::page_id(); $pre = $on ? self::prefix() : '';
		if ( $on ) {
			$q = preg_quote( $pre, '#' );
			/* a past season (SCOPE §44): /2025/ is its season page, /2025/team/min/ and the rest its pages */
			add_rewrite_rule( '^' . $q . '((?:19|20)\d\d)/(' . self::BASES . ')(?:/(.*?))?/?$', 'index.php?page_id=' . $id . '&dbd_season=$matches[1]&dbd_path=$matches[2]/$matches[3]', 'top' );
			add_rewrite_rule( '^' . $q . '((?:19|20)\d\d)/?$', 'index.php?page_id=' . $id . '&dbd_season=$matches[1]', 'top' );
			add_rewrite_rule( '^' . $q . '(' . self::BASES . ')(?:/(.*?))?/?$', 'index.php?page_id=' . $id . '&dbd_path=$matches[1]/$matches[2]', 'top' );
		}
		$sig = md5( implode( '|', array( DBD_VERSION, $on ? 1 : 0, $id, $pre, (string) get_option( 'permalink_structure' ) ) ) );
		if ( get_option( self::OPT_SIG ) !== $sig ) { flush_rewrite_rules( false ); update_option( self::OPT_SIG, $sig ); }
	}

	/* ------------------------------------------------------------------ slugs */

	/** The slug rule the build uses (tools/build_data.py) and dbdSlug() in dbd-site.js: accents, apostrophes and periods dropped, other punctuation a hyphen. */
	public static function slugify( $s ) {
		$s = (string) $s;
		if ( function_exists( 'remove_accents' ) ) { $s = remove_accents( $s ); }
		elseif ( function_exists( 'iconv' ) ) { $t = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $s ); if ( $t !== false ) { $s = $t; } }
		$s = strtolower( preg_replace( "/['\x{2019}.]/u", '', $s ) ?? $s );
		$s = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $s ), '-' );
		return $s !== '' ? $s : 'player';
	}

	/** id => slug and slug => id for every indexed player: the build's slug when the index carries one, the same rule otherwise. */
	public static function players() {
		if ( self::$players !== null ) { return self::$players; }
		$rows = DBD_Data::player_index(); $n = array(); $m = array( 'id' => array(), 'slug' => array() );
		foreach ( $rows as $p ) { $s = self::slugify( $p['name'] ?? '' ); $n[ $s ] = ( $n[ $s ] ?? 0 ) + 1; }
		foreach ( $rows as $p ) {
			$s = ! empty( $p['slug'] ) ? (string) $p['slug'] : self::slugify( $p['name'] ?? '' );
			if ( empty( $p['slug'] ) && $n[ $s ] > 1 ) { $s .= '-' . strtolower( (string) ( $p['pos'] ?? '' ) ) . '-' . substr( (string) $p['id'], -4 ); }
			$m['id'][ $p['id'] ] = $s; $m['slug'][ $s ] = $p['id'];
		}
		return self::$players = $m;
	}

	public static function player_slug( $id ) { return self::players()['id'][ $id ] ?? null; }

	/** 2026-week-8-vikings-at-lions: season and week from the game id, both nicknames. */
	public static function game_slug( $g ) {
		$T = DBD_Data::teams(); $id = (string) ( $g['id'] ?? '' );
		$season = (int) substr( $id, 0, 4 ); $week = (int) ( $g['week'] ?? substr( $id, 5, 2 ) );
		return $season . '-week-' . $week . '-' . self::slugify( $T[ $g['away'] ]['nick'] ?? $g['away'] ) . '-at-' . self::slugify( $T[ $g['home'] ]['nick'] ?? $g['home'] );
	}

	public static function games() {
		if ( self::$games !== null ) { return self::$games; }
		$m = array();
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( (array) $gl as $g ) { if ( ! empty( $g['id'] ) ) { $m[ self::game_slug( $g ) ] = $g['id']; } } }
		return self::$games = $m;
	}

	/* ------------------------------------------------------------------ path <-> route values */

	/** A leading season segment: '2025/team/min/' -> array( 2025, 'team/min/' ); none -> array( 0, $path ). */
	public static function split_season( $path ) {
		$p = ltrim( (string) $path, '/' );
		return preg_match( '#^((?:19|20)\d\d)(?:/|$)(.*)$#', $p, $m ) ? array( (int) $m[1], $m[2] ) : array( 0, $p );
	}

	/** The season a request names: the path's prefix, ?season=, or a game's id or slug (a game link never changes season). */
	public static function season_of( $season_var, $path, $get ) {
		if ( (int) $season_var ) { return (int) $season_var; }
		list( $s ) = self::split_season( $path ); if ( $s ) { return $s; }
		if ( ! empty( $get['season'] ) && preg_match( '/^\d{4}$/', (string) $get['season'] ) ) { return (int) $get['season']; }
		if ( preg_match( '#^/?game/((?:19|20)\d\d)-week-#', (string) $path, $m ) ) { return (int) $m[1]; }
		if ( ! empty( $get['game'] ) && preg_match( '/^((?:19|20)\d\d)_/', (string) $get['game'], $m ) ) { return (int) $m[1]; }
		return 0;
	}

	/** A path (without the app page's prefix) to the values the router reads. Anything it can't place is array( '__nf' => 1 ). */
	public static function parse( $path ) {
		$seg = array_values( array_filter( explode( '/', strtolower( trim( (string) $path, '/' ) ) ), 'strlen' ) );
		$b = (string) array_shift( $seg ); $nf = array( '__nf' => 1 ); $n = count( $seg ); $T = DBD_Data::teams();
		if ( $b === '' ) { return array(); }  /* the app page itself: the hub */
		switch ( $b ) {
			case 'team':
				$t = strtoupper( $seg[0] ?? '' ); if ( ! isset( $T[ $t ] ) || $n > 2 ) { return $nf; }
				if ( $n === 1 ) { return array( 'team' => $t ); }
				if ( $seg[1] === 'defense' ) { return array( 'team' => $t, 'side' => 'def' ); }
				if ( $seg[1] === 'players' ) { return array( 'view' => 'players', 'team' => $t ); }
				return $nf;
			case 'game':
				$id = self::games()[ $seg[0] ?? '' ] ?? null; if ( ! $id || $n > 2 ) { return $nf; }
				$out = array( 'game' => $id );
				if ( $n === 2 ) {
					$g = DBD_Data::game( $id );
					if ( $seg[1] === self::slugify( $T[ $g['home'] ]['nick'] ?? $g['home'] ) ) { $out['side'] = 'home'; }
					elseif ( $seg[1] !== self::slugify( $T[ $g['away'] ]['nick'] ?? $g['away'] ) ) { return $nf; }
				}
				return $out;
			case 'player':
				if ( $n !== 1 ) { return $nf; }
				$id = self::players()['slug'][ $seg[0] ] ?? null;
				return $id ? array( 'player' => $id ) : array( 'view' => 'players', 'q' => str_replace( '-', ' ', $seg[0] ) );  /* a slug that no longer exists searches for the name */
			case 'compare':
				$ids = array(); foreach ( array_slice( $seg, 0, 3 ) as $s ) { $id = self::players()['slug'][ $s ] ?? null; if ( $id ) { $ids[] = $id; } }
				return array( 'compare' => $ids ? implode( ',', $ids ) : 'none' );
			case 'players':  return $n ? $nf : array( 'view' => 'players' );
			case 'teams':    return $n ? $nf : array( 'view' => 'teams' );
			case 'my-team':  return $n ? $nf : array( 'view' => 'myteam' );
			case 'leaders':
				if ( ! $n ) { return array( 'view' => 'leaders' ); }
				return ( $n === 1 && isset( DBD_Leaders::GROUPS[ $seg[0] ] ) ) ? array( 'view' => 'leaders', 'g' => $seg[0] ) : $nf;
			case 'week':
				if ( ! $n ) { return array( 'view' => 'week' ); }
				return ( $n === 1 && ctype_digit( $seg[0] ) ) ? array( 'view' => 'week', 'week' => $seg[0] ) : $nf;
			case 'matchups':
				if ( ! $n ) { return array( 'view' => 'matchups' ); }
				return ( $n === 1 && preg_match( '/^week-(\d{1,2})$/', $seg[0], $mm ) ) ? array( 'view' => 'matchups', 'week' => $mm[1] ) : $nf;
		}
		return $nf;
	}

	/** Link arguments to a path URL under $base, the arguments a path doesn't carry left as a query string; null when there is no path for them. */
	public static function build( $base, $args ) {
		$a = $args; $T = DBD_Data::teams(); $p = null;
		if ( isset( $a['card'] ) ) { return null; }
		if ( isset( $a['player'] ) ) {
			$s = self::player_slug( $a['player'] ); if ( ! $s ) { return null; }
			$p = 'player/' . $s . '/'; unset( $a['player'] );
		} elseif ( isset( $a['compare'] ) ) {
			$ss = array();
			foreach ( explode( ',', (string) $a['compare'] ) as $id ) { if ( $id === 'none' || $id === '' ) { continue; } $s = self::player_slug( $id ); if ( ! $s ) { return null; } $ss[] = $s; }
			$p = 'compare/' . ( $ss ? implode( '/', $ss ) . '/' : '' ); unset( $a['compare'] );
		} elseif ( isset( $a['game'] ) ) {
			$g = DBD_Data::game( $a['game'] ); if ( ! $g ) { return null; }
			$p = 'game/' . self::game_slug( $g ) . '/' . ( ( $a['side'] ?? '' ) === 'home' ? self::slugify( $T[ $g['home'] ]['nick'] ?? $g['home'] ) . '/' : '' );
			unset( $a['game'], $a['side'] );
		} elseif ( isset( $a['team'] ) && ! isset( $a['view'] ) ) {
			$t = strtoupper( (string) $a['team'] ); if ( ! isset( $T[ $t ] ) ) { return null; }
			$p = 'team/' . strtolower( $t ) . '/' . ( ( $a['side'] ?? '' ) === 'def' ? 'defense/' : '' ); unset( $a['team'], $a['side'] );
		} elseif ( isset( $a['view'] ) ) {
			$v = $a['view'];
			if ( $v === 'players' && ! empty( $a['team'] ) ) {
				$t = strtoupper( (string) $a['team'] ); if ( ! isset( $T[ $t ] ) ) { return null; }
				$p = 'team/' . strtolower( $t ) . '/players/'; unset( $a['team'] );
			} elseif ( $v === 'players' ) { $p = 'players/'; }
			elseif ( $v === 'teams' ) { $p = 'teams/'; }
			elseif ( $v === 'myteam' ) { $p = 'my-team/'; }
			elseif ( $v === 'leaders' ) { $g = (string) ( $a['g'] ?? '' ); $p = 'leaders/' . ( $g !== '' && $g !== 'fan' && isset( DBD_Leaders::GROUPS[ $g ] ) ? $g . '/' : '' ); unset( $a['g'] ); }
			elseif ( $v === 'week' ) { $p = 'week/' . ( ! empty( $a['week'] ) ? (int) $a['week'] . '/' : '' ); unset( $a['week'] ); }
			elseif ( $v === 'matchups' ) { $p = 'matchups/' . ( ! empty( $a['week'] ) ? 'week-' . (int) $a['week'] . '/' : '' ); unset( $a['week'] ); }
			else { return null; }
			unset( $a['view'] );
		} elseif ( isset( $a['myteam'] ) ) {
			$p = 'my-team/';  /* the lineup stays in the query string, so a shared team still travels in the link */
		} else {
			return null;
		}
		$url = trailingslashit( $base ) . $p;
		return $a ? add_query_arg( array_map( 'rawurlencode', $a ), $url ) : $url;
	}

	/** What the scripts need to draw the same links (dbdUrl() in dbd-site.js). $slugs: id => slug for the players a page links to. */
	public static function js_config( $base, $slugs = array() ) {
		/* a query-string base (?season=2025 on a plain-permalink site) keeps its query intact; dbdUrl() appends with & (Greptile, PR #56) */
		return array( 'base' => strpos( (string) $base, '?' ) === false ? trailingslashit( $base ) : $base, 'pretty' => self::on(), 'slugs' => (object) $slugs );
	}
}
