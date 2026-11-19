<?php
/**
 * The Leaders charts (SCOPE §80): one scatter at the top of each position tab, and the pass rushers' on a tab of their own (§79).
 * The position charts read players/leaders.json, the boards' own file, pool and metrics, so a dot matches the boards below it. Dots take the team's
 * color and, on the position charts, a size by volume (targets, touches, attempts); the middle of the pool is dashed; the top six on each axis
 * are named, every name placed clear of the others (place()). The Highlight menu dims every team but one and names its players; the choice is
 * shared by every chart on the site and remembered on the device (scatter.js). A phone gets its own smaller chart naming the top three.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Scatter {
	const NAMED = 6;   /* names on each axis, wide chart */
	const NAMED_SMALL = 3;

	/** The position charts: the tab's Scorecard metrics, across and up, as [key, label, format]. */
	const CHARTS = array(
		'wr' => array( 'x' => array( 'tshare', 'Target share', 'pct' ), 'y' => array( 'epat', 'EPA per target', 'sgn' ), 'unit' => 'targets', 'who' => 'wide receivers', 'h' => 'Targets and what they’re worth', 'sub' => 'target share against EPA a target',
			'note' => 'Target share is his part of the team’s targets; EPA a target is the expected points his targets added.' ),
		'te' => array( 'x' => array( 'tshare', 'Target share', 'pct' ), 'y' => array( 'epat', 'EPA per target', 'sgn' ), 'unit' => 'targets', 'who' => 'tight ends', 'h' => 'Targets and what they’re worth', 'sub' => 'target share against EPA a target',
			'note' => 'Target share is his part of the team’s targets; EPA a target is the expected points his targets added.' ),
		'rb' => array( 'x' => array( 'tpg', 'Touches per game', 'f1' ), 'y' => array( 'ypt', 'Yards per touch', 'f1' ), 'unit' => 'touches', 'who' => 'backs', 'h' => 'Touches and what they’re worth', 'sub' => 'touches a game against yards a touch',
			'note' => 'Touches are carries plus catches.' ),
		'qb' => array( 'x' => array( 'epadb', 'EPA per dropback', 'sgn' ), 'y' => array( 'cpoe', 'CPOE', 'sgn1' ), 'unit' => 'attempts', 'who' => 'quarterbacks', 'h' => 'Efficiency and accuracy', 'sub' => 'EPA a dropback against completion over expected',
			'note' => 'EPA a dropback counts every pass and sack; CPOE is completion percentage over what the throws’ difficulty predicts.' ),
	);

	private static function nick( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; }
	public static function color( $t ) { $c = (string) ( DBD_Data::team( $t )['c1'] ?? '' ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '#6b6f78'; }

	/** A value for reading: 38.5%, +0.32, 5.5, +10.2. */
	public static function val( $kind, $v ) {
		$v = (float) $v;
		if ( $kind === 'pct' ) { return number_format( $v * 100, 1 ) . '%'; }
		if ( $kind === 'sgn' ) { return ( $v > 0 ? '+' : ( $v < 0 ? '−' : '' ) ) . number_format( abs( $v ), 2 ); }
		if ( $kind === 'sgn1' ) { return ( $v > 0 ? '+' : ( $v < 0 ? '−' : '' ) ) . number_format( abs( $v ), 1 ); }
		return number_format( $v, 1 );
	}
	/** A tick: 20%, +0.5, 10, +5.0. */
	public static function tick( $kind, $v ) {
		$v = round( (float) $v, 6 );
		if ( $kind === 'pct' ) { return round( $v * 100 ) . '%'; }
		$s = $v > 0 ? '+' : ( $v < 0 ? '−' : '' );
		if ( $kind === 'sgn1' ) { return $s . number_format( abs( $v ), 1 ); }
		if ( $kind === 'sgn' ) { return $s . rtrim( rtrim( number_format( abs( $v ), 2 ), '0' ), '.' ); }
		if ( $kind === 'q' ) { return number_format( $v, 2 ); }  /* the rushers' sacks a game, quarters */
		return rtrim( rtrim( number_format( $v, 2 ), '0' ), '.' );
	}

	/** An axis: [low, high, step], round steps, 6 or fewer of them. */
	public static function axis( $lo, $hi ) {
		$span = ( $hi - $lo ) ?: 1; $st = 10;
		foreach ( array( 0.02, 0.05, 0.1, 0.2, 0.25, 0.5, 1, 2, 2.5, 5, 10 ) as $s ) { if ( $span / $s <= 6 ) { $st = $s; break; } }
		return array( floor( $lo / $st + 1e-9 ) * $st, ceil( $hi / $st - 1e-9 ) * $st, $st );
	}

	public static function median( $v ) { sort( $v ); $n = count( $v ); return $n ? ( $n % 2 ? $v[ ( $n - 1 ) / 2 ] : ( $v[ $n / 2 - 1 ] + $v[ $n / 2 ] ) / 2 ) : 0; }

	/** The ids to name: the top $k on each axis (ties by name). */
	public static function named( $rows, $k ) {
		$a = $rows; usort( $a, function ( $p, $q ) { return array( -$p['x'], -$p['y'], $p['n'] ) <=> array( -$q['x'], -$q['y'], $q['n'] ); } );
		$b = $rows; usort( $b, function ( $p, $q ) { return array( -$p['y'], -$p['x'], $p['n'] ) <=> array( -$q['y'], -$q['x'], $q['n'] ); } );
		return array_values( array_unique( array_merge( array_column( array_slice( $a, 0, $k ), 'id' ), array_column( array_slice( $b, 0, $k ), 'id' ) ) ) );
	}

	/**
	 * Where each name goes (SCOPE §80): right of the dot, left, above ending at it, below, above starting at it; the first spot inside the box that
	 * covers no other name and no dot; failing that, the first that covers no other name; failing that, no name (the dot keeps its tip). The box is
	 * the type's full height. $rows in priority order; $pts: id => [x, y, radius]; $w: a name's width. Returns id => [x, baseline, 'start'|'end'].
	 */
	public static function place( $rows, $named, $pts, $w, $px, $box ) {
		$taken = array(); $out = array(); $want = array_flip( $named );
		$hit = function ( $a, $dots ) use ( &$taken, $pts ) {
			foreach ( $taken as $b ) { if ( $a[0] < $b[2] && $b[0] < $a[2] && $a[1] < $b[3] && $b[1] < $a[3] ) { return true; } }
			if ( ! $dots ) { return false; }
			foreach ( $pts as $id => $p ) { if ( (string) $id === $a[4] ) { continue; } if ( $p[0] + $p[2] > $a[0] && $p[0] - $p[2] < $a[2] && $p[1] + $p[2] > $a[1] && $p[1] - $p[2] < $a[3] ) { return true; } }
			return false;
		};
		foreach ( $rows as $row ) {
			$id = (string) $row['id']; if ( ! isset( $want[ $id ], $pts[ $id ] ) ) { continue; }
			list( $cx, $cy, $r ) = $pts[ $id ]; $tw = $w( $row['n'] ); $g = $r + 4;
			$cands = array( array( $cx + $g, $cy + $px * 0.35, 'start' ), array( $cx - $g, $cy + $px * 0.35, 'end' ), array( $cx + $r, $cy - $r - 4, 'end' ), array( $cx + $r, $cy + $r + $px, 'end' ), array( $cx - $r, $cy - $r - 4, 'start' ) );
			$pick = null;
			foreach ( array( true, false ) as $dots ) {
				foreach ( $cands as $c ) {
					$x0 = $c[2] === 'start' ? $c[0] : $c[0] - $tw; $a = array( $x0, $c[1] - $px * 0.95, $x0 + $tw, $c[1] + $px * 0.3, $id );
					if ( $a[0] < $box[0] || $a[2] > $box[2] || $a[1] < $box[1] || $a[3] > $box[3] ) { continue; }
					if ( ! $hit( $a, $dots ) ) { $pick = array( $c, $a ); break 2; }
				}
			}
			if ( $pick ) { $taken[] = $pick[1]; $out[ $id ] = $pick[0]; }
		}
		return $out;
	}

	/**
	 * The chart. $rows: id, n, t, pos, x, y, u (volume), tip. $o: W, H, m [left, right, top, bottom], px, rmin, rmax (equal: one size), named (ids),
	 * xk/yk (tick formats), xl/yl (axis labels), cls, label (the aria label), xr/yr (fixed [low, high, step], optional), mid ([x, y]).
	 */
	public static function svg( $rows, $o ) {
		$W = $o['W']; $H = $o['H']; list( $ml, $mr, $mt, $mb ) = $o['m']; $px = $o['px'];
		$xa = $o['xr'] ?? self::axis( min( array_column( $rows, 'x' ) ), max( array_column( $rows, 'x' ) ) );
		$ya = $o['yr'] ?? self::axis( min( array_column( $rows, 'y' ) ), max( array_column( $rows, 'y' ) ) );
		$X = function ( $v ) use ( $ml, $mr, $W, $xa ) { return $ml + ( $W - $ml - $mr ) * ( $v - $xa[0] ) / ( ( $xa[1] - $xa[0] ) ?: 1 ); };
		$Y = function ( $v ) use ( $mt, $mb, $H, $ya ) { return $H - $mb - ( $H - $mt - $mb ) * ( $v - $ya[0] ) / ( ( $ya[1] - $ya[0] ) ?: 1 ); };
		$us = array_column( $rows, 'u' ); $umin = $us ? min( $us ) : 0; $umax = $us ? max( $us ) : 1;
		$R = function ( $u ) use ( $o, $umin, $umax ) { return $o['rmax'] == $o['rmin'] ? $o['rmax'] : $o['rmin'] + ( $o['rmax'] - $o['rmin'] ) * sqrt( max( 0, $u - $umin ) / ( ( $umax - $umin ) ?: 1 ) ); };
		$f = 'font-family="IBM Plex Mono,monospace" font-size="' . $px . '"';
		$s = '<svg class="pr-svg ' . esc_attr( $o['cls'] ) . '" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . esc_attr( $o['label'] ) . '">';
		for ( $i = 0, $v = $xa[0]; $v <= $xa[1] + 1e-9 && $i < 20; $i++, $v = $xa[0] + $i * $xa[2] ) { $x = round( $X( $v ), 1 ); $z = abs( $v ) < 1e-9 && $xa[0] < 0 ? ' z' : '';
			$s .= '<line x1="' . $x . '" x2="' . $x . '" y1="' . $mt . '" y2="' . ( $H - $mb ) . '" class="pr-g' . $z . '"/><text x="' . $x . '" y="' . ( $H - $mb + $px + 6 ) . '" text-anchor="middle" ' . $f . ' class="pr-tk">' . esc_html( self::tick( $o['xk'], $v ) ) . '</text>'; }
		for ( $i = 0, $v = $ya[0]; $v <= $ya[1] + 1e-9 && $i < 20; $i++, $v = $ya[0] + $i * $ya[2] ) { $y = round( $Y( $v ), 1 ); $z = abs( $v ) < 1e-9 && $ya[0] < 0 ? ' z' : '';
			$s .= '<line x1="' . $ml . '" x2="' . ( $W - $mr ) . '" y1="' . $y . '" y2="' . $y . '" class="pr-g' . $z . '"/><text x="' . ( $ml - 8 ) . '" y="' . round( $y + $px * 0.35, 1 ) . '" text-anchor="end" ' . $f . ' class="pr-tk">' . esc_html( self::tick( $o['yk'], $v ) ) . '</text>'; }
		$mid = $o['mid'] ?? array( self::median( array_column( $rows, 'x' ) ), self::median( array_column( $rows, 'y' ) ) );
		$s .= '<line x1="' . round( $X( $mid[0] ), 1 ) . '" x2="' . round( $X( $mid[0] ), 1 ) . '" y1="' . $mt . '" y2="' . ( $H - $mb ) . '" class="pr-md"/><line x1="' . $ml . '" x2="' . ( $W - $mr ) . '" y1="' . round( $Y( $mid[1] ), 1 ) . '" y2="' . round( $Y( $mid[1] ), 1 ) . '" class="pr-md"/>';
		$s .= '<text x="' . round( ( $ml + $W - $mr ) / 2 ) . '" y="' . ( $H - 6 ) . '" text-anchor="middle" ' . $f . ' class="pr-ax">' . esc_html( strtoupper( $o['xl'] ) ) . '</text><text transform="translate(' . ( $px + 2 ) . ' ' . round( ( $mt + $H - $mb ) / 2 ) . ') rotate(-90)" text-anchor="middle" ' . $f . ' class="pr-ax">' . esc_html( strtoupper( $o['yl'] ) ) . '</text>';
		$pts = array(); foreach ( $rows as $x ) { $pts[ (string) $x['id'] ] = array( $X( $x['x'] ), $Y( $x['y'] ), $R( $x['u'] ) ); }
		$wf = function ( $t ) use ( $px ) { return mb_strlen( (string) $t ) * $px * 0.6; }; $box = array( $ml, $mt, $W - $mr, $H - $mb );
		$by = $rows; usort( $by, function ( $a, $b ) { return array( -$a['x'], -$a['y'], $a['n'] ) <=> array( -$b['x'], -$b['y'], $b['n'] ); } );
		$at = self::place( $by, $o['named'], $pts, $wf, $px, $box );
		$team = array(); foreach ( array_unique( array_column( $rows, 't' ) ) as $t ) { $ids = array(); foreach ( $rows as $x ) { if ( $x['t'] === $t ) { $ids[] = (string) $x['id']; } } $team += self::place( $by, $ids, $pts, $wf, $px, $box ); }  /* a team's names laid out together for the highlight */
		$named = array_flip( $o['named'] ); $dots = $rows;
		usort( $dots, function ( $a, $b ) use ( $named ) { return array( isset( $named[ (string) $a['id'] ] ), -$a['u'] ) <=> array( isset( $named[ (string) $b['id'] ] ), -$b['u'] ); } );  /* big dots under small, named on top */
		foreach ( $dots as $x ) {
			$id = (string) $x['id']; list( $cx, $cy, $r ) = $pts[ $id ];
			$lab = function ( $p, $cls ) use ( $x, $f ) { return '<text class="' . $cls . '" x="' . round( $p[0], 1 ) . '" y="' . round( $p[1], 1 ) . '" text-anchor="' . $p[2] . '" ' . $f . '>' . esc_html( $x['n'] ) . '</text>'; };
			$hp = $team[ $id ] ?? array( $cx + $r + 4, $cy + $px * 0.35, 'start' );
			$s .= '<g class="pr-d' . ( isset( $at[ $id ] ) ? ' lab' : '' ) . '" data-t="' . esc_attr( $x['t'] ) . '" data-tip="' . esc_attr( $x['tip'] ) . '" tabindex="0"><circle cx="' . round( $cx, 1 ) . '" cy="' . round( $cy, 1 ) . '" r="' . round( $r, 1 ) . '" fill="' . esc_attr( self::color( $x['t'] ) ) . '"><title>' . esc_html( $x['n'] . ', ' . $x['t'] ) . '</title></circle>'
				. ( isset( $at[ $id ] ) ? $lab( $at[ $id ], 'pr-l' ) : '' ) . $lab( $hp, 'pr-h' ) . '</g>';
		}
		return $s . '</svg>';
	}

	/** The Highlight menu: every team on the chart, by nickname. */
	public static function menu( $rows ) {
		$teams = array_values( array_unique( array_column( $rows, 't' ) ) ); usort( $teams, function ( $a, $b ) { return strcmp( self::nick( $a ), self::nick( $b ) ); } );
		$o = '<option value="">All teams</option>'; foreach ( $teams as $t ) { $o .= '<option value="' . esc_attr( $t ) . '">' . esc_html( self::nick( $t ) ) . '</option>'; }
		return '<label class="pr-hl">Highlight <select class="pr-team">' . $o . '</select></label>';
	}

	/** A position tab's rows from the boards' file: [id, n, t, pos, x, y, u, tip], the pool the boards use. */
	public static function rows( $g ) {
		$c = self::CHARTS[ $g ] ?? null; if ( ! $c ) { return array(); }
		$dg = DBD_Leaders::data_group( $g ); $pos = DBD_Leaders::pos_of( $g ); $min = DBD_Leaders::steps( $g )[0];
		$P = (array) ( DBD_Leaders::file()['players'] ?? array() ); $ys = array();
		foreach ( DBD_Leaders::rows( $dg, 'g', $c['y'][0], $min, $pos ) as $r ) { $ys[ $r[0] ] = (float) $r[1]; }
		$out = array();
		foreach ( DBD_Leaders::rows( $dg, 'g', $c['x'][0], $min, $pos ) as $r ) {
			$id = (string) $r[0]; $p = $P[ $id ] ?? null; if ( ! $p || empty( $p[1] ) || ! isset( $ys[ $id ] ) || $r[1] === null ) { continue; }
			$x = (float) $r[1]; $y = $ys[ $id ]; $u = (int) $r[2];
			$tip = '<b>' . esc_html( $p[0] ) . '</b> · ' . esc_html( self::nick( $p[1] ) . ' ' . $p[2] ) . '<br>' . esc_html( $c['x'][1] . ' ' . self::val( $c['x'][2], $x ) . ' · ' . $c['y'][1] . ' ' . self::val( $c['y'][2], $y ) ) . '<br>' . $u . ' ' . esc_html( $c['unit'] );
			$out[] = array( 'id' => $id, 'n' => (string) $p[0], 't' => (string) $p[1], 'pos' => (string) $p[2], 'x' => $x, 'y' => $y, 'u' => $u, 'tip' => $tip );
		}
		return $out;
	}

	/** A position tab's band: the read, the chart (wide and phone) and the note; '' with fewer than three players. */
	public static function band( $g ) {
		$c = self::CHARTS[ $g ] ?? null; $rows = self::rows( $g ); if ( ! $c || count( $rows ) < 3 ) { return ''; }
		$n = count( $rows ); $min = DBD_Leaders::steps( $g )[0]; $wk = (int) DBD_Data::through_week();
		$hx = $rows[0]; $hy = $rows[0]; foreach ( $rows as $r ) { if ( array( $r['x'], $r['u'] ) > array( $hx['x'], $hx['u'] ) ) { $hx = $r; } if ( array( $r['y'], $r['u'] ) > array( $hy['y'], $hy['u'] ) ) { $hy = $r; } }
		$b = function ( $r ) { return '<b>' . esc_html( $r['n'] ) . '</b> (' . esc_html( self::nick( $r['t'] ) ) . ')'; };
		$vx = esc_html( self::val( $c['x'][2], $hx['x'] ) ); $vy = esc_html( self::val( $c['y'][2], $hy['y'] ) );
		if ( $g === 'qb' ) { $say = $b( $hx ) . ' leads in EPA a dropback, ' . $vx . ' on ' . (int) $hx['u'] . ' attempts; ' . $b( $hy ) . ' in completion over expected, ' . $vy . '.'; }
		elseif ( $g === 'rb' ) { $say = $b( $hx ) . ' gets the ball most: ' . $vx . ' touches a game. ' . $b( $hy ) . ' makes the most of each: ' . $vy . ' yards a touch, on ' . (int) $hy['u'] . ' touches.'; }
		else { $say = $b( $hx ) . ' draws the most: ' . $vx . ' of his team’s targets. ' . $b( $hy ) . ' gets the most from each: ' . $vy . ' EPA a target, on ' . (int) $hy['u'] . ' targets.'; }
		$say .= ' Bigger dots are more ' . esc_html( $c['unit'] ) . '. Up and to the right of the dashed lines is both.';
		$label = $c['x'][1] . ' against ' . $c['y'][1] . ', ' . $n . ' ' . $c['who'];
		$base = array( 'xk' => $c['x'][2], 'yk' => $c['y'][2], 'xl' => $c['x'][1], 'yl' => $c['y'][1], 'label' => $label );
		$wide = self::svg( $rows, $base + array( 'W' => 960, 'H' => 540, 'm' => array( 68, 24, 16, 48 ), 'px' => 12, 'rmin' => 3, 'rmax' => 9, 'named' => self::named( $rows, self::NAMED ), 'cls' => 'wide' ) );
		$small = self::svg( $rows, $base + array( 'W' => 380, 'H' => 420, 'm' => array( 50, 10, 10, 38 ), 'px' => 11, 'rmin' => 2, 'rmax' => 6, 'named' => self::named( $rows, self::NAMED_SMALL ), 'cls' => 'narrow' ) );
		return '<section class="dbd-band ld-band" id="ld-chart"><h2 class="dbd-bandh">' . esc_html( $c['h'] ) . '<span>' . esc_html( $c['sub'] . ', every one of the ' . $c['who'] . ' with ' . $min . '+ ' . $c['unit'] ) . '</span></h2>'
			. '<section class="dbd-panel pr-panel" data-chart="' . esc_attr( $g ) . '"><div class="dbd-ph"><span class="dbd-lab"><b>Through Week ' . $wk . '</b> · ' . $n . ' ' . esc_html( $c['who'] ) . '</span>' . self::menu( $rows ) . '</div>'
			. '<p class="dbd-mv-say">' . $say . '</p><div class="pr-chart">' . $wide . $small . '</div>'
			. '<p class="dbd-note">' . esc_html( $c['note'] ) . ' The players are the boards’ own pool below, ' . $min . '+ ' . esc_html( $c['unit'] ) . '; the dashed lines are the middle of it. The team you highlight is remembered on this device and carries to every chart.</p></section></section>';
	}
}
