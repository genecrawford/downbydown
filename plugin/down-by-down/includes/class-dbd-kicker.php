<?php
/**
 * Kickers (SCOPE §47): every kicker's season from players/kickers.json. The kicker page (views/kicker.php) reads it
 * through this class, as do the Kickers tab on Leaders, kicker compare, the share card and the play-text links.
 * Each try is measured against the league curve the build fitted: the chance an average kicker makes it from that distance.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Kicker {
	/* key => label, sub-label, format, lower is better. Scorecard order. */
	const MET = array(
		'fgpct'  => array( 'Field goal %', 'made of tried', 'pct', false ),
		'oe'     => array( 'Over expected', 'makes vs the league curve', 'sgn1', false ),
		'oepa'   => array( 'Over expected / try', 'per attempt', 'sgn2', false ),
		'f50'    => array( 'From 50+', 'made', 'int', false ),
		'avgd'   => array( 'Avg try', 'yards', 'f1', false ),
		'xppct'  => array( 'Extra point %', 'made of tried', 'pct', false ),
		'kstart' => array( 'Kickoffs', 'their start, lower is better', 'f1', true ),
		'ppg'    => array( 'Points a game', 'FG and extra points', 'f1', false ),
	);
	/* the Kickers tab on Leaders: key => title, sub-label, format, lower is better */
	const BOARDS = array(
		'oe'     => array( 'Over expected', 'makes against the league curve', 'sgn1', false ),
		'fgpct'  => array( 'Field goal %', 'made of tried', 'pct', false ),
		'f50'    => array( 'From 50+', 'field goals made from 50 yards or more', 'int', false ),
		'lng'    => array( 'Longest', 'his longest made field goal', 'int', false ),
		'pts'    => array( 'Points', 'field goals and extra points', 'int', false ),
		'kstart' => array( 'Kickoffs', 'the other team\'s average start, lower is better', 'own', true ),
	);

	public static function file() { return DBD_Data::get( 'players/kickers.json' ) ?: array(); }
	public static function get( $id ) { $f = self::file(); return $f['kickers'][ $id ] ?? null; }
	public static function is( $row ) { return is_array( $row ) && ( $row['pos'] ?? '' ) === 'K'; }
	public static function n() { return (int) ( self::file()['n'] ?? 0 ); }

	/** A metric's value for a kicker, as the build ranks it. */
	public static function val( $k, $key ) {
		$fga = (int) ( $k['fga'] ?? 0 ); $g = (int) ( $k['g'] ?? 0 ); $pat = (array) ( $k['pat'] ?? array( 0, 0 ) ); $by = (array) ( $k['by'] ?? array() );
		switch ( $key ) {
			case 'fgpct': return $fga ? $k['fgm'] / $fga : null;
			case 'oe': return isset( $k['oe'] ) ? (float) $k['oe'] : null;
			case 'oepa': return $fga && isset( $k['oe'] ) ? $k['oe'] / $fga : null;
			case 'f50': return (int) ( ( $by[3][0] ?? 0 ) + ( $by[4][0] ?? 0 ) );
			case 'f50a': return (int) ( ( $by[3][1] ?? 0 ) + ( $by[4][1] ?? 0 ) );
			case 'avgd': return isset( $k['avgd'] ) ? (float) $k['avgd'] : null;
			case 'lng': return (int) ( $k['lng'] ?? 0 );
			case 'xppct': return ! empty( $pat[1] ) ? $pat[0] / $pat[1] : null;
			case 'kstart': return isset( $k['ko'][2] ) ? (float) $k['ko'][2] : null;
			case 'pts': return (int) ( $k['pts'] ?? 0 );
			case 'ppg': return $g ? $k['pts'] / $g : null;
			case 'fxpg': return $g ? $k['fx'] / $g : null;
		}
		return null;
	}

	public static function fmt( $kind, $v ) {
		if ( $v === null ) { return '–'; }
		switch ( $kind ) {
			case 'pct': return round( $v * 100 ) . '%';
			case 'sgn1': return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 1 );
			case 'sgn2': return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 );
			case 'f1': return number_format( (float) $v, 1 );
			case 'own': return 'own ' . number_format( (float) $v, 1 );
		}
		return (string) (int) $v;
	}

	/** His percentile among qualifying kickers, from the pool the build wrote (ties count half). Null when he doesn't qualify. */
	public static function pctile( $k, $key ) {
		if ( empty( $k['q'] ) ) { return null; }
		$vals = (array) ( self::file()['dist'][ $key ] ?? array() ); $v = self::val( $k, $key ); if ( ! $vals || $v === null ) { return null; }
		$low = ! empty( self::MET[ $key ][3] ) || $key === 'kstart'; $below = 0; $eq = 0;
		foreach ( $vals as $x ) { $x = (float) $x; if ( abs( $x - $v ) < 1e-9 ) { $eq++; } elseif ( $low ? $x > $v : $x < $v ) { $below++; } }
		return (int) round( 100 * ( $below + $eq / 2 ) / count( $vals ) );
	}
	public static function median( $key ) { $vals = (array) ( self::file()['dist'][ $key ] ?? array() ); return $vals ? (float) $vals[ (int) floor( count( $vals ) / 2 ) ] : null; }

	/** "4th" or "T-10th" among qualifying kickers, or '' for a kicker who doesn't qualify. */
	public static function rank( $k, $key ) {
		if ( empty( $k['trk'][ $key ] ) ) { return ''; }
		return ( ! empty( $k['trk'][ $key ][1] ) ? 'T-' : '' ) . DBD_Grades::ordinal( (int) $k['trk'][ $key ][0] );
	}
	public static function rank_n( $k, $key ) { return (int) ( $k['trk'][ $key ][0] ?? 0 ); }

	/** The league curve: an average kicker's chance from $d yards. */
	public static function p_make( $d ) {
		$c = (array) ( self::file()['curve'] ?? array( 2.1024, -0.9952 ) );
		return 1 / ( 1 + exp( -( (float) $c[0] + (float) $c[1] * ( $d - 40 ) / 10 ) ) );
	}

	/** Kicker ids by team and short name ("W.Reichard"), for linking play text. */
	public static function by_short() {
		static $m = null; if ( $m !== null ) { return $m; }
		$m = array(); foreach ( (array) ( self::file()['kickers'] ?? array() ) as $id => $k ) { $m[ ( $k['team'] ?? '' ) . '|' . ( $k['short'] ?? '' ) ] = $id; $m[ '|' . ( $k['short'] ?? '' ) ] = $id; }
		return $m;
	}

	/** A play's text, escaped, with the kicker's name linked when it is a field goal or extra point ("J.Bates 46 yard field goal…"). */
	public static function link_desc( $desc, $team = '' ) {
		$desc = (string) $desc;
		if ( ! preg_match( '/^([A-Z][A-Za-z]*\.[A-Za-z\'\-\.]+)(?= (?:\d+ yard field goal|extra point))/', $desc, $m ) ) { return esc_html( $desc ); }
		$S = self::by_short(); $id = $S[ $team . '|' . $m[1] ] ?? ( $S[ '|' . $m[1] ] ?? null );
		if ( ! $id ) { return esc_html( $desc ); }
		return '<a href="' . esc_url( DBD_Router::url( array( 'player' => $id ) ) ) . '">' . esc_html( $m[1] ) . '</a>' . esc_html( substr( $desc, strlen( $m[1] ) ) );
	}

	/** A box score's kicking line from the build's every-kicker list (2.8.0 data), linked, with misses. */
	public static function box_line( $kk ) {
		$out = array();
		foreach ( (array) $kk as $x ) {
			list( $id, $nm, $fgm, $fga, $miss, $pm, $pa ) = array_pad( (array) $x, 7, 0 );
			$ms = array(); foreach ( (array) $miss as $mm ) { $ms[] = (int) $mm[0] . ' ' . ( ( $mm[1] ?? '' ) === 'b' ? 'blocked' : 'no good' ); }
			$name = DBD_Data::player( (string) $id ) ? '<a href="' . esc_url( DBD_Router::url( array( 'player' => $id ) ) ) . '">' . esc_html( (string) $nm ) . '</a>' : esc_html( (string) $nm );
			$bits = array();
			if ( (int) $fga ) { $bits[] = (int) $fgm . '/' . (int) $fga . ' FG' . ( $ms ? ' (' . esc_html( implode( ', ', $ms ) ) . ')' : '' ); }
			if ( (int) $pa ) { $bits[] = (int) $pm . '/' . (int) $pa . ' XP'; }
			$pts = 3 * (int) $fgm + (int) $pm; $bits[] = $pts . ' pt' . ( $pts === 1 ? '' : 's' );
			$out[] = $name . ' ' . implode( ', ', $bits );
		}
		return implode( ' · ', $out );
	}

	/** Kickers to compare him with: the closest in how far they are asked to kick and how often. */
	public static function suggest( $k, $n = 3 ) {
		$all = (array) ( self::file()['kickers'] ?? array() ); $c = array();
		foreach ( $all as $id => $o ) {
			if ( $id === ( $k['id'] ?? '' ) || empty( $o['q'] ) ) { continue; }
			$c[] = array( abs( (float) ( $o['avgd'] ?? 0 ) - (float) ( $k['avgd'] ?? 0 ) ) / 3 + abs( (int) $o['fga'] - (int) $k['fga'] ) / 6, $o );
		}
		usort( $c, function ( $a, $b ) { return $a[0] <=> $b[0]; } );
		return array_map( function ( $x ) { return $x[1]; }, array_slice( $c, 0, $n ) );
	}

	/* ------------------------------------------------------------------ the field charts */

	/** Every try at the spot it was held (distance − 10 yards from the goal line), one row per week; extra points at the end. Sideways for wide screens. */
	public static function field_h( $k, $weeks ) {
		$T = DBD_Data::team( $k['team'] ) ?: array(); $c1 = preg_match( '/^#[0-9a-fA-F]{6}$/', $T['c1'] ?? '' ) ? $T['c1'] : '#4F2683'; $nick = strtoupper( (string) ( $T['nick'] ?? $k['team'] ) );
		$U = 12; $XE = 960; $XG = $XE - 10 * $U; $xl = function ( $L ) use ( $XG, $U ) { return $XG - $L * $U; };
		$RH = 40; $top = 26; $H = $top + $RH * count( $weeks ) + 30; $G = self::games_by_week( $k );
		$s = '<svg class="kk-field kk-h" viewBox="0 0 1056 ' . $H . '" role="img" aria-label="' . esc_attr( 'Every field goal try ' . $k['name'] . ' has made or missed this season, by week and distance' ) . '">';
		$s .= '<rect x="0" y="0" width="' . $XE . '" height="' . $H . '" fill="#2c4a35"/><rect x="' . $XG . '" y="0" width="' . ( 10 * $U ) . '" height="' . $H . '" fill="' . $c1 . '" opacity=".9"/>';
		$cx0 = ( $XG + $XE ) / 2; $cy0 = $H / 2;
		$s .= '<text x="' . $cx0 . '" y="' . $cy0 . '" fill="#fff" opacity=".85" font-family="Tourney,sans-serif" font-weight="800" font-size="30" text-anchor="middle" dominant-baseline="middle" transform="rotate(90 ' . $cx0 . ' ' . $cy0 . ')">' . esc_html( $nick ) . '</text>';
		for ( $L = 0; $L <= 65; $L += 5 ) {
			$x = $xl( $L ); $s .= '<line x1="' . $x . '" x2="' . $x . '" y1="0" y2="' . $H . '" stroke="#fff" stroke-opacity="' . ( $L % 10 === 0 ? '.55' : '.18' ) . '" stroke-width="' . ( $L === 0 ? 2 : 1 ) . '"/>';
			if ( $L % 10 === 0 && $L > 0 && $L <= 60 ) { $s .= '<text x="' . $x . '" y="' . ( $H - 10 ) . '" fill="#fff" fill-opacity=".55" font-family="Tourney,sans-serif" font-weight="800" font-size="18" text-anchor="middle">' . ( $L <= 50 ? $L : 100 - $L ) . '</text>'; }
		}
		$s .= '<line x1="' . $XE . '" x2="' . $XE . '" y1="' . ( $top - 8 ) . '" y2="' . ( $H - 30 ) . '" stroke="#ffd23f" stroke-width="5"/>';
		foreach ( $weeks as $i => $w ) {
			$y = $top + $RH * $i + $RH / 2; $g = $G[ $w ] ?? null;
			$s .= '<text x="14" y="' . ( $y + 4 ) . '" fill="#fff" fill-opacity=".8" font-family="IBM Plex Mono,monospace" font-size="11">Wk ' . (int) $w . '</text>';
			if ( ! $g ) { $s .= '<text x="70" y="' . ( $y + 4 ) . '" fill="#fff" fill-opacity=".5" font-family="IBM Plex Mono,monospace" font-size="11">' . ( self::bye( $k['team'], $w ) ? 'bye' : '–' ) . '</text>'; continue; }
			$s .= '<text x="70" y="' . ( $y + 4 ) . '" fill="#fff" fill-opacity=".8" font-family="IBM Plex Mono,monospace" font-size="11">' . ( $g[3] ? 'vs ' : 'at ' ) . esc_html( $g[2] ) . '</text>';
			$s .= '<line x1="' . $xl( 60 ) . '" x2="' . $XE . '" y1="' . ( $y + $RH / 2 ) . '" y2="' . ( $y + $RH / 2 ) . '" stroke="#fff" stroke-opacity=".08"/>';
			$ks = self::kicks_in( $k, $w ); $pos = array_map( function ( $x ) use ( $xl ) { return $xl( (int) $x[2] - 10 ); }, $ks ); $off = self::stack( $pos, 24, array( 1 => array( 0 ), 2 => array( -11, 11 ), 3 => array( -13, 0, 13 ) ) );
			foreach ( $ks as $n => $x ) { $s .= self::dot( $k, $g, $x, $pos[ $n ], $y + $off[ $n ], 12, 11 ); }
			$s .= '<text x="1048" y="' . ( $y + 4 ) . '" fill="#121417" font-family="IBM Plex Mono,monospace" font-size="11" text-anchor="end">XP ' . (int) $g[7] . '/' . (int) $g[8] . '</text>';
		}
		return $s . '</svg>';
	}

	/** The same kicks on an upright field for phones: the uprights at the top, one column per week. */
	public static function field_v( $k, $weeks ) {
		$T = DBD_Data::team( $k['team'] ) ?: array(); $c1 = preg_match( '/^#[0-9a-fA-F]{6}$/', $T['c1'] ?? '' ) ? $T['c1'] : '#4F2683'; $nick = strtoupper( (string) ( $T['nick'] ?? $k['team'] ) );
		$W = 390; $YU = 34; $V = 8; $YG = $YU + 10 * $V; $yl = function ( $L ) use ( $YG, $V ) { return $YG + $L * $V; };
		$H = $yl( 57 ) + 16; $nw = max( 1, count( $weeks ) ); $cw = ( $W - 46 ) / $nw; $G = self::games_by_week( $k );
		$s = '<svg class="kk-field kk-v" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="' . esc_attr( 'Every field goal try ' . $k['name'] . ' has made or missed this season, by week and distance' ) . '">';
		$s .= '<rect x="0" y="' . $YU . '" width="' . $W . '" height="' . ( $H - $YU ) . '" fill="#2c4a35"/><rect x="0" y="' . $YU . '" width="' . $W . '" height="' . ( 10 * $V ) . '" fill="' . $c1 . '" opacity=".9"/>';
		$s .= '<text x="' . ( $W / 2 ) . '" y="' . ( $YU + 5 * $V + 7 ) . '" fill="#fff" opacity=".85" font-family="Tourney,sans-serif" font-weight="800" font-size="22" text-anchor="middle">' . esc_html( $nick ) . '</text>';
		$s .= '<line x1="0" x2="' . $W . '" y1="' . $YU . '" y2="' . $YU . '" stroke="#ffd23f" stroke-width="4"/>';
		for ( $L = 0; $L <= 55; $L += 5 ) {
			$s .= '<line x1="0" x2="' . $W . '" y1="' . $yl( $L ) . '" y2="' . $yl( $L ) . '" stroke="#fff" stroke-opacity="' . ( $L % 10 === 0 ? '.55' : '.18' ) . '" stroke-width="' . ( $L === 0 ? 2 : 1 ) . '"/>';
			if ( $L % 10 === 0 && $L ) { $s .= '<text x="8" y="' . ( $yl( $L ) + 4 ) . '" fill="#fff" fill-opacity=".55" font-family="Tourney,sans-serif" font-weight="800" font-size="13">' . $L . '</text>'; }
		}
		foreach ( $weeks as $i => $w ) {
			$x = 46 + $cw * $i + $cw / 2; $g = $G[ $w ] ?? null;
			$s .= '<text x="' . $x . '" y="12" font-family="IBM Plex Mono,monospace" font-size="10" fill="#121417" text-anchor="middle">Wk ' . (int) $w . '</text>';
			$s .= '<text x="' . $x . '" y="26" font-family="IBM Plex Mono,monospace" font-size="9" fill="#5d616a" text-anchor="middle">' . ( $g ? ( $g[3] ? 'vs ' : '@' ) . esc_html( $g[2] ) : ( self::bye( $k['team'], $w ) ? 'bye' : '–' ) ) . '</text>';
			if ( ! $g ) { continue; }
			$ks = self::kicks_in( $k, $w ); $pos = array_map( function ( $x ) use ( $yl ) { return $yl( (int) $x[2] - 10 ); }, $ks ); $off = self::stack( $pos, 22, array( 1 => array( 0 ), 2 => array( -10, 10 ), 3 => array( -11, 0, 11 ) ) );
			foreach ( $ks as $n => $kx ) { $s .= self::dot( $k, $g, $kx, $x + $off[ $n ], $pos[ $n ], 10, 9.5, 9.5 ); }
		}
		return $s . '</svg>';
	}

	/** One kick: a white dot (good), a red ring (no good) or a red dot (blocked), numbered by distance, with the details on hover. */
	private static function dot( $k, $g, $x, $cx, $cy, $r, $rm, $fs = 11 ) {
		$res = $x[3]; $w = (int) $x[0]; $d = (int) $x[2]; $sd = (int) $x[6];
		$sit = $sd === 0 ? 'tied' : ( $sd > 0 ? 'up ' . $sd : 'down ' . -$sd );
		$opp = DBD_Data::team( $g[2] )['nick'] ?? $g[2]; $q = (int) $x[4] === 5 ? 'OT' : 'Q' . (int) $x[4];
		$tip = '<b>' . $d . ' yards · ' . ( $res === 'm' ? 'Good' : ( $res === 'b' ? 'Blocked' : 'No good' ) ) . '</b><br>Week ' . $w . ' ' . ( $g[3] ? 'vs ' : 'at ' ) . esc_html( $opp ) . ' · ' . $q . ' ' . esc_html( (string) $x[5] ) . ' · ' . $sit
			. '<br><span class="m">An average kicker makes it ' . round( 100 * self::p_make( $d ) ) . '% of the time' . ( isset( $x[8] ) && $x[8] !== null ? ' · win chance ' . ( $x[8] >= 0 ? '+' : '' ) . round( $x[8] * 100 ) : '' ) . '</span>';
		$cx = round( $cx, 1 ); $cy = round( $cy, 1 );
		$c = $res === 'm' ? '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="#fff"/>' : '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $rm . '" fill="' . ( $res === 'b' ? '#cb2026' : 'none' ) . '" stroke="#ff5a5f" stroke-width="2.5"/>';
		return '<g class="kk-k" tabindex="0" data-tip="' . esc_attr( $tip ) . '">' . $c . '<text x="' . $cx . '" y="' . ( $cy + 4 ) . '" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="' . $fs . '" fill="' . ( $res === 'm' ? '#121417' : '#fff' ) . '" text-anchor="middle">' . $d . '</text></g>';
	}

	/** Offsets that stack kicks closer than $gap apart instead of letting them overlap. */
	private static function stack( $pos, $gap, $sets ) {
		$groups = array(); foreach ( $pos as $i => $p ) { $n = count( $groups ); if ( $n && abs( $pos[ end( $groups[ $n - 1 ] ) ] - $p ) < $gap ) { $groups[ $n - 1 ][] = $i; } else { $groups[] = array( $i ); } }
		$off = array(); foreach ( $groups as $gr ) { $set = $sets[ count( $gr ) ] ?? array_fill( 0, count( $gr ), 0 ); foreach ( $gr as $j => $i ) { $off[ $i ] = $set[ $j ] ?? 0; } }
		return $off;
	}

	public static function games_by_week( $k ) { $o = array(); foreach ( (array) ( $k['games'] ?? array() ) as $g ) { $o[ (int) $g[0] ] = $g; } return $o; }
	public static function kicks_in( $k, $w ) { $o = array_values( array_filter( (array) ( $k['fg'] ?? array() ), function ( $x ) use ( $w ) { return (int) $x[0] === (int) $w; } ) ); usort( $o, function ( $a, $b ) { return (int) $b[2] <=> (int) $a[2]; } ); return $o; }
	private static function bye( $team, $w ) { return ! DBD_Data::team_game( $team, $w ); }

	/* ------------------------------------------------------------------ Leaders and compare */

	/** The qualifying kickers, best first on a board's metric. */
	public static function board_rows( $key ) {
		$B = self::BOARDS[ $key ] ?? ( isset( self::MET[ $key ] ) ? self::MET[ $key ] : null ); if ( ! $B ) { return array(); }
		$rows = array();
		foreach ( (array) ( self::file()['kickers'] ?? array() ) as $id => $k ) { if ( empty( $k['q'] ) ) { continue; } $v = self::val( $k, $key ); if ( $v !== null ) { $rows[] = array( $id, $v, (int) $k['fga'] ); } }
		usort( $rows, function ( $a, $b ) use ( $B ) { return $B[3] ? $a[1] <=> $b[1] : ( $b[1] <=> $a[1] ?: $a[2] <=> $b[2] ); } );
		return $rows;
	}

	public static function who( $id, $size = 24 ) {
		$k = self::get( $id ); if ( ! $k ) { return ''; }
		return '<span class="who">' . DBD_Marks::player( $k['team'], $k['num'] ?? '', $size, $k['photo'] ?? null ) . '<span class="nm"><a href="' . esc_url( DBD_Router::url( array( 'player' => $id ) ) ) . '">' . esc_html( $k['name'] ) . '</a><small>' . esc_html( $k['team'] . ' · K' ) . '</small></span></span>';
	}

	/** The Kickers tab: six boards, the top ten each. */
	public static function leaders_tab() {
		if ( ! self::file() ) { return '<div class="dbd-empty"><h2>No kicker boards yet</h2><p>They fill in with the next data build.</p></div>'; }
		$cards = '';
		foreach ( self::BOARDS as $key => $B ) {
			$rows = self::board_rows( $key ); $rk = DBD_Leaders::ranks( $rows ); $li = '';
			foreach ( array_slice( $rows, 0, DBD_Leaders::TOP ) as $i => $r ) { $li .= '<li><span class="rk">' . esc_html( DBD_Leaders::rk( $rk[ $i ] ) ) . '</span>' . self::who( $r[0] ) . '<b class="v">' . esc_html( self::fmt( $B[2], $r[1] ) ) . '</b></li>'; }
			$cards .= '<section class="dbd-panel ld-card"><div class="ld-h"><b>' . esc_html( $B[0] ) . '</b><small>' . esc_html( $B[1] ) . '</small></div>' . ( $li ? '<ol class="ld-list">' . $li . '</ol>' : '<p class="m">Nobody qualifies yet.</p>' ) . '<a class="ld-all" href="' . esc_url( DBD_Router::url( array( 'view' => 'leaders', 'b' => 'k.' . $key ) ) ) . '">' . esc_html( 'Full board · ' . count( $rows ) . ' qualified' ) . ' →</a></section>';
		}
		return '<section class="dbd-band ld-band"><h2 class="dbd-bandh">Kickers<span>' . esc_html( 'among the ' . self::n() . ' kickers with a field goal try for every game their team has played' ) . '</span></h2><div class="ld-grid">' . $cards . '</div></section>';
	}

	/** One full kicker board for Leaders. */
	public static function board( $key ) {
		$B = self::BOARDS[ $key ] ?? null; if ( ! $B ) { return null; }
		$rows = self::board_rows( $key ); $rk = DBD_Leaders::ranks( $rows ); $tb = '';
		foreach ( $rows as $i => $r ) { $tb .= '<tr><td class="rk">' . esc_html( DBD_Leaders::rk( $rk[ $i ] ) ) . '</td><td>' . self::who( $r[0], 28 ) . '</td><td data-v="' . esc_attr( (string) $r[1] ) . '">' . esc_html( self::fmt( $B[2], $r[1] ) ) . '</td><td>' . (int) $r[2] . '</td></tr>'; }
		return array( 'title' => $B[0] . ' · kickers', 'sub' => $B[1], 'group' => 'k', 'filters' => '', 'count' => count( $rows ),
			'body' => '<div class="dbd-tw"><table class="dbd-stat ld-full"><thead><tr><th>Rank</th><th>Kicker</th><th>' . esc_html( $B[0] ) . '</th><th>Tries</th></tr></thead><tbody>' . ( $tb ?: '<tr><td colspan="4" class="dbd-note">Nobody qualifies yet.</td></tr>' ) . '</tbody></table></div>' );
	}

	/** Kicker against kicker: the Scorecard side by side, the best on each line marked, then each one's ranges. */
	public static function compare( $rows ) {
		$K = array(); foreach ( $rows as $r ) { $k = self::get( $r['id'] ); if ( $k ) { $K[] = $k; } }
		if ( count( $K ) < 2 ) { return ''; }
		$head = '<tr><th>Stat</th>'; foreach ( $K as $k ) { $head .= '<th>' . self::who( $k['id'] ) . '</th>'; } $head .= '</tr>';
		$lines = array( array( 'Field goals', function ( $k ) { return array( $k['fga'] ? $k['fgm'] / $k['fga'] : null, $k['fgm'] . '/' . $k['fga'] ); } ) );
		foreach ( self::MET as $key => $M ) { $lines[] = array( $M[0], function ( $k ) use ( $key, $M ) { $v = self::val( $k, $key ); return array( $v === null ? null : ( $M[3] ? -$v : $v ), self::fmt( $M[2], $v ) ); } ); }
		$lines[] = array( 'Longest', function ( $k ) { return array( (int) $k['lng'], (string) (int) $k['lng'] ); } );
		$lines[] = array( 'Fantasy a game', function ( $k ) { $v = self::val( $k, 'fxpg' ); return array( $v, $v === null ? '–' : number_format( $v, 1 ) ); } );
		$tb = '';
		foreach ( $lines as $ln ) {
			$cells = array_map( $ln[1], $K ); $best = null; foreach ( $cells as $c ) { if ( $c[0] !== null && ( $best === null || $c[0] > $best ) ) { $best = $c[0]; } }
			$ties = 0; foreach ( $cells as $c ) { if ( $best !== null && $c[0] === $best ) { $ties++; } }
			$tb .= '<tr><td class="n">' . esc_html( $ln[0] ) . '</td>'; foreach ( $cells as $c ) { $tb .= '<td' . ( $best !== null && $c[0] === $best && $ties === 1 ? ' class="b"' : '' ) . '>' . esc_html( $c[1] ) . '</td>'; } $tb .= '</tr>';
		}
		$rg = (array) ( self::file()['ranges'] ?? array() ); $tb2 = '';
		foreach ( $rg as $i => $nm ) { $tb2 .= '<tr><td class="n">' . esc_html( $nm ) . '</td>'; foreach ( $K as $k ) { $b = $k['by'][ $i ] ?? array( 0, 0, 0 ); $tb2 .= '<td>' . ( $b[1] ? (int) $b[0] . '/' . (int) $b[1] . ' <small>' . ( ( $b[0] - $b[2] ) >= 0 ? '+' : '' ) . number_format( $b[0] - $b[2], 1 ) . '</small>' : '–' ) . '</td>'; } $tb2 .= '</tr>'; }
		return '<section class="dbd-panel kk-cmp"><div class="dbd-ph"><span class="dbd-lab"><b>Side by side</b> · the kicker Scorecard</span></div><div class="dbd-tw"><table class="dbd-stat kk-cmpt"><thead>' . $head . '</thead><tbody>' . $tb . '</tbody></table></div></section>'
			. '<section class="dbd-panel kk-cmp"><div class="dbd-ph"><span class="dbd-lab"><b>By distance</b> · made of tried, and makes over expected</span></div><div class="dbd-tw"><table class="dbd-stat kk-cmpt"><thead>' . str_replace( '<th>Stat</th>', '<th>Yards</th>', $head ) . '</thead><tbody>' . $tb2 . '</tbody></table></div></section>';
	}

	/* ------------------------------------------------------------------ the share card */

	/** The share card's spec, in the player card's shape: four tiles and a percentile bar for each Scorecard stat. */
	public static function card_spec( $k, $team ) {
		$q = ! empty( $k['q'] ); $parts = explode( ' ', (string) $k['name'], 2 ); $rows = array();
		foreach ( array( 'fgpct', 'oe', 'f50', 'avgd', 'xppct', 'kstart', 'ppg' ) as $key ) { $rows[] = array( 'lab' => self::MET[ $key ][0], 'pc' => $q ? self::pctile( $k, $key ) : null ); }
		$n = self::n();
		return array(
			'season' => (int) DBD_Data::season(), 'through' => (int) DBD_Data::through_week(),
			'team' => array( 'abbr' => $k['team'], 'nick' => $team['nick'] ?? $k['team'], 'c1' => $team['c1'] ?? '#4F2683', 'c2' => $team['c2'] ?? '#FFC62F', 'accent' => DBD_Marks::accent( $team ?: null, DBD_Card::BG ) ),
			'first' => $parts[0], 'last' => $parts[1] ?? '', 'num' => (string) ( $k['num'] ?? '' ),
			'kicker' => 'K · #' . ( $k['num'] ?? '' ) . ' · ' . ( $team['nick'] ?? '' ) . ' · ' . DBD_Grades::ordinal( (int) ( $k['exp'] ?? 0 ) + 1 ) . ' season',
			'tiles' => array( array( 'Field goals', (int) $k['fgm'], 'of ' . (int) $k['fga'] . ' tried' ), array( 'From 50+', self::val( $k, 'f50' ), 'of ' . self::val( $k, 'f50a' ) . ' tried' ), array( 'Longest', (int) $k['lng'], 'yards' ), array( 'Points', (int) $k['pts'], number_format( (float) self::val( $k, 'ppg' ), 1 ) . ' a game' ) ),
			'graded' => $q, 'ranks' => $rows,
			'rank_head' => 'Where he ranks · ' . ( $q ? 'among ' . $n . ' kickers' : 'ranked at a try for every team game' ),
			'foot_right' => 'Data: nflverse · ranks among ' . $n . ' kickers',
		);
	}
}
