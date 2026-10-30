<?php
/** Settings › Down by Down: data source, sync token, Sync now, status and log. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	// Its own entry in the admin sidebar, just below Comments, with a pennant icon.
	add_menu_page( 'Down by Down', 'Down by Down', 'manage_options', 'down-by-down', 'dbd_settings_page', 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBvbHlnb24gcG9pbnRzPSIyLDIgMTgsMTAgMiwxOCIgZmlsbD0iYmxhY2siLz48cmVjdCB4PSIyIiB5PSIyIiB3aWR0aD0iMyIgaGVpZ2h0PSIxNiIgZmlsbD0iYmxhY2siIG9wYWNpdHk9Ii41NSIvPjwvc3ZnPg==', 26 );
} );

add_action( 'admin_init', function () {
	register_setting( 'dbd', DBD_Sync::OPT_SOURCE, array( 'type' => 'string', 'sanitize_callback' => 'esc_url_raw', 'default' => DBD_DEFAULT_SOURCE ) );
	register_setting( 'dbd', 'dbd_headshots', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
} );

add_action( 'admin_post_dbd_sync_now', function () {
	check_admin_referer( 'dbd_sync_now' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	$r = DBD_Sync::sync( 'manual' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down', 'synced' => $r['ok'] ? 1 : 0 ), admin_url( 'admin.php' ) ) );
	exit;
} );

add_action( 'admin_post_dbd_new_token', function () {
	check_admin_referer( 'dbd_new_token' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	update_option( DBD_Sync::OPT_TOKEN, wp_generate_password( 40, false, false ), false );
	wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down', 'token' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

function dbd_settings_page() {
	$m     = DBD_Data::manifest();
	$log   = DBD_Sync::log();
	$token = DBD_Sync::ensure_token();
	$next  = wp_next_scheduled( DBD_Sync::HOOK );
	$cron_off = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
	?>
	<div class="wrap">
		<h1>Down by Down</h1>
		<?php if ( isset( $_GET['synced'] ) ) : ?>
			<div class="notice notice-<?php echo $_GET['synced'] ? 'success' : 'error'; ?>"><p><?php echo $_GET['synced'] ? 'Synced.' : 'Sync failed. See the log below.'; ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['token'] ) ) : ?>
			<div class="notice notice-warning"><p>New token generated. Update the <code>DBD_SYNC_TOKEN</code> secret in the GitHub repo or the nightly push will stop working.</p></div>
		<?php endif; ?>

		<h2>Current data</h2>
		<table class="widefat striped" style="max-width:720px">
			<tbody>
			<tr><td>Season</td><td><?php echo esc_html( $m['season'] ?? '–' ); ?></td></tr>
			<tr><td>Current week</td><td><?php echo esc_html( $m['week'] ?? '–' ); ?></td></tr>
			<tr><td>Play-by-play through</td><td><?php echo esc_html( isset( $m['through_week'] ) ? 'Week ' . $m['through_week'] : '–' ); ?></td></tr>
			<tr><td>Built</td><td><?php echo esc_html( $m['built_at'] ?? 'never' ); ?> <?php echo esc_html( DBD_Data::updated_text() ? '(' . DBD_Data::updated_text() . ')' : '' ); ?></td></tr>
			<tr><td>Files</td><td><?php echo count( $m['files'] ?? array() ); ?> in <code><?php echo esc_html( DBD_Data::dir() ); ?></code></td></tr>
			<tr><td>Next scheduled pull</td><td><?php echo $next ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'M j, g:i A' ) ) : 'not scheduled'; ?><?php if ( $cron_off ) : ?> <strong>WP-Cron is disabled on this site.</strong> Make sure a real cron hits <code>wp-cron.php</code> or runs <code>wp cron event run <?php echo esc_html( DBD_Sync::HOOK ); ?></code> daily.<?php endif; ?></td></tr>
			</tbody>
		</table>
		<p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
				<?php wp_nonce_field( 'dbd_sync_now' ); ?><input type="hidden" name="action" value="dbd_sync_now">
				<button class="button button-primary">Sync now</button>
			</form>
		</p>

		<h2>Data source</h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'dbd' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row"><label for="dbd_source_url">Folder URL</label></th>
					<td><input name="<?php echo esc_attr( DBD_Sync::OPT_SOURCE ); ?>" id="dbd_source_url" type="url" class="regular-text code" value="<?php echo esc_attr( get_option( DBD_Sync::OPT_SOURCE, DBD_DEFAULT_SOURCE ) ); ?>">
					<p class="description">The <code>data/</code> folder of the GitHub repo, as a raw URL. Must contain <code>manifest.json</code>.</p></td></tr>
				<tr><th scope="row">Player photos</th>
					<td><label><input type="checkbox" name="dbd_headshots" value="1" <?php checked( DBD_Data::headshots() ); ?>> Show NFL headshots on player pages and the Players index</label>
					<p class="description">Off by default. The photos are hotlinked from the NFL's image CDN using the URLs nflverse publishes; they are league property and not licensed to this site. When on, each photo loads lazily over the player's jersey tile and falls back to the tile if the image fails. Turn this off to remove every photo at once.</p></td></tr>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>

		<h2>Push from GitHub</h2>
		<p>After each nightly build the GitHub Action calls this site so new data lands within a minute. In the repo, set a variable <code>DBD_SITE_URL</code> and a secret <code>DBD_SYNC_TOKEN</code>:</p>
		<table class="widefat striped" style="max-width:720px"><tbody>
			<tr><td><code>DBD_SITE_URL</code> (variable)</td><td><code><?php echo esc_html( home_url( '/' ) ); ?></code></td></tr>
			<tr><td><code>DBD_SYNC_TOKEN</code> (secret)</td><td><code><?php echo esc_html( $token ); ?></code></td></tr>
			<tr><td>Endpoint</td><td><code>POST <?php echo esc_html( home_url( '/?rest_route=/dbd/v1/sync' ) ); ?></code> with header <code>X-DBD-Token</code></td></tr>
		</tbody></table>
		<p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Generate a new token? The old one stops working immediately.');">
			<?php wp_nonce_field( 'dbd_new_token' ); ?><input type="hidden" name="action" value="dbd_new_token"><button class="button">Generate a new token</button></form></p>

		<h2>Using it in the theme</h2>
		<ul style="list-style:disc;padding-left:20px;max-width:720px">
			<li>Put <code>[down_by_down]</code> on one page. That page becomes the app: the hub at its URL, <code>?team=MIN</code> for a team, <code>?game=…</code> for a matchup, <code>?view=matchups</code>, <code>?view=teams</code> and <code>?view=players</code> for the index views, <code>?player=…</code> for a player.</li>
			<li>The app renders only the content column. Build the sidebar in your theme (Divi Theme Builder) and link its menu to the URLs above.</li>
			<li>Body classes tell your CSS which view is showing: <code>dbd-view-hub</code>, <code>dbd-view-team</code>, <code>dbd-view-game</code>, <code>dbd-view-matchups</code>, <code>dbd-view-teams</code>, <code>dbd-view-players</code>, <code>dbd-view-player</code>.</li>
			<li><code>[dbd_count games]</code> and <code>[dbd_count teams]</code> print the small numbers for menu items.</li>
			<li>Without a page-builder theme, assign the page template "Down by Down: app page (no theme header)".</li>
		</ul>

		<h2>Sync log</h2>
		<table class="widefat striped" style="max-width:960px">
			<thead><tr><th>When (UTC)</th><th>Trigger</th><th>Result</th><th>Changed</th><th>Seconds</th></tr></thead>
			<tbody>
			<?php if ( ! $log ) : ?><tr><td colspan="5">No syncs yet.</td></tr><?php endif; ?>
			<?php foreach ( $log as $row ) : ?>
				<tr><td><?php echo esc_html( $row['time'] ); ?></td><td><?php echo esc_html( $row['trigger'] ); ?></td>
					<td><?php echo $row['ok'] ? 'ok' : '<span style="color:#b32d2e">' . esc_html( $row['error'] ?: 'failed' ) . '</span>'; ?></td>
					<td><?php echo esc_html( $row['changed'] ? count( $row['changed'] ) . ' files' : 'nothing' ); ?></td><td><?php echo esc_html( $row['seconds'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
