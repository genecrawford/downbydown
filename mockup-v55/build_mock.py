"""Mock v55 (SCOPE §82): Bluesky on Instagram's weekly schedule. The Week 10 pack's live texts (_pack.json, pulled from the server for §81).
Shows the three Tuesday choices, the five daily posts, the status line and the new setting. Writes index.html."""
import html, json, os, re
here = os.path.dirname(os.path.abspath(__file__))
e = lambda s: html.escape(str(s), quote=True)
D = json.load(open(os.path.join(here, '_pack.json')))
I = {it['k']: it for it in D['items']}
CARD = 'https://downbydown.com/?card='
def short(u):
    s = re.sub(r'^https?://(www\.)?', '', u); s = re.sub(r'[?#].*$', '', s).rstrip('/')
    return s[:29].rstrip('/') + '…' if len(s) > 30 else s
def body(it):
    t = it['post'].rsplit(' https://', 1)[0]
    return f'{e(t)} <a>{e(short(it["link"]))}</a>'
def head(when):
    return f'<div class="bkh"><span class="av">DxD</span><div><b>Down x Down</b> <span class="mu">@{e(D["bsky"])} · {e(when)}</span></div></div>'
def post(it, when):
    return f'''<div class="bk">{head(when)}<p class="bkt">{body(it)}</p>
<div class="lk"><img src="{CARD}{e(it['card'])}" alt=""><div class="lkb"><span class="mu">downbydown.com</span><b>{e(it['title'])}</b></div></div></div>'''
DAILY = [('Wednesday', 'swing'), ('Thursday', 'snaps'), ('Friday', 'offense'), ('Saturday', 'targets'), ('Sunday', 'leaders')]
FIVE = ['swing', 'snaps', 'offense', 'targets', 'leaders']
lines = {'swing': 'The biggest swing', 'snaps': 'A usage riser', 'offense': 'Best and worst offense', 'targets': 'A target-share mover', 'leaders': 'Week leaders'}

# Tuesday A: a thread. The first post names the week; each item replies under it with its own link card.
thread = f'''<div class="bk">{head('Tue 9:00 AM')}<p class="bkt">Week 10 in five charts, one in each reply below. Every chart and the numbers behind it: <a>downbydown.com/week/10</a></p></div>''' + ''.join(
    f'<div class="rep"><div class="rl"></div>{post(I[k], "reply " + str(i + 2) + "/6")}</div>' for i, k in enumerate(FIVE[:2])) + \
    '<p class="more">…three more replies: best and worst offense, the target-share mover, week leaders.</p>'
# Tuesday B: one post, four squares (Bluesky's most); the fifth left out.
grid = ''.join(f'<img src="{CARD}{e(I[k]["sq"])}" alt="">' for k in FIVE[:4])
four = f'''<div class="bk">{head('Tue 9:00 AM')}<p class="bkt">Week 10 in four charts: the biggest swing, a usage riser, best and worst offense, a target-share mover. Every chart: <a>downbydown.com/week/10</a></p>
<div class="g4">{grid}</div><p class="mu sm">Four images is Bluesky’s most, so week leaders sits this one out; it gets its own day Sunday.</p></div>'''
skip = '<div class="bk none"><p class="bkt mu">Nothing on Tuesday. Bluesky starts Wednesday with the biggest swing.</p></div>'

days = ''.join(f'<div class="day"><p class="dh">{d} 9:00 AM</p>{post(I[k], d[:3] + " 9:00 AM")}</div>' for d, k in DAILY)

css = '''
body{margin:0;background:#f0f0f1;color:#1d2327;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
main{max-width:1180px;margin:0 auto;padding:20px 16px 60px}
.note{padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;margin:0 0 20px;line-height:1.55;font-size:14px}
h2.s{font-size:15px;margin:30px 0 4px}p.sd{margin:0 0 12px;color:#50575e;max-width:860px}
a{color:#1185fe;cursor:pointer}
.opts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:start}
.opt>h3{font-size:14px;margin:0 0 2px}.opt>p{margin:0 0 8px;color:#50575e}
.bk{background:#fff;border:1px solid #c3c4c7;padding:12px}.bk.none{min-height:60px}
.bkh{display:flex;gap:8px;align-items:center}.av{flex:0 0 32px;width:32px;height:32px;border-radius:16px;background:#121417;color:#fff;font:600 10px/32px sans-serif;text-align:center}.mu{color:#646970}.sm{font-size:12px;margin:6px 0 0}
.bkt{margin:6px 0 8px;font-size:14px;line-height:1.45}
.lk{border:1px solid #dcdcde;border-radius:8px;overflow:hidden}.lk img{display:block;width:100%;aspect-ratio:1200/630}.lkb{padding:6px 10px;display:flex;flex-direction:column;gap:1px;border-top:1px solid #dcdcde}.lkb b{font-size:13px;line-height:1.35}
.rep{display:grid;grid-template-columns:18px minmax(0,1fr);margin-top:-1px}.rl{border-left:2px solid #c3c4c7;margin-left:15px}
.more{margin:8px 0 0 18px;color:#50575e}
.g4{display:grid;grid-template-columns:1fr 1fr;gap:4px;border-radius:8px;overflow:hidden}.g4 img{display:block;width:100%;aspect-ratio:1}
.days{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.dh{font-weight:600;margin:0 0 6px}
.days .bkt{font-size:12.5px}
.box{background:#fff;border:1px solid #c3c4c7}.top{padding:12px 16px}.top p{margin:0;color:#50575e;line-height:1.9}
.dot{display:inline-block;width:8px;height:8px;border-radius:4px;background:#00a32a;margin-right:6px}
.set>.sm2{padding:12px 16px;font-size:14px;font-weight:600;border-bottom:1px solid #c3c4c7}
.sg{display:grid;grid-template-columns:200px minmax(0,1fr);gap:12px 16px;padding:16px;align-items:start}.sg>label,.sg>span{color:#50575e;padding-top:3px}
.sh{grid-column:1/-1;font-weight:600;font-size:14px;padding-top:12px;border-top:1px solid #f0f0f1}.faded{opacity:.4}
.dd{color:#646970;margin:4px 0 0;font-size:12px}.ok{color:#007017;font-weight:600}.new{background:#fffbe6;margin:-4px -6px;padding:4px 6px}
.b{display:inline-block;padding:0 12px;line-height:28px;border:1px solid #2271b1;border-radius:3px;background:#2271b1;color:#fff}
.foot{padding:12px 16px;border-top:1px solid #c3c4c7;background:#f6f7f7}
@media (max-width:1000px){.opts{grid-template-columns:minmax(0,1fr)}.days{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:640px){.days{grid-template-columns:minmax(0,1fr)}.sg{grid-template-columns:minmax(0,1fr)}}
'''
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bluesky schedule mock</title><style>{css}</style></head><body><main>
<div class="note"><b>Mock: Bluesky on Instagram’s schedule (SCOPE §82).</b> Each finished week, Bluesky gets the same days Instagram does: something on <b>Tuesday</b>, then <b>one item a day, Wednesday to Sunday</b>, each the item’s Bluesky text with its card as the link preview (the post you tested with Auto publish). An item you’ve already sent with Auto publish is skipped on its day, so nothing posts twice. Week 10’s live texts below.</div>

<h2 class="s">Tuesday: three ways (decision 1)</h2>
<p class="sd">Bluesky has no carousel, and a post holds four images at most.</p>
<div class="opts">
<div class="opt"><h3>A. A thread</h3><p>One post names the week; each of the five items replies under it with its own text and card.</p>{thread}</div>
<div class="opt"><h3>B. Four squares in one post</h3><p>Closest to the Instagram carousel. Four is Bluesky’s limit, so one item is left out.</p>{four}</div>
<div class="opt"><h3>C. Skip Tuesday</h3><p>Bluesky starts Wednesday. No repeat of the week’s items.</p>{skip}</div>
</div>

<h2 class="s">Then one item a day, Wednesday to Sunday</h2>
<p class="sd">Same order as Instagram. Each is exactly what Auto publish sends: the text, a short clickable link, and the card as the preview. A week missing an item skips that day.</p>
<div class="days">{days}</div>

<h2 class="s">At the top of the Share pack tab</h2>
<div class="box top"><p><span class="dot"></span>Week 10 pack emailed Tuesday, Nov 17, 7:00 AM to 2 people. <a>Change the list</a><br><span class="dot"></span>Instagram: posted the usage riser Thursday, Nov 19, 9:00 AM. Next: best and worst offense, Friday, Nov 20, 9:00 AM.<br><span class="dot"></span>Bluesky: posted the usage riser Thursday, Nov 19, 9:00 AM. Next: best and worst offense, Friday, Nov 20, 9:00 AM.</p></div>

<h2 class="s">In the settings box</h2>
<div class="box set"><div class="sm2">▾ Settings: the Tuesday email, your accounts, Instagram and Bluesky posting</div>
<div class="sg"><div class="sh faded" style="border-top:0;padding-top:0">The Tuesday email · Your accounts · Instagram posting</div><span class="faded">unchanged</span><div class="faded">as now</div>
<div class="sh">Bluesky posting</div>
<span>Post it</span><div class="new"><label><input type="checkbox" checked> Post on Tuesday, then one item a day Wednesday to Sunday, 9:00 AM Eastern</label><p class="dd">New. Untick it and Bluesky posts only when you press Auto publish.</p></div>
<span>Account</span><div>@{e(D['bsky'])}</div>
<label>App password</label><div><span class="ok">● Connected as @{e(D['bsky'])}.</span></div>
<span>This week</span><div class="new">Tuesday: posted (<a>view</a>)<br>Wednesday: the biggest swing, posted (<a>view</a>)<br>Thursday: the usage riser, posted (<a>view</a>)<br>Friday: best and worst offense, booked<br>Saturday: the target-share mover, already sent with Auto publish<br>Sunday: week leaders, booked</div>
<div class="sh faded">Auto publish</div><span class="faded">Posts to</span><div class="faded">Instagram, Bluesky (unchanged)</div>
</div><div class="foot"><span class="b">Save settings</span></div></div>
</main></body></html>'''
open(os.path.join(here, 'index.html'), 'w').write(page)
print('ok')
