# Down by Down

**downbydown.com** — how every NFL offense actually plays, drawn on the field from public play-by-play data.

A free site by [Period Three](https://period-three.com). Not affiliated with the NFL or any team.

## What's here

| Path | What it is |
|---|---|
| `plugin/down-by-down/` | The WordPress plugin. One shortcode, `[down_by_down]`, turns a page into the app (hub, matchups, team explorer). |
| `tools/build_data.py` | Turns four nflverse files into the JSON the plugin reads. |
| `data/` | The built JSON, committed nightly by the Action during the season. The site pulls from here. |
| `.github/workflows/build-data.yml` | The nightly build. |
| `BRIEF.md`, `SCOPE.md` | Project brief and the working scope document. |
| `mockup-v5/` | The approved design mock (earlier rounds in `mockup/` through `mockup-v4/`). |

## Data flow

1. nflverse publishes play-by-play, rosters and the schedule every night in season.
2. The Action downloads them, runs `build_data.py`, and commits `data/` if anything changed.
3. The Action calls the site's sync endpoint. The plugin reads `data/manifest.json`, downloads only the files whose hash changed into `wp-content/uploads/down-by-down/`, and swaps them in. The plugin also pulls once a day on its own as a fallback.

Data: [nflverse](https://github.com/nflverse/nflverse-data), under its licence.
