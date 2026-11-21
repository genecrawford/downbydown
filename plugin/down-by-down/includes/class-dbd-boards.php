<?php
/**
 * The season draft and combine boards (SCOPE §63): /draft/{year}/ and /combine/{year}/, from data/draft/{year}.json, data/combine/{year}.json
 * and data/boards.json (tools/build_boards.py). The boards span every year, so they read the current data folder even on an archived
 * season's page, and they never ride along into an archive.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Boards {

	const DRILLS = array( 'forty' => '40-yard dash', 'vertical' => 'Vertical', 'broad_jump' => 'Broad jump', 'bench' => 'Bench press', 'cone' => '3-cone', 'shuttle' => 'Shuttle' );
	const LOW    = array( 'forty', 'cone', 'shuttle' );
	private static $cache = array();

	private static function read( $rel ) {
		if ( array_key_exists( $rel, self::$cache ) ) { return self::$cache[ $rel ]; }
		$path = DBD_Data::root_dir() . '/' . $rel; $j = null;
		if ( is_readable( $path ) ) { $j = json_decode( (string) file_get_contents( $path ), true ); }
		return self::$cache[ $rel ] = is_array( $j ) ? $j : null;
	}

	/** The years a board has, newest first. $kind is 'draft' or 'combine'. */
	public static function years( $kind ) {
		$b = self::read( 'boards.json' ); $y = array_map( 'intval', (array) ( $b[ $kind ] ?? array() ) ); rsort( $y );
		return $y;
	}

	/** One year's board with its rows as keyed arrays, or null. */
	public static function get( $kind, $year ) {
		if ( ! in_array( $kind, array( 'draft', 'combine' ), true ) ) { return null; }
		$f = self::read( $kind . '/' . (int) $year . '.json' );
		if ( ! $f || empty( $f['keys'] ) ) { return null; }
		$k = $f['keys']; $f['rows'] = array_map( function ( $r ) use ( $k ) { return array_combine( $k, array_pad( array_slice( (array) $r, 0, count( $k ) ), count( $k ), null ) ); }, (array) $f['rows'] );
		return $f;
	}

	/** A pick's line so far (the newest class) or over his career, by what he plays. */
	public static function line( $r ) {
		if ( ! (int) $r['g'] ) { return ''; }
		$g = (int) $r['g'] . ' g'; $c = (string) $r['cat'];
		if ( $c === 'QB' ) { return $g . ' · ' . number_format( (int) $r['py'] ) . ' pass yds, ' . (int) $r['ptd'] . ' TD'; }
		if ( $c === 'RB' ) { return $g . ' · ' . number_format( (int) $r['ry'] ) . ' rush yds' . ( (int) $r['recy'] ? ', ' . number_format( (int) $r['recy'] ) . ' rec' : '' ); }
		if ( $c === 'WR' || $c === 'TE' ) { return $g . ' · ' . (int) $r['rec'] . ' rec, ' . number_format( (int) $r['recy'] ) . ' yds'; }
		if ( $r['side'] === 'D' ) { return $g . ' · ' . (int) $r['tkl'] . ' solo tkl' . ( (float) $r['sk'] ? ', ' . ( 0 + $r['sk'] ) . ' sk' : '' ) . ( (int) $r['int'] ? ', ' . (int) $r['int'] . ' INT' : '' ); }
		return $g;
	}

	/** How a "so far" column sorts: the main production number for his position. */
	public static function weight( $r ) {
		$c = (string) $r['cat'];
		if ( $c === 'QB' ) { return (int) $r['py']; }
		if ( $c === 'RB' ) { return (int) $r['ry'] + (int) $r['recy']; }
		if ( $c === 'WR' || $c === 'TE' ) { return (int) $r['recy']; }
		if ( $r['side'] === 'D' ) { return (int) $r['tkl'] + 6 * (float) $r['sk'] + 10 * (int) $r['int']; }
		return (int) $r['g'];
	}

	/** A drill result for display. */
	public static function fmt( $k, $v ) {
		if ( $v === null ) { return '–'; }
		$v = (float) $v;
		switch ( $k ) {
			case 'ht': return floor( $v / 12 ) . "'" . ( (int) $v % 12 ) . '"';
			case 'wt': return (string) (int) $v;
			case 'bench': return (string) (int) $v;
			case 'vertical': return rtrim( rtrim( number_format( $v, 1 ), '0' ), '.' ) . '"';
			case 'broad_jump': return floor( $v / 12 ) . "'" . ( (int) $v % 12 ) . '"';
			default: return number_format( $v, 2 );
		}
	}

	/** Shade for a percentile: green above the middle, red below, nothing for none. */
	public static function heat( $p ) {
		if ( $p === null ) { return ''; }
		$p = (int) $p;
		return 'background:color-mix(in srgb,#1e7f4a ' . round( max( 0, $p - 50 ) * 1.1 ) . '%,color-mix(in srgb,#b4261f ' . round( max( 0, 50 - $p ) * 0.9 ) . '%,transparent))';
	}

	/** The team logo for a franchise, small. */
	public static function logo( $f, $n = 18 ) {
		return DBD_Data::team( (string) $f ) ? '<span class="tl">' . DBD_Marks::tile( (string) $f, $n ) . '</span>' : '';  /* the shared mark: the logo only when the logo setting is on (Greptile, PR #112) */
	}

	/** The other board for this year (SCOPE §63, 2.23.1): array( url, button label, headline, line ). Before 2000 the draft points at the newest combine. */
	public static function other( $kind, $year ) {
		$to = $kind === 'draft' ? 'combine' : 'draft'; $ys = self::years( $to ); $y = in_array( (int) $year, $ys, true ) ? (int) $year : ( $ys[0] ?? 0 );
		if ( ! $y ) { return null; }
		$B = self::get( $to, $y ); $n = $B ? count( $B['rows'] ) : 0; $same = $y === (int) $year;
		if ( $to === 'combine' ) {
			return array( DBD_Router::url( array( 'view' => 'combine', 'y' => $y ) ), $same ? $y . ' combine results' : 'Combine results', 'The ' . $y . ' combine',
				( $same ? '' : 'The combine data starts in 2000. ' ) . $n . ' players tested: the 40, vertical, broad jump, bench, 3-cone and shuttle, each against every player at the position since 2000, with where each was drafted.' );
		}
		return array( DBD_Router::url( array( 'view' => 'draft', 'y' => $y ) ), $y . ' draft board', 'The ' . $y . ' draft', $n . ' picks, round by round: team, school, combine score and what each player has done since.' );
	}

	/** The head's controls: the year switcher and a button to the other board. */
	public static function controls( $kind, $year ) {
		$o = self::other( $kind, $year );
		return '<div class="bd-ctl">' . self::switcher( $kind, $year ) . ( $o ? '<a class="bd-btn" href="' . esc_url( $o[0] ) . '">' . esc_html( $o[1] ) . '</a>' : '' ) . '</div>';
	}

	/** The big link to the other board at the foot of the page. */
	public static function next_link( $kind, $year ) {
		$o = self::other( $kind, $year ); if ( ! $o ) { return ''; }
		return '<a class="bd-next" href="' . esc_url( $o[0] ) . '"><span class="k">' . ( $kind === 'draft' ? 'Next: the combine' : 'Next: the draft' ) . '</span><b>' . esc_html( $o[2] ) . '</b><span class="s">' . esc_html( $o[3] ) . '</span><span class="go">' . esc_html( $kind === 'draft' ? 'See the combine results' : 'See the draft board' ) . '</span></a>';
	}

	/** The year switcher: every year of this board, each linking to its page. */
	public static function switcher( $kind, $year ) {
		$o = ''; foreach ( self::years( $kind ) as $y ) { $o .= '<option value="' . esc_url( DBD_Router::url( array( 'view' => $kind, 'y' => $y ) ) ) . '"' . ( $y === (int) $year ? ' selected' : '' ) . '>' . (int) $y . '</option>'; }
		return '<label class="bd-year">' . ( $kind === 'draft' ? 'Draft' : 'Combine' ) . ' <select data-go>' . $o . '</select></label>';
	}
}
