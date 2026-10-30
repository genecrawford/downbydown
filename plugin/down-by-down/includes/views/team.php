<?php
/** Team play explorer: the shell is server-rendered, the explorer fills #dbd-explorer from the team's play file. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $a = $r['team']; $t = DBD_Data::team( $a );
$season = DBD_Data::season(); $through = DBD_Data::through_week();
echo DBD_Router::topbar( array( 'play explorer' => DBD_Router::url( array( 'view' => 'teams' ) ), $t['nick'] . ' offense' => null ) );
echo '<main class="dbd-content" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">';
echo '<div class="dbd-ttitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1" id="dbd-kicker">' . esc_html( $through ? "$season season · Weeks 1 to $through · " . DBD_Data::record_text( $a ) : "$season season · Preseason" ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::pennant( $a, 48 ) . '<span><em>' . esc_html( $t['nick'] ) . '</em> offense</span></h1>';
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">Every ' . esc_html( preg_replace( '/\s+\S+$/', '', $t['name'] ) ) . ' offensive snap so far, drawn on the field. Pick games and filters, then hover or click any play to see what happened.' . ( DBD_Data::has_players() ? ' Or start from <a href="' . esc_url( DBD_Router::url( array( 'view' => 'players', 'team' => $a ) ) ) . '">the ' . esc_html( $t['nick'] ) . ' players</a>.' : '' ) . '</p></div>';
echo '<div class="dbd-badge" id="dbd-snaps"></div></div>';
if ( ! $through ) {
	echo '<div class="dbd-empty"><h2>No plays yet</h2><p>This page fills in the morning after the ' . esc_html( $t['nick'] ) . ' play their first game of the ' . (int) $season . ' season.</p></div>';
} else {
	echo '<div id="dbd-explorer" data-team="' . esc_attr( $a ) . '"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div>';
}
echo '</main>';
