/* Down by Down: Leaders (SCOPE §37). The receivers tab's All / WR / TE switch, and a full board's position and minimum-usage
   filters, which hide rows and renumber the ranks (ties marked T-). No config: everything is in the page. */
(function () {
  var root = document.querySelector('.dbd-view-leaders'); if (!root) return;
  var pill = function (bar) { var t = bar.querySelector('[aria-selected="true"]'), p = bar.querySelector('.t-tabs-pill'); if (!t || !p || !t.offsetWidth) return; p.style.transform = 'translateX(' + t.offsetLeft + 'px)'; p.style.width = t.offsetWidth + 'px'; };
  var bars = root.querySelectorAll('.ld-pos,.ld-bpos');
  var again = function () { bars.forEach(pill); };
  window.addEventListener('load', again); window.addEventListener('resize', again); setTimeout(again, 60); if (document.fonts && document.fonts.ready) document.fonts.ready.then(again);
  var choose = function (bar, b) { bar.querySelectorAll('.t-tab').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); pill(bar); };

  /* the tab's position switch: every card carries All, WR and TE lists; the wrapper's data-pos picks one */
  var box = root.querySelector('.dbd-ld'), pos = root.querySelector('.ld-pos');
  if (box && pos) pos.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; choose(pos, b); box.setAttribute('data-pos', b.dataset.lpos || 'all'); });

  /* a full board's filters */
  var table = root.querySelector('table.ld-full[data-low]'); if (!table) return;
  var min = root.querySelector('#ld-min'), bpos = root.querySelector('.ld-bpos'), count = root.querySelector('#ld-count'), want = '';
  var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-use]'));
  function apply() {
    var m = min ? +min.value : 0, shown = rows.filter(function (r) { var ok = +r.dataset.use >= m && (!want || r.dataset.pos === want); r.hidden = !ok; return ok; });
    shown.forEach(function (r, i) {  /* rows are already in board order: competition ranks, ties by equal value */
      var v = r.dataset.v, j = i; while (j > 0 && shown[j - 1].dataset.v === v) j--;
      var tied = (i > 0 && shown[i - 1].dataset.v === v) || (i < shown.length - 1 && shown[i + 1].dataset.v === v);
      r.querySelector('.rk').textContent = (tied ? 'T-' : '') + (j + 1);
    });
    if (count) count.textContent = shown.length + ' player' + (shown.length === 1 ? '' : 's');
  }
  if (min) min.addEventListener('change', apply);
  if (bpos) bpos.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; choose(bpos, b); want = b.dataset.lpos || ''; apply(); });
  apply();
})();
