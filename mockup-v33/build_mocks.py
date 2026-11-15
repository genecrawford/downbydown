"""Mock: the pass and run maps as a playbook page (Gene, 2026-11-15). Run from mockup-v33/ with _t-min.html (https://downbydown.com/team/min/),
_te.js (the 2.18.0 team-explorer.js) and MIN.json (the Vikings play file). schematic.js holds the new passmap() and runmap(); this swaps
them into the explorer and builds schematic.html, the live page running on this folder's data. Serve the folder over http to view."""
import re

h = open('_t-min.html').read(); js = open('_te.js').read(); new = open('schematic.js').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)
a = js.index('    function passmap(F) {'); b = js.index('    function runmap(F) {'); c = js.index('\n    }\n', b) + len('\n    }\n')
open('schematic-te.js', 'w').write(js[:a] + new + js[c:])
CSS = '''<style>.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}</style>'''
out = re.sub(r'"dataUrl":"[^"]*"', '"dataUrl":"MIN.json"', h, count=1)
out = re.sub(r'src="[^"]*team-explorer\.js[^"]*"', 'src="schematic-te.js"', out, count=1)
i = out.find('<header class="dbd-tpanel"')
out = out[:i] + ('<div class="mock-note"><b>The pass and run maps as a playbook page.</b> Scroll to Pass &amp; run map. Passes: the line drawn as O\'s (the center a square) with the quarterback behind it, and a route from the quarterback to each zone the ball went (three sides by four depths: behind the line, short, 10 to 19, 20 plus). A route is as thick as its share of the throws, colored by how those throws went, and labeled completions/targets and EPA per target. Every target stays as a faint dot you can click. Runs: the same line with the tight end spots dashed, and an arrow from the back through each gap, as long as the yards per carry and as wide as the carries, with carries and success rate under each gap. Picking games and filters redraws both.</div>') + out[i:]
open('schematic.html', 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))
print('ok')
