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
	echo '<div class="dbd-rkline">' . ( $final ? '<b class="dbd-score">' . (int) $sc . '</b>' : 'OFF #' . (int) DBD_Data::rank( 'off', $a ) . ' · ' . DBD_Defense::link( $a, 'DEF #' . (int) DBD_Data::rank( 'def', $a ) ) ) . '</div>';  /* SCOPE §39: to the defense tab */
}
echo '</div><div class="dbd-line"><span>' . esc_html( DBD_Router::line_text( $g ) ) . '</span><span>' . ( isset( $g['total'] ) ? 'O/U ' . esc_html( $g['total'] ) : '' ) . '</span><span>' . DBD_Router::kickoff_html( $g ) . '</span></div></div></aside>';
echo '</div>';

/* ---- how it played out (SCOPE §30): win probability for a final game, from wp/w{week}.json. Regulation only; overtime shows its scoring plays. */
$WP = $final ? DBD_Data::win_prob( $g ) : null;
if ( $WP && ! empty( $WP['s'] ) ) {
	$wS = $WP['s']; $wOT = ! empty( $WP['ot'] ); $wFin = (int) $WP['fin']; $wRfin = $wOT ? 500 : $wFin; $wOts = (array) ( $WP['otsc'] ?? array() );
	$wEnd = $wOT ? ( $wFin === 500 ? 4200 : min( 4200, max( 3660, ( $wOts ? (int) end( $wOts )[0] : 3600 ) + 60 ) ) ) : 3600;  /* overtime is ten minutes at most */
	$wHt = DBD_Data::team( $HOME ); $wAt = DBD_Data::team( $AWAY ); $wCh = DBD_Marks::accent( $wHt ); $wCa = DBD_Marks::accent( $wAt );
	$wHs = (int) ( $g['home_score'] ?? 0 ); $wAs = (int) ( $g['away_score'] ?? 0 );
	$wWin = $wFin === 1000 ? $HOME : ( $wFin === 0 ? $AWAY : null ); $wLose = $wWin ? ( $wWin === $HOME ? $AWAY : $HOME ) : null;
	$wFor = function ( $team, $v ) use ( $HOME ) { return $team === $HOME ? (int) $v : 1000 - (int) $v; };  /* a home chance, read for either team */
	$wPct = function ( $v ) { return round( $v / 10 ) . '%'; };
	$wNick = function ( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; };
	$wClock = function ( $el ) use ( $wEnd ) { if ( $el >= 3600 && $wEnd > 3600 ) { $r = max( 0, 600 - ( $el - 3600 ) ); return 'OT ' . floor( $r / 60 ) . ':' . sprintf( '%02d', $r % 60 ); } $q = min( 4, (int) floor( $el / 900 ) + 1 ); $r = 900 * $q - $el; return 'Q' . $q . ' ' . floor( $r / 60 ) . ':' . sprintf( '%02d', $r % 60 ); };
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
		$mk .= '<div class="otb" style="left:' . $wX( 3600 ) . '%"><span>Overtime</span></div>';  /* overtime scores are stacked down the band so close ones never overlap; one at the very end reads leftward so it stays inside the chart */
		foreach ( $wOts as $k => $o ) { $mk .= '<i class="ots' . ( $wX( $o[0] ) > 94 ? ' r' : '' ) . '" style="left:' . $wX( $o[0] ) . '%;top:' . min( 88, 34 + 18 * $k ) . '%" data-tip="' . esc_attr( '<b>' . $wClock( $o[0] ) . '</b> ' . esc_html( $o[2] ) . '<br>' . $AWAY . ' ' . (int) $o[4] . ' · ' . $HOME . ' ' . (int) $o[3] ) . '" tabindex="0">' . esc_html( (string) $o[1] ) . '</i>'; }
		if ( $wWin ) { $mk .= '<i class="fin' . ( $wWin === $HOME ? ' top' : '' ) . '" style="left:100%"></i>'; }
	}
	/* the scrubber: the snaps, then the end of regulation (the result, or 50% when it went to overtime), then overtime's scores, which carry no chance */
	$wData = wp_json_encode( array( 'e' => $wEnd, 'h' => $HOME, 'a' => $AWAY, 's' => array_merge( $wS, array( array( 3600, $wRfin, (int) ( $wOT ? $WP['reg'][0] : $wHs ), (int) ( $wOT ? $WP['reg'][1] : $wAs ) ) ) ), 'o' => array_map( function ( $o ) { return array( (int) $o[0], (int) $o[3], (int) $o[4] ); }, $wOts ) ) );
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

/* ---- box score (SCOPE §32): a final game's offense lines with snap share, then defense and kicking, from box/w{week}.json */
$BX = $final ? DBD_Data::box( $g ) : null;
if ( $BX && ! empty( $BX['keys'] ) ) {
	$bk = array_flip( $BX['keys'] ); $bsay = array(); $bcols = '';
	foreach ( array( 'away', 'home' ) as $bside ) {
		$bt = $BX[ $bside ] ?? null; if ( ! $bt ) { continue; } $btm = $bt['team']; $rows = (array) ( $bt['off'] ?? array() );
		$v = function ( $r, $k ) use ( $bk ) { return isset( $bk[ $k ] ) ? ( $r[ $bk[ $k ] ] ?? 0 ) : 0; };
		$nm = function ( $r ) use ( $v ) { $sn = $v( $r, 'snap' ); return '<a href="' . esc_url( DBD_Router::url( array( 'player' => $v( $r, 'id' ) ) ) ) . '">' . esc_html( (string) $v( $r, 'name' ) ) . '</a>' . ( $sn ? '<small>' . (int) $sn . '%</small>' : '' ); };
		$ep = function ( $x ) { $x = (float) $x; return '<td class="' . ( $x >= 0 ? 'pos' : 'neg' ) . '">' . ( $x > 0 ? '+' : '' ) . number_format( $x, 1 ) . '</td>'; };
		$by = function ( $k ) use ( $rows, $v ) { $o = array_values( array_filter( $rows, function ( $r ) use ( $v, $k ) { return (float) $v( $r, $k ) > 0; } ) ); usort( $o, function ( $a, $b ) use ( $v, $k ) { return $v( $b, $k ) <=> $v( $a, $k ); } ); return $o; };
		$qb = $by( 'attempts' ); $ru = $by( 'carries' ); $rc = $by( 'targets' );
		$tca = array_sum( array_map( function ( $r ) use ( $v ) { return $v( $r, 'carries' ); }, $ru ) ) ?: 1; $ttg = array_sum( array_map( function ( $r ) use ( $v ) { return $v( $r, 'targets' ); }, $rc ) ) ?: 1;
		$z = function ( $x ) { return (int) $x ?: ''; };
		$pas = ''; foreach ( $qb as $r ) { $pas .= '<tr><td class="nm">' . $nm( $r ) . '</td><td>' . (int) $v( $r, 'completions' ) . '/' . (int) $v( $r, 'attempts' ) . '</td><td>' . (int) $v( $r, 'passing_yards' ) . '</td><td>' . $z( $v( $r, 'passing_tds' ) ) . '</td><td>' . $z( $v( $r, 'passing_interceptions' ) ) . '</td><td>' . $z( $v( $r, 'sacks_suffered' ) ) . '</td>' . $ep( $v( $r, 'passing_epa' ) ) . '</tr>'; }
		$rus = ''; foreach ( $ru as $r ) { $rus .= '<tr><td class="nm">' . $nm( $r ) . '</td><td>' . (int) $v( $r, 'carries' ) . '</td><td>' . round( $v( $r, 'carries' ) / $tca * 100 ) . '%</td><td>' . (int) $v( $r, 'rushing_yards' ) . '</td><td>' . $z( $v( $r, 'rushing_tds' ) ) . '</td>' . $ep( $v( $r, 'rushing_epa' ) ) . '</tr>'; }
		$rec = ''; foreach ( $rc as $r ) { $rec .= '<tr><td class="nm">' . $nm( $r ) . '</td><td>' . (int) $v( $r, 'targets' ) . '</td><td>' . round( $v( $r, 'targets' ) / $ttg * 100 ) . '%</td><td>' . (int) $v( $r, 'receptions' ) . '</td><td>' . (int) $v( $r, 'receiving_yards' ) . '</td><td>' . $z( $v( $r, 'receiving_tds' ) ) . '</td>' . $ep( $v( $r, 'receiving_epa' ) ) . '</tr>'; }
		$H = function ( $c ) { return '<thead><tr>' . implode( '', array_map( function ( $x ) { return '<th>' . $x . '</th>'; }, $c ) ) . '</tr></thead>'; };
		$dl = array(); foreach ( array_slice( (array) ( $bt['d'] ?? array() ), 0, 4 ) as $d ) { $bits = array(); if ( (float) $d[1] ) { $bits[] = ( 0 + $d[1] ) . ' sack' . ( (float) $d[1] === 1.0 ? '' : 's' ); } if ( (int) $d[2] ) { $bits[] = (int) $d[2] . ' INT'; } $dl[] = esc_html( $d[0] . ' ' . implode( ' and ', $bits ) ); }
		$kk = ! empty( $bt['k'] ) ? esc_html( $bt['k'][0] . ' ' . (int) $bt['k'][1] . '/' . (int) $bt['k'][2] . ' FG' . ( (int) $bt['k'][3] ? ' (long ' . (int) $bt['k'][3] . ')' : '' ) ) : '';
		$best = null; $bestE = null; foreach ( $rows as $r ) { $e = (float) $v( $r, 'passing_epa' ) + (float) $v( $r, 'rushing_epa' ) + (float) $v( $r, 'receiving_epa' ); if ( $best === null || $e > $bestE ) { $best = $r; $bestE = $e; } }
		if ( $best ) { $bp = (string) $v( $best, 'pos' );
			$bl = $bp === 'QB' ? (int) $v( $best, 'completions' ) . ' of ' . (int) $v( $best, 'attempts' ) . ' for ' . (int) $v( $best, 'passing_yards' ) : ( in_array( $bp, array( 'RB', 'FB' ), true ) ? (int) $v( $best, 'carries' ) . ' carries for ' . (int) $v( $best, 'rushing_yards' ) : (int) $v( $best, 'receptions' ) . ' catches for ' . (int) $v( $best, 'receiving_yards' ) );
			$bsay[] = '<b>' . esc_html( (string) $v( $best, 'name' ) ) . '</b> led the ' . esc_html( DBD_Data::team( $btm )['nick'] ?? $btm ) . ' at ' . ( $bestE > 0 ? '+' : '' ) . number_format( $bestE, 1 ) . ' EPA (' . $bl . ').'; }
		$bcols .= '<div class="dbd-bxteam"><h4>' . DBD_Marks::tile( $btm, 20 ) . ' ' . esc_html( DBD_Data::team( $btm )['nick'] ?? $btm ) . '</h4>'
			. ( $pas ? '<table class="dbd-stat dbd-bx">' . $H( array( 'Passing', 'C/A', 'Yds', 'TD', 'INT', 'Sk', 'EPA' ) ) . '<tbody>' . $pas . '</tbody></table>' : '' )
			. ( $rus ? '<table class="dbd-stat dbd-bx">' . $H( array( 'Rushing', 'Car', 'Share', 'Yds', 'TD', 'EPA' ) ) . '<tbody>' . $rus . '</tbody></table>' : '' )
			. ( $rec ? '<table class="dbd-stat dbd-bx">' . $H( array( 'Receiving', 'Tgt', 'Share', 'Rec', 'Yds', 'TD', 'EPA' ) ) . '<tbody>' . $rec . '</tbody></table>' : '' )
			. '<p class="dbd-bxdk"><b>Defense</b> ' . ( $dl ? implode( ', ', $dl ) : 'no sacks or interceptions' ) . ( $kk ? ' · <b>Kicking</b> ' . $kk : '' ) . '</p></div>';
	}
	echo '<section class="dbd-panel dbd-box"><div class="dbd-ph"><span class="dbd-lab"><b>Box score</b> · every offensive player who touched the ball</span><span class="dbd-badge">Week ' . (int) $g['week'] . '</span></div>';
	echo '<p class="dbd-wp-say">' . implode( ' ', $bsay ) . '</p><div class="dbd-bx2">' . $bcols . '</div><p class="dbd-note">Each player\'s line for this game from nflverse\'s weekly stats; the percentage beside his name is his share of the offense\'s snaps. Share is his part of the team\'s carries or targets in this game. Defense lists sacks and interceptions; kicking lists field goals.</p></section>';
}

/* ---- the lineups (SCOPE §32): an upcoming game draws this offense against the other team's defense from the depth charts; a final has the box score instead */
$luHtml = '';
if ( ! $final ) { $luB = DBD_Lineup::board( $O, $D );
	if ( $luB ) { $luHtml = '<section class="dbd-panel dbd-lu"><div class="dbd-ph"><span class="dbd-lab"><b>The lineups</b> · ' . esc_html( $Ot['nick'] ) . ' offense against the ' . esc_html( $Dt['nick'] ) . ' defense</span><span class="dbd-badge">Depth charts</span></div>' . $luB
		. '<p class="dbd-note">Starters from each team\'s current depth chart (nflverse, from ESPN), drawn in the offense\'s base three-receiver set against the defense\'s base front. The nickel back, the extra defensive back most teams use against three receivers, is drawn lighter. Left and right are as the offense sees the field. The toggle at the top flips which offense is drawn.</p></section>'; } }

if ( ! $cards ) {
	echo $luHtml;
	echo '<div class="dbd-empty"><h2>Numbers appear after Week 1</h2><p>Takeaways, the head-to-head table and the pass zones fill in once both teams have played.</p></div></main>';
	return;
}
if ( $early ) { echo '<div class="dbd-panel dbd-notice"><div class="dbd-pb">Fewer than three games for one or both teams. Ranks are shown, but treat every number as a small sample.</div></div>'; }

/* ---- keys to the game (SCOPE §32, Takeaways renamed): the generated reads, and on an upcoming game the players to watch */
echo '<section class="dbd-panel dbd-keys"><div class="dbd-ph"><span class="dbd-lab"><b>Keys to the game</b> · ' . ( $final ? 'from the season numbers, not this game alone' : 'where this matchup tilts, from the season numbers' ) . '</span><span class="dbd-badge">' . count( $cards ) . ' reads</span></div><div class="dbd-takes t-stagger">';
$n = 0;
foreach ( $cards as $c ) {
	$n++;
	echo '<div class="dbd-take t-stagger-line t-stagger-line--' . $n . ' ' . esc_attr( $c[1] ) . '"><span class="n">' . sprintf( '%02d', $n ) . '</span><div class="tag">' . esc_html( $c[0] ) . '</div><h3>' . esc_html( $c[2] ) . '</h3><p>' . esc_html( $c[3] ) . '</p>' . ( $early ? '<span class="small">Early season: small samples</span>' : '' ) . '</div>';
}
echo '</div>';
if ( ! $final ) { $pw = DBD_Lineup::watch( $AWAY ) . DBD_Lineup::watch( $HOME ); if ( $pw ) { echo '<h4 class="dbd-wph">Players to watch</h4><div class="dbd-pw2">' . $pw . '</div>'; } }
echo '</section>';
/* ---- the series and the records (SCOPE §33): since 1999 from series.json, read from the away team's side, with both teams' records this season */
$SR = DBD_Data::series( $AWAY, $HOME );
if ( $SR && ! empty( $SR['n'] ) ) {
	$srN = function ( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; }; $wl = function ( $x ) { return (int) $x[0] . '–' . (int) $x[1] . ( ! empty( $x[2] ) ? '–' . (int) $x[2] : '' ); };
	$lead = $SR['w'] > $SR['l'] ? $AWAY : ( $SR['l'] > $SR['w'] ? $HOME : null );
	$srT = (int) ( $SR['t'] ?? 0 ) ? '–' . (int) $SR['t'] : '';  /* ties count, and show */
	$srSince = (int) $SR['since'] . ( (int) $SR['first'] > (int) $SR['since'] ? ' (they first met in ' . (int) $SR['first'] . ')' : '' );
	$srSay = $lead ? 'The ' . esc_html( $srN( $lead ) ) . ' lead the series <b>' . max( $SR['w'], $SR['l'] ) . '–' . min( $SR['w'], $SR['l'] ) . $srT . '</b> since ' . $srSince . '.' : 'The series is even at ' . (int) $SR['w'] . '–' . (int) $SR['l'] . $srT . ' since ' . $srSince . '.';
	$sk = $SR['streak'] ?? null;
	if ( $sk && $sk[0] && (int) $sk[1] > 1 ) { $srSay .= $sk[0] === $lead ? ' They have won the last ' . (int) $sk[1] . '.' : ' The ' . esc_html( $srN( $sk[0] ) ) . ' have won the last ' . (int) $sk[1] . '.'; }
	$L = $SR['lastm'] ?? null;
	if ( $L && ( $L['id'] ?? '' ) !== $g['id'] ) {  /* on a final, the last meeting is this game, which the panels above already cover */
		$tie = (int) $L['home_score'] === (int) $L['away_score']; $lw = (int) $L['home_score'] > (int) $L['away_score'] ? $L['home'] : $L['away']; $ts = strtotime( $L['date'] ); $xmas = $ts && gmdate( 'm-d', $ts ) === '12-25';
		$srSay .= ' <b>' . esc_html( $ts ? gmdate( 'M j, Y', $ts ) : $L['date'] ) . '</b>' . ( $xmas ? ' (Christmas)' : '' ) . ( ! empty( $L['stadium'] ) ? ', at ' . esc_html( $L['stadium'] ) : '' ) . ': '
			. ( $tie ? 'the teams tied ' . (int) $L['home_score'] . '–' . (int) $L['away_score'] : 'the ' . esc_html( $srN( $lw ) ) . ' won ' . max( (int) $L['home_score'], (int) $L['away_score'] ) . '–' . min( (int) $L['home_score'], (int) $L['away_score'] ) ) . ( ! empty( $L['ot'] ) ? ' in overtime' : '' ) . '.';
		if ( ! empty( $L['qb'][0] ) && ! empty( $L['qb'][1] ) ) { $srSay .= ' ' . esc_html( $L['qb'][1] ) . ' started at quarterback for the ' . esc_html( $srN( $L['home'] ) ) . ' and ' . esc_html( $L['qb'][0] ) . ' for the ' . esc_html( $srN( $L['away'] ) ) . ( ! empty( $L['coach'][0] ) && ! empty( $L['coach'][1] ) ? '; ' . esc_html( $L['coach'][1] ) . ' and ' . esc_html( $L['coach'][0] ) . ' coached.' : '.' ); }
	} elseif ( $final ) { $srSay .= ' This game is the latest meeting.'; }
	$hn = DBD_Data::team( $HOME ); $city = trim( str_replace( $hn['nick'], '', $hn['name'] ) );
	$srChips = array( array( $srN( $AWAY ) . ' at home', $wl( $SR['home'] ), 'the ' . $srN( $AWAY ) . '\' record' ), array( 'In ' . $city, $wl( $SR['road'] ), 'the ' . $srN( $AWAY ) . '\' record' ),
		array( 'Most points', $srN( $AWAY ) . ' ' . (int) $SR['most'][ $AWAY ][0] . ' · ' . $srN( $HOME ) . ' ' . (int) $SR['most'][ $HOME ][0], (int) $SR['most'][ $AWAY ][1] . ' · ' . (int) $SR['most'][ $HOME ][1] ),
		array( 'Biggest win', ( (int) $SR['big'][ $AWAY ][0] ? $srN( $AWAY ) . ' by ' . (int) $SR['big'][ $AWAY ][0] : $srN( $AWAY ) . ': none' ) . ' · ' . ( (int) $SR['big'][ $HOME ][0] ? $srN( $HOME ) . ' by ' . (int) $SR['big'][ $HOME ][0] : $srN( $HOME ) . ': none' ), ( (int) $SR['big'][ $AWAY ][1] ?: '–' ) . ' · ' . ( (int) $SR['big'][ $HOME ][1] ?: '–' ) ) );
	if ( ! empty( $SR['post'][0] ) || ! empty( $SR['post'][1] ) ) { $srChips[] = array( 'Playoffs', $wl( $SR['post'] ), 'the ' . $srN( $AWAY ) . '\' record' ); }
	$chipH = ''; foreach ( $srChips as $cc ) { $chipH .= '<div class="c"><small>' . esc_html( $cc[0] ) . '</small><b>' . esc_html( $cc[1] ) . '</b><span>' . esc_html( (string) $cc[2] ) . '</span></div>'; }
	$rows = '';
	foreach ( array_reverse( (array) ( $SR['last5'] ?? array() ) ) as $m ) {
		list( $mid, $ms, $mw, $mt, $ma, $mas, $mh, $mhs, $mot ) = $m; $win = $mas > $mhs ? $ma : ( $mhs > $mas ? $mh : '' );
		$sc = '<span class="' . ( $win === $ma ? 'w' : '' ) . '">' . esc_html( $ma ) . ' ' . (int) $mas . '</span> <i>at</i> <span class="' . ( $win === $mh ? 'w' : '' ) . '">' . esc_html( $mh ) . ' ' . (int) $mhs . '</span>';
		$lab = (int) $ms . ' · Wk ' . (int) $mw . ( $mot ? ' · OT' : '' ) . ( $mt !== 'REG' ? ' · playoffs' : '' );
		$link = (int) $ms === (int) DBD_Data::season() && $mid !== $g['id'] ? DBD_Router::url( array( 'game' => $mid ) ) : '';
		$rows .= '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $lab ) . '</a>' : esc_html( $lab ) ) . '</td><td>' . $sc . '</td></tr>';
	}
	$recA = DBD_Data::record_split( $AWAY ); $recH = DBD_Data::record_split( $HOME ); $recT = '';
	if ( $recA && $recH && ( $recA['g'] || $recH['g'] ) ) {
		$cellr = function ( $r, $k ) use ( $wl ) { return isset( $r['rec'][ $k ] ) ? $wl( $r['rec'][ $k ] ) : '0–0'; };
		$dots = function ( $s ) { $o = ''; foreach ( str_split( (string) $s ) as $ch ) { if ( $ch !== '' ) { $o .= '<i class="' . ( $ch === 'W' ? 'w' : ( $ch === 'L' ? 'l' : 't' ) ) . '">' . esc_html( $ch ) . '</i>'; } } return '<span class="dots">' . $o . '</span>'; };
		$stk = function ( $s ) { return $s ? ( $s[0] === 'W' ? 'Won ' : ( $s[0] === 'L' ? 'Lost ' : 'Tied ' ) ) . substr( $s, 1 ) : '–'; };
		$ppg = function ( $r ) { return $r['g'] ? number_format( $r['pf'] / $r['g'], 1 ) . ' – ' . number_format( $r['pa'] / $r['g'], 1 ) : '–'; };
		foreach ( array( 'all' => 'Overall', 'div' => 'Division', 'conf' => 'Conference', 'nonconf' => 'Other conference', 'home' => 'Home', 'away' => 'Away' ) as $k => $lab ) { $recT .= '<tr><td>' . $lab . '</td><td>' . $cellr( $recA, $k ) . '</td><td>' . $cellr( $recH, $k ) . '</td></tr>'; }
		$recT .= '<tr><td>Last 5</td><td>' . $dots( $recA['last5'] ) . '</td><td>' . $dots( $recH['last5'] ) . '</td></tr><tr><td>Streak</td><td>' . esc_html( $stk( $recA['streak'] ) ) . '</td><td>' . esc_html( $stk( $recH['streak'] ) ) . '</td></tr><tr><td>Points a game</td><td>' . $ppg( $recA ) . '</td><td>' . $ppg( $recH ) . '</td></tr>';
		$recT = '<div><h4 class="dbd-wph">' . (int) DBD_Data::season() . ' records</h4><table class="dbd-stat dbd-srr"><thead><tr><th></th><th>' . esc_html( $srN( $AWAY ) ) . '</th><th>' . esc_html( $srN( $HOME ) ) . '</th></tr></thead><tbody>' . $recT . '</tbody></table></div>';
	}
	echo '<section class="dbd-panel dbd-series"><div class="dbd-ph"><span class="dbd-lab"><b>The series</b> · ' . esc_html( $srN( $AWAY ) ) . ' and ' . esc_html( $srN( $HOME ) ) . ' since ' . (int) $SR['since'] . ', and this season\'s records</span><span class="dbd-badge">' . (int) $SR['n'] . ' meetings</span></div>';
	echo '<p class="dbd-wp-say">' . $srSay . '</p><div class="dbd-sr2"><div><div class="dbd-wpchips sr">' . $chipH . '</div><h4 class="dbd-wph">The last ' . count( (array) $SR['last5'] ) . '</h4><table class="dbd-stat dbd-srl"><tbody>' . $rows . '</tbody></table></div>' . $recT . '</div>';
	echo '<p class="dbd-note">Every meeting since ' . (int) $SR['since'] . ', regular season and playoffs, from nflverse\'s game list, which starts in ' . (int) $SR['since'] . '; the all-time series goes back further. Franchises that moved count as one team (the Raiders, Chargers and Rams). Home and away are from the ' . esc_html( $srN( $AWAY ) ) . '\' side. Records are this regular season.</p></section>';
}
echo $luHtml;

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
