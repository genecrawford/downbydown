<?php
/** One conference standings table with rank bars. Expects $conf ('AFC'|'NFC') in scope. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$teams   = DBD_Data::teams();
$byes    = array_flip( DBD_Data::byes( DBD_Data::current_week() ) );
$ranked  = DBD_Data::through_week() > 0;
$tier    = function ( $r ) { return $r <= 10 ? 'g' : ( $r >= 23 ? 'b' : 'm' ); };
$rb      = function ( $r ) use ( $tier ) {
	if ( ! $r ) { return '<span class="dbd-note">–</span>'; }
	return '<div class="dbd-rb"><b>' . (int) $r . '</b><i class="' . $tier( $r ) . '" style="--w:' . round( ( 33 - $r ) / 32 * 100 ) . '%"></i></div>';
};
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $conf ) . '</b> · EPA per play, ranked 1 to 32</span><span class="dbd-badge">Off · Def</span></div><div class="dbd-tw"><table class="dbd-stand"><colgroup><col><col class="c-rec"><col class="c-rk"><col class="c-rk"></colgroup>';
foreach ( array( 'East', 'North', 'South', 'West' ) as $d ) {
	$div = "$conf $d";
	$rows = array_filter( $teams, function ( $t ) use ( $div ) { return ( $t['div'] ?? '' ) === $div; } );
	uksort( $rows, function ( $a, $b ) {
		$c = DBD_Data::win_pct( $b ) <=> DBD_Data::win_pct( $a );
		return $c ?: ( (int) DBD_Data::rank( 'off', $a ) ?: 99 ) <=> ( (int) DBD_Data::rank( 'off', $b ) ?: 99 );
	} );
	echo '<tr class="dbd-dv"><td colspan="2">' . esc_html( $div ) . '</td><td class="rh">Off</td><td class="rh">Def</td></tr>';
	foreach ( $rows as $a => $t ) {
		$href = DBD_Router::url( array( 'team' => $a ) );
		echo '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::tile( $a, 24 ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $t['name'] ) . '</a><small>' . esc_html( $a ) . '</small></span></td>';
		echo '<td class="dbd-rec">' . esc_html( DBD_Data::record_text( $a ) ) . ( isset( $byes[ $a ] ) ? '<i>Bye</i>' : '' ) . '</td>';
		echo '<td class="dbd-rk-cell">' . ( $ranked ? $rb( DBD_Data::rank( 'off', $a ) ) : '' ) . '</td><td class="dbd-rk-cell">' . ( $ranked ? $rb( DBD_Data::rank( 'def', $a ) ) : '' ) . '</td></tr>';
	}
}
echo '</table></div></section>';
