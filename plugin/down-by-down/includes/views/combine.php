<?php
/** The combine board (SCOPE §63): the year's leaders in each drill, then everyone who tested, shaded by percentile at his position since 2000. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
DBD_Data::use_season( null );  /* the boards span every year: links and the switcher stay in the current season even under an archived address (Greptile, PR #112) */
$r = DBD_Router::$route; $Y = (int) $r['y']; $B = DBD_Boards::get( 'combine', $Y );
echo DBD_Router::topbar( array( 'combine' => DBD_Router::url( array( 'view' => 'combine' ) ), (string) $Y => null ) );
echo '<main class="dbd-content dbd-boards">';
if ( ! $B || ! $B['rows'] ) { echo '<div class="dbd-empty"><h2>No results yet</h2><p>This combine fills in during combine week in late February.</p></div></main>'; return; }
$rows = $B['rows']; $D = DBD_Boards::DRILLS; $lead = (array) ( $B['lead'] ?? array() );
$drafted = count( array_filter( $rows, function ( $x ) { return ! empty( $x['dr'] ); } ) );
$fast = null; foreach ( $rows as $x ) { if ( $x['forty'] !== null && ( ! $fast || $x['forty'] < $fast['forty'] ) ) { $fast = $x; } }
echo '<div class="bd-head"><div><p class="dbd-kicker">The ' . $Y . ' NFL Combine · ' . count( $rows ) . ' players tested</p><h1>The <em>' . $Y . '</em> combine</h1>'
	. '<p class="bd-say">' . ( $fast ? '<b>' . esc_html( $fast['n'] ) . '</b> ran the fastest 40 (' . esc_html( DBD_Boards::fmt( 'forty', $fast['forty'] ) ) . '). ' : '' ) . '<b>' . $drafted . '</b> of the ' . count( $rows ) . ' who tested were drafted that spring. Every result below is shaded by how it compares with every player at the same position since 2000.</p></div>'
	. DBD_Boards::controls( 'combine', $Y ) . '</div>';

/* ---- the leaders: each drill's top five this year, against history */
echo '<div class="bd-leaders">';
foreach ( $D as $k => $label ) {
	if ( empty( $lead[ $k ] ) ) { continue; } list( $idx, $better ) = $lead[ $k ];
	echo '<section class="dbd-panel bd-lead"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $label ) . '</b></span><span class="dbd-badge">' . ( (int) $better === 0 ? 'best since 2000' : (int) $better . ' better since 2000' ) . '</span></div><div class="dbd-pb"><ol>';
	foreach ( (array) $idx as $i ) { $x = $rows[ (int) $i ] ?? null; if ( ! $x ) { continue; } echo '<li><span>' . esc_html( $x['n'] ) . '<small>' . esc_html( $x['pos'] . ' · ' . $x['sc'] ) . '</small></span><b>' . esc_html( DBD_Boards::fmt( $k, $x[ $k ] ) ) . ( $k === 'forty' || $k === 'cone' || $k === 'shuttle' ? 's' : '' ) . '</b></li>'; }
	echo '</ol></div></section>';
}
echo '</div>';

/* ---- everyone who tested */
$ORDER = array( 'QB', 'RB', 'WR', 'TE', 'OT', 'G', 'C', 'DE', 'DT', 'LB', 'CB', 'S', 'K', 'P', 'LS' );
$pc = array(); foreach ( $rows as $x ) { $pc[ $x['g'] ] = ( $pc[ $x['g'] ] ?? 0 ) + 1; }
uksort( $pc, function ( $a, $b ) use ( $ORDER ) { $i = array_search( $a, $ORDER, true ); $j = array_search( $b, $ORDER, true ); return ( $i === false ? 99 : $i ) <=> ( $j === false ? 99 : $j ) ?: strcmp( $a, $b ); } );
$chip = function ( $v, $label, $on = false ) { return '<button class="bd-chip" type="button" data-v="' . esc_attr( $v ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">' . $label . '</button>'; };
echo '<section class="dbd-panel bd-filters"><div class="dbd-ph"><span class="dbd-lab"><b>Every player</b> · <span class="bd-count" data-unit="players"></span> · click a column to sort</span><span class="dbd-badge">Green beats most at his position, red trails</span></div>';
echo '<div class="fr"><span class="fl">Position</span><div class="bd-chips" data-filter="g">' . $chip( '', 'All', true ); foreach ( $pc as $g => $c ) { echo $chip( $g, esc_html( $g ) . ' <small>' . (int) $c . '</small>' ); } echo '</div></div>';
usort( $rows, function ( $a, $b ) { return ( $b['cs'] ?? -1 ) <=> ( $a['cs'] ?? -1 ); } );
echo '<div class="bd-tw"><table class="bd-t"><thead><tr><th data-k="n">Player</th><th data-k="g">Pos</th><th data-k="sc">School</th><th class="n" data-k="ht">Ht</th><th class="n" data-k="wt">Wt</th>';
foreach ( $D as $k => $label ) { echo '<th class="n" data-k="' . esc_attr( str_replace( '_', '', $k ) ) . '"' . ( in_array( $k, DBD_Boards::LOW, true ) ? ' data-low' : '' ) . '>' . esc_html( str_replace( array( '-yard dash', ' press', ' jump' ), '', $label ) ) . '</th>'; }
echo '<th class="n" data-k="cs">Score</th><th class="n" data-k="dp" data-low>Drafted</th></tr></thead><tbody>';
foreach ( $rows as $x ) {
	$dr = $x['dr'];
	echo '<tr data-p="1" data-g="' . esc_attr( $x['g'] ) . '" data-n="' . esc_attr( $x['n'] ) . '" data-sc="' . esc_attr( $x['sc'] ) . '" data-ht="' . esc_attr( (string) $x['ht'] ) . '" data-wt="' . esc_attr( (string) $x['wt'] ) . '" data-cs="' . ( $x['cs'] === null ? '' : (int) $x['cs'] ) . '" data-dp="' . ( $dr ? (int) $dr[1] : '' ) . '"';
	foreach ( $D as $k => $label ) { echo ' data-' . str_replace( '_', '', $k ) . '="' . esc_attr( $x[ $k ] === null ? '' : (string) $x[ $k ] ) . '"'; }
	echo '><td class="nm"><b>' . esc_html( $x['n'] ) . '</b></td><td>' . esc_html( $x['g'] ) . '</td><td>' . esc_html( $x['sc'] ) . '</td><td class="n">' . esc_html( $x['ht'] !== null ? DBD_Boards::fmt( 'ht', $x['ht'] ) : '–' ) . '</td><td class="n">' . esc_html( $x['wt'] !== null ? DBD_Boards::fmt( 'wt', $x['wt'] ) : '–' ) . '</td>';
	foreach ( $D as $k => $label ) { $p = $x[ $k . '_p' ]; echo $x[ $k ] === null ? '<td class="n bd-none">–</td>' : '<td class="n" style="' . esc_attr( DBD_Boards::heat( $p ) ) . '" title="' . esc_attr( DBD_Grades::ordinal( (int) $p ) . ' percentile among ' . $x['g'] . 's since 2000' ) . '">' . esc_html( DBD_Boards::fmt( $k, $x[ $k ] ) ) . '</td>'; }
	echo '<td class="n">' . ( $x['cs'] === null ? '<span class="bd-sc">–</span>' : '<span class="bd-sc" style="' . esc_attr( DBD_Boards::heat( $x['cs'] ) ) . '">' . (int) $x['cs'] . '</span>' ) . '</td>';
	echo '<td class="dr">' . ( $dr ? esc_html( ( $dr[2] ? $dr[2] . ' · ' : '' ) . 'R' . (int) $dr[0] . ' #' . (int) $dr[1] ) : 'Undrafted' ) . '</td></tr>';
}
echo '</tbody></table></div><p class="dbd-note">Results from nflverse\'s combine data (Pro Football Reference). Shading compares each result with every player at the same position at the combine since 2000: faster is better on the 40, 3-cone and shuttle, more is better on the rest. The score averages those percentiles over the drills he ran (two at least). Pro day results aren\'t included.</p></section>';
echo DBD_Boards::next_link( 'combine', $Y );  /* the draft, big and obvious (2.23.1) */
echo DBD_Signup::strip( 'combine' ) . '</main>';  /* the signup strip, last (SCOPE §66) */
