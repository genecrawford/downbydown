/* Down by Down: small behaviors shared by every view.
   Local kickoff times, clickable rows, the title reveal, the softened strip hover, and the team picker's
   "pick up where you left off" box. No dependencies. */
(function () {
  var root = document.querySelector('.dbd');
  if (!root) return;

  /* kickoff times: the server prints Eastern; show the viewer's local time instead */
  var fmt = { time: { hour: 'numeric', minute: '2-digit' }, day: { weekday: 'short', hour: 'numeric', minute: '2-digit' } };
  root.querySelectorAll('time.dbd-kick[datetime]').forEach(function (el) {
    var d = new Date(el.getAttribute('datetime'));
    if (isNaN(d)) return;
    try { el.textContent = d.toLocaleString([], fmt[el.dataset.fmt] || fmt.time); } catch (e) {}
  });
  root.querySelectorAll('.dbd-sc .dbd-hd time').forEach(function (el) {
    var d = new Date(el.getAttribute('datetime'));
    if (!isNaN(d)) { try { el.textContent = d.toLocaleString([], fmt.day); } catch (e) {} }
  });

  /* sortable stat tables: click a heading. Numeric columns start descending, text ascending; blanks sink.
     The Players index sorts its own data in players.js; everything else sorts the rows in place. */
  root.addEventListener('click', function (e) {
    var th = e.target.closest('th'); if (!th) return;
    var table = th.closest('table.dbd-sortable'); if (!table || table.id === 'dbd-plist') return;
    var hdr = th.parentNode, idx = Array.prototype.indexOf.call(hdr.children, th);
    var rows = Array.prototype.filter.call(table.rows, function (r) { return r !== hdr && !r.querySelector('th') && !(r.parentNode && r.parentNode.tagName === 'TFOOT'); });
    var num = function (t) { t = t.replace(/[%,+]/g, '').replace(/\u2212/g, '-').trim(); if (t === '' || t === '-' || t === '\u2013') return null; var v = parseFloat(t); return isNaN(v) ? null : v; };
    var cell = function (r) { var c = r.children[idx]; return c ? c.textContent.trim() : ''; };
    var numeric = rows.some(function (r) { return num(cell(r)) !== null; });
    var cur = th.getAttribute('aria-sort'), dir = cur === 'descending' ? 1 : cur === 'ascending' ? -1 : (numeric ? -1 : 1);
    rows.sort(function (a, b) { var x = cell(a), y = cell(b); if (numeric) { var nx = num(x), ny = num(y); if (nx === null && ny === null) return 0; if (nx === null) return 1; if (ny === null) return -1; return (nx - ny) * dir; } return x.localeCompare(y) * dir; });
    Array.prototype.forEach.call(hdr.children, function (h) { h.removeAttribute('aria-sort'); });
    th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
    rows.forEach(function (r) { r.parentNode.appendChild(r); });
  });

  /* whole rows are links */
  root.addEventListener('click', function (e) {
    if (e.target.closest('a,button,select,input')) return;
    var r = e.target.closest('tr[data-href]');
    if (r) location.href = r.dataset.href;
  });

  /* 18 texts reveal on first paint, with a settle fallback so nothing can stay invisible */
  requestAnimationFrame(function () { root.querySelectorAll('.t-stagger').forEach(function (b) { b.classList.add('is-shown'); }); });
  setTimeout(function () { document.documentElement.classList.add('t-settled'); }, 1400);

  /* 11 avatar group hover on the score strip, softened */
  var strip = root.querySelector('.dbd-strip');
  if (strip) {
    var cs = getComputedStyle(root);
    var num = function (n, fb) { var v = parseFloat(cs.getPropertyValue(n)); return isFinite(v) ? v : fb; };
    var ease = function (n, fb) { return cs.getPropertyValue(n).trim() || fb; };
    var setShifts = function (activeIdx, phase) {
      var items = Array.prototype.slice.call(strip.querySelectorAll('.t-avatar'));
      var lift = num('--avatar-lift', -2), falloff = num('--avatar-falloff', .3), scale = num('--avatar-scale', 1.015);
      var tf = phase === 'out' ? ease('--avatar-ease-out', 'cubic-bezier(0.34, 1.6, 0.64, 1)') : ease('--avatar-ease-in', 'cubic-bezier(0.22, 1, 0.36, 1)');
      items.forEach(function (el, i) {
        el.style.transitionTimingFunction = tf;
        if (activeIdx == null) { el.style.setProperty('--shift', '0px'); el.style.setProperty('--scale-active', '1'); return; }
        var d = Math.abs(i - activeIdx);
        el.style.setProperty('--shift', (lift * Math.pow(falloff, d)).toFixed(3) + 'px');
        el.style.setProperty('--scale-active', i === activeIdx ? String(scale) : '1');
      });
    };
    strip.addEventListener('mouseover', function (e) {
      var el = e.target.closest('.t-avatar'); if (!el) return;
      setShifts(Array.prototype.indexOf.call(strip.querySelectorAll('.t-avatar'), el), 'in');
    });
    strip.addEventListener('mouseleave', function () { setShifts(null, 'out'); });
  }

  /* team picker: remember the last team viewed */
  var last = document.getElementById('dbd-last');
  if (last) {
    var abbr = null;
    try { abbr = localStorage.getItem('dbd-last-team'); } catch (e) {}
    var pen = document.getElementById('dbd-pennants');
    if (abbr && pen) {
      try {
        var marks = JSON.parse(pen.textContent || '{}');
        var row = root.querySelector('.dbd-stand tr[data-href*="team=' + abbr + '"] .dbd-nm a');
        if (marks[abbr] && row) {
          var a = document.createElement('a');
          a.className = 'dbd-panel dbd-lastbox'; a.href = row.href;
          a.innerHTML = '<span class="dbd-lab">Pick up where you left off</span><span class="lb">' + marks[abbr] + '<b>' + row.textContent + '</b></span><span class="dbd-go">Open the explorer</span>';
          last.appendChild(a);
        }
      } catch (e) {}
    }
  }
})();
