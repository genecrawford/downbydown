<?php
/**
 * Pulls the data files from the GitHub repo into uploads/down-by-down/.
 * Triggered by the GitHub Action (POST /wp-json/dbd/v1/sync with the token), by WP-Cron daily,
 * by the Sync now button on the settings page, and once after each plugin update (1.13.1). Downloads only files whose manifest hash changed,
 * validates the JSON, then renames into place so a bad download never replaces a good file.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Sync {

	const OPT_SOURCE = 'dbd_source_url';
	const OPT_TOKEN  = 'dbd_sync_token';
	const OPT_LOG    = 'dbd_sync_log';
	const HOOK       = 'dbd_sync_event';
	const HOOK_UPDATE = 'dbd_sync_after_update';
	const HOOK_ARCH   = 'dbd_sync_archive';  /* an archived season's pull that ran out of time continues here (SCOPE §44) */
	const OPT_VER    = 'dbd_synced_version';  /* the plugin version that last ran a successful sync */
	const AFTER      = 120;
	const OPT_ARCH   = 'dbd_archive_hashes';  /* each archived season's manifest hash as last synced (SCOPE §44) */
	/* the files the site takes from the repo, the same list for the current season and an archived one */
	const ALLOW = '#^(plays/[A-Z]{2,3}|players/(?:[A-Z]{2,3}|index|mini|league|movers|leaders|career/[A-Z]{2,3})|wp/w\d{1,2}|box/w\d{1,2}|defense/(?:[A-Z]{2,3}|league)|plays/def/[A-Z]{2,3}|week/w\d{1,2}|lineups|series|fantasy|teams|metrics|schedule)\.json$#';                   /* seconds after an update: hosts can keep serving the old compiled code for a minute or so */

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		add_action( self::HOOK_UPDATE, array( __CLASS__, 'after_update' ) );
		add_action( self::HOOK_ARCH, function () { self::sync( 'archive' ); } );
		if ( ! wp_next_scheduled( self::HOOK ) ) { self::schedule(); }
		/* a new version that has not synced yet (an upload, an update) pulls the data on its own a couple of minutes later, so files
		   it newly knows about arrive without pressing Sync now; the sync the data build triggers may have run on the old version */
		if ( get_option( self::OPT_VER ) !== DBD_VERSION && ! wp_next_scheduled( self::HOOK_UPDATE ) && ! get_transient( 'dbd_sync_upd_' . DBD_VERSION ) ) { wp_schedule_single_event( time() + self::AFTER, self::HOOK_UPDATE ); }
	}

	/** The post-update sync: skipped when this version has already synced (Sync now, the webhook or the daily pull got there first). */
	public static function after_update() {
		if ( get_option( self::OPT_VER ) === DBD_VERSION ) { return; }
		set_transient( 'dbd_sync_upd_' . DBD_VERSION, 1, HOUR_IN_SECONDS );  /* a failed try waits an hour before the next, per version */
		self::sync( 'update' );
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// 11:30 UTC daily: after the Action's 10:15 run, as the fallback.
			$next = strtotime( 'tomorrow 11:30 UTC' );
			wp_schedule_event( $next, 'daily', self::HOOK );
		}
	}

	public static function unschedule() {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::HOOK_UPDATE );
	}

	public static function source() {
		$u = get_option( self::OPT_SOURCE );
		return trailingslashit( $u ?: DBD_DEFAULT_SOURCE );
	}

	public static function ensure_token() {
		if ( ! get_option( self::OPT_TOKEN ) ) {
			update_option( self::OPT_TOKEN, wp_generate_password( 40, false, false ), false );
		}
		return get_option( self::OPT_TOKEN );
	}

	public static function rest() {
		register_rest_route( 'dbd/v1', '/sync', array(
			'methods'             => 'POST',
			'permission_callback' => function ( $req ) {
				$given = (string) $req->get_header( 'x-dbd-token' );
				$token = (string) get_option( self::OPT_TOKEN );
				return $token !== '' && hash_equals( $token, $given );
			},
			'callback'            => function () {
				$r = self::sync( 'webhook' );
				return rest_ensure_response( $r );
			},
		) );
		register_rest_route( 'dbd/v1', '/status', array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$m = DBD_Data::manifest();
				return rest_ensure_response( array( 'season' => $m['season'] ?? null, 'week' => $m['week'] ?? null, 'through_week' => $m['through_week'] ?? null, 'built_at' => $m['built_at'] ?? null ) );
			},
		) );
	}

	/** Run a sync. Returns a summary array and appends it to the log. */
	public static function sync( $trigger = 'cron' ) {
		$start = microtime( true );
		$dir   = DBD_Data::dir();
		$base  = self::source();
		$out   = array( 'time' => current_time( 'mysql', true ), 'trigger' => $trigger, 'ok' => false, 'changed' => array(), 'error' => '' );

		$res = wp_remote_get( $base . 'manifest.json', array( 'timeout' => 20, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
			$out['error'] = 'Could not fetch manifest: ' . ( is_wp_error( $res ) ? $res->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code( $res ) );
			return self::finish( $out, $start );
		}
		$remote = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $remote['files'] ) || ! is_array( $remote['files'] ) ) {
			$out['error'] = 'Manifest had no file list.';
			return self::finish( $out, $start );
		}
		$local = DBD_Data::manifest();
		$have  = $local['files'] ?? array();

		foreach ( $remote['files'] as $rel => $hash ) {
			if ( ! preg_match( self::ALLOW, $rel ) ) { continue; }
			$path = $dir . '/' . $rel;
			if ( isset( $have[ $rel ] ) && $have[ $rel ] === $hash && file_exists( $path ) ) { continue; }
			$get = wp_remote_get( $base . $rel, array( 'timeout' => 30 ) );
			if ( is_wp_error( $get ) || wp_remote_retrieve_response_code( $get ) !== 200 ) {
				$out['error'] .= "Failed $rel. ";
				continue;
			}
			$body = wp_remote_retrieve_body( $get );
			if ( json_decode( $body, true ) === null ) { $out['error'] .= "Bad JSON in $rel. "; continue; }
			wp_mkdir_p( dirname( $path ) );
			$tmp = $path . '.tmp';
			if ( file_put_contents( $tmp, $body ) === false || ! rename( $tmp, $path ) ) { $out['error'] .= "Could not write $rel. "; @unlink( $tmp ); continue; }
			$out['changed'][] = $rel;
		}

		// Write the manifest last, only if every changed file landed.
		if ( $out['error'] === '' ) {
			file_put_contents( $dir . '/manifest.json', wp_json_encode( $remote ) );
			$out['ok'] = true;
			update_option( self::OPT_VER, DBD_VERSION );  /* autoloaded: init() reads it on every request */
			self::recompute_stats();
			if ( class_exists( 'DBD_Card' ) ) { DBD_Data::flush(); DBD_Card::prune(); }  /* the cards for earlier weeks and versions go (SCOPE §43) */
			$out['archive'] = self::sync_archive( $remote, $base, $start );
		}
		return self::finish( $out, $start );
	}

	/**
	 * Archived seasons (SCOPE §44): the current manifest lists each with its manifest's hash. A season that is new or changed is pulled
	 * into archive/{season}/: files already on disk with the right hash are kept, so a pull that runs out of time picks up where it
	 * stopped. One season per run and about 40 seconds of it; an unfinished pull schedules the next run in a minute.
	 */
	private static function sync_archive( $remote, $base, $start ) {
		$want = (array) ( $remote['archive'] ?? array() ); if ( ! $want ) { return ''; }
		$have = (array) get_option( self::OPT_ARCH, array() ); $root = DBD_Data::root_dir();
		foreach ( $want as $s => $hash ) {
			$s = (int) $s; if ( $s < 1999 || $s > 2100 ) { continue; }
			$dir = $root . '/archive/' . $s;
			if ( ( $have[ $s ] ?? '' ) === $hash && is_readable( $dir . '/manifest.json' ) ) { continue; }
			$res = wp_remote_get( $base . 'archive/' . $s . '/manifest.json', array( 'timeout' => 20, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
			if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) { return $s . ': no manifest'; }
			$body = wp_remote_retrieve_body( $res ); $m = json_decode( $body, true ); if ( empty( $m['files'] ) ) { return $s . ': empty manifest'; }
			$got = 0; $left = 0;
			foreach ( $m['files'] as $rel => $fh ) {
				if ( ! preg_match( self::ALLOW, $rel ) ) { continue; }
				$path = $dir . '/' . $rel;
				if ( is_readable( $path ) && sha1_file( $path ) === $fh ) { continue; }
				if ( microtime( true ) - $start > 40 ) { $left++; continue; }
				$get = wp_remote_get( $base . 'archive/' . $s . '/' . $rel, array( 'timeout' => 30 ) );
				if ( is_wp_error( $get ) || wp_remote_retrieve_response_code( $get ) !== 200 ) { $left++; continue; }
				$b = wp_remote_retrieve_body( $get ); if ( json_decode( $b, true ) === null ) { $left++; continue; }
				wp_mkdir_p( dirname( $path ) ); $tmp = $path . '.tmp';
				if ( file_put_contents( $tmp, $b ) === false || ! rename( $tmp, $path ) ) { @unlink( $tmp ); $left++; continue; }
				$got++;
			}
			if ( $left ) {  /* not done: the next run continues */
				if ( ! wp_next_scheduled( self::HOOK_ARCH ) ) { wp_schedule_single_event( time() + 60, self::HOOK_ARCH ); }
				return $s . ': ' . $got . ' files, ' . $left . ' to go';
			}
			file_put_contents( $dir . '/manifest.json', $body );  /* last, as for the current season */
			$have[ $s ] = $hash; update_option( self::OPT_ARCH, $have, false );
			return $s . ': ' . $got . ' files, complete';
		}
		return '';
	}

	private static function finish( $out, $start ) {
		$out['seconds'] = round( microtime( true ) - $start, 1 );
		$log = get_option( self::OPT_LOG, array() );
		array_unshift( $log, $out );
		update_option( self::OPT_LOG, array_slice( $log, 0, 20 ), false );
		return $out;
	}

	/** Totals the hub shows: snaps charted across all 32 play files. Done once per sync, not per page view. */
	public static function recompute_stats() {
		$dir = DBD_Data::dir() . '/plays';
		$snaps = 0;
		foreach ( glob( $dir . '/*.json' ) ?: array() as $f ) {
			$j = json_decode( file_get_contents( $f ), true );
			$snaps += is_array( $j['plays'] ?? null ) ? count( $j['plays'] ) : 0;
		}
		update_option( 'dbd_stats', array( 'snaps' => $snaps, 'at' => time() ), false );
	}

	public static function log() {
		return get_option( self::OPT_LOG, array() );
	}
}
