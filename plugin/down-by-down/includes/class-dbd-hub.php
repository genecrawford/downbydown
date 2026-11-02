<?php
/**
 * The hub refresh (SCOPE §41): the bands under the score strip. The week so far (from the week in review), a card per game still to
 * play, the players row (movers, fantasy leaders, My team) and the standings with the playoff picture. All from published data.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Hub {

	const MAX_CARDS = 6;  /* early in the week every game is still to play: the six biggest edges, then a link to the slate */

	private static function nick( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; }
	private static function mk( $a, $s = 22 ) { return '<span class="wk-mk">' . DBD_Marks::tile( $a, $s ) . '</span>'; }

	/* ------------------------------------------------------------ the week so far, or last week */
	public static function week_band( $wk ) {
		$games = DBD_Data::week_games( $wk );
		$rn = count( array_filter( $games, array( 'DBD_Router', 'is_final' ) ) ) ? $wk : $wk - 1;
		$W = $rn > 0 ? DBD_Week::file( $rn ) : null; if ( ! $W ) { return ''; }
		$fin = array_values( array_filter( $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final' && isset( $g['pre'] ); } ) ); if ( ! $fin ) { return ''; }
		$score = function ( $g ) { return max( (int) $g['a'], (int) $g['h'] ) . '–' . min( (int) $g['a'], (int) $g['h'] ); };
		$loser = function ( $g ) { return $g['win'] === $g['away'] ? $g['home'] : $g['away']; };
		$won = array_values( array_filter( $fin, function ( $g ) { return ! empty( $g['win'] ); } ) );
		$up = array_values( array_filter( $won, function ( $g ) { return $g['pre_w'] < 500; } ) ); usort( $up, function ( $a, $b ) { return $a['pre_w'] <=> $b['pre_w']; } ); $upg = $up[0] ?? null;
		$cb = $won; usort( $cb, function ( $a, $b ) { return $a['low'] <=> $b['low']; } ); $cbg = $cb[0] ?? null;
		$wd = $fin; usort( $wd, function ( $a, $b ) { return array( $b['lc'] ?? 0, $b['drama'] ?? 0 ) <=> array( $a['lc'] ?? 0, $a['drama'] ?? 0 ); } );
		$lines = array();
		$far = $cbg && $cbg['low'] < 300;  /* a comeback is a winner who fell below 30%, as the week page says it */
		if ( $upg && $far && $upg['id'] === $cbg['id'] ) {  /* one game was both: say it once, and make room for the wildest */
			$lines[] = array( 'Upset', '<b>' . esc_html( self::nick( $upg['win'] ) ) . '</b> over the ' . esc_html( self::nick( $loser( $upg ) ) ) . ', ' . $score( $upg ) . ': a ' . round( $upg['pre_w'] / 10 ) . '% underdog at kickoff, and down to ' . max( 1, round( $upg['low'] / 10 ) ) . '% before winning.', $upg['id'] );
			$wg = null; foreach ( $wd as $g ) { if ( $g['id'] !== $upg['id'] ) { $wg = $g; break; } }
			if ( $wg ) { $lc = (int) ( $wg['lc'] ?? 0 ); $lines[] = array( 'Wildest', '<b>' . esc_html( self::nick( $wg['away'] ) . ' at ' . self::nick( $wg['home'] ) ) . '</b>: the lead changed hands ' . $lc . ' time' . ( $lc === 1 ? '' : 's' ) . '; ' . ( ! empty( $wg['win'] ) ? 'the ' . esc_html( self::nick( $wg['win'] ) ) . ' won ' . $score( $wg ) . '.' : 'it ended tied.' ), $wg['id'] ); }
		} else {
			if ( $upg ) { $lines[] = array( 'Upset', '<b>' . esc_html( self::nick( $upg['win'] ) ) . '</b> over the ' . esc_html( self::nick( $loser( $upg ) ) ) . ', ' . $score( $upg ) . ', as a ' . round( $upg['pre_w'] / 10 ) . '% underdog.', $upg['id'] ); }
			if ( $far ) { $lines[] = array( 'Comeback', '<b>' . esc_html( self::nick( $cbg['win'] ) ) . '</b> back from ' . max( 1, round( $cbg['low'] / 10 ) ) . '% against the ' . esc_html( self::nick( $loser( $cbg ) ) ) . ', ' . $score( $cbg ) . '.', $cbg['id'] ); }
			elseif ( $cbg ) { $lines[] = array( 'Comeback', 'Nobody came back from far: the lowest any winner fell was ' . round( $cbg['low'] / 10 ) . '% (the ' . esc_html( self::nick( $cbg['win'] ) ) . ').', $cbg['id'] ); }
		}
		$star = null; foreach ( (array) ( $W['players'] ?? array() ) as $rows ) { foreach ( (array) $rows as $r ) { if ( ! $star || $r['e'] > $star['e'] ) { $star = $r; } } }
		if ( $star ) { $lines[] = array( 'Player', '<b>' . esc_html( $star['n'] ) . '</b> (' . esc_html( $star['t'] . ' ' . $star['pos'] ) . '): ' . ( $star['e'] > 0 ? '+' : '' ) . number_format( (float) $star['e'], 1 ) . ' EPA, ' . esc_html( $star['x'] ) . '.', null ); }
		$say = ''; foreach ( $lines as $l ) { $say .= '<li><span>' . esc_html( $l[0] ) . '</span><p>' . $l[1] . '</p></li>'; }
		$sw = ''; foreach ( array_slice( (array) ( $W['swings'] ?? array() ), 0, 3 ) as $i => $x ) {
			$sw .= '<li><span class="rk">' . ( $i + 1 ) . '</span><span class="wk-pd"><span class="wk-pm">' . self::mk( $x['t'], 16 ) . '<b>' . esc_html( self::nick( $x['t'] ) ) . '</b> · ' . esc_html( $x['c'] ) . ' · <a href="' . esc_url( DBD_Router::url( array( 'game' => $x['g'] ) ) ) . '">the game</a></span><span class="wk-pt">' . esc_html( $x['d'] ) . '</span></span><span class="wk-pv"><b>+' . round( $x['v'] ) . '</b><small>' . (int) $x['ba'] . '% → ' . min( 100, (int) round( $x['ba'] + $x['v'] ) ) . '%</small></span></li>'; }
		$done = ! empty( $W['complete'] ); $lab = DBD_Router::week_label( $rn );
		return '<section class="dbd-band" id="hb-week"><h2 class="dbd-bandh">' . ( $done && $rn < $wk ? 'Last week' : 'The week so far' ) . '<span>' . esc_html( $lab ) . ': ' . (int) $W['fin'] . ' of ' . (int) $W['n'] . ' games final</span></h2>'
			. '<section class="dbd-panel hb-week"><div class="hb-week2"><div><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b>' . ( $done ? '' : ' · so far' ) . '</span></div><ul class="hb-say">' . $say . '</ul></div>'
			. '<div><div class="dbd-ph"><span class="dbd-lab"><b>The biggest swings</b></span></div><ol class="ld-list wk-pl">' . ( $sw ?: '<li class="none">No swings yet.</li>' ) . '</ol></div></div>'
			. '<a class="hb-more" href="' . esc_url( DBD_Router::url( array( 'view' => 'week', 'week' => $rn ) ) ) . '">' . esc_html( $lab ) . ' in review: every game, the best plays, teams and players →</a></section></section>';
	}

	/* ------------------------------------------------------------ still to play */
	private static function fx_line( $g ) {
		$f = DBD_Fantasy::file(); $best = null;
		foreach ( array( array( $g['away'], $g['home'] ), array( $g['home'], $g['away'] ) ) as $pr ) {
			list( $O, $D ) = $pr;
			foreach ( array( 'QB', 'RB', 'WR', 'TE' ) as $pos ) {
				$rk = (int) ( $f['rank'][ $D ][ $pos ][1] ?? 0 );  /* half PPR */
				if ( $rk && ( ! $best || $rk < $best[0] ) ) { $best = array( $rk, $O, $D, $pos ); }
			}
		}
		if ( ! $best ) { return ''; }
		return '<div class="hb-fx"><i>Fantasy</i> <b>' . esc_html( self::nick( $best[1] ) . ' ' . $best[3] . 's' ) . '</b>: the ' . esc_html( self::nick( $best[2] ) ) . ' allow the ' . esc_html( DBD_Grades::ordinal( $best[0] ) ) . '-most ' . esc_html( $best[3] ) . ' points</div>';
	}

	public static function still_to_play( $wk, $games, $skip_id ) {
		$left = array_values( array_filter( $games, function ( $g ) use ( $skip_id ) { return ! DBD_Router::is_final( $g ) && $g['id'] !== $skip_id; } ) );
		if ( ! $left ) { return ''; }
		$ranked = DBD_Data::through_week() > 0; $edge = array();
		foreach ( $left as $g ) { $edge[ $g['id'] ] = $ranked ? DBD_Takeaways::best_edge( $g['away'], $g['home'] ) : null; }
		$shown = $left; $more = 0;
		if ( count( $left ) > self::MAX_CARDS ) {
			usort( $shown, function ( $a, $b ) use ( $edge ) { return ( $edge[ $b['id'] ]['s'] ?? 0 ) <=> ( $edge[ $a['id'] ]['s'] ?? 0 ); } );
			$shown = array_slice( $shown, 0, self::MAX_CARDS ); $more = count( $left ) - self::MAX_CARDS;
			usort( $shown, function ( $a, $b ) { return strcmp( ( $a['date'] ?? '' ) . ( $a['time'] ?? '' ), ( $b['date'] ?? '' ) . ( $b['time'] ?? '' ) ); } );
		}
		$cards = '';
		foreach ( $shown as $g ) {
			$ln = DBD_Router::line_text( $g ); $tot = isset( $g['total'] ) && $g['total'] !== null && $g['total'] !== '' ? 'O/U ' . $g['total'] : '';
			$rows = '';
			foreach ( array( $g['away'], $g['home'] ) as $a ) {
				$rows .= '<div class="hb-tm">' . self::mk( $a, 24 ) . '<b>' . esc_html( self::nick( $a ) ) . '</b><small>' . esc_html( DBD_Data::record_text( $a ) ) . '</small>'
					. ( $ranked ? '<span>OFF ' . esc_html( DBD_Grades::ordinal( (int) DBD_Data::rank( 'off', $a ) ) ) . ' · DEF ' . esc_html( DBD_Grades::ordinal( (int) DBD_Data::rank( 'def', $a ) ) ) . '</span>' : '<span></span>' ) . '</div>';
			}
			$e = ''; $b = $edge[ $g['id'] ];
			if ( $b ) { $c = DBD_Takeaways::cards( $b['O'], $b['D'] ); if ( $c ) { $e = '<div class="hb-edge"><i>Biggest edge · ' . esc_html( self::nick( $b['O'] ) ) . ' offense</i><b>' . esc_html( $c[0][2] ) . '</b><p>' . esc_html( $c[0][3] ) . '</p></div>'; } }
			$side = ( $b && $b['O'] === $g['home'] ) ? 'home' : 'away';
			$cards .= '<a class="hb-g" href="' . esc_url( DBD_Router::url( array( 'game' => $g['id'], 'side' => $side ) ) ) . '"><div class="hb-gh"><span>' . esc_html( (string) ( $g['day'] ?? '' ) ) . ' · ' . DBD_Router::kickoff_html( $g ) . '</span><span>' . esc_html( implode( ' · ', array_filter( array( $ln, $tot ) ) ) ) . '</span></div>'
				. $rows . $e . self::fx_line( $g ) . '<span class="hb-open">Open the matchup →</span></a>';
		}
		$lab = DBD_Router::week_label( $wk );
		return '<section class="dbd-band" id="hb-next"><h2 class="dbd-bandh">Still to play<span>' . esc_html( $lab ) . ( $skip_id ? ': the games left after the game of the week' : ': the games left' ) . '</span></h2><div class="hb-grid">' . $cards . '</div>'
			. ( $more ? '<p class="hb-all"><a href="' . esc_url( DBD_Router::url( array( 'view' => 'matchups', 'week' => $wk ) ) ) . '">' . (int) $more . ' more game' . ( $more === 1 ? '' : 's' ) . ' on the matchups page →</a></p>' : '' )
			. '<p class="dbd-note hb-note">Ranks are EPA a play this season, 1 to 32. The edge is the matchup\'s biggest gap between one offense and the other defense, as on the game page; the fantasy line is the softest position matchup in the game, in half-PPR points allowed.' . ( $more ? ' Early in the week the six games with the biggest edges show here.' : '' ) . '</p></section>';
	}

	/* ------------------------------------------------------------ players: movers, fantasy leaders, My team */
	public static function players_row( $wk ) {
		$cards = '';
		$MV = DBD_Data::get( 'players/movers.json' );
		if ( $MV && ! empty( $MV['week'] ) ) {
			$row = function ( $r, $up ) {
				return '<li><span class="rk">' . ( $up ? '▲' : '▼' ) . '</span><span class="who">' . DBD_Marks::player( $r['team'], $r['num'], 24, $r['photo'] ?? null ) . '<span class="nm"><a href="' . esc_url( DBD_Router::url( array( 'player' => $r['id'] ) ) ) . '">' . esc_html( $r['name'] ) . '</a><small>' . esc_html( $r['team'] . ' · ' . $r['pos'] . ' · ' . round( $r['before'] ) . '% → ' . round( $r['now'] ) . '%' ) . '</small></span></span><b class="v ' . ( $up ? 'up' : 'dn' ) . '">' . ( $up ? '+' : '−' ) . round( abs( (float) $r['delta'] ) ) . '</b></li>';
			};
			$li = ''; foreach ( array_slice( (array) ( $MV['snaps']['up'] ?? array() ), 0, 4 ) as $r ) { $li .= $row( $r, true ); }
			foreach ( array_slice( (array) ( $MV['snaps']['down'] ?? array() ), 0, 4 ) as $r ) { $li .= $row( $r, false ); }
			if ( $li ) { $cards .= '<section class="dbd-panel ld-card"><div class="ld-h"><b>Movers · Week ' . (int) $MV['week'] . '</b><small>the biggest swings in snap share, against his own season</small></div><ol class="ld-list hb-mvl">' . $li . '</ol><a class="hb-more" href="' . esc_url( DBD_Router::url( array( 'view' => 'players' ) ) . '#dbd-mv' ) . '">All movers →</a></section>'; }
		}
		$L = DBD_Data::get( 'players/leaders.json' );
		if ( $L && ! empty( $L['fan'] ) ) {
			$fx = '';
			foreach ( DBD_Fantasy::FMT as $fm ) {
				$li = '';
				foreach ( array( 'QB', 'RB', 'WR', 'TE' ) as $pos ) {
					foreach ( array_slice( (array) ( $L['fan'][ $fm ][ 'ppg.' . $pos ] ?? array() ), 0, 2 ) as $r ) {
						$p = $L['players'][ $r[0] ] ?? null; if ( ! $p ) { continue; }  /* [name, team, pos, num, photo], the leaders file's own map */
						$li .= '<li><span class="rk">' . $pos . '</span><span class="who">' . ( $p[1] ? DBD_Marks::player( $p[1], $p[3] ?? '', 24, $p[4] ?? null ) : '' ) . '<span class="nm"><a href="' . esc_url( DBD_Router::url( array( 'player' => $r[0] ) ) ) . '">' . esc_html( $p[0] ) . '</a><small>' . esc_html( ( $p[1] ?: '–' ) . ' · ' . (int) $r[2] . ' games' ) . '</small></span></span><b class="v">' . number_format( (float) $r[1], 1 ) . '</b></li>';
					}
				}
				$fx .= '<div data-fx="' . esc_attr( $fm ) . '"><ol class="ld-list hb-fxl">' . ( $li ?: '<li class="none">No leaders yet.</li>' ) . '</ol></div>';
			}
			$cards .= '<section class="dbd-panel ld-card"><div class="ld-h wk-fxh"><span><b>Fantasy leaders</b><small>points a game, the top two at each position</small></span>' . DBD_Fantasy::seg() . '</div>' . $fx . '<a class="hb-more" href="' . esc_url( DBD_Router::url( array( 'view' => 'leaders' ) ) ) . '">All the boards →</a></section>';
		}
		/* My team: the empty card; hub.js swaps in the saved lineup from this device */
		$cards .= '<section class="dbd-panel ld-card hb-mt" id="hb-mt"><div class="ld-h"><b>My team</b><small class="hb-mts">your lineup, kept on this device</small></div>'
			. '<div class="hb-mte"><p>Pick your players once and see them together every week: this week\'s matchup, status, snap trend and points pace.</p><a class="dbd-cta" href="' . esc_url( DBD_Router::url( array( 'view' => 'myteam' ) ) ) . '">Build my team</a></div></section>';
		return '<section class="dbd-band" id="hb-players"><h2 class="dbd-bandh">Players<span>whose role moved, who leads, and your team</span></h2><div class="ld-grid hb-players">' . $cards . '</div></section>';
	}

	/** This week's opponent per team for the My team card: [at|vs, opponent, final]. */
	public static function opponents( $wk ) {
		$o = array();
		foreach ( DBD_Data::week_games( $wk ) as $g ) {
			$f = DBD_Router::is_final( $g );
			$o[ $g['away'] ] = array( 'at', $g['home'], $f ); $o[ $g['home'] ] = array( 'vs', $g['away'], $f );
		}
		return $o;
	}

	/* ------------------------------------------------------------ standings: the playoff picture and the divisions */
	private static function diffs() {
		$d = array_fill_keys( array_keys( DBD_Data::teams() ), 0 );
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) {
			foreach ( $gl as $g ) {
				if ( ! DBD_Router::is_final( $g ) || ( $g['game_type'] ?? 'REG' ) !== 'REG' || ! isset( $d[ $g['home'] ], $d[ $g['away'] ] ) ) { continue; }
				$d[ $g['home'] ] += $g['home_score'] - $g['away_score']; $d[ $g['away'] ] += $g['away_score'] - $g['home_score'];
			}
		}
		return $d;
	}

	/** Seeds 1 to 7 by record then point differential (the NFL's tiebreakers are not applied), the hunt within a game of the 7th, and the rest. */
	public static function picture( $conf ) {
		$teams = DBD_Data::teams(); $rec = DBD_Data::records(); $pd = self::diffs();
		$key = function ( $a ) use ( $pd ) { return array( DBD_Data::win_pct( $a ), $pd[ $a ] ?? 0 ); };
		$cmp = function ( $a, $b ) use ( $key ) { return $key( $b ) <=> $key( $a ); };
		$in = array_keys( array_filter( $teams, function ( $t ) use ( $conf ) { return ( $t['conf'] ?? '' ) === $conf; } ) );
		if ( ! $in ) { return ''; }
		$lead = array();
		foreach ( array( 'East', 'North', 'South', 'West' ) as $d ) {
			$dv = array_values( array_filter( $in, function ( $a ) use ( $teams, $conf, $d ) { return ( $teams[ $a ]['div'] ?? '' ) === "$conf $d"; } ) );
			if ( $dv ) { usort( $dv, $cmp ); $lead[] = $dv[0]; }
		}
		usort( $lead, $cmp );
		$rest = array_values( array_diff( $in, $lead ) ); usort( $rest, $cmp );
		$wc = array_slice( $rest, 0, 3 ); $seven = end( $wc );
		$gb = function ( $a ) use ( $rec, $seven ) { return ( ( $rec[ $seven ][0] - $rec[ $a ][0] ) + ( $rec[ $a ][1] - $rec[ $seven ][1] ) ) / 2; };
		$gbt = function ( $x ) { if ( $x <= 0 ) { return 'tied with the 7th seed'; } $w = floor( $x ); return ( $w ? (int) $w : '' ) . ( $x - $w ? '½' : '' ) . ( $x <= 1 ? ' game' : ' games' ) . ' back'; };
		$hunt = array(); $out = array();
		foreach ( array_slice( $rest, 3 ) as $a ) { if ( $gb( $a ) <= 1 ) { $hunt[] = $a; } else { $out[] = $a; } }
		$row = function ( $seed, $a, $tag ) use ( $teams, $pd ) {
			$href = DBD_Router::url( array( 'team' => $a ) ); $d = (int) ( $pd[ $a ] ?? 0 );
			return '<tr><td class="sd">' . esc_html( (string) $seed ) . '</td><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::tile( $a, 24 ) . '<a href="' . esc_url( $href ) . '"><span class="full">' . esc_html( $teams[ $a ]['name'] ) . '</span><span class="short">' . esc_html( $teams[ $a ]['nick'] ) . '</span></a><small>' . esc_html( $tag ) . '</small></span></td>'
				. '<td class="dbd-rec">' . esc_html( DBD_Data::record_text( $a ) ) . '</td><td class="pd ' . ( $d > 0 ? 'pos' : ( $d < 0 ? 'neg' : '' ) ) . '">' . ( $d > 0 ? '+' : '' ) . $d . '</td></tr>';
		};
		$b = '';
		foreach ( $lead as $i => $a ) { $b .= $row( $i + 1, $a, preg_replace( '/^\S+\s/', '', $teams[ $a ]['div'] ?? '' ) . ' leader' ); }
		foreach ( $wc as $i => $a ) { $b .= $row( $i + 5, $a, 'wild card' ); }
		$b .= '<tr class="dbd-dv"><td colspan="4">In the hunt · within one game of the 7th seed</td></tr>';
		if ( $hunt ) { foreach ( $hunt as $a ) { $b .= $row( '', $a, $gbt( $gb( $a ) ) ); } } else { $b .= '<tr><td colspan="4" class="dbd-note">Nobody within a game.</td></tr>'; }
		return '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $conf ) . '</b> · if the season ended today</span><span class="dbd-badge">Seeds 1 to 7</span></div>'
			. '<div class="dbd-tw"><table class="dbd-stand hb-pic"><thead><tr><th>Seed</th><th>Team</th><th>Record</th><th>Diff</th></tr></thead><tbody>' . $b . '</tbody></table></div>'
			. ( $out ? '<p class="hb-out">Outside: ' . esc_html( implode( ', ', array_map( function ( $a ) { return self::nick( $a ) . ' ' . DBD_Data::record_text( $a ); }, $out ) ) ) . '</p>' : '' ) . '</section>';
	}

	/** The standings band: Playoff picture (default) and Divisions (today's tables). Before Week 1 only the divisions show. */
	public static function standings( $divisions_html ) {
		$played = array_sum( array_map( function ( $r ) { return $r[0] + $r[1] + $r[2]; }, DBD_Data::records() ) ) > 0;
		if ( ! $played ) { return '<section class="dbd-band" id="hb-stand">' . $divisions_html . '</section>'; }
		$tabs = '<div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" aria-selected="true" data-pane="0">Playoff picture</button><button class="t-tab" type="button" role="tab" aria-selected="false" data-pane="1">Divisions</button></div></div>';
		return '<section class="dbd-band" id="hb-stand"><h2 class="dbd-bandh">Standings<span>the playoff picture today, and every division</span></h2><section class="dbd-panel dbd-tabbed hb-stand">' . $tabs
			. '<section class="dbd-pane" data-pane="0"><div class="dbd-two">' . self::picture( 'AFC' ) . self::picture( 'NFC' ) . '</div><p class="dbd-note">Seeds go by record, then point differential: the four division leaders first, then the three best records left. The NFL\'s own tiebreakers (head to head, division and conference record) are not applied, so a tie on record can land the other way. Diff is points scored minus allowed.</p></section>'
			. '<section class="dbd-pane" data-pane="1" hidden>' . $divisions_html . '</section></section></section>';
	}
}
