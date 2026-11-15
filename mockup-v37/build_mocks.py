"""Mock: the draft, the combine and Wikipedia on a player page (Gene, 2026-11-15: "Scope both"). Run from mockup-v37/ with _p-jj.html
(https://downbydown.com/player/justin-jefferson/), combine.csv and draft.csv (nflverse-data releases 'combine' and 'draft_picks'). Builds
player-jj.html: Justin Jefferson's live page with a Before the NFL band after Career, a Wikipedia pill in the header and the round in the
bio line. Every number is computed here from the nflverse files; the Wikipedia text is his article's summary. Static: opens from disk."""
import csv, re, html

H = open('_p-jj.html').read()
C = list(csv.DictReader(open('combine.csv'))); D = list(csv.DictReader(open('draft.csv')))
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

PFR, POS = 'JeffJu00', 'WR'
me = [x for x in C if x['pfr_id'] == PFR][0]
dp = [x for x in D if x['gsis_id'] == '00-0036322'][0]
def inch(s):
    try: f, i = s.split('-'); return int(f) * 12 + int(i)
    except Exception: return None
DRILLS = [('ht', 'Height', lambda v: f"{v // 12}'{v % 12}\"", False), ('wt', 'Weight', lambda v: f'{int(v)} lb', False), ('forty', '40-yard dash', lambda v: f'{v:.2f}s', True),
          ('vertical', 'Vertical', lambda v: f'{v:g}"', False), ('broad_jump', 'Broad jump', lambda v: f"{int(v) // 12}'{int(v) % 12}\"", False), ('bench', 'Bench press', lambda v: f'{int(v)} reps', False),
          ('cone', '3-cone', lambda v: f'{v:.2f}s', True), ('shuttle', 'Shuttle', lambda v: f'{v:.2f}s', True)]
def val(x, k): return inch(x['ht']) if k == 'ht' else (float(x[k]) if x[k] else None)
rows, tested = '', []
for k, lab, fm, low in DRILLS:
    pool = [v for v in (val(x, k) for x in C if x['pos'] == POS) if v is not None]; v = val(me, k)
    if v is None:
        rows += f'<div class="cb-row none"><span class="cb-k">{lab}</span><span class="cb-v">did not test</span><span class="cb-bar"></span><span class="cb-p"></span></div>'; continue
    pct = round(100 * sum(1 for o in pool if (o > v if low else o < v)) / len(pool))
    if k not in ('ht', 'wt'): tested.append(pct)
    ordn = lambda n: f'{n}{"th" if 10 <= n % 100 <= 20 else {1: "st", 2: "nd", 3: "rd"}.get(n % 10, "th")}'
    rows += f'<div class="cb-row"><span class="cb-k">{lab}</span><span class="cb-v">{fm(v)}</span><span class="cb-bar"><i style="width:{pct}%"></i></span><span class="cb-p">{ordn(pct)}</span></div>'
score = round(sum(tested) / len(tested)) if tested else None
wrs = sorted([x for x in D if x['season'] == dp['season'] and x['position'] == POS], key=lambda x: int(x['pick']))
rk = [x['gsis_id'] for x in wrs].index('00-0036322') + 1
before = ', '.join(x['pfr_player_name'] for x in wrs[:rk - 1])
WIKI = ('Justin Jamal Jefferson is an American professional football wide receiver for the Minnesota Vikings of the National Football League (NFL). '
        'He played college football for the LSU Tigers, where he won the 2020 College Football Playoff National Championship as a junior before being drafted by the Vikings in the first round of the 2020 NFL draft.')
WURL = 'https://en.wikipedia.org/wiki/Justin_Jefferson'

BAND = f'''<section class="dbd-band" id="dbd-before"><h2 class="dbd-bandh">Before the NFL<span>the draft, the combine, and the rest of his story</span></h2>
<div class="dbd-bf2">
<section class="dbd-panel dbd-draft"><div class="dbd-ph"><span class="dbd-lab"><b>The draft</b></span><span class="dbd-badge">{dp['season']} class</span></div><div class="dbd-pb">
<p class="df-big">Round {dp['round']} · Pick {dp['pick']}</p>
<p class="df-sub">{dp['season']} · Minnesota Vikings · from {html.escape(dp['college'])}</p>
<dl class="dbd-kv"><dt>Among {POS}s</dt><dd><b>{rk}th</b> of {len(wrs)} taken, after {html.escape(before)}</dd>
<dt>Since</dt><dd><b>{dp['probowls']}</b> Pro Bowls · <b>{dp['allpro']}</b> first-team All-Pro</dd></dl></div></section>
<section class="dbd-panel dbd-combine"><div class="dbd-ph"><span class="dbd-lab"><b>The combine</b> · {me['season']}, against every {POS} tested since 2000</span><span class="dbd-badge">Score {score} of 100</span></div><div class="dbd-pb">
<div class="cb-head"><span>Drill</span><span>Result</span><span>Percentile among {POS}s</span><span></span></div>{rows}
<p class="dbd-note">Percentile is the share of {POS}s at the combine since 2000 he beat: faster on the timed drills, higher, longer or stronger on the rest. The score averages the drills he ran ({len(tested)} of 6). Skipped drills are often run later at his school's pro day, which isn't in this data.</p></div></section>
</div>
<section class="dbd-panel dbd-wiki"><div class="dbd-ph"><span class="dbd-lab"><b>From Wikipedia</b> · American football player (born 1999)</span><a class="dbd-badge" href="{WURL}" target="_blank" rel="noopener">Read on Wikipedia ↗</a></div><div class="dbd-pb">
<p class="wk-x">{html.escape(WIKI)}</p><p class="dbd-note">From the Wikipedia article <a href="{WURL}" target="_blank" rel="noopener">Justin Jefferson</a>, available under <a href="https://creativecommons.org/licenses/by-sa/4.0/" target="_blank" rel="noopener">CC BY-SA 4.0</a>.</p></div></section>
</section>'''

CSS = '''<style>.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}
.dbd .dbd-bf2{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.6fr);gap:18px}
@container dbd (max-width:820px){.dbd .dbd-bf2{grid-template-columns:minmax(0,1fr)}}
.dbd .dbd-draft .df-big{margin:0;font:800 34px/1 var(--dbd-hero);text-transform:uppercase;color:var(--dbd-ink)}
.dbd .dbd-draft .df-sub{margin:6px 0 14px;font:500 14px var(--dbd-body);color:var(--dbd-ink-2)}
.dbd .dbd-draft .dbd-kv{display:grid;grid-template-columns:auto minmax(0,1fr);gap:8px 14px;margin:0;font-size:13px}
.dbd .dbd-draft .dbd-kv dt{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted);padding-top:2px}
.dbd .dbd-draft .dbd-kv dd{margin:0;color:var(--dbd-ink-2)}
.dbd .dbd-combine .cb-head,.dbd .dbd-combine .cb-row{display:grid;grid-template-columns:120px 92px minmax(0,1fr) 44px;gap:12px;align-items:center}
.dbd .dbd-combine .cb-head{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted);padding-bottom:6px;border-bottom:1px solid var(--dbd-line)}
.dbd .dbd-combine .cb-row{padding:7px 0;border-bottom:1px solid var(--dbd-line);font-size:13px}
.dbd .dbd-combine .cb-k{font-weight:600;color:var(--dbd-ink)}
.dbd .dbd-combine .cb-v{font:500 13px var(--dbd-mono);color:var(--dbd-ink)}
.dbd .dbd-combine .cb-bar{height:8px;border-radius:4px;background:var(--dbd-panel-2);overflow:hidden}
.dbd .dbd-combine .cb-bar i{display:block;height:100%;background:var(--dbd-team)}
.dbd .dbd-combine .cb-p{font:600 12px var(--dbd-mono);color:var(--dbd-ink-2);text-align:right}
.dbd .dbd-combine .cb-row.none .cb-v{color:var(--dbd-muted);font-family:var(--dbd-body);font-style:italic}
.dbd .dbd-wiki{margin-top:18px}
.dbd .dbd-wiki .wk-x{margin:0;max-width:75ch;font-size:15px;line-height:1.6;color:var(--dbd-ink-2)}
.dbd a.dbd-badge{text-decoration:none}
</style>'''
out = H.replace('</head>', CSS + '</head>', 1)
i = out.find('<section class="dbd-band" id="dbd-more">'); assert i > 0
note = ('<div class="mock-note"><b>Before the NFL.</b> A new band after Career: the draft (round and pick, his class, where he went among his position, what he has earned since), '
        'the combine (each drill with his percentile against every player at his position tested since 2000, and a score that averages the drills he ran), and the opening of his Wikipedia article '
        'with the credit Wikipedia asks for. Up top, the bio line names the round, and a Wikipedia pill sits with the others. Every number is real, from nflverse. Scroll past Career.</div>')
out = out[:i] + note + BAND + out[i:]
out = out.replace('Pick 22 overall (2020)', 'Round 1, pick 22 (2020)', 1)
out = out.replace('<a class="dbd-pill" href="https://downbydown.com/team/min/players/">Vikings players</a></div>', f'<a class="dbd-pill" href="https://downbydown.com/team/min/players/">Vikings players</a><a class="dbd-pill" href="#dbd-before">Draft and combine</a><a class="dbd-pill" href="{WURL}" target="_blank" rel="noopener">Wikipedia ↗</a></div>', 1)
k = out.find('<main class="dbd-content dbd-player"'); k = out.find('>', k) + 1
out = out[:k] + '<div class="mock-note">Mock: the header names the round and adds Draft and combine and Wikipedia pills. The new band is after Career, below.</div>' + out[k:]
open('player-jj.html', 'w').write(no_tracking(out)); print('ok', score, rk, len(wrs))
