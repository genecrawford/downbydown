=== Down by Down ===
Contributors: periodthree
Tags: nfl, football, analytics, play-by-play
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
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

= 1.0.0 =
* First release.
