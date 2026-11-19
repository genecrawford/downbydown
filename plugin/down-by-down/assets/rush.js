/* Pass rushers (SCOPE §79): the team highlight (remembered on the device) and Show all. No dependencies. */
(function () {
  var s = document.getElementById('pr-team'), c = document.getElementById('pr-chart'), m = document.getElementById('pr-more'), K = 'dbd_pr_team';
  if (!s || !c) return;
  function hl(t) {
    c.classList.toggle('hl', !!t);
    c.querySelectorAll('.pr-d').forEach(function (g) { g.classList.toggle('on', g.getAttribute('data-t') === t); });
    document.querySelectorAll('.pr-t tbody tr').forEach(function (r) { r.classList.toggle('on', !!t && r.getAttribute('data-t') === t); });
    try { if (t) { localStorage.setItem(K, t); } else { localStorage.removeItem(K); } } catch (e) {}
  }
  s.addEventListener('change', function () { hl(s.value); });
  try { var v = localStorage.getItem(K); if (v && s.querySelector('option[value="' + v.replace(/[^A-Z]/g, '') + '"]')) { s.value = v; hl(v); } } catch (e) {}
  if (m) m.addEventListener('click', function () { document.querySelectorAll('.pr-t tbody tr.pr-x').forEach(function (r) { r.classList.remove('pr-x'); }); m.remove(); });
})();
