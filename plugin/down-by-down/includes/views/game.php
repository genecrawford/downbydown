<?php
/** Matchup: offense O against defense D, with generated takeaways, head to head, pass zones. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r    = DBD_Router::$route;
$g    = $r['game'];
$side = $r['side'];
$AWAY = $g['away']; $HOME = $g['home'];
$O = $side === 'away' ? $AWAY : $HOME; $D = $side === 'away' ? $HOME : $AWAY;
$Ot = DBD_Data::team( $O ); $Dt = DBD_Data::team( $D );
$to = DBD_Marks::accent( $Ot ); $td = DBD_Marks::accent( $Dt );
$final = DBD_Router::is_final( $g );
$k = DBD_Data::kickoff( $g );
$label = DBD_Router::week_label( $g['week'] );
$og = DBD_Metrics::games_played( $O, 'off' ); $dg = DBD_Metrics::games_played( $D, 'def' );
$early = $og && $dg && ( $og < 3 || $dg < 3 );
$cards = DBD_Takeaways::cards( $O, $D );

echo DBD_Router::topbar( array( strtolower( $label ) => DBD_Router::url( array( 'view' => 'matchups', 'week' => $g['week'] ) ), DBD_Data::team( $AWAY )['nick'] . ' at ' . DBD_Data::team( $HOME )['nick'] => null ) );
echo '<main class="dbd-content">';

/* ---- title */
$day = '';
try { $day = ( new DateTime( $g['date'] ) )->format( 'l' ); } catch ( Exception $e ) {}
echo '<div class="dbd-mtitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( $label . ' · ' . $day . ' ' . $k['et'] . ( ! empty( $g['stadium'] ) ? ' · ' . $g['stadium'] : '' ) ) . '</p>';
echo '<div class="dbd-markpair t-stagger-line t-stagger-line--2">' . DBD_Marks::pennant( $O, 40 ) . '<span class="vs">vs</span>' . DBD_Marks::pennant( $D, 40 ) . '</div>';
echo '<h1 class="t-stagger-line t-stagger-line--3"><span class="o" style="--u:' . esc_attr( $to ) . '">' . esc_html( $Ot['nick'] ) . ' offense</span><span class="vs">vs</span><span class="d" style="--u:' . esc_attr( $td ) . '">' . esc_html( $Dt['nick'] ) . ' defense</span></h1>';
if ( $og || $dg ) {
	echo '<p class="dbd-sub t-stagger-line t-stagger-line--4">Season numbers through Week ' . (int) DBD_Data::through_week() . ': ' . (int) $og . ' games for ' . esc_html( $Ot['nick'] ) . ', ' . (int) $dg . ' for ' . esc_html( $Dt['nick'] ) . '. Ranks are out of 32; #1 is the best offense or the stingiest defense.</p>';
}
echo '<div class="dbd-flipseg"><a href="' . esc_url( DBD_Router::url( array( 'game' => $g['id'] ) ) ) . '"' . ( $side === 'away' ? ' aria-current="page"' : '' ) . '>' . esc_html( $AWAY ) . ' offense</a><a href="' . esc_url( DBD_Router::url( array( 'game' => $g['id'], 'side' => 'home' ) ) ) . '"' . ( $side === 'home' ? ' aria-current="page"' : '' ) . '>' . esc_html( $HOME ) . ' offense</a></div>';
echo '</div>';

/* ---- the game box */
echo '<aside class="dbd-panel dbd-gamebox"><div class="dbd-ph"><span class="dbd-lab"><b>The game</b></span><span class="dbd-badge' . ( $final ? ' fin' : '' ) . '">' . ( $final ? 'Final' : 'Upcoming' ) . '</span></div><div class="dbd-pb"><div class="dbd-teams">';
foreach ( array( $AWAY, $HOME ) as $a ) {
	$t = DBD_Data::team( $a ); $sc = $a === $AWAY ? ( $g['away_score'] ?? null ) : ( $g['home_score'] ?? null );
	echo '<div class="dbd-t">' . DBD_Marks::tile( $a, 34 ) . '<b>' . esc_html( $a ) . '</b><span>' . esc_html( $t['nick'] . ' · ' . DBD_Data::record_text( $a ) ) . '</span></div>';
	echo '<div class="dbd-rkline">' . ( $final ? '<b class="dbd-score">' . (int) $sc . '</b>' : 'OFF #' . (int) DBD_Data::rank( 'off', $a ) . ' · DEF #' . (int) DBD_Data::rank( 'def', $a ) ) . '</div>';
}
echo '</div><div class="dbd-line"><span>' . esc_html( DBD_Router::line_text( $g ) ) . '</span><span>' . ( isset( $g['total'] ) ? 'O/U ' . esc_html( $g['total'] ) : '' ) . '</span><span>' . DBD_Router::kickoff_html( $g ) . '</span></div></div></aside>';
echo '</div>';

/* ---- how it played out (SCOPE §30): win probability for a final game, from wp/w{week}.json. Regulation only; overtime shows its scoring plays. */
$WP = $final ? DBD_Data::win_prob( $g ) : null;
if ( $WP && ! empty( $WP['s'] ) ) {
	$wS = $WP['s']; $wOT = ! empty( $WP['ot'] ); $wFin = (int) $WP['fin']; $wRfin = $wOT ? 500 : $wFin; $wOts = (array) ( $WP['otsc'] ?? array() );
	$wEnd = $wOT ? ( $wFin === 500 ? 4200 : max( 3660, ( $wOts ? (int) end( $wOts )[0] : 3600 ) + 60 ) ) : 3600;
	$wHt = DBD_Data::team( $HOME ); $wAt = DBD_Data::team( $AWAY ); $wCh = DBD_Marks::accent( $wHt ); $wCa = DBD_Marks::accent( $wAt );
	$wHs = (int) ( $g['home_score'] ?? 0 ); $wAs = (int) ( $g['away_score'] ?? 0 );
	$wWin = $wFin === 1000 ? $HOME : ( $wFin === 0 ? $AWAY : null ); $wLose = $wWin ? ( $wWin === $HOME ? $AWAY : $HOME ) : null;
	$wFor = function ( $team, $v ) use ( $HOME ) { return $team === $HOME ? (int) $v : 1000 - (int) $v; };  /* a home chance, read for either team */
	$wPct = function ( $v ) { return round( $v / 10 ) . '%'; };
	$wNick = function ( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; };
	$wClock = function ( $el ) use ( $wEnd ) { if ( $el >= 3600 && $wEnd > 3600 ) { $r = 600 - ( $el - 3600 ); return 'OT ' . floor( $r / 60 ) . ':' . sprintf( '%02d', $r % 60 ); } $q = min( 4, (int) floor( $el / 900 ) + 1 ); $r = 900 * $q - $el; return 'Q' . $q . ' ' . floor( $r / 60 ) . ':' . sprintf( '%02d', $r % 60 ); };
	$wX = function ( $el ) use ( $wEnd ) { return round( $el / $wEnd * 100, 2 ); }; $wY = function ( $v ) { return round( ( 1000 - $v ) / 10, 2 ); };
	$wAfter = function ( $i ) use ( $wS, $wRfin ) { return isset( $wS[ $i + 1 ] ) ? (int) $wS[ $i + 1 ][1] : $wRfin; };
	$wScore = function ( $x, $team ) use ( $HOME ) { $m = $team === $HOME ? $x[2] : $x[3]; $o = $team === $HOME ? $x[3] : $x[2]; return $m === $o ? 'tied ' . $m . "\u{2060}–\u{2060}" . $o : ( $m < $o ? 'down ' : 'up ' ) . max( $m, $o ) . "\u{2060}–\u{2060}" . min( $m, $o ); };
	/* the chart: the SVG stretches to the box; labels and markers are HTML in percent */
	$pts = array(); foreach ( $wS as $x ) { $pts[] = $wX( $x[0] ) . ',' . $wY( $x[1] ); } $lastX = $wOT ? $wX( 3600 ) : 100; $pts[] = $lastX . ',' . $wY( $wRfin );
	$line = implode( ' ', $pts ); $area = '0,50 ' . $line . ' ' . $lastX . ',50'; $cid = preg_replace( '/[^A-Za-z0-9_]/', '', $g['id'] );
	$svg = '<svg class="plot" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><defs><clipPath id="wpT-' . $cid . '"><rect x="0" y="0" width="100" height="50"/></clipPath><clipPath id="wpB-' . $cid . '"><rect x="0" y="50" width="100" height="50"/></clipPath></defs>'
		. '<polygon points="' . $area . '" fill="' . esc_attr( $wCh ) . '" fill-opacity=".5" clip-path="url(#wpT-' . $cid . ')"/><polygon points="' . $area . '" fill="' . esc_attr( $wCa ) . '" fill-opacity=".5" clip-path="url(#wpB-' . $cid . ')"/>'
		. '<polyline points="' . $line . '" fill="none" stroke="var(--dbd-ink)" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
	$mk = ''; foreach ( array( 900, 1800, 2700, 3600 ) as $e ) { if ( $e < $wEnd ) { $mk .= '<i class="q" style="left:' . $wX( $e ) . '%"></i>'; } }
	$ql = ''; foreach ( array( array( 450, 'Q1' ), array( 1350, 'Q2' ), array( 2250, 'Q3' ), array( 3150, 'Q4' ) ) as $qq ) { $ql .= '<span style="left:' . $wX( $qq[0] ) . '%">' . $qq[1] . '</span>'; } if ( $wOT ) { $ql .= '<span style="left:' . $wX( 3600 + ( $wEnd - 3600 ) / 2 ) . '%">OT</span>'; }
	$prev = array( 0, 0 ); foreach ( $wS as $x ) { if ( array( $x[2], $x[3] ) !== $prev ) { $prev = array( $x[2], $x[3] ); $mk .= '<i class="sc" style="left:' . $wX( $x[0] ) . '%;top:' . $wY( $x[1] ) . '%" data-tip="' . esc_attr( '<b>' . $wClock( $x[0] ) . '</b> ' . $AWAY . ' ' . $x[3] . ' · ' . $HOME . ' ' . $x[2] ) . '" tabindex="0"></i>'; } }
	$ben = function ( $t ) use ( $HOME, $AWAY ) { return (int) $t[1] >= 0 ? $HOME : $AWAY; };  /* the team a swing helped */
	foreach ( (array) $WP['top'] as $n => $t ) { $b = $ben( $t ); $i = (int) $t[0];
		$mk .= '<b class="sw" style="left:' . $wX( $wS[ $i ][0] ) . '%;top:' . $wY( $wAfter( $i ) ) . '%" data-tip="' . esc_attr( '<b>' . ( $n + 1 ) . '. ' . $wClock( $wS[ $i ][0] ) . '</b> ' . esc_html( $t[3] ) . '<br>' . $b . ' ' . $wPct( $wFor( $b, $wS[ $i ][1] ) ) . ' → ' . $wPct( $wFor( $b, $wAfter( $i ) ) ) ) . '" tabindex="0">' . ( $n + 1 ) . '</b>'; }
	$tp = $WP['tp'] ?? null;
	if ( $tp ) { $i = (int) $tp[0]; $mk .= '<em class="tp" style="left:' . $wX( $wS[ $i ][0] ) . '%;top:' . $wY( $wAfter( $i ) ) . '%" data-tip="' . esc_attr( '<b>Turning point · ' . $wClock( $wS[ $i ][0] ) . '</b> ' . esc_html( $tp[3] ) ) . '" tabindex="0"><span>Turning point</span></em>'; }
	$lo = isset( $WP['lo'] ) && $WP['lo'] !== null && $wWin ? $wS[ (int) $WP['lo'] ] : null;
	if ( $lo ) { $lx = $wX( $lo[0] ); $mk .= '<em class="lo' . ( $wWin === $HOME ? ' dn' : '' ) . ( $lx > 70 ? ' lft' : '' ) . '" style="left:' . $lx . '%;top:' . $wY( $lo[1] ) . '%"><span>' . esc_html( $wWin ) . ' low ' . $wPct( $wFor( $wWin, $lo[1] ) ) . '</span></em>'; }
	if ( $wOT ) {
		$mk .= '<div class="otb" style="left:' . $wX( 3600 ) . '%"><span>Overtime</span></div>';  /* overtime scores are stacked down the band so close ones never overlap */
		foreach ( $wOts as $k => $o ) { $mk .= '<i class="ots" style="left:' . $wX( $o[0] ) . '%;top:' . min( 88, 34 + 18 * $k ) . '%" data-tip="' . esc_attr( '<b>' . $wClock( $o[0] ) . '</b> ' . esc_html( $o[2] ) . '<br>' . $AWAY . ' ' . (int) $o[4] . ' · ' . $HOME . ' ' . (int) $o[3] ) . '" tabindex="0">' . esc_html( (string) $o[1] ) . '</i>'; }
		if ( $wWin ) { $mk .= '<i class="fin' . ( $wWin === $HOME ? ' top' : '' ) . '" style="left:100%"></i>'; }
	}
	$wData = wp_json_encode( array( 'e' => $wEnd, 'h' => $HOME, 'a' => $AWAY, 's' => $wS ) );
	$chart = '<div class="dbd-wpc" data-wp="' . esc_attr( $wData ) . '"><div class="side"><span class="h">' . esc_html( $HOME ) . ' wins</span><span class="m">50%</span><span class="a">' . esc_html( $AWAY ) . ' wins</span></div><div class="area" role="img" aria-label="' . esc_attr( 'Win probability across the game, ' . $HOME . ' on top' ) . '">' . $svg . '<i class="mid"></i><i class="g25"></i><i class="g75"></i>' . $mk . '<i class="cur" hidden></i><div class="read" hidden></div></div><div class="ql">' . $ql . '</div></div>';
	/* chips */
	$fav = (int) $WP['pre'] >= 500 ? $HOME : $AWAY; $favV = $wFor( $fav, $WP['pre'] ); $sp = $WP['spread'] ?? null;
	$big = $WP['top'][0] ?? null; $bigB = $big ? $ben( $big ) : null;
	if ( $wWin ) { $lov = $wFor( $wWin, $lo[1] ); $c2 = array( $wWin . ' low point', $wPct( $lov ), $wClock( $lo[0] ) . ' · ' . $wScore( $lo, $wWin ) ); }
	else { $hi = array( $HOME => null, $AWAY => null ); foreach ( $wS as $x ) { foreach ( array( $HOME, $AWAY ) as $tm ) { if ( ! $hi[ $tm ] || $wFor( $tm, $x[1] ) > $wFor( $tm, $hi[ $tm ][1] ) ) { $hi[ $tm ] = $x; } } }
		$cl = $wFor( $HOME, $hi[ $HOME ][1] ) >= $wFor( $AWAY, $hi[ $AWAY ][1] ) ? $HOME : $AWAY; $c2 = array( 'Closest to winning', $cl . ' ' . $wPct( $wFor( $cl, $hi[ $cl ][1] ) ), $wClock( $hi[ $cl ][0] ) . ' · ' . $wScore( $hi[ $cl ], $cl ) ); }
	$chips = array( array( 'At kickoff', $fav . ' ' . $wPct( $favV ), $sp === null || $sp === '' ? '' : ( (float) $sp == 0 ? 'a pick\'em on the line' : $fav . ' by ' . abs( (float) $sp ) . ' on the line' ) ), $c2,
		array( 'Swings past 50%', (string) (int) $WP['flips'], 'times the favorite changed' ), array( 'Biggest swing', $big ? round( abs( $big[1] ) / 10 ) . ' pts' : '–', $big ? $bigB . ' · ' . $wClock( $wS[ (int) $big[0] ][0] ) : '' ) );
	$chipH = ''; foreach ( $chips as $cc ) { $chipH .= '<div class="c"><small>' . esc_html( $cc[0] ) . '</small><b>' . esc_html( $cc[1] ) . '</b><span>' . esc_html( $cc[2] ) . '</span></div>'; }
	/* the read */
	$say = array();
	if ( $wWin ) {
		$wn = $wNick( $wWin ); $score = max( $wHs, $wAs ) . '–' . min( $wHs, $wAs );
		if ( $lov < 250 ) { $say[] = 'The ' . esc_html( $wn ) . ' won ' . $score . ' from as low as <b>' . $wPct( $lov ) . '</b> (' . $wClock( $lo[0] ) . ', ' . $wScore( $lo, $wWin ) . ').'; }
		elseif ( $lov > 500 ) { $say[] = 'The ' . esc_html( $wn ) . ' won ' . $score . ' and were favored the whole way' . ( $wOT ? ' in regulation' : '' ) . ', never below <b>' . $wPct( $lov ) . '</b>.'; }
		else { $say[] = 'The ' . esc_html( $wn ) . ' won ' . $score . '; their low point' . ( $wOT ? ' in regulation' : '' ) . ' was <b>' . $wPct( $lov ) . '</b> at ' . $wClock( $lo[0] ) . '.'; }
		if ( $fav !== $wWin ) { $say[] = 'The ' . esc_html( $wNick( $fav ) ) . ' had been ' . $wPct( $favV ) . ' favorites at kickoff.'; }
		if ( $tp ) { $i = (int) $tp[0]; $say[] = 'The game turned for good at ' . $wClock( $wS[ $i ][0] ) . ': ' . esc_html( rtrim( $tp[3], '.' ) ) . ' (' . $wPct( $wFor( $wWin, $wS[ $i ][1] ) ) . ' → <b>' . $wPct( $wFor( $wWin, $wAfter( $i ) ) ) . '</b>).'; }
		if ( $wOT ) { $wl = $wOts ? end( $wOts ) : null; $say[] = 'Tied ' . (int) $WP['reg'][0] . '–' . (int) $WP['reg'][1] . ' after regulation, they won it in overtime' . ( $wl ? ' at ' . $wClock( $wl[0] ) . ': ' . esc_html( rtrim( $wl[2], '.' ) ) . '.' : '.' ); }
	} else {
		$peak = function ( $tm ) use ( $hi, $wFor, $wPct, $wClock, $wNick ) { $v = $wFor( $tm, $hi[ $tm ][1] ); return $v <= 500 ? 'the ' . esc_html( $wNick( $tm ) ) . ' never had better than even odds' : 'the ' . esc_html( $wNick( $tm ) ) . ' got as high as <b>' . $wPct( $v ) . '</b> (' . $wClock( $hi[ $tm ][0] ) . ')'; };
		$pk = array( $HOME, $AWAY ); usort( $pk, function ( $x, $y ) use ( $hi, $wFor ) { return $wFor( $y, $hi[ $y ][1] ) <=> $wFor( $x, $hi[ $x ][1] ); } );  /* the closer team first */
		$say[] = 'Neither team won: ' . $wHs . '–' . $wAs . ' after ' . ( $wOT ? 'overtime' : 'regulation' ) . '. ' . ucfirst( $peak( $pk[0] ) ) . ', and ' . $peak( $pk[1] ) . '.';
	}
	if ( $big && ( ! $tp || (int) $big[0] !== (int) $tp[0] ) ) { $say[] = 'The biggest single swing came at ' . $wClock( $wS[ (int) $big[0] ][0] ) . ', ' . round( abs( $big[1] ) / 10 ) . ' points for the ' . esc_html( $wNick( $bigB ) ) . '.'; }
	/* the plays that swung it, and the turning point as a sixth row when it is not in the five */
	$row = function ( $t, $label, $isTp ) use ( $wS, $wAfter, $wFor, $wPct, $wClock, $ben ) { $b = $ben( $t ); $i = (int) $t[0]; $col = DBD_Marks::accent( DBD_Data::team( $b ) );
		return '<li' . ( $isTp ? ' class="tp"' : '' ) . '><b class="n">' . $label . '</b><span class="when">' . $wClock( $wS[ $i ][0] ) . '</span><span class="who" style="--c:' . esc_attr( $col ) . '">' . esc_html( $b ) . '</span><span class="what">' . esc_html( $t[3] ) . ( $isTp ? '<em>turning point</em>' : '' ) . '</span>'
			. '<span class="wp">' . $wPct( $wFor( $b, $wS[ $i ][1] ) ) . ' → <b>' . $wPct( $wFor( $b, $wAfter( $i ) ) ) . '</b></span><span class="bar"><i style="width:' . min( 100, round( abs( $t[1] ) / 6, 1 ) ) . '%;background:' . esc_attr( $col ) . '"></i><small>+' . round( abs( $t[1] ) / 10 ) . '</small></span></li>'; };
	$li = ''; $tpIn = false; foreach ( (array) $WP['top'] as $n => $t ) { $isTp = $tp && (int) $t[0] === (int) $tp[0]; $tpIn = $tpIn || $isTp; $li .= $row( $t, (string) ( $n + 1 ), $isTp ); }
	if ( $tp && ! $tpIn ) { $li .= $row( $tp, '★', true ); }
	$note = 'Win probability is nflverse\'s model, which starts from the betting line and updates before every snap with the score, the clock, down, distance, field position and timeouts. A swing is the change in one team\'s chance across a play, in percentage points. The turning point is the play after which the winner never fell to 50% again.'
		. ( $wOT ? ' The model does not hold together under the current overtime rules, so the chart covers regulation and overtime shows its scoring plays.' : '' );
	echo '<section class="dbd-panel dbd-wp"><div class="dbd-ph"><span class="dbd-lab"><b>How it played out</b> · win probability, snap by snap</span><span class="dbd-badge fin">' . esc_html( 'Final' . ( $wOT ? ' · OT' : '' ) . ' · ' . $AWAY . ' ' . $wAs . ' · ' . $HOME . ' ' . $wHs ) . '</span></div>';
	echo '<p class="dbd-wp-say">' . implode( ' ', $say ) . '</p><div class="dbd-wpchips">' . $chipH . '</div>' . $chart . '<h4 class="dbd-wph">The plays that swung it</h4><ol class="dbd-wpl">' . $li . '</ol><p class="dbd-note">' . $note . '</p></section>';
}

if ( ! $cards ) {
	echo '<div class="dbd-empty"><h2>Numbers appear after Week 1</h2><p>Takeaways, the head-to-head table and the pass zones fill in once both teams have played.</p></div></main>';
	return;
}
if ( $early ) { echo '<div class="dbd-panel dbd-notice"><div class="dbd-pb">Fewer than three games for one or both teams. Ranks are shown, but treat every number as a small sample.</div></div>'; }

/* ---- takeaways */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Takeaways</b> · ' . ( $final ? 'from the season numbers, not this game alone' : 'generated from the numbers' ) . '</span><span class="dbd-badge">' . count( $cards ) . ' reads</span></div><div class="dbd-takes t-stagger">';
$n = 0;
foreach ( $cards as $c ) {
	$n++;
	echo '<div class="dbd-take t-stagger-line t-stagger-line--' . $n . ' ' . esc_attr( $c[1] ) . '"><span class="n">' . sprintf( '%02d', $n ) . '</span><div class="tag">' . esc_html( $c[0] ) . '</div><h3>' . esc_html( $c[2] ) . '</h3><p>' . esc_html( $c[3] ) . '</p>' . ( $early ? '<span class="small">Early season: small samples</span>' : '' ) . '</div>';
}
echo '</div></section>';

/* ---- head to head + zones */
echo '<div class="dbd-mgrid">';
echo '<section class="dbd-panel dbd-span"><div class="dbd-ph"><span class="dbd-lab"><b>Head to head</b> · ' . esc_html( $O ) . ' offense against ' . esc_html( $D ) . ' defense</span></div><div class="dbd-tw dbd-h2hwrap"><table class="dbd-h2h">';
echo '<thead><tr><th>Stat</th><th style="color:' . esc_attr( $to ) . '">' . DBD_Marks::tile( $O, 18 ) . ' ' . esc_html( $O ) . ' off</th><th class="c">Favors <span style="color:' . esc_attr( $to ) . '">' . esc_html( $O ) . '</span> · Favors <span style="color:' . esc_attr( $td ) . '">' . esc_html( $D ) . '</span></th><th style="color:' . esc_attr( $td ) . '">' . DBD_Marks::tile( $D, 18 ) . ' ' . esc_html( $D ) . ' def allows</th><th class="lg">NFL avg</th></tr></thead><tbody>';
foreach ( DBD_Metrics::rows() as $group => $keys ) {
	echo '<tr class="grp"><td colspan="5">' . esc_html( $group ) . '</td></tr>';
	foreach ( $keys as $key => $lbl ) {
		list( $ov, $or ) = DBD_Metrics::off( $O, $key ); list( $dv, $dr ) = DBD_Metrics::def( $D, $key );
		$s = DBD_Metrics::score( $or, $dr ); $w = round( abs( $s - 0.5 ) * 200 ) / 2;
		echo '<tr><td>' . esc_html( $lbl ) . '</td><td class="v">' . esc_html( DBD_Metrics::fmt( $key, $ov ) ) . DBD_Metrics::rank_badge( $or ) . '</td>';
		echo '<td class="divcell"><div class="dbd-diverge" style="--to:' . esc_attr( $to ) . ';--td:' . esc_attr( $td ) . '"><i class="' . ( $s >= 0.5 ? 'o' : 'd' ) . '" style="width:' . $w . '%"></i></div></td>';
		echo '<td class="v">' . esc_html( DBD_Metrics::fmt( $key, $dv ) ) . DBD_Metrics::rank_badge( $dr ) . '</td><td class="lg">' . esc_html( DBD_Metrics::fmt( $key, DBD_Metrics::league( $key ) ) ) . '</td></tr>';
	}
}
echo '</tbody></table></div></section>';

$zone_grid = function ( $abbr, $side, $title ) {
	$z = DBD_Metrics::zones( $abbr, $side );
	$cell = function ( $key ) use ( $z, $side ) {
		$c = $z[ $key ] ?? array( 'n' => 0 );
		$n = (int) ( $c['n'] ?? 0 );
		if ( $n < 5 || ! isset( $c['epa'] ) ) { return '<div class="dbd-z thin"><b>' . $n . '</b><span>too few</span></div>'; }
		$epa = (float) $c['epa']; $col = $epa >= 0 ? 'var(--dbd-good)' : 'var(--dbd-bad)'; $mix = min( 85, (int) round( abs( $epa ) / 0.8 * 85 ) );
		$sub = $side === 'off' ? round( ( $c['share'] ?? 0 ) * 100 ) . '% of throws' : $n . ' throws';
		return '<div class="dbd-z" style="background:color-mix(in srgb,' . $col . ' ' . $mix . '%,var(--dbd-panel))"><b>' . esc_html( DBD_Metrics::fmt( 'epa', $epa ) ) . '</b><span>' . esc_html( $sub ) . '</span></div>';
	};
	return '<div class="dbd-zone"><h4>' . esc_html( $title ) . '</h4><div class="dbd-zgrid"><div></div><div class="zh">Left</div><div class="zh">Middle</div><div class="zh">Right</div><div class="zl">Deep</div>' . $cell( 'deep_left' ) . $cell( 'deep_middle' ) . $cell( 'deep_right' ) . '<div class="zl">Short</div>' . $cell( 'short_left' ) . $cell( 'short_middle' ) . $cell( 'short_right' ) . '</div></div>';
};
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where the ball goes</b> · EPA per throw</span><span class="dbd-badge">Deep is 15+ air yds</span></div><div class="dbd-pb"><div class="dbd-zones">';
echo $zone_grid( $O, 'off', $Ot['nick'] . ': EPA per throw' ) . $zone_grid( $D, 'def', $Dt['nick'] . ': EPA allowed per throw' );
echo '</div><p class="dbd-note">Green gains more than expected, red loses value. Cells with fewer than 5 throws are grayed out.</p></div></section>';
echo '</div></main>';
