<?php
/**
 * Kicker page (SCOPE §47): the player route for a K. Everything is in players/kickers.json, so the page is all PHP:
 * tiles, the short version, the Scorecard, by distance, every kick on the field, the kicks that mattered, the game
 * log with conditions, and the career.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $row = $r['player']; $k = DBD_Kicker::get( $row['id'] );
if ( ! $k ) { include DBD_PATH . 'includes/views/notfound.php'; return; }
$a = $k['team']; $t = DBD_Data::team( $a ) ?: array( 'nick' => $a, 'name' => $a ); $season = DBD_Data::season(); $through = (int) DBD_Data::through_week();
$arch = DBD_Data::archived(); $N = DBD_Kicker::n(); $q = ! empty( $k['q'] );
$parts = explode( ' ', (string) $k['name'], 2 ); $first = $parts[0]; $last = $parts[1] ?? ''; $nick = $t['nick'] ?? $a;
$f1 = function ( $v ) { return number_format( (float) $v, 1 ); };
$pct = function ( $m, $n ) { return $n ? round( $m / $n * 100 ) . '%' : '–'; };
$f50 = DBD_Kicker::val( $k, 'f50' ); $f50a = DBD_Kicker::val( $k, 'f50a' ); $pat = (array) ( $k['pat'] ?? array( 0, 0 ) ); $ko = (array) ( $k['ko'] ?? array( 0, 0, null, 0 ) );
$games = (array) ( $k['games'] ?? array() ); $kicks = (array) ( $k['fg'] ?? array() ); $g = max( 1, (int) $k['g'] );
$turl = DBD_Router::url( array( 'team' => $a ) ); $purl = DBD_Router::url( array( 'view' => 'players' ) ); $tpurl = DBD_Router::url( array( 'view' => 'players', 'team' => $a ) );
$depth = ! $arch && ! empty( $k['depth'] ) ? 'K' . (int) $k['depth']['rank'] : '';
$inj = $arch ? null : ( $k['inj'] ?? null ); $st = $inj['status'] ?? ''; $icls = preg_match( '/out|did not/i', $st ) ? 'bad' : ( preg_match( '/limited|questionable|doubtful/i', $st ) ? '' : 'good' );

echo DBD_Router::topbar( array( 'players' => $purl, $nick => $tpurl, $k['name'] => null ) );
echo '<main class="dbd-content dbd-player dbd-kicker" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">';

/* ---- hero */
echo '<div class="dbd-ttitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · K · #" . ( $k['num'] ?? '' ) . ' · ' . DBD_Grades::ordinal( (int) ( $k['exp'] ?? 0 ) + 1 ) . ' season' ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::jersey( $a, $k['num'] ?? '', 58 ) . '<span>' . esc_html( $first ) . ' <em>' . esc_html( $last ) . '</em></span></h1>';
$bio = array_filter( array( ! empty( $k['age'] ) ? 'Age ' . (int) $k['age'] : '', $k['ht'] ?? '', ! empty( $k['wt'] ) ? (int) $k['wt'] . ' lb' : '', $k['col'] ?? '', $k['draft'] ?? '' ) );
$role = $depth === 'K1' ? 'The ' . $nick . '\' kicker on the depth chart' : ( $depth ? 'Listed <b>' . esc_html( $depth ) . '</b> on the ' . esc_html( $nick ) . '\' depth chart' : '' );
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">' . esc_html( implode( ' · ', $bio ) ) . ( $bio ? '. ' : '' ) . ( $role ? ( $depth === 'K1' ? esc_html( $role ) : $role ) . ', ' : '' ) . (int) $k['g'] . ' ' . ( (int) $k['g'] === 1 ? 'game' : 'games' ) . ' this season.</p>';
echo '<div class="dbd-pills">' . ( $arch ? '' : ( $inj ? '<span class="dbd-pill ' . $icls . '">Week ' . (int) $inj['week'] . ' report · ' . esc_html( (string) $inj['what'] ) . ' · ' . esc_html( str_ireplace( ' in practice', '', $st ) ) . '</span>' : '<span class="dbd-pill good">No injury listing</span>' ) ) . '<span class="dbd-pill">K</span><a class="dbd-pill" href="' . esc_url( $tpurl ) . '">' . esc_html( $nick ) . ' players</a></div>';
$cmp = function ( $ids ) { return DBD_Router::url( array( 'compare' => implode( ',', $ids ) ) ); };
$sug = $arch ? array() : DBD_Kicker::suggest( $k, 3 );
echo '</div>' . DBD_Marks::hero_photo( $a, $k['photo'] ?? null ) . '<div class="dbd-hdr dbd-hcard"><a class="dbd-pn-link" href="' . esc_url( $turl ) . '">' . DBD_Marks::pennant( $a, 40 ) . '<span class="t"><b>' . esc_html( $nick ) . ' special teams</b><small>' . esc_html( (int) $k['fga'] . ' field goal ' . ( (int) $k['fga'] === 1 ? 'try' : 'tries' ) . ', ' . (int) $ko[0] . ' kickoffs' ) . '</small></span></a>';
echo '<div class="acts"><a class="dbd-cmp-btn" href="' . esc_url( $cmp( array( $k['id'] ) ) ) . '">Compare</a>'
	. ( DBD_Card::available() ? '<a class="dbd-share" href="' . esc_url( DBD_Card::url( $k['id'] ) ) . '" target="_blank" rel="noopener" data-share-title="' . esc_attr( $k['name'] . ' | Down by Down' ) . '" data-tip="' . esc_attr( 'A 1200 × 630 image of this page for social' ) . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg>Share card</a>' : '' ) . '</div>';
if ( ! $arch ) { echo '<button class="dbd-mtbtn" type="button" data-mtid="' . esc_attr( $k['id'] ) . '" data-mtname="' . esc_attr( $k['name'] ) . '" aria-pressed="false"><span class="off">+ My team</span><span class="on">✓ On My team</span></button><a class="dbd-mtopen" href="' . esc_url( DBD_Router::url( array( 'view' => 'myteam' ) ) ) . '">Open My team →</a>'; }
if ( $sug ) { echo '<div class="dbd-cmp-sug"><span>Kickers like him</span>' . implode( '', array_map( function ( $o ) use ( $cmp, $k ) { return '<a class="r" href="' . esc_url( $cmp( array( $k['id'], $o['id'] ) ) ) . '" title="' . esc_attr( 'Compare with ' . $o['name'] ) . '">' . DBD_Marks::tile( $o['team'], 16 ) . '<b>' . esc_html( $o['name'] ) . '</b><i>Compare →</i></a>'; }, $sug ) ) . '</div>'; }
echo '</div></div>';

/* ---- tiles, ranked among qualifying kickers */
$trk = function ( $key, $what ) use ( $k, $N ) {
	$rk = DBD_Kicker::rank( $k, $key ); if ( $rk === '' || ! $N ) { return ''; }
	return '<div class="dbd-trk' . ( DBD_Kicker::rank_n( $k, $key ) <= 5 ? ' top' : '' ) . '" data-tip="' . esc_attr( '<b>' . $rk . '</b> of ' . $N . ' qualifying kickers in ' . $what ) . '" tabindex="0"><b>' . esc_html( $rk ) . '</b> of ' . $N . '</div>';
};
$tiles = array(
	array( 'Field goals', (int) $k['fgm'] . '/' . (int) $k['fga'], $pct( $k['fgm'], $k['fga'] ) . ' made', 'fgpct', 'field goal percentage' ),
	array( 'Over expected', DBD_Kicker::fmt( 'sgn1', $k['oe'] ?? null ), 'makes, for his distances', 'oe', 'makes over expected' ),
	array( 'From 50+', $f50 . '/' . $f50a, 'long of ' . (int) $k['lng'], 'f50', 'field goals from 50+' ),
	array( 'Avg try', $k['avgd'] !== null ? $f1( $k['avgd'] ) : '–', 'yards, how far he is asked', 'avgd', 'average try' ),
	array( 'Extra points', (int) $pat[0] . '/' . (int) $pat[1], $pct( $pat[0], $pat[1] ) . ' made', 'xppct', 'extra point percentage' ),
	array( 'Points', (int) $k['pts'], $f1( $k['pts'] / $g ) . ' a game', 'pts', 'points' ),
	array( 'Kickoffs', $ko[2] !== null ? 'own ' . round( (float) $ko[2] ) : '–', 'where the other team starts', 'kstart', 'the other team\'s start after kickoffs' ),
	array( 'Fantasy', $f1( $k['fx'] / $g ), 'points a game', 'fxpg', 'fantasy points a game' ),
);
echo '<div class="dbd-tiles">';
foreach ( $tiles as $x ) { echo '<div class="dbd-tile"><div class="l">' . esc_html( $x[0] ) . '</div><div class="v">' . esc_html( (string) $x[1] ) . '</div><div class="s">' . esc_html( $x[2] ) . '</div>' . $trk( $x[3], $x[4] ) . '</div>'; }
echo '</div>';

/* ---- the short version */
$inside = array_filter( $kicks, function ( $x ) { return (int) $x[2] < 55; } ); $inM = count( array_filter( $inside, function ( $x ) { return $x[3] === 'm'; } ) );
$late = array_filter( $kicks, function ( $x ) { return (int) $x[4] >= 4 && abs( (int) $x[6] ) <= 8; } ); $lateM = count( array_filter( $late, function ( $x ) { return $x[3] === 'm'; } ) );
$miss = array_values( array_filter( $kicks, function ( $x ) { return $x[3] !== 'm'; } ) ); usort( $miss, function ( $x, $y ) { return (int) $x[2] <=> (int) $y[2]; } );
$short = array();
if ( $kicks ) {
	$short[] = array( '#dbd-good', 'How good', '<b>' . esc_html( DBD_Kicker::fmt( 'sgn1', $k['oe'] ?? 0 ) ) . '</b> makes over what an average kicker would make from his distances' . ( $q && DBD_Kicker::rank( $k, 'oe' ) ? ', <b>' . esc_html( DBD_Kicker::rank( $k, 'oe' ) ) . '</b> of ' . $N : '' ) . '.' );
	if ( ! $miss ) { $short[] = array( '#dbd-kicks', 'Range', 'Perfect: <b>' . count( $kicks ) . ' of ' . count( $kicks ) . '</b>, the longest from ' . (int) $k['lng'] . '.' ); }
	else {
		$mtxt = array_map( function ( $x ) { return (int) $x[2] . ( $x[3] === 'b' ? ' (blocked)' : '' ); }, $miss );
		$short[] = array( '#dbd-kicks', 'Range', ( $inside && $inM === count( $inside ) ? 'Perfect inside 55 (<b>' . $inM . ' of ' . count( $inside ) . '</b>). ' : '' ) . ( count( $miss ) === 1 ? 'His miss came from ' : 'His misses came from ' ) . esc_html( count( $mtxt ) > 1 ? implode( ', ', array_slice( $mtxt, 0, -1 ) ) . ' and ' . end( $mtxt ) : $mtxt[0] ) . '.' );
	}
	if ( $late ) { $short[] = array( '#dbd-kicks', 'Under pressure', 'In the fourth quarter or overtime of a one-score game: <b>' . $lateM . ' of ' . count( $late ) . '</b>.' ); }
}
if ( ! empty( $k['fan']['rk']['half']['ppg'] ) ) { $short[] = array( '#dbd-weeks', 'Fantasy', '<b>' . esc_html( $f1( $k['fx'] / $g ) ) . '</b> points a game, <b>K' . (int) $k['fan']['rk']['half']['ppg'] . '</b> of ' . (int) ( $k['fan']['rk']['half']['n'] ?? 0 ) . '. Scoring is the same in every format.' ); }
/* this week: his team's next game and its roof */
$next = null; $cw = (int) DBD_Data::current_week();
if ( ! $arch ) { foreach ( (array) ( DBD_Data::schedule()['weeks'] ?? array() ) as $wkn => $gl ) { foreach ( (array) $gl as $sg ) { if ( ( $sg['home'] === $a || $sg['away'] === $a ) && (int) ( $sg['week'] ?? $wkn ) >= $cw && ( $sg['status'] ?? '' ) !== 'final' && ( ! $next || (int) $sg['week'] < (int) $next['week'] ) ) { $next = $sg; } } } }
if ( $next ) {
	$home = $next['home'] === $a; $opp = DBD_Data::team( $home ? $next['away'] : $next['home'] )['nick'] ?? ''; $roof = (string) ( $next['roof'] ?? '' );
	$where = in_array( $roof, array( 'dome', 'closed' ), true ) ? ', <b>indoors</b>' . ( ! empty( $next['stadium'] ) ? ' at ' . esc_html( $next['stadium'] ) : '' ) . ': no wind to kick into' : ( $roof === 'outdoors' ? ', <b>outdoors</b>' . ( ! empty( $next['stadium'] ) ? ' at ' . esc_html( $next['stadium'] ) : '' ) : ( $roof === 'open' ? ', the roof open' : '' ) );
	$short[] = array( '#dbd-weeks', 'This week', esc_html( DBD_Router::week_label( (int) $next['week'] ) . ': ' . ( $home ? 'the ' . $opp . ' visit' : 'at the ' . $opp ) . ', ' . ( $next['day'] ?? '' ) ) . $where . '.' );
}
if ( $short ) { echo '<section class="dbd-panel dbd-short"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b></span></div><ul>' . implode( '', array_map( function ( $s ) { return '<li><a href="' . esc_attr( $s[0] ) . '">' . esc_html( $s[1] ) . '</a><span>' . $s[2] . '</span></li>'; }, $short ) ) . '</ul></section>'; }
echo '<nav class="dbd-jump" aria-label="On this page"><a href="#dbd-good">How good is he</a><a href="#dbd-kicks">Every kick</a><a href="#dbd-weeks">Week by week</a><a href="#dbd-career">Career</a></nav>';

/* ---- how good is he: the Scorecard and by distance */
echo '<section class="dbd-band" id="dbd-good"><h2 class="dbd-bandh">How good is he<span>his kicks against the league, distance for distance</span></h2>';
$sc = '';
foreach ( DBD_Kicker::MET as $key => $M ) {
	$v = DBD_Kicker::val( $k, $key ); $vs = $key === 'kstart' ? ( $v === null ? '–' : $f1( $v ) ) : DBD_Kicker::fmt( $M[2], $v ); $rk = DBD_Kicker::rank( $k, $key ); $p = DBD_Kicker::pctile( $k, $key );
	$med = DBD_Kicker::median( $key ); $mtxt = $med === null ? '' : ( $key === 'kstart' ? $f1( $med ) : DBD_Kicker::fmt( $M[2], $med ) );
	$tip = '<b>' . esc_html( $k['name'] ) . '</b><br>' . esc_html( $M[0] ) . ': <b>' . esc_html( $vs ) . '</b>' . ( $p !== null ? ' · ' . DBD_Grades::ordinal( $p ) . ' percentile · ' . esc_html( $rk ) . ' of ' . $N : '' ) . '<br><span class="m">' . esc_html( 'League median ' . $mtxt . ' · ' . $M[1] ) . '</span>';
	$sc .= '<tr' . ( $p !== null && $p >= 80 ? ' class="top"' : '' ) . '><td class="n">' . esc_html( $M[0] ) . '<small>' . esc_html( $M[1] ) . '</small></td><td class="v">' . esc_html( $vs ) . '</td><td class="rk">' . ( $rk !== '' ? '<b>' . esc_html( $rk ) . '</b> of ' . $N : '–' ) . '</td>'
		. '<td class="b"><div class="bar">' . ( $p !== null ? '<i style="--p:' . (int) $p . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $M[0] . ' ' . $vs . ', ' . DBD_Grades::ordinal( $p ) . ' percentile' ) . '"></i>' : '' ) . '</div></td><td class="g' . ( $p !== null && $p >= 80 ? ' hi' : ( $p !== null && $p <= 20 ? ' lo' : '' ) ) . '">' . ( $p !== null ? esc_html( DBD_Grades::ordinal( $p ) ) : '–' ) . '</td></tr>';
}
echo '<section class="dbd-panel dbd-score"><div class="dbd-ph"><span class="dbd-lab"><b>Scorecard</b> · ' . esc_html( $q ? "among $N kickers with a try for every team game" : 'not enough tries yet to rank: a try for every game his team has played' ) . '</span><span class="dbd-badge">League percentiles</span></div><div class="dbd-score2"><table class="dbd-stat"><thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead><tbody>' . $sc . '</tbody></table></div></section>';
$F = DBD_Kicker::file(); $LG = (array) ( $F['league'] ?? array() ); $dr = '';
foreach ( (array) ( $F['ranges'] ?? array() ) as $i => $nm ) {
	$b = $k['by'][ $i ] ?? array( 0, 0, 0 ); list( $bm, $ba, $be ) = array( (int) $b[0], (int) $b[1], (float) $b[2] ); $lm = (int) ( $LG[ $i ][0] ?? 0 ); $la = (int) ( $LG[ $i ][1] ?? 0 ); $d = $bm - $be;
	$dr .= '<tr><td class="n">' . esc_html( $nm ) . '</td><td class="v">' . $bm . '/' . $ba . '</td><td>' . $pct( $bm, $ba ) . '</td><td>' . ( $ba ? round( $be / $ba * 100 ) . '%' : '–' ) . '</td><td>' . $pct( $lm, $la ) . '</td><td class="' . ( $ba && $d > 0.05 ? 'pos' : ( $ba && $d < -0.05 ? 'neg' : '' ) ) . '">' . ( $ba ? ( $d >= 0 ? '+' : '' ) . number_format( $d, 1 ) : '–' ) . '</td></tr>';
}
echo '<section class="dbd-panel kk-dist"><div class="dbd-ph"><span class="dbd-lab"><b>By distance</b> · his makes against what an average kicker makes from the same spots</span></div><div class="dbd-tw"><table class="dbd-stat"><thead><tr><th>Yards</th><th>Made</th><th>His %</th><th>Expected</th><th>League</th><th>Over exp.</th></tr></thead><tbody>' . $dr . '</tbody></table></div>'
	. '<p class="dbd-note">Expected is the chance an average kicker makes each of his tries, from a curve fitted to every field goal try of the three seasons before this one. A block counts as a miss. Over expected is his makes minus those chances added up.</p></section>';
echo '</section>';

/* ---- every kick */
$wks = range( 1, max( 1, $through ) ); if ( $games ) { $wks = range( 1, max( (int) end( $games )[0], min( $through, 18 ) ) ); }
$reach = $k['avgd'] !== null ? $f1( $k['avgd'] ) : null; $avr = DBD_Kicker::rank( $k, 'avgd' );
$read = '<b>' . (int) $k['fgm'] . ' of ' . (int) $k['fga'] . '</b>' . ( $reach ? ', and his tries average <b>' . $reach . ' yards</b>' . ( $avr !== '' ? ', ' . esc_html( $avr ) . '-longest among qualifying kickers' : '' ) : '' ) . '.'
	. ( $miss ? ' ' . ( count( $miss ) === 1 ? 'The one that got away came from ' . (int) $miss[0][2] . '.' : 'The ones that got away came from ' . esc_html( implode( ', ', array_map( function ( $x ) { return (int) $x[2]; }, $miss ) ) ) . '.' ) : '' );
echo '<section class="dbd-band"><h2 class="dbd-bandh">Every kick<span>where each try came from, and the ones that swung a game</span></h2>';
echo '<section class="dbd-panel kk-kicks" id="dbd-kicks"><div class="dbd-ph"><span class="dbd-lab"><b>Every kick</b> · ' . esc_html( (int) $k['fga'] . ' field goal ' . ( (int) $k['fga'] === 1 ? 'try' : 'tries' ) . ( $through ? ', Weeks 1–' . min( $through, end( $wks ) ) : '' ) ) . '</span><span class="dbd-badge">Hover a kick</span></div>';
if ( $kicks ) {
	echo '<p class="kk-read">' . $read . '</p><div class="kk-wrap">' . DBD_Kicker::field_h( $k, $wks ) . DBD_Kicker::field_v( $k, $wks ) . '</div>';
	echo '<div class="kk-legend"><span><i class="m"></i>Good</span><span><i class="x"></i>No good</span><span><i class="b"></i>Blocked</span><span>Each kick sits at the spot it was held, numbered by distance. The yellow line is the uprights.</span></div>';
} else { echo '<p class="kk-read">No field goal tries yet this season.</p>'; }
echo '</section>';
/* the kicks that mattered: his tries that moved his team's win chance most */
$big = array_values( array_filter( $kicks, function ( $x ) { return isset( $x[8] ) && $x[8] !== null; } ) ); usort( $big, function ( $x, $y ) { return abs( (float) $y[8] ) <=> abs( (float) $x[8] ); } ); $big = array_slice( $big, 0, 5 );
if ( $big ) {
	$G = DBD_Kicker::games_by_week( $k ); $col = DBD_Marks::accent( $t ); $li = '';
	foreach ( $big as $i => $x ) {
		$gm = $G[ (int) $x[0] ] ?? null; $opp = $gm ? ( DBD_Data::team( $gm[2] )['nick'] ?? $gm[2] ) : ''; $sd = (int) $x[6]; $sit = $sd === 0 ? 'tied' : ( $sd > 0 ? 'up ' . $sd : 'down ' . -$sd );
		$res = $x[3] === 'm' ? 'good' : ( $x[3] === 'b' ? 'blocked' : 'no good' ); $w0 = round( (float) $x[7] * 100 ); $w1 = round( ( (float) $x[7] + (float) $x[8] ) * 100 ); $up = (float) $x[8] >= 0;
		$li .= '<li><b class="n">' . ( $i + 1 ) . '</b><span class="when">' . ( (int) $x[4] === 5 ? 'OT' : 'Q' . (int) $x[4] ) . ' ' . esc_html( (string) $x[5] ) . '</span><span class="who" style="--c:' . esc_attr( $col ) . '">' . esc_html( $a ) . '</span>'
			. '<span class="what">' . ( $gm ? '<a href="' . esc_url( DBD_Router::url( array( 'game' => $gm[1] ) ) ) . '">Week ' . (int) $x[0] . ' ' . ( $gm[3] ? 'vs' : 'at' ) . ' the ' . esc_html( $opp ) . '</a>: ' : '' ) . '<b>' . (int) $x[2] . ' yards, ' . $res . '</b>, ' . $sit . '</span>'
			. '<span class="wp">' . max( 0, $w0 ) . '% → <b>' . min( 100, max( 0, $w1 ) ) . '%</b></span><span class="bar"><i style="width:' . min( 100, round( abs( (float) $x[8] ) * 160, 1 ) ) . '%;background:' . esc_attr( $up ? $col : '#8a8f99' ) . '"></i><small>' . ( $up ? '+' : '−' ) . abs( round( (float) $x[8] * 100 ) ) . '</small></span></li>';
	}
	echo '<section class="dbd-panel kk-mattered"><div class="dbd-ph"><span class="dbd-lab"><b>The kicks that mattered</b> · ' . esc_html( 'his tries that moved the ' . $nick . '\' chance to win most' ) . '</span></div><ol class="dbd-wpl">' . $li . '</ol><p class="dbd-note">The change in his team\'s chance to win from the snap to the next play, from nflverse\'s model. A made kick can lower it: three points can be worth less than the touchdown chances the drive had.</p></section>';
}
echo '</section>';

/* ---- week by week: the game log with conditions */
echo '<section class="dbd-band" id="dbd-weeks"><h2 class="dbd-bandh">Week by week<span>the game log, with the conditions he kicked in</span></h2>';
if ( $games ) {
	$gl = '';
	foreach ( $games as $gm ) {
		$roof = (string) ( $gm[10] ?? '' ); $cond = in_array( $roof, array( 'dome', 'closed' ), true ) ? 'indoors' : ( $gm[11] !== null && $gm[11] !== '' ? (int) $gm[11] . '°' . ( $gm[12] !== null && $gm[12] !== '' ? ', wind ' . (int) $gm[12] : '' ) : ( $roof === 'open' ? 'roof open' : 'outdoors' ) );
		$gl .= '<tr><td class="nm"><a href="' . esc_url( DBD_Router::url( array( 'game' => $gm[1] ) ) ) . '">Wk ' . (int) $gm[0] . '</a> <small>' . ( $gm[3] ? 'vs ' : 'at ' ) . esc_html( $gm[2] ) . '</small></td><td>' . (int) $gm[4] . '/' . (int) $gm[5] . '</td><td>' . ( (int) $gm[6] ?: '–' ) . '</td><td>' . (int) $gm[7] . '/' . (int) $gm[8] . '</td><td>' . ( 3 * (int) $gm[4] + (int) $gm[7] ) . '</td><td>' . (int) $gm[9] . '</td><td class="kk-cond">' . esc_html( $cond ) . '</td></tr>';
	}
	echo '<section class="dbd-panel kk-log"><div class="dbd-ph"><span class="dbd-lab"><b>Game log</b></span><span class="dbd-badge">Fantasy: ESPN standard</span></div><div class="dbd-tw"><table class="dbd-stat"><thead><tr><th>Game</th><th>FG</th><th>Long</th><th>XP</th><th>Pts</th><th>Fant.</th><th>Conditions</th></tr></thead><tbody>' . $gl . '</tbody></table></div>'
		. '<p class="dbd-note">Fantasy is ESPN standard: 3 points for a field goal up to 39 yards, 4 from 40–49, 5 from 50–59, 6 from 60+, minus 1 for a miss; 1 for an extra point, minus 1 for a miss. The same in every format.</p></section>';
} else { echo '<section class="dbd-panel"><p class="dbd-note">No games yet this season.</p></section>'; }
echo '</section>';

/* ---- career */
$CR = (array) ( $k['career'] ?? array() );
if ( $CR ) {
	$tot = array( 'g' => 0, 'fgm' => 0, 'fga' => 0, 'pm' => 0, 'pa' => 0 ); $lng = 0; $teams = array(); $best = null; $rows = '';
	foreach ( $CR as $c ) {
		foreach ( $tot as $x => $v ) { $tot[ $x ] += (int) ( $c[ $x ] ?? 0 ); } $lng = max( $lng, (int) ( $c['lng'] ?? 0 ) ); if ( ! empty( $c['team'] ) ) { $teams[ $c['team'] ] = 1; }
		if ( (int) $c['season'] !== (int) $season && (int) ( $c['fga'] ?? 0 ) >= 10 && ( ! $best || $c['fgm'] / $c['fga'] > $best['fgm'] / $best['fga'] ) ) { $best = $c; }
		$cur = (int) $c['season'] === (int) $season;
		$rows .= '<tr><td class="nm">' . (int) $c['season'] . ( $cur && ! $arch ? ' <small>so far</small>' : '' ) . ( ! empty( $c['team'] ) && count( $CR ) > 1 ? ' <small>' . esc_html( $c['team'] ) . '</small>' : '' ) . '</td><td>' . (int) $c['g'] . '</td><td>' . (int) $c['fgm'] . '/' . (int) $c['fga'] . '</td><td>' . $pct( $c['fgm'], $c['fga'] ) . '</td><td>' . ( (int) $c['lng'] ?: '–' ) . '</td><td>' . (int) ( $c['f50'][0] ?? 0 ) . '/' . (int) ( $c['f50'][1] ?? 0 ) . '</td><td>' . (int) $c['pm'] . '/' . (int) $c['pa'] . '</td></tr>';
	}
	$y0 = (int) $CR[0]['season']; $y1 = (int) end( $CR )['season']; $ns = count( $CR ); $nt = count( $teams );
	$say = $best ? 'His best year was <b>' . (int) $best['season'] . '</b>, ' . (int) $best['fgm'] . ' of ' . (int) $best['fga'] . ' (' . $pct( $best['fgm'], $best['fga'] ) . ') with a long of ' . (int) $best['lng'] . '.' : '';
	$say .= ( $say ? ' ' : '' ) . 'This season he is ' . (int) $k['fgm'] . ' of ' . (int) $k['fga'] . ' on field goals and ' . (int) $pat[0] . ' of ' . (int) $pat[1] . ' on extra points.';
	echo '<section class="dbd-band" id="dbd-career"><h2 class="dbd-bandh">Career<span>' . esc_html( $ns . ' ' . ( $ns === 1 ? 'season' : 'seasons' ) . ', ' . $nt . ' ' . ( $nt === 1 ? 'team' : 'teams' ) ) . '</span></h2>';
	echo '<section class="dbd-panel dbd-career kk-career"><div class="dbd-ph"><span class="dbd-lab"><b>Career</b> · regular season</span><span class="dbd-badge">' . esc_html( $ns . ' ' . ( $ns === 1 ? 'season' : 'seasons' ) ) . '</span></div>'
		. '<div class="dbd-cr-line"><div><b>' . $tot['g'] . '</b><small>Games</small></div><div><b>' . $tot['fgm'] . '/' . $tot['fga'] . '</b><small>Field goals</small></div><div><b>' . $pct( $tot['fgm'], $tot['fga'] ) . '</b><small>Made</small></div><div><b>' . $lng . '</b><small>Long</small></div><div><b>' . $tot['pm'] . '/' . $tot['pa'] . '</b><small>Extra points</small></div><span class="span">' . esc_html( ( $y0 === $y1 ? (string) $y0 : $y0 . ' to ' . $y1 ) . ' · ' . $ns . ' ' . ( $ns === 1 ? 'season' : 'seasons' ) . ' · ' . $nt . ' ' . ( $nt === 1 ? 'team' : 'teams' ) ) . '</span></div>'
		. '<p class="dbd-cr-say">' . $say . '</p><div class="dbd-tw"><table class="dbd-stat"><thead><tr><th>Season</th><th>G</th><th>FG</th><th>%</th><th>Long</th><th>50+</th><th>XP</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section></section>';
}
echo '</main>';
