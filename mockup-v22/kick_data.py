"""Kickers, SCOPE §47 mock: every kicker's season from nflverse play-by-play, written to kickers.json.
Run: python3 kick_data.py <folder with pbp.csv.gz (this season), pbp2024.csv.gz, pbp2025.csv.gz, roster.csv>"""
import csv, gzip, json, math, sys, collections
K = sys.argv[1]
# the league make curve: a logistic on distance fitted to 2023-2025 (3,413 tries), z = (yards - 40) / 10
A, B = 2.1024, -0.9952
def p_make(d): return 1 / (1 + math.exp(-(A + B * (d - 40) / 10)))
def fx_fg(d): return 3 if d < 40 else 4 if d < 50 else 5 if d < 60 else 6   # ESPN standard: 0-39 3, 40-49 4, 50-59 5, 60+ 6; a miss -1; extra point +1, a miss -1
RANGES = [('Under 30', 0, 29), ('30–39', 30, 39), ('40–49', 40, 49), ('50–54', 50, 54), ('55+', 55, 99)]
def rng(d): return next(i for i, (_, a, b) in enumerate(RANGES) if a <= d <= b)

ros = {r['gsis_id']: r for r in csv.DictReader(open(K + '/roster.csv'))}
kk = collections.defaultdict(lambda: dict(fg=[], pat=[0, 0], ko=[0, 0, 0.0], team=None, games={}))
games = {}; cond = {}
for r in csv.DictReader(gzip.open(K + '/pbp.csv.gz', 'rt')):
    if r['season_type'] != 'REG': continue
    gid = r['game_id']
    if gid not in cond: cond[gid] = dict(roof=r['roof'], temp=r['temp'], wind=r['wind'], home=r['home_team'], away=r['away_team'], week=int(r['week']), stadium=r.get('stadium') or '')
    kid = r['kicker_player_id']
    if not kid: continue
    k = kk[kid]; k['name'] = r['kicker_player_name']
    if r['field_goal_attempt'] == '1':
        d = int(float(r['kick_distance'])); res = r['field_goal_result']
        k['team'] = r['posteam']
        el = 3600 - int(float(r['game_seconds_remaining'] or 0))
        k['fg'].append(dict(d=d, res=res, wk=int(r['week']), gid=gid, opp=r['defteam'], q=r['qtr'], t=r['time'], wpa=float(r['wpa'] or 0), wp=float(r['wp'] or 0),
                            sd=int(float(r['score_differential'] or 0)), ps=r['posteam_score'], ds=r['defteam_score'], yl=int(float(r['yardline_100']))))
        g = k['games'].setdefault(gid, dict(wk=int(r['week']), opp=r['defteam'], home=r['home_team'] == r['posteam'], fgm=0, fga=0, lng=0, pm=0, pa=0, fx=0))
        g['fga'] += 1
        if res == 'made': g['fgm'] += 1; g['lng'] = max(g['lng'], d); g['fx'] += fx_fg(d)
        else: g['fx'] -= 1
    elif r['extra_point_attempt'] == '1':
        k['team'] = r['posteam']; good = r['extra_point_result'] == 'good'
        k['pat'][1] += 1; k['pat'][0] += good
        g = k['games'].setdefault(gid, dict(wk=int(r['week']), opp=r['defteam'], home=r['home_team'] == r['posteam'], fgm=0, fga=0, lng=0, pm=0, pa=0, fx=0))
        g['pa'] += 1; g['pm'] += good; g['fx'] += 1 if good else -1
    elif r['kickoff_attempt'] == '1':
        k['ko'][0] += 1; k['ko'][1] += r['touchback'] == '1'; k['ko'][2] += float(r['kick_distance'] or 0)

# where the other team starts after each kickoff: its first snap of the drive that follows (own yard line), onside kicks left out
start = collections.defaultdict(list); pend = None
for r in csv.DictReader(gzip.open(K + '/pbp.csv.gz', 'rt')):
    if r['season_type'] != 'REG': continue
    if r['kickoff_attempt'] == '1' and r['kicker_player_id'] and r.get('own_kickoff_recovery') != '1' and 'onside' not in r['desc'].lower():
        pend = (r['game_id'], r['posteam'], r['kicker_player_id']); continue
    if pend and r['game_id'] == pend[0] and r['play_type'] in ('pass', 'run', 'punt', 'field_goal', 'qb_kneel', 'qb_spike'):
        if r['posteam'] == pend[1] and r['yardline_100']: start[pend[2]].append(100 - int(float(r['yardline_100'])))
        pend = None
    elif pend and r['game_id'] != pend[0]: pend = None
out = []
for kid, k in kk.items():
    fg = k['fg']
    if not fg and not k['pat'][1]: continue
    m = sum(x['res'] == 'made' for x in fg); exp = sum(p_make(x['d']) for x in fg)
    by = [[0, 0, 0.0] for _ in RANGES]
    for x in fg: b = by[rng(x['d'])]; b[1] += 1; b[0] += x['res'] == 'made'; b[2] += p_make(x['d'])
    ro = ros.get(kid, {})
    out.append(dict(id=kid, name=ro.get('full_name') or k['name'], short=k['name'], team=k['team'], num=ro.get('jersey_number'), photo=ro.get('headshot_url'),
                    birth=ro.get('birth_date'), college=ro.get('college'), ht=ro.get('height'), wt=ro.get('weight'), exp=ro.get('years_exp'),
                    draft=[ro.get('draft_number'), ro.get('entry_year')], fgm=m, fga=len(fg), blk=sum(x['res'] == 'blocked' for x in fg),
                    lng=max([x['d'] for x in fg if x['res'] == 'made'] or [0]), oe=round(m - exp, 2), by=[[a, b, round(c, 2)] for a, b, c in by],
                    pat=k['pat'], ko=[k['ko'][0], k['ko'][1], round(k['ko'][2] / k['ko'][0], 1) if k['ko'][0] else 0],
                    pts=3 * m + k['pat'][0], kstart=round(sum(start[kid]) / len(start[kid]), 1) if start[kid] else None, kn=len(start[kid]), fx=sum(g['fx'] for g in k['games'].values()), g=len(k['games']),
                    avgd=round(sum(x['d'] for x in fg) / len(fg), 1) if fg else 0, kicks=fg,
                    games=sorted(({**g, 'gid': gid, 'roof': cond[gid]['roof'], 'temp': cond[gid]['temp'], 'wind': cond[gid]['wind'], 'stadium': cond[gid]['stadium']} for gid, g in k['games'].items()), key=lambda g: g['wk'])))
# league by range, this season
lg = [[0, 0] for _ in RANGES]
for k in out:
    for i, b in enumerate(k['by']): lg[i][0] += b[0]; lg[i][1] += b[1]
# career: regular season FG / XP from the two prior seasons
car = collections.defaultdict(dict)
for y in (2024, 2025):
    for r in csv.DictReader(gzip.open(f'{K}/pbp{y}.csv.gz', 'rt')):
        if r['season_type'] != 'REG' or not r['kicker_player_id']: continue
        c = car[r['kicker_player_id']].setdefault(y, dict(fgm=0, fga=0, lng=0, f50=[0, 0], pm=0, pa=0, g=set(), team=r['posteam']))
        if r['field_goal_attempt'] == '1':
            d = int(float(r['kick_distance'])); ok = r['field_goal_result'] == 'made'
            c['fga'] += 1; c['fgm'] += ok; c['g'].add(r['game_id']); c['team'] = r['posteam']
            if ok: c['lng'] = max(c['lng'], d)
            if d >= 50: c['f50'][1] += 1; c['f50'][0] += ok
        elif r['extra_point_attempt'] == '1':
            c['pa'] += 1; c['pm'] += r['extra_point_result'] == 'good'; c['g'].add(r['game_id'])
for k in out:
    k['career'] = {y: {**v, 'g': len(v['g'])} for y, v in car.get(k['id'], {}).items()}
json.dump(dict(curve=[A, B], ranges=[r[0] for r in RANGES], league=lg, kickers=sorted(out, key=lambda k: -k['fga'])), open('kickers.json', 'w'), separators=(',', ':'))
q = [k for k in out if k['fga'] >= 8]
print(len(out), 'kickers;', len(q), 'with 8+ tries; league by range', lg)
r = next(k for k in out if 'Reichard' in k['name'])
print({x: r[x] for x in ('kstart','kn','name', 'team', 'num', 'fgm', 'fga', 'lng', 'oe', 'by', 'pat', 'ko', 'pts', 'fx', 'g', 'avgd', 'college', 'birth', 'exp', 'draft')})
print('career', r['career'])
