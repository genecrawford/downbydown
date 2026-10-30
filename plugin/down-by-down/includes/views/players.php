<?php
/**
 * Players index: every skill player with an offensive snap. Server renders the chosen team's rows (or the
 * league leaders by snap share); the full index rides along as JSON so the filters work without a round trip.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $season = DBD_Data::season(); $through = DBD_Data::through_week();
$team = $r['pteam']; $pos = $r['pos']; $q = trim( $r['q'] );
$idx  = DBD_Data::player_index();
$t    = $team ? DBD_Data::team( $team ) : null;
$purl = function ( $args = array() ) { return DBD_Router::url( array_merge( array( 'view' => 'players' ), $args ) ); };

$crumbs = $team ? array( 'players' => $purl(), $t['nick'] => null ) : array( 'players' => null );
echo DBD_Router::topbar( $crumbs );
echo '<main class="dbd-content"' . ( $t ? ' style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '"' : '' ) . '>';

echo '<div class="dbd-ttitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( $through ? "$season season · offense · through week $through" : "$season season · offense" ) . '</p>';
if ( $t ) { echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::pennant( $team, 48 ) . '<span><em>' . esc_html( $t['nick'] ) . '</em> players</span></h1>'; }
else { echo '<h1 class="t-stagger-line t-stagger-line--2">Pick a <em>player</em></h1>'; }
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">Every skill player who has taken an offensive snap this season, with usage from the play file and this week\'s injury report. Pick one to see where the ball finds him.</p></div>';
echo '<div class="dbd-badge" id="dbd-pcount">' . count( $idx ) . ' players</div></div>';

if ( ! $idx ) {
	echo '<div class="dbd-empty"><h2>No player data yet</h2><p>Player pages fill in with the next nightly data build.</p></div></main>';
	return;
}

/* ---- filters: team tiles, position switch, search. All real links; JS takes over once loaded. */
echo '<section class="dbd-panel"><div class="dbd-toolbar dbd-ptools">';
echo '<div class="dbd-fg"><span>Team</span><div class="dbd-teampick" id="dbd-teampick"><a class="dbd-mkbtn' . ( $team ? '' : ' on' ) . '" href="' . esc_url( $purl( $pos ? array( 'pos' => $pos ) : array() ) ) . '" data-team="" title="All teams">ALL</a>';
foreach ( array_keys( DBD_Data::teams() ) as $a ) {
	echo '<a class="dbd-mkbtn' . ( $a === $team ? ' on' : '' ) . '" href="' . esc_url( $purl( array_filter( array( 'team' => $a, 'pos' => $pos ) ) ) ) . '" data-team="' . esc_attr( $a ) . '" title="' . esc_attr( DBD_Data::team( $a )['name'] ) . '">' . DBD_Marks::tile( $a, 24 ) . '</a>';
}
echo '</div></div>';
echo '<div class="dbd-fg"><span>Position</span><div class="dbd-seg t-tabs" id="dbd-pos" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>';
foreach ( array( '' => 'All', 'QB' => 'QB', 'RB' => 'RB', 'WR' => 'WR', 'TE' => 'TE' ) as $k => $lab ) {
	echo '<a class="t-tab" role="tab" href="' . esc_url( $purl( array_filter( array( 'team' => $team, 'pos' => $k ) ) ) ) . '" data-v="' . esc_attr( $k ) . '" aria-selected="' . ( $pos === $k ? 'true' : 'false' ) . '">' . $lab . '</a>';
}
echo '</div></div>';
echo '<div class="dbd-fg"><label for="dbd-pq">Search</label><input class="dbd-srch" id="dbd-pq" type="search" value="' . esc_attr( $q ) . '" placeholder="Name, number or college" autocomplete="off"></div>';
echo '</div></section>';

/* ---- rows: this team, or the league leaders */
$rows = array_values( array_filter( $idx, function ( $p ) use ( $team, $pos, $q ) {
	if ( $team && $p['team'] !== $team ) { return false; }
	if ( $pos && $p['pos'] !== $pos && ! ( $pos === 'RB' && $p['pos'] === 'FB' ) ) { return false; }
	if ( $q !== '' && stripos( $p['name'], $q ) === false && (string) $p['num'] !== $q && strcasecmp( $p['team'], $q ) !== 0 && ( empty( $p['col'] ) || stripos( $p['col'], $q ) === false ) ) { return false; }
	return true;
} ) );
$cap = $team || $q !== '' ? 0 : 60;
if ( $cap && count( $rows ) > $cap ) { $rows = array_slice( $rows, 0, $cap ); }
$lab = $team ? '<b>Roster usage</b> · sorted by snap share' : ( $q !== '' ? '<b>Search</b> · ' . esc_html( $q ) : '<b>League leaders</b> · top ' . count( $rows ) . ' by snap share' );
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab" id="dbd-plab">' . $lab . '</span><span class="dbd-badge">Click a row</span></div><div class="dbd-tw"><table class="dbd-plist" id="dbd-plist"><thead><tr><th>Player</th><th>Team</th><th>Pos</th><th>Snap %</th><th>Tgt / Att</th><th>Rec</th><th>Car</th><th>Yds</th><th>TD</th><th>EPA</th><th>Status</th></tr></thead><tbody>';
foreach ( $rows as $p ) {
	$href = DBD_Router::url( array( 'player' => $p['id'] ) ); $st = (string) ( $p['st'] ?? '' );
	$cls  = preg_match( '/out|did not/i', $st ) ? ' o' : ( preg_match( '/questionable|limited|doubtful/i', $st ) ? ' q' : '' );
	$lbl  = preg_match( '/did not/i', $st ) ? 'DNP' : ( preg_match( '/limited/i', $st ) ? 'Limited' : ( preg_match( '/full/i', $st ) ? 'Full' : $st ) );
	echo '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::jersey( $p['team'], $p['num'], 26 ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $p['name'] ) . '</a>' . ( ! empty( $p['slot'] ) ? '<small>' . esc_html( $p['slot'] ) . '</small>' : '' ) . '</span></td>';
	echo '<td class="dbd-ptm">' . DBD_Marks::tile( $p['team'], 20 ) . '</td><td class="dbd-ppos">' . esc_html( $p['pos'] ) . '</td><td class="dbd-psnap"><span class="dbd-sb"><i style="--w:' . (int) $p['snap'] . '%"></i></span>' . (int) $p['snap'] . '%</td>';
	$use = $p['pos'] === 'QB' ? ( $p['att'] ?? 0 ) : $p['tgt'];
	echo '<td>' . ( $use ?: '' ) . '</td><td>' . ( $p['rec'] ?: '' ) . '</td><td>' . ( $p['car'] ?: '' ) . '</td><td>' . (int) $p['yds'] . '</td><td>' . ( $p['td'] ?: '' ) . '</td><td class="' . ( $p['epa'] >= 0 ? 'pos' : 'neg' ) . '">' . ( $p['epa'] > 0 ? '+' : '' ) . number_format( (float) $p['epa'], 1 ) . '</td>';
	echo '<td>' . ( $st !== '' ? '<span class="dbd-st' . $cls . '">' . esc_html( $lbl . ( ! empty( $p['what'] ) ? ' · ' . $p['what'] : '' ) ) . '</span>' : '' ) . '</td></tr>';
}
if ( ! $rows ) { echo '<tr><td colspan="11" class="dbd-note">No players match.</td></tr>'; }
echo '</tbody></table></div><p class="dbd-note">Snap share is the season average of offensive snaps played. Status is the latest practice or game report; blank means no listing. Quarterbacks show pass attempts in the Tgt / Att column. Search matches name, number, team abbreviation or college.</p></section>';
echo '</main>';
echo '<script type="application/json" id="dbd-pindex">' . wp_json_encode( $idx ) . '</script>';
