<?php
/**
 * Playoff posts (2.47.0, SCOPE §89): eleven posts from the day after Week 18 to the day after the Super Bowl, at noon Eastern, so they never
 * land on a weekly post (9:00 AM and 5:00 PM). Each is a pack-style item on a playoff week, so every network, Auto publish, the records,
 * retries and Try again work as they do for the weekly posts:
 *   Week 19  pset   The field is set        the bracket, the day after Week 18's last game
 *            pprev  Wild Card preview       the Thursday before the round
 *            pres   Wild Card results       the bracket with scores, the day after the round's last game
 *   Week 20  pprev, pres                    the Divisional round, the same way
 *   Week 21  pprev, pres                    the Conference Championships; the results post is "Super Bowl … is set"
 *   Week 22  proad  The road to the Super Bowl   the Wednesday after the conference games
 *            plead  Playoff leaders              the Wednesday before the Super Bowl
 *            pprev  Super Bowl preview           the Friday before it
 *            pres   Champions                    the day after it
 *   - A post exists once its data does: a preview when all of its round's games are on the schedule (and none has kicked off), a results post
 *     when they're all final. Until the schedule has a round, its days are counted from Week 18's last game, for the Share pack's list.
 *   - Posts book only while the Playoffs switch is on (SCOPE §88), because every post links to the playoff pages.
 *   - Each graphic draws its own stage of the bracket, so a redraw later never shows later rounds.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_PoPosts {
	const WEEKS = array( 19, 20, 21, 22 );
	const PLAN  = array( 19 => array( 'pset', 'pprev', 'pres' ), 20 => array( 'pprev', 'pres' ), 21 => array( 'pprev', 'pres' ), 22 => array( 'proad', 'plead', 'pprev', 'pres' ) );
	const ROUND = array( 19 => 'WC', 20 => 'DIV', 21 => 'CON', 22 => 'SB' );
	const COUNT = array( 'WC' => 6, 'DIV' => 4, 'CON' => 2, 'SB' => 1 );
	/* days after Week 18's last game, while the schedule doesn't have the round yet */
	const DAYS  = array( '19-pset' => 1, '19-pprev' => 4, '19-pres' => 9, '20-pprev' => 11, '20-pres' => 15, '21-pprev' => 18, '21-pres' => 22, '22-proad' => 24, '22-plead' => 31, '22-pprev' => 33, '22-pres' => 36 );
	const HOUR  = 12;

	public static function is_slot( $slot ) { return in_array( (string) $slot, array( 'pset', 'pprev', 'pres', 'proad', 'plead' ), true ); }
	public static function valid( $n, $slot ) { return in_array( (string) $slot, self::PLAN[ (int) $n ] ?? array(), true ); }

	/* ------------------------------------------------------------------ the data */

	private static function seeds() { return DBD_Playoffs::seeds(); }
	private static function seed( $t ) { return (int) ( self::seeds()[ $t ]['seed'] ?? 0 ); }
	private static function rec( $t ) { return (string) ( self::seeds()[ $t ]['rec'] ?? '' ); }
	private static function conf( $t ) { return (string) ( self::seeds()[ $t ]['conf'] ?? DBD_Data::team( (string) $t )['conf'] ?? '' ); }
	private static function nick( $t ) { return (string) ( DBD_Data::team( (string) $t )['nick'] ?? $t ); }
	private static function up( $s ) { return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $s ) : strtoupper( (string) $s ); }
	private static function at_seed( $c, $n ) { foreach ( self::seeds() as $t => $x ) { if ( ( $x['conf'] ?? '' ) === $c && (int) ( $x['seed'] ?? 0 ) === (int) $n ) { return (string) $t; } } return ''; }
	private static function fin( $g ) { return DBD_Router::is_final( $g ); }
	private static function winner( $g ) { return self::fin( $g ) ? ( $g['home_score'] > $g['away_score'] ? $g['home'] : $g['away'] ) : ''; }
	private static function loser( $g ) { $w = self::winner( $g ); return $w === '' ? '' : ( $w === $g['home'] ? $g['away'] : $g['home'] ); }
	private static function wscore( $g ) { return max( (int) $g['home_score'], (int) $g['away_score'] ); }
	private static function lscore( $g ) { return min( (int) $g['home_score'], (int) $g['away_score'] ); }

	/** A round's games, the higher seed's first (a conference's when $c is set). */
	public static function games( $r, $c = '' ) {
		$o = array_values( array_filter( DBD_Playoffs::games(), function ( $g ) use ( $r, $c ) { return $g['r'] === $r && ( $c === '' || $g['conf'] === $c ); } ) );
		usort( $o, function ( $a, $b ) { return self::seed( $a['home'] ) <=> self::seed( $b['home'] ); } );
		return $o;
	}
	private static function full( $r ) { return count( self::games( $r ) ) === self::COUNT[ $r ]; }
	private static function done( $r ) { if ( ! self::full( $r ) ) { return false; } foreach ( self::games( $r ) as $g ) { if ( ! self::fin( $g ) ) { return false; } } return true; }
	private static function started( $r ) { foreach ( self::games( $r ) as $g ) { if ( DBD_Pack::started( $g ) ) { return true; } } return false; }
	private static function dates( $G ) { $d = array_map( function ( $g ) { return (string) $g['date']; }, $G ); sort( $d ); return $d; }
	/** Week 18: its last game's date, and whether every game is final. */
	private static function wk18() {
		$G = array_values( array_filter( (array) DBD_Data::week_games( 18 ), function ( $g ) { return ( $g['game_type'] ?? 'REG' ) === 'REG'; } ) ); if ( ! $G ) { return array( '', false ); }
		$d = self::dates( $G ); $all = true; foreach ( $G as $g ) { if ( ! self::fin( $g ) ) { $all = false; } }
		return array( (string) end( $d ), $all );
	}
	/** The Super Bowl: its schedule row, or its two teams from the conference winners until the row is there. */
	private static function sb() {
		$s = self::games( 'SB' )[0] ?? null; if ( $s ) { return $s; }
		if ( ! self::done( 'CON' ) ) { return null; }
		$w = array(); foreach ( self::games( 'CON' ) as $g ) { $w[ $g['conf'] ] = self::winner( $g ); }
		return isset( $w['AFC'], $w['NFC'] ) ? array( 'id' => '', 'r' => 'SB', 'conf' => '', 'away' => $w['NFC'], 'home' => $w['AFC'], 'home_score' => null, 'away_score' => null, 'date' => '', 'time' => '', 'day' => '', 'stadium' => '' ) : null;
	}
	private static function roman( $n ) { $o = ''; foreach ( array( 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I' ) as $v => $s ) { while ( $n >= $v ) { $o .= $s; $n -= $v; } } return $o; }
	public static function sb_name() { return 'Super Bowl ' . self::roman( (int) DBD_Data::season() - 1965 ); }

	/* ------------------------------------------------------------------ when */

	private static function noon( $ymd, $plus = 0 ) {
		$d = DateTime::createFromFormat( '!Y-m-d', (string) $ymd, new DateTimeZone( DBD_Instagram::TZ ) ); if ( ! $d ) { return 0; }
		if ( $plus ) { $d->modify( ( $plus > 0 ? '+' : '' ) . (int) $plus . ' days' ); } $d->setTime( self::HOUR, 0 ); return $d->getTimestamp();
	}
	/** The Thursday before a date, at noon. */
	private static function thursday( $ymd ) {
		$d = DateTime::createFromFormat( '!Y-m-d', (string) $ymd, new DateTimeZone( DBD_Instagram::TZ ) ); if ( ! $d ) { return 0; }
		do { $d->modify( '-1 day' ); } while ( $d->format( 'N' ) !== '4' ); $d->setTime( self::HOUR, 0 ); return $d->getTimestamp();
	}
	/** When a post goes: from its round's games once the schedule has them, otherwise counted from Week 18's last game; 0 when neither is known. */
	public static function time( $n, $slot ) {
		$n = (int) $n; if ( ! self::valid( $n, $slot ) ) { return 0; }
		list( $end ) = self::wk18(); $est = $end !== '' ? self::noon( $end, self::DAYS[ $n . '-' . $slot ] ) : 0; $r = self::ROUND[ $n ];
		if ( $slot === 'pset' ) { return $est; }
		if ( $r === 'SB' ) {
			$s = self::games( 'SB' )[0] ?? null; $con = self::full( 'CON' ) ? self::dates( self::games( 'CON' ) ) : array();
			if ( $slot === 'proad' ) { return $con ? self::noon( end( $con ), 3 ) : $est; }
			if ( ! $s ) { return $est; }
			return self::noon( $s['date'], array( 'plead' => -4, 'pprev' => -2, 'pres' => 1 )[ $slot ] );
		}
		if ( ! self::full( $r ) ) { return $est; }
		$d = self::dates( self::games( $r ) );
		return $slot === 'pprev' ? self::thursday( $d[0] ) : self::noon( end( $d ), 1 );
	}

	/* ------------------------------------------------------------------ the posts */

	/** A post's name, whether or not it exists yet. */
	public static function name( $n, $slot ) {
		$nm = array( '19-pset' => 'The field is set', '19-pprev' => 'Wild Card preview', '19-pres' => 'Wild Card results', '20-pprev' => 'Divisional preview', '20-pres' => 'Divisional results',
			'21-pprev' => 'Conference Championship preview', '21-pres' => self::sb_name() . ' is set', '22-proad' => 'The road to the Super Bowl', '22-plead' => 'Playoff leaders', '22-pprev' => 'Super Bowl preview', '22-pres' => 'Champions' );
		return $nm[ (int) $n . '-' . $slot ] ?? (string) $slot;
	}
	/** What a post is waiting for, in a sentence, or '' when it's ready. */
	public static function waiting( $n, $slot ) {
		if ( self::item( $n, $slot ) ) { return ''; }
		$rn = array( 'WC' => 'Wild Card', 'DIV' => 'Divisional', 'CON' => 'Conference Championship' ); $r = self::ROUND[ (int) $n ];
		if ( ! DBD_Playoffs::ready() ) { return 'Waiting for this season’s playoff seeds.'; }
		if ( $slot === 'pset' ) { return 'Appears when every Week 18 game is final.'; }
		if ( $slot === 'proad' || $slot === 'plead' ) { return 'Appears when both Conference Championships are final.'; }
		if ( $r === 'SB' ) { return $slot === 'pprev' ? ( self::games( 'SB' ) ? 'The Super Bowl has kicked off, so the preview is past.' : 'Appears when the Super Bowl is on the schedule.' ) : 'Appears when the Super Bowl is final.'; }
		if ( $slot === 'pprev' ) { return self::full( $r ) ? 'The round has kicked off, so the preview is past.' : 'Appears when all the ' . $rn[ $r ] . ' games are on the schedule.'; }
		return 'Appears when every ' . $rn[ $r ] . ' game is final.';
	}

	private static function nicks( $G, $join = ' at ' ) { return array_map( function ( $g ) use ( $join ) { return self::nick( $g['away'] ) . $join . self::nick( $g['home'] ); }, $G ); }
	private static function and_list( $a ) { $a = array_values( $a ); $l = array_pop( $a ); return $a ? implode( ', ', $a ) . ( count( $a ) > 1 ? ',' : '' ) . ' and ' . $l : (string) $l; }
	private static function day( $ymd ) { $d = DateTime::createFromFormat( '!Y-m-d', (string) $ymd ); return $d ? $d->format( 'l, F j' ) : ''; }
	private static function teams_of( $G ) { $o = array(); foreach ( $G as $g ) { $o[] = $g['away']; $o[] = $g['home']; } return array_values( array_unique( $o ) ); }
	private static function line_words( $g ) {
		$s = (float) ( $g['spread'] ?? 0 ); if ( ! $s ) { return ''; }
		return 'The ' . self::nick( $s > 0 ? $g['home'] : $g['away'] ) . ' are ' . rtrim( rtrim( number_format( abs( $s ), 1 ), '0' ), '.' ) . '-point favorites.';
	}

	/** A post's item, shaped like the weekly pack's (SCOPE §68), or null until its data is in. */
	public static function item( $n, $slot ) {
		$n = (int) $n; if ( ! self::valid( $n, $slot ) || ! DBD_Playoffs::ready() ) { return null; }
		$r = self::ROUND[ $n ]; $pl = DBD_Router::url( array( 'view' => 'playoffs' ) ); $sbn = self::sb_name(); $link = $pl; $teams = array(); $why = '';
		$words = array( 1 => 'one', 2 => 'two', 4 => 'four', 6 => 'six' ); $rname = array( 'WC' => 'Wild Card weekend', 'DIV' => 'The Divisional round', 'CON' => 'The Conference Championships' );
		if ( $slot === 'pset' ) {
			list( , $all ) = self::wk18(); if ( ! $all ) { return null; }
			$wc = self::games( 'WC' ); $d = $wc ? self::dates( $wc ) : array();
			$tl = 'The playoff field is set. The ' . self::nick( self::at_seed( 'AFC', 1 ) ) . ' and the ' . self::nick( self::at_seed( 'NFC', 1 ) ) . ' have the byes, and six Wild Card games come ' . ( $d ? 'next, starting ' . self::day( $d[0] ) : 'next' ) . '.';
			$ts = 'The playoff field is set: 14 teams, two byes and six Wild Card games.'; $teams = array( self::at_seed( 'AFC', 1 ), self::at_seed( 'NFC', 1 ) ); $why = 'The bracket the day after Week 18, with the six Wild Card games and both byes.';
		} elseif ( $slot === 'pprev' && $r !== 'SB' ) {
			if ( ! self::full( $r ) || self::started( $r ) ) { return null; }
			$G = self::games( $r ); usort( $G, function ( $a, $b ) { return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] ); } ); $d = self::dates( $G );
			$tl = $rname[ $r ] . ': ' . self::and_list( self::nicks( $G ) ) . '.'; $ts = $rname[ $r ] . ', ' . $words[ count( $G ) ] . ' games from ' . self::day( $d[0] ) . '.';
			$teams = self::teams_of( $G ); $why = 'Every game of the round: when and where, the seeds and records, and the line.';
		} elseif ( $slot === 'pprev' ) {
			$s = self::games( 'SB' )[0] ?? null; if ( ! $s || DBD_Pack::started( $s ) ) { return null; }
			$tl = $sbn . ': the ' . self::nick( $s['away'] ) . ' and the ' . self::nick( $s['home'] ) . ', ' . self::day( $s['date'] ) . ( ! empty( $s['stadium'] ) ? ' at ' . $s['stadium'] : '' ) . '. ' . self::line_words( $s );
			$ts = $sbn . ': ' . self::nick( $s['away'] ) . ' vs ' . self::nick( $s['home'] ) . ', ' . self::day( $s['date'] ) . '.'; $teams = array( $s['away'], $s['home'] ); $link = DBD_Router::url( array( 'view' => 'playoffs', 'pg' => $s['id'] ) );
			$why = 'The line and the total, and where each offense can win it.';
		} elseif ( $slot === 'pres' && $r === 'SB' ) {
			$s = self::games( 'SB' )[0] ?? null; if ( ! $s || ! self::fin( $s ) ) { return null; }
			$tl = 'The ' . self::nick( self::winner( $s ) ) . ' win ' . $sbn . ', ' . self::wscore( $s ) . '-' . self::lscore( $s ) . ' over the ' . self::nick( self::loser( $s ) ) . '.'; $ts = $tl;
			$teams = array( self::winner( $s ) ); $why = 'The finished bracket, with the champion’s road in red.';
		} elseif ( $slot === 'pres' && $r === 'CON' ) {
			if ( ! self::done( 'CON' ) ) { return null; } $s = self::sb(); $G = self::games( 'CON' );
			$bits = array_map( function ( $g ) { return 'the ' . self::nick( self::winner( $g ) ) . ' beat the ' . self::nick( self::loser( $g ) ) . ' ' . self::wscore( $g ) . '-' . self::lscore( $g ); }, $G );
			$tl = ucfirst( implode( ' and ', $bits ) ) . '. ' . $sbn . ' is set: ' . self::nick( $s['away'] ) . ' vs ' . self::nick( $s['home'] ) . ( ! empty( $s['date'] ) ? ', ' . self::day( $s['date'] ) : '' ) . '.';
			$ts = $sbn . ' is set: the ' . self::nick( $s['away'] ) . ' and the ' . self::nick( $s['home'] ) . '.'; $teams = array( $s['away'], $s['home'] ); if ( ! empty( $s['id'] ) ) { $link = DBD_Router::url( array( 'view' => 'playoffs', 'pg' => $s['id'] ) ); }
			$why = 'The bracket with both conference champions and the Super Bowl game filled in.';
		} elseif ( $slot === 'pres' ) {
			if ( ! self::done( $r ) ) { return null; } $G = self::games( $r ); usort( $G, function ( $a, $b ) { return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] ); } );
			$sc = array_map( function ( $g ) { return self::nick( self::winner( $g ) ) . ' ' . self::wscore( $g ) . ', ' . self::nick( self::loser( $g ) ) . ' ' . self::lscore( $g ); }, $G );
			$nx = $r === 'WC' ? 'The Divisional round is set.' : 'The Conference Championships are set.';
			$tl = ( $r === 'WC' ? 'Wild Card' : 'Divisional' ) . ' results: ' . implode( '; ', $sc ) . '. ' . $nx; $ts = ( $r === 'WC' ? 'Wild Card weekend is done.' : 'The Divisional round is done.' ) . ' ' . $nx;
			$teams = array_map( array( __CLASS__, 'winner' ), $G ); $why = 'The bracket with the scores, and the winners carried into the next round.';
		} elseif ( $slot === 'proad' ) {
			if ( ! self::done( 'CON' ) ) { return null; } $s = self::sb();
			$tl = 'How the ' . self::nick( $s['away'] ) . ' and the ' . self::nick( $s['home'] ) . ' reached ' . $sbn . ', game by game.'; $ts = $tl; $teams = array( $s['away'], $s['home'] );
			if ( ! empty( $s['id'] ) ) { $link = DBD_Router::url( array( 'view' => 'playoffs', 'pg' => $s['id'] ) ); } $why = 'Each team’s playoff games, round by round.';
		} else {  /* plead */
			if ( ! self::done( 'CON' ) ) { return null; } $L = self::leaders(); if ( ! isset( $L['py'], $L['ry'], $L['recy'] ) ) { return null; }
			$tl = 'Playoff leaders through the Conference Championships: ' . $L['py']['n'] . ', ' . number_format( (float) $L['py']['v'] ) . ' passing yards; ' . $L['ry']['n'] . ', ' . number_format( (float) $L['ry']['v'] ) . ' rushing; ' . $L['recy']['n'] . ', ' . number_format( (float) $L['recy']['v'] ) . ' receiving.';
			$ts = 'Playoff leaders through the Conference Championships.'; $teams = array_values( array_unique( array( $L['py']['t'], $L['ry']['t'], $L['recy']['t'] ) ) ); $why = 'Passing, rushing and receiving yards, touchdowns and sacks, playoff games only.';
		}
		$h = self::name( $n, $slot ); $file = 'dbd-playoffs-' . strtolower( (string) preg_replace( '/[^a-z0-9]+/i', '-', $h ) ) . '.jpg';
		return array( 'k' => $slot, 'h' => $h, 'why' => $why, 'card' => 'page-hub', 'file' => $file, 'fits' => array( 'r/nfl' ), 'title' => $tl, 'comment' => 'The bracket, every game’s preview and playoff leaders: ' . $link,
			'post' => DBD_Pack::post( $tl, $ts, $link ), 'tl' => $tl, 'ts' => $ts, 'link' => $link, 'lead' => $h, 'teams' => array_values( array_filter( $teams ) ), 'sq' => DBD_Tall::key( $n, $slot ), 'sqfile' => $file, 'po' => true );
	}

	/** Week $n's posts to book, slot => array( item ), in time order: none while the Playoffs switch is off. */
	public static function plan( $n ) {
		if ( ! DBD_Playoffs::on() || ! isset( self::PLAN[ (int) $n ] ) ) { return array(); }
		$o = array(); foreach ( self::PLAN[ (int) $n ] as $sl ) { $it = self::item( $n, $sl ); if ( $it && self::time( $n, $sl ) ) { $o[ $sl ] = array( $it ); } }
		return $o;
	}

	/** The leader in each playoff total through the Conference Championships: key => id, n, t, v, g. */
	private static function leaders() {
		$o = array(); foreach ( DBD_Playoffs::totals( array( 'WC', 'DIV', 'CON' ) ) as $k => $rows ) {
			$L = array_values( $rows ); if ( ! $L ) { continue; } usort( $L, function ( $a, $b ) { return array( -$a['v'], $a['n'] ) <=> array( -$b['v'], $b['n'] ); } ); $o[ $k ] = $L[0];
		}
		return $o;
	}

	/* ------------------------------------------------------------------ the graphics (SCOPE §85's look) */

	/** Draw a post's graphic, or null when the post isn't there. */
	public static function render( $n, $slot, $look, $fetch ) {
		if ( ! self::item( $n, $slot ) ) { return null; }
		$c = new DBD_Tall_Canvas( $look, $fetch ); if ( ! $c->ok ) { return null; } $n = (int) $n; $sbn = self::up( self::sb_name() );
		if ( $slot === 'pset' ) { $wc = self::games( 'WC' ); return self::bracket( $c, 1, array( 'THE FIELD IS SET' ), (int) DBD_Data::season() . ' PLAYOFFS' . ( count( $wc ) === 6 ? ' · WILD CARD ' . self::span( $wc ) : ' · 14 TEAMS' ) ); }
		if ( $slot === 'pres' ) {
			if ( $n === 19 ) { $dv = self::games( 'DIV' ); return self::bracket( $c, 2, array( 'WILD CARD RESULTS' ), 'THE DIVISIONAL ROUND' . ( count( $dv ) === 4 ? ' · ' . self::span( $dv ) : ' IS SET' ) ); }
			if ( $n === 20 ) { $cg = self::games( 'CON' ); return self::bracket( $c, 3, array( 'DIVISIONAL RESULTS' ), 'THE CONFERENCE CHAMPIONSHIPS' . ( count( $cg ) === 2 ? ' · ' . self::span( $cg ) : ' ARE SET' ) ); }
			$s = self::sb();
			if ( $n === 21 ) { return self::bracket( $c, 4, array( $sbn . ' IS SET' ), self::up( self::nick( $s['away'] ) . ' vs ' . self::nick( $s['home'] ) ) . ( ! empty( $s['date'] ) ? ' · ' . self::md( $s['date'] ) : '' ) ); }
			$w = self::winner( $s ); return self::bracket( $c, 5, array( self::up( self::nick( $w ) ) . ' WIN', $sbn ), self::up( self::nick( $w ) . ' ' . self::wscore( $s ) . ', ' . self::nick( self::loser( $s ) ) . ' ' . self::lscore( $s ) ) );
		}
		if ( $slot === 'pprev' && $n < 22 ) {
			$G = self::games( self::ROUND[ $n ] ); $t = array( 19 => array( 'WILD CARD WEEKEND' ), 20 => array( 'THE DIVISIONAL', 'ROUND' ), 21 => array( 'CONFERENCE', 'CHAMPIONSHIPS' ) )[ $n ];
			$d = self::dates( $G ); $sub = $n === 21 ? self::up( ( DateTime::createFromFormat( '!Y-m-d', $d[0] ) ?: new DateTime() )->format( 'l' ) ) . ' ' . self::md( $d[0] ) : count( $G ) . ' GAMES · ' . self::span( $G );
			return self::preview( $c, $G, $t, $sub );
		}
		if ( $slot === 'pprev' ) { return self::sb_preview( $c, self::games( 'SB' )[0] ); }
		if ( $slot === 'proad' ) { return self::road( $c, self::sb() ); }
		return self::leaders_board( $c );
	}

	private static function md( $ymd ) { $d = DateTime::createFromFormat( '!Y-m-d', (string) $ymd ); return $d ? self::up( $d->format( 'M' ) ) . ' ' . $d->format( 'j' ) : ''; }
	/** JAN 16–18, or JAN 31–FEB 1. */
	private static function span( $G ) {
		$d = self::dates( $G ); $a = DateTime::createFromFormat( '!Y-m-d', $d[0] ); $z = DateTime::createFromFormat( '!Y-m-d', (string) end( $d ) ); if ( ! $a || ! $z ) { return ''; }
		if ( $a == $z ) { return self::md( $d[0] ); }
		return self::md( $d[0] ) . '–' . ( $a->format( 'M' ) === $z->format( 'M' ) ? $z->format( 'j' ) : self::md( (string) end( $d ) ) );
	}
	/** A see-through veil of the background over a box: an eliminated team's tile. */
	private static function fade( $c, $x, $y, $w, $h ) {
		$S = DBD_Tall::S; list( $r, $g, $b ) = DBD_Tall::rgb( $c->t['bg'] ); $a = imagecolorallocatealpha( $c->im, $r, $g, $b, 48 );
		imagefilledrectangle( $c->im, (int) round( $x * $S ), (int) round( $y * $S ), (int) round( ( $x + $w ) * $S ) - 1, (int) round( ( $y + $h ) * $S ) - 1, $a );
	}
	private static function hl( $c, $xa, $xb, $y, $col ) { $c->rect( min( $xa, $xb ), $y - 1.5, abs( $xb - $xa ) + 1.5, 3, $col ); }
	private static function vl( $c, $x, $ya, $yb, $col ) { $c->rect( $x - 1.5, min( $ya, $yb ), 3, abs( $yb - $ya ) + 1.5, $col ); }

	/** The bracket at a stage: 1 the field, 2 after the Wild Card round, 3 after the Divisional, 4 the Super Bowl set, 5 the champion. */
	private static function bracket( $c, $st, $lines, $sub ) {
		$W = DBD_Tall::W; $H = DBD_Tall::H; $b = $c->title( $lines, $sub, 56, $W - 112, 128, 104 ); $y0 = $b + 30; $y1 = $H - 112; $band = 124; $blk = ( $y1 - $y0 - $band ) / 2;
		$lc = DBD_Tall::mix( $c->t['mut'], $c->t['bg'], 0.3 ); $sb = $st >= 4 ? self::sb() : null; $champ = $st === 5 && $sb ? self::winner( $sb ) : '';
		$COL = array( array( 56, 300 ), array( 400, 280 ), array( 724, 300 ) ); $pos = array();
		$fin = array( 'WC' => $st >= 2, 'DIV' => $st >= 3, 'CON' => $st >= 4, 'SB' => $st >= 5 ); $known = array( 'DIV' => $st >= 2, 'CON' => $st >= 3, 'SB' => $st >= 4 );
		$rh = min( 48, ( $blk - 54 ) / 8.5 );
		$row = function ( $x, $y, $w, $t, $sc, $state, $r ) use ( $c, $rh, &$pos ) {
			$c->rect( $x, $y, $w, $rh, $c->t['row'] ); if ( $t === '' ) { return; }
			$c->text( $x + 13, $y + $rh * 0.7, (string) self::seed( $t ), 's', $rh * 0.55, $c->t['mut'] );
			$tw = $rh * 1.3; $c->tile( $t, $x + 40, $y, $tw, $rh, $rh - 8 );
			$nm = self::up( self::nick( $t ) ); $c->text( $x + 40 + $tw + 12, $y + $rh * 0.74, $nm, 'b', $c->fit( $nm, 'b', $rh * 0.64, $w - 40 - $tw - 12 - 62 ), $state === 'l' ? $c->t['mut'] : $c->t['ink'] );
			if ( $sc === 'BYE' ) { $c->text( $x + $w - 12, $y + $rh * 0.7, 'BYE', 's', $rh * 0.5, $c->t['mut'], true, 1.5 ); }
			elseif ( $sc !== null ) { $c->text( $x + $w - 12, $y + $rh * 0.76, (string) $sc, 'b', $rh * 0.68, $state === 'w' ? $c->t['ink'] : $c->t['mut'], true ); }
			if ( $state === 'l' ) { self::fade( $c, $x + 40, $y, $tw, $rh ); }
			$pos[ $r . '|' . $t ] = array( $x, $y, $w );
		};
		$box = function ( $x, $y, $w, $g, $r ) use ( $row, $rh, $fin ) {
			if ( ! $g ) { $row( $x, $y, $w, '', null, 'e', $r ); $row( $x, $y + $rh + 2, $w, '', null, 'e', $r ); return; }
			foreach ( array( 'away', 'home' ) as $i => $sd ) { $t = (string) $g[ $sd ]; $f = $fin[ $r ] && self::fin( $g ); $row( $x, $y + $i * ( $rh + 2 ), $w, $t, $f ? (int) $g[ $sd . '_score' ] : null, $f ? ( self::winner( $g ) === $t ? 'w' : 'l' ) : 'p', $r ); }
		};
		$link = function ( $a, $to, $t ) use ( $c, &$pos, $rh, $lc, $champ ) {
			if ( ! isset( $pos[ $a ], $pos[ $to ] ) ) { return; }
			list( $xa, $ya, $wa ) = $pos[ $a ]; list( $xb, $yb ) = $pos[ $to ]; $x1 = $xa + $wa; $y1 = $ya + $rh / 2; $y2 = $yb + $rh / 2; $mx = ( $x1 + $xb ) / 2; $col = $t === $champ ? $c->t['red'] : $lc;
			self::hl( $c, $x1, $mx, $y1, $col ); self::vl( $c, $mx, $y1, $y2, $col ); self::hl( $c, $mx, $xb, $y2, $col );
		};
		$sbtop = $y0 + $blk + $band / 2 - $rh - 1; $conbox = array();
		foreach ( array( 'AFC', 'NFC' ) as $ci => $cf ) {
			$by = $y0 + $ci * ( $blk + $band ); $c->text( 56, $by + 32, $cf, 'b', 42, $c->t['ink'] );
			foreach ( array( 'WILD CARD', 'DIVISIONAL', 'CONFERENCE' ) as $k => $lab ) { $c->text( $COL[ $k ][0] + ( $k ? 0 : $c->w( $cf, 'b', 42 ) + 18 ), $by + 30, $lab, 's', 22, $c->t['mut'], false, 1.6 ); }
			$ga = $by + 54; $gh = $blk - 54; $gap = ( $gh - 7 * $rh ) / 3; $t0 = $ga; $t1 = $t0 + $rh + $gap; $t2 = $t1 + 2 * $rh + $gap; $t3 = $t2 + 2 * $rh + $gap; $one = self::at_seed( $cf, 1 );
			$row( $COL[0][0], $t0, $COL[0][1], $one, 'BYE', $st >= 2 ? 'w' : 'p', 'WC' );
			$wc = self::games( 'WC', $cf ); $pairs = array();
			foreach ( array( array( 2, 7 ), array( 3, 6 ), array( 4, 5 ) ) as $p ) {  /* the seeds decide the Wild Card games; the schedule's row adds the score */
				$h = self::at_seed( $cf, $p[0] ); $a = self::at_seed( $cf, $p[1] ); $g = null; foreach ( $wc as $x ) { if ( $x['home'] === $h && $x['away'] === $a ) { $g = $x; } }
				$pairs[] = $g ?: array( 'home' => $h, 'away' => $a, 'home_score' => null, 'away_score' => null );
			}
			foreach ( $pairs as $i => $g ) { $box( $COL[0][0], array( $t1, $t2, $t3 )[ $i ], $COL[0][1], $g, 'WC' ); }
			$dv = $known['DIV'] ? self::games( 'DIV', $cf ) : array(); usort( $dv, function ( $a, $b ) use ( $one ) { return ( in_array( $one, array( $a['home'], $a['away'] ), true ) ? 0 : 1 ) <=> ( in_array( $one, array( $b['home'], $b['away'] ), true ) ? 0 : 1 ); } );
			foreach ( array( ( $t0 + $t1 + 2 * $rh ) / 2, ( $t2 + $t3 + 2 * $rh ) / 2 ) as $i => $cy ) { $box( $COL[1][0], $cy - $rh - 1, $COL[1][1], $dv[ $i ] ?? null, 'DIV' ); }
			$cg = $known['CON'] ? ( self::games( 'CON', $cf )[0] ?? null ) : null; $ct = ( $t0 + $t3 + 2 * $rh ) / 2 - $rh - 1; $box( $COL[2][0], $ct, $COL[2][1], $cg, 'CON' ); $conbox[ $cf ] = $ct;
			if ( $known['DIV'] ) { $link( 'WC|' . $one, 'DIV|' . $one, $one ); if ( $fin['WC'] ) { foreach ( $pairs as $g ) { if ( self::fin( $g ) ) { $w = self::winner( $g ); $link( 'WC|' . $w, 'DIV|' . $w, $w ); } } } }
			if ( $known['CON'] && $fin['DIV'] ) { foreach ( $dv as $g ) { if ( self::fin( $g ) ) { $w = self::winner( $g ); $link( 'DIV|' . $w, 'CON|' . $w, $w ); } } }
		}
		/* the Super Bowl, between the conferences */
		$bt = $y0 + $blk; $c->text( 56, $bt + $band / 2 + 6, self::up( self::sb_name() ), 'b', 54, $c->t['ink'] ); $s = self::games( 'SB' )[0] ?? null;
		$under = $champ !== '' ? 'CHAMPIONS: ' . self::up( self::nick( $champ ) ) : ( $s ? self::up( self::md( $s['date'] ) . ( ! empty( $s['stadium'] ) ? ' · ' . $s['stadium'] : '' ) ) : '' );
		if ( $under !== '' ) { $c->text( 56, $bt + $band / 2 + 44, $under, 's', $c->fit( $under, 's', 24, 600 ), $champ !== '' ? $c->t['red'] : $c->t['mut'], false, 1.6 ); }
		$sides = $sb ? array( 'away', 'home' ) : array(); usort( $sides, function ( $a, $b ) use ( $sb ) { return ( self::conf( $sb[ $a ] ) === 'AFC' ? 0 : 1 ) <=> ( self::conf( $sb[ $b ] ) === 'AFC' ? 0 : 1 ); } );
		if ( ! $sides ) { $box( $COL[2][0], $sbtop, $COL[2][1], null, 'SB' ); }
		foreach ( $sides as $i => $sd ) { $t = (string) $sb[ $sd ]; $f = $fin['SB'] && self::fin( $sb ); $row( $COL[2][0], $sbtop + $i * ( $rh + 2 ), $COL[2][1], $t, $f ? (int) $sb[ $sd . '_score' ] : null, $f ? ( self::winner( $sb ) === $t ? 'w' : 'l' ) : 'p', 'SB' ); }
		if ( $sb && $fin['CON'] ) {
			$cx = $COL[2][0] + $COL[2][1] / 2;
			foreach ( array( 'AFC' => array( $conbox['AFC'] + 2 * $rh + 2, $sbtop ), 'NFC' => array( $conbox['NFC'], $sbtop + 2 * $rh + 2 ) ) as $cf => $yy ) {
				$t = self::conf( $sb['away'] ) === $cf ? $sb['away'] : $sb['home']; self::vl( $c, $cx, $yy[0], $yy[1], $t === $champ ? $c->t['red'] : $lc );
			}
		}
		$c->foot(); return $c->done();
	}

	/** A round's games: when and where, the seeds and records, and the line. */
	private static function preview( $c, $G, $lines, $sub ) {
		$W = DBD_Tall::W; $H = DBD_Tall::H; $b = $c->title( $lines, $sub, 56, $W - 112, 128, 108 ); $y = $b + 14;
		usort( $G, function ( $a, $b ) { return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] ); } ); $n = max( 1, count( $G ) ); $rh = ( $H - 112 - $y ) / $n; $k = min( $rh / 170, 1.45 ); $k2 = min( $k, 1.2 );
		foreach ( $G as $i => $g ) {
			$ry = $y + $i * $rh; $oy = $ry + ( $rh - 168 * $k ) / 2; if ( $i ) { $c->rect( 56, $ry, $W - 112, 1, $c->t['line'] ); }
			$lab = self::up( trim( ( $g['day'] ?? '' ) . ' ' . self::md( $g['date'] ) ) . ' · ' . ( DBD_Data::kickoff( $g )['et'] ?? '' ) . ( ! empty( $g['stadium'] ) ? ' · ' . $g['stadium'] : '' ) );
			$c->text( 56, $oy + 34 * $k, $lab, 's', $c->fit( $lab, 's', 24 * $k, $W - 112 - 30 ), $c->t['mut'], false, 1.4 );
			$ty = $oy + 52 * $k; $th = 96 * $k; $tw = 116;
			foreach ( array( $g['away'], $g['home'] ) as $j => $t ) {
				$x = 56 + $j * 384; $c->rect( $x, $ty, $tw, $th, DBD_Tall::c1( $t ) ); $lb = min( $th - 16, $tw - 16 ); $c->logo( $t, $x + ( $tw - $lb ) / 2, $ty + ( $th - $lb ) / 2, $lb, true );
				$nm = self::up( self::nick( $t ) ); $c->text( $x + $tw + 16, $ty + $th * 0.52, $nm, 'b', $c->fit( $nm, 'b', 52 * $k2, 190 ), $c->t['ink'] );
				$c->text( $x + $tw + 16, $ty + $th * 0.52 + 34 * $k2, self::seed( $t ) . ' SEED · ' . self::rec( $t ), 's', 24 * $k2, $c->t['mut'], false, 1 );
			}
			$c->text( 421 - $c->w( 'AT', 's', 26 ) / 2, $ty + $th * 0.6, 'AT', 's', 26, $c->t['mut'] );
			$ln = self::line( $g ); $c->text( $W - 56, $ty + 24 * $k, 'LINE', 's', 22, $c->t['mut'], true, 1.6 ); $c->text( $W - 56, $ty + $th - 4, $ln, 'b', $c->fit( $ln, 'b', 50 * $k2, 230 ), $c->t['ink'], true );
		}
		$c->foot(); return $c->done();
	}
	/** RAMS −10, or PICK. */
	private static function line( $g ) { $s = (float) ( $g['spread'] ?? 0 ); if ( ! $s ) { return 'PICK'; } return self::up( self::nick( $s > 0 ? $g['home'] : $g['away'] ) ) . ' −' . rtrim( rtrim( number_format( abs( $s ), 1 ), '0' ), '.' ); }

	/** The two Super Bowl teams' playoff games, round by round. */
	private static function road( $c, $sb ) {
		$W = DBD_Tall::W; $H = DBD_Tall::H; $sbn = self::up( self::sb_name() ); $s = self::games( 'SB' )[0] ?? null;
		$b = $c->title( array( 'THE ROAD TO', $sbn ), $s ? self::up( self::md( $s['date'] ) . ( ! empty( $s['stadium'] ) ? ' · ' . $s['stadium'] : '' ) ) : null, 56, $W - 112, 128, 104 ); $y = $b + 28;
		$tt = array( $sb['away'], $sb['home'] ); usort( $tt, function ( $a, $b ) { return ( self::conf( $a ) === 'AFC' ? 0 : 1 ) <=> ( self::conf( $b ) === 'AFC' ? 0 : 1 ); } );
		foreach ( $tt as $j => $t ) {
			$x = 56 + $j * 496; $w = 472; $col = DBD_Tall::c1( $t ); $ink = DBD_Tall::on( $col ); $c->rect( $x, $y, $w, 200, $col ); $c->logo( $t, $x + 22, $y + 24, 152, true );
			$nm = self::up( self::nick( $t ) ); $c->text( $x + $w - 24, $y + 112, $nm, 'b', $c->fit( $nm, 'b', 70, $w - 210 ), $ink, true );
			$c->text( $x + $w - 24, $y + 160, self::conf( $t ) . ' · ' . self::seed( $t ) . ' SEED · ' . self::rec( $t ), 's', 26, $ink, true, 1.2 );
			$yy = $y + 226; $rh = ( $H - 120 - $yy ) / 3;
			foreach ( array( 'WC' => 'WILD CARD', 'DIV' => 'DIVISIONAL', 'CON' => 'CONFERENCE' ) as $r => $lab ) {
				$i = array_search( $r, array( 'WC', 'DIV', 'CON' ), true ); $ry = $yy + $i * $rh; $g = null; foreach ( self::games( $r ) as $x2 ) { if ( in_array( $t, array( $x2['home'], $x2['away'] ), true ) ) { $g = $x2; } }
				if ( $i ) { $c->rect( $x, $ry, $w, 1, $c->t['line'] ); } $c->text( $x, $ry + 44, $lab, 's', 26, $c->t['red'], false, 2 );
				if ( ! $g ) { $c->text( $x, $ry + 128, 'BYE', 'b', 84, $c->t['mut'] ); $c->text( $x, $ry + 172, 'The 1 seed sat out the first round', 'p', 24, $c->t['mut'] ); continue; }
				$o = $g['home'] === $t ? $g['away'] : $g['home']; $me = (int) ( $g['home'] === $t ? $g['home_score'] : $g['away_score'] ); $op = (int) ( $g['home'] === $t ? $g['away_score'] : $g['home_score'] );
				$c->text( $x, $ry + 128, ( $me > $op ? 'W ' : 'L ' ) . $me . '–' . $op, 'b', 84, $c->t['ink'] );
				$c->tile( $o, $x, $ry + 146, 64, 40, 32 ); $c->text( $x + 78, $ry + 176, ( $g['away'] === $t ? 'at the ' : 'vs the ' ) . self::nick( $o ), 'p', 26, $c->t['mut'] );
			}
		}
		$c->foot(); return $c->done();
	}

	/** Playoff leaders through the Conference Championships, in Week leaders' layout. */
	private static function leaders_board( $c ) {
		$W = DBD_Tall::W; $H = DBD_Tall::H; $L = self::leaders(); $b = $c->title( array( 'PLAYOFF LEADERS' ), 'THROUGH THE CONFERENCE CHAMPIONSHIPS', 56, $W - 112, 128, 118 ); $y = $b + 22;
		$rows = array_filter( array( 'py' => array( 'PASSING', 'YDS' ), 'ry' => array( 'RUSHING', 'YDS' ), 'recy' => array( 'RECEIVING', 'YDS' ), 'td' => array( 'TOUCHDOWNS', 'TD' ), 'sk' => array( 'SACKS', 'SACKS' ) ), function ( $k ) use ( $L ) { return isset( $L[ $k ] ); }, ARRAY_FILTER_USE_KEY );
		$rh = ( $H - 104 - $y ) / max( 1, count( $rows ) ); $i = 0;
		foreach ( $rows as $k => $lu ) {
			$e = $L[ $k ]; $ry = $y + $i * $rh; $tile = 176; $c->rect( 56, $ry, $tile, $rh - 14, DBD_Tall::c1( $e['t'] ) );
			if ( $e['id'] !== '' && $c->head_ok( $e['id'], $e['t'] ) ) { $c->head( $e['id'], $e['t'], 56, $ry, $tile, $rh - 14, 1.62 ); } else { $c->logo( $e['t'], 56 + 26, $ry + ( $rh - 14 - 124 ) / 2, 124, true ); }
			$val = $k === 'sk' ? rtrim( rtrim( number_format( (float) $e['v'], 1 ), '0' ), '.' ) : number_format( (float) $e['v'] ); $uw = $c->w( $lu[1], 's', 32 ); $vp = $c->fit( $val, 'b', 128, 300 ); $vw = $c->w( $val, 'b', $vp ); $tx = 56 + $tile + 28; $room = $W - 56 - $uw - 12 - $vw - 20 - $tx;
			$c->text( $tx, $ry + 44, $lu[0], 's', 30, $c->t['red'], false, 2 ); $nm = self::up( $k === 'sk' ? str_replace( '.', '. ', (string) $e['n'] ) : (string) $e['n'] ); $c->text( $tx, $ry + 104, $nm, 'b', $c->fit( $nm, 'b', 62, min( 470, $room ) ), $c->t['ink'] );
			$det = self::nick( $e['t'] ) . ' · ' . (int) $e['g'] . ( (int) $e['g'] === 1 ? ' game' : ' games' ); $c->text( $tx, $ry + 148, $det, 'p', $c->fit( $det, 'p', 25, $room ), $c->t['mut'] );
			$c->text( $W - 56, $ry + 148, $lu[1], 's', 32, $c->t['mut'], true ); $c->text( $W - 56 - $uw - 12, $ry + 148, $val, 'b', $vp, $c->t['ink'], true );
			if ( $i < count( $rows ) - 1 ) { $c->rect( 56, $ry + $rh - 7, $W - 112, 1, $c->t['line'] ); } $i++;
		}
		$c->foot(); return $c->done();
	}

	/** The Super Bowl: the teams, the line and the total, and where each offense can win it (the game page's edge, SCOPE §88). */
	private static function sb_preview( $c, $s ) {
		$W = DBD_Tall::W; $sub = self::up( trim( ( $s['day'] ?? '' ) . ' ' . self::md( $s['date'] ) ) . ' · ' . ( DBD_Data::kickoff( $s )['et'] ?? '' ) . ( ! empty( $s['stadium'] ) ? ' · ' . $s['stadium'] : '' ) );
		$b = $c->title( array( self::up( self::sb_name() ) ), $sub, 56, $W - 112, 128, 124 ); $y = $b + 28;
		foreach ( array( $s['away'], $s['home'] ) as $i => $t ) {
			$x = 56 + $i * 496; $col = DBD_Tall::c1( $t ); $ink = DBD_Tall::on( $col ); $c->rect( $x, $y, 472, 150, $col ); $c->logo( $t, $x + 22, $y + 19, 112, true );
			$nm = self::up( self::nick( $t ) ); $c->text( $x + 450, $y + 98, $nm, 'b', $c->fit( $nm, 'b', 66, 290 ), $ink, true ); $c->text( $x + 450, $y + 132, self::conf( $t ) . ' · ' . self::rec( $t ), 's', 24, $ink, true, 1 );
		}
		$tw = $c->w( 'VS', 'b', 34 ) + 22; $c->rect( $W / 2 - $tw / 2, $y + 54, $tw, 44, $c->t['bg'] ); $c->text( $W / 2 - $c->w( 'VS', 'b', 34 ) / 2, $y + 88, 'VS', 'b', 34, $c->t['ink'] ); $y += 214;
		$c->text( 56, $y, 'THE LINE', 's', 28, $c->t['mut'], false, 2 ); if ( isset( $s['total'] ) && $s['total'] !== null && $s['total'] !== '' ) { $c->text( $W - 56, $y, 'TOTAL ' . rtrim( rtrim( number_format( (float) $s['total'], 1 ), '0' ), '.' ), 's', 28, $c->t['mut'], true, 2 ); }
		$c->text( 52, $y + 84, self::line( $s ), 'b', 88, $c->t['ink'] ); $y += 132;
		foreach ( array( array( $s['away'], $s['home'] ), array( $s['home'], $s['away'] ) ) as $p ) {
			$e = DBD_Playoffs::edge( $p[0], $p[1] ); if ( ! $e ) { continue; }
			$c->rect( 56, $y - 20, $W - 112, 1, $c->t['line'] ); $c->text( 56, $y + 30, 'FOR THE ' . self::up( self::nick( $p[0] ) ), 's', 30, $c->t['red'], false, 2.2 );
			$hd = self::up( $e['head'] ); $c->text( 52, $y + 128, $hd, 'b', $c->fit( $hd, 'b', 108, $W - 112 ), $c->t['ink'] ); $yy = $y + 180;
			foreach ( $c->wrap( $e['say'], 'p', 28, $W - 112 ) as $ln ) { $c->text( 56, $yy, $ln, 'p', 28, $c->t['mut'] ); $yy += 40; }
			$y = $yy + 46;
		}
		$c->foot(); return $c->done();
	}
}
