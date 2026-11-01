import json, glob, os, re, sys, html
SP, DD, OUT = sys.argv[1:4]
S = json.load(open(SP + '/nfelo-calc/sample.json'))
P = []
for f in glob.glob(DD + '/players/*.json'):
    if os.path.basename(f) in ('index.json', 'league.json', 'movers.json'): continue
    P += json.load(open(f))['players']
sched = json.load(open(DD + '/schedule.json'))
esc = html.escape
def T(p, k): return sum(float(w.get(k) or 0) for w in p['weeks'])
def ordn(n):
    n = int(n); s = 'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th'); return f'{n}{s}'
def rank_in(pool, key, me, low=False):
    v = key(me); vals = [key(x) for x in pool]
    better = sum(1 for x in vals if (x < v - 1e-9 if low else x > v + 1e-9)); ties = sum(1 for x in vals if abs(x - v) < 1e-9)
    return (('T-' if ties > 1 else '') + ordn(better + 1)), better + 1
sgn = lambda v, d=2: ('+' if v > 0 else ('−' if v < 0 else '')) + f'{abs(v):.{d}f}'
def chip(rk, n, what, pool, top):
    lab, num = rk
    cls = ' top' if num <= top else ''
    return f'<div class="dbd-trk{cls}" data-tip="&lt;b&gt;{esc(lab)}&lt;/b&gt; of {n} {esc(pool)} in {esc(what)}" tabindex="0"><b>{esc(lab)}</b> of {n}</div>'
NEW = lambda inner, label='New': f'<div class="mk-new" data-mk="{esc(label)}">{inner}</div>'

def tiles_with_ranks(root, specs):
    # specs: list of (tile label, chip html, new value or None, new sub or None, new label or None)
    for lab, ch, nv, ns, nl in specs:
        m = re.search(r'<div class="dbd-tile"><div class="l">' + re.escape(lab) + r'</div><div class="v">([^<]*)</div><div class="s">([^<]*)</div></div>', root)
        if not m: print('tile not found', lab); continue
        v = nv if nv is not None else m.group(1); s = ns if ns is not None else m.group(2); l = nl or lab
        flag = ' mk-tile' if (nv is not None or nl) else ''
        rep = f'<div class="dbd-tile{flag}"><div class="l">{esc(l)}</div><div class="v">{esc(v)}</div><div class="s">{esc(s)}</div>{ch}</div>'
        root = root[:m.start()] + rep + root[m.end():]
    return root.replace('<div class="dbd-tiles">', '<div class="dbd-tiles mk-chips">', 1)

def add_sstat_rows(root, rows):
    tr = ''.join(f'<tr class="mk-row{" top" if top else ""}"><td>{esc(a)}</td><td>{esc(b)}</td><td class="rk"><b>{esc(c)}</b> of {n}</td></tr>' for a, b, c, n, top in rows)
    i = root.find('<table class="dbd-sstat dbd-stat">'); j = root.find('</table>', i)
    return root[:j] + tr + root[j:]

def waterfall(rows, db_label, say, career, note, title, sub, badge):
    # rows: dicts kind in part/sub/total, lab, sm, v, per, pc
    run = 0; pts = [0]
    for r in rows:
        if r['kind'] == 'part': r['a'] = run; run += r['v']; r['b'] = run
        else: r['a'] = 0; r['b'] = r['v']; run = r['v']
        pts += [r['a'], r['b']]
    lo, hi = min(pts), max(pts); pad = (hi - lo) * 0.04; lo -= pad if lo < 0 else 0; hi += pad
    X = lambda v: (v - lo) / (hi - lo) * 100
    out = []
    for r in rows:
        l, w = X(min(r['a'], r['b'])), abs(X(r['b']) - X(r['a']))
        cls = r['kind'] if r['kind'] != 'part' else ('pos' if r['v'] >= 0 else 'neg')
        pc = r.get('pc'); g = '' if pc is None else ordn(pc); gc = ' hi' if pc is not None and pc >= 75 else (' lo' if pc is not None and pc <= 25 else '')
        gtxt = g if pc is not None else (r['rk'].split(' ')[0] if r.get('rk') else '')
        tip = f"&lt;b&gt;{esc(r['lab'])}&lt;/b&gt;: {sgn(r['v'], 1)} EPA" + ('' if r.get('per') is None else f" · {sgn(r['per'], 3)} {db_label}") + ('' if pc is None else f"&lt;br&gt;{ordn(pc)} percentile" + (f" · {r['rk']}" if r.get('rk') else ''))
        out.append(f'<div class="row {r["kind"]}"><div class="l">{esc(r["lab"])}' + (f'<small>{esc(r["sm"])}</small>' if r.get('sm') else '') +
                   f'</div><div class="tr"><i class="z" style="left:{X(0):.2f}%"></i><i class="b {cls}" style="left:{l:.2f}%;width:{max(w, 0.4):.2f}%" data-tip="{tip}" tabindex="0"></i><i class="c" style="left:{X(r["b"]):.2f}%"></i></div>' +
                   f'<div class="v">{sgn(r["v"], 1)}</div><div class="p">{"" if r.get("per") is None else sgn(r["per"], 3)}</div><div class="g{gc}">{gtxt}</div></div>')
    head = f'<div class="row hd"><div class="l"></div><div class="tr"></div><div class="v">EPA</div><div class="p">{esc(db_label)}</div><div class="g">Pctl</div></div>'
    return (f'<section class="dbd-panel dbd-ep"><div class="dbd-ph"><span class="dbd-lab"><b>{esc(title)}</b> · {esc(sub)}</span><span class="dbd-badge">{esc(badge)}</span></div>'
            f'<p class="dbd-ep-say">{say}</p><div class="dbd-ep-wf">{head}{"".join(out)}</div>{career}<p class="dbd-note">{note}</p></section>')

DEP = [('deep', 'Deep', '20+ yds'), ('inter', 'Intermediate', '10–19'), ('short', 'Short', '0–9'), ('behind', 'Behind', 'the line')]
COL = [('left', 'Left'), ('middle', 'Middle'), ('right', 'Right')]
def grid(g, title, sub, unit, say, note, cmp=True):
    tot = sum(c['n'] for c in g.values())
    cells = []
    for dk, dl, ds in DEP:
        if dk == 'behind': cells.append('<div class="los"><span>Line of scrimmage</span></div>')
        cells.append(f'<div class="rl"><b>{dl}</b><small>{ds}</small></div>')
        for ck, cl in COL:
            c = g.get(f'{dk}-{ck}', dict(n=0, f=0, lg=0, epa=None, cmp=None))
            ratio = c['f'] / c['lg'] if c['lg'] else 1
            tf = max(0, min(0.3, (ratio - 1) * 0.28)) if ratio > 1 else 0
            tl = max(0, min(0.5, (1 - ratio) * 0.6)) if ratio < 1 else 0
            e = c['epa']; te = 0 if e is None or c['n'] < 3 else max(-0.55, min(0.55, e * 0.45))
            d = (c['f'] - c['lg']) * 100
            tip = (f"&lt;b&gt;{dl} {cl.lower()}&lt;/b&gt;: {c['n']} of {tot} {unit} ({c['f']*100:.0f}%) · league {c['lg']*100:.0f}%" +
                   ('' if e is None else f"&lt;br&gt;{sgn(e)} EPA per {unit[:-1]} · league {sgn(c.get('lge', 0))}") + ('' if not cmp or c.get('cmp') is None else f" · {c['cmp']*100:.0f}% complete"))
            ecls = '' if e is None or c['n'] < 3 else (' pos' if e > 0 else ' neg')
            cells.append(f'<div class="cell{" low" if c["n"] < 3 else ""}" style="--tf:{tf:.2f};--tl:{tl:.2f};--te:{te:.2f}" data-tip="{tip}" tabindex="0">'
                         f'<span class="f">{c["f"]*100:.0f}%</span><span class="d{" up" if d >= 1 else (" dn" if d <= -1 else "")}">league {c["lg"]*100:.0f}%</span>'
                         f'<span class="e{ecls}">{"–" if e is None or c["n"] < 3 else sgn(e)}</span><span class="le">league {sgn(c.get("lge", 0))}</span><span class="n">{c["n"]} {unit}</span></div>')
    head = '<div class="rl"></div>' + ''.join(f'<div class="ch">{cl}</div>' for _, cl in COL)
    return (f'<section class="dbd-panel dbd-lz" data-mode="f"><div class="dbd-ph"><span class="dbd-lab"><b>{esc(title)}</b> · {esc(sub)}</span>'
            f'<div class="dbd-seg t-tabs dbd-lzv" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" data-m="f" aria-selected="true">How often</button><button class="t-tab" type="button" role="tab" data-m="e" aria-selected="false">EPA per {unit[:-1]}</button></div></div>'
            f'<p class="dbd-ep-say">{say}</p><div class="dbd-lzg">{head}{"".join(cells)}</div><div class="dbd-lzk"><span class="k f"><i class="up"></i>more than the league <i class="dn"></i>less</span><span class="k e"><i class="pos"></i>gains EPA <i class="neg"></i>loses EPA</span></div><p class="dbd-note">{note}</p></section>')

def best_cell(g, unit):
    c = [(k, v) for k, v in g.items() if v['n'] >= 5 and v['lg']]
    k, v = max(c, key=lambda kv: kv[1]['f'] / kv[1]['lg'])
    dk, ck = k.split('-'); dl = dict((a, b) for a, b, _ in DEP)[dk].lower(); 
    return k, v, f'{dl} {ck}'

def glog_rows(root, team, weeks_played, dnp, ncols_after):
    # Insert bye, DNP and the rest-of-season rows into the Default table, in week order.
    i = root.find('data-view="Default"'); j = root.find('</tbody>', i)
    body_start = root.find('<tbody>', i) + 7
    rows = re.findall(r'<tr>.*?</tr>', root[body_start:j], re.S)
    byweek = {}
    for r in rows:
        wk = int(re.match(r'<tr><td>(\d+)</td>', r).group(1)); byweek[wk] = r
    games = {}
    for wk, gl in sched['weeks'].items():
        for g in gl:
            if team in (g['away'], g['home']) and g['game_type'] == 'REG': games[int(wk)] = g
    nxt = sched['current_week']
    out, later = [], []
    for wk in range(1, 19):
        if wk in byweek: out.append(byweek[wk]); continue
        g = games.get(wk)
        if not g:
            out.append(f'<tr class="dbd-gx bye"><td>{wk}</td><td class="res" colspan="{ncols_after}">Bye week</td></tr>'); continue
        home = g['home'] == team; opp = g['away'] if home else g['home']; vs = ('vs ' if home else 'at ') + opp
        if g['status'] == 'final':
            pf, pa = (g['home_score'], g['away_score']) if home else (g['away_score'], g['home_score'])
            res = 'W' if pf > pa else ('L' if pf < pa else 'T')
            out.append(f'<tr class="dbd-gx dnp"><td>{wk}</td><td class="res">{vs} <b class="{"l" if res == "L" else ""}">{res}</b> {pf}-{pa}</td><td colspan="{ncols_after - 1}" class="why">Did not play{dnp.get(wk, "")}</td></tr>')
        else:
            from datetime import date
            dd = date.fromisoformat(g['date']); lab = dd.strftime('%a %b ') + str(dd.day)
            row = f'<tr class="dbd-gx up{" next" if wk == nxt else ""}"><td>{wk}</td><td class="res">{vs}</td><td colspan="{ncols_after - 1}" class="why">{lab}{" · this week" if wk == nxt else ""}</td></tr>'
            (out if wk == nxt else later).append(row)
    tb = ''.join(out) + ''.join(r.replace('<tr class="dbd-gx up', '<tr hidden class="dbd-gx up') for r in later)
    more = f'<button type="button" class="dbd-gx-more" aria-expanded="false">Rest of the schedule · {len(later)} games</button>' if later else ''
    root = root[:body_start] + tb + root[j:]
    k = root.find('</table>', root.find('data-view="Default"'))
    return root[:k + 8] + more + root[k + 8:]

def page(root, js, title, out):
    shell = open(SP + '/nfelo-calc/shell.html').read()
    open(out, 'w').write(shell.replace('%TITLE%', esc(title)).replace('%ROOT%', root).replace('%JS%', js))

def insert_after_panel(root, label, html_):
    i = root.find('<b>' + label + '</b>'); j = root.find('</section>', i) + len('</section>')
    return root[:j] + html_ + root[j:]

# ================================================================ QB: Josh Allen
q = S['qb']; qx = q['extra']
allen = next(x for x in P if x['id'] == '00-0034857')
QBP = [x for x in P if x['pos'] == 'QB' and T(x, 'attempts') >= 50]
n = len(QBP); pool = 'qualifying QBs'
db = lambda p: T(p, 'attempts') + T(p, 'sacks_suffered')
specs = [('Attempts', chip(rank_in(QBP, lambda p: T(p, 'attempts'), allen), n, 'attempts', pool, 10), None, None, None),
         ('Completion', chip(rank_in(QBP, lambda p: T(p, 'completions') / T(p, 'attempts'), allen), n, 'completion rate', pool, 10), None, None, None),
         ('Pass yards', chip(rank_in(QBP, lambda p: T(p, 'passing_yards'), allen), n, 'passing yards', pool, 10), None, None, None),
         ('TD / INT', chip(rank_in(QBP, lambda p: T(p, 'passing_tds'), allen), n, 'touchdown passes', pool, 10), None, f"{qx['fd']} first downs", None),
         ('EPA / dropback', chip(rank_in(QBP, lambda p: T(p, 'passing_epa') / db(p), allen), n, 'EPA per dropback', pool, 10), None, None, None),
         ('Sacks', chip(rank_in(QBP, lambda p: T(p, 'sacks_suffered') / db(p), allen, low=True), n, 'sack rate (fewest first)', pool, 10), None, None, None),
         ('Rush', chip(rank_in(QBP, lambda p: T(p, 'rushing_yards'), allen), n, 'rushing yards', pool, 10), None, None, None),
         ('Snap share', chip((ordn(q['anya_rk']), q['anya_rk']), n, 'ANY/A', pool, 10), f"{q['anya']:.2f}", 'net yds per dropback', 'ANY/A')]
A = open(SP + '/allen-root.html').read()
A = tiles_with_ranks(A, specs)
pe, re_ = T(allen, 'passing_epa'), T(allen, 'rushing_epa'); tot = pe + re_
tot_rk = rank_in(QBP, lambda p: T(p, 'passing_epa') + T(p, 'rushing_epa'), allen)
A = add_sstat_rows(A, [('Success rate', f"{q['succ']*100:.0f}%", ordn(qx['succ_rk']), n, qx['succ_rk'] <= 3), ('ANY/A', f"{q['anya']:.2f}", ordn(q['anya_rk']), n, q['anya_rk'] <= 3), ('Total EPA', sgn(tot, 1), tot_rk[0], n, tot_rk[1] <= 3)])
C = q['comp']; dbn = q['db']
resid = round(pe - qx['pass_epa'], 1)
rows = [dict(kind='part', lab='Air yards', sm='on completions', v=C['air']['tot'], per=C['air']['per'], pc=C['air']['pc']),
        dict(kind='part', lab='After the catch', sm='his share, catch to whistle', v=C['yac']['tot'], per=C['yac']['per'], pc=C['yac']['pc']),
        dict(kind='part', lab='Incompletions', v=C['inc']['tot'], per=C['inc']['per'], pc=C['inc']['pc']),
        dict(kind='part', lab='Interceptions', v=C['intE']['tot'], per=C['intE']['per'], pc=C['intE']['pc']),
        dict(kind='part', lab='Sacks', v=C['skE']['tot'], per=C['skE']['per'], pc=C['skE']['pc'])]
if abs(resid) >= 0.1: rows.append(dict(kind='part', lab='Other', sm='fumbles, stat corrections', v=resid))
rows.append(dict(kind='sub', lab='Passing', sm='matches EPA / dropback above', v=round(pe, 1), per=round(pe / db(allen), 3)))
rows += [dict(kind='part', lab='Scrambles', v=C['scr']['tot'], per=C['scr']['per'], pc=C['scr']['pc']),
         dict(kind='part', lab='Designed runs', sm='incl. kneels', v=round(re_ - C['scr']['tot'], 1), pc=C['run']['pc'])]
rows.append(dict(kind='total', lab='Total EPA', v=round(tot, 1), rk=f'{tot_rk[0]} of {n}'))
say = (f"Allen's <b>{sgn(tot, 1)}</b> EPA is <b>{tot_rk[0]}</b> of {n} quarterbacks. The biggest share comes <b>after the catch</b> ({sgn(C['yac']['tot'], 1)}, {ordn(C['yac']['pc'])} percentile per dropback) "
       f"and his <b>scrambles</b> are the best in the league at {ordn(C['scr']['pc'])}; <b>sacks</b> cost him more than most ({sgn(C['skE']['tot'], 1)}, {ordn(C['skE']['pc'])}).")
car = [(2018, -0.114), (2019, -0.024), (2020, 0.282), (2021, 0.119), (2022, 0.17), (2023, 0.152), (2024, 0.262), (2025, 0.143)]
sea = pe / db(allen)
mx = max(abs(v) for _, v in car + [(0, sea)])
bars = ''.join(f'<i style="--h:{abs(v)/mx*100:.0f}%" class="{"neg" if v < 0 else ""}" data-tip="&lt;b&gt;{y}&lt;/b&gt; {sgn(v)} per dropback" tabindex="0"><em>{str(y)[2:]}</em></i>' for y, v in car) + f'<i style="--h:{abs(sea)/mx*100:.0f}%" class="now" data-tip="&lt;b&gt;2026&lt;/b&gt; {sgn(sea)} per dropback" tabindex="0"><em>26</em></i>'
career = (f'<div class="dbd-ep-car"><div class="t"><b>{sgn(sea)}</b> passing EPA per dropback this season<br><span>career <b>{sgn(0.137)}</b> over 2018–2025 · the best season of his nine so far</span></div><div class="sp">{bars}</div></div>')
note = ('Every dropback and run this season, split by what happened. Completions are divided at the catch: the yards in the air, and what came after (nflverse\'s air and YAC EPA). '
        'Per dropback counts scrambles as dropbacks; the percentile is him among the qualifying quarterbacks, higher is better, so a cost that is smaller than most ranks high. Parts sum to the totals on the page.')
A = insert_after_panel(A, 'Playstyle', NEW(waterfall(rows, 'per dropback', say, career, note, 'Where his EPA comes from', 'every dropback and run, split', f'{n} QBs')))
g = q['grid']; k, v, nm = best_cell(g, 'throws')
gsay = (f"He throws <b>{nm}</b> {v['f']/v['lg']:.1f}× as often as the league ({v['f']*100:.0f}% of his attempts against {v['lg']*100:.0f}%) and gains <b>{sgn(v['epa'])}</b> EPA a throw there. "
        f"Behind the line on the right, the screens, is his next most over-used spot ({g['behind-right']['f']*100:.0f}% against {g['behind-right']['lg']*100:.0f}%).")
gnote = 'Every pass attempt with a charted depth and direction, sacks and throwaways without a target excluded. Left and right are as the offense sees the field. Cells with fewer than three throws show no EPA.'
A = insert_after_panel(A, 'Where his EPA comes from', NEW(grid(g, 'Depth by direction', 'where he throws, against the league', 'throws', gsay, gnote)))
A = glog_rows(A, 'BUF', None, {}, 8)
A = A.replace('<b>Game log</b> · week by week', '<b>Game log</b> · all eighteen weeks')
page(A, open(SP + '/allen-root.html.js').read(), 'Josh Allen · nfelo parity mock', OUT + '/parity-qb.html')

# ================================================================ WR: Justin Jefferson
r = S['rec']; rx = r['extra']
jj = next(x for x in P if x['id'] == '00-0036322')
RP = [x for x in P if x['pos'] in ('WR', 'TE') and T(x, 'targets') >= 15]
n = len(RP); pool = 'qualifying receivers'
avg = lambda p, k: T(p, k) / max(1, len(p['weeks']))
specs = [('Targets', chip(rank_in(RP, lambda p: T(p, 'targets'), jj), n, 'targets', pool, 10), None, None, None),
         ('Receptions', chip(rank_in(RP, lambda p: T(p, 'receptions'), jj), n, 'receptions', pool, 10), None, None, None),
         ('Yards', chip(rank_in(RP, lambda p: T(p, 'receiving_yards'), jj), n, 'receiving yards', pool, 10), None, None, None),
         ('Touchdowns', chip(rank_in(RP, lambda p: T(p, 'receiving_tds'), jj), n, 'receiving touchdowns', pool, 10), None, None, None),
         ('Target share', chip(rank_in(RP, lambda p: avg(p, 'target_share'), jj), n, 'target share', pool, 10), None, None, None),
         ('aDOT', chip(rank_in(RP, lambda p: T(p, 'receiving_air_yards') / T(p, 'targets'), jj), n, 'depth of target', pool, 10), None, None, None),
         ('EPA / target', chip(rank_in(RP, lambda p: T(p, 'receiving_epa') / T(p, 'targets'), jj), n, 'EPA per target', pool, 10), None, None, None),
         ('YAC / catch', chip(rank_in(RP, lambda p: T(p, 'receiving_yards_after_catch') / max(1, T(p, 'receptions')), jj), n, 'yards after the catch per catch', pool, 10), None, None, None)]
J = open(SP + '/jj-root.html').read()
J = tiles_with_ranks(J, specs)
te = T(jj, 'receiving_epa'); trk = rank_in(RP, lambda p: T(p, 'receiving_epa'), jj)
J = add_sstat_rows(J, [('Success rate', f"{r['succ']*100:.0f}%", ordn(rx['succ_rk']), n, False), ('Total EPA', sgn(te, 1), trk[0], n, trk[1] <= 3)])
C = r['comp']; tg = r['tg']; resid = round(te - r['total'], 1)
rows = [dict(kind='part', lab='Air yards', sm='on catches', v=C['air']['tot'], per=C['air']['per'], pc=C['air']['pc']),
        dict(kind='part', lab='After the catch', v=C['yac']['tot'], per=C['yac']['per'], pc=C['yac']['pc']),
        dict(kind='part', lab='Incompletions', sm='targets not caught', v=C['inc']['tot'], per=C['inc']['per'], pc=C['inc']['pc']),
        dict(kind='part', lab='Interceptions', sm='when targeted', v=C['intE']['tot'], per=C['intE']['per'], pc=C['intE']['pc'])]
if abs(resid) >= 0.1: rows.append(dict(kind='part', lab='Other', sm='fumbles, stat corrections', v=resid))
rows.append(dict(kind='total', lab='Total EPA', sm='matches EPA / target above', v=round(te, 1), per=round(te / tg, 3), rk=f'{trk[0]} of {n}'))
say = (f"Jefferson's <b>{sgn(te, 1)}</b> receiving EPA is <b>{trk[0]}</b> of {n} receivers on {tg} targets. More of it comes <b>in the air</b> ({sgn(C['air']['tot'], 1)}, {ordn(C['air']['pc'])} percentile per target) "
       f"than after the catch ({sgn(C['yac']['tot'], 1)}, {ordn(C['yac']['pc'])}); no target to him has been intercepted.")
car = [(2020, 0.529), (2021, 0.465), (2022, 0.41), (2023, 0.428), (2024, 0.417), (2025, -0.098)]
sea = te / tg; mx = max(abs(v) for _, v in car + [(0, sea)])
bars = ''.join(f'<i style="--h:{abs(v)/mx*100:.0f}%" class="{"neg" if v < 0 else ""}" data-tip="&lt;b&gt;{y}&lt;/b&gt; {sgn(v)} per target" tabindex="0"><em>{str(y)[2:]}</em></i>' for y, v in car) + f'<i style="--h:{abs(sea)/mx*100:.0f}%" class="now" data-tip="&lt;b&gt;2026&lt;/b&gt; {sgn(sea)} per target" tabindex="0"><em>26</em></i>'
career = f'<div class="dbd-ep-car"><div class="t"><b>{sgn(sea)}</b> EPA per target this season<br><span>career <b>{sgn(0.359)}</b> over 2020–2025 · a bounce back from 2025 ({sgn(-0.098)})</span></div><div class="sp">{bars}</div></div>'
note = 'Every target this season, split by what happened. Catches are divided at the catch: the yards in the air, and what came after. The percentile is him among the qualifying receivers (15+ targets), higher is better. Parts sum to the totals on the page.'
J = insert_after_panel(J, 'Playstyle', NEW(waterfall(rows, 'per target', say, career, note, 'Where his EPA comes from', 'every target, split', f'{n} receivers')))
g = r['grid']; k, v, nm = best_cell(g, 'targets')
gsay = f"He is targeted <b>{nm}</b> {v['f']/v['lg']:.1f}× as often as the league's receivers ({v['f']*100:.0f}% of his targets against {v['lg']*100:.0f}%)" + ('' if v['epa'] is None else f" and gains <b>{sgn(v['epa'])}</b> EPA a target there") + '.'
J = insert_after_panel(J, 'Where his EPA comes from', NEW(grid(g, 'Depth by direction', 'where he is targeted, against the league', 'targets', gsay, 'Every target with a charted depth and direction. Left and right are as the offense sees the field. Cells with fewer than three targets show no EPA.', cmp=False)))
# game log: how many columns follow the week column in the Default table
hd = re.search(r'data-view="Default"><table[^>]*><thead><tr>(.*?)</tr>', J).group(1); nc = hd.count('<th') - 1
J = glog_rows(J, 'MIN', None, {}, nc)
J = J.replace('<b>Game log</b> · week by week', '<b>Game log</b> · all eighteen weeks')
page(J, open(SP + '/jj-root.html.js').read(), 'Justin Jefferson · nfelo parity mock', OUT + '/parity-wr.html')
print('ok', n)
