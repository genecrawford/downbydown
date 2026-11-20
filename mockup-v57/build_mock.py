"""Mock v57 (SCOPE §84): Threads on the same schedule as Instagram, Bluesky and X. The Week 10 pack's live texts (_pack.json, as in v55).
Shows the post, the two ways to connect (decision 1), the three Tuesday choices (decision 2), the daily posts, the tab line, the settings
and the Auto publish ask. Writes index.html."""
import html, json, os
here = os.path.dirname(os.path.abspath(__file__))
e = lambda s: html.escape(str(s), quote=True)
D = json.load(open(os.path.join(here, '_pack.json')))
I = {it['k']: it for it in D['items']}
CARD = 'https://downbydown.com/?card='
TH = 'down_by_down'
def head(when):
    return f'<div class="th"><span class="av">DxD</span><div><b>{TH}</b> <span class="mu">{e(when)}</span></div></div>'
def card(it, title=None):
    return f'<div class="lk"><img src="{CARD}{e(it["card"])}" alt=""><div class="lkb"><span class="mu">downbydown.com</span><b>{e(title or it["title"])}</b></div></div>'
def post(it, when, text=None):
    return f'<div class="tp">{head(when)}<p class="tt">{e(text or it["title"])}</p>{card(it)}</div>'
FIVE = ['swing', 'snaps', 'offense', 'targets', 'leaders']
DAILY = [('Wednesday', 'swing'), ('Thursday', 'snaps'), ('Friday', 'offense'), ('Saturday', 'targets'), ('Sunday', 'leaders')]
wk = {'card': 'page-week-10', 'title': 'Week 10'}
intro = f'<div class="tp">{head("Tue 9:00 AM")}<p class="tt">Week 10 in five charts, one in each reply below. Every chart and the numbers behind it:</p>{card(wk, "Week 10 · Down by Down")}</div>'
thread = intro + ''.join(f'<div class="rep"><div class="rl"></div>{post(I[k], "reply " + str(i + 1) + "/5")}</div>' for i, k in enumerate(FIVE[:2])) + \
    '<p class="more">…three more replies: best and worst offense, the target-share mover, week leaders.</p>'
one = f'<div class="tp">{head("Tue 9:00 AM")}<p class="tt">Week 10 in five charts: the biggest swing, a usage riser, best and worst offense, a target-share mover and week leaders. Every chart and the numbers behind it:</p>{card(wk, "Week 10 · Down by Down")}</div>'
skip = '<div class="tp none"><p class="tt mu">Nothing on Tuesday. Threads starts Wednesday with the biggest swing.</p></div>'
days = ''.join(f'<div class="day"><p class="dh">{d} 9:00 AM</p>{post(I[k], d[:3] + " 9:00 AM")}</div>' for d, k in DAILY)

conn_a = f'''<div class="box"><div class="sg">
<label>Threads App ID</label><div><input type="text" placeholder="From the Meta app’s Threads settings"></div>
<label>Threads App secret</label><div><input type="password" placeholder="From the Meta app’s Threads settings"></div>
<span></span><div><span class="b">Connect Threads</span><p class="dd">Opens Threads’ own screen: approve as @{TH} and you come back here connected. Add this address to the app’s redirect callback URLs once: <code>downbydown.com/wp-admin/admin-post.php?action=dbd_th_back</code></p></div>
</div></div>'''
conn_b = f'''<div class="box"><div class="sg">
<label>Threads App secret</label><div><input type="password" placeholder="From the Meta app’s Threads settings"></div>
<label>Access token</label><div><input type="password" placeholder="From Meta’s Graph API Explorer (Threads)"><p class="dd">In the Graph API Explorer, choose the app and threads.net, tick threads_basic, threads_content_publish and threads_manage_replies, Generate token as @{TH}, and paste it here. The secret lets the plugin make the hour-long token last 60 days.</p></div>
</div></div>'''

css = '''
body{margin:0;background:#f0f0f1;color:#1d2327;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
main{max-width:1180px;margin:0 auto;padding:20px 16px 60px}
.note{padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;margin:0 0 20px;line-height:1.55;font-size:14px}
h2.s{font-size:15px;margin:30px 0 4px}p.sd{margin:0 0 12px;color:#50575e;max-width:860px}
a{color:#2271b1;cursor:pointer}code{font-size:12px;background:#f0f0f1;padding:1px 4px}
.opts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:start}.opts.two{grid-template-columns:repeat(2,minmax(0,1fr))}
.opt>h3{font-size:14px;margin:0 0 2px}.opt>p{margin:0 0 8px;color:#50575e}
.tp{background:#fff;border:1px solid #d9d9d9;border-radius:4px;padding:12px}.tp.none{min-height:60px}
.th{display:flex;gap:8px;align-items:center}.av{flex:0 0 32px;width:32px;height:32px;border-radius:16px;background:#121417;color:#fff;font:600 10px/32px sans-serif;text-align:center}.mu{color:#777}
.tt{margin:6px 0 8px;font-size:14px;line-height:1.45;color:#000}
.lk{border:1px solid #d9d9d9;border-radius:12px;overflow:hidden}.lk img{display:block;width:100%;aspect-ratio:1200/630}.lkb{padding:6px 10px;display:flex;flex-direction:column;gap:1px;border-top:1px solid #d9d9d9}.lkb b{font-size:13px;line-height:1.35}
.rep{display:grid;grid-template-columns:18px minmax(0,1fr);margin-top:-1px}.rl{border-left:2px solid #d9d9d9;margin-left:15px}
.more{margin:8px 0 0 18px;color:#50575e}
.days{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.dh{font-weight:600;margin:0 0 6px}.days .tt{font-size:12.5px}
.box{background:#fff;border:1px solid #c3c4c7}.top{padding:12px 16px}.top p{margin:0;color:#50575e;line-height:1.9}
.dot{display:inline-block;width:8px;height:8px;border-radius:4px;background:#00a32a;margin-right:6px}
.set>.sm2{padding:12px 16px;font-size:14px;font-weight:600;border-bottom:1px solid #c3c4c7}
.sg{display:grid;grid-template-columns:170px minmax(0,1fr);gap:12px 16px;padding:16px;align-items:start}.sg>label,.sg>span{color:#50575e;padding-top:3px}
.sh{grid-column:1/-1;font-weight:600;font-size:14px;padding-top:12px;border-top:1px solid #f0f0f1}.faded{opacity:.4}
.dd{color:#646970;margin:6px 0 0;font-size:12px}.ok{color:#007017;font-weight:600}.new{background:#fffbe6;margin:-4px -6px;padding:4px 6px}
input[type=password],input[type=text]{width:320px;max-width:100%;padding:3px 8px;border:1px solid #8c8f94;border-radius:4px;font:inherit}
.b{display:inline-block;padding:0 12px;line-height:28px;border:1px solid #2271b1;border-radius:3px;background:#2271b1;color:#fff}.b2{display:inline-block;padding:0 12px;line-height:28px;border:1px solid #2271b1;border-radius:3px;color:#2271b1;background:#f6f7f7}
.foot{padding:12px 16px;border-top:1px solid #c3c4c7;background:#f6f7f7}.ask{max-width:560px;padding:12px 16px}.ask p{margin:0 0 10px}
@media (max-width:1000px){.opts,.opts.two{grid-template-columns:minmax(0,1fr)}.days{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:640px){.days{grid-template-columns:minmax(0,1fr)}.sg{grid-template-columns:minmax(0,1fr)}}
'''
sw = I['swing']
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Threads schedule mock</title><style>{css}</style></head><body><main>
<div class="note"><b>Mock: Threads on the same schedule (SCOPE §84).</b> Threads gets the days the other three have: something on <b>Tuesday</b>, then <b>one item a day, Wednesday to Sunday</b>, 9:00 AM Eastern, and the Auto publish button posts to Threads too. Meta lists no charge for posting. Week 10’s live texts below; the cards are the real ones.</div>

<h2 class="s">The post</h2>
<p class="sd">The item’s long takeaway (Threads allows 500 characters, so nothing is cut) with the page as the preview card. No link in the text: the card is the link.</p>
<div style="max-width:420px">{post(sw, 'Wed 9:00 AM')}</div>

<h2 class="s">Connecting: two ways (decision 1)</h2>
<p class="sd">Either way the token lasts 60 days and the plugin renews it every week, as it does Instagram’s. Both need the Meta app set up for Threads with @{TH} as a Threads Tester first.</p>
<div class="opts two">
<div class="opt"><h3>A. A connect button</h3><p>Paste two values once, click, approve on Threads. Reconnecting later is one click.</p>{conn_a}</div>
<div class="opt"><h3>B. Paste a token</h3><p>Like Instagram’s setup: make a token in Meta’s tools and paste it, with the App secret.</p>{conn_b}</div>
</div>

<h2 class="s">Tuesday: three ways (decision 2)</h2>
<p class="sd">Threads has carousels, but only of images; the post above is text with a card.</p>
<div class="opts">
<div class="opt"><h3>A. A thread, like Bluesky’s and X’s</h3><p>One post names the week with its card; each of the five items replies under it with its own text and card.</p>{thread}</div>
<div class="opt"><h3>B. One post naming the week</h3><p>The five items in a line, the week page as the card.</p>{one}</div>
<div class="opt"><h3>C. Nothing on Tuesday</h3><p>Threads starts Wednesday.</p>{skip}</div>
</div>

<h2 class="s">Then one item a day, Wednesday to Sunday</h2>
<p class="sd">Same order as the others, each exactly what Auto publish sends. An item already sent with the button is skipped on its day.</p>
<div class="days">{days}</div>

<h2 class="s">At the top of the Share pack tab</h2>
<div class="box top"><p><span class="dot"></span>Week 10 pack emailed Tuesday, Nov 17, 7:00 AM to 2 people. <a>Change the list</a><br><span class="dot"></span>Instagram: posted best and worst offense Friday, Nov 20, 9:00 AM. Next: the target-share mover, Saturday, Nov 21, 9:00 AM.<br><span class="dot"></span>Bluesky: … <br><span class="dot"></span>X: …<br><span class="new"><span class="dot"></span>Threads: posted best and worst offense Friday, Nov 20, 9:00 AM. Next: the target-share mover, Saturday, Nov 21, 9:00 AM. <a>View on Threads</a></span></p></div>

<h2 class="s">In the settings box</h2>
<div class="box set"><div class="sm2">▾ Settings: the Tuesday email, your accounts, Instagram, Bluesky, X and Threads posting</div>
<div class="sg"><div class="sh" style="border-top:0;padding-top:0">Your accounts</div><span class="faded">Reddit · X · Bluesky · Facebook · Instagram</span><div class="faded">as now</div>
<label>Threads <span class="mu">(new)</span></label><div class="new"><input type="text" value="{TH}" style="width:220px"><p class="dd">The Threads profile’s name, the Instagram account’s by default.</p></div>
<div class="sh faded">Instagram posting · Bluesky posting · X posting</div><span class="faded">unchanged</span><div class="faded">as now</div>
<div class="sh">Threads posting <span class="mu" style="font-weight:400">(new)</span></div>
<span>Post it</span><div><label><input type="checkbox" checked> Post on Tuesday, then one item a day Wednesday to Sunday, 9:00 AM Eastern</label><p class="dd">Untick it and Threads posts only when you press Auto publish.</p></div>
<span>Account</span><div>@{TH} <span class="mu">(from Your accounts above)</span></div>
<span>Connection</span><div><span class="mu">(decision 1’s fields)</span><p class="dd"><span class="ok">● Connected as @{TH}.</span> Good until Jan 19, 2027; renewed every week. (once connected)</p></div>
<div class="sh faded">Auto publish</div><span class="faded">Posts to</span><div><span class="faded">Instagram · Bluesky · X</span><br><span class="new"><label><input type="checkbox" checked> Threads</label></span></div>
</div><div class="foot"><span class="b">Save settings</span></div>
<div class="sg" style="border-top:1px solid #c3c4c7"><div class="sh" style="border-top:0;padding-top:0">Threads this week <span class="mu" style="font-weight:400">(new, under X this week)</span></div><span></span><div><span class="dot"></span>The thread: posted Tuesday, Nov 17, 9:00 AM <a>View</a><br><span class="dot"></span>The biggest swing: posted Wednesday, Nov 18, 9:00 AM <a>View</a><br><span class="dot"></span>The target-share mover: booked for Saturday, Nov 21, 9:00 AM<br><span class="dot"></span>Week leaders: sent with Auto publish Friday, Nov 20, 10:06 AM <a>View</a></div></div></div>

<h2 class="s">The Auto publish ask, on each item</h2>
<div class="box ask"><p><b>Post “The biggest swing” to Instagram, Bluesky, X and Threads now?</b> It goes out right away.</p><span class="b">Post it now</span> <span class="b2">Cancel</span></div>
</main></body></html>'''
open(os.path.join(here, 'index.html'), 'w').write(page)
print('ok')
