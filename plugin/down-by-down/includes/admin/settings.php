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
	register_setting( 'dbd', 'dbd_logos', array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => false ) );
	register_setting( 'dbd', DBD_Urls::OPT, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );  /* readable URLs (SCOPE §43) */
	register_setting( 'dbd', DBD_Urls::OPT_ROOT, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );  /* app paths at the root while the app sits on its own page */
} );

add_action( 'admin_post_dbd_clear_cards', function () {
	check_admin_referer( 'dbd_clear_cards' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	$r = DBD_Card::prune( true );
	wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down', 'cleared' => (int) $r[0] ), admin_url( 'admin.php' ) ) );
	exit;
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
		<?php if ( isset( $_GET['cleared'] ) ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( (int) $_GET['cleared'] . ' cached cards cleared. They are drawn again the next time a page is shared.' ); ?></p></div>
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
			<tr><td>After an update</td><td><?php $sv = (string) get_option( DBD_Sync::OPT_VER ); $su = wp_next_scheduled( DBD_Sync::HOOK_UPDATE ); echo $sv === DBD_VERSION ? 'This version (' . esc_html( DBD_VERSION ) . ') has synced.' : ( $su ? 'A sync for ' . esc_html( DBD_VERSION ) . ' runs at ' . esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $su ), 'g:i A' ) ) . '.' : 'Not synced on ' . esc_html( DBD_VERSION ) . ' yet.' ); ?> A new version pulls the data on its own about two minutes after it is installed.</td></tr>
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
				<tr><th scope="row">Team logos</th>
					<td><label><input type="checkbox" name="dbd_logos" value="1" <?php checked( DBD_Data::logos() ); ?>> Show NFL team logos in place of the drawn team tiles</label>
					<p class="description">Off by default. The logos are hotlinked from the ESPN image CDN using the URLs nflverse publishes; they are league and team property and not licensed to this site. When on, each logo loads lazily over the drawn tile, which stays if a logo fails to load. Run Sync now once after turning this on if the team data predates 1.4.0.</p></td></tr>
				<tr><th scope="row">Readable URLs</th>
					<td><?php $plain = ! get_option( 'permalink_structure' ); ?><label><input type="checkbox" name="<?php echo esc_attr( DBD_Urls::OPT ); ?>" value="1" <?php checked( (bool) get_option( DBD_Urls::OPT, true ) ); ?>> Use paths like <code>/team/min/</code> and <code>/player/justin-jefferson/</code></label>
					<p class="description">On by default. Old <code>?team=</code> and <code>?player=</code> links keep working: they redirect to the new paths. Turn this off to go back to query strings.
					<?php if ( $plain ) : ?><strong>This site uses plain permalinks, so the app keeps query strings.</strong> Choose any other structure under Settings › Permalinks to use paths.<?php else : ?>Permalinks: <?php echo esc_html( get_option( 'permalink_structure' ) ); ?>, so paths work here<?php echo DBD_Urls::page_id() ? '' : ', once a page holds <code>[down_by_down]</code>'; ?>.<?php endif; ?></p></td></tr>
				<tr><th scope="row">Paths at the site root</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( DBD_Urls::OPT_ROOT ); ?>" value="1" <?php checked( DBD_Urls::root() ); ?>> Keep <code>/team/min/</code>, <code>/player/…/</code> and <code>/2025/</code> at the root when the app page isn't the front page</label>
					<p class="description"><?php
					$aid = DBD_Urls::page_id();
					if ( ! $aid ) { echo 'No page holds <code>[down_by_down]</code> yet.'; }
					elseif ( DBD_Urls::is_front() ) { echo 'The app is the front page, so its paths are at the root either way.'; }
					else { echo 'The app page is <a href="' . esc_url( get_permalink( $aid ) ) . '">' . esc_html( get_permalink( $aid ) ) . '</a>: the hub lives there. ' . ( DBD_Urls::on() ? 'Test a path: <a href="' . esc_url( DBD_Router::url( array( 'team' => 'MIN' ) ) ) . '">' . esc_html( DBD_Router::url( array( 'team' => 'MIN' ) ) ) . '</a>.' : '' ) . ' Leave this on so links shared before the move keep working; a real page named teams, players, leaders, week or matchups would be hidden behind the app\'s paths. Only one page should hold <code>[down_by_down]</code>.'; }
					?></p></td></tr>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>

		<h2>Share cards</h2>
		<?php list( $cn, $cb ) = DBD_Card::stats(); $cp = get_option( 'dbd_card_prune' ); ?>
		<table class="widefat striped" style="max-width:720px"><tbody>
			<tr><td style="width:160px">Cached</td><td><?php echo esc_html( number_format( $cn ) . ' card' . ( $cn === 1 ? '' : 's' ) . ', ' . size_format( $cb, 1 ) ); ?></td></tr>
			<tr><td>Kept</td><td>This data week's cards for <?php echo esc_html( DBD_VERSION ); ?> only. After each sync, cards from earlier weeks or versions, and any older than 14 days, are deleted.</td></tr>
			<tr><td>Last cleanup</td><td><?php echo is_array( $cp ) ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $cp['time'] ), 'M j, g:i A' ) . ': removed ' . number_format( (int) $cp['n'] ) . ' card' . ( (int) $cp['n'] === 1 ? '' : 's' ) . ' (' . size_format( (int) $cp['bytes'], 1 ) . ')' ) : 'not yet'; ?></td></tr>
		</tbody></table>
		<p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Clear every cached card? Each is drawn again the next time its page is shared.');">
			<?php wp_nonce_field( 'dbd_clear_cards' ); ?><input type="hidden" name="action" value="dbd_clear_cards"><button class="button">Clear card cache</button></form></p>

		<h2>Seasons</h2>
		<?php $cs = DBD_Data::current_season(); $am = (array) ( DBD_Data::root_manifest()['archive'] ?? array() ); $ah = (array) get_option( DBD_Sync::OPT_ARCH, array() ); ?>
		<table class="widefat striped" style="max-width:720px"><thead><tr><th>Season</th><th>State</th><th>Files</th><th>Built</th></tr></thead><tbody>
			<tr><td><?php echo (int) $cs; ?></td><td>current</td><td><?php echo count( DBD_Data::root_manifest()['files'] ?? array() ); ?></td><td><?php echo esc_html( DBD_Data::root_manifest()['built_at'] ?? '' ); ?></td></tr>
			<?php foreach ( $am as $s => $h ) : $mf = DBD_Data::root_dir() . '/archive/' . (int) $s . '/manifest.json'; $mj = is_readable( $mf ) ? json_decode( (string) file_get_contents( $mf ), true ) : null; ?>
			<tr><td><?php echo (int) $s; ?></td><td><?php echo $mj && ( $ah[ $s ] ?? '' ) === $h ? 'archived' : 'syncing'; ?></td><td><?php echo $mj ? count( $mj['files'] ?? array() ) : '–'; ?></td><td><?php echo esc_html( $mj['built_at'] ?? '' ); ?></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<p class="description">A finished season is set aside automatically when the next one's first build runs. To add an earlier season (2022 or later), run <b>Build an archived season</b> in the repo's GitHub Actions with that year; the site pulls it on the next sync, a minute or so at a time.</p>

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
			<?php if ( DBD_Urls::on() ) : ?>
			<li>Put <code>[down_by_down]</code> on one page. That page becomes the app: the hub at its URL, <code>team/min/</code> for a team (<code>team/min/defense/</code>, <code>team/min/players/</code>), <code>game/2026-week-8-vikings-at-lions/</code> for a matchup, <code>player/justin-jefferson/</code> for a player, <code>compare/a/b/</code> to compare players, and <code>matchups/</code>, <code>teams/</code>, <code>players/</code>, <code>leaders/</code>, <code>week/8/</code> and <code>my-team/</code>. Link the sidebar menu to these; the old <code>?team=</code> style links redirect to them.</li>
			<?php else : ?>
			<li>Put <code>[down_by_down]</code> on one page. That page becomes the app: the hub at its URL, <code>?team=MIN</code> for a team, <code>?game=…</code> for a matchup, <code>?view=matchups</code>, <code>?view=teams</code> and <code>?view=players</code> for the index views, <code>?player=…</code> for a player, <code>?compare=id,id</code> to compare players.</li>
			<?php endif; ?>
			<li>The app renders only the content column. Build the sidebar in your theme (Divi Theme Builder) and link its menu to the URLs above.</li>
			<li>Body classes tell your CSS which view is showing: <code>dbd-view-hub</code>, <code>dbd-view-team</code>, <code>dbd-view-game</code>, <code>dbd-view-matchups</code>, <code>dbd-view-teams</code>, <code>dbd-view-players</code>, <code>dbd-view-player</code>, <code>dbd-view-compare</code>.</li>
			<li><code>[dbd_count games]</code> and <code>[dbd_count teams]</code> print the small numbers for menu items.</li>
			<li>Live blocks for a page you build in Divi: <code>[dbd_block type="hero-map"]</code> (the week's most-targeted player; <code>player="00-0036322"</code> pins one), <code>[dbd_block type="games"]</code> (this week's games), <code>[dbd_block type="counts"]</code>, <code>[dbd_block type="week-highlights"]</code> (the latest week in three cards) , <code>[dbd_block type="matchup"]</code> (the game of the week with its offense vs defense), <code>[dbd_block type="player-card"]</code> (the week's biggest riser's share card) , <code>[dbd_block type="movers"]</code> (the week's risers in target share), <code>[dbd_block type="fantasy"]</code> (the softest defense at each position), <code>[dbd_block type="leaders"]</code> (leader boards; <code>boards="ppr"</code> for PPR points) , <code>[dbd_block type="teams"]</code> (all 32 teams by division) and <code>[dbd_block type="newsletter-preview"]</code> (the latest finished week as the Tuesday email). Each shows this week's data and updates with every sync.</li>
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
					<td><?php echo esc_html( ( $row['changed'] ? count( $row['changed'] ) . ' files' : 'nothing' ) . ( ! empty( $row['archive'] ) ? ' · archive ' . $row['archive'] : '' ) ); ?></td><td><?php echo esc_html( $row['seconds'] ); ?></td></tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}
