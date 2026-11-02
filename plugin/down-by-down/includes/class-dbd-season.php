<?php
/**
 * The season page (SCOPE §44): an archived season's hub, and the current season's once its Super Bowl is final. The champion, the
 * playoffs round by round, final standings with playoff seeds marked, who led the season, the best offense and defense, and every
 * week in review. Everything comes from the season showing (DBD_Data::use_season).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Season {
	const ROUNDS = array( 'WC' => 'Wild Card', 'DIV' => 'Divisional', 'CON' => 'Conference', 'SB' => 'Super Bowl' );

	private static function all_games() {
		$out = array();
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( (array) $gl as $g ) { $out[] = $g; } }
		return $out;
	}

	/** The Super Bowl, once it is final. */
	public static function super_bowl() {
		foreach ( self::all_games() as $g ) { if ( ( $g['game_type'] ?? '' ) === 'SB' && DBD_Router::is_final( $g ) ) { return $g; } }
		return null;
	}

	/** The season page shows for a past season, and for this one once the Super Bowl is final (until next season's first build). */
	public static function show() { return DBD_Data::archived() || (bool) self::super_bowl(); }

	private static function pd() {
		$pd = array();
		foreach ( self::all_games() as $g ) {
			if ( ( $g['game_type'] ?? 'REG' ) !== 'REG' || ! DBD_Router::is_final( $g ) ) { continue; }
			$pd[ $g['home'] ] = ( $pd[ $g['home'] ] ?? 0 ) + $g['home_score'] - $g['away_score']; $pd[ $g['away'] ] = ( $pd[ $g['away'] ] ?? 0 ) + $g['away_score'] - $g['home_score'];
		}
		return $pd;
	}

	private static function post() {
		$out = array(); foreach ( self::all_games() as $g ) { if ( isset( self::ROUNDS[ $g['game_type'] ?? '' ] ) && DBD_Router::is_final( $g ) ) { $out[] = $g; } } return $out;
	}

	private static function winner( $g ) { return $g['home_score'] > $g['away_score'] ? $g['home'] : ( $g['away_score'] > $g['home_score'] ? $g['away'] : '' ); }

	private static function roman( $n ) { $o = ''; foreach ( array( 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I' ) as $v => $r ) { while ( $n >= $v ) { $o .= $r; $n -= $v; } } return $o; }

	private static function rec( $t ) { $r = DBD_Data::records()[ $t ] ?? array( 0, 0, 0 ); return $r[0] . '-' . $r[1] . ( $r[2] ? '-' . $r[2] : '' ); }

	public static function render() {
		$T = DBD_Data::teams(); $season = (int) DBD_Data::season(); $post = self::post(); $sb = self::super_bowl(); $h = '';
		$nick = function ( $t ) use ( $T ) { return $T[ $t ]['nick'] ?? $t; };
		$pw = function ( $t ) use ( $post ) { $n = 0; foreach ( $post as $g ) { if ( self::winner( $g ) === $t ) { $n++; } } return $n; };
		$wc = array(); $dv = array(); $in = array();
		foreach ( $post as $g ) { foreach ( array( $g['home'], $g['away'] ) as $t ) { $in[ $t ] = 1; if ( $g['game_type'] === 'WC' ) { $wc[ $t ] = 1; } if ( $g['game_type'] === 'DIV' ) { $dv[ $t ] = 1; } } }
		$bye = $wc ? array_diff_key( $dv, $wc ) : array();

		/* the champion */
		if ( $sb && ( $w = self::winner( $sb ) ) ) {
			$l = $w === $sb['home'] ? $sb['away'] : $sb['home']; $ws = max( $sb['home_score'], $sb['away_score'] ); $ls = min( $sb['home_score'], $sb['away_score'] );
			$d = ''; try { $d = ( new DateTime( $sb['date'] . ' 12:00' ) )->format( 'M j, Y' ); } catch ( Exception $e ) {}
			$side = function ( $t, $s, $lose ) use ( $nick ) { return '<span class="' . ( $lose ? 'l' : '' ) . '">' . DBD_Marks::tile( $t, 56 ) . '<b>' . esc_html( $nick( $t ) ) . '</b><em>' . (int) $s . '</em></span>'; };
			$h .= '<section class="dbd-panel dbd-champ" style="--c:' . esc_attr( $T[ $w ]['c1'] ?? '#121417' ) . '"><div class="l"><p class="k">Super Bowl ' . esc_html( self::roman( $season - 1965 ) ) . ( $d ? ' · ' . esc_html( $d ) : '' ) . ( ! empty( $sb['stadium'] ) ? ' · ' . esc_html( $sb['stadium'] ) : '' ) . '</p>'
				. '<h2><a href="' . esc_url( DBD_Router::url( array( 'team' => $w ) ) ) . '">' . esc_html( $T[ $w ]['name'] ?? $w ) . '</a> are the champions</h2>'
				. '<a class="s" href="' . esc_url( DBD_Router::url( array( 'game' => $sb['id'] ) ) ) . '">' . $side( $w, $ws, false ) . $side( $l, $ls, true ) . '</a>'
				. '<p class="n">' . esc_html( $nick( $w ) . ' went ' . self::rec( $w ) . ( isset( $bye[ $w ] ) ? ', had a bye,' : '' ) . ' and won ' . $pw( $w ) . ' playoff game' . ( $pw( $w ) === 1 ? '' : 's' ) . '; the ' . $nick( $l ) . ' went ' . self::rec( $l ) . ' and won ' . $pw( $l ) . ' before the final.' ) . '</p></div>'
				. '<div class="r">' . DBD_Marks::tile( $w, 170 ) . '</div></section>';
		}

		/* the playoffs */
		if ( $post ) {
			$h .= '<h2 class="dbd-bandh">The playoffs<span>round by round; every game opens its page</span></h2><div class="dbd-bracket">';
			foreach ( self::ROUNDS as $k => $lab ) {
				$gs = array_filter( $post, function ( $g ) use ( $k ) { return $g['game_type'] === $k; } ); if ( ! $gs ) { continue; }
				$h .= '<div class="rd"><h4>' . esc_html( $lab ) . '</h4>';
				foreach ( $gs as $g ) { $w = self::winner( $g ); $row = function ( $t, $s ) use ( $w, $nick ) { return '<span class="r' . ( $t === $w ? ' w' : '' ) . '">' . DBD_Marks::tile( $t, 22 ) . '<b>' . esc_html( $nick( $t ) ) . '</b><em>' . (int) $s . '</em></span>'; };
					$h .= '<a class="pg" href="' . esc_url( DBD_Router::url( array( 'game' => $g['id'] ) ) ) . '">' . $row( $g['away'], $g['away_score'] ) . $row( $g['home'], $g['home_score'] ) . '</a>'; }
				$h .= '</div>';
			}
			$h .= '</div>';
		}

		/* final standings */
		$pd = self::pd(); $rec = DBD_Data::records(); $wp = function ( $t ) use ( $rec ) { $r = $rec[ $t ] ?? array( 0, 0, 0 ); $n = $r[0] + $r[1] + $r[2]; return $n ? ( $r[0] + $r[2] / 2 ) / $n : 0; };
		$h .= '<h2 class="dbd-bandh">Final standings<span>' . ( $in ? 'P made the playoffs, Bye was a top seed' : 'regular season' ) . '</span></h2><div class="dbd-sdivs">';
		foreach ( array( 'AFC', 'NFC' ) as $c ) { foreach ( array( 'East', 'North', 'South', 'West' ) as $dn ) {
			$div = $c . ' ' . $dn; $ts = array_values( array_filter( array_keys( $T ), function ( $t ) use ( $T, $div ) { return ( $T[ $t ]['div'] ?? '' ) === $div; } ) );
			usort( $ts, function ( $a, $b ) use ( $wp, $pd ) { return $wp( $b ) <=> $wp( $a ) ?: ( $pd[ $b ] ?? 0 ) <=> ( $pd[ $a ] ?? 0 ); } );
			$h .= '<div class="dv"><h4>' . esc_html( $div ) . '</h4><table class="dbd-sst"><thead><tr><th>Team</th><th>W-L</th><th>Diff</th></tr></thead><tbody>';
			foreach ( $ts as $t ) { $d = (int) ( $pd[ $t ] ?? 0 );
				$h .= '<tr><td class="tm">' . DBD_Marks::tile( $t, 20 ) . '<a href="' . esc_url( DBD_Router::url( array( 'team' => $t ) ) ) . '">' . esc_html( $nick( $t ) ) . '</a>' . ( isset( $in[ $t ] ) ? '<i class="seed' . ( isset( $bye[ $t ] ) ? ' bye' : '' ) . '">' . ( isset( $bye[ $t ] ) ? 'Bye' : 'P' ) . '</i>' : '' ) . '</td><td>' . esc_html( self::rec( $t ) ) . '</td><td class="' . ( $d >= 0 ? 'pos' : 'neg' ) . '">' . ( $d > 0 ? '+' : '' ) . $d . '</td></tr>'; }
			$h .= '</tbody></table></div>';
		} }
		$h .= '</div>';

		/* who led: regular-season totals from each team's career file (this season's row), PPR points from the leaders file */
		$tot = array();
		foreach ( array_keys( $T ) as $t ) { $f = DBD_Data::get( 'players/career/' . $t . '.json' );
			foreach ( (array) ( $f['players'] ?? array() ) as $id => $rows ) { foreach ( (array) $rows as $r ) { if ( (int) ( $r['season'] ?? 0 ) === $season ) { $tot[ $id ] = $r; } } } }
		$boards = array( array( 'Passing yards', function ( $r ) { return (float) ( $r['passing_yards'] ?? 0 ); }, 'yds' ), array( 'Rushing yards', function ( $r ) { return (float) ( $r['rushing_yards'] ?? 0 ); }, 'yds' ),
			array( 'Receiving yards', function ( $r ) { return (float) ( $r['receiving_yards'] ?? 0 ); }, 'yds' ), array( 'Scrimmage touchdowns', function ( $r ) { return (float) ( $r['rushing_tds'] ?? 0 ) + (float) ( $r['receiving_tds'] ?? 0 ); }, 'TD' ) );
		$cards = '';
		$card = function ( $title, $list, $unit, $fmt ) {
			if ( ! $list ) { return ''; }
			list( $id, $v, $tm ) = $list[0]; $p = DBD_Data::player( $id );
			$rest = ''; foreach ( array_slice( $list, 1, 4 ) as $i => $x ) { $q = DBD_Data::player( $x[0] ); $rest .= '<li><span>' . ( $i + 2 ) . '</span><a href="' . esc_url( DBD_Router::url( array( 'player' => $x[0] ) ) ) . '">' . esc_html( $q['name'] ?? $x[0] ) . '</a><small>' . esc_html( $x[2] ) . '</small><em>' . esc_html( $fmt( $x[1] ) ) . '</em></li>'; }
			return '<section class="dbd-panel dbd-sld"><i class="h">' . esc_html( $title ) . '</i><div class="a">' . DBD_Marks::player( $tm ?: ( $p['team'] ?? '' ), $p['num'] ?? '', 52, $p['photo'] ?? null )
				. '<div><a href="' . esc_url( DBD_Router::url( array( 'player' => $id ) ) ) . '">' . esc_html( $p['name'] ?? $id ) . '</a><small>' . esc_html( ( $p['pos'] ?? '' ) . ' · ' . $tm ) . '</small><strong>' . esc_html( $fmt( $v ) ) . '<span>' . esc_html( $unit ) . '</span></strong></div></div><ol start="2">' . $rest . '</ol></section>';
		};
		$n0 = function ( $v ) { return number_format( (float) $v ); };
		foreach ( $boards as $b ) { $l = array(); foreach ( $tot as $id => $r ) { $v = $b[1]( $r ); if ( $v > 0 && DBD_Data::player( $id ) ) { $l[] = array( $id, $v, (string) ( $r['team'] ?? '' ) ); } } usort( $l, function ( $a, $b ) { return $b[1] <=> $a[1]; } ); $cards .= $card( $b[0], $l, $b[2], $n0 ); }
		$fan = DBD_Leaders::file()['fan']['ppr']['tot'] ?? array(); $l = array(); foreach ( array_slice( (array) $fan, 0, 5 ) as $x ) { $p = DBD_Data::player( $x[0] ); if ( $p ) { $l[] = array( $x[0], (float) $x[1], $p['team'] ); } }
		$cards .= $card( 'PPR fantasy points', $l, 'pts', function ( $v ) { return number_format( (float) $v, 1 ); } );
		if ( $cards !== '' ) { $h .= '<h2 class="dbd-bandh">Who led ' . $season . '<span>regular season</span></h2><div class="dbd-slds">' . $cards . '</div>'; }

		/* the best offense and defense, by EPA a play */
		$m = DBD_Data::metrics(); $best = function ( $side ) use ( $m ) { foreach ( (array) ( $m['rank'][ $side ] ?? array() ) as $t => $r ) { if ( ( $r['epa'] ?? 0 ) === 1 ) { return $t; } } return null; };
		$bo = $best( 'off' ); $bd = $best( 'def' );
		if ( $bo || $bd ) {
			$u = function ( $lab, $t, $v, $sub, $args ) use ( $nick ) { return $t ? '<a class="dbd-panel" href="' . esc_url( DBD_Router::url( $args ) ) . '"><i>' . esc_html( $lab ) . '</i>' . DBD_Marks::tile( $t, 40 ) . '<b>' . esc_html( $nick( $t ) ) . '</b><span>' . esc_html( $v . ' ' . $sub ) . '</span></a>' : ''; };
			$sg = function ( $v ) { return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 ); };
			$h .= '<div class="dbd-sunits">' . $u( 'Best offense', $bo, $bo ? $sg( $m['off'][ $bo ]['epa'] ?? 0 ) : '', 'EPA a play', array( 'team' => $bo ) ) . $u( 'Best defense', $bd, $bd ? $sg( $m['defense'][ $bd ]['epa'] ?? 0 ) : '', 'EPA a play allowed', array( 'team' => $bd, 'side' => 'def' ) ) . '</div>';
		}

		/* every week */
		$wks = DBD_Week::weeks(); sort( $wks );
		if ( $wks ) {
			$h .= '<h2 class="dbd-bandh">Every week<span>the week in review' . ( $post ? ', the regular season and the playoffs' : '' ) . '</span></h2><nav class="dbd-swks">';
			foreach ( $wks as $w ) { $lab = DBD_Router::week_label( $w ); $po = strpos( $lab, 'Week ' ) !== 0; $h .= '<a' . ( $po ? ' class="po"' : '' ) . ' href="' . esc_url( DBD_Router::url( array( 'view' => 'week', 'week' => $w ) ) ) . '">' . esc_html( $po ? $lab : (string) $w ) . '</a>'; }
			$h .= '</nav>';
		}
		return $h;
	}
}
