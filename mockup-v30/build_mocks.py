"""Mock: the game page header, rebalanced (Gene, 2026-11-15: "this section is now not balanced enough"). Run from mockup-v30/ with
_g-min.html saved from https://downbydown.com/game/2026-week-10-vikings-at-packers/ (curl). Two options over today's header:
header-a.html (two columns, evened out) and header-b.html (face-off: offense left, the game in the middle, defense right)."""
import re

h = open('_g-min.html').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

i = h.find('<div class="dbd-mtitle">'); j = h.find('</aside>', i) + len('</aside></div>'); OLD = h[i:j]
logos = re.findall(r'<span class="dbd-lgw".*?</span>', OLD, flags=re.S)   # [MIN 40, GB 40, MIN 34, GB 34]
def logo(k, size):  # a logo span at a new size
    s = logos[k]; s = re.sub(r'width:\d+px;height:\d+px', f'width:{size}px;height:{size}px', s, count=1)
    return re.sub(r'width="\d+" height="\d+"', f'width="{size}" height="{size}"', s)
preview = re.search(r'<a class="dbd-vmk gb".*?</a>', OLD, flags=re.S).group(0)
line = re.search(r'<div class="dbd-line">.*?</div>', OLD, flags=re.S).group(0)
kick = 'Week 10 · Sunday 1:00 PM ET · Lambeau Field'
sub = 'Season numbers through Week 10: 8 games for Vikings, 9 for Packers. Ranks are out of 32; #1 is the best offense or the stingiest defense.'
U = 'https://downbydown.com/game/2026-week-10-vikings-at-packers/'
flip = lambda cls: (f'<div class="{cls}"><a href="{U}" aria-current="page">{logo(2, 20)}<span>Vikings offense</span></a>'
                    f'<a href="{U}packers/">{logo(3, 20)}<span>Packers offense</span></a></div>')

CSS = '''<style>
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}
.dbd .mock-note b{font-weight:600}
.dbd .mock-old{margin-top:34px}

/* the flip, both options: bigger, with each team's logo, the current one dark */
.dbd .mflip{display:inline-flex;border:1px solid var(--dbd-line-2);border-radius:var(--dbd-r);overflow:hidden}
.dbd .mflip a{display:flex;align-items:center;gap:8px;padding:8px 14px 8px 10px;font:600 12px var(--dbd-display);text-transform:uppercase;letter-spacing:.12em;color:var(--dbd-ink-2);text-decoration:none}
.dbd .mflip a+a{border-left:1px solid var(--dbd-line-2)}
.dbd .mflip a[aria-current]{background:var(--dbd-ink);color:#fff}

/* A: two columns that end level. No logo pair over the headline (the box has them); the box is wider, starts with the headline and
   stretches to the flip; each team is one row that never wraps */
.dbd .ma{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:clamp(18px,3vw,48px);align-items:stretch;padding:10px 0 6px}
.dbd .ma h1{font-size:clamp(28px,3.6vw,50px);line-height:1;margin:0}
.dbd .ma h1 .o,.dbd .ma h1 .d{display:inline-block;box-shadow:inset 0 -.14em 0 var(--u)}
.dbd .ma h1 .vs{font:600 .5em var(--dbd-display);color:var(--dbd-muted);letter-spacing:.14em;margin:0 .4em;vertical-align:.4em}
.dbd .ma .dbd-sub{max-width:60ch;margin:16px 0 18px}
.dbd .ma .dbd-gamebox{display:flex;flex-direction:column}
.dbd .ma .dbd-gamebox .dbd-pb{flex:1;display:flex;flex-direction:column;justify-content:space-between;gap:12px}
.dbd .ma .mrow{display:grid;grid-template-columns:40px minmax(0,1fr) auto;gap:12px;align-items:center;padding:6px 0}
.dbd .ma .mrow+.mrow{border-top:1px solid var(--dbd-line)}
.dbd .ma .mrow b{display:block;font:700 26px/1 var(--dbd-display);letter-spacing:.06em}
.dbd .ma .mrow small{display:block;margin-top:2px;font:400 13px var(--dbd-body);color:var(--dbd-ink-2);white-space:nowrap}
.dbd .ma .mrow .rk{font:500 11px/1.6 var(--dbd-mono);color:var(--dbd-muted);text-align:right;white-space:nowrap}
.dbd .ma .dbd-line{margin-top:0}
@media (max-width:1000px){.dbd .ma{grid-template-columns:minmax(0,1fr)}}

/* B: a face-off. Offense on the left, the game in the middle, defense on the right, the headline's two halves under each team */
.dbd .mb{margin:10px 0 6px}
.dbd .mb .dbd-kicker{justify-content:center}
.dbd .mb .dbd-kicker::after{content:"";width:22px;height:2px;background:var(--dbd-red)}
.dbd .mb .face{display:grid;grid-template-columns:minmax(0,1fr) 300px minmax(0,1fr);align-items:center;gap:clamp(16px,3vw,40px);padding:18px 0 4px}
.dbd .mb .side{display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px}
.dbd .mb .side h1,.dbd .mb .side .h1{margin:0;font:800 clamp(26px,3.2vw,44px)/1 var(--dbd-hero);text-transform:uppercase;letter-spacing:.02em;color:var(--dbd-ink)}
.dbd .mb .side .u{display:inline-block;box-shadow:inset 0 -.14em 0 var(--u)}
.dbd .mb .side .meta{font:500 12px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .mb .side .meta b{font-weight:600;color:var(--dbd-ink)}
.dbd .mb .mid{border:1px solid var(--dbd-line-2);border-radius:var(--dbd-r);background:var(--dbd-panel);padding:14px 16px;text-align:center}
.dbd .mb .mid .vs{display:block;font:800 30px/1 var(--dbd-hero);color:var(--dbd-line-2);margin:0 0 10px}
.dbd .mb .mid .dbd-badge{margin-bottom:10px}
.dbd .mb .mid .dbd-line{justify-content:center;gap:18px;margin-top:0;border-top:0;padding-top:0}
.dbd .mb .mid .dbd-vmk{justify-content:center;margin-top:12px}
.dbd .mb .foot{display:flex;flex-direction:column;align-items:center;gap:14px;padding:14px 0 6px}
.dbd .mb .foot .dbd-sub{margin:0;text-align:center;max-width:70ch}
@media (max-width:860px){.dbd .mb .face{grid-template-columns:1fr 1fr}.dbd .mb .mid{grid-column:1 / -1;grid-row:2}}
</style>'''

A = ('<div class="ma"><div class="t-stagger">'
     f'<p class="dbd-kicker">{kick}</p>'
     '<h1><span class="o" style="--u:#4F2683">Vikings offense</span><span class="vs">vs</span><span class="d" style="--u:#203731">Packers defense</span></h1>'
     f'<p class="dbd-sub">{sub}</p>' + flip('mflip') + '</div>'
     '<aside class="dbd-panel dbd-gamebox"><div class="dbd-ph"><span class="dbd-lab"><b>The game</b></span><span class="dbd-badge">Upcoming</span></div><div class="dbd-pb"><div>'
     f'<div class="mrow">{logo(2, 40)}<span><b>MIN</b><small>Vikings · 6-2</small></span><span class="rk">OFF #31<br><a class="dbd-dlink" href="https://downbydown.com/team/min/defense/">DEF #2</a></span></div>'
     f'<div class="mrow">{logo(3, 40)}<span><b>GB</b><small>Packers · 6-3</small></span><span class="rk">OFF #13<br><a class="dbd-dlink" href="https://downbydown.com/team/gb/defense/">DEF #7</a></span></div>'
     f'</div><div>{line}{preview}</div></div></aside></div>')

B = ('<div class="mb">'
     f'<p class="dbd-kicker">{kick}</p>'
     '<div class="face">'
     f'<div class="side">{logo(0, 72)}<h1><span class="u" style="--u:#4F2683">Vikings offense</span></h1><span class="meta"><b>OFF #31</b> · 6-2</span></div>'
     f'<div class="mid"><span class="dbd-badge">Upcoming</span><span class="vs">VS</span>{line}{preview}</div>'
     f'<div class="side">{logo(1, 72)}<div class="h1"><span class="u" style="--u:#203731">Packers defense</span></div><span class="meta"><b>DEF #7</b> · 6-3</span></div>'
     '</div>'
     f'<div class="foot"><p class="dbd-sub">{sub}</p>' + flip('mflip') + '</div></div>')

note = lambda t: f'<div class="mock-note">{t}</div>'
for name, new, say in (
    ('header-a.html', A, '<b>Option A: two columns that end level.</b> The logo pair above the headline goes (the game box already shows both teams). The box widens to 380px and runs the full height of the headline block, so both columns start and end together. Each team is one row that never wraps (the "6-/3" break is gone), ranks stacked on the right. The flip becomes a real switch with each team\'s logo.'),
    ('header-b.html', B, '<b>Option B: a face-off.</b> The offense on the left and the defense on the right, each with a big logo, its half of the headline underlined in its color, and the rank that matters for it (OFF for the offense, DEF for the defense). The game sits in the middle: status, the line, the total, kickoff and the preview. The note and the flip center underneath. On a phone the two teams sit side by side with the game under them.'),
):
    out = h[:i] + note(say) + new + '<div class="mock-old">' + note('Today, for comparison.') + OLD + '</div>' + h[j:]
    open(name, 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))
print('ok', len(logos))
