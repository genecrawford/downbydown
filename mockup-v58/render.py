"""Mock v58 (SCOPE §85): tall 1080×1350 social graphics, one idea each, drawn from the live Week 10 numbers (_data.json) with the plugin's own fonts.
Usage: python3 render.py <art dir> <out dir>. The art dir (logos, headshots) stays out of the repo."""
import json, os, re, sys
from PIL import Image, ImageDraw, ImageFont
here = os.path.dirname(os.path.abspath(__file__)); ART, OUT = sys.argv[1], sys.argv[2]; os.makedirs(OUT, exist_ok=True)
D = json.load(open(os.path.join(here, '_data.json'))); FD = os.path.join(here, '..', 'plugin', 'down-by-down', 'assets', 'fonts')
W, H, S = 1080, 1350, 2
TH = {'black': dict(bg='#0f1012', ink='#ffffff', mut='#a6aab2', line='#2b2e34', red='#e5282e', row='#191b1f'),
      'paper': dict(bg='#f4f1ea', ink='#121417', mut='#62666f', line='#d9d4c8', red='#cb2026', row='#fbf9f4')}
_f = {}
def F(n, px):
    k = (n, px)
    if k not in _f: _f[k] = ImageFont.truetype(os.path.join(FD, {'b': 'BarlowCondensed-Bold.ttf', 's': 'BarlowCondensed-SemiBold.ttf', 'm': 'IBMPlexMono-Medium.ttf', 'p': 'IBMPlexSans-Medium.ttf'}[n]), int(px * S))
    return _f[k]
def rgb(h): h = h.lstrip('#'); return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))
def lum(h): r, g, b = [(c / 255) for c in rgb(h)]; return .2126 * r + .7152 * g + .0722 * b
def on(h): return '#ffffff' if lum(h) < .58 else '#121417'
def vis(h, th): return mix(h, '#ffffff', .42) if th == 'black' and lum(h) < .24 else h
def mix(a, b, t): x, y = rgb(a), rgb(b); return '#%02x%02x%02x' % tuple(int(x[i] + (y[i] - x[i]) * t) for i in range(3))
class C:
    def __init__(s, th): s.t = TH[th]; s.th = th; s.im = Image.new('RGB', (W * S, H * S), s.t['bg']); s.d = ImageDraw.Draw(s.im)
    def rect(s, x, y, w, h, c): s.d.rectangle([x * S, y * S, (x + w) * S - 1, (y + h) * S - 1], fill=c)
    def w(s, t, f, px): return s.d.textlength(t, font=F(f, px)) / S
    def text(s, x, y, t, f, px, c, right=False, track=0):
        if track:
            tw = sum(s.w(ch, f, px) + track for ch in t) - track; cx = x - tw if right else x
            for ch in t: s.d.text((cx * S, y * S), ch, font=F(f, px), fill=c, anchor='ls'); cx += s.w(ch, f, px) + track
            return
        s.d.text((x * S, y * S), t, font=F(f, px), fill=c, anchor='rs' if right else 'ls')
    def fit(s, t, f, px, mw):
        while px > 16 and s.w(t, f, px) > mw: px -= 1
        return px
    def wrap(s, t, f, px, mw):
        o, cur = [], ''
        for w in t.split():
            tr = (cur + ' ' + w).strip()
            if cur and s.w(tr, f, px) > mw: o.append(cur); cur = w
            else: cur = tr
        return o + [cur]
    def paste(s, im, x, y): s.im.paste(im, (int(x * S), int(y * S)), im)
    def logo(s, t, x, y, b, dark=None):
        d = s.th == 'black' if dark is None else dark
        p = os.path.join(ART, 'logos', t + ('-d' if d else '') + '.png'); im = Image.open(p).convert('RGBA'); im = im.crop(im.getbbox()); r = min(b / im.width, b / im.height) * S
        im = im.resize((max(1, int(im.width * r)), max(1, int(im.height * r))), Image.LANCZOS); s.im.paste(im, (int((x + b / 2) * S - im.width / 2), int((y + b / 2) * S - im.height / 2)), im)
    def tile(s, t, x, y, w, h, b=None):
        c1 = D['teams'][t]['c1']; s.rect(x, y, w, h, c1); s.logo(t, x + (w - (b or h - 10)) / 2, y + (h - (b or h - 10)) / 2, b or h - 10, dark=True)
    def head(s, pid, x, y, w, h, scale=1.0, up=0):
        """A headshot cutout, standing on the bottom edge of the box x,y,w,h."""
        im = Image.open(os.path.join(ART, 'heads', pid + '.png')).convert('RGBA'); bb = im.getbbox(); im = im.crop(bb)
        r = w / im.width * scale * S; im = im.resize((int(im.width * r), int(im.height * r)), Image.LANCZOS)
        bx, by, bw, bh = int(x * S), int(y * S), int(w * S), int(h * S); left = max(0, (im.width - bw) // 2); top = max(0, im.height - bh); im = im.crop((left, top, left + min(bw, im.width), im.height))
        s.im.paste(im, (bx + (bw - im.width) // 2, by + bh - im.height - int(up * S)), im)
    def foot(s, x0=56, x1=W - 56, y=H - 50):
        lg = Image.open(os.path.join(here, '..', 'plugin', 'down-by-down', 'assets', 'card-logo.png')).convert('RGBA'); lg = lg.crop(lg.getbbox())
        px = lg.load()
        for i in range(lg.width):
            for j in range(lg.height):
                r, g, b, al = px[i, j]; k = 255 - min(r, g, b); na = al * k // 255
                if not na: px[i, j] = (0, 0, 0, 0); continue
                un = lambda v: max(0, min(255, (v - (255 - k)) * 255 // k))
                r, g, b = un(r), un(g), un(b)
                if s.th == 'black' and max(r, g, b) - min(r, g, b) < 60: r, g, b = 255, 255, 255
                px[i, j] = (r, g, b, na)
        h = 42 * S; lg = lg.resize((int(lg.width * h / lg.height), h), Image.LANCZOS); s.im.paste(lg, (int(x0 * S), int((y - 34) * S)), lg)
        s.text(x1, y, 'downbydown.com', 'm', 24, s.t['mut'], right=True)
    def title(s, lines, sub, x=56, mw=968, y=128, px=108):
        px = min(s.fit(l, 'b', px, mw) for l in lines); yy = y
        for l in lines: s.text(x, yy, l, 'b', px, s.t['ink']); yy += px * .96
        yy += -px * .96 + 50
        if sub: s.text(x, yy, sub, 's', 36, s.t['mut'], track=2.4); yy += 22
        s.rect(x, yy, mw, 5, s.t['red']); return yy + 5
    def done(s, name):
        o = s.im.resize((W, H), Image.LANCZOS); o.save(os.path.join(OUT, name + '.png')); return o
def split(name):
    p = name.split(); return (' '.join(p[:-2]) + ' ' + p[-2], p[-1]) if len(p) > 2 and p[-1] in ('III', 'II', 'Jr.', 'Sr.') and False else (p[0], ' '.join(p[1:]))
N = D['n']
def board(th, fn, lines, sub, R, lab, photo=True):
    """The ranked board: rows of id, full, t, v (and x, a small second stat), the leader on the team-color panel."""
    c = C(th); lead = R[0]; c1 = D['teams'][lead['t']]['c1']; px0 = 640; num = str(D['people'].get(lead['id'], {}).get('num') or ''); ink = on(c1); cx = (px0 + W) / 2; nn = str(lead['v'])
    c.rect(px0, 0, W - px0, H, c1)
    if photo:
        c.logo(lead['t'], cx - 66, 54, 132, dark=True); c.text(cx - c.w(nn, 'b', 300) / 2, 438, nn, 'b', 300, ink); c.text(cx - c.w(lab, 's', 40) / 2, 496, lab, 's', 40, ink)
        c.text(W - 12, 930, num, 'b', 430, mix(c1, ink, .12), right=True); c.head(lead['id'], px0, 560, W - px0, H - 560, 1.72)
    else:
        c.logo(lead['t'], px0 + 56, 190, W - px0 - 112, dark=True); c.text(cx - c.w(nn, 'b', 380) / 2, 930, nn, 'b', 380, ink); c.text(cx - c.w(lab, 's', 48) / 2, 1004, lab, 's', 48, ink); nm = lead['full'].upper(); pxn = c.fit(nm, 'b', 62, 400); c.text(cx - c.w(nm, 'b', pxn) / 2, 1120, nm, 'b', pxn, ink)
    b = c.title(lines, sub, mw=540, px=96); y = b + 15; rh = (H - 104 - y) / len(R)
    for i, r in enumerate(R):
        ry = y + i * rh; f, l = split(r['full']); c.tile(r['t'], 56, ry + 3, 92, int(rh - 12))
        c.text(164, ry + rh * .43, f.upper(), 's', 27, c.t['ink']); c.text(164, ry + rh * .43 + 28, l.upper(), 'b', c.fit(l.upper(), 'b', 30, 270), c.t['ink'])
        if r.get('x'): c.text(516, ry + rh * .70, r['x'], 's', 26, c.t['mut'], right=True)
        c.text(596, ry + rh * .76, str(r['v']), 'b', 64, c.t['ink'], right=True)
        if i: c.rect(56, ry - 3, 540, 1, c.t['line'])
    c.foot(x1=596); return c.done(fn + '-' + th + ('' if photo else '-nophoto'))
def league(th, photo=True): return board(th, 'league', ['LEAGUE LEADERS', 'RUSHING TDS'], 'THROUGH WEEK ' + str(N), [dict(r, v=r['rtd']) for r in D['rtd']], 'RUSHING TDS', photo)
def rushers(th):
    X = D['rush']; return board(th, 'rush', ['PASS RUSHERS', 'MOST PRESSURES'], 'THROUGH WEEK %d' % X['through'], [dict(r, v=r['prs'], x='%g SACKS' % r['sk']) for r in X['rows']], 'PRESSURES')
def keys(th):
    k = D['key']; c = C(th); T = D['teams']; b = c.title(['KEYS TO THE GAME'], 'WEEK %d · %s %s' % (k['week'], k['day'].upper(), k['time']), y=136, px=120); y = b + 28
    for i, t in enumerate((k['away'], k['home'])):
        x = 56 + i * 496; c1 = T[t]['c1']; c.rect(x, y, 472, 150, c1); c.logo(t, x + 22, y + 19, 112, dark=True); nm = T[t]['nick'].upper(); c.text(x + (430 if i == 0 else 450), y + 98, nm, 'b', c.fit(nm, 'b', 66, 280), on(c1), right=True)
    tw = c.w('AT', 'b', 34) + 22; c.rect(W / 2 - tw / 2, y + 54, tw, 44, c.t['bg']); c.text(W / 2 - c.w('AT', 'b', 34) / 2, y + 88, 'AT', 'b', 34, c.t['ink']); y += 214
    c.text(56, y, 'FOR THE ' + T[k['t']]['nick'].upper(), 's', 38, c.t['red'], track=2.4); hd = k['head'].upper(); pxh = c.fit(hd, 'b', 172, W - 112); c.text(52, y + pxh * .96, hd, 'b', pxh, c.t['ink']); y += int(pxh * .96) + 56
    mx = max(r[1] for r in k['rows']); rh = (H - 150 - y - 34) / 3
    for i, (lab, v, rank) in enumerate(k['rows']):
        ry = y + i * rh; col = c.t['red'] if i == 0 else (c.t['ink'] if i == 1 else c.t['mut']); c.text(56, ry + 32, lab, 's', 32, c.t['ink'] if i < 2 else c.t['mut'], track=1)
        if rank: c.text(W - 56, ry + 32, rank.upper(), 's', 32, c.t['mut'], right=True)
        bw = W - 112 - 190; c.rect(56, ry + 50, bw, 62, c.t['row'] if th == 'paper' else c.t['line']); c.rect(56, ry + 50, bw * v / mx, 62, col); c.text(W - 56, ry + 106, str(v), 'b', 76, c.t['ink'] if i < 2 else c.t['mut'], right=True)
    c.text(56, H - 112, k['unit'], 's', 26, c.t['mut'], track=1.4); c.foot(); return c.done('keys-' + th)
def leaders(th):
    c = C(th); b = c.title(['WEEK %d LEADERS' % N], None, y=136, px=124); L = D['leaders']; y = b + 22; rh = (H - 104 - y) / 5
    for i, k in enumerate(('pass', 'rush', 'rec', 'td', 'def')):
        v = L[k]; ry = y + i * rh; t = v[1]; tile = 176; c1 = D['teams'][t]['c1']; c.rect(56, ry, tile, rh - 14, c1)
        if k == 'def': c.logo(t, 56 + 26, ry + (rh - 14 - 124) / 2, 124, dark=True)
        else: c.head(v[7]['id'], 56, ry, tile, rh - 14, 1.62)
        tx = 56 + tile + 28; c.text(tx, ry + 44, v[0].upper(), 's', 30, c.t['red'], track=2); nm = v[2].upper(); c.text(tx, ry + 104, nm, 'b', c.fit(nm, 'b', 62, 470), c.t['ink'])
        det = re.sub(r' · held.*', '', v[5]).replace('a play allowed', 'a play allowed'); c.text(tx, ry + 148, det, 'p', 25, c.t['mut'])
        val = v[3]; uw = c.w(v[4].upper(), 's', 32); c.text(W - 56, ry + 148, v[4].upper(), 's', 32, c.t['mut'], right=True); c.text(W - 56 - uw - 12, ry + 148, val, 'b', c.fit(val, 'b', 128, 300), c.t['ink'], right=True)
        if i < 4: c.rect(56, ry + rh - 7, W - 112, 1, c.t['line'])
    c.foot(); return c.done('leaders-' + th)
def offense(th):
    c = C(th); b = c.title(['WHO MOVED THE BALL'], 'WEEK %d · EPA A PLAY' % N, y=136, px=120); R = D['offense']; best = R[:5]; worst = list(reversed(R[-5:])); y = b + 20; rh = 84; T = D['teams']
    mx = max(abs(r[1]) for r in best + worst)
    def block(lab, rows, y, col):
        c.text(56, y + 34, lab, 's', 34, col, track=2.4); y += 50
        for i, r in enumerate(rows):
            ry = y + i * rh; c.tile(r[0], 56, ry, 96, rh - 12); nm = T[r[0]]['nick'].upper(); c.text(172, ry + 38, nm, 'b', 42, c.t['ink'])
            w, l = r[2] > r[3], r[2] < r[3]; res = ('won ' if w else 'lost ' if l else 'tied ') + '%d-%d' % (max(r[2], r[3]), min(r[2], r[3])) + (' over the ' if w else ' to the ' if l else ' with the ') + T[r[4]]['nick']; c.text(172, ry + 66, res, 'p', 22, c.t['mut'])
            bx, bw = 560, 280; c.rect(bx, ry + 22, bw, 26, c.t['row'] if th == 'paper' else c.t['line']); c.rect(bx, ry + 22, max(4, bw * abs(r[1]) / mx), 26, col if col != c.t['ink'] else c.t['ink'])
            val = ('+' if r[1] > 0 else '−') + '%.2f' % abs(r[1]); c.text(W - 56, ry + 56, val, 'b', 62, c.t['ink'], right=True)
        return y + len(rows) * rh
    y = block('BEST', best, y, c.t['ink']); c.rect(56, y + 2, W - 112, 1, c.t['line']); y = block('WORST', worst, y + 12, c.t['red']); c.foot(); return c.done('offense-' + th)
def swing(th):
    c = C(th); G = D['game']; sw = D['swing']; T = D['teams']; b = c.title(['THE BIGGEST SWING'], 'WEEK %d · %s' % (N, G['stadium'].upper()), y=136, px=120); y = b + 28
    for i, (t, sc) in enumerate(((G['away'], G['a']), (G['home'], G['h']))):
        x = 56 + i * 496; c1 = T[t]['c1']; win = sc == max(G['a'], G['h']); c.tile(t, x, y, 472, 150, 112)
        tcol = on(c1); c.text(x + 456, y + 118, str(sc), 'b', 118, tcol, right=True)
    y += 182; v = '+%d' % round(sw['v']); c.text(52, y + 186, v, 'b', 244, c.t['red']); tx = 52 + c.w(v, 'b', 244) + 34
    for j, l in enumerate(('POINTS OF WIN', 'PROBABILITY', 'ON ONE PLAY')): c.text(tx, y + 72 + j * 56, l, 'b', 56, c.t['ink'])
    y += 224; cx, cy, cw, ch = 56, y, W - 112, H - 196 - y; away = sw['t'] == G['away']; pts = [(p[0] / 3600, (1000 - p[1] if away else p[1]) / 1000) for p in D['wp']]
    c.rect(cx, cy, cw, ch, c.t['row']); c.rect(cx, cy + ch / 2, cw, 1, c.t['line']); xy = [((cx + min(1, a) * cw) * S, (cy + (1 - b_) * ch) * S) for a, b_ in pts]; lc = '#ffffff' if th == 'black' else T[sw['t']]['c1']
    poly = [(cx * S, (cy + ch) * S)] + xy + [(xy[-1][0], (cy + ch) * S)]; ov = Image.new('RGBA', c.im.size, (0, 0, 0, 0)); ImageDraw.Draw(ov).polygon(poly, fill=rgb(lc) + (46,)); c.im.paste(ov, (0, 0), ov); c.d = ImageDraw.Draw(c.im); c.d.line(xy, fill=lc, width=8, joint='curve')
    m = re.match(r'Q(\d) (\d+):(\d+)', sw['c']); at = (int(m[1]) * 900 - int(m[2]) * 60 - int(m[3])) / 3600; near = min(pts, key=lambda p: abs(p[0] - at)); mx_, my_ = (cx + at * cw) * S, (cy + (1 - near[1]) * ch) * S; r = 15 * S; c.d.ellipse([mx_ - r, my_ - r, mx_ + r, my_ + r], fill=c.t['red'], outline=c.t['bg'], width=6)
    c.text(cx + 14, cy + 34, T[sw['t']]['nick'].upper() + ' WIN PROBABILITY', 's', 26, c.t['mut'], track=1.4); c.text(cx + cw - 14, cy + 34, sw['c'], 's', 28, c.t['red'], right=True)
    txt = sw['d']; py = cy + ch + 46; pp = c.fit(txt, 's', 40, W - 112); c.text(56, py, txt, 's', pp, c.t['ink']); c.foot(); return c.done('swing-' + th)
def riser(th, k):
    s = D[k]; c = C(th); t = s['team']; c1 = s['c1']; ink = on(c1); bh = 610; c.rect(0, 0, W, bh, c1); pid = next(i for i, p in D['people'].items() if p['name'] == s['first'] + ' ' + s['last']); num = str(D['people'][pid]['num'] or '')
    c.text(W - 20, 340, num, 'b', 400, mix(c1, ink, .13), right=True); c.head(pid, 450, 40, 630, bh - 40, 1.02)
    head = 'THE USAGE RISER' if k == 'snaps' else 'TARGET-SHARE MOVER'; c.text(56, 108, head.split()[0] + (' ' + head.split()[1] if k == 'snaps' else ''), 'b', 88, ink); c.text(56, 190, head.split()[-1], 'b', 88, ink); c.text(56, 246, 'WEEK %d' % N, 's', 36, ink, track=2.4)
    c.logo(t, 56, 306, 120, dark=True); c.text(56, 492, s['first'].upper(), 's', 50, ink); c.text(56, 560, s['last'].upper(), 'b', c.fit(s['last'].upper(), 'b', 88, 430), ink)
    y = bh + 188; v = '%d%%' % round(s['now']); c.text(52, y, v, 'b', 200, c.t['red']); sayx = 52 + c.w(v, 'b', 200) + 30
    ls = c.wrap(s['say'], 'p', 31, W - 56 - sayx); y0 = y - 142 + (142 - len(ls) * 40) / 2 + 26
    for j, l in enumerate(ls): c.text(sayx, y0 + j * 40, l, 'p', 31, c.t['ink'])
    ser = s['series']; ct = y + 52; cb = H - 136; mxv = max(x for x in ser if x is not None) or 1; bw = (W - 112) / len(ser)
    for i, x in enumerate(ser):
        bx = 56 + i * bw; c.text(bx + bw / 2 - c.w(str(i + 1), 's', 26) / 2, cb + 32, str(i + 1), 's', 26, c.t['mut'])
        if x is None: c.text(bx + bw / 2 - c.w('BYE', 's', 22) / 2, cb - 8, 'BYE', 's', 22, c.t['mut']); continue
        hh = max(4, (cb - ct - 34) * x / mxv); last = i == len(ser) - 1; c.rect(bx + 6, cb - hh, bw - 12, hh, c.t['red'] if last else (c.t['line'] if th == 'black' else '#cfcabd')); lab = '%d' % round(x); c.text(bx + bw / 2 - c.w(lab, 'b', 30) / 2, cb - hh - 8, lab, 'b', 30, c.t['ink'] if last else c.t['mut'])
    c.foot(); return c.done(k + '-' + th)
for th in TH:
    league(th); leaders(th); swing(th); riser(th, 'snaps'); offense(th); riser(th, 'targets'); rushers(th); keys(th)
league('black', False); league('paper', False)
print(len(os.listdir(OUT)), 'images')
