<?php
/** Matchups index: every week's slate with a week switcher. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r      = DBD_Router::$route;
$week   = (int) $r['week'];
$cur    = DBD_Data::current_week();
$season = DBD_Data::season();
$games  = DBD_Data::week_games( $week );
$finals = count( array_filter( $games, array( 'DBD_Router', 'is_final' ) ) );
$label  = DBD_Router::week_label( $week );

echo DBD_Router::topbar( array( 'matchups' => null ) );
echo '<main class="dbd-content">';
echo '<div class="dbd-title"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · every week" ) . '</p><h1 class="t-stagger-line t-stagger-line--2">Matchups</h1>';
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">Every game of the season. Played games open a recap of how the offenses did against the defenses they faced. Upcoming games open the matchup read.</p></div></div>';

echo '<nav class="dbd-wkbar" aria-label="Weeks"><div class="dbd-wktabs">';
foreach ( DBD_Data::weeks() as $w ) {
	$href = DBD_Router::url( array( 'view' => 'matchups', 'week' => $w ) );
	echo '<a href="' . esc_url( $href ) . '"' . ( $w === $week ? ' aria-current="page"' : '' ) . '>' . ( $w === $cur ? '<i></i>' : '' ) . esc_html( DBD_Router::week_label( $w ) === 'Week ' . $w ? 'Wk ' . $w : DBD_Router::week_label( $w ) ) . '</a>';
}
echo '</div></nav>';

$state = $finals === count( $games ) && $games ? 'all final' : ( $finals ? "$finals final" : 'all upcoming' );
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $label ) . '</b> · ' . count( $games ) . ' games · ' . esc_html( $state ) . '</span><span>Lines and totals from nflverse. Kickoffs in your local time.</span></div>';
include DBD_PATH . 'includes/views/partials/slate-table.php';
echo '</section>';
echo DBD_Fantasy::matchups( $games, $label );  /* SCOPE §34: the games left to play, each offense against its opponent's points allowed by position */
echo '</main>';
