<?php
/**
 * Team news (SCOPE §49): headlines from the teams' own sites, their SB Nation blogs, The Athletic and six league feeds,
 * matched to teams and shown on the team pages. Headline, source and time only, linking out; no article text or images.
 *
 * Every 6 hours a background cron pass downloads the 102 feeds, 8 at a time, keeps the stories of the last 7 days that
 * belong to a team, and stores one small list per team (option dbd_news_{TEAM}, not autoloaded). Raw feeds are never
 * stored. Each pass adds to the stories already stored (within the week), so a feed that fails or serves a stale copy loses nothing.
 * collect() is the whole matching step with no network,
 * so the CI harness runs it on fixture feeds.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_News {
	const HOOK       = 'dbd_news_event';
	const HOOK_FIRST = 'dbd_news_first';
	const EVERY      = 'dbd_6h';
	const OPT        = 'dbd_news_';         /* + team: that team's lists */
	const OPT_STATUS = 'dbd_news_status';   /* the last pass: when, how long, and each feed's state */
	const OPT_SRC    = 'dbd_news_sources';  /* the source groups switched on; false (never saved) means all */
	const WEEK       = 604800;
	const CAP        = 20;                  /* stories kept per column */
	const TOP        = 5;                   /* shown before "Show more", no more than two from one source */
	const BATCH      = 8;
	const UA         = 'Mozilla/5.0 (compatible; DownByDown/%s; +https://downbydown.com/)';

	/* the teams' own sites: https://www.{site}/rss/news */
	const SITES = array( 'ARI' => 'azcardinals.com', 'ATL' => 'atlantafalcons.com', 'BAL' => 'baltimoreravens.com', 'BUF' => 'buffalobills.com', 'CAR' => 'panthers.com',
		'CHI' => 'chicagobears.com', 'CIN' => 'bengals.com', 'CLE' => 'clevelandbrowns.com', 'DAL' => 'dallascowboys.com', 'DEN' => 'denverbroncos.com', 'DET' => 'detroitlions.com',
		'GB' => 'packers.com', 'HOU' => 'houstontexans.com', 'IND' => 'colts.com', 'JAX' => 'jaguars.com', 'KC' => 'chiefs.com', 'LV' => 'raiders.com', 'LAC' => 'chargers.com',
		'LA' => 'therams.com', 'MIA' => 'miamidolphins.com', 'MIN' => 'vikings.com', 'NE' => 'patriots.com', 'NO' => 'neworleanssaints.com', 'NYG' => 'giants.com',
		'NYJ' => 'newyorkjets.com', 'PHI' => 'philadelphiaeagles.com', 'PIT' => 'steelers.com', 'SF' => '49ers.com', 'SEA' => 'seahawks.com', 'TB' => 'buccaneers.com',
		'TEN' => 'tennesseetitans.com', 'WAS' => 'commanders.com' );
	/* SB Nation team blogs: https://www.{blog}/rss/index.xml */
	const FAN = array( 'ARI' => array( 'revengeofthebirds.com', 'Revenge of the Birds' ), 'ATL' => array( 'thefalcoholic.com', 'The Falcoholic' ), 'BAL' => array( 'baltimorebeatdown.com', 'Baltimore Beatdown' ),
		'BUF' => array( 'buffalorumblings.com', 'Buffalo Rumblings' ), 'CAR' => array( 'catscratchreader.com', 'Cat Scratch Reader' ), 'CHI' => array( 'windycitygridiron.com', 'Windy City Gridiron' ),
		'CIN' => array( 'cincyjungle.com', 'Cincy Jungle' ), 'CLE' => array( 'dawgsbynature.com', 'Dawgs By Nature' ), 'DAL' => array( 'bloggingtheboys.com', 'Blogging The Boys' ),
		'DEN' => array( 'milehighreport.com', 'Mile High Report' ), 'DET' => array( 'prideofdetroit.com', 'Pride Of Detroit' ), 'GB' => array( 'acmepackingcompany.com', 'Acme Packing Company' ),
		'HOU' => array( 'battleredblog.com', 'Battle Red Blog' ), 'IND' => array( 'stampedeblue.com', 'Stampede Blue' ), 'JAX' => array( 'bigcatcountry.com', 'Big Cat Country' ),
		'KC' => array( 'arrowheadpride.com', 'Arrowhead Pride' ), 'LV' => array( 'silverandblackpride.com', 'Silver And Black Pride' ), 'LAC' => array( 'boltsfromtheblue.com', 'Bolts From The Blue' ),
		'LA' => array( 'turfshowtimes.com', 'Turf Show Times' ), 'MIA' => array( 'thephinsider.com', 'The Phinsider' ), 'MIN' => array( 'dailynorseman.com', 'Daily Norseman' ),
		'NE' => array( 'patspulpit.com', 'Pats Pulpit' ), 'NO' => array( 'canalstreetchronicles.com', 'Canal Street Chronicles' ), 'NYG' => array( 'bigblueview.com', 'Big Blue View' ),
		'NYJ' => array( 'ganggreennation.com', 'Gang Green Nation' ), 'PHI' => array( 'bleedinggreennation.com', 'Bleeding Green Nation' ), 'PIT' => array( 'behindthesteelcurtain.com', 'Behind The Steel Curtain' ),
		'SF' => array( 'ninersnation.com', 'Niners Nation' ), 'SEA' => array( 'fieldgulls.com', 'Field Gulls' ), 'TB' => array( 'bucsnation.com', 'Bucs Nation' ),
		'TEN' => array( 'musiccitymiracles.com', 'Music City Miracles' ), 'WAS' => array( 'hogshaven.com', 'Hogs Haven' ) );
	/* The Athletic's team feeds: https://www.nytimes.com/athletic/rss/nfl/{slug}/. No Cardinals feed: their Athletic team page points at baseball's
	   Cardinals, so the league feed covers them. */
	const ATH = array( 'ATL' => 'falcons', 'BAL' => 'ravens', 'BUF' => 'bills', 'CAR' => 'panthers', 'CHI' => 'bears', 'CIN' => 'bengals', 'CLE' => 'browns', 'DAL' => 'cowboys',
		'DEN' => 'broncos', 'DET' => 'lions', 'GB' => 'packers', 'HOU' => 'texans', 'IND' => 'colts', 'JAX' => 'jaguars', 'KC' => 'chiefs', 'LV' => 'raiders', 'LAC' => 'chargers',
		'LA' => 'rams', 'MIA' => 'dolphins', 'MIN' => 'vikings', 'NE' => 'patriots', 'NO' => 'saints', 'NYG' => 'giants', 'NYJ' => 'jets', 'PHI' => 'eagles', 'PIT' => 'steelers',
		'SF' => 'niners', 'SEA' => 'seahawks', 'TB' => 'buccaneers', 'TEN' => 'titans', 'WAS' => 'commanders' );
	/* league feeds, matched to teams by the names in each headline */
	const LEAGUE = array( 'The Athletic' => 'https://www.nytimes.com/athletic/rss/nfl/', 'ProFootballTalk' => 'https://profootballtalk.nbcsports.com/feed/',
		'CBS Sports' => 'https://www.cbssports.com/rss/headlines/nfl/', 'ESPN' => 'https://www.espn.com/espn/rss/nfl/news', 'Yahoo Sports' => 'https://sports.yahoo.com/nfl/rss/',
		'Sporting News' => 'https://www.sportingnews.com/us/rss/nfl', 'NFL Trade Rumors' => 'https://www.nfltraderumors.co/feed/' );
	const SUB = array( 'The Athletic' => true );  /* sources whose articles mostly need a subscription: each link says so */
	/* college teams that share an NFL nickname ("Nittany Lions" isn't the Lions) */
	const COLLEGE = '(?<!Nittany )(?<!Penn State )(?<!Baylor )(?<!Pitt )(?<!Florida )(?<!Louisville )(?<!Winnipeg )(?<!Boston College )(?<!Colorado State )(?<!St\. Louis )(?<!Texas Southern )';

	public static $now = null;  /* the harness pins the clock */
	public static function now() { return self::$now ?? time(); }

	public static function init() {
		add_filter( 'cron_schedules', function ( $s ) { $s[ self::EVERY ] = array( 'interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Every 6 hours' ); return $s; } );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );
		add_action( self::HOOK_FIRST, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) { self::schedule(); }
		/* no news yet (a new install or the update that brings this): the first pass runs a minute or so later instead of waiting for the next 6-hour mark */
		if ( ! get_option( self::OPT_STATUS ) && ! wp_next_scheduled( self::HOOK_FIRST ) && ! get_transient( 'dbd_news_first' ) ) {
			set_transient( 'dbd_news_first', 1, HOUR_IN_SECONDS );
			wp_schedule_single_event( time() + 90, self::HOOK_FIRST );
		}
	}

	/** Every 6 hours from the next of 12, 6, 12 and 6 o'clock Eastern. */
	public static function schedule() {
		if ( wp_next_scheduled( self::HOOK ) ) { return; }
		$d = new DateTime( 'now', new DateTimeZone( 'America/New_York' ) );
		$h = ( intdiv( (int) $d->format( 'G' ), 6 ) + 1 ) * 6;
		$d->setTime( 0, 0 ); $d->modify( '+' . $h . ' hours' );
		wp_schedule_event( $d->getTimestamp(), self::EVERY, self::HOOK );
	}

	public static function unschedule() { wp_clear_scheduled_hook( self::HOOK ); wp_clear_scheduled_hook( self::HOOK_FIRST ); }

	/* ---- the sources */

	/** The source groups a setting can switch off: the team sites, the SB Nation blogs, The Athletic (team and league feeds), and each other league feed. */
	public static function groups() {
		$g = array( 'site' => 'The teams\' own sites', 'fan' => 'SB Nation team blogs', 'ath' => 'The Athletic (team and league feeds)' );
		foreach ( array_keys( self::LEAGUE ) as $l ) { if ( $l !== 'The Athletic' ) { $g[ 'l:' . $l ] = $l; } }
		return $g;
	}
	public static function on( $group ) { $o = get_option( self::OPT_SRC, false ); return ! is_array( $o ) || in_array( $group, $o, true ); }

	public static function site_label( $team ) { $s = self::SITES[ $team ] ?? ''; return $s === '49ers.com' ? '49ers.com' : ucfirst( $s ); }

	/** Every feed: key => array( url, kind, team or '', source label, group ). */
	public static function feeds() {
		$f = array();
		foreach ( self::SITES as $t => $d ) { $f[ 'site:' . $t ] = array( 'https://www.' . $d . '/rss/news', 'site', $t, self::site_label( $t ), 'site' ); }
		foreach ( self::FAN as $t => $x ) { $f[ 'fan:' . $t ] = array( 'https://www.' . $x[0] . '/rss/index.xml', 'fan', $t, $x[1], 'fan' ); }
		foreach ( self::ATH as $t => $s ) { $f[ 'ath:' . $t ] = array( 'https://www.nytimes.com/athletic/rss/nfl/' . $s . '/', 'ath', $t, 'The Athletic', 'ath' ); }
		foreach ( self::LEAGUE as $l => $u ) { $f[ 'league:' . $l ] = array( $u, 'league', '', $l, $l === 'The Athletic' ? 'ath' : 'l:' . $l ); }
		return $f;
	}

	/* ---- one pass */

	/** The cron pass: download the switched-on feeds 8 at a time, match, store. */
	public static function run() {
		if ( get_transient( 'dbd_news_lock' ) ) { return; }
		set_transient( 'dbd_news_lock', 1, 5 * MINUTE_IN_SECONDS );
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 120 ); }
		$t0 = microtime( true ); $feeds = array_filter( self::feeds(), function ( $x ) { return self::on( $x[4] ); } );
		$bodies = self::fetch( $feeds );
		$prev = array(); foreach ( array_keys( self::SITES ) as $t ) { $prev[ $t ] = get_option( self::OPT . $t, array() ); }
		list( $stores, $status ) = self::collect( $feeds, $bodies, $prev, (array) ( get_option( self::OPT_STATUS )['feeds'] ?? array() ) );
		foreach ( $stores as $t => $s ) { update_option( self::OPT . $t, $s, false ); }
		update_option( self::OPT_STATUS, array( 'at' => self::now(), 'secs' => round( microtime( true ) - $t0, 1 ), 'feeds' => $status ), false );
		delete_transient( 'dbd_news_lock' );
	}

	/** Download the feeds in parallel batches. Returns key => body string, or key => WP_Error-like array( 'error' => message ). */
	public static function fetch( $feeds ) {
		$out = array(); $ua = sprintf( self::UA, DBD_VERSION );
		$multi = class_exists( '\WpOrg\Requests\Requests' );
		foreach ( array_chunk( $feeds, self::BATCH, true ) as $chunk ) {
			if ( $multi ) {
				$req = array();
				foreach ( $chunk as $k => $x ) { $req[ $k ] = array( 'url' => $x[0], 'type' => 'GET', 'headers' => array( 'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml' ) ); }
				try {
					$res = \WpOrg\Requests\Requests::request_multiple( $req, array( 'timeout' => 15, 'connect_timeout' => 10, 'useragent' => $ua, 'redirects' => 5, 'verify' => ABSPATH . WPINC . '/certificates/ca-bundle.crt' ) );
				} catch ( \Throwable $e ) { $res = array(); }
				foreach ( $chunk as $k => $x ) {
					$r = $res[ $k ] ?? null;
					if ( $r instanceof \WpOrg\Requests\Response && $r->status_code === 200 ) { $out[ $k ] = (string) $r->body; }
					else { $out[ $k ] = array( 'error' => $r instanceof \WpOrg\Requests\Response ? 'HTTP ' . $r->status_code : ( $r instanceof \Throwable ? $r->getMessage() : 'no response' ) ); }
				}
			} else {
				foreach ( $chunk as $k => $x ) {
					$r = wp_remote_get( $x[0], array( 'timeout' => 10, 'user-agent' => $ua, 'redirection' => 5 ) );
					$c = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
					$out[ $k ] = $c === 200 ? (string) wp_remote_retrieve_body( $r ) : array( 'error' => is_wp_error( $r ) ? $r->get_error_message() : 'HTTP ' . $c );
				}
			}
		}
		return $out;
	}

	/** A feed's stories: RSS items or Atom entries, as array( title, url, ts ). Read with patterns, not an XML parser, so one bad character doesn't lose a feed. */
	public static function parse( $xml ) {
		$out = array(); $clean = function ( $s ) { return trim( html_entity_decode( strip_tags( str_replace( array( '<![CDATA[', ']]>' ), '', (string) $s ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ); };
		if ( preg_match_all( '#<item\b.*?</item>#s', $xml, $m ) ) {
			foreach ( $m[0] as $it ) {
				if ( ! preg_match( '#<title[^>]*>(.*?)</title>#s', $it, $t ) || ! preg_match( '#<link[^>]*>(.*?)</link>#s', $it, $l ) ) { continue; }
				$d = preg_match( '#<pubDate>(.*?)</pubDate>#s', $it, $dm ) ? strtotime( $clean( $dm[1] ) ) : 0;
				$out[] = array( $clean( $t[1] ), $clean( $l[1] ), (int) $d );
			}
		}
		if ( preg_match_all( '#<entry\b.*?</entry>#s', $xml, $m ) ) {
			foreach ( $m[0] as $it ) {
				if ( ! preg_match( '#<title[^>]*>(.*?)</title>#s', $it, $t ) ) { continue; }
				if ( ! preg_match( '#<link[^>]*rel="alternate"[^>]*href="([^"]+)"#', $it, $l ) && ! preg_match( '#<link[^>]*href="([^"]+)"#', $it, $l ) ) { continue; }
				$d = preg_match( '#<published>(.*?)</published>#', $it, $dm ) || preg_match( '#<updated>(.*?)</updated>#', $it, $dm ) ? strtotime( trim( $dm[1] ) ) : 0;
				$out[] = array( $clean( $t[1] ), html_entity_decode( $l[1], ENT_QUOTES, 'UTF-8' ), (int) $d );
			}
		}
		return array_values( array_filter( $out, function ( $x ) { return $x[0] !== '' && preg_match( '#^https?://#', $x[1] ); } ) );
	}

	/** The players a headline names: unique full names (first and last, however short: "Bo Nix") from the players index, as whole words, at most two. */
	private static function names() {
		static $n = null; if ( $n !== null ) { return $n; }
		$c = array(); foreach ( DBD_Data::player_index() as $p ) { $nm = trim( (string) ( $p['name'] ?? '' ) ); if ( strpos( $nm, ' ' ) !== false ) { $c[ $nm ][] = $p; } }
		$n = array(); foreach ( $c as $name => $ps ) { if ( count( $ps ) === 1 ) { $n[ $name ] = array( $ps[0]['id'], $ps[0]['team'] ); } }
		return $n;
	}
	private static function who( $title ) {
		$o = array();
		foreach ( self::names() as $name => $x ) {
			if ( strpos( $title, $name ) === false || ! preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $name, '/' ) . '(?![\p{L}\p{N}])/u', $title ) ) { continue; }  /* "Bo Nix", not "Bo Nixon" */
			$o[] = array( $x[0], $name, $x[1] ); if ( count( $o ) === 2 ) { break; }
		}
		return $o;
	}
	private static function nick_re() {
		static $r = null; if ( $r !== null ) { return $r; }
		$r = array(); foreach ( DBD_Data::teams() as $t => $x ) { $r[ $t ] = '/' . self::COLLEGE . '\b(' . preg_quote( $x['nick'], '/' ) . '|' . preg_quote( $x['name'], '/' ) . ')\b/u'; }
		return $r;
	}
	public static function names_team( $t, $title ) { $re = self::nick_re(); return isset( $re[ $t ] ) && preg_match( $re[ $t ], $title ); }

	/**
	 * Match every feed's stories to teams. $bodies: key => body or array( 'error' => … ). $prev: team => last stored lists (a failed feed's stories
	 * carry over from them). Returns array( team => array( 'at', 't' => team column, 'l' => league column ), feed key => status ).
	 */
	public static function collect( $feeds, $bodies, $prev = array(), $prev_status = array() ) {
		$now = self::now(); $teams = array_keys( self::SITES );
		$col = array(); foreach ( $teams as $t ) { $col[ $t ] = array( 't' => array(), 'l' => array() ); }
		$status = array();
		/* each feed's newest story as stored last time, to spot a feed that hands back an older copy of itself (a CDN node's stale cache, 2.9.1) */
		$had = array(); foreach ( $prev as $pt ) { foreach ( array( 't', 'l' ) as $c ) { foreach ( (array) ( $pt[ $c ] ?? array() ) as $o ) { $f = (string) ( $o['f'] ?? '' ); $had[ $f ] = max( $had[ $f ] ?? 0, (int) $o['ts'] ); } } }
		$item = function ( $s, $key, $src, $who, $team ) {
			$w = array(); foreach ( $who as $q ) { if ( $q[2] === $team ) { $w[] = array( $q[0], $q[1] ); } }
			return array( 'h' => $s[0], 'u' => $s[1], 'ts' => $s[2], 's' => $src, 'f' => $key, 'x' => isset( self::SUB[ $src ] ) ? 1 : 0, 'w' => $w );
		};
		$league = array();
		foreach ( $feeds as $key => $x ) {
			list( , $kind, $team, $src ) = $x;
			$b = $bodies[ $key ] ?? array( 'error' => 'not fetched' );
			if ( is_array( $b ) ) { $status[ $key ] = array( 'ok' => 0, 'n' => 0, 'err' => $b['error'], 'last' => (int) ( $prev_status[ $key ]['last'] ?? 0 ) ); continue; }
			$all = self::parse( $b ); $fresh = array_values( array_filter( $all, function ( $s ) use ( $now ) { return $s[2] && $now - $s[2] <= self::WEEK && $s[2] <= $now + 3600; } ) );
			$kept = 0;
			if ( $kind === 'league' ) { foreach ( $fresh as $s ) { $league[] = array( $s, $key, $src ); } $kept = count( $fresh ); }
			else {
				foreach ( $fresh as $s ) {
					$who = self::who( $s[0] );
					/* The Athletic's team feeds carry league-wide pieces too: keep a story only when it names the team or one of its players */
					if ( $kind === 'ath' && ! self::names_team( $team, $s[0] ) && ! array_filter( $who, function ( $q ) use ( $team ) { return $q[2] === $team; } ) ) { continue; }
					$col[ $team ]['t'][] = $item( $s, $key, $src, $who, $team ); $kept++;
				}
			}
			$newest = 0; foreach ( $all as $s ) { $newest = max( $newest, (int) $s[2] ); }
			$err = $all ? ( $fresh ? '' : 'nothing from the last 7 days' ) : 'no stories in the feed';
			if ( $all && isset( $had[ $key ] ) && $newest < $had[ $key ] ) { $err = 'sent an older copy of itself; the newer stories were kept'; }
			$status[ $key ] = array( 'ok' => 1, 'n' => $kept, 'all' => count( $all ), 'err' => $err, 'last' => $now );
		}
		/* league stories: every team the headline names or whose player it names; none when it names four or more; once each */
		$seen = array();
		foreach ( $league as $L ) {
			list( $s, $key, $src ) = $L; $k = preg_replace( '/\W+/u', '', strtolower( $s[0] ) );
			if ( isset( $seen[ $s[1] ] ) || isset( $seen[ $k ] ) ) { continue; }
			$seen[ $s[1] ] = 1; $seen[ $k ] = 1;
			$who = self::who( $s[0] ); $ts = array();
			foreach ( $teams as $t ) { if ( self::names_team( $t, $s[0] ) ) { $ts[ $t ] = 1; } }
			foreach ( $who as $q ) { if ( isset( $col[ $q[2] ] ) ) { $ts[ $q[2] ] = 1; } }
			if ( ! $ts || count( $ts ) > 3 ) { continue; }
			foreach ( array_keys( $ts ) as $t ) { $col[ $t ]['l'][] = $item( $s, $key, $src, $who, $t ); }
		}
		/* every story stored last time stays while it's within the week, and this pass adds to it: a feed that fails, or hands back an older
		   copy of itself (a CDN node's stale cache), can't take newer stories away (2.9.1). Only feeds still switched on carry over; a fresh
		   copy of a story comes first, so the dedupe below keeps it. */
		foreach ( $teams as $t ) {
			foreach ( array( 't', 'l' ) as $c ) {
				foreach ( (array) ( $prev[ $t ][ $c ] ?? array() ) as $o ) { if ( isset( $feeds[ $o['f'] ?? '' ] ) && $now - (int) $o['ts'] <= self::WEEK ) { $col[ $t ][ $c ][] = $o; } }
			}
		}
		$stores = array();
		foreach ( $teams as $t ) {
			$stores[ $t ] = array( 'at' => $now );
			$key = function ( $o ) { return preg_replace( '/\W+/u', '', strtolower( $o['h'] ) ); };
			$pick = function ( $items, $skip ) use ( $key ) {
				$u = $skip; $list = array();
				foreach ( $items as $o ) { $k = $key( $o ); if ( isset( $u[ $o['u'] ] ) || isset( $u[ $k ] ) ) { continue; } $u[ $o['u'] ] = 1; $u[ $k ] = 1; $list[] = $o; }
				return array_slice( self::balance( $list ), 0, self::CAP );
			};
			$stores[ $t ]['t'] = $pick( $col[ $t ]['t'], array() );
			/* a story shows once on the panel: a league story whose link or headline is in the team column as stored (after the cap) is left out (Greptile, PR #72) */
			$in = array(); foreach ( $stores[ $t ]['t'] as $o ) { $in[ $o['u'] ] = 1; $in[ $key( $o ) ] = 1; }
			$stores[ $t ]['l'] = $pick( $col[ $t ]['l'], $in );
		}
		return array( $stores, $status );
	}

	/** Newest first, but no more than two from one source in the first five. */
	public static function balance( $list ) {
		$by = function ( $a, $b ) { return $b['ts'] <=> $a['ts']; };
		usort( $list, $by );
		$top = array(); $rest = array(); $n = array();
		foreach ( $list as $o ) { if ( count( $top ) < self::TOP && ( $n[ $o['s'] ] ?? 0 ) < 2 ) { $top[] = $o; $n[ $o['s'] ] = ( $n[ $o['s'] ] ?? 0 ) + 1; } else { $rest[] = $o; } }
		while ( count( $top ) < self::TOP && $rest ) { $top[] = array_shift( $rest ); }  /* too few sources to keep to two each: fill with the newest */
		usort( $top, $by );
		return array_merge( $top, $rest );
	}

	/* ---- on the page */

	/** A team's stored lists with anything older than 7 days dropped (a pass that stalls for days doesn't leave old news up). Empty on an archived season. */
	public static function team( $t ) {
		if ( DBD_Data::archived() || ! array_filter( array_keys( self::groups() ), array( __CLASS__, 'on' ) ) ) { return null; }  /* every source switched off hides the news */
		$s = get_option( self::OPT . $t ); if ( ! is_array( $s ) ) { return null; }
		$now = self::now();
		foreach ( array( 't', 'l' ) as $c ) { $s[ $c ] = array_values( array_filter( (array) ( $s[ $c ] ?? array() ), function ( $o ) use ( $now ) { return $now - (int) $o['ts'] <= self::WEEK; } ) ); }
		return $s;
	}

	public static function age( $ts ) {
		$d = max( 0, self::now() - (int) $ts );
		if ( $d < HOUR_IN_SECONDS ) { return max( 1, intdiv( $d, 60 ) ) . 'm'; }
		if ( $d < DAY_IN_SECONDS ) { return intdiv( $d, HOUR_IN_SECONDS ) . 'h'; }
		return wp_date( 'D', (int) $ts, new DateTimeZone( 'America/New_York' ) );
	}

	private static function row( $o, $more ) {
		$who = ''; foreach ( (array) ( $o['w'] ?? array() ) as $w ) { $who .= ' · <a class="nw-p" href="' . esc_url( DBD_Router::url( array( 'player' => $w[0] ) ) ) . '">' . esc_html( $w[1] ) . '</a>'; }
		return '<li' . ( $more ? ' class="nw-more"' : '' ) . '><a class="nw-h" href="' . esc_url( $o['u'] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $o['h'] ) . '</a>'
			. '<span class="nw-m">' . esc_html( $o['s'] ) . ( ! empty( $o['x'] ) ? ' · <i>Subscriber</i>' : '' ) . ' · ' . esc_html( self::age( $o['ts'] ) ) . $who . '</span></li>';
	}

	/** The team column's heading: the sources this team has, switched on. */
	public static function team_sources( $t ) {
		$n = array(); if ( self::on( 'site' ) ) { $n[] = self::site_label( $t ); } if ( self::on( 'fan' ) && isset( self::FAN[ $t ] ) ) { $n[] = self::FAN[ $t ][1]; } if ( self::on( 'ath' ) && isset( self::ATH[ $t ] ) ) { $n[] = 'The Athletic'; }
		return count( $n ) > 1 ? implode( ', ', array_slice( $n, 0, -1 ) ) . ' and ' . end( $n ) : ( $n[0] ?? 'The team' );
	}

	/** The "{Nick} news" panel for the team players page; empty until the first pass has run. */
	public static function panel( $t ) {
		$s = self::team( $t ); if ( ! $s ) { return ''; }
		$T = DBD_Data::team( $t ); $nick = $T['nick'] ?? $t; $src = self::team_sources( $t );
		$col = function ( $h, $list, $empty ) {
			$o = '<div class="nw-col"><h3>' . esc_html( $h ) . '</h3>';
			if ( ! $list ) { return $o . '<p class="nw-empty">' . esc_html( $empty ) . '</p></div>'; }
			$o .= '<ol>'; foreach ( $list as $i => $x ) { $o .= self::row( $x, $i >= self::TOP ); } $o .= '</ol>';
			if ( count( $list ) > self::TOP ) { $o .= '<button type="button" class="nw-btn" onclick="this.parentNode.classList.add(\'open\');this.remove()">Show more</button>'; }
			return $o . '</div>';
		};
		return '<section class="dbd-panel dbd-news dbd-feature" id="news"><div class="dbd-ph big"><h2>' . esc_html( $nick ) . ' news</h2><p>The last 7 days, from the team, its fans and around the league</p></div>'
			. '<div class="nw-cols">' . $col( $src, $s['t'], 'No stories from ' . $src . ' this week.' ) . $col( 'Around the league', $s['l'], 'No ' . $nick . ' stories from the league feeds this week.' ) . '</div></section>';
	}

	/**
	 * The news and video block at the end of a page (2.13.0, SCOPE §53-54): a news half (a lead story large, up to two more, a link to all) and
	 * a video half. Either half alone when the other is empty; nothing with neither. $vid: array( item, heading, meta, link url, link text ) or null.
	 */
	public static function block( $id, $news_h, $news, $all_url, $all_text, $vid ) {
		if ( ! $news && ! $vid ) { return ''; }
		$meta = function ( $o ) { return esc_html( $o['s'] ) . ( ! empty( $o['x'] ) ? ' · Subscriber' : '' ) . ' · ' . esc_html( self::age( $o['ts'] ) ); };
		$o = '<section class="dbd-around' . ( $news && $vid ? '' : ' one' ) . '" id="' . esc_attr( $id ) . '">';
		if ( $news ) {
			$lead = array_shift( $news );
			$o .= '<div class="ar-news"><h2 class="ar-t">' . esc_html( $news_h ) . '</h2>'
				. '<a class="ar-lead" href="' . esc_url( $lead['u'] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $lead['h'] ) . '</a><span class="ar-m">' . $meta( $lead ) . '</span>';
			if ( $news ) { $o .= '<ul class="ar-more">'; foreach ( $news as $x ) { $o .= '<li><a href="' . esc_url( $x['u'] ) . '" target="_blank" rel="nofollow noopener">' . esc_html( $x['h'] ) . '</a><span class="ar-m">' . $meta( $x ) . '</span></li>'; } $o .= '</ul>'; }
			$o .= $all_url !== '' ? '<a class="ar-all" href="' . esc_url( $all_url ) . '">' . esc_html( $all_text ) . '</a></div>' : '</div>';
		}
		if ( $vid ) {
			list( $it, $vh, $vm, $vu, $vt ) = $vid;
			$o .= '<div class="ar-vid"><h2 class="ar-t">' . esc_html( $vh ) . '</h2>' . DBD_Videos::play( $it['v'], (string) $it['h'], true ) . '<h3>' . esc_html( (string) $it['h'] ) . '</h3>'
				. '<span class="ar-m">' . esc_html( $vm ) . ( $vu !== '' ? ' · <a href="' . esc_url( $vu ) . '">' . esc_html( $vt ) . '</a>' : '' ) . '</span></div>';
		}
		return $o . '</section>';
	}

	/** Every stored story about a team (its own column and the league's), newest first, without repeats. */
	private static function all_of( $t ) {
		$s = self::team( $t ); if ( ! $s ) { return array(); }
		$l = array_merge( (array) $s['t'], (array) $s['l'] ); usort( $l, function ( $a, $b ) { return (int) $b['ts'] <=> (int) $a['ts']; } );
		$seen = array(); return array_values( array_filter( $l, function ( $o ) use ( &$seen ) { if ( isset( $seen[ $o['u'] ] ) ) { return false; } $seen[ $o['u'] ] = 1; return true; } ) );
	}

	/** The newest stories naming both teams of a game (from either team's stored news), up to $n. */
	public static function both( $a, $b, $n = 3 ) {
		$out = array(); $seen = array();
		foreach ( array_merge( self::all_of( $a ), self::all_of( $b ) ) as $o ) {
			if ( isset( $seen[ $o['u'] ] ) || ! self::names_team( $a, $o['h'] ) || ! self::names_team( $b, $o['h'] ) ) { continue; }
			$seen[ $o['u'] ] = 1; $out[] = $o;
		}
		usort( $out, function ( $x, $y ) { return (int) $y['ts'] <=> (int) $x['ts']; } );
		return array_slice( $out, 0, $n );
	}

	/** The newest stories that name a player (the news tags them by id), from his team's stored news, up to $n. */
	public static function about( $pid, $team, $n = 3 ) {
		$out = array();
		foreach ( self::all_of( $team ) as $o ) { foreach ( (array) ( $o['w'] ?? array() ) as $w ) { if ( ( $w[0] ?? '' ) === $pid ) { $out[] = $o; break; } } }
		return array_slice( $out, 0, $n );
	}

	/** The team's newest stories from its own column, newest first. */
	public static function newest( $t, $n = 3 ) {
		$s = self::team( $t ); if ( ! $s || ! $s['t'] ) { return array(); }
		$l = $s['t']; usort( $l, function ( $a, $b ) { return (int) $b['ts'] <=> (int) $a['ts']; } );
		return array_slice( $l, 0, $n );
	}

	/**
	 * At the end of the offense and defense pages, after the explorer: the team's newest stories and its newest video. On the defense page the
	 * video is the NFL's "{Team}' best defensive plays" when one is from the last 14 days (2.14.0).
	 */
	public static function around( $t, $def = false ) {
		$nick = DBD_Data::team( $t )['nick'] ?? $t; $pp = DBD_Router::url( array( 'view' => 'players', 'team' => $t ) );
		$dv = $def ? DBD_Videos::defense( $t ) : null; $vids = $dv ? array() : ( class_exists( 'DBD_Videos' ) ? DBD_Videos::team( $t ) : array() );
		$vid = $dv ? array( $dv, 'Defense highlights', 'NFL on YouTube', '', '' )
			: ( $vids ? array( $vids[0], 'Newest video', ( $vids[0]['s'] === 'nfl' ? 'NFL' : $nick ) . ' · ' . DBD_Videos::age( $vids[0]['ts'] ), $pp . '#videos', 'All ' . $nick . ' videos' ) : null );
		return self::block( 'around', $nick . ' news', self::newest( $t, 3 ), $pp . '#news', 'All ' . $nick . ' news', $vid );
	}

	/** At the end of a game's page: the news naming both teams, and the NFL's preview (before) or recap (after). */
	public static function game_block( $g ) {
		$f = DBD_Router::is_final( $g ); $gv = DBD_Videos::game_video( $g );
		$vid = $gv ? array( $gv[0], $gv[1] === 'recap' ? 'The highlights' : 'The NFL preview', 'NFL on YouTube', '', '' ) : null;
		return self::block( 'around', $f ? 'After the game' : 'Before the game', self::both( $g['away'], $g['home'], 3 ), '', '', $vid );
	}

	/** At the end of a player's page: the stories naming him, and his newest highlights. */
	public static function player_block( $pid, $name, $team ) {
		$hw = DBD_Videos::player_weeks( $pid ); $last = $hw ? end( $hw ) : null; $lw = $hw ? array_key_last( $hw ) : 0;
		$vid = $last ? array( $last, 'His highlights', DBD_Router::week_label( (int) $lw ) . ' · NFL on YouTube', '', '' ) : null;
		return self::block( 'around', $name . ' in the news', self::about( $pid, $team, 3 ), '', '', $vid );
	}
}
