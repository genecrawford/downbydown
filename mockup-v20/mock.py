"""Mock for SCOPE §45: a marketing home page, second pass (fuller, no play-explorer, share-card or season-kept sections).
Built from the site's published data (../data): the week's scoreboard, highlights, the most-targeted receiver's map, the game of the
week, target risers, fantasy, leaders and the 32 teams. The page is Gene's to build in Divi; each live piece is a plugin block.
Writes home.html. python3 -I mock.py"""
import json, html, random, collections, datetime, re
esc = html.escape
D = '../data'
T = json.load(open(f'{D}/teams.json')); S = json.load(open(f'{D}/schedule.json')); M = json.load(open(f'{D}/metrics.json'))
MINI = json.load(open(f'{D}/players/mini.json')); FX = json.load(open(f'{D}/fantasy.json')); MAN = json.load(open(f'{D}/manifest.json'))
LD = json.load(open(f'{D}/players/leaders.json')); MV = json.load(open(f'{D}/players/movers.json'))
season = S['season']; fin_w = max(int(w) for w, gl in S['weeks'].items() if any(g['status'] == 'final' for g in gl))
WK = json.load(open(f'{D}/week/w{fin_w}.json')); pre = f'{season}_{fin_w:02d}_'

# ---------------------------------------------------------------- helpers
logo = lambda t, px: f'<img class="lg" src="{T[t]["logo"]}" alt="{esc(T[t]["name"])}" width="{px}" height="{px}" loading="lazy">'
PHB = 'https://static.www.nfl.com/image/upload/f_auto,q_auto,w_96,h_96,c_fill,g_face/league/'
def head(pid, px):
    m = MINI.get(pid)
    return f'<img class="hd" src="{PHB}{m[4]}" alt="" width="{px}" height="{px}" loading="lazy">' if m and m[4] else f'<span class="hd ph" style="width:{px}px;height:{px}px"></span>'
nm = lambda pid: MINI[pid][0] if pid in MINI else pid
def ordn(n): n = int(n); return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
rec = collections.defaultdict(lambda: [0, 0, 0])
for gl in S['weeks'].values():
    for g in gl:
        if g['status'] != 'final' or g.get('game_type', 'REG') != 'REG': continue
        a, h = g['away'], g['home']
        if g['away_score'] > g['home_score']: rec[a][0] += 1; rec[h][1] += 1
        elif g['home_score'] > g['away_score']: rec[h][0] += 1; rec[a][1] += 1
        else: rec[a][2] += 1; rec[h][2] += 1
rt = lambda t: f'{rec[t][0]}-{rec[t][1]}' + (f'-{rec[t][2]}' if rec[t][2] else '')

# ---------------------------------------------------------------- the week's plays, for the hero map and the counts
plays = []; snaps = 0
for t in T:
    p = json.load(open(f'{D}/plays/{t}.json')); K = p['keys']
    for r in p['plays']:
        snaps += 1; r = dict(zip(K, r))
        if r['g'].startswith(pre): plays.append(r)
passes = [r for r in plays if r['pt'] == 'pass' and r.get('pid')]
star, n_t = collections.Counter(r['pid'] for r in passes).most_common(1)[0]; st = [r for r in passes if r['pid'] == star]

def field(rows, seed=7, dot=5.5):
    rnd = random.Random(seed); xs = {'left': (34, 139.3), 'middle': (139.3, 244.7), 'right': (244.7, 350)}
    g = ''.join(f'<line x1="34" x2="350" y1="{y}" y2="{y}" stroke="rgba(255,255,255,.22)" stroke-width="{2 if y == 320 else 1}"/><text x="28" y="{y + 4}" fill="rgba(255,255,255,.7)" font-size="10" text-anchor="end">{lab}</text>'
                for y, lab in ((390, -10), (320, 0), (250, 10), (180, 20), (110, 30), (40, 40)))
    g += ''.join(f'<line x1="{x}" x2="{x}" y1="40" y2="390" stroke="rgba(255,255,255,.18)" stroke-dasharray="2 4"/>' for x in (139.3, 244.7))
    g += ''.join(f'<text x="{x}" y="410" fill="rgba(255,255,255,.7)" font-size="11" text-anchor="middle">{l}</text>' for x, l in ((86.7, 'Left'), (192, 'Middle'), (297.3, 'Right')))
    for r in rows:
        if not r.get('loc') or r.get('air') is None: continue
        a0, b0 = xs[r['loc']]; x = a0 + 8 + rnd.random() * (b0 - a0 - 16); y = 320 - max(-10, min(42, r['air'])) * 7
        col = '#4fd18b' if r.get('td') else ('#5b9cf6' if r['k'] == 'comp' else '#9a96a6')
        g += f'<circle cx="{x:.1f}" cy="{y:.0f}" r="{dot + (1.5 if r.get("td") else 0)}" fill="{col}" fill-opacity=".92" stroke="rgba(0,0,0,.35)"/>'
    return f'<svg viewBox="0 0 360 420" width="100%" style="display:block;background:#2c4a35;border-radius:8px" role="img">{g}</svg>'

# ---------------------------------------------------------------- 1. hero, with the week's scoreboard under it
comp = sum(1 for r in st if r['k'] == 'comp'); yds = sum(r['yds'] or 0 for r in st if r['k'] == 'comp'); tds = sum(1 for r in st if r.get('td'))
sp = MINI[star]
tags = {g['id']: [k for k, _ in g.get('tags', [])] for g in WK['games']}
def chip(g):
    a, h = g['away'], g['home']; fin = g['status'] == 'final'; win = g.get('win')
    tg = tags.get(g['id'], []); lab = 'Upset' if 'upset' in tg else ('OT' if g.get('ot') else ('Comeback' if 'comeback' in tg else ''))
    row = lambda t, s: f'<span class="r{" w" if fin and win == t else ""}">{logo(t, 18)}<b>{t}</b><em>{"" if s is None else s}</em></span>'
    nx = '' if fin else '<i class="nx">Next</i>'
    k = '' if fin else f'<span class="up">{esc(g["day"][:3])} {esc(g["time"])}</span>'
    return f'<a class="sc{" live" if not fin else ""}">{nx}{row(a, g.get("a"))}{row(h, g.get("h"))}<span class="st">{"Final" if fin else ""}{(" · " + lab) if lab else ""}{k}</span></a>'
# every game of the week in a wrapping grid, games still to play first (no sideways scroll)
games_grid = ('<div class="h-games"><div class="h-games-h"><b>Week ' + str(fin_w) + '</b><span>' + str(sum(1 for g in WK['games'] if g['status'] == 'final')) + ' final · ' + str(sum(1 for g in WK['games'] if g['status'] != 'final')) + ' to play</span><a>Every matchup →</a></div>'
              '<div class="h-games-g">' + ''.join(chip(g) for g in sorted(WK['games'], key=lambda g: g['status'] == 'final')) + '</div><p class="blkw"><span class="blk">block: games type=grid</span></p></div>')
hero = (f'<section class="h-hero-wrap" data-yd="20"><div class="h-hero"><div class="h-copy">'
        f'<h1>Every NFL snap, <span>drawn on the field.</span></h1>'
        f'<p class="lede">Down by Down charts every play of every game from public play-by-play: where each team throws and runs, how every offense matches up against every defense, and what it all means for the players you start. Free, no account, updated three times a day.</p>'
        f'<div class="ctas"><a class="btn pri">See this week →</a><a class="btn ghost">Pick your team</a></div>'
        f'</div>'
        f'<figure class="h-map"><figcaption><span class="who">{head(star, 46)}<span><b>{esc(sp[0])}</b><small>{esc(sp[2])} · {esc(T[sp[1]]["nick"])} · most targets in week {fin_w}</small></span></span></figcaption>'
        f'<div class="stats"><span><b>{n_t}</b>targets</span><span><b>{comp}</b>caught</span><span><b>{yds}</b>yards</span><span><b>{tds}</b>TD</span></div>'
        f'{field(st, seed=3)}<p class="leg"><i style="background:#5b9cf6"></i>caught <i style="background:#9a96a6"></i>incomplete <i style="background:#4fd18b"></i>touchdown <a>His page →</a></p>'
        f'<p class="blkw"><span class="blk">block: hero-map</span></p></figure></div><div class="h-yards" aria-hidden="true">' + ''.join(f'<span style="left:{(i + 1) * 10}%">{"<em class=l>◀</em>" if i < 4 else ""}{n // 10}<i></i>0{"<em class=r>▶</em>" if i > 4 else ""}</span>' for i, n in enumerate((10, 20, 30, 40, 50, 40, 30, 20, 10))) + f'</div>{games_grid}</section>')

def dm(down, dist, spot):
    sup = {1: 'st', 2: 'nd', 3: 'rd', 4: 'th'}[down]
    return f'<span class="dm"><span class="dm-b"><b>{down}</b><sup>{sup}</sup></span><span class="dm-t"><b>&amp; {esc(dist)}</b><small>{esc(spot)}</small></span></span>'

# ---------------------------------------------------------------- 2. the numbers
finals = sum(1 for gl in S['weeks'].values() for g in gl if g['status'] == 'final')
nums_old = (f'<section class="h-nums"><div><b>{snaps:,}</b><span>snaps charted this season</span></div><div><b>{finals}</b><span>games broken down</span></div>'
        f'<div><b>{len(MINI)}</b><span>players with their own page</span></div><div><b>32</b><span>offenses and defenses ranked</span></div><div><b>3×</b><span>updates a day in season</span></div>'
        f'<p class="blkw"><span class="blk">block: counts</span></p></section>')

nums = (f'<p class="h-count"><b>{snaps:,}</b> snaps charted this season · <b>{finals}</b> games · <b>{len(MINI)}</b> players · <b>32</b> offenses and defenses <span class="blk">block: counts</span></p>')

# ---------------------------------------------------------------- 3. this week in one minute
tp = WK['plays'][0]; sw = WK['swings'][0]
ups = [g for g in WK['games'] if 'upset' in tags.get(g['id'], [])]
wild = max((g for g in WK['games'] if g['status'] == 'final'), key=lambda g: g.get('drama', 0))
def sline(g):
    w = g['win']; l = g['home'] if w == g['away'] else g['away']; ws = g['a'] if w == g['away'] else g['h']; ls = g['h'] if w == g['away'] else g['a']
    return w, l, ws, ls
wW, wL, wWs, wLs = sline(wild)
ups_html = ''.join(f'<li>{logo(sline(g)[0], 22)}<span><b>{esc(T[sline(g)[0]]["nick"])} {sline(g)[2]}</b>, {esc(T[sline(g)[1]]["nick"])} {sline(g)[3]}</span> <small>{round(g["pre_w"] / 10)}% at kickoff</small></li>' for g in ups)
week = (f'<section class="h-week" data-yd="25"><div class="h-sec-h">{dm(1, "10", "Own 25")}<p class="kick">Week {fin_w}, in one minute</p><h2>What happened this week</h2><a class="more">The full week in review →</a></div><div class="h-week-g">'
        f'<article class="wk play"><i>The play of the week</i>{logo(tp["t"], 40)}<h3>{esc(tp["d"].split(" [")[0])}</h3><p><b>+{tp["e"]:.2f} EPA</b> · {esc(T[tp["t"]]["nick"])} vs {esc(T[tp["o"]]["nick"])}, {esc(tp["c"])}</p></article>'
        f'<article class="wk swing"><i>The swing of the week</i>{logo(sw["t"], 40)}<h3>{esc(sw["d"])}</h3><p><b>+{sw["v"]:.0f}%</b> win probability for the {esc(T[sw["t"]]["nick"])}, {esc(sw["c"])}</p></article>'
        f'<article class="wk ups"><i>Upsets</i><ul>{ups_html}</ul><p class="wild"><span>Wildest game</span>{logo(wW, 22)} <b>{esc(T[wW]["nick"])} {wWs}, {esc(T[wL]["nick"])} {wLs}</b>{" in overtime" if wild.get("ot") else ""}</p></article>'
        f'</div><p class="blkw"><span class="blk">block: week-highlights · from the week in review</span></p></section>')

# ---------------------------------------------------------------- 4. features: matchups, players, fantasy
RO, RD = M['rank']['off'], M['rank']['def']; MO, MD = M['off'], M['defense']
H2 = [('epa', 'EPA a play', lambda v: f'{v:+.2f}'), ('sr', 'Success rate', lambda v: f'{v * 100:.0f}%'), ('expl', 'Explosive plays', lambda v: f'{v * 100:.0f}%'), ('rz_td', 'Red-zone TDs', lambda v: f'{v * 100:.0f}%')]
def h2h(o, d):
    rows = ''
    for k, lab, f in H2:
        ro, rd = RO[o][k], RD[d][k]; edge = (33 - ro) - (33 - rd)  # positive favours the offense
        w = min(50, abs(edge) * 1.6)
        rows += (f'<div class="hr"><span class="o">{f(MO[o][k])}<small>{ordn(ro)}</small></span><span class="lab">{esc(lab)}<span class="bar"><i class="{"o" if edge > 0 else "d"}" style="width:{w:.0f}%;--to:{T[o]["c1"]};--td:{T[d]["c1"]}"></i></span></span>'
                 f'<span class="d">{f(MD[d][k])}<small>{ordn(rd)}</small></span></div>')
    return f'<div class="h-h2h"><div class="hh">{logo(o, 26)}<b>{esc(T[o]["nick"])} offense</b><span>vs</span><b>{esc(T[d]["nick"])} defense</b>{logo(d, 26)}</div>{rows}</div>'
gotw = ('<div class="h-gotw"><p class="k">Game of the week · Monday 8:15 PM ET · Lumen Field</p><div class="mt"><span>' + logo('CHI', 54) + f'<b>Bears</b><small>{rt("CHI")}</small></span><em>at</em><span>' + logo('SEA', 54) + f'<b>Seahawks</b><small>{rt("SEA")}</small></span></div>'
        '<p class="ln">SEA −3 · O/U 46.5</p><p class="say"><i>Biggest edge</i>Bears give up 149 yards a game to wide receivers (19th), against a league average of 147. Seahawks\' wide receivers produce 185 (4th).</p><a class="mini">Open the matchup →</a></div>')
movers = MV['targets']['up'][:4]
rise = ''.join(f'<li>{head(m["id"], 40)}<span><b>{esc(m["name"])}</b><small>{esc(m["pos"])} · {esc(T[m["team"]]["nick"])}</small></span><em>{m["before"]:.0f}% <i>→</i> <strong>{m["now"]:.0f}%</strong></em></li>' for m in movers)
soft = []
for pos in ('QB', 'RB', 'WR', 'TE'):
    t = min(FX['rank'], key=lambda x: FX['rank'][x][pos][1]); soft.append((pos, t, FX['allow'][t][pos][1]))
ppr = LD['fan']['ppr']['tot'][:5]
pprl = ''.join(f'<li><span class="n">{i + 1}</span>{head(r[0], 34)}<span><b>{esc(nm(r[0]))}</b><small>{esc(MINI[r[0]][2])} · {esc(T[MINI[r[0]][1]]["nick"])}</small></span><em>{r[1]:.1f}</em></li>' for i, r in enumerate(ppr))
def feat(n, kick, title, body, cta, live, flip=False, tone='', mark='', yd=0):
    return (f'<section class="h-feat {tone}{" flip" if flip else ""}" data-yd="{yd}"><div class="txt">{mark}<p class="kick">{esc(kick)}</p><h2>{esc(title)}</h2>{body}<a class="btn">{esc(cta)} →</a></div>'
            f'<div class="h-live">{live}</div></section>')
f1 = feat(1, 'Matchups', 'Every matchup, read for you',
          '<p>Every game puts one offense against the other defense, metric by metric. You get the biggest edge, the opening it can attack and what to watch, in plain sentences with the ranks behind them.</p><ul class="pts"><li>Head to head on 20 metrics</li><li>Where each defense is weakest, by zone</li><li>Lines, totals and how the game turned</li></ul>',
          "This week's matchups", gotw + h2h('CHI', 'SEA') + '<p class="blkw"><span class="blk">block: matchup · the live hub\'s game of the week</span></p>', mark=dm(2, '2', 'Own 33'), yd=33)
f2 = feat(2, 'Players', 'Every player, in context',
          '<p>Where the ball finds him, how he grades against his position, how his weeks are trending and who he faces next. Receivers, backs, tight ends and quarterbacks, with a page each.</p><ul class="pts"><li>Every target and carry on the field</li><li>League percentiles on the stats that matter</li><li>Snap, target and EPA movers every week</li></ul>',
          'Find a player', '<img class="card" src="assets/card-player.png" alt="Justin Jefferson\'s share card"><div class="h-rise"><p class="k">Rising this week · share of targets</p><ul>' + rise + '</ul></div>'
          '<p class="blkw"><span class="blk">block: player-card</span> <span class="blk">block: movers</span></p>', True, 'tint', dm(1, '10', 'Own 41'), 41)
f3 = feat(3, 'Fantasy', 'Fantasy with the why',
          '<p>Standard, half and full PPR, with the matchup behind every number: which defenses give up the most to each position, and who on your lineup draws them. Build your lineup once and the site keeps it.</p><ul class="pts"><li>Points allowed by position, every defense</li><li>Floor, ceiling and boom weeks</li><li>My team: your lineup, this week\'s matchups</li></ul>',
          'Build My team', '<div class="h-soft">' + ''.join(f'<div class="s">{logo(t, 38)}<span><i>{pos}</i><b>{esc(T[t]["nick"])}</b><small>allow {v:.1f} pts a game, most in the NFL</small></span></div>' for pos, t, v in soft)
          + '</div><div class="h-ppr"><p class="k">PPR points, season leaders</p><ol>' + pprl + '</ol></div><p class="blkw"><span class="blk">block: fantasy</span> <span class="blk">block: leaders type=ppr</span></p>', mark=dm(2, '1', 'Midfield'), yd=50)

# ---------------------------------------------------------------- 5. leaders
def board(title, rows, fmt, sub):
    li = ''.join(f'<li><span class="n">{i + 1}</span>{head(r[0], 32)}<span><b>{esc(nm(r[0]))}</b><small>{esc(T[MINI[r[0]][1]]["nick"])}</small></span><em>{fmt(r[1])}</em></li>' for i, r in enumerate(rows[:5]))
    return f'<div class="lb"><h3>{esc(title)}</h3><p class="sub">{esc(sub)}</p><ol>{li}</ol></div>'
q = lambda rows: [r for r in rows if len(r) < 4 or r[3]]
leaders = ('<section class="h-leaders" data-yd="62"><div class="h-sec-h">' + dm(1, '10', 'Opp 38') + '<p class="kick">Leaders</p><h2>Who is actually good</h2><a class="more">Every leaderboard →</a></div><div class="h-lbs">'
           + board('Target share', q(LD['groups']['wr']['g']['tshare']), lambda v: f'{v * 100:.1f}%', 'wide receivers, share of team targets')
           + board('EPA per target', q(LD['groups']['wr']['g']['epat']), lambda v: f'{v:+.2f}', 'wide receivers, value added per look')
           + board('EPA per dropback', q(LD['groups']['qb']['g']['epadb']), lambda v: f'{v:+.2f}', 'quarterbacks, sacks and scrambles included')
           + '</div><p class="blkw"><span class="blk">block: leaders</span></p></section>')

# ---------------------------------------------------------------- 6. teams by division
divs = ''
for c in ('AFC', 'NFC'):
    for d in ('East', 'North', 'South', 'West'):
        k = f'{c} {d}'; ts = sorted([t for t in T if T[t]['div'] == k], key=lambda t: (-(rec[t][0] + rec[t][2] / 2), rec[t][1]))
        divs += f'<div class="dv"><h4>{esc(k)}</h4>' + ''.join(f'<a style="--c:{T[t]["c1"]}">{logo(t, 30)}<span>{esc(T[t]["nick"])}</span><em>{rt(t)}</em></a>' for t in ts) + '</div>'
teams = (f'<section class="h-teams-s" data-yd="69"><div class="h-sec-h">{dm(2, "3", "Opp 31")}<p class="kick">All 32 teams</p><h2>Start with your team</h2></div><div class="h-divs">{divs}</div>'
         '<p class="blkw"><span class="blk">block: teams · each opens the team page</span></p></section>')

# ---------------------------------------------------------------- 7. newsletter, with the email itself
email = (f'<div class="h-mail"><div class="mh"><span>From: Down by Down</span><span>Subject: <b>Week {fin_w}: {esc(T[sline(ups[0])[0]]["nick"])} stun {esc(T[sline(ups[0])[1]]["nick"])} from {round(ups[0]["pre_w"] / 10)}%</b></span></div>'
         f'<div class="mb"><p class="mk">Week {fin_w} in review</p><h4>{len(ups)} upsets, {sum(1 for g in WK["games"] if "comeback" in tags.get(g["id"], []))} comebacks, {sum(1 for g in WK["games"] if g.get("ot"))} overtimes</h4>'
         f'<p><b>The swing:</b> {esc(sw["d"])} ({esc(sw["c"])}, +{sw["v"]:.0f}%).</p><p><b>The play:</b> {esc(tp["d"].split(" [")[0])} (+{tp["e"]:.2f} EPA).</p>'
         f'<p><b>Rising:</b> ' + ', '.join(esc(m['name']) for m in movers[:3]) + ' saw their target share jump.</p><span class="mbtn">Read the full week →</span></div></div>')
news = (f'<section class="h-news" data-yd="83"><div class="txt">{dm(1, "10", "Opp 17")}<p class="kick">The newsletter</p><h2>The week in review, every Tuesday</h2><p>The upsets, the comebacks, the plays that swung each game and who moved, in one short email after every week. Written from the same data as the site. Free; unsubscribe anytime.</p>'
        '<form class="optin" onsubmit="return false"><input type="email" placeholder="you@example.com" aria-label="Email"><button type="submit">Send me the week</button></form><p class="blkw"><span class="blk">Divi Email Optin · RSS-to-email from /feed/week-in-review/</span></p></div>'
        f'{email}</section>')

# ---------------------------------------------------------------- 8. FAQ, 9. close
QA = [('What is EPA?', 'Expected points added: how much a play changed a team\'s expected score, given the down, distance and field position. A 6-yard gain on 3rd and 5 is worth more than on 3rd and 15. It is the fairest single number for comparing plays.'),
      ('Where does the data come from?', 'Public play-by-play from nflverse, the open NFL data project, plus their weekly player stats, snap counts and injury reports. Nothing is scraped from paid services.'),
      ('How often does it update?', 'Three times a day during the season. Most games are charted within hours of the final whistle; player stat lines follow when nflverse posts them.'),
      ('Is it free?', 'Yes. No account, no paywall, no ads.'),
      ('Do you make picks?', 'No. Down by Down shows how teams and players actually play and how matchups line up. What you do with that is up to you.'),
      ('Can I see past seasons?', 'Yes. Every finished season is kept as it ended, starting with 2025: its teams, players, games and the playoffs.')]
faq = ('<section class="h-faq" data-yd="94"><div class="h-sec-h">' + dm(1, 'Goal', 'Opp 6') + '<p class="kick">Questions</p><h2>Questions</h2></div><div class="qa">' + ''.join(f'<details{" open" if i < 2 else ""}><summary>{esc(qq)}</summary><p>{esc(a)}</p></details>' for i, (qq, a) in enumerate(QA))
       + '</div><p class="blkw"><span class="blk">FAQPage structured data from the plugin</span></p></section>')
close = '<section class="h-close" data-yd="100"><p class="ez" aria-hidden="true">Down<i>×</i>Down</p><p class="td">Touchdown</p><h2>Every snap of the season, on one field.</h2><p>Free, updated three times a day, built on public data.</p><div class="ctas"><a class="btn pri inv">See this week →</a><a class="btn ghost">Pick your team</a></div></section>'

hero = hero[:-len('</section>')] + '<div class="h-games h-count-w">' + nums + '</div></section>'  # the counts close the hero
page = f'<div class="dbd-home">{hero}<div class="h-wrap">{week}{f1}</div>{f2}<div class="h-wrap">{f3}{leaders}{teams}</div>{news}<div class="h-wrap">{faq}</div>{close}</div>'
SHELL = open('shell.html').read()
# plain: no eyebrow labels over headings (the down marker is the label), no dash-bullet lists, no arrows on links
page = re.sub(r'<p class="kick">[^<]*</p>', '', page)
page = re.sub(r'<ul class="pts">.*?</ul>', '', page)
page = page.replace(' →', '').replace('→ ', '')
open('home.html', 'w').write(SHELL.replace('%ROOT%', page))
print('wrote home.html | star', sp[0], n_t, '| play', tp['pl'], tp['e'], '| swing', sw['t'], sw['v'], '| upsets', len(ups), '| risers', [m['name'] for m in movers])
