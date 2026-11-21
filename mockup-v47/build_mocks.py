"""Mock v47 (SCOPE §73): Share on Facebook and Copy for Instagram on the Share pack tab.
Reuses mockup-v44's admin CSS; data is the live Week 10 pack (_pack.json, from mockup-v46). Writes tab.html + index.html."""
import html, json, os
from urllib.parse import quote

here = os.path.dirname(os.path.abspath(__file__))
D = json.load(open(os.path.join(here, '_pack.json')))
v44 = open(os.path.join(here, '..', 'mockup-v44', 'tab.html')).read()
css = v44[v44.index('<style>') + 7:v44.index('</style>')]
N = D['n']
NUM = {'swing': 1, 'snaps': 2, 'offense': 3, 'targets': 4}
TAGS = {'swing': ['#NFL', '#Commanders', '#Giants'], 'snaps': ['#NFL', '#Titans', '#FantasyFootball'], 'offense': ['#NFL', '#49ers', '#Cardinals'], 'targets': ['#NFL', '#Texans', '#FantasyFootball']}
NICK = {'IND': 'Colts', 'HOU': 'Texans', 'MIA': 'Dolphins', 'BUF': 'Bills'}
LOGO = 'https://a.espncdn.com/i/teamlogos/nfl/500/{}.png'
e = lambda s: html.escape(str(s), quote=True)
fb = lambda link: 'https://www.facebook.com/sharer/sharer.php?u=' + quote(link, safe='')


def caption(text, tags):
    return text + '\n\nFull chart: link in bio (downbydown.com)\n\n' + ' '.join(tags)


def item(it):
    cap = caption(it['title'], TAGS[it['k']])
    return f'''<div class="dbd-box sp-item"><h2><span>{NUM[it['k']]}. {e(it['h'])}</span></h2><div class="sp-body">
<div class="sp-l"><div class="sp-card"><img src="https://downbydown.com/?card={e(it['card'])}" width="1200" height="630" alt=""></div><div class="sp-acts"><a class="button button-primary">Download image</a><a class="button faded">Open the page</a><a class="button sp-rd faded">Post to r/downbydown</a></div></div>
<div class="sp-r">
<div class="sp-f faded"><div class="sp-fh"><b>X and Bluesky</b><button type="button" class="button">Copy</button></div><textarea rows="2" readonly>{e(it['post'])}</textarea></div>
<div class="sp-acts sp-so"><a class="button sp-x faded">Post on X</a><a class="button sp-bs faded">Post on Bluesky</a><a class="button sp-fb" href="{e(fb(it['share']))}" target="_blank" rel="noopener" data-t="{e(it['title'])}">Share on Facebook</a></div>
<div class="sp-f"><div class="sp-fh"><b>Instagram caption</b><span class="cnt">post it with the image</span><button type="button" class="button">Copy</button></div><textarea rows="6" readonly>{e(cap)}</textarea></div>
</div></div></div>'''


def keys_box():
    rows = []
    for g in D['keys']:
        sides = []
        for sd in g['sides']:
            opp = g['home'] if sd['t'] == g['away'] else g['away']
            cap = caption(sd['post'].rsplit(' https://', 1)[0], ['#NFL', '#' + NICK[sd['t']], '#' + NICK[opp]])
            sides.append(f'''<div class="sp-k"><div class="sp-kh"><img src="{LOGO.format(sd['t'].lower())}" alt=""><b>{NICK[sd['t']]} offense</b><span>{e(sd['fits'])}</span></div><p><b>{e(sd['head'].rstrip('.'))}.</b> {e(sd['say'])}</p><div class="sp-kb"><button type="button" class="button faded">Copy title</button><button type="button" class="button faded">Copy comment</button><button type="button" class="button faded">Copy for X and Bluesky</button><a class="button sp-rd faded">Post to r/downbydown</a><a class="button sp-rd faded">Post to {e(sd['fits'])}</a><a class="button sp-x faded">Post on X</a><a class="button sp-bs faded">Post on Bluesky</a><a class="button sp-fb" href="{e(fb(sd['link']))}" target="_blank" rel="noopener">Share on Facebook</a><button type="button" class="button sp-ig" title="{e(cap)}">Copy for Instagram</button></div></div>''')
        rows.append(f'<div class="sp-g"><div class="sp-gc"><img src="https://downbydown.com/?card={e(g["card"])}" width="1200" height="630" alt="" loading="lazy"></div><div class="sp-gs"><b class="sp-gt">{NICK[g["away"]]} at {NICK[g["home"]]}</b>{"".join(sides)}</div></div>')
    return f'<div class="dbd-box sp-item"><h2><span>5. Keys to the game, Week {N + 1}</span><small>Decision 1: each side also gets Share on Facebook and Copy for Instagram (hover it in the mock to see the caption).</small></h2><div class="sp-keys">{"".join(rows)}</div></div>'


extra = '''
.faded{opacity:.4}
.sp-rd{border-color:#cb2026;color:#cb2026}.sp-x{border-color:#0f1419;color:#0f1419}.sp-bs{border-color:#1185fe;color:#1185fe}
.sp-fb{border-color:#0866ff;color:#0866ff}.sp-ig{border-color:#c13584;color:#c13584}
.sp-so{margin:-6px 0 14px}
.acc{max-width:1180px;margin:0 0 16px;display:flex;gap:10px 18px;align-items:center;flex-wrap:wrap}.acc label{color:#50575e}.acc input{width:190px}
.acc a{margin-left:-8px}
'''
frame_top = v44[:v44.index('<div class="mock-note"')].replace(css, css + extra).replace('Week 10 · data built 6:12 AM · version 2.27.0', f'Week {N + 1} · version 2.31.0')
body = f'''<div class="mock-note" style="max-width:1180px"><b>Mock: Share on Facebook and Copy for Instagram.</b> <b>Share on Facebook</b> copies the post text and opens Facebook’s share box with the link and its card; choose “Share to a group”, pick Down by Down, and paste. <b>Instagram caption</b> is the takeaway, “Full chart: link in bio”, and hashtags, to post with the downloaded image. Two new fields set the group and the Instagram account. Everything else is unchanged and shown faded. (The preview pane doesn’t open links; the Facebook test link is in the chat.)</div>
<nav class="nav-tab-wrapper"><a class="nav-tab">Overview</a><a class="nav-tab">Week in review</a><a class="nav-tab">News and videos</a><a class="nav-tab">Site</a><a class="nav-tab">Data</a><a class="nav-tab">Growth</a><a class="nav-tab on">Share pack</a></nav>
<div class="acc"><span class="faded"><label>Your subreddit</label> r/<input value="downbydown"></span><span class="faded"><label>X account</label> @<input value="dxd_NFLfootball"></span><span class="faded"><label>Bluesky</label> <input value="downbydown.bsky.social"></span>
<span><label for="f">Facebook group</label> <input id="f" value="https://www.facebook.com/groups/988685080499981" style="width:300px"></span><a href="https://www.facebook.com/groups/988685080499981" target="_blank" rel="noopener">Open the group</a>
<span><label for="i">Instagram</label> @<input id="i" value="down_by_down"></span><button type="button" class="button">Save</button></div>
{''.join(item(it) for it in D['items'])}
{keys_box()}
</div></div></body></html>'''
open(os.path.join(here, 'tab.html'), 'w').write(frame_top + body)
open(os.path.join(here, 'index.html'), 'w').write('''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock v47: Facebook and Instagram</title>
<style>body{margin:0;background:#f6f7f7;color:#1d2327;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}main{max-width:640px;margin:0 auto;padding:32px 16px}a{color:#2271b1}</style></head>
<body><main><h1 style="font-size:22px;font-weight:600">Mock v47: Share on Facebook and Copy for Instagram</h1><p>SCOPE §73, using the live Week 10 pack.</p><p><a href="tab.html">Open the Share pack tab mock</a></p></main></body></html>''')
print('ok', fb(next(i for i in D['items'] if i['k'] == 'snaps')['share']))
