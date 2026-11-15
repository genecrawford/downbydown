"""Mock: player chips under the filter bar (Gene, 2026-11-15). Run from mockup-v34/ with _t-min.html (https://downbydown.com/team/min/),
_te.js (the 2.19.1 team-explorer.js) and MIN.json (the Vikings play file). Patches the explorer with a player strip, a full table that no
longer collapses, and builds players.html, the live page running on this folder's data. Serve the folder over http to view."""
import re
h = open('_t-min.html').read(); js = open('_te.js').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)
def sub(a, b):
    global js
    assert js.count(a) == 1, a[:70]; js = js.replace(a, b)
# the strip goes under the filter bar, above the numbers; the dropdown leaves the bar
sub("'<div class=\"dbd-fg\"><label for=\"f-pl\">Player</label><select id=\"f-pl\"></select></div>' +", "'<select id=\"f-pl\" hidden></select>' +")
sub("'<div class=\"dbd-tiles\" id=\"dbd-tiles\"></div>' +", "'<div class=\"dbd-pstrip\" id=\"dbd-players\" role=\"group\" aria-label=\"Players\"></div><div class=\"dbd-tiles\" id=\"dbd-tiles\"></div>' +")
# the plays every other filter allows: the strip and the table count from these, so picking a player never hides the rest
sub("    function render() { var F = filtered(); drawGames(); tiles(F); drive(F); passmap(F); runmap(F); tend(F); charts(F); usage(F); detail(F); }",
"""    function others() { var keep = st.pl; st.pl = ''; var o = filtered(); st.pl = keep; return o; }
    function touches(F) {
      var m = {};
      F.forEach(function (p) { if (!p.pl) return; var t = ['comp', 'inc', 'int'].indexOf(p.k) >= 0, c = p.k === 'run' || p.k === 'scramble'; if (!t && !c) return; var u = m[p.pl] || (m[p.pl] = { n: p.pl, pos: p.pos || '', tg: 0, car: 0 }); if (t) u.tg++; else u.car++; });
      return Object.keys(m).map(function (k) { return m[k]; }).sort(function (x, y) { return (y.tg + y.car) - (x.tg + x.car); });
    }
    function drawPlayers(O) {
      var a = touches(O).filter(function (u) { return u.pos !== 'QB' || u.car >= 5; }).slice(0, 10), on = st.pl;
      if (on && !a.some(function (u) { return u.n === on; })) { var me = touches(O).filter(function (u) { return u.n === on; })[0]; if (me) a.push(me); }
      $('#dbd-players').innerHTML = '<span class="lab">Players</span><button class="dbd-pchip" type="button" data-pl="" aria-pressed="' + !on + '">All</button>' + a.map(function (u) {
        var big = u.tg >= u.car ? u.tg + ' tgt' : u.car + ' car';
        return '<button class="dbd-pchip" type="button" data-pl="' + esc(u.n) + '" aria-pressed="' + (on === u.n) + '"><b>' + esc(u.n) + '</b><small>' + (u.pos ? esc(u.pos) + ' · ' : '') + big + '</small></button>';
      }).join('');
    }
    $('#dbd-players').onclick = function (e) { var b = e.target.closest('.dbd-pchip'); if (!b) return; st.pl = b.dataset.pl === st.pl ? '' : b.dataset.pl; $('#f-pl').value = st.pl; render(); };
    function render() { var F = filtered(), O = others(); drawGames(); drawPlayers(O); tiles(F); drive(F); passmap(F); runmap(F); tend(F); charts(F); usage(O); detail(F); }""")
open('players-te.js', 'w').write(js)
CSS = '''<style>.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}
/* the player strip: one line of chips like the game chips; the picked one fills with the team's color */
.dbd .dbd-pstrip{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:2px 0 0}
.dbd .dbd-pstrip .lab{font:700 11px var(--dbd-display);text-transform:uppercase;letter-spacing:.14em;color:var(--dbd-muted);margin-right:4px}
.dbd .dbd-pchip{all:unset;cursor:pointer;display:inline-flex;align-items:baseline;gap:7px;padding:6px 12px;border:1px solid var(--dbd-line);border-radius:999px;background:var(--dbd-panel);font:600 13px/1 var(--dbd-body);color:var(--dbd-ink);white-space:nowrap}
.dbd .dbd-pchip b{font-weight:600}
.dbd .dbd-pchip small{font:500 11px var(--dbd-mono);color:var(--dbd-muted)}
.dbd .dbd-pchip:hover{border-color:var(--dbd-line-2);background:var(--dbd-panel-2)}
.dbd .dbd-pchip[aria-pressed="true"]{background:var(--k-bg,var(--dbd-ink));border-color:var(--k-bg,var(--dbd-ink));color:#fff}
.dbd .dbd-pchip[aria-pressed="true"] small{color:var(--k-ac,#fff)}
@container dbd (max-width:560px){.dbd .dbd-pstrip{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px}}
</style>'''
out = re.sub(r'"dataUrl":"[^"]*"', '"dataUrl":"MIN.json"', h, count=1)
out = re.sub(r'src="[^"]*team-explorer\.js[^"]*"', 'src="players-te.js"', out, count=1)
i = out.find('<header class="dbd-tpanel"')
out = out[:i] + '<div class="mock-note"><b>Player chips under the filters.</b> The ten players with the most touches in the plays you have picked (games, down, type, zone, formation, quarter) sit in one row above the numbers, each with his position and targets or carries. Pick one and the whole page follows him, the chip fills with the team color, and every other chip stays so switching is one click; pick him again or All to clear. The Player dropdown leaves the filter bar. Further down, Who gets the ball keeps every player and highlights the one picked instead of shrinking to him.</div>' + out[i:]
open('players.html', 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))
print('ok')
