<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
echo '<main class="dbd-content"><div class="dbd-empty"><h2>Warming up</h2><p>The data has not synced yet. ' . ( current_user_can( 'manage_options' ) ? 'Go to <a href="' . esc_url( admin_url( 'options-general.php?page=down-by-down' ) ) . '">Settings › Down by Down</a> and click Sync now.' : 'Check back in a few minutes.' ) . '</p></div></main>';
