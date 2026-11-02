"""Mock for SCOPE §40: the week in review (?view=week&week=N). Built from the site's own published data (data/: schedule, wp/wN,
box/wN, plays/{TEAM}, players/{TEAM}, movers) inside the live 1.15.2 Leaders page shell. week_data() is the shape the build will
write as week/w{N}.json; render() is what week.php will draw. Writes week-7.html (a finished week) and week-8.html (a week with
games still to play). python3 -I mock.py"""
import json, re, html, glob, os
esc = html.escape
D = '../data'
T = json.load(open(f'{D}/teams.json')); S = json.load(open(f'{D}/schedule.json'))
IDX = {p['id']: p for p in json.load(open(f'{D}/players/index.json'))['players']}
PLAYS = {os.path.basename(f)[:-5]: json.load(open(f)) for f in glob.glob(f'{D}/plays/[A-Z]*.json')}
PFILES = {os.path.basename(f)[:-5]: json.load(open(f)) for f in glob.glob(f'{D}/players/[A-Z]*.json') if os.path.basename(f)[:-5].isupper() and len(os.path.basename(f)) <= 8}
MOV = json.load(open(f'{D}/players/movers.json'))
ROOT = open('src/root.html').read(); LIVE_P = open('src/live-players.html').read()

def ordn(n): n = int(n); return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
sgn = lambda v, k=2: ('+' if v > 0 else ('−' if v < 0 else '')) + f'{abs(v):.{k}f}'
nick = lambda a: T[a]['nick']
logo = lambda a, s=18: f'<img class="lg" src="{T[a]["logo"]}" alt="" width="{s}" height="{s}">'
def clock(el):  # elapsed regulation seconds -> "Q4 2:26"
    q = min(4, el // 900 + 1); r = 900 * q - el; return f'Q{q} {r // 60}:{r % 60:02d}'
def clean(d):
    d = re.sub(r'^\(\s*\d*:\d+\)\s*', '', str(d)); d = re.sub(r'\((?:No Huddle, )?Shotgun\)\s*|\(No Huddle\)\s*', '', d)
    d = re.sub(r'[^.]*reported in as eligible\.\s*', '', d); d = re.sub(r'\b\d{1,2}-(?=[A-Z])', '', d); d = re.sub(r'\s*\([^)]*\)', '', d)
    d = re.sub(r'\s+', ' ', d).strip(); return d if len(d) <= 120 else d[:117].rsplit(' ', 1)[0] + '…'
def head(team, num, photo, s=24):
    c = T[team]['c1']
    svg = (f'<svg class="dbd-mk dbd-jersey" width="{s}" height="{s}" viewBox="0 0 40 40" role="img" aria-label="{esc(nick(team))} #{num or ""}"><rect width="40" height="40" rx="5" fill="{c}"/>'
           f'<rect x="2.5" y="2.5" width="35" height="35" rx="3.5" fill="none" stroke="#fff" stroke-opacity=".5" stroke-width="1.5"/><text x="20" y="27" text-anchor="middle" font-size="19" font-family="Tourney,Arial Black,sans-serif" font-weight="800" fill="#fff">{esc(str(num or ""))}</text></svg>')
    img = f'<img src="{esc(photo.replace("f_auto,q_auto", "f_auto,q_auto,w_48,h_48,c_fill,g_face"))}" alt="" width="{s}" height="{s}" loading="lazy" referrerpolicy="no-referrer" style="background:{c}" onerror="this.remove()">' if photo else ''
    return f'<span class="dbd-head" style="width:{s}px;height:{s}px">{svg}{img}</span>'

# ======================================================================= the week file (build: week/w{N}.json)
def week_data(N):
    games = S['weeks'][str(N)]; WP = json.load(open(f'{D}/wp/w{N}.json'))['games'] if os.path.exists(f'{D}/wp/w{N}.json') else {}
    BX = json.load(open(f'{D}/box/w{N}.json')) if os.path.exists(f'{D}/box/w{N}.json') else {'keys': [], 'games': {}}
    played = {g['away'] for g in games} | {g['home'] for g in games}; byes = sorted(set(T) - played)
    G = []; swings = []
    for g in games:
        r = dict(id=g['id'], away=g['away'], home=g['home'], day=g['day'], date=g['date'], time=g['time'], spread=g['spread'], status=g['status'],
                 a=g['away_score'], h=g['home_score'])
        w = WP.get(g['id'])
        if g['status'] == 'final' and w:
            s = w['s']; hw = r['h'] > r['a']; tie = r['h'] == r['a']; win = None if tie else (g['home'] if hw else g['away'])
            ch = lambda x: x if hw else 1000 - x  # the winner's chance
            pre_w = None if tie else ch(int(w['pre'])); low = None if tie else min(ch(p[1]) for p in s)
            step = max(1, len(s) // 40); r['spark'] = [s[i][1] for i in range(0, len(s), step)] + [s[-1][1] if not w['ot'] else 500]
            if not hw and not tie: r['spark'] = [1000 - x for x in r['spark']]  # drawn from the winner's side: above the middle, the winner was ahead
            lead = 0; lc = 0
            for p_ in s:
                d_ = (p_[2] > p_[3]) - (p_[2] < p_[3])
                if d_ and lead and d_ != lead: lc += 1
                if d_: lead = d_
            r['lc'] = lc
            big = max(w['top'], key=lambda x: abs(x[1]))
            tags = []
            if win and pre_w < 500: tags.append(['upset', f'Upset · {round(pre_w / 10)}% at kickoff'])
            if win and low < 250: tags.append(['comeback', f'Comeback from {max(1, round(low / 10))}%'])
            if w['ot']: tags.append(['ot', 'Overtime'])
            if win and low > 500 and not w['ot']: tags.append(['wire', 'Led all the way'])
            if tie: tags.append(['tie', 'Tie'])
            r.update(win=win, pre=int(w['pre']), pre_w=pre_w, low=low, flips=w['flips'], ot=bool(w['ot']), tags=tags,
                     big=[round(abs(big[1]) / 10), clock(s[big[0]][0]), g['home'] if big[1] > 0 else g['away']])
            # drama: how far the winner fell plus how often it flipped (sorts the "most dramatic" order)
            r['drama'] = (1000 - (low if low is not None else 500)) + 120 * r['lc'] + 30 * w['flips'] + (200 if w['ot'] else 0)
            for x in w['top']:
                if ' kneels ' in x[3]: continue  # regulation ending tied moves the model to 50% on the kneel; that is not a play that swung it
                helped = g['home'] if x[1] > 0 else g['away']
                swings.append(dict(g=g['id'], m=f"{g['away']} at {g['home']}", t=helped, off=x[2], c=clock(s[x[0]][0]), d=x[3], v=round(abs(x[1]) / 10, 1),
                                   ba=round((1000 - s[x[0]][1] if helped == g['away'] else s[x[0]][1]) / 10), aa=None))
        G.append(r)
    swings.sort(key=lambda x: -x['v'])
    # ---- the best plays by EPA, and each offense and defense's game, from the play files
    ids = {g['id'] for g in games if g['status'] == 'final'}; opp = {}
    for g in games: opp[g['away']] = g['home']; opp[g['home']] = g['away']
    eplays = []; off = {}; season = {}
    for t, P in PLAYS.items():
        K = P['keys']; ix = {k: i for i, k in enumerate(K)}
        wk_of = {gm['id']: gm['week'] for gm in P['games']}
        for row in P['plays']:
            gid = row[ix['g']]; e = row[ix['epa']]
            if e is None: continue
            ss = season.setdefault(t, {}).setdefault(wk_of.get(gid), [0, 0.0, 0]); ss[0] += 1; ss[1] += e; ss[2] += row[ix['succ']] or 0
            if gid in ids:
                eplays.append(dict(t=t, o=opp.get(t), g=gid, q=row[ix['q']], c=f"Q{row[ix['q']]} {str(row[ix['t']]).lstrip('0') if not str(row[ix['t']]).startswith('00') else str(row[ix['t']])[1:]}", e=round(e, 2), d=clean(row[ix['desc']]),
                                   pid=row[ix['pid']] if row[ix['pt']] == 'run' else (row[ix['qb']] or row[ix['pid']]), pl=row[ix['pl']], rid=row[ix['pid']], k=row[ix['pt']]))
    eplays.sort(key=lambda x: -x['e'])
    teams = []
    for t, wks in season.items():
        if N not in wks or t not in opp: continue
        n, e, s_ = wks[N]; rest = [v for w, v in wks.items() if w != N]; rn = sum(v[0] for v in rest); re_ = sum(v[1] for v in rest)
        dn, de = season[opp[t]].get(N, [0, 0, 0])[:2]
        orest = [v for w, v in season[opp[t]].items() if w != N]
        g = next(x for x in G if t in (x['away'], x['home']))
        pf, pa = (g['h'], g['a']) if g['home'] == t else (g['a'], g['h'])
        teams.append(dict(t=t, o=opp[t], home=g['home'] == t, pf=pf, pa=pa, n=n, epa=round(e / n, 3), sr=round(s_ / n, 3), usual=round(re_ / rn, 3) if rn else None,
                          dn=dn, depa=round(de / dn, 3) if dn else None, dusual=None))
    # a defense's usual: EPA a play it allowed in its other games
    allowed = {}
    for t, wks in season.items():
        for w, v in wks.items():
            pass
    for tm in teams:
        o_rest = []  # plays offenses ran against tm in other weeks = each opponent's plays in those games
        for t2, P in PLAYS.items():
            for gm in P['games']:
                if gm['opp'] == tm['t'] and gm['week'] != N and gm['week'] in season[t2]: o_rest.append(season[t2][gm['week']])
        n_, e_ = sum(v[0] for v in o_rest), sum(v[1] for v in o_rest); tm['dusual'] = round(e_ / n_, 3) if n_ else None
    # ---- the players: each line from the box file (weekly stats, or the play-by-play when they lag); fantasy from the weekly lines
    k = {x: i for i, x in enumerate(BX['keys'])}; lines = []
    for gid, gb in BX['games'].items():
        for sd in ('away', 'home'):
            tb = gb[sd]; tm = tb['team']
            for L in tb['off']:
                v = lambda x: L[k[x]] if x in k else 0
                pos = (IDX.get(v('id')) or {}).get('pos') or v('pos'); pos = 'RB' if pos == 'FB' else pos
                if pos not in ('QB', 'RB', 'WR', 'TE'): continue
                pe, ru, rc = v('passing_epa'), v('rushing_epa'), v('receiving_epa')
                tot = pe + ru if pos == 'QB' else (ru + rc if pos == 'RB' else rc)
                if pos == 'QB': txt = f"{v('completions')}/{v('attempts')}, {v('passing_yards')} yds" + (f", {v('passing_tds')} TD" if v('passing_tds') else '') + (f", {v('passing_interceptions')} INT" if v('passing_interceptions') else '') + (f" · {v('carries')}-{v('rushing_yards')} rush" if v('carries') >= 3 else '')
                elif pos == 'RB': txt = f"{v('carries')} car, {v('rushing_yards')} yds" + (f", {v('rushing_tds')} TD" if v('rushing_tds') else '') + (f" · {v('receptions')}-{v('receiving_yards')} rec" if v('receptions') else '')
                else: txt = f"{v('receptions')}/{v('targets')}, {v('receiving_yards')} yds" + (f", {v('receiving_tds')} TD" if v('receiving_tds') else '')
                ip = IDX.get(v('id')) or {}
                fstd = v('passing_yards') / 25 + 4 * v('passing_tds') - 2 * v('passing_interceptions') + (v('rushing_yards') + v('receiving_yards')) / 10 + 6 * (v('rushing_tds') + v('receiving_tds'))
                lines.append(dict(id=v('id'), n=ip.get('name') or v('name'), t=tm, o=opp.get(tm), pos=pos, num=ip.get('num'), ph=ip.get('photo'), e=round(tot, 1), x=txt, src=tb.get('src', 'stats'), g=gid,
                                  f=[round(fstd, 1), round(fstd + v('receptions') / 2, 1), round(fstd + v('receptions'), 1)]))
    fan = {}
    for t, P in PFILES.items():
        for p in P.get('players', []):
            for w in p.get('weeks') or []:
                if int(w.get('week') or 0) != N or p['pos'] not in ('QB', 'RB', 'WR', 'TE', 'FB'): continue
                std, ppr = w.get('fantasy_points') or 0, w.get('fantasy_points_ppr') or 0
                fan[p['id']] = dict(id=p['id'], n=p['name'], t=w.get('team') or t, pos='RB' if p['pos'] == 'FB' else p['pos'], num=p.get('num'), ph=p.get('photo'), f=[round(std, 1), round((std + ppr) / 2, 1), round(ppr, 1)])
    for l in lines:  # a game the weekly stats haven't posted: points from the box line (no fumbles or two-point tries until they do)
        if l['src'] == 'pbp' and l['id'] not in fan: fan[l['id']] = dict(id=l['id'], n=l['n'], t=l['t'], pos=l['pos'], num=l['num'], ph=l['ph'], f=l['f'], est=1)
    for r in fan.values(): r['o'] = opp.get(r['t'])
    fx = {fm: sorted(fan.values(), key=lambda r: -r['f'][i])[:16] for i, fm in enumerate(('std', 'half', 'ppr'))}
    pl = {pos: sorted([l for l in lines if l['pos'] == pos], key=lambda r: -r['e'])[:8] for pos in ('QB', 'RB', 'WR', 'TE')}
    nfin = sum(g['status'] == 'final' for g in games)
    return dict(week=N, n=len(games), fin=nfin, byes=byes, games=G, swings=swings[:10], plays=eplays[:10], teams=teams, players=pl, fx=fx,
                pbp=sorted({l['g'] for l in lines if l['src'] == 'pbp'}), movers=MOV['week'] == N and nfin == len(games))

# ======================================================================= the page (plugin: views/week.php)
def spark(g):
    pts = g['spark']; n = len(pts) - 1; W, H = 120, 34
    xy = ' '.join(f'{i / n * W:.1f},{(1 - p / 1000) * H:.1f}' for i, p in enumerate(pts))
    wc = T[g['win']]['c1'] if g.get('win') else '#6b6f78'
    return (f'<svg class="wk-sp" viewBox="0 0 {W} {H}" preserveAspectRatio="none" aria-hidden="true"><defs><clipPath id="t{g["id"]}"><rect width="{W}" height="{H / 2}"/></clipPath><clipPath id="b{g["id"]}"><rect y="{H / 2}" width="{W}" height="{H / 2}"/></clipPath></defs>'
            f'<polygon points="0,{H / 2} {xy} {W},{H / 2}" fill="{wc}" fill-opacity=".45" clip-path="url(#t{g["id"]})"/><polygon points="0,{H / 2} {xy} {W},{H / 2}" fill="#6b6f78" fill-opacity=".22" clip-path="url(#b{g["id"]})"/>'
            f'<line x1="0" x2="{W}" y1="{H / 2}" y2="{H / 2}" stroke="currentColor" stroke-opacity=".35" stroke-dasharray="2 2" vector-effect="non-scaling-stroke"/><polyline points="{xy}" fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke"/></svg>')

def game_card(g):
    href = f'?game={g["id"]}'
    if g['status'] != 'final':
        h, m = map(int, g['time'].split(':')); tm = f'{(h - 1) % 12 + 1}:{m:02d} {"PM" if h >= 12 else "AM"}'
        return (f'<a class="wk-g up" href="{href}" data-k="{g["date"]} {g["time"]}" data-d="0"><div class="wk-gt"><span>{logo(g["away"])}<b>{nick(g["away"])}</b></span><span>{logo(g["home"])}<b>{nick(g["home"])}</b></span></div>'
                f'<div class="wk-gm"><span class="wk-up">{esc(g["day"])} · {tm} ET</span><small>{esc(nick(g["home"] if g["spread"] > 0 else g["away"]))} by {abs(g["spread"])}</small></div></a>')
    wa, wh = g['win'] == g['away'], g['win'] == g['home']
    row = lambda a, s, w: f'<span class="{"w" if w else ""}">{logo(a)}<b>{nick(a)}</b><em>{s}</em></span>'
    tags = ''.join(f'<i class="wk-tag {c}">{esc(t)}</i>' for c, t in g['tags'])
    tip = esc(f'<b>{nick(g["away"])} at {nick(g["home"])}</b><br>Kickoff: {nick(g["home"] if g["pre"] >= 500 else g["away"])} {round(max(g["pre"], 1000 - g["pre"]) / 10)}%<br>Lead changes: {g["lc"]} · the favorite flipped {g["flips"]} times<br>Biggest swing: {g["big"][0]} pts, {g["big"][1]}')
    return (f'<a class="wk-g" href="{href}" data-k="{g["date"]} {g["time"]}" data-d="{g["drama"]}" data-tip="{tip}"><div class="wk-gt">{row(g["away"], g["a"], wa)}{row(g["home"], g["h"], wh)}</div>'
            f'<div class="wk-gm">{spark(g)}<small>Biggest swing <b>{g["big"][0]} pts</b> · {g["big"][1]}</small></div>' + (f'<div class="wk-tags">{tags}</div>' if tags else '') + '</a>')

def render(W, mk=True):
    N = W['week']; done = W['fin'] == W['n']; fin = [g for g in W['games'] if g['status'] == 'final']
    M = lambda label: f' mk-new" data-mk="{esc(label)}' if mk else ''
    first, last = W['games'][0], W['games'][-1]
    import datetime as dt
    fd = lambda s: dt.date.fromisoformat(s).strftime('%b %-d')
    kicker = f'2026 season · {fd(first["date"])} to {fd(last["date"])} · {W["n"]} games' + (f' · byes: {", ".join(W["byes"])}' if W['byes'] else '')
    title = (f'<div class="dbd-ttitle"><div class="t-stagger is-shown"><p class="dbd-kicker">{kicker}</p><h1><span>Week {N}</span> in review</h1>'
             f'<p class="dbd-sub">What happened this week: every game\'s swings in one strip, the plays that decided them, the offenses and defenses that had their day, the players who carried them' + (', and whose role moved' if done else '') + '.</p></div>'
             f'<div class="dbd-badge">{W["fin"]} of {W["n"]} final</div></div>')
    CUR = ' aria-current="page"'
    weeks = sorted(int(w) for w, gl in S['weeks'].items() if any(g['status'] == 'final' for g in gl))
    picker = (f'<div class="wk-pick{M("New · every week with a final game")}"><nav class="dbd-wkbar" aria-label="Week"><div class="dbd-wktabs">' +
              ''.join(f'<a href="week-{w}.html"{CUR if w == N else ""}>Week {w}' + ('' if all(g['status'] == 'final' for g in S['weeks'][str(w)]) else '<i class="wk-live" title="games still to play"></i>') + '</a>' for w in weeks) + '</div></nav></div>')
    jump = '<nav class="dbd-jump" aria-label="On this page"><a href="#wk-games">The games</a><a href="#wk-plays">The plays</a><a href="#wk-teams">Teams</a><a href="#wk-players">Players</a>' + ('<a href="#wk-movers">Movers</a>' if done else '') + '</nav>'

    # ---- the short version
    up = [g for g in fin if g['win'] and g['pre_w'] < 500]; cb = [g for g in fin if g['win']]
    up_g = min(up, key=lambda g: g['pre_w']) if up else None; cb_g = min(cb, key=lambda g: g['low']) if cb else None; wild = max(fin, key=lambda g: (g['lc'], g['drama']))
    offs = sorted(W['teams'], key=lambda r: -r['epa']); defs = sorted(W['teams'], key=lambda r: r['depa'] if r['depa'] is not None else 9)
    star = max((l for p in W['players'].values() for l in p), key=lambda l: l['e'])
    loser = lambda g: g['home'] if g['win'] == g['away'] else g['away']
    def score(g): return f'{max(g["a"], g["h"])}–{min(g["a"], g["h"])}'
    li = []
    if up_g: li.append(('Upset', f'The <b>{nick(up_g["win"])}</b> beat the {nick(loser(up_g))} {score(up_g)} as a <b>{round(up_g["pre_w"] / 10)}%</b> underdog at kickoff, the longest odds anyone overcame this week.', up_g['id']))
    if cb_g: li.append(('Comeback', f'The <b>{nick(cb_g["win"])}</b> were down to <b>{max(1, round(cb_g["low"] / 10))}%</b> against the {nick(loser(cb_g))} and won {score(cb_g)}.' if cb_g['low'] < 300 else f'Nobody came back from far: the lowest any winner fell was <b>{round(cb_g["low"] / 10)}%</b> (the {nick(cb_g["win"])}).', cb_g['id']))
    li.append(('Wildest', f'<b>{nick(wild["away"])} at {nick(wild["home"])}</b>: the lead changed hands <b>{wild["lc"]}</b> time{"s" if wild["lc"] != 1 else ""}' + (' and it went to overtime' if wild['ot'] else '') + f'; the {nick(wild["win"]) if wild["win"] else "game"} ' + (f'won {score(wild)}' if wild['win'] else 'ended tied') + f', and the biggest swing was {wild["big"][0]} points at {wild["big"][1]}.', wild['id']))
    li.append(('Best offense', f'The <b>{nick(offs[0]["t"])}</b>: <b>{sgn(offs[0]["epa"])}</b> EPA a play against the {nick(offs[0]["o"])}, ' + (f'{sgn(offs[0]["epa"] - offs[0]["usual"])} above their usual.' if offs[0]['usual'] is not None else 'their best game.'), None))
    li.append(('Best defense', f'The <b>{nick(defs[0]["t"])}</b> held the {nick(defs[0]["o"])} to <b>{sgn(defs[0]["depa"])}</b> EPA a play' + (f', {sgn(defs[0]["depa"] - defs[0]["dusual"])} against what they usually allow.' if defs[0]['dusual'] is not None else '.'), None))
    li.append(('Player', f'<b>{esc(star["n"])}</b> ({star["t"]} {star["pos"]}): <b>{sgn(star["e"], 1)}</b> EPA, {esc(star["x"])}.', None))
    short = (f'<section class="dbd-panel dbd-short{M("New · the short version")}"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b>' + ('' if done else ' · so far') + '</span></div><ul>' +
             ''.join(f'<li><a href="{("?game=" + g) if g else "#wk-teams"}">{lab}</a><span>{txt}</span></li>' for lab, txt, g in li) + '</ul></section>')

    # ---- band 1: the games
    sortbar = '<div class="dbd-seg t-tabs wk-sort" role="tablist" aria-label="Order"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" aria-selected="true" data-sort="k">Kickoff order</button><button class="t-tab" type="button" role="tab" aria-selected="false" data-sort="d">Most dramatic</button></div>'
    chips = [('Games final', f'{W["fin"]} of {W["n"]}'), ('Overtime', str(sum(g['ot'] for g in fin))), ('Comebacks', str(sum(1 for g in fin if g['win'] and g['low'] < 250))), ('Upsets', str(len(up))),
             ('Points', str(sum(g['a'] + g['h'] for g in fin))), ('Biggest swing', f'{max(g["big"][0] for g in fin)} pts')]
    band1 = (f'<section class="dbd-band" id="wk-games"><h2 class="dbd-bandh">The games<span>every final with its win-probability line</span></h2><section class="dbd-panel wk-games{M("New · every game in one strip")}">'
             f'<div class="dbd-ph"><span class="dbd-lab"><b>Scoreboard</b> · Week {N}</span>{sortbar}</div>'
             '<div class="wk-chips">' + ''.join(f'<div><small>{a}</small><b>{b}</b></div>' for a, b in chips) + '</div>'
             '<div class="wk-grid">' + ''.join(game_card(g) for g in W['games']) + '</div>'
             '<p class="dbd-note">Each line is the winner\'s chance of winning, snap by snap, from nflverse\'s spread-adjusted model: above the dashed middle (shaded in the winner\'s color) they were favored, below it (shaded grey) they were behind. Overtime is left off the line, as on the game page. A comeback is a winner who fell below 25%; an upset is a winner who was under 50% at kickoff. Click a game for the full chart, the box score and the plays that swung it.</p></section></section>')

    # ---- band 2: the plays
    def swing_row(i, x):
        c = T[x['t']]['c1']
        return (f'<li><span class="rk">{i + 1}</span><span class="wk-pd"><span class="wk-pm">{logo(x["t"], 16)}<b>{nick(x["t"])}</b> · {x["c"]} · <a href="?game={x["g"]}">{x["m"]}</a></span><span class="wk-pt">{esc(x["d"])}</span></span>'
                f'<span class="wk-pv"><b>+{x["v"]:.0f}</b><small>{x["ba"]}% → {min(100, round(x["ba"] + x["v"]))}%</small><i style="--w:{min(100, x["v"] * 1.6):.0f}%;background:{c}"></i></span></li>')
    def epa_row(i, x):
        ip = IDX.get(x['rid']) or IDX.get(x['pid']) or {}
        who = f'<a href="?player={x["rid"]}">{esc(ip.get("name") or x["pl"] or "")}</a>' if x['rid'] else ''
        return (f'<li><span class="rk">{i + 1}</span><span class="wk-pd"><span class="wk-pm">{logo(x["t"], 16)}<b>{nick(x["t"])}</b> · {x["c"]} · vs {x["o"]}' + (f' · {who}' if who else '') + f'</span><span class="wk-pt">{esc(x["d"])}</span></span>'
                f'<span class="wk-pv"><b>+{x["e"]:.1f}</b><small>EPA</small></span></li>')
    band2 = (f'<section class="dbd-band" id="wk-plays"><h2 class="dbd-bandh">The plays<span>the ones that moved games, and the ones that moved the ball</span></h2><div class="wk-2{M("New · the plays of the week")}">'
             f'<section class="dbd-panel ld-card"><div class="ld-h"><b>The biggest swings</b><small>points of win probability, for the team they helped</small></div><ol class="ld-list wk-pl">{"".join(swing_row(i, x) for i, x in enumerate(W["swings"]))}</ol></section>'
             f'<section class="dbd-panel ld-card"><div class="ld-h"><b>The best plays by EPA</b><small>expected points added on one snap</small></div><ol class="ld-list wk-pl">{"".join(epa_row(i, x) for i, x in enumerate(W["plays"]))}</ol></section></div></section>')

    # ---- band 3: teams
    def team_rows(rows, key, usual, good_low):
        vals = [r[key] for r in rows]; lo, hi = min(vals), max(vals); span = max(abs(lo), abs(hi)) or 1
        out = ''
        for i, r in enumerate(rows):
            v = r[key]; w = abs(v) / span * 50; good = (v < 0) if good_low else (v > 0)
            res = ('W' if r['pf'] > r['pa'] else ('L' if r['pf'] < r['pa'] else 'T')) + f' {r["pf"]}–{r["pa"]}'
            u = r[usual]; du = (v - u) if u is not None else None
            out += (f'<div class="wk-tr"><span class="rk">{i + 1}</span><span class="nm">{logo(r["t"])}<b>{nick(r["t"])}</b><small>{"vs" if r["home"] else "at"} {r["o"]} · <em class="{res[0].lower()}">{res}</em></small></span>'
                    f'<span class="wk-bar"><i class="z"></i><i class="b {"good" if good else "bad"}" style="{"left:50%" if v > 0 else "right:50%"};width:{w:.1f}%"></i></span><b class="v">{sgn(v)}</b>'
                    f'<small class="u">' + ('' if du is None else f'{sgn(du)} vs usual') + '</small></div>')
        return out
    tt = '<div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" aria-selected="true" data-pane="0">Offenses</button><button class="t-tab" type="button" role="tab" aria-selected="false" data-pane="1">Defenses</button></div></div>'
    band3 = (f'<section class="dbd-band" id="wk-teams"><h2 class="dbd-bandh">Teams<span>who had their day, by EPA a play</span></h2><section class="dbd-panel dbd-tabbed wk-teams{M("New · every offense and defense this week")}">{tt}'
             f'<section class="dbd-panel dbd-pane" data-pane="0"><div class="dbd-ph"><span class="dbd-lab"><b>Offenses</b><span class="sub">EPA a play this week, against their season</span></span></div><div class="wk-trs">{team_rows(offs, "epa", "usual", False)}</div></section>'
             f'<section class="dbd-panel dbd-pane" data-pane="1" hidden><div class="dbd-ph"><span class="dbd-lab"><b>Defenses</b><span class="sub">EPA a play allowed this week, against what they usually allow</span></span></div><div class="wk-trs">{team_rows(defs, "depa", "dusual", True)}</div></section>'
             '<p class="dbd-note">EPA a play counts every pass and run (scrambles and sacks included) in the game. "Vs usual" compares it with the same team\'s average in its other games this season, so a good offense having an ordinary day reads as a dip. Each team links to its offense or defense tab.</p></section></section>')

    # ---- band 4: players
    POSN = {'QB': ('Quarterbacks', 'passing and rushing EPA'), 'RB': ('Backs', 'rushing and receiving EPA'), 'WR': ('Wide receivers', 'receiving EPA'), 'TE': ('Tight ends', 'receiving EPA')}
    def p_card(pos):
        rows = W['players'][pos]
        li_ = ''.join(f'<li><span class="rk">{i + 1}</span><span class="who">{head(r["t"], r["num"], r["ph"])}<span class="nm"><a href="?player={r["id"]}">{esc(r["n"])}</a><small>{r["t"]} vs {r["o"]} · {esc(r["x"])}</small></span></span><b class="v">{sgn(r["e"], 1)}</b></li>' for i, r in enumerate(rows))
        return f'<section class="dbd-panel ld-card"><div class="ld-h"><b>{POSN[pos][0]}</b><small>{POSN[pos][1]} this week</small></div><ol class="ld-list">{li_}</ol></section>'
    def fx_card():
        out = ''
        for i, fm in enumerate(('std', 'half', 'ppr')):
            li_ = ''.join(f'<li><span class="rk">{j + 1}</span><span class="who">{head(r["t"], r["num"], r["ph"])}<span class="nm"><a href="?player={r["id"]}">{esc(r["n"])}</a><small>{r["t"]} vs {r["o"]} · {r["pos"]}</small></span></span><b class="v">{r["f"][i]:.1f}{"*" if r.get("est") else ""}</b></li>' for j, r in enumerate(W['fx'][fm]))
            out += f'<div data-fx="{fm}"><ol class="ld-list">{li_}</ol></div>'
        seg = '<div class="dbd-seg t-tabs dbd-fxseg" role="tablist" aria-label="Fantasy scoring"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" data-fxset="std" aria-selected="false">Std</button><button class="t-tab" type="button" role="tab" data-fxset="half" aria-selected="true">Half</button><button class="t-tab" type="button" role="tab" data-fxset="ppr" aria-selected="false">PPR</button></div>'
        return f'<section class="dbd-panel ld-card wk-fx"><div class="ld-h wk-fxh"><span><b>Fantasy points</b><small>the week\'s top scorers</small></span>{seg}</div>{out}</section>'
    lag = ''
    if W['pbp']:
        lag = f'<p class="dbd-note wk-lag">Lines for {len(W["pbp"])} game{"s" if len(W["pbp"]) != 1 else ""} are counted from the play-by-play because nflverse\'s weekly stats for them haven\'t posted yet. Their fantasy points (marked *) leave out fumbles and two-point tries until the stats arrive, usually by the next morning.</p>'
    band4 = (f'<section class="dbd-band" id="wk-players"><h2 class="dbd-bandh">Players<span>the best games by position, by EPA</span></h2><div class="ld-grid{M("New · players of the week")}">'
             + p_card('QB') + p_card('RB') + fx_card() + p_card('WR') + p_card('TE') + '</div>' + lag + '</section>')

    # ---- band 5: movers (a finished week only), the existing panel from the Players page
    band5 = ''
    if W['movers']:
        a = LIVE_P.index('<section class="dbd-panel dbd-mv"'); b = LIVE_P.index('</section>', a) + len('</section>')
        band5 = (f'<section class="dbd-band" id="wk-movers"><h2 class="dbd-bandh">Movers<span>whose role broke from his own season</span></h2><div class="{M("The Players page panel, for this week")[2:] if mk else ""}">' + LIVE_P[a:b] + '</div></section>')
    elif not done:
        band5 = '<section class="dbd-band" id="wk-movers"><p class="dbd-note wk-wait">Movers for Week ' + str(N) + ' appear once every game is final.</p></section>'

    main = '<main class="dbd-content">' + title + picker + jump + short + band1 + band2 + band3 + band4 + band5 + '</main>'
    a = ROOT.index('<main'); b = ROOT.index('</main>') + len('</main>')
    out = ROOT[:a] + main + ROOT[b:]
    out = out.replace('dbd-view-leaders', 'dbd-view-week').replace('<a href="https://downbydown.com/?view=players">players</a> <span>›</span> <b>leaders</b>', '<a href="https://downbydown.com/?view=matchups">matchups</a> <span>›</span> <b>Week ' + str(N) + ' in review</b>')
    return out

SHELL = open('shell.html').read()
for N in (7, 8):
    W = week_data(N)
    json.dump(W, open(f'src/week-{N}.json', 'w'), separators=(',', ':'))
    open(f'week-{N}.html', 'w').write(SHELL.replace('%TITLE%', f'Week {N} in review · mock').replace('%ROOT%', render(W)))
    print(N, 'games', W['n'], 'final', W['fin'], 'swings', len(W['swings']), 'plays', len(W['plays']), 'teams', len(W['teams']), 'movers', W['movers'], 'pbp', W['pbp'][:4],
          'file KB', round(os.path.getsize(f'src/week-{N}.json') / 1024))
