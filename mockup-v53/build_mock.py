"""Mock v53 (SCOPE §80): a chart at the top of every position tab on Leaders, and Pass rushers on a tab of their own.
Frames: the live tabs (_wr/_te/_rb/_qb/_def.html). Data: _charts.json (pull.py) and ../mockup-v52/_rush.json is NOT used: the rushers come
from the live Defenses page's band (2.36.0), moved onto its own tab. Static, opens from disk; the tab bar links between the mock pages.
Writes wr.html, te.html, rb.html, qb.html, rush.html, def.html, index.html."""
import html, json, math, re

e = lambda s: html.escape(str(s), quote=True)
D = json.load(open('_charts.json')); T = D['teams']; N = D['through']
nick = lambda t: T.get(t, {}).get('nick', t)
c1 = lambda t: T.get(t, {}).get('c1', '#6b6f78')
POS = {'wr': 'wide receivers', 'te': 'tight ends', 'rb': 'backs', 'qb': 'quarterbacks'}
HEAD = {'wr': ('Targets and what they’re worth', 'target share against EPA a target'), 'te': ('Targets and what they’re worth', 'target share against EPA a target'),
        'rb': ('Touches and what they’re worth', 'touches a game against yards a touch'), 'qb': ('Efficiency and accuracy', 'EPA a dropback against completion over expected')}


def fmt(kind, v):
    if kind == 'pct': return f'{v * 100:.1f}%'
    if kind == 'sgn': return ('+' if v > 0 else '−' if v < 0 else '') + f'{abs(v):.2f}'
    if kind == 'sgn1': return ('+' if v > 0 else '−' if v < 0 else '') + f'{abs(v):.1f}'
    return f'{v:.1f}'


def tick_fmt(kind, v):
    if kind == 'pct': return f'{v * 100:.0f}%'
    if kind in ('sgn', 'sgn1'): return ('+' if v > 0 else '−' if v < 0 else '') + (f'{abs(v):.1f}' if kind == 'sgn1' or abs(v) >= 1 else f'{abs(v):.2f}'.rstrip('0').rstrip('.'))
    return f'{v:g}'


def nice(lo, hi):
    span = hi - lo or 1
    for st in (0.02, 0.05, 0.1, 0.2, 0.25, 0.5, 1, 2, 2.5, 5, 10):
        if span / st <= 6: break
    a = math.floor(lo / st) * st; b = math.ceil(hi / st) * st
    return a, b, st


def place(rows, named, pts, w, px, box, rad):
    """The same placement the plugin uses (DBD_Rush::place): right, left, above-ending, below, above-starting; the first spot clear of labels and dots."""
    taken = []; out = {}
    def hit(a, dots=True):
        for b in taken:
            if a[0] < b[2] and b[0] < a[2] and a[1] < b[3] and b[1] < a[3]: return True
        if not dots: return False
        for i, (x, y, r) in pts.items():
            if i == a[4]: continue
            if x + r > a[0] and x - r < a[2] and y + r > a[1] and y - r < a[3]: return True
        return False
    for row in [r for r in rows if r['id'] in named]:
        cx, cy, r = pts[row['id']]; tw = w(row['n']); g = r + 4
        cands = [(cx + g, cy + px * .35, 'start'), (cx - g, cy + px * .35, 'end'), (cx + r, cy - r - 4, 'end'), (cx + r, cy + r + px, 'end'), (cx - r, cy - r - 4, 'start')]
        pick = None
        for dots in (True, False):  # clear of labels and dots; then clear of labels; else no name (the dot keeps its tip)
            for c in cands:
                x0 = c[0] if c[2] == 'start' else c[0] - tw; a = (x0, c[1] - px * .95, x0 + tw, c[1] + px * .3, row['id'])
                if a[0] < box[0] or a[2] > box[2] or a[1] < box[1] or a[3] > box[3]: continue
                if not hit(a, dots): pick = (c, a); break
            if pick: break
        if pick: taken.append(pick[1]); out[row['id']] = pick[0]
    return out


def chart(rows, cfg, W, H, m, named, px, rmin, rmax, cls, unit):
    xl, xr, yt, yb = m; xk, xlab, xf = cfg['x']; yk, ylab, yf = cfg['y']
    x0, x1, xs = nice(min(r['x'] for r in rows), max(r['x'] for r in rows)); y0, y1, ys = nice(min(r['y'] for r in rows), max(r['y'] for r in rows))
    X = lambda v: xl + (W - xl - xr) * (v - x0) / (x1 - x0); Y = lambda v: H - yb - (H - yt - yb) * (v - y0) / (y1 - y0)
    umin = min(r['u'] for r in rows); umax = max(r['u'] for r in rows)
    R = lambda u: rmin + (rmax - rmin) * math.sqrt((u - umin) / ((umax - umin) or 1))
    f = f'font-family="IBM Plex Mono,monospace" font-size="{px}"'
    o = f'<svg class="pr-svg {cls}" viewBox="0 0 {W} {H}" role="img" aria-label="{e(xlab)} against {e(ylab)}, {len(rows)} {e(unit)}">'
    v = x0
    while v <= x1 + 1e-9:
        o += f'<line x1="{X(v):.1f}" x2="{X(v):.1f}" y1="{yt}" y2="{H - yb}" class="pr-g{" z" if abs(v) < 1e-9 and x0 < 0 else ""}"/><text x="{X(v):.1f}" y="{H - yb + px + 6}" text-anchor="middle" {f} class="pr-tk">{tick_fmt(xf, v)}</text>'; v += xs
    v = y0
    while v <= y1 + 1e-9:
        o += f'<line x1="{xl}" x2="{W - xr}" y1="{Y(v):.1f}" y2="{Y(v):.1f}" class="pr-g{" z" if abs(v) < 1e-9 and y0 < 0 else ""}"/><text x="{xl - 8}" y="{Y(v) + px * .35:.1f}" text-anchor="end" {f} class="pr-tk">{tick_fmt(yf, v)}</text>'; v += ys
    mx, my = cfg['mx'], cfg['my']
    o += f'<line x1="{X(mx):.1f}" x2="{X(mx):.1f}" y1="{yt}" y2="{H - yb}" class="pr-md"/><line x1="{xl}" x2="{W - xr}" y1="{Y(my):.1f}" y2="{Y(my):.1f}" class="pr-md"/>'
    o += f'<text x="{(xl + W - xr) / 2:.0f}" y="{H - 6}" text-anchor="middle" {f} class="pr-ax">{e(xlab.upper())}</text><text transform="translate({px + 2} {(yt + H - yb) / 2:.0f}) rotate(-90)" text-anchor="middle" {f} class="pr-ax">{e(ylab.upper())}</text>'
    pts = {r['id']: (X(r['x']), Y(r['y']), R(r['u'])) for r in rows}
    wf = lambda s: len(s) * px * .6; box = (xl, yt, W - xr, H - yb)
    at = place(sorted(rows, key=lambda r: -r['x']), named, pts, wf, px, box, rmax)
    team = {}
    for t in {r['t'] for r in rows}: team.update(place(rows, {r['id'] for r in rows if r['t'] == t}, pts, wf, px, box, rmax))
    for r in rows: team.setdefault(r['id'], (pts[r['id']][0] + pts[r['id']][2] + 4, pts[r['id']][1] + px * .35, 'start'))
    for r in sorted(rows, key=lambda r: (r['id'] in named, -r['u'])):
        cx, cy, rad = pts[r['id']]
        tip = f'<b>{e(r["n"])}</b> · {e(nick(r["t"]))} {e(r["pos"])}<br>{e(xlab)} {fmt(xf, r["x"])} · {e(ylab)} {fmt(yf, r["y"])}<br>{int(r["u"])} {e(cfg["unit"])}'
        lab = lambda p, c: f'<text class="{c}" x="{p[0]:.1f}" y="{p[1]:.1f}" text-anchor="{p[2]}" {f}>{e(r["n"])}</text>'
        o += (f'<g class="pr-d{" lab" if r["id"] in at else ""}" data-t="{r["t"]}" data-tip="{e(tip)}" tabindex="0"><circle cx="{cx:.1f}" cy="{cy:.1f}" r="{rad:.1f}" fill="{c1(r["t"])}"><title>{e(r["n"])}, {e(r["t"])}</title></circle>'
              + (lab(at[r['id']], 'pr-l') if r['id'] in at else '') + lab(team[r['id']], 'pr-h') + '</g>')
    return o + '</svg>'


def named_ids(rows, k):
    a = sorted(rows, key=lambda r: (-r['x'], r['n']))[:k]; b = sorted(rows, key=lambda r: (-r['y'], r['n']))[:k]
    return {r['id'] for r in a} | {r['id'] for r in b}


def opts(rows):
    return '<option value="">All teams</option>' + ''.join(f'<option value="{t}">{e(nick(t))}</option>' for t in sorted({r['t'] for r in rows}, key=nick))


def band(g):
    cfg = D['tabs'][g]; rows = cfg['rows']; xk, xlab, xf = cfg['x']; yk, ylab, yf = cfg['y']; u = cfg['unit']
    hx = max(rows, key=lambda r: r['x']); hy = max(rows, key=lambda r: r['y'])
    if g in ('wr', 'te'):
        say = (f'<b>{e(hx["n"])}</b> ({e(nick(hx["t"]))}) draws the most: {fmt(xf, hx["x"])} of his team’s targets. <b>{e(hy["n"])}</b> ({e(nick(hy["t"]))}) gets the most from each: '
               f'{fmt(yf, hy["y"])} EPA a target, on {int(hy["u"])} targets. Bigger dots are more targets.')
    elif g == 'rb':
        say = (f'<b>{e(hx["n"])}</b> ({e(nick(hx["t"]))}) gets the ball most: {fmt(xf, hx["x"])} touches a game. <b>{e(hy["n"])}</b> ({e(nick(hy["t"]))}) makes the most of each: '
               f'{fmt(yf, hy["y"])} yards a touch, on {int(hy["u"])} touches. Bigger dots are more touches.')
    else:
        say = (f'<b>{e(hx["n"])}</b> ({e(nick(hx["t"]))}) leads in EPA a dropback, {fmt(xf, hx["x"])} on {int(hx["u"])} attempts; <b>{e(hy["n"])}</b> ({e(nick(hy["t"]))}) in completion over expected, '
               f'{fmt(yf, hy["y"])}. Bigger dots are more attempts.')
    say += ' Up and to the right of the dashed lines is both.'
    h, sub = HEAD[g]
    wide = chart(rows, cfg, 960, 540, (68, 24, 16, 48), named_ids(rows, 6), 12, 3, 9, 'wide', POS[g])
    narrow = chart(rows, cfg, 380, 420, (50, 10, 10, 38), named_ids(rows, 3), 11, 2, 6, 'narrow', POS[g])
    note = {'wr': 'Target share is his part of the team’s targets; EPA a target is the expected points his targets added. ',
            'te': 'Target share is his part of the team’s targets; EPA a target is the expected points his targets added. ',
            'rb': 'Touches are carries plus catches. ',
            'qb': 'EPA a dropback counts every pass and sack; CPOE is completion percentage over what the throws’ difficulty predicts. '}[g]
    return (f'<section class="dbd-band ld-band" id="ld-chart"><h2 class="dbd-bandh">{e(h)}<span>{e(sub)}, every one of the {e(POS[g])} with {cfg["min"]}+ {e(u)}</span></h2>'
            f'<section class="dbd-panel pr-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Through Week {N}</b> · {len(rows)} {e(POS[g])}</span><label class="pr-hl">Highlight <select class="pr-team">{opts(rows)}</select></label></div>'
            f'<p class="dbd-mv-say">{say}</p><div class="pr-chart">{wide}{narrow}</div>'
            f'<p class="dbd-note">{note}The players are the boards’ own pool below, {cfg["min"]}+ {e(u)}; the dashed lines are the middle of it. Every number here is on the player’s page. The team you highlight is remembered on this device and carries to every tab.</p></section></section>')


css = '''<style id="pr-mock">
.dbd .pr-panel .dbd-ph{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.dbd .pr-hl{font:600 11px var(--dbd-mono);text-transform:uppercase;letter-spacing:.06em;color:var(--dbd-muted)}
.dbd .pr-hl select{margin-left:6px;font:500 13px var(--dbd-body);padding:4px 8px;border:1px solid var(--dbd-line-2);border-radius:4px;background:#fff;color:var(--dbd-ink);text-transform:none;letter-spacing:0}
.dbd .pr-chart{padding:4px var(--dbd-edge) 8px}
.dbd .pr-svg{display:block;width:100%;height:auto}
.dbd .pr-svg.narrow{display:none}
.dbd .pr-svg .pr-g{stroke:rgba(22,30,54,.10);stroke-width:1}
.dbd .pr-svg .pr-g.z{stroke:rgba(22,30,54,.32)}
.dbd .pr-svg .pr-md{stroke:var(--dbd-muted);stroke-width:1.5;stroke-dasharray:5 4}
.dbd .pr-svg .pr-tk{fill:var(--dbd-muted)}
.dbd .pr-svg .pr-ax{fill:var(--dbd-ink);font-weight:600;letter-spacing:.06em}
.dbd .pr-svg .pr-l{fill:var(--dbd-ink)}
.dbd .pr-svg .pr-h{display:none;fill:var(--dbd-ink);font-weight:600}
.dbd .pr-d{outline:none}
.dbd .pr-d circle{stroke:#fff;stroke-width:1.5}
.dbd .pr-d:hover circle,.dbd .pr-d:focus circle{stroke:var(--dbd-ink);stroke-width:2}
.dbd .pr-chart.hl .pr-d{opacity:.14}
.dbd .pr-chart.hl .pr-d .pr-l{display:none}
.dbd .pr-chart.hl .pr-d.on{opacity:1}
.dbd .pr-chart.hl .pr-d.on .pr-h{display:inline}
.fz-note{max-width:1240px;margin:12px auto;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:14px/1.5 -apple-system,sans-serif;color:#1d2327;box-sizing:border-box}
@container dbd (max-width:640px){.dbd .pr-chart{padding:4px 6px 8px}.dbd .pr-svg.wide{display:none}.dbd .pr-svg.narrow{display:block}}
</style>'''
js = '''<script>(function(){var K='dbd_pr_team';function hl(s,t){var c=s.closest('.pr-panel').querySelector('.pr-chart');c.classList.toggle('hl',!!t);c.querySelectorAll('.pr-d').forEach(function(g){g.classList.toggle('on',g.getAttribute('data-t')===t)});
document.querySelectorAll('.pr-t tbody tr').forEach(function(r){r.classList.toggle('on',!!t&&r.getAttribute('data-t')===t)});}
document.querySelectorAll('.pr-team,#pr-team').forEach(function(s){s.addEventListener('change',function(){hl(s,s.value);try{s.value?localStorage.setItem(K,s.value):localStorage.removeItem(K)}catch(e){}});
try{var v=localStorage.getItem(K);if(v&&s.querySelector('option[value="'+v+'"]')){s.value=v;hl(s,v)}}catch(e){}});
var m=document.getElementById('pr-more');if(m)m.addEventListener('click',function(){document.querySelectorAll('.pr-t tbody tr.pr-x').forEach(function(r){r.classList.remove('pr-x')});m.remove()});})();</script>'''


def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    x = re.sub(r'<script[^>]*(googlesitekit|rush\.js|leaders\.js)[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer|_googlesitekit)(?:(?!</script>).)*</script>', '', x, flags=re.S)


TABS = [('', 'Fantasy', None), ('wr', 'Wide receivers', 'wr.html'), ('te', 'Tight ends', 'te.html'), ('rb', 'Backs', 'rb.html'), ('qb', 'Quarterbacks', 'qb.html'), ('k', 'Kickers', None), ('rush', 'Pass rushers', 'rush.html'), ('def', 'Defenses', 'def.html')]


def tabbar(cur):
    CUR = ' aria-current="page"'
    a = ''.join(f'<a href="{f or "https://downbydown.com/leaders/" + (k + "/" if k else "")}"{CUR if k == cur else ""}>{e(lab)}</a>' for k, lab, f in TABS)
    return f'<nav class="dbd-wkbar ld-tabs" aria-label="Leaders"><div class="dbd-wktabs">{a}</div></nav>'


def page(frame, cur, insert, note, keep_rest=True, strip_rush=False):
    H = open(frame).read()
    a = H.index('<nav class="dbd-wkbar ld-tabs"'); b = H.index('</nav>', a) + len('</nav>')
    rest = H[b:]
    if strip_rush and 'id="ld-rush"' in rest:  # the Defenses tab without the 2.36.0 band
        i = rest.index('<section class="dbd-band" id="ld-rush"'); j = rest.index('</section></section>', i) + len('</section></section>'); rest = rest[:i] + rest[j:]
    if not keep_rest:
        m = rest.index('<p class="dbd-note ld-foot">'); rest = rest[m:]
    out = H[:a] + tabbar(cur) + insert + rest
    out = no_tracking(out).replace('</head>', css + '</head>', 1).replace('</body>', js + '</body>', 1)
    return re.sub(r'(<body[^>]*>)', lambda mm: mm.group(1) + f'<div class="fz-note">{note}</div>', out, count=1)


for g in ('wr', 'te', 'rb', 'qb'):
    n = D['tabs'][g]
    open(f'{g}.html', 'w').write(page(f'_{g}.html', g, band(g), f'<b>Mock: a chart on every position tab (SCOPE §80) · {e(POS[g].capitalize())}.</b> Real numbers through Week {N}, the boards’ own pool ({len(n["rows"])} players). '
                                     'The tab bar moves between the mock’s tabs; the highlight carries across them. The boards below are the live page as it is.'))
# Pass rushers: the live band (2.36.0), moved onto its own tab, the same table it has now
H = open('_def.html').read(); i = H.index('<section class="dbd-band" id="ld-rush"'); j = H.index('</section></section>', i) + len('</section></section>')
rush = H[i:j].replace('id="pr-team"', 'id="pr-team" class="pr-team"', 1)
open('rush.html', 'w').write(page('_def.html', 'rush', rush, f'<b>Mock: Pass rushers on a tab of their own (SCOPE §80).</b> The live chart from 2.36.0, moved off the Defenses tab. Its table stays, since no board lists rushers.', keep_rest=False))
open('def.html', 'w').write(page('_def.html', 'def', '', '<b>Mock: the Defenses tab (SCOPE §80).</b> Back to the 32 team units only; Pass rushers have their own tab.', strip_rush=True))
open('index.html', 'w').write(f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock v53: a chart on every Leaders tab</title>
<style>body{{margin:0;background:#f6f7f7;color:#1d2327;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}}main{{max-width:640px;margin:0 auto;padding:32px 16px}}a{{color:#2271b1}}li{{margin:4px 0}}</style></head>
<body><main><h1 style="font-size:22px;font-weight:600">Mock v53: a chart on every Leaders tab</h1><p>SCOPE §80. Real numbers through Week {N}.</p><ul>
<li><a href="wr.html">Wide receivers</a>: target share against EPA a target</li><li><a href="te.html">Tight ends</a>: the same axes</li><li><a href="rb.html">Backs</a>: touches a game against yards a touch</li>
<li><a href="qb.html">Quarterbacks</a>: EPA a dropback against CPOE</li><li><a href="rush.html">Pass rushers</a>: their own tab</li><li><a href="def.html">Defenses</a>: the team boards only</li></ul></main></body></html>''')
print('ok', {g: len(D['tabs'][g]['rows']) for g in D['tabs']})
