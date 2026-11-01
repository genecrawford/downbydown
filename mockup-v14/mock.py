"""Mock for SCOPE §39: the defensive half. The Vikings' Defense tab, built from this season's nflverse files through Week 8
(src/defcalc.py → src/defense.json), inside the live 1.14.0 team page shell, with the existing explorer run on the 339 plays the
Vikings defended (data/MIN-def.json). python3 -I mock.py"""
import json, re, html
esc = html.escape
X = json.load(open('src/defense.json')); D, LG, RANK, DIST = X['D'], X['LG'], X['RANK'], X['DIST']
T = json.load(open('../data/teams.json')); M = json.load(open('../data/metrics.json')); FX = json.load(open('../data/fantasy.json'))
NM = json.load(open('src/names.json'))
for _t in D:
    for _w in D[_t]['who']: _w[1] = NM.get(_w[0], _w[1])
A = 'MIN'; d = D[A]; t = T[A]; NICK = t['nick']; TEAMS = sorted(D)
def ordn(n): n = int(n); return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
pct = lambda v: '–' if v is None else f'{round(v * 100)}%'
pct1 = lambda v: '–' if v is None else f'{v * 100:.1f}%'
sgn = lambda v, k=2: '–' if v is None else ('+' if v > 0 else '') + f'{v:.{k}f}'
f1 = lambda v: '–' if v is None else f'{v:.1f}'
logo = lambda a, s=16: f'<img class="lg" src="{T[a]["logo"]}" alt="" width="{s}" height="{s}">'
def rk_of(vals, v, hib):  # 1 = best (or most, for style counts)
    return 1 + sum(1 for x in vals if (x > v if hib else x < v))
def pctile(k, v, hib):
    a = DIST[k]; below = sum(1 for x in a if x < v); eq = sum(1 for x in a if x == v); p = (below + eq / 2) / len(a)
    return round(100 * (p if hib else 1 - p))
root = open('src/root-team-MIN.html').read()
pennant = re.search(r'<h1[^>]*>(.*?)<span>', root, re.S).group(1)

# ---------------- title and switch
title = (f'<div class="dbd-ttitle"><div class="t-stagger is-shown"><p class="dbd-kicker">2026 season · Weeks 1 to 8 · {d["w"]}-{d["l"]}</p>'
         f'<h1 class="withmark">{pennant}<span><em>{NICK}</em> defense</span></h1>'
         f'<p class="dbd-sub">Every snap the Vikings defended, what it cost them and how they got there: the league ranks the player pages quote, where offenses find room, how this defense plays, and who plays in it.</p>'
         '<div class="dbd-seg t-tabs ld-side mk-new" data-mk="New · offense or defense" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><a class="t-tab" role="tab" aria-selected="false" href="#">Offense</a><a class="t-tab" role="tab" aria-selected="true" href="#">Defense</a></div></div>'
         f'<div class="dbd-badge">{ordn(RANK[A]["epa"])} of 32 in EPA allowed</div></div>')
jump = '<nav class="dbd-jump" aria-label="On this page"><a href="#df-good">How good is it</a><a href="#df-beat">Where offenses find room</a><a href="#df-play">How they play</a><a href="#df-who">The defenders</a><a href="#df-snaps">Every snap</a></nav>'

# ---------------- hero tiles
al = d['all']
def tile(lab, v, sub, k):
    r = RANK[A][k]
    return f'<div class="dbd-tile"><div class="l">{lab}</div><div class="v">{v}</div><div class="s">{sub}</div><div class="dbd-trk{" top" if r <= 10 else ""}" data-tip="{esc("<b>" + ordn(r) + "</b> of 32 defenses")}" tabindex="0"><b>{ordn(r)}</b> of 32</div></div>'
tiles = ('<div class="dbd-tiles mk-new" data-mk="New · hero tiles, ranked among 32">' +
         tile('EPA allowed', sgn(al['epa']), 'per play, league ' + sgn(LG['epa']), 'epa') +
         tile('Success allowed', pct(al['sr']), 'league ' + pct(LG['sr']), 'sr') +
         tile('Points allowed', f1(d['pa_pg']), 'per game', 'pa_pg') +
         tile('Takeaways', str(d['take']), f'{d["int"]} INT · {d["fum"]} fumbles', 'take_pg') +
         tile('Sack rate', pct1(al['sack']), 'of dropbacks, league ' + pct1(LG['sack']), 'sack') +
         tile('Pressure rate', pct(d['press']), f'{d["pressures"]} pressures', 'press') +
         tile('Third downs', pct(al['third']), 'converted against them', 'third') +
         tile('Red zone', pct(d['rz_td']), f'TD on {round(d["rz_td"] * d["rz_n"])} of {d["rz_n"]} trips', 'rz_td') + '</div>')

# ---------------- the short version
st = d['style']; who = d['who']
blz_rk = rk_of([D[x]['style']['blitz'] for x in TEAMS], st['blitz'], True)
ypc_rk = RANK[A]['ypc']
lanes = d['lanes']; worst_lane = max((k for k in lanes if lanes[k][0] >= 8), key=lambda k: lanes[k][2])
LANE_NM = {'le': 'outside left', 'lt': 'off left tackle', 'lg': 'left guard', 'mid': 'up the middle', 'rg': 'right guard', 'rt': 'off right tackle', 're': 'outside right'}
nxt_opp = 'BAL'
short = ('<section class="dbd-panel dbd-short mk-new" data-mk="New · the short version"><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b></span></div><ul>'
         f'<li><a href="#df-good">How good</a><span>The league\'s best defense: <b>{sgn(al["epa"])}</b> EPA a play allowed, <b>1st</b> of 32, and <b>{sgn(d["adj"])}</b> below what the offenses they faced usually manage, also <b>1st</b>.</span></li>'
         f'<li><a href="#df-play">How they play</a><span>They blitz on <b>{pct(st["blitz"])}</b> of dropbacks, the <b>{ordn(blz_rk)}</b>-most in the league, and get pressure on <b>{pct(d["press"])}</b>.</span></li>'
         f'<li><a href="#df-beat">Where offenses find room</a><span>On the ground: <b>{ordn(ypc_rk)}</b> in yards per carry ({f1(al["ypc"])}), and runs {LANE_NM[worst_lane]} gain <b>{f1(lanes[worst_lane][1])}</b> a carry.</span></li>'
         f'<li><a href="#df-beat">Who hurt them</a><span><b>{esc(who[0][1])}</b> ({who[0][2]}): {who[0][3]} yards and {sgn(who[0][4], 1)} EPA on {who[0][5]} touches, the most anyone has managed against them.</span></li>'
         f'<li><a href="#df-good">Next up</a><span>Week 9 at the <b>Ravens</b>, whose offense ranks <b>{ordn(sorted(TEAMS, key=lambda x: -X["OFF"][x]["epa"]).index(nxt_opp) + 1)}</b> in EPA a play.</span></li></ul></section>')

# ---------------- band 1: how good is it
SC = [('epa', 'EPA per play', 'every snap', 'sgn', False), ('pepa', 'Pass EPA', 'per dropback', 'sgn', False), ('repa', 'Rush EPA', 'per designed run', 'sgn', False), ('sr', 'Success rate', 'plays that gain enough', 'pct', False),
      ('expl', 'Explosive rate', '20+ pass, 10+ run', 'pct', False), ('ypp', 'Yards per play', '', 'f1', False), ('ypc', 'Yards per carry', '', 'f1', False), ('third', 'Third downs', 'converted against them', 'pct', False),
      ('sack', 'Sack rate', 'per dropback', 'pct1', True), ('press', 'Pressure rate', 'per dropback, PFR', 'pct', True), ('hit', 'QB hit rate', 'per dropback', 'pct', True), ('intr', 'INT rate', 'per dropback', 'pct1', True),
      ('rz_td', 'Red-zone TD rate', 'trips ending in a touchdown', 'pct', False), ('ppd', 'Points per drive', 'touchdown 7, field goal 3', 'f2', False), ('take_pg', 'Takeaways', 'per game', 'f1', True), ('adj', 'EPA against expectation', 'allowed minus the offenses\' usual', 'sgn', False)]
FMT = {'sgn': sgn, 'pct': pct, 'pct1': pct1, 'f1': f1, 'f2': lambda v: f'{v:.2f}'}
def val(k): return al[k] if k in al else d[k]
def sc_row(r):
    k, lab, sub, kind, hib = r; v = val(k); rk = RANK[A][k]; p = pctile(k, v, hib); a = DIST[k]; med = a[(len(a) - 1) // 2]
    tip = esc(f'<b>Vikings defense</b><br>{lab}: <b>{FMT[kind](v)}</b> · {ordn(p)} percentile · {ordn(rk)} of 32<br><span class="m">League median {FMT[kind](med)}</span>')
    return (f'<tr><td class="n">{lab}<small>{sub}</small></td><td class="v">{FMT[kind](v)}</td><td class="rk"><b>{ordn(rk)}</b> of 32</td>'
            f'<td class="b"><div class="bar"><i style="--p:{p}" data-tip="{tip}" tabindex="0" role="img"></i></div></td><td class="g{" hi" if p >= 75 else (" lo" if p <= 25 else "")}">{ordn(p)}</td></tr>')
head = '<thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead>'
score = ('<section class="dbd-panel dbd-score mk-new" data-mk="New · the defense Scorecard"><div class="dbd-ph"><span class="dbd-lab"><b>Scorecard</b> · against all 32 defenses</span><span class="dbd-badge">League percentiles</span></div>'
         f'<div class="dbd-score2"><table class="dbd-stat">{head}<tbody>{"".join(sc_row(r) for r in SC[:8])}</tbody></table><table class="dbd-stat">{head}<tbody>{"".join(sc_row(r) for r in SC[8:])}</tbody></table></div>'
         '<p class="dbd-note">Each bar is the league\'s 32 defenses; the marker is the Vikings. Right is better for the defense, so a low EPA allowed sits right. The pressure rate is PFR\'s pressures (hurries, hits and sacks) per dropback. EPA against expectation compares each game with what that offense averaged in its other games, so a strong schedule is not held against them.</p></section>')
# game by game
wk = d['weeks']; held = sum(1 for w in wk if w['oe'] is not None and w['epa'] < w['oe'])
span = max(max(abs(w['epa']), abs(w['oe'] or 0)) for w in wk) * 1.15
gx = lambda v: round(50 + v / span * 50, 1)
grow = ''
for w in wk:
    res = ('W' if w['pf'] > w['pa'] else ('L' if w['pf'] < w['pa'] else 'T')) + f' {w["pf"]}-{w["pa"]}'
    lo, hi = sorted((gx(w['epa']), gx(w['oe'])))
    better = w['epa'] < w['oe']
    tip = esc(f'<b>Week {w["wk"]} {"vs" if w["home"] else "at"} {T[w["opp"]]["nick"]}</b><br>Allowed {sgn(w["epa"])} EPA a play · the {T[w["opp"]]["nick"]} usually manage {sgn(w["oe"])}<br>{abs(w["epa"] - w["oe"]):.2f} {"better" if better else "worse"} than their usual')
    grow += (f'<div class="gw"><span class="wkl">Wk {w["wk"]}</span><span class="op">{logo(w["opp"])}{"vs" if w["home"] else "at"} {w["opp"]}</span><span class="rs {"w" if w["pf"] > w["pa"] else "l"}">{res}</span>'
             f'<span class="tr" data-tip="{tip}" tabindex="0"><i class="z"></i><i class="gap {"good" if better else "bad"}" style="left:{lo}%;width:{round(hi - lo, 1)}%"></i><i class="ex" style="left:{gx(w["oe"])}%"></i><i class="me" style="left:{gx(w["epa"])}%"></i></span>'
             f'<span class="num">{sgn(w["epa"])}<small>usual {sgn(w["oe"])}</small></span></div>')
games = (f'<section class="dbd-panel dbd-gbg mk-new" data-mk="New · game by game"><div class="dbd-ph"><span class="dbd-lab"><b>Game by game</b> · EPA a play allowed, against what each offense usually manages</span><span class="dbd-badge">held {held} of {len(wk)} below their usual</span></div>'
         f'<p class="dbd-wp-say">They held <b>{held} of {len(wk)}</b> offenses below their usual EPA a play. The biggest stop: <b>Week {min(wk, key=lambda w: w["epa"] - w["oe"])["wk"]}</b> against the {T[min(wk, key=lambda w: w["epa"] - w["oe"])["opp"]]["nick"]}, {abs(min(w["epa"] - w["oe"] for w in wk)):.2f} a play under their norm. The offenses they have faced average {sgn(d["sos"])} a play elsewhere, the <b>{ordn(rk_of([D[x]["sos"] for x in TEAMS], d["sos"], True))}</b>-toughest schedule of offenses.</p>'
         f'<div class="gwl"><div class="gw hd"><span>Week</span><span>Opponent</span><span>Result</span><span class="trh"><em>better for the defense ←</em><em>→ worse</em></span><span>Allowed</span></div>{grow}</div>'
         '<div class="dbd-legend"><span><i class="sq" style="background:var(--dbd-ink)"></i>EPA a play allowed</span><span><i class="sq hollow"></i>that offense\'s usual, in its other games</span><span><i class="sq" style="background:rgba(30,127,74,.35)"></i>held below</span><span><i class="sq" style="background:rgba(180,38,31,.3)"></i>gave up more</span></div></section>')
band1 = f'<section class="dbd-band" id="df-good"><h2 class="dbd-bandh">How good is it<span>the defense against the league, and game by game</span></h2>{score}{games}</section>'

# ---------------- band 2: where offenses find room (tabbed)
G = d['grid']; LGG = X['LGG']; gn = d['grid_n']; lgn = sum(v[0] for v in LGG.values())
DEP = (('dp', 'Deep', '20+ yds'), ('in', 'Intermediate', '10 to 19'), ('sh', 'Short', '0 to 9'), ('bh', 'Behind', 'the line'))
cells = '<div class="rl"></div><div class="ch">Left</div><div class="ch">Middle</div><div class="ch">Right</div>'
for dk, nm, sm in DEP:
    cells += f'<div class="rl"><b>{nm}</b><small>{sm}</small></div>'
    for loc in ('left', 'middle', 'right'):
        n, e, cp = G[dk + '.' + loc]; ln, le, lc = LGG[dk + '.' + loc]; fr = n / gn if gn else 0; lfr = ln / lgn
        te = max(-0.55, min(0.55, -(e - le) / 0.8)) if e is not None else 0  # capped so the numbers stay readable
        tip = esc(f'<b>{nm} {loc}</b>: {n} of {gn} throws against them ({round(fr * 100)}%) · league {round(lfr * 100)}%<br>' + (f'{sgn(e)} EPA per throw allowed · league {sgn(le)}<br>{pct(cp)} completed · league {pct(lc)}' if e is not None else 'no throws'))
        cells += (f'<div class="cell{" low" if n < 5 else ""}" style="--tf:{round(min(1, fr / 0.25), 2)};--te:{round(te, 2)}" data-tip="{tip}" tabindex="0"><span class="f">{round(fr * 100)}%</span><span class="d{" up" if fr - lfr >= .02 else (" dn" if lfr - fr >= .02 else "")}">league {round(lfr * 100)}%</span>'
                  f'<span class="e">{sgn(e)}</span><span class="le">league {sgn(le)}</span><span class="n">{n} throw{"s" if n != 1 else ""}</span></div>')
worst = max((k for k in G if G[k][0] >= 6), key=lambda k: G[k][1] - LGG[k][1])
best = min((k for k in G if G[k][0] >= 6), key=lambda k: G[k][1] - LGG[k][1])
nmk = lambda k: dict(dp='deep', **{'in': 'intermediate'}, sh='short', bh='behind the line')[k.split('.')[0]] + ' ' + k.split('.')[1]
pane_pass = (f'<section class="dbd-panel dbd-pane dbd-lz" data-pane="0" data-mode="e"><div class="dbd-ph"><span class="dbd-lab"><b>Pass by depth and direction</b><span class="sub">where offenses throw against them, against the league</span></span>'
             '<div class="dbd-seg t-tabs dbd-lzv" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" data-m="f" aria-selected="false">How often</button><button class="t-tab" type="button" role="tab" data-m="e" aria-selected="true">EPA allowed</button></div></div>'
             f'<p class="dbd-ep-say">The soft spot is <b>{nmk(worst)}</b>: {sgn(G[worst][1])} EPA a throw against a league {sgn(LGG[worst][1])}. The lockdown zone is <b>{nmk(best)}</b>, {sgn(G[best][1])} a throw (league {sgn(LGG[best][1])}). Completions against them run <b>{abs(st["cpoe"]):.1f} points under expected</b>, {ordn(rk_of([D[x]["style"]["cpoe"] for x in TEAMS], st["cpoe"], False))}-best in the league.</p>'
             f'<div class="dbd-lzg">{cells}</div><p class="dbd-note">Every pass they faced with a charted air distance and side, sacks excluded. Green is a zone where they allow less than the league, red more. How often shows the share of throws against them there.</p></section>')
LGL = X['LGL']; ln_tot = d['lanes_n']
lane_html = ''
for k, nm, ab in (('le', 'Left end', 'LE'), ('lt', 'Left tackle', 'LT'), ('lg', 'Left guard', 'LG'), ('mid', 'Middle', 'MID'), ('rg', 'Right guard', 'RG'), ('rt', 'Right tackle', 'RT'), ('re', 'Right end', 'RE')):
    n, ypc, e, sr = lanes[k]; lnn, lypc, le, lsr = LGL[k]
    cls = 'good' if e is not None and e < le - .05 else ('bad' if e is not None and e > le + .05 else '')
    tip = esc(f'<b>{nm}</b>: {n} carries against them ({pct(n / ln_tot)})<br>{f1(ypc)} yards a carry · league {f1(lypc)}<br>{sgn(e)} EPA · league {sgn(le)} · {pct(sr)} success')
    lane_html += f'<div class="ln {cls}" data-tip="{tip}" tabindex="0"><b>{f1(ypc)}</b><small>yds/car</small><span class="ep">{sgn(e)}</span><span class="cn">{n} car</span><i style="--h:{round(min(1, (n / ln_tot) / 0.3) * 100)}%"></i><em data-s="{ab}">{nm}</em></div>'
pane_run = (f'<section class="dbd-panel dbd-pane" data-pane="1" hidden><div class="dbd-ph"><span class="dbd-lab"><b>Runs by lane</b><span class="sub">where offenses run against them, from left end to right end</span></span><span class="dbd-badge">{ln_tot} designed runs</span></div>'
            f'<p class="dbd-ep-say">Runs are where offenses have had their success: <b>{f1(al["ypc"])}</b> yards a carry, <b>{ordn(ypc_rk)}</b> of 32. The leak is {LANE_NM[worst_lane]}: <b>{f1(lanes[worst_lane][1])}</b> a carry and {sgn(lanes[worst_lane][2])} EPA (league {f1(LGL[worst_lane][1])}, {sgn(LGL[worst_lane][2])}).</p>'
            f'<div class="dbd-lanes">{lane_html}<div class="line"><span>TE</span><span>LT</span><span>LG</span><span>C</span><span>RG</span><span>RT</span><span>TE</span></div></div>'
            '<div class="dbd-legend"><span><i class="sq" style="background:rgba(30,127,74,.35)"></i>held under the league\'s EPA there</span><span><i class="sq" style="background:rgba(180,38,31,.3)"></i>over it</span><span>Bar height is the share of runs</span></div></section>')
# by position, with the metrics ranks the player pages quote and fantasy points allowed
dm = M['defense'][A]; dr = M['rank']['def'][A]; fr_ = FX['rank'][A]; fa = FX['allow'][A]
prow = ''
for ps, nm in (('WR', 'Wide receivers'), ('TE', 'Tight ends'), ('RB', 'Backs, receiving')):
    fx = ''.join(f'<span data-fx="{fm}">{fa[ps][i]:.1f}<small>{ordn(33 - fr_[ps][i])}-fewest</small></span>' for i, fm in enumerate(('std', 'half', 'ppr')))
    prow += (f'<tr><td class="n">{nm}</td><td>{dm[ps + "_tgt_pg"]:.1f}<small>{ordn(dr[ps + "_tgt_pg"])}</small></td><td>{dm[ps + "_yds_pg"]:.1f}<small>{ordn(dr[ps + "_yds_pg"])}</small></td>'
             f'<td class="{"pos" if dm[ps + "_epa"] < 0 else "neg"}">{sgn(dm[ps + "_epa"])}<small>{ordn(dr[ps + "_epa"])}-toughest</small></td><td class="fx">{fx}</td></tr>')
fxq = ''.join(f'<span data-fx="{fm}">{fa["QB"][i]:.1f}<small>{ordn(33 - fr_["QB"][i])}-fewest</small></span>' for i, fm in enumerate(('std', 'half', 'ppr')))
prow += f'<tr><td class="n">Quarterbacks</td><td>–</td><td>–</td><td>–</td><td class="fx">{fxq}</td></tr>'
seg = '<div class="dbd-seg t-tabs dbd-fxseg" role="tablist" aria-label="Fantasy scoring"><span class="t-tabs-pill" aria-hidden="true"></span>' + ''.join(f'<button class="t-tab" type="button" role="tab" data-fxset="{k}" aria-selected="{"true" if k == "half" else "false"}">{v}</button>' for k, v in (('std', 'Standard'), ('half', 'Half PPR'), ('ppr', 'PPR'))) + '</div>'
whorows = ''.join(f'<tr><td class="n">{logo(w[2])}{esc(w[1])}</td><td>{w[2]}</td><td>{w[5]}</td><td>{w[3]}</td><td>{w[6] or ""}</td><td class="{"pos" if w[4] > 0 else "neg"}">{sgn(w[4], 1)}</td></tr>' for w in who)
pane_pos = (f'<section class="dbd-panel dbd-pane" data-pane="2" hidden><div class="dbd-ph"><span class="dbd-lab"><b>By position</b><span class="sub">what each position does against them, the ranks the player pages quote</span></span>{seg}</div>'
            f'<div class="dbd-tw"><table class="dbd-stat dbd-dpos"><thead><tr><th>Position</th><th>Targets a game</th><th>Yards a game</th><th>EPA per target</th><th>Fantasy points allowed</th></tr></thead><tbody>{prow}</tbody></table></div>'
            f'<h4 class="dbd-h4">Who hurt them most</h4><div class="dbd-tw"><table class="dbd-stat dbd-dwho"><thead><tr><th>Player</th><th>Team</th><th>Touches</th><th>Yards</th><th>TD</th><th>EPA</th></tr></thead><tbody>{whorows}</tbody></table></div>'
            '<p class="dbd-note">Targets, yards and EPA per target by the receiver\'s position, ranked 1st for the toughest defense: the same numbers a receiver\'s This week panel quotes against this defense. Fantasy points allowed follow the scoring switch. Who hurt them ranks every opposing player by EPA on his targets and carries against this defense.</p></section>')
S = d['sit']; LGS = X['LGS']
def srow(lab, k, kind):
    v, l = S[k], LGS[k]
    if kind == 'conv': return f'<tr><td class="n">{lab}</td><td>{v[0]}</td><td>{pct(v[1])}</td><td>{pct(l[1])}</td><td class="{"pos" if v[1] < l[1] else "neg"}">{"−" if v[1] < l[1] else "+"}{abs(round((v[1] - l[1]) * 100))} pts</td></tr>'
    return f'<tr><td class="n">{lab}</td><td>{v[0]}</td><td>{sgn(v[1])}</td><td>{sgn(l[1])}</td><td class="{"pos" if v[1] < l[1] else "neg"}">{sgn(v[1] - l[1])}</td></tr>'
sit_rows = (srow('1st down', 'd1', 'epa') + srow('2nd down', 'd2', 'epa') + srow('3rd down', 'd3', 'epa') + srow('3rd and 1 to 3', '3s', 'conv') + srow('3rd and 4 to 6', '3m', 'conv') + srow('3rd and 7+', '3l', 'conv') +
            srow('Red zone', 'rz', 'epa') + srow('Two-minute drill', '2m', 'epa') + srow('When they lead', 'lead', 'epa') + srow('One-score game', 'close', 'epa') + srow('When they trail by 9+', 'trail', 'epa'))
SITLAB = {'d1': '1st down', 'd2': '2nd down', 'd3': '3rd down', 'rz': 'the red zone', '2m': 'the two-minute drill', 'lead': 'games they lead', 'close': 'one-score games', 'trail': 'games they trail by 9+'}
_ep = [(k, S[k][1] - LGS[k][1]) for k in SITLAB if S[k][0] >= 20]
_cv = [(k, S[k][1] - LGS[k][1]) for k in ('3s', '3m', '3l') if S[k][0] >= 12]
_b3 = min(_cv, key=lambda x: x[1]); _w = max(_ep, key=lambda x: x[1]); _be = min(_ep, key=lambda x: x[1])
_3n = {'3s': 'third-and-short (1 to 3)', '3m': 'third-and-4-to-6', '3l': 'third-and-7+'}
SITSAY = (f'Their biggest third-down edge is <b>{_3n[_b3[0]]}</b>: offenses convert {pct(S[_b3[0]][1])} against a league {pct(LGS[_b3[0]][1])}. '
          f'They are stingiest in <b>{SITLAB[_be[0]]}</b> ({sgn(S[_be[0]][1])} a play, league {sgn(LGS[_be[0]][1])}) and closest to average in {SITLAB[_w[0]]} ({sgn(S[_w[0]][1])}, league {sgn(LGS[_w[0]][1])}).')
pane_sit = (f'<section class="dbd-panel dbd-pane" data-pane="3" hidden><div class="dbd-ph"><span class="dbd-lab"><b>Situations</b><span class="sub">by down, third-down distance, field zone, clock and score</span></span></div>'
            f'<p class="dbd-ep-say">{SITSAY}</p>'
            f'<div class="dbd-tw"><table class="dbd-stat dbd-dsit"><thead><tr><th>Situation</th><th>Plays</th><th>Allowed</th><th>League</th><th>Difference</th></tr></thead><tbody>{sit_rows}</tbody></table></div>'
            '<p class="dbd-note">EPA a play allowed, or for third downs the share converted (a first down or a touchdown). Green is better than the league for the defense. Score states are from the defense\'s side.</p></section>')
tabs = '<div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>' + ''.join(f'<button class="t-tab" type="button" role="tab" aria-selected="{"true" if i == 0 else "false"}" data-pane="{i}">{n}</button>' for i, n in enumerate(('Pass by zone', 'Runs by lane', 'By position', 'Situations'))) + '</div></div>'
band2 = f'<section class="dbd-band" id="df-beat"><h2 class="dbd-bandh">Where offenses find room<span>the field, the run lanes, the positions and the situations</span></h2><section class="dbd-panel dbd-tabbed mk-new" data-mk="New · four views of what they allow">{tabs}{pane_pass}{pane_run}{pane_pos}{pane_sit}</section></section>'

# ---------------- band 3: how they play
LGST = X['LGST']
def chip(lab, v, sub, k, hib, kind, words=('most', 'fewest')):
    vals = [D[x]['style'][k] for x in TEAMS if D[x]['style'][k] is not None]; r = rk_of(vals, v, hib)
    return f'<div class="c"><small>{lab}</small><b>{FMT[kind](v)}</b><span>{sub}</span><em>{ordn(r)}-{words[0]} of 32 · league {FMT[kind](LGST[k])}</em></div>'
FMT['x1'] = lambda v: f'{v:.1f}'
FMT['sgn1'] = lambda v: sgn(v, 1)
FMT['ypc'] = lambda v: f'{v:.1f}'
card_press = (f'<section class="dbd-panel dbd-style"><div class="dbd-ph"><span class="dbd-lab"><b>Pressure and the blitz</b> · FTN charting and PFR</span></div>'
              f'<p class="dbd-wp-say">The most aggressive defense in football. They send extra rushers on <b>{pct(st["blitz"])}</b> of dropbacks and average <b>{st["rush"]:.1f}</b> rushers; only {pct(st["four"])} of dropbacks see four or fewer. It works both ways: <b>{sgn(st["e_bz"])}</b> EPA allowed when they blitz, <b>{sgn(st["e_nb"])}</b> when they do not.</p>'
              '<div class="dbd-wpchips">' + chip('Blitz rate', st['blitz'], 'of dropbacks', 'blitz', True, 'pct') + chip('Pass rushers', st['rush'], 'a dropback', 'rush', True, 'x1') +
              chip('EPA when blitzing', st['e_bz'], 'per dropback', 'e_bz', False, 'sgn', ('best',)) + chip('EPA without', st['e_nb'], 'per dropback', 'e_nb', False, 'sgn', ('best',)) + '</div></section>')
card_box = (f'<section class="dbd-panel dbd-style"><div class="dbd-ph"><span class="dbd-lab"><b>The box and the run</b> · defenders near the line on runs</span></div>'
            f'<p class="dbd-wp-say">They play light: <b>{st["box"]:.1f}</b> defenders in the box on runs, eight or more on only <b>{pct(st["heavy"])}</b>. Into a light box (six or fewer) runs gain <b>{f1(st["ypc_l"])}</b> a carry; into eight or more, {f1(st["ypc_h"])}. That is where the rushing yards come from.</p>'
            '<div class="dbd-wpchips">' + chip('Box count', st['box'], 'defenders on runs', 'box', True, 'x1') + chip('Eight or more', st['heavy'], 'of runs', 'heavy', True, 'pct') +
            chip('YPC, light box', st['ypc_l'], '6 or fewer', 'ypc_l', False, 'ypc', ('best',)) + chip('YPC, heavy box', st['ypc_h'], '8 or more', 'ypc_h', False, 'ypc', ('best',)) + '</div></section>')
card_off = (f'<section class="dbd-panel dbd-style"><div class="dbd-ph"><span class="dbd-lab"><b>What offenses try against them</b> · the plan they draw</span></div>'
            f'<p class="dbd-wp-say">Offenses lean on the run against them: <b>{abs(st["proe"]) * 100:.0f} points under</b> their expected pass rate on early downs in a neutral game. Play action finds a little room ({sgn(st["e_pa"])} EPA, {pct(st["pa"])} of throws); screens find more ({sgn(st["e_scr"])}), though offenses rarely call them.</p>'
            '<div class="dbd-wpchips">' + chip('Pass rate vs expected', st['proe'], 'early downs, neutral game', 'proe', False, 'pct', ('lowest',)) + chip('CPOE allowed', st['cpoe'], 'completion over expected, points', 'cpoe', False, 'sgn1', ('best',)) +
            chip('Play action faced', st['pa'], f'{sgn(st["e_pa"])} EPA allowed', 'pa', True, 'pct') + chip('Motion faced', st['mot'], f'{sgn(st["e_mot"])} EPA allowed', 'mot', True, 'pct') + '</div></section>')
pen_rk = rk_of([D[x]['pen'] for x in TEAMS], d['pen'], False); mt_rk = rk_of([D[x]['mtk'] / max(1, D[x]['tkl'] + D[x]['mtk']) for x in TEAMS], d['mtk'] / (d['tkl'] + d['mtk']), False)
card_disc = (f'<section class="dbd-panel dbd-style"><div class="dbd-ph"><span class="dbd-lab"><b>Discipline and tackling</b></span></div>'
             f'<p class="dbd-wp-say">{d["pen"]} defensive penalties for {d["pen_yds"]} yards, {d["pen_fd"]} of them first downs, the {ordn(pen_rk)}-fewest. They miss <b>{pct(d["mtk"] / (d["tkl"] + d["mtk"]))}</b> of tackle attempts by PFR\'s count, {ordn(mt_rk)}-best.</p>'
             f'<div class="dbd-wpchips"><div class="c"><small>Penalties</small><b>{d["pen"]}</b><span>{d["pen_yds"]} yards</span><em>{ordn(pen_rk)}-fewest of 32</em></div><div class="c"><small>First downs by penalty</small><b>{d["pen_fd"]}</b><span>handed to offenses</span><em>&nbsp;</em></div>'
             f'<div class="c"><small>Missed tackles</small><b>{d["mtk"]}</b><span>{pct(d["mtk"] / (d["tkl"] + d["mtk"]))} of attempts</span><em>{ordn(mt_rk)}-best of 32</em></div><div class="c"><small>Takeaways</small><b>{d["take"]}</b><span>{d["int"]} INT · {d["fum"]} fumbles</span><em>{ordn(RANK[A]["take_pg"])} per game</em></div></div></section>')
band3 = f'<section class="dbd-band" id="df-play"><h2 class="dbd-bandh">How they play<span>pressure, the box, what offenses try, discipline</span></h2><div class="dbd-stylegrid mk-new" data-mk="New · style, from FTN and PFR">{card_press}{card_box}{card_off}{card_disc}</div></section>'

# ---------------- band 4: the defenders
DF = d['defenders']; heads = {}
idx = json.load(open('../data/players/index.json'))  # offense only; defenders have no headshots in our data, so the jersey tile stands in
def jersey(num):
    return f'<span class="dbd-jt" style="background:{t["c1"]};color:#fff">{esc(str(num or ""))}</span>'
def tbl(rows, cols):
    th = ''.join(f'<th>{c[0]}</th>' for c in cols)
    tb = ''.join('<tr><td class="n">' + jersey(r['num']) + f'<b>{esc(r["name"])}</b><small>{esc(r["pos"])}</small></td>' + ''.join(f'<td>{c[1](r)}</td>' for c in cols[1:]) + '</tr>' for r in rows)
    return f'<div class="dbd-tw"><table class="dbd-stat dbd-ddef"><thead><tr>{th}</tr></thead><tbody>{tb}</tbody></table></div>'
z = lambda v: '' if not v else (f'{v:g}' if isinstance(v, float) else str(v))
edge = lambda r: r['grp'] == 'DL' or r['pos'] in ('OLB', 'DE', 'EDGE') or (r['grp'] == 'LB' and r['prs'] > r['tgt'])  # a linebacker who rushes more than he covers plays on the front
front = [r for r in DF if edge(r)][:9]
lbs = [r for r in DF if r['grp'] == 'LB' and not edge(r)][:6]
sec = [r for r in DF if r['grp'] == 'DB'][:8]
p_front = tbl(front, [('Player', None), ('Snaps', lambda r: f'{r["snap"]}%'), ('Pressures', lambda r: z(r['prs'])), ('Sacks', lambda r: z(r['sk'])), ('QB hits', lambda r: z(r['qbh'])), ('TFL', lambda r: z(r['tfl'])), ('Tackles', lambda r: z(r['tkl'])), ('FF', lambda r: z(r['ff']))])
p_lb = tbl(lbs, [('Player', None), ('Snaps', lambda r: f'{r["snap"]}%'), ('Tackles', lambda r: z(r['tkl'])), ('TFL', lambda r: z(r['tfl'])), ('Sacks', lambda r: z(r['sk'])), ('Pressures', lambda r: z(r['prs'])), ('Targets', lambda r: z(r['tgt'])), ('Rating allowed', lambda r: f1(r['rate']) if r['rate'] is not None else ''), ('Missed', lambda r: z(r['mt']))])
p_sec = tbl(sec, [('Player', None), ('Snaps', lambda r: f'{r["snap"]}%'), ('Targets', lambda r: z(r['tgt'])), ('Catches allowed', lambda r: f'{r["cmp"]} of {r["tgt"]}' if r['tgt'] else ''), ('Yards', lambda r: z(r['yds'])), ('TD', lambda r: z(r['tda'])), ('Rating allowed', lambda r: f1(r['rate']) if r['rate'] is not None else ''), ('INT', lambda r: z(r['int'])), ('PD', lambda r: z(r['pd']))])
top_rush = max(front + lbs, key=lambda r: r['prs']); top_cov = min((r for r in sec if r['tgt'] >= 15), key=lambda r: r['rate'] or 999)
dtabs = '<div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span>' + ''.join(f'<button class="t-tab" type="button" role="tab" aria-selected="{"true" if i == 0 else "false"}" data-pane="{i}">{n}</button>' for i, n in enumerate(('The front', 'Linebackers', 'The secondary'))) + '</div></div>'
def pane(i, lab, body, say):
    return f'<section class="dbd-panel dbd-pane" data-pane="{i}"{" hidden" if i else ""}><div class="dbd-ph"><span class="dbd-lab"><b>{lab}</b><span class="sub">season lines, by snap share</span></span></div><p class="dbd-ep-say">{say}</p>{body}</section>'
band4 = (f'<section class="dbd-band" id="df-who"><h2 class="dbd-bandh">The defenders<span>who plays, and what each one has done</span></h2><section class="dbd-panel dbd-tabbed mk-new" data-mk="New · the defenders">{dtabs}'
         + pane(0, 'The front', p_front, f'<b>{esc(top_rush["name"])}</b> leads the pass rush with <b>{top_rush["prs"]}</b> pressures and {z(top_rush["sk"])} sacks on {top_rush["snap"]}% of the snaps.')
         + pane(1, 'Linebackers', p_lb, f'<b>{esc(lbs[0]["name"])}</b> plays {lbs[0]["snap"]}% of the snaps with {lbs[0]["tkl"]} tackles and {z(lbs[0]["sk"])} sacks.')
         + pane(2, 'The secondary', p_sec, f'<b>{esc(top_cov["name"])}</b> is the hardest to complete a pass against: a <b>{f1(top_cov["rate"])}</b> passer rating on {top_cov["tgt"]} targets.')
         + '<p class="dbd-note">Tackles, sacks, QB hits, TFL, interceptions, passes defended and forced fumbles are nflverse\'s weekly stats; pressures, targets, catches and yards allowed, passer rating allowed and missed tackles are PFR\'s charting. Snap share is the season average of defensive snaps.</p></section></section>')

# ---------------- band 5: every snap they defended (the existing explorer, defense mode)
band5 = ('<section class="dbd-band" id="df-snaps"><h2 class="dbd-bandh">Every snap they defended<span>the play explorer, on offenses\' plays against them</span></h2>'
         '<div class="mk-new" data-mk="New · the explorer in defense mode"><div id="dbd-explorer" data-team="MIN"><div class="dbd-skel"><span class="dbd-note">Loading every snap…</span></div></div></div></section>')

main_html = '<main class="dbd-content" style="--dbd-team:' + t['c1'] + '">' + title + jump + tiles + short + band1 + band2 + band3 + band4 + band5 + '</main>'
a = root.index('<main'); b = root.index('</main>') + len('</main>')
out = root[:a] + main_html + root[b:]
out = out.replace('Vikings offense</b>', 'Vikings defense</b>').replace('dbd-view-team"', 'dbd-view-team dbd-defview"')
cfg = json.load(open('src/dbd_team_cfg.json')); cfg['dataUrl'] = 'data/MIN-def.json'; cfg['side'] = 'def'
SHELL = open('shell.html').read()
open('def-MIN.html', 'w').write(SHELL.replace('%TITLE%', 'Vikings defense · mock').replace('%ROOT%', out).replace('%CFG%', json.dumps(cfg)))
print('ok; worst zone', worst, 'best', best, 'worst lane', worst_lane, 'held', held)
