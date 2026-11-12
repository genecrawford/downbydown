"""Mock: the roster's depth slot (QB1) beside each name, cleaner (Gene, 2026-11-12). Run from mockup-v28/ with _tp-min.html saved from
https://downbydown.com/team/min/players/ (curl). Two options, A and B, each a copy of the live roster."""
import re, html
h = open('_tp-min.html').read()
def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)
CSS = '''<style>
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}
/* A: a small chip after the name, a gap away; the starter's chip in ink, a backup's muted */
.dbd .optA .dbd-nmi{gap:10px}
.dbd .optA .dbd-slot{flex:0 0 auto;margin-left:2px;padding:2px 5px 1px;border:1px solid var(--dbd-line-2);border-radius:3px;font:600 10px/1.2 var(--dbd-mono);letter-spacing:.04em;color:var(--dbd-muted);cursor:help}
.dbd .optA .dbd-slot.s1{border-color:var(--dbd-ink);color:var(--dbd-ink)}
/* B: the slot on a second line under the name, in words */
.dbd .optB .dbd-nmi{display:flex;align-items:center;gap:10px}
.dbd .optB .dbd-nmt{display:flex;flex-direction:column;min-width:0;line-height:1.2}
.dbd .optB .dbd-nmt small{margin:2px 0 0;font:500 11px/1.2 var(--dbd-body);color:var(--dbd-muted)}
.dbd .optB .dbd-nmt small b{font-weight:600;color:var(--dbd-ink-2)}
</style>'''
i = h.find('<section class="dbd-panel dbd-roster"'); j = h.find('</section>', i) + len('</section>'); R = h[i:j]
nick = 'Vikings'
ORD = {1: 'Starter', 2: '2nd', 3: '3rd', 4: '4th', 5: '5th', 6: '6th'}
def a_row(m):
    slot = m.group(2); pos, n = re.match(r'([A-Z]+)(\d)', slot).groups(); n = int(n)
    tip = f'{"First" if n == 1 else ORD[n]} on the {nick} depth chart at {pos}' if n > 1 else f'First on the {nick} depth chart at {pos}: the starter'
    return f'{m.group(1)}<span class="dbd-slot{" s1" if n == 1 else ""}" title="{html.escape(tip)}">{slot}</span>'
def b_row(m):
    slot = m.group(2); pos, n = re.match(r'([A-Z]+)(\d)', slot).groups(); n = int(n)
    words = f'<b>Starter</b> · {pos}1' if n == 1 else f'{ORD[n]} on the depth chart · {slot}'
    return f'<span class="dbd-nmt">{m.group(1)}<small>{words}</small></span>'
pat = r'(<a href="https://downbydown\.com/player/[^"]+">[^<]+</a>)<small>([A-Z]+\d)</small>'
A = re.sub(pat, a_row, R).replace('<section class="dbd-panel dbd-roster"', '<section class="dbd-panel dbd-roster optA"', 1)
B = re.sub(pat, b_row, R).replace('<section class="dbd-panel dbd-roster"', '<section class="dbd-panel dbd-roster optB"', 1)
note = lambda t: f'<div class="mock-note"><b>{t}</b></div>'
out = h[:i] + note('Option A: a small chip after the name, a gap away. The starter\'s chip is dark, a backup\'s is light; hovering says "First on the Vikings depth chart at QB: the starter".') + A \
      + note('Option B: the slot on a second line under the name, in words: "Starter · QB1", "2nd on the depth chart · QB2".') + B \
      + note('Today, for comparison.') + R + h[j:]
out = out.replace('</head>', CSS + '</head>', 1)
out = re.sub(r'<section class="dbd-panel dbd-tabbed dbd-lu".*?</section>', '', out, count=1, flags=re.S)  # the lineup out of the way
open('roster-slot.html', 'w').write(no_tracking(out))
print('rows', len(re.findall(pat, R)))
