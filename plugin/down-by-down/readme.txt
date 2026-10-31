=== Down by Down ===
Contributors: periodthree
Tags: nfl, football, analytics, play-by-play
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.4.2
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

= 1.4.2 =
* One panel edge everywhere, and a roomier one: every panel's label, reads, notes, legends, chart grids, headings and tables now sit 20px in from the panel's border on desktop and tablet (they were at 14 to 16px after 1.4.1), and 12px on phones (where tables sat at 10px and labels at 15px).
* Team logos in the hero positions (the team and players titles, the player hero's team link, the game header) are 30% larger, capped so a wide wordmark stays clear of the text beside it.

= 1.4.1 =
* Edges: every table in a panel now starts on the same 14px line as the panel's label (before: anywhere from 2 to 24px, depending on the table); the defense-tier table no longer touches the panel edge.
* Rows that carry a headshot, jersey or logo get 7px above and below it, so photos no longer touch row to row.
* The field maps' legends show dots for dot charts; the defense-tier chips keep a gap before their label.
* Team logos at a common visual size: the nightly build measures each logo's artwork and stores a scale, so a thin wordmark-shaped logo (the Jets') and a solid shield (the Raiders') read alike. Logos load through ESPN's resizer at three times their display size: about 2 KB each instead of up to 130 KB.

= 1.4.0 =
* Design pass. The theme's table padding (Divi's inline 24px on every cell) no longer reaches inside the app: every plugin rule now sits one class above the theme, so tables are about half as wide as before at the same type. No text under 11px anywhere; uppercase labels on two letter-spacings instead of eleven; panel headers one height, a long label truncating with its full text on hover; reads and notes on the panel's 14px edge; a spacing scale (4, 8, 14, 22px) for panels and bands.
* Phone hero: the headshot sits beside the name as a round cutout, the team block is one row, and Share card, Compare and Players like him wrap as one row.
* Team logos: a new "Show NFL team logos" setting, off by default, beside the headshot one. When on, the real logos load over the drawn team tiles and pennants everywhere they appear, and the share card puts the team's logo in its corner; a logo that fails to load leaves the drawn mark. The nightly build adds the logo URLs to the team data, so run Sync now once after updating.

= 1.3.13 =
* Fix: player pages stopped after the trend panel on 1.3.9 through 1.3.12 for any graded player, hiding the game log, Who he's faced, Career and Intel bands behind a critical error. The Playstyle block reused the season-targets variable for its tags.
* The Plugin check workflow now renders the player, players, team and hub views under a WordPress shim on every pull request, so a fatal shows its message in CI instead of on the site.

= 1.3.12 =
* Share card: every player page now has a 1200 × 630 image of its hero, season line and grade bars, rendered on the server with GD on first request and cached under uploads. It is the page's og:image and Twitter card, so a shared link unfurls into the card, and a Share card button in the hero opens it or hands it to the phone's share sheet. Needs GD with FreeType on the host; without it the button and tags are simply absent.
* The plugin bundles Tourney, Barlow Condensed, IBM Plex Sans and IBM Plex Mono as TTF (Open Font License) for the renderer.

= 1.3.11 =
* This week: a strip at the top of the player page under the season tiles with his next game (kickoff in the site's time zone, stadium, the market line), the opposing defense's rank at his position and what it allows, his line against defenses of that tier this season and the last meeting with this opponent, his injury status and snap share over the last three games, and a one-line read. Once the week's game is final it becomes Next up; a bye week says so.

= 1.3.10 =
* Career: a new band on the player page between Week by week and Intel & plays. Career totals and span, a read on his best season and this season's 17-game pace, a yards-by-season chart with the current season hollow and its pace shown, a season-by-season table with a totals row and career highs in bold, and a career-highs strip. Regular season only, back to 1999.
* Build: data/career/{year}.json caches every finished season's totals once from nflverse weekly stats; players/career/{TEAM}.json is assembled nightly with this season added. The sync fetches the team career files; an older data file skips the band.

= 1.3.9 =
* Playstyle: a new panel at the top of the player page's How he's used band. Eight usage traits per position (receivers: deep share, screens, middle of the field, yards after the catch, third-down and red-zone shares, motion, play action; backs: inside runs, shotgun runs, receiving share, third-down, red-zone and short-yardage shares, RPO, motion; quarterbacks: deep share, middle of the field, play action, RPO, scrambles, shotgun, performance against the blitz, motion) as neutral league-percentile bars, trait tags, and a generated read of how he is used.
* Players index: the league view lists every player sorted by snap share instead of the top 60.
* Build: each player record carries fp (the traits as shares of his own plays); league.json carries the per-trait pools. Scrambles attach to the quarterback who ran them. An older data file skips the panel.

= 1.3.8 =
* Movers: a new panel at the top of the Players index for the last full week. Three boards (snap share, target share, EPA) list the five biggest climbers and fallers against each player's own season to that point, with a sparkline of his season, the change, and before-and-after figures; a New faces strip names debuts and first start-level weeks; a one-line read calls the biggest swing in snaps.
* Movers are league-wide; the team tile marks that team's rows, the position switch and search dim the rest.
* Build: players/movers.json, computed from the weekly lines (no previous-week snapshot needed); an older data file skips the panel.

= 1.3.7 =
* The shape of his season: a new panel at the top of the player page's Week by week band. Four small charts per position (targets, receiving yards, EPA per target and aDOT for receivers; touches, scrimmage yards, EPA per touch and targets for backs; dropbacks, passing yards, EPA per dropback and CPOE for quarterbacks), each with his number every week, the league's middle half at his position as a shaded band with its median, and his trailing three games as a line. Byes, games he missed and the current week are marked.
* A generated read under the charts: whether his usage is climbing, slipping or steady against his season average, how the yards are following, streaks above or below a starter's median, season highs and lows, and whether efficiency is moving too.
* Build: league.json carries per-game quartiles for the trend metrics; an older data file still draws the charts without the league band.

= 1.3.6 =
* Design cleanup: the horizontal scrollbars are gone at desktop and tablet widths. The player page is reorganized into five bands by question under a sticky jump nav (How good is he, How he's used, Who he works with, Week by week, Intel & plays); the rail is retired, so wide tables get the whole width. The team explorer's Who gets the ball and the game page's Head to head take a full row.
* One compact rule for every stat table (11px numbers, tighter cells) and column tiers: second- and third-priority columns hide as the table's container narrows, and on phones the game log's Defense rank folds under the opponent as a small chip. Only the connection table and the players index keep an in-panel scroll on phones.
* The hub's games strip and the team game picker wrap into rows instead of scrolling sideways; the drive chart scales to its panel; the breadcrumb wraps on phones.

= 1.3.5 =
* Opponent context. The game log gains a Defense column: a chip for the defense faced that week, ranked against his position (EPA allowed per target to wide receivers or tight ends, per designed carry for backs, per dropback for quarterbacks; 1 is the toughest), tiered navy for the top 10 and green for the bottom 10, sortable, with the number and the league average in the tooltip.
* "Who he's faced" panel under the game log: where his slate ranks among all 32 teams and what is ahead; the eighteen-week ribbon (played solid, ahead dashed, this week outlined, DNP marked); his line against top-10, middle and bottom-10 defenses; and yards against the rank of the defense with a one-line read from the correlation. Rendered server-side from the metrics and schedule files already shipped; no data build needed.

= 1.3.4 =
* The connection. A new player-page panel between the field map and the game log. On a quarterback's page: every receiver he has targeted in a sortable table (share of his throws, catch rate, aDOT, YAC, third-down and red-zone targets, EPA per target), a takeaway line (first read, deep threat, most efficient pairing, third-down favorite), the target map with dots colored by receiver and click-to-isolate chips, weekly stacked columns of how he spreads the ball, and a receivers-by-depth grid. On everyone else's page: his line by passer with a "to everyone else" row under each quarterback, passer rating when targeting him, and the same map, weekly columns and depth grid split by passer. All of it comes from the play rows already shipped; no data build needed.
* Sortable tables can carry a data-v value per cell, so a cell like "5 · 2 conv" sorts by 5.

= 1.3.3 =
* Percentile markers get a real tooltip on hover or focus: player, value, percentile, rank of the pool and the league median; on the compare page the row dims the other players while you look.
* Backs' run maps are drawn off the line of scrimmage: the five linemen on the line, carries rising from each gap, the back behind center with his number. Player page and compare page alike, on one scale across compared players.
* Compare: Copy link and Start over buttons in the Who panel and the sticky key. The tray is kept per position group, so a receiver set never follows you into a backs compare, and the browser remembers your eight most recent comparisons as one-click links.
* Compare: a player key in slot colors sticks to the top of the screen as you scroll, every graphic carries its own key, the week and season tables show player names as colored chips, and the race lines are labeled at their ends. No more guessing which color is who halfway down the page.

= 1.3.2 =
* Compare graphics. Seven visuals join the compare page: a percentile radar, a where-he-wins heat grid (EPA per target by lane and depth), every target on one field in slot colors, a depth butterfly for the first two slots, the cumulative yards race, grouped weekly bars, and a floor-and-ceiling strip that marks each week boom, solid or dud against the quartiles of every qualified player's game weeks. Radar and the strip render server-side; the rest draw from the play files and Chartist.
* Data build: league.json carries weekly yardage quartiles per group.

= 1.3.1 =
* Compare. ?compare=id,id[,id] puts two or three players of one position group side by side: the league percentile bars with a marker per player, the field maps on one shared scale, a week-aligned log with a metric switch, and season stats with ranks, best value in every row marked. Three ways in: the Compare button on every player page (it builds on whoever you picked last), the empty slot's search across every team, and the link itself. Each player page also offers Players like him, the three qualified players nearest in usage, one click to a head to head.

= 1.3.0 =
* Player grades. Every player page gets a Where he ranks panel: eight metrics for his position group placed against every qualifying player in the league (receivers and tight ends with 15+ targets, backs with 30+ touches, quarterbacks with 50+ attempts), each with his value and a percentile. Season stats in the rail carry a league rank beside every graded number. The game log has Default, Advanced and Traditional views (passer rating included for quarterbacks).
* Data build: per-player season metrics on each player record and a new players/league.json with the pool per group. Sync allows it.

= 1.2.2 =
* Players index: bigger team tiles, search spans every team, and every stat table on the site sorts by its column headings (index, game log, teammates, Who gets the ball, situations). Standings stay grouped by division.
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
