<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
echo DBD_Router::topbar( array( 'not found' => null ) );
echo '<main class="dbd-content"><div class="dbd-empty"><h2>Nothing here</h2><p>That team or game is not in this season\'s data. <a href="' . esc_url( DBD_Router::url() ) . '">Back to this week</a>.</p></div></main>';
