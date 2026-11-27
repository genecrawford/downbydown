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

	private static function col( $title, $links, $cls = 'dxn-col' ) {
		$h = '<div class="' . $cls . '"><h3>' . esc_html( $title ) . '</h3><ul>';
		foreach ( $links as $l ) { $h .= self::link( $l[0], $l[1] ); }
		return $h . '</ul></div>';
	}

	public static function tile( $abbr, $t ) {
		$c1 = preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $t['c1'] ?? '' ) ) ? $t['c1'] : '#444444';
		$c2 = preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $t['c2'] ?? '' ) ) ? $t['c2'] : '#888888';
		return '<span class="dxn-tile" style="--c1:' . $c1 . ';--c2:' . $c2 . '">' . esc_html( $abbr ) . '</span>';
	}

	private static function teams_grid( $urls, $cls = 'dxn' ) {
		$T = DBD_Data::teams(); if ( ! $T ) { return ''; }
		$h = '';
		foreach ( array( 'AFC', 'NFC' ) as $conf ) {
			$h .= '<div class="' . $cls . '-conf">';
			foreach ( self::DIVS as $d ) {
				if ( strpos( $d, $conf ) !== 0 ) { continue; }
				$in = array(); foreach ( $T as $a => $t ) { if ( ( $t['div'] ?? '' ) === $d ) { $in[ $a ] = $t; } }
				uasort( $in, function ( $x, $y ) { return strcmp( (string) ( $x['nick'] ?? '' ), (string) ( $y['nick'] ?? '' ) ); } );
				$h .= '<div class="' . $cls . '-div"><' . ( $cls === 'dxn' ? 'h3' : 'h4' ) . '>' . esc_html( $d ) . '</' . ( $cls === 'dxn' ? 'h3' : 'h4' ) . '><ul>';
				foreach ( $in as $a => $t ) {
					$h .= '<li><a href="' . esc_url( $urls[ $a ] ?? '' ) . '" data-t="' . esc_attr( $a ) . '" data-n="' . esc_attr( $t['name'] ?? $a ) . '" data-k="' . esc_attr( $t['nick'] ?? $a ) . '">'
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

	/** The site's accounts (the footer's follow icons, from the Share pack settings) for the right edge of a panel (Gene, 2.53.1). */
	private static function follow( $cls = 'dxn-follow' ) {
		$links = DBD_Blocks::follow_links(); if ( ! $links ) { return ''; }
		$h = '<div class="' . $cls . '" role="group" aria-label="Follow Down x Down">';
		foreach ( $links as $l ) {
			$h .= '<a href="' . esc_url( $l[2] ) . '" target="_blank" rel="noopener" aria-label="Down x Down on ' . esc_attr( $l[1] ) . '" title="' . esc_attr( $l[1] ) . '">'
				. '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . DBD_Blocks::ICONS[ $l[0] ] . '"/></svg></a>';
		}
		return $h . '</div>';
	}

	/**
	 * Every address the header and the footer link to, built once a page so the two never drift apart (2.54.0). Always this season's,
	 * even on a past season's page (the season picker under the header moves between seasons).
	 */
	private static $urls = null;
	private static function urls() {
		if ( self::$urls !== null ) { return self::$urls; }
		$was = DBD_Data::archived() ? DBD_Data::season() : 0; if ( $was ) { DBD_Data::use_season( 0 ); }
		$u = function ( $args = array() ) { return DBD_Router::url( $args ); };
		$page = function ( $slug ) { $p = function_exists( 'get_page_by_path' ) ? get_page_by_path( $slug ) : null; return $p ? get_permalink( $p ) : home_url( '/' . $slug . '/' ); };
		$hub = $u(); $lead = array();
		foreach ( DBD_Leaders::GROUPS as $g => $label ) { $lead[] = array( $label, $u( $g === 'fan' ? array( 'view' => 'leaders' ) : array( 'view' => 'leaders', 'g' => $g ) ) ); }
		$team = array(); foreach ( DBD_Data::teams() as $ab => $t ) { $team[ $ab ] = $u( array( 'team' => $ab ) ); }
		$r = array(
			'hub' => $hub, 'standings' => $hub . '#dbd-teams', 'matchups' => $u( array( 'view' => 'matchups' ) ), 'week' => $u( array( 'view' => 'week' ) ),
			'playoffs' => $u( array( 'view' => 'playoffs' ) ), 'po' => class_exists( 'DBD_Playoffs' ) && DBD_Playoffs::visible(), 'news' => $page( 'news' ), 'about' => $page( 'about' ),
			'teams' => $u( array( 'view' => 'teams' ) ), 'team' => $team, 'players' => $u( array( 'view' => 'players' ) ), 'compare' => $u( array( 'compare' => 'none' ) ),
			'draft' => $u( array( 'view' => 'draft' ) ), 'combine' => $u( array( 'view' => 'combine' ) ), 'lead' => $lead,
			'fantasy' => $u( array( 'view' => 'fantasy' ) ), 'leaders' => $u( array( 'view' => 'leaders' ) ), 'myteam' => $u( array( 'view' => 'myteam' ) ),
			'find' => function_exists( 'rest_url' ) ? rest_url( 'dbd/v1/find' ) : home_url( '/?rest_route=/dbd/v1/find' ),
		);
		if ( $was ) { DBD_Data::use_season( $was ); }
		return self::$urls = $r;
	}

	/** A stylesheet printed inline once a page, comments out: Divi runs shortcodes over a module's output again, so a comment naming the shortcode would draw it twice (2.53.1). */
	private static function inline_css( $file, $id ) {
		return '<style id="' . $id . '">' . trim( (string) preg_replace( array( '#/\*.*?\*/#s', '/\s*\n\s*/' ), array( '', "\n" ), (string) @file_get_contents( DBD_PATH . 'assets/' . $file ) ) ) . '</style>';
	}

	public static function render( $a = array() ) {
		$U = self::urls();
		$also = array( array( 'News', $U['news'] ) );
		if ( $U['po'] ) { array_unshift( $also, array( 'Playoffs', $U['playoffs'] ) ); }  /* hidden playoffs pages are not found (Greptile, PR #167) */
		$soc = self::follow();  /* not in Teams, whose panel is the 32 teams (Gene) */
		$groups = array(
			'week'    => array( 'This week', self::col( 'This week', array( array( 'The slate', $U['hub'] ), array( 'Standings', $U['standings'] ), array( 'Games by week', $U['matchups'] ), array( 'Week in review', $U['week'] ) ) )
				. self::col( 'Also', $also ) . $soc ),
			'teams'   => array( 'Teams', self::teams_grid( $U['team'] ) . '<p class="dxn-foot"><a href="' . esc_url( $U['standings'] ) . '">Standings</a><a href="' . esc_url( $U['teams'] ) . '">Play explorer</a></p>' ),
			'players' => array( 'Players', self::col( 'Players', array( array( 'All players', $U['players'] ), array( 'Compare players', $U['compare'] ) ) )
				. self::col( 'Leaders', $U['lead'] ) . self::col( 'Before the NFL', array( array( 'Draft', $U['draft'] ), array( 'Combine', $U['combine'] ) ) ) . $soc ),
			'fantasy' => array( 'Fantasy', self::col( 'Fantasy', array( array( 'This week in fantasy', $U['fantasy'] ), array( 'Fantasy leaders', $U['leaders'] ), array( 'My team', $U['myteam'] ) ) ) . $soc ),
		);
		$playoffs = $U['po']; $po_url = $U['playoffs']; $players = $U['players']; $find = $U['find'];

		$cur = self::current(); $id = 'dxn' . ( ++self::$n );
		$h = '';
		if ( ! self::$css ) {
			self::$css = true;
			$h .= self::inline_css( 'nav.css', 'dxn-css' );
			if ( function_exists( 'wp_enqueue_script' ) ) { wp_enqueue_script( 'dbd-nav', DBD_URL . 'assets/nav.js', array(), DBD_VERSION, true ); }
		}
		$logo = (string) @file_get_contents( DBD_PATH . 'assets/nav-logo.svg' );
		$h .= '<header class="dxn"><div class="dxn-bar">'
			. '<a class="dxn-home" href="' . esc_url( home_url( '/' ) ) . '" aria-label="Down x Down home">' . trim( $logo ) . '</a>'
			. '<button class="dxn-burger" type="button" aria-expanded="false" aria-controls="' . $id . '-nav" aria-label="Menu"><span></span><span></span><span></span></button>'
			. '<nav class="dxn-nav" id="' . $id . '-nav" aria-label="Main"><div class="dxn-msearch">' . self::search( $id . '-m', $players, $find ) . '</div><ul class="dxn-list">';
		foreach ( $groups as $k => $g ) {
			$h .= '<li class="dxn-item dxn-' . $k . '"><button class="dxn-top" type="button" aria-expanded="false" aria-controls="' . $id . '-' . $k . '"' . ( $cur === $k ? ' aria-current="true"' : '' ) . '>'
				. '<span class="dxn-l">' . esc_html( $g[0] ) . '</span><svg viewBox="0 0 10 6" aria-hidden="true" focusable="false"><path d="M1 1l4 4 4-4"/></svg></button>'
				. '<div class="dxn-panel" id="' . $id . '-' . $k . '"><div class="dxn-in dxn-in-' . $k . '">' . $g[1] . '</div></div></li>';
		}
		if ( $playoffs ) {  /* last on the bar (Gene, 2.53.1) */
			$h .= '<li class="dxn-po"><a class="dxn-top" href="' . esc_url( $po_url ) . '"' . ( $cur === 'playoffs' ? ' aria-current="page"' : '' ) . '><span class="dxn-l">Playoffs</span></a></li>';
		}
		$h .= '</ul></nav><div class="dxn-dsearch">' . self::search( $id . '-d', $players, $find ) . '</div></div></header>';
		return $h;
	}

	/* ------------------------------------------------------------------ the footer (2.54.0, SCOPE §96) */

	private static $fcss = false;

	/**
	 * The site footer, the header's companion: logo, the line "Every NFL snap, drawn on the field." and the social icons; the header's
	 * links laid out flat (This week, Players, Leaders, Fantasy, and About, the one page the header leaves out); all 32 teams by division;
	 * then the footer's own two lines. Same links as the header (urls()), Playoffs only while the switch shows them.
	 */
	public static function footer( $a = array() ) {
		$U = self::urls();
		$week = array( array( 'The slate', $U['hub'] ), array( 'Standings', $U['standings'] ), array( 'Games by week', $U['matchups'] ), array( 'Week in review', $U['week'] ) );
		if ( $U['po'] ) { $week[] = array( 'Playoffs', $U['playoffs'] ); }
		$week[] = array( 'News', $U['news'] );
		$h = '';
		if ( ! self::$fcss ) { self::$fcss = true; $h .= self::inline_css( 'footer.css', 'dxf-css' ); }
		$logo = str_replace( array( 'class="dxn-logo"', 'dxn-clip-' ), array( 'class="dxf-logo"', 'dxf-clip-' ), trim( (string) @file_get_contents( DBD_PATH . 'assets/nav-logo.svg' ) ) );  /* its own ids: the header's logo is on the same page */
		$h .= '<footer class="dxf"><div class="dxf-in">'
			. '<div class="dxf-top"><a class="dxf-home" href="' . esc_url( home_url( '/' ) ) . '" aria-label="Down x Down home">' . $logo . '</a>'
			. '<p class="dxf-line">Every NFL snap, drawn on the field.</p>' . self::follow( 'dxf-follow' ) . '</div>'
			. '<nav class="dxf-links" aria-label="Site">'
			. self::col( 'This week', $week, 'dxf-col' )
			. self::col( 'Players', array( array( 'All players', $U['players'] ), array( 'Compare players', $U['compare'] ), array( 'Draft', $U['draft'] ), array( 'Combine', $U['combine'] ) ), 'dxf-col' )
			. self::col( 'Leaders', $U['lead'], 'dxf-col' )
			. self::col( 'Fantasy', array( array( 'This week in fantasy', $U['fantasy'] ), array( 'Fantasy leaders', $U['leaders'] ), array( 'My team', $U['myteam'] ) ), 'dxf-col' )
			. self::col( 'Down x Down', array( array( 'About', $U['about'] ) ), 'dxf-col' )
			. '</nav>';
		$grid = self::teams_grid( $U['team'], 'dxf' );
		if ( $grid !== '' ) { $h .= '<div class="dxf-teams"><h3>Teams</h3><div class="dxf-divs">' . $grid . '</div></div>'; }
		$h .= '<div class="dxf-base"><p>Data: nflverse. Not affiliated with the NFL.</p><p>By <a href="https://period-three.com/">Period Three</a></p></div>'
			. '</div></footer>';
		return $h;
	}
}
