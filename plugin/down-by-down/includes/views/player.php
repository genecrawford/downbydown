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
$zero = array_fill_keys( array( 'targets', 'receptions', 'receiving_yards', 'receiving_tds', 'receiving_air_yards', 'receiving_yards_after_catch', 'receiving_epa', 'receiving_first_downs', 'target_share', 'air_yards_share', 'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa', 'rushing_first_downs', 'attempts', 'completions', 'passing_yards', 'passing_tds', 'passing_interceptions', 'passing_epa', 'passing_cpoe', 'sacks_suffered' ), 0 );
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
echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::player( $a, $p['num'], 72, $p['photo'] ?? null ) . '<span>' . esc_html( $first ) . ' <em>' . esc_html( $last ) . '</em></span></h1>';
$bio = array_filter( array( $p['age'] ? 'Age ' . (int) $p['age'] : '', $p['ht'] ?: '', $p['wt'] ? (int) $p['wt'] . ' lb' : '', $p['col'] ?: '', $p['draft'] ?: '' ) );
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">' . esc_html( implode( ' · ', $bio ) ) . '.' . ( $depth ? ' Listed <b>' . esc_html( $depth ) . '</b> on the ' . esc_html( $p['depth']['grp'] ) . ' depth chart.' : '' ) . ' Snap share <b>' . round( $snapAvg ) . '%</b> across ' . $ng . ' ' . ( $ng === 1 ? 'game' : 'games' ) . '.</p>';
echo '<div class="dbd-pills">' . ( $p['inj'] ? '<span class="dbd-pill ' . $icls . '">Week ' . (int) $p['inj']['week'] . ' report · ' . esc_html( $p['inj']['what'] ) . ' · ' . esc_html( str_ireplace( ' in practice', '', $st ) ) . '</span>' : '<span class="dbd-pill good">No injury listing</span>' ) . ( $depth ? '<span class="dbd-pill">' . esc_html( $depth ) . '</span>' : '' ) . '<a class="dbd-pill" href="' . esc_url( $tpurl ) . '">' . esc_html( $t['nick'] ) . ' players</a></div>';
echo '</div><div class="dbd-hdr"><a class="dbd-pn-link" href="' . esc_url( $turl ) . '">' . DBD_Marks::pennant( $a, 40 ) . esc_html( $t['nick'] ) . ' offense</a><span class="dbd-badge" id="dbd-psnaps">' . ( $p['snaps'] ? round( $snapAvg ) . '% of snaps' : 'no snap data' ) . '</span></div></div>';

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

echo '<div class="dbd-pwork"><div class="dbd-pmain">';

/* ---- field map + depth bands (JS) */
echo '<section class="dbd-panel" id="dbd-pmap-panel"><div class="dbd-ph"><span class="dbd-lab"><b id="dbd-maplab">' . ( $grp === 'qb' ? 'Where he throws' : ( $grp === 'rb' ? 'Where he runs' : 'Where the ball finds him' ) ) . '</b> · from the play file</span><span class="dbd-badge" id="dbd-mapn">loading</span></div>';
echo '<div class="dbd-sprays"><div><h4 id="dbd-map1h">On the field</h4><div id="dbd-pmap"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div></div><div><h4>By depth of target</h4><div class="dbd-bands" id="dbd-bands"></div><p class="dbd-note" id="dbd-bandnote" style="padding:8px 0 0"></p></div></div>';
echo '<div class="dbd-legend" id="dbd-pleg"></div><p class="dbd-note" id="dbd-mapnote"></p></section>';

/* ---- game log (PHP) */
$res = function ( $wk, $tm = null ) use ( $a ) { $tm = $tm ?: $a; $g = DBD_Data::team_game( $tm, $wk ); if ( ! $g ) { return ''; } $home = $g['home'] === $tm; $opp = $home ? $g['away'] : $g['home']; $pf = $home ? $g['home_score'] : $g['away_score']; $pa = $home ? $g['away_score'] : $g['home_score'];
	if ( $pf === null ) { return ( $home ? 'vs ' : 'at ' ) . $opp; }
	return ( $tm !== $a ? esc_html( $tm ) . ' ' : '' ) . ( $home ? 'vs ' : 'at ' ) . esc_html( $opp ) . ' <b class="' . ( $pf > $pa ? '' : 'l' ) . '">' . ( $pf > $pa ? 'W' : ( $pf < $pa ? 'L' : 'T' ) ) . '</b> ' . (int) $pf . '-' . (int) $pa; };
$snap = function ( $wk ) use ( $p ) { return isset( $p['snaps'][ (string) (int) $wk ] ) ? (int) $p['snaps'][ (string) (int) $wk ] . '%' : '-'; };
$pf = function ( $wk, $k ) use ( $p ) { return (int) ( $p['pfr'][ (string) (int) $wk ][ $k ] ?? 0 ); };
$drops = 0; $bt = 0; $rats = array();
foreach ( $p['pfr'] ?? array() as $x ) { $drops += (int) ( $x['drop'] ?? 0 ); $bt += (int) ( $x['bt'] ?? 0 ) + (int) ( $x['rbt'] ?? 0 ); if ( isset( $x['rat'] ) && $x['rat'] !== null ) { $rats[] = (float) $x['rat']; } }
$cell = function ( $v, $cls = '' ) { return '<td' . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . $v . '</td>'; };
$e = function ( $v ) { return '<td class="' . ( $v >= 0 ? 'pos' : 'neg' ) . '">' . ( $v > 0 ? '+' : '' ) . number_format( $v, 1 ) . '</td>'; };
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Game log</b> · week by week</span><span class="dbd-badge">Season totals in the last row</span></div><div class="dbd-tw"><table class="dbd-glog">';
if ( $grp === 'rec' ) {
	echo '<thead><tr><th>Wk</th><th>Opponent</th><th>Snap</th><th>Tgt</th><th>Rec</th><th>Yds</th><th>TD</th><th>aDOT</th><th>YAC</th><th>Share</th><th>EPA</th><th>Drops</th></tr></thead><tbody>';
	foreach ( $L as $w ) { $wk = (int) $w['week']; echo '<tr>' . $cell( $wk ) . $cell( $res( $wk, $w['team'] ?? null ), 'res' ) . $cell( $snap( $wk ) ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $cell( (int) $w['receiving_tds'] ?: '' ) . $cell( $f1( $div( $w['receiving_air_yards'], $w['targets'] ) ) ) . $cell( (int) $w['receiving_yards_after_catch'] ) . $cell( $pct( (float) $w['target_share'] ) ) . $e( (float) $w['receiving_epa'] ) . $cell( $pf( $wk, 'drop' ) ?: '' ) . '</tr>'; }
	echo '</tbody><tfoot><tr><td></td><td>' . $ng . ' games</td><td>' . round( $snapAvg ) . '%</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td><td>' . (int) $rtd . '</td><td>' . $f1( $div( $air, $tg ) ) . '</td><td>' . (int) $yac . '</td><td>' . $pct( $tshare ) . '</td>' . $e( $repa ) . '<td>' . ( $drops ?: '' ) . '</td></tr></tfoot>';
} elseif ( $grp === 'rb' ) {
	echo '<thead><tr><th>Wk</th><th>Opponent</th><th>Snap</th><th>Car</th><th>Yds</th><th>YPC</th><th>TD</th><th>Tgt</th><th>Rec</th><th>Rec yds</th><th>EPA</th><th>Brk tkl</th></tr></thead><tbody>';
	foreach ( $L as $w ) { $wk = (int) $w['week']; echo '<tr>' . $cell( $wk ) . $cell( $res( $wk, $w['team'] ?? null ), 'res' ) . $cell( $snap( $wk ) ) . $cell( (int) $w['carries'] ) . $cell( (int) $w['rushing_yards'] ) . $cell( $f1( $div( $w['rushing_yards'], $w['carries'] ) ) ) . $cell( ( (int) $w['rushing_tds'] + (int) $w['receiving_tds'] ) ?: '' ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $e( (float) $w['rushing_epa'] + (float) $w['receiving_epa'] ) . $cell( ( $pf( $wk, 'bt' ) + $pf( $wk, 'rbt' ) ) ?: '' ) . '</tr>'; }
	echo '</tbody><tfoot><tr><td></td><td>' . $ng . ' games</td><td>' . round( $snapAvg ) . '%</td><td>' . (int) $ca . '</td><td>' . (int) $ruy . '</td><td>' . $f1( $div( $ruy, $ca ) ) . '</td><td>' . (int) ( $rutd + $rtd ) . '</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td>' . $e( $ruepa + $repa ) . '<td>' . ( $bt ?: '' ) . '</td></tr></tfoot>';
} else {
	echo '<thead><tr><th>Wk</th><th>Opponent</th><th>Snap</th><th>Cmp</th><th>Att</th><th>Yds</th><th>TD</th><th>INT</th><th>Sk</th><th>CPOE</th><th>EPA</th></tr></thead><tbody>';
	foreach ( $L as $w ) { $wk = (int) $w['week']; echo '<tr>' . $cell( $wk ) . $cell( $res( $wk, $w['team'] ?? null ), 'res' ) . $cell( $snap( $wk ) ) . $cell( (int) $w['completions'] ) . $cell( (int) $w['attempts'] ) . $cell( (int) $w['passing_yards'] ) . $cell( (int) $w['passing_tds'] ?: '' ) . $cell( (int) $w['passing_interceptions'] ?: '' ) . $cell( (int) $w['sacks_suffered'] ?: '' ) . $cell( $w['attempts'] ? $sgn( (float) $w['passing_cpoe'], 1 ) : '-' ) . $e( (float) $w['passing_epa'] ) . '</tr>'; }
	echo '</tbody><tfoot><tr><td></td><td>' . $ng . ' games</td><td>' . round( $snapAvg ) . '%</td><td>' . (int) $cmp . '</td><td>' . (int) $att . '</td><td>' . (int) $py . '</td><td>' . (int) $ptd . '</td><td>' . (int) $pint . '</td><td>' . (int) $sk . '</td><td>' . $sgn( $cpoe, 1 ) . '</td>' . $e( $pepa ) . '</tr></tfoot>';
}
echo '</table></div>' . ( $traded ? '<p class="dbd-note">Weeks marked with another team come from that team\'s stat line. The field map and situations below cover ' . esc_html( $t['nick'] ) . ' snaps only.</p>' : '' ) . '</section>';

/* ---- situations (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>When he gets it</b> · touches by situation</span></div><div class="dbd-wk3 dbd-tend" id="dbd-sit"></div></section>';
echo '</div><div class="dbd-rail">';

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
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Teammates</b> · share of the ball</span></div><div class="dbd-tw"><table class="dbd-mates"><thead><tr><th>Player</th><th>Touches</th><th>Yds</th><th>TD</th><th>EPA</th></tr></thead><tbody>';
foreach ( $mates as $x ) { list( $m, $tc, $y, $td, $ep ) = $x; $href = DBD_Router::url( array( 'player' => $m['id'] ) );
	echo '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::player( $a, $m['num'], 30, $m['photo'] ?? null ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $m['name'] ) . '</a><small>' . esc_html( $m['pos'] ) . '</small></span></td><td>' . (int) $tc . '</td><td>' . (int) round( $y ) . '</td><td>' . ( (int) $td ?: '' ) . '</td>' . $e( $ep ) . '</tr>'; }
echo '</tbody></table></div></section>';
echo '</div></div></main>';
