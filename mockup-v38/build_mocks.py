"""Mock: a season Draft board and a season Combine board (Gene, 2026-11-15: "explore what a season draft board/page and a season combine
board/page could look like ... good traffic pages during the offseason"). Run from mockup-v38/ with draft.csv and combine.csv (nflverse-data
releases draft_picks and combine) and ../mockup-v37/_p-jj.html as the page shell. Builds draft-2026.html and combine-2026.html: static
pages with their data inline and a little inline script for the filters and sorting, so they open straight from disk."""
import csv, json, html, re, bisect, collections

YEAR = '2026'
D_ALL = list(csv.DictReader(open('draft.csv'))); C_ALL = list(csv.DictReader(open('combine.csv')))
T = json.load(open('../data/teams.json')); T = T.get('teams', T)
PFR2 = {'GNB': 'GB', 'KAN': 'KC', 'LVR': 'LV', 'NOR': 'NO', 'NWE': 'NE', 'SFO': 'SF', 'TAM': 'TB', 'LAR': 'LA', 'OAK': 'LV', 'SDG': 'LAC', 'STL': 'LA'}
tm = lambda a: PFR2.get(a, a)
SHELL = open('../mockup-v37/_p-jj.html').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)
esc = html.escape
def logo(a, n=18):
    t = T.get(a, {}); u = t.get('logo') or ''
    return f'<img class="tl" src="{esc(u)}" width="{n}" height="{n}" alt="" loading="lazy" referrerpolicy="no-referrer">' if u else ''
ordn = lambda n: f'{n}{"th" if 10 <= n % 100 <= 20 else {1: "st", 2: "nd", 3: "rd"}.get(n % 10, "th")}'

# ---- combine pools (every player at the position since 2000), the same rules as the player page (SCOPE §62)
def inch(h):
    try: f, i = h.split('-'); return int(f) * 12 + int(i)
    except Exception: return None
GRP = lambda p: {'FB': 'RB', 'HB': 'RB', 'SAF': 'S', 'OLB': 'LB', 'ILB': 'LB', 'EDGE': 'DE'}.get(p, p)
DR = ['forty', 'vertical', 'broad_jump', 'bench', 'cone', 'shuttle']; LOW = {'forty', 'cone', 'shuttle'}
def v(x, k):
    if k == 'ht': return inch(x['ht'])
    try: return float(x[k])
    except Exception: return None
POOL = collections.defaultdict(list)
for x in C_ALL:
    for k in ['ht', 'wt'] + DR:
        y = v(x, k)
        if y is not None: POOL[(GRP(x['pos']), k)].append(y)
for k in POOL: POOL[k].sort()
def pct(g, k, y):
    p = POOL.get((g, k)) or []
    if not p or y is None: return None
    b = len(p) - bisect.bisect_right(p, y) if k in LOW else bisect.bisect_left(p, y)
    return round(100 * b / len(p))
def score(x):
    g = GRP(x['pos']); ps = [pct(g, k, v(x, k)) for k in DR if v(x, k) is not None]
    return round(sum(ps) / len(ps)) if len(ps) >= 2 else None
CB = {x['pfr_id']: x for x in C_ALL if x['season'] == YEAR and x['pfr_id']}

# ---- the draft board rows
D = sorted([x for x in D_ALL if x['season'] == YEAR], key=lambda x: int(x['pick']))
def num(s):
    try: return float(s)
    except Exception: return 0
def so_far(x):
    g = int(num(x['games'])); cat = x['category']
    if not g: return 'hasn\'t played', 0
    if cat == 'QB': s = f"{int(num(x['pass_yards']))} pass yds, {int(num(x['pass_tds']))} TD"; k = num(x['pass_yards'])
    elif cat == 'RB': s = f"{int(num(x['rush_yards']))} rush yds" + (f", {int(num(x['rec_yards']))} rec" if num(x['rec_yards']) else ''); k = num(x['rush_yards']) + num(x['rec_yards'])
    elif cat in ('WR', 'TE'): s = f"{int(num(x['receptions']))} rec, {int(num(x['rec_yards']))} yds"; k = num(x['rec_yards'])
    elif x['side'] == 'D': s = f"{int(num(x['def_solo_tackles']))} solo tkl" + (f", {num(x['def_sacks']):g} sk" if num(x['def_sacks']) else '') + (f", {int(num(x['def_ints']))} INT" if num(x['def_ints']) else ''); k = num(x['def_solo_tackles']) + 6 * num(x['def_sacks']) + 10 * num(x['def_ints'])
    else: s = ''; k = 0
    return f"{g} g" + (f" · {s}" if s else ''), k
DPOS = lambda p: {'OLB': 'LB', 'ILB': 'LB', 'OG': 'G', 'DL': 'DT', 'NT': 'DT', 'DB': 'S', 'FB': 'RB', 'EDGE': 'DE', 'SAF': 'S', 'OL': 'G'}.get(p, p)
rows = []
for x in D:
    cb = CB.get(x['pfr_player_id']); sc = score(cb) if cb else None; line, k = so_far(x)
    rows.append(dict(p=int(x['pick']), r=int(x['round']), t=tm(x['team']), n=x['pfr_player_name'], pos=DPOS(x['position']), sc_=x['college'], cs=sc, so=line, k=k, g=int(num(x['games']))))

def shell(title, body, css, js):
    i = SHELL.find('<main'); j = SHELL.find('</main>') + len('</main>')
    out = SHELL[:i] + '<main class="dbd-content dbd-boards">' + body + '</main>' + SHELL[j:]
    out = re.sub(r'<title>[^<]*</title>', f'<title>{esc(title)} | Down by Down</title>', out, count=1)
    out = out.replace('</head>', '<style>.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}' + css + '</style></head>', 1)
    out = out.replace('</body>', '<script>' + js + '</script></body>', 1)
    out = re.sub(r'<b>Justin Jefferson</b></div>', f'<b>{esc(title)}</b></div>', out, count=1)
    return no_tracking(out)

CSS = '''
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}
.dbd .bd-head{display:flex;align-items:end;justify-content:space-between;gap:18px;flex-wrap:wrap;padding:10px 0 4px}
.dbd .bd-head h1{margin:0;font:800 clamp(32px,4.4vw,56px)/1 var(--dbd-hero);text-transform:uppercase}
.dbd .bd-head h1 em{font-style:normal;color:var(--dbd-red)}
.dbd .bd-head .dbd-sub{margin:12px 0 0;max-width:68ch}
.dbd .bd-year{display:flex;align-items:center;gap:8px;font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted)}
.dbd .bd-year select{height:34px;padding:0 10px;border:1px solid var(--dbd-line-2);border-radius:var(--dbd-r);background:var(--dbd-panel);font:600 14px var(--dbd-body)}
.dbd .bd-say{margin:6px 0 0;font-size:15px;line-height:1.6;color:var(--dbd-ink-2);max-width:90ch}
.dbd .bd-say b{color:var(--dbd-ink)}
.dbd .bd-filters .fr{display:grid;grid-template-columns:86px minmax(0,1fr);gap:14px;align-items:center;padding:10px 14px;border-top:1px solid var(--dbd-line)}
.dbd .bd-filters>.dbd-ph+.fr{border-top:0}
.dbd .bd-filters .fl{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted)}
.dbd .bd-chips{display:flex;flex-wrap:wrap;gap:6px}
.dbd .bd-chip{all:unset;cursor:pointer;display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 11px;border:1px solid var(--dbd-line);border-radius:999px;background:var(--dbd-panel);font:600 13px/1 var(--dbd-body);color:var(--dbd-ink);white-space:nowrap}
.dbd .bd-chip small{font:500 11px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .bd-chip[aria-pressed="true"]{background:var(--dbd-ink);border-color:var(--dbd-ink);color:#fff}.dbd .bd-chip[aria-pressed="true"] small{color:rgba(255,255,255,.7)}
.dbd .bd-filters select{height:28px;border:1px solid var(--dbd-line-2);border-radius:var(--dbd-r);background:var(--dbd-panel);font:500 13px var(--dbd-body);padding:0 8px}
.dbd .bd-grid{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px;align-items:start}
@container dbd (max-width:980px){.dbd .bd-grid{grid-template-columns:minmax(0,1fr)}}
.dbd .bd-side{display:flex;flex-direction:column;gap:18px}
.dbd table.bd-t{width:100%;border-collapse:collapse;font-size:13px}
.dbd .bd-t th{background:var(--dbd-panel);text-align:left;font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.12em;color:var(--dbd-muted);padding:8px 8px;border-bottom:1px solid var(--dbd-line-2);cursor:pointer;white-space:nowrap;z-index:1}
.dbd .bd-t th.n,.dbd .bd-t td.n{text-align:right}
.dbd .bd-t td{padding:7px 8px;border-bottom:1px solid var(--dbd-line);vertical-align:middle}
.dbd .bd-t td.pk{font:700 14px var(--dbd-display);color:var(--dbd-ink)}.dbd .bd-t td.pk small{display:block;font:500 10px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .bd-t td.nm b{font-weight:600}.dbd .bd-t td.nm a{color:var(--dbd-ink);text-decoration:underline;text-decoration-color:var(--dbd-line-2);text-underline-offset:3px}
.dbd .bd-t td.tm{white-space:nowrap;font:700 13px var(--dbd-display)}.dbd .tl{vertical-align:-4px;margin-right:6px}
.dbd .bd-t td.so{color:var(--dbd-ink-2);white-space:nowrap}.dbd .bd-t td.so.none{color:var(--dbd-muted);font-style:italic}
.dbd .bd-t tr.rd td{background:var(--dbd-panel-2);font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-ink-2);padding:6px 8px}
.dbd .bd-sc{display:inline-block;min-width:30px;padding:2px 6px;border-radius:4px;font:600 12px var(--dbd-mono);text-align:center}
.dbd .bd-bars{display:flex;flex-direction:column;gap:6px}
.dbd .bd-bar{display:grid;grid-template-columns:110px minmax(0,1fr) 28px;gap:8px;align-items:center;font-size:12px}
.dbd .bd-bar i{display:block;height:8px;border-radius:4px;background:var(--dbd-ink-2)}
.dbd .bd-bar b{font:600 12px var(--dbd-mono);text-align:right}
.dbd .bd-list{margin:0;padding:0;list-style:none;font-size:13px}
.dbd .bd-list li{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;padding:6px 0;border-bottom:1px solid var(--dbd-line)}
.dbd .bd-list li small{display:block;color:var(--dbd-muted);font-size:11px}
.dbd .bd-list li b{font:600 12px var(--dbd-mono);white-space:nowrap}
.dbd .bd-leaders{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}
@container dbd (max-width:900px){.dbd .bd-leaders{grid-template-columns:repeat(2,minmax(0,1fr))}}
@container dbd (max-width:560px){.dbd .bd-leaders{grid-template-columns:minmax(0,1fr)}}
.dbd .bd-lead ol{margin:0;padding:0;list-style:none;counter-reset:l}
.dbd .bd-lead li{display:grid;grid-template-columns:22px minmax(0,1fr) auto;gap:8px;align-items:baseline;padding:6px 0;border-bottom:1px solid var(--dbd-line);font-size:13px}
.dbd .bd-lead li:before{counter-increment:l;content:counter(l);font:700 12px var(--dbd-display);color:var(--dbd-muted)}
.dbd .bd-lead li small{display:block;font-size:11px;color:var(--dbd-muted)}
.dbd .bd-lead li b{font:700 15px var(--dbd-mono);color:var(--dbd-ink)}
.dbd .bd-lead li:first-child b{color:var(--dbd-red)}
.dbd .bd-t td.dr{font:500 12px var(--dbd-mono);text-align:right;white-space:nowrap}
.dbd .bd-tw{overflow-x:auto}
'''
HEAT = lambda p: '' if p is None else f'background:color-mix(in srgb,#1e7f4a {round(max(0, p - 50) * 1.1)}%,color-mix(in srgb,#b4261f {round(max(0, 50 - p) * 0.9)}%,transparent))'
SORTJS = '''
document.querySelectorAll('table.bd-t').forEach(function(t){t.querySelectorAll('th[data-k]').forEach(function(th,ci){th.onclick=function(){var k=th.dataset.k,asc=th.dataset.asc!=='1';t.querySelectorAll('th').forEach(function(x){delete x.dataset.asc});th.dataset.asc=asc?'1':'0';var b=t.tBodies[0],rs=[].slice.call(b.querySelectorAll('tr[data-p]'));b.querySelectorAll('tr.rd').forEach(function(r){r.style.display=k==='p'?'':'none'});rs.sort(function(a,c){var x=a.dataset[k],y=c.dataset[k];var nx=parseFloat(x),ny=parseFloat(y);if(x===''&&y!=='')return 1;if(y===''&&x!=='')return -1;var d=(!isNaN(nx)&&!isNaN(ny))?nx-ny:String(x).localeCompare(String(y));return asc?d:-d});rs.forEach(function(r){b.appendChild(r)});if(k==='p'&&asc){b.querySelectorAll('tr.rd').forEach(function(rd){var first=b.querySelector('tr[data-r="'+rd.dataset.rd+'"]');if(first)b.insertBefore(rd,first)})}}})});
function chips(id,attr){var box=document.getElementById(id);if(!box)return;box.onclick=function(e){var b=e.target.closest('.bd-chip');if(!b)return;box.querySelectorAll('.bd-chip').forEach(function(x){x.setAttribute('aria-pressed',x===b?'true':'false')});box.dataset.v=b.dataset.v;apply()}}
function apply(){var f={};document.querySelectorAll('[data-filter]').forEach(function(el){f[el.dataset.filter]=el.dataset.v||el.value||''});var n=0,all=0;document.querySelectorAll('table.bd-t tbody tr[data-p]').forEach(function(r){all++;var ok=Object.keys(f).every(function(k){return!f[k]||r.dataset[k]===f[k]});r.style.display=ok?'':'none';if(ok)n++});document.querySelectorAll('table.bd-t tbody tr.rd').forEach(function(rd){var any=[].some.call(document.querySelectorAll('table.bd-t tbody tr[data-r="'+rd.dataset.rd+'"]'),function(r){return r.style.display!=='none'});rd.style.display=any?'':'none'});var s=document.getElementById('bd-count');if(s)s.textContent=(n===all?'all ':n+' of ')+all+' '+s.dataset.unit}
document.querySelectorAll('[data-filter]').forEach(function(el){if(el.tagName==='SELECT')el.onchange=apply;else chips(el.id)});apply();
'''

# ================= the Draft board
years = sorted({x['season'] for x in D_ALL}, reverse=True)
pos_c = collections.Counter(r['pos'] for r in rows); sch_c = collections.Counter(r['sc_'] for r in rows); team_c = collections.Counter(r['t'] for r in rows)
first = rows[0]; mostpos = pos_c.most_common(1)[0]; mostsch = sch_c.most_common(1)[0]; mostteam = team_c.most_common(1)[0]
nplayed = sum(1 for r in rows if r['g'])
POS_ORDER = ['QB', 'RB', 'WR', 'TE', 'OT', 'G', 'C', 'OL', 'DE', 'DT', 'LB', 'CB', 'S', 'K', 'P', 'LS']
pchips = '<button class="bd-chip" data-v="" aria-pressed="true">All</button>' + ''.join(f'<button class="bd-chip" data-v="{p}" aria-pressed="false">{p} <small>{pos_c[p]}</small></button>' for p in POS_ORDER if pos_c.get(p)) + ''.join(f'<button class="bd-chip" data-v="{p}" aria-pressed="false">{p} <small>{c}</small></button>' for p, c in pos_c.items() if p not in POS_ORDER)
rchips = '<button class="bd-chip" data-v="" aria-pressed="true">All</button>' + ''.join(f'<button class="bd-chip" data-v="{r}" aria-pressed="false">Round {r}</button>' for r in range(1, 8))
tsel = '<select data-filter="t" aria-label="Team"><option value="">All teams</option>' + ''.join(f'<option value="{a}">{esc(T[a]["name"])} ({team_c[a]})</option>' for a in sorted(T, key=lambda a: T[a]['name']) if team_c.get(a)) + '</select>'
JJ = {'Jeremiyah Love': 'https://downbydown.com/player/jeremiyah-love/', 'Carnell Tate': 'https://downbydown.com/player/carnell-tate/', 'Fernando Mendoza': 'https://downbydown.com/player/fernando-mendoza/'}
trs, cur = '', None
for r in rows:
    if r['r'] != cur:
        cur = r['r']; trs += f'<tr class="rd" data-rd="{cur}"><td colspan="7">Round {cur}</td></tr>'
    nm = f'<a href="{JJ[r["n"]]}">{esc(r["n"])}</a>' if r['n'] in JJ else f'<b>{esc(r["n"])}</b>'
    cs = f'<span class="bd-sc" style="{HEAT(r["cs"])}">{r["cs"]}</span>' if r['cs'] is not None else '<span class="bd-sc">–</span>'
    trs += (f'<tr data-p="{r["p"]}" data-r="{r["r"]}" data-pos="{r["pos"]}" data-t="{r["t"]}" data-nm="{esc(r["n"])}" data-cs="{r["cs"] if r["cs"] is not None else ""}" data-k="{r["k"]}">'
            f'<td class="pk">{r["p"]}<small>Rd {r["r"]}</small></td><td class="tm">{logo(r["t"])}{r["t"]}</td><td class="nm">{nm}</td><td>{r["pos"]}</td><td>{esc(r["sc_"])}</td><td class="n">{cs}</td><td class="so{" none" if not r["g"] else ""}">{esc(r["so"])}</td></tr>')
bars = ''.join(f'<div class="bd-bar"><span>{p}</span><i style="width:{round(100 * c / mostpos[1])}%"></i><b>{c}</b></div>' for p, c in pos_c.most_common(10))
schl = ''.join(f'<li><span>{esc(s)}</span><b>{c}</b></li>' for s, c in sch_c.most_common(8))
def top(cat, key, fmt, n=5):
    pool = [x for x in D if (x['category'] in cat if isinstance(cat, tuple) else x['side'] == cat) and num(x[key]) > 0]
    pool.sort(key=lambda x: -num(x[key]))
    return ''.join(f'<li><span>{esc(x["pfr_player_name"])}<small>{x["position"]} · {tm(x["team"])} · pick {x["pick"]}</small></span><b>{fmt(num(x[key]))}</b></li>' for x in pool[:n])
early = (f'<h4 class="bd-h4">Receiving yards</h4><ul class="bd-list">{top(("WR", "TE", "RB"), "rec_yards", lambda v: f"{int(v)}")}</ul>'
         f'<h4 class="bd-h4">Rushing yards</h4><ul class="bd-list">{top(("RB", "QB"), "rush_yards", lambda v: f"{int(v)}", 3)}</ul>'
         f'<h4 class="bd-h4">Sacks</h4><ul class="bd-list">{top("D", "def_sacks", lambda v: f"{v:g}", 3)}</ul>')
body = f'''<div class="mock-note"><b>The draft board, one page per draft.</b> Every pick of the {YEAR} draft with the team, school, combine score and what he has done so far, filterable by round, position and team and sortable by any column. A year switcher goes back to 1980 (combine scores from 2000). In the offseason the newest draft leads; during the season the "so far" column and Early returns keep it moving. Real {YEAR} data from nflverse.</div>
<div class="bd-head"><div><p class="dbd-kicker">The {YEAR} NFL Draft · {len(rows)} picks · 7 rounds</p><h1>The <em>{YEAR}</em> draft</h1>
<p class="bd-say">The {esc(T[first["t"]]["nick"])} took <b>{esc(first["n"])}</b> first. {mostpos[0]}s went most often (<b>{mostpos[1]}</b>), and <b>{esc(mostsch[0])}</b> sent the most players ({mostsch[1]}). The {esc(T[mostteam[0]]["nick"])} made the most picks ({mostteam[1]}). <b>{nplayed}</b> of the {len(rows)} have played a snap so far.</p></div>
<label class="bd-year">Draft <select>{"".join(f"<option{' selected' if y == YEAR else ''}>{y}</option>" for y in years)}</select></label></div>
<section class="dbd-panel bd-filters"><div class="dbd-ph"><span class="dbd-lab"><b>Filter the picks</b> · <span id="bd-count" data-unit="picks"></span></span></div>
<div class="fr"><span class="fl">Round</span><div class="bd-chips" id="f-r" data-filter="r">{rchips}</div></div>
<div class="fr"><span class="fl">Position</span><div class="bd-chips" id="f-pos" data-filter="pos">{pchips}</div></div>
<div class="fr"><span class="fl">Team</span><div>{tsel}</div></div></section>
<div class="bd-grid"><section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Every pick</b> · click a column to sort</span><span class="dbd-badge">Combine score: 0 to 100 against his position</span></div>
<div class="bd-tw"><table class="bd-t"><thead><tr><th data-k="p">Pick</th><th data-k="t">Team</th><th data-k="nm">Player</th><th data-k="pos">Pos</th><th>School</th><th class="n" data-k="cs">Combine</th><th data-k="k">So far</th></tr></thead><tbody>{trs}</tbody></table></div>
<p class="dbd-note">Picks, schools and each player's stats so far from nflverse's draft data (Pro Football Reference). The combine score averages his percentiles against every player at his position tested since 2000; a dash means he didn't test at the combine. A linked name has a player page.</p></section>
<aside class="bd-side"><section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Early returns</b> · the class so far</span></div><div class="dbd-pb">{early}</div></section>
<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>By position</b></span></div><div class="dbd-pb"><div class="bd-bars">{bars}</div></div></section>
<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>By school</b></span></div><div class="dbd-pb"><ul class="bd-list">{schl}</ul></div></section></aside></div>'''
open('draft-2026.html', 'w').write(shell(f'{YEAR} NFL Draft', body, CSS + '.dbd .bd-h4{margin:10px 0 2px;font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted)}.dbd .bd-h4:first-child{margin-top:0}', SORTJS))

# ================= the Combine board
C = [x for x in C_ALL if x['season'] == YEAR]
DRAFTED = {x['pfr_player_id']: x for x in D_ALL if x['season'] == YEAR and x['pfr_player_id']}
LAB = {'forty': '40-yard dash', 'vertical': 'Vertical', 'broad_jump': 'Broad jump', 'bench': 'Bench press', 'cone': '3-cone', 'shuttle': 'Shuttle'}
FMT = {'forty': lambda y: f'{y:.2f}', 'cone': lambda y: f'{y:.2f}', 'shuttle': lambda y: f'{y:.2f}', 'vertical': lambda y: f'{y:g}"', 'broad_jump': lambda y: f"{int(y) // 12}'{int(y) % 12}\"", 'bench': lambda y: f'{int(y)}'}
ALLT = {k: sorted([v(x, k) for x in C_ALL if v(x, k) is not None]) for k in DR}
leads = ''
for k in DR:
    pool = sorted([x for x in C if v(x, k) is not None], key=lambda x: v(x, k) if k in LOW else -v(x, k))[:5]
    best = v(pool[0], k); allp = ALLT[k]; better = sum(1 for y in allp if (y < best if k in LOW else y > best))
    note = f'best since 2000' if better == 0 else f'{better} better since 2000'
    leads += (f'<section class="dbd-panel bd-lead"><div class="dbd-ph"><span class="dbd-lab"><b>{LAB[k]}</b></span><span class="dbd-badge">{note}</span></div><div class="dbd-pb"><ol>'
              + ''.join(f'<li><span>{esc(x["player_name"])}<small>{x["pos"]} · {esc(x["school"])}</small></span><b>{FMT[k](v(x, k))}</b></li>' for x in pool) + '</ol></div></section>')
cpos = collections.Counter(GRP(x['pos']) for x in C)
pchips2 = '<button class="bd-chip" data-v="" aria-pressed="true">All</button>' + ''.join(f'<button class="bd-chip" data-v="{p}" aria-pressed="false">{p} <small>{c}</small></button>' for p, c in sorted(cpos.items(), key=lambda pc: (POS_ORDER.index(pc[0]) if pc[0] in POS_ORDER else 99, pc[0])))
trs2 = ''
for x in sorted(C, key=lambda x: -(score(x) or -1)):
    g = GRP(x['pos']); sc = score(x); dr = DRAFTED.get(x['pfr_id'])
    cells = ''.join(f'<td class="n" style="{HEAT(pct(g, k, v(x, k)))}" title="{ordn(pct(g, k, v(x, k)))} percentile among {g}s">{FMT[k](v(x, k))}</td>' if v(x, k) is not None else '<td class="n" style="color:var(--dbd-muted)">–</td>' for k in DR)
    dtxt = f'{tm(dr["team"])} · R{dr["round"]} #{dr["pick"]}' if dr else 'Undrafted'
    trs2 += (f'<tr data-p="{sc if sc is not None else ""}" data-pos="{g}" data-nm="{esc(x["player_name"])}" data-cs="{sc if sc is not None else ""}" data-dp="{dr["pick"] if dr else 999}" ' + ' '.join(f'data-{k.replace("_", "")}="{v(x, k) if v(x, k) is not None else ""}"' for k in DR) + '>'
             f'<td class="nm"><b>{esc(x["player_name"])}</b></td><td>{g}</td><td>{esc(x["school"])}</td><td class="n">{x["ht"]}</td><td class="n">{x["wt"]}</td>{cells}'
             f'<td class="n"><span class="bd-sc" style="{HEAT(sc)}">{sc if sc is not None else "–"}</span></td><td class="dr">{dtxt}</td></tr>')
nund = sum(1 for x in C if x['pfr_id'] not in DRAFTED)
body2 = f'''<div class="mock-note"><b>The combine board, one page per year.</b> The fastest, highest and strongest of the {YEAR} combine up top, each list noting how it stacks up since 2000, then every player who tested in a sortable table: each result shaded green to red by his percentile at his position, the combine score, and where he was drafted. Filter by position. Real {YEAR} data from nflverse; it fills in during combine week in late February, two months before the draft.</div>
<div class="bd-head"><div><p class="dbd-kicker">The {YEAR} NFL Combine · {len(C)} players tested</p><h1>The <em>{YEAR}</em> combine</h1>
<p class="bd-say"><b>{esc(sorted([x for x in C if x["forty"]], key=lambda x: float(x["forty"]))[0]["player_name"])}</b> ran the fastest 40 ({sorted([x for x in C if x["forty"]], key=lambda x: float(x["forty"]))[0]["forty"]}). <b>{len(C) - nund}</b> of the {len(C)} who tested were drafted; <b>{nund}</b> weren't. Every result below is shaded by how it compares with every player at the same position since 2000.</p></div>
<label class="bd-year">Combine <select>{"".join(f"<option{' selected' if y == YEAR else ''}>{y}</option>" for y in sorted({x['season'] for x in C_ALL}, reverse=True))}</select></label></div>
<div class="bd-leaders">{leads}</div>
<section class="dbd-panel bd-filters"><div class="dbd-ph"><span class="dbd-lab"><b>Every player</b> · <span id="bd-count" data-unit="players"></span> · click a column to sort</span><span class="dbd-badge">Green beats most at his position, red trails</span></div>
<div class="fr"><span class="fl">Position</span><div class="bd-chips" id="f-pos" data-filter="pos">{pchips2}</div></div>
<div class="bd-tw"><table class="bd-t"><thead><tr><th data-k="nm">Player</th><th data-k="pos">Pos</th><th>School</th><th class="n">Ht</th><th class="n">Wt</th>{"".join(f'<th class="n" data-k="{k.replace("_", "")}">{LAB[k].replace("-yard dash", "").replace(" press", "").replace(" jump", "")}</th>' for k in DR)}<th class="n" data-k="cs">Score</th><th class="n" data-k="dp">Drafted</th></tr></thead><tbody>{trs2}</tbody></table></div>
<p class="dbd-note">Results from nflverse's combine data (Pro Football Reference). Shading compares each result with every player at the same position at the combine since 2000: faster is better on the 40, 3-cone and shuttle, more is better on the rest. The score averages those percentiles over the drills he ran (two at least). Pro day results aren't included.</p></section>'''
open('combine-2026.html', 'w').write(shell(f'{YEAR} NFL Combine', body2, CSS, SORTJS))
print('ok', len(rows), 'picks;', len(C), 'tested')
