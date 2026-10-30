<?php
/**
 * Search: an XML sitemap for every app URL (WordPress cannot discover query-string routes on its own),
 * and JSON-LD SportsEvent data on matchup pages.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Seo {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_sitemap' ) );
	}

	public static function register_sitemap() {
		if ( ! function_exists( 'wp_register_sitemap_provider' ) ) { return; }
		wp_register_sitemap_provider( 'downbydown', new DBD_Sitemap_Provider() );
	}

	/** The page that carries the shortcode, found once and cached for a day. */
	public static function app_url() {
		$url = get_transient( 'dbd_app_url' );
		if ( $url ) { return $url; }
		$q = new WP_Query( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => 1, 's' => '[down_by_down', 'fields' => 'ids', 'no_found_rows' => true ) );
		$url = $q->posts ? get_permalink( $q->posts[0] ) : home_url( '/' );
		set_transient( 'dbd_app_url', $url, DAY_IN_SECONDS );
		return $url;
	}

	/** Every app URL: hub, matchups index per week, team picker, 32 team pages, every game (both sides). */
	public static function urls() {
		$base = self::app_url();
		$u    = function ( $args = array() ) use ( $base ) { return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $base ) : $base; };
		$list = array( array( 'loc' => $u(), 'changefreq' => 'daily', 'priority' => 1.0 ), array( 'loc' => $u( array( 'view' => 'teams' ) ), 'changefreq' => 'daily' ), array( 'loc' => $u( array( 'view' => 'matchups' ) ), 'changefreq' => 'daily' ) );
		foreach ( DBD_Data::weeks() as $w ) { $list[] = array( 'loc' => $u( array( 'view' => 'matchups', 'week' => $w ) ), 'changefreq' => 'weekly' ); }
		foreach ( array_keys( DBD_Data::teams() ) as $a ) { $list[] = array( 'loc' => $u( array( 'team' => $a ) ), 'changefreq' => 'daily', 'priority' => 0.8 ); }
		if ( DBD_Data::has_players() ) {
			$list[] = array( 'loc' => $u( array( 'view' => 'players' ) ), 'changefreq' => 'daily', 'priority' => 0.8 );
			foreach ( array_keys( DBD_Data::teams() ) as $a ) { $list[] = array( 'loc' => $u( array( 'view' => 'players', 'team' => $a ) ), 'changefreq' => 'weekly' ); }
			foreach ( DBD_Data::player_index() as $p ) { $list[] = array( 'loc' => $u( array( 'player' => $p['id'] ) ), 'changefreq' => 'weekly', 'priority' => 0.6 ); }
		}
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $games ) {
			foreach ( $games as $g ) {
				$list[] = array( 'loc' => $u( array( 'game' => $g['id'] ) ), 'changefreq' => 'weekly', 'priority' => 0.7 );
				$list[] = array( 'loc' => $u( array( 'game' => $g['id'], 'side' => 'home' ) ), 'changefreq' => 'weekly', 'priority' => 0.6 );
			}
		}
		return $list;
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
		return DBD_Seo::urls();
	}
	public function get_max_num_pages( $object_subtype = '' ) {
		return 1;
	}
}
