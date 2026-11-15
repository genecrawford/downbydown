"""The season draft and combine boards (SCOPE §63): data/draft/{year}.json for every draft since 1980, data/combine/{year}.json for every
combine since 2000, and data/boards.json listing the years. Standard library only, so it runs anywhere the nightly build does and locally.

    python3 tools/build_boards.py <draft_picks.csv> <combine.csv> <data dir>

Rules shared with the player page (§62): combine percentiles are against every player at the same position group tested since 2000 (WR and
TE apart), the timed drills count faster as better, and the combine score averages the athletic drills he ran (two at least). Teams: each
pick keeps the team as it was that year (t: "BAL" for the 1982 Colts, "HOU" for the Oilers) and the franchise it belongs to today (f), which
the site's logos and team filter use."""
import csv, json, os, sys, bisect, collections

draft_path, combine_path, out = sys.argv[1:4]
D_ALL = list(csv.DictReader(open(draft_path, newline='', encoding='utf-8')))
C_ALL = list(csv.DictReader(open(combine_path, newline='', encoding='utf-8')))

# ---------------------------------------------------------------- teams then and now
SITE = {'GNB': 'GB', 'KAN': 'KC', 'LVR': 'LV', 'NOR': 'NO', 'NWE': 'NE', 'SFO': 'SF', 'TAM': 'TB', 'LAR': 'LA', 'OAK': 'LV', 'RAI': 'LV', 'SDG': 'LAC', 'RAM': 'LA', 'PHO': 'ARI'}
def teams(code, year):
    """(as it was that year, today's franchise). BAL before 1984 is the Colts, HOU before 1997 the Oilers, STL before 1988 the Cardinals."""
    if code == 'BAL' and year <= 1983: return 'BAL', 'IND'
    if code == 'HOU' and year <= 1996: return 'HOU', 'TEN'
    if code == 'STL': return 'STL', 'ARI' if year <= 1987 else 'LA'
    now = SITE.get(code, code)
    return {'OAK': 'OAK', 'RAI': 'RAI', 'SDG': 'SD', 'RAM': 'RAM', 'PHO': 'PHO'}.get(code, now), now

# ---------------------------------------------------------------- positions
DPOS = {'OLB': 'LB', 'ILB': 'LB', 'OG': 'G', 'DL': 'DT', 'NT': 'DT', 'DB': 'S', 'FB': 'RB', 'EDGE': 'DE', 'SAF': 'S', 'OL': 'G', 'T': 'OT', 'KR': 'WR', 'HB': 'RB'}
dpos = lambda p: DPOS.get(str(p), str(p))
CGRP = lambda p: {'FB': 'RB', 'HB': 'RB', 'SAF': 'S', 'OLB': 'LB', 'ILB': 'LB', 'EDGE': 'DE', 'OG': 'G', 'NT': 'DT', 'DL': 'DT', 'DB': 'S', 'OL': 'G'}.get(str(p), str(p))

# ---------------------------------------------------------------- the combine pools
DRILLS = ('forty', 'vertical', 'broad_jump', 'bench', 'cone', 'shuttle'); LOW = {'forty', 'cone', 'shuttle'}
def inches(h):
    try: f_, i_ = str(h).split('-'); return int(f_) * 12 + int(i_)
    except Exception: return None
def val(x, k):
    if k == 'ht': return inches(x.get('ht'))
    try:
        v = float(x.get(k) or '')
        return None if v != v else v
    except ValueError: return None
POOL = collections.defaultdict(list)
for x in C_ALL:
    g = CGRP(x.get('pos'))
    for k in ('ht', 'wt') + DRILLS:
        v = val(x, k)
        if v is not None: POOL[(g, k)].append(v)
for k in POOL: POOL[k].sort()
ALL_TIME = {k: sorted(v for v in (val(x, k) for x in C_ALL) if v is not None) for k in DRILLS}
def pct(g, k, v):
    p = POOL.get((g, k)) or []
    if v is None or not p: return None
    beat = len(p) - bisect.bisect_right(p, v) if k in LOW else bisect.bisect_left(p, v)
    return round(100 * beat / len(p))
def score(x):
    g = CGRP(x.get('pos')); ps = [pct(g, k, val(x, k)) for k in DRILLS if val(x, k) is not None]
    return round(sum(ps) / len(ps)) if len(ps) >= 2 else None
CB_BY_PFR = {x['pfr_id']: x for x in C_ALL if x.get('pfr_id')}
CB_BY_NAME = {(x.get('player_name'), x.get('season')): x for x in C_ALL}

# ---------------------------------------------------------------- the player pages the boards can link to
try: HAVE = {p['id'] for p in json.load(open(os.path.join(out, 'players', 'index.json')))['players']}
except Exception: HAVE = set()

def num(s):
    try:
        v = float(s or 0)
        return 0 if v != v else v
    except ValueError: return 0
def n_(v): return int(v) if float(v).is_integer() else round(v, 1)

def dump(rel, obj):
    p = os.path.join(out, rel); os.makedirs(os.path.dirname(p), exist_ok=True)
    json.dump(obj, open(p, 'w', encoding='utf-8'), separators=(',', ':'), ensure_ascii=False)

# ---------------------------------------------------------------- data/draft/{year}.json
D_KEYS = ['p', 'r', 't', 'f', 'n', 'pos', 'sc', 'cs', 'id', 'pg', 'cat', 'side', 'g', 'pb', 'ap', 'hof', 'py', 'ptd', 'ry', 'rec', 'recy', 'tkl', 'sk', 'int']
DRAFTED_BY = {}  # pfr id -> (year, round, pick, franchise), for the combine board's Drafted column
by_year = collections.defaultdict(list)
for x in D_ALL:
    try: by_year[int(x['season'])].append(x)
    except ValueError: continue
draft_years = sorted(by_year)
for y in draft_years:
    rows = []
    for x in sorted(by_year[y], key=lambda x: int(num(x['pick']))):
        t, f = teams(x['team'], y)
        cb = CB_BY_PFR.get(x.get('pfr_player_id')) or CB_BY_NAME.get((x.get('pfr_player_name'), str(y)))
        gid = x.get('gsis_id') or None
        rows.append([int(num(x['pick'])), int(num(x['round'])), t, f, x.get('pfr_player_name') or '', dpos(x.get('position')), x.get('college') or '', score(cb) if cb else None,
                     gid, 1 if gid and gid in HAVE else 0, x.get('category') or '', x.get('side') or '', int(num(x.get('games'))), int(num(x.get('probowls'))), int(num(x.get('allpro'))),
                     1 if str(x.get('hof')).upper() == 'TRUE' else 0, int(num(x.get('pass_yards'))), int(num(x.get('pass_tds'))), int(num(x.get('rush_yards'))), int(num(x.get('receptions'))),
                     int(num(x.get('rec_yards'))), int(num(x.get('def_solo_tackles'))), n_(num(x.get('def_sacks'))), int(num(x.get('def_ints')))])
        if x.get('pfr_player_id'): DRAFTED_BY[x['pfr_player_id']] = (y, int(num(x['round'])), int(num(x['pick'])), f)
    dump(f'draft/{y}.json', dict(y=y, keys=D_KEYS, rows=rows))

# ---------------------------------------------------------------- data/combine/{year}.json
C_KEYS = ['n', 'pos', 'g', 'sc', 'ht', 'wt'] + list(DRILLS) + [k + '_p' for k in DRILLS] + ['cs', 'dr']
cyears = sorted({int(x['season']) for x in C_ALL if str(x.get('season', '')).isdigit()})
for y in cyears:
    C = [x for x in C_ALL if x.get('season') == str(y)]
    rows = []
    for x in C:
        g = CGRP(x.get('pos')); vs = [val(x, k) for k in DRILLS]
        dr = DRAFTED_BY.get(x.get('pfr_id')) if x.get('pfr_id') else None
        if dr is None and x.get('draft_ovr'):  # the combine file's own draft columns, when the ids don't meet
            dr = (y, int(num(x.get('draft_round'))), int(num(x.get('draft_ovr'))), None)
        rows.append([x.get('player_name') or '', x.get('pos') or '', g, x.get('school') or '', val(x, 'ht'), n_(val(x, 'wt')) if val(x, 'wt') is not None else None] + [n_(v) if v is not None else None for v in vs] + [pct(g, k, v) for k, v in zip(DRILLS, vs)]
                    + [score(x), [dr[1], dr[2], dr[3]] if dr and dr[0] == y else None])
    # each drill's top five this year, and how many results since 2000 beat this year's best
    lead = {}
    for i, k in enumerate(DRILLS):
        have = [(r[6 + i], j) for j, r in enumerate(rows) if r[6 + i] is not None]
        have.sort(key=lambda vj: vj[0] if k in LOW else -vj[0])
        if not have: continue
        best = have[0][0]; allt = ALL_TIME[k]
        better = bisect.bisect_left(allt, best) if k in LOW else len(allt) - bisect.bisect_right(allt, best)
        lead[k] = [[j for _, j in have[:5]], better]
    dump(f'combine/{y}.json', dict(y=y, keys=C_KEYS, rows=rows, lead=lead))

dump('boards.json', dict(draft=draft_years, combine=cyears))
print('boards:', len(draft_years), 'drafts', draft_years[0], '-', draft_years[-1], '·', len(cyears), 'combines', cyears[0], '-', cyears[-1], '·', len(HAVE), 'player pages to link')
