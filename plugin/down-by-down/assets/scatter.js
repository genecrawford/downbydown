/* The Leaders charts (SCOPE §79–80): every chart's Highlight menu shares one team, remembered on the device so it carries to every tab;
   Show all opens the table above it. A reader's choice sends an Analytics event; a restored one doesn't. No dependencies. */
(function () {
  var K = 'dbd_pr_team', menus = document.querySelectorAll('.pr-team');
  if (!menus.length) return;
  function hl(t) {
    document.querySelectorAll('.pr-chart').forEach(function (c) {
      var has = !!t && !!c.querySelector('.pr-d[data-t="' + t + '"]');  /* a team with nobody on this chart leaves it as it is; the choice stays for the others (Greptile, PR #148) */
      c.classList.toggle('hl', has);
      c.querySelectorAll('.pr-d').forEach(function (g) { g.classList.toggle('on', has && g.getAttribute('data-t') === t); });
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
  /* each Show all opens the table just above it: the chart's, or the takeaways board (SCOPE §102) */
  document.querySelectorAll('.pr-more').forEach(function (m) {
    m.addEventListener('click', function () {
      var box = m.previousElementSibling; while (box && !box.querySelector('.pr-t')) box = box.previousElementSibling;
      (box || document).querySelectorAll('.pr-t tbody tr.pr-x').forEach(function (r) { r.classList.remove('pr-x'); }); m.remove();
    });
  });
})();
