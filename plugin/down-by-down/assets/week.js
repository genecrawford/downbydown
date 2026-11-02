/* The week in review (SCOPE §40): the scoreboard's Kickoff order / Most dramatic switch. Pane tabs, tooltips and the fantasy
   switch are dbd-site.js's. Without this script the cards stay in kickoff order. */
(function () {
  document.querySelectorAll('.wk-games').forEach(function (p) {
    var seg = p.querySelector('.wk-sort'), grid = p.querySelector('.wk-grid'); if (!seg || !grid) return;
    var pill = seg.querySelector('.t-tabs-pill');
    var place = function () { var t = seg.querySelector('[aria-selected="true"]'); if (pill && t && t.offsetWidth) { pill.style.transform = 'translateX(' + t.offsetLeft + 'px)'; pill.style.width = t.offsetWidth + 'px'; } };
    seg.addEventListener('click', function (e) {
      var b = e.target.closest('.t-tab'); if (!b) return;
      seg.querySelectorAll('.t-tab').forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); place();
      var k = b.getAttribute('data-sort'), cards = Array.prototype.slice.call(grid.children);
      cards.sort(function (a, c) { return k === 'd' ? (+c.getAttribute('data-d')) - (+a.getAttribute('data-d')) : (a.getAttribute('data-k') < c.getAttribute('data-k') ? -1 : (a.getAttribute('data-k') > c.getAttribute('data-k') ? 1 : 0)); });
      cards.forEach(function (c) { grid.appendChild(c); });
    });
    requestAnimationFrame(place); window.addEventListener('load', place); window.addEventListener('resize', place);
  });
})();
