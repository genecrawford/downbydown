<?php
/**
 * Every throw, one game at a time (2.48.0, SCOPE §90): the pass chart on a finished game's page and on a quarterback's page.
 * PHP gathers the throws from the play files and embeds them; assets/passchart.js draws the field.
 *   - A throw is a pass attempt (complete, incomplete or intercepted) with charted air yards; sacks, scrambles and two-point tries are left out.
 *   - The game page: each team's throws, every passer. The QB page: his games from every team he threw for this season, newest first, so a
 *     player who changed teams has all of them (the old chart read only his current team's file).
 * Each row: quarter, clock, down, distance, result (c complete, i incomplete, x intercepted), air yards, yards, lane (0 left, 1 middle,
 * 2 right, -1 none), touchdown, receiver key, passer key, EPA, the offense's score margin (2.49.0, for the replay). Names and numbers ride
 * along once, keyed the same way.
 *   - 2.49.0 (SCOPE §91): the panel also carries the league's field-use spreads (every QB game with 20+ throws, every QB season with 150+),
 *     worked out once per data update, so the script can say how evenly a game or season spread the ball against the rest.
 *   - 2.56.0 (SCOPE §98): the same panel for everyone else, drawn by assets/touchchart.js. A receiver or tight end gets every target
 *     ("Where he's targeted"); a back gets his designed runs and his catches on one field ("Where he gets the ball"). It replaces the
 *     player page's old On the field tab.
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
			(int) $p['air'], (int) ( $p['yds'] ?? 0 ), $lane[ $p['loc'] ?? '' ] ?? -1, ! empty( $p['td'] ) ? 1 : 0, $rk, $qk, round( (float) ( $p['epa'] ?? 0 ), 2 ),
			isset( $p['sd'] ) && is_numeric( $p['sd'] ) ? (int) $p['sd'] : null );
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

	/* ------------------------------------------------------------------ receivers and backs (SCOPE §98) */

	/** The seven gaps the play data names, left to right; anything without a side or gap counts as the middle. */
	const GAPS = array( 'left|end' => 0, 'left|tackle' => 1, 'left|guard' => 2, 'right|guard' => 4, 'right|tackle' => 5, 'right|end' => 6 );
	private static function gap( $p ) { $l = (string) ( $p['loc'] ?? '' ); return $l === 'middle' ? 3 : ( self::GAPS[ $l . '|' . (string) ( $p['gap'] ?? '' ) ] ?? 3 ); }
	/** The play as it finally stood: after a replay reversal the description carries the overturned call first, then the corrected play (Greptile, PR #172). */
	private static function ruling( $p ) { $d = (string) ( $p['desc'] ?? '' ); $i = strrpos( $d, 'REVERSED' ); return $i === false ? $d : substr( $d, $i + 8 ); }
	/** A fumble the other team recovered, from the play's final ruling (the play files carry no fumble field). */
	public static function lost( $p, $team ) { return preg_match( '/FUMBLES.*?RECOVERED by ([A-Z]{2,3})-/', self::ruling( $p ), $m ) && $m[1] !== $team ? 1 : 0; }
	/** His touchdown: the play's flag also covers the defense's (a pick-six, a fumble returned), so a target counts only when caught and kept, a carry only when kept (Greptile, PR #172). */
	private static function own_td( $p, $team, $caught ) { return ! empty( $p['td'] ) && $caught && ! self::lost( $p, $team ) ? 1 : 0; }
	/** A designed run (scrambles and kneels left out). */
	private static function is_carry( $p ) { return ( $p['pt'] ?? '' ) === 'run' && ( $p['k'] ?? '' ) === 'run'; }
	private static function margin( $p ) { return isset( $p['sd'] ) && is_numeric( $p['sd'] ) ? (int) $p['sd'] : null; }
	/** A target: q, clock, down, distance, result (c i x), air, yards, lane, td, passer key, EPA, margin, yards after the catch, dropped, contested, fumble lost. */
	private static function target_row( $p, $team, &$names ) {
		$qk = (string) ( $p['qb'] ?? '' );
		if ( $qk !== '' && ! isset( $names[ $qk ] ) ) { $x = DBD_Data::player( $qk ) ?: array(); $names[ $qk ] = array( (string) ( $x['name'] ?? '' ), isset( $x['num'] ) && $x['num'] !== '' && $x['num'] !== null ? (string) (int) $x['num'] : '' ); }
		$lane = array( 'left' => 0, 'middle' => 1, 'right' => 2 );
		return array( (int) ( $p['q'] ?? 0 ), (string) ( $p['t'] ?? '' ), (int) ( $p['dn'] ?? 0 ), (int) ( $p['togo'] ?? 0 ), array( 'comp' => 'c', 'inc' => 'i', 'int' => 'x' )[ $p['k'] ],
			(int) $p['air'], (int) ( $p['yds'] ?? 0 ), $lane[ $p['loc'] ?? '' ] ?? -1, self::own_td( $p, $team, $p['k'] === 'comp' ), $qk, round( (float) ( $p['epa'] ?? 0 ), 2 ), self::margin( $p ),
			is_numeric( $p['yac'] ?? null ) ? (int) $p['yac'] : 0, ! empty( $p['drop'] ) ? 1 : 0, ! empty( $p['cont'] ) ? 1 : 0, self::lost( $p, $team ) );
	}
	/** A carry: q, clock, down, distance, yards, gap (0 left end .. 6 right end), td, successful, first down, EPA, margin, fumble lost. */
	private static function carry_row( $p, $team ) {
		return array( (int) ( $p['q'] ?? 0 ), (string) ( $p['t'] ?? '' ), (int) ( $p['dn'] ?? 0 ), (int) ( $p['togo'] ?? 0 ), (int) ( $p['yds'] ?? 0 ), self::gap( $p ), self::own_td( $p, $team, true ),
			! empty( $p['succ'] ) ? 1 : 0, ! empty( $p['fd'] ) ? 1 : 0, round( (float) ( $p['epa'] ?? 0 ), 2 ), self::margin( $p ), self::lost( $p, $team ) );
	}

	/**
	 * A receiver's targets, or a back's carries and catches, this season, game by game newest first, from every team he played for:
	 * array( 'kind' => 'rx'|'rb', 'sets' => [ g, week, t, opp, home, label, nick, c1, 't' => targets, 'c' => carries ], 'names', 'me' ), or null.
	 * A back's targets are his catches only (Gene: throws "when they get a reception").
	 */
	public static function player( $p, $back ) {
		$id = (string) ( $p['id'] ?? '' ); if ( $id === '' ) { return null; }
		$teams = array( (string) ( $p['team'] ?? '' ) ); foreach ( (array) ( $p['weeks'] ?? array() ) as $w ) { if ( ! empty( $w['team'] ) ) { $teams[] = (string) $w['team']; } }
		$names = array(); $by = array(); $n = 0;
		foreach ( array_unique( array_filter( $teams ) ) as $t ) {
			foreach ( self::plays( $t ) as $x ) {
				if ( ( $x['pid'] ?? '' ) !== $id ) { continue; }
				$run = $back && self::is_carry( $x ); $tgt = self::is_throw( $x ) && ( ! $back || $x['k'] === 'comp' );
				if ( ! $run && ! $tgt ) { continue; }
				$gid = (string) ( $x['g'] ?? '' ); if ( ! preg_match( '/^\d{4}_(\d{2})_([A-Z]{2,3})_([A-Z]{2,3})$/', $gid, $m ) ) { continue; }
				if ( ! isset( $by[ $gid ] ) ) { $home = $m[3] === $t; $by[ $gid ] = array( 'g' => $gid, 'week' => (int) $m[1], 't' => $t, 'opp' => $home ? $m[2] : $m[3], 'home' => $home, 'tg' => array(), 'c' => array() ); }
				if ( $run ) { $by[ $gid ]['c'][] = self::carry_row( $x, $t ); } else { $by[ $gid ]['tg'][] = self::target_row( $x, $t, $names ); }
				$n++;
			}
		}
		if ( ! $n ) { return null; }
		$sets = array_values( $by ); usort( $sets, function ( $a, $b ) { return $b['week'] <=> $a['week']; } );
		foreach ( $sets as $i => $s ) {
			$wl = DBD_Router::week_label( $s['week'] ); $wl = is_string( $wl ) && $wl !== '' ? $wl : 'Week ' . $s['week'];
			$sets[ $i ] += self::team_info( $s['t'] ) + array( 'label' => $wl . ( $s['home'] ? ' vs ' : ' at ' ) . self::team_info( $s['opp'] )['nick'], 'wl' => $wl );
		}
		$me = isset( $p['num'] ) && $p['num'] !== '' && $p['num'] !== null ? (string) (int) $p['num'] : '';
		return array( 'kind' => $back ? 'rb' : 'rx', 'sets' => $sets, 'names' => $names, 'me' => $me );
	}

	/* ------------------------------------------------------------------ field use (SCOPE §91) */

	const MIN_GAME = 20; const MIN_SEASON = 150;
	/** A depth band: behind the line, 0 to 9, 10 to 19, 20+. */
	public static function band( $a ) { return $a < 0 ? 0 : ( $a < 10 ? 1 : ( $a < 20 ? 2 : 3 ) ); }
	/** How evenly a list of zone counts spreads across the 12 zones: 1 perfectly even, 0 all in one (normalized entropy). */
	public static function spread( $counts ) {
		$n = array_sum( $counts ); if ( $n <= 0 ) { return 0.0; } $e = 0.0;
		foreach ( $counts as $c ) { if ( $c > 0 ) { $p = $c / $n; $e -= $p * log( $p ); } }
		return round( $e / log( 12 ), 4 );
	}
	/** Every QB game's spread (20+ throws with a direction) and every QB season's (150+), sorted: array( 'g' => [...], 's' => [...] ). Read file by file and dropped, so the 32 play files are never all in memory; kept until the data changes. */
	public static function league() {
		$m = DBD_Data::manifest(); $key = 'dbd_thr_lg_' . md5( (int) DBD_Data::season() . '|' . (string) ( $m['built_at'] ?? '' ) . '|' . DBD_VERSION );
		$c = get_transient( $key ); if ( is_array( $c ) && isset( $c['g'], $c['s'] ) ) { return $c; }
		$games = array(); $seasons = array(); $lane = array( 'left' => 0, 'middle' => 1, 'right' => 2 );
		foreach ( array_keys( DBD_Data::teams() ) as $t ) {
			$path = DBD_Data::dir() . '/plays/' . $t . '.json'; if ( ! is_readable( $path ) ) { continue; }
			$f = json_decode( (string) file_get_contents( $path ), true ); if ( ! is_array( $f ) || empty( $f['keys'] ) ) { continue; }
			$K = array_flip( (array) $f['keys'] ); if ( ! isset( $K['pt'], $K['k'], $K['air'], $K['loc'], $K['qb'], $K['g'] ) ) { continue; }
			foreach ( (array) ( $f['plays'] ?? array() ) as $r ) {
				if ( ( $r[ $K['pt'] ] ?? '' ) !== 'pass' || ! in_array( $r[ $K['k'] ] ?? '', array( 'comp', 'inc', 'int' ), true ) || ! is_numeric( $r[ $K['air'] ] ?? null ) || ! isset( $lane[ $r[ $K['loc'] ] ?? '' ] ) ) { continue; }
				$q = (string) ( $r[ $K['qb'] ] ?? '' ); if ( $q === '' ) { continue; } $z = $lane[ $r[ $K['loc'] ] ] * 4 + self::band( (float) $r[ $K['air'] ] ); $g = $r[ $K['g'] ] . '|' . $q;
				$games[ $g ][ $z ] = ( $games[ $g ][ $z ] ?? 0 ) + 1; $seasons[ $q ][ $z ] = ( $seasons[ $q ][ $z ] ?? 0 ) + 1;
			}
			unset( $f );
		}
		$o = array( 'g' => array(), 's' => array() );
		foreach ( $games as $z ) { if ( array_sum( $z ) >= self::MIN_GAME ) { $o['g'][] = self::spread( $z ); } }
		foreach ( $seasons as $z ) { if ( array_sum( $z ) >= self::MIN_SEASON ) { $o['s'][] = self::spread( $z ); } }
		sort( $o['g'] ); sort( $o['s'] ); set_transient( $key, $o, DAY_IN_SECONDS );
		return $o;
	}

	/** The panel: a shell the script fills, with the throws embedded. '' with nothing to draw. */
	public static function panel( $d, $head, $sub ) {
		if ( ! $d ) { return ''; }
		if ( in_array( $d['kind'], array( 'qb', 'game' ), true ) ) { $d['lg'] = self::league() + array( 'min' => array( self::MIN_GAME, self::MIN_SEASON ) ); }  /* field use is the quarterback's */
		$json = wp_json_encode( $d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
		return '<section class="dbd-panel dbd-thr" data-kind="' . esc_attr( $d['kind'] ) . '"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $head ) . '</b> · ' . esc_html( $sub ) . '</span><span class="dbd-thr-pick"></span></div>'
			. '<div class="dbd-thr-b"><p class="dbd-note">The field draws here once the page’s script loads.</p></div><script type="application/json" class="dbd-thr-d">' . $json . '</script></section>';
	}
	public static function game_panel( $g ) { return self::panel( self::game( $g ), 'Every throw', 'air yards from the line of scrimmage, one team at a time' ); }
	public static function qb_panel( $p ) { return self::panel( self::qb( $p ), 'Where he throws', 'one game at a time, or the whole season' ); }
	/** A receiver's or a back's panel (SCOPE §98); '' with nothing to draw. */
	public static function player_panel( $p, $back ) { return self::panel( self::player( $p, $back ), $back ? 'Where he gets the ball' : 'Where he’s targeted', 'one game at a time, or the whole season' ); }
}
