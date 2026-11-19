<?php
/** The weekly fantasy page (SCOPE §78): /fantasy/week/{N}/, /fantasy/ the newest week. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$wk = (int) DBD_Router::$route['week'];
echo DBD_Router::topbar( $wk ? array( 'fantasy' => DBD_FanWeek::url(), 'Week ' . $wk => null ) : array( 'fantasy' => null ) );
echo '<main class="dbd-content">' . DBD_FanWeek::page( $wk ) . '</main>';
