<?php
/** The slate: one table of game rows for a week. Expects $games (array) in scope. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$last_day = null;
echo '<div class="dbd-tw"><table class="dbd-slate">';
foreach ( $games as $g ) {
	$day = $g['day'] ?? '';
	if ( $day !== $last_day ) {
		$date = '';
		try { $date = ( new DateTime( $g['date'] ) )->format( 'M j' ); } catch ( Exception $e ) {}
		echo '<tr class="dbd-day"><td colspan="7">' . esc_html( $day . ', ' . $date ) . '</td></tr>';
		$last_day = $day;
	}
	$f  = DBD_Router::is_final( $g );
	$aw = $f && $g['away_score'] > $g['home_score'];
	$hw = $f && $g['home_score'] > $g['away_score'];
	$href = DBD_Router::url( array( 'game' => $g['id'] ) );
	$tm = function ( $t ) {
		$team = DBD_Data::team( $t );
		return '<td><div class="dbd-tm">' . DBD_Marks::tile( $t, 28 ) . '<b>' . esc_html( $t ) . '</b><span>' . esc_html( $team['nick'] ?? '' ) . '</span></div></td>';
	};
	echo '<tr class="dbd-g" data-href="' . esc_url( $href ) . '">';
	echo '<td class="dbd-when">' . ( $f ? '<span class="dbd-badge fin">Final</span>' : DBD_Router::kickoff_html( $g ) ) . '</td>';
	echo $tm( $g['away'] ) . '<td class="dbd-num ' . ( $f && ! $aw ? 'l' : '' ) . '">' . ( $f ? (int) $g['away_score'] : '' ) . '</td>';
	echo '<td class="dbd-at">at</td>';
	echo $tm( $g['home'] ) . '<td class="dbd-num ' . ( $f && ! $hw ? 'l' : '' ) . '">' . ( $f ? (int) $g['home_score'] : '' ) . '</td>';
	$line = DBD_Router::line_text( $g );
	echo '<td class="dbd-ln">' . ( $line ? '<b>' . esc_html( $line ) . '</b>' : '' ) . ( isset( $g['total'] ) && $g['total'] !== null ? ' · O/U ' . esc_html( $g['total'] ) : '' ) . '</td>';
	echo '<td><a class="dbd-go" href="' . esc_url( $href ) . '">' . ( $f ? 'Recap' : 'Matchup' ) . '</a></td>';
	echo '</tr>';
}
if ( ! $games ) { echo '<tr><td class="dbd-note">No games this week.</td></tr>'; }
echo '</table></div>';
