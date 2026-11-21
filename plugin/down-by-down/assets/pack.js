/* The Share pack's Copy buttons (SCOPE §68): a button copies its own data-t, or the text box beside it, then says Copied for a moment.
   Share on Facebook (SCOPE §73) copies its data-t too, says "Copied, now paste", and still opens Facebook's share box (the link's own target).
   "Change the list" opens the settings box at the bottom (SCOPE §74).
   Auto publish (SCOPE §81): Cancel closes the ask; Post it now says Posting… and can't be pressed twice.
   The clipboard API first; a hidden text box and execCommand where it isn't there. No dependencies. */
(function () {
  function fallback(s) {
    var a = document.createElement('textarea'); a.value = s; a.setAttribute('readonly', ''); a.style.position = 'fixed'; a.style.opacity = '0';
    document.body.appendChild(a); a.select(); var ok = false; try { ok = document.execCommand('copy'); } catch (e) {} a.remove(); return ok;
  }
  function copy(t, then) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(function () { then(true); }, function () { then(fallback(t)); });
    } else { then(fallback(t)); }
  }
  function done(b, ok, yes, ms) {
    var was = b.getAttribute('data-was') || b.textContent; b.setAttribute('data-was', was);
    b.textContent = ok ? (yes || 'Copied') : 'Select and copy'; b.classList.toggle('ok', ok);
    clearTimeout(b._t); b._t = setTimeout(function () { b.textContent = was; b.classList.remove('ok'); }, ms || 1400);
  }
  if (location.hash === '#dbd-pack-settings') { var sb0 = document.getElementById('dbd-pack-settings'); if (sb0) { sb0.open = true; } }
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target : null; if (!t) return;
    var cx = t.closest('.sp-apx');  /* Auto publish's Cancel (SCOPE §81) */
    if (cx) { var d = cx.closest('details'); if (d) { d.open = false; } return; }
    var op = t.closest('.sp-open');  /* "Change the list" opens the settings box (SCOPE §74) */
    if (op) { var sb = document.getElementById('dbd-pack-settings'); if (sb) { sb.open = true; } return; }
    var fb = t.closest('.sp-fb');
    if (fb) { var ft = fb.getAttribute('data-t'); if (ft) { copy(ft, function (ok) { done(fb, ok, 'Copied, now paste', 4000); }); } return; }  /* no preventDefault: the share box opens */
    var b = t.closest('.sp-cp'); if (!b) return;
    var s = b.getAttribute('data-t'), box = null;
    if (s === null) { var f = b.closest('.sp-f'); box = f ? f.querySelector('textarea') : null; s = box ? box.value : ''; }
    copy(s, function (ok) { done(b, ok); });
    if (box) { box.focus(); box.select(); }
  });
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f || !f.querySelector) return;
    var go = f.querySelector('.sp-apgo'); if (!go) return;
    if (f.getAttribute('data-sent')) { e.preventDefault(); return; }
    f.setAttribute('data-sent', '1'); go.textContent = 'Posting…'; go.setAttribute('aria-disabled', 'true');
  });
})();
