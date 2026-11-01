"""Mock for SCOPE §30 (win probability on the game page).
   python3 -I mock.py <pbp.csv.gz> <teams.json> <out dir>
Computes each game's win probability the way the build will (nflverse's spread-adjusted home WP per play),
then inserts the "How it played out" panel into the live game page saved in src/."""
import csv, gzip, json, sys, re, html
pbp, teams_path, OUT = sys.argv[1:4]
GAMES = {'2026_02_IND_KC': 'wp-ind-kc.html', '2026_04_LA_PHI': 'wp-la-phi.html'}
T = json.load(open(teams_path)); esc = html.escape
F = lambda x: float(x) if x not in ('', 'NA', None) else None
rows = {g: [] for g in GAMES}
for r in csv.DictReader(gzip.open(pbp, 'rt')):
    if r['game_id'] in rows: rows[r['game_id']].append(r)

def clean(d):
    d = re.sub(r'^\(\s*\d*:\d+\)\s*', '', d)
    d = re.sub(r'\((?:No Huddle, )?Shotgun\)\s*|\(No Huddle\)\s*', '', d)
    d = re.sub(r'[^.]*reported in as eligible\.\s*', '', d)
    d = re.sub(r',\s*Center-[\w.\'-]+(?:,\s*Holder-[\w.\'-]+)?', '', d)   # snapper and holder credits
    d = re.sub(r'\b\d{1,2}-(?=[A-Z])', '', d)                                  # jersey numbers: 4-J.Elliott -> J.Elliott
    d = re.sub(r'\s+', ' ', d).strip()
    return d if len(d) <= 150 else d[:147].rsplit(' ', 1)[0] + '…'

def calc(gid):
    ps = rows[gid]; home, away = ps[0]['home_team'], ps[0]['away_team']
    s = []; meta = []; sc = (0, 0)  # nflverse's running totals include the play's own points; win probability is before the snap, so each point carries the score going in
    for r in ps:
        hw = F(r['vegas_home_wp']); gs = F(r['game_seconds_remaining'])
        if hw is not None and gs is not None:
            el = 3600 - gs if r['qtr'] != '5' else 3600 + (600 - gs)
            s.append([round(el), int(round(hw * 1000)), sc[0], sc[1]]); meta.append(r)
        sc = (int(float(r['total_home_score'])), int(float(r['total_away_score'])))
    last = ps[-1]; hs, as_ = int(float(last['total_home_score'])), int(float(last['total_away_score']))
    fin = 1000 if hs > as_ else (0 if hs < as_ else 500)
    ot = any(r['qtr'] == '5' for r in ps)
    # nflverse's model does not hold together under the current overtime rules (both teams get the ball): win probability covers regulation only,
    # a game tied after sixty minutes ends regulation at 50%, and overtime shows its scoring plays
    otsc = []
    if ot:
        prev = None
        for r in ps:
            if r['qtr'] != '5': continue
            cur = (int(float(r['total_home_score'])), int(float(r['total_away_score'])))
            if prev is not None and cur != prev:
                gs = F(r['game_seconds_remaining']) or 0; otsc.append(dict(el=round(3600 + 600 - gs), pos=r['posteam'], desc=clean(r['desc']), hs=cur[0], as_=cur[1]))
            prev = cur
        keep = [k for k, x in enumerate(s) if x[0] < 3600]; s = [s[k] for k in keep]; meta = [meta[k] for k in keep]
    rfin = 500 if ot else fin
    reg4 = [r for r in ps if r['qtr'] in ('1', '2', '3', '4')][-1]; regsc = (int(float(reg4['total_home_score'])), int(float(reg4['total_away_score'])))
    after = [x[1] for x in s[1:]] + [rfin]
    plays = []
    for i, (x, r, a) in enumerate(zip(s, meta, after)):
        if not r['desc'] or r['play_type'] in ('', 'NA') and 'END' in r['desc'].upper(): continue
        plays.append(dict(i=i, el=x[0], q=r['qtr'], clk=r['time'], pos=r['posteam'] if r['posteam'] not in ('', 'NA') else None, desc=clean(r['desc']), b=x[1], a=a, d=a - x[1]))
    end = max([3600] + [o['el'] for o in otsc]) + (60 if ot else 0)
    win = home if fin == 1000 else (away if fin == 0 else None)
    wv = (lambda v: v) if win == home else (lambda v: 1000 - v)
    top = sorted(plays, key=lambda p: -abs(p['d']))[:5]
    tp = None; lo = None
    if win:
        for p in plays:
            if wv(p['b']) < 500 <= wv(p['a']) and wv(p['a']) > 500: tp = p
        if ot: tp = None
        lo = min(range(len(s)), key=lambda k: wv(s[k][1]))
    seq = [x[1] for x in s] + [rfin]
    flips = sum(1 for u, v in zip(seq, seq[1:]) if (u - 500) * (v - 500) < 0)
    return dict(id=gid, home=home, away=away, hs=hs, as_=as_, pre=s[0][1], spread=F(ps[0]['spread_line']), s=s, fin=fin, rfin=rfin, regsc=regsc, end=end, ot=ot, otsc=otsc, top=top, tp=tp, lo=lo, win=win, flips=flips, week=int(ps[0]['week']))

def clock(el, end):
    if el >= 3600 and end > 3600: r = 600 - (el - 3600); return f"OT {int(r // 60)}:{int(r % 60):02d}"
    q = min(4, int(el // 900) + 1); r = 900 * q - el; return f"Q{q} {int(r // 60)}:{int(r % 60):02d}"
def nick(a): return T[a]['nick']
def pct(v): return f"{round(v / 10)}%"

def panel(G):
    H, A, S, end = G['home'], G['away'], G['s'], G['end']
    ch, ca = T[H]['c1'], T[A]['c1']
    X = lambda el: el / end * 100; Y = lambda wp: (1000 - wp) / 1000 * 100   # top is the home side
    pts = [(X(x[0]), Y(x[1])) for x in S] + [(X(3600) if G['ot'] else 100, Y(G['rfin']))]
    line = ' '.join(f"{x:.2f},{y:.2f}" for x, y in pts)
    area = f"0,50 {line} {pts[-1][0]:.2f},50"
    svg = (f'<svg class="plot" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><defs><clipPath id="wpT-{G["id"]}"><rect x="0" y="0" width="100" height="50"/></clipPath><clipPath id="wpB-{G["id"]}"><rect x="0" y="50" width="100" height="50"/></clipPath></defs>'
           f'<polygon points="{area}" fill="{ch}" fill-opacity=".5" clip-path="url(#wpT-{G["id"]})"/><polygon points="{area}" fill="{ca}" fill-opacity=".5" clip-path="url(#wpB-{G["id"]})"/>'
           f'<polyline points="{line}" fill="none" stroke="var(--dbd-ink)" stroke-width="1.6" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>')
    grid = ''.join(f'<i class="q" style="left:{X(e):.2f}%"></i>' for e in (900, 1800, 2700, 3600) if e < end)
    qlab = ''.join(f'<span style="left:{X(a + 450 if a < 3600 else a + (end - 3600) / 2):.2f}%">{l}</span>' for a, l in ((0, 'Q1'), (900, 'Q2'), (1800, 'Q3'), (2700, 'Q4')) ) + (f'<span style="left:{X(3600 + (end - 3600) / 2):.2f}%">OT</span>' if G['ot'] else '')
    # score changes
    sc = ''; prev = (0, 0)
    for x in S:
        if (x[2], x[3]) != prev:
            prev = (x[2], x[3]); sc += f'<i class="sc" style="left:{X(x[0]):.2f}%;top:{Y(x[1]):.2f}%" data-tip="{esc(f"<b>{clock(x[0], end)}</b> {A} {x[3]} · {H} {x[2]}")}" tabindex="0"></i>'
    # numbered swings, the turning point, the low point
    mk = ''
    for n, p in enumerate(G['top'], 1):
        ben = H if p['d'] > 0 else A; v = p['a'] if ben == H else 1000 - p['a']; b = p['b'] if ben == H else 1000 - p['b']
        tip = f"<b>{n}. {clock(p['el'], end)}</b> {esc(p['desc'])}<br>{ben} {pct(b)} → {pct(v)}"
        mk += f'<b class="sw" style="left:{X(S[p["i"]][0]):.2f}%;top:{Y(p["a"]):.2f}%" data-tip="{esc(tip)}" tabindex="0">{n}</b>'
    if G['tp']:
        p = G['tp']; ttip = esc('<b>Turning point · ' + clock(p['el'], end) + '</b> ' + esc(p['desc']))
        mk += f'<em class="tp" style="left:{X(S[p["i"]][0]):.2f}%;top:{Y(p["a"]):.2f}%" data-tip="{ttip}" tabindex="0"><span>Turning point</span></em>'
    if G['lo'] is not None:
        x = S[G['lo']]; W = G['win']; v = x[1] if W == H else 1000 - x[1]
        mk += f'<em class="lo{" dn" if W == H else ""}{" lft" if X(x[0]) > 70 else ""}" style="left:{X(x[0]):.2f}%;top:{Y(x[1]):.2f}%"><span>{W} low {pct(v)}</span></em>'  # near the right edge the label reads leftward
    if G['ot']:
        mk += f'<div class="otb" style="left:{X(3600):.2f}%"><span>Overtime</span></div>'
        for o in G['otsc']:
            ttip = esc('<b>' + clock(o['el'], end) + '</b> ' + esc(o['desc']) + '<br>' + A + ' ' + str(o['as_']) + ' · ' + H + ' ' + str(o['hs']))
            mk += f'<i class="ots" style="left:{X(o["el"]):.2f}%" data-tip="{ttip}" tabindex="0">{esc(o["pos"] or "")}</i>'
        mk += f'<i class="fin{" top" if G["fin"] == 1000 else ""}" style="left:100%"></i>'
    side = f'<div class="side"><span class="h">{esc(H)} wins</span><span class="m">50%</span><span class="a">{esc(A)} wins</span></div>'
    data = esc(json.dumps(dict(e=end, h=H, a=A, s=S)))
    chart = f'<div class="dbd-wpc" data-wp="{data}">{side}<div class="area">{svg}<i class="mid"></i><i class="g25"></i><i class="g75"></i>{grid}{sc}{mk}<i class="cur" hidden></i><div class="read" hidden></div></div><div class="ql">{qlab}</div></div>'
    # chips
    W = G['win']; Lz = (A if W == H else H) if W else None
    fav = H if G['pre'] >= 500 else A; fv = G['pre'] if fav == H else 1000 - G['pre']
    lo = S[G['lo']] if W else None; lov = (lo[1] if W == H else 1000 - lo[1]) if W else None
    big = G['top'][0]; bben = H if big['d'] > 0 else A
    chips = [('At kickoff', f'{fav} {pct(fv)}', f'{fav} by {abs(G["spread"]):g} on the line' if G['spread'] else 'pick\'em'),
             (f'{W} low point' if W else 'Tied', pct(lov) if W else f"{G['hs']}–{G['as_']}", (clock(lo[0], end) + f' · down {max(lo[2], lo[3])}\u2060–\u2060{min(lo[2], lo[3])}' if lo and lo[2] != lo[3] else (clock(lo[0], end) + ' · tied' if lo else 'no winner'))),
             ('Swings past 50%', str(G['flips']), 'times the favorite changed'),
             ('Biggest swing', f'{round(abs(big["d"]) / 10)} pts', f'{bben} · {clock(big["el"], end)}')]
    chiph = ''.join(f'<div class="c"><small>{esc(a)}</small><b>{esc(b)}</b><span>{esc(c)}</span></div>' for a, b, c in chips)
    # the read
    say = []
    if W:
        wn = nick(W); score = f"{max(G['hs'], G['as_'])}–{min(G['hs'], G['as_'])}"
        if lov < 250: say.append(f"The {wn} won {score} from as low as <b>{pct(lov)}</b> ({clock(lo[0], end)}{', down ' + str(max(lo[2], lo[3])) + '–' + str(min(lo[2], lo[3])) if lo[2] != lo[3] else ', tied'}).")
        elif lov >= 500: say.append(f"The {wn} won {score} and were favored the whole way, never below <b>{pct(lov)}</b>.")
        else: say.append(f"The {wn} won {score}; their low point{' in regulation' if G['ot'] else ''} was <b>{pct(lov)}</b> at {clock(lo[0], end)}.")
        if fav != W: say.append(f"The {nick(fav)} had been {pct(fv)} favorites at kickoff.")
        if G['tp']:
            p = G['tp']; v0 = p['b'] if W == H else 1000 - p['b']; v1 = p['a'] if W == H else 1000 - p['a']
            say.append(f"The game turned for good at {clock(p['el'], end)}: {esc(p['desc'].rstrip('.'))} ({pct(v0)} → <b>{pct(v1)}</b>).")
        if G['ot']:
            last = G['otsc'][-1] if G['otsc'] else None
            say.append(f"Tied {G['regsc'][0]}–{G['regsc'][1]} after regulation, they won it in overtime" + (f" at {clock(last['el'], end)}: {esc(last['desc'].rstrip('.'))}." if last else '.'))
        if G['tp'] is None or big['i'] != G['tp']['i']:
            say.append(f"The biggest single swing came at {clock(big['el'], end)}, {round(abs(big['d']) / 10)} points for the {nick(bben)}.")
    # the plays
    li = ''
    for n, p in enumerate(G['top'], 1):
        ben = H if p['d'] > 0 else A; b = p['b'] if ben == H else 1000 - p['b']; a = p['a'] if ben == H else 1000 - p['a']
        tpm = ' tp' if G['tp'] and p['i'] == G['tp']['i'] else ''
        li += (f'<li class="{tpm.strip()}"><b class="n">{n}</b><span class="when">{clock(p["el"], end)}</span><span class="who" style="--c:{T[ben]["c1"]}">{esc(ben)}</span>'
               f'<span class="what">{esc(p["desc"])}{"<em>turning point</em>" if tpm else ""}</span><span class="wp">{pct(b)} → <b>{pct(a)}</b></span><span class="bar"><i style="width:{min(100, abs(p["d"]) / 6):.1f}%;background:{T[ben]["c1"]}"></i><small>+{round(abs(p["d"]) / 10)}</small></span></li>')
    if G['tp'] and all(p['i'] != G['tp']['i'] for p in G['top']):
        p = G['tp']; b = p['b'] if W == H else 1000 - p['b']; a = p['a'] if W == H else 1000 - p['a']
        li += (f'<li class="tp"><b class="n">★</b><span class="when">{clock(p["el"], end)}</span><span class="who" style="--c:{T[W]["c1"]}">{esc(W)}</span><span class="what">{esc(p["desc"])}<em>turning point</em></span>'
               f'<span class="wp">{pct(b)} → <b>{pct(a)}</b></span><span class="bar"><i style="width:{min(100, abs(p["d"]) / 6):.1f}%;background:{T[W]["c1"]}"></i><small>+{round(abs(p["d"]) / 10)}</small></span></li>')
    final = f"Final{' · OT' if G['ot'] else ''} · {A} {G['as_']} · {H} {G['hs']}"
    note = ('Win probability is nflverse\'s model, which starts from the betting line and updates after every snap with the score, the clock, the down, distance and field position, and timeouts. A swing is the change in one team\'s chance across a play, in percentage points. '
            'The turning point is the play after which the winner never fell below 50% again. Penalties and special teams count; kneel-downs barely move it.')
    return (f'<section class="dbd-panel dbd-wp mk-new" data-mk="New · SCOPE §30"><div class="dbd-ph"><span class="dbd-lab"><b>How it played out</b> · win probability, snap by snap</span><span class="dbd-badge fin">{esc(final)}</span></div>'
            f'<p class="dbd-wp-say">{" ".join(say)}</p><div class="dbd-wpchips">{chiph}</div>{chart}'
            f'<h4 class="dbd-wph">The plays that swung it</h4><ol class="dbd-wpl">{li}</ol><p class="dbd-note">{note}</p></section>')

SHELL = open('shell.html').read()
for gid, fn in GAMES.items():
    G = calc(gid); root = open(f'src/root-{gid}.html').read()
    k = root.index('<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Takeaways</b>')
    root = root[:k] + panel(G) + root[k:]
    root = root.replace('<b>Takeaways</b> · generated from the numbers', '<b>Takeaways</b> · from the season numbers, not this game alone', 1)
    open(f'{OUT}/{fn}', 'w').write(SHELL.replace('%TITLE%', esc(f"{nick(G['away'])} at {nick(G['home'])} · win probability mock")).replace('%ROOT%', root))
    print(fn, 'win', G['win'], 'low', G['lo'] is not None and G['s'][G['lo']], 'tp', G['tp'] and (G['tp']['q'], G['tp']['clk'], G['tp']['desc'][:70]), 'flips', G['flips'], 'pre', G['pre'])
