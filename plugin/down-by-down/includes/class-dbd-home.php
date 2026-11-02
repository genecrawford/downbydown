<?php
/**
 * The marketing home (SCOPE §45). The front page is Gene's, built in Divi; the plugin supplies:
 *   [dbd_block type="…"]  live blocks drawn from this week's data (hero-map, games, counts, week-highlights, matchup, player-card,
 *                         movers, fantasy, leaders, teams, newsletter-preview, faq) and two decorations (down, yards)
 *   [dbd_url to="…"]      the right link for a button: hub, teams, players, myteam, matchups, leaders, week, season
 *   /feed/week-in-review/ an RSS item per finished week, for RSS-to-email
 *   the front page's title, description, share tags and WebSite / Organization data, when the app lives on its own page
 * Every block shows the current season, picks its own subject each week, and renders nothing when its data is missing.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Home {
	const TYPES = array( 'hero-map', 'games', 'counts', 'down', 'yards', 'week-highlights', 'matchup', 'player-card', 'movers', 'fantasy', 'leaders', 'teams', 'newsletter-preview', 'faq' );
	const FEED  = 'week-in-review';
	const TITLE = 'Down by Down: every NFL snap, drawn on the field | Free NFL charts, matchups and fantasy';
	const DESC  = 'Free NFL play-by-play charts: every snap drawn on the field, target maps, EPA by team, a matchup read for every game and fantasy with the why. Updated three times a day.';

	public static function init() {
		add_shortcode( 'dbd_block', array( __CLASS__, 'block_shortcode' ) );
		add_shortcode( 'dbd_url', array( __CLASS__, 'url_shortcode' ) );
		add_action( 'init', function () { add_feed( self::FEED, array( __CLASS__, 'feed' ) ); }, 5 );  /* before DBD_Urls::rules() flushes at 20 */
		add_action( 'wp', array( __CLASS__, 'detect' ), 20 );
	}

	/** On a page that carries blocks, load their styles in the head; on the front page, when the app isn't there, write its head tags. */
	public static function detect() {
		if ( is_admin() ) { return; }
		$post = is_singular() ? get_queried_object() : null;
		if ( $post && strpos( (string) ( $post->post_content ?? '' ), 'dbd_block' ) !== false ) { add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) ); }
		if ( is_front_page() && ! DBD_Router::$active && self::seo_on() ) {
			add_filter( 'pre_get_document_title', function () { return self::TITLE; }, 20 );
			add_action( 'wp_head', array( __CLASS__, 'head' ), 2 );
		}
	}

	/** Head tags only when no SEO plugin writes them. */
	public static function seo_on() {
		$other = defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
		return (bool) apply_filters( 'dbd_home_seo', ! $other );
	}

	public static function enqueue() {
		wp_enqueue_style( 'dbd-fonts', 'https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap', array(), null );
		wp_enqueue_style( 'dbd-home', DBD_URL . 'assets/home.css', array( 'dbd-fonts' ), DBD_VERSION );
	}

	public static function head() {
		$home = home_url( '/' );
		echo "\n" . '<meta name="description" content="' . esc_attr( self::DESC ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( self::TITLE ) . '">' . "\n" . '<meta property="og:description" content="' . esc_attr( self::DESC ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( $home ) . '">' . "\n" . '<meta property="og:type" content="website">' . "\n";
		if ( DBD_Data::has_data() && DBD_Card::available() ) {
			$img = DBD_Card::url( 'page-hub' );
			echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n" . '<meta property="og:image:width" content="1200">' . "\n" . '<meta property="og:image:height" content="630">' . "\n";
			echo '<meta name="twitter:card" content="summary_large_image">' . "\n" . '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
		}
		echo self::site_jsonld();
	}

	public static function site_jsonld() {
		$home = home_url( '/' );
		$ld = array( '@context' => 'https://schema.org', '@graph' => array(
			array( '@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => 'Down by Down', 'description' => self::DESC, 'publisher' => array( '@id' => $home . '#org' ) ),
			array( '@type' => 'Organization', '@id' => $home . '#org', 'name' => 'Down by Down', 'url' => $home, 'logo' => DBD_URL . 'assets/card-logo.png' ),
		) );
		return '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}

	/* ================================================================== shortcodes */

	public static function block_shortcode( $atts ) {
		$a = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
		$type = strtolower( trim( (string) ( $a['type'] ?? '' ) ) );
		if ( ! in_array( $type, self::TYPES, true ) ) { return ''; }
		self::enqueue();  /* a block in a Theme Builder layout isn't in the page's content: its styles print in the footer instead */
		return self::render( $type, $a );
	}

	/** One block's HTML, cached until the next data build (blocks read up to 32 player files). */
	public static function render( $type, $a = array() ) {
		$was = DBD_Data::archived() ? DBD_Data::season() : null; DBD_Data::use_season( null );  /* the home always shows this season */
		if ( ! DBD_Data::has_data() ) { DBD_Data::use_season( $was ); return ''; }
		unset( $a['type'] ); ksort( $a );
		$key = 'dbd_hb_' . md5( wp_json_encode( array( $type, $a, DBD_VERSION, DBD_Data::manifest()['built_at'] ?? '', DBD_Data::logos(), DBD_Data::headshots(), DBD_Router::url( array( 'view' => 'teams' ) ) ) ) );
		$html = get_transient( $key );
		if ( ! is_string( $html ) ) {
			$fn = 'b_' . str_replace( '-', '_', $type );
			$html = (string) self::$fn( $a );
			set_transient( $key, $html, 6 * HOUR_IN_SECONDS );
		}
		DBD_Data::use_season( $was );
		return $html;
	}

	/** [dbd_url to="hub|teams|players|myteam|matchups|leaders|week|season"], plus team="MIN" for a team's page. */
	public static function url_shortcode( $atts ) {
		$a = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
		return esc_url( self::url_for( strtolower( (string) ( $a['to'] ?? 'hub' ) ), $a ) );
	}

	public static function url_for( $to, $a = array() ) {
		$was = DBD_Data::archived() ? DBD_Data::season() : null; DBD_Data::use_season( null );
		$t = strtoupper( (string) ( $a['team'] ?? '' ) );
		switch ( $to ) {
			case 'teams': $u = DBD_Router::url( array( 'view' => 'teams' ) ); break;
			case 'players': $u = DBD_Router::url( array( 'view' => 'players' ) ); break;
			case 'myteam': $u = DBD_Router::url( array( 'view' => 'myteam' ) ); break;
			case 'matchups': $u = DBD_Router::url( array( 'view' => 'matchups' ) ); break;
			case 'leaders': $u = DBD_Router::url( array( 'view' => 'leaders' ) ); break;
			case 'week': $u = DBD_Router::url( array( 'view' => 'week' ) ); break;
			case 'team': $u = DBD_Data::team( $t ) ? DBD_Router::url( array( 'team' => $t ) ) : DBD_Router::url( array( 'view' => 'teams' ) ); break;
			case 'season':
				$s = DBD_Data::archived_seasons();
				if ( $s ) { DBD_Data::use_season( $s[0] ); $u = DBD_Router::url(); DBD_Data::use_season( null ); } else { $u = DBD_Router::url(); }
				break;
			default: $u = DBD_Router::url();
		}
		DBD_Data::use_season( $was );
		return $u;
	}

	/* ================================================================== helpers */

	private static function nick( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; }
	private static function u( $args ) { return esc_url( DBD_Router::url( $args ) ); }
	private static function wrap( $type, $inner ) { return $inner === '' ? '' : '<div class="dbd-hb hb-' . esc_attr( $type ) . '">' . $inner . '</div>'; }
	private static function pct( $v ) { return round( (float) $v / 10 ); }
	/** A play's description without the tacklers' brackets: "…, TOUCHDOWN [J.McMillian]." reads "…, TOUCHDOWN." */
	private static function clean( $d ) { return trim( (string) preg_replace( '/\s*\[[^\]]*\]/', '', (string) $d ) ); }
	private static function tags( $g ) { $o = array(); foreach ( (array) ( $g['tags'] ?? array() ) as $t ) { $o[ $t[0] ] = $t[1]; } return $o; }

	/** The latest week with a final game, and the latest finished week, from the week-in-review files. */
	private static function latest( $complete = false ) {
		foreach ( array_reverse( DBD_Week::weeks() ) as $n ) {
			$W = DBD_Week::file( $n ); if ( ! $W ) { continue; }
			if ( ! $complete || ! empty( $W['complete'] ) ) { return array( $n, $W ); }
		}
		return array( 0, null );
	}

	/** A final game's winner, loser and both scores, the winner's first. */
	private static function wl( $g ) {
		$w = $g['win']; $aw = $w === $g['away'];
		return array( $w, $aw ? $g['home'] : $g['away'], (int) ( $aw ? $g['a'] : $g['h'] ), (int) ( $aw ? $g['h'] : $g['a'] ) );
	}

	/* ================================================================== the blocks */

	/** The week's most-targeted player (or player="id"): photo, line, every target on the field, a link to his page. */
	private static function b_hero_map( $a ) {
		$wk = DBD_Week::default_week(); if ( ! $wk ) { return ''; }
		$pid = (string) ( $a['player'] ?? '' ); $auto = $pid === '';
		$hit = $auto ? self::most_targeted( $wk ) : self::week_line( $pid, $wk );
		if ( ! $hit ) { return ''; }
		list( $pid, $team, $w ) = $hit; $wk = (int) $w['week'];
		$p = DBD_Data::player( $pid ); if ( ! $p ) { return ''; }
		$rows = array(); $pf = DBD_Data::get( 'plays/' . $team . '.json' ); $pre = sprintf( '%d_%02d_', DBD_Data::season(), $wk );
		if ( $pf && ! empty( $pf['keys'] ) ) {
			foreach ( (array) $pf['plays'] as $r ) {
				if ( count( $r ) !== count( $pf['keys'] ) ) { continue; }
				$r = array_combine( $pf['keys'], $r );
				if ( strpos( (string) $r['g'], $pre ) === 0 && ( $r['pid'] ?? '' ) === $pid && ( $r['pt'] ?? '' ) === 'pass' ) { $rows[] = $r; }
			}
		}
		$lab = DBD_Router::week_label( $wk ); $sub = $p['pos'] . ' · ' . self::nick( $team ) . ' · ' . ( $auto ? 'most targets in ' . ( strpos( $lab, 'Week ' ) === 0 ? 'week ' . $wk : 'the ' . $lab ) : $lab );
		$st = array( array( (int) $w['targets'], 'targets' ), array( (int) $w['receptions'], 'caught' ), array( (int) $w['receiving_yards'], 'yards' ), array( (int) $w['receiving_tds'], 'TD' ) );
		$h = '<div class="hb-who">' . DBD_Marks::player( $team, $p['num'] ?? null, 46, $p['photo'] ?? null ) . '<span><b><a href="' . self::u( array( 'player' => $pid ) ) . '">' . esc_html( $p['name'] ) . '</a></b><small>' . esc_html( $sub ) . '</small></span></div>';
		$h .= '<div class="hb-stats">'; foreach ( $st as $s ) { $h .= '<span><b>' . (int) $s[0] . '</b>' . esc_html( $s[1] ) . '</span>'; } $h .= '</div>';
		$h .= self::field( $rows, $p['name'] . "'s targets, " . $lab . ', by depth and side of the field' );
		$h .= '<p class="hb-leg"><i class="c"></i>caught <i class="n"></i>incomplete <i class="t"></i>touchdown <a href="' . self::u( array( 'player' => $pid ) ) . '">His page</a></p>';
		return self::wrap( 'hero-map', $h );
	}

	/** [id, team, week row] of the player with the most targets in week $wk (yards break a tie). */
	private static function most_targeted( $wk ) {
		$best = null; $bk = array( -1, -1 );
		foreach ( array_keys( DBD_Data::teams() ) as $t ) {
			foreach ( DBD_Data::team_players( $t ) as $p ) {
				foreach ( (array) ( $p['weeks'] ?? array() ) as $w ) {
					if ( (int) $w['week'] !== (int) $wk || ( $w['team'] ?? $t ) !== $t ) { continue; }
					$k = array( (int) ( $w['targets'] ?? 0 ), (int) ( $w['receiving_yards'] ?? 0 ) );
					if ( $k > $bk ) { $bk = $k; $best = array( $p['id'], $t, $w ); }
				}
			}
		}
		return $best && $bk[0] > 0 ? $best : null;
	}

	/** A named player's line in week $wk, or his latest week before it. */
	private static function week_line( $pid, $wk ) {
		$row = DBD_Data::player( $pid ); if ( ! $row ) { return null; }
		$rec = DBD_Data::player_record( $row['team'], $pid ); if ( ! $rec ) { return null; }
		$best = null;
		foreach ( (array) ( $rec['weeks'] ?? array() ) as $w ) { if ( (int) $w['week'] <= (int) $wk && ( $w['team'] ?? $row['team'] ) === $row['team'] && ( ! $best || (int) $w['week'] > (int) $best['week'] ) ) { $best = $w; } }
		return $best ? array( $pid, $row['team'], $best ) : null;
	}

	/** The target map: air yards up the field, left / middle / right across it. The spread inside a third is fixed per play, so it never moves. */
	private static function field( $rows, $label ) {
		$xs = array( 'left' => array( 34, 139.3 ), 'middle' => array( 139.3, 244.7 ), 'right' => array( 244.7, 350 ) );
		$g = '';
		foreach ( array( array( 390, -10 ), array( 320, 0 ), array( 250, 10 ), array( 180, 20 ), array( 110, 30 ), array( 40, 40 ) ) as $l ) {
			$g .= '<line x1="34" x2="350" y1="' . $l[0] . '" y2="' . $l[0] . '" stroke="rgba(255,255,255,.22)" stroke-width="' . ( $l[0] === 320 ? 2 : 1 ) . '"/><text x="28" y="' . ( $l[0] + 4 ) . '" fill="rgba(255,255,255,.7)" font-size="10" text-anchor="end">' . $l[1] . '</text>';
		}
		foreach ( array( 139.3, 244.7 ) as $x ) { $g .= '<line x1="' . $x . '" x2="' . $x . '" y1="40" y2="390" stroke="rgba(255,255,255,.18)" stroke-dasharray="2 4"/>'; }
		foreach ( array( array( 86.7, 'Left' ), array( 192, 'Middle' ), array( 297.3, 'Right' ) ) as $l ) { $g .= '<text x="' . $l[0] . '" y="410" fill="rgba(255,255,255,.7)" font-size="11" text-anchor="middle">' . $l[1] . '</text>'; }
		foreach ( $rows as $i => $r ) {
			if ( empty( $r['loc'] ) || ! isset( $xs[ $r['loc'] ] ) || $r['air'] === null ) { continue; }
			list( $a0, $b0 ) = $xs[ $r['loc'] ];
			$j = ( crc32( $r['g'] . '|' . $r['q'] . '|' . $r['t'] . '|' . $i ) % 1000 ) / 1000;
			$x = $a0 + 8 + $j * ( $b0 - $a0 - 16 ); $y = 320 - max( -10, min( 42, (float) $r['air'] ) ) * 7;
			$td = ! empty( $r['td'] ); $col = $td ? '#4fd18b' : ( $r['k'] === 'comp' ? '#5b9cf6' : '#9a96a6' );
			$g .= '<circle cx="' . round( $x, 1 ) . '" cy="' . round( $y ) . '" r="' . ( $td ? 7 : 5.5 ) . '" fill="' . $col . '" fill-opacity=".92" stroke="rgba(0,0,0,.35)"/>';
		}
		return '<svg class="hb-field" viewBox="0 0 360 420" width="100%" role="img" aria-label="' . esc_attr( $label ) . '">' . $g . '</svg>';
	}

	/** This week's games as score tiles, games still to play first; the next one flagged. */
	private static function b_games( $a ) {
		$wk = DBD_Data::current_week(); $games = DBD_Data::week_games( $wk ); if ( ! $games ) { return ''; }
		$W = DBD_Week::file( $wk ); $meta = array(); foreach ( (array) ( $W['games'] ?? array() ) as $g ) { $meta[ $g['id'] ] = $g; }
		$open = array_values( array_filter( $games, function ( $g ) { return ! DBD_Router::is_final( $g ); } ) );
		$done = array_values( array_filter( $games, array( 'DBD_Router', 'is_final' ) ) );
		$tiles = '';
		foreach ( array_merge( $open, $done ) as $i => $g ) {
			$f = DBD_Router::is_final( $g ); $win = $f ? ( $g['away_score'] > $g['home_score'] ? $g['away'] : ( $g['home_score'] > $g['away_score'] ? $g['home'] : '' ) ) : '';
			$row = function ( $t, $s ) use ( $f, $win ) { return '<span class="r' . ( $f && $win === $t ? ' w' : '' ) . '">' . DBD_Marks::tile( $t, 18 ) . '<b>' . esc_html( $t ) . '</b><em>' . ( $f ? (int) $s : '' ) . '</em></span>'; };
			if ( $f ) {
				$tg = self::tags( $meta[ $g['id'] ] ?? array() ); $ot = ! empty( $meta[ $g['id'] ]['ot'] );
				$st = 'Final' . ( isset( $tg['upset'] ) ? ' · Upset' : ( $ot ? ' · OT' : ( isset( $tg['comeback'] ) ? ' · Comeback' : '' ) ) );
			} else {
				$k = DBD_Data::kickoff( $g ); $d = strtotime( (string) $g['date'] ); $st = ( $d ? gmdate( 'D', $d ) . ' ' : '' ) . str_replace( ' ET', '', $k['et'] );
			}
			$tiles .= '<a class="hb-gm' . ( $f ? '' : ' up' ) . '" href="' . self::u( array( 'game' => $g['id'] ) ) . '">' . ( ! $f && $i === 0 ? '<i class="nx">Next</i>' : '' ) . $row( $g['away'], $g['away_score'] ?? null ) . $row( $g['home'], $g['home_score'] ?? null ) . '<span class="st">' . esc_html( $st ) . '</span></a>';
		}
		$nf = count( $done ); $no = count( $open );
		$sum = $nf && $no ? $nf . ' final · ' . $no . ' to play' : ( $nf ? 'all ' . $nf . ' final' : count( $games ) . ' games' );
		return self::wrap( 'games', '<div class="hb-games-h"><b>' . esc_html( DBD_Router::week_label( $wk ) ) . '</b><span>' . esc_html( $sum ) . '</span><a href="' . self::u( array( 'view' => 'matchups' ) ) . '">Every matchup</a></div><div class="hb-games-g">' . $tiles . '</div>' );
	}

	/** One plain line of the site's size. */
	private static function b_counts( $a ) {
		$st = DBD_Data::stats(); $snaps = (int) ( $st['snaps'] ?? 0 ); $fin = 0;
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( (array) $gl as $g ) { if ( DBD_Router::is_final( $g ) ) { $fin++; } } }
		$parts = array();
		if ( $snaps ) { $parts[] = '<b>' . number_format( $snaps ) . '</b> snaps charted this season'; }
		if ( $fin ) { $parts[] = '<b>' . $fin . '</b> games'; }
		if ( DBD_Data::has_players() ) { $parts[] = '<b>' . count( DBD_Data::player_index() ) . '</b> players'; }
		$parts[] = '<b>' . count( DBD_Data::teams() ) . '</b> offenses and defenses';
		return self::wrap( 'counts', '<p>' . implode( ' · ', $parts ) . '</p>' );
	}

	/** A sideline down marker: down="1" dist="10" spot="Own 25" (decoration). */
	private static function b_down( $a ) {
		$d = max( 1, min( 4, (int) ( $a['down'] ?? 1 ) ) ); $sup = array( 1 => 'st', 2 => 'nd', 3 => 'rd', 4 => 'th' )[ $d ];
		$dist = trim( (string) ( $a['dist'] ?? '10' ) ); $spot = trim( (string) ( $a['spot'] ?? '' ) );
		return self::wrap( 'down', '<span class="hb-dm" aria-hidden="true"><span class="b"><b>' . $d . '</b><sup>' . $sup . '</sup></span><span class="t"><b>&amp; ' . esc_html( $dist ) . '</b>' . ( $spot !== '' ? '<small>' . esc_html( $spot ) . '</small>' : '' ) . '</span></span>' );
	}

	/** The painted field numbers, 10 to 50 and back, centered on the yard lines of a .dbd-turf section (decoration). */
	private static function b_yards( $a ) {
		$h = '';
		foreach ( array( 10, 20, 30, 40, 50, 40, 30, 20, 10 ) as $i => $n ) {
			$h .= '<span style="left:' . ( ( $i + 1 ) * 10 ) . '%">' . ( $i < 4 ? '<em class="l">◀</em>' : '' ) . (int) ( $n / 10 ) . '<i></i>0' . ( $i > 4 ? '<em class="r">▶</em>' : '' ) . '</span>';
		}
		return '<div class="dbd-hb hb-yards" aria-hidden="true">' . $h . '</div>';
	}

	/** The play of the week, the swing of the week, the upsets and the wildest game, from the latest week in review. */
	private static function b_week_highlights( $a ) {
		list( $n, $W ) = self::latest(); if ( ! $W ) { return ''; }
		$h = '';
		$tp = $W['plays'][0] ?? null;
		if ( $tp ) {
			$h .= '<a class="hb-wk" href="' . self::u( array( 'game' => $tp['g'] ) ) . '"><i>The play of the week</i>' . DBD_Marks::tile( $tp['t'], 40 ) . '<h3>' . esc_html( self::clean( $tp['d'] ) ) . '</h3><p><b>' . ( $tp['e'] >= 0 ? '+' : '−' ) . number_format( abs( (float) $tp['e'] ), 2 ) . ' EPA</b> · ' . esc_html( self::nick( $tp['t'] ) . ' vs ' . self::nick( $tp['o'] ) . ', ' . $tp['c'] ) . '</p></a>';
		}
		$sw = $W['swings'][0] ?? null;
		if ( $sw ) {
			$h .= '<a class="hb-wk" href="' . self::u( array( 'game' => $sw['g'] ) ) . '"><i>The swing of the week</i>' . DBD_Marks::tile( $sw['t'], 40 ) . '<h3>' . esc_html( (string) $sw['d'] ) . '</h3><p><b>+' . round( (float) $sw['v'] ) . '%</b> win probability for the ' . esc_html( self::nick( $sw['t'] ) . ', ' . $sw['c'] ) . '</p></a>';
		}
		$fin = array_values( array_filter( (array) $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final' && ! empty( $g['win'] ); } ) );
		$ups = array_values( array_filter( $fin, function ( $g ) { return isset( self::tags( $g )['upset'] ); } ) ); usort( $ups, function ( $x, $y ) { return $x['pre_w'] <=> $y['pre_w']; } );
		$shown = array_slice( array_map( function ( $g ) { return $g['id']; }, $ups ), 0, 3 );  /* the wildest is another game when there is one */
		$wd = array_values( array_filter( $fin, function ( $g ) use ( $shown ) { return ! in_array( $g['id'], $shown, true ); } ) ) ?: $fin;
		usort( $wd, function ( $x, $y ) { return array( $y['lc'] ?? 0, $y['drama'] ?? 0 ) <=> array( $x['lc'] ?? 0, $x['drama'] ?? 0 ); } ); $wild = $wd[0] ?? null;
		if ( $ups || $wild ) {
			$li = '';
			foreach ( array_slice( $ups, 0, 3 ) as $g ) { list( $w, $l, $ws, $ls ) = self::wl( $g ); $li .= '<li><a href="' . self::u( array( 'game' => $g['id'] ) ) . '">' . DBD_Marks::tile( $w, 22 ) . '<span><b>' . esc_html( self::nick( $w ) . ' ' . $ws ) . '</b>, ' . esc_html( self::nick( $l ) . ' ' . $ls ) . '</span></a><small>' . self::pct( $g['pre_w'] ) . '% at kickoff</small></li>'; }
			$wl = '';
			if ( $wild ) { list( $w, $l, $ws, $ls ) = self::wl( $wild ); $wl = '<p class="wild"><span>Wildest game</span><a href="' . self::u( array( 'game' => $wild['id'] ) ) . '">' . DBD_Marks::tile( $w, 22 ) . ' <b>' . esc_html( self::nick( $w ) . ' ' . $ws . ', ' . self::nick( $l ) . ' ' . $ls ) . '</b>' . ( ! empty( $wild['ot'] ) ? ' in overtime' : '' ) . '</a></p>'; }
			$h .= '<div class="hb-wk ups"><i>' . ( $ups ? 'Upsets' : 'No upsets' ) . '</i>' . ( $li ? '<ul>' . $li . '</ul>' : '' ) . $wl . '</div>';
		}
		return self::wrap( 'week-highlights', $h );
	}

	/** The game of the week, picked the way the hub picks it, over its four-row head to head. */
	private static function b_matchup( $a ) {
		if ( DBD_Data::through_week() < 1 ) { return ''; }
		$gotw = null; $best = null;
		foreach ( DBD_Data::week_games( DBD_Data::current_week() ) as $g ) {
			if ( DBD_Router::is_final( $g ) ) { continue; }
			$b = DBD_Takeaways::best_edge( $g['away'], $g['home'] );
			if ( ! $best || $b['s'] > $best['s'] ) { $gotw = $g; $best = $b; }
		}
		if ( ! $gotw ) { return ''; }
		$O = $best['O']; $D = $best['D']; $cards = DBD_Takeaways::cards( $O, $D ); $k = DBD_Data::kickoff( $gotw ); $d = strtotime( (string) $gotw['date'] );
		$kick = 'Game of the week · ' . ( $d ? gmdate( 'l', $d ) . ' ' : '' ) . $k['et'] . ( ! empty( $gotw['stadium'] ) ? ' · ' . $gotw['stadium'] : '' );
		$side = function ( $t ) { return '<span>' . DBD_Marks::tile( $t, 54 ) . '<b>' . esc_html( self::nick( $t ) ) . '</b><small>' . esc_html( DBD_Data::record_text( $t ) ) . '</small></span>'; };
		$line = array_filter( array( DBD_Router::line_text( $gotw ), isset( $gotw['total'] ) && $gotw['total'] !== null ? 'O/U ' . $gotw['total'] : '' ) );
		$h = '<div class="hb-gotw"><p class="k">' . esc_html( $kick ) . '</p><div class="mt">' . $side( $gotw['away'] ) . '<em>at</em>' . $side( $gotw['home'] ) . '</div>' . ( $line ? '<p class="ln">' . esc_html( implode( ' · ', $line ) ) . '</p>' : '' );
		if ( $cards ) { $h .= '<p class="say"><i>' . esc_html( $cards[0][0] ) . '</i>' . esc_html( $cards[0][3] ) . '</p>'; }
		$h .= '<a class="hb-link" href="' . self::u( array( 'game' => $gotw['id'], 'side' => $O === $gotw['home'] ? 'home' : 'away' ) ) . '">Open the matchup</a></div>';
		$rows = '';
		foreach ( array( 'epa' => 'EPA a play', 'sr' => 'Success rate', 'expl' => 'Explosive plays', 'rz_td' => 'Red-zone TDs' ) as $mk => $lab ) {
			list( $ov, $or ) = DBD_Metrics::off( $O, $mk ); list( $dv, $dr ) = DBD_Metrics::def( $D, $mk );
			if ( ! $or || ! $dr ) { continue; }
			$edge = (int) $dr - (int) $or; $w = min( 50, abs( $edge ) * 1.6 ); $c = DBD_Marks::accent( DBD_Data::team( $edge > 0 ? $O : $D ) );
			$rows .= '<div class="hr"><span class="o">' . esc_html( DBD_Metrics::fmt( $mk, $ov ) ) . '<small>' . esc_html( DBD_Metrics::ordinal( $or ) ) . '</small></span><span class="lab">' . esc_html( $lab ) . '<span class="bar"><i class="' . ( $edge > 0 ? 'o' : 'd' ) . '" style="width:' . round( $w ) . '%;background:' . esc_attr( $c ) . '"></i></span></span><span class="d">' . esc_html( DBD_Metrics::fmt( $mk, $dv ) ) . '<small>' . esc_html( DBD_Metrics::ordinal( $dr ) ) . '</small></span></div>';
		}
		if ( $rows ) { $h .= '<div class="hb-h2h"><div class="hh">' . DBD_Marks::tile( $O, 26 ) . '<b>' . esc_html( self::nick( $O ) ) . ' offense</b><span>vs</span><b>' . esc_html( self::nick( $D ) ) . ' defense</b>' . DBD_Marks::tile( $D, 26 ) . '</div>' . $rows . '</div>'; }
		return self::wrap( 'matchup', $h );
	}

	/** A player's share card (default: the week's biggest riser in target share), linked to his page. */
	private static function b_player_card( $a ) {
		if ( ! DBD_Card::available() ) { return ''; }
		$pid = (string) ( $a['player'] ?? '' );
		if ( $pid === '' ) { $mv = DBD_Data::get( 'players/movers.json' ); $pid = (string) ( $mv['targets']['up'][0]['id'] ?? '' ); }
		$p = $pid !== '' ? DBD_Data::player( $pid ) : null; if ( ! $p ) { return ''; }
		return self::wrap( 'player-card', '<a href="' . self::u( array( 'player' => $pid ) ) . '"><img src="' . esc_url( DBD_Card::url( $pid ) ) . '" alt="' . esc_attr( $p['name'] . "'s share card" ) . '" width="1200" height="630" loading="lazy" decoding="async"></a>' );
	}

	/** The four biggest jumps in target share, before and after. */
	private static function b_movers( $a ) {
		$mv = DBD_Data::get( 'players/movers.json' ); $up = array_slice( (array) ( $mv['targets']['up'] ?? array() ), 0, max( 1, min( 8, (int) ( $a['count'] ?? 4 ) ) ) );
		if ( ! $up ) { return ''; }
		$li = '';
		foreach ( $up as $m ) {
			$li .= '<li>' . DBD_Marks::player( $m['team'], $m['num'] ?? null, 40, $m['photo'] ?? null ) . '<span><b><a href="' . self::u( array( 'player' => $m['id'] ) ) . '">' . esc_html( $m['name'] ) . '</a></b><small>' . esc_html( $m['pos'] . ' · ' . self::nick( $m['team'] ) ) . '</small></span><em>' . round( (float) $m['before'] ) . '% <i>→</i> <strong>' . round( (float) $m['now'] ) . '%</strong></em></li>';
		}
		return self::wrap( 'movers', '<div class="hb-panel"><p class="k">Rising this week · share of targets</p><ul class="hb-list">' . $li . '</ul></div>' );
	}

	/** The softest defense at each position, half PPR. */
	private static function b_fantasy( $a ) {
		$f = DBD_Fantasy::file(); if ( empty( $f['rank'] ) ) { return ''; }
		$h = '';
		foreach ( array( 'QB', 'RB', 'WR', 'TE' ) as $pos ) {
			$best = null; foreach ( (array) $f['rank'] as $t => $r ) { $rk = (int) ( $r[ $pos ][1] ?? 0 ); if ( $rk && ( ! $best || $rk < $best[1] ) ) { $best = array( $t, $rk ); } }
			if ( ! $best ) { continue; }
			$v = (float) ( $f['allow'][ $best[0] ][ $pos ][1] ?? 0 );
			$h .= '<a class="hb-soft" href="' . self::u( array( 'team' => $best[0], 'side' => 'def' ) ) . '">' . DBD_Marks::tile( $best[0], 38 ) . '<span><i>' . esc_html( $pos ) . '</i><b>' . esc_html( self::nick( $best[0] ) ) . '</b><small>allow ' . esc_html( DBD_Fantasy::num( $v ) ) . ' points a game to ' . esc_html( $pos ) . 's, the most in the NFL</small></span></a>';
		}
		return self::wrap( 'fantasy', $h );
	}

	/** Top-five boards: boards="wr.tshare,wr.epat,qb.epadb" (group.metric) or "ppr" (season PPR points). */
	private static function b_leaders( $a ) {
		$keys = array_filter( array_map( 'trim', explode( ',', strtolower( (string) ( $a['boards'] ?? 'wr.tshare,wr.epat,qb.epadb' ) ) ) ) );
		$top = max( 3, min( 10, (int) ( $a['top'] ?? 5 ) ) ); $L = DBD_Leaders::file(); $P = (array) ( $L['players'] ?? array() ); $h = '';
		foreach ( $keys as $key ) {
			if ( $key === 'ppr' ) {
				$rows = array_slice( (array) ( $L['fan']['ppr']['tot'] ?? array() ), 0, $top ); $title = 'PPR points'; $sub = 'season leaders, every position';
				$val = function ( $r ) { return DBD_Fantasy::num( $r[1] ); };
			} else {
				$pp = explode( '.', $key ); if ( count( $pp ) !== 2 ) { continue; }
				$g = DBD_Leaders::data_group( $pp[0] ); $df = DBD_Leaders::def( $g, 'g', $pp[1] ); if ( ! $df ) { continue; }
				$rows = array_slice( DBD_Leaders::rows( $g, 'g', $pp[1], null, DBD_Leaders::pos_of( $pp[0] ) ), 0, $top ); $title = $df[0]; $sub = DBD_Leaders::sub( $df ) ?: DBD_Leaders::pool( $pp[0] );
				$val = function ( $r ) use ( $df ) { return DBD_Leaders::fmt( 'g', $df[2], $r[1] ); };
			}
			if ( ! $rows ) { continue; }
			$li = '';
			foreach ( $rows as $i => $r ) {
				$p = $P[ $r[0] ] ?? null; if ( ! $p ) { continue; }
				list( $name, $team, $pos, $num, $photo ) = array_pad( (array) $p, 5, null );
				$li .= '<li><span class="n">' . ( $i + 1 ) . '</span>' . ( $team ? DBD_Marks::player( $team, $num, 32, $photo ) : '' ) . '<span><b><a href="' . self::u( array( 'player' => $r[0] ) ) . '">' . esc_html( $name ) . '</a></b><small>' . esc_html( ( $team ? self::nick( $team ) . ' · ' : '' ) . $pos ) . '</small></span><em>' . esc_html( $val( $r ) ) . '</em></li>';
			}
			$h .= '<div class="hb-board"><h3>' . esc_html( $title ) . '</h3><p class="sub">' . esc_html( $sub ) . '</p><ol class="hb-list">' . $li . '</ol></div>';
		}
		return self::wrap( 'leaders', $h );
	}

	/** The 32 teams by division, best record first, each to its team page. */
	private static function b_teams( $a ) {
		$T = DBD_Data::teams(); $h = '';
		foreach ( array( 'AFC', 'NFC' ) as $c ) {
			foreach ( array( 'East', 'North', 'South', 'West' ) as $d ) {
				$div = $c . ' ' . $d; $ts = array_keys( array_filter( $T, function ( $t ) use ( $div ) { return ( $t['div'] ?? '' ) === $div; } ) );
				if ( ! $ts ) { continue; }
				usort( $ts, function ( $x, $y ) { return DBD_Data::win_pct( $y ) <=> DBD_Data::win_pct( $x ); } );
				$h .= '<div class="hb-div"><h4>' . esc_html( $div ) . '</h4>';
				foreach ( $ts as $t ) { $h .= '<a href="' . self::u( array( 'team' => $t ) ) . '">' . DBD_Marks::tile( $t, 30 ) . '<span>' . esc_html( self::nick( $t ) ) . '</span><em>' . esc_html( DBD_Data::record_text( $t ) ) . '</em></a>'; }
				$h .= '</div>';
			}
		}
		return self::wrap( 'teams', $h );
	}

	/** The email, as the latest finished week would send it. */
	private static function b_newsletter_preview( $a ) {
		$it = self::issue(); if ( ! $it ) { return ''; }
		$ps = '';
		foreach ( $it['lines'] as $l ) { $ps .= '<p><b>' . esc_html( $l[0] ) . ':</b> ' . esc_html( $l[1] ) . '</p>'; }
		return self::wrap( 'newsletter-preview', '<div class="hb-mail"><div class="mh"><span>From: Down by Down</span><span>Subject: <b>' . esc_html( $it['subject'] ) . '</b></span></div><div class="mb"><p class="mk">' . esc_html( $it['label'] ) . ' in review</p><h4>' . esc_html( $it['head'] ) . '</h4>' . $ps . '<a class="mbtn" href="' . esc_url( $it['url'] ) . '">Read the full week</a></div></div>' );
	}

	/** Six questions, marked up as FAQPage. */
	private static function b_faq( $a ) {
		$h = ''; $qa = array();
		foreach ( self::faqs() as $i => $f ) {
			$h .= '<details' . ( $i < 2 ? ' open' : '' ) . '><summary>' . esc_html( $f[0] ) . '</summary><p>' . esc_html( $f[1] ) . '</p></details>';
			$qa[] = array( '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f[1] ) );
		}
		$ld = '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $qa ), JSON_UNESCAPED_SLASHES ) . '</script>';
		return self::wrap( 'faq', '<div class="hb-qa">' . $h . '</div>' . $ld );
	}

	public static function faqs() {
		return array(
			array( 'What is EPA?', 'Expected points added: how much a play changed a team\'s expected score, given the down, distance and field position. A 6-yard gain on 3rd and 5 is worth more than on 3rd and 15. It is the fairest single number for comparing plays.' ),
			array( 'Where does the data come from?', 'Public play-by-play from nflverse, the open NFL data project, plus their weekly player stats, snap counts and injury reports. Nothing is scraped from paid services.' ),
			array( 'How often does it update?', 'Three times a day during the season. Most games are charted within hours of the final whistle; player stat lines follow when nflverse posts them.' ),
			array( 'Is it free?', 'Yes. No account, no paywall, no ads.' ),
			array( 'Do you make picks?', 'No. Down by Down shows how teams and players actually play and how matchups line up. What you do with that is up to you.' ),
			array( 'Can I see past seasons?', 'Yes. Every finished season is kept as it ended: its teams, players, games and the playoffs.' ),
		);
	}

	/* ================================================================== the week in review, as an email and a feed item */

	/** A finished week's issue: subject, headline, the swing, the play, the risers, its page and card. Null when the week isn't finished. */
	public static function issue( $n = null ) {
		if ( $n === null ) { list( $n, $W ) = self::latest( true ); } else { $W = DBD_Week::file( $n ); }
		if ( ! $W || empty( $W['complete'] ) ) { return null; }
		$lab = DBD_Router::week_label( $n );
		$fin = array_values( array_filter( (array) $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final' && ! empty( $g['win'] ); } ) );
		$nu = 0; $nc = 0; $no = 0; $up = null;
		foreach ( (array) $W['games'] as $g ) { $tg = self::tags( $g ); if ( isset( $tg['comeback'] ) ) { $nc++; } if ( ! empty( $g['ot'] ) ) { $no++; } }
		foreach ( $fin as $g ) { if ( isset( self::tags( $g )['upset'] ) ) { $nu++; if ( ! $up || $g['pre_w'] < $up['pre_w'] ) { $up = $g; } } }
		if ( $up ) { list( $w, $l ) = self::wl( $up ); $subject = $lab . ': ' . self::nick( $w ) . ' stun ' . self::nick( $l ) . ' from ' . self::pct( $up['pre_w'] ) . '%'; }
		else {
			$wd = $fin; usort( $wd, function ( $x, $y ) { return ( $y['drama'] ?? 0 ) <=> ( $x['drama'] ?? 0 ); } );
			$subject = $wd ? $lab . ': ' . self::nick( $wd[0]['win'] ) . ' win the wildest game' : $lab . ' in review';
		}
		$pl = function ( $k, $one, $many ) { return $k . ' ' . ( $k === 1 ? $one : $many ); };
		$lines = array();
		$sw = $W['swings'][0] ?? null; if ( $sw ) { $lines[] = array( 'The swing', $sw['d'] . ' (' . self::nick( $sw['t'] ) . ', ' . $sw['c'] . ', +' . round( (float) $sw['v'] ) . '%)' ); }
		$tp = $W['plays'][0] ?? null; if ( $tp ) { $lines[] = array( 'The play', self::clean( $tp['d'] ) . ' (+' . number_format( (float) $tp['e'], 2 ) . ' EPA)' ); }
		$ri = array_slice( (array) ( $W['movers']['targets']['up'] ?? array() ), 0, 3 );
		if ( $ri ) { $lines[] = array( 'Rising', implode( ', ', array_map( function ( $m ) { return $m['name']; }, $ri ) ) . ( count( $ri ) === 1 ? ' saw his' : ' saw their' ) . ' target share jump' ); }
		$dates = array_filter( array_map( function ( $g ) { return $g['date'] ?? ''; }, DBD_Data::week_games( $n ) ) ); sort( $dates );
		return array(
			'week' => (int) $n, 'label' => $lab, 'subject' => $subject,
			'head' => $pl( $nu, 'upset', 'upsets' ) . ', ' . $pl( $nc, 'comeback', 'comebacks' ) . ', ' . $pl( $no, 'overtime', 'overtimes' ),
			'lines' => $lines, 'url' => DBD_Router::url( array( 'view' => 'week', 'week' => (int) $n ) ),
			'card' => DBD_Card::available() ? DBD_Card::url( 'page-week-' . (int) $n ) : '',
			'date' => $dates ? end( $dates ) : '',
		);
	}

	public static function feed() {
		DBD_Data::use_season( null );
		header( 'Content-Type: application/rss+xml; charset=UTF-8' );
		echo self::feed_xml();  // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts
	}

	/** RSS 2.0: one item per finished week, newest first. Dated the morning after the week's last game, so a Tuesday send picks it up. */
	public static function feed_xml() {
		$x = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' ); };
		$items = ''; $newest = 0;
		foreach ( array_slice( array_reverse( DBD_Week::weeks() ), 0, 20 ) as $n ) {
			$it = self::issue( $n ); if ( ! $it ) { continue; }
			$ts = 0;
			if ( $it['date'] ) { try { $d = new DateTime( $it['date'] . ' 06:00', new DateTimeZone( 'America/New_York' ) ); $d->modify( '+1 day' ); $ts = $d->getTimestamp(); } catch ( Exception $e ) { $ts = 0; } }
			$newest = max( $newest, $ts );
			$body = ( $it['card'] ? '<p><a href="' . esc_url( $it['url'] ) . '"><img src="' . esc_url( $it['card'] ) . '" alt="' . esc_attr( $it['label'] . ' in review' ) . '" width="600" height="315"></a></p>' : '' ) . '<h2>' . esc_html( $it['head'] ) . '</h2>';
			foreach ( $it['lines'] as $l ) { $body .= '<p><b>' . esc_html( $l[0] ) . ':</b> ' . esc_html( $l[1] ) . '</p>'; }
			$body .= '<p><a href="' . esc_url( $it['url'] ) . '">Read the full week</a></p>';
			$items .= '<item><title>' . $x( $it['subject'] ) . '</title><link>' . $x( $it['url'] ) . '</link><guid isPermaLink="true">' . $x( $it['url'] ) . '</guid>'
				. ( $ts ? '<pubDate>' . gmdate( DATE_RSS, $ts ) . '</pubDate>' : '' ) . '<description><![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', $body ) . ']]></description></item>' . "\n";
		}
		$self = function_exists( 'get_feed_link' ) ? get_feed_link( self::FEED ) : home_url( '/feed/' . self::FEED . '/' );
		return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>'
			. '<title>Down by Down: the week in review</title><link>' . $x( home_url( '/' ) ) . '</link>'
			. '<atom:link href="' . $x( $self ) . '" rel="self" type="application/rss+xml"/>'
			. '<description>The upsets, the comebacks, the plays that swung each game and who moved, after every NFL week.</description><language>en-us</language>'
			. ( $newest ? '<lastBuildDate>' . gmdate( DATE_RSS, $newest ) . '</lastBuildDate>' : '' ) . "\n" . $items . '</channel></rss>' . "\n";
	}
}
