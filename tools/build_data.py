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
  teams.json      {ABBR: {name, nick, conf, div, c1, c2, logo, logo_sq}}  (logo = ESPN 500px PNG, logo_sq = nflverse squared)
  plays/{ABBR}.json  {season, team, byes, games:[...], keys:[...], plays:[[...]]}  (pid = ball handler's gsis_id; FTN flags when available)
  players/{ABBR}.json  roster rows for QB/RB/FB/WR/TE with weekly stat lines, snap share, PFR extras, injury line, depth slot, grades, fp (playstyle traits),
                       ep (EPA split, success, ANY/A, depth-by-direction grid), trk (hero tile ranks), injw (report designation per week)
  players/index.json   one row per player league-wide for the Players index and search
  career/{year}.json  season totals for every player, one file per finished season (built once, kept)
  players/career/{ABBR}.json  each rostered player's season rows, oldest first, this season last
  players/movers.json  who moved in the last full week: snap share, target share and EPA against his own season, new faces
  players/league.json  per position group: qualifier, n, sorted metric lists (the pool behind the percentile grades), week + game quartiles (trend yardsticks);
                       grid: the league's share and EPA per depth-by-direction cell, for throws and for targets
  metrics.json    offense/defense metrics per team, league averages, ranks, through_week
  schedule.json   {season, current_week, weeks: {"1": [games], ...}} with status per game
  wp/w{N}.json    win probability for each final game that week: regulation series, overtime scores, top swings, turning point, low point (SCOPE §30)
  lineups.json    each team's starters from its latest depth chart, offense, defense and special teams, with jerseys and defenders' season lines (SCOPE §32)
  box/w{N}.json   each final game's box score: offense lines with snap share, defense sacks and interceptions, kicking (SCOPE §32)
  manifest.json   {season, week, through_week, built_at, files: {path: sha1}}

Runs nightly in GitHub Actions during the season; the WordPress plugin pulls what changed.
"""
import pandas as pd, json, math, sys, os, hashlib, datetime

pbp_path, roster_path, games_path, teams_path, out = sys.argv[1:6]
week_arg = int(sys.argv[6]) if len(sys.argv) > 6 else None
os.makedirs(os.path.join(out, 'plays'), exist_ok=True)
os.makedirs(os.path.join(out, 'players'), exist_ok=True)
env = lambda k: (os.environ.get(k) or None) if os.environ.get(k) and os.path.exists(os.environ.get(k)) else None

# a pandas cell as plain JSON: NaN to None, numpy scalars to Python, whole floats to int (defined before the teams step uses it)
def c(x):
    if isinstance(x, float) and math.isnan(x): return None
    if hasattr(x, 'item'): x = x.item()
    if isinstance(x, float) and x.is_integer(): return int(x)
    return x

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
                       c1=r.team_color, c2=r.team_color2, logo=c(getattr(r, 'team_logo_espn', None)), logo_sq=c(str(getattr(r, 'team_logo_squared', '') or '').replace('https://github.com/nflverse/nflverse-pbp/raw/master/', 'https://raw.githubusercontent.com/nflverse/nflverse-pbp/master/')) or None) for r in teams.itertuples()}
# optical logo scale (1.4.1): ESPN's logo files carry uneven margins (the Chargers bolt fills 38% of its image, the
# Raiders shield 85%), so the same box makes them look different sizes. Measure the artwork's alpha bounding box and
# scale toward a common area, never letting the longer side pass 1.35x the box (wordmarks such as the Jets' need the width). Skipped quietly without Pillow or a network.
try:
    from PIL import Image
    import io as _io, urllib.request as _ur
    for _a, _t in T.items():
        if not _t.get('logo'): continue
        try:
            _im = Image.open(_io.BytesIO(_ur.urlopen(_t['logo'], timeout=20).read())).convert('RGBA')
            _bb = _im.getchannel('A').point(lambda v: 255 if v > 20 else 0).getbbox()
            if not _bb: continue
            _fw = (_bb[2] - _bb[0]) / _im.width; _fh = (_bb[3] - _bb[1]) / _im.height
            _s = min((0.6 / max(_fw * _fh, 0.05)) ** 0.5, 1.35 / max(_fw, _fh))
            _t['logo_s'] = round(max(0.7, min(1.5, _s)), 2)
        except Exception as _e:
            print('logo scale: skipped', _a, repr(_e)[:80])
except ImportError:
    print('logo scale: Pillow not installed; logos keep their natural size')
json.dump(T, open(f'{out}/teams.json', 'w'), separators=(',', ':'))




# ---------------------------------------------------------------- per-team plays
keys = ['g', 'q', 't', 'dn', 'togo', 'yl', 'pt', 'k', 'yds', 'epa', 'succ', 'air', 'yac', 'loc', 'len', 'gap', 'sg', 'td', 'fd', 'drv', 'sd', 'pl', 'desc', 'pos', 'pid', 'qb', 'pa', 'scr', 'rpo', 'mot', 'blz', 'cont', 'drop']
team_plays = {}
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
                                        r.touchdown, r.first_down, r.drive, r.score_differential, pl, r.desc, ppos, pid if isinstance(pid, str) else None,
                                        r.passer_player_id if isinstance(r.passer_player_id, str) else None] + fl])
    played_weeks = {x['week'] for x in gl}
    # weeks before the current one with no game: a team that has not played yet this week is not on bye
    byes = sorted(set(range(1, week)) - played_weeks)
    team_plays[t] = [dict(zip(keys, r)) for r in rows]
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
    # nflverse rosters carry the overall pick, not the round; rounds vary in length so we do not guess one
    try:
        n = int(float(r.draft_number)); return f"Pick {n} overall ({c(r.entry_year)})"
    except Exception:
        return f"Undrafted ({c(r.entry_year)})" if c(r.entry_year) else 'Undrafted'

SKILL = ('QB', 'RB', 'FB', 'WR', 'TE')
WEEK_FIELDS = ['week', 'team', 'opponent_team', 'targets', 'receptions', 'receiving_yards', 'receiving_tds', 'receiving_air_yards', 'receiving_yards_after_catch', 'receiving_epa', 'receiving_first_downs', 'target_share', 'air_yards_share', 'wopr',
               'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa', 'rushing_first_downs', 'attempts', 'completions', 'passing_yards', 'passing_tds', 'passing_interceptions', 'passing_epa', 'passing_cpoe', 'sacks_suffered', 'passing_air_yards', 'passing_first_downs', 'receiving_20', 'rushing_10']
_rw = _r[_r.position.isin(SKILL)].copy()
if 'week' in _rw.columns:
    # each team's own latest roster week: teams on bye have no rows for the current week and would vanish otherwise
    _rw = _rw[_rw.week == _rw.groupby('team').week.transform('max')]
    # a player traded from a bye team to one with a newer roster week would appear on both: keep his newest row only
    _rw = _rw.sort_values('week').drop_duplicates('gsis_id', keep='last')
stats = pd.read_csv(env('DBD_STATS'), low_memory=False) if env('DBD_STATS') else pd.DataFrame()
if len(stats) and 'season_type' in stats.columns: stats = stats[stats.season_type == 'REG']
snaps = pd.read_csv(env('DBD_SNAPS'), low_memory=False) if env('DBD_SNAPS') else pd.DataFrame()
pfr = pd.read_csv(env('DBD_PFR'), low_memory=False) if env('DBD_PFR') else pd.DataFrame()
inj = pd.read_csv(env('DBD_INJ'), low_memory=False) if env('DBD_INJ') else pd.DataFrame()
depth = pd.read_csv(env('DBD_DEPTH'), low_memory=False, usecols=['dt', 'team', 'gsis_id', 'pos_grp', 'pos_abb', 'pos_rank']) if env('DBD_DEPTH') else pd.DataFrame()
if len(depth): depth = depth[depth.pos_abb.isin(SKILL)]
# every week's report designation per player (SCOPE §29): the game log's did-not-play rows say "Out · hamstring" for that week
injw_all = {}
if len(inj) and 'gsis_id' in inj.columns and 'report_status' in inj.columns:
    for w in inj[inj.report_status.notna()].itertuples():
        if isinstance(w.gsis_id, str):
            injw_all.setdefault(w.gsis_id, {})[str(int(w.week))] = [c(w.report_status), c(getattr(w, 'report_primary_injury', None))]
index_rows = []
team_players = {}
for t in T:
    roster = _rw[_rw.team == t]
    in_t = inj[inj.team == t] if len(inj) else inj
    if len(in_t): in_t = in_t[in_t.week == in_t.week.max()]
    dp_t = depth[depth.team == t] if len(depth) else depth
    if len(dp_t): dp_t = dp_t[dp_t['dt'] == dp_t['dt'].max()]  # ['dt'], not .dt: pandas would read the datetime accessor
    out_players = []
    for r in roster.itertuples():
        fn = r.football_name if isinstance(getattr(r, 'football_name', None), str) and r.football_name else r.first_name
        p = dict(id=r.gsis_id, name=r.full_name, short=f"{str(fn)[:1]}.{r.last_name}", pos=r.position, num=c(r.jersey_number), age=_age(r.birth_date), ht=_ht(r.height), wt=c(r.weight),
                 col=c(r.college), exp=int(_num(r.years_exp)), draft=_draft(r), status=c(r.status),
                 photo=(r.headshot_url if isinstance(getattr(r, 'headshot_url', None), str) and r.headshot_url.startswith('https://') else None), weeks=[], snaps={}, snapteam={}, pfr={}, inj=None, depth=None)
        if len(stats):  # every week this season, whichever team he was on
            for w in stats[stats.player_id == r.gsis_id].sort_values('week').itertuples():
                p['weeks'].append({k: (getattr(w, k) if k in ('team', 'opponent_team') else round(_num(getattr(w, k, 0)), 4)) for k in WEEK_FIELDS if k in stats.columns})
        # snap counts and PFR extras key by PFR id; names collide (two Justin Jeffersons), so the name fallback
        # is limited to teams this player actually has stat lines for
        pfid = r.pfr_id if isinstance(getattr(r, 'pfr_id', None), str) and r.pfr_id else None
        teams_of = {t} | {w['team'] for w in p['weeks'] if w.get('team')}
        if len(snaps):
            sn_p = snaps[snaps.pfr_player_id == pfid] if pfid and 'pfr_player_id' in snaps.columns else snaps[(snaps.player == r.full_name) & snaps.team.isin(teams_of)]
            for w in sn_p.itertuples():
                p['snaps'][str(int(w.week))] = int(round(_num(w.offense_pct) * 100))
                if isinstance(w.team, str) and w.team != t: p['snapteam'][str(int(w.week))] = w.team  # a week played for another team (trade)
        if len(pfr):
            pf_p = pfr[pfr.pfr_player_id == pfid] if pfid and 'pfr_player_id' in pfr.columns else pfr[(pfr.pfr_player_name == r.full_name) & pfr.team.isin(teams_of)]
            for w in pf_p.itertuples():
                p['pfr'][str(int(w.week))] = dict(drop=int(_num(w.receiving_drop)), bt=int(_num(w.receiving_broken_tackles)), rbt=int(_num(w.rushing_broken_tackles)), rat=c(w.receiving_rat) if _num(w.receiving_rat, None) is not None else None)
        if len(in_t):
            m = in_t[in_t.gsis_id == r.gsis_id]
            if len(m):
                w = m.iloc[0]; p['inj'] = dict(week=int(w.week), status=c(w.report_status) or c(w.practice_status), what=c(w.report_primary_injury) or c(w.practice_primary_injury))
        if r.gsis_id in injw_all: p['injw'] = injw_all[r.gsis_id]
        if len(dp_t):
            m = dp_t[dp_t.gsis_id == r.gsis_id]
            if len(m):
                w = m.sort_values('pos_rank').iloc[0]; p['depth'] = dict(slot=w['pos_abb'], rank=int(_num(w['pos_rank'], 9)), grp=c(w['pos_grp']), date=str(w['dt'])[:10])
        if not p['weeks'] and not p['snaps']:
            continue
        out_players.append(p)
        ws = p['weeks']; S = lambda k: sum(_num(x.get(k)) for x in ws)
        sv = list(p['snaps'].values())
        index_rows.append(dict(id=p['id'], name=p['name'], team=t, pos=p['pos'], num=p['num'], col=p['col'], photo=p['photo'], snap=int(round(sum(sv) / len(sv))) if sv else 0,
                               tgt=int(S('targets')), rec=int(S('receptions')), car=int(S('carries')), att=int(S('attempts')),
                               yds=int(round(S('receiving_yards') + S('rushing_yards') + S('passing_yards'))), td=int(S('receiving_tds') + S('rushing_tds') + S('passing_tds')),
                               epa=round(S('receiving_epa') + S('rushing_epa') + S('passing_epa'), 1), g=len(ws),
                               st=(p['inj']['status'] if p['inj'] else None), what=(p['inj']['what'] if p['inj'] else None), slot=(f"{p['depth']['slot']}{p['depth']['rank']}" if p['depth'] else None)))
    out_players.sort(key=lambda p: -(sum(p['snaps'].values()) / len(p['snaps'])) if p['snaps'] else 0)
    team_players[t] = out_players
# ---- grades: season metrics per player, and the league pool per position group (SCOPE §15)
GROUPS = {'QB': 'qb', 'RB': 'rb', 'FB': 'rb', 'WR': 'rec', 'TE': 'rec'}
MINQ = {'rec': 15, 'rb': 30, 'qb': 50}; MINLABEL = {'rec': '15 targets', 'rb': '30 touches', 'qb': '50 attempts'}
LOW = {'rec': ['drop'], 'rb': [], 'qb': ['sack', 'intr']}

def grade(p):
    grp = GROUPS.get(p['pos'])
    if not grp: return None
    ws = p['weeks']; S = lambda k: sum(_num(x.get(k)) for x in ws)
    played = {int(x['week']) for x in ws} | {int(k) for k in p['snaps']}; g = max(1, len(played))
    tg, rc, ca, att, sk = S('targets'), S('receptions'), S('carries'), S('attempts'), S('sacks_suffered')
    drops = sum(int(x.get('drop') or 0) for x in p['pfr'].values()); bt = sum(int(x.get('bt') or 0) + int(x.get('rbt') or 0) for x in p['pfr'].values())
    d = lambda a, b: (a / b) if b else None
    if grp == 'rec':
        m = dict(tshare=d(S('target_share'), len(ws)), adot=d(S('receiving_air_yards'), tg), yacpc=d(S('receiving_yards_after_catch'), rc), catch=d(rc, tg), epat=d(S('receiving_epa'), tg),
                 fdpt=d(S('receiving_first_downs'), tg), drop=d(drops, tg), ypg=S('receiving_yards') / g); q = tg >= MINQ['rec']
    elif grp == 'rb':
        tch = ca + tg
        m = dict(ypc=d(S('rushing_yards'), ca), repc=d(S('rushing_epa'), ca), epat=d(S('receiving_epa'), tg) if tg >= 5 else None, btpt=d(bt, tch), expl=d(S('rushing_10') + S('receiving_20'), tch),
                 tpg=tch / g, tgtshare=d(tg, tch), ypt=d(S('rushing_yards') + S('receiving_yards'), tch)); q = tch >= MINQ['rb']
    else:
        qbw = [x for x in ws if _num(x.get('attempts')) > 0]; db = att + sk
        m = dict(epadb=d(S('passing_epa'), db), cpoe=d(sum(_num(x.get('passing_cpoe')) for x in qbw), len(qbw)), sack=d(sk, db), ypa=d(S('passing_yards'), att), tdr=d(S('passing_tds'), att),
                 intr=d(S('passing_interceptions'), att), adot=d(S('passing_air_yards'), att), rushepa=S('rushing_epa') / g); q = att >= MINQ['qb']
    return dict(grp=grp, q=bool(q), m={k: (round(v, 4) if v is not None else None) for k, v in m.items()})

dist = {g: {} for g in MINQ}; npool = {g: 0 for g in MINQ}; wkyds = {g: [] for g in MINQ}; wkm = {g: {} for g in MINQ}
# per-game trend metrics (SCOPE §21): usage, yards, efficiency, role. Counts take a zero for a snaps-only week; rates only count games with a target, touch or dropback.
def game_metrics(g, w):
    if g == 'rec':
        tg = _num(w.get('targets')); r = {'tgt': tg, 'yds': _num(w.get('receiving_yards'))}
        if tg: r['epat'] = _num(w.get('receiving_epa')) / tg; r['adot'] = _num(w.get('receiving_air_yards')) / tg
    elif g == 'rb':
        tch = _num(w.get('carries')) + _num(w.get('targets')); r = {'tch': tch, 'yds': _num(w.get('rushing_yards')) + _num(w.get('receiving_yards')), 'tgt': _num(w.get('targets'))}
        if tch: r['epatch'] = (_num(w.get('rushing_epa')) + _num(w.get('receiving_epa'))) / tch
    else:
        db = _num(w.get('attempts')) + _num(w.get('sacks_suffered')); r = {'db': db, 'yds': _num(w.get('passing_yards'))}
        if db: r['epadb'] = _num(w.get('passing_epa')) / db
        if _num(w.get('attempts')): r['cpoe'] = _num(w.get('passing_cpoe'))
    return r
GAME_COUNTS = {'rec': ['tgt', 'yds'], 'rb': ['tch', 'yds', 'tgt'], 'qb': ['db', 'yds']}
YDS = {'rec': ('receiving_yards',), 'rb': ('rushing_yards', 'receiving_yards'), 'qb': ('passing_yards',)}
# ---- playstyle fingerprint (SCOPE §23): eight usage traits per group as shares of a player's own plays, plus the league pool per trait
FP_BLITZ_MIN = 10
def fp_for(plays, posmap):
    """{player id: (group, traits)} for every rostered player with a target, carry or dropback in a team's play rows. The group is his roster position, so a receiver's trick-play pass does not make him a quarterback."""
    T = [q for q in plays if q['k'] in ('comp', 'inc', 'int') and q['pid']]
    R = [q for q in plays if q['k'] == 'run' and q['pid']]
    qbids = {q['qb'] for q in plays if q['qb'] and posmap.get(q['qb']) == 'QB'}
    D = [dict(q, _qb=(q['pid'] if q['k'] == 'scramble' else q['qb'])) for q in plays if (q['qb'] and q['k'] in ('comp', 'inc', 'int', 'sack')) or (q['k'] == 'scramble' and q['pid'] in qbids)]
    U = T + R
    share = lambda mine, team: (len(mine) / len(team)) if team else None
    def rate(mine, f, den=None):
        d = [q for q in mine if den is None or den(q)]
        return (sum(1 for q in d if f(q)) / len(d)) if d else None
    out = {}
    for pid in {q['pid'] for q in U} | qbids:
        g = GROUPS.get(posmap.get(pid))
        if not g: continue
        if g == 'rec':
            m = [q for q in T if q['pid'] == pid]; comps = [q for q in m if q['k'] == 'comp']
            yds = sum(q['yds'] or 0 for q in comps); yac = sum(q['yac'] or 0 for q in comps)
            fp = dict(n=len(m), share=share(m, T), deep=rate(m, lambda q: q['air'] >= 20, lambda q: q['air'] is not None), scr=rate(m, lambda q: q['scr'] == 1), mid=rate(m, lambda q: q['loc'] == 'middle', lambda q: q['loc']),
                      yacs=(yac / yds) if yds > 0 else None, d3=share([q for q in m if q['dn'] == 3], [q for q in T if q['dn'] == 3]), rzs=share([q for q in m if q['yl'] is not None and q['yl'] <= 20], [q for q in T if q['yl'] is not None and q['yl'] <= 20]),
                      mot=rate(m, lambda q: q['mot'] == 1), pa=rate(m, lambda q: q['pa'] == 1))
        elif g == 'rb':
            m = [q for q in U if q['pid'] == pid]; r = [q for q in m if q['k'] == 'run']; tg = [q for q in m if q['k'] != 'run']
            fp = dict(n=len(m), share=share(m, U), ins=rate(r, lambda q: q['gap'] in ('guard', 'tackle')), sg=rate(r, lambda q: q['sg'] == 1), recs=(len(tg) / len(m)) if m else None,
                      d3=share([q for q in m if q['dn'] == 3], [q for q in U if q['dn'] == 3]), rzs=share([q for q in m if q['yl'] is not None and q['yl'] <= 20], [q for q in U if q['yl'] is not None and q['yl'] <= 20]),
                      shorty=share([q for q in m if q['togo'] is not None and q['togo'] <= 2], [q for q in U if q['togo'] is not None and q['togo'] <= 2]), rpo=rate(r, lambda q: q['rpo'] == 1), mot=rate(m, lambda q: q['mot'] == 1))
        else:
            m = [q for q in D if q['_qb'] == pid]; a = [q for q in m if q['k'] in ('comp', 'inc', 'int')]
            bz = [q for q in m if q['blz'] == 1]; nb = [q for q in m if q['blz'] != 1]
            e = lambda l: (sum(q['epa'] or 0 for q in l) / len(l)) if l else None
            fp = dict(n=len(a), share=share(m, D), deep=rate(a, lambda q: q['air'] >= 20, lambda q: q['air'] is not None), mid=rate(a, lambda q: q['loc'] == 'middle', lambda q: q['loc']), pa=rate(a, lambda q: q['pa'] == 1), rpo=rate(m, lambda q: q['rpo'] == 1),
                      scram=rate(m, lambda q: q['k'] == 'scramble'), sg=rate(m, lambda q: q['sg'] == 1), blz=(e(bz) - e(nb)) if len(bz) >= FP_BLITZ_MIN and nb else None, mot=rate(m, lambda q: q['mot'] == 1))
        out[pid] = (g, {k: (round(v, 4) if isinstance(v, float) else v) for k, v in fp.items()})
    return out
fpdist = {g: {} for g in MINQ}; fp_cache = {}
for t, pl in team_players.items():
    fpt = fp_cache.setdefault(t, fp_for(team_plays.get(t, []), {x['id']: x['pos'] for x in pl}))
    for p in pl:
        gr = grade(p); p['grades'] = gr
        fpe = fpt.get(p['id']); p['fp'] = fpe[1] if fpe else None
        if gr and gr['q'] and fpe and fpe[0] == gr['grp']:
            for k, v in fpe[1].items():
                if k not in ('n', 'share') and v is not None: fpdist[gr['grp']].setdefault(k, []).append(v)
        if gr and gr['q']:
            npool[gr['grp']] += 1
            for k, v in gr['m'].items():
                if v is not None: dist[gr['grp']].setdefault(k, []).append(v)
            # the pool's game weeks, the yardstick for a boom or a dud: every week he played, a snaps-only week counting as zero
            statwk = {int(w['week']) for w in p['weeks']}
            for w in p['weeks']:
                wkyds[gr['grp']].append(sum(_num(w.get(k)) for k in YDS[gr['grp']]))
                for k, v in game_metrics(gr['grp'], w).items(): wkm[gr['grp']].setdefault(k, []).append(v)
            for wk in p['snaps']:
                if int(wk) not in statwk:
                    wkyds[gr['grp']].append(0.0)
                    for k in GAME_COUNTS[gr['grp']]: wkm[gr['grp']].setdefault(k, []).append(0.0)
for g in dist:
    for k in dist[g]: dist[g][k].sort()
for g in fpdist:
    for k in fpdist[g]: fpdist[g][k].sort()
# ---- where the EPA comes from, depth by direction, success rate, ANY/A and tile ranks (SCOPE §29)
# Every regular-season dropback, target and run is split by what happened. Completions divide at the catch with nflverse's
# air EPA; a quarterback's side uses his credited EPA (qb_epa), so his passing parts add up to the weekly passing EPA the
# tiles show. Parts come from play-by-play and section totals from the weekly lines; the gap is the section's "other".
from collections import defaultdict
def _cell(air, loc):
    if air is None or loc not in ('left', 'middle', 'right'): return None
    return ('behind' if air < 0 else ('short' if air < 10 else ('inter' if air < 20 else 'deep'))) + '-' + loc
_fv = lambda x: None if x is None or (isinstance(x, float) and math.isnan(x)) else float(x)
EPQ = defaultdict(lambda: defaultdict(float)); EPR = defaultdict(lambda: defaultdict(float)); EPU = defaultdict(lambda: defaultdict(float))
GQ = defaultdict(lambda: defaultdict(lambda: [0, 0.0, 0])); GR = defaultdict(lambda: defaultdict(lambda: [0, 0.0, 0]))
LGQ = defaultdict(lambda: [0, 0.0]); LGR = defaultdict(lambda: [0, 0.0])
if have_pbp:
    _e = df[df.play_type.isin(['pass', 'run', 'qb_kneel', 'qb_spike'])]
    if 'season_type' in _e.columns: _e = _e[_e.season_type == 'REG']
    for r in _e.itertuples(index=False):
        e0 = _fv(r.epa) or 0.0; qe = _fv(getattr(r, 'qb_epa', None)); qe = e0 if qe is None else qe
        s0 = 1.0 if _fv(r.success) == 1 else 0.0; scr = _fv(r.qb_scramble) == 1
        if _fv(getattr(r, 'two_point_attempt', None)) == 1:  # the weekly EPA counts two-point tries; they get their own part and stay out of the rates
            if r.play_type == 'pass' and isinstance(r.passer_player_id, str):
                EPQ[r.passer_player_id]['two'] += qe
                if isinstance(r.receiver_player_id, str): EPR[r.receiver_player_id]['two'] += e0
            elif isinstance(r.rusher_player_id, str): EPU[r.rusher_player_id]['two'] += e0
            continue
        if r.play_type in ('pass', 'qb_spike') and not scr:
            pid = r.passer_player_id
            if not isinstance(pid, str): continue
            q = EPQ[pid]; q['db'] += 1; q['succ'] += s0
            if _fv(r.sack) == 1:
                q['sk'] += qe; q['nsk'] += 1; q['sky'] -= _fv(r.yards_gained) or 0; continue
            q['att'] += 1  # a spike is a pass attempt (ANY/A's denominator); its EPA sits with the incompletions
            if r.play_type == 'qb_spike':
                q['inc'] += qe; continue
            comp = _fv(r.complete_pass) == 1; cl = _cell(_fv(r.air_yards), r.pass_location if isinstance(r.pass_location, str) else None)
            if _fv(r.interception) == 1: q['int'] += qe; q['nint'] += 1
            elif comp:
                a = _fv(r.comp_air_epa) or 0.0; q['air'] += a; q['yac'] += qe - a
                q['yds'] += _fv(r.passing_yards) or 0; q['td'] += 1 if _fv(r.pass_touchdown) == 1 else 0
            else: q['inc'] += qe
            if cl:
                g = GQ[pid][cl]; g[0] += 1; g[1] += qe; g[2] += 1 if comp else 0; LGQ[cl][0] += 1; LGQ[cl][1] += qe
            tid = r.receiver_player_id
            if isinstance(tid, str):
                t = EPR[tid]; t['tg'] += 1; t['succ'] += s0
                if _fv(r.interception) == 1: t['int'] += e0
                elif comp: t['air'] += _fv(r.comp_air_epa) or 0.0; t['yac'] += _fv(r.comp_yac_epa) or 0.0
                else: t['inc'] += e0
                if cl:
                    g = GR[tid][cl]; g[0] += 1; g[1] += e0; g[2] += 1 if comp else 0; LGR[cl][0] += 1; LGR[cl][1] += e0
        else:
            pid = r.rusher_player_id
            if not isinstance(pid, str): continue
            u = EPU[pid]
            if scr:
                u['scr'] += e0; u['nscr'] += 1; EPQ[pid]['db'] += 1; EPQ[pid]['succ'] += s0
            else:
                u['run'] += e0; u['car'] += 1; u['succ'] += s0
def _lgrid(L):
    n = sum(v[0] for v in L.values())
    return {k: [round(v[0] / n, 4), round(v[1] / v[0], 3)] for k, v in L.items() if v[0]} if n else {}
lg_grid = dict(qb=_lgrid(LGQ), tgt=_lgrid(LGR))
def _grid(G):
    return {k: [v[0], round(v[1] / v[0], 3), v[2]] for k, v in G.items() if v[0]}
EP_PARTS = {'pass': ('air', 'yac', 'inc', 'int', 'sk'), 'rush': ('scr', 'run'), 'rec': ('air', 'yac', 'inc', 'int')}
EP_NOPC = ('two',)  # parts with no rate and no percentile
def ep_for(p, grp):
    """The player's split, or None when he has no plays in his role. Parts are [key, total, per play, percentile]; the percentile is filled once the pool is known."""
    ws = p['weeks']; S = lambda k: sum(_num(x.get(k)) for x in ws); pid = p['id']
    sec = []; d = lambda a, b: (a / b) if b else None
    if grp == 'qb':
        q = EPQ.get(pid)
        if not q or not q['db']: return None
        u = EPU.get(pid, {}); db = q['db']
        sec.append(['pass', S('passing_epa'), [[k, q[k], q[k] / db] for k in EP_PARTS['pass']] + ([['two', q['two'], None]] if q['two'] else [])])
        sec.append(['rush', S('rushing_epa'), [['scr', u.get('scr', 0.0), u.get('scr', 0.0) / db], ['run', u.get('run', 0.0), None]] + ([['two', u['two'], None]] if u.get('two') else [])])
        base = dict(u=int(db), sr=q['succ'] / db, any=d(q['yds'] + 20 * q['td'] - 45 * q['nint'] - q['sky'], q['att'] + q['nsk']), gr=_grid(GQ.get(pid, {})))
    elif grp == 'rec':
        t = EPR.get(pid)
        if not t or not t['tg']: return None
        sec.append(['rec', S('receiving_epa'), [[k, t[k], t[k] / t['tg']] for k in EP_PARTS['rec']] + ([['two', t['two'], None]] if t['two'] else [])])
        base = dict(u=int(t['tg']), sr=t['succ'] / t['tg'], gr=_grid(GR.get(pid, {})))
    else:
        u = EPU.get(pid, {}); t = EPR.get(pid, {}); car = u.get('car', 0); tg = t.get('tg', 0)
        if not car and not tg: return None
        sec.append(['rush', S('rushing_epa'), [['run', u.get('run', 0.0), d(u.get('run', 0.0), car)]] + ([['two', u['two'], None]] if u.get('two') else [])])
        if tg: sec.append(['rec', S('receiving_epa'), [[k, t.get(k, 0.0), t.get(k, 0.0) / tg] for k in EP_PARTS['rec']] + ([['two', t['two'], None]] if t.get('two') else [])])
        base = dict(u=int(car + tg), car=int(car), tg=int(tg), sr=(u.get('succ', 0.0) + t.get('succ', 0.0)) / (car + tg), gr=_grid(GR.get(pid, {})))
    out = []
    for k, tot, parts in sec:
        rp = [[pk, round(v, 3), (round(pr, 4) if pr is not None else None), None] for pk, v, pr in parts]
        out.append([k, round(tot, 3), round(tot - sum(x[1] for x in rp), 3), rp])
    base.update(sec=out, tot=round(sum(x[1] for x in out), 3))
    if base.get('any') is not None: base['any'] = round(base['any'], 3)
    base['sr'] = round(base['sr'], 4)
    return base
# the hero tiles' metrics, keyed as player.php names them; counting stats rank on season totals, as nfelo's do
TILE = {
    'rec': dict(tgt=lambda p, S, m: S('targets'), rec=lambda p, S, m: S('receptions'), yds=lambda p, S, m: S('receiving_yards'), td=lambda p, S, m: S('receiving_tds'),
                tsh=lambda p, S, m: m.get('tshare'), adot=lambda p, S, m: m.get('adot'), epat=lambda p, S, m: m.get('epat'), yac=lambda p, S, m: m.get('yacpc')),
    'rb': dict(car=lambda p, S, m: S('carries'), ruy=lambda p, S, m: S('rushing_yards'), rutd=lambda p, S, m: S('rushing_tds'), tgt=lambda p, S, m: S('targets'),
               tpg=lambda p, S, m: m.get('tpg'), epatch=lambda p, S, m: ((S('rushing_epa') + S('receiving_epa')) / (S('carries') + S('targets'))) if (S('carries') + S('targets')) else None,
               expl=lambda p, S, m: S('rushing_10') + S('receiving_20'), snap=lambda p, S, m: (sum(p['snaps'].values()) / len(p['snaps'])) if p['snaps'] else None),
    'qb': dict(att=lambda p, S, m: S('attempts'), cmp=lambda p, S, m: (S('completions') / S('attempts')) if S('attempts') else None, py=lambda p, S, m: S('passing_yards'),
               ptd=lambda p, S, m: S('passing_tds'), epadb=lambda p, S, m: m.get('epadb'), sack=lambda p, S, m: m.get('sack'), ruy=lambda p, S, m: S('rushing_yards'),
               any=lambda p, S, m: (p.get('ep') or {}).get('any')),
}
TILE_LOW = {'sack'}
def _rank(vals, v, low=False):
    if v is None: return None
    better = sum(1 for x in vals if (x < v - 1e-9 if low else x > v + 1e-9)); ties = sum(1 for x in vals if abs(x - v) <= 1e-9)
    return [better + 1, 1 if ties > 1 else 0]
def _pc(vals, v):
    if v is None or not vals: return None
    below = sum(1 for x in vals if x < v - 1e-9); eq = sum(1 for x in vals if abs(x - v) <= 1e-9)
    return int(round(100 * (below + eq / 2) / len(vals)))
allp = [p for pl in team_players.values() for p in pl]
for p in allp:
    gr = p.get('grades'); p['ep'] = ep_for(p, gr['grp']) if gr and have_pbp else None
pool = {g: [p for p in allp if p.get('grades') and p['grades']['q'] and p['grades']['grp'] == g] for g in MINQ}
for g, members in pool.items():
    # per-part rates (or totals where a part has no per-play rate), success, ANY/A and total EPA among the qualifiers
    pv = defaultdict(list)
    for p in members:
        ep = p.get('ep')
        if not ep: continue
        for sk, _, _, parts in ep['sec']:
            for pk, tot, per, _ in parts:
                if pk not in EP_NOPC: pv[sk + '.' + pk].append(per if per is not None else tot)
        for k in ('sr', 'any', 'tot'):
            if ep.get(k) is not None: pv[k].append(ep[k])
    tv = {k: [] for k in TILE[g]}
    for p in members:
        S = (lambda ws: (lambda k: sum(_num(x.get(k)) for x in ws)))(p['weeks']); m = p['grades']['m']
        for k, fn in TILE[g].items():
            v = fn(p, S, m)
            if v is not None: tv[k].append(v)
    for p in members:
        ep = p.get('ep')
        if ep:
            for sk, _, _, parts in ep['sec']:
                for x in parts: x[3] = None if x[0] in EP_NOPC else _pc(pv[sk + '.' + x[0]], x[2] if x[2] is not None else x[1])
            ep['rk'] = {k: _rank(pv[k], ep[k]) for k in ('sr', 'any', 'tot') if ep.get(k) is not None}
            ep['pc'] = {k: _pc(pv[k], ep[k]) for k in ('sr', 'any', 'tot') if ep.get(k) is not None}
        S = (lambda ws: (lambda k: sum(_num(x.get(k)) for x in ws)))(p['weeks']); m = p['grades']['m']
        p['trk'] = {k: _rank(tv[k], fn(p, S, m), k in TILE_LOW) for k, fn in TILE[g].items() if fn(p, S, m) is not None}
def quart(a):
    if not a: return None
    a = sorted(a); q = lambda f: a[min(len(a) - 1, int(len(a) * f))]
    return dict(p25=q(.25), p50=q(.5), p75=q(.75), n=len(a))
json.dump(clean(dict(season=season, through=through_week, groups={g: dict(n=npool[g], min=MINLABEL[g], low=LOW[g], dist=dist[g], week=quart(wkyds[g]), game={k: quart(v) for k, v in wkm[g].items()}, fp=fpdist[g]) for g in MINQ}, grid=lg_grid)), open(f'{out}/players/league.json', 'w'), separators=(',', ':'))
for t, pl in team_players.items():
    json.dump(clean(dict(season=season, team=t, through=through_week, players=pl)), open(f'{out}/players/{t}.json', 'w'), separators=(',', ':'))

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

# ---------------------------------------------------------------- win probability (SCOPE §30): wp/w{N}.json, one file per week of final games
# nflverse's spread-adjusted home win probability before every snap. Its model does not hold together under the current overtime rules,
# so the series covers regulation only (a game tied after sixty minutes ends regulation at 50%) and overtime keeps its scoring plays.
# Each point carries the score going into the snap: nflverse's running totals include the play's own points.
import re as _re
def wp_clean(d):
    d = _re.sub(r'^\(\s*\d*:\d+\)\s*', '', str(d))
    d = _re.sub(r'\((?:No Huddle, )?Shotgun\)\s*|\(No Huddle\)\s*', '', d)
    d = _re.sub(r'[^.]*reported in as eligible\.\s*', '', d)
    d = _re.sub(r",\s*Center-[\w.'-]+(?:,\s*Holder-[\w.'-]+)?", '', d)   # snapper and holder credits
    d = _re.sub(r'\b\d{1,2}-(?=[A-Z])', '', d)                            # jersey numbers: 4-J.Elliott -> J.Elliott
    d = _re.sub(r'\s+', ' ', d).strip()
    return d if len(d) <= 150 else d[:147].rsplit(' ', 1)[0] + '…'
def wp_game(g):
    """One final game's record, or None when the play-by-play has no win probability for it."""
    s = []; meta = []; sc = (0, 0); ots = []; ot = False; reg_end = (0, 0); home_t = g.iloc[0].home_team; away_t = g.iloc[0].away_team
    for r in g.itertuples(index=False):  # the file's own order; timeouts and quarter ends are not snaps and would take the next snap's swing
        if not isinstance(r.play_type, str): continue
        hw = _fv(getattr(r, 'vegas_home_wp', None)); gs = _fv(r.game_seconds_remaining); q = int(_num(r.qtr))
        cur = (int(_num(r.total_home_score)), int(_num(r.total_away_score)))
        if q <= 4:
            if hw is not None and gs is not None:
                s.append([int(round(3600 - gs)), int(round(hw * 1000)), sc[0], sc[1]]); meta.append(r)
            reg_end = cur
        else:
            base = sc if ot else reg_end
            if cur != base:  # the scoring team is whichever total rose, so a pick-six or a safety is credited to the defense
                ots.append([int(round(3600 + 600 - (gs or 0))), home_t if cur[0] > base[0] else away_t, wp_clean(r.desc), cur[0], cur[1]])
            ot = True
        sc = cur
    if len(s) < 20: return None
    hs, as_ = sc
    fin = 1000 if hs > as_ else (0 if hs < as_ else 500); rfin = 500 if ot else fin
    after = [x[1] for x in s[1:]] + [rfin]
    plays = [dict(i=i, d=a - x[1]) for i, (x, a) in enumerate(zip(s, after))]
    win = 'home' if fin == 1000 else ('away' if fin == 0 else None)
    wv = (lambda v: v) if win == 'home' else (lambda v: 1000 - v)
    tp = lo = None
    if win:
        lo = min(range(len(s)), key=lambda k: wv(s[k][1]))
        if not ot:
            for p in plays:
                if wv(s[p['i']][1]) <= 500 < wv(after[p['i']]): tp = p['i']
    seq = [v for v in [x[1] for x in s] + [rfin] if v != 500]  # even odds favor no one: 490 -> 500 -> 510 is one change, 490 -> 500 -> 490 none
    top = sorted(plays, key=lambda p: -abs(p['d']))[:5]
    pl = lambda i: [i, after[i] - s[i][1], meta[i].posteam if isinstance(meta[i].posteam, str) else None, wp_clean(meta[i].desc)]
    return dict(pre=s[0][1], spread=c(getattr(g.iloc[0], 'spread_line', None)), s=s, fin=fin, ot=ot, reg=list(reg_end), otsc=ots,
                top=[pl(p['i']) for p in top], tp=pl(tp) if tp is not None else None, lo=lo,
                flips=sum(1 for u, v in zip(seq, seq[1:]) if (u - 500) * (v - 500) < 0))
os.makedirs(f'{out}/wp', exist_ok=True)
if have_pbp and 'vegas_home_wp' in df.columns:
    _w = df[df.season_type == 'REG'] if 'season_type' in df.columns else df
    final_ids = {g['id'] for gl in weeks.values() for g in gl if g['status'] == 'final'}
    byweek = {}
    for gid, g in _w[_w.game_id.isin(final_ids)].groupby('game_id'):
        rec = wp_game(g)
        if rec: byweek.setdefault(int(g.week.iloc[0]), {})[gid] = rec
    for wk, games in byweek.items():
        json.dump(dict(season=season, week=wk, games=games), open(f'{out}/wp/w{wk}.json', 'w'), separators=(',', ':'))
    print('win probability:', sum(len(v) for v in byweek.values()), 'games in', len(byweek), 'weeks')

# ---------------------------------------------------------------- lineups and box scores (SCOPE §32)
# lineups.json: each team's starters from its latest depth chart snapshot (the file is daily), every position, with jersey
# numbers from the roster and, for defenders and kickers, their season line. box/w{N}.json: each final game's player lines,
# offense with snap share, defense (sacks, interceptions) and kicking (field goals), from the weekly stats for every player.
DEF_K = ['def_tackles_solo', 'def_tackle_assists', 'def_sacks', 'def_interceptions', 'def_pass_defended', 'def_tackles_for_loss']
def _short(name):
    parts = str(name or '').split(' '); return (parts[0][:1] + '.' + parts[-1]) if len(parts) > 1 else str(name or '')
jersey = {r.gsis_id: c(r.jersey_number) for r in _r.itertuples() if isinstance(r.gsis_id, str)}
seasonal = {}
if len(stats):
    for pid, g in stats.groupby('player_id'):
        row = {k: float(g[k].fillna(0).sum()) for k in DEF_K + ['fg_made', 'fg_att'] if k in g.columns}
        if 'fg_long' in g.columns: row['fg_long'] = float(g['fg_long'].fillna(0).max())
        seasonal[pid] = row
def _defl(pid):
    s = seasonal.get(pid, {}); return [int(s.get('def_tackles_solo', 0) + s.get('def_tackle_assists', 0)), round(s.get('def_sacks', 0), 1), int(s.get('def_interceptions', 0)), int(s.get('def_pass_defended', 0)), round(s.get('def_tackles_for_loss', 0), 1)]
# league ranks for the playmaking stats the players-to-watch cards quote
_rk = {}
for k in ('def_sacks', 'def_interceptions', 'def_pass_defended'):
    vals = sorted((v.get(k, 0) for v in seasonal.values() if v.get(k, 0) > 0), reverse=True)
    _rk[k] = lambda x, vals=vals: [1 + sum(1 for y in vals if y > x), 1 if sum(1 for y in vals if y == x) > 1 else 0] if x > 0 else None  # [rank, tied]
if env('DBD_DEPTH'):
    _dc = pd.read_csv(env('DBD_DEPTH'), low_memory=False, usecols=['dt', 'team', 'player_name', 'gsis_id', 'pos_grp', 'pos_name', 'pos_abb', 'pos_slot', 'pos_rank'])
    _dc = _dc[_dc['dt'] == _dc.groupby('team')['dt'].transform('max')]  # each team's latest snapshot
    lineups = {}
    for t, g in _dc.groupby('team'):
        if t not in T: continue
        u = {'off': {}, 'def': {}, 'st': {}, 'grp': {}}
        for r in g.sort_values('pos_rank').itertuples():
            unit = 'st' if 'Special' in str(r.pos_grp) else ('def' if str(r.pos_grp).startswith('Base') else 'off')
            key = r.pos_abb + (str(int(r.pos_slot)) if r.pos_abb == 'WR' and r.pos_slot == r.pos_slot else '')  # receivers are told apart by slot
            if key in u[unit] or (unit == 'off' and r.pos_abb == 'FB'): continue  # the slot's starter is its lowest rank; a fullback is not in the three-receiver set
            if not isinstance(r.player_name, str) or not r.player_name.strip(): continue  # an entry with no name gives the slot to the next player listed
            pid = r.gsis_id if isinstance(r.gsis_id, str) else None
            e = dict(id=pid, name=c(r.player_name), pos=c(r.pos_name), num=jersey.get(pid))
            if unit == 'def' and pid:
                e['s'] = _defl(pid); sv = seasonal.get(pid, {})
                e['rk'] = [_rk['def_sacks'](sv.get('def_sacks', 0)), _rk['def_interceptions'](sv.get('def_interceptions', 0)), _rk['def_pass_defended'](sv.get('def_pass_defended', 0))]
            if unit == 'st' and pid and r.pos_abb == 'PK':
                sv = seasonal.get(pid, {}); e['fg'] = [int(sv.get('fg_made', 0)), int(sv.get('fg_att', 0)), int(sv.get('fg_long', 0))]
            u[unit][key] = e; u['grp'][unit] = c(r.pos_grp)
        lineups[t] = u
    json.dump(clean(dict(season=season, teams=lineups)), open(f'{out}/lineups.json', 'w'), separators=(',', ':'))
    print('lineups:', len(lineups), 'teams;', sum(len(v['off']) for v in lineups.values()), 'offensive and', sum(len(v['def']) for v in lineups.values()), 'defensive starters')
# box scores: every final game, both teams
os.makedirs(f'{out}/box', exist_ok=True)
if len(stats):
    snap_of = {p['id']: p['snaps'] for pl in team_players.values() for p in pl}
    OFFK = ['completions', 'attempts', 'passing_yards', 'passing_tds', 'passing_interceptions', 'sacks_suffered', 'passing_epa', 'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa', 'targets', 'receptions', 'receiving_yards', 'receiving_tds', 'receiving_epa']
    tcol = 'team' if 'team' in stats.columns else 'recent_team'
    byweek = {}
    for wk, gl in weeks.items():
        for gm in gl:
            if gm['status'] != 'final': continue
            rec = {}
            for side, tm in (('away', gm['away']), ('home', gm['home'])):
                rows = stats[(stats.week == int(wk)) & (stats[tcol] == tm)]
                offr, defr, kick = [], [], None
                for r in rows.itertuples():
                    v = {k: _num(getattr(r, k, 0)) for k in OFFK}
                    if v['attempts'] or v['carries'] or v['targets']:
                        offr.append([r.player_id, _short(getattr(r, 'player_display_name', '')), c(getattr(r, 'position', None)), (snap_of.get(r.player_id) or {}).get(str(int(wk)))] + [round(v[k], 2) if 'epa' in k else int(v[k]) for k in OFFK])
                    sk, it = _num(getattr(r, 'def_sacks', 0)), _num(getattr(r, 'def_interceptions', 0))
                    if sk or it: defr.append([_short(getattr(r, 'player_display_name', '')), round(sk, 1), int(it)])
                    if _num(getattr(r, 'fg_att', 0)): kick = [_short(getattr(r, 'player_display_name', '')), int(_num(r.fg_made)), int(_num(r.fg_att)), int(_num(getattr(r, 'fg_long', 0)))]
                defr.sort(key=lambda x: -(x[1] * 2 + x[2] * 3))
                rec[side] = dict(team=tm, off=offr, d=defr, k=kick)
            byweek.setdefault(int(wk), {})[gm['id']] = rec
    for wk, games in byweek.items():
        json.dump(clean(dict(season=season, week=wk, keys=['id', 'name', 'pos', 'snap'] + OFFK, games=games)), open(f'{out}/box/w{wk}.json', 'w'), separators=(',', ':'))
    print('box scores:', sum(len(v) for v in byweek.values()), 'games in', len(byweek), 'weeks')

# ---------------------------------------------------------------- movers (SCOPE §22): who moved in the last full week, against his own season
MV_FLOOR = {'snaps': 12.0, 'targets': 6.0, 'epa': 4.0}; MV_MING = 2; MV_PER = 5; MV_DEBUT = 25; MV_START = 50; MV_PRIOR = 30
done = [int(w) for w, gl in weeks.items() if gl and all(g['status'] == 'final' for g in gl)]
mv_week = max(done) if done else None
def mover_rows():
    rows = []; played_n = 0
    for t, pl in team_players.items():
        for p in pl:
            wk = {int(w['week']): w for w in p['weeks']}; sn = {int(k): v for k, v in p['snaps'].items()}
            played = sorted(set(wk) | set(sn))
            if mv_week not in played: continue
            played_n += 1
            prior = [w for w in played if w < mv_week]
            wrow = wk.get(mv_week); tm_wk = (wrow or {}).get('team') or p['snapteam'].get(str(mv_week)) or t
            opp = (wrow or {}).get('opponent_team') or next((g['away'] if g['home'] == tm_wk else g['home'] for g in weeks.get(str(mv_week), []) if tm_wk in (g['home'], g['away'])), None)
            base = dict(id=p['id'], name=p['name'], team=t, pos=p['pos'], num=p['num'], photo=p['photo'], g=len(prior), opp=opp)
            def val(kind, w):
                r = wk.get(w)
                if kind == 'snaps': return sn.get(w)
                if r is None: return 0.0
                if kind == 'targets': return 100 * _num(r.get('target_share'))
                if p['pos'] == 'QB': return _num(r.get('passing_epa'))
                if p['pos'] in ('RB', 'FB'): return _num(r.get('rushing_epa')) + _num(r.get('receiving_epa'))
                return _num(r.get('receiving_epa'))
            now_sn = sn.get(mv_week)
            if played[0] == mv_week and now_sn is not None and now_sn >= MV_DEBUT: rows.append(('face', dict(base, kind='debut', now=now_sn)))
            elif prior and now_sn is not None and now_sn >= MV_START and max([sn.get(w) or 0 for w in prior]) <= MV_PRIOR: rows.append(('face', dict(base, kind='first start', now=now_sn, before=max(sn.get(w) or 0 for w in prior))))
            if len(prior) < MV_MING: continue
            for k in MV_FLOOR:
                if k == 'targets' and p['pos'] == 'QB': continue
                now = val(k, mv_week)
                if now is None: continue
                pv = [v for v in (val(k, w) for w in prior) if v is not None]
                if not pv: continue
                before = sum(pv) / len(pv); delta = now - before
                if abs(delta) < MV_FLOOR[k]: continue
                series = [(None if w not in played else val(k, w)) for w in range(1, mv_week + 1)]
                rows.append((k, dict(base, before=round(before, 1), now=round(now, 1), delta=round(delta, 1), s=[None if v is None else round(v, 1) for v in series])))
    return rows, played_n
if mv_week:
    mrows, mv_n = mover_rows()
    movers = dict(season=season, week=mv_week, n=mv_n, faces=[r for k, r in mrows if k == 'face'])
    for k in MV_FLOOR:
        rs = [r for kk, r in mrows if kk == k]
        movers[k] = dict(up=sorted([r for r in rs if r['delta'] > 0], key=lambda r: -r['delta'])[:MV_PER], down=sorted([r for r in rs if r['delta'] < 0], key=lambda r: r['delta'])[:MV_PER])
    json.dump(clean(movers), open(f'{out}/players/movers.json', 'w'), separators=(',', ':'))

# ---------------------------------------------------------------- career (SCOPE §24): season totals per player, regular season only
# data/career/{year}.json is built once per finished season from stats_player_week_{year}.csv.gz and never rebuilt; this season comes from the weekly stats already loaded.
import urllib.request, gzip, io
CAREER_FROM = 1999
CAREER_SUM = ['completions', 'attempts', 'passing_yards', 'passing_tds', 'passing_interceptions', 'sacks_suffered', 'passing_epa', 'carries', 'rushing_yards', 'rushing_tds', 'rushing_epa',
              'receptions', 'targets', 'receiving_yards', 'receiving_tds', 'receiving_epa', 'receiving_first_downs', 'receiving_air_yards', 'receiving_yards_after_catch']
def season_totals(wk):
    """{player id: season row} from a weekly stats frame: games, last team, sums, mean target share."""
    if not len(wk): return {}
    if 'season_type' in wk.columns: wk = wk[wk.season_type == 'REG']
    tcol = 'team' if 'team' in wk.columns else 'recent_team'; out = {}
    for pid, g in wk.sort_values('week').groupby('player_id'):
        row = dict(season=int(g.season.iloc[0]), g=int(len(g)), team=str(g[tcol].iloc[-1]), teams=[str(x) for x in dict.fromkeys(g[tcol].astype(str))],
                   name=c(g.player_display_name.iloc[-1]) if 'player_display_name' in g.columns else None, pos=c(g.position.iloc[-1]) if 'position' in g.columns else None)
        for k in CAREER_SUM:
            v = float(g[k].fillna(0).sum()) if k in g.columns else 0.0
            row[k] = round(v, 2) if 'epa' in k else int(round(v))
        ts = g['target_share'].dropna() if 'target_share' in g.columns else []
        row['tshare'] = round(float(ts.mean()), 4) if len(ts) else None
        out[pid] = row
    return out
os.makedirs(f'{out}/career', exist_ok=True)
career_years = {}
for y in range(CAREER_FROM, season):
    cp = f'{out}/career/{y}.json'
    if os.path.exists(cp):
        career_years[y] = json.load(open(cp)); continue
    try:
        raw = urllib.request.urlopen(f'https://github.com/nflverse/nflverse-data/releases/download/stats_player/stats_player_week_{y}.csv.gz', timeout=120).read()
        wk = pd.read_csv(io.BytesIO(gzip.decompress(raw)), low_memory=False)
        career_years[y] = season_totals(wk)
        json.dump(clean(career_years[y]), open(cp, 'w'), separators=(',', ':'))
        print('career: built', y, len(career_years[y]), 'players')
    except Exception as e:
        print('career: skipped', y, repr(e)[:120])
career_now = season_totals(stats) if len(stats) else {}
for t, pl in team_players.items():
    rows = {}
    for p in pl:
        hist = [career_years[y][p['id']] for y in sorted(career_years) if p['id'] in career_years[y]]
        if p['id'] in career_now: hist.append(career_now[p['id']])
        if hist: rows[p['id']] = [{k: v for k, v in r.items() if k not in ('name', 'pos')} for r in hist]
    os.makedirs(f'{out}/players/career', exist_ok=True)
    json.dump(clean(dict(season=season, team=t, through=through_week, players=rows)), open(f'{out}/players/career/{t}.json', 'w'), separators=(',', ':'))

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
