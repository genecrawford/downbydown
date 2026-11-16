"""Mock: one dark signup box across the site (Gene, 2026-11-15). Run from mockup-v42/ with _combine.html, _game.html and _post.html saved
from the live site (curl). Builds:
  combine.html  the board's end: the next-board block and the signup side by side, both dark
  game.html     the signup alone at the end of a game page, the same dark box
  post.html     a weekly post with your Divi newsletter section in the same dark look
  index.html    the three
Static: opens from disk; the live stylesheets load from downbydown.com."""
import re

def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

NOTE = '.mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 "IBM Plex Sans",sans-serif;color:#3b3b3b}.mock-note b{font-weight:600}.mock-note button{margin-left:6px;font:600 12px sans-serif;padding:3px 8px;border:1px solid #c9a400;background:#fff;cursor:pointer}'

# the dark signup box (what goes in dbd.css), modeled on the combine block (a.bd-next)
STRIP_CSS = '''
.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}
.dbd .dbd-sign{position:relative;display:grid;gap:6px;align-content:start;margin:8px 0 0;padding:26px clamp(18px,3vw,32px) 31px;border:0;border-radius:var(--dbd-r);background:var(--dbd-ink);color:#fff;overflow:hidden}
.dbd .dbd-sign::after{content:"";position:absolute;left:0;right:0;bottom:0;height:5px;background:var(--dbd-red)}
.dbd .dbd-sign .k{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.2em;color:#ff8a80}
.dbd .dbd-sign .h{font:800 clamp(30px,4.2vw,52px)/1 var(--dbd-hero);text-transform:uppercase;letter-spacing:.02em;color:#fff}
.dbd .dbd-sign .s{max-width:60ch;font:400 15px/1.5 var(--dbd-body);color:rgba(255,255,255,.8)}
.dbd .dbd-sign .dbd-sg-f{margin-top:10px}
.dbd .dbd-sign .wpforms-container{margin:0!important;max-width:520px}
.dbd .dbd-sign form{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start}
.dbd .dbd-sign .wpforms-field-container{flex:1 1 220px;min-width:0}
.dbd .dbd-sign .wpforms-field{padding:0!important}
.dbd .dbd-sign .wpforms-field-label{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;white-space:nowrap!important}
.dbd .dbd-sign input[type=email],.dbd .dbd-sign input[type=text]{width:100%!important;max-width:none!important;height:44px!important;padding:0 14px!important;border:1px solid rgba(255,255,255,.2)!important;border-radius:6px!important;background:#fff!important;box-shadow:none!important;color:var(--dbd-ink)!important;font:400 15px var(--dbd-body)!important}
.dbd .dbd-sign .wpforms-submit-container{padding:0!important;margin:0!important}
.dbd .dbd-sign button[type=submit]{height:44px!important;margin:0!important;padding:0 20px!important;border:0!important;border-radius:6px!important;background:var(--dbd-red)!important;box-shadow:none!important;color:#fff!important;font:700 13px var(--dbd-display)!important;letter-spacing:.12em!important;text-transform:uppercase}
.dbd .dbd-sign button[type=submit]:hover{background:#fff!important;color:var(--dbd-ink)!important}
.dbd .dbd-sign em.wpforms-error,.dbd .dbd-sign label.wpforms-error{color:#ff8a80!important;font-size:13px!important}
.dbd .dbd-sign .dbd-sg-on{display:none;margin:4px 0 0;font:400 15px/1.5 var(--dbd-body);color:rgba(255,255,255,.85)}
.dbd .dbd-sign.is-on .s,.dbd .dbd-sign.is-on .dbd-sg-f{display:none}.dbd .dbd-sign.is-on .dbd-sg-on{display:block}
/* on the boards, side by side with the next-board block */
.dbd .bd-pair{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:8px 0 0}
.dbd .bd-pair>a.bd-next,.dbd .bd-pair>.dbd-sign{margin:0}
.dbd .bd-pair a.bd-next b,.dbd .bd-pair .dbd-sign .h{font-size:clamp(28px,3vw,40px)}
@media (max-width:860px){.dbd .bd-pair{grid-template-columns:minmax(0,1fr)}}
'''
PLACEHOLDER = '<script>document.querySelectorAll(".dbd-sign input[type=email], .dxd-nl input[type=email]").forEach(function(i){if(!i.placeholder)i.placeholder="Your email";});</script>'

STRIP_RE = re.compile(r'<section class="dbd-panel dbd-sign" id="dbd-sign" data-type="(\w+)">.*?<div class="dbd-sg-f">(.*?)</div><p class="dbd-sg-on">(.*?)</p></div></section>', re.S)
def dark(m):
    return (f'<section class="dbd-sign" id="dbd-sign" data-type="{m.group(1)}"><span class="k">The email: every Tuesday</span><b class="h">The week in review</b>'
            '<span class="s">The scores, the biggest swings and the players who rose, one email after every week. Free.</span>'
            f'<div class="dbd-sg-f">{m.group(2)}</div><p class="dbd-sg-on">{m.group(3)}</p></section>')

def page(src, out, note, pair=False):
    h = open(src).read()
    assert STRIP_RE.search(h), src
    h = STRIP_RE.sub(dark, h, count=1)
    if pair:
        h = re.sub(r'(<a class="bd-next".*?</a>)(<section class="dbd-sign".*?</section>)', r'<div class="bd-pair">\1\2</div>', h, count=1, flags=re.S)
    h = h.replace('</head>', '<style>' + NOTE + STRIP_CSS + '</style></head>', 1)
    m = re.search(r'<main[^>]*>', h); h = h[:m.end()] + '<div class="mock-note">' + note + '</div>' + h[m.end():]
    h = h.replace('</body>', PLACEHOLDER + '</body>', 1)
    open(out, 'w').write(no_tracking(h))

jump = ' <button type="button" onclick="document.getElementById(\'dbd-sign\').scrollIntoView({block:\'center\'})">Jump to it</button>'
page('_combine.html', 'combine.html', '<b>Mock: the end of a board page.</b> The link to the next board and the email signup side by side, both dark, the same height. Below 860px they stack.' + jump, pair=True)
page('_game.html', 'game.html', '<b>Mock: the same dark signup alone at the end of a game page</b> (and player and kicker pages). The form’s label is hidden and the field says “Your email”; screen readers still hear the label.' + jump)

# your Divi newsletter section (divi/home-newsletter.html), in the same dark look
NL_CSS = '''
.dxd-nl{background:#121417!important;color:#fff!important;border-top:0!important;position:relative}
.dxd-nl::after{content:"";position:absolute;left:0;right:0;bottom:0;height:5px;background:#cb2026}
.dxd-nl h2{color:#fff!important}
.dxd-nl .dxd-lede{color:rgba(255,255,255,.8)!important}
.dxd-nl .dxd-dm .t b{color:#fff}
.dxd-nl .dxd-dm .t small{color:#ff8a80!important}
.dxd-nl .wpforms-container .wpforms-field-label{position:absolute!important;width:1px!important;height:1px!important;overflow:hidden!important;clip:rect(0 0 0 0)!important;white-space:nowrap!important}
.dxd-nl .wpforms-container form{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-start}
.dxd-nl .wpforms-container .wpforms-field-container{flex:1 1 220px;min-width:0}
.dxd-nl .wpforms-container .wpforms-field{padding:0!important}
.dxd-nl .wpforms-container input[type=email],.dxd-nl .wpforms-container input[type=text]{background:#fff!important;border-color:rgba(255,255,255,.2)!important}
.dxd-nl .wpforms-container .wpforms-submit-container{padding:0!important;margin:0!important}
.dxd-nl .wpforms-container button[type=submit]{margin:0!important}
.dxd-nl .wpforms-container button[type=submit]:hover{background:#fff!important;color:#121417!important}
.dxd-nl .wpforms-confirmation-container-full{background:rgba(255,255,255,.08)!important;border-color:rgba(255,255,255,.2)!important;color:#fff!important}
'''
h = open('_post.html').read()
h = h.replace('</head>', '<style>' + NOTE + NL_CSS + '</style></head>', 1)
i = h.find('<section class="dxd-nl">')
h = h[:i] + '<div class="mock-note" style="max-width:1240px;margin:16px auto"><b>Mock: your newsletter section, dark.</b> This is the Divi section on the home page and under each weekly post. Same colors, the same red bar along the bottom, the field and button on one line with “Your email” in the field. The email preview on the right stays as it is. After your go I’d send the full updated code for the section’s Code module.</div>' + h[i:]
h = h.replace('</body>', PLACEHOLDER + '<script>var s=document.querySelector(".dxd-nl");s&&s.scrollIntoView({block:"start"});</script></body>', 1)
open('post.html', 'w').write(no_tracking(h))

open('index.html', 'w').write('''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dark signup mock</title>
<style>body{margin:0;background:#f4f1ea;color:#121417;font:15px/1.55 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}main{max-width:680px;margin:0 auto;padding:32px 16px}
h1{font:900 24px/1.2 'Arial Black',Arial,sans-serif;text-transform:uppercase;margin:0 0 8px}ol{padding-left:20px}li{margin:0 0 10px}a{color:#b4261f;font-weight:600}</style></head><body><main>
<h1>One dark signup box</h1><p>Three views, each opening from disk.</p><ol>
<li><a href="combine.html">The end of a board page</a>: the next-board block and the signup side by side, both dark.</li>
<li><a href="game.html">The end of a game page</a>: the same dark signup alone (player and kicker pages match).</li>
<li><a href="post.html">A weekly post</a>: your Divi newsletter section in the same dark look (also the home page’s).</li></ol></main></body></html>''')
print('ok')
