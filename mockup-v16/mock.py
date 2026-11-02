"""Mock for SCOPE §41: the hub refresh. The live 1.16.0 hub (hero, game of the week, score strip) with the new bands added: the week so
far, still to play, players (movers, fantasy leaders, My team) and the playoff picture beside the division standings. Every number is the
site's published data (../data) and the live game pages' reads. Writes hub.html (My team empty) and hub-team.html (a saved team).
python3 -I mock.py"""
import json, re, html, os
esc = html.escape
D = '../data'
T = json.load(open(f'{D}/teams.json')); S = json.load(open(f'{D}/schedule.json')); M = json.load(open(f'{D}/metrics.json'))
FX = json.load(open(f'{D}/fantasy.json')); L = json.load(open(f'{D}/players/leaders.json')); MV = json.load(open(f'{D}/players/movers.json'))
IDX = {p['id']: p for p in json.load(open(f'{D}/players/index.json'))['players']}
ROOT = open('src/root.html').read()
WK = S['current_week']; games = S['weeks'][str(WK)]
nick = lambda a: T[a]['nick']
def ordn(n): n = int(n); return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
def tile(a, s=24):
    return f'<span class="wk-mk"><span class="dbd-lgw" style="width:{s}px;height:{s}px"><img class="dbd-logo" src="{T[a]["logo"]}" alt="" width="{s}" height="{s}" style="width:{s}px;height:{s}px;object-fit:contain"></span></span>'
def head(team, num, photo, s=24):
    c = T[team]['c1']
    svg = (f'<svg class="dbd-mk dbd-jersey" width="{s}" height="{s}" viewBox="0 0 40 40"><rect width="40" height="40" rx="5" fill="{c}"/><text x="20" y="27" text-anchor="middle" font-size="19" font-family="Tourney,Arial Black,sans-serif" font-weight="800" fill="#fff">{esc(str(num or ""))}</text></svg>')
    img = f'<img src="{esc(photo.replace("f_auto,q_auto", "f_auto,q_auto,w_48,h_48,c_fill,g_face"))}" alt="" width="{s}" height="{s}" loading="lazy" referrerpolicy="no-referrer" style="background:{c}" onerror="this.remove()">' if photo else ''
    return f'<span class="dbd-head" style="width:{s}px;height:{s}px">{svg}{img}</span>'

# ---------------------------------------------------------------- records and point differential (from the schedule's finals)
rec = {a: [0, 0, 0, 0] for a in T}  # w, l, t, point diff
for gl in S['weeks'].values():
    for g in gl:
        if g['status'] != 'final' or g.get('game_type', 'REG') != 'REG': continue
        a, h, sa, sh = g['away'], g['home'], g['away_score'], g['home_score']
        rec[a][3] += sa - sh; rec[h][3] += sh - sa
        if sa > sh: rec[a][0] += 1; rec[h][1] += 1
        elif sh > sa: rec[h][0] += 1; rec[a][1] += 1
        else: rec[a][2] += 1; rec[h][2] += 1
rtxt = lambda a: f'{rec[a][0]}-{rec[a][1]}' + (f'-{rec[a][2]}' if rec[a][2] else '')
pct = lambda a: (rec[a][0] + rec[a][2] / 2) / max(1, sum(rec[a][:3]))
key = lambda a: (pct(a), rec[a][3])

M_ = ' mk-new" data-mk="'
def mk(label): return f'{M_}{esc(label)}'

# ---------------------------------------------------------------- band A: the week so far (or last week)
cur_fin = sum(g['status'] == 'final' for g in games); RN = WK if cur_fin else WK - 1
W = json.load(open(f'{D}/week/w{RN}.json'))
fin = [g for g in W['games'] if g['status'] == 'final' and 'pre' in g]
up_ = [g for g in fin if g.get('win') and g['pre_w'] < 500]; upg = min(up_, key=lambda g: g['pre_w']) if up_ else None
cbg = min([g for g in fin if g.get('win')], key=lambda g: g['low'])
loser = lambda g: g['home'] if g['win'] == g['away'] else g['away']
score = lambda g: f'{max(g["a"], g["h"])}–{min(g["a"], g["h"])}'
star = max((l for p in W['players'].values() for l in p), key=lambda l: l['e'])
lines = []
wild = max(fin, key=lambda g: (g.get('lc', 0), g.get('drama', 0)))
if upg and cbg and upg['id'] == cbg['id']:  # one game was both: say it once, and make room for the wildest
    lines.append(('Upset', f'<b>{nick(upg["win"])}</b> over the {nick(loser(upg))}, {score(upg)}: a {round(upg["pre_w"] / 10)}% underdog at kickoff, and down to {max(1, round(upg["low"] / 10))}% before winning.'))
    if wild['id'] == upg['id']: wild = sorted(fin, key=lambda g: (g.get('lc', 0), g.get('drama', 0)))[-2]
    lines.append(('Wildest', f'<b>{nick(wild["away"])} at {nick(wild["home"])}</b>: the lead changed hands {wild.get("lc", 0)} times; ' + (f'the {nick(wild["win"])} won {score(wild)}.' if wild.get('win') else 'it ended tied.')))
else:
    if upg: lines.append(('Upset', f'<b>{nick(upg["win"])}</b> over the {nick(loser(upg))}, {score(upg)}, as a {round(upg["pre_w"] / 10)}% underdog.'))
    lines.append(('Comeback', f'<b>{nick(cbg["win"])}</b> back from {max(1, round(cbg["low"] / 10))}% against the {nick(loser(cbg))}, {score(cbg)}.'))
lines.append(('Player', f'<b>{esc(star["n"])}</b> ({star["t"]} {star["pos"]}): {("+" if star["e"] > 0 else "")}{star["e"]:.1f} EPA, {esc(star["x"])}.'))
short = '<ul class="hb-say">' + ''.join(f'<li><span>{a}</span><p>{b}</p></li>' for a, b in lines) + '</ul>'
sw = ''.join(f'<li><span class="rk">{i + 1}</span><span class="wk-pd"><span class="wk-pm">{tile(x["t"], 16)}<b>{nick(x["t"])}</b> · {x["c"]}</span><span class="wk-pt">{esc(x["d"])}</span></span><span class="wk-pv"><b>+{round(x["v"])}</b><small>{x["ba"]}% → {min(100, round(x["ba"] + x["v"]))}%</small></span></li>' for i, x in enumerate(W['swings'][:3]))
done = W['fin'] == W['n']
bandA = (f'<section class="dbd-band" id="hb-week"><h2 class="dbd-bandh">{"The week so far" if not done else "Last week"}<span>Week {RN}: {W["fin"]} of {W["n"]} games final</span></h2>'
         f'<section class="dbd-panel hb-week{mk("New · the week so far, from the week in review")}"><div class="hb-week2"><div><div class="dbd-ph"><span class="dbd-lab"><b>The short version</b>{" · so far" if not done else ""}</span></div>{short}</div>'
         f'<div><div class="dbd-ph"><span class="dbd-lab"><b>The biggest swings</b></span></div><ol class="ld-list wk-pl">{sw}</ol></div></div>'
         f'<a class="hb-more" href="?view=week&week={RN}">Week {RN} in review: every game, the best plays, teams and players →</a></section></section>')

# ---------------------------------------------------------------- band B: still to play
def edge_read(g):
    best = (0, g['away'], g['home'])
    for O, Dd in ((g['away'], g['home']), (g['home'], g['away'])):
        for grp in ('pass_epa', 'sack', 'run_epa', 'ypc', 'third', 'rz_td', 'WR_yds_pg', 'TE_yds_pg', 'TE_tgt_pg', 'RB_yds_pg'):
            orr = M['rank']['off'].get(O, {}).get(grp); dr = M['rank']['def'].get(Dd, {}).get(grp)
            if not orr or not dr: continue
            s = ((33 - orr) / 32 + dr / 32) / 2
            if s > best[0]: best = (s, O, Dd)
    side = 'away' if best[1] == g['away'] else 'home'
    h = open(f'src/game-{g["id"]}-{side}.html').read(); i = h.find('Biggest edge')
    h3 = re.search(r'<h3>(.*?)</h3>', h[i:]).group(1); p = re.search(r'<p>(.*?)</p>', h[i:]).group(1)
    return best, html.unescape(h3), html.unescape(p)
def fx_chip(g):
    best = None
    for O, Dd in ((g['away'], g['home']), (g['home'], g['away'])):
        for pos in ('QB', 'RB', 'WR', 'TE'):
            rk = FX['rank'].get(Dd, {}).get(pos, [None, None, None])[1]
            if rk and (best is None or rk < best[0]): best = (rk, O, Dd, pos)
    rk, O, Dd, pos = best
    return f'<b>{nick(O)} {pos}s</b>: the {nick(Dd)} allow the {ordn(rk)}-most {pos} points'
gotw_id = re.search(r'game=(\d{4}_\d{2}_[A-Z]+_[A-Z]+)[^"]*"[^>]*>Open the matchup', ROOT)
gotw_id = gotw_id.group(1) if gotw_id else None
cards = ''
for g in games:
    if g['status'] == 'final' or g['id'] == gotw_id: continue
    (s, O, Dd), h3, p = edge_read(g)
    h_, m_ = map(int, g['time'].split(':')); kt = f'{(h_ - 1) % 12 + 1}:{m_:02d} {"PM" if h_ >= 12 else "AM"} ET'
    line = (f'{g["home"]} -{abs(g["spread"]):g}' if g['spread'] > 0 else f'{g["away"]} -{abs(g["spread"]):g}') + f' · O/U {g["total"]:g}'
    rows = ''.join(f'<div class="hb-tm">{tile(a)}<b>{nick(a)}</b><small>{rtxt(a)}</small><span>OFF {ordn(M["rank"]["off"][a]["epa"])} · DEF {ordn(M["rank"]["def"][a]["epa"])}</span></div>' for a in (g['away'], g['home']))
    cards += (f'<a class="hb-g" href="?game={g["id"]}"><div class="hb-gh"><span>{esc(g["day"])} · {kt}</span><span>{line}</span></div>{rows}'
              f'<div class="hb-edge"><i>Biggest edge · {nick(O)} offense</i><b>{esc(h3)}</b><p>{esc(p)}</p></div><div class="hb-fx"><i>Fantasy</i> {fx_chip(g)}</div><span class="hb-open">Open the matchup →</span></a>')
bandB = (f'<section class="dbd-band" id="hb-next"><h2 class="dbd-bandh">Still to play<span>Week {WK}: the games left after the game of the week</span></h2>'
         f'<div class="hb-grid{mk("New · a card per game left to play")}">{cards}</div>'
         '<p class="dbd-note hb-note">Ranks are EPA a play this season, 1 to 32. The edge is the matchup\'s biggest gap between one offense and the other defense, as on the game page; the fantasy line is the softest position matchup in the game, in half-PPR points allowed.</p></section>')

# ---------------------------------------------------------------- band C: players
def mvrow(r, up):
    return f'<li><span class="rk">{"▲" if up else "▼"}</span><span class="who">{head(r["team"], r["num"], r.get("photo"))}<span class="nm"><a href="?player={r["id"]}">{esc(r["name"])}</a><small>{r["team"]} · {r["pos"]} · {round(r["before"])}% → {round(r["now"])}%</small></span></span><b class="v {"up" if up else "dn"}">{"+" if up else "−"}{round(abs(r["delta"]))}</b></li>'
mvr = ''.join(mvrow(r, True) for r in MV['snaps']['up'][:4]) + ''.join(mvrow(r, False) for r in MV['snaps']['down'][:4])
mcard = f'<section class="dbd-panel ld-card"><div class="ld-h"><b>Movers · Week {MV["week"]}</b><small>the biggest swings in snap share, against his own season</small></div><ol class="ld-list hb-mvl">{mvr}</ol><a class="hb-more" href="?view=players#dbd-mv">All movers →</a></section>'
fxb = ''
for i, fm in enumerate(('std', 'half', 'ppr')):
    li = ''
    for pos in ('QB', 'RB', 'WR', 'TE'):
        for r in L['fan'][fm][f'ppg.{pos}'][:2]:
            p = IDX.get(r[0]) or {}
            li += f'<li><span class="rk">{pos}</span><span class="who">{head(p.get("team", "MIN"), p.get("num"), p.get("photo"))}<span class="nm"><a href="?player={r[0]}">{esc(p.get("name", r[0]))}</a><small>{p.get("team", "")} · {r[2]} games</small></span></span><b class="v">{r[1]:.1f}</b></li>'
    fxb += f'<div data-fx="{fm}"><ol class="ld-list hb-fxl">{li}</ol></div>'
seg = '<div class="dbd-seg t-tabs dbd-fxseg" role="tablist" aria-label="Fantasy scoring"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" data-fxset="std" aria-selected="false">Standard</button><button class="t-tab" type="button" role="tab" data-fxset="half" aria-selected="true">Half PPR</button><button class="t-tab" type="button" role="tab" data-fxset="ppr" aria-selected="false">PPR</button></div>'
fcard = f'<section class="dbd-panel ld-card"><div class="ld-h wk-fxh"><span><b>Fantasy leaders</b><small>points a game, the top two at each position</small></span>{seg}</div>{fxb}<a class="hb-more" href="?view=leaders">All the boards →</a></section>'
# My team: the hub ships a small map of skill players (name, team, position, this week's opponent) so the card can draw a saved team
opp = {}
for g in games: opp[g['away']] = ('at', g['home'], g['status'] == 'final'); opp[g['home']] = ('vs', g['away'], g['status'] == 'final')
def mt_rows(ids):
    out = ''
    for pid in ids:
        p = IDX[pid]; o = opp.get(p['team'])
        st = 'bye' if not o else (f'{o[0]} {o[1]} · final' if o[2] else f'{o[0]} {o[1]}')
        out += f'<li><span class="who">{head(p["team"], p.get("num"), p.get("photo"))}<span class="nm"><a href="?player={pid}">{esc(p["name"])}</a><small>{p["team"]} · {p["pos"]}</small></span></span><span class="hb-mto{" bye" if not o else ""}">{st}</span></li>'
    return out
SAMPLE = ['00-0034857', '00-0038543', '00-0036322', '00-0039075', '00-0037239', '00-0036358']
SAMPLE = [x for x in SAMPLE if x in IDX][:6]
tcard_empty = ('<section class="dbd-panel ld-card hb-mt"><div class="ld-h"><b>My team</b><small>your lineup, kept on this device</small></div>'
               '<div class="hb-mte"><p>Pick your players once and see them together every week: this week\'s matchup, status, snap trend and points pace.</p><a class="dbd-cta" href="?view=myteam">Build my team</a></div></section>')
tcard_full = (f'<section class="dbd-panel ld-card hb-mt"><div class="ld-h"><b>My team</b><small>{len(SAMPLE)} players · this week</small></div><ol class="ld-list hb-mtl">{mt_rows(SAMPLE)}</ol>'
              '<a class="hb-more" href="?view=myteam">Open My team →</a></section>')
def bandC(tcard):
    return (f'<section class="dbd-band" id="hb-players"><h2 class="dbd-bandh">Players<span>whose role moved, who leads, and your team</span></h2>'
            f'<div class="ld-grid hb-players{mk("New · movers, fantasy leaders, My team")}">{mcard}{fcard}{tcard}</div></section>')

# ---------------------------------------------------------------- band D: the playoff picture beside the division standings
def picture(conf):
    teams = [a for a in T if T[a]['conf'] == conf]
    leaders = []
    for d in ('East', 'North', 'South', 'West'):
        dv = [a for a in teams if T[a]['div'] == f'{conf} {d}']; leaders.append(max(dv, key=key))
    leaders.sort(key=key, reverse=True)
    rest = sorted([a for a in teams if a not in leaders], key=key, reverse=True)
    wc = rest[:3]; seven = wc[-1]
    gb = lambda a: ((rec[seven][0] - rec[a][0]) + (rec[a][1] - rec[seven][1])) / 2
    gbt = lambda x: 'tied with the 7th seed' if not x else ((str(int(x)) if x >= 1 else '') + ('½' if x % 1 else '') + (' game' if x <= 1 else ' games') + ' back')
    hunt = [a for a in rest[3:] if gb(a) <= 1]; out_ = [a for a in rest[3:] if gb(a) > 1]
    def row(seed, a, tag):
        pd = rec[a][3]
        return (f'<tr><td class="sd">{seed}</td><td class="dbd-nm"><span class="dbd-nmi">{tile(a)}<a href="?team={a}"><span class="full">{T[a]["name"]}</span><span class="short">{nick(a)}</span></a><small>{tag}</small></span></td>'
                f'<td class="dbd-rec">{rtxt(a)}</td><td class="pd {"pos" if pd > 0 else ("neg" if pd < 0 else "")}">{"+" if pd > 0 else ""}{pd}</td></tr>')
    body = ''.join(row(i + 1, a, T[a]['div'].split(' ')[1] + ' leader') for i, a in enumerate(leaders))
    body += ''.join(row(i + 5, a, 'wild card') for i, a in enumerate(wc))
    body += '<tr class="dbd-dv"><td colspan="4">In the hunt · within one game of the 7th seed</td></tr>'
    body += ''.join(row('', a, gbt(gb(a))) for a in hunt) or '<tr><td colspan="4" class="dbd-note">Nobody within a game.</td></tr>'
    return (f'<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>{conf}</b> · if the season ended today</span><span class="dbd-badge">Seeds 1 to 7</span></div>'
            f'<div class="dbd-tw"><table class="dbd-stand hb-pic"><thead><tr><th>Seed</th><th>Team</th><th>Record</th><th>Diff</th></tr></thead><tbody>{body}</tbody></table></div>'
            f'<p class="hb-out">Outside: {", ".join(f"{nick(a)} {rtxt(a)}" for a in out_)}</p></section>')
st_html = ROOT[ROOT.index('<div class="dbd-two" id="dbd-teams">'):ROOT.index('</main>')]
tabs = '<div class="dbd-ph dbd-tabh"><div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" aria-selected="true" data-pane="0">Playoff picture</button><button class="t-tab" type="button" role="tab" aria-selected="false" data-pane="1">Divisions</button></div></div>'
bandD = (f'<section class="dbd-band" id="hb-stand"><h2 class="dbd-bandh">Standings<span>the playoff picture today, and every division</span></h2><section class="dbd-panel dbd-tabbed hb-stand{mk("New · the playoff picture tab")}">{tabs}'
         f'<section class="dbd-pane" data-pane="0"><div class="dbd-two">{picture("AFC")}{picture("NFC")}</div><p class="dbd-note">Seeds go by record, then point differential: the four division leaders first, then the three best records left. The NFL\'s own tiebreakers (head to head, division and conference record) are not applied, so a tie on record can land the other way. Diff is points scored minus allowed.</p></section>'
         f'<section class="dbd-pane" data-pane="1" hidden>{st_html}</section></section></section>')

# ---------------------------------------------------------------- assemble
a = ROOT.index('<p class="dbd-slatelink">'); b = ROOT.index('</main>')
slatelink = ROOT[a:ROOT.index('</p>', a) + 4]
SHELL = open('shell.html').read()
for name, tc, cap in (('hub.html', tcard_empty, 'My team empty'), ('hub-team.html', tcard_full, 'a saved team')):
    main = ROOT[:a] + slatelink + bandA + bandB + bandC(tc) + bandD + ROOT[b:]
    open(name, 'w').write(SHELL.replace('%TITLE%', f'Hub refresh · mock ({cap})').replace('%ROOT%', main))
print('week band', RN, 'done' if done else 'so far', '| still to play', cards.count('class="hb-g"'), '| gotw', gotw_id)
for c in ('AFC', 'NFC'):
    tm = [a for a in T if T[a]['conf'] == c]; print(c, sorted(tm, key=key, reverse=True)[:9])
