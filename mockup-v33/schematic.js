    /* SCOPE §59 mock: the pass and run maps drawn like a playbook page. Chalk on the field: the line as O's (the center a square), the
       quarterback and the back, passes as routes from the quarterback to where the ball went, runs as arrows through the gaps. */
    var CHK = 'rgba(255,255,255,.92)', CHK2 = 'rgba(255,255,255,.55)';
    function tint(epa, sr) { return epa >= 0 ? COL.td : sr >= .4 ? COL.run : COL.bad; }
    function markers(pre) { return '<defs>' + [['g', COL.td], ['y', COL.run], ['r', COL.bad], ['w', CHK]].map(function (m) { return '<marker id="' + pre + m[0] + '" viewBox="0 0 10 10" refX="7" refY="5" markerWidth="3.2" markerHeight="3.2" orient="auto-start-reverse"><path d="M0,0 L10,5 L0,10 z" fill="' + m[1] + '"/></marker>'; }).join('') + '</defs>'; }
    function mk(c) { return c === COL.td ? 'g' : c === COL.run ? 'y' : c === COL.bad ? 'r' : 'w'; }
    function line5(cx, los, gap) {  /* the five linemen, the center a square, plus the ends drawn as open O's */
      var s = '', xs = [-2, -1, 0, 1, 2];
      xs.forEach(function (k) { var x0 = cx + k * gap; s += k === 0 ? '<rect x="' + (x0 - 9) + '" y="' + (los + 4) + '" width="18" height="18" fill="none" stroke="' + CHK + '" stroke-width="2"/>' : '<circle cx="' + x0 + '" cy="' + (los + 13) + '" r="9" fill="none" stroke="' + CHK + '" stroke-width="2"/>'; });
      return s;
    }
    function passmap(F) {
      var D = F.filter(function (p) { return ['comp', 'inc', 'int'].indexOf(p.k) >= 0 && p.air != null; });
      var Wd = 360, H = 440, Tp = 44, LOS = 330, maxA = Math.max.apply(null, [40].concat(D.map(function (p) { return p.air; }))), ppy = (LOS - Tp) / maxA;
      var y = function (a) { return LOS - a * ppy; }, LX = { left: 62, middle: 180, right: 298 }, QB = { x: 180, y: LOS + 44 };
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Pass routes by zone">' + markers('pm');
      for (var a = 5; a <= maxA; a += 5) g += '<line x1="0" x2="' + Wd + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '" stroke-width="' + (a % 10 ? .6 : 1) + '"/>' + (a % 10 ? '' : '<text x="8" y="' + (y(a) - 3) + '" fill="' + FI + '" font-size="10">' + a + '</text>');
      for (a = 1; a <= maxA; a++) g += '<line x1="120" x2="126" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '"/><line x1="234" x2="240" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '"/>';  /* the hash marks */
      g += '<line x1="0" x2="' + Wd + '" y1="' + LOS + '" y2="' + LOS + '" stroke="' + CHK + '" stroke-width="2"/><text x="8" y="' + (LOS - 4) + '" fill="#fff" font-size="10" font-weight="600">LOS</text>';
      /* every target, faint, so a click still opens the play */
      D.forEach(function (p) { var cx = LX[p.loc] != null ? LX[p.loc] : 180, jx = cx + (hash(p.i + 1) - .5) * 92, cy = y(Math.max(-6, p.air)); g += '<g class="dbd-play' + (st.sel === p.i ? ' dbd-sel' : '') + '" data-i="' + p.i + '"><circle cx="' + jx + '" cy="' + cy + '" r="' + (st.sel === p.i ? 6 : 3) + '" fill="' + color(p) + '" fill-opacity="' + (st.sel === p.i ? 1 : .35) + '"' + (st.sel === p.i ? ' stroke="#fff" stroke-width="2"' : '') + '/></g>'; });
      /* the zones: three sides by four depths, each a route from the quarterback, as thick as its share of the throws */
      var bands = [['behind', -99, 0], ['short', 0, 10], ['mid', 10, 20], ['deep', 20, 999]], Z = [];
      ['left', 'middle', 'right'].forEach(function (side) { bands.forEach(function (b) { var arr = D.filter(function (p) { return (p.loc || 'middle') === side && p.air >= b[1] && p.air < b[2]; }); if (!arr.length) return; var c = arr.filter(function (p) { return p.k === 'comp'; }).length, e = arr.reduce(function (s, p) { return s + (p.epa || 0); }, 0) / arr.length, sr = arr.filter(function (p) { return p.succ; }).length / arr.length, av = arr.reduce(function (s, p) { return s + p.air; }, 0) / arr.length; Z.push({ side: side, band: b[0], n: arr.length, c: c, epa: e, sr: sr, air: av }); }); });
      var maxN = Math.max.apply(null, [1].concat(Z.map(function (z) { return z.n; })));
      Z.sort(function (a, b) { return a.n - b.n; }).forEach(function (z) {
        var dxs = z.side === 'middle' ? { behind: 0, short: -30, mid: 30, deep: 0 } : { behind: 0, short: 16, mid: 0, deep: -16 }, tx = LX[z.side] + (z.side === 'right' ? -1 : 1) * (dxs[z.band] || 0) * (z.side === 'middle' ? 1 : 1), ty = y(Math.max(-4, z.air)), col = tint(z.epa, z.sr), w = 1.6 + 6 * z.n / maxN, path;
        if (z.band === 'behind') path = 'M' + QB.x + ',' + QB.y + ' Q' + ((QB.x + tx) / 2) + ',' + (QB.y + 18) + ' ' + tx + ',' + (LOS + 14);  /* a swing out to the flat */
        else { var sx = QB.x + (tx - QB.x) * .3, sy = LOS - Math.min(30, (LOS - ty) * .35); path = 'M' + QB.x + ',' + (QB.y - 8) + ' L' + sx + ',' + sy + ' L' + tx + ',' + ty; }  /* a stem, then the break */
        g += '<path d="' + path + '" fill="none" stroke="' + col + '" stroke-width="' + w.toFixed(1) + '" stroke-linecap="round" stroke-linejoin="round" marker-end="url(#pm' + mk(col) + ')" opacity=".95"/>';
        var ly = z.band === 'behind' ? (z.side === 'middle' ? QB.y + 26 : LOS + 52) : ty - 9, lx = z.band === 'behind' ? (z.side === 'left' ? 8 : z.side === 'right' ? Wd - 8 : QB.x) : tx;
        g += '<text x="' + lx + '" y="' + ly + '" fill="#fff" font-size="11" font-weight="700" text-anchor="' + (z.band === 'behind' ? (z.side === 'left' ? 'start' : z.side === 'right' ? 'end' : 'middle') : 'middle') + '" paint-order="stroke" stroke="#2c4a35" stroke-width="3">' + z.c + '/' + z.n + ' · ' + (z.epa > 0 ? '+' : '') + fmt(z.epa, 2) + '</text>';
      });
      g += line5(180, LOS, 24) + '<circle cx="' + QB.x + '" cy="' + QB.y + '" r="9" fill="' + CHK + '"/><text x="' + QB.x + '" y="' + (QB.y + 4) + '" fill="#2c4a35" font-size="10" font-weight="800" text-anchor="middle">QB</text>';
      ['Left', 'Middle', 'Right'].forEach(function (n, i) { var arr = D.filter(function (p) { return (p.loc || 'middle') === ['left', 'middle', 'right'][i]; }), c = arr.filter(function (p) { return p.k === 'comp'; }).length; g += '<text x="' + [62, 180, 298][i] + '" y="18" fill="' + FI + '" font-size="10" text-anchor="middle">' + n.toUpperCase() + '</text>' + (arr.length ? '<text x="' + [62, 180, 298][i] + '" y="33" fill="#fff" font-size="11" font-weight="600" text-anchor="middle">' + c + '/' + arr.length + ' · ' + Math.round(c / arr.length * 100) + '%</text>' : ''); });
      $('#dbd-passmap').innerHTML = g + '</svg>';
    }
    function runmap(F) {
      var R = F.filter(function (p) { return p.pt === 'run' && p.k === 'run'; });
      var cols = [['left', 'end', 'L End', -134], ['left', 'tackle', 'L Tackle', -92], ['left', 'guard', 'L Guard', -50], ['middle', null, 'Middle', 0], ['right', 'guard', 'R Guard', 50], ['right', 'tackle', 'R Tackle', 92], ['right', 'end', 'R End', 134]];
      var data = cols.map(function (c) { var arr = R.filter(function (p) { return p.loc === c[0] && (c[0] === 'middle' || p.gap === c[1]); }); var n = arr.length; return { n: c[2], dx: c[3], c: n, ypc: n ? arr.reduce(function (s, p) { return s + (p.yds || 0); }, 0) / n : 0, sr: n ? arr.filter(function (p) { return p.succ; }).length / n : 0, epa: n ? arr.reduce(function (s, p) { return s + (p.epa || 0); }, 0) / n : 0 }; });
      var Wd = 360, H = 440, CX = 180, LOS = 250, RB = { x: CX, y: LOS + 88 }, ppy = 15, maxC = Math.max.apply(null, [1].concat(data.map(function (d) { return d.c; })));
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Runs by gap">' + markers('rm');
      for (var a = 5; a <= 15; a += 5) g += '<line x1="0" x2="' + Wd + '" y1="' + (LOS - a * ppy) + '" y2="' + (LOS - a * ppy) + '" stroke="' + FL + '"/><text x="8" y="' + (LOS - a * ppy - 3) + '" fill="' + FI + '" font-size="10">' + a + ' yds</text>';
      g += '<text x="' + (Wd / 2) + '" y="18" fill="' + FI + '" font-size="10" text-anchor="middle">arrow length = yards per carry · width = carries</text>';
      g += '<line x1="0" x2="' + Wd + '" y1="' + LOS + '" y2="' + LOS + '" stroke="' + CHK + '" stroke-width="2"/><text x="8" y="' + (LOS - 4) + '" fill="#fff" font-size="10" font-weight="600">LOS</text>';
      g += line5(CX, LOS, 34) + '<circle cx="' + (CX - 3 * 34 - 4) + '" cy="' + (LOS + 13) + '" r="9" fill="none" stroke="' + CHK2 + '" stroke-width="2" stroke-dasharray="3 3"/><circle cx="' + (CX + 3 * 34 + 4) + '" cy="' + (LOS + 13) + '" r="9" fill="none" stroke="' + CHK2 + '" stroke-width="2" stroke-dasharray="3 3"/>';  /* the tight end spots, dashed */
      g += '<circle cx="' + CX + '" cy="' + (LOS + 44) + '" r="9" fill="none" stroke="' + CHK + '" stroke-width="2"/><text x="' + CX + '" y="' + (LOS + 48) + '" fill="' + CHK + '" font-size="9" font-weight="700" text-anchor="middle">QB</text>';
      data.slice().sort(function (a, b) { return a.c - b.c; }).forEach(function (d) {
        if (!d.c) return; var col = tint(d.epa, d.sr), w = 2 + 7 * d.c / maxC, gx = CX + d.dx, top = LOS - Math.max(.6, Math.min(12, d.ypc)) * ppy;
        var path = 'M' + RB.x + ',' + (RB.y - 10) + ' C' + RB.x + ',' + (LOS + 40) + ' ' + gx + ',' + (LOS + 44) + ' ' + gx + ',' + (LOS + 4) + ' L' + gx + ',' + top;
        g += '<path d="' + path + '" fill="none" stroke="' + col + '" stroke-width="' + w.toFixed(1) + '" stroke-linecap="round" marker-end="url(#rm' + mk(col) + ')" opacity=".95"/>';
        g += '<text x="' + gx + '" y="' + (top - 10) + '" fill="#fff" font-size="12" font-weight="700" text-anchor="middle" paint-order="stroke" stroke="#2c4a35" stroke-width="3">' + fmt(d.ypc) + '</text>';
      });
      g += '<circle cx="' + RB.x + '" cy="' + RB.y + '" r="10" fill="' + CHK + '"/><text x="' + RB.x + '" y="' + (RB.y + 4) + '" fill="#2c4a35" font-size="10" font-weight="800" text-anchor="middle">RB</text>';
      data.forEach(function (d) { var gx = CX + d.dx, parts = d.n.split(' '); g += '<text x="' + gx + '" y="' + (H - 46) + '" fill="' + FI + '" font-size="9.5" text-anchor="middle">' + parts[0] + (parts[1] ? ' ' + parts[1].charAt(0) + parts[1].slice(1, 3).toLowerCase() : '') + '</text><text x="' + gx + '" y="' + (H - 30) + '" fill="#fff" font-size="10" font-weight="600" text-anchor="middle">' + d.c + '</text><text x="' + gx + '" y="' + (H - 16) + '" fill="' + FI + '" font-size="9.5" text-anchor="middle">' + (d.c ? Math.round(d.sr * 100) + '%' : '') + '</text>'; });
      g += '<text x="6" y="' + (H - 30) + '" fill="' + FI + '" font-size="9">car</text><text x="6" y="' + (H - 16) + '" fill="' + FI + '" font-size="9">SR</text></svg>';
      $('#dbd-runmap').innerHTML = g + '<div class="dbd-legend" style="padding:8px 0 0"><span><i class="dot" style="background:' + COL.td + '"></i>Positive EPA</span><span><i class="dot" style="background:' + COL.run + '"></i>Negative EPA, 40%+ success</span><span><i class="dot" style="background:' + COL.bad + '"></i>Under 40% success</span></div>';
    }
