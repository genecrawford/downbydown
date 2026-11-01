<?php
/**
 * A team's players (SCOPE §32): its own page, not the league index with a filter. The lineup from the depth chart
 * (offense, defense, special teams), every skill player with a snap grouped by position, and this team's movers only.
 * Data: lineups.json, players/{TEAM}.json, players/movers.json. Without lineups.json yet, the lineup panel is skipped.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $team = $r['pteam']; $t = DBD_Data::team( $team ); $season = DBD_Data::season(); $through = DBD_Data::through_week();
$pl = DBD_Data::team_players( $team ); $all = count( DBD_Data::player_index() );
$purl = DBD_Router::url( array( 'view' => 'players' ) ); $turl = DBD_Router::url( array( 'team' => $team ) );

echo DBD_Router::topbar( array( 'players' => $purl, $t['nick'] => null ) );
echo '<main class="dbd-content" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">';

echo '<div class="dbd-ttitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( $through ? "$season season · through week $through" : "$season season" ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::pennant( $team, 48 ) . '<span><em>' . esc_html( $t['nick'] ) . '</em> players</span></h1>';
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">The ' . esc_html( $t['nick'] ) . '\' lineup from the depth chart, every skill player\'s season, and who moved this week. <a href="' . esc_url( $turl ) . '">Open the ' . esc_html( $t['nick'] ) . ' offense explorer</a>.</p></div>';
echo '<a class="dbd-badge" href="' . esc_url( $purl ) . '">All ' . (int) $all . ' players</a></div>';

/* ---- the lineup: one tabbed panel, a board per unit */
$luO = DBD_Lineup::unit( $team, 'off' ); $luD = DBD_Lineup::unit( $team, 'def' ); $luS = DBD_Lineup::special( $team ); $lu = DBD_Data::lineup( $team );
if ( $luO || $luD ) {
	$tabs = array(); if ( $luO ) { $tabs[] = array( 'Offense · ' . ( $lu['grp']['off'] ?? '' ), $luO ); } if ( $luD ) { $tabs[] = array( 'Defense · ' . str_replace( 'Base ', '', (string) ( $lu['grp']['def'] ?? '' ) ), $luD ); } if ( $luS ) { $tabs[] = array( 'Special teams', $luS ); }
	echo '<section class="dbd-panel dbd-tabbed dbd-lu"><div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>';
	foreach ( $tabs as $i => $x ) { echo '<button class="t-tab" type="button" role="tab" aria-selected="' . ( $i ? 'false' : 'true' ) . '" data-pane="' . $i . '">' . esc_html( $x[0] ) . '</button>'; }
	echo '</div></div>';
	foreach ( $tabs as $i => $x ) { echo '<div class="dbd-pane" data-pane="' . $i . '"' . ( $i ? ' hidden' : '' ) . '>' . $x[1] . '</div>'; }
	echo '<p class="dbd-note">Starters from the ' . esc_html( $t['nick'] ) . '\' current depth chart (nflverse, from ESPN). Hover a player for his season; skill players open their page. The nickel back, the extra defensive back most teams use against three receivers, is drawn lighter.</p></section>';
}

/* ---- the roster, grouped by position */
if ( ! $pl ) { echo '<div class="dbd-empty"><h2>No player data yet</h2><p>Player pages fill in with the next nightly data build.</p></div></main>'; return; }
$S = function ( $p, $k ) { $s = 0; foreach ( $p['weeks'] ?? array() as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s; };
$snapAvg = function ( $p ) { return ! empty( $p['snaps'] ) ? (int) round( array_sum( $p['snaps'] ) / count( $p['snaps'] ) ) : 0; };
$groups = array( 'QB' => 'Quarterbacks', 'RB' => 'Running backs', 'WR' => 'Wide receivers', 'TE' => 'Tight ends' );
$body = '';
foreach ( $groups as $gk => $gl ) {
	$rows = array_values( array_filter( $pl, function ( $p ) use ( $gk ) { return $p['pos'] === $gk || ( $gk === 'RB' && $p['pos'] === 'FB' ); } ) );
	if ( ! $rows ) { continue; }
	usort( $rows, function ( $a, $b ) use ( $snapAvg ) { return $snapAvg( $b ) <=> $snapAvg( $a ); } );
	$body .= '<tbody><tr class="gh"><th colspan="9">' . esc_html( $gl ) . '</th></tr>';
	foreach ( $rows as $p ) {
		$use = $gk === 'QB' ? $S( $p, 'attempts' ) : $S( $p, 'targets' ); $yds = $S( $p, 'receiving_yards' ) + $S( $p, 'rushing_yards' ) + $S( $p, 'passing_yards' );
		$td = $S( $p, 'receiving_tds' ) + $S( $p, 'rushing_tds' ) + $S( $p, 'passing_tds' ); $ep = $S( $p, 'receiving_epa' ) + $S( $p, 'rushing_epa' ) + $S( $p, 'passing_epa' );
		$slot = ! empty( $p['depth'] ) ? $p['depth']['slot'] . $p['depth']['rank'] : ''; $st = $p['inj']['status'] ?? '';
		$stc = preg_match( '/out|did not/i', $st ) ? ' o' : ( preg_match( '/limited|questionable|doubtful/i', $st ) ? ' q' : '' );
		$href = DBD_Router::url( array( 'player' => $p['id'] ) );
		$body .= '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::player( $team, $p['num'] ?? null, 30, $p['photo'] ?? null ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $p['name'] ) . '</a>' . ( $slot ? '<small>' . esc_html( $slot ) . '</small>' : '' ) . '</span></td>'
			. '<td>' . $snapAvg( $p ) . '%</td><td>' . (int) $use . '</td><td>' . ( $gk === 'QB' ? '' : (int) $S( $p, 'receptions' ) ) . '</td><td>' . ( (int) $S( $p, 'carries' ) ?: '' ) . '</td><td>' . (int) round( $yds ) . '</td><td>' . ( (int) $td ?: '' ) . '</td>'
			. '<td class="' . ( $ep >= 0 ? 'pos' : 'neg' ) . '">' . ( $ep > 0 ? '+' : '' ) . number_format( $ep, 1 ) . '</td><td>' . ( $st ? '<span class="dbd-st' . $stc . '">' . esc_html( $st . ( ! empty( $p['inj']['what'] ) ? ' · ' . $p['inj']['what'] : '' ) ) . '</span>' : '' ) . '</td></tr>';
	}
	$body .= '</tbody>';
}
echo '<section class="dbd-panel dbd-roster"><div class="dbd-ph"><span class="dbd-lab"><b>The roster</b> · every skill player with a snap this season</span><span class="dbd-badge">' . count( $pl ) . ' players</span></div>';
echo '<div class="dbd-tw"><table class="dbd-stat dbd-rost"><thead><tr><th>Player</th><th>Snap</th><th>Tgt/Att</th><th>Rec</th><th>Car</th><th>Yds</th><th>TD</th><th>EPA</th><th>Status</th></tr></thead>' . $body . '</table></div></section>';

/* ---- this team's movers only (the league's are on the Players index) */
$MV = DBD_Data::get( 'players/movers.json' );
if ( $MV && ! empty( $MV['week'] ) ) {
	$what = array( 'snaps' => 'snap share', 'targets' => 'target share', 'epa' => 'EPA' ); $li = '';
	foreach ( $what as $k => $lab ) { foreach ( array( 'up', 'down' ) as $d ) { foreach ( (array) ( $MV[ $k ][ $d ] ?? array() ) as $m ) { if ( ( $m['team'] ?? '' ) !== $team ) { continue; }
		$li .= '<li><a href="' . esc_url( DBD_Router::url( array( 'player' => $m['id'] ) ) ) . '">' . esc_html( $m['name'] ) . '</a> <span class="' . ( $d === 'up' ? 'up' : 'dn' ) . '">' . ( $m['delta'] > 0 ? '+' : '' ) . number_format( (float) $m['delta'], 1 ) . '</span> <small>' . esc_html( $lab ) . ', ' . number_format( (float) $m['before'], 1 ) . ' → ' . number_format( (float) $m['now'], 1 ) . '</small></li>'; } } }
	foreach ( (array) ( $MV['faces'] ?? array() ) as $m ) { if ( ( $m['team'] ?? '' ) === $team ) { $li .= '<li><a href="' . esc_url( DBD_Router::url( array( 'player' => $m['id'] ) ) ) . '">' . esc_html( $m['name'] ) . '</a> <small>' . esc_html( $m['kind'] . ', ' . (int) $m['now'] . '% of the snaps' ) . '</small></li>'; } }
	echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>' . esc_html( $t['nick'] ) . ' movers</b> · Week ' . (int) $MV['week'] . ' against his own season</span></div>';
	echo $li ? '<ul class="dbd-mvlist">' . $li . '</ul>' : '<p class="dbd-wp-say">No ' . esc_html( $t['nick'] ) . ' player moved enough in Week ' . (int) $MV['week'] . ' to make the league\'s movers. <a href="' . esc_url( $purl ) . '">See every team\'s movers</a>.</p>';
	echo '</section>';
}
echo '</main>';
