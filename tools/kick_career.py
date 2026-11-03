"""Kickers' career files (SCOPE §47): data/career/k{year}.json, one per finished season, from nflverse's weekly player stats.
build_data.py imports k_season() and builds a missing year itself; this script prebuilds them so a build never has to.

  python3 tools/kick_career.py data/career 1999 2025

Each file: {player id: {season, team, g, fgm, fga, lng, f50: [made, tried], pm, pa}}, regular season only, every player
with a field goal or extra point try. Standard library only."""
import csv, gzip, io, json, os, sys, urllib.request

URL = 'https://github.com/nflverse/nflverse-data/releases/download/stats_player/stats_player_week_{y}.csv.gz'

def _n(v):
    try:
        f = float(v); return 0.0 if f != f else f
    except (TypeError, ValueError):
        return 0.0

def k_season(rows, year):
    """{id: season line} from the weekly rows (dicts) of one season."""
    out = {}
    for r in sorted(rows, key=lambda r: _n(r.get('week'))):
        if (r.get('season_type') or 'REG') != 'REG': continue
        fga, pa = _n(r.get('fg_att')), _n(r.get('pat_att'))
        if not fga and not pa: continue
        pid = r.get('player_id')
        if not pid: continue
        k = out.setdefault(pid, dict(season=int(year), team=None, g=0, fgm=0, fga=0, lng=0, f50=[0, 0], pm=0, pa=0))
        k['team'] = r.get('team') or r.get('recent_team') or k['team']; k['g'] += 1
        k['fgm'] += int(_n(r.get('fg_made'))); k['fga'] += int(fga); k['lng'] = max(k['lng'], int(_n(r.get('fg_long'))))
        m50 = _n(r.get('fg_made_50_59')) + _n(r.get('fg_made_60_')); x50 = _n(r.get('fg_missed_50_59')) + _n(r.get('fg_missed_60_'))
        k['f50'][0] += int(m50); k['f50'][1] += int(m50 + x50)
        k['pm'] += int(_n(r.get('pat_made'))); k['pa'] += int(pa)
    return out

def fetch_year(year, timeout=120):
    raw = urllib.request.urlopen(URL.format(y=year), timeout=timeout).read()
    rows = list(csv.DictReader(io.StringIO(gzip.decompress(raw).decode('utf-8'))))
    if rows and 'fg_att' not in rows[0]: return None   # no kicking columns that year
    return k_season(rows, year)

if __name__ == '__main__':
    d, a, b = sys.argv[1], int(sys.argv[2]), int(sys.argv[3])
    os.makedirs(d, exist_ok=True)
    for y in range(a, b + 1):
        p = f'{d}/k{y}.json'
        if os.path.exists(p): print(y, 'kept'); continue
        s = fetch_year(y)
        if s is None: print(y, 'no kicking columns'); continue
        json.dump(s, open(p, 'w'), separators=(',', ':')); print(y, len(s), 'kickers')
