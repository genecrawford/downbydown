<?php
/**
 * Pass rushers (SCOPE §79): every rusher with a regular role on one chart, pressures a game against sacks a game.
 * Read from defense/{TEAM}.json at render time (no build change): pressures are PFR's charting, sacks, QB hits and tackles for loss
 * nflverse's play-by-play. Drawn as a band at the top of Leaders → Defenses, as the pack card pack-week-N-rush (DBD_Card::draw_page's
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
				if ( ! in_array( $p['grp'] ?? '', array( 'DL', 'LB' ), true ) || ! $g || $g * 2 < $tg || (int) ( $p['snap'] ?? 0 ) < self::SNAP || $prs < $g || empty( $p['id'] ) ) { continue; }
				$sk = (float) ( $p['sk'] ?? 0 );
				$out[] = array( 'id' => (string) $p['id'], 'n' => (string) ( $p['name'] ?? '' ), 't' => $t, 'pos' => (string) ( $p['pos'] ?? '' ), 'num' => $p['num'] ?? '', 'g' => $g, 'snap' => (int) $p['snap'], 'prs' => $prs, 'sk' => $sk,
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

	/**
	 * Where each named rusher's label goes: right of the dot, else left, else above it ending at the dot, else below, the first spot that stays
	 * inside the box and covers no other label and no dot. $pts: id => [x, y] in drawing units; $w: a function giving a name's width.
	 * Returns id => [x, y baseline, 'start'|'end'].
	 */
	public static function place( $rows, $named, $pts, $w, $px, $box, $r ) {
		$taken = array(); $out = array();
		$hit = function ( $a ) use ( &$taken, $pts, $r ) {
			foreach ( $taken as $b ) { if ( $a[0] < $b[2] && $b[0] < $a[2] && $a[1] < $b[3] && $b[1] < $a[3] ) { return true; } }
			foreach ( $pts as $id => $p ) { if ( $id === $a[4] ) { continue; } if ( $p[0] + $r > $a[0] && $p[0] - $r < $a[2] && $p[1] + $r > $a[1] && $p[1] - $r < $a[3] ) { return true; } }
			return false;
		};
		$order = array_values( array_filter( $rows, function ( $x ) use ( $named ) { return in_array( $x['id'], $named, true ); } ) );
		foreach ( $order as $row ) {
			$id = $row['id']; list( $cx, $cy ) = $pts[ $id ]; $tw = $w( $row['n'] ); $g = $r + 4; $h = $px;
			$cands = array( array( $cx + $g, $cy + $px * 0.35, 'start' ), array( $cx - $g, $cy + $px * 0.35, 'end' ), array( $cx + $r, $cy - $r - 4, 'end' ), array( $cx + $r, $cy + $r + $px, 'end' ), array( $cx - $r, $cy - $r - 4, 'start' ) );
			$pick = null;
			foreach ( $cands as $c ) {
				$x0 = $c[2] === 'start' ? $c[0] : $c[0] - $tw; $a = array( $x0, $c[1] - $h * 0.8, $x0 + $tw, $c[1] + $h * 0.2, $id );
				if ( $a[0] < $box[0] || $a[2] > $box[2] || $a[1] < $box[1] || $a[3] > $box[3] ) { continue; }
				if ( ! $hit( $a ) ) { $pick = array( $c, $a ); break; }
			}
			if ( ! $pick ) { $c = $cands[0]; $x0 = $c[0]; if ( $x0 + $tw > $box[2] ) { $c = $cands[1]; $x0 = $c[0] - $tw; } $pick = array( $c, array( $x0, $c[1] - $h * 0.8, $x0 + $tw, $c[1] + $h * 0.2, $id ) ); }  /* nowhere clear: right, or left at the edge */
			$taken[] = $pick[1]; $out[ $id ] = $pick[0];
		}
		return $out;
	}

	private static function nick( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; }
	private static function color( $t ) { $c = (string) ( DBD_Data::team( $t )['c1'] ?? '' ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '#6b6f78'; }
	private static function n1( $v ) { return number_format( (float) $v, 1 ); }
	private static function n2( $v ) { return number_format( (float) $v, 2 ); }
	private static function sk( $v ) { return rtrim( rtrim( number_format( (float) $v, 1 ), '0' ), '.' ); }

	/** The page chart: $W × $H, margins [left, right, top, bottom], named rushers labelled; every rusher carries a hidden name the team highlight shows. */
	public static function svg( $rows, $W, $H, $m, $named, $px, $r, $cls ) {
		list( $xl, $xr, $yt, $yb ) = $m; list( $xmax, $ymax ) = self::scale( $rows ); list( $mx, $my ) = self::medians( $rows );
		$X = function ( $v ) use ( $xl, $xr, $W, $xmax ) { return $xl + ( $W - $xl - $xr ) * $v / $xmax; }; $Y = function ( $v ) use ( $yt, $yb, $H, $ymax ) { return $H - $yb - ( $H - $yt - $yb ) * $v / $ymax; };
		$f = 'font-family="IBM Plex Mono,monospace" font-size="' . $px . '"';
		$o = '<svg class="pr-svg ' . $cls . '" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . esc_attr( 'Pressures a game against sacks a game, ' . count( $rows ) . ' pass rushers' ) . '">';
		for ( $v = 0; $v <= $xmax; $v++ ) { $x = round( $X( $v ), 1 ); $o .= '<line x1="' . $x . '" x2="' . $x . '" y1="' . $yt . '" y2="' . ( $H - $yb ) . '" class="pr-g"/><text x="' . $x . '" y="' . ( $H - $yb + $px + 6 ) . '" text-anchor="middle" ' . $f . ' class="pr-tk">' . $v . '</text>'; }
		for ( $v = 0; $v <= $ymax + 1e-9; $v += 0.25 ) { $y = round( $Y( $v ), 1 ); $o .= '<line x1="' . $xl . '" x2="' . ( $W - $xr ) . '" y1="' . $y . '" y2="' . $y . '" class="pr-g"/><text x="' . ( $xl - 8 ) . '" y="' . round( $y + $px * 0.35, 1 ) . '" text-anchor="end" ' . $f . ' class="pr-tk">' . self::n2( $v ) . '</text>'; }
		$o .= '<line x1="' . round( $X( $mx ), 1 ) . '" x2="' . round( $X( $mx ), 1 ) . '" y1="' . $yt . '" y2="' . ( $H - $yb ) . '" class="pr-md"/><line x1="' . $xl . '" x2="' . ( $W - $xr ) . '" y1="' . round( $Y( $my ), 1 ) . '" y2="' . round( $Y( $my ), 1 ) . '" class="pr-md"/>';
		$o .= '<text x="' . round( ( $xl + $W - $xr ) / 2 ) . '" y="' . ( $H - 6 ) . '" text-anchor="middle" ' . $f . ' class="pr-ax">PRESSURES A GAME</text><text transform="translate(' . ( $px + 2 ) . ' ' . round( ( $yt + $H - $yb ) / 2 ) . ') rotate(-90)" text-anchor="middle" ' . $f . ' class="pr-ax">SACKS A GAME</text>';
		$pts = array(); foreach ( $rows as $x ) { $pts[ $x['id'] ] = array( $X( $x['x'] ), $Y( $x['y'] ) ); }
		$wf = function ( $s ) use ( $px ) { return mb_strlen( $s ) * $px * 0.6; };
		$at = self::place( $rows, $named, $pts, $wf, $px, array( $xl, $yt, $W - $xr, $H - $yb ), $r );
		$dots = $rows; usort( $dots, function ( $a, $b ) use ( $named ) { return in_array( $a['id'], $named, true ) <=> in_array( $b['id'], $named, true ); } );  /* named ones on top */
		foreach ( $dots as $x ) {
			list( $cx, $cy ) = $pts[ $x['id'] ];
			if ( isset( $at[ $x['id'] ] ) ) { list( $lx, $ly, $la ) = $at[ $x['id'] ]; $lc = 'pr-l'; }
			else { $lx = $cx + $r + 4; $ly = $cy + $px * 0.35; $la = 'start'; if ( $lx + $wf( $x['n'] ) > $W - $xr ) { $lx = $cx - $r - 4; $la = 'end'; } $lc = 'pr-l pr-h'; }  /* shown only when his team is highlighted */
			$tip = '<b>' . esc_html( $x['n'] ) . '</b> · ' . esc_html( self::nick( $x['t'] ) . ' ' . $x['pos'] ) . '<br>' . (int) $x['prs'] . ' pressures, ' . self::sk( $x['sk'] ) . ' sacks, ' . (int) $x['qbh'] . ' QB hits in ' . (int) $x['g'] . ' games · ' . (int) $x['snap'] . '% of snaps';
			$o .= '<g class="pr-d' . ( isset( $at[ $x['id'] ] ) ? ' lab' : '' ) . '" data-t="' . esc_attr( $x['t'] ) . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0"><circle cx="' . round( $cx, 1 ) . '" cy="' . round( $cy, 1 ) . '" r="' . $r . '" fill="' . esc_attr( self::color( $x['t'] ) ) . '"><title>' . esc_html( $x['n'] . ', ' . $x['t'] . ': ' . self::n1( $x['x'] ) . ' pressures and ' . self::n2( $x['y'] ) . ' sacks a game' ) . '</title></circle>'
				. '<text class="' . $lc . '" x="' . round( $lx, 1 ) . '" y="' . round( $ly, 1 ) . '" text-anchor="' . $la . '" ' . $f . '>' . esc_html( $x['n'] ) . '</text></g>';
		}
		return $o . '</svg>';
	}

	/** The band at the top of Leaders → Defenses; '' with no rushers yet. */
	public static function band() {
		$rows = self::rows(); if ( count( $rows ) < 3 ) { return ''; }
		$N = count( $rows ); $wk = self::through(); $L = self::leaders( $rows ); $rp = $L['prs']; $rs = $L['sk'];
		$say = $rp['id'] === $rs['id']
			? '<b>' . esc_html( $rp['n'] ) . '</b> (' . esc_html( self::nick( $rp['t'] ) ) . ') leads both ways: ' . (int) $rp['prs'] . ' pressures, ' . self::n1( $rp['x'] ) . ' a game, and ' . self::sk( $rs['sk'] ) . ' sacks.'
			: '<b>' . esc_html( $rp['n'] ) . '</b> (' . esc_html( self::nick( $rp['t'] ) ) . ') gets there most: ' . self::n1( $rp['x'] ) . ' pressures a game, ' . (int) $rp['prs'] . ' in all. <b>' . esc_html( $rs['n'] ) . '</b> (' . esc_html( self::nick( $rs['t'] ) ) . ') finishes most: ' . self::sk( $rs['sk'] ) . ' sacks, ' . self::n2( $rs['y'] ) . ' a game.';
		$say .= ' Up and to the right of the dashed lines is both.';
		$teams = array_values( array_unique( array_column( $rows, 't' ) ) ); usort( $teams, function ( $a, $b ) { return strcmp( self::nick( $a ), self::nick( $b ) ); } );
		$opts = '<option value="">All teams</option>'; foreach ( $teams as $t ) { $opts .= '<option value="' . esc_attr( $t ) . '">' . esc_html( self::nick( $t ) ) . '</option>'; }
		$tb = '';
		foreach ( $rows as $i => $x ) {
			$tb .= '<tr' . ( $i >= self::TOP ? ' class="pr-x"' : '' ) . ' data-t="' . esc_attr( $x['t'] ) . '"><td class="rk">' . ( $i + 1 ) . '</td><td><a href="' . esc_url( DBD_Router::url( array( 'player' => $x['id'] ) ) ) . '">' . esc_html( $x['n'] ) . '</a><small>' . esc_html( $x['t'] . ' · ' . $x['pos'] . ' · ' . $x['g'] . ' games' ) . '</small></td>'
				. '<td><b>' . self::n1( $x['x'] ) . '</b></td><td class="c-p">' . (int) $x['prs'] . '</td><td>' . esc_html( self::sk( $x['sk'] ) ) . '</td><td class="c-h">' . (int) $x['qbh'] . '</td><td class="c-h">' . (int) $x['tfl'] . '</td><td class="c-s">' . (int) $x['snap'] . '%</td></tr>';
		}
		$small = self::named( $rows, 3 );
		return '<section class="dbd-band" id="ld-rush"><h2 class="dbd-bandh">Pass rushers<span>pressures against sacks, every rusher with a regular role</span></h2>'
			. '<section class="dbd-panel pr-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Through Week ' . (int) $wk . '</b> · ' . $N . ' rushers, a game each</span><label class="pr-hl">Highlight <select id="pr-team">' . $opts . '</select></label></div>'
			. '<p class="dbd-mv-say">' . $say . '</p>'
			. '<div class="pr-chart" id="pr-chart">' . self::svg( $rows, 960, 540, array( 64, 24, 16, 48 ), self::named( $rows, self::NAMED ), 12, 6, 'wide' ) . self::svg( $rows, 380, 420, array( 46, 10, 10, 38 ), $small, 11, 4, 'narrow' ) . '</div>'
			. '<div class="dbd-tw"><table class="pr-t"><thead><tr><th></th><th>Rusher</th><th>A game</th><th class="c-p">Pressures</th><th>Sacks</th><th class="c-h">QB hits</th><th class="c-h">TFL</th><th class="c-s">Snaps</th></tr></thead><tbody>' . $tb . '</tbody></table></div>'
			. ( $N > self::TOP ? '<button class="dbd-cbtn pr-more" type="button" id="pr-more">Show all ' . $N . '</button>' : '' )
			. '<p class="dbd-note">Pressures are Pro Football Reference\'s charting: the hurries, hits and sacks it saw. Sacks, QB hits and tackles for loss come from nflverse\'s play-by-play, so the two don\'t always add up the same way. Rushers are linemen and linebackers who played at least half their team\'s games, on ' . self::SNAP . '% or more of the snaps, with at least one pressure a game. The dashed lines are the middle of the pack. The team you highlight is remembered on this device.</p></section></section>';
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
		$link = DBD_Router::url( array( 'view' => 'leaders', 'g' => 'def' ) );
		return array( 'k' => 'rush', 'h' => 'Pass rushers', 'why' => 'Every pass rusher with a regular role on one chart: pressures a game against sacks a game.',
			'card' => 'pack-week-' . (int) $n . '-rush', 'file' => 'dbd-week-' . (int) $n . '-pass-rushers.png', 'fits' => array( 'r/nfl' ),
			'title' => $title, 'comment' => 'Every rusher, with a team highlight and the full list: ' . $link, 'post' => DBD_Pack::post( $short, $short, $link ), 'tl' => $short, 'ts' => $short, 'link' => $link,
			'lead' => DBD_Pack::last( $rp['n'] ) . '’s ' . (int) $rp['prs'] . ' pressures',
			'teams' => array_values( array_unique( array( $rp['t'], $rs['t'] ) ) ), 'sq' => 'ig-week-' . (int) $n . '-rush', 'sqfile' => 'dbd-week-' . (int) $n . '-pass-rushers-square.png' );
	}
}
