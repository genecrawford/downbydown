<?php
/**
 * Compare, finished (SCOPE §42): this week, playstyle, where the EPA comes from, the shape of their seasons and careers, side by side
 * in the slot colors. $P is compare.php's slot list (row, rec, c, t); $grp is the pool (wr, te, rb, qb).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Compare {

	private static function first( $x ) { $n = (string) ( $x['row']['name'] ?? '' ); $s = strpos( $n, ' ' ); return $s ? substr( $n, 0, $s ) : $n; }
	private static function sgn( $v, $d = 1 ) { if ( $v === null ) { return '–'; } $v = (float) $v; return ( $v > 0 ? '+' : ( $v < 0 ? '−' : '' ) ) . number_format( abs( $v ), $d ); }
	private static function legend( $P ) { $h = '<div class="dbd-cleg">'; foreach ( $P as $x ) { $h .= '<span><i style="background:' . esc_attr( $x['c'] ) . '"></i>' . esc_html( $x['row']['name'] ) . '</span>'; } return $h . '</div>'; }

	/**
	 * A chart whose lines stretch in an SVG with non-scaling strokes while its dots and labels are HTML placed in percent, so text keeps
	 * its size at every width (the game page's win probability does the same). $series: [color, [[x, y, tip, kind]...], [[x, y]...] faint].
	 */
	private static function chart( $series, $ylo, $yhi, $x0, $x1, $xlabels, $ylabels, $h, $band = null, $med = null ) {
		$X = function ( $x ) use ( $x0, $x1 ) { return round( ( $x - $x0 ) / max( 1e-9, $x1 - $x0 ) * 100, 2 ); };
		$Y = function ( $v ) use ( $ylo, $yhi ) { return round( ( $yhi - $v ) / max( 1e-9, $yhi - $ylo ) * 100, 2 ); };
		$svg = '';
		if ( $band ) { $svg .= '<rect x="0" y="' . $Y( $band[1] ) . '" width="100" height="' . round( $Y( $band[0] ) - $Y( $band[1] ), 2 ) . '" fill="rgba(22,30,54,.08)"/>'; }
		if ( $med !== null ) { $svg .= '<line x1="0" x2="100" y1="' . $Y( $med ) . '" y2="' . $Y( $med ) . '" stroke="var(--dbd-line-2)" stroke-dasharray="3 3" vector-effect="non-scaling-stroke"/>'; }
		if ( ! $band ) { foreach ( $ylabels as $yl ) { $svg .= '<line x1="0" x2="100" y1="' . $Y( $yl[0] ) . '" y2="' . $Y( $yl[0] ) . '" stroke="var(--dbd-line)" vector-effect="non-scaling-stroke"/>'; } }
		$dots = '';
		foreach ( $series as $s ) {
			list( $c, $pts, $faint ) = $s; $line = array();
			foreach ( $pts as $p ) { if ( $p[1] !== null ) { $line[] = $X( $p[0] ) . ',' . $Y( $p[1] ); } }
			if ( count( $line ) > 1 ) { $svg .= '<polyline points="' . implode( ' ', $line ) . '" fill="none" stroke="' . esc_attr( $c ) . '" stroke-width="2.2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>'; }
			foreach ( $pts as $p ) { if ( $p[1] === null ) { continue; } $dots .= '<i class="d ' . esc_attr( $p[3] ) . '" style="left:' . $X( $p[0] ) . '%;top:' . $Y( $p[1] ) . '%;--c:' . esc_attr( $c ) . '"' . ( $p[2] ? ' data-tip="' . esc_attr( $p[2] ) . '" tabindex="0"' : '' ) . '></i>'; }
			foreach ( $faint as $f ) { if ( $f[1] === null ) { continue; } $dots .= '<i class="d f" style="left:' . $X( $f[0] ) . '%;top:' . max( 0, min( 100, $Y( $f[1] ) ) ) . '%;--c:' . esc_attr( $c ) . '"></i>'; }
		}
		$yl = ''; foreach ( $ylabels as $l ) { $yl .= '<span class="yl" style="top:' . $Y( $l[0] ) . '%">' . esc_html( $l[1] ) . '</span>'; }
		$xl = ''; foreach ( $xlabels as $l ) { $xl .= '<span style="left:' . $X( $l[0] ) . '%">' . esc_html( $l[1] ) . '</span>'; }
		return '<div class="cw-ch"><div class="pl" style="height:' . (int) $h . 'px"><svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">' . $svg . '</svg>' . $dots . $yl . '</div><div class="xl">' . $xl . '</div></div>';
	}

	/* ------------------------------------------------------------ this week */
	public static function this_week( $P ) {
		$f = DBD_Fantasy::file(); if ( ! $f || empty( $f['rank'] ) ) { return ''; }
		$n = count( $f['rank'] ); $cells = ''; $any = false;
		foreach ( $P as $x ) {
			$a = $x['row']['team']; $pos = $x['row']['pos'] === 'FB' ? 'RB' : $x['row']['pos']; $ng = DBD_Fantasy::next_game( $a );
			if ( ! $ng ) { $cells .= '<div class="cw-nx" style="--c:' . esc_attr( $x['c'] ) . '"><small>' . esc_html( self::first( $x ) ) . '</small><b>No game left this season</b></div>'; continue; }
			$any = true; $g = DBD_Data::team_game( $a, $ng['wk'] ); $o = $ng['opp']; $home = $g && $g['home'] === $a;
			$rk = (int) ( $f['rank'][ $o ][ $pos ][1] ?? 0 ); $on = DBD_Data::team( $o )['nick'] ?? $o;
			$word = $rk === 1 ? 'most' : ( $rk === $n ? 'fewest' : DBD_Fantasy::most( $rk, $n ) );
			$cells .= '<div class="cw-nx" style="--c:' . esc_attr( $x['c'] ) . '"><small>' . esc_html( 'Week ' . $ng['wk'] . ( $g && ! empty( $g['day'] ) ? ' · ' . $g['day'] : '' ) ) . ( $g ? ' · ' . DBD_Router::kickoff_html( $g ) : '' ) . '</small><b>' . esc_html( self::first( $x ) . ( $home ? ' vs ' : ' at ' ) . $on ) . '</b>'
				. ( $rk ? '<span class="cw-def ' . esc_attr( DBD_Fantasy::tier( $rk ) ) . '">' . esc_html( $on . ' allow the ' . $word . ' ' . $pos . ' points' ) . '</span>' : '' ) . '</div>';
		}
		if ( ! $any ) { return ''; }
		return '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>This week</b> · each one\'s next game and the defense at his position</span><span class="dbd-badge">Half PPR</span></div><div class="dbd-cslots cw-next">' . $cells . '</div></section>';
	}

	/* ------------------------------------------------------------ playstyle */
	public static function playstyle( $P, $grp ) {
		$gi = DBD_Grades::group_info( $grp ); if ( empty( $gi['fp'] ) ) { return ''; }
		$defs = DBD_Grades::fp_defs( $grp ); $unit = DBD_Grades::fp_unit( $grp ); $plab = DBD_Grades::pool_label( DBD_Grades::in_use( $grp ) );
		$rows = ''; $tags = array(); $have = false;
		foreach ( $defs as $d ) {
			$med = DBD_Grades::median( $grp, $d[0], 'fp' ); $mp = $med === null ? null : DBD_Grades::pctile( $grp, $d[0], $med, 'fp' );
			$marks = ''; $cv = '';
			foreach ( $P as $i => $x ) {
				$fp = $x['rec']['fp'] ?? null; $v = $fp[ $d[0] ] ?? null; $q = ! empty( $x['rec']['grades']['q'] );
				$pc = ( $q && $v !== null ) ? DBD_Grades::pctile( $grp, $d[0], $v, 'fp' ) : null; if ( $v !== null ) { $have = true; }
				if ( $pc !== null ) {
					$tip = '<b>' . esc_html( $x['row']['name'] ) . '</b><br>' . esc_html( $d[1] ) . ': <b>' . esc_html( DBD_Grades::fp_fmt( $d[3], $v ) ) . '</b> · ' . esc_html( DBD_Grades::ordinal( $pc ) ) . ' percentile' . ( $med === null ? '' : '<br><span class="m">League median ' . esc_html( DBD_Grades::fp_fmt( $d[3], $med ) ) . '</span>' );
					$marks .= '<i style="--p:' . (int) $pc . ';--c:' . esc_attr( $x['c'] ) . '" data-slot="' . $i . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $x['row']['name'] . ', ' . $d[1] . ' ' . DBD_Grades::fp_fmt( $d[3], $v ) . ', ' . DBD_Grades::ordinal( $pc ) . ' percentile' ) . '"></i>';
					if ( ( $pc >= DBD_Grades::FP_HI && $d[4] !== '' ) || ( $pc <= DBD_Grades::FP_LO && $d[5] !== '' ) ) { $tags[ $i ][] = array( abs( $pc - 50 ), $pc >= DBD_Grades::FP_HI ? $d[4] : $d[5], $pc >= DBD_Grades::FP_HI ); }
				}
				$cv .= '<div class="cv" style="--c:' . esc_attr( $x['c'] ) . '" data-slot="' . $i . '">' . esc_html( DBD_Grades::fp_fmt( $d[3], $v ) ) . '<small>' . ( $pc === null ? '–' : esc_html( DBD_Grades::ordinal( $pc ) ) ) . '</small></div>';
			}
			$rows .= '<div class="row"><div class="l">' . esc_html( $d[1] ) . '<small>' . esc_html( $d[2] ) . '</small></div><div class="bar">' . ( $mp === null ? '' : '<span class="cw-med" style="--p:' . (int) $mp . '"></span>' ) . $marks . '</div>' . $cv . '</div>';
		}
		if ( ! $have ) { return ''; }
		$tr = '';
		foreach ( $P as $i => $x ) {
			$t = $tags[ $i ] ?? array(); usort( $t, function ( $a, $b ) { return $b[0] <=> $a[0]; } ); $t = array_slice( $t, 0, 3 );
			$tr .= '<div class="cw-tags" style="--c:' . esc_attr( $x['c'] ) . '"><b>' . esc_html( self::first( $x ) ) . '</b>';
			foreach ( $t as $g ) { $tr .= '<span class="dbd-fp-tag ' . ( $g[2] ? 'hi' : 'lo' ) . '"><i aria-hidden="true">' . ( $g[2] ? '▲' : '▼' ) . '</i>' . esc_html( $g[1] ) . '</span>'; }
			$tr .= ( $t ? '' : '<span class="m">' . ( empty( $x['rec']['grades']['q'] ) ? 'not graded yet' : 'a typical profile' ) . '</span>' ) . '</div>';
		}
		return '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Playstyle</b> · how each is used, against the league\'s ' . esc_html( $plab ) . '</span><span class="dbd-badge">among ' . (int) ( $gi['n'] ?? 0 ) . ' ' . esc_html( $plab ) . '</span></div>'
			. self::legend( $P ) . '<div class="cw-tagrow">' . $tr . '</div><div class="dbd-cpct">' . $rows . '</div>'
			. '<p class="dbd-note">Every trait is a share of his own ' . esc_html( $unit ) . ', so volume does not move it. Each bar is the league\'s qualifying ' . esc_html( $plab ) . '; the markers are the players and the thin line is the median. Styles are not grades: a high or low share describes the role, not how well he plays it. The tags are each player\'s traits furthest from the middle.</p></section>';
	}

	/* ------------------------------------------------------------ where the EPA comes from */
	const PARTS = array(  /* label, small print (receivers and backs / quarterbacks), color */
		'air' => array( 'Air yards', 'on catches', 'on completions', '#161e36' ), 'yac' => array( 'After the catch', '', 'his share, catch to whistle', '#1e7f4a' ),
		'inc' => array( 'Incompletions', 'targets not caught', 'spikes included', '#9aa0aa' ), 'int' => array( 'Interceptions', 'when targeted', '', '#b4261f' ),
		'sk' => array( 'Sacks', '', '', '#7a3b8f' ), 'scr' => array( 'Scrambles', '', '', '#2c7fb8' ), 'run' => array( 'Designed runs', '', 'kneels included', '#8a6d3b' ), 'two' => array( 'Two-point tries', 'not in the rates', 'not in the rates', '#c8b98a' ),
	);
	public static function epa( $P, $grp ) {
		$kind = DBD_Grades::kind( $grp ); $qb = $kind === 'qb'; $plab = DBD_Grades::pool_label( DBD_Grades::in_use( $grp ) );
		$S = array(); $tot = array(); $u = array();
		foreach ( $P as $i => $x ) {
			$ep = $x['rec']['ep'] ?? null; if ( ! $ep || empty( $ep['sec'] ) ) { return ''; }  /* an older data file, or a player with no plays in his role */
			$parts = array(); $t = 0.0;
			foreach ( $ep['sec'] as $sec ) {
				$t += (float) $sec[1];
				foreach ( (array) $sec[3] as $q ) {
					if ( isset( $parts[ $q[0] ] ) ) { $parts[ $q[0] ] = array( $parts[ $q[0] ][0] + (float) $q[1], null, null ); }  /* a part in two sections (a quarterback's two-point tries): the total only */
					else { $parts[ $q[0] ] = array( (float) $q[1], isset( $q[2] ) ? (float) $q[2] : null, $q[3] ?? null ); }
				}
			}
			$S[ $i ] = $parts; $tot[ $i ] = $t; $u[ $i ] = (int) ( $ep['u'] ?? 0 );
		}
		$keys = array(); foreach ( array_keys( self::PARTS ) as $k ) { foreach ( $S as $s ) { if ( isset( $s[ $k ] ) ) { $keys[] = $k; break; } } }
		$span = 1e-9; foreach ( $S as $s ) { $p = 0; $m = 0; foreach ( $s as $v ) { if ( $v[0] > 0 ) { $p += $v[0]; } else { $m -= $v[0]; } } $span = max( $span, $p, $m ); }
		$bars = '';
		foreach ( $P as $i => $x ) {
			$neg = ''; $pos = '';
			foreach ( $keys as $k ) {
				if ( ! isset( $S[ $i ][ $k ] ) || ! $S[ $i ][ $k ][0] ) { continue; } $v = $S[ $i ][ $k ][0];
				$seg = '<i style="width:' . round( abs( $v ) / $span * 100, 2 ) . '%;background:' . self::PARTS[ $k ][3] . '" data-tip="' . esc_attr( '<b>' . esc_html( $x['row']['name'] ) . '</b><br>' . self::PARTS[ $k ][0] . ': <b>' . self::sgn( $v ) . '</b> EPA' ) . '" tabindex="0"></i>';
				if ( $v > 0 ) { $pos .= $seg; } else { $neg = $seg . $neg; }
			}
			$bars .= '<div class="cw-eprow"><div class="nm"><i style="background:' . esc_attr( $x['c'] ) . '"></i>' . esc_html( $x['row']['name'] ) . '<b>' . self::sgn( $tot[ $i ] ) . '</b></div><div class="cw-epbar"><span class="neg">' . $neg . '</span><span class="z"></span><span class="pos">' . $pos . '</span></div></div>';
		}
		$unit = $qb ? 'dropback' : ( $kind === 'rb' ? 'touch' : 'target' );
		$th = '<th>Part</th>'; foreach ( $P as $x ) { $th .= '<th><i class="sw" style="background:' . esc_attr( $x['c'] ) . '"></i>' . esc_html( self::first( $x ) ) . '</th>'; }
		$tb = '';
		foreach ( $keys as $k ) {
			$best = -1; $bv = null; foreach ( $S as $i => $s ) { $r = $s[ $k ][1] ?? null; if ( $r !== null && ( $bv === null || $r > $bv ) ) { $bv = $r; $best = $i; } }
			$tb .= '<tr><td class="n">' . esc_html( self::PARTS[ $k ][0] ) . '<small>' . esc_html( self::PARTS[ $k ][ $qb ? 2 : 1 ] ) . '</small></td>';
			foreach ( $P as $i => $x ) { $v = $S[ $i ][ $k ] ?? null; $tb .= '<td' . ( $i === $best && count( $P ) > 1 ? ' class="b"' : '' ) . '>' . ( $v ? self::sgn( $v[0] ) . '<small>' . ( $v[1] !== null ? self::sgn( $v[1], 2 ) . ' a ' . $unit : '' ) . ( $v[2] !== null ? ' · ' . esc_html( DBD_Grades::ordinal( $v[2] ) ) : '' ) . '</small>' : '–' ) . '</td>'; }
			$tb .= '</tr>';
		}
		$tb .= '<tr class="tot"><td class="n">Total</td>'; foreach ( $P as $i => $x ) { $tb .= '<td>' . self::sgn( $tot[ $i ] ) . '<small>' . $u[ $i ] . ' ' . $unit . ( $u[ $i ] === 1 ? '' : ( $unit === 'touch' ? 'es' : 's' ) ) . '</small></td>'; } $tb .= '</tr>';
		$leg = '<div class="dbd-legend">'; foreach ( $keys as $k ) { $leg .= '<span><i class="sq" style="background:' . self::PARTS[ $k ][3] . '"></i>' . esc_html( self::PARTS[ $k ][0] ) . '</span>'; } $leg .= '</div>';
		/* the read: who gains most in two ways, who gives most back in one */
		$G = $qb ? array( array( 'air', 'the ball in the air' ), array( 'scr', 'scrambling' ) ) : ( $kind === 'rb' ? array( array( 'run', 'on runs' ), array( 'yac', 'after the catch' ) ) : array( array( 'air', 'the ball in the air' ), array( 'yac', 'after the catch' ) ) );
		$B = $qb ? array( 'sk', 'on sacks' ) : ( $kind === 'rb' ? array( 'inc', 'on targets he does not catch' ) : array( 'inc', 'on targets he does not catch' ) );
		$arg = function ( $k, $hi ) use ( $S ) { $bi = null; $bv = null; foreach ( $S as $i => $s ) { if ( ! isset( $s[ $k ] ) ) { continue; } $v = $s[ $k ][0]; if ( $bv === null || ( $hi ? $v > $bv : $v < $bv ) ) { $bv = $v; $bi = $i; } } return array( $bi, $bv ); };
		$say = ''; $named = array();
		if ( count( $P ) > 1 ) {
			$bits = array();
			foreach ( $G as $j => $g ) { list( $bi, $bv ) = $arg( $g[0], true ); if ( $bi === null ) { continue; } $nm = in_array( $bi, $named, true ) ? 'he' : '<b>' . esc_html( $P[ $bi ]['row']['name'] ) . '</b>'; $bits[] = $nm . ' gets the most ' . ( $g[0] === 'air' ? 'from ' : '' ) . $g[1] . ' (' . self::sgn( $bv ) . ' EPA)'; $named[] = $bi; }
			list( $wi, $wv ) = $arg( $B[0], false );
			if ( $wi !== null && $wv < 0 ) { $bits[] = ( in_array( $wi, $named, true ) ? esc_html( self::first( $P[ $wi ] ) ) . ' also gives' : '<b>' . esc_html( $P[ $wi ]['row']['name'] ) . '</b> gives' ) . ' the most back ' . $B[1] . ' (' . self::sgn( $wv ) . ')'; }
			if ( $bits ) { $last = array_pop( $bits ); $say = ucfirst( $bits ? implode( ', ', $bits ) . ', and ' . $last : $last ) . '.'; }
		}
		return '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where the EPA comes from</b> · the season split by what happened on his plays</span><span class="dbd-badge">' . ( $qb ? 'Passing and rushing EPA' : ( $kind === 'rb' ? 'Rushing and receiving EPA' : 'Receiving EPA' ) ) . '</span></div>'
			. ( $say ? '<p class="dbd-cx-say">' . $say . '</p>' : '' ) . '<div class="cw-ep">' . $bars . '</div>' . $leg . '<div class="dbd-tw"><table class="dbd-stat cw-ept"><thead><tr>' . $th . '</tr></thead><tbody>' . $tb . '</tbody></table></div>'
			. '<p class="dbd-note">Every play is split by what happened: completions at the catch into air yards and yards after the catch (nflverse\'s air EPA), the cost of the ones that fell incomplete or were picked' . ( $qb ? ', sacks, scrambles and designed runs' : ( $kind === 'rb' ? ', and his designed runs' : '' ) ) . '. Bars share one scale, gains right of the line and losses left. The rate is per ' . $unit . ', with its percentile among the league\'s qualifying ' . esc_html( $plab ) . '; the best rate in each row is green.</p></section>';
	}

	/* ------------------------------------------------------------ the shape of their seasons */
	public static function trends( $P, $grp ) {
		$defs = DBD_Grades::trend_defs( $grp ); if ( ! $defs ) { return ''; }
		$nw = 0; foreach ( $P as $x ) { foreach ( (array) ( $x['rec']['weeks'] ?? array() ) as $w ) { $nw = max( $nw, (int) $w['week'] ); } }
		if ( $nw < 2 ) { return ''; }
		$WIN = DBD_Grades::TREND_WIN; $charts = ''; $any = false;
		foreach ( $defs as $d ) {
			$q = DBD_Grades::trend_league( $grp, $d[0] ); $SER = array(); $vals = array();
			foreach ( $P as $i => $x ) {
				$wk = array(); foreach ( (array) ( $x['rec']['weeks'] ?? array() ) as $w ) { $wk[ (int) $w['week'] ] = $w; } ksort( $wk ); $played = array_keys( $wk );
				$pts = array(); $faint = array();
				foreach ( $played as $j => $w ) {
					$num = 0.0; $den = 0.0; foreach ( array_slice( $played, max( 0, $j - $WIN + 1 ), min( $WIN, $j + 1 ) ) as $pw ) { list( $a, $b ) = DBD_Grades::trend_parts( $d, $wk[ $pw ] ); $num += $a; $den += $b; }
					list( $ga, $gb ) = DBD_Grades::trend_parts( $d, $wk[ $w ] );
					$line = $den ? $num / $den : null; $game = $gb ? $ga / $gb : null;
					$pts[] = array( $w, $line, null, $j === count( $played ) - 1 ? 'last' : 'pt' ); $faint[] = array( $w, $game );
					if ( $line !== null ) { $vals[] = $line; } if ( $game !== null ) { $vals[] = $game; }
				}
				$SER[] = array( $x['c'], $pts, $faint );
			}
			if ( ! $vals ) { continue; } $any = true;
			if ( $q ) { $vals[] = $q['p25']; $vals[] = $q['p75']; }
			$lo = min( $vals ); $hi = max( $vals ); $pad = ( $hi - $lo ) * 0.08 ?: 1; $lo -= $pad; $hi += $pad;
			$xl = array(); for ( $w = 1; $w <= $nw; $w++ ) { $xl[] = array( $w, (string) $w ); }
			$yl = $q ? array( array( $q['p25'], DBD_Grades::trend_fmt( $d[2], $q['p25'] ) ), array( $q['p75'], DBD_Grades::trend_fmt( $d[2], $q['p75'] ) ) ) : array();
			$ch = self::chart( $SER, $lo, $hi, 1, $nw, $xl, $yl, 150, $q ? array( $q['p25'], $q['p75'] ) : null, $q['p50'] ?? null );
			$now = ''; foreach ( $P as $i => $x ) { $l = end( $SER[ $i ][1] ); if ( $l && $l[1] !== null ) { $now .= '<span style="--c:' . esc_attr( $x['c'] ) . '"><i></i>' . esc_html( self::first( $x ) ) . ' <b>' . esc_html( DBD_Grades::trend_fmt( $d[2], $l[1] ) ) . '</b></span>'; } }
			$charts .= '<div class="cw-tr"><h4>' . esc_html( $d[1] ) . '<small>last ' . $WIN . ' games</small></h4>' . $ch . '<div class="cw-now">' . $now . '</div></div>';
		}
		if ( ! $any ) { return ''; }
		return '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>The shape of their seasons</b> · each one\'s last ' . $WIN . ' games, week by week</span><span class="dbd-badge">Weeks 1 to ' . (int) $nw . '</span></div>'
			. self::legend( $P ) . '<div class="cw-trs">' . $charts . '</div>'
			. '<p class="dbd-note">Each line is a player\'s trailing ' . $WIN . ' games (rates are pooled over the games, not averaged); the faint dots are single games. The shaded band is the league\'s middle half of single games at the position, the dashed line its median. A line that climbs out of the band is a role getting bigger.</p></section>';
	}

	/* ------------------------------------------------------------ careers */
	public static function careers( $P, $grp ) {
		$kind = DBD_Grades::kind( $grp ); $season = (int) DBD_Data::season(); $through = (int) DBD_Data::through_week();
		$yds = function ( $r ) use ( $kind ) { return $kind === 'qb' ? (float) ( $r['passing_yards'] ?? 0 ) : ( $kind === 'rb' ? (float) ( $r['rushing_yards'] ?? 0 ) + (float) ( $r['receiving_yards'] ?? 0 ) : (float) ( $r['receiving_yards'] ?? 0 ) ); };
		$td = function ( $r ) use ( $kind ) { return $kind === 'qb' ? (int) ( $r['passing_tds'] ?? 0 ) : ( $kind === 'rb' ? (int) ( $r['rushing_tds'] ?? 0 ) + (int) ( $r['receiving_tds'] ?? 0 ) : (int) ( $r['receiving_tds'] ?? 0 ) ); };
		$vol = function ( $r ) use ( $kind ) { return $kind === 'qb' ? (int) ( $r['attempts'] ?? 0 ) : ( $kind === 'rb' ? (int) ( $r['carries'] ?? 0 ) : (int) ( $r['receptions'] ?? 0 ) ); };
		$ypg = function ( $r ) use ( $yds ) { return ! empty( $r['g'] ) ? $yds( $r ) / (int) $r['g'] : null; };
		$CR = array(); $seasons = array();
		foreach ( $P as $i => $x ) {
			$f = DBD_Data::get( 'players/career/' . $x['row']['team'] . '.json' ); $rows = array_values( array_filter( (array) ( $f['players'][ $x['row']['id'] ] ?? array() ), function ( $r ) { return ! empty( $r['g'] ); } ) );
			$CR[ $i ] = $rows; foreach ( $rows as $r ) { $seasons[ (int) $r['season'] ] = true; }
		}
		if ( ! $seasons ) { return ''; }
		$seasons = array_keys( $seasons ); sort( $seasons ); $six = array_flip( $seasons );
		$hi = 1; foreach ( $CR as $rows ) { foreach ( $rows as $r ) { $v = $ypg( $r ); if ( $v !== null ) { $hi = max( $hi, $v ); } } } $hi *= 1.1;
		$step = $hi > 300 ? 100 : ( $hi > 120 ? 50 : 25 ); $yl = array(); for ( $v = $step; $v < $hi; $v += $step ) { $yl[] = array( $v, (string) $v ); }
		$ylab = $kind === 'qb' ? 'passing yards' : ( $kind === 'rb' ? 'scrimmage yards' : 'receiving yards' );
		$SER = array();
		foreach ( $P as $i => $x ) {
			$pts = array();
			foreach ( $CR[ $i ] as $r ) {
				$v = $ypg( $r ); if ( $v === null ) { continue; }
				$tip = '<b>' . esc_html( $x['row']['name'] ) . '</b> · ' . (int) $r['season'] . '<br>' . number_format( $yds( $r ) ) . ' ' . $ylab . ' in ' . (int) $r['g'] . ' games, <b>' . number_format( $v, 1 ) . '</b> a game<br>' . $td( $r ) . ' TD';
				$pts[] = array( $six[ (int) $r['season'] ], $v, $tip, (int) $r['season'] === $season ? 'cur' : 'pt' );
			}
			$SER[] = array( $x['c'], $pts, array() );
		}
		$xl = array(); foreach ( $seasons as $j => $s ) { $xl[] = array( $j, $s . ( $s === $season ? '*' : '' ) ); }
		$ch = self::chart( $SER, 0, $hi, 0, max( 1, count( $seasons ) - 1 ), $xl, $yl, 210 );
		$vh = $kind === 'qb' ? 'Att' : ( $kind === 'rb' ? 'Car' : 'Rec' );
		$tb = '';
		foreach ( $P as $i => $x ) {
			$rows = $CR[ $i ]; if ( ! $rows ) { $tb .= '<tr><td class="n"><i class="sw" style="background:' . esc_attr( $x['c'] ) . '"></i>' . esc_html( $x['row']['name'] ) . '</td><td colspan="7" class="dbd-note">No career file yet.</td></tr>'; continue; }
			$g = 0; $y = 0; $t = 0; $vo = 0; foreach ( $rows as $r ) { $g += (int) $r['g']; $y += $yds( $r ); $t += $td( $r ); $vo += $vol( $r ); }
			$past = array_values( array_filter( $rows, function ( $r ) use ( $season ) { return (int) $r['season'] !== $season; } ) ); $pool = $past ?: $rows;
			$best = $pool[0]; foreach ( $pool as $r ) { if ( $yds( $r ) > $yds( $best ) ) { $best = $r; } }
			$now = null; foreach ( $rows as $r ) { if ( (int) $r['season'] === $season ) { $now = $r; } }
			$rate = $g ? $y / $g : 0; $nr = $now ? $ypg( $now ) : null;
			$pg = 0; $py = 0; foreach ( $past as $r ) { $pg += (int) $r['g']; $py += $yds( $r ); } $prate = $pg ? $py / $pg : null;  /* his earlier seasons, for "this season" */
			$tb .= '<tr><td class="n"><i class="sw" style="background:' . esc_attr( $x['c'] ) . '"></i>' . esc_html( $x['row']['name'] ) . '<small>' . (int) $rows[0]['season'] . ' to ' . (int) end( $rows )['season'] . ' · ' . count( $rows ) . ' season' . ( count( $rows ) === 1 ? '' : 's' ) . '</small></td>'
				. '<td>' . $g . '</td><td class="dbd-t3">' . number_format( $vo ) . '</td><td>' . number_format( $y ) . '</td><td>' . $t . '</td><td>' . number_format( $rate, 1 ) . '</td>'
				. '<td class="dbd-t2">' . (int) $best['season'] . ': ' . number_format( $yds( $best ) ) . '<small>' . number_format( (float) $ypg( $best ), 1 ) . ' a game</small></td>'
				. '<td>' . ( $nr === null ? '–' : number_format( $nr, 1 ) . '<small>' . ( $prate !== null ? ( $nr >= $prate ? 'above' : 'below' ) . ' his career rate' : 'first season' ) . '</small>' ) . '</td></tr>';
		}
		return '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Careers</b> · ' . esc_html( $ylab ) . ' a game, season by season</span><span class="dbd-badge">Regular season</span></div>'
			. self::legend( $P ) . '<div class="cw-cr">' . $ch . '</div>'
			. '<div class="dbd-tw"><table class="dbd-stat cw-crt"><thead><tr><th>Player</th><th>G</th><th class="dbd-t3">' . $vh . '</th><th>Yds</th><th>TD</th><th>Yds/g</th><th class="dbd-t2">Best season</th><th>This season</th></tr></thead><tbody>' . $tb . '</tbody></table></div>'
			. '<p class="dbd-note">Regular season only, from each player\'s career file. * ' . $season . ' is through Week ' . max( 1, $through ) . '; its dot is hollow because the season is not over. "This season" compares this year\'s rate with his earlier seasons. Hover a dot for the season\'s line.</p></section>';
	}
}
