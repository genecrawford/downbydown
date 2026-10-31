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
    function runmap(R) {
      var cols = [['left', 'end', 'L End'], ['left', 'tackle', 'L Tackle'], ['left', 'guard', 'L Guard'], ['middle', null, 'Middle'], ['right', 'guard', 'R Guard'], ['right', 'tackle', 'R Tackle'], ['right', 'end', 'R End']];
      var data = cols.map(function (c) { var arr = R.filter(function (p) { return p.loc === c[0] && (c[0] === 'middle' || p.gap === c[1]); }); var n = arr.length; return { n: c[2], c: n, ypc: n ? arr.reduce(function (s, p) { return s + (p.yds || 0); }, 0) / n : 0, sr: n ? arr.filter(function (p) { return p.succ; }).length / n : 0, epa: n ? arr.reduce(function (s, p) { return s + (p.epa || 0); }, 0) / n : 0 }; });
      var Wd = 360, H = 420, Tp = 50, B = 70, Lf = 10, Rr = 10, maxC = Math.max.apply(null, [1].concat(data.map(function (d) { return d.c; }))), cw = (Wd - Lf - Rr) / 7;
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Run gap chart"><text x="' + (Wd / 2) + '" y="18" fill="' + FI + '" font-size="10" text-anchor="middle">bar height = carries · label = yards per carry</text>';
      data.forEach(function (d, i) { var bh = (H - Tp - B) * d.c / maxC, bx = Lf + cw * i + 5, by = H - B - bh, fill = d.c ? (d.epa >= 0 ? COL.td : d.sr >= .4 ? COL.run : COL.bad) : 'transparent'; g += '<rect x="' + bx + '" y="' + by + '" width="' + (cw - 10) + '" height="' + bh + '" rx="2" fill="' + fill + '" fill-opacity=".85"/>'; if (d.c) g += '<text x="' + (bx + (cw - 10) / 2) + '" y="' + (by - 6) + '" fill="#fff" font-size="12" font-weight="600" text-anchor="middle">' + f1(d.ypc) + '</text>'; var parts = d.n.split(' '); g += '<text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 16) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + parts[0] + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 28) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + (parts[1] || '') + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 46) + '" fill="#fff" font-size="11" text-anchor="middle">' + d.c + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 60) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + (d.c ? Math.round(d.sr * 100) + '%' : '') + '</text>'; });
      return g + '<text x="4" y="' + (H - B + 46) + '" fill="' + FI + '" font-size="9">n</text><text x="4" y="' + (H - B + 60) + '" fill="' + FI + '" font-size="9">SR</text></svg>';
    }
    var runFirst = grp === 'rb' && runs.length >= tgts.length;
    if (runFirst) {
      $('#dbd-map1h').textContent = 'Carries by gap'; $('#dbd-pmap').innerHTML = runmap(runs); $('#dbd-mapn').textContent = runs.length + ' carries · ' + tgts.length + ' targets';
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
