=== Down by Down ===
Contributors: periodthree
Tags: nfl, football, analytics, play-by-play
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.7
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

= 1.0.7 =
* Phones: the Divi sidebar column now actually goes full width above the content; the plugin's own 232px rule was overriding its phone override.
* Phones: standings show rank numbers without bars so team names keep their room.
* Head-to-head stat labels stay on one line; the snaps badge no longer stretches when the team title stacks.

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
