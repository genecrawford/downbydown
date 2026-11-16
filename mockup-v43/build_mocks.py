"""Mock: the Share pack tab (SCOPE §68). Run from mockup-v43/ after fetch_keys.py (the Week 11 keys from the live game pages).
Builds index.html: the admin tab with the Week 9 pack, real numbers from data/week/w9.json, and the Week 11 previews (Week 10
finishes Monday night, so the mock borrows the next week that has previews). Opens from disk; the existing cards load from
downbydown.com; the two new cards are drawn here in HTML to show what the plugin would draw with GD."""
import json, re, html

D = '../data/'
W = json.load(open(D + 'week/w9.json')); T = json.load(open(D + 'teams.json')); S = json.load(open(D + 'schedule.json'))
K = json.load(open('_keys.json'))
SITE = 'https://downbydown.com'
E = html.escape
nick = lambda t: T[t]['nick']
poss = lambda t: nick(t) + '’'
def slug(s): return re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')
def epa(v): return ('−' if v < 0 else '+') + f'{abs(v):.2f}'

SUBS = {'ARI': 'AZCardinals', 'ATL': 'falcons', 'BAL': 'ravens', 'BUF': 'buffalobills', 'CAR': 'panthers', 'CHI': 'CHIBears', 'CIN': 'bengals', 'CLE': 'Browns',
        'DAL': 'cowboys', 'DEN': 'DenverBroncos', 'DET': 'detroitlions', 'GB': 'GreenBayPackers', 'HOU': 'Texans', 'IND': 'Colts', 'JAX': 'Jaguars', 'KC': 'KansasCityChiefs',
        'LV': 'raiders', 'LAC': 'Chargers', 'LA': 'LosAngelesRams', 'MIA': 'miamidolphins', 'MIN': 'minnesotavikings', 'NE': 'Patriots', 'NO': 'Saints', 'NYG': 'NYGiants',
        'NYJ': 'nyjets', 'PHI': 'eagles', 'PIT': 'steelers', 'SF': '49ers', 'SEA': 'Seahawks', 'TB': 'buccaneers', 'TEN': 'Tennesseetitans', 'WAS': 'Commanders'}
r = lambda t: 'r/' + SUBS[t]

def xlen(s):  # X counts every link as 23 characters
    return len(re.sub(r'https?://\S+', 'x' * 23, s))

games = {g['id']: g for g in W['games']}
def game_url(g): return f"{SITE}/game/2026-week-{g['id'].split('_')[1].lstrip('0')}-{slug(nick(g['away']))}-at-{slug(nick(g['home']))}/"

items = []

# 1. the biggest swing
sw = W['swings'][0]; g = games[sw['g']]; t = sw['t']; o = g['home'] if t == g['away'] else g['away']
ws, ls = max(g['a'], g['h']), min(g['a'], g['h']); won = g['win'] == t
q, clk = sw['c'].split(' ')
qw = {'Q1': 'first quarter', 'Q2': 'second quarter', 'Q3': 'third', 'Q4': 'fourth'}[q]
when = f"with {clk} left in the {qw}" if q == 'Q4' else f"in the {qw}"
play = re.sub(r'\s*\[[^\]]*\]', '', sw['d']); play = re.sub(r'\s+[^.]*…$', '', play)  # brackets out, a sentence cut off with … out
link = game_url(g)
title = f"Week 9’s biggest swing: one play {when} moved the {poss(t)} chance of beating the {nick(o)} by {round(sw['v'])} points. They {'won' if won else 'lost'} {ws}-{ls}."
items.append(dict(k='swing', h='The biggest swing', why='The week’s largest one-play change in win probability, on the game’s card with the swing marked.',
    img=f"{SITE}/?card=game-{g['id']}", file=f"dbd-week-9-swing-{t.lower()}-{o.lower()}.png", fits=['r/nfl', r(t), r(o)],
    title=title, comment=f"The play: {play} The whole game’s win probability, play by play: {link}", post=f"{title} {link}", link=link))

# 2. a usage riser (snap share, quarterbacks out: a new starter is the debut list's story, not a riser's)
sn = next(m for m in W['movers']['snaps']['up'] if m['pos'] != 'QB')
link = f"{SITE}/player/{slug(sn['name'])}/"
title = f"{sn['name']} played {round(sn['now'])}% of the {poss(sn['team'])} snaps against the {nick(sn['opp'])} in Week 9, up from {round(sn['before'])}% in his {sn['g']} earlier games."
items.append(dict(k='snaps', h='A usage riser', why='The biggest jump in snap share against his own earlier games, quarterbacks left out. A new card: the share game by game.',
    card=('snaps', sn), file=f"dbd-week-9-snaps-{slug(sn['name'])}.png", fits=['r/fantasyfootball', r(sn['team'])],
    title=title, comment=f"His snap share and targets game by game: {link}", post=f"{title} {link}", link=link))

# 3. the best and worst offense
tm = W['teams']; bo = max(tm, key=lambda x: x['epa']); wo = min(tm, key=lambda x: x['epa'])
def res(x): return f"a {x['pf']}-{x['pa']} win over the {nick(x['o'])}" if x['pf'] > x['pa'] else f"a {x['pa']}-{x['pf']} loss to the {nick(x['o'])}"
link = f"{SITE}/week/9/"
title = f"Week 9 offenses by EPA a play: the {nick(bo['t'])} were best ({epa(bo['epa'])} in {res(bo)}), the {nick(wo['t'])} worst ({epa(wo['epa'])} in {res(wo)})."
items.append(dict(k='offense', h='Best and worst offense', why='Every offense that played, by EPA a play for the week. A new card: the whole league in one chart.',
    card=('offense', None), file='dbd-week-9-offense.png', fits=['r/nfl', r(bo['t'])],
    title=title, comment=f"Every team’s week, with the usual for each side: {link}", post=f"{title} {link}", link=link))

# 4. a target-share mover (the keys come last because there are 26 of them)
tg = W['movers']['targets']['up'][0]
link = f"{SITE}/player/{slug(tg['name'])}/"
title = f"{tg['name']} drew {round(tg['now'])}% of the {poss(tg['team'])} targets against the {nick(tg['opp'])} in Week 9, up from {round(tg['before'])}% in his {tg['g']} earlier games."
items.append(dict(k='targets', h='A target-share mover', why='The biggest jump in share of the team’s targets against his own earlier games. Same new card as the usage riser.',
    card=('targets', tg), file=f"dbd-week-9-targets-{slug(tg['name'])}.png", fits=['r/fantasyfootball', r(tg['team'])],
    title=title, comment=f"His target share and routes game by game: {link}", post=f"{title} {link}", link=link))

# ---- the two new cards, drawn in HTML the way GD would draw them
def bars_svg(s, now_c, before):
    vals = [v for v in s if v is not None]; hi = max(100, max(vals)) if now_c == 'snaps' else max(40, max(vals) * 1.15)
    n = len(s); x0, x1, y0, y1 = 0, 508, 30, 380; bw = (x1 - x0) / n
    out = []
    by = y1 - before / hi * (y1 - y0)
    for i, v in enumerate(s):
        cx = x0 + i * bw + 6; w = bw - 12
        if v is None:
            out.append(f'<text x="{cx + w / 2}" y="{y1 - 8}" text-anchor="middle" class="m">–</text>')
        else:
            h = max(2, v / hi * (y1 - y0)); last = i == n - 1
            out.append(f'<rect x="{cx}" y="{y1 - h}" width="{w}" height="{h}" fill="{"var(--c1)" if last else "#cfcabd"}"/>')
            out.append(f'<text x="{cx + w / 2}" y="{y1 - h - 8}" text-anchor="middle" class="{"v b" if last else "v"}">{round(v)}%</text>')
        out.append(f'<text x="{cx + w / 2}" y="{y1 + 24}" text-anchor="middle" class="m">W{i + 1}</text>')
    out.append(f'<line x1="0" x2="{x1}" y1="{by}" y2="{by}" stroke="#121417" stroke-width="2" stroke-dasharray="6 6"/>')
    out.append(f'<line x1="0" x2="{x1}" y1="{y1}" y2="{y1}" stroke="#121417" stroke-width="1"/>')
    return f'<svg viewBox="0 -10 508 420" width="508" height="420">{"".join(out)}</svg>'

def card_riser(kind, m):
    t = m['team']; c1 = T[t]['c1']; first, last = m['name'].split(' ', 1)
    lab = 'Snap share' if kind == 'snaps' else 'Target share'
    say = f"of the {poss(t)} snaps against the {nick(m['opp'])}" if kind == 'snaps' else f"of the {poss(t)} targets against the {nick(m['opp'])}"
    return f'''<div class="cd" style="--c1:{c1}"><div class="cd-top"><img class="cd-logo" src="{SITE}/wp-content/plugins/down-by-down/assets/card-logo.png" alt=""><span class="cd-meta"><img src="{T[t]['logo']}" alt="">2026 · Week 9 · {lab}</span></div><i class="cd-rule"></i>
<div class="cd-l"><span class="cd-k">{lab} · {E(nick(t))} {m['pos']} #{m['num']}</span><b class="cd-n">{E(first)}<br><em>{E(last)}</em></b>
<span class="cd-big">{round(m['now'])}%</span><span class="cd-s">{E(say)}, up from {round(m['before'])}% in his {m['g']} earlier games</span></div>
<div class="cd-r"><span class="cd-k2">{lab} by game · dashes: earlier games, {round(m['before'])}%</span>{bars_svg(m['s'], kind, m['before'])}</div>
<div class="cd-foot"><b>downbydown.com</b><span>Data: nflverse · {'share of the team’s offensive snaps' if kind == 'snaps' else 'share of the team’s targets'}</span></div></div>'''

def card_offense():
    rows = sorted(tm, key=lambda x: -x['epa']); hi = max(abs(x['epa']) for x in rows); n = len(rows)
    rh = 430 / n; zx = 370; sc = 270 / hi; out = []
    for i, x in enumerate(rows):
        y = 18 + i * rh; w = abs(x['epa']) * sc; edge = x is rows[0] or x is rows[-1]
        fill = T[x['t']]['c1'] if edge else ('#9fb8a6' if x['epa'] >= 0 else '#d7a9a4')
        bx = zx if x['epa'] >= 0 else zx - w
        out.append(f'<rect x="{bx:.1f}" y="{y + 2:.1f}" width="{w:.1f}" height="{rh - 4:.1f}" fill="{fill}"/>')
        lx, anc = (zx - 8, 'end') if x['epa'] >= 0 else (zx + 8, 'start')
        out.append(f'<text x="{lx}" y="{y + rh - 4:.1f}" text-anchor="{anc}" class="{"t b" if edge else "t"}">{x["t"]}</text>')
        vx, vanc = (bx + w + 6, 'start') if x['epa'] >= 0 else (bx - 6, 'end')
        if edge or i % 1 == 0: out.append(f'<text x="{vx:.1f}" y="{y + rh - 4:.1f}" text-anchor="{vanc}" class="{"t b" if edge else "t m"}">{epa(x["epa"])}</text>')
    out.append(f'<line x1="{zx}" x2="{zx}" y1="10" y2="{18 + n * rh + 4}" stroke="#121417" stroke-width="1.5"/>')
    tile = lambda lab, x: f'<div class="cd-tile" style="--c1:{T[x["t"]]["c1"]}"><span>{lab}</span><b><img src="{T[x["t"]]["logo"]}" alt="">{E(nick(x["t"]))}</b><em>{epa(x["epa"])}</em><small>EPA a play · {x["pf"]}-{x["pa"]} {"over" if x["pf"] > x["pa"] else "to"} the {E(nick(x["o"]))}</small></div>'
    return f'''<div class="cd" style="--c1:#cb2026"><div class="cd-top"><img class="cd-logo" src="{SITE}/wp-content/plugins/down-by-down/assets/card-logo.png" alt=""><span class="cd-meta">2026 · Week 9 · Offense</span></div><i class="cd-rule"></i>
<div class="cd-l"><span class="cd-k">Week 9 · {n} offenses</span><b class="cd-n">Who moved<br><em>the ball</em></b>
<div class="cd-tiles">{tile('Best', rows[0])}{tile('Worst', rows[-1])}</div></div>
<div class="cd-r o"><span class="cd-k2">EPA a play · every offense that played</span><svg viewBox="0 0 640 456" width="640" height="456">{"".join(out)}</svg></div>
<div class="cd-foot"><b>downbydown.com</b><span>Data: nflverse · expected points added a play</span></div></div>'''

def card_html(c):
    return card_riser(c[0], c[1]) if c[0] in ('snaps', 'targets') else card_offense()

def field(lab, text, cnt=None):
    c = f'<span class="cnt">{cnt} of 280</span>' if cnt is not None else ''
    return f'<div class="sp-f"><div class="sp-fh"><b>{lab}</b>{c}<button type="button" class="button cp">Copy</button></div><textarea readonly rows="{3 if len(text) > 150 else 2}">{E(text)}</textarea></div>'

def item_html(i, it):
    img = f'<div class="sp-card"><div class="cd-wrap">{card_html(it["card"])}</div></div>' if 'card' in it else f'<div class="sp-card"><img src="{it["img"]}" width="1200" height="630" alt=""></div>'
    return f'''<div class="dbd-box sp-item"><h2><span>{i}. {it["h"]}</span><small>{E(it["why"])}</small></h2><div class="sp-body">
<div class="sp-l">{img}<div class="sp-acts"><a class="button button-primary" href="{it.get("img", "#")}" download="{it["file"]}">Download image</a><a class="button" href="{it["link"]}" target="_blank" rel="noopener">Open the page</a></div>
<p class="sp-fits">Fits: {" · ".join(it["fits"])}</p></div>
<div class="sp-r">{field("Reddit title", it["title"])}{field("Reddit comment, with the link", it["comment"])}{field("X and Bluesky", it["post"], xlen(it["post"]))}</div></div></div>'''

# 5. keys to the game for next week, one line per team, for team subreddits
krows = ''
for g in S['weeks']['11']:
    a, h_ = g['away'], g['home']; gu = f"{SITE}/game/2026-week-11-{slug(nick(a))}-at-{slug(nick(h_))}/"
    sides = ''
    for t, o in ((a, h_), (h_, a)):
        k = K.get(g['id'] + ':' + t)
        if not k: continue
        title = f"{nick(a)} at {nick(h_)}: where the matchup tilts for the {nick(t)} offense"
        comment = f"{k[0]}. {k[1]} The other keys and the head to head: {gu}"
        post = f"{nick(a)} at {nick(h_)}: {k[1]} {gu}"
        sides += (f'<div class="sp-k"><div class="sp-kh"><img src="{T[t]["logo"]}" alt=""><b>{E(nick(t))} offense</b><span>{r(t)}</span></div>'
                  f'<p><b>{E(k[0])}.</b> {E(k[1])}</p><div class="sp-kb">'
                  f'<button type="button" class="button cp" data-t="{E(title)}">Copy title</button><button type="button" class="button cp" data-t="{E(comment)}">Copy comment</button><button type="button" class="button cp" data-t="{E(post)}">Copy for X and Bluesky</button></div></div>')
    day = g['day'] + ' ' + g['time']
    krows += (f'<div class="sp-g"><div class="sp-gc"><img src="{SITE}/?card=game-{g["id"]}" width="1200" height="630" alt="" loading="lazy">'
              f'<a class="button" href="{SITE}/?card=game-{g["id"]}" download="dbd-week-11-{a.lower()}-{h_.lower()}.png">Download</a></div><div class="sp-gs"><b class="sp-gt">{E(nick(a))} at {E(nick(h_))}</b>{sides}</div></div>')

keys = f'''<div class="dbd-box sp-item"><h2><span>5. Keys to the game, Week 11</span><small>Each team’s biggest edge against its next opponent, for its own subreddit. The image is the game’s preview card, which already shows both sides.</small></h2>
<div class="sp-keys">{krows}</div></div>'''

CARD_CSS = '''
.cd-wrap{position:relative;width:100%;aspect-ratio:1200/630;overflow:hidden;background:#f4f1ea}
.cd{position:absolute;left:0;top:0;width:1200px;height:630px;transform-origin:0 0;background:#f4f1ea;color:#121417;font-family:"IBM Plex Sans",sans-serif}
.cd-top{position:absolute;left:44px;right:44px;top:15px;height:34px;display:flex;justify-content:space-between;align-items:center}
.cd-logo{height:34px}
.cd-meta{display:flex;align-items:center;gap:12px;font:700 15px "Barlow Condensed",sans-serif;letter-spacing:.2em;text-transform:uppercase;color:#3b4255}.cd-meta img{height:30px}
.cd-rule{position:absolute;left:0;right:0;top:64px;height:6px;background:var(--c1)}
.cd-l{position:absolute;left:44px;top:108px;width:540px}
.cd-k{display:block;font:700 15px "Barlow Condensed",sans-serif;letter-spacing:.2em;text-transform:uppercase;color:#cb2026}
.cd-n{display:block;margin-top:14px;font:800 62px/1 "Tourney",sans-serif;text-transform:uppercase;color:#121417}.cd-n em{font-style:normal;color:var(--c1)}
.cd-big{display:block;margin-top:26px;font:800 96px/1 "Tourney",sans-serif;color:var(--c1)}
.cd-s{display:block;margin-top:10px;max-width:500px;font:500 22px/1.35 "IBM Plex Sans",sans-serif;color:#3b4255}
.cd-r{position:absolute;left:648px;top:104px;width:508px}.cd-r.o{left:520px;width:640px;top:100px}
.cd-k2{display:block;margin-bottom:6px;font:700 15px "Barlow Condensed",sans-serif;letter-spacing:.2em;text-transform:uppercase;color:#6b6f78}
.cd svg text{font:500 15px "IBM Plex Mono",monospace;fill:#3b4255}.cd svg text.v{font:700 18px "Barlow Condensed",sans-serif;fill:#3b4255}.cd svg text.b{font-weight:700;fill:#121417}.cd svg text.m{fill:#6b6f78}.cd svg text.d{fill:#121417}
.cd-r.o svg text{font-size:13px}
.cd-tiles{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:34px}
.cd-tile{padding:16px;border:1px solid #d9d5cb;background:#fffdf8;border-top:5px solid var(--c1)}
.cd-tile span{display:block;font:700 13px "Barlow Condensed",sans-serif;letter-spacing:.2em;text-transform:uppercase;color:#6b6f78}
.cd-tile b{display:flex;align-items:center;gap:8px;margin-top:8px;font:700 26px "Barlow Condensed",sans-serif;text-transform:uppercase}.cd-tile b img{height:30px}
.cd-tile em{display:block;margin-top:4px;font:800 46px/1 "Tourney",sans-serif;font-style:normal;color:var(--c1)}
.cd-tile small{display:block;margin-top:8px;font:500 13px/1.4 "IBM Plex Mono",monospace;color:#6b6f78}
.cd-foot{position:absolute;left:0;right:0;top:586px;height:44px;border-top:1px solid #d9d5cb;display:flex;justify-content:space-between;align-items:center;padding:0 44px;font:500 13px "IBM Plex Mono",monospace;color:#6b6f78}.cd-foot b{color:#121417;font-weight:600}
'''

base = open('../mockup-v40/index.html').read()
css = base[base.find('<style>') + 7:base.find('</style>')]
css = css[:css.find('/* the Growth tab')]

SP_CSS = '''
.sp-head{max-width:1180px;display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin:0 0 16px}
.sp-head select{min-height:30px}
.sp-head p{margin:0;color:#50575e}
.sp-item{max-width:1180px}
.sp-body{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:20px;padding:16px}
.sp-card>img{display:block;width:100%;height:auto;border:1px solid #dcdcde}
.sp-card .cd-wrap{border:1px solid #dcdcde}
.sp-acts{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px}
.button-primary{background:#2271b1;color:#fff}
.sp-fits{margin:8px 0 0;color:#50575e}
.sp-f{margin:0 0 14px}
.sp-fh{display:flex;align-items:center;gap:10px;margin-bottom:4px}.sp-fh b{flex:1}.sp-fh .cnt{color:#646970;font-size:12px}
.sp-f textarea{width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid #8c8f94;border-radius:4px;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;resize:vertical;background:#fff;field-sizing:content;min-height:3.2em}
.cp.ok{border-color:#00a32a;color:#007017}
.sp-keys{padding:0 16px}
.sp-g{display:grid;grid-template-columns:260px minmax(0,1fr);gap:20px;padding:16px 0;border-top:1px solid #f0f0f1}.sp-g:first-child{border-top:0}
.sp-gc img{display:block;width:100%;height:auto;border:1px solid #dcdcde;margin-bottom:8px}
.sp-gt{display:block;font-size:14px;margin-bottom:8px}
.sp-gs{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-content:start}.sp-gt{grid-column:1/-1;margin:0}
.sp-kh{display:flex;align-items:center;gap:6px}.sp-kh img{width:22px;height:22px}.sp-kh span{margin-left:auto;color:#646970}
.sp-k p{margin:6px 0 8px;line-height:1.5}
.sp-kb{display:flex;flex-wrap:wrap;gap:6px}.sp-kb .button{font-size:12px;min-height:26px;line-height:24px}
@media (max-width:1000px){.sp-body{grid-template-columns:minmax(0,1fr)}.sp-g{grid-template-columns:minmax(0,1fr)}.sp-gc img{max-width:360px}}
@media (max-width:782px){.side{display:none}.wrap{padding:10px 12px 40px}.sp-body{padding:12px}.sp-keys{padding:0 12px}}
@media (max-width:640px){.sp-gs{grid-template-columns:minmax(0,1fr)}}
'''

tabs = ['Overview', 'Week in review', 'News and videos', 'Site', 'Data', 'Growth', 'Share pack']
nav = ''.join(f'<a class="nav-tab{" on" if t == "Share pack" else ""}">{t}</a>' for t in tabs)
weeks = ''.join(f'<option{" selected" if w == 9 else ""}>Week {w}</option>' for w in range(9, 0, -1))
body = ''.join(item_html(i + 1, it) for i, it in enumerate(items[:2])) + item_html(3, items[2]) + item_html(4, items[3]) + keys

page = f'''<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Share pack mock</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans:wght@400;500&family=Tourney:wght@800&display=swap" rel="stylesheet">
<style>{css}{SP_CSS}{CARD_CSS}</style></head>
<body>
<div class="bar"></div>
<div class="shell">
<div class="side"><div>Dashboard</div><div>Posts</div><div>Pages</div><div class="on">Down by Down</div><div>Site Kit</div><div>WPForms</div><div>Settings</div></div>
<div class="wrap">
<h1>Down by Down</h1>
<p class="dbd-lede">Season 2026 · Week 10 · data built 6:12 AM · version 2.27.0</p>
<div class="mock-note" style="max-width:1180px"><b>Mock, real numbers.</b> The new Share pack tab: five things to post each week, built from the newest finished week (Week 9 here) the moment its last game is in, so it’s ready Tuesday morning. Each has the image, a Reddit title, a Reddit comment with the link, and a post for X and Bluesky, each with a Copy button. Nothing posts by itself. Items 1 and 5 use the game cards the site already draws; items 2, 3 and 4 use two new cards, drawn here in HTML to show what the plugin would draw. Week 10 finishes tonight, so the keys below borrow Week 11’s previews.</div>
<nav class="nav-tab-wrapper">{nav}</nav>
<div class="sp-head"><select aria-label="Week">{weeks}</select><p>Week 9 pack, ready since Tuesday, Nov 10. Post two or three a week by hand: the image first, and the link in a comment where a subreddit asks for that. Visits from reddit.com, x.com and bsky.app show on the Growth tab.</p></div>
{body}
</div></div>
<script>
/* scale the drawn cards to their box */
function fit(){{document.querySelectorAll('.cd-wrap').forEach(function(w){{var c=w.querySelector('.cd');c.style.transform='scale('+(w.clientWidth/1200)+')';}});}}
fit();addEventListener('resize',fit);document.fonts&&document.fonts.ready.then(fit);
/* copy buttons: a field's text, or the button's own data-t */
document.addEventListener('click',function(e){{var b=e.target.closest('.cp');if(!b)return;
var t=b.getAttribute('data-t');if(t===null){{var f=b.closest('.sp-f');t=f.querySelector('textarea').value;}}
var done=function(){{var o=b.textContent;b.textContent='Copied';b.classList.add('ok');setTimeout(function(){{b.textContent=o;b.classList.remove('ok');}},1400);}};
if(navigator.clipboard&&navigator.clipboard.writeText){{navigator.clipboard.writeText(t).then(done,function(){{fallback(t);done();}});}}else{{fallback(t);done();}}
function fallback(s){{var a=document.createElement('textarea');a.value=s;document.body.appendChild(a);a.select();try{{document.execCommand('copy');}}catch(x){{}}a.remove();}}
}});
</script>
</body></html>'''
open('index.html', 'w').write(page)
for it in items: print(it['k'], xlen(it['post']), '|', it['title'])
