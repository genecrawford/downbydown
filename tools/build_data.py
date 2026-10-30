"""Build the Down by Down data files from nflverse.

Usage:
  python3 build_data.py <pbp.csv.gz|-> <roster.csv> <games.csv> <teams.csv> <out_data_dir> [week]

  pbp may be "-" (or a missing file) before Week 1, when nflverse has no play-by-play for the
  season yet. The build then writes teams.json and schedule.json and an empty metrics.json so the
  site can show the Week 1 slate.

  week is optional. When omitted it is derived from the schedule: the first week of the season
  with at least one unplayed game, or the last week once everything is final.

Writes:
  teams.json      {ABBR: {name, nick, conf, div, c1, c2}}
  plays/{ABBR}.json  {season, team, byes, games:[...], keys:[...], plays:[[...]]}
  metrics.json    offense/defense metrics per team, league averages, ranks, through_week
  schedule.json   {season, current_week, weeks: {"1": [games], ...}} with status per game
  manifest.json   {season, week, through_week, built_at, files: {path: sha1}}

Runs nightly in GitHub Actions during the season; the WordPress plugin pulls what changed.
"""
import pandas as pd, json, math, sys, os, hashlib, datetime

pbp_path, roster_path, games_path, teams_path, out = sys.argv[1:6]
week_arg = int(sys.argv[6]) if len(sys.argv) > 6 else None
os.makedirs(os.path.join(out, 'plays'), exist_ok=True)

# ---------------------------------------------------------------- inputs
have_pbp = pbp_path != '-' and os.path.exists(pbp_path)
df = pd.read_csv(pbp_path, low_memory=False) if have_pbp else pd.DataFrame()
if have_pbp and df.empty:
    have_pbp = False

sched_all = pd.read_csv(games_path)
season = int(df.season.max()) if have_pbp else int(sched_all.season.max())
sched = sched_all[sched_all.season == season].copy()

# current week: first week with an unplayed game, else the last week
unplayed = sched[sched.home_score.isna()]
if week_arg:
    week = week_arg
elif len(unplayed):
    week = int(unplayed.week.min())
else:
    week = int(sched.week.max())
through_week = int(df[df.home_score.notna()].week.max()) if have_pbp else 0

_r = pd.read_csv(roster_path, low_memory=False).dropna(subset=['gsis_id'])
pos = dict(zip(_r.gsis_id, _r.position))

teams = pd.read_csv(teams_path)
active = set(sched.home_team) | set(sched.away_team)
teams = teams[teams.team_abbr.isin(active)]
T = {r.team_abbr: dict(name=r.team_name, nick=r.team_nick, conf=r.team_conf, div=r.team_division,
                       c1=r.team_color, c2=r.team_color2) for r in teams.itertuples()}
json.dump(T, open(f'{out}/teams.json', 'w'), separators=(',', ':'))


def c(x):
    if isinstance(x, float) and math.isnan(x): return None
    if hasattr(x, 'item'): x = x.item()
    if isinstance(x, float) and x.is_integer(): return int(x)
    return x


# ---------------------------------------------------------------- per-team plays
keys = ['g', 'q', 't', 'dn', 'togo', 'yl', 'pt', 'k', 'yds', 'epa', 'succ', 'air', 'yac', 'loc', 'len', 'gap', 'sg', 'td', 'fd', 'drv', 'sd', 'pl', 'desc']
for t in T:
    gl, rows = [], []
    if have_pbp:
        for gid, g in df[(df.home_team == t) | (df.away_team == t)].groupby('game_id'):
            last = g.iloc[-1]; home = last.home_team == t
            gl.append(dict(id=gid, week=int(last.week), opp=last.away_team if home else last.home_team, home=bool(home),
                           pf=int(last.total_home_score if home else last.total_away_score),
                           pa=int(last.total_away_score if home else last.total_home_score)))
        v = df[(df.posteam == t) & (df.play_type.isin(['pass', 'run']))]
        for _, r in v.iterrows():
            kind = 'run'
            if r.play_type == 'pass':
                kind = 'sack' if r.sack == 1 else ('scramble' if r.qb_scramble == 1 else ('int' if r.interception == 1 else ('comp' if r.complete_pass == 1 else 'inc')))
            elif r.qb_scramble == 1:
                kind = 'scramble'
            pl = r.receiver_player_name if r.play_type == 'pass' and kind not in ('sack', 'scramble') else (r.rusher_player_name if isinstance(r.rusher_player_name, str) else r.passer_player_name)
            rows.append([c(x) for x in [r.game_id, int(r.qtr), r.time, r.down, r.ydstogo, r.yardline_100, r.play_type, kind, r.yards_gained,
                                        round(r.epa, 3) if not math.isnan(r.epa) else None, r.success, r.air_yards, r.yards_after_catch,
                                        r.pass_location if r.play_type == 'pass' else r.run_location, r.pass_length, r.run_gap, int(r.shotgun),
                                        r.touchdown, r.first_down, r.drive, r.score_differential, pl, r.desc]])
    played_weeks = {x['week'] for x in gl}
    # weeks before the current one with no game: a team that has not played yet this week is not on bye
    byes = sorted(set(range(1, week)) - played_weeks)
    json.dump(dict(season=season, team=t, byes=byes, games=sorted(gl, key=lambda x: x['week']), keys=keys, plays=rows),
              open(f'{out}/plays/{t}.json', 'w'), separators=(',', ':'))

# ---------------------------------------------------------------- team metrics (offense and defense)
def clean(o):
    if isinstance(o, dict): return {k: clean(v) for k, v in o.items()}
    if isinstance(o, float): return None if o != o else round(o, 4)
    return o

if have_pbp:
    d = df[df.play_type.isin(['pass', 'run']) & df.epa.notna() & df.down.notna()].copy()
    d['rpos'] = d.receiver_player_id.map(lambda x: pos.get(x) if isinstance(x, str) else None)
    d['expl'] = ((d.play_type == 'pass') & (d.yards_gained >= 20)) | ((d.play_type == 'run') & (d.yards_gained >= 10))

    def metrics(x, per_team=1):
        p = x[x.play_type == 'pass']; ru = x[(x.play_type == 'run') & (x.qb_scramble != 1)]
        tg = p[p.receiver_player_id.notna()]; third = x[x.down == 3]; g = x.game_id.nunique() * per_team
        m = dict(epa=x.epa.mean(), sr=x.success.mean(), ypp=x.yards_gained.mean(), expl=x.expl.mean(), pass_epa=p.epa.mean(),
                 run_epa=ru.epa.mean(), ypc=ru.yards_gained.mean(), sack=p.sack.mean(),
                 third=(third.first_down.fillna(0) + third.touchdown.fillna(0)).clip(0, 1).mean(), passrate=(x.play_type == 'pass').mean())
        rz = list(x[x.yardline_100 <= 20].groupby(['game_id', 'drive']).groups.keys())
        tds = set(x[x.touchdown == 1].groupby(['game_id', 'drive']).groups.keys())
        m['rz_td'] = sum(k in tds for k in rz) / len(rz) if rz else None
        for P in ['WR', 'TE', 'RB']:
            t = tg[tg.rpos == P]; m[f'{P}_tgt_pg'] = len(t) / g if g else None; m[f'{P}_yds_pg'] = t.yards_gained.sum() / g if g else None; m[f'{P}_epa'] = t.epa.mean()
        m['zones'] = {f'{L}_{loc}': dict(n=int(len(z := p[(p.pass_length == L) & (p.pass_location == loc)])), share=len(z) / max(1, p.pass_length.notna().sum()), epa=z.epa.mean())
                      for L in ['short', 'deep'] for loc in ['left', 'middle', 'right']}
        m['runs'] = {loc: dict(n=int(len(z := ru[ru.run_location == loc])), ypc=z.yards_gained.mean(), epa=z.epa.mean()) for loc in ['left', 'middle', 'right']}
        m['games'] = x.game_id.nunique()
        return m

    off = {t: metrics(g) for t, g in d.groupby('posteam')}; dfn = {t: metrics(g) for t, g in d.groupby('defteam')}; lg = metrics(d, 2)
    flat = [k for k, v in lg.items() if isinstance(v, (int, float)) and k != 'games']
    ranks = {'off': {}, 'def': {}}
    for k in flat:
        hb = k != 'sack'
        for side, tab, rev in (('off', off, hb), ('def', dfn, not hb)):
            vals = sorted(((v[k], t) for t, v in tab.items() if v.get(k) is not None and v[k] == v[k]), reverse=rev)
            for i, (_, t) in enumerate(vals): ranks[side].setdefault(t, {})[k] = i + 1
    json.dump(clean(dict(season=season, off=off, defense=dfn, league=lg, rank=ranks, through_week=through_week)), open(f'{out}/metrics.json', 'w'), separators=(',', ':'))
else:
    json.dump(dict(season=season, off={}, defense={}, league={}, rank={'off': {}, 'def': {}}, through_week=0), open(f'{out}/metrics.json', 'w'))

# ---------------------------------------------------------------- schedule: every week of the season
weeks = {}
for r in sched.sort_values(['week', 'gameday', 'gametime']).itertuples():
    g = dict(id=r.game_id, week=int(r.week), day=r.weekday, date=r.gameday, time=r.gametime, away=r.away_team, home=r.home_team,
             away_score=c(r.away_score), home_score=c(r.home_score), spread=c(r.spread_line), total=c(r.total_line),
             stadium=c(r.stadium), game_type=r.game_type, status='final' if not (isinstance(r.home_score, float) and math.isnan(r.home_score)) else 'upcoming')
    weeks.setdefault(str(int(r.week)), []).append(g)
json.dump(dict(season=season, current_week=week, through_week=through_week, weeks=weeks), open(f'{out}/schedule.json', 'w'), separators=(',', ':'))

# ---------------------------------------------------------------- manifest
files = {}
for root, _, names in os.walk(out):
    for n in sorted(names):
        if n == 'manifest.json' or not n.endswith('.json'): continue
        p = os.path.join(root, n); rel = os.path.relpath(p, out).replace(os.sep, '/')
        files[rel] = hashlib.sha1(open(p, 'rb').read()).hexdigest()
json.dump(dict(season=season, week=week, through_week=through_week, built_at=datetime.datetime.now(datetime.timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ'), files=files),
          open(f'{out}/manifest.json', 'w'), indent=1)
print('ok', len(T), 'teams, season', season, 'week', week, 'through', through_week, 'pbp' if have_pbp else 'no pbp yet')
