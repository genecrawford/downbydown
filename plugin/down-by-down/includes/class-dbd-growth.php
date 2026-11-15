<?php
/**
 * Growth (SCOPE §65): the numbers for the admin's Growth tab and the Overview's Growth box.
 *   - Email, from Brevo on the server with the key the Tuesday email already uses: the list's size, its change (the plugin saves the
 *     size once a day, 90 days kept) and the last Tuesday email's opens and clicks. Each read is kept for an hour.
 *   - Analytics and Search Console are read in the browser by assets/growth.js through Site Kit's own routes, as the signed-in user,
 *     the way Site Kit's dashboard reads them. Nothing from them is stored.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Growth {

	const OPT_SIZES = 'dbd_growth_sizes';  /* the email list's size by day: array( 'Y-m-d' => n ), the newest 90 */
	const HOOK      = 'dbd_growth_daily';
	const KEEP      = 90;
	const DAYS      = 28;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'daily' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) { wp_schedule_event( time() + 300, 'daily', self::HOOK ); }
	}
	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK ); }

	/** The daily job: save today's list size. */
	public static function daily() { self::list_size(); }

	/* ------------------------------------------------------------------ Brevo */

	/** A Brevo GET kept for an hour; only a good answer is kept. array( body or null, error text or '' ). */
	private static function brevo( $path ) {
		$k = 'dbd_growth_' . md5( $path . '|' . (string) DBD_Email::get( 'key' ) );
		$v = get_transient( $k ); if ( is_array( $v ) ) { return array( $v, '' ); }
		$r = DBD_Email::api( 'GET', $path );
		if ( $r[2] !== '' || ! is_array( $r[1] ) ) { return array( null, $r[2] !== '' ? $r[2] : 'Brevo sent nothing back' ); }
		set_transient( $k, $r[1], HOUR_IN_SECONDS );
		return array( $r[1], '' );
	}

	/** Brevo is connected and a list is picked. */
	public static function email_on() { $o = DBD_Email::opts(); return (string) $o['key'] !== '' && (int) $o['list'] > 0; }

	/** The list's size now, saving today's size as it goes: array( n or null, error text or '' ). */
	public static function list_size() {
		if ( ! self::email_on() ) { return array( null, 'Brevo isn’t set up' ); }
		list( $j, $err ) = self::brevo( 'contacts/lists/' . (int) DBD_Email::get( 'list' ) );
		if ( $err !== '' || ! isset( $j['totalSubscribers'] ) ) { return array( null, $err !== '' ? $err : 'Brevo didn’t say how many are on the list' ); }
		$n = (int) $j['totalSubscribers'];
		self::save_size( wp_date( 'Y-m-d' ), $n );
		return array( $n, '' );
	}

	public static function save_size( $day, $n ) {
		$s = get_option( self::OPT_SIZES, array() ); $s = is_array( $s ) ? $s : array();
		if ( isset( $s[ $day ] ) && (int) $s[ $day ] === (int) $n ) { return; }
		$s[ $day ] = (int) $n; ksort( $s ); $s = array_slice( $s, -self::KEEP, null, true );
		update_option( self::OPT_SIZES, $s, false );
	}

	/**
	 * The change in the list's size over $days: array( change, the day it's measured from ) or null with nothing saved before today.
	 * It's measured from the newest saved day at least $days back, or from the oldest saved day while the record is younger than that.
	 */
	public static function size_change( $now, $days = self::DAYS, $today = null ) {
		$s = get_option( self::OPT_SIZES, array() ); if ( ! is_array( $s ) || ! $s ) { return null; }
		$today = $today ?? wp_date( 'Y-m-d' ); $cut = gmdate( 'Y-m-d', strtotime( $today . ' -' . (int) $days . ' days' ) );
		ksort( $s ); $from = null;
		foreach ( $s as $d => $n ) { if ( $d < $today && ( $from === null || $d <= $cut ) ) { $from = $d; } }
		return $from === null ? null : array( (int) $now - (int) $s[ $from ], $from );
	}

	/** The newest Tuesday email Brevo has a campaign for: array( log row, stats or null, error text or '' ), or null when none was sent. */
	public static function last_email() {
		$row = null;
		foreach ( (array) get_option( DBD_Email::OPT_LOG, array() ) as $x ) { if ( ! empty( $x['camp'] ) && $x['result'] === 'sent' ) { $row = $x; break; } }
		if ( ! $row ) { return null; }
		if ( ! self::email_on() ) { return array( $row, null, 'Brevo isn’t set up' ); }
		list( $j, $err ) = self::brevo( 'emailCampaigns/' . (int) $row['camp'] . '?statistics=globalStats' );
		return array( $row, is_array( $j['statistics']['globalStats'] ?? null ) ? $j['statistics']['globalStats'] : null, $err );
	}

	/** "sent to 139, 58% opened, 21% clicked" from Brevo's campaign stats. */
	public static function email_line( $row, $st ) {
		$t = $row['title'] . ', ' . dbd_admin_when( $row['at'], 'M j' );
		if ( ! $st ) { return $t . ': sent to ' . (int) $row['n']; }
		$d = max( 1, (int) ( $st['delivered'] ?? $row['n'] ) );
		return $t . ': delivered to ' . (int) ( $st['delivered'] ?? 0 ) . ', ' . round( 100 * (int) ( $st['uniqueViews'] ?? 0 ) / $d ) . '% opened, ' . round( 100 * (int) ( $st['uniqueClicks'] ?? 0 ) / $d ) . '% clicked';
	}

	/* ------------------------------------------------------------------ links and the browser's settings */

	/** The full reports: array( label => url ). Analytics and Search Console open on the property Site Kit is connected to. */
	public static function links() {
		$ga = get_option( 'googlesitekit_analytics-4_settings' ); $ga = is_array( $ga ) ? (string) ( $ga['propertyID'] ?? '' ) : '';
		$sc = get_option( 'googlesitekit_search-console_settings' ); $sc = is_array( $sc ) ? (string) ( $sc['propertyID'] ?? '' ) : '';
		return array(
			'analytics' => $ga !== '' && ctype_digit( $ga ) ? 'https://analytics.google.com/analytics/web/#/p' . $ga . '/reports/intelligenthome' : 'https://analytics.google.com/',
			'search'    => 'https://search.google.com/search-console/performance/search-analytics' . ( $sc !== '' ? '?resource_id=' . rawurlencode( $sc ) : '' ),
			'sitekit'   => admin_url( 'admin.php?page=googlesitekit-dashboard' ),
			'brevo'     => 'https://app.brevo.com/contact/list-listing',
		);
	}

	/** What growth.js needs: Site Kit's REST root and a nonce for it, and the site's address to turn Search Console's full URLs into paths. */
	public static function js_config() {
		return array( 'rest' => esc_url_raw( rest_url( 'google-site-kit/v1/modules/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ), 'home' => home_url( '/' ), 'days' => self::DAYS, 'v' => DBD_VERSION );
	}
}
