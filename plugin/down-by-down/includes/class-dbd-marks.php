<?php
/**
 * Team marks: the jersey tile and the pennant, generated from a team's two colors and abbreviation.
 * Inline SVG, no image files, no league artwork. The same rules live in assets/marks.js for the explorer.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class DBD_Marks {

	private static function lum( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( strlen( $hex ) !== 6 ) { return 0; }
		$c = array();
		foreach ( array( 0, 2, 4 ) as $i ) {
			$v = hexdec( substr( $hex, $i, 2 ) ) / 255;
			$c[] = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	}

	public static function contrast( $a, $b ) {
		$x = self::lum( $a ); $y = self::lum( $b );
		return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
	}

	/** Text color inside a mark: the second color if it reads on the first, else white, else near-black. */
	public static function ink( $c1, $c2 ) {
		if ( self::contrast( $c2, $c1 ) >= 3 ) { return $c2; }
		if ( self::contrast( '#ffffff', $c1 ) >= 3 ) { return '#ffffff'; }
		return '#121417';
	}

	/** The team's primary color if it reads on a light surface, else its secondary, else ink. */
	public static function accent( $team, $surface = '#fffdf8' ) {
		if ( ! $team ) { return '#121417'; }
		if ( self::contrast( $team['c1'], $surface ) >= 2.5 ) { return $team['c1']; }
		if ( ! empty( $team['c2'] ) && self::contrast( $team['c2'], $surface ) >= 2.5 ) { return $team['c2']; }
		return '#121417';
	}

	private static function color( $c, $fallback ) {
		return preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $c ) ? $c : $fallback;
	}

	public static function tile( $abbr, $size = 24 ) {
		$svg = self::tile_svg( $abbr, $size );
		$logo = $svg ? DBD_Data::logo( $abbr ) : null;
		return $logo ? self::with_logo( $svg, $logo, $size, $size, DBD_Data::team( $abbr )['name'] ?? $abbr, DBD_Data::team( $abbr )['logo_s'] ?? 1 ) : $svg;
	}

	/** The real logo over the drawn mark: the mark shows until the image loads, then hides; a failed image removes itself (SCOPE §28). */
	public static function with_logo( $svg, $logo, $w, $h, $alt, $scale = 1 ) {
		$sc = max( 0.7, min( 1.3, (float) $scale ) );  /* optical scale from the build (1.4.1), clamped */
		return '<span class="dbd-lgw" style="width:' . (int) $w . 'px;height:' . (int) $h . 'px;--lgs:' . round( $sc, 2 ) . '">' . $svg . '<img class="dbd-logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $alt ) . '" width="' . (int) $h . '" height="' . (int) $h . '" loading="lazy" decoding="async" referrerpolicy="no-referrer" onload="this.previousElementSibling.style.visibility=\'hidden\'" onerror="this.remove()"></span>';
	}

	public static function tile_svg( $abbr, $size = 24 ) {
		$t = DBD_Data::team( $abbr );
		if ( ! $t ) { return ''; }
		$c1 = self::color( $t['c1'], '#161e36' ); $c2 = self::color( $t['c2'], '#cb2026' );
		$fs = strlen( $abbr ) > 2 ? 13 : 16;
		return sprintf(
			'<svg class="dbd-mk" width="%1$d" height="%1$d" viewBox="0 0 40 40" role="img" aria-label="%2$s"><rect width="40" height="40" rx="4" fill="%3$s"/><rect y="33" width="40" height="7" fill="%4$s"/><text x="20" y="25" text-anchor="middle" font-size="%5$d" font-family="Tourney,Arial Black,sans-serif" font-weight="800" fill="%6$s">%7$s</text></svg>',
			(int) $size, esc_attr( $t['name'] ), esc_attr( $c1 ), esc_attr( $c2 ), $fs, esc_attr( self::ink( $c1, $c2 ) ), esc_html( $abbr )
		);
	}

	/** The player mark: a jersey tile with the number in the team's colors. No headshots, same rule as logos. */
	public static function jersey( $abbr, $num, $size = 40 ) {
		$t = DBD_Data::team( $abbr );
		if ( ! $t ) { return ''; }
		$c1 = self::color( $t['c1'], '#161e36' ); $c2 = self::color( $t['c2'], '#cb2026' ); $ink = self::ink( $c1, $c2 );
		$n  = $num === null || $num === '' ? '' : (string) (int) $num;
		$fs = strlen( $n ) > 1 ? 19 : 21;
		return sprintf(
			'<svg class="dbd-mk dbd-jersey" width="%1$d" height="%1$d" viewBox="0 0 40 40" role="img" aria-label="%2$s"><rect width="40" height="40" rx="5" fill="%3$s"/><rect x="2.5" y="2.5" width="35" height="35" rx="3.5" fill="none" stroke="%4$s" stroke-opacity=".5" stroke-width="1.5"/><text x="20" y="27" text-anchor="middle" font-size="%5$d" font-family="Tourney,Arial Black,sans-serif" font-weight="800" fill="%4$s">%6$s</text></svg>',
			(int) $size, esc_attr( $t['nick'] . ' #' . $n ), esc_attr( $c1 ), esc_attr( $ink ), $fs, esc_html( $n )
		);
	}

	/** The player mark: the jersey tile, and when headshots are on and a URL exists, the photo lazily on top.
	 *  The image removes itself if it fails to load, leaving the tile. */
	public static function player( $abbr, $num, $size = 40, $photo = null ) {
		$tile = self::jersey( $abbr, $num, $size );
		if ( ! $tile || ! $photo || ! DBD_Data::headshots() || ! preg_match( '#^https://[^"\'<>\s]+$#', $photo ) ) { return $tile; }
		$t = DBD_Data::team( $abbr ); $c1 = self::color( $t['c1'], '#161e36' );
		return '<span class="dbd-head" style="width:' . (int) $size . 'px;height:' . (int) $size . 'px">' . $tile . '<img src="' . esc_url( self::sized( $photo, $size ) ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy" decoding="async" referrerpolicy="no-referrer" style="background:' . esc_attr( $c1 ) . '" onerror="this.remove()"></span>';
	}

	/** The hero cutout on a player page: the headshot at display height, fitted (not cropped) so the transparent
	 *  background stays, over a soft team-color glow. Nothing is printed when photos are off or the URL is missing. */
	public static function hero_photo( $abbr, $photo, $height = 190 ) {
		if ( ! $photo || ! DBD_Data::headshots() || ! preg_match( '#^https://[^"\'<>\s]+$#', $photo ) ) { return ''; }
		$src = preg_replace( '#^(https://static\.www\.nfl\.com/image/upload/)f_auto,q_auto/#', '${1}f_auto,q_auto,h_' . ( (int) $height * 2 ) . ',c_fit/', $photo, 1 );
		return '<figure class="dbd-herophoto" aria-hidden="true"><img src="' . esc_url( $src ) . '" alt="" height="' . (int) $height . '" decoding="async" referrerpolicy="no-referrer" onerror="this.parentNode.remove()"></figure>';
	}

	/** The NFL image CDN takes Cloudinary transforms: ask for a face-cropped square at 2x the display size
	 *  (about 6 KB) instead of the 3,400 px original (about 4 MB). Other hosts are left alone. */
	public static function sized( $photo, $size ) {
		$n = (int) $size * 2;
		return preg_replace( '#^(https://static\.www\.nfl\.com/image/upload/)f_auto,q_auto/#', '${1}f_auto,q_auto,w_' . $n . ',h_' . $n . ',c_fill,g_face/', $photo, 1 );
	}

	public static function pennant( $abbr, $height = 40 ) {
		$svg = self::pennant_svg( $abbr, $height );
		$logo = $svg ? DBD_Data::logo( $abbr ) : null;
		return $logo ? self::with_logo( $svg, $logo, (int) round( $height * 1.6 ), $height, DBD_Data::team( $abbr )['name'] ?? $abbr, DBD_Data::team( $abbr )['logo_s'] ?? 1 ) : $svg;
	}

	public static function pennant_svg( $abbr, $height = 40 ) {
		$t = DBD_Data::team( $abbr );
		if ( ! $t ) { return ''; }
		$c1 = self::color( $t['c1'], '#161e36' ); $c2 = self::color( $t['c2'], '#cb2026' );
		$fs = strlen( $abbr ) > 2 ? 13 : 15;
		return sprintf(
			'<svg class="dbd-mk dbd-pn" width="%1$d" height="%2$d" viewBox="0 0 64 40" role="img" aria-label="%3$s"><polygon points="0,0 64,20 0,40" fill="%4$s"/><polygon points="0,0 9,2.8 9,37.2 0,40" fill="%5$s"/><text x="14" y="25.5" font-size="%6$d" font-family="Tourney,Arial Black,sans-serif" font-weight="800" fill="%7$s">%8$s</text></svg>',
			(int) round( $height * 1.6 ), (int) $height, esc_attr( $t['name'] ), esc_attr( $c1 ), esc_attr( $c2 ), $fs, esc_attr( self::ink( $c1, $c2 ) ), esc_html( $abbr )
		);
	}
}
