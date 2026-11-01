<?php
/**
 * The fantasy lens (SCOPE §34): standard, half-PPR and PPR points for QBs, RBs, WRs and TEs.
 * The build stores each player's per-format summary in his player file (fan), points a game in the index (fp/fr/fpos),
 * and points allowed by every defense in fantasy.json. Every format is rendered; [data-fx] blocks show the one the
 * reader picked, which dbd-site.js remembers on the device (half PPR until they choose).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Fantasy {
	const FMT   = array( 'std', 'half', 'ppr' );
	const LABEL = array( 'std' => 'Standard', 'half' => 'Half PPR', 'ppr' => 'PPR' );
	const WORD  = array( 'std' => 'standard', 'half' => 'half-PPR', 'ppr' => 'PPR' );
	/* fallbacks if fantasy.json is missing a line; the build writes the same numbers */
	const BOOM  = array( 'QB' => array( 25, 25, 25 ), 'RB' => array( 12, 16, 20 ), 'WR' => array( 12, 16, 20 ), 'TE' => array( 9, 12, 15 ) );
	const BUST  = array( 'QB' => array( 12, 12, 12 ), 'RB' => array( 5, 6.5, 8 ), 'WR' => array( 5, 6.5, 8 ), 'TE' => array( 3, 4, 5 ) );
	const SLOT  = array( 'QB' => 12, 'RB' => 24, 'WR' => 24, 'TE' => 12 );
	const SOFT  = 8;   /* 1st to 8th in points allowed is a soft matchup */
	const TOUGH = 25;  /* 25th and below is a tough one */

	public static function file() { return DBD_Data::fantasy(); }
	public static function idx( $fm ) { return (int) array_search( $fm, self::FMT, true ); }
	public static function num( $v ) { return number_format( (float) $v, 1 ); }
	public static function ord( $n ) { return DBD_Grades::ordinal( (int) $n ); }

	/** A week's points in one format from the weekly stat line. */
	public static function pts( $w, $fm ) {
		$s = (float) ( $w['fantasy_points'] ?? 0 ); $p = (float) ( $w['fantasy_points_ppr'] ?? 0 );
		return $fm === 'std' ? $s : ( $fm === 'ppr' ? $p : ( $s + $p ) / 2 );
	}
	public static function boom( $pos, $fm ) { $f = self::file(); return (float) ( $f['boom'][ $pos ][ self::idx( $fm ) ] ?? self::BOOM[ $pos ][ self::idx( $fm ) ] ?? 0 ); }
	public static function bust( $pos, $fm ) { $f = self::file(); return (float) ( $f['bust'][ $pos ][ self::idx( $fm ) ] ?? self::BUST[ $pos ][ self::idx( $fm ) ] ?? 0 ); }
	public static function slot( $pos ) { $f = self::file(); return (int) ( $f['slot'][ $pos ] ?? self::SLOT[ $pos ] ?? 12 ); }
	public static function start( $pos, $fm ) { $f = self::file(); $v = $f['start'][ $pos ][ self::idx( $fm ) ] ?? null; return $v === null ? null : (float) $v; }
	public static function line( $v ) { return rtrim( rtrim( number_format( (float) $v, 1 ), '0' ), '.' ); }

	/** The format switch. Buttons carry data-fxset (not data-fx, which marks the blocks the switch shows and hides). */
	public static function seg( $extra = '' ) {
		$h = '<div class="dbd-seg t-tabs dbd-fxseg' . ( $extra ? ' ' . $extra : '' ) . '" role="tablist" aria-label="Fantasy scoring"><span class="t-tabs-pill" aria-hidden="true"></span>';
		foreach ( self::LABEL as $k => $lab ) { $h .= '<button class="t-tab" type="button" role="tab" data-fxset="' . $k . '" aria-selected="' . ( $k === 'half' ? 'true' : 'false' ) . '">' . $lab . '</button>'; }
		return $h . '</div>';
	}

	/** A defense's points allowed to a position: [value a game, rank (1 = most), of n]; null before the file exists. */
	public static function allowed( $def, $pos, $fm ) {
		$f = self::file(); $i = self::idx( $fm );
		if ( ! isset( $f['allow'][ $def ][ $pos ][ $i ], $f['rank'][ $def ][ $pos ][ $i ] ) ) { return null; }
		return array( (float) $f['allow'][ $def ][ $pos ][ $i ], (int) $f['rank'][ $def ][ $pos ][ $i ], count( $f['allow'] ) );
	}
	/** "3rd-most" or "5th-fewest" from a points-allowed rank. */
	public static function most( $rk, $n ) { return $rk <= $n / 2 ? self::ord( $rk ) . '-most' : self::ord( $n + 1 - $rk ) . '-fewest'; }
	public static function tier( $rk ) { return $rk <= self::SOFT ? 'soft' : ( $rk >= self::TOUGH ? 'tough' : '' ); }

	/** His season by league week: game (with the line), dnp where his team played without him, bye, or ahead. A traded player's weeks follow the team he was on. */
	public static function weeks( $p, $a ) {
		$by = array(); foreach ( (array) ( $p['weeks'] ?? array() ) as $w ) { $by[ (int) $w['week'] ] = $w; }
		$wl = DBD_Data::weeks(); $last = max( 18, $wl ? max( $wl ) : 18 );
		$tm = $a; foreach ( $by as $w ) { $tm = $w['team'] ?? $a; break; }
		$rows = array();
		for ( $wk = 1; $wk <= $last; $wk++ ) {
			if ( isset( $by[ $wk ] ) ) { $tm = $by[ $wk ]['team'] ?? $tm; $rows[ $wk ] = array( 'kind' => 'game', 'w' => $by[ $wk ] ); continue; }
			$g = DBD_Data::team_game( $tm, $wk );
			if ( ! $g ) { $rows[ $wk ] = array( 'kind' => 'bye' ); continue; }
			$fin = ( $g['status'] ?? '' ) === 'final' || isset( $g['home_score'] );
			$rows[ $wk ] = array( 'kind' => $fin ? 'dnp' : 'ahead', 'opp' => $g['home'] === $tm ? $g['away'] : $g['home'] );
		}
		return $rows;
	}

	/** Weekly bars for one format: green on a boom week, red on a bust, × for a miss, a dashed tick for the bye, a stub for weeks ahead; the dashed line is the starter line. */
	public static function bars( $rows, $pos, $fm ) {
		$teams = DBD_Data::teams(); $bt = self::boom( $pos, $fm ); $bs = self::bust( $pos, $fm ); $st = self::start( $pos, $fm );
		$vals = array(); foreach ( $rows as $r ) { if ( $r['kind'] === 'game' ) { $vals[] = self::pts( $r['w'], $fm ); } }
		$hi = max( array_merge( $vals, array( $bt, (float) $st, 1 ) ) ) * 1.08; $pc = function ( $v ) use ( $hi ) { return round( max( 0, $v ) / $hi * 100, 1 ) . '%'; };
		$cols = '';
		foreach ( $rows as $wk => $r ) {
			$lab = $wk % 3 === 1 ? '<i>' . $wk . '</i>' : '<i></i>';
			if ( $r['kind'] === 'game' ) {
				$v = self::pts( $r['w'], $fm ); $cl = $v >= $bt ? ' boom' : ( $v < $bs ? ' bust' : '' ); $opp = (string) ( $r['w']['opponent_team'] ?? '' );
				$tip = '<b>Week ' . $wk . ( $opp !== '' ? ' vs ' . esc_html( $teams[ $opp ]['nick'] ?? $opp ) : '' ) . '</b><br>' . self::num( $v ) . ' ' . self::WORD[ $fm ] . ' points' . ( $cl ? ( $cl === ' boom' ? ', a boom week' : ', a bust' ) : '' );
				$cols .= '<span class="c' . $cl . '"><b style="height:' . $pc( $v ) . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" aria-label="' . esc_attr( 'Week ' . $wk . ', ' . self::num( $v ) . ' points' ) . '"></b>' . $lab . '</span>';
			} elseif ( $r['kind'] === 'dnp' ) {
				$cols .= '<span class="c dnp"><b data-tip="' . esc_attr( '<b>Week ' . $wk . ' vs ' . esc_html( $teams[ $r['opp'] ]['nick'] ?? $r['opp'] ) . '</b><br>he did not play' ) . '" tabindex="0" aria-label="' . esc_attr( 'Week ' . $wk . ', did not play' ) . '">×</b>' . $lab . '</span>';
			} elseif ( $r['kind'] === 'bye' ) {
				$cols .= '<span class="c bye"><b data-tip="' . esc_attr( '<b>Week ' . $wk . '</b><br>bye' ) . '" tabindex="0" aria-label="' . esc_attr( 'Week ' . $wk . ', bye' ) . '"></b>' . $lab . '</span>';
			} else {
				$cols .= '<span class="c next"><b></b>' . $lab . '</span>';
			}
		}
		$ref = $st === null ? '' : '<span class="ref" style="bottom:' . $pc( $st ) . '"><em>' . esc_html( $pos . self::slot( $pos ) ) . ' · ' . self::num( $st ) . '</em></span>';
		return '<div class="dbd-fxbars"><div class="dbd-fxcols" role="img" aria-label="' . esc_attr( self::LABEL[ $fm ] . ' points by week' ) . '">' . $ref . $cols . '</div></div>';
	}

	/** His team's next game this season (the current week's, or the first one after it), with the opponent. */
	public static function next_game( $a ) {
		$cur = (int) DBD_Data::current_week(); $wl = DBD_Data::weeks(); $last = $wl ? max( $wl ) : 18;
		for ( $wk = max( 1, $cur ); $wk <= $last; $wk++ ) {
			$g = DBD_Data::team_game( $a, $wk ); if ( ! $g ) { continue; }
			if ( ( $g['status'] ?? '' ) === 'final' || isset( $g['home_score'] ) ) { continue; }
			return array( 'wk' => $wk, 'opp' => $g['home'] === $a ? $g['away'] : $g['home'], 'later' => $wk > $cur );
		}
		return null;
	}

	/**
	 * The player-page panel. Returns [panel html, short-version html], both empty when the player file has no fantasy block
	 * (older data, or a position the lens does not cover).
	 */
	public static function panel( $p, $a ) {
		$fan = $p['fan'] ?? null; $pos = $fan['pos'] ?? null;
		if ( ! $fan || ! isset( self::SLOT[ $pos ] ) ) { return array( '', '' ); }
		$teams = DBD_Data::teams(); $rows = self::weeks( $p, $a ); $nx = self::next_game( $a );
		$last = preg_replace( '/\s+(Jr\.?|Sr\.?|II|III|IV|V)$/', '', trim( (string) ( $p['name'] ?? '' ) ) ); $last = substr( $last, strrpos( ' ' . $last, ' ' ) );
		$last = trim( $last ) ?: ( $p['name'] ?? 'He' );
		$panes = ''; $short = '';
		foreach ( self::FMT as $fm ) {
			$s = $fan['s'][ $fm ] ?? null; $r = $fan['rk'][ $fm ] ?? array(); if ( ! $s ) { continue; }
			$bt = self::boom( $pos, $fm ); $bs = self::bust( $pos, $fm ); $W = self::WORD[ $fm ];
			$rank = ! empty( $r['ppg'] ) ? '<b>' . esc_html( $pos . (int) $r['ppg'] ) . '</b> of ' . (int) $r['n'] . ' ' . esc_html( $pos ) . 's with enough games' : 'not enough games to rank yet';
			$say = esc_html( $last ) . ' scores <b>' . self::num( $s['ppg'] ) . '</b> ' . $W . ' points a game, ' . $rank . '. ';
			if ( (int) $s['g'] >= 3 ) { $say .= 'Most weeks land between ' . self::num( $s['lo'] ) . ' and ' . self::num( $s['hi'] ) . '; '; }
			$say .= (int) $s['boom'] . ' boom week' . ( (int) $s['boom'] === 1 ? '' : 's' ) . ' (' . self::line( $bt ) . '+) and ' . (int) $s['bust'] . ' bust' . ( (int) $s['bust'] === 1 ? '' : 's' ) . ' (under ' . self::line( $bs ) . ').';
			if ( $nx && ( $al = self::allowed( $nx['opp'], $pos, $fm ) ) ) {
				$say .= ' ' . ( $nx['later'] ? 'Next up, in Week ' . (int) $nx['wk'] . ', the ' : 'This week the ' ) . esc_html( $teams[ $nx['opp'] ]['nick'] ?? $nx['opp'] ) . ' allow the <b>' . esc_html( self::most( $al[1], $al[2] ) ) . '</b> ' . $W . ' points to ' . esc_html( $pos ) . 's, ' . self::num( $al[0] ) . ' a game.';
			}
			$chips = array(
				array( 'Points a game', self::num( $s['ppg'] ), ! empty( $r['ppg'] ) ? $pos . (int) $r['ppg'] . ' of ' . (int) $r['n'] : 'not ranked yet' ),
				array( 'Season', self::num( $s['tot'] ), ( ! empty( $r['tot'] ) ? $pos . (int) $r['tot'] . ' · ' : '' ) . (int) $s['g'] . ' game' . ( (int) $s['g'] === 1 ? '' : 's' ) ),
				array( 'Most weeks', (int) $s['g'] >= 3 ? self::num( $s['lo'] ) . '–' . self::num( $s['hi'] ) : '–', (int) $s['g'] >= 3 ? 'his middle 60% of games' : 'after three games' ),
				array( 'Booms · busts', (int) $s['boom'] . ' · ' . (int) $s['bust'], self::line( $bt ) . '+ · under ' . self::line( $bs ) ),
			);
			$ch = ''; foreach ( $chips as $c ) { $ch .= '<div class="c"><small>' . esc_html( $c[0] ) . '</small><b>' . esc_html( $c[1] ) . '</b><span>' . esc_html( $c[2] ) . '</span></div>'; }
			$panes .= '<div data-fx="' . $fm . '"><p class="dbd-wp-say">' . $say . '</p><div class="dbd-wpchips">' . $ch . '</div>' . self::bars( $rows, $pos, $fm ) . '</div>';
			$short .= '<span data-fx="' . $fm . '"><b>' . self::num( $s['ppg'] ) . '</b> ' . $W . ' points a game' . ( ! empty( $r['ppg'] ) ? ', <b>' . esc_html( $pos . (int) $r['ppg'] ) . '</b> of ' . (int) $r['n'] : '' ) . '.</span>';
		}
		if ( $panes === '' ) { return array( '', '' ); }
		$note = 'Fantasy points are nflverse\'s standard and full-PPR scoring (6 points for a rushing or receiving touchdown, 4 for a passing touchdown, minus 2 for an interception or a lost fumble); half PPR is the average of the two. Ranks are points a game among players at his position with at least half the most games played. Most weeks is his 20th to 80th percentile game. Bars are green on a boom week and red on a bust; the dashed line is the starter line, the points a game of the ' . $pos . self::slot( $pos ) . ' in a 12-team league. The format you pick is remembered on this device.';
		$html = '<section class="dbd-panel dbd-fx" id="dbd-fantasy"><div class="dbd-ph"><span class="dbd-lab"><b>Fantasy</b> · points, rank, range and the next matchup</span>' . self::seg() . '</div>' . $panes . '<p class="dbd-note">' . esc_html( $note ) . '</p></section>';
		return array( $html, $short );
	}

	/** The Players index cell: points a game in every format with the position rank; data-fv lets the switch re-key the sort value. */
	public static function index_cell( $r ) {
		if ( empty( $r['fp'] ) ) { return '<td class="dbd-pfp" data-v="">–</td>'; }
		$h = ''; foreach ( self::FMT as $i => $fm ) { $rk = $r['fr'][ $i ] ?? null; $h .= '<span data-fx="' . $fm . '">' . self::num( $r['fp'][ $i ] ) . ( $rk ? '<small>' . esc_html( ( $r['fpos'] ?? '' ) . (int) $rk ) . '</small>' : '' ) . '</span>'; }
		return '<td class="dbd-pfp" data-fv="' . esc_attr( implode( ',', array_map( 'floatval', $r['fp'] ) ) ) . '" data-v="' . esc_attr( (string) (float) $r['fp'][1] ) . '">' . $h . '</td>';
	}

	/** One rank cell of the matchups table: every format, each with its own tint and tip. */
	private static function rank_cell( $def, $pos ) {
		$teams = DBD_Data::teams(); $h = '';
		foreach ( self::FMT as $fm ) {
			$al = self::allowed( $def, $pos, $fm ); if ( ! $al ) { $h .= '<span data-fx="' . $fm . '">–</span>'; continue; }
			$tip = '<b>' . esc_html( $teams[ $def ]['nick'] ?? $def ) . '</b> allow ' . self::num( $al[0] ) . ' ' . self::WORD[ $fm ] . ' points a game to ' . esc_html( $pos ) . 's<br>' . esc_html( self::ord( $al[1] ) ) . '-most of ' . (int) $al[2];
			$h .= '<span data-fx="' . $fm . '"><span class="dbd-fxr ' . self::tier( $al[1] ) . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0">' . esc_html( self::ord( $al[1] ) ) . '</span></span>';
		}
		return '<td>' . $h . '</td>';
	}

	/** The Matchups page's fantasy grid: each offense left to play this week against its opponent's points-allowed rank by position. */
	public static function matchups( $games, $label ) {
		$f = self::file(); if ( ! $f || empty( $f['allow'] ) ) { return ''; }
		$teams = DBD_Data::teams(); $rows = '';
		foreach ( $games as $g ) {
			if ( DBD_Router::is_final( $g ) ) { continue; }
			foreach ( array( array( $g['away'], $g['home'] ), array( $g['home'], $g['away'] ) ) as $pr ) {
				list( $off, $def ) = $pr;
				$rows .= '<tr><td class="nm"><b>' . esc_html( $off ) . '</b><i>vs</i>' . esc_html( $def ) . ' <span class="full">' . esc_html( $teams[ $def ]['nick'] ?? '' ) . '</span></td>';
				foreach ( array_keys( self::SLOT ) as $pos ) { $rows .= self::rank_cell( $def, $pos ); }
				$rows .= '</tr>';
			}
		}
		if ( $rows === '' ) { return ''; }
		return '<section class="dbd-panel dbd-fxm" id="dbd-fxm"><div class="dbd-ph"><span class="dbd-lab"><b>Fantasy matchups</b> · ' . esc_html( $label ) . ': where each defense ranks in points allowed, by position</span>' . self::seg() . '</div>'
			. '<div class="dbd-tw"><table class="dbd-stat dbd-fxmt"><thead><tr><th>Offense</th><th>QB</th><th>RB</th><th>WR</th><th>TE</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
			. '<div class="dbd-legend"><span><i class="sq soft"></i>1st to ' . self::ord( self::SOFT ) . ', a soft matchup</span><span><i class="sq tough"></i>' . self::ord( self::TOUGH ) . ' to ' . self::ord( count( $f['allow'] ) ) . ', a tough one</span><span>1st allows the most</span></div>'
			. '<p class="dbd-note">Points allowed are every player at the position the defense has faced this season, per game. The format you pick is remembered on this device.</p></section>';
	}

	/** Compare (SCOPE §34): the four numbers side by side and the weeks grouped, per format. $P is compare.php's slot list. */
	public static function compare( $P ) {
		$any = false; foreach ( $P as $x ) { if ( ! empty( $x['rec']['fan'] ) ) { $any = true; } } if ( ! $any ) { return ''; }
		$wl = DBD_Data::weeks(); $last = max( 1, (int) DBD_Data::through_week() ); $panes = '';
		foreach ( self::FMT as $fm ) {
			$cells = array(); $vals = array();
			foreach ( $P as $i => $x ) {
				$fan = $x['rec']['fan'] ?? null; $s = $fan['s'][ $fm ] ?? null; $r = $fan['rk'][ $fm ] ?? array(); $pos = $fan['pos'] ?? '';
				$cells[ $i ] = $s ? array(
					array( (float) $s['ppg'], self::num( $s['ppg'] ), ! empty( $r['ppg'] ) ? $pos . (int) $r['ppg'] : '' ),
					array( (float) $s['tot'], self::num( $s['tot'] ), ! empty( $r['tot'] ) ? $pos . (int) $r['tot'] : '' ),
					array( null, (int) $s['g'] >= 3 ? self::num( $s['lo'] ) . '–' . self::num( $s['hi'] ) : '–', '' ),
					array( null, (int) $s['boom'] . ' · ' . (int) $s['bust'], self::line( self::boom( $pos, $fm ) ) . '+ · under ' . self::line( self::bust( $pos, $fm ) ) ),
				) : null;
				$wk = array(); foreach ( (array) ( $x['rec']['weeks'] ?? array() ) as $w ) { $wk[ (int) $w['week'] ] = self::pts( $w, $fm ); } $vals[ $i ] = $wk;
			}
			$tb = '';
			foreach ( array( 'Points a game', 'Season', 'Most weeks', 'Booms · busts' ) as $ri => $lab ) {
				$best = null; $bi = -1; foreach ( $cells as $i => $c ) { if ( $c && $c[ $ri ][0] !== null && ( $best === null || $c[ $ri ][0] > $best ) ) { $best = $c[ $ri ][0]; $bi = $i; } }
				$ties = 0; foreach ( $cells as $c ) { if ( $c && $best !== null && $c[ $ri ][0] === $best ) { $ties++; } }
				$tb .= '<tr><td>' . esc_html( $lab ) . '</td>';
				foreach ( $cells as $i => $c ) { $tb .= $c ? '<td' . ( $i === $bi && $ties === 1 ? ' class="b"' : '' ) . '>' . esc_html( $c[ $ri ][1] ) . ( $c[ $ri ][2] !== '' ? '<small>' . esc_html( $c[ $ri ][2] ) . '</small>' : '' ) . '</td>' : '<td>–</td>'; }
				$tb .= '</tr>';
			}
			$hi = 1; foreach ( $vals as $wk ) { foreach ( $wk as $v ) { $hi = max( $hi, $v ); } } $hi *= 1.08;
			$grp = '';
			for ( $wk = 1; $wk <= $last; $wk++ ) {
				$bars = ''; foreach ( $P as $i => $x ) { $v = $vals[ $i ][ $wk ] ?? null;
					$bars .= $v === null ? '<b class="none" style="--c:' . $x['c'] . '"></b>' : '<b style="--c:' . $x['c'] . ';height:' . round( max( 0, $v ) / $hi * 100, 1 ) . '%" data-tip="' . esc_attr( '<b>' . esc_html( $x['row']['name'] ) . '</b><br>Week ' . $wk . ': ' . self::num( $v ) . ' ' . self::WORD[ $fm ] . ' points' ) . '" tabindex="0"></b>'; }
				$grp .= '<span class="g">' . $bars . '<i>' . ( $wk % 3 === 1 ? $wk : '' ) . '</i></span>';
			}
			$head = ''; foreach ( $P as $x ) { $head .= '<th style="--c:' . $x['c'] . '"><span class="chip">' . esc_html( $x['rec']['short'] ?? $x['row']['name'] ) . '</span></th>'; }
			$panes .= '<div data-fx="' . $fm . '"><div class="dbd-tw"><table class="dbd-sbs"><thead><tr><th>' . esc_html( self::LABEL[ $fm ] ) . '</th>' . $head . '</tr></thead><tbody>' . $tb . '</tbody></table></div>'
				. '<div class="dbd-pb"><div class="dbd-fxgrp" role="img" aria-label="' . esc_attr( self::LABEL[ $fm ] . ' points by week, grouped' ) . '">' . $grp . '</div></div></div>';
		}
		return '<section class="dbd-panel dbd-fx dbd-cfx" id="dbd-cfantasy"><div class="dbd-ph"><span class="dbd-lab"><b>Fantasy</b> · points, rank and range, week by week</span>' . self::seg() . '</div>' . $panes
			. '<p class="dbd-note">Ranks are among players at each one\'s own position. A missing bar is a week he did not score: a bye or a missed game. The format you pick is remembered on this device.</p></section>';
	}
}
