<?php
/** Team picker: the two conference standings tables, with a "pick up where you left off" box filled by JS. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$season = DBD_Data::season();
echo DBD_Router::topbar( array( 'play explorer' => null ) );
echo '<main class="dbd-content">';
echo '<div class="dbd-title"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · 32 offenses" ) . '</p><h1 class="t-stagger-line t-stagger-line--2">Pick a team</h1>';
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">Every offensive snap a team has run this season, drawn on the field, with filters for down, play type, field zone, formation, quarter and player.</p></div>';
echo '<div id="dbd-last" data-base="' . esc_url( DBD_Router::url() ) . '"></div></div>';
echo '<div class="dbd-two">';
foreach ( array( 'AFC', 'NFC' ) as $conf ) { include DBD_PATH . 'includes/views/partials/standings.php'; }
echo '</div>';
echo '<p class="dbd-note">Offense #1 moves the ball best; defense #1 allows the least. Green is top 10, red is bottom 10. Ranks update every night.</p>';
echo '</main>';
// Pennants for every team so the last-viewed box can be drawn without a round trip.
$pen = array();
foreach ( array_keys( DBD_Data::teams() ) as $a ) { $pen[ $a ] = DBD_Marks::pennant( $a, 40 ); }
echo '<script type="application/json" id="dbd-pennants">' . wp_json_encode( $pen ) . '</script>';
