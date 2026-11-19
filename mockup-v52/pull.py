"""Mock v52 data (SCOPE §79): the pass rushers chart from the repo's defense files (through the synced week). Writes _rush.json.
Qualifier: a defensive lineman or linebacker who played at least half his team's games, at least 40% of the defensive snaps in the games
he played, and at least one pressure a game. X = pressures a game (PFR charting), Y = sacks a game (nflverse)."""
import glob, json, os, re, statistics, unicodedata
here = os.path.dirname(os.path.abspath(__file__)); D = os.path.join(here, '..', 'data')
T = json.load(open(os.path.join(D, 'teams.json'))); S = json.load(open(os.path.join(D, 'schedule.json')))
def slug(s):
    s = unicodedata.normalize('NFKD', s).encode('ascii', 'ignore').decode().lower(); s = re.sub(r"['’.]", '', s); return re.sub(r'[^a-z0-9]+', '-', s).strip('-')
rows = []
for f in glob.glob(os.path.join(D, 'defense', '[A-Z]*.json')):
    d = json.load(open(f)); tg = d.get('g') or 0
    for p in d.get('defenders') or d.get('players') or []:
        g = p.get('g') or 0
        if p.get('grp') not in ('DL', 'LB') or not g or g * 2 < tg or (p.get('snap') or 0) < 40: continue
        prs = p.get('prs') or 0
        if prs < g: continue
        rows.append(dict(id=p['id'], n=p['name'], t=d['team'], pos=p['pos'], num=p.get('num'), g=g, snap=p.get('snap'), prs=prs, sk=p.get('sk') or 0, qbh=p.get('qbh') or 0, tfl=p.get('tfl') or 0,
                         x=round(prs / g, 2), y=round((p.get('sk') or 0) / g, 2), slug=slug(p['name'])))
mx = statistics.median(r['x'] for r in rows); my = statistics.median(r['y'] for r in rows)
# labels: the top eight on each axis
lab = {r['id'] for r in sorted(rows, key=lambda r: -r['x'])[:8]} | {r['id'] for r in sorted(rows, key=lambda r: -r['y'])[:8]}
for r in rows: r['lab'] = r['id'] in lab
lead = lambda k: max(rows, key=lambda r: (r[k], r['x']))
json.dump(dict(thru=int(S['through_week']), season=S['season'], n=len(rows), mx=mx, my=my, rows=rows,
               lead=dict(prs=lead('prs'), sk=lead('sk'), qbh=lead('qbh')), teams=T), open(os.path.join(here, '_rush.json'), 'w'))
print(len(rows), 'rushers; medians', mx, my, '; labelled', len(lab))
for k in ('prs', 'sk', 'qbh'): r = lead(k); print(k, r['n'], r['t'], r[k])
print('MIN', [(r['n'], r['x'], r['y']) for r in rows if r['t'] == 'MIN'])
