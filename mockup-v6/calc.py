import gzip, csv, json, sys, collections
pbp, datadir, out = sys.argv[1:4]
F = lambda x: float(x) if x not in ('', 'NA') else None
I = lambda x: x in ('1', '1.0')
rows = []
for r in csv.DictReader(gzip.open(pbp, 'rt')):
    if r['season_type'] != 'REG' or r['play_type'] not in ('pass', 'run', 'qb_kneel') or I(r['two_point_attempt']): continue
    rows.append(r)
print('plays', len(rows), 'through week', max(int(r['week']) for r in rows))

# ---- QB dropback decomposition
def depth(a):
    if a is None: return None
    return 'behind' if a < 0 else ('short' if a < 10 else ('inter' if a < 20 else 'deep'))
qb = collections.defaultdict(lambda: dict(fd=0, db=0, att=0, cmp=0, sk=0, sky=0, yds=0, td=0, int=0, succ=0, epa=0, air=0, yac=0, inc=0, intE=0, skE=0, scr=0, run=0, grid=collections.Counter(), gridE=collections.defaultdict(float), gridC=collections.Counter()))
rec = collections.defaultdict(lambda: dict(tg=0, cat=0, succ=0, epa=0, air=0, yac=0, inc=0, intE=0, grid=collections.Counter(), gridE=collections.defaultdict(float)))
lgq, lgr = collections.Counter(), collections.Counter(); lgqE, lgrE = collections.defaultdict(float), collections.defaultdict(float)
for r in rows:
    e = F(r['epa']) or 0.0; s = I(r['success']); qe = F(r['qb_epa']); qe = e if qe is None else qe
    if r['play_type'] == 'pass' and not I(r['qb_scramble']):
        pid = r['passer_player_id']
        if not pid: continue
        q = qb[pid]; q['db'] += 1; q['epa'] += qe; q['succ'] += s
        if I(r['sack']):
            q['sk'] += 1; q['skE'] += qe; q['sky'] += -(F(r['yards_gained']) or 0); continue
        q['att'] += 1
        loc, dp = r['pass_location'] or None, depth(F(r['air_yards']))
        if loc and dp:
            q['grid'][dp + '-' + loc] += 1; q['gridE'][dp + '-' + loc] += e; lgq[dp + '-' + loc] += 1; lgqE[dp + '-' + loc] += qe
            if I(r['complete_pass']): q['gridC'][dp + '-' + loc] += 1
        if I(r['interception']): q['int'] += 1; q['intE'] += qe
        elif I(r['complete_pass']):
            q['cmp'] += 1; a = F(r['comp_air_epa']) or 0; q['air'] += a; q['yac'] += qe - a
            q['yds'] += F(r['passing_yards']) or 0; q['td'] += I(r['pass_touchdown']); q['fd'] += I(r['first_down'])
        else: q['inc'] += qe
        tid = r['receiver_player_id']
        if tid:
            t = rec[tid]; t['tg'] += 1; t['epa'] += e; t['succ'] += s
            if loc and dp: t['grid'][dp + '-' + loc] += 1; t['gridE'][dp + '-' + loc] += e; lgr[dp + '-' + loc] += 1; lgrE[dp + '-' + loc] += e
            if I(r['interception']): t['intE'] += e
            elif I(r['complete_pass']): t['cat'] += 1; t['air'] += F(r['comp_air_epa']) or 0; t['yac'] += F(r['comp_yac_epa']) or 0
            else: t['inc'] += e
    else:
        pid = r['rusher_player_id']
        if pid in qb or I(r['qb_scramble']):
            q = qb[pid]
            if I(r['qb_scramble']): q['db'] += 1; q['scr'] += e; q['epa'] += e; q['succ'] += s
            else: q['run'] += e
# league pools
QB = {k: v for k, v in qb.items() if v['att'] >= 50}
REC = {k: v for k, v in rec.items() if v['tg'] >= 15}
def pct(pool, val):
    xs = sorted(pool); below = sum(1 for x in xs if x < val); eq = sum(1 for x in xs if x == val)
    return round(100 * (below + 0.5 * eq) / len(xs))
qcomp = ['air', 'yac', 'inc', 'intE', 'skE', 'scr']
def qrate(v, k): return v[k] / v['db']
def rk(vals, v): return 1 + sum(1 for x in vals if x > v + 1e-9)
def anya(v): return (v['yds'] + 20 * v['td'] - 45 * v['int'] - v['sky']) / (v['att'] + v['sk'])
rcomp = ['air', 'yac', 'inc', 'intE']
def summary_qb(pid):
    v = qb[pid]
    comp = {k: dict(tot=round(v[k], 1), per=round(qrate(v, k), 3), pc=pct([qrate(x, k) for x in QB.values()], qrate(v, k))) for k in qcomp}
    comp['run'] = dict(tot=round(v['run'], 1), per=None, pc=pct([x['run'] for x in QB.values()], v['run']))
    tot = v['epa'] + v['run']
    ranks = dict(epa=1 + sum(1 for x in QB.values() if x['epa'] + x['run'] > tot), n=len(QB))
    att = sum(v['grid'].values()); L = sum(lgq.values())
    grid = {c: dict(n=v['grid'][c], f=round(v['grid'][c] / att, 3), lg=round(lgq[c] / L, 3), epa=round(v['gridE'][c] / v['grid'][c], 2) if v['grid'][c] else None, cmp=round(v['gridC'][c] / v['grid'][c], 2) if v['grid'][c] else None, lge=round(lgqE[c] / lgq[c], 2)) for c in lgq}
    extra = dict(fd=v['fd'], succ_rk=rk([x['succ'] / x['db'] for x in QB.values()], v['succ'] / v['db']), pass_epa=round(sum(v[k] for k in ['air','yac','inc','intE','skE']), 1), pass_db=v['att'] + v['sk'], n=len(QB), ndr=0)
    return dict(extra=extra, db=v['db'], total=round(tot, 1), epadb=round(v['epa'] / v['db'], 3), succ=round(v['succ'] / v['db'], 3), succ_pc=pct([x['succ'] / x['db'] for x in QB.values()], v['succ'] / v['db']),
                anya=round(anya(v), 2), anya_pc=pct([anya(x) for x in QB.values()], anya(v)), anya_rk=1 + sum(1 for x in QB.values() if anya(x) > anya(v)), comp=comp, ranks=ranks, grid=grid, check=round(sum(v[k] for k in qcomp) - v['epa'], 3))
def summary_rec(pid):
    v = rec[pid]
    comp = {k: dict(tot=round(v[k], 1), per=round(v[k] / v['tg'], 3), pc=pct([x[k] / x['tg'] for x in REC.values()], v[k] / v['tg'])) for k in rcomp}
    att = sum(v['grid'].values()); L = sum(lgr.values())
    grid = {c: dict(n=v['grid'][c], f=round(v['grid'][c] / att, 3), lg=round(lgr[c] / L, 3), epa=round(v['gridE'][c] / v['grid'][c], 2) if v['grid'][c] else None, lge=round(lgrE[c] / lgr[c], 2)) for c in lgr}
    extra = dict(total_rk=rk([x['epa'] for x in REC.values()], v['epa']), succ_rk=rk([x['succ'] / x['tg'] for x in REC.values()], v['succ'] / v['tg']), cat=v['cat'])
    return dict(extra=extra, tg=v['tg'], total=round(v['epa'], 1), succ=round(v['succ'] / v['tg'], 3), succ_pc=pct([x['succ'] / x['tg'] for x in REC.values()], v['succ'] / v['tg']), comp=comp, grid=grid, n=len(REC), check=round(sum(v[k] for k in rcomp) - v['epa'], 3))
res = dict(qb=summary_qb('00-0034857'), rec=summary_rec('00-0036322'), nqb=len(QB), nrec=len(REC))
json.dump(res, open(out, 'w'), indent=1)
print(json.dumps(res['qb'], indent=0)[:2500]); print(json.dumps({k: res['rec'][k] for k in ('tg', 'total', 'succ', 'succ_pc', 'comp', 'n', 'check')}))
