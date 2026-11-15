/* The season draft and combine boards (SCOPE §63): chip and list filters, the count in the panel header, Reset, column sorting and the year
   switcher. The tables come from PHP with every value in data- attributes; this only shows, hides and reorders rows. */
(function () {
  var root = document.querySelector('.dbd-boards'); if (!root) return;
  var table = root.querySelector('table.bd-t'), body = table && table.tBodies[0];
  var rows = body ? [].slice.call(body.querySelectorAll('tr[data-p]')) : [], heads = body ? [].slice.call(body.querySelectorAll('tr.rd')) : [];
  var count = root.querySelector('.bd-count'), reset = root.querySelector('[data-reset]');
  var sortKey = null;

  function filters() { var f = {}; root.querySelectorAll('[data-filter]').forEach(function (el) { var v = el.tagName === 'SELECT' ? el.value : (el.dataset.v || ''); if (v) f[el.dataset.filter] = v; }); return f; }
  function apply() {
    var f = filters(), keys = Object.keys(f), n = 0;
    rows.forEach(function (r) { var ok = keys.every(function (k) { return r.dataset[k] === f[k]; }); r.hidden = !ok; if (ok) n++; });
    heads.forEach(function (h) { h.hidden = sortKey !== null || !rows.some(function (r) { return !r.hidden && r.dataset.r === h.dataset.rd; }); });
    if (count) count.textContent = (n === rows.length ? 'all ' : n + ' of ') + rows.length + ' ' + count.dataset.unit;
    if (reset) reset.hidden = !keys.length;
  }
  root.querySelectorAll('.bd-chips[data-filter]').forEach(function (box) {
    box.addEventListener('click', function (e) {
      var b = e.target.closest('.bd-chip'); if (!b) return;
      box.querySelectorAll('.bd-chip').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      box.dataset.v = b.dataset.v; apply();
    });
  });
  root.querySelectorAll('select[data-filter]').forEach(function (s) { s.addEventListener('change', apply); });
  if (reset) reset.addEventListener('click', function () {
    root.querySelectorAll('.bd-chips[data-filter]').forEach(function (box) { box.dataset.v = ''; box.querySelectorAll('.bd-chip').forEach(function (x) { x.setAttribute('aria-pressed', x.dataset.v === '' ? 'true' : 'false'); }); });
    root.querySelectorAll('select[data-filter]').forEach(function (s) { s.value = ''; }); apply();
  });

  /* sorting: numbers first-click biggest first, except the pick, the timed drills and the draft slot (smallest first); names A to Z. Blanks sink. */
  if (table) table.querySelectorAll('th[data-k]').forEach(function (th) {
    th.setAttribute('role', 'button'); th.tabIndex = 0;  /* round headings show only in pick order, ascending (Greptile, PR #112) */
    var go = function () {
      var k = th.dataset.k, num = rows.some(function (r) { return r.dataset[k] !== '' && !isNaN(parseFloat(r.dataset[k])); });
      var first = k === 'p' || th.hasAttribute('data-low') || !num ? 'asc' : 'desc';
      var dir = th.dataset.dir ? (th.dataset.dir === 'asc' ? 'desc' : 'asc') : first;
      table.querySelectorAll('th[data-k]').forEach(function (x) { delete x.dataset.dir; x.removeAttribute('aria-sort'); });
      th.dataset.dir = dir; th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending'); sortKey = k;
      var s = dir === 'asc' ? 1 : -1;
      rows.sort(function (a, b) {
        var x = a.dataset[k], y = b.dataset[k];
        if (x === '' || y === '') return x === y ? 0 : (x === '' ? 1 : -1);
        var d = num ? parseFloat(x) - parseFloat(y) : x.localeCompare(y);
        return d * s || (parseFloat(a.dataset.p) - parseFloat(b.dataset.p));
      });
      if (k === 'p' && dir === 'asc') { var byR = {}; rows.forEach(function (r) { (byR[r.dataset.r] = byR[r.dataset.r] || []).push(r); }); heads.forEach(function (h) { body.appendChild(h); (byR[h.dataset.rd] || []).forEach(function (r) { body.appendChild(r); }); }); }
      else { rows.forEach(function (r) { body.appendChild(r); }); }
      if (k === 'p' && dir === 'asc') sortKey = null;
      table.classList.toggle('is-sorted', sortKey !== null);  /* the round shows beside each pick once its headings are hidden (2.23.2) */
      apply();
    };
    th.addEventListener('click', go); th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
  });

  root.querySelectorAll('select[data-go]').forEach(function (s) { s.addEventListener('change', function () { if (s.value) location.href = s.value; }); });
  apply();
})();
