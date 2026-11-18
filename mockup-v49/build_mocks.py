"""Mock v49 (SCOPE §75): Week leaders, a sixth pack item. The card (1200x630), its Instagram square, and how it sits on the Share pack tab.
Week 10's real leaders, from data/box/w10.json and data/week/w10.json (worked out by hand here; the plugin computes them)."""
import html, os
here = os.path.dirname(os.path.abspath(__file__))
e = lambda s: html.escape(str(s), quote=True)
LOGO = 'https://a.espncdn.com/i/teamlogos/nfl/500/{}.png'
P = [  # label, leader, team, big stat, unit, sub, then #2 and #3
    ('Passing', 'Joe Burrow', 'cin', '409', 'yds', '35 of 44 · 4 TD · 0 INT', [('Brock Purdy', 'sf', '389'), ('Trevor Lawrence', 'jax', '352')]),
    ('Rushing', 'James Cook', 'buf', '157', 'yds', '18 carries · 1 TD', [('Jahmyr Gibbs', 'det', '153'), ('Omarion Hampton', 'lac', '105')]),
    ('Receiving', 'Ja’Marr Chase', 'cin', '231', 'yds', '11 catches', [('Jaxon Smith-Njigba', 'sea', '171'), ('Christian McCaffrey', 'sf', '161')]),
    ('Touchdowns', 'Christian McCaffrey', 'sf', '3', 'TD', '1 rushing · 2 receiving', [('George Pickens', 'dal', '3'), ('Jahmyr Gibbs', 'det', '2')]),
    ('Defense', 'Rams', 'lar', '−0.52', 'EPA', 'a play allowed · held the Cardinals to 6', [('Chiefs', 'kc', '−0.28'), ('Lions', 'det', '−0.26')]),
]


def panel(p):
    lab, name, t, big, unit, sub, rest = p
    more = ''.join(f'<li><img src="{LOGO.format(rt)}" alt=""><span>{e(rn)}</span><b>{e(rv)}</b></li>' for rn, rt, rv in rest)
    return f'''<div class="pn"><div class="lb">{e(lab)}</div><div class="who"><img src="{LOGO.format(t)}" alt=""><span>{e(name)}</span></div>
<div class="big">{e(big)}<small>{e(unit)}</small></div><div class="sub">{e(sub)}</div><ol>{more}</ol></div>'''


card = f'''<div class="card"><div class="brand"><img class="lg" src="https://downbydown.com/wp-content/plugins/down-by-down/assets/card-logo.png" alt="Down x Down"><span>2026 · WEEK 10 · LEADERS</span></div><div class="rule"></div>
<div class="ttl">WEEK 10 <em>LEADERS</em></div>
<div class="grid">{''.join(panel(p) for p in P)}</div>
<div class="foot"><span>downbydown.com</span><span>Data: nflverse · defense by EPA a play allowed</span></div></div>'''

head = 'Week 10 leaders: Joe Burrow threw for 409 yards, James Cook ran for 157, Ja’Marr Chase caught 11 for 231, and the Rams held Arizona to 6.'
title = 'Week 10 leaders: Joe Burrow threw for 409 yards and 4 TDs, James Cook ran for 157, Ja’Marr Chase caught 11 for 231, Christian McCaffrey and George Pickens scored 3 each, and the Rams held the Cardinals to 6.'

css = '''
body{margin:0;background:#f0f0f1;color:#1d2327;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
main{max-width:1240px;margin:0 auto;padding:20px 16px 60px}
.note{padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;margin:0 0 20px;line-height:1.5}
h2.s{font-size:15px;margin:26px 0 10px}
.frame{width:1200px;height:630px;transform-origin:0 0}
.wrap1{width:900px;height:473px;overflow:hidden;border:1px solid #c3c4c7}.wrap1 .frame{transform:scale(.75)}
.card{width:1200px;height:630px;background:#f4f1ea;position:relative;font-family:"IBM Plex Sans",sans-serif;color:#121417;box-sizing:border-box}
.brand{display:flex;justify-content:space-between;align-items:center;padding:15px 44px 0;height:49px}.brand .lg{height:34px;mix-blend-mode:multiply}.brand span{font:700 16px "Barlow Condensed";letter-spacing:.2em;color:#3b4255}
.rule{height:6px;background:#cb2026;margin-top:0}
.ttl{font:800 40px/1 "Tourney",sans-serif;padding:26px 44px 0;letter-spacing:.01em}.ttl em{font-style:normal;color:#cb2026}
.grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;padding:22px 44px 0}
.pn{background:#fffdf8;border:1px solid #dcd7cc;padding:14px 14px 10px;height:384px;box-sizing:border-box;display:flex;flex-direction:column}
.lb{font:700 13px "Barlow Condensed";letter-spacing:.16em;text-transform:uppercase;color:#6b6f78}
.who{display:flex;align-items:center;gap:8px;margin-top:14px;min-height:52px}.who img{width:34px;height:34px}.who span{font:700 24px/1.02 "Barlow Condensed";text-transform:uppercase}
.big{font:800 66px/1 "Tourney",sans-serif;margin-top:22px}.big small{font:600 16px "IBM Plex Mono";margin-left:6px;color:#6b6f78}
.sub{font:500 13px/1.45 "IBM Plex Mono";color:#3b4255;margin-top:10px;min-height:38px}
.pn ol{list-style:none;margin:auto 0 0;padding:10px 0 0;border-top:1px solid #e3dfd5}.pn li{display:grid;grid-template-columns:20px 1fr auto;gap:6px;align-items:center;font-size:14px;padding:4px 0}.pn li img{width:20px;height:20px}.pn li b{font:600 14px "IBM Plex Mono"}
.foot{position:absolute;left:0;right:0;bottom:0;height:44px;border-top:1px solid #e3dfd5;display:flex;justify-content:space-between;align-items:center;padding:0 44px;font:500 13px "IBM Plex Mono";color:#6b6f78}.foot span:first-child{color:#121417;font-weight:600}
.sqw{width:360px;height:360px;overflow:hidden;border:1px solid #c3c4c7}.sq{width:1080px;height:1080px;transform:scale(.3333);transform-origin:0 0;background:#f4f1ea;position:relative;box-sizing:border-box;padding:64px 60px 0}
.sq .hd{font:700 70px/1.02 "Barlow Condensed";height:340px;margin:0;display:flex;align-items:flex-end}
.sq .in{margin-top:38px;width:960px;height:504px;overflow:hidden;border:2px solid #dcd7cc}.sq .in .frame{transform:scale(.8)}
.sq .ft{position:absolute;left:60px;right:60px;bottom:50px;margin:0;display:flex;justify-content:space-between;font:600 26px/1 "IBM Plex Mono";border-top:6px solid #cb2026;padding-top:22px}.sq .ft span+span{color:#6b6f78;font-weight:500}
.it{background:#fff;border:1px solid #c3c4c7;max-width:1100px}
.ih{display:flex;gap:10px;padding:12px 16px 0;align-items:baseline}.ih h3{margin:0;font-size:15px}.no{color:#646970;font-weight:600}
.ib{display:grid;grid-template-columns:400px 1fr;gap:20px;padding:12px 16px 16px}.ib .th{width:400px;height:210px;overflow:hidden;border:1px solid #dcdcde}.ib .th .frame{transform:scale(.3333)}
.dl{display:flex;gap:14px;margin-top:8px;font-size:13px}.dl a{color:#2271b1}
.tk{font-size:15px;margin:0 0 14px}.pl{font-weight:600;margin:0 0 6px}.row{display:flex;gap:6px;flex-wrap:wrap}
.b{padding:0 12px;line-height:28px;border:1px solid;border-radius:3px;background:#f6f7f7;font-size:13px}.rd{color:#cb2026}.x{color:#0f1419}.bs{color:#1185fe}.fb{color:#0866ff}.ig{color:#c13584}
.fits{color:#646970;margin:10px 0 0}.tx{color:#2271b1;font-weight:600;margin:12px 0 0;border-top:1px solid #f0f0f1;padding-top:10px}
'''
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Week leaders mock</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans:wght@400;500;600&family=Tourney:wght@800&display=swap" rel="stylesheet"><style>{css}</style></head><body><main>
<div class="note"><b>Mock: Week leaders, a new Share pack item (SCOPE §75).</b> Week 10’s real numbers. One card with five leaders (passing yards, rushing yards, receiving yards, touchdowns and team defense), the top player big and the next two under them, plus its Instagram square and the same Post it row as the other items. Defense is the team that allowed the fewest EPA a play; individual sacks and interceptions are mostly ties in a single week (five players had 2 sacks in Week 10).</div>
<h2 class="s">The card, 1200 × 630 (shown at three quarters)</h2>
<div class="wrap1"><div class="frame">{card}</div></div>
<h2 class="s">The Instagram square, 1080 × 1080 (shown at a third)</h2>
<div class="sqw"><div class="sq"><p class="hd">{e(head)}</p><div class="in"><div class="frame">{card}</div></div><p class="ft"><span>downbydown.com</span><span>Link in bio</span></p></div></div>
<h2 class="s">On the Share pack tab, as item 5 (keys to the game becomes 6)</h2>
<div class="it"><div class="ih"><span class="no">5</span><h3>Week leaders</h3></div><div class="ib"><div><div class="th"><div class="frame">{card}</div></div><div class="dl"><a>Download image</a><a>Download square for Instagram</a></div></div>
<div><p class="tk">{e(title)}</p><p class="pl">Post it</p><div class="row"><span class="b rd">r/downbydown</span><span class="b x">X</span><span class="b bs">Bluesky</span><span class="b fb">Facebook</span><span class="b ig">Instagram</span></div>
<p class="fits">Also fits r/nfl, r/fantasyfootball: post there by hand with the texts below.</p><p class="tx">▸ Texts to copy</p></div></div></div>
</main></body></html>'''
open(os.path.join(here, 'leaders.html'), 'w').write(page)
open(os.path.join(here, 'index.html'), 'w').write('<!doctype html><meta charset="utf-8"><title>Mock v49</title><p style="font:15px sans-serif;padding:24px">SCOPE §75. <a href="leaders.html">Week leaders</a>.</p>')
print('ok')
