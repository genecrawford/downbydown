<?php
/**
 * Auto publish (2.38.0, SCOPE §81): one button on each Share pack item (and each keys side) posts it to every network that can post by itself
 * and is ticked in the settings: Instagram (DBD_Instagram::auto(), in the background) and Bluesky (DBD_Bluesky::post(), during the press).
 * A network that already has the item is left out, so nothing posts twice; an Instagram day still to come is taken over and dropped.
 * Reddit, X and Facebook can't post on their own, so their buttons stay as they are.
 * 2.39.0 (SCOPE §82): a Bluesky day still to come is taken over the same way (the day's run finds the record sent).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_AutoPub {

	const OPT  = 'dbd_ap_set';   /* which networks the button posts to: array( 'ig' => bool, 'bs' => bool ) */
	const NETS = array( 'ig' => 'Instagram', 'bs' => 'Bluesky' );

	public static function init() { add_action( 'admin_post_dbd_auto_publish', array( __CLASS__, 'action' ) ); }

	public static function settings() { $o = get_option( self::OPT, null ); return array( 'ig' => is_array( $o ) ? ! empty( $o['ig'] ) : true, 'bs' => is_array( $o ) ? ! empty( $o['bs'] ) : true ); }
	public static function sanitize( $v ) { return array( 'ig' => is_array( $v ) && ! empty( $v['ig'] ), 'bs' => is_array( $v ) && ! empty( $v['bs'] ) ); }

	/** The networks the button posts to: ticked and connected. */
	public static function nets() {
		$s = self::settings(); $o = array();
		if ( $s['ig'] && DBD_Instagram::can_post() ) { $o[] = 'ig'; }
		if ( $s['bs'] && DBD_Bluesky::connected() ) { $o[] = 'bs'; }
		return $o;
	}

	/** An item's slot: its pack key ('swing' … 'rush'), or a keys side's 'keys-{game}-{team}' from its square. */
	public static function slot( $n, $it ) {
		if ( ! empty( $it['k'] ) ) { return (string) $it['k']; }
		$sq = (string) ( $it['sq'] ?? '' ); $pre = 'ig-week-' . (int) $n . '-keys-';
		return strpos( $sq, $pre ) === 0 ? substr( $sq, strlen( $pre ) - 5 ) : '';
	}
	public static function key( $n, $slot ) { return DBD_Instagram::key( (int) DBD_Data::season(), (int) $n, $slot ); }
	/** What the ask calls the item: its heading, or a keys side's offense. */
	public static function name( $it ) { return ! empty( $it['h'] ) ? (string) $it['h'] : ( DBD_Data::team( (string) ( $it['t'] ?? '' ) )['nick'] ?? (string) ( $it['t'] ?? '' ) ) . ' keys'; }

	/**
	 * Each network's state for an item: net => array( state, line, View link, dot ). State 'done' (it's out), 'busy' (posting or retrying
	 * for the button), 'ready' (the button posts it). A network shows when it's ticked and connected, or already has a record for the item.
	 */
	public static function states( $n, $slot, $it = null ) {
		$key = self::key( $n, $slot ); $nets = self::nets(); $out = array(); $when = function ( $ts ) { return wp_date( 'l, M j, g:i A', (int) $ts ); };
		$it = $it ?? DBD_Pack::find( $n, $slot );
		$ij = DBD_Instagram::job( $key ); $is = (string) ( $ij['state'] ?? '' );
		if ( ( in_array( 'ig', $nets, true ) && ! empty( $it['sq'] ) ) || $is === 'sent' ) {
			if ( $is === 'sent' ) { $out['ig'] = array( 'done', 'Instagram: posted ' . $when( $ij['sent_at'] ?? $ij['at'] ) . '.', (string) ( $ij['link'] ?? '' ), 'ok' ); }
			elseif ( ! empty( $ij['auto'] ) && $is === 'booked' ) { $out['ig'] = array( 'busy', 'Instagram: posting…', '', 'warn' ); }
			elseif ( ! empty( $ij['auto'] ) && strpos( $is, 'retrying' ) === 0 ) { $out['ig'] = array( 'busy', 'Instagram: retrying, ' . substr( $is, 10 ), '', 'warn' ); }
			elseif ( strpos( $is, 'failed' ) === 0 ) { $out['ig'] = array( 'ready', 'Instagram didn’t post: ' . substr( $is, 8 ), '', 'bad' ); }
			elseif ( $is === 'booked' || strpos( $is, 'retrying' ) === 0 ) { $out['ig'] = array( 'ready', 'Instagram: booked for ' . $when( $ij['slot_at'] ?? $ij['at'] ) . '. Auto publish posts it now instead.', '', '' ); }
			elseif ( $is !== '' ) { $out['ig'] = array( 'ready', 'Instagram: ' . $is . '.', '', '' ); }
			else { $out['ig'] = array( 'ready', '', '', '' ); }
		}
		$bj = DBD_Bluesky::job( $key ); $bs = (string) ( $bj['state'] ?? '' );
		if ( in_array( 'bs', $nets, true ) || $bs === 'sent' ) {
			if ( $bs === 'sent' ) { $out['bs'] = array( 'done', 'Bluesky: posted ' . $when( $bj['sent_at'] ) . '.', (string) ( $bj['link'] ?? '' ), 'ok' ); }
			elseif ( strpos( $bs, 'failed' ) === 0 ) { $out['bs'] = array( 'ready', 'Bluesky didn’t post: ' . DBD_Bluesky::why( $bj ), '', 'bad' ); }
			/* its day on the weekly schedule (SCOPE §82): the button posts it now, and the day finds it sent */
			elseif ( $bs === 'booked' ) { $out['bs'] = array( 'ready', 'Bluesky: booked for ' . $when( $bj['slot_at'] ?? $bj['at'] ) . '. Auto publish posts it now instead.', '', '' ); }
			elseif ( strpos( $bs, 'retrying' ) === 0 ) { $out['bs'] = array( 'ready', 'Bluesky: retrying, ' . substr( $bs, 10 ) . '. Auto publish tries it now.', '', 'warn' ); }
			elseif ( strpos( $bs, 'not posted' ) === 0 || strpos( $bs, 'skipped' ) === 0 ) { $out['bs'] = array( 'ready', 'Bluesky: ' . $bs . '.', '', '' ); }
			else { $out['bs'] = array( 'ready', '', '', '' ); }
		}
		return $out;
	}

	/** The networks the button would post to now. */
	public static function ready( $n, $slot, $it = null ) {
		$st = self::states( $n, $slot, $it );
		return array_values( array_filter( self::nets(), function ( $x ) use ( $st ) { return ( $st[ $x ][0] ?? '' ) === 'ready'; } ) );
	}

	/** Post week $n's item in $slot to every ready network: net => what went wrong (empty when all went). */
	public static function publish( $n, $slot ) {
		$it = DBD_Pack::find( $n, $slot ); if ( ! $it ) { return array( 'item' => 'That item isn’t in the Week ' . (int) $n . ' pack.' ); }
		$key = self::key( $n, $slot ); $errs = array();
		foreach ( self::ready( $n, $slot, $it ) as $net ) {
			$e = $net === 'bs' ? DBD_Bluesky::post( $key, $it ) : DBD_Instagram::auto( $key );
			if ( $e !== '' ) { $errs[ $net ] = $e; }
		}
		return $errs;
	}

	public static function action() {
		$n = (int) ( $_POST['week'] ?? 0 ); $slot = sanitize_text_field( wp_unslash( (string) ( $_POST['slot'] ?? '' ) ) );
		check_admin_referer( 'dbd_ap_' . $n . '_' . $slot ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		$errs = self::publish( $n, $slot );
		$args = array( 'page' => 'down-by-down-share', 'week' => $n ); if ( isset( $errs['item'] ) ) { $args['pack'] = 'igerror'; $args['pack_err'] = rawurlencode( $errs['item'] ); }
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) . ( isset( $errs['item'] ) ? '#dbd-pack-settings' : '#sp-' . $slot ) ); exit;
	}
}
