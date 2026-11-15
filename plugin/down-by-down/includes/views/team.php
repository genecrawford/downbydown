<?php
/** Team page: the offense play explorer, or (SCOPE §39) the defense tab, chosen by the Offense | Defense switch. The explorer fills #dbd-explorer from the team's play file. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $a = $r['team']; $t = DBD_Data::team( $a ); $def = ( $r['tside'] ?? 'off' ) === 'def';
$season = DBD_Data::season(); $through = DBD_Data::through_week();
$side = $def ? 'defense' : 'offense';
echo DBD_Router::topbar( array( 'play explorer' => DBD_Router::url( array( 'view' => 'teams' ) ), $t['nick'] . ' ' . $side => null ) );
/* SCOPE §57: the header is the team's own panel, in the colors of the game page's face-off and Keys (DBD_Marks::deep). The chips
 * further down pick up --k-bg and --k-ac from main. */
list( $kBg, $kAc ) = DBD_Marks::deep( $t );
echo '<main class="dbd-content' . ( $def ? ' dbd-defview' : '' ) . '" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . ';--k-bg:' . esc_attr( $kBg ) . ';--k-ac:' . esc_attr( $kAc ) . '">';
echo '<header class="dbd-tpanel"><div class="tp-mark">' . DBD_Marks::tile( $a, 96, true ) . '</div><div class="tp-main">';
echo '<p class="tp-kick" id="dbd-kicker">' . esc_html( $through ? "$season season · Weeks 1 to $through · " . DBD_Data::record_text( $a ) : "$season season · Preseason" ) . '</p>';
echo '<h1>' . esc_html( $t['nick'] ) . ' <span>' . $side . '</span></h1>';
$town = esc_html( preg_replace( '/\s+\S+$/', '', $t['name'] ) );
if ( $def ) { echo '<p class="tp-sub">Every snap the ' . esc_html( $t['nick'] ) . ' defended, what it cost them and how they got there: the league ranks the player pages quote, where offenses find room, how this defense plays, and who plays in it.</p>'; }
else { echo '<p class="tp-sub">Every ' . $town . ' offensive snap so far, drawn on the field. Pick games and filters, then hover or click any play to see what happened.' . ( DBD_Data::has_players() ? ' Or start from <a href="' . esc_url( DBD_Router::url( array( 'view' => 'players', 'team' => $a ) ) ) . '">the ' . esc_html( $t['nick'] ) . ' players</a>.' : '' ) . '</p>'; }
/* SCOPE §39: the switch is two links, so each side is its own address. Small links down to the news and videos at the end (SCOPE §54) */
echo '<div class="tp-row"><nav class="dbd-sideswitch" aria-label="Offense or defense"><a href="' . esc_url( DBD_Router::url( array( 'team' => $a ) ) ) . '"' . ( $def ? '' : ' aria-current="page"' ) . '>Offense</a><a href="' . esc_url( DBD_Router::url( array( 'team' => $a, 'side' => 'def' ) ) ) . '"' . ( $def ? ' aria-current="page"' : '' ) . '>Defense</a></nav>';
$nS = DBD_News::team( $a ); $nN = $nS && $nS['t'] ? count( $nS['t'] ) + count( $nS['l'] ) : 0; $vN = count( DBD_Videos::team( $a ) ) ?: ( $def && DBD_Videos::defense( $a ) ? 1 : 0 );  /* a link only when the block it jumps to has that half (Greptile, PR #91) */
if ( $nN || $vN ) { echo '<nav class="dbd-hop" aria-label="Further down this page">' . ( $nN ? '<a href="#around">News <b>' . (int) $nN . '</b></a>' : '' ) . ( $vN ? '<a href="#around">Videos <b>' . (int) $vN . '</b></a>' : '' ) . '</nav>'; }
echo '</div></div>';
/* four numbers about this team: both ranks, the snaps the explorer counts, and the next game (the last one once the season has none left) */
$rk = function ( $sd ) use ( $a ) { $v = DBD_Data::rank( $sd, $a ); return $v ? '#' . (int) $v : '–'; };
$nx = DBD_Fantasy::next_game( $a ); $ng = $nx ? DBD_Data::team_game( $a, $nx['wk'] ) : null; $nCell = '';
if ( $ng ) {
	$nts = strtotime( $ng['date'] );  /* the time goes through kickoff_html, so it shows in the viewer's zone like the game card (Greptile, PR #98) */
	$nCell = '<div><dt>Next</dt><dd><a href="' . esc_url( DBD_Router::url( $ng['home'] === $a ? array( 'game' => $ng['id'], 'side' => 'home' ) : array( 'game' => $ng['id'] ) ) ) . '">' . ( $ng['home'] === $a ? 'vs ' : 'at ' ) . esc_html( $nx['opp'] ) . '</a><small>' . esc_html( $nts ? gmdate( 'D, M j', $nts ) . ' · ' : '' ) . DBD_Router::kickoff_html( $ng ) . '</small></dd></div>';
} else {
	$lwl = DBD_Data::weeks();  /* from the schedule, not the plays: a final score can post before its plays do (Greptile, PR #98) */
	for ( $lw = $lwl ? (int) max( $lwl ) : (int) $through; $lw >= 1; $lw-- ) {
		$lg = DBD_Data::team_game( $a, $lw ); if ( ! $lg || ! DBD_Router::is_final( $lg ) ) { continue; }
		$lh = $lg['home'] === $a; $pf = (int) ( $lh ? $lg['home_score'] : $lg['away_score'] ); $pa = (int) ( $lh ? $lg['away_score'] : $lg['home_score'] );
		$nCell = '<div><dt>Last</dt><dd><a href="' . esc_url( DBD_Router::url( $lh ? array( 'game' => $lg['id'], 'side' => 'home' ) : array( 'game' => $lg['id'] ) ) ) . '">' . ( $lh ? 'vs ' : 'at ' ) . esc_html( $lh ? $lg['away'] : $lg['home'] ) . '</a><small>' . ( $pf > $pa ? 'W' : ( $pf < $pa ? 'L' : 'T' ) ) . ' ' . $pf . '-' . $pa . '</small></dd></div>';
		break;
	}
}
echo '<dl class="tp-stats"><div><dt>Offense</dt><dd>' . esc_html( $rk( 'off' ) ) . '<small>of 32 in EPA</small></dd></div>'
	. '<div><dt>Defense</dt><dd>' . ( $def ? esc_html( $rk( 'def' ) ) : DBD_Defense::link( $a, esc_html( $rk( 'def' ) ) ) ) . '<small>of 32 in EPA allowed</small></dd></div>'
	. '<div><dt>' . ( $def ? 'Snaps defended' : 'Snaps' ) . '</dt><dd id="dbd-snaps">–</dd></div>' . $nCell . '</dl></header>';
$D = $def ? DBD_Data::defense( $a ) : null;
if ( ! $through ) {
	echo '<div class="dbd-empty"><h2>No plays yet</h2><p>This page fills in the morning after the ' . esc_html( $t['nick'] ) . ' play their first game of the ' . (int) $season . ' season.</p></div>';
} elseif ( $def && ! $D ) {
	echo '<div class="dbd-empty"><h2>No defense data yet</h2><p>The defense tab fills in with the next nightly data build.</p></div>';
} elseif ( $def ) {
	echo DBD_Defense::page( $a, $D );
	echo '<section class="dbd-band" id="df-snaps"><h2 class="dbd-bandh">Every snap they defended<span>the play explorer, on offenses\' plays against them</span></h2><div id="dbd-explorer" data-team="' . esc_attr( $a ) . '" data-side="def">' . DBD_Marks::skel( 'explorer', 'Loading every snap' ) . '</div></section>';
} else {
	echo '<div id="dbd-explorer" data-team="' . esc_attr( $a ) . '">' . DBD_Marks::skel( 'explorer', 'Loading every snap' ) . '</div>';
}
echo DBD_News::around( $a, (bool) $def );  /* news and the newest video, after the explorer: secondary to the stats (2.13.0) */
echo '</main>';
