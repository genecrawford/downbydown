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
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Who</b> · up to three, same position group</span><span class="dbd-badge">The link in the address bar is the share link</span></div><div class="dbd-cslots">';
for ( $i = 0; $i < 3; $i++ ) {
	if ( isset( $P[ $i ] ) ) {
		$x = $P[ $i ]; $rowx = $x['row']; $rm = array_values( array_diff( $ids, array( $rowx['id'] ) ) );
		echo '<div class="dbd-cslot" style="--c:' . $x['c'] . '">' . DBD_Marks::player( $rowx['team'], $rowx['num'], 40, $rowx['photo'] ?? null ) . '<div class="who"><b><a href="' . esc_url( DBD_Router::url( array( 'player' => $rowx['id'] ) ) ) . '">' . esc_html( $rowx['name'] ) . '</a></b><small>' . esc_html( $rowx['pos'] . ' · ' . ( $x['t']['nick'] ?? $rowx['team'] ) . ' · ' . ( DBD_Grades::qualifies( $rowx ) ? 'graded' : 'not yet qualified' ) ) . '</small></div>' . ( $n > 1 ? '<a class="x" href="' . esc_url( $curl( $rm ) ) . '" title="Remove">✕</a>' : '' ) . '</div>';
	} else {
		echo '<div class="dbd-cslot empty"><input type="search" class="dbd-srch" placeholder="' . esc_attr( 'Add a ' . $one . ' by name or team' ) . '" autocomplete="off" data-slot="' . $i . '"><ul class="dbd-csug" hidden></ul></div>';
	}
}
echo '</div></section>';

if ( ! $n ) { echo '<div class="dbd-empty"><h2>Nobody picked yet</h2><p>Add a player above, or open any player page and hit Compare.</p></div></main>'; return; }

/* ---- where they rank */
if ( $gi ) {
	echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where they rank</b> · among ' . $N . ' ' . esc_html( $pool ) . ' with ' . esc_html( $gi['min'] ) . '+</span><span class="dbd-badge">League percentiles</span></div>';
	echo '<div class="dbd-cleg">' . implode( '', array_map( function ( $x ) { return '<span><i style="background:' . $x['c'] . '"></i>' . esc_html( $x['row']['name'] ) . '</span>'; }, $P ) ) . '</div><div class="dbd-cpct">';
	foreach ( DBD_Grades::defs( $grp ) as $d ) {
		list( $k, $lab, $sub, $kind ) = $d; $vals = array(); $bi = -1;
		foreach ( $P as $i => $x ) { $g = $x['rec']['grades'] ?? null; $vals[ $i ] = ( $g && ! empty( $g['q'] ) ) ? ( $g['m'][ $k ] ?? null ) : null; if ( $bi < 0 ? $vals[ $i ] !== null : $better( $k, $vals[ $i ], $vals[ $bi ] ) ) { $bi = $i; } }
		echo '<div class="row"><div class="l">' . esc_html( $lab ) . ( $sub ? '<small>' . esc_html( $sub ) . '</small>' : '' ) . '</div><div class="bar">';
		foreach ( $P as $i => $x ) { $pc = DBD_Grades::pctile( $grp, $k, $vals[ $i ] ); if ( $pc !== null ) { echo '<i style="--p:' . (int) $pc . ';--c:' . $x['c'] . '" title="' . esc_attr( $x['row']['name'] . ': ' . DBD_Grades::ordinal( $pc ) ) . '"></i>'; } }
		echo '</div>';
		foreach ( $P as $i => $x ) { $pc = DBD_Grades::pctile( $grp, $k, $vals[ $i ] ); echo '<div class="cv' . ( $i === $bi ? ' best' : '' ) . '" style="--c:' . $x['c'] . '">' . esc_html( DBD_Grades::fmt( $kind, $vals[ $i ] ) ) . '<small>' . ( $pc === null ? '-' : esc_html( DBD_Grades::ordinal( $pc ) ) ) . '</small></div>'; }
		echo '</div>';
	}
	echo '</div><p class="dbd-note">Each bar is the league pool; the markers are the players. Right is better, the dashed line is the median. The best value in each row is marked green.</p></section>';
}

/* ---- field maps (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where the ball finds them</b> · same scale, same field</span><span class="dbd-badge" id="dbd-cmapn">loading plays</span></div><div class="dbd-cmaps">';
foreach ( $P as $i => $x ) { echo '<div><h4><i style="background:' . $x['c'] . '"></i>' . esc_html( $x['row']['name'] ) . ' <span class="n" id="dbd-cmapc-' . $i . '"></span></h4><div id="dbd-cmap-' . $i . '"><div class="dbd-skel"><span class="dbd-note">Loading…</span></div></div></div>'; }
echo '</div><div class="dbd-legend" id="dbd-cmapleg"></div><p class="dbd-note">' . ( $grp === 'rb' ? 'Designed carries by gap, from each team\'s play file.' : 'Targets placed by air yards and side of the field, from each team\'s play file.' ) . '</p></section>';

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
	foreach ( $P as $x ) { echo '<th class="p" style="--c:' . $x['c'] . '">' . esc_html( $x['rec']['short'] ?? $x['row']['name'] ) . '</th>'; }
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
foreach ( $P as $x ) { echo '<th style="--c:' . $x['c'] . '">' . esc_html( $x['rec']['short'] ?? $x['row']['name'] ) . '</th>'; }
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
