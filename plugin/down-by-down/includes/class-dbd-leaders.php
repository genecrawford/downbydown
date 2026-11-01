<?php
/**
 * Leaders (SCOPE §37): a board for every Scorecard stat, every Playstyle trait and the fantasy numbers, from
 * players/leaders.json. Labels, formats and lower-is-better flags come from DBD_Grades, so they match the player page.
 * Board keys: rec.g.tshare (group, g = Scorecard or fp = Playstyle, metric), fan.ppg.WR, fan.tot, fan.boom, fan.lo.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Leaders {
	const TOP    = 10;
	const GROUPS = array( 'fan' => 'Fantasy', 'wr' => 'Wide receivers', 'te' => 'Tight ends', 'rb' => 'Backs', 'qb' => 'Quarterbacks' );
	/* wide receivers and tight ends share a data group (rec) but rank as separate positions, the way fantasy leagues do (Gene, 2026-11-01) */
	const SPLIT  = array( 'wr' => array( 'rec', 'WR', 'wide receivers' ), 'te' => array( 'rec', 'TE', 'tight ends' ) );
	public static function data_group( $g ) { return self::SPLIT[ $g ][0] ?? $g; }
	public static function pos_of( $g ) { return self::SPLIT[ $g ][1] ?? null; }
	public static function pool( $g ) { return self::SPLIT[ $g ][2] ?? DBD_Grades::pool_label( $g ); }
	const STEPS  = array( 'rec' => array( 15, 10, 5 ), 'rb' => array( 30, 20, 10 ), 'qb' => array( 50, 35, 20 ) );  /* the qualified pool, then two lower steps */
	const UNIT   = array( 'rec' => 'targets', 'rb' => 'touches', 'qb' => 'attempts' );
	const FAN    = array( 'ppg.QB' => array( 'Points a game', 'quarterbacks with enough games' ), 'ppg.RB' => array( 'Points a game', 'running backs with enough games' ), 'ppg.WR' => array( 'Points a game', 'wide receivers with enough games' ), 'ppg.TE' => array( 'Points a game', 'tight ends with enough games' ),
		'tot' => array( 'Season points', 'every position' ), 'boom' => array( 'Boom weeks', 'every position' ), 'lo' => array( 'Highest floor', 'his 20th-percentile game, 3+ games' ) );

	public static function file() { return DBD_Data::get( 'players/leaders.json' ) ?: array(); }
	public static function steps( $g ) { $g = self::data_group( $g ); $s = self::STEPS[ $g ]; $q = (int) ( self::file()['min'][ $g ] ?? $s[0] ); $s[0] = $q; return $s; }
	public static function unit( $g ) { return self::UNIT[ self::data_group( $g ) ]; }

	/** [label, sub, kind, lower is better] for a group metric. */
	public static function def( $g, $src, $k ) {
		$defs = $src === 'fp' ? DBD_Grades::fp_defs( $g ) : DBD_Grades::defs( $g );
		foreach ( $defs as $d ) { if ( $d[0] === $k ) { $low = $src === 'g' && in_array( $k, (array) ( DBD_Grades::group_info( $g )['low'] ?? array() ), true ); return array( $d[1], $d[2], $d[3], $low ); } }
		return null;
	}
	/** The board's sub-label, with "lower is better" added once when it applies. */
	public static function sub( $df ) { $s = (string) $df[1]; if ( $df[3] && stripos( $s, 'lower is better' ) === false ) { $s .= ( $s !== '' ? ', ' : '' ) . 'lower is better'; } return $s; }
	public static function fmt( $src, $kind, $v ) { return $src === 'fp' ? DBD_Grades::fp_fmt( $kind, $v ) : DBD_Grades::fmt( $kind, $v ); }

	/** A group board's rows, best first, above a usage floor and at a position (WR/TE on the receivers board). */
	public static function rows( $g, $src, $k, $min = null, $pos = null ) {
		$f = self::file(); $rows = (array) ( $f['groups'][ $g ][ $src ][ $k ] ?? array() ); $d = self::def( $g, $src, $k ); if ( ! $d ) { return array(); }
		$P = (array) ( $f['players'] ?? array() ); $min = $min === null ? self::steps( $g )[0] : (int) $min;
		$rows = array_values( array_filter( $rows, function ( $r ) use ( $min, $pos, $P ) { return (int) $r[2] >= $min && ( ! $pos || ( $P[ $r[0] ][2] ?? '' ) === $pos ) && isset( $P[ $r[0] ] ); } ) );
		if ( $d[3] ) { usort( $rows, function ( $a, $b ) { return $a[1] <=> $b[1]; } ); }
		return $rows;
	}

	/** Competition ranks with ties: [rank, tied] per row, by the value in column $col. */
	public static function ranks( $rows, $col = 1 ) {
		$out = array(); $n = count( $rows );
		foreach ( $rows as $i => $r ) {
			$v = $r[ $col ]; $j = $i; while ( $j > 0 && $rows[ $j - 1 ][ $col ] == $v ) { $j--; }
			$tied = ( $i > 0 && $rows[ $i - 1 ][ $col ] == $v ) || ( $i < $n - 1 && $rows[ $i + 1 ][ $col ] == $v );
			$out[] = array( $j + 1, $tied );
		}
		return $out;
	}
	public static function rk( $r ) { return ( $r[1] ? 'T-' : '' ) . $r[0]; }

	public static function who( $id, $size = 24 ) {
		$p = self::file()['players'][ $id ] ?? null; if ( ! $p ) { return ''; }
		list( $name, $team, $pos, $num, $photo ) = $p;
		return '<span class="who">' . ( $team ? DBD_Marks::player( $team, $num, $size, $photo ) : '' ) . '<span class="nm"><a href="' . esc_url( DBD_Router::url( array( 'player' => $id ) ) ) . '">' . esc_html( $name ) . '</a><small>' . esc_html( ( $team ?: '–' ) . ' · ' . $pos ) . '</small></span></span>';
	}

	private static function list_html( $rows, $valf ) {
		if ( ! $rows ) { return '<p class="m">Nobody qualifies yet.</p>'; }
		$top = array_slice( $rows, 0, self::TOP ); $rk = self::ranks( $rows );
		$h = '<ol class="ld-list">'; foreach ( $top as $i => $r ) { $h .= '<li><span class="rk">' . esc_html( self::rk( $rk[ $i ] ) ) . '</span>' . self::who( $r[0] ) . '<b class="v">' . esc_html( $valf( $r ) ) . '</b></li>'; }
		return $h . '</ol>';
	}

	private static function card( $title, $sub, $body, $href, $foot ) {
		return '<section class="dbd-panel ld-card"><div class="ld-h"><b>' . esc_html( $title ) . '</b>' . ( $sub !== '' ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</div>' . $body . '<a class="ld-all" href="' . esc_url( $href ) . '">' . esc_html( $foot ) . ' →</a></section>';
	}

	/** A position tab: Scorecard boards, then Playstyle boards, ranked within that position (wr and te are the receiver group split by position). */
	public static function group( $g ) {
		$dg = self::data_group( $g ); $only = self::pos_of( $g ); $q = self::steps( $g )[0]; $h = '';
		foreach ( array( 'g' => array( 'How good', 'the Scorecard stats, among ' . self::pool( $g ) . ' with ' . $q . '+ ' . self::unit( $g ) ), 'fp' => array( 'How he\'s used', 'the Playstyle traits, same pool' ) ) as $src => $band ) {
			$defs = $src === 'fp' ? DBD_Grades::fp_defs( $dg ) : DBD_Grades::defs( $dg ); $cards = '';
			foreach ( $defs as $d ) {
				$k = $d[0]; $df = self::def( $dg, $src, $k ); $rows = self::rows( $dg, $src, $k, null, $only );
				$body = self::list_html( $rows, function ( $r ) use ( $src, $df ) { return self::fmt( $src, $df[2], $r[1] ); } );
				$cards .= self::card( $df[0], self::sub( $df ), $body, DBD_Router::url( array( 'view' => 'leaders', 'b' => "$g.$src.$k" ) ), 'Full board · ' . count( $rows ) . ' qualified' );
			}
			$h .= '<section class="dbd-band ld-band"><h2 class="dbd-bandh">' . esc_html( $band[0] ) . '<span>' . esc_html( $band[1] ) . '</span></h2><div class="ld-grid">' . $cards . '</div></section>';
		}
		return array( '', '<div class="dbd-ld">' . $h . '</div>' );
	}

	/** The fantasy tab: points a game by position, season points, boom weeks, highest floor; every format, shown by the switch. */
	public static function fantasy() {
		$f = self::file(); $cards = '';
		foreach ( self::FAN as $k => $lab ) {
			$body = ''; $n = 0;
			foreach ( DBD_Fantasy::FMT as $fm ) {
				$rows = (array) ( $f['fan'][ $fm ][ $k ] ?? array() ); if ( $fm === 'half' ) { $n = count( $rows ); }
				$valf = $k === 'boom' ? function ( $r ) { return (string) (int) $r[1]; } : function ( $r ) { return DBD_Fantasy::num( $r[1] ); };
				$body .= '<div data-fx="' . $fm . '">' . self::list_html( $rows, $valf ) . '</div>';
			}
			$cards .= self::card( $lab[0] . ( strpos( $k, 'ppg.' ) === 0 ? ' · ' . substr( $k, 4 ) : '' ), $lab[1], $body, DBD_Router::url( array( 'view' => 'leaders', 'b' => 'fan.' . $k ) ), 'Full board · ' . $n . ( strpos( $k, 'ppg.' ) === 0 ? ' qualified' : '' ) );
		}
		return '<div class="ld-grid">' . $cards . '</div>';
	}

	/** One full board: every row above the floor, ranked; the filters (position, usage) run in leaders.js and renumber. */
	public static function board( $key ) {
		$parts = explode( '.', $key ); $f = self::file();
		if ( ( $parts[0] ?? '' ) === 'fan' ) {
			$k = implode( '.', array_slice( $parts, 1 ) ); $lab = self::FAN[ $k ] ?? null; if ( ! $lab ) { return null; }
			$tables = '';
			foreach ( DBD_Fantasy::FMT as $fm ) {
				$rows = (array) ( $f['fan'][ $fm ][ $k ] ?? array() ); $rk = self::ranks( $rows ); $tb = '';
				foreach ( $rows as $i => $r ) { $tb .= '<tr><td class="rk">' . esc_html( self::rk( $rk[ $i ] ) ) . '</td><td>' . self::who( $r[0], 28 ) . '</td><td data-v="' . esc_attr( (string) $r[1] ) . '">' . esc_html( $k === 'boom' ? (string) (int) $r[1] : DBD_Fantasy::num( $r[1] ) ) . '</td><td>' . (int) $r[2] . '</td></tr>'; }
				$tables .= '<div data-fx="' . $fm . '"><div class="dbd-tw"><table class="dbd-stat ld-full"><thead><tr><th>Rank</th><th>Player</th><th>' . esc_html( $lab[0] ) . '</th><th>Games</th></tr></thead><tbody>' . ( $tb ?: '<tr><td colspan="4" class="dbd-note">Nobody qualifies yet.</td></tr>' ) . '</tbody></table></div></div>';
			}
			return array( 'title' => $lab[0] . ( strpos( $k, 'ppg.' ) === 0 ? ' · ' . substr( $k, 4 ) : '' ), 'sub' => $lab[1], 'group' => 'fan', 'filters' => '<div class="dbd-fg"><span>Fantasy scoring</span>' . DBD_Fantasy::seg() . '</div>', 'body' => $tables );
		}
		list( $g, $src, $k ) = array_pad( $parts, 3, '' );
		if ( $g === 'rec' ) { $g = 'wr'; }  /* 1.13.0 links (rec.*) open the wide receivers board */
		$dg = self::data_group( $g ); $only = self::pos_of( $g );
		if ( ! isset( self::STEPS[ $dg ] ) || ! in_array( $src, array( 'g', 'fp' ), true ) ) { return null; }
		$df = self::def( $dg, $src, $k ); if ( ! $df ) { return null; }
		$steps = self::steps( $g ); $rows = self::rows( $dg, $src, $k, min( $steps ), $only ); $P = (array) ( $f['players'] ?? array() );
		$q = $steps[0]; $qrows = array_values( array_filter( $rows, function ( $r ) use ( $q ) { return (int) $r[2] >= $q; } ) ); $rk = self::ranks( $qrows ); $ri = 0; $tb = '';
		foreach ( $rows as $r ) {
			$in = (int) $r[2] >= $q; $lab = $in ? self::rk( $rk[ $ri++ ] ) : '';
			$tb .= '<tr data-use="' . (int) $r[2] . '" data-pos="' . esc_attr( $P[ $r[0] ][2] ?? '' ) . '" data-v="' . esc_attr( (string) $r[1] ) . '"' . ( $in ? '' : ' hidden' ) . '><td class="rk">' . esc_html( $lab ) . '</td><td>' . self::who( $r[0], 28 ) . '</td><td>' . esc_html( self::fmt( $src, $df[2], $r[1] ) ) . '</td><td>' . (int) $r[2] . '</td></tr>';
		}
		$fl = '<div class="dbd-fg"><label for="ld-min">Minimum ' . esc_html( self::unit( $g ) ) . '</label><select id="ld-min" class="ld-min">';
		foreach ( $steps as $i => $s ) { $fl .= '<option value="' . (int) $s . '"' . ( $i ? '' : ' selected' ) . '>' . (int) $s . '+' . ( $i ? '' : ' (qualified)' ) . '</option>'; }
		$fl .= '</select></div>';
		$body = '<div class="dbd-tw"><table class="dbd-stat ld-full" data-low="' . ( $df[3] ? 1 : 0 ) . '"><thead><tr><th>Rank</th><th>Player</th><th>' . esc_html( $df[0] ) . '</th><th>' . esc_html( ucfirst( self::unit( $g ) ) ) . '</th></tr></thead><tbody>' . ( $tb ?: '<tr><td colspan="4" class="dbd-note">Nobody qualifies yet.</td></tr>' ) . '</tbody></table></div>';
		return array( 'title' => $df[0] . ( $only ? ' · ' . $only : '' ), 'sub' => self::sub( $df ) ?: self::pool( $g ), 'group' => $g, 'filters' => $fl, 'body' => $body, 'count' => count( $qrows ) );
	}
}
