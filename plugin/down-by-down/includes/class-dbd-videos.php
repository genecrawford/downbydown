<?php
/**
 * Team videos and game highlights (SCOPE §51), from YouTube's public feeds, with no key.
 *
 *   - Each team's official channel, and the NFL's channel filtered to videos whose title names the team (two teams at most: a title naming
 *     more is a league roundup). Shorts are left out. The last 14 days, up to 12 a team, kept the way team news keeps its stories: each
 *     fetch adds to what's stored, so a channel that fails loses nothing.
 *   - Game highlights: the NFL posts a "Game Recaps (Week N) | 2026 NFL Season" playlist each week, one "Team vs Team ... Highlights" video a
 *     game. The NFL's playlists page names them; each fetch reads it for the weeks still missing highlights and keeps every match for good.
 *   - Runs with team news, every 6 hours. Each thumbnail links to the video on YouTube: the NFL and the teams don't allow playing them on other sites.
 *
 * collect() and highlights() are pure (bodies in, lists out), so the view harness checks the matching without the network.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Videos {

	const OPT        = 'dbd_videos_';        /* + team: that team's videos */
	const OPT_HL     = 'dbd_highlights';     /* season => game id => array( v, h, ts ) */
	const OPT_STATUS = 'dbd_videos_status';  /* the last pass: when, how long, each channel's state, the playlists found */
	const OPT_ON     = 'dbd_videos_on';      /* videos and highlights shown (on by default) */
	const HOOK_FIRST = 'dbd_videos_first';
	const DAYS       = 1209600;              /* 14 days */
	const CAP        = 12;
	const TOP        = 5;                   /* the first large, four beside it (2.13.0) */
	const NFL        = 'UCDVYQ4Zhbm3S2dlz7P1GBDg';
	const PLAYLISTS  = 'https://www.youtube.com/channel/UCDVYQ4Zhbm3S2dlz7P1GBDg/playlists';
	/* each team's official channel, found by its handle and checked 2026-11-12 (@chiefs is an old 2006 channel and @DenverBroncos is empty) */
	const CH = array(
		'ARI' => 'UCzNfiKcvNjLHljohEkO83Rg', 'ATL' => 'UCzjCfV3LHyarKbdb-2ScGZg', 'BAL' => 'UCbpj2JAUMb8G_oQQFPeGrhg', 'BUF' => 'UCcEvCxUe2Sm5W6MliEUv0vg',
		'CAR' => 'UC6vl4XAyO5mZLczhFC8MgpA', 'CHI' => 'UCP0Cdc6moLMyDJiO0s-yhbQ', 'CIN' => 'UCnQUpSnJ39KsjZKbT0CprHQ', 'CLE' => 'UCQQQO7Kdo0cu9iEb19qOoYA',
		'DAL' => 'UCC0BPKJxAyxjQoRTYbpW0FQ', 'DEN' => 'UCDGdBexlDZA8T7hnweWWyow', 'DET' => 'UCv5J06V-ESk5_1uriG65f3w', 'GB'  => 'UCJtI-l6La0zniodFtSHYrBg',
		'HOU' => 'UCa_FcpOBe8G6VAR18RYS-aA', 'IND' => 'UCyYn26HPC4HIedifGnNbjBw', 'JAX' => 'UCsGacW6z0GedR-Wv45SBRZg', 'KC'  => 'UC-hXefb6XBFSubWz6Ezf_lA',
		'LV'  => 'UC1es5fp8FEK1L0EgHjCvmtQ', 'LAC' => 'UCUyz_gEY_N-KBU4zjt2s-uQ', 'LA'  => 'UCyJ6yZdVUkBvt2vl4R03jcA', 'MIA' => 'UCHUSfEzpSRkUUsRkk_aJwDw',
		'MIN' => 'UCcsw_KrB_wg5lQ5nXWR_LFA', 'NE'  => 'UCMm_V8YjmnZRhToXIqDYDuw', 'NO'  => 'UCwuddf1JrodMlc5fYpVlrQA', 'NYG' => 'UCk2FqoG8dN5EAz5WU3A0D7A',
		'NYJ' => 'UCROj9vBjc4ZW3AL4cd_BjHg', 'PHI' => 'UCaogx6OHpsGg0zuGRKsjbtQ', 'PIT' => 'UChaRXjMDs4ppKfnTPE6Z89w', 'SF'  => 'UCeIOarQkwmGhimim9cDUTng',
		'SEA' => 'UCzkFCRiMcOBeef8xcaqipmw', 'TB'  => 'UC0Wwu7r1ybaaR09ANhudTzA', 'TEN' => 'UCHBsqVkFraWvtNd1w0Qx4_g', 'WAS' => 'UC2a0ENbCZqIO5C1fWXGXZXA',
	);

	public static function on() { return (bool) get_option( self::OPT_ON, true ); }

	public static function init() {
		add_action( DBD_News::HOOK, array( __CLASS__, 'run' ), 20 );  /* right after the news pass */
		add_action( self::HOOK_FIRST, array( __CLASS__, 'run' ) );
		/* no videos yet (the update that brings this): the first pass runs a minute or so later instead of waiting for the next 6-hour mark */
		if ( ! get_option( self::OPT_STATUS ) && ! wp_next_scheduled( self::HOOK_FIRST ) && ! get_transient( 'dbd_videos_first' ) ) {
			set_transient( 'dbd_videos_first', 1, HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + 120, self::HOOK_FIRST );
		}
	}

	/** Every channel feed: key => array( url, kind, team or '', label ). */
	public static function feeds() {
		$f = array();
		foreach ( self::CH as $t => $id ) { $f[ 'yt:' . $t ] = array( 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $id, 'team', $t, ( DBD_Data::team( $t )['nick'] ?? $t ) . ' on YouTube' ); }
		$f['yt:NFL'] = array( 'https://www.youtube.com/feeds/videos.xml?channel_id=' . self::NFL, 'nfl', '', 'NFL on YouTube' );
		return $f;
	}

	/* ------------------------------------------------------------------ one pass */

	public static function run() {
		if ( ! self::on() || get_transient( 'dbd_videos_lock' ) ) { return; }
		set_transient( 'dbd_videos_lock', 1, 5 * MINUTE_IN_SECONDS );
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); }
		$t0 = microtime( true ); DBD_Data::use_season( null );
		$feeds = self::feeds(); $bodies = DBD_News::fetch( $feeds );
		$prev = array(); foreach ( array_keys( self::CH ) as $t ) { $prev[ $t ] = get_option( self::OPT . $t, array() ); }
		list( $stores, $status ) = self::collect( $feeds, $bodies, $prev );
		foreach ( $stores as $t => $s ) { update_option( self::OPT . $t, $s, false ); }

		/* highlights: the weeks with a finished game still missing its video, from the playlists page */
		$season = DBD_Data::season(); $all = (array) get_option( self::OPT_HL, array() ); $have = (array) ( $all[ $season ] ?? array() );
		$need = self::weeks_missing( $have ); $pl = array( 'ok' => true, 'err' => '', 'weeks' => array(), 'found' => 0 );
		if ( $need ) {
			$r = wp_remote_get( self::PLAYLISTS, array( 'timeout' => 15, 'user-agent' => sprintf( DBD_News::UA, DBD_VERSION ), 'headers' => array( 'Accept-Language' => 'en-US,en;q=0.8' ) ) );
			$html = ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) ? (string) wp_remote_retrieve_body( $r ) : '';
			$lists = $html !== '' ? self::playlists( $html, $season ) : array();
			if ( $html === '' ) { $pl = array( 'ok' => false, 'err' => 'the NFL playlists page didn\'t answer', 'weeks' => array(), 'found' => 0 ); }
			elseif ( ! $lists ) { $pl = array( 'ok' => false, 'err' => 'no "Game Recaps" playlists found on the NFL playlists page (YouTube may have changed it)', 'weeks' => array(), 'found' => 0 ); }
			$want = array_intersect_key( $lists, array_flip( $need ) ); $pf = array();
			foreach ( $want as $w => $pid ) { $pf[ 'pl:' . $w ] = array( 'https://www.youtube.com/feeds/videos.xml?playlist_id=' . $pid, 'pl', '', 'Game Recaps, Week ' . $w ); }
			$pb = $pf ? DBD_News::fetch( $pf ) : array();
			list( $have, $found, $errs ) = self::apply_recaps( $want, $pb, $have );
			$pl['found'] += $found; $pl['weeks'] = array_keys( $want );
			if ( $errs ) { $pl['ok'] = false; $pl['err'] = implode( '; ', $errs ); }
			$all[ $season ] = $have; update_option( self::OPT_HL, $all, false );
		}
		update_option( self::OPT_STATUS, array( 'at' => DBD_News::now(), 'secs' => round( microtime( true ) - $t0, 1 ), 'feeds' => $status, 'pl' => $pl ), false );
		delete_transient( 'dbd_videos_lock' );
	}

	/**
	 * This season's weeks that have a finished game without its highlights, newest first. Once any are stored, only weeks played in the last
	 * 21 days count: an older week the NFL's page no longer lists (or a game it never posted) would otherwise send the page fetch every pass.
	 */
	public static function weeks_missing( $have, $now = null ) {
		$now = $now ?? DBD_News::now(); $out = array();
		foreach ( DBD_Data::weeks() as $w ) {
			$miss = false; $last = 0;
			foreach ( DBD_Data::week_games( $w ) as $g ) {
				$last = max( $last, (int) strtotime( (string) ( $g['date'] ?? '' ) . ' 12:00:00 UTC' ) );
				if ( DBD_Router::is_final( $g ) && ! isset( $have[ $g['id'] ] ) ) { $miss = true; }
			}
			if ( $miss && ( ! $have || $now - $last <= 21 * DAY_IN_SECONDS ) ) { $out[] = (int) $w; }
		}
		rsort( $out );
		return $out;
	}

	/* ------------------------------------------------------------------ reading YouTube */

	/** A channel or playlist feed's videos: array( id, title, ts, short ). Read with patterns, like the news feeds. */
	public static function parse( $xml ) {
		$out = array();
		if ( ! preg_match_all( '#<entry>(.*?)</entry>#s', (string) $xml, $m ) ) { return $out; }
		foreach ( $m[1] as $e ) {
			if ( ! preg_match( '#<yt:videoId>([\w-]{6,20})</yt:videoId>#', $e, $id ) ) { continue; }
			$t = preg_match( '#<title>(.*?)</title>#s', $e, $x ) ? trim( html_entity_decode( $x[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) : '';
			$ts = preg_match( '#<published>([^<]+)</published>#', $e, $p ) ? (int) strtotime( $p[1] ) : 0;
			$short = (bool) preg_match( '#<link rel="alternate" href="[^"]*/shorts/#', $e );
			if ( $t !== '' && $ts ) { $out[] = array( $id[1], $t, $ts, $short ); }
		}
		return $out;
	}

	/** The NFL playlists page's "Game Recaps (Week N) | {season} NFL Season" playlists: week => playlist id. */
	public static function playlists( $html, $season ) {
		$out = array();
		foreach ( explode( '{"lockupViewModel":', (string) $html ) as $chunk ) {
			if ( ! preg_match( '#"title":\{"content":"Game Recaps \(Week (\d{1,2})\)\s*\|\s*(\d{4}) NFL Season"#', $chunk, $m ) || (int) $m[2] !== (int) $season ) { continue; }
			if ( preg_match( '#"(?:playlistId|contentId)":"(PL[\w-]{8,40})"#', $chunk, $p ) && ! isset( $out[ (int) $m[1] ] ) ) { $out[ (int) $m[1] ] = $p[1]; }
		}
		return $out;
	}

	/** A recap playlist's videos matched to week $w's games: game id => array( v, h, ts ). A video names both teams in full and the week. */
	public static function highlights( $w, $body, $games ) {
		$out = array();
		foreach ( self::parse( $body ) as $v ) {
			if ( ! preg_match( '/\bWeek\s+' . (int) $w . '\b/i', $v[1] ) || stripos( $v[1], 'highlights' ) === false ) { continue; }
			foreach ( $games as $g ) {
				$a = (string) ( DBD_Data::team( $g['away'] )['name'] ?? '' ); $h = (string) ( DBD_Data::team( $g['home'] )['name'] ?? '' );
				if ( $a !== '' && $h !== '' && stripos( $v[1], $a ) !== false && stripos( $v[1], $h ) !== false && ! isset( $out[ $g['id'] ] ) ) { $out[ $g['id'] ] = array( 'v' => $v[0], 'h' => $v[1], 'ts' => $v[2] ); break; }
			}
		}
		return $out;
	}

	/**
	 * Each week's recap playlist body matched to its games and added to $have. $bodies: 'pl:{week}' => body or array( 'error' => … ).
	 * Returns array( $have, how many new, the errors ) so a playlist that fails is reported, not skipped quietly (Greptile, PR #84).
	 */
	public static function apply_recaps( $want, $bodies, $have ) {
		$found = 0; $errs = array();
		foreach ( $want as $w => $pid ) {
			$b = $bodies[ 'pl:' . $w ] ?? array( 'error' => 'not fetched' );
			if ( ! is_string( $b ) ) { $errs[] = 'the Week ' . (int) $w . ' recap playlist didn\'t answer (' . ( $b['error'] ?? 'failed' ) . ')'; continue; }
			$got = self::highlights( (int) $w, $b, DBD_Data::week_games( (int) $w ) );
			$found += count( array_diff_key( $got, $have ) ); $have = $got + $have;
		}
		return array( $have, $found, $errs );
	}

	/** The teams a title names, by nickname or full name (whole words). */
	private static function named( $title ) {
		static $re = null;
		if ( $re === null ) { $re = array(); foreach ( DBD_Data::teams() as $t => $x ) { $re[ $t ] = '/\b(' . preg_quote( (string) $x['nick'], '/' ) . '|' . preg_quote( (string) $x['name'], '/' ) . ')\b/iu'; } }  /* any case: "VIKINGS vs. PACKERS" (Greptile, PR #84) */
		$o = array(); foreach ( $re as $t => $r ) { if ( preg_match( $r, $title ) ) { $o[] = $t; } }
		return $o;
	}

	/**
	 * Every channel's videos matched to teams. $bodies: key => body or array( 'error' => … ). $prev: team => last stored list (a failed channel's
	 * videos carry over). Returns array( team => array( 'at', 'v' => list ), key => status ). An item: array( v, h, ts, s ) where s is 'team' or 'nfl'.
	 */
	public static function collect( $feeds, $bodies, $prev = array() ) {
		$now = DBD_News::now(); $teams = array_keys( self::CH ); $lists = array_fill_keys( $teams, array() ); $status = array();
		foreach ( $feeds as $k => $f ) {
			$b = $bodies[ $k ] ?? array( 'error' => 'not fetched' );
			if ( ! is_string( $b ) ) { $status[ $k ] = array( 'ok' => false, 'err' => (string) ( $b['error'] ?? 'failed' ), 'n' => 0 ); continue; }
			$vids = self::parse( $b );
			if ( ! $vids ) { $status[ $k ] = array( 'ok' => false, 'err' => 'no videos in the feed', 'n' => 0 ); continue; }
			$n = 0;
			foreach ( $vids as $v ) {
				if ( $v[3] || $now - $v[2] > self::DAYS || $v[2] > $now + 3600 ) { continue; }  /* Shorts, too old, or dated ahead */
				$to = $f[1] === 'team' ? array( $f[2] ) : self::named( $v[1] );
				if ( $f[1] === 'nfl' && count( $to ) > 2 ) { continue; }  /* a league roundup */
				foreach ( $to as $t ) { if ( isset( $lists[ $t ] ) ) { $lists[ $t ][] = array( 'v' => $v[0], 'h' => $v[1], 'ts' => $v[2], 's' => $f[1] ); $n++; } }
			}
			$newest = max( array_map( function ( $v ) { return $v[2]; }, $vids ) );
			$status[ $k ] = array( 'ok' => true, 'err' => $now - $newest > self::DAYS ? 'nothing posted in the last 14 days' : '', 'n' => $n, 'last' => $newest );
		}
		$stores = array();
		foreach ( $teams as $t ) {
			$all = $lists[ $t ];
			foreach ( (array) ( $prev[ $t ]['v'] ?? array() ) as $o ) { if ( $now - (int) $o['ts'] <= self::DAYS ) { $all[] = $o; } }  /* what the last pass kept, still in the window */
			$seen = array(); $keep = array();
			usort( $all, function ( $a, $b ) { return $b['ts'] <=> $a['ts']; } );
			foreach ( $all as $o ) { if ( isset( $seen[ $o['v'] ] ) ) { continue; } $seen[ $o['v'] ] = 1; $keep[] = $o; if ( count( $keep ) >= self::CAP ) { break; } }
			$stores[ $t ] = array( 'at' => $now, 'v' => $keep );
		}
		return array( $stores, $status );
	}

	/* ------------------------------------------------------------------ on the site */

	public static function team( $t ) {
		if ( ! self::on() || DBD_Data::archived() ) { return array(); }
		$s = get_option( self::OPT . $t ); $now = DBD_News::now();
		return is_array( $s ) ? array_values( array_filter( (array) ( $s['v'] ?? array() ), function ( $o ) use ( $now ) { return $now - (int) $o['ts'] <= self::DAYS; } ) ) : array();
	}

	public static function age( $ts ) {
		$d = max( 0, DBD_News::now() - (int) $ts );
		if ( $d < HOUR_IN_SECONDS ) { return max( 1, intdiv( $d, 60 ) ) . 'm'; }
		if ( $d < DAY_IN_SECONDS ) { return intdiv( $d, HOUR_IN_SECONDS ) . 'h'; }
		return intdiv( $d, DAY_IN_SECONDS ) . 'd';
	}

	/**
	 * A video's thumbnail, linking to it on YouTube in a new tab. The NFL and the teams block their videos from playing on other sites (only a
	 * click shows it: "blocked it from display on this website"), so nothing plays in place (2.13.1).
	 */
	public static function play( $v, $title, $big = false ) {
		$id = preg_replace( '/[^\w-]/', '', (string) $v );
		return '<a class="dbd-play" href="' . esc_url( 'https://www.youtube.com/watch?v=' . $id ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( 'Watch on YouTube (opens a new tab): ' . $title ) . '">'
			. '<img src="' . esc_url( 'https://i.ytimg.com/vi/' . $id . '/' . ( $big ? 'hqdefault' : 'mqdefault' ) . '.jpg' ) . '" alt="" loading="lazy" decoding="async" width="' . ( $big ? 480 : 320 ) . '" height="' . ( $big ? 270 : 180 ) . '"><span class="yt">YouTube</span></a>';
	}

	/** "{Nick} videos" for the team players page, above the news; empty with nothing stored. */
	public static function panel( $t ) {
		$list = self::team( $t ); if ( ! $list ) { return ''; }
		$nick = DBD_Data::team( $t )['nick'] ?? $t; $cards = '';
		foreach ( $list as $i => $o ) {
			$cards .= '<figure class="dbd-vid' . ( $i >= self::TOP ? ' vd-more' : '' ) . '">' . self::play( $o['v'], $o['h'], $i === 0 ) . '<h4>'  /* the first, shown large, gets the bigger thumbnail (Greptile, PR #86) */ . esc_html( $o['h'] ) . '</h4>'
				. '<span>' . esc_html( ( $o['s'] === 'nfl' ? 'NFL' : $nick ) . ' · ' . self::age( $o['ts'] ) ) . '</span></figure>';
		}
		return '<section class="dbd-panel dbd-vids dbd-feature" id="videos"><div class="dbd-ph big"><h2>' . esc_html( $nick ) . ' videos</h2><p>The last 14 days, from the ' . esc_html( $nick ) . ' and the NFL on YouTube</p></div>'
			. '<div class="vd-grid">' . $cards . '</div>'
			. ( count( $list ) > self::TOP ? '<button type="button" class="nw-btn" onclick="this.parentNode.classList.add(\'open\');this.remove()">Show more</button>' : '' ) . '</section>';
	}

	/** A finished game's highlights, at the top of its page; empty until the NFL's recap is found. */
	public static function highlight( $g ) {
		if ( ! self::on() || ! DBD_Router::is_final( $g ) ) { return ''; }
		$h = ( (array) get_option( self::OPT_HL, array() ) )[ DBD_Data::season() ][ $g['id'] ] ?? null; if ( ! is_array( $h ) || empty( $h['v'] ) ) { return ''; }
		return '<section class="dbd-panel dbd-hl" id="highlights"><div class="dbd-ph"><span class="dbd-lab"><b>Highlights</b> · from the NFL on YouTube</span></div>'
			. '<div class="dbd-pb">' . self::play( $h['v'], (string) $h['h'], true ) . '<p>' . esc_html( (string) $h['h'] ) . '</p></div></section>';
	}
}
