<?php
/**
 * Live blocks for pages built in the theme (SCOPE §45): [dbd_block type="…"] draws a piece of this week's data anywhere, so a Divi page
 * (the marketing home) can carry live parts while its own layout, words and styling stay in Divi. Each block shows the current season,
 * picks its own subject each week, is cached until the next data build, and renders nothing when its data is missing.
 *   hero-map  the week's most-targeted player: photo, line, every target on the field, a link to his page (player="id" pins one)
 *   games     this week's games as score tiles, games still to play first, the next one flagged
 *   counts    one line: snaps charted, games, players, offenses and defenses
 *   week-highlights  the latest week in review in three cards: the play of the week, the swing of the week, the upsets and the wildest game
 *   matchup   the game of the week (picked the way the hub picks it) with its biggest edge, over a four-row offense vs defense
 *   player-card  a player's share card image linked to his page: the week's biggest riser in target share (player="id" pins one)
 *   movers    the biggest jumps in target share in the latest finished week, before and after (count="4", 1 to 5)
 *   fantasy   the softest defense at each position (QB, RB, WR, TE): the most half-PPR points allowed a game
 *   leaders   season leader boards (boards="wr.tshare,wr.epat,qb.epadb" by default; "ppr" is the PPR points board; top="5", 3 to 10)
 *   teams     all 32 teams by division, best record first, each linked to its page
 *   newsletter-preview  the latest finished week as the Tuesday email: subject, headline, the swing, the play, the risers
 *   follow    (2.52.0, SCOPE §94) a row of icons linking to the site's accounts: X, Bluesky, Threads, Instagram, the Facebook group and
 *             the subreddit, from the Share pack settings (a cleared account drops its icon). It needs no data and isn't cached, and it
 *             carries its own few lines of CSS, so the site footer doesn't pull in blocks.css and the fonts on every page. color="#fff" sets
 *             the icons' color; without it they take the surrounding text color.
 * Styles: assets/blocks.css. The blocks inherit the surrounding text color for their headings and lines, so they sit on any background.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Blocks {
	const TYPES = array( 'hero-map', 'games', 'counts', 'week-highlights', 'matchup', 'player-card', 'movers', 'fantasy', 'leaders', 'teams', 'newsletter-preview', 'follow' );

	public static function init() {
		add_shortcode( 'dbd_block', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp', array( __CLASS__, 'detect' ), 20 );
	}

	/** A page whose content carries a block loads the styles in the head. */
	public static function detect() {
		if ( is_admin() || ! is_singular() ) { return; }
		$post = get_queried_object();
		if ( $post && strpos( (string) ( $post->post_content ?? '' ), 'dbd_block' ) !== false ) { add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) ); }
	}

	public static function enqueue() {
		wp_enqueue_style( 'dbd-fonts', 'https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap', array(), null );
		wp_enqueue_style( 'dbd-blocks', DBD_URL . 'assets/blocks.css', array( 'dbd-fonts' ), DBD_VERSION );
	}

	public static function shortcode( $atts ) {
		$a = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
		$type = strtolower( trim( (string) ( $a['type'] ?? '' ) ) );
		if ( ! in_array( $type, self::TYPES, true ) ) { return ''; }
		if ( $type === 'follow' ) { return self::b_follow( $a ); }  /* its own CSS rides along: no stylesheet or fonts for a footer on every page */
		self::enqueue();  /* a block in a Theme Builder layout isn't in the page's content: its styles print in the footer instead */
		return self::render( $type, $a );
	}

	/** One block's HTML, cached until the next data build (hero-map reads every team's player file). */
	public static function render( $type, $a = array() ) {
		if ( $type === 'follow' ) { return self::b_follow( $a ); }
		$was = DBD_Data::archived() ? DBD_Data::season() : null; DBD_Data::use_season( null );  /* blocks always show this season */
		if ( ! DBD_Data::has_data() ) { DBD_Data::use_season( $was ); return ''; }
		unset( $a['type'] ); ksort( $a );
		$key = 'dbd_hb_' . md5( wp_json_encode( array( $type, $a, DBD_VERSION, DBD_Data::manifest()['built_at'] ?? '', DBD_Data::logos(), DBD_Data::headshots(), DBD_Router::url( array( 'view' => 'teams' ) ) ) ) );
		$html = get_transient( $key );
		if ( ! is_string( $html ) ) {
			$fn = 'b_' . str_replace( '-', '_', $type );
			$html = (string) self::$fn( $a );
			set_transient( $key, $html, 6 * HOUR_IN_SECONDS );
		}
		DBD_Data::use_season( $was );
		return $html;
	}


	/* ------------------------------------------------------------------ follow (SCOPE §94) */

	/** The six networks' marks, 24 by 24 (Simple Icons, CC0). */
	const ICONS = array(
		'x' => 'M18.901 1.153h3.68l-8.04 9.19L24 22.846h-7.406l-5.8-7.584-6.638 7.584H.474l8.6-9.83L0 1.154h7.594l5.243 6.932ZM17.61 20.644h2.039L6.486 3.24H4.298Z',
		'bluesky' => 'M12 10.8c-1.087-2.114-4.046-6.053-6.798-7.995C2.566.944 1.561 1.266.902 1.565.139 1.908 0 3.08 0 3.768c0 .69.378 5.65.624 6.479.815 2.736 3.713 3.66 6.383 3.364.136-.02.275-.039.415-.056-.138.022-.276.04-.415.056-3.912.58-7.387 2.005-2.83 7.078 5.013 5.19 6.87-1.113 7.823-4.308.953 3.195 2.05 9.271 7.733 4.308 4.267-4.308 1.172-6.498-2.74-7.078a8.741 8.741 0 0 1-.415-.056c.14.017.279.036.415.056 2.67.297 5.568-.628 6.383-3.364.246-.828.624-5.79.624-6.478 0-.69-.139-1.861-.902-2.206-.659-.298-1.664-.62-4.3 1.24C16.046 4.748 13.087 8.687 12 10.8Z',
		'threads' => 'M12.186 24h-.007c-3.581-.024-6.334-1.205-8.184-3.509C2.35 18.44 1.5 15.586 1.472 12.01v-.017c.03-3.579.879-6.43 2.525-8.482C5.845 1.205 8.6.024 12.18 0h.014c2.746.02 5.043.725 6.826 2.098 1.677 1.29 2.858 3.13 3.509 5.467l-2.04.569c-1.104-3.96-3.898-5.984-8.304-6.015-2.91.022-5.11.936-6.54 2.717C4.307 6.504 3.616 8.914 3.589 12c.027 3.086.718 5.496 2.057 7.164 1.43 1.783 3.631 2.698 6.54 2.717 2.623-.02 4.358-.631 5.8-2.045 1.647-1.613 1.618-3.593 1.09-4.798-.31-.71-.873-1.3-1.634-1.75-.192 1.352-.622 2.446-1.284 3.272-.886 1.102-2.14 1.704-3.73 1.79-1.202.065-2.361-.218-3.259-.801-1.063-.689-1.685-1.74-1.752-2.964-.065-1.19.408-2.285 1.33-3.082.88-.76 2.119-1.207 3.583-1.291a13.853 13.853 0 0 1 3.02.142c-.126-.742-.375-1.332-.75-1.757-.513-.586-1.308-.883-2.359-.89h-.029c-.844 0-1.992.232-2.721 1.32L7.734 7.847c.98-1.454 2.568-2.256 4.478-2.256h.044c3.194.02 5.097 1.975 5.287 5.388.108.046.216.094.321.142 1.49.7 2.58 1.761 3.154 3.07.797 1.82.871 4.79-1.548 7.158-1.85 1.81-4.094 2.628-7.277 2.65Zm1.003-11.69c-.242 0-.487.007-.739.021-1.836.103-2.98.946-2.916 2.143.067 1.256 1.452 1.839 2.784 1.767 1.224-.065 2.818-.543 3.086-3.71a10.5 10.5 0 0 0-2.215-.221z',
		'instagram' => 'M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z',
		'facebook' => 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z',
		'reddit' => 'M12 0C5.373 0 0 5.373 0 12c0 3.314 1.343 6.314 3.515 8.485l-2.286 2.286C.775 23.225 1.097 24 1.738 24H12c6.627 0 12-5.373 12-12S18.627 0 12 0Zm4.388 3.199c1.104 0 1.999.895 1.999 1.999 0 1.105-.895 2-1.999 2-.946 0-1.739-.657-1.947-1.539v.002c-1.147.162-2.032 1.15-2.032 2.341v.007c1.776.067 3.4.567 4.686 1.363.473-.363 1.064-.58 1.707-.58 1.547 0 2.802 1.254 2.802 2.802 0 1.117-.655 2.081-1.601 2.531-.088 3.256-3.637 5.876-7.997 5.876-4.361 0-7.905-2.617-7.998-5.87-.954-.447-1.614-1.415-1.614-2.538 0-1.548 1.255-2.802 2.803-2.802.645 0 1.239.218 1.712.585 1.275-.79 2.881-1.291 4.64-1.365v-.01c0-1.663 1.263-3.034 2.88-3.207.188-.911.993-1.595 1.959-1.595Zm-8.085 8.376c-.784 0-1.459.78-1.506 1.797-.047 1.016.64 1.429 1.426 1.429.786 0 1.371-.369 1.418-1.385.047-1.017-.553-1.841-1.338-1.841Zm7.406 0c-.786 0-1.385.824-1.338 1.841.047 1.017.634 1.385 1.418 1.385.785 0 1.473-.413 1.426-1.429-.046-1.017-.721-1.797-1.506-1.797Zm-3.703 4.013c-.974 0-1.907.048-2.77.135-.147.015-.241.168-.183.305.483 1.154 1.622 1.964 2.953 1.964 1.33 0 2.47-.81 2.953-1.964.057-.137-.037-.29-.184-.305-.863-.087-1.795-.135-2.769-.135Z',
	);
	private static $follow_css = false;

	/** The accounts with a link, in order: [ icon, name, url ]. Each from the Share pack settings; a cleared one is left out. */
	public static function follow_links() {
		$o = array(); $x = DBD_Pack::our_x(); $b = DBD_Pack::our_bsky(); $t = DBD_Pack::our_th(); $i = DBD_Pack::our_ig(); $f = DBD_Pack::our_fb(); $r = DBD_Pack::our_sub();
		if ( $x !== '' ) { $o[] = array( 'x', 'X', 'https://x.com/' . $x ); }
		if ( $b !== '' ) { $o[] = array( 'bluesky', 'Bluesky', 'https://bsky.app/profile/' . $b ); }
		if ( $t !== '' ) { $o[] = array( 'threads', 'Threads', 'https://www.threads.com/@' . $t ); }
		if ( $i !== '' ) { $o[] = array( 'instagram', 'Instagram', 'https://www.instagram.com/' . $i . '/' ); }
		if ( $f !== '' ) { $o[] = array( 'facebook', 'Facebook', $f ); }
		if ( $r !== '' ) { $o[] = array( 'reddit', 'Reddit', 'https://www.reddit.com/r/' . $r . '/' ); }
		return $o;
	}

	/** The row of icons, with its CSS the first time on a page; '' when no account is set. */
	private static function b_follow( $a ) {
		$links = self::follow_links(); if ( ! $links ) { return ''; }
		$c = trim( (string) ( $a['color'] ?? '' ) ); $style = preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $c ) ? ' style="color:' . $c . '"' : '';
		$h = '';
		if ( ! self::$follow_css ) {
			self::$follow_css = true;
			$h .= '<style>nav.hb-follow.hb-follow{display:flex;justify-content:center;flex-wrap:wrap;gap:6px;margin:0 0 18px;padding:0}'
				. 'nav.hb-follow.hb-follow a{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;color:inherit;opacity:.7;text-decoration:none;transition:opacity .15s ease,background-color .15s ease}'
				. 'nav.hb-follow.hb-follow a:hover,nav.hb-follow.hb-follow a:focus-visible{opacity:1;background:rgba(128,128,128,.2)}'
				. 'nav.hb-follow.hb-follow a:focus-visible{outline:2px solid currentColor;outline-offset:2px}'
				. 'nav.hb-follow.hb-follow svg{display:block;width:20px;height:20px;fill:currentColor}'
				. '@media (prefers-reduced-motion:reduce){nav.hb-follow.hb-follow a{transition:none}}</style>';
		}
		$h .= '<nav class="dbd-hb hb-follow" aria-label="Follow Down x Down"' . $style . '>';
		foreach ( $links as $l ) {
			$h .= '<a href="' . esc_url( $l[2] ) . '" target="_blank" rel="noopener" aria-label="Down x Down on ' . esc_attr( $l[1] ) . '" title="' . esc_attr( $l[1] ) . '">'
				. '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( self::ICONS[ $l[0] ] ) . '"/></svg></a>';
		}
		return $h . '</nav>';
	}

	private static function nick( $a ) { return DBD_Data::team( $a )['nick'] ?? $a; }
	private static function u( $args ) { return esc_url( DBD_Router::url( $args ) ); }
	private static function wrap( $type, $inner ) { return $inner === '' ? '' : '<div class="dbd-hb hb-' . esc_attr( $type ) . '">' . $inner . '</div>'; }
	private static function pct( $v ) { return DBD_Data::pct( $v ); }
	/** A play's description without the tacklers' brackets: "…, TOUCHDOWN [J.McMillian]." reads "…, TOUCHDOWN." */
	private static function clean( $d ) { return trim( (string) preg_replace( '/\s*\[[^\]]*\]/', '', (string) $d ) ); }
	/** A final game's winner, loser and both scores, the winner's first; a tie keeps away then home. */
	private static function wl( $g ) {
		$w = (string) ( $g['win'] ?? '' ); if ( $w === '' ) { return array( $g['away'], $g['home'], (int) $g['a'], (int) $g['h'] ); }
		$aw = $w === $g['away'];
		return array( $w, $aw ? $g['home'] : $g['away'], (int) ( $aw ? $g['a'] : $g['h'] ), (int) ( $aw ? $g['h'] : $g['a'] ) );
	}
	private static function tags( $g ) { $o = array(); foreach ( (array) ( $g['tags'] ?? array() ) as $t ) { $o[ $t[0] ] = $t[1]; } return $o; }

	/** The week's most-targeted player (or player="id"): photo, line, every target on the field, a link to his page. */
	private static function b_hero_map( $a ) {
		$wk = DBD_Week::settled_week(); if ( ! $wk ) { return ''; }
		$pid = (string) ( $a['player'] ?? '' ); $auto = $pid === '';
		$hit = $auto ? self::most_targeted( $wk ) : self::week_line( $pid, $wk );
		if ( ! $hit ) { return ''; }
		list( $pid, $team, $w ) = $hit; $wk = (int) $w['week'];
		$p = DBD_Data::player( $pid ); if ( ! $p ) { return ''; }
		/* the plays of the team he played for that week; no play file yet (a first sync still downloading) or no plays of his in it: no block (Greptile, PR #60) */
		$pf = DBD_Data::get( 'plays/' . $team . '.json' ); if ( ! $pf || empty( $pf['keys'] ) ) { return ''; }
		$rows = array(); $pre = sprintf( '%d_%02d_', DBD_Data::season(), $wk );
		foreach ( (array) $pf['plays'] as $r ) {
			if ( count( $r ) !== count( $pf['keys'] ) ) { continue; }
			$r = array_combine( $pf['keys'], $r );
			if ( strpos( (string) $r['g'], $pre ) === 0 && ( $r['pid'] ?? '' ) === $pid && ( $r['pt'] ?? '' ) === 'pass' ) { $rows[] = $r; }
		}
		if ( ! $rows ) { return ''; }
		$lab = DBD_Router::week_label( $wk ); $sub = $p['pos'] . ' · ' . self::nick( $team ) . ' · ' . ( $auto ? 'most targets in ' . ( strpos( $lab, 'Week ' ) === 0 ? 'week ' . $wk : 'the ' . $lab ) : $lab );
		$st = array( array( (int) $w['targets'], 'targets' ), array( (int) $w['receptions'], 'caught' ), array( (int) $w['receiving_yards'], 'yards' ), array( (int) $w['receiving_tds'], 'TD' ) );
		$h = '<div class="hb-who">' . DBD_Marks::player( $team, $p['num'] ?? null, 46, $p['photo'] ?? null ) . '<span><b><a href="' . self::u( array( 'player' => $pid ) ) . '">' . esc_html( $p['name'] ) . '</a></b><small>' . esc_html( $sub ) . '</small></span></div>';
		$h .= '<div class="hb-stats">'; foreach ( $st as $s ) { $h .= '<span><b>' . (int) $s[0] . '</b>' . esc_html( $s[1] ) . '</span>'; } $h .= '</div>';
		$h .= self::field( $rows, $p['name'] . "'s targets, " . $lab . ', by depth and side of the field' );
		$h .= '<p class="hb-leg"><i class="c"></i>caught <i class="n"></i>incomplete <i class="t"></i>touchdown <a href="' . self::u( array( 'player' => $pid ) ) . '">His page</a></p>';
		return self::wrap( 'hero-map', $h );
	}

	/**
	 * [id, team, week row] of the player with the most targets in week $wk (yards break a tie). A traded player sits only in his new
	 * team's file but keeps each earlier week under the team he played for, so the week's own team is the one that counts (Greptile, PR #60).
	 */
	/** The week's most-targeted players, most first (targets, then yards): [id, the team he played for, his week line] each. */
	private static function top_targeted( $wk, $n = 1 ) {
		$all = array();
		foreach ( array_keys( DBD_Data::teams() ) as $t ) {
			foreach ( DBD_Data::team_players( $t ) as $p ) {
				foreach ( (array) ( $p['weeks'] ?? array() ) as $w ) {
					if ( (int) $w['week'] !== (int) $wk || (int) ( $w['targets'] ?? 0 ) < 1 ) { continue; }
					$all[] = array( array( (int) $w['targets'], (int) ( $w['receiving_yards'] ?? 0 ) ), array( $p['id'], (string) ( $w['team'] ?? $t ), $w ) );
				}
			}
		}
		usort( $all, function ( $x, $y ) { return $y[0] <=> $x[0]; } );
		return array_map( function ( $r ) { return $r[1]; }, array_slice( $all, 0, max( 1, (int) $n ) ) );
	}
	private static function most_targeted( $wk ) { return self::top_targeted( $wk, 1 )[0] ?? null; }

	/** A named player's line in week $wk, or his latest week before it. */
	private static function week_line( $pid, $wk ) {
		$row = DBD_Data::player( $pid ); if ( ! $row ) { return null; }
		$rec = DBD_Data::player_record( $row['team'], $pid ); if ( ! $rec ) { return null; }
		$best = null;
		foreach ( (array) ( $rec['weeks'] ?? array() ) as $w ) { if ( (int) $w['week'] <= (int) $wk && ( ! $best || (int) $w['week'] > (int) $best['week'] ) ) { $best = $w; } }
		return $best ? array( $pid, (string) ( $best['team'] ?? $row['team'] ), $best ) : null;  /* the team he played for that week, before or after a trade */
	}

	/** The target map: air yards up the field, left / middle / right across it. The spread inside a third is fixed per play, so it never moves. */
	private static function field( $rows, $label ) {
		$xs = array( 'left' => array( 34, 139.3 ), 'middle' => array( 139.3, 244.7 ), 'right' => array( 244.7, 350 ) );
		$g = '';
		foreach ( array( array( 390, -10 ), array( 320, 0 ), array( 250, 10 ), array( 180, 20 ), array( 110, 30 ), array( 40, 40 ) ) as $l ) {
			$g .= '<line x1="34" x2="350" y1="' . $l[0] . '" y2="' . $l[0] . '" stroke="rgba(255,255,255,.22)" stroke-width="' . ( $l[0] === 320 ? 2 : 1 ) . '"/><text x="28" y="' . ( $l[0] + 4 ) . '" fill="rgba(255,255,255,.7)" font-size="10" text-anchor="end">' . $l[1] . '</text>';
		}
		foreach ( array( 139.3, 244.7 ) as $x ) { $g .= '<line x1="' . $x . '" x2="' . $x . '" y1="40" y2="390" stroke="rgba(255,255,255,.18)" stroke-dasharray="2 4"/>'; }
		foreach ( array( array( 86.7, 'Left' ), array( 192, 'Middle' ), array( 297.3, 'Right' ) ) as $l ) { $g .= '<text x="' . $l[0] . '" y="410" fill="rgba(255,255,255,.7)" font-size="11" text-anchor="middle">' . $l[1] . '</text>'; }
		foreach ( $rows as $i => $r ) {
			if ( empty( $r['loc'] ) || ! isset( $xs[ $r['loc'] ] ) || $r['air'] === null ) { continue; }
			list( $a0, $b0 ) = $xs[ $r['loc'] ];
			$j = ( crc32( $r['g'] . '|' . $r['q'] . '|' . $r['t'] . '|' . $i ) % 1000 ) / 1000;
			$x = $a0 + 8 + $j * ( $b0 - $a0 - 16 ); $y = 320 - max( -10, min( 42, (float) $r['air'] ) ) * 7;
			$td = ! empty( $r['td'] ); $col = $td ? '#4fd18b' : ( $r['k'] === 'comp' ? '#5b9cf6' : '#9a96a6' );
			$g .= '<circle cx="' . round( $x, 1 ) . '" cy="' . round( $y ) . '" r="' . ( $td ? 7 : 5.5 ) . '" fill="' . $col . '" fill-opacity=".92" stroke="rgba(0,0,0,.35)"/>';
		}
		return '<svg class="hb-field" viewBox="0 0 360 420" width="100%" role="img" aria-label="' . esc_attr( $label ) . '">' . $g . '</svg>';
	}

	/** This week's games as score tiles, games still to play first; the next one flagged. */
	private static function b_games( $a ) {
		$wk = DBD_Data::current_week(); $games = DBD_Data::week_games( $wk ); if ( ! $games ) { return ''; }
		$W = DBD_Week::file( $wk ); $meta = array(); foreach ( (array) ( $W['games'] ?? array() ) as $g ) { $meta[ $g['id'] ] = $g; }
		$open = array_values( array_filter( $games, function ( $g ) { return ! DBD_Router::is_final( $g ); } ) );
		$done = array_values( array_filter( $games, array( 'DBD_Router', 'is_final' ) ) );
		$tiles = '';
		foreach ( array_merge( $open, $done ) as $i => $g ) {
			$f = DBD_Router::is_final( $g ); $win = $f ? ( $g['away_score'] > $g['home_score'] ? $g['away'] : ( $g['home_score'] > $g['away_score'] ? $g['home'] : '' ) ) : '';
			$row = function ( $t, $s ) use ( $f, $win ) { return '<span class="r' . ( $f && $win === $t ? ' w' : '' ) . '">' . DBD_Marks::tile( $t, 18 ) . '<b>' . esc_html( $t ) . '</b><em>' . ( $f ? (int) $s : '' ) . '</em></span>'; };
			if ( $f ) {
				$tg = self::tags( $meta[ $g['id'] ] ?? array() ); $ot = ! empty( $meta[ $g['id'] ]['ot'] );
				$st = 'Final' . ( isset( $tg['upset'] ) ? ' · Upset' : ( $ot ? ' · OT' : ( isset( $tg['comeback'] ) ? ' · Comeback' : '' ) ) );
			} else {
				$k = DBD_Data::kickoff( $g ); $d = strtotime( (string) $g['date'] ); $st = ( $d ? gmdate( 'D', $d ) . ' ' : '' ) . str_replace( ' ET', '', $k['et'] );
			}
			$tiles .= '<a class="hb-gm' . ( $f ? '' : ' up' ) . '" href="' . self::u( array( 'game' => $g['id'] ) ) . '">' . ( ! $f && $i === 0 ? '<i class="nx">Next</i>' : '' ) . $row( $g['away'], $g['away_score'] ?? null ) . $row( $g['home'], $g['home_score'] ?? null ) . '<span class="st">' . esc_html( $st ) . '</span></a>';
		}
		$nf = count( $done ); $no = count( $open );
		$sum = $nf && $no ? $nf . ' final · ' . $no . ' to play' : ( $nf ? ( $nf === 1 ? 'final' : 'all ' . $nf . ' final' ) : ( $no === 1 ? '1 game' : $no . ' games' ) );
		return self::wrap( 'games', '<div class="hb-games-h"><b>' . esc_html( DBD_Router::week_label( $wk ) ) . '</b><span>' . esc_html( $sum ) . '</span><a href="' . self::u( array( 'view' => 'matchups' ) ) . '">Every matchup</a></div><div class="hb-games-g">' . $tiles . '</div>' );
	}

	/** One plain line of the site's size. */
	private static function b_counts( $a ) {
		$st = DBD_Data::stats(); $snaps = (int) ( $st['snaps'] ?? 0 ); $fin = 0;
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( (array) $gl as $g ) { if ( DBD_Router::is_final( $g ) ) { $fin++; } } }
		$parts = array();
		if ( $snaps ) { $parts[] = '<b>' . number_format( $snaps ) . '</b> snaps charted this season'; }
		if ( $fin ) { $parts[] = '<b>' . $fin . '</b> games'; }
		if ( DBD_Data::has_players() ) { $parts[] = '<b>' . count( DBD_Data::player_index() ) . '</b> players'; }
		$parts[] = '<b>' . count( DBD_Data::teams() ) . '</b> offenses and defenses';
		return self::wrap( 'counts', '<p>' . implode( ' · ', $parts ) . '</p>' );
	}

	/**
	 * The latest week in review in three cards: the play of the week (by EPA), the swing of the week (the biggest jump in win
	 * probability), and the upsets with each winner's chance at kickoff plus the wildest other game. The week is the latest with a
	 * final game, so a Sunday night shows that Sunday.
	 */
	private static function b_week_highlights( $a ) {
		$n = DBD_Week::settled_week(); $W = $n ? DBD_Week::file( $n ) : null; if ( ! $W ) { return ''; }
		$h = '';
		$tp = $W['plays'][0] ?? null;
		if ( $tp ) {
			$h .= '<a class="hb-wk" href="' . self::u( array( 'game' => $tp['g'] ) ) . '"><i>The play of the week</i><span class="mk">' . DBD_Marks::tile( $tp['t'], 40 ) . '</span><h3>' . esc_html( self::clean( $tp['d'] ) ) . '</h3><p><b>' . ( $tp['e'] >= 0 ? '+' : '−' ) . number_format( abs( (float) $tp['e'] ), 2 ) . ' EPA</b> · ' . esc_html( self::nick( $tp['t'] ) . ' vs ' . self::nick( $tp['o'] ) . ', ' . $tp['c'] ) . '</p></a>';
		}
		$sw = $W['swings'][0] ?? null;
		if ( $sw ) {
			$h .= '<a class="hb-wk" href="' . self::u( array( 'game' => $sw['g'] ) ) . '"><i>The swing of the week</i><span class="mk">' . DBD_Marks::tile( $sw['t'], 40 ) . '</span><h3>' . esc_html( self::clean( $sw['d'] ) ) . '</h3><p><b>+' . round( (float) $sw['v'] ) . '%</b> win probability for the ' . esc_html( self::nick( $sw['t'] ) . ', ' . $sw['c'] ) . '</p></a>';
		}
		$fin = array_values( array_filter( (array) $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final'; } ) );  /* ties stay in for the wildest game (Greptile, PR #61) */
		$ups = array_values( array_filter( $fin, function ( $g ) { return ! empty( $g['win'] ) && isset( self::tags( $g )['upset'] ); } ) );
		usort( $ups, function ( $x, $y ) { return $x['pre_w'] <=> $y['pre_w']; } ); $ups = array_slice( $ups, 0, 3 );
		$shown = array_map( function ( $g ) { return $g['id']; }, $ups );  /* the wildest is another game when there is one */
		$wd = array_values( array_filter( $fin, function ( $g ) use ( $shown ) { return ! in_array( $g['id'], $shown, true ); } ) ) ?: $fin;
		usort( $wd, function ( $x, $y ) { return array( $y['lc'] ?? 0, $y['drama'] ?? 0 ) <=> array( $x['lc'] ?? 0, $x['drama'] ?? 0 ); } ); $wild = $wd[0] ?? null;
		if ( $ups || $wild ) {
			$li = '';
			foreach ( $ups as $g ) { list( $w, $l, $ws, $ls ) = self::wl( $g ); $li .= '<li><a href="' . self::u( array( 'game' => $g['id'] ) ) . '"><b>' . esc_html( self::nick( $w ) . ' ' . $ws ) . '</b>, ' . esc_html( self::nick( $l ) . ' ' . $ls ) . '</a><small>' . self::pct( $g['pre_w'] ) . '% at kickoff</small></li>'; }
			$wl = ''; $mk = '';
			if ( $wild ) { list( $w, $l, $ws, $ls ) = self::wl( $wild ); $mk = empty( $wild['win'] ) ? '' : '<span class="mk">' . DBD_Marks::tile( $w, 30 ) . '</span>'; $wl = '<p class="wild"><span>Wildest game</span><a href="' . self::u( array( 'game' => $wild['id'] ) ) . '"><b>' . esc_html( self::nick( $w ) . ' ' . $ws . ', ' . self::nick( $l ) . ' ' . $ls ) . '</b>' . ( ! empty( $wild['ot'] ) ? ' in overtime' : '' ) . '</a></p>'; }
			$h .= '<div class="hb-wk ups"><i>' . ( $ups ? 'Upsets' : 'No upsets' ) . '</i>' . $mk . ( $li ? '<ul>' . $li . '</ul>' : '' ) . $wl . '</div>';
		}
		return self::wrap( 'week-highlights', $h );
	}

	/**
	 * The game of the week, picked the way the hub picks it (the open game with the biggest edge), over a four-row head to head of that
	 * edge's offense against the defense. When every game of the current week is final, next week's games stand in.
	 */
	private static function b_matchup( $a ) {
		if ( DBD_Data::through_week() < 1 ) { return ''; }
		$cw = DBD_Data::current_week(); $gotw = null; $best = null;
		foreach ( array( $cw, $cw + 1 ) as $wk ) {
			foreach ( DBD_Data::week_games( $wk ) as $g ) {
				if ( DBD_Router::is_final( $g ) ) { continue; }
				$b = DBD_Takeaways::best_edge( $g['away'], $g['home'] );
				if ( ! $best || $b['s'] > $best['s'] ) { $gotw = $g; $best = $b; }
			}
			if ( $gotw ) { break; }
		}
		/* no game left to play (the Super Bowl is over, the new season hasn't started): the last week's final game stands in, with its score */
		$done = false;
		if ( ! $gotw ) {
			$lw = DBD_Week::settled_week();
			foreach ( DBD_Data::week_games( $lw ) as $g ) {
				if ( ! DBD_Router::is_final( $g ) ) { continue; }
				$b = DBD_Takeaways::best_edge( $g['away'], $g['home'] );
				if ( ! $best || $b['s'] > $best['s'] ) { $gotw = $g; $best = $b; }
			}
			$done = (bool) $gotw;
		}
		if ( ! $gotw ) { return ''; }
		$O = $best['O']; $D = $best['D']; $cards = DBD_Takeaways::cards( $O, $D ); $k = DBD_Data::kickoff( $gotw ); $d = strtotime( (string) ( $gotw['date'] ?? '' ) );
		$kick = $done
			? implode( ' · ', array_filter( array( DBD_Router::week_label( (int) ( $gotw['week'] ?? DBD_Week::settled_week() ) ), 'Final', (string) ( $gotw['stadium'] ?? '' ) ) ) )
			: implode( ' · ', array_filter( array( 'Game of the week', trim( ( $d ? gmdate( 'l', $d ) . ' ' : '' ) . ( $k['et'] ?? '' ) ), (string) ( $gotw['stadium'] ?? '' ) ) ) );
		$side = function ( $t ) { return '<span>' . DBD_Marks::tile( $t, 54 ) . '<b>' . esc_html( self::nick( $t ) ) . '</b><small>' . esc_html( DBD_Data::record_text( $t ) ) . '</small></span>'; };
		$tot  = isset( $gotw['total'] ) && $gotw['total'] !== null && $gotw['total'] !== '' ? 'O/U ' . $gotw['total'] : '';
		$line = $done
			? array( self::nick( $gotw['away'] ) . ' ' . (int) ( $gotw['away_score'] ?? 0 ) . ', ' . self::nick( $gotw['home'] ) . ' ' . (int) ( $gotw['home_score'] ?? 0 ) )
			: array_filter( array( DBD_Router::line_text( $gotw ), $tot ) );
		$h = '<div class="hb-gotw"><p class="k">' . esc_html( $kick ) . '</p><div class="mt">' . $side( $gotw['away'] ) . '<em>at</em>' . $side( $gotw['home'] ) . '</div>' . ( $line ? '<p class="ln">' . esc_html( implode( ' · ', $line ) ) . '</p>' : '' );
		if ( $cards ) { $h .= '<p class="say"><i>' . esc_html( $cards[0][0] ) . '</i>' . esc_html( $cards[0][3] ) . '</p>'; }
		$h .= '<a class="hb-link" href="' . self::u( array( 'game' => $gotw['id'], 'side' => $O === $gotw['home'] ? 'home' : 'away' ) ) . '">' . ( $done ? 'Open the game' : 'Open the matchup' ) . '</a></div>';
		/* each bar grows from the middle toward the side with the edge: lower rank is better on both sides, so a defense ranked
		   above the offense pushes right, an offense ranked above the defense pushes left */
		$rows = '';
		foreach ( array( 'epa' => 'EPA a play', 'sr' => 'Success rate', 'expl' => 'Explosive plays', 'rz_td' => 'Red-zone TDs' ) as $mk => $lab ) {
			list( $ov, $or ) = DBD_Metrics::off( $O, $mk ); list( $dv, $dr ) = DBD_Metrics::def( $D, $mk );
			if ( ! $or || ! $dr ) { continue; }
			$edge = (int) $dr - (int) $or; $w = min( 50, abs( $edge ) * 1.6 ); $c = DBD_Marks::accent( DBD_Data::team( $edge > 0 ? $O : $D ) );
			$rows .= '<div class="hr"><span class="o">' . esc_html( DBD_Metrics::fmt( $mk, $ov ) ) . '<small>' . esc_html( DBD_Metrics::ordinal( $or ) ) . '</small></span><span class="lab">' . esc_html( $lab ) . '<span class="bar"><i class="' . ( $edge > 0 ? 'o' : 'd' ) . '" style="width:' . round( $w ) . '%;background:' . esc_attr( $c ) . '"></i></span></span><span class="d">' . esc_html( DBD_Metrics::fmt( $mk, $dv ) ) . '<small>' . esc_html( DBD_Metrics::ordinal( $dr ) ) . '</small></span></div>';
		}
		if ( $rows ) { $h .= '<div class="hb-h2h"><div class="hh">' . DBD_Marks::tile( $O, 26 ) . '<b>' . esc_html( self::nick( $O ) ) . ' offense</b><span>vs</span><b>' . esc_html( self::nick( $D ) ) . ' defense</b>' . DBD_Marks::tile( $D, 26 ) . '</div>' . $rows . '</div>'; }
		return self::wrap( 'matchup', $h );
	}

	/** A week as a sentence names it: "Week 8", or "the Wild Card round", "the Super Bowl" in the playoffs. */
	private static function week_name( $wk ) { $l = DBD_Router::week_label( $wk ); return strpos( $l, 'Week ' ) === 0 ? $l : 'the ' . $l . ( $l === 'Super Bowl' ? '' : ' round' ); }

	/** The risers in target share from the Players page's movers file (the latest week with every game final), and that week. */
	private static function movers() {
		$mv = DBD_Data::get( 'players/movers.json' );
		if ( (int) ( $mv['season'] ?? 0 ) !== DBD_Data::season() ) { return array( array(), 0 ); }  /* a new season's first week has none yet; last season's don't count */
		$up = array_values( array_filter( (array) ( $mv['targets']['up'] ?? array() ), function ( $m ) { return ! empty( $m['id'] ) && DBD_Data::player( (string) $m['id'] ); } ) );
		return array( $up, (int) ( $mv['week'] ?? 0 ) );
	}

	/** A new season's first week, before anyone has a week to rise from: the week's most-targeted players instead. */
	private static function most_targets_list( $cnt ) {
		$wk = DBD_Week::settled_week(); if ( ! $wk ) { return ''; }
		$li = '';
		foreach ( self::top_targeted( $wk, $cnt ) as $r ) {
			list( $pid, $team, $w ) = $r; $p = DBD_Data::player( $pid ); if ( ! $p ) { continue; }
			$li .= '<li>' . DBD_Marks::player( $team, $p['num'] ?? null, 40, $p['photo'] ?? null ) . '<span><b><a href="' . self::u( array( 'player' => $pid ) ) . '">' . esc_html( $p['name'] ) . '</a></b><small>' . esc_html( ( $p['pos'] ?? '' ) . ' · ' . self::nick( $team ) ) . '</small></span><em><strong>' . (int) $w['targets'] . '</strong> targets</em></li>';
		}
		return $li === '' ? '' : self::wrap( 'movers', '<div class="hb-panel"><p class="k">' . esc_html( 'Most targets in ' . self::week_name( $wk ) ) . '</p><ul class="hb-list">' . $li . '</ul></div>' );
	}

	/** A player's share card (default: the week's biggest riser in target share), linked to his page. No card drawing on the host: no block. */
	private static function b_player_card( $a ) {
		if ( ! DBD_Card::available() ) { return ''; }
		$pid = (string) ( $a['player'] ?? '' );
		if ( $pid === '' ) { $pid = (string) ( self::movers()[0][0]['id'] ?? '' ); }
		if ( $pid === '' ) { $hit = self::most_targeted( DBD_Week::settled_week() ); $pid = $hit ? (string) $hit[0] : ''; }  /* no risers yet: the week's most-targeted player */
		$p = $pid !== '' ? DBD_Data::player( $pid ) : null; if ( ! $p ) { return ''; }
		return self::wrap( 'player-card', '<a href="' . self::u( array( 'player' => $pid ) ) . '"><img src="' . esc_url( DBD_Card::url( $pid ) ) . '" alt="' . esc_attr( $p['name'] . "'s share card" ) . '" width="1200" height="630" loading="lazy" decoding="async"></a>' );
	}

	/** The biggest jumps in target share in the latest finished week: his share before that week, and that week's. The data keeps five. */
	private static function b_movers( $a ) {
		$cnt = max( 1, min( 5, (int) ( $a['count'] ?? 4 ) ) ); list( $up, $wk ) = self::movers(); $up = array_slice( $up, 0, $cnt );
		if ( ! $up ) { return self::most_targets_list( $cnt ); }
		$li = '';
		foreach ( $up as $m ) {
			$li .= '<li>' . DBD_Marks::player( $m['team'], $m['num'] ?? null, 40, $m['photo'] ?? null ) . '<span><b><a href="' . self::u( array( 'player' => $m['id'] ) ) . '">' . esc_html( $m['name'] ) . '</a></b><small>' . esc_html( ( $m['pos'] ?? '' ) . ' · ' . self::nick( $m['team'] ) ) . '</small></span><em>' . round( (float) $m['before'] ) . '% <i aria-hidden="true">→</i><span class="screen-reader-text"> to </span> <strong>' . round( (float) $m['now'] ) . '%</strong></em></li>';
		}
		return self::wrap( 'movers', '<div class="hb-panel"><p class="k">' . esc_html( ( $wk ? 'Rising in ' . self::week_name( $wk ) : 'Rising' ) . ' · share of targets' ) . '</p><ul class="hb-list">' . $li . '</ul></div>' );
	}

	/** The softest defense at each position: the most half-PPR points allowed a game, linked to that defense. */
	private static function b_fantasy( $a ) {
		$f = DBD_Fantasy::file(); if ( empty( $f['rank'] ) || empty( $f['allow'] ) ) { return ''; }
		$i = DBD_Fantasy::idx( 'half' ); $h = '';
		foreach ( array( 'QB' => 'quarterbacks', 'RB' => 'running backs', 'WR' => 'wide receivers', 'TE' => 'tight ends' ) as $pos => $who ) {
			$best = null;
			foreach ( (array) $f['rank'] as $t => $r ) { $rk = (int) ( $r[ $pos ][ $i ] ?? 0 ); if ( $rk && isset( $f['allow'][ $t ][ $pos ][ $i ] ) && ( ! $best || $rk < $best[1] ) ) { $best = array( $t, $rk ); } }
			if ( ! $best ) { continue; }
			$v = (float) $f['allow'][ $best[0] ][ $pos ][ $i ];
			$h .= '<a class="hb-soft" href="' . self::u( array( 'team' => $best[0], 'side' => 'def' ) ) . '">' . DBD_Marks::tile( $best[0], 38 ) . '<span><i>' . esc_html( $pos ) . '</i><b>' . esc_html( self::nick( $best[0] ) ) . '</b><small>allow ' . esc_html( DBD_Fantasy::num( $v ) ) . ' half-PPR points a game to ' . esc_html( $who ) . ', the most in the NFL</small></span></a>';
		}
		return self::wrap( 'fantasy', $h );
	}

	/**
	 * Season leader boards from the Leaders page's file. boards="group.metric,…" (wr.tshare, wr.epat, qb.epadb, rb.ypc…, the
	 * Leaders page's keys), or "ppr" for the PPR points leaders at every position. Only qualified players, as on the Leaders page.
	 */
	private static function b_leaders( $a ) {
		$keys = array_filter( array_map( 'trim', explode( ',', strtolower( (string) ( $a['boards'] ?? 'wr.tshare,wr.epat,qb.epadb' ) ) ) ) );
		$top = max( 3, min( 10, (int) ( $a['top'] ?? 5 ) ) ); $L = DBD_Leaders::file(); $P = (array) ( $L['players'] ?? array() ); $h = '';
		foreach ( $keys as $key ) {
			if ( $key === 'ppr' ) {
				$rows = (array) ( $L['fan']['ppr']['tot'] ?? array() ); $title = 'PPR points'; $sub = 'season leaders, every position';
				$val = function ( $r ) { return DBD_Fantasy::num( $r[1] ); };
			} else {
				$pp = explode( '.', $key ); if ( count( $pp ) !== 2 ) { continue; }
				$g = DBD_Leaders::data_group( $pp[0] ); $df = DBD_Leaders::def( $g, 'g', $pp[1] ); if ( ! $df ) { continue; }
				/* the qualified pool first; early in a season, when too few qualify, the Leaders page's lower steps */
				$st = DBD_Leaders::steps( $g );
				foreach ( $st as $min ) { $rows = DBD_Leaders::rows( $g, 'g', $pp[1], $min, DBD_Leaders::pos_of( $pp[0] ) ); if ( count( $rows ) >= $top ) { break; } }
				$title = $df[0]; $sub = DBD_Leaders::sub( $df ) ?: DBD_Leaders::pool( $pp[0] );
				if ( $min < $st[0] ) { $sub .= ( $sub !== '' ? ', ' : '' ) . 'at least ' . $min . ' ' . DBD_Leaders::unit( $g ); }  /* a lower minimum says so, as the Leaders page does (Greptile, PR #65) */
				$val = function ( $r ) use ( $df ) { return DBD_Leaders::fmt( 'g', $df[2], $r[1] ); };
			}
			$li = ''; $n = 0;
			foreach ( $rows as $r ) {
				$p = $P[ $r[0] ] ?? null; if ( ! $p ) { continue; }  /* a player missing from the index has no page to link */
				list( $name, $team, $pos, $num, $photo ) = array_pad( (array) $p, 5, null ); $n++;
				$li .= '<li><span class="n">' . $n . '</span>' . ( $team ? DBD_Marks::player( $team, $num, 32, $photo ) : '' ) . '<span><b><a href="' . self::u( array( 'player' => $r[0] ) ) . '">' . esc_html( $name ) . '</a></b><small>' . esc_html( ( $team ? self::nick( $team ) . ' · ' : '' ) . $pos ) . '</small></span><em>' . esc_html( $val( $r ) ) . '</em></li>';
				if ( $n >= $top ) { break; }
			}
			if ( $li === '' ) { continue; }
			$h .= '<div class="hb-board"><h3>' . esc_html( $title ) . '</h3>' . ( $sub !== '' ? '<p class="sub">' . esc_html( $sub ) . '</p>' : '' ) . '<ol class="hb-list">' . $li . '</ol></div>';
		}
		return self::wrap( 'leaders', $h );
	}

	/** All 32 teams by division, AFC then NFC, best record first, each linked to its page. */
	private static function b_teams( $a ) {
		$T = DBD_Data::teams(); $h = '';
		foreach ( array( 'AFC', 'NFC' ) as $c ) {
			foreach ( array( 'East', 'North', 'South', 'West' ) as $d ) {
				$div = $c . ' ' . $d; $ts = array_keys( array_filter( $T, function ( $t ) use ( $div ) { return ( $t['div'] ?? '' ) === $div; } ) );
				if ( ! $ts ) { continue; }
				usort( $ts, function ( $x, $y ) { return array( DBD_Data::win_pct( $y ), $x ) <=> array( DBD_Data::win_pct( $x ), $y ); } );
				$h .= '<div class="hb-div"><h4>' . esc_html( $div ) . '</h4>';
				foreach ( $ts as $t ) { $h .= '<a href="' . self::u( array( 'team' => $t ) ) . '">' . DBD_Marks::tile( $t, 30 ) . '<span>' . esc_html( self::nick( $t ) ) . '</span><em>' . esc_html( DBD_Data::record_text( $t ) ) . '</em></a>'; }
				$h .= '</div>';
			}
		}
		return self::wrap( 'teams', $h );
	}

	/**
	 * The latest finished week as the Tuesday email: a subject (the biggest upset, else the wildest game), a headline count of upsets,
	 * comebacks and overtimes, the swing, the play and the risers, and a button to the week in review. No finished week yet: no block.
	 */
	private static function b_newsletter_preview( $a ) {
		$n = 0; $W = null;
		foreach ( array_reverse( DBD_Week::weeks() ) as $w ) { $f = DBD_Week::file( $w ); if ( $f && ! empty( $f['complete'] ) ) { $n = $w; $W = $f; break; } }
		if ( ! $W ) { return ''; }
		$lab = DBD_Router::week_label( $n );
		$fin = array_values( array_filter( (array) $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final'; } ) );
		$nu = 0; $nc = 0; $no = 0; $up = null;
		/* upsets, comebacks and overtimes come from each game's win probability, which can trail the score: count them only when every game has it (Greptile, PR #65) */
		$known = $fin && count( array_filter( $fin, function ( $g ) { return isset( $g['pre'], $g['tags'] ); } ) ) === count( $fin );  /* pre, not pre_w: a tie has no winner's chance */
		foreach ( $fin as $g ) {
			$tg = self::tags( $g );
			if ( isset( $tg['comeback'] ) ) { $nc++; }
			if ( ! empty( $g['ot'] ) ) { $no++; }
			if ( ! empty( $g['win'] ) && isset( $tg['upset'] ) ) { $nu++; if ( ! $up || $g['pre_w'] < $up['pre_w'] ) { $up = $g; } }
		}
		if ( $up ) { list( $w, $l ) = self::wl( $up ); $subject = $lab . ': ' . self::nick( $w ) . ' stun ' . self::nick( $l ) . ' from ' . self::pct( $up['pre_w'] ) . '%'; }
		else {
			$wd = $fin; usort( $wd, function ( $x, $y ) { return array( $y['lc'] ?? 0, $y['drama'] ?? 0 ) <=> array( $x['lc'] ?? 0, $x['drama'] ?? 0 ); } );
			$subject = $wd && ! empty( $wd[0]['win'] ) ? $lab . ': ' . self::nick( $wd[0]['win'] ) . ' win the wildest game' : $lab . ' in review';
		}
		$pl = function ( $k, $one, $many ) { return $k . ' ' . ( $k === 1 ? $one : $many ); };
		$head = $known ? $pl( $nu, 'upset', 'upsets' ) . ', ' . $pl( $nc, 'comeback', 'comebacks' ) . ', ' . $pl( $no, 'overtime', 'overtimes' ) : $pl( count( $fin ), 'game', 'games' ) . ', all final';
		if ( ! $known && ! $up ) { $subject = $lab . ' in review'; }
		$ps = '';
		$sw = $W['swings'][0] ?? null;
		if ( $sw ) { $ps .= '<p><b>The swing:</b> ' . esc_html( self::clean( $sw['d'] ) . ' (' . self::nick( $sw['t'] ) . ', ' . $sw['c'] . ', +' . round( (float) $sw['v'] ) . '%)' ) . '</p>'; }
		$tp = $W['plays'][0] ?? null;
		if ( $tp ) { $ps .= '<p><b>The play:</b> ' . esc_html( self::clean( $tp['d'] ) . ' (' . ( $tp['e'] >= 0 ? '+' : '−' ) . number_format( abs( (float) $tp['e'] ), 2 ) . ' EPA)' ) . '</p>'; }
		$ri = array_slice( (array) ( $W['movers']['targets']['up'] ?? array() ), 0, 3 );
		if ( $ri ) { $ps .= '<p><b>Rising:</b> ' . esc_html( implode( ', ', array_map( function ( $m ) { return $m['name']; }, $ri ) ) . ( count( $ri ) === 1 ? ' saw his' : ' saw their' ) . ' target share jump' ) . '</p>'; }
		return self::wrap( 'newsletter-preview', '<div class="hb-mail"><div class="mh"><span>From: Down by Down</span><span>Subject: <b>' . esc_html( $subject ) . '</b></span></div><div class="mb"><p class="mk">' . esc_html( $lab ) . ' in review</p><h4>' . esc_html( $head ) . '</h4>' . $ps . '<a class="mbtn" href="' . self::u( array( 'view' => 'week', 'week' => (int) $n ) ) . '">Read the full week</a></div></div>' );
	}
}
