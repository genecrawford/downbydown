/* Down by Down: the compare page's browser half. PHP rendered the slots, ranks, aligned log and season table;
   this adds the slot search over the same-group index, the compare tray, the metric switch, and the field maps
   drawn from each team's play file on one shared scale. Config in DBD_CMP. */
(function () {
  var C = window.DBD_CMP, root = document.querySelector('.dbd-view-compare');
  if (!C || !root) return;
  window.DBD_TEAMS = C.teams;
  var $ = function (s) { return root.querySelector(s); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var ids = C.players.map(function (p) { return p.id; });
  var url = function (list) { return C.base + (C.base.indexOf('?') >= 0 ? '&' : '?') + 'compare=' + encodeURIComponent(list.join(',')); };
  var f1 = function (v) { return isFinite(v) ? (+v).toFixed(1) : '-'; };

  /* the tray: whoever is on this page is what Compare buttons elsewhere build on */
  try { localStorage.setItem('dbd-cmp', JSON.stringify(ids)); } catch (e) {}

  /* slot search over the same-group index */
  var src = document.getElementById('dbd-cindex'), IDX = [];
  try { IDX = JSON.parse(src ? src.textContent : '[]'); } catch (e) {}
  root.querySelectorAll('.dbd-cslot.empty input').forEach(function (inp) {
    var list = inp.parentNode.querySelector('.dbd-csug');
    var render = function () {
      var q = inp.value.trim().toLowerCase(); if (!q) { list.hidden = true; list.innerHTML = ''; return; }
      var hits = IDX.filter(function (p) { return p.name.toLowerCase().indexOf(q) >= 0 || (p.team || '').toLowerCase() === q || String(p.num) === q; }).slice(0, 8);
      list.innerHTML = hits.length ? hits.map(function (p) { return '<li><a href="' + esc(url(ids.concat([p.id]))) + '">' + window.dbdPlayer(p.team, p.num, 24, null, false) + '<b>' + esc(p.name) + '</b><small>' + esc(p.pos + ' · ' + p.team + ' · ' + (p.snap | 0) + '% snaps') + '</small></a></li>'; }).join('') : '<li class="none">No one by that name in this group.</li>';
      list.hidden = false;
    };
    var tm; inp.addEventListener('input', function () { clearTimeout(tm); tm = setTimeout(render, 100); });
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { var a = list.querySelector('a'); if (a) location.href = a.href; } if (e.key === 'Escape') { list.hidden = true; } });
    document.addEventListener('click', function (e) { if (!inp.parentNode.contains(e.target)) list.hidden = true; });
  });

  /* metric switch on the aligned log */
  var seg = $('.dbd-cmetric');
  if (seg) {
    var pill = function (b, anim) { var pl = seg.querySelector('.t-tabs-pill'); if (!pl || !b) return; var go = function () { pl.style.transform = 'translateX(' + b.offsetLeft + 'px)'; pl.style.width = b.offsetWidth + 'px'; }; if (anim) { go(); return; } var prev = pl.style.transition; pl.style.transition = 'none'; go(); void pl.offsetWidth; pl.style.transition = prev; };
    seg.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; seg.querySelectorAll('.t-tab').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); root.querySelectorAll('[data-metric]').forEach(function (t) { t.hidden = t.dataset.metric !== b.dataset.v; }); pill(b, true); });
    requestAnimationFrame(function () { pill(seg.querySelector('[aria-selected="true"]'), false); });
    window.addEventListener('resize', function () { pill(seg.querySelector('[aria-selected="true"]'), false); });
  }

  /* the race and the grouped weeks: Chartist on the weekly series PHP handed over */
  if (window.Chartist && C.series && C.series.length) {
    var race = C.series.map(function (sv) { var c = 0; return sv.map(function (v) { c += v || 0; return c; }); });
    var CH1 = $('#dbd-crace'), CH2 = $('#dbd-cweek');
    if (CH1) new Chartist.LineChart(CH1, { labels: C.labels, series: race }, { lineSmooth: Chartist.Interpolation.step(), showPoint: true, axisY: { onlyInteger: true, low: 0 }, chartPadding: { right: 20, top: 10 } });
    if (CH2) new Chartist.BarChart(CH2, { labels: C.labels, series: C.series.map(function (sv) { return sv.map(function (v) { return v || 0; }); }) }, { seriesBarDistance: C.series.length > 2 ? 9 : 12, axisY: { onlyInteger: true, low: 0 }, chartPadding: { right: 10, top: 10 } });
  }

  /* field maps: one play file per distinct team, drawn on a shared air-yard scale */
  if (!C.players.length) return;
  var COL = { pass: '#5b9cf6', inc: '#9a96a6', bad: '#e0564b', td: '#4fd18b', run: '#f0b429' }, FL = 'rgba(255,255,255,.22)', FI = 'rgba(255,255,255,.75)';
  var teams = Object.keys(C.files);
  Promise.all(teams.map(function (t) { return fetch(C.files[t], { credentials: 'omit' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }); })).then(function (raws) {
    var plays = {};
    raws.forEach(function (RAW, i) { var K = RAW.keys; plays[teams[i]] = RAW.plays.map(function (r, j) { var o = { i: j }; K.forEach(function (k, m) { o[k] = r[m]; }); return o; }); });
    var byId = function (p) { var P = plays[p.team] || []; var hasId = P.length && 'pid' in P[0]; return P.filter(function (q) { return hasId && q.pid ? q.pid === p.id : q.pl === p.short; }); };
    var isT = function (q) { return q.k === 'comp' || q.k === 'inc' || q.k === 'int'; };
    var sets = C.players.map(function (p) {
      if (C.group === 'qb') { var P = plays[p.team] || []; var hasQb = P.length && 'qb' in P[0]; return P.filter(function (q) { return q.pt === 'pass' && q.k !== 'sack' && q.k !== 'scramble' && (!hasQb || !q.qb || q.qb === p.id); }); }
      if (C.group === 'rb') return byId(p).filter(function (q) { return q.k === 'run'; });
      return byId(p).filter(isT);
    });
    var airs = [].concat.apply([], sets.map(function (s) { return s.filter(function (q) { return q.air != null; }).map(function (q) { return q.air; }); }));
    var minA = Math.min(-10, Math.floor(Math.min.apply(null, airs.concat([0])) / 5) * 5), maxA = Math.max.apply(null, [40].concat(airs));
    $('#dbd-cmapn').textContent = sets.map(function (s, i) { return C.players[i].short + ' ' + s.length; }).join(' · ');
    $('#dbd-cmapleg').innerHTML = C.group === 'rb' ? '<span><i style="background:' + COL.td + '"></i>Positive EPA</span><span><i style="background:' + COL.run + '"></i>Negative EPA, 40%+ success</span><span><i style="background:' + COL.bad + '"></i>Under 40% success</span>' : '<span><i style="background:' + COL.pass + '"></i>Catch</span><span><i style="background:' + COL.inc + '"></i>Incomplete</span><span><i style="background:' + COL.bad + '"></i>Interception</span><span><i style="background:' + COL.td + '"></i>Touchdown</span>';
    sets.forEach(function (s, i) { $('#dbd-cmapc-' + i).textContent = '· ' + s.length + (C.group === 'rb' ? ' carries' : C.group === 'qb' ? ' throws' : ' targets'); $('#dbd-cmap-' + i).innerHTML = C.group === 'rb' ? runmap(s) : passmap(s.filter(function (q) { return q.air != null; }), minA, maxA); });

    /* targets (or throws) per player for the one-field, butterfly and heat graphics */
    var tsets = C.players.map(function (p) { return (C.group === 'qb' ? sets[C.players.indexOf(p)] : byId(p).filter(isT)).filter(function (q) { return q.air != null; }); });
    var SLOT = ['#161e36', '#cb2026', '#d99a06'];

    /* one field */
    (function () {
      var host = $('#dbd-cfield'); if (!host) return;
      var all = [].concat.apply([], tsets), Wd = 360, H = 380, Tp = 36, Bt = 28, Lf = 34, Rt = 10;
      if (!all.length) { host.innerHTML = '<p class="dbd-note">No targets in the play files yet.</p>'; return; }
      var airs2 = all.map(function (q) { return q.air; }), mn = Math.min(-10, Math.floor(Math.min.apply(null, airs2) / 5) * 5), mx = Math.max.apply(null, [40].concat(airs2));
      var y = function (a) { return Tp + (mx - a) / (mx - mn) * (H - Tp - Bt); }, lw = (Wd - Lf - Rt) / 3, lanes = { left: 0, middle: 1, right: 2 };
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Targets on one field">';
      for (var a = -10; a <= mx; a += 10) g += '<line x1="' + Lf + '" x2="' + (Wd - Rt) + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '" stroke-width="' + (a === 0 ? 2 : 1) + '"/><text x="' + (Lf - 6) + '" y="' + (y(a) + 4) + '" fill="' + FI + '" font-size="10" text-anchor="end">' + a + '</text>';
      ['Left', 'Middle', 'Right'].forEach(function (nm, i) { g += '<text x="' + (Lf + lw * i + lw / 2) + '" y="' + (H - 9) + '" fill="' + FI + '" font-size="11" text-anchor="middle">' + nm + '</text>'; if (i) g += '<line x1="' + (Lf + lw * i) + '" x2="' + (Lf + lw * i) + '" y1="' + Tp + '" y2="' + (H - Bt) + '" stroke="' + FL + '" stroke-dasharray="2 4"/>'; });
      tsets.forEach(function (set, pi) { set.forEach(function (q) { var li = lanes[q.loc] == null ? 1 : lanes[q.loc]; g += '<circle cx="' + (Lf + lw * li + lw * (.12 + .76 * hash(q.i + 1))) + '" cy="' + y(q.air) + '" r="' + (q.k === 'comp' ? 6 : 5) + '" fill="' + (q.k === 'comp' ? SLOT[pi] : 'none') + '" stroke="' + SLOT[pi] + '" stroke-width="2" fill-opacity=".9"><title>' + esc(q.desc) + '</title></circle>'; }); });
      g += '</svg>';
      var lane = function (set, l) { return set.filter(function (q) { return (q.loc || 'middle') === l; }).length; };
      var side = ['left', 'middle', 'right'].map(function (l) { var counts = tsets.map(function (set) { return lane(set, l); }), tot = counts.reduce(function (x, y) { return x + y; }, 0) || 1; return '<div class="lane"><span class="l">' + l + '</span><span class="b">' + counts.map(function (c, i) { return '<i style="width:' + (c / tot * 100) + '%;background:' + SLOT[i] + '"></i>'; }).join('') + '</span><span class="n">' + counts.join(' · ') + '</span></div>'; }).join('');
      host.innerHTML = g + '<div class="dbd-clanes">' + side + '</div>';
    })();

    /* depth butterfly, first two slots */
    (function () {
      var host = $('#dbd-cbfly'); if (!host) return;
      if (tsets.length < 2) { host.innerHTML = '<p class="dbd-note">Add a second player for the butterfly.</p>'; return; }
      var A = tsets[0], B = tsets[1], bands = [['Deep 20+', function (q) { return q.air >= 20; }], ['Medium 10-19', function (q) { return q.air >= 10 && q.air < 20; }], ['Short 0-9', function (q) { return q.air >= 0 && q.air < 10; }], ['Behind', function (q) { return q.air < 0; }]];
      var mxb = Math.max.apply(null, [1].concat(bands.map(function (b) { return Math.max(A.filter(b[1]).length, B.filter(b[1]).length); })));
      var h = '<div class="dbd-cbf"><div class="hd"><i style="background:' + SLOT[0] + '"></i>' + esc(C.players[0].short) + '</div><div></div><div class="hd r">' + esc(C.players[1].short) + '<i style="background:' + SLOT[1] + '"></i></div>';
      bands.forEach(function (b) { var a = A.filter(b[1]), bb = B.filter(b[1]), ac = a.filter(function (q) { return q.k === 'comp'; }).length, bc = bb.filter(function (q) { return q.k === 'comp'; }).length;
        h += '<div class="bar left" style="--c:' + SLOT[0] + '"><i style="width:' + (a.length / mxb * 100) + '%"></i><b style="width:' + (ac / mxb * 100) + '%"></b><span>' + ac + '/' + a.length + '</span></div><div class="lab">' + b[0] + '</div><div class="bar right" style="--c:' + SLOT[1] + '"><i style="width:' + (bb.length / mxb * 100) + '%"></i><b style="width:' + (bc / mxb * 100) + '%"></b><span>' + bc + '/' + bb.length + '</span></div>'; });
      host.innerHTML = h + '</div>';
    })();

    /* where he wins: lanes by depth, EPA per target, same color scale for everyone */
    (function () {
      var bands = [['Deep', function (q) { return q.air >= 20; }], ['Mid', function (q) { return q.air >= 10 && q.air < 20; }], ['Short', function (q) { return q.air < 10; }]], lanes3 = ['left', 'middle', 'right'];
      tsets.forEach(function (T, pi) { var host = $('#dbd-cheat-' + pi); if (!host) return;
        var g = '<div></div>' + lanes3.map(function (l) { return '<div class="h">' + l + '</div>'; }).join('');
        bands.forEach(function (b) { g += '<div class="h l">' + b[0] + '</div>' + lanes3.map(function (l) { var a = T.filter(function (q) { return b[1](q) && (q.loc || 'middle') === l; }); if (a.length < 2) return '<div class="c few"><b>' + (a.length || '·') + '</b><span>&nbsp;</span></div>'; var e = a.reduce(function (s2, q) { return s2 + (q.epa || 0); }, 0) / a.length, al = Math.min(.75, Math.abs(e) / 1.2 + .12); return '<div class="c" style="background:' + (e >= 0 ? 'rgba(30,127,74,' + al + ')' : 'rgba(180,38,31,' + al + ')') + '"><b>' + (e > 0 ? '+' : '') + e.toFixed(2) + '</b><span>' + a.length + ' tgt</span></div>'; }).join(''); });
        host.innerHTML = g;
      });
    })();
  }).catch(function (err) { $('#dbd-cmapn').textContent = 'could not load the plays'; root.querySelectorAll('.dbd-cmaps .dbd-skel').forEach(function (k) { k.innerHTML = '<span class="dbd-note">' + esc(err.message || err) + '</span>'; }); });

  function hash(i) { return (i * 2654435761 % 4294967296) / 4294967296; }
  function passmap(D, minA, maxA) {
    var Wd = 360, H = 420, Tp = 40, B = 30, Lf = 34, R = 10;
    var y = function (a) { return Tp + (maxA - a) / (maxA - minA) * (H - Tp - B); }, lw = (Wd - Lf - R) / 3, lanes = { left: 0, middle: 1, right: 2 };
    var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Target map">';
    for (var a = -10; a <= maxA; a += 10) g += '<line x1="' + Lf + '" x2="' + (Wd - R) + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '" stroke-width="' + (a === 0 ? 2 : 1) + '"/><text x="' + (Lf - 6) + '" y="' + (y(a) + 4) + '" fill="' + FI + '" font-size="10" text-anchor="end">' + a + '</text>';
    ['Left', 'Middle', 'Right'].forEach(function (n, i) { g += '<text x="' + (Lf + lw * i + lw / 2) + '" y="' + (H - 10) + '" fill="' + FI + '" font-size="11" text-anchor="middle">' + n + '</text>'; if (i) g += '<line x1="' + (Lf + lw * i) + '" x2="' + (Lf + lw * i) + '" y1="' + Tp + '" y2="' + (H - B) + '" stroke="' + FL + '" stroke-dasharray="2 4"/>'; });
    var byLane = { left: [], middle: [], right: [] };
    D.forEach(function (q) { var li = lanes[q.loc] == null ? 1 : lanes[q.loc]; (byLane[q.loc] || byLane.middle).push(q); var c = q.td ? COL.td : q.k === 'int' ? COL.bad : q.k === 'inc' ? COL.inc : COL.pass; g += '<circle cx="' + (Lf + lw * li + lw * (.12 + .76 * hash(q.i + 1))) + '" cy="' + y(q.air) + '" r="' + (q.td ? 7 : 5.5) + '" fill="' + c + '" fill-opacity=".9" stroke="rgba(0,0,0,.35)"><title>' + esc(q.desc) + '</title></circle>'; });
    Object.keys(byLane).forEach(function (k) { var arr = byLane[k]; if (!arr.length) return; var c = arr.filter(function (q) { return q.k === 'comp'; }).length; g += '<text x="' + (Lf + lw * lanes[k] + lw / 2) + '" y="' + (Tp - 24) + '" fill="#fff" font-size="11" font-weight="600" text-anchor="middle">' + c + '/' + arr.length + ' · ' + Math.round(c / arr.length * 100) + '%</text>'; });
    return g + '</svg>';
  }
  function runmap(R) {
    var cols = [['left', 'end', 'L End'], ['left', 'tackle', 'L Tackle'], ['left', 'guard', 'L Guard'], ['middle', null, 'Middle'], ['right', 'guard', 'R Guard'], ['right', 'tackle', 'R Tackle'], ['right', 'end', 'R End']];
    var data = cols.map(function (c) { var arr = R.filter(function (q) { return q.loc === c[0] && (c[0] === 'middle' || q.gap === c[1]); }); var n = arr.length; return { n: c[2], c: n, ypc: n ? arr.reduce(function (s, q) { return s + (q.yds || 0); }, 0) / n : 0, sr: n ? arr.filter(function (q) { return q.succ; }).length / n : 0, epa: n ? arr.reduce(function (s, q) { return s + (q.epa || 0); }, 0) / n : 0 }; });
    var Wd = 360, H = 420, Tp = 50, B = 70, Lf = 10, Rr = 10, maxC = Math.max.apply(null, [1].concat(data.map(function (d) { return d.c; }))), cw = (Wd - Lf - Rr) / 7;
    var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Run gap chart"><text x="' + (Wd / 2) + '" y="18" fill="' + FI + '" font-size="10" text-anchor="middle">bar height = carries · label = yards per carry</text>';
    data.forEach(function (d, i) { var bh = (H - Tp - B) * d.c / maxC, bx = Lf + cw * i + 5, by = H - B - bh, fill = d.c ? (d.epa >= 0 ? COL.td : d.sr >= .4 ? COL.run : COL.bad) : 'transparent'; g += '<rect x="' + bx + '" y="' + by + '" width="' + (cw - 10) + '" height="' + bh + '" rx="2" fill="' + fill + '" fill-opacity=".85"/>'; if (d.c) g += '<text x="' + (bx + (cw - 10) / 2) + '" y="' + (by - 6) + '" fill="#fff" font-size="12" font-weight="600" text-anchor="middle">' + f1(d.ypc) + '</text>'; var parts = d.n.split(' '); g += '<text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 16) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + parts[0] + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 28) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + (parts[1] || '') + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 46) + '" fill="#fff" font-size="11" text-anchor="middle">' + d.c + '</text>'; });
    return g + '</svg>';
  }
})();
