"""Mock v48 (SCOPE §74): the Share pack tab, simplified. Live Week 10 data (_pack.json from mockup-v46). Writes tab.html + index.html.
One idea per item: the card, the takeaway, one row of "post it" buttons. The texts sit behind a fold; the setup moves to the bottom."""
import html, json, os
here = os.path.dirname(os.path.abspath(__file__))
D = json.load(open(os.path.join(here, '_pack.json')))
N = D['n']
NUM = {'swing': 1, 'snaps': 2, 'offense': 3, 'targets': 4}
FITS = {'swing': 'r/nfl, r/Commanders, r/NYGiants', 'snaps': 'r/fantasyfootball, r/Tennesseetitans', 'offense': 'r/nfl, r/49ers', 'targets': 'r/fantasyfootball, r/Texans'}
TAGS = {'swing': '#NFL #Commanders #Giants', 'snaps': '#NFL #Titans #FantasyFootball', 'offense': '#NFL #49ers #Cardinals', 'targets': '#NFL #Texans #FantasyFootball'}
NICK = {'IND': 'Colts', 'HOU': 'Texans', 'MIA': 'Dolphins', 'BUF': 'Bills'}
LOGO = 'https://a.espncdn.com/i/teamlogos/nfl/500/{}.png'
e = lambda s: html.escape(str(s), quote=True)


def post_row(extra_sub=''):
    sub = f'<a class="b rd">{e(extra_sub)}</a>' if extra_sub else ''
    return f'''<a class="b rd">r/downbydown</a>{sub}<a class="b x">X</a><a class="b bs">Bluesky</a><a class="b fb">Facebook</a><a class="b ig">Instagram</a>'''


def texts(it):
    cap = it['title'] + '\n\nFull chart: link in bio (downbydown.com)\n\n' + TAGS[it['k']]
    rows = [('Reddit title', it['title']), ('Reddit first comment', 'The link, with a line about it: ' + it['link']), ('X and Bluesky', it['post']), ('Instagram caption', cap)]
    return ''.join(f'<div class="tx"><div class="txh"><b>{e(a)}</b><a class="b sm">Copy</a></div><textarea readonly rows="{4 if "Instagram" in a else 2}">{e(b)}</textarea></div>' for a, b in rows)


def item(it, open_=False):
    return f'''<section class="it">
<div class="ih"><span class="no">{NUM[it['k']]}</span><h2>{e(it['h'])}</h2></div>
<div class="ib">
<div class="il"><img src="https://downbydown.com/?card={e(it['card'])}" width="1200" height="630" alt=""><div class="dl"><a class="lk">Download image</a><a class="lk">Download square for Instagram</a></div></div>
<div class="ir"><p class="tk">{e(it['title'])}</p>
<p class="pl">Post it</p><div class="row">{post_row()}</div>
<p class="fits">Also fits {e(FITS[it['k']])}: post there by hand with the texts below.</p>
<details{' open' if open_ else ''}><summary>Texts to copy</summary>{texts(it)}</details>
</div></div></section>'''


def keys():
    out = []
    for g in D['keys']:
        sides = ''.join(f'''<div class="ks"><img src="{LOGO.format(sd['t'].lower())}" alt=""><div class="kt"><b>{NICK[sd['t']]} offense.</b> {e(sd['head'].rstrip('.'))}. <span>{e(sd['say'])}</span></div><div class="row sm">{post_row(sd['fits'])}<a class="b sq">Square</a></div></div>''' for sd in g['sides'])
        tx = ''.join(f'<div class="tx"><div class="txh"><b>{NICK[sd["t"]]}: {a}</b><a class="b sm">Copy</a></div><textarea readonly rows="2">{e(b)}</textarea></div>' for sd in g['sides'] for a, b in (('Reddit title', sd['title']), ('Reddit first comment', sd['head'].rstrip('.') + '. ' + sd['say'] + ' ' + sd['link'])))
        out.append(f'<div class="kg"><div class="kgh"><b>{NICK[g["away"]]} at {NICK[g["home"]]}</b><a class="lk">Download the game card</a></div>{sides}<details><summary>Texts to copy</summary>{tx}</details></div>')
    return f'''<section class="it"><div class="ih"><span class="no">5</span><h2>Keys to the game, Week {N + 1}</h2></div>
<p class="kl">Each team’s biggest edge in its next game, for its own fans. 13 games this week.</p>{''.join(out)}<p class="more">… 11 more games</p></section>'''


css = '''
body{margin:0;background:#f0f0f1;color:#1d2327;font:13px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.bar{height:32px;background:#1d2327}.shell{display:flex}.side{width:160px;background:#1d2327;min-height:100vh;flex:none}.side div{padding:8px 12px;color:#f0f0f1;font-size:14px}.side .on{background:#2271b1}
.wrap{flex:1;padding:10px 20px 60px;min-width:0;max-width:1100px}
h1{font-size:23px;font-weight:400;margin:9px 0 4px}.lede{margin:0 0 12px;color:#50575e}
.tabs{display:flex;gap:4px;border-bottom:1px solid #c3c4c7;margin:0 0 18px}.tabs a{padding:5px 10px;border:1px solid #c3c4c7;margin-bottom:-1px;background:#dcdcde;color:#50575e;font-size:14px;font-weight:600}.tabs a.on{background:#f0f0f1;border-bottom-color:#f0f0f1;color:#000}
.note{padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;margin:0 0 16px;line-height:1.5}
.top{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;margin:0 0 6px}
.top h2{font-size:18px;font-weight:600;margin:0}.top select{min-height:30px;font-size:13px}
.how{margin:0 0 6px;color:#3c434a;max-width:820px;line-height:1.55}
.mail{margin:0 0 18px;color:#50575e}.mail .dot{display:inline-block;width:8px;height:8px;border-radius:4px;background:#00a32a;margin-right:6px}.mail .warn{color:#b32d2e;font-weight:600}
a{color:#2271b1}.lk{color:#2271b1;text-decoration:underline;cursor:pointer}
.it{background:#fff;border:1px solid #c3c4c7;margin:0 0 16px}
.ih{display:flex;align-items:baseline;gap:10px;padding:12px 16px 0}.ih h2{font-size:15px;margin:0}.no{font:600 13px/1 -apple-system,sans-serif;color:#646970}
.ib{display:grid;grid-template-columns:400px minmax(0,1fr);gap:20px;padding:12px 16px 16px}
.il img{display:block;width:100%;height:auto;border:1px solid #dcdcde}.dl{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}
.tk{font-size:15px;line-height:1.5;margin:0 0 14px;color:#1d2327}
.pl{margin:0 0 6px;font-weight:600}
.row{display:flex;flex-wrap:wrap;gap:6px}
.b{display:inline-block;padding:0 12px;min-height:30px;line-height:28px;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;text-decoration:none;font-size:13px;cursor:pointer}
.b.rd{border-color:#cb2026;color:#cb2026}.b.x{border-color:#0f1419;color:#0f1419}.b.bs{border-color:#1185fe;color:#1185fe}.b.fb{border-color:#0866ff;color:#0866ff}.b.ig,.b.sq{border-color:#c13584;color:#c13584}
.b.sm{min-height:24px;line-height:22px;padding:0 8px;font-size:12px}
.row.sm .b{min-height:26px;line-height:24px;padding:0 9px;font-size:12px}
.fits{margin:10px 0 0;color:#646970}
details{margin-top:12px;border-top:1px solid #f0f0f1;padding-top:10px}summary{cursor:pointer;color:#2271b1;font-weight:600}
.tx{margin:10px 0 0}.txh{display:flex;justify-content:space-between;align-items:center;margin-bottom:4px}
textarea{width:100%;box-sizing:border-box;padding:6px 8px;border:1px solid #8c8f94;border-radius:4px;font:13px/1.5 -apple-system,sans-serif;resize:vertical}
.kl{margin:6px 16px 4px;color:#50575e}
.kg{margin:0 16px;padding:12px 0;border-top:1px solid #f0f0f1}.kg:first-of-type{border-top:0}
.kgh{display:flex;gap:14px;align-items:baseline;margin-bottom:6px}.kgh b{font-size:14px}
.ks{display:grid;grid-template-columns:24px minmax(0,1fr);gap:4px 10px;padding:6px 0}.ks img{width:24px;height:24px}
.kt{line-height:1.5}.kt span{color:#50575e}.ks .row{grid-column:2}
.more{margin:0 16px 14px;color:#646970}
.set{background:#fff;border:1px solid #c3c4c7;margin:24px 0 0}.set>summary{padding:12px 16px;font-size:14px;color:#1d2327}
.set[open]>summary{border-bottom:1px solid #c3c4c7}
.sg{display:grid;grid-template-columns:200px minmax(0,1fr);gap:12px 16px;padding:16px;align-items:start}.sg label{color:#50575e;padding-top:5px}
.sg input[type=text],.sg textarea{width:100%;max-width:420px;min-height:30px;box-sizing:border-box;padding:0 8px;border:1px solid #8c8f94;border-radius:4px;font-size:13px}.sg textarea{padding:6px 8px}
.sg .d{color:#646970;font-size:12px;margin-top:3px}
.sh{grid-column:1/-1;font-weight:600;font-size:14px;margin-top:8px;padding-top:12px;border-top:1px solid #f0f0f1}.sh:first-child{margin-top:0;padding-top:0;border-top:0}
.sf{padding:12px 16px;border-top:1px solid #c3c4c7;background:#f6f7f7;display:flex;gap:8px;align-items:center}
.bp{background:#2271b1;color:#fff}
@media (max-width:1000px){.ib{grid-template-columns:minmax(0,1fr)}.il{max-width:420px}}
@media (max-width:782px){.side{display:none}.sg{grid-template-columns:minmax(0,1fr)}}
'''

items = D['items']
body = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Share pack, simplified</title><style>{css}</style></head><body>
<div class="bar"></div><div class="shell"><div class="side"><div>Dashboard</div><div>Posts</div><div>Pages</div><div class="on">Down by Down</div><div>Settings</div></div><div class="wrap">
<h1>Down by Down</h1><p class="lede">Season 2026 · Week 11 · version 2.32.0</p>
<div class="tabs"><a>Overview</a><a>Week in review</a><a>News and videos</a><a>Site</a><a>Data</a><a>Growth</a><a class="on">Share pack</a></div>
<div class="note"><b>Mock: the Share pack, simplified.</b> Same features, less to read. Each item shows its card, its takeaway and one row of buttons, one per place to post. The four texts to copy fold away under “Texts to copy”. The email list and your accounts move into one box at the bottom, since you set them once. Keys to the game turn into compact rows. Buttons don’t work in the mock.</div>
<div class="top"><h2>Week {N} pack</h2><select aria-label="Week"><option>Week {N}</option><option>Week {N-1}</option></select><span class="lede" style="margin:0">ready Tuesday, Nov 17</span></div>
<p class="how">Click where you want to post. Reddit, X and Bluesky open with the post filled in. Facebook copies the text and opens its share box: choose Share to a group, then paste. Instagram copies the caption: post it with the square.</p>
<p class="mail"><span class="dot"></span>Emailed to 2 people Tuesday, Nov 17 at 7:00 AM. <a class="lk" href="#settings">Change the list</a></p>
{item(items[0])}
{''.join(item(it) for it in items[1:])}
{keys()}
<details class="set" id="settings"><summary>Settings: the Tuesday email and your accounts</summary>
<div class="sg">
<div class="sh">The Tuesday email</div>
<label>Send it</label><div><label style="color:#1d2327;padding:0"><input type="checkbox" checked> Email each week’s pack, Tuesday 7:00 AM Eastern</label></div>
<label>Send to</label><div><textarea rows="3">erik@crawfishstudios.com
gene@period-three.com</textarea><div class="d">One address per line, up to 10. Each person gets their own copy.</div></div>
<label>Last sends</label><div>Week 10 sent Tuesday, Nov 17, 7:00 AM to 2 people.<br>Week 9 skipped: more than two days late. <div style="margin-top:8px"><a class="b">Send the Week 10 pack now</a></div></div>
<div class="sh">Your accounts</div>
<label>Reddit</label><div>r/ <input type="text" value="downbydown" style="max-width:240px"></div>
<label>X</label><div>@ <input type="text" value="dxd_NFLfootball" style="max-width:240px"><div class="d">Also named in every page’s share tags.</div></div>
<label>Bluesky</label><div><input type="text" value="downbydown.bsky.social" style="max-width:260px"></div>
<label>Facebook group</label><div><input type="text" value="https://www.facebook.com/groups/988685080499981"> <a class="lk">Open the group</a></div>
<label>Instagram</label><div>@ <input type="text" value="down_by_down" style="max-width:240px"></div>
<div></div><div class="d">Clear an account to hide its button.</div>
</div><div class="sf"><a class="b bp">Save settings</a></div></details>
</div></div></body></html>'''
open(os.path.join(here, 'tab.html'), 'w').write(body)
open(os.path.join(here, 'index.html'), 'w').write('<!doctype html><meta charset="utf-8"><title>Mock v48</title><p style="font:15px sans-serif;padding:24px">SCOPE §74. <a href="tab.html">The Share pack, simplified</a>.</p>')
print('ok')
