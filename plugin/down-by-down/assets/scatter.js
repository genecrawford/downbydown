/* The Leaders charts (SCOPE §79–80): every chart's Highlight menu shares one team, remembered on the device so it carries to every tab;
   Show all opens the pass rushers' table. A reader's choice sends an Analytics event; a restored one doesn't. No dependencies. */
(function () {
  var K = 'dbd_pr_team', menus = document.querySelectorAll('.pr-team');
  if (!menus.length) return;
  function hl(t) {
    document.querySelectorAll('.pr-chart').forEach(function (c) {
      c.classList.toggle('hl', !!t);
      c.querySelectorAll('.pr-d').forEach(function (g) { g.classList.toggle('on', g.getAttribute('data-t') === t); });
    });
    document.querySelectorAll('.pr-t tbody tr').forEach(function (r) { r.classList.toggle('on', !!t && r.getAttribute('data-t') === t); });
    menus.forEach(function (s) { s.value = s.querySelector('option[value="' + t + '"]') ? t : ''; });
  }
  menus.forEach(function (s) {
    s.addEventListener('change', function () {
      var t = s.value; hl(t);
      try { if (t) { localStorage.setItem(K, t); } else { localStorage.removeItem(K); } } catch (e) {}
      var p = s.closest('.pr-panel');
      if (window.dbdTrack) window.dbdTrack('select_content', { content_type: 'leaders_chart_' + (p ? p.getAttribute('data-chart') : ''), item_id: t || 'all' });
    });
  });
  try { var v = (localStorage.getItem(K) || '').replace(/[^A-Z]/g, ''); if (v) { hl(v); } } catch (e) {}
  var m = document.getElementById('pr-more');
  if (m) m.addEventListener('click', function () { document.querySelectorAll('.pr-t tbody tr.pr-x').forEach(function (r) { r.classList.remove('pr-x'); }); m.remove(); });
})();
