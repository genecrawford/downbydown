<?php
/**
 * Player page. PHP renders what the player file holds (hero, tiles, game log, intel basics, teammates) so
 * crawlers see a real page; player.js adds the field map, depth bands, situations and best plays from the
 * team's play file.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $row = $r['player']; $a = $row['team']; $t = DBD_Data::team( $a );
$p = DBD_Data::player_record( $a, $row['id'] );
if ( ! $p ) { include DBD_PATH . 'includes/views/notfound.php'; return; }
$season = DBD_Data::season(); $through = DBD_Data::through_week();
$W = $p['weeks'] ?? array();
$played = array_unique( array_merge( array_map( function ( $w ) { return (int) $w['week']; }, $W ), array_map( 'intval', array_keys( $p['snaps'] ?? array() ) ) ) ); $ng = count( $played ); $gp = max( 1, $ng );
$traded = ! empty( $p['snapteam'] ) || (bool) array_filter( $W, function ( $w ) use ( $a ) { return ! empty( $w['team'] ) && $w['team'] !== $a; } );
/* game-log rows: every played week, a zero line where he took snaps but never touched the ball */
sort( $played );
$zero = array_fill_keys( array( 'targets', 'receptions', 'receiving_yards', 'receiving_tds', 'receiving_air_yards', 'receiving_yards_after_catch', 'receiving_epa', 'receiving_first_downs', 'target_share', 'air_yards_share', 'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa', 'rushing_first_downs', 'attempts', 'completions', 'passing_yards', 'passing_tds', 'passing_interceptions', 'passing_epa', 'passing_cpoe', 'sacks_suffered', 'passing_air_yards', 'receiving_20', 'rushing_10' ), 0 );
$byWk = array(); foreach ( $W as $w ) { $byWk[ (int) $w['week'] ] = $w; }
$L = array_map( function ( $wk ) use ( $byWk, $zero, $a, $p ) { return $byWk[ $wk ] ?? array_merge( $zero, array( 'week' => $wk, 'team' => $p['snapteam'][ (string) $wk ] ?? $a ) ); }, $played );
$sum = function ( $k ) use ( $W ) { $s = 0; foreach ( $W as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s; };
$avg = function ( $k ) use ( $W, $gp ) { $s = 0; foreach ( $W as $w ) { $s += (float) ( $w[ $k ] ?? 0 ); } return $s / $gp; };
$pct = function ( $v ) { return is_finite( $v ) && ! is_nan( $v ) ? round( $v * 100 ) . '%' : '-'; };
$f1  = function ( $v ) { return is_finite( $v ) && ! is_nan( $v ) ? number_format( $v, 1 ) : '-'; };
$sgn = function ( $v, $d = 2 ) { return ( $v > 0 ? '+' : '' ) . number_format( $v, $d ); };
$div = function ( $n, $d ) { return $d ? $n / $d : NAN; };
$grp = $p['pos'] === 'QB' ? 'qb' : ( in_array( $p['pos'], array( 'RB', 'FB' ), true ) ? 'rb' : 'rec' );
$snapAvg = ! empty( $p['snaps'] ) ? array_sum( $p['snaps'] ) / count( $p['snaps'] ) : 0;
$parts = explode( ' ', $p['name'], 2 ); $first = $parts[0]; $last = $parts[1] ?? '';
$ord = function ( $n ) { $s = array( 'th', 'st', 'nd', 'rd' ); $v = $n % 100; return $n . ( $s[ ( $v - 20 ) % 10 ] ?? $s[ $v ] ?? $s[0] ); };
$depth = ! empty( $p['depth'] ) ? $p['depth']['slot'] . $p['depth']['rank'] : '';
$st = $p['inj']['status'] ?? ''; $icls = preg_match( '/out|did not/i', $st ) ? 'bad' : ( preg_match( '/limited|questionable|doubtful/i', $st ) ? '' : 'good' );
$CRF = DBD_Data::get( 'players/career/' . $a . '.json' ); $CR = $CRF['players'][ $p['id'] ] ?? array();  /* career rows (SCOPE §24), empty on an older data file */
$turl = DBD_Router::url( array( 'team' => $a ) ); $purl = DBD_Router::url( array( 'view' => 'players' ) ); $tpurl = DBD_Router::url( array( 'view' => 'players', 'team' => $a ) );

echo DBD_Router::topbar( array( 'players' => $purl, $t['nick'] => $tpurl, $p['name'] => null ) );
echo '<main class="dbd-content dbd-player" style="--dbd-team:' . esc_attr( DBD_Marks::accent( $t ) ) . '">';

/* ---- hero */
echo '<div class="dbd-ttitle"><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · {$p['pos']} · #{$p['num']} · " . $ord( (int) $p['exp'] + 1 ) . ' season' ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2 withmark">' . DBD_Marks::jersey( $a, $p['num'], 58 ) . '<span>' . esc_html( $first ) . ' <em>' . esc_html( $last ) . '</em></span></h1>';
$bio = array_filter( array( $p['age'] ? 'Age ' . (int) $p['age'] : '', $p['ht'] ?: '', $p['wt'] ? (int) $p['wt'] . ' lb' : '', $p['col'] ?: '', $p['draft'] ?: '' ) );
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">' . esc_html( implode( ' · ', $bio ) ) . '.' . ( $depth ? ' Listed <b>' . esc_html( $depth ) . '</b> on the ' . esc_html( $p['depth']['grp'] ) . ' depth chart.' : '' ) . ' Snap share <b>' . round( $snapAvg ) . '%</b> across ' . $ng . ' ' . ( $ng === 1 ? 'game' : 'games' ) . '.</p>';
echo '<div class="dbd-pills">' . ( $p['inj'] ? '<span class="dbd-pill ' . $icls . '">Week ' . (int) $p['inj']['week'] . ' report · ' . esc_html( $p['inj']['what'] ) . ' · ' . esc_html( str_ireplace( ' in practice', '', $st ) ) . '</span>' : '<span class="dbd-pill good">No injury listing</span>' ) . ( $depth ? '<span class="dbd-pill">' . esc_html( $depth ) . '</span>' : '' ) . '<a class="dbd-pill" href="' . esc_url( $tpurl ) . '">' . esc_html( $t['nick'] ) . ' players</a></div>';
$sug = DBD_Grades::suggest( $row, 3 ); $cmp = function ( $ids ) { return DBD_Router::url( array( 'compare' => implode( ',', $ids ) ) ); };
/* the side card (SCOPE §35): the team with his snap share, two equal actions, then players like him as a list */
echo '</div>' . DBD_Marks::hero_photo( $a, $p['photo'] ?? null ) . '<div class="dbd-hdr dbd-hcard"><a class="dbd-pn-link" href="' . esc_url( $turl ) . '">' . DBD_Marks::pennant( $a, 40 ) . '<span class="t"><b>' . esc_html( $t['nick'] ) . ' offense</b><small id="dbd-psnaps">' . ( $p['snaps'] ? round( $snapAvg ) . '% of snaps' : 'no snap data' ) . '</small></span></a>';
echo '<div class="acts"><a class="dbd-cmp-btn" id="dbd-cmp-btn" href="' . esc_url( $cmp( array( $p['id'] ) ) ) . '" data-id="' . esc_attr( $p['id'] ) . '" data-base="' . esc_url( DBD_Router::url() ) . '">Compare</a>'
	. ( DBD_Card::available() ? '<a class="dbd-share" href="' . esc_url( DBD_Card::url( $p['id'] ) ) . '" target="_blank" rel="noopener" data-share-title="' . esc_attr( $p['name'] . ' | Down by Down' ) . '" data-tip="' . esc_attr( 'A 1200 × 630 image of this page for social' ) . '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 12v7a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v13"/></svg>Share card</a>' : '' ) . '</div>';
if ( $sug ) { echo '<div class="dbd-cmp-sug"><span>Players like him</span>' . implode( '', array_map( function ( $o ) use ( $cmp, $p ) { return '<a class="r" href="' . esc_url( $cmp( array( $p['id'], $o['id'] ) ) ) . '" title="' . esc_attr( 'Compare with ' . $o['name'] ) . '">' . DBD_Marks::tile( $o['team'], 16 ) . '<b>' . esc_html( $o['name'] ) . '</b><i>Compare →</i></a>'; }, $sug ) ) . '</div>'; }
echo '</div></div>';

/* ---- tiles by position group */
$tg = $sum( 'targets' ); $rc = $sum( 'receptions' ); $ry = $sum( 'receiving_yards' ); $rtd = $sum( 'receiving_tds' ); $air = $sum( 'receiving_air_yards' ); $yac = $sum( 'receiving_yards_after_catch' ); $repa = $sum( 'receiving_epa' ); $rfd = $sum( 'receiving_first_downs' );
$ca = $sum( 'carries' ); $ruy = $sum( 'rushing_yards' ); $rutd = $sum( 'rushing_tds' ); $ruepa = $sum( 'rushing_epa' );
$att = $sum( 'attempts' ); $cmp = $sum( 'completions' ); $py = $sum( 'passing_yards' ); $ptd = $sum( 'passing_tds' ); $pint = $sum( 'passing_interceptions' ); $pepa = $sum( 'passing_epa' ); $sk = $sum( 'sacks_suffered' );
$tshare = $avg( 'target_share' ); $ashare = $avg( 'air_yards_share' );
$qbw = array_filter( $W, function ( $w ) { return ( $w['attempts'] ?? 0 ) > 0; } ); $cpoe = $qbw ? array_sum( array_map( function ( $w ) { return (float) ( $w['passing_cpoe'] ?? 0 ); }, $qbw ) ) / count( $qbw ) : 0;
/* SCOPE §29: the EPA split, success rate, ANY/A and the tiles' league ranks, all from the player file; an older data file shows none of them */
$EP = ! empty( $p['ep']['sec'] ) ? $p['ep'] : null; $TRK = (array) ( $p['trk'] ?? array() ); $poolN = (int) ( DBD_Grades::group_info( $grp )['n'] ?? 0 );
$hasPfd = (bool) array_filter( $W, function ( $w ) { return array_key_exists( 'passing_first_downs', $w ); } );  /* first downs reached the weekly lines in 1.5.0's build */
if ( $grp === 'rec' ) {
	$tiles = array( array( 'Targets', (int) $tg, $f1( $tg / $gp ) . ' per game', 'tgt', 'targets' ), array( 'Receptions', (int) $rc, $pct( $div( $rc, $tg ) ) . ' catch rate', 'rec', 'receptions' ), array( 'Yards', (int) $ry, round( $ry / $gp ) . ' per game', 'yds', 'receiving yards' ), array( 'Touchdowns', (int) $rtd, (int) $rfd . ' first downs', 'td', 'receiving touchdowns' ),
		array( 'Target share', $pct( $tshare ), $pct( $ashare ) . ' of air yards', 'tsh', 'target share' ), array( 'aDOT', $f1( $div( $air, $tg ) ), 'air yards per target', 'adot', 'depth of target' ), array( 'EPA / target', $tg ? $sgn( $repa / $tg ) : '-', $sgn( $repa, 1 ) . ' total', 'epat', 'EPA per target' ), array( 'YAC / catch', $f1( $div( $yac, $rc ) ), (int) $yac . ' after the catch', 'yac', 'yards after the catch per catch' ) );
} elseif ( $grp === 'rb' ) {
	$tiles = array( array( 'Carries', (int) $ca, $f1( $ca / $gp ) . ' per game', 'car', 'carries' ), array( 'Rush yards', (int) $ruy, $f1( $div( $ruy, $ca ) ) . ' per carry', 'ruy', 'rushing yards' ), array( 'Rush TD', (int) $rutd, (int) $sum( 'rushing_first_downs' ) . ' first downs', 'rutd', 'rushing touchdowns' ), array( 'Targets', (int) $tg, (int) $rc . ' catches · ' . (int) $ry . ' yds', 'tgt', 'targets' ),
		array( 'Touches / game', $f1( ( $ca + $tg ) / $gp ), (int) ( $ca + $tg ) . ' total', 'tpg', 'touches per game' ), array( 'EPA / touch', ( $ca + $tg ) ? $sgn( ( $ruepa + $repa ) / ( $ca + $tg ) ) : '-', $sgn( $ruepa + $repa, 1 ) . ' total', 'epatch', 'EPA per touch' ), array( 'Explosive', (int) ( $sum( 'rushing_10' ) + $sum( 'receiving_20' ) ), '10+ run · 20+ catch', 'expl', 'explosive plays' ), array( 'Snap share', round( $snapAvg ) . '%', 'offensive snaps', 'snap', 'snap share' ) );
} else {
	$tiles = array( array( 'Attempts', (int) $att, $f1( $att / $gp ) . ' per game', 'att', 'attempts' ), array( 'Completion', $pct( $div( $cmp, $att ) ), $sgn( $cpoe, 1 ) . ' over expected', 'cmp', 'completion rate' ), array( 'Pass yards', (int) $py, $f1( $div( $py, $att ) ) . ' per attempt', 'py', 'passing yards' ), array( 'TD / INT', (int) $ptd . ' / ' . (int) $pint, $hasPfd ? (int) $sum( 'passing_first_downs' ) . ' first downs' : $pct( $div( $ptd, $att ) ) . ' TD rate', 'ptd', 'touchdown passes' ),
		array( 'EPA / dropback', ( $att + $sk ) ? $sgn( $pepa / ( $att + $sk ) ) : '-', $sgn( $pepa, 1 ) . ' total', 'epadb', 'EPA per dropback' ), array( 'Sacks', (int) $sk, $pct( $div( $sk, $att + $sk ) ) . ' of dropbacks', 'sack', 'sack rate, fewest first' ), array( 'Rush', (int) $ruy . ' yds', (int) $ca . ' carries · ' . (int) $rutd . ' TD', 'ruy', 'rushing yards' ),
		isset( $EP['any'] ) ? array( 'ANY/A', number_format( (float) $EP['any'], 2 ), 'net yds per dropback', 'any', 'ANY/A' ) : array( 'Snap share', round( $snapAvg ) . '%', 'offensive snaps', null, '' ) );  /* the hero badge already shows snap share */
}
/* the rank line: "5th of 38" against the pool Where he ranks uses, green inside the top 10, ties as T-; none for a player outside the pool */
$trkLine = function ( $k, $what ) use ( $TRK, $poolN, $grp ) {
	if ( ! $k || empty( $TRK[ $k ] ) || ! $poolN ) { return ''; }
	$rk = (int) $TRK[ $k ][0]; $lab = ( ! empty( $TRK[ $k ][1] ) ? 'T-' : '' ) . DBD_Grades::ordinal( $rk );
	return '<div class="dbd-trk' . ( $rk <= 10 ? ' top' : '' ) . '" data-tip="' . esc_attr( '<b>' . $lab . '</b> of ' . $poolN . ' qualifying ' . DBD_Grades::pool_label( $grp ) . ' in ' . $what ) . '" tabindex="0"><b>' . esc_html( $lab ) . '</b> of ' . $poolN . '</div>';
};
echo '<div class="dbd-tiles">';
foreach ( $tiles as $x ) { echo '<div class="dbd-tile"><div class="l">' . esc_html( $x[0] ) . '</div><div class="v">' . esc_html( $x[1] ) . '</div><div class="s">' . esc_html( $x[2] ) . '</div>' . $trkLine( $x[3] ?? null, $x[4] ?? '' ) . '</div>'; }
echo '</div>';

/* SCOPE §31: the rest of the page is buffered; the short version is built from its reads and placed under the tiles at the end */
ob_start();

/* ---- opponent context (SCOPE §19): the defense faced each week, ranked against his position. Metrics and schedule are already shipped. */
$dk = DBD_Grades::defense_key( $p['pos'] ); $dkey = $dk['key']; $M = DBD_Data::metrics(); $dlg = $M['league'][ $dkey ] ?? null; $hasDef = $dlg !== null && ! empty( $M['rank']['def'] );
$teams = DBD_Data::teams();
$drank = function ( $tm ) use ( $M, $dkey ) { return $tm && isset( $M['rank']['def'][ $tm ][ $dkey ] ) ? (int) $M['rank']['def'][ $tm ][ $dkey ] : null; };
$dval  = function ( $tm ) use ( $M, $dkey ) { return $M['defense'][ $tm ][ $dkey ] ?? null; };
$dtip  = function ( $tm ) use ( $drank, $dval, $dlg, $dk, $teams, $sgn ) { $v = $dval( $tm ); return '<b>' . esc_html( $teams[ $tm ]['nick'] ?? $tm ) . '</b> allow ' . ( $v === null ? '-' : $sgn( (float) $v ) ) . ' EPA ' . esc_html( $dk['unit'] ) . ( $dlg === null ? '' : ' <span class="m">league ' . $sgn( (float) $dlg ) . '</span>' ) . '<br>' . esc_html( DBD_Grades::ordinal( $drank( $tm ) ) ) . '-toughest of 32 ' . esc_html( $dk['lab'] ); };
$dchipShort = function ( $tm ) use ( $drank, $dtip ) { $rk = $drank( $tm ); return $rk ? '<span class="dbd-dchip ' . DBD_Grades::defense_tier( $rk ) . '" data-tip="' . esc_attr( $dtip( $tm ) ) . '" tabindex="0">' . esc_html( DBD_Grades::ordinal( $rk ) ) . ' D</span>' : ''; };  /* phone-sized: the fold under the opponent */
$dchip = function ( $tm ) use ( $drank, $dtip, $dk ) { $rk = $drank( $tm ); if ( ! $rk ) { return '<td class="dbd-dcell"></td>'; } return '<td class="dbd-dcell" data-v="' . $rk . '"><span class="dbd-dchip ' . DBD_Grades::defense_tier( $rk ) . '" data-tip="' . esc_attr( $dtip( $tm ) ) . '" tabindex="0">' . esc_html( DBD_Grades::ordinal( $rk ) . ' ' . $dk['lab'] ) . '</span></td>'; };
$oppOf = function ( $wk, $tm = null ) use ( $a ) { $tm = $tm ?: $a; $g = DBD_Data::team_game( $tm, $wk ); if ( ! $g ) { return null; } return $g['home'] === $tm ? $g['away'] : $g['home']; };

/* ---- this week (SCOPE §25): his next game, the defense at his position, his history against that tier, his status. Schedule, metrics and his own line; nothing new. */
$curWk0 = (int) DBD_Data::current_week(); $mineAll = array(); $maxW = 0;
foreach ( (array) ( DBD_Data::schedule()['weeks'] ?? array() ) as $wkn => $gl ) { foreach ( (array) $gl as $twGm ) { if ( $twGm['home'] === $a || $twGm['away'] === $a ) { $twGm['wk'] = (int) ( $twGm['week'] ?? $wkn ); $mineAll[] = $twGm; } } }
usort( $mineAll, function ( $x, $y ) { return $x['wk'] - $y['wk']; } );
$twCur = null; $twNext = null; foreach ( $mineAll as $twGm ) { $fin = ( $twGm['status'] ?? '' ) === 'final' || isset( $twGm['home_score'] ); if ( $twGm['wk'] === $curWk0 ) { $twCur = $twGm; } if ( ! $twNext && $twGm['wk'] >= $curWk0 && ! $fin ) { $twNext = $twGm; } }
ob_start();  /* SCOPE §31: this week becomes the first tab of Week by week */
if ( $mineAll ) {
	if ( ! $twNext ) {
		echo '<section class="dbd-panel dbd-week"><div class="dbd-ph"><span class="dbd-lab"><b>This week</b> · season over</span></div><p class="dbd-week-say">No games left on the schedule.</p></section>';
	} else {
		$twGm = $twNext; $twHome = $twGm['home'] === $a; $twOpp = $twHome ? $twGm['away'] : $twGm['home']; $later = $twGm['wk'] > $curWk0; $bye = ! $twCur && $later; $oppNick = $teams[ $twOpp ]['nick'] ?? $twOpp;
		/* kickoff in Eastern time, like the rest of the page; the schedule's clock is Eastern (1.5.0: the site's own zone printed GMT) */
		$kick = ''; try { $dt = new DateTime( ( $twGm['date'] ?? '' ) . ' ' . ( $twGm['time'] ?: '13:00' ), new DateTimeZone( 'America/New_York' ) ); $kick = wp_date( 'D M j · g:i A T', $dt->getTimestamp(), new DateTimeZone( 'America/New_York' ) ); } catch ( Exception $e ) { $kick = trim( ( $twGm['day'] ?? '' ) . ' ' . ( $twGm['time'] ?? '' ) ); }
		$fav = $twGm['spread'] === null || $twGm['spread'] === '' ? '' : ( (float) $twGm['spread'] > 0 ? $twGm['home'] . ' −' . (float) $twGm['spread'] : ( (float) $twGm['spread'] < 0 ? $twGm['away'] . ' −' . abs( (float) $twGm['spread'] ) : 'pick' ) );
		$twRk = $drank( $twOpp ); $twVal = $dval( $twOpp ); $twTier = DBD_Grades::defense_tier( $twRk ); $tierWord = array( 'tough' => 'a tough matchup', 'mid' => 'a middle-of-the-pack matchup', 'soft' => 'a soft matchup' )[ $twTier ] ?? ''; $tierName = array( 'tough' => 'top-10', 'mid' => 'middle', 'soft' => 'bottom-10' )[ $twTier ] ?? '';
		/* his line against defenses of that tier this season, and the last meeting with this opponent */
		$uDef = $grp === 'rec' ? array( function ( $w ) { return $w['targets']; }, 'targets', function ( $w ) { return $w['receiving_yards']; } ) : ( $grp === 'rb' ? array( function ( $w ) { return $w['carries'] + $w['targets']; }, 'touches', function ( $w ) { return $w['rushing_yards'] + $w['receiving_yards']; } ) : array( function ( $w ) { return $w['attempts']; }, 'attempts', function ( $w ) { return $w['passing_yards']; } ) );
		$twSame = array(); $twMet = null;
		foreach ( $L as $w ) { $o = $w['opponent_team'] ?? $oppOf( (int) $w['week'], $w['team'] ?? null ); if ( $o && DBD_Grades::defense_tier( $drank( $o ) ) === $twTier && $twTier !== '' ) { $twSame[] = $w; } if ( $o === $twOpp ) { $twMet = $w; } }
		$twG = count( $twSame ); $twU = 0; $twY = 0; foreach ( $twSame as $w ) { $twU += (float) $uDef[0]( $w ); $twY += (float) $uDef[2]( $w ); }
		$lineOf = function ( $w ) use ( $grp ) { if ( $grp === 'rec' ) { return (int) $w['receptions'] . ' catch' . ( (int) $w['receptions'] === 1 ? '' : 'es' ) . ', ' . (int) $w['receiving_yards'] . ' yards' . ( $w['receiving_tds'] ? ', ' . (int) $w['receiving_tds'] . ' TD' : '' ); }
			if ( $grp === 'rb' ) { return (int) $w['carries'] . ' carries for ' . (int) $w['rushing_yards'] . ( $w['targets'] ? ', ' . (int) $w['receptions'] . ' catch' . ( (int) $w['receptions'] === 1 ? '' : 'es' ) . ' for ' . (int) $w['receiving_yards'] : '' ) . ( ( $w['rushing_tds'] + $w['receiving_tds'] ) ? ', ' . (int) ( $w['rushing_tds'] + $w['receiving_tds'] ) . ' TD' : '' ); }
			return (int) $w['completions'] . ' of ' . (int) $w['attempts'] . ' for ' . (int) $w['passing_yards'] . ( $w['passing_tds'] ? ', ' . (int) $w['passing_tds'] . ' TD' : '' ) . ( $w['passing_interceptions'] ? ', ' . (int) $w['passing_interceptions'] . ' INT' : '' ); };
		/* status: the injury line and his snap share over the last three games */
		$snk = array_map( 'intval', array_keys( $p['snaps'] ?? array() ) ); sort( $snk ); $last3 = array_slice( $snk, -3 ); $snv = array(); foreach ( $last3 as $k ) { $snv[] = (float) $p['snaps'][ (string) $k ]; } $snAvg = $snv ? (int) round( array_sum( $snv ) / count( $snv ) ) : null;
		$stTxt = $p['inj'] ? $p['inj']['status'] . ( ! empty( $p['inj']['what'] ) ? ' · ' . $p['inj']['what'] : '' ) : 'No listing'; $stCls = ! $p['inj'] ? 'good' : ( preg_match( '/out|did not/i', (string) $p['inj']['status'] ) ? 'bad' : '' );
		echo '<section class="dbd-panel dbd-week"><div class="dbd-ph"><span class="dbd-lab"><b>' . ( $later ? 'Next up' : 'This week' ) . '</b> · Week ' . (int) $twGm['wk'] . ' · ' . ( $twHome ? 'vs' : 'at' ) . ' ' . esc_html( $oppNick ) . '</span><span class="dbd-badge">' . ( $bye ? 'Week ' . $curWk0 . ': bye' : esc_html( $kick ) ) . '</span></div><div class="dbd-week4">';
		echo '<div><h5>The game</h5><div class="big">' . DBD_Marks::tile( $twOpp, 30 ) . '<span>' . ( $twHome ? 'vs' : 'at' ) . ' ' . esc_html( $oppNick ) . '</span></div><div class="m">' . esc_html( $kick ) . '<br>' . esc_html( $twGm['stadium'] ?? '' ) . ( $fav ? '<br><b>' . esc_html( $fav ) . '</b> · O/U ' . esc_html( $twGm['total'] ?? '–' ) : '' ) . '</div></div>';
		echo '<div><h5>The defense ' . esc_html( $dk['lab'] ) . '</h5><div class="big">' . ( $twRk ? '<span class="dbd-dchip ' . $twTier . '" data-tip="' . esc_attr( $dtip( $twOpp ) ) . '" tabindex="0">' . esc_html( DBD_Grades::ordinal( $twRk ) . ' ' . $dk['lab'] ) . '</span>' : '<span class="dbd-dchip">not ranked yet</span>' ) . '</div><div class="m">' . ( $twVal === null ? 'Not ranked yet' : 'allow <b>' . $sgn( (float) $twVal ) . '</b> EPA ' . esc_html( $dk['unit'] ) . ( $dlg === null ? '' : ', league ' . $sgn( (float) $dlg ) ) ) . '<br>' . esc_html( $tierWord ) . '</div></div>';
		echo '<div><h5>His history</h5><div class="m">' . ( $twG ? 'vs ' . esc_html( $tierName ) . ' defenses: <b>' . $twG . '</b> game' . ( $twG === 1 ? '' : 's' ) . ', ' . number_format( $twU / $twG, 1 ) . ' ' . esc_html( $uDef[1] ) . ' a game, <b>' . round( $twY / $twG ) . '</b> yards a game' : ( $tierName ? 'No games against ' . esc_html( $tierName ) . ' defenses yet' : 'No ranked defenses yet' ) ) . '<br>' . ( $twMet ? 'Week ' . (int) $twMet['week'] . ' vs ' . esc_html( $twOpp ) . ': ' . esc_html( $lineOf( $twMet ) ) : 'First meeting this season' ) . '</div></div>';
		echo '<div><h5>His status</h5><div class="w ' . $stCls . '">' . esc_html( $stTxt ) . '</div><div class="m">' . ( $snAvg !== null ? 'Snap share, last ' . count( $snv ) . ': <b>' . $snAvg . '%</b>' : '' ) . '</div></div></div>';
		$twSay = $twRk ? esc_html( ucfirst( $tierWord ) ) . ': the ' . esc_html( $oppNick ) . ' rank <b>' . esc_html( DBD_Grades::ordinal( $twRk ) ) . '</b> ' . esc_html( $dk['lab'] ) . ', allowing ' . $sgn( (float) $twVal ) . ' EPA ' . esc_html( $dk['unit'] ) . '.' : 'The ' . esc_html( $oppNick ) . ' are not ranked ' . esc_html( $dk['lab'] ) . ' yet.';
		if ( $twG ) { $twSay .= ' He has averaged <b>' . round( $twY / $twG ) . '</b> yards a game against ' . esc_html( $tierName ) . ' defenses this season' . ( $twMet ? ', and he went ' . esc_html( $lineOf( $twMet ) ) . ' against them in Week ' . (int) $twMet['week'] . '.' : '.' ); }
		elseif ( $twMet ) { $twSay .= ' He went ' . esc_html( $lineOf( $twMet ) ) . ' against them in Week ' . (int) $twMet['week'] . '.'; }
		if ( $bye ) { $twSay = 'Week ' . $curWk0 . ' is the bye. ' . $twSay; }
		echo '<p class="dbd-week-say">' . $twSay . '</p></section>';
	}
}

$weekHtml = ob_get_clean();

/* ---- bands by question (SCOPE §20): a sticky jump nav, then five bands. The rail is gone. */
echo '<nav class="dbd-jump" aria-label="On this page"><a href="#dbd-good">How good is he</a><a href="#dbd-used">How he\'s used</a><a href="#dbd-with" id="dbd-jump-with" hidden>Who he works with</a><a href="#dbd-weeks">Week by week</a>' . ( $CR ? '<a href="#dbd-career">Career</a>' : '' ) . '<a href="#dbd-more">Intel &amp; plays</a></nav>';
/* SCOPE §31: one panel per band question. Every pane is rendered (crawlers and the share card see it all); the tab picks which one shows.
   A pane loses its own title (the tab names it) and keeps its sub-label and badge. Empty panes are skipped. */
$tabbed = function ( $panes ) {
	$panes = array_values( array_filter( $panes, function ( $x ) { return trim( (string) $x[1] ) !== ''; } ) );
	if ( ! $panes ) { return ''; }
	if ( count( $panes ) === 1 ) { return $panes[0][1]; }
	$btn = ''; $body = '';
	foreach ( $panes as $i => $x ) {
		$btn .= '<button class="t-tab" type="button" role="tab" aria-selected="' . ( $i ? 'false' : 'true' ) . '" data-pane="' . $i . '">' . esc_html( $x[0] ) . '</button>';
		$h = preg_replace( '/<section class="dbd-panel([^"]*)"/', '<section class="dbd-panel dbd-pane$1" data-pane="' . $i . '"' . ( $i ? ' hidden' : '' ), $x[1], 1 );
		$h = preg_replace( '/<span class="dbd-lab">(<b[^>]*>.*?<\/b>)\s*·\s*(.*?)<\/span>/s', '<span class="dbd-lab">$1<span class="sub">$2</span></span>', $h, 1 );
		$body .= $h;
	}
	return '<section class="dbd-panel dbd-tabbed"><div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>' . $btn . '</div></div>' . $body . '</section>';
};
$band = function ( $id, $kick, $sub, $hidden = false ) { return '<section class="dbd-band" id="dbd-' . $id . '"' . ( $hidden ? ' hidden' : '' ) . '><h2 class="dbd-bandh">' . $kick . '<span>' . $sub . '</span></h2>'; };
echo $band( 'good', 'How good is he', 'his line against the league at his position' );

/* ---- scorecard (SCOPE §31): Where he ranks and Season stats were the same stats twice. One table: value, rank, the league bar, percentile.
   The unranked counting stats sit on the hero tiles. Two columns of rows on a wide screen, one on a phone. */
$gr = $p['grades'] ?? null; $ggrp = $gr['grp'] ?? $grp; $gi = $gr ? DBD_Grades::group_info( $ggrp ) : null; $N = (int) ( $gi['n'] ?? 0 ); $graded = $gr && ! empty( $gr['q'] ) && $N > 0;
$scRows = array();
if ( $gr && $gi ) {
	foreach ( DBD_Grades::defs( $ggrp ) as $d ) {
		list( $k, $lab, $sub, $kind ) = $d; $v = $gr['m'][ $k ] ?? null; $pc = $graded ? DBD_Grades::pctile( $ggrp, $k, $v ) : null;
		$rk = $pc === null ? null : DBD_Grades::rank( $ggrp, $k, $v ); $med = DBD_Grades::median( $ggrp, $k ); $Nk = count( $gi['dist'][ $k ] ?? array() );
		$tip = '<b>' . esc_html( $p['name'] ) . '</b><br>' . esc_html( $lab ) . ': <b>' . esc_html( DBD_Grades::fmt( $kind, $v ) ) . '</b>' . ( $pc === null ? '' : ' · ' . esc_html( DBD_Grades::ordinal( $pc ) ) . ' percentile · ' . esc_html( DBD_Grades::ordinal( $rk ) ) . ' of ' . $Nk ) . '<br><span class="m">League median ' . esc_html( DBD_Grades::fmt( $kind, $med ) ) . ( $sub ? ' · ' . esc_html( $sub ) : '' ) . '</span>';
		$scRows[] = array( 'lab' => $lab, 'sub' => $sub, 'v' => DBD_Grades::fmt( $kind, $v ), 'rk' => $rk ? array( $rk, 0 ) : null, 'n' => $Nk, 'pc' => $pc, 'tip' => $tip );
	}
}
if ( $EP ) {  /* SCOPE §29: success rate, ANY/A (quarterbacks) and total EPA, ranked in the same pool, with the percentiles the build stores */
	$er = (array) ( $EP['rk'] ?? array() ); $ep_pc = (array) ( $EP['pc'] ?? array() );
	$epRow = function ( $k, $lab, $val ) use ( $er, $ep_pc, $graded, $N, $p ) { $pc = $graded && isset( $ep_pc[ $k ] ) ? (int) $ep_pc[ $k ] : null;
		return array( 'lab' => $lab, 'sub' => '', 'v' => $val, 'rk' => $graded && ! empty( $er[ $k ] ) ? $er[ $k ] : null, 'n' => $N, 'pc' => $pc, 'tip' => '<b>' . esc_html( $p['name'] ) . '</b><br>' . esc_html( $lab ) . ': <b>' . esc_html( $val ) . '</b>' . ( $pc === null ? '' : ' · ' . DBD_Grades::ordinal( $pc ) . ' percentile' ) ); };
	$scRows[] = $epRow( 'sr', 'Success rate', $pct( (float) $EP['sr'] ) );
	if ( $grp === 'qb' && isset( $EP['any'] ) ) { $scRows[] = $epRow( 'any', 'ANY/A', number_format( (float) $EP['any'], 2 ) ); }
	$scRows[] = $epRow( 'tot', 'Total EPA', $sgn( (float) $EP['tot'], 1 ) );
}
if ( $scRows ) {
	$scTr = function ( $r ) { $rk = $r['rk']; $pc = $r['pc'];
		$rkh = $rk ? '<b>' . ( ! empty( $rk[1] ) ? 'T-' : '' ) . esc_html( DBD_Grades::ordinal( (int) $rk[0] ) ) . '</b> of ' . (int) $r['n'] : '';
		return '<tr' . ( $rk && $rk[0] <= ceil( $r['n'] * 0.1 ) ? ' class="top"' : '' ) . '><td class="n">' . esc_html( $r['lab'] ) . ( $r['sub'] ? '<small>' . esc_html( $r['sub'] ) . '</small>' : '' ) . '</td><td class="v">' . esc_html( $r['v'] ) . '</td><td class="rk">' . $rkh . '</td>'
			. '<td class="b"><div class="bar">' . ( $pc === null ? '' : '<i style="--p:' . (int) $pc . '" data-tip="' . esc_attr( $r['tip'] ) . '" tabindex="0" role="img" aria-label="' . esc_attr( $r['lab'] . ' ' . $r['v'] . ', ' . DBD_Grades::ordinal( $pc ) . ' percentile' ) . '"></i>' ) . '</div></td>'
			. '<td class="g' . ( $pc !== null && $pc >= 75 ? ' hi' : ( $pc !== null && $pc <= 25 ? ' lo' : '' ) ) . '">' . ( $pc === null ? '' : esc_html( DBD_Grades::ordinal( $pc ) ) ) . '</td></tr>'; };
	$scHead = '<thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead>'; $half = (int) ceil( count( $scRows ) / 2 );
	echo '<section class="dbd-panel dbd-score"><div class="dbd-ph"><span class="dbd-lab"><b>Scorecard</b> · ' . ( $gi ? 'among ' . $N . ' ' . esc_html( DBD_Grades::pool_label( $ggrp ) ) . ' with ' . esc_html( $gi['min'] ) . '+' : 'this season' ) . '</span><span class="dbd-badge">' . ( $graded ? 'League percentiles' : 'graded at ' . esc_html( $gi['min'] ?? '' ) ) . '</span></div>';
	echo '<div class="dbd-score2"><table class="dbd-stat">' . $scHead . '<tbody>' . implode( '', array_map( $scTr, array_slice( $scRows, 0, $half ) ) ) . '</tbody></table><table class="dbd-stat">' . $scHead . '<tbody>' . implode( '', array_map( $scTr, array_slice( $scRows, $half ) ) ) . '</tbody></table></div>';
	echo '<p class="dbd-note">' . ( $graded ? 'Each bar is the league at his position; the marker is him. Right is better, the dashed line is the median. Ranks are out of the qualifying pool, ties shown as T-, and move every week as the pool changes.' : 'Graded once he reaches ' . esc_html( $gi['min'] ?? 'the qualifier' ) . '. ' . $N . ' ' . esc_html( DBD_Grades::pool_label( $ggrp ) ) . ' qualify so far.' ) . '</p></section>';
}

/* ---- fantasy (SCOPE §34): points in every format, rank, range, booms and busts, the weeks, the next matchup. From the player file's fan block; older data skips it. */
list( $fxHtml, $fxShort ) = DBD_Fantasy::panel( $p, $a );
echo $fxHtml;

echo '</section>';
echo $band( 'used', 'How he\'s used', 'his fingerprint, where the ball finds him and when' );

$usedP = array();  /* SCOPE §31: the band's panels become tabs */
ob_start();
/* ---- playstyle fingerprint (SCOPE §23): eight usage traits against the league pool at his position, tags, and a generated read. From the player file's fp and league.json's fp pools; an older data file skips the panel. */
$FP = $p['fp'] ?? null; $fgi = DBD_Grades::group_info( $grp ); $fpool = $fgi['fp'] ?? null;
if ( $FP && $fpool ) {
	$fdefs = DBD_Grades::fp_defs( $grp ); $funit = DBD_Grades::fp_unit( $grp ); $fq = ! empty( $p['grades']['q'] ); $Nf = (int) ( $fgi['n'] ?? 0 ); $fpl = DBD_Grades::pool_label( $grp );
	$fshare = $FP['share'] ?? null; $pcs = function ( $v ) { return $v === null ? '–' : round( $v * 100 ) . '%'; };
	$TR = array();
	foreach ( $fdefs as $d ) { $v = $FP[ $d[0] ] ?? null; $pc = $fq ? DBD_Grades::pctile( $grp, $d[0], $v, 'fp' ) : null; $lg = DBD_Grades::median( $grp, $d[0], 'fp' ); $TR[] = array( 'd' => $d, 'v' => $v, 'pc' => $pc, 'lg' => $lg, 'dev' => $pc === null ? -1 : abs( $pc - 50 ) ); }
	/* bars */
	$fbars = ''; $fbarRows = array();
	foreach ( $TR as $ft ) { $d = $ft['d']; $vs = DBD_Grades::fp_fmt( $d[3], $ft['v'] );
		$tip = '<b>' . esc_html( $d[1] ) . '</b> ' . esc_html( $vs ) . ( $ft['lg'] === null ? '' : ' <span class="m">league median ' . esc_html( DBD_Grades::fp_fmt( $d[3], $ft['lg'] ) ) . '</span>' ) . ( $ft['pc'] === null ? '' : '<br>' . esc_html( DBD_Grades::ordinal( $ft['pc'] ) ) . ' percentile of ' . count( $fpool[ $d[0] ] ?? array() ) . ' ' . esc_html( $fpl ) );
		$fbarRows[] = '<div class="row"><div class="l">' . esc_html( $d[1] ) . '<small>' . esc_html( $d[2] ) . '</small></div><div class="bar">' . ( $ft['pc'] === null ? '' : '<i style="--p:' . (int) $ft['pc'] . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0" aria-label="' . esc_attr( $d[1] . ' ' . $vs ) . '"></i>' ) . '</div><div class="v">' . esc_html( $vs ) . '</div><div class="g ' . ( $ft['pc'] !== null && $ft['pc'] >= DBD_Grades::FP_HI ? 'hi' : ( $ft['pc'] !== null && $ft['pc'] <= DBD_Grades::FP_LO ? 'lo' : '' ) ) . '">' . ( $ft['pc'] === null ? '–' : esc_html( DBD_Grades::ordinal( $ft['pc'] ) ) ) . '</div></div>'; $fbars .= end( $fbarRows );
	}
	/* the read and the tags */
	$fsay = array(); $ftags = array();
	$sentence = function ( $ft, $hi ) use ( $first, $fshare, $pcs ) { $d = $ft['d']; return esc_html( strtr( $hi ? $d[6] : $d[7], array( '{F}' => $first, '{v}' => DBD_Grades::fp_fmt( $d[3], $ft['v'] ), '{l}' => DBD_Grades::fp_fmt( $d[3], $ft['lg'] ), '{sh}' => $pcs( $fshare ) ) ) ); };
	$tagOf = function ( $ft, $hi ) { $d = $ft['d']; $w = $hi ? $d[4] : $d[5]; return $w === '' ? null : array( 'w' => $w, 'hi' => $hi, 'tip' => $d[1] . ' ' . DBD_Grades::fp_fmt( $d[3], $ft['v'] ) . ', ' . DBD_Grades::ordinal( $ft['pc'] ) . ' percentile', 'sub' => $d[1] . ' ' . DBD_Grades::fp_fmt( $d[3], $ft['v'] ) . ' · ' . DBD_Grades::ordinal( $ft['pc'] ) . ' pctl' ); };
	if ( ! $fq ) { $fsay[] = esc_html( $first . ' gets a fingerprint once he reaches ' . ( $fgi['min'] ?? 'the qualifier' ) . '; the bars show his shares so far without a league percentile.' ); }
	else {
		$stand = array_values( array_filter( $TR, function ( $ft ) { return $ft['d'][6] !== null && $ft['pc'] !== null && ( $ft['pc'] >= DBD_Grades::FP_HI || $ft['pc'] <= DBD_Grades::FP_LO ); } ) );
		usort( $stand, function ( $x, $y ) { return $y['dev'] <=> $x['dev']; } );
		$used = array();
		foreach ( array_slice( $stand, 0, 2 ) as $ft ) { $hi = $ft['pc'] >= DBD_Grades::FP_HI; $fsay[] = $sentence( $ft, $hi ); $used[] = $ft['d'][0]; if ( $ftg = $tagOf( $ft, $hi ) ) { $ftags[] = $ftg; } }
		if ( $grp !== 'qb' && $fshare !== null ) {
			$mv = function ( $v ) use ( $fshare ) { return $v === null ? null : ( $v - $fshare >= DBD_Grades::FP_MOVE ? 'climbs' : ( $fshare - $v >= DBD_Grades::FP_MOVE ? 'falls' : 'holds' ) ); };
			$part = function ( $lab, $v ) use ( $mv, $pcs ) { $m = $mv( $v ); return $m === null ? '' : ( $m === 'holds' ? $lab . ' it holds at ' . $pcs( $v ) : $lab . ' it ' . $m . ' to ' . $pcs( $v ) ); };
			$parts = array_filter( array( $part( 'on third down', $FP['d3'] ?? null ), $part( 'inside the 20', $FP['rzs'] ?? null ) ) );
			if ( $parts ) { $fsay[] = esc_html( 'His share of the team\'s ' . $funit . ' is ' . $pcs( $fshare ) . ': ' . implode( '; ', $parts ) . '.' ); }
			foreach ( array( array( 'd3', 'on third down' ), array( 'rzs', 'inside the 20' ) ) as $sk ) { $m = $mv( $FP[ $sk[0] ] ?? null ); $dd = null; foreach ( $fdefs as $d ) { if ( $d[0] === $sk[0] ) { $dd = $d; } }
				if ( $dd && $m === 'climbs' ) { $ftags[] = array( 'w' => $dd[4], 'hi' => true, 'tip' => $pcs( $FP[ $sk[0] ] ) . ' of the team\'s ' . $funit . ' ' . $sk[1], 'sub' => $pcs( $FP[ $sk[0] ] ) . ' ' . $sk[1] . ' · ' . $pcs( $fshare ) . ' overall' ); }
				elseif ( $dd && $m === 'falls' ) { $ftags[] = array( 'w' => $dd[5], 'hi' => false, 'tip' => $pcs( $FP[ $sk[0] ] ) . ' ' . $sk[1] . ' against ' . $pcs( $fshare ) . ' overall', 'sub' => $pcs( $FP[ $sk[0] ] ) . ' ' . $sk[1] . ' · ' . $pcs( $fshare ) . ' overall' ); } }
		}
		$third = null; foreach ( array_slice( $stand, 2 ) as $ft ) { if ( ! in_array( $ft['d'][0], $used, true ) ) { $third = $ft; break; } }
		if ( $third ) { $hi = $third['pc'] >= DBD_Grades::FP_HI; $fsay[] = $sentence( $third, $hi ); if ( $ftg = $tagOf( $third, $hi ) ) { $ftags[] = $ftg; } }
		$anyExt = (bool) array_filter( $TR, function ( $ft ) { return $ft['pc'] !== null && ( $ft['pc'] >= DBD_Grades::FP_HI || $ft['pc'] <= DBD_Grades::FP_LO ); } );  /* all eight, the situational pair included */
		if ( ! $anyExt ) { array_unshift( $fsay, esc_html( $first . ' profiles as a typical ' . ( $grp === 'rec' ? 'receiver' : ( $grp === 'rb' ? 'back' : 'quarterback' ) ) . ' in how he is used: nothing in his fingerprint sits outside the league\'s middle half.' ) ); }
	}
	/* SCOPE §35: each tag is a small card, an arrow for which end of the league he sits at, the tag, and the stat behind it */
	$ftagh = ''; foreach ( array_slice( $ftags, 0, 4 ) as $ftg ) { $ftagh .= '<span class="dbd-fp-tag' . ( $ftg['hi'] ? ' hi' : ' lo' ) . '" data-tip="' . esc_attr( $ftg['tip'] ) . '" tabindex="0"><i aria-hidden="true">' . ( $ftg['hi'] ? '▲' : '▼' ) . '</i><span><b>' . esc_html( $ftg['w'] ) . '</b>' . ( ! empty( $ftg['sub'] ) ? '<small>' . esc_html( $ftg['sub'] ) . '</small>' : '' ) . '</span></span>'; }
	echo '<section class="dbd-panel dbd-fp"><div class="dbd-ph"><span class="dbd-lab"><b>Playstyle</b> · how he is used, against the league at his position</span><span class="dbd-badge">' . ( $fq ? 'among ' . $Nf . ' ' . esc_html( $fpl ) : 'graded at ' . esc_html( $fgi['min'] ?? '' ) ) . '</span></div>';
	/* SCOPE §35: the read and tags across the top, then the traits in two columns of four, each with its own header */
	$fhd = '<div class="hd"><span>Trait</span><span>League<em> · line is the median</em></span><span>Him</span><span>Pctl</span></div>';
	$fcut = (int) ceil( count( $fbarRows ) / 2 );
	echo '<div class="dbd-fp1"><p class="dbd-fp-say">' . implode( ' ', $fsay ) . '</p>' . ( $ftagh ? '<div class="dbd-fp-tags">' . $ftagh . '</div>' : '' )
		. '<div class="dbd-pct dbd-fpcols"><div class="col">' . $fhd . implode( '', array_slice( $fbarRows, 0, $fcut ) ) . '</div><div class="col">' . $fhd . implode( '', array_slice( $fbarRows, $fcut ) ) . '</div></div></div>';
	echo '<p class="dbd-note">Every trait is a share of his own ' . esc_html( $funit ) . ', so volume does not move it. The marker is him among the league\'s qualifying ' . esc_html( $fpl ) . '; the dashed line is the median. Styles are not grades: a high or low percentile is a description, not a verdict. Play action, screen, RPO, motion and blitz flags come from FTN charting and read low in a week it has not published.</p></section>';
}
$usedP[] = array( 'Playstyle', ob_get_clean() );
ob_start();
/* ---- where his EPA comes from (SCOPE §29): the season's EPA split by what happened, each part's per-play rate and league percentile, a read, and this season against his career */
if ( $EP ) {
	$epQ = $grp === 'qb'; $epRB = $grp === 'rb'; $epPool = DBD_Grades::pool_label( $grp ); $epG = $graded && ! empty( $EP['rk']['tot'] );
	$EPL = array(  /* label, small print, where it comes from, what it is */
		'air' => array( 'Air yards', $epQ ? 'on completions' : 'on catches', 'in the air', 'air yards' ),
		'yac' => array( 'After the catch', $epQ ? 'his share, catch to whistle' : '', 'after the catch', 'yards after the catch' ),
		'inc' => array( 'Incompletions', $epQ ? 'spikes included' : 'targets not caught', '', 'incompletions' ),
		'int' => array( 'Interceptions', $epQ ? '' : 'when targeted', '', 'interceptions' ),
		'sk'  => array( 'Sacks', '', '', 'sacks' ),
		'scr' => array( 'Scrambles', '', 'on scrambles', 'scrambles' ),
		'run' => array( 'Designed runs', $epQ ? 'kneels included' : '', 'on runs', 'runs' ),
		'two' => array( 'Two-point tries', 'not in the rates', 'on two-point tries', 'two-point tries' ),
	);
	$epUnit = function ( $sk, $pk ) use ( $epQ, $epRB ) { if ( $epQ ) { return $pk === 'run' || $pk === 'two' ? '' : 'per dropback'; } return $sk === 'rush' ? 'per carry' : 'per target'; };
	$epSecL = array( 'pass' => 'Passing', 'rush' => 'Rushing', 'rec' => 'Receiving' );
	$epSecS = array( 'pass' => 'matches EPA / dropback above', 'rush' => '', 'rec' => '' );
	/* rows: parts, an Other row where play-by-play and the weekly line disagree, a subtotal between sections, the total */
	$epRows = array(); $nSec = count( $EP['sec'] );
	foreach ( $EP['sec'] as $si => $sec ) {
		list( $epSk, $stot, $soth, $sparts ) = $sec;
		foreach ( $sparts as $pt ) { $epRows[] = array( 'kind' => 'part', 'sk' => $epSk, 'k' => $pt[0], 'lab' => $EPL[ $pt[0] ][0] ?? $pt[0], 'sm' => $EPL[ $pt[0] ][1] ?? '', 'v' => (float) $pt[1], 'per' => $pt[2], 'pc' => $pt[3], 'unit' => $epUnit( $epSk, $pt[0] ) ); }
		if ( abs( (float) $soth ) >= 0.1 ) { $epRows[] = array( 'kind' => 'part', 'sk' => $epSk, 'k' => 'oth', 'lab' => 'Other', 'sm' => 'fumbles, laterals, stat corrections', 'v' => (float) $soth, 'per' => null, 'pc' => null, 'unit' => '' ); }
		if ( $nSec > 1 && $si < $nSec - 1 && count( $sparts ) > 1 ) { $epRows[] = array( 'kind' => 'sub', 'lab' => $epSecL[ $epSk ], 'sm' => $epSecS[ $epSk ] ?? '', 'v' => (float) $stot, 'per' => null, 'pc' => null, 'unit' => '' ); }
	}
	foreach ( $epRows as &$er_ ) { if ( $er_['kind'] === 'sub' && $er_['lab'] === 'Passing' && ( $att + $sk ) ) { $er_['per'] = $pepa / ( $att + $sk ); $er_['unit'] = 'per dropback'; } } unset( $er_ );
	$epTotPer = $grp === 'rec' ? ( $tg ? (float) $EP['tot'] / $tg : null ) : ( $epRB && ( $ca + $tg ) ? (float) $EP['tot'] / ( $ca + $tg ) : null );  /* matches the EPA / target and EPA / touch tiles */
	$epRk = $EP['rk']['tot'] ?? null; $epRkL = $epG ? ( ! empty( $epRk[1] ) ? 'T-' : '' ) . DBD_Grades::ordinal( (int) $epRk[0] ) : '';
	$epRows[] = array( 'kind' => 'total', 'lab' => 'Total EPA', 'sm' => $grp === 'rec' ? 'matches EPA / target above' : ( $epRB ? 'matches EPA / touch above' : '' ), 'v' => (float) $EP['tot'], 'per' => $epTotPer, 'pc' => null, 'unit' => $grp === 'rec' ? 'per target' : ( $epRB ? 'per touch' : '' ), 'rk' => $epRkL );
	/* geometry: parts float from the running total, subtotals and the total stand on zero */
	$run = 0.0; $ends = array( 0.0 );
	foreach ( $epRows as &$er_ ) { if ( $er_['kind'] === 'part' ) { $er_['a'] = $run; $run += $er_['v']; $er_['b'] = $run; } else { $er_['a'] = 0.0; $er_['b'] = $er_['v']; $run = $er_['v']; } $ends[] = $er_['a']; $ends[] = $er_['b']; } unset( $er_ );
	$epLo = min( $ends ); $epHi = max( $ends ); $epPad = ( $epHi - $epLo ) * 0.04; if ( $epLo < 0 ) { $epLo -= $epPad; } $epHi += $epPad; $epSpan = ( $epHi - $epLo ) ?: 1;
	$epX = function ( $v ) use ( $epLo, $epSpan ) { return round( ( $v - $epLo ) / $epSpan * 100, 2 ); };
	$epWf = '<div class="row hd"><div class="l"></div><div class="tr"></div><div class="v">EPA</div><div class="p">' . ( $epQ ? 'Per dropback' : ( $epRB ? 'Per play' : 'Per target' ) ) . '</div><div class="g">Pctl</div></div>';
	foreach ( $epRows as $er_ ) {
		$l = $epX( min( $er_['a'], $er_['b'] ) ); $w = max( 0.4, abs( $epX( $er_['b'] ) - $epX( $er_['a'] ) ) );
		$cls = $er_['kind'] === 'part' ? ( $er_['v'] >= 0 ? 'pos' : 'neg' ) : $er_['kind'];
		$pc = $er_['pc']; $gtxt = $pc !== null ? DBD_Grades::ordinal( $pc ) : ( $er_['rk'] ?? '' ); $gc = $pc !== null && $pc >= 75 ? ' hi' : ( $pc !== null && $pc <= 25 ? ' lo' : '' );
		$tip = '<b>' . esc_html( $er_['lab'] ) . '</b>: ' . $sgn( $er_['v'], 1 ) . ' EPA' . ( $er_['per'] === null ? '' : ' · ' . $sgn( (float) $er_['per'], 3 ) . ' ' . esc_html( $er_['unit'] ) ) . ( $pc === null ? '' : '<br>' . DBD_Grades::ordinal( $pc ) . ' percentile among ' . $N . ' ' . esc_html( $epPool ) ) . ( ! empty( $er_['rk'] ) ? '<br>' . esc_html( $er_['rk'] ) . ' of ' . $N . ' ' . esc_html( $epPool ) : '' );
		$epWf .= '<div class="row ' . $er_['kind'] . '"><div class="l">' . esc_html( $er_['lab'] ) . ( $er_['sm'] ? '<small>' . esc_html( $er_['sm'] ) . '</small>' : '' ) . '</div><div class="tr"><i class="z" style="left:' . $epX( 0 ) . '%"></i><i class="b ' . $cls . '" style="left:' . $l . '%;width:' . round( $w, 2 ) . '%" data-tip="' . esc_attr( $tip ) . '" tabindex="0"></i><i class="c" style="left:' . $epX( $er_['b'] ) . '%"></i></div>'
			. '<div class="v">' . $sgn( $er_['v'], 1 ) . '</div><div class="p">' . ( $er_['per'] === null ? '' : $sgn( (float) $er_['per'], 3 ) ) . '</div><div class="g' . $gc . '">' . esc_html( $gtxt ) . '</div></div>';
	}
	/* the read: his place, where most of it comes from, a standout and a cost */
	$epParts = array_values( array_filter( $epRows, function ( $x ) { return $x['kind'] === 'part' && $x['k'] !== 'oth' && $x['k'] !== 'two'; } ) );
	$epSay = array( $epG ? esc_html( $last ) . '\'s <b>' . $sgn( (float) $EP['tot'], 1 ) . '</b> EPA is <b>' . esc_html( $epRkL ) . '</b> of ' . $N . ' ' . esc_html( $epPool ) . '.' : esc_html( $last ) . ' has <b>' . $sgn( (float) $EP['tot'], 1 ) . '</b> EPA on ' . (int) $EP['u'] . ' ' . ( $epQ ? 'dropbacks' : ( $epRB ? 'touches' : 'targets' ) ) . '; percentiles arrive once he reaches ' . esc_html( $gi['min'] ?? 'the qualifier' ) . '.' );
	$gains = array_values( array_filter( $epParts, function ( $x ) { return $x['v'] > 0; } ) ); usort( $gains, function ( $x, $y ) { return $y['v'] <=> $x['v']; } ); $named = array();
	if ( $gains && ( $EPL[ $gains[0]['k'] ][2] ?? '' ) ) { $g0 = $gains[0]; $named[] = $g0['k']; $epSay[] = 'The biggest share comes <b>' . esc_html( $EPL[ $g0['k'] ][2] ) . '</b> (' . $sgn( $g0['v'], 1 ) . ( $g0['pc'] !== null ? ', ' . DBD_Grades::ordinal( $g0['pc'] ) . ' percentile ' . esc_html( $g0['unit'] ?: 'among ' . $epPool ) : '' ) . ').'; }
	if ( $epG ) {
		$best = null; $worst = null;
		foreach ( $epParts as $x ) { if ( $x['pc'] === null || in_array( $x['k'], $named, true ) ) { continue; } if ( $x['pc'] >= 90 && ( ! $best || $x['pc'] > $best['pc'] ) ) { $best = $x; } if ( $x['pc'] <= 25 && ( ! $worst || $x['pc'] < $worst['pc'] ) ) { $worst = $x; } }
		if ( $best ) { $epSay[] = 'His <b>' . esc_html( $EPL[ $best['k'] ][3] ) . '</b> rank in the ' . DBD_Grades::ordinal( $best['pc'] ) . ' percentile (' . $sgn( $best['v'], 1 ) . ').'; }
		if ( $worst ) { $epSay[] = '<b>' . esc_html( ucfirst( $EPL[ $worst['k'] ][3] ) ) . '</b> ' . ( $worst['v'] < 0 ? 'cost him more than most' : 'add less than most' ) . ' (' . $sgn( $worst['v'], 1 ) . ', ' . DBD_Grades::ordinal( $worst['pc'] ) . ' percentile).'; }
	}
	/* this season against his career, per play, from the career rows (seasons that met the qualifier) */
	$epCar = '';
	if ( $CR ) {
		$cv = function ( $r ) use ( $grp ) { if ( $grp === 'qb' ) { $d = (float) ( $r['attempts'] ?? 0 ) + (float) ( $r['sacks_suffered'] ?? 0 ); return array( (float) ( $r['passing_epa'] ?? 0 ), $d, (float) ( $r['attempts'] ?? 0 ) >= 50 ); }
			if ( $grp === 'rec' ) { $d = (float) ( $r['targets'] ?? 0 ); return array( (float) ( $r['receiving_epa'] ?? 0 ), $d, $d >= 15 ); }
			$d = (float) ( $r['carries'] ?? 0 ) + (float) ( $r['targets'] ?? 0 ); return array( (float) ( $r['rushing_epa'] ?? 0 ) + (float) ( $r['receiving_epa'] ?? 0 ), $d, $d >= 30 ); };
		$prior = array(); $cE = 0.0; $cD = 0.0;
		foreach ( $CR as $r ) { if ( (int) ( $r['season'] ?? 0 ) >= (int) $season ) { continue; } list( $ce, $cd, $cq ) = $cv( $r ); if ( $cq && $cd > 0 ) { $prior[ (int) $r['season'] ] = $ce / $cd; $cE += $ce; $cD += $cd; } }
		$nowV = $grp === 'qb' ? ( ( $att + $sk ) ? $pepa / ( $att + $sk ) : null ) : ( $grp === 'rec' ? ( $tg ? $repa / $tg : null ) : ( ( $ca + $tg ) ? ( $ruepa + $repa ) / ( $ca + $tg ) : null ) );
		if ( $prior && $nowV !== null && $cD > 0 ) {
			ksort( $prior ); $prior = array_slice( $prior, -11, null, true ); $all = array_values( $prior ); $all[] = $nowV; $nS = count( $all );
			$place = 1 + count( array_filter( $all, function ( $v ) use ( $nowV ) { return $v > $nowV + 1e-9; } ) );
			$what = $grp === 'qb' ? 'passing EPA per dropback' : ( $grp === 'rec' ? 'EPA per target' : 'EPA per touch' ); $per = $grp === 'qb' ? 'per dropback' : ( $grp === 'rec' ? 'per target' : 'per touch' );
			$verdict = $place === 1 ? 'the best of his ' . $nS . ' seasons so far' : ( $place === $nS ? 'the lowest of his ' . $nS . ' seasons' : 'his ' . DBD_Grades::ordinal( $place ) . ' best of ' . $nS );
			$mx = max( array_map( 'abs', $all ) ) ?: 1; $bars = '';
			foreach ( $prior as $yr => $v ) { $bars .= '<i style="--h:' . round( abs( $v ) / $mx * 100 ) . '%"' . ( $v < 0 ? ' class="neg"' : '' ) . ' data-tip="' . esc_attr( '<b>' . $yr . '</b> ' . $sgn( $v ) . ' ' . $per ) . '" tabindex="0"><em>' . substr( (string) $yr, 2 ) . '</em></i>'; }
			$bars .= '<i style="--h:' . round( abs( $nowV ) / $mx * 100 ) . '%" class="now" data-tip="' . esc_attr( '<b>' . $season . '</b> ' . $sgn( $nowV ) . ' ' . $per . ', this season' ) . '" tabindex="0"><em>' . substr( (string) $season, 2 ) . '</em></i>';
			$ys = array_keys( $prior );
			$epCar = '<div class="dbd-ep-car"><div class="t"><b>' . $sgn( $nowV ) . '</b> ' . esc_html( $what ) . ' this season<br><span>career <b>' . $sgn( $cE / $cD ) . '</b> over ' . ( count( $ys ) > 1 ? reset( $ys ) . '–' . end( $ys ) : reset( $ys ) ) . ' · ' . esc_html( $verdict ) . '</span></div><div class="sp" role="img" aria-label="' . esc_attr( $what . ' by season' ) . '">' . $bars . '</div></div>';
		}
	}
	$epNote = $epQ ? 'Every dropback and run this season, split by what happened. Completions divide at the catch: the yards in the air, and his credited share of what came after (nflverse\'s air EPA and QB EPA). Per dropback counts scrambles as dropbacks.'
		: ( $epRB ? 'Every carry and target this season, split by what happened. Catches divide at the catch: the yards in the air and what came after. Runs are per carry, the receiving parts per target.' : 'Every target this season, split by what happened. Catches divide at the catch: the yards in the air and what came after.' );
	$epNote .= ' The percentile is him among the qualifying ' . $epPool . ', higher is better, so a cost smaller than most ranks high. The parts add up to the totals above; the career strip counts seasons with at least ' . esc_html( $gi['min'] ?? 'a qualifying load' ) . '.';
	echo '<section class="dbd-panel dbd-ep"><div class="dbd-ph"><span class="dbd-lab"><b>Where his EPA comes from</b> · ' . ( $epQ ? 'every dropback and run, split' : ( $epRB ? 'every carry and target, split' : 'every target, split' ) ) . '</span><span class="dbd-badge">' . ( $epG ? 'among ' . $N . ' ' . esc_html( $epPool ) : 'graded at ' . esc_html( $gi['min'] ?? '' ) ) . '</span></div>';
	echo '<p class="dbd-ep-say">' . implode( ' ', $epSay ) . '</p><div class="dbd-ep-wf">' . $epWf . '</div>' . $epCar . '<p class="dbd-note">' . $epNote . '</p></section>';
}

$usedP[] = array( 'Where his EPA comes from', ob_get_clean() );
ob_start();
/* ---- depth by direction (SCOPE §29): the 4 × 3 grid of his throws or targets against the league, two views. A back gets it with 15+ targets. */
$lzLg = DBD_Grades::league()['grid'][ $grp === 'qb' ? 'qb' : 'tgt' ] ?? null;
if ( $EP && ! empty( $EP['gr'] ) && $lzLg && ( $grp !== 'rb' || (int) ( $EP['tg'] ?? 0 ) >= 15 ) ) {
	$lzU = $grp === 'qb' ? array( 'throws', 'throw', 'attempts' ) : array( 'targets', 'target', 'targets' );
	$lzDep = array( array( 'deep', 'Deep', '20+ yds' ), array( 'inter', 'Intermediate', '10–19' ), array( 'short', 'Short', '0–9' ), array( 'behind', 'Behind', 'the line' ) );
	$lzCol = array( 'left' => 'Left', 'middle' => 'Middle', 'right' => 'Right' );
	$lzTot = array_sum( array_map( function ( $c ) { return (int) $c[0]; }, $EP['gr'] ) ) ?: 1;
	$lzCells = '<div class="rl"></div>'; foreach ( $lzCol as $cl ) { $lzCells .= '<div class="ch">' . $cl . '</div>'; }
	$lzBest = null;
	foreach ( $lzDep as $dd ) {
		if ( $dd[0] === 'behind' ) { $lzCells .= '<div class="los"><span>Line of scrimmage</span></div>'; }
		$lzCells .= '<div class="rl"><b>' . $dd[1] . '</b><small>' . $dd[2] . '</small></div>';
		foreach ( $lzCol as $ck => $cl ) {
			$key = $dd[0] . '-' . $ck; $c = $EP['gr'][ $key ] ?? array( 0, null, 0 ); $n = (int) $c[0]; $fr = $n / $lzTot; $lg = $lzLg[ $key ] ?? array( 0, null ); $lgs = (float) $lg[0];
			$ratio = $lgs ? $fr / $lgs : 1; $tf = $ratio > 1 ? max( 0, min( 0.3, ( $ratio - 1 ) * 0.28 ) ) : 0;
			$ev = $n >= 3 && $c[1] !== null ? (float) $c[1] : null; $te = $ev === null ? 0 : max( -0.55, min( 0.55, $ev * 0.45 ) ); $dlt = ( $fr - $lgs ) * 100;
			if ( $n >= 5 && $lgs && ( ! $lzBest || $ratio > $lzBest['r'] ) ) { $lzBest = array( 'r' => $ratio, 'n' => $n, 'f' => $fr, 'lg' => $lgs, 'e' => $ev, 'nm' => strtolower( $dd[1] ) . ' ' . $ck ); }
			$tip = '<b>' . $dd[1] . ' ' . $ck . '</b>: ' . $n . ' of ' . $lzTot . ' ' . $lzU[0] . ' (' . round( $fr * 100 ) . '%) · league ' . round( $lgs * 100 ) . '%' . ( $ev === null ? '' : '<br>' . $sgn( $ev ) . ' EPA per ' . $lzU[1] . ( $lg[1] === null ? '' : ' · league ' . $sgn( (float) $lg[1] ) ) ) . ( $grp === 'qb' && $n ? ' · ' . round( (int) $c[2] / $n * 100 ) . '% complete' : '' );
			$lzCells .= '<div class="cell' . ( $n < 3 ? ' low' : '' ) . '" style="--tf:' . round( $tf, 2 ) . ';--te:' . round( $te, 2 ) . '" data-tip="' . esc_attr( $tip ) . '" tabindex="0"><span class="f">' . round( $fr * 100 ) . '%</span><span class="d' . ( $dlt >= 1 ? ' up' : ( $dlt <= -1 ? ' dn' : '' ) ) . '">league ' . round( $lgs * 100 ) . '%</span>'
				. '<span class="e' . ( $ev === null ? '' : ( $ev > 0 ? ' pos' : ' neg' ) ) . '">' . ( $ev === null ? '–' : $sgn( $ev ) ) . '</span><span class="le">league ' . ( $lg[1] === null ? '–' : $sgn( (float) $lg[1] ) ) . '</span><span class="n">' . $n . ' ' . ( $n === 1 ? $lzU[1] : $lzU[0] ) . '</span></div>';
		}
	}
	$lzSay = $lzBest && $lzBest['r'] >= 1.15
		? ( $grp === 'qb' ? 'He throws' : 'He is targeted' ) . ' <b>' . esc_html( $lzBest['nm'] ) . '</b> ' . number_format( $lzBest['r'], 1 ) . '× as often as the league (' . round( $lzBest['f'] * 100 ) . '% of his ' . $lzU[2] . ' against ' . round( $lzBest['lg'] * 100 ) . '%)' . ( $lzBest['e'] === null ? '' : ( $lzBest['e'] >= 0 ? ' and gains <b>' : ' but averages <b>' ) . $sgn( $lzBest['e'] ) . '</b> EPA a ' . $lzU[1] . ' there' ) . '.'
		: 'His ' . $lzU[0] . ' spread much like the league\'s: no spot with five or more runs 15% above the league\'s share.';
	echo '<section class="dbd-panel dbd-lz" data-mode="f"><div class="dbd-ph"><span class="dbd-lab"><b>Depth by direction</b> · ' . ( $grp === 'qb' ? 'where he throws' : 'where he is targeted' ) . ', against the league</span>';
	echo '<div class="dbd-seg t-tabs dbd-lzv" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" data-m="f" aria-selected="true">How often</button><button class="t-tab" type="button" role="tab" data-m="e" aria-selected="false">EPA per ' . $lzU[1] . '</button></div></div>';
	echo '<p class="dbd-ep-say">' . $lzSay . '</p><div class="dbd-lzg">' . $lzCells . '</div><div class="dbd-lzk"><span class="k f"><i class="up"></i>more than the league <i class="dn"></i>less</span><span class="k e"><i class="pos"></i>gains EPA <i class="neg"></i>loses EPA</span></div>';
	echo '<p class="dbd-note">Every ' . ( $grp === 'qb' ? 'pass attempt' : 'target' ) . ' with a charted depth and direction this season; sacks and throws with no charted direction are left out. Left and right are as the offense sees the field. Cells with fewer than three ' . $lzU[0] . ' show no EPA.</p></section>';
}
$usedP[] = array( 'Depth by direction', ob_get_clean() );
ob_start();
/* ---- field map + depth bands (JS) */
echo '<section class="dbd-panel" id="dbd-pmap-panel"><div class="dbd-ph"><span class="dbd-lab"><b id="dbd-maplab">' . ( $grp === 'qb' ? 'Where he throws' : ( $grp === 'rb' ? 'Where he runs' : 'Where the ball finds him' ) ) . '</b> · from the play file</span><span class="dbd-badge" id="dbd-mapn">loading</span></div>';
echo '<div class="dbd-sprays"><div><h4 id="dbd-map1h">On the field</h4><div id="dbd-pmap"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div></div><div><h4>By depth of target</h4><div class="dbd-bands" id="dbd-bands"></div><p class="dbd-note" id="dbd-bandnote" style="padding:8px 0 0"></p></div></div>';
echo '<div class="dbd-legend" id="dbd-pleg"></div><p class="dbd-note" id="dbd-mapnote"></p></section>';

$usedP[] = array( 'On the field', ob_get_clean() );
ob_start();
/* ---- situations (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>When he gets it</b> · touches by situation</span></div><div class="dbd-wk3 dbd-tend" id="dbd-sit"></div></section>';
$usedP[] = array( 'Situations', ob_get_clean() );
echo $tabbed( $usedP ) . '</section>';
echo $band( 'with', 'Who he works with', 'the connection, and the rest of the huddle', true );

/* ---- the connection (JS, SCOPE §18): a quarterback's receivers, or everyone else's line by passer. Hidden until player.js finds targets. */
echo '<section class="dbd-panel dbd-cx" id="dbd-cx" hidden><div class="dbd-ph"><span class="dbd-lab"><b>The connection</b> · ' . ( $grp === 'qb' ? 'who he throws to' : 'his line by passer' ) . '</span><span class="dbd-badge" id="dbd-cx-n"></span></div>';
echo '<div class="dbd-tw"><table class="dbd-cx-tab' . ( $grp === 'qb' ? ' dbd-sortable' : '' ) . '" id="dbd-cx-tab"></table></div><p class="dbd-cx-say" id="dbd-cx-say"></p>';
echo '<div class="dbd-cx-2"><div><h4 id="dbd-cx-maph"></h4><div id="dbd-cx-map"></div><div class="dbd-cx-chips" id="dbd-cx-chips" role="group" aria-label="Isolate one ' . ( $grp === 'qb' ? 'receiver' : 'passer' ) . '"></div><p class="dbd-note" id="dbd-cx-mapnote" style="padding:8px 0 0"></p></div>';
echo '<div><h4 id="dbd-cx-wkh"></h4><div class="dbd-cx-wks" id="dbd-cx-wks"></div><div class="dbd-cx-wkl" id="dbd-cx-wkl"></div><h4>By depth</h4><div class="dbd-cx-dg" id="dbd-cx-dg"></div></div></div></section>';


echo '</section>' . $band( 'weeks', 'Week by week', 'the shape of his season, the game log and the defenses behind it' );

/* ---- the schedule, week by week per team: the trend panel and Who he's faced both read it */
$sched = DBD_Data::schedule(); $games = array(); $maxWk = 0; $curWk = (int) ( $sched['current_week'] ?? $through );
foreach ( (array) ( $sched['weeks'] ?? array() ) as $wkn => $gl ) {
	foreach ( (array) $gl as $g ) {
		$wi = (int) ( $g['week'] ?? $wkn ); $maxWk = max( $maxWk, $wi ); $fin = ( $g['status'] ?? '' ) === 'final' || ( isset( $g['home_score'] ) && $g['home_score'] !== null );
		$games[ $g['home'] ][] = array( 'wk' => $wi, 'opp' => $g['away'], 'home' => true, 'played' => $fin );
		$games[ $g['away'] ][] = array( 'wk' => $wi, 'opp' => $g['home'], 'home' => false, 'played' => $fin );
	}
}
foreach ( $games as &$gg ) { usort( $gg, function ( $x, $y ) { return $x['wk'] - $y['wk']; } ); } unset( $gg );

ob_start();
/* ---- the shape of his season (SCOPE §21): four per-game metrics, week by week, his trailing three games over the league's middle half */
$gxWeeks = array();  /* every league week as game, dnp, bye or ahead; the game log reads it too (SCOPE §29) */
if ( $L ) {
	$tdefs = DBD_Grades::trend_defs( $grp ); $WIN = DBD_Grades::TREND_WIN;
	/* his season by league week: a game (stats, or a zero line where he dressed), a DNP where his team played without him, a bye, or a week still ahead */
	$Lby = array(); foreach ( $L as $w ) { $Lby[ (int) $w['week'] ] = $w; }
	$rows = array(); $lastWk = max( 18, $maxWk );
	/* the team he belonged to each week: his own line where he played, otherwise the team of his nearest played week before it (or after it, before his first game), so a traded player's missed weeks read his old team's schedule, not the new one's */
	$teamAt = array(); $firstTm = null; foreach ( $Lby as $w ) { $firstTm = $w['team'] ?? $a; break; }
	$carry = $firstTm ?: $a; for ( $wk = 1; $wk <= $lastWk; $wk++ ) { if ( isset( $Lby[ $wk ] ) ) { $carry = $Lby[ $wk ]['team'] ?? $a; } $teamAt[ $wk ] = $carry; }
	$tmG = array(); foreach ( $games as $tmk => $gl ) { foreach ( $gl as $x ) { $tmG[ $tmk ][ $x['wk'] ] = $x; } }
	for ( $wk = 1; $wk <= $lastWk; $wk++ ) {
		$tm = $teamAt[ $wk ];
		if ( isset( $Lby[ $wk ] ) ) { $w = $Lby[ $wk ]; $gm = DBD_Data::team_game( $tm, $wk ); $opp = $w['opponent_team'] ?? $oppOf( $wk, $tm );
			$rows[ $wk ] = array( 'kind' => 'game', 'w' => $w, 'tm' => $tm, 'opp' => (string) $opp, 'home' => $gm ? $gm['home'] === $tm : true, 'res' => $gm && isset( $gm['home_score'], $gm['away_score'] ) ? ( ( $gm['home'] === $tm ? $gm['home_score'] > $gm['away_score'] : $gm['away_score'] > $gm['home_score'] ) ? 'W' : ( $gm['home_score'] === $gm['away_score'] ? 'T' : 'L' ) ) . ' ' . (int) ( $gm['home'] === $tm ? $gm['home_score'] : $gm['away_score'] ) . '-' . (int) ( $gm['home'] === $tm ? $gm['away_score'] : $gm['home_score'] ) : '' ); }
		elseif ( isset( $tmG[ $tm ][ $wk ] ) ) { $x = $tmG[ $tm ][ $wk ]; $rows[ $wk ] = array( 'kind' => $x['played'] ? 'dnp' : 'ahead', 'tm' => $tm, 'opp' => $x['opp'], 'home' => $x['home'] ); }
		else { $rows[ $wk ] = array( 'kind' => 'bye' ); }
	}
	$gxWeeks = $rows;
	$playedWks = array_keys( $Lby ); sort( $playedWks ); $nPlayed = count( $playedWks );
	/* one series per metric: per-game value, trailing-window line, season figure, the league's quartiles, the direction call */
	$series = array();
	foreach ( $tdefs as $def ) {
		$pts = array();
		foreach ( $playedWks as $wk ) { list( $n, $dn ) = DBD_Grades::trend_parts( $def, $Lby[ $wk ] ); $pts[] = array( 'wk' => $wk, 'n' => $n, 'dn' => $dn, 'v' => $dn ? $n / $dn : null ); }
		$roll = array();
		foreach ( $pts as $i => $q ) { if ( $i < $WIN - 1 ) { $roll[] = null; continue; } $sn = 0; $sd = 0; for ( $j = $i - $WIN + 1; $j <= $i; $j++ ) { $sn += $pts[ $j ]['n']; $sd += $pts[ $j ]['dn']; } $roll[] = $sd ? $sn / $sd : null; }
		$SN = array_sum( array_column( $pts, 'n' ) ); $SD = array_sum( array_column( $pts, 'dn' ) ); $sAvg = $SD ? $SN / $SD : null;
		$lg = DBD_Grades::trend_league( $grp, $def[0] ); $li = null;
		$cur = $roll ? $roll[ count( $roll ) - 1 ] : null;  /* the final window, null when his last three games have no target, touch or dropback */
		foreach ( $roll as $i => $v ) { if ( $v !== null ) { $li = $i; } }  /* the last point the line reaches */
		$iqr = $lg ? (float) $lg['p75'] - (float) $lg['p25'] : ( $sAvg ? abs( $sAvg ) * 0.4 : 1 );
		$dir = ( $cur === null || $sAvg === null ) ? 'flat' : ( $cur - $sAvg > DBD_Grades::TREND_DIR * $iqr ? 'up' : ( $sAvg - $cur > DBD_Grades::TREND_DIR * $iqr ? 'down' : 'flat' ) );
		$series[] = array( 'key' => $def[0], 'lab' => $def[1], 'kind' => $def[2], 'rate' => $def[4] !== null, 'pts' => $pts, 'roll' => $roll, 'avg' => $sAvg, 'lg' => $lg, 'cur' => $cur, 'li' => $li, 'iqr' => $iqr, 'dir' => $dir );
	}
	$tf = function ( $m, $v ) { return DBD_Grades::trend_fmt( $m['kind'], $v ); };
	/* the charts */
	$Wd = 220; $Hh = 108; $Lm = 6; $Rm = 38; $Tp = 12; $Bm = 16; $step = ( $Wd - $Lm - $Rm ) / $lastWk; $bw = max( 3, $step * 0.55 );
	$xw = function ( $wk ) use ( $Lm, $step ) { return round( $Lm + ( $wk - 0.5 ) * $step, 2 ); };
	$fo = 'font-family="IBM Plex Mono,monospace"';
	$charts = '';
	foreach ( $series as $m ) {
		$vals = array_filter( array_column( $m['pts'], 'v' ), function ( $v ) { return $v !== null; } ); $rl = array_filter( $m['roll'], function ( $v ) { return $v !== null; } );
		$hi = max( array_merge( array( 0 ), $vals, $rl, $m['lg'] ? array( (float) $m['lg']['p75'] ) : array() ) ); $lo = min( array_merge( array( 0 ), $vals, $rl, $m['lg'] ? array( (float) $m['lg']['p25'] ) : array() ) );
		$span = ( $hi - $lo ) ?: 1; $yv = function ( $v ) use ( $Tp, $hi, $span, $Hh, $Bm ) { return round( $Tp + ( $hi - $v ) / $span * ( $Hh - $Tp - $Bm ), 2 ); };
		$g = '<svg viewBox="0 0 ' . $Wd . ' ' . $Hh . '" role="img" aria-label="' . esc_attr( $m['lab'] . ' by week' ) . '">';
		if ( $m['lg'] ) { $g .= '<rect x="' . $Lm . '" y="' . $yv( $m['lg']['p75'] ) . '" width="' . ( $Wd - $Lm - $Rm ) . '" height="' . max( 1, $yv( $m['lg']['p25'] ) - $yv( $m['lg']['p75'] ) ) . '" fill="var(--dbd-line)"/><line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $yv( $m['lg']['p50'] ) . '" y2="' . $yv( $m['lg']['p50'] ) . '" stroke="var(--dbd-muted)" stroke-dasharray="3 3"/><text x="' . ( $Wd - $Rm + 3 ) . '" y="' . ( $yv( $m['lg']['p50'] ) + 3 ) . '" font-size="10" fill="var(--dbd-muted)" ' . $fo . '>lg ' . esc_html( $tf( $m, $m['lg']['p50'] ) ) . '</text>'; }
		$g .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $yv( 0 ) . '" y2="' . $yv( 0 ) . '" stroke="var(--dbd-line-2)"/>';
		foreach ( $rows as $wk => $r ) { $cx = $xw( $wk );
			if ( $r['kind'] === 'bye' ) { $g .= '<line x1="' . $cx . '" x2="' . $cx . '" y1="' . ( $yv( 0 ) - 3 ) . '" y2="' . ( $yv( 0 ) + 3 ) . '" stroke="var(--dbd-muted)" stroke-dasharray="1 1"/>'; }
			elseif ( $r['kind'] === 'dnp' ) { $g .= '<text x="' . $cx . '" y="' . ( $yv( 0 ) + 3 ) . '" font-size="10" fill="var(--dbd-bad)" text-anchor="middle" ' . $fo . ' tabindex="0" data-tip="' . esc_attr( '<b>Week ' . $wk . ' ' . ( $r['tm'] !== $a ? esc_html( $r['tm'] ) . ' ' : '' ) . ( $r['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $teams[ $r['opp'] ]['nick'] ?? $r['opp'] ) . '</b><br>he did not play' ) . '">×</text>'; }
			if ( $wk === $curWk ) { $g .= '<line x1="' . $cx . '" x2="' . $cx . '" y1="' . ( $Hh - $Bm + 2 ) . '" y2="' . ( $Hh - $Bm + 6 ) . '" stroke="var(--dbd-red)" stroke-width="2"/>'; }
			if ( ( $wk - 1 ) % 3 === 0 ) { $g .= '<text x="' . $cx . '" y="' . ( $Hh - 3 ) . '" font-size="10" fill="var(--dbd-muted)" text-anchor="middle" ' . $fo . '>' . $wk . '</text>'; }
		}
		foreach ( $m['pts'] as $q ) { if ( $q['v'] === null ) { continue; } $r = $rows[ $q['wk'] ]; $top = min( $yv( 0 ), $yv( $q['v'] ) ); $hgt = max( 1, abs( $yv( $q['v'] ) - $yv( 0 ) ) );
			$tip = '<b>Week ' . $q['wk'] . ' ' . ( ! empty( $r['tm'] ) && $r['tm'] !== $a ? esc_html( $r['tm'] ) . ' ' : '' ) . ( $r['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $teams[ $r['opp'] ]['nick'] ?? $r['opp'] ) . ( $r['res'] ? ' · ' . $r['res'] : '' ) . '</b><br>' . esc_html( $m['lab'] ) . ' <b>' . esc_html( $tf( $m, $q['v'] ) ) . '</b>' . ( $m['lg'] ? ' <span class="m">league median ' . esc_html( $tf( $m, $m['lg']['p50'] ) ) . '</span>' : '' );
			$g .= '<rect x="' . round( $xw( $q['wk'] ) - $bw / 2, 2 ) . '" y="' . $top . '" width="' . round( $bw, 2 ) . '" height="' . $hgt . '" fill="' . ( $q['v'] < 0 ? 'var(--dbd-bad)' : 'var(--dbd-ink-2)' ) . '" fill-opacity=".5" tabindex="0" data-tip="' . esc_attr( $tip ) . '"></rect>'; }
		$line = array(); foreach ( $m['pts'] as $i => $q ) { if ( $m['roll'][ $i ] !== null ) { $line[] = $xw( $q['wk'] ) . ',' . $yv( $m['roll'][ $i ] ); } }
		if ( $line ) { $g .= '<polyline points="' . implode( ' ', $line ) . '" fill="none" stroke="var(--dbd-red)" stroke-width="2" stroke-linejoin="round"/>'; }
		foreach ( $m['pts'] as $i => $q ) { if ( $m['roll'][ $i ] === null ) { continue; } $w3 = array(); for ( $j = $i - $WIN + 1; $j <= $i; $j++ ) { $w3[] = 'Wk ' . $m['pts'][ $j ]['wk']; } $last = $i === $m['li'];
			$g .= '<circle cx="' . $xw( $q['wk'] ) . '" cy="' . $yv( $m['roll'][ $i ] ) . '" r="' . ( $last ? '3.5' : '2.5' ) . '" fill="var(--dbd-red)" stroke="var(--dbd-panel)" stroke-width="1.5" tabindex="0" data-tip="' . esc_attr( '<b>' . ( $last ? 'Last three' : 'Three games' ) . '</b> (' . implode( ', ', $w3 ) . ')<br>' . esc_html( $m['lab'] ) . ' <b>' . esc_html( $tf( $m, $m['roll'][ $i ] ) ) . '</b>' ) . '"></circle>'; }
		$g .= '</svg>';
		$near = ( $m['lg'] && $m['cur'] !== null ) ? $m['cur'] - (float) $m['lg']['p50'] : null;
		$dcls = $near === null ? '' : ( abs( $near ) < DBD_Grades::TREND_NEAR * $m['iqr'] ? '' : ( $near > 0 ? ' up' : ' dn' ) );
		$chip = $near === null ? '' : '<span class="d' . $dcls . '" data-tip="' . esc_attr( 'His last three against the league median' ) . '" tabindex="0">' . ( $near > 0 ? '+' : '' ) . number_format( $near, $m['kind'] === 'sgn' ? 2 : 1 ) . '<i> vs lg</i></span>';
		$head = $m['cur'] !== null ? $tf( $m, $m['cur'] ) : ( $nPlayed < $WIN ? $tf( $m, $m['avg'] ) : '–' );
		$charts .= '<div class="dbd-trc"><div class="th"><b>' . esc_html( $m['lab'] ) . '</b><span class="v">' . esc_html( $head ) . '</span>' . $chip . '</div>' . $g . '</div>';
	}
	/* the read: direction, shape, streak or share above the median, season high or low, efficiency */
	list( $U, $Yd, $E ) = $series; $say = array();
	$word = function ( $m ) { return $m['dir'] === 'up' ? 'climbing' : ( $m['dir'] === 'down' ? 'slipping' : 'steady' ); };
	if ( $nPlayed < $WIN ) { $say[] = 'Too few games for a trend yet: <b>' . $nPlayed . '</b> played.'; }
	else {
		$say[] = esc_html( $U['lab'] ) . ' are <b>' . $word( $U ) . '</b>: ' . $tf( $U, $U['cur'] ) . ( $U['rate'] ? '' : ' a game' ) . ' over his last three against ' . $tf( $U, $U['avg'] ) . ' for the season.';
		$yc = DBD_Grades::trend_fmt( 'n', $Yd['cur'] === null ? null : round( $Yd['cur'] ) ); $ya = DBD_Grades::trend_fmt( 'n', $Yd['avg'] === null ? null : round( $Yd['avg'] ) );
		if ( $U['dir'] === 'up' && $Yd['dir'] === 'up' ) { $say[] = 'The role and the production are both growing: <b>' . $yc . '</b> yards a game lately against ' . $ya . '.'; }
		elseif ( $U['dir'] === 'up' && $Yd['cur'] > $Yd['avg'] ) { $say[] = 'The yards are edging up with it: ' . $yc . ' a game lately against ' . $ya . '.'; }
		elseif ( $U['dir'] === 'up' ) { $say[] = 'The volume is up; the yards haven\'t followed yet (' . $yc . ' against ' . $ya . ').'; }
		elseif ( $Yd['dir'] === 'up' ) { $say[] = 'He is doing more with less: <b>' . $yc . '</b> yards a game over the last three against ' . $ya . '.'; }
		elseif ( $U['dir'] === 'down' && $Yd['dir'] === 'down' ) { $say[] = 'The role is shrinking, and the yards with it (' . $yc . ' against ' . $ya . ').'; }
		elseif ( $Yd['dir'] !== 'flat' ) { $say[] = 'Yards are ' . $word( $Yd ) . ': ' . $yc . ' a game lately against ' . $ya . '.'; }
		$best = null;
		foreach ( array( $U, $Yd ) as $m ) { if ( ! $m['lg'] ) { continue; } $n = 0; $above = null;
			for ( $i = count( $m['pts'] ) - 1; $i >= 0; $i-- ) { $v = $m['pts'][ $i ]['v']; if ( $v === null ) { break; } $ab = $v <=> (float) $m['lg']['p50']; if ( $ab === 0 ) { break; } if ( $above === null ) { $above = $ab; } if ( $ab !== $above ) { break; } $n++; }
			if ( $n >= DBD_Grades::TREND_STREAK && ( ! $best || $n > $best['n'] ) ) { $best = array( 'n' => $n, 'above' => $above, 'm' => $m ); } }
		if ( $best ) { $say[] = '<b>' . $best['n'] . ' straight</b> games ' . ( $best['above'] > 0 ? 'above' : 'below' ) . ' a starter\'s median in ' . esc_html( strtolower( $best['m']['lab'] ) ) . '.'; }
		elseif ( $Yd['lg'] ) { $ab = 0; foreach ( $Yd['pts'] as $q ) { if ( $q['v'] !== null && $q['v'] >= (float) $Yd['lg']['p50'] ) { $ab++; } } $say[] = 'At or above a starter\'s median in yards in <b>' . $ab . ' of ' . count( $Yd['pts'] ) . '</b> games.'; }
		$ys = array_column( $Yd['pts'], 'v' ); $lastY = end( $ys ); $lastWkP = end( $playedWks );
		if ( count( $ys ) >= 4 && $lastY == max( $ys ) ) { $say[] = 'Week ' . $lastWkP . ' was his season high in yards.'; }
		elseif ( count( $ys ) >= 4 && $lastY == min( $ys ) ) { $say[] = 'Week ' . $lastWkP . ' was his season low in yards.'; }
		if ( $E['dir'] !== 'flat' && $E['cur'] !== null ) { $say[] = esc_html( $E['lab'] ) . ' is ' . ( $E['dir'] === 'up' ? 'rising' : 'falling' ) . ' too: ' . $tf( $E, $E['cur'] ) . ' over the last three.'; }
	}
	echo '<section class="dbd-panel dbd-tr"><div class="dbd-ph"><span class="dbd-lab"><b>The shape of his season</b> · week by week against the league</span><span class="dbd-badge">' . $nPlayed . ' game' . ( $nPlayed === 1 ? '' : 's' ) . ' · through week ' . (int) $through . '</span></div>';
	echo '<div class="dbd-tr4">' . $charts . '</div><p class="dbd-tr-say">' . implode( ' ', $say ) . '</p>';
	echo '<p class="dbd-note" style="padding-top:0">Bars are his week. The shaded band is the league\'s middle half at his position, every qualifying player-game this season, and the dashed line is its median. The solid line is his trailing three games. An × on the baseline is a game his team played without him; a dotted tick is the bye.</p></section>';
}

$trendHtml = ob_get_clean(); $shortLately = ( isset( $say ) && is_array( $say ) ) ? (string) ( $say[0] ?? '' ) : '';
ob_start();
/* ---- game log (PHP): Default, Advanced and Traditional tables; player.js switches which one shows */
$res = function ( $wk, $tm = null ) use ( $a ) { $tm = $tm ?: $a; $g = DBD_Data::team_game( $tm, $wk ); if ( ! $g ) { return ''; } $home = $g['home'] === $tm; $opp = $home ? $g['away'] : $g['home']; $pf = $home ? $g['home_score'] : $g['away_score']; $pa = $home ? $g['away_score'] : $g['home_score'];
	if ( $pf === null ) { return ( $home ? 'vs ' : 'at ' ) . $opp; }
	return ( $tm !== $a ? esc_html( $tm ) . ' ' : '' ) . ( $home ? 'vs ' : 'at ' ) . esc_html( $opp ) . ' <b class="' . ( $pf > $pa ? '' : 'l' ) . '">' . ( $pf > $pa ? 'W' : ( $pf < $pa ? 'L' : 'T' ) ) . '</b> ' . (int) $pf . '-' . (int) $pa; };
$snap = function ( $wk ) use ( $p ) { return isset( $p['snaps'][ (string) (int) $wk ] ) ? (int) $p['snaps'][ (string) (int) $wk ] . '%' : '-'; };
$pf = function ( $wk, $k ) use ( $p ) { return (int) ( $p['pfr'][ (string) (int) $wk ][ $k ] ?? 0 ); };
$drops = 0; $bt = 0; $rats = array();
foreach ( $p['pfr'] ?? array() as $x ) { $drops += (int) ( $x['drop'] ?? 0 ); $bt += (int) ( $x['bt'] ?? 0 ) + (int) ( $x['rbt'] ?? 0 ); if ( isset( $x['rat'] ) && $x['rat'] !== null ) { $rats[] = (float) $x['rat']; } }
$cell = function ( $v, $cls = '' ) { return '<td' . ( $cls ? ' class="' . $cls . '"' : '' ) . '>' . $v . '</td>'; };
$e = function ( $v, $d = 1 ) { return '<td class="' . ( $v >= 0 ? 'pos' : 'neg' ) . '">' . ( $v > 0 ? '+' : '' ) . number_format( (float) $v, $d ) . '</td>'; };
$f = function ( $n, $d ) use ( $f1 ) { return $d ? $f1( $n / $d ) : '-'; };
$pc2 = function ( $n, $d ) { return $d ? round( $n / $d * 100 ) . '%' : '-'; };
$pair = $sum( 'passing_air_yards' );
$views = array();
if ( $grp === 'rec' ) {
	$views['Default'] = array( 'Snap^2|Tgt|Rec|Yds|TD^2|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $cell( (int) $w['receiving_tds'] ?: '' ) . $e( (float) $w['receiving_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td><td>' . (int) $rtd . '</td>' . $e( $repa ) );
	$views['Advanced'] = array( 'Tgt share|Air share^2|aDOT|YAC^2|EPA/tgt|1D^2|Drops^3', function ( $w, $wk ) use ( $cell, $e, $pct, $f, $pf ) { return $cell( $pct( (float) $w['target_share'] ) ) . $cell( $pct( (float) $w['air_yards_share'] ) ) . $cell( $f( $w['receiving_air_yards'], $w['targets'] ) ) . $cell( (int) $w['receiving_yards_after_catch'] ) . ( $w['targets'] ? $e( $w['receiving_epa'] / $w['targets'], 2 ) : $cell( '-' ) ) . $cell( (int) $w['receiving_first_downs'] ) . $cell( $pf( $wk, 'drop' ) ?: '' ); },
		'<td>' . $pct( $tshare ) . '</td><td>' . $pct( $ashare ) . '</td><td>' . $f1( $div( $air, $tg ) ) . '</td><td>' . (int) $yac . '</td>' . ( $tg ? $e( $repa / $tg, 2 ) : '<td>-</td>' ) . '<td>' . (int) $rfd . '</td><td>' . ( $drops ?: '' ) . '</td>' );
	$views['Traditional'] = array( 'Rec|Yds|Y/R^2|TD|20+^3', function ( $w, $wk ) use ( $cell, $f ) { return $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ) . $cell( $f( $w['receiving_yards'], $w['receptions'] ) ) . $cell( (int) $w['receiving_tds'] ?: '' ) . $cell( (int) ( $w['receiving_20'] ?? 0 ) ?: '' ); },
		'<td>' . (int) $rc . '</td><td>' . (int) $ry . '</td><td>' . $f1( $div( $ry, $rc ) ) . '</td><td>' . (int) $rtd . '</td><td>' . (int) $sum( 'receiving_20' ) . '</td>' );
} elseif ( $grp === 'rb' ) {
	$views['Default'] = array( 'Snap^2|Car|Yds|TD^2|Tgt|Rec^3|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['carries'] ) . $cell( (int) $w['rushing_yards'] ) . $cell( ( (int) $w['rushing_tds'] + (int) $w['receiving_tds'] ) ?: '' ) . $cell( (int) $w['targets'] ) . $cell( (int) $w['receptions'] ) . $e( (float) $w['rushing_epa'] + (float) $w['receiving_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $ca . '</td><td>' . (int) $ruy . '</td><td>' . (int) ( $rutd + $rtd ) . '</td><td>' . (int) $tg . '</td><td>' . (int) $rc . '</td>' . $e( $ruepa + $repa ) );
	$views['Advanced'] = array( 'YPC|EPA/car|EPA/tgt^2|10+^2|Brk tkl^3|Snap^3', function ( $w, $wk ) use ( $cell, $e, $f, $pf, $snap ) { return $cell( $f( $w['rushing_yards'], $w['carries'] ) ) . ( $w['carries'] ? $e( $w['rushing_epa'] / $w['carries'], 2 ) : $cell( '-' ) ) . ( $w['targets'] ? $e( $w['receiving_epa'] / $w['targets'], 2 ) : $cell( '-' ) ) . $cell( (int) ( $w['rushing_10'] ?? 0 ) ?: '' ) . $cell( ( $pf( $wk, 'bt' ) + $pf( $wk, 'rbt' ) ) ?: '' ) . $cell( $snap( $wk ) ); },
		'<td>' . $f1( $div( $ruy, $ca ) ) . '</td>' . ( $ca ? $e( $ruepa / $ca, 2 ) : '<td>-</td>' ) . ( $tg ? $e( $repa / $tg, 2 ) : '<td>-</td>' ) . '<td>' . (int) $sum( 'rushing_10' ) . '</td><td>' . ( $bt ?: '' ) . '</td><td>' . round( $snapAvg ) . '%</td>' );
	$views['Traditional'] = array( 'Car|Yds|YPC^2|TD|Rec^3|Rec yds^3', function ( $w, $wk ) use ( $cell, $f ) { return $cell( (int) $w['carries'] ) . $cell( (int) $w['rushing_yards'] ) . $cell( $f( $w['rushing_yards'], $w['carries'] ) ) . $cell( (int) $w['rushing_tds'] ?: '' ) . $cell( (int) $w['receptions'] ) . $cell( (int) $w['receiving_yards'] ); },
		'<td>' . (int) $ca . '</td><td>' . (int) $ruy . '</td><td>' . $f1( $div( $ruy, $ca ) ) . '</td><td>' . (int) $rutd . '</td><td>' . (int) $rc . '</td><td>' . (int) $ry . '</td>' );
} else {
	$views['Default'] = array( 'Snap^2|Cmp/Att|Yds|TD^2|INT^2|EPA', function ( $w, $wk ) use ( $cell, $e, $snap ) { return $cell( $snap( $wk ) ) . $cell( (int) $w['completions'] . '/' . (int) $w['attempts'] ) . $cell( (int) $w['passing_yards'] ) . $cell( (int) $w['passing_tds'] ?: '' ) . $cell( (int) $w['passing_interceptions'] ?: '' ) . $e( (float) $w['passing_epa'] ); },
		'<td>' . round( $snapAvg ) . '%</td><td>' . (int) $cmp . '/' . (int) $att . '</td><td>' . (int) $py . '</td><td>' . (int) $ptd . '</td><td>' . (int) $pint . '</td>' . $e( $pepa ) );
	$views['Advanced'] = array( 'EPA/DB|CPOE|aDOT^2|Sk^3|Sk %^2|Rush EPA^2', function ( $w, $wk ) use ( $cell, $e, $f, $pc2, $sgn ) { $db = $w['attempts'] + $w['sacks_suffered']; return ( $db ? $e( $w['passing_epa'] / $db, 2 ) : $cell( '-' ) ) . $cell( $w['attempts'] ? $sgn( (float) $w['passing_cpoe'], 1 ) : '-' ) . $cell( $f( $w['passing_air_yards'] ?? 0, $w['attempts'] ) ) . $cell( (int) $w['sacks_suffered'] ?: '' ) . $cell( $pc2( $w['sacks_suffered'], $db ) ) . $e( (float) $w['rushing_epa'] ); },
		( ( $att + $sk ) ? $e( $pepa / ( $att + $sk ), 2 ) : '<td>-</td>' ) . '<td>' . $sgn( $cpoe, 1 ) . '</td><td>' . $f1( $div( $pair, $att ) ) . '</td><td>' . (int) $sk . '</td><td>' . $pct( $div( $sk, $att + $sk ) ) . '</td>' . $e( $ruepa ) );
	$views['Traditional'] = array( 'Cmp^3|Att^3|Pct|Yds|Y/A^2|TD|INT|Rate^2', function ( $w, $wk ) use ( $cell, $f, $pc2 ) { $r = DBD_Grades::passer_rating( (float) $w['completions'], (float) $w['attempts'], (float) $w['passing_yards'], (float) $w['passing_tds'], (float) $w['passing_interceptions'] ); return $cell( (int) $w['completions'] ) . $cell( (int) $w['attempts'] ) . $cell( $pc2( $w['completions'], $w['attempts'] ) ) . $cell( (int) $w['passing_yards'] ) . $cell( $f( $w['passing_yards'], $w['attempts'] ) ) . $cell( (int) $w['passing_tds'] ?: '' ) . $cell( (int) $w['passing_interceptions'] ?: '' ) . $cell( $r === null ? '-' : number_format( $r, 1 ) ); },
		'<td>' . (int) $cmp . '</td><td>' . (int) $att . '</td><td>' . $pct( $div( $cmp, $att ) ) . '</td><td>' . (int) $py . '</td><td>' . $f1( $div( $py, $att ) ) . '</td><td>' . (int) $ptd . '</td><td>' . (int) $pint . '</td><td>' . ( ( $r = DBD_Grades::passer_rating( $cmp, $att, $py, $ptd, $pint ) ) === null ? '-' : number_format( $r, 1 ) ) . '</td>' );
}
echo '<section class="dbd-panel"><div class="dbd-ph dbd-glogh"><span class="dbd-lab"><b>Game log</b> · ' . ( $gxWeeks ? 'all ' . count( $gxWeeks ) . ' weeks' : 'week by week' ) . '</span><div class="dbd-seg t-tabs dbd-gview" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>';
$i = 0; foreach ( array_keys( $views ) as $name ) { echo '<button class="t-tab" type="button" role="tab" data-v="' . esc_attr( $name ) . '" aria-selected="' . ( $i++ ? 'false' : 'true' ) . '">' . esc_html( $name ) . '</button>'; }
echo '</div></div>';
foreach ( $views as $name => $vd ) {
	list( $cols, $row, $foot ) = $vd;
	$tiers = array( null, null ); if ( $hasDef ) { $tiers[] = 'dbd-t3'; }
	$heads = '';
	foreach ( explode( '|', $cols ) as $c ) { $t = null; if ( preg_match( '/\^(\d)$/', $c, $m ) ) { $t = 'dbd-t' . $m[1]; $c = substr( $c, 0, -2 ); } $tiers[] = $t; $heads .= '<th>' . esc_html( $c ) . '</th>'; }
	$body = ''; $gameRow = function ( $w ) use ( $cell, $res, $hasDef, $dchipShort, $dchip, $oppOf, $row ) { $wk = (int) $w['week']; $opp = $w['opponent_team'] ?? $oppOf( $wk, $w['team'] ?? null ); return '<tr>' . $cell( $wk ) . $cell( $res( $wk, $w['team'] ?? null ) . ( $hasDef ? '<span class="dbd-dfold">' . $dchipShort( $opp ) . '</span>' : '' ), 'res' ) . ( $hasDef ? $dchip( $opp ) : '' ) . $row( $w, $wk ) . '</tr>'; };
	$gxMore = 0;
	if ( $gxWeeks ) {
		/* SCOPE §29: the bye and the weeks he missed sit in place, then this week's game; the rest of the schedule waits behind a button */
		/* every cell is kept, so the stat columns the phone layout hides stay hidden on these rows too; the note sits under the opponent */
		$gxPad = str_repeat( '<td></td>', ( $hasDef ? 1 : 0 ) + count( explode( '|', $cols ) ) ); $gxNext = false; $gxLater = '';
		$gxTr = function ( $cls, $wk, $res_, $why, $later ) use ( $gxPad ) { return '<tr class="dbd-gx ' . $cls . ( $later ? ' later' : '' ) . '"' . ( $later ? ' hidden' : '' ) . '><td>' . (int) $wk . '</td><td class="res">' . $res_ . ( $why !== '' ? '<span class="why">' . $why . '</span>' : '' ) . '</td>' . $gxPad . '</tr>'; };
		foreach ( $gxWeeks as $wk => $gr_ ) {
			if ( $gr_['kind'] === 'game' ) { $body .= $gameRow( $gr_['w'] ); continue; }
			if ( $gr_['kind'] === 'bye' ) { $tr = $gxTr( 'bye', $wk, 'Bye week', '', $gxNext ); if ( $gxNext ) { $gxLater .= $tr; } else { $body .= $tr; } continue; }  /* a bye still ahead waits in order with the games around it */
			if ( $gr_['kind'] === 'dnp' ) { $iw = $p['injw'][ (string) $wk ] ?? null; $body .= $gxTr( 'dnp', $wk, $res( $wk, $gr_['tm'] ), 'Did not play' . ( $iw && ! empty( $iw[0] ) ? ' · ' . esc_html( $iw[0] ) . ( ! empty( $iw[1] ) ? ' (' . esc_html( strtolower( $iw[1] ) ) . ')' : '' ) : '' ), false ); continue; }
			$gm = DBD_Data::team_game( $gr_['tm'], $wk ); $when = '';
			if ( $gm && ! empty( $gm['date'] ) ) { try { $when = wp_date( 'D M j', ( new DateTime( $gm['date'] . ' ' . ( $gm['time'] ?: '13:00' ), new DateTimeZone( 'America/New_York' ) ) )->getTimestamp(), new DateTimeZone( 'America/New_York' ) ); } catch ( Exception $ex ) { $when = ''; } }
			$tr = $gxTr( 'up' . ( $gxNext ? '' : ' next' ), $wk, ( $gr_['tm'] !== $a ? esc_html( $gr_['tm'] ) . ' ' : '' ) . ( $gr_['home'] ? 'vs ' : 'at ' ) . esc_html( $gr_['opp'] ), esc_html( $when ) . ( $gxNext ? '' : ( (int) $wk === $curWk ? ' · this week' : ' · next up' ) ), $gxNext );
			if ( $gxNext ) { $gxLater .= $tr; $gxMore++; } else { $body .= $tr; $gxNext = true; }
		}
		$body .= $gxLater;
	} else { foreach ( $L as $w ) { $body .= $gameRow( $w ); } }
	echo '<div class="dbd-tw" data-view="' . esc_attr( $name ) . '"' . ( $name === 'Default' ? '' : ' hidden' ) . '><table class="dbd-glog dbd-stat dbd-sortable">';
	echo DBD_Router::tierize( '<thead><tr><th>Wk</th><th>Opponent</th>' . ( $hasDef ? '<th>Defense</th>' : '' ) . $heads . '</tr></thead><tbody>' . $body . '</tbody><tfoot><tr><td></td><td>' . $ng . ' games</td>' . ( $hasDef ? '<td></td>' : '' ) . $foot . '</tr></tfoot>', $tiers ) . '</table>';
	echo ( $gxMore ? '<button type="button" class="dbd-gx-more" aria-expanded="false" data-n="' . $gxMore . '">Rest of the schedule · ' . $gxMore . ' game' . ( $gxMore === 1 ? '' : 's' ) . '</button>' : '' ) . '</div>';
}
echo ( $traded ? '<p class="dbd-note">Weeks marked with another team come from that team\'s stat line. The field map and situations below cover ' . esc_html( $t['nick'] ) . ' snaps only.</p>' : '' ) . '</section>';

$logHtml = ob_get_clean();
ob_start();
/* ---- who he has faced (SCOPE §19): slate rank, the eighteen-week ribbon, his line by defense tier, yards against the rank */
/* the schedule was read above, before the trend panel */
$myRows = array();
foreach ( $L as $w ) { $wk = (int) $w['week']; $opp = $w['opponent_team'] ?? $oppOf( $wk, $w['team'] ?? null ); $rk = $drank( $opp ); if ( $rk ) { $myRows[] = array( 'w' => $w, 'wk' => $wk, 'opp' => $opp, 'rk' => $rk ); } }
if ( $hasDef && $myRows && $games ) {
	$slates = array();
	foreach ( array_keys( $teams ) as $tm ) { $s = 0; $n = 0; foreach ( $games[ $tm ] ?? array() as $x ) { $rk = $drank( $x['opp'] ); if ( $x['played'] && $rk ) { $s += $rk; $n++; } } if ( $n ) { $slates[] = $s / $n; } }
	sort( $slates );
	$myAvg = array_sum( array_column( $myRows, 'rk' ) ) / count( $myRows );
	$myRank = count( array_filter( $slates, function ( $v ) use ( $myAvg ) { return $v < $myAvg; } ) ) + 1; $med = $slates ? $slates[ (int) floor( count( $slates ) / 2 ) ] : null;
	$mineG = $games[ $a ] ?? array(); $ahead = array_values( array_filter( $mineG, function ( $x ) { return ! $x['played']; } ) );
	$aheadTough = count( array_filter( $ahead, function ( $x ) use ( $drank ) { return DBD_Grades::defense_tier( $drank( $x['opp'] ) ) === 'tough'; } ) ); $aheadSoft = count( array_filter( $ahead, function ( $x ) use ( $drank ) { return DBD_Grades::defense_tier( $drank( $x['opp'] ) ) === 'soft'; } ) ); $next = $ahead[0] ?? null;
	$lab = esc_html( $dk['lab'] );
	$say = 'He has faced the <b>' . esc_html( DBD_Grades::ordinal( $myRank ) ) . '-toughest</b> slate ' . $lab . ': his opponents rank <b>' . $f1( $myAvg ) . '</b> of 32 on average' . ( $med === null ? '' : ' (league middle ' . $f1( $med ) . ')' ) . '.';
	if ( $next && $drank( $next['opp'] ) ) { $say .= ' Ahead: <b>' . $aheadTough . '</b> of his next ' . count( $ahead ) . ( $aheadTough === 1 ? ' is' : ' are' ) . ' top-10 ' . $lab . ( $aheadSoft ? ' and ' . $aheadSoft . ( $aheadSoft === 1 ? ' is' : ' are' ) . ' bottom-10' : '' ) . ', starting with the <b>' . esc_html( $teams[ $next['opp'] ]['nick'] ?? $next['opp'] ) . '</b> (' . esc_html( DBD_Grades::ordinal( $drank( $next['opp'] ) ) ) . ( $next['home'] ? '' : ', away' ) . ') in Week ' . (int) $next['wk'] . '.'; }
	echo '<section class="dbd-panel dbd-ox"><div class="dbd-ph"><span class="dbd-lab"><b>Who he\'s faced</b> · defenses ranked ' . $lab . '</span><span class="dbd-badge">' . count( $L ) . ' played · ' . count( $ahead ) . ' ahead</span></div>';
	echo '<p class="dbd-ox-say">' . $say . '</p>';
	/* ribbon */
	/* played weeks come from his own log (his team that week and its opponent, so a traded player's early weeks are right); weeks still to play come from the current team's schedule */
	$byWk = array(); foreach ( $mineG as $x ) { $byWk[ $x['wk'] ] = $x; }
	foreach ( $L as $w ) { $wk = (int) $w['week']; $tm = $w['team'] ?? $a; $gm = DBD_Data::team_game( $tm, $wk ); $opp = $w['opponent_team'] ?? $oppOf( $wk, $tm ); if ( $opp ) { $byWk[ $wk ] = array( 'wk' => $wk, 'opp' => $opp, 'home' => $gm ? $gm['home'] === $tm : true, 'played' => true, 'tm' => $tm, 'his' => true ); } }
	$rib = '';
	for ( $wk = 1; $wk <= max( 18, $maxWk ); $wk++ ) {
		$x = $byWk[ $wk ] ?? null;
		if ( ! $x ) { $rib .= '<div class="c bye"><small>WK ' . $wk . '</small><b>—</b><i>bye</i></div>'; continue; }
		$dnp = $x['played'] && empty( $x['his'] ); $away = ! empty( $x['tm'] ) && $x['tm'] !== $a ? esc_html( $x['tm'] ) . ' ' : '';
		$rk = $drank( $x['opp'] ); $cls = DBD_Grades::defense_tier( $rk ) . ( $x['played'] ? ' done' : ' ahead' ) . ( $dnp ? ' dnp' : '' ) . ( $wk === $curWk ? ' now' : '' );
		$rib .= '<div class="c ' . $cls . '" tabindex="0" data-tip="' . esc_attr( '<b>Week ' . $wk . ' ' . $away . ( $x['home'] ? 'vs' : 'at' ) . ' ' . esc_html( $teams[ $x['opp'] ]['nick'] ?? $x['opp'] ) . '</b>' . ( $dnp ? ' · he did not play' : '' ) . '<br>' . ( $rk ? $dtip( $x['opp'] ) : 'not ranked yet' ) ) . '"><small>' . $away . ( $x['home'] ? 'VS' : 'AT' ) . ' ' . esc_html( $x['opp'] ) . '</small><b>' . ( $rk ?: '–' ) . '</b><i>' . ( $rk ? $lab : '' ) . '</i></div>';
	}
	echo '<h4>The slate · played solid, ahead dashed</h4><div class="dbd-ox-rib">' . $rib . '</div>';
	echo '<div class="dbd-legend"><span><i class="sq" style="background:#161e36"></i>Top 10, tough</span><span><i class="sq" style="background:var(--dbd-line-2)"></i>11 to 22</span><span><i class="sq" style="background:rgba(30,127,74,.35)"></i>23 to 32, soft</span><span>Number is the defense\'s rank ' . $lab . ', season to date. Dashed cells are still to play; the red outline is this week.</span></div>';
	/* his line by defense tier, from his weekly line */
	if ( $grp === 'rec' ) { $uLab = 'Tgt/g'; $eLab = 'EPA/tgt'; $uUse = function ( $w ) { return (float) $w['targets']; }; $uYds = function ( $w ) { return (float) $w['receiving_yards']; }; $uEpa = function ( $w ) { return (float) $w['receiving_epa']; }; $uDen = $uUse; }
	elseif ( $grp === 'rb' ) { $uLab = 'Touches/g'; $eLab = 'EPA/touch'; $uUse = function ( $w ) { return (float) $w['carries'] + (float) $w['targets']; }; $uYds = function ( $w ) { return (float) $w['rushing_yards'] + (float) $w['receiving_yards']; }; $uEpa = function ( $w ) { return (float) $w['rushing_epa'] + (float) $w['receiving_epa']; }; $uDen = $uUse; }
	else { $uLab = 'Att/g'; $eLab = 'EPA/db'; $uUse = function ( $w ) { return (float) $w['attempts']; }; $uYds = function ( $w ) { return (float) $w['passing_yards']; }; $uEpa = function ( $w ) { return (float) $w['passing_epa']; }; $uDen = function ( $w ) { return (float) $w['attempts'] + (float) ( $w['sacks_suffered'] ?? 0 ); }; }
	$tot = function ( $rows, $fn ) { $s = 0; foreach ( $rows as $r ) { $s += $fn( $r['w'] ); } return $s; };
	$tiers = array( array( 'tough', 'Top-10 defenses', 'ranked 1 to 10' ), array( 'mid', 'Middle', '11 to 22' ), array( 'soft', 'Bottom-10', '23 to 32' ) );
	echo '<div class="dbd-ox-2"><div><h4>His line by defense faced</h4><table class="dbd-ox-split dbd-stat"><thead><tr><th>Defenses</th><th>G</th><th>' . esc_html( $uLab ) . '</th><th>Yds/g</th><th>' . esc_html( $eLab ) . '</th></tr></thead><tbody>';
	foreach ( $tiers as $tr ) {
		$rows = array_filter( $myRows, function ( $r ) use ( $tr ) { return DBD_Grades::defense_tier( $r['rk'] ) === $tr[0]; } ); $g = count( $rows ); $den = $tot( $rows, $uDen ); $ep = $tot( $rows, $uEpa );
		echo '<tr><td><span class="dbd-dchip ' . $tr[0] . '">' . esc_html( $tr[2] ) . '</span>' . esc_html( $tr[1] ) . '</td><td>' . ( $g ?: '–' ) . '</td><td>' . ( $g ? $f1( $tot( $rows, $uUse ) / $g ) : '–' ) . '</td><td>' . ( $g ? round( $tot( $rows, $uYds ) / $g ) : '–' ) . '</td>' . ( $den ? $e( $ep / $den, 2 ) : '<td>–</td>' ) . '</tr>';
	}
	$gAll = count( $myRows ); $denAll = $tot( $myRows, $uDen );
	echo '</tbody><tfoot><tr><td>All games</td><td>' . $gAll . '</td><td>' . $f1( $tot( $myRows, $uUse ) / $gAll ) . '</td><td>' . round( $tot( $myRows, $uYds ) / $gAll ) . '</td>' . ( $denAll ? $e( $tot( $myRows, $uEpa ) / $denAll, 2 ) : '<td>–</td>' ) . '</tr></tfoot></table></div>';
	/* scatter: opponent rank against his yards that week */
	$pts = array_map( function ( $r ) use ( $uYds ) { return array( 'r' => $r['rk'], 'y' => $uYds( $r['w'] ), 'o' => $r['opp'], 'wk' => $r['wk'] ); }, $myRows );
	$Wd = 360; $H = 210; $Lm = 34; $Rm = 10; $Tp = 16; $Bm = 30; $maxY = max( 10, max( array_column( $pts, 'y' ) ) ) * 1.15; $n = count( $pts );
	$X = function ( $r ) use ( $Lm, $Wd, $Rm ) { return $Lm + ( $r - 1 ) / 31 * ( $Wd - $Lm - $Rm ); }; $Y = function ( $v ) use ( $Tp, $H, $Bm, $maxY ) { return $Tp + ( 1 - $v / $maxY ) * ( $H - $Tp - $Bm ); };
	$avgY = array_sum( array_column( $pts, 'y' ) ) / $n; $colT = array( 'tough' => '#161e36', 'mid' => '#9a96a6', 'soft' => '#1e7f4a' );
	$sc = '<svg viewBox="0 0 ' . $Wd . ' ' . $H . '" width="100%" style="display:block" role="img" aria-label="Yards by defense rank">';
	foreach ( array( 0, .5, 1 ) as $fr ) { $v = round( $maxY * $fr / 10 ) * 10; $sc .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $Y( $v ) . '" y2="' . $Y( $v ) . '" stroke="var(--dbd-line)"/><text x="' . ( $Lm - 6 ) . '" y="' . ( $Y( $v ) + 4 ) . '" font-size="9" fill="var(--dbd-muted)" text-anchor="end" font-family="IBM Plex Mono,monospace">' . $v . '</text>'; }
	$sc .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $Y( $avgY ) . '" y2="' . $Y( $avgY ) . '" stroke="var(--dbd-red)" stroke-dasharray="3 4"/><text x="' . ( $Wd - $Rm ) . '" y="' . ( $Y( $avgY ) - 4 ) . '" font-size="9" fill="var(--dbd-red)" text-anchor="end" font-family="IBM Plex Mono,monospace">avg ' . round( $avgY ) . '</text>';
	$sc .= '<text x="' . $Lm . '" y="' . ( $H - 8 ) . '" font-size="9" fill="var(--dbd-muted)" font-family="IBM Plex Mono,monospace">1 · toughest</text><text x="' . ( $Wd - $Rm ) . '" y="' . ( $H - 8 ) . '" font-size="9" fill="var(--dbd-muted)" text-anchor="end" font-family="IBM Plex Mono,monospace">32 · softest</text><text x="' . ( ( $Lm + $Wd - $Rm ) / 2 ) . '" y="' . ( $H - 8 ) . '" font-size="9" fill="var(--dbd-muted)" text-anchor="middle" font-family="IBM Plex Mono,monospace">defense rank ' . $lab . '</text>';
	foreach ( $pts as $q ) { $sc .= '<circle cx="' . $X( $q['r'] ) . '" cy="' . $Y( $q['y'] ) . '" r="6" fill="' . $colT[ DBD_Grades::defense_tier( $q['r'] ) ] . '" stroke="#fff" stroke-width="1.5" tabindex="0" data-tip="' . esc_attr( '<b>Week ' . $q['wk'] . ' vs ' . esc_html( $q['o'] ) . '</b> (' . DBD_Grades::ordinal( $q['r'] ) . ' ' . esc_html( $dk['lab'] ) . ')<br>' . round( $q['y'] ) . ' yards' ) . '"></circle><text x="' . $X( $q['r'] ) . '" y="' . ( $Y( $q['y'] ) - 9 ) . '" font-size="9" fill="var(--dbd-ink-2)" text-anchor="middle" font-family="IBM Plex Mono,monospace">' . esc_html( $q['o'] ) . '</text>'; }
	$sc .= '</svg>';
	$mx = array_sum( array_column( $pts, 'r' ) ) / $n; $cov = 0; $vx = 0; $vy = 0;
	foreach ( $pts as $q ) { $cov += ( $q['r'] - $mx ) * ( $q['y'] - $avgY ); $vx += ( $q['r'] - $mx ) ** 2; $vy += ( $q['y'] - $avgY ) ** 2; }
	$rr = ( $vx && $vy ) ? $cov / sqrt( $vx * $vy ) : 0; $rs = number_format( $rr, 2 );
	$read = $n < 4 ? 'Too few games to read a pattern yet.' : ( $rr > DBD_Grades::DEF_CORR ? 'His yards climb as the defense gets softer (r = ' . $rs . '): the schedule has been doing some of the work.' : ( $rr < -DBD_Grades::DEF_CORR ? 'He has done more against the better defenses (r = ' . $rs . ').' : 'His production barely moves with the opponent (r = ' . $rs . '); it has traveled.' ) );
	echo '<div><h4>Yards against the rank of the defense</h4><div class="dbd-ox-sc">' . $sc . '</div><p class="dbd-note" style="padding:6px var(--dbd-edge,14px) 0">' . esc_html( $read ) . '</p></div></div></section>';
}


$facedHtml = ob_get_clean();
echo $tabbed( array( array( 'This week', $weekHtml ), array( 'Game log', $logHtml ), array( 'The trend', $trendHtml ), array( 'Defenses faced', $facedHtml ) ) ) . '</section>';

/* ---- career (SCOPE §24): season by season from players/career/{TEAM}.json, regular season only */
if ( $CR ) {
	$cgrp = $grp; $ccols = DBD_Grades::career_cols( $cgrp ); $ydsLab = DBD_Grades::career_yds_label( $cgrp ); $PG = DBD_Grades::PACE_G;
	$CRs = array_map( function ( $r ) use ( $cgrp ) { return DBD_Grades::career_derive( $cgrp, $r ); }, $CR );
	$isCur = function ( $r ) use ( $season ) { return (int) $r['season'] === (int) $season; };
	$cur = $CRs[ count( $CRs ) - 1 ]; $past = array_values( array_filter( $CRs, function ( $r ) use ( $isCur ) { return ! $isCur( $r ); } ) );
	$cf = function ( $kind, $v ) { if ( $v === null || $v === '' ) { return '–'; } switch ( $kind ) { case 's': return (string) $v; case 'n': return number_format( (float) $v ); case 'f1': return number_format( (float) $v, 1 ); case 'pct': return round( $v * 100 ) . '%'; default: return ( $v > 0 ? '+' : '' ) . number_format( (float) $v, 2 ); } };
	/* totals row */
	$tot = array( 'season' => 'Career', 'g' => 0 ); foreach ( array( 'completions', 'attempts', 'passing_yards', 'passing_tds', 'passing_interceptions', 'sacks_suffered', 'passing_epa', 'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa', 'receptions', 'targets', 'receiving_yards', 'receiving_tds', 'receiving_epa' ) as $k ) { $tot[ $k ] = 0; foreach ( $CR as $r ) { $tot[ $k ] += (float) ( $r[ $k ] ?? 0 ); } }
	foreach ( $CR as $r ) { $tot['g'] += (int) $r['g']; } $cteams = array(); foreach ( $CR as $r ) { foreach ( (array) ( $r['teams'] ?? array( $r['team'] ) ) as $tm ) { $cteams[ $tm ] = true; } } $cteams = array_keys( $cteams );  /* every team he suited up for, mid-season moves included */ $tot['team'] = count( $cteams ) . ' tm'; $tot = DBD_Grades::career_derive( $cgrp, $tot ); $tot['cmpatt'] = number_format( (int) $tot['completions'] ) . '/' . number_format( (int) $tot['attempts'] );
	/* career highs per counting column */
	$chi = array(); foreach ( $ccols as $i => $c ) { if ( $c[3] ) { $chi[ $i ] = max( array_map( function ( $r ) use ( $c ) { return (float) ( $r[ $c[1] ] ?? 0 ); }, $CRs ) ); } }
	$ccell = function ( $c, $r, $i, $isTot ) use ( $cf, $chi, $CRs ) { $v = $r[ $c[1] ] ?? null; $tier = preg_match( '/\^(\d)$/', $c[0], $m ) ? ' dbd-t' . $m[1] : ''; $hi = ! $isTot && $c[3] && count( $CRs ) > 1 && $v !== null && (float) $v == $chi[ $i ]; return '<td class="' . trim( $tier . ( $hi ? ' hi' : '' ) ) . '" data-v="' . esc_attr( is_numeric( $v ) ? $v : ( $c[1] === 'team' ? (string) $v : ( $c[1] === 'cmpatt' ? (string) ( $r['attempts'] ?? '' ) : '' ) ) ) . '">' . esc_html( $cf( $c[2], $v ) ) . '</td>'; };
	$thead = ''; foreach ( $ccols as $c ) { $tier = preg_match( '/\^(\d)$/', $c[0], $m ) ? ' class="dbd-t' . $m[1] . '"' : ''; $thead .= '<th' . $tier . '>' . esc_html( preg_replace( '/\^\d$/', '', $c[0] ) ) . '</th>'; }
	$tbody = ''; foreach ( $CRs as $r ) { $tbody .= '<tr' . ( $isCur( $r ) ? ' class="cur" title="This season, through week ' . (int) $through . '"' : '' ) . '>'; foreach ( $ccols as $i => $c ) { $tbody .= $ccell( $c, $r, $i, false ); } $tbody .= '</tr>'; }
	$tfoot = '<tr class="tot">'; foreach ( $ccols as $i => $c ) { $tfoot .= $ccell( $c, $tot, $i, true ); } $tfoot .= '</tr>';  /* tfoot: the shared sorter leaves it in place */
	/* career line */
	$cline = array( 'rec' => array( array( 'Targets', 'targets' ), array( 'Receptions', 'receptions' ), array( 'Yards', 'receiving_yards' ), array( 'TD', 'receiving_tds' ) ), 'rb' => array( array( 'Carries', 'carries' ), array( 'Rush yards', 'rushing_yards' ), array( 'TD', 'td' ), array( 'Receptions', 'receptions' ) ), 'qb' => array( array( 'Attempts', 'attempts' ), array( 'Yards', 'passing_yards' ), array( 'TD', 'passing_tds' ), array( 'INT', 'passing_interceptions' ) ) )[ $cgrp ];
	$lineh = '<div><b>' . number_format( (int) $tot['g'] ) . '</b><small>Games</small></div>'; foreach ( $cline as $x ) { $lineh .= '<div><b>' . number_format( (float) $tot[ $x[1] ] ) . '</b><small>' . esc_html( $x[0] ) . '</small></div>'; }
	$ns = count( $CRs ); $lineh .= '<span class="span">' . (int) $CRs[0]['season'] . ' to ' . (int) $cur['season'] . ' · ' . $ns . ' season' . ( $ns > 1 ? 's' : '' ) . ' · ' . count( $cteams ) . ' team' . ( count( $cteams ) > 1 ? 's' : '' ) . '</span>';
	/* the read: best season and this season's pace */
	$best = null; foreach ( $past as $r ) { if ( ! $best || $r['yds'] > $best['yds'] ) { $best = $r; } }
	$pace = ( $isCur( $cur ) && $cur['g'] ) ? $cur['yds'] / $cur['g'] * $PG : null;
	if ( ! $past ) { $csay = 'First season: <b>' . (int) $cur['g'] . '</b> game' . ( (int) $cur['g'] === 1 ? '' : 's' ) . ' in, ' . number_format( (float) $cur['yds'] ) . ' ' . esc_html( $ydsLab ) . ( $pace ? ', a ' . $PG . '-game pace of <b>' . number_format( $pace ) . '</b>' : '' ) . '.'; }
	else { $csay = 'His best year was <b>' . (int) $best['season'] . '</b>, ' . number_format( (float) $best['yds'] ) . ' ' . esc_html( $ydsLab ) . ' in ' . (int) $best['g'] . ' games' . ( $best['team'] !== $cur['team'] ? ' with ' . esc_html( $best['team'] ) : '' ) . '.';
		if ( $pace !== null ) { $csay .= ' This season\'s ' . number_format( $cur['yds'] / $cur['g'], 1 ) . ' a game puts him on pace for <b>' . number_format( $pace ) . '</b> over ' . $PG . ', ' . ( $pace >= $best['yds'] ? 'a career high' : ( $pace >= $best['yds'] * 0.85 ? 'close to it' : 'short of it' ) ) . '.'; } }
	/* chart: yards by season */
	$Wd = 640; $Hh = 140; $Lm = 40; $Rm = 12; $Tp = 18; $Bm = 22; $n = count( $CRs ); $step = ( $Wd - $Lm - $Rm ) / $n; $bw = min( 44, $step * 0.6 );
	$mx = max( array_merge( array_map( function ( $r ) { return (float) $r['yds']; }, $CRs ), array( $pace ?: 0, 10 ) ) ) * 1.08;
	$Yc = function ( $v ) use ( $Tp, $mx, $Hh, $Bm ) { return round( $Tp + ( 1 - $v / $mx ) * ( $Hh - $Tp - $Bm ), 1 ); }; $Xc = function ( $i ) use ( $Lm, $step ) { return round( $Lm + $step * ( $i + 0.5 ), 1 ); };
	$fo = 'font-family="IBM Plex Mono,monospace"'; $teamNick = DBD_Data::teams();
	$cg = '<svg viewBox="0 0 ' . $Wd . ' ' . $Hh . '" role="img" aria-label="' . esc_attr( $ydsLab . ' by season' ) . '">';
	foreach ( array( 0, .5, 1 ) as $fr ) { $v = round( $mx * $fr / 100 ) * 100; $cg .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $Yc( $v ) . '" y2="' . $Yc( $v ) . '" stroke="var(--dbd-line)"/><text x="' . ( $Lm - 6 ) . '" y="' . ( $Yc( $v ) + 3 ) . '" font-size="9" fill="var(--dbd-muted)" text-anchor="end" ' . $fo . '>' . number_format( $v ) . '</text>'; }
	foreach ( $CRs as $i => $r ) { $v = (float) $r['yds']; $isc = $isCur( $r ); $tipc = '<b>' . (int) $r['season'] . ' ' . esc_html( $teamNick[ $r['team'] ]['nick'] ?? $r['team'] ) . '</b>' . ( $isc ? ' · through week ' . (int) $through : '' ) . '<br>' . number_format( $v ) . ' ' . esc_html( $ydsLab ) . ' in ' . (int) $r['g'] . ' game' . ( (int) $r['g'] === 1 ? '' : 's' ) . ' (' . number_format( $r['g'] ? $v / $r['g'] : 0, 1 ) . ' a game)';
		if ( $isc && $pace ) { $cg .= '<rect x="' . ( $Xc( $i ) - $bw / 2 ) . '" y="' . $Yc( $pace ) . '" width="' . round( $bw, 1 ) . '" height="' . max( 0, $Yc( $v ) - $Yc( $pace ) ) . '" fill="var(--dbd-line)" tabindex="0" data-tip="' . esc_attr( '<b>Pace</b> over ' . $PG . ' games: ' . number_format( $pace ) . ' ' . esc_html( $ydsLab ) ) . '"></rect>'; }
		$cg .= '<rect x="' . ( $Xc( $i ) - $bw / 2 ) . '" y="' . $Yc( $v ) . '" width="' . round( $bw, 1 ) . '" height="' . max( 1, $Yc( 0 ) - $Yc( $v ) ) . '" fill="' . ( $isc ? 'var(--dbd-panel)' : 'var(--dbd-team)' ) . '" stroke="var(--dbd-team)" stroke-width="' . ( $isc ? 2 : 0 ) . '" tabindex="0" data-tip="' . esc_attr( $tipc ) . '"></rect>';
		if ( $best && $r['season'] === $best['season'] ) { $cg .= '<text x="' . $Xc( $i ) . '" y="' . ( $Yc( $v ) - 5 ) . '" font-size="10" fill="var(--dbd-red)" text-anchor="middle" ' . $fo . '>★</text>'; }
		$cg .= '<text x="' . $Xc( $i ) . '" y="' . ( $Hh - 6 ) . '" font-size="9" fill="' . ( $isc ? 'var(--dbd-ink)' : 'var(--dbd-muted)' ) . '" text-anchor="middle" ' . $fo . '>' . substr( (string) $r['season'], -2 ) . ( $r['team'] !== $cur['team'] ? '·' . esc_html( $r['team'] ) : '' ) . '</text>'; }
	$cg .= '<line x1="' . $Lm . '" x2="' . ( $Wd - $Rm ) . '" y1="' . $Yc( 0 ) . '" y2="' . $Yc( 0 ) . '" stroke="var(--dbd-line-2)"/></svg>';
	/* career highs */
	$chdefs = array( 'rec' => array( array( 'Yards', 'receiving_yards', 'n' ), array( 'TD', 'receiving_tds', 'n' ), array( 'Receptions', 'receptions', 'n' ) ), 'rb' => array( array( 'Rush yards', 'rushing_yards', 'n' ), array( 'TD', 'td', 'n' ), array( 'Carries', 'carries', 'n' ) ), 'qb' => array( array( 'Yards', 'passing_yards', 'n' ), array( 'TD', 'passing_tds', 'n' ), array( 'Rating', 'rating', 'f1' ) ) )[ $cgrp ];
	$chh = ''; if ( $ns > 1 ) { $chh = '<span class="l">Career highs</span>'; foreach ( $chdefs as $x ) { $b = null; foreach ( $CRs as $r ) { if ( $r[ $x[1] ] !== null && ( ! $b || $r[ $x[1] ] > $b[ $x[1] ] ) ) { $b = $r; } } if ( $b ) { $chh .= '<span class="h">' . esc_html( $x[0] ) . ' <b>' . esc_html( $cf( $x[2], $b[ $x[1] ] ) ) . '</b> · ' . (int) $b['season'] . '</span>'; } } }
	echo $band( 'career', 'Career', 'season by season, back to his rookie year' );
	echo '<section class="dbd-panel dbd-career"><div class="dbd-ph"><span class="dbd-lab"><b>Career</b> · regular season</span><span class="dbd-badge">' . $ns . ' season' . ( $ns > 1 ? 's' : '' ) . '</span></div>';
	echo '<div class="dbd-cr-line">' . $lineh . '</div><p class="dbd-cr-say">' . $csay . '</p><div class="dbd-cr-chart">' . $cg . '</div>';
	echo '<div class="dbd-tw"><table class="dbd-glog dbd-stat dbd-sortable dbd-crtab"><thead><tr>' . $thead . '</tr></thead><tbody>' . $tbody . '</tbody><tfoot>' . $tfoot . '</tfoot></table></div>';
	echo ( $chh ? '<div class="dbd-cr-highs">' . $chh . '</div>' : '' ) . '<p class="dbd-note">Regular season only, from nflverse weekly stats back to 1999. This season is partial and drawn hollow; the pace is his per-game rate over ' . $PG . ' games. Bold cells are career highs.</p></section>';
	echo '</section>';
}

echo $band( 'more', 'Intel &amp; plays', 'the report, his best snaps, his teammates' ) . '<div class="dbd-three">';

/* ---- intel (PHP basics, JS fills the play-file lines) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Intel</b></span><span class="dbd-badge">Week ' . (int) $through . '</span></div><div class="dbd-pb"><dl class="dbd-kv dbd-intel">';
echo '<dt>Depth chart</dt><dd>' . ( $depth ? esc_html( $depth . ' · ' . $p['depth']['grp'] ) : 'not listed' ) . '</dd>';
echo '<dt>Injury report</dt><dd class="' . ( $p['inj'] ? $icls : 'good' ) . '">' . ( $p['inj'] ? esc_html( $p['inj']['what'] . ', ' . strtolower( $st ) ) : 'clean' ) . '</dd>';
echo '<dt>Red zone touches</dt><dd data-fill="rz">…</dd><dt>Third down</dt><dd data-fill="third">…</dd><dt>Explosive plays</dt><dd data-fill="expl">…</dd>';
if ( $grp !== 'qb' ) {
	echo '<dt>Drops</dt><dd class="' . ( $drops >= 3 ? 'bad' : '' ) . '">' . (int) $drops . ' on ' . (int) $tg . ' targets</dd><dt>Broken tackles</dt><dd>' . (int) $bt . '</dd>';
	if ( $rats ) { echo '<dt>Passer rating when targeted</dt><dd>' . $f1( array_sum( $rats ) / count( $rats ) ) . '</dd>'; }
	echo '<dt>Play action</dt><dd data-fill="pa">…</dd><dt>Contested</dt><dd data-fill="cont">…</dd>';
} else {
	echo '<dt>CPOE</dt><dd class="' . ( $cpoe >= 0 ? 'good' : 'bad' ) . '">' . $sgn( $cpoe, 1 ) . ' pts over expected</dd><dt>Sack rate</dt><dd>' . $pct( $div( $sk, $att + $sk ) ) . '</dd><dt>Play action</dt><dd data-fill="pa">…</dd><dt>Blitzed</dt><dd data-fill="blz">…</dd>';
}
echo '</dl></div></section>';

/* ---- best plays (JS) */
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Best plays</b> · by EPA</span></div><div class="dbd-pb"><ol class="dbd-best" id="dbd-best"></ol></div></section>';

/* ---- teammates (PHP) */
$mates = array();
foreach ( DBD_Data::team_players( $a ) as $m ) {
	if ( $m['id'] === $p['id'] || empty( $m['weeks'] ) ) { continue; }
	$s = function ( $k ) use ( $m ) { $v = 0; foreach ( $m['weeks'] as $w ) { $v += (float) ( $w[ $k ] ?? 0 ); } return $v; };
	$tc = $s( 'targets' ) + $s( 'carries' ); if ( ! $tc ) { continue; }
	$mates[] = array( $m, $tc, $s( 'receiving_yards' ) + $s( 'rushing_yards' ), $s( 'receiving_tds' ) + $s( 'rushing_tds' ), $s( 'receiving_epa' ) + $s( 'rushing_epa' ) );
}
usort( $mates, function ( $x, $y ) { return $y[1] <=> $x[1]; } ); $mates = array_slice( $mates, 0, 8 );
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Teammates</b> · share of the ball</span></div><div class="dbd-tw"><table class="dbd-mates dbd-stat dbd-sortable"><thead><tr><th>Player</th><th>Touches</th><th>Yds</th><th>EPA</th></tr></thead><tbody>';
foreach ( $mates as $x ) { list( $m, $tc, $y, $td, $ep ) = $x; $href = DBD_Router::url( array( 'player' => $m['id'] ) );
	echo '<tr class="dbd-trow" data-href="' . esc_url( $href ) . '"><td class="dbd-nm"><span class="dbd-nmi">' . DBD_Marks::player( $a, $m['num'], 30, $m['photo'] ?? null ) . '<a href="' . esc_url( $href ) . '">' . esc_html( $m['name'] ) . '</a><small>' . esc_html( $m['pos'] ) . '</small></span></td><td>' . (int) $tc . '</td><td data-v="' . (int) round( $y ) . '">' . (int) round( $y ) . ( (int) $td ? ' <small>· ' . (int) $td . ' TD</small>' : '' ) . '</td>' . $e( $ep ) . '</tr>'; }
echo '</tbody></table></div></section>';
echo '</div></section></main>';

/* ---- SCOPE §31: fold the methods notes, then put the short version under the tiles */
$rest = ob_get_clean();
$rest = preg_replace_callback( '/<p class="dbd-note"((?: style="[^"]*")?)>(.+?)<\/p>/s', function ( $m ) {
	if ( str_word_count( strip_tags( $m[2] ) ) < 14 ) { return $m[0]; }  /* a short note stays open */
	return '<details class="dbd-how"><summary>How this is measured</summary><p class="dbd-note"' . $m[1] . '>' . $m[2] . '</p></details>';
}, $rest );
$first = function ( $h ) { $h = trim( (string) $h ); return preg_match( '/^(.*?[.!?])(\s|$)/s', $h, $m ) ? $m[1] : $h; };
$short = array_filter( array(
	array( 'How good', ! empty( $epSay[0] ) ? $epSay[0] : '', '#dbd-good' ),
	array( 'Where it comes from', ! empty( $epSay[1] ) ? $epSay[1] : '', '#dbd-used' ),
	array( 'Fantasy', $fxShort ?? '', '#dbd-fantasy' ),
	array( 'How he\'s used', ! empty( $fsay[0] ) ? $first( $fsay[0] ) : '', '#dbd-used' ),
	array( 'Lately', $first( $shortLately ?? '' ), '#dbd-weeks' ),
	array( 'This week', isset( $twSay ) ? $first( $twSay ) : '', '#dbd-weeks' ),
), function ( $x ) { return $x[1] !== ''; } );
if ( $short ) {
	echo '<section class="dbd-panel dbd-short"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b></span></div><ul>';
	foreach ( $short as $x ) { echo '<li><a href="' . esc_attr( $x[2] ) . '">' . esc_html( $x[0] ) . '</a><span>' . $x[1] . '</span></li>'; }  /* the reads are already escaped where they were written */
	echo '</ul></section>';
}
echo $rest;
