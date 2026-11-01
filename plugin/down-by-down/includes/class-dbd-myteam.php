<?php
/**
 * My team (SCOPE §36): the players someone follows, laid out as a fantasy lineup. The team lives in the address bar
 * (?myteam=id,id,…) and on the device (localStorage dbd-myteam, written by myteam.js). Every format is rendered;
 * [data-fx] blocks show the one the reader picked (SCOPE §34). Nothing here needs data the site does not already have.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_MyTeam {
	const MAX   = 20;
	const SLOTS = array( array( 'QB', array( 'QB' ) ), array( 'RB', array( 'RB' ) ), array( 'RB', array( 'RB' ) ), array( 'WR', array( 'WR' ) ), array( 'WR', array( 'WR' ) ), array( 'TE', array( 'TE' ) ), array( 'FLEX', array( 'RB', 'WR', 'TE' ) ) );
	const SNAPW = 6;   /* weeks in the snap sparkline */
	const SWING = 2.0; /* last three games this far above or below his average get ▲ or ▼ */

	/** Valid, known, unique player ids from a comma list, capped. Returns index rows in the order given. */
	public static function parse( $list ) {
		$rows = array(); $seen = array();
		foreach ( explode( ',', (string) $list ) as $id ) {
			$id = trim( $id ); if ( ! preg_match( '/^\d{2}-\d{7}$/', $id ) || isset( $seen[ $id ] ) ) { continue; }
			$row = DBD_Data::player( $id ); if ( ! $row ) { continue; }
			$seen[ $id ] = 1; $rows[] = $row; if ( count( $rows ) >= self::MAX ) { break; }
		}
		return $rows;
	}

	public static function fpos( $r ) { $p = $r['fpos'] ?? $r['pos']; return $p === 'FB' ? 'RB' : $p; }
	public static function ppg( $r, $i ) { return isset( $r['fp'][ $i ] ) ? (float) $r['fp'][ $i ] : 0.0; }
	public static function game( $r ) { return DBD_Data::team_game( $r['team'], (int) DBD_Data::current_week() ); }
	public static function is_final( $g ) { return ( $g['status'] ?? '' ) === 'final' || isset( $g['home_score'] ); }
	/** Ruled out on the injury report: Out or Doubtful. A practice listing ("Did Not Participate") is not. */
	public static function ruled_out( $r ) { return (bool) preg_match( '/^(out|doubtful)\b/i', (string) ( $r['st'] ?? '' ) ); }
	/** bye, out, final or up: where he stands this week */
	public static function avail( $r ) {
		$g = self::game( $r ); if ( ! $g ) { return 'bye'; }
		if ( self::ruled_out( $r ) ) { return 'out'; }
		return self::is_final( $g ) ? 'final' : 'up';
	}

	/** The best lineup in one format: each slot takes the highest scorer by points a game who is playing this week. Returns [slots, bench]. */
	public static function lineup( $rows, $i ) {
		$pool = $rows; usort( $pool, function ( $a, $b ) use ( $i ) { return self::ppg( $b, $i ) <=> self::ppg( $a, $i ); } );
		$used = array(); $slots = array();
		foreach ( self::SLOTS as $s ) {
			$pick = null;
			foreach ( $pool as $r ) { if ( isset( $used[ $r['id'] ] ) || ! in_array( self::fpos( $r ), $s[1], true ) || ! in_array( self::avail( $r ), array( 'up', 'final' ), true ) ) { continue; } $pick = $r; break; }
			if ( $pick ) { $used[ $pick['id'] ] = 1; }
			$slots[] = array( $s[0], $pick );
		}
		$bench = array_values( array_filter( $pool, function ( $r ) use ( $used ) { return ! isset( $used[ $r['id'] ] ); } ) );
		return array( $slots, $bench );
	}

	/** The sample team: this season's points-a-game leaders at each slot, then the next best at each group for the bench. */
	public static function sample() {
		$by = array( 'QB' => array(), 'RB' => array(), 'WR' => array(), 'TE' => array() );
		foreach ( DBD_Data::player_index() as $r ) { $p = self::fpos( $r ); if ( isset( $by[ $p ] ) && ! empty( $r['fr'][1] ) ) { $by[ $p ][] = $r; } }
		foreach ( $by as &$l ) { usort( $l, function ( $a, $b ) { return $a['fr'][1] <=> $b['fr'][1]; } ); } unset( $l );
		$take = array( 'QB' => 2, 'RB' => 4, 'WR' => 4, 'TE' => 2 ); $ids = array();
		foreach ( $take as $p => $n ) { foreach ( array_slice( $by[ $p ], 0, $n ) as $r ) { $ids[] = $r['id']; } }
		return $ids;
	}

	/** Weekly points from his record, the zero-point snap weeks (fx0) included, oldest first. */
	private static function weeks( $rec, $i ) {
		$fm = DBD_Fantasy::FMT[ $i ]; $w = array();
		foreach ( (array) ( $rec['weeks'] ?? array() ) as $x ) { $w[ (int) $x['week'] ] = DBD_Fantasy::pts( $x, $fm ); }
		foreach ( (array) ( $rec['fx0'] ?? array() ) as $z ) { $w[ (int) $z ] = 0.0; }
		ksort( $w ); return $w;
	}

	private static function status( $r ) {
		$st = (string) ( $r['st'] ?? '' ); $what = (string) ( $r['what'] ?? '' ); if ( $st === '' ) { return ''; }
		$rest = (bool) preg_match( '/not injury/i', $what );
		if ( $rest && preg_match( '/full/i', $st ) ) { return ''; }  /* a full practice on a rest day says nothing */
		$lbl = preg_match( '/^out/i', $st ) ? 'Out' : ( preg_match( '/did not/i', $st ) ? 'DNP' : ( preg_match( '/limited/i', $st ) ? 'Limited' : ( preg_match( '/full/i', $st ) ? 'Full' : $st ) ) );
		$cls = $rest ? '' : ( self::ruled_out( $r ) ? ' o' : ( preg_match( '/question|limited|did not/i', $st ) ? ' q' : '' ) );
		$why = $rest ? 'rest' : $what;
		return '<span class="dbd-st' . $cls . '">' . esc_html( $lbl . ( $why !== '' ? ' · ' . $why : '' ) ) . '</span>';
	}

	private static function week_cell( $r, $rec, $i ) {
		$g = self::game( $r ); $cw = (int) DBD_Data::current_week(); $teams = DBD_Data::teams(); $pos = self::fpos( $r ); $fm = DBD_Fantasy::FMT[ $i ];
		if ( ! $g ) { return '<span class="wk bye"><b>Bye</b><small>Week ' . $cw . '</small></span>'; }
		$home = $g['home'] === $r['team']; $opp = $home ? $g['away'] : $g['home']; $at = $home ? 'vs' : 'at';
		if ( self::is_final( $g ) ) {
			$w = self::weeks( $rec, $i ); $v = $w[ $cw ] ?? null;
			return '<span class="wk fin"><b>' . $at . ' ' . esc_html( $opp ) . '</b><small>Final · ' . ( $v === null ? 'did not play' : DBD_Fantasy::num( $v ) . ' pts' ) . '</small></span>';
		}
		$k = DBD_Data::kickoff( $g ); $al = isset( DBD_Fantasy::SLOT[ $pos ] ) ? DBD_Fantasy::allowed( $opp, $pos, $fm ) : null;
		$chip = '';
		if ( $al ) {
			$tip = '<b>' . esc_html( $teams[ $opp ]['nick'] ?? $opp ) . '</b> allow ' . DBD_Fantasy::num( $al[0] ) . ' ' . DBD_Fantasy::WORD[ $fm ] . ' points a game to ' . esc_html( $pos ) . 's<br>' . esc_html( DBD_Fantasy::ord( $al[1] ) ) . '-most of ' . (int) $al[2];
			$chip = '<em class="dbd-fxr ' . DBD_Fantasy::tier( $al[1] ) . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0">' . esc_html( DBD_Fantasy::most( $al[1], $al[2] ) ) . '</em>';
		}
		return '<span class="wk"><b>' . DBD_Marks::tile( $opp, 14 ) . $at . ' ' . esc_html( $opp ) . '</b><small><time class="dbd-kick" datetime="' . esc_attr( $k['utc'] ) . '" data-fmt="day">' . esc_html( ( $g['day'] ?? '' ) !== '' ? substr( $g['day'], 0, 3 ) . ' ' . $k['et'] : $k['et'] ) . '</time></small>' . $chip . '</span>';
	}

	private static function snap_cell( $r, $rec ) {
		$cw = (int) DBD_Data::current_week(); $sn = (array) ( $rec['snaps'] ?? array() ); $from = max( 1, $cw - self::SNAPW + 1 );
		$ws = array(); for ( $wk = $from; $wk <= $cw; $wk++ ) { $ws[] = isset( $sn[ (string) $wk ] ) ? (int) $sn[ (string) $wk ] : null; }
		$have = array_filter( $ws, function ( $v ) { return $v !== null; } ); if ( ! $have ) { return '<span class="m">no snaps</span>'; }
		$W = 64; $H = 20; $n = count( $ws ); $x = function ( $j ) use ( $n, $W ) { return round( 2 + $j * ( $W - 4 ) / max( 1, $n - 1 ), 1 ); }; $y = function ( $v ) use ( $H ) { return round( 2 + ( 100 - $v ) / 100 * ( $H - 4 ), 1 ); };
		$pts = array(); $lj = 0; foreach ( $ws as $j => $v ) { if ( $v !== null ) { $pts[] = $x( $j ) . ',' . $y( $v ); $lj = $j; } }
		$tip = '<b>Snap share</b>, weeks ' . $from . '–' . $cw . '<br>' . implode( ' · ', array_map( function ( $v ) { return $v === null ? '–' : $v . '%'; }, $ws ) ) . '<br>season ' . (int) round( $r['snap'] ?? 0 ) . '%';
		return '<span class="snap" data-tip="' . esc_attr( $tip ) . '" tabindex="0"><svg viewBox="0 0 ' . $W . ' ' . $H . '" width="' . $W . '" height="' . $H . '" aria-hidden="true"><polyline points="' . implode( ' ', $pts ) . '" fill="none" stroke="var(--dbd-muted)" stroke-width="1.5"/><circle cx="' . $x( $lj ) . '" cy="' . $y( $ws[ $lj ] ) . '" r="2.5" fill="var(--dbd-team)"/></svg><b>' . (int) $ws[ $lj ] . '%</b></span>';
	}

	private static function fan_cell( $r, $rec, $i ) {
		if ( empty( $r['fp'] ) ) { return '<span class="fan"><b>–</b></span>'; }
		$v = self::ppg( $r, $i ); $rk = $r['fr'][ $i ] ?? null; $w = array_slice( self::weeks( $rec, $i ), -3, 3, true );
		$h = '<span class="fan"><b>' . DBD_Fantasy::num( $v ) . '</b><small>' . esc_html( $rk ? self::fpos( $r ) . (int) $rk : 'not ranked' ) . '</small>';
		if ( $w ) {
			$l3 = array_sum( $w ) / count( $w ); $d = $l3 - $v; $cl = $d >= self::SWING ? ' up' : ( $d <= -self::SWING ? ' dn' : '' ); $ar = $d >= self::SWING ? '▲' : ( $d <= -self::SWING ? '▼' : '•' );
			$tip = 'Last ' . count( $w ) . ' games: ' . implode( ', ', array_map( function ( $wk, $p ) { return 'Wk ' . $wk . ' ' . DBD_Fantasy::num( $p ); }, array_keys( $w ), $w ) );
			$h .= '<span class="l3' . $cl . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0">' . $ar . ' ' . DBD_Fantasy::num( $l3 ) . ' last ' . count( $w ) . '</span>';
		}
		return $h . '</span>';
	}

	/** One lineup row. $lab is the slot (QB, FLEX) or, on the bench, his position. */
	public static function row( $lab, $r, $i, $bench = false ) {
		if ( ! $r ) { return '<div class="mt-row empty"><span class="slot">' . esc_html( $lab ) . '</span><span class="who"><span class="m">Empty: add ' . ( $lab === 'FLEX' ? 'a back, receiver or tight end' : 'a ' . esc_html( $lab ) ) . '</span></span></div>'; }
		$rec = DBD_Data::player_record( $r['team'], $r['id'] ) ?: array(); $t = DBD_Data::team( $r['team'] ); $av = self::avail( $r ); $href = DBD_Router::url( array( 'player' => $r['id'] ) );
		$flag = $av === 'bye' ? '<i class="fl bye">Bye</i>' : ( $av === 'out' ? '<i class="fl out">Out</i>' : '' );
		return '<div class="mt-row' . ( $bench ? ' bench' : '' ) . ( in_array( $av, array( 'bye', 'out' ), true ) ? ' ' . $av : '' ) . '" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">'
			. '<span class="slot">' . esc_html( $lab ) . '</span>'
			. '<span class="who">' . DBD_Marks::player( $r['team'], $r['num'], 36, $r['photo'] ?? null ) . '<span class="nm"><a href="' . esc_url( $href ) . '">' . esc_html( $r['name'] ) . '</a><small>' . DBD_Marks::tile( $r['team'], 14 ) . esc_html( $r['team'] . ' · ' . self::fpos( $r ) ) . '</small>' . self::status( $r ) . '</span>' . $flag . '</span>'
			. '<span class="c-wk">' . self::week_cell( $r, $rec, $i ) . '</span><span class="c-sn">' . self::snap_cell( $r, $rec ) . '</span><span class="c-fp">' . self::fan_cell( $r, $rec, $i ) . '</span>'
			. '<span class="c-x"><a class="ic" href="' . esc_url( $href ) . '" title="' . esc_attr( $r['name'] . ': player page' ) . '" aria-label="' . esc_attr( $r['name'] . ': player page' ) . '">→</a><button class="ic mt-rm" type="button" data-id="' . esc_attr( $r['id'] ) . '" title="Remove from My team" aria-label="' . esc_attr( 'Remove ' . $r['name'] ) . '">×</button></span></div>';
	}

	public static function board( $rows, $i ) {
		list( $slots, $bench ) = self::lineup( $rows, $i ); $cw = (int) DBD_Data::current_week();
		$h = '<div class="mt-hd"><span>Slot</span><span>Player</span><span>Week ' . $cw . '</span><span>Snaps</span><span>Points a game</span><span></span></div>';
		foreach ( $slots as $s ) { $h .= self::row( $s[0], $s[1], $i ); }
		if ( $bench ) { $h .= '<div class="mt-bh"><span>Bench</span><small>' . count( $bench ) . ' player' . ( count( $bench ) === 1 ? '' : 's' ) . '</small></div>'; foreach ( $bench as $r ) { $h .= self::row( self::fpos( $r ), $r, $i, true ); } }
		return $h;
	}

	/** The week's read and four numbers for the best lineup in one format. */
	public static function summary( $rows, $i ) {
		list( $slots, ) = self::lineup( $rows, $i ); $fm = DBD_Fantasy::FMT[ $i ]; $W = DBD_Fantasy::WORD[ $fm ];
		$st = array_values( array_filter( array_map( function ( $s ) { return $s[1]; }, $slots ) ) ); $n = count( $st );
		$tot = array_sum( array_map( function ( $r ) use ( $i ) { return self::ppg( $r, $i ); }, $st ) );
		$byes = array_values( array_filter( $rows, function ( $r ) { return self::avail( $r ) === 'bye'; } ) );
		$outs = array_values( array_filter( $rows, function ( $r ) { return self::avail( $r ) === 'out'; } ) );
		$done = array_values( array_filter( $st, function ( $r ) { return self::avail( $r ) === 'final'; } ) );
		$soft = 0; foreach ( $st as $r ) { $g = self::game( $r ); if ( ! $g || self::is_final( $g ) ) { continue; } $opp = $g['home'] === $r['team'] ? $g['away'] : $g['home']; $al = DBD_Fantasy::allowed( $opp, self::fpos( $r ), $fm ); if ( $al && $al[1] <= DBD_Fantasy::SOFT ) { $soft++; } }
		$names = function ( $l ) { $nm = array_map( function ( $r ) { return esc_html( $r['name'] ); }, $l ); return count( $nm ) > 1 ? implode( ', ', array_slice( $nm, 0, -1 ) ) . ' and ' . end( $nm ) : ( $nm[0] ?? '' ); };
		$last = function ( $l ) { return $l ? implode( ', ', array_map( function ( $r ) { $p = explode( ' ', preg_replace( '/\s+(Jr\.?|Sr\.?|II|III|IV|V)$/', '', $r['name'] ) ); return end( $p ); }, $l ) ) : 'none'; };
		if ( ! $n ) { $say = 'Nobody on your team is playing this week yet: add players, or check back after the byes.'; }
		else {
			$say = 'Your best lineup this week starts ' . $n . ' player' . ( $n === 1 ? '' : 's' ) . ' who average <b>' . DBD_Fantasy::num( $tot ) . '</b> ' . $W . ' points a week together' . ( $n < count( self::SLOTS ) ? ', with ' . ( count( self::SLOTS ) - $n ) . ' slot' . ( count( self::SLOTS ) - $n === 1 ? '' : 's' ) . ' empty' : '' ) . '. ';
			if ( $byes || $outs ) { $say .= trim( ( $byes ? $names( $byes ) . ( count( $byes ) === 1 ? ' is' : ' are' ) . ' on bye' : '' ) . ( $byes && $outs ? ' and ' : '' ) . ( $outs ? $names( $outs ) . ( count( $outs ) === 1 ? ' is' : ' are' ) . ' ruled out' : '' ) ) . ', so the lineup works around ' . ( count( $byes ) + count( $outs ) === 1 ? 'him' : 'them' ) . '. '; }
			if ( $done ) { $say .= $names( $done ) . ( count( $done ) === 1 ? ' has' : ' have' ) . ' already played. '; }
			$say .= $soft ? $soft . ' of your starters face a defense in the top eight for points allowed at their position.' : 'None of your starters face a defense in the top eight for points allowed at their position.';
		}
		$chips = array( array( 'Starters a week', DBD_Fantasy::num( $tot ), $W . ' points, by season average' ), array( 'On bye', (string) count( $byes ), $last( $byes ) ), array( 'Ruled out', (string) count( $outs ), $last( $outs ) ), array( 'Soft matchups', $soft . ' of ' . $n, 'top-eight defenses for points allowed' ) );
		$ch = ''; foreach ( $chips as $c ) { $ch .= '<div class="c"><small>' . esc_html( $c[0] ) . '</small><b>' . esc_html( $c[1] ) . '</b><span>' . esc_html( $c[2] ) . '</span></div>'; }
		return '<p class="dbd-wp-say">' . $say . '</p><div class="dbd-wpchips">' . $ch . '</div>';
	}

	/** Compare links: the top three at each group, when there are two or more. */
	public static function compare_links( $rows ) {
		$grp = array( 'QB' => array( 'Compare your quarterbacks', array( 'QB' ) ), 'RB' => array( 'Compare your backs', array( 'RB' ) ), 'rec' => array( 'Compare your receivers', array( 'WR', 'TE' ) ) );
		$h = ''; $sorted = $rows; usort( $sorted, function ( $a, $b ) { return self::ppg( $b, 1 ) <=> self::ppg( $a, 1 ); } );
		foreach ( $grp as $g ) {
			$ids = array(); foreach ( $sorted as $r ) { if ( in_array( self::fpos( $r ), $g[1], true ) ) { $ids[] = $r['id']; } if ( count( $ids ) >= 3 ) { break; } }
			if ( count( $ids ) > 1 ) { $h .= '<a class="dbd-pill" href="' . esc_url( DBD_Router::url( array( 'compare' => implode( ',', $ids ) ) ) ) . '">' . esc_html( $g[0] ) . '</a>'; }
		}
		return $h;
	}
}
