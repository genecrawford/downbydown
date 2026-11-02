"""Mock for SCOPE §44: the season archive. The 2025 season page (an archived season's hub) with the season switcher open and
the archive banner, built from nflverse's real 2025 results (games25.csv) and regular-season player stats (stats25.csv),
plus the top of an archived team page. Team marks from the site's teams.json. Writes archive.html. python3 -I mock.py"""
import csv, json, html, datetime, collections
esc = html.escape
T = json.load(open('../data/teams.json'))
G = list(csv.DictReader(open('games25.csv')))
REG = [g for g in G if g['game_type'] == 'REG' and g['home_score'] != '']
POST = [g for g in G if g['game_type'] in ('WC', 'DIV', 'CON', 'SB') and g['home_score'] != '']
FIX = {'LA': 'LA', 'LAR': 'LA', 'STL': 'LA', 'OAK': 'LV', 'SD': 'LAC'}
t_ = lambda a: FIX.get(a, a)
def logo(a, px): a = t_(a); return f'<img class="m-lg" src="{T[a]["logo"]}" alt="" width="{px}" height="{px}">'
nick = lambda a: T[t_(a)]['nick']

# ---------------------------------------------------------------- standings
rec = collections.defaultdict(lambda: [0, 0, 0, 0, 0])  # w l t pf pa
for g in REG:
    a, h, sa, sh = t_(g['away_team']), t_(g['home_team']), int(g['away_score']), int(g['home_score'])
    rec[a][3] += sa; rec[a][4] += sh; rec[h][3] += sh; rec[h][4] += sa
    if sa > sh: rec[a][0] += 1; rec[h][1] += 1
    elif sh > sa: rec[h][0] += 1; rec[a][1] += 1
    else: rec[a][2] += 1; rec[h][2] += 1
wc_teams = {t_(g[k]) for g in POST if g['game_type'] == 'WC' for k in ('away_team', 'home_team')}
div_teams = {t_(g[k]) for g in POST if g['game_type'] == 'DIV' for k in ('away_team', 'home_team')}
inn = wc_teams | div_teams; bye = div_teams - wc_teams
wpct = lambda t: (rec[t][0] + rec[t][2] / 2) / max(1, sum(rec[t][:3]))
def rtxt(t): w, l, ti = rec[t][:3]; return f'{w}-{l}' + (f'-{ti}' if ti else '')
divs = collections.OrderedDict()
for conf in ('AFC', 'NFC'):
    for d in ('East', 'North', 'South', 'West'):
        k = f'{conf} {d}'; divs[k] = sorted([t for t in T if T[t]['div'] == k], key=lambda t: (-wpct(t), -(rec[t][3] - rec[t][4])))
def stand(k):
    rows = ''.join(f'<tr><td class="tm">{logo(t, 20)}<a>{esc(T[t]["nick"])}</a>' + (f'<i class="m-seed{" bye" if t in bye else ""}">{"Bye" if t in bye else "P"}</i>' if t in inn else '')
                   + f'</td><td>{rtxt(t)}</td><td class="{"pos" if rec[t][3] >= rec[t][4] else "neg"}">{rec[t][3] - rec[t][4]:+d}</td></tr>' for t in divs[k])
    return f'<div class="m-div"><h4>{esc(k)}</h4><table class="m-st"><thead><tr><th>Team</th><th>W-L</th><th>Diff</th></tr></thead><tbody>{rows}</tbody></table></div>'
standings = '<div class="m-divs">' + ''.join(stand(k) for k in divs) + '</div>'

# ---------------------------------------------------------------- playoffs
RND = [('WC', 'Wild Card'), ('DIV', 'Divisional'), ('CON', 'Conference'), ('SB', 'Super Bowl')]
def pgame(g):
    a, h, sa, sh = t_(g['away_team']), t_(g['home_team']), int(g['away_score']), int(g['home_score'])
    ot = g['overtime'] == '1'
    row = lambda t, s, w: f'<span class="r{" w" if w else ""}">{logo(t, 22)}<b>{esc(T[t]["nick"])}</b><em>{s}</em></span>'
    return f'<a class="m-pg">{row(a, sa, sa > sh)}{row(h, sh, sh > sa)}' + ('<small>OT</small>' if ot else '') + '</a>'
bracket = '<div class="m-br">' + ''.join(f'<div class="m-rd"><h4>{lab}</h4>' + ''.join(pgame(g) for g in POST if g['game_type'] == k) + '</div>' for k, lab in RND) + '</div>'

sb = next(g for g in POST if g['game_type'] == 'SB')
sa, sh = int(sb['away_score']), int(sb['home_score']); win = t_(sb['away_team'] if sa > sh else sb['home_team']); los = t_(sb['home_team'] if sa > sh else sb['away_team'])
ws, ls = max(sa, sh), min(sa, sh)
sbday = datetime.date.fromisoformat(sb['gameday']).strftime('%b %-d, %Y')
pw = lambda t: sum(1 for g in POST if (t_(g['away_team']) == t and int(g['away_score']) > int(g['home_score'])) or (t_(g['home_team']) == t and int(g['home_score']) > int(g['away_score'])))
def roman(n):
    out = ''
    for v, r in ((50, 'L'), (40, 'XL'), (10, 'X'), (9, 'IX'), (5, 'V'), (4, 'IV'), (1, 'I')):
        while n >= v: out += r; n -= v
    return out
champ = (f'<section class="dbd-panel m-champ" style="--c:{T[win]["c1"]}"><div class="m-ch-l"><p class="m-ch-k">Super Bowl {roman(int(sb["season"]) - 1965)} · {esc(sbday)} · {esc(sb["stadium"])}</p>'
         f'<h2><span class="w">{esc(T[win]["name"])}</span> are the champions</h2>'
         f'<div class="m-ch-s"><span>{logo(win, 64)}<b>{esc(nick(win))}</b><em>{ws}</em></span><span class="l">{logo(los, 64)}<b>{esc(nick(los))}</b><em>{ls}</em></span></div>'
         f'<p class="m-ch-n">{esc(nick(win))} finished {rtxt(win)} in the regular season{" and had a bye" if win in bye else ""}, then won {pw(win)} playoff games; the {esc(nick(los))} came in {rtxt(los)} and won {pw(los) - 0} before the final.</p></div>'
         f'<div class="m-ch-r">{logo(win, 180)}</div></section>')

# ---------------------------------------------------------------- leaders (regular season)
S = list(csv.DictReader(open('stats25.csv')))
P = {}
for r in S:
    p = P.setdefault(r['player_id'], dict(name=r['player_display_name'], pos=r['position'], team=t_(r['team']), head=r['headshot_url'], py=0, ry=0, cy=0, td=0, ppr=0.0, g=0))
    f = lambda k: float(r[k] or 0)
    p['py'] += f('passing_yards'); p['ry'] += f('rushing_yards'); p['cy'] += f('receiving_yards'); p['td'] += f('rushing_tds') + f('receiving_tds'); p['ppr'] += f('fantasy_points_ppr'); p['g'] += 1; p['team'] = t_(r['team'])
def board(title, k, fmt, unit):
    top = sorted(P.values(), key=lambda p: -p[k])[:5]; a = top[0]
    rest = ''.join(f'<li><span>{i + 2}</span><b>{esc(p["name"])}</b><small>{esc(p["team"])}</small><em>{fmt(p[k])}</em></li>' for i, p in enumerate(top[1:]))
    return (f'<section class="dbd-panel m-ld"><div class="m-ld-h"><i>{esc(title)}</i></div><div class="m-ld-a"><img class="m-hd" src="{esc(a["head"])}" alt="" width="64" height="64">'
            f'<div><b>{esc(a["name"])}</b><small>{esc(a["pos"])} · {esc(T[a["team"]]["nick"])}</small><strong>{fmt(a[k])}<span>{unit}</span></strong></div></div><ol start="2">{rest}</ol></section>')
n0 = lambda v: f'{v:,.0f}'
leaders = ('<div class="m-lds">' + board('Passing yards', 'py', n0, 'yds') + board('Rushing yards', 'ry', n0, 'yds') + board('Receiving yards', 'cy', n0, 'yds')
           + board('Scrimmage touchdowns', 'td', n0, 'TD') + board('PPR fantasy points', 'ppr', lambda v: f'{v:,.1f}', 'pts') + '</div>')
ppg = lambda t: rec[t][3] / max(1, sum(rec[t][:3])); papg = lambda t: rec[t][4] / max(1, sum(rec[t][:3]))
bo = max(T, key=ppg); bd = min(T, key=papg)
units = (f'<div class="m-units"><div class="dbd-panel"><i>Best offense</i>{logo(bo, 40)}<b>{esc(T[bo]["nick"])}</b><span>{ppg(bo):.1f} points a game <small>(the build: EPA a play)</small></span></div>'
         f'<div class="dbd-panel"><i>Best defense</i>{logo(bd, 40)}<b>{esc(T[bd]["nick"])}</b><span>{papg(bd):.1f} allowed a game <small>(the build: EPA a play allowed)</small></span></div></div>')

# ---------------------------------------------------------------- weeks strip
weeks = '<nav class="m-wks">' + ''.join(f'<a>{w}</a>' for w in range(1, 19)) + ''.join(f'<a class="po">{lab}</a>' for _, lab in RND) + '</nav>'

# ---------------------------------------------------------------- chrome: top bar with the switcher open, the archive banner
def topbar(crumbs, open_=False):
    menu = ('<div class="m-sw-menu"><a class="cur"><b>2026</b><small>this season · week 8</small></a><a class="on"><b>2025</b><small>final · Seahawks won it</small></a>'
            '<span class="soon"><b>2024 · 2023 · 2022</b><small>one click each to build (§44)</small></span></div>') if open_ else ''
    return (f'<div class="dbd-top"><div class="dbd-crumb"><a class="dbd-brand">DOWN X DOWN</a> {crumbs}</div><div class="dbd-r"><span class="dbd-upd">Season over · final</span>'
            f'<div class="m-sw{" open" if open_ else ""}"><button type="button" class="m-swb" aria-expanded="{str(open_).lower()}">2025 <span>▾</span></button>{menu}</div></div></div>')
banner = '<div class="m-arch"><span><b>2025 season · final</b> Every snap of the 2025 season, kept as it ended.</span><a>Back to 2026 →</a></div>'
band = lambda t, s: f'<h2 class="dbd-bandh">{esc(t)}<span>{esc(s)}</span></h2>'

page = (f'<div class="dbd dbd-view-hub">{topbar("<span>›</span> <b>2025 season</b>", True)}{banner}<main class="dbd-content">'
        f'<div class="dbd-ttitle"><div><p class="dbd-kicker">2025 season · final</p><h1><em>2025</em> season</h1><p class="dbd-sub">How it ended, who led, and every game still here: '
        f'each team, player and week of 2025 reads the way it did the morning after the Super Bowl.</p></div></div>'
        f'{champ}{band("The playoffs", "round by round; every game opens its page")}{bracket}'
        f'{band("Final standings", "P made the playoffs, Bye was a top seed")}{standings}'
        f'{band("Who led 2025", "regular season")}{leaders}{units}'
        f'{band("Every week", "the week in review, weeks 1 to 18 and the playoffs")}{weeks}</main></div>')

# the same banner on any archived page: the top of the Vikings' 2025 team page
mr = rec['MIN']
team = (f'<div class="dbd dbd-view-team m-mini">{topbar("<span>›</span> <a>teams</a> <span>›</span> <b>Vikings</b>")}{banner}<main class="dbd-content">'
        f'<div class="dbd-ttitle"><div><p class="dbd-kicker">2025 season · offense · {rtxt("MIN")}</p><h1 class="withmark">{logo("MIN", 44)}<em>Vikings</em> offense</h1>'
        f'<p class="dbd-sub">Every Vikings snap of 2025 on the field. This page is today\'s team page reading the 2025 files; only the banner and the switcher say so.</p></div></div>'
        f'<div class="m-ghost">The play explorer, ranks, players and defense tabs, as on the 2026 page</div></main></div>')

off = ('<div class="dbd"><section class="dbd-panel m-off"><div class="dbd-ph"><span class="dbd-lab"><b>What a past season leaves out</b> · the parts about now</span></div><ul>'
       '<li>My team, and the hub\'s My team card</li><li>"This week" and next-game cards on player, compare and team pages</li>'
       '<li>The injury report and depth chart labels</li><li>"Still to play", kickoff times and the stats-pending note</li><li>The fantasy "this week" line</li></ul></section></div>')

SHELL = open('shell.html').read()
open('archive.html', 'w').write(SHELL.replace('%TITLE%', 'Season archive · mock').replace('%A%', page).replace('%B%', team + off))
print('wrote archive.html;', 'champion', win, ws, '-', ls, los, '| playoff teams', len(inn), '| leaders', [max(P.values(), key=lambda p: p[k])['name'] for k in ('py', 'ry', 'cy', 'td', 'ppr')])
