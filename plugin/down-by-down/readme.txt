=== Down by Down ===
Contributors: periodthree
Tags: nfl, football, analytics, play-by-play
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 2.10.0
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

= 2.10.0 =
* The Tuesday email (SCOPE §48). Each week post, as it publishes, is emailed to a Brevo list: the whole post with every style inline, a 600px column that narrows on a phone, links back to the site at the end of its sections and a button to the week page. It sends once (the Brevo campaign is stored on the post); a post published more than a day after its date never sends, and the post's "Tuesday email" box has "Don't email this one" and a test button. A failed send retries every 10 minutes for an hour, then emails the site owner.
* Signups: the WPForms signup form hands each address to Brevo's double opt-in. Brevo sends the confirmation (the settings page gives its HTML), and the click lands on the newest week post with a "You're in" note. WordPress keeps no addresses.
* Settings › Down by Down › Tuesday email: the Brevo key, list, confirmation template, sender, reply-to, postal address, form, a test button and the send log. Off until switched on.

= 2.9.1 =
* Team news keeps what it has. Each fetch now adds to the stories already stored from the last 7 days instead of replacing a feed's stories. A feed that fails, or a CDN node that hands back an hours-old copy of a feed (seen live on Daily Norseman on 2026-11-03), can no longer take newer stories off the panel. A source switched off still leaves at the next fetch.
* Down by Down › Team news flags a feed that sent an older copy of itself.

= 2.9.0 =
* Team news (SCOPE §49): each team's players page gets a news panel with the last 7 days of stories in two columns. One is the team's own site, its SB Nation blog and The Athletic. The other is around the league: ProFootballTalk, CBS Sports, ESPN, Yahoo Sports, Sporting News, NFL Trade Rumors and The Athletic. Each row is the headline (opening the article in a new tab), the source, the time and any player named, linked to his page. The Athletic's links say "Subscriber". The first five in each column hold no more than two from one source; "Show more" opens up to 20.
* The offense and defense pages show the team's newest story in one line under the description, linking to the panel.
* How it runs: every 6 hours, a background pass reads the 102 feeds 8 at a time. It keeps only the stories that belong to a team, matched by the team's name or a player the headline names. College teams that share a nickname are excluded, stories naming four or more teams go to none, and duplicates show once. It stores one small list per team that isn't autoloaded. Raw feeds are never stored, and a feed that fails keeps its last stories.
* Down by Down › Team news: last and next fetch, any feed that failed or has nothing recent, a switch for each source, and Fetch news now. The first fetch runs about 90 seconds after the update.
* The weekly post's scores table stacks below 760px wide instead of 560px, so it no longer squeezes in the post column on tablets.

= 2.8.0 =
* Kickers (SCOPE §47): every kicker gets a page at his player address, built from players/kickers.json: eight tiles ranked among qualifying kickers (a try for every game his team has played), the short version, a Scorecard, his makes by distance against an average kicker's from the same spots (a league curve fitted to 2023–2025), every try drawn on the field at the spot it was held (sideways on wide screens, upright on phones), the kicks that moved his team's win chance most, a game log with the conditions, and his career since 1999.
* The swing lists on the game page and the week in review tag each play with the team that had the ball; the bar names the team the play helped. A kicker's name in a play links to his page.
* The box score's kicking line lists every kicker in the game, linked, with misses and their distances, extra points and points.
* Kickers on Leaders (a Kickers tab of six boards and a K fantasy board), in fantasy (ESPN standard scoring, the same in every format; the matchups grid gains a K column), on My team (a K slot), the Players index (a K filter), each team's players page and lineup, compare (kicker against kicker) and the share card.
* App pages center in the header's 1240px column, with its side padding, on full-width Divi rows.
* The week-in-review posts' card images reach Divi's Blog grid whole instead of its 400 × 250 crop.

= 2.7.0 =
* The weekly post (SCOPE §46): each finished week becomes a post in a "Week in review" category, written from the week's data (the scores, the swing and the play, the best offense and defense, players of the week, fantasy, who is rising and next week's game of the week) and scheduled for the Tuesday after the week at 8:00 AM Eastern. A later sync refreshes a scheduled post's numbers; an edited, published or deleted post is never touched again. The week's share card is the featured image. Settings › Weekly post: on/off, the signup form under each post, every week's post and state, and "Write past weeks" to backfill the season. The week page links to its write-up.
* The home's featured player and week highlights stay on last week until half of the new week's games are final, so a Thursday night game alone doesn't take over the home.
* Kickoff chances round the way the data build does (22.5% reads 22% everywhere, as in the tags).
* Search: the home page (a theme page) gets a description (Settings), share tags with the hub's card and the site's name as WebSite data; the sitemap adds the week in review and leaders pages and drops the author page; robots.txt loses Crawl-delay; Divi's viewport no longer blocks pinch-zoom on phones.

= 2.6.0 =
* `[dbd_block type="newsletter-preview"]`: the latest finished week as the Tuesday email (subject, headline, the swing, the play, the risers, a button to the week in review), for the home's newsletter section.
* Every block keeps something to show across a season's edges: the matchup block shows the last final game with its score once no game is left to play (the Super Bowl until the next opener); the leader boards step down to the Leaders page's lower minimums when too few players qualify early in a season; the player card and the risers fall back to the week's most-targeted players in a new season's first week, and never show last season's risers; the games line reads "final" on a one-game week.

= 2.5.0 =
* `[dbd_block type="fantasy"]`: the softest defense at each position (QB, RB, WR, TE), the most half-PPR points allowed a game, each linked to that defense.
* `[dbd_block type="leaders"]`: season leader boards, qualified players only as on the Leaders page; `boards="wr.tshare,wr.epat,qb.epadb"` by default, `"ppr"` for the PPR points leaders, `top` 3 to 10 (default 5).
* `[dbd_block type="teams"]`: all 32 teams by division, best record first, each linked to its page. All three for the home's fantasy, leaders and teams sections.

= 2.4.0 =
* `[dbd_block type="player-card"]`: a player's share card linked to his page, the week's biggest riser in target share by default (`player="id"` pins one).
* `[dbd_block type="movers"]`: the biggest jumps in target share in the latest finished week, named in its heading, his share before that week and that week's (`count`, 1 to 5, default 4). Both for the home's players section.

= 2.3.0 =
* `[dbd_block type="matchup"]`: the game of the week (picked the way the hub picks it, with next week's games standing in once a week is all final), its biggest edge in a sentence, and a four-row offense vs defense with the ranks and a bar toward the side with the edge, for the home's matchup section.

= 2.2.0 =
* `[dbd_block type="week-highlights"]`: the latest week in review in three cards (the play of the week, the swing of the week, the upsets with each winner's chance at kickoff and the wildest other game), for the home's second section.

= 2.1.0 =
* Live blocks for pages built in the theme: `[dbd_block type="hero-map"]` (the week's most-targeted player, his line and every target on the field; `player="…"` pins one), `[dbd_block type="games"]` (this week's games as score tiles, the next one flagged) and `[dbd_block type="counts"]` (snaps, games, players, offenses and defenses). Each draws this week's data, is cached until the next data build, and takes the surrounding text color for its headings, so it sits on any background.

= 2.0.3 =
* An old front-page link naming a season the site doesn't hold (/?season=1999&team=MIN) stays on the home page instead of forwarding to this season's page.

= 2.0.2 =
* The app can live on its own page (for example /hub/) while its pages keep their addresses at the site root: /team/min/, /player/justin-jefferson/, /2025/. The front page is then free for a custom home. A setting, on by default.
* Links that carry the app page's address (/hub/team/min/) forward to the root address. Old /?team= links and /?card= share images on the front page still land.
* The app page is the page last saved with [down_by_down]. Visiting a page no longer changes it, so two pages holding the shortcode can't flip the site's addresses back and forth.

= 2.0.1 =
* Playoff games and weeks are titled by round (Wild Card, Divisional, Conference, Super Bowl) instead of Week 19 to 22.

= 2.0.0 =
* The season archive: a finished season stays browsable at /2025/ (or ?season=2025), every page reading that season's files, with a season switcher in the top bar and an archive banner. Live parts (My team, this week, injury and depth labels) stay with the current season.
* The season page: the champion, the playoffs round by round, final standings, who led and every week in review. It is a past season's hub, and this season's once the Super Bowl is final.
* The sync pulls archived seasons from the repo's data/archive/{season}/, a minute at a time; a Seasons box in admin lists them.

= 1.19.2 =
* On a phone the play explorer's situation tables drop their bars and the success column, and a player's Teammates names wrap, so neither scrolls sideways.

= 1.19.1 =
* The skeleton cross-fade runs (1.19.0 threw a script error before it), and the defense's Situations table fits a phone.

= 1.19.0 =
* Readable URLs: /team/min/, /player/justin-jefferson/, /game/2026-week-8-vikings-at-lions/ and the rest. The old ?team= and ?player= links redirect to them. A Readable URLs setting turns them off; plain-permalink sites keep query strings.
* Share cards for every page: a game card (before kickoff, then the final with win probability and the leaders), a team card, and a page card for the hub, the week in review and the index pages.
* Old share cards are deleted after each sync; a Share cards box in admin shows the cache and clears it.
* Skeletons in the shape of what is loading, cross-faded into the content, on the team explorer, the player map, compare and the hub's My team card.
* The game log remembers Default, Advanced or Traditional. On a phone, head to head, the defense's player tables and situations, and a quarterback's career no longer scroll sideways.

= 1.18.1 =
* A final whose weekly player stats nflverse hasn't posted yet now reads "Stats not posted yet" on the player page, the fantasy strip and the My team card, instead of "Did not play".

= 1.18.0 =
* Compare, finished (SCOPE §42): five new sections side by side in the slot colors. This week (each player's next game and how the defense ranks in points allowed at his position), Playstyle (the eight traits with each player's tags), Where the EPA comes from (one diverging bar per player and a table of parts), The shape of their seasons (the four trend metrics, trailing three games over the league's middle half) and Careers (yards a game season by season, and a summary table). Charts keep their labels at 11px at every width.

= 1.17.1 =
* The hub's My team card script always loads. Right after an update the player list it reads may not have synced yet; a page cached in that window (Cloudflare keeps it ten minutes) left the card on "Build my team" even with a team saved.

= 1.17.0 =
* The hub refresh (SCOPE §41): under the score strip, the week so far (three lines and the biggest swings, linking to the week in review), a card for every game still to play (line, offense and defense ranks, the biggest edge, the softest fantasy matchup), a players row (movers, fantasy leaders with the format switch, and your My team lineup with this week's opponents), and a Playoff picture tab beside the division standings.
* Data: players/mini.json (name, team, position, number and photo for each indexed player) for the My team card; the sync allowlist gains it.

= 1.16.0 =
* The week in review (SCOPE §40): a new page, ?view=week&week=N, for every week with a final game. The short version (upset, comeback, wildest game, best offense and defense, player of the week), a scoreboard card per game with its win-probability line and tags, the week's biggest swings and best plays by EPA, every offense and defense against its usual, the best players by position and the fantasy leaders, and that week's movers. The matchups page links to it.
* Data: week/w{N}.json per week, with each complete week's movers kept; the sync allowlist gains week/w{N}.
* The movers panel is shared between the Players page and the week in review; its rows now get their team accent color.
* Compare: the week-by-week tables and the receivers' season totals came back. Since 1.14.0 the Scorecard loop reused the variable that picks which stats a pool shows, so those panels rendered empty.
* Movers keep the team a player played for that week, so a traded player's earlier weeks show the old team.

= 1.15.2 =
* Box scores for games nflverse's weekly stats haven't posted yet (they can trail a day) are counted from the play-by-play instead of showing empty with "no sacks or interceptions". The note under the box says which source it used; the weekly lines replace them with the next build.

= 1.15.1 =
* The Offense | Defense switch on team pages renamed its class (dbd-sideswitch). It shared dbd-side with the site's Divi sidebar column, so the sidebar's sticky, full-height column styles landed on the switch.
* Data build: play-by-play and nflverse's weekly stats can land a day apart. The EPA split, snap-only zero-point weeks and game totals now count only the games both have, so a build mid-Sunday no longer half-counts a game.

= 1.15.0 =
* The defensive half (SCOPE §39): every team page has an Offense | Defense switch, and the Defense tab (?team=MIN&side=def) is as deep as a player page. Eight hero tiles ranked among 32 and a short version; a defense Scorecard against all 32 and a game-by-game chart of EPA allowed against what each offense usually manages; where offenses find room (a depth-by-direction pass grid, seven run lanes, the position ranks the player pages quote with fantasy points allowed and the players who hurt them most, and situations); how they play (pressure and the blitz, the box and the run, what offenses try, discipline and tackling, from FTN and PFR); the defenders (the front, linebackers and the secondary, with PFR's pressures and coverage); and the play explorer on every snap they defended.
* Leaders gains a Defenses tab: the 32 defenses on each Scorecard stat.
* The defense chip on a player's This week panel, and the DEF rank on game pages and the hub, link to that defense's tab.
* The build adds PFR's advanced defense file and writes defense/{TEAM}.json, defense/league.json and plays/def/{TEAM}.json; the sync pulls them.

= 1.14.0 =
* Wide receivers and tight ends grade in separate pools (SCOPE §38), the way fantasy leagues rank them. Player pages compare a wide receiver with wide receivers and a tight end with tight ends: the Scorecard, the tile ranks, Playstyle percentiles, the EPA split and the trend yardsticks. Leaders reads each position's own pool; Compare and Players like him stay within the position; My team compares wide receivers and tight ends separately; the share card grades among his position.
* The build writes per-position pools to league.json and leaders.json, and keeps the combined receivers pool so an older plugin keeps working until this one is installed.

= 1.13.1 =
* A new version syncs on its own about two minutes after it is installed, so data files it newly knows about arrive without pressing Sync now. The sync the nightly data build triggers can run while the old version is still installed, and hosts can keep running the old code for a minute after an upload; this covers both. Settings shows whether the installed version has synced.
* Leaders: wide receivers and tight ends are separate tabs, each ranked only against its own position, the way fantasy leagues treat them. Their full boards (wr.g.tshare, te.fp.deep) rank within the position; 1.13.0's combined receivers links open the wide receivers boards.

= 1.13.0 =
* Leaders (SCOPE §37): a new page, ?view=leaders, with a board for every Scorecard stat, every Playstyle trait and the fantasy numbers. Four tabs: Fantasy (points a game at each position, season points, boom weeks, highest floor, in the format you pick), Receivers and tight ends (with an All / WR / TE switch), Backs and Quarterbacks. Each board shows the top ten and links to the full ranked list, which filters by position and minimum usage and renumbers as you go. Lower-is-better stats rank lowest first.
* The build writes players/leaders.json, and the sync pulls it.
* My team links use an encoded comma, so adding or removing a player no longer takes WordPress's extra redirect.

= 1.12.1 =
* The Players index adds a My team button at the end of every row: a round + that becomes a green check once he is on your team. It stays pinned to the right edge when the table scrolls sideways on a phone, and it works on rows the filters redraw.
* My team: every empty lineup slot has a round + that opens a search right in the row, limited to the positions that slot takes (FLEX takes backs, receivers and tight ends). Escape, or leaving it empty, puts the + back. The empty My team page now shows the empty lineup, so each slot can be filled from the start.

= 1.12.0 =
* My team (SCOPE §36): a new page, ?view=myteam, that lays out the players you follow as a fantasy lineup (QB, 2 RB, 2 WR, TE, FLEX, then the bench). Each row has this week's opponent and kickoff with the opponent's rank in points allowed to his position, the injury report line, a six-week snap sparkline, and points a game with his last three games. A read and four numbers sum up the week: starters' points a week, byes, players ruled out and soft matchups.
* The lineup picks itself: each slot takes your highest scorer by points a game in the chosen format, skipping byes and players ruled out.
* The team lives in the address bar (?myteam=…), so the link is the share link, and it is saved on the device. Opening someone else's link shows their team without replacing yours, with a choice to save it. Add players by search; remove, copy the link or clear the team on the page.
* Player pages add a "+ My team" button to the side card.
* The page is noindex. No new data files.

= 1.11.1 =
* Player pages (SCOPE §35): the column beside the headshot becomes one card. The team and his snap share sit on top, Compare and Share card are two equal buttons, and Players like him is a list with a Compare link on each row.
* The Playstyle pane puts the read across the top and the eight traits in two columns of four, each with a header, so there is no empty block beside a short read. The tags become larger cards with an arrow for which end of the league he sits at and the stat behind each one.

= 1.11.0 =
* The fantasy lens (SCOPE §34): standard, half-PPR and PPR points for every quarterback, back, receiver and tight end. A Standard / Half PPR / PPR switch, remembered on the device (half PPR until you pick), sets the format on every page.
* Player pages add a Fantasy panel after the Scorecard: a read with points a game and the position rank, his usual range, booms and busts, and how many points his next opponent allows at his position; four numbers; and every week of the season as a bar, green on a boom week and red on a bust, against the starter line (QB12, RB24, WR24, TE12). The short version gains a Fantasy line.
* The Players index adds a sortable Fpts/g column with the position rank, and its filters are regrouped: search, position and fantasy scoring on one line, the team logos on their own line below.
* The Matchups page adds Fantasy matchups for the games left in the week: each defense's rank in points allowed to QBs, RBs, WRs and TEs, soft matchups in green and tough ones in red.
* Compare adds a Fantasy panel: the four numbers side by side and the weeks grouped in each player's color.
* The build adds nflverse's fantasy points to every weekly line, a fantasy block to each player file, points a game to the index, and writes fantasy.json; the sync pulls it.
* Tables sit flush in their panels: the theme's 1px border around every table in a post no longer shows on the site's tables, and every table's first and last column is inset 10px so text never touches an edge.

= 1.10.0 =
* The series (SCOPE §33): every game page adds the two teams' series since 1999, read from the away team's side. A read with the series leader, the streak and the last meeting (date, stadium, score, starting quarterbacks and coaches); home and road records, most points and biggest win each way, playoff meetings when there are any; the last five meetings, linked when they were this season; and both teams' records this season side by side.
* Team players pages carry the record broken down under the title: overall, division, conference, home, away and the streak.
* The build writes series.json, and the sync pulls it.

= 1.9.0 =
* Game page (SCOPE §32). Takeaways are now Keys to the game, and an upcoming game adds Players to watch: each team's quarterback, lead back and two most-targeted receivers, and its defense's two playmakers, with league ranks.
* The lineups: on an upcoming game, a play-art board of the offense the page is set to against the other team's defense, starters from the depth charts, names on the board and seasons in the tooltips. A phone gets numbers on the board and a list below.
* Box score: a final game lists each team's passing, rushing and receiving lines with snap share and share of the carries and targets, a read naming each team's best player by EPA, and lines for defense (sacks, interceptions) and kicking.
* Team players: a team's players is its own page now, with the lineup (offense, defense, special teams), the roster grouped by position, and that team's movers only. The league index keeps the movers.
* Tabbed panels are handled site-wide, and the tab pill is measured again once the page and its fonts have loaded.
* The build writes lineups.json and box/w{N}.json, and the sync pulls them.

= 1.8.1 =
* Fixes from the 1.8.0 live check. The player page's scorecard is one column below about 940px (beside the site's sidebar on a laptop its two columns ran past the panel). Methods notes read in the 12px body face instead of 11px monospace. Compare's values join the type scale. Notes the page scripts draw after load fold on a short timer rather than an animation frame, which pauses in a tab that is not showing.

= 1.8.0 =
* Readability across the site (SCOPE §31, the pass after the player page). Methods notes fold under "How this is measured" on every page, including the notes the team explorer and the player page draw after load.
* Players index: 50 rows at a time with "Show 50 more" and "Show all"; sorting, positions, teams and search still run over every player, and the page still carries every row for search engines.
* Team explorer: Tendencies sets its charts two to a row beside the play detail rail (they were one to a row, about two screens).
* Hub: each upcoming game's card carries its line and total, and the slate table that repeated the cards moves to Matchups, with a link from the hub.

= 1.7.0 =
* Readability and scanning, the player page (SCOPE §31). The short version opens the page: one line per band from the reads the page already writes, each linked to its band.
* Scorecard: Where he ranks and Season stats were the same stats twice; one table now holds value, rank, the league bar and percentile, with bars for success rate, ANY/A and total EPA too.
* How he's used and Week by week are one panel each with tabs (Playstyle, Where his EPA comes from, Depth by direction, On the field, Situations; This week, Game log, The trend, Defenses faced). Every pane is still in the page.
* Methods notes fold under "How this is measured". The connection shows its core columns with the rest behind "All columns".
* A real type scale across the site: numbers in 13px IBM Plex Sans with tabular figures instead of 11px monospace, reads at 15px, table headers in sentence case, darker small print. Phones get one sideways-scrolling row for the jump nav and the tabs.
* Fixes: two labels were 10px; the depth bands' text ran out of its column.
* Fix: on phones, an overtime score on the last snap (a walk-off) hung half outside the win probability chart. It now sits just left of its moment.

= 1.6.0 =
* Win probability on the game page (SCOPE §30). Every final game gains How it played out: a generated read (who won and from how low, the turning point, the biggest swing), four numbers (the favorite at kickoff, the winner's low point, swings past 50%, the biggest swing), a chart of each side's chance across the game with every score, the five biggest swings, the turning point and the low point marked (hover or drag for the clock, the chance and the score), and the plays that swung it.
* Overtime: nflverse's model does not hold together under the current overtime rules, so the chart covers regulation (a tied game ends regulation at 50%) and overtime shows its scoring plays and the result.
* Takeaways on a final game are labeled as season numbers, not this game alone.
* The build writes wp/w{N}.json per week, and the sync pulls it.
* Fix: the team picker's "Pick up where you left off" box doubled the name ("New York GiantsGiants"): it read both the full and the phone-size name from the standings link.
* Fix: on phones the red DNP tag in Who he's faced covered the opponent label ("VS MIA"). It now sits on the cell's top border.

= 1.5.0 =
* nfelo parity on the player page (SCOPE §29). Each hero tile now carries its league rank ("5th of 38", green inside the top 10, ties as T-) against the same pool Where he ranks uses; a player outside the pool shows none. A quarterback's Snap share tile becomes ANY/A, since the hero badge already shows snap share.
* Season stats gain success rate, ANY/A (quarterbacks) and total EPA, each ranked.
* New panel, Where his EPA comes from: the season's EPA split by what happened (air yards, after the catch, incompletions, interceptions, sacks, scrambles, runs, two-point tries), each part's per-play rate and league percentile, a one-sentence read, and this season against his career with a bar per season. The parts add up to the totals on the page.
* New panel, Depth by direction: the 4 × 3 grid of throws or targets (behind the line, short, intermediate, deep; left, middle, right) against the league, with How often and EPA per throw views. Backs get it with 15+ targets.
* The game log covers every week: the bye and the games he missed sit in place (with that week's injury designation when the report had one), then this week's game, with the rest of the schedule behind a button. Sorting a stat column sets those rows aside; sorting by week brings them back.
* Fixes: a quarterback's TD / INT tile read "0 first downs" (the data never carried passing first downs; it does now), and the This week kickoff badge printed the site's GMT time instead of Eastern.

= 1.4.3 =
* The team logo in the player hero sits in a bigger slot: 64px square instead of 64 by 40, so the logo draws at about 61px for most teams (49px in 1.4.2, 38px in 1.4.1), and the snap-share badge and buttons below move down to make room rather than being overlapped. 48px on phones.

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
