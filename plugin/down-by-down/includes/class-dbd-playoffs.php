<?php
/**
 * Playoffs and the Super Bowl (2.46.0, SCOPE §88). One switch on the Playoffs tab turns all of it on and off:
 *   - the home page's playoff hero, drawn in place of the normal hero (.dxd-hero) with no change to the Divi page;
 *   - /playoffs/, the bracket on the field, this round's games and playoff leaders;
 *   - /playoffs/{game}/, a preview of every game, and /playoffs/super-bowl/.
 * The switch ships off. While it's off, a logged-in admin can still see everything (the home page with ?dbd_playoffs_preview=1).
 * Seeds come from playoffs.json (tools/build_playoffs.py). Games come from the schedule, so reseeded rounds appear as they're set.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Playoffs {
	const OPT     = 'dbd_playoffs_on';
	const PREVIEW = 'dbd_playoffs_preview';
	const ROUNDS  = array( 'WC' => 'Wild Card', 'DIV' => 'Divisional', 'CON' => 'Conference', 'SB' => 'Super Bowl' );
	const IDX     = array( 'WC' => 1, 'DIV' => 2, 'CON' => 3, 'SB' => 4 );
	const HX      = array( 12, 19.6, 27.2, 33 );  /* how far each round stands from its end zone, as a percent of the field's width */

	/** The harness's replay: a seeds table, and the round to stop at (0 today, 1 to 4 that round still to play, 5 all played). */
	public static $seeds = null; public static $replay = null;
	private static $memo = array(); private static $swapped = false;

	public static function init() {
		add_filter( 'the_content', array( __CLASS__, 'swap_hero' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'front_assets' ) );
		add_action( 'admin_post_dbd_playoffs_switch', array( __CLASS__, 'switch_action' ) );
	}
	public static function reset() { self::$memo = array(); }

	/* ------------------------------------------------------------------ the switch */

	public static function on() { return (bool) get_option( self::OPT, false ); }
	private static function admin() { return function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ); }
	/** Both conferences have seeds 1 to 7 for the season showing. */
	public static function ready() { return count( self::seeds() ) === 14; }
	/** Can this visitor open /playoffs/? On for everyone; off, only an admin. */
	public static function visible() { return ! DBD_Data::archived() && self::ready() && ( self::on() || self::admin() ); }
	private static function show_hero() {
		if ( is_admin() || ! is_front_page() || ! DBD_Data::has_data() || ! self::ready() ) { return false; }
		return self::on() || ( isset( $_GET[ self::PREVIEW ] ) && self::admin() );
	}

	public static function switch_action() {
		check_admin_referer( 'dbd_playoffs_switch' ); if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No.' ); }
		$on = ! empty( $_POST['on'] ); update_option( self::OPT, $on ? 1 : 0, false );
		if ( class_exists( 'WpeCommon' ) ) {  /* WP Engine's page cache, so the home page changes on the next visit */
			foreach ( array( 'purge_memcached', 'purge_varnish_cache' ) as $m ) { if ( method_exists( 'WpeCommon', $m ) ) { call_user_func( array( 'WpeCommon', $m ) ); } }
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'down-by-down-playoffs', 'po' => $on ? 'on' : 'off' ), admin_url( 'admin.php' ) ) ); exit;
	}

	/** Where things stand, in a sentence, for the Playoffs tab. */
	public static function status() {
		if ( ! self::ready() ) { return 'Waiting for this season’s playoff seeds from the next data update.'; }
		$st = self::stage();
		if ( $st === 5 ) { return 'The ' . self::nick( self::champion() ) . ' won the Super Bowl.'; }
		if ( $st === 0 ) { return 'Showing the bracket as if the season ended today.'; }
		return 'Showing the ' . self::ROUNDS[ array_keys( self::IDX )[ $st - 1 ] ] . ' round.';
	}

	/* ------------------------------------------------------------------ the data */

	public static function seeds() {
		if ( self::$seeds !== null ) { return self::$seeds; }
		$f = DBD_Data::get( 'playoffs.json' );
		return is_array( $f ) && (int) ( $f['season'] ?? 0 ) === (int) DBD_Data::season() ? (array) ( $f['seeds'] ?? array() ) : array();
	}
	private static function seed( $t ) { return (int) ( self::seeds()[ $t ]['seed'] ?? 0 ); }
	private static function rec( $t ) { return (string) ( self::seeds()[ $t ]['rec'] ?? '' ); }
	private static function conf_of( $t ) { return (string) ( self::seeds()[ $t ]['conf'] ?? DBD_Data::team( $t )['conf'] ?? '' ); }
	private static function nick( $t ) { return (string) ( DBD_Data::team( (string) $t )['nick'] ?? $t ); }
	private static function poss( $t ) { return self::nick( $t ) . '’'; }  /* every NFL nickname is plural */
	private static function at_seed( $c, $n ) { foreach ( self::seeds() as $t => $x ) { if ( $x['conf'] === $c && (int) $x['seed'] === $n ) { return (string) $t; } } return ''; }
	private static function color( $t, $k ) { $c = (string) ( DBD_Data::team( $t )[ $k ] ?? '' ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $c ) ? $c : '#3b4255'; }

	/** Every playoff game in the schedule, earliest first, each with its round and conference. */
	public static function games() {
		$k = (int) DBD_Data::season() . '|' . ( self::$replay === null ? 'x' : (int) self::$replay );
		if ( isset( self::$memo[ $k ] ) ) { return self::$memo[ $k ]; }
		$o = array();
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) {
			foreach ( (array) $gl as $g ) {
				$r = (string) ( $g['game_type'] ?? '' ); if ( ! isset( self::IDX[ $r ] ) || empty( $g['home'] ) || empty( $g['away'] ) ) { continue; }
				if ( self::$replay !== null ) { if ( self::IDX[ $r ] > self::$replay ) { continue; } if ( self::IDX[ $r ] === self::$replay ) { $g['home_score'] = null; $g['away_score'] = null; } }
				$g['r'] = $r; $g['conf'] = $r === 'SB' ? '' : self::conf_of( $g['home'] ); $o[] = $g;
			}
		}
		usort( $o, function ( $a, $b ) { return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] ); } );
		return self::$memo[ $k ] = $o;
	}
	private static function find( $id ) { foreach ( self::games() as $g ) { if ( $g['id'] === $id ) { return $g; } } return null; }
	private static function final_( $g ) { return DBD_Router::is_final( $g ); }
	private static function winner( $g ) { return self::final_( $g ) ? ( $g['home_score'] > $g['away_score'] ? $g['home'] : $g['away'] ) : ''; }
	private static function has( $g, $t ) { return $g['home'] === $t || $g['away'] === $t; }
	private static function lost( $t ) { foreach ( self::games() as $g ) { if ( self::final_( $g ) && self::has( $g, $t ) && self::winner( $g ) !== $t ) { return true; } } return false; }
	/** A round's games, the higher seed's first. */
	private static function round( $r, $c = '' ) {
		$o = array_values( array_filter( self::games(), function ( $g ) use ( $r, $c ) { return $g['r'] === $r && ( $c === '' || $g['conf'] === $c ); } ) );
		usort( $o, function ( $a, $b ) { return self::seed( $a['home'] ) <=> self::seed( $b['home'] ); } );
		return $o;
	}
	public static function super_bowl() { return self::round( 'SB' )[0] ?? null; }
	public static function champion() { $s = self::super_bowl(); return $s ? self::winner( $s ) : ''; }
	/** 0 before the Wild Card round is set, 1 to 4 the newest round in the schedule, 5 once the Super Bowl is final. */
	public static function stage() {
		$top = 0; foreach ( self::games() as $g ) { if ( $g['r'] === 'SB' && self::final_( $g ) ) { return 5; } $top = max( $top, self::IDX[ $g['r'] ] ); }
		return $top;
	}
	private static function wc_pairs( $c ) {
		if ( self::stage() ) { return self::round( 'WC', $c ); }
		$o = array(); foreach ( array( array( 2, 7 ), array( 3, 6 ), array( 4, 5 ) ) as $p ) { $o[] = array( 'proj' => true, 'r' => 'WC', 'conf' => $c, 'home' => self::at_seed( $c, $p[0] ), 'away' => self::at_seed( $c, $p[1] ) ); }
		return $o;
	}
	/** A conference's teams by bracket column: the Wild Card field, Divisional, Conference, the Super Bowl team. */
	private static function cols( $c ) {
		$cols = array( array( self::at_seed( $c, 1 ) ), array(), array(), array() );
		foreach ( self::wc_pairs( $c ) as $g ) { $cols[0][] = $g['home']; $cols[0][] = $g['away']; }
		foreach ( self::round( 'DIV', $c ) as $g ) { $cols[1][] = $g['home']; $cols[1][] = $g['away']; }
		foreach ( self::round( 'CON', $c ) as $g ) { $cols[2][] = $g['home']; $cols[2][] = $g['away']; }
		$s = self::super_bowl(); if ( $s ) { foreach ( array( $s['home'], $s['away'] ) as $t ) { if ( self::conf_of( $t ) === $c ) { $cols[3][] = $t; } } }
		return $cols;
	}
	private static function roman( $n ) { $o = ''; foreach ( array( 50 => 'L', 40 => 'XL', 10 => 'X', 9 => 'IX', 5 => 'V', 4 => 'IV', 1 => 'I' ) as $v => $s ) { while ( $n >= $v ) { $o .= $s; $n -= $v; } } return $o; }
	private static function sb_name() { return 'Super Bowl ' . self::roman( (int) DBD_Data::season() - 1965 ); }
	private static function clock( $g ) {
		$d = DateTime::createFromFormat( 'Y-m-d H:i', (string) ( $g['date'] ?? '' ) . ' ' . (string) ( $g['time'] ?? '' ) );
		return $d ? $d->format( 'D M j, g:i A' ) . ' ET' : (string) ( $g['date'] ?? '' );
	}
	private static function link( $g = null ) { return DBD_Router::url( $g ? array( 'view' => 'playoffs', 'pg' => $g['id'] ) : array( 'view' => 'playoffs' ) ); }
	/** super-bowl, or wild-card-rams-at-panthers. */
	public static function slug( $g ) {
		if ( $g['r'] === 'SB' ) { return 'super-bowl'; }
		return DBD_Urls::slugify( self::ROUNDS[ $g['r'] ] . ' ' . self::nick( $g['away'] ) . ' at ' . self::nick( $g['home'] ) );
	}
	public static function id_of( $slug ) { foreach ( self::games() as $g ) { if ( self::slug( $g ) === $slug ) { return $g['id']; } } return ''; }
	public static function game_slug( $id ) { $g = self::find( $id ); return $g ? self::slug( $g ) : ''; }
	/** The round's next game to kick off, or null when they're all final. */
	private static function next_game( $r ) { $o = array_values( array_filter( self::games(), function ( $g ) use ( $r ) { return $g['r'] === $r && ! self::final_( $g ); } ) ); return $o[0] ?? null; }

	/* ------------------------------------------------------------------ the hero */

	public static function front_assets() { if ( self::show_hero() ) { self::assets(); } }
	public static function assets() {
		DBD_Blocks::enqueue();
		wp_enqueue_style( 'dbd-playoffs', DBD_URL . 'assets/playoffs.css', array( 'dbd-blocks' ), DBD_VERSION );
		wp_enqueue_script( 'dbd-playoffs', DBD_URL . 'assets/playoffs.js', array(), DBD_VERSION, true );
	}

	/** On the home page: the playoff hero goes in where the normal one starts, and the normal one stays in the page, hidden. */
	public static function swap_hero( $c ) {
		if ( self::$swapped || strpos( (string) $c, '<div class="dxd-hero"' ) === false || ! self::show_hero() ) { return $c; }
		$h = self::hero_html(); if ( $h === '' ) { return $c; }
		self::$swapped = true;
		return (string) preg_replace_callback( '/<div class="dxd-hero"/', function () use ( $h ) { return $h . '<div hidden style="display:none" class="dxd-hero"'; }, (string) $c, 1 );
	}
	private static function hero_html() {
		$key = 'dbd_po_h_' . md5( wp_json_encode( array( DBD_VERSION, DBD_Data::manifest()['built_at'] ?? '', DBD_Data::logos(), self::link() ) ) );
		$h = get_transient( $key ); if ( is_string( $h ) && $h !== '' ) { return $h; }
		$h = self::hero(); set_transient( $key, $h, 6 * HOUR_IN_SECONDS ); return $h;
	}

	/** A team on the field: where it stands wide (x, y) and on a phone (vx, vy), its size in each, and whether it's out. */
	private static function pieces() {
		$ch = self::champion(); $ps = array(); $rs = array();
		foreach ( array( 'AFC', 'NFC' ) as $c ) {
			$cols = self::cols( $c ); $L = $c === 'AFC'; $far = array();
			foreach ( $cols as $k => $l ) { foreach ( $l as $i => $t ) { $far[ $t ] = array( $k, $i, count( $l ) ); } }
			$spot = function ( $k, $i, $n ) use ( $L ) { return array( $L ? self::HX[ $k ] : 100 - self::HX[ $k ], 12 + 76 * ( $i + .5 ) / $n ); };
			$live = array(); $gone = array();
			$by = array_keys( $far ); usort( $by, function ( $a, $b ) { return self::seed( $a ) <=> self::seed( $b ); } );
			foreach ( $by as $t ) { if ( self::lost( $t ) ) { $gone[] = $t; } elseif ( $t !== $ch ) { $live[] = $t; } }
			$n = count( $live ); $vsize = $n >= 5 ? 'min(40px,10.5vw)' : ( $n >= 3 ? '56px' : ( $n === 2 ? '72px' : '88px' ) );
			foreach ( array( array( $gone, 10.6, 89.4, '25px', false ), array( $live, 19.5, 79, $vsize, $n <= 4 ) ) as $row ) {
				foreach ( $row[0] as $i => $t ) {
					list( $k, $ci, $cn ) = $far[ $t ]; $out = self::lost( $t ); list( $x, $y ) = $spot( $k, $ci, $cn );
					$size = $out ? ( $k === 3 ? 'clamp(70px,6.6vw,96px)' : 'clamp(34px,3vw,44px)' ) : array( 'clamp(42px,3.8vw,56px)', 'clamp(50px,4.6vw,66px)', 'clamp(60px,5.7vw,84px)', 'clamp(92px,9vw,132px)' )[ $k ];
					$ps[] = array( 't' => $t, 'x' => $x, 'y' => $y, 'size' => $size, 'out' => $out, 'big' => $k === 3, 'vx' => 6 + 88 * ( $i + .5 ) / count( $row[0] ), 'vy' => $L ? $row[1] : $row[2], 'vsize' => $row[3], 'vbig' => ! $out && $n <= 2, 'vlab' => $row[4], 'sx' => $L ? 3.5 : 96.5, 'vsy' => $L ? 3.5 : 96.5 );
				}
			}
			foreach ( $cols as $k => $l ) { if ( ! $k ) { continue; } foreach ( $l as $i => $t ) { $p = array_search( $t, $cols[ $k - 1 ], true ); if ( $p !== false ) { $rs[] = array( $L, $t, $spot( $k - 1, $p, count( $cols[ $k - 1 ] ) ), $spot( $k, $i, count( $l ) ) ); } } }
			if ( $ch !== '' && in_array( $ch, $cols[3], true ) ) { $rs[] = array( $L, $ch, $spot( 3, 0, 1 ), array( 50, 37 ) ); }
		}
		return array( $ps, $rs );
	}
	private static function chip( $t ) {
		$lg = DBD_Data::logo( $t );
		return '<b class="po-ab">' . esc_html( $t ) . '</b>' . ( $lg ? '<img src="' . esc_url( DBD_Marks::logo_src( $lg, 96 ) ) . '" alt="" width="96" height="96" onerror="this.remove()">' : '' );
	}
	private static function route_svg( $rs, $left ) {
		$o = '';
		foreach ( $rs as $r ) {
			if ( $r[0] !== $left ) { continue; } list( $ax, $ay ) = $r[2]; list( $bx, $by ) = $r[3]; $dx = ( $bx - $ax ) * .55;
			$o .= '<path data-t="' . esc_attr( $r[1] ) . '" d="M' . round( $ax, 2 ) . ' ' . round( $ay, 2 ) . 'C' . round( $ax + $dx, 2 ) . ' ' . round( $ay, 2 ) . ' ' . round( $bx - $dx, 2 ) . ' ' . round( $by, 2 ) . ' ' . round( $bx, 2 ) . ' ' . round( $by, 2 ) . '"/>';
		}
		return '<svg class="po-rt ' . ( $left ? 'l' : 'r' ) . '" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">' . $o . '</svg>';
	}
	private static function hero_copy( $st ) {
		$sb = self::super_bowl(); $ch = self::champion(); $bracket = self::link();
		if ( $ch !== '' ) {
			$lose = $sb['home'] === $ch ? $sb['away'] : $sb['home'];
			return array( '<div class="po-cl" style="--c2:' . esc_attr( self::color( $ch, 'c2' ) ) . '">' . self::chip( $ch ) . '</div>', self::nick( $ch ) . ' win ' . self::sb_name(),
				'<b>' . max( (int) $sb['home_score'], (int) $sb['away_score'] ) . '-' . min( (int) $sb['home_score'], (int) $sb['away_score'] ) . '</b> over the ' . esc_html( self::nick( $lose ) ) . ' at ' . esc_html( (string) ( $sb['stadium'] ?? '' ) ) . '.', array( 'The Super Bowl', self::link( $sb ) ) );
		}
		$n = array( 14, 14, 8, 4, 2 )[ $st ]; $big = '<div class="po-n"><b>' . $n . '</b><span>teams<br>' . ( $st ? 'left' : 'in' ) . '</span></div>';
		if ( ! $st ) { return array( $big, 'If the season ended today', 'Seeds update after every game until Week 18 sets the real bracket.', null ); }
		if ( $st === 4 ) { return array( $big, self::sb_name(), '<b>' . esc_html( self::nick( $sb['away'] ) . ' at ' . self::nick( $sb['home'] ) ) . '.</b><br><span class="nb">' . esc_html( self::clock( $sb ) ) . '</span><br><span class="nb">' . esc_html( (string) ( $sb['stadium'] ?? '' ) ) . '</span>', array( 'The matchup', self::link( $sb ) ) ); }
		$r = array_keys( self::IDX )[ $st - 1 ]; $nx = self::next_game( $r ); $first = self::round( $r )[0] ?? null;
		$h = array( 1 => 'Wild Card weekend', 2 => 'The Divisional round', 3 => 'Conference championships' )[ $st ];
		$p = $nx ? '<b>Next: ' . esc_html( self::nick( $nx['away'] ) . ' at ' . self::nick( $nx['home'] ) ) . '.</b><br><span class="nb">' . esc_html( self::clock( $nx ) ) . '</span>' : 'Every game this round is final.';
		return array( $big, $h, $p, array( 'Game previews', self::link( $nx ?: $first ) ) );
	}
	private static function tiles( $hd, $sub, $games ) {
		$o = '';
		foreach ( $games as $g ) {
			$f = self::final_( $g ); $w = self::winner( $g );
			$row = function ( $t, $s ) use ( $f, $w ) { return '<span class="r' . ( $f && $w === $t ? ' w' : '' ) . '">' . DBD_Marks::tile( $t, 18 ) . '<b>' . esc_html( $t ) . '</b><em>' . ( $f ? (int) $s : '' ) . '</em></span>'; };
			$o .= '<a class="hb-gm' . ( $f ? '' : ' up' ) . '" href="' . esc_url( self::link( $g ) ) . '">' . $row( $g['away'], $g['away_score'] ?? null ) . $row( $g['home'], $g['home_score'] ?? null ) . '<span class="st">' . esc_html( $f ? 'Final · ' . self::ROUNDS[ $g['r'] ] : self::clock( $g ) ) . '</span></a>';
		}
		return '<div class="dbd-hb hb-games"><div class="hb-games-h"><b>' . esc_html( $hd ) . '</b><span>' . esc_html( $sub ) . '</span><a href="' . esc_url( self::link() ) . '">The full bracket</a></div><div class="hb-games-g">' . $o . '</div></div>';
	}
	private static function strip( $st ) {
		if ( $st === 5 ) { $ch = self::champion(); $g = array_values( array_filter( self::games(), function ( $x ) use ( $ch ) { return self::has( $x, $ch ); } ) ); return self::tiles( 'The ' . self::poss( $ch ) . ' road', count( $g ) . ' straight wins', $g ); }
		if ( $st === 4 ) { return self::tiles( 'How they got here', 'The conference championships', self::round( 'CON' ) ); }
		return DBD_Blocks::render( 'games' );
	}
	public static function hero() {
		if ( ! self::ready() ) { return ''; }
		$st = self::stage(); $ch = self::champion(); $sb = $st >= 4 ? self::super_bowl() : null; list( $ps, $rs ) = self::pieces();
		$A = ''; $N = ''; if ( $sb ) { foreach ( array( $sb['home'], $sb['away'] ) as $t ) { if ( self::conf_of( $t ) === 'AFC' ) { $A = $t; } else { $N = $t; } } }
		$ca = ''; $cn = '';
		if ( $A !== '' && $N !== '' ) {
			$ca = self::color( $A, 'c1' ); $cn = self::color( $N, 'c1' ); list( $r1, $g1, $b1 ) = DBD_Tall::rgb( $ca ); list( $r2, $g2, $b2 ) = DBD_Tall::rgb( $cn );
			if ( sqrt( ( $r1 - $r2 ) ** 2 + ( $g1 - $g2 ) ** 2 + ( $b1 - $b2 ) ** 2 ) < 70 ) { $ca = self::color( $A, 'c2' ); }  /* two navy teams: the AFC side takes its second color */
		}
		$wash = $ch !== '' ? 'background:color-mix(in srgb,' . self::color( $ch, 'c1' ) . ' 84%,#000)' : ( $sb ? 'background:linear-gradient(var(--po-wd),' . $ca . ',#101a14 40%,#101a14 60%,' . $cn . ')' : '' );
		$ez = function ( $side, $t, $col, $word ) use ( $ch ) {
			if ( $ch !== '' ) { $col = self::color( $ch, 'c2' ); $word = self::nick( $ch ); } elseif ( $t !== '' ) { $word = self::nick( $t ); }
			$st = $col !== '' ? ' style="--ezc:' . esc_attr( $col ) . ';--ezi:' . esc_attr( DBD_Tall::on( $col ) ) . '"' : '';
			return '<div class="po-ez ' . $side . '"' . $st . ' aria-hidden="true">' . esc_html( $word ) . '</div>';
		};
		$yd = ''; foreach ( array( 10 => 10, 20 => 20, 30 => 30, 40 => 40, 60 => 40, 70 => 30, 80 => 20, 90 => 10 ) as $at => $n ) { $yd .= '<span style="left:' . $at . '%">' . $n . '</span>'; }
		$pcs = '';
		foreach ( $ps as $i => $p ) {
			$g = null; foreach ( self::games() as $x ) { if ( self::has( $x, $p['t'] ) && ( ! $g || self::IDX[ $x['r'] ] >= self::IDX[ $g['r'] ] ) ) { $g = $x; } }
			$tag = $g ? 'a' : 'span'; $href = $g ? ' href="' . esc_url( self::link( $g ) ) . '"' : '';
			$cl = 'po-pc' . ( $p['out'] ? ' out' : '' ) . ( $p['big'] ? ' big' : '' ) . ( $p['vbig'] ? ' vbig' : '' ) . ( $p['vlab'] ? '' : ' vnolb' );
			$sty = sprintf( '--c:%s;--x:%.2f%%;--y:%.2f%%;--vx:%.2f%%;--vy:%.2f%%;--sx:%.1f%%;--vsy:%.1f%%;--s:%s;--vs:%s;--i:%d', self::color( $p['t'], 'c1' ), $p['x'], $p['y'], $p['vx'], $p['vy'], $p['sx'], $p['vsy'], $p['size'], $p['vsize'], $i );
			$pcs .= '<' . $tag . ' class="' . $cl . '" data-t="' . esc_attr( $p['t'] ) . '"' . $href . ' style="' . esc_attr( $sty ) . '" aria-label="' . esc_attr( self::nick( $p['t'] ) . ', ' . self::seed( $p['t'] ) . ' seed' . ( $p['out'] ? ', out' : '' ) ) . '">' . self::chip( $p['t'] ) . '<span class="sd">' . self::seed( $p['t'] ) . '</span><span class="lb">' . esc_html( self::nick( $p['t'] ) ) . '</span></' . $tag . '>';
		}
		list( $big, $h, $para, $act ) = self::hero_copy( $st );
		$btn = $act ? '<a class="po-btn" href="' . esc_url( $act[1] ) . '">' . esc_html( $act[0] ) . '</a><a class="po-btn ln" href="' . esc_url( self::link() ) . '">The full bracket</a>' : '<a class="po-btn" href="' . esc_url( self::link() ) . '">The full bracket</a><a class="po-btn ln" href="#po-strip">See this week</a>';
		$cf = '';
		if ( $ch !== '' ) {
			$cols = array( self::color( $ch, 'c2' ), '#ffffff', '#ffd84d', self::color( $ch, 'c2' ), '#ffffff' ); $x = 7;
			for ( $i = 0; $i < 120; $i++ ) {
				$x = ( $x * 37 + 11 ) % 1009;
				$cf .= sprintf( '<i style="left:%.1f%%;--y:%d%%;--w:%dpx;--h:%dpx;--p:%s;--r:%ddeg;--d:%.2fs;--l:%.2fs"></i>', $x / 10.09, 6 + ( $x * 13 ) % 89, 5 + $x % 6, 9 + $x % 9, $cols[ $i % 5 ], ( $x * 7 ) % 180, 1.5 + ( $x % 12 ) / 10, ( $i % 24 ) / 30 );
			}
			$cf = '<div class="po-cf" aria-hidden="true">' . $cf . '</div>';
		}
		return '<section class="dbd-po po-h" aria-label="The playoffs"><div class="po-f' . ( $sb ? ' lit' : '' ) . '" data-pofield><div class="po-grass"></div>' . ( $wash !== '' ? '<div class="po-wash" style="' . esc_attr( $wash ) . '"></div>' : '' ) . '<div class="po-light"></div><div class="po-lines"></div><div class="po-yd" aria-hidden="true">' . $yd . '</div>'
			. $ez( 'l', $A, $ca, 'AFC' ) . $ez( 'r', $N, $cn, 'NFC' ) . self::route_svg( $rs, true ) . self::route_svg( $rs, false ) . $pcs
			. '<div class="po-c">' . $big . '<h1>' . esc_html( $h ) . '</h1><p>' . $para . '</p><div class="po-b">' . $btn . '</div></div>' . $cf . '</div><div class="po-strip" id="po-strip">' . self::strip( $st ) . '</div></section>';
	}

	/* ------------------------------------------------------------------ the bracket */

	private static function row( $t, $g = null, $extra = '' ) {
		if ( $t === '' || $t === null ) { return '<div class="rw tbd"><span class="sd"></span><i></i><span class="nm">To be set</span><span></span></div>'; }
		$real = $g && empty( $g['proj'] ); $f = $real && self::final_( $g ); $won = $f && self::winner( $g ) === $t; $sc = $f ? (int) ( $t === $g['home'] ? $g['home_score'] : $g['away_score'] ) : $extra;
		return '<div class="rw' . ( $won ? ' w' : ( $f ? ' l' : '' ) ) . '" data-t="' . esc_attr( $t ) . '"' . ( $real ? ' data-r="' . esc_attr( $g['r'] ) . '"' : '' ) . '><span class="sd">' . self::seed( $t ) . '</span>' . DBD_Marks::tile( $t, 20 ) . '<span class="nm">' . esc_html( self::nick( $t ) ) . '</span><span class="sc">' . esc_html( (string) $sc ) . '</span></div>';
	}
	private static function card( $g ) {
		if ( ! empty( $g['proj'] ) ) { return '<div class="g"><span class="wh">If it ended today</span>' . self::row( $g['home'], $g ) . self::row( $g['away'], $g ) . '</div>'; }
		$f = self::final_( $g ); $lab = $f ? 'Final' : self::clock( $g );
		return '<a class="g" href="' . esc_url( self::link( $g ) ) . '"><span class="wh">' . esc_html( $lab ) . '</span>' . self::row( $g['home'], $g ) . self::row( $g['away'], $g ) . '</a>';
	}
	private static function blank() { return '<div class="g"><span class="wh">&nbsp;</span>' . self::row( '' ) . self::row( '' ) . '</div>'; }
	private static function bye( $c ) { $t = self::at_seed( $c, 1 ); return '<div class="g bye"><span class="wh">First-round bye</span><div class="rw" data-t="' . esc_attr( $t ) . '" data-bye="1"><span class="sd">1</span>' . DBD_Marks::tile( $t, 20 ) . '<span class="nm">' . esc_html( self::nick( $t ) ) . '</span><span class="sc">BYE</span></div></div>'; }
	private static function column( $r, $c ) {
		if ( $r === 'WC' ) { $h = self::bye( $c ); foreach ( self::wc_pairs( $c ) as $g ) { $h .= self::card( $g ); } }
		else { $x = self::round( $r, $c ); $h = $x ? implode( '', array_map( array( __CLASS__, 'card' ), $x ) ) : ( $r === 'DIV' ? self::blank() . self::blank() : self::blank() ); }
		return '<div class="col"><div class="colh">' . esc_html( self::ROUNDS[ $r ] ) . '</div><div class="stack">' . $h . '</div></div>';
	}
	private static function sb_card() {
		$g = self::super_bowl(); $f = $g && self::final_( $g ); $tag = $g ? 'a' : 'div'; $href = $g ? ' href="' . esc_url( self::link( $g ) ) . '"' : '';
		$sub = $g ? esc_html( self::clock( $g ) ) . '<br>' . esc_html( (string) ( $g['stadium'] ?? '' ) ) : esc_html( self::sb_name() );
		$rows = $g ? self::row( $g['away'], $g ) . self::row( $g['home'], $g ) : self::row( '' ) . self::row( '' );
		return '<div class="col"><div class="colh"></div><div class="stack"><' . $tag . ' class="sbbox"' . $href . '><span class="tt">Super Bowl</span><span class="wh">' . $sub . '</span>' . $rows . ( $f ? '<span class="champ">' . esc_html( self::nick( self::winner( $g ) ) ) . ' win it</span>' : '' ) . '</' . $tag . '></div></div>';
	}
	private static function list_view() {
		$o = '<h3>Wild Card</h3>';
		foreach ( array( 'AFC', 'NFC' ) as $c ) { $o .= '<div class="conf">' . $c . '</div><div class="gs">' . self::bye( $c ); foreach ( self::wc_pairs( $c ) as $g ) { $o .= self::card( $g ); } $o .= '</div>'; }
		foreach ( array( 'DIV', 'CON' ) as $r ) {
			$o .= '<h3>' . esc_html( self::ROUNDS[ $r ] ) . '</h3>';
			if ( ! self::round( $r ) ) { $o .= '<div class="gs">' . self::blank() . '</div>'; continue; }
			foreach ( array( 'AFC', 'NFC' ) as $c ) { $o .= '<div class="conf">' . $c . '</div><div class="gs">' . implode( '', array_map( array( __CLASS__, 'card' ), self::round( $r, $c ) ) ) . '</div>'; }
		}
		$s = self::super_bowl();
		return '<div class="po-list">' . $o . '<h3>Super Bowl</h3><div class="gs">' . ( $s ? self::card( $s ) : self::blank() ) . '</div></div>';
	}
	public static function bracket() {
		$yd = ''; foreach ( array( 10, 20, 30, 40, 50, 40, 30, 20, 10 ) as $n ) { $yd .= '<span>' . $n . '</span>'; }
		return '<div class="po-field" data-bracket data-champ="' . esc_attr( self::champion() ) . '"><div class="ez l">AFC</div>' . self::column( 'WC', 'AFC' ) . self::column( 'DIV', 'AFC' ) . self::column( 'CON', 'AFC' ) . self::sb_card() . self::column( 'CON', 'NFC' ) . self::column( 'DIV', 'NFC' ) . self::column( 'WC', 'NFC' )
			. '<div class="ez r">NFC</div><svg class="routes" aria-hidden="true"></svg><div class="yards" aria-hidden="true">' . $yd . '</div></div>' . self::list_view();
	}

	/* ------------------------------------------------------------------ the page */

	private static function sg( $v ) { return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 ); }
	private static function ord( $n ) { $n = (int) $n; return $n . ( $n % 100 >= 11 && $n % 100 <= 13 ? 'th' : ( array( 1 => 'st', 2 => 'nd', 3 => 'rd' )[ $n % 10 ] ?? 'th' ) ); }
	/** Where a team can win it: the bigger gap between its offense's rank and the other defense's, passing or running. */
	public static function edge( $t, $o ) {
		$M = DBD_Data::metrics(); $x = $M['off'][ $t ] ?? null; $d = $M['defense'][ $o ] ?? null; $rx = $M['rank']['off'][ $t ] ?? null; $rd = $M['rank']['def'][ $o ] ?? null;
		if ( ! $x || ! $d || ! $rx || ! $rd ) { return null; }
		$pass = ( (int) $rd['pass_epa'] - (int) $rx['pass_epa'] ) >= ( (int) $rd['run_epa'] - (int) $rx['run_epa'] ); $k = $pass ? 'pass_epa' : 'run_epa';
		return array( 'head' => $pass ? 'Throw it' : 'Run it at them', 'say' => sprintf( '%s gain %s EPA per %s (%s). %s allow %s (%s).', self::nick( $t ), self::sg( $x[ $k ] ), $pass ? 'dropback' : 'carry', self::ord( $rx[ $k ] ), self::nick( $o ), self::sg( $d[ $k ] ), self::ord( $rd[ $k ] ) ) );
	}
	private static function mline( $g ) {
		$lg = function ( $t ) { return DBD_Marks::tile( $t, 22 ); };
		if ( ! empty( $g['proj'] ) ) { return '<div class="mline"><span class="mt">' . $lg( $g['away'] ) . esc_html( self::nick( $g['away'] ) ) . ' <span class="at">at</span> ' . $lg( $g['home'] ) . esc_html( self::nick( $g['home'] ) ) . '</span><span class="mw">' . esc_html( self::rec( $g['away'] ) . ' at ' . self::rec( $g['home'] ) ) . '</span></div>'; }
		$f = self::final_( $g ); $e = self::edge( $g['away'], $g['home'] );
		return '<a class="mline" href="' . esc_url( self::link( $g ) ) . '"><span class="mt">' . $lg( $g['away'] ) . esc_html( self::nick( $g['away'] ) ) . ( $f ? ' ' . (int) $g['away_score'] : '' ) . ' <span class="at">at</span> ' . $lg( $g['home'] ) . esc_html( self::nick( $g['home'] ) ) . ( $f ? ' ' . (int) $g['home_score'] : '' ) . '</span><span class="mw">' . esc_html( $f ? 'Final' : self::clock( $g ) ) . '</span>'
			. ( $e ? '<span class="me"><b>' . esc_html( self::nick( $g['away'] ) ) . ':</b> ' . esc_html( strtolower( $e['head'] ) . '. ' . $e['say'] ) . '</span>' : '' ) . '</a>';
	}
	/** Playoff totals from the final games' box scores (SCOPE §88), in the rounds given (all of them by default): key => id => id, n, t, v, g (games). */
	public static function totals( $rounds = null ) {
		$T = array( 'py' => array(), 'ry' => array(), 'recy' => array(), 'td' => array(), 'sk' => array() );
		$fin = array_filter( self::games(), function ( $g ) use ( $rounds ) { return self::final_( $g ) && ( $rounds === null || in_array( $g['r'], (array) $rounds, true ) ); } );
		foreach ( $fin as $g ) {
			$b = DBD_Data::box( $g ); if ( ! $b ) { continue; } $K = (array) $b['keys'];
			foreach ( array( 'away', 'home' ) as $sd ) {
				$t = (string) ( $b[ $sd ]['team'] ?? '' );
				foreach ( (array) ( $b[ $sd ]['off'] ?? array() ) as $r ) {
					if ( count( $r ) !== count( $K ) ) { continue; } $x = array_combine( $K, $r ); $p = DBD_Data::player( (string) $x['id'] ); $nm = ! empty( $p['name'] ) ? (string) $p['name'] : (string) $x['name'];
					foreach ( array( 'py' => (int) $x['passing_yards'], 'ry' => (int) $x['rushing_yards'], 'recy' => (int) $x['receiving_yards'], 'td' => (int) $x['passing_tds'] + (int) $x['rushing_tds'] + (int) $x['receiving_tds'] ) as $k => $v ) {
						if ( ! $v ) { continue; } if ( ! isset( $T[ $k ][ $x['id'] ] ) ) { $T[ $k ][ $x['id'] ] = array( 'id' => (string) $x['id'], 'n' => $nm, 't' => $t, 'v' => 0, 'g' => 0 ); } $T[ $k ][ $x['id'] ]['v'] += $v; $T[ $k ][ $x['id'] ]['g']++;
					}
				}
				foreach ( (array) ( $b[ $sd ]['d'] ?? array() ) as $d ) { if ( empty( $d[1] ) ) { continue; } $id = $t . $d[0]; if ( ! isset( $T['sk'][ $id ] ) ) { $T['sk'][ $id ] = array( 'id' => '', 'n' => (string) $d[0], 't' => $t, 'v' => 0, 'g' => 0 ); } $T['sk'][ $id ]['v'] += (float) $d[1]; $T['sk'][ $id ]['g']++; }
			}
		}
		return $T;
	}
	/** The page's playoff leaders: passing, rushing and receiving yards, touchdowns, sacks. */
	private static function leaders() {
		$st = self::stage(); $h = '<section class="pn"><h2>Playoff leaders</h2>';
		$fin = array_filter( self::games(), array( __CLASS__, 'final_' ) );
		if ( ! $fin ) { return $h . '<p class="empty">These start after the first playoff game. Regular-season boards stay as they are.</p></section>'; }
		$T = self::totals();
		foreach ( array( 'py' => 'Passing yards', 'ry' => 'Rushing yards', 'recy' => 'Receiving yards', 'td' => 'Touchdowns', 'sk' => 'Sacks' ) as $k => $lab ) {
			$L = array_values( $T[ $k ] ); usort( $L, function ( $a, $b ) { return array( -$a['v'], $a['n'] ) <=> array( -$b['v'], $b['n'] ); } );
			$h .= '<div class="lead-b"><h3>' . esc_html( $lab ) . '</h3>';
			foreach ( array_slice( $L, 0, 3 ) as $i => $x ) { $h .= '<div class="lr"><span class="rk">' . ( $i + 1 ) . '</span>' . DBD_Marks::tile( $x['t'], 20 ) . '<span class="nm">' . esc_html( $x['n'] ) . '</span><b>' . esc_html( $k === 'sk' ? rtrim( rtrim( number_format( $x['v'], 1 ), '0' ), '.' ) : number_format( $x['v'] ) ) . '</b></div>'; }
			$h .= '</div>';
		}
		return $h . '</section>';
	}
	private static function lead() {
		$st = self::stage();
		if ( ! $st ) { return '<b>If the season ended today.</b> Seeds update after every game until Week 18 sets the real bracket.'; }
		if ( $st === 5 ) { $s = self::super_bowl(); $w = self::winner( $s ); $l = $w === $s['home'] ? $s['away'] : $s['home']; return '<b>' . esc_html( self::nick( $w ) . ' ' . max( (int) $s['home_score'], (int) $s['away_score'] ) . ', ' . self::nick( $l ) . ' ' . min( (int) $s['home_score'], (int) $s['away_score'] ) ) . '.</b> The season’s last game, and the whole road to it.'; }
		$nx = self::next_game( array_keys( self::IDX )[ $st - 1 ] );
		return $nx ? '<b>Next: ' . esc_html( self::clock( $nx ) ) . '.</b> ' . esc_html( self::nick( $nx['away'] ) . ' at ' . self::nick( $nx['home'] ) ) . '.' : 'Every game this round is final. The next round appears here as soon as it’s set.';
	}
	private static function off_note() { return self::on() ? '' : '<p class="po-off">The playoffs are switched off, so only you can see this page.</p>'; }
	public static function page() {
		$st = self::stage(); $r = $st ? array_keys( self::IDX )[ min( $st, 4 ) - 1 ] : '';
		$cur = $st ? self::round( $r ) : array_merge( self::wc_pairs( 'AFC' ), self::wc_pairs( 'NFC' ) );
		$head = ! $st ? 'If the season ended today' : ( $st >= 4 ? ( $st === 5 ? 'The Super Bowl' : self::sb_name() ) : 'The ' . self::ROUNDS[ $r ] . ' round' );
		return '<div class="dbd-po po-pg">' . self::off_note() . '<h1>' . (int) DBD_Data::season() . ' Playoffs</h1><p class="lead">' . self::lead() . '</p>' . self::bracket()
			. '<div class="two"><section class="pn"><h2>' . esc_html( $head ) . '</h2>' . implode( '', array_map( array( __CLASS__, 'mline' ), $cur ) ) . '</section>' . self::leaders() . '</div></div>';
	}

	/* ------------------------------------------------------------------ a game's preview */

	private static function stats_table( $a, $b ) {
		$M = DBD_Data::metrics(); $cell = function ( $side, $rk, $t, $k ) use ( $M ) { $v = $M[ $side ][ $t ][ $k ] ?? null; $r = $M['rank'][ $rk ][ $t ][ $k ] ?? null; return $v === null ? '' : self::sg( $v ) . ( $r ? ' (' . self::ord( $r ) . ')' : '' ); };
		$h = '<table><thead><tr><th>' . esc_html( self::nick( $a ) ) . ' offense</th><th class="n">Gains</th><th class="n">' . esc_html( self::nick( $b ) ) . ' allow</th></tr></thead><tbody>';
		foreach ( array( 'EPA a play' => 'epa', 'Passing' => 'pass_epa', 'Running' => 'run_epa' ) as $lab => $k ) { $h .= '<tr><td>' . $lab . '</td><td class="n">' . esc_html( $cell( 'off', 'off', $a, $k ) ) . '</td><td class="n">' . esc_html( $cell( 'defense', 'def', $b, $k ) ) . '</td></tr>'; }
		return $h . '</tbody></table>';
	}
	private static function road( $t, $g ) {
		$o = '<div class="rl"><span class="r">Season</span>' . DBD_Marks::tile( $t, 22 ) . '<b>' . esc_html( self::nick( $t ) ) . '</b><span>' . esc_html( self::rec( $t ) . ', ' . self::ord( self::seed( $t ) ) . ' seed' ) . '</span></div>';
		if ( self::seed( $t ) === 1 ) { $o .= '<div class="rl"><span class="r">Wild Card</span><span></span><span>First-round bye</span><span></span></div>'; }
		foreach ( self::games() as $x ) {
			if ( self::IDX[ $x['r'] ] >= self::IDX[ $g['r'] ] || ! self::has( $x, $t ) || ! self::final_( $x ) ) { continue; }
			$home = $x['home'] === $t; $opp = $home ? $x['away'] : $x['home']; $m = (int) ( $home ? $x['home_score'] : $x['away_score'] ); $v = (int) ( $home ? $x['away_score'] : $x['home_score'] );
			$o .= '<div class="rl"><span class="r">' . esc_html( self::ROUNDS[ $x['r'] ] ) . '</span>' . DBD_Marks::tile( $opp, 22 ) . '<span>' . ( $home ? 'vs ' : 'at ' ) . esc_html( self::nick( $opp ) ) . '</span><b>W ' . $m . '-' . $v . '</b></div>';
		}
		return '<section class="pn"><h3>The ' . esc_html( self::poss( $t ) ) . ' road</h3>' . $o . '</section>';
	}
	/** The series as it stood before this game: later meetings (and this one) come off the totals. */
	private static function series_panel( $g ) {
		$s = DBD_Data::series( $g['away'], $g['home'] ); if ( ! $s ) { return ''; }
		$w = (int) $s['w']; $l = (int) $s['l']; $t = (int) $s['t']; $pa = (int) $s['post'][0]; $pb = (int) $s['post'][1]; $prior = array(); $sn = (int) substr( $g['id'], 0, 4 ); $wk = (int) $g['week'];
		foreach ( (array) ( $s['last5'] ?? array() ) as $m ) {
			if ( array( (int) $m[1], (int) $m[2] ) < array( $sn, $wk ) ) { $prior[] = $m; continue; }
			$win = (int) $m[5] > (int) $m[7] ? $m[4] : ( (int) $m[7] > (int) $m[5] ? $m[6] : '' ); $post = $m[3] !== 'REG';
			if ( $win === $s['a'] ) { $w--; $pa -= $post ? 1 : 0; } elseif ( $win === $s['b'] ) { $l--; $pb -= $post ? 1 : 0; } else { $t--; }
		}
		$A = self::nick( $s['a'] ); $B = self::nick( $s['b'] ); $tie = $t ? '-' . $t : '';
		$ld = $w === $l ? 'Tied ' . $w . '-' . $l . $tie : ( $w > $l ? $A : $B ) . ' lead ' . max( $w, $l ) . '-' . min( $w, $l ) . $tie;
		$po = $pa + $pb ? ( $pa === $pb ? 'Tied ' . $pa . '-' . $pb : ( $pa > $pb ? $A : $B ) . ' lead ' . max( $pa, $pb ) . '-' . min( $pa, $pb ) ) : 'Never met';
		$last = end( $prior ); $lt = $last ? (int) $last[1] . ': ' . self::nick( $last[4] ) . ' ' . (int) $last[5] . ', ' . self::nick( $last[6] ) . ' ' . (int) $last[7] : 'None in the last five';
		return '<section class="pn"><h3>The series before this game</h3><table><tbody><tr><td>All meetings since ' . (int) $s['since'] . '</td><td class="n">' . esc_html( $ld ) . '</td></tr><tr><td>In the playoffs</td><td class="n">' . esc_html( $po ) . '</td></tr><tr><td>Last meeting</td><td class="n">' . esc_html( $lt ) . '</td></tr></tbody></table></section>';
	}
	private static function recap( $g ) {
		if ( ! self::final_( $g ) ) { return ''; }
		$W = DBD_Week::file( (int) $g['week'] ); $plays = array_values( array_filter( (array) ( $W['plays'] ?? array() ), function ( $p ) use ( $g ) { return ( $p['g'] ?? '' ) === $g['id']; } ) );
		usort( $plays, function ( $a, $b ) { return (float) $b['e'] <=> (float) $a['e']; } );
		$ph = ''; foreach ( array_slice( $plays, 0, 3 ) as $p ) { $ph .= '<div class="pl"><small>' . esc_html( self::nick( $p['t'] ) . ' · ' . $p['c'] . ' · ' . self::sg( $p['e'] ) . ' EPA' ) . '</small>' . esc_html( DBD_Pack::play( $p['d'] ) ) . '</div>'; }
		$rows = (array) DBD_Pack::box_rows( (int) $g['week'] ); $cell = function ( $t, $k ) use ( $rows, $g ) {
			$best = null; foreach ( $rows as $x ) { if ( $x['t'] === $t && self::has( $g, (string) $x['o'] ) && (int) $x[ $k ] > 0 && ( ! $best || (int) $x[ $k ] > (int) $best[ $k ] ) ) { $best = $x; } }
			return $best ? esc_html( $best['full'] ) . ' <b>' . (int) $best[ $k ] . '</b> yds' : '';
		};
		$tb = ''; foreach ( array( 'Passing' => 'passing_yards', 'Rushing' => 'rushing_yards', 'Receiving' => 'receiving_yards' ) as $lab => $k ) { $tb .= '<tr><td>' . $lab . '</td><td>' . $cell( $g['away'], $k ) . '</td><td>' . $cell( $g['home'], $k ) . '</td></tr>'; }
		return '<div class="cards">' . ( $ph !== '' ? '<section class="pn"><h3>Biggest plays</h3>' . $ph . '</section>' : '' ) . '<section class="pn"><h3>Who led each team</h3><table><thead><tr><th></th><th>' . esc_html( self::nick( $g['away'] ) ) . '</th><th>' . esc_html( self::nick( $g['home'] ) ) . '</th></tr></thead><tbody>' . $tb . '</tbody></table></section></div>';
	}
	public static function game_page( $id ) {
		$g = self::find( $id ); if ( ! $g ) { return ''; }
		$f = self::final_( $g ); $sb = $g['r'] === 'SB';
		$tm = function ( $t, $h ) { return '<div class="tm' . ( $h ? ' h' : '' ) . '"><span class="lg">' . DBD_Marks::tile( $t, 64 ) . '</span><span class="tn"><b>' . esc_html( self::nick( $t ) ) . '</b><small>' . esc_html( self::seed( $t ) . ' seed · ' . self::rec( $t ) ) . '</small></span></div>'; };
		$spr = ''; if ( ! $f && isset( $g['spread'] ) && $g['spread'] !== null ) { $v = (float) $g['spread']; $spr = $v == 0 ? 'Pick’em' : self::nick( $v > 0 ? $g['home'] : $g['away'] ) . ' -' . abs( $v ); if ( ! empty( $g['total'] ) ) { $spr .= ' · O/U ' . $g['total']; } }
		$info = esc_html( self::clock( $g ) ) . '<br>' . esc_html( (string) ( $g['stadium'] ?? '' ) ) . ( $spr !== '' ? '<br>' . esc_html( $spr ) : '' );
		$mid = $f ? '<div class="sc">' . (int) $g['away_score'] . ' - ' . (int) $g['home_score'] . '</div><div class="inf">Final</div>' : '<div class="sc at">at</div>';
		$side = function ( $t, $o ) use ( $f ) {
			$e = self::edge( $t, $o );
			return '<section class="pn"><h3>' . esc_html( ( $f ? 'The ' . self::nick( $t ) . ' going in' : 'Where the ' . self::nick( $t ) . ' can win it' ) ) . '</h3>' . ( $e ? '<div class="pad edge"><b>' . esc_html( $e['head'] ) . '</b><p>' . esc_html( $e['say'] ) . '</p></div>' : '' ) . self::stats_table( $t, $o ) . '</section>';
		};
		return '<div class="dbd-po po-pg">' . self::off_note() . '<a class="back" href="' . esc_url( self::link() ) . '">The full bracket</a><div class="gh' . ( $sb ? ' sbk' : '' ) . '">' . $tm( $g['away'], false ) . '<div class="mid"><div class="rd">' . esc_html( $sb ? self::sb_name() : self::ROUNDS[ $g['r'] ] . ( $g['conf'] ? ' · ' . $g['conf'] : '' ) ) . '</div>' . $mid . '<div class="inf">' . $info . '</div></div>' . $tm( $g['home'], true ) . '</div>'
			. self::recap( $g ) . '<div class="cards">' . $side( $g['away'], $g['home'] ) . $side( $g['home'], $g['away'] ) . self::road( $g['away'], $g ) . self::road( $g['home'], $g ) . '</div><div class="cards one">' . self::series_panel( $g ) . '</div>'
			. ( $f ? '<p class="po-all"><a href="' . esc_url( DBD_Router::url( array( 'game' => $g['id'] ) ) ) . '">Every snap of this game</a></p>' : '' ) . '</div>';
	}

	/** The page's title and description. */
	public static function seo( $r ) {
		$y = (int) DBD_Data::season();
		if ( ! empty( $r['pgame'] ) ) {
			$g = self::find( $r['pgame'] );
			if ( $g ) { $lab = $g['r'] === 'SB' ? self::sb_name() : self::ROUNDS[ $g['r'] ]; $m = self::nick( $g['away'] ) . ' at ' . self::nick( $g['home'] ); return array( "$m: $y $lab preview", "$m in the $y NFL playoffs: where each team can win it, offense against defense, each team's road and the series." ); }
		}
		return array( "$y NFL playoff bracket", "The $y NFL playoff bracket drawn on the field: every round, every matchup, playoff leaders and a preview of each game." );
	}
}
