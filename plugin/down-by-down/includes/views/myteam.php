<?php
/** My team (SCOPE §36): the players someone follows as a lineup. Rendered from ?myteam=; myteam.js adds search, remove, copy and the device copy. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$r = DBD_Router::$route; $rows = $r['myteam'] ?? array(); $season = DBD_Data::season(); $cw = (int) DBD_Data::current_week();
echo DBD_Router::topbar( array( 'players' => DBD_Router::url( array( 'view' => 'players' ) ), 'My team' => null ) );
echo '<main class="dbd-content">';
echo '<div class="dbd-ttitle"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">' . esc_html( "$season season · your lineup" . ( $rows ? " · Week $cw" : '' ) ) . '</p><h1 class="t-stagger-line t-stagger-line--2">My <em>team</em></h1>';
echo '<p class="dbd-sub t-stagger-line t-stagger-line--3">' . ( $rows ? 'The players you follow, laid out as a lineup: this week\'s matchup, status, snap trend and fantasy pace for each. Saved on this device; the address bar is the share link.' : 'Follow your fantasy lineup, or any players you like, on one page.' ) . '</p></div>';
if ( $rows ) { echo '<div class="dbd-badge" id="mt-count">' . count( $rows ) . ' of ' . DBD_MyTeam::MAX . ' players</div>'; }
echo '</div>';
$search = '<div class="dbd-fg mt-q"><label for="mt-q">Add a player</label><input class="dbd-srch" id="mt-q" type="search" placeholder="Name, team or number" autocomplete="off" aria-autocomplete="list" aria-controls="mt-sug"><ul class="mt-sug" id="mt-sug" role="listbox" hidden></ul></div>';
if ( ! DBD_Data::player_index() ) { echo '<div class="dbd-empty"><h2>No player data yet</h2><p>My team fills in with the next nightly data build.</p></div></main>'; return; }
if ( ! $rows ) {
	$sample = DBD_Router::url( array( 'myteam' => implode( ',', DBD_MyTeam::sample() ) ) );
	echo '<section class="dbd-panel mt-empty"><div class="mt-e"><h2>Start your team</h2><p>Add players by name below, or with the <b>+ My team</b> button on any player page. Your team is saved on this device, and the address bar is a link you can send or open on your phone.</p>' . $search;
	echo '<p class="m">Or try it with a <a href="' . esc_url( $sample ) . '" class="mt-sample">sample team</a>: this season\'s points-a-game leaders at each slot.</p></div></section>';
	echo '<section class="dbd-panel mt-board"><div class="dbd-ph"><span class="dbd-lab"><b>Lineup</b> · QB, 2 RB, 2 WR, TE, FLEX, then the bench</span></div>' . DBD_MyTeam::board( array(), 1 ) . '</section>';  /* the empty lineup: every slot has its own + */
} else {
	echo '<section class="dbd-panel mt-add"><div class="mt-addrow">' . $search . '<div class="dbd-fg"><span>Fantasy scoring</span>' . DBD_Fantasy::seg() . '</div><div class="mt-acts"><button class="dbd-cbtn" type="button" id="mt-copy">Copy link</button><button class="dbd-cbtn" type="button" id="mt-clear">Clear</button></div></div></section>';
	echo '<section class="dbd-panel mt-sum"><div class="dbd-ph"><span class="dbd-lab"><b>This week</b> · Week ' . $cw . ', your starters</span><span class="dbd-badge">best lineup by points a game</span></div>';
	foreach ( DBD_Fantasy::FMT as $i => $fm ) { echo '<div data-fx="' . $fm . '">' . DBD_MyTeam::summary( $rows, $i ) . '</div>'; }
	echo '</section>';
	echo '<section class="dbd-panel mt-board"><div class="dbd-ph"><span class="dbd-lab"><b>Lineup</b> · QB, 2 RB, 2 WR, TE, FLEX, then the bench</span><span class="mt-cmp">' . DBD_MyTeam::compare_links( $rows ) . '</span></div>';
	foreach ( DBD_Fantasy::FMT as $i => $fm ) { echo '<div data-fx="' . $fm . '">' . DBD_MyTeam::board( $rows, $i ) . '</div>'; }
	echo '<p class="dbd-note">The lineup fills each slot with your highest scorer by points a game in the format you pick, skipping players on bye and players ruled out (Out or Doubtful) on the injury report; FLEX takes the best remaining back, receiver or tight end. A game already played shows his points for the week. The matchup is the opponent\'s rank in points allowed to his position (1st allows the most; green is a soft matchup, red a tough one). Snaps are his last ' . DBD_MyTeam::SNAPW . ' weeks. ▲ or ▼ marks a last three games ' . (int) DBD_MyTeam::SWING . ' points above or below his average.</p></section>';
}
echo '</main>';
/* the search list: the index, trimmed to what the suggestions need */
echo '<script type="application/json" id="mt-index">' . wp_json_encode( array_map( function ( $o ) { return array( 'id' => $o['id'], 'name' => $o['name'], 'team' => $o['team'], 'pos' => DBD_MyTeam::fpos( $o ), 'num' => $o['num'], 'fp' => isset( $o['fp'][1] ) ? round( (float) $o['fp'][1], 1 ) : null ); }, DBD_Data::player_index() ) ) . '</script>';
