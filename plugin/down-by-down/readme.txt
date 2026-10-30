=== Down by Down ===
Contributors: periodthree
Tags: nfl, football, analytics, play-by-play
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.2.2
License: GPLv2 or later

How every NFL offense actually plays, drawn on the field from public play-by-play data.

== Description ==

Put `[down_by_down]` on one page. That page becomes the app:

* the hub at the page's own URL (this week's slate, Game of the Week, standings),
* `?team=MIN` for a team's play explorer,
* `?game=2026_09_MIN_DET` for a matchup read (`&side=home` flips which offense is shown),
* `?view=matchups` for every week's slate, `?view=teams` for the team picker.

The plugin renders only the content column. Build the sidebar or header in your theme. Body classes
(`dbd-view-hub`, `dbd-view-team`, `dbd-view-game`, `dbd-view-matchups`, `dbd-view-teams`) tell your CSS
which view is showing. `[dbd_count games]` and `[dbd_count teams]` print the small numbers for menu items.

Data comes from nflverse by way of the project's GitHub repo. The plugin pulls `data/manifest.json` from the
configured folder URL, downloads only the files whose hash changed, and swaps them into
`wp-content/uploads/down-by-down/`. It pulls once a day on its own and immediately when the repo's nightly
build calls `POST /wp-json/dbd/v1/sync` with the token shown on Settings > Down by Down.

Not affiliated with the NFL or any team. No team logos are used; team marks are generated from team colors.

== Changelog ==

= 1.2.2 =
* Player hero: with Player photos on, the headshot sits large in the open space right of the title, cut out over a soft team-color glow; the jersey tile stays by the name. Larger marks in the Players index (34px) and Teammates (30px).
* Data build: teams on bye kept their players. The roster filter took the league's latest week, so a bye week emptied that team's roster (Saints, Giants, Texans and 49ers in Week 8); it now takes each team's own latest week.

= 1.2.1 =
* Optional player photos: a Player photos switch on the Down by Down admin page (off by default) shows the NFL headshots nflverse publishes, lazily, on top of the jersey tile, falling back to the tile if an image fails. The site hotlinks the league's CDN; the switch removes every photo at once.
* Review fixes on the player layer. Quarterback pages show that passer's own throws (play rows now carry the passer id), not the whole team's. Traded players keep every week of the season, with the other team marked in the game log. Charting lines say so when a play file has no FTN data instead of showing zeros. Games played come from snap weeks as well as stat weeks. Search matches name, number, team abbreviation or college on the server and in the browser alike, and quarterbacks show pass attempts in the index. Draft shows the overall pick, since nflverse does not record the round.

= 1.2.0 =
* Player layer. New Players index at ?view=players (team tiles, position switch, search; league leaders by default, &team= for a roster) and player pages at ?player=<id>: jersey-tile hero with bio, depth-chart slot and injury line, position-aware stat tiles, field map of every target (or carries by gap for backs), depth bands, game log with snap share, touches by down, quarter and score, an Intel rail (drops, broken tackles, rating when targeted, play action, contested catches, blitz for QBs), best plays by EPA, and teammates.
* Explorer: names in Who gets the ball link to player pages. Team pages link to their roster.
* Data build: play rows carry the ball handler's nflverse id and, when FTN charting is available, play action, screen, RPO, motion, blitz, contested and drop flags. New players/{TEAM}.json (roster, weekly lines, snap share, PFR extras, injury, depth) and players/index.json. The Action downloads stats_player_week, snap_counts, pfr_advstats, injuries, depth_charts and ftn_charting, each optional.
* Sitemap lists the index, each team's roster view and every player page. Player pages carry Person JSON-LD. Sync allows the new files.

= 1.1.1 =
* Team explorer: no more tabs. Drive chart, Pass & run map, Situations and Tendencies are all on the page as stacked panels. On desktop the Play detail and Who gets the ball rail pins alongside while you scroll; on narrow screens it sits right under the drive chart.

= 1.1.0 =
* Team explorer: new Tendencies tab with five charts drawn by Chartist (bundled, no CDN): pass rate by down and distance with the league rate for each spot, shotgun vs under center by down, cumulative EPA across the season with game rules, receivers by targets and depth colored by position, and a quarter-by-game-state pass-rate grid. Every chart follows the filters.
* The old Tendencies tables are now the Situations tab.
* Data build: each play carries the ball handler's position; metrics carry league pass rates by down and distance, by formation per down, and by quarter per game state. Both arrive with the next nightly build; until then the charts fall back to the overall league pass rate.

= 1.0.8 =
* Fix: the play description, kickers and subtitles lost their padding and margins to an over-specific reset; resets now use :where() so component styles win.
* The breadcrumb's first item reads DOWN X DOWN.
* Search: an XML sitemap provider lists every app URL (hub, each week's matchups, the team picker, all 32 team pages, every game both ways) at /wp-sitemap.xml. Matchup pages carry SportsEvent JSON-LD.

= 1.0.7 =
* Phones: the Divi sidebar column now actually goes full width above the content; the plugin's own 232px rule was overriding its phone override.
* Phones: standings show rank numbers without bars so team names keep their room.
* Head-to-head stat labels stay on one line; the snaps badge no longer stretches when the team title stacks.
* The pass-zone grids stack when their panel is narrower than 620px instead of squeezing captions onto three lines.
* Standings show nicknames instead of full names when the column is narrow, so nothing truncates on phones.

= 1.0.6 =
* Standings tables use a fixed column plan: the team name takes the remaining width and truncates, record and rank columns are set widths, rank bars fill their cells. No more clipped Def column or short tables.
* The hub no longer prints a "What this is" block; that copy belongs to the site's About page.

= 1.0.5 =
* Fix: standings rows shared a class with a flex component and lost their table layout. Rows are dbd-trow and table parts are pinned to table display.

= 1.0.4 =
* Fix: the team picker and matchup views inherited component styles because the view wrapper shared their class names. Wrappers are now dbd-view-*.
* Hub accepts /#slate and /#teams as well as /#dbd-slate and /#dbd-teams.

= 1.0.3 =
* Explorer: game picker, filters, tiles and charts are spaced like the rest of the app again.
* The admin page moves from Settings to its own Down by Down entry in the admin sidebar.

= 1.0.2 =
* Divi 5 flex rows: the app's column now grows to fill the space beside the sidebar instead of keeping a fixed width that overflowed the viewport.

= 1.0.1 =
* Divi: the app page's section, row and column go full width so the content column fits the viewport.
* Sync endpoint shown and called in the ?rest_route= form, which works without pretty permalinks.

= 1.0.0 =
* First release.
