"""Mock for SCOPE §66, more signups (Gene, 2026-11-15). Run from mockup-v41/ with _game.html and _combine.html saved from the live
game and combine pages (curl). Builds:
  game.html         the live game page with the signup strip at the end (the in-season line), and its "on the list" state
  combine.html      the live combine board with the strip's February-to-April line
  email.html        the Tuesday email's swing section with the standout chart, the main button and the new forward line
  board-email.html  the combine board email
  index.html        the four, with notes
Static: opens from disk; images are in assets/."""
import re

def no_tracking(x):
    x = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', x)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', x, flags=re.S)

STRIP_CSS = '''<style>.t-stagger-line,.dbd .dbd-panel{opacity:1!important;filter:none!important;transform:none!important;animation:none!important}
.dbd .mock-note{margin:0 0 16px;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:400 13px/1.5 var(--dbd-body);color:#3b3b3b}.dbd .mock-note b{font-weight:600}
.dbd .mock-note button{margin-left:6px;font:600 12px var(--dbd-body);padding:3px 8px;border:1px solid #c9a400;background:#fff;cursor:pointer}
.dbd .dbd-sign .sg{padding:16px 18px 18px}
.dbd .dbd-sign .sg p{margin:0 0 12px;font:400 15px/1.5 var(--dbd-body);color:var(--dbd-ink-2);max-width:62ch}
.dbd .dbd-sign form{display:flex;gap:8px;flex-wrap:wrap;max-width:520px}
.dbd .dbd-sign input{flex:1 1 240px;height:44px;padding:0 12px;border:1px solid var(--dbd-line-2);border-radius:6px;background:#fff;font:400 15px var(--dbd-body);color:var(--dbd-ink)}
.dbd .dbd-sign button{height:44px;padding:0 20px;border:0;border-radius:6px;background:var(--dbd-red);color:#fff;font:700 15px var(--dbd-display);letter-spacing:.1em;text-transform:uppercase;cursor:pointer}
.dbd .dbd-sign .on{display:none;margin:0;font:400 15px/1.5 var(--dbd-body);color:var(--dbd-ink-2)}
.dbd .dbd-sign.is-on form,.dbd .dbd-sign.is-on .pitch{display:none}.dbd .dbd-sign.is-on .on{display:block}
</style>'''

def strip(label, sub, sentence):
    return ('<section class="dbd-panel dbd-sign" id="dbd-sign"><div class="dbd-ph"><span class="dbd-lab"><b>' + label + '</b> · ' + sub + '</span></div>'
            '<div class="sg"><p class="pitch">' + sentence + '</p><form onsubmit="return false"><input type="email" placeholder="Your email" aria-label="Your email"><button type="submit">Sign me up</button></form>'
            '<p class="on"><b>You’re on the list.</b> The next one arrives Tuesday morning.</p></div></section>')

def page(src, out, note, s):
    h = open(src).read()
    h = h.replace('</head>', STRIP_CSS + '</head>', 1)
    i = h.rfind('</main>'); h = h[:i] + s + h[i:]
    m = re.search(r'<main[^>]*>', h); h = h[:m.end()] + '<div class="mock-note">' + note + '</div>' + h[m.end():]
    open(out, 'w').write(no_tracking(h))

page('_game.html', 'game.html',
     '<b>Mock: the signup strip on a game page.</b> Scroll to the very end: it sits after the news and videos, the same form as under the weekly posts. This is the September-to-February line. <button type="button" onclick="document.querySelector(\'.dbd-sign\').classList.toggle(\'is-on\');document.getElementById(\'dbd-sign\').scrollIntoView({block:\'center\'})">Show it after signing up</button> <button type="button" onclick="document.getElementById(\'dbd-sign\').scrollIntoView({block:\'center\'})">Jump to it</button>',
     strip('The week in review', 'every Tuesday', 'The scores, the biggest swings and the players who rose, one email after every week. Free.'))
page('_combine.html', 'combine.html',
     '<b>Mock: the strip on the combine board, February 15 to April 30.</b> At the very end, after the link to the draft board. <button type="button" onclick="document.getElementById(\'dbd-sign\').scrollIntoView({block:\'center\'})">Jump to it</button>',
     strip('The combine and draft boards', 'the week they land', 'Every drill and every pick, the week the results are in. Then the week in review again from September. Free.'))

# ---- the emails, in the Tuesday email's own styles (DBD_Email::compose)
INK, MUT, RED, LINE, PANEL, BG = '#121417', '#5d616a', '#cb2026', '#e3dfd6', '#fffdf8', '#efebe2'
SANS = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif"; HEAVY = "'Arial Black','Helvetica Neue',Arial,sans-serif"
def shell(title, date, inner, note, top_img=''):
    return ('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' + title + '</title>'
        '<style>body{margin:0}.mock-note{max-width:600px;margin:16px auto 0;padding:10px 14px;border:1px dashed #c9a400;background:#fffbe6;font:13px/1.5 ' + SANS + ';color:#3b3b3b;box-sizing:border-box}'
        '.new{outline:2px dashed #c9a400;outline-offset:6px}</style></head>'
        f'<body style="margin:0;padding:0;background:{BG}"><div class="mock-note">' + note + '</div>'
        f'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{BG}"><tr><td align="center" style="padding:20px 10px">'
        f'<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:{PANEL};border:1px solid #dcd8ce">'
        f'<tr><td style="background:#0f1311;padding:16px 24px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>'
        f'<td style="font:900 20px/1 {HEAVY};letter-spacing:.03em;color:#ffffff">DOWN <span style="color:{RED}">X</span> DOWN</td>'
        f'<td align="right" style="font:12px/1.2 {SANS};color:#b9bcc2">{date}</td></tr></table></td></tr>'
        + top_img + f'<tr><td style="padding:26px 28px 8px">' + inner + '</td></tr>'
        f'<tr><td style="background:#f4f1ea;padding:20px 28px;border-top:1px solid {LINE};font:12px/1.6 {SANS};color:{MUT}">'
        f'You’re getting this because you signed up for the week in review at <a href="#" style="color:{MUT}">downbydown.com</a>.<br>'
        f'<a href="#" style="color:{MUT}">View in browser</a> · <a href="#" style="color:{MUT}">Unsubscribe</a><br>Down by Down · 808 Lady Street, Suite D, Columbia, SC 29201<br>Not affiliated with the NFL. Data: nflverse.</td></tr>'
        '</table></td></tr></table></body></html>')
H2 = f'margin:26px 0 10px;font:900 18px/1.2 {HEAVY};text-transform:uppercase;color:{INK}'
P = f'margin:0 0 14px;font:16px/1.55 {SANS};color:{INK}'
A = f'color:{RED};font-weight:600'
def button(t): return f'<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 18px"><tr><td style="background:{RED};border-radius:4px"><a href="#" style="display:inline-block;padding:13px 22px;font:700 15px/1 {SANS};color:#ffffff;text-decoration:none">{t}</a></td></tr></table>'
def forward(where): return (f'<p class="new" style="margin:0 0 26px;padding:14px 16px;background:#f4f1ea;border:1px solid {LINE};font:15px/1.5 {SANS};color:{INK}">'
                            f'<b>Know someone who’d like this?</b> Forward it. They can sign up at <a href="#" style="{A}">{where}</a>.</p>')

swing = (f'<h2 style="{H2}">The swing and the play</h2>'
    f'<p style="{P}">The biggest swing of the week came in the Broncos’ game against the Panthers, with 1:24 left in the fourth quarter: B.Young sacked at DEN 44 for -8 yards (N.Bonitto), fumbled, and Bonitto recovered. It moved the Broncos’ chance of winning by 43 points, and they won 26-21. <a href="#" style="{A}">Open the game</a></p>'
    f'<p class="new" style="margin:0 0 16px"><a href="#"><img src="assets/swing.png" width="544" alt="Broncos 26, Panthers 21: the Broncos’ win probability, down to 28%, with the biggest swing at Q4 1:24" style="display:block;width:100%;height:auto;border:1px solid {LINE}"></a></p>'
    f'<p style="{P}">The best single play by expected points was the Texans’ against the Chargers, with 14:23 left in the fourth quarter: C.Stroud pass deep middle to K.Boutte for 63 yards, TOUCHDOWN. It was worth +6.35 EPA. <a href="#" style="{A}">Open the game</a></p>'
    f'<p style="{P};color:{MUT}">… the rest of the email as today …</p>'
    + button('Every play from Week 9') + forward('downbydown.com'))
open('email.html', 'w').write(shell('Week 9 in review: Packers stun Patriots from 40%', 'Tuesday, November 11',
    f'<h1 style="margin:0 0 16px;font:900 26px/1.15 {HEAVY};text-transform:uppercase;color:{INK}">Week 9 in review: Packers stun Patriots from 40%</h1><p style="{P};color:{MUT}">… the opening, the scores …</p>' + swing,
    '<b>Mock: the Tuesday email, with the two new pieces outlined.</b> The swing game’s card under "The swing and the play" (it’s in the weekly post on the site too), and the forward line under the main button. Everything else stays as it is.'))

rows = [('Taylen Green', 'QB', 'Arkansas', 100), ('Eli Stowers', 'TE', 'Vanderbilt', 98), ('Deion Burks', 'WR', 'Oklahoma', 97), ('Jeff Caldwell', 'WR', 'Cincinnati', 97), ('Malachi Lawrence', 'EDGE', 'Central Florida', 97),
        ('Enrique Cruz', 'OT', 'Kansas', 96), ('Kenyon Sadiq', 'TE', 'Oregon', 96), ('Monroe Freeling', 'OT', 'Georgia', 95), ('Mike Washington Jr.', 'RB', 'Arkansas', 95), ('Dani Dennis-Sutton', 'EDGE', 'Penn St.', 94)]
tops = ''.join(f'<tr><td style="padding:7px 0;border-top:1px solid {LINE};font:13px {SANS};color:{MUT};width:26px">{i+1}</td><td style="padding:7px 0;border-top:1px solid {LINE};font:600 15px {SANS};color:{INK}">{n}<span style="font-weight:400;color:{MUT}"> · {p}, {s}</span></td><td align="right" style="padding:7px 0;border-top:1px solid {LINE};font:700 15px {SANS};color:{INK}">{c}</td></tr>' for i, (n, p, s, c) in enumerate(rows))
lead = lambda k, v, w: f'<td width="33%" valign="top" style="padding:12px 10px;border:1px solid {LINE};background:#ffffff"><div style="font:600 11px {SANS};letter-spacing:.08em;text-transform:uppercase;color:{MUT}">{k}</div><div style="margin:4px 0 2px;font:900 22px/1 {HEAVY};color:{INK}">{v}</div><div style="font:13px/1.35 {SANS};color:{MUT}">{w}</div></td>'
board = (f'<h1 style="margin:0 0 12px;font:900 26px/1.15 {HEAVY};text-transform:uppercase;color:{INK}">The 2026 combine results</h1>'
    f'<p style="{P}">319 players tested. Each drill is ranked against every player at the position since 2000, and the combine score puts them together.</p>'
    f'<table role="presentation" width="100%" cellpadding="0" cellspacing="6" style="margin:0 -6px 8px;width:calc(100% + 12px)"><tr>'
    + lead('Fastest 40', '4.26', 'Brenen Thompson, WR, Mississippi St.') + lead('Top vertical', '45.5"', 'Eli Stowers, TE, Vanderbilt') + lead('Most bench reps', '37', 'David Gusta, DT, Kentucky') + '</tr></table>'
    f'<h2 style="{H2}">The top ten by combine score</h2><table role="presentation" width="100%" cellpadding="0" cellspacing="0">{tops}</table>'
    + button('See every drill') + forward('downbydown.com'))
open('board-email.html', 'w').write(shell('The 2026 combine results', 'Tuesday, March 3', board,
    '<b>Mock: a board email.</b> It goes out the Tuesday after a new combine’s results land (and again after the draft, with the first pick, the team with the most picks and the first ten picks). Same template as the Tuesday email; the board page is its "View in browser".',
    top_img='<tr><td style="padding:0"><a href="#"><img src="assets/combine.png" width="600" alt="The 2026 combine" style="display:block;width:100%;height:auto;border:0"></a></td></tr>'))

open('index.html', 'w').write('''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Signups mock</title>
<style>body{margin:0;background:#f4f1ea;color:#121417;font:15px/1.55 -apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}main{max-width:680px;margin:0 auto;padding:32px 16px}
h1{font:900 24px/1.2 'Arial Black',Arial,sans-serif;text-transform:uppercase;margin:0 0 8px}ol{padding-left:20px}li{margin:0 0 10px}a{color:#b4261f;font-weight:600}</style></head><body><main>
<h1>More signups: the mock</h1><p>SCOPE §66. Four views, each opening from disk.</p><ol>
<li><a href="game.html">A game page</a>: the signup strip at the very end, with the September-to-February line, and how it looks once someone's on the list.</li>
<li><a href="combine.html">The combine board</a>: the strip's February-to-April line.</li>
<li><a href="email.html">The Tuesday email</a>: the swing game's chart and the forward line.</li>
<li><a href="board-email.html">A board email</a>: the new combine email (the draft one has the same shape).</li></ol></main></body></html>''')
print('ok')
