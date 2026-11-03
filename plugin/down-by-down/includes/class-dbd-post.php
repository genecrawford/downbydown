<?php
/**
 * The weekly post (SCOPE §46): every finished week of the current season becomes a normal WordPress post in a "Week in review"
 * category, written from the week's data in plain sentences and scheduled for the Tuesday after the week at 8:00 AM Eastern.
 *
 *   - The sync that finds a newly complete week writes its post (DBD_Sync calls after_sync). A sync after 8:00 publishes it at once.
 *   - Until it publishes, a later sync refreshes its numbers. Once Gene edits it, or once it is published, it is never touched again.
 *   - One post per season and week, remembered in an option and on the post, so a deleted post is never written again.
 *   - The settings page's "Write past weeks" writes every earlier complete week, each dated the Tuesday after it (backfill).
 *
 * compose() is pure (data in, title, excerpt and HTML out), so the view harness renders a post without WordPress.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Post {

	const CAT      = 'week-in-review';
	const CAT_NAME = 'Week in review';
	const OPT_ON   = 'dbd_posts_on';     /* write the weekly post (on by default) */
	const OPT_FORM = 'dbd_post_form';    /* the signup shortcode shown under each post on the website */
	const OPT_MAP  = 'dbd_posts';        /* season => week => post id, kept after a delete so the week is never written again */
	const M_SEASON = '_dbd_season';
	const M_WEEK   = '_dbd_week';
	const M_HASH   = '_dbd_hash';        /* the content as the plugin last wrote it: anything else means Gene edited it */
	const FORM     = '[wpforms id="63" title="false"]';

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'relink' ), 9 );
		add_filter( 'the_content', array( __CLASS__, 'signup' ), 20 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_head', array( __CLASS__, 'head' ), 3 );
	}

	public static function on() { return (bool) get_option( self::OPT_ON, true ); }

	/* ------------------------------------------------------------------ writing posts */

	/** After a good sync: the latest complete week of the current season gets its post, and scheduled posts take any new numbers. */
	public static function after_sync() {
		if ( ! self::on() ) { return array(); }
		DBD_Data::use_season( null );
		$done = array_values( array_filter( DBD_Week::weeks(), function ( $w ) { $f = DBD_Week::file( $w ); return $f && ! empty( $f['complete'] ); } ) );
		$out = array();
		if ( $done ) { $n = max( $done ); $out[ $n ] = self::write( $n, false ); }
		foreach ( self::map() as $w => $id ) { if ( ! isset( $out[ $w ] ) && in_array( get_post_status( $id ), array( 'future', 'draft' ), true ) ) { $out[ $w ] = self::write( (int) $w, false ); } }
		return $out;
	}

	/** Every complete week of this season without a post gets one, dated the Tuesday after its week. Returns week => result. */
	public static function backfill() {
		DBD_Data::use_season( null );
		$out = array();
		foreach ( DBD_Week::weeks() as $w ) { $f = DBD_Week::file( $w ); if ( $f && ! empty( $f['complete'] ) ) { $out[ $w ] = self::write( (int) $w, true ); } }
		return $out;
	}

	/** This season's week => post id. */
	public static function map() { $m = (array) get_option( self::OPT_MAP, array() ); return (array) ( $m[ DBD_Data::season() ] ?? array() ); }
	private static function remember( $n, $id ) {
		$m = (array) get_option( self::OPT_MAP, array() ); $s = DBD_Data::season();
		$m[ $s ] = (array) ( $m[ $s ] ?? array() ); $m[ $s ][ (int) $n ] = (int) $id; ksort( $m[ $s ] );
		update_option( self::OPT_MAP, $m, false );
	}

	/** The post for this season's week n, if one was written and still exists (any status but the trash). */
	public static function post_for( $n ) {
		$id = (int) ( self::map()[ (int) $n ] ?? 0 );
		if ( ! $id ) {  /* the option lost (a restore, a migration): the post's own fields still say which week it is */
			$q = get_posts( array( 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( array( 'key' => self::M_SEASON, 'value' => DBD_Data::season() ), array( 'key' => self::M_WEEK, 'value' => (int) $n ) ) ) );
			if ( $q ) { $id = (int) $q[0]; self::remember( $n, $id ); }
		}
		return $id ? get_post( $id ) : null;
	}

	/** The published write-up's address for this season's week n, for the week page's link back. */
	public static function url_for( $n ) {
		$p = self::post_for( $n );
		return $p && $p->post_status === 'publish' ? get_permalink( $p ) : '';
	}

	/**
	 * Write or refresh week n's post. 'new', 'refreshed', 'kept' (published or edited: never touched), 'deleted' (Gene deleted it:
	 * never written again), 'not ready' (the week isn't complete) or an error message.
	 */
	public static function write( $n, $backfill ) {
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return 'not ready'; }
		/* one writer per week at a time: a webhook sync and "Write past weeks" can run together (Greptile, PR #67). add_option is a
		   single insert on a unique name, so only one of them gets the lock; a lock left by a crashed run expires after five minutes */
		$lock = 'dbd_post_lock_' . DBD_Data::season() . '_' . (int) $n;
		if ( ! add_option( $lock, time(), '', false ) ) {
			if ( time() - (int) get_option( $lock, 0 ) < 300 ) { return 'busy'; }
			update_option( $lock, time(), false );
		}
		try { return self::write_locked( (int) $n, $W, $backfill ); } finally { delete_option( $lock ); }
	}

	private static function write_locked( $n, $W, $backfill ) {
		$known = isset( self::map()[ (int) $n ] ); $p = self::post_for( $n );
		if ( $known && ( ! $p || $p->post_status === 'trash' ) ) { return 'deleted'; }
		$doc = self::compose( $n, $W, ! $backfill ); if ( ! $doc ) { return 'not ready'; }
		if ( $p ) {
			if ( ! in_array( $p->post_status, array( 'future', 'draft' ), true ) || self::edited( $p ) ) { return 'kept'; }
			if ( md5( $doc['title'] . "\x1f" . $doc['html'] . "\x1f" . $doc['excerpt'] ) === get_post_meta( $p->ID, '_dbd_src', true ) ) { return 'kept'; }  /* same numbers as last time */
			$r = wp_update_post( array( 'ID' => $p->ID, 'post_title' => $doc['title'], 'post_content' => $doc['html'], 'post_excerpt' => $doc['excerpt'] ), true );
			if ( is_wp_error( $r ) ) { return $r->get_error_message(); }
			self::stamp( $p->ID, $doc['title'] . "\x1f" . $doc['html'] . "\x1f" . $doc['excerpt'] );
			return 'refreshed';
		}
		$gmt = self::tuesday( $n ); $now = time();  /* dated the Tuesday after its week: scheduled when that's ahead, published (with that date) when it has passed */
		$id = wp_insert_post( array(
			'post_type'     => 'post',
			'post_status'   => $gmt > $now ? 'future' : 'publish',
			'post_title'    => $doc['title'],
			'post_content'  => $doc['html'],
			'post_excerpt'  => $doc['excerpt'],
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $gmt ),
			'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $gmt ) ),
			'post_author'   => self::author(),
			'post_category' => array( self::category() ),
			'meta_input'    => array( self::M_SEASON => DBD_Data::season(), self::M_WEEK => (int) $n ),
		), true );
		if ( is_wp_error( $id ) ) { return $id->get_error_message(); }
		self::remember( $n, $id );
		self::stamp( $id, $doc['title'] . "\x1f" . $doc['html'] . "\x1f" . $doc['excerpt'] );
		self::thumbnail( $id, $n, $doc['title'] );
		return 'new';
	}

	/** Remember the post as saved (WordPress may tidy the markup on save): an edit to its title, body or excerpt is anything that differs. */
	private static function stamp( $id, $src ) {
		$p = get_post( $id ); if ( ! $p ) { return; }
		update_post_meta( $id, self::M_HASH, self::sig( $p ) );
		update_post_meta( $id, '_dbd_src', md5( (string) $src ) );
	}
	private static function sig( $p ) { return md5( (string) $p->post_title . "\x1f" . (string) $p->post_content . "\x1f" . (string) $p->post_excerpt ); }
	private static function edited( $p ) { $h = (string) get_post_meta( $p->ID, self::M_HASH, true ); return $h !== '' && $h !== self::sig( $p ); }

	/** The Tuesday after the week's last game, 8:00 AM Eastern, as a Unix time. */
	public static function tuesday( $n ) {
		$dates = array_filter( array_map( function ( $g ) { return (string) ( $g['date'] ?? '' ); }, DBD_Data::week_games( $n ) ) );
		if ( ! $dates ) { return time(); }
		$et = new DateTimeZone( 'America/New_York' );
		$d  = new DateTime( max( $dates ) . ' 12:00:00', $et );
		$d->modify( 'next tuesday' )->setTime( 8, 0 );
		return $d->getTimestamp();
	}

	/** The "Week in review" category, made the first time it's needed. */
	public static function category() {
		$t = get_term_by( 'slug', self::CAT, 'category' );
		if ( $t ) { return (int) $t->term_id; }
		$r = wp_insert_term( self::CAT_NAME, 'category', array( 'slug' => self::CAT, 'description' => 'Every NFL week in review: the upsets, the swings, the best plays and players, fantasy and who is rising. Published every Tuesday.' ) );
		return is_wp_error( $r ) ? (int) get_option( 'default_category' ) : (int) $r['term_id'];
	}

	/** The site's first administrator writes the posts (a sync has no logged-in user). */
	private static function author() {
		$u = get_users( array( 'role' => 'administrator', 'orderby' => 'ID', 'order' => 'ASC', 'number' => 1, 'fields' => 'ID' ) );
		return $u ? (int) $u[0] : 1;
	}

	/** The week's share card as the featured image, drawn straight to a file and added to the media library. */
	private static function thumbnail( $id, $n, $title ) {
		if ( ! DBD_Card::available() ) { return; }
		$fetch = function ( $url ) { $r = wp_remote_get( $url, array( 'timeout' => 5 ) ); return ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) ? wp_remote_retrieve_body( $r ) : null; };
		$im = DBD_Card::render( 'page-week-' . (int) $n, $fetch ); if ( ! $im ) { return; }
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';  /* wp_tempnam and the sideload live in admin files */
		$tmp = wp_tempnam( 'dbd-week-' . (int) $n . '.png' ); $ok = imagepng( $im, $tmp, 6 ); imagedestroy( $im );
		if ( ! $ok ) { @unlink( $tmp ); return; }
		$att = media_handle_sideload( array( 'name' => 'down-by-down-' . DBD_Data::season() . '-week-' . (int) $n . '.png', 'tmp_name' => $tmp ), $id, $title );
		if ( is_wp_error( $att ) ) { @unlink( $tmp ); return; }
		update_post_meta( $att, '_wp_attachment_image_alt', $title );
		set_post_thumbnail( $id, $att );
	}

	/* ------------------------------------------------------------------ the post's words */

	private static function nick( $a ) { return DBD_Data::team( $a )['nick'] ?? (string) $a; }
	private static function u( $args ) { return esc_url( DBD_Router::url( $args ) ); }
	private static function a( $args, $text ) { return '<a href="' . self::u( $args ) . '">' . $text . '</a>'; }
	private static function clean( $d ) { $d = trim( (string) preg_replace( '/\s*\[[^\]]*\]/', '', (string) $d ) ); return $d === '' || preg_match( '/[.!?]$/', $d ) ? $d : $d . '.'; }
	private static function tags( $g ) { $o = array(); foreach ( (array) ( $g['tags'] ?? array() ) as $t ) { $o[ $t[0] ] = $t[1]; } return $o; }
	private static function word( $n ) { $w = array( 'no', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten' ); return $w[ $n ] ?? (string) $n; }
	private static function count_of( $n, $one, $many ) { return self::word( $n ) . ' ' . ( $n === 1 ? $one : $many ); }
	private static function and_list( $items ) { $items = array_values( $items ); $c = count( $items ); return $c < 2 ? implode( '', $items ) : implode( ', ', array_slice( $items, 0, -1 ) ) . ' and ' . $items[ $c - 1 ]; }
	private static function epa( $v ) { return ( $v < 0 ? '−' : '+' ) . number_format( abs( (float) $v ), 2 ); }
	private static function poss( $nick ) { return $nick . '’'; }  /* every NFL nickname is plural: the Browns’ chance */

	/** The winner from the final score ('' for a tie). The win-probability fields (win, tags) can lag the score or be missing in the playoffs. */
	private static function winner( $g ) { $a = (int) ( $g['a'] ?? 0 ); $h = (int) ( $g['h'] ?? 0 ); return $a > $h ? $g['away'] : ( $h > $a ? $g['home'] : '' ); }

	/** Winner, loser, winner's score, loser's score; a tie keeps away then home. */
	private static function wl( $g ) {
		$w = self::winner( $g ); $a = (int) ( $g['a'] ?? 0 ); $h = (int) ( $g['h'] ?? 0 );
		if ( $w === '' ) { return array( $g['away'], $g['home'], $a, $h ); }
		return $w === $g['away'] ? array( $g['away'], $g['home'], $a, $h ) : array( $g['home'], $g['away'], $h, $a );
	}

	/** "Q4 0:28" as "with 0:28 left in the fourth quarter"; overtime and a missing clock read plainly. */
	private static function clock( $c ) {
		if ( ! preg_match( '/^(Q(\d)|OT)\s*(\d{1,2}:\d{2})?/', (string) $c, $m ) ) { return ''; }
		$q = $m[1] === 'OT' || (int) ( $m[2] ?? 0 ) >= 5 ? 'overtime' : array( 1 => 'the first quarter', 2 => 'the second quarter', 3 => 'the third quarter', 4 => 'the fourth quarter' )[ (int) $m[2] ];
		return ! empty( $m[3] ) ? 'with ' . $m[3] . ' left in ' . $q : 'in ' . $q;
	}

	/** A player's headshot sized for the post (the NFL's image service crops it to his face), when the site shows headshots. */
	private static function face( $url, $team ) {
		if ( ! DBD_Data::headshots() || ! preg_match( '#^https://[^"\'<>\s]+$#', (string) $url ) ) { return ''; }
		$src = str_replace( 'f_auto,q_auto/', 'f_auto,q_auto,w_80,h_80,c_fill,g_face/', (string) $url );
		$c = DBD_Data::team( $team )['c1'] ?? '#161e36';
		return '<img class="hs" src="' . esc_url( $src ) . '" alt="" width="40" height="40" loading="lazy" style="background:' . esc_attr( $c ) . '">';
	}
	private static function logo( $a, $n = 22 ) {
		if ( ! DBD_Data::logos() ) { return ''; }
		$src = (string) ( DBD_Data::team( $a )['logo'] ?? '' );
		return preg_match( '#^https://[^"\'<>\s]+$#', $src ) ? '<img class="lg" src="' . esc_url( $src ) . '" alt="" width="' . (int) $n . '" height="' . (int) $n . '" loading="lazy">' : '';
	}

	/**
	 * Week n's post from its week file: array( title, excerpt, html ), or null when the week isn't complete. $ahead adds the coming
	 * week's game of the week, which only makes sense on the Tuesday the post is written (a backfilled week's next games are played).
	 */
	public static function compose( $n, $W = null, $ahead = true ) {
		$W = $W ?: DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) || empty( $W['games'] ) ) { return null; }
		$n = (int) $n; $lab = DBD_Router::week_label( $n ); $games = (array) $W['games'];
		$won = array_values( array_filter( $games, function ( $g ) { return self::winner( $g ) !== ''; } ) );
		$ups = array_values( array_filter( $won, function ( $g ) { return isset( self::tags( $g )['upset'] ); } ) );
		$cbs = array_values( array_filter( $won, function ( $g ) { return isset( self::tags( $g )['comeback'] ); } ) );
		$ots = array_values( array_filter( $games, function ( $g ) { return ! empty( $g['ot'] ); } ) );
		$ties = array_values( array_filter( $games, function ( $g ) { return self::winner( $g ) === ''; } ) );
		$ups = array_values( array_filter( $ups, function ( $g ) { return isset( $g['pre_w'] ); } ) ); $cbs = array_values( array_filter( $cbs, function ( $g ) { return isset( $g['low'] ); } ) );
		usort( $ups, function ( $x, $y ) { return $x['pre_w'] <=> $y['pre_w']; } );
		usort( $cbs, function ( $x, $y ) { return $x['low'] <=> $y['low']; } );
		$wild = $won; usort( $wild, function ( $x, $y ) { return array( $y['lc'] ?? 0, $y['drama'] ?? 0 ) <=> array( $x['lc'] ?? 0, $x['drama'] ?? 0 ); } );
		$up = $ups[0] ?? null; $cb = $cbs[0] ?? null; $wd = $wild[0] ?? null;
		$v = $n % 2;  /* two ways to say a few things, alternating by week */

		/* title */
		if ( $up ) { list( $w, $l ) = self::wl( $up ); $title = $lab . ' in review: ' . self::nick( $w ) . ' stun ' . self::nick( $l ) . ' from ' . DBD_Data::pct( $up['pre_w'] ) . '%'; }
		elseif ( $wd && ( $wd['lc'] ?? 0 ) >= 2 ) { $title = $lab . ' in review: ' . self::nick( self::winner( $wd ) ) . ' win the wildest game'; }
		else { $title = $lab . ' in review'; }

		/* the opening: the counts, the season context when it stands out, the week's best story */
		$counts = ucfirst( self::count_of( count( $ups ), 'upset', 'upsets' ) ) . ', ' . self::count_of( count( $cbs ), 'comeback', 'comebacks' ) . ' and ' . self::count_of( count( $ots ), 'overtime game', 'overtime games' ) . '.';
		$ctx = self::context( $n, array( 'upsets' => count( $ups ), 'comebacks' => count( $cbs ), 'overtime games' => count( $ots ) ) );
		$story = '';
		if ( $up ) {
			list( $w, $l, $ws, $ls ) = self::wl( $up ); $c = isset( self::tags( $up )['comeback'] );
			$story = 'The ' . self::a( array( 'team' => $w ), esc_html( self::nick( $w ) ) ) . ' were ' . ( $c ? '' : 'only ' ) . DBD_Data::pct( $up['pre_w'] ) . '% to win at kickoff'
				. ( $c ? ', fell to ' . DBD_Data::pct( $up['low'] ) . '% during the game, and still beat' : ( $v ? ' and beat' : ' and still beat' ) )
				. ' the ' . esc_html( self::nick( $l ) ) . ' ' . $ws . '-' . $ls . ( ! empty( $up['ot'] ) ? ' in overtime' : '' ) . '.';
		} elseif ( $cb ) {
			list( $w, $l, $ws, $ls ) = self::wl( $cb );
			$story = 'The ' . self::a( array( 'team' => $w ), esc_html( self::nick( $w ) ) ) . ' fell to ' . DBD_Data::pct( $cb['low'] ) . '% against the ' . esc_html( self::nick( $l ) ) . ' and came back to win ' . $ws . '-' . $ls . ( ! empty( $cb['ot'] ) ? ' in overtime' : '' ) . '.';
		} elseif ( $wd && ( $wd['lc'] ?? 0 ) >= 2 ) {
			list( $w, $l, $ws, $ls ) = self::wl( $wd );
			$story = 'The ' . esc_html( self::nick( $w ) ) . ' and ' . esc_html( self::nick( $l ) ) . ' traded the lead ' . (int) $wd['lc'] . ' times before the ' . self::a( array( 'team' => $w ), esc_html( self::nick( $w ) ) ) . ' won ' . $ws . '-' . $ls . '.';
		}
		$open = trim( $counts . ( $ctx !== '' ? ' ' . $ctx : '' ) . ( $story !== '' ? ' ' . $story : '' ) );
		$more = array();
		if ( $cb && ( ! $up || $cb['id'] !== $up['id'] ) ) { list( $w, $l, $ws, $ls ) = self::wl( $cb ); $more[] = 'The ' . self::a( array( 'team' => $w ), esc_html( self::nick( $w ) ) ) . ' came back from ' . DBD_Data::pct( $cb['low'] ) . '% against the ' . esc_html( self::nick( $l ) ) . ' and won ' . $ws . '-' . $ls . '.'; }
		foreach ( $ties as $g ) { $more[] = 'The ' . esc_html( self::nick( $g['away'] ) ) . ' and ' . esc_html( self::nick( $g['home'] ) ) . ' tied ' . (int) $g['a'] . '-' . (int) $g['h'] . '.'; }
		if ( ! empty( $W['byes'] ) ) { $more[] = 'On a bye: ' . esc_html( self::and_list( array_map( array( __CLASS__, 'nick' ), (array) $W['byes'] ) ) ) . '.'; }

		$h = '<p class="open">' . $open . '</p>' . ( $more ? '<p>' . implode( ' ', $more ) . '</p>' : '' );

		/* the scores, the wild games first */
		$byd = $games; usort( $byd, function ( $x, $y ) { return ( $y['drama'] ?? 0 ) <=> ( $x['drama'] ?? 0 ); } );
		$rows = '';
		foreach ( $byd as $g ) {
			list( $w, $l, $ws, $ls ) = self::wl( $g ); $tg = self::tags( $g ); $tie = self::winner( $g ) === '';
			$note = $tie ? 'Tie' : implode( ' · ', array_filter( array( $tg['upset'] ?? '', $tg['comeback'] ?? '', isset( $tg['ot'] ) || ! empty( $g['ot'] ) ? 'Overtime' : '' ) ) );
			if ( $note === '' && isset( $tg['wire'] ) ) { $note = 'Led all the way'; }
			$rows .= '<tr><td><a href="' . self::u( array( 'game' => $g['id'] ) ) . '">' . self::logo( $w ) . ( $tie ? esc_html( self::nick( $w ) ) : '<b>' . esc_html( self::nick( $w ) ) . '</b>' ) . ' ' . $ws . ', ' . esc_html( self::nick( $l ) ) . ' ' . $ls . '</a></td><td class="tg">' . esc_html( $note ) . '</td></tr>';
		}
		$h .= '<h2>The scores</h2><table class="sc">' . $rows . '</table>';

		/* the swing and the play */
		$gid = array(); foreach ( $games as $g ) { $gid[ $g['id'] ] = $g; }
		$ps = '';
		$sw = $W['swings'][0] ?? null;
		if ( $sw && isset( $gid[ $sw['g'] ] ) ) {
			$g = $gid[ $sw['g'] ]; $t = $sw['t']; $o = $t === $g['away'] ? $g['home'] : $g['away']; list( , , $ws, $ls ) = self::wl( $g );
			$gw = self::winner( $g ); $res = $gw === '' ? ', and the game ended in a tie.' : ( $gw === $t ? ', and they won ' . $ws . '-' . $ls . '.' : ', but they lost ' . $ws . '-' . $ls . '.' );
			$ck = self::clock( $sw['c'] );
			$ps .= '<p>' . ( $v ? 'The biggest swing of the week' : 'The week’s biggest swing' ) . ' came in the ' . esc_html( self::poss( self::nick( $t ) ) ) . ' game against the ' . esc_html( self::nick( $o ) ) . ( $ck !== '' ? ', ' . $ck : '' ) . ': ' . esc_html( self::clean( $sw['d'] ) )
				. ' It moved the ' . esc_html( self::poss( self::nick( $t ) ) ) . ' chance of winning by ' . (int) round( (float) $sw['v'] ) . ' points' . $res . ' ' . self::a( array( 'game' => $sw['g'] ), 'Open the game' ) . '</p>';
		}
		$tp = $W['plays'][0] ?? null;
		if ( $tp ) {
			$ck = self::clock( $tp['c'] );
			$ps .= '<p>The best single play by expected points was the ' . esc_html( self::poss( self::nick( $tp['t'] ) ) ) . ' against the ' . esc_html( self::nick( $tp['o'] ) ) . ( $ck !== '' ? ', ' . $ck : '' ) . ': ' . esc_html( self::clean( $tp['d'] ) )
				. ' It was worth ' . self::epa( $tp['e'] ) . ' EPA. ' . self::a( array( 'game' => $tp['g'] ), 'Open the game' ) . '</p>';
		}
		if ( $ps !== '' ) { $h .= '<h2>The swing and the play</h2>' . $ps; }

		/* the best offense and defense against their usual */
		$tm = (array) ( $W['teams'] ?? array() );
		if ( $tm ) {
			$bo = $tm[0]; $bd = $tm[0];
			foreach ( $tm as $t ) { if ( $t['epa'] > $bo['epa'] ) { $bo = $t; } if ( $t['depa'] < $bd['depa'] ) { $bd = $t; } }
			$res = function ( $t, $vs = true ) { $pf = (int) $t['pf']; $pa = (int) $t['pa']; $o = $vs ? esc_html( self::nick( $t['o'] ) ) : '';
				return $pf > $pa ? 'a ' . $pf . '-' . $pa . ' win' . ( $vs ? ' over the ' . $o : '' ) : ( $pf < $pa ? 'a ' . $pa . '-' . $pf . ' loss' . ( $vs ? ' to the ' . $o : '' ) : 'a ' . $pf . '-' . $pa . ' tie' . ( $vs ? ' with the ' . $o : '' ) ); };
			$h .= '<h2>Best offense, best defense</h2><p>The ' . self::a( array( 'team' => $bo['t'] ), esc_html( self::nick( $bo['t'] ) ) ) . ' had the week’s best offense, ' . self::epa( $bo['epa'] ) . ' EPA a play in ' . $res( $bo ) . '.'
				. ( isset( $bo['usual'] ) ? ' Their usual is ' . self::epa( $bo['usual'] ) . '.' : '' )
				. ' The ' . self::a( array( 'team' => $bd['t'], 'side' => 'def' ), esc_html( self::nick( $bd['t'] ) ) ) . ' had the best defense, holding the ' . esc_html( self::nick( $bd['o'] ) ) . ' to ' . self::epa( $bd['depa'] ) . ' a play in ' . $res( $bd, false ) . '.'
				. ( isset( $bd['dusual'] ) ? ' Their usual is ' . self::epa( $bd['dusual'] ) . ' allowed.' : '' ) . '</p>';
		}

		/* players of the week, fantasy */
		$row = function ( $p, $val ) {
			return '<tr><td class="ph">' . self::face( $p['ph'] ?? '', $p['t'] ) . '</td><td><a href="' . self::u( array( 'player' => $p['id'] ) ) . '"><b>' . esc_html( $p['n'] ) . '</b></a><small>' . esc_html( $p['pos'] . ' · ' . self::nick( $p['t'] ) . ' vs ' . self::nick( $p['o'] ) ) . '</small></td><td class="ln">' . $val . '</td></tr>';
		};
		$pw = '';
		foreach ( array( 'QB', 'RB', 'WR', 'TE' ) as $pos ) {
			$p = $W['players'][ $pos ][0] ?? null; if ( ! $p ) { continue; }
			$pw .= $row( $p, esc_html( (string) ( $p['x'] ?? '' ) ) . '<span>' . ( $p['e'] < 0 ? '−' : '+' ) . number_format( abs( (float) $p['e'] ), 1 ) . ' EPA</span>' );
		}
		if ( $pw !== '' ) { $h .= '<h2>Players of the week</h2><p>The top quarterback, back, receiver and tight end by expected points added.</p><table class="pl">' . $pw . '</table>'; }
		$fx = (array) ( $W['fx']['half'] ?? array() ); usort( $fx, function ( $x, $y ) { return ( $y['f'][1] ?? 0 ) <=> ( $x['f'][1] ?? 0 ); } );
		$fr = ''; foreach ( array_slice( $fx, 0, 5 ) as $p ) { $fr .= $row( $p, '<b>' . number_format( (float) ( $p['f'][1] ?? 0 ), 1 ) . '</b> half PPR' ); }
		if ( $fr !== '' ) { $h .= '<h2>Fantasy</h2><p>The week’s top five in half-PPR scoring.</p><table class="pl">' . $fr . '</table>'; }

		/* rising */
		$mv = (array) ( $W['movers'] ?? array() ); $ri = array_slice( (array) ( $mv['targets']['up'] ?? array() ), 0, 3 ); $fc = array_slice( (array) ( $mv['faces'] ?? array() ), 0, 3 );
		if ( $ri || $fc ) {
			$h .= '<h2>Rising</h2>';
			if ( $ri ) { $h .= '<p>The biggest jumps in share of the team’s targets, against their own earlier games: ' . self::and_list( array_map( function ( $m ) { return self::a( array( 'player' => $m['id'] ), esc_html( $m['name'] ) ) . ' (' . esc_html( self::nick( $m['team'] ) ) . ', ' . (int) round( (float) $m['before'] ) . '% to ' . (int) round( (float) $m['now'] ) . '%)'; }, $ri ) ) . '.</p>'; }
			if ( $fc ) {
				$kind = array( 'debut' => 'first game of the season', 'first start' => 'first start' );
				$h .= '<p>New faces: ' . self::and_list( array_map( function ( $m ) use ( $kind ) { return self::a( array( 'player' => $m['id'] ), esc_html( $m['name'] ) ) . ' (' . esc_html( ( $kind[ $m['kind'] ] ?? $m['kind'] ) . ' for the ' . self::nick( $m['team'] ) ) . ', ' . (int) $m['now'] . '% of snaps)'; }, $fc ) ) . '.</p>';
			}
		}

		/* next week's game of the week, on the Tuesday it's written */
		if ( $ahead ) { $h .= self::ahead( $n + 1 ); }

		$h .= '<p class="full">Every play, every team and every player from ' . esc_html( $lab ) . ' is on ' . self::a( array( 'view' => 'week', 'week' => $n ), 'the week page' ) . '.</p>';
		$excerpt = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $counts . ' ' . $story ) ) );
		return array( 'title' => $title, 'excerpt' => $excerpt, 'html' => '<div class="dbd-post">' . $h . '</div>' );
	}

	/** "No week this season has had more comebacks." when this week's count tops every other complete week's (from Week 3 on). */
	private static function context( $n, $counts ) {
		$others = array();
		foreach ( DBD_Week::weeks() as $w ) { if ( $w === $n ) { continue; } $f = DBD_Week::file( $w ); if ( $f && ! empty( $f['complete'] ) ) { $others[] = $f; } }
		if ( count( $others ) < 2 ) { return ''; }
		foreach ( $counts as $what => $c ) {
			if ( $c < 2 ) { continue; }
			$max = 0;
			foreach ( $others as $f ) {
				$g = (array) $f['games'];
				$k = $what === 'upsets' ? count( array_filter( $g, function ( $x ) { return self::winner( $x ) !== '' && isset( self::tags( $x )['upset'] ); } ) )
					: ( $what === 'comebacks' ? count( array_filter( $g, function ( $x ) { return self::winner( $x ) !== '' && isset( self::tags( $x )['comeback'] ); } ) ) : count( array_filter( $g, function ( $x ) { return ! empty( $x['ot'] ); } ) ) );
				$max = max( $max, $k );
			}
			if ( $c > $max ) { return 'No week this season has had more ' . $what . '.'; }
		}
		return '';
	}

	/** The coming week's game of the week (the hub's pick: the open game with the biggest edge) and that edge in a sentence. */
	private static function ahead( $n ) {
		$best = null; $gotw = null;
		foreach ( DBD_Data::week_games( $n ) as $g ) {
			if ( DBD_Router::is_final( $g ) ) { continue; }
			$b = DBD_Takeaways::best_edge( $g['away'], $g['home'] );
			if ( ! $best || $b['s'] > $best['s'] ) { $gotw = $g; $best = $b; }
		}
		if ( ! $gotw ) { return ''; }
		$cards = DBD_Takeaways::cards( $best['O'], $best['D'] ); $k = DBD_Data::kickoff( $gotw );
		$when = trim( (string) ( $gotw['day'] ?? '' ) . ( ! empty( $k['et'] ) ? ' at ' . $k['et'] : '' ) );
		return '<h2>Next week</h2><p class="next">' . self::logo( $gotw['away'], 30 ) . esc_html( self::nick( $gotw['away'] ) . ' at ' . self::nick( $gotw['home'] ) ) . self::logo( $gotw['home'], 30 ) . '</p>'
			. '<p>The game of the week' . ( $when !== '' ? ' is ' . esc_html( $when ) : '' ) . '.' . ( $cards ? ' Its biggest edge: ' . esc_html( $cards[0][3] ) : '' ) . ' '
			. self::a( array( 'game' => $gotw['id'], 'side' => $best['O'] === $gotw['home'] ? 'home' : 'away' ), 'Open the matchup' ) . '</p>';
	}

	/* ------------------------------------------------------------------ on the website */

	private static function ours() {
		return is_singular( 'post' ) && has_term( self::CAT, 'category', get_queried_object_id() );
	}

	/**
	 * A post keeps the app's plain links (/team/min/), which are the canonical pages while its season is current. Once that season is
	 * archived, its links are shown with the season (/2026/team/min/), so an old write-up opens its own season's pages, on the site
	 * and in the feed. A game's link already names its season.
	 */
	public static function relink( $content ) {
		$id = get_the_ID(); if ( ! $id ) { return $content; }
		$s = (int) get_post_meta( $id, self::M_SEASON, true );
		if ( ! $s || $s === DBD_Data::current_season() || strpos( (string) $content, 'dbd-post' ) === false ) { return $content; }
		return self::with_season( (string) $content, $s );
	}

	/** Every app link in $html given season $s: a /2026/ prefix with readable URLs, ?season=2026 without. */
	public static function with_season( $html, $s ) {
		$s = (int) $s;
		return (string) preg_replace_callback( '/href="([^"]+)"/', function ( $m ) use ( $s ) {
			$u = html_entity_decode( $m[1], ENT_QUOTES );
			if ( DBD_Urls::on() ) {
				$b = DBD_Urls::path_base(); if ( strpos( $u, $b ) !== 0 ) { return $m[0]; }
				$rest = substr( $u, strlen( $b ) );
				if ( ! preg_match( '#^(?:team|player|compare|players|leaders|week|matchups|teams)/#', $rest ) ) { return $m[0]; }  /* only the app's own pages (a game's link names its season; /about/ or a post Gene links stays as it is) */
				$u = $b . $s . '/' . $rest;
			} else {
				if ( strpos( $u, (string) DBD_Router::base() ) !== 0 || strpos( $u, 'game=' ) !== false || strpos( $u, 'season=' ) !== false ) { return $m[0]; }
				$u = add_query_arg( 'season', $s, $u );
			}
			return 'href="' . esc_url( $u ) . '"';
		}, $html );
	}

	/** Under each weekly post on the website (not in the feed the email reads): the signup form. */
	public static function signup( $content ) {
		if ( is_feed() || ! in_the_loop() || ! is_main_query() || ! self::ours() ) { return $content; }
		$sc = trim( (string) get_option( self::OPT_FORM, self::FORM ) );
		if ( $sc === '' || ! preg_match( '/^\[([a-z0-9_-]+)/i', $sc, $m ) || ! shortcode_exists( $m[1] ) ) { return $content; }
		return $content . '<div class="dbd-post-sign"><h3>Get this every Tuesday</h3><p>One email after every week. Free.</p>' . do_shortcode( $sc ) . '</div>';
	}

	public static function assets() {
		if ( ! is_singular( 'post' ) ) { return; }
		$p = get_queried_object();
		if ( ! $p || ( strpos( (string) $p->post_content, 'dbd-post' ) === false && ! self::ours() ) ) { return; }
		wp_enqueue_style( 'dbd-fonts', 'https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap', array(), null );
		wp_enqueue_style( 'dbd-post', DBD_URL . 'assets/post.css', array( 'dbd-fonts' ), DBD_VERSION );
	}

	/** The weekly post's description and share tags: the opening as the description, the week card as the image. */
	public static function head() {
		if ( ! self::ours() || DBD_Seo::seo_plugin() ) { return; }
		$p = get_queried_object(); $d = trim( (string) $p->post_excerpt );
		if ( $d !== '' ) { echo '<meta name="description" content="' . esc_attr( $d ) . '">' . "\n" . '<meta property="og:description" content="' . esc_attr( $d ) . '">' . "\n"; }
		echo '<meta property="og:title" content="' . esc_attr( get_the_title( $p ) ) . '">' . "\n" . '<meta property="og:type" content="article">' . "\n" . '<meta property="og:url" content="' . esc_url( get_permalink( $p ) ) . '">' . "\n";
		$img = get_the_post_thumbnail_url( $p, 'full' );
		if ( $img ) { echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n" . '<meta name="twitter:card" content="summary_large_image">' . "\n" . '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n"; }
	}
}
