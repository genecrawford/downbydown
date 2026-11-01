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
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">How every NFL offense actually plays, from public play-by-play, updated every night. <a href="#dbd-games">Open a game</a> to see how the offense matches up against the defense, or <a href="#dbd-teams">pick a team</a> and dig through its plays.</p>';
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
		echo '<div class="dbd-rkline">OFF #' . (int) DBD_Data::rank( 'off', $a ) . ' · ' . DBD_Defense::link( $a, 'DEF #' . (int) DBD_Data::rank( 'def', $a ) ) . '</div>';  /* SCOPE §39: to the defense tab */
	}
	echo '</div><div class="dbd-line"><span>' . esc_html( DBD_Router::line_text( $gotw ) ) . '</span><span>O/U ' . esc_html( $gotw['total'] ?? '' ) . '</span><span>' . esc_html( $gotw['stadium'] ?? '' ) . '</span></div>';
	if ( $blurb ) { echo '<p>' . esc_html( $blurb ) . '</p>'; }
	echo '<a class="dbd-cta" href="' . esc_url( DBD_Router::url( array( 'game' => $gotw['id'], 'side' => $O === $gotw['home'] ? 'home' : 'away' ) ) ) . '">Open the matchup</a></div></aside>';
}
echo '</div>';

/* ---- score strip */
if ( $games ) {
	echo '<span id="dbd-games" class="dbd-anchor"></span><span id="slate" class="dbd-anchor"></span><span id="dbd-slate" class="dbd-anchor"></span><div class="dbd-strip" aria-label="This week\'s games">';  /* the old #slate and #dbd-slate links land on the games too */
	foreach ( $games as $g ) {
		$f = DBD_Router::is_final( $g ); $aw = $f && $g['away_score'] > $g['home_score']; $hw = $f && $g['home_score'] > $g['away_score'];
		$sel = $gotw && $g['id'] === $gotw['id'] ? ' sel' : '';
		echo '<a class="dbd-sc t-avatar' . $sel . '" href="' . esc_url( DBD_Router::url( array( 'game' => $g['id'] ) ) ) . '">';
		echo '<div class="dbd-hd"><span class="dbd-badge' . ( $f ? ' fin' : '' ) . '">' . ( $f ? 'Final' : 'Upcoming' ) . '</span><span>' . ( $f ? '' : DBD_Router::kickoff_html( $g ) ) . '</span></div>';
		foreach ( array( array( $g['away'], $g['away_score'] ?? null, $aw ), array( $g['home'], $g['home_score'] ?? null, $hw ) ) as $row ) {
			list( $a, $s, $won ) = $row; $t = DBD_Data::team( $a );
			echo '<div class="dbd-row' . ( $f && ! $won ? ' l' : '' ) . '">' . DBD_Marks::tile( $a, 24 ) . '<div><b>' . esc_html( $t['nick'] ) . '</b><small>' . esc_html( DBD_Data::record_text( $a ) ) . '</small></div><span class="dbd-s">' . ( $f ? (int) $s : '–' ) . '</span></div>';
		}
		$ln = DBD_Router::line_text( $g ); $tot = isset( $g['total'] ) && $g['total'] !== null && $g['total'] !== '' ? 'O/U ' . $g['total'] : '';  /* SCOPE §31: the line rides on the card, so the hub needs no second slate */
		if ( ! $f && ( $ln || $tot ) ) { echo '<div class="dbd-scln">' . esc_html( implode( ' · ', array_filter( array( $ln, $tot ) ) ) ) . '</div>'; }
		echo '</a>';
	}
	echo '</div>';
}

/* ---- slate: the cards above carry every game, its kickoff and its line; the full table with lines, totals and recap links lives on Matchups (SCOPE §31) */
echo '<p class="dbd-slatelink"><a href="' . esc_url( DBD_Router::url( array( 'view' => 'matchups', 'week' => $week ) ) ) . '">The full slate: every line and total, and every week</a></p>';

/* ---- standings */
echo '<span id="teams" class="dbd-anchor"></span><div class="dbd-two" id="dbd-teams">';
foreach ( array( 'AFC', 'NFC' ) as $conf ) { include DBD_PATH . 'includes/views/partials/standings.php'; }
echo '</div>';
if ( ! $ranked ) { echo '<p class="dbd-note">Ranks appear after Week 1.</p>'; }

/* The About copy lives on the site's own About page, not here. */
echo '</main>';
