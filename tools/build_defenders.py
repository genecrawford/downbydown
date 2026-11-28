"""A page for every defender (SCOPE §101): defenders/index.json and defenders/{TEAM}.json.

Called by build_data.py after the defense files, with the frames it already holds (play-by-play, weekly stats, PFR's
defensive charting, snap counts, the roster, the draft). Everything a defender page shows is worked out here, so the plugin
only reads and draws:

  defenders/index.json   every defender with a defensive snap this season: id, name, team, pos, role, num, photo, slug, and a
                         few season figures for search and the Players index
  defenders/{TEAM}.json  {season, through, team, wk: [week keys], pk: [play keys], players: {id: record}}, a record being the bio,
                         the season line (s), its ranks in his role's pool (r: {metric: [rank, pct, n, median]}, tr: {total: [rank, n]}),
                         players like him (like), the weekly lines (w), every play the play-by-play credits him with (pl) and his
                         career by season (car)
  career/d{year}.json    each finished season's defensive lines, built once from nflverse's weekly stats and kept

Roles, as on the defense tab: the front (linemen, and linebackers who rush more than they cover), linebackers, the secondary.
A regular role, the pool for ranks: half his team's games or more, on 40% or more of the defense's snaps in the games he played.
"""
import csv
import gzip
import io
import json
import os
import re
import unicodedata
import urllib.request
from collections import defaultdict

FRONT_POS = {'DE', 'DT', 'NT', 'DL', 'EDGE'}
LB_POS = {'LB', 'OLB', 'ILB', 'MLB'}
DB_POS = {'CB', 'S', 'FS', 'SS', 'SAF', 'DB'}
WK = ['week', 'team', 'opp', 'sp', 'tkl', 'tfl', 'sk', 'qbh', 'prs', 'hur', 'blz', 'tgt', 'cmp', 'yds', 'tda', 'int', 'pd', 'ff']
PK = ['week', 'off', 'q', 'time', 'dn', 'togo', 'k', 'lane', 'y', 'air', 'epa', 'ff', 'desc']
CAR = ['season', 'team', 'g', 'tkl', 'tfl', 'sk', 'qbh', 'int', 'pd', 'ff']
CAREER_FROM = 2005
CREDIT = ['solo_tackle_1_player_id', 'solo_tackle_2_player_id', 'assist_tackle_1_player_id', 'assist_tackle_2_player_id', 'assist_tackle_3_player_id',
          'assist_tackle_4_player_id', 'tackle_with_assist_1_player_id', 'tackle_with_assist_2_player_id', 'sack_player_id', 'half_sack_1_player_id',
          'half_sack_2_player_id', 'qb_hit_1_player_id', 'qb_hit_2_player_id', 'interception_player_id', 'pass_defense_1_player_id', 'pass_defense_2_player_id',
          'forced_fumble_player_1_player_id', 'forced_fumble_player_2_player_id', 'tackle_for_loss_1_player_id', 'tackle_for_loss_2_player_id']
LANE = {('left', 'end'): 0, ('left', 'tackle'): 1, ('left', 'guard'): 2, ('middle', ''): 3, ('right', 'guard'): 4, ('right', 'tackle'): 5, ('right', 'end'): 6}


def f(v):
    try:
        x = float(v)
        return 0.0 if x != x else x
    except (TypeError, ValueError):
        return 0.0


def s_(v):
    return '' if v is None or (isinstance(v, float) and v != v) else str(v)


def slugify(name):  # the build's rule (build_data.slugify), so offense and defense slugs agree
    s = unicodedata.normalize('NFKD', str(name)).encode('ascii', 'ignore').decode()
    s = re.sub(r"['’.]", '', s.lower())
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-') or 'player'


def rating(att, cmp_, yds, td, int_):
    if not att:
        return None
    c = lambda x: max(0.0, min(2.375, x))
    return round((c((cmp_ / att - .3) * 5) + c((yds / att - 3) * .25) + c(td / att * 20) + c(2.375 - int_ / att * 25)) / 6 * 100, 1)


# (key, value from a season line, lower is better)
pg = lambda k: (lambda s: s[k] / s['g'] if s['g'] else None)
mtp = lambda s: s['mt'] / (s['mt'] + s['tklp']) if (s['mt'] + s['tklp']) >= 10 else None
METRICS = {
    'front': [('prs', pg('prs'), 0), ('sk', pg('sk'), 0), ('qbh', pg('qbh'), 0), ('tfl', pg('tfl'), 0),
              ('fin', lambda s: s['sk'] / s['prs'] if s['prs'] >= 5 else None, 0), ('tkl', pg('tkl'), 0), ('mtp', mtp, 1), ('snap', lambda s: s['snap'] / 100, 0)],
    'lb': [('tkl', pg('tkl'), 0), ('tfl', pg('tfl'), 0), ('prs', pg('prs'), 0), ('ypt', lambda s: s['yds'] / s['tgt'] if s['tgt'] >= 10 else None, 1),
           ('rate', lambda s: s['rate'] if s['tgt'] >= 10 else None, 1), ('mtp', mtp, 1), ('snap', lambda s: s['snap'] / 100, 0)],
    'db': [('rate', lambda s: s['rate'] if s['tgt'] >= 10 else None, 1), ('ypt', lambda s: s['yds'] / s['tgt'] if s['tgt'] >= 10 else None, 1),
           ('cpa', lambda s: s['cmp'] / s['tgt'] if s['tgt'] >= 10 else None, 1), ('ball', lambda s: (s['int'] + s['pd']) / s['g'] if s['g'] else None, 0),
           ('tkl', pg('tkl'), 0), ('mtp', mtp, 1), ('snap', lambda s: s['snap'] / 100, 0)],
}
TOTALS = ('prs', 'sk', 'qbh', 'tfl', 'tkl', 'ff', 'hur', 'tgt', 'int', 'pd')


RECOVER = ['fumble_recovery_1_player_id', 'fumble_recovery_1_team', 'fumble_recovery_2_player_id', 'fumble_recovery_2_team']
S_KEYS = ('tkl', 'tfl', 'sk', 'qbh', 'int', 'pd', 'ff', 'fr', 'dtd', 'prs', 'hur', 'blz', 'tgt', 'cmp', 'yds', 'tda', 'mt', 'tklp')  # every season line carries all of them, 0 when he has none
PBP_COLS = CREDIT + RECOVER + ['season_type', 'week', 'posteam', 'defteam', 'qtr', 'time', 'down', 'ydstogo', 'play_type', 'yards_gained', 'air_yards', 'pass_location', 'run_location', 'run_gap', 'epa', 'desc', 'sack']


def records(rows, cols=None):
    """Rows as dicts: a list of dicts as is, or a pandas frame's records."""
    if rows is None:
        return []
    if hasattr(rows, 'to_dict'):
        if not len(rows):
            return []
        if cols:
            rows = rows[[c for c in cols if c in rows.columns]]
        return rows.to_dict('records')
    return rows


def season_lines(rows):
    """{player id: [CAR row]} from weekly stat rows (one season)."""
    acc = {}
    for r in rows:
        if s_(r.get('season_type')) not in ('REG', '') or s_(r.get('position_group')) not in ('DL', 'LB', 'DB'):
            continue
        pid = s_(r.get('player_id'))
        if not pid:
            continue
        a = acc.setdefault(pid, dict(season=int(f(r.get('season'))), team='', g=0, tkl=0, tfl=0, sk=0.0, qbh=0, int=0, pd=0, ff=0, _w=-1))
        a['g'] += 1
        if f(r.get('week')) >= a['_w']:
            a['_w'] = f(r.get('week')); a['team'] = s_(r.get('team') or r.get('recent_team'))
        a['tkl'] += int(f(r.get('def_tackles_solo')) + f(r.get('def_tackle_assists'))); a['tfl'] += int(f(r.get('def_tackles_for_loss')))
        a['sk'] += f(r.get('def_sacks')); a['qbh'] += int(f(r.get('def_qb_hits'))); a['int'] += int(f(r.get('def_interceptions')))
        a['pd'] += int(f(r.get('def_pass_defended'))); a['ff'] += int(f(r.get('def_fumbles_forced')))
    return {pid: [a['season'], a['team'], a['g'], a['tkl'], a['tfl'], round(a['sk'], 1), a['qbh'], a['int'], a['pd'], a['ff']] for pid, a in acc.items()}


def career_years(out, season, fetch=True):
    """{year: {id: CAR row}} for every finished season since CAREER_FROM, each built once into career/d{year}.json."""
    os.makedirs(os.path.join(out, 'career'), exist_ok=True)
    years = {}
    for y in range(CAREER_FROM, season):
        p = os.path.join(out, 'career', 'd%d.json' % y)
        if os.path.exists(p):
            years[y] = json.load(open(p)); continue
        if not fetch:
            continue
        try:
            raw = urllib.request.urlopen('https://github.com/nflverse/nflverse-data/releases/download/stats_player/stats_player_week_%d.csv.gz' % y, timeout=120).read()
            years[y] = season_lines(csv.DictReader(io.StringIO(gzip.decompress(raw).decode('utf-8'))))
            json.dump(years[y], open(p, 'w'), separators=(',', ':'))
            print('defender careers: built', y, len(years[y]), 'players')
        except Exception as e:
            print('defender careers: skipped', y, repr(e)[:120])
    return years


def build(out, season, through, pbp, stats, pfrd, snaps, roster, draft, taken_slugs, sched):
    """pbp, stats, pfrd, snaps, roster, draft: pandas frames (any may be empty). taken_slugs: the offense index's slugs. sched: schedule.json."""
    os.makedirs(os.path.join(out, 'defenders'), exist_ok=True)
    tg = defaultdict(int)  # games each team has played
    for gl in sched.get('weeks', {}).values():
        for g in gl:
            if g.get('status') == 'final' and g.get('game_type', 'REG') == 'REG':
                tg[g['home']] += 1; tg[g['away']] += 1
    ros = {}
    for r in records(roster):
        gid = s_(r.get('gsis_id'))
        if gid and (gid not in ros or f(r.get('week')) >= f(ros[gid].get('week'))):
            ros[gid] = r
    pfr2g = {s_(r.get('pfr_id')): g for g, r in ros.items() if s_(r.get('pfr_id'))}
    dr = {}
    for r in records(draft, ['gsis_id', 'season', 'round', 'pick']):
        if s_(r.get('gsis_id')):
            dr[s_(r['gsis_id'])] = [int(f(r['round'])), int(f(r['pick'])), int(f(r['season']))]
    P = {}

    def wk(pid, w):
        p = P.setdefault(pid, dict(weeks={}, plays=[]))
        return p['weeks'].setdefault(int(w), defaultdict(float))
    posted = set()  # (team, week) whose weekly stat lines are out: snap counts can post first (Greptile, PR #176)
    for r in records(stats):
        if s_(r.get('season_type')) not in ('REG', '') or s_(r.get('position_group')) not in ('DL', 'LB', 'DB'):
            continue
        posted.add((s_(r.get('team')), int(f(r['week']))))
        x = wk(s_(r['player_id']), f(r['week']))
        x['team'] = s_(r.get('team')); x['opp'] = s_(r.get('opponent_team'))
        x['tkl'] = f(r.get('def_tackles_solo')) + f(r.get('def_tackle_assists'))
        for k, col in (('tfl', 'def_tackles_for_loss'), ('sk', 'def_sacks'), ('qbh', 'def_qb_hits'), ('int', 'def_interceptions'), ('pd', 'def_pass_defended'), ('ff', 'def_fumbles_forced'), ('dtd', 'def_tds')):
            x[k] = f(r.get(col))
        x['_st'] = 1
    for r in records(pfrd):
        g = pfr2g.get(s_(r.get('pfr_player_id')))
        if not g or s_(r.get('game_type')) not in ('REG', ''):
            continue
        x = wk(g, f(r['week']))
        for k, col in (('prs', 'def_pressures'), ('hur', 'def_times_hurried'), ('blz', 'def_times_blitzed'), ('tgt', 'def_targets'), ('cmp', 'def_completions_allowed'),
                       ('yds', 'def_yards_allowed'), ('tda', 'def_receiving_td_allowed'), ('mt', 'def_missed_tackles'), ('tklp', 'def_tackles_combined')):
            x[k] = f(r.get(col))
    for r in records(snaps):
        g = pfr2g.get(s_(r.get('pfr_player_id')))
        if not g or s_(r.get('game_type')) not in ('REG', '') or f(r.get('defense_snaps')) <= 0:
            continue
        x = wk(g, f(r['week'])); x['snaps'] = f(r['defense_snaps']); x['sp'] = f(r['defense_pct'])
        x['team'] = x.get('team') or s_(r.get('team')); x['opp'] = x.get('opp') or s_(r.get('opponent'))
    # who gets a page: a defensive snap this season, on a roster we know
    P = {pid: p for pid, p in P.items() if pid in ros and any(w.get('snaps', 0) > 0 for w in p['weeks'].values())}
    # (a week held back below can leave a player with no weeks; his page then says his stats are pending)
    # every play the play-by-play credits him with
    if pbp is not None:
        for r in records(pbp, PBP_COLS):
            if s_(r.get('season_type')) not in ('REG', ''):
                continue
            if s_(r.get('play_type')) in ('pass', 'run'):  # SCOPE §102: a fumble he recovered for his defense (the offense's ball)
                for n in ('1', '2'):
                    rp = s_(r.get('fumble_recovery_%s_player_id' % n))
                    if rp in P and s_(r.get('fumble_recovery_%s_team' % n)) == s_(r.get('defteam')) != s_(r.get('posteam')):
                        x = wk(rp, f(r['week'])); x['fr'] = x.get('fr', 0.0) + 1.0
            hit = defaultdict(set)
            for c in CREDIT:
                if s_(r.get(c)) in P:
                    hit[s_(r[c])].add(c)
            for pid, cs in hit.items():
                has = lambda *ks: any(k in cs for k in ks)
                if has('interception_player_id'): k = 'int'
                elif has('sack_player_id', 'half_sack_1_player_id', 'half_sack_2_player_id'): k = 'sack'
                elif has('pass_defense_1_player_id', 'pass_defense_2_player_id'): k = 'pd'
                elif has('tackle_for_loss_1_player_id', 'tackle_for_loss_2_player_id'): k = 'tfl'
                elif has('qb_hit_1_player_id', 'qb_hit_2_player_id'): k = 'qbh'
                elif has('solo_tackle_1_player_id', 'solo_tackle_2_player_id', 'tackle_with_assist_1_player_id', 'tackle_with_assist_2_player_id'): k = 'tkl'
                elif has('forced_fumble_player_1_player_id', 'forced_fumble_player_2_player_id'): k = 'tkl'
                else: k = 'ast'
                pt, loc, gap = s_(r.get('play_type')), s_(r.get('pass_location')) or s_(r.get('run_location')), s_(r.get('run_gap'))
                if pt == 'run':
                    lane = LANE.get((loc or 'middle', gap), 3 if (loc or 'middle') == 'middle' else (0 if loc == 'left' else 6))
                else:
                    lane = {'left': 1, 'middle': 3, 'right': 5}.get(loc, 3)
                air = r.get('air_yards'); air = None if air is None or (isinstance(air, float) and air != air) else int(f(air))
                dn = r.get('down'); dn = None if dn is None or (isinstance(dn, float) and dn != dn) else int(f(dn))
                desc = re.sub(r'^\(\d+:\d+\)\s*', '', s_(r.get('desc')))
                P[pid]['plays'].append([int(f(r['week'])), s_(r.get('posteam')), int(f(r.get('qtr'))), s_(r.get('time')), dn, int(f(r.get('ydstogo'))) if dn else None,
                                        k, lane, int(f(r.get('yards_gained'))), air, round(f(r.get('epa')), 2), 1 if has('forced_fumble_player_1_player_id', 'forced_fumble_player_2_player_id') else 0,
                                        desc[:400]])
    # a week whose stat lines aren't posted yet for his team is held back: not a zero game, not in his totals or ranks, and the
    # game log says the stats are pending
    for pid, p in P.items():
        p['pend'] = sorted(w for w, x in p['weeks'].items() if x.get('team') and (x['team'], w) not in posted)
        for w in p['pend']:
            del p['weeks'][w]
    # season lines, roles and pools
    for pid, p in P.items():
        W = [w for w in p['weeks'].values() if w.get('snaps', 0) > 0 or w.get('_st')]
        s = defaultdict(float)
        for w in W:
            for k, v in w.items():
                if isinstance(v, float) and k not in ('sp', 'snaps', '_st'):
                    s[k] += v
        for k in S_KEYS:
            s.setdefault(k, 0.0)
        played = [w for w in W if w.get('snaps', 0) > 0]
        s['g'] = len(played) or len(W)
        s['snap'] = sum(w['sp'] for w in played) / len(played) * 100 if played else 0.0
        s['rate'] = rating(s['tgt'], s['cmp'], s['yds'], s['tda'], s['int'])
        r = ros[pid]
        p['team'] = s_(r.get('team')) or (max(p['weeks'].items())[1].get('team') if p['weeks'] else '')
        pos = s_(r.get('depth_chart_position')) or s_(r.get('position'))
        p['pos'] = pos
        p['role'] = 'front' if pos in FRONT_POS or (pos in LB_POS and s['prs'] > s['tgt']) else ('lb' if pos in LB_POS else ('db' if pos in DB_POS else 'front'))
        p['s'] = s
        p['q'] = s['g'] >= max(1, tg.get(p['team'], 0) / 2) and s['snap'] >= 40
    pools = defaultdict(list)
    for pid, p in P.items():
        if p['q']:
            pools[p['role']].append(pid)
    for pid, p in P.items():
        pool = pools[p['role']]
        rk = {}
        for key, fn, low in METRICS[p['role']]:
            vals = sorted(v for v in (fn(P[q]['s']) for q in pool) if v is not None)
            mine = fn(p['s'])
            if mine is None or not vals:
                rk[key] = [None, None, len(vals), vals[len(vals) // 2] if vals else None, mine]; continue
            better = sum(1 for v in vals if (v < mine if low else v > mine)); worse = sum(1 for v in vals if (v > mine if low else v < mine))
            rk[key] = [better + 1 if p['q'] else None, round((worse + (len(vals) - better - worse) / 2) / len(vals) * 100) if p['q'] else None, len(vals), vals[len(vals) // 2], mine]
        p['r'] = rk
        p['tr'] = {k: [1 + sum(1 for q in pool if P[q]['s'][k] > p['s'][k]), len(pool)] for k in TOTALS} if p['q'] else {}
        if p['q']:
            base = [(key, fn) for key, fn, _ in METRICS[p['role']][:4]]
            mine = [fn(p['s']) or 0 for _, fn in base]
            def dist(q):
                return sum(((fn(P[q]['s']) or 0) - m) ** 2 / ((abs(m) + .05) ** 2) for (_, fn), m in zip(base, mine))
            p['like'] = sorted((q for q in pool if q != pid), key=dist)[:3]
        else:
            p['like'] = []
    # slugs: the offense keeps its slug; a defender who shares one takes his position and the id's last four digits, as offense duplicates do
    cnt = defaultdict(int)
    for pid in P:
        cnt[slugify(ros[pid]['full_name'])] += 1
    for pid, p in P.items():
        sl = slugify(ros[pid]['full_name'])
        p['slug'] = sl if cnt[sl] == 1 and sl not in taken_slugs else '%s-%s-%s' % (sl, p['pos'].lower() or 'def', pid[-4:])
    # careers
    years = career_years(out, season)
    now = season_lines(records(stats))
    # write
    by_team = defaultdict(dict)
    index = []
    rnd = lambda v: None if v is None else (round(v, 4) if isinstance(v, float) else v)
    for pid, p in sorted(P.items()):
        r = ros[pid]; s = p['s']
        photo = s_(r.get('headshot_url')); photo = photo if photo.startswith('https://') else None
        try:
            ht = int(f(r.get('height'))); ht = "%d'%d\"" % (ht // 12, ht % 12) if ht else None
        except Exception:
            ht = None
        rec = dict(id=pid, name=s_(r.get('full_name')), team=p['team'], pos=p['pos'], role=p['role'], q=1 if p['q'] else 0, num=int(f(r.get('jersey_number'))) if s_(r.get('jersey_number')) else None,
                   photo=photo, slug=p['slug'], born=s_(r.get('birth_date')) or None, ht=ht, wt=int(f(r.get('weight'))) if s_(r.get('weight')) else None, col=s_(r.get('college')) or None,
                   exp=int(f(r.get('years_exp'))), rookie=int(f(r.get('rookie_year'))) if s_(r.get('rookie_year')) else None, draft=dr.get(pid),
                   s={k: rnd(v) for k, v in s.items()}, r={k: [rnd(x) for x in v] for k, v in p['r'].items()}, tr=p['tr'], like=p['like'],
                   w=[[w, x.get('team', ''), x.get('opp', '')] + [rnd(x.get(k, 0.0)) for k in WK[3:]] for w, x in sorted(p['weeks'].items())],
                   pl=sorted(p['plays'], key=lambda x: (x[0], -x[2], x[3])), pend=p['pend'],
                   car=[years[y][pid] for y in sorted(years) if pid in years[y]] + ([now[pid]] if pid in now else []))
        by_team[p['team']][pid] = rec
        index.append(dict(id=pid, name=rec['name'], team=p['team'], pos=p['pos'], role=p['role'], q=rec['q'], num=rec['num'], photo=photo, slug=p['slug'],
                          g=int(s['g']), snap=round(s['snap']), tkl=int(s['tkl']), sk=round(s['sk'], 1), prs=int(s['prs']), int=int(s['int']), pd=int(s['pd'])))
    for t, ps in by_team.items():
        json.dump(dict(season=season, through=through, team=t, wk=WK, pk=PK, car=CAR, players=ps), open(os.path.join(out, 'defenders', t + '.json'), 'w'), separators=(',', ':'))
    json.dump(dict(season=season, through=through, pools={k: len(v) for k, v in pools.items()}, players=sorted(index, key=lambda x: (-x['snap'], x['name']))),
              open(os.path.join(out, 'defenders', 'index.json'), 'w'), separators=(',', ':'))
    print('defenders:', len(index), 'pages,', ' / '.join('%s %d' % (k, len(v)) for k, v in sorted(pools.items())), 'in the pools,', len(by_team), 'teams')
    return index
