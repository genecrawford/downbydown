<?php
/**
 * Share a player from the WordPress toolbar (SCOPE §103). On a player's page (offense or defender), a site admin gets a Share player item in
 * the toolbar. It opens a panel with a 1080 × 1350 graphic of his best stat, buttons for his best three, the post's text, Share…, Download
 * and Copy, Post now to Down by Down's own four accounts, and compose links for whatever accounts the admin's browser is logged into.
 *   - The tiles: the same ones his page shows, built here for both the page and the graphic, so the graphic never says something his page doesn't.
 *   - The best stat: the tile with the best "Nth of N" rank; a tie goes to the stat fans quote first (FIRST), then the page's order.
 *     Snap share, "Thrown at him" and catches allowed are never picked (SKIP).
 *   - Nothing of this is printed for a visitor: the toolbar is drawn only for logged-in users, and the item, panel and data only for admins.
 *     Cloudflare caches player pages for visitors, which is why none of it is in the page itself.
 *   - The graphic is a card key, share-{player id}-{stat}-{look}, cached and pruned like the tall graphics; the week's look is the Share pack's.
 *   - Post now goes one network per request (Instagram and Threads can take half a minute each), through each client's share(); a network
 *     keeps one post per player per week (share-{season}-w{N}-{id}), so a second press can't post him twice.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Share {
	/** Never the pick: they rank how much he plays or is thrown at, not how well. */
	const SKIP = array( 'o' => array( 'snap' ), 'd' => array( 'snap', 'tgt', 'cmp' ) );
	/** A tie goes to the stat fans quote first, by tile key; the rest keep the page's order. */
	const FIRST = array(
		'rec'   => array( 'yds', 'td', 'rec', 'tsh', 'tgt', 'yac', 'epat', 'adot' ),
		'rb'    => array( 'ruy', 'rutd', 'expl', 'tpg', 'car', 'tgt', 'epatch' ),
		'qb'    => array( 'py', 'ptd', 'epadb', 'any', 'cmp', 'ruy', 'att', 'sack' ),
		'front' => array( 'sk', 'prs', 'qbh', 'ff', 'tfl', 'hur', 'tkl' ),
		'lb'    => array( 'tkl', 'sk', 'tfl', 'prs', 'pd', 'rate' ),
		'db'    => array( 'int', 'pd', 'rate', 'yds', 'tkl' ),
	);
	/** A defender's tiles by role, in the page's order. */
	const ROLE_TILES = array( 'front' => array( 'prs', 'sk', 'qbh', 'tfl', 'tkl', 'ff', 'hur', 'snap' ), 'lb' => array( 'tkl', 'tfl', 'sk', 'prs', 'tgt', 'rate', 'pd', 'snap' ), 'db' => array( 'tgt', 'cmp', 'yds', 'rate', 'int', 'pd', 'tkl', 'snap' ) );
	const ROLE_NAME = array( 'front' => 'pass rushers and linemen', 'lb' => 'linebackers', 'db' => 'defensive backs' );
	/** The words a stat takes in the post's text, where the tile's own would read badly after the number. */
	const SAY = array( 'adot' => 'air yards per target', 'sack' => 'sacks taken', 'tpg' => 'touches a game', 'any' => 'ANY/A', 'rate' => 'passer rating allowed' );
	/** One of a thing, for a count of 1. */
	const ONE = array( 'targets' => 'target', 'receptions' => 'reception', 'receiving touchdowns' => 'receiving touchdown', 'carries' => 'carry', 'rushing touchdowns' => 'rushing touchdown',
		'explosive plays' => 'explosive play', 'attempts' => 'attempt', 'touchdown passes' => 'touchdown pass', 'sacks taken' => 'sack taken', 'pressures' => 'pressure', 'sacks' => 'sack',
		'QB hits' => 'QB hit', 'tackles for loss' => 'tackle for loss', 'tackles' => 'tackle', 'forced fumbles' => 'forced fumble', 'hurries' => 'hurry', 'interceptions' => 'interception', 'passes defended' => 'pass defended' );
	const NETS = array( 'ig' => 'Instagram', 'bsky' => 'Bluesky', 'x' => 'X', 'th' => 'Threads' );
	const NONCE = 'dbd_share';

	/** The harness sets this: true draws the toolbar item and panel as for an admin, false as for a visitor. */
	public static $admin = null;

	public static function init() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'bar' ), 90 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 30 );
		add_action( 'wp_footer', array( __CLASS__, 'footer' ) );
		add_action( 'wp_ajax_dbd_share_post', array( __CLASS__, 'post_action' ) );
	}

	public static function admin() { return self::$admin ?? ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) ); }

	/* ------------------------------------------------------------------ the tiles, for the page and the graphic */

	/**
	 * An offense player's eight tiles, as his page shows them: array( label, value, second line, key, what it ranks ), plus 'r' (rank),
	 * 'n' (the pool) and 'tie' when he's ranked. $p: his record, $a: his team.
	 */
	public static function offense_tiles( $p, $a ) {
		$W = $p['weeks'] ?? array();
		$played = array_unique( array_merge( array_map( function ( $w ) { return (int) $w['week']; }, $W ), array_filter( array_map( 'intval', array_keys( $p['snaps'] ?? array() ) ), function ( $wk ) use ( $p, $a ) { return DBD_Data::stats_posted( $p['snapteam'][ (string) $wk ] ?? $a, $wk ); } ) ) );
		$gp = max( 1, count( $played ) );
		$sum = function ( $k ) use ( $W ) { $s = 0; foreach ( $W as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s; };
		$avg = function ( $k ) use ( $W, $gp ) { $s = 0; foreach ( $W as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s / $gp; };
		$pct = function ( $v ) { return is_finite( $v ) && ! is_nan( $v ) ? round( $v * 100 ) . '%' : '-'; };
		$f1  = function ( $v ) { return is_finite( $v ) && ! is_nan( $v ) ? number_format( $v, 1 ) : '-'; };
		$sgn = function ( $v, $d = 2 ) { return ( $v > 0 ? '+' : '' ) . number_format( $v, $d ); };
		$div = function ( $n, $d ) { return $d ? $n / $d : NAN; };
		$grp = $p['pos'] === 'QB' ? 'qb' : ( in_array( $p['pos'], array( 'RB', 'FB' ), true ) ? 'rb' : 'rec' );
		$snapAvg = ! empty( $p['snaps'] ) ? array_sum( $p['snaps'] ) / count( $p['snaps'] ) : 0;
		$EP = ! empty( $p['ep']['sec'] ) ? $p['ep'] : null;
		$tg = $sum( 'targets' ); $rc = $sum( 'receptions' ); $ry = $sum( 'receiving_yards' ); $rtd = $sum( 'receiving_tds' ); $air = $sum( 'receiving_air_yards' ); $yac = $sum( 'receiving_yards_after_catch' ); $repa = $sum( 'receiving_epa' ); $rfd = $sum( 'receiving_first_downs' );
		$ca = $sum( 'carries' ); $ruy = $sum( 'rushing_yards' ); $rutd = $sum( 'rushing_tds' ); $ruepa = $sum( 'rushing_epa' );
		$att = $sum( 'attempts' ); $cmp = $sum( 'completions' ); $py = $sum( 'passing_yards' ); $ptd = $sum( 'passing_tds' ); $pint = $sum( 'passing_interceptions' ); $pepa = $sum( 'passing_epa' ); $sk = $sum( 'sacks_suffered' );
		$tshare = $avg( 'target_share' ); $ashare = $avg( 'air_yards_share' );
		$qbw = array_filter( $W, function ( $w ) { return ( $w['attempts'] ?? 0 ) > 0; } ); $cpoe = $qbw ? array_sum( array_map( function ( $w ) { return (float) ( $w['passing_cpoe'] ?? 0 ); }, $qbw ) ) / count( $qbw ) : 0;
		$hasPfd = (bool) array_filter( $W, function ( $w ) { return array_key_exists( 'passing_first_downs', $w ); } );  /* first downs reached the weekly lines in 1.5.0's build */
		if ( $grp === 'rec' ) {
			$tiles = array( array( 'Targets', (int) $tg, $f1( $tg / $gp ) . ' per game', 'tgt', 'targets' ), array( 'Receptions', (int) $rc, $pct( $div( $rc, $tg ) ) . ' catch rate', 'rec', 'receptions' ), array( 'Yards', (int) $ry, round( $ry / $gp ) . ' per game', 'yds', 'receiving yards' ), array( 'Touchdowns', (int) $rtd, (int) $rfd . ' first downs', 'td', 'receiving touchdowns' ),
				array( 'Target share', $pct( $tshare ), $pct( $ashare ) . ' of air yards', 'tsh', 'target share' ), array( 'aDOT', $f1( $div( $air, $tg ) ), 'air yards per target', 'adot', 'depth of target' ), array( 'EPA / target', $tg ? $sgn( $repa / $tg ) : '-', $sgn( $repa, 1 ) . ' total', 'epat', 'EPA per target' ), array( 'YAC / catch', $f1( $div( $yac, $rc ) ), (int) $yac . ' after the catch', 'yac', 'yards after the catch per catch' ) );
		} elseif ( $grp === 'rb' ) {
			$tiles = array( array( 'Carries', (int) $ca, $f1( $ca / $gp ) . ' per game', 'car', 'carries' ), array( 'Rush yards', (int) $ruy, $f1( $div( $ruy, $ca ) ) . ' per carry', 'ruy', 'rushing yards' ), array( 'Rush TD', (int) $rutd, (int) $sum( 'rushing_first_downs' ) . ' first downs', 'rutd', 'rushing touchdowns' ), array( 'Targets', (int) $tg, (int) $rc . ' catches · ' . (int) $ry . ' yds', 'tgt', 'targets' ),
				array( 'Touches / game', $f1( ( $ca + $tg ) / $gp ), (int) ( $ca + $tg ) . ' total', 'tpg', 'touches per game' ), array( 'EPA / touch', ( $ca + $tg ) ? $sgn( ( $ruepa + $repa ) / ( $ca + $tg ) ) : '-', $sgn( $ruepa + $repa, 1 ) . ' total', 'epatch', 'EPA per touch' ), array( 'Explosive', (int) ( $sum( 'rushing_10' ) + $sum( 'receiving_20' ) ), '10+ run · 20+ catch', 'expl', 'explosive plays' ), array( 'Snap share', round( $snapAvg ) . '%', 'offensive snaps', 'snap', 'snap share' ) );
		} else {
			$tiles = array( array( 'Attempts', (int) $att, $f1( $att / $gp ) . ' per game', 'att', 'attempts' ), array( 'Completion', $pct( $div( $cmp, $att ) ), $sgn( $cpoe, 1 ) . ' over expected', 'cmp', 'completion rate' ), array( 'Pass yards', (int) $py, $f1( $div( $py, $att ) ) . ' per attempt', 'py', 'passing yards' ), array( 'TD / INT', (int) $ptd . ' / ' . (int) $pint, $hasPfd ? (int) $sum( 'passing_first_downs' ) . ' first downs' : $pct( $div( $ptd, $att ) ) . ' TD rate', 'ptd', 'touchdown passes' ),
				array( 'EPA / dropback', ( $att + $sk ) ? $sgn( $pepa / ( $att + $sk ) ) : '-', $sgn( $pepa, 1 ) . ' total', 'epadb', 'EPA per dropback' ), array( 'Sacks', (int) $sk, $pct( $div( $sk, $att + $sk ) ) . ' of dropbacks', 'sack', 'sack rate, fewest first' ), array( 'Rush', (int) $ruy . ' yds', (int) $ca . ' carries · ' . (int) $rutd . ' TD', 'ruy', 'rushing yards' ),
				isset( $EP['any'] ) ? array( 'ANY/A', number_format( (float) $EP['any'], 2 ), 'net yds per dropback', 'any', 'ANY/A' ) : array( 'Snap share', round( $snapAvg ) . '%', 'offensive snaps', null, '' ) );  /* the hero badge already shows snap share */
		}
		/* the ranks his page shows: "5th of 38" against the pool Where he ranks uses, ties as T-; none outside the pool */
		$gpool = DBD_Grades::in_use( $p['grades']['pool'] ?? DBD_Grades::pool_of( $p['pos'] ) ?? $grp ); $TRK = (array) ( $p['trk'] ?? array() ); $poolN = (int) ( DBD_Grades::group_info( $gpool )['n'] ?? 0 );
		foreach ( $tiles as &$x ) {
			if ( $x[3] && ! empty( $TRK[ $x[3] ] ) && $poolN ) { $x['r'] = (int) $TRK[ $x[3] ][0]; $x['n'] = $poolN; $x['tie'] = ! empty( $TRK[ $x[3] ][1] ); }
		}
		unset( $x );
		return $tiles;
	}

	/**
	 * A defender's eight tiles for his role, as his page shows them: array( label, value, second line, rank, pool, key ), the rank and pool
	 * only for a regular role ($d['q']). Rank-less tiles (catches allowed) carry nulls.
	 */
	public static function defense_tiles( $d ) {
		$s = (array) ( $d['s'] ?? array() ); $role = in_array( $d['role'] ?? '', array( 'front', 'lb', 'db' ), true ) ? $d['role'] : 'front'; $q = ! empty( $d['q'] );
		$g = max( 1, (int) ( $s['g'] ?? 0 ) ); $R = (array) ( $d['r'] ?? array() ); $TR = (array) ( $d['tr'] ?? array() );
		$n0 = function ( $v ) { $v = (float) $v; return $v == (int) $v ? (string) (int) $v : rtrim( rtrim( number_format( $v, 1 ), '0' ), '.' ); };
		$tr = function ( $k ) use ( $TR ) { return isset( $TR[ $k ] ) ? array( (int) $TR[ $k ][0], (int) $TR[ $k ][1] ) : array( null, null ); };
		$mr = function ( $k ) use ( $R ) { return isset( $R[ $k ][0] ) && $R[ $k ][0] ? array( (int) $R[ $k ][0], (int) $R[ $k ][2] ) : array( null, null ); };
		$pg = function ( $k, $dp = 1 ) use ( $s, $g ) { return number_format( (float) ( $s[ $k ] ?? 0 ) / $g, $dp ) . ' a game'; };
		$tgt = (float) ( $s['tgt'] ?? 0 );
		$T = array(
			'prs' => array( 'Pressures', $n0( $s['prs'] ?? 0 ), $pg( 'prs' ), $tr( 'prs' ) ), 'sk' => array( 'Sacks', $n0( $s['sk'] ?? 0 ), $pg( 'sk', 2 ), $tr( 'sk' ) ),
			'qbh' => array( 'QB hits', $n0( $s['qbh'] ?? 0 ), $pg( 'qbh' ), $tr( 'qbh' ) ), 'tfl' => array( 'Tackles for loss', $n0( $s['tfl'] ?? 0 ), $pg( 'tfl' ), $tr( 'tfl' ) ),
			'tkl' => array( 'Tackles', $n0( $s['tkl'] ?? 0 ), $pg( 'tkl' ), $tr( 'tkl' ) ), 'ff' => array( 'Forced fumbles', $n0( $s['ff'] ?? 0 ), 'and ' . $n0( $s['pd'] ?? 0 ) . ' passes defended', $tr( 'ff' ) ),
			'hur' => array( 'Hurries', $n0( $s['hur'] ?? 0 ), 'rushed the passer off his spot', $tr( 'hur' ) ), 'snap' => array( 'Snap share', (int) round( (float) ( $s['snap'] ?? 0 ) ) . '%', "of the defense's snaps", $mr( 'snap' ) ),
			'tgt' => array( 'Thrown at him', $n0( $tgt ), $pg( 'tgt' ), $tr( 'tgt' ) ), 'cmp' => array( 'Catches allowed', $n0( $s['cmp'] ?? 0 ), $tgt ? round( $s['cmp'] / $tgt * 100 ) . '% of targets' : 'no targets', array( null, null ) ),
			'yds' => array( 'Yards allowed', $n0( $s['yds'] ?? 0 ), $tgt ? number_format( $s['yds'] / $tgt, 1 ) . ' a target' : 'in coverage', $mr( 'ypt' ) ),
			'rate' => array( 'Passer rating', isset( $s['rate'] ) && $s['rate'] !== null ? number_format( (float) $s['rate'], 1 ) : '–', 'when thrown at', $mr( 'rate' ) ),
			'int' => array( 'Interceptions', $n0( $s['int'] ?? 0 ), $n0( $s['tda'] ?? 0 ) . ' TD allowed', $tr( 'int' ) ), 'pd' => array( 'Passes defended', $n0( $s['pd'] ?? 0 ), $pg( 'pd' ), $tr( 'pd' ) ),
		);
		$o = array();
		foreach ( self::ROLE_TILES[ $role ] as $k ) { $x = $T[ $k ]; $o[] = array( $x[0], $x[1], $x[2], $q ? $x[3][0] : null, $x[3][1], $k ); }
		return $o;
	}

	/* ------------------------------------------------------------------ who, and his best stats */

	/** The player a page or a key is about: array( side o|d, id, rec, team, name, grp (rec|rb|qb or a role), pool words ), or null. */
	public static function subject( $id ) {
		$id = (string) $id; if ( ! preg_match( '/^\d{2}-\d{7}$/', $id ) ) { return null; }
		$row = DBD_Data::player( $id );
		if ( $row && ! DBD_Kicker::is( $row ) ) {
			$p = DBD_Data::player_record( $row['team'], $id ); if ( ! $p ) { return null; }
			$grp = $p['pos'] === 'QB' ? 'qb' : ( in_array( $p['pos'], array( 'RB', 'FB' ), true ) ? 'rb' : 'rec' );
			$gpool = DBD_Grades::in_use( $p['grades']['pool'] ?? DBD_Grades::pool_of( $p['pos'] ) ?? $grp );
			return array( 'side' => 'o', 'id' => $id, 'rec' => $p, 'team' => (string) $row['team'], 'name' => (string) $p['name'], 'pos' => (string) $p['pos'], 'num' => (string) ( $p['num'] ?? '' ), 'grp' => $grp, 'pool' => 'qualifying ' . DBD_Grades::pool_label( $gpool ) );
		}
		if ( $row ) { return null; }  /* a kicker's page has no ranked tiles */
		$dr = DBD_Data::defender( $id ); if ( ! $dr ) { return null; }
		$d = DBD_Data::defender_record( $dr['team'], $id ); if ( ! $d ) { return null; }
		$role = in_array( $d['role'] ?? '', array( 'front', 'lb', 'db' ), true ) ? $d['role'] : 'front';
		return array( 'side' => 'd', 'id' => $id, 'rec' => $d, 'team' => (string) $d['team'], 'name' => (string) $d['name'], 'pos' => (string) ( $d['pos'] ?? '' ), 'num' => (string) ( $d['num'] ?? '' ), 'grp' => $role, 'pool' => self::ROLE_NAME[ $role ] . ' with a regular role' );
	}

	/** The page's player, when this is a player's or defender's page this season. */
	public static function here() {
		$r = DBD_Router::$route ?? null; if ( ! $r || ! in_array( $r['view'] ?? '', array( 'player', 'defender' ), true ) || DBD_Data::archived() ) { return null; }
		return self::subject( $r['player']['id'] ?? '' );
	}

	/**
	 * His ranked stats, best first: array( k, lab, v, sub, r, n, tie, rank (T-13th), what ). The best rank; a tie goes to FIRST's order,
	 * then the page's. SKIP is never in it.
	 */
	public static function stats( $s ) {
		if ( ! $s ) { return array(); }
		$o = array();
		if ( $s['side'] === 'o' ) {
			foreach ( self::offense_tiles( $s['rec'], $s['team'] ) as $i => $x ) {
				if ( ! $x[3] || ! isset( $x['r'] ) || in_array( $x[3], self::SKIP['o'], true ) ) { continue; }
				$e = array( 'k' => $x[3], 'lab' => $x[0], 'v' => (string) $x[1], 'sub' => (string) $x[2], 'r' => $x['r'], 'n' => $x['n'], 'tie' => $x['tie'], 'what' => self::SAY[ $x[3] ] ?? $x[4], 'i' => $i );
				if ( $x[3] === 'sack' ) { $e = array_merge( $e, array( 'lab' => 'Sack rate', 'v' => (string) strtok( (string) $x[2], ' ' ), 'sub' => (int) $x[1] . ' sacks taken', 'what' => 'sack rate' ) ); }  /* the tile shows sacks taken; its rank is sack rate, fewest first (Greptile, PR #179) */
				$o[] = $e;
			}
		} else {
			foreach ( self::defense_tiles( $s['rec'] ) as $i => $x ) {
				if ( ! $x[3] || in_array( $x[5], self::SKIP['d'], true ) ) { continue; }
				$e = array( 'k' => $x[5], 'lab' => $x[0], 'v' => (string) $x[1], 'sub' => (string) $x[2], 'r' => (int) $x[3], 'n' => (int) $x[4], 'tie' => false, 'what' => self::SAY[ $x[5] ] ?? ( $x[0] === 'QB hits' ? 'QB hits' : strtolower( $x[0] ) ), 'i' => $i );
				if ( $x[5] === 'yds' ) {  /* the tile shows yards allowed; its rank is yards a target, fewest first (Greptile, PR #179) */
					$sd = (array) ( $s['rec']['s'] ?? array() ); $tg = (float) ( $sd['tgt'] ?? 0 ); if ( ! $tg ) { continue; }
					$e = array_merge( $e, array( 'lab' => 'Yards a target', 'v' => number_format( (float) ( $sd['yds'] ?? 0 ) / $tg, 1 ), 'sub' => (string) $x[1] . ' yards allowed on ' . (int) $tg . ' targets', 'what' => 'yards a target allowed in coverage' ) );
				}
				$o[] = $e;
			}
		}
		$first = self::FIRST[ $s['grp'] ] ?? array();
		usort( $o, function ( $a, $b ) use ( $first ) {
			$pa = array_search( $a['k'], $first, true ); $pb = array_search( $b['k'], $first, true );
			return array( $a['r'], $pa === false ? 99 : $pa, $a['i'] ) <=> array( $b['r'], $pb === false ? 99 : $pb, $b['i'] );
		} );
		foreach ( $o as &$x ) { $x['rank'] = ( $x['tie'] ? 'T-' : '' ) . DBD_Grades::ordinal( $x['r'] ); unset( $x['i'] ); }
		unset( $x );
		return $o;
	}

	/** One of his ranked stats by key, or null. */
	public static function stat( $s, $k ) { foreach ( self::stats( $s ) as $x ) { if ( $x['k'] === $k ) { return $x; } } return null; }

	/** The graphic's heading and big number: the tile's, except where it reads badly that large (a QB's Rush tile, TD / INT). */
	public static function show( $st ) {
		if ( $st['k'] === 'ruy' && $st['lab'] === 'Rush' ) { return array( 'Rush yards', (string) (int) $st['v'] ); }
		if ( $st['k'] === 'ptd' ) { return array( 'TD passes', trim( explode( '/', $st['v'] )[0] ) ); }
		return array( $st['lab'], $st['v'] );
	}

	/** The post's text, without the link: "Dallas Turner: 31 pressures, 3rd of 191 pass rushers and linemen with a regular role, through Week 12." */
	public static function text( $s, $st ) {
		list( , $num ) = self::show( $st ); $what = $st['what'];
		if ( $num === '1' && isset( self::ONE[ $what ] ) ) { $what = self::ONE[ $what ]; }
		return $s['name'] . ': ' . $num . ' ' . $what . ', ' . $st['rank'] . ' of ' . (int) $st['n'] . ' ' . $s['pool'] . ', through Week ' . (int) DBD_Data::through_week() . '.';
	}

	/** His page, the link the posts carry. */
	public static function link( $s ) { return DBD_Router::url( array( 'player' => $s['id'] ) ); }
	/** The page's last path part, for the download's name. */
	public static function slug( $s ) { return preg_match( '#/player/([a-z0-9-]+)/?#', self::link( $s ), $m ) ? $m[1] : $s['id']; }

	/* ------------------------------------------------------------------ the graphic */

	/** The week's look from the Share pack. */
	public static function look() { return DBD_Tall::look( (int) DBD_Data::through_week() ); }
	public static function key( $id, $k, $look = null ) { return 'share-' . $id . '-' . $k . '-' . ( $look ?? self::look() ); }
	public static function parse( $key ) { return preg_match( '/^share-(\d{2}-\d{7})-([a-z]{2,6})-(black|paper)$/', (string) $key, $m ) ? array( $m[1], $m[2], $m[3] ) : null; }
	/** The graphic's address. It carries the data stamp, so a browser that kept last week's graphic (served with a day's cache) asks again (Greptile, PR #179). */
	public static function img_url( $id, $k ) { return add_query_arg( array( 'v' => substr( md5( DBD_Card::stamp() . ( DBD_Data::logos() ? '-l' : '' ) ), 0, 8 ) ), DBD_Card::jpg_url( self::key( $id, $k ) ) ); }

	/** Draw a share graphic, 1080 × 1350, or null for a key that isn't one of his ranked stats. */
	public static function render( $key, $fetch ) {
		$kp = self::parse( $key ); if ( ! $kp ) { return null; } list( $id, $k, $look ) = $kp;
		$s = self::subject( $id ); $all = self::stats( $s ); $st = null; $rest = array();
		foreach ( $all as $x ) { if ( $x['k'] === $k && ! $st ) { $st = $x; } else { $rest[] = $x; } }
		if ( ! $st ) { return null; }
		$c = new DBD_Tall_Canvas( $look, $fetch ); if ( ! $c->ok ) { return null; }
		$W = DBD_Tall::W; $t = $s['team']; $c1 = DBD_Tall::c1( $t ); $ink = DBD_Tall::on( $c1 ); $bh = 560; $up = function ( $x ) { return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( (string) $x ) : strtoupper( (string) $x ); };
		/* the team-color block: number, headshot (or the logo), the season line, logo, name, position and team */
		$c->rect( 0, 0, $W, $bh, $c1 );
		if ( $s['num'] !== '' ) { $c->text( $W - 20, 330, $s['num'], 'b', 400, DBD_Tall::mix( $c1, $ink, 0.13 ), true ); }
		if ( $c->head_ok( $id, $t ) ) { $c->head( $id, $t, 440, 30, 640, $bh - 30, 1.02 ); } else { $c->logo( $t, 620, 120, 340, true ); }
		$c->text( 56, 92, (int) DBD_Data::season() . ' SEASON · THROUGH WEEK ' . (int) DBD_Data::through_week(), 's', 30, $ink, false, 2.2 );
		$c->logo( $t, 56, 138, 110, true );
		$nm = preg_split( '/\s+/', trim( $s['name'] ) ); $first = $up( array_shift( $nm ) ); $last = $up( implode( ' ', $nm ) );
		$c->text( 56, 338, $first, 's', $c->fit( $first, 's', 50, 380 ), $ink ); $c->text( 56, 424, $last, 'b', $c->fit( $last, 'b', 104, 380 ), $ink );
		$c->text( 56, 480, $up( $s['pos'] . ' · ' . ( DBD_Data::team( $t )['nick'] ?? $t ) ), 's', 30, $ink, false, 2.2 );
		/* the stat: its name, the number in red, the rank large, who it's among, the tile's second line */
		list( $lab, $val ) = self::show( $st ); $lab = $up( $lab );
		$c->text( 56, 652, $lab, 'b', $c->fit( $lab, 'b', 72, $W - 112 ), $c->t['ink'] );
		$vpx = $c->fit( $val, 'b', 230, 470 ); $c->text( 50, 880, $val, 'b', $vpx, $c->t['red'] ); $rx = 50 + $c->w( $val, 'b', $vpx ) + 40;
		$rt = $up( $st['rank'] ); $c->text( $rx, 770, $rt, 'b', $c->fit( $rt, 'b', 96, $W - 56 - $rx ), $c->t['ink'] );
		foreach ( array_slice( $c->wrap( 'of ' . (int) $st['n'] . ' ' . $s['pool'], 'p', 30, $W - 56 - $rx ), 0, 3 ) as $j => $ln ) { $c->text( $rx, 818 + $j * 40, $ln, 'p', 30, $c->t['ink'] ); }
		$c->text( 56, 940, $st['sub'], 'p', 30, $c->t['mut'] );
		/* his next two */
		if ( $rest ) {
			$y = 1000; $c->rect( 56, $y, $W - 112, 2, $c->t['line'] ); $c->text( 56, $y + 52, 'ALSO', 's', 28, $c->t['mut'], false, 2.4 );
			foreach ( array_slice( $rest, 0, 2 ) as $j => $o ) {
				$yy = $y + 112 + $j * 84;
				$c->text( 56, $yy, $o['lab'], 'p', 32, $c->t['ink'] ); $c->text( 640, $yy, $o['v'], 'b', 44, $c->t['ink'], true );
				$c->text( $W - 56, $yy, $o['rank'] . ' of ' . (int) $o['n'], 's', 32, $c->t['mut'], true );
				$c->rect( 56, $yy + 28, $W - 112, 2, $c->t['line'] );
			}
		}
		$c->foot(); return $c->done();
	}

	/* ------------------------------------------------------------------ the toolbar item and the panel */

	/** The toolbar item, for an admin on a ranked player's page. */
	public static function bar( $bar ) {
		if ( ! self::admin() || ! is_object( $bar ) || ! method_exists( $bar, 'add_node' ) ) { return; }
		$s = self::here(); if ( ! $s || ! self::stats( $s ) ) { return; }
		$bar->add_node( array( 'id' => 'dbd-share', 'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">Share player</span>', 'href' => '#dbd-share', 'meta' => array( 'class' => 'dbd-share-node', 'title' => 'Share ' . $s['name'] ) ) );
	}

	public static function assets() {
		if ( ! self::admin() || ! self::here() ) { return; }
		wp_enqueue_style( 'dbd-share-player', DBD_URL . 'assets/share-player.css', array(), DBD_VERSION );
		wp_enqueue_script( 'dbd-share-player', DBD_URL . 'assets/share-player.js', array(), DBD_VERSION, true );
	}

	/** What the panel needs: the player, his best three, each network's state for him this week, the address to post through. */
	public static function data( $s ) {
		$link = self::link( $s ); $slug = self::slug( $s ); $stats = array();
		foreach ( array_slice( self::stats( $s ), 0, 3 ) as $st ) {
			$stats[] = array( 'k' => $st['k'], 'lab' => $st['lab'], 'rank' => $st['rank'], 'v' => $st['v'], 'n' => (int) $st['n'], 'text' => self::text( $s, $st ), 'img' => self::img_url( $s['id'], $st['k'] ), 'file' => 'dbd-' . $slug . '-' . $st['k'] . '.jpg' );
		}
		$nets = array(); $jk = self::job_key( $s['id'] );
		foreach ( self::NETS as $n => $name ) { list( $on, $link0 ) = self::net_state( $n, $jk ); $nets[] = array( 'n' => $n, 'name' => $name, 'on' => $on, 'posted' => $link0 ); }
		return array( 'id' => $s['id'], 'name' => $s['name'], 'url' => $link, 'stats' => $stats, 'nets' => $nets, 'ajax' => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '', 'nonce' => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( self::NONCE ) : '' );
	}

	/** The panel, in the footer: for an admin on a ranked player's page, and nothing for anyone else. */
	public static function footer() { echo self::panel(); }
	public static function panel() {
		if ( ! self::admin() ) { return ''; }
		$s = self::here(); if ( ! $s || ! self::stats( $s ) ) { return ''; }
		$d = self::data( $s );
		$box = function ( $x ) { return '<label class="dbs-net" data-net="' . esc_attr( $x['n'] ) . '"><input type="checkbox" value="' . esc_attr( $x['n'] ) . '"' . ( $x['on'] && $x['posted'] === '' ? ' checked' : '' ) . ( ! $x['on'] || $x['posted'] !== '' ? ' disabled' : '' ) . '>' . esc_html( $x['name'] ) . '</label>'; };
		return '<dialog class="dbs" id="dbd-share" aria-labelledby="dbs-h"><div class="dbs-in">'
			. '<div class="dbs-top"><h2 id="dbs-h">Share ' . esc_html( $s['name'] ) . '</h2><button type="button" class="dbs-x" aria-label="Close">×</button></div>'
			. '<figure class="dbs-fig"><img alt="" width="360" height="450" decoding="async"></figure>'
			. '<div class="dbs-side">'
			. '<p class="dbs-lab dbs-first" id="dbs-sl">Stat</p><div class="dbs-stats" role="group" aria-labelledby="dbs-sl"></div>'
			. '<p class="dbs-lab"><label for="dbs-t">Text</label></p><textarea id="dbs-t" rows="4" readonly></textarea>'
			. '<div class="dbs-btns"><button type="button" class="dbs-b pri dbs-share" hidden>Share…</button><a class="dbs-b dbs-dl" download>Download graphic</a><button type="button" class="dbs-b dbs-copy">Copy text</button></div>'
			. '<p class="dbs-msg" role="status" aria-live="polite"></p>'
			. '<div class="dbs-post">'
			. '<p class="dbs-lab" id="dbs-pl">Post as Down by Down</p><div class="dbs-nets" role="group" aria-labelledby="dbs-pl">' . implode( '', array_map( $box, $d['nets'] ) ) . '</div>'
			. '<div class="dbs-btns"><button type="button" class="dbs-b pri dbs-now">Post now</button></div><ul class="dbs-st" aria-live="polite"></ul>'
			. '<p class="dbs-lab">Post from your own account</p>'
			. '<div class="dbs-btns dbs-own"><button type="button" class="dbs-b" data-net="x">Post on X</button><button type="button" class="dbs-b" data-net="bsky">Post on Bluesky</button><button type="button" class="dbs-b" data-net="th">Post on Threads</button><button type="button" class="dbs-b" data-net="fb">Share on Facebook</button></div>'
			. '<p class="dbs-hint">Each opens a new post in whatever account you’re logged into on that site, with the text filled in, and copies the graphic so you can paste it in. Facebook copies the text instead.</p>'
			. '</div><p class="dbs-note">Only site admins see this.</p></div></div>'
			. '<script type="application/json" id="dbs-data">' . wp_json_encode( $d, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES ) . '</script></dialog>';
	}

	/* ------------------------------------------------------------------ Post now */

	/** One post per network per player per week: the record's key on each network. */
	public static function job_key( $id ) { return 'share-' . (int) DBD_Data::season() . '-w' . (int) DBD_Data::through_week() . '-' . $id; }

	/** A network's state for this player-week: array( connected, the post's link or '' ). */
	public static function net_state( $n, $jk ) {
		switch ( $n ) {
			case 'ig':   $on = DBD_Instagram::can_post(); $j = DBD_Instagram::job( $jk ); break;
			case 'bsky': $on = DBD_Bluesky::connected(); $j = DBD_Bluesky::job( $jk ); break;
			case 'x':    $on = DBD_X::connected(); $j = DBD_X::job( $jk ); break;
			case 'th':   $on = DBD_Threads::connected(); $j = DBD_Threads::job( $jk ); break;
			default:     return array( false, '' );
		}
		if ( ( $j['state'] ?? '' ) !== 'sent' ) { return array( (bool) $on, '' ); }
		$l = (string) ( $j['link'] ?? '' ); return array( (bool) $on, $l !== '' ? $l : '#' );  /* posted, even where the network gave no link back */
	}

	/** The Instagram caption: the text, the bio line, then #NFL and the team. */
	public static function caption( $s, $text ) { return $text . "\n\nLink in bio (downbydown.com)\n\n#NFL " . DBD_Pack::tag( $s['team'] ); }

	/** Post one player's stat to one of Down by Down's networks: array( link, error ). */
	public static function post( $id, $k, $net ) {
		$s = self::subject( $id ); $st = $s ? self::stat( $s, $k ) : null; if ( ! $st ) { return array( '', 'that stat isn’t one of his ranked stats' ); }
		if ( ! isset( self::NETS[ $net ] ) ) { return array( '', 'not a network' ); }
		$key = self::key( $id, $k ); list( $file ) = DBD_Card::ensure( $key, true ); if ( $file === '' ) { return array( '', 'the graphic couldn’t be drawn' ); }
		$text = self::text( $s, $st ); $link = self::link( $s ); $jk = self::job_key( $id ); $img = self::img_url( $id, $k );
		switch ( $net ) {
			case 'x':    return DBD_X::share( $jk, $text . ' ' . $link, $file );
			case 'bsky': return DBD_Bluesky::share( $jk, $text, $link, $file, $text );
			case 'ig':   return DBD_Instagram::share( $jk, self::caption( $s, $text ), $img );
			case 'th':   return DBD_Threads::share( $jk, $text . ' ' . $link, $img );
		}
		return array( '', 'not a network' );
	}

	/** The panel's Post now, one network a request. */
	public static function post_action() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) || ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'err' => 'Not allowed: reload the page and try again.' ), 403 ); }
		$id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) ); $k = sanitize_key( wp_unslash( $_POST['k'] ?? '' ) ); $net = sanitize_key( wp_unslash( $_POST['net'] ?? '' ) );
		list( $link, $err ) = self::post( $id, $k, $net );
		if ( $err !== '' ) { wp_send_json_error( array( 'err' => $err ) ); }
		wp_send_json_success( array( 'link' => $link ) );
	}
}
