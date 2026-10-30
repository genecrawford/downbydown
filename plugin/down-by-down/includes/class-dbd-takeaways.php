<?php
/**
 * Generated matchup takeaways. Rules from SCOPE.md section 6: score every metric, pick the biggest edge,
 * the watch-out, the opportunity zone and the wash, fill a sentence template per metric.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Takeaways {

	const ZONE_LABEL = array( 'deep_left' => 'deep left', 'deep_middle' => 'deep middle', 'deep_right' => 'deep right', 'short_left' => 'short left', 'short_middle' => 'short middle', 'short_right' => 'short right' );
	const POS = array( 'WR_yds_pg' => 'wide receiver', 'TE_yds_pg' => 'tight end', 'TE_tgt_pg' => 'tight end', 'RB_yds_pg' => 'running back' );

	/** Headings per metric: [edge heading, watch-out heading]. */
	private static function heads( $o, $d ) {
		return array(
			'epa'       => array( "Efficiency gap favors $o", "$d win the efficiency battle" ),
			'sr'        => array( 'Down-to-down consistency', 'Stalled drives ahead' ),
			'ypp'       => array( 'Chunk yardage', 'Yards come hard' ),
			'expl'      => array( 'Big plays are there', 'Big plays will be scarce' ),
			'pass_epa'  => array( 'Throw it', 'Passing lanes are closed' ),
			'sack'      => array( 'Clean pockets', 'Pressure problem' ),
			'run_epa'   => array( 'Run it at them', 'Nowhere to run' ),
			'ypc'       => array( 'Ground game edge', 'Ground game stalls' ),
			'third'     => array( "Third downs favor $o", "Third downs favor $d" ),
			'rz_td'     => array( 'Red zone touchdowns', 'Field goals, not touchdowns' ),
			'WR_yds_pg' => array( 'Wideouts feast', 'Wideouts get blanketed' ),
			'TE_yds_pg' => array( 'Tight ends feast', 'Tight ends go quiet' ),
			'TE_tgt_pg' => array( 'Tight ends feast', 'Tight ends go quiet' ),
			'RB_yds_pg' => array( 'Backs catch passes', 'Checkdowns go nowhere' ),
		);
	}

	/** One sentence for a metric, offense O against defense D. */
	public static function sentence( $key, $O, $D ) {
		list( $ov, $or ) = DBD_Metrics::off( $O, $key );
		list( $dv, $dr ) = DBD_Metrics::def( $D, $key );
		$o = DBD_Data::team( $O )['nick'] ?? $O; $d = DBD_Data::team( $D )['nick'] ?? $D;
		$f = function ( $v ) use ( $key ) { return DBD_Metrics::fmt( $key, $v ); };
		$ovs = $f( $ov ); $dvs = $f( $dv ); $lg = $f( DBD_Metrics::league( $key ) );
		$ors = DBD_Metrics::ordinal( $or ); $drs = DBD_Metrics::ordinal( $dr );
		switch ( $key ) {
			case 'epa':      return "$o average $ovs EPA per play ($ors). $d allow $dvs ($drs). League average is $lg.";
			case 'sr':       return "$o succeed on $ovs of plays ($ors) against a defense that allows $dvs ($drs).";
			case 'ypp':      return "$o gain $ovs yards per play ($ors). $d give up $dvs ($drs), with the league at $lg.";
			case 'expl':     return "$o create explosive plays on $ovs of snaps ($ors). $d give them up at $dvs ($drs).";
			case 'pass_epa': return "$o gain $ovs EPA per dropback ($ors). $d allow $dvs ($drs).";
			case 'sack':     return "$o take a sack on $ovs of dropbacks ($ors). $d get home on $dvs ($drs). League average is $lg.";
			case 'run_epa':  return "$o' run game is $ors in EPA per carry ($ovs). $d allow $dvs ($drs).";
			case 'ypc':      return "$o average $ovs yards per carry ($ors). $d allow $dvs ($drs).";
			case 'third':    return "$o convert $ovs of third downs ($ors). $d allow $dvs ($drs).";
			case 'rz_td':    return "$o score a touchdown on $ovs of red zone trips ($ors). $d allow $dvs ($drs).";
			default:
				$what = strpos( $key, 'tgt' ) !== false ? 'targets' : 'yards';
				$pos  = self::POS[ $key ] ?? 'receiver';
				return "$d give up $dvs $what a game to {$pos}s ($drs), against a league average of $lg. $o' {$pos}s produce $ovs ($ors).";
		}
	}

	/**
	 * The four cards for offense O against defense D.
	 * Returns a list of [tag, class, heading, text] or an empty array before Week 1.
	 */
	public static function cards( $O, $D ) {
		$m = DBD_Data::metrics();
		if ( empty( $m['off'][ $O ] ) || empty( $m['defense'][ $D ] ) ) { return array(); }
		$o = DBD_Data::team( $O )['nick'] ?? $O; $d = DBD_Data::team( $D )['nick'] ?? $D;
		$heads = self::heads( $o, $d );

		$cand = array();
		foreach ( DBD_Metrics::rows() as $group => $keys ) {
			if ( $group === 'Overall' ) { continue; }
			foreach ( array_keys( $keys ) as $k ) {
				list( , $or ) = DBD_Metrics::off( $O, $k ); list( , $dr ) = DBD_Metrics::def( $D, $k );
				if ( ! $or || ! $dr ) { continue; }
				$cand[] = array( 'k' => $k, 's' => DBD_Metrics::score( $or, $dr ), 'gap' => abs( $or - $dr ) );
			}
		}
		if ( ! $cand ) { return array(); }
		usort( $cand, function ( $a, $b ) { return $b['s'] <=> $a['s'] ?: $b['gap'] <=> $a['gap']; } );
		$best = $cand[0]; $worst = end( $cand );
		$used = array( $best['k'] => 1, $worst['k'] => 1 );

		// Opportunity: the defense's worst zone with at least 10 throws.
		$zones = array_filter( DBD_Metrics::zones( $D, 'def' ), function ( $z ) { return ( $z['n'] ?? 0 ) >= 10 && isset( $z['epa'] ); } );
		uasort( $zones, function ( $a, $b ) { return $b['epa'] <=> $a['epa']; } );
		$zk = key( $zones ); $zv = $zk ? $zones[ $zk ] : null;
		$offz = DBD_Metrics::zones( $O, 'off' );
		$opp = null;
		if ( $zv ) {
			$share = $offz[ $zk ]['share'] ?? 0;
			$txt = sprintf( '%s are worst against %s throws, allowing %s EPA per attempt. %s send %d%% of their throws there.', $d, self::ZONE_LABEL[ $zk ], DBD_Metrics::fmt( 'epa', $zv['epa'] ), $o, round( $share * 100 ) );
			if ( $share < 0.10 ) { $txt .= ' That is a small part of their offense so far.'; }
			$opp = array( 'Opportunity', 'opp', ucfirst( self::ZONE_LABEL[ $zk ] ) . ' is open', $txt );
		}

		// Wash: the overall metric closest to even, skipping anything already used.
		$wash = null; $bestd = 9;
		foreach ( array_keys( DBD_Metrics::rows()['Overall'] ) as $k ) {
			if ( isset( $used[ $k ] ) ) { continue; }
			list( , $or ) = DBD_Metrics::off( $O, $k ); list( , $dr ) = DBD_Metrics::def( $D, $k );
			if ( ! $or || ! $dr ) { continue; }
			$dist = abs( DBD_Metrics::score( $or, $dr ) - 0.5 );
			if ( $dist < $bestd ) { $bestd = $dist; $wash = $k; }
		}

		$cards = array();
		$cards[] = array( 'Biggest edge', 'edge', $heads[ $best['k'] ][0] ?? ucfirst( DBD_Metrics::label( $best['k'] ) ), self::sentence( $best['k'], $O, $D ) );
		if ( $opp ) { $cards[] = $opp; }
		$cards[] = array( 'Watch out', 'warn', $heads[ $worst['k'] ][1] ?? ucfirst( DBD_Metrics::label( $worst['k'] ) ), self::sentence( $worst['k'], $O, $D ) );
		if ( $wash ) { $cards[] = array( 'Wash', 'even', DBD_Metrics::label( $wash ), 'Call this one even. ' . self::sentence( $wash, $O, $D ) ); }
		return $cards;
	}

	/** The single highest edge score for a game (either side), used to pick the Game of the Week. */
	public static function best_edge( $away, $home ) {
		$best = array( 's' => 0, 'O' => $away, 'D' => $home );
		foreach ( array( array( $away, $home ), array( $home, $away ) ) as $pair ) {
			list( $O, $D ) = $pair;
			foreach ( DBD_Metrics::rows() as $group => $keys ) {
				if ( $group === 'Overall' ) { continue; }
				foreach ( array_keys( $keys ) as $k ) {
					list( , $or ) = DBD_Metrics::off( $O, $k ); list( , $dr ) = DBD_Metrics::def( $D, $k );
					if ( ! $or || ! $dr ) { continue; }
					$s = DBD_Metrics::score( $or, $dr );
					if ( $s > $best['s'] ) { $best = array( 's' => $s, 'O' => $O, 'D' => $D, 'k' => $k ); }
				}
			}
		}
		return $best;
	}
}
