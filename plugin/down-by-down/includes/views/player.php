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
$sug = DBD_Grades::suggest( $row, 3 ); $cmp = function ( $ids ) { return DBD_Router::url( array( 'compare' => implode( ',', $ids ) ) ); };
echo '</div>' . DBD_Marks::hero_photo( $a, $p['photo'] ?? null ) . '<div class="dbd-hdr"><a class="dbd-pn-link" href="' . esc_url( $turl ) . '">' . DBD_Marks::pennant( $a, 40 ) . esc_html( $t['nick'] ) . ' offense</a><span class="dbd-badge" id="dbd-psnaps">' . ( $p['snaps'] ? round( $snapAvg ) . '% of snaps' : 'no snap data' ) . '</span>';
echo '<a class="dbd-cmp-btn" id="dbd-cmp-btn" href="' . esc_url( $cmp( array( $p['id'] ) ) ) . '" data-id="' . esc_attr( $p['id'] ) . '" data-base="' . esc_url( DBD_Router::url() ) . '">Compare</a>';
if ( $sug ) { echo '<div class="dbd-cmp-sug"><span>Players like him</span>' . implode( '', array_map( function ( $o ) use ( $cmp, $p ) { return '<a href="' . esc_url( $cmp( array( $p['id'], $o['id'] ) ) ) . '" title="' . esc_attr( 'Compare with ' . $o['name'] ) . '">' . DBD_Marks::tile( $o['team'], 16 ) . esc_html( $o['name'] ) . '</a>'; }, $sug ) ) . '</div>'; }
echo '</div></div>';

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

/* ---- bands by question (SCOPE §20): a sticky jump nav, then five bands. The rail is gone. */
echo '<nav class="dbd-jump" aria-label="On this page"><a href="#dbd-good">How good is he</a><a href="#dbd-used">How he\'s used</a><a href="#dbd-with" id="dbd-jump-with" hidden>Who he works with</a><a href="#dbd-weeks">Week by week</a><a href="#dbd-more">Intel &amp; plays</a></nav>';
$band = function ( $id, $kick, $sub, $hidden = false ) { return '<section class="dbd-band" id="dbd-' . $id . '"' . ( $hidden ? ' hidden' : '' ) . '><h2 class="dbd-bandh">' . $kick . '<span>' . $sub . '</span></h2>'; };
echo $band( 'good', 'How good is he', 'league grades and the season line, side by side' ) . '<div class="dbd-two">';

/* ---- where he ranks: percentile bars against the league pool at his position (SCOPE §15) */
$gr = $p['grades'] ?? null; $ggrp = $gr['grp'] ?? $grp; $gi = $gr ? DBD_Grades::group_info( $ggrp ) : null; $N = (int) ( $gi['n'] ?? 0 ); $graded = $gr && ! empty( $gr['q'] ) && $N > 0;
if ( $gr && $gi ) {
	echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where he ranks</b> · among ' . $N . ' ' . esc_html( DBD_Grades::pool_label( $ggrp ) ) . ' with ' . esc_html( $gi['min'] ) . '+</span><span class="dbd-badge">League percentiles</span></div><div class="dbd-pct">';
	foreach ( DBD_Grades::defs( $ggrp ) as $d ) {
		list( $k, $lab, $sub, $kind ) = $d; $v = $gr['m'][ $k ] ?? null; $pc = $graded ? DBD_Grades::pctile( $ggrp, $k, $v ) : null;
		$rk = $pc === null ? null : DBD_Grades::rank( $ggrp, $k, $v ); $med = DBD_Grades::median( $ggrp, $k ); $Nk = count( $gi['dist'][ $k ] ?? array() );
		$tip = '<b>' . esc_html( $p['name'] ) . '</b><br>' . esc_html( $lab ) . ': <b>' . esc_html( DBD_Grades::fmt( $kind, $v ) ) . '</b>' . ( $pc === null ? '' : ' · ' . esc_html( DBD_Grades::ordinal( $pc ) ) . ' percentile · ' . esc_html( DBD_Grades::ordinal( $rk ) ) . ' of ' . $Nk ) . '<br><span class="m">League median ' . esc_html( DBD_Grades::fmt( $kind, $med ) ) . ( $sub ? ' · ' . esc_html( $sub ) : '' ) . '</span>';
		echo '<div class="row"><div class="l">' . esc_html( $lab ) . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</div><div class="bar">' . ( $pc === null ? '' : '<i style="--p:' . (int) $pc . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $lab . ' ' . DBD_Grades::fmt( $kind, $v ) . ', ' . DBD_Grades::ordinal( $pc ) . ' percentile' ) . '"></i>' ) . '</div><div class="v">' . esc_html( DBD_Grades::fmt( $kind, $v ) ) . '</div><div class="g' . ( $pc !== null && $pc >= 75 ? ' hi' : ( $pc !== null && $pc <= 25 ? ' lo' : '' ) ) . '">' . ( $pc === null ? '-' : esc_html( DBD_Grades::ordinal( $pc ) ) ) . '</div></div>';
	}
	echo '</div><p class="dbd-note">' . ( $graded ? 'Each bar is the league at his position; the marker is him. Right is better, the dashed line is the median. Grades move every week as the pool changes.' : 'Graded once he reaches ' . esc_html( $gi['min'] ) . '. ' . $N . ' ' . esc_html( DBD_Grades::pool_label( $ggrp ) ) . ' qualify so far.' ) . '</p></section>';
}

/* ---- season stats with league rank */
$ss = $grp === 'rec' ? array( array( 'Targets', (int) $tg ), array( 'Receptions', (int) $rc ), array( 'Yards', (int) $ry ), array( 'Touchdowns', (int) $rtd ) )
	: ( $grp === 'rb' ? array( array( 'Carries', (int) $ca ), array( 'Rush yards', (int) $ruy ), array( 'Rush TD', (int) $rutd ), array( 'Targets', (int) $tg ) )
	: array( array( 'Attempts', (int) $att ), array( 'Completions', (int) $cmp ), array( 'Pass yards', (int) $py ), array( 'TD / INT', (int) $ptd . ' / ' . (int) $pint ) ) );
if ( $gr ) { foreach ( DBD_Grades::defs( $ggrp ) as $d ) { $v = $gr['m'][ $d[0] ] ?? null; $ss[] = array( $d[1], DBD_Grades::fmt( $d[3], $v ), $graded ? DBD_Grades::rank( $ggrp, $d[0], $v ) : null, count( $gi['dist'][ $d[0] ] ?? array() ) ); } }
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Season stats</b> · with league rank</span><span class="dbd-badge">' . ( $graded ? 'of ' . $N : 'unranked' ) . '</span></div><div class="dbd-tw"><table class="dbd-sstat dbd-stat">';
foreach ( $ss as $r ) { $rk = $r[2] ?? null; $Nr = isset( $r[3] ) ? (int) $r[3] : $N; echo '<tr' . ( $rk && $rk <= ceil( $Nr * 0.1 ) ? ' class="top"' : '' ) . '><td>' . esc_html( $r[0] ) . '</td><td>' . esc_html( $r[1] ) . '</td><td class="rk">' . ( $rk ? '<b>' . esc_html( DBD_Grades::ordinal( $rk ) ) . '</b> of ' . $Nr : '' ) . '</td></tr>'; }
echo '</table></div></section>';

echo '</div></section>';
echo $band( 'used', 'How he\'s used', 'where the ball finds him and when' ) . '<div class="dbd-two">';

/* ---- field map + depth bands (JS) */
echo '<section class="dbd-panel" id="dbd-pmap-panel"><div class="dbd-ph"><span class="dbd-lab"><b id="dbd-maplab">' . ( $grp === 'qb' ? 'Where he throws' : ( $grp === 'rb' ? 'Where he runs' : 'Where the ball finds him' ) ) . '</b> · from the play file</span><span class="dbd-badge" id="dbd-mapn">loading</span></div>';
echo '<div class="dbd-sprays"><div><h4 id="dbd-map1h">On the field</h4><div id="dbd-pmap"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div></div><div><h4>By depth of target</h4><div class="dbd-bands" id="dbd-bands"></div><p class="dbd-note" id="dbd-bandnote" style="padding:8px 0 0"></p></div></div>';
echo '<div class="dbd-legend" id="dbd-pleg"></div><p class="dbd-note" id="dbd-mapnote"></p></section>';

/* ---- situations (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>When he gets it</b> · touches by situation</span></div><div class="dbd-wk3 dbd-tend" id="dbd-sit"></div></section>';
echo '</div></section>';
echo $band( 'with', 'Who he works with', 'the connection, and the rest of the huddle', true );

/* ---- the connection (JS, SCOPE §18): a quarterback's receivers, or everyone else's line by passer. Hidden until player.js finds targets. */
echo '<section class="dbd-panel dbd-cx" id="dbd-cx" hidden><div class="dbd-ph"><span class="dbd-lab"><b>The connection</b> · ' . ( $grp === 'qb' ? 'who he throws to' : 'his line by passer' ) . '</span><span class="dbd-badge" id="dbd-cx-n"></span></div>';
echo '<div class="dbd-tw"><table class="dbd-cx-tab' . ( $grp === 'qb' ? ' dbd-sortable' : '' ) . '" id="dbd-cx-tab"></table></div><p class="dbd-cx-say" id="dbd-cx-say"></p>';
echo '<div class="dbd-cx-2"><div><h4 id="dbd-cx-maph"></h4><div id="dbd-cx-map"></div><div class="dbd-cx-chips" id="dbd-cx-chips" role="group" aria-label="Isolate one ' . ( $grp === 'qb' ? 'receiver' : 'passer' ) . '"></div><p class="dbd-note" id="dbd-cx-mapnote" style="padding:8px 0 0"></p></div>';
echo '<div><h4 id="dbd-cx-wkh"></h4><div class="dbd-cx-wks" id="dbd-cx-wks"></div><div class="dbd-cx-wkl" id="dbd-cx-wkl"></div><h4>By depth</h4><div class="dbd-cx-dg" id="dbd-cx-dg"></div></div></div></section>';

/* ---- opponent context (SCOPE §19): the defense faced each week, ranked against his position. Metrics and schedule are already shipped. */
$dk = DBD_Grades::defense_key( $p['pos'] ); $dkey = $dk['key']; $M = DBD_Data::metrics(); $dlg = $M['league'][ $dkey ] ?? null; $hasDef = $dlg !== null && ! empty( $M['rank']['def'] );
$teams = DBD_Data::teams();
$drank = function ( $tm ) use ( $M, $dkey ) { return $tm && isset( $M['rank']['def'][ $tm ][ $dkey ] ) ? (int) $M['rank']['def'][ $tm ][ $dkey ] : null; };
$dval  = function ( $tm ) use ( $M, $dkey ) { return $M['defense'][ $tm ][ $dkey ] ?? null; };
$dtip  = function ( $tm ) use ( $drank, $dval, $dlg, $dk, $teams, $sgn ) { $v = $dval( $tm ); return '<b>' . esc_html( $teams[ $tm ]['nick'] ?? $tm ) . '</b> allow ' . ( $v === null ? '-' : $sgn( (float) $v ) ) . ' EPA ' . esc_html( $dk['unit'] ) . ( $dlg === null ? '' : ' <span class="m">league ' . $sgn( (float) $dlg ) . '</span>' ) . '<br>' . esc_html( DBD_Grades::ordinal( $drank( $tm ) ) ) . '-toughest of 32 ' . esc_html( $dk['lab'] ); };
$dchipShort = function ( $tm ) use ( $drank, $dtip ) { $rk = $drank( $tm ); return $rk ? '<span class="dbd-dchip ' . DBD_Grades::defense_tier( $rk ) . '" data-tip="' . esc_attr( $dtip( $tm ) ) . '" tabindex="0">' . esc_html( DBD_Grades::ordinal( $rk ) ) . ' D</span>' : ''; };  /* phone-sized: the fold under the opponent */
$dchip = function ( $tm ) use ( $drank, $dtip, $dk ) { $rk = $drank( $tm ); if ( ! $rk ) { return '<td class="dbd-dcell"></td>'; } return '<td class="dbd-dcell" data-v="' . $rk . '"><span class="dbd-dchip ' . DBD_Grades::defense_tier( $rk ) . '" data-tip="' . esc_attr( $dtip( $tm ) ) . '" tabindex="0">' . esc_html( DBD_Grades::ordinal( $rk ) . ' ' . $dk['lab'] ) . '</span></td>'; };
$oppOf = function ( $wk, $tm = null ) use ( $a ) { $tm = $tm ?: $a; $g = DBD_Data::team_game( $tm, $wk ); if ( ! $g ) { return null; } return $g['home'] === $tm ? $g['away'] : $g['home']; };

echo '</section>' . $band( 'weeks', 'Week by week', 'the shape of his season, the game log and the defenses behind it' );

/* ---- the schedule, week by week per team: the trend panel and Who he's faced both read it */
$sched = DBD_Data::schedule(); $games = array(); $maxWk = 0; $curWk = (int) ( $sched['current_week'] ?? $through );
foreach ( (array) ( $sched['weeks'] ?? array() ) as $wkn => $gl ) {
	foreach ( (array) $gl as $g ) {
		$wi = (int) ( $g['week'] ?? $wkn ); $maxWk = max( $maxWk, $wi ); $fin = ( $g['status'] ?? '' ) === 'final' || ( isset( $g['home_score'] ) && $g['home_score'] !== null );
		$games[ $g['home'] ][] = array( 'wk' => $wi, 'opp' => $g['away'], 'home' => true, 'played' => $fin );
		$games[ $g['away'] ][] = array( 'wk' => $wi, 'opp' => $g['home'], 'home' => false, 'played' => $fin );
	}
}
foreach ( $games as &$gg ) { usort( $gg, function ( $x, $y ) { return $x['wk'] - $y['wk']; } ); } unset( $gg );

/* ---- the shape of his season (SCOPE §21): four per-game metrics, week by week, his trailing three games over the league's middle half */
if ( $L ) {
	$tdefs = DBD_Grades::trend_defs( $grp ); $WIN = DBD_Grades::TREND_WIN;
	/* his season by league week: a game (stats, or a zero line where he dressed), a DNP where his team played without him, a bye, or a week still ahead */
	$Lby = array(); foreach ( $L as $w ) { $Lby[ (int) $w['week'] ] = $w; }
	$tmG = array(); foreach ( $games[ $a ] ?? array() as $x ) { $tmG[ $x['wk'] ] = $x; }
	$rows = array(); $lastWk = max( 18, $maxWk );
	for ( $wk = 1; $wk <= $lastWk; $wk++ ) {
		if ( isset( $Lby[ $wk ] ) ) { $w = $Lby[ $wk ]; $tm = $w['team'] ?? $a; $gm = DBD_Data::team_game( $tm, $wk ); $opp = $w['opponent_team'] ?? $oppOf( $wk, $tm );
			$rows[ $wk ] = array( 'kind' => 'game', 'w' => $w, 'tm' => $tm, 'opp' => (string) $opp, 'home' => $gm ? $gm['home'] === $tm : true, 'res' => $gm && $gm['home_score'] !== null ? ( ( $gm['home'] === $tm ? $gm['home_score'] > $gm['away_score'] : $gm['away_score'] > $gm['home_score'] ) ? 'W' : ( $gm['home_score'] === $gm['away_score'] ? 'T' : 'L' ) ) . ' ' . (int) ( $gm['home'] === $tm ? $gm['home_score'] : $gm['away_score'] ) . '-' . (int) ( $gm['home'] === $tm ? $gm['away_score'] : $gm['home_score'] ) : '' ); }
		elseif ( isset( $tmG[ $wk ] ) ) { $x = $tmG[ $wk ]; $rows[ $wk ] = array( 'kind' => $x['played'] ? 'dnp' : 'ahead', 'opp' => $x['opp'], 'home' => $x['home'] ); }
		else { $rows[ $wk ] = array( 'kind' => 'bye' ); }
	}
	$playedWks = array_keys( $Lby ); sort( $playedWks ); $nPlayed = count( $playedWks );
	/* one series per metric: per-game value, trailing-window line, season figure, the league's quartiles, the direction call */
	$series = array();
	foreach ( $tdefs as $def ) {
		$pts = array();
		foreach ( $playedWks as $wk ) { list( $n, $dn ) = DBD_Grades::trend_parts( $def, $Lby[ $wk ] ); $pts[] = array( 'wk' => $wk, 'n' => $n, 'dn' => $dn, 'v' => $dn ? $n / $dn : null ); }
		$roll = array();
		foreach ( $pts as $i => $q ) { if ( $i < $WIN - 1 ) { $roll[] = null; continue; } $sn = 0; $sd = 0; for ( $j = $i - $WIN + 1; $j <= $i; $j++ ) { $sn += $pts[ $j ]['n']; $sd += $pts[ $j ]['dn']; } $roll[] = $sd ? $sn / $sd : null; }
		$SN = array_sum( array_column( $pts, 'n' ) ); $SD = array_sum( array_column( $pts, 'dn' ) ); $sAvg = $SD ? $SN / $SD : null;
		$lg = DBD_Grades::trend_league( $grp, $def[0] ); $cur = null; $li = null;
		foreach ( $roll as $i => $v ) { if ( $v !== null ) { $cur = $v; $li = $i; } }
		$iqr = $lg ? (float) $lg['p75'] - (float) $lg['p25'] : ( $sAvg ? abs( $sAvg ) * 0.4 : 1 );
		$dir = ( $cur === null || $sAvg === null ) ? 'flat' : ( $cur - $sAvg > DBD_Grades::TREND_DIR * $iqr ? 'up' : ( $sAvg - $cur > DBD_Grades::TREND_DIR * $iqr ? 'down' : 'flat' ) );
		$series[] = array( 'key' => $def[0], 'lab' => $def[1], 'kind' => $def[2], 'rate' => $def[4] !== null, 'pts' => $pts, 'roll' => $roll, 'avg' => $sAvg, 'lg' => $lg, 'cur' => $cur, 'li' => $li, 'iqr' => $iqr, 'dir' => $dir );
	}
	$tf = function ( $m, $v ) { return DBD_Grades::trend_fmt( $m['kind'], $v ); };
	/* the charts */
	$Wd = 220; $Hh = 108; $Lm = 6; $Rm = 38; $Tp = 12; $Bm = 16; $step = ( $Wd - $Lm - $Rm ) / 18; $bw = max( 3, $step * 0.55 );
	$xw = function ( $wk ) use ( $Lm, $step ) { return round( $Lm + ( $wk - 0.5 ) * $step, 2 ); };
	$fo = 'font-family="IBM Plex Mono,monospace"';
	$charts = '';
	foreach ( $series as $m ) {
		$vals = array_filter( array_column( $m['pts'], 'v' ), function ( $v ) { return $v !== null; } ); $rl = array_filter( $m['roll'], function ( $v ) { return $v !== null; } );
		$hi = max( array_merge( array( 0 ), $vals, $rl, $m['lg'] ? array( (float) $m['lg']['p75'] ) : array() ) ); $lo = min( array_merge( array( 0 ), $vals, $rl, $m['lg'] ? array( (float) $m['lg']['p25'] ) : array() ) );
		$span = ( $hi - $lo ) ?: 1; $yv = function ( $v ) use ( $Tp, $hi, $span, $Hh, $Bm ) { return round( $Tp + ( $hi - $v ) / $span * ( $Hh - $Tp - $Bm ), 2 ); };
		$g = '<svg viewBox="0 0 ' . $Wd . ' ' . $Hh . '" role="img" aria-label="' . esc_attr( $m['lab'] . ' by week' ) . '">';
		if ( $m['lg'] ) { $g .= '<rect x="' . $Lm . '" y="' . $yv( $m['lg']['p75'] ) . '" width="' . ( $Wd - $Lm - $Rm ) . '" height="' . max( 1, $yv( $m['lg']['p25'] ) - $yv( $m['lg']['p75'] ) ) . '" fill="var(--dbd-line)"/><line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $yv( $m['lg']['p50'] ) . '" y2="' . $yv( $m['lg']['p50'] ) . '" stroke="var(--dbd-muted)" stroke-dasharray="3 3"/><text x="' . ( $Wd - $Rm + 3 ) . '" y="' . ( $yv( $m['lg']['p50'] ) + 3 ) . '" font-size="8" fill="var(--dbd-muted)" ' . $fo . '>lg ' . esc_html( $tf( $m, $m['lg']['p50'] ) ) . '</text>'; }
		$g .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $yv( 0 ) . '" y2="' . $yv( 0 ) . '" stroke="var(--dbd-line-2)"/>';
		foreach ( $rows as $wk => $r ) { $cx = $xw( $wk );
			if ( $r['kind'] === 'bye' ) { $g .= '<line x1="' . $cx . '" x2="' . $cx . '" y1="' . ( $yv( 0 ) - 3 ) . '" y2="' . ( $yv( 0 ) + 3 ) . '" stroke="var(--dbd-muted)" stroke-dasharray="1 1"/>'; }
			elseif ( $r['kind'] === 'dnp' ) { $g .= '<text x="' . $cx . '" y="' . ( $yv( 0 ) + 3 ) . '" font-size="9" fill="var(--dbd-bad)" text-anchor="middle" ' . $fo . ' tabindex="0" data-tip="' . esc_attr( '<b>Week ' . $wk . ' ' . ( $r['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $teams[ $r['opp'] ]['nick'] ?? $r['opp'] ) . '</b><br>he did not play' ) . '">×</text>'; }
			if ( $wk === $curWk ) { $g .= '<line x1="' . $cx . '" x2="' . $cx . '" y1="' . ( $Hh - $Bm + 2 ) . '" y2="' . ( $Hh - $Bm + 6 ) . '" stroke="var(--dbd-red)" stroke-width="2"/>'; }
			if ( ( $wk - 1 ) % 3 === 0 ) { $g .= '<text x="' . $cx . '" y="' . ( $Hh - 3 ) . '" font-size="8" fill="var(--dbd-muted)" text-anchor="middle" ' . $fo . '>' . $wk . '</text>'; }
		}
		foreach ( $m['pts'] as $q ) { if ( $q['v'] === null ) { continue; } $r = $rows[ $q['wk'] ]; $top = min( $yv( 0 ), $yv( $q['v'] ) ); $hgt = max( 1, abs( $yv( $q['v'] ) - $yv( 0 ) ) );
			$tip = '<b>Week ' . $q['wk'] . ' ' . ( ! empty( $r['tm'] ) && $r['tm'] !== $a ? esc_html( $r['tm'] ) . ' ' : '' ) . ( $r['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $teams[ $r['opp'] ]['nick'] ?? $r['opp'] ) . ( $r['res'] ? ' · ' . $r['res'] : '' ) . '</b><br>' . esc_html( $m['lab'] ) . ' <b>' . esc_html( $tf( $m, $q['v'] ) ) . '</b>' . ( $m['lg'] ? ' <span class="m">league median ' . esc_html( $tf( $m, $m['lg']['p50'] ) ) . '</span>' : '' );
			$g .= '<rect x="' . round( $xw( $q['wk'] ) - $bw / 2, 2 ) . '" y="' . $top . '" width="' . round( $bw, 2 ) . '" height="' . $hgt . '" fill="' . ( $q['v'] < 0 ? 'var(--dbd-bad)' : 'var(--dbd-ink-2)' ) . '" fill-opacity=".5" tabindex="0" data-tip="' . esc_attr( $tip ) . '"></rect>'; }
		$line = array(); foreach ( $m['pts'] as $i => $q ) { if ( $m['roll'][ $i ] !== null ) { $line[] = $xw( $q['wk'] ) . ',' . $yv( $m['roll'][ $i ] ); } }
		if ( $line ) { $g .= '<polyline points="' . implode( ' ', $line ) . '" fill="none" stroke="var(--dbd-red)" stroke-width="2" stroke-linejoin="round"/>'; }
		if ( $m['li'] !== null ) { $q = $m['pts'][ $m['li'] ]; $w3 = array(); for ( $j = $m['li'] - $WIN + 1; $j <= $m['li']; $j++ ) { $w3[] = 'Wk ' . $m['pts'][ $j ]['wk']; }
			$g .= '<circle cx="' . $xw( $q['wk'] ) . '" cy="' . $yv( $m['cur'] ) . '" r="3.5" fill="var(--dbd-red)" stroke="var(--dbd-panel)" stroke-width="1.5" tabindex="0" data-tip="' . esc_attr( '<b>Last three</b> (' . implode( ', ', $w3 ) . ')<br>' . esc_html( $m['lab'] ) . ' <b>' . esc_html( $tf( $m, $m['cur'] ) ) . '</b>' ) . '"></circle>'; }
		$g .= '</svg>';
		$near = ( $m['lg'] && $m['cur'] !== null ) ? $m['cur'] - (float) $m['lg']['p50'] : null;
		$dcls = $near === null ? '' : ( abs( $near ) < DBD_Grades::TREND_NEAR * $m['iqr'] ? '' : ( $near > 0 ? ' up' : ' dn' ) );
		$chip = $near === null ? '' : '<span class="d' . $dcls . '" data-tip="' . esc_attr( 'His last three against the league median' ) . '" tabindex="0">' . ( $near > 0 ? '+' : '' ) . number_format( $near, $m['kind'] === 'sgn' ? 2 : 1 ) . '<i> vs lg</i></span>';
		$head = $m['cur'] !== null ? $tf( $m, $m['cur'] ) : $tf( $m, $m['avg'] );
		$charts .= '<div class="dbd-trc"><div class="th"><b>' . esc_html( $m['lab'] ) . '</b><span class="v">' . esc_html( $head ) . '</span>' . $chip . '</div>' . $g . '</div>';
	}
	/* the read: direction, shape, streak or share above the median, season high or low, efficiency */
	list( $U, $Yd, $E ) = $series; $say = array();
	$word = function ( $m ) { return $m['dir'] === 'up' ? 'climbing' : ( $m['dir'] === 'down' ? 'slipping' : 'steady' ); };
	if ( $nPlayed < $WIN ) { $say[] = 'Too few games for a trend yet: <b>' . $nPlayed . '</b> played.'; }
	else {
		$say[] = esc_html( $U['lab'] ) . ' are <b>' . $word( $U ) . '</b>: ' . $tf( $U, $U['cur'] ) . ( $U['rate'] ? '' : ' a game' ) . ' over his last three against ' . $tf( $U, $U['avg'] ) . ' for the season.';
		$yc = DBD_Grades::trend_fmt( 'n', $Yd['cur'] === null ? null : round( $Yd['cur'] ) ); $ya = DBD_Grades::trend_fmt( 'n', $Yd['avg'] === null ? null : round( $Yd['avg'] ) );
		if ( $U['dir'] === 'up' && $Yd['dir'] === 'up' ) { $say[] = 'The role and the production are both growing: <b>' . $yc . '</b> yards a game lately against ' . $ya . '.'; }
		elseif ( $U['dir'] === 'up' && $Yd['cur'] > $Yd['avg'] ) { $say[] = 'The yards are edging up with it: ' . $yc . ' a game lately against ' . $ya . '.'; }
		elseif ( $U['dir'] === 'up' ) { $say[] = 'The volume is up; the yards haven\'t followed yet (' . $yc . ' against ' . $ya . ').'; }
		elseif ( $Yd['dir'] === 'up' ) { $say[] = 'He is doing more with less: <b>' . $yc . '</b> yards a game over the last three against ' . $ya . '.'; }
		elseif ( $U['dir'] === 'down' && $Yd['dir'] === 'down' ) { $say[] = 'The role is shrinking, and the yards with it (' . $yc . ' against ' . $ya . ').'; }
		elseif ( $Yd['dir'] !== 'flat' ) { $say[] = 'Yards are ' . $word( $Yd ) . ': ' . $yc . ' a game lately against ' . $ya . '.'; }
		$best = null;
		foreach ( array( $U, $Yd ) as $m ) { if ( ! $m['lg'] ) { continue; } $n = 0; $above = null;
			for ( $i = count( $m['pts'] ) - 1; $i >= 0; $i-- ) { $v = $m['pts'][ $i ]['v']; if ( $v === null ) { break; } $ab = $v > (float) $m['lg']['p50']; if ( $above === null ) { $above = $ab; } if ( $ab !== $above ) { break; } $n++; }
			if ( $n >= DBD_Grades::TREND_STREAK && ( ! $best || $n > $best['n'] ) ) { $best = array( 'n' => $n, 'above' => $above, 'm' => $m ); } }
		if ( $best ) { $say[] = '<b>' . $best['n'] . ' straight</b> games ' . ( $best['above'] ? 'above' : 'below' ) . ' a starter\'s median in ' . esc_html( strtolower( $best['m']['lab'] ) ) . '.'; }
		elseif ( $Yd['lg'] ) { $ab = 0; foreach ( $Yd['pts'] as $q ) { if ( $q['v'] !== null && $q['v'] >= (float) $Yd['lg']['p50'] ) { $ab++; } } $say[] = 'At or above a starter\'s median in yards in <b>' . $ab . ' of ' . count( $Yd['pts'] ) . '</b> games.'; }
		$ys = array_column( $Yd['pts'], 'v' ); $lastY = end( $ys ); $lastWkP = end( $playedWks );
		if ( count( $ys ) >= 4 && $lastY == max( $ys ) ) { $say[] = 'Week ' . $lastWkP . ' was his season high in yards.'; }
		elseif ( count( $ys ) >= 4 && $lastY == min( $ys ) ) { $say[] = 'Week ' . $lastWkP . ' was his season low in yards.'; }
		if ( $E['dir'] !== 'flat' ) { $say[] = esc_html( $E['lab'] ) . ' is ' . ( $E['dir'] === 'up' ? 'rising' : 'falling' ) . ' too: ' . $tf( $E, $E['cur'] ) . ' over the last three.'; }
	}
	echo '<section class="dbd-panel dbd-tr"><div class="dbd-ph"><span class="dbd-lab"><b>The shape of his season</b> · week by week against the league</span><span class="dbd-badge">' . $nPlayed . ' game' . ( $nPlayed === 1 ? '' : 's' ) . ' · through week ' . (int) $through . '</span></div>';
	echo '<div class="dbd-tr4">' . $charts . '</div><p class="dbd-tr-say">' . implode( ' ', $say ) . '</p>';
	echo '<p class="dbd-note" style="padding-top:0">Bars are his week. The shaded band is the league\'s middle half at his position, every qualifying player-game this season, and the dashed line is its median. The solid line is his trailing three games. An × on the baseline is a game his team played without him; a dotted tick is the bye.</p></section>';
}

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
	$views['Default'] = array( 'Snap^2|Tgt|Rec|Yds|TD^2|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $cell( (int) $w['receiving_tds'] ?: '' ) . $e( (float) $w['receiving_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td><td>' . (int) $rtd . '</td>' . $e( $repa ) );
	$views['Advanced'] = array( 'Tgt share|Air share^2|aDOT|YAC^2|EPA/tgt|1D^2|Drops^3', function ( $w, $wk ) use ( $cell, $e, $pct, $f, $pf ) { return $cell( $pct( (float) $w['target_share'] ) ) . $cell( $pct( (float) $w['air_yards_share'] ) ) . $cell( $f( $w['receiving_air_yards'], $w['targets'] ) ) . $cell( (int) $w['receiving_yards_after_catch'] ) . ( $w['targets'] ? $e( $w['receiving_epa'] / $w['targets'], 2 ) : $cell( '-' ) ) . $cell( (int) $w['receiving_first_downs'] ) . $cell( $pf( $wk, 'drop' ) ?: '' ); },
		'<td>' . $pct( $tshare ) . '</td><td>' . $pct( $ashare ) . '</td><td>' . $f1( $div( $air, $tg ) ) . '</td><td>' . (int) $yac . '</td>' . ( $tg ? $e( $repa / $tg, 2 ) : '<td>-</td>' ) . '<td>' . (int) $rfd . '</td><td>' . ( $drops ?: '' ) . '</td>' );
	$views['Traditional'] = array( 'Rec|Yds|Y/R^2|TD|20+^3', function ( $w, $wk ) use ( $cell, $f ) { return $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $cell( $f( $w['receiving_yards'], $w['receptions'] ) ) . $cell( (int) $w['receiving_tds'] ?: '' ) . $cell( (int) ( $w['receiving_20'] ?? 0 ) ?: '' ); },
		'<td>' . (int) $rc . '</td><td>' . (int) $ry . '</td><td>' . $f1( $div( $ry, $rc ) ) . '</td><td>' . (int) $rtd . '</td><td>' . (int) $sum( 'receiving_20' ) . '</td>' );
} elseif ( $grp === 'rb' ) {
	$views['Default'] = array( 'Snap^2|Car|Yds|TD^2|Tgt|Rec^3|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['carries'] ) . $cell( (int) $w['rushing_yards'] ) . $cell( ( (int) $w['rushing_tds'] + (int) $w['receiving_tds'] ) ?: '' ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $e( (float) $w['rushing_epa'] + (float) $w['receiving_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $ca . '</td><td>' . (int) $ruy . '</td><td>' . (int) ( $rutd + $rtd ) . '</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td>' . $e( $ruepa + $repa ) );
	$views['Advanced'] = array( 'YPC|EPA/car|EPA/tgt^2|10+^2|Brk tkl^3|Snap^3', function ( $w, $wk ) use ( $cell, $e, $f, $pf, $snap ) { return $cell( $f( $w['rushing_yards'], $w['carries'] ) ) . ( $w['carries'] ? $e( $w['rushing_epa'] / $w['carries'], 2 ) : $cell( '-' ) ) . ( $w['targets'] ? $e( $w['receiving_epa'] / $w['targets'], 2 ) : $cell( '-' ) ) . $cell( (int) ( $w['rushing_10'] ?? 0 ) ?: '' ) . $cell( ( $pf( $wk, 'bt' ) + $pf( $wk, 'rbt' ) ) ?: '' ) . $cell( $snap( $wk ) ); },
		'<td>' . $f1( $div( $ruy, $ca ) ) . '</td>' . ( $ca ? $e( $ruepa / $ca, 2 ) : '<td>-</td>' ) . ( $tg ? $e( $repa / $tg, 2 ) : '<td>-</td>' ) . '<td>' . (int) $sum( 'rushing_10' ) . '</td><td>' . ( $bt ?: '' ) . '</td><td>' . round( $snapAvg ) . '%</td>' );
	$views['Traditional'] = array( 'Car|Yds|YPC^2|TD|Rec^3|Rec yds^3', function ( $w, $wk ) use ( $cell, $f ) { return $cell( (int) $w['carries'] ) . $cell( (int) $w['rushing_yards'] ) . $cell( $f( $w['rushing_yards'], $w['carries'] ) ) . $cell( (int) $w['rushing_tds'] ?: '' ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ); },
		'<td>' . (int) $ca . '</td><td>' . (int) $ruy . '</td><td>' . $f1( $div( $ruy, $ca ) ) . '</td><td>' . (int) $rutd . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td>' );
} else {
	$views['Default'] = array( 'Snap^2|Cmp/Att|Yds|TD^2|INT^2|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['completions'] . '/' . (int) $w['attempts'] ) . $cell( (int) $w['passing_yards'] ) . $cell( (int) $w['passing_tds'] ?: '' ) . $cell( (int) $w['passing_interceptions'] ?: '' ) . $e( (float) $w['passing_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $cmp . '/' . (int) $att . '</td><td>' . (int) $py . '</td><td>' . (int) $ptd . '</td><td>' . (int) $pint . '</td>' . $e( $pepa ) );
	$views['Advanced'] = array( 'EPA/DB|CPOE|aDOT^2|Sk^3|Sk %^2|Rush EPA^2', function ( $w, $wk ) use ( $cell, $e, $f, $pc2, $sgn ) { $db = $w['attempts'] + $w['sacks_suffered']; return ( $db ? $e( $w['passing_epa'] / $db, 2 ) : $cell( '-' ) ) . $cell( $w['attempts'] ? $sgn( (float) $w['passing_cpoe'], 1 ) : '-' ) . $cell( $f( $w['passing_air_yards'] ?? 0, $w['attempts'] ) ) . $cell( (int) $w['sacks_suffered'] ?: '' ) . $cell( $pc2( $w['sacks_suffered'], $db ) ) . $e( (float) $w['rushing_epa'] ); },
		( ( $att + $sk ) ? $e( $pepa / ( $att + $sk ), 2 ) : '<td>-</td>' ) . '<td>' . $sgn( $cpoe, 1 ) . '</td><td>' . $f1( $div( $pair, $att ) ) . '</td><td>' . (int) $sk . '</td><td>' . $pct( $div( $sk, $att + $sk ) ) . '</td>' . $e( $ruepa ) );
	$views['Traditional'] = array( 'Cmp^3|Att^3|Pct|Yds|Y/A^2|TD|INT|Rate^2', function ( $w, $wk ) use ( $cell, $f, $pc2 ) { $r = DBD_Grades::passer_rating( (float) $w['completions'], (float) $w['attempts'], (float) $w['passing_yards'], (float) $w['passing_tds'], (float) $w['passing_interceptions'] ); return $cell( (int) $w['completions'] ) . $cell( (int) $w['attempts'] ) . $cell( $pc2( $w['completions'], $w['attempts'] ) ) . $cell( (int) $w['passing_yards'] ) . $cell( $f( $w['passing_yards'], $w['attempts'] ) ) . $cell( (int) $w['passing_tds'] ?: '' ) . $cell( (int) $w['passing_interceptions'] ?: '' ) . $cell( $r === null ? '-' : number_format( $r, 1 ) ); },
		'<td>' . (int) $cmp . '</td><td>' . (int) $att . '</td><td>' . $pct( $div( $cmp, $att ) ) . '</td><td>' . (int) $py . '</td><td>' . $f1( $div( $py, $att ) ) . '</td><td>' . (int) $ptd . '</td><td>' . (int) $pint . '</td><td>' . ( ( $r = DBD_Grades::passer_rating( $cmp, $att, $py, $ptd, $pint ) ) === null ? '-' : number_format( $r, 1 ) ) . '</td>' );
}
echo '<section class="dbd-panel"><div class="dbd-ph dbd-glogh"><span class="dbd-lab"><b>Game log</b> · week by week</span><div class="dbd-seg t-tabs dbd-gview" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>';
$i = 0; foreach ( array_keys( $views ) as $name ) { echo '<button class="t-tab" type="button" role="tab" data-v="' . esc_attr( $name ) . '" aria-selected="' . ( $i++ ? 'false' : 'true' ) . '">' . esc_html( $name ) . '</button>'; }
echo '</div></div>';
foreach ( $views as $name => $vd ) {
	list( $cols, $row, $foot ) = $vd;
	$tiers = array( null, null ); if ( $hasDef ) { $tiers[] = 'dbd-t3'; }
	$heads = '';
	foreach ( explode( '|', $cols ) as $c ) { $t = null; if ( preg_match( '/\^(\d)$/', $c, $m ) ) { $t = 'dbd-t' . $m[1]; $c = substr( $c, 0, -2 ); } $tiers[] = $t; $heads .= '<th>' . esc_html( $c ) . '</th>'; }
	$body = '';
	foreach ( $L as $w ) { $wk = (int) $w['week']; $opp = $w['opponent_team'] ?? $oppOf( $wk, $w['team'] ?? null ); $body .= '<tr>' . $cell( $wk ) . $cell( $res( $wk, $w['team'] ?? null ) . ( $hasDef ? '<span class="dbd-dfold">' . $dchipShort( $opp ) . '</span>' : '' ), 'res' ) . ( $hasDef ? $dchip( $opp ) : '' ) . $row( $w, $wk ) . '</tr>'; }
	echo '<div class="dbd-tw" data-view="' . esc_attr( $name ) . '"' . ( $name === 'Default' ? '' : ' hidden' ) . '><table class="dbd-glog dbd-stat dbd-sortable">';
	echo DBD_Router::tierize( '<thead><tr><th>Wk</th><th>Opponent</th>' . ( $hasDef ? '<th>Defense</th>' : '' ) . $heads . '</tr></thead><tbody>' . $body . '</tbody><tfoot><tr><td></td><td>' . $ng . ' games</td>' . ( $hasDef ? '<td></td>' : '' ) . $foot . '</tr></tfoot>', $tiers ) . '</table></div>';
}
echo ( $traded ? '<p class="dbd-note">Weeks marked with another team come from that team\'s stat line. The field map and situations below cover ' . esc_html( $t['nick'] ) . ' snaps only.</p>' : '' ) . '</section>';

/* ---- who he has faced (SCOPE §19): slate rank, the eighteen-week ribbon, his line by defense tier, yards against the rank */
/* the schedule was read above, before the trend panel */
$myRows = array();
foreach ( $L as $w ) { $wk = (int) $w['week']; $opp = $w['opponent_team'] ?? $oppOf( $wk, $w['team'] ?? null ); $rk = $drank( $opp ); if ( $rk ) { $myRows[] = array( 'w' => $w, 'wk' => $wk, 'opp' => $opp, 'rk' => $rk ); } }
if ( $hasDef && $myRows && $games ) {
	$slates = array();
	foreach ( array_keys( $teams ) as $tm ) { $s = 0; $n = 0; foreach ( $games[ $tm ] ?? array() as $x ) { $rk = $drank( $x['opp'] ); if ( $x['played'] && $rk ) { $s += $rk; $n++; } } if ( $n ) { $slates[] = $s / $n; } }
	sort( $slates );
	$myAvg = array_sum( array_column( $myRows, 'rk' ) ) / count( $myRows );
	$myRank = count( array_filter( $slates, function ( $v ) use ( $myAvg ) { return $v < $myAvg; } ) ) + 1; $med = $slates ? $slates[ (int) floor( count( $slates ) / 2 ) ] : null;
	$mineG = $games[ $a ] ?? array(); $ahead = array_values( array_filter( $mineG, function ( $x ) { return ! $x['played']; } ) );
	$aheadTough = count( array_filter( $ahead, function ( $x ) use ( $drank ) { return DBD_Grades::defense_tier( $drank( $x['opp'] ) ) === 'tough'; } ) ); $aheadSoft = count( array_filter( $ahead, function ( $x ) use ( $drank ) { return DBD_Grades::defense_tier( $drank( $x['opp'] ) ) === 'soft'; } ) ); $next = $ahead[0] ?? null;
	$lab = esc_html( $dk['lab'] );
	$say = 'He has faced the <b>' . esc_html( DBD_Grades::ordinal( $myRank ) ) . '-toughest</b> slate ' . $lab . ': his opponents rank <b>' . $f1( $myAvg ) . '</b> of 32 on average' . ( $med === null ? '' : ' (league middle ' . $f1( $med ) . ')' ) . '.';
	if ( $next && $drank( $next['opp'] ) ) { $say .= ' Ahead: <b>' . $aheadTough . '</b> of his next ' . count( $ahead ) . ( $aheadTough === 1 ? ' is' : ' are' ) . ' top-10 ' . $lab . ( $aheadSoft ? ' and ' . $aheadSoft . ( $aheadSoft === 1 ? ' is' : ' are' ) . ' bottom-10' : '' ) . ', starting with the <b>' . esc_html( $teams[ $next['opp'] ]['nick'] ?? $next['opp'] ) . '</b> (' . esc_html( DBD_Grades::ordinal( $drank( $next['opp'] ) ) ) . ( $next['home'] ? '' : ', away' ) . ') in Week ' . (int) $next['wk'] . '.'; }
	echo '<section class="dbd-panel dbd-ox"><div class="dbd-ph"><span class="dbd-lab"><b>Who he\'s faced</b> · defenses ranked ' . $lab . '</span><span class="dbd-badge">' . count( $L ) . ' played · ' . count( $ahead ) . ' ahead</span></div>';
	echo '<p class="dbd-ox-say">' . $say . '</p>';
	/* ribbon */
	/* played weeks come from his own log (his team that week and its opponent, so a traded player's early weeks are right); weeks still to play come from the current team's schedule */
	$byWk = array(); foreach ( $mineG as $x ) { $byWk[ $x['wk'] ] = $x; }
	foreach ( $L as $w ) { $wk = (int) $w['week']; $tm = $w['team'] ?? $a; $gm = DBD_Data::team_game( $tm, $wk ); $opp = $w['opponent_team'] ?? $oppOf( $wk, $tm ); if ( $opp ) { $byWk[ $wk ] = array( 'wk' => $wk, 'opp' => $opp, 'home' => $gm ? $gm['home'] === $tm : true, 'played' => true, 'tm' => $tm, 'his' => true ); } }
	$rib = '';
	for ( $wk = 1; $wk <= max( 18, $maxWk ); $wk++ ) {
		$x = $byWk[ $wk ] ?? null;
		if ( ! $x ) { $rib .= '<div class="c bye"><small>WK ' . $wk . '</small><b>—</b><i>bye</i></div>'; continue; }
		$dnp = $x['played'] && empty( $x['his'] ); $away = ! empty( $x['tm'] ) && $x['tm'] !== $a ? esc_html( $x['tm'] ) . ' ' : '';
		$rk = $drank( $x['opp'] ); $cls = DBD_Grades::defense_tier( $rk ) . ( $x['played'] ? ' done' : ' ahead' ) . ( $dnp ? ' dnp' : '' ) . ( $wk === $curWk ? ' now' : '' );
		$rib .= '<div class="c ' . $cls . '" tabindex="0" data-tip="' . esc_attr( '<b>Week ' . $wk . ' ' . $away . ( $x['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $teams[ $x['opp'] ]['nick'] ?? $x['opp'] ) . '</b>' . ( $dnp ? ' · he did not play' : '' ) . '<br>' . ( $rk ? $dtip( $x['opp'] ) : 'not ranked yet' ) ) . '"><small>' . $away . ( $x['home'] ? 'VS' : 'AT' ) . ' ' . esc_html( $x['opp'] ) . '</small><b>' . ( $rk ?: '–' ) . '</b><i>' . ( $rk ? $lab : '' ) . '</i></div>';
	}
	echo '<h4>The slate · played solid, ahead dashed</h4><div class="dbd-ox-rib">' . $rib . '</div>';
	echo '<div class="dbd-legend"><span><i class="sq" style="background:#161e36"></i>Top 10, tough</span><span><i class="sq" style="background:var(--dbd-line-2)"></i>11 to 22</span><span><i class="sq" style="background:rgba(30,127,74,.35)"></i>23 to 32, soft</span><span>Number is the defense\'s rank ' . $lab . ', season to date. Dashed cells are still to play; the red outline is this week.</span></div>';
	/* his line by defense tier, from his weekly line */
	if ( $grp === 'rec' ) { $uLab = 'Tgt/g'; $eLab = 'EPA/tgt'; $uUse = function ( $w ) { return (float) $w['targets']; }; $uYds = function ( $w ) { return (float) $w['receiving_yards']; }; $uEpa = function ( $w ) { return (float) $w['receiving_epa']; }; $uDen = $uUse; }
	elseif ( $grp === 'rb' ) { $uLab = 'Touches/g'; $eLab = 'EPA/touch'; $uUse = function ( $w ) { return (float) $w['carries'] + (float) $w['targets']; }; $uYds = function ( $w ) { return (float) $w['rushing_yards'] + (float) $w['receiving_yards']; }; $uEpa = function ( $w ) { return (float) $w['rushing_epa'] + (float) $w['receiving_epa']; }; $uDen = $uUse; }
	else { $uLab = 'Att/g'; $eLab = 'EPA/db'; $uUse = function ( $w ) { return (float) $w['attempts']; }; $uYds = function ( $w ) { return (float) $w['passing_yards']; }; $uEpa = function ( $w ) { return (float) $w['passing_epa']; }; $uDen = function ( $w ) { return (float) $w['attempts'] + (float) ( $w['sacks_suffered'] ?? 0 ); }; }
	$tot = function ( $rows, $fn ) { $s = 0; foreach ( $rows as $r ) { $s += $fn( $r['w'] ); } return $s; };
	$tiers = array( array( 'tough', 'Top-10 defenses', 'ranked 1 to 10' ), array( 'mid', 'Middle', '11 to 22' ), array( 'soft', 'Bottom-10', '23 to 32' ) );
	echo '<div class="dbd-ox-2"><div><h4>His line by defense faced</h4><table class="dbd-ox-split dbd-stat"><thead><tr><th>Defenses</th><th>G</th><th>' . esc_html( $uLab ) . '</th><th>Yds/g</th><th>' . esc_html( $eLab ) . '</th></tr></thead><tbody>';
	foreach ( $tiers as $tr ) {
		$rows = array_filter( $myRows, function ( $r ) use ( $tr ) { return DBD_Grades::defense_tier( $r['rk'] ) === $tr[0]; } ); $g = count( $rows ); $den = $tot( $rows, $uDen ); $ep = $tot( $rows, $uEpa );
		echo '<tr><td><span class="dbd-dchip ' . $tr[0] . '">' . esc_html( $tr[2] ) . '</span>' . esc_html( $tr[1] ) . '</td><td>' . ( $g ?: '–' ) . '</td><td>' . ( $g ? $f1( $tot( $rows, $uUse ) / $g ) : '–' ) . '</td><td>' . ( $g ? round( $tot( $rows, $uYds ) / $g ) : '–' ) . '</td>' . ( $den ? $e( $ep / $den, 2 ) : '<td>–</td>' ) . '</tr>';
	}
	$gAll = count( $myRows ); $denAll = $tot( $myRows, $uDen );
	echo '</tbody><tfoot><tr><td>All games</td><td>' . $gAll . '</td><td>' . $f1( $tot( $myRows, $uUse ) / $gAll ) . '</td><td>' . round( $tot( $myRows, $uYds ) / $gAll ) . '</td>' . ( $denAll ? $e( $tot( $myRows, $uEpa ) / $denAll, 2 ) : '<td>–</td>' ) . '</tr></tfoot></table></div>';
	/* scatter: opponent rank against his yards that week */
	$pts = array_map( function ( $r ) use ( $uYds ) { return array( 'r' => $r['rk'], 'y' => $uYds( $r['w'] ), 'o' => $r['opp'], 'wk' => $r['wk'] ); }, $myRows );
	$Wd = 360; $H = 210; $Lm = 34; $Rm = 10; $Tp = 16; $Bm = 30; $maxY = max( 10, max( array_column( $pts, 'y' ) ) ) * 1.15; $n = count( $pts );
	$X = function ( $r ) use ( $Lm, $Wd, $Rm ) { return $Lm + ( $r - 1 ) / 31 * ( $Wd - $Lm - $Rm ); }; $Y = function ( $v ) use ( $Tp, $H, $Bm, $maxY ) { return $Tp + ( 1 - $v / $maxY ) * ( $H - $Tp - $Bm ); };
	$avgY = array_sum( array_column( $pts, 'y' ) ) / $n; $colT = array( 'tough' => '#161e36', 'mid' => '#9a96a6', 'soft' => '#1e7f4a' );
	$sc = '<svg viewBox="0 0 ' . $Wd . ' ' . $H . '" width="100%" style="display:block" role="img" aria-label="Yards by defense rank">';
	foreach ( array( 0, .5, 1 ) as $fr ) { $v = round( $maxY * $fr / 10 ) * 10; $sc .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $Y( $v ) . '" y2="' . $Y( $v ) . '" stroke="var(--dbd-line)"/><text x="' . ( $Lm - 6 ) . '" y="' . ( $Y( $v ) + 4 ) . '" font-size="9" fill="var(--dbd-muted)" text-anchor="end" font-family="IBM Plex Mono,monospace">' . $v . '</text>'; }
	$sc .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $Y( $avgY ) . '" y2="' . $Y( $avgY ) . '" stroke="var(--dbd-red)" stroke-dasharray="3 4"/><text x="' . ( $Wd - $Rm ) . '" y="' . ( $Y( $avgY ) - 4 ) . '" font-size="9" fill="var(--dbd-red)" text-anchor="end" font-family="IBM Plex Mono,monospace">avg ' . round( $avgY ) . '</text>';
	$sc .= '<text x="' . $Lm . '" y="' . ( $H - 8 ) . '" font-size="9" fill="var(--dbd-muted)" font-family="IBM Plex Mono,monospace">1 · toughest</text><text x="' . ( $Wd - $Rm ) . '" y="' . ( $H - 8 ) . '" font-size="9" fill="var(--dbd-muted)" text-anchor="end" font-family="IBM Plex Mono,monospace">32 · softest</text><text x="' . ( ( $Lm + $Wd - $Rm ) / 2 ) . '" y="' . ( $H - 8 ) . '" font-size="9" fill="var(--dbd-muted)" text-anchor="middle" font-family="IBM Plex Mono,monospace">defense rank ' . $lab . '</text>';
	foreach ( $pts as $q ) { $sc .= '<circle cx="' . $X( $q['r'] ) . '" cy="' . $Y( $q['y'] ) . '" r="6" fill="' . $colT[ DBD_Grades::defense_tier( $q['r'] ) ] . '" stroke="#fff" stroke-width="1.5" tabindex="0" data-tip="' . esc_attr( '<b>Week ' . $q['wk'] . ' vs ' . esc_html( $q['o'] ) . '</b> (' . DBD_Grades::ordinal( $q['r'] ) . ' ' . esc_html( $dk['lab'] ) . ')<br>' . round( $q['y'] ) . ' yards' ) . '"></circle><text x="' . $X( $q['r'] ) . '" y="' . ( $Y( $q['y'] ) - 9 ) . '" font-size="9" fill="var(--dbd-ink-2)" text-anchor="middle" font-family="IBM Plex Mono,monospace">' . esc_html( $q['o'] ) . '</text>'; }
	$sc .= '</svg>';
	$mx = array_sum( array_column( $pts, 'r' ) ) / $n; $cov = 0; $vx = 0; $vy = 0;
	foreach ( $pts as $q ) { $cov += ( $q['r'] - $mx ) * ( $q['y'] - $avgY ); $vx += ( $q['r'] - $mx ) ** 2; $vy += ( $q['y'] - $avgY ) ** 2; }
	$rr = ( $vx && $vy ) ? $cov / sqrt( $vx * $vy ) : 0; $rs = number_format( $rr, 2 );
	$read = $n < 4 ? 'Too few games to read a pattern yet.' : ( $rr > DBD_Grades::DEF_CORR ? 'His yards climb as the defense gets softer (r = ' . $rs . '): the schedule has been doing some of the work.' : ( $rr < -DBD_Grades::DEF_CORR ? 'He has done more against the better defenses (r = ' . $rs . ').' : 'His production barely moves with the opponent (r = ' . $rs . '); it has traveled.' ) );
	echo '<div><h4>Yards against the rank of the defense</h4><div class="dbd-ox-sc">' . $sc . '</div><p class="dbd-note" style="padding:6px 14px 0">' . esc_html( $read ) . '</p></div></div></section>';
}


echo '</section>' . $band( 'more', 'Intel &amp; plays', 'the report, his best snaps, his teammates' ) . '<div class="dbd-three">';

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
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Teammates</b> · share of the ball</span></div><div class="dbd-tw"><table class="dbd-mates dbd-stat dbd-sortable"><thead><tr><th>Player</th><th>Touches</th><th>Yds</th><th>EPA</th></tr></thead><tbody>';
foreach ( $mates as $x ) { list( $m, $tc, $y, $td, $ep ) = $x; $href = DBD_Router::url( array( 'player' => $m['id'] ) );
	echo '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::player( $a, $m['num'], 30, $m['photo'] ?? null ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $m['name'] ) . '</a><small>' . esc_html( $m['pos'] ) . '</small></span></td><td>' . (int) $tc . '</td><td data-v="' . (int) round( $y ) . '">' . (int) round( $y ) . ( (int) $td ? ' <small>· ' . (int) $td . ' TD</small>' : '' ) . '</td>' . $e( $ep ) . '</tr>'; }
echo '</tbody></table></div></section>';
echo '</div></section></main>';
