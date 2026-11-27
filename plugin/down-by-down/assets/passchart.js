/* Every throw, one game at a time (SCOPE §90): the pass chart on a finished game's page and a quarterback's page.
   The field stands upright and fills the panel's width: the line of scrimmage near the bottom, the quarterback behind it in his team's color
   with his number, depth up the field, his left on the left. Each throw is a dot by air yards and third of the field; hover, focus or tap one
   and a line arcs from the quarterback to it with the receiver named. Data: the panel's embedded JSON (class-dbd-throws.php).
   2.49.0 (SCOPE §91): Whole season on the quarterback's picker, the replay, and field use against the league.
   2.51.0 (SCOPE §93): nothing beside the field; everything under it, in the order it's used:
     1. the replay bar: Play or Pause, back and forward a throw, a timeline of the game with each throw a tick in its result's color (tap one
        to jump to it), the count, 1× or 2×. The field shows every throw until Play; pausing or finishing puts them all back.
     2. the throw you're on: what happened and to whom, the quarter, clock, score, down and distance, then air yards, yards and EPA.
     3. the game in two lines: the passing line, and field use with Show the 12 zones beside it.
     4. by depth (a table) beside the key to the dots.
     5. highlight a receiver (and, on a game page, pick a passer). */
(function () {
  var COL = { c: '#5b9cf6', i: '#c9c6d1', x: '#e0564b', td: '#4fd18b' };   /* on the field */
  var TICK = { c: '#5b9cf6', i: '#a9a5b4', x: '#e0564b', td: '#2fb873' };  /* on the page's light ground */
  var BANDS = [[-99, 0, 'Behind the line'], [0, 10, '0 to 9 yds'], [10, 20, '10 to 19 yds'], [20, 999, '20+ yds']];
  var STEP = 1100;
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function rnd(i) { var x = Math.sin(i * 12.9898 + 78.233) * 43758.5453; return x - Math.floor(x); }
  function ord(n) { return n + (['th', 'st', 'nd', 'rd'][n] || 'th'); }
  function num(v) { return Number(v).toLocaleString('en-US'); }
  function short(n) { var p = String(n || '').split(' '); return p.length > 1 && p[0].indexOf('.') < 0 ? p[0].charAt(0) + '. ' + p.slice(1).join(' ') : String(n || ''); }
  function band(a) { return a < 0 ? 0 : a < 10 ? 1 : a < 20 ? 2 : 3; }
  function secs(c) { var p = String(c || '').split(':'); return (+p[0] || 0) * 60 + (+p[1] || 0); }
  function when(r) { return Math.max(0, Math.min(1, ((r.q - 1) * 900 + (900 - secs(r.c))) / 3600)); }  /* how far into regulation; overtime sits at the end */
  /* a row: q, clock, down, distance, result, air, yards, lane, td, receiver, passer, epa, score margin */
  function R(a) { return { q: a[0], c: a[1], dn: a[2], tg: a[3], k: a[4], a: a[5], y: a[6], l: a[7], td: a[8], r: a[9], qb: a[10], e: a[11], sd: a.length > 12 ? a[12] : null }; }
  function kind(r) { return r.td ? 'td' : r.k; }
  function dot(c, x, y, r, extra, pal) {
    var P = pal || COL;
    if (c === 'i') return '<circle cx="' + x + '" cy="' + y + '" r="' + (r - 1) + '" fill="rgba(0,0,0,.001)" stroke="' + P.i + '" stroke-width="2"' + extra + '/>';
    if (c === 'x') { var d = r * .45; return '<g' + extra + '><circle cx="' + x + '" cy="' + y + '" r="' + r + '" fill="' + P.x + '"/><path d="M' + (x - d) + ' ' + (y - d) + 'L' + (x + d) + ' ' + (y + d) + 'M' + (x + d) + ' ' + (y - d) + 'L' + (x - d) + ' ' + (y + d) + '" stroke="#fff" stroke-width="1.6"/></g>'; }
    return '<circle cx="' + x + '" cy="' + y + '" r="' + r + '" fill="' + P[c] + '"' + (c === 'td' ? ' stroke="#fff" stroke-width="1.5"' : '') + extra + '/>';
  }
  function zones(rows) { var z = {}; rows.forEach(function (r) { if (r.l < 0) return; var k = r.l * 4 + band(r.a); z[k] = (z[k] || 0) + 1; }); return z; }
  /* field use: zones touched, and how evenly, against the league (SCOPE §91) */
  function use(rows, season, lg) {
    if (!lg || !lg.g || !lg.g.length) return null;
    var z = zones(rows), keys = Object.keys(z), n = keys.reduce(function (s, k) { return s + z[k]; }, 0), min = (lg.min || [20, 150])[season ? 1 : 0], L = season ? lg.s : lg.g, t = keys.length + ' of 12 zones';
    if (n < min || !L || !L.length) return { t: t, p: 'too few throws to compare (' + min + '+)' };
    var e = -keys.reduce(function (s, k) { var p = z[k] / n; return s + p * Math.log(p); }, 0) / Math.log(12), er = Math.round(e * 1e4) / 1e4;
    return { t: t, p: 'more evenly spread than ' + Math.round(L.filter(function (x) { return x < er - 1e-9; }).length / L.length * 100) + '% of quarterback ' + (season ? 'seasons (' + min + '+ throws)' : 'games this season (' + min + '+ throws)') };
  }

  function mount(sec) {
    var D; try { D = JSON.parse(sec.querySelector('.dbd-thr-d').textContent); } catch (e) { return; }
    var N = D.names || {}, sets = D.sets || []; if (!sets.length) return;
    sets.forEach(function (s) { s.R = s.rows.map(function (a) { var r = R(a); r.tn = s.nick || s.label; return r; }); });
    var first = 0;
    if (D.kind === 'qb' && sets.length > 1) {  /* the whole season, ahead of the games; his newest game stays the default */
      var all = [], by = []; sets.slice().reverse().forEach(function (s) { s.R.forEach(function (r) { var x = Object.create(r); x.gl = s.label; all.push(x); }); if (by.indexOf(s.nick) < 0) by.push(s.nick); });
      sets.unshift({ season: true, label: 'Whole season', nick: by.join(' and the '), c1: sets[0].c1, R: all }); first = 1;
    }
    var body = sec.querySelector('.dbd-thr-b'), pick = sec.querySelector('.dbd-thr-pick'), st = { set: first, qb: '', hl: '', zones: false, sel: -1, rp: null }, G = {};
    var name = function (k) { return (N[k] || [])[0] || ''; }, jersey = function (k) { return (N[k] || [])[1] || ''; };
    var lg = function (c, l) { return '<span><svg width="14" height="14" aria-hidden="true">' + dot(c, 7, 7, 5.5, '', TICK) + '</svg>' + l + '</span>'; };
    body.innerHTML = '<div class="dbd-thr-fld"></div><div class="dbd-thr-bar"></div><div class="dbd-thr-now" aria-live="polite"></div><p class="dbd-thr-sum"></p><p class="dbd-thr-use"></p>'
      + '<div class="dbd-thr-g2"><div><p class="dbd-thr-hh">By depth</p><table class="dbd-thr-dp"></table></div><div><p class="dbd-thr-hh">The dots</p><div class="dbd-thr-key">' + lg('c', 'Complete') + lg('i', 'Incomplete') + lg('x', 'Intercepted') + lg('td', 'Touchdown') + '<span><svg width="14" height="14" aria-hidden="true"><circle class="dbd-thr-qk" cx="7" cy="7" r="6.5"/></svg>The quarterback, behind the line</span></div></div></div>'
      + '<div class="dbd-thr-hl"><div class="dbd-thr-qbs dbd-thr-chips"></div><div class="dbd-thr-recs dbd-thr-chips"></div></div>'
      + '<p class="dbd-note">Left and right are as the quarterback sees the field. Depth is air yards, how far past the line of scrimmage the ball was thrown. The play data says left, middle or right, not exactly where, so a dot’s place across its third is spread out. Field use splits the field into 12 zones (left, middle and right, by depth) and says how evenly the throws were spread across them, against the rest of the league.</p>';
    var $ = function (c) { return body.querySelector(c); };
    if (D.kind === 'game') {
      pick.innerHTML = '<span class="dbd-thr-seg" role="group" aria-label="Team">' + sets.map(function (s, i) { return '<button type="button" data-s="' + i + '" aria-pressed="' + (i === 0) + '">' + esc(s.label) + '</button>'; }).join('') + '</span>';
      pick.onclick = function (e) { var b = e.target.closest('button'); if (!b) return; stop(); st.set = +b.getAttribute('data-s'); st.qb = ''; st.hl = ''; st.sel = -1; [].forEach.call(pick.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); render(); };
    } else {
      pick.innerHTML = '<select aria-label="Which game">' + sets.map(function (s, i) { return '<option value="' + i + '"' + (i === first ? ' selected' : '') + '>' + esc(s.label + (s.season ? '' : ' · ' + s.nick) + ' · ' + s.R.length + (s.R.length === 1 ? ' throw' : ' throws')) + '</option>'; }).join('') + '</select>';
      pick.querySelector('select').onchange = function () { stop(); st.set = +this.value; st.hl = ''; st.sel = -1; render(); };
    }
    $('.dbd-thr-qbs').onclick = function (e) { var b = e.target.closest('button'); if (!b) return; stop(); st.qb = b.getAttribute('data-q'); st.hl = ''; st.sel = -1; render(); };
    $('.dbd-thr-recs').onclick = function (e) { var b = e.target.closest('button'); if (!b) return; st.hl = b.getAttribute('data-r'); render(); };

    function rows() { var s = sets[st.set]; return st.qb ? s.R.filter(function (r) { return r.qb === st.qb; }) : s.R; }
    function render() {
      var s = sets[st.set], all = s.R, cnt = {}; all.forEach(function (r) { cnt[r.qb] = (cnt[r.qb] || 0) + 1; });
      var ps = Object.keys(cnt).sort(function (a, b) { return cnt[b] - cnt[a]; }), Rs = rows();
      var c = Rs.filter(function (r) { return r.k === 'c'; }), deep = Rs.filter(function (r) { return r.a >= 20; }), td = Rs.filter(function (r) { return r.td; }).length, it = Rs.filter(function (r) { return r.k === 'x'; }).length;
      var who = D.kind === 'game' ? (st.qb ? name(st.qb) : ps.map(name).join(' and ')) : s.season ? 'This season, for the ' + s.nick : s.label + ', for the ' + s.nick;
      $('.dbd-thr-sum').innerHTML = esc(who) + ': <b>' + c.length + ' of ' + Rs.length + ', ' + num(c.reduce(function (t, r) { return t + r.y; }, 0)) + ' yards</b>, ' + td + ' TD, ' + it + ' INT, ' + deep.length + (deep.length === 1 ? ' throw' : ' throws') + ' of 20+ air yards.';
      var u = use(Rs, !!s.season, D.lg), ue = $('.dbd-thr-use');
      ue.hidden = !u; if (u) { ue.innerHTML = 'Field use: <b>' + u.t + '</b>, ' + u.p + '. <button type="button" class="dbd-thr-lk">' + (st.zones ? 'Hide the zones' : 'Show the 12 zones') + '</button>'; ue.querySelector('button').onclick = function () { st.zones = !st.zones; render(); }; }
      var dp = '<thead><tr><th scope="col">Depth</th><th scope="col">Comp</th><th scope="col">Yds</th><th scope="col">TD</th><th scope="col">INT</th></tr></thead><tbody>';
      BANDS.slice().reverse().forEach(function (b) { var rs = Rs.filter(function (r) { return r.a >= b[0] && r.a < b[1]; }), cc = rs.filter(function (r) { return r.k === 'c'; });
        dp += '<tr><th scope="row">' + b[2] + '</th><td>' + cc.length + '/' + rs.length + '</td><td>' + num(cc.reduce(function (t, r) { return t + r.y; }, 0)) + '</td><td>' + rs.filter(function (r) { return r.td; }).length + '</td><td>' + rs.filter(function (r) { return r.k === 'x'; }).length + '</td></tr>'; });
      $('.dbd-thr-dp').innerHTML = dp + '</tbody>';
      var qe = $('.dbd-thr-qbs'); qe.hidden = D.kind !== 'game' || ps.length < 2;
      if (!qe.hidden) qe.innerHTML = '<span class="cl">Passer</span><button type="button" data-q="" aria-pressed="' + !st.qb + '">' + (ps.length === 2 ? 'Both' : 'All') + '</button>' + ps.map(function (q) { return '<button type="button" data-q="' + esc(q) + '" aria-pressed="' + (st.qb === q) + '">' + esc(name(q)) + ' · ' + cnt[q] + '</button>'; }).join('');
      var n = {}; Rs.forEach(function (r) { if (r.r) n[r.r] = (n[r.r] || 0) + 1; });
      var top = Object.keys(n).sort(function (a, b) { return n[b] - n[a] || (name(a) < name(b) ? -1 : 1); }).slice(0, 8);
      $('.dbd-thr-recs').innerHTML = '<span class="cl">Highlight a receiver</span><button type="button" data-r="" aria-pressed="' + !st.hl + '">Everyone</button>' + top.map(function (r) { return '<button type="button" data-r="' + esc(r) + '" aria-pressed="' + (st.hl === r) + '">' + esc(name(r)) + ' · ' + n[r] + '</button>'; }).join('');
      draw(st.rp ? st.rp.i : null); bar(); if (!st.rp && st.sel < 0) now(null);
    }

    /* 1. the replay bar */
    function bar() {
      var s = sets[st.set], Rs = rows(), b = $('.dbd-thr-bar');
      if (s.season) { b.className = 'dbd-thr-bar is-season'; b.textContent = 'The replay works one game at a time. Pick a game above to watch its throws go out in order.'; return; }
      b.className = 'dbd-thr-bar'; var at = st.rp ? st.rp.i : (st.sel >= 0 ? st.sel + 1 : 0), on = !!(st.rp && st.rp.on), W = 1000, x = function (r) { return 8 + when(r) * (W - 16); };
      var g = '<svg viewBox="0 0 ' + W + ' 30" preserveAspectRatio="none" aria-hidden="true"><rect x="8" y="13" width="' + (W - 16) + '" height="4" rx="2" fill="rgba(22,30,54,.12)"/>';
      if (at) g += '<rect x="8" y="13" width="' + Math.max(0, x(Rs[at - 1]) - 8) + '" height="4" rx="2" fill="#121417"/>';
      [1, 2, 3].forEach(function (q) { var qx = 8 + q / 4 * (W - 16); g += '<line x1="' + qx + '" x2="' + qx + '" y1="8" y2="22" stroke="rgba(22,30,54,.3)"/>'; });
      Rs.forEach(function (r, i) { var cur = at === i + 1; g += '<rect x="' + (x(r) - (cur ? 2.5 : 1.5)) + '" y="' + (cur ? 0 : 4) + '" width="' + (cur ? 5 : 3) + '" height="' + (cur ? 30 : 22) + '" rx="1" fill="' + TICK[kind(r)] + '"' + (st.rp && i >= st.rp.i ? ' opacity=".25"' : '') + '/>'; });
      var sp = st.rp ? st.rp.sp : 1;
      b.innerHTML = '<div class="dbd-thr-btns"><button type="button" class="dbd-thr-pp" data-a="pp">' + (on ? '<svg viewBox="0 0 12 12" aria-hidden="true"><rect x="1" y="1" width="3.5" height="10"/><rect x="7.5" y="1" width="3.5" height="10"/></svg>Pause' : '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2 1l9 5-9 5z"/></svg>' + (st.rp ? 'Play' : 'Replay')) + '</button>'
        + '<button type="button" class="dbd-thr-st" data-a="bk" aria-label="Back one throw"' + (at <= 1 ? ' disabled' : '') + '>‹</button><button type="button" class="dbd-thr-st" data-a="fw" aria-label="Forward one throw"' + (at >= Rs.length ? ' disabled' : '') + '>›</button></div>'
        + '<div class="dbd-thr-tl" title="Every throw in the game, in order. Tap one to see it.">' + g + '</svg><div class="dbd-thr-qs" aria-hidden="true"><span>Q1</span><span>Q2</span><span>Q3</span><span>Q4</span></div></div>'
        + '<div class="dbd-thr-rt"><span class="dbd-thr-ct">' + (at ? at + ' of ' + Rs.length : Rs.length + (Rs.length === 1 ? ' throw' : ' throws')) + '</span><span class="dbd-thr-sp" role="group" aria-label="Speed"><button type="button" data-sp="1" aria-pressed="' + (sp === 1) + '">1×</button><button type="button" data-sp="2" aria-pressed="' + (sp === 2) + '">2×</button></span></div>';
      b.onclick = function (e) {
        var t = e.target.closest('button'), tl = e.target.closest('.dbd-thr-tl');
        if (t && !t.disabled) {
          var a = t.getAttribute('data-a'), v = t.getAttribute('data-sp');
          if (v) { if (!st.rp) st.rp = { i: at, sp: 1, on: false, t: null }; st.rp.sp = +v; bar(); return; }
          if (a === 'pp') { if (!st.rp) { start(); return; } st.rp.on = !st.rp.on; clearTimeout(st.rp.t); if (st.rp.on) { if (st.rp.i >= Rs.length) st.rp.i = 0; step(1); } else bar(); return; }
          if (a === 'bk') jump(at - 1); else if (a === 'fw') jump(at + 1);
          return;
        }
        if (tl) { var bx = tl.getBoundingClientRect(), f = (e.clientX - bx.left - bx.width * 8 / W) / (bx.width * (W - 16) / W), best = 0, d = 9; Rs.forEach(function (r, i) { var dd = Math.abs(when(r) - f); if (dd < d) { d = dd; best = i; } }); jump(best + 1); }
      };
    }
    /* go to throw n (1-based): in a replay it moves the replay there, paused; otherwise it shows that throw */
    function jump(n) { var Rs = rows(); if (!Rs.length) return; n = Math.max(1, Math.min(Rs.length, n)); if (st.rp) { clearTimeout(st.rp.t); st.rp.on = false; st.rp.i = n; draw(n); show(n - 1, true); } else { st.sel = n - 1; draw(); show(n - 1, false); } bar(); }
    function start() { st.sel = -1; st.rp = { i: 0, sp: (st.rp && st.rp.sp) || 1, on: true, t: null }; step(1); }
    function stop() { if (st.rp) clearTimeout(st.rp.t); st.rp = null; }
    function step(d) {
      var Rs = rows(), P = st.rp; if (!P || !Rs.length) return; P.i = Math.max(1, Math.min(Rs.length, P.i + d)); draw(P.i); show(P.i - 1, true); bar();
      if (P.i >= Rs.length) { P.on = false; bar(); P.t = setTimeout(function () { var last = Rs.length - 1; stop(); st.sel = last; draw(); show(last, false); bar(); }, 1400); return; }  /* the end: every throw back, the last one showing */
      if (P.on) P.t = setTimeout(function () { step(1); }, STEP / P.sp);
    }

    /* the field; upto draws only the first throws (the replay) */
    function draw(upto) {
      var s = sets[st.set], all = rows(), Rs = upto == null ? all : all.slice(0, upto), narrow = sec.clientWidth < 600; sec.classList.toggle('is-narrow', narrow);
      var fld = $('.dbd-thr-fld'), W = fld.clientWidth || 600, minA = -10, maxA = Math.max(40, Math.ceil((Math.max.apply(null, all.map(function (r) { return r.a; }).concat([0])) + 3) / 5) * 5);
      var H = Math.round(narrow ? W * 1.2 : Math.min(600, Math.max(440, W * .5))), TOP = 30, lw = W / 3;
      var y = function (a) { return TOP + (maxA - Math.max(minA, Math.min(maxA, a))) / (maxA - minA) * (H - TOP - 6); };
      var g = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img" aria-label="' + Rs.length + ' throws, placed by depth and side of the field"><rect width="' + W + '" height="' + H + '" fill="#2c4a35" rx="4"/>';
      if (st.zones) {  /* the 12 zones, each shaded by its share of the throws */
        var z = zones(Rs), mx = Math.max.apply(null, [1].concat(Object.keys(z).map(function (k) { return z[k]; })));
        for (var li = 0; li < 3; li++) { for (var bi = 0; bi < 4; bi++) {
          var zn = z[li * 4 + bi] || 0, zt = y(Math.min(maxA, BANDS[bi][1])), zb = y(Math.max(minA, BANDS[bi][0]));
          g += '<rect x="' + (lw * li + 2) + '" y="' + (zt + 2) + '" width="' + (lw - 4) + '" height="' + Math.max(0, zb - zt - 4) + '" fill="rgba(255,255,255,' + (zn ? (.05 + .22 * zn / mx).toFixed(3) : 0) + ')" stroke="rgba(255,255,255,.35)"' + (zn ? '' : ' stroke-dasharray="4 4"') + '/>'
            + '<text x="' + (lw * li + lw - 10) + '" y="' + (zb - 10) + '" fill="rgba(255,255,255,' + (zn ? .9 : .45) + ')" font-family="IBM Plex Mono,monospace" font-size="12" text-anchor="end">' + zn + '</text>';
        } }
      }
      for (var a = minA; a <= maxA; a += 5) {
        g += '<line x1="0" x2="' + W + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="rgba(255,255,255,' + (a === 0 ? .85 : a % 10 ? .08 : .2) + ')" stroke-width="' + (a === 0 ? 2 : 1) + '"/>';
        if (a % 10 === 0 && a >= 0 && a < maxA) g += '<text x="8" y="' + (y(a) - 5) + '" fill="rgba(255,255,255,.75)" font-family="IBM Plex Mono,monospace" font-size="11">' + (a === 0 ? 'LINE' : a + ' YDS') + '</text>';
      }
      ['Left', 'Middle', 'Right'].forEach(function (n, i) {
        if (i) g += '<line x1="' + lw * i + '" x2="' + lw * i + '" y1="' + TOP + '" y2="' + y(0) + '" stroke="rgba(255,255,255,.2)" stroke-dasharray="3 6"/>';
        var lr = Rs.filter(function (r) { return r.l === i; }), c = lr.filter(function (r) { return r.k === 'c'; }).length;
        g += '<text x="' + (lw * i + lw / 2) + '" y="20" fill="#fff" font-family="Barlow Condensed,sans-serif" font-weight="700" font-size="13" letter-spacing="1.5" text-anchor="middle">' + n.toUpperCase() + ' <tspan font-family="IBM Plex Mono,monospace" font-weight="500" letter-spacing="0" fill="rgba(255,255,255,.75)">' + c + '/' + lr.length + '</tspan></text>';
      });
      var qx = W / 2, qy = y(-6), small = !!s.season, r0 = small ? (narrow ? 4 : 4.5) : (narrow ? 6 : 7), pos = [];
      g += '<g class="arc"></g>';
      Rs.forEach(function (r, i) {
        var li = r.l < 0 ? 1 : r.l, lo = li === 0 ? 56 : lw * .1, cx = lw * li + lo + (lw * .9 - lo) * rnd(i * 7 + r.a + r.q), cy = y(r.a) + (rnd(i + 3) - .5) * 4;
        if (Math.abs(cx - qx) < 22 && cy > qy - 22) cx += cx < qx ? -24 : 24;  /* clear of the quarterback */
        pos[i] = [Math.round(cx * 10) / 10, Math.round(cy * 10) / 10];
        g += dot(kind(r), pos[i][0], pos[i][1], r0, (st.hl && r.r !== st.hl ? ' opacity=".14"' : small ? ' opacity=".7"' : '') + ' data-i="' + i + '" tabindex="0" style="cursor:pointer"');
      });
      var q0 = st.qb || (Rs[0] || all[0] || {}).qb;
      g += '<g class="qb" transform="translate(' + qx + ',' + qy + ')"><circle r="17" fill="' + esc(s.c1) + '" stroke="#fff" stroke-width="2"/><text y="5.5" fill="#fff" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="15" text-anchor="middle">' + esc(jersey(q0)) + '</text><title>' + esc(name(q0)) + '</title></g>';
      fld.innerHTML = g + '</svg>';
      sec.style.setProperty('--thr-qb', s.c1);
      G = { svg: fld.querySelector('svg'), qx: qx, qy: qy, pos: pos, R: Rs, r0: r0, W: W, TOP: TOP };
      var pickDot = function (e) {
        var t = e.target && e.target.closest ? e.target.closest('[data-i]') : null; if (!t) return; var i = +t.getAttribute('data-i');
        if (st.rp) { if (!st.rp.on && e.type === 'click') jump(i + 1); return; }
        st.sel = i; show(i, false); bar();
      };
      G.svg.addEventListener('mouseover', pickDot); G.svg.addEventListener('focusin', pickDot); G.svg.addEventListener('click', pickDot);
      if (!st.rp && st.sel >= 0 && st.sel < Rs.length) show(st.sel, false);
    }

    /* one throw: the line from the quarterback to the ball, the receiver named at the end; anim draws the line (the replay) */
    function show(i, anim) {
      var r = G.R[i], p = G.pos[i]; if (!r || !p) return; var qx = G.qx, qy = G.qy;
      var dx = p[0] - qx, dy = p[1] - qy, sg = dx >= 0 ? 1 : -1, mx = (qx + p[0]) / 2 + dy * .16 * sg, my = (qy + p[1]) / 2 - dx * .16 * sg;  /* the ball's arc bows up the field */
      var col = r.k === 'x' ? COL.x : r.td ? COL.td : '#fff', dash = r.k === 'i' ? ' stroke-dasharray="6 6"' : '';
      var lab = name(r.r) ? short(name(r.r)) + (jersey(r.r) ? ' · ' + jersey(r.r) : '') : 'No receiver', tw = lab.length * 7 + 16, lx = Math.max(4, Math.min(G.W - tw - 4, p[0] - tw / 2)), ly = p[1] - 34 < G.TOP ? p[1] + 14 : p[1] - 34;
      var arc = G.svg.querySelector('.arc'), qb = G.svg.querySelector('.qb');
      arc.innerHTML = '<path class="dbd-thr-arc" d="M' + qx + ' ' + qy + 'Q' + mx + ' ' + my + ' ' + p[0] + ' ' + p[1] + '" fill="none" stroke="' + col + '" stroke-width="2.5"' + (anim && !still ? '' : dash) + ' stroke-linecap="round"/>'
        + '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="' + (G.r0 + 5) + '" fill="none" stroke="#fff" stroke-width="2"/>'
        + '<rect x="' + lx + '" y="' + ly + '" width="' + tw + '" height="22" rx="3" fill="rgba(10,14,12,.88)"/><text x="' + (lx + tw / 2) + '" y="' + (ly + 15) + '" fill="#fff" font-family="IBM Plex Sans,sans-serif" font-weight="600" font-size="12" text-anchor="middle">' + esc(lab) + '</text>';
      if (anim && !still) {
        var pa = arc.querySelector('path'), L = pa.getTotalLength ? pa.getTotalLength() : 0;
        if (L) { pa.style.setProperty('--len', L); pa.classList.add('is-drawing'); setTimeout(function () { pa.classList.remove('is-drawing'); if (r.k === 'i') pa.setAttribute('stroke-dasharray', '6 6'); }, 750); }  /* the finished line, even where the animation doesn't run */
      }
      qb.querySelector('text').textContent = jersey(r.qb); qb.querySelector('title').textContent = name(r.qb);
      now(r);
    }

    /* 2. the throw you're on */
    function now(r) {
      var b = $('.dbd-thr-now'), s = sets[st.set];
      if (!r) { b.className = 'dbd-thr-now is-empty'; b.textContent = s.season ? 'Hover or tap any throw to see it.' : 'Hover or tap a throw on the field or the timeline, or press Replay to watch them go out in order.'; return; }
      var rn = name(r.r), what = r.k === 'c' ? 'Complete' : r.k === 'x' ? 'Intercepted' : 'Incomplete', to = rn ? (r.k === 'x' ? ', intended for ' : r.k === 'c' ? ' to ' : ', thrown to ') + esc(rn) : '';
      var where = ['left', 'middle', 'right'][r.l], m = r.sd == null ? '' : r.sd === 0 ? 'tied' : r.sd > 0 ? 'up ' + r.sd : 'down ' + (-r.sd);
      var ctx = (r.gl ? esc(r.gl) + ' · ' : '') + 'Q' + r.q + ' ' + esc(r.c) + (m ? ' · ' + esc(r.tn) + ' ' + m : '') + (r.dn ? ' · ' + ord(r.dn) + ' &amp; ' + r.tg : '') + (where ? ' · thrown ' + where : '') + (D.kind === 'game' && sets[st.set].R.some(function (x) { return x.qb !== r.qb; }) ? ' · ' + esc(name(r.qb)) : '');
      b.className = 'dbd-thr-now';
      b.innerHTML = '<div><div class="hd"><i style="background:' + TICK[kind(r)] + '"></i>' + what + to + (r.td ? ', touchdown' : '') + '</div><div class="ctx">' + ctx + '</div></div>'
        + '<div class="nums"><div><b>' + r.a + '</b><span>Air yds</span></div><div><b>' + (r.k === 'c' ? r.y : '–') + '</b><span>Yards</span></div><div><b>' + (r.e > 0 ? '+' : r.e < 0 ? '−' : '') + Math.abs(r.e).toFixed(2) + '</b><span>EPA</span></div></div>';
    }

    render();
    var lw0 = sec.clientWidth, t; window.addEventListener('resize', function () { if (sec.clientWidth === lw0) return; lw0 = sec.clientWidth; clearTimeout(t); t = setTimeout(function () { if (st.rp) { draw(st.rp.i); show(st.rp.i - 1, false); } else render(); }, 150); });
    /* a panel inside a closed tab or band has no width yet: draw it when it first shows */
    if (!sec.clientWidth && 'ResizeObserver' in window) { var ro = new ResizeObserver(function () { if (sec.clientWidth) { ro.disconnect(); lw0 = sec.clientWidth; render(); } }); ro.observe(sec); }
  }
  function boot() { [].forEach.call(document.querySelectorAll('.dbd-thr:not([data-kind=rx]):not([data-kind=rb])'), mount); }  /* a receiver's or back's panel is touchchart.js's (SCOPE §98) */
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
