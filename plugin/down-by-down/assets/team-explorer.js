/* Down by Down: the team play explorer. Fetches the team's play file and draws everything inside #dbd-explorer.
   Config arrives in DBD_TEAM (localized by PHP): abbr, team, teams, season, dataUrl, passrate, tend, through, accent.
   The Tendencies tab draws with Chartist (bundled in assets/vendor). */
(function () {
  var host = document.getElementById('dbd-explorer');
  if (!host || !window.DBD_TEAM) return;
  var C = window.DBD_TEAM, TEAM = C.abbr, T = C.team, TEAMS = C.teams, SEASON = C.season;
  window.DBD_TEAMS = TEAMS;
  try { localStorage.setItem('dbd-last-team', TEAM); } catch (e) {}

  fetch(C.dataUrl, { credentials: 'omit' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
    .then(boot)
    .catch(function (err) { host.innerHTML = '<div class="dbd-empty"><h2>Could not load the plays</h2><p>' + esc(String(err.message || err)) + '. Try again in a minute.</p></div>'; });

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var fmt = function (n, d) { d = d == null ? 1 : d; return n == null || isNaN(n) ? '-' : (+n).toFixed(d); };
  var sign = function (n) { return (n > 0 ? '+' : '') + fmt(n, 2); };
  var COL = { run: '#f0b429', pass: '#5b9cf6', inc: '#9a96a6', bad: '#e0564b', td: '#4fd18b' };
  var FL = 'rgba(255,255,255,.22)', FI = 'rgba(255,255,255,.75)';

  /* transitions.dev orchestration: 04 text swap, 16 sliding pill */
  function swapText(el, next) { if (!el || el.textContent === next) return; var dur = parseFloat(getComputedStyle(host).getPropertyValue('--text-swap-dur')) || 150; el.classList.add('is-exit'); setTimeout(function () { el.textContent = next; el.classList.remove('is-exit'); el.classList.add('is-enter-start'); void el.offsetHeight; el.classList.remove('is-enter-start'); }, dur); }
  function slidePill(bar, tab, animate) { var pill = bar.querySelector('.t-tabs-pill'); if (!pill || !tab) return; var go = function () { pill.style.transform = 'translateX(' + tab.offsetLeft + 'px)'; pill.style.width = tab.offsetWidth + 'px'; }; if (animate) { go(); return; } var prev = pill.style.transition; pill.style.transition = 'none'; go(); void pill.offsetWidth; pill.style.transition = prev; }

  function boot(RAW) {
    var K = RAW.keys, P = RAW.plays.map(function (r, i) { var o = { i: i }; K.forEach(function (k, j) { o[k] = r[j]; }); return o; }), G = RAW.games || [];
    var gIndex = {}; G.forEach(function (g) { gIndex[g.id] = g; });
    var W = G.filter(function (g) { return g.pf > g.pa; }).length, L = G.filter(function (g) { return g.pf < g.pa; }).length;
    var lastWeek = G.length ? Math.max.apply(null, G.map(function (g) { return g.week; })) : 0;
    var byes = RAW.byes || [];
    var PASSRATE = C.passrate || 0.57;

    var snaps = document.getElementById('dbd-snaps'); if (snaps) snaps.textContent = P.length + ' snaps';
    var kick = document.getElementById('dbd-kicker'); if (kick && lastWeek) kick.textContent = SEASON + ' season · Weeks 1 to ' + lastWeek + (byes.length ? ' · bye week ' + byes.join(', ') : '') + ' · ' + W + '-' + L;

    host.innerHTML =
      '<div class="dbd-gstrip" id="dbd-games" role="group" aria-label="Games"></div>' +
      '<section class="dbd-panel"><div class="dbd-toolbar">' +
      '<div class="dbd-fg"><span>Down</span><div class="dbd-seg" id="f-down"></div></div>' +
      '<div class="dbd-fg"><span>Play type</span><div class="dbd-seg" id="f-type"></div></div>' +
      '<div class="dbd-fg"><span>Field zone</span><div class="dbd-seg" id="f-zone"></div></div>' +
      '<div class="dbd-fg"><span>Formation</span><div class="dbd-seg" id="f-sg"></div></div>' +
      '<div class="dbd-fg"><label for="f-q">Quarter</label><select id="f-q"><option value="">All</option><option>1</option><option>2</option><option>3</option><option>4</option><option value="5">OT</option></select></div>' +
      '<div class="dbd-fg"><label for="f-pl">Player</label><select id="f-pl"></select></div>' +
      '<button class="dbd-reset" id="dbd-reset" type="button">Reset</button></div></section>' +
      '<div class="dbd-tiles" id="dbd-tiles"></div>' +
      '<div class="dbd-work"><section class="dbd-panel">' +
      '<div class="dbd-ph tabsrow"><div class="t-tabs dbd-tabs" role="tablist" id="dbd-viewtabs"><span class="t-tabs-pill" aria-hidden="true"></span><button class="t-tab" role="tab" id="tab-drive" aria-selected="true" type="button">Drive chart</button><button class="t-tab" role="tab" id="tab-spray" aria-selected="false" type="button">Pass &amp; run map</button><button class="t-tab" role="tab" id="tab-down" aria-selected="false" type="button">Situations</button><button class="t-tab" role="tab" id="tab-charts" aria-selected="false" type="button">Tendencies</button></div></div>' +
      '<div id="view-drive" class="dbd-view t-panel-slide" data-open="true"><div class="dbd-legend"><span><i style="background:' + COL.run + '"></i>Run</span><span><i style="background:' + COL.pass + '"></i>Completion</span><span><i style="background:' + COL.inc + '"></i>Incompletion (air yards)</span><span><i style="background:' + COL.bad + '"></i>Sack / INT</span><span><i style="background:' + COL.td + '"></i>Touchdown</span></div><div class="dbd-scroll" id="dbd-drive"></div><p class="dbd-note">Each row is one snap. ' + esc(T.nick) + ' always move left to right. Rows are grouped by game and drive; a dot marks the line of scrimmage.</p></div>' +
      '<div id="view-spray" class="dbd-view t-panel-slide" data-open="false" hidden><div class="dbd-sprays"><div><h4>Pass targets by depth</h4><div id="dbd-passmap"></div></div><div><h4>Runs by gap</h4><div id="dbd-runmap"></div></div></div><p class="dbd-note">Pass map plots where the ball was thrown (air yards beyond the line of scrimmage, the thick 0 line) and to which side. Sacks and scrambles are left out. Run map shows designed runs by gap.</p></div>' +
      '<div id="view-down" class="dbd-view t-panel-slide" data-open="false" hidden><div id="dbd-tend" class="dbd-tend"></div></div>' +
      '<div id="view-charts" class="dbd-view dbd-chview t-panel-slide" data-open="false" hidden><div class="dbd-chgrid" id="dbd-charts">' +
      '<section class="dbd-chart"><h4>Pass rate by down and distance</h4><p class="dbd-read" id="dbd-read1"></p><div class="dbd-ch dbd-ch-hbar" id="dbd-c1"></div><div class="dbd-legend"><span><i style="background:#5b9cf6"></i>Pass rate</span><span><i style="background:#cb2026"></i>League rate for that spot</span></div></section>' +
      '<section class="dbd-chart"><h4>Shotgun vs under center, by down</h4><p class="dbd-read" id="dbd-read2"></p><div class="dbd-ch" id="dbd-c2"></div><div class="dbd-legend"><span><i style="background:#5b9cf6"></i>Shotgun pass rate</span><span><i style="background:#f0b429"></i>Under center pass rate</span></div></section>' +
      '<section class="dbd-chart"><h4>Cumulative EPA, game by game</h4><p class="dbd-read" id="dbd-read3"></p><div class="dbd-ch" id="dbd-c3"></div><div class="dbd-legend"><span><i style="background:#121417"></i>Cumulative EPA</span><span>Rules mark the start of each game</span></div></section>' +
      '<section class="dbd-chart"><h4>Who gets the ball, and how deep</h4><p class="dbd-read" id="dbd-read4"></p><div class="dbd-ch" id="dbd-c4"></div><div class="dbd-legend"><span><i style="background:#5b9cf6"></i>Wide receiver</span><span><i style="background:#121417"></i>Tight end</span><span><i style="background:#f0b429"></i>Running back</span><span>Dot size is targets</span></div></section>' +
      '<section class="dbd-chart wide"><h4>Pass rate by quarter and game state</h4><p class="dbd-read" id="dbd-read5"></p><div class="dbd-heat" id="dbd-c5"></div></section>' +
      '</div></div>' +
      '</section><div class="dbd-rail">' +
      '<section class="dbd-panel dbd-detail"><div class="dbd-ph"><span class="dbd-lab"><b>Play detail</b></span><span class="dbd-badge"><span class="t-text-swap" id="dbd-detailmode">Best by EPA</span></span></div><div class="dbd-pb" id="dbd-detail"></div></section>' +
      '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Who gets the ball</b></span><span class="dbd-badge">Click to filter</span></div><div class="dbd-tw"><table class="dbd-usage" id="dbd-usage"></table></div></section>' +
      '</div></div><div class="dbd-tt" id="dbd-tt" role="tooltip"></div>';

    var st = { games: new Set(), down: '', type: '', zone: '', sg: '', q: '', pl: '', sel: null, selAuto: true, tab: 'drive' };
    var $ = function (s) { return host.querySelector(s); };
    var color = function (p) { return p.td ? COL.td : (p.k === 'sack' || p.k === 'int') ? COL.bad : p.k === 'inc' ? COL.inc : p.pt === 'run' ? COL.run : COL.pass; };
    var label = function (p) { return { run: 'Run', comp: 'Completion', inc: 'Incomplete', int: 'Interception', sack: 'Sack', scramble: 'Scramble' }[p.k] || p.k; };
    var ord = function (n) { return ['', '1st', '2nd', '3rd', '4th'][n] || n; };
    var yardText = function (yl) { return yl == 50 ? '50' : yl > 50 ? TEAM + ' ' + (100 - yl) : 'OPP ' + yl; };

    /* filters as sliding segmented controls */
    function seg(id, key, opts) {
      var el = $(id); el.classList.add('t-tabs'); el.setAttribute('role', 'tablist');
      el.innerHTML = '<span class="t-tabs-pill" aria-hidden="true"></span>' + opts.map(function (o) { return '<button class="t-tab" type="button" role="tab" data-v="' + o[0] + '" aria-selected="' + (st[key] === o[0]) + '">' + o[1] + '</button>'; }).join('');
      el.onclick = function (e) { var b = e.target.closest('button'); if (!b) return; st[key] = b.dataset.v; el.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); slidePill(el, b, true); render(); };
      requestAnimationFrame(function () { slidePill(el, el.querySelector('[aria-selected="true"]'), false); });
    }
    var SEGS = [['#f-down', 'down', [['', 'All'], ['1', '1st'], ['2', '2nd'], ['3', '3rd'], ['4', '4th']]], ['#f-type', 'type', [['', 'All'], ['pass', 'Pass'], ['run', 'Run']]], ['#f-zone', 'zone', [['', 'All'], ['own', 'Own half'], ['opp', 'Opp half'], ['rz', 'Red zone']]], ['#f-sg', 'sg', [['', 'All'], ['1', 'Shotgun'], ['0', 'Under center']]]];
    SEGS.forEach(function (a) { seg(a[0], a[1], a[2]); });
    window.addEventListener('resize', function () { host.querySelectorAll('.t-tabs').forEach(function (b) { slidePill(b, b.querySelector('[aria-selected="true"]'), false); }); });

    function drawGames() {
      var all = st.games.size === 0;
      $('#dbd-games').innerHTML = '<button class="dbd-game" type="button" data-g="" aria-pressed="' + all + '"><span class="wk">SEASON</span><span class="opp">All games</span><span class="res">' + G.length + ' games · ' + W + '-' + L + '</span></button>' +
        G.map(function (g) { return '<button class="dbd-game" type="button" data-g="' + g.id + '" aria-pressed="' + st.games.has(g.id) + '"><span class="wk">WK ' + g.week + ' ' + (g.home ? 'vs' : 'at') + '</span><span class="opp">' + window.dbdMark(g.opp, 22) + g.opp + '</span><span class="res"><b class="' + (g.pf > g.pa ? '' : 'l') + '">' + (g.pf > g.pa ? 'W' : g.pf < g.pa ? 'L' : 'T') + '</b> ' + g.pf + '-' + g.pa + '</span></button>'; }).join('');
    }
    $('#dbd-games').onclick = function (e) { var b = e.target.closest('.dbd-game'); if (!b) return; var g = b.dataset.g; if (!g) st.games.clear(); else st.games.has(g) ? st.games.delete(g) : st.games.add(g); if (st.games.size === G.length) st.games.clear(); render(); };
    $('#f-q').onchange = function (e) { st.q = e.target.value; render(); };
    $('#f-pl').onchange = function (e) { st.pl = e.target.value; render(); };
    $('#dbd-reset').onclick = function () { Object.assign(st, { games: new Set(), down: '', type: '', zone: '', sg: '', q: '', pl: '' }); SEGS.forEach(function (a) { var el = $(a[0]); el.querySelectorAll('button').forEach(function (x) { x.setAttribute('aria-selected', x.dataset.v === '' ? 'true' : 'false'); }); slidePill(el, el.querySelector('[data-v=""]'), true); }); $('#f-q').value = ''; render(); };

    var counts = {}; P.forEach(function (p) { if (p.pl) counts[p.pl] = (counts[p.pl] || 0) + 1; });
    var players = Object.keys(counts).map(function (n) { return [n, counts[n]]; }).sort(function (a, b) { return b[1] - a[1]; });
    $('#f-pl').innerHTML = '<option value="">All players</option>' + players.map(function (pc) { return '<option value="' + esc(pc[0]) + '">' + esc(pc[0]) + ' (' + pc[1] + ')</option>'; }).join('');

    /* view tabs: sliding pill + panel reveal */
    var VIEWS = ['drive', 'spray', 'down', 'charts'], vt = $('#dbd-viewtabs');
    VIEWS.forEach(function (t) {
      $('#tab-' + t).onclick = function () {
        if (st.tab === t) return; var prev = st.tab; st.tab = t;
        VIEWS.forEach(function (x) { $('#tab-' + x).setAttribute('aria-selected', x === t); });
        slidePill(vt, $('#tab-' + t), true);
        var closeDur = parseFloat(getComputedStyle(host).getPropertyValue('--panel-close-dur')) || 350;
        var o = $('#view-' + prev), n = $('#view-' + t);
        o.dataset.open = 'false'; setTimeout(function () { o.hidden = true; }, closeDur);
        n.hidden = false; requestAnimationFrame(function () { n.dataset.open = 'true'; });
        if (t === 'charts' && chartsDirty) charts(chartsDirty);
      };
    });
    requestAnimationFrame(function () { slidePill(vt, $('#tab-drive'), false); });

    function filtered() {
      return P.filter(function (p) {
        return (st.games.size === 0 || st.games.has(p.g)) && (!st.down || p.dn == +st.down) && (!st.type || p.pt === st.type) &&
          (!st.zone || (st.zone === 'own' && p.yl > 50) || (st.zone === 'opp' && p.yl <= 50) || (st.zone === 'rz' && p.yl <= 20)) &&
          (st.sg === '' || p.sg == +st.sg) && (!st.q || p.q == +st.q) && (!st.pl || p.pl === st.pl);
      });
    }

    /* tiles with number pop-in for values that changed */
    function tiles(F) {
      var n = F.length, pass = F.filter(function (p) { return p.pt === 'pass'; }).length;
      var epa = F.reduce(function (a, p) { return a + (p.epa || 0); }, 0) / (n || 1), succ = F.filter(function (p) { return p.succ; }).length / (n || 1);
      var yds = F.reduce(function (a, p) { return a + (p.yds || 0); }, 0), tds = F.filter(function (p) { return p.td; }).length, fd = F.filter(function (p) { return p.fd; }).length;
      var expl = F.filter(function (p) { return (p.pt === 'pass' && p.yds >= 20) || (p.pt === 'run' && p.yds >= 10); }).length;
      var t = [['Plays', String(n), pass + ' pass · ' + (n - pass) + ' run', ''], ['Pass rate', n ? Math.round(pass / n * 100) + '%' : '-', 'league ' + Math.round(PASSRATE * 100) + '%', ''], ['EPA / play', n ? sign(epa) : '-', 'above 0 beats expectation', epa >= 0 ? 'pos' : 'neg'], ['Success rate', n ? Math.round(succ * 100) + '%' : '-', 'good offenses top 45%', ''], ['Yards / play', n ? fmt(yds / n) : '-', yds + ' total yards', ''], ['Explosive', String(expl), '20+ pass · 10+ run', ''], ['1st downs / TDs', fd + ' / ' + tds, 'by play', '']];
      var box = $('#dbd-tiles');
      if (!box.children.length) box.innerHTML = t.map(function (x) { return '<div class="dbd-tile"><div class="l">' + x[0] + '</div><div class="v"><span class="t-digit-group"></span></div><div class="s"></div></div>'; }).join('');
      Array.prototype.forEach.call(box.children, function (tile, i) {
        var v = t[i][1], sub = t[i][2], cls = t[i][3], g = tile.querySelector('.t-digit-group');
        tile.querySelector('.s').textContent = sub; g.className = 't-digit-group ' + cls;
        if (g.dataset.v === v) return; g.dataset.v = v;
        g.classList.remove('is-animating'); g.replaceChildren();
        var chars = v.split(''); chars.forEach(function (ch, j) { var sp = document.createElement('span'); sp.className = 't-digit'; sp.textContent = ch; if (j === chars.length - 2) sp.dataset.stagger = '1'; else if (j === chars.length - 1) sp.dataset.stagger = '2'; g.appendChild(sp); });
        void g.offsetHeight; g.classList.add('is-animating');
      });
    }

    function drive(F) {
      var Wd = 760, EZ = 40, FW = Wd - EZ * 2, s = FW / 100, RH = 9, HDR = 24, DH = 8, y = 8, rows = [], lastG = null, lastD = null;
      F.forEach(function (p) { if (p.g !== lastG) { if (lastG) y += 10; rows.push({ hdr: gIndex[p.g], y: y }); y += HDR; lastG = p.g; lastD = null; } if (p.drv !== lastD) { if (lastD != null) y += DH; lastD = p.drv; } rows.push({ p: p, y: y }); y += RH; });
      var H = Math.max(y + 12, 180), x = function (yd) { return EZ + yd * s; };
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;min-width:560px" role="img" aria-label="Drive chart of ' + F.length + ' plays">';
      g += '<rect x="0" y="0" width="' + EZ + '" height="' + H + '" fill="' + (C.accent || '#121417') + '" fill-opacity=".35"/><rect x="' + (Wd - EZ) + '" y="0" width="' + EZ + '" height="' + H + '" fill="rgba(0,0,0,.18)"/>';
      for (var i = 0; i <= 100; i += 10) g += '<line x1="' + x(i) + '" x2="' + x(i) + '" y1="0" y2="' + H + '" stroke="' + FL + '" stroke-width="' + (i === 50 ? 2 : 1) + '"/>';
      for (i = 10; i < 100; i += 10) { var lab = i <= 50 ? i : 100 - i; g += '<text x="' + x(i) + '" y="' + (H - 4) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + lab + '</text>'; }
      g += '<text x="' + (EZ / 2) + '" y="' + (H / 2) + '" fill="' + FI + '" font-size="11" text-anchor="middle" transform="rotate(-90 ' + (EZ / 2) + ' ' + (H / 2) + ')">' + TEAM + ' GOAL</text><text x="' + (Wd - EZ / 2) + '" y="' + (H / 2) + '" fill="' + FI + '" font-size="11" text-anchor="middle" transform="rotate(90 ' + (Wd - EZ / 2) + ' ' + (H / 2) + ')">OPPONENT END ZONE</text>';
      rows.forEach(function (r) {
        if (r.hdr) { var h = r.hdr; g += '<text x="' + (EZ + 6) + '" y="' + (r.y + 15) + '" fill="#fff" font-size="12" font-weight="600">WEEK ' + h.week + ' ' + (h.home ? 'VS' : 'AT') + ' ' + h.opp + '  ·  ' + (h.pf > h.pa ? 'W' : 'L') + ' ' + h.pf + '-' + h.pa + '</text>'; return; }
        var p = r.p, los = 100 - p.yl, cy = r.y + RH / 2, end = (p.k === 'inc' || p.k === 'int') ? los + (p.air == null ? 0 : p.air) : los + (p.yds == null ? 0 : p.yds);
        end = Math.max(-2, Math.min(102, end));
        var dash = p.k === 'inc' ? ' stroke-dasharray="3 3"' : '', sel = st.sel === p.i ? ' dbd-sel' : '';
        g += '<g class="dbd-play' + sel + '" data-i="' + p.i + '"><rect x="0" y="' + r.y + '" width="' + Wd + '" height="' + RH + '" fill="transparent"/>';
        if (p.k === 'comp' && p.air != null) { var ca = Math.max(-2, Math.min(102, los + p.air)); g += '<line x1="' + x(los) + '" x2="' + x(ca) + '" y1="' + cy + '" y2="' + cy + '" stroke="' + color(p) + '" stroke-width="2" stroke-opacity=".55" stroke-dasharray="2 2"/><line x1="' + x(ca) + '" x2="' + x(end) + '" y1="' + cy + '" y2="' + cy + '" stroke="' + color(p) + '" stroke-width="3" stroke-linecap="round"/>'; }
        else g += '<line x1="' + x(los) + '" x2="' + x(end) + '" y1="' + cy + '" y2="' + cy + '" stroke="' + color(p) + '" stroke-width="3" stroke-linecap="round"' + dash + '/>';
        g += '<circle cx="' + x(los) + '" cy="' + cy + '" r="2.2" fill="#fff"/></g>';
      });
      if (!F.length) g += '<text x="' + (Wd / 2) + '" y="90" fill="#fff" text-anchor="middle" font-size="14">No plays match these filters.</text>';
      $('#dbd-drive').innerHTML = g + '</svg>';
    }

    function hash(i) { return (i * 2654435761 % 4294967296) / 4294967296; }
    function passmap(F) {
      var D = F.filter(function (p) { return ['comp', 'inc', 'int'].indexOf(p.k) >= 0 && p.air != null; });
      var Wd = 360, H = 420, Tp = 40, B = 30, Lf = 34, R = 10, airs = D.map(function (p) { return p.air; });
      var minA = Math.min(-10, Math.floor(Math.min.apply(null, airs.concat([0])) / 5) * 5), maxA = Math.max.apply(null, [40].concat(airs));
      var y = function (a) { return Tp + (maxA - a) / (maxA - minA) * (H - Tp - B); }, lw = (Wd - Lf - R) / 3, lanes = { left: 0, middle: 1, right: 2 };
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Pass target map">';
      for (var a = -10; a <= maxA; a += 10) g += '<line x1="' + Lf + '" x2="' + (Wd - R) + '" y1="' + y(a) + '" y2="' + y(a) + '" stroke="' + FL + '" stroke-width="' + (a === 0 ? 2 : 1) + '"/><text x="' + (Lf - 6) + '" y="' + (y(a) + 4) + '" fill="' + FI + '" font-size="10" text-anchor="end">' + a + '</text>';
      g += '<text x="' + (Lf - 6) + '" y="' + (Tp - 24) + '" fill="' + FI + '" font-size="10" text-anchor="end">air yds</text>';
      ['Left', 'Middle', 'Right'].forEach(function (n, i) { g += '<text x="' + (Lf + lw * i + lw / 2) + '" y="' + (H - 10) + '" fill="' + FI + '" font-size="11" text-anchor="middle">' + n + '</text>'; if (i) g += '<line x1="' + (Lf + lw * i) + '" x2="' + (Lf + lw * i) + '" y1="' + Tp + '" y2="' + (H - B) + '" stroke="' + FL + '" stroke-dasharray="2 4"/>'; });
      var byLane = { left: [], middle: [], right: [] };
      D.forEach(function (p) { var li = lanes[p.loc] == null ? 1 : lanes[p.loc]; (byLane[p.loc] || byLane.middle).push(p); var cx = Lf + lw * li + lw * (.12 + .76 * hash(p.i + 1)), cy = y(p.air); g += '<g class="dbd-play' + (st.sel === p.i ? ' dbd-sel' : '') + '" data-i="' + p.i + '"><circle cx="' + cx + '" cy="' + cy + '" r="' + (st.sel === p.i ? 7 : 5) + '" fill="' + color(p) + '" fill-opacity=".85" stroke="' + (st.sel === p.i ? '#fff' : 'rgba(0,0,0,.35)') + '" stroke-width="' + (st.sel === p.i ? 2 : 1) + '"/></g>'; });
      Object.keys(byLane).forEach(function (k) { var arr = byLane[k]; if (!arr.length) return; var c = arr.filter(function (p) { return p.k === 'comp'; }).length, i = lanes[k]; g += '<text x="' + (Lf + lw * i + lw / 2) + '" y="' + (Tp - 24) + '" fill="#fff" font-size="11" font-weight="600" text-anchor="middle">' + c + '/' + arr.length + ' · ' + Math.round(c / arr.length * 100) + '%</text>'; });
      $('#dbd-passmap').innerHTML = g + '</svg>';
    }
    function runmap(F) {
      var R = F.filter(function (p) { return p.pt === 'run' && p.k === 'run'; });
      var cols = [['left', 'end', 'L End'], ['left', 'tackle', 'L Tackle'], ['left', 'guard', 'L Guard'], ['middle', null, 'Middle'], ['right', 'guard', 'R Guard'], ['right', 'tackle', 'R Tackle'], ['right', 'end', 'R End']];
      var data = cols.map(function (c) { var arr = R.filter(function (p) { return p.loc === c[0] && (c[0] === 'middle' || p.gap === c[1]); }); var n = arr.length; return { n: c[2], c: n, ypc: n ? arr.reduce(function (s, p) { return s + (p.yds || 0); }, 0) / n : 0, sr: n ? arr.filter(function (p) { return p.succ; }).length / n : 0, epa: n ? arr.reduce(function (s, p) { return s + (p.epa || 0); }, 0) / n : 0 }; });
      var Wd = 360, H = 420, Tp = 50, B = 70, Lf = 10, Rr = 10, maxC = Math.max.apply(null, [1].concat(data.map(function (d) { return d.c; }))), cw = (Wd - Lf - Rr) / 7;
      var g = '<svg viewBox="0 0 ' + Wd + ' ' + H + '" width="100%" style="display:block;background:#2c4a35;border-radius:4px" role="img" aria-label="Run gap chart"><text x="' + (Wd / 2) + '" y="18" fill="' + FI + '" font-size="10" text-anchor="middle">bar height = carries · label = yards per carry</text>';
      data.forEach(function (d, i) { var bh = (H - Tp - B) * d.c / maxC, bx = Lf + cw * i + 5, by = H - B - bh, fill = d.c ? (d.epa >= 0 ? COL.td : d.sr >= .4 ? COL.run : COL.bad) : 'transparent'; g += '<rect x="' + bx + '" y="' + by + '" width="' + (cw - 10) + '" height="' + bh + '" rx="2" fill="' + fill + '" fill-opacity=".85"/>'; if (d.c) g += '<text x="' + (bx + (cw - 10) / 2) + '" y="' + (by - 6) + '" fill="#fff" font-size="12" font-weight="600" text-anchor="middle">' + fmt(d.ypc) + '</text>'; var parts = d.n.split(' '); g += '<text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 16) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + parts[0] + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 28) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + (parts[1] || '') + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 46) + '" fill="#fff" font-size="11" text-anchor="middle">' + d.c + '</text><text x="' + (bx + (cw - 10) / 2) + '" y="' + (H - B + 60) + '" fill="' + FI + '" font-size="10" text-anchor="middle">' + (d.c ? Math.round(d.sr * 100) + '%' : '') + '</text>'; });
      g += '<text x="4" y="' + (H - B + 46) + '" fill="' + FI + '" font-size="9">n</text><text x="4" y="' + (H - B + 60) + '" fill="' + FI + '" font-size="9">SR</text></svg>';
      $('#dbd-runmap').innerHTML = g + '<div class="dbd-legend" style="padding:8px 0 0"><span><i style="background:' + COL.td + '"></i>Positive EPA</span><span><i style="background:' + COL.run + '"></i>Negative EPA, 40%+ success</span><span><i style="background:' + COL.bad + '"></i>Under 40% success</span></div>';
    }

    var BK = [['1st & 10+', function (p) { return p.dn == 1 && p.togo >= 10; }], ['1st & short', function (p) { return p.dn == 1 && p.togo < 10; }], ['2nd & 7+', function (p) { return p.dn == 2 && p.togo >= 7; }], ['2nd & 3-6', function (p) { return p.dn == 2 && p.togo >= 3 && p.togo < 7; }], ['2nd & 1-2', function (p) { return p.dn == 2 && p.togo < 3; }], ['3rd & 7+', function (p) { return p.dn == 3 && p.togo >= 7; }], ['3rd & 3-6', function (p) { return p.dn == 3 && p.togo >= 3 && p.togo < 7; }], ['3rd & 1-2', function (p) { return p.dn == 3 && p.togo < 3; }], ['4th down', function (p) { return p.dn == 4; }]];
    function tend(F) {
      var B = BK;
      var row = function (n, a, conv) { var ps = a.filter(function (p) { return p.pt === 'pass'; }).length, e = a.reduce(function (s, p) { return s + (p.epa || 0); }, 0); return '<tr><td>' + n + '</td><td>' + a.length + '</td><td><span class="dbd-bar" style="width:' + (a.length ? ps / a.length * 50 : 0) + 'px"></span>' + (a.length ? Math.round(ps / a.length * 100) + '%' : '-') + '</td><td>' + (a.length ? Math.round(a.filter(function (p) { return p.succ; }).length / a.length * 100) + '%' : '-') + '</td><td class="' + (e >= 0 ? 'pos' : 'neg') + '">' + (a.length ? sign(e / a.length) : '-') + '</td>' + (conv !== undefined ? '<td>' + conv + '</td>' : '') + '</tr>'; };
      var dd = B.map(function (b) { var a = F.filter(b[1]); return row(b[0], a, (b[0].indexOf('3rd') === 0 || b[0].indexOf('4th') === 0) && a.length ? Math.round(a.filter(function (p) { return p.fd || p.td; }).length / a.length * 100) + '%' : ''); }).join('');
      var qs = [1, 2, 3, 4].map(function (q) { return row('Q' + q, F.filter(function (p) { return p.q == q; })); }).join('');
      var sit = [['Leading', function (p) { return p.sd > 0; }], ['Tied', function (p) { return p.sd == 0; }], ['Trailing', function (p) { return p.sd < 0; }]].map(function (s) { return row(s[0], F.filter(s[1])); }).join('');
      $('#dbd-tend').innerHTML = '<h4>Down and distance</h4><div class="dbd-tw" style="padding:0 14px"><table><tr><th>Situation</th><th>Plays</th><th>Pass rate</th><th>Success</th><th>EPA/play</th><th>Converted</th></tr>' + dd + '</table></div><div class="dbd-sprays"><div><h4>By quarter</h4><table><tr><th>Qtr</th><th>Plays</th><th>Pass</th><th>Success</th><th>EPA</th></tr>' + qs + '</table></div><div><h4>By game score</h4><table><tr><th>When</th><th>Plays</th><th>Pass</th><th>Success</th><th>EPA</th></tr>' + sit + '</table></div></div>';
    }

    /* ---- tendencies charts (Chartist, bundled). Drawn only while the tab is open; the view is display:none
       otherwise and Chartist would measure a zero-width box. Overlays (labels, ticks, rules) are HTML on top of
       the SVG, queued during 'draw' and flushed on 'created' so a resize redraw never doubles them. */
    var CH = {}, chartsDirty = null, TEND = C.tend || null;
    var lgRate = function (kind, a, b) { var v = TEND && TEND[kind] ? (b == null ? TEND[kind][a] : (TEND[kind][a] || {})[b]) : null; return v == null ? PASSRATE : v; };
    var pct = function (a) { return a.length ? a.filter(function (p) { return p.pt === 'pass'; }).length / a.length : 0; }, r0 = function (v) { return Math.round(v * 100); };
    function overlays(el) { var q = []; return { add: function (cls, html, x, y, h) { q.push([cls, html, x, y, h]); }, flush: function () { Array.prototype.slice.call(el.querySelectorAll('.dbd-ch-ov')).forEach(function (x) { x.remove(); }); q.forEach(function (a) { var d = document.createElement('div'); d.className = 'dbd-ch-ov ' + a[0]; d.innerHTML = a[1]; d.style.left = a[2] + 'px'; d.style.top = a[3] + 'px'; if (a[4] != null) d.style.height = a[4] + 'px'; el.appendChild(d); }); q = []; } }; }
    var setRead = function (n, txt) { $('#dbd-read' + n).textContent = txt; };
    var at = function (g) { return 'Week ' + g.week + (g.home ? ' vs ' : ' at ') + g.opp; };
    function charts(F) { chartsDirty = F; if (st.tab !== 'charts') return; chartsDirty = null; if (!window.Chartist) { $('#dbd-charts').innerHTML = '<p class="dbd-note">The chart library did not load. Reload the page to try again.</p>'; return; } drawCharts(F); }
    function drawCharts(F) {
      var nick = T.nick, pr = function (v) { return r0(v) + '%'; };
      Object.keys(CH).forEach(function (k) { CH[k].detach(); delete CH[k]; });
      if (!F.length) { [1, 2, 3, 4, 5].forEach(function (i) { setRead(i, 'No plays match these filters.'); $('#dbd-c' + i).innerHTML = ''; }); return; }

      /* 1. pass rate by down and distance, horizontal bars, a red tick per bar at the league rate for that spot */
      var d1 = BK.slice(0, 8).map(function (b) { return { n: b[0], a: F.filter(b[1]), lg: lgRate('dd', b[0]) }; }).reverse();
      var h1 = $('#dbd-c1'), o1 = overlays(h1);
      CH.c1 = new Chartist.BarChart(h1, { labels: d1.map(function (d) { return d.n; }), series: [d1.map(function (d) { return r0(pct(d.a)); })] }, { horizontalBars: true, axisX: { low: 0, high: 100, onlyInteger: true, labelInterpolationFnc: pr }, axisY: { offset: 84 }, chartPadding: { right: 28, left: 0 } })
        .on('draw', function (d) { if (d.type !== 'bar') return; var v = d.series[d.index], b = d1[d.index]; o1.add('dbd-ch-lbl' + (v > 72 ? ' in' : ''), esc(v + '% · ' + b.a.length + ' plays'), d.x2, d.y2); o1.add('dbd-ch-tick', '', d.chartRect.x1 + d.axisX.projectValue(b.lg * 100), d.y2); })
        .on('created', function () { o1.flush(); });
      var solid = d1.filter(function (d) { return d.a.length >= 10; });
      if (solid.length) { var hi = solid.reduce(function (m, d) { return pct(d.a) > pct(m.a) ? d : m; }), lo = solid.reduce(function (m, d) { return pct(d.a) < pct(m.a) ? d : m; }); setRead(1, 'Most pass-happy on ' + hi.n + ': ' + pr(pct(hi.a)) + ' against a league ' + pr(hi.lg) + '. Most run-heavy on ' + lo.n + ': ' + pr(pct(lo.a)) + ' against ' + pr(lo.lg) + '. Red ticks mark the league rate for each spot.'); }
      else setRead(1, 'Too few plays in this slice to read much into it. Red ticks mark the league rate for each spot.');

      /* 2. shotgun vs under center, grouped by down */
      var downs = [1, 2, 3, 4], byF = function (dn, sg) { return F.filter(function (p) { return p.dn == dn && p.sg == sg; }); };
      var sgv = downs.map(function (dn) { return r0(pct(byF(dn, 1))); }), ucv = downs.map(function (dn) { return r0(pct(byF(dn, 0))); });
      CH.c2 = new Chartist.BarChart($('#dbd-c2'), { labels: ['1st down', '2nd down', '3rd down', '4th down'], series: [sgv, ucv] }, { seriesBarDistance: 16, axisY: { low: 0, high: 100, onlyInteger: true, labelInterpolationFnc: pr }, chartPadding: { right: 10 } });
      var sgShare = r0(F.filter(function (p) { return p.sg == 1; }).length / F.length), uc1 = byF(1, 0).length, uc2 = byF(2, 0).length;
      var r2 = sgShare + '% of these snaps are from shotgun. ';
      r2 += uc1 >= 5 ? 'Under center is where the run game lives: ' + (100 - ucv[0]) + '% runs on first down' + (uc2 >= 5 ? ', ' + (100 - ucv[1]) + '% on second. ' : '. ') : 'Under center snaps are rare in this slice. ';
      if (TEND && TEND.sg) r2 += 'League on first down: ' + pr(lgRate('sg', '1', '1')) + ' pass from shotgun, ' + pr(lgRate('sg', '1', '0')) + ' under center.';
      setRead(2, r2);

      /* 3. cumulative EPA, one season line, a rule at the start of each game */
      var cum = 0, idx = 0, bounds = [], pts = [], perGame = [];
      G.forEach(function (g) { var gp = F.filter(function (p) { return p.g === g.id; }); if (!gp.length) return; bounds.push({ x: idx + 1, g: g }); var v = 0; gp.forEach(function (p) { cum += p.epa || 0; v += p.epa || 0; idx++; pts.push({ x: idx, y: +cum.toFixed(2) }); }); perGame.push({ g: g, v: v }); });
      var h3 = $('#dbd-c3'), o3 = overlays(h3);
      CH.c3 = new Chartist.LineChart(h3, { series: [{ name: 'season', data: pts }] }, { showPoint: false, showArea: true, lineSmooth: Chartist.Interpolation.step(), axisX: { type: Chartist.AutoScaleAxis, onlyInteger: true, low: 0, labelInterpolationFnc: function (v) { return v % 50 === 0 ? v : null; } }, axisY: { labelInterpolationFnc: function (v) { return (v > 0 ? '+' : '') + v; } }, chartPadding: { right: 16, top: 16 } })
        .on('created', function (ctx) { var r = ctx.chartRect; bounds.forEach(function (b) { o3.add('dbd-ch-rule', '<span>Wk ' + b.g.week + ' ' + esc(b.g.opp) + '</span>', r.x1 + ctx.axisX.projectValue(b.x), r.y2, r.height()); }); o3.flush(); });
      var sg3 = function (v) { return (v > 0 ? '+' : '') + v.toFixed(1); }, bestG = perGame.reduce(function (m, e) { return e.v > m.v ? e : m; }), worstG = perGame.reduce(function (m, e) { return e.v < m.v ? e : m; });
      setRead(3, 'EPA runs to ' + sg3(cum) + ' over ' + idx + ' snaps. Best game: ' + at(bestG.g) + ' (' + sg3(bestG.v) + ').' + (perGame.length > 1 ? ' Worst: ' + at(worstG.g) + ' (' + sg3(worstG.v) + ').' : '') + ' Climbs are drives that worked. Flat or falling stretches went nowhere.');

      /* 4. receivers: targets vs average depth of target, dot size = targets, color = position */
      var tg = {}, carries = {};
      F.forEach(function (p) { if (!p.pl) return; if (p.k === 'run') carries[p.pl] = (carries[p.pl] || 0) + 1; if (['comp', 'inc', 'int'].indexOf(p.k) < 0 || p.air == null) return; var t = tg[p.pl] || (tg[p.pl] = { n: p.pl, t: 0, air: 0, pos: null }); t.t++; t.air += p.air; if (p.pos) t.pos = p.pos; });
      var minT = F.length >= 200 ? 5 : 3;
      var recs = Object.keys(tg).map(function (k) { return tg[k]; }).filter(function (t) { return t.t >= minT; }).map(function (t) { var pos = String(t.pos || '').toUpperCase(), cls = pos === 'RB' || pos === 'FB' || pos === 'HB' || (!pos && (carries[t.n] || 0) > t.t) ? 'rb' : pos === 'TE' ? 'te' : 'wr'; return { n: t.n, t: t.t, adot: +(t.air / t.t).toFixed(1), pos: cls }; }).sort(function (a, b) { return b.t - a.t; });
      var h4 = $('#dbd-c4'), o4 = overlays(h4);
      if (recs.length) {
        CH.c4 = new Chartist.LineChart(h4, { series: [{ name: 'recs', data: recs.map(function (r) { return { x: r.t, y: r.adot, meta: r }; }) }] }, { showLine: false, axisX: { type: Chartist.AutoScaleAxis, onlyInteger: true, low: 0 }, axisY: { type: Chartist.AutoScaleAxis, labelInterpolationFnc: function (v) { return v + ' yd'; } }, chartPadding: { right: 84, top: 10 } })
          .on('draw', function (d) { if (d.type !== 'point') return; var r = d.meta; d.element.attr({ style: 'stroke-width:' + Math.max(8, Math.sqrt(r.t) * 4) + 'px' }); d.element.addClass('dbd-pt-' + r.pos); o4.add('dbd-ch-lbl', esc(r.n), d.x, d.y); })
          .on('created', function () { o4.flush(); });
        var deepest = recs.reduce(function (m, r) { return r.adot > m.adot ? r : m; }), busiest = recs[0];
        setRead(4, busiest.n + ' leads with ' + busiest.t + ' targets at ' + busiest.adot + ' air yards each. ' + (deepest.n !== busiest.n ? deepest.n + ' is the deep threat at ' + deepest.adot + '. ' : '') + 'Across is targets, up is average depth of target. Receivers with fewer than ' + minT + ' targets are left off.');
      } else { h4.innerHTML = ''; setRead(4, 'Not enough targets in this slice to plot receivers.'); }

      /* 5. heat grid: quarter by game state, tinted by distance from the league rate in that same spot */
      var states = [['Leading', 'lead', function (p) { return p.sd > 0; }], ['Tied', 'tied', function (p) { return p.sd == 0; }], ['Trailing', 'trail', function (p) { return p.sd < 0; }]];
      var h = '<div class="h l">State</div>' + downs.map(function (q) { return '<div class="h">Q' + q + '</div>'; }).join('');
      states.forEach(function (s) { h += '<div class="h l">' + s[0] + '</div>' + downs.map(function (q) { var a = F.filter(function (p) { return s[2](p) && p.q == q; }); if (a.length < 6) return '<div class="c few"><b>' + a.length + '</b><span>too few</span></div>'; var v = pct(a), lg = lgRate('state', s[1], String(q)), col = v >= lg ? 'var(--dbd-ch-pass)' : 'var(--dbd-ch-run)', mix = Math.min(70, Math.round(Math.abs(v - lg) / 0.3 * 70)); return '<div class="c" style="background:color-mix(in srgb,' + col + ' ' + mix + '%,var(--dbd-panel))"><b>' + pr(v) + '</b><span>' + a.length + ' plays</span></div>'; }).join(''); });
      $('#dbd-c5').innerHTML = h;
      var lead = F.filter(states[0][2]), trail = F.filter(states[2][2]);
      setRead(5, 'Blue means more passing than the league in that spot, amber means more running. Leading, the ' + nick + ' pass ' + (lead.length ? pr(pct(lead)) : '-') + ' of the time. Trailing, ' + (trail.length ? pr(pct(trail)) : '-') + '. Cells with fewer than 6 plays are grayed out.');
    }

    function usage(F) {
      var m = {};
      F.forEach(function (p) { if (!p.pl) return; var u = m[p.pl] || (m[p.pl] = { n: p.pl, tg: 0, rec: 0, car: 0, yds: 0, epa: 0, td: 0, rz: 0 }); if (['comp', 'inc', 'int'].indexOf(p.k) >= 0) { u.tg++; if (p.k === 'comp') u.rec++; } else if (p.k === 'run' || p.k === 'scramble') u.car++; else return; u.yds += p.yds || 0; u.epa += p.epa || 0; u.td += p.td || 0; if (p.yl <= 20) u.rz++; });
      var a = Object.keys(m).map(function (k) { return m[k]; }).sort(function (x, y) { return (y.tg + y.car) - (x.tg + x.car); }).slice(0, 12);
      $('#dbd-usage').innerHTML = '<tr><th>Player</th><th>Tgt</th><th>Rec</th><th>Car</th><th>Yds</th><th>RZ</th><th>EPA</th></tr>' + a.map(function (u) { return '<tr class="clk' + (st.pl === u.n ? ' on' : '') + '" data-pl="' + esc(u.n) + '"><td>' + esc(u.n) + '</td><td>' + (u.tg || '') + '</td><td>' + (u.rec || '') + '</td><td>' + (u.car || '') + '</td><td>' + u.yds + '</td><td>' + (u.rz || '') + '</td><td class="' + (u.epa >= 0 ? 'pos' : 'neg') + '">' + fmt(u.epa, 1) + '</td></tr>'; }).join('') + (a.length ? '' : '<tr><td colspan="7">No plays match.</td></tr>');
    }
    $('#dbd-usage').onclick = function (e) { var r = e.target.closest('tr[data-pl]'); if (!r) return; st.pl = st.pl === r.dataset.pl ? '' : r.dataset.pl; $('#f-pl').value = st.pl; render(); };

    function detail(F) {
      var p = st.sel == null ? null : P[st.sel];
      if (p == null || F.indexOf(p) < 0) { p = F.slice().sort(function (a, b) { return (b.epa == null ? -9 : b.epa) - (a.epa == null ? -9 : a.epa); })[0]; st.sel = p ? p.i : null; st.selAuto = true; }
      swapText($('#dbd-detailmode'), st.selAuto ? 'Best by EPA' : 'Clicked');
      if (!p) { $('#dbd-detail').innerHTML = '<p class="dbd-note" style="padding:0">No plays match these filters.</p>'; return; }
      var g = gIndex[p.g] || {}, pills = ['<span class="dbd-pill">' + label(p) + '</span>'];
      if (p.td) pills.push('<span class="dbd-pill good">Touchdown</span>'); else if (p.fd) pills.push('<span class="dbd-pill good">First down</span>');
      pills.push('<span class="dbd-pill ' + (p.succ ? 'good' : 'bad') + '">' + (p.succ ? 'Successful' : 'Unsuccessful') + '</span>'); if (p.sg) pills.push('<span class="dbd-pill">Shotgun</span>');
      $('#dbd-detail').innerHTML = '<h3>Week ' + g.week + ' ' + (g.home ? 'vs' : 'at') + ' ' + g.opp + ' · Q' + p.q + ' ' + esc(p.t) + '</h3><h2>' + ord(p.dn) + ' &amp; ' + p.togo + ' at ' + yardText(p.yl) + '</h2><div class="meta">' + pills.join('') + '</div><p class="dbd-desc">' + esc(p.desc) + '</p>' +
        '<dl class="dbd-kv"><dt>Yards</dt><dd>' + (p.yds == null ? 0 : p.yds) + '</dd>' + (p.air != null ? '<dt>Air yards</dt><dd>' + p.air + (p.yac != null ? ' + ' + p.yac + ' after catch' : '') + '</dd>' : '') + (p.loc ? '<dt>Direction</dt><dd>' + p.loc + (p.gap ? ' ' + p.gap : '') + (p.len ? ' · ' + p.len : '') + '</dd>' : '') + '<dt>EPA</dt><dd class="' + ((p.epa || 0) >= 0 ? 'pos' : 'neg') + '">' + sign(p.epa || 0) + '</dd><dt>Score</dt><dd>' + (p.sd > 0 ? 'Up ' + p.sd : p.sd < 0 ? 'Down ' + (-p.sd) : 'Tied') + '</dd></dl>';
    }

    var tt = $('#dbd-tt');
    host.addEventListener('mouseover', function (e) { var g = e.target.closest('.dbd-play'); if (!g) { tt.classList.remove('is-shown'); return; } var p = P[+g.dataset.i], gm = gIndex[p.g] || {}; tt.innerHTML = '<b>Wk ' + gm.week + ' ' + gm.opp + ' · Q' + p.q + ' ' + esc(p.t) + ' · ' + ord(p.dn) + ' &amp; ' + p.togo + '</b><br>' + esc(p.desc) + '<br><span class="' + ((p.epa || 0) >= 0 ? 'pos' : 'neg') + '">EPA ' + sign(p.epa || 0) + '</span>'; tt.classList.add('is-shown'); });
    host.addEventListener('mousemove', function (e) { if (tt.classList.contains('is-shown')) { tt.style.left = Math.min(e.clientX + 14, innerWidth - 330) + 'px'; tt.style.top = (e.clientY + 14) + 'px'; } });
    host.addEventListener('click', function (e) { var g = e.target.closest('.dbd-play'); if (!g) return; st.sel = +g.dataset.i; st.selAuto = false; render(); });

    function render() { var F = filtered(); drawGames(); tiles(F); drive(F); passmap(F); runmap(F); tend(F); charts(F); usage(F); detail(F); }
    render();
  }
})();
