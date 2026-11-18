"""Mock v50 (SCOPE §76): Instagram posts itself. The Tuesday carousel (the five live Week 10 squares and its caption),
and the Share pack tab's status line and new Instagram posting settings. Writes index.html."""
import html, os
here = os.path.dirname(os.path.abspath(__file__))
e = lambda s: html.escape(str(s), quote=True)
SL = [('ig-week-10-swing', 'Week 10’s biggest swing: one play with 1:05 left moved the Commanders’ chance of beating the Giants by 39 points.'),
      ('ig-week-10-snaps', 'Chimere Dike played 95% of the Titans’ snaps, up from 21%.'),
      ('ig-week-10-offense', 'Best offense: the 49ers (+0.45 EPA a play). Worst: the Cardinals (−0.52).'),
      ('ig-week-10-targets', 'Nico Collins drew 44% of the Texans’ targets, up from 21%.'),
      ('ig-week-10-leaders', 'Leaders: Burrow 409 passing, Cook 157 rushing, Chase 231 receiving; the Rams held the Cardinals to 6.')]
cap = 'Week 10 in five charts:\n\n' + '\n'.join(f'{i + 1}. {t}' for i, (_, t) in enumerate(SL)) + '\n\nFull charts: link in bio (downbydown.com)\n\n#NFL #FantasyFootball #Commanders #Giants #Titans #49ers #Cardinals #Texans #Bengals #Bills #Rams'
slides = ''.join(f'<div class="sl"><img src="https://downbydown.com/?card={k}" alt=""><span>{i + 1}/5</span></div>' for i, (k, _) in enumerate(SL))
css = '''
body{margin:0;background:#f0f0f1;color:#1d2327;font:13px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
main{max-width:1100px;margin:0 auto;padding:20px 16px 60px}
.note{padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;margin:0 0 20px;line-height:1.5;font-size:14px}
h2.s{font-size:15px;margin:26px 0 10px}
.ig{display:grid;grid-template-columns:minmax(0,420px) minmax(0,1fr);gap:20px;background:#fff;border:1px solid #c3c4c7;padding:16px}
.strip{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:6px}
.sl{position:relative;flex:0 0 100%;scroll-snap-align:start}.sl img{display:block;width:100%;aspect-ratio:1;border:1px solid #dcdcde}
.sl span{position:absolute;top:8px;right:8px;background:rgba(18,20,23,.7);color:#fff;font-size:12px;padding:1px 7px;border-radius:9px}
.hint{color:#646970;margin:6px 0 0}
.cap{white-space:pre-wrap;font:14px/1.5 -apple-system,sans-serif;background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:12px;margin:0}
.ch{font-weight:600;margin:0 0 6px}.cn{color:#646970;margin:6px 0 0}
.mail{margin:0;color:#50575e;line-height:1.9}.dot{display:inline-block;width:8px;height:8px;border-radius:4px;background:#00a32a;margin-right:6px}
.mail a{color:#2271b1}
.box{background:#fff;border:1px solid #c3c4c7}.box>.sm{padding:12px 16px;font-size:14px;font-weight:600;border-bottom:1px solid #c3c4c7}
.sg{display:grid;grid-template-columns:200px minmax(0,1fr);gap:12px 16px;padding:16px;align-items:start}.sg>label,.sg>span{color:#50575e;padding-top:5px}
.sh{grid-column:1/-1;font-weight:600;font-size:14px;padding-top:12px;border-top:1px solid #f0f0f1}
.faded{opacity:.4}
input[type=text],input[type=password]{width:420px;max-width:100%;min-height:30px;box-sizing:border-box;padding:0 8px;border:1px solid #8c8f94;border-radius:4px}
.d{color:#646970;margin:4px 0 0;font-size:12px}
.ok{color:#007017;font-weight:600}
.b{display:inline-block;padding:0 12px;line-height:28px;border:1px solid #2271b1;border-radius:3px;background:#f6f7f7;color:#2271b1;font-size:13px}.bp{background:#2271b1;color:#fff}
.foot{padding:12px 16px;border-top:1px solid #c3c4c7;background:#f6f7f7}
@media (max-width:800px){.ig{grid-template-columns:minmax(0,1fr)}.sg{grid-template-columns:minmax(0,1fr)}}
'''
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instagram posting mock</title><style>{css}</style></head><body><main>
<div class="note"><b>Mock: Instagram posts itself (SCOPE §76).</b> Every Tuesday at 9:00 AM Eastern, two hours after the pack email, the plugin posts one carousel to @down_by_down: the week’s five squares and one caption. Below: that carousel for Week 10 (swipe the slides), then the two places it shows on the Share pack tab.</div>
<h2 class="s">The Tuesday carousel</h2>
<div class="ig"><div><div class="strip">{slides}</div><p class="hint">Five slides, in pack order. Swipe or scroll sideways.</p></div>
<div><p class="ch">Caption</p><p class="cap">{e(cap)}</p><p class="cn">{len(cap)} of 2,200 characters · 11 hashtags</p></div></div>
<h2 class="s">At the top of the Share pack tab</h2>
<div class="box" style="padding:12px 16px"><p class="mail"><span class="dot"></span>Week 10 pack emailed Tuesday, Nov 17, 7:00 AM to 2 people. <a>Change the list</a><br><span class="dot"></span>Week 10 carousel posted to Instagram Tuesday, Nov 17, 9:00 AM. <a>View on Instagram</a></p></div>
<h2 class="s">In the settings box at the bottom</h2>
<div class="box"><div class="sm">▾ Settings: the Tuesday email, your accounts and Instagram posting</div>
<div class="sg"><div class="sh faded" style="border-top:0;padding-top:0">The Tuesday email</div><span class="faded">Send it, Send to</span><div class="faded">unchanged</div>
<div class="sh faded">Your accounts</div><span class="faded">Reddit, X, Bluesky, Facebook, Instagram</span><div class="faded">unchanged</div>
<div class="sh">Instagram posting</div>
<span>Post it</span><div><label><input type="checkbox" checked> Post each week’s carousel to Instagram, Tuesday 9:00 AM Eastern</label></div>
<label>Access token</label><div><input type="password" placeholder="Paste a new token to replace it"><p class="d"><span class="ok">Connected as @down_by_down.</span> Renewed Nov 16; good until Jan 15. The plugin renews it every week.</p></div>
<span>Last posts</span><div>Week 10 carousel posted Tuesday, Nov 17, 9:00 AM. <a>View</a><div style="margin-top:8px"><span class="b">Post the Week 10 carousel now</span></div></div>
</div><div class="foot"><span class="b bp">Save settings</span></div></div>
</main></body></html>'''
open(os.path.join(here, 'index.html'), 'w').write(page)
print('ok', len(cap))
