/* Down by Down: the player page's play-file half. PHP renders the hero, tiles, game log, intel basics and
   teammates from the player file; this script fetches the team's play file and draws the field map, the depth
   bands, the situation tables, the best plays and the play-level intel lines. Config in DBD_PLAYER. */
(function () {
  var C = window.DBD_PLAYER, root = document.querySelector('.dbd-player');
  if (!C || !root) return;
  window.DBD_TEAMS = C.teams;
  var $ = function (s) { return root.querySelector(s); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var f1 = function (v) { return isFinite(v) ? (+v).toFixed(1) : '-'; }, sgn = function (v, d) { d = d == null ? 2 : d; return (v > 0 ? '+' : '') + (+v).toFixed(d); }, pc = function (n, d) { return d ? Math.round(n / d * 100) + '%' : '-'; };
  var COL = { run: '#f0b429', pass: '#5b9cf6', inc: '#9a96a6', bad: '#e0564b', td: '#4fd18b' }, FL = 'rgba(255,255,255,.22)', FI = 'rgba(255,255,255,.75)';
  var grp = C.pos === 'QB' ? 'qb' : (C.pos === 'RB' || C.pos === 'FB') ? 'rb' : 'rec';

  /* Compare button: build on whoever is already in the tray (the last compare page's players) */
  var cb = root.querySelector('#dbd-cmp-btn');
  if (cb) {
    var grp = C.pos === 'QB' ? 'qb' : (C.pos === 'RB' || C.pos === 'FB') ? 'rb' : 'rec', tray = [];
    try { var t = JSON.parse(localStorage.getItem('dbd-cmp') || 'null'); if (t && t.ids && t.grp === grp) tray = t.ids; } catch (e) {}  /* another group's tray is ignored: this page starts fresh */
    tray = tray.filter(function (x) { return x && x !== cb.dataset.id; }).slice(-2);
    if (tray.length) { cb.href = cb.dataset.base + (cb.dataset.base.indexOf('?') >= 0 ? '&' : '?') + 'compare=' + encodeURIComponent(tray.concat([cb.dataset.id]).join(',')); cb.textContent = 'Compare with ' + tray.length + ' picked'; }
    cb.addEventListener('click', function () { try { localStorage.setItem('dbd-cmp', JSON.stringify({ grp: grp, ids: tray.concat([cb.dataset.id]) })); } catch (e) {} });
  }

  /* game log views: PHP rendered all three tables; this shows one and slides the pill */
  var gv = root.querySelector('.dbd-gview');
  if (gv) {
    var gpill = function (b, anim) { var pl = gv.querySelector('.t-tabs-pill'); if (!pl || !b) return; var go = function () { pl.style.transform = 'translateX(' + b.offsetLeft + 'px)'; pl.style.width = b.offsetWidth + 'px'; }; if (anim) { go(); return; } var prev = pl.style.transition; pl.style.transition = 'none'; go(); void pl.offsetWidth; pl.style.transition = prev; };
    gv.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; gv.querySelectorAll('.t-tab').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); root.querySelectorAll('[data-view]').forEach(function (t) { t.hidden = t.dataset.view !== b.dataset.v; }); gpill(b, true); });
    requestAnimationFrame(function () { gpill(gv.querySelector('[aria-selected="true"]'), false); });
    window.addEventListener('resize', function () { gpill(gv.querySelector('[aria-selected="true"]'), false); });
  }

  /* depth by direction (SCOPE §29): How often or EPA per throw, the same sliding pill as the game log */
  var lz = root.querySelector('.dbd-lz'), lzv = lz && lz.querySelector('.dbd-lzv');
  if (lzv) {
    var lpill = function (b, anim) { var pl = lzv.querySelector('.t-tabs-pill'); if (!pl || !b) return; var go = function () { pl.style.transform = 'translateX(' + b.offsetLeft + 'px)'; pl.style.width = b.offsetWidth + 'px'; }; if (anim) { go(); return; } var prev = pl.style.transition; pl.style.transition = 'none'; go(); void pl.offsetWidth; pl.style.transition = prev; };
    lzv.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; lzv.querySelectorAll('.t-tab').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); lz.setAttribute('data-mode', b.dataset.m); lpill(b, true); });
    requestAnimationFrame(function () { lpill(lzv.querySelector('[aria-selected="true"]'), false); });
    window.addEventListener('resize', function () { lpill(lzv.querySelector('[aria-selected="true"]'), false); });
  }

  /* the game log's rest of the schedule: one button per view, all three open and close together */
  var more = root.querySelectorAll('.dbd-gx-more');
  more.forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = btn.getAttribute('aria-expanded') !== 'true';
      root.querySelectorAll('.dbd-glog tr.dbd-gx.later').forEach(function (r) { r.hidden = !open; });
      more.forEach(function (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); b.textContent = open ? 'Hide the rest of the schedule' : 'Rest of the schedule · ' + b.dataset.n + ' game' + (b.dataset.n === '1' ? '' : 's'); });
    });
  });

  /* tabbed bands: handled site-wide in dbd-site.js (1.9.0) */

  /* the connection: core columns first, the rest behind a button */
  var cxp = root.querySelector('#dbd-cx');
  if (cxp) { var cxb = document.createElement('button'); cxb.type = 'button'; cxb.className = 'dbd-cx-more'; cxb.textContent = 'All columns'; cxb.addEventListener('click', function () { cxp.classList.toggle('is-all'); cxb.textContent = cxp.classList.contains('is-all') ? 'Core columns' : 'All columns'; }); var cxh = cxp.querySelector('.dbd-ph'); if (cxh) cxh.appendChild(cxb); }

  fetch(C.dataUrl, { credentials: 'omit' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }).then(boot)
    .catch(function (err) { $('#dbd-pmap').innerHTML = '<p class="dbd-note">Could not load the plays (' + esc(err.message || err) + ').</p>'; $('#dbd-mapn').textContent = ''; });

  function boot(RAW) {
    var K = RAW.keys, P = RAW.plays.map(function (r, i) { var o = { i: i }; K.forEach(function (k, j) { o[k] = r[j]; }); return o; }), G = RAW.games || [], gIdx = {};
    G.forEach(function (g) { gIdx[g.id] = g; });
    var byId = K.indexOf('pid') >= 0, byQb = K.indexOf('qb') >= 0, charted = function (p) { return p.pa != null; }, hasFtn = P.some(charted);
    var mine = P.filter(function (p) { return byId && p.pid ? p.pid === C.id : p.pl === C.short; });
    var isT = function (p) { return p.k === 'comp' || p.k === 'inc' || p.k === 'int'; };
    var tgts = mine.filter(isT), runs = mine.filter(function (p) { return p.k === 'run'; });
    var teamPass = P.filter(function (p) { return p.pt === 'pass' && p.k !== 'sack' && p.k !== 'scramble'; });
    var qbPass = byQb ? teamPass.filter(function (p) { return p.qb === C.id; }) : teamPass;  /* his throws, not the whole team's */
    var mapSet = grp === 'qb' ? qbPass : tgts;

    /* ---- field map */
    function hash(i) { return (i * 2654435761 % 4294967296) / 4294967296; }
    function passmap(D) {
      var Wd = 360, H = 420, Tp = 40, B = 30, Lf = 34, R = 10, airs = D.map(function (p) { return p.air; });
      var minA = Math.min(-10, Math.floor(Math.min.apply(null, airs.concat([0])) / 5) * 5), maxA = Math.max.apply(null, [40].concat(airs));
      var y = function (a) { return Tp + (maxA - a) / (maxA - minA) * (H - Tp - B); }, lw = (Wd - Lf - R) / 3, lanes = { left: 0, middle: 1, right: 2 };
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Target map">';
      for (var a = -10; a <= maxA; a += 10) g += '<line x1="' + Lf + '" x2="' + (Wd - R) + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '" stroke-width="' + (a === 0 ? 2 : 1) + '"/><text x="' + (Lf - 6) + '" y="' + (y(a) + 4) + '" fill="' + FI + '" font-size="10" text-anchor="end">' + a + '</text>';
      g += '<text x="' + (Lf - 6) + '" y="' + (Tp - 24) + '" fill="' + FI + '" font-size="10" text-anchor="end">air yds</text>';
      ['Left', 'Middle', 'Right'].forEach(function (n, i) { g += '<text x="' + (Lf + lw * i + lw / 2) + '" y="' + (H - 10) + '" fill="' + FI + '" font-size="11" text-anchor="middle">' + n + '</text>'; if (i) g += '<line x1="' + (Lf + lw * i) + '" x2="' + (Lf + lw * i) + '" y1="' + Tp + '" y2="' + (H - B) + '" stroke="' + FL + '" stroke-dasharray="2 4"/>'; });
      var byLane = { left: [], middle: [], right: [] };
      D.forEach(function (p) { var li = lanes[p.loc] == null ? 1 : lanes[p.loc]; (byLane[p.loc] || byLane.middle).push(p); var cx = Lf + lw * li + lw * (.12 + .76 * hash(p.i + 1)), cy = y(p.air), c = p.td ? COL.td : p.k === 'int' ? COL.bad : p.k === 'inc' ? COL.inc : COL.pass, gm = gIdx[p.g] || {}; g += '<circle class="dbd-pdot" cx="' + cx + '" cy="' + cy + '" r="' + (p.td ? 7 : 5.5) + '" fill="' + c + '" fill-opacity=".9" stroke="rgba(0,0,0,.35)"><title>Wk ' + gm.week + ' ' + esc(gm.opp) + ': ' + esc(p.desc) + '</title></circle>'; });
      Object.keys(byLane).forEach(function (k) { var arr = byLane[k]; if (!arr.length) return; var c = arr.filter(function (p) { return p.k === 'comp'; }).length; g += '<text x="' + (Lf + lw * lanes[k] + lw / 2) + '" y="' + (Tp - 24) + '" fill="#fff" font-size="11" font-weight="600" text-anchor="middle">' + c + '/' + arr.length + ' · ' + Math.round(c / arr.length * 100) + '%</text>'; });
      return g + '</svg>';
    }
    function gaps(R) {
      var cols = [['left', 'end', 'L End', 40], ['left', 'tackle', 'L Tackle', 112], ['left', 'guard', 'L Guard', 170], ['middle', null, 'Middle', 200], ['right', 'guard', 'R Guard', 230], ['right', 'tackle', 'R Tackle', 288], ['right', 'end', 'R End', 360]];
      return cols.map(function (c) { var arr = R.filter(function (q) { return q.loc === c[0] && (c[0] === 'middle' || q.gap === c[1]); }); var n = arr.length; return { n: c[2], x: c[3], c: n, ypc: n ? arr.reduce(function (s, q) { return s + (q.yds || 0); }, 0) / n : 0, sr: n ? arr.filter(function (q) { return q.succ; }).length / n : 0, epa: n ? arr.reduce(function (s, q) { return s + (q.epa || 0); }, 0) / n : 0 }; });
    }
    /* carries drawn as lanes off the line of scrimmage, between the five linemen; bar height = carries, label = yards per carry */
    function runmap(R, num, scaleMax, color) {
      var data = gaps(R), Wd = 400, H = 420, LOS = 292, TOP = 48, maxC = Math.max.apply(null, [1, scaleMax || 0].concat(data.map(function (d) { return d.c; })));
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Carries by gap, drawn off the offensive line">';
      for (var yd = 1; yd <= 4; yd++) { var yy = LOS - (LOS - TOP) * yd / 4; g += '<line x1="12" x2="' + (Wd - 12) + '" y1="' + yy + '" y2="' + yy + '" stroke="' + FL + '" stroke-dasharray="2 6"/>'; }
      g += '<line x1="12" x2="' + (Wd - 12) + '" y1="' + LOS + '" y2="' + LOS + '" stroke="rgba(255,255,255,.8)" stroke-width="2"/>';
      g += '<text x="' + (Wd - 14) + '" y="' + (TOP - 8) + '" fill="' + FI + '" font-size="10" text-anchor="end">bar height = carries (' + maxC + ' at the top) · label = yards per carry</text>';
      data.forEach(function (d, di) { var bh = (LOS - TOP - 6) * d.c / maxC, bx = d.x - 11, by = LOS - bh, fill = d.c ? (d.epa >= 0 ? COL.td : d.sr >= .4 ? COL.run : COL.bad) : 'rgba(255,255,255,.08)';
        g += '<rect x="' + bx + '" y="' + (d.c ? by : LOS - 3) + '" width="22" height="' + (d.c ? bh : 3) + '" rx="3" fill="' + fill + '" fill-opacity=".9"/>';
        if (d.c) g += '<text x="' + d.x + '" y="' + (by - 6) + '" fill="#fff" font-size="12" font-weight="600" text-anchor="middle">' + f1(d.ypc) + '</text>';
        g += '<text x="' + d.x + '" y="' + (LOS + 56) + '" fill="#fff" font-size="11" font-weight="600" text-anchor="middle">' + (d.c || '') + '</text>';
        g += '<text x="' + d.x + '" y="' + (LOS + (di % 2 ? 82 : 70)) + '" fill="' + FI + '" font-size="9" text-anchor="middle">' + d.n + '</text>';  /* alternate rows so the three inside gaps do not collide */
      });
      [['LT', 84], ['LG', 141], ['C', 200], ['RG', 259], ['RT', 316]].forEach(function (l) { g += '<rect x="' + (l[1] - 17) + '" y="' + (LOS + 8) + '" width="34" height="30" rx="5" fill="#f4f1ea" stroke="rgba(0,0,0,.25)"/><text x="' + l[1] + '" y="' + (LOS + 28) + '" fill="#161e36" font-size="12" font-weight="800" font-family="Tourney,Arial Black,sans-serif" text-anchor="middle">' + l[0] + '</text>'; });
      g += '<circle cx="200" cy="' + (LOS + 104) + '" r="15" fill="' + (color || '#161e36') + '" stroke="#fff" stroke-width="2"/><text x="200" y="' + (LOS + 109) + '" fill="#fff" font-size="12" font-weight="800" font-family="Tourney,Arial Black,sans-serif" text-anchor="middle">' + esc(num == null ? 'RB' : num) + '</text>';
      g += '</svg>';  /* the counts under the line are carries; the caption at the top says so */
      return g;
    }
    var runFirst = grp === 'rb' && runs.length >= tgts.length;
    if (runFirst) {
      $('#dbd-map1h').textContent = 'Carries by gap'; $('#dbd-pmap').innerHTML = runmap(runs, C.num, 0, C.accent); $('#dbd-mapn').textContent = runs.length + ' carries · ' + tgts.length + ' targets';
      $('#dbd-pleg').innerHTML = '<span><i class="dot" style="background:' + COL.td + '"></i>Positive EPA</span><span><i class="dot" style="background:' + COL.run + '"></i>Negative EPA, 40%+ success</span><span><i class="dot" style="background:' + COL.bad + '"></i>Under 40% success</span>';
      $('#dbd-mapnote').textContent = 'Designed runs only, grouped by the gap nflverse records. Scrambles and kneels are left out. Depth bands on the right are his targets.';
    } else {
      $('#dbd-map1h').textContent = grp === 'qb' ? 'Throws on the field' : 'Targets on the field'; $('#dbd-pmap').innerHTML = passmap(mapSet.filter(function (p) { return p.air != null; })); $('#dbd-mapn').textContent = mapSet.length + (grp === 'qb' ? ' throws' : ' targets');
      $('#dbd-pleg').innerHTML = '<span><i class="dot" style="background:' + COL.pass + '"></i>Catch</span><span><i class="dot" style="background:' + COL.inc + '"></i>Incomplete</span><span><i class="dot" style="background:' + COL.bad + '"></i>Interception</span><span><i class="dot" style="background:' + COL.td + '"></i>Touchdown</span>';
      $('#dbd-mapnote').textContent = 'Each dot is one target, placed by air yards beyond the line of scrimmage (the thick 0 line) and the side of the field. Hover a dot for the play.';
    }

    /* ---- depth bands */
    var bands = [['Behind the line', function (p) { return p.air < 0; }], ['Short, 0 to 9', function (p) { return p.air >= 0 && p.air < 10; }], ['Medium, 10 to 19', function (p) { return p.air >= 10 && p.air < 20; }], ['Deep, 20+', function (p) { return p.air >= 20; }]];
    var bSet = mapSet.filter(function (p) { return p.air != null; }), bmax = Math.max.apply(null, [1].concat(bands.map(function (b) { return bSet.filter(b[1]).length; })));
    $('#dbd-bands').innerHTML = bSet.length ? bands.map(function (b) { var a = bSet.filter(b[1]), c = a.filter(function (p) { return p.k === 'comp'; }).length, y = a.reduce(function (s, p) { return s + (p.yds || 0); }, 0); return '<div class="n">' + b[0] + '</div><div class="bar"><i style="--w:' + (a.length / bmax * 100) + '%"></i><i class="c" style="--w:' + (c / bmax * 100) + '%"></i></div><div class="t">' + a.length + ' tgt · ' + c + ' rec · ' + y + ' yds</div>'; }).join('') : '<p class="dbd-note" style="padding:0">No targets in the play file yet.</p>';
    var deep = bSet.filter(bands[3][1]), shortB = bSet.filter(bands[1][1]);
    $('#dbd-bandnote').textContent = bSet.length ? 'Green is the caught share of each bar. Deep: ' + (deep.length ? pc(deep.filter(function (p) { return p.k === 'comp'; }).length, deep.length) + ' caught' : 'no targets') + (shortB.length ? ', short: ' + pc(shortB.filter(function (p) { return p.k === 'comp'; }).length, shortB.length) : '') + '.' : '';

    /* ---- situations */
    var touches = grp === 'qb' ? qbPass : mine.filter(function (p) { return isT(p) || p.k === 'run'; });
    var teamTouch = grp === 'qb' ? teamPass : P.filter(function (p) { return isT(p) || p.k === 'run'; });
    var sitRow = function (n, f) { var a = touches.filter(f), t = teamTouch.filter(f), y = a.reduce(function (s, p) { return s + (p.yds || 0); }, 0), e = a.reduce(function (s, p) { return s + (p.epa || 0); }, 0); return '<tr><td>' + n + '</td><td>' + a.length + '</td><td>' + (t.length ? Math.round(a.length / t.length * 100) + '%' : '-') + '</td><td>' + (a.length ? f1(y / a.length) : '-') + '</td><td class="' + (e >= 0 ? 'pos' : 'neg') + '">' + (a.length ? sgn(e / a.length) : '-') + '</td></tr>'; };
    var hdr = '<tr><th></th><th>Touches</th><th>Of team</th><th>Yds/touch</th><th>EPA</th></tr>';
    $('#dbd-sit').innerHTML = '<div><h4>By down</h4><table class="dbd-stat dbd-sortable">' + hdr + [1, 2, 3, 4].map(function (d) { return sitRow(['', '1st', '2nd', '3rd', '4th'][d], function (p) { return p.dn == d; }); }).join('') + '</table></div>' +
      '<div><h4>By quarter</h4><table class="dbd-stat dbd-sortable">' + hdr + [1, 2, 3, 4].map(function (q) { return sitRow('Q' + q, function (p) { return p.q == q; }); }).join('') + '</table></div>' +
      '<div><h4>By game score</h4><table class="dbd-stat dbd-sortable">' + hdr + [['Leading', function (p) { return p.sd > 0; }], ['Tied', function (p) { return p.sd == 0; }], ['Trailing', function (p) { return p.sd < 0; }], ['Red zone', function (p) { return p.yl <= 20; }]].map(function (s) { return sitRow(s[0], s[1]); }).join('') + '</table></div>';

    /* ---- intel fills */
    var fill = function (k, txt, cls) { var el = root.querySelector('[data-fill="' + k + '"]'); if (!el) return; el.textContent = txt; if (cls) el.className = cls; };
    var rz = touches.filter(function (p) { return p.yl <= 20; }), third = touches.filter(function (p) { return p.dn == 3; }), conv = third.filter(function (p) { return p.fd || p.td; }).length;
    var expl = (grp === 'qb' ? qbPass : mine).filter(function (p) { return (p.pt === 'pass' && p.yds >= 20) || (p.pt === 'run' && p.yds >= 10); }).length;
    fill('rz', rz.length + ' · ' + rz.filter(function (p) { return p.td; }).length + ' TD');
    fill('third', third.length + ' touches · ' + (third.length ? Math.round(conv / third.length * 100) : 0) + '% converted');
    fill('expl', expl + ' · 20+ catch, 10+ run');
    if (hasFtn) {
      var chM = mapSet.filter(charted), pa = chM.filter(function (p) { return p.pa; }), paC = pa.filter(function (p) { return p.k === 'comp'; }).length;
      fill('pa', chM.length ? pa.length + ' of ' + chM.length + ' charted ' + (grp === 'qb' ? 'throws' : 'targets') + (pa.length ? ' · ' + pc(paC, pa.length) + ' caught' : '') : 'no charted plays yet');
      var chT = tgts.filter(charted), cont = chT.filter(function (p) { return p.cont; }), contC = cont.filter(function (p) { return p.k === 'comp'; }).length;
      fill('cont', chT.length ? (cont.length ? cont.length + ' contested · ' + contC + ' caught' : 'none charted contested') : 'no charted plays yet');
      var chQ = qbPass.filter(charted), blz = chQ.filter(function (p) { return p.blz; });
      fill('blz', chQ.length ? blz.length + ' of ' + chQ.length + ' charted dropbacks' + (blz.length ? ' · ' + sgn(blz.reduce(function (s, p) { return s + (p.epa || 0); }, 0) / blz.length) + ' EPA' : '') : 'no charted plays yet');
    } else { ['pa', 'cont', 'blz'].forEach(function (k) { fill(k, 'charting arrives with the next data build'); }); }

    /* ---- best plays */
    var best = (grp === 'qb' ? qbPass : mine).slice().sort(function (a, b) { return (b.epa || 0) - (a.epa || 0); }).slice(0, 4);
    $('#dbd-best').innerHTML = best.length ? best.map(function (p) { var g = gIdx[p.g] || {}; return '<li><b>Wk ' + g.week + ' ' + (g.home ? 'vs' : 'at') + ' ' + esc(g.opp) + ' · Q' + p.q + ' ' + esc(p.t) + ' · ' + ['', '1st', '2nd', '3rd', '4th'][p.dn] + ' &amp; ' + p.togo + '</b><span class="e">EPA ' + sgn(p.epa || 0) + '</span><p>' + esc(p.desc) + '</p></li>'; }).join('') : '<li><p>No plays in the file yet.</p></li>';

    /* ---- the connection (SCOPE §18): on a quarterback's page his receivers, on everyone else's page his passers.
       Every number is from the play rows: qb is the passer, pid the targeted receiver on comp, inc and int plays. */
    (function () {
      var sec = $('#dbd-cx'); if (!sec || !byQb || !byId) return;
      var isQB = grp === 'qb', key = isQB ? 'pid' : 'qb', RC = ['#5b9cf6', '#f0b429', '#e0564b', '#4fd18b', '#c77dff', '#ff9f43'], OTH = '#9a96a6', MIN_T = 5, MIN_3 = 3;
      var throws = teamPass.filter(function (p) { return isT(p) && p.dn != null; }), mineT = throws.filter(function (p) { return (isQB ? p.qb : p.pid) === C.id; });  /* two-point tries have no down and are not targets */
      var rtd = function (p) { return p.k === 'comp' && !!p.td && !/FUMBLE/i.test(p.desc || ''); };  /* td is the play's flag; a catch-and-fumble returned for a score is not a receiving touchdown */
      if (!mineT.length) return;
      var R = C.roster || {}, who = isQB ? 'receiver' : 'passer', whos = isQB ? 'receivers' : 'passers', iso = null;
      var purl = function (id) { return C.base + (C.base.indexOf('?') >= 0 ? '&' : '?') + 'player=' + encodeURIComponent(id); };
      var sum = function (a, f) { return a.reduce(function (s, p) { return s + (f(p) || 0); }, 0); };
      var line = function (a) {
        var c = a.filter(function (p) { return p.k === 'comp'; }), air = a.filter(function (p) { return p.air != null; }), third = a.filter(function (p) { return p.dn == 3; }), rz = a.filter(function (p) { return p.yl <= 20; });
        return { n: a.length, c: c.length, y: sum(c, function (p) { return p.yds; }), ad: air.length ? sum(air, function (p) { return p.air; }) / air.length : null, yac: sum(c, function (p) { return p.yac; }),
          td: a.filter(rtd).length, ints: a.filter(function (p) { return p.k === 'int'; }).length, e: sum(a, function (p) { return p.epa; }),
          third: third.length, thirdc: third.filter(function (p) { return p.fd || p.td; }).length, rz: rz.length, rzt: rz.filter(rtd).length };
      };
      var clamp = function (v) { return Math.max(0, Math.min(2.375, v)); };
      var prate = function (l) { if (!l.n) return '-'; return f1((clamp((l.c / l.n - .3) * 5) + clamp((l.y / l.n - 3) * .25) + clamp(l.td / l.n * 20) + clamp(2.375 - l.ints / l.n * 25)) / 6 * 100); };
      var groups = {}; mineT.forEach(function (p) { var k = p[key] || '_none'; (groups[k] = groups[k] || []).push(p); });
      var parts = Object.keys(groups).filter(function (k) { return k !== '_none'; }).map(function (k) { var r = R[k] || {}, fb = isQB ? (groups[k][0].pl || k) : 'Passer ' + k.slice(-4);
        return { id: k, known: !!R[k], nm: r.name || fb, short: r.short || fb, pos: r.pos || '', num: r.num, photo: r.photo, l: line(groups[k]), q: groups[k] }; }).sort(function (a, b) { return b.l.n - a.l.n; });
      if (!parts.length) return;  /* only throwaways or batted balls so far: nothing to connect */
      sec.hidden = false; ['dbd-with', 'dbd-jump-with'].forEach(function (id) { var el = document.getElementById(id); if (el) el.hidden = false; });
      parts.forEach(function (r, i) { r.col = i < RC.length ? RC[i] : OTH; var all = isQB ? mineT : throws.filter(function (p) { return p.qb === r.id; }); r.oth = isQB ? null : line(all.filter(function (p) { return p.pid !== C.id; })); r.share = all.length ? r.l.n / all.length : 0; });
      var none = (groups._none || []).length, colOf = {}, nameOf = {}; parts.forEach(function (r) { colOf[r.id] = r.col; nameOf[r.id] = r.short; });
      $('#dbd-cx-n').textContent = mineT.length + (isQB ? ' throws' : ' targets') + ' · ' + parts.length + ' ' + (parts.length === 1 ? who : whos);
      $('#dbd-cx-maph').textContent = isQB ? 'Where each receiver gets it' : 'Who threw it where';
      $('#dbd-cx-wkh').textContent = isQB ? 'How he spreads it, week by week' : 'Targets by week, by passer';

      /* table */
      var tile = function (r) { return window.dbdPlayer ? window.dbdPlayer(C.team, r.num, 26, r.photo, C.heads) : ''; };
      var nmCell = function (r) { return '<td class="dbd-cx-nm" data-v="' + esc(r.nm) + '"><span class="dbd-cx-nmi"><i class="dbd-cx-sw" style="--c:' + r.col + '"></i>' + tile(r) + (r.known ? '<a href="' + esc(purl(r.id)) + '">' + esc(r.nm) + '</a>' : '<span>' + esc(r.nm) + '</span>') + '<small>' + esc(r.pos) + '</small></span></td>'; };
      var epaCell = function (l) { var v = l.n ? l.e / l.n : 0; return '<td class="' + (v >= 0 ? 'pos' : 'neg') + '">' + (l.n ? sgn(v) : '-') + '</td>'; };
      var stat = function (l) { return '<td>' + l.c + '</td><td>' + pc(l.c, l.n) + '</td><td>' + l.y + '</td><td>' + (l.ad == null ? '-' : f1(l.ad)) + '</td><td>' + (l.c ? f1(l.yac / l.c) : '-') + '</td><td>' + (l.td || '') + '</td>'; };
      var shareCell = function (r) { return '<td class="dbd-cx-sh" data-v="' + Math.round(r.share * 1000) / 10 + '">' + pc(r.share * 100, 100) + '<span class="dbd-cx-shb" style="--c:' + r.col + '"><i style="--w:' + Math.round(r.share * 100) + '%"></i></span></td>'; };
      /* column tiers (SCOPE §20): QB side aDOT, YAC, TD at t2 and 3rd dn, RZ at t3; passer side aDOT, YAC, TD, Rating at t2 */
      var TIERS = isQB ? [null, null, null, null, null, null, 'dbd-t2', 'dbd-t2', 'dbd-t2', 'dbd-t3', 'dbd-t3', null] : [null, null, null, null, null, null, 'dbd-t2', 'dbd-t2', 'dbd-t2', null, 'dbd-t2'];
      function tierize(html, tiers) {
        return html.replace(/<tr\b[^>]*>[\s\S]*?<\/tr>/g, function (row) { var i = -1; return row.replace(/<t([dh])\b([^>]*)>/g, function (m, tag, attrs) { i++; var t = tiers[i]; if (!t) return m; var cm = attrs.match(/\bclass="([^"]*)"/); attrs = cm ? attrs.replace(cm[0], 'class="' + t + ' ' + cm[1] + '"') : attrs + ' class="' + t + '"'; return '<t' + tag + attrs + '>'; }); });
      }
      function drawTab() {
        var h;
        if (isQB) {
          h = '<thead><tr><th>Receiver</th><th>Tgt</th><th>Share</th><th>Rec</th><th>Catch</th><th>Yds</th><th>aDOT</th><th>YAC/rec</th><th>TD</th><th>3rd dn</th><th>RZ</th><th>EPA/tgt</th></tr></thead><tbody>';
          parts.forEach(function (r) { var l = r.l; h += '<tr data-id="' + esc(r.id) + '" class="' + (iso === r.id ? 'on' : '') + '">' + nmCell(r) + '<td>' + l.n + '</td>' + shareCell(r) + stat(l) +
            '<td data-v="' + l.third + '">' + (l.third ? l.third + ' <small>· ' + l.thirdc + ' conv</small>' : '') + '</td><td data-v="' + l.rz + '">' + (l.rz ? l.rz + ' <small>· ' + l.rzt + ' TD</small>' : '') + '</td>' + epaCell(l) + '</tr>'; });
          var all = line(mineT);
          h += '</tbody><tfoot><tr><td class="dbd-cx-nm">All throws</td><td>' + all.n + '</td><td></td>' + stat(all) + '<td>' + all.third + '</td><td>' + all.rz + '</td>' + epaCell(all) + '</tr></tfoot>';
        } else {
          h = '<thead><tr><th>Passer</th><th>Tgt</th><th>Of his throws</th><th>Rec</th><th>Catch</th><th>Yds</th><th>aDOT</th><th>YAC/rec</th><th>TD</th><th>EPA/tgt</th><th>Rating</th></tr></thead><tbody>';
          parts.forEach(function (r) { var l = r.l, o = r.oth; h += '<tr data-id="' + esc(r.id) + '" class="' + (iso === r.id ? 'on' : '') + '">' + nmCell(r) + '<td>' + l.n + '</td>' + shareCell(r) + stat(l) + epaCell(l) + '<td>' + prate(l) + '</td></tr>';
            if (o && o.n) h += '<tr class="dbd-cx-oth"><td class="dbd-cx-nm">to everyone else</td><td>' + o.n + '</td><td></td>' + stat(o) + epaCell(o) + '<td>' + prate(o) + '</td></tr>'; });
          h += '</tbody>';
        }
        $('#dbd-cx-tab').className = 'dbd-cx-tab dbd-stat' + (isQB ? ' dbd-sortable is-qb' : ' is-rx');  /* is-qb / is-rx: which columns are core (SCOPE §31) */
        $('#dbd-cx-tab').innerHTML = tierize(h, TIERS);
      }
      $('#dbd-cx-tab').addEventListener('click', function (e) { if (e.target.closest('a, th')) return; var tr = e.target.closest('tr[data-id]'); if (tr) setIso(iso === tr.dataset.id ? null : tr.dataset.id); });

      /* the takeaway line */
      var q5 = parts.filter(function (r) { return r.l.n >= MIN_T; }), top = parts[0], say;
      var deep = q5.slice().sort(function (a, b) { return (b.l.ad || 0) - (a.l.ad || 0); })[0], best = q5.slice().sort(function (a, b) { return b.l.e / b.l.n - a.l.e / a.l.n; })[0], third = parts.slice().sort(function (a, b) { return b.l.third - a.l.third; })[0];
      var B = function (r) { return '<b>' + esc(r.short) + '</b>'; }, epa = function (l) { return sgn(l.e / l.n); };
      if (isQB) {
        say = B(top) + ' is the first read: ' + top.l.n + ' of ' + mineT.length + ' throws (' + pc(top.share * 100, 100) + '), ' + pc(top.l.c, top.l.n) + ' caught, ' + epa(top.l) + ' EPA a throw.';
        if (deep && deep !== top) say += ' ' + B(deep) + ' draws the deep ball, ' + f1(deep.l.ad) + ' air yards a target.';
        if (best && best !== top) say += ' The most efficient pairing is ' + B(best) + ' at ' + epa(best.l) + ' a throw on ' + best.l.n + ' targets.';
        if (third && third.l.third >= MIN_3) say += ' On third down he looks for ' + B(third) + ' (' + third.l.third + ' targets, ' + third.l.thirdc + ' converted).';
        if (none) say += ' ' + none + ' throw' + (none > 1 ? 's' : '') + ' had no intended receiver.';
      } else {
        say = parts.map(function (r) { return B(r) + ' to him: ' + r.l.n + ' targets, ' + pc(r.l.c, r.l.n) + ' caught, ' + epa(r.l) + ' EPA a target' + (r.oth && r.oth.n ? '; to everyone else ' + pc(r.oth.c, r.oth.n) + ' and ' + epa(r.oth) : '') + '.'; }).join(' ');
        if (parts.length > 1) say += ' He is ' + pc(parts[0].share * 100, 100) + ' of ' + esc(parts[0].short) + "'s throws and " + pc(parts[1].share * 100, 100) + ' of ' + esc(parts[1].short) + "'s.";
      }
      $('#dbd-cx-say').innerHTML = say;

      /* the field, dots colored by receiver or passer; outcome moves to shape */
      var mapSet2 = mineT.filter(function (p) { return p.air != null; });
      var tipFor = function (p) { var gm = gIdx[p.g] || {}; return '<b>' + esc(nameOf[p[key]] || p.pl || 'no target') + '</b> · Wk ' + gm.week + ' ' + (gm.home ? 'vs' : 'at') + ' ' + esc(gm.opp) + '<br>' + esc(p.desc); };
      function cmap(D) {
        var Wd = 360, H = 420, Tp = 40, Bm = 30, Lf = 34, Rt = 10, airs = D.map(function (p) { return p.air; });
        var minA = Math.min(-10, Math.floor(Math.min.apply(null, airs.concat([0])) / 5) * 5), maxA = Math.max.apply(null, [40].concat(airs));
        var y = function (a) { return Tp + (maxA - a) / (maxA - minA) * (H - Tp - Bm); }, lw = (Wd - Lf - Rt) / 3, lanes = { left: 0, middle: 1, right: 2 };
        var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Target map by ' + who + '">';
        for (var a = -10; a <= maxA; a += 10) g += '<line x1="' + Lf + '" x2="' + (Wd - Rt) + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '" stroke-width="' + (a === 0 ? 2 : 1) + '"/><text x="' + (Lf - 6) + '" y="' + (y(a) + 4) + '" fill="' + FI + '" font-size="10" text-anchor="end">' + a + '</text>';
        g += '<text x="' + (Lf - 6) + '" y="' + (Tp - 24) + '" fill="' + FI + '" font-size="10" text-anchor="end">air yds</text>';
        ['Left', 'Middle', 'Right'].forEach(function (n, i) { g += '<text x="' + (Lf + lw * i + lw / 2) + '" y="' + (H - 10) + '" fill="' + FI + '" font-size="11" text-anchor="middle">' + n + '</text>'; if (i) g += '<line x1="' + (Lf + lw * i) + '" x2="' + (Lf + lw * i) + '" y1="' + Tp + '" y2="' + (H - Bm) + '" stroke="' + FL + '" stroke-dasharray="2 4"/>'; });
        var byLane = { left: [], middle: [], right: [] }, on = function (p) { return !iso || (p[key] || '_none') === iso; };
        D.slice().sort(function (a, b) { return on(a) - on(b); }).forEach(function (p) {  /* the isolated player's dots draw last, on top */
          var li = lanes[p.loc] == null ? 1 : lanes[p.loc]; if (on(p)) (byLane[p.loc] || byLane.middle).push(p);
          var cx = Lf + lw * li + lw * (.12 + .76 * hash(p.i + 1)), cy = y(p.air), c = colOf[p[key]] || OTH, td = rtd(p), r = td ? 7 : 5.5, o = on(p) ? 1 : .12, tp = ' tabindex="0" data-tip="' + esc(tipFor(p)) + '"';
          if (p.k === 'comp') g += '<circle class="dbd-pdot" cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="' + c + '" fill-opacity="' + (o * .92) + '" stroke="' + (td ? '#fff' : 'rgba(0,0,0,.35)') + '" stroke-width="' + (td ? 2 : 1) + '" stroke-opacity="' + o + '"' + tp + '></circle>';
          else g += '<circle class="dbd-pdot" cx="' + cx + '" cy="' + cy + '" r="' + (r - 1) + '" fill="none" stroke="' + (p.k === 'int' ? COL.bad : c) + '" stroke-width="2" stroke-opacity="' + (o * .95) + '"' + tp + '></circle>';
        });
        Object.keys(byLane).forEach(function (k) { var arr = byLane[k]; if (!arr.length) return; var c = arr.filter(function (p) { return p.k === 'comp'; }).length; g += '<text x="' + (Lf + lw * lanes[k] + lw / 2) + '" y="' + (Tp - 24) + '" fill="#fff" font-size="11" font-weight="600" text-anchor="middle">' + c + '/' + arr.length + ' · ' + Math.round(c / arr.length * 100) + '%</text>'; });
        return g + '</svg>';
      }
      function drawMap() { $('#dbd-cx-map').innerHTML = mapSet2.length ? cmap(mapSet2) : '<p class="dbd-note">No air-yards data on these targets yet.</p>'; }
      var chips = $('#dbd-cx-chips');
      chips.innerHTML = parts.slice(0, RC.length).map(function (r) { return '<button type="button" data-id="' + esc(r.id) + '" aria-pressed="false" style="--c:' + r.col + '"><i></i>' + esc(r.short) + '<small>' + r.l.n + '</small></button>'; }).join('') +
        (parts.length > RC.length ? '<span class="dbd-cx-chip"><i style="background:' + OTH + '"></i>' + (parts.length - RC.length) + ' others<small>' + parts.slice(RC.length).reduce(function (s, r) { return s + r.l.n; }, 0) + '</small></span>' : '');
      function setIso(id) { iso = id; chips.classList.toggle('dim', !!iso); chips.querySelectorAll('button').forEach(function (b) { b.setAttribute('aria-pressed', b.dataset.id === iso ? 'true' : 'false'); }); drawMap(); drawWks(); var cur = null; sec.querySelectorAll('#dbd-cx-tab tbody tr').forEach(function (tr) { if (tr.dataset.id) cur = tr.dataset.id; tr.classList.toggle('on', !!iso && cur === iso); tr.classList.toggle('dim', !!iso && cur !== iso); }); }  /* rows toggle in place so a sort survives; a "to everyone else" row follows its passer */
      chips.addEventListener('click', function (e) { var b = e.target.closest('button'); if (!b) return; setIso(iso === b.dataset.id ? null : b.dataset.id); });
      $('#dbd-cx-mapnote').textContent = 'Filled dots are catches, hollow are incompletions, a red ring is an interception, a white ring a touchdown. Click a ' + who + ' to isolate his targets; click again to clear.';

      /* weekly stacks on league weeks: a bye is a dashed baseline, a week not yet played is blank */
      var weeks = [], gw = {}, byes = RAW.byes || [], thru = Math.max(C.through || 0, G.reduce(function (m, g) { return Math.max(m, g.week || 0); }, 0));
      for (var w = 1; w <= thru; w++) weeks.push(w);
      G.forEach(function (g) { gw[g.week] = g; });
      function drawWks() {
        var withR = mineT.filter(function (p) { return p[key]; });  /* the segments are per receiver, so the totals are too; no-receiver throws are in the takeaway line */
        var tot = weeks.map(function (w) { return withR.filter(function (p) { return (gIdx[p.g] || {}).week === w; }).length; }), mx = Math.max.apply(null, [1].concat(tot));
        $('#dbd-cx-wks').innerHTML = weeks.map(function (w, i) { var g = gw[w]; if (!g) return '<div class="dbd-cx-col' + (byes.indexOf(w) >= 0 ? ' bye' : '') + '"></div>'; var T = tot[i];
          return '<div class="dbd-cx-col"><b style="--t:' + (T / mx * 100) + '%">' + (T || '') + '</b>' + parts.map(function (r) { var a = r.q.filter(function (p) { return p.g === g.id; }); if (!a.length) return ''; var l = line(a);
            return '<i class="' + (iso && iso !== r.id ? 'dim' : '') + '" style="--c:' + r.col + ';--h:' + (a.length / mx * 100) + '%" tabindex="0" data-tip="<b>' + esc(r.short) + '</b> · Wk ' + w + ' ' + (g.home ? 'vs' : 'at') + ' ' + esc(g.opp) + '<br>' + l.n + ' tgt · ' + l.c + ' rec · ' + l.y + ' yds · ' + sgn(l.e, 1) + ' EPA"></i>'; }).join('') + '</div>'; }).join('');
        $('#dbd-cx-wkl').innerHTML = weeks.map(function (w) { return '<span>' + w + '<br><small>' + (gw[w] ? esc(gw[w].opp) : (byes.indexOf(w) >= 0 ? 'bye' : '')) + '</small></span>'; }).join('');
      }

      /* depth grid */
      var dbands = [['Behind', function (p) { return p.air < 0; }], ['Short', function (p) { return p.air >= 0 && p.air < 10; }], ['Medium', function (p) { return p.air >= 10 && p.air < 20; }], ['Deep', function (p) { return p.air >= 20; }]];
      var rows = parts.slice(0, RC.length); if (parts.length > RC.length) rows.push({ short: (parts.length - RC.length) + ' others', col: OTH, q: parts.slice(RC.length).reduce(function (a, r) { return a.concat(r.q); }, []) });
      var cells = rows.map(function (r) { return dbands.map(function (b) { return r.q.filter(function (p) { return p.air != null; }).filter(b[1]); }); }), mxc = Math.max.apply(null, [1].concat(cells.reduce(function (a, x) { return a.concat(x.map(function (c) { return c.length; })); }, [])));
      $('#dbd-cx-dg').innerHTML = '<div></div>' + dbands.map(function (b) { return '<div class="h">' + b[0] + '</div>'; }).join('') + rows.map(function (r, i) { return '<div class="r"><i class="dbd-cx-sw" style="--c:' + r.col + '"></i>' + esc(r.short) + '</div>' + cells[i].map(function (a, j) { var n = a.length, c = a.filter(function (p) { return p.k === 'comp'; }).length, al = n / mxc;
        return n ? '<div class="c' + (al > .6 ? ' hot' : '') + '" style="--a:' + al + '" tabindex="0" data-tip="<b>' + esc(r.short) + '</b> · ' + dbands[j][0].toLowerCase() + '<br>' + n + ' targets · ' + c + ' caught · ' + sum(a, function (p) { return p.yds; }) + ' yds"><b>' + n + '</b><small>' + Math.round(c / n * 100) + '%</small></div>' : '<div class="c z" style="--a:0">·</div>'; }).join(''); }).join('');
      drawTab(); drawMap(); drawWks();
    })();
  }
})();
