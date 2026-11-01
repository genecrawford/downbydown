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

**Status: built into the plugin as 1.1.0 (2026-10-30), and as of 1.1.1 the explorer has no tabs at all: Drive chart, Pass & run map, Situations and Tendencies stack on the page with a sticky detail rail (Gene: the tabs were hiding the data).** Chartist 1.3 is bundled in `assets/vendor/` (no CDN). Every chart follows the explorer filters and redraws on every filter change (there is nothing hidden to defer for). Build-side additions: each play row carries `pos` (ball handler's roster position, from nflverse rosters) and `metrics.json` carries `league.tend` (pass rate per down-and-distance bucket, per down for shotgun and under center, per quarter for leading, tied and trailing). Chart 1 shows a red tick per bar at the league rate for that exact spot instead of one dashed line; chart 5 tints each cell against the league rate for that same quarter and state. Until the next nightly build the charts fall back to the overall league pass rate and infer backs from carries.

Gene asked what the data could do with charts (Chartist) and offense tendency mapping. The mock is a proposed fourth explorer tab, drawn from the real Vikings play file with Chartist 1.3 (SVG, CSS-styled, matches the site). Five views:

1. **Pass rate by down and distance.** Horizontal bars per bucket with the league's overall pass rate as a dashed reference and the play count on each bar. Readout names the most pass-happy and most run-heavy buckets among those with 10+ plays.
2. **Shotgun vs under center by down.** Paired bars of pass rate per formation.
3. **Cumulative EPA across the season.** One stepped area line over every snap, vertical rules at game starts, readout with best and worst game.
4. **Who gets the ball, and how deep.** Scatter of targets (x) against average depth of target (y) per receiver, dot size by targets, colored by position. Needs receiver position in the play file; the mock hard-codes a map.
5. **Pass rate by quarter and game state.** A heat grid, blue above league pass rate, amber below, small samples grayed.

Production notes: Chartist bundled locally in the plugin (no CDN); charts computed client side from the play file the explorer already loads; the per-bucket league pass rates and receiver positions should come from the nightly build (`league_tendencies` in metrics.json, `pos` column in plays). Matchup overlays (offense tendency vs what the defense allows) need defense tendencies in the build and are a second step.

## 14. Player layer (scoped and mocked 2026-10-30, `mockup-v5/players.html` + `player.html`)

**Status: built as 1.2.0 (2026-10-30), PR against main.** Everything in this section except the matchup "players to watch" strip, which stays queued as 1.2.x. FTN flags shipped in the build already (they were cheap), so the Intel rail shows play action, contested catches and blitz lines as soon as the first build with the FTN file runs.

Gene: "I'd like to move towards getting to individual player level stats and intel too." Players first, nfelo model layer after it as its own version. The mock runs on the real Vikings roster, weekly stats, snap counts, injury report, depth chart and play file through Week 8.

### What it is

- **Players index** at `?view=players` (optionally `&team=MIN`, `&pos=WR`, `&q=jeff`). Team picker (the generated tiles), position segmented control, search. One row per skill player who has taken an offensive snap: jersey tile with number, name, depth-chart slot, position, snap share bar, targets, receptions, carries, yards, TD, EPA, latest injury status. Click a row to open the player.
- **Player page** at `?player=<gsis_id>`. Hero: jersey tile (number in team colors, no headshots, same rule as logos), name with the team underline, kicker with position, number and season count, one line of bio (age, height, weight, college, draft slot), depth-chart slot, snap share, and the injury pill. Pennant link back to the team's offense.
- **Tiles** that change with position group. Receivers: targets, receptions, yards, TD, target share, aDOT, EPA per target, YAC per catch. Backs: carries, rush yards, rush TD, targets, touches per game, EPA per touch, explosive plays, snap share. Quarterbacks: attempts, completion with CPOE, yards, TD/INT, EPA per dropback, sacks, rushing, snap share.
- **Where the ball finds him.** Receivers and quarterbacks get the target map (air yards by side of field) plus depth bands with the caught share. Backs whose carries outnumber targets get the run-gap chart instead. Dots carry the play text on hover.
- **Game log.** Week by week with the result, snap share and the position's stat line, season totals in the footer. Drops and broken tackles from PFR ride along.
- **When he gets it.** Touches by down, quarter and game score, each with his share of the team's touches in that spot, yards per touch and EPA. Red zone row included.
- **Intel rail.** Depth chart slot and package, injury report line, red zone touches and TDs, third-down touches and conversion rate, explosive plays, drops, broken tackles, passer rating when targeted (or CPOE and sack rate for a QB). Best plays by EPA. Teammates by share of the ball, each a link.
- **Links in.** Every name in the explorer's Who gets the ball table and in the game-page takeaways becomes a link. Matchup pages get a "players to watch" strip (top three by touches each side) in a later slice.

### Data (all nflverse, all free, all weekly)

| Need | File | Notes |
|---|---|---|
| Bio, number, position, draft | `rosters/roster_2026.csv` (weekly rows, take the latest week) | `gsis_id` is the key everywhere |
| Weekly stat lines | `stats_player/stats_player_week_2026.csv` | receiving, rushing, passing, target share, air yards share, EPA, CPOE |
| Snap share | `snap_counts/snap_counts_2026.csv` | keyed by PFR name; join on name + team |
| Drops, broken tackles, rating when targeted | `pfr_advstats/advstats_week_rec_2026.csv` | PFR name key |
| Injury report | `injuries/injuries_2026.csv` | latest week per team; `report_status` or `practice_status` |
| Depth chart | `depth_charts/depth_charts_2026.csv` | 68 MB; latest `dt` per team, offensive packages only |
| Play-level intel | `ftn_charting/ftn_charting_2026.csv` | play action, screen, RPO, motion, pressure, contested and created catches, drops; joins on `nflverse_play_id` |

Not available: Next Gen Stats (separation, cushion, RYOE) have no 2026 files on nflverse. Leave the slot in the design, add them if they appear.

### Build changes (`tools/build_data.py`)

1. Play rows gain `pid` (the ball handler's `gsis_id`) next to `pl`. Pages key on id; names stay for display. Fixes two players sharing a short name.
2. New `players/{TEAM}.json` per team: roster rows for QB, RB, FB, WR, TE with any snap or stat, each with weekly lines, snap share by week, PFR extras by week, injury line, depth slot. About 20 to 40 KB per team.
3. New `players/index.json`: one row per player league-wide (id, name, team, pos, number, snap share, touches, yards, TD, EPA, status) for the index page and search. About 300 KB; served once, cached.
4. FTN join adds per-play flags to the play rows (`pa`, `scr`, `rpo`, `mot`, `prs`, `cont`, `drop`) when the FTN row exists. Also unlocks team tendencies by play action and screen rate later.
5. Manifest and sync pick the new files up unchanged.

### Plugin (`1.2.0`)

- Router: `view=players` and `player=<id>` routes, body classes `dbd-view-players` / `dbd-view-player`, breadcrumb `DOWN X DOWN › players › Name`.
- Views: `players.php` (server-rendered table from `players/index.json`, filters in JS), `player.php` (server-rendered hero, tiles and game log for crawlers; the map, situations and intel fill in from the play file and the team player file in JS, reusing the explorer's field drawing).
- Sitemap: every player page, `changefreq weekly`. JSON-LD `Person` with `athlete` role and the team.
- Sidebar: Gene adds a Players item under Explore; the plugin exposes `dbd-view-players` for the current state.
- Marks: `DBD_Marks::jersey($abbr, $number, $size)` and `dbdJersey()` in JS, the player version of the tile.

### Versions

- **1.2.0** player layer as above (index, page, links in, build changes 1 to 3 and 5).
- **1.2.x** FTN intel flags on plays and the "players to watch" strip on matchups.
- **1.3.0** nfelo model layer (§15, to be scoped): model line and win probability on matchups, unit-strength grid, power rank on team pages. Needs greerreNFL's OK to republish.

### Open calls for Gene

- Positions covered: offense skill only (QB, RB, FB, WR, TE), or add defenders from the tackle and coverage columns later? Mock is offense only.
- Index default: the visitor's last team (as the explorer remembers) or a league-wide top-touches list?

## 15. Player grades (scoped and mocked 2026-10-30, `mockup-v5/player.html`)

**Status: built as 1.3.0 (2026-10-30), PR stacked on 1.2.2.** Server-rendered bars, ranks and all three game log tables; the view switch is the only JS.

Gene asked what else the player data could do, with nfelo's QB page as the bar. Ten ideas were tabled; this is the first slice: the three pieces that make every player page feel complete and that share one calculation. The mock runs on the real league: every qualifying player's 2026 weekly stats and PFR extras through Week 8.

### 15.1 Where he ranks (percentile bars)

A panel under the stat tiles. One row per metric: label with a sub-label, a league bar with his marker on it (right is better, dashed median), his value, and the percentile as an ordinal in green above the 75th, red below the 25th. Eight metrics per position group, each against every qualifying player at that group league-wide:

| Group | Qualifier | Metrics (lower is better where marked) |
|---|---|---|
| Receivers (WR, TE) | 15+ targets | target share, depth of target, YAC per reception, catch rate, EPA per target, first downs per target, drop rate (low), yards per game |
| Backs (RB, FB) | 30+ touches | yards per carry, rush EPA per carry, EPA per target (5+ targets), broken tackles per touch, explosive rate (10+ run, 20+ catch per touch), touches per game, receiving share of touches, yards per touch |
| Quarterbacks | 50+ attempts | EPA per dropback, CPOE, sack rate (low), yards per attempt, TD rate, INT rate (low), depth of target, rush EPA per game |

Percentile = (players below + half of ties) / n, flipped for lower-is-better. Rank = 1 + players strictly better. Header names the pool: "among 136 receivers and tight ends with 15+ targets". Grades move every week as the pool changes, by design.

### 15.2 Season stats with league rank

Rail panel, above Intel. Totals first (targets, receptions, yards, TD, or the back and QB equivalents), then every graded rate with "7th of 136" beside it; top 10 percent of the pool in green. Same numbers as the bars, so nothing disagrees with itself.

### 15.3 Game log views

A Default / Advanced / Traditional segmented control in the game log header (the sliding pill). Columns per group:

- Receivers: Default = Snap, Tgt, Rec, Yds, TD, EPA. Advanced = target share, air yards share, aDOT, YAC, EPA per target, first downs, drops. Traditional = Rec, Yds, Y/R, TD, 20+.
- Backs: Default = Snap, Car, Yds, TD, Tgt, Rec, EPA. Advanced = YPC, EPA per carry, EPA per target, 10+ runs, broken tackles, snap. Traditional = Car, Yds, YPC, TD, Rec, Rec yds.
- Quarterbacks: Default = Snap, Cmp/Att, Yds, TD, INT, EPA. Advanced = EPA per dropback, CPOE, aDOT, sacks, sack rate, rush EPA. Traditional = Cmp, Att, Pct, Yds, Y/A, TD, INT, passer rating (computed from the four components).

Every view keeps the week and opponent columns, the totals footer, and the sortable headings.

### Build

- New `players/league.json`: for each group, the sorted value list per metric plus the qualifier and n. About 20 KB. Built from the same season totals the index uses; the per-player metric values are added to each player's record in `players/{TEAM}.json` so PHP never recomputes them.
- Percentile and rank are computed in PHP at render time from the sorted lists (binary search), so the bars and the table are server-rendered and crawlable. The game log views are a JS column switch over data already in the page.

### Plugin (`1.3.0`)

- `player.php`: the Where he ranks panel after the tiles, Season stats at the top of the rail, the view switch on the game log. `DBD_Grades` helper: `pctile($group, $metric, $value)`, `rank(...)`, `defs($group)`.
- Players index gets one more sortable column later (the group's headline grade) once the pool math is in the index build; not in this slice.
- The nfelo model layer moves to `1.4.0`.

### Not in this slice (from the ten)

Compare, playstyle text, trend lines, QB to receiver connection, opponent context, weekly movers, career tab, share card, this-week panel. Compare is next, since it reuses everything on this page.

## 16. Player roadmap (agreed 2026-10-30)

Gene: "make the 10 things the next roadmap and build this first slice." Benchmark is nfelo's QB page; our edge is every position, every play on the field, and the charting. Order is the build order unless Gene reshuffles.

| # | Slice | Version | What it is | Data it needs |
|---|---|---|---|---|
| 1 | **Player grades** (§15) | 1.3.0 | League percentile bars, season stats with rank, game log views | `players/league.json` from the build, metrics per player record |
| 2 | **Compare** | 1.3.x | Two or three players of one position side by side: percentile bars overlaid, field maps stacked, game logs aligned by week. Route `?compare=id,id` | Nothing new; reuses the player page pieces |
| 3 | **QB to receiver connection** | 1.3.x | QB page: targets by receiver with catch rate, aDOT, EPA per throw, the field map colored by receiver. Receiver page: his line by passer | `pid` and `qb` on play rows (shipped) |
| 4 | **Opponent context** | 1.3.x | Each game log row names the defense faced and its rank against his position; a strength-of-schedule line | Team defense metrics (shipped) joined by week |
| 4b | **Design cleanup** (added 2026-10-31) | 1.3.x | Gene: "There are A LOT of tables and things on these pages and a lot of them have scroll bars and horizontal bars; look at the layouts and lose any horizontal bars where we can by rearranging things." A layout pass over the team, player and compare pages: audit every `dbd-tw` wrapper at desktop, tablet and phone widths, then widen the main column, drop or fold columns, stack panels, or move tables out of the rail so nothing scrolls sideways except where a wide table has no other honest form. Scope and mock first | Nothing new |
| 5 | **Trend lines** (§21) | 1.3.7 | Four small charts at the top of Week by week: usage, yards, efficiency and a role metric, week by week with a three-game rolling line over the league's middle half, plus a generated read | Per-game league quartiles for those metrics from the build |
| 6 | **Weekly movers** (§22) | 1.3.8 | Players index: who moved in the last full week, three boards (snap share, target share, EPA) of climbers and fallers against each player's own season, plus new faces | `players/movers.json` from the build, computed from the weekly lines already shipped |
| 7 | **Playstyle fingerprint** (§23) | 1.3.9 | Top of How he's used: eight usage traits per position as league-percentile bars, trait tags, and a generated read of how he is used (depth, screens, middle of the field, YAC, third down, red zone, motion, play action, formation) | `fp` per player and per-group `fp` pools from the build, computed from the play rows |
| 8 | **Career** (§24) | 1.3.10 | A Career band on the player page: season-by-season table back to his rookie year with a totals row, a yards-per-season chart with this season's pace, career highs and a read; play-level views stay current season | `data/career/{year}.json` season aggregates cached once per past year from `stats_player_week_{year}`, assembled into `players/career/{TEAM}.json` |
| 9 | **Share card** (§26) | 1.3.12 | A 1200×630 PNG per player (hero, season line, grade bars, team accent) rendered by PHP GD on first request and cached; the player page's og:image, plus a Share button that downloads or shares it | Nothing new from the build; bundled fonts; GD with FreeType on the host |
| 10 | **This week** (§25) | 1.3.11 | A strip under the hero tiles: his next game (kickoff, line, total), the defense's rank at his position and what it allows, his line against that tier this season and the last meeting, his status, and a one-line read | Schedule and team defense metrics (shipped); nothing new |

The nfelo model layer (§13 backlog, greerreNFL's OK needed) is 1.4.0 and runs independent of this list.

## 17. Compare (scoped and mocked 2026-10-31, `mockup-v5/compare.html`)

**Status: built as 1.3.1 (2026-10-31).** All three ways in plus "Players like him" (the three qualified players nearest in usage per game) on every player page.

**Graphics addendum, built as 1.3.2 (2026-10-31, mocked in `mockup-v5/compare-graphics.html`):** Gene took all seven candidates. Percentile radar and floor-and-ceiling strip (boom at or above the pool's 75th percentile game week, dud at or below the 25th; `league.json` now carries those quartiles per group) render in PHP; where-he-wins heat grid, targets on one field, depth butterfly (first two slots), the cumulative race and grouped weekly bars draw in `compare.js` (Chartist for the two charts). Page order: who, ranks, shape and heat, per-player maps, one field and butterfly, race and grouped weeks, floor and ceiling, aligned log, season table.

Roadmap slice 2 (§16). Two or three players of one position group side by side. The mock runs on the real Vikings play file, player file and the league pool; the build searches every team.

### What it is

- **Route** `?compare=id,id[,id]`. Same position group only (receivers and tight ends, backs, quarterbacks); the page refuses a mixed set with a one-line note and keeps the first player. Up to three. The URL is the share link and updates as slots change.
- **Who.** Three slots with a color each (navy, red, amber; never team colors, since teammates would collide). A filled slot shows the jersey tile or photo, name, position, team and whether he is graded. An empty slot is a search box over the league index, filtered to the group. Remove is a small ✕. A **Compare** button on every player page opens the compare with that player in slot one; a second click from another player page fills slot two (the tray lives in `localStorage`).
- **Where they rank.** The same eight metrics as the player page (§15), one league bar per metric with two or three markers in slot colors, then a value column per player with his percentile underneath. The best value in each row is marked green, honoring lower-is-better.
- **Where the ball finds them.** The field maps side by side on one shared air-yard scale so depth reads across. Backs get carries by gap, quarterbacks every throw.
- **Week by week.** One table aligned on league weeks, so byes and missed games show as gaps. A metric switch (receivers: yards, targets, receptions, EPA, snap share; backs and quarterbacks: their equivalents). The best value each week is marked green; a totals row at the foot.
- **Season stats side by side**, each number with its league rank, best in each row green.

### Data and rendering

- Player files per team (`players/{TEAM}.json`) already carry everything the rank rows, the aligned log and the season table need, and the league pool is `players/league.json`. PHP renders those three sections from up to three team files, so the page is crawlable.
- The field maps need each player's team play file; `compare.js` fetches the distinct files (one request per team, two or three at most) and draws on a shared scale. The maps are the only JS-dependent section.
- The index page gains nothing new; the search in the empty slot reuses `players/index.json` inline.
- SEO: canonical on the sorted ids, `noindex,follow` (combinatorial pages are not worth indexing), a `Person` list in JSON-LD is skipped.

### Plugin (`1.3.1`)

- Router: `compare` param, validated ids, group check, body class `dbd-view-compare`. `compare.php` view, `compare.js`, styles under `dbd-cmp*`.
- Player page: a Compare button in the hero's right column under the pennant link.
- Marks: slot colors as CSS variables on the page, not per team.

### Not in this slice

Comparing across seasons, comparing a player to a league average line in the log, and comparing defenses. Three players is the ceiling for the layout to hold at 1000 px.

## 18. The connection: QB to receiver (scoped and mocked 2026-10-31, `mockup-v5/player.html`)

Roadmap slice 3 (§16). One new panel on the player page, between the field map and the game log, that reads two ways depending on who the page is about. The mock runs on the real Vikings play file, where Murray and Wentz have both thrown this season, so both readings are live: Murray's page (`player.html?id=00-0035228`) and Hockenson's (`?id=00-0035229`).

### What it is

**On a quarterback's page: "The connection · who he throws to."**

- **Table of receivers**, one row per player he has targeted, sorted by targets, every header sortable: receiver (color swatch, jersey tile, name linking to his page, position), targets, share of his throws (number plus an inline bar in the receiver's color), receptions, catch rate, yards, aDOT, YAC per reception, touchdowns, third-down targets with conversions, red-zone targets with touchdowns, EPA per target (green or red). An "All throws" footer row. Throws with no intended receiver (throwaways, batted balls) are counted in the takeaway line, not in the table.
- **Takeaway line** in the takeaways voice, generated from the table: first read (most targets, with share, catch rate and EPA a throw), who draws the deep ball (highest aDOT among receivers with five or more targets), the most efficient pairing (best EPA a throw, five-plus targets), the third-down favorite (three or more third-down targets), and the no-receiver count.
- **Where each receiver gets it.** The target field map with dots colored by receiver: the top six receivers get a fixed six-color palette, everyone else grey. Outcome moves to shape: filled dot is a catch, hollow is an incompletion, a red ring is an interception, a white ring a touchdown. Lane headers keep the completions and rate per third of the field. Under it, a chip per receiver with his target count; click a chip (or his table row) to isolate his targets on the map, in the weekly stack and in the table; click again to clear.
- **How he spreads it, week by week.** One stacked column per league week, segments in receiver colors, height is targets, total on top; a bye is a dashed baseline and weeks not yet played are blank. Hover a segment for that receiver's week: targets, catches, yards, EPA.
- **By depth.** A grid of receivers (top six plus "others") by depth band (behind the line, short, medium, deep): targets in the cell with catch rate underneath, shaded by target count.

**On every other player's page: "The connection · his line by passer."**

- **Table of passers**, one row per quarterback who has targeted him: passer (swatch, tile, name), targets, share of that passer's throws that went to him, receptions, catch rate, yards, aDOT, YAC per reception, touchdowns, EPA per target, passer rating when targeting him. Under each passer a muted **"to everyone else"** row with the same columns for that passer's other throws, so the chemistry reads as a delta on the same line.
- **Takeaway line**: each passer to him versus to everyone else (catch rate and EPA a target), and his share of each passer's throws when there are two.
- **Who threw it where**: his target map with dots colored by passer, same shapes and chips as the QB side. **Targets by week, by passer**: the stacked columns, which is where a quarterback change shows up. **By depth** by passer.
- A player with no targets this season (a back who has only carried) does not get the panel.

Colors are a fixed palette of six (blue, gold, red, green, violet, orange) plus grey, assigned by target rank, never team colors, so the swatch in the table, the dot on the field, the segment in the stack and the chip all agree. Takeaway thresholds (five targets, three third-down targets) are constants in one place.

### Data and rendering

- Nothing new from the build. Every number comes from the play rows already shipped: `qb` is the passer, `pid` the targeted receiver on `comp`, `inc` and `int` plays; sacks and scrambles are excluded. Names, numbers, positions and the link come from the team player file, with the row's `pl` short name as the fallback for a receiver who is not in it.
- Rendered by `player.js` from the play file it already fetches for the field map, so the panel costs no extra request; PHP emits the panel shell with a skeleton. A player with games on two teams uses the same per-team files the map already pulls.
- Passer rating when targeted is the standard formula over the targets in question (completions, yards, touchdowns, interceptions per target). Catch rate is receptions over targets. aDOT averages only plays with an air-yards value.
- Tooltips use the shared `[data-tip]` rich tooltip; the table uses the generic `dbd-sortable` sorter with a numeric read of each cell.
- Reduced motion: the isolate toggle swaps opacity with no transition.

## 19. Opponent context (scoped and mocked 2026-10-31, `mockup-v5/player.html`)

Roadmap slice 4 (§16). Two additions to the player page, both from data already shipped: the metrics file carries every defense's EPA allowed per target to wide receivers, tight ends and backs, per designed carry and per dropback, with league ranks (`rank.def[TEAM].WR_epa` and friends), and the schedule file carries the full eighteen weeks with results. Mocked on Jefferson (`?id=00-0036322`), Aaron Jones (`?id=00-0033293`) and Murray (`?id=00-0035228`).

### What it is

**The defense's rank in the game log.** A **Defense** column after Opponent in all three game-log views: a chip reading "4th vs WR" for the defense faced that week, ranked against his position. Wide receivers use EPA allowed per target to WRs, tight ends the TE figure, backs EPA allowed per designed carry ("vs the run"), quarterbacks EPA allowed per dropback ("vs the pass"). Rank 1 is the toughest. Chips are tiered: 1 to 10 navy (tough), 11 to 22 plain, 23 to 32 green (soft). The tooltip gives the defense's number, the league average and the ordinal. The column sorts by rank. Ranks are season to date, the same number the team pages show, not the rank as of that week.

**"Who he's faced" panel**, under the game log:

- **Takeaway line.** Where his slate ranks: the average rank of the defenses he has played against his position, placed among all 32 teams' played slates ("the 1st-toughest slate vs WR: his opponents rank 8.4 of 32 on average, league middle 16.0"), then what is ahead: how many of the remaining opponents are top-10 and bottom-10 against his position, and who is next.
- **The slate.** Eighteen league weeks in two rows of nine. Each cell is the opponent, the defense's rank against his position, and the tier color; played weeks are solid, weeks still to play are dashed, the current week has a red outline, a bye is dotted, and a game his team played without him carries a small DNP mark.
- **His line by defense faced.** Three rows (top-10, middle, bottom-10 defenses) with games, usage per game (targets, touches or attempts), yards per game and EPA per target, touch or dropback, plus an all-games row. Yards are receiving for receivers, scrimmage for backs, passing for quarterbacks.
- **Yards against the rank of the defense.** A scatter of his games, x from 1 (toughest) to 32 (softest), y his yards that week, dots in tier colors labeled with the opponent, a dashed line at his average. Under it a one-line read from the correlation: climbs with softer defenses (r above 0.4), better against the good ones (below minus 0.4), or "barely moves with the opponent; it has traveled". Fewer than four games says so instead.

### Data and rendering

- No build changes. Defense numbers and ranks come from `metrics.json`; opponents and what is ahead from `schedule.json`; his weekly line from the player file. The league comparison of slates uses every team's played games from the schedule, so it exists for all 32 teams at once.
- PHP renders the Defense column and the whole panel: `DBD_Data::rank('def', $opp, $key)` and `DBD_Data::metrics()` are already loaded for the page; the schedule is one more cached read. The scatter is inline SVG from PHP; no JS beyond the shared tooltip.
- The position key is one function of position (WR, TE, RB and FB, QB) kept with the grade group logic so the game log, the panel and future slices (this-week panel) agree.
- Tier thresholds (10 and 22) and the correlation thresholds (0.4) are constants in one place.

## 20. Design cleanup: losing the horizontal scrollbars (audited and scoped 2026-10-31, mock in `mockup-v5/player.html`, `players.html`)

**Status: built as 1.3.6 (2026-10-31).**

Roadmap row 4b. Gene: "There are A LOT of tables and things on these pages and a lot of them have scroll bars and horizontal bars; look at the layouts and try to lose any horizontal bars where we can by rearranging things."

### The frame we actually have

Measured on the live site (1.3.5) inside Gene's Divi layout. The sidebar column eats 231px until Divi stacks it, so the app's width is smaller than the viewport suggests:

| Viewport | App width | Player page main column | Rail |
|---|---|---|---|
| 1440 | 1193 | 751 | 360 |
| 1024 | 777 | 716 (rail stacks under) | 716 |
| 768 | 753 | 705 | 705 |
| 375 | 375 | 341 | 341 |

### Inventory: every wrapper that scrolls sideways

Width needed is the table's natural width; available is the wrapper's. Bars appear wherever needed exceeds available.

| Page | What scrolls | Needs | Available by viewport (1440 / 1024 / 768 / 375) | Why |
|---|---|---|---|---|
| Player | **The connection** table (12 cols QB, 11 cols others) | 1175 / 1010 | 749 / 714 / 705 / 341 | Sits in the 751px main column next to the rail; even full width (1129) is 46px short at 1440 |
| Player | **Game log** (9 to 10 cols) | 839 / 873 | 749 / 714 / 705 / 341 | Same column; Defense chip added 90px in 1.3.5 |
| Player | **Teammates** (5 cols) in the rail | 494 | 347 / fits / fits / 341 | Rail is 360px; five mono columns plus tiles |
| Player | **His line by defense faced**, situations tables | 461, 442 | fits / fits / fits / 341 | Phone only |
| Players index | The roster table (11 cols) | 1092 | fits / 714 / 705 / 341 | Status, Team and Pos columns are text-wide |
| Team explorer | **Who gets the ball** (7 cols) | 564 | 358 / fits / fits / 341 | Lives in one half of a two-column grid at 1440 |
| Team explorer | Game picker strip (`.dbd-gstrip`) | 962 | fits / 716 / 707 / 343 | A single row of game buttons |
| Team explorer | Drive chart SVG | 560 | fits / fits / fits / 321 | Fixed minimum width |
| Game page | Head to head (5 cols) | 678 | 626 / fits / fits / 341 | Two-column grid |
| Hub | This week's games strip (`.dbd-strip`) | 2486 | 1129 / 716 / 707 / 343 | Fourteen game cards in one row, by design |
| Hub | The slate table | 764 | fits / 714 / 705 / ? | 50px over at tablet |
| Compare | Season stats side by side (3 cols) | 417 | fits / fits / fits / 341 | Phone only |
| All pages | Divi's text module reports 540px of content at 375 | 540 | 374 | Something absolutely positioned in the hero; to find in the build |

Not in the inventory: the compare graphics (clean at every width), matchups, and the player rank bars.

### The plan

Four moves, applied in this order, each one removing a class of bars rather than one table.

**1. Reorganize the player page into bands by question, and give wide tables the whole width.** (Revised after Gene's note that the block organization could be better.) The rail goes away. Under the hero and tiles sits a sticky jump nav, then five bands, each with a red kicker heading that names the question it answers:
- **How good is he** · Where he ranks beside Season stats, two columns. They are the same story, league grades and the season line.
- **How he's used** · Where the ball finds him beside When he gets it, two columns. Where and when.
- **Who he works with** · The connection at full width.
- **Week by week** · Game log at full width, then Who he's faced. The schedule story in one place.
- **Intel & plays** · Intel, Best plays and Teammates as a three-up strip at the foot.
Two-column pairs collapse to one column under 900px, the three-up strip under 1100px. The percentile bars run single-column inside their half. On the team explorer, Who gets the ball leaves the half-width grid cell and takes a full row; on the game page, Head to head does the same. Full width is 1129 at 1440, 716 at 1024, 705 at 768.

**2. Compact table typography, one rule for every stat table.** Numbers at 11px mono with 4px cell padding instead of 12px and 6px, headers at 10px with tighter letter-spacing, the name column capped with an ellipsis, tiles at 22px. On the connection table alone this takes 1175 down to about 980; the game log from 839 to about 680. This is the move that makes full width enough at 1440 and 1024 for everything but the players index.

**3. Column tiers with container queries.** `.dbd-content` is already a named container. Every stat table marks its columns with a priority: core (always), `dbd-t2` (hidden under 860px of container), `dbd-t3` (hidden under 560px). The hidden columns are the ones a reader can live without at a glance and can still find on the player's own page:
- Connection, QB side: core Receiver, Tgt, Share, Rec, Catch, Yds, EPA/tgt; t2 aDOT, YAC/rec, TD; t3 3rd dn, RZ.
- Connection, passer side: core Passer, Tgt, Of his throws, Rec, Catch, Yds, EPA/tgt; t2 aDOT, YAC/rec, TD, Rating.
- Game log: core Wk, Opponent, Yds, EPA and the position's usage pair (Tgt and Rec, Car and Yds, Cmp/Att); t2 Snap, TD; t3 Defense, which on phones folds into the opponent cell as a small chip under the name so the rank is never lost.
- Players index: core Player, Pos, Yds, TD, EPA and the usage column; t2 Team, Snap %, Rec, Car; t3 Status (its text moves into the player cell as a dot and a tooltip).
- Teammates in the rail: four columns (Player, Touches, Yds, EPA); TD goes into the yards cell as a mark.
- Head to head, Who gets the ball, defense split, situations: t2 on the least-read column of each.
A sort header that is hidden is simply not offered; the sorter already reads the visible cells. On phones the folded Defense chip is terse ("6th D", the full label in its tooltip) and the Who he's faced ribbon drops to six cells a row and hides its label line.

Mock result (player page, players index): zero sideways bars at 1440, 1024 and 768 with the live app widths; at 375 the connection table and the players index keep their in-panel scroll as planned.

**4. Strips become grids.** The hub's games strip wraps into rows of cards (four across at 1129, two at 716, one at phone) instead of one 2486px row; the team game picker wraps into two rows below 1024. The drive chart loses its minimum width and scales with its viewBox. The slate table at tablet gets the compact typography and fits.

**What still scrolls, on purpose.** At 375px the connection table (about 480px after tiers) and the players index (about 420px) keep an in-panel scroll: six and five columns of numbers cannot honestly fit 341px without becoming a card list, which is a different design. Everything else is bar-free at every width, and nothing scrolls at the page level.

### Data and rendering

- No data changes. PHP views emit the priority classes and the new band order; `dbd.css` carries the compact table rule, the tiers and the grid strips; `players.js` and `team-explorer.js` emit the same classes on the rows they render.
- One shared class, `dbd-stat`, for compact tables so the typography lives in one place; the existing per-table classes stay for their own quirks.
- The Divi 540px overflow at phone is investigated and fixed in the same release.

## 21. Trend lines: the shape of his season (scoped and mocked 2026-10-31, `mockup-v5/player.html`)

Roadmap slice 5 (§16). One new panel at the top of the **Week by week** band, above the game log, so the band reads chart first, table second, defenses third. The game log already holds every number; this is the picture of where they are heading. Mocked on Hockenson (`?id=00-0035229`, six games), Aaron Jones (`?id=00-0033293`) and Murray (`?id=00-0035228`), with Jefferson (`?id=00-0036322`) as the short-season case.

### What it is

**"The shape of his season" · week by week against the league.** Four small charts in a row, one per metric, chosen by position:

| Group | Usage | Production | Efficiency | Role |
|---|---|---|---|---|
| Receivers and tight ends | Targets | Receiving yards | EPA per target | aDOT |
| Backs | Touches (carries + targets) | Scrimmage yards | EPA per touch | Targets |
| Quarterbacks | Dropbacks (attempts + sacks) | Passing yards | EPA per dropback | CPOE |

Each chart:

- **x is the league week, 1 to 18.** Weeks already played carry a slim bar for his number that week (negative efficiency drops below the zero line). A bye is a gap with a dotted tick. A week his team played without him is an × on the baseline marked DNP. Weeks still to play are blank; the current week has the red tick the slate uses. Week numbers label every third week.
- **The league's middle half** is a shaded band from the 25th to the 75th percentile of every qualifying player-game at his position this season, with the median as a dashed line. Qualifying is the same pool as the grades (15 targets, 30 touches, 50 attempts), so the yardstick is "a starter's ordinary week". For the counting metrics a week he dressed but recorded nothing counts as zero, as it already does for the boom-or-dud yardstick; the rate metrics only count games with a target, touch or dropback.
- **The three-game line** is the trailing average over his last three games played, drawn from his third game on; rate metrics roll as the ratio of sums over the window (EPA over targets), not an average of three ratios. A dot marks the latest point, with the value beside it.
- **Header:** the metric, his current three-game figure in mono, and a chip for the gap to the league median ("+3.4 vs lg" in green, "−12 vs lg" in red, grey inside half a quartile of it).
- Hover any bar for that week: opponent, result, the number, and the league median; hover the line for the three games behind the point. Shared `[data-tip]` tooltip.

**The read**, one or two sentences under the charts in the takeaways voice, from fixed rules over the usage and production charts first and the efficiency chart second:

1. **Direction.** Compare the current three-game figure with his season per-game average. It is "climbing" or "slipping" when the gap exceeds half the league's interquartile range for that metric (one threshold for every metric, scaled to how noisy the metric is), otherwise "steady". Example: "Targets are climbing: 9.3 a game over his last three against 7.2 for the season."
2. **Shape.** Both up: "the role and the production are both growing." Usage up with yards above his average but short of the threshold: "the yards are edging up with it." Usage up with yards at or under his average: "the volume is up; the yards haven't followed yet." Usage steady or down with production up: "doing more with less." Both down: "the role is shrinking."
3. **Streak.** Three or more straight games above (or below) the league median in usage or yards, counted back from his latest game: "four straight games above a starter's median in yards." With no streak, the count instead: "at or above a starter's median in yards in 4 of 6 games."
4. **High or low.** His latest game is a season high or low in yards: "Week 7 was his season high."
5. **Fewer than three games** played says "Too few games for a trend yet" and shows the bars alone; a player with no games does not get the panel.

### Data and rendering

- **Build change, small.** `league.json` gains `game` under each group: `{metric: {p25, p50, p75, n}}` for the four metrics above, computed over the same qualifying player-games that already feed `week`. The keys are fixed: rec `tgt, yds, epat, adot`; rb `tch, yds, epatch, tgt`; qb `db, yds, epadb, cpoe`. `week` stays as it is for the game log. The nightly Action publishes it with the next run.
- **Plugin (`1.3.7`).** PHP renders the whole panel in `player.php`: his weekly line from the player file, DNP and bye from the schedule the opponent-context panel already reads, the league band from `DBD_Data::league()`. Inline SVG like the opponent scatter; no Chartist, no new script. If the data file has not picked up `game` yet (an older nightly), the charts draw without the band and the median, the chips are omitted, and the direction rule still runs against his own average.
- The four metric definitions and the rule thresholds (half an interquartile range, three-game window, three-game streak) live in one place in `DBD_Grades` beside the grade groups and the defense keys, so the game log, the grades and the trend agree on what a target, touch and dropback are.
- Layout: four across in the main column, two across below the `dbd-t2` breakpoint (860px container), which holds at phone width. Nothing scrolls sideways; SVGs scale with the column.
- Reduced motion: no animation anywhere in the panel.

### Not in this slice

Trend lines on the compare page (overlaying two players' rolling lines), week-over-week movers on the index (slice 6), and any rolling line on team pages.

Status: built as 1.3.7 (2026-10-31).

## 22. Weekly movers: who moved this week (scoped and mocked 2026-10-31, `mockup-v5/players.html`)

Roadmap slice 6 (§16). A panel at the top of the Players index, above the filters, that answers the question the index table cannot: whose role just changed. The mock runs on the real league through Week 7 (`mockup-v5/data/movers.js`, generated from every team's player file by the same rule the build will use).

### What it is

**"Movers · Week 7" · against his own season.** Every number compares a player's last full week with his average over the games he had played before it, so a mover is someone whose week broke from his own pattern, not someone who had a big week because he always does.

- **The week** is the latest league week in which every scheduled game is final. Mid-week, that is last week; Week 8 with one game played is not a week yet. The badge says which week and how many players played in it.
- **Three boards side by side:** Snap share (points), Target share (points, quarterbacks excluded), EPA (the week's total EPA against his per-game average; receiving for receivers, rushing plus receiving for backs, passing for quarterbacks). Each board lists the five biggest climbers then the five biggest fallers, each row a jersey tile, the name linking to his page, team and position, a sparkline of his season in that metric with the last week's point marked, and a chip for the change ("+31 pts", "−6.4 EPA") beside "before → now".
- **Who qualifies:** two or more games played before that week and a snap that week (the trend's "played" rule: a stats line, or snaps with nothing recorded). A board only lists moves past a floor so a quiet week does not fill with noise: 12 points of snap share, 6 points of target share, 4.0 EPA. Fewer than five past the floor shows fewer rows; none shows "Nobody moved far on this board."
- **New faces**, a chip strip under the boards: players whose first snap of the season came that week with 25% of the snaps or more ("debut"), and players who crossed 50% of the snaps for the first time after never passing 30% ("first start-level week"). Each chip links to the player.
- **The read**, one sentence over the boards in the takeaways voice: the biggest climber and faller on the snap board and what it means ("Week 7 was a changing of the guard in Carolina: Rico Dowdle took 78% of the snaps, up 41 points, while Chuba Hubbard fell to 19%"), from fixed phrasing over the top rows.
- Movers are league-wide whatever the filters say. The team filter marks that team's rows with its color bar; the position switch and the search dim the rows that do not match, so the panel still reads on a team page.

### Data and rendering

- **Build change.** `tools/build_data.py` writes `players/movers.json`: `{season, week, n, snaps: {up: [...], down: [...]}, targets: {...}, epa: {...}, faces: [...]}`. Every row carries `id, name, team, pos, num, photo, before, now, delta, g` (games before) and `s`, his weekly values of that metric for weeks 1 through the movers week (null for a bye or a miss), for the sparkline. The comparison is computed from the player files' weekly lines, so no previous-week snapshot is kept and a missed nightly loses nothing; the §16 row said "previous week's index kept by the build", and this is the sturdier version of the same idea.
- The floors (12, 6, 4.0), the qualifying games (2), the rows per side (5) and the new-face thresholds (25%, 50%, 30%) are constants at the top of the build step and are repeated once in `DBD_Grades` for the plugin's notes.
- **Plugin (`1.3.8`).** `players.php` renders the whole panel from `movers.json` through `DBD_Data::get()`: PHP markup, inline SVG sparklines, the shared `[data-tip]` tooltip (the week's opponent and the exact numbers). `players.js` applies the dim-and-highlight when a filter is active; nothing else moves. A data file without `movers.json` (an older nightly) skips the panel.
- Layout: three boards across in the main column, one board after another below the `dbd-t2` breakpoint (860px container); rows never scroll sideways, since the sparkline is fixed at 72px and the name cell truncates.
- Reduced motion: no animation in the panel.

### Not in this slice

Injury-report changes week over week (the index already carries this week's status beside each name; a "newly listed" mark needs last week's report kept by the build and can ride on a later slice), a movers card on the hub, per-team movers on the team page, and a `?week=` picker for past weeks (the data shape allows it later).

Status: built as 1.3.8 (2026-10-31).

## 23. Playstyle fingerprint: how he is used (scoped and mocked 2026-10-31, `mockup-v5/player.html`)

Roadmap slice 7 (§16). A panel at the top of the **How he's used** band, before the field map, that says in words what the map and the situation tables show in dots and rows: the shape of his role. The grades (§15) say how good he is; this says what kind of player he is. Mocked on Jefferson (`?id=00-0036322`), Hockenson (`?id=00-0035229`), Aaron Jones (`?id=00-0033293`) and Murray (`?id=00-0035228`) with real league pools.

### What it is

**"Playstyle" · how he is used, against the league at his position.** Two halves:

- **The read**, three or four sentences in the takeaways voice, generated from fixed rules over his traits: the two traits that stand furthest from the league (75th percentile or above, 25th or below) lead, each with his figure and the league median in the same sentence ("Jefferson is a downfield target: 21% of his targets travel 20 yards or more, against 12% for the league's receivers, and he wins before the catch rather than after it"). Then a situational sentence from his third-down and red-zone shares against his overall share ("His share of the team's targets is 26%: on third down it climbs to 33%; inside the 20 it falls to 18%"; a move under four points reads "holds"). Then a formation sentence from motion, play action or shotgun when one of them stands out. A player with nothing extreme gets a sentence that says so and names what sits nearest the middle.
- **Trait tags** under the read: up to four short tags for the traits that stand out ("Downfield", "Screen game", "Third-down guy", "Motion man", or their opposites, "Rarely deep", "Not the red-zone option"). Each tag carries the number in its tooltip.
- **Eight bars** on the right, one per trait, in the percentile-bar form of "Where he ranks" but neutral: the gradient is grey, the marker is the team color, the dashed line the median, and the percentile is printed without a good or bad color, because a style is not a grade. Hover a bar for his figure, the league median and the pool size.

**The traits**, eight per group, every one a share of his own plays so a low-usage player is not punished for volume:

| Receivers and tight ends (15+ targets) | Backs (30+ touches) | Quarterbacks (50+ attempts) |
|---|---|---|
| Deep share: targets 20+ yards downfield | Inside runs: carries at guard or tackle, of all his carries | Deep share: attempts 20+ yards downfield |
| Screens: share of his targets | Shotgun runs: carries from the gun | Middle of the field: attempts between the numbers |
| Middle of the field: targets between the numbers | Receiving share: targets per touch | Play action: share of attempts |
| After the catch: share of his receiving yards gained after the catch | Third-down share: of team touches on third down | RPO: share of dropbacks |
| Third-down share: of team targets on third down | Red-zone share: of team touches inside the 20 | Scrambles: share of dropbacks |
| Red-zone share: of team targets inside the 20 | Short-yardage share: of team touches with two or fewer to go | Shotgun: share of dropbacks |
| Motion: targets with pre-snap motion | RPO: carries on run-pass options | Against the blitz: EPA per dropback blitzed minus not blitzed (ten blitzed dropbacks or more) |
| Play action: targets off play action | Motion: touches with pre-snap motion | Motion: dropbacks with pre-snap motion |

The pool is the grades' pool (the same qualifiers), so the percentiles move every week like the grades do. A player under the qualifier gets the bars without markers and a note saying when the fingerprint arrives, as the grades do.

### Data and rendering

- **Build change.** Each player record in `players/{TEAM}.json` gains `fp`: the eight trait values plus `share`, his overall share of the team's targets (or touches, or dropbacks), all from the team's play rows the build already writes (target = `comp`, `inc`, `int` with his `pid`; carry = `run` with his `pid`; dropback = `comp`, `inc`, `int` or `sack` with his `qb`, plus `scramble` rows where he is the `pid`, since a scramble's passer field is empty in the play data; the group is his roster position, so a receiver's trick-play pass never makes him a quarterback; team denominators are the team's own plays of the same kind). `players/league.json` gains `fp` under each group: a sorted list per trait across qualifying players, the same shape as `dist`, so the plugin's percentile code serves both. The FTN flags (play action, screen, RPO, motion, blitz) are already on the rows; where FTN has not published a week they are zero and the shares read low that week, which the note under the bars says.
- **Plugin (`1.3.9`).** PHP renders the whole panel in `player.php` from `fp` and the pool: `DBD_Grades::pctile()` gains a pool argument (`dist` or `fp`); trait definitions, the tag words and the read templates live in `DBD_Grades` beside the grade definitions. No JS; the shared tooltip handles the bars and tags. A data file without `fp` (an older nightly) skips the panel.
- Thresholds, in one place: standout at the 75th and 25th percentiles, the situational move at four points, the blitz split at ten blitzed dropbacks.
- Layout: read and tags on the left, bars on the right, in the band's existing two-column grid; one column under 700px container like the rest of the band. Nothing scrolls sideways.
- Reduced motion: no animation in the panel.

### Also in this release

- The Players index's League leaders view lists every player (565 and counting) sorted by snap share instead of the top 60. Gene, 2026-10-31: "why don't we just show ALL players?" No reason beyond page weight, which is modest.

### Not in this slice

A fingerprint on the compare page (two players' bars overlaid), team-level tendencies in words (the team page keeps its charts), and a league "most extreme" list on the index.

Status: built as 1.3.9 (2026-10-31).

## 24. Career: season by season (scoped and mocked 2026-10-31, `mockup-v5/player.html`)

Roadmap slice 8 (§16). A new band on the player page, **Career**, between Week by week and Intel & plays, so the page reads this season first and his history after. Everything above it stays current season; this is the only place older years appear. Mocked on the real careers of Jefferson (`?id=00-0036322`), Hockenson (`?id=00-0035229`), Aaron Jones (`?id=00-0033293`) and Murray (`?id=00-0035228`), pulled from nflverse's weekly stat files for 2016 through this season.

### What it is

**"Career" · season by season, back to his rookie year.** One panel, three parts:

- **The career line and the read.** A header strip of career totals (games, and the position's four counting numbers: targets, receptions, yards, touchdowns for receivers; carries, rushing yards, touchdowns, receptions for backs; attempts, yards, touchdowns, interceptions for quarterbacks) with the span ("2020 to 2026 · 7 seasons · 3 teams"). Under it one or two sentences in the takeaways voice: his best season by yards and what this season is on pace for over 17 games from his per-game rate, against that best ("His best year was 2022, 1,809 yards; this season's 60 a game puts him on pace for 1,027"). A rookie reads "First season; N games in."
- **Yards by season.** A bar per season, regular season only, with the current season drawn hollow and labeled "through wk N" plus a faint pace extension to the 17-game projection, so a partial year never looks like a decline. The best season carries a small mark. Hover a bar for the season's team, games and the yards-per-game figure.
- **The table.** One row per season, oldest first, with the current season last and a career totals row under a rule. Columns by group, tiered so narrow screens keep the core: receivers Season, Team, G, Tgt, Rec, Yds, TD, Y/R², Yds/g², EPA/tgt³; backs Season, Team, G, Car, Yds, YPC², TD, Tgt², Rec, Rec yds², EPA/touch³; quarterbacks Season, Team, G, Cmp/Att, Pct², Yds, TD, INT², Y/A², Rating, EPA/db³. The current season's row is marked as partial; the career-high cell in each counting column is bold. Rows sort by any column.
- **Career highs**, a chip strip under the table: the season of his most yards, most touchdowns, most receptions or carries, with the figure; a player with one season gets none.

Seasons are regular season only; a player with no prior seasons in the data (a rookie) still gets the band with this season as his one row and the read saying so. Players whose careers predate 1999 do not exist on current rosters, so the backfill starts there.

### Data and rendering

- **Build change, two steps.** (1) `data/career/{year}.json`, one per past season from 1999 through last season: every player's regular-season totals for that year (id, name, team, position, games and the counting and EPA fields the weekly file carries), aggregated from `stats_player_week_{year}.csv.gz` the first time the build runs without the file and never rebuilt after, since a finished season does not change. A missing year is skipped with a note in the build log rather than failing the build. (2) `players/career/{TEAM}.json`, rebuilt nightly: for each player on the team file, his season rows from the yearly files plus this season from the weekly stats the build already loads, newest season's team from the roster. Passer rating and per-game figures are computed in the plugin, not stored.
- `DBD_Sync` learns the two new paths (`career/{year}` and `players/career/{TEAM}`), the lesson of 1.3.8.
- **Plugin (`1.3.10`).** PHP renders the band in `player.php` from the team career file through `DBD_Data::get()`; inline SVG for the chart, the shared `[data-tip]` tooltip, the generic sorter on the table, `DBD_Router::tierize()` for the column tiers. Column definitions and the pace rule (17 games, per-game rate from games played) live in `DBD_Grades` beside the trend definitions. A data file without the career files skips the band and its jump-nav entry.
- Layout: the band is a single full-width panel; chart above the table; the table uses `dbd-stat` and the tiers so nothing scrolls sideways at tablet widths; on phones the Y/R, Yds/g and EPA columns fold away.
- Reduced motion: no animation in the panel.

### Not in this slice

Playoff seasons (the weekly files carry them; a toggle can come later), college and draft history beyond the hero's draft line, season-by-season grades (the league pools are current-season only), and a career view on the compare page.

Status: built as 1.3.10 (2026-10-31).

## 25. This week: his next game (scoped and mocked 2026-10-31, `mockup-v5/player.html`)

Roadmap slice 10 (§16). A strip on the player page between the season tiles and the jump nav, so the most time-sensitive thing on the page sits at the top: who he plays next and what that defense gives up at his position. Mocked on the Vikings' Week 8 trip to Detroit (Jefferson `?id=00-0036322`, Jones `?id=00-0033293`, Murray `?id=00-0035228`), which the real schedule and metrics files already hold.

### What it is

**"This week" · Week 8 · at Lions.** One compact panel, four cells left to right, stacking on phones:

- **The game.** The opponent's mark and nickname with home or away, kickoff in the site's time zone ("Sun Nov 1 · 1:00 PM ET · Ford Field"), and the market line from the schedule file ("DET −3 · O/U 46.5"). A bye reads "Bye week" with the following game named; a season with nothing left reads "Season over".
- **The defense.** The opponent's rank against his position (the §19 chip, tiered: tough, middle, soft) with the number behind it: "allow +0.42 EPA per target to wide receivers, league +0.10", and the plain word for the tier ("a soft matchup", "a tough one", "middle of the pack").
- **His history.** His line this season against defenses in that tier (games, usage a game, yards a game, from the §19 split), and the last meeting with this opponent this season when there has been one ("Week 3 at MIN: 6 catches, 89 yards, 1 TD"), else "First meeting this season".
- **His status.** The injury line from the hero (status and what), or "No listing", and his snap share over the last three games so a returning player's ramp shows.

Under the cells, **the read**, one or two sentences in the takeaways voice from fixed phrasing: the matchup word and the number, his history against the tier, and the last meeting if any ("A soft matchup: the Lions rank 31st vs WR, allowing +0.42 EPA a target. He has averaged 84 yards a game against bottom-10 defenses this season, and he caught 6 for 89 against them in Week 3.").

**Which game is "this week":** the current week's game while it is still to play; once it is final (mid-week on this site, since the build runs nightly), the next game on the schedule, with the strip titled "Next up · Week 9" instead. The current-week rule is the schedule file's `current_week`.

### Data and rendering

- **No build change.** The schedule file carries kickoff, stadium, spread and total per game; the metrics file carries every defense's EPA allowed per target by position with ranks; the player file carries his weekly line, snaps and injury listing. Spread follows nflverse's sign (positive = home team favored) and is rendered as the favorite's name and number.
- **Plugin (`1.3.11`).** PHP renders the strip in `player.php` from data already loaded for the page; the tier split reuses the §19 helpers. Kickoff times convert from the schedule's Eastern clock to the WordPress time zone. The shared tooltip carries the defense's full line on the chip. No JS.
- Layout: a four-cell grid in the main column; two cells across under 860px container, one under 480px. Nothing scrolls sideways.
- Reduced motion: no animation in the panel.

### Not in this slice

A team-level this-week card on the team page and the hub (the hub's slate already names the game), weather, and the opponent's injury report.

Status: built as 1.3.11 (2026-10-31).

## 26. Share card: the player page as an image (scoped and mocked 2026-10-31, `mockup-v5/card.html`)

Roadmap slice 9 (§16), the last on the list. Two things that are one image: the picture a link to a player page unfurls into on X, Slack, iMessage and Facebook, and the same picture a reader can save or share from the page. Social crawlers do not run JavaScript, so the image has to exist at a URL as a PNG; that rules out drawing it in the browser and points at the server. The mock is the visual spec, three real cards at 1200×630: Jefferson (`card.html?id=00-0036322`), Jones (`?id=00-0033293`), Murray (`?id=00-0035228`).

### What it is

**The card**, 1200×630, light, in the site's type:

- **Brand bar** across the top: the Down by Down wordmark left, "2026 season · through Week 8" right, a rule under it in the team's color.
- **Left half, the hero:** headshot in a circle on the team-color disc (the jersey tile with his number when the headshot is missing), the name in the hero face with the surname in red, the kicker line "WR · #18 · Vikings · 6th season", then the season line as four tiles from the hero (targets, receptions, yards, touchdowns for receivers; carries, yards, touchdowns, receptions for backs; attempts, yards, touchdowns, interceptions for quarterbacks), each with its per-game or rate sub-line.
- **Right half, where he ranks:** the eight grade bars from §15 in the card's own proportions, label left, bar with the median tick and his marker, percentile right in the hero face, green for 75th and up, red for 25th and down. A player under the qualifier gets the four season tiles across the full width and the line "Graded at 15 targets" where the bars would be.
- **Footer:** "downbydown.com" left, "Data: nflverse · grades among 136 receivers and tight ends" right.

**On the page:** a **Share** button in the hero header beside the team link. On a phone it opens the system share sheet with the image attached (the Web Share API with files, where supported); elsewhere it opens the PNG in a new tab, which the browser saves or copies. The button carries a tooltip with the card's size.

**In the head:** `og:image`, `og:image:width`, `og:image:height`, `twitter:card` (summary_large_image) and `twitter:image` on player pages, pointing at the card URL, alongside the title and description the router already writes.

### Data and rendering

- **No build change.** Everything on the card is on the player page already: the player record (name, number, position, photo, weekly line), the grades and the league pool, the team's colors.
- **Rendering: PHP GD at request time, cached.** A route `?card=<gsis id>` renders the PNG with GD and FreeType, writes it to `wp-content/uploads/down-by-down/cards/<id>-<through week>-<plugin version>.png`, and serves it with long cache headers; later requests serve the file. The cache key carries the data week, so the card refreshes with each week's build, and the plugin version, so a design change does not serve stale cards. The plugin bundles the four typefaces as TTF under `assets/fonts/` (Tourney, Barlow Condensed, IBM Plex Sans, IBM Plex Mono; all Open Font License). The headshot is fetched once with `wp_remote_get`, drawn through a circular mask, and skipped for the jersey tile on any failure. Text is drawn with `imagettftext`; bars and discs with GD's filled shapes at 2× and downsampled for smooth edges.
- **Host precondition:** GD with FreeType, which WordPress's Site Health lists under the GD module. Without it the route returns a 404, the Share button is not rendered, and the head carries no image tags; nothing else on the site changes. The first request for a given card does the work (about a quarter of a second); crawlers hitting a page before anyone has asked for its card wait that long once.
- **Plugin (`1.3.12`).** `class-dbd-card.php` (the renderer and the route), the fonts, the Share button in `player.php`, the head tags in `DBD_Router::head()`, and a small script for the Web Share API in `dbd-site.js` that falls back to a plain link. The Cloudflare layer in front of the site caches the PNG like any upload.
- **Versioning the design:** the card's layout constants (sizes, margins, the eight bars' geometry) live at the top of the renderer; the mock is the reference.

### Not in this slice

Team cards, game cards and compare cards (the renderer is written so a second layout can be added), a dark card, and a card for the hub.

Status: built as 1.3.12 (2026-10-31). The repo gained a Plugin check workflow (PHP syntax plus three rendered cards as an artifact) since no machine here runs PHP.

## 27. Roadmap 2 (proposed 2026-10-31, revised the same day with Gene's list; awaiting his order)

All ten slices of §16 are live as of 1.3.13. The player page is now the deepest page on the site; the game page, the team page and the hub have barely moved since 1.3.0, and the site still describes only the offensive half of the sport. Gene's own list (2026-10-31) added fantasy, a lineup tool, team logos, a visual pass, the defense, and a look at what nfelo's player page has that ours does not; his first item, every player on the index instead of the top 60, shipped in 1.3.9.

### What nfelo's QB page has that ours does not (read 2026-10-31, Josh Allen 2026)

- Ranks on the headline numbers ("Total EPA 80 · 1st in the NFL"); ours rank only in the season-stats table.
- Three stats we do not show: success rate, ANY/A and total EPA; also QBR, which is ESPN's and not reproducible.
- A **playstyle decomposition** of EPA into components (after the catch, air yards, rushing, sack avoidance, interception avoidance, incompletion avoidance) with each component's league percentile, and the season against the career average. Ours is a fingerprint of shares, which says how he is used, not where his value comes from.
- A compact **passes-by-location grid** (behind, short, intermediate, deep by left, middle, right) with frequency against the league. Ours has the field map and the depth bars, not the 4 × 3 grid.
- A game log that lists all eighteen weeks including the bye and DNP rows; ours lists played weeks and leaves the slate to the ribbon.
- A season switcher in the URL (that is the season archive, below).

Ours has what theirs does not: every position, the play-level field map, the connection, the opponent context, trend lines, movers, the career band, this week and the card.

### The slices

| # | Slice | Page | Reader question it answers | What it needs | Size |
|---|---|---|---|---|---|
| 1 | **Design pass** (Gene) | Everywhere | Does every element breathe? An audit of spacing, padding, header heights, table cell rhythm, chart margins and the hero at 1440, 1024, 768 and 375, written as a checklist in the scope, then fixed in one release | Nothing new | M |
| 2 | **Team logos** (Gene) | Everywhere | The real marks, handled like the headshots: hotlinked from the public CDN URLs the nflverse teams file already carries, behind the same admin toggle, with the drawn tiles as the fallback; used in heroes, game pages, the hub slate and the cards | Nothing new | S |
| 3 | **Player page, nfelo parity** | Player | The list above: ranks on the hero tiles; success rate, ANY/A and total EPA in the stats; an EPA decomposition for quarterbacks (and a receiving one for receivers: air, after the catch, drops, first downs); the passes-by-location grid against the league; all eighteen weeks in the game log | Career pool for the season-vs-career line comes from the career caches; the rest is on the page | M |
| 4 | **Win probability** | Game | How did this game turn? The chart across the game, the swings marked, the top plays by WPA, a "the game turned on…" read | `wp` and `wpa` on the play rows (the pbp carries them) | M |
| 5 | **Box score and the players in this game** | Game | Who did what? Each team's player lines for the week, linking out, and who got the ball in that game | Nothing new | M |
| 6 | **Fantasy lens** (Gene) | Player, Compare, Leaders | Should I start him? Points per game in standard, half and full PPR, position rank, floor and ceiling from his weekly line, and the matchup: fantasy points the opponent allows to his position, from the weekly files joined to the schedule; a Fantasy tab on compare | Fantasy columns are in the weekly files; a per-defense allowed table from the build | M |
| 7 | **My team** (Gene) | New `?view=myteam` | Here is my lineup; show me everything about it. Pick players by search, kept in the browser and shareable as a URL (`?myteam=id,id,…`), laid out as a lineup: each slot with this week's matchup chip, status, snap trend and fantasy pace, one click to the player page or a compare | Nothing new once 6 ships | M to L |
| 8 | **Leaders** | New `?view=leaders` | Who leads the league in what this site measures? Boards for every grade metric, fingerprint trait and fantasy format, with position and minimum-usage filters | `players/leaders.json` from the build | M |
| 9 | **The defensive half** (Gene) | Team | What does this defense allow, and how? A Defense tab on the team page from the plays the team defended: by position, depth, situation, formation, with the ranks the player pages quote and the fantasy points allowed from 6 | `plays/{TEAM}-def.json` from the pbp by defensive team | L |
| 10 | **Week in review** | New `?view=week` | What happened this week? Movers, best plays by EPA and WPA, the biggest swings, the cards; past weeks stay reachable | Nothing new once 4 ships; a league-wide best-plays pass in the build | M |
| 11 | **Hub refresh** | Hub | What should I look at today? Movers card, this-week cards per game with the line and the defense context, standings in reach | Nothing new | S to M |
| 12 | **Compare, finished** | Compare | The deferred overlays: career lines, fingerprint bars, trend lines | Nothing new | S to M |
| 13 | **Polish release** | Everywhere | Pretty URLs with per-matchup Open Graph images, the top-level admin menu, team and game cards, pruning old card PNGs, the skeleton reveal | Nothing new | S each |
| 14 | **Season archive** | Everywhere | Next September, where did 2026 go? Data per season, a season switch | Build and data layout; before the 2027 season | L |
| 15 | **nfelo layer (1.4.0)** | Hub, game | The model's numbers beside ours | greerreNFL's OK | M |

**Foundations ride along:** the view harness learns the game, compare and matchups views; the nightly build gains a sanity step; the 22 MB of career caches is the known cost of the backfill.

**Recommended order:** 1 and 2 together as one release (the visual pass and the logos touch the same places), then 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13; 14 in the summer; 15 when it unblocks. The reasoning: the visual pass first so everything built after it lands on a settled grid; nfelo parity next because it is the page people compare us against; the game page because games happen weekly; fantasy before My team because the lineup is only useful with points and matchups behind it; the defensive half once the landing pages are strong.

## 28. Design pass and team logos (scoped and mocked 2026-10-31, `mockup-v5/system.html`)

Roadmap 2, slices 1 and 2 as one release (Gene: "go over all the visual elements and make sure they all have proper spacing around the content and everything is properly proportioned for good UX"; "explore using the NFL logos in some places, the same way we handled the player photos"). The audit below was measured on the live site (1.3.13, inside the Divi page with Gene's sidebar) at 1440 and 375, on the player, team and hub pages, by reading computed styles, not by eye.

### What the audit found

| # | Finding | Measured | Effect |
|---|---|---|---|
| A1 | **Divi's table rule wins over ours.** The theme's inline `.entry-content tr td { padding: 6px 24px }` is more specific than `.dbd-stat td` and `.dbd-glog td`, so nearly every table cell on the site has 24px left and right instead of 4 to 6 | 296 of 400 cells on the player page, 161 of 173 on the team page, 146 of 200 on the hub | Every table is about 40px wider per column than designed; this is most of why tables still scroll on phones (four on Jefferson's page: 681, 530, 499 and 443px of content in a 341px box) |
| A2 | **Fourteen text sizes** in one page, 123 runs of text at 8 or 9px | 8, 9, 10, 11, 12, 13, 14, 15, 18, 19, 21, 22, 28, 54px | 8 and 9px is below comfortable reading on every screen; the steps are too close to read as hierarchy |
| A3 | **Eleven letter-spacings** on uppercase labels | 0.01 to 0.20em | Labels that are the same kind of thing look subtly different |
| A4 | **Panel headers four different heights** | 34, 39, 57, 62px; the two tall ones wrap (Where he ranks' sub-label, the game log's view switch) | Panels side by side start their content at different heights |
| A5 | **Panel insides padded six ways** | content 14/12, 14/0, 0/0, 14/8; read paragraphs 14/10/0, 14/8/0, 14/8/10, 14/8/6; notes 14 or 0 on the left | Text edges inside panels do not line up with each other or with the header |
| A6 | **Phone hero falls apart.** At 375 the headshot sits alone between the bio and the team block with dead space around it, and the pennant, snap badge, Share card, Compare and Players like him stack as five loose rows | about 160px of empty space, a 330px header stack | The first screen on a phone is mostly air and buttons |
| A7 | **No team logos anywhere**; teams are drawn tiles and pennants | 0 images on the hub | Gene's request |

### The fix

**A spacing scale, used everywhere.** Four steps: 4, 8, 14, 22px, as CSS variables (`--dbd-s1` to `--dbd-s4`). Panel header padding s3 by s2+2 (14 by 10, as now) with a fixed minimum height of 40px; every first block inside a panel padded s3 on the sides and s3 on top; read paragraphs s3 sides, s2 top, s3 bottom; notes s3 sides, s3 bottom; gaps between panels s3, between bands s4. Panel headers never wrap at desktop: a long sub-label truncates with a tooltip, and the game log's view switch drops under the label only under 700px container.

**A type scale of seven steps:** 11, 12, 13, 15, 18, 28 and the hero size; nothing under 11px. Uppercase labels use one tracking (0.14em) for panel labels and one (0.08em) for table headers.

**Tables:** our cell rules gain enough specificity to beat the theme (`.dbd .dbd-stat td`, scoped to the plugin's root) and the theme's padding stops applying inside the app. Measured effect in the mock on Jefferson's real game log with the extra Air, YAC and target-share columns: 928px of content with the theme's padding, 496px with ours, 47% narrower at the same font; on a phone the tiered columns fold the rest, so three of the four phone scrollers should stop scrolling.

**Phone hero:** the headshot moves up beside the name as a 96px cutout (the desktop hero keeps the large cutout), the team block collapses to one row (pennant and nickname left, snap share right), and Share card, Compare and Players like him become one wrapping row of buttons under it.

**Team logos, handled like the headshots:**

- The nightly build adds `logo` (ESPN's 500px PNG, which every team has and which responds as an image) and `logo_sq` (nflverse's squared logo) to `teams.json`.
- A **Show NFL team logos** checkbox under Settings, off by default, beside the headshot one, with the same note about hotlinking marks the site does not own.
- When it is on, `DBD_Marks::tile()` and `dbdMark()` return the logo as an `<img>` (sized, lazy, `alt` the team name) with the drawn tile as the fallback on error; the pennant keeps its shape and puts the logo in the flag; the share card draws the logo in the brand bar's corner instead of nothing. When it is off, everything is as it is today.
- Places the logo shows: the hub slate and game of the week, standings, the team page hero, the game page header and head to head, the player hero's team block, the players index team picker and rows, the this-week strip and the opponent chips' tooltips.

### Data and rendering

- Build: two columns into `teams.json`. Plugin (`1.4.0`, since it touches every page): the scale and the table fix in `dbd.css`, the phone hero in `player.php` and CSS, the toggle in `settings.php` and `DBD_Data::logos()`, the logo branch in `DBD_Marks` and `marks.js`, the card's corner logo in `DBD_Card`.
- The view harness checks the tables' computed padding in a later foundations pass; this release adds a CSS rule test to the mock only.
- Measured acceptance: no text under 11px; panel headers one height at desktop; no table cell padded more than 8px on a side; on Jefferson's page at 375, one table scrolls (the connection's, which has no honest narrower form) instead of four.

### Not in this release

Pretty URLs and the admin menu (polish release), dark mode (not wanted), a logo on the share card's hero (the headshot stays the subject).

Status: built as 1.4.0 (2026-10-31). As built: the type floor (11px) and the two letter-spacings apply everywhere; 17px merged into 18px; the larger display sizes (20 to 34px headings and scores) were left as they were rather than forced onto the seven-step scale, to avoid reflowing headline layouts for little gain. The table fix raises every plugin rule by one class under .dbd rather than patching individual tables.

## 29. Player page, nfelo parity (scoped and mocked 2026-10-31, `mockup-v6/`)

Roadmap 2, slice 3. Gene's question: what does nfelo's player page have that ours does not (the list is in §27). The mock is the real 1.4.3 page for **Josh Allen** (`parity-qb.html`) and **Justin Jefferson** (`parity-wr.html`), fetched from downbydown.com, with the new parts added and outlined in red. Every number in it is this season's data through Week 8, computed from nflverse play-by-play the way the build will compute it.

### What changes on the page

| # | Part | What the reader sees | Allen, measured | Jefferson, measured |
|---|---|---|---|---|
| P1 | **Ranks on the hero tiles** | Each tile gets a bottom line, "5th of 38", green inside the top 10, ties as "T-16th", with a tooltip naming the stat and the pool. The pool is the one "Where he ranks" already uses (QBs with 50+ attempts, receivers with 15+ targets, backs with 30+ touches); a player outside it shows no rank line. Counting stats rank on season totals, as nfelo does, so a missed game costs places | ANY/A 5th, rushing yards 1st, EPA per dropback 5th of 38 | targets T-68th, EPA per target 10th of 136 (he missed Week 4) |
| P2 | **Three new season stats** | Success rate, ANY/A (QBs) and Total EPA join the Season stats table, each with its league rank. On the QB tiles, ANY/A replaces Snap share, which the hero badge already shows | success 48% (13th), ANY/A 7.83 (5th), total EPA +72.5 (2nd) | success 52% (73rd), total +22.3 (17th) |
| P3 | **Where his EPA comes from** (new panel, after Playstyle) | A waterfall that splits the season's EPA by what happened, with each part's total, per-play rate and league percentile, a one-sentence read, and this season's efficiency against his career (from the career caches) with a bar per season | air yards +28.0 (67th), after the catch +96.4 (88th), incompletions −41.3, interceptions −8.7, sacks −20.6 (41st), passing +53.0; scrambles +21.2 (96th), designed runs −1.7; total +72.5. Passing EPA per dropback +0.29 against +0.14 career: the best of his nine seasons | air +15.2 (78th), after the catch +13.3 (63rd), incompletions −7.3, no interceptions; total +22.3. +0.72 per target against +0.36 career |
| P4 | **Depth by direction** (new panel, under P3) | The 4 × 3 grid (behind the line, short 0–9, intermediate 10–19, deep 20+; left, middle, right) with two views: **How often** (his share of throws or targets against the league's, tinted where he goes more than the league) and **EPA per throw** (his against the league's in that cell, green or red). The line of scrimmage is drawn across. Cells under three plays show no EPA. A sentence names the most over-used cell with five or more plays | deep middle 5% against the league's 2%, +1.53 EPA a throw | the grid on targets |
| P5 | **Game log, all eighteen weeks** | The bye week and the weeks he did not play appear in place (a did-not-play row shows the team's result, and that week's injury designation when the report had one); this week's game follows, and the rest of the schedule sits behind a "Rest of the schedule · N games" button. All three views (Default, Advanced, Traditional) get the rows; sorting a column sets them aside, and they never count in the totals row | bye in Week 7, Week 8 vs Ravens, 10 more behind the button | did not play Week 4, bye Week 6 |

**Running backs** get P1, P2 and P5, and a P3 with runs and receiving parts (designed runs; air yards, after the catch and incompletions on targets; total). P4 shows for a back only with 15+ targets.

### Definitions

- **Dropback:** a pass attempt, a sack or a scramble. **Success:** nflverse's flag (the play gained EPA). **ANY/A:** (passing yards + 20 × TD − 45 × INT − sack yards) ÷ (attempts + sacks).
- **Total EPA:** passing plus rushing for a QB, rushing plus receiving for a back, receiving for a receiver, from the same weekly figures the tiles already use.
- **The split (P3):** completions divide at the catch using nflverse's air EPA; for a QB the after-the-catch part is his credited EPA (nflverse's QB EPA) minus the air part, so the passing parts add up to the passing EPA the tile shows. Parts come from the play file and totals from the weekly file; the two differ slightly (fumbles after the catch, laterals, stat corrections), and the gap shows as an **Other** row, hidden when under 0.1. Allen's is −0.8, Jefferson's +1.0.
- **Percentiles** are per play (per dropback, per target, per touch) among the same qualifying pool, higher is better, so a cost smaller than most ranks high.
- **Depth and direction** use nflverse's air yards and pass location, as the offense sees the field; sacks and throws with no charted direction are left out.

### Ride-along fixes (found while building the mock)

- The QB tile "TD / INT" reads "0 first downs" for every quarterback: the build never carried passing first downs. Allen has 66.
- The This week panel's kickoff badge reads "6:00 PM GMT+0000" on the live site; it should read Eastern time like everything else on the page.

### Data and rendering

- **Build** (`tools/build_data.py`): reads `air_epa`, `comp_air_epa`, `qb_epa`, `pass_location` and `first_down` from the play-by-play it already downloads and writes a per-player `ep` object into `players/{TEAM}.json` (parts with totals, per-play rates and percentiles, success rate, ANY/A, the grid cells with the league's share and EPA per cell) plus `trk` (tile ranks). `league.json` gains the league grid. The weekly columns gain `passing_first_downs`; the injuries step keeps a per-week designation (`injw`). Play files and the sync allowlist do not change. About 1 KB per qualifying player across the team files.
- **Build check:** for every qualifier the parts must add to the play-file total within 0.01, and the run fails if any Other row is larger than 3 EPA.
- **Plugin** (1.5.0): `player.php` (rank line, the three stats, two panels, the game log rows), `dbd.css`, `player.js` (the grid's view switch, the schedule button, sorting that sets non-game rows aside), the view harness fixtures (a QB, a receiver, a back and a non-qualifier with no rank lines), and the This week time zone.

### Left out

- **QBR:** ESPN's, not reproducible.
- **Career average for each part:** the career caches carry totals, not air or after-the-catch EPA; that would need every season's play-by-play back to 1999 as a one-off backfill. The career line compares overall efficiency only.
- **Run splits by gap for backs:** the field map already covers gaps.

### Acceptance (measured on the built page)

Allen: total EPA +72.5, 2nd of 38; ANY/A 7.83, 5th; the parts add to passing +53.0 and rushing +19.5. Jefferson: total +22.3, 17th of 136; Week 4 did-not-play and Week 6 bye rows in place. No new text under 11px; no horizontal page scroll at 375; the two new panels checked at 1440, 1024, 768 and 375.

Size: M. Status: signed off 2026-11-01 and built as 1.5.0.

### Built (1.5.0)

- **Two-point tries.** nflverse's weekly EPA counts two-point attempts, which is where the mock's Other rows came from (Allen's −0.8 is one failed try; Michael Penix Jr.'s +3.2 is three good ones). The build gives them their own part, "Two-point tries", shown only when a player has one and left out of the rates and percentiles. With that, every qualifier's Other row is under 0.01 EPA, so the build check fails at 3 EPA as scoped but in practice watches for a regression.
- **Ranks** come from the site's own pool (the players Where he ranks grades), so a few ranks differ from the mock's play-count pool: Allen's success rate is T-12th (mock 13th), Jefferson's T-67th (mock 73rd). Review fix: spikes count as attempts in ANY/A, as Pro Football Reference counts them. The acceptance numbers hold: Allen +72.5, 2nd of 38; ANY/A 7.83, 5th (he has no spikes); passing +53.0 and rushing +19.5. Jefferson +22.3, 17th of 136.
- **Data:** `ep` = {u, sr, any (QBs), tot, sec: [[section, weekly total, other, [[part, EPA, per play, percentile]]]], rk, pc, gr: {cell: [n, EPA per play, completions]}}; `trk` = {tile key: [rank, tie]}; `injw` = {week: [designation, injury]}; league.json `grid` = {qb, tgt: {cell: [share, EPA per play]}}.

