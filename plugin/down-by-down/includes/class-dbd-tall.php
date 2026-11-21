<?php
/**
 * The tall social graphics (2.43.0, SCOPE §85): one 1080 × 1350 image per post, one idea each, drawn with GD from the pack's own data and the
 * plugin's fonts. Three layouts: a ranked board (League Leaders, pass rushers), a player spotlight (the risers) and a game (the swing, a keys
 * side), with Week leaders and Best and worst offense as ranked lists. Two looks, Black and Paper, picked week by week on the Share pack tab:
 *   - The pick is kept per week in dbd_pack_look. A new week starts with the last pick (Black the first time). Picking pins every other week
 *     first, so a post still to come for an older week keeps the look that week had.
 *   - The look is part of each graphic's key (tall-week-10-black-swing), so its address and cache file change with it.
 *   - Headshots follow the site's dbd_headshots switch; a missing photo falls back to the team logo and number. A missing logo falls back to
 *     the team's letters on its color. A week missing an item draws nothing.
 * The 1200 × 630 website cards don't change.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Tall {
	const W = 1080; const H = 1350; const S = 2;
	const OPT = 'dbd_pack_look';  /* array( 'w' => array( '2026-10' => 'black' ), 'last' => 'black' ) */
	const TH = array(
		'black' => array( 'bg' => '#0f1012', 'ink' => '#ffffff', 'mut' => '#a6aab2', 'line' => '#2b2e34', 'red' => '#e5282e', 'row' => '#191b1f' ),
		'paper' => array( 'bg' => '#f4f1ea', 'ink' => '#121417', 'mut' => '#62666f', 'line' => '#d9d4c8', 'red' => '#cb2026', 'row' => '#fbf9f4' ),
	);
	/* League Leaders' rotation, one a week: box column (sk: the defense files), the heading, the panel's label, the words in a sentence */
	const STATS = array(
		array( 'passing_yards', 'PASSING YARDS', 'PASSING YARDS', 'passing yards' ), array( 'passing_tds', 'PASSING TDS', 'PASSING TDS', 'passing TDs' ),
		array( 'rushing_yards', 'RUSHING YARDS', 'RUSHING YARDS', 'rushing yards' ), array( 'rushing_tds', 'RUSHING TDS', 'RUSHING TDS', 'rushing TDs' ),
		array( 'receiving_yards', 'RECEIVING YARDS', 'RECEIVING YARDS', 'receiving yards' ), array( 'receiving_tds', 'RECEIVING TDS', 'RECEIVING TDS', 'receiving TDs' ),
		array( 'receptions', 'RECEPTIONS', 'RECEPTIONS', 'receptions' ), array( 'sk', 'SACKS', 'SACKS', 'sacks' ),
	);
	const TOP = 11;
	/** The harness's older posting tests set this: no graphics, no League Leaders, no kickoff guard, so they keep covering the text-and-card fallback. */
	public static $off = false;
	const SLOTS = 'swing|snaps|offense|targets|leaders|rush|board|keys-\d{4}_\d{2}_[A-Z]{2,3}_[A-Z]{2,3}-[A-Z]{2,3}';

	public static function init() { add_action( 'admin_post_dbd_pack_look', array( __CLASS__, 'look_action' ) ); }

	/* ------------------------------------------------------------------ the week's look */

	private static function wk( $n ) { return (int) DBD_Data::season() . '-' . (int) $n; }
	private static function store() { $o = get_option( self::OPT, array() ); return is_array( $o ) ? $o : array(); }
	/** The week's look: its own pick, or the last pick, or Black. */
	public static function look( $n ) { $s = self::store(); $l = (string) ( $s['w'][ self::wk( $n ) ] ?? $s['last'] ?? 'black' ); return isset( self::TH[ $l ] ) ? $l : 'black'; }
	/** Pick week $n's look. Every other pack week without a pick is pinned to the look it has now, so only this week changes. */
	public static function set_look( $n, $look ) {
		if ( ! isset( self::TH[ $look ] ) ) { return false; }
		$s = self::store(); $was = isset( self::TH[ $s['last'] ?? '' ] ) ? $s['last'] : 'black'; $w = is_array( $s['w'] ?? null ) ? $s['w'] : array();
		foreach ( DBD_Pack::weeks() as $x ) { $k = self::wk( $x ); if ( ! isset( $w[ $k ] ) ) { $w[ $k ] = $was; } }
		$w[ self::wk( $n ) ] = $look; update_option( self::OPT, array( 'w' => $w, 'last' => $look ), false );
		return true;
	}
	public static function look_action() {
		$n = (int) ( $_POST['week'] ?? 0 ); check_admin_referer( 'dbd_pack_look_' . $n ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		self::set_look( $n, sanitize_key( wp_unslash( (string) ( $_POST['look'] ?? '' ) ) ) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down-share', 'week' => $n ), admin_url( 'admin.php' ) ) ); exit;
	}

	/* ------------------------------------------------------------------ keys and files */

	public static function parse( $key ) { return preg_match( '/^tall-week-(\d{1,2})-(black|paper)-(' . self::SLOTS . ')$/', (string) $key, $m ) ? array( (int) $m[1], $m[2], $m[3] ) : null; }
	public static function key( $n, $slot, $look = null ) { return 'tall-week-' . (int) $n . '-' . ( $look ?? self::look( $n ) ) . '-' . $slot; }
	/** An item's or keys side's graphic key in the week's look, or ''. */
	public static function key_of( $n, $it ) { $slot = DBD_AutoPub::slot( $n, $it ); return $slot === '' ? '' : self::key( $n, $slot ); }
	/** Its JPEG on disk, drawn if need be, or '' when it can't be drawn. */
	public static function file( $n, $it ) {
		$k = self::key_of( $n, $it ); if ( self::$off || $k === '' || ! DBD_Card::jpg_ok() ) { return ''; }
		list( $f ) = DBD_Card::ensure( $k, true ); return (string) $f;
	}
	/** Its public JPEG address once it's drawn, or '' (the networks fetch this). */
	public static function url( $n, $it ) { return self::file( $n, $it ) !== '' ? DBD_Card::jpg_url( self::key_of( $n, $it ) ) : ''; }
	/** The tab's address for it: not drawn until the browser asks. */
	public static function preview( $n, $it ) { $k = self::key_of( $n, $it ); return $k === '' ? '' : DBD_Card::jpg_url( $k ); }
	public static function file_name( $n, $it ) { $k = self::key_of( $n, $it ); return 'dbd-' . ( $k === '' ? 'graphic' : strtolower( (string) preg_replace( '/^tall-/', '', str_replace( '_', '-', $k ) ) ) ) . '.jpg'; }

	/* ------------------------------------------------------------------ League Leaders */

	/** The week's stat in the rotation (Week 10: rushing TDs). */
	public static function stat( $n ) { return self::STATS[ ( (int) $n + 1 ) % count( self::STATS ) ]; }

	private static function sk( $v ) { return rtrim( rtrim( number_format( (float) $v, 1 ), '0' ), '.' ); }

	/** The season board through week $n: array( 'stat' => …, 'rows' => [ id, full, t, num, v, vs ] ), the top 11, ties in last-name order; null when there's nothing to rank. */
	public static function board_spec( $n ) {
		$st = self::stat( $n ); $k = $st[0]; $tot = array();
		if ( $k === 'sk' ) {
			foreach ( array_keys( DBD_Data::teams() ) as $t ) { $d = DBD_Data::defense( $t ); if ( ! $d ) { continue; } $thru = (int) ( $d['through'] ?? 0 );
				foreach ( (array) ( $d['defenders'] ?? array() ) as $p ) {
					if ( empty( $p['id'] ) ) { continue; }
					if ( isset( $p['sw'] ) ) { $v = 0.0; foreach ( (array) $p['sw'] as $w => $x ) { if ( (int) $w <= (int) $n ) { $v += (float) $x; } } }
					elseif ( $thru === (int) $n ) { $v = (float) ( $p['sk'] ?? 0 ); }
					else { continue; }  /* a file from before 2.43.0 that runs past this week can't say what he had through it */
					$id = (string) $p['id']; if ( ! isset( $tot[ $id ] ) ) { $tot[ $id ] = array( 'id' => $id, 'full' => (string) ( $p['name'] ?? '' ), 't' => $t, 'num' => $p['num'] ?? '', 'v' => 0.0, 'w' => -1 ); }
					$tot[ $id ]['v'] += $v; $last = ! empty( $p['sw'] ) ? max( array_map( 'intval', array_keys( (array) $p['sw'] ) ) ) : 0;
					if ( $last > $tot[ $id ]['w'] ) { $tot[ $id ]['w'] = $last; $tot[ $id ]['t'] = $t; $tot[ $id ]['num'] = $p['num'] ?? ''; }
				}
			}
		} else {
			for ( $w = 1; $w <= (int) $n; $w++ ) { foreach ( (array) DBD_Pack::box_rows( $w ) as $x ) {
				$id = (string) $x['id']; if ( ! isset( $tot[ $id ] ) ) { $tot[ $id ] = array( 'id' => $id, 'full' => (string) $x['full'], 't' => (string) $x['t'], 'num' => DBD_Data::player( $id )['num'] ?? '', 'v' => 0.0 ); }
				$tot[ $id ]['v'] += (float) ( $x[ $k ] ?? 0 ); $tot[ $id ]['t'] = (string) $x['t'];  /* the team he's on now */
			} }
		}
		$rows = array_values( array_filter( $tot, function ( $x ) { return $x['v'] > 0; } ) ); if ( ! $rows ) { return null; }
		usort( $rows, function ( $a, $b ) { return array( -$a['v'], DBD_Pack::last( $a['full'] ), $a['full'] ) <=> array( -$b['v'], DBD_Pack::last( $b['full'] ), $b['full'] ); } );
		$rows = array_slice( $rows, 0, self::TOP );
		foreach ( $rows as $i => $r ) { $rows[ $i ]['vs'] = $k === 'sk' ? self::sk( $r['v'] ) : number_format( (float) $r['v'] ); }
		return array( 'stat' => $st, 'rows' => $rows );
	}

	/* ------------------------------------------------------------------ colors */

	public static function rgb( $h ) { $h = ltrim( (string) $h, '#' ); if ( strlen( $h ) !== 6 ) { $h = '121417'; } return array( hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) ); }
	private static function lum( $h ) { list( $r, $g, $b ) = self::rgb( $h ); return ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255; }
	public static function on( $h ) { return self::lum( $h ) < 0.58 ? '#ffffff' : '#121417'; }
	public static function mix( $a, $b, $t ) { $x = self::rgb( $a ); $y = self::rgb( $b ); return sprintf( '#%02x%02x%02x', (int) ( $x[0] + ( $y[0] - $x[0] ) * $t ), (int) ( $x[1] + ( $y[1] - $x[1] ) * $t ), (int) ( $x[2] + ( $y[2] - $x[2] ) * $t ) ); }
	public static function c1( $t ) { $c = (string) ( DBD_Data::team( $t )['c1'] ?? '' ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '#3b4255'; }
	private static function nick( $t ) { return (string) ( DBD_Data::team( $t )['nick'] ?? $t ); }

	/* ------------------------------------------------------------------ people */

	/** A player's headshot URL: the offense's index, or the defense file's. Null with headshots off or no photo. */
	public static function photo_url( $id, $t ) {
		if ( ! DBD_Data::headshots() ) { return null; }
		$u = DBD_Data::player( $id )['photo'] ?? null;
		if ( ! $u ) { foreach ( (array) ( DBD_Data::defense( $t )['defenders'] ?? array() ) as $p ) { if ( ( $p['id'] ?? '' ) === $id ) { $u = $p['photo'] ?? null; break; } } }
		return is_string( $u ) && strpos( $u, 'https://' ) === 0 ? $u : null;
	}
	private static function num_of( $id, $t ) {
		$x = DBD_Data::player( $id )['num'] ?? null; if ( $x !== null && $x !== '' ) { return (string) $x; }
		foreach ( (array) ( DBD_Data::defense( $t )['defenders'] ?? array() ) as $p ) { if ( ( $p['id'] ?? '' ) === $id ) { return (string) ( $p['num'] ?? '' ); } }
		return '';
	}
	/** First name, the rest. */
	private static function split( $name ) { $p = preg_split( '/\s+/', trim( (string) $name ) ); return array( (string) array_shift( $p ), implode( ' ', $p ) ); }

	/* ------------------------------------------------------------------ drawing */

	/** Draw a graphic from its key: a GD image, or null when the week has no such item. */
	public static function render( $key, $fetch ) {
		$p = self::parse( $key ); if ( ! $p ) { return null; } list( $n, $look, $slot ) = $p;
		if ( strpos( $slot, 'keys-' ) === 0 ) { $s = self::spec_keys( $n, $slot ); return $s ? self::draw_keys( new DBD_Tall_Canvas( $look, $fetch ), $s ) : null; }
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return null; }
		switch ( $slot ) {
			case 'board':   $s = self::board_spec( $n ); if ( ! $s ) { return null; } return self::board( new DBD_Tall_Canvas( $look, $fetch ), array( 'LEAGUE LEADERS', $s['stat'][1] ), 'THROUGH WEEK ' . $n, $s['rows'], $s['stat'][2] );
			case 'rush':    return self::draw_rush( $n, $look, $fetch );
			case 'leaders': $s = DBD_Pack::spec_leaders( $n ); return $s ? self::draw_leaders( new DBD_Tall_Canvas( $look, $fetch ), $s, $n ) : null;
			case 'offense': $s = DBD_Pack::spec_offense( $n ); return $s ? self::draw_offense( new DBD_Tall_Canvas( $look, $fetch ), $s, $n ) : null;
			case 'swing':   return self::draw_swing( $W, $n, $look, $fetch );
			case 'snaps': case 'targets': $s = DBD_Pack::spec_riser( $n, $slot ); return $s ? self::draw_riser( new DBD_Tall_Canvas( $look, $fetch ), $s, $slot, $n ) : null;
		}
		return null;
	}

	/** The ranked board: rows of id, full, t, vs (and x, a small second stat), the leader on the team-color panel. */
	private static function board( $c, $lines, $sub, $R, $lab ) {
		if ( ! $c->ok ) { return null; }
		$W = self::W; $H = self::H; $lead = $R[0]; $c1 = self::c1( $lead['t'] ); $px0 = 640; $ink = self::on( $c1 ); $cx = ( $px0 + $W ) / 2; $nn = (string) $lead['vs']; $pw = $W - $px0;
		$num = (string) ( $lead['num'] ?? '' ); if ( $num === '' ) { $num = self::num_of( $lead['id'], $lead['t'] ); }
		$c->rect( $px0, 0, $pw, $H, $c1 );
		if ( $c->head_ok( $lead['id'], $lead['t'] ) ) {
			$c->logo( $lead['t'], $cx - 66, 54, 132, true );
			$p = $c->fit( $nn, 'b', 300, $pw - 36 ); $c->text( $cx - $c->w( $nn, 'b', $p ) / 2, 438, $nn, 'b', $p, $ink );
			$pl = $c->fit( $lab, 's', 40, $pw - 36 ); $c->text( $cx - $c->w( $lab, 's', $pl ) / 2, 496, $lab, 's', $pl, $ink );
			if ( $num !== '' ) { $c->text( $W - 12, 930, $num, 'b', 430, self::mix( $c1, $ink, 0.12 ), true ); }
			$c->head( $lead['id'], $lead['t'], $px0, 560, $pw, $H - 560, 1.72 );
		} else {
			$c->logo( $lead['t'], $px0 + 56, 190, $pw - 112, true );
			$p = $c->fit( $nn, 'b', 380, $pw - 36 ); $c->text( $cx - $c->w( $nn, 'b', $p ) / 2, 930, $nn, 'b', $p, $ink );
			$pl = $c->fit( $lab, 's', 48, $pw - 36 ); $c->text( $cx - $c->w( $lab, 's', $pl ) / 2, 1004, $lab, 's', $pl, $ink );
			$nm = self::up( $lead['full'] ); $pn = $c->fit( $nm, 'b', 62, 400 ); $c->text( $cx - $c->w( $nm, 'b', $pn ) / 2, 1120, $nm, 'b', $pn, $ink );
		}
		$b = $c->title( $lines, $sub, 56, 540, 128, 96 ); $y = $b + 15; $rh = ( $H - 104 - $y ) / count( $R );
		foreach ( $R as $i => $r ) {
			$ry = $y + $i * $rh; list( $f, $l ) = self::split( $r['full'] ); $c->tile( $r['t'], 56, $ry + 3, 92, (int) ( $rh - 12 ) );
			$vl = 596 - $c->w( (string) $r['vs'], 'b', 64 ) - 14; $xr = min( 516, $vl ); $vx = ! empty( $r['x'] ) ? $xr - $c->w( (string) $r['x'], 's', 26 ) - 14 : $vl;  /* a big number pushes the second stat left */
			$c->text( 164, $ry + $rh * 0.43, self::up( $f ), 's', 27, $c->t['ink'] ); $lu = self::up( $l ); $c->text( 164, $ry + $rh * 0.43 + 28, $lu, 'b', $c->fit( $lu, 'b', 30, max( 120, min( 270, $vx - 164 ) ) ), $c->t['ink'] );
			if ( ! empty( $r['x'] ) ) { $c->text( $xr, $ry + $rh * 0.70, (string) $r['x'], 's', 26, $c->t['mut'], true ); }
			$c->text( 596, $ry + $rh * 0.76, (string) $r['vs'], 'b', 64, $c->t['ink'], true );
			if ( $i ) { $c->rect( 56, $ry - 3, 540, 1, $c->t['line'] ); }
		}
		$c->foot( 56, 596 ); return $c->done();
	}
	private static function up( $s ) { return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $s ) : strtoupper( (string) $s ); }

	/** Pass rushers: the most pressures through the week the defense files run through (the pack item only exists for that week). */
	private static function draw_rush( $n, $look, $fetch ) {
		if ( ! DBD_Rush::card_spec( $n ) ) { return null; }
		$rows = DBD_Rush::rows(); usort( $rows, function ( $a, $b ) { return array( -$a['prs'], -$a['sk'], DBD_Pack::last( $a['n'] ) ) <=> array( -$b['prs'], -$b['sk'], DBD_Pack::last( $b['n'] ) ); } );
		$R = array(); foreach ( array_slice( $rows, 0, self::TOP ) as $r ) { $sk = self::sk( $r['sk'] ); $R[] = array( 'id' => $r['id'], 'full' => $r['n'], 't' => $r['t'], 'num' => $r['num'] ?? '', 'vs' => (string) (int) $r['prs'], 'x' => $sk . ( $sk === '1' ? ' SACK' : ' SACKS' ) ); }
		return $R ? self::board( new DBD_Tall_Canvas( $look, $fetch ), array( 'PASS RUSHERS', 'MOST PRESSURES' ), 'THROUGH WEEK ' . (int) $n, $R, 'PRESSURES' ) : null;
	}

	private static function draw_leaders( $c, $s, $n ) {
		if ( ! $c->ok ) { return null; }
		$W = self::W; $H = self::H; $b = $c->title( array( 'WEEK ' . (int) $n . ' LEADERS' ), null, 56, $W - 112, 136, 124 ); $y = $b + 22; $rh = ( $H - 104 - $y ) / 5; $P = $s['panels'];
		foreach ( array( 'pass', 'rush', 'rec', 'td', 'def' ) as $i => $k ) {
			$v = $P[ $k ]; $ry = $y + $i * $rh; $t = $v[1]; $tile = 176; $c->rect( 56, $ry, $tile, $rh - 14, self::c1( $t ) ); $pid = (string) ( $v[7]['id'] ?? '' );
			if ( $k === 'def' || $pid === '' || ! $c->head_ok( $pid, $t ) ) { $c->logo( $t, 56 + 26, $ry + ( $rh - 14 - 124 ) / 2, 124, true ); }
			else { $c->head( $pid, $t, 56, $ry, $tile, $rh - 14, 1.62 ); }
			$tx = 56 + $tile + 28; $val = (string) $v[3]; $unit = self::up( $v[4] ); $uw = $c->w( $unit, 's', 32 ); $vp = $c->fit( $val, 'b', 128, 300 ); $vw = $c->w( $val, 'b', $vp ); $room = $W - 56 - $uw - 12 - $vw - 20 - $tx;
			$c->text( $tx, $ry + 44, self::up( $v[0] ), 's', 30, $c->t['red'], false, 2 ); $nm = self::up( $v[2] ); $c->text( $tx, $ry + 104, $nm, 'b', $c->fit( $nm, 'b', 62, min( 470, $room ) ), $c->t['ink'] );
			$det = (string) preg_replace( '/ · held.*/u', '', (string) $v[5] ); $c->text( $tx, $ry + 148, $det, 'p', $c->fit( $det, 'p', 25, $room ), $c->t['mut'] );
			$c->text( $W - 56, $ry + 148, $unit, 's', 32, $c->t['mut'], true ); $c->text( $W - 56 - $uw - 12, $ry + 148, $val, 'b', $vp, $c->t['ink'], true );
			if ( $i < 4 ) { $c->rect( 56, $ry + $rh - 7, $W - 112, 1, $c->t['line'] ); }
		}
		$c->foot(); return $c->done();
	}

	private static function draw_offense( $c, $s, $n ) {
		if ( ! $c->ok ) { return null; }
		$W = self::W; $b = $c->title( array( 'WHO MOVED THE BALL' ), 'WEEK ' . (int) $n . ' · EPA A PLAY', 56, $W - 112, 136, 120 ); $R = $s['rows']; $k = min( 5, (int) floor( count( $R ) / 2 ) );
		$best = array_slice( $R, 0, $k ); $worst = array_reverse( array_slice( $R, -$k ) ); $y = $b + 20; $rh = 84; $mx = 0.01; foreach ( array_merge( $best, $worst ) as $r ) { $mx = max( $mx, abs( $r[1] ) ); }
		$track = $c->th === 'paper' ? $c->t['row'] : $c->t['line'];
		$block = function ( $lab, $rows, $y, $col ) use ( $c, $rh, $mx, $track, $W ) {
			$c->text( 56, $y + 34, $lab, 's', 34, $col, false, 2.4 ); $y += 50;
			foreach ( $rows as $i => $r ) {
				$ry = $y + $i * $rh; $c->tile( $r[0], 56, $ry, 96, $rh - 12 ); $nm = self::up( self::nick( $r[0] ) ); $c->text( 172, $ry + 38, $nm, 'b', $c->fit( $nm, 'b', 42, 370 ), $c->t['ink'] );
				$win = $r[2] > $r[3]; $loss = $r[2] < $r[3]; $res = ( $win ? 'won ' : ( $loss ? 'lost ' : 'tied ' ) ) . max( $r[2], $r[3] ) . '-' . min( $r[2], $r[3] ) . ( $win ? ' over the ' : ( $loss ? ' to the ' : ' with the ' ) ) . self::nick( $r[4] );
				$c->text( 172, $ry + 66, $res, 'p', $c->fit( $res, 'p', 22, 375 ), $c->t['mut'] );
				$bx = 560; $bw = 280; $c->rect( $bx, $ry + 22, $bw, 26, $track ); $c->rect( $bx, $ry + 22, max( 4, $bw * abs( $r[1] ) / $mx ), 26, $col );
				$c->text( $W - 56, $ry + 56, ( $r[1] > 0 ? '+' : '−' ) . number_format( abs( $r[1] ), 2 ), 'b', 62, $c->t['ink'], true );
			}
			return $y + count( $rows ) * $rh;
		};
		$y = $block( 'BEST', $best, $y, $c->t['ink'] ); $c->rect( 56, $y + 2, $W - 112, 1, $c->t['line'] ); $block( 'WORST', $worst, $y + 12, $c->t['red'] );
		$c->foot(); return $c->done();
	}

	private static function draw_swing( $W0, $n, $look, $fetch ) {
		$sw = $W0['swings'][0] ?? null; if ( ! $sw ) { return null; }
		$G = null; foreach ( (array) ( $W0['games'] ?? array() ) as $x ) { if ( ( $x['id'] ?? '' ) === $sw['g'] ) { $G = $x; } } if ( ! $G ) { return null; }
		$sg = DBD_Data::game( $sw['g'] ) ?: $G; $wp = DBD_Data::win_prob( $sg ); if ( ! $wp || empty( $wp['s'] ) ) { return null; }
		$c = new DBD_Tall_Canvas( $look, $fetch ); if ( ! $c->ok ) { return null; }
		$W = self::W; $H = self::H; $sub = 'WEEK ' . (int) $n . ( ! empty( $sg['stadium'] ) ? ' · ' . self::up( $sg['stadium'] ) : '' );
		$b = $c->title( array( 'THE BIGGEST SWING' ), $sub, 56, $W - 112, 136, 120 ); $y = $b + 28;
		foreach ( array( array( $G['away'], (int) $G['a'] ), array( $G['home'], (int) $G['h'] ) ) as $i => $g ) { $x = 56 + $i * 496; $c->tile( $g[0], $x, $y, 472, 150, 112 ); $c->text( $x + 456, $y + 118, (string) $g[1], 'b', 118, self::on( self::c1( $g[0] ) ), true ); }
		$y += 182; $v = '+' . (int) round( (float) $sw['v'] ); $c->text( 52, $y + 186, $v, 'b', 244, $c->t['red'] ); $tx = 52 + $c->w( $v, 'b', 244 ) + 34;
		foreach ( array( 'POINTS OF WIN', 'PROBABILITY', 'ON ONE PLAY' ) as $j => $l ) { $c->text( $tx, $y + 72 + $j * 56, $l, 'b', 56, $c->t['ink'] ); }
		$y += 224;
		/* the play, up to three lines under the chart */
		$txt = DBD_Pack::play( $sw['d'] ?? '' ); $pp = 40; $ls = $c->wrap( $txt, 's', $pp, $W - 112 ); while ( $pp > 26 && count( $ls ) > 2 ) { $pp -= 2; $ls = $c->wrap( $txt, 's', $pp, $W - 112 ); }
		if ( count( $ls ) > 3 ) { $ls = array_slice( $ls, 0, 3 ); $ls[2] = rtrim( $ls[2], ' ,;:.' ) . '…'; } $tl = count( $ls ) * ( $pp + 6 );
		$cx = 56; $cy = $y; $cw = $W - 112; $ch = $H - 150 - $tl - $y; $away = $sw['t'] === $G['away'];
		$pts = array(); foreach ( $wp['s'] as $p ) { $pts[] = array( min( 1, max( 0, (float) $p[0] / 3600 ) ), ( $away ? 1000 - (int) $p[1] : (int) $p[1] ) / 1000 ); }
		$c->rect( $cx, $cy, $cw, $ch, $c->t['row'] ); $c->rect( $cx, $cy + $ch / 2, $cw, 1, $c->t['line'] ); $lc = $c->th === 'black' ? '#ffffff' : self::c1( $sw['t'] ); $c->chart( $pts, $cx, $cy, $cw, $ch, $lc );
		$at = 1.0; if ( preg_match( '/^Q(\d) (\d+):(\d+)/', (string) ( $sw['c'] ?? '' ), $m ) ) { $at = ( (int) $m[1] * 900 - (int) $m[2] * 60 - (int) $m[3] ) / 3600; }
		$near = $pts[0]; foreach ( $pts as $q ) { if ( abs( $q[0] - $at ) < abs( $near[0] - $at ) ) { $near = $q; } }
		$c->dot( $cx + min( 1, $at ) * $cw, $cy + ( 1 - $near[1] ) * $ch, 15 );
		$c->text( $cx + 14, $cy + 34, self::up( self::nick( $sw['t'] ) ) . ' WIN PROBABILITY', 's', 26, $c->t['mut'], false, 1.4 ); $c->text( $cx + $cw - 14, $cy + 34, (string) ( $sw['c'] ?? '' ), 's', 28, $c->t['red'], true );
		foreach ( $ls as $i => $l ) { $c->text( 56, $cy + $ch + 46 + $i * ( $pp + 6 ), $l, 's', $pp, $c->t['ink'] ); }
		$c->foot(); return $c->done();
	}

	private static function draw_riser( $c, $s, $k, $n ) {
		if ( ! $c->ok ) { return null; }
		$W = self::W; $H = self::H; $t = $s['team']; $c1 = self::c1( $t ); $ink = self::on( $c1 ); $bh = 610; $c->rect( 0, 0, $W, $bh, $c1 ); $pid = (string) ( $s['id'] ?? '' ); $num = (string) ( $s['num'] ?? '' );
		if ( $num !== '' ) { $c->text( $W - 20, 340, $num, 'b', 400, self::mix( $c1, $ink, 0.13 ), true ); }
		if ( $pid !== '' && $c->head_ok( $pid, $t ) ) { $c->head( $pid, $t, 450, 40, 630, $bh - 40, 1.02 ); } else { $c->logo( $t, 590, 150, 400, true ); }
		$h = $k === 'snaps' ? array( 'THE USAGE', 'RISER' ) : array( 'TARGET-SHARE', 'MOVER' );
		$c->text( 56, 108, $h[0], 'b', $c->fit( $h[0], 'b', 88, 500 ), $ink ); $c->text( 56, 190, $h[1], 'b', 88, $ink ); $c->text( 56, 246, 'WEEK ' . (int) $n, 's', 36, $ink, false, 2.4 );
		$c->logo( $t, 56, 306, 120, true ); $f = self::up( $s['first'] ); $l = self::up( $s['last'] ); $c->text( 56, 492, $f, 's', $c->fit( $f, 's', 50, 430 ), $ink ); $c->text( 56, 560, $l, 'b', $c->fit( $l, 'b', 88, 430 ), $ink );
		$y = $bh + 188; $v = (int) round( (float) $s['now'] ) . '%'; $c->text( 52, $y, $v, 'b', 200, $c->t['red'] ); $sx = 52 + $c->w( $v, 'b', 200 ) + 30;
		$ls = $c->wrap( (string) $s['say'], 'p', 31, $W - 56 - $sx ); $y0 = $y - 142 + ( 142 - count( $ls ) * 40 ) / 2 + 26; foreach ( $ls as $j => $x ) { $c->text( $sx, $y0 + $j * 40, $x, 'p', 31, $c->t['ink'] ); }
		$ser = array_values( (array) $s['series'] ); $ct = $y + 52; $cb = $H - 136; $nums = array_filter( $ser, function ( $x ) { return $x !== null; } ); $mx = $nums ? max( $nums ) : 1; $mx = $mx ?: 1; $bw = ( $W - 112 ) / max( 1, count( $ser ) );
		$lab = $bw < 44 ? 22 : 30;
		foreach ( $ser as $i => $x ) {
			$bx = 56 + $i * $bw; $wk = (string) ( $i + 1 ); $c->text( $bx + $bw / 2 - $c->w( $wk, 's', 26 ) / 2, $cb + 32, $wk, 's', 26, $c->t['mut'] );
			if ( $x === null ) { $off = in_array( $t, DBD_Data::byes( $i + 1 ), true ) ? 'BYE' : 'DNP'; $c->text( $bx + $bw / 2 - $c->w( $off, 's', 22 ) / 2, $cb - 8, $off, 's', 22, $c->t['mut'] ); continue; }  /* a game he missed isn't a bye */
			$hh = max( 4, ( $cb - $ct - 34 ) * (float) $x / $mx ); $last = $i === count( $ser ) - 1;
			$c->rect( $bx + 6, $cb - $hh, $bw - 12, $hh, $last ? $c->t['red'] : ( $c->th === 'black' ? $c->t['line'] : '#cfcabd' ) );
			$tx = (string) (int) round( (float) $x ); $c->text( $bx + $bw / 2 - $c->w( $tx, 'b', $lab ) / 2, $cb - $hh - 8, $tx, 'b', $lab, $last ? $c->t['ink'] : $c->t['mut'] );
		}
		$c->foot(); return $c->done();
	}

	/** A keys side's numbers: the matchup, the edge's heading, and the three bars (this offense, that defense, the league). Null once the game has kicked off. */
	public static function spec_keys( $n, $slot ) {
		if ( ! preg_match( '/^keys-(\d{4}_\d{2}_[A-Z]{2,3}_[A-Z]{2,3})-([A-Z]{2,3})$/', (string) $slot, $m ) ) { return null; }
		$g = DBD_Data::game( $m[1] ); $t = $m[2]; if ( ! $g || (int) $g['week'] !== (int) $n + 1 || DBD_Pack::started( $g ) || ! in_array( $t, array( $g['away'], $g['home'] ), true ) ) { return null; }
		$o = $t === $g['away'] ? $g['home'] : $g['away']; $card = DBD_Takeaways::cards( $t, $o )[0] ?? null; if ( ! $card || empty( $card[4] ) ) { return null; } $k = (string) $card[4];
		list( $ov, $or ) = DBD_Metrics::off( $t, $k ); list( $dv, $dr ) = DBD_Metrics::def( $o, $k ); $lg = DBD_Metrics::league( $k ); if ( ! is_numeric( $ov ) || ! is_numeric( $dv ) || ! is_numeric( $lg ) ) { return null; }
		$nt = self::up( self::nick( $t ) ); $no = self::up( self::nick( $o ) ); $pos = array( 'WR_yds_pg' => array( 'WIDE RECEIVERS', 'YARDS' ), 'TE_yds_pg' => array( 'TIGHT ENDS', 'YARDS' ), 'TE_tgt_pg' => array( 'TIGHT ENDS', 'TARGETS' ), 'RB_yds_pg' => array( 'RUNNING BACKS', 'RECEIVING YARDS' ) );
		if ( isset( $pos[ $k ] ) ) { $l1 = $nt . '’ ' . $pos[ $k ][0] . ' PRODUCE'; $l2 = $no . ' GIVE UP'; $unit = $pos[ $k ][1] . ' A GAME TO ' . $pos[ $k ][0]; }
		else { $l1 = $nt . ' OFFENSE'; $l2 = $no . ' DEFENSE'; $unit = self::up( DBD_Metrics::label( $k ) ); }
		$f = function ( $v ) use ( $k ) { return (string) DBD_Metrics::fmt( $k, $v ); };
		return array( 'week' => (int) $g['week'], 'day' => self::up( (string) ( $g['day'] ?? '' ) ), 'time' => (string) ( DBD_Data::kickoff( $g )['et'] ?? '' ), 'away' => $g['away'], 'home' => $g['home'], 't' => $t, 'head' => self::up( rtrim( (string) $card[2], '.' ) ), 'unit' => $unit,
			'rows' => array( array( $l1, (float) $ov, $f( $ov ), $or ? DBD_Metrics::ordinal( $or ) : '' ), array( $l2, (float) $dv, $f( $dv ), $dr ? DBD_Metrics::ordinal( $dr ) : '' ), array( 'LEAGUE AVERAGE', (float) $lg, $f( $lg ), '' ) ) );
	}

	private static function draw_keys( $c, $k ) {
		if ( ! $c->ok ) { return null; }
		$W = self::W; $H = self::H; $b = $c->title( array( 'KEYS TO THE GAME' ), 'WEEK ' . $k['week'] . ' · ' . trim( $k['day'] . ' ' . $k['time'] ), 56, $W - 112, 136, 120 ); $y = $b + 28;
		foreach ( array( $k['away'], $k['home'] ) as $i => $t ) { $x = 56 + $i * 496; $c1 = self::c1( $t ); $c->rect( $x, $y, 472, 150, $c1 ); $c->logo( $t, $x + 22, $y + 19, 112, true ); $nm = self::up( self::nick( $t ) ); $c->text( $x + ( $i ? 450 : 430 ), $y + 98, $nm, 'b', $c->fit( $nm, 'b', 66, 280 ), self::on( $c1 ), true ); }
		$tw = $c->w( 'AT', 'b', 34 ) + 22; $c->rect( $W / 2 - $tw / 2, $y + 54, $tw, 44, $c->t['bg'] ); $c->text( $W / 2 - $c->w( 'AT', 'b', 34 ) / 2, $y + 88, 'AT', 'b', 34, $c->t['ink'] ); $y += 214;
		$c->text( 56, $y, 'FOR THE ' . self::up( self::nick( $k['t'] ) ), 's', 38, $c->t['red'], false, 2.4 ); $pxh = $c->fit( $k['head'], 'b', 172, $W - 112 ); $c->text( 52, $y + $pxh * 0.96, $k['head'], 'b', $pxh, $c->t['ink'] ); $y += (int) ( $pxh * 0.96 ) + 56;
		$vals = array_column( $k['rows'], 1 ); $hi = max( $vals ); $lo = min( 0, min( $vals ) ); if ( $lo < 0 ) { $lo -= ( $hi - $lo ) * 0.2; } $span = ( $hi - $lo ) ?: 1; $rh = ( $H - 150 - $y - 34 ) / 3;
		foreach ( $k['rows'] as $i => $r ) {
			$ry = $y + $i * $rh; $col = $i === 0 ? $c->t['red'] : ( $i === 1 ? $c->t['ink'] : $c->t['mut'] ); $tc = $i < 2 ? $c->t['ink'] : $c->t['mut'];
			$c->text( 56, $ry + 32, $r[0], 's', $c->fit( $r[0], 's', 32, 800 ), $tc, false, 1 ); if ( $r[3] !== '' ) { $c->text( $W - 56, $ry + 32, self::up( $r[3] ), 's', 32, $c->t['mut'], true ); }
			$bw = $W - 112 - 190; $c->rect( 56, $ry + 50, $bw, 62, $c->th === 'paper' ? $c->t['row'] : $c->t['line'] ); $c->rect( 56, $ry + 50, max( 6, $bw * ( $r[1] - $lo ) / $span ), 62, $col ); $c->text( $W - 56, $ry + 106, $r[2], 'b', $c->fit( $r[2], 'b', 76, 180 ), $tc, true );
		}
		$c->text( 56, $H - 112, $k['unit'], 's', 26, $c->t['mut'], false, 1.4 ); $c->foot(); return $c->done();
	}
}

/** The drawing surface: 2× and downsampled, the mock's helpers in GD. */
class DBD_Tall_Canvas {
	public $ok = false; public $im = null; public $t; public $th; private $fetch; private $F = array(); private $cols = array(); private $heads = array();
	const W = DBD_Tall::W; const H = DBD_Tall::H; const S = DBD_Tall::S;

	public function __construct( $th, $fetch ) {
		$this->th = $th; $this->t = DBD_Tall::TH[ $th ]; $this->fetch = $fetch; $d = DBD_PATH . 'assets/fonts/';
		$this->F = array( 'b' => $d . 'BarlowCondensed-Bold.ttf', 's' => $d . 'BarlowCondensed-SemiBold.ttf', 'm' => $d . 'IBMPlexMono-Medium.ttf', 'p' => $d . 'IBMPlexSans-Medium.ttf' );
		foreach ( $this->F as $f ) { if ( ! is_readable( $f ) ) { return; } }
		$this->im = imagecreatetruecolor( self::W * self::S, self::H * self::S ); if ( ! $this->im ) { return; }
		imagealphablending( $this->im, true ); imagefilledrectangle( $this->im, 0, 0, self::W * self::S, self::H * self::S, $this->col( $this->t['bg'] ) ); $this->ok = true;
	}
	public function col( $h ) { if ( ! isset( $this->cols[ $h ] ) ) { list( $r, $g, $b ) = DBD_Tall::rgb( $h ); $this->cols[ $h ] = imagecolorallocate( $this->im, $r, $g, $b ); } return $this->cols[ $h ]; }
	public function rect( $x, $y, $w, $h, $c ) { $S = self::S; imagefilledrectangle( $this->im, (int) round( $x * $S ), (int) round( $y * $S ), (int) round( ( $x + $w ) * $S ) - 1, (int) round( ( $y + $h ) * $S ) - 1, $this->col( $c ) ); }
	private function size( $px ) { return $px * self::S * 0.75; }
	public function w( $t, $f, $px ) { $b = imagettfbbox( $this->size( $px ), 0, $this->F[ $f ], (string) $t ); return $b ? abs( $b[2] - $b[0] ) / self::S : 0; }
	/** One character's advance; GD measures a lone space as nothing. */
	private function cw( $ch, $f, $px ) { return $ch === ' ' ? $this->w( 'n n', $f, $px ) - $this->w( 'nn', $f, $px ) : $this->w( $ch, $f, $px ); }
	public function text( $x, $y, $t, $f, $px, $c, $right = false, $track = 0 ) {
		$t = (string) $t; if ( $t === '' ) { return; } $S = self::S; $col = $this->col( $c ); $size = $this->size( $px ); $font = $this->F[ $f ];
		if ( $track ) {
			$chars = preg_split( '//u', $t, -1, PREG_SPLIT_NO_EMPTY ); $ws = array(); $tw = -$track; foreach ( $chars as $ch ) { $a = $this->cw( $ch, $f, $px ); $ws[] = $a; $tw += $a + $track; }
			$cx = $right ? $x - $tw : $x; foreach ( $chars as $i => $ch ) { if ( $ch !== ' ' ) { imagettftext( $this->im, $size, 0, (int) round( $cx * $S ), (int) round( $y * $S ), $col, $font, $ch ); } $cx += $ws[ $i ] + $track; }
			return;
		}
		$x0 = $right ? $x - $this->w( $t, $f, $px ) : $x; imagettftext( $this->im, $size, 0, (int) round( $x0 * $S ), (int) round( $y * $S ), $col, $font, $t );
	}
	public function fit( $t, $f, $px, $mw ) { while ( $px > 16 && $this->w( $t, $f, $px ) > $mw ) { $px--; } return $px; }
	public function wrap( $t, $f, $px, $mw ) {
		$o = array(); $cur = '';
		foreach ( preg_split( '/\s+/u', trim( (string) $t ) ) as $w ) { $try = trim( $cur . ' ' . $w ); if ( $cur !== '' && $this->w( $try, $f, $px ) > $mw ) { $o[] = $cur; $cur = $w; } else { $cur = $try; } }
		if ( $cur !== '' ) { $o[] = $cur; } return $o;
	}
	/** The title block: big lines, an optional spaced line, the red rule. Returns the y under the rule. */
	public function title( $lines, $sub, $x = 56, $mw = 968, $y = 128, $px = 108 ) {
		foreach ( $lines as $l ) { $px = min( $px, $this->fit( $l, 'b', $px, $mw ) ); } $yy = $y;
		foreach ( $lines as $l ) { $this->text( $x, $yy, $l, 'b', $px, $this->t['ink'] ); $yy += $px * 0.96; }
		$yy += -$px * 0.96 + 50;
		if ( $sub ) { $sp = 36; while ( $sp > 22 && $this->w( $sub, 's', $sp ) + mb_strlen( $sub ) * 2.4 > $mw ) { $sp--; } $this->text( $x, $yy, $sub, 's', $sp, $this->t['mut'], false, 2.4 ); $yy += 22; }
		$this->rect( $x, $yy, $mw, 5, $this->t['red'] ); return $yy + 5;
	}
	/** An image from bytes as true color with its alpha, or null. */
	private function load( $bin ) {
		if ( ! is_string( $bin ) || $bin === '' ) { return null; } $im = @imagecreatefromstring( $bin ); if ( ! $im ) { return null; }
		if ( ! imageistruecolor( $im ) ) { imagepalettetotruecolor( $im ); } imagesavealpha( $im, true ); return $im;
	}
	/** The box of the visible pixels: x, y, w, h. */
	private function bbox( $im ) {
		$iw = imagesx( $im ); $ih = imagesy( $im ); $x0 = $iw; $y0 = $ih; $x1 = -1; $y1 = -1; $step = max( 1, (int) floor( min( $iw, $ih ) / 300 ) );
		for ( $y = 0; $y < $ih; $y += $step ) { for ( $x = 0; $x < $iw; $x += $step ) { if ( ( ( imagecolorat( $im, $x, $y ) >> 24 ) & 127 ) < 120 ) { if ( $x < $x0 ) { $x0 = $x; } if ( $x > $x1 ) { $x1 = $x; } if ( $y < $y0 ) { $y0 = $y; } if ( $y > $y1 ) { $y1 = $y; } } } }
		if ( $x1 < 0 ) { return array( 0, 0, $iw, $ih ); }
		$x0 = max( 0, $x0 - $step ); $y0 = max( 0, $y0 - $step ); $x1 = min( $iw - 1, $x1 + $step ); $y1 = min( $ih - 1, $y1 + $step ); return array( $x0, $y0, $x1 - $x0 + 1, $y1 - $y0 + 1 );
	}
	/** A team logo centered in the b-square at x,y; the team's letters on its own color when logos are off or missing. */
	public function logo( $t, $x, $y, $b, $dark = null ) {
		$d = $dark === null ? $this->th === 'black' : $dark; $S = self::S; $im = $this->load( DBD_Card::logo_bin( $t, $this->fetch, $d ) );
		if ( ! $im ) { $ink = DBD_Tall::on( DBD_Tall::c1( $t ) ); $p = $this->fit( $t, 'b', $b * 0.62, $b * 0.92 ); $this->text( $x + ( $b - $this->w( $t, 'b', $p ) ) / 2, $y + $b / 2 + $p * 0.34, $t, 'b', $p, $ink ); return; }
		list( $sx, $sy, $sw, $sh ) = $this->bbox( $im ); $r = min( $b / $sw, $b / $sh ) * $S; $dw = max( 1, (int) round( $sw * $r ) ); $dh = max( 1, (int) round( $sh * $r ) );
		imagecopyresampled( $this->im, $im, (int) round( ( $x + $b / 2 ) * $S - $dw / 2 ), (int) round( ( $y + $b / 2 ) * $S - $dh / 2 ), $sx, $sy, $dw, $dh, $sw, $sh ); imagedestroy( $im );
	}
	public function tile( $t, $x, $y, $w, $h, $b = null ) { $this->rect( $x, $y, $w, $h, DBD_Tall::c1( $t ) ); $b = $b ?? $h - 10; $this->logo( $t, $x + ( $w - $b ) / 2, $y + ( $h - $b ) / 2, $b, true ); }
	private function head_bin( $id, $t ) { if ( ! array_key_exists( $id, $this->heads ) ) { $this->heads[ $id ] = DBD_Card::head_bin( $id, DBD_Tall::photo_url( $id, $t ), $this->fetch ); } return $this->heads[ $id ]; }
	public function head_ok( $id, $t ) { $im = $this->load( $this->head_bin( $id, $t ) ); if ( ! $im ) { return false; } imagedestroy( $im ); return true; }
	/** A headshot cutout standing on the bottom edge of the box x,y,w,h. */
	public function head( $id, $t, $x, $y, $w, $h, $scale = 1.0 ) {
		$im = $this->load( $this->head_bin( $id, $t ) ); if ( ! $im ) { return false; } $S = self::S; list( $bx, $by, $iw, $ih ) = $this->bbox( $im ); $r = $w / $iw * $scale * $S; $bw = $w * $S; $bh = $h * $S;
		$sw = min( $iw, $bw / $r ); $sh = min( $ih, $bh / $r ); $sx = $bx + ( $iw - $sw ) / 2; $sy = $by + $ih - $sh; $dw = (int) round( $sw * $r ); $dh = (int) round( $sh * $r );
		imagecopyresampled( $this->im, $im, (int) round( $x * $S + ( $bw - $dw ) / 2 ), (int) round( $y * $S + $bh - $dh ), (int) round( $sx ), (int) round( $sy ), $dw, $dh, (int) round( $sw ), (int) round( $sh ) ); imagedestroy( $im ); return true;
	}
	/** The win-probability line with a light fill under it. */
	public function chart( $pts, $cx, $cy, $cw, $ch, $hex ) {
		$S = self::S; list( $r, $g, $b ) = DBD_Tall::rgb( $hex ); $xy = array(); foreach ( $pts as $p ) { $xy[] = array( (int) round( ( $cx + $p[0] * $cw ) * $S ), (int) round( ( $cy + ( 1 - $p[1] ) * $ch ) * $S ) ); }
		$poly = array( (int) ( $cx * $S ), (int) ( ( $cy + $ch ) * $S ) ); foreach ( $xy as $q ) { $poly[] = $q[0]; $poly[] = $q[1]; } $last = end( $xy ); $poly[] = $last[0]; $poly[] = (int) ( ( $cy + $ch ) * $S );
		$fill = imagecolorallocatealpha( $this->im, $r, $g, $b, 104 );
		if ( PHP_VERSION_ID >= 80000 ) { imagefilledpolygon( $this->im, $poly, $fill ); } else { imagefilledpolygon( $this->im, $poly, (int) ( count( $poly ) / 2 ), $fill ); }
		$line = imagecolorallocate( $this->im, $r, $g, $b ); imagesetthickness( $this->im, 8 ); $prev = null;
		foreach ( $xy as $q ) { if ( $prev ) { imageline( $this->im, $prev[0], $prev[1], $q[0], $q[1], $line ); } imagefilledellipse( $this->im, $q[0], $q[1], 8, 8, $line ); $prev = $q; }
		imagesetthickness( $this->im, 1 );
	}
	public function dot( $x, $y, $rad ) { $S = self::S; $d = (int) ( 2 * $rad * $S ); imagefilledellipse( $this->im, (int) ( $x * $S ), (int) ( $y * $S ), $d, $d, $this->col( $this->t['bg'] ) ); imagefilledellipse( $this->im, (int) ( $x * $S ), (int) ( $y * $S ), $d - 12, $d - 12, $this->col( $this->t['red'] ) ); }
	/** The footer: the logo with its white backing keyed out (its grey type turned white on Black), and the site's address. */
	public function foot( $x0 = 56, $x1 = null, $y = null ) {
		$x1 = $x1 ?? self::W - 56; $y = $y ?? self::H - 50; $S = self::S; $p = DBD_PATH . 'assets/card-logo.png'; $lg = is_readable( $p ) ? @imagecreatefrompng( $p ) : false;
		if ( $lg ) {
			$lw = imagesx( $lg ); $lh = imagesy( $lg ); $h = 42 * $S; $w = (int) round( $lw * $h / $lh ); $sm = imagecreatetruecolor( $w, $h ); imagefilledrectangle( $sm, 0, 0, $w, $h, imagecolorallocate( $sm, 255, 255, 255 ) ); imagealphablending( $sm, true );
			imagecopyresampled( $sm, $lg, 0, 0, 0, 0, $w, $h, $lw, $lh ); imagedestroy( $lg );
			$o = imagecreatetruecolor( $w, $h ); imagealphablending( $o, false ); imagesavealpha( $o, true ); $clear = imagecolorallocatealpha( $o, 0, 0, 0, 127 ); imagefilledrectangle( $o, 0, 0, $w, $h, $clear ); $black = $this->th === 'black';
			for ( $j = 0; $j < $h; $j++ ) { for ( $i = 0; $i < $w; $i++ ) {
				$c = imagecolorat( $sm, $i, $j ); $r = ( $c >> 16 ) & 255; $g = ( $c >> 8 ) & 255; $b = $c & 255; $k = 255 - min( $r, $g, $b ); if ( ! $k ) { continue; }
				$un = function ( $v ) use ( $k ) { return max( 0, min( 255, (int) ( ( $v - ( 255 - $k ) ) * 255 / $k ) ) ); }; $r = $un( $r ); $g = $un( $g ); $b = $un( $b );
				if ( $black && max( $r, $g, $b ) - min( $r, $g, $b ) < 60 ) { $r = 255; $g = 255; $b = 255; }
				imagesetpixel( $o, $i, $j, imagecolorallocatealpha( $o, $r, $g, $b, 127 - (int) ( $k * 127 / 255 ) ) );
			} }
			imagedestroy( $sm ); imagealphablending( $this->im, true ); imagecopy( $this->im, $o, (int) ( $x0 * $S ), (int) ( ( $y - 34 ) * $S ), 0, 0, $w, $h ); imagedestroy( $o );
		}
		$this->text( $x1, $y, 'downbydown.com', 'm', 24, $this->t['mut'], true );
	}
	public function done() {
		$o = imagecreatetruecolor( self::W, self::H ); imagecopyresampled( $o, $this->im, 0, 0, 0, 0, self::W, self::H, self::W * self::S, self::H * self::S ); imagedestroy( $this->im ); $this->im = null; return $o;
	}
}
