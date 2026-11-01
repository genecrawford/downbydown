/* Down by Down: the game page. The win probability scrubber (SCOPE §30): hover or drag across the chart for the clock, the chance and the score at that snap */
(function () {
  document.querySelectorAll('.dbd-wpc[data-wp]').forEach(function (box) {
    var D = JSON.parse(box.getAttribute('data-wp')), S = D.s, area = box.querySelector('.area'), cur = area.querySelector('.cur'), rd = area.querySelector('.read');
    var clock = function (el) { if (el >= 3600 && D.e > 3600) { var r = Math.max(0, 600 - (el - 3600)); return 'OT ' + Math.floor(r / 60) + ':' + ('0' + Math.round(r % 60)).slice(-2); } var q = Math.min(4, Math.floor(el / 900) + 1), r2 = 900 * q - el; return 'Q' + q + ' ' + Math.floor(r2 / 60) + ':' + ('0' + Math.round(r2 % 60)).slice(-2); };
    var show = function (cx) {
      var r = area.getBoundingClientRect(), f = Math.max(0, Math.min(1, (cx - r.left) / r.width)), el = f * D.e, i = 0;
      while (i < S.length - 1 && S[i + 1][0] <= el) i++;
      var x = S[i], hw = Math.round(x[1] / 10), fav = hw >= 50 ? D.h : D.a, fv = hw >= 50 ? hw : 100 - hw;
      cur.hidden = false; rd.hidden = false; cur.style.left = (f * 100) + '%';
      if (el > 3600 && D.e > 3600) {  /* overtime: the score at that moment, no chance (the model does not hold up there) */
        var h = S[S.length - 1][2], a = S[S.length - 1][3];
        (D.o || []).forEach(function (o) { if (o[0] <= el) { h = o[1]; a = o[2]; } });
        rd.innerHTML = '<b>' + clock(el) + '</b> · overtime · ' + D.a + ' ' + a + ' ' + D.h + ' ' + h;
      } else if (x[0] >= 3600) {
        rd.innerHTML = '<b>End of regulation</b> · ' + (x[1] === 500 ? 'tied' : (x[1] > 500 ? D.h : D.a) + ' won') + ' · ' + D.a + ' ' + x[3] + ' ' + D.h + ' ' + x[2];
      } else {
        rd.innerHTML = '<b>' + clock(x[0]) + '</b> · ' + (hw === 50 ? 'even' : fav + ' <b>' + fv + '%</b>') + ' · ' + D.a + ' ' + x[3] + ' ' + D.h + ' ' + x[2];
      }
      var w = rd.offsetWidth, px = f * r.width; rd.style.left = Math.max(0, Math.min(r.width - w, px + 8 > r.width - w ? px - w - 8 : px + 8)) + 'px';
    };
    var hide = function () { cur.hidden = true; rd.hidden = true; };
    area.addEventListener('pointermove', function (e) { if (e.target.closest('[data-tip]')) { hide(); return; } show(e.clientX); });
    area.addEventListener('pointerleave', hide);
    area.addEventListener('pointerdown', function (e) { if (!e.target.closest('[data-tip]')) show(e.clientX); });
  });
})();
