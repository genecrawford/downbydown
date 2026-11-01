"""Defense numbers for every team from this season's nflverse files (SCOPE §39 mock). Pure Python, no pandas."""
import csv, gzip, json, collections, statistics as st
F = lambda x: float(x) if x not in ('', 'NA', None) else None
I = lambda x: int(float(x)) if x not in ('', 'NA', None) else 0
r = csv.reader(gzip.open('play_by_play_2026.csv.gz', 'rt')); h = next(r); ix = {c: i for i, c in enumerate(h)}
need = ['game_id','play_id','week','season_type','posteam','defteam','play_type','two_point_attempt','qb_dropback','rush_attempt','pass_attempt','qb_scramble','qb_spike','qb_kneel','down','ydstogo','yardline_100',
        'epa','success','yards_gained','first_down','touchdown','interception','fumble_lost','sack','qb_hit','air_yards','pass_location','run_location','run_gap','complete_pass','xpass','cpoe','cp',
        'fixed_drive','fixed_drive_result','drive_inside20','half_seconds_remaining','score_differential','receiver_player_id','receiver_player_name','rusher_player_id','rusher_player_name','passer_player_id',
        'penalty','penalty_team','penalty_yards','first_down_penalty','shotgun','wp','qtr','home_team','away_team','total_home_score','total_away_score']
P = []
for x in r:
    d = {c: x[ix[c]] for c in need}
    if d['season_type'] != 'REG': continue
    P.append(d)
ftn = {}
for x in csv.DictReader(open('ftn_charting_2026.csv')):
    ftn[(x['nflverse_game_id'], x['nflverse_play_id'])] = x
pos = {x['gsis_id']: x['position'] for x in csv.DictReader(open('roster.csv')) if x['gsis_id']}
TEAMS = sorted({p['defteam'] for p in P if p['defteam']})
snap = [p for p in P if p['play_type'] in ('pass', 'run') and p['two_point_attempt'] != '1' and p['qb_spike'] != '1' and p['qb_kneel'] != '1' and F(p['epa']) is not None]
print(len(P), 'plays,', len(snap), 'scrimmage snaps,', len(TEAMS), 'teams, ftn', len(ftn))
def agg(rows):
    n = len(rows); db = [p for p in rows if p['qb_dropback'] == '1']; ru = [p for p in rows if p['rush_attempt'] == '1' and p['qb_scramble'] != '1']
    epa = lambda L: sum(F(p['epa']) for p in L) / len(L) if L else None
    third = [p for p in rows if p['down'] == '3']
    return dict(n=n, epa=epa(rows), sr=sum(I(p['success']) for p in rows) / n if n else None,
                ypp=sum(I(p['yards_gained']) for p in rows) / n if n else None,
                expl=sum(1 for p in rows if (p['play_type'] == 'pass' and I(p['yards_gained']) >= 20) or (p['play_type'] == 'run' and I(p['yards_gained']) >= 10)) / n if n else None,
                pepa=epa(db), repa=epa(ru), ypc=(sum(I(p['yards_gained']) for p in ru) / len(ru)) if ru else None,
                sack=(sum(I(p['sack']) for p in db) / len(db)) if db else None, hit=(sum(I(p['qb_hit']) for p in db) / len(db)) if db else None,
                intr=(sum(I(p['interception']) for p in db) / len(db)) if db else None,
                third=(sum(1 for p in third if p['first_down'] == '1' or p['touchdown'] == '1') / len(third)) if third else None, n3=len(third), ndb=len(db), nru=len(ru))
D = {}
for t in TEAMS:
    rows = [p for p in snap if p['defteam'] == t]
    D[t] = dict(all=agg(rows))
LG = agg(snap)
# drives: red zone TD rate, points per drive (TD 7, FG 3), takeaways
for t in TEAMS:
    dr = {}
    for p in P:
        if p['defteam'] != t or not p['fixed_drive']: continue
        dr[(p['game_id'], p['fixed_drive'])] = (p['fixed_drive_result'], p['drive_inside20'])
    rz = [v for v in dr.values() if v[1] == '1']
    D[t]['drives'] = len(dr); D[t]['ppd'] = sum(7 if v[0] == 'Touchdown' else (3 if v[0] == 'Field goal' else 0) for v in dr.values()) / len(dr)
    D[t]['rz_td'] = sum(1 for v in rz if v[0] == 'Touchdown') / len(rz) if rz else None; D[t]['rz_n'] = len(rz)
    tk = [p for p in P if p['defteam'] == t and (p['interception'] == '1' or p['fumble_lost'] == '1')]
    D[t]['take'] = len(tk); D[t]['int'] = sum(1 for p in tk if p['interception'] == '1'); D[t]['fum'] = sum(1 for p in tk if p['fumble_lost'] == '1')
    pen = [p for p in P if p['penalty'] == '1' and p['penalty_team'] == t]
    D[t]['pen'] = len(pen); D[t]['pen_yds'] = sum(I(p['penalty_yards']) for p in pen); D[t]['pen_fd'] = sum(1 for p in pen if p['first_down_penalty'] == '1')
# games and points allowed, opponents' offense
games = [g for g in csv.DictReader(open('games.csv')) if g['season'] == '2026' and g['game_type'] == 'REG' and g['home_score'] not in ('', 'NA')]
for t in TEAMS:
    gl = [g for g in games if t in (g['home_team'], g['away_team'])]
    D[t]['g'] = len(gl); D[t]['pa'] = sum(int(g['away_score'] if g['home_team'] == t else g['home_score']) for g in gl)
    D[t]['w'] = sum(1 for g in gl if int(g['home_score' if g['home_team'] == t else 'away_score']) > int(g['away_score' if g['home_team'] == t else 'home_score']))
    D[t]['l'] = D[t]['g'] - D[t]['w']
OFF = {t: agg([p for p in snap if p['posteam'] == t]) for t in TEAMS}
for t in TEAMS:
    wk = []
    for gid in sorted({p['game_id'] for p in snap if p['defteam'] == t}, key=lambda g: int(g.split('_')[1])):
        rows = [p for p in snap if p['game_id'] == gid and p['defteam'] == t]; opp = rows[0]['posteam']
        orows = [p for p in snap if p['posteam'] == opp and p['game_id'] != gid]  # the opponent's offense in its other games
        oe = sum(F(p['epa']) for p in orows) / len(orows) if orows else None
        g = next((g for g in games if g['game_id'] == gid), None)
        pts = (int(g['away_score']) if g['home_team'] == t else int(g['home_score'])) if g else None
        pf = (int(g['home_score']) if g['home_team'] == t else int(g['away_score'])) if g else None
        a = agg(rows); wk.append(dict(gid=gid, wk=int(gid.split('_')[1]), opp=opp, home=g['home_team'] == t if g else None, epa=round(a['epa'], 3), oe=round(oe, 3) if oe is not None else None, sr=round(a['sr'], 3), pa=pts, pf=pf, n=a['n']))
    D[t]['weeks'] = wk
    adj = [w['epa'] - w['oe'] for w in wk if w['oe'] is not None]
    D[t]['adj'] = sum(adj) / len(adj) if adj else None
    D[t]['sos'] = sum(w['oe'] for w in wk if w['oe'] is not None) / len([w for w in wk if w['oe'] is not None])
# pass grid allowed: depth x direction
DEPTH = (('bh', lambda a: a < 0, 'Behind the line'), ('sh', lambda a: 0 <= a < 10, 'Short, 0 to 9'), ('in', lambda a: 10 <= a < 20, 'Intermediate, 10 to 19'), ('dp', lambda a: a >= 20, 'Deep, 20+'))
def grid(rows):
    pa = [p for p in rows if p['pass_attempt'] == '1' and p['sack'] != '1' and F(p['air_yards']) is not None and p['pass_location'] in ('left', 'middle', 'right')]
    out = {}
    for dk, f, _ in DEPTH:
        for loc in ('left', 'middle', 'right'):
            c = [p for p in pa if f(F(p['air_yards'])) and p['pass_location'] == loc]
            out[dk + '.' + loc] = [len(c), round(sum(F(p['epa']) for p in c) / len(c), 3) if c else None, round(sum(I(p['complete_pass']) for p in c) / len(c), 3) if c else None]
    return out, len(pa)
LGG, lgn = grid(snap)
for t in TEAMS:
    D[t]['grid'], D[t]['grid_n'] = grid([p for p in snap if p['defteam'] == t])
# run lanes
LANES = (('le', 'left', 'end'), ('lt', 'left', 'tackle'), ('lg', 'left', 'guard'), ('mid', 'middle', ''), ('rg', 'right', 'guard'), ('rt', 'right', 'tackle'), ('re', 'right', 'end'))
def lanes(rows):
    ru = [p for p in rows if p['rush_attempt'] == '1' and p['qb_scramble'] != '1' and p['run_location']]
    out = {}
    for k, loc, gap in LANES:
        c = [p for p in ru if p['run_location'] == loc and (loc == 'middle' or p['run_gap'] == gap)]
        out[k] = [len(c), round(sum(I(p['yards_gained']) for p in c) / len(c), 2) if c else None, round(sum(F(p['epa']) for p in c) / len(c), 3) if c else None, round(sum(I(p['success']) for p in c) / len(c), 3) if c else None]
    return out, len(ru)
LGL, _ = lanes(snap)
for t in TEAMS: D[t]['lanes'], D[t]['lanes_n'] = lanes([p for p in snap if p['defteam'] == t])
# by position: targets and EPA allowed to WR, TE, RB; who beat them
for t in TEAMS:
    rows = [p for p in snap if p['defteam'] == t]
    byp = {}
    for ps in ('WR', 'TE', 'RB'):
        c = [p for p in rows if p['pass_attempt'] == '1' and p['sack'] != '1' and pos.get(p['receiver_player_id']) in ((ps, 'FB') if ps == 'RB' else (ps,))]
        byp[ps] = dict(tgt=len(c), tpg=len(c) / D[t]['g'], yds=sum(I(p['yards_gained']) for p in c) / D[t]['g'], epa=sum(F(p['epa']) for p in c) / len(c) if c else None, td=sum(I(p['touchdown']) for p in c))
    D[t]['byp'] = byp
    who = collections.defaultdict(lambda: [None, None, 0, 0.0, 0, 0])  # name, team, yards, epa, touches, td
    for p in rows:
        pid, nm = (p['receiver_player_id'], p['receiver_player_name']) if p['pass_attempt'] == '1' and p['receiver_player_id'] else ((p['rusher_player_id'], p['rusher_player_name']) if p['rush_attempt'] == '1' and p['qb_scramble'] != '1' else (None, None))
        if not pid: continue
        w = who[pid]; w[0] = nm; w[1] = p['posteam']; w[2] += I(p['yards_gained']); w[3] += F(p['epa']); w[4] += 1; w[5] += I(p['touchdown'])
    D[t]['who'] = sorted(([k] + v for k, v in who.items()), key=lambda x: -x[3])[:6]
# situations
def sit(rows):
    out = {}
    for dn in ('1', '2', '3'):
        c = [p for p in rows if p['down'] == dn]; out['d' + dn] = [len(c), sum(F(p['epa']) for p in c) / len(c) if c else None, sum(I(p['success']) for p in c) / len(c) if c else None]
    for k, f in (('3s', lambda y: y <= 3), ('3m', lambda y: 4 <= y <= 6), ('3l', lambda y: y >= 7)):
        c = [p for p in rows if p['down'] == '3' and f(I(p['ydstogo']))]; out[k] = [len(c), sum(1 for p in c if p['first_down'] == '1' or p['touchdown'] == '1') / len(c) if c else None]
    c = [p for p in rows if F(p['yardline_100']) is not None and F(p['yardline_100']) <= 20]; out['rz'] = [len(c), sum(F(p['epa']) for p in c) / len(c) if c else None, sum(I(p['touchdown']) for p in c) / len(c) if c else None]
    c = [p for p in rows if F(p['half_seconds_remaining']) is not None and F(p['half_seconds_remaining']) <= 120]; out['2m'] = [len(c), sum(F(p['epa']) for p in c) / len(c) if c else None]
    for k, f in (('lead', lambda s: s < 0), ('close', lambda s: -8 <= s <= 8), ('trail', lambda s: s > 8)):  # score_differential is the offense's: negative = the defense leads
        c = [p for p in rows if F(p['score_differential']) is not None and f(F(p['score_differential']))]; out[k] = [len(c), sum(F(p['epa']) for p in c) / len(c) if c else None]
    return out
LGS = sit(snap)
for t in TEAMS: D[t]['sit'] = sit([p for p in snap if p['defteam'] == t])
# style from FTN: blitz, rushers, box; what offenses do against them
def style(rows):
    db = [(p, ftn.get((p['game_id'], p['play_id']))) for p in rows if p['qb_dropback'] == '1']; db = [(p, f) for p, f in db if f and f['n_pass_rushers'] not in ('', 'NA')]
    bz = [p for p, f in db if I(f['n_blitzers']) > 0]; nb = [p for p, f in db if I(f['n_blitzers']) == 0]
    ru = [(p, ftn.get((p['game_id'], p['play_id']))) for p in rows if p['rush_attempt'] == '1' and p['qb_scramble'] != '1']; ru = [(p, f) for p, f in ru if f and f['n_defense_box'] not in ('', 'NA')]
    heavy = [p for p, f in ru if I(f['n_defense_box']) >= 8]; light = [p for p, f in ru if I(f['n_defense_box']) <= 6]
    e = lambda L: sum(F(p['epa']) for p in L) / len(L) if L else None
    ypc = lambda L: sum(I(p['yards_gained']) for p in L) / len(L) if L else None
    allp = [(p, ftn.get((p['game_id'], p['play_id']))) for p in rows]; allp = [(p, f) for p, f in allp if f]
    pa = [p for p, f in allp if f['is_play_action'] in ('TRUE', '1', 'True') and p['pass_attempt'] == '1']
    scr = [p for p, f in allp if f['is_screen_pass'] in ('TRUE', '1', 'True')]
    mot = [p for p, f in allp if f['is_motion'] in ('TRUE', '1', 'True')]
    passes = [p for p, f in allp if p['pass_attempt'] == '1']
    neutral = [p for p in rows if p['down'] in ('1', '2') and F(p['wp']) is not None and 0.2 <= F(p['wp']) <= 0.8 and F(p['xpass']) is not None and F(p['half_seconds_remaining']) is not None and F(p['half_seconds_remaining']) > 120]
    proe = (sum(1 for p in neutral if p['qb_dropback'] == '1') / len(neutral) - sum(F(p['xpass']) for p in neutral) / len(neutral)) if neutral else None
    rushers = [I(f['n_pass_rushers']) for p, f in db]
    return dict(n_db=len(db), blitz=len(bz) / len(db) if db else None, rush=sum(rushers) / len(rushers) if rushers else None, four=sum(1 for x in rushers if x <= 4) / len(rushers) if rushers else None,
                e_bz=e(bz), e_nb=e(nb), box=sum(I(f['n_defense_box']) for p, f in ru) / len(ru) if ru else None, heavy=len(heavy) / len(ru) if ru else None, ypc_h=ypc(heavy), ypc_l=ypc(light),
                pa=len(pa) / len(passes) if passes else None, e_pa=e(pa), scr=len(scr) / len(allp) if allp else None, e_scr=e(scr), mot=len(mot) / len(allp) if allp else None, e_mot=e(mot), proe=proe,
                cpoe=(sum(F(p['cpoe']) for p in rows if F(p['cpoe']) is not None) / max(1, sum(1 for p in rows if F(p['cpoe']) is not None))))
LGST = style(snap)
for t in TEAMS: D[t]['style'] = style([p for p in snap if p['defteam'] == t])
# pressure from PFR (team sum of defender pressures / dropbacks)
pfr = list(csv.DictReader(open('advstats_week_def_2026.csv'))); pfr = [x for x in pfr if x.get('game_type', 'REG') == 'REG']
for t in TEAMS:
    rows = [x for x in pfr if x['team'] == t]
    pr = sum(I(x['def_pressures']) for x in rows); D[t]['pressures'] = pr; D[t]['press'] = pr / D[t]['all']['ndb'] if D[t]['all']['ndb'] else None
    D[t]['mtk'] = sum(I(x['def_missed_tackles']) for x in rows); D[t]['tkl'] = sum(I(x['def_tackles_combined']) for x in rows)
# defenders: stats + pfr + snaps
sw = [x for x in csv.DictReader(open('stats_player_week_2026.csv')) if x.get('season_type', 'REG') == 'REG']
sn = [x for x in csv.DictReader(open('snap_counts_2026.csv')) if x.get('game_type', 'REG') == 'REG']
ros = {x['gsis_id']: x for x in csv.DictReader(open('roster.csv')) if x['gsis_id']}
pfr_by_name = collections.defaultdict(list)
for x in pfr: pfr_by_name[(x['team'], x['pfr_player_name'])].append(x)
team_snaps = collections.Counter(); seen = set()
for x in sn:
    if (x['team'], x['game_id']) in seen: continue
for t in TEAMS:
    gsn = collections.defaultdict(int)
    for x in sn:
        if x['team'] == t: gsn[x['game_id']] = max(gsn[x['game_id']], I(x['defense_snaps']) / max(F(x['defense_pct']) or 1, 0.01) if F(x['defense_pct']) else 0)
    defs = {}
    for x in sw:
        if x['team'] != t or x['position_group'] not in ('DL', 'LB', 'DB'): continue
        d = defs.setdefault(x['player_id'], dict(id=x['player_id'], name=x['player_display_name'], pos=x['position'], grp=x['position_group'], num=ros.get(x['player_id'], {}).get('jersey_number'),
                                                    g=0, tkl=0, tfl=0, sk=0.0, qbh=0, int=0, pd=0, ff=0))
        d['g'] += 1; d['tkl'] += I(x['def_tackles_solo']) + I(x['def_tackle_assists']); d['tfl'] += I(x['def_tackles_for_loss']); d['sk'] += F(x['def_sacks']) or 0
        d['qbh'] += I(x['def_qb_hits']); d['int'] += I(x['def_interceptions']); d['pd'] += I(x['def_pass_defended']); d['ff'] += I(x['def_fumbles_forced'])
    for d in defs.values():
        pr = pfr_by_name.get((t, d['name']), [])
        d['prs'] = sum(I(x['def_pressures']) for x in pr); d['tgt'] = sum(I(x['def_targets']) for x in pr); d['cmp'] = sum(I(x['def_completions_allowed']) for x in pr)
        d['yds'] = sum(I(x['def_yards_allowed']) for x in pr); d['tda'] = sum(I(x['def_receiving_td_allowed']) for x in pr); d['mt'] = sum(I(x['def_missed_tackles']) for x in pr)
        d['rate'] = None
        if d['tgt']:
            # passer rating allowed, from PFR's weekly values weighted by targets
            vals = [(F(x['def_passer_rating_allowed']), I(x['def_targets'])) for x in pr if F(x['def_passer_rating_allowed']) is not None and I(x['def_targets'])]
            d['rate'] = round(sum(v * w for v, w in vals) / sum(w for v, w in vals), 1) if vals else None
        s_ = [x for x in sn if x['team'] == t and x['player'] == d['name']]
        d['snap'] = round(100 * sum(F(x['defense_pct']) or 0 for x in s_) / max(1, D[t]['g']))
    D[t]['defenders'] = sorted(defs.values(), key=lambda d: -d['snap'])
# league ranks for the scorecard
KEYS = {'epa': False, 'sr': False, 'ypp': False, 'expl': False, 'pepa': False, 'repa': False, 'ypc': False, 'sack': True, 'hit': True, 'intr': True, 'third': False}
EXTRA = {'ppd': False, 'rz_td': False, 'press': True, 'adj': False, 'take_pg': True, 'pa_pg': False}
for t in TEAMS: D[t]['take_pg'] = D[t]['take'] / D[t]['g']; D[t]['pa_pg'] = D[t]['pa'] / D[t]['g']
RANK = {t: {} for t in TEAMS}; DIST = {}
for k, hib in list(KEYS.items()) + list(EXTRA.items()):
    get = (lambda t, k=k: D[t]['all'][k]) if k in KEYS else (lambda t, k=k: D[t][k])
    vals = [(t, get(t)) for t in TEAMS if get(t) is not None]
    order = sorted(vals, key=lambda x: (-x[1] if hib else x[1]))  # rank 1 = best defense
    for i, (t, v) in enumerate(order): RANK[t][k] = i + 1
    DIST[k] = sorted(v for _, v in vals)
json.dump(dict(D=D, LG=LG, LGG=LGG, LGL=LGL, LGS=LGS, LGST=LGST, RANK=RANK, DIST=DIST, OFF={t: dict(epa=OFF[t]['epa']) for t in TEAMS}), open('defense.json', 'w'))
best = sorted(TEAMS, key=lambda t: RANK[t]['epa'])
print('best EPA defenses:', [(t, round(D[t]['all']['epa'], 3)) for t in best[:5]]); print('worst:', [(t, round(D[t]['all']['epa'], 3)) for t in best[-3:]])
t = 'MIN'; print('MIN', {k: RANK[t][k] for k in RANK[t]}); print('MIN style', {k: (round(v, 3) if isinstance(v, float) else v) for k, v in D[t]['style'].items()}); print('MIN press', D[t]['press'], 'take', D[t]['take'], 'rz', D[t]['rz_td'])
print('MIN defenders', [(d['name'], d['pos'], d['snap'], d['tkl'], d['sk'], d['prs'], d['tgt'], d['rate']) for d in D[t]['defenders'][:8]])
