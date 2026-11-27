/* Every play a defender made (SCOPE §101), on the throw chart's field (passchart.js, touchchart.js) turned for the defense.
   Reads the JSON the defender view prints in .dbd-dfc: k kind (tkl, ast, tfl, sack, qbh, pd, int), l lane 0-6 (LE LT LG MID RG RT RE,
   as the offense sees it), y yards gained, a air yards, f forced fumble, e EPA, h c d the headline, context and play text.
   Draws the field, key chips that filter by kind (with counts), and the play under the field on hover, focus or tap. */
(function () {
  'use strict';
  var COL = { tkl: '#5b9cf6', ast: '#5b9cf6', tfl: '#e0564b', sack: '#e0564b', qbh: '#f0b04a', pd: '#c4a8ff', int: '#4fd18b', ff: '#4fd18b' };
  var NAME = { sack: 'Sacks', qbh: 'QB hits', tfl: 'Tackles for loss', tkl: 'Tackles', ast: 'Shared tackles', pd: 'Passes defended', int: 'Interceptions' };
  var ORDER = ['ast', 'tkl', 'qbh', 'tfl', 'pd', 'sack', 'int'];
  var W = 900, X0 = 90, LW = 120;

  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function hash(s) { var h = 2166136261; for (var i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); } return ((h >>> 0) % 1000) / 1000; }
  function depth(p) { return (p.k === 'pd' || p.k === 'int') && p.a !== null ? p.a : (p.k === 'qbh' ? -7 + (hash(p.d + 'y') - 0.5) * 3 : p.y); }

  function draw(box) {
    var data; try { data = JSON.parse(box.querySelector('.dbd-dfc-data').textContent || '[]'); } catch (e) { return; }
    if (!data.length) return;
    var fld = box.querySelector('.dbd-dfc-fld'), keys = box.querySelector('.dbd-dfc-keys'), now = box.querySelector('.dbd-thr-now');
    var top = 26; data.forEach(function (p) { top = Math.max(top, Math.min(51, Math.ceil(depth(p)) + 3)); });
    var H = 40 + (top + 12) * 10;
    var Y = function (yd) { return 40 + (top - Math.max(-11, Math.min(top, yd))) * 10; };
    var g = ['<svg viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '" aria-hidden="true" focusable="false"><rect width="' + W + '" height="' + H + '" fill="#2c4a35" rx="4"/>'];
    for (var yd = -10; yd < top; yd += 5) {
      var y = Y(yd);
      g.push('<line x1="0" x2="' + W + '" y1="' + y + '" y2="' + y + '" stroke="rgba(255,255,255,' + (yd === 0 ? '.55' : '.13') + ')" stroke-width="' + (yd === 0 ? 2 : 1) + '"/>');
      if (yd) g.push('<text x="12" y="' + (y + 4) + '" fill="rgba(255,255,255,.55)" font-size="12" font-family="IBM Plex Mono,monospace">' + (yd > 0 ? '+' : '') + yd + '</text>');
    }
    ['LE', 'LT', 'LG', 'MID', 'RG', 'RT', 'RE'].forEach(function (l, i) { g.push('<text x="' + (X0 + i * LW) + '" y="' + Y(-10.6) + '" fill="rgba(255,255,255,.6)" font-size="11" text-anchor="middle" font-family="IBM Plex Mono,monospace">' + l + '</text>'); });
    for (var i = 1; i < 7; i++) { var lx = X0 + i * LW - LW / 2; g.push('<line x1="' + lx + '" x2="' + lx + '" y1="' + Y(top) + '" y2="' + Y(-9.5) + '" stroke="rgba(255,255,255,.07)"/>'); }
    g.push('<text x="12" y="' + (Y(0) + 4) + '" fill="rgba(255,255,255,.8)" font-size="12" font-family="IBM Plex Mono,monospace">LOS</text>');
    g.push('<text x="' + (W - 10) + '" y="22" fill="rgba(255,255,255,.55)" font-size="11" text-anchor="end" font-family="IBM Plex Mono,monospace">THE OFFENSE GOES UP THE FIELD</text>');
    var idx = data.map(function (p, i) { return i; }).sort(function (a, b) { return ORDER.indexOf(data[a].k) - ORDER.indexOf(data[b].k); });
    idx.forEach(function (i) {
      var p = data[i], c = COL[p.k] || '#ccc', x = X0 + p.l * LW + (hash(p.d + i) - 0.5) * (p.k === 'pd' || p.k === 'int' || p.k === 'qbh' ? 80 : 60), yd0 = depth(p), y = Y(yd0);
      var at = ' data-i="' + i + '" data-k="' + p.k + '" tabindex="0" role="button" aria-label="' + esc(p.h + '. ' + p.c) + '"';
      if (p.k === 'pd' || p.k === 'int') { var s = p.k === 'int' ? 8 : 6.5; g.push('<path d="M' + x.toFixed(1) + ' ' + (y - s).toFixed(1) + ' l' + s + ' ' + s + ' l-' + s + ' ' + s + ' l-' + s + ' -' + s + ' z" fill="' + c + '" stroke="#1b2f22" stroke-width="1"' + at + '/>'); }
      else if (p.k === 'qbh') g.push('<circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="6" fill="#2c4a35" stroke="' + c + '" stroke-width="2.4"' + at + '/>');
      else if (p.k === 'ast') g.push('<circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="5" fill="#2c4a35" stroke="' + c + '" stroke-width="2"' + at + '/>');
      else g.push('<circle cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="' + (p.k === 'sack' ? 7.5 : 5.5) + '" fill="' + c + '" stroke="#1b2f22" stroke-width="1"' + at + '/>');
      if (p.f) g.push('<circle class="ff" data-for="' + i + '" cx="' + x.toFixed(1) + '" cy="' + y.toFixed(1) + '" r="11" fill="none" stroke="' + COL.ff + '" stroke-width="2" pointer-events="none"/>');
      if (yd0 > top) g.push('<text data-for="' + i + '" x="' + x.toFixed(1) + '" y="' + (y - 11).toFixed(1) + '" fill="#fff" font-size="11" text-anchor="middle" pointer-events="none">▲ ' + Math.round(yd0) + '</text>');
    });
    g.push('</svg>');
    fld.innerHTML = g.join('');
    var svg = fld.querySelector('svg'); svg.removeAttribute('aria-hidden'); svg.setAttribute('role', 'group');
    svg.setAttribute('aria-label', 'Every play ' + (box.dataset.name || 'he') + ' made, placed by lane and the yard line where it ended');
    /* key chips: counts, and a filter by kind */
    var n = {}; data.forEach(function (p) { n[p.k] = (n[p.k] || 0) + 1; });
    keys.innerHTML = ['sack', 'qbh', 'tfl', 'tkl', 'ast', 'pd', 'int'].filter(function (k) { return n[k]; }).map(function (k) {
      var sh = k === 'pd' || k === 'int' ? 'dia' : (k === 'qbh' || k === 'ast' ? 'ring' : 'dot');
      return '<button type="button" class="dbd-dfc-k" data-k="' + k + '" aria-pressed="true"><i class="' + sh + '" style="--c:' + COL[k] + '"></i>' + NAME[k] + ' <b>' + n[k] + '</b></button>';
    }).join('') + (data.some(function (p) { return p.f; }) ? '<span class="dbd-dfc-ff"><i></i>Forced fumble</span>' : '');
    keys.addEventListener('click', function (e) {
      var b = e.target.closest('.dbd-dfc-k'); if (!b) return;
      var on = b.getAttribute('aria-pressed') !== 'true'; b.setAttribute('aria-pressed', on ? 'true' : 'false');
      svg.querySelectorAll('[data-k="' + b.dataset.k + '"]').forEach(function (el) {
        el.classList.toggle('off', !on);
        svg.querySelectorAll('[data-for="' + el.dataset.i + '"]').forEach(function (r) { r.classList.toggle('off', !on); });
      });
    });
    /* the play you're on, under the field */
    var cur = null;
    function show(el) {
      if (cur) cur.classList.remove('on'); cur = el; el.classList.add('on');
      var p = data[+el.dataset.i]; now.className = 'dbd-thr-now';
      now.innerHTML = '<div><div class="hd"><i style="background:' + (COL[p.k] || '#ccc') + '"></i>' + esc(p.h) + '</div><div class="ctx">' + esc(p.c) + '</div><div class="ctx dbd-dfc-d">' + esc(p.d) + '</div></div>'
        + '<div class="nums"><div><b>' + (p.e > 0 ? '+' : '') + p.e.toFixed(2) + '</b><small>EPA</small></div></div>';
    }
    svg.addEventListener('mouseover', function (e) { var el = e.target.closest('[data-i]'); if (el) show(el); });
    svg.addEventListener('click', function (e) { var el = e.target.closest('[data-i]'); if (el) show(el); });
    svg.addEventListener('focusin', function (e) { var el = e.target.closest('[data-i]'); if (el) show(el); });
    svg.addEventListener('keydown', function (e) { if (e.key !== 'Enter' && e.key !== ' ') return; var el = e.target.closest('[data-i]'); if (el) { e.preventDefault(); show(el); } });
  }

  function boot() { document.querySelectorAll('.dbd-dfc').forEach(draw); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
