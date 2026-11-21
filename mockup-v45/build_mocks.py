"""Mock v45 (SCOPE §71): Post to r/downbydown buttons on the Share pack tab.
Reuses mockup-v44's admin CSS; data is the live Week 10 pack (_pack.json, pulled over SSH 2026-11-18). Writes tab.html + index.html."""
import html, json, os, re
from urllib.parse import quote

here = os.path.dirname(os.path.abspath(__file__))
D = json.load(open(os.path.join(here, '_pack.json')))
v44 = open(os.path.join(here, '..', 'mockup-v44', 'tab.html')).read()
css = v44[v44.index('<style>') + 7:v44.index('</style>')]
SUB = 'downbydown'
N = D['n']
NUM = {'swing': 1, 'snaps': 2, 'offense': 3, 'targets': 4}
WHY = {'swing': 'The week’s largest one-play change in win probability, on the game’s card with the swing marked.',
       'snaps': 'A player whose snap share jumped this week, with his weeks drawn.',
       'offense': 'Every offense that played, by EPA a play for the week, in one chart.',
       'targets': 'A player whose share of his team’s targets jumped this week.'}
LOGO = 'https://a.espncdn.com/i/teamlogos/nfl/500/{}.png'
NICK = {'IND': 'Colts', 'HOU': 'Texans', 'MIA': 'Dolphins', 'BUF': 'Bills', 'BAL': 'Ravens', 'CAR': 'Panthers'}
e = lambda s: html.escape(str(s), quote=True)


def share_link(it):
    """The page link the post points at. Pack-only cards (snaps, targets, offense) add ?share= so the page's preview image is that card."""
    return it['link'] + ('?share=' + it['card'] if it['card'].startswith('pack-') else '')


def reddit(title, url):
    return 'https://www.reddit.com/r/%s/submit?url=%s&title=%s' % (SUB, quote(url, safe=''), quote(title, safe=''))


def item(it):
    u = share_link(it)
    return f'''<div class="dbd-box sp-item"><h2><span>{NUM[it['k']]}. {e(it['h'])}</span><small>{e(WHY[it['k']])}</small></h2><div class="sp-body">
<div class="sp-l"><div class="sp-card"><img src="https://downbydown.com/?card={e(it['card'])}" width="1200" height="630" alt=""></div><div class="sp-acts"><a class="button button-primary" href="https://downbydown.com/?card={e(it['card'])}" download="{e(it['file'])}">Download image</a><a class="button" href="{e(it['link'])}" target="_blank" rel="noopener">Open the page</a></div></div>
<div class="sp-r">
<div class="rd"><div class="rd-h"><b>r/{SUB}</b><span>link post</span></div>
<p class="rd-t">{e(it['title'])}</p>
<p class="rd-u">{e(u)}</p>
<div class="sp-acts"><a class="button button-primary rd-go" href="{e(reddit(it['title'], u))}" target="_blank" rel="noopener">Post to r/{SUB}</a><span class="rd-n">Opens Reddit with the title and link filled in. The preview is this card.</span></div></div>
<div class="sp-f faded"><div class="sp-fh"><b>Title</b><button type="button" class="button cp">Copy</button></div><textarea rows="2" readonly>{e(it['title'])}</textarea></div>
<div class="sp-f faded"><div class="sp-fh"><b>First comment</b><button type="button" class="button cp">Copy</button></div><textarea rows="3" readonly>{e(it['comment'])}</textarea></div>
<p class="faded-note">Copy buttons, the X and Bluesky text and the “Fits” line stay as they are today (shown faded).</p>
</div></div></div>'''


def keys_box():
    rows = []
    for g in D['keys'][:2]:
        sides = []
        for t, s in zip((g['away'], g['home']), g['sides']):
            sides.append(f'''<div class="sp-k"><div class="sp-kh"><img src="{LOGO.format(t.lower())}" alt=""><b>{NICK[t]} offense</b></div><p>{e(s['title'])}</p><div class="sp-kb"><button type="button" class="button cp faded">Copy title</button><button type="button" class="button cp faded">Copy comment</button><button type="button" class="button cp faded">Copy for X and Bluesky</button><a class="button button-primary" href="{e(reddit(s['title'], s['link']))}" target="_blank" rel="noopener">Post to r/{SUB}</a></div></div>''')
        a, h = NICK[g['away']], NICK[g['home']]
        rows.append(f'<div class="sp-g"><div class="sp-gc"><img src="https://downbydown.com/?card={e(g["card"])}" width="1200" height="630" alt="" loading="lazy"></div><div class="sp-gs"><b class="sp-gt">{a} at {h}</b>{"".join(sides)}</div></div>')
    return f'''<div class="dbd-box sp-item"><h2><span>5. Keys to the game, Week {N + 1}</span><small>{D['nkeys']} games, two offenses each. Every side gets its own button; post the few you like.</small></h2><div class="sp-keys">{"".join(rows)}<p class="faded-note" style="padding:0 0 16px">… and the other {D['nkeys'] - 2} games the same way.</p></div></div>'''


extra = '''
.rd{border:1px solid #c3c4c7;padding:12px 14px;margin:0 0 16px;background:#fff}
.rd-h{display:flex;gap:10px;align-items:baseline;margin-bottom:6px}.rd-h span{color:#646970}
.rd-t{margin:0 0 4px;font-size:14px;line-height:1.45}
.rd-u{margin:0 0 10px;color:#646970;font-size:12px;word-break:break-all}
.rd-n{color:#646970;align-self:center}
.faded{opacity:.45}
.faded-note{color:#8c8f94;font-size:12px;margin:0}
.sub-row{max-width:1180px;margin:0 0 16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.sub-row input{width:180px}
.test{max-width:1180px;box-sizing:border-box;margin:0 0 16px;padding:12px 14px;border:1px solid #c3c4c7;background:#fff;line-height:1.5}
.test ol{margin:6px 0 0;padding-left:20px}
'''

frame_top = v44[:v44.index('<div class="mock-note"')]
frame_top = frame_top.replace(css, css + extra).replace('Week 10 · data built 6:12 AM · version 2.27.0', f'Week {N + 1} · data built 11:39 AM · version 2.29.0')

test_it = D['items'][1]
test_url = reddit(test_it['title'], test_it['link'])
body = f'''<div class="mock-note" style="max-width:1180px"><b>Mock: Post to r/{SUB}.</b> Each item gets one more button. It opens Reddit’s new-post form for your subreddit with the title and the link already filled in; you click Post. It’s a link post, so Reddit shows the card as the preview, pulled from the page. For snaps, targets and the offense chart the link carries <code>?share=…</code>, which makes the page’s preview that card (the swing and keys already use the game card). Everything else on the tab is unchanged and shown faded.</div>
<nav class="nav-tab-wrapper"><a class="nav-tab">Overview</a><a class="nav-tab">Week in review</a><a class="nav-tab">News and videos</a><a class="nav-tab">Site</a><a class="nav-tab">Data</a><a class="nav-tab">Growth</a><a class="nav-tab on">Share pack</a></nav>
<div class="test"><b>One test before we build (from your own browser, signed in to Reddit):</b> <a href="{e(test_url)}" target="_blank" rel="noopener">open this prefilled post</a>.
<ol><li>The form should open on r/{SUB} with the title “{e(test_it['title'][:60])}…” and the link to Chimere Dike’s page filled in.</li><li>Don’t post it; just tell me what was filled in and what wasn’t. Today the preview will be his player card, since <code>?share=</code> isn’t built yet.</li></ol></div>
<div class="sub-row"><label for="sub">Your subreddit</label> r/<input id="sub" value="{SUB}"> <button type="button" class="button">Save</button><span class="faded-note">The buttons post here. Clear it to hide them.</span></div>
<div class="sp-head"><select aria-label="Week"><option selected>Week {N}</option><option>Week {N - 1}</option></select><p class="faded">Week {N} pack, ready since Tuesday, Nov 17.</p></div>
{''.join(item(it) for it in D['items'])}
{keys_box()}
</div></div></body></html>'''

open(os.path.join(here, 'tab.html'), 'w').write(frame_top + body)
open(os.path.join(here, 'index.html'), 'w').write(f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock v45: Post to Reddit</title>
<style>body{{margin:0;background:#f6f7f7;color:#1d2327;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}}main{{max-width:640px;margin:0 auto;padding:32px 16px}}a{{color:#2271b1}}</style></head>
<body><main><h1 style="font-size:22px;font-weight:600">Mock v45: Post to r/{SUB}</h1><p>SCOPE §71. The Share pack tab with a Post button on every item, using the live Week {N} pack.</p><p><a href="tab.html">Open the Share pack tab mock</a></p></main></body></html>''')
print('wrote tab.html', len(frame_top + body), 'bytes; test url', len(test_url), 'chars')
