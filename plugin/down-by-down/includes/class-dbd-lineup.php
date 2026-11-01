<?php
/**
 * The lineup board (SCOPE §32): starters from the depth chart drawn like play art, offense below the line of
 * scrimmage and defense above, as the offense sees the field. Used by the game page (one offense against the other
 * team's defense) and the team players page (each unit on its own board). Data: lineups.json from the build.
 * Every team lists a 3WR 1TE offense and a Base 3-4 or 4-3 defense, and the position codes are a closed set, so each
 * slot has a fixed place. The nickel back, the extra defensive back most teams use against three receivers, is lighter.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Lineup {
	/** x, y in percent of the board; WR keys carry the depth chart slot (1 the X receiver, 8 the slot, 2 the Z) */
	const OFF   = array( 'LT' => array( 36, 60 ), 'LG' => array( 43, 60 ), 'C' => array( 50, 60 ), 'RG' => array( 57, 60 ), 'RT' => array( 64, 60 ), 'TE' => array( 71, 61 ), 'QB' => array( 50, 74 ), 'RB' => array( 59, 75 ), 'WR1' => array( 7, 59 ), 'WR8' => array( 21, 62 ), 'WR2' => array( 93, 59 ) );
	const DEF34 = array( 'RDE' => array( 42, 45 ), 'NT' => array( 50, 45 ), 'LDE' => array( 58, 45 ), 'WLB' => array( 31, 44 ), 'SLB' => array( 70, 44 ), 'RILB' => array( 44, 34 ), 'LILB' => array( 56, 34 ), 'RCB' => array( 7, 43 ), 'LCB' => array( 93, 43 ), 'NB' => array( 21, 39 ), 'SS' => array( 62, 22 ), 'FS' => array( 40, 20 ) );
	const DEF43 = array( 'RDE' => array( 38, 45 ), 'RDT' => array( 46, 45 ), 'LDT' => array( 54, 45 ), 'LDE' => array( 62, 45 ), 'WLB' => array( 34, 34 ), 'MLB' => array( 50, 33 ), 'SLB' => array( 67, 34 ), 'RCB' => array( 7, 43 ), 'LCB' => array( 93, 43 ), 'NB' => array( 21, 39 ), 'SS' => array( 62, 22 ), 'FS' => array( 40, 20 ) );
	const SKILL = array( 'QB', 'RB', 'TE', 'WR1', 'WR2', 'WR8' );
	const OFF_ORDER = array( 'QB', 'RB', 'WR1', 'WR2', 'WR8', 'TE', 'LT', 'LG', 'C', 'RG', 'RT' );
	const STAGGER = array( 'LG', 'RG', 'NT', 'LDT', 'RDT', 'RILB', 'MLB' );  /* every other interior lineman's name drops a line so neighbors never touch */

	public static function front( $lu ) { return strpos( (string) ( $lu['grp']['def'] ?? '' ), '3-4' ) !== false ? self::DEF34 : self::DEF43; }

	/** A skill player's season in one line, from his team file; empty for anyone the site does not track. */
	public static function off_line( $team, $id ) {
		$p = $id ? DBD_Data::player_record( $team, $id ) : null; if ( ! $p ) { return ''; }
		$s = function ( $k ) use ( $p ) { $t = 0; foreach ( $p['weeks'] ?? array() as $w ) { $t += (float) ( $w[ $k ] ?? 0 ); } return (int) round( $t ); };
		if ( $p['pos'] === 'QB' ) { return $s( 'completions' ) . '/' . $s( 'attempts' ) . ' · ' . $s( 'passing_yards' ) . ' yds · ' . $s( 'passing_tds' ) . ' TD · ' . $s( 'passing_interceptions' ) . ' INT'; }
		if ( in_array( $p['pos'], array( 'RB', 'FB' ), true ) ) { return $s( 'carries' ) . ' car · ' . $s( 'rushing_yards' ) . ' yds · ' . ( $s( 'rushing_tds' ) + $s( 'receiving_tds' ) ) . ' TD · ' . $s( 'receptions' ) . ' rec'; }
		return $s( 'receptions' ) . ' rec · ' . $s( 'receiving_yards' ) . ' yds · ' . $s( 'receiving_tds' ) . ' TD · ' . $s( 'targets' ) . ' tgt';
	}

	/** A defender's season: [tackles, sacks, interceptions, passes defended, tackles for loss] from lineups.json */
	public static function def_line( $e ) {
		$s = $e['s'] ?? null; if ( ! $s ) { return ''; }
		$b = array( (int) $s[0] . ' tackles' );
		if ( (float) $s[1] ) { $b[] = ( 0 + $s[1] ) . ' sack' . ( (float) $s[1] === 1.0 ? '' : 's' ); }
		if ( (int) $s[2] ) { $b[] = (int) $s[2] . ' INT'; }
		if ( (int) $s[3] ) { $b[] = (int) $s[3] . ' PD'; }
		return implode( ' · ', $b );
	}

	private static function last( $name ) { $p = explode( ' ', (string) $name ); $l = end( $p ); if ( in_array( $l, array( 'Jr.', 'Sr.', 'II', 'III', 'IV' ), true ) && count( $p ) > 1 ) { $l = $p[ count( $p ) - 2 ]; } return $l; }

	private static function chip( $team, $key, $x, $y, $e, $side ) {
		$t = DBD_Data::team( $team ); $c = DBD_Marks::accent( $t );
		$link = $side === 'off' && in_array( $key, self::SKILL, true ) && ! empty( $e['id'] ) && DBD_Data::player_record( $team, $e['id'] );
		$line = $side === 'off' ? self::off_line( $team, $e['id'] ?? null ) : self::def_line( $e );
		$tip = '<b>' . esc_html( $e['name'] ?? '' ) . '</b> · ' . esc_html( $e['pos'] ?? '' ) . ( $line ? '<br>' . esc_html( $line ) . ( $side === 'def' ? ' this season' : '' ) : '' );
		$cls = 'dbd-lup ' . $side . ( in_array( $key, self::STAGGER, true ) ? ' lo' : '' ) . ( $key === 'NB' ? ' fade' : '' ) . ( $link ? ' sk' : '' );
		$attr = ' class="' . $cls . '" style="left:' . $x . '%;top:' . $y . '%;--c:' . esc_attr( $c ) . '" data-tip="' . esc_attr( $tip ) . '" aria-label="' . esc_attr( ( $e['name'] ?? '' ) . ', ' . ( $e['pos'] ?? '' ) ) . '" tabindex="0"';
		$inner = '<i>' . esc_html( (string) ( $e['num'] ?? '' ) ) . '</i><span>' . esc_html( self::last( $e['name'] ?? '' ) ) . '</span>';
		return $link ? '<a href="' . esc_url( DBD_Router::url( array( 'player' => $e['id'] ) ) ) . '"' . $attr . '>' . $inner . '</a>' : '<span' . $attr . '>' . $inner . '</span>';
	}

	/** The phone's list (and the screen reader's): every starter with his season */
	private static function lists( $team, $unit, $lu, $keys, $label ) {
		$li = '';
		foreach ( $keys as $k ) {
			$e = $lu[ $unit ][ $k ] ?? null; if ( ! $e ) { continue; }
			$line = $unit === 'off' ? self::off_line( $team, $e['id'] ?? null ) : self::def_line( $e );
			$nm = $unit === 'off' && in_array( $k, self::SKILL, true ) && ! empty( $e['id'] ) && DBD_Data::player_record( $team, $e['id'] ) ? '<a href="' . esc_url( DBD_Router::url( array( 'player' => $e['id'] ) ) ) . '">' . esc_html( $e['name'] ) . '</a>' : esc_html( $e['name'] ?? '' );
			$li .= '<li><b>' . esc_html( preg_replace( '/\d/', '', $k ) ) . '</b><span class="n">' . $nm . '<small>' . ( isset( $e['num'] ) && $e['num'] !== null ? '#' . esc_html( (string) $e['num'] ) : '' ) . ( $line ? ' · ' . esc_html( $line ) : '' ) . '</small></span></li>';
		}
		return '<div><h4>' . esc_html( $label ) . '</h4><ul class="dbd-lulist">' . $li . '</ul></div>';
	}

	/** One offense against the other team's defense (the game page) */
	public static function board( $off, $dfn ) {
		$lo = DBD_Data::lineup( $off ); $ld = DBD_Data::lineup( $dfn );
		if ( ! $lo || ! $ld || empty( $lo['off'] ) || empty( $ld['def'] ) ) { return ''; }
		$M = self::front( $ld ); $spread = function ( $y ) { return round( 50 + ( $y - 50 ) * 1.3, 1 ); };  /* the formation fills the board */
		$ch = '';
		foreach ( self::OFF as $k => $xy ) { if ( isset( $lo['off'][ $k ] ) ) { $ch .= self::chip( $off, $k, $xy[0], $spread( $xy[1] ), $lo['off'][ $k ], 'off' ); } }
		foreach ( $M as $k => $xy ) { if ( isset( $ld['def'][ $k ] ) ) { $ch .= self::chip( $dfn, $k, $xy[0], $spread( $xy[1] ), $ld['def'][ $k ], 'def' ); } }
		$on = DBD_Data::team( $off )['nick']; $dn = DBD_Data::team( $dfn )['nick']; $og = (string) ( $lo['grp']['off'] ?? '' ); $dg = (string) ( $ld['grp']['def'] ?? '' );
		return '<div class="dbd-luwrap"><div class="dbd-lufield" aria-label="' . esc_attr( $on . ' offense against the ' . $dn . ' defense, starters from the depth charts' ) . '"><i class="dbd-lulos"></i>'
			. '<span class="dbd-lutag def">' . esc_html( $dn . ' defense · ' . str_replace( 'Base ', '', $dg ) ) . '</span><span class="dbd-lutag off">' . esc_html( $on . ' offense · ' . $og ) . '</span>' . $ch . '</div>'
			. '<div class="dbd-lulists">' . self::lists( $off, 'off', $lo, self::OFF_ORDER, $on . ' offense · ' . $og ) . self::lists( $dfn, 'def', $ld, array_keys( $M ), $dn . ' defense · ' . $dg ) . '</div></div>';
	}

	/** One unit on its own board (the team players page) */
	public static function unit( $team, $which ) {
		$lu = DBD_Data::lineup( $team ); if ( ! $lu || empty( $lu[ $which ] ) ) { return ''; }
		$ch = '';
		if ( $which === 'off' ) { foreach ( self::OFF as $k => $xy ) { if ( isset( $lu['off'][ $k ] ) ) { $ch .= self::chip( $team, $k, $xy[0], round( 26 + ( $xy[1] - 59 ) * 3.4, 1 ), $lu['off'][ $k ], 'off' ); } } }
		else { foreach ( self::front( $lu ) as $k => $xy ) { if ( isset( $lu['def'][ $k ] ) ) { $ch .= self::chip( $team, $k, $xy[0], round( 76 - ( 45 - $xy[1] ) * 2.4, 1 ), $lu['def'][ $k ], 'def' ); } } }
		$keys = $which === 'off' ? self::OFF_ORDER : array_keys( self::front( $lu ) );
		return '<div class="dbd-luwrap"><div class="dbd-lufield unit"><i class="dbd-lulos ' . $which . '"></i>' . $ch . '</div><div class="dbd-lulists">' . self::lists( $team, $which, $lu, $keys, DBD_Data::team( $team )['nick'] . ( $which === 'off' ? ' offense' : ' defense' ) ) . '</div></div>';
	}

	/** Special teams as a list */
	public static function special( $team ) {
		$lu = DBD_Data::lineup( $team ); if ( ! $lu || empty( $lu['st'] ) ) { return ''; }
		$li = '';
		foreach ( array( 'PK', 'P', 'KR', 'PR', 'LS', 'H' ) as $k ) { $e = $lu['st'][ $k ] ?? null; if ( ! $e ) { continue; }
			$fg = ! empty( $e['fg'] ) && $e['fg'][1] ? ' · ' . (int) $e['fg'][0] . '/' . (int) $e['fg'][1] . ' FG, long ' . (int) $e['fg'][2] : '';
			$li .= '<li><b>' . esc_html( $k ) . '</b><span class="n">' . esc_html( $e['name'] ?? '' ) . '<small>' . esc_html( ( $e['pos'] ?? '' ) . $fg ) . '</small></span></li>'; }
		return '<ul class="dbd-lulist st">' . $li . '</ul>';
	}

	/** Players to watch on one team: quarterback, lead back, two most-targeted receivers, and the defense's two playmakers */
	public static function watch( $team ) {
		$lu = DBD_Data::lineup( $team ); $pl = DBD_Data::team_players( $team ); $t = DBD_Data::team( $team ); if ( ! $t ) { return ''; }
		$S = function ( $p, $k ) { $s = 0; foreach ( $p['weeks'] ?? array() as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s; };
		$rec = array_values( array_filter( $pl, function ( $p ) { return in_array( $p['pos'], array( 'WR', 'TE' ), true ); } ) ); usort( $rec, function ( $a, $b ) use ( $S ) { return $S( $b, 'targets' ) <=> $S( $a, 'targets' ); } );
		$picks = array( array( 'qb', $lu ? DBD_Data::player_record( $team, $lu['off']['QB']['id'] ?? '' ) : null ), array( 'rb', $lu ? DBD_Data::player_record( $team, $lu['off']['RB']['id'] ?? '' ) : null ) );
		foreach ( array_slice( $rec, 0, 2 ) as $p ) { $picks[] = array( 'rec', $p ); }
		$cards = '';
		foreach ( $picks as $pk ) {
			list( $why, $p ) = $pk; if ( ! $p ) { continue; }
			$def = array( 'qb' => array( 'epadb', 'epadb', 'EPA per dropback' ), 'rb' => array( 'epatch', 'repc', 'EPA per touch' ), 'rec' => array( 'epat', 'epat', 'EPA per target' ) )[ $why ];
			$r = $p['trk'][ $def[0] ] ?? null; $v = $p['grades']['m'][ $def[1] ] ?? null;
			$sub = $v === null ? '' : $def[2] . ' ' . ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 ) . ( $r && $r[0] <= 30 ? ' · ' . ( ! empty( $r[1] ) ? 'T-' : '' ) . DBD_Grades::ordinal( (int) $r[0] ) : '' );
			$cards .= '<a class="dbd-pw" href="' . esc_url( DBD_Router::url( array( 'player' => $p['id'] ) ) ) . '">' . DBD_Marks::player( $team, $p['num'] ?? null, 34, $p['photo'] ?? null ) . '<span><b>' . esc_html( $p['name'] ) . '</b><small>' . esc_html( $p['pos'] . ' · ' . self::off_line( $team, $p['id'] ) ) . '</small><em' . ( $r && $r[0] <= 10 ? ' class="top"' : '' ) . '>' . esc_html( $sub ) . '</em></span></a>';
		}
		if ( $lu && ! empty( $lu['def'] ) ) {
			$d = array_values( $lu['def'] );
			usort( $d, function ( $a, $b ) { $sc = function ( $e ) { $s = $e['s'] ?? array( 0, 0, 0, 0, 0 ); return (float) $s[1] * 2 + (float) $s[2] * 3 + (float) $s[3] + (float) $s[4] * 0.5; }; return $sc( $b ) <=> $sc( $a ); } );
			foreach ( array_slice( $d, 0, 2 ) as $e ) {
				$s = $e['s'] ?? array( 0, 0, 0, 0, 0 ); $rk = $e['rk'] ?? array( null, null, null ); $db = (bool) preg_match( '/corner|safety|nickel/i', (string) ( $e['pos'] ?? '' ) );
				$lead = ( $db && $s[2] ) || ( $s[2] * 2 > $s[1] ) ? 1 : ( $s[1] ? 0 : 2 );  /* interceptions for a defensive back, sacks for a rusher, else passes defended */
				$val = array( $s[1], $s[2], $s[3] )[ $lead ]; $words = array( array( 'sack', 'sacks' ), array( 'INT', 'INT' ), array( 'pass defended', 'passes defended' ) )[ $lead ];
				$rr = $rk[ $lead ] ?? null; $r = is_array( $rr ) ? (int) $rr[0] : (int) $rr; $tie = is_array( $rr ) && ! empty( $rr[1] );  /* [rank, tied] since 1.9.0's build */
				$sub = $val ? ( 0 + $val ) . ' ' . ( (float) $val === 1.0 ? $words[0] : $words[1] ) . ( $r && $r <= 30 ? ' · ' . ( $tie ? 'T-' : '' ) . DBD_Grades::ordinal( $r ) . ' in the NFL' : '' ) : '';
				$cards .= '<span class="dbd-pw d">' . DBD_Marks::jersey( $team, $e['num'] ?? null, 34 ) . '<span><b>' . esc_html( $e['name'] ?? '' ) . '</b><small>' . esc_html( ( $e['pos'] ?? '' ) . ' · ' . self::def_line( $e ) ) . '</small><em' . ( $r && $r <= 10 ? ' class="top"' : '' ) . '>' . esc_html( $sub ) . '</em></span></span>';
			}
		}
		return $cards ? '<div class="dbd-pwcol"><h4>' . DBD_Marks::tile( $team, 20 ) . ' ' . esc_html( $t['nick'] ) . '</h4>' . $cards . '</div>' : '';
	}
}
