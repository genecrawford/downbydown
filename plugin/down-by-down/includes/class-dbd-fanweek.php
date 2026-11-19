<?php
/**
 * The weekly fantasy page (SCOPE §78): /fantasy/week/{N}/, drawn from fan/w{N}.json (tools/build_fan.py). /fantasy/ is the newest week.
 * The build writes the coming week's file until its first kickoff and then leaves it, so a past week reads as it did before its games.
 * Usage risers and fallers, red zone, matchups at a glance and byes come from the file; the edges come from Keys to the game at render time.
 * Every number that depends on the format is drawn in all three and shown by the format switch ([data-fx], dbd-site.js).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_FanWeek {
	const POS = array( 'RB', 'WR', 'TE' );
	const LAB = array( 'RB' => 'Running backs', 'WR' => 'Wide receivers', 'TE' => 'Tight ends' );
	const WHAT = array( 'RB' => 'carries + targets', 'WR' => 'targets', 'TE' => 'targets' );

	private static $weeks = null;

	/** Weeks with a fantasy file, ascending. */
	public static function weeks() {
		if ( self::$weeks !== null && ! DBD_Data::archived() ) { return self::$weeks; }
		$out = array();
		foreach ( (array) glob( DBD_Data::dir() . '/fan/w*.json' ) as $f ) { if ( preg_match( '/\/w(\d{1,2})\.json$/', (string) $f, $m ) && self::file( (int) $m[1] ) ) { $out[] = (int) $m[1]; } }
		sort( $out );
		if ( ! DBD_Data::archived() ) { self::$weeks = $out; }
		return $out;
	}

	public static function reset() { self::$weeks = null; }

	public static function default_week() { $w = self::weeks(); return $w ? max( $w ) : 0; }

	public static function file( $n ) {
		$f = DBD_Data::get( 'fan/w' . (int) $n . '.json' );
		return ( $f && isset( $f['risers'], $f['mu'] ) && (int) ( $f['week'] ?? 0 ) === (int) $n ) ? $f : null;
	}

	/** The page's link: this week's, or week $n's. */
	public static function url( $n = 0 ) { return DBD_Router::url( $n ? array( 'view' => 'fantasy', 'week' => (int) $n ) : array( 'view' => 'fantasy' ) ); }

	/** A "Week 11 fantasy" link for another page, or '' when week $n has no file. */
	public static function link( $n, $text = null, $class = '' ) {
		if ( ! $n || ! self::file( $n ) ) { return ''; }
		return '<a' . ( $class ? ' class="' . esc_attr( $class ) . '"' : '' ) . ' href="' . esc_url( self::url( $n ) ) . '">' . esc_html( $text ?? 'Week ' . (int) $n . ' fantasy' ) . '</a>';
	}

	/** The top riser at each position, for the description and the card. */
	public static function tops( $F ) {
		$t = array(); foreach ( self::POS as $p ) { if ( ! empty( $F['risers'][ $p ][0] ) ) { $t[ $p ] = $F['risers'][ $p ][0]; } }
		return $t;
	}

	public static function seo( $n ) {
		$F = self::file( $n ); $title = 'Week ' . (int) $n . ' fantasy football: usage risers, red zone and matchups';
		$t = $F ? self::tops( $F ) : array();
		if ( count( $t ) === 3 ) { return array( $title, 'Week ' . (int) $n . ' fantasy: ' . $t['RB']['n'] . ', ' . $t['WR']['n'] . ' and ' . $t['TE']['n'] . ' lead the usage risers, plus red-zone work and every matchup.' ); }
		return array( $title, 'Week ' . (int) $n . ' fantasy: whose role is growing, who gets the ball near the goal line, and which defenses give up the most at each position.' );
	}

	/* ------------------------------------------------------------ pieces */

	private static function nick( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; }
	private static function pl( $id ) { return DBD_Router::url( array( 'player' => $id ) ); }
	private static function pct( $v ) { return (int) round( (float) $v ) . '%'; }

	/** One value per format, each in its own [data-fx] span. */
	private static function fx( $vals, $fmt ) {
		$h = ''; foreach ( DBD_Fantasy::FMT as $i => $fm ) { $h .= '<span data-fx="' . $fm . '">' . esc_html( $fmt( $vals[ $i ] ?? 0 ) ) . '</span>'; }
		return $h;
	}

	/** "at LV 4th vs RB": his next opponent and that defense's rank against his position, tinted soft or tough, in every format. */
	private static function mtag( $m, $pos ) {
		if ( ! $m || empty( $m['o'] ) ) { return ''; }
		$rk = ''; foreach ( DBD_Fantasy::FMT as $i => $fm ) { $r = (int) ( $m['rk'][ $i ] ?? 0 ); if ( ! $r ) { continue; } $rk .= '<span data-fx="' . $fm . '" class="fz-t ' . DBD_Fantasy::tier( $r ) . '">' . esc_html( DBD_Fantasy::ord( $r ) ) . '</span>'; }
		return '<span class="fz-m">' . esc_html( ( ! empty( $m['h'] ) ? 'vs ' : 'at ' ) . $m['o'] ) . ' ' . $rk . ( $rk !== '' ? '<i>vs ' . esc_html( $pos ) . '</i>' : '' ) . '</span>';
	}

	/** His share game by game; the last three games shaded, the dot his latest game. */
	private static function spark( $s ) {
		$s = array_values( (array) $s ); $n = count( $s ); $pts = array(); $top = 0;
		foreach ( $s as $v ) { if ( $v !== null ) { $top = max( $top, (float) $v ); } }
		$top = $top ?: 1; $d = max( $n - 1, 1 );
		foreach ( $s as $i => $v ) { if ( $v !== null ) { $pts[] = array( 2 + 68 * $i / $d, 20 - 18 * (float) $v / $top ); } }
		if ( count( $pts ) < 2 ) { return '<span></span>'; }
		$line = implode( ' ', array_map( function ( $p ) { return round( $p[0], 1 ) . ',' . round( $p[1], 1 ); }, $pts ) ); $l = end( $pts ); $x0 = round( 2 + 68 * max( 0, $n - 3 ) / $d, 1 );
		return '<svg viewBox="0 0 72 22" aria-hidden="true"><rect x="' . $x0 . '" y="0" width="' . round( 72 - $x0, 1 ) . '" height="22" fill="var(--dbd-bg-2)"/><polyline points="' . $line . '" fill="none" stroke="var(--dbd-muted)" stroke-width="1.5"/><circle cx="' . round( $l[0], 1 ) . '" cy="' . round( $l[1], 1 ) . '" r="2.5" fill="var(--dbd-ink)"/></svg>';
	}

	private static function row( $r, $down ) {
		$d = (float) $r['d']; $T = DBD_Data::teams();
		$tip = '<b>' . esc_html( $r['n'] ) . '</b> · ' . esc_html( self::nick( $r['t'] ) ) . '<br>Share of the team\'s ' . esc_html( self::WHAT[ $r['pos'] ] ?? 'targets' ) . ': ' . number_format( (float) $r['now'], 1 ) . '% over the last three games, ' . number_format( (float) $r['bef'], 1 ) . '% before';
		return '<a class="dbd-mvr fz-r ' . ( $down ? 'dn' : 'up' ) . '" href="' . esc_url( self::pl( $r['id'] ) ) . '" data-team="' . esc_attr( $r['t'] ) . '" data-pos="' . esc_attr( $r['pos'] ) . '" data-id="' . esc_attr( $r['id'] ) . '" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $T[ $r['t'] ] ?? null ) ) . '" data-tip="' . esc_attr( $tip ) . '">'
			. DBD_Marks::player( $r['t'], $r['num'] ?? '', 24, $r['ph'] ?? null )
			. '<span class="nm"><b>' . esc_html( $r['n'] ) . '</b><small>' . esc_html( $r['t'] . ' · ' . $r['pos'] ) . ' · ' . self::fx( $r['ppg'], function ( $v ) { return number_format( (float) $v, 1 ) . ' a game'; } ) . '</small>' . self::mtag( $r['m'] ?? null, $r['pos'] ) . '</span>'
			. self::spark( $r['s'] ?? array() ) . '<span class="ch"><b>' . ( $d > 0 ? '+' : '−' ) . (int) round( abs( $d ) ) . ' pts</b><small>' . self::pct( $r['bef'] ) . ' → ' . self::pct( $r['now'] ) . '</small></span></a>';
	}

	private static function boards( $F, $key, $down ) {
		$h = '';
		foreach ( self::POS as $p ) {
			$li = ''; foreach ( (array) ( $F[ $key ][ $p ] ?? array() ) as $r ) { $li .= self::row( $r, $down ); }
			$h .= '<div class="dbd-mvb"><h4>' . esc_html( self::LAB[ $p ] ) . '<small>share of the team\'s ' . esc_html( self::WHAT[ $p ] ) . '</small></h4>' . ( $li ?: '<p class="none">Nobody moved far enough yet.</p>' ) . '</div>';
		}
		return $h;
	}

	private static function rz_table( $rows, $rec ) {
		$th = '<tr><th>Player</th>' . ( $rec ? '<th>Targets</th>' : '<th>Carries</th><th>Targets</th>' ) . '<th>Inside 5</th><th>TD</th><th class="c-sh">Team share</th><th class="c-nx">Next</th></tr>'; $tr = '';
		foreach ( (array) $rows as $r ) {
			$tr .= '<tr><td><a href="' . esc_url( self::pl( $r['id'] ) ) . '">' . esc_html( $r['n'] ) . '</a><small>' . esc_html( $r['t'] . ' · ' . $r['pos'] ) . '</small></td>'
				. ( $rec ? '<td>' . (int) $r['tg'] . '</td>' : '<td>' . (int) $r['c'] . '</td><td>' . (int) $r['tg'] . '</td>' )
				. '<td>' . (int) $r['in5'] . '</td><td>' . (int) $r['td'] . '</td><td class="c-sh"><span class="fz-bar"><i style="width:' . min( 100, (int) $r['sh'] ) . '%"></i></span>' . (int) $r['sh'] . '%</td><td class="c-nx">' . self::mtag( $r['m'] ?? null, $r['pos'] ) . '</td></tr>';
		}
		if ( $tr === '' ) { return '<p class="dbd-note">No red-zone plays counted yet.</p>'; }
		return '<div class="dbd-tw"><table class="fz-rz"><thead>' . $th . '</thead><tbody>' . $tr . '</tbody></table></div>';
	}

	/** One position's softest four and toughest two, in every format. */
	private static function glance( $F, $pos ) {
		$rows = (array) ( $F['mu'][ $pos ] ?? array() ); if ( ! $rows ) { return ''; }
		$h = '';
		foreach ( DBD_Fantasy::FMT as $i => $fm ) {
			$s = $rows; usort( $s, function ( $a, $b ) use ( $i ) { return array( (int) $a['rk'][ $i ], -(float) $a['al'][ $i ], $a['t'] ) <=> array( (int) $b['rk'][ $i ], -(float) $b['al'][ $i ], $b['t'] ); } );
			$li = function ( $x, $cls ) use ( $i ) { return '<li><span class="rk fz-t ' . $cls . '">' . esc_html( DBD_Fantasy::ord( (int) $x['rk'][ $i ] ) ) . '</span><span class="who"><b>' . esc_html( self::nick( $x['t'] ) ) . '</b> ' . ( ! empty( $x['h'] ) ? 'vs' : 'at' ) . ' ' . esc_html( self::nick( $x['o'] ) ) . '<small>' . esc_html( DBD_Fantasy::num( $x['al'][ $i ] ) ) . ' a game</small></span></li>'; };
			$soft = ''; foreach ( array_slice( $s, 0, 4 ) as $x ) { $soft .= $li( $x, DBD_Fantasy::tier( (int) $x['rk'][ $i ] ) ); }
			$tough = ''; foreach ( array_reverse( array_slice( $s, -2 ) ) as $x ) { $tough .= $li( $x, DBD_Fantasy::tier( (int) $x['rk'][ $i ] ) ); }
			$h .= '<div data-fx="' . $fm . '"><div class="dbd-mvg up">Softest</div><ol class="fz-l">' . $soft . '</ol><div class="dbd-mvg dn">Toughest</div><ol class="fz-l">' . $tough . '</ol></div>';
		}
		return '<div class="dbd-mvb fz-g"><h4>' . esc_html( $pos ) . '<small>defense\'s rank in points allowed</small></h4>' . $h . '</div>';
	}

	private static function edges( $n ) {
		if ( ! class_exists( 'DBD_Pack' ) || ! class_exists( 'DBD_Takeaways' ) ) { return ''; }
		$li = '';
		foreach ( DBD_Pack::keys( $n - 1 ) as $k ) {
			$li .= '<li><a class="fz-eg" href="' . esc_url( $k['link'] ) . '">' . esc_html( self::nick( $k['g']['away'] ) . ' at ' . self::nick( $k['g']['home'] ) ) . '</a>';
			foreach ( $k['sides'] as $s ) { $li .= '<span class="fz-es"><b>' . esc_html( self::nick( $s['t'] ) ) . ':</b> ' . esc_html( rtrim( (string) $s['head'], '.' ) ) . '. <span>' . esc_html( $s['say'] ) . '</span></span>'; }
			$li .= '</li>';
		}
		return $li === '' ? '' : '<section class="dbd-band" id="fz-edges"><h2 class="dbd-bandh">Edges<span>each side\'s biggest edge, from Keys to the game</span></h2><section class="dbd-panel"><ol class="fz-edges">' . $li . '</ol></section></section>';
	}

	private static function byes( $F ) {
		$li = '';
		foreach ( (array) ( $F['bye'] ?? array() ) as $b ) {
			$cells = '';  /* the top three in each format: the switch changes who's listed, not just the points (Greptile, PR #146) */
			foreach ( DBD_Fantasy::FMT as $fm ) {
				$ps = array(); foreach ( (array) ( $b['top'][ $fm ] ?? array() ) as $p ) { $ps[] = '<a href="' . esc_url( self::pl( $p['id'] ) ) . '">' . esc_html( $p['n'] ) . '</a> <small>' . esc_html( $p['pos'] . ' · ' . DBD_Fantasy::num( $p['ppg'] ) ) . '</small>'; }
				$cells .= '<span data-fx="' . $fm . '">' . ( $ps ? implode( ', ', $ps ) : '<small>no regular scorers yet</small>' ) . '</span>';
			}
			$li .= '<li><b>' . esc_html( self::nick( $b['t'] ) ) . '</b>' . $cells . '</li>';
		}
		return $li === '' ? '' : '<section class="dbd-band" id="fz-bye"><h2 class="dbd-bandh">On bye<span>who you\'re replacing this week</span></h2><section class="dbd-panel"><ul class="fz-bye">' . $li . '</ul><p class="dbd-note">Each team\'s top three scorers by points a game, in the format you pick, among players with three games or more.</p></section></section>';
	}

	/* ------------------------------------------------------------ the page */

	public static function page( $n ) {
		$F = $n ? self::file( $n ) : null;
		if ( ! $F ) {
			return '<div class="dbd-ttitle"><div class="t-stagger"><h1 class="t-stagger-line t-stagger-line--1"><span>Fantasy</span></h1></div></div><section class="dbd-panel"><div class="dbd-empty"><h2>The fantasy page isn\'t in yet</h2><p>It fills in with the next data build once the regular season has a week ahead.</p></div></section>';
		}
		$N = (int) $F['week']; $thru = (int) $F['thru']; $season = (int) ( $F['season'] ?? DBD_Data::season() );
		$played = false; foreach ( DBD_Data::week_games( $N ) as $g ) { if ( DBD_Router::is_final( $g ) ) { $played = true; break; } }
		$k = $season . ' season · data through Week ' . $thru . ( $played ? ', as it stood at kickoff' : '' ) . ' · ' . count( (array) $F['games'] ) . ' games' . ( ! empty( $F['byes'] ) ? ' · byes: ' . implode( ', ', $F['byes'] ) : '' );
		$h = '<div class="dbd-ttitle fz-title"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( $k ) . '</p><h1 class="t-stagger-line t-stagger-line--2"><span>Week ' . $N . '</span> fantasy</h1>'
			. '<p class="dbd-sub t-stagger-line t-stagger-line--3">Whose role is growing, who gets the ball near the goal line, and which defenses give up the most at each position. Every row shows his Week ' . $N . ' opponent.</p></div>' . DBD_Fantasy::seg() . '</div>';
		$ws = self::weeks();
		if ( count( $ws ) > 1 ) {
			$h .= '<nav class="dbd-wkbar wk-pick" aria-label="Week"><div class="dbd-wktabs">';
			foreach ( $ws as $w ) { $h .= '<a href="' . esc_url( self::url( $w ) ) . '"' . ( $w === $N ? ' aria-current="page"' : '' ) . '>Week ' . (int) $w . '</a>'; }
			$h .= '</div></nav>';
		}
		$edges = self::edges( $N ); $byes = self::byes( $F );
		$h .= '<nav class="dbd-jump" aria-label="On this page"><a href="#fz-up">Risers</a><a href="#fz-down">Fallers</a><a href="#fz-rz">Red zone</a><a href="#fz-mu">Matchups</a>' . ( $edges ? '<a href="#fz-edges">Edges</a>' : '' ) . ( $byes ? '<a href="#fz-bye">On bye</a>' : '' ) . '</nav>';

		$t = self::tops( $F ); $say = array();
		if ( isset( $t['RB'] ) ) { $say[] = '<b>' . esc_html( $t['RB']['n'] ) . '</b> (' . esc_html( self::nick( $t['RB']['t'] ) ) . ') now gets ' . self::pct( $t['RB']['now'] ) . ' of the carries and targets, up from ' . self::pct( $t['RB']['bef'] ); }
		if ( isset( $t['WR'] ) ) { $say[] = '<b>' . esc_html( $t['WR']['n'] ) . '</b> (' . esc_html( self::nick( $t['WR']['t'] ) ) . ') draws ' . self::pct( $t['WR']['now'] ) . ' of the targets, up from ' . self::pct( $t['WR']['bef'] ); }
		$h .= '<section class="dbd-band" id="fz-up"><h2 class="dbd-bandh">Usage risers<span>last three games against his earlier ones</span></h2><section class="dbd-panel dbd-mv"><div class="dbd-ph"><span class="dbd-lab"><b>Risers</b> · each team\'s last three games against its earlier ones</span></div>'
			. ( $say ? '<p class="dbd-mv-say">The biggest gains in role: ' . implode( '; ', $say ) . '. The shaded part of each line is the last three games.</p>' : '' )
			. '<div class="dbd-mv3">' . self::boards( $F, 'risers', false ) . '</div>'
			. '<p class="dbd-note">Share is his part of the team\'s plays: for backs, carries plus targets out of the team\'s designed runs and targets; for receivers and tight ends, targets out of the team\'s targets. The last three are his team\'s last three games before Week ' . $N . '; he needs two games on each side. Risers hold at least 10% now. Points a game are over those three games, in the format you pick. The opponent\'s rank is in points allowed to his position: 1st allows the most, green is a soft matchup (1st to ' . DBD_Fantasy::ord( DBD_Fantasy::SOFT ) . '), red a tough one (' . DBD_Fantasy::ord( DBD_Fantasy::TOUGH ) . ' and below). Teams on bye are left out.</p></section></section>';
		$h .= '<section class="dbd-band" id="fz-down"><h2 class="dbd-bandh">Fallers<span>roles that shrank, among players who had one</span></h2><section class="dbd-panel dbd-mv"><div class="dbd-mv3">' . self::boards( $F, 'fallers', true ) . '</div><p class="dbd-note">Players who had at least 15% before, with the biggest drops.</p></section></section>';
		$h .= '<section class="dbd-band" id="fz-rz"><h2 class="dbd-bandh">Red zone<span>inside the 20, last three games</span></h2><div class="fz-2">'
			. '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Backs</b> · carries and targets inside the 20</span></div>' . self::rz_table( $F['rz']['rb'] ?? array(), false ) . '</section>'
			. '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Receivers and tight ends</b> · targets inside the 20</span></div>' . self::rz_table( $F['rz']['rec'] ?? array(), true ) . '</section></div>'
			. '<p class="dbd-note">Every designed run and target inside the opponent\'s 20 in each team\'s last three games before Week ' . $N . '. Inside 5 counts the ones inside the 5; TD the plays that scored; team share is his part of the team\'s red-zone runs and targets.</p></section>';
		$g = ''; foreach ( array( 'QB', 'RB', 'WR', 'TE' ) as $p ) { $g .= self::glance( $F, $p ); }
		if ( $g !== '' ) {
			$h .= '<section class="dbd-band" id="fz-mu"><h2 class="dbd-bandh">Matchups at a glance<span>who faces the defenses that give up the most</span></h2><section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Week ' . $N . '</b> · rank in fantasy points allowed to the position, 1st = most</span><a class="dbd-badge" href="' . esc_url( DBD_Router::url( array( 'view' => 'matchups', 'week' => $N ) ) ) . '#dbd-fxm">Every game on Matchups</a></div><div class="fz-4">' . $g . '</div></section></section>';
		}
		return $h . $edges . $byes;
	}
}
