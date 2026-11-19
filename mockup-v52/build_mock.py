"""Mock v52 (SCOPE §79): the pass rushers chart. Run from mockup-v52/ after pull.py.
rush.html: the live Leaders > Defenses page (_leaders_def.html) with the Pass rushers band added above the team boards.
card.html: the share card (1200x630) and the Instagram square (1080x1080) drawn in HTML/SVG the way DBD_Card would draw them, and the
pack item's text. Static: everything inline, opens from disk. Writes rush.html, card.html, index.html."""
import html, json, math, re

e = lambda s: html.escape(str(s), quote=True)
D = json.load(open('_rush.json')); R = D['rows']; T = D['teams']; N = D['thru']; S = D['season']
SITE = 'https://downbydown.com'
nick = lambda t: T.get(t, {}).get('nick', t)
c1 = lambda t: T.get(t, {}).get('c1', '#6b6f78')
L = D['lead']


def ticks(hi, step):
    return [round(i * step, 2) for i in range(int(math.floor(hi / step + 1e-9)) + 1)]


def chart(W, H, m, labels, font, ink, muted, line, dot_r, label_px, team_dots=True, hl_attr=True, bg=None, mid=None, cls=''):
    """The scatter: x pressures a game, y sacks a game, medians dashed, labelled rushers named."""
    xl, xr, yt, yb = m
    xmax = math.ceil(max(r['x'] for r in R) + 0.4); ymax = math.ceil(max(r['y'] for r in R) * 4 + 1) / 4
    X = lambda v: xl + (W - xl - xr) * v / xmax; Y = lambda v: H - yb - (H - yt - yb) * v / ymax
    o = f'<svg class="pr-svg{(" " + cls) if cls else ""}" viewBox="0 0 {W} {H}" role="img" aria-label="Pressures a game against sacks a game, {len(R)} pass rushers">'
    if bg: o += f'<rect width="{W}" height="{H}" fill="{bg}"/>'
    for v in ticks(xmax, 1):
        o += f'<line x1="{X(v):.1f}" x2="{X(v):.1f}" y1="{yt}" y2="{H - yb}" stroke="{line}" stroke-width="1"/><text x="{X(v):.1f}" y="{H - yb + label_px + 6}" text-anchor="middle" font-family="{font}" font-size="{label_px}" fill="{muted}">{v:g}</text>'
    for v in ticks(ymax, 0.25):
        o += f'<line x1="{xl}" x2="{W - xr}" y1="{Y(v):.1f}" y2="{Y(v):.1f}" stroke="{line}" stroke-width="1"/><text x="{xl - 8}" y="{Y(v) + label_px * .35:.1f}" text-anchor="end" font-family="{font}" font-size="{label_px}" fill="{muted}">{v:.2f}</text>'
    mc = mid or muted
    o += f'<line x1="{X(D["mx"]):.1f}" x2="{X(D["mx"]):.1f}" y1="{yt}" y2="{H - yb}" stroke="{mc}" stroke-width="1.5" stroke-dasharray="5 4"/>'
    o += f'<line x1="{xl}" x2="{W - xr}" y1="{Y(D["my"]):.1f}" y2="{Y(D["my"]):.1f}" stroke="{mc}" stroke-width="1.5" stroke-dasharray="5 4"/>'
    o += f'<text x="{(xl + W - xr) / 2:.0f}" y="{H - 6}" text-anchor="middle" font-family="{font}" font-size="{label_px}" font-weight="600" fill="{ink}" letter-spacing=".06em">PRESSURES A GAME</text>'
    o += f'<text transform="translate({label_px + 2} {(yt + H - yb) / 2:.0f}) rotate(-90)" text-anchor="middle" font-family="{font}" font-size="{label_px}" font-weight="600" fill="{ink}" letter-spacing=".06em">SACKS A GAME</text>'
    # labels: right of the dot, nudged down when two would overlap, flipped left near the right edge
    placed = []; lab_xy = {}
    for r in sorted([r for r in R if r['id'] in labels], key=lambda r: Y(r['y'])):
        txt = r['n']; w = len(txt) * label_px * 0.56; x = X(r['x']) + dot_r + 4; y = Y(r['y']) + label_px * 0.35; anchor = 'start'
        if x + w > W - xr: x = X(r['x']) + dot_r; y = Y(r['y']) - dot_r - 4; anchor = 'end'  # near the right edge: above the dot, ending at it
        x0 = x if anchor == 'start' else x - w
        for _ in range(12):
            hit = [p for p in placed if abs(p[1] - y) < label_px + 1 and not (x0 + w < p[0] or p[0] + p[2] < x0)]
            if not hit: break
            y = max(p[1] for p in hit) + label_px + 1
        placed.append((x0, y, w)); lab_xy[r['id']] = (x, y, anchor)
    dots = ''
    for r in sorted(R, key=lambda r: r['id'] in labels):
        tip = f'<b>{e(r["n"])}</b> · {e(nick(r["t"]))} {e(r["pos"])}<br>{r["prs"]} pressures, {r["sk"]:g} sacks, {r["qbh"]} QB hits in {r["g"]} games · {r["snap"]}% of snaps'
        fill = c1(r['t']) if team_dots else ink
        lb = ''
        if r['id'] in lab_xy:
            x, y, a = lab_xy[r['id']]
            lb = f'<text class="pr-l" x="{x:.1f}" y="{y:.1f}" text-anchor="{a}" font-family="{font}" font-size="{label_px}" fill="{ink}">{e(r["n"])}</text>'
        elif hl_attr:  # on the page, unlabelled rushers carry a hidden name the team highlight shows
            x = X(r['x']) + dot_r + 4; a = 'start'
            if x + len(r['n']) * label_px * 0.56 > W - xr: x = X(r['x']) - dot_r - 4; a = 'end'
            lb = f'<text class="pr-l pr-h" x="{x:.1f}" y="{Y(r["y"]) + label_px * .35:.1f}" text-anchor="{a}" font-family="{font}" font-size="{label_px}" fill="{ink}">{e(r["n"])}</text>'
        attr = f' data-t="{r["t"]}" data-tip="{e(tip)}" tabindex="0"' if hl_attr else ''
        dots += f'<g class="pr-d{" lab" if r["id"] in labels else ""}"{attr}><circle cx="{X(r["x"]):.1f}" cy="{Y(r["y"]):.1f}" r="{dot_r}" fill="{fill}" stroke="#fff" stroke-width="1.5"><title>{e(r["n"])}, {e(r["t"])}: {r["x"]:.1f} pressures and {r["y"]:.2f} sacks a game</title></circle>{lb}</g>'
    return o + dots + '</svg>'


labels = {r['id'] for r in R if r['lab']}
small_labels = {r['id'] for r in sorted(R, key=lambda r: -r['x'])[:3]} | {r['id'] for r in sorted(R, key=lambda r: -r['y'])[:3]}  # the phone chart names the top three on each axis
by = sorted(R, key=lambda r: (-r['x'], -r['y']))
opts = ''.join(f'<option value="{t}">{e(nick(t))}</option>' for t in sorted({r['t'] for r in R}, key=nick))
rows_html = ''; X10 = ' class="pr-x"'
for i, r in enumerate(by):
    rows_html += (f'<tr{X10 if i >= 10 else ""} data-t="{r["t"]}"><td class="rk">{i + 1}</td><td><a href="{SITE}/player/{e(r["slug"])}/">{e(r["n"])}</a><small>{e(r["t"])} · {e(r["pos"])} · {r["g"]} games</small></td>'
                  f'<td><b>{r["x"]:.1f}</b></td><td class="c-p">{r["prs"]}</td><td>{r["sk"]:g}</td><td class="c-h">{r["qbh"]}</td><td class="c-h">{r["tfl"]}</td><td class="c-s">{r["snap"]}%</td></tr>')
rp, rs = L['prs'], L['sk']
say = (f'<b>{e(rp["n"])}</b> ({e(nick(rp["t"]))}) gets there most: {rp["x"]:.1f} pressures a game, {rp["prs"]} in all. '
       f'<b>{e(rs["n"])}</b> ({e(nick(rs["t"]))}) finishes most: {rs["sk"]:g} sacks, {rs["y"]:.2f} a game. Up and to the right of the dashed lines is both.')
panel = f'''<section class="dbd-band" id="ld-rush"><h2 class="dbd-bandh">Pass rushers<span>pressures against sacks, every rusher with a regular role</span></h2>
<section class="dbd-panel pr-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Through Week {N}</b> · {len(R)} rushers, a game each</span><label class="pr-hl">Highlight <select id="pr-team"><option value="">All teams</option>{opts}</select></label></div>
<p class="dbd-mv-say">{say}</p>
<div class="pr-chart" id="pr-chart">{chart(960, 540, (64, 24, 16, 48), labels, 'IBM Plex Mono,monospace', '#121417', '#6b6f78', 'rgba(22,30,54,.10)', 6, 12, cls='wide')}{chart(380, 420, (46, 10, 10, 38), small_labels, 'IBM Plex Mono,monospace', '#121417', '#6b6f78', 'rgba(22,30,54,.10)', 4, 11, cls='narrow')}</div>
<div class="dbd-tw"><table class="pr-t"><thead><tr><th></th><th>Rusher</th><th>A game</th><th class="c-p">Pressures</th><th>Sacks</th><th class="c-h">QB hits</th><th class="c-h">TFL</th><th class="c-s">Snaps</th></tr></thead><tbody>{rows_html}</tbody></table></div>
<button class="dbd-cbtn pr-more" type="button" id="pr-more">Show all {len(R)}</button>
<p class="dbd-note">Pressures are Pro Football Reference's charting: hurries, hits and sacks it saw. Sacks, QB hits and tackles for loss come from nflverse's play-by-play, so the two don't always add up the same way. Rushers are linemen and linebackers who played at least half their team's games, on 40% or more of the snaps, with at least one pressure a game. The dashed lines are the middle of the pack. The team you highlight is remembered on this device.</p></section></section>'''

css = '''<style id="pr-mock">
.dbd .pr-panel .dbd-ph{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}
.dbd .pr-hl{font:600 11px var(--dbd-mono);text-transform:uppercase;letter-spacing:.06em;color:var(--dbd-muted)}
.dbd .pr-hl select{margin-left:6px;font:500 13px var(--dbd-body);padding:4px 8px;border:1px solid var(--dbd-line-2);border-radius:4px;background:#fff;color:var(--dbd-ink)}
.dbd .pr-chart{padding:4px var(--dbd-edge) 8px}
.dbd .pr-svg{display:block;width:100%;height:auto}
.dbd .pr-svg.narrow{display:none}
.dbd .pr-t tr.pr-x{display:none}
.dbd .pr-d{cursor:default;outline:none}
.dbd .pr-d .pr-h{display:none}
.dbd .pr-chart.hl .pr-d{opacity:.14}
.dbd .pr-chart.hl .pr-d .pr-l{display:none}
.dbd .pr-chart.hl .pr-d.on{opacity:1}
.dbd .pr-chart.hl .pr-d.on .pr-l{display:inline;font-weight:600}
.dbd .pr-d:hover circle,.dbd .pr-d:focus circle{stroke:#121417;stroke-width:2}
.dbd .pr-t{width:100%;border-collapse:collapse;font:500 12px var(--dbd-mono)}
.dbd .pr-t th{font:600 10px var(--dbd-mono);text-transform:uppercase;letter-spacing:.06em;color:var(--dbd-muted);text-align:right;padding:6px 10px;border-bottom:1px solid var(--dbd-line)}
.dbd .pr-t td{text-align:right;padding:6px 10px;border-top:1px solid var(--dbd-line);white-space:nowrap}
.dbd .pr-t th:nth-child(2),.dbd .pr-t td:nth-child(2){text-align:left}
.dbd .pr-t td:nth-child(2) a{font:500 13px var(--dbd-body);color:var(--dbd-ink);text-decoration:none;display:block}
.dbd .pr-t td small{color:var(--dbd-muted)}
.dbd .pr-t td.rk{color:var(--dbd-muted);width:28px}
.dbd .pr-t tr.on td{background:var(--dbd-bg-2)}
.dbd .pr-more{margin:10px var(--dbd-edge) 4px}
.fz-note{max-width:1240px;margin:12px auto;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:14px/1.5 -apple-system,sans-serif;color:#1d2327;box-sizing:border-box}
@container dbd (max-width:640px){.dbd .pr-t .c-h,.dbd .pr-t .c-s{display:none}.dbd .pr-chart{padding:4px 6px 8px}.dbd .pr-svg.wide{display:none}.dbd .pr-svg.narrow{display:block}}
@container dbd (max-width:420px){.dbd .pr-t .c-p{display:none}.dbd .pr-t th,.dbd .pr-t td{padding:6px 6px}}
</style>'''

js = '''<script>(function(){var s=document.getElementById('pr-team'),c=document.getElementById('pr-chart'),m=document.getElementById('pr-more'),K='dbd_pr_team';
function hl(t){c.classList.toggle('hl',!!t);c.querySelectorAll('.pr-d').forEach(function(g){g.classList.toggle('on',g.getAttribute('data-t')===t)});
document.querySelectorAll('.pr-t tbody tr').forEach(function(r){r.classList.toggle('on',!!t&&r.getAttribute('data-t')===t)});try{t?localStorage.setItem(K,t):localStorage.removeItem(K)}catch(e){}}
s.addEventListener('change',function(){hl(s.value)});try{var v=localStorage.getItem(K);if(v){s.value=v;hl(v)}}catch(e){}
m.addEventListener('click',function(){document.querySelectorAll('.pr-t tbody tr.pr-x').forEach(function(r){r.classList.remove('pr-x')});m.remove()});})();</script>'''


def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    x = re.sub(r'<script[^>]*(googlesitekit)[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer|_googlesitekit)(?:(?!</script>).)*</script>', '', x, flags=re.S)


H = open('_leaders_def.html').read()
a = H.index('<div class="ld-grid">')
out = H[:a] + panel + H[a:]
out = no_tracking(out).replace('</head>', css + '</head>', 1).replace('</body>', js + '</body>', 1)
note = (f'<div class="fz-note"><b>Mock: pass rushers on Leaders → Defenses (SCOPE §79).</b> Real numbers through Week {N} from the site\'s defense files, inside the live page. '
        'The highlight menu, the hover tips and Show all work. The team boards below are the live page as it is.</div>')
out = re.sub(r'(<body[^>]*>)', lambda m: m.group(1) + note, out, count=1)
open('rush.html', 'w').write(out)

# ------------------------------------------------------------ the card and the square
BG, INK, INK2, MUTED, RED, PANEL, FIELD = '#f4f1ea', '#121417', '#3b4255', '#6b6f78', '#cb2026', '#fffdf8', '#2f4a3a'
top6 = {r['id'] for r in sorted(R, key=lambda r: -r['x'])[:3]} | {r['id'] for r in sorted(R, key=lambda r: -r['y'])[:3]}
field = chart(456, 456, (54, 14, 12, 36), top6, 'IBM Plex Mono,monospace', '#f4f1ea', '#a9b8ad', 'rgba(255,255,255,.10)', 5, 11, team_dots=False, hl_attr=False, bg=FIELD, mid='#e7c66b')
field = field.replace('fill="#f4f1ea" stroke="#fff"', 'fill="#f4f1ea" stroke="#2f4a3a"')
tiles = [('Most pressures', str(rp['prs']), f'{rp["n"]}, {rp["t"]}'), ('Most sacks', f'{rs["sk"]:g}', f'{rs["n"]}, {rs["t"]}'), ('Rushers', str(len(R)), 'with a regular role, a game each')]
tile_html = ''.join(f'<div class="t"><small>{e(a)}</small><b>{e(b)}</b><span>{e(c)}</span></div>' for a, b, c in tiles)
card = f'''<div class="card"><div class="hd"><img src="{SITE}/wp-content/plugins/down-by-down/assets/card-logo.png" alt="Down by Down" height="30"><span>{S} SEASON · THROUGH WEEK {N}</span></div>
<div class="bd"><div class="lt"><p class="k">{S} SEASON · THROUGH WEEK {N} · DEFENSE</p><h2>PASS<br><em>RUSHERS</em></h2><p class="say">Pressures a game against sacks a game, every rusher with a regular role.</p><div class="tiles">{tile_html}</div></div>
<div class="fd">{field}</div></div><div class="ft"><b>downbydown.com</b><span>Data: nflverse, PFR</span></div></div>'''
short = f'Pass rushers through Week {N}: {rp["n"]} gets there most ({rp["x"]:.1f} pressures a game) and {rs["n"]} finishes most ({rs["sk"]:g} sacks). All {len(R)} on one chart.'
title = f'Every NFL pass rusher through Week {N}, pressures a game against sacks a game ({len(R)} rushers with a regular role)'
page = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock v52: pass rushers card and square</title>
<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">
<style>
body{{margin:0;background:#e9e6df;color:{INK};font:15px/1.5 "IBM Plex Sans",sans-serif}} main{{max-width:1240px;margin:0 auto;padding:24px 16px 48px}}
h1{{font:600 20px "IBM Plex Sans";margin:0 0 4px}} .lede{{margin:0 0 20px;color:{INK2}}} h3{{font:600 13px "IBM Plex Mono";text-transform:uppercase;letter-spacing:.06em;color:{MUTED};margin:28px 0 10px}}
.wrap{{width:100%;overflow:hidden}} .scale{{transform-origin:0 0}}
.card{{width:1200px;height:630px;background:{BG};position:relative;box-sizing:border-box;font-family:"IBM Plex Sans"}}
.hd{{height:66px;display:flex;justify-content:space-between;align-items:center;padding:0 44px;border-bottom:6px solid {RED}}} .hd span{{font:600 15px "Barlow Condensed";letter-spacing:.2em;color:{INK2}}}
.bd{{display:grid;grid-template-columns:600px 456px;gap:56px;padding:34px 44px 0}}
.k{{font:600 14px "Barlow Condensed";letter-spacing:.2em;color:{RED};margin:12px 0 10px}} .lt h2{{font:800 74px/0.95 Tourney;margin:0;letter-spacing:.01em}} .lt h2 em{{font-style:normal;color:{RED}}}
.say{{font:400 19px/1.45 "IBM Plex Sans";color:{INK2};margin:18px 0 0;max-width:560px}}
.tiles{{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:36px}} .t{{background:{PANEL};border:1px solid #dcd7cc;padding:14px 14px 12px;height:118px;box-sizing:border-box}}
.t small{{display:block;font:600 11px "Barlow Condensed";letter-spacing:.14em;text-transform:uppercase;color:{INK2}}} .t b{{display:block;font:800 32px Tourney;margin:6px 0 4px}} .t span{{font:500 12px/1.3 "IBM Plex Mono";color:{MUTED}}}
.fd{{width:456px;height:456px;margin-top:-4px}} .fd svg{{display:block;width:456px;height:456px}}
.ft{{position:absolute;left:0;right:0;bottom:0;height:44px;border-top:1px solid #dcd7cc;display:flex;justify-content:space-between;align-items:center;padding:0 44px;font:600 13px "IBM Plex Mono"}} .ft span{{font-weight:500;color:{MUTED}}}
.sq{{width:1080px;height:1080px;background:{BG};position:relative}} .sq .hl{{position:absolute;left:60px;width:960px;bottom:calc(1080px - 404px);font:700 56px/1.02 "Barlow Condensed";color:{INK};margin:0}}
.sq .in{{position:absolute;left:58px;top:440px;width:964px;height:508px;background:#dcd7cc}} .sq .in .scale{{position:absolute;left:2px;top:2px;transform:scale(.8)}}
.sq .bar{{position:absolute;left:60px;right:60px;top:976px;height:6px;background:{RED}}} .sq .f{{position:absolute;left:60px;right:60px;top:1004px;display:flex;justify-content:space-between;font:600 26px "IBM Plex Mono"}} .sq .f span{{font-weight:500;color:{MUTED}}}
.copy{{background:#fff;border:1px solid #dcd7cc;padding:14px 16px;max-width:900px}} .copy p{{margin:0 0 10px}} .copy b{{font-weight:600}}
.fz-note{{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:14px/1.5 -apple-system,sans-serif}}
</style></head><body><main>
<div class="fz-note"><b>Mock: the pass rushers card and Instagram square (SCOPE §79).</b> Drawn here in HTML at the real sizes; the plugin draws them with GD like every other card. Real numbers through Week {N}.</div>
<h1>Pass rushers: the card, the square and the post</h1><p class="lede">Shown scaled to fit; both are full size underneath.</p>
<h3>Share card · 1200 × 630</h3><div class="wrap" data-w="1200" data-h="630"><div class="scale">{card}</div></div>
<h3>Instagram square · 1080 × 1080</h3><div class="wrap" data-w="1080" data-h="1080"><div class="scale"><div class="sq"><p class="hl">{e(short)}</p><div class="in"><div class="scale">{card}</div></div><div class="bar"></div><div class="f"><b>downbydown.com</b><span>Link in bio</span></div></div></div></div>
<h3>The Share pack item</h3><div class="copy"><p><b>Reddit title:</b> {e(title)}</p><p><b>Short version</b> (X, Bluesky, Facebook, Instagram, the square's headline): {e(short)}</p><p><b>First comment:</b> Every rusher, with a team highlight and the full list: {SITE}/leaders/def/#ld-rush</p><p><b>Fits:</b> r/nfl, and each highlighted team's subreddit when one of its rushers leads a board.</p></div>
</main><script>function fit(){{document.querySelectorAll('.wrap').forEach(function(w){{var W=+w.dataset.w,H=+w.dataset.h,s=Math.min(1,w.clientWidth/W);w.firstElementChild.style.transform='scale('+s+')';w.style.height=(H*s)+'px'}})}}addEventListener('resize',fit);fit();</script></body></html>'''
open('card.html', 'w').write(page)
open('index.html', 'w').write(f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock v52: pass rushers</title>
<style>body{{margin:0;background:#f6f7f7;color:#1d2327;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}}main{{max-width:640px;margin:0 auto;padding:32px 16px}}a{{color:#2271b1}}</style></head>
<body><main><h1 style="font-size:22px;font-weight:600">Mock v52: pass rushers</h1><p>SCOPE §79. Real numbers through Week {N}: {len(R)} rushers.</p>
<p><a href="rush.html">Leaders → Defenses with the Pass rushers band</a></p><p><a href="card.html">The share card, the Instagram square and the post</a></p></main></body></html>''')
print('ok', len(R), short)
