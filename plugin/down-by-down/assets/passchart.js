/* Every throw, one game at a time (SCOPE §90): the pass chart on a finished game's page and a quarterback's page.
   The field stands upright and fills the panel's width: the line of scrimmage near the bottom, the quarterback behind it in his team's color
   with his number, depth up the field, his left on the left. Each throw is a dot by air yards and third of the field. Hover, focus or tap
   one and a line arcs from the quarterback to it, with the receiver named and the play written below. The depth split runs down the left
   side, lined up with the field (under it on a narrow panel). The game page picks a team, and a passer when a team used more than one;
   the quarterback's page picks a game. Picking a receiver lights up his targets. Data: the panel's embedded JSON (class-dbd-throws.php). */
(function () {
  var COL = { c: '#5b9cf6', i: '#c9c6d1', x: '#e0564b', td: '#4fd18b' };
  var BANDS = [[-99, 0, 'Behind the line', 'Behind'], [0, 10, '0 to 9 yds', '0–9'], [10, 20, '10 to 19 yds', '10–19'], [20, 999, '20+ yds', '20+']];
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function rnd(i) { var x = Math.sin(i * 12.9898 + 78.233) * 43758.5453; return x - Math.floor(x); }
  function ord(n) { return n + (['th', 'st', 'nd', 'rd'][n] || 'th'); }
  function num(v) { return Number(v).toLocaleString('en-US'); }
  function short(n) { var p = String(n || '').split(' '); return p.length > 1 && p[0].indexOf('.') < 0 ? p[0].charAt(0) + '. ' + p.slice(1).join(' ') : String(n || ''); }
  /* a row: q, clock, down, distance, result, air, yards, lane, td, receiver, passer, epa */
  function R(a) { return { q: a[0], c: a[1], dn: a[2], tg: a[3], k: a[4], a: a[5], y: a[6], l: a[7], td: a[8], r: a[9], qb: a[10], e: a[11] }; }
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

  function mount(sec) {
    var D; try { D = JSON.parse(sec.querySelector('.dbd-thr-d').textContent); } catch (e) { return; }
    var N = D.names || {}, sets = D.sets || []; if (!sets.length) return;
    sets.forEach(function (s) { s.R = s.rows.map(R); });
    var body = sec.querySelector('.dbd-thr-b'), pick = sec.querySelector('.dbd-thr-pick'), st = { set: 0, qb: '', hl: '' };
    var name = function (k) { return (N[k] || [])[0] || ''; }, jersey = function (k) { return (N[k] || [])[1] || ''; };
    body.innerHTML = '<p class="dbd-thr-say"></p><div class="dbd-thr-qbs dbd-thr-chips"></div><div class="dbd-thr-vf"><div class="dbd-thr-gut" aria-hidden="true"></div><div class="dbd-thr-fld"></div></div><div class="dbd-thr-tbl"></div>'
      + '<p class="dbd-thr-info" aria-live="polite"></p>' + legend() + '<div class="dbd-thr-recs dbd-thr-chips"></div>'
      + '<p class="dbd-note">Left and right are as the quarterback sees the field. Within a third, a throw’s place across it is spread out so dots don’t sit on each other: the play data says left, middle or right, not exactly where. Depth is air yards, how far past the line of scrimmage the ball was thrown.</p>';
    var $ = function (c) { return body.querySelector(c); };
    if (D.kind === 'game') {
      pick.innerHTML = '<span class="dbd-thr-seg" role="group" aria-label="Team">' + sets.map(function (s, i) { return '<button type="button" data-s="' + i + '" aria-pressed="' + (i === 0) + '">' + esc(s.label) + '</button>'; }).join('') + '</span>';
      pick.onclick = function (e) { var b = e.target.closest('button'); if (!b) return; st.set = +b.getAttribute('data-s'); st.qb = ''; st.hl = ''; [].forEach.call(pick.querySelectorAll('button'), function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); render(); };
    } else {
      pick.innerHTML = '<select aria-label="Which game">' + sets.map(function (s, i) { return '<option value="' + i + '">' + esc(s.label + ' · ' + s.nick + ' · ' + s.R.length + (s.R.length === 1 ? ' throw' : ' throws')) + '</option>'; }).join('') + '</select>';
      pick.querySelector('select').onchange = function () { st.set = +this.value; st.hl = ''; render(); };
    }
    $('.dbd-thr-qbs').onclick = function (e) { var b = e.target.closest('button'); if (!b) return; st.qb = b.getAttribute('data-q'); st.hl = ''; render(); };
    $('.dbd-thr-recs').onclick = function (e) { var b = e.target.closest('button'); if (!b) return; st.hl = b.getAttribute('data-r'); render(); };

    function render() {
      var s = sets[st.set], all = s.R, cnt = {}; all.forEach(function (r) { cnt[r.qb] = (cnt[r.qb] || 0) + 1; });
      var ps = Object.keys(cnt).sort(function (a, b) { return cnt[b] - cnt[a]; }), rows = st.qb ? all.filter(function (r) { return r.qb === st.qb; }) : all;
      var qe = $('.dbd-thr-qbs'); qe.hidden = D.kind !== 'game' || ps.length < 2;
      if (!qe.hidden) qe.innerHTML = '<span class="cl">Passer</span><button type="button" data-q="" aria-pressed="' + !st.qb + '">' + (ps.length === 2 ? 'Both' : 'All') + '</button>' + ps.map(function (q) { return '<button type="button" data-q="' + esc(q) + '" aria-pressed="' + (st.qb === q) + '">' + esc(name(q)) + ' ' + cnt[q] + '</button>'; }).join('');
      var c = rows.filter(function (r) { return r.k === 'c'; }), deep = rows.filter(function (r) { return r.a >= 20; });
      var who = D.kind === 'game' ? (st.qb ? name(st.qb) : ps.map(name).join(' and ')) : s.label + ', for the ' + s.nick;
      $('.dbd-thr-say').textContent = who + ': ' + c.length + ' of ' + rows.length + ', ' + num(c.reduce(function (t, r) { return t + r.y; }, 0)) + ' yards, ' + deep.length + (deep.length === 1 ? ' throw' : ' throws') + ' of 20+ air yards.';
      draw(rows, s, st.qb ? [st.qb] : ps);
      var n = {}; rows.forEach(function (r) { if (r.r) n[r.r] = (n[r.r] || 0) + 1; });
      var top = Object.keys(n).sort(function (a, b) { return n[b] - n[a] || (name(a) < name(b) ? -1 : 1); }).slice(0, 8);
      $('.dbd-thr-recs').innerHTML = '<span class="cl">Targets</span><button type="button" data-r="" aria-pressed="' + !st.hl + '">Everyone</button>' + top.map(function (r) { return '<button type="button" data-r="' + esc(r) + '" aria-pressed="' + (st.hl === r) + '">' + esc(name(r)) + ' ' + n[r] + '</button>'; }).join('');
    }

    function about(r) {
      var what = r.k === 'c' ? 'Complete' : r.k === 'x' ? 'Intercepted' : 'Incomplete', rn = name(r.r);
      var to = rn ? (r.k === 'x' ? ', intended for ' : r.k === 'c' ? ' to ' : ' for ') + esc(rn) : '';
      var lane = ['to the left', 'over the middle', 'to the right'][r.l] || 'with no direction';
      return '<b>Q' + r.q + ' ' + esc(r.c) + '</b> · ' + (r.dn ? ord(r.dn) + ' &amp; ' + r.tg + ' · ' : '') + esc(name(r.qb)) + ': ' + what + to + ', ' + r.a + ' air yards ' + lane + (r.k === 'c' ? ', ' + r.y + ' yards' : '') + (r.td ? ', touchdown' : '') + ' · EPA ' + (r.e > 0 ? '+' : r.e < 0 ? '−' : '') + Math.abs(r.e).toFixed(2);
    }

    function draw(rows, s, qbs) {
      var narrow = sec.clientWidth < 600; sec.classList.toggle('is-narrow', narrow);
      var fld = $('.dbd-thr-fld'), W = fld.clientWidth || 600, minA = -10, maxA = Math.max(40, Math.ceil((Math.max.apply(null, rows.map(function (r) { return r.a; }).concat([0])) + 3) / 5) * 5);
      var H = Math.round(narrow ? W * 1.25 : Math.min(640, Math.max(460, W * .74))), TOP = 30, BOT = 6, lw = W / 3, hl = st.hl;
      var y = function (a) { return TOP + (maxA - Math.max(minA, Math.min(maxA, a))) / (maxA - minA) * (H - TOP - BOT); };
      var g = '<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" role="img" aria-label="' + rows.length + ' throws, placed by depth and side of the field"><rect width="' + W + '" height="' + H + '" fill="#2c4a35" rx="4"/>';
      for (var a = minA; a <= maxA; a += 5) {
        g += '<line x1="0" x2="' + W + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="rgba(255,255,255,' + (a === 0 ? .85 : a % 10 ? .08 : .2) + ')" stroke-width="' + (a === 0 ? 2 : 1) + '"/>';
        if (a % 10 === 0 && a >= 0 && a < maxA) g += '<text x="8" y="' + (y(a) - 5) + '" fill="rgba(255,255,255,.7)" font-family="IBM Plex Mono,monospace" font-size="11">' + (a === 0 ? 'LOS' : a) + '</text>';
      }
      ['Left', 'Middle', 'Right'].forEach(function (n, i) {
        if (i) g += '<line x1="' + lw * i + '" x2="' + lw * i + '" y1="' + TOP + '" y2="' + y(0) + '" stroke="rgba(255,255,255,.2)" stroke-dasharray="3 6"/>';
        var lr = rows.filter(function (r) { return r.l === i; }), c = lr.filter(function (r) { return r.k === 'c'; }).length;
        g += '<text x="' + (lw * i + lw / 2) + '" y="20" fill="#fff" font-family="Barlow Condensed,sans-serif" font-weight="700" font-size="13" letter-spacing="1.5" text-anchor="middle">' + n.toUpperCase() + ' <tspan font-family="IBM Plex Mono,monospace" font-weight="500" letter-spacing="0" fill="rgba(255,255,255,.75)">' + c + '/' + lr.length + '</tspan></text>';
      });
      var qx = W / 2, qy = y(-6), r0 = narrow ? 6 : 7, pos = [];
      g += '<g class="arc"></g>';
      rows.forEach(function (r, i) {
        var li = r.l < 0 ? 1 : r.l, lo = li === 0 ? 34 : lw * .1, cx = lw * li + lo + (lw * .9 - lo) * rnd(i * 7 + r.a + r.q), cy = y(r.a) + (rnd(i + 3) - .5) * 4;
        if (Math.abs(cx - qx) < 22 && cy > qy - 22) cx += cx < qx ? -24 : 24;  /* clear of the quarterback */
        pos[i] = [Math.round(cx * 10) / 10, Math.round(cy * 10) / 10];
        g += dot(kind(r), pos[i][0], pos[i][1], r0, (hl && r.r !== hl ? ' opacity=".14"' : '') + ' data-i="' + i + '" tabindex="0" focusable="true" style="cursor:pointer"');
      });
      g += '<g class="qb" transform="translate(' + qx + ',' + qy + ')"><circle r="17" fill="' + esc(s.c1) + '" stroke="#fff" stroke-width="2"/><text y="5.5" fill="#fff" font-family="IBM Plex Mono,monospace" font-weight="600" font-size="15" text-anchor="middle">' + esc(jersey(qbs[0])) + '</text><title>' + esc(name(qbs[0])) + '</title></g>';
      fld.innerHTML = g + '</svg>';
      var svg = fld.querySelector('svg'), arc = svg.querySelector('.arc'), qb = svg.querySelector('.qb');
      /* the depth split, lined up with the field: beside it, or under it on a narrow panel */
      var gh = '', tb = '';
      BANDS.forEach(function (b) {
        var rs = rows.filter(function (r) { return r.a >= b[0] && r.a < b[1]; }), sp = rs.length ? split(rs) : { v: '–', v2: '–', y: '', y2: '' }, top = y(Math.min(maxA, b[1])), bot = y(Math.max(minA, b[0]));
        gh = '<div class="band" style="top:' + top + 'px;height:' + (bot - top) + 'px"><span class="n">' + b[2] + '</span><span class="v">' + sp.v + '</span><span class="y">' + sp.y + '</span></div>' + gh;
        tb = '<div><span class="n">' + b[3] + '</span><span class="v">' + sp.v2 + '</span><span class="y">' + sp.y2 + '</span></div>' + tb;
      });
      var gut = $('.dbd-thr-gut'); gut.style.height = H + 'px'; gut.innerHTML = gh; $('.dbd-thr-tbl').innerHTML = tb;
      var info = $('.dbd-thr-info'); info.textContent = 'Hover or tap a throw.';
      function show(t) {
        var el = t && t.closest ? t.closest('[data-i]') : null; if (!el) return; var i = +el.getAttribute('data-i'), r = rows[i], p = pos[i];
        var dx = p[0] - qx, dy = p[1] - qy, d = Math.sqrt(dx * dx + dy * dy) || 1, sg = dx >= 0 ? 1 : -1, mx = (qx + p[0]) / 2 + dy * .16 * sg, my = (qy + p[1]) / 2 - dx * .16 * sg;  /* the ball's arc bows up the field */
        var col = r.k === 'x' ? COL.x : r.td ? COL.td : '#fff', dash = r.k === 'i' ? ' stroke-dasharray="6 6"' : '';
        var lab = name(r.r) ? short(name(r.r)) + (jersey(r.r) ? ' · ' + jersey(r.r) : '') : 'No receiver', tw = lab.length * 7 + 16, lx = Math.max(4, Math.min(W - tw - 4, p[0] - tw / 2)), ly = p[1] - 34 < TOP ? p[1] + 14 : p[1] - 34;
        arc.innerHTML = '<path d="M' + qx + ' ' + qy + 'Q' + mx + ' ' + my + ' ' + p[0] + ' ' + p[1] + '" fill="none" stroke="' + col + '" stroke-width="2.5"' + dash + ' stroke-linecap="round"/>'
          + '<circle cx="' + p[0] + '" cy="' + p[1] + '" r="' + (r0 + 5) + '" fill="none" stroke="#fff" stroke-width="2"/>'
          + '<rect x="' + lx + '" y="' + ly + '" width="' + tw + '" height="22" rx="3" fill="rgba(10,14,12,.88)"/><text x="' + (lx + tw / 2) + '" y="' + (ly + 15) + '" fill="#fff" font-family="IBM Plex Sans,sans-serif" font-weight="600" font-size="12" text-anchor="middle">' + esc(lab) + '</text>';
        qb.querySelector('text').textContent = jersey(r.qb); qb.querySelector('title').textContent = name(r.qb);
        info.innerHTML = about(r);
      }
      svg.addEventListener('mouseover', function (e) { show(e.target); }); svg.addEventListener('focusin', function (e) { show(e.target); }); svg.addEventListener('click', function (e) { show(e.target); });
    }

    render();
    var lw0 = sec.clientWidth, t; window.addEventListener('resize', function () { if (sec.clientWidth === lw0) return; lw0 = sec.clientWidth; clearTimeout(t); t = setTimeout(render, 150); });
    /* a panel inside a closed tab has no width yet: draw it when it first shows */
    if (!sec.clientWidth && 'ResizeObserver' in window) { var ro = new ResizeObserver(function () { if (sec.clientWidth) { ro.disconnect(); lw0 = sec.clientWidth; render(); } }); ro.observe(sec); }
  }
  function boot() { [].forEach.call(document.querySelectorAll('.dbd-thr'), mount); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
