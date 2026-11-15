"""Mock: one filter panel for the explorer, and the receiver chart keeping every receiver (Gene, 2026-11-15: "this area looks like a mess
now visually and ux wise"; "show them all but just highlight the selected player more"). Run from mockup-v35/ with _t-min.html
(https://downbydown.com/team/min/), _te.js (the 2.20.0 team-explorer.js) and MIN.json (the Vikings play file). Builds filters.html with
the play data and the patched explorer INLINE, so it opens straight from disk (file://) in the app's view pane."""
import re, json

h = open('_t-min.html').read(); js = open('_te.js').read(); data = open('MIN.json').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)
def sub(a, b):
    global js
    assert js.count(a) == 1, a[:80]; js = js.replace(a, b)

# 1. one panel: a header that says what you're looking at, then Games, Players and Situation as labeled rows
a = js.index("      '<div class=\"dbd-gstrip\" id=\"dbd-games\""); b = js.index("<div class=\"dbd-tiles\" id=\"dbd-tiles\"></div>' +") ; b = js.rindex('\n', 0, b) + 1
js = js[:a] + """      '<section class="dbd-panel dbd-filters"><div class="dbd-ph"><span class="dbd-lab"><b>Filter the snaps</b> · <span id="dbd-fsum"></span></span><button class="dbd-reset" id="dbd-reset" type="button">Reset</button></div>' +
      '<div class="fr"><span class="fl">Games</span><div class="dbd-gstrip" id="dbd-games" role="group" aria-label="Games"></div></div>' +
      '<div class="fr"><span class="fl">Players</span><div class="dbd-pstrip" id="dbd-players" role="group" aria-label="Players"></div></div>' +
      '<div class="fr"><span class="fl">Situation</span><div class="dbd-toolbar">' +
      '<div class="dbd-fg"><span>Down</span><div class="dbd-seg" id="f-down"></div></div>' +
      '<div class="dbd-fg"><span>Type</span><div class="dbd-seg" id="f-type"></div></div>' +
      '<div class="dbd-fg"><span>Zone</span><div class="dbd-seg" id="f-zone"></div></div>' +
      '<div class="dbd-fg"><span>Formation</span><div class="dbd-seg" id="f-sg"></div></div>' +
      '<div class="dbd-fg"><label for="f-q">Quarter</label><select id="f-q"><option value="">All</option><option>1</option><option>2</option><option>3</option><option>4</option><option value="5">OT</option></select></div>' +
      '</div></div><select id="f-pl" hidden></select></section>' +
      '<div class="dbd-tiles" id="dbd-tiles"></div>' +
""" + js[js.index('\n', b) + 1:]
sub("""$('#dbd-players').innerHTML = '<span class="lab">Players</span><button""", """$('#dbd-players').innerHTML = '<button""")
# the header line: how many snaps the filters leave, and who
sub("function render() { var F = filtered(), O = others();",
    "function render() { var F = filtered(), O = others(), busy = F.length !== P.length; $('#dbd-fsum').textContent = (busy ? 'showing ' + F.length + ' of ' + P.length + ' snaps' : 'all ' + P.length + ' snaps') + (st.pl ? ' · ' + st.pl : ''); $('#dbd-reset').hidden = !busy;")
# 2. the receiver chart keeps every receiver when a player is picked, and lights him up
sub("      var tg = {}, carries = {};\n      F.forEach(function (p) { if (!p.pl) return; if (p.k === 'run')",
    "      var tg = {}, carries = {}, F4 = st.pl ? others() : F;  /* a picked player is highlighted among everyone, not plotted alone */\n      F4.forEach(function (p) { if (!p.pl) return; if (p.k === 'run')")
sub("var minT = F.length >= 200 ? 5 : 3;", "var minT = F4.length >= 200 ? 5 : 3;")
sub("d.element.addClass('dbd-pt-' + r.pos); o4.add('dbd-ch-lbl', esc(r.n), d.x, d.y);",
    "d.element.addClass('dbd-pt-' + r.pos); if (st.pl) d.element.addClass(r.n === st.pl ? 'is-on' : 'is-dim'); o4.add('dbd-ch-lbl' + (st.pl ? (r.n === st.pl ? ' is-on' : ' is-dim') : ''), esc(r.n), d.x, d.y);")
sub("var deepest = recs.reduce(function (m, r) { return r.adot > m.adot ? r : m; }), busiest = recs[0];\n        setRead(4, ",
    "var deepest = recs.reduce(function (m, r) { return r.adot > m.adot ? r : m; }), busiest = recs[0], me = st.pl ? recs.filter(function (r) { return r.n === st.pl; })[0] : null, rk = me ? recs.indexOf(me) + 1 : 0;\n        if (st.pl) setRead(4, me ? me.n + ': ' + me.t + ' targets at ' + me.adot + ' air yards each, ' + (rk === 1 ? 'the most' : 'the ' + ['', '', '2nd', '3rd'][rk] || rk + 'th') + ' most of ' + recs.length + ' receivers here. ' + (rk === 1 ? '' : busiest.n + ' leads with ' + busiest.t + '. ') + 'The rest are dimmed.' : st.pl + ' has fewer than ' + minT + ' targets in this slice, so every receiver is shown dimmed.'); else\n        setRead(4, ")
js = js.replace("(rk === 1 ? 'the most' : 'the ' + ['', '', '2nd', '3rd'][rk] || rk + 'th') + ' most of '", "(rk === 1 ? 'the most' : 'the ' + (['', '', '2nd', '3rd'][rk] || rk + 'th') + ' most') + ' of '")
# 3. the data rides in the page, so the file opens from disk
sub("fetch(C.dataUrl, { credentials: 'omit' })", "Promise.resolve({ ok: true, json: function () { return window.__DBD_PLAYS; } })")

CSS = '''<style>.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}
/* one filter panel: labeled rows, one selected color (the team's) everywhere */
.dbd .dbd-filters>.dbd-ph .dbd-lab span{text-transform:none;letter-spacing:0;font:500 13px var(--dbd-body);color:var(--dbd-ink-2)}
.dbd .dbd-filters .fr{display:grid;grid-template-columns:88px minmax(0,1fr);gap:14px;align-items:center;padding:10px 14px;border-top:1px solid var(--dbd-line)}
.dbd .dbd-filters>.dbd-ph+.fr{border-top:0}
.dbd .dbd-filters .fl{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted)}
.dbd .dbd-filters .dbd-gstrip,.dbd .dbd-filters .dbd-pstrip{display:flex;flex-wrap:wrap;gap:6px;padding:0;margin:0}
.dbd .dbd-filters .dbd-game,.dbd .dbd-filters .dbd-pchip{all:unset;cursor:pointer;display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 11px;border:1px solid var(--dbd-line);border-radius:999px;background:var(--dbd-panel);font:600 13px/1 var(--dbd-body);color:var(--dbd-ink);white-space:nowrap}
.dbd .dbd-filters .dbd-game .wk{font:600 10px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .dbd-filters .dbd-game .opp{font:700 13px/1 var(--dbd-display);display:inline-flex;align-items:center;gap:4px}
.dbd .dbd-filters .dbd-game .res,.dbd .dbd-filters .dbd-pchip small{font:500 11px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .dbd-filters .dbd-game:hover,.dbd .dbd-filters .dbd-pchip:hover{border-color:var(--dbd-line-2);background:var(--dbd-panel-2)}
.dbd .dbd-filters .dbd-game[aria-pressed="true"],.dbd .dbd-filters .dbd-pchip[aria-pressed="true"]{background:var(--k-bg);border-color:var(--k-bg);color:#fff}
.dbd .dbd-filters .dbd-game[aria-pressed="true"] .wk,.dbd .dbd-filters .dbd-game[aria-pressed="true"] .res{color:rgba(255,255,255,.75)}
.dbd .dbd-filters .dbd-game[aria-pressed="true"] .res b,.dbd .dbd-filters .dbd-pchip[aria-pressed="true"] small{color:var(--k-ac)}
.dbd .dbd-filters .dbd-toolbar{padding:0;gap:10px 18px;align-items:center}
.dbd .dbd-filters .dbd-fg{flex-direction:row;align-items:center;gap:8px}
.dbd .dbd-filters .dbd-seg.t-tabs{background:var(--dbd-panel-2);border:1px solid var(--dbd-line)}
.dbd .dbd-filters .dbd-seg .t-tabs-pill{background:var(--k-bg)}
.dbd .dbd-filters select{height:28px}
.dbd .dbd-filters .dbd-reset[hidden]{display:none}
@container dbd (max-width:700px){.dbd .dbd-filters .fr{grid-template-columns:minmax(0,1fr);gap:6px}.dbd .dbd-filters .dbd-gstrip,.dbd .dbd-filters .dbd-pstrip{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px}}
/* the receiver chart: the picked player full strength and labeled in bold, everyone else faded */
.dbd .dbd-ch .ct-series .ct-point.is-dim{stroke-opacity:.18}
.dbd .dbd-ch .ct-series .ct-point.is-on{stroke-opacity:1;stroke:var(--k-bg)}
.dbd .dbd-ch-lbl.is-dim{opacity:.4}
.dbd .dbd-ch-lbl.is-on{font-weight:700;color:var(--dbd-ink)}
</style>'''
out = h.replace('</head>', CSS + '</head>', 1)
out = out.replace('<script', '<script>window.__DBD_PLAYS = ' + data + ';</script><script', 1)
out = re.sub(r'<script[^>]*src="[^"]*team-explorer\.js[^"]*"[^>]*></script>', lambda m: '<script>' + js.replace('</script', '<\\/script') + '</script>', out, count=1)
i = out.find('<header class="dbd-tpanel"')
out = out[:i] + ('<div class="mock-note"><b>One filter panel.</b> Games, players and the situation filters become labeled rows in one panel under a header that says what you are looking at ("showing 53 of 447 snaps · J.Jefferson"), with Reset beside it only when something is filtered. Every selected chip and switch uses the team color; the red goes. The situation filters sit on one line with their labels beside them. <b>Receiver chart:</b> pick a player and every receiver stays on the chart, the picked one solid and bold, the rest faded. Click J.Jefferson, then scroll to Tendencies.</div>') + out[i:]
open('filters.html', 'w').write(no_tracking(out))
print('ok', len(out) // 1024, 'KB')
