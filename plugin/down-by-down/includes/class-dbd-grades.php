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
	/** Opponent context (SCOPE §19): which defensive metric a position is measured against, with tier cutoffs. Rank 1 is the toughest. */
	const DEF_TOUGH = 10; const DEF_SOFT = 23; const DEF_CORR = 0.4;
	public static function defense_key( $pos ) {
		if ( $pos === 'QB' ) { return array( 'key' => 'pass_epa', 'lab' => 'vs the pass', 'unit' => 'per dropback' ); }
		if ( $pos === 'RB' || $pos === 'FB' ) { return array( 'key' => 'run_epa', 'lab' => 'vs the run', 'unit' => 'per designed carry' ); }
		if ( $pos === 'TE' ) { return array( 'key' => 'TE_epa', 'lab' => 'vs TE', 'unit' => 'per target to tight ends' ); }
		return array( 'key' => 'WR_epa', 'lab' => 'vs WR', 'unit' => 'per target to wide receivers' );
	}
	public static function defense_tier( $rk ) { return $rk === null ? '' : ( $rk <= self::DEF_TOUGH ? 'tough' : ( $rk >= self::DEF_SOFT ? 'soft' : 'mid' ) ); }

	/** Trend lines (SCOPE §21): the four per-game metrics per group (usage, yards, efficiency, role), the rolling window, the streak length,
	 *  the direction threshold (fraction of the league's per-game interquartile range) and the near-median band for the header chip. */
	const TREND_WIN = 3; const TREND_STREAK = 3; const TREND_DIR = 0.5; const TREND_NEAR = 0.25;
	/** key, label, format kind (n = count, f1, sgn, sgn1), numerator fields, denominator fields (null = a count per game), weight field (cpoe is per attempt). */
	public static function trend_defs( $grp ) {
		static $d = array(
			'rec' => array( array( 'tgt', 'Targets', 'n', array( 'targets' ), null, null ), array( 'yds', 'Rec yards', 'n', array( 'receiving_yards' ), null, null ), array( 'epat', 'EPA / target', 'sgn', array( 'receiving_epa' ), array( 'targets' ), null ), array( 'adot', 'aDOT', 'f1', array( 'receiving_air_yards' ), array( 'targets' ), null ) ),
			'rb'  => array( array( 'tch', 'Touches', 'n', array( 'carries', 'targets' ), null, null ), array( 'yds', 'Scrimmage yds', 'n', array( 'rushing_yards', 'receiving_yards' ), null, null ), array( 'epatch', 'EPA / touch', 'sgn', array( 'rushing_epa', 'receiving_epa' ), array( 'carries', 'targets' ), null ), array( 'tgt', 'Targets', 'n', array( 'targets' ), null, null ) ),
			'qb'  => array( array( 'db', 'Dropbacks', 'n', array( 'attempts', 'sacks_suffered' ), null, null ), array( 'yds', 'Pass yards', 'n', array( 'passing_yards' ), null, null ), array( 'epadb', 'EPA / dropback', 'sgn', array( 'passing_epa' ), array( 'attempts', 'sacks_suffered' ), null ), array( 'cpoe', 'CPOE', 'sgn1', array( 'passing_cpoe' ), array( 'attempts' ), 'attempts' ) ),
		);
		return $d[ $grp ] ?? array();
	}
	/** One game's numerator and denominator for a trend metric, from a weekly stat row. A count metric has a denominator of 1. */
	public static function trend_parts( $def, $w ) {
		$n = 0; foreach ( $def[3] as $k ) { $n += (float) ( $w[ $k ] ?? 0 ); }
		if ( $def[5] ) { $n *= (float) ( $w[ $def[5] ] ?? 0 ); }
		if ( $def[4] === null ) { return array( $n, 1.0 ); }
		$dn = 0; foreach ( $def[4] as $k ) { $dn += (float) ( $w[ $k ] ?? 0 ); }
		return array( $n, $dn );
	}
	/** Format a trend value: counts print whole when whole, else one decimal; the rest use fmt(). */
	public static function trend_fmt( $kind, $v ) {
		if ( $v === null ) { return '–'; }
		if ( $kind === 'n' ) { return abs( $v - round( $v ) ) < 1e-9 ? (string) (int) round( $v ) : number_format( (float) $v, 1 ); }
		return self::fmt( $kind, $v );
	}
	/** The league's per-game quartiles for a trend metric: {p25, p50, p75, n} or null when the data file predates them. */
	public static function trend_league( $grp, $key ) {
		return self::group_info( $grp )['game'][ $key ] ?? null;
	}

	/** Weekly movers (SCOPE §22): the build's floors and thresholds, repeated here for the panel's notes. */
	const MV_FLOOR_SNAPS = 12; const MV_FLOOR_TARGETS = 6; const MV_FLOOR_EPA = 4.0; const MV_PER = 5; const MV_DEBUT = 25; const MV_START = 50;

	/** Playstyle fingerprint (SCOPE §23): a standout sits at or past these percentiles; a situational share has moved past FP_MOVE of the team's plays. */
	const FP_HI = 75; const FP_LO = 25; const FP_MOVE = 0.04;
	/** key, label, sub-label, kind (pct|sgn), high tag, low tag, high sentence, low sentence. Sentences take {F} first name, {v} his figure, {l} league median, {sh} his overall share; null = situational, read against his share. */
	public static function fp_defs( $grp ) {
		static $d = array(
			'rec' => array(
				array( 'deep', 'Deep share', 'targets 20+ yards downfield', 'pct', 'Downfield', 'Rarely deep', '{F} is a downfield target: {v} of his targets travel 20 yards or more, against {l} for the league\'s receivers.', '{F} rarely goes deep: {v} of his targets travel 20 yards or more, against {l} for the league\'s receivers.' ),
				array( 'scr', 'Screens', 'share of his targets', 'pct', 'Screen game', 'No screens', 'The screen game runs through him: {v} of his targets are screens (league {l}).', 'Screens are not part of his role: {v} of his targets (league {l}).' ),
				array( 'mid', 'Middle of the field', 'targets between the numbers', 'pct', 'Works the middle', 'Stays outside', 'He works the middle of the field: {v} of his targets come between the numbers (league {l}).', 'He lives outside the numbers: only {v} of his targets come over the middle (league {l}).' ),
				array( 'yacs', 'After the catch', 'share of his yards after the catch', 'pct', 'Yards after the catch', 'Wins before the catch', 'Much of his damage comes after the catch: {v} of his yards (league {l}).', 'He wins before the catch rather than after it: {v} of his yards come after the catch (league {l}).' ),
				array( 'd3', 'Third-down share', 'of team targets on 3rd down', 'pct', 'Third-down guy', 'Not the third-down option', null, null ),
				array( 'rzs', 'Red-zone share', 'of team targets inside the 20', 'pct', 'Red-zone guy', 'Not the red-zone option', null, null ),
				array( 'mot', 'Motion', 'targets with pre-snap motion', 'pct', 'Motion man', 'Static', 'Motion is part of the plan: {v} of his targets come with pre-snap movement (league {l}).', 'He rarely moves before the snap: {v} of his targets come with motion (league {l}).' ),
				array( 'pa', 'Play action', 'targets off play action', 'pct', 'Play-action target', 'Dropback target', 'Play action finds him: {v} of his targets come off a fake (league {l}).', 'He is a straight-dropback target: {v} of his targets come off play action (league {l}).' ),
			),
			'rb' => array(
				array( 'ins', 'Inside runs', 'carries at guard or tackle', 'pct', 'Between the tackles', 'Bounces outside', 'He runs between the tackles: {v} of his carries go at guard or tackle (league {l}).', 'He gets to the edge: only {v} of his carries go inside (league {l}).' ),
				array( 'sg', 'Shotgun runs', 'carries from the gun', 'pct', 'Gun runner', 'Under center', 'His carries come from the gun: {v} of them (league {l}).', 'He runs from under center: {v} of his carries come from shotgun (league {l}).' ),
				array( 'recs', 'Receiving share', 'targets per touch', 'pct', 'Pass-catching back', 'Ground only', 'He is a pass-catching back: {v} of his touches are targets (league {l}).', 'The passing game barely involves him: {v} of his touches are targets (league {l}).' ),
				array( 'd3', 'Third-down share', 'of team touches on 3rd down', 'pct', 'Third-down back', 'Not the third-down back', null, null ),
				array( 'rzs', 'Red-zone share', 'of team touches inside the 20', 'pct', 'Red-zone back', 'Not the red-zone back', null, null ),
				array( 'shorty', 'Short-yardage share', 'of team touches, 2 or fewer to go', 'pct', 'Short-yardage back', 'Not the short-yardage back', 'In short yardage he is the call: {v} of the team\'s touches with two or fewer to go (his overall share is {sh}).', 'Short yardage goes elsewhere: {v} of those touches against {sh} overall.' ),
				array( 'rpo', 'RPO', 'carries on run-pass options', 'pct', 'RPO back', '', 'Run-pass options feed him: {v} of his carries (league {l}).', 'RPOs are rare for him: {v} of his carries (league {l}).' ),
				array( 'mot', 'Motion', 'touches with pre-snap motion', 'pct', 'Motion man', 'Static', 'Motion is part of the plan: {v} of his touches come with pre-snap movement (league {l}).', 'He rarely moves before the snap: {v} of his touches come with motion (league {l}).' ),
			),
			'qb' => array(
				array( 'deep', 'Deep share', 'attempts 20+ yards downfield', 'pct', 'Downfield thrower', 'Keeps it short', 'He pushes the ball downfield: {v} of his attempts travel 20 yards or more (league {l}).', 'He keeps it short: {v} of his attempts travel 20 yards or more (league {l}).' ),
				array( 'mid', 'Middle of the field', 'attempts between the numbers', 'pct', 'Attacks the middle', 'Sidelines', 'He attacks the middle of the field: {v} of his attempts go between the numbers (league {l}).', 'He works the sidelines: {v} of his attempts go between the numbers (league {l}).' ),
				array( 'pa', 'Play action', 'share of attempts', 'pct', 'Play-action passer', 'Straight dropback', 'Play action is a staple: {v} of his attempts come off a fake (league {l}).', 'Straight dropbacks: {v} of his attempts come off play action (league {l}).' ),
				array( 'rpo', 'RPO', 'share of dropbacks', 'pct', 'RPO offense', '', 'The RPO is part of his offense: {v} of his dropbacks (league {l}).', 'Almost no RPOs: {v} of his dropbacks (league {l}).' ),
				array( 'scram', 'Scrambles', 'share of dropbacks', 'pct', 'Scrambler', 'Pocket passer', 'He runs when it breaks down: {v} of his dropbacks end in a scramble (league {l}).', 'He stays in the pocket: {v} of his dropbacks end in a scramble (league {l}).' ),
				array( 'sg', 'Shotgun', 'share of dropbacks', 'pct', 'Shotgun offense', 'Under center', 'A shotgun offense: {v} of his dropbacks come from the gun (league {l}).', 'He works under center more than most: {v} of his dropbacks come from the gun (league {l}).' ),
				array( 'blz', 'Against the blitz', 'EPA per dropback, blitzed minus not', 'sgn', 'Blitz-proof', 'Blitz him', 'He beats the blitz: {v} EPA a dropback better when blitzed than not (league {l}).', 'The blitz gets to him: {v} EPA a dropback when blitzed against not (league {l}).' ),
				array( 'mot', 'Motion', 'dropbacks with pre-snap motion', 'pct', 'Motion offense', 'Static', 'Motion is part of the plan: {v} of his dropbacks come with pre-snap movement (league {l}).', 'Little motion: {v} of his dropbacks come with pre-snap movement (league {l}).' ),
			),
		);
		return $d[ $grp ] ?? array();
	}
	public static function fp_unit( $grp ) { return array( 'rec' => 'targets', 'rb' => 'touches', 'qb' => 'dropbacks' )[ $grp ] ?? 'plays'; }
	public static function fp_fmt( $kind, $v ) { if ( $v === null ) { return '–'; } return $kind === 'sgn' ? ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 ) : round( $v * 100 ) . '%'; }

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

	/** The group's primary yardage keys: what a week is measured in. */
	public static function yds_keys( $grp ) {
		return array( 'rec' => array( 'receiving_yards' ), 'rb' => array( 'rushing_yards', 'receiving_yards' ), 'qb' => array( 'passing_yards' ) )[ $grp ] ?? array( 'receiving_yards' );
	}

	/** Weekly primary yards for weeks 1..$max: a number for a played week, null for a bye or a miss. */
	public static function week_series( $rec, $grp, $max ) {
		$keys = self::yds_keys( $grp ); $by = array();
		foreach ( $rec['weeks'] ?? array() as $w ) { $v = 0; foreach ( $keys as $k ) { $v += (float) ( $w[ $k ] ?? 0 ); } $by[ (int) $w['week'] ] = $v; }
		$out = array();
		for ( $wk = 1; $wk <= $max; $wk++ ) { $out[] = array_key_exists( $wk, $by ) ? round( $by[ $wk ] ) : ( isset( $rec['snaps'][ (string) $wk ] ) ? 0 : null ); }
		return $out;
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
	public static function pctile( $grp, $key, $v, $pool = 'dist' ) {
		$gi = self::group_info( $grp ); $a = $gi[ $pool ][ $key ] ?? array();
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

	/** The pool's median for a metric, or null. */
	public static function median( $grp, $key, $pool = 'dist' ) {
		$a = self::group_info( $grp )[ $pool ][ $key ] ?? array();
		return $a ? $a[ (int) floor( ( count( $a ) - 1 ) / 2 ) ] : null;
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
