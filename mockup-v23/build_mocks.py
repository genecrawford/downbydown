"""SCOPE §48 and §49 mocks. Run from mockup-v23/ after news_data.py:  python3 build_mocks.py
Inputs saved from the live site on 2026-11-03: _post8.json (the Week 8 post via the REST API), _post8.html (its page),
_tp-min.html (the Vikings players page), _team-min.html (the Vikings offense page), news.json, ../divi/home-newsletter.html."""
import json, re, html, datetime
e = html.escape
POST = json.load(open('_post8.json'))[0]
NEWS = json.load(open('news.json')); NOW = NEWS['built']
INK, MUT, RED, REDINK, LINE, PANEL, BG = '#121417', '#5d616a', '#cb2026', '#b4261f', '#e3dfd6', '#fffdf8', '#efebe2'
SANS = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif"
HEAVY = "'Arial Black','Helvetica Neue',Arial,sans-serif"
COND = "'Arial Narrow','Helvetica Neue',Arial,sans-serif"
NOTE = ('<p style="max-width:640px;margin:0 auto 16px;font:13px/1.5 ' + SANS + ';color:#555">{}</p>')

def no_tracking(h):
    """The copied live pages lose the site's analytics, so opening a mock never counts as a visit."""
    h = re.sub(r'<script[^>]*googletagmanager\.com[^>]*>\s*</script>', '', h)
    return re.sub(r'<script[^>]*>(?:(?!</script>).)*?(?:gtag\(|dataLayer)(?:(?!</script>).)*</script>', '', h, flags=re.S)

# ---------- the email (§48) ----------
def email_body(c):
    """The post body with every style inline, laid out in tables: what the plugin would hand Brevo."""
    c = re.sub(r'</?div[^>]*>', '', c)
    # the scores table: one cell a game, the tag under the score
    def sc(m):
        rows = re.findall(r'<tr>\s*<td>(.*?)</td>\s*<td class="tg">(.*?)</td>\s*</tr>', m.group(0), re.S)
        out = ''
        for a, tg in rows:
            a = re.sub(r'<img[^>]*src="([^"]*)"[^>]*>', r'<img src="\1" width="22" height="22" alt="" style="vertical-align:-6px;margin-right:8px;border:0">', a)
            a = a.replace('<a ', f'<a style="color:{INK};text-decoration:none;font:400 15px/1.4 {SANS}" ')
            out += (f'<tr><td style="padding:9px 0;border-top:1px solid {LINE}">{a}'
                    f'<div style="margin:2px 0 0 30px;font:13px/1.4 {SANS};color:{MUT}">{tg}</div></td></tr>')
        return f'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;border-bottom:1px solid {LINE}">{out}</table>'
    c = re.sub(r'<table class="sc">.*?</table>', sc, c, flags=re.S)
    # player rows: photo, then name, context and line stacked
    def pl(m):
        rows = re.findall(r'<tr>\s*<td class="ph">(.*?)</td>\s*<td>(.*?)</td>\s*<td class="ln">(.*?)</td>\s*</tr>', m.group(0), re.S)
        out = ''
        for ph, nm, ln in rows:
            src = re.search(r'src="([^"]*)"', ph)
            img = f'<img src="{src.group(1)}" width="40" height="40" alt="" style="display:block;border-radius:20px;background:#efebe2;border:0">' if src else ''
            nm = nm.replace('<a ', f'<a style="color:{INK};text-decoration:none" ').replace('<small>', f'<br><span style="font:12px/1.4 {SANS};color:{MUT}">').replace('</small>', '</span>')
            ln = re.sub(r'<span>(.*?)</span>', rf' <b style="color:{INK}">\1</b>', ln)
            out += (f'<tr><td width="52" valign="top" style="padding:10px 0;border-top:1px solid {LINE}">{img}</td>'
                    f'<td valign="top" style="padding:10px 0;border-top:1px solid {LINE};font:600 15px/1.4 {SANS};color:{INK}">{nm}'
                    f'<div style="margin-top:3px;font:400 14px/1.4 {SANS};color:#3b4255">{ln}</div></td></tr>')
        return f'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;border-bottom:1px solid {LINE}">{out}</table>'
    c = re.sub(r'<table class="pl">.*?</table>', pl, c, flags=re.S)
    c = re.sub(r'<p class="next">(.*?)</p>', lambda m: '<p style="margin:0 0 8px;font:700 17px/1.4 ' + SANS + f';color:{INK}">' +
               re.sub(r'<img[^>]*src="([^"]*)"[^>]*>', r'<img src="\1" width="26" height="26" alt="" style="vertical-align:-7px;margin:0 6px;border:0">', m.group(1)) + '</p>', c, flags=re.S)
    c = re.sub(r'<p class="full">.*?</p>', '', c, flags=re.S)
    c = re.sub(r'<h2>(.*?)</h2>', rf'<h2 style="margin:26px 0 10px;padding-top:14px;border-top:2px solid {INK};font:700 19px/1.2 {COND};letter-spacing:.05em;text-transform:uppercase;color:{INK}">\1</h2>', c)
    c = re.sub(r'<p( class="open")?>', lambda m: f'<p style="margin:0 0 14px;font:{"17px/1.55" if m.group(1) else "16px/1.6"} {SANS};color:{INK}">', c)
    c = re.sub(r'<a (?!style)', f'<a style="color:{REDINK};text-decoration:underline" ', c)
    return c

title = html.unescape(POST['title']['rendered'])
card = POST['_embedded']['wp:featuredmedia'][0]['source_url']
opening = html.unescape(re.sub(r'<[^>]+>', '', re.search(r'<p class="open">(.*?)</p>', POST['content']['rendered'], re.S).group(1)))
first = re.match(r'(.+?\.)\s', opening + ' ').group(1)
FROM = 'Down by Down &lt;week@downbydown.com&gt;'

def shell(sub, inner, pre, date='Tuesday, November 3', view=True):
    return f'''<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{BG}"><tr><td align="center" style="padding:20px 10px">
<span style="display:none;max-height:0;overflow:hidden">{e(pre)}</span>
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:{PANEL};border:1px solid rgba(22,30,54,.12)">
<tr><td style="background:#0f1311;padding:16px 24px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
<td style="font:900 20px/1 {HEAVY};letter-spacing:.03em;color:#fff">DOWN <span style="color:{RED}">X</span> DOWN</td>
<td align="right" style="font:12px/1.2 {SANS};color:#b9bcc2">{date}</td></tr></table></td></tr>
{inner}
<tr><td style="background:#f4f1ea;padding:20px 28px;border-top:1px solid {LINE};font:12px/1.6 {SANS};color:{MUT}">
{'You&#39;re getting this because you signed up for the week in review at' if view else 'Someone, hopefully you, signed up for the week in review at'} <a href="https://downbydown.com/" style="color:{MUT}">downbydown.com</a>.<br>
{(f'<a href="{POST[chr(108)+"ink"]}" style="color:{MUT}">View in browser</a> · <a href="#" style="color:{MUT}">Unsubscribe</a><br>') if view else ''}
Down by Down · 808 Lady Street, Suite D, Columbia, SC 29201<br>Not affiliated with the NFL. Data: nflverse.</td></tr>
</table></td></tr></table>'''

def inbox(sub, pre, body):
    return (f'<div class="mail"><div class="mh"><span>From: <b>{FROM}</b></span><span>Subject: <b>{e(sub)}</b></span>'
            f'<span class="pv">{e(pre)}</span></div>{body}</div>')

PAGE = '''<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{t}</title><style>
body{{margin:0;padding:24px 12px 60px;background:#dcdcdf;font-family:-apple-system,system-ui,sans-serif}}
.mail{{max-width:640px;margin:0 auto 32px;background:#fff;border:1px solid #c9c9cf;border-radius:10px;overflow:hidden}}
.mh{{display:flex;flex-direction:column;gap:3px;padding:12px 16px;background:#f2f2f4;border-bottom:1px solid #e1e1e6;font:13px/1.45 -apple-system,system-ui,sans-serif;color:#555}}
.mh b{{color:#111}} .mh .pv{{color:#777}}
.mail img{{max-width:100%;height:auto}}
h1.m{{max-width:640px;margin:0 auto 6px;font:700 15px/1.3 -apple-system,system-ui,sans-serif;color:#222}}
{extra}</style></head><body>{body}</body></html>'''

inner = f'''<tr><td style="padding:0"><a href="{POST['link']}"><img src="{card}" width="600" alt="{e(title)}" style="display:block;width:100%;height:auto;border:0"></a></td></tr>
<tr><td style="padding:26px 28px 8px">
<h1 style="margin:0 0 16px;font:900 26px/1.15 {HEAVY};text-transform:uppercase;color:{INK}">{e(title)}</h1>
{email_body(POST['content']['rendered'])}
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0 26px"><tr><td style="background:{RED};border-radius:4px">
<a href="https://downbydown.com/week/8/" style="display:inline-block;padding:13px 22px;font:700 15px/1 {SANS};color:#fff;text-decoration:none">Every play from Week 8</a></td></tr></table>
</td></tr>'''
email = inbox(title, first, shell(title, inner, first))
open('email-week-8.html', 'w').write(PAGE.format(t='Tuesday email, Week 8', extra='', body=
    '<h1 class="m">The Week 8 email (SCOPE §48)</h1>' +
    NOTE.format('Built from the live Week 8 post exactly as the plugin would send it: inline styles, a 600px column, system fonts. '
                'The footer carries the postal address from decision 4.') + email))

# ---------- the signup (§48) ----------
nl = open('../divi/home-newsletter.html').read()
nl_css = re.search(r'<style>(.*?)</style>', nl, re.S).group(1)
nl_sec = re.search(r'<section class="dxd-nl">.*?</section>', nl, re.S).group(0)
nl_sec = nl_sec.replace('[wpforms id="63" title="false"]',
    '<div class="wpforms-confirmation-container-full"><p><b>Almost there.</b> Check your inbox for a confirmation link.</p></div>')
nl_sec = re.sub(r'<div class="dxd-mail">.*?</div>', '', nl_sec, flags=re.S)
conf_inner = f'''<tr><td style="padding:28px 28px 30px">
<h1 style="margin:0 0 14px;font:900 24px/1.15 {HEAVY};text-transform:uppercase;color:{INK}">One click and you're in</h1>
<p style="margin:0 0 18px;font:16px/1.6 {SANS};color:{INK}">Confirm your email to get the week in review every Tuesday morning: the upsets, the swing that turned a game, the best play and the players whose roles are growing.</p>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px"><tr><td style="background:{RED};border-radius:4px">
<a href="signup-landed.html" style="display:inline-block;padding:13px 22px;font:700 15px/1 {SANS};color:#fff;text-decoration:none">Confirm my email</a></td></tr></table>
<p style="margin:0;font:14px/1.6 {SANS};color:{MUT}">If you didn't sign up, ignore this email and you won't hear from us.</p>
</td></tr>'''
conf = inbox('Confirm your email for Down by Down', 'One click and you\'re in.', shell('', conf_inner, 'One click and you\'re in.', date='', view=False))
fonts = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=IBM+Plex+Mono:wght@500;600&family=IBM+Plex+Sans:wght@400;600&family=Tourney:wght@800&display=swap">'
sig_css = nl_css + '''
.step{max-width:1000px;margin:0 auto 10px;font:700 15px/1.3 -apple-system,system-ui,sans-serif;color:#222}
.frame{max-width:1000px;margin:0 auto 34px;border:1px solid #c9c9cf;border-radius:10px;overflow:hidden}
.dxd-nl .wpforms-confirmation-container-full{margin-top:18px;padding:14px 16px;background:#fff;border:1px solid rgba(22,30,54,.22);border-radius:4px;font:16px/1.5 "IBM Plex Sans",sans-serif;color:#121417}
.dxd-nl .wpforms-confirmation-container-full p{margin:0}'''
open('signup.html', 'w').write(PAGE.format(t='Signup flow', extra=sig_css, body=fonts.join(['', '']) +
    '<p class="step">1. The home form after submitting (the message is WPForms #63\'s Confirmation setting)</p><div class="frame">' + nl_sec + '</div>'
    '<p class="step">2. The confirmation email Brevo sends (the plugin\'s settings page gives this as HTML to paste into Brevo)</p>' + conf +
    '<p class="step">3. The confirm link lands on the latest post with a note on top: <a href="signup-landed.html">open it</a></p>'))

# the landing: the live Week 8 post with the note
ph = open('_post8.html').read()
note = ('<style>.dbd-joined{margin:0 0 22px;padding:12px 16px;border:1px solid rgba(22,30,54,.22);border-radius:4px;background:#fffdf8;'
        'font:400 16px/1.5 "IBM Plex Sans",system-ui,sans-serif;color:#121417}.dbd-joined b{font-weight:600}</style>'
        '<p class="dbd-joined"><b>You\'re in.</b> The next one arrives Tuesday morning. This is the latest, Week 8.</p>')
ph = ph.replace('<div class="dbd-post">', note + '<div class="dbd-post">', 1)
open('signup-landed.html', 'w').write(no_tracking(ph))

# ---------- team news (§49) ----------
def age(ts):
    d = NOW - ts
    if d < 3600: return f'{max(1, d // 60)}m'
    if d < 86400: return f'{d // 3600}h'
    return datetime.datetime.fromtimestamp(ts).strftime('%a')
def rows(items, n=5):
    out = ''
    for i, it in enumerate(items[:10]):
        who = ''.join(f' · <a class="nw-p" href="https://downbydown.com/player/{w["slug"]}/">{e(w["name"])}</a>' for w in it['who'])
        out += (f'<li{" class=nw-more" if i >= n else ""}><a class="nw-h" href="{e(it["url"])}" target="_blank" rel="nofollow noopener">{e(it["title"])}</a>'
                f'<span class="nw-m">{e(it["src"])}{" · <i>Subscriber</i>" if it.get("sub") else ""} · {age(it["ts"])}{who}</span></li>')
    return out
def panel(t, nick, site):
    N = NEWS['news'][t]
    col = lambda h, items, empty: (f'<div class="nw-col"><h3>{h}</h3>' + (f'<ol>{rows(items)}</ol>' if items else f'<p class="nw-empty">{empty}</p>') +
                                   (f'<button type="button" class="nw-btn" onclick="this.parentNode.classList.add(\'open\');this.remove()">Show more</button>' if len(items) > 5 else '') + '</div>')
    return (f'<section class="dbd-panel dbd-news mock-new" id="news"><div class="dbd-ph"><span class="dbd-lab"><b>{nick} news</b> · the last 7 days, from the team, its fans and around the league</span></div>'
            f'<div class="nw-cols">' + col(f'{site.capitalize()}, {N["fan"]} and The Athletic' if t != 'ARI' else f'{site.capitalize()} and {N["fan"]}', N['official'], f'No stories from {site} or {N["fan"]} this week.') +
            col('Around the league', N['league'], f'No {nick} stories from the league feeds this week.') + '</div></section>')
NCSS = '''<style>
.dbd .dbd-news .nw-cols{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:0 28px;padding:4px var(--dbd-edge) 14px}
.dbd .dbd-news h3{margin:10px 0 2px;font:700 13px/1.3 var(--dbd-display);letter-spacing:.08em;text-transform:uppercase;color:var(--dbd-muted)}
.dbd .dbd-news ol{margin:0;padding:0;list-style:none}
.dbd .dbd-news li{margin:0;padding:10px 0;border-bottom:1px solid var(--dbd-line)}
.dbd .dbd-news li:last-child{border-bottom:0}
.dbd .dbd-news .nw-more{display:none}.dbd .dbd-news .open .nw-more{display:block}
.dbd .dbd-news .nw-h{display:block;font:600 15px/1.35 var(--dbd-body);color:var(--dbd-ink);text-decoration:none}
.dbd .dbd-news .nw-h:hover{color:var(--dbd-red-ink);text-decoration:underline}
.dbd .dbd-news .nw-m{display:block;margin-top:3px;font:500 11px/1.4 var(--dbd-mono);letter-spacing:.04em;text-transform:uppercase;color:var(--dbd-muted)}
.dbd .dbd-news .nw-m i{font-style:normal;color:var(--dbd-ink-2)}
.dbd .dbd-news .nw-p{color:var(--dbd-ink-2);text-decoration:underline;text-underline-offset:2px}
.dbd .dbd-news .nw-empty{margin:10px 0;font:400 14px/1.5 var(--dbd-body);color:var(--dbd-muted)}
.dbd .dbd-news .nw-btn{margin-top:6px;padding:6px 12px;background:none;border:1px solid var(--dbd-line-2);border-radius:4px;font:600 13px var(--dbd-body);color:var(--dbd-ink);cursor:pointer}
.dbd .dbd-latest{margin:10px 0 0;font:400 14px/1.5 var(--dbd-body);color:var(--dbd-ink-2)}
.dbd .dbd-latest a{color:var(--dbd-ink)}
.dbd .dbd-latest span{font:500 11px var(--dbd-mono);letter-spacing:.04em;text-transform:uppercase;color:var(--dbd-muted)}
.t-stagger-line{opacity:1!important;filter:none!important;transform:none!important}
.dbd .mock-new{outline:1px dashed var(--dbd-red);outline-offset:4px}
@media (max-width:700px){.dbd .dbd-news .nw-cols{grid-template-columns:minmax(0,1fr)}}
</style>'''
tp = open('_tp-min.html').read()
i = tp.find('<section class="dbd-panel dbd-roster">')
tp = tp[:i] + panel('MIN', 'Vikings', 'vikings.com') + tp[i:]
tp = tp.replace('</head>', NCSS + '</head>', 1)
open('team-news-MIN.html', 'w').write(no_tracking(tp))

tm = open('_team-min.html').read()
lt = NEWS['news']['MIN']['official'][0]
latest = (f'<p class="dbd-latest mock-new">Latest: <a href="{e(lt["url"])}" target="_blank" rel="nofollow noopener">{e(lt["title"])}</a> '
          f'<span>{e(lt["src"])} · {age(lt["ts"])}</span> · <a href="team-news-MIN.html#news">All Vikings news</a></p>')
tm = re.sub(r'(<p class="dbd-sub t-stagger-line t-stagger-line--3">.*?</p>)', lambda m: m.group(1) + latest, tm, count=1, flags=re.S)
tm = tm.replace('</head>', NCSS + '</head>', 1)
open('team-latest-MIN.html', 'w').write(no_tracking(tm))
print('wrote email-week-8.html signup.html signup-landed.html team-news-MIN.html team-latest-MIN.html')
