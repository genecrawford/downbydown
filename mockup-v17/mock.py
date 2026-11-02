"""Mock for SCOPE §42: compare, finished. The live 1.17.0 compare page for Justin Jefferson, Ja'Marr Chase and Puka Nacua with the new
sections added and outlined: this week, playstyle side by side, where the EPA comes from, the shape of their seasons, and careers. Every
number is the site's published data (../data). Writes compare.html. python3 -I mock.py"""
import json, re, html
esc = html.escape
D = '../data'
T = json.load(open(f'{D}/teams.json')); S = json.load(open(f'{D}/schedule.json')); LG = json.load(open(f'{D}/players/league.json'))['groups']['wr']
FX = json.load(open(f'{D}/fantasy.json'))
ROOT = open('src/root.html').read()
IDS = [('00-0036322', 'MIN'), ('00-0036900', 'CIN'), ('00-0039075', 'LA')]
COL = ['#161e36', '#cb2026', '#d99a06']
P = []
for pid, t in IDS:
    p = next(x for x in json.load(open(f'{D}/players/{t}.json'))['players'] if x['id'] == pid)
    cr = json.load(open(f'{D}/players/career/{t}.json'))['players'].get(pid, [])
    P.append(dict(p=p, t=t, cr=cr))
first = lambda p: p['name'].split(' ')[0]
def ordn(n): n = int(n); return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
def pctile(pool, v, low=False):
    if v is None or not pool: return None
    b = sum(1 for x in pool if x < v); e = sum(1 for x in pool if x == v); pc = (b + e / 2) / len(pool)
    return round((1 - pc if low else pc) * 100)
pct = lambda v: '–' if v is None else f'{round(v * 100)}%'
sgn = lambda v, k=1: '–' if v is None else ('+' if v > 0 else ('−' if v < 0 else '')) + f'{abs(v):.{k}f}'
def mk(label): return f' mk-new" data-mk="{esc(label)}'
legend = '<div class="dbd-cleg">' + ''.join(f'<span><i style="background:{COL[i]}"></i>{esc(x["p"]["name"])}</span>' for i, x in enumerate(P)) + '</div>'

# ---------------------------------------------------------------- this week: each player's next game and the defense at his position
CW = S['current_week']
def next_game(t):
    for w in sorted(S['weeks'], key=int):
        if int(w) < CW: continue
        for g in S['weeks'][w]:
            if t in (g['away'], g['home']) and g['status'] != 'final':
                return int(w), g
    return None, None
cells = ''
for i, x in enumerate(P):
    w, g = next_game(x['t'])
    if not g: cells += f'<div class="cw-nx" style="--c:{COL[i]}"><b>No game left</b></div>'; continue
    home = g['home'] == x['t']; o = g['away'] if home else g['home']
    rk = FX['rank'][o]['WR'][1]; n = len(FX['rank']); tier = 'soft' if rk <= 8 else ('tough' if rk >= 25 else '')
    h_, m_ = map(int, g['time'].split(':')); kt = f'{(h_ - 1) % 12 + 1}:{m_:02d} {"PM" if h_ >= 12 else "AM"} ET'
    word = 'most' if rk == 1 else ('fewest' if rk == n else (f'{ordn(rk)}-most' if rk <= n / 2 else f'{ordn(n + 1 - rk)}-fewest'))
    cells += (f'<div class="cw-nx" style="--c:{COL[i]}"><small>Week {w} · {esc(g["day"])} {kt}</small><b>{"vs" if home else "at"} {T[o]["nick"]}</b>'
              f'<span class="cw-def {tier}">{T[o]["nick"]} allow the {word} WR points</span></div>')
bandWeek = (f'<section class="dbd-panel{mk("New · this week, side by side")}"><div class="dbd-ph"><span class="dbd-lab"><b>This week</b> · each one\'s next game and the defense at his position</span><span class="dbd-badge">Half PPR</span></div>'
            f'<div class="dbd-cslots cw-next">{cells}</div></section>')

# ---------------------------------------------------------------- playstyle side by side
FPD = [('deep', 'Deep share', 'targets 20+ yards downfield', 'Downfield', 'Rarely deep'), ('scr', 'Screens', 'share of his targets', 'Screen game', 'No screens'),
       ('mid', 'Middle of the field', 'targets between the numbers', 'Works the middle', 'Stays outside'), ('yacs', 'After the catch', 'share of his yards after the catch', 'Yards after the catch', 'Wins before the catch'),
       ('d3', 'Third-down share', 'of team targets on 3rd down', 'Third-down guy', 'Not the third-down option'), ('rzs', 'Red-zone share', 'of team targets inside the 20', 'Red-zone guy', 'Not the red-zone option'),
       ('mot', 'Motion', 'targets with pre-snap motion', 'Motion man', 'Static'), ('pa', 'Play action', 'targets off play action', 'Play-action target', 'Dropback target')]
rows = ''; tags = {i: [] for i in range(3)}
for k, lab, sub, hi, lo in FPD:
    pool = LG['fp'][k]; med = sorted(pool)[len(pool) // 2]
    vals = [x['p']['fp'].get(k) for x in P]; pcs = [pctile(pool, v) for v in vals]
    marks = ''.join(f'<i style="--p:{pc};--c:{COL[i]}" data-slot="{i}" data-tip="{esc("<b>" + P[i]["p"]["name"] + "</b><br>" + lab + ": <b>" + pct(vals[i]) + "</b> · " + ordn(pc) + " percentile<br><span class=m>League median " + pct(med) + "</span>")}" tabindex="0" role="img"></i>' for i, pc in enumerate(pcs) if pc is not None)
    cv = ''.join(f'<div class="cv" style="--c:{COL[i]}" data-slot="{i}">{pct(v)}<small>{ordn(pcs[i]) if pcs[i] is not None else "–"}</small></div>' for i, v in enumerate(vals))
    rows += f'<div class="row"><div class="l">{lab}<small>{sub}</small></div><div class="bar"><span class="cw-med" style="--p:{pctile(pool, med)}"></span>{marks}</div>{cv}</div>'
    for i, pc in enumerate(pcs):
        if pc is not None and (pc >= 75 or pc <= 25): tags[i].append((abs(pc - 50), hi if pc >= 75 else lo, pc >= 75))
tagrow = ''
for i, x in enumerate(P):
    tg = sorted(tags[i], reverse=True)[:3]
    tagrow += f'<div class="cw-tags" style="--c:{COL[i]}"><b>{esc(first(x["p"]))}</b>' + ''.join(f'<span class="dbd-fp-tag {"hi" if up else "lo"}"><i aria-hidden="true">{"▲" if up else "▼"}</i>{esc(w)}</span>' for _, w, up in tg) + ('' if tg else '<span class="m">a typical profile</span>') + '</div>'
bandFP = (f'<section class="dbd-panel{mk("New · playstyle, side by side")}"><div class="dbd-ph"><span class="dbd-lab"><b>Playstyle</b> · how each is used, against the league\'s wide receivers</span><span class="dbd-badge">among {LG["n"]} wide receivers</span></div>'
          f'{legend}<div class="cw-tagrow">{tagrow}</div><div class="dbd-cpct">{rows}</div>'
          '<p class="dbd-note">Every trait is a share of his own targets, so volume does not move it. Each bar is the league\'s qualifying wide receivers; the markers are the three players and the thin line is the median. Styles are not grades: a high or low share describes the role, not how well he plays it. The tags are each player\'s traits furthest from the middle.</p></section>')

# ---------------------------------------------------------------- where the EPA comes from, side by side
PART = {'air': ('Air yards', 'on catches', '#161e36'), 'yac': ('After the catch', '', '#1e7f4a'), 'inc': ('Incompletions', 'targets not caught', '#9aa0aa'), 'int': ('Interceptions', 'when targeted', '#b4261f'), 'two': ('Two-point tries', 'not in the rates', '#c8b98a')}
secs = []
for x in P:
    d = {}
    for q in x['p']['ep']['sec'][0][3]: d[q[0]] = (q[1], q[2], q[3] if len(q) > 3 else None)
    secs.append(d)
tots = [x['p']['ep']['sec'][0][1] for x in P]
span = max(max(sum(v[0] for v in s.values() if v[0] > 0), -sum(v[0] for v in s.values() if v[0] < 0)) for s in secs) or 1
bars = ''
for i, s in enumerate(secs):
    pos = [(k, v[0]) for k, v in s.items() if v[0] > 0]; neg = [(k, v[0]) for k, v in s.items() if v[0] < 0]
    segp = ''.join(f'<i style="width:{v / span * 100:.2f}%;background:{PART[k][2]}" data-tip="{esc("<b>" + P[i]["p"]["name"] + "</b><br>" + PART[k][0] + ": <b>" + sgn(v) + "</b> EPA")}" tabindex="0"></i>' for k, v in pos)
    segn = ''.join(f'<i style="width:{-v / span * 100:.2f}%;background:{PART[k][2]}" data-tip="{esc("<b>" + P[i]["p"]["name"] + "</b><br>" + PART[k][0] + ": <b>" + sgn(v) + "</b> EPA")}" tabindex="0"></i>' for k, v in neg)
    bars += (f'<div class="cw-eprow" style="--c:{COL[i]}"><div class="nm"><i style="background:{COL[i]}"></i>{esc(P[i]["p"]["name"])}<b>{sgn(tots[i])}</b></div>'
             f'<div class="cw-epbar"><span class="neg">{segn}</span><span class="z"></span><span class="pos">{segp}</span></div></div>')
keys = [k for k in ('air', 'yac', 'inc', 'int') if any(k in s for s in secs)]
th = '<th>Part</th>' + ''.join(f'<th style="--c:{COL[i]}"><i class="sw" style="background:{COL[i]}"></i>{esc(first(x["p"]))}</th>' for i, x in enumerate(P))
tb = ''
for k in keys:
    vals = [s.get(k, (None, None, None)) for s in secs]; best = max(range(3), key=lambda i: vals[i][1] if vals[i][1] is not None else -9)
    tb += f'<tr><td class="n">{PART[k][0]}<small>{PART[k][1]}</small></td>' + ''.join(f'<td{" class=b" if i == best else ""}>{sgn(v[0])}<small>{sgn(v[1], 2)} a target · {ordn(v[2]) if v[2] is not None else "–"}</small></td>' for i, v in enumerate(vals)) + '</tr>'
tb += '<tr class="tot"><td class="n">Total</td>' + ''.join(f'<td>{sgn(t_)}<small>{x["p"]["ep"]["u"]} targets</small></td>' for x, t_ in zip(P, tots)) + '</tr>'
leg = '<div class="dbd-legend">' + ''.join(f'<span><i class="sq" style="background:{PART[k][2]}"></i>{PART[k][0]}</span>' for k in keys) + '</div>'
best_air = max(range(3), key=lambda i: secs[i]['air'][0]); best_yac = max(range(3), key=lambda i: secs[i]['yac'][0]); worst_inc = min(range(3), key=lambda i: secs[i]['inc'][0])
say = (f'<b>{esc(P[best_air]["p"]["name"])}</b> gets the most from the ball in the air ({sgn(secs[best_air]["air"][0])} EPA), <b>{esc(P[best_yac]["p"]["name"])}</b> the most after the catch ({sgn(secs[best_yac]["yac"][0])}), '
       + (f'and {first(P[worst_inc]["p"])} also gives the most back' if worst_inc in (best_air, best_yac) else f'and <b>{esc(P[worst_inc]["p"]["name"])}</b> gives the most back') + f' on targets he does not catch ({sgn(secs[worst_inc]["inc"][0])}).')
bandEP = (f'<section class="dbd-panel{mk("New · where the EPA comes from, side by side")}"><div class="dbd-ph"><span class="dbd-lab"><b>Where the EPA comes from</b> · the season split by what happened on his targets</span><span class="dbd-badge">Receiving EPA</span></div>'
          f'<p class="dbd-cx-say">{say}</p><div class="cw-ep">{bars}</div>{leg}<div class="dbd-tw"><table class="dbd-stat cw-ept"><thead><tr>{th}</tr></thead><tbody>{tb}</tbody></table></div>'
          '<p class="dbd-note">Every target is split at the catch: air yards and yards after the catch for completions (nflverse\'s air EPA), the cost of the ones that fell incomplete or were picked. Bars share one scale, gains right of the line and losses left. The rate is per target, with its percentile among the league\'s qualifying wide receivers; the best rate in each row is green.</p></section>')


def chart(series, ylo, yhi, xs, xlabels, ylabels, h, band=None, med=None):
    """Lines in a stretched SVG (non-scaling strokes); dots, labels and the band edges as HTML placed in percent, so text never scales."""
    X = lambda x: (x - xs[0]) / max(1e-9, xs[1] - xs[0]) * 100; Y = lambda v: (yhi - v) / (yhi - ylo) * 100
    svg = ''
    if band: svg += f'<rect x="0" y="{Y(band[1]):.2f}" width="100" height="{Y(band[0]) - Y(band[1]):.2f}" fill="rgba(22,30,54,.08)"/>'
    if med is not None: svg += f'<line x1="0" x2="100" y1="{Y(med):.2f}" y2="{Y(med):.2f}" stroke="var(--dbd-line-2)" stroke-dasharray="3 3" vector-effect="non-scaling-stroke"/>'
    for v, _ in ylabels: svg += f'<line x1="0" x2="100" y1="{Y(v):.2f}" y2="{Y(v):.2f}" stroke="var(--dbd-line)" vector-effect="non-scaling-stroke"/>' if band is None else ''
    dots = ''
    for c, pts, faint in series:
        line = [(X(x), Y(y)) for x, y, *_ in pts if y is not None]
        svg += f'<polyline points="{" ".join(f"{a:.2f},{b:.2f}" for a, b in line)}" fill="none" stroke="{c}" stroke-width="2.2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>'
        for x, y, tip, kind in pts:
            if y is None: continue
            dots += f'<i class="d {kind}" style="left:{X(x):.2f}%;top:{Y(y):.2f}%;--c:{c}"' + (f' data-tip="{tip}" tabindex="0"' if tip else '') + '></i>'
        for x, y in faint:
            if y is not None: dots += f'<i class="d f" style="left:{X(x):.2f}%;top:{max(0, min(100, Y(y))):.2f}%;--c:{c}"></i>'
    yl = ''.join(f'<span class="yl" style="top:{Y(v):.2f}%">{t}</span>' for v, t in ylabels)
    xl = ''.join(f'<span style="left:{X(x):.2f}%">{t}</span>' for x, t in xlabels)
    return f'<div class="cw-ch"><div class="pl" style="height:{h}px"><svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">{svg}</svg>{dots}{yl}</div><div class="xl">{xl}</div></div>'

# ---------------------------------------------------------------- the shape of their seasons: trailing-three lines over the league's middle half
TD = [('tgt', 'Targets', ['targets'], None, lambda v: f'{v:.1f}'), ('yds', 'Rec yards', ['receiving_yards'], None, lambda v: f'{v:.0f}'),
      ('epat', 'EPA / target', ['receiving_epa'], ['targets'], lambda v: sgn(v, 2)), ('adot', 'aDOT', ['receiving_air_yards'], ['targets'], lambda v: f'{v:.1f}')]
NW = max(int(w['week']) for x in P for w in x['p']['weeks'])
def series(x, num, den):
    wk = {int(w['week']): w for w in x['p']['weeks'] if (w.get('targets') or 0) or (w.get('receptions') or 0) or True}
    pts = []; played = sorted(wk)
    for i, w in enumerate(played):
        win = played[max(0, i - 2): i + 1]
        n = sum(sum(float(wk[q].get(f) or 0) for f in num) for q in win); d = sum(sum(float(wk[q].get(f) or 0) for f in den) for q in win) if den else len(win)
        g_n = sum(float(wk[w].get(f) or 0) for f in num); g_d = sum(float(wk[w].get(f) or 0) for f in den) if den else 1
        pts.append((w, n / d if d else None, g_n / g_d if g_d else None))
    return pts
charts = ''
for key, lab, num, den, f in TD:
    q = LG['game'][key]; S_ = [series(x, num, den) for x in P]
    vals = [v for s in S_ for _, a, b in s for v in (a, b) if v is not None] + [q['p25'], q['p75']]
    lo, hi = min(vals), max(vals); pad = (hi - lo) * .08 or 1; lo -= pad; hi += pad
    ser = [(COL[i], [(w, a_, None, 'last' if j == len(S_[i]) - 1 else 'pt') for j, (w, a_, _) in enumerate(S_[i])], [(w, b_) for w, _, b_ in S_[i]]) for i in range(len(P))]
    ch = chart(ser, lo, hi, (1, NW), [(w, str(w)) for w in range(1, NW + 1)], [(q['p25'], f(q['p25'])), (q['p75'], f(q['p75']))], 150, band=(q['p25'], q['p75']), med=q['p50'])
    now = ''.join(f'<span style="--c:{COL[i]}"><i></i>{esc(first(x["p"]))} <b>{f(S_[i][-1][1])}</b></span>' for i, x in enumerate(P) if S_[i] and S_[i][-1][1] is not None)
    charts += f'<div class="cw-tr"><h4>{lab}<small>last three games</small></h4>{ch}<div class="cw-now">{now}</div></div>'
bandTR = (f'<section class="dbd-panel{mk("New · the shape of their seasons, overlaid")}"><div class="dbd-ph"><span class="dbd-lab"><b>The shape of their seasons</b> · each one\'s last three games, week by week</span><span class="dbd-badge">Weeks 1 to {NW}</span></div>'
          f'{legend}<div class="cw-trs">{charts}</div>'
          '<p class="dbd-note">Each line is a player\'s trailing three games (rates are pooled over the three, not averaged); the faint dots are single games. The shaded band is the league\'s middle half of single games at the position, the dashed line its median. A line that climbs out of the band is a role getting bigger.</p></section>')

# ---------------------------------------------------------------- careers, overlaid
seasons = sorted({r['season'] for x in P for r in x['cr']})
cur = S['season']
def ypg(r): return r['receiving_yards'] / r['g'] if r['g'] else None
vals = [ypg(r) for x in P for r in x['cr'] if ypg(r) is not None]; hi = max(vals) * 1.1; lo = 0
ser = []
for i, x in enumerate(P):
    pts = []
    for r in x['cr']:
        if ypg(r) is None: continue
        tip = esc(f'<b>{x["p"]["name"]}</b> · {r["season"]}<br>{r["receiving_yards"]} yards in {r["g"]} games, <b>{ypg(r):.1f}</b> a game<br>{r["receptions"]} catches, {r["receiving_tds"]} TD')
        pts.append((seasons.index(r['season']), ypg(r), tip, 'cur' if r['season'] == cur else 'pt'))
    ser.append((COL[i], pts, []))
crch = chart(ser, 0, hi, (0, len(seasons) - 1), [(i, str(sn) + ('*' if sn == cur else '')) for i, sn in enumerate(seasons)], [(v, str(v)) for v in (25, 50, 75, 100) if v < hi], 210)
crow = ''
for i, x in enumerate(P):
    cr = x['cr']; g = sum(r['g'] for r in cr); yd = sum(r['receiving_yards'] for r in cr); td = sum(r['receiving_tds'] for r in cr); rc = sum(r['receptions'] for r in cr)
    past = [r for r in cr if r['season'] != cur]; best = max(past or cr, key=lambda r: r['receiving_yards'])
    now = next((r for r in cr if r['season'] == cur), None)
    crow += (f'<tr><td class="n"><i class="sw" style="background:{COL[i]}"></i>{esc(x["p"]["name"])}<small>{cr[0]["season"]} to {cr[-1]["season"]} · {len(cr)} seasons</small></td><td>{g}</td><td class="dbd-t3">{rc}</td><td>{yd:,}</td><td>{td}</td><td>{yd / g:.1f}</td>'
             f'<td class="dbd-t2">{best["season"]}: {best["receiving_yards"]:,}<small>{ypg(best):.1f} a game</small></td><td>{ypg(now):.1f}<small>{"above" if ypg(now) > yd / g else "below"} his career rate</small></td></tr>')
bandCR = (f'<section class="dbd-panel{mk("New · careers, overlaid")}"><div class="dbd-ph"><span class="dbd-lab"><b>Careers</b> · receiving yards a game, season by season</span><span class="dbd-badge">Regular season</span></div>'
          f'{legend}<div class="cw-cr">{crch}</div>'
          f'<div class="dbd-tw"><table class="dbd-stat cw-crt"><thead><tr><th>Player</th><th>G</th><th class="dbd-t3">Rec</th><th>Yds</th><th>TD</th><th>Yds/g</th><th class="dbd-t2">Best season</th><th>This season</th></tr></thead><tbody>{crow}</tbody></table></div>'
          f'<p class="dbd-note">Regular season only, from each player\'s career file. * {cur} is through Week {S["current_week"] - 1 if S["current_week"] > 1 else 1}; its dot is hollow because the season is not over. Hover a dot for the season\'s line.</p></section>')

# ---------------------------------------------------------------- assemble: new sections in their places
def at(label):
    i = ROOT.find(f'<b>{label}</b>'); return ROOT.rfind('<section class="dbd-panel', 0, i)
def end_of(label):
    a = at(label); depth = 0; i = a
    while True:
        o = ROOT.find('<section', i + 1); c = ROOT.find('</section>', i + 1)
        if o != -1 and o < c: depth += 1; i = o
        else:
            if depth == 0: return c + len('</section>')
            depth -= 1; i = c
out = ROOT
ins = [(end_of('Who'), bandWeek), (end_of('Where they rank'), bandFP), (at('Where the ball finds them'), bandEP), (at('Floor and ceiling'), bandTR), (end_of('Season stats'), bandCR)]
for pos, html_ in sorted(ins, key=lambda x: -x[0]): out = out[:pos] + html_ + out[pos:]
SHELL = open('shell.html').read()
open('compare.html', 'w').write(SHELL.replace('%TITLE%', 'Compare, finished · mock').replace('%ROOT%', out))
print('ok', NW, seasons, [round(t, 1) for t in tots])
