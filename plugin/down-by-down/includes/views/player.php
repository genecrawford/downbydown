<?php
/**
 * Player page. PHP renders what the player file holds (hero, tiles, game log, intel basics, teammates) so
 * crawlers see a real page; player.js adds the field map, depth bands, situations and best plays from the
 * team's play file.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $row = $r['player']; $a = $row['team']; $t = DBD_Data::team( $a );
$p = DBD_Data::player_record( $a, $row['id'] );
if ( ! $p ) { include DBD_PATH . 'includes/views/notfound.php'; return; }
$season = DBD_Data::season(); $through = DBD_Data::through_week();
$W = $p['weeks'] ?? array();
$played = array_unique( array_merge( array_map( function ( $w ) { return (int) $w['week']; }, $W ), array_map( 'intval', array_keys( $p['snaps'] ?? array() ) ) ) ); $ng = count( $played ); $gp = max( 1, $ng );
$traded = ! empty( $p['snapteam'] ) || (bool) array_filter( $W, function ( $w ) use ( $a ) { return ! empty( $w['team'] ) && $w['team'] !== $a; } );
/* game-log rows: every played week, a zero line where he took snaps but never touched the ball */
sort( $played );
$zero = array_fill_keys( array( 'targets', 'receptions', 'receiving_yards', 'receiving_tds', 'receiving_air_yards', 'receiving_yards_after_catch', 'receiving_epa', 'receiving_first_downs', 'target_share', 'air_yards_share', 'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa', 'rushing_first_downs', 'attempts', 'completions', 'passing_yards', 'passing_tds', 'passing_interceptions', 'passing_epa', 'passing_cpoe', 'sacks_suffered', 'passing_air_yards', 'receiving_20', 'rushing_10' ), 0 );
$byWk = array(); foreach ( $W as $w ) { $byWk[ (int) $w['week'] ] = $w; }
$L = array_map( function ( $wk ) use ( $byWk, $zero, $a, $p ) { return $byWk[ $wk ] ?? array_merge( $zero, array( 'week' => $wk, 'team' => $p['snapteam'][ (string) $wk ] ?? $a ) ); }, $played );
$sum = function ( $k ) use ( $W ) { $s = 0; foreach ( $W as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s; };
$avg = function ( $k ) use ( $W, $gp ) { $s = 0; foreach ( $W as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s / $gp; };
$pct = function ( $v ) { return is_finite( $v ) && ! is_nan( $v ) ? round( $v * 100 ) . '%' : '-'; };
$f1  = function ( $v ) { return is_finite( $v ) && ! is_nan( $v ) ? number_format( $v, 1 ) : '-'; };
$sgn = function ( $v, $d = 2 ) { return ( $v > 0 ? '+' : '' ) . number_format( $v, $d ); };
$div = function ( $n, $d ) { return $d ? $n / $d : NAN; };
$grp = $p['pos'] === 'QB' ? 'qb' : ( in_array( $p['pos'], array( 'RB', 'FB' ), true ) ? 'rb' : 'rec' );
$snapAvg = ! empty( $p['snaps'] ) ? array_sum( $p['snaps'] ) / count( $p['snaps'] ) : 0;
$parts = explode( ' ', $p['name'], 2 ); $first = $parts[0]; $last = $parts[1] ?? '';
$ord = function ( $n ) { $s = array( 'th', 'st', 'nd', 'rd' ); $v = $n % 100; return $n . ( $s[ ( $v - 20 ) % 10 ] ?? $s[ $v ] ?? $s[0] ); };
$depth = ! empty( $p['depth'] ) ? $p['depth']['slot'] . $p['depth']['rank'] : '';
$st = $p['inj']['status'] ?? ''; $icls = preg_match( '/out|did not/i', $st ) ? 'bad' : ( preg_match( '/limited|questionable|doubtful/i', $st ) ? '' : 'good' );
$turl = DBD_Router::url( array( 'team' => $a ) ); $purl = DBD_Router::url( array( 'view' => 'players' ) ); $tpurl = DBD_Router::url( array( 'view' => 'players', 'team' => $a ) );

echo DBD_Router::topbar( array( 'players' => $purl, $t['nick'] => $tpurl, $p['name'] => null ) );
echo '<main class="dbd-content dbd-player" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">';

/* ---- hero */
echo '<div class="dbd-ttitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · {$p['pos']} · #{$p['num']} · " . $ord( (int) $p['exp'] + 1 ) . ' season' ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::jersey( $a, $p['num'], 58 ) . '<span>' . esc_html( $first ) . ' <em>' . esc_html( $last ) . '</em></span></h1>';
$bio = array_filter( array( $p['age'] ? 'Age ' . (int) $p['age'] : '', $p['ht'] ?: '', $p['wt'] ? (int) $p['wt'] . ' lb' : '', $p['col'] ?: '', $p['draft'] ?: '' ) );
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">' . esc_html( implode( ' · ', $bio ) ) . '.' . ( $depth ? ' Listed <b>' . esc_html( $depth ) . '</b> on the ' . esc_html( $p['depth']['grp'] ) . ' depth chart.' : '' ) . ' Snap share <b>' . round( $snapAvg ) . '%</b> across ' . $ng . ' ' . ( $ng === 1 ? 'game' : 'games' ) . '.</p>';
echo '<div class="dbd-pills">' . ( $p['inj'] ? '<span class="dbd-pill ' . $icls . '">Week ' . (int) $p['inj']['week'] . ' report · ' . esc_html( $p['inj']['what'] ) . ' · ' . esc_html( str_ireplace( ' in practice', '', $st ) ) . '</span>' : '<span class="dbd-pill good">No injury listing</span>' ) . ( $depth ? '<span class="dbd-pill">' . esc_html( $depth ) . '</span>' : '' ) . '<a class="dbd-pill" href="' . esc_url( $tpurl ) . '">' . esc_html( $t['nick'] ) . ' players</a></div>';
echo '</div>' . DBD_Marks::hero_photo( $a, $p['photo'] ?? null ) . '<div class="dbd-hdr"><a class="dbd-pn-link" href="' . esc_url( $turl ) . '">' . DBD_Marks::pennant( $a, 40 ) . esc_html( $t['nick'] ) . ' offense</a><span class="dbd-badge" id="dbd-psnaps">' . ( $p['snaps'] ? round( $snapAvg ) . '% of snaps' : 'no snap data' ) . '</span></div></div>';

/* ---- tiles by position group */
$tg = $sum( 'targets' ); $rc = $sum( 'receptions' ); $ry = $sum( 'receiving_yards' ); $rtd = $sum( 'receiving_tds' ); $air = $sum( 'receiving_air_yards' ); $yac = $sum( 'receiving_yards_after_catch' ); $repa = $sum( 'receiving_epa' ); $rfd = $sum( 'receiving_first_downs' );
$ca = $sum( 'carries' ); $ruy = $sum( 'rushing_yards' ); $rutd = $sum( 'rushing_tds' ); $ruepa = $sum( 'rushing_epa' );
$att = $sum( 'attempts' ); $cmp = $sum( 'completions' ); $py = $sum( 'passing_yards' ); $ptd = $sum( 'passing_tds' ); $pint = $sum( 'passing_interceptions' ); $pepa = $sum( 'passing_epa' ); $sk = $sum( 'sacks_suffered' );
$tshare = $avg( 'target_share' ); $ashare = $avg( 'air_yards_share' );
$qbw = array_filter( $W, function ( $w ) { return ( $w['attempts'] ?? 0 ) > 0; } ); $cpoe = $qbw ? array_sum( array_map( function ( $w ) { return (float) ( $w['passing_cpoe'] ?? 0 ); }, $qbw ) ) / count( $qbw ) : 0;
if ( $grp === 'rec' ) {
	$tiles = array( array( 'Targets', (int) $tg, $f1( $tg / $gp ) . ' per game' ), array( 'Receptions', (int) $rc, $pct( $div( $rc, $tg ) ) . ' catch rate' ), array( 'Yards', (int) $ry, round( $ry / $gp ) . ' per game' ), array( 'Touchdowns', (int) $rtd, (int) $rfd . ' first downs' ),
		array( 'Target share', $pct( $tshare ), $pct( $ashare ) . ' of air yards' ), array( 'aDOT', $f1( $div( $air, $tg ) ), 'air yards per target' ), array( 'EPA / target', $tg ? $sgn( $repa / $tg ) : '-', $sgn( $repa, 1 ) . ' total' ), array( 'YAC / catch', $f1( $div( $yac, $rc ) ), (int) $yac . ' after the catch' ) );
} elseif ( $grp === 'rb' ) {
	$tiles = array( array( 'Carries', (int) $ca, $f1( $ca / $gp ) . ' per game' ), array( 'Rush yards', (int) $ruy, $f1( $div( $ruy, $ca ) ) . ' per carry' ), array( 'Rush TD', (int) $rutd, (int) $sum( 'rushing_first_downs' ) . ' first downs' ), array( 'Targets', (int) $tg, (int) $rc . ' catches · ' . (int) $ry . ' yds' ),
		array( 'Touches / game', $f1( ( $ca + $tg ) / $gp ), (int) ( $ca + $tg ) . ' total' ), array( 'EPA / touch', ( $ca + $tg ) ? $sgn( ( $ruepa + $repa ) / ( $ca + $tg ) ) : '-', $sgn( $ruepa + $repa, 1 ) . ' total' ), array( 'Explosive', (int) ( $sum( 'rushing_10' ) + $sum( 'receiving_20' ) ), '10+ run · 20+ catch' ), array( 'Snap share', round( $snapAvg ) . '%', 'offensive snaps' ) );
} else {
	$tiles = array( array( 'Attempts', (int) $att, $f1( $att / $gp ) . ' per game' ), array( 'Completion', $pct( $div( $cmp, $att ) ), $sgn( $cpoe, 1 ) . ' over expected' ), array( 'Pass yards', (int) $py, $f1( $div( $py, $att ) ) . ' per attempt' ), array( 'TD / INT', (int) $ptd . ' / ' . (int) $pint, (int) $sum( 'passing_first_downs' ) . ' first downs' ),
		array( 'EPA / dropback', ( $att + $sk ) ? $sgn( $pepa / ( $att + $sk ) ) : '-', $sgn( $pepa, 1 ) . ' total' ), array( 'Sacks', (int) $sk, $pct( $div( $sk, $att + $sk ) ) . ' of dropbacks' ), array( 'Rush', (int) $ruy . ' yds', (int) $ca . ' carries · ' . (int) $rutd . ' TD' ), array( 'Snap share', round( $snapAvg ) . '%', 'offensive snaps' ) );
}
echo '<div class="dbd-tiles">';
foreach ( $tiles as $x ) { echo '<div class="dbd-tile"><div class="l">' . esc_html( $x[0] ) . '</div><div class="v">' . esc_html( $x[1] ) . '</div><div class="s">' . esc_html( $x[2] ) . '</div></div>'; }
echo '</div>';

/* ---- where he ranks: percentile bars against the league pool at his position (SCOPE §15) */
$gr = $p['grades'] ?? null; $ggrp = $gr['grp'] ?? $grp; $gi = $gr ? DBD_Grades::group_info( $ggrp ) : null; $N = (int) ( $gi['n'] ?? 0 ); $graded = $gr && ! empty( $gr['q'] ) && $N > 0;
if ( $gr && $gi ) {
	echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where he ranks</b> · among ' . $N . ' ' . esc_html( DBD_Grades::pool_label( $ggrp ) ) . ' with ' . esc_html( $gi['min'] ) . '+</span><span class="dbd-badge">League percentiles</span></div><div class="dbd-pct">';
	foreach ( DBD_Grades::defs( $ggrp ) as $d ) {
		list( $k, $lab, $sub, $kind ) = $d; $v = $gr['m'][ $k ] ?? null; $pc = $graded ? DBD_Grades::pctile( $ggrp, $k, $v ) : null;
		echo '<div class="row"><div class="l">' . esc_html( $lab ) . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</div><div class="bar">' . ( $pc === null ? '' : '<i style="--p:' . (int) $pc . '"></i>' ) . '</div><div class="v">' . esc_html( DBD_Grades::fmt( $kind, $v ) ) . '</div><div class="g' . ( $pc !== null && $pc >= 75 ? ' hi' : ( $pc !== null && $pc <= 25 ? ' lo' : '' ) ) . '">' . ( $pc === null ? '-' : esc_html( DBD_Grades::ordinal( $pc ) ) ) . '</div></div>';
	}
	echo '</div><p class="dbd-note">' . ( $graded ? 'Each bar is the league at his position; the marker is him. Right is better, the dashed line is the median. Grades move every week as the pool changes.' : 'Graded once he reaches ' . esc_html( $gi['min'] ) . '. ' . $N . ' ' . esc_html( DBD_Grades::pool_label( $ggrp ) ) . ' qualify so far.' ) . '</p></section>';
}

echo '<div class="dbd-pwork"><div class="dbd-pmain">';

/* ---- field map + depth bands (JS) */
echo '<section class="dbd-panel" id="dbd-pmap-panel"><div class="dbd-ph"><span class="dbd-lab"><b id="dbd-maplab">' . ( $grp === 'qb' ? 'Where he throws' : ( $grp === 'rb' ? 'Where he runs' : 'Where the ball finds him' ) ) . '</b> · from the play file</span><span class="dbd-badge" id="dbd-mapn">loading</span></div>';
echo '<div class="dbd-sprays"><div><h4 id="dbd-map1h">On the field</h4><div id="dbd-pmap"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div></div><div><h4>By depth of target</h4><div class="dbd-bands" id="dbd-bands"></div><p class="dbd-note" id="dbd-bandnote" style="padding:8px 0 0"></p></div></div>';
echo '<div class="dbd-legend" id="dbd-pleg"></div><p class="dbd-note" id="dbd-mapnote"></p></section>';

/* ---- game log (PHP): Default, Advanced and Traditional tables; player.js switches which one shows */
$res = function ( $wk, $tm = null ) use ( $a ) { $tm = $tm ?: $a; $g = DBD_Data::team_game( $tm, $wk ); if ( ! $g ) { return ''; } $home = $g['home'] === $tm; $opp = $home ? $g['away'] : $g['home']; $pf = $home ? $g['home_score'] : $g['away_score']; $pa = $home ? $g['away_score'] : $g['home_score'];
	if ( $pf === null ) { return ( $home ? 'vs ' : 'at ' ) . $opp; }
	return ( $tm !== $a ? esc_html( $tm ) . ' ' : '' ) . ( $home ? 'vs ' : 'at ' ) . esc_html( $opp ) . ' <b class="' . ( $pf > $pa ? '' : 'l' ) . '">' . ( $pf > $pa ? 'W' : ( $pf < $pa ? 'L' : 'T' ) ) . '</b> ' . (int) $pf . '-' . (int) $pa; };
$snap = function ( $wk ) use ( $p ) { return isset( $p['snaps'][ (string) (int) $wk ] ) ? (int) $p['snaps'][ (string) (int) $wk ] . '%' : '-'; };
$pf = function ( $wk, $k ) use ( $p ) { return (int) ( $p['pfr'][ (string) (int) $wk ][ $k ] ?? 0 ); };
$drops = 0; $bt = 0; $rats = array();
foreach ( $p['pfr'] ?? array() as $x ) { $drops += (int) ( $x['drop'] ?? 0 ); $bt += (int) ( $x['bt'] ?? 0 ) + (int) ( $x['rbt'] ?? 0 ); if ( isset( $x['rat'] ) && $x['rat'] !== null ) { $rats[] = (float) $x['rat']; } }
$cell = function ( $v, $cls = '' ) { return '<td' . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . $v . '</td>'; };
$e = function ( $v, $d = 1 ) { return '<td class="' . ( $v >= 0 ? 'pos' : 'neg' ) . '">' . ( $v > 0 ? '+' : '' ) . number_format( (float) $v, $d ) . '</td>'; };
$f = function ( $n, $d ) use ( $f1 ) { return $d ? $f1( $n / $d ) : '-'; };
$pc2 = function ( $n, $d ) { return $d ? round( $n / $d * 100 ) . '%' : '-'; };
$pair = $sum( 'passing_air_yards' );
$views = array();
if ( $grp === 'rec' ) {
	$views['Default'] = array( 'Snap|Tgt|Rec|Yds|TD|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $cell( (int) $w['receiving_tds'] ?: '' ) . $e( (float) $w['receiving_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td><td>' . (int) $rtd . '</td>' . $e( $repa ) );
	$views['Advanced'] = array( 'Tgt share|Air share|aDOT|YAC|EPA/tgt|1D|Drops', function ( $w, $wk ) use ( $cell, $e, $pct, $f, $pf ) { return $cell( $pct( (float) $w['target_share'] ) ) . $cell( $pct( (float) $w['air_yards_share'] ) ) . $cell( $f( $w['receiving_air_yards'], $w['targets'] ) ) . $cell( (int) $w['receiving_yards_after_catch'] ) . ( $w['targets'] ? $e( $w['receiving_epa'] / $w['targets'], 2 ) : $cell( '-' ) ) . $cell( (int) $w['receiving_first_downs'] ) . $cell( $pf( $wk, 'drop' ) ?: '' ); },
		'<td>' . $pct( $tshare ) . '</td><td>' . $pct( $ashare ) . '</td><td>' . $f1( $div( $air, $tg ) ) . '</td><td>' . (int) $yac . '</td>' . ( $tg ? $e( $repa / $tg, 2 ) : '<td>-</td>' ) . '<td>' . (int) $rfd . '</td><td>' . ( $drops ?: '' ) . '</td>' );
	$views['Traditional'] = array( 'Rec|Yds|Y/R|TD|20+', function ( $w, $wk ) use ( $cell, $f ) { return $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $cell( $f( $w['receiving_yards'], $w['receptions'] ) ) . $cell( (int) $w['receiving_tds'] ?: '' ) . $cell( (int) ( $w['receiving_20'] ?? 0 ) ?: '' ); },
		'<td>' . (int) $rc . '</td><td>' . (int) $ry . '</td><td>' . $f1( $div( $ry, $rc ) ) . '</td><td>' . (int) $rtd . '</td><td>' . (int) $sum( 'receiving_20' ) . '</td>' );
} elseif ( $grp === 'rb' ) {
	$views['Default'] = array( 'Snap|Car|Yds|TD|Tgt|Rec|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['carries'] ) . $cell( (int) $w['rushing_yards'] ) . $cell( ( (int) $w['rushing_tds'] + (int) $w['receiving_tds'] ) ?: '' ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $e( (float) $w['rushing_epa'] + (float) $w['receiving_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $ca . '</td><td>' . (int) $ruy . '</td><td>' . (int) ( $rutd + $rtd ) . '</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td>' . $e( $ruepa + $repa ) );
	$views['Advanced'] = array( 'YPC|EPA/car|EPA/tgt|10+|Brk tkl|Snap', function ( $w, $wk ) use ( $cell, $e, $f, $pf, $snap ) { return $cell( $f( $w['rushing_yards'], $w['carries'] ) ) . ( $w['carries'] ? $e( $w['rushing_epa'] / $w['carries'], 2 ) : $cell( '-' ) ) . ( $w['targets'] ? $e( $w['receiving_epa'] / $w['targets'], 2 ) : $cell( '-' ) ) . $cell( (int) ( $w['rushing_10'] ?? 0 ) ?: '' ) . $cell( ( $pf( $wk, 'bt' ) + $pf( $wk, 'rbt' ) ) ?: '' ) . $cell( $snap( $wk ) ); },
		'<td>' . $f1( $div( $ruy, $ca ) ) . '</td>' . ( $ca ? $e( $ruepa / $ca, 2 ) : '<td>-</td>' ) . ( $tg ? $e( $repa / $tg, 2 ) : '<td>-</td>' ) . '<td>' . (int) $sum( 'rushing_10' ) . '</td><td>' . ( $bt ?: '' ) . '</td><td>' . round( $snapAvg ) . '%</td>' );
	$views['Traditional'] = array( 'Car|Yds|YPC|TD|Rec|Rec yds', function ( $w, $wk ) use ( $cell, $f ) { return $cell( (int) $w['carries'] ) . $cell( (int) $w['rushing_yards'] ) . $cell( $f( $w['rushing_yards'], $w['carries'] ) ) . $cell( (int) $w['rushing_tds'] ?: '' ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ); },
		'<td>' . (int) $ca . '</td><td>' . (int) $ruy . '</td><td>' . $f1( $div( $ruy, $ca ) ) . '</td><td>' . (int) $rutd . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td>' );
} else {
	$views['Default'] = array( 'Snap|Cmp/Att|Yds|TD|INT|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['completions'] . '/' . (int) $w['attempts'] ) . $cell( (int) $w['passing_yards'] ) . $cell( (int) $w['passing_tds'] ?: '' ) . $cell( (int) $w['passing_interceptions'] ?: '' ) . $e( (float) $w['passing_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $cmp . '/' . (int) $att . '</td><td>' . (int) $py . '</td><td>' . (int) $ptd . '</td><td>' . (int) $pint . '</td>' . $e( $pepa ) );
	$views['Advanced'] = array( 'EPA/DB|CPOE|aDOT|Sk|Sk %|Rush EPA', function ( $w, $wk ) use ( $cell, $e, $f, $pc2, $sgn ) { $db = $w['attempts'] + $w['sacks_suffered']; return ( $db ? $e( $w['passing_epa'] / $db, 2 ) : $cell( '-' ) ) . $cell( $w['attempts'] ? $sgn( (float) $w['passing_cpoe'], 1 ) : '-' ) . $cell( $f( $w['passing_air_yards'] ?? 0, $w['attempts'] ) ) . $cell( (int) $w['sacks_suffered'] ?: '' ) . $cell( $pc2( $w['sacks_suffered'], $db ) ) . $e( (float) $w['rushing_epa'] ); },
		( ( $att + $sk ) ? $e( $pepa / ( $att + $sk ), 2 ) : '<td>-</td>' ) . '<td>' . $sgn( $cpoe, 1 ) . '</td><td>' . $f1( $div( $pair, $att ) ) . '</td><td>' . (int) $sk . '</td><td>' . $pct( $div( $sk, $att + $sk ) ) . '</td>' . $e( $ruepa ) );
	$views['Traditional'] = array( 'Cmp|Att|Pct|Yds|Y/A|TD|INT|Rate', function ( $w, $wk ) use ( $cell, $f, $pc2 ) { $r = DBD_Grades::passer_rating( (float) $w['completions'], (float) $w['attempts'], (float) $w['passing_yards'], (float) $w['passing_tds'], (float) $w['passing_interceptions'] ); return $cell( (int) $w['completions'] ) . $cell( (int) $w['attempts'] ) . $cell( $pc2( $w['completions'], $w['attempts'] ) ) . $cell( (int) $w['passing_yards'] ) . $cell( $f( $w['passing_yards'], $w['attempts'] ) ) . $cell( (int) $w['passing_tds'] ?: '' ) . $cell( (int) $w['passing_interceptions'] ?: '' ) . $cell( $r === null ? '-' : number_format( $r, 1 ) ); },
		'<td>' . (int) $cmp . '</td><td>' . (int) $att . '</td><td>' . $pct( $div( $cmp, $att ) ) . '</td><td>' . (int) $py . '</td><td>' . $f1( $div( $py, $att ) ) . '</td><td>' . (int) $ptd . '</td><td>' . (int) $pint . '</td><td>' . ( ( $r = DBD_Grades::passer_rating( $cmp, $att, $py, $ptd, $pint ) ) === null ? '-' : number_format( $r, 1 ) ) . '</td>' );
}
echo '<section class="dbd-panel"><div class="dbd-ph dbd-glogh"><span class="dbd-lab"><b>Game log</b> · week by week</span><div class="dbd-seg t-tabs dbd-gview" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>';
$i = 0; foreach ( array_keys( $views ) as $name ) { echo '<button class="t-tab" type="button" role="tab" data-v="' . esc_attr( $name ) . '" aria-selected="' . ( $i++ ? 'false' : 'true' ) . '">' . esc_html( $name ) . '</button>'; }
echo '</div></div>';
foreach ( $views as $name => $vd ) {
	list( $cols, $row, $foot ) = $vd;
	echo '<div class="dbd-tw" data-view="' . esc_attr( $name ) . '"' . ( $name === 'Default' ? '' : ' hidden' ) . '><table class="dbd-glog dbd-sortable"><thead><tr><th>Wk</th><th>Opponent</th>';
	foreach ( explode( '|', $cols ) as $c ) { echo '<th>' . esc_html( $c ) . '</th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $L as $w ) { $wk = (int) $w['week']; echo '<tr>' . $cell( $wk ) . $cell( $res( $wk, $w['team'] ?? null ), 'res' ) . $row( $w, $wk ) . '</tr>'; }
	echo '</tbody><tfoot><tr><td></td><td>' . $ng . ' games</td>' . $foot . '</tr></tfoot></table></div>';
}
echo ( $traded ? '<p class="dbd-note">Weeks marked with another team come from that team\'s stat line. The field map and situations below cover ' . esc_html( $t['nick'] ) . ' snaps only.</p>' : '' ) . '</section>';

/* ---- situations (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>When he gets it</b> · touches by situation</span></div><div class="dbd-wk3 dbd-tend" id="dbd-sit"></div></section>';
echo '</div><div class="dbd-rail">';

/* ---- season stats with league rank */
$ss = $grp === 'rec' ? array( array( 'Targets', (int) $tg ), array( 'Receptions', (int) $rc ), array( 'Yards', (int) $ry ), array( 'Touchdowns', (int) $rtd ) )
	: ( $grp === 'rb' ? array( array( 'Carries', (int) $ca ), array( 'Rush yards', (int) $ruy ), array( 'Rush TD', (int) $rutd ), array( 'Targets', (int) $tg ) )
	: array( array( 'Attempts', (int) $att ), array( 'Completions', (int) $cmp ), array( 'Pass yards', (int) $py ), array( 'TD / INT', (int) $ptd . ' / ' . (int) $pint ) ) );
if ( $gr ) { foreach ( DBD_Grades::defs( $ggrp ) as $d ) { $v = $gr['m'][ $d[0] ] ?? null; $ss[] = array( $d[1], DBD_Grades::fmt( $d[3], $v ), $graded ? DBD_Grades::rank( $ggrp, $d[0], $v ) : null ); } }
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Season stats</b> · with league rank</span><span class="dbd-badge">' . ( $graded ? 'of ' . $N : 'unranked' ) . '</span></div><div class="dbd-tw"><table class="dbd-sstat">';
foreach ( $ss as $r ) { $rk = $r[2] ?? null; echo '<tr' . ( $rk && $rk <= ceil( $N * 0.1 ) ? ' class="top"' : '' ) . '><td>' . esc_html( $r[0] ) . '</td><td>' . esc_html( $r[1] ) . '</td><td class="rk">' . ( $rk ? '<b>' . esc_html( DBD_Grades::ordinal( $rk ) ) . '</b> of ' . $N : '' ) . '</td></tr>'; }
echo '</table></div></section>';

/* ---- intel (PHP basics, JS fills the play-file lines) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Intel</b></span><span class="dbd-badge">Week ' . (int) $through . '</span></div><div class="dbd-pb"><dl class="dbd-kv dbd-intel">';
echo '<dt>Depth chart</dt><dd>' . ( $depth ? esc_html( $depth . ' · ' . $p['depth']['grp'] ) : 'not listed' ) . '</dd>';
echo '<dt>Injury report</dt><dd class="' . ( $p['inj'] ? $icls : 'good' ) . '">' . ( $p['inj'] ? esc_html( $p['inj']['what'] . ', ' . strtolower( $st ) ) : 'clean' ) . '</dd>';
echo '<dt>Red zone touches</dt><dd data-fill="rz">…</dd><dt>Third down</dt><dd data-fill="third">…</dd><dt>Explosive plays</dt><dd data-fill="expl">…</dd>';
if ( $grp !== 'qb' ) {
	echo '<dt>Drops</dt><dd class="' . ( $drops >= 3 ? 'bad' : '' ) . '">' . (int) $drops . ' on ' . (int) $tg . ' targets</dd><dt>Broken tackles</dt><dd>' . (int) $bt . '</dd>';
	if ( $rats ) { echo '<dt>Passer rating when targeted</dt><dd>' . $f1( array_sum( $rats ) / count( $rats ) ) . '</dd>'; }
	echo '<dt>Play action</dt><dd data-fill="pa">…</dd><dt>Contested</dt><dd data-fill="cont">…</dd>';
} else {
	echo '<dt>CPOE</dt><dd class="' . ( $cpoe >= 0 ? 'good' : 'bad' ) . '">' . $sgn( $cpoe, 1 ) . ' pts over expected</dd><dt>Sack rate</dt><dd>' . $pct( $div( $sk, $att + $sk ) ) . '</dd><dt>Play action</dt><dd data-fill="pa">…</dd><dt>Blitzed</dt><dd data-fill="blz">…</dd>';
}
echo '</dl></div></section>';

/* ---- best plays (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Best plays</b> · by EPA</span></div><div class="dbd-pb"><ol class="dbd-best" id="dbd-best"></ol></div></section>';

/* ---- teammates (PHP) */
$mates = array();
foreach ( DBD_Data::team_players( $a ) as $m ) {
	if ( $m['id'] === $p['id'] || empty( $m['weeks'] ) ) { continue; }
	$s = function ( $k ) use ( $m ) { $v = 0; foreach ( $m['weeks'] as $w ) { $v += (float) ( $w[ $k ] ?? 0 ); } return $v; };
	$tc = $s( 'targets' ) + $s( 'carries' ); if ( ! $tc ) { continue; }
	$mates[] = array( $m, $tc, $s( 'receiving_yards' ) + $s( 'rushing_yards' ), $s( 'receiving_tds' ) + $s( 'rushing_tds' ), $s( 'receiving_epa' ) + $s( 'rushing_epa' ) );
}
usort( $mates, function ( $x, $y ) { return $y[1] <=> $x[1]; } ); $mates = array_slice( $mates, 0, 8 );
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Teammates</b> · share of the ball</span></div><div class="dbd-tw"><table class="dbd-mates dbd-sortable"><thead><tr><th>Player</th><th>Touches</th><th>Yds</th><th>TD</th><th>EPA</th></tr></thead><tbody>';
foreach ( $mates as $x ) { list( $m, $tc, $y, $td, $ep ) = $x; $href = DBD_Router::url( array( 'player' => $m['id'] ) );
	echo '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::player( $a, $m['num'], 30, $m['photo'] ?? null ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $m['name'] ) . '</a><small>' . esc_html( $m['pos'] ) . '</small></span></td><td>' . (int) $tc . '</td><td>' . (int) round( $y ) . '</td><td>' . ( (int) $td ?: '' ) . '</td>' . $e( $ep ) . '</tr>'; }
echo '</tbody></table></div></section>';
echo '</div></div></main>';
