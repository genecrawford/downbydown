"""Mock: news and videos that stand out (Gene, 2026-11-12: "they get really lost when you scan the page"). Run from mockup-v26/.
Inputs saved from the live site on 2026-11-12 (2.12.0): _team-min.html (the Vikings offense page), _tp-min.html (the players page)."""
import re, html
e = html.escape

def no_tracking(h):
    h = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', h)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', h, flags=re.S)

CSS = '''<style>
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}
/* the offense and defense pages: a news and video block under the title, in place of the one-line Latest */
.dbd .dbd-around{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(0,1fr);gap:0;margin:18px 0 22px;background:#fff;border:1px solid var(--dbd-line-2);border-top:3px solid var(--dbd-team,#161e36);border-radius:6px;overflow:hidden}
.dbd .dbd-around>div{padding:16px 20px 18px;min-width:0}
.dbd .dbd-around>div+div{border-left:1px solid var(--dbd-line)}
.dbd .ar-t{margin:0 0 10px;padding:0;font:700 20px/1.1 var(--dbd-display);letter-spacing:.04em;text-transform:uppercase;color:var(--dbd-ink)}
.dbd .ar-lead{display:block;font:600 21px/1.28 var(--dbd-body);color:var(--dbd-ink);text-decoration:none}
.dbd .ar-lead:hover,.dbd .ar-more a:hover,.dbd .ar-vid h3 a:hover{color:var(--dbd-red-ink);text-decoration:underline;text-underline-offset:3px}
.dbd .ar-m{display:block;margin:5px 0 0;font:500 11px/1.4 var(--dbd-mono);letter-spacing:.04em;text-transform:uppercase;color:var(--dbd-muted)}
.dbd .ar-m a{color:var(--dbd-ink-2)}
.dbd .ar-more{margin:12px 0 0;padding:0;list-style:none}
.dbd .ar-more li{margin:0;padding:9px 0;border-top:1px solid var(--dbd-line)}
.dbd .ar-more a{font:600 15px/1.35 var(--dbd-body);color:var(--dbd-ink);text-decoration:none}
.dbd .ar-all{display:inline-block;margin-top:12px;padding:7px 12px;border:1px solid var(--dbd-ink);border-radius:4px;font:600 13px/1 var(--dbd-body);color:var(--dbd-ink);text-decoration:none}
.dbd .ar-all:hover{background:var(--dbd-ink);color:#fff}
.dbd .ar-vid h3{margin:10px 0 0;padding:0;font:600 16px/1.35 var(--dbd-body)}
.dbd .ar-vid h3 a{color:var(--dbd-ink);text-decoration:none}
@media (max-width:760px){.dbd .dbd-around{grid-template-columns:minmax(0,1fr)}.dbd .dbd-around>div+div{border-left:0;border-top:1px solid var(--dbd-line)}.dbd .ar-lead{font-size:19px}}
/* the players page: jump links under the title */
.dbd .dbd-jump{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0 0}
.dbd .dbd-jump a{display:inline-flex;align-items:baseline;gap:6px;padding:7px 12px;border:1px solid var(--dbd-ink);border-radius:4px;font:700 14px/1 var(--dbd-display);letter-spacing:.06em;text-transform:uppercase;color:var(--dbd-ink);text-decoration:none;background:#fff}
.dbd .dbd-jump a b{font:600 11px/1 var(--dbd-mono);color:var(--dbd-muted)}
.dbd .dbd-jump a:hover{background:var(--dbd-ink);color:#fff}.dbd .dbd-jump a:hover b{color:#cfd2d8}
/* the players page: news and videos as the page's two big sections */
.dbd .dbd-vids,.dbd .dbd-news{background:#fff;border-top:3px solid var(--dbd-team,#161e36)}
.dbd .dbd-ph.big{display:block;padding:16px var(--dbd-edge) 4px;border-bottom:0}
.dbd .dbd-ph.big h2{margin:0;padding:0;font:700 26px/1.05 var(--dbd-display);letter-spacing:.03em;text-transform:uppercase;color:var(--dbd-ink)}
.dbd .dbd-ph.big p{margin:4px 0 0;padding:0;font:400 14px/1.4 var(--dbd-body);color:var(--dbd-muted)}
.dbd .dbd-vids .vd-grid{grid-template-columns:repeat(4,minmax(0,1fr));gap:18px 18px}
.dbd .dbd-vids .vd-grid>.dbd-vid:first-child{grid-column:span 2;grid-row:span 2}
.dbd .dbd-vids .vd-grid>.dbd-vid:first-child h4{font-size:19px;line-height:1.28;-webkit-line-clamp:3}
.dbd .dbd-vid h4{font-size:15px}
.dbd .dbd-news h3{font-size:14px}
.dbd .dbd-news .nw-h{font-size:16px;line-height:1.35}
.dbd .dbd-news li:first-child .nw-h{font-size:19px;line-height:1.28}
@media (max-width:900px){.dbd .dbd-vids .vd-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dbd .dbd-vids .vd-grid>.dbd-vid:first-child{grid-column:span 2;grid-row:auto}.dbd .dbd-ph.big h2{font-size:22px}}
</style>'''

def note(t): return f'<div class="mock-note"><b>Mock.</b> {t}</div>'

tp = open('_tp-min.html').read()
# the newest stories and video, read from the players page's panels
news = re.findall(r'<li[^>]*><a class="nw-h" href="([^"]+)"[^>]*>(.*?)</a><span class="nw-m">(.*?)</span></li>', tp[tp.find('dbd-news'):tp.find('dbd-roster')], re.S)[:3]
vid = re.search(r'<figure class="dbd-vid">(<button.*?</button>)<h4>(.*?)</h4><span>(.*?)</span></figure>', tp, re.S)
vn = len(re.findall(r'<figure class="dbd-vid', tp)); nn = len(re.findall(r'class="nw-h"', tp))

# ---- the offense page
tm = open('_team-min.html').read()
more = ''.join(f'<li><a href="{u}" target="_blank" rel="nofollow noopener">{t}</a><span class="ar-m">{re.sub(r"<[^>]+>", "", m)}</span></li>' for u, t, m in news[1:3])
lead_u, lead_t, lead_m = news[0]
around = (f'<section class="dbd-around"><div class="ar-news"><h2 class="ar-t">Vikings news</h2>'
          f'<a class="ar-lead" href="{lead_u}" target="_blank" rel="nofollow noopener">{lead_t}</a><span class="ar-m">{lead_m}</span>'
          f'<ul class="ar-more">{more}</ul><a class="ar-all" href="https://downbydown.com/team/min/players/#news">All Vikings news</a></div>'
          f'<div class="ar-vid"><h2 class="ar-t">Newest video</h2>{vid.group(1)}<h3><a href="https://downbydown.com/team/min/players/#videos">{vid.group(2)}</a></h3><span class="ar-m">{vid.group(3)} · <a href="https://downbydown.com/team/min/players/#videos">All Vikings videos</a></span></div></section>')
tm = re.sub(r'<p class="dbd-latest">.*?</p>', '', tm, count=1, flags=re.S)
i = tm.rfind('</main>')  # after the explorer and everything else: news and video are the page's secondary content
tm = tm[:i] + note('The one-line "Latest" under the title becomes a block of its own at the end of the page, after the explorer: the newest headline in large type, the next two under it, and the newest video beside it. Same on the defense page.') + around + tm[i:]
tm = tm.replace('</head>', CSS + '</head>', 1)
open('team-MIN.html', 'w').write(no_tracking(tm))

# ---- the players page
jump = f'<nav class="dbd-jump" aria-label="On this page"><a href="#videos">Videos <b>{vn}</b></a><a href="#news">News <b>{nn}</b></a></nav>'
tp = re.sub(r'(<p class="dbd-sub t-stagger-line t-stagger-line--3">.*?</p>)', lambda m: m.group(1) + jump, tp, count=1, flags=re.S)
tp = re.sub(r'<div class="dbd-ph"><span class="dbd-lab"><b>Vikings videos</b> · (.*?)</span></div>', lambda m: f'<div class="dbd-ph big"><h2>Vikings videos</h2><p>{m.group(1)[:1].upper() + m.group(1)[1:]}</p></div>', tp, count=1)
tp = re.sub(r'<div class="dbd-ph"><span class="dbd-lab"><b>Vikings news</b> · (.*?)</span></div>', lambda m: f'<div class="dbd-ph big"><h2>Vikings news</h2><p>{m.group(1)[:1].upper() + m.group(1)[1:]}</p></div>', tp, count=1)
# videos and news move to the end of the page, after the roster and movers: secondary to the stats
vi = tp.find('<section class="dbd-panel dbd-vids"'); ri = tp.find('<section class="dbd-panel dbd-roster"')
block = tp[vi:ri]; tp = tp[:vi] + tp[ri:]
mi = tp.rfind('</main>'); tp = tp[:mi] + block + tp[mi:]
# the first video large and four beside it: the fifth shows before Show more
tp = re.sub(r'<figure class="dbd-vid vd-more">', '<figure class="dbd-vid">', tp, count=1)
tp = tp.replace('<section class="dbd-panel dbd-vids"', note('News and videos move to the end of the page, after the roster and movers; small jump links under the title point down to them. They get the page\'s biggest headings, a white panel with a team-color top rule, and their newest item larger: the first video spans two columns with four beside it, and each news column opens with its newest headline in large type.') + '<section class="dbd-panel dbd-vids"', 1)
tp = tp.replace('</head>', CSS + '</head>', 1)
open('players-MIN.html', 'w').write(no_tracking(tp))
print('news', len(news), 'video', bool(vid), 'counts', vn, nn)
