# Down by Down (downbydown.com): Revised Scope (v3.4)

Date: 2026-10-30, updated same day with the name, the v2 mock and the v3 design pass. Owner: Gene Crawford, Period Three.
Working title was "Play Explorer". Site name is now **Down by Down**, domain **downbydown.com** (purchased 2026-10-30).
Status: **Built 2026-10-30.** Plugin 1.0.0 is in `plugin/down-by-down/` and packaged as `down-by-down-1.0.0.zip`; repo `github.com/genecrawford/downbydown` is public with the nightly Action running and real data committed under `data/`. **Live on downbydown.com** (installed 2026-10-30; Divi 5, plain permalinks, Cloudflare). The design reference is `styleguide/index.html`, which renders with the plugin's own stylesheet; mock v5 in `mockup-v5/` is history.

This document supersedes the architecture parts of `BRIEF.md`. The brief's page descriptions, metric definitions and gotchas still stand and are not repeated here.

---

## 1. What changed since the brief

| Topic | Brief said | Now decided |
|---|---|---|
| How nightly data reaches WordPress | Open ("deploy /data to the plugin folder") | **WordPress pulls from GitHub.** A GitHub Action builds the JSON and commits it to the repo. The plugin's WP-Cron job downloads it nightly. |
| Where data lives on the site | Inside the plugin folder | `wp-content/uploads/down-by-down/` so plugin updates never wipe data |
| Where the build script runs | Unspecified | Only in GitHub Actions. Gene's Mac has no pandas and will not get one. |
| Repo | Unspecified | One public GitHub repo, `genecrawford/downbydown`, holding plugin, tools and data. Public because the site pulls from `raw.githubusercontent.com` without a token. Nothing secret goes in it. |

---

## 2. System overview

```
nflverse (CSV, nightly)
      |
      v
GitHub Action (cron, Sep to Feb, ~5am ET daily)
  python3 tools/build_data.py  ->  data/*.json + data/manifest.json
  git commit + push to main
      |
      v  (HTTPS, public raw URLs)
WordPress plugin WP-Cron job (nightly ~6am site time)
  reads manifest.json, downloads only changed files
  writes to wp-content/uploads/down-by-down/
      |
      v
[down_by_down] shortcode
  hub + matchup: PHP reads JSON, renders HTML (SEO)
  team: JS fetches /uploads/down-by-down/plays/{TEAM}.json
```

The site never needs Python, a database table, or host credentials in GitHub. If GitHub is unreachable one night, the site keeps serving the last good data.

---

## 3. Data contract

### 3.1 Files the Action produces (unchanged from the brief, plus a manifest)

| File | Produced by | Read by |
|---|---|---|
| `data/manifest.json` | new, written at the end of the build | plugin cron |
| `data/teams.json` | build_data.py | PHP, all views |
| `data/metrics.json` | build_data.py | PHP hub + matchup |
| `data/schedule.json` | build_data.py | PHP hub |
| `data/plays/{ABBR}.json` x 32 | build_data.py | JS team explorer |

`manifest.json`:

```json
{
  "season": 2026,
  "week": 9,
  "through_week": 8,
  "built_at": "2026-10-31T09:12:40Z",
  "files": { "teams.json": "sha1...", "metrics.json": "sha1...", "schedule.json": "sha1...", "plays/MIN.json": "sha1...", ... }
}
```

The cron compares hashes and downloads only what changed. On a normal night that is `metrics.json`, `schedule.json`, the manifest, and the plays files for teams that played.

### 3.2 Changes to build_data.py (small, scoped)

- Derive `week` automatically: first week in the schedule that still has an unplayed game. The CLI arg becomes optional.
- Write `manifest.json`.
- Add to `schedule.json` games: `status` (`upcoming`, `final`). No live in-game state; the data is nightly.
- `schedule.json` carries every week of the season, not just the current one, with `current_week` at the top (needed by the Matchups view's week switcher). Still one small file.
- Add to `metrics.json`: `league.passrate` already exists; the team page tile hint "league avg is about 57%" will read it instead of being hard-coded.
- Add `season` to every file.
- Postseason: schedule weeks 19 to 22 are included as "Wild Card", "Divisional", "Conference", "Super Bowl". Play-by-play for those games flows through the same code with no changes.

### 3.3 Season boundaries

| When | Hub shows | Team page shows |
|---|---|---|
| Preseason to Week 1 kickoff | Week 1 schedule with lines; team list with records 0-0 and no ranks | "No plays yet. The season starts Sep X." |
| In season | Current week (rolls over Tuesday morning) | All games to date |
| After Super Bowl until next September | Final week; header says "2026 season, final" | Full season |
| Action off-season | Cron does nothing; nothing changes | unchanged |

---

## 4. WordPress plugin

### 4.1 Identity

- Plugin name: Down by Down. Folder and slug: `down-by-down`. Text domain the same.
- PHP prefix `dbd_`, CSS prefix `.dbd-` (root wrapper `.dbd`), JS global `DBD`.
- Shortcodes: `[down_by_down]` (the app), `[dbd_count games|teams]` (optional numbers for the Divi menu). The brief's `[play_explorer]` is kept as an alias for the app.
- Data folder: `wp-content/uploads/down-by-down/`.
- Requires PHP 8.0, WordPress 6.4. No build step, no Composer, no npm. Plain PHP, CSS and one JS file, same as Gene's other plugins.

### 4.2 Files

```
down-by-down/
  down-by-down.php             header, constants, autoload-by-require, hooks
  includes/
    class-dbd-data.php         reads JSON from uploads, caches in memory per request
    class-dbd-sync.php         WP-Cron job, manifest diff, downloads, admin "Sync now"
    class-dbd-router.php       shortcode, query-arg routing, title/meta/canonical
    class-dbd-metrics.php      edge score, pills, rank colors, number formatting
    class-dbd-takeaways.php    generated matchup takeaways (rules in section 6)
    views/hub.php
    views/team.php             shell only; JS fills it
    views/matchup.php
    admin/settings.php         one settings page under Settings
  assets/
    dbd.css                    all three views, scoped under .dbd
    team-explorer.js           port of mockup/team.html script, team-agnostic
  uninstall.php                removes options and the uploads folder
```

### 4.2a How the page is framed (decided 2026-10-30, revised same day: Divi owns the frame, light only)

- **The left sidebar is built in Divi by Gene**, the normal way: a Theme Builder layout with a full-width section and a two-column row; the left column holds an Image module (color logo), a Menu module on a WordPress menu, and a Text module for the season note. The plugin never renders or styles the sidebar.
- **`[down_by_down]`** renders the right side only: the top bar (breadcrumb and "Updated" stamp) and the view the URL asks for. No sidebar, no theme toggle.
- **Light theme only.** The chalk-paper palette from the v5 light mode is the one theme. The Light/Dark toggle and the `data-theme` machinery are out. The dark mock stays as a reference for density and hierarchy, not color.
- **What the plugin gives the Divi sidebar:** a body class per view (`dbd-view-hub`, `dbd-view-team`, `dbd-view-game`, `dbd-view-matchups`, `dbd-view-teams`) so a current-item style is one CSS rule, and two optional shortcodes, `[dbd_count games]` and `[dbd_count teams]`, for the small numbers next to menu items.
- **Divi sidebar spec** (so it matches the app): column width 232px, sticky; background `#f4f1ea`; right border 1px `rgba(22,30,54,.12)`; group labels Barlow Condensed 600 10px, uppercase, letter-spacing .18em, color `#6b6f78`; items IBM Plex Sans 500 14px, color `#3b4255`, current item `#121417` on `#f6f3ec` with a 6px red dot; foot note Plex 11px `#6b6f78` with a Barlow Condensed label. These are the v5 light tokens; the plugin's stylesheet publishes them as CSS variables too.
- **Cost accepted:** Divi's own CSS and JS load on the app page. The plugin's stylesheet opens with a reset block under `.dbd` so Divi's global rules do not reach into the app.
- The plugin still registers a standalone full-page template (no theme header, no sidebar) as an option for non-Divi installs; not used on downbydown.com.

### 4.3 Routing

One page carries `[down_by_down]`. On downbydown.com that page is the home page, so the URLs below are from the root. Query args pick the view:

| URL | View |
|---|---|
| `/` | Hub |
| `/?team=MIN` | Team explorer |
| `/?game=2026_09_MIN_DET` | Matchup, away offense vs home defense by default |
| `/?game=2026_09_MIN_DET&side=home` | Matchup flipped: home offense vs away defense |
| `/?view=matchups` | All weeks' slates with a week switcher (added 2026-10-30; in the mock the sidebar's Matchups link points at one game as a placeholder) |
| `/?view=matchups&week=5` | A past or future week's slate; played games link to recaps |
| `/?view=teams` | Team picker: the 32-team standings table on its own page; remembers the last team viewed (added 2026-10-30; the mock's Play explorer link points at the Vikings as a placeholder) |

Unknown team or game: 404-style notice inside the page plus a link back to the hub, HTTP 200 (WordPress already resolved the page). Pretty URLs (`/team/min/`, `/game/2026_09_MIN_DET/`) are a later polish item, not phase 1.

Each view sets: document title, meta description, canonical URL, Open Graph title and description. OG images come later (brief, polish step 6).

### 4.4 Assets

- CSS and JS enqueue only when the shortcode is on the page.
- Fonts: Tourney 800 (page titles), Barlow Condensed 500/600/700 (section heads, labels, numbers), IBM Plex Sans 400/500/600 (body), IBM Plex Mono 400/500 (play text, team view only). One Google Fonts link, loaded only on the app page. If Gene prefers no third-party fonts for privacy, swap to self-hosted woff2 inside `assets/fonts/` at build time; no scope change either way.
- Team JSON is requested with `?v={hash}` from the manifest so browsers cache it until the data changes.

### 4.5 Settings page (Settings > Down by Down)

- Data source URL (default: the raw GitHub URL of `data/` on `main`).
- Last sync: time, result, files changed.
- "Sync now" button.
- Current data: season, week, through_week, built_at.
- Sync token (for the GitHub Action's push call) and the endpoint URL to paste into the Action.
- Nothing else.

### 4.6 Sync job

- Scheduled daily via WP-Cron on activation; runs on first activation immediately.
- Downloads the manifest, diffs hashes against the local manifest, downloads changed files to a temp name, validates JSON, then renames into place. A bad download never replaces a good file.
- Logs the last 20 runs in an option for the settings page.
- If WP-Cron is disabled on the host (common on managed hosts), the settings page says so and shows the `wp cron event run dbd_sync` command or the real-cron URL.

### 4.7 What the theme sees

The shortcode returns one `<div class="dbd">`. All styles are nested under `.dbd` with explicit font, color and box-sizing resets so Divi or any theme cannot leak into it. The mock's `wpbar` (still branded "Play Explorer") and `mockbar` are mock-only and are not part of the plugin; the site's own header and footer wrap the app.

---

## 5. Views: what the mock shows, and what becomes dynamic

### 5.1 Hub (`mockup/index.html`)

Approved as drawn. Dynamic in production:

- Week header from `schedule.json`. Postseason weeks use round names.
- Game card states: `Final` with scores and "Recap" label; upcoming with kickoff in the viewer's local time (JS converts; PHP renders ET as the fallback text) and "View matchup".
- Spread shown as `HOME -X`; a pick'em shows `PK`. Missing line shows nothing.
- Team records from `plays/{ABBR}.json` games, ranks from `metrics.json` (`rank.off[T].epa`, `rank.def[T].epa`). Green top 10, red bottom 10.
- Before Week 1, the teams section shows names only.

### 5.2 Team explorer (`mockup/team.html`)

Approved as drawn. Everything that says "MIN", "Minnesota" or "Vikings" becomes a value from `teams.json`:

- Title, intro sentence (season, weeks played, bye week), drive chart end-zone label ("MIN GOAL" becomes `{ABBR} GOAL`), yard-line text (`MIN 35` becomes `{ABBR} 35`), footer date from `built_at`.
- Tile hint "league avg is about 57%" reads `league.passrate`.
- Known bug from the brief (auto vs clicked play note) is fixed in the port.
- The explorer is one JS file taking `{team, dataUrl}` from a localized config. It builds its own DOM inside the shell, so the PHP view is a few lines.

### 5.3 Matchup (`mockup/matchup.html`)

Approved as drawn, with one addition and one change:

- Addition: a flip link under the title, "See Lions offense vs Vikings defense", pointing at `&side=home` (or back). The mock shows only one side.
- Change: the four takeaway cards are generated (section 6), so their copy will be plainer than the hand-written mock. The mock copy is the quality bar for tone, not a template.
- Zone heatmap intensity: cell color mixes the hot or cold color at `min(100, |epa| / 0.8 * 100)` percent. Cells with fewer than 5 throws are shown gray with the count, no EPA.

---

## 6. Generated takeaways (rules)

Inputs for a matchup: offense team O, defense team D, `metrics.json`.

**Candidate metrics** and their sentence templates. `{ov}` offense value, `{or}` offense rank, `{dv}` defense allowed value, `{dr}` defense rank, `{lg}` league average. Ranks print as ordinals ("3rd", "28th").

| Key | Group | Template |
|---|---|---|
| epa | overall | "{O} averages {ov} EPA per play ({or}). {D} allows {dv} ({dr}). League average is {lg}." |
| sr | overall | "{O} succeeds on {ov} of plays ({or}) against a defense that allows {dv} ({dr})." |
| expl | overall | "{O} creates explosive plays on {ov} of snaps ({or}). {D} gives them up at {dv} ({dr})." |
| pass_epa | passing | "{O} gains {ov} EPA per dropback ({or}). {D} allows {dv} ({dr})." |
| sack | passing | "{O} takes a sack on {ov} of dropbacks ({or}). {D} gets home on {dv} ({dr})." |
| run_epa | running | "{O}'s run game is {or} in EPA per carry ({ov}). {D} allows {dv} ({dr})." |
| third | money | "{O} converts {ov} of third downs ({or}). {D} allows {dv} ({dr})." |
| rz_td | money | "{O} scores a touchdown on {ov} of red zone trips ({or}). {D} allows {dv} ({dr})." |
| WR_yds_pg, TE_yds_pg, RB_yds_pg | position | "{D} gives up {dv} yards a game to {pos}s ({dr}), against a league average of {lg}. {O}'s {pos}s produce {ov} ({or})." |

**Scoring.** For each candidate, `score = ((33 - or)/32 + dr/32) / 2`, the same formula as the edge pill. No inversion is needed: `build_data.py` already ranks every metric so that 1 is best for that side (fewest sacks taken for offense, most sacks made for defense).

**Card selection**

1. **Biggest edge**: highest score among passing, running, money and position groups. Heading from a per-metric heading table ("Tight ends should eat", "Deep shots are there", "Third downs favor {O}", and so on).
2. **Watch out**: lowest score among the same set. Same heading table, "watch out" variants.
3. **Opportunity**: the pass zone where D's EPA allowed is highest with at least 10 throws. Sentence: "{D} is worst against {zone} throws, allowing {dv} EPA per attempt. {O} sends {share} of throws there." If O's share in that zone is under 10%, add "That is a small part of their offense so far."
4. **Wash**: the overall-group metric whose score is closest to 0.50 (skipping any metric already used). Sentence from its template with the lead "Call this one even."

**Guards**

- Fewer than 3 games for either team: the cards show "Early season: small samples" as a subtitle and ranks still print.
- Before Week 1: no cards; the page shows the schedule line and "Numbers appear after Week 1."
- Never two cards on the same metric. If a tie, prefer the metric with the larger gap between offense and defense rank.

The brief's threshold table for the pill (0.68 edge, 0.56 lean, 0.44 lean other way, 0.32 edge other way) is unchanged.

---

## 7. Mock v2 (drawn 2026-10-30, in `mockup-v2/`)

Open `mockup-v2/index.html` in a browser. The approved v1 mock in `mockup/` is untouched. v2 re-skins everything to the neutral rule, brands it Down by Down, and fills the six gaps:

| Gap | Where to see it | Status |
|---|---|---|
| 1. Matchup flipped side + flip link | `matchup.html?game=2026_08_MIN_DET&side=home` | Drawn. Lions-offense numbers are placeholders (banner says so) |
| 2. Neutral re-skin + second team | `team.html?team=MIN`, then the "Switch team" picker in the mock bar | Drawn. Any team works; teams without a play file show Vikings plays under their labels, with a banner |
| 3. Hub mid-week with byes | `index.html` | Drawn. Thursday final, Sunday and Monday upcoming, four bye teams tagged |
| 4. Empty and early states | `team.html?team=MIN&state=pre`, `matchup.html?...&state=early`, `index.html?state=pre` | Drawn |
| 5. Phone width | Any page at 375px | Checked. No horizontal page scroll. The drive chart scrolls sideways inside its card (560px minimum); a stacked phone layout for it is a later polish item |
| 6. About | Bottom of `index.html` | Decided: a short About section on the hub itself, rendered by the plugin. No separate page |

What v2 also proves beyond the gaps:

- **Generated takeaways work.** `matchup.html` builds the four cards from the numbers with the section 6 rules, not from hand-written copy. On the real Vikings vs Lions numbers it lands on the same four stories the hand-written v1 told (tight ends, deep middle, sack rate, EPA wash).
- **Contrast guard** runs in JS on both pages and is simple enough to port to PHP as is.
- **Kickoff times** convert to the viewer's local time in the browser; the mock stores Eastern times, production should store UTC in `schedule.json`.
- **Dark mode removed.** v1 had a dark theme; v2 is light only, per the house rule.
- The known v1 bug (auto vs clicked play note) is fixed in the v2 explorer.

Data shortcuts in the mock that production replaces: `data/teams.js` carries records and ranks lifted from the v1 hub; `data/MIN.js` is the v1 play set; placeholder numbers for any matchup other than Vikings offense vs Lions defense; the pre-season Week 1 schedule is invented.

### Mock v3: design push (drawn 2026-10-30, in `mockup-v3/`)

Gene asked to push the visual design. Direction chosen: broadcast board with editorial restraint. v3 is v2's pages and behavior with a new stylesheet and three markup changes. v2 stays for comparison.

- **Field green becomes the site's second color.** It was already the color of every chart. The site bar and footer are deep green, and the hub hero and matchup header sit in a full-width green band with faint yard lines and a hash-mark rule near the bottom. Active UI states (pressed filters, tab underline, links, selected game) now use this green instead of the ink. Team color still appears only where section 7 allows.
- **Type scale pushed.** Hero headline up to 92px, matchup title up to 84px, section heads 32px with a green bar, tile numbers 40px, game-card scores 30px, abbreviations 24px. A small green uppercase kicker sits above every page title (season, week, record, venue).
- **Scoreboard details.** Hero stat strip (games this week, finals, byes, snaps charted). Final game cards get a green FINAL tag and a chalk-paper tint. Day groups get a ruled label. Filters sit in their own card. Play description gets a green rule. Tooltip is dark.
- **Motion.** Cards and containers rise in over 0.45 seconds on first paint only, staggered slightly. Tiles and charts that re-render on filter changes do not re-animate. Respects reduced-motion.
- **Matchup title** on phones stacks offense and defense on separate lines with their team-color underlines.
- The "snaps charted" hero number is invented in the mock; production computes it from the play files.
- **Logo (2026-10-30):** `logo/dxd-logo.svg`, a flaming football with a black stencil DOWNxDOWN wordmark and red x (navy #161e36, red #cb2026, black #231f20). It sits in a white site bar at 40px tall (32px on phones) in its real colors; the black wordmark does not read on the green band, so the bar is no longer green. The green band below the bar is unchanged. A knockout (white) version for the dark footer is a nice-to-have.
- **Headline face trials (Gene's suggestions, 2026-10-30), page titles only** (hub hero, matchup title, team title); Barlow Condensed stays for section heads, labels, tile numbers and scores, IBM Plex Sans body. First Archivo Black (wide, sizes dropped to 72/60/52px, tight tracking). Then Tourney at weight 900 (narrower, angular; sizes 86/72/62px, slight positive tracking). **Decided (Gene, 2026-10-30): Tourney, weight 800, uppercase, page titles only.** Never on section heads, numbers or body. Three Google Fonts families total; candidates for self-hosting at Build.

Rule change from this pass: the site accent for active states is field green, not the ink. Section 7's table of where team color may appear is unchanged.

### Mock v5: box score meets nfelo (hub only, 2026-10-30)

Gene liked the v4 direction and asked for an even mix with nfeloapp.com/live. What nfelo brings: a dark product shell with a left sidebar nav and grouped tiny-caps labels, a top bar with breadcrumb and "last updated", bordered panels with small radii and a label row, a horizontal scoreboard strip with Final/Upcoming badges and a highlighted selected card, monospace tabular numbers, dense rows, one accent color. What v4 keeps: the brand palette (navy becomes the dark base instead of neutral black, red is the single accent, chalk for the light theme), Tourney page title with the red "field.", Barlow Condensed labels, team color bars instead of logos, the slate as a ruled table, standings with rank bars, Game of the Week module, ghost yard numerals.

- **Dark by default, light available** in the mock. Superseded 2026-10-30: production is light only (see 4.2a).
- **Logo:** a white knockout version (`assets/dxd-logo-knockout.svg`: wordmark white, ball chalk, flame red) for the dark sidebar; the original on light.
- **Sidebar** becomes a top strip on phones. Nav: This week (Slate, Teams with counts), Explore (Matchups, Play explorer, About).
- Panel radius 6px (between v4's square corners and nfelo's 8 to 10px). Borders are hairline white at 9% on dark.
- In production the sidebar is part of the plugin's page shell, so the theme's own header sits above it or is hidden on the app page.

**v5 approved by Gene ("This is it") and carried to all three pages, 2026-10-30.**

- **Matchup (v5):** title with team-color underlines and an offense/defense segmented switch; "The game" panel; takeaways as a numbered 01 to 04 strip with vertical rules inside one panel; head to head with a diverging bar per row (center is even, fill grows toward the side the edge favors, in that team's accent color, length is the edge score's distance from 0.50); zone grids in a side panel. Mock placeholder and early-season banners are panels with a red rule.
- **Team explorer (v5):** title with the team color underlining the nickname and a snaps badge; the game picker is a scrolling strip of cards; filters sit in a toolbar panel as red-pill segmented controls; stat tiles with Tourney numbers; the chart panel has pill tabs in its header; a right rail with Play detail (badge says Best by EPA or Clicked) and Who gets the ball.
- **Mock controls** (team switcher, pre-season, early-season) live in the top bar in small mono text.

**Interactions from transitions.dev (added 2026-10-30, Gene asked).** Patterns pasted from the transitions-dev library into `mockup-v5/transitions.css`, themed through our tokens:

| Pattern | Where |
|---|---|
| Tabs sliding (16) | explorer chart tabs; every filter segmented control (the red pill slides between Down, Play type, Field zone, Formation options; Reset slides them home) |
| Number pop-in (02) | stat tile values re-enter digit by digit when a filter changes a value; unchanged tiles stay still |
| Text states swap (04) | Play detail badge (Best by EPA / Clicked) and the Light/Dark toggle label |
| Texts reveal (18) | kicker, title and subtitle on all three pages; the four takeaway cards stagger in on the matchup |
| Avatar group hover (11) | hub scoreboard strip only, softened (2px lift, 1.015 scale, gentle return). Removed from the explorer game picker 2026-10-30: Gene found it too much on a row of toggles |
| Panel reveal (07) | switching explorer tabs slides the new view in with a cross-blur |
| Tooltip (17) | the play tooltip uses its delayed fade-and-scale in and instant out (timing tokens only; position stays JS-driven) |

All patterns keep their reduced-motion guards. A settle class makes everything visible after 1.4 seconds in case a throttled tab never runs the transitions. Not used: modal, dropdown, success check, shake, skeleton, shimmer. Skeleton reveal is the natural fit for the team page's data fetch in production.

**Index views mocked (2026-10-30):** `mockup-v5/matchups.html` (every week on a sliding week switcher, current week dotted red, the slate panel re-renders in place, URL carries `?week=`; weeks other than 8 are generated placeholders around the real Vikings results and say so) and `mockup-v5/teams.html` (Pick a team: a "pick up where you left off" box for the last team viewed, then the two conference standings panels with whole rows clickable). Sidebar Matchups and Play explorer now point at these. `data/schedule.js` carries weeks 1 to 9 with `current_week`.

**Team marks (2026-10-30, Gene wants stronger team identity without league logos).** `mockup-v5/marks.html` is a design sheet of four mark systems generated from each team's two colors plus its abbreviation in Tourney: Jersey tile (square, sleeve stripe), Pennant (flag with hoist stripe), Chevron plate, Monogram ring. All 32 per system, each at 16/24/32/48px, and an in-context block (strip card, standings rows, matchup title, game picker, team title). Text color inside a mark: the second color if it reads 3:1 on the first, else white, else near-black. Rationale: four teams share the same navy and three are black, so marks must carry the abbreviation. The second colors in the mock are hand-entered approximations; production uses nflverse `team_color2`. **Decided: Jersey tile (Gene, 2026-10-30).** Applied across v5: strip cards (24px), slate rows (28px), standings rows (24px), Game of the Week and The game boxes (34px), explorer title (52px) and game picker (22px), matchup title pair (44px) and head-to-head column heads (18px), team picker's last-viewed box (40px). The mark replaces the team-color left bar wherever it sits; the bar remains nowhere a mark is present. **Pennant as the hero mark (Gene, 2026-10-30):** the explorer title (48px tall), the matchup title pair (40px), and the team picker's last-viewed box (40px) use the pennant instead of the tile; everywhere else stays tile. Implementation: one generator, `data/marks.js` in the mock, inline SVG with no image files; production has the same function in PHP for server views and in JS for the explorer. Section 7's team-color table gains a row: the jersey tile is now the primary carrier of team identity, and tile tops and the drive chart end zone keep the color accent.

### Mock v4: the box score (hub only so far, 2026-10-30)

Gene's verdict on v3: it still reads as a first-prompt template (rounded cards in even grids, kicker-stripe-headline-lede hero, pills, soft shadows, a palette the brand did not give us). v4 is a redesign of the hub against a concept, built as the test before the other pages follow.

**Concept: the box score.**

- **Brand palette.** Chalk paper background, navy (#161e36) for dark surfaces, red (#cb2026) only for emphasis and labels, black ink. These come from the logo. Field green appears only inside charts.
- **Flat and ruled.** No cards, no shadows, square corners. Hairline rules and whitespace carry the structure. Dense where the data is, airy where the words are.
- **Football idioms.** A navy "bottom line" ticker of the week's scores under the hero. The slate is a table of game rows, not tiles. Teams are two conference standings tables with rank bars (green top 10, red bottom 10, navy middle). Tourney numerals for scores.
- **Asymmetry.** Split hero: the statement on the left in Tourney with "field." in red, a navy Game of the Week module on the right with both teams, ranks, line, one generated sentence and a CTA.
- **One decoration only.** Ghost yard numerals at very large size behind the hero, 4.5% opacity.
- Logo at 46px in a white bar with a 4px navy rule. Nav: Slate, Teams, About.

Game of the Week in production: the matchup with the highest "biggest edge" score that week, with that card's sentence as the blurb. Section heads carry a red sub-label (game count, rank note).

If Gene approves the direction, the matchup and team pages get the same treatment: matchup head-to-head as diverging bars around the league average instead of edge pills, takeaways as a numbered strip with rules instead of boxes, the team page's drive chart as the first thing under the title with filters as a toolbar.

### Decided: neutral palette, team color as accent (Gene, 2026-10-30)

The brief's rule stands. One look across all 32 teams. The Vikings mock is re-skinned to this rule before any second-team mock is drawn.

**Site palette (all views).** The mock's neutral base stays: light background, white cards, dark ink, muted gray text, hairline borders. Active UI states (pressed filter buttons, the selected tab underline, links, the selected game card border) use a single site accent, not the team color. Site accent: field green as of the v3 pass (was the dark ink in v2), so the UI never fights a team color. Semantic colors stay as they are: green good, red bad, plus the field green and the play-type colors on the charts.

**Where team color appears, and only there**

| Element | Color |
|---|---|
| Header stripe | team c1 (60%) + c2 (40%), as the mock draws it today |
| Summary tile top border | team c1 |
| Hub game card chip bar and team list left bar | team c1, as the mock draws it today |
| Drive chart: the team's own end zone tint | team c1 at 35% over the field |
| Matchup header: offense team name and defense team name | each team's c1, as the mock does with purple and blue |
| Matchup head-to-head column headers | each team's c1 |

**Never in team color:** body text, buttons, tab states, links, pills, rank badges, chart marks for plays. The explorer's purple pass color becomes a fixed site color (same for every team) so a pass is always the same color on every team page.

**Contrast guard.** If a team's c1 fails 3:1 against the card surface (black, very dark navy, white, light gray secondaries), use c2 for the tile tops and chart tint; if both fail, fall back to the site ink. Computed once in PHP from `teams.json`, passed to the JS as two CSS variables, `--dbd-team` and `--dbd-team-2`. No per-team override table to maintain.

---

## 8. Phase plan for when Gene says Build

One pass, in this order, delivered as a versioned zip plus the repo.

1. Repo setup (narrated, step by step, Gene says go first): `git init` in the project folder, public repo `genecrawford/downbydown`, `.gitignore`, folder layout, first push.
2. GitHub Action: nightly workflow, first manual run to produce `data/` so the plugin has something to show.
3. Plugin scaffold, router, settings page, sync job.
4. Hub view.
5. Team explorer port.
6. Matchup view and takeaways.
7. Static PHP checks, browser check of the explorer against real data, zip `down-by-down-1.0.0.zip`.

Out of this pass: pretty URLs, OG images, ptit or Kernl auto-update wiring.

---

## 9. Still out of scope

Unchanged from the brief: accounts, paid tiers, betting picks or affiliate links, college football, player pages, team logos. Added: live in-game data (nightly only), historical seasons (2026 only; a season switcher is a possible later feature), defense play explorer (the team page is offense only).

---

## 10. Hosting checklist for the site that will run it

- PHP 8.0 or newer, WordPress 6.4 or newer.
- Outbound HTTPS to `raw.githubusercontent.com` allowed.
- `wp-content/uploads` writable (standard).
- WP-Cron running, or a real cron hitting `wp-cron.php` daily.
- Roughly 3 MB of disk for data.
- downbydown.com pointed at the site; the app page is the home page.
- Any host. Nothing here is Kinsta- or WP Engine-specific.

---

## 11. Name and domain (added 2026-10-30)

`nflplayexplorer.com` was available but rejected: "NFL" in the site name reads as affiliation, which the no-logos decision was meant to avoid, and a descriptive name is weak branding.

.com availability checked against the Verisign whois server on 2026-10-30 (90 candidates). Shortlist, all unregistered at check time:

| Domain | Why it works |
|---|---|
| downbydown.com | Football-native phrase, says exactly how granular the site is, short, easy to say |
| drivebydrive.com | Same rhythm; the drive chart is the hero view |
| drawnplays.com / playsdrawn.com | Literal version of the tagline "every snap, drawn on the field" |
| chalkplays.com | Chalkboard-diagram feel, short. Note: "chalk" is also betting slang for the favorite |
| eachsnap.com | Shortest option; "every snap" is taken |
| playdrawn.com, drawntheplay.com, sketchtheplay.com | Backups in the "drawn" family |
| chartthedrive.com, chalkthedrive.com, thedrivechart.com | Backups in the "drive" family |

Also open: playmapped.com, drivemapped.com, fieldcharted.com, yardsdrawn.com, snapcharted.com, offthechalkboard.com, chalkedplaybook.com, playsonfield.com, playbyplaymap.com.

**Decided: downbydown.com, purchased by Gene on 2026-10-30.** Site name "Down by Down". Plugin slug, prefixes, shortcode and repo name updated throughout this document to match. Tagline stays "every snap, drawn on the field". The Desktop project folder is `~/Desktop/downbydown_com/` (renamed by Gene 2026-10-30).

---

## 12. Backlog (post-launch)

- **Top-level admin menu** (Gene, 2026-10-30): move Settings › Down by Down to its own entry in the WordPress admin sidebar, with the pennant or tile as its icon. Queued for the next plugin build.
- Skeleton reveal (transitions.dev 14) while a team's play file loads.
- Pretty URLs (`/team/min/`, `/game/…/`), Open Graph images per matchup.
- Knockout logo and a dark footer are no longer needed (light only).

---

## 13. Tendencies charts (mocked 2026-10-30, `mockup-v5/charts.html`)

Gene asked what the data could do with charts (Chartist) and offense tendency mapping. The mock is a proposed fourth explorer tab, drawn from the real Vikings play file with Chartist 1.3 (SVG, CSS-styled, matches the site). Five views:

1. **Pass rate by down and distance.** Horizontal bars per bucket with the league's overall pass rate as a dashed reference and the play count on each bar. Readout names the most pass-happy and most run-heavy buckets among those with 10+ plays.
2. **Shotgun vs under center by down.** Paired bars of pass rate per formation.
3. **Cumulative EPA across the season.** One stepped area line over every snap, vertical rules at game starts, readout with best and worst game.
4. **Who gets the ball, and how deep.** Scatter of targets (x) against average depth of target (y) per receiver, dot size by targets, colored by position. Needs receiver position in the play file; the mock hard-codes a map.
5. **Pass rate by quarter and game state.** A heat grid, blue above league pass rate, amber below, small samples grayed.

Production notes: Chartist bundled locally in the plugin (no CDN); charts computed client side from the play file the explorer already loads; the per-bucket league pass rates and receiver positions should come from the nightly build (`league_tendencies` in metrics.json, `pos` column in plays). Matchup overlays (offense tendency vs what the defense allows) need defense tendencies in the build and are a second step.
