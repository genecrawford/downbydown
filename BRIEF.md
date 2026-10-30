# Play Explorer: Project Brief

Handoff from a Claude chat session to Claude Code. Owner: Gene Crawford (Period Three).

## What this is

A free website that shows how every NFL team actually plays, built on public play-by-play data. It will live inside a WordPress site as a plugin. A working, clickable mockup with real data is in `/mockup` (open `mockup/index.html`).

**Status:** prototype approved for look and flow. No production code yet.

## Decisions already made

- **Free.** No accounts, payments, or premium tier for now.
- **WordPress plugin.** Ships as one plugin. A single shortcode, `[play_explorer]`, placed on one page turns that page into the whole app. Views are routed with query args: no args = hub, `?team=MIN` = team page, `?game=<game_id>` = matchup page (`&side=home` flips which offense is shown).
- **Static JSON data.** No database. A Python script builds JSON files; the plugin reads them. Matchup and hub pages should render server side in PHP (good for SEO). The team play explorer renders client side in JS from a per-team JSON file.
- **Zero maintenance is the goal.** Data refreshes automatically on a nightly schedule during the season.
- **No team logos** (trademarks). Team names, abbreviations and team colors only. Footer says "Not affiliated with the NFL or any team."

## Pages (see the mockup)

1. **Hub** (`mockup/index.html`)
   - This week's games as cards: kickoff time, spread, total, final score once played, link to the matchup.
   - All 32 teams grouped by division, each with record, offense EPA rank and defense EPA rank, linking to the team page.
2. **Team play explorer** (`mockup/team.html`, Vikings example)
   - Game picker (multi-select), filters: down, play type, field zone, formation (shotgun or under center), quarter, player.
   - Summary tiles: plays, pass rate, EPA/play, success rate, yards/play, explosive plays, first downs/TDs.
   - Tabs: **Drive chart** (every snap drawn on a field, grouped by game and drive, team always moves left to right), **Pass and run map** (targets by depth and side; runs by gap), **Tendencies** (down and distance, quarter, game score).
   - Side panel: clicked play detail, and a "Who gets the ball" usage table that filters on click.
3. **Matchup** (`mockup/matchup.html`, Vikings offense vs Lions defense)
   - Four takeaway cards (edge, opportunity, risk, wash).
   - Head to head table: offense value and rank, defense allowed value and rank, NFL average, and an edge pill.
   - Pass zone heatmaps (short/deep by left/middle/right) for both sides.
   - In the mockup, the takeaway text is hand-written. In production it should be generated from the numbers (rules below).

## Data

All from nflverse, free, updated nightly in season.

| File | URL |
|---|---|
| Play-by-play | `https://github.com/nflverse/nflverse-data/releases/download/pbp/play_by_play_2026.csv.gz` |
| Rosters (for receiver position) | `https://github.com/nflverse/nflverse-data/releases/download/rosters/roster_2026.csv` |
| Teams, names, colors | `https://github.com/nflverse/nflverse-data/releases/download/teams/teams_colors_logos.csv` |
| Schedule, lines, totals | `https://raw.githubusercontent.com/nflverse/nfldata/master/data/games.csv` |

`tools/build_data.py` already works. It produces:

- `data/teams.json`: `{ABBR: {name, nick, conf, div, c1, c2}}`
- `data/plays/{ABBR}.json`: `{team, byes, games:[{id,week,opp,home,pf,pa}], keys:[...], plays:[[...]]}`. Plays are arrays to keep files small (~60 to 100 KB per team). The `keys` list names each column: game, quarter, clock, down, distance, yardline_100, play_type, result kind (run, comp, inc, int, sack, scramble), yards, EPA, success, air yards, YAC, direction, pass depth, run gap, shotgun, TD, first down, drive, score differential, player, description.
- `data/metrics.json`: offense and defense metrics for every team, league averages, and ranks (`rank.off[TEAM][metric]`, `rank.def[TEAM][metric]`).
- `data/schedule.json`: the chosen week's games.

Run: `python3 build_data.py pbp.csv.gz roster.csv games.csv teams.csv <out_dir> <week>`

## Metric definitions

- **EPA:** expected points added, straight from nflverse.
- **Success:** nflverse `success` column (40% of needed yards on 1st down, 60% on 2nd, 100% on 3rd/4th).
- **Explosive:** pass of 20+ yards or run of 10+.
- **Run stats** exclude QB scrambles.
- **Third down conversion:** first down or TD on 3rd down plays.
- **Red zone TD rate:** drives that reached the opponent 20 and scored a TD.
- **Targets by position:** join `receiver_player_id` to roster `gsis_id`, use `position` (WR, TE, RB).
- **Ranks:** 1 to 32. Offense: higher is better, except sack rate. Defense: allowing less is better, except sack rate (more is better).
- **Edge pill:** `score = ((33 - off_rank)/32 + def_rank/32) / 2`. 0.68 or more = Offense edge; 0.56 or more = Lean offense; 0.32 or less = Defense edge; 0.44 or less = Lean defense; otherwise Even.
- **Generated takeaways:** highest edge score among the position and passing metrics = "Biggest edge"; lowest = "Watch out". Fill a sentence template with both values and ranks.

## Gotchas learned the hard way

- **Week numbering:** a team's bye leaves a gap in its games. Do not assume the latest week in the play-by-play is complete; the Thursday game shows up before Sunday's games. Use the schedule for the current week.
- **League averages for per-game stats** must be divided by 2 (each game has two teams).
- **Roster file has rows with empty `gsis_id`.** Drop them before building the ID to position map, or empty receiver IDs get a position.
- **Rams are `LA`** in nflverse, Chargers are `LAC`.
- **`spread_line` is positive when the home team is favored.** Display it as `HOME -X`.
- `github.com/nflverse/nfldata/raw/...` may be blocked in some environments; the `raw.githubusercontent.com` URL works.
- The play explorer has a known small bug: the "Showing the best play by EPA" note logic does not always reflect auto vs clicked selection. Low priority.

## Suggested build order

1. **Plugin scaffold:** main file, shortcode, query-arg router, scoped CSS (prefix everything with `.npe` so the theme can't break it), assets only enqueued when the shortcode is on the page.
2. **Hub view in PHP** from `teams.json`, `metrics.json`, `schedule.json`.
3. **Team view:** port the explorer JS from `mockup/team.html` into `assets/team-explorer.js`, loading `data/plays/{TEAM}.json` with `fetch`. Make team-specific labels dynamic (currently hard-coded "MIN" and "Minnesota").
4. **Matchup view in PHP** with generated takeaways and the offense/defense flip.
5. **Data automation:** a GitHub Action (or cron on the server) that runs `build_data.py` nightly from September to February and deploys `/data` to the plugin folder. The week can be derived from the schedule (first week with unplayed games).
6. **Polish:** Open Graph image per matchup for sharing, mobile check, caching headers on JSON, a name and domain.

## Out of scope for now

User accounts, paid tiers, betting picks or sportsbook affiliate links, college football, player pages, team logos.

## Style notes

- Fonts: Barlow Condensed (display, uppercase headings) and IBM Plex Sans (body). Tabular numbers for all stats.
- Semantic colors: green = good / top 10 rank, red = bad / bottom 10. Team colors are used only as accents (chip bars, tile edges).
- Copy is casual and direct. Avoid em dashes and en dashes.
