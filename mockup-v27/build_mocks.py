"""SCOPE §54 mocks: small hints of the news and the NFL's weekly videos across the site. Run from mockup-v27/ after feeds.py:  python3 build_mocks.py
Inputs saved from the live site (2.13.1) on 2026-11-12 with curl: _game-2026-week-10-vikings-at-packers.html, _player-josh-allen.html, _team-min.html,
_matchups.html, _week-9.html, _team-buf-players.html, _team-min-players.html, _team-gb-players.html (https://downbydown.com/<path>/), feeds.json
(feeds.py), and ../mockup-v23/email-week-8.html. Everything opens on YouTube in a new tab: the NFL blocks playing its videos on other sites."""
import json, re, html
e = html.escape
F = json.load(open('feeds.json'))
YT = lambda v: f'https://www.youtube.com/watch?v={v}'

def no_tracking(h):
    h = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', h)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', h, flags=re.S)

CSS = '''<style>
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}
.dbd .mock-new{outline:1px dashed var(--dbd-red);outline-offset:3px}
/* a small play mark: in a table row, a matchups row, the game box, a player's pills */
.dbd .dbd-vmk{display:inline-flex;align-items:center;gap:5px;font:600 12px/1 var(--dbd-body);color:var(--dbd-ink);text-decoration:none;white-space:nowrap}
.dbd .dbd-vmk::before{content:"";width:18px;height:13px;border-radius:3px;background:#0f1311 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 10 10'%3E%3Cpath d='M3 2l5 3-5 3z' fill='%23fff'/%3E%3C/svg%3E") center/8px no-repeat}
.dbd .dbd-vmk:hover{color:var(--dbd-red-ink)}.dbd .dbd-vmk:hover::before{background-color:var(--dbd-red)}
.dbd td .dbd-vmk{margin-left:8px;font-size:11px}
.dbd .dbd-gamebox .dbd-vmk{display:flex;margin-top:10px;padding-top:10px;border-top:1px solid var(--dbd-line)}
.dbd .dbd-pill.dbd-vmk{gap:6px}
/* a row of video cards (the week page, the end of a game or player page) */
.dbd .vd-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px 16px;padding:12px var(--dbd-edge) 16px}
.dbd .vd-row .dbd-vid h4{font-size:14px}
.dbd .vd-row .vd-more{display:none}.dbd .open .vd-row .vd-more{display:block}
@media (max-width:900px){.dbd .vd-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>'''
def note(t): return f'<div class="mock-note"><b>Mock.</b> {t}</div>'
def play(v, title, big=False):
    return (f'<a class="dbd-play" href="{YT(v)}" target="_blank" rel="noopener" aria-label="Watch on YouTube (opens a new tab): {e(title)}">'
            f'<img src="https://i.ytimg.com/vi/{v}/{"hqdefault" if big else "mqdefault"}.jpg" alt="" loading="lazy" width="{480 if big else 320}" height="{270 if big else 180}"><span class="yt">YouTube</span></a>')
def card(v, title, meta, big=False): return f'<figure class="dbd-vid">{play(v, title, big)}<h4>{e(title)}</h4><span>{e(meta)}</span></figure>'
def end(h, block): i = h.rfind('</main>'); return h[:i] + block + h[i:]
def css(h): return h.replace('</head>', CSS + '</head>', 1)
def news_items(path, test):
    """(url, headline, meta) for the stories on a saved players page that pass test(item html)."""
    s = open(path).read(); s = s[s.find('<section class="dbd-panel dbd-news'):]
    return [(u, t, re.sub(r'<[^>]+>', '', m)) for li, u, t, m in re.findall(r'(<li[^>]*><a class="nw-h" href="([^"]+)"[^>]*>(.*?)</a><span class="nw-m">(.*?)</span></li>)', s, re.S) if test(li)]

# ---------- 1. an upcoming game: a preview mark in the game box; the preview and the news naming both teams at the end
pv = next(v for v in F['preview10'] if 'Vikings' in v['title'] and 'Packers' in v['title'])
both = []
for u, t, m in news_items('_team-min-players.html', lambda li: 'Packers' in li) + news_items('_team-gb-players.html', lambda li: 'Vikings' in li):
    if u not in [x[0] for x in both]: both.append((u, t, m))
g = open('_game-2026-week-10-vikings-at-packers.html').read()
i = g.find('<aside class="dbd-panel dbd-gamebox">'); j = g.find('</aside>', i)
g = g[:j] + f'<a class="dbd-vmk mock-new" href="{YT(pv["id"])}" target="_blank" rel="noopener">Watch the NFL preview</a>' + g[j:]
lst = ''.join(f'<li><a href="{u}" target="_blank" rel="nofollow noopener">{t}</a><span class="ar-m">{e(m)}</span></li>' for u, t, m in both[1:3])
blk = (f'<section class="dbd-around mock-new" id="around"><div class="ar-news"><h2 class="ar-t">Before the game</h2>'
       + (f'<a class="ar-lead" href="{both[0][0]}" target="_blank" rel="nofollow noopener">{both[0][1]}</a><span class="ar-m">{e(both[0][2])}</span><ul class="ar-more">{lst}</ul>' if both else '<p>No stories naming both teams this week.</p>')
       + f'</div><div class="ar-vid"><h2 class="ar-t">The NFL preview</h2>{play(pv["id"], pv["title"], True)}<h3>{e(pv["title"])}</h3><span class="ar-m">NFL on YouTube</span></div></section>')
g = end(g, note('An upcoming game: "Watch the NFL preview" at the foot of the game box (the one hint near the top), and at the end of the page, after the stats, the news naming both teams with the preview beside it. After the game the same spots carry the recap.') + blk)
open('game-preview.html', 'w').write(no_tracking(css(g)))

# ---------- 2. a player: a pill to his newest highlights, a mark on that week's game log row, his news and videos at the end
ph = next(v for v in F['players9'] if v['title'].startswith("Josh Allen"))
p = open('_player-josh-allen.html').read()
p = p.replace('<div class="dbd-pills">', f'<div class="dbd-pills"><a class="dbd-pill dbd-vmk mock-new" href="{YT(ph["id"])}" target="_blank" rel="noopener">Week 9 highlights</a>', 1)
p = re.sub(r'(<tr><td>9</td><td class="res">.*?\d+-\d+)', lambda m: m.group(1) + f'<a class="dbd-vmk mock-new" href="{YT(ph["id"])}" target="_blank" rel="noopener" title="{e(ph["title"])}">Highlights</a>', p, flags=re.S)  # every view of the log (each is its own table)
mine = news_items('_team-buf-players.html', lambda li: 'player/josh-allen/"' in li)
lst = ''.join(f'<li><a href="{u}" target="_blank" rel="nofollow noopener">{t}</a><span class="ar-m">{e(m)}</span></li>' for u, t, m in mine[1:3])
blk = (f'<section class="dbd-around mock-new" id="around"><div class="ar-news"><h2 class="ar-t">Josh Allen in the news</h2>'
       + (f'<a class="ar-lead" href="{mine[0][0]}" target="_blank" rel="nofollow noopener">{mine[0][1]}</a><span class="ar-m">{e(mine[0][2])}</span><ul class="ar-more">{lst}</ul>' if mine else '')
       + f'</div><div class="ar-vid"><h2 class="ar-t">His highlights</h2>{play(ph["id"], ph["title"], True)}<h3>{e(ph["title"])}</h3><span class="ar-m">NFL on YouTube</span></div></section>')
p = end(p, note('A player: "Week 9 highlights" with his pills at the top, a Highlights mark on that week\'s row of his game log (open the game log tab), and at the end of the page the stories naming him with his newest highlights beside them. A player the NFL hasn\'t made a highlights video of shows only the news.') + blk)
open('player-allen.html', 'w').write(no_tracking(css(p)))

# ---------- 3. the offense page: jump links down to the block at the end
t = open('_team-min.html').read()
t = re.sub(r'(<p class="dbd-sub t-stagger-line t-stagger-line--3">.*?</p>)', lambda m: m.group(1) + '<nav class="dbd-jump mock-new" aria-label="Further down this page"><a href="#around">News <b>40</b></a><a href="#around">Videos <b>12</b></a></nav>', t, count=1, flags=re.S)
t = t.replace('<div id="dbd-explorer"', note('The offense and defense pages: two small jump links under the title, down to the news and video block after the explorer. Nothing else moves.') + '<div id="dbd-explorer"', 1)
open('team-jump.html', 'w').write(no_tracking(css(t)))

# ---------- 4. the matchups list: a play mark on each game with a preview
mu = open('_matchups.html').read()
def nick_pair(row):
    return re.findall(r'<b>[A-Z]{2,3}</b><span>([^<]+)</span>', row)
def addmk(m):
    row = m.group(0); nk = nick_pair(row)
    if len(nk) < 2: return row
    v = next((x for x in F['preview10'] if nk[0].split(' ·')[0] in x['title'] and nk[1].split(' ·')[0] in x['title']), None)
    if not v: return row
    return row.replace('<td><a class="dbd-go"', f'<td><a class="dbd-vmk" href="{YT(v["id"])}" target="_blank" rel="noopener">Preview</a></td><td><a class="dbd-go"', 1)
mu = re.sub(r'<tr class="dbd-g".*?</tr>', addmk, mu, flags=re.S)
mu = re.sub(r'(<main class="dbd-content"[^>]*>)', lambda m: m.group(1) + note('The matchups list: a small "Preview" mark on each game the NFL has posted a preview for. Once a game is final, the mark opens its recap.'), mu, count=1)
open('matchups-marks.html', 'w').write(no_tracking(css(mu)))

# ---------- 5. the week page: every game's recap at the end
wk = open('_week-9.html').read()
cards = ''.join(card(v['id'], v['title'], 'NFL on YouTube').replace('<figure class="dbd-vid">', '<figure class="dbd-vid vd-more">' if i >= 8 else '<figure class="dbd-vid">', 1) for i, v in enumerate(F['recap9']))
blk = (f'<section class="dbd-panel dbd-vids dbd-feature mock-new" id="highlights"><div class="dbd-ph big"><h2>Week 9 highlights</h2><p>Every game\'s recap from the NFL on YouTube</p></div>'
       f'<div class="vd-row">{cards}</div><button type="button" class="nw-btn" onclick="this.parentNode.classList.add(\'open\');this.remove()">Show all {len(F["recap9"])}</button></section>')
wk = end(wk, note('The week page: every game\'s recap at the end, after the week\'s numbers. Eight show, then "Show all".') + blk)
open('week-highlights.html', 'w').write(no_tracking(css(wk)))

# ---------- 6. the Tuesday email: a Highlights link on each score
em = open('../mockup-v23/email-week-8.html').read()
def emk(m):
    a = m.group(0); nk = re.findall(r'<b>([^<]+)</b> \d+, ([^<]+?) \d+</a>', a)
    if not nk: return a
    w, l = nk[0]
    v = next((x for x in F['recap8'] if w in x['title'] and l in x['title']), None)
    return a if not v else a + f' <a href="{YT(v["id"])}" style="margin-left:6px;font:600 13px/1.4 -apple-system,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#b4261f;text-decoration:underline">Highlights</a>'
em = re.sub(r'<a style="color:#121417;text-decoration:none;font:400 15px/1\.4[^"]*" href="https://downbydown\.com/game/[^"]+">.*?</a>', emk, em, flags=re.S)
em = em.replace('The Week 8 email (SCOPE §48)', 'The Week 8 email with a Highlights link on each score (SCOPE §54)')
open('email-highlights.html', 'w').write(em)
print('preview', pv['title'][:50], '| both-team stories', len(both), '| Allen stories', len(mine), '| matchups marks', mu.count('class="dbd-vmk"'), '| email links', em.count('>Highlights</a>'))
