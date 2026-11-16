<?php
/**
 * The share pack (2.27.0, SCOPE §68): five things to post each week, for the Share pack tab in the admin. Built from the newest
 * finished week each time the tab opens (nothing is stored): the biggest swing, a usage riser, the best and worst offense, a
 * target-share mover, and each team's Biggest edge for next week. Each item carries a card to download, a Reddit title, a Reddit
 * comment with the link and a post for X and Bluesky. Nothing posts by itself: Gene posts by hand.
 * The two new cards (the riser card and the offense card) are drawn by DBD_Card from the specs here.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Pack {
	const X_MAX = 280; const X_LINK = 23;  /* X counts every link as 23 characters */

	/** Each team's main subreddit, for the "Fits" hint. */
	const SUBS = array( 'ARI' => 'AZCardinals', 'ATL' => 'falcons', 'BAL' => 'ravens', 'BUF' => 'buffalobills', 'CAR' => 'panthers', 'CHI' => 'CHIBears', 'CIN' => 'bengals', 'CLE' => 'Browns',
		'DAL' => 'cowboys', 'DEN' => 'DenverBroncos', 'DET' => 'detroitlions', 'GB' => 'GreenBayPackers', 'HOU' => 'Texans', 'IND' => 'Colts', 'JAX' => 'Jaguars', 'KC' => 'KansasCityChiefs',
		'LV' => 'raiders', 'LAC' => 'Chargers', 'LA' => 'LosAngelesRams', 'MIA' => 'miamidolphins', 'MIN' => 'minnesotavikings', 'NE' => 'Patriots', 'NO' => 'Saints', 'NYG' => 'NYGiants',
		'NYJ' => 'nyjets', 'PHI' => 'eagles', 'PIT' => 'steelers', 'SF' => '49ers', 'SEA' => 'Seahawks', 'TB' => 'buccaneers', 'TEN' => 'Tennesseetitans', 'WAS' => 'Commanders' );

	public static function sub( $t ) { return isset( self::SUBS[ $t ] ) ? 'r/' . self::SUBS[ $t ] : ''; }

	/** The finished weeks that have their movers, newest first. */
	public static function weeks() {
		$o = array();
		foreach ( DBD_Week::weeks() as $w ) { $f = DBD_Week::file( $w ); if ( $f && ! empty( $f['complete'] ) && ! empty( $f['movers'] ) ) { $o[] = (int) $w; } }
		rsort( $o ); return $o;
	}
	public static function newest() { $w = self::weeks(); return $w ? $w[0] : 0; }

	private static function nick( $t ) { return DBD_Data::team( (string) $t )['nick'] ?? (string) $t; }
	private static function poss( $t ) { return self::nick( $t ) . '’'; }  /* every NFL nickname is plural */
	private static function epa( $v ) { return ( $v < 0 ? '−' : '+' ) . number_format( abs( (float) $v ), 2 ); }
	private static function slug( $s ) { return trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }

	/** How long a post is on X: every link counts as 23. */
	public static function xlen( $s ) { return (int) mb_strlen( (string) preg_replace( '#https?://\S+#', str_repeat( 'x', self::X_LINK ), (string) $s ) ); }

	/** The X and Bluesky post: the takeaway and the link; the short takeaway when the long one runs over; cut at a word as a last resort. */
	public static function post( $long, $short, $link ) {
		foreach ( array( $long, $short ) as $t ) { $p = $t . ' ' . $link; if ( self::xlen( $p ) <= self::X_MAX ) { return $p; } }
		$room = self::X_MAX - self::X_LINK - 2; $t = mb_substr( $short, 0, $room ); $t = preg_replace( '/\s+\S*$/u', '', $t );
		return rtrim( $t, ' ,;:' ) . '… ' . $link;
	}

	/** A play description for a person to read: no bracketed notes, and no last sentence nflverse cut off with an ellipsis. */
	public static function play( $d ) {
		$d = (string) preg_replace( '/\s*\[[^\]]*\]/', '', (string) $d );
		$d = (string) preg_replace( '/(?<=[.!?])\s+[^.!?]*…$/u', '', trim( $d ) );
		return DBD_Post::clean( rtrim( $d, '…' ) );
	}

	/** The mover for a riser: the first in the list, quarterbacks left out for snaps, never $skip. */
	public static function mover( $W, $kind, $skip = '' ) {
		foreach ( (array) ( $W['movers'][ $kind ]['up'] ?? array() ) as $m ) {
			if ( empty( $m['id'] ) || $m['id'] === $skip ) { continue; }
			if ( $kind === 'snaps' && ( $m['pos'] ?? '' ) === 'QB' ) { continue; }
			return $m;
		}
		return null;
	}

	/** The five items' first four for week $n (the keys are keys()). Each: k, h, why, card, file, fits, title, comment, post, link. */
	public static function items( $n ) {
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return array(); }
		$out = array(); $lab = 'Week ' . (int) $n;

		/* 1. the biggest swing (the weekly post's) */
		$sw = $W['swings'][0] ?? null; $games = array(); foreach ( (array) ( $W['games'] ?? array() ) as $g ) { $games[ $g['id'] ] = $g; }
		if ( $sw && isset( $games[ $sw['g'] ] ) ) {
			$g = $games[ $sw['g'] ]; $t = $sw['t']; $o = $t === $g['away'] ? $g['home'] : $g['away'];
			$ts = (int) ( $t === $g['away'] ? $g['a'] : $g['h'] ); $os = (int) ( $t === $g['away'] ? $g['h'] : $g['a'] );
			$res = $ts > $os ? 'They won ' . $ts . '-' . $os . '.' : ( $ts < $os ? 'They lost ' . $os . '-' . $ts . '.' : 'It ended in a ' . $ts . '-' . $os . ' tie.' );
			$ck = DBD_Post::clock( $sw['c'] ?? '' );
			$short = $lab . '’s biggest swing: one play' . ( $ck !== '' ? ' ' . $ck : '' ) . ' moved the ' . self::poss( $t ) . ' chance of beating the ' . self::nick( $o ) . ' by ' . (int) round( (float) $sw['v'] ) . ' points.';
			$link = DBD_Router::url( array( 'game' => $g['id'] ) ); $title = $short . ' ' . $res;
			$out[] = array( 'k' => 'swing', 'h' => 'The biggest swing', 'why' => 'The week’s largest one-play change in win probability, on the game’s card with the swing marked.',
				'card' => 'game-' . $g['id'], 'file' => 'dbd-week-' . (int) $n . '-swing-' . strtolower( $t . '-' . $o ) . '.png', 'fits' => array_filter( array( 'r/nfl', self::sub( $t ), self::sub( $o ) ) ),
				'title' => $title, 'comment' => 'The play: ' . self::play( $sw['d'] ?? '' ) . ' The whole game’s win probability, play by play: ' . $link,
				'post' => self::post( $title, $short, $link ), 'link' => $link );
		}

		/* 2. a usage riser: snap share, quarterbacks out */
		$sn = self::mover( $W, 'snaps' );
		if ( $sn ) { $out[] = self::riser_item( $n, 'snaps', $sn ); }

		/* 3. the best and worst offense */
		$tm = array_values( array_filter( (array) ( $W['teams'] ?? array() ), function ( $x ) { return isset( $x['epa'] ); } ) );
		if ( count( $tm ) >= 2 ) {
			$bo = $tm[0]; $wo = $tm[0]; foreach ( $tm as $x ) { if ( $x['epa'] > $bo['epa'] ) { $bo = $x; } if ( $x['epa'] < $wo['epa'] ) { $wo = $x; } }
			$res = function ( $x ) { $pf = (int) $x['pf']; $pa = (int) $x['pa']; return $pf > $pa ? 'a ' . $pf . '-' . $pa . ' win over the ' . self::nick( $x['o'] ) : ( $pf < $pa ? 'a ' . $pa . '-' . $pf . ' loss to the ' . self::nick( $x['o'] ) : 'a ' . $pf . '-' . $pa . ' tie with the ' . self::nick( $x['o'] ) ); };
			$title = $lab . ' offenses by EPA a play: the ' . self::nick( $bo['t'] ) . ' were best (' . self::epa( $bo['epa'] ) . ' in ' . $res( $bo ) . '), the ' . self::nick( $wo['t'] ) . ' worst (' . self::epa( $wo['epa'] ) . ' in ' . $res( $wo ) . ').';
			$short = $lab . ' offenses by EPA a play: the ' . self::nick( $bo['t'] ) . ' were best (' . self::epa( $bo['epa'] ) . '), the ' . self::nick( $wo['t'] ) . ' worst (' . self::epa( $wo['epa'] ) . ').';
			$link = DBD_Router::url( array( 'view' => 'week', 'week' => (int) $n ) );
			$out[] = array( 'k' => 'offense', 'h' => 'Best and worst offense', 'why' => 'Every offense that played, by EPA a play for the week, in one chart.',
				'card' => 'pack-week-' . (int) $n . '-offense', 'file' => 'dbd-week-' . (int) $n . '-offense.png', 'fits' => array_filter( array( 'r/nfl', self::sub( $bo['t'] ) ) ),
				'title' => $title, 'comment' => 'Every team’s week, with the usual for each side: ' . $link, 'post' => self::post( $title, $short, $link ), 'link' => $link );
		}

		/* 4. a target-share mover, not the usage riser again */
		$tg = self::mover( $W, 'targets', $sn['id'] ?? '' );
		if ( $tg ) { $out[] = self::riser_item( $n, 'targets', $tg ); }
		return $out;
	}

	private static function riser_item( $n, $kind, $m ) {
		$t = (string) ( $m['team'] ?? '' ); $now = (int) round( (float) $m['now'] ); $was = (int) round( (float) $m['before'] ); $g = (int) ( $m['g'] ?? 0 );
		$what = $kind === 'snaps' ? ' played ' . $now . '% of the ' . self::poss( $t ) . ' snaps' : ' drew ' . $now . '% of the ' . self::poss( $t ) . ' targets';
		$short = $m['name'] . $what . ' against the ' . self::nick( $m['opp'] ?? '' ) . ' in Week ' . (int) $n . ', up from ' . $was . '%' . ( $g ? ' in his ' . $g . ' earlier ' . ( $g === 1 ? 'game' : 'games' ) : '' ) . '.';
		$link = DBD_Router::url( array( 'player' => $m['id'] ) );
		return array( 'k' => $kind, 'h' => $kind === 'snaps' ? 'A usage riser' : 'A target-share mover',
			'why' => $kind === 'snaps' ? 'The biggest jump in snap share against his own earlier games, quarterbacks left out.' : 'The biggest jump in share of the team’s targets against his own earlier games.',
			'card' => 'pack-week-' . (int) $n . '-' . $kind, 'file' => 'dbd-week-' . (int) $n . '-' . $kind . '-' . self::slug( $m['name'] ) . '.png', 'fits' => array_filter( array( 'r/fantasyfootball', self::sub( $t ) ) ),
			'title' => $short, 'comment' => ( $kind === 'snaps' ? 'His snap share and targets game by game: ' : 'His target share and routes game by game: ' ) . $link,
			'post' => self::post( $short, $short, $link ), 'link' => $link );
	}

	/** Keys to the game for the week after $n: per game, each offense's Biggest edge, ready to post in that team's subreddit. */
	public static function keys( $n ) {
		$next = (int) $n + 1; $out = array();
		foreach ( DBD_Data::week_games( $next ) as $g ) {
			$a = $g['away'] ?? ''; $h = $g['home'] ?? ''; if ( $a === '' || $h === '' ) { continue; }
			$link = DBD_Router::url( array( 'game' => $g['id'] ) ); $sides = array();
			foreach ( array( array( $a, $h ), array( $h, $a ) ) as $p ) {
				$c = DBD_Takeaways::cards( $p[0], $p[1] )[0] ?? null; if ( ! $c ) { continue; }
				$head = self::nick( $a ) . ' at ' . self::nick( $h );
				$sides[] = array( 't' => $p[0], 'fits' => self::sub( $p[0] ), 'head' => $c[2], 'say' => $c[3],
					'title' => $head . ': where the matchup tilts for the ' . self::nick( $p[0] ) . ' offense',
					'comment' => rtrim( $c[2], '.' ) . '. ' . $c[3] . ' The other keys and the head to head: ' . $link,
					'post' => self::post( $head . ': ' . $c[3], $head . ': ' . $c[3], $link ) );
			}
			if ( $sides ) { $out[] = array( 'g' => $g, 'card' => 'game-' . $g['id'], 'file' => 'dbd-week-' . $next . '-' . strtolower( $a . '-' . $h ) . '.png', 'link' => $link, 'sides' => $sides ); }
		}
		return $out;
	}

	/* ---- the two new cards' specs (DBD_Card draws them) */

	/** A riser card: pack-week-9-snaps or pack-week-9-targets. */
	public static function spec_riser( $n, $kind ) {
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return null; }
		$sn = self::mover( $W, 'snaps' ); $m = $kind === 'snaps' ? $sn : self::mover( $W, 'targets', $sn['id'] ?? '' ); if ( ! $m ) { return null; }
		$t = (string) $m['team']; $tm = DBD_Data::team( $t ) ?: array(); $parts = explode( ' ', (string) $m['name'], 2 );
		$lab = $kind === 'snaps' ? 'Snap share' : 'Target share'; $g = (int) ( $m['g'] ?? 0 );
		return array( 'kind' => $kind, 'week' => (int) $n, 'season' => (int) DBD_Data::season(), 'team' => $t, 'c1' => $tm['c1'] ?? '#121417',
			'meta' => DBD_Data::season() . ' · week ' . (int) $n . ' · ' . strtolower( $lab ), 'kicker' => $lab . ' · ' . ( $tm['nick'] ?? $t ) . ' ' . ( $m['pos'] ?? '' ) . ( isset( $m['num'] ) ? ' #' . $m['num'] : '' ),
			'first' => $parts[0], 'last' => $parts[1] ?? '', 'now' => (float) $m['now'], 'before' => (float) $m['before'],
			'say' => ( $kind === 'snaps' ? 'of the ' . self::poss( $t ) . ' snaps' : 'of the ' . self::poss( $t ) . ' targets' ) . ' against the ' . self::nick( $m['opp'] ?? '' ) . ', up from ' . (int) round( (float) $m['before'] ) . '%' . ( $g ? ' in his ' . $g . ' earlier ' . ( $g === 1 ? 'game' : 'games' ) : '' ),
			'series' => array_values( (array) ( $m['s'] ?? array() ) ), 'chart' => $lab . ' by game · dashes: earlier games, ' . (int) round( (float) $m['before'] ) . '%',
			'foot' => 'Data: nflverse · ' . ( $kind === 'snaps' ? 'share of the team’s offensive snaps' : 'share of the team’s targets' ) );
	}

	/** The offense card: pack-week-9-offense. Rows best first: [team, epa, pf, pa, opp]. */
	public static function spec_offense( $n ) {
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return null; }
		$rows = array(); foreach ( (array) ( $W['teams'] ?? array() ) as $x ) { if ( isset( $x['epa'], $x['t'] ) ) { $rows[] = array( $x['t'], (float) $x['epa'], (int) ( $x['pf'] ?? 0 ), (int) ( $x['pa'] ?? 0 ), (string) ( $x['o'] ?? '' ) ); } }
		if ( count( $rows ) < 2 ) { return null; }
		usort( $rows, function ( $a, $b ) { return $b[1] <=> $a[1]; } );
		return array( 'week' => (int) $n, 'meta' => DBD_Data::season() . ' · week ' . (int) $n . ' · offense', 'kicker' => 'Week ' . (int) $n . ' · ' . count( $rows ) . ' offenses', 'rows' => $rows,
			'logos' => array( $rows[0][0], $rows[ count( $rows ) - 1 ][0] ) );
	}
}
