<?php
/**
 * Search: an XML sitemap for every app URL (WordPress cannot discover query-string routes on its own),
 * and JSON-LD SportsEvent data on matchup pages. Since 2.7.0 also the site-wide pieces: the home page's description, share tags and
 * site-name data when the home is a theme page, no author pages in the sitemap, no Crawl-delay in robots.txt, and pinch-zoom on phones.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Seo {

	const OPT_HOME_DESC = 'dbd_home_desc';
	const HOME_DESC = 'Free NFL play-by-play charts: every snap drawn on the field, target maps, EPA by team, a matchup read for every game and fantasy with the why. Updated three times a day.';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_sitemap' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'drop_users' ), 10, 2 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots' ), PHP_INT_MAX );
		add_action( 'wp_head', array( __CLASS__, 'viewport' ), 0 );
		add_action( 'wp_head', array( __CLASS__, 'home_head' ), 3 );
	}

	/** An SEO plugin writes the head tags itself; the plugin then leaves the home page's to it. */
	public static function seo_plugin() {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' );
	}

	/** The author archive is a thin page that shows the login name: out of the sitemap. */
	public static function drop_users( $provider, $name ) { return $name === 'users' ? false : $provider; }

	/** No Crawl-delay: Bing honors it and would crawl the site's thousands of pages slowly (Google ignores it). */
	public static function robots( $txt ) { return (string) preg_replace( '/^\s*Crawl-delay:.*(\r?\n)?/mi', '', (string) $txt ); }

	/**
	 * Divi prints a viewport that turns off pinch-zoom (maximum-scale=1, user-scalable=0), an accessibility failure. Its tag goes and a
	 * plain one takes its place. Only when Divi's function is hooked, so another theme's viewport is never doubled.
	 */
	public static function viewport() {
		if ( has_action( 'wp_head', 'et_add_viewport_meta' ) === false ) { return; }
		remove_action( 'wp_head', 'et_add_viewport_meta' );
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
	}

	/** The home page when it is a theme page (the app lives on /hub/): description, share tags with the hub's card, and the site's name. */
	public static function home_head() {
		if ( ! is_front_page() || ( class_exists( 'DBD_Router' ) && DBD_Router::$active ) ) { return; }  /* the app as the front page writes its own */
		$name = get_bloginfo( 'name' ); $url = home_url( '/' );
		$ld = array( '@context' => 'https://schema.org', '@graph' => array(
			array( '@type' => 'WebSite', '@id' => $url . '#website', 'name' => $name, 'url' => $url ),
			array( '@type' => 'Organization', '@id' => $url . '#org', 'name' => $name, 'url' => $url ) + ( get_site_icon_url( 512 ) ? array( 'logo' => get_site_icon_url( 512 ) ) : array() ),
		) );
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
		if ( self::seo_plugin() ) { return; }
		$d = trim( (string) get_option( self::OPT_HOME_DESC, '' ) ); if ( $d === '' ) { $d = self::HOME_DESC; }
		$t = wp_get_document_title();
		echo '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $t ) . '">' . "\n" . '<meta property="og:description" content="' . esc_attr( $d ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n" . '<meta property="og:type" content="website">' . "\n" . '<meta property="og:site_name" content="' . esc_attr( $name ) . '">' . "\n";
		if ( class_exists( 'DBD_Pack' ) ) { echo DBD_Pack::twitter_site_tag(); }  /* SCOPE §72 */
		if ( class_exists( 'DBD_Card' ) && DBD_Card::available() && DBD_Data::has_data() ) {
			$img = DBD_Card::url( 'page-hub' );
			echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n" . '<meta property="og:image:width" content="1200">' . "\n" . '<meta property="og:image:height" content="630">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n" . '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
		}
	}

	public static function register_sitemap() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) ) { return; }
		wp_register_sitemap_provider( 'downbydown', new DBD_Sitemap_Provider() );
	}

	/** The page that carries the shortcode: the remembered app page (DBD_Urls), else a search cached for a day. */
	public static function app_url() {
		$id = class_exists( 'DBD_Urls' ) ? DBD_Urls::page_id() : 0;  /* follows the app when it moves to its own page (/hub/) (SCOPE §45) */
		if ( $id ) { return get_permalink( $id ); }
		$url = get_transient( 'dbd_app_url' );
		if ( $url ) { return $url; }
		$q = new WP_Query( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 's' => '[down_by_down', 'fields' => 'ids', 'no_found_rows' => true ) );
		$url = $q->posts ? get_permalink( $q->posts[0] ) : home_url( '/' );
		set_transient( 'dbd_app_url', $url, DAY_IN_SECONDS );
		return $url;
	}

	/** Every app URL: hub, matchups index per week, team picker, 32 team pages, every game (both sides). */
	public static function urls() {
		$u    = function ( $args = array() ) { return DBD_Router::url( $args ); };  /* readable paths when the site uses them (SCOPE §43) */
		$list = array( array( 'loc' => $u(), 'changefreq' => 'daily', 'priority' => 1.0 ), array( 'loc' => $u( array( 'view' => 'teams' ) ), 'changefreq' => 'daily' ), array( 'loc' => $u( array( 'view' => 'matchups' ) ), 'changefreq' => 'daily' ) );
		foreach ( DBD_Data::weeks() as $w ) { $list[] = array( 'loc' => $u( array( 'view' => 'matchups', 'week' => $w ) ), 'changefreq' => 'weekly' ); }
		if ( ! DBD_Data::archived() ) foreach ( array( 'draft', 'combine' ) as $bk ) { foreach ( DBD_Boards::years( $bk ) as $i => $y ) { $list[] = array( 'loc' => $u( array( 'view' => $bk, 'y' => $y ) ), 'changefreq' => $i < 2 ? 'weekly' : 'yearly', 'priority' => $i < 2 ? 0.7 : 0.4 ) + ( $i < 2 ? array() : array( 'lastmod' => null ) ); } }  /* the season boards (SCOPE §63) */
		/* the week in review and the leaders (2.7.0): a week page once a game in it is final, the leaders index and each group's boards */
		$wks = class_exists( 'DBD_Week' ) ? DBD_Week::weeks() : array();
		if ( $wks ) { $list[] = array( 'loc' => $u( array( 'view' => 'week' ) ), 'changefreq' => 'daily', 'priority' => 0.8 ); }
		foreach ( $wks as $w ) { $list[] = array( 'loc' => $u( array( 'view' => 'week', 'week' => $w ) ), 'changefreq' => 'weekly', 'priority' => 0.7 ); }
		if ( DBD_Data::has_players() ) {
			$list[] = array( 'loc' => $u( array( 'view' => 'leaders' ) ), 'changefreq' => 'daily', 'priority' => 0.8 );
			foreach ( array_keys( DBD_Leaders::GROUPS ) as $g ) { if ( $g !== 'fan' ) { $list[] = array( 'loc' => $u( array( 'view' => 'leaders', 'g' => $g ) ), 'changefreq' => 'daily', 'priority' => 0.7 ); } }
		}
		foreach ( array_keys( DBD_Data::teams() ) as $a ) { $list[] = array( 'loc' => $u( array( 'team' => $a ) ), 'changefreq' => 'daily', 'priority' => 0.8 ); }
		if ( DBD_Data::has_players() ) {
			$list[] = array( 'loc' => $u( array( 'view' => 'players' ) ), 'changefreq' => 'daily', 'priority' => 0.8 );
			foreach ( array_keys( DBD_Data::teams() ) as $a ) { $list[] = array( 'loc' => $u( array( 'view' => 'players', 'team' => $a ) ), 'changefreq' => 'weekly' ); }
			foreach ( DBD_Data::player_index() as $p ) { $list[] = array( 'loc' => $u( array( 'player' => $p['id'] ) ), 'changefreq' => 'weekly', 'priority' => 0.6 ); }
		}
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $games ) {
			foreach ( $games as $g ) {
				$gm = DBD_Router::is_final( $g ) && ! empty( $g['date'] ) && strtotime( $g['date'] ) ? array( 'lastmod' => gmdate( 'Y-m-d', strtotime( $g['date'] . ' +1 day' ) ) ) : array();  /* a final game settles the day after (SCOPE §64) */
				$list[] = array( 'loc' => $u( array( 'game' => $g['id'] ) ), 'changefreq' => 'weekly', 'priority' => 0.7 ) + $gm;
				$list[] = array( 'loc' => $u( array( 'game' => $g['id'], 'side' => 'home' ) ), 'changefreq' => 'weekly', 'priority' => 0.6 ) + $gm;
			}
		}
		/* lastmod (SCOPE §64): this season's pages change with every data build, so they carry its time and Google recrawls what changed
		   overnight. A final game keeps its own date; an archived season and the old board years carry none. */
		$m = DBD_Data::manifest(); $bt = ! DBD_Data::archived() && ! empty( $m['built_at'] ) && strtotime( $m['built_at'] ) ? gmdate( 'c', strtotime( $m['built_at'] ) ) : null;
		foreach ( $list as $i => $e ) {
			if ( ! array_key_exists( 'lastmod', $e ) && $bt ) { $list[ $i ]['lastmod'] = $bt; }
			if ( array_key_exists( 'lastmod', $list[ $i ] ) && ( $list[ $i ]['lastmod'] === null || DBD_Data::archived() ) ) { unset( $list[ $i ]['lastmod'] ); }
		}
		return array_values( $list );
	}

	/** JSON-LD for a player page: the player as a Person on a SportsTeam. */
	public static function player_jsonld( $p ) {
		$t  = DBD_Data::team( $p['team'] );
		$ld = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Person',
			'name'        => $p['name'],
			'jobTitle'    => 'American football player',
			'affiliation' => array( '@type' => 'SportsTeam', 'name' => $t['name'] ?? $p['team'] ),
			'url'         => DBD_Router::url( array( 'player' => $p['id'] ) ),
		);
		$rec = DBD_Data::player_record( $p['team'], $p['id'] );  /* his Wikipedia article (SCOPE §62), so search ties the page to the right person (§64) */
		if ( ! empty( $rec['wk'][0] ) ) { $ld['sameAs'] = array( 'https://en.wikipedia.org/wiki/' . rawurlencode( str_replace( ' ', '_', (string) $rec['wk'][0] ) ) ); }
		return '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/**
	 * The Related panel at the end of a player's page, his and a kicker's alike (SCOPE §64): his next game, his team's offense and players,
	 * his position's leaders, and his draft class and combine when the boards have them.
	 */
	public static function related_panel( $team, $pos, $bf ) {
		$t = DBD_Data::team( $team ) ?: array( 'nick' => $team ); $rel = array();
		if ( ! DBD_Data::archived() && ( $nx = DBD_Fantasy::next_game( $team ) ) && ( $ng = DBD_Data::team_game( $team, $nx['wk'] ) ) ) {
			$on = DBD_Data::team( $nx['opp'] ); $home = $ng['home'] === $team;
			$rel[] = array( DBD_Router::url( $home ? array( 'game' => $ng['id'], 'side' => 'home' ) : array( 'game' => $ng['id'] ) ), 'Next game: ' . ( $home ? 'vs ' : 'at ' ) . ( $on['nick'] ?? $nx['opp'] ) . ', ' . DBD_Router::week_label( $nx['wk'] ) );
		}
		$rel[] = array( DBD_Router::url( array( 'team' => $team ) ), $t['nick'] . ' offense' );
		$rel[] = array( DBD_Router::url( array( 'view' => 'players', 'team' => $team ) ), $t['nick'] . ' players' );
		$lg = array( 'QB' => 'qb', 'RB' => 'rb', 'FB' => 'rb', 'WR' => 'wr', 'TE' => 'te', 'K' => 'k' )[ (string) $pos ] ?? '';
		if ( $lg ) { $rel[] = array( DBD_Router::url( array( 'view' => 'leaders', 'g' => $lg ) ), DBD_Leaders::GROUPS[ $lg ] . ' leaders' ); }
		$dy = (int) ( $bf['dr']['y'] ?? 0 ); if ( $dy && in_array( $dy, DBD_Boards::years( 'draft' ), true ) ) { $rel[] = array( DBD_Router::url( array( 'view' => 'draft', 'y' => $dy ) ), 'The ' . $dy . ' draft class' ); }
		$cy = (int) ( $bf['cb']['y'] ?? 0 ); if ( $cy && in_array( $cy, DBD_Boards::years( 'combine' ), true ) ) { $rel[] = array( DBD_Router::url( array( 'view' => 'combine', 'y' => $cy ) ), 'The ' . $cy . ' combine' ); }
		$h = '<section class="dbd-panel dbd-related"><div class="dbd-ph"><span class="dbd-lab"><b>Related</b></span></div><div class="dbd-pb dbd-pills">';
		foreach ( $rel as $x ) { $h .= '<a class="dbd-pill" href="' . esc_url( $x[0] ) . '">' . esc_html( $x[1] ) . '</a>'; }
		return $h . '</div></section>';
	}

	/** JSON-LD for a draft or combine board (SCOPE §64): the board as an ItemList, each pick or player a Person, linked when he has a page here. */
	public static function board_jsonld( $kind, $y ) {
		$B = DBD_Boards::get( $kind, $y ); if ( ! $B || empty( $B['rows'] ) ) { return ''; }
		/* the head runs before the view resets the season, and a board's player pages are this season's: build in it, then put back (Greptile, PR #117) */
		$prev = DBD_Data::archived() ? DBD_Data::season() : null; DBD_Data::use_season( null );
		$out = self::board_ld( $kind, $y, $B['rows'] ); DBD_Data::use_season( $prev );
		return $out;
	}

	private static function board_ld( $kind, $y, $R ) {
		if ( $kind === 'draft' ) { usort( $R, function ( $a, $b ) { return (int) $a['p'] <=> (int) $b['p']; } ); }
		$items = array();
		foreach ( array_values( $R ) as $i => $x ) {
			$who = array( '@type' => 'Person', 'name' => (string) $x['n'] );
			if ( $kind === 'draft' ) {
				$t = $x['t'] === $x['f'] ? DBD_Data::team( (string) $x['f'] ) : null; if ( $t ) {  /* only when the team then is the team now (the 1983 Colts are not today's BAL) */ $who['affiliation'] = array( '@type' => 'SportsTeam', 'name' => $t['name'] ); }
				if ( ! empty( $x['pg'] ) && ! empty( $x['id'] ) ) { $who['url'] = DBD_Router::url( array( 'player' => $x['id'] ) ); }
			}
			$who['description'] = trim( (string) $x['pos'] . ( (string) $x['sc'] !== '' ? ', ' . $x['sc'] : '' ), ', ' );
			$items[] = array( '@type' => 'ListItem', 'position' => $kind === 'draft' ? (int) $x['p'] : $i + 1, 'item' => $who );
		}
		$ld = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => $kind === 'draft' ? $y . ' NFL Draft' : $y . ' NFL Combine',
			'url'             => DBD_Router::url( array( 'view' => $kind, 'y' => $y ) ),
			'itemListOrder'   => $kind === 'draft' ? 'https://schema.org/ItemListOrderAscending' : 'https://schema.org/ItemListUnordered',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		);
		return '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/** JSON-LD for a matchup page: the game as a SportsEvent. */
	public static function game_jsonld( $g ) {
		$k  = DBD_Data::kickoff( $g );
		$h  = DBD_Data::team( $g['home'] ); $a = DBD_Data::team( $g['away'] );
		$ld = array(
			'@context'  => 'https://schema.org',
			'@type'     => 'SportsEvent',
			'name'      => $a['name'] . ' at ' . $h['name'],
			'sport'     => 'American football',
			'startDate' => $k['utc'],
			'eventStatus' => 'https://schema.org/EventScheduled',
			'homeTeam'  => array( '@type' => 'SportsTeam', 'name' => $h['name'] ),
			'awayTeam'  => array( '@type' => 'SportsTeam', 'name' => $a['name'] ),
			'url'       => DBD_Router::url( array( 'game' => $g['id'] ) ),
		);
		if ( ! empty( $g['stadium'] ) ) { $ld['location'] = array( '@type' => 'Place', 'name' => $g['stadium'] ); }
		return '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
}

class DBD_Sitemap_Provider extends WP_Sitemaps_Provider {
	public function __construct() {
		$this->name        = 'downbydown';
		$this->object_type = 'downbydown';
	}
	public function get_url_list( $page_num, $object_subtype = '' ) {
		if ( $page_num > 1 || ! DBD_Data::has_data() ) { return array(); }
		$list = DBD_Seo::urls();
		foreach ( DBD_Data::archived_seasons() as $s ) {  /* archived seasons (SCOPE §44): real pages that don't change, listed at lower priority */
			DBD_Data::use_season( $s );
			foreach ( DBD_Seo::urls() as $u ) { if ( strpos( $u['loc'], 'side=home' ) !== false || strpos( $u['loc'], '/game/' ) === false && isset( $u['priority'] ) && $u['priority'] < 0.6 ) { continue; } $u['priority'] = round( ( $u['priority'] ?? 0.5 ) * 0.5, 2 ); $u['changefreq'] = 'yearly'; $list[] = $u; }
		}
		DBD_Data::use_season( null );
		return $list;
	}
	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
