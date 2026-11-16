<?php
/**
 * The signup strip (SCOPE §66): the Tuesday email's form at the end of game, player and kicker pages and the draft and combine boards.
 *   - Its line follows the calendar: the week in review in season, the combine and draft boards from February 15 to April 30, the week in
 *     review "from September" over the summer.
 *   - It shows only while the email is on and Brevo is set up, with the shortcode from the Week in review tab's "Signup under each post".
 *   - assets/sign.js: someone who confirmed on this device sees "You're on the list"; sending the form tells Analytics (generate_lead).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Signup {

	/** array( label, after the label, the sentence, the "on the list" line ) for the date $ts (now when null), in the site's time zone. */
	public static function line( $ts = null ) {
		$md = (int) wp_date( 'nd', $ts );
		if ( $md >= 215 && $md <= 430 ) {
			return array( 'The combine and draft boards', 'the week they land', 'Every drill and every pick, the week the results are in. Then the week in review again from September. Free.', 'The combine and draft emails arrive the Tuesday after each one lands.' );
		}
		$say = 'The scores, the biggest swings and the players who rose, one email after every week. Free.';
		if ( $md >= 501 && $md <= 831 ) { return array( 'The week in review', 'every Tuesday from September', $say, 'The first one arrives the Tuesday after Week 1.' ); }
		return array( 'The week in review', 'every Tuesday', $say, 'The next one arrives Tuesday morning.' );
	}

	/** The form's shortcode, or '' when there's nothing to sign up for. */
	public static function form() {
		if ( ! DBD_Email::ready() || (int) DBD_Email::get( 'tpl' ) < 1 ) { return ''; }  /* no confirmation template: a signup would go nowhere (Greptile, PR #121) */
		return trim( (string) get_option( DBD_Post::OPT_FORM, DBD_Post::FORM ) );
	}

	/** The strip for a page of $type (game, player, kicker, draft, combine), or ''. */
	public static function strip( $type ) {
		$sc = self::form(); if ( $sc === '' || ! function_exists( 'do_shortcode' ) ) { return ''; }
		list( $lab, $sub, $say, $on ) = self::line();
		return '<section class="dbd-panel dbd-sign" id="dbd-sign" data-type="' . esc_attr( $type ) . '"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $lab ) . '</b> · ' . esc_html( $sub ) . '</span></div>'
			. '<div class="dbd-sg"><p class="dbd-sg-p">' . esc_html( $say ) . '</p><div class="dbd-sg-f">' . do_shortcode( $sc ) . '</div>'
			. '<p class="dbd-sg-on"><b>You’re on the list.</b> ' . esc_html( $on ) . '</p></div></section>';
	}

	/** sign.js: on every app page and the weekly posts. */
	public static function enqueue() { wp_enqueue_script( 'dbd-sign', DBD_URL . 'assets/sign.js', array(), DBD_VERSION, true ); }
}
