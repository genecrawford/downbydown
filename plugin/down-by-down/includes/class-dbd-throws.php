<?php
/**
 * Every throw, one game at a time (2.48.0, SCOPE §90): the pass chart on a finished game's page and on a quarterback's page.
 * PHP gathers the throws from the play files and embeds them; assets/passchart.js draws the field.
 *   - A throw is a pass attempt (complete, incomplete or intercepted) with charted air yards; sacks, scrambles and two-point tries are left out.
 *   - The game page: each team's throws, every passer. The QB page: his games from every team he threw for this season, newest first, so a
 *     player who changed teams has all of them (the old chart read only his current team's file).
 * Each row: quarter, clock, down, distance, result (c complete, i incomplete, x intercepted), air yards, yards, lane (0 left, 1 middle,
 * 2 right, -1 none), touchdown, receiver key, passer key, EPA. Names and numbers ride along once, keyed the same way.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Throws {
	private static $memo = array();

	/** One team's offensive plays, as keyed rows. */
	private static function plays( $team ) {
		if ( isset( self::$memo[ $team ] ) ) { return self::$memo[ $team ]; }
		$f = DBD_Data::get( 'plays/' . $team . '.json' ); $o = array();
		if ( is_array( $f ) && ! empty( $f['keys'] ) ) { $K = (array) $f['keys']; $n = count( $K ); foreach ( (array) ( $f['plays'] ?? array() ) as $r ) { if ( is_array( $r ) && count( $r ) === $n ) { $o[] = array_combine( $K, $r ); } } }
		return self::$memo[ $team ] = $o;
	}
	public static function reset() { self::$memo = array(); }

	private static function is_throw( $p ) { return ( $p['pt'] ?? '' ) === 'pass' && in_array( $p['k'] ?? '', array( 'comp', 'inc', 'int' ), true ) && isset( $p['air'] ) && $p['air'] !== null && is_numeric( $p['air'] ); }

	/** A row, adding its passer and receiver to the names. */
	private static function row( $p, &$names ) {
		$rk = (string) ( $p['pid'] ?? '' ); if ( $rk === '' && ! empty( $p['pl'] ) ) { $rk = '~' . $p['pl']; }
		$qk = (string) ( $p['qb'] ?? '' );
		foreach ( array( $rk, $qk ) as $k ) {
			if ( $k === '' || isset( $names[ $k ] ) ) { continue; }
			if ( $k[0] === '~' ) { $names[ $k ] = array( substr( $k, 1 ), '' ); continue; }
			$x = DBD_Data::player( $k ) ?: array(); $names[ $k ] = array( (string) ( $x['name'] ?? ( $k === $rk ? ( $p['pl'] ?? '' ) : '' ) ), isset( $x['num'] ) && $x['num'] !== '' && $x['num'] !== null ? (string) (int) $x['num'] : '' );
		}
		$lane = array( 'left' => 0, 'middle' => 1, 'right' => 2 );
		return array( (int) ( $p['q'] ?? 0 ), (string) ( $p['t'] ?? '' ), (int) ( $p['dn'] ?? 0 ), (int) ( $p['togo'] ?? 0 ), array( 'comp' => 'c', 'inc' => 'i', 'int' => 'x' )[ $p['k'] ],
			(int) $p['air'], (int) ( $p['yds'] ?? 0 ), $lane[ $p['loc'] ?? '' ] ?? -1, ! empty( $p['td'] ) ? 1 : 0, $rk, $qk, round( (float) ( $p['epa'] ?? 0 ), 2 ) );
	}

	private static function team_info( $t ) { $x = DBD_Data::team( $t ) ?: array(); $c = (string) ( $x['c1'] ?? '' ); return array( 'nick' => (string) ( $x['nick'] ?? $t ), 'c1' => preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '#3b4255' ); }

	/** A finished game's throws, team by team (away first): array( 'kind', 'sets' => [ t, nick, c1, rows ], 'names' ), or null with none. */
	public static function game( $g ) {
		if ( empty( $g['id'] ) || ! DBD_Router::is_final( $g ) ) { return null; }
		$names = array(); $sets = array();
		foreach ( array( (string) $g['away'], (string) $g['home'] ) as $t ) {
			$rows = array(); foreach ( self::plays( $t ) as $p ) { if ( ( $p['g'] ?? '' ) === $g['id'] && self::is_throw( $p ) ) { $rows[] = self::row( $p, $names ); } }
			if ( $rows ) { $sets[] = array( 't' => $t ) + self::team_info( $t ) + array( 'label' => self::team_info( $t )['nick'], 'rows' => $rows ); }
		}
		return $sets ? array( 'kind' => 'game', 'sets' => $sets, 'names' => $names ) : null;
	}

	/** A quarterback's games this season, newest first, from every team he threw for: array( 'kind', 'sets' => [ g, week, t, opp, home, label, rows ], 'names' ), or null. */
	public static function qb( $p ) {
		$id = (string) ( $p['id'] ?? '' ); if ( $id === '' ) { return null; }
		$teams = array( (string) ( $p['team'] ?? '' ) ); foreach ( (array) ( $p['weeks'] ?? array() ) as $w ) { if ( ! empty( $w['team'] ) ) { $teams[] = (string) $w['team']; } }
		$names = array(); $by = array();
		foreach ( array_unique( array_filter( $teams ) ) as $t ) {
			foreach ( self::plays( $t ) as $x ) {
				if ( ( $x['qb'] ?? '' ) !== $id || ! self::is_throw( $x ) ) { continue; }
				$gid = (string) ( $x['g'] ?? '' ); if ( ! preg_match( '/^\d{4}_(\d{2})_([A-Z]{2,3})_([A-Z]{2,3})$/', $gid, $m ) ) { continue; }
				if ( ! isset( $by[ $gid ] ) ) { $home = $m[3] === $t; $by[ $gid ] = array( 'g' => $gid, 'week' => (int) $m[1], 't' => $t, 'opp' => $home ? $m[2] : $m[3], 'home' => $home, 'rows' => array() ); }
				$by[ $gid ]['rows'][] = self::row( $x, $names );
			}
		}
		if ( ! $by ) { return null; }
		$sets = array_values( $by ); usort( $sets, function ( $a, $b ) { return $b['week'] <=> $a['week']; } );
		foreach ( $sets as $i => $s ) {
			$wl = DBD_Router::week_label( $s['week'] ); $wl = is_string( $wl ) && $wl !== '' ? $wl : 'Week ' . $s['week'];
			$sets[ $i ] += self::team_info( $s['t'] ) + array( 'label' => $wl . ( $s['home'] ? ' vs ' : ' at ' ) . self::team_info( $s['opp'] )['nick'], 'onick' => self::team_info( $s['opp'] )['nick'], 'wl' => $wl );
		}
		return array( 'kind' => 'qb', 'sets' => $sets, 'names' => $names, 'qb' => $id );
	}

	/** The panel: a shell the script fills, with the throws embedded. '' with nothing to draw. */
	public static function panel( $d, $head, $sub ) {
		if ( ! $d ) { return ''; }
		$json = wp_json_encode( $d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
		return '<section class="dbd-panel dbd-thr" data-kind="' . esc_attr( $d['kind'] ) . '"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $head ) . '</b> · ' . esc_html( $sub ) . '</span><span class="dbd-thr-pick"></span></div>'
			. '<div class="dbd-thr-b"><p class="dbd-note">The field draws here once the page’s script loads.</p></div><script type="application/json" class="dbd-thr-d">' . $json . '</script></section>';
	}
	public static function game_panel( $g ) { return self::panel( self::game( $g ), 'Every throw', 'air yards from the line of scrimmage, one team at a time' ); }
	public static function qb_panel( $p ) { return self::panel( self::qb( $p ), 'Where he throws', 'one game at a time, every pass' ); }
}
