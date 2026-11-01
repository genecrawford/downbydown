<?php
/**
 * Share card (SCOPE §26): a 1200 × 630 PNG of a player's hero, season line and grade bars, drawn with GD and
 * FreeType from the plugin's bundled fonts. spec() and draw() are pure PHP so tools/card_harness.php can render
 * one outside WordPress; serve() is the WordPress side (route, cache in uploads, headers).
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Card {
	const W = 1200; const H = 630; const SS = 2;  /* drawn at 2× and downsampled for smooth edges */
	const BG = '#f4f1ea'; const PANEL = '#fffdf8'; const INK = '#121417'; const INK2 = '#3b4255'; const MUTED = '#6b6f78'; const RED = '#cb2026'; const GOOD = '#1e7f4a'; const BAD = '#b4261f';

	public static function available() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' ) && function_exists( 'imagepng' ) && function_exists( 'imagecopyresampled' );
	}

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

	/** The route: render (once per player, data week and plugin version), cache under uploads, stream. Exits. */
	public static function serve( $id ) {
		$fail = function ( $code ) { status_header( $code ); nocache_headers(); exit; };
		if ( ! self::available() || ! preg_match( '/^\d{2}-\d{7}$/', $id ) ) { $fail( 404 ); }
		$row = DBD_Data::player( $id ); if ( ! $row ) { $fail( 404 ); }
		$rec = DBD_Data::player_record( $row['team'], $id ); if ( ! $rec ) { $fail( 404 ); }
		$through = (int) DBD_Data::through_week(); $up = wp_upload_dir(); $dir = trailingslashit( $up['basedir'] ) . 'down-by-down/cards'; wp_mkdir_p( $dir );
		$logo = DBD_Data::logo( $row['team'] );
		$file = $dir . '/' . $id . '-' . (int) DBD_Data::season() . '-' . $through . '-' . DBD_VERSION . ( $logo ? '-l' : '' ) . '.png';  /* turning logos on or off draws a fresh card */  /* season, data week and plugin version: a new year or a new design never serves an old card */
		if ( ! file_exists( $file ) ) {
			$team = DBD_Data::team( $row['team'] ); $grp = DBD_Grades::in_use( $rec['grades']['pool'] ?? DBD_Grades::pool_of( $row['pos'] ) ); $gi = $grp ? ( DBD_Grades::group_info( $grp ) ?: array() ) : array(); $gi['_pool'] = $grp;  /* SCOPE §38: the player's own pool, or the combined one an older file has */
			$spec = self::spec( $rec, $row, $team, $gi, DBD_Data::season(), $through ); if ( ! $spec ) { $fail( 404 ); }
			$photo = null;
			if ( ! empty( $rec['photo'] ) ) { $r = wp_remote_get( $rec['photo'], array( 'timeout' => 5 ) ); if ( ! is_wp_error( $r ) && (int) wp_remote_retrieve_response_code( $r ) === 200 ) { $photo = wp_remote_retrieve_body( $r ); } }
			$tlogo = null; if ( $logo ) { $r2 = wp_remote_get( $logo, array( 'timeout' => 5 ) ); if ( ! is_wp_error( $r2 ) && (int) wp_remote_retrieve_response_code( $r2 ) === 200 ) { $tlogo = wp_remote_retrieve_body( $r2 ); } }
			$im = self::draw( $spec, array( 'fonts' => DBD_PATH . 'assets/fonts/', 'logo' => DBD_PATH . 'assets/card-logo.png', 'photo' => $photo, 'team_logo' => $tlogo ) ); if ( ! $im ) { $fail( 500 ); }
			/* write beside the final name, then rename into place, so a request arriving mid-write never streams a half-written PNG */
			$tmp = $dir . '/.' . wp_generate_password( 12, false ) . '.tmp'; $ok = imagepng( $im, $tmp, 6 ); imagedestroy( $im );
			if ( ! $ok || ! file_exists( $tmp ) || filesize( $tmp ) < 1000 || ! @rename( $tmp, $file ) ) { @unlink( $tmp ); $fail( 500 ); }
		}
		header( 'Content-Type: image/png' ); header( 'Content-Length: ' . filesize( $file ) ); header( 'Cache-Control: public, max-age=86400' ); header( 'X-Content-Type-Options: nosniff' );
		readfile( $file ); exit;
	}
}
