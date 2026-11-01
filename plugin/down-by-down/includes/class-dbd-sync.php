<?php
/**
 * Pulls the data files from the GitHub repo into uploads/down-by-down/.
 * Triggered by the GitHub Action (POST /wp-json/dbd/v1/sync with the token), by WP-Cron daily,
 * and by the Sync now button on the settings page. Downloads only files whose manifest hash changed,
 * validates the JSON, then renames into place so a bad download never replaces a good file.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Sync {

	const OPT_SOURCE = 'dbd_source_url';
	const OPT_TOKEN  = 'dbd_sync_token';
	const OPT_LOG    = 'dbd_sync_log';
	const HOOK       = 'dbd_sync_event';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'rest' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) { self::schedule(); }
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
			if ( ! preg_match( '#^(plays/[A-Z]{2,3}|players/(?:[A-Z]{2,3}|index|league|movers|career/[A-Z]{2,3})|wp/w\d{1,2}|box/w\d{1,2}|lineups|teams|metrics|schedule)\.json$#', $rel ) ) { continue; }
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
			self::recompute_stats();
		}
		return self::finish( $out, $start );
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
