/* The Share pack's Copy buttons (SCOPE §68): a button copies its own data-t, or the text box beside it, then says Copied for a moment.
   The clipboard API first; a hidden text box and execCommand where it isn't there. No dependencies. */
(function () {
  function fallback(s) {
    var a = document.createElement('textarea'); a.value = s; a.setAttribute('readonly', ''); a.style.position = 'fixed'; a.style.opacity = '0';
    document.body.appendChild(a); a.select(); var ok = false; try { ok = document.execCommand('copy'); } catch (e) {} a.remove(); return ok;
  }
  function done(b, ok) {
    var was = b.getAttribute('data-was') || b.textContent; b.setAttribute('data-was', was);
    b.textContent = ok ? 'Copied' : 'Select and copy'; b.classList.toggle('ok', ok);
    clearTimeout(b._t); b._t = setTimeout(function () { b.textContent = was; b.classList.remove('ok'); }, 1400);
  }
  document.addEventListener('click', function (e) {
    var b = e.target && e.target.closest ? e.target.closest('.sp-cp') : null; if (!b) return;
    var t = b.getAttribute('data-t'), box = null;
    if (t === null) { var f = b.closest('.sp-f'); box = f ? f.querySelector('textarea') : null; t = box ? box.value : ''; }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(t).then(function () { done(b, true); }, function () { done(b, fallback(t)); });
    } else { done(b, fallback(t)); }
    if (box) { box.focus(); box.select(); }
  });
})();
