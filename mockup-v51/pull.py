"""Mock v51 data (SCOPE §78): Week 11 fantasy page numbers from the repo's synced data (through Week 10). Writes _fan.json."""
import json, glob, os, re, unicodedata
from collections import defaultdict
here = os.path.dirname(os.path.abspath(__file__)); D = os.path.join(here, '..', 'data')
S = json.load(open(os.path.join(D, 'schedule.json'))); THRU = int(S['through_week']); NEXT = int(S['current_week'])
F = json.load(open(os.path.join(D, 'fantasy.json'))); T = json.load(open(os.path.join(D, 'teams.json')))
def slug(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower(); s = re.sub(r"['’.]", '', s); return re.sub(r'[^a-z0-9]+', '-', s).strip('-')
P = {}; byteam = defaultdict(list)
for f in glob.glob(os.path.join(D, 'players', '*.json')):
    if not re.search(r'/[A-Z]{2,3}\.json$', f): continue
    d = json.load(open(f))
    for p in d['players']:
        P[p['id']] = dict(id=p['id'], name=p['name'], pos=p['pos'], team=d['team'], num=p.get('num'), photo=p.get('photo'), weeks={int(w['week']): w for w in p.get('weeks', [])}, snaps={int(k): v for k, v in (p.get('snaps') or {}).items()}, st=p.get('status'), inj=p.get('inj'), fan=p.get('fan'))
        byteam[d['team']].append(p['id'])
nxt = {}  # team -> (opp, home)
games = S['weeks'][str(NEXT)]
for g in games: nxt[g['away']] = (g['home'], False, g); nxt[g['home']] = (g['away'], True, g)
teams = sorted(byteam); byes = [t for t in teams if t not in nxt]
# team weekly totals from the plays (every target and designed run), so players missing from a roster can't inflate a share
tot = defaultdict(lambda: defaultdict(float)); tgt = defaultdict(lambda: defaultdict(float))
for f in glob.glob(os.path.join(D, 'plays', '*.json')):
    d = json.load(open(f)); K = d['keys']
    for raw in d['plays']:
        r = dict(zip(K, raw)); wk = int(r['g'].split('_')[1])
        if r['pt'] == 'pass' and r['k'] in ('comp', 'inc', 'int') and r['pid']: tot[d['team']][wk] += 1; tgt[d['team']][wk] += 1
        elif r['pt'] == 'run' and r['k'] == 'run' and r['pid']: tot[d['team']][wk] += 1
def team_weeks(t): return sorted(w for w in tot[t] if tot[t][w] > 0 and w <= THRU)
def share(p, w):
    r = p['weeks'].get(w)
    if not r: return 0.0
    if p['pos'] == 'RB': return 100 * ((r.get('carries') or 0) + (r.get('targets') or 0)) / tot[p['team']][w] if tot[p['team']][w] else 0
    return 100 * (r.get('targets') or 0) / tgt[p['team']][w] if tgt[p['team']][w] else 0
RANKI = 1  # half PPR
def matchup(team, pos):
    if team not in nxt: return None
    opp, home, g = nxt[team]; rk = F['rank'][opp][pos][RANKI]
    return dict(opp=opp, home=home, rk=rk, rks=F['rank'][opp][pos], allows=F['allow'][opp][pos], tier='soft' if rk <= 8 else ('tough' if rk >= 25 else ''), allow=F['allow'][opp][pos][RANKI])
movers = {}
for pos in ('RB', 'WR', 'TE'):
    rows = []
    for i, p in P.items():
        if p['pos'] != pos or p['team'] not in nxt: continue  # on bye next week: not on this page's lists
        tw = team_weeks(p['team'])
        if len(tw) < 5: continue
        last = tw[-3:]; before = tw[:-3]
        played = lambda ws: [w for w in ws if p['snaps'].get(w, 0) > 0]
        lp, bp = played(last), played(before)
        if len(lp) < 2 or len(bp) < 2: continue
        now = sum(share(p, w) for w in lp) / len(lp)
        bef = sum(share(p, w) for w in bp) / len(bp) if bp else 0.0
        spark = [round(share(p, w), 1) if p['snaps'].get(w, 0) > 0 else None for w in tw]
        fp = [ (r.get('fantasy_points') or 0 + 0) for r in [p['weeks'].get(w, {}) for w in lp]]
        sd = [p['weeks'].get(w, {}).get('fantasy_points') or 0 for w in lp]; pp = [p['weeks'].get(w, {}).get('fantasy_points_ppr') or 0 for w in lp]
        hppr = [(a + b) / 2 for a, b in zip(sd, pp)]; ppg3s = [round(sum(x) / len(x), 1) for x in (sd, hppr, pp)]
        rows.append(dict(id=i, name=p['name'], team=p['team'], pos=pos, num=p['num'], photo=p['photo'], now=round(now, 1), before=round(bef, 1), delta=round(now - bef, 1), g_before=len(bp), spark=spark, ppg3=round(sum(hppr) / len(hppr), 1), ppg3s=ppg3s, m=matchup(p['team'], pos), slug=slug(p['name']), inj=p['inj']))
    up = sorted([r for r in rows if r['now'] >= 10], key=lambda r: -r['delta'])[:5]
    down = sorted([r for r in rows if r['before'] >= 15], key=lambda r: r['delta'])[:3]
    movers[pos] = dict(up=up, down=down)
# red zone, last 3 weeks of plays (inside the 20; inside the 5)
rz = defaultdict(lambda: dict(t=0, c=0, t5=0, c5=0, td=0)); rzteam = defaultdict(int)
for f in glob.glob(os.path.join(D, 'plays', '*.json')):
    d = json.load(open(f)); K = d['keys']; team = d['team']; tw = team_weeks(team); last = set(tw[-3:])
    for raw in d['plays']:
        r = dict(zip(K, raw)); wk = int(r['g'].split('_')[1])
        if wk not in last or r['yl'] is None or r['yl'] > 20 or not r['pid']: continue
        if r['pt'] == 'pass' and r['k'] in ('comp', 'inc', 'int'): k = 't'
        elif r['pt'] == 'run' and r['k'] == 'run': k = 'c'
        else: continue
        x = rz[r['pid']]; x[k] += 1; x[k + '5'] += 1 if r['yl'] <= 5 else 0; x['td'] += 1 if r['td'] else 0; rzteam[team] += 1
rzl = []
for i, x in rz.items():
    p = P.get(i)
    if not p or p['pos'] not in ('RB', 'WR', 'TE', 'QB'): continue
    o = x['t'] + x['c']
    rzl.append(dict(id=i, name=p['name'], team=p['team'], pos=p['pos'], t=x['t'], c=x['c'], in5=x['t5'] + x['c5'], td=x['td'], opp=o, share=round(100 * o / rzteam[p['team']]) if rzteam[p['team']] else 0, m=matchup(p['team'], p['pos'] if p['pos'] != 'QB' else 'QB'), slug=slug(p['name'])))
rzk = lambda r: (-r['opp'], -r['in5'], -r['td'])
rz_rb = sorted([r for r in rzl if r['pos'] == 'RB' and r['team'] in nxt], key=rzk)[:6]
rz_rec = sorted([r for r in rzl if r['pos'] in ('WR', 'TE') and r['team'] in nxt], key=rzk)[:6]
grid = []
for g in games:
    row = dict(id=g['id'], away=g['away'], home=g['home'], day=g['day'], time=g['time'], total=g.get('total'), spread=g.get('spread'), sides={})
    for t, o in ((g['away'], g['home']), (g['home'], g['away'])):
        row['sides'][t] = {pos: dict(rk=F['rank'][o][pos][RANKI], rks=F['rank'][o][pos], allows=F['allow'][o][pos]) for pos in ('QB', 'RB', 'WR', 'TE')}
    grid.append(row)
bye = []
for t in byes:
    pl = [p for p in P.values() if p['team'] == t and p['pos'] in ('QB', 'RB', 'WR', 'TE') and p.get('fan') and p['fan'].get('s', {}).get('half', {}).get('g', 0) >= 3]
    pl = sorted(pl, key=lambda p: -p['fan']['s']['half']['ppg'])[:3]
    bye.append(dict(t=t, top=[dict(name=p['name'], pos=p['pos'], ppg=[p['fan']['s'][f]['ppg'] for f in ('std', 'half', 'ppr')], slug=slug(p['name'])) for p in pl]))
names = {t: (T[t].get('nick') or T[t].get('name') or t) if isinstance(T, dict) and t in T else t for t in teams}
json.dump(dict(next=NEXT, thru=THRU, movers=movers, rz_rb=rz_rb, rz_rec=rz_rec, grid=grid, byes=byes, bye=bye, teams=T if isinstance(T, dict) else {}), open(os.path.join(here, '_fan.json'), 'w'))
for pos, m in movers.items():
    print(pos, 'UP', [(r['name'], r['team'], r['before'], r['now'], r['m'] and (r['m']['opp'], r['m']['rk'])) for r in m['up']])
    print(pos, 'DOWN', [(r['name'], r['team'], r['before'], r['now']) for r in m['down']])
print('RZ RB', [(r['name'], r['team'], r['t'], r['c'], r['in5'], r['td'], r['share']) for r in rz_rb]); print('RZ REC', [(r['name'], r['team'], r['t'], r['c'], r['in5'], r['td'], r['share']) for r in rz_rec])
print('byes', byes, 'games', len(grid))
