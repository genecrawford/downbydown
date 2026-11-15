"""Mock: three ways to tidy the explorer's filters (Gene, 2026-11-15: "still confusing"; "try each visual approach and mock each one").
Run from mockup-v36/ with _t-min.html (https://downbydown.com/team/min/), _te.js (the 2.20.0 team-explorer.js) and MIN.json (the Vikings
play file). Builds filters-a-sentence.html, filters-b-dropdowns.html and filters-c-fewer.html, each with the play data and the patched
explorer INLINE so it opens straight from disk. All three also keep every receiver on the receiver chart and highlight the picked one."""
import re

H = open('_t-min.html').read(); JS0 = open('_te.js').read(); DATA = open('MIN.json').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

class Patch:
    def __init__(self, js): self.js = js
    def sub(self, a, b):
        assert self.js.count(a) == 1, a[:90]; self.js = self.js.replace(a, b)
    def block(self, start, end_incl, new):  # replace from a line starting with `start` through the line containing `end_incl`
        a = self.js.index(start); b = self.js.index(end_incl, a); b = self.js.index('\n', b) + 1
        self.js = self.js[:a] + new + self.js[b:]

def common(p):
    # data rides in the page
    p.sub("fetch(C.dataUrl, { credentials: 'omit' })", "Promise.resolve({ ok: true, json: function () { return window.__DBD_PLAYS; } })")
    # receiver chart: everyone stays, the picked player lit
    p.sub("      var tg = {}, carries = {};\n      F.forEach(function (p) { if (!p.pl) return; if (p.k === 'run')",
          "      var tg = {}, carries = {}, F4 = st.pl ? others() : F;\n      F4.forEach(function (p) { if (!p.pl) return; if (p.k === 'run')")
    p.sub("var minT = F.length >= 200 ? 5 : 3;", "var minT = F4.length >= 200 ? 5 : 3;")
    p.sub("d.element.addClass('dbd-pt-' + r.pos); o4.add('dbd-ch-lbl', esc(r.n), d.x, d.y);",
          "d.element.addClass('dbd-pt-' + r.pos); if (st.pl) d.element.addClass(r.n === st.pl ? 'is-on' : 'is-dim'); o4.add('dbd-ch-lbl' + (st.pl ? (r.n === st.pl ? ' is-on' : ' is-dim') : ''), esc(r.n), d.x, d.y);")
    p.sub("var deepest = recs.reduce(function (m, r) { return r.adot > m.adot ? r : m; }), busiest = recs[0];\n        setRead(4, ",
          "var deepest = recs.reduce(function (m, r) { return r.adot > m.adot ? r : m; }), busiest = recs[0], me = st.pl ? recs.filter(function (r) { return r.n === st.pl; })[0] : null, rk = me ? recs.indexOf(me) + 1 : 0;\n"
          "        if (st.pl) setRead(4, me ? me.n + ': ' + me.t + ' targets at ' + me.adot + ' air yards each, ' + (rk === 1 ? 'the most' : 'the ' + (['', '', '2nd', '3rd'][rk] || rk + 'th') + ' most') + ' of ' + recs.length + ' receivers here. ' + (rk === 1 ? '' : busiest.n + ' leads with ' + busiest.t + '. ') + 'The rest are dimmed.' : st.pl + ' has fewer than ' + minT + ' targets in this slice, so every receiver is shown dimmed.'); else\n        setRead(4, ")
    # the header line and the in-use marks, every render
    p.sub("function render() { var F = filtered(), O = others(); drawGames(); drawPlayers(O);",
          "function render() { var F = filtered(), O = others(); drawGames(); drawPlayers(O); var busy = F.length !== P.length; $('#dbd-fsum').textContent = busy ? F.length + ' of ' + P.length + ' snaps' : 'all ' + P.length + ' snaps'; $('#dbd-reset').hidden = !busy; host.querySelectorAll('.dbd-filters select').forEach(function (s) { s.classList.toggle('on', !!s.value); }); if (window.dbdMore) window.dbdMore();")
    p.sub("$('#f-q').value = ''; render(", "$('#f-q').value = ''; host.querySelectorAll('.dbd-filters select').forEach(function (s) { s.value = ''; }); render(")

HEAD = """      '<section class="dbd-panel dbd-filters"><div class="dbd-ph"><span class="dbd-lab"><b>Filter the snaps</b> · <span id="dbd-fsum"></span></span><button class="dbd-reset" id="dbd-reset" type="button">Reset</button></div>' +\n"""
TAIL = """      '<div class="dbd-tiles" id="dbd-tiles"></div>' +\n"""

def as_selects(p, segs, game_all, game_fmt, pl_all, pl_fmt):
    """Every filter a select: the down/type/zone/formation groups, the game, the player."""
    p.block("    var SEGS = [", "]]];", "    var SEGS = " + segs + ";\n")
    p.block("    function seg(id, key, opts) {", "requestAnimationFrame(function () { slidePill", """    function seg(id, key, opts) {
      var el = $(id); el.innerHTML = '<select aria-label="' + key + '">' + opts.map(function (o) { return '<option value="' + o[0] + '"' + (st[key] === o[0] ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>';
      el.querySelector('select').onchange = function (e) { st[key] = e.target.value; render(); };
""")
    p.block("    function drawGames() {", "join('');", """    function drawGames() {
      var cur = st.games.size === 1 ? Array.from(st.games)[0] : '';
      $('#dbd-games').innerHTML = '<select id="f-game" aria-label="Game"><option value="">""" + game_all + """</option>' + G.map(function (g) { var r = g.pf > g.pa ? 'W' : g.pf < g.pa ? 'L' : 'T'; return '<option value="' + g.id + '"' + (cur === g.id ? ' selected' : '') + '>' + """ + game_fmt + """ + '</option>'; }).join('') + '</select>';
      $('#f-game').onchange = function (e) { st.games = new Set(e.target.value ? [e.target.value] : []); render(); };
""")
    p.sub("$('#f-pl').innerHTML = '<option value=\"\">All players</option>' + players.map(function (pc) { return '<option value=\"' + esc(pc[0]) + '\">' + esc(pc[0]) + ' (' + pc[1] + ')</option>'; }).join('');",
          "$('#f-pl').innerHTML = '<option value=\"\">" + pl_all + "</option>' + players.map(function (pc) { return '<option value=\"' + esc(pc[0]) + '\">' + " + pl_fmt + " + '</option>'; }).join('');")

def host(p, inner):
    a = p.js.index("      '<div class=\"dbd-gstrip\" id=\"dbd-games\""); b = p.js.index("<div class=\"dbd-tiles\" id=\"dbd-tiles\"></div>' +"); b = p.js.index('\n', b) + 1
    p.js = p.js[:a] + HEAD + inner + TAIL + p.js[b:]
    p.sub("""$('#dbd-players').innerHTML = '<span class="lab">Players</span><button""", """$('#dbd-players').innerHTML = '<button""")

BASE_CSS = '''<style>.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}
.dbd .dbd-filters>.dbd-ph .dbd-lab span{text-transform:none;letter-spacing:0;font:500 13px var(--dbd-body);color:var(--dbd-ink-2)}
.dbd .dbd-filters .dbd-reset[hidden]{display:none}
.dbd .dbd-filters .dbd-hide{display:none}
.dbd .dbd-ch .ct-series .ct-point.is-dim{stroke-opacity:.18}
.dbd .dbd-ch .ct-series .ct-point.is-on{stroke-opacity:1;stroke:var(--k-bg)}
.dbd .dbd-ch-lbl.is-dim{opacity:.4}.dbd .dbd-ch-lbl.is-on{font-weight:700;color:var(--dbd-ink)}
'''
CHEV = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%235d616a'/%3E%3C/svg%3E\")"

def build(name, p, css, note):
    out = H.replace('</head>', BASE_CSS + css + '</style></head>', 1)
    out = out.replace('<script', '<script>window.__DBD_PLAYS = ' + DATA + ';</script><script', 1)
    out = re.sub(r'<script[^>]*src="[^"]*team-explorer\.js[^"]*"[^>]*></script>', lambda m: '<script>' + p.js.replace('</script', '<\\/script') + '</script>', out, count=1)
    i = out.find('<header class="dbd-tpanel"')
    out = out[:i] + '<div class="mock-note">' + note + ' Try J.Jefferson, then scroll to Tendencies for the receiver chart.</div>' + out[i:]
    open(name, 'w').write(no_tracking(out)); print(name, len(out) // 1024, 'KB')

# ---------------- A: a sentence
a = Patch(JS0); common(a)
host(a, """      '<p class="dbd-sentence">Show snaps by <span id="dbd-plwrap"></span> in <span class="dbd-gstrip" id="dbd-games"></span>, on <span id="f-down"></span>, <span id="f-type"></span>, <span id="f-zone"></span>, from <span id="f-sg"></span>, in <select id="f-q" aria-label="Quarter"><option value="">any quarter</option><option value="1">the 1st quarter</option><option value="2">the 2nd quarter</option><option value="3">the 3rd quarter</option><option value="4">the 4th quarter</option><option value="5">overtime</option></select>.</p>' +
      '<select id="f-pl" aria-label="Player"></select><div class="dbd-pstrip dbd-hide" id="dbd-players"></div></section>' +
""")
a.sub("$('#f-pl').onchange", "$('#dbd-plwrap').appendChild($('#f-pl')); $('#f-pl').onchange")
as_selects(a, "[['#f-down', 'down', [['', 'any down'], ['1', '1st down'], ['2', '2nd down'], ['3', '3rd down'], ['4', '4th down']]], ['#f-type', 'type', [['', 'passes and runs'], ['pass', 'passes only'], ['run', 'runs only']]], ['#f-zone', 'zone', [['', 'anywhere on the field'], ['own', DEF ? 'in their half' : 'in their own half'], ['opp', DEF ? 'in our half' : 'in the other half'], ['rz', 'in the red zone']]], ['#f-sg', 'sg', [['', 'any formation'], ['1', 'shotgun'], ['0', 'under center']]]]",
           "all games", "'week ' + g.week + (g.home ? ' vs ' : ' at ') + g.opp + ' (' + r + ' ' + g.pf + '-' + g.pa + ')'", "anyone", "esc(pc[0])")
build('filters-a-sentence.html', a, '''
.dbd .dbd-sentence{margin:0;padding:16px 18px 18px;font:500 19px/2.1 var(--dbd-body);color:var(--dbd-ink-2)}
.dbd .dbd-sentence select{appearance:none;-webkit-appearance:none;font:600 19px var(--dbd-body);color:var(--dbd-ink);background:transparent ''' + CHEV + ''' no-repeat right 4px center;border:0;border-bottom:2px dashed var(--dbd-line-2);border-radius:0;padding:0 18px 1px 2px;cursor:pointer}
.dbd .dbd-sentence .dbd-gstrip{display:inline;padding:0;margin:0;overflow:visible}
.dbd .dbd-sentence select{field-sizing:content}
.dbd .dbd-sentence select:hover{border-bottom-color:var(--dbd-ink-2)}
.dbd .dbd-sentence select.on{color:var(--k-bg);border-bottom:2px solid var(--k-bg);background-color:color-mix(in srgb,var(--k-bg) 8%,transparent)}
@container dbd (max-width:600px){.dbd .dbd-sentence,.dbd .dbd-sentence select{font-size:16px}}
''', '<b>A, a sentence.</b> One sentence that says exactly what you are looking at, each underlined part a dropdown: "Show snaps by anyone in all games, on any down, passes and runs, anywhere on the field, from any formation, in any quarter." A part you change turns the team color. The header says how many snaps are left, with Reset beside it once anything is filtered. The game is one pick at a time.')

# ---------------- B: one row of dropdowns
b = Patch(JS0); common(b)
host(b, """      '<div class="dbd-frow">' +
      '<label class="dbd-fg"><span>Game</span><span class="dbd-gstrip" id="dbd-games"></span></label>' +
      '<label class="dbd-fg"><span>Player</span><select id="f-pl"></select></label>' +
      '<label class="dbd-fg"><span>Down</span><span id="f-down"></span></label>' +
      '<label class="dbd-fg"><span>Type</span><span id="f-type"></span></label>' +
      '<label class="dbd-fg"><span>Field zone</span><span id="f-zone"></span></label>' +
      '<label class="dbd-fg"><span>Formation</span><span id="f-sg"></span></label>' +
      '<label class="dbd-fg"><span>Quarter</span><select id="f-q"><option value="">Any</option><option>1</option><option>2</option><option>3</option><option>4</option><option value="5">OT</option></select></label>' +
      '</div><div class="dbd-pstrip dbd-hide" id="dbd-players"></div></section>' +
""")
as_selects(b, "[['#f-down', 'down', [['', 'Any'], ['1', '1st'], ['2', '2nd'], ['3', '3rd'], ['4', '4th']]], ['#f-type', 'type', [['', 'Any'], ['pass', 'Pass'], ['run', 'Run']]], ['#f-zone', 'zone', [['', 'Any'], ['own', DEF ? 'Their half' : 'Own half'], ['opp', DEF ? 'Our half' : 'Opp half'], ['rz', 'Red zone']]], ['#f-sg', 'sg', [['', 'Any'], ['1', 'Shotgun'], ['0', 'Under center']]]]",
           "All games", "'Wk ' + g.week + (g.home ? ' vs ' : ' at ') + g.opp + ' · ' + r + ' ' + g.pf + '-' + g.pa", "All players", "esc(pc[0]) + ' · ' + pc[1]")
build('filters-b-dropdowns.html', b, '''
.dbd .dbd-frow{display:grid;grid-template-columns:1.5fr 1.4fr repeat(5,minmax(0,1fr));gap:10px;padding:14px}
.dbd .dbd-frow .dbd-fg{display:flex;flex-direction:column;gap:5px;min-width:0}
.dbd .dbd-frow .dbd-fg>span:first-child{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted)}
.dbd .dbd-frow select{appearance:none;-webkit-appearance:none;width:100%;height:36px;padding:0 26px 0 10px;border:1px solid var(--dbd-line-2);border-radius:var(--dbd-r);background:var(--dbd-panel) ''' + CHEV + ''' no-repeat right 10px center;font:500 13px var(--dbd-body);color:var(--dbd-ink);cursor:pointer;text-overflow:ellipsis}
.dbd .dbd-frow select:hover{border-color:var(--dbd-ink-2)}
.dbd .dbd-frow select.on{border-color:var(--k-bg);box-shadow:inset 0 0 0 1px var(--k-bg);background-color:color-mix(in srgb,var(--k-bg) 7%,var(--dbd-panel));font-weight:600}
@container dbd (max-width:900px){.dbd .dbd-frow{grid-template-columns:repeat(4,minmax(0,1fr))}.dbd .dbd-frow .dbd-fg:nth-child(-n+2){grid-column:span 2}}
@container dbd (max-width:520px){.dbd .dbd-frow{grid-template-columns:repeat(2,minmax(0,1fr))}}
''', '<b>B, one row of dropdowns.</b> Seven labeled dropdowns in one row: Game, Player, Down, Type, Field zone, Formation, Quarter. One in use gets a team-color border and tint. The header says how many snaps are left, with Reset beside it once anything is filtered. The game is one pick at a time; on a phone the row folds into two columns.')

# ---------------- C: fewer at once
c = Patch(JS0); common(c)
host(c, """      '<div class="fr"><span class="fl">Games</span><div class="dbd-gstrip" id="dbd-games" role="group" aria-label="Games"></div></div>' +
      '<div class="fr"><span class="fl">Players</span><div class="dbd-pstrip" id="dbd-players" role="group" aria-label="Players"></div></div>' +
      '<details class="dbd-more"><summary>More filters <b id="dbd-morecount"></b></summary><div class="dbd-toolbar">' +
      '<div class="dbd-fg"><span>Down</span><div class="dbd-seg" id="f-down"></div></div>' +
      '<div class="dbd-fg"><span>Type</span><div class="dbd-seg" id="f-type"></div></div>' +
      '<div class="dbd-fg"><span>Zone</span><div class="dbd-seg" id="f-zone"></div></div>' +
      '<div class="dbd-fg"><span>Formation</span><div class="dbd-seg" id="f-sg"></div></div>' +
      '<div class="dbd-fg"><label for="f-q">Quarter</label><select id="f-q"><option value="">All</option><option>1</option><option>2</option><option>3</option><option>4</option><option value="5">OT</option></select></div>' +
      '</div></details><select id="f-pl" hidden></select></section>' +
""")
c.sub("function render() {", "window.dbdMore = function () { var n = ['down', 'type', 'zone', 'sg', 'q'].filter(function (k) { return st[k]; }).length, el = $('#dbd-morecount'); if (el) el.textContent = n ? n + ' on' : ''; var d = host.querySelector('.dbd-more'); if (d && n) d.open = true; };\n    function render() {")
c.sub("host.querySelectorAll('.dbd-filters select').forEach(function (s) { s.value = ''; }); render(", "host.querySelectorAll('.dbd-filters select').forEach(function (s) { s.value = ''; }); var dm = host.querySelector('.dbd-more'); if (dm) dm.open = false; render(")
build('filters-c-fewer.html', c, '''
.dbd .dbd-filters .fr{display:grid;grid-template-columns:76px minmax(0,1fr);gap:14px;align-items:center;padding:10px 14px;border-top:1px solid var(--dbd-line)}
.dbd .dbd-filters>.dbd-ph+.fr{border-top:0}
.dbd .dbd-filters .fl{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted)}
.dbd .dbd-filters .dbd-gstrip,.dbd .dbd-filters .dbd-pstrip{display:flex;flex-wrap:wrap;gap:6px;padding:0;margin:0}
.dbd .dbd-filters .dbd-game,.dbd .dbd-filters .dbd-pchip{all:unset;cursor:pointer;display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 11px;border:1px solid var(--dbd-line);border-radius:999px;background:var(--dbd-panel);font:600 13px/1 var(--dbd-body);color:var(--dbd-ink);white-space:nowrap}
.dbd .dbd-filters .dbd-game .wk{font:600 10px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .dbd-filters .dbd-game .opp{font:700 13px/1 var(--dbd-display);display:inline-flex;align-items:center;gap:4px}
.dbd .dbd-filters .dbd-game .res,.dbd .dbd-filters .dbd-pchip small{font:500 11px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .dbd-filters .dbd-game[aria-pressed="true"],.dbd .dbd-filters .dbd-pchip[aria-pressed="true"]{background:var(--k-bg);border-color:var(--k-bg);color:#fff}
.dbd .dbd-filters .dbd-game[aria-pressed="true"] .wk,.dbd .dbd-filters .dbd-game[aria-pressed="true"] .res{color:rgba(255,255,255,.75)}
.dbd .dbd-filters .dbd-game[aria-pressed="true"] .res b,.dbd .dbd-filters .dbd-pchip[aria-pressed="true"] small{color:var(--k-ac)}
.dbd .dbd-more{border-top:1px solid var(--dbd-line)}
.dbd .dbd-more summary{cursor:pointer;padding:10px 14px;font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-ink-2);list-style:none}
.dbd .dbd-more summary::after{content:" ▾"}.dbd .dbd-more[open] summary::after{content:" ▴"}
.dbd .dbd-more summary b{margin-left:6px;padding:2px 7px;border-radius:999px;background:var(--k-bg);color:#fff;font:600 10px var(--dbd-mono);letter-spacing:0}
.dbd .dbd-more summary b:empty{display:none}
.dbd .dbd-more .dbd-toolbar{padding:0 14px 12px;gap:10px 18px;align-items:center}
.dbd .dbd-more .dbd-fg{flex-direction:row;align-items:center;gap:8px}
.dbd .dbd-filters .dbd-seg.t-tabs{background:var(--dbd-panel-2);border:1px solid var(--dbd-line)}
.dbd .dbd-filters .dbd-seg .t-tabs-pill{background:var(--k-bg)}
@container dbd (max-width:700px){.dbd .dbd-filters .fr{grid-template-columns:minmax(0,1fr);gap:6px}.dbd .dbd-filters .dbd-gstrip,.dbd .dbd-filters .dbd-pstrip{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px}}
''', '<b>C, fewer at once.</b> The game and player chips stay up front as two labeled rows, everything else waits behind "More filters", which shows how many are on ("2 on") and opens by itself when any are. One selected color, the team color. The header says how many snaps are left, with Reset beside it once anything is filtered.')
