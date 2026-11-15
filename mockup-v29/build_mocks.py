"""Mock: Keys to the game branded to the offense (Gene, 2026-11-15). Run from mockup-v29/ with the _g-*.html game pages saved
from downbydown.com (curl). Builds keys-min.html (a final, in place), keys-upcoming.html (an upcoming game, Players to watch in
its own panel below) and keys-teams.html (twelve offenses' panels, to check the color rule across the league)."""
import re, json

TEAMS = json.load(open('../data/teams.json'))
TEAMS = TEAMS if isinstance(TEAMS, dict) else {t['abbr']: t for t in TEAMS}

def lum(h):
    h = h.lstrip('#'); c = []
    for i in (0, 2, 4):
        v = int(h[i:i + 2], 16) / 255
        c.append(v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4)
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]
def contrast(a, b):
    x, y = lum(a), lum(b); return (max(x, y) + 0.05) / (min(x, y) + 0.05)
def mix(a, b, t):
    a = a.lstrip('#'); b = b.lstrip('#')
    return '#' + ''.join('%02x' % round(int(a[i:i + 2], 16) * (1 - t) + int(b[i:i + 2], 16) * t) for i in (0, 2, 4))

INK = '#121417'
def colors(abbr):
    """The panel color: the team's first color that is dark enough for white text (10:1), else its primary deepened toward ink.
    Pure black lifts to the site's ink. The accent: the other team color if it reads on that (4:1), else the primary lightened."""
    t = TEAMS[abbr]; c1, c2 = t['c1'], t.get('c2') or INK
    bg = next((c for c in (c1, c2) if contrast(c, '#ffffff') >= 10), None)
    if bg is None:
        k = 0.0; bg = c1
        while contrast(bg, '#ffffff') < 10 and k < 1: k += 0.05; bg = mix(c1, INK, k)
    if lum(bg) < 0.004: bg = INK
    ac = next((c for c in (c2, c1) if c.lower() != bg.lower() and contrast(c, bg) >= 4.5), None)
    if ac is None and c2.lower() != bg.lower():  # deepen the panel further (to 15:1 at most) so the second color can read on it
        base, k, d = bg, 0.0, bg
        while contrast(c2, d) < 4.5 and contrast(d, '#ffffff') < 15 and k < 1: k += 0.05; d = mix(base, INK, k)
        if contrast(c2, d) >= 4.5: bg, ac = d, c2
    if ac is None:
        k = 0.0; ac = c1
        while contrast(ac, bg) < 4.5 and k < 1: k += 0.05; ac = mix(c1, '#ffffff', k)
    return bg, ac

def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

CSS = '''<style>
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}
.dbd .mock-note b{font-weight:600}
.dbd .mock-sw{display:inline-block;width:12px;height:12px;border-radius:2px;vertical-align:-1px;margin:0 2px 0 6px;border:1px solid rgba(0,0,0,.15)}
.dbd .mock-h{margin:22px 0 8px;font:700 13px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-ink-2)}

/* the offense's panel: its color behind, its other color picking out the numbers, tags and the band along the bottom */
.dbd .dbd-keys.tm{background:var(--k-bg);border-color:var(--k-bg);color:#fff;overflow:hidden;padding-bottom:5px}
.dbd .dbd-keys.tm::after{content:"";position:absolute;left:0;right:0;bottom:0;height:5px;background:var(--k-ac)}
.dbd .dbd-keys.tm>.dbd-ph{border-bottom-color:rgba(255,255,255,.14);padding-top:12px;padding-bottom:12px}
.dbd .dbd-keys.tm>.dbd-ph .dbd-lab{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.66)}
.dbd .dbd-keys.tm>.dbd-ph .dbd-lab b{color:#fff;font-size:15px}
.dbd .dbd-keys.tm>.dbd-ph .dbd-lab .kt{color:var(--k-ac)}
.dbd .dbd-keys.tm .dbd-badge{border-color:rgba(255,255,255,.3);color:rgba(255,255,255,.85)}
.dbd .dbd-keys.tm .dbd-take{border-color:rgba(255,255,255,.14);padding:18px 18px 20px}
.dbd .dbd-keys.tm .dbd-take .n{color:var(--k-ac);opacity:.5}
.dbd .dbd-keys.tm .dbd-take .tag{color:var(--k-ac)}
.dbd .dbd-keys.tm .dbd-take.even .tag{color:rgba(255,255,255,.6)}
.dbd .dbd-keys.tm .dbd-take h3{color:#fff}
.dbd .dbd-keys.tm .dbd-take p{color:rgba(255,255,255,.8)}
.dbd .dbd-keys.tm .dbd-take .small{color:var(--k-ac)}
</style>'''

def section(h):
    i = h.find('<section class="dbd-panel dbd-keys"'); j = h.find('</section>', i) + len('</section>'); return i, j, h[i:j]

def tile(h, abbr):
    """The 20px team tile the page already prints for this team (box score or players to watch)."""
    m = re.search(r'<span class="dbd-lgw"[^>]*>(?:(?!</span>).)*?aria-label="[^"]*' + re.escape(TEAMS[abbr]['nick']) + r'".*?</span>', h, flags=re.S)
    return m.group(0) if m else ''

def brand(h, abbr):
    i, j, S = section(h); bg, ac = colors(abbr); nick = TEAMS[abbr]['nick']
    S = S.replace('<section class="dbd-panel dbd-keys"', f'<section class="dbd-panel dbd-keys tm" style="--k-bg:{bg};--k-ac:{ac}"', 1)
    S = re.sub(r'<span class="dbd-lab"><b>Keys to the game</b> · ',
               f'<span class="dbd-lab">{tile(h, abbr)}<span><b>Keys to the game</b> · <span class="kt">{nick} offense</span>, ', S, count=1)
    S = S.replace('</span><span class="dbd-badge">', '</span></span><span class="dbd-badge">', 1)
    pw = ''
    k = S.find('<h4 class="dbd-wph">Players to watch</h4>')
    if k > -1:  # players to watch leaves the offense's panel: it covers both teams
        pw = '<section class="dbd-panel dbd-pwp"><div class="dbd-ph"><span class="dbd-lab"><b>Players to watch</b> · both teams</span></div><div style="height:10px"></div>' + S[k + len('<h4 class="dbd-wph">Players to watch</h4>'):]
        S = S[:k] + '</section>'
    return i, j, S + pw, bg, ac

note = lambda t: f'<div class="mock-note">{t}</div>'

# 1. a final, in place, with today's panel under it
h = open('_g-MIN.html').read(); i, j, S, bg, ac = brand(h, 'MIN'); old = h[i:j]
out = h[:i] + note('<b>Keys to the game in the offense\'s colors.</b> Vikings purple behind, gold for the numbers, the tags, the offense\'s name and the band along the bottom. Flip the page to the Packers offense and it turns green and gold.') + S \
      + note('Today, for comparison.') + old + h[j:]
open('keys-min.html', 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))

# 2. an upcoming game: players to watch moves to its own light panel under the keys
h = open('_g-up.html').read(); i, j, S, *_ = brand(h, 'MIN')
out = h[:i] + note('<b>An upcoming game.</b> Players to watch covers both teams, so it leaves the Vikings panel and sits in its own panel right under it.') + S + h[j:]
open('keys-upcoming.html', 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))

# 3. twelve offenses: the color rule across the league
h = open('_g-MIN.html').read(); i0, j0, _ = section(h)
body = note('<b>Twelve offenses, one rule.</b> The panel takes the team\'s first color that is dark enough for white text. Teams whose main color is bright (Bengals orange, Saints gold, Browns orange) use their dark second color; teams with no dark color (Chiefs red, 49ers red, Titans and Chargers blue, Dolphins teal) get their main color deepened. The accent is the other team color when it reads on that, else a lighter shade of the main one (Bills).')
for t in ('MIN', 'GB', 'KC', 'SF', 'CIN', 'NO', 'CLE', 'LV', 'TEN', 'MIA', 'LAC', 'BUF'):
    g = open(f'_g-{t}.html').read(); i, j, S, bg, ac = brand(g, t); S = S[:S.find('</section>') + len('</section>')]  # the keys alone
    body += f'<h3 class="mock-h">{TEAMS[t]["nick"]}<span class="mock-sw" style="background:{bg}"></span>{bg}<span class="mock-sw" style="background:{ac}"></span>{ac}</h3>' + S
    print(t, bg, ac, round(contrast(bg, '#ffffff'), 1), round(contrast(ac, bg), 1))
m = re.search(r'<main[^>]*>', h); e = h.rfind('</main>')
out = h[:m.end()] + body + h[e:]
open('keys-teams.html', 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))
