"""Mock of the weekly post (SCOPE §46): Week 7 2026 written from data/week/w7.json by the rules the plugin would use."""
import json, html, re, os
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
D = lambda p: json.load(open(os.path.join(ROOT, 'data', p)))
T = D('teams.json'); W = D('week/w7.json'); I = {p['id']: p for p in D('players/index.json')['players']}
SITE = 'https://downbydown.com'
e = lambda s: html.escape(str(s), quote=True)
nick = lambda a: T[a]['nick']
slug = lambda s: re.sub(r'[^a-z0-9]+', '-', s.lower()).strip('-')
def game_url(gid):
    s, w, a, h = gid.split('_'); return f"{SITE}/game/{s}-week-{int(w)}-{slug(nick(a))}-at-{slug(nick(h))}/"
def player_url(pid):
    p = I.get(pid); return f"{SITE}/player/{p['slug'] if p and p.get('slug') else pid}/"
team_url = lambda a: f"{SITE}/team/{a.lower()}/"
clean = lambda d: re.sub(r'\s*\[[^\]]*\]', '', d).strip()
def logo(a, n=22):
    t = T[a]; src = t.get('logo')  # logo_s is the logo's scale, not an image
    return f'<img class="lg" src="{e(src)}" alt="" width="{n}" height="{n}">' if src else ''
def tagtext(g):
    return ' · '.join(t[1] for t in g['tags'] if t[0] in ('upset', 'comeback', 'ot')) or ('Led all the way' if any(t[0] == 'wire' for t in g['tags']) else '')
words = {1:'one',2:'two',3:'three',4:'four',5:'five',6:'six',7:'seven',8:'eight',9:'nine'}
num = lambda n: words.get(n, str(n))
pl = lambda n, one, many: f"{num(n)} {one if n == 1 else many}"

games = W['games']; ups = [g for g in games if any(t[0] == 'upset' for t in g['tags'])]
cbs = [g for g in games if any(t[0] == 'comeback' for t in g['tags'])]; ots = [g for g in games if g.get('ot')]
up = min(ups, key=lambda g: g['pre_w']); pct = lambda v: round(v / 10)  # the build's rounding: 22.5 -> 22, as in the tags
loser = lambda g: g['home'] if g['win'] == g['away'] else g['away']
score = lambda g: (g['a'], g['h']) if g['win'] == g['away'] else (g['h'], g['a'])
title = f"Week 7 in review: {nick(up['win'])} stun {nick(loser(up))} from {pct(up['pre_w'])}%"
ws, ls = score(up)
opening = (f"{pl(len(ups),'upset','upsets').capitalize()}, {pl(len(cbs),'comeback','comebacks')} and {pl(len(ots),'overtime game','overtime games')}. "
           f"The {nick(up['win'])} were {pct(up['pre_w'])}% to win at kickoff, fell to {pct(up['low'])}% during the game, and still beat the {nick(loser(up))} {ws}-{ls} in overtime.")

rows = ''
for g in sorted(games, key=lambda g: -g['drama']):
    w = g['win']; l = loser(g); a, b = score(g)
    rows += (f'<tr><td><a href="{game_url(g["id"])}">{logo(w)}<b>{e(nick(w))}</b> {a}, {e(nick(l))} {b}</a></td>'
             f'<td class="tg">{e(tagtext(g))}</td></tr>')

sw = W['swings'][0]; tp = W['plays'][0]
swing = (f"The biggest swing came with {sw['c'].split()[1]} left in Cleveland's game in Tennessee: {e(clean(sw['d']))} "
         f"It moved the Browns' chance of winning by {round(sw['v'])} points, and they won 27-24. <a href=\"{game_url(sw['g'])}\">The game</a>")
play = (f"The best single play by expected points was the Patriots' at the end of the first half in Chicago: {e(clean(tp['d']))} "
        f"Worth {tp['e']:+.2f} EPA. <a href=\"{game_url(tp['g'])}\">The game</a>")
bo = max(W['teams'], key=lambda t: t['epa']); bd = min(W['teams'], key=lambda t: t['depa'])
units = (f"<a href=\"{team_url(bo['t'])}\">The {nick(bo['t'])}</a> had the week's best offense, {bo['epa']:+.2f} EPA a play in a {bo['pf']}-13 win in Baltimore, against their usual {bo['usual']:+.2f}. "
         f"<a href=\"{team_url(bd['t'])}defense/\">The {nick(bd['t'])}</a> had the best defense, holding Carolina to {bd['depa']:+.2f} a play in a 43-13 win.")

def prow(p, val):
    ph = (p.get('ph') or p.get('photo') or '').replace('f_auto,q_auto', 'f_auto,q_auto,w_80,h_80,c_fill,g_face'); img = f'<img class="hs" src="{e(ph)}" alt="" width="40" height="40" style="background:{T[p["t"]]["c1"]}">' if ph else ''
    return f'<tr><td>{img}</td><td><a href="{player_url(p["id"])}"><b>{e(p["n"])}</b></a><small>{e(p["pos"])} · {e(nick(p["t"]))} vs {e(nick(p["o"]))}</small></td><td class="ln">{val}</td></tr>'
pow_rows = ''.join(prow(W['players'][pos][0], f'{e(W["players"][pos][0]["x"])}<span>{W["players"][pos][0]["e"]:+.1f} EPA</span>') for pos in ('QB', 'RB', 'WR', 'TE'))
fx = sorted(W['fx']['half'], key=lambda p: -p['f'][1])[:5]
fx_rows = ''.join(prow(p, f'<b>{p["f"][1]:.1f}</b> half PPR') for p in fx)
ris = W['movers']['targets']['up'][:3]
rising = ', '.join(f'<a href="{player_url(m["id"])}">{e(m["name"])}</a> ({e(nick(m["team"]))}, {round(m["before"])}% to {round(m["now"])}%)' for m in ris)
faces = W['movers']['faces'][:3]
kind = {'debut': 'first game of the season', 'first start': 'first start'}
face_t = '; '.join(f'<a href="{player_url(m["id"])}">{e(m["name"])}</a> ({kind.get(m["kind"], m["kind"])} for the {e(nick(m["team"]))}, {m["now"]}% of snaps)' for m in faces)

H = open(os.path.join(ROOT, 'divi', 'header.html')).read()
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{e(title)} | Down by Down</title>
<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
body{{margin:0;background:#f4f1ea;color:#121417;font:400 18px/1.65 "IBM Plex Sans",system-ui,sans-serif;-webkit-font-smoothing:auto}}
.post{{max-width:760px;margin:0 auto;padding:clamp(32px,6vw,64px) 20px 72px}}
.post h1{{margin:0 0 12px;font:800 clamp(32px,4.6vw,52px)/1.04 "Tourney",sans-serif;text-transform:uppercase}}
.by{{margin:0 0 24px;font:600 13px "IBM Plex Mono",monospace;letter-spacing:.04em;color:#5d616a}}
.card{{display:block;width:100%;height:auto;border:1px solid rgba(22,30,54,.14);border-radius:12px;margin:0 0 28px}}
.open{{font-size:21px;line-height:1.55;margin:0 0 8px}}
.post h2{{margin:40px 0 12px;padding-top:24px;border-top:1px solid rgba(22,30,54,.2);font:800 26px/1.1 "Tourney",sans-serif;text-transform:uppercase}}
.post p{{margin:0 0 16px}}
.post a{{color:#121417;text-decoration:underline;text-decoration-color:rgba(203,32,38,.55);text-underline-offset:3px}}
.post a:hover{{color:#cb2026}}
table{{width:100%;border-collapse:collapse;font-size:16px}}
td{{padding:9px 6px;border-top:1px solid rgba(22,30,54,.14);vertical-align:middle}}
tr:last-child td{{border-bottom:1px solid rgba(22,30,54,.14)}}
.post td a{{text-decoration:none}}.post td a:hover b{{text-decoration:underline}}
td .lg{{vertical-align:-5px;margin-right:8px}}
td.tg{{font:500 13px "IBM Plex Sans",sans-serif;color:#5d616a;text-align:right}}
.pl td:first-child{{width:48px}}
.hs{{display:block;border-radius:50%;object-fit:cover}}
.pl small{{display:block;font-size:13px;color:#5d616a}}
td.ln{{text-align:right;font:500 14px "IBM Plex Mono",monospace;color:#3b4255}}
td.ln span{{display:block;font-size:12px;color:#1e7f4a}}
td.ln b{{font:800 18px "Tourney",sans-serif;color:#121417}}
.next{{display:flex;align-items:center;gap:12px;margin:0 0 12px;font:800 22px "Tourney",sans-serif;text-transform:uppercase}}
.sign{{margin:44px 0 0;padding:22px 24px;background:#fffdf8;border:1px solid rgba(22,30,54,.18);border-radius:12px}}
.sign h3{{margin:0 0 6px;font:800 22px "Tourney",sans-serif;text-transform:uppercase}}
.sign form{{display:flex;flex-wrap:wrap;gap:10px;margin-top:12px}}
.sign input{{flex:1 1 220px;height:48px;padding:0 14px;border:1px solid rgba(22,30,54,.28);border-radius:6px;background:#fff;font:16px "IBM Plex Sans",sans-serif}}
.sign button{{height:48px;padding:0 22px;border:0;border-radius:6px;background:#cb2026;color:#fff;font:700 15px "Barlow Condensed",sans-serif;letter-spacing:.1em;text-transform:uppercase}}
@media (max-width:560px){{.pl tr{{display:grid;grid-template-columns:48px minmax(0,1fr);border-top:1px solid rgba(22,30,54,.14)}}.pl td{{border:0;padding:8px 4px 2px}}.pl td:first-child{{grid-row:span 2;padding-top:10px}}.pl td.ln{{text-align:left;padding:0 4px 10px}}.pl td.ln span{{display:inline;margin-left:8px}}.pl{{border-bottom:1px solid rgba(22,30,54,.14)}}body{{font-size:17px}}.open{{font-size:19px}}td.tg{{display:block;text-align:left;padding-top:0;border-top:0}}.sc tr{{display:block;border-top:1px solid rgba(22,30,54,.14)}}.sc td{{display:block;border:0;padding:8px 2px 2px}}.sc td.tg{{padding:0 2px 9px}}.sc tr:last-child td{{border:0}}.sc{{border-bottom:1px solid rgba(22,30,54,.14)}}}}
</style></head><body>
{H}
<article class="post">
<h1>{e(title)}</h1>
<p class="by">Tuesday, October 27, 2026 · Week 7 · 14 games</p>
<img class="card" src="{SITE}/?card=page-week-7" alt="Week 7 in review" width="1200" height="630">
<p class="open">{opening}</p>
<p>The Bengals came back from 14% in Baltimore and won by 22. Byes: Bills, Jaguars, Chargers and Commanders.</p>
<h2>The scores</h2>
<table class="sc">{rows}</table>
<h2>The swing and the play</h2>
<p>{swing}</p>
<p>{play}</p>
<h2>Best offense, best defense</h2>
<p>{units}</p>
<h2>Players of the week</h2>
<table class="pl">{pow_rows}</table>
<h2>Fantasy</h2>
<p>The week's top five in half PPR. Tony Pollard scored three touchdowns on a day his rushing was below average by EPA.</p>
<table class="pl">{fx_rows}</table>
<h2>Rising</h2>
<p>The biggest jumps in share of the team's targets, against their own earlier games: {rising}.</p>
<p>New faces: {face_t}.</p>
<h2>Next week</h2>
<p class="next">{logo('CHI',30)} Bears at Seahawks {logo('SEA',30)}</p>
<p>The game of the week is Monday night in Seattle. Its biggest edge: the Bears give up 149 yards a game to wide receivers (19th), and the Seahawks' receivers produce 185 (4th). <a href="{game_url('2026_08_CHI_SEA')}seahawks/">Open the matchup</a></p>
<p>Every play, every team and every player from Week 7 is on <a href="{SITE}/week/7/">the week page</a>.</p>
<div class="sign"><h3>Get this every Tuesday</h3><p style="margin:0">One email after every week. Free.</p><form onsubmit="return false"><input type="email" placeholder="you@example.com" aria-label="Email"><button>Sign up</button></form></div>
</article>
</body></html>'''
open(os.path.join(ROOT, 'mockup-v21', 'week-7-post.html'), 'w').write(page)
print('wrote', len(page), 'bytes;', title)
