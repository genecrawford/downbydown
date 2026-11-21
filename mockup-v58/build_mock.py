"""Mock v58 (SCOPE §85): puts the rendered graphics (render.py) into one page that opens from disk. Usage: python3 build_mock.py <out dir> <today's square>."""
import base64, html, io, os, sys
from PIL import Image
here = os.path.dirname(os.path.abspath(__file__)); OUT, OLD = sys.argv[1], sys.argv[2]
def uri(p, w=864):
    im = Image.open(p).convert('RGB'); im = im.resize((w, round(im.height * w / im.width)), Image.LANCZOS); b = io.BytesIO(); im.save(b, 'JPEG', quality=87, optimize=True); return 'data:image/jpeg;base64,' + base64.b64encode(b.getvalue()).decode()
P = [('swing', 'The biggest swing', 'Wednesday'), ('snaps', 'The usage riser', 'Thursday'), ('offense', 'Best and worst offense', 'Friday'), ('targets', 'The target-share mover', 'Saturday'), ('leaders', 'Week leaders', 'Sunday'), ('league', 'League Leaders', 'Monday: new, one stat a week'), ('rush', 'Pass rushers', 'On the Auto publish button'), ('keys', 'Keys to the game', 'On the button, one per team')]
def grid(th): return ''.join(f'<figure><img src="{uri(os.path.join(OUT, k + "-" + th + ".png"))}" alt="{html.escape(n)}" loading="lazy"><figcaption><b>{html.escape(n)}</b><br>{html.escape(d)}</figcaption></figure>' for k, n, d in P)
css = '''
:root{--bg:#f4f1ea;--ink:#121417;--mut:#62666f;--line:#d9d4c8;--red:#cb2026;--card:#fff}
*{box-sizing:border-box}html{background:var(--bg)}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
main{max-width:1180px;margin:0 auto;padding:28px 16px 72px}h1{font-size:26px;line-height:1.2;margin:0 0 8px}h2{font-size:19px;margin:38px 0 4px}p{margin:0 0 12px;max-width:780px}.mu{color:var(--mut)}
img{display:block;width:100%;height:auto;background:#ddd}figure{margin:0;min-width:0}figcaption{padding:8px 0 0;font-size:14px;color:var(--mut)}figcaption b{color:var(--ink)}
.two{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px;max-width:820px}.three{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}
.wk{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;background:var(--card);border:1px solid var(--line);padding:12px 16px;margin:8px 0 18px}.wk b{font-size:18px;margin-right:10px}.wl{font-weight:600}.wn{font-size:14px}.sw{display:inline-flex;border:1px solid var(--ink)}.sw label{padding:7px 20px;cursor:pointer;font-weight:600}input[name=look]{position:absolute;opacity:0}
#k:focus-visible~.wk .sw,#p:focus-visible~.wk .sw{outline:2px solid var(--red);outline-offset:2px}#k:checked~.wk label[for=k],#p:checked~.wk label[for=p]{background:var(--ink);color:#fff}.pa{display:none}#k:checked~.ka{display:grid}#p:checked~.pb{display:grid}
@media(max-width:820px){.three{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:520px){.three,.two{grid-template-columns:minmax(0,1fr)}}
'''
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Social graphics mock</title><style>{css}</style></head><body><main>
<h1>Cleaner social posts</h1><p>Each post becomes one tall graphic (1080 × 1350) with one idea, big type and team colors, instead of a website card shrunk under a headline. Everything below is drawn from the real Week 10 numbers with the site’s fonts.</p>
<h2>Today and proposed</h2><p class="mu">The same post, at the same size on a phone.</p>
<div class="two"><figure><img src="{uri(OLD)}" alt="Today's square"><figcaption><b>Today</b></figcaption></figure><figure><img src="{uri(os.path.join(OUT, 'leaders-black.png'))}" alt="Proposed"><figcaption><b>Proposed</b></figcaption></figure></div>
<h2>The look, chosen week to week</h2><p class="mu">This is the new switch at the top of the Share pack tab. Try it: all eight posts change.</p>
<input type="radio" name="look" id="k" checked><input type="radio" name="look" id="p"><div class="wk"><b>Week 10 pack</b><span class="wl">Look this week</span><div class="sw"><label for="k">Black</label><label for="p">Paper</label></div><span class="mu wn">Posts not out yet use it. A new week starts with your last pick.</span></div>
<div class="three pa ka">{grid('black')}</div><div class="three pa pb">{grid('paper')}</div>
<h2>When there’s no photo</h2><p class="mu">Posts use headshots. If a player has no photo, or the site’s headshot switch is off, the post falls back to the logo and the number.</p>
<div class="two"><figure><img src="{uri(os.path.join(OUT, 'league-black.png'))}" alt="With a headshot"><figcaption><b>With a headshot</b></figcaption></figure><figure><img src="{uri(os.path.join(OUT, 'league-black-nophoto.png'))}" alt="Logo and number"><figcaption><b>The fallback</b></figcaption></figure></div>
</main></body></html>'''
open(os.path.join(here, 'index.html'), 'w').write(page); print('ok', round(len(page) / 1048576, 1), 'MB')
