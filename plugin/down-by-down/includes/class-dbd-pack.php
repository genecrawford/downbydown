<?php
/**
 * The share pack (2.27.0, SCOPE §68; six since §75): the things to post each week, for the Share pack tab in the admin. Built from the newest
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

	/* ---- Post to r/downbydown (2.29.0, SCOPE §71): Reddit's new-post form, filled in from its address. A link post, so the page's share card is the preview. */

	const OPT_SUB = 'dbd_pack_sub'; const SUB_DEFAULT = 'downbydown'; const REDDIT_TITLE_MAX = 300;

	/** Gene's subreddit: letters, digits and underscores, up to 21 (Reddit's own rule). '' turns the buttons off. */
	public static function sanitize_sub( $v ) {
		$v = trim( (string) $v ); $v = (string) preg_replace( '#^(https?://)?(www\.|old\.)?(reddit\.com)?/*(r/)?#i', '', $v );
		return substr( (string) preg_replace( '/[^A-Za-z0-9_]/', '', $v ), 0, 21 );
	}
	public static function our_sub() { return self::sanitize_sub( get_option( self::OPT_SUB, self::SUB_DEFAULT ) ); }

	/** The address an item's post points at: the page, plus ?share= for the cards only the pack has, so the page's preview is that card. */
	public static function share_link( $it ) {
		$card = (string) ( $it['card'] ?? '' );
		return strpos( $card, 'pack-' ) === 0 && DBD_Card::valid( $card ) ? add_query_arg( array( 'share' => $card ), (string) $it['link'] ) : (string) $it['link'];
	}

	/** Reddit's new-post form with the title and link filled in: our subreddit, or $sub (a team's, 2.29.1), or '' when there's none. */
	public static function reddit_url( $title, $link, $sub = null ) {
		$sub = $sub === null ? self::our_sub() : self::sanitize_sub( $sub ); if ( $sub === '' ) { return ''; }
		$title = (string) $title;
		if ( mb_strlen( $title ) > self::REDDIT_TITLE_MAX ) { $title = rtrim( (string) preg_replace( '/\s+\S*$/u', '', mb_substr( $title, 0, self::REDDIT_TITLE_MAX - 1 ) ), ' ,;:' ) . '…'; }
		return 'https://www.reddit.com/r/' . $sub . '/submit?url=' . rawurlencode( (string) $link ) . '&title=' . rawurlencode( $title );
	}

	/* ---- Post on X and Post on Bluesky (2.30.0, SCOPE §72): each site's official compose link, the post written, link included */

	const OPT_X = 'dbd_pack_x'; const X_DEFAULT = 'dxd_NFLfootball';
	const OPT_BSKY = 'dbd_pack_bsky'; const BSKY_DEFAULT = 'downbydown.bsky.social'; const BSKY_MAX = 300;

	/** An X handle: a pasted @name or profile address gives the name; letters, digits, underscores, up to 15. */
	public static function sanitize_x( $v ) {
		$v = trim( (string) $v ); $v = (string) preg_replace( '#^(https?://)?(www\.)?(x|twitter)\.com/#i', '', $v ); $v = ltrim( $v, '@' );
		$v = (string) preg_replace( '#[/?].*$#', '', $v );
		return substr( (string) preg_replace( '/[^A-Za-z0-9_]/', '', $v ), 0, 15 );
	}
	/** A Bluesky handle: a pasted @handle or profile address gives the handle; lowercase letters, digits, dots and hyphens. */
	public static function sanitize_bsky( $v ) {
		$v = trim( (string) $v ); $v = (string) preg_replace( '#^(https?://)?(www\.)?bsky\.app/profile/#i', '', $v ); $v = ltrim( $v, '@' );
		$v = (string) preg_replace( '#[/?].*$#', '', $v );
		return substr( trim( (string) preg_replace( '/[^a-z0-9.\-]/', '', strtolower( $v ) ), '.-' ), 0, 253 );
	}
	public static function our_x() { return self::sanitize_x( get_option( self::OPT_X, self::X_DEFAULT ) ); }
	public static function our_bsky() { return self::sanitize_bsky( get_option( self::OPT_BSKY, self::BSKY_DEFAULT ) ); }

	/** The Bluesky post: like post(), but Bluesky counts the whole link against its 300. */
	public static function bpost( $long, $short, $link ) {
		foreach ( array( $long, $short ) as $t ) { $p = $t . ' ' . $link; if ( mb_strlen( $p ) <= self::BSKY_MAX ) { return $p; } }
		$room = max( 20, self::BSKY_MAX - mb_strlen( $link ) - 2 ); $t = mb_substr( (string) $short, 0, $room ); $t = preg_replace( '/\s+\S*$/u', '', $t );
		return rtrim( (string) $t, ' ,;:' ) . '… ' . $link;
	}

	/** An item's or keys side's two compose texts, with the share link (so the pack-only cards are the preview): array( 'x' => …, 'bsky' => … ). */
	public static function social( $it ) {
		$l = self::share_link( $it ); $tl = (string) ( $it['tl'] ?? $it['title'] ?? '' ); $ts = (string) ( $it['ts'] ?? $tl );
		return array( 'x' => self::post( $tl, $ts, $l ), 'bsky' => self::bpost( $tl, $ts, $l ) );
	}
	public static function x_url( $text ) { return self::our_x() === '' ? '' : 'https://x.com/intent/post?text=' . rawurlencode( (string) $text ); }
	public static function bsky_url( $text ) { return self::our_bsky() === '' ? '' : 'https://bsky.app/intent/compose?text=' . rawurlencode( (string) $text ); }

	/* ---- Share on Facebook, the Instagram caption and the Instagram square (2.31.0, SCOPE §73) */

	const OPT_FB = 'dbd_pack_fb'; const FB_DEFAULT = 'https://www.facebook.com/groups/988685080499981';
	const OPT_IG = 'dbd_pack_ig'; const IG_DEFAULT = 'down_by_down';
	const OPT_TH = 'dbd_pack_th'; const TH_DEFAULT = 'down_by_down';  /* Threads (2.41.0, SCOPE §84): a Threads profile comes from the Instagram account */

	/** The Facebook group's address: only a facebook.com address, kept as https://www.facebook.com/…; anything else gives ''. */
	public static function sanitize_fb( $v ) {
		$v = trim( (string) $v ); if ( $v === '' ) { return ''; }
		if ( ! preg_match( '#^(?:https?://)?(?:www\.|m\.|web\.)?facebook\.com(/[A-Za-z0-9._/\-]*)?#i', $v, $m ) ) { return ''; }
		$path = rtrim( (string) ( $m[1] ?? '' ), '/' ); return $path === '' ? '' : 'https://www.facebook.com' . $path;
	}
	/** An Instagram handle: a pasted @name or profile address gives the name; letters, digits, dots, underscores, up to 30. */
	public static function sanitize_ig( $v ) {
		$v = trim( (string) $v ); $v = (string) preg_replace( '#^(https?://)?(www\.)?instagram\.com/#i', '', $v ); $v = ltrim( $v, '@' );
		$v = (string) preg_replace( '#[/?].*$#', '', $v );
		return substr( (string) preg_replace( '/[^A-Za-z0-9._]/', '', $v ), 0, 30 );
	}
	public static function our_fb() { return self::sanitize_fb( get_option( self::OPT_FB, self::FB_DEFAULT ) ); }
	public static function our_ig() { return self::sanitize_ig( get_option( self::OPT_IG, self::IG_DEFAULT ) ); }
	/** A Threads handle: a pasted @name or threads.com/@name (or threads.net) address gives the name; the Instagram rules. */
	public static function sanitize_th( $v ) { return self::sanitize_ig( (string) preg_replace( '#^(https?://)?(www\.)?threads\.(com|net)/@?#i', '', trim( (string) $v ) ) ); }
	public static function our_th() { return self::sanitize_th( get_option( self::OPT_TH, self::TH_DEFAULT ) ); }
	/** Post on Threads (2.42.0): Threads' compose page with the item's post filled in (its long takeaway, as Auto publish sends it) and the page as the card; '' when there's no Threads account. */
	public static function th_url( $it ) { return self::our_th() === '' ? '' : 'https://www.threads.com/intent/post?text=' . rawurlencode( DBD_Threads::text( $it ) ) . '&url=' . rawurlencode( self::share_link( $it ) ); }

	/** Facebook's share box for an item or keys side (its share link, so the pack card is the preview), or '' when no group is set. */
	public static function fb_url( $it ) { return self::our_fb() === '' ? '' : 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( self::share_link( $it ) ); }
	/** The text the Facebook button copies: the takeaway, no link (the card carries it). */
	public static function fb_text( $it ) { return (string) ( $it['tl'] ?? $it['title'] ?? '' ); }

	/** A team's hashtag: its nickname without spaces (#49ers, #Commanders). */
	public static function tag( $t ) { return '#' . preg_replace( '/[^A-Za-z0-9]/', '', self::nick( $t ) ); }
	/** The Instagram caption: the takeaway, the bio line, then #NFL, the teams, and #FantasyFootball on the usage items; '' when no account is set. */
	public static function ig_caption( $it, $force = false ) {
		if ( ! $force && self::our_ig() === '' ) { return ''; }  /* $force: the plugin's own Instagram posts (SCOPE §76) */
		$tags = array( '#NFL' ); foreach ( (array) ( $it['teams'] ?? array() ) as $t ) { $tags[] = self::tag( $t ); } if ( ! empty( $it['ff'] ) ) { $tags[] = '#FantasyFootball'; }
		$host = (string) preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( home_url( '/' ) ) );
		return self::fb_text( $it ) . "\n\nFull chart: link in bio (" . $host . ")\n\n" . implode( ' ', array_unique( $tags ) );
	}

	/** What a square draws: the headline and the card under it, from its key (ig-week-N-swing, … or ig-week-N-keys-{game}-{team}), or null. */
	public static function spec_square( $key ) {
		if ( ! preg_match( '/^ig-week-(\d{1,2})-(swing|snaps|offense|targets|leaders|rush|keys-(\d{4}_\d{2}_[A-Z]{2,3}_[A-Z]{2,3})-([A-Z]{2,3}))$/', (string) $key, $m ) ) { return null; }
		$n = (int) $m[1];
		if ( empty( $m[3] ) ) { foreach ( self::items( $n ) as $it ) { if ( $it['k'] === $m[2] ) { return array( 'head' => (string) $it['ts'], 'card' => $it['card'] ); } } return null; }
		foreach ( self::keys( $n ) as $kg ) { if ( $kg['g']['id'] !== $m[3] ) { continue; } foreach ( $kg['sides'] as $sd ) { if ( $sd['t'] === $m[4] ) { return array( 'head' => (string) $sd['ts'], 'card' => $kg['card'] ); } } }
		return null;
	}

	/** twitter:site for every page, so links shared on X credit the account. */
	public static function twitter_site_tag() { $x = self::our_x(); return $x === '' ? '' : '<meta name="twitter:site" content="@' . esc_attr( $x ) . '">' . "\n"; }

	/** A share key a page may take for its preview: one of the pack's own cards. */
	public static function share_key( $v ) {
		$v = (string) $v;
		return preg_match( '/^pack-week-\d{1,2}-(snaps|targets|offense|leaders|rush)$/', $v ) && DBD_Card::valid( $v ) ? $v : '';
	}

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
	/** A player's last name for a subject line, keeping a suffix with it: Singletary, Cooper Jr. */
	public static function last( $name ) { $p = preg_split( '/\s+/', trim( (string) $name ) ); $n = count( $p ); if ( $n < 2 ) { return (string) $name; } return preg_match( '/^(Jr\.?|Sr\.?|II|III|IV|V)$/', $p[ $n - 1 ] ) && $n > 2 ? $p[ $n - 2 ] . ' ' . $p[ $n - 1 ] : $p[ $n - 1 ]; }
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

	/** Each item's fixed number on the tab, so a week missing one doesn't renumber the rest (Greptile, PR #125). */
	const NUM = array( 'swing' => 1, 'snaps' => 2, 'offense' => 3, 'targets' => 4, 'leaders' => 5, 'rush' => 6, 'keys' => 7, 'board' => 8 );  /* Week leaders went in as 5 (SCOPE §75), pass rushers as 6 (SCOPE §79), League Leaders as 8 (SCOPE §85) */

	/** The cards week $n's pack draws (for the harness): the item keys that have a pack card. */
	public static function cards( $n ) { $o = array(); foreach ( self::items( $n ) as $it ) { if ( strpos( $it['card'], 'pack-' ) === 0 ) { $o[] = $it['card']; } } return $o; }

	/** The items for week $n before the keys (the keys are keys()): swing, snaps, offense, targets, leaders. Each: k, h, why, card, file, fits, title, comment, post, link. */
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
				'post' => self::post( $title, $short, $link ), 'tl' => $title, 'ts' => $short, 'link' => $link, 'lead' => self::poss( $t ) . ' ' . (int) round( (float) $sw['v'] ) . '-point swing',
				'teams' => array( $t, $o ), 'sq' => 'ig-week-' . (int) $n . '-swing', 'sqfile' => 'dbd-week-' . (int) $n . '-swing-' . strtolower( $t . '-' . $o ) . '-square.png' );
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
				'title' => $title, 'comment' => 'Every team’s week, with the usual for each side: ' . $link, 'post' => self::post( $title, $short, $link ), 'tl' => $title, 'ts' => $short, 'link' => $link,
				'lead' => self::poss( $bo['t'] ) . ' ' . self::epa( $bo['epa'] ) . ' a play',
				'teams' => array( $bo['t'], $wo['t'] ), 'sq' => 'ig-week-' . (int) $n . '-offense', 'sqfile' => 'dbd-week-' . (int) $n . '-offense-square.png' );
		}

		/* 4. a target-share mover, not the usage riser again */
		$tg = self::mover( $W, 'targets', $sn['id'] ?? '' );
		if ( $tg ) { $out[] = self::riser_item( $n, 'targets', $tg ); }

		/* 5. the week's leaders (SCOPE §75) */
		$ld = self::leaders_item( $n ); if ( $ld ) { $out[] = $ld; }

		/* 6. pass rushers (SCOPE §79): only for the week the defense files run through */
		$ru = DBD_Rush::item( $n ); if ( $ru ) { $out[] = $ru; }
		return $out;
	}

	/* ---- Week leaders (2.33.0, SCOPE §75): passing, rushing and receiving yards, touchdowns, and the team defense that allowed the fewest EPA a play */

	/** Every offensive line of week $n's box scores as named rows (team, opponent, the box columns, the player's full name), or null before the file is there. */
	public static function box_rows( $n ) {
		$f = DBD_Data::get( 'box/w' . (int) $n . '.json' ); if ( ! $f || empty( $f['games'] ) ) { return null; }
		$K = (array) ( $f['keys'] ?? array() ); $rows = array();
		foreach ( (array) $f['games'] as $g ) {
			foreach ( array( 'away' => 'home', 'home' => 'away' ) as $sd => $os ) {
				$t = (string) ( $g[ $sd ]['team'] ?? '' ); $o = (string) ( $g[ $os ]['team'] ?? '' ); if ( $t === '' ) { continue; }
				foreach ( (array) ( $g[ $sd ]['off'] ?? array() ) as $r ) {
					if ( count( $r ) !== count( $K ) ) { continue; }
					$x = array_combine( $K, $r ); $p = DBD_Data::player( (string) $x['id'] );
					$x['full'] = ! empty( $p['name'] ) ? (string) $p['name'] : (string) $x['name']; $x['t'] = $t; $x['o'] = $o;
					$x['td'] = (int) $x['rushing_tds'] + (int) $x['receiving_tds']; $x['scrim'] = (int) $x['rushing_yards'] + (int) $x['receiving_yards'];
					$rows[] = $x;
				}
			}
		}
		return $rows;
	}

	/** The leaders card's spec: five panels [ label, team, name, the number, its unit, the detail line, the next two as [ name, team, value ] ], or null. */
	public static function spec_leaders( $n ) {
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return null; }
		$rows = self::box_rows( $n ); if ( ! $rows ) { return null; }
		$top = function ( $key, $tie = null ) use ( $rows ) {
			$r = array_values( array_filter( $rows, function ( $x ) use ( $key ) { return (float) $x[ $key ] > 0; } ) );
			usort( $r, function ( $a, $b ) use ( $key, $tie ) { $c = (float) $b[ $key ] <=> (float) $a[ $key ]; return $c !== 0 || ! $tie ? $c : (float) $b[ $tie ] <=> (float) $a[ $tie ]; } );
			return array_slice( $r, 0, 3 );
		};
		$rest = function ( $L, $key ) { $o = array(); foreach ( array_slice( $L, 1, 2 ) as $x ) { $o[] = array( $x['full'], $x['t'], (string) (int) $x[ $key ] ); } return $o; };
		$pl = function ( $k, $one, $many ) { return (int) $k . ' ' . ( (int) $k === 1 ? $one : $many ); };
		$P = array(); $logos = array();
		if ( ( $L = $top( 'passing_yards' ) ) ) { $x = $L[0]; $P['pass'] = array( 'Passing', $x['t'], $x['full'], (string) (int) $x['passing_yards'], 'yds', (int) $x['completions'] . ' of ' . (int) $x['attempts'] . ' · ' . (int) $x['passing_tds'] . ' TD · ' . (int) $x['passing_interceptions'] . ' INT', $rest( $L, 'passing_yards' ), $x ); }
		if ( ( $L = $top( 'rushing_yards' ) ) ) { $x = $L[0]; $P['rush'] = array( 'Rushing', $x['t'], $x['full'], (string) (int) $x['rushing_yards'], 'yds', $pl( $x['carries'], 'carry', 'carries' ) . ' · ' . (int) $x['rushing_tds'] . ' TD', $rest( $L, 'rushing_yards' ), $x ); }
		if ( ( $L = $top( 'receiving_yards' ) ) ) { $x = $L[0]; $P['rec'] = array( 'Receiving', $x['t'], $x['full'], (string) (int) $x['receiving_yards'], 'yds', $pl( $x['receptions'], 'catch', 'catches' ) . ' · ' . (int) $x['receiving_tds'] . ' TD', $rest( $L, 'receiving_yards' ), $x ); }
		if ( ( $L = $top( 'td', 'scrim' ) ) ) { $x = $L[0]; $P['td'] = array( 'Touchdowns', $x['t'], $x['full'], (string) (int) $x['td'], 'TD', (int) $x['rushing_tds'] . ' rushing · ' . (int) $x['receiving_tds'] . ' receiving', $rest( $L, 'td' ), $x, $L ); }
		$tm = array_values( array_filter( (array) ( $W['teams'] ?? array() ), function ( $x ) { return isset( $x['depa'], $x['t'] ); } ) );
		usort( $tm, function ( $a, $b ) { return (float) $a['depa'] <=> (float) $b['depa']; } );
		if ( $tm ) { $d = $tm[0]; $o = array(); foreach ( array_slice( $tm, 1, 2 ) as $x ) { $o[] = array( self::nick( $x['t'] ), $x['t'], self::epa( $x['depa'] ) ); }
			$P['def'] = array( 'Defense', $d['t'], self::nick( $d['t'] ), self::epa( $d['depa'] ), 'EPA', 'a play allowed · held the ' . self::nick( $d['o'] ?? '' ) . ' to ' . (int) ( $d['pa'] ?? 0 ), $o, $d ); }
		if ( count( $P ) < 5 ) { return null; }
		foreach ( $P as $p ) { $logos[] = $p[1]; foreach ( $p[6] as $r ) { $logos[] = $r[1]; } }
		return array( 'week' => (int) $n, 'meta' => DBD_Data::season() . ' · week ' . (int) $n . ' · leaders', 'title' => 'Week ' . (int) $n, 'panels' => $P, 'logos' => array_values( array_unique( $logos ) ) );
	}

	private static function leaders_item( $n ) {
		$s = self::spec_leaders( $n ); if ( ! $s ) { return null; }
		$P = $s['panels']; $lab = 'Week ' . (int) $n;
		$pa = $P['pass'][7]; $ru = $P['rush'][7]; $re = $P['rec'][7]; $d = $P['def'][7];
		$tds = array_values( array_filter( $P['td'][8], function ( $x ) use ( $P ) { return (int) $x['td'] === (int) $P['td'][7]['td']; } ) );
		$names = array_map( function ( $x ) { return $x['full']; }, array_slice( $tds, 0, 2 ) );
		$tdl = count( $names ) > 1 ? implode( ' and ', $names ) . ' scored ' . (int) $tds[0]['td'] . ' each' : $names[0] . ' scored ' . (int) $tds[0]['td'];
		$pass = $pa['full'] . ' threw for ' . (int) $pa['passing_yards'] . ' yards'; $rush = $ru['full'] . ' ran for ' . (int) $ru['rushing_yards']; $rec = $re['full'] . ' caught ' . (int) $re['receptions'] . ' for ' . (int) $re['receiving_yards'];
		$def = 'the ' . self::nick( $d['t'] ) . ' held the ' . self::nick( $d['o'] ?? '' ) . ' to ' . (int) ( $d['pa'] ?? 0 );
		$title = $lab . ' leaders: ' . $pass . ( (int) $pa['passing_tds'] > 0 ? ' and ' . (int) $pa['passing_tds'] . ( (int) $pa['passing_tds'] === 1 ? ' TD' : ' TDs' ) : '' ) . ', ' . $rush . ', ' . $rec . ', ' . $tdl . ', and ' . $def . '.';
		$short = $lab . ' leaders: ' . $pass . ', ' . $rush . ', ' . $rec . ', and ' . $def . '.';
		$link = DBD_Router::url( array( 'view' => 'week', 'week' => (int) $n ) );
		return array( 'k' => 'leaders', 'h' => 'Week leaders', 'why' => 'The week’s top passer, rusher, receiver and scorer, and the defense that allowed the least.',
			'card' => 'pack-week-' . (int) $n . '-leaders', 'file' => 'dbd-week-' . (int) $n . '-leaders.png', 'fits' => array( 'r/nfl', 'r/fantasyfootball' ),
			'title' => $title, 'comment' => 'The whole week, game by game, with every big play: ' . $link, 'post' => self::post( $short, $short, $link ), 'tl' => $short, 'ts' => $short, 'link' => $link,  /* X, Bluesky, Facebook, Instagram and the square take the short version; Reddit the full title (SCOPE §75, Greptile PR #140) */
			'lead' => self::last( $pa['full'] ) . '’s ' . (int) $pa['passing_yards'],
			'teams' => array_values( array_unique( array( $pa['t'], $ru['t'], $re['t'], $d['t'] ) ) ), 'sq' => 'ig-week-' . (int) $n . '-leaders', 'sqfile' => 'dbd-week-' . (int) $n . '-leaders-square.png' );
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
			'post' => self::post( $short, $short, $link ), 'tl' => $short, 'ts' => $short, 'link' => $link,
			'lead' => self::last( $m['name'] ) . '’s ' . $now . '%',
			'teams' => array( $t ), 'ff' => true, 'sq' => 'ig-week-' . (int) $n . '-' . $kind, 'sqfile' => 'dbd-week-' . (int) $n . '-' . $kind . '-' . self::slug( $m['name'] ) . '-square.png' );
	}

	/** Keys to the game for the week after $n: per game, each offense's Biggest edge, ready to post in that team's subreddit. */
	public static function keys( $n ) {
		$next = (int) $n + 1; $out = array();
		foreach ( DBD_Data::week_games( $next ) as $g ) {
			$a = $g['away'] ?? ''; $h = $g['home'] ?? ''; if ( $a === '' || $h === '' ) { continue; }
			$link = DBD_Router::url( array( 'game' => $g['id'] ) ); $sides = array();
			foreach ( array( array( $a, $h ), array( $h, $a ) ) as $p ) {
				$c = DBD_Takeaways::cards( $p[0], $p[1] )[0] ?? null; if ( ! $c ) { continue; }
				$link = DBD_Router::url( $p[0] === $h ? array( 'game' => $g['id'], 'side' => 'home' ) : array( 'game' => $g['id'] ) );  /* each offense's own keys (Greptile, PR #125) */
				$head = self::nick( $a ) . ' at ' . self::nick( $h );
				$sides[] = array( 't' => $p[0], 'fits' => self::sub( $p[0] ), 'head' => $c[2], 'say' => $c[3],
					'title' => $head . ': where the matchup tilts for the ' . self::nick( $p[0] ) . ' offense',
					'comment' => rtrim( $c[2], '.' ) . '. ' . $c[3] . ' The other keys and the head to head: ' . $link,
					'post' => self::post( $head . ': ' . $c[3], $head . ': ' . $c[3], $link ), 'tl' => $head . ': ' . $c[3], 'ts' => $head . ': ' . $c[3], 'link' => $link,
					'teams' => array( $p[0], $p[1] ), 'sq' => 'ig-week-' . (int) $n . '-keys-' . $g['id'] . '-' . $p[0], 'sqfile' => 'dbd-week-' . $next . '-' . strtolower( $a . '-' . $h . '-' . $p[0] ) . '-square.png' );
			}
			if ( $sides ) { $out[] = array( 'g' => $g, 'card' => 'game-' . $g['id'], 'file' => 'dbd-week-' . $next . '-' . strtolower( $a . '-' . $h ) . '.png', 'link' => DBD_Router::url( array( 'game' => $g['id'] ) ), 'sides' => $sides ); }
		}
		return $out;
	}

	/** Week $n's item for a slot ('swing' … 'rush'), or a keys side for 'keys-{game}-{team}' carrying its game's card; null when there's none (SCOPE §81). */
	public static function find( $n, $slot ) {
		$slot = (string) $slot; if ( $slot === 'board' ) { return self::board_item( $n ); }
		if ( strpos( $slot, 'keys-' ) !== 0 ) { foreach ( self::items( $n ) as $it ) { if ( ( $it['k'] ?? '' ) === $slot ) { return $it; } } return null; }
		foreach ( self::keys( $n ) as $kg ) { if ( self::started( $kg['g'] ) ) { continue; } foreach ( $kg['sides'] as $sd ) {  /* a preview is no use once the game's on (SCOPE §85) */ if ( $sd['sq'] === 'ig-week-' . (int) $n . '-' . $slot ) { $sd['card'] = $kg['card']; return $sd; } } }
		return null;
	}

	/** Has this game kicked off? A final has; so has one past its kickoff time. The harness sets $now. */
	public static $now = null;
	public static function started( $g ) {
		if ( DBD_Tall::$off ) { return false; }
		if ( DBD_Router::is_final( $g ) ) { return true; }
		$u = (string) ( DBD_Data::kickoff( $g )['utc'] ?? '' ); $t = $u !== '' ? strtotime( $u ) : false;
		return $t !== false && ( self::$now ?? time() ) >= $t;
	}
	/** The same, from a keys slot ('keys-{game}-{team}'). */
	public static function slot_started( $slot ) {
		if ( ! preg_match( '/^keys-(\d{4}_\d{2}_[A-Z]{2,3}_[A-Z]{2,3})-/', (string) $slot, $m ) ) { return false; }
		$g = DBD_Data::game( $m[1] ); return ! $g || self::started( $g );
	}

	/* ---- League Leaders (2.43.0, SCOPE §85): Monday's post, a season board, one stat a week; not part of Tuesday's carousel or thread */

	public static function board_item( $n ) {
		if ( DBD_Tall::$off ) { return null; }
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return null; }
		$s = DBD_Tall::board_spec( $n ); if ( ! $s ) { return null; }
		$R = $s['rows']; $what = $s['stat'][3]; $top = array_values( array_filter( $R, function ( $x ) use ( $R ) { return (float) $x['v'] === (float) $R[0]['v']; } ) ); $c = count( $top );
		$who = $c === 1 ? $top[0]['full'] . ' leads' : ( $c === 2 ? $top[0]['full'] . ' and ' . $top[1]['full'] . ' lead' : $top[0]['full'] . ', ' . $top[1]['full'] . ' and ' . ( $c - 2 ) . ( $c === 3 ? ' other' : ' others' ) . ' lead' );
		$short = 'League leaders in ' . $what . ' through Week ' . (int) $n . ': ' . $who . ' with ' . $R[0]['vs'] . '.';
		$link = DBD_Router::url( $s['stat'][0] === 'sk' ? array( 'view' => 'leaders', 'g' => 'rush' ) : array( 'view' => 'leaders' ) ); $teams = array_values( array_unique( array_column( array_slice( $R, 0, 3 ), 't' ) ) );
		return array( 'k' => 'board', 'h' => 'League Leaders', 'why' => 'The season’s top 11 in one stat, a different stat each week.', 'card' => 'page-leaders', 'file' => 'dbd-week-' . (int) $n . '-league-leaders.jpg', 'fits' => array( 'r/nfl' ),
			'title' => $short, 'comment' => 'Every leaderboard, all season: ' . $link, 'post' => self::post( $short, $short, $link ), 'tl' => $short, 'ts' => $short, 'link' => $link,
			'lead' => self::last( $R[0]['full'] ) . '’s ' . $R[0]['vs'], 'teams' => $teams, 'sq' => DBD_Tall::key( $n, 'board' ), 'sqfile' => 'dbd-week-' . (int) $n . '-league-leaders.jpg' );
	}

	/* ---- the two new cards' specs (DBD_Card draws them) */

	/** A riser card: pack-week-9-snaps or pack-week-9-targets. */
	public static function spec_riser( $n, $kind ) {
		$W = DBD_Week::file( $n ); if ( ! $W || empty( $W['complete'] ) ) { return null; }
		$sn = self::mover( $W, 'snaps' ); $m = $kind === 'snaps' ? $sn : self::mover( $W, 'targets', $sn['id'] ?? '' ); if ( ! $m ) { return null; }
		$t = (string) $m['team']; $tm = DBD_Data::team( $t ) ?: array(); $parts = explode( ' ', (string) $m['name'], 2 );
		$lab = $kind === 'snaps' ? 'Snap share' : 'Target share'; $g = (int) ( $m['g'] ?? 0 );
		return array( 'kind' => $kind, 'id' => (string) ( $m['id'] ?? '' ), 'num' => (string) ( $m['num'] ?? '' ), 'week' => (int) $n, 'season' => (int) DBD_Data::season(), 'team' => $t, 'c1' => $tm['c1'] ?? '#121417',
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
