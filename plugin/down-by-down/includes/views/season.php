<?php
/** The season page (SCOPE §44): an archived season's hub, and this season's once the Super Bowl is final. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$season = (int) DBD_Data::season();
echo DBD_Router::topbar( array( $season . ' season' => null ) );
echo '<main class="dbd-content dbd-season-page"><div class="dbd-ttitle"><div><p class="dbd-kicker">' . $season . ' season · final</p><h1><em>' . $season . '</em> season</h1>'
	. '<p class="dbd-sub">How it ended, who led, and every game still here: each team, player and week of ' . $season . ' reads the way it did the morning after the Super Bowl.</p></div></div>';
echo DBD_Season::render();
echo '</main>';
