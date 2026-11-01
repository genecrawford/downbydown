"""Mock for SCOPE §33: the series since 1999, the last meeting and both teams' records, on the live Vikings at Lions page.
   python3 -I mock.py <teams.json> ; numbers in src/series.json come from nflverse's games.csv (calc.py in the scratchpad)."""
import json, sys, html, datetime
esc = html.escape
T = json.load(open(sys.argv[1])); D = json.load(open('src/series.json')); S = D['series']; R = D['rec']
A, B = 'MIN', 'DET'; nick = lambda t: T[t]['nick']
def wl(x): return f"{x[0]}–{x[1]}" + (f"–{x[2]}" if len(x) > 2 and x[2] else '')
lead = A if S['w'] > S['l'] else (B if S['l'] > S['w'] else None)
head = (f"The {nick(lead)} lead the series <b>{max(S['w'], S['l'])}–{min(S['w'], S['l'])}</b> since {S['first']}" if lead else f"The series is even at {S['w']}–{S['l']} since {S['first']}") + '.'
sk = S['streak']; head += f" They have won the last {sk[1]}." if sk and sk[0] == lead and sk[1] > 1 else (f" The {nick(sk[0])} have won the last {sk[1]}." if sk and sk[1] > 1 else '')
chips = [(f'{nick(A)} at home', wl(S['home']), f"the {nick(A)}' record"), (f'In {T[B]["name"].replace(" " + nick(B), "")}', wl(S['road']), f"the {nick(A)}' record"),
         ('Most points', f"{nick(A)} {S['most'][A][0]} · {nick(B)} {S['most'][B][0]}", f"{S['most'][A][1]} · {S['most'][B][1]}"),
         ('Biggest win', f"{nick(A)} by {S['big'][A][0]} · {nick(B)} by {S['big'][B][0]}", f"{S['big'][A][1]} · {S['big'][B][1]}")]
chip = ''.join(f'<div class="c"><small>{esc(a)}</small><b>{esc(b)}</b><span>{esc(c)}</span></div>' for a, b, c in chips)
rows = ''
for (season, wk, gt, aw, as_, hm, hs, ot) in reversed(S['last5']):
    a_s, h_s = int(as_), int(hs); w = aw if a_s > h_s else hm
    score = f'{esc(aw)} <b>{a_s}</b> at {esc(hm)} <b>{h_s}</b>' if w == aw else f'{esc(aw)} {a_s} at {esc(hm)} <b>{h_s}</b>'
    score = f'<span class="{"w" if aw == w else ""}">{esc(aw)} {a_s}</span> <i>at</i> <span class="{"w" if hm == w else ""}">{esc(hm)} {h_s}</span>'
    rows += f'<tr><td>{season} · Wk {wk}{" · OT" if ot == "1" else ""}{" · playoffs" if gt != "REG" else ""}</td><td>{score}</td></tr>'
L = S['lastm']; dt = datetime.date.fromisoformat(L['date']); hm, aw = L['home'], L['away']
lw = hm if int(L['h_s']) > int(L['a_s']) else aw
last = (f"<b>{dt.strftime('%b')} {dt.day}, {dt.year}</b>{' (Christmas)' if (dt.month, dt.day) == (12, 25) else ''}, at {esc(L['stadium'])}: the {esc(nick(lw))} won "
        f"{max(int(L['h_s']), int(L['a_s']))}–{min(int(L['h_s']), int(L['a_s']))}. {esc(L['qbs'][1])} started at quarterback for the {esc(nick(hm))} and {esc(L['qbs'][0])} for the {esc(nick(aw))}; "
        f"{esc(L['coaches'][1])} and {esc(L['coaches'][0])} coached.")
# records, side by side
labels = [('all', 'Overall'), ('div', 'Division'), ('conf', 'Conference'), ('nonconf', 'Other conference'), ('home', 'Home'), ('away', 'Away')]
def cell(t, k):
    v = R[t]['rec'].get(k); return wl(v) if v else '0–0'
rec = ''.join(f'<tr><td>{esc(lab)}</td><td>{cell(A, k)}</td><td>{cell(B, k)}</td></tr>' for k, lab in labels)
dots = lambda s: ''.join(f'<i class="{"w" if c == "W" else ("l" if c == "L" else "t")}">{c}</i>' for c in s)
rec += f'<tr><td>Last 5</td><td><span class="dots">{dots(R[A]["last5"])}</span></td><td><span class="dots">{dots(R[B]["last5"])}</span></td></tr>'
rec += f'<tr><td>Streak</td><td>{esc(R[A]["streak"].replace("W", "Won ").replace("L", "Lost "))}</td><td>{esc(R[B]["streak"].replace("W", "Won ").replace("L", "Lost "))}</td></tr>'
rec += f'<tr><td>Points a game</td><td>{R[A]["pf"] / R[A]["g"]:.1f} – {R[A]["pa"] / R[A]["g"]:.1f}</td><td>{R[B]["pf"] / R[B]["g"]:.1f} – {R[B]["pa"] / R[B]["g"]:.1f}</td></tr>'
panel = (f'<section class="dbd-panel dbd-series mk-new" data-mk="New · the series and the records"><div class="dbd-ph"><span class="dbd-lab"><b>The series</b> · {esc(nick(A))} and {esc(nick(B))} since {S["first"]}, and this season\'s records</span><span class="dbd-badge">{S["n"]} meetings</span></div>'
         f'<p class="dbd-wp-say">{head} {last}</p><div class="dbd-sr2"><div><div class="dbd-wpchips sr">{chip}</div><h4 class="dbd-wph">The last five</h4><table class="dbd-stat dbd-srl"><tbody>{rows}</tbody></table></div>'
         f'<div><h4 class="dbd-wph">2026 records</h4><table class="dbd-stat dbd-srr"><thead><tr><th></th><th>{esc(nick(A))}</th><th>{esc(nick(B))}</th></tr></thead><tbody>{rec}</tbody></table></div></div>'
         f'<details class="dbd-how"><summary>How this is measured</summary><p class="dbd-note">Every meeting since {S["first"]}, regular season and playoffs, from nflverse\'s game list, which starts in {S["first"]}; the all-time series goes back further. Franchises that moved count as one team (the Raiders, Chargers and Rams). Home and away are from the {esc(nick(A))}\' side. Records are this regular season; division and conference games count from each team\'s own side.</p></details></section>')
root = open('src/root.html').read()
k = root.index('<section class="dbd-panel dbd-lu">')
root = root[:k] + panel + root[k:]
shell = open('shell.html').read()
open('series-min-det.html', 'w').write(shell.replace('%ROOT%', root))
print('ok')
