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
	register_setting( 'dbd', DBD_Seo::OPT_HOME_DESC, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ) );  /* the home page's description (2.7.0) */
	register_setting( 'dbd_posts', DBD_Post::OPT_ON, array( 'type' => 'boolean', 'sanitize_callback' => 'rest_sanitize_boolean', 'default' => true ) );  /* the weekly post (SCOPE §46) */
	register_setting( 'dbd_posts', DBD_Post::OPT_FORM, array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => DBD_Post::FORM ) );
	/* team news (SCOPE §49): the source groups switched on; an empty save switches every source off */
	/* the Tuesday email (SCOPE §48): one array; a blank API key keeps the saved one */
	register_setting( 'dbd_email', DBD_Email::OPT, array( 'type' => 'array', 'sanitize_callback' => array( 'DBD_Email', 'sanitize' ) ) );
	register_setting( 'dbd_news', DBD_News::OPT_SRC, array( 'type' => 'array', 'sanitize_callback' => function ( $v ) { return array_values( array_intersect( array_map( 'strval', (array) $v ), array_keys( DBD_News::groups() ) ) ); } ) );
} );

add_action( 'admin_post_dbd_news_now', function () {
	check_admin_referer( 'dbd_news_now' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	delete_transient( 'dbd_news_lock' );
	DBD_News::run();
	wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down', 'news' => 1 ), admin_url( 'admin.php' ) ) . '#dbd-news' );
	exit;
} );

add_action( 'admin_post_dbd_clear_cards', function () {
	check_admin_referer( 'dbd_clear_cards' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	$r = DBD_Card::prune( true );
	wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down', 'cleared' => (int) $r[0] ), admin_url( 'admin.php' ) ) );
	exit;
} );

add_action( 'admin_post_dbd_write_past', function () {
	check_admin_referer( 'dbd_write_past' );
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
	if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); }  /* each post draws its week's share card */
	$r = DBD_Post::backfill();
	$new = count( array_filter( $r, function ( $x ) { return $x === 'new'; } ) );
	wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down', 'written' => $new ), admin_url( 'admin.php' ) ) . '#dbd-weekly' );
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
		<?php if ( isset( $_GET['written'] ) ) : ?>
			<div class="notice notice-success"><p><?php $wn = (int) $_GET['written']; echo esc_html( $wn ? $wn . ' weekly post' . ( $wn === 1 ? '' : 's' ) . ' written.' : 'No new posts: every finished week already has one, or was deleted.' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['news'] ) ) : $ns = get_option( DBD_News::OPT_STATUS ); ?>
			<div class="notice notice-success"><p><?php echo esc_html( is_array( $ns ) ? 'News fetched in ' . $ns['secs'] . ' seconds.' : 'News fetched.' ); ?></p></div>
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
				<tr><th scope="row"><label for="dbd_home_desc">Home page description</label></th>
					<td><textarea name="<?php echo esc_attr( DBD_Seo::OPT_HOME_DESC ); ?>" id="dbd_home_desc" rows="3" class="large-text" placeholder="<?php echo esc_attr( DBD_Seo::HOME_DESC ); ?>"><?php echo esc_textarea( (string) get_option( DBD_Seo::OPT_HOME_DESC, '' ) ); ?></textarea>
					<p class="description">What search results and shared links show under the home page's title, when the home is a page you built (the app on its own page). Empty uses the text shown. An SEO plugin, if you add one, takes over.</p></td></tr>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>

		<h2 id="dbd-weekly">Weekly post</h2>
		<p>Every finished week becomes a post in the <b><?php echo esc_html( DBD_Post::CAT_NAME ); ?></b> category, written from the week's data and scheduled for the Tuesday after the week at 8:00 AM Eastern, so you can read and edit it first. Until it publishes, a later sync refreshes its numbers; once you edit it or it publishes, it is never changed again. A deleted post is never written again.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'dbd_posts' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Write the weekly post</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( DBD_Post::OPT_ON ); ?>" value="1" <?php checked( DBD_Post::on() ); ?>> Write each finished week's post after the sync that finishes it</label></td></tr>
				<tr><th scope="row"><label for="dbd_post_form">Signup under each post</label></th>
					<td><input name="<?php echo esc_attr( DBD_Post::OPT_FORM ); ?>" id="dbd_post_form" type="text" class="regular-text code" value="<?php echo esc_attr( get_option( DBD_Post::OPT_FORM, DBD_Post::FORM ) ); ?>">
					<p class="description">A form shortcode shown under each weekly post on the website, not in the feed. Leave it empty for none.</p></td></tr>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>
		<?php $pm = DBD_Post::map(); $cw = array_values( array_filter( DBD_Week::weeks(), function ( $w ) { $f = DBD_Week::file( $w ); return $f && ! empty( $f['complete'] ); } ) ); ?>
		<table class="widefat striped" style="max-width:720px"><thead><tr><th>Week</th><th>Post</th><th>State</th></tr></thead><tbody>
			<?php foreach ( array_reverse( $cw ) as $w ) : $pp = isset( $pm[ $w ] ) ? get_post( (int) $pm[ $w ] ) : null; ?>
			<tr><td><?php echo esc_html( DBD_Router::week_label( $w ) ); ?></td>
				<td><?php echo $pp ? '<a href="' . esc_url( get_edit_post_link( $pp->ID ) ) . '">' . esc_html( get_the_title( $pp ) ) . '</a>' : '–'; ?></td>
				<td><?php
				if ( ! isset( $pm[ $w ] ) ) { echo 'not written'; }
				elseif ( ! $pp || $pp->post_status === 'trash' ) { echo 'deleted (won\'t be written again)'; }
				elseif ( $pp->post_status === 'future' ) { echo esc_html( 'scheduled for ' . get_date_from_gmt( $pp->post_date_gmt, 'D M j, g:i A' ) ); }
				else { echo esc_html( $pp->post_status === 'publish' ? 'published ' . get_date_from_gmt( $pp->post_date_gmt, 'M j' ) : $pp->post_status ); }
				?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $cw ) : ?><tr><td colspan="3">No finished week yet this season.</td></tr><?php endif; ?>
		</tbody></table>
		<?php $missing = count( array_filter( $cw, function ( $w ) use ( $pm ) { return ! isset( $pm[ $w ] ); } ) ); ?>
		<?php if ( $missing ) : ?>
		<p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Write a post for each of the <?php echo (int) $missing; ?> finished week(s) without one? Each is dated the Tuesday after its week and publishes with that date.');">
			<?php wp_nonce_field( 'dbd_write_past' ); ?><input type="hidden" name="action" value="dbd_write_past"><button class="button button-primary">Write past weeks (<?php echo (int) $missing; ?>)</button></form></p>
		<?php endif; ?>

		<h2 id="dbd-email">Tuesday email</h2>
		<?php
		$eo = DBD_Email::opts(); $acct = $eo['key'] !== '' ? DBD_Email::cached( 'account' ) : null;
		$lists = $eo['key'] !== '' ? DBD_Email::cached( 'contacts/lists?limit=50' ) : null; $tpls = $eo['key'] !== '' ? DBD_Email::cached( 'smtp/templates?templateStatus=true&limit=100' ) : null;
		$ll = is_array( $lists[1]['lists'] ?? null ) ? $lists[1]['lists'] : array(); $tl = is_array( $tpls[1]['templates'] ?? null ) ? $tpls[1]['templates'] : array();
		usort( $tl, function ( $x, $y ) { return array( ( $y['tag'] ?? '' ) === 'optin', $y['id'] ?? 0 ) <=> array( ( $x['tag'] ?? '' ) === 'optin', $x['id'] ?? 0 ); } );
		$es = get_option( DBD_Email::OPT_SUB ); $el = (array) get_option( DBD_Email::OPT_LOG, array() ); $lp = DBD_Email::latest();
		$opt = DBD_Email::OPT;
		?>
		<p>Each week post, as it publishes on its Tuesday, is emailed to your Brevo list: the whole post, styled for email, with links back to the site. It sends once; editing or republishing never sends it again, and a post published more than a day after its date (a backfilled week, a past season) never sends. The post's own <b>Tuesday email</b> box has "Don't email this one" and a test button. New signups from the form get Brevo's confirmation email and join the list only after they click it. WordPress keeps no addresses.</p>
		<table class="widefat striped" style="max-width:720px"><tbody>
			<tr><td style="width:160px">Brevo</td><td><?php
			if ( $eo['key'] === '' ) { echo 'Not connected. Paste an API key below (Brevo › your name › SMTP &amp; API › API keys).'; }
			elseif ( $acct[2] ) { echo '<span style="color:#b32d2e">' . esc_html( $acct[2] ) . '</span>'; }
			else { echo esc_html( 'Connected as ' . ( $acct[1]['email'] ?? '?' ) . ( ! empty( $acct[1]['plan'][0]['type'] ) ? ' (' . $acct[1]['plan'][0]['type'] . ' plan)' : '' ) ); }
			?></td></tr>
			<tr><td>Sending</td><td><?php echo DBD_Email::ready() ? 'On: the next week post to publish is emailed.' : ( $eo['on'] ? 'Switched on, but it needs an API key and a list.' : 'Off. Nothing is emailed and the form adds nobody until you switch it on.' ); ?></td></tr>
			<tr><td>Signups</td><td><?php
			if ( (int) $eo['tpl'] < 1 ) { echo 'Waiting for the confirmation template, so new signups aren\'t passed to Brevo yet.'; }
			else { echo esc_html( is_array( $es ) ? (int) ( $es['ok'] ?? 0 ) . ' confirmation email' . ( (int) ( $es['ok'] ?? 0 ) === 1 ? '' : 's' ) . ' sent' . ( ! empty( $es['last'] ) ? ', the last ' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $es['last'] ), 'M j, g:i A' ) : '' ) . '.' : 'None yet.' ); }
			if ( is_array( $es ) && ! empty( $es['last_err'] ) ) { echo '<br><span style="color:#b32d2e">' . esc_html( (int) $es['err'] . ' failed. The last, ' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $es['last_err'][0] ), 'M j, g:i A' ) . ': ' . $es['last_err'][1] ) . '</span>'; }
			?></td></tr>
		</tbody></table>
		<form method="post" action="options.php">
			<?php settings_fields( 'dbd_email' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Send the email</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[on]" value="1" <?php checked( (bool) $eo['on'] ); ?>> Email each week post to the list when it publishes, and pass signups to Brevo</label></td></tr>
				<tr><th scope="row"><label for="dbd_e_key">Brevo API key</label></th>
					<td><input name="<?php echo esc_attr( $opt ); ?>[key]" id="dbd_e_key" type="password" class="regular-text code" autocomplete="off" value="" placeholder="<?php echo esc_attr( $eo['key'] !== '' ? 'saved, ending ' . substr( $eo['key'], -4 ) : 'xkeysib-…' ); ?>">
					<?php if ( $eo['key'] !== '' ) : ?><label style="margin-left:8px"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[forget]" value="1"> Remove the key</label><?php endif; ?>
					<p class="description">Leave it empty to keep the saved key.</p></td></tr>
				<tr><th scope="row"><label for="dbd_e_list">List</label></th>
					<td><?php if ( $ll ) : ?><select name="<?php echo esc_attr( $opt ); ?>[list]" id="dbd_e_list"><option value="0">Choose a list</option><?php foreach ( $ll as $l ) : ?><option value="<?php echo (int) $l['id']; ?>" <?php selected( (int) $eo['list'], (int) $l['id'] ); ?>><?php echo esc_html( $l['name'] . ' (' . (int) ( $l['uniqueSubscribers'] ?? $l['totalSubscribers'] ?? 0 ) . ')' ); ?></option><?php endforeach; ?></select>
					<?php else : ?><input name="<?php echo esc_attr( $opt ); ?>[list]" id="dbd_e_list" type="number" min="0" class="small-text" value="<?php echo (int) $eo['list']; ?>"><?php endif; ?>
					<p class="description">The Brevo list the email goes to and signups join. Create one called "Week in review" in Brevo (Contacts › Lists), then save here to see it.</p></td></tr>
				<tr><th scope="row"><label for="dbd_e_tpl">Confirmation template</label></th>
					<td><?php if ( $tl ) : ?><select name="<?php echo esc_attr( $opt ); ?>[tpl]" id="dbd_e_tpl"><option value="0">Choose a template</option><?php foreach ( $tl as $t ) : ?><option value="<?php echo (int) $t['id']; ?>" <?php selected( (int) $eo['tpl'], (int) $t['id'] ); ?>><?php echo esc_html( $t['name'] . ( ( $t['tag'] ?? '' ) === 'optin' ? ' (optin)' : '' ) ); ?></option><?php endforeach; ?></select>
					<?php else : ?><input name="<?php echo esc_attr( $opt ); ?>[tpl]" id="dbd_e_tpl" type="number" min="0" class="small-text" value="<?php echo (int) $eo['tpl']; ?>"><?php endif; ?>
					<p class="description">The "Confirm your email" message Brevo sends each signup. Make it from the HTML below.</p>
					<details style="margin-top:8px"><summary>The confirmation email's HTML</summary>
					<p class="description">In Brevo: Campaigns › Templates › New template, choose to paste your own code and paste this. In the template's settings, give it the tag <code>optin</code>, then save and activate it. Its button links to <code>{{ doubleoptin }}</code>, Brevo's confirm link; after the click, Brevo opens your newest week post with a "You're in" note.</p>
					<textarea readonly rows="8" class="large-text code" onclick="this.select()"><?php echo esc_textarea( DBD_Email::confirm_html() ); ?></textarea></details></td></tr>
				<tr><th scope="row"><label for="dbd_e_fn">From</label></th>
					<td><input name="<?php echo esc_attr( $opt ); ?>[from_name]" id="dbd_e_fn" type="text" class="regular-text" value="<?php echo esc_attr( $eo['from_name'] ); ?>"> <input name="<?php echo esc_attr( $opt ); ?>[from_email]" type="email" class="regular-text" value="<?php echo esc_attr( $eo['from_email'] ); ?>" aria-label="From address">
					<p class="description">The address must be a sender in Brevo (Senders, Domains &amp; Dedicated IPs).</p></td></tr>
				<tr><th scope="row"><label for="dbd_e_re">Reply-to</label></th>
					<td><input name="<?php echo esc_attr( $opt ); ?>[reply]" id="dbd_e_re" type="email" class="regular-text" value="<?php echo esc_attr( $eo['reply'] ); ?>"></td></tr>
				<tr><th scope="row"><label for="dbd_e_ad">Postal address</label></th>
					<td><input name="<?php echo esc_attr( $opt ); ?>[address]" id="dbd_e_ad" type="text" class="large-text" value="<?php echo esc_attr( $eo['address'] ); ?>"><p class="description">In every email's footer, as US law requires.</p></td></tr>
				<tr><th scope="row"><label for="dbd_e_fm">Signup form</label></th>
					<td><input name="<?php echo esc_attr( $opt ); ?>[form]" id="dbd_e_fm" type="number" min="1" class="small-text" value="<?php echo (int) $eo['form']; ?>"> <span class="description">The WPForms form's ID. Change its Confirmation message (Settings › Confirmations) to "Almost there. Check your inbox for a confirmation link."</span></td></tr>
				<tr><th scope="row"><label for="dbd_e_te">Tests go to</label></th>
					<td><input name="<?php echo esc_attr( $opt ); ?>[test]" id="dbd_e_te" type="email" class="regular-text" value="<?php echo esc_attr( $eo['test'] ); ?>" placeholder="<?php echo esc_attr( DBD_Email::test_to() ); ?>"> <span class="description">Empty uses the reply-to.</span></td></tr>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>
		<?php if ( $lp ) : ?>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dbd_email_test' ), 'dbd_email_test' ) ); ?>">Send a test of "<?php echo esc_html( html_entity_decode( $lp->post_title, ENT_QUOTES, 'UTF-8' ) ); ?>" to <?php echo esc_html( DBD_Email::test_to() ); ?></a></p>
		<?php endif; ?>
		<table class="widefat striped" style="max-width:720px"><thead><tr><th>When</th><th>Post</th><th>Result</th><th>To</th></tr></thead><tbody>
			<?php foreach ( $el as $r ) : ?>
			<tr><td><?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $r['at'] ), 'M j, g:i A' ) ); ?></td>
				<td><a href="<?php echo esc_url( (string) get_edit_post_link( (int) $r['post'] ) ); ?>"><?php echo esc_html( $r['title'] ); ?></a></td>
				<td><?php echo esc_html( $r['result'] . ( $r['camp'] ? ' (Brevo campaign ' . $r['camp'] . ')' : '' ) ); ?></td><td><?php echo $r['n'] ? (int) $r['n'] . ' subscribers' : '–'; ?></td></tr>
			<?php endforeach; ?>
			<?php if ( ! $el ) : ?><tr><td colspan="4">Nothing sent yet.</td></tr><?php endif; ?>
		</tbody></table>

		<h2 id="dbd-news">Team news</h2>
		<p>Every 6 hours (about 12 and 6, morning and evening, Eastern) the site reads the news feeds below, keeps the last 7 days of stories that belong to a team, and shows them on each team's players page, with the newest one on its offense and defense pages. Headline, source and time only, linking out. Each fetch adds to the stories already kept, so a feed that fails or sends an old copy of itself loses nothing.</p>
		<?php
		$ns = get_option( DBD_News::OPT_STATUS ); $nf = DBD_News::feeds(); $nnext = wp_next_scheduled( DBD_News::HOOK );
		$nst = is_array( $ns ) ? (array) $ns['feeds'] : array(); $nok = count( array_filter( $nst, function ( $x ) { return ! empty( $x['ok'] ); } ) );
		$nprob = array_filter( $nst, function ( $x ) { return empty( $x['ok'] ) || ! empty( $x['err'] ); } );
		?>
		<table class="widefat striped" style="max-width:720px"><tbody>
			<tr><td style="width:160px">Last fetch</td><td><?php echo is_array( $ns ) ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $ns['at'] ), 'M j, g:i A' ) . ': ' . $nok . ' of ' . count( $nst ) . ' feeds read in ' . $ns['secs'] . ' seconds' ) : 'not yet'; ?></td></tr>
			<tr><td>Next fetch</td><td><?php echo $nnext ? esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $nnext ), 'M j, g:i A' ) ) : 'not scheduled'; ?></td></tr>
			<tr><td>Feeds to check</td><td><?php if ( ! $nprob ) { echo is_array( $ns ) ? 'None: every feed answered with recent stories.' : '–'; } else { $li = array(); foreach ( $nprob as $k => $x ) { $li[] = '<a href="' . esc_url( $nf[ $k ][0] ?? '' ) . '">' . esc_html( ( $nf[ $k ][3] ?? $k ) . ( ! empty( $nf[ $k ][2] ) && ( $nf[ $k ][1] ?? '' ) !== 'site' ? ' (' . $nf[ $k ][2] . ')' : '' ) ) . '</a>: ' . esc_html( $x['err'] ?: 'failed' ) . ( empty( $x['ok'] ) && ! empty( $x['last'] ) ? esc_html( ', last read ' . get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) $x['last'] ), 'M j' ) ) : '' ); } echo implode( '<br>', $li ); } ?></td></tr>
		</tbody></table>
		<form method="post" action="options.php">
			<?php settings_fields( 'dbd_news' ); ?>
			<table class="form-table" role="presentation">
				<tr><th scope="row">Sources</th><td>
				<?php foreach ( DBD_News::groups() as $g => $lab ) : ?>
					<label style="display:block;margin:0 0 4px"><input type="checkbox" name="<?php echo esc_attr( DBD_News::OPT_SRC ); ?>[]" value="<?php echo esc_attr( $g ); ?>" <?php checked( DBD_News::on( $g ) ); ?>> <?php echo esc_html( $lab ); ?></label>
				<?php endforeach; ?>
				<p class="description">A source switched off is skipped from the next fetch on, and its stories leave the pages then. The Athletic's links are labelled "Subscriber". Switch everything off to hide the news.</p></td></tr>
			</table>
			<?php submit_button( 'Save' ); ?>
		</form>
		<p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><?php wp_nonce_field( 'dbd_news_now' ); ?><input type="hidden" name="action" value="dbd_news_now"><button class="button">Fetch news now</button></form></p>

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
