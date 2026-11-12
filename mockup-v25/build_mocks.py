"""SCOPE §51 (team videos and game highlights) and §52 (tickets) mocks. Run from mockup-v25/ after videos_data.py:  python3 build_mocks.py
Inputs saved from the live site on 2026-11-12: _team-min-players.html, _team-min.html, _game-2026-week-9-bills-at-vikings.html,
_game-2026-week-10-vikings-at-packers.html, _matchups.html, and videos.json (YouTube's public feeds). Ticket prices are samples:
SeatGeek's API needs a key the site doesn't have yet."""
import json, re, html, datetime
e = html.escape
V = json.load(open('videos.json')); NOW = V['built']

def no_tracking(h):
    """The copied live pages lose the site's analytics, so opening a mock never counts as a visit."""
    h = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', h)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', h, flags=re.S)

def age(ts):
    d = NOW - ts
    if d < 3600: return f'{max(1, d // 60)}m'
    if d < 86400: return f'{d // 3600}h'
    if d < 7 * 86400: return f'{d // 86400}d'
    return datetime.datetime.fromtimestamp(ts).strftime('%b %-d')

CSS = '''<style>
.dbd .mock-note{max-width:980px;margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}
.dbd .mock-new{outline:1px dashed var(--dbd-red);outline-offset:4px}
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
/* videos (§51) */
.dbd .dbd-vids .vd-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px 16px;padding:12px var(--dbd-edge) 16px}
.dbd .dbd-vids .vd-more{display:none}.dbd .dbd-vids.open .vd-more{display:block}
.dbd .dbd-vid{margin:0}
.dbd .dbd-play{position:relative;display:block;width:100%;aspect-ratio:16/9;padding:0;border:0;border-radius:4px;overflow:hidden;background:#0f1311;cursor:pointer}
.dbd .dbd-play img{display:block;width:100%;height:100%;object-fit:cover}
.dbd .dbd-play::after{content:"";position:absolute;left:10px;bottom:10px;width:34px;height:24px;border-radius:5px;background:rgba(15,19,17,.82) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 10 10'%3E%3Cpath d='M3 2l5 3-5 3z' fill='%23fff'/%3E%3C/svg%3E") center/12px no-repeat}
.dbd .dbd-play:hover::after,.dbd .dbd-play:focus-visible::after{background-color:var(--dbd-red)}
.dbd .dbd-play iframe{display:block;width:100%;height:100%;border:0}
.dbd .dbd-vid h4{margin:8px 0 0;font:600 14px/1.35 var(--dbd-body);color:var(--dbd-ink);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.dbd .dbd-vid span{display:block;margin-top:3px;font:500 11px/1.4 var(--dbd-mono);letter-spacing:.04em;text-transform:uppercase;color:var(--dbd-muted)}
.dbd .dbd-vids .nw-btn{margin:0 var(--dbd-edge) 14px;padding:6px 12px;background:none;border:1px solid var(--dbd-line-2);border-radius:4px;font:600 13px var(--dbd-body);color:var(--dbd-ink);cursor:pointer}
.dbd .dbd-hl .dbd-pb{padding:12px var(--dbd-edge) 14px}
.dbd .dbd-hl .dbd-play{max-width:760px}
.dbd .dbd-hl p{margin:8px 0 0;font:400 14px/1.5 var(--dbd-body);color:var(--dbd-ink-2)}
@media (max-width:900px){.dbd .dbd-vids .vd-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
/* tickets (§52) */
.dbd .dbd-tix{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 10px;margin:12px 0 0;padding-top:10px;border-top:1px solid var(--dbd-line)}
.dbd .dbd-tix a{font:600 15px/1.3 var(--dbd-body);color:var(--dbd-ink);text-decoration:underline;text-underline-offset:3px}
.dbd .dbd-tix a:hover{color:var(--dbd-red-ink)}
.dbd .dbd-tix span,.dbd .dbd-next span{font:500 11px/1.4 var(--dbd-mono);letter-spacing:.04em;text-transform:uppercase;color:var(--dbd-muted)}
.dbd .dbd-next{margin:6px 0 0;font:400 14px/1.5 var(--dbd-body);color:var(--dbd-ink-2)}
.dbd .dbd-next a{color:var(--dbd-ink)}
.dbd .dbd-ln .tx{display:block;margin-top:3px;font:500 12px/1.3 var(--dbd-body);color:var(--dbd-ink-2);text-decoration:underline;text-underline-offset:2px}
</style>
<script>document.addEventListener('click',function(ev){var b=ev.target.closest('.dbd-play');if(!b||b.querySelector('iframe'))return;var f=document.createElement('iframe');f.src='https://www.youtube-nocookie.com/embed/'+b.dataset.v+'?autoplay=1&rel=0&modestbranding=1';f.title=b.getAttribute('aria-label');f.allow='autoplay; encrypted-media; picture-in-picture; fullscreen';f.allowFullscreen=true;b.innerHTML='';b.appendChild(f);});</script>'''

def note(t): return f'<div class="mock-note"><b>Mock.</b> {t}</div>'

def play(v, big=False):
    img = f'https://i.ytimg.com/vi/{v["id"]}/{"hqdefault" if big else "mqdefault"}.jpg'
    return (f'<button type="button" class="dbd-play" data-v="{e(v["id"])}" aria-label="Play: {e(v["title"])}">'
            f'<img src="{img}" alt="" loading="lazy" width="{480 if big else 320}" height="{270 if big else 180}"></button>')

# ---------- §51: the Vikings' videos on their players page ----------
vids = [dict(v, src='Vikings') for v in V['team'] if not v['short']] + [dict(v, src='NFL') for v in V['nfl'] if not v['short']]
vids.sort(key=lambda v: -v['ts'])
cards = ''.join(f'<figure class="dbd-vid{" vd-more" if i >= 4 else ""}">{play(v)}<h4>{e(v["title"])}</h4><span>{v["src"]} · {age(v["ts"])}</span></figure>' for i, v in enumerate(vids[:12]))
panel = (f'<section class="dbd-panel dbd-vids mock-new" id="videos"><div class="dbd-ph"><span class="dbd-lab"><b>Vikings videos</b> · the last 14 days, from the Vikings and the NFL on YouTube</span></div>'
         f'<div class="vd-grid">{cards}</div>'
         + (f'<button type="button" class="nw-btn" onclick="this.parentNode.classList.add(\'open\');this.remove()">Show more</button>' if len(vids) > 4 else '') + '</section>')
tp = open('_team-min-players.html').read()
tp = tp.replace('<section class="dbd-panel dbd-news"', panel + '<section class="dbd-panel dbd-news"', 1)
tp = tp.replace('<section class="dbd-panel dbd-tabbed dbd-lu"', note('SCOPE §51. The Vikings\' newest videos (Shorts left out) from their channel and the NFL\'s, above the news. Only thumbnails load; a click plays the video in place with YouTube\'s player. Real videos from the feeds on Nov 12. The dashed outline marks what\'s new.') + '<section class="dbd-panel dbd-tabbed dbd-lu"', 1)
tp = tp.replace('</head>', CSS + '</head>', 1)
open('videos-MIN.html', 'w').write(no_tracking(tp))

# ---------- §51: a finished game's highlights ----------
hl = V['highlight']
hp = (f'<section class="dbd-panel dbd-hl mock-new"><div class="dbd-ph"><span class="dbd-lab"><b>Highlights</b> · from the NFL on YouTube</span></div>'
      f'<div class="dbd-pb">{play(hl, True)}<p>{e(hl["title"])}</p></div></section>')
g9 = open('_game-2026-week-9-bills-at-vikings.html').read()
g9 = g9.replace('<section class="dbd-panel dbd-wp"', note('SCOPE §51. A finished game\'s page opens with its highlights from the NFL\'s weekly Game Recaps playlist, matched to the game by its two teams. Real video.') + hp + '<section class="dbd-panel dbd-wp"', 1)
g9 = g9.replace('</head>', CSS + '</head>', 1)
open('game-highlights.html', 'w').write(no_tracking(g9))

# ---------- §52: tickets on an upcoming game ----------
tix = ('<div class="dbd-tix mock-new"><a href="https://seatgeek.com/" target="_blank" rel="nofollow sponsored noopener">Tickets from <b>$112</b> on SeatGeek</a>'
       '<span>checked 2h ago</span></div>')
g10 = open('_game-2026-week-10-vikings-at-packers.html').read()
i = g10.find('<aside class="dbd-panel dbd-gamebox">'); j = g10.find('</aside>', i)
g10 = g10[:j] + tix + g10[j:]
g10 = g10.replace('<section class="dbd-panel dbd-keys"', note('SCOPE §52. An upcoming game\'s box gets the cheapest ticket on SeatGeek, linking to the game there. The price is a sample: SeatGeek\'s data needs a key the site doesn\'t have yet.') + '<section class="dbd-panel dbd-keys"', 1)
g10 = g10.replace('</head>', CSS + '</head>', 1)
open('tickets-game.html', 'w').write(no_tracking(g10))

# ---------- §52: the team page's next games ----------
nxt = ('<p class="dbd-next mock-new">Next game: <a href="https://downbydown.com/game/2026-week-10-vikings-at-packers/">at Packers</a>, Sun Nov 15, 1:00 PM · '
       '<a href="https://seatgeek.com/" target="_blank" rel="nofollow sponsored noopener">tickets from $112</a>. '
       'Next home game: <a href="https://downbydown.com/game/2026-week-12-falcons-at-vikings/">Falcons</a>, Sun Nov 29 · '
       '<a href="https://seatgeek.com/" target="_blank" rel="nofollow sponsored noopener">from $96</a> <span>SeatGeek, checked 2h ago</span></p>')
tm = open('_team-min.html').read()
tm = re.sub(r'(<p class="dbd-latest">.*?</p>)', lambda m: m.group(1) + nxt, tm, count=1, flags=re.S)
tm = tm.replace('</head>', CSS + '</head>', 1)
tm = re.sub(r'(<main class="dbd-content"[^>]*>)', lambda m: m.group(1) + note('SCOPE §52. Under the news line: the next game and the next home game, each with its cheapest SeatGeek ticket. Sample prices.'), tm, count=1)
open('tickets-team.html', 'w').write(no_tracking(tm))

# ---------- §52: the matchups list ----------
mu = open('_matchups.html').read()
prices = iter([84, 112, 67, 143, 95, 71, 129, 88, 156, 63, 102, 77, 118, 90, 135, 59])
def addtix(m):
    if 'class="dbd-num "></td>' not in m.group(0): return m.group(0)  # a finished game keeps its score
    return re.sub(r'(<td class="dbd-ln">.*?)(</td>)', lambda x: x.group(1) + f'<a class="tx" href="https://seatgeek.com/" target="_blank" rel="nofollow sponsored noopener">Tickets from ${next(prices, 80)}</a>' + x.group(2), m.group(0), count=1, flags=re.S)
mu = re.sub(r'<tr class="dbd-g".*?</tr>', addtix, mu, flags=re.S)
mu = mu.replace('</head>', CSS + '</head>', 1)
mu = re.sub(r'(<main class="dbd-content"[^>]*>)', lambda m: m.group(1) + note('SCOPE §52. Each upcoming game in the list gets "Tickets from $X" under its line, and one line above the games says when the prices were checked. Sample prices.') + '<p class="dbd-next mock-new">Ticket prices from SeatGeek <span>checked 2h ago</span></p>', mu, count=1)
open('tickets-matchups.html', 'w').write(no_tracking(mu))
print('wrote videos-MIN.html game-highlights.html tickets-game.html tickets-team.html tickets-matchups.html;', len(vids), 'videos')
