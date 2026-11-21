"""Mock v53 data (SCOPE §80): a chart on every position tab of Leaders, from players/leaders.json (the boards' own pools and metrics).
Writes _charts.json: per tab, its axes and its qualified players."""
import json, os, statistics
here = os.path.dirname(os.path.abspath(__file__)); D = os.path.join(here, '..', 'data')
L = json.load(open(os.path.join(D, 'players', 'leaders.json'))); T = json.load(open(os.path.join(D, 'teams.json')))
P = L['players']
TABS = {
    'wr': dict(x=('tshare', 'Target share', 'pct'), y=('epat', 'EPA per target', 'sgn'), unit='targets'),
    'te': dict(x=('tshare', 'Target share', 'pct'), y=('epat', 'EPA per target', 'sgn'), unit='targets'),
    'rb': dict(x=('tpg', 'Touches per game', 'f1'), y=('ypt', 'Yards per touch', 'f1'), unit='touches'),
    'qb': dict(x=('epadb', 'EPA per dropback', 'sgn'), y=('cpoe', 'CPOE', 'sgn1'), unit='attempts'),
}
out = {}
for g, c in TABS.items():
    G = L['groups'][g]['g']; mn = L['min'][g]
    xs = {r[0]: (r[1], r[2]) for r in G[c['x'][0]] if r[2] >= mn}; ys = {r[0]: r[1] for r in G[c['y'][0]] if r[2] >= mn}
    rows = [dict(id=i, n=P[i][0], t=P[i][1], pos=P[i][2], x=xs[i][0], y=ys[i], u=xs[i][1]) for i in xs if i in ys and i in P and P[i][1]]
    out[g] = dict(c, min=mn, rows=rows, mx=statistics.median(r['x'] for r in rows), my=statistics.median(r['y'] for r in rows))
    xr = sorted(rows, key=lambda r: -r['x'])[:3]; yr = sorted(rows, key=lambda r: -r['y'])[:3]
    print(g, len(rows), 'x', [(r['n'], round(r['x'], 3)) for r in xr], 'y', [(r['n'], round(r['y'], 3)) for r in yr])
json.dump(dict(through=L['through'], season=L['season'], tabs=out, teams=T), open(os.path.join(here, '_charts.json'), 'w'))
