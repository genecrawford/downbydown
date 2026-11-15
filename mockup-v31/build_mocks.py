"""Mock: the team page header in the team's colors, with smaller game chips (Gene, 2026-11-15). Run from mockup-v31/ with _t-min.html
saved from https://downbydown.com/team/min/ (curl). Builds team-min.html: the new header over today's, the game chips restyled in CSS
(the explorer draws them, so the live JS still runs)."""
import re

h = open('_t-min.html').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

i = h.find('<div class="dbd-ttitle">'); j = h.find('<div id="dbd-explorer"', i); OLD = h[i:j]
logo = re.search(r'<span class="dbd-lgw".*?</span>', OLD, flags=re.S).group(0)
logo = re.sub(r'width:\d+px;height:\d+px', 'width:96px;height:96px', logo, count=1).replace('width="48" height="48"', 'width="96" height="96"')
BG, AC = '#4f2683', '#ffc62f'
U = 'https://downbydown.com/'

NEW = (f'<header class="dbd-tpanel" style="--k-bg:{BG};--k-ac:{AC}">'
       f'<div class="tp-mark">{logo}</div>'
       '<div class="tp-main">'
       '<p class="tp-kick" id="dbd-kicker">2026 season · Weeks 1 to 10 · Bye week 6 · 6-2</p>'
       '<h1>Vikings <span>offense</span></h1>'
       '<p class="tp-sub">Every Minnesota offensive snap so far, drawn on the field. Pick games and filters, then hover or click any play to see what happened. Or start from <a href="' + U + 'team/min/players/">the Vikings players</a>.</p>'
       '<div class="tp-row"><nav class="tp-switch" aria-label="Offense or defense"><a href="' + U + 'team/min/" aria-current="page">Offense</a><a href="' + U + 'team/min/defense/">Defense</a></nav>'
       '<nav class="tp-hop" aria-label="Further down this page"><a href="#around">News <b>40</b></a><a href="#around">Videos <b>12</b></a></nav></div>'
       '</div>'
       '<dl class="tp-stats">'
       '<div><dt>Offense</dt><dd>#31<small>of 32 in EPA</small></dd></div>'
       '<div><dt>Defense</dt><dd><a href="' + U + 'team/min/defense/">#2</a><small>of 32 in EPA allowed</small></dd></div>'
       '<div><dt>Snaps</dt><dd id="dbd-snaps">447</dd></div>'
       '<div><dt>Next</dt><dd><a href="' + U + 'game/2026-week-11-vikings-at-49ers/">at SF</a><small>Sun, Nov 22 · 8:20 PM</small></dd></div>'
       '</dl></header>')


G = [('1','vs','GB','W','39-22'),('2','at','CHI','W','9-3'),('3','at','TB','W','23-16'),('4','vs','MIA','W','15-10'),('5','at','NO','W','23-13'),('7','vs','IND','W','16-10'),('8','at','DET','L','15-19'),('9','vs','BUF','L','6-23')]
lg = lambda t, n: f'<img src="https://a.espncdn.com/combiner/i?img=/i/teamlogos/nfl/500/{t.lower()}.png&w=64&h=64" width="{n}" height="{n}" alt="" style="width:{n}px;height:{n}px">'
def chips(n, first_on=True):
    out = f'<button class="dbd-game" type="button" aria-pressed="{str(first_on).lower()}"><span class="wk">SEASON</span><span class="opp">All games</span><span class="res">8 games · 6-2</span></button>'
    for w, ha, o, r, sc in G:
        out += f'<button class="dbd-game" type="button" aria-pressed="false"><span class="wk">WK {w} {ha}</span><span class="opp">{lg(o, n)}{o}</span><span class="res"><b class="{"" if r == "W" else "l"}">{r}</b> {sc}</span></button>'
    return '<div class="dbd-gstrip">' + out + '</div>'
STATIC = '<div class="mock-chips">' + chips(16) + '</div>'
STATIC_OLD = '<div class="mock-oldchips">' + chips(22) + '</div>'

CSS = '''<style>
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}
.dbd .mock-note b{font-weight:600}
.dbd .mock-old{margin-top:30px;opacity:.9}

/* the team's own panel, the same colors and band as the game page's face-off and Keys panel */
.dbd .dbd-tpanel{position:relative;display:grid;grid-template-columns:auto minmax(0,1fr) minmax(220px,300px);gap:clamp(16px,2.4vw,32px);align-items:center;
  background:var(--k-bg);color:#fff;border-radius:var(--dbd-r);padding:26px clamp(18px,2.6vw,32px) 31px;overflow:hidden;margin:10px 0 4px}
.dbd .dbd-tpanel::after{content:"";position:absolute;left:0;right:0;bottom:0;height:5px;background:var(--k-ac)}
.dbd .tp-mark{align-self:start}
.dbd .tp-kick{margin:0 0 8px;font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.2em;color:var(--k-ac)}
.dbd .dbd-tpanel h1{margin:0;font:800 clamp(30px,4vw,52px)/1 var(--dbd-hero);text-transform:uppercase;letter-spacing:.02em;color:#fff}
.dbd .dbd-tpanel h1 span{color:rgba(255,255,255,.55)}
.dbd .tp-sub{margin:12px 0 0;max-width:60ch;font-size:15px;line-height:1.5;color:rgba(255,255,255,.82)}
.dbd .tp-sub a{color:#fff;font-weight:600;text-decoration:underline;text-decoration-color:var(--k-ac);text-decoration-thickness:2px;text-underline-offset:3px}
.dbd .tp-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:16px}
.dbd .tp-switch{display:inline-flex;padding:3px;gap:2px;border-radius:var(--dbd-r);background:rgba(0,0,0,.22)}
.dbd .tp-switch a{padding:7px 16px;border-radius:calc(var(--dbd-r) - 2px);font:700 12px var(--dbd-display);letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.8);text-decoration:none}
.dbd .tp-switch a[aria-current]{background:var(--k-ac);color:var(--k-bg)}
.dbd .tp-hop{display:flex;gap:8px}
.dbd .tp-hop a{display:inline-flex;align-items:baseline;gap:6px;padding:7px 12px;border:1px solid rgba(255,255,255,.4);border-radius:4px;font:700 13px/1 var(--dbd-display);letter-spacing:.06em;text-transform:uppercase;color:#fff;text-decoration:none}
.dbd .tp-hop a b{font:600 11px/1 var(--dbd-mono);color:var(--k-ac)}
.dbd .tp-stats{display:grid;grid-template-columns:1fr 1fr;margin:0;border:1px solid rgba(255,255,255,.16);border-radius:var(--dbd-r)}
.dbd .tp-stats>div{padding:12px 14px}
.dbd .tp-stats>div:nth-child(even){border-left:1px solid rgba(255,255,255,.16)}
.dbd .tp-stats>div:nth-child(n+3){border-top:1px solid rgba(255,255,255,.16)}
.dbd .tp-stats dt{font:700 10px var(--dbd-display);text-transform:uppercase;letter-spacing:.18em;color:rgba(255,255,255,.6)}
.dbd .tp-stats dd{margin:4px 0 0;font:800 26px/1 var(--dbd-hero);color:var(--k-ac)}
.dbd .tp-stats dd a{color:inherit;text-decoration:none}
.dbd .tp-stats dd small{display:block;margin-top:4px;font:500 11px var(--dbd-mono);color:rgba(255,255,255,.65)}
@container dbd (max-width:900px){.dbd .dbd-tpanel{grid-template-columns:auto minmax(0,1fr)}.dbd .tp-stats{grid-column:1 / -1;grid-template-columns:repeat(4,1fr)}.dbd .tp-stats>div:nth-child(n){border-top:0;border-left:1px solid rgba(255,255,255,.16)}.dbd .tp-stats>div:first-child{border-left:0}}
@container dbd (max-width:560px){.dbd .dbd-tpanel{grid-template-columns:1fr}.dbd .tp-mark .dbd-lgw{width:60px!important;height:60px!important}.dbd .tp-mark img{width:60px!important;height:60px!important}.dbd .mock-new .mock-chips .dbd-gstrip{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px}.dbd .tp-stats{grid-template-columns:1fr 1fr}.dbd .tp-stats>div:nth-child(odd){border-left:0}.dbd .tp-stats>div:nth-child(n+3){border-top:1px solid rgba(255,255,255,.16)}}

/* the game chips, one line each: week, opponent, result. The selected chip in the team's color */
.dbd .mock-chips{margin:14px 0 0}.dbd .mock-oldchips{margin-top:16px}.dbd .mock-oldchips .dbd-gstrip,.dbd .mock-chips .dbd-gstrip{display:flex;flex-wrap:wrap;gap:8px}
.dbd .mock-new .mock-chips .dbd-gstrip{gap:6px}
.dbd .mock-new .mock-chips .dbd-game{flex-direction:row;align-items:center;gap:8px;min-width:0;padding:5px 10px 5px 8px;border-radius:999px}
.dbd .mock-new .mock-chips .dbd-game .wk{font:600 10px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .mock-new .mock-chips .dbd-game .opp{font:700 14px/1 var(--dbd-display);gap:5px}
.dbd .mock-new .mock-chips .dbd-game .opp svg,.dbd .mock-new .mock-chips .dbd-game .opp .dbd-lgw{width:16px!important;height:16px!important}
.dbd .mock-new .mock-chips .dbd-game .opp img{width:16px;height:16px}
.dbd .mock-new .mock-chips .dbd-game .res{font:500 11px var(--dbd-mono)}
.dbd .mock-new .mock-chips .dbd-game[aria-pressed="true"]{background:var(--k-bg);border-color:var(--k-bg);box-shadow:none;color:#fff}
.dbd .mock-new .mock-chips .dbd-game[aria-pressed="true"] .wk,.dbd .mock-new .mock-chips .dbd-game[aria-pressed="true"] .res{color:rgba(255,255,255,.75)}
.dbd .mock-new .mock-chips .dbd-game[aria-pressed="true"] .res b{color:var(--k-ac)}
.dbd #dbd-explorer{display:none}
.dbd .mock-oldchips .dbd-game .opp img{width:22px;height:22px}
</style>'''

note = '<div class="mock-note"><b>The Vikings offense page, in the Vikings colors.</b> The header becomes the team\'s own panel, the same purple and gold band as the game page. Inside it: the season line in gold, the title, the note, the Offense | Defense switch (the current side in gold) and the News and Videos links. On the right, four numbers about this team: offense and defense rank, snaps, and the next game with a link to it. Below, the game chips shrink to one line each (week, opponent, result) and the one you pick fills with the team color. The defense page gets the same panel.</div>'
old = '<div class="mock-old"><div class="mock-note">Today, for comparison (header only).</div>' + OLD.replace('id="dbd-kicker"', '').replace('id="dbd-snaps"', '') + STATIC_OLD + '</div>'
out = h[:i] + note + NEW + STATIC + h[j:]
# the explorer, then today's header for comparison under the new page's games: put it after the explorer's host div closes is fiddly, so it goes at the end of main
out = out.replace('<main class="dbd-content', '<main class="mock-new dbd-content', 1).replace('style="--dbd-team:', f'style="--k-bg:{BG};--k-ac:{AC};--dbd-team:', 1)
k = out.rfind('</main>'); out = out[:k] + old + out[k:]
open('team-min.html', 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))
print('ok')
