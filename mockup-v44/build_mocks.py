"""Mock: the share pack by email (SCOPE §69). Run from mockup-v44/. Reuses mockup-v43's items (Week 9, real numbers) and the Week 11
keys it fetched (Week 10 finishes tonight). Builds email.html (the email as it lands), tab.html (the new box on the Share pack tab)
and index.html. Opens from disk; the cards load from downbydown.com."""
import os, html, runpy
E = html.escape
here = os.getcwd(); os.chdir('../mockup-v43'); ns = runpy.run_path('build_mocks.py'); os.chdir(here)
items, T, S, K, nick, slug, xlen, r = ns['items'], ns['T'], ns['S'], ns['K'], ns['nick'], ns['slug'], ns['xlen'], ns['r']
SITE = 'https://downbydown.com'
for it in items:  # PHP rounds 42.5 up, as the live tab shows
    for f in ('title', 'post'): it[f] = it[f].replace('by 42 points', 'by 43 points').replace('in the fourth moved', 'in the fourth quarter moved')
num = {'swing': 1, 'snaps': 2, 'offense': 3, 'targets': 4}
card = {'swing': SITE + '/?card=game-2026_09_DEN_CAR', 'snaps': SITE + '/?card=pack-week-9-snaps', 'offense': SITE + '/?card=pack-week-9-offense', 'targets': SITE + '/?card=pack-week-9-targets'}

INK = '#121417'; MUTED = '#6b6f78'; RED = '#cb2026'; BG = '#f4f1ea'; LINE = '#e3dfd5'
font = "font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif"
def block(lab, text, extra=''):
    return (f'<tr><td style="padding:0 0 10px"><div style="{font};font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:{MUTED};padding:0 0 4px">{lab}{extra}</div>'
            f'<div style="{font};font-size:15px;line-height:1.5;color:{INK};background:#f6f5f1;border:1px solid {LINE};border-radius:6px;padding:10px 12px">{E(text)}</div></td></tr>')
rows = ''
for it in items:
    rows += (f'<tr><td style="padding:28px 28px 8px;border-top:1px solid {LINE}"><table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
             f'<tr><td style="{font};font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:{RED};padding:0 0 10px">{num[it["k"]]} · {E(it["h"])}</td></tr>'
             f'<tr><td style="padding:0 0 14px"><a href="{card[it["k"]]}"><img src="{card[it["k"]]}" width="544" alt="{E(it["h"])}" style="display:block;width:100%;max-width:544px;height:auto;border:1px solid {LINE}"></a></td></tr>'
             + block('Reddit title', it['title']) + block('Reddit comment, with the link', it['comment'])
             + block('X and Bluesky', it['post'], f' <span style="font-weight:400;letter-spacing:0;text-transform:none">· {xlen(it["post"])} of 280</span>')
             + f'<tr><td style="{font};font-size:14px;color:{MUTED};padding:2px 0 0">Fits: {E(" · ".join(it["fits"]))} · <a href="{it["link"]}" style="color:{RED};font-weight:600">Open the page</a></td></tr></table></td></tr>')
keys = ''
for g in S['weeks']['11']:
    a, h = g['away'], g['home']; base = f"{SITE}/game/2026-week-11-{slug(nick(a))}-at-{slug(nick(h))}/"
    lines = ''
    for t in (a, h):
        k = K.get(g['id'] + ':' + t)
        if not k: continue
        link = base if t == a else base + slug(nick(h)) + '/'
        lines += (f'<div style="{font};font-size:14px;line-height:1.5;color:{INK};padding:6px 0 0"><span style="color:{MUTED}">{r(t)}</span> · <b>{E(k[0])}.</b> {E(k[1])} '
                  f'<a href="{link}" style="color:{RED};font-weight:600">Link</a></div>')
    keys += f'<div style="padding:12px 0;border-top:1px solid {LINE}"><div style="{font};font-size:15px;font-weight:700;color:{INK}">{E(nick(a))} at {E(nick(h))}</div>{lines}</div>'

subject = 'Week 9 share pack: Broncos’ 43-point swing, Singletary’s 93%'
email = f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>{E(subject)}</title>
<style>.mock-note{{max-width:600px;margin:16px auto;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;{font};font-size:13px;line-height:1.5;color:#3b3b3b}}</style></head>
<body style="margin:0;background:{BG}">
<div class="mock-note"><b>Mock: the email as it lands, Tuesday 7:00 AM.</b> To your address only, through Brevo, never the list. Subject: “{E(subject)}”. Week 9’s numbers, with the Week 11 keys standing in for Week 10 (Week 10 finishes tonight). On a phone, long-press any text to copy it; tap an image to open it full size and save it.</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{BG}"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid {LINE}">
<tr><td style="padding:22px 28px 16px;border-bottom:5px solid {RED}"><img src="{SITE}/wp-content/plugins/down-by-down/assets/card-logo.png" height="30" alt="Down x Down" style="display:block;height:30px"></td></tr>
<tr><td style="padding:24px 28px 20px"><div style="{font};font-size:26px;font-weight:800;line-height:1.15;color:{INK}">Week 9 share pack</div>
<div style="{font};font-size:15px;line-height:1.55;color:#3b4255;padding-top:8px">Five things to post this week. Long-press any text to copy it; tap an image to open it full size and save it. On Reddit, post the image with the title, then the comment with the link.</div></td></tr>
{rows}
<tr><td style="padding:28px 28px 16px;border-top:1px solid {LINE}"><div style="{font};font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:{RED};padding:0 0 4px">5 · Keys to the game, Week 11</div>
<div style="{font};font-size:14px;line-height:1.5;color:#3b4255;padding:0 0 4px">Each team’s biggest edge against its next opponent, for its own subreddit. The game cards are on the Share pack tab.</div>{keys}</td></tr>
<tr><td style="padding:20px 28px 28px;border-top:1px solid {LINE}"><a href="https://downbydown.com/wp-admin/admin.php?page=down-by-down-share" style="display:inline-block;background:{RED};color:#ffffff;{font};font-size:15px;font-weight:700;text-decoration:none;padding:12px 20px;border-radius:6px">Open the Share pack</a>
<div style="{font};font-size:13px;color:{MUTED};padding-top:14px">Sent to you only, from downbydown.com. Turn this email off on the Share pack tab.</div></td></tr>
</table></td></tr></table></body></html>'''
open('email.html', 'w').write(email)

# the tab's new box, above the week picker
v43 = open('../mockup-v43/index.html').read()
box = '''<div class="dbd-box" style="max-width:1180px"><h2><span>The Tuesday email to you</span><small>Each new pack, Tuesday 7:00 AM Eastern, through Brevo to one address</small></h2>
<table class="dbd-rows" style="width:100%;border-collapse:collapse"><tbody>
<tr><td style="width:180px;color:#50575e;padding:8px 16px">Send it</td><td style="padding:8px 16px"><label><input type="checkbox" checked> Email me each week’s pack</label></td></tr>
<tr><td style="color:#50575e;padding:8px 16px;border-top:1px solid #f0f0f1">Send to</td><td style="padding:8px 16px;border-top:1px solid #f0f0f1"><input type="email" value="you@yourdomain.com" style="width:320px;max-width:100%"> <span style="color:#646970">Defaults to the test address on Week in review.</span></td></tr>
<tr><td style="color:#50575e;padding:8px 16px;border-top:1px solid #f0f0f1">Status</td><td style="padding:8px 16px;border-top:1px solid #f0f0f1"><span class="dbd-dot ok"></span>Week 9 pack sent Tuesday, Nov 10, 7:00 AM. Week 10 pack booked for Tuesday, Nov 17, 7:00 AM.</td></tr>
</tbody></table>
<div class="dbd-foot"><button type="button" class="button button-primary">Save</button><button type="button" class="button">Send this week’s pack now</button><span class="description">Sends the week shown below to the address above.</span></div></div>'''
note = '<div class="mock-note" style="max-width:1180px"><b>Mock: the new box at the top of the Share pack tab.</b> On by default, to the Week in review test address unless you set another. The status line says when the last pack went and when the next one is booked; after a failed send it shows the error and a Try again button. If Brevo isn’t set up, the box says so and links to Week in review. The pack below is unchanged.</div>'
i = v43.index('<div class="mock-note"'); j = v43.index('</div>', i) + 6
tab = v43[:i] + note + v43[j:]
k = tab.index('<div class="sp-head">')
tab = tab[:k] + box + tab[k:]
open('tab.html', 'w').write(tab)

open('index.html', 'w').write(f'''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pack email mock</title>
<style>body{{margin:0;background:{BG};color:{INK};{font};font-size:15px;line-height:1.55}}main{{max-width:680px;margin:0 auto;padding:32px 16px}}h1{{font-size:24px;margin:0 0 8px}}li{{margin:0 0 10px}}a{{color:#b4261f;font-weight:600}}</style></head><body><main>
<h1>The share pack by email</h1><p>Two views, each opening from disk.</p><ol>
<li><a href="email.html">The email</a>: what lands in your inbox Tuesday at 7:00 AM.</li>
<li><a href="tab.html">The Share pack tab</a>: the new box at the top that turns it on, sets the address and sends one now.</li></ol></main></body></html>''')
print('ok', subject)
