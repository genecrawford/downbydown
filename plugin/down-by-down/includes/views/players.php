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

/* ---- movers (SCOPE §22): who moved in the last full week, against his own season. From players/movers.json; an older data file skips the panel. */
$MV = DBD_Data::get( 'players/movers.json' );
if ( $MV && ! empty( $MV['week'] ) ) {
	$teams = DBD_Data::teams(); $nick = function ( $a ) use ( $teams ) { return $teams[ $a ]['nick'] ?? $a; };
	$boards = array(
		array( 'snaps', 'Snap share', 'points of the snaps', 'pts', function ( $v ) { return round( $v ) . '%'; } ),
		array( 'targets', 'Target share', 'points of team targets, QBs excluded', 'pts', function ( $v ) { return round( $v ) . '%'; } ),
		array( 'epa', 'EPA', 'the week against his per-game average', 'EPA', function ( $v ) { return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 1 ); } ),
	);
	$spark = function ( $s, $up ) {
		$v = array_values( array_filter( (array) $s, function ( $x ) { return $x !== null; } ) ); if ( ! $v ) { return ''; }
		$hi = max( $v ); $lo = min( $v ); $sp = ( $hi - $lo ) ?: 1; $Wd = 72; $Hh = 22; $n = count( $s );
		$x = function ( $i ) use ( $n, $Wd ) { return $n > 1 ? round( 2 + $i * ( $Wd - 4 ) / ( $n - 1 ), 1 ) : $Wd / 2; }; $y = function ( $val ) use ( $hi, $sp, $Hh ) { return round( 2 + ( $hi - $val ) / $sp * ( $Hh - 4 ), 1 ); };
		$pts = array(); foreach ( $s as $i => $val ) { if ( $val !== null ) { $pts[] = $x( $i ) . ',' . $y( $val ); } }
		$li = $n - 1; $last = $s[ $li ];
		return '<svg viewBox="0 0 ' . $Wd . ' ' . $Hh . '" aria-hidden="true"><polyline points="' . implode( ' ', $pts ) . '" fill="none" stroke="var(--dbd-muted)" stroke-width="1.5"/>' . ( $last === null ? '' : '<circle cx="' . $x( $li ) . '" cy="' . $y( $last ) . '" r="2.5" fill="' . ( $up ? 'var(--dbd-good)' : 'var(--dbd-bad)' ) . '"/>' ) . '</svg>';
	};
	$mrow = function ( $r, $up, $b ) use ( $MV, $spark, $nick ) {
		$fmt = $b[4]; $href = DBD_Router::url( array( 'player' => $r['id'] ) );
		$tip = '<b>' . esc_html( $r['name'] ) . '</b> · ' . esc_html( $nick( $r['team'] ) ) . '<br>Week ' . (int) $MV['week'] . ': ' . esc_html( $b[1] ) . ' <b>' . esc_html( $fmt( $r['now'] ) ) . '</b>, before ' . esc_html( $fmt( $r['before'] ) ) . ' over ' . (int) $r['g'] . ' game' . ( (int) $r['g'] === 1 ? '' : 's' );
		$d = (float) $r['delta']; $dtxt = ( $d > 0 ? '+' : '−' ) . ( $b[3] === 'pts' ? round( abs( $d ) ) : number_format( abs( $d ), 1 ) ) . ' ' . $b[3];
		return '<a class="dbd-mvr ' . ( $up ? 'up' : 'dn' ) . '" href="' . esc_url( $href ) . '" data-team="' . esc_attr( $r['team'] ) . '" data-pos="' . esc_attr( $r['pos'] ) . '" data-name="' . esc_attr( strtolower( $r['name'] ) ) . '" data-tip="' . esc_attr( $tip ) . '">' . DBD_Marks::player( $r['team'], $r['num'], 24, $r['photo'] ?? null ) . '<span class="nm"><b>' . esc_html( $r['name'] ) . '</b><small>' . esc_html( $r['team'] . ' · ' . $r['pos'] ) . '</small></span>' . $spark( $r['s'] ?? array(), $up ) . '<span class="ch"><b>' . esc_html( $dtxt ) . '</b><small>' . esc_html( $fmt( $r['before'] ) . ' → ' . $fmt( $r['now'] ) ) . '</small></span></a>';
	};
	/* the read: the snap board's top climber and faller */
	$u = $MV['snaps']['up'][0] ?? null; $dn = $MV['snaps']['down'][0] ?? null; $wkn = (int) $MV['week'];
	if ( $u && $dn && $u['team'] === $dn['team'] ) { $say = 'Week ' . $wkn . ' was a changing of the guard in ' . esc_html( $nick( $u['team'] ) ) . ' ' . ( $u['pos'] === 'QB' ? 'quarterbacking' : 'snaps' ) . ': <b>' . esc_html( $u['name'] ) . '</b> took ' . round( $u['now'] ) . '% of the snaps, up ' . round( $u['delta'] ) . ' points on his season, while <b>' . esc_html( $dn['name'] ) . '</b> fell to ' . round( $dn['now'] ) . '%.'; }
	elseif ( $u && $dn ) { $say = 'The biggest swing in snaps in Week ' . $wkn . ': <b>' . esc_html( $u['name'] ) . '</b> (' . esc_html( $nick( $u['team'] ) ) . ') took ' . round( $u['now'] ) . '% of the snaps, up ' . round( $u['delta'] ) . ' points on his season; <b>' . esc_html( $dn['name'] ) . '</b> (' . esc_html( $nick( $dn['team'] ) ) . ') fell to ' . round( $dn['now'] ) . '% from ' . round( $dn['before'] ) . '.'; }
	else { $say = 'Week ' . $wkn . ': ' . (int) $MV['n'] . ' players took a snap; no snap share moved past the floor.'; }
	echo '<section class="dbd-panel dbd-mv" id="dbd-mv"><div class="dbd-ph"><span class="dbd-lab"><b>Movers</b> · Week ' . $wkn . ' · against his own season</span><span class="dbd-badge">' . (int) $MV['n'] . ' played</span></div>';
	echo '<p class="dbd-mv-say">' . $say . '</p><div class="dbd-mv3">';
	foreach ( $boards as $b ) {
		$up = ''; foreach ( $MV[ $b[0] ]['up'] ?? array() as $r ) { $up .= $mrow( $r, true, $b ); }
		$down = ''; foreach ( $MV[ $b[0] ]['down'] ?? array() as $r ) { $down .= $mrow( $r, false, $b ); }
		echo '<div class="dbd-mvb"><h4>' . esc_html( $b[1] ) . '<small>' . esc_html( $b[2] ) . '</small></h4>' . ( $up || $down ? ( $up ? '<div class="dbd-mvg up">▲ Climbers</div>' . $up : '' ) . ( $down ? '<div class="dbd-mvg dn">▼ Fallers</div>' . $down : '' ) : '<p class="none">Nobody moved far on this board.</p>' ) . '</div>';
	}
	echo '</div>';
	if ( ! empty( $MV['faces'] ) ) {
		echo '<div class="dbd-mvf"><span class="l">New faces</span>';
		foreach ( $MV['faces'] as $r ) { echo '<a href="' . esc_url( DBD_Router::url( array( 'player' => $r['id'] ) ) ) . '" data-team="' . esc_attr( $r['team'] ) . '" data-pos="' . esc_attr( $r['pos'] ) . '" data-name="' . esc_attr( strtolower( $r['name'] ) ) . '">' . DBD_Marks::player( $r['team'], $r['num'], 22, $r['photo'] ?? null ) . esc_html( $r['name'] ) . '<small>' . esc_html( $r['team'] . ' · ' . $r['pos'] . ' · ' . ( ( $r['kind'] ?? '' ) === 'debut' ? 'debut' : 'first start-level week' ) . ', ' . round( $r['now'] ) . '% of the snaps' ) . '</small></a>'; }
		echo '</div>';
	}
	echo '<p class="dbd-note">Each row compares his last full week with his average over the games he had played before it, so a mover broke from his own pattern. Boards list the ' . DBD_Grades::MV_PER . ' biggest climbers, then the ' . DBD_Grades::MV_PER . ' biggest fallers, past a floor (' . DBD_Grades::MV_FLOOR_SNAPS . ' points of snap share, ' . DBD_Grades::MV_FLOOR_TARGETS . ' of target share, ' . number_format( DBD_Grades::MV_FLOOR_EPA, 1 ) . ' EPA). The sparkline is his season; the dot is that week. New faces took their first snap that week with ' . DBD_Grades::MV_DEBUT . '% or more, or passed ' . DBD_Grades::MV_START . '% for the first time.</p></section>';
}

/* ---- filters: team tiles, position switch, search. All real links; JS takes over once loaded. */
echo '<section class="dbd-panel"><div class="dbd-toolbar dbd-ptools">';
echo '<div class="dbd-fg"><span>Team</span><div class="dbd-teampick" id="dbd-teampick"><a class="dbd-mkbtn' . ( $team ? '' : ' on' ) . '" href="' . esc_url( $purl( $pos ? array( 'pos' => $pos ) : array() ) ) . '" data-team="" title="All teams">ALL</a>';
foreach ( array_keys( DBD_Data::teams() ) as $a ) {
	echo '<a class="dbd-mkbtn' . ( $a === $team ? ' on' : '' ) . '" href="' . esc_url( $purl( array_filter( array( 'team' => $a, 'pos' => $pos ) ) ) ) . '" data-team="' . esc_attr( $a ) . '" title="' . esc_attr( DBD_Data::team( $a )['name'] ) . '">' . DBD_Marks::tile( $a, 32 ) . '</a>';
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
	if ( $team && $q === '' && $p['team'] !== $team ) { return false; }  // a search spans every team
	if ( $pos && $p['pos'] !== $pos && ! ( $pos === 'RB' && $p['pos'] === 'FB' ) ) { return false; }
	if ( $q !== '' && stripos( $p['name'], $q ) === false && (string) $p['num'] !== $q && strcasecmp( $p['team'], $q ) !== 0 && ( empty( $p['col'] ) || stripos( $p['col'], $q ) === false ) ) { return false; }
	return true;
} ) );
$cap = $team || $q !== '' ? 0 : 60;
if ( $cap && count( $rows ) > $cap ) { $rows = array_slice( $rows, 0, $cap ); }
$lab = $q !== '' ? '<b>Search</b> · ' . esc_html( $q ) . ' · all teams' : ( $team ? '<b>Roster usage</b> · sorted by snap share' : '<b>League leaders</b> · top ' . count( $rows ) . ' by snap share' );
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab" id="dbd-plab">' . $lab . '</span><span class="dbd-badge">Click a row</span></div><div class="dbd-tw"><table class="dbd-plist dbd-stat dbd-sortable" id="dbd-plist"><thead><tr><th data-sort="name">Player</th><th data-sort="team" class="dbd-t2">Team</th><th data-sort="pos">Pos</th><th data-sort="snap" class="dbd-t2">Snap %</th><th data-sort="use">Tgt / Att</th><th data-sort="rec" class="dbd-t2">Rec</th><th data-sort="car" class="dbd-t2">Car</th><th data-sort="yds">Yds</th><th data-sort="td">TD</th><th data-sort="epa">EPA</th><th data-sort="st" class="dbd-t3">Status</th></tr></thead><tbody>';
ob_start();
foreach ( $rows as $p ) {
	$href = DBD_Router::url( array( 'player' => $p['id'] ) ); $st = (string) ( $p['st'] ?? '' );
	$cls  = preg_match( '/out|did not/i', $st ) ? ' o' : ( preg_match( '/questionable|limited|doubtful/i', $st ) ? ' q' : '' );
	$lbl  = preg_match( '/did not/i', $st ) ? 'DNP' : ( preg_match( '/limited/i', $st ) ? 'Limited' : ( preg_match( '/full/i', $st ) ? 'Full' : $st ) );
	echo '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::player( $p['team'], $p['num'], 34, $p['photo'] ?? null ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $p['name'] ) . '</a>' . ( ! empty( $p['slot'] ) ? '<small>' . esc_html( $p['slot'] ) . '</small>' : '' ) . ( $st !== '' ? '<i class="dbd-stdot' . $cls . '" data-tip="' . esc_attr( $lbl . ( ! empty( $p['what'] ) ? ' · ' . $p['what'] : '' ) ) . '" tabindex="0" aria-label="' . esc_attr( $lbl ) . '"></i>' : '' ) . '</span></td>';
	echo '<td class="dbd-ptm">' . DBD_Marks::tile( $p['team'], 20 ) . '</td><td class="dbd-ppos">' . esc_html( $p['pos'] ) . '</td><td class="dbd-psnap"><span class="dbd-sb"><i style="--w:' . (int) $p['snap'] . '%"></i></span>' . (int) $p['snap'] . '%</td>';
	$use = $p['pos'] === 'QB' ? ( $p['att'] ?? 0 ) : $p['tgt'];
	echo '<td>' . ( $use ?: '' ) . '</td><td>' . ( $p['rec'] ?: '' ) . '</td><td>' . ( $p['car'] ?: '' ) . '</td><td>' . (int) $p['yds'] . '</td><td>' . ( $p['td'] ?: '' ) . '</td><td class="' . ( $p['epa'] >= 0 ? 'pos' : 'neg' ) . '">' . ( $p['epa'] > 0 ? '+' : '' ) . number_format( (float) $p['epa'], 1 ) . '</td>';
	echo '<td>' . ( $st !== '' ? '<span class="dbd-st' . $cls . '">' . esc_html( $lbl . ( ! empty( $p['what'] ) ? ' · ' . $p['what'] : '' ) ) . '</span>' : '' ) . '</td></tr>';
}
if ( ! $rows ) { echo '<tr><td colspan="11" class="dbd-note">No players match.</td></tr>'; }
echo DBD_Router::tierize( ob_get_clean(), array( null, 'dbd-t2', null, 'dbd-t2', null, 'dbd-t2', 'dbd-t2', null, null, null, 'dbd-t3' ) );
echo '</tbody></table></div><p class="dbd-note">Snap share is the season average of offensive snaps played. Status is the latest practice or game report; blank means no listing. Quarterbacks show pass attempts in the Tgt / Att column. Search matches name, number, team abbreviation or college across every team. Click a column heading to sort.</p></section>';
echo '</main>';
echo '<script type="application/json" id="dbd-pindex">' . wp_json_encode( $idx ) . '</script>';
