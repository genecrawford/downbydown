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
