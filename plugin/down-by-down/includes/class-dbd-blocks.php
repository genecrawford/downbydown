<?php
/**
 * Live blocks for pages built in the theme (SCOPE §45): [dbd_block type="…"] draws a piece of this week's data anywhere, so a Divi page
 * (the marketing home) can carry live parts while its own layout, words and styling stay in Divi. Each block shows the current season,
 * picks its own subject each week, is cached until the next data build, and renders nothing when its data is missing.
 *   hero-map  the week's most-targeted player: photo, line, every target on the field, a link to his page (player="id" pins one)
 *   games     this week's games as score tiles, games still to play first, the next one flagged
 *   counts    one line: snaps charted, games, players, offenses and defenses
 *   week-highlights  the latest week in review in three cards: the play of the week, the swing of the week, the upsets and the wildest game
 *   matchup   the game of the week (picked the way the hub picks it) with its biggest edge, over a four-row offense vs defense
 *   player-card  a player's share card image linked to his page: the week's biggest riser in target share (player="id" pins one)
 *   movers    the biggest jumps in target share in the latest finished week, before and after (count="4", 1 to 5)
 * Styles: assets/blocks.css. The blocks inherit the surrounding text color for their headings and lines, so they sit on any background.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Blocks {
	const TYPES = array( 'hero-map', 'games', 'counts', 'week-highlights', 'matchup', 'player-card', 'movers' );

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
	private static function pct( $v ) { return round( (float) $v / 10 ); }
	/** A play's description without the tacklers' brackets: "…, TOUCHDOWN [J.McMillian]." reads "…, TOUCHDOWN." */
	private static function clean( $d ) { return trim( (string) preg_replace( '/\s*\[[^\]]*\]/', '', (string) $d ) ); }
	/** A final game's winner, loser and both scores, the winner's first; a tie keeps away then home. */
	private static function wl( $g ) {
		$w = (string) ( $g['win'] ?? '' ); if ( $w === '' ) { return array( $g['away'], $g['home'], (int) $g['a'], (int) $g['h'] ); }
		$aw = $w === $g['away'];
		return array( $w, $aw ? $g['home'] : $g['away'], (int) ( $aw ? $g['a'] : $g['h'] ), (int) ( $aw ? $g['h'] : $g['a'] ) );
	}
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

	/**
	 * The latest week in review in three cards: the play of the week (by EPA), the swing of the week (the biggest jump in win
	 * probability), and the upsets with each winner's chance at kickoff plus the wildest other game. The week is the latest with a
	 * final game, so a Sunday night shows that Sunday.
	 */
	private static function b_week_highlights( $a ) {
		$n = DBD_Week::default_week(); $W = $n ? DBD_Week::file( $n ) : null; if ( ! $W ) { return ''; }
		$h = '';
		$tp = $W['plays'][0] ?? null;
		if ( $tp ) {
			$h .= '<a class="hb-wk" href="' . self::u( array( 'game' => $tp['g'] ) ) . '"><i>The play of the week</i><span class="mk">' . DBD_Marks::tile( $tp['t'], 40 ) . '</span><h3>' . esc_html( self::clean( $tp['d'] ) ) . '</h3><p><b>' . ( $tp['e'] >= 0 ? '+' : '−' ) . number_format( abs( (float) $tp['e'] ), 2 ) . ' EPA</b> · ' . esc_html( self::nick( $tp['t'] ) . ' vs ' . self::nick( $tp['o'] ) . ', ' . $tp['c'] ) . '</p></a>';
		}
		$sw = $W['swings'][0] ?? null;
		if ( $sw ) {
			$h .= '<a class="hb-wk" href="' . self::u( array( 'game' => $sw['g'] ) ) . '"><i>The swing of the week</i><span class="mk">' . DBD_Marks::tile( $sw['t'], 40 ) . '</span><h3>' . esc_html( self::clean( $sw['d'] ) ) . '</h3><p><b>+' . round( (float) $sw['v'] ) . '%</b> win probability for the ' . esc_html( self::nick( $sw['t'] ) . ', ' . $sw['c'] ) . '</p></a>';
		}
		$fin = array_values( array_filter( (array) $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final'; } ) );  /* ties stay in for the wildest game (Greptile, PR #61) */
		$ups = array_values( array_filter( $fin, function ( $g ) { return ! empty( $g['win'] ) && isset( self::tags( $g )['upset'] ); } ) );
		usort( $ups, function ( $x, $y ) { return $x['pre_w'] <=> $y['pre_w']; } ); $ups = array_slice( $ups, 0, 3 );
		$shown = array_map( function ( $g ) { return $g['id']; }, $ups );  /* the wildest is another game when there is one */
		$wd = array_values( array_filter( $fin, function ( $g ) use ( $shown ) { return ! in_array( $g['id'], $shown, true ); } ) ) ?: $fin;
		usort( $wd, function ( $x, $y ) { return array( $y['lc'] ?? 0, $y['drama'] ?? 0 ) <=> array( $x['lc'] ?? 0, $x['drama'] ?? 0 ); } ); $wild = $wd[0] ?? null;
		if ( $ups || $wild ) {
			$li = '';
			foreach ( $ups as $g ) { list( $w, $l, $ws, $ls ) = self::wl( $g ); $li .= '<li><a href="' . self::u( array( 'game' => $g['id'] ) ) . '"><b>' . esc_html( self::nick( $w ) . ' ' . $ws ) . '</b>, ' . esc_html( self::nick( $l ) . ' ' . $ls ) . '</a><small>' . self::pct( $g['pre_w'] ) . '% at kickoff</small></li>'; }
			$wl = ''; $mk = '';
			if ( $wild ) { list( $w, $l, $ws, $ls ) = self::wl( $wild ); $mk = empty( $wild['win'] ) ? '' : '<span class="mk">' . DBD_Marks::tile( $w, 30 ) . '</span>'; $wl = '<p class="wild"><span>Wildest game</span><a href="' . self::u( array( 'game' => $wild['id'] ) ) . '"><b>' . esc_html( self::nick( $w ) . ' ' . $ws . ', ' . self::nick( $l ) . ' ' . $ls ) . '</b>' . ( ! empty( $wild['ot'] ) ? ' in overtime' : '' ) . '</a></p>'; }
			$h .= '<div class="hb-wk ups"><i>' . ( $ups ? 'Upsets' : 'No upsets' ) . '</i>' . $mk . ( $li ? '<ul>' . $li . '</ul>' : '' ) . $wl . '</div>';
		}
		return self::wrap( 'week-highlights', $h );
	}

	/**
	 * The game of the week, picked the way the hub picks it (the open game with the biggest edge), over a four-row head to head of that
	 * edge's offense against the defense. When every game of the current week is final, next week's games stand in.
	 */
	private static function b_matchup( $a ) {
		if ( DBD_Data::through_week() < 1 ) { return ''; }
		$cw = DBD_Data::current_week(); $gotw = null; $best = null;
		foreach ( array( $cw, $cw + 1 ) as $wk ) {
			foreach ( DBD_Data::week_games( $wk ) as $g ) {
				if ( DBD_Router::is_final( $g ) ) { continue; }
				$b = DBD_Takeaways::best_edge( $g['away'], $g['home'] );
				if ( ! $best || $b['s'] > $best['s'] ) { $gotw = $g; $best = $b; }
			}
			if ( $gotw ) { break; }
		}
		if ( ! $gotw ) { return ''; }
		$O = $best['O']; $D = $best['D']; $cards = DBD_Takeaways::cards( $O, $D ); $k = DBD_Data::kickoff( $gotw ); $d = strtotime( (string) ( $gotw['date'] ?? '' ) );
		$kick = implode( ' · ', array_filter( array( 'Game of the week', trim( ( $d ? gmdate( 'l', $d ) . ' ' : '' ) . ( $k['et'] ?? '' ) ), (string) ( $gotw['stadium'] ?? '' ) ) ) );
		$side = function ( $t ) { return '<span>' . DBD_Marks::tile( $t, 54 ) . '<b>' . esc_html( self::nick( $t ) ) . '</b><small>' . esc_html( DBD_Data::record_text( $t ) ) . '</small></span>'; };
		$tot  = isset( $gotw['total'] ) && $gotw['total'] !== null && $gotw['total'] !== '' ? 'O/U ' . $gotw['total'] : '';
		$line = array_filter( array( DBD_Router::line_text( $gotw ), $tot ) );
		$h = '<div class="hb-gotw"><p class="k">' . esc_html( $kick ) . '</p><div class="mt">' . $side( $gotw['away'] ) . '<em>at</em>' . $side( $gotw['home'] ) . '</div>' . ( $line ? '<p class="ln">' . esc_html( implode( ' · ', $line ) ) . '</p>' : '' );
		if ( $cards ) { $h .= '<p class="say"><i>' . esc_html( $cards[0][0] ) . '</i>' . esc_html( $cards[0][3] ) . '</p>'; }
		$h .= '<a class="hb-link" href="' . self::u( array( 'game' => $gotw['id'], 'side' => $O === $gotw['home'] ? 'home' : 'away' ) ) . '">Open the matchup</a></div>';
		/* each bar grows from the middle toward the side with the edge: lower rank is better on both sides, so a defense ranked
		   above the offense pushes right, an offense ranked above the defense pushes left */
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

	/** A week as a sentence names it: "Week 8", or "the Wild Card round", "the Super Bowl" in the playoffs. */
	private static function week_name( $wk ) { $l = DBD_Router::week_label( $wk ); return strpos( $l, 'Week ' ) === 0 ? $l : 'the ' . $l . ( $l === 'Super Bowl' ? '' : ' round' ); }

	/** The risers in target share from the Players page's movers file (the latest week with every game final), and that week. */
	private static function movers() {
		$mv = DBD_Data::get( 'players/movers.json' );
		$up = array_values( array_filter( (array) ( $mv['targets']['up'] ?? array() ), function ( $m ) { return ! empty( $m['id'] ) && DBD_Data::player( (string) $m['id'] ); } ) );
		return array( $up, (int) ( $mv['week'] ?? 0 ) );
	}

	/** A player's share card (default: the week's biggest riser in target share), linked to his page. No card drawing on the host: no block. */
	private static function b_player_card( $a ) {
		if ( ! DBD_Card::available() ) { return ''; }
		$pid = (string) ( $a['player'] ?? '' );
		if ( $pid === '' ) { $pid = (string) ( self::movers()[0][0]['id'] ?? '' ); }
		$p = $pid !== '' ? DBD_Data::player( $pid ) : null; if ( ! $p ) { return ''; }
		return self::wrap( 'player-card', '<a href="' . self::u( array( 'player' => $pid ) ) . '"><img src="' . esc_url( DBD_Card::url( $pid ) ) . '" alt="' . esc_attr( $p['name'] . "'s share card" ) . '" width="1200" height="630" loading="lazy" decoding="async"></a>' );
	}

	/** The biggest jumps in target share in the latest finished week: his share before that week, and that week's. The data keeps five. */
	private static function b_movers( $a ) {
		list( $up, $wk ) = self::movers(); $up = array_slice( $up, 0, max( 1, min( 5, (int) ( $a['count'] ?? 4 ) ) ) ); if ( ! $up ) { return ''; }
		$li = '';
		foreach ( $up as $m ) {
			$li .= '<li>' . DBD_Marks::player( $m['team'], $m['num'] ?? null, 40, $m['photo'] ?? null ) . '<span><b><a href="' . self::u( array( 'player' => $m['id'] ) ) . '">' . esc_html( $m['name'] ) . '</a></b><small>' . esc_html( ( $m['pos'] ?? '' ) . ' · ' . self::nick( $m['team'] ) ) . '</small></span><em>' . round( (float) $m['before'] ) . '% <i aria-hidden="true">→</i><span class="screen-reader-text"> to </span> <strong>' . round( (float) $m['now'] ) . '%</strong></em></li>';
		}
		return self::wrap( 'movers', '<div class="hb-panel"><p class="k">' . esc_html( ( $wk ? 'Rising in ' . self::week_name( $wk ) : 'Rising' ) . ' · share of targets' ) . '</p><ul class="hb-list">' . $li . '</ul></div>' );
	}
}
