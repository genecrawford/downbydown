"""Build the Down by Down data files from nflverse.

Usage:
  python3 build_data.py <pbp.csv.gz|-> <roster.csv> <games.csv> <teams.csv> <out_data_dir> [week]

  Optional player-layer inputs come from environment variables (each skipped when unset or missing):
    DBD_STATS  stats_player_week_{season}.csv     DBD_SNAPS  snap_counts_{season}.csv
    DBD_PFR    advstats_week_rec_{season}.csv     DBD_INJ    injuries_{season}.csv
    DBD_DEPTH  depth_charts_{season}.csv          DBD_FTN    ftn_charting_{season}.csv

  pbp may be "-" (or a missing file) before Week 1, when nflverse has no play-by-play for the
  season yet. The build then writes teams.json and schedule.json and an empty metrics.json so the
  site can show the Week 1 slate.

  week is optional. When omitted it is derived from the schedule: the first week of the season
  with at least one unplayed game, or the last week once everything is final.

Writes:
  teams.json      {ABBR: {name, nick, conf, div, c1, c2}}
  plays/{ABBR}.json  {season, team, byes, games:[...], keys:[...], plays:[[...]]}  (pid = ball handler's gsis_id; FTN flags when available)
  players/{ABBR}.json  roster rows for QB/RB/FB/WR/TE with weekly stat lines, snap share, PFR extras, injury line, depth slot
  players/index.json   one row per player league-wide for the Players index and search
  metrics.json    offense/defense metrics per team, league averages, ranks, through_week
  schedule.json   {season, current_week, weeks: {"1": [games], ...}} with status per game
  manifest.json   {season, week, through_week, built_at, files: {path: sha1}}

Runs nightly in GitHub Actions during the season; the WordPress plugin pulls what changed.
"""
import pandas as pd, json, math, sys, os, hashlib, datetime

pbp_path, roster_path, games_path, teams_path, out = sys.argv[1:6]
week_arg = int(sys.argv[6]) if len(sys.argv) > 6 else None
os.makedirs(os.path.join(out, 'plays'), exist_ok=True)
os.makedirs(os.path.join(out, 'players'), exist_ok=True)
env = lambda k: (os.environ.get(k) or None) if os.environ.get(k) and os.path.exists(os.environ.get(k)) else None

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

# FTN charting flags keyed by (game_id, play_id); empty when the file is not there
ftn = {}
if have_pbp and env('DBD_FTN'):
    _f = pd.read_csv(env('DBD_FTN'), low_memory=False)
    for x in _f.itertuples():
        ftn[(x.nflverse_game_id, int(x.nflverse_play_id))] = [int(x.is_play_action == 1), int(x.is_screen_pass == 1), int(x.is_rpo == 1), int(x.is_motion == 1), int(x.n_blitzers > 0), int(x.is_contested_ball == 1), int(x.is_drop == 1)]

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
keys = ['g', 'q', 't', 'dn', 'togo', 'yl', 'pt', 'k', 'yds', 'epa', 'succ', 'air', 'yac', 'loc', 'len', 'gap', 'sg', 'td', 'fd', 'drv', 'sd', 'pl', 'desc', 'pos', 'pid', 'pa', 'scr', 'rpo', 'mot', 'blz', 'cont', 'drop']
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
            pid = r.receiver_player_id if r.play_type == 'pass' and kind not in ('sack', 'scramble') else (r.rusher_player_id if isinstance(r.rusher_player_id, str) else r.passer_player_id)
            ppos = pos.get(pid) if isinstance(pid, str) else None
            fl = ftn.get((r.game_id, int(r.play_id)), [None] * 7)
            rows.append([c(x) for x in [r.game_id, int(r.qtr), r.time, r.down, r.ydstogo, r.yardline_100, r.play_type, kind, r.yards_gained,
                                        round(r.epa, 3) if not math.isnan(r.epa) else None, r.success, r.air_yards, r.yards_after_catch,
                                        r.pass_location if r.play_type == 'pass' else r.run_location, r.pass_length, r.run_gap, int(r.shotgun),
                                        r.touchdown, r.first_down, r.drive, r.score_differential, pl, r.desc, ppos, pid if isinstance(pid, str) else None] + fl])
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

    # league pass rates by situation: the reference lines on the explorer's Tendencies tab
    def prate(x):
        return float((x.play_type == 'pass').mean()) if len(x) else None
    buckets = {
        '1st & 10+': (d.down == 1) & (d.ydstogo >= 10), '1st & short': (d.down == 1) & (d.ydstogo < 10),
        '2nd & 7+': (d.down == 2) & (d.ydstogo >= 7), '2nd & 3-6': (d.down == 2) & (d.ydstogo >= 3) & (d.ydstogo < 7), '2nd & 1-2': (d.down == 2) & (d.ydstogo < 3),
        '3rd & 7+': (d.down == 3) & (d.ydstogo >= 7), '3rd & 3-6': (d.down == 3) & (d.ydstogo >= 3) & (d.ydstogo < 7), '3rd & 1-2': (d.down == 3) & (d.ydstogo < 3), '4th down': d.down == 4,
    }
    lg['tend'] = {
        'passrate': prate(d),
        'dd': {k: prate(d[m]) for k, m in buckets.items()},
        'sg': {str(dn): {'1': prate(d[(d.down == dn) & (d.shotgun == 1)]), '0': prate(d[(d.down == dn) & (d.shotgun == 0)])} for dn in (1, 2, 3, 4)},
        'state': {st: {str(q): prate(d[m & (d.qtr == q)]) for q in (1, 2, 3, 4)} for st, m in (('lead', d.score_differential > 0), ('tied', d.score_differential == 0), ('trail', d.score_differential < 0))},
    }
    flat = [k for k, v in lg.items() if isinstance(v, (int, float)) and k not in ('games',)]
    ranks = {'off': {}, 'def': {}}
    for k in flat:
        hb = k != 'sack'
        for side, tab, rev in (('off', off, hb), ('def', dfn, not hb)):
            vals = sorted(((v[k], t) for t, v in tab.items() if v.get(k) is not None and v[k] == v[k]), reverse=rev)
            for i, (_, t) in enumerate(vals): ranks[side].setdefault(t, {})[k] = i + 1
    json.dump(clean(dict(season=season, off=off, defense=dfn, league=lg, rank=ranks, through_week=through_week)), open(f'{out}/metrics.json', 'w'), separators=(',', ':'))
else:
    json.dump(dict(season=season, off={}, defense={}, league={}, rank={'off': {}, 'def': {}}, through_week=0), open(f'{out}/metrics.json', 'w'))


# ---------------------------------------------------------------- player layer: players/{TEAM}.json + players/index.json
def _num(v, d=0):
    try:
        f = float(v); return d if f != f else f
    except (TypeError, ValueError):
        return d

def _age(b):
    try:
        y, m, dd = map(int, str(b)[:10].split('-')); t = datetime.date.today(); return t.year - y - ((t.month, t.day) < (m, dd))
    except Exception:
        return None

def _ht(h):
    try:
        h = int(float(h)); return f"{h // 12}'{h % 12}\""
    except Exception:
        return None

def _draft(r):
    try:
        n = int(float(r.draft_number)); return f"Round {(n - 1) // 32 + 1}, pick {n} ({c(r.entry_year)})"
    except Exception:
        return f"Undrafted ({c(r.entry_year)})" if c(r.entry_year) else 'Undrafted'

SKILL = ('QB', 'RB', 'FB', 'WR', 'TE')
WEEK_FIELDS = ['week', 'opponent_team', 'targets', 'receptions', 'receiving_yards', 'receiving_tds', 'receiving_air_yards', 'receiving_yards_after_catch', 'receiving_epa', 'receiving_first_downs', 'target_share', 'air_yards_share', 'wopr',
               'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa', 'rushing_first_downs', 'attempts', 'completions', 'passing_yards', 'passing_tds', 'passing_interceptions', 'passing_epa', 'passing_cpoe', 'sacks_suffered', 'passing_air_yards', 'receiving_20', 'rushing_10']
_rw = _r[_r.position.isin(SKILL)].copy()
if 'week' in _rw.columns:
    _rw = _rw[_rw.week == _rw.week.max()]
stats = pd.read_csv(env('DBD_STATS'), low_memory=False) if env('DBD_STATS') else pd.DataFrame()
if len(stats) and 'season_type' in stats.columns: stats = stats[stats.season_type == 'REG']
snaps = pd.read_csv(env('DBD_SNAPS'), low_memory=False) if env('DBD_SNAPS') else pd.DataFrame()
pfr = pd.read_csv(env('DBD_PFR'), low_memory=False) if env('DBD_PFR') else pd.DataFrame()
inj = pd.read_csv(env('DBD_INJ'), low_memory=False) if env('DBD_INJ') else pd.DataFrame()
depth = pd.read_csv(env('DBD_DEPTH'), low_memory=False, usecols=['dt', 'team', 'gsis_id', 'pos_grp', 'pos_abb', 'pos_rank']) if env('DBD_DEPTH') else pd.DataFrame()
if len(depth): depth = depth[depth.pos_abb.isin(SKILL)]
index_rows = []
for t in T:
    roster = _rw[_rw.team == t]
    st_t = stats[stats.team == t] if len(stats) else stats
    sn_t = snaps[snaps.team == t] if len(snaps) else snaps
    pf_t = pfr[pfr.team == t] if len(pfr) else pfr
    in_t = inj[inj.team == t] if len(inj) else inj
    if len(in_t): in_t = in_t[in_t.week == in_t.week.max()]
    dp_t = depth[depth.team == t] if len(depth) else depth
    if len(dp_t): dp_t = dp_t[dp_t.dt == dp_t.dt.max()]
    out_players = []
    for r in roster.itertuples():
        fn = r.football_name if isinstance(getattr(r, 'football_name', None), str) and r.football_name else r.first_name
        p = dict(id=r.gsis_id, name=r.full_name, short=f"{str(fn)[:1]}.{r.last_name}", pos=r.position, num=c(r.jersey_number), age=_age(r.birth_date), ht=_ht(r.height), wt=c(r.weight),
                 col=c(r.college), exp=int(_num(r.years_exp)), draft=_draft(r), status=c(r.status), weeks=[], snaps={}, pfr={}, inj=None, depth=None)
        if len(st_t):
            for w in st_t[st_t.player_id == r.gsis_id].sort_values('week').itertuples():
                p['weeks'].append({k: (getattr(w, k) if k == 'opponent_team' else round(_num(getattr(w, k, 0)), 4)) for k in WEEK_FIELDS if k in st_t.columns})
        if len(sn_t):
            for w in sn_t[sn_t.player == r.full_name].itertuples():
                p['snaps'][str(int(w.week))] = int(round(_num(w.offense_pct) * 100))
        if len(pf_t):
            for w in pf_t[pf_t.pfr_player_name == r.full_name].itertuples():
                p['pfr'][str(int(w.week))] = dict(drop=int(_num(w.receiving_drop)), bt=int(_num(w.receiving_broken_tackles)), rbt=int(_num(w.rushing_broken_tackles)), rat=c(w.receiving_rat) if _num(w.receiving_rat, None) is not None else None)
        if len(in_t):
            m = in_t[in_t.gsis_id == r.gsis_id]
            if len(m):
                w = m.iloc[0]; p['inj'] = dict(week=int(w.week), status=c(w.report_status) or c(w.practice_status), what=c(w.report_primary_injury) or c(w.practice_primary_injury))
        if len(dp_t):
            m = dp_t[dp_t.gsis_id == r.gsis_id]
            if len(m):
                w = m.sort_values('pos_rank').iloc[0]; p['depth'] = dict(slot=w.pos_abb, rank=int(_num(w.pos_rank, 9)), grp=c(w.pos_grp), date=str(w.dt)[:10])
        if not p['weeks'] and not p['snaps']:
            continue
        out_players.append(p)
        ws = p['weeks']; S = lambda k: sum(_num(x.get(k)) for x in ws)
        sv = list(p['snaps'].values())
        index_rows.append(dict(id=p['id'], name=p['name'], team=t, pos=p['pos'], num=p['num'], snap=int(round(sum(sv) / len(sv))) if sv else 0,
                               tgt=int(S('targets')), rec=int(S('receptions')), car=int(S('carries')), att=int(S('attempts')),
                               yds=int(round(S('receiving_yards') + S('rushing_yards') + S('passing_yards'))), td=int(S('receiving_tds') + S('rushing_tds') + S('passing_tds')),
                               epa=round(S('receiving_epa') + S('rushing_epa') + S('passing_epa'), 1), g=len(ws),
                               st=(p['inj']['status'] if p['inj'] else None), what=(p['inj']['what'] if p['inj'] else None), slot=(f"{p['depth']['slot']}{p['depth']['rank']}" if p['depth'] else None)))
    out_players.sort(key=lambda p: -(sum(p['snaps'].values()) / len(p['snaps'])) if p['snaps'] else 0)
    json.dump(clean(dict(season=season, team=t, through=through_week, players=out_players)), open(f'{out}/players/{t}.json', 'w'), separators=(',', ':'))
index_rows.sort(key=lambda x: (-x['snap'], -x['tgt'] - x['car'] - x['att']))
json.dump(clean(dict(season=season, through=through_week, players=index_rows)), open(f'{out}/players/index.json', 'w'), separators=(',', ':'))

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
