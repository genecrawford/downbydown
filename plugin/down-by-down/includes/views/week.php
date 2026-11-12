<?php
/** The week in review (SCOPE §40): ?view=week&week=N. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wk = (int) DBD_Router::$route['week'];
echo DBD_Router::topbar( array( 'matchups' => DBD_Router::url( array( 'view' => 'matchups', 'week' => $wk ) ), DBD_Router::week_label( $wk ) . ' in review' => null ) );
echo '<main class="dbd-content">' . DBD_Week::page( $wk ) . DBD_Videos::week_panel( $wk ) . '</main>';  /* every game's recap, after the week's numbers (SCOPE §54) */
