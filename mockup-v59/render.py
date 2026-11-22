"""Mock v59 (SCOPE §86): three more season boards from the real Week 10 box scores, drawn with v58's board layout. Usage: python3 render.py <art dir> <out dir>."""
import base64, collections, glob, html, io, json, os, sys
from PIL import Image
here = os.path.dirname(os.path.abspath(__file__)); root = os.path.join(here, '..'); ART, OUT = sys.argv[1], sys.argv[2]; N = 10
v58 = os.path.join(root, 'mockup-v58', 'render.py'); src = open(v58).read().split('def league(')[0]; M = {'__file__': v58}; exec(src, M)
T = json.load(open(os.path.join(root, 'data', 'teams.json'))); T = T.get('teams', T)
team = {x['abbr']: x for x in T} if isinstance(T, list) else T
people = {}
for f in glob.glob(os.path.join(root, 'data', 'players', '*.json')):
    P_ = json.load(open(f)).get('players', {})
    for i, p in (P_.items() if isinstance(P_, dict) else ((x.get('id'), x) for x in P_)):
        if isinstance(p, dict): people[p.get('id') or i] = p
rtd, rec, sk, tm = collections.Counter(), collections.Counter(), collections.Counter(), {}
for w in range(1, N + 1):
    b = json.load(open(os.path.join(root, 'data', 'box', 'w%d.json' % w))); K = b['keys']
    for g in b['games'].values():
        for s, o in (('away', 'home'), ('home', 'away')):
            t = g[s]['team']; sk[t] += 0
            for r in g[o]['off']: sk[t] += dict(zip(K, r))['sacks_suffered']
            for r in g[s]['off']:
                x = dict(zip(K, r)); rtd[x['id']] += x['rushing_tds']; rec[x['id']] += x['receptions']; tm[x['id']] = t
def last(n): return [w for w in n.split() if w not in ('Jr.', 'Sr.', 'II', 'III', 'IV')][-1]
def cut(rows, n=11, most=13):
    if len(rows) <= n or rows[n]['v'] != rows[n - 1]['v']: return rows[:n]
    e = n
    while e < len(rows) and rows[e]['v'] == rows[n - 1]['v']: e += 1
    if e <= most: return rows[:e]
    s = n - 1
    while s > 0 and rows[s - 1]['v'] == rows[n - 1]['v']: s -= 1
    return rows[:s]
def players(c):
    R = [dict(id=i, full=people[i]['name'], t=tm[i], v=int(v)) for i, v in c.items() if v > 0 and i in people]
    R.sort(key=lambda r: (-r['v'], last(r['full']), r['full'])); return cut(R)
for r in players(rec)[:1] + players(rtd)[:2]: M['D']['people'].setdefault(r['id'], dict(name=r['full'], num=people[r['id']].get('num')))
names = {}
def nm(t):
    x = team[t]; n = x.get('nick') or M['D']['teams'][t]['nick']; f = x.get('name') or x.get('full') or n; c = f[:-len(n)].strip() if f.endswith(n) else f; names[c + ' ' + n] = (c, n); return c + ' ' + n
S = [dict(id=t, full=nm(t), t=t, v=int(v)) for t, v in sk.items()]; S.sort(key=lambda r: (-r['v'], r['full'])); S = cut(S)
base = M['split']; M['split'] = lambda n: names.get(n) or base(n)
os.makedirs(OUT, exist_ok=True); has = lambda i: os.path.exists(os.path.join(ART, 'heads', i + '.png'))
B = [('rtd', ['LEAGUE LEADERS', 'RUSHING TDS'], players(rtd), 'RUSHING TDS'), ('rec', ['LEAGUE LEADERS', 'RECEPTIONS'], players(rec), 'RECEPTIONS'), ('sacks', ['MOST SACKS', 'BY A DEFENSE'], S, 'SACKS')]
for th in ('black', 'paper'):
    for k, lines, R, lab in B: M['board'](th, k, lines, 'THROUGH WEEK %d' % N, R, lab, photo=k != 'sacks' and has(R[0]['id']))
json.dump({k: [(r['full'], r['t'], r['v']) for r in R] for k, _, R, _ in B}, open(os.path.join(OUT, 'boards.json'), 'w'), indent=0)
for k, _, R, _ in B: print(k, len(R), [(r['full'], r['v']) for r in R])
