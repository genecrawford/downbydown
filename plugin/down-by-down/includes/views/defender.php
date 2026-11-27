<?php
/**
 * Defender page (SCOPE §101): the player route for a defender. Everything is in defenders/{TEAM}.json (tools/build_defenders.py):
 * the header in his team's colors, tiles and the Scorecard for his role (the front, linebackers, the secondary), the short version,
 * every play he made on the field (assets/defchart.js), his biggest plays, the game log, his career, and his unit.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $row = $r['player'];
$d = $row ? DBD_Data::defender_record( $row['team'], $row['id'] ) : null;
if ( ! $d ) { include DBD_PATH . 'includes/views/notfound.php'; return; }
$a = $d['team']; $t = DBD_Data::team( $a ) ?: array( 'nick' => $a, 'name' => $a ); $nick = $t['nick'] ?? $a; $season = DBD_Data::season(); $arch = DBD_Data::archived();
$s = (array) $d['s']; $role = in_array( $d['role'] ?? '', array( 'front', 'lb', 'db' ), true ) ? $d['role'] : 'front'; $q = ! empty( $d['q'] );
$g = max( 1, (int) ( $s['g'] ?? 0 ) ); $n0 = function ( $v ) { $v = (float) $v; return $v == (int) $v ? (string) (int) $v : rtrim( rtrim( number_format( $v, 1 ), '0' ), '.' ); };
$parts = explode( ' ', (string) $d['name'], 2 ); $first = $parts[0]; $last = $parts[1] ?? '';
$RN = array( 'front' => array( 'pass rushers and linemen', 'The front' ), 'lb' => array( 'linebackers', 'Linebackers' ), 'db' => array( 'defensive backs', 'The secondary' ) );
/* the Scorecard's figures by role: label, what it measures, format, lower is better */
$MET = array(
	'prs' => array( 'Pressures', 'a game', '1', false ), 'sk' => array( 'Sacks', 'a game', '2', false ), 'qbh' => array( 'QB hits', 'a game', '1', false ),
	'tfl' => array( 'Tackles for loss', 'a game', '2', false ), 'fin' => array( 'Pressures that end in a sack', 'sacks per pressure', '%', false ),
	'tkl' => array( 'Tackles', 'a game', '1', false ), 'mtp' => array( 'Missed tackles', 'of his tackle attempts', '%', true ), 'snap' => array( 'Snap share', "of the defense's snaps, in his games", '%', false ),
	'ypt' => array( 'Yards a target', 'in coverage', '1', true ), 'rate' => array( 'Passer rating allowed', 'thrown at him', '1', true ), 'cpa' => array( 'Catches allowed', 'per target', '%', true ),
	'ball' => array( 'Plays on the ball', 'interceptions and passes defended a game', '2', false ),
);
$fmt = function ( $v, $f ) { if ( $v === null ) { return '–'; } return $f === '%' ? round( (float) $v * 100 ) . '%' : number_format( (float) $v, (int) $f ); };
$R = (array) ( $d['r'] ?? array() ); $TR = (array) ( $d['tr'] ?? array() );
$pool = (int) ( ( reset( $R ) ?: array( 0, 0, 0 ) )[2] ?? 0 ); foreach ( $R as $x ) { $pool = max( $pool, (int) ( $x[2] ?? 0 ) ); }
$ord = function ( $n ) { return DBD_Grades::ordinal( (int) $n ); };
$turl = DBD_Router::url( array( 'team' => $a, 'side' => 'def' ) ); $purl = DBD_Router::url( array( 'view' => 'players' ) );

echo DBD_Router::topbar( array( 'players' => $purl, $nick . ' defense' => $turl, $d['name'] => null ) );
echo '<main class="dbd-content dbd-player dbd-dfp" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">';

/* ---- the header band in his team's colors (SCOPE §99) */
$BAND = DBD_Marks::band( $a );
echo '<div class="dbd-pband' . $BAND['cls'] . '" style="' . esc_attr( $BAND['style'] ) . '"><div class="t-stagger">';
echo '<a class="dbd-bteam" href="' . esc_url( $turl ) . '">' . DBD_Marks::pennant( $a, 46 ) . '<span class="t"><b>' . esc_html( $nick ) . ' defense</b><small>' . (int) round( (float) ( $s['snap'] ?? 0 ) ) . '% of snaps</small></span></a>';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · " . $d['pos'] . ( isset( $d['num'] ) ? ' · #' . $d['num'] : '' ) . ' · ' . $ord( (int) ( $d['exp'] ?? 0 ) + 1 ) . ' season' ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2"><span>' . esc_html( $first ) . ' <em>' . esc_html( $last ) . '</em></span></h1>';
$age = ''; if ( ! empty( $d['born'] ) && ( $bt = strtotime( $d['born'] ) ) ) { $age = 'Age ' . (int) floor( ( time() - $bt ) / ( 365.2425 * 86400 ) ); }
$dr = is_array( $d['draft'] ?? null ) ? 'Round ' . (int) $d['draft'][0] . ', pick ' . (int) $d['draft'][1] . ' (' . (int) $d['draft'][2] . ')' : ( ! empty( $d['rookie'] ) ? 'Undrafted (' . (int) $d['rookie'] . ')' : '' );
$bio = array_filter( array( $age, $d['ht'] ?? '', ! empty( $d['wt'] ) ? (int) $d['wt'] . ' lb' : '', $d['col'] ?? '', $dr ) );
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">' . esc_html( implode( ' · ', $bio ) ) . ( $bio ? '. ' : '' ) . 'Snap share <b>' . (int) round( (float) ( $s['snap'] ?? 0 ) ) . '%</b> across ' . (int) $s['g'] . ' ' . ( (int) $s['g'] === 1 ? 'game' : 'games' ) . '.</p>';
$rush = DBD_Router::url( array( 'view' => 'leaders', 'g' => 'def' ) ) . '#ld-rush';
echo '<div class="dbd-pills"><span class="dbd-pill">' . esc_html( $RN[ $role ][1] ) . '</span><a class="dbd-pill" href="' . esc_url( $turl ) . '">' . esc_html( $nick ) . ' defense</a>'
	. ( $role === 'front' && ! $arch ? '<a class="dbd-pill" href="' . esc_url( $rush ) . '">Pass rushers chart</a>' : '' ) . ( is_array( $d['draft'] ?? null ) ? '<a class="dbd-pill" href="' . esc_url( DBD_Router::url( array( 'view' => 'draft', 'y' => (int) $d['draft'][2] ) ) ) . '">The ' . (int) $d['draft'][2] . ' draft</a>' : '' ) . '</div>';
$like = array_values( array_filter( array_map( array( 'DBD_Data', 'defender' ), (array) ( $d['like'] ?? array() ) ) ) );
if ( $like && ! $arch ) {
	echo '<div class="dbd-blike"><span>Players like him</span>';
	foreach ( $like as $x ) { echo '<a class="dbd-bchip" href="' . esc_url( DBD_Router::url( array( 'player' => $x['id'] ) ) ) . '">' . DBD_Marks::tile( $x['team'], 18 ) . '<b>' . esc_html( $x['name'] ) . '</b></a>'; }
	echo '</div>';
}
echo '</div>' . DBD_Marks::hero_photo( $a, $d['photo'] ?? null, 350, 'dbd-bphoto' ) . '</div>';

/* ---- tiles, each ranked in his role's pool */
$tile = function ( $lab, $v, $sub, $rk = null, $n = null ) {
	return '<div class="dbd-tile"><div class="l">' . esc_html( $lab ) . '</div><div class="v">' . esc_html( (string) $v ) . '</div><div class="s">' . esc_html( $sub ) . '</div>'
		. ( $rk ? '<div class="dbd-trk' . ( $rk <= 10 ? ' top' : '' ) . '"><b>' . esc_html( DBD_Grades::ordinal( (int) $rk ) ) . '</b> of ' . (int) $n . '</div>' : '' ) . '</div>';
};
$tr = function ( $k ) use ( $TR ) { return isset( $TR[ $k ] ) ? array( (int) $TR[ $k ][0], (int) $TR[ $k ][1] ) : array( null, null ); };
$mr = function ( $k ) use ( $R ) { return isset( $R[ $k ][0] ) && $R[ $k ][0] ? array( (int) $R[ $k ][0], (int) $R[ $k ][2] ) : array( null, null ); };
$pg = function ( $k, $dp = 1 ) use ( $s, $g ) { return number_format( (float) ( $s[ $k ] ?? 0 ) / $g, $dp ) . ' a game'; };
$tgt = (float) ( $s['tgt'] ?? 0 );
$T = array(
	'prs' => array( 'Pressures', $n0( $s['prs'] ?? 0 ), $pg( 'prs' ), $tr( 'prs' ) ), 'sk' => array( 'Sacks', $n0( $s['sk'] ?? 0 ), $pg( 'sk', 2 ), $tr( 'sk' ) ),
	'qbh' => array( 'QB hits', $n0( $s['qbh'] ?? 0 ), $pg( 'qbh' ), $tr( 'qbh' ) ), 'tfl' => array( 'Tackles for loss', $n0( $s['tfl'] ?? 0 ), $pg( 'tfl' ), $tr( 'tfl' ) ),
	'tkl' => array( 'Tackles', $n0( $s['tkl'] ?? 0 ), $pg( 'tkl' ), $tr( 'tkl' ) ), 'ff' => array( 'Forced fumbles', $n0( $s['ff'] ?? 0 ), 'and ' . $n0( $s['pd'] ?? 0 ) . ' passes defended', $tr( 'ff' ) ),
	'hur' => array( 'Hurries', $n0( $s['hur'] ?? 0 ), 'rushed the passer off his spot', $tr( 'hur' ) ), 'snap' => array( 'Snap share', (int) round( (float) ( $s['snap'] ?? 0 ) ) . '%', "of the defense's snaps", $mr( 'snap' ) ),
	'tgt' => array( 'Thrown at him', $n0( $tgt ), $pg( 'tgt' ), $tr( 'tgt' ) ), 'cmp' => array( 'Catches allowed', $n0( $s['cmp'] ?? 0 ), $tgt ? round( $s['cmp'] / $tgt * 100 ) . '% of targets' : 'no targets', array( null, null ) ),
	'yds' => array( 'Yards allowed', $n0( $s['yds'] ?? 0 ), $tgt ? number_format( $s['yds'] / $tgt, 1 ) . ' a target' : 'in coverage', $mr( 'ypt' ) ),
	'rate' => array( 'Passer rating', isset( $s['rate'] ) && $s['rate'] !== null ? number_format( (float) $s['rate'], 1 ) : '–', 'when thrown at', $mr( 'rate' ) ),
	'int' => array( 'Interceptions', $n0( $s['int'] ?? 0 ), $n0( $s['tda'] ?? 0 ) . ' TD allowed', $tr( 'int' ) ), 'pd' => array( 'Passes defended', $n0( $s['pd'] ?? 0 ), $pg( 'pd' ), $tr( 'pd' ) ),
);
$TK = array( 'front' => array( 'prs', 'sk', 'qbh', 'tfl', 'tkl', 'ff', 'hur', 'snap' ), 'lb' => array( 'tkl', 'tfl', 'sk', 'prs', 'tgt', 'rate', 'pd', 'snap' ), 'db' => array( 'tgt', 'cmp', 'yds', 'rate', 'int', 'pd', 'tkl', 'snap' ) );
echo '<div class="dbd-tiles">'; foreach ( $TK[ $role ] as $k ) { $x = $T[ $k ]; echo $tile( $x[0], $x[1], $x[2], $q ? $x[3][0] : null, $x[3][1] ); } echo '</div>';

/* ---- the short version */
$W = array(); $WK = (array) $d['_keys']['wk']; foreach ( (array) $d['w'] as $w ) { $W[] = array_combine( $WK, array_slice( array_pad( (array) $w, count( $WK ), 0 ), 0, count( $WK ) ) ); }
$played = array_values( array_filter( $W, function ( $w ) { return (float) $w['sp'] > 0; } ) ); $l3 = array_slice( $played, -3 );
$sum = function ( $rows, $k ) { return array_sum( array_map( function ( $w ) use ( $k ) { return (float) $w[ $k ]; }, $rows ) ); };
$PL = array(); $PK = (array) $d['_keys']['pk']; foreach ( (array) $d['pl'] as $p ) { if ( count( (array) $p ) === count( $PK ) ) { $PL[] = array_combine( $PK, (array) $p ); } }
$kn = array_count_values( array_map( function ( $p ) { return (string) $p['k']; }, $PL ) );
$nx = $arch ? null : DBD_Fantasy::next_game( $a ); $nopp = $nx['opp'] ?? '';
$short = array();
$rk = function ( $k ) use ( $R ) { return $R[ $k ] ?? array( null, null, 0, null, null ); };
if ( $role === 'db' ) {
	$rr = $rk( 'rate' ); $rb = $rk( 'ball' ); $rc = $rk( 'cpa' );
	if ( $q && $rr[0] ) { $short[] = array( '#dbd-good', 'How good', 'Quarterbacks have a <b>' . esc_html( number_format( (float) $s['rate'], 1 ) ) . '</b> passer rating throwing at ' . esc_html( $last ) . ', <b>' . $ord( $rr[0] ) . '</b> of ' . (int) $rr[2] . ' defensive backs (league median ' . esc_html( number_format( (float) $rr[3], 1 ) ) . ').' ); }
	$short[] = array( '#dbd-made', 'On the ball', '<b>' . $n0( $s['int'] ) . '</b> ' . ( (int) $s['int'] === 1 ? 'interception' : 'interceptions' ) . ' and <b>' . $n0( $s['pd'] ) . '</b> passes defended' . ( $q && $rb[0] ? ': ' . number_format( ( $s['int'] + $s['pd'] ) / $g, 1 ) . ' plays on the ball a game, <b>' . $ord( $rb[0] ) . '</b> of ' . (int) $rb[2] . '.' : '.' ) );
	if ( $tgt >= 10 && $rc[3] !== null ) { $short[] = array( '#dbd-good', 'The cost', '<b>' . round( $s['cmp'] / $tgt * 100 ) . '%</b> of throws at him are caught (league median ' . round( (float) $rc[3] * 100 ) . '%), for ' . number_format( $s['yds'] / $tgt, 1 ) . ' yards a target.' ); }
	if ( $l3 ) { $short[] = array( '#dbd-weeks', 'Lately', (int) $sum( $l3, 'cmp' ) . ' catches on ' . (int) $sum( $l3, 'tgt' ) . ' targets for ' . (int) $sum( $l3, 'yds' ) . ' yards over his last ' . ( count( $l3 ) === 1 ? 'game' : count( $l3 ) . ' games' ) . '.' ); }
	if ( $nopp && ( $pr = DBD_Data::rank( 'off', $nopp, 'pass_epa' ) ) ) { $short[] = array( '#dbd-more', 'Next', 'The ' . esc_html( DBD_Data::team( $nopp )['nick'] ?? $nopp ) . ', <b>' . $ord( $pr ) . '</b> in EPA a dropback.' ); }
} else {
	$main = $role === 'front' ? 'prs' : 'tkl'; $mn = $role === 'front' ? 'pressures' : 'tackles'; $rm = $rk( $main );
	if ( $q && $rm[0] ) { $short[] = array( '#dbd-good', 'How good', esc_html( $last ) . '\'s <b>' . number_format( (float) $s[ $main ] / $g, 1 ) . '</b> ' . $mn . ' a game ' . ( $role === 'front' ? 'are' : 'are' ) . ' <b>' . $ord( $rm[0] ) . '</b> of ' . (int) $rm[2] . ' ' . $RN[ $role ][0] . ( isset( $TR['sk'] ) && (float) $s['sk'] > 0 ? ', and his <b>' . $n0( $s['sk'] ) . '</b> sacks <b>' . $ord( $TR['sk'][0] ) . '</b>.' : '.' ) ); }
	$behind = count( array_filter( $PL, function ( $p ) { return in_array( $p['k'], array( 'sack', 'tfl', 'qbh' ), true ) || ( in_array( $p['k'], array( 'tkl', 'ast' ), true ) && (int) $p['y'] <= 0 ); } ) );
	if ( $PL ) { $short[] = array( '#dbd-made', 'Where he wins', '<b>' . $behind . '</b> of his ' . count( $PL ) . ' plays end at or behind the line: ' . ( (float) ( $kn['sack'] ?? 0 ) == (float) $s['sk'] ? (int) ( $kn['sack'] ?? 0 ) . ' sacks' : (int) ( $kn['sack'] ?? 0 ) . ' plays with a sack (' . $n0( $s['sk'] ) . ' sacks, a shared one counting half)' ) . ', ' . (int) ( $kn['qbh'] ?? 0 ) . ' more hits on the quarterback, and ' . ( $behind - (int) ( $kn['sack'] ?? 0 ) - (int) ( $kn['qbh'] ?? 0 ) ) . ' stops for a loss or no gain.' ); }
	if ( count( $l3 ) === 3 ) {
		$a3 = $sum( $l3, $main ) / 3; $as = (float) $s[ $main ] / $g;
		$short[] = array( '#dbd-weeks', 'Lately', $a3 > $as * 1.15 ? ucfirst( $mn ) . ' are <b>climbing</b>: ' . number_format( $a3, 1 ) . ' a game over his last three against ' . number_format( $as, 1 ) . ' for the season.' : ( $a3 < $as * 0.85 ? ucfirst( $mn ) . ' have <b>cooled</b>: ' . number_format( $a3, 1 ) . ' a game over his last three against ' . number_format( $as, 1 ) . ' for the season.' : 'Steady: ' . number_format( $a3, 1 ) . ' ' . $mn . ' a game over his last three, ' . number_format( $as, 1 ) . ' for the season.' ) );
	}
	if ( $nopp && $role === 'front' && ( $sr = DBD_Data::rank( 'off', $nopp, 'sack' ) ) ) { $short[] = array( '#dbd-more', 'Next', 'The ' . esc_html( DBD_Data::team( $nopp )['nick'] ?? $nopp ) . ', whose line ranks <b>' . $ord( $sr ) . '</b> in sack rate allowed (1st allows the fewest).' ); }
	elseif ( $nopp && ( $er = DBD_Data::rank( 'off', $nopp, 'epa' ) ) ) { $short[] = array( '#dbd-more', 'Next', 'The ' . esc_html( DBD_Data::team( $nopp )['nick'] ?? $nopp ) . ', <b>' . $ord( $er ) . '</b> in EPA a play on offense.' ); }
}
if ( $short ) { echo '<section class="dbd-panel dbd-short"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b></span></div><ul>' . implode( '', array_map( function ( $x ) { return '<li><a href="' . esc_attr( $x[0] ) . '">' . esc_html( $x[1] ) . '</a><span>' . $x[2] . '</span></li>'; }, $short ) ) . '</ul></section>'; }
echo '<nav class="dbd-jump" aria-label="On this page"><a href="#dbd-good">How good is he</a><a href="#dbd-made">Where he makes plays</a><a href="#dbd-weeks">Week by week</a><a href="#dbd-career">Career</a><a href="#dbd-more">More</a></nav>';

/* ---- how good is he: the Scorecard in his role's pool */
echo '<section class="dbd-band" id="dbd-good"><h2 class="dbd-bandh">How good is he<span>his line against the league\'s ' . esc_html( $RN[ $role ][0] ) . '</span></h2>';
$KEYS = array( 'front' => array( 'prs', 'sk', 'qbh', 'tfl', 'fin', 'tkl', 'mtp', 'snap' ), 'lb' => array( 'tkl', 'tfl', 'prs', 'ypt', 'rate', 'mtp', 'snap' ), 'db' => array( 'rate', 'ypt', 'cpa', 'ball', 'tkl', 'mtp', 'snap' ) );
$sc = ''; $head = '<table class="dbd-stat"><thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead><tbody>'; $i = 0; $keys = $KEYS[ $role ];
foreach ( $keys as $k ) {
	if ( $i++ === (int) ceil( count( $keys ) / 2 ) ) { $sc .= '</tbody></table>' . $head; }
	$M = $MET[ $k ]; $x = $R[ $k ] ?? array( null, null, 0, null, null ); $v = $x[4] ?? null; $rk0 = $x[0] ?? null; $p = $x[1] ?? null; $n = (int) ( $x[2] ?? 0 );
	$vs = $fmt( $v, $M[2] ); $low = $M[3] ? ' · lower is better' : '';
	$tip = '<b>' . esc_html( $d['name'] ) . '</b><br>' . esc_html( $M[0] ) . ': <b>' . esc_html( $vs ) . '</b>' . ( $p !== null ? ' · ' . $ord( $p ) . ' percentile · ' . $ord( $rk0 ) . ' of ' . $n : '' ) . '<br><span class="m">' . esc_html( 'League median ' . $fmt( $x[3] ?? null, $M[2] ) . ' · ' . $M[1] . $low ) . '</span>';
	$sc .= '<tr' . ( $p !== null && $p >= 90 ? ' class="top"' : '' ) . '><td class="n">' . esc_html( $M[0] ) . '<small>' . esc_html( $M[1] . $low ) . '</small></td><td class="v">' . esc_html( $vs ) . '</td><td class="rk">' . ( $rk0 ? '<b>' . $ord( $rk0 ) . '</b> of ' . $n : '–' ) . '</td>'
		. '<td class="b"><div class="bar">' . ( $p !== null ? '<i style="--p:' . (int) $p . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $M[0] . ' ' . $vs . ', ' . $ord( $p ) . ' percentile' ) . '"></i>' : '' ) . '</div></td><td class="g' . ( $p !== null && $p >= 75 ? ' hi' : ( $p !== null && $p <= 25 ? ' lo' : '' ) ) . '">' . ( $p !== null ? $ord( $p ) : '–' ) . '</td></tr>';
}
$why = $role === 'db' ? 'Coverage figures need 10 targets or more.' : 'Linebackers who rush the passer more than they cover count with the front, as on the defense tab.';
echo '<section class="dbd-panel dbd-score"><div class="dbd-ph"><span class="dbd-lab"><b>Scorecard</b> · ' . esc_html( $q ? 'among ' . $pool . ' ' . $RN[ $role ][0] . ' with a regular role' : 'not ranked: a regular role is half his team\'s games, on 40% of the snaps in his games' ) . '</span><span class="dbd-badge">League percentiles</span></div>'
	. '<div class="dbd-score2">' . $head . $sc . '</tbody></table></div>'
	. '<p class="dbd-note">A regular role: half his team\'s games or more, on 40% or more of the defense\'s snaps in the games he played. ' . esc_html( $why ) . ' Pressures, hurries, targets and missed tackles are Pro Football Reference\'s charting; tackles, sacks, hits and takeaways are the play-by-play\'s.</p></section></section>';

/* ---- where he makes plays: every play on the field (assets/defchart.js), then the biggest */
$KIND = array( 'tkl' => 'Tackle', 'ast' => 'Shared tackle', 'tfl' => 'Tackle for loss', 'sack' => 'Sack', 'qbh' => 'QB hit', 'pd' => 'Pass defended', 'int' => 'Interception' );
$wkTeam = array(); foreach ( $W as $w ) { $wkTeam[ (int) $w['week'] ] = (string) ( $w['team'] ?: $a ); }
$glab = function ( $wk, $tm = null ) use ( $wkTeam, $a ) { $tm = $tm ?: ( $wkTeam[ $wk ] ?? $a ); $gg = DBD_Data::team_game( $tm, $wk ); if ( ! $gg ) { return array( 'Bye', '', null ); } $home = $gg['home'] === $tm; return array( ( $home ? 'vs ' : 'at ' ) . ( $home ? $gg['away'] : $gg['home'] ), $home, $gg ); };
$JS = array();
foreach ( $PL as $p ) {
	$y = (int) $p['y']; $how = in_array( $p['k'], array( 'pd', 'int', 'qbh' ), true ) ? ( $p['air'] !== null ? 'on a throw ' . (int) $p['air'] . ' yards downfield' : 'on the throw' ) : ( $y > 0 ? 'after a ' . $y . '-yard gain' : ( $y === 0 ? 'for no gain' : 'for a loss of ' . -$y ) );
	$dn = $p['dn'] ? $ord( $p['dn'] ) . ' & ' . (int) $p['togo'] : 'no down';
	$JS[] = array( 'k' => $p['k'], 'l' => (int) $p['lane'], 'y' => $y, 'a' => $p['air'] === null ? null : (int) $p['air'], 'f' => (int) $p['ff'], 'e' => (float) $p['epa'],
		'h' => ( $KIND[ $p['k'] ] ?? 'Play' ) . ' ' . $how . ( $p['ff'] ? ' · forced fumble' : '' ), 'c' => 'Week ' . (int) $p['week'] . ' ' . $glab( (int) $p['week'] )[0] . ' · Q' . (int) $p['q'] . ' ' . $p['time'] . ' · ' . $dn, 'd' => (string) $p['desc'] );
}
echo '<section class="dbd-band" id="dbd-made"><h2 class="dbd-bandh">Where he makes plays<span>every tackle, sack, hit and pass he got a hand on</span></h2>';
if ( $JS ) {
	$wks = array_map( function ( $w ) { return (int) $w['week']; }, $played );
	echo '<section class="dbd-panel dbd-dfc" data-name="' . esc_attr( $d['name'] ) . '"><div class="dbd-ph"><span class="dbd-lab"><b>Every play he made</b> · ' . count( $JS ) . ' plays' . ( $wks ? ' · Weeks ' . min( $wks ) . ' to ' . max( $wks ) : '' ) . '</span></div><div class="dbd-dfc-b">'
		. '<script type="application/json" class="dbd-dfc-data">' . str_replace( '</', '<\/', (string) wp_json_encode( $JS ) ) . '</script><div class="dbd-dfc-keys"></div><div class="dbd-dfc-fld"></div>'
		. '<div class="dbd-thr-now is-empty">Hover or tap any play to see it.</div>'
		. '<p class="dbd-note">Each play sits in the lane the play data names (a run\'s gap, or a throw\'s left, middle or right; not the exact spot, so dots spread across their lane) at the yard line where it ended: where he made the tackle, where the sack dropped the quarterback, or how far the throw he broke up or caught was going. A hit on the quarterback that wasn\'t a sack sits behind the line. A green ring is a forced fumble.</p></div></section>';
	$big = $PL; usort( $big, function ( $x, $y ) { return (float) $x['epa'] <=> (float) $y['epa']; } ); $big = array_slice( $big, 0, 6 );
	echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>His biggest plays</b> · by the EPA they took from the offense</span></div><div class="dbd-tw"><table class="dbd-stat dbd-dfbig"><thead><tr><th>Wk</th><th>Game</th><th>Play</th><th class="dbd-t2">What happened</th><th>EPA</th></tr></thead><tbody>';
	foreach ( $big as $p ) { echo '<tr><td>' . (int) $p['week'] . '</td><td class="res">' . esc_html( $glab( (int) $p['week'] )[0] ) . '</td><td>' . esc_html( ( $KIND[ $p['k'] ] ?? '' ) . ( $p['ff'] ? ' · forced fumble' : '' ) ) . '</td><td class="dbd-t2 dbd-dfd">' . esc_html( (string) $p['desc'] ) . '</td><td class="' . ( (float) $p['epa'] <= 0 ? 'neg' : 'pos' ) . '">' . sprintf( '%+.1f', (float) $p['epa'] ) . '</td></tr>'; }
	echo '</tbody></table></div></section>';
} else {
	echo '<section class="dbd-panel"><p class="dbd-note">No plays credited to him in the play-by-play yet.</p></section>';
}
echo '</section>';

/* ---- week by week: the game log, all 18 weeks */
$cols = $role === 'db' ? array( array( 'Tkl', 'tkl', 'dbd-t3' ), array( 'Tgt', 'tgt', '' ), array( 'Cmp', 'cmp', '' ), array( 'Yds', 'yds', '' ), array( 'TD', 'tda', 'dbd-t2' ), array( 'Rate', 'rate', 'dbd-t2' ), array( 'INT', 'int', '' ), array( 'PD', 'pd', 'dbd-t3' ) )
	: ( $role === 'lb' ? array( array( 'Tkl', 'tkl', '' ), array( 'TFL', 'tfl', 'dbd-t2' ), array( 'Sk', 'sk', '' ), array( 'Prs', 'prs', '' ), array( 'Tgt', 'tgt', 'dbd-t2' ), array( 'Yds', 'yds', 'dbd-t3' ), array( 'INT', 'int', 'dbd-t3' ), array( 'PD', 'pd', '' ) )
	: array( array( 'Tkl', 'tkl', '' ), array( 'TFL', 'tfl', 'dbd-t2' ), array( 'Sk', 'sk', '' ), array( 'QB hits', 'qbh', 'dbd-t2' ), array( 'Prs', 'prs', '' ), array( 'Hur', 'hur', 'dbd-t3' ), array( 'PD', 'pd', 'dbd-t3' ), array( 'FF', 'ff', 'dbd-t2' ) ) );
$byW = array(); foreach ( $W as $w ) { $byW[ (int) $w['week'] ] = $w; }
$rating = function ( $w ) { $att = (float) $w['tgt']; if ( ! $att ) { return null; } $c = function ( $x ) { return max( 0.0, min( 2.375, $x ) ); };
	return round( ( $c( ( $w['cmp'] / $att - .3 ) * 5 ) + $c( ( $w['yds'] / $att - 3 ) * .25 ) + $c( $w['tda'] / $att * 20 ) + $c( 2.375 - $w['int'] / $att * 25 ) ) / 6 * 100, 1 ); };
$blank = str_repeat( '<td></td>', count( $cols ) ); $tr2 = '';
for ( $wk = 1; $wk <= 18; $wk++ ) {
	$w = $byW[ $wk ] ?? null; list( $lab, $home, $gg ) = $glab( $wk, $w ? (string) $w['team'] : null );
	if ( ! $gg ) { $tr2 .= '<tr class="dbd-gx"><td>' . $wk . '</td><td class="res">Bye</td><td class="dbd-t2"></td>' . $blank . '</tr>'; continue; }
	if ( ! DBD_Router::is_final( $gg ) ) { $tr2 .= '<tr class="dbd-gx up"><td>' . $wk . '</td><td class="res">' . esc_html( $lab ) . '<span class="why">' . ( $nx && (int) $nx['wk'] === $wk ? 'Next game' : 'Ahead' ) . '</span></td><td class="dbd-t2"></td>' . $blank . '</tr>'; continue; }
	$tm = $w ? (string) $w['team'] : $a; $us = $home ? (int) $gg['home_score'] : (int) $gg['away_score']; $them = $home ? (int) $gg['away_score'] : (int) $gg['home_score'];
	$res = '<b class="' . ( $us > $them ? '' : 'l' ) . '">' . ( $us > $them ? 'W' : ( $us < $them ? 'L' : 'T' ) ) . '</b> ' . $us . '-' . $them;
	if ( ! $w && in_array( $wk, (array) ( $d['pend'] ?? array() ), true ) ) { $tr2 .= '<tr class="dbd-gx up"><td>' . $wk . '</td><td class="res">' . esc_html( $lab ) . ' ' . $res . '<span class="why">Stats not posted yet</span></td><td class="dbd-t2"></td>' . $blank . '</tr>'; continue; }
	if ( ! $w || ! (float) $w['sp'] ) { $tr2 .= '<tr class="dbd-gx dnp"><td>' . $wk . '</td><td class="res">' . esc_html( $lab ) . ' ' . $res . '<span class="why">Did not play</span></td><td class="dbd-t2"></td>' . $blank . '</tr>'; continue; }
	$cells = ''; foreach ( $cols as $c ) { $v = $c[1] === 'rate' ? $rating( $w ) : (float) $w[ $c[1] ]; $cells .= '<td class="' . $c[2] . '">' . ( $c[1] === 'rate' ? ( $v === null ? '–' : number_format( $v, 1 ) ) : ( $v ? $n0( $v ) : '<span class="z">0</span>' ) ) . '</td>'; }
	$tr2 .= '<tr><td>' . $wk . '</td><td class="res">' . esc_html( $lab ) . ' ' . $res . '</td><td class="dbd-t2">' . (int) round( (float) $w['sp'] * 100 ) . '%</td>' . $cells . '</tr>';
}
echo '<section class="dbd-band" id="dbd-weeks"><h2 class="dbd-bandh">Week by week<span>the game log, all 18 weeks</span></h2><section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Game log</b> · all 18 weeks</span></div>'
	. '<div class="dbd-tw"><table class="dbd-glog dbd-stat"><thead><tr><th>Wk</th><th>Opponent</th><th class="dbd-t2">Snap</th>' . implode( '', array_map( function ( $c ) { return '<th class="' . $c[2] . '">' . esc_html( $c[0] ) . '</th>'; }, $cols ) ) . '</tr></thead><tbody>' . $tr2 . '</tbody></table></div></section></section>';

/* ---- career: season by season */
$CK = (array) $d['_keys']['car']; $car = array(); foreach ( (array) ( $d['car'] ?? array() ) as $c ) { if ( count( (array) $c ) === count( $CK ) ) { $car[] = array_combine( $CK, (array) $c ); } }
if ( $car ) {
	$tot = array( 'g' => 0, 'tkl' => 0, 'tfl' => 0, 'sk' => 0, 'qbh' => 0, 'int' => 0, 'pd' => 0, 'ff' => 0 ); foreach ( $car as $c ) { foreach ( $tot as $k => $v ) { $tot[ $k ] += (float) $c[ $k ]; } }
	$crow = function ( $c, $cls = '' ) use ( $n0 ) { return '<tr' . $cls . '><td>' . esc_html( (string) $c['season'] ) . '</td><td>' . esc_html( (string) $c['team'] ) . '</td><td>' . (int) $c['g'] . '</td><td>' . $n0( $c['tkl'] ) . '</td><td class="dbd-t2">' . $n0( $c['tfl'] ) . '</td><td>' . $n0( $c['sk'] ) . '</td><td class="dbd-t2">' . $n0( $c['qbh'] ) . '</td><td>' . $n0( $c['int'] ) . '</td><td>' . $n0( $c['pd'] ) . '</td><td class="dbd-t3">' . $n0( $c['ff'] ) . '</td></tr>'; };
	echo '<section class="dbd-band" id="dbd-career"><h2 class="dbd-bandh">Career<span>season by season, back to his rookie year</span></h2><section class="dbd-panel dbd-career"><div class="dbd-ph"><span class="dbd-lab"><b>Career</b> · regular season</span><span class="dbd-badge">' . count( $car ) . ' ' . ( count( $car ) === 1 ? 'season' : 'seasons' ) . '</span></div>'
		. '<div class="dbd-tw"><table class="dbd-stat"><thead><tr><th>Season</th><th>Team</th><th>G</th><th>Tkl</th><th class="dbd-t2">TFL</th><th>Sk</th><th class="dbd-t2">QB hits</th><th>INT</th><th>PD</th><th class="dbd-t3">FF</th></tr></thead><tbody>'
		. implode( '', array_map( $crow, array_reverse( $car ) ) ) . ( count( $car ) > 1 ? $crow( array( 'season' => 'Total', 'team' => '' ) + $tot, ' class="dbd-crtot"' ) : '' ) . '</tbody></table></div>'
		. '<p class="dbd-note">Season lines from nflverse\'s weekly stats, regular season only; this season through Week ' . (int) DBD_Data::through_week() . '.</p></section></section>';
}

/* ---- more: his unit, and where to go next */
$unit = array_values( array_filter( DBD_Data::defender_index(), function ( $x ) use ( $a, $d ) { return ( $x['team'] ?? '' ) === $a && ( $x['id'] ?? '' ) !== $d['id']; } ) );
usort( $unit, function ( $x, $y ) { return (int) $y['snap'] <=> (int) $x['snap']; } ); $unit = array_slice( $unit, 0, 10 );
echo '<section class="dbd-band" id="dbd-more"><h2 class="dbd-bandh">More<span>his unit, and where to go next</span></h2>';
if ( $unit ) { echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>The ' . esc_html( $nick ) . ' defense</b> · by snap share</span></div><div class="dbd-pb dbd-pills">' . implode( '', array_map( function ( $x ) { return '<a class="dbd-pill" href="' . esc_url( DBD_Router::url( array( 'player' => $x['id'] ) ) ) . '">' . esc_html( $x['name'] ) . ' <small>' . esc_html( $x['pos'] . ' · ' . (int) $x['snap'] . '%' ) . '</small></a>'; }, $unit ) ) . '</div></section>'; }
$rel = array();
if ( $nx && ( $ng = DBD_Data::team_game( $a, (int) $nx['wk'] ) ) ) { $rel[] = array( DBD_Router::url( $ng['home'] === $a ? array( 'game' => $ng['id'], 'side' => 'home' ) : array( 'game' => $ng['id'] ) ), 'Next game: ' . ( $ng['home'] === $a ? 'vs ' : 'at ' ) . ( DBD_Data::team( $nx['opp'] )['nick'] ?? $nx['opp'] ) . ', Week ' . (int) $nx['wk'] ); }
$rel[] = array( $turl, $nick . ' defense' );
$rel[] = array( $role === 'front' ? $rush : DBD_Router::url( array( 'view' => 'leaders', 'g' => 'def' ) ), $role === 'front' ? 'Leaders: pass rushers' : 'Leaders: defenses' );
if ( is_array( $d['draft'] ?? null ) ) { $rel[] = array( DBD_Router::url( array( 'view' => 'draft', 'y' => (int) $d['draft'][2] ) ), 'The ' . (int) $d['draft'][2] . ' draft class' ); }
echo '<section class="dbd-panel dbd-related"><div class="dbd-ph"><span class="dbd-lab"><b>Related</b></span></div><div class="dbd-pb dbd-pills">' . implode( '', array_map( function ( $x ) { return '<a class="dbd-pill" href="' . esc_url( $x[0] ) . '">' . esc_html( $x[1] ) . '</a>'; }, $rel ) ) . '</div></section></section>';
echo DBD_Signup::strip( 'player' ) . '</main>';
