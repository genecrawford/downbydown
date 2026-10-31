<?php
/**
 * Player grades (SCOPE §15): percentile and rank of a player's season metrics against every qualifying
 * player at his position group, from players/league.json (sorted metric lists per group) that the build writes.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Grades {

	/** key, label, sub-label, format kind. Lower-is-better keys are flagged in the league file. */
	public static function defs( $grp ) {
		static $d = array(
			'rec' => array( array( 'tshare', 'Target share', 'of team targets', 'pct' ), array( 'adot', 'Depth of target', 'air yards per target', 'f1' ), array( 'yacpc', 'After the catch', 'yards per reception', 'f1' ), array( 'catch', 'Catch rate', 'receptions per target', 'pct' ),
				array( 'epat', 'EPA per target', '', 'sgn' ), array( 'fdpt', 'First downs', 'per target', 'pct' ), array( 'drop', 'Drop rate', 'drops per target, lower is better', 'pct' ), array( 'ypg', 'Yards per game', '', 'f1' ) ),
			'rb'  => array( array( 'ypc', 'Yards per carry', '', 'f1' ), array( 'repc', 'Rush EPA per carry', '', 'sgn' ), array( 'epat', 'EPA per target', 'receiving, 5+ targets', 'sgn' ), array( 'btpt', 'Broken tackles', 'per touch', 'pct' ),
				array( 'expl', 'Explosive rate', '10+ run, 20+ catch per touch', 'pct' ), array( 'tpg', 'Touches per game', '', 'f1' ), array( 'tgtshare', 'Receiving share', 'targets per touch', 'pct' ), array( 'ypt', 'Yards per touch', '', 'f1' ) ),
			'qb'  => array( array( 'epadb', 'EPA per dropback', '', 'sgn' ), array( 'cpoe', 'CPOE', 'completion over expected', 'sgn1' ), array( 'sack', 'Sack rate', 'per dropback, lower is better', 'pct' ), array( 'ypa', 'Yards per attempt', '', 'f1' ),
				array( 'tdr', 'TD rate', 'per attempt', 'pct' ), array( 'intr', 'INT rate', 'per attempt, lower is better', 'pct' ), array( 'adot', 'Depth of target', 'air yards per attempt', 'f1' ), array( 'rushepa', 'Rush EPA per game', '', 'sgn' ) ),
		);
		return $d[ $grp ] ?? array();
	}

	/** Position group for a roster position: qb, rb, rec, or null. */
	public static function group_of( $pos ) {
		return array( 'QB' => 'qb', 'RB' => 'rb', 'FB' => 'rb', 'WR' => 'rec', 'TE' => 'rec' )[ $pos ] ?? null;
	}

	/** Whether an index row clears the group's qualifier. */
	public static function qualifies( $row ) {
		$g = self::group_of( $row['pos'] ?? '' );
		if ( $g === 'rec' ) { return ( $row['tgt'] ?? 0 ) >= 15; }
		if ( $g === 'rb' )  { return ( ( $row['tgt'] ?? 0 ) + ( $row['car'] ?? 0 ) ) >= 30; }
		if ( $g === 'qb' )  { return ( $row['att'] ?? 0 ) >= 50; }
		return false;
	}

	/** Usage per game, the yardstick for "players like him": targets, touches or attempts. */
	public static function usage( $row ) {
		$g = self::group_of( $row['pos'] ?? '' ); $games = max( 1, (int) ( $row['g'] ?? 1 ) );
		if ( $g === 'rec' ) { return ( $row['tgt'] ?? 0 ) / $games; }
		if ( $g === 'rb' )  { return ( ( $row['tgt'] ?? 0 ) + ( $row['car'] ?? 0 ) ) / $games; }
		return ( $row['att'] ?? 0 ) / $games;
	}

	/** Suggested rivals: the n qualified players in the same group whose usage per game is nearest his. */
	public static function suggest( $row, $n = 3 ) {
		$g = self::group_of( $row['pos'] ?? '' ); if ( ! $g ) { return array(); }
		$u = self::usage( $row ); $c = array();
		foreach ( DBD_Data::player_index() as $o ) {
			if ( $o['id'] === $row['id'] || self::group_of( $o['pos'] ) !== $g || ! self::qualifies( $o ) || ( $o['g'] ?? 0 ) < 3 ) { continue; }
			$c[] = array( abs( self::usage( $o ) - $u ), $o );
		}
		usort( $c, function ( $x, $y ) { return $x[0] <=> $y[0]; } );
		return array_map( function ( $x ) { return $x[1]; }, array_slice( $c, 0, $n ) );
	}

	public static function pool_label( $grp ) {
		return array( 'rec' => 'receivers and tight ends', 'rb' => 'backs', 'qb' => 'quarterbacks' )[ $grp ] ?? 'players';
	}

	public static function league() {
		return DBD_Data::get( 'players/league.json' ) ?: array();
	}

	public static function group_info( $grp ) {
		return self::league()['groups'][ $grp ] ?? null;
	}

	/** Percentile 0 to 100 among the pool: players below plus half the ties, flipped for lower-is-better. Null when ungradable. */
	public static function pctile( $grp, $key, $v ) {
		$gi = self::group_info( $grp ); $a = $gi['dist'][ $key ] ?? array();
		if ( $v === null || ! $a ) { return null; }
		$below = 0; $eq = 0;
		foreach ( $a as $x ) { if ( $x < $v ) { $below++; } elseif ( $x == $v ) { $eq++; } }
		$pc = ( $below + $eq / 2 ) / count( $a );
		if ( in_array( $key, $gi['low'] ?? array(), true ) ) { $pc = 1 - $pc; }
		return (int) round( $pc * 100 );
	}

	/** Rank 1..n among the pool: one plus the players strictly better. */
	public static function rank( $grp, $key, $v ) {
		$gi = self::group_info( $grp ); $a = $gi['dist'][ $key ] ?? array();
		if ( $v === null || ! $a ) { return null; }
		$low = in_array( $key, $gi['low'] ?? array(), true ); $better = 0;
		foreach ( $a as $x ) { if ( $low ? $x < $v : $x > $v ) { $better++; } }
		return $better + 1;
	}

	public static function fmt( $kind, $v ) {
		if ( $v === null ) { return '-'; }
		switch ( $kind ) {
			case 'pct':  return round( $v * 100 ) . '%';
			case 'f1':   return number_format( (float) $v, 1 );
			case 'sgn1': return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 1 );
			default:     return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 );
		}
	}

	public static function ordinal( $n ) {
		$n = (int) $n; $v = $n % 100; $s = array( 'th', 'st', 'nd', 'rd' );
		return $n . ( ( $v >= 11 && $v <= 13 ) ? 'th' : ( $s[ $n % 10 ] ?? 'th' ) );
	}

	/** NFL passer rating from the four components. */
	public static function passer_rating( $cmp, $att, $yds, $td, $int ) {
		if ( ! $att ) { return null; }
		$c = function ( $x ) { return max( 0, min( 2.375, $x ) ); };
		return ( $c( ( $cmp / $att - 0.3 ) * 5 ) + $c( ( $yds / $att - 3 ) * 0.25 ) + $c( $td / $att * 20 ) + $c( 2.375 - $int / $att * 25 ) ) / 6 * 100;
	}
}
