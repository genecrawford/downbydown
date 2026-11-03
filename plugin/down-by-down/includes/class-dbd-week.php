<?php
/**
 * The week in review (SCOPE §40): ?view=week&week=N, drawn from week/w{N}.json. The build writes one file per week with a final
 * game; everything on the page (the short version, the scoreboard, the plays, the teams, the players, the movers) comes from it.
 * The movers panel is shared with the Players page, which draws the latest complete week from players/movers.json.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Week {

	/** Weeks with at least one final game, ascending. */
	public static function weeks() {
		$out = array();
		foreach ( DBD_Data::weeks() as $w ) {
			foreach ( DBD_Data::week_games( $w ) as $g ) { if ( DBD_Router::is_final( $g ) ) { $out[] = $w; break; } }
		}
		return $out;
	}

	/** The most recent week with a final game, so a Sunday night shows that Sunday. */
	/**
	 * The week the home's blocks feature (the most-targeted player, the week's highlights): the latest week once at least half of its
	 * games are final, else the week before. A Thursday night game alone doesn't take over the home until Sunday's games are in.
	 */
	/** The published weekly post for this season's week n (SCOPE §46), for the link back from the week page; never for an archived season. */
	private static function writeup( $n ) {
		if ( ! class_exists( 'DBD_Post' ) || ! function_exists( 'get_posts' ) || DBD_Data::archived() ) { return ''; }
		return (string) DBD_Post::url_for( $n );
	}

	public static function settled_week() {
		$ws = self::weeks(); if ( ! $ws ) { return 0; }
		$last = max( $ws ); $g = DBD_Data::week_games( $last );
		$fin = count( array_filter( $g, array( 'DBD_Router', 'is_final' ) ) );
		if ( $fin * 2 >= count( $g ) ) { return $last; }
		$prev = array_filter( $ws, function ( $w ) use ( $last ) { return $w < $last; } );
		return $prev ? max( $prev ) : $last;  /* a season's first week has no week before it */
	}

	public static function default_week() {
		$w = self::weeks();
		return $w ? max( $w ) : 0;
	}

	public static function file( $n ) {
		$f = DBD_Data::get( 'week/w' . (int) $n . '.json' );
		return ( $f && ! empty( $f['games'] ) ) ? $f : null;
	}

	private static function nick( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; }
	private static function sgn( $v, $k = 2 ) { $v = (float) $v; return ( $v > 0 ? '+' : ( $v < 0 ? '−' : '' ) ) . number_format( abs( $v ), $k ); }
	private static function mk( $a, $s = 18 ) { return '<span class="wk-mk">' . DBD_Marks::tile( $a, $s ) . '</span>'; }
	private static function game_url( $id ) { return DBD_Router::url( array( 'game' => $id ) ); }
	private static function score( $g ) { return max( (int) $g['a'], (int) $g['h'] ) . '–' . min( (int) $g['a'], (int) $g['h'] ); }
	private static function loser( $g ) { return $g['win'] === $g['away'] ? $g['home'] : $g['away']; }

	/** The whole page body for week $n (below the top bar). */
	public static function page( $n ) {
		$W = self::file( $n );
		$weeks = self::weeks();
		$h = self::title( $n, $W ) . self::picker( $n, $weeks );
		if ( ! $W ) {
			return $h . '<section class="dbd-panel"><div class="dbd-empty"><h2>Week ' . (int) $n . ' isn\'t in yet</h2><p>The week in review fills in with the next nightly data build after its first game is final.</p></div></section>';
		}
		$done = ! empty( $W['complete'] );
		$h .= '<nav class="dbd-jump" aria-label="On this page"><a href="#wk-games">The games</a><a href="#wk-plays">The plays</a><a href="#wk-teams">Teams</a><a href="#wk-players">Players</a>' . ( $done ? '<a href="#wk-movers">Movers</a>' : '' ) . '</nav>';
		return $h . self::short( $W ) . self::games( $W ) . self::plays( $W ) . self::teams( $W ) . self::players( $W ) . self::movers_band( $W );
	}

	private static function title( $n, $W ) {
		$season = DBD_Data::season(); $games = DBD_Data::week_games( $n );
		$dates = array_filter( array_map( function ( $g ) { return $g['date'] ?? ''; }, $games ) ); sort( $dates );
		$fd = function ( $d ) { $t = strtotime( $d ); return $t ? gmdate( 'M j', $t ) : ''; };
		$fin = count( array_filter( $games, array( 'DBD_Router', 'is_final' ) ) ); $all = count( $games );
		$k = $season . ' season' . ( $dates ? ' · ' . $fd( reset( $dates ) ) . ' to ' . $fd( end( $dates ) ) : '' ) . ' · ' . $all . ' games' . ( ! empty( $W['byes'] ) ? ' · byes: ' . implode( ', ', $W['byes'] ) : '' );
		$sub = 'What happened this week: every game\'s swings in one strip, the plays that decided them, the offenses and defenses that had their day, the players who carried them' . ( $W && ! empty( $W['complete'] ) ? ', and whose role moved' : '' ) . '.';
		return '<div class="dbd-ttitle"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( $k ) . '</p><h1 class="t-stagger-line t-stagger-line--2"><span>' . esc_html( DBD_Router::week_label( $n ) ) . '</span> in review</h1>'
			. '<p class="dbd-sub t-stagger-line t-stagger-line--3">' . esc_html( $sub ) . ( ( $wu = self::writeup( $n ) ) !== '' ? ' <a class="dbd-writeup" href="' . esc_url( $wu ) . '">Read the ' . esc_html( DBD_Router::week_label( $n ) ) . ' write-up</a>' : '' ) . '</p></div><div class="dbd-badge">' . (int) $fin . ' of ' . (int) $all . ' final</div></div>';
	}

	private static function picker( $n, $weeks ) {
		if ( ! $weeks ) { return ''; }
		$h = '<nav class="dbd-wkbar wk-pick" aria-label="Week"><div class="dbd-wktabs">';
		foreach ( $weeks as $w ) {
			$gl = DBD_Data::week_games( $w ); $open = count( array_filter( $gl, array( 'DBD_Router', 'is_final' ) ) ) < count( $gl );
			$lab = DBD_Router::week_label( $w );
			$h .= '<a href="' . esc_url( DBD_Router::url( array( 'view' => 'week', 'week' => $w ) ) ) . '"' . ( $w === (int) $n ? ' aria-current="page"' : '' ) . ( $open ? ' title="games still to play"' : '' ) . '>' . ( $open ? '<i></i>' : '' ) . esc_html( $lab === 'Week ' . $w ? 'Week ' . $w : $lab ) . '</a>';
		}
		return $h . '</div></nav>';
	}

	/* ------------------------------------------------------------ the short version */
	private static function short( $W ) {
		$fin = array_values( array_filter( $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final' && isset( $g['pre'] ); } ) );
		$li = array();
		$up = array_filter( $fin, function ( $g ) { return ! empty( $g['win'] ) && $g['pre_w'] < 500; } );
		if ( $up ) { usort( $up, function ( $a, $b ) { return $a['pre_w'] <=> $b['pre_w']; } ); $g = $up[0];
			$li[] = array( 'Upset', 'The <b>' . esc_html( self::nick( $g['win'] ) ) . '</b> beat the ' . esc_html( self::nick( self::loser( $g ) ) ) . ' ' . self::score( $g ) . ' as a <b>' . DBD_Data::pct( $g['pre_w'] ) . '%</b> underdog at kickoff, the longest odds anyone overcame this week.', self::game_url( $g['id'] ) ); }
		$cb = array_filter( $fin, function ( $g ) { return ! empty( $g['win'] ); } );
		if ( $cb ) { usort( $cb, function ( $a, $b ) { return $a['low'] <=> $b['low']; } ); $g = $cb[0];
			$li[] = array( 'Comeback', $g['low'] < 300 ? 'The <b>' . esc_html( self::nick( $g['win'] ) ) . '</b> were down to <b>' . max( 1, DBD_Data::pct( $g['low'] ) ) . '%</b> against the ' . esc_html( self::nick( self::loser( $g ) ) ) . ' and won ' . self::score( $g ) . '.' : 'Nobody came back from far: the lowest any winner fell was <b>' . DBD_Data::pct( $g['low'] ) . '%</b> (the ' . esc_html( self::nick( $g['win'] ) ) . ').', self::game_url( $g['id'] ) ); }
		if ( $fin ) { $wd = $fin; usort( $wd, function ( $a, $b ) { return array( $b['lc'] ?? 0, $b['drama'] ?? 0 ) <=> array( $a['lc'] ?? 0, $a['drama'] ?? 0 ); } ); $g = $wd[0]; $lc = (int) ( $g['lc'] ?? 0 );
			$li[] = array( 'Wildest', '<b>' . esc_html( self::nick( $g['away'] ) . ' at ' . self::nick( $g['home'] ) ) . '</b>: the lead changed hands <b>' . $lc . '</b> time' . ( $lc === 1 ? '' : 's' ) . ( ! empty( $g['ot'] ) ? ' and it went to overtime' : '' ) . '; ' . ( ! empty( $g['win'] ) ? 'the ' . esc_html( self::nick( $g['win'] ) ) . ' won ' . self::score( $g ) : 'it ended tied' ) . ( ! empty( $g['big'] ) ? ', and the biggest swing was ' . (int) $g['big'][0] . ' points at ' . esc_html( $g['big'][1] ) : '' ) . '.', self::game_url( $g['id'] ) ); }
		$T = (array) ( $W['teams'] ?? array() );
		if ( $T ) {
			$o = $T; usort( $o, function ( $a, $b ) { return $b['epa'] <=> $a['epa']; } ); $o = $o[0];
			$li[] = array( 'Best offense', 'The <b>' . esc_html( self::nick( $o['t'] ) ) . '</b>: <b>' . self::sgn( $o['epa'] ) . '</b> EPA a play against the ' . esc_html( self::nick( $o['o'] ) ) . ( isset( $o['usual'] ) ? ', ' . self::sgn( $o['epa'] - $o['usual'] ) . ' against their usual.' : '.' ), DBD_Router::url( array( 'team' => $o['t'] ) ) );
			$d = array_values( array_filter( $T, function ( $r ) { return isset( $r['depa'] ); } ) );
			if ( $d ) { usort( $d, function ( $a, $b ) { return $a['depa'] <=> $b['depa']; } ); $d = $d[0];
				$li[] = array( 'Best defense', 'The <b>' . esc_html( self::nick( $d['t'] ) ) . '</b> held the ' . esc_html( self::nick( $d['o'] ) ) . ' to <b>' . self::sgn( $d['depa'] ) . '</b> EPA a play' . ( isset( $d['dusual'] ) ? ', ' . self::sgn( $d['depa'] - $d['dusual'] ) . ' against what they usually allow.' : '.' ), DBD_Router::url( array( 'team' => $d['t'], 'side' => 'def' ) ) ); }
		}
		$star = null; foreach ( (array) ( $W['players'] ?? array() ) as $rows ) { foreach ( (array) $rows as $r ) { if ( ! $star || $r['e'] > $star['e'] ) { $star = $r; } } }
		if ( $star ) { $li[] = array( 'Player', '<b>' . esc_html( $star['n'] ) . '</b> (' . esc_html( $star['t'] . ' ' . $star['pos'] ) . '): <b>' . self::sgn( $star['e'], 1 ) . '</b> EPA, ' . esc_html( $star['x'] ) . '.', DBD_Router::url( array( 'player' => $star['id'] ) ) ); }
		if ( ! $li ) { return ''; }
		$h = '<section class="dbd-panel dbd-short"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b>' . ( empty( $W['complete'] ) ? ' · so far' : '' ) . '</span></div><ul>';
		foreach ( $li as $x ) { $h .= '<li><a href="' . esc_url( $x[2] ) . '">' . esc_html( $x[0] ) . '</a><span>' . $x[1] . '</span></li>'; }
		return $h . '</ul></section>';
	}

	/* ------------------------------------------------------------ the games */
	private static function spark( $g ) {
		$pts = (array) ( $g['spark'] ?? array() ); $n = count( $pts ) - 1; if ( $n < 1 ) { return ''; }
		$Wd = 120; $H = 34; $xy = array();
		foreach ( $pts as $i => $p ) { $xy[] = round( $i / $n * $Wd, 1 ) . ',' . round( ( 1 - $p / 1000 ) * $H, 1 ); }
		$xy = implode( ' ', $xy ); $wc = ! empty( $g['win'] ) ? ( DBD_Data::team( $g['win'] )['c1'] ?? '#6b6f78' ) : '#6b6f78';
		$wc = preg_match( '/^#[0-9a-fA-F]{6}$/', $wc ) ? $wc : '#6b6f78'; $id = preg_replace( '/[^A-Za-z0-9_]/', '', $g['id'] ); $mid = $H / 2;
		return '<svg class="wk-sp" viewBox="0 0 ' . $Wd . ' ' . $H . '" preserveAspectRatio="none" aria-hidden="true"><defs><clipPath id="wkt' . $id . '"><rect width="' . $Wd . '" height="' . $mid . '"/></clipPath><clipPath id="wkb' . $id . '"><rect y="' . $mid . '" width="' . $Wd . '" height="' . $mid . '"/></clipPath></defs>'
			. '<polygon points="0,' . $mid . ' ' . $xy . ' ' . $Wd . ',' . $mid . '" fill="' . $wc . '" fill-opacity=".45" clip-path="url(#wkt' . $id . ')"/><polygon points="0,' . $mid . ' ' . $xy . ' ' . $Wd . ',' . $mid . '" fill="#6b6f78" fill-opacity=".22" clip-path="url(#wkb' . $id . ')"/>'
			. '<line x1="0" x2="' . $Wd . '" y1="' . $mid . '" y2="' . $mid . '" stroke="currentColor" stroke-opacity=".35" stroke-dasharray="2 2" vector-effect="non-scaling-stroke"/><polyline points="' . $xy . '" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg>';
	}

	private static function card( $g ) {
		$href = esc_url( self::game_url( $g['id'] ) ); $key = esc_attr( ( $g['date'] ?? '' ) . ' ' . ( $g['time'] ?? '' ) );
		if ( ( $g['status'] ?? '' ) !== 'final' || ! isset( $g['pre'] ) ) {
			$sg = DBD_Data::game( $g['id'] ); $fin = isset( $g['a'], $g['h'] ) && $g['a'] !== null;
			$when = $fin ? 'Final' : esc_html( (string) ( $g['day'] ?? '' ) ) . ( $sg ? ' · ' . DBD_Router::kickoff_html( $sg ) : '' );
			return '<a class="wk-g up" href="' . $href . '" data-k="' . $key . '" data-d="0"><div class="wk-gt"><span>' . self::mk( $g['away'] ) . '<b>' . esc_html( self::nick( $g['away'] ) ) . '</b>' . ( $fin ? '<em>' . (int) $g['a'] . '</em>' : '' ) . '</span><span>' . self::mk( $g['home'] ) . '<b>' . esc_html( self::nick( $g['home'] ) ) . '</b>' . ( $fin ? '<em>' . (int) $g['h'] . '</em>' : '' ) . '</span></div>'
				. '<div class="wk-gm"><span class="wk-up">' . $when . '</span><small>' . esc_html( $fin ? 'win probability arrives with the next build' : DBD_Router::line_text( array( 'home' => $g['home'], 'away' => $g['away'], 'spread' => $g['spread'] ?? null ) ) ) . '</small></div></a>';
		}
		$row = function ( $a, $s ) use ( $g ) { return '<span class="' . ( ( $g['win'] ?? '' ) === $a ? 'w' : '' ) . '">' . self::mk( $a ) . '<b>' . esc_html( self::nick( $a ) ) . '</b><em>' . (int) $s . '</em></span>'; };
		$tags = ''; foreach ( (array) ( $g['tags'] ?? array() ) as $t ) { $tags .= '<i class="wk-tag ' . esc_attr( $t[0] ) . '">' . esc_html( $t[1] ) . '</i>'; }
		$fav = $g['pre'] >= 500 ? $g['home'] : $g['away'];
		$tip = '<b>' . esc_html( self::nick( $g['away'] ) . ' at ' . self::nick( $g['home'] ) ) . '</b><br>Kickoff: ' . esc_html( self::nick( $fav ) ) . ' ' . DBD_Data::pct( max( $g['pre'], 1000 - $g['pre'] ) ) . '%<br>Lead changes: ' . (int) ( $g['lc'] ?? 0 ) . ' · the favorite flipped ' . (int) ( $g['flips'] ?? 0 ) . ' times' . ( ! empty( $g['big'] ) ? '<br>Biggest swing: ' . (int) $g['big'][0] . ' pts, ' . esc_html( $g['big'][1] ) : '' );
		return '<a class="wk-g" href="' . $href . '" data-k="' . $key . '" data-d="' . (int) ( $g['drama'] ?? 0 ) . '" data-tip="' . esc_attr( $tip ) . '"><div class="wk-gt">' . $row( $g['away'], $g['a'] ) . $row( $g['home'], $g['h'] ) . '</div>'
			. '<div class="wk-gm">' . self::spark( $g ) . ( ! empty( $g['big'] ) ? '<small>Biggest swing <b>' . (int) $g['big'][0] . ' pts</b> · ' . esc_html( $g['big'][1] ) . '</small>' : '' ) . '</div>' . ( $tags ? '<div class="wk-tags">' . $tags . '</div>' : '' ) . '</a>';
	}

	private static function games( $W ) {
		$fin = array_values( array_filter( $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final' && isset( $g['pre'] ); } ) );
		$sum = function ( $f ) use ( $fin ) { return array_sum( array_map( $f, $fin ) ); };
		$chips = array(
			array( 'Games final', (int) $W['fin'] . ' of ' . (int) $W['n'] ),
			array( 'Overtime', (string) $sum( function ( $g ) { return empty( $g['ot'] ) ? 0 : 1; } ) ),
			array( 'Comebacks', (string) $sum( function ( $g ) { return ( ! empty( $g['win'] ) && $g['low'] < 250 ) ? 1 : 0; } ) ),
			array( 'Upsets', (string) $sum( function ( $g ) { return ( ! empty( $g['win'] ) && $g['pre_w'] < 500 ) ? 1 : 0; } ) ),
			array( 'Points', (string) $sum( function ( $g ) { return (int) $g['a'] + (int) $g['h']; } ) ),
			array( 'Biggest swing', $fin ? max( array_map( function ( $g ) { return (int) ( $g['big'][0] ?? 0 ); }, $fin ) ) . ' pts' : '–' ),
		);
		$sort = '<div class="dbd-seg t-tabs wk-sort" role="tablist" aria-label="Order"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" aria-selected="true" data-sort="k">Kickoff order</button><button class="t-tab" type="button" role="tab" aria-selected="false" data-sort="d">Most dramatic</button></div>';
		$h = '<section class="dbd-band" id="wk-games"><h2 class="dbd-bandh">The games<span>every final with its win-probability line</span></h2><section class="dbd-panel wk-games"><div class="dbd-ph"><span class="dbd-lab"><b>Scoreboard</b> · ' . esc_html( DBD_Router::week_label( $W['week'] ) ) . '</span>' . $sort . '</div><div class="wk-chips">';
		foreach ( $chips as $c ) { $h .= '<div><small>' . esc_html( $c[0] ) . '</small><b>' . esc_html( $c[1] ) . '</b></div>'; }
		$h .= '</div><div class="wk-grid">'; foreach ( $W['games'] as $g ) { $h .= self::card( $g ); }
		return $h . '</div><p class="dbd-note">Each line is the winner\'s chance of winning, snap by snap, from nflverse\'s spread-adjusted model: above the dashed middle (shaded in the winner\'s color) they were favored, below it (shaded grey) they were behind. Overtime is left off the line, as on the game page. A comeback is a winner who fell below 25%; an upset is a winner who was under 50% at kickoff; lead changes count the score changing hands. Click a game for the full chart, the box score and the plays that swung it.</p></section></section>';
	}

	/* ------------------------------------------------------------ the plays */
	private static function plays( $W ) {
		$sw = ''; foreach ( (array) ( $W['swings'] ?? array() ) as $i => $x ) {
			$g = DBD_Data::game( $x['g'] ); $m = $g ? $g['away'] . ' at ' . $g['home'] : ''; $c = DBD_Data::team( $x['t'] )['c1'] ?? '#6b6f78'; $c = preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '#6b6f78';
			$ball = ! empty( $x['b'] ) ? $x['b'] : $x['t'];  /* SCOPE §47: the team with the ball leads the line; the value names the team it helped */
			$sw .= '<li><span class="rk">' . ( $i + 1 ) . '</span><span class="wk-pd"><span class="wk-pm">' . self::mk( $ball, 16 ) . '<b>' . esc_html( self::nick( $ball ) ) . '</b> · ' . esc_html( $x['c'] ) . ( $m ? ' · <a href="' . esc_url( self::game_url( $x['g'] ) ) . '">' . esc_html( $m ) . '</a>' : '' ) . '</span><span class="wk-pt">' . DBD_Kicker::link_desc( $x['d'], $ball ) . '</span></span>'
				. '<span class="wk-pv"><b>+' . round( $x['v'] ) . '</b><small>' . esc_html( $x['t'] ) . ' ' . (int) $x['ba'] . '% → ' . min( 100, (int) round( $x['ba'] + $x['v'] ) ) . '%</small><i style="--w:' . min( 100, round( $x['v'] * 1.6 ) ) . '%;background:' . $c . '"></i></span></li>'; }
		$ep = ''; foreach ( (array) ( $W['plays'] ?? array() ) as $i => $x ) {
			$p = ! empty( $x['pid'] ) ? DBD_Data::player( $x['pid'] ) : null; $who = $p ? '<a href="' . esc_url( DBD_Router::url( array( 'player' => $x['pid'] ) ) ) . '">' . esc_html( $p['name'] ) . '</a>' : esc_html( (string) ( $x['pl'] ?? '' ) );
			$ep .= '<li><span class="rk">' . ( $i + 1 ) . '</span><span class="wk-pd"><span class="wk-pm">' . self::mk( $x['t'], 16 ) . '<b>' . esc_html( self::nick( $x['t'] ) ) . '</b> · ' . esc_html( $x['c'] ) . ' · <a href="' . esc_url( self::game_url( $x['g'] ) ) . '">vs ' . esc_html( (string) $x['o'] ) . '</a>' . ( $who !== '' ? ' · ' . $who : '' ) . '</span><span class="wk-pt">' . esc_html( $x['d'] ) . '</span></span>'
				. '<span class="wk-pv"><b>+' . number_format( (float) $x['e'], 1 ) . '</b><small>EPA</small></span></li>'; }
		if ( ! $sw && ! $ep ) { return ''; }
		return '<section class="dbd-band" id="wk-plays"><h2 class="dbd-bandh">The plays<span>the ones that moved games, and the ones that moved the ball</span></h2><div class="wk-2">'
			. '<section class="dbd-panel ld-card"><div class="ld-h"><b>The biggest swings</b><small>the team with the ball, and the points of win probability for the team the play helped</small></div><ol class="ld-list wk-pl">' . ( $sw ?: '<li class="none">No final games with win probability yet.</li>' ) . '</ol></section>'
			. '<section class="dbd-panel ld-card"><div class="ld-h"><b>The best plays by EPA</b><small>expected points added on one snap</small></div><ol class="ld-list wk-pl">' . $ep . '</ol></section></div></section>';
	}

	/* ------------------------------------------------------------ teams */
	private static function team_rows( $rows, $key, $usual, $good_low, $def ) {
		$vals = array_map( function ( $r ) use ( $key ) { return (float) $r[ $key ]; }, $rows ); if ( ! $vals ) { return ''; }
		$span = max( abs( min( $vals ) ), abs( max( $vals ) ) ) ?: 1; $h = '';
		foreach ( $rows as $i => $r ) {
			$v = (float) $r[ $key ]; $w = round( abs( $v ) / $span * 50, 1 ); $good = $good_low ? $v < 0 : $v > 0;
			$res = ( $r['pf'] > $r['pa'] ? 'W' : ( $r['pf'] < $r['pa'] ? 'L' : 'T' ) ); $du = isset( $r[ $usual ] ) ? $v - (float) $r[ $usual ] : null;
			$href = DBD_Router::url( $def ? array( 'team' => $r['t'], 'side' => 'def' ) : array( 'team' => $r['t'] ) );
			$h .= '<div class="wk-tr"><span class="rk">' . ( $i + 1 ) . '</span><span class="nm">' . self::mk( $r['t'] ) . '<a href="' . esc_url( $href ) . '">' . esc_html( self::nick( $r['t'] ) ) . '</a><small>' . ( $r['home'] ? 'vs ' : 'at ' ) . esc_html( $r['o'] ) . ' · <em class="' . strtolower( $res ) . '">' . $res . ' ' . (int) $r['pf'] . '–' . (int) $r['pa'] . '</em></small></span>'
				. '<span class="wk-bar"><i class="z"></i><i class="b ' . ( $good ? 'good' : 'bad' ) . '" style="' . ( $v > 0 ? 'left:50%' : 'right:50%' ) . ';width:' . $w . '%"></i></span><b class="v">' . self::sgn( $v ) . '</b><small class="u">' . ( $du === null ? '' : self::sgn( $du ) . ' vs usual' ) . '</small></div>';
		}
		return $h;
	}

	private static function teams( $W ) {
		$T = (array) ( $W['teams'] ?? array() ); if ( ! $T ) { return ''; }
		$o = $T; usort( $o, function ( $a, $b ) { return $b['epa'] <=> $a['epa']; } );
		$d = array_values( array_filter( $T, function ( $r ) { return isset( $r['depa'] ); } ) ); usort( $d, function ( $a, $b ) { return $a['depa'] <=> $b['depa']; } );
		$tabs = '<div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" aria-selected="true" data-pane="0">Offenses</button><button class="t-tab" type="button" role="tab" aria-selected="false" data-pane="1">Defenses</button></div></div>';
		return '<section class="dbd-band" id="wk-teams"><h2 class="dbd-bandh">Teams<span>who had their day, by EPA a play</span></h2><section class="dbd-panel dbd-tabbed wk-teams">' . $tabs
			. '<section class="dbd-panel dbd-pane" data-pane="0"><div class="dbd-ph"><span class="dbd-lab"><b>Offenses</b><span class="sub">EPA a play this week, against their season</span></span></div><div class="wk-trs">' . self::team_rows( $o, 'epa', 'usual', false, false ) . '</div></section>'
			. '<section class="dbd-panel dbd-pane" data-pane="1" hidden><div class="dbd-ph"><span class="dbd-lab"><b>Defenses</b><span class="sub">EPA a play allowed this week, against what they usually allow</span></span></div><div class="wk-trs">' . self::team_rows( $d, 'depa', 'dusual', true, true ) . '</div></section>'
			. '<p class="dbd-note">EPA a play counts every pass and run in the game, scrambles and sacks included. "Vs usual" compares it with the same team\'s average in its other games this season, so a good offense having an ordinary day reads as a dip. Each team links to its offense or defense tab.</p></section></section>';
	}

	/* ------------------------------------------------------------ players */
	private static function players( $W ) {
		$P = (array) ( $W['players'] ?? array() );
		$names = array( 'QB' => array( 'Quarterbacks', 'passing and rushing EPA this week' ), 'RB' => array( 'Backs', 'rushing and receiving EPA this week' ), 'WR' => array( 'Wide receivers', 'receiving EPA this week' ), 'TE' => array( 'Tight ends', 'receiving EPA this week' ) );
		$card = function ( $pos ) use ( $P, $names ) {
			$li = ''; foreach ( (array) ( $P[ $pos ] ?? array() ) as $i => $r ) {
				$li .= '<li><span class="rk">' . ( $i + 1 ) . '</span><span class="who">' . DBD_Marks::player( $r['t'], $r['num'] ?? '', 24, $r['ph'] ?? null ) . '<span class="nm"><a href="' . esc_url( DBD_Router::url( array( 'player' => $r['id'] ) ) ) . '">' . esc_html( $r['n'] ) . '</a><small>' . esc_html( $r['t'] . ( ! empty( $r['o'] ) ? ' vs ' . $r['o'] : '' ) . ' · ' . $r['x'] ) . '</small></span></span><b class="v">' . self::sgn( $r['e'], 1 ) . '</b></li>'; }
			return '<section class="dbd-panel ld-card"><div class="ld-h"><b>' . esc_html( $names[ $pos ][0] ) . '</b><small>' . esc_html( $names[ $pos ][1] ) . '</small></div><ol class="ld-list">' . ( $li ?: '<li class="none">No lines yet.</li>' ) . '</ol></section>';
		};
		$fx = ''; foreach ( DBD_Fantasy::FMT as $i => $fm ) {
			$li = ''; foreach ( (array) ( $W['fx'][ $fm ] ?? array() ) as $j => $r ) {
				$li .= '<li><span class="rk">' . ( $j + 1 ) . '</span><span class="who">' . DBD_Marks::player( $r['t'], $r['num'] ?? '', 24, $r['ph'] ?? null ) . '<span class="nm"><a href="' . esc_url( DBD_Router::url( array( 'player' => $r['id'] ) ) ) . '">' . esc_html( $r['n'] ) . '</a><small>' . esc_html( $r['t'] . ( ! empty( $r['o'] ) ? ' vs ' . $r['o'] : '' ) . ' · ' . $r['pos'] ) . '</small></span></span><b class="v">' . number_format( (float) $r['f'][ $i ], 1 ) . ( ! empty( $r['est'] ) ? '*' : '' ) . '</b></li>'; }
			$fx .= '<div data-fx="' . esc_attr( $fm ) . '"><ol class="ld-list">' . ( $li ?: '<li class="none">No fantasy lines yet.</li>' ) . '</ol></div>';
		}
		$fxc = '<section class="dbd-panel ld-card wk-fx"><div class="ld-h wk-fxh"><span><b>Fantasy points</b><small>the week\'s top scorers</small></span>' . DBD_Fantasy::seg() . '</div>' . $fx . '</section>';
		$np = count( (array) ( $W['pbp'] ?? array() ) );
		$lag = $np ? '<p class="dbd-note wk-lag">Lines for ' . $np . ' game' . ( $np === 1 ? '' : 's' ) . ' are counted from the play-by-play because nflverse\'s weekly stats for them haven\'t posted yet. Their fantasy points (marked *) leave out fumbles and two-point tries until the stats arrive, usually by the next morning.</p>' : '';
		return '<section class="dbd-band" id="wk-players"><h2 class="dbd-bandh">Players<span>the best games by position, by EPA</span></h2><div class="ld-grid wk-players">' . $card( 'QB' ) . $card( 'RB' ) . $fxc . $card( 'WR' ) . $card( 'TE' ) . '</div>' . $lag . '</section>';
	}

	private static function movers_band( $W ) {
		if ( ! empty( $W['movers'] ) ) { return '<section class="dbd-band" id="wk-movers"><h2 class="dbd-bandh">Movers<span>whose role broke from his own season</span></h2>' . self::movers( $W['movers'] ) . '</section>'; }
		if ( empty( $W['complete'] ) ) { return '<section class="dbd-band" id="wk-movers"><p class="dbd-note wk-wait">Movers for ' . esc_html( DBD_Router::week_label( $W['week'] ) ) . ' appear once every game is final.</p></section>'; }
		return '';
	}

	/* ------------------------------------------------------------ the movers panel (SCOPE §22), shared with the Players page */
	public static function movers( $MV ) {
		if ( ! $MV || empty( $MV['week'] ) ) { return ''; }
		$teams = DBD_Data::teams(); $nick = function ( $a ) use ( $teams ) { return $teams[ $a ]['nick'] ?? $a; };
		$boards = array(
			array( 'snaps', 'Snap share', 'points of the snaps', 'pts', function ( $v ) { return round( $v ) . '%'; } ),
			array( 'targets', 'Target share', 'points of team targets, QBs excluded', 'pts', function ( $v ) { return round( $v ) . '%'; } ),
			array( 'epa', 'EPA', 'the week against his per-game average', 'EPA', function ( $v ) { return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 1 ); } ),
		);
		$spark = function ( $s, $up ) {
			$v = array_values( array_filter( (array) $s, function ( $x ) { return $x !== null; } ) ); if ( ! $v ) { return ''; }
			$hi = max( $v ); $lo = min( $v ); $sp = ( $hi - $lo ) ?: 1; $Wd = 72; $Hh = 22; $n = count( $s );
			$x = function ( $i ) use ( $n, $Wd ) { return $n > 1 ? round( 2 + $i * ( $Wd - 4 ) / ( $n - 1 ), 1 ) : $Wd / 2; }; $y = function ( $val ) use ( $hi, $sp, $Hh ) { return round( 2 + ( $hi - $val ) / $sp * ( $Hh - 4 ), 1 ); };
			$pts = array(); foreach ( $s as $i => $val ) { if ( $val !== null ) { $pts[] = $x( $i ) . ',' . $y( $val ); } }
			$li = $n - 1; $last = $s[ $li ];
			return '<svg viewBox="0 0 ' . $Wd . ' ' . $Hh . '" aria-hidden="true"><polyline points="' . implode( ' ', $pts ) . '" fill="none" stroke="var(--dbd-muted)" stroke-width="1.5"/>' . ( $last === null ? '' : '<circle cx="' . $x( $li ) . '" cy="' . $y( $last ) . '" r="2.5" fill="' . ( $up ? 'var(--dbd-good)' : 'var(--dbd-bad)' ) . '"/>' ) . '</svg>';
		};
		$mrow = function ( $r, $up, $b ) use ( $MV, $spark, $nick, $teams ) {
			$fmt = $b[4]; $href = DBD_Router::url( array( 'player' => $r['id'] ) );
			$exact = function ( $v ) use ( $b ) { return $b[3] === 'pts' ? number_format( (float) $v, 1 ) . '%' : ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 ); };  /* the stored decimals, not the row's rounding */
			$tip = '<b>' . esc_html( $r['name'] ) . '</b> · ' . esc_html( $nick( $r['team'] ) ) . '<br>Week ' . (int) $MV['week'] . ( ! empty( $r['opp'] ) ? ' vs ' . esc_html( $nick( $r['opp'] ) ) : '' ) . ': ' . esc_html( $b[1] ) . ' <b>' . esc_html( $exact( $r['now'] ) ) . '</b>, before ' . esc_html( $exact( $r['before'] ) ) . ' over ' . (int) $r['g'] . ' game' . ( (int) $r['g'] === 1 ? '' : 's' );
			$d = (float) $r['delta']; $dtxt = ( $d > 0 ? '+' : '−' ) . ( $b[3] === 'pts' ? round( abs( $d ) ) : number_format( abs( $d ), 1 ) ) . ' ' . $b[3];
			return '<a class="dbd-mvr ' . ( $up ? 'up' : 'dn' ) . '" href="' . esc_url( $href ) . '" data-team="' . esc_attr( $r['team'] ) . '" data-pos="' . esc_attr( $r['pos'] ) . '" data-id="' . esc_attr( $r['id'] ) . '" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $teams[ $r['team'] ] ?? null ) ) . '" data-tip="' . esc_attr( $tip ) . '">' . DBD_Marks::player( $r['team'], $r['num'], 24, $r['photo'] ?? null ) . '<span class="nm"><b>' . esc_html( $r['name'] ) . '</b><small>' . esc_html( $r['team'] . ' · ' . $r['pos'] ) . '</small></span>' . $spark( $r['s'] ?? array(), $up ) . '<span class="ch"><b>' . esc_html( $dtxt ) . '</b><small>' . esc_html( $fmt( $r['before'] ) . ' → ' . $fmt( $r['now'] ) ) . '</small></span></a>';
		};
		/* the read: the snap board's top climber and faller */
		$u = $MV['snaps']['up'][0] ?? null; $dn = $MV['snaps']['down'][0] ?? null; $wkn = (int) $MV['week'];
		if ( $u && $dn && $u['team'] === $dn['team'] ) { $say = 'Week ' . $wkn . ' was a changing of the guard in ' . esc_html( $nick( $u['team'] ) ) . ' ' . ( $u['pos'] === 'QB' ? 'quarterbacking' : 'snaps' ) . ': <b>' . esc_html( $u['name'] ) . '</b> took ' . round( $u['now'] ) . '% of the snaps, up ' . round( $u['delta'] ) . ' points on his season, while <b>' . esc_html( $dn['name'] ) . '</b> fell to ' . round( $dn['now'] ) . '%.'; }
		elseif ( $u && $dn ) { $say = 'The biggest swing in snaps in Week ' . $wkn . ': <b>' . esc_html( $u['name'] ) . '</b> (' . esc_html( $nick( $u['team'] ) ) . ') took ' . round( $u['now'] ) . '% of the snaps, up ' . round( $u['delta'] ) . ' points on his season; <b>' . esc_html( $dn['name'] ) . '</b> (' . esc_html( $nick( $dn['team'] ) ) . ') fell to ' . round( $dn['now'] ) . '% from ' . round( $dn['before'] ) . '.'; }
		elseif ( $u ) { $say = 'The biggest climb in snaps in Week ' . $wkn . ': <b>' . esc_html( $u['name'] ) . '</b> (' . esc_html( $nick( $u['team'] ) ) . ') took ' . round( $u['now'] ) . '% of the snaps, up ' . round( $u['delta'] ) . ' points on his season; nobody fell past the floor.'; }
		elseif ( $dn ) { $say = 'The biggest drop in snaps in Week ' . $wkn . ': <b>' . esc_html( $dn['name'] ) . '</b> (' . esc_html( $nick( $dn['team'] ) ) . ') fell to ' . round( $dn['now'] ) . '% of the snaps from ' . round( $dn['before'] ) . '; nobody climbed past the floor.'; }
		else { $say = 'Week ' . $wkn . ': ' . (int) $MV['n'] . ' players took a snap; no snap share moved past the floor.'; }
		$h = '<section class="dbd-panel dbd-mv" id="dbd-mv"><div class="dbd-ph"><span class="dbd-lab"><b>Movers</b> · Week ' . $wkn . ' · against his own season</span><span class="dbd-badge">' . (int) $MV['n'] . ' played</span></div>';
		$h .= '<p class="dbd-mv-say">' . $say . '</p><div class="dbd-mv3">';
		foreach ( $boards as $b ) {
			$up = ''; foreach ( $MV[ $b[0] ]['up'] ?? array() as $r ) { $up .= $mrow( $r, true, $b ); }
			$down = ''; foreach ( $MV[ $b[0] ]['down'] ?? array() as $r ) { $down .= $mrow( $r, false, $b ); }
			$h .= '<div class="dbd-mvb"><h4>' . esc_html( $b[1] ) . '<small>' . esc_html( $b[2] ) . '</small></h4>' . ( $up || $down ? ( $up ? '<div class="dbd-mvg up">▲ Climbers</div>' . $up : '' ) . ( $down ? '<div class="dbd-mvg dn">▼ Fallers</div>' . $down : '' ) : '<p class="none">Nobody moved far on this board.</p>' ) . '</div>';
		}
		$h .= '</div>';
		if ( ! empty( $MV['faces'] ) ) {
			$h .= '<div class="dbd-mvf"><span class="l">New faces</span>';
			foreach ( $MV['faces'] as $r ) { $h .= '<a href="' . esc_url( DBD_Router::url( array( 'player' => $r['id'] ) ) ) . '" data-team="' . esc_attr( $r['team'] ) . '" data-pos="' . esc_attr( $r['pos'] ) . '" data-id="' . esc_attr( $r['id'] ) . '" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $teams[ $r['team'] ] ?? null ) ) . '">' . DBD_Marks::player( $r['team'], $r['num'], 22, $r['photo'] ?? null ) . esc_html( $r['name'] ) . '<small>' . esc_html( $r['team'] . ' · ' . $r['pos'] . ' · ' . ( ( $r['kind'] ?? '' ) === 'debut' ? 'debut' : 'first start-level week' ) . ', ' . round( $r['now'] ) . '% of the snaps' ) . '</small></a>'; }
			$h .= '</div>';
		}
		return $h . '<p class="dbd-note">Each row compares his last full week with his average over the games he had played before it, so a mover broke from his own pattern. Boards list the ' . DBD_Grades::MV_PER . ' biggest climbers, then the ' . DBD_Grades::MV_PER . ' biggest fallers, past a floor (' . DBD_Grades::MV_FLOOR_SNAPS . ' points of snap share, ' . DBD_Grades::MV_FLOOR_TARGETS . ' of target share, ' . number_format( DBD_Grades::MV_FLOOR_EPA, 1 ) . ' EPA). The sparkline is his season; the dot is that week. New faces took their first snap that week with ' . DBD_Grades::MV_DEBUT . '% or more, or passed ' . DBD_Grades::MV_START . '% for the first time.</p></section>';
	}
}
