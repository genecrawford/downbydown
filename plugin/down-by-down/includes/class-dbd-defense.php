<?php
/**
 * The defensive half (SCOPE §39): a team's Defense tab, from defense/{TEAM}.json and defense/league.json (the build),
 * metrics.json (the position ranks the player pages quote) and fantasy.json (points allowed). Ranks put 1st the best
 * defense, or for the style figures the most. The explorer below the tab runs on plays/def/{TEAM}.json.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Defense {
	/* key, label, sub-label, format, higher is better for the defense */
	const SC = array(
		array( 'epa', 'EPA per play', 'every snap', 'sgn', false ), array( 'pepa', 'Pass EPA', 'per dropback', 'sgn', false ), array( 'repa', 'Rush EPA', 'per designed run', 'sgn', false ),
		array( 'sr', 'Success rate', 'plays that gain enough', 'pct', false ), array( 'expl', 'Explosive rate', '20+ pass, 10+ run', 'pct', false ), array( 'ypp', 'Yards per play', '', 'f1', false ),
		array( 'ypc', 'Yards per carry', '', 'f1', false ), array( 'third', 'Third downs', 'converted against them', 'pct', false ), array( 'sack', 'Sack rate', 'per dropback', 'pct1', true ),
		array( 'press', 'Pressure rate', 'per dropback, PFR', 'pct', true ), array( 'hit', 'QB hit rate', 'per dropback', 'pct', true ), array( 'intr', 'INT rate', 'per dropback', 'pct1', true ),
		array( 'rz_td', 'Red-zone TD rate', 'trips ending in a touchdown', 'pct', false ), array( 'ppd', 'Points per drive', 'touchdown 7, field goal 3', 'f2', false ),
		array( 'take_pg', 'Takeaways', 'per game', 'f1', true ), array( 'adj', 'EPA against expectation', 'allowed minus the offenses\' usual', 'sgn', false ),
	);
	const DEPTH = array( array( 'dp', 'Deep', '20+ yds' ), array( 'in', 'Intermediate', '10 to 19' ), array( 'sh', 'Short', '0 to 9' ), array( 'bh', 'Behind', 'the line' ) );
	const LANES = array( array( 'le', 'Left end', 'LE', 'outside left' ), array( 'lt', 'Left tackle', 'LT', 'off left tackle' ), array( 'lg', 'Left guard', 'LG', 'at left guard' ), array( 'mid', 'Middle', 'MID', 'up the middle' ),
		array( 'rg', 'Right guard', 'RG', 'at right guard' ), array( 'rt', 'Right tackle', 'RT', 'off right tackle' ), array( 're', 'Right end', 'RE', 'outside right' ) );

	/* formats */
	public static function pct( $v ) { return $v === null ? '–' : round( $v * 100 ) . '%'; }
	public static function pct1( $v ) { return $v === null ? '–' : number_format( $v * 100, 1 ) . '%'; }
	public static function sgn( $v, $k = 2 ) { return $v === null ? '–' : ( $v > 0 ? '+' : '' ) . number_format( (float) $v, $k ); }
	public static function f1( $v ) { return $v === null ? '–' : number_format( (float) $v, 1 ); }
	public static function f2( $v ) { return $v === null ? '–' : number_format( (float) $v, 2 ); }
	public static function fmt( $kind, $v ) { switch ( $kind ) { case 'sgn': return self::sgn( $v ); case 'sgn1': return self::sgn( $v, 1 ); case 'pct': return self::pct( $v ); case 'pct1': return self::pct1( $v ); case 'f2': return self::f2( $v ); default: return self::f1( $v ); } }
	public static function ord( $n ) { return DBD_Grades::ordinal( (int) $n ); }
	/** 1 + the teams better (or, for a style figure, higher) than $v. */
	public static function rank_of( $vals, $v, $hib ) { $r = 1; foreach ( (array) $vals as $x ) { if ( $hib ? $x > $v : $x < $v ) { $r++; } } return $r; }
	public static function pctile( $L, $k, $v, $hib ) {
		$a = (array) ( $L['dist'][ $k ] ?? array() ); if ( ! $a || $v === null ) { return null; }
		$below = 0; $eq = 0; foreach ( $a as $x ) { if ( $x < $v ) { $below++; } elseif ( $x == $v ) { $eq++; } }
		$p = ( $below + $eq / 2 ) / count( $a ); return (int) round( 100 * ( $hib ? $p : 1 - $p ) );
	}
	private static function val( $D, $k ) { return array_key_exists( $k, (array) ( $D['all'] ?? array() ) ) ? $D['all'][ $k ] : ( $D[ $k ] ?? null ); }
	private static function tile_logo( $a, $s = 16 ) { return DBD_Marks::tile( $a, $s ); }

	/** The whole tab above the explorer. */
	/** Every field the renderers read, present (null, 0 or an empty list), so a short or older file never raises a warning. */
	public static function normalize( $D ) {
		$D = (array) $D;
		foreach ( array( 'rz_td', 'press', 'pressures', 'adj', 'sos', 'mtk', 'mtr', 'tkl', 'pa_pg', 'take_pg' ) as $k ) { if ( ! array_key_exists( $k, $D ) ) { $D[ $k ] = null; } }
		foreach ( array( 'rz_n', 'pen', 'pen_yds', 'pen_fd', 'take', 'int', 'fum', 'grid_n', 'lanes_n' ) as $k ) { $D[ $k ] = (int) ( $D[ $k ] ?? 0 ); }
		foreach ( array( 'grid', 'lanes', 'sit', 'style', 'who', 'weeks', 'defenders', 'rank', 'all' ) as $k ) { $D[ $k ] = (array) ( $D[ $k ] ?? array() ); }
		foreach ( array( 'epa', 'sr', 'ypp', 'expl', 'pepa', 'repa', 'ypc', 'sack', 'hit', 'intr', 'third' ) as $k ) { if ( ! array_key_exists( $k, $D['all'] ) ) { $D['all'][ $k ] = null; } }
		foreach ( array( 'blitz', 'rush', 'four', 'e_bz', 'e_nb', 'box', 'heavy', 'ypc_h', 'ypc_l', 'pa', 'e_pa', 'scr', 'e_scr', 'mot', 'e_mot', 'proe', 'cpoe' ) as $k ) { if ( ! array_key_exists( $k, $D['style'] ) ) { $D['style'][ $k ] = null; } }
		return $D;
	}

	public static function page( $a, $D ) {
		$D = self::normalize( $D );
		$L = DBD_Data::defense_league(); $t = DBD_Data::team( $a ); $nick = $t['nick'];
		$h = '<nav class="dbd-jump" aria-label="On this page"><a href="#df-good">How good is it</a><a href="#df-beat">Where offenses find room</a><a href="#df-play">How they play</a><a href="#df-who">The defenders</a><a href="#df-snaps">Every snap</a></nav>';
		$h .= self::tiles( $D ) . self::short( $a, $D, $L, $nick );
		$h .= '<section class="dbd-band" id="df-good"><h2 class="dbd-bandh">How good is it<span>the defense against the league, and game by game</span></h2>' . self::scorecard( $D, $L, $nick ) . self::games( $D, $L ) . '</section>';
		$h .= '<section class="dbd-band" id="df-beat"><h2 class="dbd-bandh">Where offenses find room<span>the field, the run lanes, the positions and the situations</span></h2>' . self::tabbed( array(
			array( 'Pass by zone', self::pass_zone( $D, $L, 0 ) ), array( 'Runs by lane', self::run_lanes( $D, $L, 1 ) ), array( 'By position', self::positions( $a, $D, 2 ) ), array( 'Situations', self::situations( $D, $L, 3 ) ) ) ) . '</section>';
		$h .= '<section class="dbd-band" id="df-play"><h2 class="dbd-bandh">How they play<span>pressure, the box, what offenses try, discipline</span></h2>' . self::style( $D, $L ) . '</section>';
		$h .= '<section class="dbd-band" id="df-who"><h2 class="dbd-bandh">The defenders<span>who plays, and what each one has done</span></h2>' . self::defenders( $a, $D ) . '</section>';
		return $h;
	}

	private static function tabbed( $panes ) {
		$btn = ''; foreach ( $panes as $i => $p ) { $btn .= '<button class="t-tab" type="button" role="tab" aria-selected="' . ( $i ? 'false' : 'true' ) . '" data-pane="' . $i . '">' . esc_html( $p[0] ) . '</button>'; }
		return '<section class="dbd-panel dbd-tabbed"><div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>' . $btn . '</div></div>' . implode( '', array_column( $panes, 1 ) ) . '</section>';
	}

	public static function tiles( $D ) {
		$al = (array) ( $D['all'] ?? array() ); $R = (array) ( $D['rank'] ?? array() ); $L = DBD_Data::defense_league(); $lg = (array) ( $L['lg']['all'] ?? array() );
		$tile = function ( $lab, $v, $sub, $k ) use ( $R ) {
			$r = (int) ( $R[ $k ] ?? 0 );
			return '<div class="dbd-tile"><div class="l">' . esc_html( $lab ) . '</div><div class="v">' . esc_html( $v ) . '</div><div class="s">' . esc_html( $sub ) . '</div>' . ( $r ? '<div class="dbd-trk' . ( $r <= 10 ? ' top' : '' ) . '" data-tip="' . esc_attr( '<b>' . self::ord( $r ) . '</b> of 32 defenses' ) . '" tabindex="0"><b>' . esc_html( self::ord( $r ) ) . '</b> of 32</div>' : '' ) . '</div>';
		};
		$rzn = (int) ( $D['rz_n'] ?? 0 ); $rzt = $D['rz_td'] !== null ? (int) round( $D['rz_td'] * $rzn ) : 0;
		return '<div class="dbd-tiles">' . $tile( 'EPA allowed', self::sgn( $al['epa'] ?? null ), 'per play, league ' . self::sgn( $lg['epa'] ?? null ), 'epa' )
			. $tile( 'Success allowed', self::pct( $al['sr'] ?? null ), 'league ' . self::pct( $lg['sr'] ?? null ), 'sr' )
			. $tile( 'Points allowed', self::f1( $D['pa_pg'] ?? null ), 'per game', 'pa_pg' )
			. $tile( 'Takeaways', (string) (int) ( $D['take'] ?? 0 ), (int) ( $D['int'] ?? 0 ) . ' INT · ' . (int) ( $D['fum'] ?? 0 ) . ' fumbles', 'take_pg' )
			. $tile( 'Sack rate', self::pct1( $al['sack'] ?? null ), 'of dropbacks, league ' . self::pct1( $lg['sack'] ?? null ), 'sack' )
			. $tile( 'Pressure rate', self::pct( $D['press'] ?? null ), ( $D['pressures'] !== null ? (int) $D['pressures'] . ' pressures' : 'PFR charting' ), 'press' )
			. $tile( 'Third downs', self::pct( $al['third'] ?? null ), 'converted against them', 'third' )
			. $tile( 'Red zone', self::pct( $D['rz_td'] ?? null ), 'TD on ' . $rzt . ' of ' . $rzn . ' trips', 'rz_td' ) . '</div>';
	}

	private static function short( $a, $D, $L, $nick ) {
		$al = (array) $D['all']; $R = (array) $D['rank']; $st = (array) ( $D['style'] ?? array() ); $teams = DBD_Data::teams(); $li = array();
		$top = (int) ( $R['epa'] ?? 0 );
		$li[] = array( 'How good', ( $top === 1 ? 'The league\'s best defense: ' : '' ) . '<b>' . self::sgn( $al['epa'] ) . '</b> EPA a play allowed, <b>' . self::ord( $top ) . '</b> of 32' . ( $D['adj'] !== null ? ', and <b>' . number_format( abs( $D['adj'] ), 2 ) . '</b> ' . ( $D['adj'] < 0 ? 'below' : 'above' ) . ' what the offenses they faced usually manage (' . self::ord( $R['adj'] ?? 0 ) . ').' : '.' ), '#df-good' );
		if ( isset( $st['blitz'] ) && $st['blitz'] !== null ) { $br = self::rank_of( $L['style']['blitz'] ?? array(), $st['blitz'], true );
			$li[] = array( 'How they play', 'They blitz on <b>' . self::pct( $st['blitz'] ) . '</b> of dropbacks, the <b>' . self::ord( $br ) . '</b>-most in the league' . ( $D['press'] !== null ? ', and get pressure on <b>' . self::pct( $D['press'] ) . '</b>.' : '.' ), '#df-play' ); }
		/* where offenses find room: the weaker phase, then its worst spot */
		$runs = (int) ( $R['repa'] ?? 0 ) > (int) ( $R['pepa'] ?? 0 );
		if ( $runs ) { $lw = self::worst_lane( $D );
			$li[] = array( 'Where offenses find room', 'On the ground: <b>' . self::f1( $al['ypc'] ) . '</b> a carry, <b>' . self::ord( $R['ypc'] ?? 0 ) . '</b>' . ( $lw ? ', and runs ' . $lw[3] . ' gain <b>' . self::f1( $D['lanes'][ $lw[0] ][1] ) . '</b> a carry.' : '.' ), '#df-beat' ); }
		else { $zw = self::worst_zone( $D, $L );
			$li[] = array( 'Where offenses find room', 'Through the air: <b>' . self::sgn( $al['pepa'] ) . '</b> EPA a dropback, <b>' . self::ord( $R['pepa'] ?? 0 ) . '</b>' . ( $zw ? ', softest ' . self::zone_name( $zw ) . ' (' . self::sgn( $D['grid'][ $zw ][1] ) . ' a throw).' : '.' ), '#df-beat' ); }
		$w = $D['who'][0] ?? null;
		if ( $w ) { $li[] = array( 'Who hurt them', '<b>' . esc_html( $w[1] ) . '</b> (' . esc_html( $w[2] ) . '): ' . (int) $w[3] . ' yards and ' . self::sgn( $w[4], 1 ) . ' EPA on ' . (int) $w[5] . ' touches, the most anyone has managed against them.', '#df-beat' ); }
		$nx = DBD_Fantasy::next_game( $a );
		if ( $nx && ! empty( $L['off_epa'] ) ) { $oe = $L['off_epa']; arsort( $oe ); $orank = array_search( $nx['opp'], array_keys( $oe ), true );
			$li[] = array( 'Next up', 'Week ' . (int) $nx['wk'] . ' against the <b>' . esc_html( $teams[ $nx['opp'] ]['nick'] ?? $nx['opp'] ) . '</b>, whose offense ranks <b>' . self::ord( $orank + 1 ) . '</b> in EPA a play.', '#df-good' ); }
		$h = '<section class="dbd-panel dbd-short"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b></span></div><ul>';
		foreach ( $li as $x ) { $h .= '<li><a href="' . esc_attr( $x[2] ) . '">' . esc_html( $x[0] ) . '</a><span>' . $x[1] . '</span></li>'; }
		return $h . '</ul></section>';
	}
	private static function worst_lane( $D ) { $best = null; foreach ( self::LANES as $l ) { $v = $D['lanes'][ $l[0] ] ?? null; if ( ! $v || $v[0] < 8 || $v[2] === null ) { continue; } if ( ! $best || $v[2] > $D['lanes'][ $best[0] ][2] ) { $best = $l; } } return $best; }
	private static function zone_gap( $D, $L, $k ) { $v = $D['grid'][ $k ] ?? null; $l = $L['lg']['grid'][ $k ] ?? null; return ( $v && $l && $v[1] !== null && $l[1] !== null ) ? $v[1] - $l[1] : null; }
	private static function worst_zone( $D, $L ) { $b = null; $bv = null; foreach ( (array) $D['grid'] as $k => $v ) { if ( $v[0] < 6 ) { continue; } $g = self::zone_gap( $D, $L, $k ); if ( $g !== null && ( $bv === null || $g > $bv ) ) { $b = $k; $bv = $g; } } return $b; }
	private static function best_zone( $D, $L ) { $b = null; $bv = null; foreach ( (array) $D['grid'] as $k => $v ) { if ( $v[0] < 6 ) { continue; } $g = self::zone_gap( $D, $L, $k ); if ( $g !== null && ( $bv === null || $g < $bv ) ) { $b = $k; $bv = $g; } } return $b; }
	private static function zone_name( $k ) { list( $d, $loc ) = explode( '.', $k ); return array( 'dp' => 'deep', 'in' => 'intermediate', 'sh' => 'short', 'bh' => 'behind the line' )[ $d ] . ' ' . $loc; }

	private static function scorecard( $D, $L, $nick ) {
		$row = function ( $r ) use ( $D, $L, $nick ) {
			list( $k, $lab, $sub, $kind, $hib ) = $r; $v = self::val( $D, $k ); $rk = (int) ( $D['rank'][ $k ] ?? 0 ); $p = self::pctile( $L, $k, $v, $hib );
			$a = (array) ( $L['dist'][ $k ] ?? array() ); $med = $a ? $a[ (int) floor( ( count( $a ) - 1 ) / 2 ) ] : null;
			$tip = '<b>' . esc_html( $nick ) . ' defense</b><br>' . esc_html( $lab ) . ': <b>' . esc_html( self::fmt( $kind, $v ) ) . '</b>' . ( $p !== null ? ' · ' . self::ord( $p ) . ' percentile · ' . self::ord( $rk ) . ' of 32' : '' ) . '<br><span class="m">League median ' . esc_html( self::fmt( $kind, $med ) ) . '</span>';
			return '<tr><td class="n">' . esc_html( $lab ) . ( $sub !== '' ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</td><td class="v">' . esc_html( self::fmt( $kind, $v ) ) . '</td><td class="rk">' . ( $rk ? '<b>' . self::ord( $rk ) . '</b> of 32' : '–' ) . '</td>'
				. '<td class="b"><div class="bar">' . ( $p === null ? '' : '<i style="--p:' . $p . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $lab . ' ' . self::fmt( $kind, $v ) . ', ' . self::ord( $p ) . ' percentile' ) . '"></i>' ) . '</div></td><td class="g' . ( $p !== null && $p >= 75 ? ' hi' : ( $p !== null && $p <= 25 ? ' lo' : '' ) ) . '">' . ( $p === null ? '–' : self::ord( $p ) ) . '</td></tr>';
		};
		$head = '<thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead>'; $sc = self::SC;
		return '<section class="dbd-panel dbd-score"><div class="dbd-ph"><span class="dbd-lab"><b>Scorecard</b> · against all 32 defenses</span><span class="dbd-badge">League percentiles</span></div>'
			. '<div class="dbd-score2"><table class="dbd-stat">' . $head . '<tbody>' . implode( '', array_map( $row, array_slice( $sc, 0, 8 ) ) ) . '</tbody></table><table class="dbd-stat">' . $head . '<tbody>' . implode( '', array_map( $row, array_slice( $sc, 8 ) ) ) . '</tbody></table></div>'
			. '<p class="dbd-note">Each bar is the league\'s 32 defenses; the marker is the ' . esc_html( $nick ) . '. Right is better for the defense, so a low EPA allowed sits right. The pressure rate is PFR\'s pressures (hurries, hits and sacks) per dropback. EPA against expectation compares each game with what that offense averaged in its other games, so a strong schedule is not held against them.</p></section>';
	}

	private static function games( $D, $L ) {
		$teams = DBD_Data::teams(); $wk = array_values( array_filter( (array) $D['weeks'], function ( $w ) { return $w['oe'] !== null && $w['epa'] !== null; } ) ); if ( ! $wk ) { return ''; }
		$held = count( array_filter( $wk, function ( $w ) { return $w['epa'] < $w['oe']; } ) ); $span = 0.05; foreach ( $wk as $w ) { $span = max( $span, abs( $w['epa'] ), abs( $w['oe'] ) ); } $span *= 1.15;
		$gx = function ( $v ) use ( $span ) { return round( 50 + $v / $span * 50, 1 ); };
		$rows = ''; $best = null;
		foreach ( $wk as $w ) {
			if ( ! $best || $w['epa'] - $w['oe'] < $best['epa'] - $best['oe'] ) { $best = $w; }
			$pf = (int) $w['pf']; $pa = (int) $w['pa']; $res = ( $pf > $pa ? 'W' : ( $pf < $pa ? 'L' : 'T' ) ) . ' ' . $pf . '-' . $pa; $better = $w['epa'] < $w['oe'];
			$lo = min( $gx( $w['epa'] ), $gx( $w['oe'] ) ); $hi = max( $gx( $w['epa'] ), $gx( $w['oe'] ) ); $on = $teams[ $w['opp'] ]['nick'] ?? $w['opp'];
			$tip = '<b>Week ' . (int) $w['wk'] . ' ' . ( $w['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $on ) . '</b><br>Allowed ' . self::sgn( $w['epa'] ) . ' EPA a play · the ' . esc_html( $on ) . ' usually manage ' . self::sgn( $w['oe'] ) . '<br>' . number_format( abs( $w['epa'] - $w['oe'] ), 2 ) . ( $better ? ' better' : ' worse' ) . ' than their usual';
			$rows .= '<div class="gw"><span class="wkl">Wk ' . (int) $w['wk'] . '</span><span class="op">' . self::tile_logo( $w['opp'] ) . ( $w['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $w['opp'] ) . '</span><span class="rs ' . ( $pf > $pa ? 'w' : ( $pf < $pa ? 'l' : '' ) ) . '">' . esc_html( $res ) . '</span>'
				. '<span class="tr" data-tip="' . esc_attr( $tip ) . '" tabindex="0"><i class="z"></i><i class="gap ' . ( $better ? 'good' : 'bad' ) . '" style="left:' . $lo . '%;width:' . round( $hi - $lo, 1 ) . '%"></i><i class="ex" style="left:' . $gx( $w['oe'] ) . '%"></i><i class="me" style="left:' . $gx( $w['epa'] ) . '%"></i></span>'
				. '<span class="num">' . self::sgn( $w['epa'] ) . '<small>usual ' . self::sgn( $w['oe'] ) . '</small></span></div>';
		}
		$sosr = self::rank_of( $L['style']['sos'] ?? array(), $D['sos'], true ); $bn = $teams[ $best['opp'] ]['nick'] ?? $best['opp'];
		$say = 'They held <b>' . $held . ' of ' . count( $wk ) . '</b> offenses below their usual EPA a play. ' . ( $best['epa'] < $best['oe'] ? 'The biggest stop: <b>Week ' . (int) $best['wk'] . '</b> against the ' . esc_html( $bn ) . ', ' . number_format( abs( $best['epa'] - $best['oe'] ), 2 ) . ' a play under their norm. ' : '' )
			. 'The offenses they have faced average ' . self::sgn( $D['sos'] ) . ' a play elsewhere, the <b>' . self::ord( $sosr ) . '</b>-toughest schedule of offenses.';
		return '<section class="dbd-panel dbd-gbg"><div class="dbd-ph"><span class="dbd-lab"><b>Game by game</b> · EPA a play allowed, against what each offense usually manages</span><span class="dbd-badge">held ' . $held . ' of ' . count( $wk ) . ' below their usual</span></div>'
			. '<p class="dbd-wp-say">' . $say . '</p><div class="gwl"><div class="gw hd"><span>Week</span><span>Opponent</span><span>Result</span><span class="trh"><em>better for the defense ←</em><em>→ worse</em></span><span>Allowed</span></div>' . $rows . '</div>'
			. '<div class="dbd-legend"><span><i class="sq" style="background:var(--dbd-ink)"></i>EPA a play allowed</span><span><i class="sq hollow"></i>that offense\'s usual, in its other games</span><span><i class="sq" style="background:rgba(30,127,74,.35)"></i>held below</span><span><i class="sq" style="background:rgba(180,38,31,.3)"></i>gave up more</span></div></section>';
	}

	private static function pass_zone( $D, $L, $i ) {
		$G = (array) $D['grid']; $LG = (array) ( $L['lg']['grid'] ?? array() ); $gn = max( 1, (int) $D['grid_n'] ); $lgn = 0; foreach ( $LG as $v ) { $lgn += (int) $v[0]; } $lgn = max( 1, $lgn );
		$cells = '<div class="rl"></div><div class="ch">Left</div><div class="ch">Middle</div><div class="ch">Right</div>';
		foreach ( self::DEPTH as $dd ) {
			$cells .= '<div class="rl"><b>' . $dd[1] . '</b><small>' . $dd[2] . '</small></div>';
			foreach ( array( 'left', 'middle', 'right' ) as $loc ) {
				$k = $dd[0] . '.' . $loc; list( $n, $e, $cp ) = $G[ $k ] ?? array( 0, null, null ); list( $ln, $le, $lc ) = $LG[ $k ] ?? array( 0, null, null );
				$fr = $n / $gn; $lfr = $ln / $lgn; $te = ( $e !== null && $le !== null ) ? max( -0.55, min( 0.55, -( $e - $le ) / 0.8 ) ) : 0;  /* capped so the numbers stay readable */
				$tip = '<b>' . $dd[1] . ' ' . $loc . '</b>: ' . (int) $n . ' of ' . $gn . ' throws against them (' . round( $fr * 100 ) . '%) · league ' . round( $lfr * 100 ) . '%<br>' . ( $e !== null ? self::sgn( $e ) . ' EPA per throw allowed · league ' . self::sgn( $le ) . '<br>' . self::pct( $cp ) . ' completed · league ' . self::pct( $lc ) : 'no throws' );
				$cells .= '<div class="cell' . ( $n < 5 ? ' low' : '' ) . '" style="--tf:' . round( min( 1, $fr / 0.25 ), 2 ) . ';--te:' . round( $te, 2 ) . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0"><span class="f">' . round( $fr * 100 ) . '%</span><span class="d' . ( $fr - $lfr >= .02 ? ' up' : ( $lfr - $fr >= .02 ? ' dn' : '' ) ) . '">league ' . round( $lfr * 100 ) . '%</span>'
					. '<span class="e">' . self::sgn( $e ) . '</span><span class="le">league ' . self::sgn( $le ) . '</span><span class="n">' . (int) $n . ' throw' . ( (int) $n === 1 ? '' : 's' ) . '</span></div>';
			}
		}
		$w = self::worst_zone( $D, $L ); $b = self::best_zone( $D, $L ); $st = (array) ( $D['style'] ?? array() ); $say = '';
		if ( $w ) { $say .= 'The soft spot is <b>' . self::zone_name( $w ) . '</b>: ' . self::sgn( $G[ $w ][1] ) . ' EPA a throw against a league ' . self::sgn( $LG[ $w ][1] ) . '. '; }
		if ( $b && $b !== $w ) { $say .= 'The lockdown zone is <b>' . self::zone_name( $b ) . '</b>, ' . self::sgn( $G[ $b ][1] ) . ' a throw (league ' . self::sgn( $LG[ $b ][1] ) . '). '; }
		if ( isset( $st['cpoe'] ) && $st['cpoe'] !== null ) { $cr = self::rank_of( $L['style']['cpoe'] ?? array(), $st['cpoe'], false ); $say .= 'Completions against them run <b>' . number_format( abs( $st['cpoe'] ), 1 ) . ' points ' . ( $st['cpoe'] < 0 ? 'under' : 'over' ) . ' expected</b>, ' . self::ord( $cr ) . '-best in the league.'; }
		return '<section class="dbd-panel dbd-pane dbd-lz" data-pane="' . $i . '"' . ( $i ? ' hidden' : '' ) . ' data-mode="e"><div class="dbd-ph"><span class="dbd-lab"><b>Pass by depth and direction</b><span class="sub">where offenses throw against them, against the league</span></span>'
			. '<div class="dbd-seg t-tabs dbd-lzv" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" data-m="f" aria-selected="false">How often</button><button class="t-tab" type="button" role="tab" data-m="e" aria-selected="true">EPA allowed</button></div></div>'
			. '<p class="dbd-ep-say">' . $say . '</p><div class="dbd-lzg dbd-dlzg">' . $cells . '</div><p class="dbd-note">Every pass they faced with a charted air distance and side, sacks excluded. Green is a zone where they allow less than the league, red more. How often shows the share of throws against them there.</p></section>';
	}

	private static function run_lanes( $D, $L, $i ) {
		$LN = (array) $D['lanes']; $LL = (array) ( $L['lg']['lanes'] ?? array() ); $tot = max( 1, (int) $D['lanes_n'] ); $h = '';
		foreach ( self::LANES as $l ) {
			list( $n, $ypc, $e, $sr ) = $LN[ $l[0] ] ?? array( 0, null, null, null ); list( , $lypc, $le, ) = $LL[ $l[0] ] ?? array( 0, null, null, null );
			$cls = ( $e !== null && $le !== null ) ? ( $e < $le - .05 ? ' good' : ( $e > $le + .05 ? ' bad' : '' ) ) : '';
			$tip = '<b>' . $l[1] . '</b>: ' . (int) $n . ' carries against them (' . self::pct( $n / $tot ) . ')<br>' . self::f1( $ypc ) . ' yards a carry · league ' . self::f1( $lypc ) . '<br>' . self::sgn( $e ) . ' EPA · league ' . self::sgn( $le ) . ' · ' . self::pct( $sr ) . ' success';
			$h .= '<div class="ln' . $cls . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0"><b>' . self::f1( $ypc ) . '</b><small>yds/car</small><span class="ep">' . self::sgn( $e ) . '</span><span class="cn">' . (int) $n . ' car</span><i style="--h:' . round( min( 1, ( $n / $tot ) / 0.3 ) * 100 ) . '%"></i><em data-s="' . $l[2] . '">' . $l[1] . '</em></div>';
		}
		$al = (array) $D['all']; $w = self::worst_lane( $D ); $say = 'Offenses run for <b>' . self::f1( $al['ypc'] ) . '</b> yards a carry against them, <b>' . self::ord( $D['rank']['ypc'] ?? 0 ) . '</b> of 32.';
		if ( $w ) { $say .= ' The leak is ' . $w[3] . ': <b>' . self::f1( $LN[ $w[0] ][1] ) . '</b> a carry and ' . self::sgn( $LN[ $w[0] ][2] ) . ' EPA (league ' . self::f1( $LL[ $w[0] ][1] ?? null ) . ', ' . self::sgn( $LL[ $w[0] ][2] ?? null ) . ').'; }
		return '<section class="dbd-panel dbd-pane" data-pane="' . $i . '" hidden><div class="dbd-ph"><span class="dbd-lab"><b>Runs by lane</b><span class="sub">where offenses run against them, from left end to right end</span></span><span class="dbd-badge">' . (int) $D['lanes_n'] . ' designed runs</span></div>'
			. '<p class="dbd-ep-say">' . $say . '</p><div class="dbd-lanes">' . $h . '<div class="line"><span>TE</span><span>LT</span><span>LG</span><span>C</span><span>RG</span><span>RT</span><span>TE</span></div></div>'
			. '<div class="dbd-legend"><span><i class="sq" style="background:rgba(30,127,74,.35)"></i>held under the league\'s EPA there</span><span><i class="sq" style="background:rgba(180,38,31,.3)"></i>over it</span><span>Bar height is the share of runs</span></div></section>';
	}

	private static function positions( $a, $D, $i ) {
		$M = DBD_Data::metrics(); $dm = (array) ( $M['defense'][ $a ] ?? array() ); $dr = (array) ( $M['rank']['def'][ $a ] ?? array() ); $teams = DBD_Data::teams(); $rows = '';
		$fxc = function ( $pos ) use ( $a ) { $h = ''; foreach ( DBD_Fantasy::FMT as $fm ) { $al = DBD_Fantasy::allowed( $a, $pos, $fm ); $h .= '<span data-fx="' . $fm . '">' . ( $al ? DBD_Fantasy::num( $al[0] ) . '<small>' . esc_html( DBD_Fantasy::most( $al[1], $al[2] ) ) . '</small>' : '–' ) . '</span>'; } return $h; };
		foreach ( array( array( 'WR', 'Wide receivers' ), array( 'TE', 'Tight ends' ), array( 'RB', 'Backs, receiving' ) ) as $p ) {
			$e = $dm[ $p[0] . '_epa' ] ?? null;
			$rows .= '<tr><td class="n">' . $p[1] . '</td><td>' . self::f1( $dm[ $p[0] . '_tgt_pg' ] ?? null ) . '<small>' . self::ord( $dr[ $p[0] . '_tgt_pg' ] ?? 0 ) . '</small></td><td>' . self::f1( $dm[ $p[0] . '_yds_pg' ] ?? null ) . '<small>' . self::ord( $dr[ $p[0] . '_yds_pg' ] ?? 0 ) . '</small></td>'
				. '<td class="' . ( $e !== null && $e < 0 ? 'pos' : 'neg' ) . '">' . self::sgn( $e ) . '<small>' . self::ord( $dr[ $p[0] . '_epa' ] ?? 0 ) . '-toughest</small></td><td class="fx">' . $fxc( $p[0] ) . '</td></tr>';
		}
		$rows .= '<tr><td class="n">Quarterbacks</td><td>–</td><td>–</td><td>–</td><td class="fx">' . $fxc( 'QB' ) . '</td></tr>';
		$who = '';
		foreach ( (array) $D['who'] as $w ) { $who .= '<tr><td class="n">' . self::tile_logo( $w[2] ) . '<a href="' . esc_url( DBD_Router::url( array( 'player' => $w[0] ) ) ) . '">' . esc_html( $w[1] ) . '</a></td><td>' . esc_html( $w[2] ) . '</td><td>' . (int) $w[5] . '</td><td>' . (int) $w[3] . '</td><td>' . ( (int) $w[6] ?: '' ) . '</td><td class="' . ( $w[4] > 0 ? 'pos' : 'neg' ) . '">' . self::sgn( $w[4], 1 ) . '</td></tr>'; }
		return '<section class="dbd-panel dbd-pane" data-pane="' . $i . '" hidden><div class="dbd-ph"><span class="dbd-lab"><b>By position</b><span class="sub">what each position does against them, the ranks the player pages quote</span></span>' . DBD_Fantasy::seg() . '</div>'
			. '<div class="dbd-tw"><table class="dbd-stat dbd-dpos"><thead><tr><th>Position</th><th>Targets a game</th><th>Yards a game</th><th>EPA per target</th><th>Fantasy points allowed</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
			. ( $who ? '<h4 class="dbd-h4">Who hurt them most</h4><div class="dbd-tw"><table class="dbd-stat dbd-dwho"><thead><tr><th>Player</th><th>Team</th><th>Touches</th><th>Yards</th><th>TD</th><th>EPA</th></tr></thead><tbody>' . $who . '</tbody></table></div>' : '' )
			. '<p class="dbd-note">Targets, yards and EPA per target by the receiver\'s position, ranked 1st for the toughest defense: the same numbers a receiver\'s This week panel quotes against this defense. Fantasy points allowed follow the scoring switch. Who hurt them ranks every opposing player by EPA on his targets and carries against this defense.</p></section>';
	}

	private static function situations( $D, $L, $i ) {
		$S = (array) $D['sit']; $LS = (array) ( $L['lg']['sit'] ?? array() );
		$row = function ( $lab, $k, $conv ) use ( $S, $LS ) {
			$v = $S[ $k ] ?? array( 0, null ); $l = $LS[ $k ] ?? array( 0, null ); if ( $v[1] === null || $l[1] === null ) { return '<tr><td class="n">' . $lab . '</td><td>' . (int) $v[0] . '</td><td>–</td><td>–</td><td></td></tr>'; }
			$good = $v[1] < $l[1];
			$diff = $conv ? ( $good ? '−' : '+' ) . abs( round( ( $v[1] - $l[1] ) * 100 ) ) . ' pts' : self::sgn( $v[1] - $l[1] );
			return '<tr><td class="n">' . $lab . '</td><td>' . (int) $v[0] . '</td><td>' . ( $conv ? self::pct( $v[1] ) : self::sgn( $v[1] ) ) . '</td><td>' . ( $conv ? self::pct( $l[1] ) : self::sgn( $l[1] ) ) . '</td><td class="' . ( $good ? 'pos' : 'neg' ) . '">' . $diff . '</td></tr>';
		};
		$rows = $row( '1st down', 'd1', false ) . $row( '2nd down', 'd2', false ) . $row( '3rd down', 'd3', false ) . $row( '3rd and 1 to 3', '3s', true ) . $row( '3rd and 4 to 6', '3m', true ) . $row( '3rd and 7+', '3l', true )
			. $row( 'Red zone', 'rz', false ) . $row( 'Two-minute drill', '2m', false ) . $row( 'When they lead', 'lead', false ) . $row( 'One-score game', 'close', false ) . $row( 'When they trail by 9+', 'trail', false );
		$lab = array( 'd1' => '1st down', 'd2' => '2nd down', 'd3' => '3rd down', 'rz' => 'the red zone', '2m' => 'the two-minute drill', 'lead' => 'games they lead', 'close' => 'one-score games', 'trail' => 'games they trail by 9+' );
		$n3 = array( '3s' => 'third-and-short (1 to 3)', '3m' => 'third-and-4-to-6', '3l' => 'third-and-7+' );
		$ep = array(); foreach ( $lab as $k => $_ ) { if ( ( $S[ $k ][0] ?? 0 ) >= 20 && isset( $S[ $k ][1], $LS[ $k ][1] ) ) { $ep[ $k ] = $S[ $k ][1] - $LS[ $k ][1]; } }
		$cv = array(); foreach ( $n3 as $k => $_ ) { if ( ( $S[ $k ][0] ?? 0 ) >= 12 && isset( $S[ $k ][1], $LS[ $k ][1] ) ) { $cv[ $k ] = $S[ $k ][1] - $LS[ $k ][1]; } }
		$say = '';
		if ( $cv ) { asort( $cv ); $b3 = array_key_first( $cv ); $say .= ( $cv[ $b3 ] < 0 ? 'Their biggest third-down edge is <b>' . $n3[ $b3 ] . '</b>: offenses convert ' . self::pct( $S[ $b3 ][1] ) . ' against a league ' . self::pct( $LS[ $b3 ][1] ) . '. ' : 'Third downs go the offense\'s way: even ' . $n3[ $b3 ] . ' converts at ' . self::pct( $S[ $b3 ][1] ) . ' (league ' . self::pct( $LS[ $b3 ][1] ) . '). ' ); }
		if ( $ep ) { asort( $ep ); $be = array_key_first( $ep ); $we = array_key_last( $ep ); $say .= 'They are stingiest in <b>' . $lab[ $be ] . '</b> (' . self::sgn( $S[ $be ][1] ) . ' a play, league ' . self::sgn( $LS[ $be ][1] ) . ') and weakest in ' . $lab[ $we ] . ' (' . self::sgn( $S[ $we ][1] ) . ', league ' . self::sgn( $LS[ $we ][1] ) . ').'; }
		return '<section class="dbd-panel dbd-pane" data-pane="' . $i . '" hidden><div class="dbd-ph"><span class="dbd-lab"><b>Situations</b><span class="sub">by down, third-down distance, field zone, clock and score</span></span></div>'
			. '<p class="dbd-ep-say">' . $say . '</p><div class="dbd-tw"><table class="dbd-stat dbd-dsit"><thead><tr><th>Situation</th><th>Plays</th><th>Allowed</th><th>League</th><th>Difference</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
			. '<p class="dbd-note">EPA a play allowed, or for third downs the share converted (a first down or a touchdown). Green is better than the league for the defense. Score states are from the defense\'s side.</p></section>';
	}

	private static function style( $D, $L ) {
		$st = (array) ( $D['style'] ?? array() ); $lg = (array) ( $L['lg']['style'] ?? array() ); $SV = (array) ( $L['style'] ?? array() );
		$chip = function ( $lab, $k, $sub, $hib, $kind, $word ) use ( $st, $lg, $SV ) {
			$v = $st[ $k ] ?? null; if ( $v === null ) { return '<div class="c"><small>' . esc_html( $lab ) . '</small><b>–</b><span>' . esc_html( $sub ) . '</span><em>&nbsp;</em></div>'; }
			$r = self::rank_of( $SV[ $k ] ?? array(), $v, $hib );
			return '<div class="c"><small>' . esc_html( $lab ) . '</small><b>' . esc_html( self::fmt( $kind, $v ) ) . '</b><span>' . esc_html( $sub ) . '</span><em>' . esc_html( self::ord( $r ) . '-' . $word . ' of 32 · league ' . self::fmt( $kind, $lg[ $k ] ?? null ) ) . '</em></div>';
		};
		$card = function ( $title, $sub, $say, $chips ) { return '<section class="dbd-panel dbd-style"><div class="dbd-ph"><span class="dbd-lab"><b>' . $title . '</b>' . ( $sub ? ' · ' . $sub : '' ) . '</span></div><p class="dbd-wp-say">' . $say . '</p><div class="dbd-wpchips">' . $chips . '</div></section>'; };
		$h = '';
		if ( isset( $st['blitz'] ) && $st['blitz'] !== null ) {
			$br = self::rank_of( $SV['blitz'] ?? array(), $st['blitz'], true );
			$say = ( $br === 1 ? 'The most aggressive defense in football. ' : ( $br >= 30 ? 'One of the least aggressive defenses in football. ' : '' ) ) . 'They send extra rushers on <b>' . self::pct( $st['blitz'] ) . '</b> of dropbacks and average <b>' . self::f1( $st['rush'] ) . '</b> rushers; ' . self::pct( $st['four'] ) . ' of dropbacks see four or fewer. '
				. ( $st['e_bz'] !== null && $st['e_nb'] !== null ? 'They allow <b>' . self::sgn( $st['e_bz'] ) . '</b> EPA when they blitz and <b>' . self::sgn( $st['e_nb'] ) . '</b> when they do not.' : '' );
			$h .= $card( 'Pressure and the blitz', 'FTN charting and PFR', $say, $chip( 'Blitz rate', 'blitz', 'of dropbacks', true, 'pct', 'most' ) . $chip( 'Pass rushers', 'rush', 'a dropback', true, 'f1', 'most' ) . $chip( 'EPA when blitzing', 'e_bz', 'per dropback', false, 'sgn', 'best' ) . $chip( 'EPA without', 'e_nb', 'per dropback', false, 'sgn', 'best' ) );
		}
		if ( isset( $st['box'] ) && $st['box'] !== null ) {
			$say = 'They put <b>' . self::f1( $st['box'] ) . '</b> defenders in the box on runs, eight or more on <b>' . self::pct( $st['heavy'] ) . '</b>. Into a light box (six or fewer) runs gain <b>' . self::f1( $st['ypc_l'] ) . '</b> a carry; into eight or more, ' . self::f1( $st['ypc_h'] ) . '.';
			$h .= $card( 'The box and the run', 'defenders near the line on runs', $say, $chip( 'Box count', 'box', 'defenders on runs', true, 'f1', 'most' ) . $chip( 'Eight or more', 'heavy', 'of runs', true, 'pct', 'most' ) . $chip( 'YPC, light box', 'ypc_l', '6 or fewer', false, 'f1', 'best' ) . $chip( 'YPC, heavy box', 'ypc_h', '8 or more', false, 'f1', 'best' ) );
		}
		$say = $st['proe'] !== null ? 'Offenses ' . ( $st['proe'] < 0 ? 'lean on the run' : 'throw more than usual' ) . ' against them: <b>' . abs( round( $st['proe'] * 100 ) ) . ' points ' . ( $st['proe'] < 0 ? 'under' : 'over' ) . '</b> their expected pass rate on early downs in a neutral game. ' : '';
		$say .= $st['e_pa'] !== null ? 'Play action gains ' . self::sgn( $st['e_pa'] ) . ' EPA against them on ' . self::pct( $st['pa'] ) . ' of throws; screens ' . self::sgn( $st['e_scr'] ) . '.' : '';
		$h .= $card( 'What offenses try against them', 'the plan they draw', $say, $chip( 'Pass rate vs expected', 'proe', 'early downs, neutral game', false, 'pct', 'lowest' ) . $chip( 'CPOE allowed', 'cpoe', 'completion over expected, points', false, 'sgn1', 'best' ) . $chip( 'Play action faced', 'pa', self::sgn( $st['e_pa'] ?? null ) . ' EPA allowed', true, 'pct', 'most' ) . $chip( 'Motion faced', 'mot', self::sgn( $st['e_mot'] ?? null ) . ' EPA allowed', true, 'pct', 'most' ) );
		$pr = self::rank_of( $SV['pen'] ?? array(), $D['pen'], false ); $mr = (int) ( $D['rank']['mtr'] ?? 0 );
		$say = (int) $D['pen'] . ' defensive penalties for ' . (int) $D['pen_yds'] . ' yards, ' . (int) $D['pen_fd'] . ' of them first downs, the ' . self::ord( $pr ) . '-fewest.' . ( $D['mtr'] !== null ? ' They miss <b>' . self::pct( $D['mtr'] ) . '</b> of tackle attempts by PFR\'s count, ' . self::ord( $mr ) . '-best.' : '' );
		$h .= $card( 'Discipline and tackling', '', $say, '<div class="c"><small>Penalties</small><b>' . (int) $D['pen'] . '</b><span>' . (int) $D['pen_yds'] . ' yards</span><em>' . self::ord( $pr ) . '-fewest of 32</em></div><div class="c"><small>First downs by penalty</small><b>' . (int) $D['pen_fd'] . '</b><span>handed to offenses</span><em>&nbsp;</em></div>'
			. '<div class="c"><small>Missed tackles</small><b>' . ( $D['mtk'] !== null ? (int) $D['mtk'] : '–' ) . '</b><span>' . self::pct( $D['mtr'] ) . ' of attempts</span><em>' . ( $mr ? self::ord( $mr ) . '-best of 32' : '&nbsp;' ) . '</em></div><div class="c"><small>Takeaways</small><b>' . (int) $D['take'] . '</b><span>' . (int) $D['int'] . ' INT · ' . (int) $D['fum'] . ' fumbles</span><em>' . self::ord( $D['rank']['take_pg'] ?? 0 ) . ' per game</em></div>' );
		return '<div class="dbd-stylegrid">' . $h . '</div>';
	}

	private static function defenders( $a, $D ) {
		$DF = (array) ( $D['defenders'] ?? array() ); if ( ! $DF ) { return ''; } $t = DBD_Data::team( $a );
		$edge = function ( $r ) { return $r['grp'] === 'DL' || in_array( $r['pos'], array( 'OLB', 'DE', 'EDGE' ), true ) || ( $r['grp'] === 'LB' && $r['prs'] > $r['tgt'] ); };  /* a linebacker who rushes more than he covers plays on the front */
		$front = array_slice( array_values( array_filter( $DF, $edge ) ), 0, 10 ); $lbs = array_slice( array_values( array_filter( $DF, function ( $r ) use ( $edge ) { return $r['grp'] === 'LB' && ! $edge( $r ); } ) ), 0, 7 );
		$sec = array_slice( array_values( array_filter( $DF, function ( $r ) { return $r['grp'] === 'DB'; } ) ), 0, 9 );
		$z = function ( $v ) { return $v ? ( is_float( $v ) ? rtrim( rtrim( number_format( $v, 1 ), '0' ), '.' ) : (string) $v ) : ''; };
		$jt = function ( $num ) use ( $t ) { return '<span class="dbd-jt" style="background:' . esc_attr( DBD_Marks::accent( $t ) ) . '">' . esc_html( (string) ( $num ?? '' ) ) . '</span>'; };
		$tbl = function ( $rows, $cols ) use ( $jt ) {
			$th = ''; foreach ( $cols as $c ) { $th .= '<th>' . $c[0] . '</th>'; }
			$tb = ''; foreach ( $rows as $r ) { $tb .= '<tr><td class="n">' . $jt( $r['num'] ) . '<b>' . esc_html( $r['name'] ) . '</b><small>' . esc_html( $r['pos'] ) . '</small></td>'; foreach ( array_slice( $cols, 1 ) as $c ) { $tb .= '<td>' . $c[1]( $r ) . '</td>'; } $tb .= '</tr>'; }
			return '<div class="dbd-tw"><table class="dbd-stat dbd-ddef"><thead><tr>' . $th . '</tr></thead><tbody>' . $tb . '</tbody></table></div>';
		};
		$snap = function ( $r ) { return (int) $r['snap'] . '%'; }; $rate = function ( $r ) { return $r['rate'] !== null ? number_format( $r['rate'], 1 ) : ''; };
		$panes = array();
		if ( $front ) { $tr = $front; usort( $tr, function ( $x, $y ) { return $y['prs'] <=> $x['prs']; } ); $tr = $tr[0];
			$panes[] = array( 'The front', '<b>' . esc_html( $tr['name'] ) . '</b> leads the pass rush with <b>' . (int) $tr['prs'] . '</b> pressures and ' . $z( (float) $tr['sk'] ) . ' sacks on ' . (int) $tr['snap'] . '% of the snaps.',
				$tbl( $front, array( array( 'Player', null ), array( 'Snaps', $snap ), array( 'Pressures', function ( $r ) use ( $z ) { return $z( $r['prs'] ); } ), array( 'Sacks', function ( $r ) use ( $z ) { return $z( (float) $r['sk'] ); } ), array( 'QB hits', function ( $r ) use ( $z ) { return $z( $r['qbh'] ); } ), array( 'TFL', function ( $r ) use ( $z ) { return $z( $r['tfl'] ); } ), array( 'Tackles', function ( $r ) use ( $z ) { return $z( $r['tkl'] ); } ), array( 'FF', function ( $r ) use ( $z ) { return $z( $r['ff'] ); } ) ) ) ); }
		if ( $lbs ) { $panes[] = array( 'Linebackers', '<b>' . esc_html( $lbs[0]['name'] ) . '</b> plays ' . (int) $lbs[0]['snap'] . '% of the snaps with ' . (int) $lbs[0]['tkl'] . ' tackles' . ( $lbs[0]['sk'] ? ' and ' . $z( (float) $lbs[0]['sk'] ) . ' sacks' : '' ) . '.',
				$tbl( $lbs, array( array( 'Player', null ), array( 'Snaps', $snap ), array( 'Tackles', function ( $r ) use ( $z ) { return $z( $r['tkl'] ); } ), array( 'TFL', function ( $r ) use ( $z ) { return $z( $r['tfl'] ); } ), array( 'Sacks', function ( $r ) use ( $z ) { return $z( (float) $r['sk'] ); } ), array( 'Pressures', function ( $r ) use ( $z ) { return $z( $r['prs'] ); } ), array( 'Targets', function ( $r ) use ( $z ) { return $z( $r['tgt'] ); } ), array( 'Rating allowed', $rate ), array( 'Missed', function ( $r ) use ( $z ) { return $z( $r['mt'] ); } ) ) ) ); }
		if ( $sec ) { $cov = array_values( array_filter( $sec, function ( $r ) { return $r['tgt'] >= 15 && $r['rate'] !== null; } ) ); usort( $cov, function ( $x, $y ) { return $x['rate'] <=> $y['rate']; } );
			$panes[] = array( 'The secondary', $cov ? '<b>' . esc_html( $cov[0]['name'] ) . '</b> is the hardest to complete a pass against: a <b>' . number_format( $cov[0]['rate'], 1 ) . '</b> passer rating on ' . (int) $cov[0]['tgt'] . ' targets.' : 'Coverage lines fill in as PFR charts the targets.',
				$tbl( $sec, array( array( 'Player', null ), array( 'Snaps', $snap ), array( 'Targets', function ( $r ) use ( $z ) { return $z( $r['tgt'] ); } ), array( 'Catches allowed', function ( $r ) { return $r['tgt'] ? (int) $r['cmp'] . ' of ' . (int) $r['tgt'] : ''; } ), array( 'Yards', function ( $r ) use ( $z ) { return $z( $r['yds'] ); } ), array( 'TD', function ( $r ) use ( $z ) { return $z( $r['tda'] ); } ), array( 'Rating allowed', $rate ), array( 'INT', function ( $r ) use ( $z ) { return $z( $r['int'] ); } ), array( 'PD', function ( $r ) use ( $z ) { return $z( $r['pd'] ); } ) ) ) ); }
		$out = array(); foreach ( $panes as $i => $p ) { $out[] = array( $p[0], '<section class="dbd-panel dbd-pane" data-pane="' . $i . '"' . ( $i ? ' hidden' : '' ) . '><div class="dbd-ph"><span class="dbd-lab"><b>' . $p[0] . '</b><span class="sub">season lines, by snap share</span></span></div><p class="dbd-ep-say">' . $p[1] . '</p>' . $p[2] . '</section>' ); }
		return str_replace( '</section></section>', '<p class="dbd-note">Tackles, sacks, QB hits, TFL, interceptions, passes defended and forced fumbles are nflverse\'s weekly stats; pressures, targets, catches and yards allowed, passer rating allowed and missed tackles are PFR\'s charting. Snap share is the season average of defensive snaps.</p></section></section>', self::tabbed( $out ) );
	}

	/** Leaders (SCOPE §39): a defense board, the 32 teams by one Scorecard stat. Returns [rows of [team, value]] best first. */
	public static function board( $k ) {
		$L = DBD_Data::defense_league(); $v = (array) ( $L['vals'][ $k ] ?? array() ); $hib = ! empty( $L['hib'][ $k ] );
		$rows = array(); foreach ( $v as $t => $x ) { $rows[] = array( $t, $x ); }
		usort( $rows, function ( $a, $b ) use ( $hib ) { return $hib ? $b[1] <=> $a[1] : $a[1] <=> $b[1]; } );
		return $rows;
	}
	public static function link( $a, $label ) { return '<a class="dbd-dlink" href="' . esc_url( DBD_Router::url( array( 'team' => $a, 'side' => 'def' ) ) ) . '">' . $label . '</a>'; }
}
