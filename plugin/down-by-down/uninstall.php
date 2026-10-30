<?php
/** Runs when the plugin is deleted from the Plugins screen: removes options, the cron event and the data folder. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

foreach ( array( 'dbd_source_url', 'dbd_sync_token', 'dbd_sync_log', 'dbd_stats' ) as $o ) { delete_option( $o ); }
wp_clear_scheduled_hook( 'dbd_sync_event' );

$u   = wp_upload_dir();
$dir = trailingslashit( $u['basedir'] ) . 'down-by-down';
if ( is_dir( $dir ) ) {
	foreach ( glob( $dir . '/plays/*.json' ) ?: array() as $f ) { @unlink( $f ); }
	@rmdir( $dir . '/plays' );
	foreach ( glob( $dir . '/*.json' ) ?: array() as $f ) { @unlink( $f ); }
	@rmdir( $dir );
}
