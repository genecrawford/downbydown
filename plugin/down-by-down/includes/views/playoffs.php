<?php
/** The playoffs (SCOPE §88): the bracket page, or one game's preview. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$dbd_po_game = (string) ( DBD_Router::$route['pgame'] ?? '' );
echo DBD_Router::topbar( $dbd_po_game !== '' ? array( 'Playoffs' => DBD_Router::url( array( 'view' => 'playoffs' ) ), 'Game preview' => null ) : array( 'Playoffs' => null ) );
echo '<main class="dbd-content">' . ( $dbd_po_game !== '' ? DBD_Playoffs::game_page( $dbd_po_game ) : DBD_Playoffs::page() ) . '</main>';
