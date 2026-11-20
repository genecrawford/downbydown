"""Mock v54 (SCOPE §81): Auto publish. One button on a Share pack item posts it to every network that can post by itself
(Instagram and Bluesky). Live Week 10 texts from _pack.json (pull them on the server). Writes index.html."""
import html, json, os
here = os.path.dirname(os.path.abspath(__file__))
e = lambda s: html.escape(str(s), quote=True)
D = json.load(open(os.path.join(here, '_pack.json')))
I = {it['k']: it for it in D['items']}
R = I['rush']; S = I['swing']
CARD = 'https://downbydown.com/?card='
tl = R['post'].rsplit(' https://', 1)[0]
bsky_text = tl + ' ' + R['link']
ig_cap = tl + '\n\nFull chart: link in bio (downbydown.com)\n\n#NFL #Bills #Texans'

def row(extra=''):
    return f'''<div class="row"><a class="b rd">r/{e(D['sub'])}</a><a class="b x">X</a><a class="b bs">Bluesky</a><a class="b fb">Facebook</a><a class="b ig">Instagram</a></div>{extra}'''

def item(it, under, note=''):
    return f'''<section class="box item"><div class="ih"><span class="no">{it['no']}</span><h3>{e(it['h'])}</h3></div><div class="body">
<div class="l"><img src="{CARD}{e(it['card'])}" alt=""><p class="dl"><a>Download image</a><a>Download square for Instagram</a></p></div>
<div class="r"><p class="tk">{e(it['title'])}</p><p class="pl">Post it</p>{row()}{under}{note}
<p class="fits">Also fits {e(', '.join(it['fits']))}: post there by hand with the texts below.</p><p class="tx">▸ Texts to copy</p></div></div></section>'''

ap = '<div class="ap"><button class="b bp">Auto publish</button><span class="d">Posts it to Instagram and Bluesky now.</span></div>'
confirm = f'''<div class="ap ask"><p><b>Post “{e(R['h'])}” to Instagram and Bluesky now?</b> It goes out right away, as shown under “What each one gets”.</p><button class="b bp">Post it now</button> <button class="b">Cancel</button></div>'''
done = '''<div class="ap"><button class="b" disabled>Published</button></div>
<p class="st"><span class="dot"></span>Instagram: posted Thursday, Nov 19, 4:12 PM. <a>View</a><br><span class="dot"></span>Bluesky: posted Thursday, Nov 19, 4:12 PM. <a>View</a></p>'''
part = '''<div class="ap"><button class="b bp">Auto publish</button><span class="d">Posts it to Instagram now. Bluesky is done.</span></div>
<p class="st"><span class="dot"></span>Bluesky: posted Thursday, Nov 19, 4:12 PM. <a>View</a><br><span class="dot bad"></span>Instagram didn’t post: the image couldn’t be fetched. It tries again every 10 minutes, 7 times. <a class="b sm">Try again</a></p>'''
booked = '''<div class="ap"><button class="b bp">Auto publish</button><span class="d">Posts it to Bluesky now. Instagram already posted it Wednesday, Nov 18, 9:00 AM (<a>view</a>).</span></div>'''

bsky_card = f'''<div class="bk"><div class="bkh"><span class="av">DxD</span><div><b>Down x Down</b> <span class="mu">@{e(D['bsky'])} · now</span></div></div>
<p class="bkt">{e(tl)} <a>downbydown.com/leaders/rush/…</a></p>
<div class="lk"><img src="{CARD}{e(R['card'])}" alt=""><div class="lkb"><span class="mu">downbydown.com</span><b>Pass rushers through Week 10</b><span>{e(R['title'])}</span></div></div></div>'''
ig_card = f'''<div class="igp"><img src="{CARD}{e(R['sq'])}" alt=""><p class="cap">{e(ig_cap)}</p></div>'''

css = '''
body{margin:0;background:#f0f0f1;color:#1d2327;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
main{max-width:1100px;margin:0 auto;padding:20px 16px 60px}
.note{padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;margin:0 0 20px;line-height:1.55;font-size:14px}
h2.s{font-size:15px;margin:30px 0 4px}p.sd{margin:0 0 10px;color:#50575e;max-width:820px}
.box{background:#fff;border:1px solid #c3c4c7}
.item .ih{display:flex;align-items:baseline;gap:10px;padding:12px 16px 0}.item h3{font-size:15px;margin:0}.no{font-weight:600;color:#646970}
.body{display:grid;grid-template-columns:400px minmax(0,1fr);gap:20px;padding:12px 16px 16px}
.l img{display:block;width:100%;aspect-ratio:1200/630;border:1px solid #dcdcde;background:#f4f1ea}
.dl{display:flex;flex-wrap:wrap;gap:6px 14px;margin:8px 0 0}a{color:#2271b1;cursor:pointer}
.tk{font-size:15px;margin:0 0 14px}.pl{margin:0 0 6px;font-weight:600}
.row{display:flex;flex-wrap:wrap;gap:6px}
.b{display:inline-block;padding:0 10px;line-height:28px;min-height:30px;box-sizing:border-box;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;font:13px/28px inherit;cursor:pointer;text-decoration:none}
.b.sm{line-height:22px;min-height:24px;font-size:12px;padding:0 8px}
.rd{border-color:#cb2026;color:#cb2026}.x{border-color:#0f1419;color:#0f1419}.bs{border-color:#1185fe;color:#1185fe}.fb{border-color:#0866ff;color:#0866ff}.ig{border-color:#c13584;color:#c13584}
.bp{background:#2271b1;color:#fff}.b[disabled]{border-color:#c3c4c7;color:#8c8f94;background:#f6f7f7;cursor:default}
.ap{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:12px 0 0;padding-top:12px;border-top:1px solid #f0f0f1}
.ap.ask{display:block;background:#f6f7f7;border:1px solid #dcdcde;padding:10px 12px}.ap.ask p{margin:0 0 8px}
.d{color:#50575e}.st{margin:8px 0 0;color:#3c434a;line-height:1.9}
.dot{display:inline-block;width:8px;height:8px;border-radius:4px;background:#00a32a;margin-right:6px}.dot.bad{background:#d63638}
.fits{margin:10px 0 0;color:#646970}.tx{margin:12px 0 0;padding-top:10px;border-top:1px solid #f0f0f1;color:#2271b1;font-weight:600}
.states{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.state{background:#fff;border:1px solid #c3c4c7;padding:12px 14px}.state h4{margin:0 0 2px;font-size:13px}.state .ap{border-top:0;padding-top:0}
.gets{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px}
.gets>div>p.h{font-weight:600;margin:0 0 6px}
.bk{background:#fff;border:1px solid #c3c4c7;padding:14px}.bkh{display:flex;gap:10px;align-items:center}.av{width:40px;height:40px;border-radius:20px;background:#121417;color:#fff;font:600 12px/40px sans-serif;text-align:center}.mu{color:#646970}
.bkt{margin:8px 0 10px;font-size:15px;line-height:1.45}.lk{border:1px solid #dcdcde;border-radius:8px;overflow:hidden}.lk img{display:block;width:100%;aspect-ratio:1200/630}.lkb{padding:8px 12px;display:flex;flex-direction:column;gap:2px;border-top:1px solid #dcdcde}.lkb b{font-size:14px}.lkb span:last-child{color:#50575e}
.igp{background:#fff;border:1px solid #c3c4c7;padding:14px}.igp img{display:block;width:100%;max-width:420px;aspect-ratio:1;border:1px solid #dcdcde}.cap{white-space:pre-wrap;margin:10px 0 0;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:10px;font-size:13px}
.set>.sm{padding:12px 16px;font-size:14px;font-weight:600;border-bottom:1px solid #c3c4c7}
.sg{display:grid;grid-template-columns:200px minmax(0,1fr);gap:12px 16px;padding:16px;align-items:start}.sg>label,.sg>span{color:#50575e;padding-top:5px}
.sh{grid-column:1/-1;font-weight:600;font-size:14px;padding-top:12px;border-top:1px solid #f0f0f1}.faded{opacity:.4}
input[type=text],input[type=password]{width:420px;max-width:100%;min-height:30px;box-sizing:border-box;padding:0 8px;border:1px solid #8c8f94;border-radius:4px}
.dd{color:#646970;margin:4px 0 0;font-size:12px}.ok{color:#007017;font-weight:600}
.foot{padding:12px 16px;border-top:1px solid #c3c4c7;background:#f6f7f7}
@media (max-width:900px){.body,.gets,.states{grid-template-columns:minmax(0,1fr)}.sg{grid-template-columns:minmax(0,1fr)}}
'''
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auto publish mock</title><style>{css}</style></head><body><main>
<div class="note"><b>Mock: Auto publish (SCOPE §81).</b> Each Share pack item gets one <b>Auto publish</b> button. It posts the item to every network that can post by itself: <b>Instagram</b> (already connected) and <b>Bluesky</b> (connected once with an app password). Reddit, X and Facebook can’t post on their own, so their buttons stay exactly as they are. Week 10’s live item 6 below.</div>

<h2 class="s">Item 6 with the button</h2>
<p class="sd">Under the row you already use, one button, and a line saying where it will post.</p>
{item(R, ap)}

<h2 class="s">What happens when you press it</h2>
<p class="sd">It asks once, then posts to both and says how each went. A network that already has this item is left out, so nothing posts twice.</p>
<div class="states">
<div class="state"><h4>1. It asks first</h4>{confirm}</div>
<div class="state"><h4>2. Both posted</h4>{done}</div>
<div class="state"><h4>3. One didn’t go</h4>{part}</div>
<div class="state"><h4>4. Item 1 on Thursday: Instagram already posted it on its day</h4>{booked}</div>
</div>

<h2 class="s">What each one gets</h2>
<p class="sd">The same texts the pack already writes. Bluesky gets the X-and-Bluesky text with the card as the link preview; Instagram gets the square and its caption, as a single post.</p>
<div class="gets"><div><p class="h">Bluesky</p>{bsky_card}</div><div><p class="h">Instagram</p>{ig_card}</div></div>

<h2 class="s">In the settings box at the bottom</h2>
<p class="sd">A new Bluesky posting section under Instagram posting. You make the app password in Bluesky (Settings → Privacy and security → App passwords) and paste it here yourself.</p>
<div class="box set"><div class="sm">▾ Settings: the Tuesday email, your accounts, Instagram and Bluesky posting</div>
<div class="sg"><div class="sh faded" style="border-top:0;padding-top:0">The Tuesday email · Your accounts · Instagram posting</div><span class="faded">unchanged</span><div class="faded">Instagram stays connected as @{e(D['ig'])}; its Tuesday carousel and daily posts run as now.</div>
<div class="sh">Bluesky posting</div>
<span>Account</span><div>@{e(D['bsky'])} <span class="dd">(from Your accounts above)</span></div>
<label>App password</label><div><input type="password" placeholder="Paste an app password from Bluesky"><p class="dd">Checked when you save, then never shown again. Make one in Bluesky: Settings → Privacy and security → App passwords. Delete it there to cut the plugin off.</p>
<p class="dd" style="margin-top:8px">Once saved: <span class="ok">Connected as @{e(D['bsky'])}.</span> <label><input type="checkbox"> Disconnect (forget the app password)</label></p></div>
<div class="sh">Auto publish</div>
<span>Posts to</span><div><label><input type="checkbox" checked> Instagram</label><br><label><input type="checkbox" checked> Bluesky</label><p class="dd">Untick one to leave it out of the button. A network that isn’t connected is left out by itself.</p></div>
</div><div class="foot"><span class="b bp">Save settings</span></div></div>
</main></body></html>'''
open(os.path.join(here, 'index.html'), 'w').write(page)
print('ok', len(bsky_text), 'chars to Bluesky (300 max)')
