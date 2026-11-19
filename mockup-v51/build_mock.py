"""Mock v51 (SCOPE §78): the weekly fantasy page, /fantasy/week/11/. Run from mockup-v51/ after pull.py.
Frame: the live Week 10 in review page (_week10.html, its header, styles and dbd-site.js for the format switch); the content is
replaced with the fantasy page built from _fan.json (repo data through Week 10) and _keys.json (Keys to the game, pulled live).
Static: opens from disk. Writes fantasy.html and index.html."""
import html, json, re

e = lambda s: html.escape(str(s), quote=True)
H = open('_week10.html').read()
D = json.load(open('_fan.json')); K = json.load(open('_keys.json'))['keys']
N, THRU, T = D['next'], D['thru'], D['teams']
SITE = 'https://downbydown.com'
FMT = ('std', 'half', 'ppr')
NICK = lambda t: T.get(t, {}).get('nick', t)
COL = lambda t: T.get(t, {}).get('c1', '#121417')
ORD = lambda n: f'{n}{"th" if 10 <= n % 100 <= 20 else {1: "st", 2: "nd", 3: "rd"}.get(n % 10, "th")}'


def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    x = re.sub(r'<script[^>]*(googlesitekit|week\.js)[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer|_googlesitekit)(?:(?!</script>).)*</script>', '', x, flags=re.S)


def head(p):
    ph = (p.get('photo') or '').replace('f_auto,q_auto/', 'f_auto,q_auto,w_48,h_48,c_fill,g_face/')
    img = f'<img src="{e(ph)}" alt="" width="24" height="24" loading="lazy" referrerpolicy="no-referrer" style="background:{COL(p["team"])}" onerror="this.remove()">' if ph else ''
    return f'<span class="dbd-head" style="width:24px;height:24px;background:{COL(p["team"])};border-radius:5px">{img}</span>'


def fx(vals, fmt=lambda v: v):
    return ''.join(f'<span data-fx="{f}">{e(fmt(v))}</span>' for f, v in zip(FMT, vals))


def spark(s):
    pts = [(i, v) for i, v in enumerate(s) if v is not None]
    if len(pts) < 2: return ''
    top = max(v for _, v in pts) or 1; n = max(len(s) - 1, 1)
    xy = [(2 + 68 * i / n, 20 - 18 * v / top) for i, v in pts]
    line = ' '.join(f'{x:.1f},{y:.1f}' for x, y in xy); lx, ly = xy[-1]
    split = 2 + 68 * (len(s) - 3) / n
    return (f'<svg viewBox="0 0 72 22" aria-hidden="true"><rect x="{split:.1f}" y="0" width="{72 - split:.1f}" height="22" fill="var(--dbd-bg-2)"/>'
            f'<polyline points="{line}" fill="none" stroke="var(--dbd-muted)" stroke-width="1.5"/><circle cx="{lx:.1f}" cy="{ly:.1f}" r="2.5" fill="var(--dbd-ink)"/></svg>')


def mtag(m, pos):
    if not m: return ''
    where = ('vs ' if m['home'] else 'at ') + m['opp']
    tier = ''.join(f'<span data-fx="{f}" class="fz-t {("soft" if r <= 8 else "tough" if r >= 25 else "")}">{ORD(r)}</span>' for f, r in zip(FMT, m['rks']))
    return f'<span class="fz-m">{e(where)} {tier}<i>vs {pos}</i></span>'


def row(r, down=False):
    d = r['delta']; arrow = 'dn' if down else 'up'
    return (f'<a class="dbd-mvr fz-r {arrow}" href="{SITE}/player/{e(r["slug"])}/" style="--dbd-team:{COL(r["team"])}">{head(r)}'
            f'<span class="nm"><b>{e(r["name"])}</b><small>{e(r["team"])} · {e(r["pos"])} · {fx(r["ppg3s"], lambda v: f"{v:.1f} a game")}</small>{mtag(r["m"], r["pos"])}</span>'
            f'{spark(r["spark"])}<span class="ch"><b>{"+" if d > 0 else "−"}{abs(d):.0f} pts</b><small>{r["before"]:.0f}% → {r["now"]:.0f}%</small></span></a>')


what = {'RB': 'carries + targets', 'WR': 'targets', 'TE': 'targets'}
lab = {'RB': 'Running backs', 'WR': 'Wide receivers', 'TE': 'Tight ends'}
mv = D['movers']
top = {p: mv[p]['up'][0] for p in ('RB', 'WR', 'TE')}
risers = ''.join(f'<div class="dbd-mvb"><h4>{lab[p]}<small>share of the team’s {what[p]}</small></h4>{"".join(row(r) for r in mv[p]["up"])}</div>' for p in ('RB', 'WR', 'TE'))
fallers = ''.join(f'<div class="dbd-mvb"><h4>{lab[p]}<small>share of the team’s {what[p]}</small></h4>{"".join(row(r, True) for r in mv[p]["down"])}</div>' for p in ('RB', 'WR', 'TE'))


def rz_table(rows, rec):
    th = '<tr><th>Player</th><th>' + ('Targets' if rec else 'Carries') + '</th>' + ('' if rec else '<th>Targets</th>') + '<th>Inside 5</th><th>TD</th><th class="c-sh">Team share</th><th class="c-nx">Next</th></tr>'
    tr = ''
    for r in rows:
        tr += (f'<tr><td><a href="{SITE}/player/{e(r["slug"])}/">{e(r["name"])}</a><small>{e(r["team"])} · {e(r["pos"])}</small></td>'
               + (f'<td>{r["t"]}</td>' if rec else f'<td>{r["c"]}</td><td>{r["t"]}</td>')
               + f'<td>{r["in5"]}</td><td>{r["td"]}</td><td class="c-sh"><span class="fz-bar"><i style="width:{min(r["share"], 100)}%"></i></span>{r["share"]}%</td><td class="c-nx">{mtag(r["m"], r["pos"])}</td></tr>')
    return f'<table class="fz-rz"><thead>{th}</thead><tbody>{tr}</tbody></table>'


# matchups at a glance: every offense this week against its opponent's rank at a position
def glance(pos):
    blocks = ''
    for i, f in enumerate(FMT):
        sides = []
        for g in D['grid']:
            for t in (g['away'], g['home']):
                o = g['home'] if t == g['away'] else g['away']; sd = g['sides'][t][pos]
                sides.append((sd['rks'][i], -sd['allows'][i], t, o, t == g['home'], sd['allows'][i]))
        sides.sort()
        li = lambda x, cls: f'<li><span class="rk fz-t {cls}">{ORD(x[0])}</span><span class="who"><b>{e(NICK(x[2]))}</b> {"vs" if x[4] else "at"} {e(NICK(x[3]))}<small>{x[5]:.1f} a game</small></span></li>'
        blocks += (f'<div data-fx="{f}"><div class="dbd-mvg up">Softest</div><ol class="fz-l">{"".join(li(x, "soft") for x in sides[:4])}</ol>'
                   f'<div class="dbd-mvg dn">Toughest</div><ol class="fz-l">{"".join(li(x, "tough") for x in sides[-2:][::-1])}</ol></div>')
    return f'<div class="dbd-mvb fz-g"><h4>{pos}<small>defense’s rank in points allowed</small></h4>{blocks}</div>'


edges = ''.join(
    f'<li><a class="fz-eg" href="{e(k["link"])}">{e(NICK(k["g"]["away"]))} at {e(NICK(k["g"]["home"]))}</a>'
    + ''.join(f'<span class="fz-es"><b>{e(NICK(s["t"]))}:</b> {e(s["head"])}. <span>{e(s["say"])}</span></span>' for s in k['sides']) + '</li>' for k in K)

byes = ''.join(
    f'<li><b>{e(NICK(b["t"]))}</b>' + ', '.join(f' <a href="{SITE}/player/{e(p["slug"])}/">{e(p["name"])}</a> <small>{e(p["pos"])} · {fx(p["ppg"], lambda v: f"{v:.1f}")}</small>' for p in b['top']) + '</li>' for b in D['bye'])

bye_line = ', '.join(D['byes'])
CUR = ' aria-current="page"'
weeks = ''.join(f'<a href="#"{CUR if w == N else ""}>Week {w}</a>' for w in range(4, N + 1))
desc = (f'Week {N} fantasy: {top["RB"]["name"]}, {top["WR"]["name"]} and {top["TE"]["name"]} lead the usage risers, plus red-zone work and every matchup.')

content = f'''<div class="dbd-top"><div class="dbd-crumb"><a href="{SITE}/hub/" class="dbd-brand">DOWN X DOWN</a> <span>›</span> <a href="#">Fantasy</a> <span>›</span> <b>Week {N}</b></div><div class="dbd-r"><span class="dbd-upd">Updated Nov 18, 3:53 PM ET</span></div></div>
<main class="dbd-content"><div class="dbd-ttitle"><div class="t-stagger"><p class="dbd-kicker">2026 season · data through Week {THRU} · {len(D['grid'])} games · byes: {e(bye_line)}</p><h1><span>Week {N}</span> fantasy</h1>
<p class="dbd-sub">Whose role is growing, who gets the ball near the goal line, and which defenses give up the most at each position. Every row shows his Week {N} opponent.</p></div>
<div class="dbd-seg t-tabs dbd-fxseg" role="tablist" aria-label="Fantasy scoring"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" type="button" role="tab" data-fxset="std" aria-selected="false">Standard</button><button class="t-tab" type="button" role="tab" data-fxset="half" aria-selected="true">Half PPR</button><button class="t-tab" type="button" role="tab" data-fxset="ppr" aria-selected="false">PPR</button></div></div>
<nav class="dbd-wkbar wk-pick" aria-label="Week"><div class="dbd-wktabs">{weeks}</div></nav>
<nav class="dbd-jump" aria-label="On this page"><a href="#fz-up">Risers</a><a href="#fz-down">Fallers</a><a href="#fz-rz">Red zone</a><a href="#fz-mu">Matchups</a><a href="#fz-edges">Edges</a><a href="#fz-bye">On bye</a></nav>

<section class="dbd-band" id="fz-up"><h2 class="dbd-bandh">Usage risers<span>last three games against his earlier ones</span></h2>
<section class="dbd-panel dbd-mv"><div class="dbd-ph"><span class="dbd-lab"><b>Risers</b> · Weeks 8–10 against Weeks 1–7</span></div>
<p class="dbd-mv-say">The biggest gains in role: <b>{e(top["RB"]["name"])}</b> ({e(NICK(top["RB"]["team"]))}) now gets {top["RB"]["now"]:.0f}% of the carries and targets, up from {top["RB"]["before"]:.0f}%; <b>{e(top["WR"]["name"])}</b> draws {top["WR"]["now"]:.0f}% of the targets, up from {top["WR"]["before"]:.0f}%. The shaded part of each line is the last three games.</p>
<div class="dbd-mv3">{risers}</div></section></section>

<section class="dbd-band" id="fz-down"><h2 class="dbd-bandh">Fallers<span>roles that shrank, among players who had one</span></h2>
<section class="dbd-panel dbd-mv"><div class="dbd-mv3">{fallers}</div></section></section>

<section class="dbd-band" id="fz-rz"><h2 class="dbd-bandh">Red zone<span>inside the 20, last three games</span></h2>
<div class="fz-2"><section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Backs</b> · carries and targets inside the 20</span></div>{rz_table(D['rz_rb'], False)}</section>
<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Receivers and tight ends</b> · targets inside the 20</span></div>{rz_table(D['rz_rec'], True)}</section></div></section>

<section class="dbd-band" id="fz-mu"><h2 class="dbd-bandh">Matchups at a glance<span>who faces the defenses that give up the most</span></h2>
<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Week {N}</b> · rank in fantasy points allowed to the position, 1st = most</span><a class="dbd-badge" href="{SITE}/matchups/week-{N}/">Every game on Matchups</a></div>
<div class="fz-4">{''.join(glance(p) for p in ('QB', 'RB', 'WR', 'TE'))}</div></section></section>

<section class="dbd-band" id="fz-edges"><h2 class="dbd-bandh">Edges<span>each side’s biggest edge, from Keys to the game</span></h2>
<section class="dbd-panel"><ol class="fz-edges">{edges}</ol></section></section>

<section class="dbd-band" id="fz-bye"><h2 class="dbd-bandh">On bye<span>who you’re replacing this week</span></h2>
<section class="dbd-panel"><ul class="fz-bye">{byes}</ul></section></section>
</main>'''

css = '''<style id="fz-mock">
.dbd .dbd-ttitle .dbd-fxseg{align-self:end}
.dbd .fz-r{grid-template-columns:24px minmax(0,1fr) 72px auto;padding:6px 10px}
.dbd .fz-r .nm small span{white-space:nowrap}
.dbd .fz-m{display:block;margin-top:2px;font:500 11px var(--dbd-mono);color:var(--dbd-ink-2)}
.dbd .fz-m i{font-style:normal;color:var(--dbd-muted);margin-left:4px}
.dbd .fz-t{font:600 11px var(--dbd-mono);padding:0 4px;border-radius:3px;border:1px solid var(--dbd-line-2)}
.dbd .fz-t.soft{background:#e3f1e8;border-color:#bcdcc8;color:var(--dbd-good)}
.dbd .fz-t.tough{background:#f6e2e0;border-color:#e6c0bc;color:var(--dbd-bad)}
.dbd .fz-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.dbd .fz-4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;padding:8px 14px 14px}
.dbd .fz-rz{width:100%;border-collapse:collapse;font:500 12px var(--dbd-mono)}
.dbd .fz-rz th{font:600 10px var(--dbd-mono);text-transform:uppercase;letter-spacing:.06em;color:var(--dbd-muted);text-align:right;padding:6px 10px;border-bottom:1px solid var(--dbd-line)}
.dbd .fz-rz th:first-child,.dbd .fz-rz td:first-child{text-align:left}
.dbd .fz-rz td{text-align:right;padding:6px 10px;border-top:1px solid var(--dbd-line);white-space:nowrap}
.dbd .fz-rz td:first-child a{font:500 13px var(--dbd-body);color:var(--dbd-ink);text-decoration:none;display:block}
.dbd .fz-rz td small{color:var(--dbd-muted)}
.dbd .fz-rz .fz-m{margin:0}
.dbd .fz-bar{display:inline-block;width:44px;height:6px;background:var(--dbd-bg-2);margin-right:6px;vertical-align:middle}
.dbd .fz-bar i{display:block;height:100%;background:var(--dbd-ink-2)}
.dbd .fz-l{list-style:none;margin:0;padding:0}
.dbd .fz-l li{display:grid;grid-template-columns:44px minmax(0,1fr);gap:8px;align-items:center;padding:5px 10px;border-top:1px solid var(--dbd-line);font:400 12px var(--dbd-body)}
.dbd .fz-l .rk{text-align:center}.dbd .fz-l small{display:block;font:500 11px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .fz-edges{list-style:none;margin:0;padding:0 var(--dbd-edge)}
.dbd .fz-edges li{display:grid;grid-template-columns:200px 1fr 1fr;gap:6px 18px;padding:9px 0;border-top:1px solid var(--dbd-line);font:400 13px/1.45 var(--dbd-body)}
.dbd .fz-edges li:first-child{border-top:0}
.dbd .fz-eg{font:600 14px var(--dbd-display);letter-spacing:.02em;text-transform:uppercase;color:var(--dbd-ink);text-decoration:none}
.dbd .fz-es span{color:var(--dbd-ink-2)}
.dbd .fz-bye{list-style:none;margin:0;padding:4px var(--dbd-edge) 10px}
.dbd .fz-bye li{padding:7px 0;border-top:1px solid var(--dbd-line);font:400 13px var(--dbd-body)}
.dbd .fz-bye li:first-child{border-top:0}
.dbd .fz-bye li>b{display:inline-block;width:110px;font-weight:600}
.dbd .fz-bye a{color:var(--dbd-ink)}.dbd .fz-bye small{font:500 11px var(--dbd-mono);color:var(--dbd-muted)}
.fz-note{max-width:1240px;margin:12px auto;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:14px/1.5 -apple-system,sans-serif;color:#1d2327;box-sizing:border-box}
@container dbd (max-width:980px){.dbd .fz-4{grid-template-columns:repeat(2,minmax(0,1fr))}.dbd .fz-edges li{grid-template-columns:1fr}}
@container dbd (max-width:760px){.dbd .fz-2{grid-template-columns:minmax(0,1fr)}.dbd .fz-rz .c-nx{display:none}}
@container dbd (max-width:520px){.dbd .fz-4{grid-template-columns:minmax(0,1fr)}.dbd .fz-rz{font-size:11px}.dbd .fz-rz .c-sh,.dbd .fz-rz .c-nx{display:none}.dbd .fz-rz th,.dbd .fz-rz td{padding:6px 6px}}
</style>'''

note = (f'<div class="fz-note"><b>Mock: the weekly fantasy page, /fantasy/week/{N}/ (SCOPE §78).</b> Real numbers through Week {THRU}, inside the live site’s frame. '
        'The format switch works (Standard / Half PPR / PPR change the points a game and the opponent ranks on each row). Links go to the live site; the week tabs and the Fantasy crumb are placeholders.</div>')

a = H.index('<div class="dbd-top">'); b = H.index('</main>', a) + len('</main>')
out = no_tracking(H[:a] + content + H[b:])
out = out.replace('</head>', css + '</head>', 1)
out = re.sub(r'<title>.*?</title>', f'<title>Week {N} fantasy football: usage risers, red zone and matchups | Down by Down</title>', out, count=1, flags=re.S)
out = re.sub(r'(<body[^>]*>)', r'\1' + note.replace('\\', '\\\\'), out, count=1)
out = out.replace('dbd-view-week', 'dbd-view-fantasy')
open('fantasy.html', 'w').write(out)
open('index.html', 'w').write(f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mock v51: the weekly fantasy page</title>
<style>body{{margin:0;background:#f6f7f7;color:#1d2327;font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}}main{{max-width:640px;margin:0 auto;padding:32px 16px}}a{{color:#2271b1}}</style></head>
<body><main><h1 style="font-size:22px;font-weight:600">Mock v51: the weekly fantasy page</h1><p>SCOPE §78. /fantasy/week/{N}/ with real numbers through Week {THRU}.</p><p>Meta description: {e(desc)}</p><p><a href="fantasy.html">Open the fantasy page mock</a></p></main></body></html>''')
print('ok', len(out), desc)
