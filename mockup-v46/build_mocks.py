"""Mock v46 (SCOPE §72): Post on X and Post on Bluesky buttons on the Share pack tab.
Reuses mockup-v44's admin CSS; data is the live Week 10 pack (_pack.json, pulled over SSH 2026-11-18). Writes tab.html + index.html."""
import html, json, os
from urllib.parse import quote

here = os.path.dirname(os.path.abspath(__file__))
D = json.load(open(os.path.join(here, '_pack.json')))
v44 = open(os.path.join(here, '..', 'mockup-v44', 'tab.html')).read()
css = v44[v44.index('<style>') + 7:v44.index('</style>')]
N = D['n']
NUM = {'swing': 1, 'snaps': 2, 'offense': 3, 'targets': 4}
LOGO = 'https://a.espncdn.com/i/teamlogos/nfl/500/{}.png'
NICK = {'IND': 'Colts', 'HOU': 'Texans', 'MIA': 'Dolphins', 'BUF': 'Bills'}
e = lambda s: html.escape(str(s), quote=True)
q = lambda s: quote(s, safe='')


def text_for(post, link, share):
    return post.replace(link, share)


def x_url(t): return 'https://x.com/intent/post?text=' + q(t)
def bs_url(t): return 'https://bsky.app/intent/compose?text=' + q(t)
def rd_url(sub, title, link): return 'https://www.reddit.com/r/%s/submit?url=%s&title=%s' % (sub, q(link), q(title))


def item(it):
    t = text_for(it['post'], it['link'], it['share'])
    return f'''<div class="dbd-box sp-item"><h2><span>{NUM[it['k']]}. {e(it['h'])}</span></h2><div class="sp-body">
<div class="sp-l"><div class="sp-card"><img src="https://downbydown.com/?card={e(it['card'])}" width="1200" height="630" alt=""></div><div class="sp-acts faded"><a class="button button-primary">Download image</a><a class="button">Open the page</a><a class="button sp-rd">Post to r/downbydown</a></div></div>
<div class="sp-r">
<div class="sp-f faded"><div class="sp-fh"><b>Reddit title</b><button type="button" class="button">Copy</button></div><textarea rows="2" readonly>{e(it['title'])}</textarea></div>
<div class="sp-f"><div class="sp-fh"><b>X and Bluesky</b><span class="cnt">{len(t)} characters with the full link</span><button type="button" class="button">Copy</button></div><textarea rows="3" readonly>{e(t)}</textarea>
<div class="sp-acts"><a class="button sp-x" href="{e(x_url(t))}" target="_blank" rel="noopener">Post on X</a><a class="button sp-bs" href="{e(bs_url(t))}" target="_blank" rel="noopener">Post on Bluesky</a></div></div>
</div></div></div>'''


def keys_box():
    rows = []
    for g in D['keys']:
        sides = []
        for sd in g['sides']:
            sub = sd['fits'][2:]
            sides.append(f'''<div class="sp-k"><div class="sp-kh"><img src="{LOGO.format(sd['t'].lower())}" alt=""><b>{NICK[sd['t']]} offense</b><span>{e(sd['fits'])}</span></div><p><b>{e(sd['head'].rstrip('.'))}.</b> {e(sd['say'])}</p><div class="sp-kb"><button type="button" class="button faded">Copy title</button><button type="button" class="button faded">Copy comment</button><button type="button" class="button faded">Copy for X and Bluesky</button><a class="button sp-rd faded">Post to r/downbydown</a><a class="button sp-rd faded">Post to {e(sd['fits'])}</a><a class="button sp-x" href="{e(x_url(sd['post']))}" target="_blank" rel="noopener">Post on X</a><a class="button sp-bs" href="{e(bs_url(sd['post']))}" target="_blank" rel="noopener">Post on Bluesky</a></div></div>''')
        rows.append(f'<div class="sp-g"><div class="sp-gc"><img src="https://downbydown.com/?card={e(g["card"])}" width="1200" height="630" alt="" loading="lazy"></div><div class="sp-gs"><b class="sp-gt">{NICK[g["away"]]} at {NICK[g["home"]]}</b>{"".join(sides)}</div></div>')
    return f'<div class="dbd-box sp-item"><h2><span>5. Keys to the game, Week {N + 1}</span><small>Every side gets Post on X and Post on Bluesky after its Reddit buttons.</small></h2><div class="sp-keys">{"".join(rows)}<p class="faded-note" style="padding:0 0 16px">… and the other games the same way.</p></div></div>'


extra = '''
.faded{opacity:.4}
.faded-note{color:#8c8f94;font-size:12px;margin:0}
.dbd-admin .sp-rd,.sp-rd{border-color:#cb2026;color:#cb2026}
.sp-x{border-color:#0f1419;color:#0f1419}
.sp-bs{border-color:#1185fe;color:#1185fe}
.sp-f .sp-acts{margin-top:8px}
.acc{max-width:1180px;margin:0 0 16px;display:flex;gap:10px 18px;align-items:center;flex-wrap:wrap}
.acc label{color:#50575e}.acc input{width:200px}
.test{max-width:1180px;box-sizing:border-box;margin:0 0 16px;padding:12px 14px;border:1px solid #c3c4c7;background:#fff;line-height:1.5}
.test ol{margin:6px 0 0;padding-left:20px}
'''
frame_top = v44[:v44.index('<div class="mock-note"')].replace(css, css + extra).replace('Week 10 · data built 6:12 AM · version 2.27.0', f'Week {N + 1} · version 2.30.0')

snaps = next(i for i in D['items'] if i['k'] == 'snaps')
tt = text_for(snaps['post'], snaps['link'], snaps['share'])
body = f'''<div class="mock-note" style="max-width:1180px"><b>Mock: Post on X and Post on Bluesky.</b> Each “X and Bluesky” text gets two buttons that open that site’s compose box with the post written, link included. The link is the same one the Reddit button uses, so snaps, targets and offense preview their own card. Two new fields set your accounts; the X handle also goes in every page’s <code>twitter:site</code> tag. Everything else is unchanged and shown faded.</div>
<nav class="nav-tab-wrapper"><a class="nav-tab">Overview</a><a class="nav-tab">Week in review</a><a class="nav-tab">News and videos</a><a class="nav-tab">Site</a><a class="nav-tab">Data</a><a class="nav-tab">Growth</a><a class="nav-tab on">Share pack</a></nav>
<div class="test"><b>Two tests before we build (from your own browser, signed in):</b>
<ol><li><a href="{e(x_url(tt))}" target="_blank" rel="noopener">Open the X test</a>: the compose box should hold the Chimere Dike post with its link.</li>
<li><a href="{e(bs_url(tt))}" target="_blank" rel="noopener">Open the Bluesky test</a>: the same post, and Bluesky should offer a link card for the link.</li></ol>
Don’t post either one; tell me what was filled in and whether a preview card showed.</div>
<div class="acc"><span><label for="s">Your subreddit</label> r/<input id="s" value="downbydown" class="faded"></span><span><label for="x">X account</label> @<input id="x" value="dxd_NFLfootball"></span><span><label for="b">Bluesky</label> <input id="b" value="downbydown.bsky.social"></span><button type="button" class="button">Save</button></div>
{''.join(item(it) for it in D['items'])}
{keys_box()}
</div></div></body></html>'''

open(os.path.join(here, 'tab.html'), 'w').write(frame_top + body)
open(os.path.join(here, 'index.html'), 'w').write('''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock v46: Post on X and Bluesky</title>
<style>body{margin:0;background:#f6f7f7;color:#1d2327;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}main{max-width:640px;margin:0 auto;padding:32px 16px}a{color:#2271b1}</style></head>
<body><main><h1 style="font-size:22px;font-weight:600">Mock v46: Post on X and Bluesky</h1><p>SCOPE §72. The Share pack tab with Post on X and Post on Bluesky buttons, using the live Week 10 pack.</p><p><a href="tab.html">Open the Share pack tab mock</a></p></main></body></html>''')
print('ok', len(frame_top + body))
