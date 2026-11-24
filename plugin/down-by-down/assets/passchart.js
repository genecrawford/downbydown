/* Every throw, one game at a time (SCOPE §90): the pass chart on a finished game's page and a quarterback's page.
   The field stands upright and fills the panel's width: the line of scrimmage near the bottom, the quarterback behind it in his team's color
   with his number, depth up the field, his left on the left. Each throw is a dot by air yards and third of the field. Hover, focus or tap
   one and a line arcs from the quarterback to it, with the receiver named and the play written below. The depth split runs down the left
   side, lined up with the field (under it on a narrow panel). The game page picks a team, and a passer when a team used more than one;
   the quarterback's page picks a game. Picking a receiver lights up his targets. Data: the panel's embedded JSON (class-dbd-throws.php).
   2.49.0 (SCOPE §91):
     - Whole season at the top of the quarterback's game picker: every throw at once, smaller and see-through; hover names the game.
     - Replay: the game's throws go out in order, one a second (two at 2×), the arc drawing from the quarterback; Pause, step, Show all.
       Reduced motion: the arcs appear without drawing. The season view has no replay.
     - Field use: zones touched of 12 (left, middle, right by behind the line, 0 to 9, 10 to 19, 20+), and how evenly he spread the ball,
       against every QB game this season with 20+ throws (or QB season with 150+). Show zones draws them on the field. */
(function () {
  var COL = { c: '#5b9cf6', i: '#c9c6d1', x: '#e0564b', td: '#4fd18b' };
  var BANDS = [[-99, 0, 'Behind the line', 'Behind'], [0, 10, '0 to 9 yds', '0–9'], [10, 20, '10 to 19 yds', '10–19'], [20, 999, '20+ yds', '20+']];
  var STEP = 1100;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function rnd(i) { var x = Math.sin(i * 12.9898 + 78.233) * 43758.5453; return x - Math.floor(x); }
  function ord(n) { return n + (['th', 'st', 'nd', 'rd'][n] || 'th'); }
  function num(v) { return Number(v).toLocaleString('en-US'); }
  function short(n) { var p = String(n || '').split(' '); return p.length > 1 && p[0].indexOf('.') < 0 ? p[0].charAt(0) + '. ' + p.slice(1).join(' ') : String(n || ''); }
  function band(a) { return a < 0 ? 0 : a < 10 ? 1 : a < 20 ? 2 : 3; }
  function margin(sd) { return sd == null ? '' : sd === 0 ? 'tied' : sd > 0 ? 'up ' + sd : 'down ' + (-sd); }
  /* a row: q, clock, down, distance, result, air, yards, lane, td, receiver, passer, epa, score margin */
  function R(a) { return { q: a[0], c: a[1], dn: a[2], tg: a[3], k: a[4], a: a[5], y: a[6], l: a[7], td: a[8], r: a[9], qb: a[10], e: a[11], sd: a.length > 12 ? a[12] : null }; }
  function kind(r) { return r.td ? 'td' : r.k; }
  function dot(c, x, y, r, extra) {
    if (c === 'i') return '<circle cx="' + x + '" cy="' + y + '" r="' + (r - 1) + '" fill="rgba(0,0,0,.001)" stroke="' + COL.i + '" stroke-width="2"' + extra + '/>';
    if (c === 'x') { var d = r * .45; return '<g' + extra + '><circle cx="' + x + '" cy="' + y + '" r="' + r + '" fill="' + COL.x + '"/><path d="M' + (x - d) + ' ' + (y - d) + 'L' + (x + d) + ' ' + (y + d) + 'M' + (x + d) + ' ' + (y - d) + 'L' + (x - d) + ' ' + (y + d) + '" stroke="#fff" stroke-width="1.6"/></g>'; }
    return '<circle cx="' + x + '" cy="' + y + '" r="' + r + '" fill="' + COL[c] + '"' + (c === 'td' ? ' stroke="#fff" stroke-width="1.5"' : '') + extra + '/>';
  }
  function legend() {
    var s = function (c, l) { return '<span><svg width="14" height="14" aria-hidden="true">' + dot(c, 7, 7, 5.5, '') + '</svg>' + l + '</span>'; };
    return '<div class="dbd-thr-key">' + s('c', 'Complete') + s('i', 'Incomplete') + s('x', 'Intercepted') + s('td', 'Touchdown') + '</div>';
  }
  function split(rs) {
    var c = rs.filter(function (r) { return r.k === 'c'; }), i = rs.filter(function (r) { return r.k === 'x'; }).length, y = c.reduce(function (s, r) { return s + r.y; }, 0);
    return { v: c.length + ' of ' + rs.length, v2: c.length + '/' + rs.length, y: num(y) + ' yds' + (i ? ' · ' + i + ' INT' : ''), y2: num(y) };
  }
  function zones(rows) { var z = {}; rows.forEach(function (r) { if (r.l < 0) return; var k = r.l * 4 + band(r.a); z[k] = (z[k] || 0) + 1; }); return z; }
  /* field use: zones touched, and how evenly, against the league (SCOPE §91) */
  function use(rows, season, lg) {
    var z = zones(rows), keys = Object.keys(z), n = keys.reduce(function (s, k) { return s + z[k]; }, 0);
    if (!lg || !lg.g || !lg.g.length) return '';
    var min = (lg.min || [20, 150])[season ? 1 : 0], head = '<b>Field use:</b> ' + keys.length + ' of 12 zones';
    if (n < min) return head + '. Too few throws to compare (' + min + '+).';
    var e = -keys.reduce(function (s, k) { var p = z[k] / n; return s + p * Math.log(p); }, 0) / Math.log(12), L = season ? lg.s : lg.g;
    if (!L || !L.length) return head + '.';
    var er = Math.round(e * 1e4) / 1e4, pct = Math.round(L.filter(function (x) { return x < er - 1e-9; }).length / L.length * 100);  /* rounded like the league's list, so his own entry never counts against him */
    return head + ', spread more evenly than ' + pct + '% of quarterback ' + (season ? 'seasons (' + min + '+ throws)' : 'games this season (' + min + '+ throws)') + '.';
  }
  var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function mount(sec) {
    var D; try { D = JSON.parse(sec.querySelector('.dbd-thr-d').textContent); } catch (e) { return; }
    var N = D.names || {}, sets = D.sets || []; if (!sets.length) return;
    sets.forEach(function (s) { s.R = s.rows.map(R); });
    var first = 0;
    if (D.kind === 'qb' && sets.length > 1) {  /* the whole season, ahead of the games; his newest game stays the default */
      var all = [], by = []; sets.slice().reverse().forEach(function (s) { s.R.forEach(function (r) { var x = Object.create(r); x.gl = s.label; all.push(x); }); if (by.indexOf(s.nick) < 0) by.push(s.nick); });
      sets.unshift({ season: true, label: 'Whole season', nick: by.join(' and the '), c1: sets[0].c1, R: all }); first = 1;
    }
    var body = sec.querySelector('.dbd-thr-b'), pick = sec.querySelector('.dbd-thr-pick'), st = { set: first, qb: '', hl: '', zones: false, rp: null }, G = {};
    var name = function (k) { return (N[k] || [])[0] || ''; }, jersey = function (k) { return (N[k] || [])[1] || ''; };
    body.innerHTML = '<p class="dbd-thr-say"></p><p class="dbd-thr-use"></p><div class="dbd-thr-qbs dbd-thr-chips"></div><div class="dbd-thr-tools"><span class="dbd-thr-rp"></span><label class="dbd-thr-zt"><input type="checkbox"> Show zones</label></div>'
      + '<div class="dbd-thr-vf"><div class="dbd-thr-gut" aria-hidden="true"></div><div class="dbd-thr-fld"></div></div><div class="dbd-thr-tbl"></div>'
      + '<p class="dbd-thr-info" aria-live="polite"></p>' + legend() + '<div class="dbd-thr-recs dbd-thr-chips"></div>'
      + '<p class="dbd-note">Left and right are as the quarterback sees the field. Within a third, a throw’s place across it is spread out so dots don’t sit on each other: the play data says left, middle or right, not exactly where. Depth is air yards, how far past the line of scrimmage the ball was thrown. Field use splits the field into 12 zones (left, middle and right, by depth) and says how evenly the throws were spread across them, against the rest of the league.</p>';
    var $ = function (c) { return body.querySelector(c); };
    if (D.kind === 'game') {
      pick.innerHTML = '<span class="dbd-thr-seg" role="group" aria-label="Team">' + sets.map(function (s, i) { return '<button type="button" data-s="' + i + '" aria-pressed="' + (i === 0) + '">' + esc(s.label) + '</button>'; }).join('') + '</span>';
      pick.onclick = function (e) { var b = e.target.closest('button'); if (!b) return; stop(); st.set = +b.getAttribute('data-s'); st.qb = ''; st.hl = ''; [].forEach.call(pick.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); render(); };
    } else {
      pick.innerHTML = '<select aria-label="Which game">' + sets.map(function (s, i) { return '<option value="' + i + '"' + (i === first ? ' selected' : '') + '>' + esc(s.label + (s.season ? '' : ' · ' + s.nick) + ' · ' + s.R.length + (s.R.length === 1 ? ' throw' : ' throws')) + '</option>'; }).join('') + '</select>';
      pick.querySelector('select').onchange = function () { stop(); st.set = +this.value; st.hl = ''; render(); };
    }
    $('.dbd-thr-qbs').onclick = function (e) { var b = e.target.closest('button'); if (!b) return; stop(); st.qb = b.getAttribute('data-q'); st.hl = ''; render(); };
    $('.dbd-thr-recs').onclick = function (e) { var b = e.target.closest('button'); if (!b) return; st.hl = b.getAttribute('data-r'); if (st.rp) { draw(st.rp.i); show(st.rp.i - 1, false); } else render(); };
    $('.dbd-thr-zt input').onchange = function () { st.zones = this.checked; if (st.rp) { draw(st.rp.i); show(st.rp.i - 1, false); } else draw(); };

    function current() { var s = sets[st.set]; return st.qb ? s.R.filter(function (r) { return r.qb === st.qb; }) : s.R; }
    function render() {
      var s = sets[st.set], all = s.R, cnt = {}; all.forEach(function (r) { cnt[r.qb] = (cnt[r.qb] || 0) + 1; });
      var ps = Object.keys(cnt).sort(function (a, b) { return cnt[b] - cnt[a]; }), rows = current();
      var qe = $('.dbd-thr-qbs'); qe.hidden = D.kind !== 'game' || ps.length < 2;
      if (!qe.hidden) qe.innerHTML = '<span class="cl">Passer</span><button type="button" data-q="" aria-pressed="' + !st.qb + '">' + (ps.length === 2 ? 'Both' : 'All') + '</button>' + ps.map(function (q) { return '<button type="button" data-q="' + esc(q) + '" aria-pressed="' + (st.qb === q) + '">' + esc(name(q)) + ' ' + cnt[q] + '</button>'; }).join('');
      var c = rows.filter(function (r) { return r.k === 'c'; }), deep = rows.filter(function (r) { return r.a >= 20; });
      var who = D.kind === 'game' ? (st.qb ? name(st.qb) : ps.map(name).join(' and ')) : s.season ? 'This season, for the ' + s.nick : s.label + ', for the ' + s.nick;
      $('.dbd-thr-say').textContent = who + ': ' + c.length + ' of ' + rows.length + ', ' + num(c.reduce(function (t, r) { return t + r.y; }, 0)) + ' yards, ' + deep.length + (deep.length === 1 ? ' throw' : ' throws') + ' of 20+ air yards.';
      var u = use(rows, !!s.season, D.lg); $('.dbd-thr-use').innerHTML = u; $('.dbd-thr-use').hidden = !u;
      var rp = $('.dbd-thr-rp');
      if (s.season) rp.innerHTML = '<span class="dbd-thr-rpn">Replay works one game at a time.</span>';
      else { rp.innerHTML = '<button type="button" class="dbd-thr-btn is-go">Replay</button>'; rp.querySelector('button').onclick = start; }
      draw();
      var n = {}; rows.forEach(function (r) { if (r.r) n[r.r] = (n[r.r] || 0) + 1; });
      var top = Object.keys(n).sort(function (a, b) { return n[b] - n[a] || (name(a) < name(b) ? -1 : 1); }).slice(0, 8);
      $('.dbd-thr-recs').innerHTML = '<span class="cl">Targets</span><button type="button" data-r="" aria-pressed="' + !st.hl + '">Everyone</button>' + top.map(function (r) { return '<button type="button" data-r="' + esc(r) + '" aria-pressed="' + (st.hl === r) + '">' + esc(name(r)) + ' ' + n[r] + '</button>'; }).join('');
    }

    function about(r) {
      var what = r.k === 'c' ? 'Complete' : r.k === 'x' ? 'Intercepted' : 'Incomplete', rn = name(r.r);
      var to = rn ? (r.k === 'x' ? ', intended for ' : r.k === 'c' ? ' to ' : ' for ') + esc(rn) : '';
      var lane = ['to the left', 'over the middle', 'to the right'][r.l] || 'with no direction', m = margin(r.sd);
      return (r.gl ? '<b>' + esc(r.gl) + '</b> · ' : '') + '<b>Q' + r.q + ' ' + esc(r.c) + '</b>' + (m ? ', ' + m : '') + ' · ' + (r.dn ? ord(r.dn) + ' &amp; ' + r.tg + ' · ' : '') + esc(name(r.qb)) + ': ' + what + to + ', ' + r.a + ' air yards ' + lane + (r.k === 'c' ? ', ' + r.y + ' yards' : '') + (r.td ? ', touchdown' : '') + ' · EPA ' + (r.e > 0 ? '+' : r.e < 0 ? '−' : '') + Math.abs(r.e).toFixed(2);
    }

    /* the field; upto draws only the first throws (the replay) */
    function draw(upto) {
      var s = sets[st.set], all = current(), rows = upto == null ? all : all.slice(0, upto), narrow = sec.clientWidth < 600; sec.classList.toggle('is-narrow', narrow);
      var fld = $('.dbd-thr-fld'), W = fld.clientWidth || 600, minA = -10, maxA = Math.max(40, Math.ceil((Math.max.apply(null, all.map(function (r) { return r.a; }).concat([0])) + 3) / 5) * 5);
      var H = Math.round(narrow ? W * 1.25 : Math.min(640, Math.max(460, W * .74))), TOP = 30, BOT = 6, lw = W / 3, hl = st.hl;
      var y = function (a) { return TOP + (maxA - Math.max(minA, Math.min(maxA, a))) / (maxA - minA) * (H - TOP - BOT); };
      var g = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img" aria-label="' + rows.length + ' throws, placed by depth and side of the field"><rect width="' + W + '" height="' + H + '" fill="#2c4a35" rx="4"/>';
      if (st.zones) {  /* the 12 zones, each shaded by its share of the throws */
        var z = zones(rows), mx = Math.max.apply(null, [1].concat(Object.keys(z).map(function (k) { return z[k]; })));
        for (var li = 0; li < 3; li++) { for (var bi = 0; bi < 4; bi++) {
          var zn = z[li * 4 + bi] || 0, zt = y(Math.min(maxA, BANDS[bi][1])), zb = y(Math.max(minA, BANDS[bi][0]));
          g += '<rect x="' + (lw * li + 2) + '" y="' + (zt + 2) + '" width="' + (lw - 4) + '" height="' + Math.max(0, zb - zt - 4) + '" fill="rgba(255,255,255,' + (zn ? (.05 + .22 * zn / mx).toFixed(3) : 0) + ')" stroke="rgba(255,255,255,.35)"' + (zn ? '' : ' stroke-dasharray="4 4"') + '/>'
            + '<text x="' + (lw * li + lw - 10) + '" y="' + (zb - 10) + '" fill="rgba(255,255,255,' + (zn ? .9 : .45) + ')" font-family="IBM Plex Mono,monospace" font-size="12" text-anchor="end">' + zn + '</text>';
        } }
      }
      for (var a = minA; a <= maxA; a += 5) {
        g += '<line x1="0" x2="' + W + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="rgba(255,255,255,' + (a === 0 ? .85 : a % 10 ? .08 : .2) + ')" stroke-width="' + (a === 0 ? 2 : 1) + '"/>';
        if (a % 10 === 0 && a >= 0 && a < maxA) g += '<text x="8" y="' + (y(a) - 5) + '" fill="rgba(255,255,255,.7)" font-family="IBM Plex Mono,monospace" font-size="11">' + (a === 0 ? 'LOS' : a) + '</text>';
      }
      ['Left', 'Middle', 'Right'].forEach(function (n, i) {
        if (i) g += '<line x1="' + lw * i + '" x2="' + lw * i + '" y1="' + TOP + '" y2="' + y(0) + '" stroke="rgba(255,255,255,.2)" stroke-dasharray="3 6"/>';
        var lr = rows.filter(function (r) { return r.l === i; }), c = lr.filter(function (r) { return r.k === 'c'; }).length;
        g += '<text x="' + (lw * i + lw / 2) + '" y="20" fill="#fff" font-family="Barlow Condensed,sans-serif" font-weight="700" font-size="13" letter-spacing="1.5" text-anchor="middle">' + n.toUpperCase() + ' <tspan font-family="IBM Plex Mono,monospace" font-weight="500" letter-spacing="0" fill="rgba(255,255,255,.75)">' + c + '/' + lr.length + '</tspan></text>';
      });
      var qx = W / 2, qy = y(-6), small = !!s.season, r0 = small ? (narrow ? 4 : 4.5) : (narrow ? 6 : 7), pos = [];
      g += '<g class="arc"></g>';
      rows.forEach(function (r, i) {
        var li = r.l < 0 ? 1 : r.l, lo = li === 0 ? 34 : lw * .1, cx = lw * li + lo + (lw * .9 - lo) * rnd(i * 7 + r.a + r.q), cy = y(r.a) + (rnd(i + 3) - .5) * 4;
        if (Math.abs(cx - qx) < 22 && cy > qy - 22) cx += cx < qx ? -24 : 24;  /* clear of the quarterback */
        pos[i] = [Math.round(cx * 10) / 10, Math.round(cy * 10) / 10];
        g += dot(kind(r), pos[i][0], pos[i][1], r0, (hl && r.r !== hl ? ' opacity=".14"' : small ? ' opacity=".7"' : '') + ' data-i="' + i + '" tabindex="0" style="cursor:pointer"');
      });
      var q0 = st.qb || (rows[0] || all[0] || {}).qb;
      g += '<g class="qb" transform="translate(' + qx + ',' + qy + ')"><circle r="17" fill="' + esc(s.c1) + '" stroke="#fff" stroke-width="2"/><text y="5.5" fill="#fff" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="15" text-anchor="middle">' + esc(jersey(q0)) + '</text><title>' + esc(name(q0)) + '</title></g>';
      fld.innerHTML = g + '</svg>';
      /* the depth split, lined up with the field: beside it, or under it on a narrow panel */
      var gh = '', tb = '';
      BANDS.forEach(function (b) {
        var rs = rows.filter(function (r) { return r.a >= b[0] && r.a < b[1]; }), sp = rs.length ? split(rs) : { v: '–', v2: '–', y: '', y2: '' }, top = y(Math.min(maxA, b[1])), bot = y(Math.max(minA, b[0]));
        gh = '<div class="band" style="top:' + top + 'px;height:' + (bot - top) + 'px"><span class="n">' + b[2] + '</span><span class="v">' + sp.v + '</span><span class="y">' + sp.y + '</span></div>' + gh;
        tb = '<div><span class="n">' + b[3] + '</span><span class="v">' + sp.v2 + '</span><span class="y">' + sp.y2 + '</span></div>' + tb;
      });
      var gut = $('.dbd-thr-gut'); gut.style.height = H + 'px'; gut.innerHTML = gh; $('.dbd-thr-tbl').innerHTML = tb;
      G = { svg: fld.querySelector('svg'), qx: qx, qy: qy, pos: pos, rows: rows, r0: r0, W: W, TOP: TOP };
      var pickDot = function (e) { if (st.rp) return; var t = e.target && e.target.closest ? e.target.closest('[data-i]') : null; if (t) show(+t.getAttribute('data-i'), false); };
      G.svg.addEventListener('mouseover', pickDot); G.svg.addEventListener('focusin', pickDot); G.svg.addEventListener('click', pickDot);
      if (upto == null) $('.dbd-thr-info').textContent = 'Hover or tap a throw.';
    }

    /* one throw: the line from the quarterback to the ball, the receiver named at the end; anim draws the line (the replay) */
    function show(i, anim) {
      var r = G.rows[i], p = G.pos[i]; if (!r || !p) return; var qx = G.qx, qy = G.qy;
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
      $('.dbd-thr-info').innerHTML = about(r);
    }

    /* the replay (SCOPE §91): one throw a second, two at 2×, in the order they happened */
    function start() { st.rp = { i: 0, sp: 1, on: true, t: null }; step(1); }
    function stop() { if (!st.rp) return; clearTimeout(st.rp.t); st.rp = null; }
    function controls() {
      var R = st.rp, n = current().length, rp = $('.dbd-thr-rp');
      rp.innerHTML = '<button type="button" class="dbd-thr-btn is-go" data-a="pp">' + (R.on ? 'Pause' : 'Play') + '</button><button type="button" class="dbd-thr-btn" data-a="bk" aria-label="Back one throw"' + (R.i <= 1 ? ' disabled' : '') + '>‹</button><button type="button" class="dbd-thr-btn" data-a="fw" aria-label="Forward one throw"' + (R.i >= n ? ' disabled' : '') + '>›</button>'
        + '<span class="dbd-thr-at">' + R.i + ' of ' + n + '</span><span class="dbd-thr-seg" role="group" aria-label="Speed"><button type="button" data-sp="1" aria-pressed="' + (R.sp === 1) + '">1×</button><button type="button" data-sp="2" aria-pressed="' + (R.sp === 2) + '">2×</button></span><button type="button" class="dbd-thr-btn" data-a="all">Show all</button>';
      rp.onclick = function (e) {
        var b = e.target.closest('button'); if (!b || b.disabled) return; var a = b.getAttribute('data-a'), sp = b.getAttribute('data-sp');
        if (sp) { R.sp = +sp; controls(); return; }
        if (a === 'all') { stop(); render(); return; }
        clearTimeout(R.t);
        if (a === 'pp') { R.on = !R.on; if (R.on) { if (R.i >= n) R.i = 0; step(1); } else controls(); return; }
        R.on = false; step(a === 'bk' ? -1 : 1);
      };
    }
    function step(d) {
      var R = st.rp, n = current().length; if (!R || !n) return; R.i = Math.max(1, Math.min(n, R.i + d)); draw(R.i); show(R.i - 1, true);
      if (R.i >= n) R.on = false; controls();
      if (R.on) R.t = setTimeout(function () { step(1); }, STEP / R.sp);
    }

    render();
    var lw0 = sec.clientWidth, t; window.addEventListener('resize', function () { if (sec.clientWidth === lw0) return; lw0 = sec.clientWidth; clearTimeout(t); t = setTimeout(function () { if (st.rp) { draw(st.rp.i); show(st.rp.i - 1, false); } else render(); }, 150); });
    /* a panel inside a closed tab has no width yet: draw it when it first shows */
    if (!sec.clientWidth && 'ResizeObserver' in window) { var ro = new ResizeObserver(function () { if (sec.clientWidth) { ro.disconnect(); lw0 = sec.clientWidth; if (st.rp) { draw(st.rp.i); show(st.rp.i - 1, false); } else render(); } }); ro.observe(sec); }
  }
  function boot() { [].forEach.call(document.querySelectorAll('.dbd-thr'), mount); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
