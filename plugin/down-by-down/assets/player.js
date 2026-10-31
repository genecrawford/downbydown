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
      $('#dbd-pleg').innerHTML = '<span><i style="background:' + COL.td + '"></i>Positive EPA</span><span><i style="background:' + COL.run + '"></i>Negative EPA, 40%+ success</span><span><i style="background:' + COL.bad + '"></i>Under 40% success</span>';
      $('#dbd-mapnote').textContent = 'Designed runs only, grouped by the gap nflverse records. Scrambles and kneels are left out. Depth bands on the right are his targets.';
    } else {
      $('#dbd-map1h').textContent = grp === 'qb' ? 'Throws on the field' : 'Targets on the field'; $('#dbd-pmap').innerHTML = passmap(mapSet.filter(function (p) { return p.air != null; })); $('#dbd-mapn').textContent = mapSet.length + (grp === 'qb' ? ' throws' : ' targets');
      $('#dbd-pleg').innerHTML = '<span><i style="background:' + COL.pass + '"></i>Catch</span><span><i style="background:' + COL.inc + '"></i>Incomplete</span><span><i style="background:' + COL.bad + '"></i>Interception</span><span><i style="background:' + COL.td + '"></i>Touchdown</span>';
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
    $('#dbd-sit').innerHTML = '<div><h4>By down</h4><table class="dbd-sortable">' + hdr + [1, 2, 3, 4].map(function (d) { return sitRow(['', '1st', '2nd', '3rd', '4th'][d], function (p) { return p.dn == d; }); }).join('') + '</table></div>' +
      '<div><h4>By quarter</h4><table class="dbd-sortable">' + hdr + [1, 2, 3, 4].map(function (q) { return sitRow('Q' + q, function (p) { return p.q == q; }); }).join('') + '</table></div>' +
      '<div><h4>By game score</h4><table class="dbd-sortable">' + hdr + [['Leading', function (p) { return p.sd > 0; }], ['Tied', function (p) { return p.sd == 0; }], ['Trailing', function (p) { return p.sd < 0; }], ['Red zone', function (p) { return p.yl <= 20; }]].map(function (s) { return sitRow(s[0], s[1]); }).join('') + '</table></div>';

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
  }
})();
