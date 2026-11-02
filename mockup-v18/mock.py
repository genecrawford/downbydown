"""Mock for SCOPE §43: the polish release. One review page: readable URLs, the new share cards (game before and after,
team, page) drawn at 1200 x 630 beside the live player card, link previews, the skeleton reveal running on Jefferson's
real target map, remembered choices, and the two new admin boxes. Every number is the site's published data (../data).
Writes polish.html. python3 -I mock.py"""
import json, html, math, random
esc = html.escape
D = '../data'
T = json.load(open(f'{D}/teams.json')); S = json.load(open(f'{D}/schedule.json')); M = json.load(open(f'{D}/metrics.json'))
WK = json.load(open(f'{D}/week/w8.json')); BOX = json.load(open(f'{D}/box/w8.json')); MINI = json.load(open(f'{D}/players/mini.json'))
CW = S['current_week']; THRU = M['through_week']; SEASON = S.get('season', 2026)

def ordn(n): n = int(n); return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
def logo(t, px): return f'<img src="{T[t]["logo"]}" alt="" style="width:{px}px;height:{px}px;object-fit:contain">'
def nick(t): return T[t]['nick']
def games_all(): return [g for w in sorted(S['weeks'], key=int) for g in S['weeks'][w]]
def record(t, upto):
    w = l = ti = pf = pa = 0
    for g in games_all():
        if g['week'] > upto or g['status'] != 'final' or t not in (g['away'], g['home']): continue
        us, them = (g['away_score'], g['home_score']) if g['away'] == t else (g['home_score'], g['away_score'])
        pf += us; pa += them
        if us > them: w += 1
        elif us < them: l += 1
        else: ti += 1
    return w, l, ti, pf - pa
def rec_txt(t, upto): w, l, ti, _ = record(t, upto); return f'{w}-{l}' + (f'-{ti}' if ti else '')
def div_place(t, upto):
    mates = [x for x in T if T[x]['div'] == T[t]['div']]
    key = lambda x: (lambda r: (-(r[0] + r[2] / 2) / max(1, r[0] + r[1] + r[2]), -r[3]))(record(x, upto))
    return sorted(mates, key=key).index(t) + 1
def kick(g):
    h, m = map(int, g['time'].split(':')); ap = 'PM' if h >= 12 else 'AM'; h12 = h - 12 if h > 12 else h
    import datetime; d = datetime.date.fromisoformat(g['date'])
    return d.strftime('%a %b ') + str(d.day), f'{h12}:{m:02d} {ap}'
def line(g):
    sp = g.get('spread')
    if sp is None: return ''
    fav = g['home'] if sp > 0 else g['away']
    return f'{fav} −{abs(sp):g} · O/U {g["total"]:g}'

# ------------------------------------------------------------------ the card frame, shared by every card (the player card's measurements)
def frame(body, meta_html, rule, foot_right):
    return (f'<div class="cv"><div class="card">'
            f'<div class="c-top"><img class="c-brand" src="assets/card-logo.png" alt="Down by Down"><div class="c-meta">{meta_html}</div></div>'
            f'<div class="c-rule" style="background:{rule}"></div>{body}'
            f'<div class="c-foot"><b>downbydown.com</b><span>{esc(foot_right)}</span></div></div></div>')
def bar(pc, color):
    """the rank bar: red to grey to green, dashed median, a marker in the team color"""
    m = '' if pc is None else f'<i class="mk" style="left:{pc:.1f}%;background:{color}"></i>'
    return f'<span class="c-bar"><i class="md"></i>{m}</span>'
def rank_row(lab, rank, color, n=32, sub=''):
    pc = (n - rank) / (n - 1) * 100
    cls = 'good' if rank <= 8 else ('bad' if rank >= 25 else '')
    return f'<div class="c-rr"><span class="l">{esc(lab)}{f"<small>{esc(sub)}</small>" if sub else ""}</span>{bar(pc, color)}<b class="{cls}">{ordn(rank)}</b></div>'

# ------------------------------------------------------------------ game card, final: Cardinals 33, Cowboys 30 (OT), the week's wildest upset
G = next(g for g in S['weeks']['8'] if g['id'] == '2026_08_ARI_DAL'); WG = next(g for g in WK['games'] if g['id'] == G['id'])
a, h = G['away'], G['home']; win = WG['win']
def team_row(t, score, won, upto):
    cls = 'win' if won else ('lose' if won is False else '')
    place = div_place(t, upto)
    return (f'<div class="c-team {cls}"><span class="lg">{logo(t, 78)}</span><span class="nm"><b>{esc(nick(t).upper())}</b>'
            f'<small>{rec_txt(t, upto)} · {ordn(place)} in the {esc(T[t]["div"])}</small></span>'
            + (f'<span class="sc">{score}</span>' if score is not None else '') + '</div>')
spark = WG['spark']; n = len(spark)
pts = ' '.join(f'{i / (n - 1) * 100:.2f},{100 - v / 10:.2f}' for i, v in enumerate(spark))
jump = max(range(1, n), key=lambda i: abs(spark[i] - spark[i - 1]))
jx, jy = jump / (n - 1) * 100, 100 - spark[jump] / 10
low = min(spark) / 10
wc = T[win]['c1']
chart = (f'<div class="c-wp"><div class="plot"><svg viewBox="0 0 100 100" preserveAspectRatio="none"><line x1="0" x2="100" y1="50" y2="50" class="mid"/>'
         f'<polyline points="0,100 {pts} 100,100" class="area" style="fill:{wc}"/><polyline points="{pts}" class="ln" style="stroke:{wc}"/></svg>'
         f'<span class="c-wpdot" style="left:{jx:.1f}%;top:{jy:.1f}%;background:{wc}"></span>'
         f'<span class="c-wplab" style="left:{min(jx, 74):.1f}%;top:{max(jy - 18, 4):.1f}%">{esc(WG["big"][1])} · +{WG["big"][0]}%</span>'
         f'</div><span class="c-wpax t">100%</span><span class="c-wpax m">50%</span><span class="c-wpax b">0%</span></div>')
def leaders(gid):
    rows = []
    for side in ('away', 'home'):
        for r in BOX['games'][gid][side]['off']: rows.append((BOX['games'][gid][side]['team'], r))
    K = BOX['keys']; ix = {k: i for i, k in enumerate(K)}
    P = max(rows, key=lambda x: x[1][ix['passing_yards']]); R = max(rows, key=lambda x: x[1][ix['rushing_yards']]); C = max(rows, key=lambda x: x[1][ix['receiving_yards']])
    td = lambda n: f' · {n} TD' if n else ''
    nm = lambda r: MINI.get(r[0], [r[1]])[0]
    return [('Passing', P[0], nm(P[1]), f'{P[1][ix["completions"]]}/{P[1][ix["attempts"]]} · {P[1][ix["passing_yards"]]} yds{td(P[1][ix["passing_tds"]])}'),
            ('Rushing', R[0], nm(R[1]), f'{R[1][ix["carries"]]} car · {R[1][ix["rushing_yards"]]} yds{td(R[1][ix["rushing_tds"]])}'),
            ('Receiving', C[0], nm(C[1]), f'{C[1][ix["receptions"]]} rec · {C[1][ix["receiving_yards"]]} yds{td(C[1][ix["receiving_tds"]])}')]
tiles = ''.join(f'<div class="c-tile"><small>{lab.upper()}</small><b>{esc(nm)}</b><span>{logo(t, 18)} {esc(line_)}</span></div>' for lab, t, nm, line_ in leaders(G['id']))
tags = ''.join(f'<span class="c-tag{" up" if k == "upset" else ""}">{esc(txt)}</span>' for k, txt in WG['tags'])
body = (f'<div class="c-left"><p class="c-kick">Week 8 · {esc(G["stadium"])} · {esc(line(G))}</p>'
        f'{team_row(a, G["away_score"], a == win, 8)}{team_row(h, G["home_score"], h == win, 8)}<div class="c-tags">{tags}</div></div>'
        f'<div class="c-right"><p class="c-head">Win probability · {esc(nick(win))}</p>{chart}'
        f'<p class="c-cap">{esc(nick(win))} were down to {low:.0f}% · biggest swing {esc(WG["big"][1])}, +{WG["big"][0]}%</p></div>'
        f'<div class="c-tiles3">{tiles}</div>')
CARD_FINAL = frame(body, f'{logo(a, 34)}{logo(h, 34)}<span>{SEASON} · WEEK 8 · FINAL{" / OT" if WG["ot"] else ""}</span>',
                   f'linear-gradient(90deg,{T[a]["c1"]} 50%,{T[h]["c1"]} 50%)', 'Data: nflverse · win probability from the play-by-play')

# ------------------------------------------------------------------ game card, before kickoff: Bears at Seahawks, Monday night
U = next(g for g in S['weeks']['8'] if g['id'] == '2026_08_CHI_SEA'); ua, uh = U['away'], U['home']
RO, RD = M['rank']['off'], M['rank']['def']; MO, MD = M['off'], M['defense']
def edge(o, d):
    """the matchup's lead takeaway: the stat where this offense is strongest against this defense's weakness"""
    tpl = {'rz_td': ('score a touchdown on {v}% of red zone trips', '{v}%'), 'third': ('convert {v}% of third downs', '{v}%'),
           'expl': ('hit an explosive play on {v}% of snaps', '{v}%'), 'pass_epa': ('gain {v} EPA a dropback', '{v}'), 'run_epa': ('gain {v} EPA a run', '{v}')}
    best = max(tpl, key=lambda m: min(33 - RO[o][m], RD[d][m]))  # both sides must favor the offense
    f = (lambda x: f'{x * 100:.0f}') if best in ('rz_td', 'third', 'expl') else (lambda x: f'{x:+.2f}')
    s1, s2 = tpl[best]
    return min(33 - RO[o][best], RD[d][best]), f'{nick(o)} {s1.format(v=f(MO[o][best]))} ({ordn(RO[o][best])}). {nick(d)} allow {s2.format(v=f(MD[d][best]))} ({ordn(RD[d][best])}).'
(k1, e1), (k2, e2) = edge(ua, uh), edge(uh, ua)
if k2 > k1: e1, e2 = e2, e1  # the stronger edge of the two leads
rows = (f'<p class="c-sub">{esc(nick(ua))} with the ball</p>' + rank_row(f'{nick(ua)} offense', RO[ua]['epa'], T[ua]['c1'], sub='EPA a play')
        + rank_row(f'{nick(uh)} defense', RD[uh]['epa'], T[uh]['c1'], sub='EPA a play allowed')
        + f'<p class="c-sub">{esc(nick(uh))} with the ball</p>' + rank_row(f'{nick(uh)} offense', RO[uh]['epa'], T[uh]['c1'], sub='EPA a play')
        + rank_row(f'{nick(ua)} defense', RD[ua]['epa'], T[ua]['c1'], sub='EPA a play allowed'))
day, tm = kick(U)
body = (f'<div class="c-left"><p class="c-kick">{esc(U["day"])} night · {esc(day)} · {tm} ET</p>'
        f'{team_row(ua, None, None, 7)}<p class="c-at">at</p>{team_row(uh, None, None, 7)}'
        f'<p class="c-line">{esc(line(U))} · {esc(U["stadium"])}</p></div>'
        f'<div class="c-right roomy"><p class="c-head">The matchup · ranks among 32</p>{rows}</div>'
        f'<div class="c-edges"><div class="c-edge"><small>The edge</small><p>{esc(e1)}</p></div><div class="c-edge b"><small>And the other way</small><p>{esc(e2)}</p></div></div>')
CARD_PRE = frame(body, f'{logo(ua, 34)}{logo(uh, 34)}<span>{SEASON} · WEEK 8 · {esc(U["day"].upper())} {tm} ET</span>',
                 f'linear-gradient(90deg,{T[ua]["c1"]} 50%,{T[uh]["c1"]} 50%)', 'Data: nflverse · ranks through week 8')

# ------------------------------------------------------------------ team card: the Vikings, 31st on offense and 1st on defense
t = 'MIN'; w_, l_, ti_, pd = record(t, THRU); place = div_place(t, THRU)
nxt = next(g for g in games_all() if g['status'] != 'final' and t in (g['away'], g['home']))
nd, nt = kick(nxt); nopp = nxt['home'] if nxt['away'] == t else nxt['away']
TT = [('Offense', f'{MO[t]["epa"]:+.2f}', f'EPA a play · {ordn(RO[t]["epa"])}'), ('Defense', f'{MD[t]["epa"]:+.2f}', f'EPA a play allowed · {ordn(RD[t]["epa"])}'),
      ('Point diff', f'{pd:+d}', f'in {w_ + l_ + ti_} games'), ('Next', nopp, ('home' if nxt['home'] == t else 'away') + f' · {nd}')]
tiles = ''.join(f'<div class="c-tile"><small>{lab.upper()}</small><b class="big">{esc(v)}</b><span>{esc(s)}</span></div>' for lab, v, s in TT)
ML = [('epa', 'EPA a play'), ('sr', 'Success rate'), ('expl', 'Explosive plays'), ('rz_td', 'Red-zone TDs')]
ranks = ('<p class="c-sub">Offense</p>' + ''.join(rank_row(lab, RO[t][k], T[t]['c1']) for k, lab in ML)
         + '<p class="c-sub">Defense · 1st allows the least</p>' + ''.join(rank_row(lab, RD[t][k], T[t]['c1']) for k, lab in ML))
city = T[t]['name'][: -len(T[t]['nick'])].strip()
body = (f'<div class="c-left"><div class="c-hero"><span class="c-mark">{logo(t, 132)}</span><div><p class="c-kick">{esc(T[t]["div"])} · {rec_txt(t, THRU)} · {ordn(place)} in the division</p>'
        f'<h1 class="c-name"><span>{esc(city.upper())}</span><span class="r">{esc(nick(t).upper())}</span></h1></div></div><div class="c-tiles4">{tiles}</div></div>'
        f'<div class="c-right tight"><p class="c-head">Where they rank · among 32 teams</p>{ranks}</div>')
CARD_TEAM = frame(body, f'{logo(t, 34)}<span>{SEASON} SEASON · THROUGH WEEK {THRU}</span>', T[t]['c1'], 'Data: nflverse · ranks among 32 teams')

# ------------------------------------------------------------------ page cards: the week in review, and the hub
fin = [g for g in WK['games'] if g['status'] == 'final']; left = len(WK['games']) - len(fin)
ups = [g for g in fin if any(k == 'upset' for k, _ in g['tags'])]; ots = [g for g in fin if g['ot']]; cbs = [g for g in fin if any(k == 'comeback' for k, _ in g['tags'])]
def score_txt(g):
    w = g['win']; l = g['home'] if w == g['away'] else g['away']; ws = g['a'] if w == g['away'] else g['h']; ls = g['h'] if w == g['away'] else g['a']
    return f'{nick(w)} {ws}, {nick(l)} {ls}'
wild = max(fin, key=lambda g: g['drama'])
def field(big, inner=''):
    yl = ''.join(f'<i style="top:{p}%"></i>' for p in range(10, 100, 10))
    return f'<div class="c-field">{yl}<span style="font-size:{300 if len(big) <= 2 else 150}px">{esc(big)}</span>{inner}</div>'
def scoreboard():
    rows = ''
    for g in sorted(WK['games'], key=lambda g: (g['status'] != 'final', g['date'], g['time'])):
        if g['status'] != 'final':
            rows += f'<li class="up"><b>{g["away"]}</b><em></em><b>{g["home"]}</b><em></em><small>{esc(g["day"][:3])} {kick(g)[1]}</small></li>'; continue
        aw = g['win'] == g['away']
        rows += (f'<li><b class="{"w" if aw else ""}">{g["away"]}</b><em class="{"w" if aw else ""}">{g["a"]}</em>'
                 f'<b class="{"" if aw else "w"}">{g["home"]}</b><em class="{"" if aw else "w"}">{g["h"]}</em><small>{"OT" if g["ot"] else "Final"}</small></li>')
    return f'<ul class="c-board">{rows}</ul>'
def passes():
    import os
    dots = ''; rnd2 = random.Random(8); n_ = 0
    for fn in sorted(os.listdir(f'{D}/plays')):
        if not fn[:-5].isalpha() or len(fn) > 8: continue
        Pz = json.load(open(f'{D}/plays/{fn}')); Kz = Pz['keys']
        for r in Pz['plays']:
            r = dict(zip(Kz, r))
            if r['pt'] != 'pass' or not r['g'].startswith('2026_08') or r['air'] is None or not r['loc']: continue
            col = {'left': (0, 33.3), 'middle': (33.3, 66.6), 'right': (66.6, 100)}[r['loc']]
            x = col[0] + 3 + rnd2.random() * (col[1] - col[0] - 6); y = 78 - max(-8, min(45, r['air'])) * 1.55
            c = '#4fd18b' if r['td'] else ('#5b9cf6' if r['k'] == 'comp' else 'rgba(255,255,255,.35)')
            dots += f'<i class="d" style="left:{x:.1f}%;top:{y:.1f}%;background:{c}"></i>'; n_ += 1
    return f'<div class="c-dots">{dots}</div><p class="c-fcap">Every pass of week {CW}: {n_} throws</p>'
WT = [('Finals', str(len(fin)), f'{left} still to play' if left else 'all played'), ('Upsets', str(len(ups)), min(ups, key=lambda g: g['pre_w']) and f'{nick(min(ups, key=lambda g: g["pre_w"])["win"])} from {min(ups, key=lambda g: g["pre_w"])["pre_w"] / 10:.0f}%'),
      ('Wildest', f'{wild["a"]}–{wild["h"]}' if False else score_txt(wild).split(',')[0].split(' ')[-1] + ' ' + str(max(wild['a'], wild['h'])), score_txt(wild) + (' (OT)' if wild['ot'] else ''))]
WT[2] = ('Wildest', f'{max(wild["a"], wild["h"])}–{min(wild["a"], wild["h"])}', score_txt(wild) + (' in OT' if wild['ot'] else ''))
tiles = ''.join(f'<div class="c-tile"><small>{lab.upper()}</small><b class="big">{esc(v)}</b><span>{esc(s)}</span></div>' for lab, v, s in WT)
body = (f'<div class="c-left wide"><p class="c-kick red">{SEASON} season · week 8</p><h1 class="c-title"><span>WEEK 8</span><span class="r">IN REVIEW</span></h1>'
        f'<p class="c-say">{len(ups)} upsets, {len(cbs)} comebacks and {len(ots)} overtimes: the plays that swung the week, and who moved.</p><div class="c-tiles3 in">{tiles}</div></div>'
        f'{field("8", scoreboard())}')
CARD_WEEK = frame(body, f'<span>{SEASON} SEASON · THROUGH WEEK {THRU}</span>', 'var(--red)', 'Data: nflverse')
top_o = min(RO, key=lambda x: RO[x]['epa']); top_d = min(RD, key=lambda x: RD[x]['epa'])
HT = [('Finals', str(len(fin)), f'week {CW} · {left} tonight' if left else f'week {CW} · all played'), ('Top offense', top_o, f'{MO[top_o]["epa"]:+.2f} EPA a play'), ('Top defense', top_d, f'{MD[top_d]["epa"]:+.2f} EPA a play allowed')]
tiles = ''.join(f'<div class="c-tile"><small>{lab.upper()}</small><b class="big">{esc(v)}</b><span>{esc(s)}</span></div>' for lab, v, s in HT)
body = (f'<div class="c-left wide"><p class="c-kick red">{SEASON} season · week {CW}</p><h1 class="c-title sm"><span>EVERY SNAP,</span><span>DRAWN ON THE</span><span class="r">FIELD.</span></h1>'
        f'<div class="c-tiles3 in">{tiles}</div></div>{field("", passes())}')
CARD_HUB = frame(body, f'<span>{SEASON} SEASON · THROUGH WEEK {THRU}</span>', 'var(--red)', 'Data: nflverse')

CARDS = [('Game, final', 'Shared after the game: the score, how win probability moved, and who did what. It replaces the preview card the moment the game goes final.', CARD_FINAL, 'new'),
         ('Game, before kickoff', "Shared before the game: kickoff, the line, each side's EPA rank and the matchup's lead takeaway, the same sentence the hub uses.", CARD_PRE, 'new'),
         ('Team', 'Record and division place, both sides of the ball with their ranks, point differential and the next game.', CARD_TEAM, 'new'),
         ('Page: week in review', 'One template for every other page. The week page counts the upsets, comebacks and overtimes.', CARD_WEEK, 'new'),
         ('Page: hub', 'The hub, matchups, teams, players and leaders share the brand card with this week in three tiles.', CARD_HUB, 'new'),
         ('Player (live today)', 'The card that ships now, for comparison: the new cards share its bar, type, tiles and footer.', '<div class="cv img"><img src="assets/player-card-live.png" alt="Live player card"></div>', 'live')]
cards_html = ''.join(f'<figure class="m-card {k}"><figcaption><b>{esc(t_)}</b> {esc(d_)}</figcaption>{c}</figure>' for t_, d_, c, k in CARDS)

# ------------------------------------------------------------------ link previews
PRE_SM = CARD_PRE.replace('class="cv"', 'class="cv sm"'); TEAM_SM = CARD_TEAM.replace('class="cv"', 'class="cv sm"')
preview = (f'<div class="m-prev"><div class="m-imsg"><p class="who">Text message</p><div class="bub">{PRE_SM}'
           f'<div class="cap"><b>Bears at Seahawks, Week 8 | Down by Down</b><span>downbydown.com</span></div></div></div>'
           f'<div class="m-slack"><p class="who">Slack</p><div class="un"><i></i><div><small>Down by Down</small><a>Minnesota Vikings | Down by Down</a>'
           f'<p>Every Vikings offensive snap of the 2026 season on the field: run and pass by down, distance, field zone and formation, and how they rank.</p>'
           f'{TEAM_SM}</div></div></div></div>')

# ------------------------------------------------------------------ readable URLs
URLS = [('Hub', '/', '/'), ('Team, offense', '/?team=MIN', '/team/min/'), ('Team, defense', '/?team=MIN&side=def', '/team/min/defense/'),
        ("Team's players", '/?view=players&team=MIN', '/team/min/players/'), ('Game', '/?game=2026_08_MIN_DET', '/game/2026-week-8-vikings-at-lions/'),
        ('Game, home offense', '/?game=2026_08_MIN_DET&side=home', '/game/2026-week-8-vikings-at-lions/lions/'), ('Player', '/?player=00-0036322', '/player/justin-jefferson/'),
        ('Compare', '/?compare=00-0036322,00-0036900,00-0039075', '/compare/justin-jefferson/jamarr-chase/puka-nacua/'), ('Players', '/?view=players', '/players/'),
        ('Leaders', '/?view=leaders&g=rec', '/leaders/rec/'), ('Week in review', '/?view=week&week=8', '/week/8/'), ('Matchups', '/?view=matchups&week=8', '/matchups/week-8/'),
        ('Teams', '/?view=teams', '/teams/'), ('My team', '/?view=myteam', '/my-team/'), ('Share card', '/?card=00-0036322', '/card/player/justin-jefferson.png')]
url_rows = ''.join(f'<tr><td>{esc(a_)}</td><td class="old">{esc(b_)}</td><td class="arr">→</td><td class="new">{esc(c_)}</td></tr>' for a_, b_, c_ in URLS)
urls = (f'<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Every page gets a path</b> · the old links answer with a 301 and keep working</span></div>'
        f'<div class="m-addr"><span class="bar"><i></i><span class="dm">downbydown.com</span><b>/?player=00-0036322</b></span><span class="hop">301</span><span class="bar on"><i></i><span class="dm">downbydown.com</span><b>/player/justin-jefferson/</b></span></div>'
        f'<div class="dbd-tw"><table class="m-urls"><thead><tr><th>Page</th><th>Today</th><th></th><th>Readable</th></tr></thead><tbody>{url_rows}</tbody></table></div>'
        f'<p class="dbd-note">Player slugs come from the build, so PHP and the scripts agree; no two of the 576 players collide today. Game slugs carry the season, so the season archive needs nothing new for games. '
        f'A site on plain permalinks keeps the query strings.</p></section>')

# ------------------------------------------------------------------ the skeleton reveal, on Jefferson's real target map
P = json.load(open(f'{D}/plays/MIN.json')); K = P['keys']; R = [dict(zip(K, r)) for r in P['plays']]
J = [r for r in R if r['pid'] == '00-0036322' and r['pt'] == 'pass']
rnd = random.Random(18)
xs = {'left': (34, 139.3), 'middle': (139.3, 244.7), 'right': (244.7, 350)}
grid = ''.join(f'<line x1="34" x2="350" y1="{y}" y2="{y}" stroke="rgba(255,255,255,.22)" stroke-width="{2 if y == 320 else 1}"/><text x="28" y="{y + 4}" fill="rgba(255,255,255,.75)" font-size="10" text-anchor="end">{lab}</text>'
               for y, lab in ((390, -10), (320, 0), (250, 10), (180, 20), (110, 30), (40, 40)))
grid += '<text x="28" y="16" fill="rgba(255,255,255,.75)" font-size="10" text-anchor="end">air yds</text>'
grid += ''.join(f'<text x="{(a0 + b0) / 2:.1f}" y="410" fill="rgba(255,255,255,.75)" font-size="11" text-anchor="middle">{n0.title()}</text>' for n0, (a0, b0) in xs.items())
grid += ''.join(f'<line x1="{x}" x2="{x}" y1="40" y2="390" stroke="rgba(255,255,255,.22)" stroke-dasharray="2 4"/>' for x in (139.3, 244.7))
dots = ''
for r in J:
    if not r['loc'] or r['air'] is None: continue
    a0, b0 = xs[r['loc']]; x = a0 + 10 + rnd.random() * (b0 - a0 - 20); y = 320 - max(-10, min(42, r['air'])) * 7
    col = '#4fd18b' if r['td'] else ('#5b9cf6' if r['k'] == 'comp' else '#9a96a6'); rr = 7 if r['td'] else 5.5
    dots += f'<circle cx="{x:.1f}" cy="{y:.0f}" r="{rr}" fill="{col}" fill-opacity=".9" stroke="rgba(0,0,0,.35)"/>'
cnt = {k: [0, 0] for k in xs}
for r in J:
    if r['loc']: cnt[r['loc']][0] += 1; cnt[r['loc']][1] += r['k'] == 'comp'
grid += ''.join(f'<text x="{(a0 + b0) / 2:.1f}" y="16" fill="#fff" font-size="11" font-weight="600" text-anchor="middle">{cnt[n0][1]}/{cnt[n0][0]} · {round(cnt[n0][1] / max(1, cnt[n0][0]) * 100)}%</text>' for n0, (a0, b0) in xs.items())
MAP = f'<svg viewBox="0 0 360 420" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Target map">{grid}{dots}</svg>'
bands = [('Behind the line', lambda a_: a_ < 0), ('Short, 0 to 9', lambda a_: 0 <= a_ < 10), ('Medium, 10 to 19', lambda a_: 10 <= a_ < 20), ('Deep, 20+', lambda a_: a_ >= 20)]
bd = [(n0, [r for r in J if r['air'] is not None and f(r['air'])]) for n0, f in bands]; mx = max(len(x) for _, x in bd)
BANDS = '<div class="dbd-bands">' + ''.join(f'<div class="n">{n0}</div><div class="bar"><i style="--w:{len(x) / mx * 100:.0f}%"></i><i class="c" style="--w:{sum(r["k"] == "comp" for r in x) / mx * 100:.0f}%"></i></div>'
                                            f'<div class="t">{len(x)} tgt · {sum(r["k"] == "comp" for r in x)} rec · {sum(r["yds"] or 0 for r in x if r["k"] == "comp")} yds</div>' for n0, x in bd) + '</div>'
SK_MAP = ('<div class="sk-field">' + ''.join(f'<i style="top:{p}%"></i>' for p in (17, 33, 50, 67, 83)) + ''.join(f'<b style="left:{x}%;top:{y}%"></b>' for x, y in ((22, 40), (64, 30), (80, 58), (35, 70), (52, 52), (18, 62), (73, 74))) + '</div>')
SK_BANDS = '<div class="sk-bands">' + ''.join(f'<i class="n"></i><i class="b" style="width:{w}%"></i><i class="t"></i>' for w in (55, 80, 100, 35)) + '</div>'
skel = (f'<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Where the ball finds him</b> · Justin Jefferson, from the play file</span>'
        f'<span class="m-btns"><button type="button" data-run="new" class="on">Replay 1.19.0</button><button type="button" data-run="old">Replay today</button></span></div>'
        f'<div class="m-skwrap"><div class="t-skel m-sk" id="m-sk" data-mode="new"><div class="t-skel-skeleton">'
        f'<div class="dbd-sprays"><div><h4>Targets on the field</h4>{SK_MAP}</div><div><h4>By depth of target</h4>{SK_BANDS}</div></div></div>'
        f'<div class="t-skel-content"><div class="dbd-sprays"><div><h4>Targets on the field</h4>{MAP}</div><div><h4>By depth of target</h4>{BANDS}'
        f'<p class="dbd-note" style="padding:8px 0 0">Green is the caught share of each bar.</p></div></div></div>'
        f'<div class="m-old"><span class="dbd-note">Loading…</span></div></div></div>'
        f'<p class="dbd-note">The skeleton has the shape of what is coming, pulses while the play file loads (held here for 1.6 seconds), then cross-fades with a 2px blur in the same box. "Replay today" shows the current centered "Loading…" and the jump. '
        f'Reduced motion: no pulse, an instant swap.</p></section>')

# ------------------------------------------------------------------ remembered choices
remember = ('<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Your scoring format sticks</b> · pick it once, every page uses it</span></div>'
            '<div class="m-rem">' + ''.join(f'<div class="m-pg"><small>{esc(p_)}</small><div class="dbd-seg m-fmt" role="group"><button type="button" data-f="std">Standard</button><button type="button" data-f="half">Half PPR</button><button type="button" data-f="ppr">PPR</button></div></div>'
                                       for p_ in ('Player page · Jefferson', 'Compare', 'Leaders · fantasy', 'My team')) + '</div>'
            '<p class="dbd-note">Click a format on any of the four: the others follow, as the next page you open will. The stats-table view (Default, Advanced, Traditional) is remembered the same way. Kept in this browser only; with nothing saved, pages open as they do today.</p></section>')

# ------------------------------------------------------------------ admin
admin = ('<div class="m-wp"><nav><b class="wpl">W</b>' + ''.join(f'<span{" class=on" if x_ == "Down by Down" else ""}>{x_}</span>' for x_ in ('Dashboard', 'Posts', 'Media', 'Pages', 'Comments', 'Down by Down', 'Divi', 'Appearance', 'Plugins', 'Users', 'Tools', 'Settings'))
         + '</nav><div class="wpb"><h1>Down by Down</h1><h2>Current data</h2><table class="wpt"><tr><td>Season</td><td>2026</td></tr><tr><td>Current week</td><td>8</td></tr><tr><td>Play-by-play through</td><td>Week 8</td></tr><tr><td>…</td><td>as today</td></tr></table>'
         '<div class="m-new"><span class="m-tagnew">New</span><h2>Readable URLs</h2><table class="wpf"><tr><th>Readable URLs</th><td><label><input type="checkbox" checked disabled> Use paths like <code>/team/min/</code> and <code>/player/justin-jefferson/</code></label>'
         '<p class="d">On by default. Old <code>?team=</code> and <code>?player=</code> links keep working: they redirect to the new paths. Turn this off to go back to query strings. Permalinks: <b>Post name</b>, so paths work on this site.</p></td></tr></table></div>'
         '<div class="m-new"><span class="m-tagnew">New</span><h2>Share cards</h2><table class="wpt"><tr><td>Cached</td><td>412 cards, 38.6 MB</td></tr><tr><td>Kept</td><td>This week\'s cards for 1.19.0 only. After each sync, cards from earlier weeks or versions, and any older than 14 days, are deleted.</td></tr>'
         '<tr><td>Last cleanup</td><td>Nov 2, 12:21 PM: removed 1,180 cards (109 MB)</td></tr></table><p><span class="wpbtn">Clear card cache</span></p></div>'
         '<h2>Using it in the theme</h2><ul class="wpu"><li>Put <code>[down_by_down]</code> on one page. That page becomes the app: the hub at its URL, <code>/team/min/</code> for a team, <code>/game/2026-week-8-vikings-at-lions/</code> for a matchup, <code>/player/justin-jefferson/</code> for a player … <span class="m-chg">reworded for the paths</span></li></ul>'
         '<h2>Sync log</h2><p class="d">as today</p></div></div>'
         '<p class="dbd-note">The admin already has its own sidebar entry (since 1.0.3), so the roadmap\'s "top-level admin menu" is done. The page gains the two boxes outlined; "Using it in the theme" lists the paths. The cache numbers are illustrative.</p>')

# ------------------------------------------------------------------ phone tables
phone = ('<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>The last three phone scrollers</b> · measured at 375 today</span></div><div class="dbd-tw"><table class="m-urls m-ph"><thead><tr><th>Table</th><th>Page</th><th>Content</th><th>Box</th><th>1.19.0</th></tr></thead><tbody>'
         '<tr><td>Head to head</td><td>Game (Vikings at Lions)</td><td>465px</td><td>341px</td><td class="new">folds to the phone columns</td></tr>'
         '<tr><td>By position</td><td>Team, defense</td><td>514px</td><td>341px</td><td class="new">folds to the phone columns</td></tr>'
         '<tr><td>Season stats</td><td>Player (quarterbacks)</td><td>473px</td><td>341px</td><td class="new">folds to the phone columns</td></tr></tbody></table></div>'
         '<p class="dbd-note">The same tiering the other tables use: the extra columns move under the row on a phone. The tab bars, jump links and week strip scroll sideways on purpose and stay. The drive chart\'s phone layout (parked in §5) is dropped: the game page no longer has one.</p></section>')

band = lambda t_, s_: f'<h2 class="dbd-bandh">{esc(t_)}<span>{esc(s_)}</span></h2>'
ROOT = (f'<div class="dbd dbd-view-hub"><main class="dbd-content">'
        f'<div class="dbd-ttitle"><div><p class="dbd-kicker">Roadmap 13 · 1.19.0</p><h1><em>Polish</em> release</h1><p class="dbd-sub">Readable URLs, a share card for every page, the card cache cleaned after each sync, the skeleton reveal, and three parked items: remembered choices, the last phone scrollers and the drive chart note.</p></div></div>'
        + band('1. Readable URLs', 'every page gets a path; old links redirect') + urls
        + band('2. Share cards', 'drawn at 1200 × 630 from the published data; click a card to see it full width') + f'<div class="m-cards">{cards_html}</div>'
        + band('How a link looks when shared', 'the game preview in a text, the team in Slack') + preview
        + band('3. Card cleanup and admin', 'the card cache box and the URL setting') + admin
        + band('4. The skeleton reveal', 'transitions.dev 14 on the five panels that draw after a fetch') + skel
        + band('5. Parked items', 'remembered choices and the phone tables') + remember + phone
        + '</main></div>')

SHELL = open('shell.html').read()
open('polish.html', 'w').write(SHELL.replace('%TITLE%', 'Polish release · mock').replace('%ROOT%', ROOT))
print('wrote polish.html', len(ROOT), 'bytes; lead edge:', e1, '|', e2)
