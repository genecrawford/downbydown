<?php
/**
 * Plugin Name: Down by Down
 * Plugin URI:  https://downbydown.com
 * Description: How every NFL offense actually plays, drawn on the field from public play-by-play. Put [down_by_down] on a page and that page becomes the app.
 * Version:     1.0.0
 * Author:      Period Three
 * Author URI:  https://period-three.com
 * License:     GPL-2.0-or-later
 * Text Domain: down-by-down
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'DBD_VERSION', '1.0.0' );
define( 'DBD_FILE', __FILE__ );
define( 'DBD_PATH', plugin_dir_path( __FILE__ ) );
define( 'DBD_URL', plugin_dir_url( __FILE__ ) );
define( 'DBD_DEFAULT_SOURCE', 'https://raw.githubusercontent.com/genecrawford/downbydown/main/data/' );

require_once DBD_PATH . 'includes/class-dbd-data.php';
require_once DBD_PATH . 'includes/class-dbd-marks.php';
require_once DBD_PATH . 'includes/class-dbd-metrics.php';
require_once DBD_PATH . 'includes/class-dbd-takeaways.php';
require_once DBD_PATH . 'includes/class-dbd-sync.php';
require_once DBD_PATH . 'includes/class-dbd-router.php';
if ( is_admin() ) {
	require_once DBD_PATH . 'includes/admin/settings.php';
}

register_activation_hook( __FILE__, function () {
	DBD_Sync::ensure_token();
	DBD_Sync::schedule();
	// First sync in the background right away so the page has something to show.
	wp_schedule_single_event( time() + 30, 'dbd_sync_event' );
} );
register_deactivation_hook( __FILE__, function () {
	DBD_Sync::unschedule();
} );

add_action( 'plugins_loaded', function () {
	DBD_Sync::init();
	DBD_Router::init();
} );
