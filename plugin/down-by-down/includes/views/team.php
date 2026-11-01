<?php
/** Team page: the offense play explorer, or (SCOPE §39) the defense tab, chosen by the Offense | Defense switch. The explorer fills #dbd-explorer from the team's play file. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $a = $r['team']; $t = DBD_Data::team( $a ); $def = ( $r['tside'] ?? 'off' ) === 'def';
$season = DBD_Data::season(); $through = DBD_Data::through_week();
$side = $def ? 'defense' : 'offense';
echo DBD_Router::topbar( array( 'play explorer' => DBD_Router::url( array( 'view' => 'teams' ) ), $t['nick'] . ' ' . $side => null ) );
echo '<main class="dbd-content' . ( $def ? ' dbd-defview' : '' ) . '" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">';
echo '<div class="dbd-ttitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1" id="dbd-kicker">' . esc_html( $through ? "$season season · Weeks 1 to $through · " . DBD_Data::record_text( $a ) : "$season season · Preseason" ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::pennant( $a, 48 ) . '<span><em>' . esc_html( $t['nick'] ) . '</em> ' . $side . '</span></h1>';
$town = esc_html( preg_replace( '/\s+\S+$/', '', $t['name'] ) );
if ( $def ) { echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">Every snap the ' . esc_html( $t['nick'] ) . ' defended, what it cost them and how they got there: the league ranks the player pages quote, where offenses find room, how this defense plays, and who plays in it.</p>'; }
else { echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">Every ' . $town . ' offensive snap so far, drawn on the field. Pick games and filters, then hover or click any play to see what happened.' . ( DBD_Data::has_players() ? ' Or start from <a href="' . esc_url( DBD_Router::url( array( 'view' => 'players', 'team' => $a ) ) ) . '">the ' . esc_html( $t['nick'] ) . ' players</a>.' : '' ) . '</p>'; }
/* SCOPE §39: the switch is two links, so each side is its own address */
echo '<nav class="dbd-sideswitch" aria-label="Offense or defense"><a href="' . esc_url( DBD_Router::url( array( 'team' => $a ) ) ) . '"' . ( $def ? '' : ' aria-current="page"' ) . '>Offense</a><a href="' . esc_url( DBD_Router::url( array( 'team' => $a, 'side' => 'def' ) ) ) . '"' . ( $def ? ' aria-current="page"' : '' ) . '>Defense</a></nav></div>';
$D = $def ? DBD_Data::defense( $a ) : null;
echo '<div class="dbd-badge" id="dbd-snaps">' . ( $D && ! empty( $D['rank']['epa'] ) ? esc_html( DBD_Grades::ordinal( (int) $D['rank']['epa'] ) ) . ' of 32 in EPA allowed' : '' ) . '</div></div>';
if ( ! $through ) {
	echo '<div class="dbd-empty"><h2>No plays yet</h2><p>This page fills in the morning after the ' . esc_html( $t['nick'] ) . ' play their first game of the ' . (int) $season . ' season.</p></div>';
} elseif ( $def && ! $D ) {
	echo '<div class="dbd-empty"><h2>No defense data yet</h2><p>The defense tab fills in with the next nightly data build.</p></div>';
} elseif ( $def ) {
	echo DBD_Defense::page( $a, $D );
	echo '<section class="dbd-band" id="df-snaps"><h2 class="dbd-bandh">Every snap they defended<span>the play explorer, on offenses\' plays against them</span></h2><div id="dbd-explorer" data-team="' . esc_attr( $a ) . '" data-side="def"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div></section>';
} else {
	echo '<div id="dbd-explorer" data-team="' . esc_attr( $a ) . '"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div>';
}
echo '</main>';
