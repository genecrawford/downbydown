/* The Share pack (SCOPE §68, laid out as a calendar in §87): a button copies its own data-t, or the text box beside it, then says Copied for a moment.
   Share on Facebook (SCOPE §73) copies its data-t too, says "Copied, now paste", and still opens Facebook's share box (the link's own target).
   A calendar card opens its post below the calendar; the address remembers it, and a link to a keys side opens its game (SCOPE §87).
   Change opens a network's credentials on Accounts and email (SCOPE §87).
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
  /* open one post's panel and mark its card; false when the page has no such post */
  function pick(slot) {
    var panel = document.getElementById('sp-' + slot); if (!panel || !panel.classList.contains('sp-d')) return false;
    document.querySelectorAll('.sp-d.on').forEach(function (d) { d.classList.remove('on'); }); panel.classList.add('on');
    document.querySelectorAll('.sp-post[aria-current]').forEach(function (c) { c.removeAttribute('aria-current'); });
    document.querySelectorAll('.sp-post').forEach(function (c) { if (c.getAttribute('data-slot') === slot) { c.setAttribute('aria-current', 'true'); } });
    return panel;
  }
  function fromHash() {
    if (location.hash.indexOf('#sp-') !== 0) return;
    var id = decodeURIComponent(location.hash.slice(1)), el = document.getElementById(id); if (!el) return;
    if (el.classList.contains('sp-d')) { pick(id.slice(3)); el.scrollIntoView({ block: 'nearest' }); return; }
    var game = el.closest('.sp-game'); if (game) { game.open = true; el.scrollIntoView({ block: 'start' }); }  /* back from posting a keys side */
  }
  fromHash();
  window.addEventListener('hashchange', fromHash);
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target : null; if (!t) return;
    var cx = t.closest('.sp-apx');  /* Auto publish's Cancel (SCOPE §81) */
    if (cx) { var d = cx.closest('details'); if (d) { d.open = false; } return; }
    var chg = t.closest('.sp-chg');  /* a network's credentials (SCOPE §87) */
    if (chg) {
      var row = document.getElementById(chg.getAttribute('aria-controls')); if (!row) return;
      var open = row.classList.toggle('open'); chg.setAttribute('aria-expanded', open ? 'true' : 'false'); chg.textContent = open ? 'Close' : 'Change'; return;
    }
    var go = t.closest('a[href^="#sp-"]');  /* a calendar card, or a chart in Tuesday's list */
    if (go) {
      var panel = pick(go.getAttribute('href').slice(4)); if (!panel) return;
      e.preventDefault(); history.replaceState(null, '', go.getAttribute('href')); panel.scrollIntoView({ block: 'nearest' }); return;
    }
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
