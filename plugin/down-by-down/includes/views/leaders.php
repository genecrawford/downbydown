<?php
/** Leaders (SCOPE §37): four tabs of boards, or one full board when b= is set. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $season = DBD_Data::season(); $through = DBD_Data::through_week();
$lurl = function ( $args = array() ) { return DBD_Router::url( array_merge( array( 'view' => 'leaders' ), $args ) ); };
$F = DBD_Leaders::file();
$B = ( $r['lboard'] ?? '' ) !== '' && $F ? DBD_Leaders::board( $r['lboard'] ) : null;
echo DBD_Router::topbar( $B ? array( 'players' => DBD_Router::url( array( 'view' => 'players' ) ), 'leaders' => $lurl( array( 'g' => $B['group'] ) ), $B['title'] => null ) : array( 'players' => DBD_Router::url( array( 'view' => 'players' ) ), 'leaders' => null ) );
echo '<main class="dbd-content">';
echo '<div class="dbd-ttitle"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( $through ? "$season season · through week $through" : "$season season" ) . '</p>';
if ( $B ) { echo '<h1 class="t-stagger-line t-stagger-line--2">' . esc_html( $B['title'] ) . '</h1><p class="dbd-sub t-stagger-line t-stagger-line--3">' . esc_html( ucfirst( $B['sub'] ) ) . '. ' . ( $B['group'] === 'fan' ? 'Every player on the board, ranked.' : 'Every player above the usage floor, ranked. The default is the qualified pool the grades use; lower the minimum to see more.' ) . '</p></div>'; if ( isset( $B['count'] ) ) { echo '<div class="dbd-badge" id="ld-count">' . (int) $B['count'] . ' players</div>'; } echo '</div>'; }
else { echo '<h1 class="t-stagger-line t-stagger-line--2">League <em>leaders</em></h1><p class="dbd-sub t-stagger-line t-stagger-line--3">Who leads the league in what this site measures: every Scorecard stat, every Playstyle trait and the fantasy numbers, the top ten on each board and one click to the full list.</p></div></div>'; }
if ( ! $F ) { echo '<div class="dbd-empty"><h2>No leaders yet</h2><p>The boards fill in with the next nightly data build.</p></div></main>'; return; }
if ( ( $r['lboard'] ?? '' ) !== '' && ! $B ) { echo '<div class="dbd-empty"><h2>No such board</h2><p><a href="' . esc_url( $lurl() ) . '">All the leaders</a></p></div></main>'; return; }
if ( $B ) {
	echo '<section class="dbd-panel ld-board"><div class="dbd-toolbar ld-tools">' . $B['filters'] . '<a class="dbd-pill ld-back" href="' . esc_url( $lurl( array( 'g' => $B['group'] ) ) ) . '">← All ' . esc_html( strtolower( DBD_Leaders::GROUPS[ $B['group'] ] ) ) . ' boards</a></div>' . $B['body'];
	echo '<p class="dbd-note">Ranks are among the ' . ( $B['group'] === 'def' ? 'defenses' : 'players' ) . ' shown, ties marked T-. ' . ( $B['group'] === 'def' ? 'Rank 1 is the best defense on that stat; open any team for its whole defense tab.' : '' ) . ( $B['group'] === 'def' ? '' : ( $B['group'] === 'fan' ? 'Points a game counts players with at least half the most games at the position; the format you pick is remembered on this device.' : 'Lower-is-better stats rank lowest first. Usage is his season total of ' . DBD_Leaders::unit( $B['group'] ) . '.' ) ) . '</p></section></main>';
	return;
}
$g = $r['lgroup'];
echo '<nav class="dbd-wkbar ld-tabs" aria-label="Leaders"><div class="dbd-wktabs">';
foreach ( DBD_Leaders::GROUPS as $k => $lab ) { echo '<a href="' . esc_url( $lurl( array( 'g' => $k ) ) ) . '"' . ( $k === $g ? ' aria-current="page"' : '' ) . '>' . esc_html( $lab ) . '</a>'; }
echo '</div></nav>';
if ( $g === 'def' ) {
	echo DBD_Leaders::defenses();  /* SCOPE §39 */
} elseif ( $g === 'fan' ) {
	echo '<section class="dbd-panel ld-tools-p"><div class="dbd-toolbar ld-tools"><div class="dbd-fg"><span>Fantasy scoring</span>' . DBD_Fantasy::seg() . '</div></div></section>';
	echo DBD_Leaders::fantasy();
} else {
	list( $seg, $html ) = DBD_Leaders::group( $g );
	if ( $seg ) { echo '<section class="dbd-panel ld-tools-p"><div class="dbd-toolbar ld-tools">' . $seg . '</div></section>'; }
	echo $html;
}
echo '<p class="dbd-note ld-foot">Wide receivers and tight ends rank separately, as fantasy leagues treat them. Boards use the qualified pool the player pages grade against (' . esc_html( implode( ', ', array_map( function ( $gg ) { return DBD_Leaders::steps( $gg )[0] . '+ ' . DBD_Leaders::unit( $gg ); }, array( 'rec', 'rb', 'qb' ) ) ) ) . '). Ties are marked T-. Each full board lowers the minimum if you want to see more players.</p>';
echo '</main>';
