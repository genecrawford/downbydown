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
    var blank = function (t) { return t === '' || t === '-' || t === '\u2013'; };
    var num = function (t) { t = t.replace(/[%,]/g, '').replace(/\u2212/g, '-').trim(); return /^[-+]?\d+(\.\d+)?$/.test(t) ? parseFloat(t) : null; };  /* whole cell must be a number */
    var cell = function (r) { var c = r.children[idx]; if (!c) return ''; if (c.getAttribute('data-v') != null) return c.getAttribute('data-v'); var a = c.querySelector('a'); return (a ? a.textContent : c.textContent).trim(); };  /* a name link wins over the jersey number inside its svg */
    var vals = rows.map(cell).filter(function (t) { return !blank(t); });
    var numeric = vals.length > 0 && vals.every(function (t) { return num(t) !== null; });
    var cur = th.getAttribute('aria-sort'), dir = cur === 'descending' ? 1 : cur === 'ascending' ? -1 : (numeric ? -1 : 1);
    rows.sort(function (a, b) { var x = cell(a), y = cell(b); if (blank(x) && blank(y)) return 0; if (blank(x)) return 1; if (blank(y)) return -1; return numeric ? (num(x) - num(y)) * dir : x.localeCompare(y) * dir; });
    Array.prototype.forEach.call(hdr.children, function (h) { h.removeAttribute('aria-sort'); });
    th.setAttribute('aria-sort', dir === 1 ? 'ascending' : 'descending');
    rows.forEach(function (r) { r.parentNode.appendChild(r); });
  });

  /* rich tooltips: any element with data-tip (percentile markers) gets the site tooltip on hover or focus,
     and a percentile row highlights that player's marker and value column while you look */
  var tip = document.getElementById('dbd-tt');
  if (!tip) { tip = document.createElement('div'); tip.className = 'dbd-tt'; tip.id = 'dbd-tt'; tip.setAttribute('role', 'tooltip'); document.body.appendChild(tip); }
  var hot = null;
  var showTip = function (el, x, y) {
    tip.innerHTML = el.getAttribute('data-tip'); tip.classList.add('is-shown');
    var w = tip.offsetWidth || 260, h = tip.offsetHeight || 60, left = Math.min(x + 14, innerWidth - w - 8), top = y - h - 14; if (top < 8) top = y + 18;
    tip.style.left = left + 'px'; tip.style.top = top + 'px';
    var row = el.closest('.row'); if (row && el.dataset.slot != null) { row.classList.add('is-hot'); row.querySelectorAll('[data-slot]').forEach(function (n) { n.classList.toggle('is-on', n.dataset.slot === el.dataset.slot); }); hot = row; }
  };
  var hideTip = function () { tip.classList.remove('is-shown'); if (hot) { hot.classList.remove('is-hot'); hot.querySelectorAll('.is-on').forEach(function (n) { n.classList.remove('is-on'); }); hot = null; } };
  root.addEventListener('mouseover', function (e) { var el = e.target.closest('[data-tip]'); if (!el) { if (tip.classList.contains('is-shown') && !e.target.closest('.dbd-play')) hideTip(); return; } showTip(el, e.clientX, e.clientY); });
  root.addEventListener('mousemove', function (e) { var el = e.target.closest('[data-tip]'); if (el && tip.classList.contains('is-shown')) { var w = tip.offsetWidth, h = tip.offsetHeight, top = e.clientY - h - 14; tip.style.left = Math.min(e.clientX + 14, innerWidth - w - 8) + 'px'; tip.style.top = (top < 8 ? e.clientY + 18 : top) + 'px'; } });
  root.addEventListener('mouseleave', hideTip);
  root.addEventListener('focusin', function (e) { var el = e.target.closest('[data-tip]'); if (!el) return; var r = el.getBoundingClientRect(); showTip(el, r.left + r.width / 2, r.top); el.setAttribute('aria-describedby', 'dbd-tt'); });
  root.addEventListener('focusout', function (e) { var el = e.target.closest('[data-tip]'); if (el) { el.removeAttribute('aria-describedby'); hideTip(); } });

  /* whole rows are links */
  root.addEventListener('click', function (e) {
    if (e.target.closest('a,button,select,input,.dbd-stdot,[data-tip]')) return;  /* a tap on a status dot or a tooltip marker reads it, it does not open the row */
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

/* share card (SCOPE §26): on devices that can share files, fetch the PNG ahead of time so the click can hand it to the share sheet
   while the browser still counts the click as a user gesture; anywhere else, or before it is ready, the link opens the image. */
(function () {
  var a = document.querySelector('a.dbd-share'); if (!a || !navigator.share || !navigator.canShare) return;
  var file = null, probe = new File([new Blob(['x'])], 'x.png', { type: 'image/png' });
  if (!navigator.canShare({ files: [probe] })) return;
  var load = function () { fetch(a.href).then(function (r) { return r.ok ? r.blob() : null; }).then(function (b) { if (b) file = new File([b], 'down-by-down-card.png', { type: 'image/png' }); }).catch(function () {}); };
  if ('requestIdleCallback' in window) requestIdleCallback(load, { timeout: 4000 }); else setTimeout(load, 1500);
  a.addEventListener('click', function (e) {
    if (!file) return;  /* not ready yet: the link opens the image in a new tab */
    e.preventDefault();
    navigator.share({ files: [file], title: a.getAttribute('data-share-title') || document.title, url: location.href }).catch(function (err) {
      if (err && err.name === 'AbortError') return;  /* the share sheet was dismissed; nothing to do */
      window.open(a.href, '_blank', 'noopener');
    });
  });
})();

/* panel labels truncate at desktop (SCOPE §28); a clipped one carries its full text as a title */
(function () {
  var label = function () {
    document.querySelectorAll('.dbd .dbd-panel > .dbd-ph .dbd-lab').forEach(function (l) {
      if (l.scrollWidth > l.clientWidth + 1) l.setAttribute('title', l.textContent.replace(/\s+/g, ' ').trim()); else l.removeAttribute('title');
    });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', label); else label();
  var t; window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(label, 150); });
})();
