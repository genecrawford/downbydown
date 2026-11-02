<?php
/**
 * Live blocks for pages built in the theme (SCOPE §45): [dbd_block type="…"] draws a piece of this week's data anywhere, so a Divi page
 * (the marketing home) can carry live parts while its own layout, words and styling stay in Divi. Each block shows the current season,
 * picks its own subject each week, is cached until the next data build, and renders nothing when its data is missing.
 *   hero-map  the week's most-targeted player: photo, line, every target on the field, a link to his page (player="id" pins one)
 *   games     this week's games as score tiles, games still to play first, the next one flagged
 *   counts    one line: snaps charted, games, players, offenses and defenses
 * Styles: assets/blocks.css. The blocks inherit the surrounding text color for their headings and lines, so they sit on any background.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Blocks {
	const TYPES = array( 'hero-map', 'games', 'counts' );

	public static function init() {
		add_shortcode( 'dbd_block', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp', array( __CLASS__, 'detect' ), 20 );
	}

	/** A page whose content carries a block loads the styles in the head. */
	public static function detect() {
		if ( is_admin() || ! is_singular() ) { return; }
		$post = get_queried_object();
		if ( $post && strpos( (string) ( $post->post_content ?? '' ), 'dbd_block' ) !== false ) { add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) ); }
	}

	public static function enqueue() {
		wp_enqueue_style( 'dbd-fonts', 'https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap', array(), null );
		wp_enqueue_style( 'dbd-blocks', DBD_URL . 'assets/blocks.css', array( 'dbd-fonts' ), DBD_VERSION );
	}

	public static function shortcode( $atts ) {
		$a = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
		$type = strtolower( trim( (string) ( $a['type'] ?? '' ) ) );
		if ( ! in_array( $type, self::TYPES, true ) ) { return ''; }
		self::enqueue();  /* a block in a Theme Builder layout isn't in the page's content: its styles print in the footer instead */
		return self::render( $type, $a );
	}

	/** One block's HTML, cached until the next data build (hero-map reads every team's player file). */
	public static function render( $type, $a = array() ) {
		$was = DBD_Data::archived() ? DBD_Data::season() : null; DBD_Data::use_season( null );  /* blocks always show this season */
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

	private static function nick( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; }
	private static function u( $args ) { return esc_url( DBD_Router::url( $args ) ); }
	private static function wrap( $type, $inner ) { return $inner === '' ? '' : '<div class="dbd-hb hb-' . esc_attr( $type ) . '">' . $inner . '</div>'; }
	private static function tags( $g ) { $o = array(); foreach ( (array) ( $g['tags'] ?? array() ) as $t ) { $o[ $t[0] ] = $t[1]; } return $o; }

	/** The week's most-targeted player (or player="id"): photo, line, every target on the field, a link to his page. */
	private static function b_hero_map( $a ) {
		$wk = DBD_Week::default_week(); if ( ! $wk ) { return ''; }
		$pid = (string) ( $a['player'] ?? '' ); $auto = $pid === '';
		$hit = $auto ? self::most_targeted( $wk ) : self::week_line( $pid, $wk );
		if ( ! $hit ) { return ''; }
		list( $pid, $team, $w ) = $hit; $wk = (int) $w['week'];
		$p = DBD_Data::player( $pid ); if ( ! $p ) { return ''; }
		/* the plays of the team he played for that week; no play file yet (a first sync still downloading) or no plays of his in it: no block (Greptile, PR #60) */
		$pf = DBD_Data::get( 'plays/' . $team . '.json' ); if ( ! $pf || empty( $pf['keys'] ) ) { return ''; }
		$rows = array(); $pre = sprintf( '%d_%02d_', DBD_Data::season(), $wk );
		foreach ( (array) $pf['plays'] as $r ) {
			if ( count( $r ) !== count( $pf['keys'] ) ) { continue; }
			$r = array_combine( $pf['keys'], $r );
			if ( strpos( (string) $r['g'], $pre ) === 0 && ( $r['pid'] ?? '' ) === $pid && ( $r['pt'] ?? '' ) === 'pass' ) { $rows[] = $r; }
		}
		if ( ! $rows ) { return ''; }
		$lab = DBD_Router::week_label( $wk ); $sub = $p['pos'] . ' · ' . self::nick( $team ) . ' · ' . ( $auto ? 'most targets in ' . ( strpos( $lab, 'Week ' ) === 0 ? 'week ' . $wk : 'the ' . $lab ) : $lab );
		$st = array( array( (int) $w['targets'], 'targets' ), array( (int) $w['receptions'], 'caught' ), array( (int) $w['receiving_yards'], 'yards' ), array( (int) $w['receiving_tds'], 'TD' ) );
		$h = '<div class="hb-who">' . DBD_Marks::player( $team, $p['num'] ?? null, 46, $p['photo'] ?? null ) . '<span><b><a href="' . self::u( array( 'player' => $pid ) ) . '">' . esc_html( $p['name'] ) . '</a></b><small>' . esc_html( $sub ) . '</small></span></div>';
		$h .= '<div class="hb-stats">'; foreach ( $st as $s ) { $h .= '<span><b>' . (int) $s[0] . '</b>' . esc_html( $s[1] ) . '</span>'; } $h .= '</div>';
		$h .= self::field( $rows, $p['name'] . "'s targets, " . $lab . ', by depth and side of the field' );
		$h .= '<p class="hb-leg"><i class="c"></i>caught <i class="n"></i>incomplete <i class="t"></i>touchdown <a href="' . self::u( array( 'player' => $pid ) ) . '">His page</a></p>';
		return self::wrap( 'hero-map', $h );
	}

	/**
	 * [id, team, week row] of the player with the most targets in week $wk (yards break a tie). A traded player sits only in his new
	 * team's file but keeps each earlier week under the team he played for, so the week's own team is the one that counts (Greptile, PR #60).
	 */
	private static function most_targeted( $wk ) {
		$best = null; $bk = array( -1, -1 );
		foreach ( array_keys( DBD_Data::teams() ) as $t ) {
			foreach ( DBD_Data::team_players( $t ) as $p ) {
				foreach ( (array) ( $p['weeks'] ?? array() ) as $w ) {
					if ( (int) $w['week'] !== (int) $wk ) { continue; }
					$k = array( (int) ( $w['targets'] ?? 0 ), (int) ( $w['receiving_yards'] ?? 0 ) );
					if ( $k > $bk ) { $bk = $k; $best = array( $p['id'], (string) ( $w['team'] ?? $t ), $w ); }
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
		foreach ( (array) ( $rec['weeks'] ?? array() ) as $w ) { if ( (int) $w['week'] <= (int) $wk && ( ! $best || (int) $w['week'] > (int) $best['week'] ) ) { $best = $w; } }
		return $best ? array( $pid, (string) ( $best['team'] ?? $row['team'] ), $best ) : null;  /* the team he played for that week, before or after a trade */
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
}
