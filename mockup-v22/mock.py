"""SCOPE §47 mock: a kicker page (Will Reichard) and the kicker pieces elsewhere on the site, in the live site's shell.
Run after kick_data.py: python3 mock.py <live player page html> <live game page html> <repo root>"""
import json, re, sys, html, math, datetime
PJ, PG, ROOT = sys.argv[1], sys.argv[2], sys.argv[3]
D = json.load(open('kickers.json')); T = json.load(open(f'{ROOT}/data/teams.json'))
KS = D['kickers']; Q = [k for k in KS if k['fga'] >= 8]; NQ = len(Q)
A, B = D['curve']
def p_make(d): return 1 / (1 + math.exp(-(A + B * (d - 40) / 10)))
e = html.escape
def ordn(n): return f"{n}{'th' if 11 <= n % 100 <= 13 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
def rank(key, k, low=False):
    vals = sorted((key(x) for x in Q), reverse=not low); v = key(k)
    r = vals.index(v) + 1; tie = vals.count(v) > 1
    return ('T-' if tie else '') + ordn(r), r
def pctl(key, k, low=False):
    vals = [key(x) for x in Q]; v = key(k)
    below = sum((x > v) if low else (x < v) for x in vals); eq = sum(x == v for x in vals)
    return round(100 * (below + eq / 2) / len(vals))
def logo(t, s=20): return f'<img class="dbd-logo" src="https://a.espncdn.com/combiner/i?img=/i/teamlogos/nfl/500/{t.lower()}.png&w={s*2}&h={s*2}" alt="{e(T[t]["name"])}" width="{s}" height="{s}" style="vertical-align:-4px">'
def head(k, s=24):
    ph = (k.get('photo') or '').replace('/upload/', f'/upload/f_auto,q_auto,w_{s*2},h_{s*2},c_fill,g_face/') if k.get('photo') else ''
    return f'<span class="dbd-head" style="width:{s}px;height:{s}px"><img src="{ph}" alt="" width="{s}" height="{s}" loading="lazy" referrerpolicy="no-referrer" style="background:{T[k["team"]]["c1"]}" onerror="this.remove()"></span>'
def clk(t): return t[1:] if t.startswith('0') and len(t) == 5 else t   # 08:38 -> 8:38, 00:03 -> 0:03
def slug(n): return re.sub(r'[^a-z0-9]+', '-', n.lower()).strip('-')
def pct(m, a): return f'{round(100 * m / a)}%' if a else '–'
fgpct = lambda x: x['fgm'] / x['fga']
f50 = lambda x: x['by'][3][0] + x['by'][4][0]
f50a = lambda x: x['by'][3][1] + x['by'][4][1]
fxg = lambda x: x['fx'] / max(1, x['g'])

R = next(k for k in KS if 'Reichard' in k['name']); TM = T[R['team']]; nick = TM['nick']; last = R['name'].split()[-1]
sch = json.load(open(f'{ROOT}/data/schedule.json'))

# ---------------------------------------------------------------- the kicker page
def tile(l, v, s, key, low=False, top=None):
    rk, r = rank(key, R, low); cls = ' top' if r <= 5 else ''
    tip = e(f'<b>{rk}</b> of {NQ} kickers with 8+ field goal tries')
    return f'<div class="dbd-tile"><div class="l">{l}</div><div class="v">{v}</div><div class="s">{s}</div><div class="dbd-trk{cls}" data-tip="{tip}" tabindex="0"><b>{rk}</b> of {NQ}</div></div>'
tiles = ''.join([
    tile('Field goals', f'{R["fgm"]}/{R["fga"]}', f'{pct(R["fgm"], R["fga"])} made', fgpct),
    tile('Over expected', f'{R["oe"]:+.1f}', 'makes, for his distances', lambda x: x['oe']),
    tile('From 50+', f'{f50(R)}/{f50a(R)}', f'long of {R["lng"]}', f50),
    tile('Avg try', f'{R["avgd"]}', 'yards, how far he is asked', lambda x: x['avgd']),
    tile('Extra points', f'{R["pat"][0]}/{R["pat"][1]}', pct(*R['pat']) + ' made', lambda x: (x['pat'][0] / x['pat'][1]) if x['pat'][1] else 0),
    tile('Points', f'{R["pts"]}', f'{R["pts"] / R["g"]:.1f} a game', lambda x: x['pts']),
    tile('Kickoffs', f'own {R["kstart"]:.0f}', 'where the other team starts', lambda x: x['kstart'] or 99, low=True),
    tile('Fantasy', f'{fxg(R):.1f}', 'points a game', fxg),
])
# the short version
by = R['by']; miss = [x for x in R['kicks'] if x['res'] != 'made']
inside = [x for x in R['kicks'] if x['d'] < 55]
late = [x for x in R['kicks'] if x['q'] in ('4', '5') and abs(x['sd']) <= 8]
oe_rk = rank(lambda x: x['oe'], R)[0]
short = [
    ('#dbd-good', 'How good', f'<b>{R["oe"]:+.1f}</b> makes over what an average kicker would make from his distances, <b>{oe_rk}</b> of {NQ}.'),
    ('#dbd-kicks', 'Range', f'Perfect inside 55 (<b>{sum(x["res"] == "made" for x in inside)} of {len(inside)}</b>). Both misses came from 57 and 59, one of them blocked.'),
    ('#dbd-kicks', 'Under pressure', f'In the fourth quarter or overtime of a one-score game: <b>{sum(x["res"] == "made" for x in late)} of {len(late)}</b>.'),
    ('#dbd-good', 'Fantasy', f'<b>{fxg(R):.1f}</b> points a game, <b>K{rank(fxg, R)[1]}</b> of {NQ}. Scoring is the same in every format.'),
    ('#dbd-weeks', 'This week', 'Bills at Vikings, Monday night, <b>indoors</b> at U.S. Bank Stadium: no wind to kick into.'),
]
shortH = '<section class="dbd-panel dbd-short"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b></span></div><ul>' + ''.join(f'<li><a href="{h}">{l}</a><span>{t}</span></li>' for h, l, t in short) + '</ul></section>'

# scorecard
def srow(n, sub, val, key, low=False, fmt=None):
    rk, r = rank(key, R, low); p = pctl(key, R, low)
    med = sorted(key(x) for x in Q)[NQ // 2]
    tip = e(f'<b>{R["name"]}</b><br>{n}: <b>{val}</b> · {ordn(p)} percentile · {rk} of {NQ}<br><span class="m">League median {fmt(med) if fmt else med} · {sub}</span>')
    cls = ' class="top"' if p >= 80 else ''
    g = ' hi' if p >= 80 else ' lo' if p <= 20 else ''
    return f'<tr{cls}><td class="n">{n}<small>{sub}</small></td><td class="v">{val}</td><td class="rk"><b>{rk}</b> of {NQ}</td><td class="b"><div class="bar"><i style="--p:{p}" data-tip="{tip}" tabindex="0" role="img" aria-label="{n} {val}, {ordn(p)} percentile"></i></div></td><td class="g{g}">{ordn(p)}</td></tr>'
pc = lambda v: f'{round(100 * v)}%'
sc = ''.join([
    srow('Field goal %', 'made of tried', pct(R['fgm'], R['fga']), fgpct, fmt=pc),
    srow('Over expected', 'makes vs the league curve', f'{R["oe"]:+.1f}', lambda x: x['oe'], fmt=lambda v: f'{v:+.1f}'),
    srow('Over expected / try', 'per attempt', f'{R["oe"] / R["fga"]:+.2f}', lambda x: x['oe'] / x['fga'], fmt=lambda v: f'{v:+.2f}'),
    srow('From 50+', 'made', str(f50(R)), f50),
    srow('Avg try', 'yards', str(R['avgd']), lambda x: x['avgd']),
    srow('Extra point %', 'made of tried', pct(*R['pat']), lambda x: (x['pat'][0] / x['pat'][1]) if x['pat'][1] else 0, fmt=pc),
    srow('Kickoffs', 'their start, lower is better', f'{R["kstart"]}', lambda x: x['kstart'] or 99, low=True, fmt=lambda v: f'their {v}'),
    srow('Points a game', 'FG and extra points', f'{R["pts"] / R["g"]:.1f}', lambda x: x['pts'] / x['g'], fmt=lambda v: f'{v:.1f}'),
])
score = f'<section class="dbd-panel dbd-score"><div class="dbd-ph"><span class="dbd-lab"><b>Scorecard</b> · among {NQ} kickers with 8 field goal tries+</span><span class="dbd-badge">League percentiles</span></div><div class="dbd-score2"><table class="dbd-stat"><thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead><tbody>{sc}</tbody></table></div></section>'
# by distance
LG = D['league']; rows = ''
for i, nm in enumerate(D['ranges']):
    m, a, x = R['by'][i]; lm, la = LG[i]
    diff = m - x
    rows += f'<tr><td class="n">{nm}</td><td class="v">{m}/{a}</td><td>{pct(m, a)}</td><td>{round(100 * x / a) if a else "–"}%</td><td>{pct(lm, la)}</td><td class="{"pos" if diff > 0.05 else "neg" if diff < -0.05 else ""}">{diff:+.1f}</td></tr>'
bydist = f'<section class="dbd-panel mk-new" data-mk="New"><div class="dbd-ph"><span class="dbd-lab"><b>By distance</b> · his makes against what an average kicker makes from the same spots</span></div><table class="dbd-stat kk-dist"><thead><tr><th>Yards</th><th>Made</th><th>His %</th><th>Expected</th><th>League</th><th>Over exp.</th></tr></thead><tbody>{rows}</tbody></table><p class="dbd-note">Expected comes from every field goal try in 2023–2025 (3,413 kicks): the chance an average kicker makes it from that distance. A block counts as a miss.</p></section>'

# every kick: the field chart
U = 12; XE = 960; XG = XE - 10 * U   # end line (posts) and goal line
def xl(L): return XG - L * U         # L yards from the goal line
wks = sorted({g['wk'] for g in R['games']})
allw = list(range(1, 9)); bye = [w for w in allw if w not in wks]
RH = 40; top = 26; H = top + RH * len(allw) + 30
svg = [f'<svg class="kk-field" viewBox="0 0 1056 {H}" role="img" aria-label="Every field goal try {R["name"]} has made or missed this season, by week and distance">']
svg.append(f'<rect x="0" y="0" width="{XE}" height="{H}" fill="#2c4a35"/>')
svg.append(f'<rect x="{XG}" y="0" width="{10 * U}" height="{H}" fill="{TM["c1"]}" opacity=".9"/>')
svg.append(f'<text x="{(XG + XE) / 2}" y="{H / 2}" fill="#fff" opacity=".85" font-family="Tourney,sans-serif" font-weight="800" font-size="30" text-anchor="middle" dominant-baseline="middle" transform="rotate(90 {(XG + XE) / 2} {H / 2})">{e(nick.upper())}</text>')
for L in range(0, 66, 5):
    x = xl(L); svg.append(f'<line x1="{x}" x2="{x}" y1="0" y2="{H}" stroke="#fff" stroke-opacity="{.55 if L % 10 == 0 else .18}" stroke-width="{2 if L == 0 else 1}"/>')
    if L % 10 == 0 and 0 < L <= 60:
        n = L if L <= 50 else 100 - L
        svg.append(f'<text x="{x}" y="{H - 10}" fill="#fff" fill-opacity=".55" font-family="Tourney,sans-serif" font-weight="800" font-size="18" text-anchor="middle">{n}</text>')
# the uprights, seen from above: a bar on the end line
svg.append(f'<line x1="{XE}" x2="{XE}" y1="{top - 8}" y2="{H - 30}" stroke="#ffd23f" stroke-width="5"/>')
for i, w in enumerate(allw):
    y = top + RH * i + RH / 2
    g = next((g for g in R['games'] if g['wk'] == w), None)
    lab = f'Wk {w}' if g else f'Wk {w}'
    svg.append(f'<text x="14" y="{y + 4}" fill="#fff" fill-opacity=".8" font-family="IBM Plex Mono,monospace" font-size="11">{lab}</text>')
    if not g:
        svg.append(f'<text x="70" y="{y + 4}" fill="#fff" fill-opacity=".5" font-family="IBM Plex Mono,monospace" font-size="11">bye</text>'); continue
    svg.append(f'<text x="70" y="{y + 4}" fill="#fff" fill-opacity=".8" font-family="IBM Plex Mono,monospace" font-size="11">{"vs" if g["home"] else "at"} {g["opp"]}</text>')
    svg.append(f'<line x1="{xl(60)}" x2="{XE}" y1="{y + RH / 2}" y2="{y + RH / 2}" stroke="#fff" stroke-opacity=".08"/>')
    ks = sorted([x for x in R['kicks'] if x['wk'] == w], key=lambda x: x['d'])
    # kicks within a dot's width of each other in one game stack up and down instead of overlapping
    pos = [xl(x['d'] - 10) for x in ks]; groups = []  # a try's distance is the line of scrimmage + 17, held 7 yards back: distance - 10 from the goal line
    for n_, cx in enumerate(pos):
        if groups and abs(pos[groups[-1][-1]] - cx) < 24: groups[-1].append(n_)
        else: groups.append([n_])
    off = {}
    for gr in groups:
        for m_, n_ in enumerate(gr): off[n_] = {1: [0], 2: [-11, 11], 3: [-13, 0, 13]}.get(len(gr), [0] * len(gr))[m_]
    for n_, x in enumerate(ks):
        cx = pos[n_]; cy = y + off[n_]
        res = x['res']; sit = 'tied' if x['sd'] == 0 else (f'up {x["sd"]}' if x['sd'] > 0 else f'down {-x["sd"]}')
        tip = e(f'<b>{x["d"]} yards · {"Good" if res == "made" else "Blocked" if res == "blocked" else "No good"}</b><br>Week {w} {"vs" if g["home"] else "at"} {T[g["opp"]]["nick"]} · Q{x["q"] if x["q"] != "5" else "OT"} {clk(x["t"])} · {sit}<br><span class="m">An average kicker makes it {round(100 * p_make(x["d"]))}% of the time · win chance {x["wpa"] * 100:+.0f}</span>')
        if res == 'made':
            svg.append(f'<g class="kk-k" tabindex="0" data-tip="{tip}"><circle cx="{cx}" cy="{cy}" r="12" fill="#fff"/><text x="{cx}" y="{cy + 4}" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="11" fill="#121417" text-anchor="middle">{x["d"]}</text></g>')
        else:
            svg.append(f'<g class="kk-k" tabindex="0" data-tip="{tip}"><circle cx="{cx}" cy="{cy}" r="11" fill="{"#cb2026" if res == "blocked" else "none"}" stroke="#ff5a5f" stroke-width="2.5"/><text x="{cx}" y="{cy + 4}" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="11" fill="#fff" text-anchor="middle">{x["d"]}</text></g>')
    svg.append(f'<text x="1048" y="{y + 4}" fill="#121417" font-family="IBM Plex Mono,monospace" font-size="11" text-anchor="end">XP {g["pm"]}/{g["pa"]}</text>')
svg.append('</svg>')
def kick_svg_v():
    W = 390; YU = 34; V = 8; YG = YU + 10 * V               # the uprights (end line) and the goal line
    yl = lambda L: YG + L * V
    H_ = yl(57) + 16; cols = allw; cw = (W - 46) / len(cols)
    o = [f'<svg class="kk-field kk-v" viewBox="0 0 {W} {H_}" role="img" aria-label="Every field goal try {e(R["name"])} has made or missed this season, by week and distance">',
         f'<rect x="0" y="{YU}" width="{W}" height="{H_ - YU}" fill="#2c4a35"/>', f'<rect x="0" y="{YU}" width="{W}" height="{10 * V}" fill="{TM["c1"]}" opacity=".9"/>',
         f'<text x="{W / 2}" y="{YU + 5 * V + 7}" fill="#fff" opacity=".85" font-family="Tourney,sans-serif" font-weight="800" font-size="22" text-anchor="middle">{e(nick.upper())}</text>',
         f'<line x1="0" x2="{W}" y1="{YU}" y2="{YU}" stroke="#ffd23f" stroke-width="4"/>']
    for L in range(0, 56, 5):
        o.append(f'<line x1="0" x2="{W}" y1="{yl(L)}" y2="{yl(L)}" stroke="#fff" stroke-opacity="{.55 if L % 10 == 0 else .18}" stroke-width="{2 if L == 0 else 1}"/>')
        if L % 10 == 0 and L: o.append(f'<text x="8" y="{yl(L) + 4}" fill="#fff" fill-opacity=".55" font-family="Tourney,sans-serif" font-weight="800" font-size="13">{L}</text>')
    for i, w in enumerate(cols):
        x = 46 + cw * i + cw / 2; g = next((g for g in R['games'] if g['wk'] == w), None)
        o.append(f'<text x="{x}" y="12" font-family="IBM Plex Mono,monospace" font-size="10" fill="#121417" text-anchor="middle">Wk {w}</text>')
        o.append(f'<text x="{x}" y="26" font-family="IBM Plex Mono,monospace" font-size="9" fill="#5d616a" text-anchor="middle">{(("vs " if g["home"] else "@") + g["opp"]) if g else "bye"}</text>')
        if not g: continue
        ks = sorted([x_ for x_ in R['kicks'] if x_['wk'] == w], key=lambda x_: x_['d'])
        pos = [yl(k_['d'] - 10) for k_ in ks]; groups = []
        for n_, cy in enumerate(pos):
            if groups and abs(pos[groups[-1][-1]] - cy) < 22: groups[-1].append(n_)
            else: groups.append([n_])
        off = {}
        for gr in groups:
            for m_, n_ in enumerate(gr): off[n_] = {1: [0], 2: [-10, 10], 3: [-11, 0, 11]}.get(len(gr), [0] * len(gr))[m_]
        for n_, k_ in enumerate(ks):
            cx = x + off[n_]; cy = pos[n_]; res = k_['res']
            if res == 'made': o.append(f'<circle cx="{cx}" cy="{cy}" r="10" fill="#fff"/><text x="{cx}" y="{cy + 3.5}" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="9.5" fill="#121417" text-anchor="middle">{k_["d"]}</text>')
            else: o.append(f'<circle cx="{cx}" cy="{cy}" r="9.5" fill="{"#cb2026" if res == "blocked" else "none"}" stroke="#ff5a5f" stroke-width="2.2"/><text x="{cx}" y="{cy + 3.5}" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="9.5" fill="#fff" text-anchor="middle">{k_["d"]}</text>')
    o.append('</svg>')
    return ''.join(o)
legend = '<div class="kk-legend"><span><i class="m"></i>Good</span><span><i class="x"></i>No good</span><span><i class="b"></i>Blocked</span><span>Each kick sits at the spot it was held, numbered by distance. The yellow bar is the uprights.</span></div>'
kread = f'<p class="kk-read"><b>{R["fgm"]} of {R["fga"]}</b>, and his tries average <b>{R["avgd"]} yards</b>, {ordn(rank(lambda x: x["avgd"], R)[1])}-longest in the league: the Vikings send him out from distances other teams punt from. The two that got away came from 57 and 59.</p>'
svg_h = ''.join(svg).replace('class="kk-field"', 'class="kk-field kk-h"', 1)
field = f'<section class="dbd-panel mk-new" data-mk="New" id="dbd-kicks"><div class="dbd-ph"><span class="dbd-lab"><b>Every kick</b> · {R["fga"]} field goal tries, Weeks 1–8</span><span class="dbd-badge">Hover a kick</span></div>{kread}<div class="kk-wrap">{svg_h}{kick_svg_v()}</div>{legend}</section>'
# the kicks that mattered
big = sorted(R['kicks'], key=lambda x: -abs(x['wpa']))[:5]; li = ''
for i, x in enumerate(big):
    g = next(g for g in R['games'] if g['wk'] == x['wk'])
    sit = 'tied' if x['sd'] == 0 else (f'up {x["sd"]}' if x['sd'] > 0 else f'down {-x["sd"]}')
    res = 'good' if x['res'] == 'made' else 'blocked' if x['res'] == 'blocked' else 'no good'
    w0 = round(100 * x['wp']); w1 = round(100 * (x['wp'] + x['wpa']))
    c = TM['c1'] if x['wpa'] > 0 else '#8a8f99'
    li += f'<li><b class="n">{i + 1}</b><span class="when">Q{x["q"] if x["q"] != "5" else "OT"} {clk(x["t"])}</span><span class="who" style="--c:{TM["c1"]}">{R["team"]}</span><span class="what">Week {x["wk"]} {"vs" if g["home"] else "at"} the {T[g["opp"]]["nick"]}: <b>{x["d"]} yards, {res}</b>, {sit}</span><span class="wp">{w0}% → <b>{w1}%</b></span><span class="bar"><i style="width:{min(100, abs(x["wpa"]) * 160):.1f}%;background:{c}"></i><small>{x["wpa"] * 100:+.0f}</small></span></li>'
mattered = f'<section class="dbd-panel mk-new" data-mk="New"><div class="dbd-ph"><span class="dbd-lab"><b>The kicks that mattered</b> · his tries that moved the Vikings\' win chance most</span></div><ol class="dbd-wpl">{li}</ol><p class="dbd-note">The change in the Vikings\' chance to win from the snap to the next play. A made kick can lower it: three points from the 21 is worth less than the touchdown chances the drive had.</p></section>'

# week by week
gl = ''
for g in R['games']:
    cond = 'indoors' if g['roof'] in ('dome', 'closed') else (f'{g["temp"]}°, wind {g["wind"]}' if g['temp'] else 'outdoors')
    gl += f'<tr><td class="nm">Wk {g["wk"]} <small>{"vs" if g["home"] else "at"} {g["opp"]}</small></td><td>{g["fgm"]}/{g["fga"]}</td><td>{g["lng"] or "–"}</td><td>{g["pm"]}/{g["pa"]}</td><td>{3 * g["fgm"] + g["pm"]}</td><td>{g["fx"]}</td><td class="kk-cond">{cond}</td></tr>'
weeks = f'<section class="dbd-panel mk-new" data-mk="New"><div class="dbd-ph"><span class="dbd-lab"><b>Game log</b></span><span class="dbd-badge">Fantasy: ESPN standard</span></div><table class="dbd-stat kk-log"><thead><tr><th>Game</th><th>FG</th><th>Long</th><th>XP</th><th>Pts</th><th>Fant.</th><th>Conditions</th></tr></thead><tbody>{gl}</tbody></table></section>'

# career
cr = dict(R['career']); cr['2026'] = dict(fgm=R['fgm'], fga=R['fga'], lng=R['lng'], f50=[f50(R), f50a(R)], pm=R['pat'][0], pa=R['pat'][1], g=R['g'], team=R['team'])
tot = {k: sum(v[k] for v in cr.values()) for k in ('fgm', 'fga', 'pm', 'pa', 'g')}
crow = ''.join(f'<tr><td class="nm">{y}{" <small>so far</small>" if y == "2026" else ""}</td><td>{v["g"]}</td><td>{v["fgm"]}/{v["fga"]}</td><td>{pct(v["fgm"], v["fga"])}</td><td>{v["lng"]}</td><td>{v["f50"][0]}/{v["f50"][1]}</td><td>{v["pm"]}/{v["pa"]}</td></tr>' for y, v in sorted(cr.items(), key=lambda kv: str(kv[0])))
best = max(((y, v) for y, v in cr.items() if y != '2026'), key=lambda kv: kv[1]['fgm'] / kv[1]['fga'])
career = f'''<section class="dbd-panel dbd-career mk-new" data-mk="Kicking columns"><div class="dbd-ph"><span class="dbd-lab"><b>Career</b> · regular season</span><span class="dbd-badge">{len(cr)} seasons</span></div><div class="dbd-cr-line"><div><b>{tot["g"]}</b><small>Games</small></div><div><b>{tot["fgm"]}/{tot["fga"]}</b><small>Field goals</small></div><div><b>{pct(tot["fgm"], tot["fga"])}</b><small>Made</small></div><div><b>{max(v["lng"] for v in cr.values())}</b><small>Long</small></div><div><b>{tot["pm"]}/{tot["pa"]}</b><small>Extra points</small></div><span class="span">2024 to 2026 · {len(cr)} seasons · 1 team</span></div><p class="dbd-cr-say">His best year was <b>{best[0]}</b>, {best[1]["fgm"]} of {best[1]["fga"]} ({pct(best[1]["fgm"], best[1]["fga"])}) with a long of {best[1]["lng"]}. This season he is {R["fgm"]} of {R["fga"]}, and perfect on extra points for a third straight year.</p><table class="dbd-stat kk-car"><thead><tr><th>Season</th><th>G</th><th>FG</th><th>%</th><th>Long</th><th>50+</th><th>XP</th></tr></thead><tbody>{crow}</tbody></table></section>'''

age = (datetime.date(2026, 11, 3) - datetime.date.fromisoformat(R['birth'])).days // 365
ph = R['photo'].replace('/upload/', '/upload/f_auto,q_auto,h_380,c_fit/')
title = f'''<div class="dbd-ttitle"><div class="t-stagger"><p class="dbd-kicker t-stagger-line t-stagger-line--1">2026 season · K · #{R["num"]} · 3rd season</p><h1 class="t-stagger-line t-stagger-line--2"><span>Will <em>Reichard</em></span></h1><p class="dbd-sub t-stagger-line t-stagger-line--3">Age {age} · 6&#039;1&quot; · 185 lb · {R["college"]} · Pick {R["draft"][0]} overall ({R["draft"][1]}). The Vikings' kicker on the depth chart, {R["g"]} games.</p><div class="dbd-pills"><span class="dbd-pill good">No injury listing</span><span class="dbd-pill">K</span><a class="dbd-pill" href="#">Vikings players</a></div></div><figure class="dbd-herophoto" aria-hidden="true"><img src="{ph}" alt="" height="190" referrerpolicy="no-referrer"></figure><div class="dbd-hdr dbd-hcard"><a class="dbd-pn-link" href="#">{logo("MIN", 40)}<span class="t"><b>Vikings special teams</b><small>{R["fga"]} field goal tries, {R["ko"][0]} kickoffs</small></span></a><div class="acts"><a class="dbd-cmp-btn" href="#">Compare</a><a class="dbd-share" href="#">Share card</a></div><button class="dbd-mtbtn" type="button" aria-pressed="false"><span class="off">+ My team</span></button></div></div>'''
top = f'<div class="dbd-top"><div class="dbd-crumb"><a href="#" class="dbd-brand">DOWN X DOWN</a> <span>›</span> <a href="#">players</a> <span>›</span> <a href="#">Vikings</a> <span>›</span> <b>Will Reichard</b></div><div class="dbd-r"><span class="dbd-upd">Updated Nov 3, 5:29 AM ET</span></div></div>'
jump = '<nav class="dbd-jump" aria-label="On this page"><a href="#dbd-good">How good is he</a><a href="#dbd-kicks">Every kick</a><a href="#dbd-weeks">Week by week</a><a href="#dbd-career">Career</a></nav>'
page = (f'<div class="dbd dbd-view-player">{top}<main class="dbd-content dbd-player" style="--dbd-team:{TM["c1"]}">{title}<div class="dbd-tiles mk-new" data-mk="Kicker tiles">{tiles}</div>{shortH}{jump}'
        f'<section class="dbd-band" id="dbd-good"><h2 class="dbd-bandh">How good is he<span>his kicks against the league, distance for distance</span></h2>{score}{bydist}</section>'
        f'<section class="dbd-band"><h2 class="dbd-bandh">Every kick<span>where each try came from, and the ones that swung a game</span></h2>{field}{mattered}</section>'
        f'<section class="dbd-band" id="dbd-weeks"><h2 class="dbd-bandh">Week by week<span>the game log, with the conditions he kicked in</span></h2>{weeks}</section>'
        f'<section class="dbd-band" id="dbd-career"><h2 class="dbd-bandh">Career<span>three seasons, one team</span></h2>{career}</section></main></div>')

# ---------------------------------------------------------------- elsewhere on the site
pg = open(PG).read()
i = pg.find('<ol class="dbd-wpl"'); j = pg.find('</ol>', i) + 5; now_ol = pg[i:j]
wp = json.loads(open('w8wp.json').read())['games']['2026_08_MIN_DET']
tops = wp['top'] + [wp['tp']]
def swap_who(ol):
    lis = re.findall(r'<li[^>]*>.*?</li>', ol, flags=re.S); out = []
    for li_, t in zip(lis, tops):
        ball = t[2]; ben = re.search(r'class="who" style="--c:(#[0-9A-Fa-f]+)">([A-Z]+)<', li_)
        bt = ben.group(2); bc = ben.group(1)
        li_ = re.sub(r'<span class="who" style="--c:#[0-9A-Fa-f]+">[A-Z]+</span>', f'<span class="who" style="--c:{T[ball]["c1"]}">{ball}</span>', li_)
        li_ = re.sub(r'<small>([+-]\d+)</small>', lambda m_: f'<small>{m_.group(1)} <em class="kk-for" style="--c:{bc}">{bt}</em></small>', li_, count=1)
        if 'J.Bates' in li_: li_ = li_.replace('J.Bates', '<a href="#">J.Bates</a>')
        out.append(li_)
    return '<ol class="dbd-wpl kk-wpl">' + ''.join(out) + '</ol>'
swing = (f'<section class="dbd-panel mk-new" data-mk="Swing tag: now"><div class="dbd-ph"><span class="dbd-lab"><b>The plays that swung it</b> · Vikings at Lions, live today</span></div>{now_ol}<p class="dbd-note">The tag is the team the play <b>helped</b>, so J.Bates\' miss reads as a Vikings play.</p></section>'
         f'<section class="dbd-panel mk-new" data-mk="Swing tag: proposed"><div class="dbd-ph"><span class="dbd-lab"><b>The plays that swung it</b> · proposed</span></div>{swap_who(now_ol)}<p class="dbd-note">The tag becomes the team <b>with the ball</b>. The bar names the team the play helped, in that team\'s color, so a miss reads as a Lions kick that helped the Vikings. Kicker names in the play link to their pages.</p></section>')
def kline(k, gid):
    g = next(g for g in k['games'] if g['gid'] == gid)
    ms = [x for x in k['kicks'] if x['gid'] == gid and x['res'] != 'made']
    miss = (' (' + ', '.join(f'{x["d"]} {"blocked" if x["res"] == "blocked" else "no good"}' for x in ms) + ')') if ms else ''
    return f'<a href="#">{k["short"]}</a> {g["fgm"]}/{g["fga"]} FG{miss}, {g["pm"]}/{g["pa"]} XP, {3 * g["fgm"] + g["pm"]} pts'
box = ('<section class="dbd-panel mk-new" data-mk="Box score"><div class="dbd-ph"><span class="dbd-lab"><b>Box score kicking line</b> · Vikings at Lions</span></div>'
       '<div class="kk-box"><p class="dbd-bxdk"><b>Now</b> · <b>Kicking</b> W.Reichard 0/1 FG</p>'
       '<p class="dbd-bxdk"><b>Proposed</b> · <b>Kicking</b> ' + ' · '.join(kline(k, '2026_08_MIN_DET') for k in KS if any(g['gid'] == '2026_08_MIN_DET' for g in k['games'])) + '</p></div>'
       '<p class="dbd-note">Every kicker in the game, linked, with the misses and their distances.</p></section>')
def board(t, sub, key, fmt, low=False, n=5, pool=None):
    pool = pool or Q
    li_ = ''
    for r_, k in enumerate(sorted(pool, key=key, reverse=not low)[:n], 1):
        li_ += f'<li><span class="rk">{r_}</span><span class="who">{head(k)}<span class="nm"><a href="#">{e(k["name"])}</a><small>{k["team"]} · K</small></span></span><b class="v">{fmt(k)}</b></li>'
    return f'<section class="dbd-panel ld-card"><div class="ld-h"><b>{t}</b><small>{sub}</small></div><ol class="ld-list">{li_}</ol></section>'
boards = ''.join([
    board('Over expected', f'makes against the league curve · {NQ} kickers, 8 tries+', lambda k: k['oe'], lambda k: f'{k["oe"]:+.1f}'),
    board('Field goal %', 'made of tried', lambda k: (fgpct(k), k['fga']), lambda k: f'{pct(k["fgm"], k["fga"])} <small>{k["fgm"]}/{k["fga"]}</small>'),
    board('From 50+', 'made, then tries', lambda k: (f50(k), -f50a(k)), lambda k: f'{f50(k)}/{f50a(k)}'),
    board('Points', 'field goals and extra points', lambda k: k['pts'], lambda k: str(k['pts'])),
    board('Kickoffs', 'the other team\'s average start, lower is better', lambda k: k['kstart'] or 99, lambda k: f'own {k["kstart"]}', low=True),
    board('Fantasy points a game · K', 'ESPN standard, same in every format', fxg, lambda k: f'{fxg(k):.1f}'),
])
leaders = f'<section class="dbd-band mk-new" data-mk="Leaders: Kickers tab"><h2 class="dbd-bandh">Leaders · Kickers<span>a new tab after Tight ends</span></h2><div class="ld-grid">{boards}</div></section>'
# defenses that give kickers the most
allow = {}
for k in KS:
    for g in k['games']:
        a = allow.setdefault(g['opp'], [0, set()]); a[0] += g['fx']; a[1].add(g['gid'])
soft = sorted(((t, v[0] / len(v[1])) for t, v in allow.items()), key=lambda x: -x[1])[:5]
softH = ''.join(f'<li><span class="rk">{i + 1}</span><span class="who">{logo(t, 24)}<span class="nm"><a href="#">{T[t]["nick"]}</a><small>{T[t]["div"]}</small></span></span><b class="v">{v:.1f}</b></li>' for i, (t, v) in enumerate(soft))
fant = (f'<section class="dbd-band mk-new" data-mk="Fantasy"><h2 class="dbd-bandh">Fantasy<span>kickers join the fantasy lens</span></h2><div class="ld-grid">'
        f'<section class="dbd-panel ld-card"><div class="ld-h"><b>Kicker points allowed a game</b><small>the defenses kickers score most against</small></div><ol class="ld-list">{softH}</ol></section>'
        f'<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where kickers show up</b></span></div><ul class="kk-where"><li><b>My team</b> takes a kicker: his points, the week\'s matchup and the conditions.</li><li>The <b>Fantasy</b> boards on Leaders and the player Fantasy panel score kickers.</li><li>A defense\'s fantasy line adds <b>points allowed to kickers</b>.</li><li>The weekly post\'s top scorers can include a kicker.</li></ul></section></div></section>')
other = f'<div class="dbd dbd-view-leaders"><div class="dbd-top"><div class="dbd-crumb"><a href="#" class="dbd-brand">DOWN X DOWN</a> <span>›</span> <b>Kickers, elsewhere on the site</b></div></div><main class="dbd-content">{swing}{box}{leaders}{fant}</main></div>'

# ---------------------------------------------------------------- write both pages into the live shell
shell = open(PJ).read()
s0 = shell.find('<div class="dbd dbd-view-player">')
depth = 0; pos = s0
for m in re.finditer(r'<(/?)div\b[^>]*>', shell[s0:]):
    depth += -1 if m.group(1) else 1
    if depth == 0: s1 = s0 + m.end(); break
shell = re.sub(r'<script[^>]*>\s*window\.DBD_[^<]*</script>', '', shell)
css = open('kk.css').read()
bar = lambda t: f'<div class="mk-bar"><b>Mock · kickers (SCOPE §47)</b><span>{t}</span><label><input type="checkbox" id="mk-toggle" checked onchange="document.body.classList.toggle(\'mk-off\',!this.checked)"> Show outlines</label></div>'
for fn, root, t, ttl in (('kicker-reichard.html', page, 'Will Reichard\'s page, built from this season\'s nflverse play-by-play through Week 8 in the live site\'s shell. Every number is real.', 'Will Reichard · mock'),
                         ('elsewhere.html', other, 'Kickers on the game page, Leaders and fantasy. Real Week 8 data; the "now" rows are copied from the live game page.', 'Kickers elsewhere · mock')):
    out = shell[:s0] + bar(t) + root + shell[s1:]
    out = out.replace('</head>', f'<style>{css}</style></head>', 1)
    out = re.sub(r'<title>.*?</title>', f'<title>{ttl}</title>', out, count=1, flags=re.S)
    open(fn, 'w').write(out)
    print(fn, len(out))
