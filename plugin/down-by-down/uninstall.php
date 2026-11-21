<?php
/** Runs when the plugin is deleted from the Plugins screen: removes options, the cron event and the data folder. */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

foreach ( array( 'dbd_source_url', 'dbd_sync_token', 'dbd_sync_log', 'dbd_stats' ) as $o ) { delete_option( $o ); }
wp_clear_scheduled_hook( 'dbd_sync_event' );
/* team news (SCOPE §49): one list per team, the status and the source switches */
foreach ( array( 'ARI', 'ATL', 'BAL', 'BUF', 'CAR', 'CHI', 'CIN', 'CLE', 'DAL', 'DEN', 'DET', 'GB', 'HOU', 'IND', 'JAX', 'KC', 'LV', 'LAC', 'LA', 'MIA', 'MIN', 'NE', 'NO', 'NYG', 'NYJ', 'PHI', 'PIT', 'SF', 'SEA', 'TB', 'TEN', 'WAS' ) as $t ) { delete_option( 'dbd_news_' . $t ); }
foreach ( array( 'dbd_news_status', 'dbd_news_sources' ) as $o ) { delete_option( $o ); }
wp_clear_scheduled_hook( 'dbd_news_event' ); wp_clear_scheduled_hook( 'dbd_news_first' );
/* team videos and highlights (SCOPE §51) */
foreach ( array( 'ARI', 'ATL', 'BAL', 'BUF', 'CAR', 'CHI', 'CIN', 'CLE', 'DAL', 'DEN', 'DET', 'GB', 'HOU', 'IND', 'JAX', 'KC', 'LV', 'LAC', 'LA', 'MIA', 'MIN', 'NE', 'NO', 'NYG', 'NYJ', 'PHI', 'PIT', 'SF', 'SEA', 'TB', 'TEN', 'WAS' ) as $t ) { delete_option( 'dbd_videos_' . $t ); }
foreach ( array( 'dbd_videos_status', 'dbd_videos_on', 'dbd_highlights', 'dbd_previews', 'dbd_player_hl', 'dbd_defense_hl' ) as $o ) { delete_option( $o ); }
wp_clear_scheduled_hook( 'dbd_videos_first' );

/* the Tuesday email (SCOPE §48): its settings, send log and signup counts; any queued sends */
foreach ( array( 'dbd_email', 'dbd_email_log', 'dbd_email_signups' ) as $o ) { delete_option( $o ); }
wp_unschedule_hook( 'dbd_email_send' );
/* the share pack email (SCOPE §69): who gets it and the send record; any booked sends */
foreach ( array( 'dbd_pack_mail', 'dbd_pack_mail_set', 'dbd_pack_sub', 'dbd_pack_x', 'dbd_pack_bsky', 'dbd_pack_fb', 'dbd_pack_ig', 'dbd_ig_set', 'dbd_ig_token', 'dbd_bsky_set', 'dbd_x_set', 'dbd_x_conn', 'dbd_pack_th', 'dbd_th_set', 'dbd_th_conn' ) as $o ) { delete_option( $o ); }
wp_unschedule_hook( 'dbd_pack_mail_send' ); wp_unschedule_hook( 'dbd_ig_send' ); wp_unschedule_hook( 'dbd_ig_refresh' ); wp_unschedule_hook( 'dbd_bsky_send' ); wp_unschedule_hook( 'dbd_x_send' ); wp_unschedule_hook( 'dbd_th_send' ); wp_unschedule_hook( 'dbd_th_refresh' );
global $wpdb; $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dbd\\_ig\\_job\\_%' OR option_name LIKE 'dbd\\_ig\\_lock\\_%'" );  /* Instagram's per-post records (SCOPE §76) */
/* Auto publish (SCOPE §81): the networks, the Bluesky connection and session, its per-post records */
foreach ( array( 'dbd_ap_set', 'dbd_bsky_conn' ) as $o ) { delete_option( $o ); } delete_transient( 'dbd_bsky_session' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dbd\\_bsky\\_job\\_%' OR option_name LIKE 'dbd\\_bsky\\_lock\\_%' OR option_name LIKE 'dbd\\_x\\_job\\_%' OR option_name LIKE 'dbd\\_x\\_lock\\_%' OR option_name LIKE 'dbd\\_x\\_count\\_%' OR option_name LIKE 'dbd\\_th\\_job\\_%' OR option_name LIKE 'dbd\\_th\\_lock\\_%'" );

$u   = wp_upload_dir();
$dir = trailingslashit( $u['basedir'] ) . 'down-by-down';
if ( is_dir( $dir ) ) {
	foreach ( glob( $dir . '/plays/*.json' ) ?: array() as $f ) { @unlink( $f ); }
	@rmdir( $dir . '/plays' );
	foreach ( glob( $dir . '/*.json' ) ?: array() as $f ) { @unlink( $f ); }
	@rmdir( $dir );
}
