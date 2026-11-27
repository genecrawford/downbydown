/* Every target, every carry (2.56.0, SCOPE §98): the quarterback chart's field (passchart.js) for the players on the other end of the ball.
   A receiver or tight end ("Where he's targeted"): each target a dot where the ball reached him (air yards, left/middle/right as the
   quarterback sees it); a catch gets a tail up to where the play ended (yards after the catch); incomplete, dropped, intercepted and
   touchdown each have a mark, a lost fumble a red ring. The passer stands behind the line with his number; pick a target and the throw
   arcs from him.
   A back ("Where he gets the ball"): his designed runs and his catches on one field. Seven lanes, the gaps the play data names, with
   LT LG C RG RT on the line; each carry a dot at the yards it gained (a loss below the line), successful solid, the rest hollow. He lines
   up behind the quarterback; pick a carry and the run draws from him, out beside the quarterback, up through his gap (it lights up) and on
   to where he was stopped. A catch is a diamond with its tail, and picking it draws the throw from the quarterback.
   Under the field, as on the quarterback's chart: the replay bar (one game at a time), the play you're on, the game's line, a table and
   the key. Data: the panel's embedded JSON (DBD_Throws::player). */
(function () {
  var COL = { c: '#5b9cf6', i: '#c9c6d1', x: '#e0564b', td: '#4fd18b', dr: '#f0b04a', ok: '#5b9cf6', no: '#c9c6d1', rec: '#c4a8ff' };   /* on the field */
  var TICK = { c: '#5b9cf6', i: '#a9a5b4', x: '#e0564b', td: '#2fb873', dr: '#d98a1f', ok: '#5b9cf6', no: '#a9a5b4', rec: '#8e6bd8' };  /* on the page's light ground */
  var BANDS = [[-99, 0, 'Behind the line'], [0, 10, '0 to 9 yds'], [10, 20, '10 to 19 yds'], [20, 999, '20+ yds']];
  var GAPS = [['L End', 'LE', 'left end'], ['L Tackle', 'LT', 'left tackle'], ['L Guard', 'LG', 'left guard'], ['Middle', 'M', 'up the middle'], ['R Guard', 'RG', 'right guard'], ['R Tackle', 'RT', 'right tackle'], ['R End', 'RE', 'right end']];
  var LINE = [['LT', 1], ['LG', 2], ['C', 3.5], ['RG', 5], ['RT', 6]];  /* where each lineman stands, in lane widths from the left */
  var STEP = 1100;
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function rnd(i) { var x = Math.sin(i * 12.9898 + 78.233) * 43758.5453; return x - Math.floor(x); }
  function ord(n) { return n + (['th', 'st', 'nd', 'rd'][n] || 'th'); }
  function num(v) { return Number(v).toLocaleString('en-US'); }
  function short(n) { var p = String(n || '').split(' '); return p.length > 1 && p[0].indexOf('.') < 0 ? p[0].charAt(0) + '. ' + p.slice(1).join(' ') : String(n || ''); }
  function secs(c) { var p = String(c || '').split(':'); return (+p[0] || 0) * 60 + (+p[1] || 0); }
  function when(r) { return Math.max(0, Math.min(1, ((r.q - 1) * 900 + (900 - secs(r.c))) / 3600)); }
  function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }
  function sgn(v) { return (v > 0 ? '+' : v < 0 ? '−' : '') + Math.abs(v).toFixed(2); }
  /* a target: q, clock, down, distance, result, air, yards, lane, td, passer, epa, margin, yac, dropped, contested, fumble lost */
  function T(a) { return { run: false, q: a[0], c: a[1], dn: a[2], tg: a[3], k: a[4], a: a[5], y: a[6], l: a[7], td: a[8], qb: a[9], e: a[10], sd: a[11], x: a[12] || 0, dr: a[13], ct: a[14], fl: a[15] }; }
  /* a carry: q, clock, down, distance, yards, gap, td, successful, first down, epa, margin, fumble lost */
  function C(a) { return { run: true, q: a[0], c: a[1], dn: a[2], tg: a[3], y: a[4], gp: a[5], td: a[6], su: a[7], fd: a[8], e: a[9], sd: a[10], fl: a[11] }; }
  function byTime(a, b) { return a.q - b.q || secs(b.c) - secs(a.c); }

  function mount(sec) {
    var D; try { D = JSON.parse(sec.querySelector('.dbd-thr-d').textContent); } catch (e) { return; }
    var back = D.kind === 'rb', N = D.names || {}, sets = D.sets || []; if (!sets.length) return;
    sets.forEach(function (s) { s.R = (back ? (s.c || []).map(C) : []).concat((s.tg || []).map(T)).sort(byTime); s.R.forEach(function (r) { r.tn = s.nick; }); });
    var first = 0;
    if (sets.length > 1) {  /* the whole season, ahead of the games; his newest game stays the default */
      var all = [], by = []; sets.slice().reverse().forEach(function (s) { s.R.forEach(function (r) { var x = Object.create(r); x.gl = s.label; all.push(x); }); if (by.indexOf(s.nick) < 0) by.push(s.nick); });
      sets.unshift({ season: true, label: 'Whole season', nick: by.join(' and the '), c1: sets[0].c1, R: all }); first = 1;
    }
    var noun = back ? 'touches' : 'targets', one = back ? 'touch' : 'target';
    var body = sec.querySelector('.dbd-thr-b'), pick = sec.querySelector('.dbd-thr-pick'), st = { set: first, hl: '', sel: -1, rp: null }, G = {};
    var name = function (k) { return (N[k] || [])[0] || ''; }, jersey = function (k) { return (N[k] || [])[1] || ''; };
    function kind(r) { if (r.run) return r.td ? 'td' : r.su ? 'ok' : 'no'; if (back) return r.td ? 'td' : 'rec'; return r.td ? 'td' : r.k === 'i' && r.dr ? 'dr' : r.k; }
    function mark(k, x, y, rr, extra, P, diamond) {
      P = P || COL;
      if (diamond) { var s2 = rr + 1.5; return '<path d="M' + x + ' ' + (y - s2) + 'L' + (x + s2) + ' ' + y + 'L' + x + ' ' + (y + s2) + 'L' + (x - s2) + ' ' + y + 'Z" fill="' + P[k] + '" stroke="#fff" stroke-width="1.2"' + extra + '/>'; }
      if (k === 'i' || k === 'no') return '<circle cx="' + x + '" cy="' + y + '" r="' + (rr - 1) + '" fill="rgba(0,0,0,.001)" stroke="' + P[k] + '" stroke-width="2"' + extra + '/>';
      if (k === 'dr') { var e = rr * .5; return '<g' + extra + '><circle cx="' + x + '" cy="' + y + '" r="' + (rr - 1) + '" fill="rgba(0,0,0,.001)" stroke="' + P.dr + '" stroke-width="2"/><path d="M' + (x - e) + ' ' + (y + e) + 'L' + (x + e) + ' ' + (y - e) + '" stroke="' + P.dr + '" stroke-width="2"/></g>'; }
      if (k === 'x') { var d = rr * .45; return '<g' + extra + '><circle cx="' + x + '" cy="' + y + '" r="' + rr + '" fill="' + P.x + '"/><path d="M' + (x - d) + ' ' + (y - d) + 'L' + (x + d) + ' ' + (y + d) + 'M' + (x + d) + ' ' + (y - d) + 'L' + (x - d) + ' ' + (y + d) + '" stroke="#fff" stroke-width="1.6"/></g>'; }
      return '<circle cx="' + x + '" cy="' + y + '" r="' + rr + '" fill="' + P[k] + '"' + (k === 'td' ? ' stroke="#fff" stroke-width="1.5"' : '') + extra + '/>';
    }
    var lg = function (k, l, dia) { return '<span><svg width="16" height="16" aria-hidden="true">' + mark(k, 8, 8, 5.5, '', TICK, dia) + '</svg>' + l + '</span>'; };
    var tail = '<span><svg width="16" height="16" aria-hidden="true"><line x1="8" y1="15" x2="8" y2="3" stroke="#3b4255" stroke-width="2"/><circle cx="8" cy="3" r="2" fill="#3b4255"/></svg>Yards after the catch</span>';
    var fum = '<span><svg width="16" height="16" aria-hidden="true"><circle cx="8" cy="8" r="4" fill="#a9a5b4"/><circle cx="8" cy="8" r="7" fill="none" stroke="#e0564b" stroke-width="1.8"/></svg>Fumbled and lost</span>';
    var key = back
      ? lg('ok', 'Run, successful') + lg('no', 'Run, not successful') + lg('td', 'Touchdown') + lg('rec', 'Catch', true) + tail + fum + '<span><svg width="16" height="16" aria-hidden="true"><circle class="dbd-thr-qk" cx="8" cy="8" r="7.5"/></svg>Him, with the quarterback in front</span>'
      : lg('c', 'Caught') + tail + lg('i', 'Incomplete') + lg('dr', 'Dropped') + lg('x', 'Intercepted') + lg('td', 'Touchdown') + fum + '<span><svg width="16" height="16" aria-hidden="true"><circle class="dbd-thr-qk" cx="8" cy="8" r="7.5"/></svg>The passer, behind the line</span>';
    body.innerHTML = '<div class="dbd-thr-fld"></div><div class="dbd-thr-bar"></div><div class="dbd-thr-now" aria-live="polite"></div><p class="dbd-thr-sum"></p>'
      + '<div class="dbd-thr-g2"><div><p class="dbd-thr-hh">' + (back ? 'By gap, and his catches' : 'By depth') + '</p><table class="dbd-thr-dp"></table></div><div><p class="dbd-thr-hh">The marks</p><div class="dbd-thr-key">' + key + '</div></div></div>'
      + '<div class="dbd-thr-hl"><div class="dbd-thr-recs dbd-thr-chips"></div></div>'
      + '<p class="dbd-note">' + (back
        ? 'Runs and catches share one field. A run is drawn from where he lines up, through the gap the play data names (not the exact spot, so dots spread across their lane), on to where he was stopped; a loss ends below the line. Designed runs only: scrambles and kneels are left out. A catch is drawn as the throw, from the quarterback to where the ball reached him, with a tail for the yards after. A run is successful when it gains 40% of the yards needed on 1st down, 60% on 2nd, or all of them on 3rd or 4th.'
        : 'Left and right are as the quarterback sees the field. A dot sits at the air yards, where the ball reached him; a catch’s tail runs up to where the play ended. The play data says left, middle or right, not exactly where, so a dot’s place across its third is spread out.') + '</p>';
    var $ = function (c) { return body.querySelector(c); };
    pick.innerHTML = '<select aria-label="Which game">' + sets.map(function (s, i) { return '<option value="' + i + '"' + (i === first ? ' selected' : '') + '>' + esc(s.label + (s.season ? '' : ' · ' + s.nick) + ' · ' + plural(s.R.length, one, noun)) + '</option>'; }).join('') + '</select>';
    pick.querySelector('select').onchange = function () { stop(); st.set = +this.value; st.hl = ''; st.sel = -1; render(); };
    $('.dbd-thr-recs').onclick = function (e) { var b = e.target.closest('button'); if (!b) return; st.hl = b.getAttribute('data-q'); render(); };

    function rows() { return sets[st.set].R; }
    function render() {
      var s = sets[st.set], Rs = rows(), who = s.season ? 'This season' + (sets.length > 2 && s.nick.indexOf(' and ') > 0 ? ', for the ' + s.nick : '') : s.label;
      if (back) {
        var runs = Rs.filter(function (r) { return r.run; }), ca = Rs.filter(function (r) { return !r.run; }), y = runs.reduce(function (t, r) { return t + r.y; }, 0), su = runs.filter(function (r) { return r.su; }).length, td = Rs.filter(function (r) { return r.td; }).length, lng = runs.length ? Math.max.apply(null, runs.map(function (r) { return r.y; })) : 0, cy = ca.reduce(function (t, r) { return t + r.y; }, 0);
        $('.dbd-thr-sum').innerHTML = esc(who) + ': <b>' + plural(runs.length, 'carry', 'carries') + ' for ' + num(y) + ' yards</b>' + (runs.length ? ', ' + (y / runs.length).toFixed(1) + ' a carry, ' + Math.round(su / runs.length * 100) + '% successful, longest ' + lng : '') + '; <b>' + plural(ca.length, 'catch', 'catches') + ' for ' + num(cy) + ' yards</b>; ' + td + ' TD.';
        var dp = '<thead><tr><th scope="col">Gap</th><th scope="col">Car</th><th scope="col">Yds</th><th scope="col">Avg</th><th scope="col">Success</th></tr></thead><tbody>';
        GAPS.forEach(function (g, i) { var gs = runs.filter(function (r) { return r.gp === i; }), gy = gs.reduce(function (t, r) { return t + r.y; }, 0); dp += '<tr><th scope="row">' + g[0] + '</th><td>' + gs.length + '</td><td>' + (gs.length ? num(gy) : '–') + '</td><td>' + (gs.length ? (gy / gs.length).toFixed(1) : '–') + '</td><td>' + (gs.length ? Math.round(gs.filter(function (r) { return r.su; }).length / gs.length * 100) + '%' : '–') + '</td></tr>'; });
        dp += '<tr><th scope="row"><b>Catches</b></th><td>' + ca.length + '</td><td>' + num(cy) + '</td><td>' + (ca.length ? (cy / ca.length).toFixed(1) : '–') + '</td><td>' + num(ca.reduce(function (t, r) { return t + r.x; }, 0)) + ' YAC</td></tr>';
        $('.dbd-thr-dp').innerHTML = dp + '</tbody>';
      } else {
        var c = Rs.filter(function (r) { return r.k === 'c'; }), yy = c.reduce(function (t, r) { return t + r.y; }, 0), yac = c.reduce(function (t, r) { return t + r.x; }, 0), tds = Rs.filter(function (r) { return r.td; }).length, drp = Rs.filter(function (r) { return r.dr; }).length;
        $('.dbd-thr-sum').innerHTML = esc(who) + ': <b>' + plural(c.length, 'catch', 'catches') + ' on ' + plural(Rs.length, 'target', 'targets') + ', ' + num(yy) + ' yards</b>, ' + num(yac) + ' after the catch, ' + tds + ' TD, ' + plural(drp, 'drop', 'drops') + '.';
        var dt = '<thead><tr><th scope="col">Depth</th><th scope="col">Rec</th><th scope="col">Yds</th><th scope="col">YAC</th><th scope="col">TD</th></tr></thead><tbody>';
        BANDS.slice().reverse().forEach(function (b) { var bs = Rs.filter(function (r) { return r.a >= b[0] && r.a < b[1]; }), cc = bs.filter(function (r) { return r.k === 'c'; });
          dt += '<tr><th scope="row">' + b[2] + '</th><td>' + cc.length + '/' + bs.length + '</td><td>' + num(cc.reduce(function (t, r) { return t + r.y; }, 0)) + '</td><td>' + num(cc.reduce(function (t, r) { return t + r.x; }, 0)) + '</td><td>' + bs.filter(function (r) { return r.td; }).length + '</td></tr>'; });
        $('.dbd-thr-dp').innerHTML = dt + '</tbody>';
      }
      /* highlight a passer, when more than one threw to him */
      var n = {}; Rs.forEach(function (r) { if (!r.run && r.qb) n[r.qb] = (n[r.qb] || 0) + 1; }); var ks = Object.keys(n).sort(function (a, b) { return n[b] - n[a]; }), re = $('.dbd-thr-recs');
      re.hidden = ks.length < 2;
      if (!re.hidden) re.innerHTML = '<span class="cl">Highlight a passer</span><button type="button" data-q="" aria-pressed="' + !st.hl + '">Everyone</button>' + ks.map(function (k) { return '<button type="button" data-q="' + esc(k) + '" aria-pressed="' + (st.hl === k) + '">' + esc(name(k) || 'Unknown') + ' · ' + n[k] + '</button>'; }).join('');
      draw(st.rp ? st.rp.i : null); bar(); if (!st.rp && st.sel < 0) now(null);
    }

    /* the replay bar */
    function bar() {
      var s = sets[st.set], Rs = rows(), b = $('.dbd-thr-bar');
      if (s.season) { b.className = 'dbd-thr-bar is-season'; b.textContent = 'The replay works one game at a time. Pick a game above to watch his ' + noun + ' in order.'; return; }
      b.className = 'dbd-thr-bar'; var at = st.rp ? st.rp.i : (st.sel >= 0 ? st.sel + 1 : 0), on = !!(st.rp && st.rp.on), W = 1000, x = function (r) { return 8 + when(r) * (W - 16); };
      var g = '<svg viewBox="0 0 ' + W + ' 30" preserveAspectRatio="none" aria-hidden="true"><rect x="8" y="13" width="' + (W - 16) + '" height="4" rx="2" fill="rgba(22,30,54,.12)"/>';
      if (at) g += '<rect x="8" y="13" width="' + Math.max(0, x(Rs[at - 1]) - 8) + '" height="4" rx="2" fill="#121417"/>';
      [1, 2, 3].forEach(function (q) { var qx = 8 + q / 4 * (W - 16); g += '<line x1="' + qx + '" x2="' + qx + '" y1="8" y2="22" stroke="rgba(22,30,54,.3)"/>'; });
      Rs.forEach(function (r, i) { var cur = at === i + 1; g += '<rect x="' + (x(r) - (cur ? 2.5 : 1.5)) + '" y="' + (cur ? 0 : 4) + '" width="' + (cur ? 5 : 3) + '" height="' + (cur ? 30 : 22) + '" rx="1" fill="' + TICK[kind(r)] + '"' + (st.rp && i >= st.rp.i ? ' opacity=".25"' : '') + '/>'; });
      var sp = st.rp ? st.rp.sp : 1;
      b.innerHTML = '<div class="dbd-thr-btns"><button type="button" class="dbd-thr-pp" data-a="pp">' + (on ? '<svg viewBox="0 0 12 12" aria-hidden="true"><rect x="1" y="1" width="3.5" height="10"/><rect x="7.5" y="1" width="3.5" height="10"/></svg>Pause' : '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="M2 1l9 5-9 5z"/></svg>' + (st.rp ? 'Play' : 'Replay')) + '</button>'
        + '<button type="button" class="dbd-thr-st" data-a="bk" aria-label="Back one ' + one + '"' + (at <= 1 ? ' disabled' : '') + '>‹</button><button type="button" class="dbd-thr-st" data-a="fw" aria-label="Forward one ' + one + '"' + (at >= Rs.length ? ' disabled' : '') + '>›</button></div>'
        + '<div class="dbd-thr-tl" title="Every ' + one + ' in the game, in order. Tap one to see it.">' + g + '</svg><div class="dbd-thr-qs" aria-hidden="true"><span>Q1</span><span>Q2</span><span>Q3</span><span>Q4</span></div></div>'
        + '<div class="dbd-thr-rt"><span class="dbd-thr-ct">' + (at ? at + ' of ' + Rs.length : plural(Rs.length, one, noun)) + '</span><span class="dbd-thr-sp" role="group" aria-label="Speed"><button type="button" data-sp="1" aria-pressed="' + (sp === 1) + '">1×</button><button type="button" data-sp="2" aria-pressed="' + (sp === 2) + '">2×</button></span></div>';
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
    function jump(n) { var Rs = rows(); if (!Rs.length) return; n = Math.max(1, Math.min(Rs.length, n)); if (st.rp) { clearTimeout(st.rp.t); st.rp.on = false; st.rp.i = n; draw(n); show(n - 1, true); } else { st.sel = n - 1; draw(); show(n - 1, false); } bar(); }
    function start() { st.sel = -1; st.rp = { i: 0, sp: (st.rp && st.rp.sp) || 1, on: true, t: null }; step(1); }
    function stop() { if (st.rp) clearTimeout(st.rp.t); st.rp = null; }
    function step(d) {
      var Rs = rows(), P = st.rp; if (!P || !Rs.length) return; P.i = Math.max(1, Math.min(Rs.length, P.i + d)); draw(P.i); show(P.i - 1, true); bar();
      if (P.i >= Rs.length) { P.on = false; bar(); P.t = setTimeout(function () { var last = Rs.length - 1; stop(); st.sel = last; draw(); show(last, false); bar(); }, 1400); return; }
      if (P.on) P.t = setTimeout(function () { step(1); }, STEP / P.sp);
    }

    /* the field; upto draws only the first plays (the replay) */
    function draw(upto) {
      var s = sets[st.set], all = rows(), Rs = upto == null ? all : all.slice(0, upto), narrow = sec.clientWidth < 600; sec.classList.toggle('is-narrow', narrow);
      var fld = $('.dbd-thr-fld'), W = fld.clientWidth || 600, cols = back ? 7 : 3, lw = W / cols, third = W / 3;
      var reach = all.map(function (r) { return r.run ? r.y : (r.k === 'c' ? Math.max(r.a, r.a + r.x) : r.a); });
      var lo = back ? Math.min(-10, Math.floor((Math.min.apply(null, reach.concat([0])) - 2) / 5) * 5) : -10, hi = Math.max(back ? 20 : 30, Math.ceil((Math.max.apply(null, reach.concat([0])) + 3) / 5) * 5);
      var H = Math.round(narrow ? W * 1.25 : Math.min(620, Math.max(460, W * .52))), TOP = 30;
      var y = function (a) { return TOP + (hi - Math.max(lo, Math.min(hi, a))) / (hi - lo) * (H - TOP - 6); };
      var g = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img" aria-label="' + plural(Rs.length, one, noun) + ', placed by ' + (back ? 'gap and yards gained' : 'depth and side of the field') + '"><rect width="' + W + '" height="' + H + '" fill="#2c4a35" rx="4"/>';
      for (var a = lo; a <= hi; a += 5) {
        g += '<line x1="0" x2="' + W + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="rgba(255,255,255,' + (a === 0 ? .85 : a % 10 ? .08 : .2) + ')" stroke-width="' + (a === 0 ? 2 : 1) + '"/>';
        if (a % 10 === 0 && a >= 0 && a < hi) g += '<text x="8" y="' + (y(a) - 5) + '" fill="rgba(255,255,255,.75)" font-family="IBM Plex Mono,monospace" font-size="11">' + (a === 0 ? 'LINE' : a + ' YDS') + '</text>';
      }
      for (var i = 0; i < cols; i++) {
        if (i) g += '<line x1="' + lw * i + '" x2="' + lw * i + '" y1="' + TOP + '" y2="' + y(0) + '" stroke="rgba(255,255,255,.2)" stroke-dasharray="3 6"/>';
        if (back) { var gs = Rs.filter(function (r) { return r.run && r.gp === i; }), gy = gs.reduce(function (t, r) { return t + r.y; }, 0);
          g += '<text x="' + (lw * i + lw / 2) + '" y="20" fill="#fff" font-family="Barlow Condensed,sans-serif" font-weight="700" font-size="13" letter-spacing="1.2" text-anchor="middle">' + (narrow ? GAPS[i][1] : GAPS[i][0].toUpperCase()) + ' <tspan font-family="IBM Plex Mono,monospace" font-weight="500" letter-spacing="0" fill="rgba(255,255,255,.75)">' + gs.length + (gs.length && !narrow ? ' · ' + (gy / gs.length).toFixed(1) : '') + '</tspan></text>'; }
        else { var ls = Rs.filter(function (r) { return r.l === i; }), cc = ls.filter(function (r) { return r.k === 'c'; }).length;
          g += '<text x="' + (lw * i + lw / 2) + '" y="20" fill="#fff" font-family="Barlow Condensed,sans-serif" font-weight="700" font-size="13" letter-spacing="1.5" text-anchor="middle">' + ['LEFT', 'MIDDLE', 'RIGHT'][i] + ' <tspan font-family="IBM Plex Mono,monospace" font-weight="500" letter-spacing="0" fill="rgba(255,255,255,.75)">' + cc + '/' + ls.length + '</tspan></text>'; }
      }
      var x0 = W / 2, qbY = y(back ? -3.4 : -6), rbY = y(-7.2), small = !!s.season, r0 = small ? (narrow ? 4 : 4.5) : (narrow ? 6 : 7), pos = [];
      g += '<g class="hole"></g><g class="tails"></g><g class="arc"></g>';
      if (back) LINE.forEach(function (o) { var ox = lw * o[1], bw = narrow ? 22 : 28; g += '<rect x="' + (ox - bw / 2) + '" y="' + (y(0) - 8) + '" width="' + bw + '" height="16" rx="3" fill="rgba(255,255,255,.9)"/><text x="' + ox + '" y="' + (y(0) + 4) + '" fill="#2c4a35" font-family="Barlow Condensed,sans-serif" font-weight="700" font-size="11" text-anchor="middle">' + o[0] + '</text>'; });
      var tails = '', dots = '';
      Rs.forEach(function (r, i) {
        var cx, cy;
        if (r.run) { cx = lw * r.gp + lw * .14 + lw * .72 * rnd(i * 7 + r.y + r.q); cy = y(r.y) + (rnd(i + 3) - .5) * 4; if (Math.abs(cy - y(0)) < 12) cy = r.y >= 0 ? y(0) - 13 : y(0) + 13; }  /* off the line and the linemen */
        else { var li = r.l < 0 ? 1 : r.l, span = back ? third : lw, lo2 = !back && li === 0 ? 56 : span * .1; cx = span * li + lo2 + (span * .9 - lo2) * rnd(i * 7 + r.a + r.q); cy = y(r.a) + (rnd(i + 3) - .5) * 4; }
        if (Math.abs(cx - x0) < 22 && cy > qbY - 22) cx += cx < x0 ? -24 : 24;  /* clear of the backfield */
        pos[i] = [Math.round(cx * 10) / 10, Math.round(cy * 10) / 10];
        var dim = st.hl && (r.run || r.qb !== st.hl) ? ' opacity=".14"' : small ? ' opacity=".7"' : '';
        if (!r.run && r.k === 'c' && r.x) tails += '<line x1="' + pos[i][0] + '" y1="' + pos[i][1] + '" x2="' + pos[i][0] + '" y2="' + y(r.a + r.x) + '" stroke="rgba(255,255,255,.55)" stroke-width="2"' + dim + '/><circle cx="' + pos[i][0] + '" cy="' + y(r.a + r.x) + '" r="2.2" fill="rgba(255,255,255,.8)"' + dim + '/>';
        if (r.fl) dots += '<circle cx="' + pos[i][0] + '" cy="' + pos[i][1] + '" r="' + (r0 + 3.5) + '" fill="none" stroke="' + COL.x + '" stroke-width="2"' + dim + '/>';
        dots += mark(kind(r), pos[i][0], pos[i][1], r0, dim + ' data-i="' + i + '" tabindex="0" style="cursor:pointer"', COL, back && !r.run);
      });
      g = g.replace('<g class="tails"></g>', '<g class="tails">' + tails + '</g>') + dots;
      var cnt = {}; all.forEach(function (r) { if (!r.run && r.qb) cnt[r.qb] = (cnt[r.qb] || 0) + 1; }); var q0 = Object.keys(cnt).sort(function (a2, b2) { return cnt[b2] - cnt[a2]; })[0] || '';
      g += '<g class="qb" transform="translate(' + x0 + ',' + qbY + ')"><circle r="' + (back ? 12 : 17) + '" fill="' + esc(s.c1) + '" stroke="#fff" stroke-width="2"' + (back ? ' opacity=".8"' : '') + '/><text y="' + (back ? 4.2 : 5.5) + '" fill="#fff" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="' + (back ? 11 : 15) + '" text-anchor="middle">' + esc(jersey(q0)) + '</text><title>' + esc(name(q0)) + '</title></g>';
      if (back) g += '<g transform="translate(' + x0 + ',' + rbY + ')"><circle r="17" fill="' + esc(s.c1) + '" stroke="#fff" stroke-width="2.5"/><text y="5.5" fill="#fff" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="15" text-anchor="middle">' + esc(D.me || '') + '</text></g>';
      fld.innerHTML = g + '</svg>';
      sec.style.setProperty('--thr-qb', s.c1);
      G = { svg: fld.querySelector('svg'), x0: x0, qbY: qbY, rbY: rbY, pos: pos, R: Rs, r0: r0, W: W, TOP: TOP, y: y, lw: lw };
      var pickDot = function (e) {
        var t = e.target && e.target.closest ? e.target.closest('[data-i]') : null; if (!t) return; var k = +t.getAttribute('data-i');
        if (st.rp) { if (!st.rp.on && e.type === 'click') jump(k + 1); return; }
        st.sel = k; show(k, false); bar();
      };
      G.svg.addEventListener('mouseover', pickDot); G.svg.addEventListener('focusin', pickDot); G.svg.addEventListener('click', pickDot);
      if (!st.rp && st.sel >= 0 && st.sel < Rs.length) show(st.sel, false);
    }

    /* one play: a run drawn as a run, a throw as a throw; anim draws the line (the replay) */
    function show(i, anim) {
      var r = G.R[i], p = G.pos[i]; if (!r || !p) return;
      var arc = G.svg.querySelector('.arc'), hole = G.svg.querySelector('.hole'), qb = G.svg.querySelector('.qb'), d, col = r.td ? COL.td : (!r.run && r.k === 'x') ? COL.x : '#fff';
      if (r.run) {
        /* from him, out beside the quarterback, up through his gap at the line, on to where he was stopped */
        var hx = G.lw * r.gp + G.lw / 2, hy = G.y(0), sx = G.x0, sy = G.rbY, side = (hx - sx) || (p[0] - sx) || 1; side = side > 0 ? 1 : -1;
        var px = Math.abs(hx - sx) < 30 ? sx + side * 30 : hx, ox = sx + side * 34, oy = (sy + G.qbY) / 2;
        d = r.y < 0 ? 'M' + sx + ' ' + sy + 'Q' + ox + ' ' + sy + ' ' + ox + ' ' + oy + 'C' + ox + ' ' + (hy + 10) + ' ' + p[0] + ' ' + (hy + 14) + ' ' + p[0] + ' ' + p[1]
          : 'M' + sx + ' ' + sy + 'Q' + ox + ' ' + sy + ' ' + ox + ' ' + oy + 'C' + ox + ' ' + (hy + 14) + ' ' + px + ' ' + (hy + 16) + ' ' + px + ' ' + hy + 'C' + px + ' ' + (hy - 16) + ' ' + p[0] + ' ' + Math.min(hy - 6, p[1] + 16) + ' ' + p[0] + ' ' + p[1];
        hole.innerHTML = '<rect x="' + (hx - G.lw / 2 + 3) + '" y="' + (hy - 3) + '" width="' + (G.lw - 6) + '" height="6" rx="3" fill="' + (r.su ? 'rgba(91,156,246,.6)' : 'rgba(255,255,255,.3)') + '"/>';
      } else {
        var qx = G.x0, qy = G.qbY, dx = p[0] - qx, dy = p[1] - qy, sg = dx >= 0 ? 1 : -1;
        d = 'M' + qx + ' ' + qy + 'Q' + ((qx + p[0]) / 2 + dy * .16 * sg) + ' ' + ((qy + p[1]) / 2 - dx * .16 * sg) + ' ' + p[0] + ' ' + p[1]; hole.innerHTML = '';
        if (r.qb) { qb.querySelector('text').textContent = jersey(r.qb); qb.querySelector('title').textContent = name(r.qb); }
      }
      var dash = !r.run && r.k === 'i' ? ' stroke-dasharray="6 6"' : '';
      var lab = r.run ? (r.y > 0 ? r.y + ' yds' : r.y < 0 ? 'Loss of ' + (-r.y) : 'No gain') : (name(r.qb) ? 'From ' + short(name(r.qb)) : ''), tw = lab.length * 7 + 16, lx = Math.max(4, Math.min(G.W - tw - 4, p[0] - tw / 2)), ly = p[1] - 34 < G.TOP ? p[1] + 14 : p[1] - 34;
      arc.innerHTML = '<path class="dbd-thr-arc" d="' + d + '" fill="none" stroke="' + col + '" stroke-width="' + (r.run ? 3.5 : 2.5) + '"' + (anim && !still ? '' : dash) + ' stroke-linecap="round" stroke-linejoin="round"/>'
        + '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="' + (G.r0 + 5) + '" fill="none" stroke="#fff" stroke-width="2"/>'
        + (lab ? '<rect x="' + lx + '" y="' + ly + '" width="' + tw + '" height="22" rx="3" fill="rgba(10,14,12,.88)"/><text x="' + (lx + tw / 2) + '" y="' + (ly + 15) + '" fill="#fff" font-family="IBM Plex Sans,sans-serif" font-weight="600" font-size="12" text-anchor="middle">' + esc(lab) + '</text>' : '');
      if (anim && !still) {
        var pa = arc.querySelector('path'), L = pa.getTotalLength ? pa.getTotalLength() : 0;
        if (L) { pa.style.setProperty('--len', L); pa.classList.add('is-drawing'); setTimeout(function () { pa.classList.remove('is-drawing'); if (!r.run && r.k === 'i') pa.setAttribute('stroke-dasharray', '6 6'); }, 750); }
      }
      now(r);
    }

    /* the play you're on */
    function now(r) {
      var b = $('.dbd-thr-now'), s = sets[st.set];
      if (!r) { b.className = 'dbd-thr-now is-empty'; b.textContent = s.season ? 'Hover or tap any ' + one + ' to see it.' : 'Hover or tap a ' + one + ' on the field or the timeline, or press Replay to watch them in order.'; return; }
      var m = r.sd == null ? '' : r.sd === 0 ? 'tied' : r.sd > 0 ? 'up ' + r.sd : 'down ' + (-r.sd);
      var ctx = (r.gl ? esc(r.gl) + ' · ' : '') + 'Q' + r.q + ' ' + esc(r.c) + (m ? ' · ' + esc(r.tn) + ' ' + m : '') + (r.dn ? ' · ' + ord(r.dn) + ' &amp; ' + r.tg : '');
      b.className = 'dbd-thr-now';
      if (r.run) {
        var hd = (r.y > 0 ? r.y + '-yard run' : r.y < 0 ? 'Loss of ' + (-r.y) : 'No gain') + ', ' + GAPS[r.gp][2] + (r.td ? ', touchdown' : r.fd ? ', first down' : '') + (r.fl ? ', fumbled and lost' : '');
        b.innerHTML = '<div><div class="hd"><i style="background:' + TICK[kind(r)] + '"></i>' + esc(hd) + '</div><div class="ctx">' + ctx + ' · ' + (r.su ? 'successful' : 'not successful') + '</div></div><div class="nums"><div><b>' + r.y + '</b><span>Yards</span></div><div><b>' + sgn(r.e) + '</b><span>EPA</span></div></div>';
        return;
      }
      var what = r.k === 'c' ? (r.x > 0 ? 'Caught, ' + plural(r.x, 'yard', 'yards') + ' after the catch' : 'Caught') : r.k === 'x' ? 'Intercepted' : r.dr ? 'Dropped' : 'Incomplete';
      b.innerHTML = '<div><div class="hd"><i style="background:' + TICK[kind(r)] + '"></i>' + esc(what) + (r.td ? ', touchdown' : '') + (r.fl ? ', fumbled and lost' : '') + (name(r.qb) ? ' · from ' + esc(name(r.qb)) : '') + '</div><div class="ctx">' + ctx + (r.l >= 0 ? ' · thrown ' + ['left', 'middle', 'right'][r.l] : '') + (r.ct ? ' · contested' : '') + '</div></div>'
        + '<div class="nums"><div><b>' + r.a + '</b><span>Air yds</span></div><div><b>' + (r.k === 'c' ? r.x : '–') + '</b><span>YAC</span></div><div><b>' + (r.k === 'c' ? r.y : '–') + '</b><span>Yards</span></div><div><b>' + sgn(r.e) + '</b><span>EPA</span></div></div>';
    }

    render();
    var lw0 = sec.clientWidth, t; window.addEventListener('resize', function () { if (sec.clientWidth === lw0) return; lw0 = sec.clientWidth; clearTimeout(t); t = setTimeout(function () { if (st.rp) { draw(st.rp.i); show(st.rp.i - 1, false); } else render(); }, 150); });
    if (!sec.clientWidth && 'ResizeObserver' in window) { var ro = new ResizeObserver(function () { if (sec.clientWidth) { ro.disconnect(); lw0 = sec.clientWidth; render(); } }); ro.observe(sec); }
  }
  function boot() { [].forEach.call(document.querySelectorAll('.dbd-thr[data-kind=rx],.dbd-thr[data-kind=rb]'), mount); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
