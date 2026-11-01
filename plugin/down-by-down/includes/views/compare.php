<?php
/**
 * Compare (SCOPE §17): two or three players of one position group side by side. PHP renders the slots, the
 * overlaid league percentiles, the week-aligned log (one table per metric) and the season table from the player
 * files; compare.js adds the slot search, the tray, the metric switch and the field maps.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $rows = $r['compare']; $grp = $r['cgroup']; $season = DBD_Data::season();
$COLS = array( '#161e36', '#cb2026', '#d99a06' );
$purl = DBD_Router::url( array( 'view' => 'players' ) );
$curl = function ( $ids ) { return $ids ? DBD_Router::url( array( 'compare' => implode( ',', $ids ) ) ) : DBD_Router::url( array( 'view' => 'players' ) ); };
$ids  = array_map( function ( $p ) { return $p['id']; }, $rows );
$P    = array();
foreach ( $rows as $i => $row ) { $P[] = array( 'row' => $row, 'rec' => DBD_Data::player_record( $row['team'], $row['id'] ) ?: array( 'weeks' => array(), 'snaps' => array(), 'grades' => null ), 'c' => $COLS[ $i ], 't' => DBD_Data::team( $row['team'] ) ); }
$n  = count( $P ); $gi = $grp ? DBD_Grades::group_info( $grp ) : null; $N = (int) ( $gi['n'] ?? 0 );
$pool = $grp ? DBD_Grades::pool_label( $grp ) : 'players'; $one = array( 'rec' => 'receiver or tight end', 'rb' => 'back', 'qb' => 'quarterback' )[ $grp ] ?? 'player';
$ordn = array( 'DBD_Grades', 'ordinal' ); $pct = function ( $v ) { return $v === null ? '-' : round( $v * 100 ) . '%'; }; $sgn = function ( $v, $d = 1 ) { return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, $d ); };
$low = $gi['low'] ?? array(); $better = function ( $k, $a, $b ) use ( $low ) { if ( $a === null ) { return false; } if ( $b === null ) { return true; } return in_array( $k, $low, true ) ? $a < $b : $a > $b; };

echo DBD_Router::topbar( array( 'players' => $purl, 'compare' => null ) );
echo '<main class="dbd-content dbd-cmp" style="--n:' . max( 1, $n ) . '">';
echo '<div class="dbd-ttitle"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · compare" . ( $grp ? ' · ' . $pool : '' ) ) . '</p><h1 class="t-stagger-line t-stagger-line--2"><em>Compare</em> players</h1>';
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">Two or three players of the same position, side by side: where each one grades against the league, where the ball finds each of them, and how their weeks line up.' . ( ! empty( $r['cmixed'] ) ? ' <b>Players from another position group were left out.</b>' : '' ) . '</p></div>';
echo '<div class="dbd-badge">' . ( $N ? $N . ' in the league pool' : 'pick a player to start' ) . '</div></div>';

/* ---- who */
$reset = DBD_Router::url( array( 'compare' => 'none' ) );
$actions = '<span class="dbd-cactions"><button type="button" class="dbd-cbtn dbd-ccopy" title="Copy the link to this comparison">Copy link</button><a class="dbd-cbtn dbd-creset" href="' . esc_url( $reset ) . '" title="Clear these players and start a new comparison">Start over</a></span>';
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Who</b> · up to three, same position group</span>' . ( $n ? $actions : '' ) . '</div><div class="dbd-cslots">';
for ( $i = 0; $i < 3; $i++ ) {
	if ( isset( $P[ $i ] ) ) {
		$x = $P[ $i ]; $rowx = $x['row']; $rm = array_values( array_diff( $ids, array( $rowx['id'] ) ) );
		echo '<div class="dbd-cslot" style="--c:' . $x['c'] . '">' . DBD_Marks::player( $rowx['team'], $rowx['num'], 40, $rowx['photo'] ?? null ) . '<div class="who"><b><a href="' . esc_url( DBD_Router::url( array( 'player' => $rowx['id'] ) ) ) . '">' . esc_html( $rowx['name'] ) . '</a></b><small>' . esc_html( $rowx['pos'] . ' · ' . ( $x['t']['nick'] ?? $rowx['team'] ) . ' · ' . ( DBD_Grades::qualifies( $rowx ) ? 'graded' : 'not yet qualified' ) ) . '</small></div>' . ( $n > 1 ? '<a class="x" href="' . esc_url( $curl( $rm ) ) . '" title="Remove">✕</a>' : '' ) . '</div>';
	} else {
		echo '<div class="dbd-cslot empty"><input type="search" class="dbd-srch" placeholder="' . esc_attr( 'Add a ' . $one . ' by name or team' ) . '" autocomplete="off" data-slot="' . $i . '"><ul class="dbd-csug" hidden></ul></div>';
	}
}
echo '</div><div class="dbd-crecent" id="dbd-crecent" hidden></div><p class="dbd-note">The address bar is the share link. Copy it, text it, post it: whoever opens it sees the same players. The Compare button on player pages builds on this set until you start over or switch positions.</p></section>';

/* sticky player key: who is which color, visible all the way down */
$chip = function ( $x ) { return '<a class="chip" style="--c:' . $x['c'] . '" href="' . esc_url( DBD_Router::url( array( 'player' => $x['row']['id'] ) ) ) . '">' . DBD_Marks::player( $x['row']['team'], $x['row']['num'], 22, $x['row']['photo'] ?? null ) . '<b>' . esc_html( $x['rec']['short'] ?? $x['row']['name'] ) . '</b><small>' . esc_html( $x['row']['team'] ) . '</small></a>'; };
$legend = function () use ( $P, $chip ) { return '<div class="dbd-cleg2">' . implode( '', array_map( $chip, $P ) ) . '</div>'; };
if ( $n ) { echo '<div class="dbd-ckey" aria-label="Who is which color"><span class="k">Key</span>' . implode( '', array_map( $chip, $P ) ) . $actions . '</div>'; }

if ( ! $n ) { echo '<div class="dbd-empty"><h2>Nobody picked yet</h2><p>Add a player above, or open any player page and hit Compare. Your recent comparisons, if any, are listed above.</p></div></main>'; return; }

/* ---- where they rank */
if ( $gi ) {
	echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where they rank</b> · among ' . $N . ' ' . esc_html( $pool ) . ' with ' . esc_html( $gi['min'] ) . '+</span><span class="dbd-badge">League percentiles</span></div>';
	echo '<div class="dbd-cleg">' . implode( '', array_map( function ( $x ) { return '<span><i style="background:' . $x['c'] . '"></i>' . esc_html( $x['row']['name'] ) . '</span>'; }, $P ) ) . '</div><div class="dbd-cpct">';
	foreach ( DBD_Grades::defs( $grp ) as $d ) {
		list( $k, $lab, $sub, $kind ) = $d; $vals = array(); $bi = -1;
		foreach ( $P as $i => $x ) { $g = $x['rec']['grades'] ?? null; $vals[ $i ] = ( $g && ! empty( $g['q'] ) ) ? ( $g['m'][ $k ] ?? null ) : null; if ( $bi < 0 ? $vals[ $i ] !== null : $better( $k, $vals[ $i ], $vals[ $bi ] ) ) { $bi = $i; } }
		echo '<div class="row"><div class="l">' . esc_html( $lab ) . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</div><div class="bar">';
		$med = DBD_Grades::median( $grp, $k ); $Nk = count( $gi['dist'][ $k ] ?? array() );  /* a metric's own pool: backs under five targets have no receiving EPA, so that pool is smaller */
		foreach ( $P as $i => $x ) { $pc = DBD_Grades::pctile( $grp, $k, $vals[ $i ] ); if ( $pc === null ) { continue; } $rk = DBD_Grades::rank( $grp, $k, $vals[ $i ] );
			$tip = '<b>' . esc_html( $x['row']['name'] ) . '</b><br>' . esc_html( $lab ) . ': <b>' . esc_html( DBD_Grades::fmt( $kind, $vals[ $i ] ) ) . '</b> · ' . esc_html( DBD_Grades::ordinal( $pc ) ) . ' percentile · ' . esc_html( DBD_Grades::ordinal( $rk ) ) . ' of ' . $Nk . '<br><span class="m">League median ' . esc_html( DBD_Grades::fmt( $kind, $med ) ) . ( $sub ? ' · ' . esc_html( $sub ) : '' ) . '</span>';
			echo '<i style="--p:' . (int) $pc . ';--c:' . $x['c'] . '" data-slot="' . $i . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $x['row']['name'] . ', ' . $lab . ' ' . DBD_Grades::fmt( $kind, $vals[ $i ] ) . ', ' . DBD_Grades::ordinal( $pc ) . ' percentile' ) . '"></i>'; }
		echo '</div>';
		foreach ( $P as $i => $x ) { $pc = DBD_Grades::pctile( $grp, $k, $vals[ $i ] ); echo '<div class="cv' . ( $i === $bi ? ' best' : '' ) . '" style="--c:' . $x['c'] . '" data-slot="' . $i . '">' . esc_html( DBD_Grades::fmt( $kind, $vals[ $i ] ) ) . '<small>' . ( $pc === null ? '-' : esc_html( DBD_Grades::ordinal( $pc ) ) ) . '</small></div>'; }
		echo '</div>';
	}
	echo '</div><p class="dbd-note">Each bar is the league pool; the markers are the players. Right is better, the dashed line is the median. The best value in each row is marked green.</p></section>';
}

/* ---- graphics (SCOPE §17 addendum): radar and floor-and-ceiling from the player files; the rest from compare.js */
$DEFS = $grp ? DBD_Grades::defs( $grp ) : array();
$pcs = array(); foreach ( $P as $i => $x ) { $g = $x['rec']['grades'] ?? null; $pcs[ $i ] = array(); foreach ( $DEFS as $d ) { $v = ( $g && ! empty( $g['q'] ) ) ? ( $g['m'][ $d[0] ] ?? null ) : null; $pcs[ $i ][ $d[0] ] = $gi ? DBD_Grades::pctile( $grp, $d[0], $v ) : null; } }
$short = function ( $lab ) { return array( 'Target share' => 'Target share', 'Depth of target' => 'Depth', 'After the catch' => 'YAC', 'Catch rate' => 'Catch rate', 'EPA per target' => 'EPA / tgt', 'First downs' => 'First downs', 'Drop rate' => 'Drops', 'Yards per game' => 'Yds / game', 'Yards per carry' => 'YPC', 'Rush EPA per carry' => 'Rush EPA', 'Broken tackles' => 'Brk tackles', 'Explosive rate' => 'Explosive', 'Touches per game' => 'Touches', 'Receiving share' => 'Rec share', 'Yards per touch' => 'Yds / touch', 'EPA per dropback' => 'EPA / DB', 'CPOE' => 'CPOE', 'Sack rate' => 'Sacks', 'Yards per attempt' => 'Y/A', 'TD rate' => 'TD rate', 'INT rate' => 'INT rate', 'Rush EPA per game' => 'Rush EPA' )[ $lab ] ?? $lab; };
echo '<div class="dbd-cgrid">';
/* radar */
if ( $gi ) {
	$S = 320; $cx = 160; $cy = 160; $R = 112; $nn = count( $DEFS ); $ang = function ( $i ) use ( $nn ) { return -M_PI / 2 + $i * 2 * M_PI / $nn; }; $pt = function ( $i, $r ) use ( $cx, $cy, $ang ) { return array( round( $cx + cos( $ang( $i ) ) * $r, 1 ), round( $cy + sin( $ang( $i ) ) * $r, 1 ) ); };
	$svg = '<svg viewBox="0 0 ' . $S . ' ' . $S . '" class="dbd-cradar" role="img" aria-label="Percentile radar">';
	foreach ( array( .25, .5, .75, 1 ) as $f ) { $pts = array(); foreach ( $DEFS as $i => $d ) { $pts[] = implode( ',', $pt( $i, $R * $f ) ); } $svg .= '<polygon points="' . implode( ' ', $pts ) . '" fill="none" stroke="' . ( $f == .5 ? 'rgba(22,30,54,.3)' : 'rgba(22,30,54,.12)' ) . '"' . ( $f == .5 ? ' stroke-dasharray="3 3"' : '' ) . '/>'; }
	foreach ( $DEFS as $i => $d ) { list( $x1, $y1 ) = $pt( $i, $R ); list( $lx, $ly ) = $pt( $i, $R + 24 ); $svg .= '<line x1="' . $cx . '" y1="' . $cy . '" x2="' . $x1 . '" y2="' . $y1 . '" stroke="rgba(22,30,54,.12)"/><text x="' . $lx . '" y="' . $ly . '" font-size="9" text-anchor="middle" dominant-baseline="middle">' . esc_html( $short( $d[1] ) ) . '</text>'; }
	$ungraded = array();
	$skipped = array();
	foreach ( $P as $i => $x ) {
		$g = $x['rec']['grades'] ?? null; if ( ! $g || empty( $g['q'] ) ) { $ungraded[] = $x['row']['name']; continue; }
		$pts = array();  // a metric with no grade (a back under five targets has no receiving EPA) is left off the shape, not drawn at zero
		foreach ( $DEFS as $j => $d ) { $pc = $pcs[ $i ][ $d[0] ]; if ( $pc === null ) { $skipped[] = $x['row']['name'] . ': ' . strtolower( $d[1] ); continue; } $pts[] = $pt( $j, $R * ( $pc / 100 ) ); }
		if ( count( $pts ) < 3 ) { $ungraded[] = $x['row']['name']; continue; }
		$svg .= '<polygon points="' . implode( ' ', array_map( function ( $q ) { return implode( ',', $q ); }, $pts ) ) . '" fill="' . $x['c'] . '" fill-opacity=".14" stroke="' . $x['c'] . '" stroke-width="2"/>';
		foreach ( $pts as $q ) { $svg .= '<circle cx="' . $q[0] . '" cy="' . $q[1] . '" r="3" fill="' . $x['c'] . '"/>'; }
	}
	echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>The shape</b> · percentile radar</span><span class="dbd-badge">Bigger and rounder is more complete</span></div><div class="dbd-pb">' . $svg . '</svg>' . ( $ungraded ? '<p class="dbd-note">' . esc_html( implode( ', ', $ungraded ) ) . ( count( $ungraded ) > 1 ? ' are' : ' is' ) . ' not graded yet, so no shape.</p>' : '' ) . ( $skipped ? '<p class="dbd-note">Not graded, so skipped on the shape: ' . esc_html( implode( '; ', $skipped ) ) . '.</p>' : '' ) . '</div></section>';
}
/* where he wins (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where he wins</b> · EPA per ' . ( $grp === 'qb' ? 'throw' : 'target' ) . ' by lane and depth</span><span class="dbd-badge">Same scale across</span></div>' . $legend() . '<div class="dbd-cheat" id="dbd-cheat">' . implode( '', array_map( function ( $x, $i ) { return '<div><h5><i style="background:' . $x['c'] . '"></i>' . esc_html( $x['row']['name'] ) . '</h5><div class="grid" id="dbd-cheat-' . $i . '"><div class="dbd-skel"><span class="dbd-note">Loading…</span></div></div></div>'; }, $P, array_keys( $P ) ) ) . '</div><p class="dbd-note">Green cells turn targets into points, red cells lose them. Cells with one target are left grey.</p></section>';
echo '</div>';

/* ---- field maps (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where the ball finds them</b> · same scale, same field</span><span class="dbd-badge" id="dbd-cmapn">loading plays</span></div><div class="dbd-cmaps">';
foreach ( $P as $i => $x ) { echo '<div><h4><i style="background:' . $x['c'] . '"></i>' . esc_html( $x['row']['name'] ) . ' <span class="n" id="dbd-cmapc-' . $i . '"></span></h4><div id="dbd-cmap-' . $i . '"><div class="dbd-skel"><span class="dbd-note">Loading…</span></div></div></div>'; }
echo '</div><div class="dbd-legend" id="dbd-cmapleg"></div><p class="dbd-note">' . ( $grp === 'rb' ? 'Designed carries by gap, from each team\'s play file.' : 'Targets placed by air yards and side of the field, from each team\'s play file.' ) . '</p></section>';

/* ---- one field + depth butterfly (JS), the race + grouped weeks (JS, Chartist) */
$two = array_slice( $P, 0, 2 );
echo '<div class="dbd-cgrid">';
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>On one field</b> · ' . ( $grp === 'qb' ? 'every throw' : 'every target' ) . ', same field</span><span class="dbd-badge">Filled is caught, a ring is not</span></div>' . $legend() . '<div class="dbd-pb" id="dbd-cfield"><div class="dbd-skel"><span class="dbd-note">Loading…</span></div></div></section>';
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Depth butterfly</b> · ' . esc_html( implode( ' and ', array_map( function ( $x ) { return $x['rec']['short'] ?? $x['row']['name']; }, $two ) ) ) . '</span><span class="dbd-badge">Solid is caught</span></div><div class="dbd-pb" id="dbd-cbfly"><div class="dbd-skel"><span class="dbd-note">Loading…</span></div></div><p class="dbd-note">' . ( $n > 2 ? 'The first two slots, back to back.' : 'Back to back by depth band.' ) . ' The widest band is where the offense goes to him; the gap between solid and faded is where it misses.</p></section>';
echo '</div><div class="dbd-cgrid">';
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>The race</b> · cumulative yards</span><span class="dbd-badge">Flat steps are byes</span></div>' . $legend() . '<div class="dbd-pb"><div class="dbd-cch ct-chart" id="dbd-crace"></div></div></section>';
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Week by week</b> · grouped</span><span class="dbd-badge">Same Sunday, side by side</span></div>' . $legend() . '<div class="dbd-pb"><div class="dbd-cch ct-chart" id="dbd-cweek"></div></div></section>';
echo '</div>';

/* ---- floor and ceiling (PHP): boom, solid or dud against the pool's game weeks */
$maxW = max( 1, (int) DBD_Data::through_week() ); $qw = $gi['week'] ?? null;
$ser = array(); foreach ( $P as $i => $x ) { $ser[ $i ] = DBD_Grades::week_series( $x['rec'], $grp, $maxW ); }
if ( ! $qw ) { $all = array(); foreach ( $ser as $sv ) { foreach ( $sv as $v ) { if ( $v !== null ) { $all[] = $v; } } } sort( $all ); $qw = $all ? array( 'p25' => $all[ (int) ( count( $all ) * .25 ) ], 'p75' => $all[ min( count( $all ) - 1, (int) ( count( $all ) * .75 ) ) ] ) : array( 'p25' => 0, 'p75' => 0 ); }
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Floor and ceiling</b> · boom, solid or dud, week by week</span><span class="dbd-badge">Boom ' . (int) $qw['p75'] . '+ · dud under ' . (int) $qw['p25'] . '</span></div><div class="dbd-pb dbd-cstrip">';
foreach ( $P as $i => $x ) {
	$cells = ''; $vals = array();
	foreach ( $ser[ $i ] as $wi => $v ) { $wk = $wi + 1; if ( $v === null ) { $cells .= '<span class="bye" title="Week ' . $wk . ': no game">' . $wk . '<small>Wk ' . $wk . '</small></span>'; continue; } $vals[] = $v; $cls = $v >= $qw['p75'] ? 'boom' : ( $v <= $qw['p25'] ? 'dud' : 'solid' ); $cells .= '<span class="' . $cls . '" title="Week ' . $wk . ': ' . (int) $v . ' yards">' . (int) $v . '<small>Wk ' . $wk . '</small></span>'; }
	$avg = $vals ? array_sum( $vals ) / count( $vals ) : 0; $sd = 0; foreach ( $vals as $v ) { $sd += ( $v - $avg ) * ( $v - $avg ); } $sd = $vals ? sqrt( $sd / count( $vals ) ) : 0;
	echo '<div class="r"><b><i style="background:' . $x['c'] . '"></i>' . esc_html( $x['rec']['short'] ?? $x['row']['name'] ) . '</b><div class="wk">' . $cells . '</div><small>' . round( $avg ) . ' ± ' . round( $sd ) . '</small></div>';
}
echo '</div><p class="dbd-note">Thresholds are the quartiles of every game week by qualified ' . esc_html( $pool ) . ' this season' . ( isset( $gi['week']['n'] ) ? ' (' . (int) $gi['week']['n'] . ' player weeks)' : '' ) . '. The right column is the average week and its swing.</p></section>';

/* ---- fantasy (SCOPE §34): the four numbers side by side and the weeks grouped, per format */
echo DBD_Fantasy::compare( $P );

/* ---- week by week, one table per metric */
$M = array( 'rec' => array( array( 'Yds', 'receiving_yards' ), array( 'Tgt', 'targets' ), array( 'Rec', 'receptions' ), array( 'EPA', 'receiving_epa' ), array( 'Snap', 'snap' ) ),
	'rb' => array( array( 'Yds', 'rushing_yards' ), array( 'Car', 'carries' ), array( 'Tgt', 'targets' ), array( 'EPA', 'rushing_epa' ), array( 'Snap', 'snap' ) ),
	'qb' => array( array( 'Yds', 'passing_yards' ), array( 'Att', 'attempts' ), array( 'TD', 'passing_tds' ), array( 'EPA', 'passing_epa' ), array( 'Snap', 'snap' ) ) )[ $grp ];
$weeks = array();
foreach ( $P as $x ) { foreach ( $x['rec']['weeks'] as $w ) { $weeks[ (int) $w['week'] ] = 1; } foreach ( array_keys( $x['rec']['snaps'] ?? array() ) as $wk ) { $weeks[ (int) $wk ] = 1; } }
$weeks = array_keys( $weeks ); sort( $weeks );
$val = function ( $x, $key, $wk ) { if ( $key === 'snap' ) { return isset( $x['rec']['snaps'][ (string) $wk ] ) ? (float) $x['rec']['snaps'][ (string) $wk ] : null; } foreach ( $x['rec']['weeks'] as $w ) { if ( (int) $w['week'] === $wk ) { return (float) ( $w[ $key ] ?? 0 ); } } return null; };
$fmtv = function ( $key, $v ) use ( $sgn ) { if ( $v === null ) { return '<span class="dbd-cdot">·</span>'; } if ( $key === 'snap' ) { return (int) $v . '%'; } return strpos( $key, 'epa' ) !== false ? $sgn( $v ) : (string) (int) round( $v ); };
echo '<section class="dbd-panel"><div class="dbd-ph dbd-glogh"><span class="dbd-lab"><b>Week by week</b> · aligned</span><div class="dbd-seg t-tabs dbd-cmetric" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>';
foreach ( $M as $i => $m ) { echo '<button class="t-tab" type="button" role="tab" data-v="' . esc_attr( $m[1] ) . '" aria-selected="' . ( $i ? 'false' : 'true' ) . '">' . esc_html( $m[0] ) . '</button>'; }
echo '</div></div>';
foreach ( $M as $mi => $m ) {
	$key = $m[1];
	echo '<div class="dbd-tw" data-metric="' . esc_attr( $key ) . '"' . ( $mi ? ' hidden' : '' ) . '><table class="dbd-alog"><thead><tr><th>Week</th>';
	foreach ( $P as $x ) { echo '<th class="p" style="--c:' . $x['c'] . '"><span class="chip">' . esc_html( $x['rec']['short'] ?? $x['row']['name'] ) . '</span></th>'; }
	echo '</tr></thead><tbody>';
	foreach ( $weeks as $wk ) {
		$vs = array(); foreach ( $P as $i => $x ) { $vs[ $i ] = $val( $x, $key, $wk ); }
		$nn = array_filter( $vs, function ( $v ) { return $v !== null; } ); $mx = $nn ? max( $nn ) : null; $ties = $mx === null ? 0 : count( array_keys( $nn, $mx, true ) );
		echo '<tr><td>Wk ' . (int) $wk . '</td>';
		foreach ( $vs as $v ) { echo '<td' . ( $v !== null && $v === $mx && $ties === 1 ? ' class="b"' : '' ) . '>' . $fmtv( $key, $v ) . '</td>'; }
		echo '</tr>';
	}
	echo '</tbody><tfoot><tr><td>' . ( $key === 'snap' ? 'Average' : 'Season' ) . '</td>';
	foreach ( $P as $x ) { if ( $key === 'snap' ) { $sv = array_values( $x['rec']['snaps'] ?? array() ); $tot = $sv ? array_sum( $sv ) / count( $sv ) : null; echo '<td>' . ( $tot === null ? '-' : round( $tot ) . '%' ) . '</td>'; } else { $tot = 0; foreach ( $x['rec']['weeks'] as $w ) { $tot += (float) ( $w[ $key ] ?? 0 ); } echo '<td>' . ( strpos( $key, 'epa' ) !== false ? $sgn( $tot ) : (int) round( $tot ) ) . '</td>'; } }
	echo '</tr></tfoot></table></div>';
}
echo '<p class="dbd-note">Rows are league weeks, so byes and missed games show as gaps. The best value each week is marked green.</p></section>';

/* ---- season stats side by side */
$S = function ( $x, $k ) { $t = 0; foreach ( $x['rec']['weeks'] as $w ) { $t += (float) ( $w[ $k ] ?? 0 ); } return $t; };
$base = array();
foreach ( $P as $x ) {
	if ( $grp === 'rec' )    { $base[] = array( array( 'Targets', $S( $x, 'targets' ) ), array( 'Receptions', $S( $x, 'receptions' ) ), array( 'Yards', $S( $x, 'receiving_yards' ) ), array( 'Touchdowns', $S( $x, 'receiving_tds' ) ) ); }
	elseif ( $grp === 'rb' ) { $base[] = array( array( 'Carries', $S( $x, 'carries' ) ), array( 'Rush yards', $S( $x, 'rushing_yards' ) ), array( 'Rush TD', $S( $x, 'rushing_tds' ) ), array( 'Targets', $S( $x, 'targets' ) ) ); }
	else                     { $base[] = array( array( 'Attempts', $S( $x, 'attempts' ) ), array( 'Completions', $S( $x, 'completions' ) ), array( 'Pass yards', $S( $x, 'passing_yards' ) ), array( 'Touchdowns', $S( $x, 'passing_tds' ) ) ); }
}
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Season stats</b> · side by side, with league rank</span></div><div class="dbd-tw"><table class="dbd-sbs"><thead><tr><th>Season</th>';
foreach ( $P as $x ) { echo '<th style="--c:' . $x['c'] . '"><span class="chip">' . esc_html( $x['rec']['short'] ?? $x['row']['name'] ) . '</span></th>'; }
echo '</tr></thead><tbody>';
foreach ( $base[0] as $ri => $b ) {
	$vs = array(); foreach ( $P as $i => $x ) { $vs[ $i ] = $base[ $i ][ $ri ][1]; } $mx = max( $vs ); $ties = count( array_keys( $vs, $mx, true ) );
	echo '<tr><td>' . esc_html( $b[0] ) . '</td>'; foreach ( $vs as $v ) { echo '<td' . ( $v === $mx && $ties === 1 ? ' class="b"' : '' ) . '>' . (int) $v . '</td>'; } echo '</tr>';
}
if ( $gi ) {
	foreach ( DBD_Grades::defs( $grp ) as $d ) {
		list( $k, $lab, $sub, $kind ) = $d; $vs = array(); $bi = -1;
		foreach ( $P as $i => $x ) { $g = $x['rec']['grades'] ?? null; $vs[ $i ] = ( $g && ! empty( $g['q'] ) ) ? ( $g['m'][ $k ] ?? null ) : null; if ( $bi < 0 ? $vs[ $i ] !== null : $better( $k, $vs[ $i ], $vs[ $bi ] ) ) { $bi = $i; } }
		echo '<tr><td>' . esc_html( $lab ) . '</td>';
		foreach ( $P as $i => $x ) { $rk = DBD_Grades::rank( $grp, $k, $vs[ $i ] ); echo '<td' . ( $i === $bi && $vs[ $i ] !== null ? ' class="b"' : '' ) . '>' . esc_html( DBD_Grades::fmt( $kind, $vs[ $i ] ) ) . ( $rk ? '<small>' . esc_html( DBD_Grades::ordinal( $rk ) ) . '</small>' : '' ) . '</td>'; }
		echo '</tr>';
	}
}
echo '</tbody></table></div></section></main>';

/* same-group index for the slot search */
$idx = array_values( array_filter( DBD_Data::player_index(), function ( $o ) use ( $grp, $ids ) { return DBD_Grades::group_of( $o['pos'] ) === $grp && ! in_array( $o['id'], $ids, true ); } ) );
echo '<script type="application/json" id="dbd-cindex">' . wp_json_encode( array_map( function ( $o ) { return array( 'id' => $o['id'], 'name' => $o['name'], 'team' => $o['team'], 'pos' => $o['pos'], 'num' => $o['num'], 'snap' => $o['snap'] ); }, $idx ) ) . '</script>';
