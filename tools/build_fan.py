"""The weekly fantasy page's file (SCOPE §78): data/fan/w{N}.json for the coming week N.

Read back from the files the build has already written (schedule.json, fantasy.json, players/{TEAM}.json, plays/{TEAM}.json), so
the page shows the same numbers as the player pages and the Matchups chart. Only games before week N count, so a Thursday game
already played doesn't leak into the week's own page.

  risers / fallers  share over his team's last three games against its earlier ones: backs, carries plus targets over the team's
                    designed runs plus targets; receivers and tight ends, targets over the team's targets. Team totals are counted
                    from the plays (every target and designed run with a ball carrier), so a player missing from a roster can't
                    inflate a share. Out: teams on bye in week N, and anyone with fewer than two games on each side of the split.
  rz                carries and targets inside the 20 over each team's last three games: inside the 5, touchdowns, share of the
                    team's red-zone plays.
  mu                every offense playing in week N against its opponent's rank and points allowed by position, all three formats.
  bye               the teams off and each one's top three scorers (half PPR, three games or more).

The file is rewritten by every build until week N's first kickoff, then left as it was, so a past week's page stays as it read
before its games. A week whose first file would come after its kickoff gets none: fantasy.json would already count those games.
Run on its own: python3 tools/build_fan.py <data dir> [--force]
"""
import datetime, glob, json, os, re, sys
from collections import defaultdict

POS = ('RB', 'WR', 'TE')
RISERS, FALLERS, RZ_N = 5, 3, 6
RISE_FLOOR, FALL_FLOOR = 10.0, 15.0   # a riser holds at least 10% now; a faller had at least 15% before
FMT = ('std', 'half', 'ppr')


def _kick_utc(g):
    """A game's kickoff in UTC from its ET date and time; None when either is missing."""
    try:
        from zoneinfo import ZoneInfo
        t = datetime.datetime.strptime(f"{g['date']} {g.get('time') or '13:00'}", '%Y-%m-%d %H:%M').replace(tzinfo=ZoneInfo('America/New_York'))
        return t.astimezone(datetime.timezone.utc)
    except Exception:
        return None


def started(games, now):
    """True once any game of the week is under way: a status past upcoming, a score, or its kickoff time gone by."""
    for g in games:
        if (g.get('status') or 'upcoming') != 'upcoming' or g.get('home_score') is not None: return True
        k = _kick_utc(g)
        if k and now >= k: return True
    return False


def scored(desc):
    """True when the ball carrier himself scored the play's touchdown (Greptile, PR #146): read only what stood after a replay
    reversal, and a fumble counts against him unless he recovered it himself. An interception never reaches here."""
    t = str(desc or '').upper()
    if 'REVERSED' in t: t = t.rsplit('REVERSED', 1)[1]
    if 'FUMBLE' not in t: return True
    return 'AND RECOVERS' in t and 'RECOVERED BY' not in t


def _wk(gid):
    try: return int(str(gid).split('_')[1])
    except (IndexError, ValueError): return 0


def build(out, now=None, force=False):
    now = now or datetime.datetime.now(datetime.timezone.utc)
    S = json.load(open(f'{out}/schedule.json')); N = int(S.get('current_week') or 0)
    games = [g for g in S.get('weeks', {}).get(str(N), []) if g.get('game_type', 'REG') == 'REG']
    if not N or not games: print('fantasy page: no regular-season week ahead'); return None
    path = f'{out}/fan/w{N}.json'
    if started(games, now) and not force:
        # kept as it was; and a week with no file yet gets none, since fantasy.json may already count the games played (Greptile, PR #146)
        print(f'fantasy page: week {N} has kicked off; ' + (f'w{N}.json kept as it was' if os.path.exists(path) else f'no w{N}.json made after kickoff'))
        return path if os.path.exists(path) else None
    if not os.path.exists(f'{out}/fantasy.json'): print('fantasy page: no fantasy.json yet'); return None
    F = json.load(open(f'{out}/fantasy.json'))
    nxt = {}
    for g in games: nxt[g['away']] = (g['home'], False); nxt[g['home']] = (g['away'], True)

    # players, and each team's targets and designed runs by week from the plays
    P = {}
    for f in glob.glob(f'{out}/players/*.json'):
        if not re.search(r'/[A-Z]{2,3}\.json$', f): continue
        d = json.load(open(f))
        for p in d.get('players', []):
            P[p['id']] = dict(id=p['id'], n=p['name'], pos=p.get('pos'), t=d['team'], num=p.get('num'), ph=p.get('photo'),
                              weeks={int(w['week']): w for w in p.get('weeks', [])}, snaps={int(k): v for k, v in (p.get('snaps') or {}).items()},
                              snapteam={int(k): v for k, v in (p.get('snapteam') or {}).items()}, fan=p.get('fan'))
    teams = sorted({p['t'] for p in P.values()})
    tot = defaultdict(lambda: defaultdict(int)); tgt = defaultdict(lambda: defaultdict(int)); plays = {}
    for f in glob.glob(f'{out}/plays/*.json'):
        if not re.search(r'/[A-Z]{2,3}\.json$', f): continue
        d = json.load(open(f)); K = {k: i for i, k in enumerate(d['keys'])}; t = d['team']; rows = []
        for r in d['plays']:
            wk = _wk(r[K['g']])
            if not wk or wk >= N or not r[K['pid']]: continue
            pt, k = r[K['pt']], r[K['k']]
            if pt == 'pass' and k in ('comp', 'inc', 'int'): kind = 't'; tot[t][wk] += 1; tgt[t][wk] += 1
            elif pt == 'run' and k == 'run': kind = 'c'; tot[t][wk] += 1
            else: continue
            mine = bool(r[K['td']]) and k in ('comp', 'run') and scored(r[K['desc']])
            rows.append((wk, kind, r[K['pid']], r[K['yl']], mine))
        plays[t] = rows
    team_weeks = lambda t: sorted(w for w, v in tot[t].items() if v > 0)

    def team_of(p, w):
        return (p['weeks'].get(w) or {}).get('team') or p['snapteam'].get(w) or p['t']

    def share(p, w):
        r = p['weeks'].get(w) or {}; t = team_of(p, w)
        if p['pos'] == 'RB': return 100.0 * ((r.get('carries') or 0) + (r.get('targets') or 0)) / tot[t][w] if tot[t].get(w) else 0.0
        return 100.0 * (r.get('targets') or 0) / tgt[t][w] if tgt[t].get(w) else 0.0

    def rk(team, pos):
        """His week-N opponent and that defense's rank and points allowed to his position, in every format."""
        if team not in nxt: return None
        o, home = nxt[team]
        rks = (F.get('rank', {}).get(o, {}) or {}).get(pos); al = (F.get('allow', {}).get(o, {}) or {}).get(pos)
        return dict(o=o, h=1 if home else 0, rk=[int(x) for x in rks] if rks else None, al=[round(float(x), 1) for x in al] if al else None)

    def pts(w, i):
        s = float(w.get('fantasy_points') or 0); p = float(w.get('fantasy_points_ppr') or 0)
        return (s, (s + p) / 2, p)[i]

    rows = {pos: [] for pos in POS}
    for p in P.values():
        if p['pos'] not in POS or p['t'] not in nxt: continue
        tw = team_weeks(p['t'])
        if len(tw) < 5: continue
        last, before = tw[-3:], tw[:-3]
        lp = [w for w in last if p['snaps'].get(w, 0) > 0]; bp = [w for w in before if p['snaps'].get(w, 0) > 0]
        if len(lp) < 2 or len(bp) < 2: continue
        now_ = sum(share(p, w) for w in lp) / len(lp); bef = sum(share(p, w) for w in bp) / len(bp)
        ppg = [round(sum(pts(p['weeks'].get(w) or {}, i) for w in lp) / len(lp), 1) for i in range(3)]
        rows[p['pos']].append(dict(id=p['id'], n=p['n'], t=p['t'], pos=p['pos'], num=p['num'], ph=p['ph'], now=round(now_, 1), bef=round(bef, 1), d=round(now_ - bef, 1),
                                   s=[round(share(p, w), 1) if p['snaps'].get(w, 0) > 0 else None for w in tw], ppg=ppg, m=rk(p['t'], p['pos'])))
    risers = {pos: sorted([r for r in rows[pos] if r['now'] >= RISE_FLOOR and r['d'] > 0], key=lambda r: (-r['d'], r['n']))[:RISERS] for pos in POS}
    fallers = {pos: sorted([r for r in rows[pos] if r['bef'] >= FALL_FLOOR and r['d'] < 0], key=lambda r: (r['d'], r['n']))[:FALLERS] for pos in POS}

    # red zone: each team's last three games, inside the 20
    # a traded player counts only his current team's plays, so his touches and the team total share one window (Greptile, PR #146)
    rz = defaultdict(lambda: dict(c=0, tg=0, in5=0, td=0)); rzteam = defaultdict(int)
    for t, rs in plays.items():
        last = set(team_weeks(t)[-3:])
        for wk, kind, pid, yl, td in rs:
            if wk not in last or yl is None or yl > 20: continue
            rzteam[t] += 1
            if (P.get(pid) or {}).get('t') != t: continue
            x = rz[pid]; x['c' if kind == 'c' else 'tg'] += 1; x['in5'] += 1 if yl <= 5 else 0; x['td'] += 1 if td else 0
    rzl = []
    for pid, x in rz.items():
        p = P.get(pid)
        if not p or p['pos'] not in POS or p['t'] not in nxt: continue
        o = x['c'] + x['tg']
        rzl.append(dict(id=pid, n=p['n'], t=p['t'], pos=p['pos'], c=x['c'], tg=x['tg'], in5=x['in5'], td=x['td'], sh=round(100 * o / rzteam[p['t']]) if rzteam[p['t']] else 0, m=rk(p['t'], p['pos'])))
    key = lambda r: (-(r['c'] + r['tg']), -r['in5'], -r['td'], r['n'])
    rz_out = dict(rb=sorted([r for r in rzl if r['pos'] == 'RB'], key=key)[:RZ_N], rec=sorted([r for r in rzl if r['pos'] in ('WR', 'TE')], key=key)[:RZ_N])

    # matchups at a glance: every offense this week against its opponent, by position, in every format
    mu = {}
    for pos in ('QB', 'RB', 'WR', 'TE'):
        mu[pos] = []
        for g in games:
            for t in (g['away'], g['home']):
                m = rk(t, pos)
                if m and m['rk'] and m['al']: mu[pos].append(dict(t=t, o=m['o'], h=m['h'], rk=m['rk'], al=m['al']))

    byes = [t for t in teams if t not in nxt]
    bye = []
    for t in byes:
        # the top three in each format, so the switch can change who's listed (Greptile, PR #146)
        top = {}
        for f in FMT:
            pl = [p for p in P.values() if p['t'] == t and p['pos'] in ('QB', 'RB', 'WR', 'TE') and ((p.get('fan') or {}).get('s', {}).get(f) or {}).get('g', 0) >= 3]
            pl = sorted(pl, key=lambda p: (-p['fan']['s'][f]['ppg'], p['n']))[:3]
            top[f] = [dict(id=p['id'], n=p['n'], pos=p['pos'], ppg=round(float(p['fan']['s'][f]['ppg']), 1)) for p in pl]
        bye.append(dict(t=t, top=top))

    gl = [dict(id=g['id'], away=g['away'], home=g['home'], day=g.get('day'), date=g.get('date'), time=g.get('time')) for g in games]
    rec = dict(season=S.get('season'), week=N, thru=N - 1, built=now.strftime('%Y-%m-%dT%H:%M:%SZ'), games=gl, byes=byes,
               risers=risers, fallers=fallers, rz=rz_out, mu=mu, bye=bye)
    check(rec)
    os.makedirs(f'{out}/fan', exist_ok=True)
    body = json.dumps(rec, separators=(',', ':'))
    # rewrite only when something besides the build time moved, so an hourly run with nothing new keeps the file (and the manifest) as it was
    if os.path.exists(path):
        old = json.load(open(path)); old_b = old.pop('built', None); cmp = dict(rec); cmp.pop('built')
        if old == json.loads(json.dumps(cmp)): print(f'fantasy page: week {N} unchanged'); return path
    open(path, 'w').write(body)
    print(f'fantasy page: week {N}', {p: [r['n'] for r in risers[p][:1]] for p in POS}, 'byes', byes)
    return path


def check(rec):
    """The file's shape, and nobody on bye on the week's lists (SCOPE §78 Tests). Raises on a problem, so the build stops."""
    off = set(rec['byes'])
    for k in ('season', 'week', 'thru', 'built', 'games', 'byes', 'risers', 'fallers', 'rz', 'mu', 'bye'):
        assert k in rec, f'fan file missing {k}'
    for grp in ('risers', 'fallers'):
        for pos in POS:
            for r in rec[grp][pos]:
                assert r['t'] not in off, f"{r['n']} ({r['t']}) is on bye but on the {grp}"
                assert len(r['ppg']) == 3 and r['m'] and r['m']['o'] and len(r['s']) >= 5, f"{r['n']}: a {grp} row is short"
    for side in ('rb', 'rec'):
        for r in rec['rz'][side]: assert r['t'] not in off, f"{r['n']} ({r['t']}) is on bye but on the red zone list"
    for pos, rs in rec['mu'].items():
        for r in rs: assert r['t'] not in off and len(r['rk']) == 3, f'{pos} matchup row for {r["t"]} is wrong'
    for b in rec['bye']: assert b['t'] in off and set(b['top']) == set(FMT), f"bye row for {b['t']} is wrong"


if __name__ == '__main__':
    build(sys.argv[1] if len(sys.argv) > 1 else 'data', force='--force' in sys.argv)
