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

if ( ! $cards ) {
	echo '<div class="dbd-empty"><h2>Numbers appear after Week 1</h2><p>Takeaways, the head-to-head table and the pass zones fill in once both teams have played.</p></div></main>';
	return;
}
if ( $early ) { echo '<div class="dbd-panel dbd-notice"><div class="dbd-pb">Fewer than three games for one or both teams. Ranks are shown, but treat every number as a small sample.</div></div>'; }

/* ---- takeaways */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Takeaways</b> · generated from the numbers</span><span class="dbd-badge">' . count( $cards ) . ' reads</span></div><div class="dbd-takes t-stagger">';
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
