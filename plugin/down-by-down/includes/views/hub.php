<?php
/** The hub: title, Game of the Week, score strip, this week's slate, standings, about. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$week   = DBD_Data::current_week();
$season = DBD_Data::season();
$games  = DBD_Data::week_games( $week );
$label  = DBD_Router::week_label( $week );
$ranked = DBD_Data::through_week() > 0;
$stats  = DBD_Data::stats();

echo DBD_Router::topbar( array( strtolower( $label ) => null ) );
echo '<main class="dbd-content">';

/* ---- title + game of the week */
$gotw = null; $gotw_best = null;
if ( $ranked ) {
	foreach ( $games as $g ) {
		if ( DBD_Router::is_final( $g ) ) { continue; }
		$b = DBD_Takeaways::best_edge( $g['away'], $g['home'] );
		if ( ! $gotw_best || $b['s'] > $gotw_best['s'] ) { $gotw = $g; $gotw_best = $b; }
	}
}
echo '<div class="dbd-title dbd-hero"><div class="dbd-ghost" aria-hidden="true">10 20 30 40 50</div><div class="t-stagger">';
echo '<p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · $label" ) . '</p>';
echo '<h1 class="t-stagger-line t-stagger-line--2">Every snap, drawn on the <em>field.</em></h1>';
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">How every NFL offense actually plays, from public play-by-play, updated every night. <a href="#dbd-slate">Open a game</a> to see how the offense matches up against the defense, or <a href="#dbd-teams">pick a team</a> and dig through its plays.</p>';
echo '</div>';
if ( $gotw ) {
	$k = DBD_Data::kickoff( $gotw ); $O = $gotw_best['O']; $D = $gotw_best['D'];
	$cards = DBD_Takeaways::cards( $O, $D );
	$blurb = $cards ? $cards[0][3] : '';
	echo '<aside class="dbd-panel dbd-gotw" aria-label="Game of the week"><div class="dbd-ph"><span class="dbd-lab"><b>Game of the week</b></span><span class="dbd-badge">' . DBD_Router::kickoff_html( $gotw ) . '</span></div><div class="dbd-pb">';
	echo '<div class="dbd-teams">';
	foreach ( array( $gotw['away'], $gotw['home'] ) as $a ) {
		$t = DBD_Data::team( $a );
		echo '<div class="dbd-t">' . DBD_Marks::tile( $a, 34 ) . '<b>' . esc_html( $a ) . '</b><span>' . esc_html( $t['nick'] . ' · ' . DBD_Data::record_text( $a ) ) . '</span></div>';
		echo '<div class="dbd-rkline">OFF #' . (int) DBD_Data::rank( 'off', $a ) . ' · DEF #' . (int) DBD_Data::rank( 'def', $a ) . '</div>';
	}
	echo '</div><div class="dbd-line"><span>' . esc_html( DBD_Router::line_text( $gotw ) ) . '</span><span>O/U ' . esc_html( $gotw['total'] ?? '' ) . '</span><span>' . esc_html( $gotw['stadium'] ?? '' ) . '</span></div>';
	if ( $blurb ) { echo '<p>' . esc_html( $blurb ) . '</p>'; }
	echo '<a class="dbd-cta" href="' . esc_url( DBD_Router::url( array( 'game' => $gotw['id'], 'side' => $O === $gotw['home'] ? 'home' : 'away' ) ) ) . '">Open the matchup</a></div></aside>';
}
echo '</div>';

/* ---- score strip */
if ( $games ) {
	echo '<div class="dbd-strip" aria-label="This week\'s games">';
	foreach ( $games as $g ) {
		$f = DBD_Router::is_final( $g ); $aw = $f && $g['away_score'] > $g['home_score']; $hw = $f && $g['home_score'] > $g['away_score'];
		$sel = $gotw && $g['id'] === $gotw['id'] ? ' sel' : '';
		echo '<a class="dbd-sc t-avatar' . $sel . '" href="' . esc_url( DBD_Router::url( array( 'game' => $g['id'] ) ) ) . '">';
		echo '<div class="dbd-hd"><span class="dbd-badge' . ( $f ? ' fin' : '' ) . '">' . ( $f ? 'Final' : 'Upcoming' ) . '</span><span>' . ( $f ? '' : DBD_Router::kickoff_html( $g ) ) . '</span></div>';
		foreach ( array( array( $g['away'], $g['away_score'] ?? null, $aw ), array( $g['home'], $g['home_score'] ?? null, $hw ) ) as $row ) {
			list( $a, $s, $won ) = $row; $t = DBD_Data::team( $a );
			echo '<div class="dbd-row' . ( $f && ! $won ? ' l' : '' ) . '">' . DBD_Marks::tile( $a, 24 ) . '<div><b>' . esc_html( $t['nick'] ) . '</b><small>' . esc_html( DBD_Data::record_text( $a ) ) . '</small></div><span class="dbd-s">' . ( $f ? (int) $s : '–' ) . '</span></div>';
		}
		echo '</a>';
	}
	echo '</div>';
}

/* ---- slate */
echo '<section class="dbd-panel" id="dbd-slate"><div class="dbd-ph"><span class="dbd-lab"><b>The slate</b> · ' . esc_html( $label ) . ' · ' . count( $games ) . ' games</span><span>Lines and totals from nflverse. Kickoffs in your local time. <a href="' . esc_url( DBD_Router::url( array( 'view' => 'matchups' ) ) ) . '">Every week</a></span></div>';
include DBD_PATH . 'includes/views/partials/slate-table.php';
echo '</section>';

/* ---- standings */
echo '<div class="dbd-two" id="dbd-teams">';
foreach ( array( 'AFC', 'NFC' ) as $conf ) { include DBD_PATH . 'includes/views/partials/standings.php'; }
echo '</div>';
if ( ! $ranked ) { echo '<p class="dbd-note">Ranks appear after Week 1.</p>'; }

/* ---- about */
echo '<div class="dbd-about" id="dbd-about"><h2>What this is</h2><p>Down by Down is a free site built on public play-by-play data from <a href="https://github.com/nflverse/nflverse-data" rel="noopener">nflverse</a>. It updates every night during the season. EPA is expected points added. A play counts as a success when it gains 40% of the needed yards on 1st down, 60% on 2nd, and 100% on 3rd and 4th. Offense #1 moves the ball best; defense #1 allows the least.' . ( ! empty( $stats['snaps'] ) ? ' ' . number_format( (int) $stats['snaps'] ) . ' snaps charted so far this season.' : '' ) . ' Not affiliated with the NFL or any team.</p></div>';
echo '</main>';
