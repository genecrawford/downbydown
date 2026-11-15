"""Mock: touchdowns stand out on the drive chart (Gene, 2026-11-15). Run from mockup-v32/ with _t-min.html (https://downbydown.com/team/min/),
_te.js (the live team-explorer.js) and MIN.json (the Vikings play file) saved by curl. Builds td-a.html and td-b.html: the live page with
its data and explorer served from this folder, the explorer patched with each touchdown treatment. Serve the folder over http to view."""
import re

h = open('_t-min.html').read()
js = open('_te.js').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

# shared: the scoring drives (game|drive keys with a touchdown) and each one's first and last row
PRE = "      var H = Math.max(y + 12, 180), x = function (yd) { return EZ + yd * s; };"
assert PRE in js
TDK = PRE + """
      var tdD = {}; rows.forEach(function (r) { if (r.p && r.p.td) tdD[r.p.g + '|' + r.p.drv] = { a: null, b: null }; });
      rows.forEach(function (r) { if (!r.p) return; var k = r.p.g + '|' + r.p.drv, d = tdD[k]; if (!d) return; if (d.a == null) d.a = r.y; d.b = r.y + RH; });"""

GRID = "      rows.forEach(function (r) {\n        if (r.hdr)"
assert GRID in js
LINE_END = "        g += '<circle cx=\"' + x(los) + '\" cy=\"' + cy + '\" r=\"2.2\" fill=\"#fff\"/></g>';"
assert LINE_END in js

TD = "#4fd18b"
# A: the touchdown row itself. A green band across the field, a thicker line, a ringed dot where the ball crossed, and "TD" filling the end zone on that row
A_ROW = LINE_END.replace("</g>';", "';") + """
        if (p.td) { var tx = x(Math.min(100, end)); g += '<rect x="' + x(0) + '" y="' + (r.y - 1) + '" width="' + (x(100) - x(0)) + '" height="' + (RH + 2) + '" fill="TDC" fill-opacity=".14" pointer-events="none"/>'
          + '<line x1="' + x(los) + '" x2="' + tx + '" y1="' + cy + '" y2="' + cy + '" stroke="TDC" stroke-width="5" stroke-linecap="round"/>'
          + '<circle cx="' + tx + '" cy="' + cy + '" r="4" fill="TDC" stroke="#fff" stroke-width="1.5"/><circle cx="' + x(los) + '" cy="' + cy + '" r="2.2" fill="#fff"/>'
          + '<rect x="' + (Wd - EZ + 3) + '" y="' + (r.y - 2) + '" width="' + (EZ - 6) + '" height="' + (RH + 4) + '" rx="2" fill="TDC"/>'
          + '<text x="' + (Wd - EZ / 2) + '" y="' + (cy + 3.5) + '" fill="#0d2a1b" font-size="10" font-weight="800" text-anchor="middle" letter-spacing=".06em">TD</text>'; }
        g += '</g>';""".replace('TDC', TD)
# B: the whole scoring drive. A green wash behind every snap of the drive, the touchdown line thicker, and a "TD +7" tag in the end zone at the drive's last row
B_BANDS = """      Object.keys(tdD).forEach(function (k) { var d = tdD[k]; g += '<rect x="' + x(0) + '" y="' + (d.a - 2) + '" width="' + (x(100) - x(0)) + '" height="' + (d.b - d.a + 4) + '" fill="TDC" fill-opacity=".12"/>'; });
""".replace('TDC', TD) + GRID
B_ROW = LINE_END.replace("</g>';", "';") + """
        if (p.td) { var tx = x(Math.min(100, end)); g += '<line x1="' + x(los) + '" x2="' + tx + '" y1="' + cy + '" y2="' + cy + '" stroke="TDC" stroke-width="5" stroke-linecap="round"/><circle cx="' + x(los) + '" cy="' + cy + '" r="2.2" fill="#fff"/>'
          + '<rect x="' + (Wd - EZ + 3) + '" y="' + (cy - 9) + '" width="' + (EZ - 6) + '" height="18" rx="2" fill="TDC"/>'
          + '<text x="' + (Wd - EZ / 2) + '" y="' + (cy + 3.5) + '" fill="#0d2a1b" font-size="10" font-weight="800" text-anchor="middle" letter-spacing=".06em">TD</text>'; }
        g += '</g>';""".replace('TDC', TD)

variants = {
    'td-a.html': (js.replace(PRE, TDK).replace(LINE_END, A_ROW),
                  '<b>Option A: the touchdown play stands out.</b> On each touchdown row: a green band across the field, a thicker green line, a ringed dot where the ball crossed the goal line, and a green "TD" block filling the end zone on that row. Everything else stays as today.'),
    'td-b.html': (js.replace(PRE, TDK).replace(GRID, B_BANDS, 1).replace(LINE_END, B_ROW),
                  '<b>Option B: the whole scoring drive stands out.</b> Every snap of a drive that ended in a touchdown sits on a light green wash, so you see the march, not just the last play. The touchdown play is thicker and a green "TD" tag sits in the end zone at the end of the drive.'),
}
CSS = '''<style>.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}</style>'''
for name, (src, say) in variants.items():
    jsn = name.replace('.html', '.js'); open(jsn, 'w').write(src)
    out = re.sub(r'"dataUrl":"[^"]*"', '"dataUrl":"MIN.json"', h, count=1)
    out = re.sub(r'src="[^"]*team-explorer\.js[^"]*"', f'src="{jsn}"', out, count=1)
    i = out.find('<header class="dbd-tpanel"')
    out = out[:i] + f'<div class="mock-note">{say} Pick a game chip to see one game; Week 1 vs GB has four touchdowns.</div>' + out[i:]
    open(name, 'w').write(no_tracking(out.replace('</head>', CSS + '</head>', 1)))
print('ok')
