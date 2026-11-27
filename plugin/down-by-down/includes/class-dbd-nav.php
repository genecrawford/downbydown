<?php
/**
 * The site header (2.53.0, SCOPE §95): [dbd_block type="nav"], pasted into the Divi Theme Builder header in place of the old
 * hand-made bar. The logo, then Playoffs (only while the Playoffs switch shows them), This week, Teams, Players and Fantasy, each
 * opening a panel, and a search box that finds players and teams as you type.
 *
 * It carries its own CSS (inline, once a page, so the bar never draws unstyled) and a small footer script; it needs no week's data
 * beyond the team list, and the player list for search is fetched only when someone starts typing (GET dbd/v1/find).
 */
defined( 'ABSPATH' ) || exit;

class DBD_Nav {

	/** Which group a view belongs to: the group's button keeps the red line while you're on one of its pages. */
	const GROUP = array(
		'hub' => 'week', 'season' => 'week', 'matchups' => 'week', 'game' => 'week', 'week' => 'week', 'nodata' => 'week',
		'team' => 'teams', 'teamplayers' => 'teams', 'teams' => 'teams',
		'player' => 'players', 'players' => 'players', 'compare' => 'players', 'leaders' => 'players', 'draft' => 'players', 'combine' => 'players',
		'fantasy' => 'fantasy', 'myteam' => 'fantasy',
	);
	const DIVS = array( 'AFC East', 'AFC North', 'AFC South', 'AFC West', 'NFC East', 'NFC North', 'NFC South', 'NFC West' );

	private static $css = false;
	private static $n   = 0;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
	}

	/** GET dbd/v1/find: every indexed player as [name, team, position, link], for the search box. Public data, cached an hour. */
	public static function rest() {
		register_rest_route( 'dbd/v1', '/find', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$r = rest_ensure_response( self::find_rows() );
				$r->header( 'Cache-Control', 'public, max-age=3600' );
				return $r;
			},
		) );
	}

	public static function find_rows() {
		/* the key carries the routing too (pretty paths, root or page-prefixed paths) through an address built the same way (Greptile, PR #167) */
		$key = 'dbd_nav_find_' . md5( DBD_VERSION . '|' . DBD_Data::file_version( 'players/index.json' ) . '|' . DBD_Router::url( array( 'view' => 'players' ) ) . '|' . ( DBD_Urls::on() ? 1 : 0 ) );
		$c = get_transient( $key ); if ( is_array( $c ) ) { return $c; }
		$out = array();
		foreach ( DBD_Data::player_index() as $p ) {
			if ( empty( $p['id'] ) || empty( $p['name'] ) ) { continue; }
			$out[] = array( (string) $p['name'], (string) ( $p['team'] ?? '' ), (string) ( $p['pos'] ?? '' ), DBD_Router::url( array( 'player' => $p['id'] ) ) );
		}
		set_transient( $key, $out, DAY_IN_SECONDS );
		return $out;
	}

	/** The group the page being served belongs to ('' when none: the home page, About, a post). */
	private static function current() {
		if ( class_exists( 'DBD_Router' ) && DBD_Router::$active && is_array( DBD_Router::$route ) ) {
			$v = (string) ( DBD_Router::$route['view'] ?? '' );
			if ( $v === 'playoffs' ) { return 'playoffs'; }
			return self::GROUP[ $v ] ?? '';
		}
		$o = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
		if ( $o && isset( $o->post_name ) && $o->post_name === 'news' ) { return 'week'; }
		return '';
	}

	private static function link( $label, $url ) {
		return '<li><a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a></li>';
	}

	private static function col( $title, $links ) {
		$h = '<div class="dxn-col"><h3>' . esc_html( $title ) . '</h3><ul>';
		foreach ( $links as $l ) { $h .= self::link( $l[0], $l[1] ); }
		return $h . '</ul></div>';
	}

	public static function tile( $abbr, $t ) {
		$c1 = preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $t['c1'] ?? '' ) ) ? $t['c1'] : '#444444';
		$c2 = preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $t['c2'] ?? '' ) ) ? $t['c2'] : '#888888';
		return '<span class="dxn-tile" style="--c1:' . $c1 . ';--c2:' . $c2 . '">' . esc_html( $abbr ) . '</span>';
	}

	private static function teams_grid( $u ) {
		$T = DBD_Data::teams(); if ( ! $T ) { return ''; }
		$h = '';
		foreach ( array( 'AFC', 'NFC' ) as $conf ) {
			$h .= '<div class="dxn-conf">';
			foreach ( self::DIVS as $d ) {
				if ( strpos( $d, $conf ) !== 0 ) { continue; }
				$in = array(); foreach ( $T as $a => $t ) { if ( ( $t['div'] ?? '' ) === $d ) { $in[ $a ] = $t; } }
				uasort( $in, function ( $x, $y ) { return strcmp( (string) ( $x['nick'] ?? '' ), (string) ( $y['nick'] ?? '' ) ); } );
				$h .= '<div class="dxn-div"><h3>' . esc_html( $d ) . '</h3><ul>';
				foreach ( $in as $a => $t ) {
					$h .= '<li><a href="' . esc_url( $u( array( 'team' => $a ) ) ) . '" data-t="' . esc_attr( $a ) . '" data-n="' . esc_attr( $t['name'] ?? $a ) . '" data-k="' . esc_attr( $t['nick'] ?? $a ) . '">'
						. self::tile( $a, $t ) . '<span>' . esc_html( $t['nick'] ?? $a ) . '</span></a></li>';
				}
				$h .= '</ul></div>';
			}
			$h .= '</div>';
		}
		return $h;
	}

	private static function search( $id, $action, $find ) {
		/* a GET form drops its action's query string, so a query-string address (?view=players) travels as hidden fields */
		$hidden = ''; $q = (string) wp_parse_url( $action, PHP_URL_QUERY );
		if ( $q !== '' ) {
			parse_str( $q, $kv ); foreach ( $kv as $k => $v ) { if ( is_string( $v ) ) { $hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">'; } }
			$action = strtok( $action, '?' );
		}
		return '<form class="dxn-search" action="' . esc_url( $action ) . '" method="get" role="search" data-find="' . esc_url( $find ) . '">' . $hidden
			. '<svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><circle cx="8.5" cy="8.5" r="5.5"/><path d="M13 13l4.5 4.5"/></svg>'
			. '<label class="dxn-sr" for="' . $id . '">Find a player or team</label>'
			. '<input id="' . $id . '" name="q" type="search" placeholder="Find a player or team" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="' . $id . '-l">'
			. '<ul class="dxn-sugg" id="' . $id . '-l" role="listbox" hidden></ul></form>';
	}

	public static function render( $a = array() ) {
		/* the menu always points at this season, even on a past season's page (the season picker under it moves between seasons) */
		$was = DBD_Data::archived() ? DBD_Data::season() : 0; if ( $was ) { DBD_Data::use_season( 0 ); }
		$u = function ( $args = array() ) { return DBD_Router::url( $args ); };
		$hub  = $u();
		$news = home_url( '/news/' );
		if ( function_exists( 'get_page_by_path' ) ) { $p = get_page_by_path( 'news' ); if ( $p ) { $news = get_permalink( $p ); } }
		$find = function_exists( 'rest_url' ) ? rest_url( 'dbd/v1/find' ) : home_url( '/?rest_route=/dbd/v1/find' );
		$lead = array();
		foreach ( DBD_Leaders::GROUPS as $g => $label ) { $lead[] = array( $label, $u( $g === 'fan' ? array( 'view' => 'leaders' ) : array( 'view' => 'leaders', 'g' => $g ) ) ); }
		$playoffs = class_exists( 'DBD_Playoffs' ) && DBD_Playoffs::visible();
		$also = array( array( 'News', $news ) );
		if ( $playoffs ) { array_unshift( $also, array( 'Playoffs', $u( array( 'view' => 'playoffs' ) ) ) ); }  /* hidden playoffs pages are not found (Greptile, PR #167) */
		$groups = array(
			'week'    => array( 'This week', self::col( 'This week', array( array( 'The slate', $hub ), array( 'Standings', $hub . '#dbd-teams' ), array( 'Games by week', $u( array( 'view' => 'matchups' ) ) ), array( 'Week in review', $u( array( 'view' => 'week' ) ) ) ) )
				. self::col( 'Also', $also ) ),
			'teams'   => array( 'Teams', self::teams_grid( $u ) . '<p class="dxn-foot"><a href="' . esc_url( $hub . '#dbd-teams' ) . '">Standings</a><a href="' . esc_url( $u( array( 'view' => 'teams' ) ) ) . '">Play explorer</a></p>' ),
			'players' => array( 'Players', self::col( 'Players', array( array( 'All players', $u( array( 'view' => 'players' ) ) ), array( 'Compare players', $u( array( 'compare' => 'none' ) ) ) ) )
				. self::col( 'Leaders', $lead ) . self::col( 'Before the NFL', array( array( 'Draft', $u( array( 'view' => 'draft' ) ) ), array( 'Combine', $u( array( 'view' => 'combine' ) ) ) ) ) ),
			'fantasy' => array( 'Fantasy', self::col( 'Fantasy', array( array( 'This week in fantasy', $u( array( 'view' => 'fantasy' ) ) ), array( 'Fantasy leaders', $u( array( 'view' => 'leaders' ) ) ), array( 'My team', $u( array( 'view' => 'myteam' ) ) ) ) ) ),
		);
		$po_url   = $u( array( 'view' => 'playoffs' ) );
		$players  = $u( array( 'view' => 'players' ) );
		if ( $was ) { DBD_Data::use_season( $was ); }

		$cur = self::current(); $id = 'dxn' . ( ++self::$n );
		$h = '';
		if ( ! self::$css ) {
			self::$css = true;
			$css = (string) @file_get_contents( DBD_PATH . 'assets/nav.css' );
			$h .= '<style id="dxn-css">' . $css . '</style>';
			if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'dbd-nav', DBD_URL . 'assets/nav.js', array(), DBD_VERSION, true ); }
		}
		$logo = (string) @file_get_contents( DBD_PATH . 'assets/nav-logo.svg' );
		$h .= '<header class="dxn"><div class="dxn-bar">'
			. '<a class="dxn-home" href="' . esc_url( home_url( '/' ) ) . '" aria-label="Down x Down home">' . trim( $logo ) . '</a>'
			. '<button class="dxn-burger" type="button" aria-expanded="false" aria-controls="' . $id . '-nav" aria-label="Menu"><span></span><span></span><span></span></button>'
			. '<nav class="dxn-nav" id="' . $id . '-nav" aria-label="Main"><div class="dxn-msearch">' . self::search( $id . '-m', $players, $find ) . '</div><ul class="dxn-list">';
		if ( $playoffs ) {
			$h .= '<li class="dxn-po"><a class="dxn-top" href="' . esc_url( $po_url ) . '"' . ( $cur === 'playoffs' ? ' aria-current="page"' : '' ) . '><span class="dxn-l">Playoffs</span></a></li>';
		}
		foreach ( $groups as $k => $g ) {
			$h .= '<li class="dxn-item dxn-' . $k . '"><button class="dxn-top" type="button" aria-expanded="false" aria-controls="' . $id . '-' . $k . '"' . ( $cur === $k ? ' aria-current="true"' : '' ) . '>'
				. '<span class="dxn-l">' . esc_html( $g[0] ) . '</span><svg viewBox="0 0 10 6" aria-hidden="true" focusable="false"><path d="M1 1l4 4 4-4"/></svg></button>'
				. '<div class="dxn-panel" id="' . $id . '-' . $k . '"><div class="dxn-in dxn-in-' . $k . '">' . $g[1] . '</div></div></li>';
		}
		$h .= '</ul></nav><div class="dxn-dsearch">' . self::search( $id . '-d', $players, $find ) . '</div></div></header>';
		return $h;
	}
}
