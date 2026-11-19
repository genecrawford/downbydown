<?php
/**
 * Pass rushers (SCOPE §79): every rusher with a regular role on one chart, pressures a game against sacks a game.
 * Read from defense/{TEAM}.json at render time (no build change): pressures are PFR's charting, sacks, QB hits and tackles for loss
 * nflverse's play-by-play. Drawn on their own Leaders tab, /leaders/rush/ (SCOPE §80; it opened 2.36.0 atop Defenses), as the pack card pack-week-N-rush (DBD_Card::draw_page's
 * scatter) with its square, and as share pack item 6 for the week the defense files run through.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Rush {
	const SNAP = 40;    /* at least this share of the snaps in the games he played */
	const TOP  = 10;    /* table rows before Show all */
	const NAMED = 8;    /* the page chart names the top eight on each axis */

	private static $rows = null;

	public static function reset() { self::$rows = null; }

	/** The week the defense files run through. */
	public static function through() {
		foreach ( array_keys( DBD_Data::teams() ) as $t ) { $d = DBD_Data::defense( $t ); if ( $d && isset( $d['through'] ) ) { return (int) $d['through']; } }
		return (int) DBD_Data::through_week();
	}

	/**
	 * The rushers: linemen and linebackers who played at least half their team's games, on 40% or more of the snaps, with at least a
	 * pressure a game. Each: id, n, t, pos, g, snap, prs, sk, qbh, tfl, x (pressures a game), y (sacks a game). Most pressures a game first.
	 */
	public static function rows() {
		if ( self::$rows !== null && ! DBD_Data::archived() ) { return self::$rows; }
		$out = array();
		foreach ( array_keys( DBD_Data::teams() ) as $t ) {
			$d = DBD_Data::defense( $t ); if ( ! $d ) { continue; } $tg = (int) ( $d['g'] ?? 0 );
			foreach ( (array) ( $d['defenders'] ?? $d['players'] ?? array() ) as $p ) {
				$g = (int) ( $p['g'] ?? 0 ); $prs = (int) ( $p['prs'] ?? 0 );
				$snap = $g ? (int) round( (float) ( $p['snap'] ?? 0 ) * max( $tg, $g ) / $g ) : 0;  /* the build divides by the team's games; this is his share in the games he played (Greptile, PR #147) */
				if ( ! in_array( $p['grp'] ?? '', array( 'DL', 'LB' ), true ) || ! $g || $g * 2 < $tg || $snap < self::SNAP || $prs < $g || empty( $p['id'] ) ) { continue; }
				$sk = (float) ( $p['sk'] ?? 0 );
				$out[] = array( 'id' => (string) $p['id'], 'n' => (string) ( $p['name'] ?? '' ), 't' => $t, 'pos' => (string) ( $p['pos'] ?? '' ), 'num' => $p['num'] ?? '', 'g' => $g, 'snap' => min( 100, $snap ), 'prs' => $prs, 'sk' => $sk,
					'qbh' => (int) ( $p['qbh'] ?? 0 ), 'tfl' => (int) ( $p['tfl'] ?? 0 ), 'x' => $prs / $g, 'y' => $sk / $g );
			}
		}
		usort( $out, function ( $a, $b ) { return array( -$a['x'], -$a['y'], $a['n'] ) <=> array( -$b['x'], -$b['y'], $b['n'] ); } );
		if ( ! DBD_Data::archived() ) { self::$rows = $out; }
		return $out;
	}

	/** The middle of the pack on each axis: [pressures a game, sacks a game]. */
	public static function medians( $rows ) {
		$m = function ( $v ) { sort( $v ); $n = count( $v ); return $n ? ( $n % 2 ? $v[ ( $n - 1 ) / 2 ] : ( $v[ $n / 2 - 1 ] + $v[ $n / 2 ] ) / 2 ) : 0; };
		return array( $m( array_column( $rows, 'x' ) ), $m( array_column( $rows, 'y' ) ) );
	}

	/** The ids to name: the top $k on each axis. */
	public static function named( $rows, $k ) {
		$a = $rows; $b = $rows; usort( $b, function ( $p, $q ) { return array( -$p['y'], -$p['x'], $p['n'] ) <=> array( -$q['y'], -$q['x'], $q['n'] ); } );
		return array_values( array_unique( array_merge( array_column( array_slice( $a, 0, $k ), 'id' ), array_column( array_slice( $b, 0, $k ), 'id' ) ) ) );
	}

	/** The leaders the read, the card and the post name: most pressures and most sacks, ties to the higher rate. */
	public static function leaders( $rows ) {
		$best = function ( $k, $rate ) use ( $rows ) { $o = null; foreach ( $rows as $r ) { if ( ! $o || array( $r[ $k ], $r[ $rate ] ) > array( $o[ $k ], $o[ $rate ] ) ) { $o = $r; } } return $o; };
		return array( 'prs' => $best( 'prs', 'x' ), 'sk' => $best( 'sk', 'y' ) );
	}

	/** The axes' tops: whole pressures, quarter sacks, with room above the highest dot. */
	public static function scale( $rows ) {
		$xm = 1; $ym = 0.25; foreach ( $rows as $r ) { $xm = max( $xm, $r['x'] ); $ym = max( $ym, $r['y'] ); }
		return array( (int) ceil( $xm + 0.4 ), ceil( $ym * 4 + 1 ) / 4 );
	}

	private static function nick( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; }
	private static function color( $t ) { $c = (string) ( DBD_Data::team( $t )['c1'] ?? '' ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '#6b6f78'; }
	private static function n1( $v ) { return number_format( (float) $v, 1 ); }
	private static function n2( $v ) { return number_format( (float) $v, 2 ); }
	private static function sk( $v ) { return rtrim( rtrim( number_format( (float) $v, 1 ), '0' ), '.' ); }

	/** The page chart through the shared one (SCOPE §80): one dot size, whole pressures across, quarter sacks up. */
	public static function svg( $rows, $W, $H, $m, $named, $px, $r, $cls ) {
		list( $xmax, $ymax ) = self::scale( $rows ); $pts = array();
		foreach ( $rows as $x ) {
			$tip = '<b>' . esc_html( $x['n'] ) . '</b> · ' . esc_html( self::nick( $x['t'] ) . ' ' . $x['pos'] ) . '<br>' . (int) $x['prs'] . ' pressures, ' . self::sk( $x['sk'] ) . ' sacks, ' . (int) $x['qbh'] . ' QB hits in ' . (int) $x['g'] . ' games · ' . (int) $x['snap'] . '% of snaps';
			$pts[] = array( 'id' => $x['id'], 'n' => $x['n'], 't' => $x['t'], 'pos' => $x['pos'], 'x' => $x['x'], 'y' => $x['y'], 'u' => 1, 'tip' => $tip );
		}
		return DBD_Scatter::svg( $pts, array( 'W' => $W, 'H' => $H, 'm' => $m, 'px' => $px, 'rmin' => $r, 'rmax' => $r, 'named' => $named, 'cls' => $cls, 'xk' => 'n', 'yk' => 'q',
			'xl' => 'Pressures a game', 'yl' => 'Sacks a game', 'label' => 'Pressures a game against sacks a game, ' . count( $rows ) . ' pass rushers', 'xr' => array( 0, $xmax, 1 ), 'yr' => array( 0, $ymax, 0.25 ), 'mid' => self::medians( $rows ) ) );
	}

	/** The band at the top of Leaders → Defenses; '' with no rushers yet. */
	public static function band() {
		$rows = self::rows(); if ( count( $rows ) < 3 ) { return ''; }
		$N = count( $rows ); $wk = self::through(); $L = self::leaders( $rows ); $rp = $L['prs']; $rs = $L['sk'];
		$say = $rp['id'] === $rs['id']
			? '<b>' . esc_html( $rp['n'] ) . '</b> (' . esc_html( self::nick( $rp['t'] ) ) . ') leads both ways: ' . (int) $rp['prs'] . ' pressures, ' . self::n1( $rp['x'] ) . ' a game, and ' . self::sk( $rs['sk'] ) . ' sacks.'
			: '<b>' . esc_html( $rp['n'] ) . '</b> (' . esc_html( self::nick( $rp['t'] ) ) . ') gets there most: ' . self::n1( $rp['x'] ) . ' pressures a game, ' . (int) $rp['prs'] . ' in all. <b>' . esc_html( $rs['n'] ) . '</b> (' . esc_html( self::nick( $rs['t'] ) ) . ') finishes most: ' . self::sk( $rs['sk'] ) . ' sacks, ' . self::n2( $rs['y'] ) . ' a game.';
		$say .= ' Up and to the right of the dashed lines is both.';
		$tb = '';
		foreach ( $rows as $i => $x ) {
			$tb .= '<tr' . ( $i >= self::TOP ? ' class="pr-x"' : '' ) . ' data-t="' . esc_attr( $x['t'] ) . '"><td class="rk">' . ( $i + 1 ) . '</td><td><a href="' . esc_url( DBD_Router::url( array( 'team' => $x['t'], 'side' => 'def' ) ) ) . '">' . esc_html( $x['n'] ) . '</a><small>' . esc_html( $x['t'] . ' · ' . $x['pos'] . ' · ' . $x['g'] . ' games' ) . '</small></td>'
				. '<td><b>' . self::n1( $x['x'] ) . '</b></td><td class="c-p">' . (int) $x['prs'] . '</td><td>' . esc_html( self::sk( $x['sk'] ) ) . '</td><td class="c-h">' . (int) $x['qbh'] . '</td><td class="c-h">' . (int) $x['tfl'] . '</td><td class="c-s">' . (int) $x['snap'] . '%</td></tr>';
		}
		$small = self::named( $rows, 3 );
		return '<section class="dbd-band" id="ld-rush"><h2 class="dbd-bandh">Pass rushers<span>pressures against sacks, every rusher with a regular role</span></h2>'
			. '<section class="dbd-panel pr-panel" data-chart="rush"><div class="dbd-ph"><span class="dbd-lab"><b>Through Week ' . (int) $wk . '</b> · ' . $N . ' rushers, a game each</span>' . DBD_Scatter::menu( $rows ) . '</div>'
			. '<p class="dbd-mv-say">' . $say . '</p>'
			. '<div class="pr-chart" id="pr-chart">' . self::svg( $rows, 960, 540, array( 64, 24, 16, 48 ), self::named( $rows, self::NAMED ), 12, 6, 'wide' ) . self::svg( $rows, 380, 420, array( 46, 10, 10, 38 ), $small, 11, 4, 'narrow' ) . '</div>'
			. '<div class="dbd-tw"><table class="pr-t"><thead><tr><th></th><th>Rusher</th><th>A game</th><th class="c-p">Pressures</th><th>Sacks</th><th class="c-h">QB hits</th><th class="c-h">TFL</th><th class="c-s">Snaps</th></tr></thead><tbody>' . $tb . '</tbody></table></div>'
			. ( $N > self::TOP ? '<button class="dbd-cbtn pr-more" type="button" id="pr-more">Show all ' . $N . '</button>' : '' )
			. '<p class="dbd-note">Pressures are Pro Football Reference\'s charting: the hurries, hits and sacks it saw. Sacks, QB hits and tackles for loss come from nflverse\'s play-by-play, so the two don\'t always add up the same way. Rushers are linemen and linebackers who played at least half their team\'s games, on ' . self::SNAP . '% or more of the snaps, with at least one pressure a game. The dashed lines are the middle of the pack. The team you highlight is remembered on this device and carries to every chart.</p></section></section>';
	}

	/* ------------------------------------------------------------ the card and the share pack item */

	/** The pack card's spec (draw_page with a scatter) for week $n: only the week the defense files run through, so an older pack never shows later numbers. */
	public static function card_spec( $n ) {
		$rows = self::rows(); if ( count( $rows ) < 3 || (int) $n !== self::through() ) { return null; }
		$L = self::leaders( $rows ); $season = (int) DBD_Data::season(); list( $xmax, $ymax ) = self::scale( $rows ); list( $mx, $my ) = self::medians( $rows );
		$pts = array(); foreach ( $rows as $x ) { $pts[] = array( $x['id'], $x['x'], $x['y'], $x['n'] ); }
		return array( 'season' => $season, 'through' => (int) $n, 'kicker' => $season . ' season · through week ' . (int) $n . ' · defense', 'title' => array( 'Pass', '!rushers' ),
			'say' => 'Pressures a game against sacks a game, every rusher with a regular role.', 'board' => null, 'dots' => null, 'big' => '', 'foot' => 'Data: nflverse, PFR',
			'tiles' => array( array( 'Most pressures', (string) (int) $L['prs']['prs'], $L['prs']['n'] . ', ' . $L['prs']['t'] ), array( 'Most sacks', self::sk( $L['sk']['sk'] ), $L['sk']['n'] . ', ' . $L['sk']['t'] ), array( 'Rushers', (string) count( $rows ), 'with a regular role, a game each' ) ),
			'scatter' => array( 'pts' => $pts, 'named' => self::named( $rows, 3 ), 'xmax' => $xmax, 'ymax' => $ymax, 'mx' => $mx, 'my' => $my ) );
	}

	/** Share pack item 6 for week $n, or null when the defense files aren't at week $n. */
	public static function item( $n ) {
		if ( ! self::card_spec( $n ) ) { return null; }
		$rows = self::rows(); $L = self::leaders( $rows ); $rp = $L['prs']; $rs = $L['sk']; $N = count( $rows ); $lab = 'Week ' . (int) $n;
		$title = 'Every NFL pass rusher through ' . $lab . ', pressures a game against sacks a game (' . $N . ' rushers with a regular role)';
		$short = $rp['id'] === $rs['id']
			? 'Pass rushers through ' . $lab . ': ' . $rp['n'] . ' leads both ways (' . self::n1( $rp['x'] ) . ' pressures a game, ' . self::sk( $rs['sk'] ) . ' sacks). All ' . $N . ' on one chart.'
			: 'Pass rushers through ' . $lab . ': ' . $rp['n'] . ' gets there most (' . self::n1( $rp['x'] ) . ' pressures a game) and ' . $rs['n'] . ' finishes most (' . self::sk( $rs['sk'] ) . ' sacks). All ' . $N . ' on one chart.';
		$link = DBD_Router::url( array( 'view' => 'leaders', 'g' => 'rush' ) );  /* their own tab (SCOPE §80) */
		return array( 'k' => 'rush', 'h' => 'Pass rushers', 'why' => 'Every pass rusher with a regular role on one chart: pressures a game against sacks a game.',
			'card' => 'pack-week-' . (int) $n . '-rush', 'file' => 'dbd-week-' . (int) $n . '-pass-rushers.png', 'fits' => array_values( array_unique( array_filter( array( 'r/nfl', DBD_Pack::sub( $rp['t'] ), DBD_Pack::sub( $rs['t'] ) ) ) ) ),
			'title' => $title, 'comment' => 'Every rusher, with a team highlight and the full list: ' . $link, 'post' => DBD_Pack::post( $short, $short, $link ), 'tl' => $short, 'ts' => $short, 'link' => $link,
			'lead' => DBD_Pack::last( $rp['n'] ) . '’s ' . (int) $rp['prs'] . ' pressures',
			'teams' => array_values( array_unique( array( $rp['t'], $rs['t'] ) ) ), 'sq' => 'ig-week-' . (int) $n . '-rush', 'sqfile' => 'dbd-week-' . (int) $n . '-pass-rushers-square.png' );
	}
}
