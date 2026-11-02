<?php
/**
 * Share cards (SCOPE §26, §43): 1200 × 630 PNGs drawn with GD and FreeType from the plugin's bundled fonts. The player
 * card (spec() and draw(), pure PHP so tools/card_harness.php can render one outside WordPress); since 1.19.0 a game card
 * (before kickoff, then final), a team card and a page card for every other view, drawn with a shared kit. render()
 * draws any of them; serve() is the WordPress side (route, cache in uploads, headers); prune() clears old ones after a sync.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Card {
	const W = 1200; const H = 630; const SS = 2;  /* drawn at 2× and downsampled for smooth edges */
	const BG = '#f4f1ea'; const PANEL = '#fffdf8'; const INK = '#121417'; const INK2 = '#3b4255'; const MUTED = '#6b6f78'; const RED = '#cb2026'; const GOOD = '#1e7f4a'; const BAD = '#b4261f';

	public static function available() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' ) && function_exists( 'imagepng' ) && function_exists( 'imagecopyresampled' );
	}

	/** A card's address: ?card= (a player id, team-MIN, game-…, page-…). It stays a query string under readable URLs: some hosts serve any .png path as a static file. */
	public static function url( $id ) { return DBD_Router::url( array( 'card' => $id ) ); }

	/** Everything the drawing needs, from the player record ($rec), his index row ($row), the team, the league group info, season and through week. */
	public static function spec( $rec, $row, $team, $gi, $season, $through ) {
		$grp = DBD_Grades::group_of( $rec['pos'] ?? '' ); if ( ! $grp ) { return null; }
		$W = $rec['weeks'] ?? array(); $sum = function ( $k ) use ( $W ) { $s = 0; foreach ( $W as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s; };
		$played = array_unique( array_merge( array_map( function ( $w ) { return (int) $w['week']; }, $W ), array_map( 'intval', array_keys( $rec['snaps'] ?? array() ) ) ) ); $gp = max( 1, count( $played ) );
		$f1 = function ( $v ) { return number_format( (float) $v, 1 ); }; $pct = function ( $n, $d ) { return $d ? round( $n / $d * 100 ) . '%' : '-'; };
		$tg = $sum( 'targets' ); $rc = $sum( 'receptions' ); $ry = $sum( 'receiving_yards' ); $rtd = $sum( 'receiving_tds' ); $ca = $sum( 'carries' ); $ruy = $sum( 'rushing_yards' ); $rutd = $sum( 'rushing_tds' );
		$att = $sum( 'attempts' ); $cmp = $sum( 'completions' ); $py = $sum( 'passing_yards' ); $ptd = $sum( 'passing_tds' ); $pint = $sum( 'passing_interceptions' );
		if ( $grp === 'rec' ) { $tiles = array( array( 'Targets', $tg, $f1( $tg / $gp ) . ' per game' ), array( 'Receptions', $rc, $pct( $rc, $tg ) . ' catch rate' ), array( 'Yards', $ry, $f1( $ry / $gp ) . ' per game' ), array( 'TD', $rtd, 'in ' . $gp . ' games' ) ); }
		elseif ( $grp === 'rb' ) { $tiles = array( array( 'Carries', $ca, $f1( $ca / $gp ) . ' per game' ), array( 'Rush yards', $ruy, ( $ca ? $f1( $ruy / $ca ) : '-' ) . ' per carry' ), array( 'TD', $rutd + $rtd, 'rush + rec' ), array( 'Receptions', $rc, (int) $ry . ' yards' ) ); }
		else { $tiles = array( array( 'Attempts', $att, $pct( $cmp, $att ) . ' completed' ), array( 'Pass yards', $py, ( $att ? $f1( $py / $att ) : '-' ) . ' per attempt' ), array( 'TD', $ptd, 'in ' . $gp . ' games' ), array( 'INT', $pint, '' ) ); }
		$q = ! empty( $rec['grades']['q'] ); $m = $rec['grades']['m'] ?? array(); $rows = array();
		foreach ( DBD_Grades::defs( $grp ) as $d ) { $v = $m[ $d[0] ] ?? null; $pc = $q ? DBD_Grades::pctile_in( $gi['dist'][ $d[0] ] ?? array(), $v, in_array( $d[0], $gi['low'] ?? array(), true ) ) : null; $rows[] = array( 'lab' => $d[1], 'pc' => $pc ); }
		$parts = explode( ' ', (string) $rec['name'], 2 ); $N = (int) ( $gi['n'] ?? 0 ); $gpool = $gi['_pool'] ?? DBD_Grades::pool_of( $rec['pos'] ?? '' ) ?? $grp;  /* the caller says which pool $gi is (SCOPE §38); spec() stays free of DBD_Data so the card harness can run it */
		return array(
			'season' => (int) $season, 'through' => (int) $through,
			'team' => array( 'abbr' => $row['team'] ?? '', 'nick' => $team['nick'] ?? ( $row['team'] ?? '' ), 'c1' => $team['c1'] ?? '#4F2683', 'c2' => $team['c2'] ?? '#FFC62F', 'accent' => DBD_Marks::accent( $team ?: null, self::BG ) ),
			'first' => $parts[0], 'last' => $parts[1] ?? '', 'num' => (string) ( $rec['num'] ?? '' ),
			'kicker' => $rec['pos'] . ' · #' . $rec['num'] . ' · ' . ( $team['nick'] ?? '' ) . ' · ' . DBD_Grades::ordinal( (int) ( $rec['exp'] ?? 0 ) + 1 ) . ' season',
			'tiles' => $tiles, 'graded' => $q, 'ranks' => $rows,
			'rank_head' => 'Where he ranks · ' . ( $q ? 'among ' . $N . ' ' . DBD_Grades::pool_label( $gpool ) : 'graded at ' . ( $gi['min'] ?? 'the qualifier' ) ),
			'foot_right' => 'Data: nflverse · grades among ' . $N . ' ' . DBD_Grades::pool_label( $gpool ),
		);
	}

	/** Draw a spec. $assets: fonts (dir with the bundled TTFs), logo (png path), photo (binary string or null). Returns a GD image or null. */
	public static function draw( $spec, $assets ) {
		if ( ! $spec || ! self::available() ) { return null; }
		$S = self::SS; $W = self::W * $S; $H = self::H * $S; $fd = rtrim( $assets['fonts'], '/' ) . '/';
		$F = array( 'hero' => $fd . 'Tourney-ExtraBold.ttf', 'disp' => $fd . 'BarlowCondensed-Bold.ttf', 'disp2' => $fd . 'BarlowCondensed-SemiBold.ttf', 'body' => $fd . 'IBMPlexSans-Medium.ttf', 'mono' => $fd . 'IBMPlexMono-Medium.ttf', 'mono2' => $fd . 'IBMPlexMono-SemiBold.ttf' );
		foreach ( $F as $f ) { if ( ! is_readable( $f ) ) { return null; } }
		$im = imagecreatetruecolor( $W, $H ); if ( ! $im ) { return null; }
		imagealphablending( $im, true ); imagesavealpha( $im, false );
		$rgb = function ( $hex ) { $hex = ltrim( (string) $hex, '#' ); if ( strlen( $hex ) !== 6 ) { $hex = '121417'; } return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ); };
		$col = function ( $hex ) use ( $im, $rgb ) { list( $r, $g, $b ) = $rgb( $hex ); return imagecolorallocate( $im, $r, $g, $b ); };
		/* a translucent color flattened onto the background, so GD never has to blend alpha */
		$mix = function ( $hex, $a, $on = self::BG ) use ( $im, $rgb ) { list( $r, $g, $b ) = $rgb( $hex ); list( $br, $bg, $bb ) = $rgb( $on ); return imagecolorallocate( $im, (int) round( $br + ( $r - $br ) * $a ), (int) round( $bg + ( $g - $bg ) * $a ), (int) round( $bb + ( $b - $bb ) * $a ) ); };
		$pt = function ( $px ) use ( $S ) { return $px * $S * 0.75; };  /* GD takes points; 1 CSS px = 0.75 pt */
		$tw = function ( $font, $px, $text ) use ( $pt ) { $b = imagettfbbox( $pt( $px ), 0, $font, $text ); return $b ? abs( $b[2] - $b[0] ) : 0; };
		$txt = function ( $font, $px, $x, $y, $color, $text ) use ( $im, $pt, $S ) { imagettftext( $im, $pt( $px ), 0, (int) round( $x * $S ), (int) round( $y * $S ), $color, $font, $text ); };
		$spaced = function ( $font, $px, $x, $y, $color, $text, $ls, $align = 'left' ) use ( $im, $pt, $tw, $S ) {
			$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY ); $gap = $ls * $px * $S; $total = 0; $ws = array();
			foreach ( $chars as $c ) { $w = $tw( $font, $px, $c ); $ws[] = $w; $total += $w; } $total += $gap * max( 0, count( $chars ) - 1 );
			$cx = $x * $S - ( $align === 'right' ? $total : 0 );
			foreach ( $chars as $i => $c ) { imagettftext( $im, $pt( $px ), 0, (int) round( $cx ), (int) round( $y * $S ), $color, $font, $c ); $cx += $ws[ $i ] + $gap; }
			return $total / $S;
		};
		$rect = function ( $x, $y, $w, $h, $color ) use ( $im, $S ) { imagefilledrectangle( $im, (int) round( $x * $S ), (int) round( $y * $S ), (int) round( ( $x + $w ) * $S ) - 1, (int) round( ( $y + $h ) * $S ) - 1, $color ); };
		$bg = $col( self::BG ); imagefilledrectangle( $im, 0, 0, $W, $H, $bg );
		$ink = $col( self::INK ); $ink2 = $col( self::INK2 ); $muted = $col( self::MUTED ); $red = $col( self::RED ); $team = $col( $spec['team']['c1'] ); $accent = $col( $spec['team']['accent'] );
		$line = $mix( '#161e36', 0.14 ); $line2 = $mix( '#161e36', 0.30 );

		/* brand bar */
		if ( ! empty( $assets['logo'] ) && is_readable( $assets['logo'] ) ) { $lg = @imagecreatefrompng( $assets['logo'] ); if ( $lg ) {
			/* the logo PNG sits on white; resample it small, then key the white out onto the card's background: out = C - (min(C)/255) * (255 - bg) */
			$lw = imagesx( $lg ); $lh = imagesy( $lg ); $th = 34 * $S; $twd = (int) round( $lw * $th / $lh ); $sm = imagecreatetruecolor( $twd, $th ); $white = imagecolorallocate( $sm, 255, 255, 255 ); imagefilledrectangle( $sm, 0, 0, $twd, $th, $white );
			imagecopyresampled( $sm, $lg, 0, 0, 0, 0, $twd, $th, $lw, $lh ); imagedestroy( $lg ); list( $br, $bgc, $bb ) = $rgb( self::BG );
			for ( $yy = 0; $yy < $th; $yy++ ) { for ( $xx = 0; $xx < $twd; $xx++ ) { $c = imagecolorat( $sm, $xx, $yy ); $r = ( $c >> 16 ) & 255; $g = ( $c >> 8 ) & 255; $b = $c & 255; $k = min( $r, $g, $b ) / 255;
				imagesetpixel( $im, 44 * $S + $xx, 15 * $S + $yy, imagecolorallocate( $im, (int) round( $r - $k * ( 255 - $br ) ), (int) round( $g - $k * ( 255 - $bgc ) ), (int) round( $b - $k * ( 255 - $bb ) ) ) ); } }
			imagedestroy( $sm ); } }
		$sw = $spaced( $F['disp'], 16, 1156, 38, $ink2, strtoupper( $spec['season'] . ' season · through week ' . $spec['through'] ), 0.2, 'right' );
		/* the team logo in the brand bar's corner when logos are on (SCOPE §28) */
		$tl = ! empty( $assets['team_logo'] ) ? @imagecreatefromstring( $assets['team_logo'] ) : false;
		if ( $tl ) { $ls = 40 * $S; imagealphablending( $im, true ); imagecopyresampled( $im, $tl, (int) round( ( 1156 - $sw - 54 ) * $S ), 12 * $S, 0, 0, $ls, $ls, imagesx( $tl ), imagesy( $tl ) ); imagedestroy( $tl ); }
		$rect( 0, 64, self::W, 6, $accent );

		/* hero: disc, headshot or number, kicker, name */
		$dcx = 124; $dcy = 186; $dr = 80;
		imagefilledellipse( $im, $dcx * $S, $dcy * $S, 2 * $dr * $S, 2 * $dr * $S, $team );
		$photo = ! empty( $assets['photo'] ) ? @imagecreatefromstring( $assets['photo'] ) : false;
		if ( $photo ) {
			$pw = imagesx( $photo ); $ph = imagesy( $photo ); $side = min( $pw, $ph ); $sx = (int) ( ( $pw - $side ) / 2 ); $sy = 0;  /* top-aligned square crop, like object-position: top */
			$d = 2 * $dr * $S; $disc = imagecreatetruecolor( $d, $d ); imagecopyresampled( $disc, $photo, 0, 0, $sx, $sy, $d, $d, $side, $side ); imagedestroy( $photo );
			list( $tr, $tgc, $tb ) = $rgb( $spec['team']['c1'] ); $tcol = imagecolorallocate( $disc, $tr, $tgc, $tb ); $r2 = ( $d / 2 ) * ( $d / 2 );
			for ( $yy = 0; $yy < $d; $yy++ ) { $dy = $yy + 0.5 - $d / 2; for ( $xx = 0; $xx < $d; $xx++ ) { $dx = $xx + 0.5 - $d / 2; if ( $dx * $dx + $dy * $dy > $r2 ) { imagesetpixel( $disc, $xx, $yy, $tcol ); } } }
			imagecopy( $im, $disc, ( $dcx - $dr ) * $S, ( $dcy - $dr ) * $S, 0, 0, $d, $d ); imagedestroy( $disc );
		} else {
			$numc = $col( DBD_Marks::ink( $spec['team']['c1'], $spec['team']['c2'] ) ); $nw = $tw( $F['hero'], 56, $spec['num'] ) / $S;
			$txt( $F['hero'], 56, $dcx - $nw / 2, $dcy + 20, $numc, $spec['num'] );
		}
		$spaced( $F['disp'], 15, 226, 142, $muted, strtoupper( $spec['kicker'] ), 0.2 );
		$txt( $F['hero'], 60, 226, 206, $ink, strtoupper( $spec['first'] ) );
		$txt( $F['hero'], 60, 226, 266, $red, strtoupper( $spec['last'] ) );

		/* tiles: four across the left half, or across the whole card when he is not graded yet (SCOPE §26) */
		$ty = 306; $tw0 = $spec['graded'] ? 131 : 269; $th0 = 108;
		foreach ( $spec['tiles'] as $i => $t ) { $tx = 44 + $i * ( $tw0 + 12 );
			$rect( $tx, $ty, $tw0, $th0, $line ); $rect( $tx + 1, $ty + 1, $tw0 - 2, $th0 - 2, $col( self::PANEL ) );
			$spaced( $F['disp'], 11, $tx + 14, $ty + 27, $muted, strtoupper( $t[0] ), 0.16 );
			$txt( $F['hero'], 42, $tx + 14, $ty + 75, $ink, number_format( (float) $t[1] ) );
			if ( $t[2] !== '' ) { $txt( $F['mono'], 12, $tx + 14, $ty + 95, $muted, $t[2] ); } }

		/* where he ranks, or the qualifier line for a player who is not graded yet */
		if ( ! $spec['graded'] ) {
			$spaced( $F['disp'], 14, 44, 452, $muted, strtoupper( $spec['rank_head'] ), 0.2 );
			$txt( $F['body'], 16, 44, 482, $ink2, 'League percentile bars appear once he clears the qualifier; the season line above is complete.' );
		} else {
		$rx = 648; $spaced( $F['disp'], 13, $rx, 119, $muted, strtoupper( $spec['rank_head'] ), 0.2 );
		$bx = 838; $bw = 230; $good = $col( self::GOOD ); $bad = $col( self::BAD );
		/* the bar gradient, red through grey to green, flattened on the background once */
		$grad = array(); for ( $i = 0; $i <= $bw; $i++ ) { $t = $i / $bw; if ( $t < 0.5 ) { $u = $t / 0.5; $c = array( 180 + ( 107 - 180 ) * $u, 38 + ( 111 - 38 ) * $u, 31 + ( 120 - 31 ) * $u ); $a = 0.18 + ( 0.12 - 0.18 ) * $u; } else { $u = ( $t - 0.5 ) / 0.5; $c = array( 107 + ( 30 - 107 ) * $u, 111 + ( 127 - 111 ) * $u, 120 + ( 74 - 120 ) * $u ); $a = 0.12 + ( 0.22 - 0.12 ) * $u; }
			$grad[ $i ] = $mix( sprintf( '#%02x%02x%02x', (int) $c[0], (int) $c[1], (int) $c[2] ), $a ); }
		foreach ( $spec['ranks'] as $i => $r ) { $y = 132 + $i * 55; if ( $i ) { $rect( $rx, $y, 508, 1, $line ); }
			$txt( $F['body'], 16, $rx, $y + 33, $ink, $r['lab'] );
			for ( $k = 0; $k < $bw; $k++ ) { $rect( $bx + $k, $y + 21.5, 1, 12, $grad[ $k ] ); }
			for ( $dsh = 0; $dsh < 18; $dsh += 6 ) { $rect( $bx + $bw / 2 - 1, $y + 18 + $dsh, 2, 3, $line2 ); }
			if ( $r['pc'] !== null ) { $mx = $bx + $r['pc'] / 100 * $bw; $rect( $mx - 6, $y + 14, 12, 26, $bg ); $rect( $mx - 3, $y + 17, 6, 20, $team ); }
			$label = $r['pc'] === null ? '–' : DBD_Grades::ordinal( $r['pc'] ); $pcol = $r['pc'] === null ? $muted : ( $r['pc'] >= 75 ? $good : ( $r['pc'] <= 25 ? $bad : $ink ) );
			$lw = $tw( $F['hero'], 24, $label ) / $S; $txt( $F['hero'], 24, 1156 - $lw, $y + 36, $pcol, $label ); }
		}

		/* footer */
		$rect( 0, 586, self::W, 1, $line );
		$txt( $F['mono2'], 13, 44, 613, $ink, 'downbydown.com' );
		$fw = $tw( $F['mono'], 13, $spec['foot_right'] ) / $S; $txt( $F['mono'], 13, 1156 - $fw, 613, $muted, $spec['foot_right'] );

		$out = imagecreatetruecolor( self::W, self::H ); imagecopyresampled( $out, $im, 0, 0, 0, 0, self::W, self::H, $W, $H ); imagedestroy( $im );
		return $out;
	}

	/* ================================================================== serving (SCOPE §26, §43) */

	/** Card keys: a player id, team-MIN, game-2026_08_MIN_DET, page-hub / page-week-8 / page-leaders … */
	public static function valid( $key ) {
		return (bool) preg_match( '/^(\d{2}-\d{7}|team-[A-Z]{2,3}|game-\d{4}_\d{2}_[A-Z]{2,3}_[A-Z]{2,3}|page-(hub|matchups|teams|players|leaders|myteam|week-\d{1,2}))$/', (string) $key );
	}

	/** The card a route shares: the player's, the team's, the game's, the first compared player's, or the page card. */
	public static function key_for( $r ) {
		switch ( $r['view'] ) {
			case 'player': return $r['player']['id'] ?? null;
			case 'compare': return $r['compare'][0]['id'] ?? 'page-players';
			case 'team': return 'team-' . $r['team'];
			case 'teamplayers': return 'team-' . $r['pteam'];
			case 'game': return 'game-' . $r['game']['id'];
			case 'week': return 'page-week-' . (int) $r['week'];
			case 'hub': case 'matchups': case 'teams': case 'players': case 'leaders': case 'myteam': return 'page-' . $r['view'];
		}
		return null;
	}

	/** The cache file's name: the subject, the season, the data week and the plugin version, plus the schedule's version for games and pages (a final changes them) and -l with logos. */
	public static function file_name( $key ) {
		$tag = '-' . (int) DBD_Data::season() . '-' . (int) DBD_Data::through_week() . '-' . DBD_VERSION;
		$h = preg_match( '/^\d{2}-\d{7}$/', $key ) ? '' : '-' . substr( md5( (string) DBD_Data::file_version( 'schedule.json' ) ), 0, 6 );
		return $key . $tag . $h . ( DBD_Data::logos() ? '-l' : '' ) . '.png';
	}

	public static function dir() { $up = wp_upload_dir(); return trailingslashit( $up['basedir'] ) . 'down-by-down/cards'; }

	/** The route: render once per file name, cache under uploads, stream. Exits. */
	public static function serve( $key ) {
		$fail = function ( $code ) { status_header( $code ); nocache_headers(); exit; };
		if ( ! self::available() || ! self::valid( $key ) ) { $fail( 404 ); }
		$dir = self::dir(); wp_mkdir_p( $dir ); $file = $dir . '/' . self::file_name( $key );
		if ( ! file_exists( $file ) ) {
			$fetch = function ( $url ) { $r = wp_remote_get( $url, array( 'timeout' => 5 ) ); return ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) ? wp_remote_retrieve_body( $r ) : null; };
			$im = self::render( $key, $fetch ); if ( ! $im ) { $fail( 404 ); }
			/* write beside the final name, then rename into place, so a request arriving mid-write never streams a half-written PNG */
			$tmp = $dir . '/.' . wp_generate_password( 12, false ) . '.tmp'; $ok = imagepng( $im, $tmp, 6 ); imagedestroy( $im );
			if ( ! $ok || ! file_exists( $tmp ) || filesize( $tmp ) < 1000 || ! @rename( $tmp, $file ) ) { @unlink( $tmp ); $fail( 500 ); }
		}
		header( 'Content-Type: image/png' ); header( 'Content-Length: ' . filesize( $file ) ); header( 'Cache-Control: public, max-age=86400' ); header( 'X-Content-Type-Options: nosniff' );
		readfile( $file ); exit;
	}

	/** Draw any card. $fetch( url ) returns image bytes or null (the live route uses wp_remote_get; the harness reads the URL or nothing). */
	public static function render( $key, $fetch = null ) {
		if ( ! self::available() || ! self::valid( $key ) ) { return null; }
		$fetch = $fetch ?: function () { return null; };
		$assets = array( 'fonts' => DBD_PATH . 'assets/fonts/', 'logo' => DBD_PATH . 'assets/card-logo.png', 'logos' => array() );
		$logo = function ( $t ) use ( $fetch ) { return self::logo_bin( $t, $fetch ); };
		if ( preg_match( '/^\d{2}-\d{7}$/', $key ) ) {
			$row = DBD_Data::player( $key ); if ( ! $row ) { return null; }
			$rec = DBD_Data::player_record( $row['team'], $key ); if ( ! $rec ) { return null; }
			$team = DBD_Data::team( $row['team'] ); $grp = DBD_Grades::in_use( $rec['grades']['pool'] ?? DBD_Grades::pool_of( $row['pos'] ) ); $gi = $grp ? ( DBD_Grades::group_info( $grp ) ?: array() ) : array(); $gi['_pool'] = $grp;  /* SCOPE §38: the player's own pool */
			$spec = self::spec( $rec, $row, $team, $gi, DBD_Data::season(), DBD_Data::through_week() ); if ( ! $spec ) { return null; }
			$assets['photo'] = ! empty( $rec['photo'] ) ? $fetch( $rec['photo'] ) : null;
			$assets['team_logo'] = $logo( $row['team'] );
			return self::draw( $spec, $assets );
		}
		if ( strpos( $key, 'team-' ) === 0 ) { $s = self::spec_team( substr( $key, 5 ) ); if ( ! $s ) { return null; } foreach ( $s['logos'] as $t ) { $assets['logos'][ $t ] = $logo( $t ); } return self::draw_team( $s, $assets ); }
		if ( strpos( $key, 'game-' ) === 0 ) { $g = DBD_Data::game( substr( $key, 5 ) ); if ( ! $g ) { return null; } $s = self::spec_game( $g ); foreach ( $s['logos'] as $t ) { $assets['logos'][ $t ] = $logo( $t ); } return self::draw_game( $s, $assets ); }
		$s = self::spec_page( substr( $key, 5 ) ); if ( ! $s ) { return null; }
		return self::draw_page( $s, $assets );
	}

	/** A team's logo bytes when logos are on, kept under uploads for 30 days so a card render doesn't fetch the same logo twice. */
	private static function logo_bin( $abbr, $fetch ) {
		$url = DBD_Data::logo( $abbr ); if ( ! $url ) { return null; }
		$dir = self::dir() . '/logos'; $f = $dir . '/' . preg_replace( '/[^A-Z]/', '', $abbr ) . '.png';
		if ( is_readable( $f ) && filemtime( $f ) > time() - 30 * DAY_IN_SECONDS ) { $b = file_get_contents( $f ); return $b !== false && $b !== '' ? $b : null; }
		$b = $fetch( $url ); if ( ! $b ) { return null; }
		if ( wp_mkdir_p( $dir ) ) { @file_put_contents( $f, $b ); }
		return $b;
	}

	/** After a sync: delete every cached card that isn't for this season, data week, plugin version and schedule, and any older than 14 days. Returns [count, bytes]. */
	public static function prune( $all = false ) {
		$dir = self::dir(); if ( ! is_dir( $dir ) ) { return array( 0, 0 ); }
		$tag = '-' . (int) DBD_Data::season() . '-' . (int) DBD_Data::through_week() . '-' . DBD_VERSION; $h = '-' . substr( md5( (string) DBD_Data::file_version( 'schedule.json' ) ), 0, 6 );
		$n = 0; $bytes = 0; $old = time() - 14 * DAY_IN_SECONDS;
		foreach ( array_merge( (array) glob( $dir . '/*.png' ), (array) glob( $dir . '/.*.tmp' ) ) as $f ) {
			if ( ! is_file( $f ) ) { continue; }
			$b = basename( $f ); $player = (bool) preg_match( '/^\d{2}-\d{7}-/', $b );
			$keep = ! $all && substr( $b, -4 ) === '.png' && strpos( $b, $tag ) !== false && ( $player || strpos( $b, $h ) !== false ) && filemtime( $f ) >= $old;
			if ( substr( $b, -4 ) === '.tmp' ) { $keep = filemtime( $f ) > time() - HOUR_IN_SECONDS; }
			if ( $keep ) { continue; }
			$sz = (int) filesize( $f ); if ( @unlink( $f ) ) { $n++; $bytes += $sz; }
		}
		update_option( 'dbd_card_prune', array( 'time' => time(), 'n' => $n, 'bytes' => $bytes ), false );
		return array( $n, $bytes );
	}

	/** How many cards are cached and their size, for the admin box. */
	public static function stats() {
		$dir = self::dir(); $n = 0; $bytes = 0;
		foreach ( (array) glob( $dir . '/*.png' ) as $f ) { if ( is_file( $f ) ) { $n++; $bytes += (int) filesize( $f ); } }
		return array( $n, $bytes );
	}

	/* ================================================================== specs: plain data for the three new cards */

	private static function pd() {
		static $pd = null; if ( $pd !== null ) { return $pd; }
		$pd = array();
		foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( (array) $gl as $g ) {
			if ( ! isset( $g['home_score'], $g['away_score'] ) || $g['home_score'] === null || ( $g['game_type'] ?? 'REG' ) !== 'REG' ) { continue; }
			$pd[ $g['home'] ] = ( $pd[ $g['home'] ] ?? 0 ) + $g['home_score'] - $g['away_score']; $pd[ $g['away'] ] = ( $pd[ $g['away'] ] ?? 0 ) + $g['away_score'] - $g['home_score']; } }
		return $pd;
	}

	/** Place in the division: win percentage, then point differential. */
	private static function place( $abbr ) {
		$T = DBD_Data::teams(); $rec = DBD_Data::records(); $pd = self::pd(); $div = $T[ $abbr ]['div'] ?? '';
		$mates = array_values( array_filter( array_keys( $T ), function ( $t ) use ( $T, $div ) { return ( $T[ $t ]['div'] ?? '' ) === $div; } ) );
		$wp = function ( $t ) use ( $rec ) { $r = $rec[ $t ] ?? array( 0, 0, 0 ); $n = $r[0] + $r[1] + $r[2]; return $n ? ( $r[0] + $r[2] / 2 ) / $n : 0; };
		usort( $mates, function ( $a, $b ) use ( $wp, $pd ) { return $wp( $b ) <=> $wp( $a ) ?: ( $pd[ $b ] ?? 0 ) <=> ( $pd[ $a ] ?? 0 ); } );
		return array_search( $abbr, $mates, true ) + 1;
	}

	private static function team_line( $t ) {
		$T = DBD_Data::teams();
		return array( 'abbr' => $t, 'nick' => $T[ $t ]['nick'] ?? $t, 'c1' => $T[ $t ]['c1'] ?? '#121417', 'c2' => $T[ $t ]['c2'] ?? '#ffffff',
			'rec' => DBD_Data::record_text( $t ) . ' · ' . DBD_Grades::ordinal( self::place( $t ) ) . ' in the ' . ( $T[ $t ]['div'] ?? '' ) );
	}

	public static function spec_game( $g ) {
		$a = $g['away']; $h = $g['home']; $fin = DBD_Router::is_final( $g ); $wk = (int) $g['week']; $T = DBD_Data::teams();
		$s = array( 'season' => (int) substr( $g['id'], 0, 4 ), 'week' => $wk, 'final' => $fin, 'away' => self::team_line( $a ), 'home' => self::team_line( $h ), 'logos' => array( $a, $h ),
			'line' => trim( DBD_Router::line_text( $g ) . ( isset( $g['total'] ) && $g['total'] !== null ? ' · O/U ' . rtrim( rtrim( number_format( (float) $g['total'], 1 ), '0' ), '.' ) : '' ), ' ·' ), 'stadium' => (string) ( $g['stadium'] ?? '' ) );
		if ( $fin ) {
			$s['as'] = (int) $g['away_score']; $s['hs'] = (int) $g['home_score']; $s['win'] = $s['as'] > $s['hs'] ? $a : ( $s['hs'] > $s['as'] ? $h : '' );
			$W = DBD_Week::file( $wk ); $wg = null; foreach ( (array) ( $W['games'] ?? array() ) as $x ) { if ( ( $x['id'] ?? '' ) === $g['id'] ) { $wg = $x; } }
			$s['ot'] = ! empty( $wg['ot'] ); $s['tags'] = array_map( function ( $t ) { return array( $t[0], $t[1] ); }, (array) ( $wg['tags'] ?? array() ) );
			$s['spark'] = ! empty( $wg['spark'] ) && $s['win'] ? array_map( 'intval', $wg['spark'] ) : array();  /* the winner's win probability, per mille */
			$s['big'] = $wg['big'] ?? null; $s['low'] = $s['spark'] ? min( $s['spark'] ) / 10 : null;
			$s['leaders'] = array(); $B = DBD_Data::box( $g );
			if ( $B && ! empty( $B['keys'] ) ) {
				$ix = array_flip( $B['keys'] ); $rows = array();
				foreach ( array( 'away', 'home' ) as $side ) { foreach ( (array) ( $B[ $side ]['off'] ?? array() ) as $r ) { $rows[] = array( $B[ $side ]['team'] ?? '', $r ); } }
				$v = function ( $r, $k ) use ( $ix ) { return isset( $ix[ $k ] ) ? (float) ( $r[ $ix[ $k ] ] ?? 0 ) : 0; };
				$name = function ( $r ) use ( $ix ) { $p = DBD_Data::player( $r[ $ix['id'] ?? 0 ] ?? '' ); return $p['name'] ?? (string) ( $r[ $ix['name'] ?? 1 ] ?? '' ); };
				$td = function ( $n ) { return $n ? ' · ' . (int) $n . ' TD' : ''; };
				$best = function ( $k ) use ( $rows, $v ) { $b = null; foreach ( $rows as $x ) { if ( $b === null || $v( $x[1], $k ) > $v( $b[1], $k ) ) { $b = $x; } } return ( $b && $v( $b[1], $k ) > 0 ) ? $b : null; };
				if ( $p = $best( 'passing_yards' ) ) { $s['leaders'][] = array( 'Passing', $p[0], $name( $p[1] ), (int) $v( $p[1], 'completions' ) . '/' . (int) $v( $p[1], 'attempts' ) . ' · ' . (int) $v( $p[1], 'passing_yards' ) . ' yds' . $td( $v( $p[1], 'passing_tds' ) ) ); }
				if ( $p = $best( 'rushing_yards' ) ) { $s['leaders'][] = array( 'Rushing', $p[0], $name( $p[1] ), (int) $v( $p[1], 'carries' ) . ' car · ' . (int) $v( $p[1], 'rushing_yards' ) . ' yds' . $td( $v( $p[1], 'rushing_tds' ) ) ); }
				if ( $p = $best( 'receiving_yards' ) ) { $s['leaders'][] = array( 'Receiving', $p[0], $name( $p[1] ), (int) $v( $p[1], 'receptions' ) . ' rec · ' . (int) $v( $p[1], 'receiving_yards' ) . ' yds' . $td( $v( $p[1], 'receiving_tds' ) ) ); }
			}
		} else {
			$k = DBD_Data::kickoff( $g ); $d = null; try { $d = new DateTime( $g['date'] . ' 12:00' ); } catch ( Exception $e ) {}
			$s['when'] = trim( ( $g['day'] ?? '' ) . ( $d ? ' · ' . $d->format( 'M j' ) : '' ) . ' · ' . $k['et'], ' ·' );
			$s['ranks'] = array( array( $a, DBD_Data::rank( 'off', $a ), DBD_Data::rank( 'def', $h ) ), array( $h, DBD_Data::rank( 'off', $h ), DBD_Data::rank( 'def', $a ) ) );
			$s['edges'] = array();
			foreach ( array( array( $a, $h ), array( $h, $a ) ) as $od ) { $c = DBD_Takeaways::cards( $od[0], $od[1] ); if ( $c ) { $s['edges'][] = array( ( $T[ $od[0] ]['nick'] ?? $od[0] ) . ' with the ball', $c[0][3] ); } }
		}
		return $s;
	}

	public static function spec_team( $t ) {
		$T = DBD_Data::teams(); if ( ! isset( $T[ $t ] ) ) { return null; }
		$m = DBD_Data::metrics(); $pd = self::pd(); $rec = DBD_Data::records()[ $t ] ?? array( 0, 0, 0 );
		$next = null; foreach ( ( DBD_Data::schedule()['weeks'] ?? array() ) as $gl ) { foreach ( (array) $gl as $g ) { if ( ! $next && ! DBD_Router::is_final( $g ) && ( $g['away'] === $t || $g['home'] === $t ) ) { $next = $g; } } }
		$name = (string) ( $T[ $t ]['name'] ?? $t ); $nick = (string) ( $T[ $t ]['nick'] ?? $t ); $city = trim( substr( $name, 0, max( 0, strlen( $name ) - strlen( $nick ) ) ) );
		$fx = function ( $v ) { return $v === null ? '–' : ( $v > 0 ? '+' : ( $v < 0 ? '-' : '' ) ) . number_format( abs( (float) $v ), 2 ); };
		$ro = function ( $k ) use ( $t ) { return DBD_Data::rank( 'off', $t, $k ); }; $rd = function ( $k ) use ( $t ) { return DBD_Data::rank( 'def', $t, $k ); };
		$tiles = array(
			array( 'Offense', $fx( $m['off'][ $t ]['epa'] ?? null ), 'EPA a play · ' . ( $ro( 'epa' ) ? DBD_Grades::ordinal( $ro( 'epa' ) ) : '–' ) ),
			array( 'Defense', $fx( $m['defense'][ $t ]['epa'] ?? null ), 'allowed · ' . ( $rd( 'epa' ) ? DBD_Grades::ordinal( $rd( 'epa' ) ) : '–' ) ),
			array( 'Point diff', ( ( $pd[ $t ] ?? 0 ) > 0 ? '+' : '' ) . (int) ( $pd[ $t ] ?? 0 ), 'in ' . ( $rec[0] + $rec[1] + $rec[2] ) . ' games' ),
		);
		if ( $next ) { $d = null; try { $d = new DateTime( $next['date'] . ' 12:00' ); } catch ( Exception $e ) {} $tiles[] = array( 'Next', $next['home'] === $t ? $next['away'] : $next['home'], ( $next['home'] === $t ? 'home' : 'away' ) . ( $d ? ' · ' . $d->format( 'M j' ) : '' ) ); }
		else { $tiles[] = array( 'Next', '–', 'season over' ); }
		$keys = array( 'epa' => 'EPA a play', 'sr' => 'Success rate', 'expl' => 'Explosive plays', 'rz_td' => 'Red-zone TDs' );
		$off = array(); $def = array(); foreach ( $keys as $k => $lab ) { $off[] = array( $lab, $ro( $k ) ); $def[] = array( $lab, $rd( $k ) ); }
		return array( 'abbr' => $t, 'c1' => $T[ $t ]['c1'] ?? '#121417', 'c2' => $T[ $t ]['c2'] ?? '#ffffff', 'city' => $city !== '' ? $city : $nick, 'nick' => $nick,
			'kicker' => ( $T[ $t ]['div'] ?? '' ) . ' · ' . DBD_Data::record_text( $t ) . ' · ' . DBD_Grades::ordinal( self::place( $t ) ) . ' in the division',
			'tiles' => $tiles, 'off' => $off, 'def' => $def, 'season' => (int) DBD_Data::season(), 'through' => (int) DBD_Data::through_week(), 'logos' => array( $t ) );
	}

	public static function spec_page( $k ) {
		$T = DBD_Data::teams(); $season = (int) DBD_Data::season(); $cw = (int) DBD_Data::current_week(); $m = DBD_Data::metrics();
		$titles = array( 'hub' => array( 'Every snap,', 'drawn on the', '!field.' ), 'matchups' => array( 'Week ' . $cw, '!matchups' ), 'teams' => array( 'All 32', '!teams' ),
			'players' => array( 'Every', '!player' ), 'leaders' => array( 'The', '!leaders' ), 'myteam' => array( 'My', '!team' ) );
		$s = array( 'season' => $season, 'through' => (int) DBD_Data::through_week(), 'kicker' => $season . ' season · week ' . $cw, 'say' => '', 'board' => null, 'dots' => null );
		$finals = 0; $left = 0; foreach ( DBD_Data::week_games( $cw ) as $g ) { if ( DBD_Router::is_final( $g ) ) { $finals++; } else { $left++; } }
		$top = function ( $side ) use ( $m ) { $best = null; foreach ( (array) ( $m['rank'][ $side ] ?? array() ) as $t => $r ) { if ( ( $r['epa'] ?? 99 ) === 1 ) { $best = $t; } } return $best; };
		$fx = function ( $v ) { return ( $v > 0 ? '+' : ( $v < 0 ? '-' : '' ) ) . number_format( abs( (float) $v ), 2 ); };
		$to = $top( 'off' ); $td = $top( 'def' );
		$s['tiles'] = array( array( 'Finals', (string) $finals, 'week ' . $cw . ( $left ? ' · ' . $left . ' to play' : ' · all played' ) ),
			array( 'Top offense', $to ?: '–', $to ? $fx( $m['off'][ $to ]['epa'] ?? 0 ) . ' EPA a play' : '' ), array( 'Top defense', $td ?: '–', $td ? $fx( $m['defense'][ $td ]['epa'] ?? 0 ) . ' allowed' : '' ) );
		if ( preg_match( '/^week-(\d{1,2})$/', $k, $mm ) ) {
			$wk = (int) $mm[1]; $W = DBD_Week::file( $wk ); if ( ! $W ) { return null; }
			$fin = array_values( array_filter( $W['games'], function ( $g ) { return ( $g['status'] ?? '' ) === 'final'; } ) ); $n = count( $W['games'] ) - count( $fin );
			$has = function ( $g, $tag ) { foreach ( (array) ( $g['tags'] ?? array() ) as $x ) { if ( $x[0] === $tag ) { return true; } } return false; };
			$ups = array_values( array_filter( $fin, function ( $g ) use ( $has ) { return $has( $g, 'upset' ); } ) ); $cbs = array_filter( $fin, function ( $g ) use ( $has ) { return $has( $g, 'comeback' ); } ); $ots = array_filter( $fin, function ( $g ) { return ! empty( $g['ot'] ); } );
			usort( $ups, function ( $a, $b ) { return ( $a['pre_w'] ?? 999 ) <=> ( $b['pre_w'] ?? 999 ); } );
			$wild = null; foreach ( $fin as $g ) { if ( ! $wild || ( $g['drama'] ?? 0 ) > ( $wild['drama'] ?? 0 ) ) { $wild = $g; } }
			$nk = function ( $t ) use ( $T ) { return $T[ $t ]['nick'] ?? $t; };
			$plural = function ( $n, $w ) { return $n . ' ' . $w . ( $n === 1 ? '' : 's' ); };
			$s['kicker'] = $season . ' season · week ' . $wk; $s['title'] = array( 'Week ' . $wk, '!in review' );
			$s['say'] = ucfirst( $plural( count( $ups ), 'upset' ) . ', ' . $plural( count( $cbs ), 'comeback' ) . ' and ' . $plural( count( $ots ), 'overtime' ) . ': the plays that swung the week, and who moved.' );
			$wtxt = ''; if ( $wild ) { $wn = $wild['win'] ?? ''; $ln = $wn === $wild['away'] ? $wild['home'] : $wild['away']; $ws = $wn === $wild['away'] ? $wild['a'] : $wild['h']; $ls = $wn === $wild['away'] ? $wild['h'] : $wild['a']; $wtxt = array( max( $ws, $ls ) . '-' . min( $ws, $ls ), $nk( $wn ) . ' ' . $ws . ', ' . $nk( $ln ) . ' ' . $ls . ( ! empty( $wild['ot'] ) ? ' in OT' : '' ) ); }
			$s['tiles'] = array( array( 'Finals', (string) count( $fin ), $n ? $n . ' still to play' : 'all played' ), array( 'Upsets', (string) count( $ups ), $ups ? $nk( $ups[0]['win'] ) . ' from ' . round( ( $ups[0]['pre_w'] ?? 0 ) / 10 ) . '%' : 'none this week' ) );
			if ( $wtxt ) { $s['tiles'][] = array( 'Wildest', $wtxt[0], $wtxt[1] ); }
			$board = array(); $gl = $W['games']; usort( $gl, function ( $a, $b ) { return array( ( $a['status'] ?? '' ) !== 'final', $a['date'] ?? '', $a['time'] ?? '' ) <=> array( ( $b['status'] ?? '' ) !== 'final', $b['date'] ?? '', $b['time'] ?? '' ); } );
			foreach ( $gl as $g ) {
				if ( ( $g['status'] ?? '' ) !== 'final' ) { $k2 = DBD_Data::kickoff( $g ); $board[] = array( $g['away'], null, $g['home'], null, substr( (string) ( $g['day'] ?? '' ), 0, 3 ) . ' ' . str_replace( ' ET', '', $k2['et'] ), '' ); continue; }
				$board[] = array( $g['away'], (int) $g['a'], $g['home'], (int) $g['h'], ! empty( $g['ot'] ) ? 'OT' : 'Final', $g['win'] ?? '' );
			}
			$s['board'] = $board; $s['big'] = (string) $wk;
			return $s;
		}
		if ( ! isset( $titles[ $k ] ) ) { return null; }
		$s['title'] = $titles[ $k ];
		/* every pass of the current week on one field: the site's tagline, drawn (read straight from the files so the decoded plays don't stay in memory) */
		$dots = array(); $pre = $season . '_' . sprintf( '%02d', $cw ) . '_'; $dir = DBD_Data::dir();
		foreach ( array_keys( $T ) as $t ) {
			$f = $dir . '/plays/' . $t . '.json'; if ( ! is_readable( $f ) ) { continue; }
			$j = json_decode( (string) file_get_contents( $f ), true ); if ( empty( $j['keys'] ) ) { continue; } $ix = array_flip( $j['keys'] );
			foreach ( (array) $j['plays'] as $r ) {
				if ( ( $r[ $ix['pt'] ] ?? '' ) !== 'pass' || strpos( (string) ( $r[ $ix['g'] ] ?? '' ), $pre ) !== 0 ) { continue; }
				$air = $r[ $ix['air'] ] ?? null; $loc = $r[ $ix['loc'] ] ?? null; if ( $air === null || ! $loc ) { continue; }
				$dots[] = array( $loc, (float) $air, ! empty( $r[ $ix['td'] ] ) ? 2 : ( ( $r[ $ix['k'] ] ?? '' ) === 'comp' ? 1 : 0 ), crc32( implode( '|', array( $r[ $ix['g'] ], $r[ $ix['q'] ] ?? '', $r[ $ix['t'] ] ?? '' ) ) ) );
			}
			unset( $j );
		}
		$s['dots'] = $dots; $s['dots_cap'] = 'Every pass of week ' . $cw . ': ' . count( $dots ) . ' throws'; $s['big'] = '';
		return $s;
	}

	/* ================================================================== drawing: a shared kit, then the three cards */

	/** The canvas and drawing helpers every card shares: the player card's measurements, fonts and colors. */
	private static function kit( $assets ) {
		$S = self::SS; $W = self::W * $S; $H = self::H * $S; $fd = rtrim( $assets['fonts'], '/' ) . '/';
		$F = array( 'hero' => $fd . 'Tourney-ExtraBold.ttf', 'disp' => $fd . 'BarlowCondensed-Bold.ttf', 'disp2' => $fd . 'BarlowCondensed-SemiBold.ttf', 'body' => $fd . 'IBMPlexSans-Medium.ttf', 'mono' => $fd . 'IBMPlexMono-Medium.ttf', 'mono2' => $fd . 'IBMPlexMono-SemiBold.ttf' );
		foreach ( $F as $f ) { if ( ! is_readable( $f ) ) { return null; } }
		$im = imagecreatetruecolor( $W, $H ); if ( ! $im ) { return null; }
		imagealphablending( $im, true ); imagesavealpha( $im, false );
		$rgb = function ( $hex ) { $hex = ltrim( (string) $hex, '#' ); if ( strlen( $hex ) !== 6 ) { $hex = '121417'; } return array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ); };
		$col = function ( $hex ) use ( $im, $rgb ) { list( $r, $g, $b ) = $rgb( $hex ); return imagecolorallocate( $im, $r, $g, $b ); };
		$mix = function ( $hex, $a, $on = self::BG ) use ( $im, $rgb ) { list( $r, $g, $b ) = $rgb( $hex ); list( $br, $bg, $bb ) = $rgb( $on ); return imagecolorallocate( $im, (int) round( $br + ( $r - $br ) * $a ), (int) round( $bg + ( $g - $bg ) * $a ), (int) round( $bb + ( $b - $bb ) * $a ) ); };
		$pt = function ( $px ) use ( $S ) { return $px * $S * 0.75; };
		$tw = function ( $font, $px, $text ) use ( $pt, $S ) { $b = imagettfbbox( $pt( $px ), 0, $font, (string) $text ); return $b ? abs( $b[2] - $b[0] ) / $S : 0; };  /* card px */
		$txt = function ( $font, $px, $x, $y, $color, $text, $align = 'left' ) use ( $im, $pt, $S, $tw ) { $w = $align === 'left' ? 0 : $tw( $font, $px, $text ); $x0 = $align === 'right' ? $x - $w : ( $align === 'center' ? $x - $w / 2 : $x ); imagettftext( $im, $pt( $px ), 0, (int) round( $x0 * $S ), (int) round( $y * $S ), $color, $font, (string) $text ); return $w; };
		$spaced = function ( $font, $px, $x, $y, $color, $text, $ls = 0.2, $align = 'left' ) use ( $im, $pt, $tw, $S ) {
			$chars = preg_split( '//u', (string) $text, -1, PREG_SPLIT_NO_EMPTY ); $gap = $ls * $px; $total = 0; $ws = array();
			foreach ( $chars as $c ) { $w = $tw( $font, $px, $c ); $ws[] = $w; $total += $w; } $total += $gap * max( 0, count( $chars ) - 1 );
			$cx = $x - ( $align === 'right' ? $total : 0 );
			foreach ( $chars as $i => $c ) { imagettftext( $im, $pt( $px ), 0, (int) round( $cx * $S ), (int) round( $y * $S ), $color, $font, $c ); $cx += $ws[ $i ] + $gap; }
			return $total;
		};
		$rect = function ( $x, $y, $w, $h, $color ) use ( $im, $S ) { if ( $w <= 0 || $h <= 0 ) { return; } imagefilledrectangle( $im, (int) round( $x * $S ), (int) round( $y * $S ), (int) round( ( $x + $w ) * $S ) - 1, (int) round( ( $y + $h ) * $S ) - 1, $color ); };
		$K = compact( 'im', 'S', 'F', 'rgb', 'col', 'mix', 'pt', 'tw', 'txt', 'spaced', 'rect' );
		$K['bg'] = $col( self::BG ); imagefilledrectangle( $im, 0, 0, $W, $H, $K['bg'] );
		$K['ink'] = $col( self::INK ); $K['ink2'] = $col( self::INK2 ); $K['muted'] = $col( self::MUTED ); $K['red'] = $col( self::RED ); $K['good'] = $col( self::GOOD ); $K['bad'] = $col( self::BAD ); $K['panel'] = $col( self::PANEL );
		$K['line'] = $mix( '#161e36', 0.14 ); $K['line2'] = $mix( '#161e36', 0.30 ); $K['faded'] = $col( '#a7aab0' );
		$line = $K['line']; $panel = $K['panel'];
		$K['box'] = function ( $x, $y, $w, $h, $fill = null, $border = null ) use ( $rect, $line, $panel ) { $rect( $x, $y, $w, $h, $border ?? $line ); $rect( $x + 1, $y + 1, $w - 2, $h - 2, $fill ?? $panel ); };
		/* words onto lines no wider than $max card px */
		$K['wrap'] = function ( $font, $px, $text, $max ) use ( $tw ) { $out = array(); $cur = ''; foreach ( preg_split( '/\s+/', trim( (string) $text ) ) as $w ) { $try = $cur === '' ? $w : $cur . ' ' . $w; if ( $cur !== '' && $tw( $font, $px, $try ) > $max ) { $out[] = $cur; $cur = $w; } else { $cur = $try; } } if ( $cur !== '' ) { $out[] = $cur; } return $out; };
		/* the largest size up to $px at which $text fits $max */
		$K['fit'] = function ( $font, $px, $text, $max ) use ( $tw ) { $w = $tw( $font, $px, $text ); return $w > $max ? max( 10, floor( $px * $max / $w ) ) : $px; };
		/* a team's mark: its logo when logos are on and it loaded, else the drawn tile (team color, abbreviation in its ink) */
		$K['mark'] = function ( $x, $y, $size, $abbr, $bin ) use ( $im, $S, $col, $rect, $tw, $F, $pt ) {
			$lg = $bin ? @imagecreatefromstring( $bin ) : false;
			if ( $lg ) { imagealphablending( $im, true ); imagecopyresampled( $im, $lg, (int) round( $x * $S ), (int) round( $y * $S ), 0, 0, (int) round( $size * $S ), (int) round( $size * $S ), imagesx( $lg ), imagesy( $lg ) ); imagedestroy( $lg ); return; }
			$t = DBD_Data::team( $abbr ) ?: array(); $c1 = $t['c1'] ?? '#121417'; $c2 = $t['c2'] ?? '#ffffff';
			$rect( $x, $y, $size, $size, $col( $c1 ) ); $px = $size * 0.36; $w = $tw( $F['disp'], $px, $abbr );
			imagettftext( $im, $pt( $px ), 0, (int) round( ( $x + ( $size - $w ) / 2 ) * $S ), (int) round( ( $y + $size / 2 + $px * 0.36 ) * $S ), $col( DBD_Marks::ink( $c1, $c2 ) ), $F['disp'], $abbr );
		};
		/* the rank bar: red through grey to green, a dashed median, a marker; 1st sits at the green end */
		$grad = array(); $bw = 230;
		for ( $i = 0; $i <= $bw; $i++ ) { $t = $i / $bw; if ( $t < 0.5 ) { $u = $t / 0.5; $c = array( 180 + ( 107 - 180 ) * $u, 38 + ( 111 - 38 ) * $u, 31 + ( 120 - 31 ) * $u ); $a = 0.18 + ( 0.12 - 0.18 ) * $u; } else { $u = ( $t - 0.5 ) / 0.5; $c = array( 107 + ( 30 - 107 ) * $u, 111 + ( 127 - 111 ) * $u, 120 + ( 74 - 120 ) * $u ); $a = 0.12 + ( 0.22 - 0.12 ) * $u; }
			$grad[ $i ] = $mix( sprintf( '#%02x%02x%02x', (int) $c[0], (int) $c[1], (int) $c[2] ), $a ); }
		$K['rankrow'] = function ( $x, $y, $h, $lab, $sub, $rank, $color, $n = 32 ) use ( $rect, $txt, $grad, $bw, $F, $K ) {
			$rect( $x, $y, 508, 1, $K['line'] );
			$mid = $y + $h / 2;
			if ( $sub !== '' ) { $txt( $F['body'], 16, $x, $mid - 2, $K['ink'], $lab ); $txt( $F['mono'], 12, $x, $mid + 16, $K['muted'], $sub ); } else { $txt( $F['body'], 16, $x, $mid + 6, $K['ink'], $lab ); }
			$bx = $x + 200; for ( $k = 0; $k < $bw; $k++ ) { $rect( $bx + $k, $mid - 6, 1, 12, $grad[ $k ] ); }
			for ( $d = 0; $d < 18; $d += 6 ) { $rect( $bx + $bw / 2 - 1, $mid - 9 + $d, 2, 3, $K['line2'] ); }
			if ( $rank ) { $pc = ( $n - $rank ) / ( $n - 1 ); $mx = $bx + $pc * $bw; $rect( $mx - 6, $mid - 13, 12, 26, $K['bg'] ); $rect( $mx - 3, $mid - 10, 6, 20, $color ); }
			$o = $rank ? DBD_Grades::ordinal( (int) $rank ) : '–'; $c = ! $rank ? $K['muted'] : ( $rank <= 8 ? $K['good'] : ( $rank >= 25 ? $K['bad'] : $K['ink'] ) );
			$txt( $F['hero'], 23, $x + 508, $mid + 9, $c, $o, 'right' );
		};
		/* brand bar: the keyed logo, the right-hand label with any team logos before it, and the colored rule (one color, or split) */
		$K['brand'] = function ( $meta, $marks, $rule ) use ( $assets, $im, $S, $rgb, $spaced, $rect, $col, $F, $K ) {
			if ( ! empty( $assets['logo'] ) && is_readable( $assets['logo'] ) ) { $lg = @imagecreatefrompng( $assets['logo'] ); if ( $lg ) {
				$lw = imagesx( $lg ); $lh = imagesy( $lg ); $th = 34 * $S; $twd = (int) round( $lw * $th / $lh ); $sm = imagecreatetruecolor( $twd, $th ); imagefilledrectangle( $sm, 0, 0, $twd, $th, imagecolorallocate( $sm, 255, 255, 255 ) );
				imagecopyresampled( $sm, $lg, 0, 0, 0, 0, $twd, $th, $lw, $lh ); imagedestroy( $lg ); list( $br, $bgc, $bb ) = $rgb( self::BG );
				for ( $yy = 0; $yy < $th; $yy++ ) { for ( $xx = 0; $xx < $twd; $xx++ ) { $c = imagecolorat( $sm, $xx, $yy ); $r = ( $c >> 16 ) & 255; $g = ( $c >> 8 ) & 255; $b = $c & 255; $k = min( $r, $g, $b ) / 255;
					imagesetpixel( $im, 44 * $S + $xx, 15 * $S + $yy, imagecolorallocate( $im, (int) round( $r - $k * ( 255 - $br ) ), (int) round( $g - $k * ( 255 - $bgc ) ), (int) round( $b - $k * ( 255 - $bb ) ) ) ); } }
				imagedestroy( $sm ); } }
			$x = 1156 - $spaced( $F['disp'], 16, 1156, 38, $K['ink2'], strtoupper( $meta ), 0.2, 'right' ) - 14;
			foreach ( array_reverse( $marks ) as $mk ) { if ( empty( $mk[1] ) ) { continue; } $x -= 36; ( $K['mark'] )( $x, 14, 36, $mk[0], $mk[1] ); $x -= 6; }
			$rule = (array) $rule; $n = count( $rule ); foreach ( $rule as $i => $hex ) { $rect( self::W * $i / $n, 64, self::W / $n, 6, $col( $hex ) ); }
		};
		$K['foot'] = function ( $right ) use ( $rect, $txt, $F, $K ) { $rect( 0, 586, self::W, 1, $K['line'] ); $txt( $F['mono2'], 13, 44, 613, $K['ink'], 'downbydown.com' ); $txt( $F['mono'], 13, 1156, 613, $K['muted'], $right, 'right' ); };
		$K['finish'] = function () use ( $im, $W, $H ) { $out = imagecreatetruecolor( self::W, self::H ); imagecopyresampled( $out, $im, 0, 0, 0, 0, self::W, self::H, $W, $H ); imagedestroy( $im ); return $out; };
		/* a tile: label, value, sub line (wrapped to two lines) */
		$K['tile'] = function ( $x, $y, $w, $h, $lab, $val, $sub, $vfont = 'hero', $vpx = 34, $mark = null ) use ( $spaced, $txt, $F, $K ) {
			( $K['box'] )( $x, $y, $w, $h );
			$spaced( $F['disp'], 12, $x + 14, $y + 26, $K['muted'], strtoupper( $lab ), 0.16 );
			$px = ( $K['fit'] )( $F[ $vfont ], $vpx, $val, $w - 28 ); $txt( $F[ $vfont ], $px, $x + 14, $y + 30 + $vpx, $K['ink'], $val );
			$sx = $x + 14; if ( $mark ) { ( $K['mark'] )( $sx, $y + $h - 30, 18, $mark[0], $mark[1] ); $sx += 24; }
			$lines = ( $K['wrap'] )( $F['mono'], 12, $sub, $x + $w - 14 - $sx ); $lines = array_slice( $lines, 0, 2 );
			foreach ( $lines as $i => $l ) { $txt( $F['mono'], 12, $sx, $y + $h - 16 - ( count( $lines ) - 1 - $i ) * 16, $K['muted'], $l ); }
		};
		return $K;
	}

	private static function nick( $t ) { $x = DBD_Data::team( (string) $t ); return $x['nick'] ?? (string) $t; }

	/** One team row on a game card: mark, nickname, record line, score. */
	private static function team_row( $K, $y, $t, $bin, $score, $state ) {
		$F = $K['F']; $c = $state === 'lose' ? $K['faded'] : $K['ink'];
		( $K['box'] )( 44, $y + 9, 86, 86 ); ( $K['mark'] )( 52, $y + 17, 70, $t['abbr'], $bin );
		$nick = strtoupper( $t['nick'] ); $px = ( $K['fit'] )( $F['hero'], 46, $nick, $score === null ? 448 : 330 );
		( $K['txt'] )( $F['hero'], $px, 148, $y + 58, $c, $nick );
		( $K['txt'] )( $F['mono'], 13, 148, $y + 84, $K['muted'], $t['rec'] );
		if ( $score !== null ) { ( $K['txt'] )( $F['hero'], 80, 600, $y + 82, $c, (string) $score, 'right' ); }
		( $K['rect'] )( 44, $y + 104, 556, 1, $K['line'] );
	}

	public static function draw_game( $s, $assets ) {
		$K = self::kit( $assets ); if ( ! $K ) { return null; } $F = $K['F']; $L = $assets['logos'];
		$a = $s['away']; $h = $s['home'];
		$meta = $s['season'] . ' · week ' . $s['week'] . ' · ' . ( $s['final'] ? 'final' . ( ! empty( $s['ot'] ) ? ' / OT' : '' ) : 'preview' );
		( $K['brand'] )( $meta, array( array( $a['abbr'], $L[ $a['abbr'] ] ?? null ), array( $h['abbr'], $L[ $h['abbr'] ] ?? null ) ), array( $a['c1'], $h['c1'] ) );
		if ( $s['final'] ) {
			$kick = 'Week ' . $s['week'] . ( $s['stadium'] !== '' ? ' · ' . $s['stadium'] : '' ) . ( $s['line'] !== '' ? ' · ' . $s['line'] : '' );
			( $K['spaced'] )( $F['disp'], 14, 44, 124, $K['muted'], strtoupper( $kick ), 0.2 );
			$win = $s['win'];
			self::team_row( $K, 136, $a, $L[ $a['abbr'] ] ?? null, $s['as'], $win === '' ? '' : ( $win === $a['abbr'] ? 'win' : 'lose' ) );
			self::team_row( $K, 240, $h, $L[ $h['abbr'] ] ?? null, $s['hs'], $win === '' ? '' : ( $win === $h['abbr'] ? 'win' : 'lose' ) );
			$x = 44; foreach ( $s['tags'] as $tg ) { $lab = strtoupper( $tg[1] ); $w = ( $K['tw'] )( $F['disp'], 14, $lab ) + strlen( $lab ) * 14 * 0.14 + 20; if ( $x + $w > 600 ) { break; }
				$up = $tg[0] === 'upset'; ( $K['box'] )( $x, 368, $w, 30, null, $up ? $K['red'] : $K['line2'] ); ( $K['spaced'] )( $F['disp'], 14, $x + 10, 389, $up ? $K['red'] : $K['ink2'], $lab, 0.14 ); $x += $w + 8; }
			$wn = $win === $a['abbr'] ? $a : $h;
			( $K['spaced'] )( $F['disp'], 14, 648, 119, $K['muted'], strtoupper( 'Win probability · ' . ( $win ? $wn['nick'] : 'tied' ) ), 0.2 );
			( $K['box'] )( 648, 134, 508, 212 );
			$px0 = 694; $px1 = 1140; $py0 = 150; $py1 = 330; $yv = function ( $v ) use ( $py0, $py1 ) { return $py1 - ( $v / 1000 ) * ( $py1 - $py0 ); };
			foreach ( array( array( '100%', $py0 ), array( '50%', ( $py0 + $py1 ) / 2 ), array( '0%', $py1 ) ) as $ax ) { ( $K['txt'] )( $F['mono'], 11, 686, $ax[1] + 4, $K['muted'], $ax[0], 'right' ); }
			for ( $x = $px0; $x < $px1; $x += 8 ) { ( $K['rect'] )( $x, ( $py0 + $py1 ) / 2, 4, 1, $K['line2'] ); }
			$sp = $s['spark']; $n = count( $sp );
			if ( $n > 1 ) {
				$wc = ( $K['col'] )( $wn['c1'] ); $fill = ( $K['mix'] )( $wn['c1'], 0.08, self::PANEL ); $S = $K['S']; $im = $K['im'];
				$pts = array(); foreach ( $sp as $i => $v ) { $pts[] = array( $px0 + ( $px1 - $px0 ) * $i / ( $n - 1 ), $yv( $v ) ); }
				$poly = array(); foreach ( $pts as $p ) { $poly[] = (int) round( $p[0] * $S ); $poly[] = (int) round( $p[1] * $S ); } $poly[] = (int) round( $px1 * $S ); $poly[] = (int) round( $py1 * $S ); $poly[] = (int) round( $px0 * $S ); $poly[] = (int) round( $py1 * $S );
				imagefilledpolygon( $im, $poly, $fill );
				imagesetthickness( $im, (int) round( 3.5 * $S ) ); for ( $i = 1; $i < $n; $i++ ) { imageline( $im, (int) round( $pts[ $i - 1 ][0] * $S ), (int) round( $pts[ $i - 1 ][1] * $S ), (int) round( $pts[ $i ][0] * $S ), (int) round( $pts[ $i ][1] * $S ), $wc ); } imagesetthickness( $im, 1 );
				$j = 1; for ( $i = 2; $i < $n; $i++ ) { if ( abs( $sp[ $i ] - $sp[ $i - 1 ] ) > abs( $sp[ $j ] - $sp[ $j - 1 ] ) ) { $j = $i; } }
				$dx = $pts[ $j ][0]; $dy = $pts[ $j ][1];
				imagefilledellipse( $im, (int) round( $dx * $S ), (int) round( $dy * $S ), 22 * $S, 22 * $S, $K['panel'] ); imagefilledellipse( $im, (int) round( $dx * $S ), (int) round( $dy * $S ), 15 * $S, 15 * $S, $wc );
				if ( $s['big'] ) { $lab = $s['big'][1] . ' · ' . self::nick( $s['big'][2] ?? '' ) . ' +' . (int) $s['big'][0] . '%'; $lw = ( $K['tw'] )( $F['mono2'], 13, $lab ) + 14; $lx = min( max( $dx - $lw / 2, $px0 ), $px1 - $lw ); $ly = max( $dy - 40, 138 );
					( $K['rect'] )( $lx, $ly, $lw, 24, $K['ink'] ); ( $K['txt'] )( $F['mono2'], 13, $lx + 7, $ly + 17, ( $K['col'] )( '#ffffff' ), $lab ); }
			}
			if ( $s['low'] !== null && $win ) { $cap = $wn['nick'] . ' were down to ' . round( $s['low'] ) . '%' . ( $s['big'] ? ' · biggest swing ' . $s['big'][1] . ', ' . self::nick( $s['big'][2] ?? '' ) . ' +' . (int) $s['big'][0] . '%' : '' ); ( $K['txt'] )( $F['body'], 15, 648, 372, $K['ink2'], $cap ); }
			$tw3 = ( 1112 - 24 ) / 3;
			foreach ( array_slice( $s['leaders'], 0, 3 ) as $i => $ld ) { ( $K['tile'] )( 44 + $i * ( $tw3 + 12 ), 440, $tw3, 120, $ld[0], strtoupper( $ld[2] ), $ld[3], 'disp2', 30, array( $ld[1], $L[ $ld[1] ] ?? null ) ); }
			( $K['foot'] )( 'Data: nflverse · win probability from the play-by-play' );
		} else {
			( $K['spaced'] )( $F['disp'], 14, 44, 124, $K['muted'], strtoupper( $s['when'] ?? '' ), 0.2 );
			self::team_row( $K, 132, $a, $L[ $a['abbr'] ] ?? null, null, '' );
			( $K['spaced'] )( $F['disp'], 14, 148, 258, $K['muted'], 'AT', 0.2 );
			self::team_row( $K, 262, $h, $L[ $h['abbr'] ] ?? null, null, '' );
			$ln = trim( $s['line'] . ( $s['stadium'] !== '' ? ' · ' . $s['stadium'] : '' ), ' ·' ); if ( $ln !== '' ) { ( $K['txt'] )( $F['mono2'], 16, 44, 410, $K['ink2'], $ln ); }
			( $K['spaced'] )( $F['disp'], 14, 648, 119, $K['muted'], 'THE MATCHUP · EPA A PLAY, RANKS AMONG 32', 0.2 );
			$y = 132; $T = DBD_Data::teams();
			foreach ( $s['ranks'] as $i => $rk ) {
				list( $o, $ro, $rd ) = $rk; $d = $o === $a['abbr'] ? $h['abbr'] : $a['abbr'];
				( $K['spaced'] )( $F['disp'], 14, 648, $y + 22, $K['ink'], strtoupper( ( $T[ $o ]['nick'] ?? $o ) . ' with the ball' ), 0.16 ); $y += 30;
				( $K['rankrow'] )( 648, $y, 56, ( $T[ $o ]['nick'] ?? $o ) . ' offense', 'EPA a play', $ro, ( $K['col'] )( $T[ $o ]['c1'] ?? '#121417' ) ); $y += 56;
				( $K['rankrow'] )( 648, $y, 56, ( $T[ $d ]['nick'] ?? $d ) . ' defense', 'EPA a play allowed', $rd, ( $K['col'] )( $T[ $d ]['c1'] ?? '#121417' ) ); $y += 60;
			}
			foreach ( array_slice( $s['edges'], 0, 2 ) as $i => $e ) {
				$x = 44 + $i * 562; ( $K['box'] )( $x, 440, 550, 120 ); ( $K['rect'] )( $x, 440, 4, 120, $i ? $K['line2'] : $K['red'] );
				( $K['spaced'] )( $F['disp'], 12, $x + 18, 464, $K['muted'], strtoupper( $e[0] ), 0.16 );
				foreach ( array_slice( ( $K['wrap'] )( $F['body'], 16, $e[1], 510 ), 0, 3 ) as $j => $l ) { ( $K['txt'] )( $F['body'], 16, $x + 18, 490 + $j * 22, $K['ink'], $l ); }
			}
			( $K['foot'] )( 'Data: nflverse · ranks through week ' . (int) DBD_Data::through_week() );
		}
		return ( $K['finish'] )();
	}

	public static function draw_team( $s, $assets ) {
		$K = self::kit( $assets ); if ( ! $K ) { return null; } $F = $K['F']; $L = $assets['logos'];
		( $K['brand'] )( $s['season'] . ' season · through week ' . $s['through'], array( array( $s['abbr'], $L[ $s['abbr'] ] ?? null ) ), $s['c1'] );
		( $K['box'] )( 44, 106, 160, 160 ); ( $K['mark'] )( 58, 120, 132, $s['abbr'], $L[ $s['abbr'] ] ?? null );
		( $K['spaced'] )( $F['disp'], 14, 228, 146, $K['muted'], strtoupper( $s['kicker'] ), 0.2 );
		$px = min( ( $K['fit'] )( $F['hero'], 46, strtoupper( $s['city'] ), 372 ), ( $K['fit'] )( $F['hero'], 46, strtoupper( $s['nick'] ), 372 ) );
		( $K['txt'] )( $F['hero'], $px, 228, 206, $K['ink'], strtoupper( $s['city'] ) );
		( $K['txt'] )( $F['hero'], $px, 228, 206 + $px * 1.12, $K['red'], strtoupper( $s['nick'] ) );
		foreach ( $s['tiles'] as $i => $t ) { ( $K['tile'] )( 44 + $i * 143, 306, 131, 112, $t[0], $t[1], $t[2], 'hero', 30 ); }
		( $K['spaced'] )( $F['disp'], 14, 648, 119, $K['muted'], 'WHERE THEY RANK · AMONG 32 TEAMS', 0.2 );
		$color = ( $K['col'] )( $s['c1'] ); $y = 128;
		( $K['spaced'] )( $F['disp'], 14, 648, $y + 18, $K['ink'], 'OFFENSE', 0.16 ); $y += 26;
		foreach ( $s['off'] as $r ) { ( $K['rankrow'] )( 648, $y, 46, $r[0], '', $r[1], $color ); $y += 46; }
		( $K['spaced'] )( $F['disp'], 14, 648, $y + 24, $K['ink'], 'DEFENSE · 1ST ALLOWS THE LEAST', 0.16 ); $y += 32;
		foreach ( $s['def'] as $r ) { ( $K['rankrow'] )( 648, $y, 46, $r[0], '', $r[1], $color ); $y += 46; }
		( $K['foot'] )( 'Data: nflverse · ranks among 32 teams' );
		return ( $K['finish'] )();
	}

	public static function draw_page( $s, $assets ) {
		$K = self::kit( $assets ); if ( ! $K ) { return null; } $F = $K['F'];
		( $K['brand'] )( $s['season'] . ' season · through week ' . $s['through'], array(), self::RED );
		( $K['spaced'] )( $F['disp'], 14, 44, 124, $K['red'], strtoupper( $s['kicker'] ), 0.2 );
		$lines = $s['title']; $n = count( $lines ); $px = $n >= 3 ? 56 : 72;
		foreach ( $lines as $l ) { $px = min( $px, ( $K['fit'] )( $F['hero'], $px, strtoupper( ltrim( $l, '!' ) ), 610 ) ); }
		$y = 132 + $px * 0.92; $last = $y;
		foreach ( $lines as $l ) { $red = $l[0] === '!'; ( $K['txt'] )( $F['hero'], $px, 44, $y, $red ? $K['red'] : $K['ink'], strtoupper( ltrim( $l, '!' ) ) ); $last = $y; $y += $px * 1.0; }
		if ( $s['say'] !== '' ) { foreach ( array_slice( ( $K['wrap'] )( $F['body'], 18, $s['say'], 600 ), 0, 2 ) as $j => $l ) { ( $K['txt'] )( $F['body'], 18, 44, $last + 48 + $j * 26, $K['ink2'], $l ); } }
		$tw3 = ( 610 - 24 ) / 3;
		foreach ( $s['tiles'] as $i => $t ) { ( $K['tile'] )( 44 + $i * ( $tw3 + 12 ), 430, $tw3, 120, $t[0], $t[1], $t[2], 'hero', 34 ); }
		/* the field */
		$fx = 700; $fy = 100; $fw = 456; $fh = 458; $green = ( $K['col'] )( '#2c4a35' );
		( $K['rect'] )( $fx, $fy, $fw, $fh, $green );
		for ( $i = 1; $i < 10; $i++ ) { ( $K['rect'] )( $fx, $fy + $fh * $i / 10, $fw, $i === 5 ? 2 : 1, ( $K['mix'] )( '#ffffff', $i === 5 ? 0.26 : 0.15, '#2c4a35' ) ); }
		if ( $s['big'] !== '' ) { $bp = strlen( $s['big'] ) <= 2 ? 300 : 150; ( $K['txt'] )( $F['hero'], $bp, $fx + $fw - 22, $fy + $fh + 10, ( $K['mix'] )( '#ffffff', 0.07, '#2c4a35' ), $s['big'], 'right' ); }
		if ( $s['board'] ) {
			$rows = $s['board']; $n = count( $rows ); $step = ( $fh - 32 ) / max( 1, $n ); $w = ( $K['col'] )( '#ffffff' ); $dim = ( $K['mix'] )( '#ffffff', 0.62, '#2c4a35' ); $dim2 = ( $K['mix'] )( '#ffffff', 0.5, '#2c4a35' ); $gold = ( $K['col'] )( '#ffd166' );
			foreach ( $rows as $i => $r ) { $y = $fy + 16 + $step * $i + $step * 0.68;
				$aw = $r[5] === $r[0]; $hw = $r[5] === $r[2];
				( $K['txt'] )( $F['disp'], 22, $fx + 22, $y, $aw ? $w : $dim, $r[0] ); if ( $r[1] !== null ) { ( $K['txt'] )( $F['mono2'], 20, $fx + 140, $y, $aw ? $w : $dim, (string) $r[1], 'right' ); }
				( $K['txt'] )( $F['disp'], 22, $fx + 176, $y, $hw ? $w : $dim, $r[2] ); if ( $r[3] !== null ) { ( $K['txt'] )( $F['mono2'], 20, $fx + 294, $y, $hw ? $w : $dim, (string) $r[3], 'right' ); }
				( $K['txt'] )( $F['mono'], 13, $fx + $fw - 22, $y, $r[1] === null ? $gold : $dim2, $r[4], 'right' ); }
		} elseif ( $s['dots'] ) {
			$cols = array( 'left' => array( 0, 1 / 3 ), 'middle' => array( 1 / 3, 2 / 3 ), 'right' => array( 2 / 3, 1 ) ); $pal = array( ( $K['mix'] )( '#ffffff', 0.35, '#2c4a35' ), ( $K['col'] )( '#5b9cf6' ), ( $K['col'] )( '#4fd18b' ) );
			$x0 = $fx + 14; $w0 = $fw - 28; $y0 = $fy + 14; $h0 = $fh - 54; $S = $K['S']; $im = $K['im'];
			usort( $s['dots'], function ( $a, $b ) { return $a[2] <=> $b[2]; } );  /* touchdowns on top */
			foreach ( $s['dots'] as $d ) { if ( ! isset( $cols[ $d[0] ] ) ) { continue; } list( $c0, $c1 ) = $cols[ $d[0] ]; $j = ( $d[3] % 1000 ) / 1000;
				$x = $x0 + $w0 * ( $c0 + 0.03 + $j * ( $c1 - $c0 - 0.06 ) ); $y = $y0 + $h0 * ( 0.78 - max( -8, min( 45, $d[1] ) ) * 0.0155 );
				imagefilledellipse( $im, (int) round( $x * $S ), (int) round( $y * $S ), 7 * $S, 7 * $S, $pal[ $d[2] ] ); }
			( $K['txt'] )( $F['mono'], 13, $fx + 16, $fy + $fh - 14, ( $K['mix'] )( '#ffffff', 0.7, '#2c4a35' ), $s['dots_cap'] );
		}
		( $K['foot'] )( 'Data: nflverse' );
		return ( $K['finish'] )();
	}
}
