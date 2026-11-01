/* Down by Down: the Defense tab (SCOPE §39). The pass grid's How often / EPA allowed switch, with its sliding pill; tabbed panels,
   the scoring switch and tooltips are dbd-site.js's. */
(function () {
  var root = document.querySelector('.dbd-defview'); if (!root) return;
  root.querySelectorAll('.dbd-lz').forEach(function (lz) {
    var v = lz.querySelector('.dbd-lzv'); if (!v) return; var pl = v.querySelector('.t-tabs-pill');
    var go = function () { var t = v.querySelector('[aria-selected="true"]'); if (t && pl && t.offsetWidth) { pl.style.transform = 'translateX(' + t.offsetLeft + 'px)'; pl.style.width = t.offsetWidth + 'px'; } };
    v.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; v.querySelectorAll('.t-tab').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); lz.setAttribute('data-mode', b.dataset.m); go(); });
    window.addEventListener('load', go); window.addEventListener('resize', go); setTimeout(go, 60); if (document.fonts && document.fonts.ready) document.fonts.ready.then(go);
    /* the grid sits in a hidden tab at first: measure again when its tab is shown */
    var pane = lz.closest('.dbd-tabbed'); if (pane) pane.addEventListener('click', function () { setTimeout(go, 30); });
  });
})();
