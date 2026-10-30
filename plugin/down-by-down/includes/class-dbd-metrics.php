<?php
/**
 * Metric definitions, formatting, ranks and the edge score shared by the matchup page and takeaways.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Metrics {

	/** Head-to-head rows, grouped. Keys match metrics.json. */
	public static function rows() {
		return array(
			'Overall'             => array( 'epa' => 'EPA per play', 'sr' => 'Success rate', 'ypp' => 'Yards per play', 'expl' => 'Explosive play rate' ),
			'Passing'             => array( 'pass_epa' => 'Pass EPA per dropback', 'sack' => 'Sack rate' ),
			'Running'             => array( 'run_epa' => 'Run EPA per carry', 'ypc' => 'Yards per carry' ),
			'Money downs'         => array( 'third' => '3rd down conversion', 'rz_td' => 'Red zone TD rate' ),
			'Targets by position' => array( 'WR_yds_pg' => 'WR yards per game', 'TE_yds_pg' => 'TE yards per game', 'TE_tgt_pg' => 'TE targets per game', 'RB_yds_pg' => 'RB receiving yards per game' ),
		);
	}

	public static function group_of( $key ) {
		foreach ( self::rows() as $g => $keys ) {
			if ( isset( $keys[ $key ] ) ) { return $g; }
		}
		return '';
	}

	public static function label( $key ) {
		foreach ( self::rows() as $keys ) {
			if ( isset( $keys[ $key ] ) ) { return $keys[ $key ]; }
		}
		return $key;
	}

	/** Format a metric value for display. */
	public static function fmt( $key, $v ) {
		if ( $v === null || ! is_numeric( $v ) ) { return '–'; }
		switch ( $key ) {
			case 'epa': case 'pass_epa': case 'run_epa':
				return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 );
			case 'sr': case 'expl': case 'sack': case 'third': case 'rz_td': case 'passrate':
				return round( $v * 100 ) . '%';
			case 'ypp': case 'ypc': case 'TE_tgt_pg': case 'WR_tgt_pg': case 'RB_tgt_pg':
				return number_format( (float) $v, 1 );
			default:
				return (string) round( $v );
		}
	}

	public static function ordinal( $n ) {
		$n = (int) $n;
		if ( $n % 100 >= 11 && $n % 100 <= 13 ) { return $n . 'th'; }
		$s = array( 'th', 'st', 'nd', 'rd' );
		return $n . ( $s[ $n % 10 ] ?? 'th' );
	}

	/** Rank badge: top 10 green, bottom 10 red. */
	public static function rank_badge( $rank ) {
		if ( ! $rank ) { return ''; }
		$cls = $rank <= 10 ? 'g' : ( $rank >= 23 ? 'b' : '' );
		return '<span class="dbd-rk ' . $cls . '">#' . (int) $rank . '</span>';
	}

	/**
	 * Edge score from 0 to 1: how much a matchup row favors the offense.
	 * Ranks are already oriented so 1 is best for that side, so no inversion is needed.
	 */
	public static function score( $off_rank, $def_rank ) {
		if ( ! $off_rank || ! $def_rank ) { return 0.5; }
		return ( ( 33 - $off_rank ) / 32 + $def_rank / 32 ) / 2;
	}

	/** Offense value and rank for a metric, or nulls. */
	public static function off( $abbr, $key ) {
		$m = DBD_Data::metrics();
		return array( $m['off'][ $abbr ][ $key ] ?? null, $m['rank']['off'][ $abbr ][ $key ] ?? null );
	}

	public static function def( $abbr, $key ) {
		$m = DBD_Data::metrics();
		return array( $m['defense'][ $abbr ][ $key ] ?? null, $m['rank']['def'][ $abbr ][ $key ] ?? null );
	}

	public static function league( $key ) {
		$m = DBD_Data::metrics();
		return $m['league'][ $key ] ?? null;
	}

	public static function games_played( $abbr, $side = 'off' ) {
		$m = DBD_Data::metrics();
		$k = $side === 'off' ? 'off' : 'defense';
		return (int) ( $m[ $k ][ $abbr ]['games'] ?? 0 );
	}

	/** Pass zones for a team: side 'off' or 'def'. Each: n, share, epa. */
	public static function zones( $abbr, $side ) {
		$m = DBD_Data::metrics();
		$k = $side === 'off' ? 'off' : 'defense';
		return $m[ $k ][ $abbr ]['zones'] ?? array();
	}
}
