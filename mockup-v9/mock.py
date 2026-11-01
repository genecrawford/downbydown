"""Mock for SCOPE §32 (game page: keys to the game, the lineups, the box score; a standalone team players page).
   python3 -I mock.py <depth.csv> <roster.csv> <stats_player_week.csv.gz> <repo data dir> <out dir>
Every number is this season's nflverse data; the pages are the live 1.8.1 pages with the new panels outlined."""
import csv, gzip, json, sys, re, html, glob, os, collections
DEPTH, ROSTER, WK, DATA, OUT = sys.argv[1:6]
esc = html.escape
T = json.load(open(f'{DATA}/teams.json'))
def nick(a): return T[a]['nick']
def ordn(n):
    n = int(n); return f"{n}{'th' if 10 <= n % 100 <= 20 else {1: 'st', 2: 'nd', 3: 'rd'}.get(n % 10, 'th')}"
F = lambda x: float(x) if x not in ('', 'NA', None) else 0.0

# ---- depth charts: each team's latest snapshot, starter per slot (lowest rank in the slot)
rows = list(csv.DictReader(open(DEPTH))); last = {}
for r in rows: last[r['team']] = max(last.get(r['team'], ''), r['dt'])
LU = collections.defaultdict(lambda: {'off': {}, 'def': {}, 'st': {}, 'grp': {}})
for r in rows:
    if r['dt'] != last[r['team']]: continue
    unit = 'st' if 'Special' in r['pos_grp'] else ('def' if r['pos_grp'].startswith('Base') else 'off')
    key = r['pos_abb'] + ('' if r['pos_abb'] != 'WR' else r['pos_slot'])
    cur = LU[r['team']][unit].get(key)
    if cur is None or int(r['pos_rank']) < int(cur['rank']):
        LU[r['team']][unit][key] = dict(name=r['player_name'], id=r['gsis_id'], rank=r['pos_rank'], pos=r['pos_name'])
    LU[r['team']]['grp'][unit] = r['pos_grp']
JERSEY = {}; HEAD = {}
for r in csv.DictReader(open(ROSTER)):
    if r['gsis_id']: JERSEY[r['gsis_id']] = r['jersey_number']; HEAD[r['gsis_id']] = r.get('headshot_url')

# ---- weekly stats: season lines for everyone, and per-game lines
WKR = collections.defaultdict(list)
for r in csv.DictReader(gzip.open(WK, 'rt')):
    if r.get('season_type', 'REG') != 'REG': continue
    WKR[r['player_id']].append(r)
def season(pid, keys): return {k: sum(F(w.get(k)) for w in WKR.get(pid, [])) for k in keys}
DK = ['def_tackles_solo', 'def_tackle_assists', 'def_sacks', 'def_interceptions', 'def_pass_defended', 'def_tackles_for_loss', 'def_qb_hits']
def defline(pid):
    s = season(pid, DK); tk = int(s['def_tackles_solo'] + s['def_tackle_assists'])
    bits = [f"{tk} tackles"] + ([f"{s['def_sacks']:g} sacks"] if s['def_sacks'] else []) + ([f"{int(s['def_interceptions'])} INT"] if s['def_interceptions'] else []) + ([f"{int(s['def_pass_defended'])} PD"] if s['def_pass_defended'] else [])
    return ' · '.join(bits), s
# league ranks for defenders' playmaking
PLAY = {}
for pid, ws in WKR.items():
    s = {k: sum(F(w.get(k)) for w in ws) for k in DK}
    if s['def_tackles_solo'] + s['def_tackle_assists'] > 0: PLAY[pid] = s
def rank_of(pid, k):
    v = PLAY.get(pid, {}).get(k, 0); return 1 + sum(1 for x in PLAY.values() if x[k] > v)

P = {}
for f in glob.glob(f'{DATA}/players/*.json'):
    if os.path.basename(f) in ('index.json', 'league.json', 'movers.json'): continue
    for p in json.load(open(f))['players']: P[p['id']] = dict(p, team_file=os.path.basename(f)[:-5])
def S(p, k): return sum(float(w.get(k) or 0) for w in p['weeks'])
def offline(pid):
    p = P.get(pid)
    if not p: return ''
    if p['pos'] == 'QB': return f"{int(S(p,'completions'))}/{int(S(p,'attempts'))} · {int(S(p,'passing_yards'))} yds · {int(S(p,'passing_tds'))} TD · {int(S(p,'passing_interceptions'))} INT"
    if p['pos'] in ('RB', 'FB'): return f"{int(S(p,'carries'))} car · {int(S(p,'rushing_yards'))} yds · {int(S(p,'rushing_tds') + S(p,'receiving_tds'))} TD · {int(S(p,'receptions'))} rec"
    return f"{int(S(p,'receptions'))} rec · {int(S(p,'receiving_yards'))} yds · {int(S(p,'receiving_tds'))} TD · {int(S(p,'targets'))} tgt"
PURL = lambda pid: f"https://downbydown.com/?player={pid}"

# ---- the lineup graphic: offense at the bottom, defense at the top, as the offense sees the field
OFFXY = {'LT': (36, 60), 'LG': (43, 60), 'C': (50, 60), 'RG': (57, 60), 'RT': (64, 60), 'TE': (71, 61), 'QB': (50, 74), 'RB': (59, 75), 'WR1': (7, 59), 'WR8': (21, 62), 'WR2': (93, 59)}
DEF34 = {'RDE': (42, 45), 'NT': (50, 45), 'LDE': (58, 45), 'WLB': (31, 44), 'SLB': (70, 44), 'RILB': (44, 34), 'LILB': (56, 34), 'RCB': (7, 43), 'LCB': (93, 43), 'NB': (21, 39), 'SS': (62, 22), 'FS': (40, 20)}
DEF43 = {'RDE': (38, 45), 'RDT': (46, 45), 'LDT': (54, 45), 'LDE': (62, 45), 'WLB': (34, 34), 'MLB': (50, 33), 'SLB': (67, 34), 'RCB': (7, 43), 'LCB': (93, 43), 'NB': (21, 39), 'SS': (62, 22), 'FS': (40, 20)}
SKILL = {'QB', 'RB', 'TE', 'WR1', 'WR2', 'WR8'}
def chip(team, key, x, y, who, side, faded=False, raw=False):
    c1 = T[team]['c1']; num = JERSEY.get(who['id'], '') or ''
    if side == 'off':
        line = offline(who['id']); tip = f"<b>{esc(who['name'])}</b> · {esc(who['pos'])}" + (f"<br>{esc(line)}" if line else '')
        link = key in SKILL and who['id'] in P
    else:
        line, _ = defline(who['id']); tip = f"<b>{esc(who['name'])}</b> · {esc(who['pos'])}" + (f"<br>{esc(line)} this season" if line else '')
        link = False
    last = who['name'].split(' ')[-1] if not who['name'].split(' ')[-1] in ('Jr.', 'III', 'II', 'Sr.') else who['name'].split(' ')[-2]
    tag = 'a' if link else 'span'; href = f' href="{PURL(who["id"])}"' if link else ''
    y = y if raw else round(50 + (y - 50) * 1.3, 1)  # spread the formation to fill the board
    stag = ' lo' if key in ('LG', 'RG', 'NT', 'LDT', 'RDT', 'RILB', 'MLB') else ''  # every other interior lineman's name drops a line so neighbors never touch
    return (f'<{tag} class="lu-p {side}{stag}{" fade" if faded else ""}{" sk" if link else ""}" style="left:{x}%;top:{y}%;--c:{c1}"{href} data-tip="{esc(tip)}" aria-label="{esc(who["name"] + ", " + who["pos"])}" tabindex="0">'
            f'<i>{esc(num)}</i><span>{esc(last)}</span></{tag}>')
def lineup(off, dfn, title_note=''):
    o = LU[off]['off']; d = LU[dfn]['def']; scheme = LU[dfn]['grp'].get('def', ''); M = DEF34 if '3-4' in scheme else DEF43
    ch = ''.join(chip(off, k, *OFFXY[k], o[k], 'off') for k in OFFXY if k in o)
    ch += ''.join(chip(dfn, k, *M[k], d[k], 'def', faded=(k == 'NB')) for k in M if k in d)
    # the list beside it: the accessible and phone-friendly version
    def col(team, unit, keys, label):
        u = LU[team][unit]; li = ''
        for k in keys:
            if k not in u: continue
            w = u[k]; line = offline(w['id']) if unit == 'off' else defline(w['id'])[0]
            nm = f'<a href="{PURL(w["id"])}">{esc(w["name"])}</a>' if unit == 'off' and k in SKILL and w['id'] in P else esc(w['name'])
            li += f'<li><b>{esc(re.sub(r"[0-9]", "", k))}</b><span class="n">{nm}<small>#{esc(JERSEY.get(w["id"], ""))}{" · " + esc(line) if line else ""}</small></span></li>'
        return f'<div><h4>{label}</h4><ul class="lu-list">{li}</ul></div>'
    lists = col(off, 'off', ['QB', 'RB', 'WR1', 'WR2', 'WR8', 'TE', 'LT', 'LG', 'C', 'RG', 'RT'], f'{esc(nick(off))} offense · {esc(LU[off]["grp"].get("off", ""))}') + \
            col(dfn, 'def', list(M.keys()), f'{esc(nick(dfn))} defense · {esc(scheme)}')
    return (f'<div class="lu-wrap"><div class="lu-field" aria-label="{esc(nick(off))} offense against the {esc(nick(dfn))} defense, starters from the depth charts">'
            f'<i class="lu-los"></i><span class="lu-tag def">{esc(nick(dfn))} defense · {esc(scheme.replace("Base ", ""))}</span><span class="lu-tag off">{esc(nick(off))} offense · {esc(LU[off]["grp"].get("off", ""))}</span>{ch}</div>'
            f'<div class="lu-lists">{lists}</div></div>')

def mark(team, num, size=30):
    return f'<span class="mk-j" style="--c:{T[team]["c1"]};--c2:{T[team]["c2"]};width:{size}px;height:{size}px">{esc(str(num or ""))}</span>'

# ---- players to watch
def watch(team, opp):
    o = LU[team]['off']; d = LU[team]['def']; cards = []
    recs = sorted([p for p in P.values() if p['team_file'] == team and p['pos'] in ('WR', 'TE')], key=lambda p: -S(p, 'targets'))[:2]
    picks = [('qb', P.get((o.get('QB') or {}).get('id'))), ('rb', P.get((o.get('RB') or {}).get('id')))] + [('rec', p) for p in recs]
    for why, p in picks:
        if not p: continue
        trk = p.get('trk') or {}
        rk = {'qb': ('epadb', 'EPA per dropback'), 'rb': ('epatch', 'EPA per touch'), 'rec': ('epat', 'EPA per target')}[why]
        r = trk.get(rk[0]); g = (p.get('grades') or {}).get('m', {}); v = g.get({'qb': 'epadb', 'rb': 'repc', 'rec': 'epat'}[why])
        sub = f"{rk[1]} {'+' if (v or 0) > 0 else ''}{v:.2f}" + (f" · {'T-' if r[1] else ''}{ordn(r[0])}" if r and r[0] <= 30 else '') if v is not None else ''
        cards.append(f'<a class="pw" href="{PURL(p["id"])}">{mark(team, p.get("num"))}<span><b>{esc(p["name"])}</b><small>{esc(p["pos"])} · {esc(offline(p["id"]))}</small><em class="{"top" if r and r[0] <= 10 else ""}">{esc(sub)}</em></span></a>')
    # the defense's two playmakers
    ds = sorted([w for w in d.values()], key=lambda w: -(PLAY.get(w['id'], {}).get('def_sacks', 0) * 2 + PLAY.get(w['id'], {}).get('def_interceptions', 0) * 3 + PLAY.get(w['id'], {}).get('def_pass_defended', 0) + PLAY.get(w['id'], {}).get('def_tackles_for_loss', 0) * .5))[:2]
    for w in ds:
        line, s = defline(w['id']); db = any(x in w['pos'] for x in ('Corner', 'Safety', 'Nickel'))
        lead = 'def_interceptions' if (db and s['def_interceptions']) or (s['def_interceptions'] * 2 > s['def_sacks']) else ('def_sacks' if s['def_sacks'] else 'def_pass_defended')
        word = {'def_sacks': ('sack', 'sacks'), 'def_interceptions': ('INT', 'INT'), 'def_pass_defended': ('pass defended', 'passes defended')}[lead]
        rk = rank_of(w['id'], lead)
        sub = (f"{s[lead]:g} {word[0] if s[lead] == 1 else word[1]}" + (f" · {ordn(rk)} in the NFL" if rk <= 30 else '')) if s[lead] else ''
        cards.append(f'<span class="pw d">{mark(team, JERSEY.get(w["id"]))}<span><b>{esc(w["name"])}</b><small>{esc(w["pos"])} · {esc(line)}</small><em class="{"top" if rk <= 10 else ""}">{esc(sub)}</em></span></span>')
    return f'<div class="pw-col"><h4>{mark(team, team, 20)} {esc(nick(team))}</h4>{"".join(cards)}</div>'

# ---- box score, from the player files' weekly lines
def box(gid, wk, away, home):
    def team_rows(t):
        ps = [p for p in P.values() if any(int(w['week']) == wk and w.get('team') == t for w in p['weeks'])]
        L = {p['id']: next(w for w in p['weeks'] if int(w['week']) == wk and w.get('team') == t) for p in ps}; return ps, L
    out = ''; say = []
    for t in (away, home):
        ps, L = team_rows(t)
        snap = lambda p: f"{p['snaps'].get(str(wk), '')}%" if p['snaps'].get(str(wk)) else ''
        nm = lambda p: f'<a href="{PURL(p["id"])}">{esc(p["short"])}</a><small>{snap(p)}</small>'
        sg = lambda v: f'<td class="{"pos" if v >= 0 else "neg"}">{"+" if v > 0 else ""}{v:.1f}</td>'
        qb = sorted([p for p in ps if float(L[p['id']].get('attempts') or 0) > 0], key=lambda p: -float(L[p['id']]['attempts']))
        ru = sorted([p for p in ps if float(L[p['id']].get('carries') or 0) > 0], key=lambda p: -float(L[p['id']]['carries']))
        rc = sorted([p for p in ps if float(L[p['id']].get('targets') or 0) > 0], key=lambda p: -float(L[p['id']]['targets']))
        ttg = sum(float(L[p['id']]['targets']) for p in rc) or 1; tca = sum(float(L[p['id']]['carries']) for p in ru) or 1
        g = lambda p, k: float(L[p['id']].get(k) or 0)
        pas = ''.join(f'<tr><td class="nm">{nm(p)}</td><td>{int(g(p,"completions"))}/{int(g(p,"attempts"))}</td><td>{int(g(p,"passing_yards"))}</td><td>{int(g(p,"passing_tds")) or ""}</td><td>{int(g(p,"passing_interceptions")) or ""}</td><td>{int(g(p,"sacks_suffered")) or ""}</td>{sg(g(p,"passing_epa"))}</tr>' for p in qb)
        rus = ''.join(f'<tr><td class="nm">{nm(p)}</td><td>{int(g(p,"carries"))}</td><td>{round(g(p,"carries")/tca*100)}%</td><td>{int(g(p,"rushing_yards"))}</td><td>{int(g(p,"rushing_tds")) or ""}</td>{sg(g(p,"rushing_epa"))}</tr>' for p in ru)
        rec = ''.join(f'<tr><td class="nm">{nm(p)}</td><td>{int(g(p,"targets"))}</td><td>{round(g(p,"targets")/ttg*100)}%</td><td>{int(g(p,"receptions"))}</td><td>{int(g(p,"receiving_yards"))}</td><td>{int(g(p,"receiving_tds")) or ""}</td>{sg(g(p,"receiving_epa"))}</tr>' for p in rc)
        # defense and kicking for that game, from the weekly file
        dl = []; kk = ''
        for pid, ws in WKR.items():
            for w in ws:
                if int(w['week']) == wk and w.get('team') == t:
                    if F(w.get('def_sacks')) or F(w.get('def_interceptions')): dl.append((F(w.get('def_sacks')) * 2 + F(w.get('def_interceptions')) * 3, w))
                    if F(w.get('fg_att')): kk = f"{w['player_display_name'].split(' ')[0][0]}.{w['player_display_name'].split(' ')[-1]} {int(F(w['fg_made']))}/{int(F(w['fg_att']))} FG" + (f" (long {int(F(w['fg_long']))})" if F(w.get('fg_long')) else '')
        dl.sort(key=lambda x: -x[0])
        dtxt = ', '.join(f"{w['player_display_name'].split(' ')[0][0]}.{w['player_display_name'].split(' ')[-1]} " + ' and '.join(filter(None, [f"{F(w['def_sacks']):g} sack{'s' if F(w['def_sacks']) != 1 else ''}" if F(w['def_sacks']) else '', f"{int(F(w['def_interceptions']))} INT" if F(w['def_interceptions']) else ''])) for _, w in dl[:4])
        best = max(ps, key=lambda p: g(p, 'passing_epa') + g(p, 'rushing_epa') + g(p, 'receiving_epa')) if ps else None
        if best:
            e = g(best, 'passing_epa') + g(best, 'rushing_epa') + g(best, 'receiving_epa')
            line = f"{int(g(best,'completions'))} of {int(g(best,'attempts'))} for {int(g(best,'passing_yards'))}" if best['pos'] == 'QB' else (f"{int(g(best,'carries'))} carries for {int(g(best,'rushing_yards'))}" if best['pos'] in ('RB', 'FB') else f"{int(g(best,'receptions'))} catches for {int(g(best,'receiving_yards'))}")
            say.append(f"<b>{esc(best['short'])}</b> led the {esc(nick(t))} at {'+' if e > 0 else ''}{e:.1f} EPA ({line}).")
        H = lambda cols: '<thead><tr>' + ''.join(f'<th>{c}</th>' for c in cols) + '</tr></thead>'
        out += (f'<div class="bx-team"><h4>{mark(t, t, 20)} {esc(nick(t))}</h4>'
                f'<table class="dbd-stat bx">{H(["Passing", "C/A", "Yds", "TD", "INT", "Sk", "EPA"])}<tbody>{pas}</tbody></table>'
                f'<table class="dbd-stat bx">{H(["Rushing", "Car", "Share", "Yds", "TD", "EPA"])}<tbody>{rus}</tbody></table>'
                f'<table class="dbd-stat bx">{H(["Receiving", "Tgt", "Share", "Rec", "Yds", "TD", "EPA"])}<tbody>{rec}</tbody></table>'
                f'<p class="bx-dk"><b>Defense</b> {esc(dtxt) or "no sacks or interceptions"}{" · <b>Kicking</b> " + esc(kk) if kk else ""}</p></div>')
    return (f'<section class="dbd-panel dbd-box mk-new" data-mk="New · the box score"><div class="dbd-ph"><span class="dbd-lab"><b>Box score</b> · every offensive player who touched the ball</span><span class="dbd-badge">Week {wk}</span></div>'
            f'<p class="dbd-wp-say">{" ".join(say)}</p><div class="bx-2">{out}</div>'
            f'<details class="dbd-how"><summary>How this is measured</summary><p class="dbd-note">Each player\'s line for this game from nflverse\'s weekly stats; the percentage beside his name is his share of the offense\'s snaps. Share is his part of the team\'s carries or targets in this game. Defense lists sacks and interceptions; kicking lists field goals.</p></details></section>')

SHELL = open('shell.html').read()
def page(root, title, fn, extra_js=''):
    open(f'{OUT}/{fn}', 'w').write(SHELL.replace('%TITLE%', esc(title)).replace('%ROOT%', root).replace('%JS%', extra_js))

# ================= upcoming game: KC at DEN, Week 8 (the Chiefs offense side)
root = open('src/root-game_2026_08_KC_DEN.html').read()
i = root.index('<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Takeaways</b>'); j = root.index('</section>', i) + len('</section>')
take = root[i:j].replace('<b>Takeaways</b> · generated from the numbers', '<b>Keys to the game</b> · where this matchup tilts, from the season numbers')
keys = (f'<section class="dbd-panel dbd-keys mk-new" data-mk="Renamed + new · keys to the game">' + take[len('<section class="dbd-panel">'):-len('</section>')] +
        f'<h4 class="dbd-wph">Players to watch</h4><div class="pw-2">{watch("KC", "DEN")}{watch("DEN", "KC")}</div></section>')
lu = (f'<section class="dbd-panel dbd-lu mk-new" data-mk="New · the lineups"><div class="dbd-ph"><span class="dbd-lab"><b>The lineups</b> · Chiefs offense against the Broncos defense</span><span class="dbd-badge">Depth charts · Nov 1</span></div>'
      f'{lineup("KC", "DEN")}<details class="dbd-how"><summary>How this is measured</summary><p class="dbd-note">Starters from each team\'s current depth chart (nflverse, from ESPN), drawn in the offense\'s base three-receiver set against the defense\'s base front. The nickel back, the extra defensive back most teams use against three receivers, is drawn lighter. Left and right are as the offense sees the field. Flip the offense with the toggle at the top of the page.</p></details></section>')
root = root[:i] + keys + lu + root[j:]
page(root, 'Chiefs at Broncos · mock', 'game-upcoming.html')

# ================= final game: IND at KC, Week 2 (box score after win probability)
root = open('src/root-game_2026_02_IND_KC.html').read()
k = root.index('<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Takeaways</b>')
root = root[:k] + box('2026_02_IND_KC', 2, 'IND', 'KC') + root[k:]
root = root.replace('<b>Takeaways</b> · from the season numbers, not this game alone', '<b>Keys to the game</b> · from the season numbers, not this game alone')
page(root, 'Colts at Chiefs · mock', 'game-final.html')

# ================= team players page: Vikings
live = open('src/root-view_players_team_MIN.html').read()
head = live[:live.index('<section class="dbd-panel dbd-mv"')]
head = head.replace('Every skill player who has taken an offensive snap this season, with usage from the play file and this week\'s injury report. Pick one to see where the ball finds him.',
                    f'The Vikings\' lineup from the depth chart, every skill player\'s season, and who moved this week. {esc(T["MIN"]["nick"])} offense and defense, through Week 8.')
head = head.replace('565 players', 'All 565 players')
# lineup tabs: offense, defense, special teams
def unit_field(team, unit):
    if unit == 'off':
        o = LU[team]['off']; ch = ''.join(chip(team, k, OFFXY[k][0], round(26 + (OFFXY[k][1] - 59) * 3.4, 1), o[k], 'off', raw=True) for k in OFFXY if k in o)  # one unit fills its own board
    else:
        d = LU[team]['def']; M = DEF34 if '3-4' in LU[team]['grp']['def'] else DEF43; ch = ''.join(chip(team, k, M[k][0], round(76 - (45 - M[k][1]) * 2.4, 1), d[k], 'def', faded=(k == 'NB'), raw=True) for k in M if k in d)
    return f'<div class="lu-field unit"><i class="lu-los {unit}"></i>{ch}</div>'
st = LU['MIN']['st']; stl = ''.join(f'<li><b>{esc(k)}</b><span class="n">{esc(v["name"])}<small>{esc(v["pos"])}</small></span></li>' for k, v in st.items() if k in ('PK', 'P', 'KR', 'PR', 'LS', 'H'))
tabs = ('<div class="dbd-seg t-tabs dbd-btabs" role="tablist"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" aria-selected="true" data-pane="0">Offense · ' + esc(LU['MIN']['grp']['off']) + '</button><button class="t-tab" aria-selected="false" data-pane="1">Defense · ' + esc(LU['MIN']['grp']['def'].replace('Base ', '')) + '</button><button class="t-tab" aria-selected="false" data-pane="2">Special teams</button></div>')
lup = (f'<section class="dbd-panel dbd-tabbed dbd-lu mk-new" data-mk="New · the lineup"><div class="dbd-ph dbd-tabh">{tabs}</div>'
       f'<div class="dbd-pane" data-pane="0">{unit_field("MIN", "off")}</div><div class="dbd-pane" data-pane="1" hidden>{unit_field("MIN", "def")}</div><div class="dbd-pane" data-pane="2" hidden><ul class="lu-list st">{stl}</ul></div>'
       f'<details class="dbd-how"><summary>How this is measured</summary><p class="dbd-note">Starters from the Vikings\' current depth chart (nflverse, from ESPN). Hover a player for his season; skill players open their page.</p></details></section>')
# roster grouped by position, from the index rows the page already renders
idx = [r for r in json.load(open(f'{DATA}/players/index.json'))['players'] if r['team'] == 'MIN']
grp = collections.OrderedDict((('QB', 'Quarterbacks'), ('RB', 'Running backs'), ('WR', 'Wide receivers'), ('TE', 'Tight ends')))
rh = ''
for gk, gl in grp.items():
    rs = [r for r in idx if (r['pos'] == gk or (gk == 'RB' and r['pos'] == 'FB'))]
    if not rs: continue
    rs.sort(key=lambda r: -r['snap'])
    def stat_html(r):
        if not r.get('st'): return ''
        cls = ' o' if re.search('out|did not', r['st'] or '', re.I) else ' q'
        return '<span class="dbd-st' + cls + '">' + esc(r['st']) + (' · ' + esc(r['what']) if r.get('what') else '') + '</span>'
    def rrow(r):
        slot = '<small>' + esc(r['slot']) + '</small>' if r.get('slot') else ''
        use = r['att'] if gk == 'QB' else r['tgt']; rec = r['rec'] if gk != 'QB' else ''
        ep = ('+' if r['epa'] > 0 else '') + f"{r['epa']:.1f}"; cl = 'pos' if r['epa'] >= 0 else 'neg'
        return (f'<tr><td class="nm"><a href="{PURL(r["id"])}">{esc(r["name"])}</a>{slot}</td><td>{r["snap"]}%</td><td>{use}</td><td>{rec}</td><td>{r["car"] or ""}</td>'
                f'<td>{r["yds"]}</td><td>{r["td"] or ""}</td><td class="{cl}">{ep}</td><td>{stat_html(r)}</td></tr>')
    body = ''.join(rrow(r) for r in rs)
    rh += f'<tbody class="grp"><tr class="gh"><th colspan="9">{gl}</th></tr>{body}</tbody>'
roster = (f'<section class="dbd-panel dbd-roster mk-new" data-mk="Regrouped · the roster by position"><div class="dbd-ph"><span class="dbd-lab"><b>The roster</b> · every skill player with a snap this season</span><span class="dbd-badge">{len(idx)} players</span></div>'
          f'<div class="dbd-tw"><table class="dbd-stat dbd-rost"><thead><tr><th>Player</th><th>Snap</th><th>Tgt/Att</th><th>Rec</th><th>Car</th><th>Yds</th><th>TD</th><th>EPA</th><th>Status</th></tr></thead>{rh}</table></div></section>')
mv = json.load(open(f'{DATA}/players/movers.json')); mine = []
for kind in ('snaps', 'targets', 'epa'):
    for d in ('up', 'down'):
        for r in mv.get(kind, {}).get(d, []):
            if r['team'] == 'MIN': mine.append((kind, d, r))
mvh = ''.join(f'<li><a href="{PURL(r["id"])}">{esc(r["name"])}</a> <span class="{"up" if d == "up" else "dn"}">{"+" if r["delta"] > 0 else ""}{r["delta"]:g}</span> <small>{ {"snaps": "snap share", "targets": "target share", "epa": "EPA"}[kind] } · Week {mv["week"]} against his season</small></li>' for kind, d, r in mine)
mvp = (f'<section class="dbd-panel mk-new" data-mk="Moved down + filtered · this team\'s movers"><div class="dbd-ph"><span class="dbd-lab"><b>Vikings movers</b> · Week {mv["week"]} against his own season</span></div>'
       + (f'<ul class="mv-list">{mvh}</ul>' if mvh else f'<p class="dbd-wp-say">No Vikings player moved enough in Week {mv["week"]} to make the league\'s movers. <a href="https://downbydown.com/?view=players">See every team\'s movers</a></p>') + '</section>')
root = head + lup + roster + mvp + '</main></div>'
page(root, 'Vikings players · mock', 'team-players.html', '<script>document.querySelectorAll(".dbd-tabbed").forEach(function(b){var bar=b.querySelector(".dbd-btabs"),pl=bar.querySelector(".t-tabs-pill");var go=function(t){pl.style.transform="translateX("+t.offsetLeft+"px)";pl.style.width=t.offsetWidth+"px";};bar.addEventListener("click",function(e){var t=e.target.closest(".t-tab");if(!t)return;bar.querySelectorAll(".t-tab").forEach(function(x){x.setAttribute("aria-selected",x===t?"true":"false");});b.querySelectorAll(".dbd-pane").forEach(function(p){p.hidden=p.dataset.pane!==t.dataset.pane;});go(t);});requestAnimationFrame(function(){go(bar.querySelector("[aria-selected=true]"));});if(document.fonts)document.fonts.ready.then(function(){go(bar.querySelector("[aria-selected=true]"));});window.addEventListener("load",function(){go(bar.querySelector("[aria-selected=true]"));});});</script>')
print('ok', {t: LU[t]['grp'] for t in ('KC', 'DEN', 'MIN')}, 'MIN movers', len(mine))
