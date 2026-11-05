<?php
/** Runs when the plugin is deleted from the Plugins screen: removes options, the cron event and the data folder. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

foreach ( array( 'dbd_source_url', 'dbd_sync_token', 'dbd_sync_log', 'dbd_stats' ) as $o ) { delete_option( $o ); }
wp_clear_scheduled_hook( 'dbd_sync_event' );
/* team news (SCOPE §49): one list per team, the status and the source switches */
foreach ( array( 'ARI', 'ATL', 'BAL', 'BUF', 'CAR', 'CHI', 'CIN', 'CLE', 'DAL', 'DEN', 'DET', 'GB', 'HOU', 'IND', 'JAX', 'KC', 'LV', 'LAC', 'LA', 'MIA', 'MIN', 'NE', 'NO', 'NYG', 'NYJ', 'PHI', 'PIT', 'SF', 'SEA', 'TB', 'TEN', 'WAS' ) as $t ) { delete_option( 'dbd_news_' . $t ); }
foreach ( array( 'dbd_news_status', 'dbd_news_sources' ) as $o ) { delete_option( $o ); }
wp_clear_scheduled_hook( 'dbd_news_event' ); wp_clear_scheduled_hook( 'dbd_news_first' );

/* the Tuesday email (SCOPE §48): its settings, send log and signup counts; any queued sends */
foreach ( array( 'dbd_email', 'dbd_email_log', 'dbd_email_signups' ) as $o ) { delete_option( $o ); }
wp_unschedule_hook( 'dbd_email_send' );

$u   = wp_upload_dir();
$dir = trailingslashit( $u['basedir'] ) . 'down-by-down';
if ( is_dir( $dir ) ) {
	foreach ( glob( $dir . '/plays/*.json' ) ?: array() as $f ) { @unlink( $f ); }
	@rmdir( $dir . '/plays' );
	foreach ( glob( $dir . '/*.json' ) ?: array() as $f ) { @unlink( $f ); }
	@rmdir( $dir );
}
