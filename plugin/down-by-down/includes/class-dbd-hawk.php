<?php
/**
 * Ball hawks and takeaways (SCOPE §102): the secondary's twin of the Pass rushers tab. Every corner and safety with a regular role
 * as a dot, passes defended a game across (he got a hand on it) and interceptions a game up (he caught it); then every defender's
 * takeaways: interceptions and fumble recoveries, with forced fumbles beside them. From defenders/{TEAM}.json (SCOPE §101).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Hawk {
	const TOP = 10;      /* table rows before Show all */
	const BTOP = 15;     /* board rows before Show all */
	const NAMED = 8;     /* names on the wide chart, each axis */

	private static function nick( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; }
	private static function n( $v ) { return rtrim( rtrim( number_format( (float) $v, 1 ), '0' ), '.' ); }

	/** Every defender's season line: [record, s] by id. */
	private static function all() {
		$out = array();
		foreach ( array_keys( DBD_Data::teams() ) as $t ) {
			$f = DBD_Data::get( 'defenders/' . $t . '.json' );
			foreach ( (array) ( $f['players'] ?? array() ) as $id => $p ) { $out[ $id ] = $p; }
		}
		return $out;
	}

	/** Corners and safeties with a regular role: x passes defended a game, y interceptions a game. Most interceptions first. */
	public static function rows() {
		$out = array();
		foreach ( self::all() as $id => $p ) {
			if ( ( $p['role'] ?? '' ) !== 'db' || empty( $p['q'] ) ) { continue; }
			$s = (array) $p['s'] + array( 'g' => 0, 'snap' => 0, 'int' => 0, 'pd' => 0, 'tgt' => 0 ); $g = max( 1, (int) $s['g'] );
			$out[] = array( 'id' => (string) $id, 'n' => (string) $p['name'], 't' => (string) $p['team'], 'pos' => (string) $p['pos'], 'g' => (int) $s['g'], 'snap' => (int) round( (float) $s['snap'] ),
				'int' => (int) $s['int'], 'pd' => (int) $s['pd'], 'tgt' => (int) $s['tgt'], 'rate' => $s['rate'] ?? null, 'fr' => (int) ( $s['fr'] ?? 0 ), 'x' => $s['pd'] / $g, 'y' => $s['int'] / $g );
		}
		usort( $out, function ( $a, $b ) { return array( -$a['int'], -$a['x'], $a['n'] ) <=> array( -$b['int'], -$b['x'], $b['n'] ); } );
		return $out;
	}

	/** Every defender with a takeaway or a forced fumble: most takeaways, then forced fumbles. */
	public static function board() {
		$out = array();
		foreach ( self::all() as $id => $p ) {
			$s = (array) $p['s']; $int = (int) ( $s['int'] ?? 0 ); $fr = (int) ( $s['fr'] ?? 0 ); $ff = (int) ( $s['ff'] ?? 0 );
			if ( ! $int && ! $fr && ! $ff ) { continue; }
			$out[] = array( 'id' => (string) $id, 'n' => (string) $p['name'], 't' => (string) $p['team'], 'pos' => (string) $p['pos'], 'g' => (int) ( $s['g'] ?? 0 ), 'ta' => $int + $fr, 'int' => $int, 'fr' => $fr, 'ff' => $ff, 'td' => (int) ( $s['dtd'] ?? 0 ) );
		}
		usort( $out, function ( $a, $b ) { return array( -$a['ta'], -$a['ff'], -$a['int'], $a['n'] ) <=> array( -$b['ta'], -$b['ff'], -$b['int'], $b['n'] ); } );
		return $out;
	}

	private static function svg( $rows, $W, $H, $m, $named, $px, $r, $cls ) {
		$xm = 0.5; $ym = 0.2; foreach ( $rows as $x ) { $xm = max( $xm, $x['x'] ); $ym = max( $ym, $x['y'] ); }
		$pts = array();
		foreach ( $rows as $x ) {
			$tip = '<b>' . esc_html( $x['n'] ) . '</b> · ' . esc_html( self::nick( $x['t'] ) . ' ' . $x['pos'] ) . '<br>' . $x['int'] . ' ' . ( $x['int'] === 1 ? 'interception' : 'interceptions' ) . ', ' . $x['pd'] . ' passes defended, ' . $x['tgt'] . ' targets in ' . $x['g'] . ' games'
				. ( $x['rate'] !== null && $x['tgt'] >= 10 ? ' · ' . number_format( (float) $x['rate'], 1 ) . ' passer rating allowed' : '' ) . ' · ' . $x['snap'] . '% of snaps';
			$pts[] = array( 'id' => $x['id'], 'n' => $x['n'], 't' => $x['t'], 'pos' => $x['pos'], 'x' => $x['x'], 'y' => $x['y'], 'u' => 1, 'tip' => $tip );
		}
		$mid = array( DBD_Scatter::median( array_column( $rows, 'x' ) ), DBD_Scatter::median( array_column( $rows, 'y' ) ) );
		return DBD_Scatter::svg( $pts, array( 'W' => $W, 'H' => $H, 'm' => $m, 'px' => $px, 'rmin' => $r, 'rmax' => $r, 'named' => $named, 'cls' => $cls, 'xk' => 'q', 'yk' => 'q',
			'xl' => 'Passes defended a game', 'yl' => 'Interceptions a game', 'label' => 'Passes defended a game against interceptions a game, ' . count( $rows ) . ' defensive backs',
			'xr' => array( 0, ceil( ( $xm + 0.1 ) * 4 ) / 4, 0.25 ), 'yr' => array( 0, ceil( ( $ym + 0.05 ) * 10 ) / 10, 0.1 ), 'mid' => $mid ) );
	}

	private static function named( $rows, $k ) {
		$a = $rows; usort( $a, function ( $p, $q ) { return array( -$p['x'], -$p['y'], $p['n'] ) <=> array( -$q['x'], -$q['y'], $q['n'] ); } );
		$b = $rows; usort( $b, function ( $p, $q ) { return array( -$p['y'], -$p['x'], $p['n'] ) <=> array( -$q['y'], -$q['x'], $q['n'] ); } );
		return array_values( array_unique( array_merge( array_column( array_slice( $a, 0, $k ), 'id' ), array_column( array_slice( $b, 0, $k ), 'id' ) ) ) );
	}

	public static function tab() {
		$rows = self::rows(); if ( count( $rows ) < 3 ) { return '<div class="dbd-empty"><h2>No ball hawks yet</h2><p>The chart fills in once defenders have played a few games.</p></div>'; }
		$N = count( $rows ); $wk = (int) DBD_Data::through_week();
		$hand = $rows; usort( $hand, function ( $p, $q ) { return array( -$p['pd'], -$p['x'] ) <=> array( -$q['pd'], -$q['x'] ); } ); $h = $hand[0]; $i = $rows[0];
		$say = '<b>' . esc_html( $i['n'] ) . '</b> (' . esc_html( self::nick( $i['t'] ) ) . ') catches the most: ' . $i['int'] . ' interceptions, ' . number_format( $i['y'], 2 ) . ' a game. '
			. ( $h['id'] === $i['id'] ? 'He also gets a hand on the most.' : '<b>' . esc_html( $h['n'] ) . '</b> (' . esc_html( self::nick( $h['t'] ) ) . ') gets a hand on the most: ' . $h['pd'] . ' passes defended, ' . number_format( $h['x'], 2 ) . ' a game.' )
			. ' Up and to the right of the dashed lines is both.';
		$tb = '';
		foreach ( $rows as $k => $x ) {
			$tb .= '<tr' . ( $k >= self::TOP ? ' class="pr-x"' : '' ) . ' data-t="' . esc_attr( $x['t'] ) . '"><td class="rk">' . ( $k + 1 ) . '</td><td><a href="' . esc_url( DBD_Data::defender_url( $x['id'] ) ) . '">' . esc_html( $x['n'] ) . '</a><small>' . esc_html( $x['t'] . ' · ' . $x['pos'] . ' · ' . $x['g'] . ' games' ) . '</small></td>'
				. '<td><b>' . $x['int'] . '</b></td><td class="c-p">' . $x['pd'] . '</td><td>' . number_format( $x['x'], 2 ) . '</td><td class="c-h">' . $x['tgt'] . '</td><td class="c-h">' . ( $x['rate'] !== null && $x['tgt'] >= 10 ? number_format( (float) $x['rate'], 1 ) : '–' ) . '</td><td class="c-s">' . $x['snap'] . '%</td></tr>';
		}
		$small = self::named( $rows, 3 );
		$out = '<section class="dbd-band" id="ld-hawks"><h2 class="dbd-bandh">Ball hawks<span>passes defended against interceptions, every defensive back with a regular role</span></h2>'
			. '<section class="dbd-panel pr-panel" data-chart="hawks"><div class="dbd-ph"><span class="dbd-lab"><b>Through Week ' . $wk . '</b> · ' . $N . ' corners and safeties, a game each</span>' . DBD_Scatter::menu( $rows ) . '</div>'
			. '<p class="dbd-mv-say">' . $say . '</p>'
			. '<div class="pr-chart">' . self::svg( $rows, 960, 540, array( 64, 24, 16, 48 ), self::named( $rows, self::NAMED ), 12, 6, 'wide' ) . self::svg( $rows, 380, 420, array( 46, 10, 10, 38 ), $small, 11, 4, 'narrow' ) . '</div>'
			. '<div class="dbd-tw"><table class="pr-t"><thead><tr><th></th><th>Defensive back</th><th>INT</th><th class="c-p">PD</th><th>PD a game</th><th class="c-h">Targets</th><th class="c-h">Rating</th><th class="c-s">Snaps</th></tr></thead><tbody>' . $tb . '</tbody></table></div>'
			. ( $N > self::TOP ? '<button class="dbd-cbtn pr-more" type="button">Show all ' . $N . '</button>' : '' )
			. '<p class="dbd-note">Passes defended count every throw he got a hand on, interceptions included; both are nflverse\'s play-by-play. Targets and the passer rating allowed when thrown at are Pro Football Reference\'s charting, shown from 10 targets. Corners and safeties who played at least half their team\'s games, on 40% or more of the snaps in the games they played. The dashed lines are the middle of the pack. The team you highlight is remembered on this device and carries to every chart.</p></section></section>';
		/* every defender's takeaways */
		$B = self::board(); $bt = '';
		$tot = array_sum( array_column( $B, 'ta' ) );
		foreach ( $B as $k => $x ) {
			$bt .= '<tr' . ( $k >= self::BTOP ? ' class="pr-x"' : '' ) . ' data-t="' . esc_attr( $x['t'] ) . '"><td class="rk">' . ( $k + 1 ) . '</td><td><a href="' . esc_url( DBD_Data::defender_url( $x['id'] ) ) . '">' . esc_html( $x['n'] ) . '</a><small>' . esc_html( $x['t'] . ' · ' . $x['pos'] . ' · ' . $x['g'] . ' games' ) . '</small></td>'
				. '<td><b>' . $x['ta'] . '</b></td><td>' . ( $x['int'] ?: '' ) . '</td><td>' . ( $x['fr'] ?: '' ) . '</td><td>' . ( $x['ff'] ?: '' ) . '</td><td class="c-h">' . ( $x['td'] ?: '' ) . '</td></tr>';
		}
		$top = $B[0] ?? null; $ffb = $B; usort( $ffb, function ( $p, $q ) { return array( -$p['ff'], -$p['ta'] ) <=> array( -$q['ff'], -$q['ta'] ); } ); $fft = $ffb[0] ?? null;
		$say2 = $top ? '<b>' . esc_html( $top['n'] ) . '</b> (' . esc_html( self::nick( $top['t'] ) ) . ') has the most takeaways: ' . $top['ta'] . ' (' . $top['int'] . ' INT, ' . $top['fr'] . ' recovered). ' . ( $fft ? '<b>' . esc_html( $fft['n'] ) . '</b> (' . esc_html( self::nick( $fft['t'] ) ) . ') forces the most fumbles: ' . $fft['ff'] . '.' : '' ) : '';
		$out .= '<section class="dbd-band" id="ld-takeaways"><h2 class="dbd-bandh">Takeaways<span>interceptions and fumble recoveries, with the fumbles each defender forced</span></h2>'
			. '<section class="dbd-panel pr-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Through Week ' . $wk . '</b> · ' . count( $B ) . ' defenders, ' . $tot . ' takeaways</span></div><p class="dbd-mv-say">' . $say2 . '</p>'
			. '<div class="dbd-tw"><table class="pr-t tk-t"><thead><tr><th></th><th>Defender</th><th>Total</th><th>INT</th><th>FR</th><th>FF</th><th class="c-h">TD</th></tr></thead><tbody>' . $bt . '</tbody></table></div>'
			. ( count( $B ) > self::BTOP ? '<button class="dbd-cbtn pr-more" type="button">Show all ' . count( $B ) . '</button>' : '' )
			. '<p class="dbd-note">Total is his takeaways: interceptions (INT) and fumbles he recovered (FR). FF is the fumbles he forced, whoever recovered them, because the punch is the skill and the bounce is often luck. TD is takeaways he returned for a touchdown. All from nflverse\'s play-by-play, regular season, every defender with at least one.</p></section></section>';
		return $out;
	}
}
