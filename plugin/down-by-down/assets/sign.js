/* The signup strip (SCOPE §66). Someone who confirmed on this device (they landed on a post with ?dbd_joined=1) sees "You're on the list"
   instead of the form. Sending a signup form, the strip's or the one under a weekly post, tells Analytics: generate_lead with the page
   type, only when Site Kit's gtag is on the page. No dependencies. */
(function () {
  var on = false;
  try { on = localStorage.getItem('dbd-on-list') === '1'; } catch (e) {}
  if (/[?&]dbd_joined=1(&|$)/.test(location.search)) { on = true; try { localStorage.setItem('dbd-on-list', '1'); } catch (e) {} }
  if (on) [].forEach.call(document.querySelectorAll('.dbd-sign'), function (s) { s.classList.add('is-on'); });
  document.addEventListener('submit', function (e) {
    var f = e.target, box = f && f.closest ? f.closest('.dbd-sign, .dbd-post-sign') : null; if (!box) return;
    var em = f.querySelector('input[type="email"]'); if (em && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em.value || '')) return;  /* the form won't go: nothing to count */
    if (typeof window.gtag !== 'function') return;
    try { window.gtag('event', 'generate_lead', { form: 'signup', page_type: box.getAttribute('data-type') || 'post' }); } catch (x) {}
  }, true);
})();
