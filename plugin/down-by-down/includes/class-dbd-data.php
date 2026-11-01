<?php
/**
 * Reads the JSON data files from wp-content/uploads/down-by-down/.
 * Everything is decoded once per request and cached in memory.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Data {

	private static $cache = array();

	/** Absolute path of the data folder (created on demand). */
	public static function dir() {
		$u   = wp_upload_dir();
		$dir = trailingslashit( $u['basedir'] ) . 'down-by-down';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			wp_mkdir_p( $dir . '/plays' );
			wp_mkdir_p( $dir . '/players' );
		}
		return $dir;
	}

	/** Public URL of the data folder (the explorer fetches play files from here). */
	public static function url() {
		$u = wp_upload_dir();
		return trailingslashit( $u['baseurl'] ) . 'down-by-down';
	}

	/** Decode one file, e.g. 'teams.json' or 'plays/MIN.json'. Returns an array or null. */
	public static function get( $file ) {
		if ( array_key_exists( $file, self::$cache ) ) {
			return self::$cache[ $file ];
		}
		$path = self::dir() . '/' . ltrim( $file, '/' );
		$data = null;
		if ( is_readable( $path ) ) {
			$json = file_get_contents( $path );
			$data = json_decode( $json, true );
			if ( ! is_array( $data ) ) { $data = null; }
		}
		self::$cache[ $file ] = $data;
		return $data;
	}

	public static function has_data() {
		return (bool) self::get( 'schedule.json' ) && (bool) self::get( 'teams.json' );
	}

	public static function teams()    { return self::get( 'teams.json' ) ?: array(); }
	public static function metrics()  { return self::get( 'metrics.json' ) ?: array(); }
	public static function schedule() { return self::get( 'schedule.json' ) ?: array(); }
	public static function manifest() { return self::get( 'manifest.json' ) ?: array(); }

	public static function season() {
		$s = self::schedule();
		return isset( $s['season'] ) ? (int) $s['season'] : (int) gmdate( 'Y' );
	}

	public static function current_week() {
		$s = self::schedule();
		return isset( $s['current_week'] ) ? (int) $s['current_week'] : 1;
	}

	public static function through_week() {
		$m = self::metrics();
		return isset( $m['through_week'] ) ? (int) $m['through_week'] : 0;
	}

	/** Weeks that exist in the schedule, as ints, ascending. */
	public static function weeks() {
		$s = self::schedule();
		$w = array_map( 'intval', array_keys( $s['weeks'] ?? array() ) );
		sort( $w );
		return $w;
	}

	public static function week_games( $week ) {
		$s = self::schedule();
		return $s['weeks'][ (string) (int) $week ] ?? array();
	}

	public static function team( $abbr ) {
		$t = self::teams();
		return $t[ $abbr ] ?? null;
	}

	/* ---- player layer (1.2.0): players/index.json and players/{TEAM}.json */

	/** Admin toggle: show NFL headshots (hotlinked from the URLs nflverse publishes). Off by default. */
	/** Admin toggle: show NFL team logos (hotlinked from the URLs nflverse publishes). Off by default (SCOPE §28). */
	public static function logos() {
		return (bool) get_option( 'dbd_logos', false );
	}

	/** A team's logo URL when logos are on and the data carries one, else null. */
	public static function logo( $abbr ) {
		if ( ! self::logos() ) { return null; }
		$u = self::team( $abbr )['logo'] ?? null;
		return ( $u && preg_match( '#^https://[^"\'<>\s]+$#', $u ) ) ? $u : null;
	}

	public static function headshots() {
		return (bool) get_option( 'dbd_headshots', false );
	}

	public static function has_players() {
		return (bool) self::get( 'players/index.json' );
	}

	/** League-wide index rows: id, name, team, pos, num, snap, tgt, rec, car, att, yds, td, epa, g, st, what, slot. */
	public static function player_index() {
		$i = self::get( 'players/index.json' );
		return $i['players'] ?? array();
	}

	public static function player( $id ) {
		if ( isset( self::$cache['__pidx'] ) ) { return self::$cache['__pidx'][ $id ] ?? null; }
		$m = array();
		foreach ( self::player_index() as $p ) { $m[ $p['id'] ] = $p; }
		self::$cache['__pidx'] = $m;
		return $m[ $id ] ?? null;
	}

	/** Full records for one team: weeks, snaps, pfr, inj, depth. */
	public static function team_players( $abbr ) {
		$f = self::get( 'players/' . $abbr . '.json' );
		return $f['players'] ?? array();
	}

	public static function player_record( $abbr, $id ) {
		foreach ( self::team_players( $abbr ) as $p ) { if ( $p['id'] === $id ) { return $p; } }
		return null;
	}

	/** The game a team played in a week, or null (bye). */
	/** Each team's starters from its latest depth chart (SCOPE §32): offense, defense and special teams; empty before the file exists. */
	public static function lineup( $abbr ) {
		return self::get( 'lineups.json' )['teams'][ $abbr ] ?? null;
	}

	/** The series since 1999 between two teams (SCOPE §33), read from $a's side: w/l, home/road, most points, biggest win keyed by team. Null before the file exists. */
	public static function series( $a, $b ) {
		$f = self::get( 'series.json' ); $k = array( $a, $b ); sort( $k );
		$s = $f['pairs'][ implode( '-', $k ) ] ?? null;
		if ( ! $s ) { return null; }
		if ( $s['a'] !== $a ) {  /* stored alphabetically from the first team's side; flip to the asking team's: its home games were the other's road games */
			$s = array_merge( $s, array( 'a' => $a, 'b' => $b, 'w' => $s['l'], 'l' => $s['w'], 'post' => array( $s['post'][1], $s['post'][0] ),
				'home' => array( $s['road'][1], $s['road'][0] ), 'road' => array( $s['home'][1], $s['home'][0] ) ) );
		}
		return $s;
	}

	/** A team's record this season, broken down (SCOPE §33): rec[all|div|conf|nonconf|home|away] = [w, l, t], pf, pa, g, last5, streak. */
	public static function record_split( $abbr ) {
		return self::get( 'series.json' )['records'][ $abbr ] ?? null;
	}

	/** A final game's box score (SCOPE §32): ['keys' => offense columns, 'away' => [...], 'home' => [...]]; null before the file exists. */
	public static function box( $game ) {
		$f = self::get( 'box/w' . (int) ( $game['week'] ?? 0 ) . '.json' );
		$g = $f['games'][ $game['id'] ?? '' ] ?? null;
		return $g ? array_merge( $g, array( 'keys' => $f['keys'] ?? array() ) ) : null;
	}

	/** A final game's win probability record (SCOPE §30), from wp/w{week}.json; null before the file exists or for a game not yet final. */
	public static function win_prob( $game ) {
		$f = self::get( 'wp/w' . (int) ( $game['week'] ?? 0 ) . '.json' );
		return $f['games'][ $game['id'] ?? '' ] ?? null;
	}

	public static function team_game( $abbr, $week ) {
		foreach ( self::week_games( $week ) as $g ) { if ( $g['home'] === $abbr || $g['away'] === $abbr ) { return $g; } }
		return null;
	}

	/** Find a game anywhere in the schedule by id. */
	public static function game( $id ) {
		foreach ( ( self::schedule()['weeks'] ?? array() ) as $games ) {
			foreach ( $games as $g ) {
				if ( $g['id'] === $id ) { return $g; }
			}
		}
		return null;
	}

	/** Win-loss-tie records for every team, from the schedule's final scores. */
	public static function records() {
		if ( isset( self::$cache['__records'] ) ) { return self::$cache['__records']; }
		$rec = array();
		foreach ( array_keys( self::teams() ) as $a ) { $rec[ $a ] = array( 0, 0, 0 ); }
		foreach ( ( self::schedule()['weeks'] ?? array() ) as $games ) {
			foreach ( $games as $g ) {
				if ( ! isset( $g['home_score'], $g['away_score'] ) || $g['home_score'] === null ) { continue; }
				if ( ( $g['game_type'] ?? 'REG' ) !== 'REG' ) { continue; }
				$h = $g['home']; $a = $g['away'];
				if ( ! isset( $rec[ $h ], $rec[ $a ] ) ) { continue; }
				if ( $g['home_score'] > $g['away_score'] )      { $rec[ $h ][0]++; $rec[ $a ][1]++; }
				elseif ( $g['home_score'] < $g['away_score'] )  { $rec[ $a ][0]++; $rec[ $h ][1]++; }
				else                                            { $rec[ $h ][2]++; $rec[ $a ][2]++; }
			}
		}
		self::$cache['__records'] = $rec;
		return $rec;
	}

	public static function record_text( $abbr ) {
		$r = self::records()[ $abbr ] ?? array( 0, 0, 0 );
		return $r[0] . '-' . $r[1] . ( $r[2] ? '-' . $r[2] : '' );
	}

	public static function win_pct( $abbr ) {
		$r = self::records()[ $abbr ] ?? array( 0, 0, 0 );
		$n = $r[0] + $r[1] + $r[2];
		return $n ? ( $r[0] + $r[2] / 2 ) / $n : 0;
	}

	/** Rank of a team's metric: side 'off' or 'def'. Null before Week 1. */
	public static function rank( $side, $abbr, $metric = 'epa' ) {
		$m = self::metrics();
		return $m['rank'][ $side ][ $abbr ][ $metric ] ?? null;
	}

	/** Teams on bye in a given week: everyone not in that week's games. */
	public static function byes( $week ) {
		$playing = array();
		foreach ( self::week_games( $week ) as $g ) { $playing[ $g['home'] ] = 1; $playing[ $g['away'] ] = 1; }
		return array_values( array_filter( array_keys( self::teams() ), function ( $a ) use ( $playing ) { return empty( $playing[ $a ] ); } ) );
	}

	/** Version string for cache-busting the play files: the file's hash from the manifest. */
	public static function file_version( $rel ) {
		$m = self::manifest();
		return isset( $m['files'][ $rel ] ) ? substr( $m['files'][ $rel ], 0, 10 ) : DBD_VERSION;
	}

	/** "Updated Oct 30, 5:12 AM ET" from the manifest's built_at. */
	public static function updated_text() {
		$m = self::manifest();
		if ( empty( $m['built_at'] ) ) { return ''; }
		try {
			$d = new DateTime( $m['built_at'], new DateTimeZone( 'UTC' ) );
			$d->setTimezone( new DateTimeZone( 'America/New_York' ) );
			return 'Updated ' . $d->format( 'M j, g:i A' ) . ' ET';
		} catch ( Exception $e ) { return ''; }
	}

	/** Kickoff as a UTC ISO string plus an Eastern-time label for a game row. */
	public static function kickoff( $g ) {
		try {
			$d = new DateTime( $g['date'] . ' ' . ( $g['time'] ?: '13:00' ), new DateTimeZone( 'America/New_York' ) );
			$et = $d->format( 'g:i A' );
			$d->setTimezone( new DateTimeZone( 'UTC' ) );
			return array( 'utc' => $d->format( 'c' ), 'et' => $et . ' ET' );
		} catch ( Exception $e ) { return array( 'utc' => '', 'et' => '' ); }
	}

	/** Site-wide totals computed at sync time (snaps charted, etc.). */
	public static function stats() {
		return get_option( 'dbd_stats', array() );
	}
}
