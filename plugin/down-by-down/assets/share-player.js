/* Share player (SCOPE §103): the panel the toolbar's Share player item opens. Admins only; the page prints the panel and its data
   (#dbs-data) only for them. The stat buttons swap the graphic and the text; Share… is the device's share sheet where there is one;
   Post now posts to Down by Down's accounts one network a request (admin-ajax, dbd_share_post); the own-account buttons open each
   site's new post in a new tab and copy the graphic (Facebook: the text) to paste in. */
(function () {
  'use strict';
  var dlg = document.getElementById('dbd-share'); if (!dlg || !dlg.showModal) return;
  var D; try { D = JSON.parse(document.getElementById('dbs-data').textContent); } catch (e) { return; }
  if (!D.stats || !D.stats.length) return;
  var img = dlg.querySelector('.dbs-fig img'), ta = dlg.querySelector('#dbs-t'), msg = dlg.querySelector('.dbs-msg'), dl = dlg.querySelector('.dbs-dl'), st = dlg.querySelector('.dbs-st'), S = 0;
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function say(s) { msg.textContent = s; clearTimeout(say.t); say.t = setTimeout(function () { msg.textContent = ''; }, 5000); }
  function cur() { return D.stats[S]; }
  function paint() {
    var s = cur();
    img.src = s.img; img.alt = D.name + ': ' + s.lab + ', ' + s.v + ', ' + s.rank + ' of ' + s.n;
    ta.value = s.text + '\n' + D.url;
    dl.href = s.img; dl.setAttribute('download', s.file);
    dlg.querySelector('.dbs-stats').innerHTML = D.stats.map(function (x, i) { return '<button type="button" data-i="' + i + '" aria-pressed="' + (i === S) + '">' + esc(x.lab) + '<small>' + esc(x.rank) + '</small></button>'; }).join('');
  }
  dlg.querySelector('.dbs-stats').addEventListener('click', function (e) { var b = e.target.closest('button'); if (b) { S = +b.dataset.i; paint(); } });

  /* open from the toolbar item; close with ×, Esc or a click outside */
  function open(e) { if (e) e.preventDefault(); paint(); if (!dlg.open) dlg.showModal(); }
  document.addEventListener('click', function (e) { var a = e.target.closest && e.target.closest('#wp-admin-bar-dbd-share a, a[href="#dbd-share"]'); if (a) open(e); });
  dlg.querySelector('.dbs-x').addEventListener('click', function () { dlg.close(); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) dlg.close(); });

  /* the posted marks Down by Down's networks already have for him this week */
  D.nets.forEach(function (n) {
    var lab = dlg.querySelector('.dbs-net[data-net="' + n.n + '"]'); if (!lab) return;
    if (n.posted) lab.insertAdjacentHTML('beforeend', ' <span class="dbs-done">posted' + (n.posted !== '#' ? ' · <a href="' + esc(n.posted) + '" target="_blank" rel="noopener">View</a>' : '') + '</span>');
    else if (!n.on) lab.insertAdjacentHTML('beforeend', ' <span class="dbs-off">not connected</span>');
  });

  function copyText() {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(ta.value);
    ta.select(); var ok = document.execCommand('copy'); ta.setSelectionRange(0, 0); return ok ? Promise.resolve() : Promise.reject();
  }
  dlg.querySelector('.dbs-copy').addEventListener('click', function () { copyText().then(function () { say('Copied.'); }, function () { say('Couldn’t copy: select the text and copy it by hand.'); }); });

  /* Share…: the device's share sheet, shown only where it can take a file */
  var sh = dlg.querySelector('.dbs-share');
  try { if (navigator.canShare && navigator.canShare({ files: [new File(['x'], 'x.jpg', { type: 'image/jpeg' })] })) sh.hidden = false; } catch (e) {}
  function blob() { return fetch(cur().img, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('graphic'); return r.blob(); }); }
  sh.addEventListener('click', function () {
    var s = cur();
    blob().then(function (b) { return navigator.share({ files: [new File([b], s.file, { type: 'image/jpeg' })], text: ta.value }); })
      .catch(function (e) { if (e && e.name !== 'AbortError') say('Couldn’t open the share sheet: use Download graphic and Copy text.'); });
  });

  /* Post now: each ticked network in its own request, its line filled as it answers */
  var now = dlg.querySelector('.dbs-now');
  now.addEventListener('click', function () {
    var picks = [].slice.call(dlg.querySelectorAll('.dbs-nets input:checked:not(:disabled)')).map(function (x) { return x.value; });
    if (!picks.length) { st.innerHTML = '<li>Tick at least one network.</li>'; return; }
    var s = cur(), left = picks.length; now.disabled = true; st.innerHTML = '';
    picks.forEach(function (n) {
      var name = (D.nets.filter(function (x) { return x.n === n; })[0] || {}).name || n, li = document.createElement('li'); li.textContent = name + ': posting…'; st.appendChild(li);
      var f = new FormData(); f.append('action', 'dbd_share_post'); f.append('nonce', D.nonce); f.append('id', D.id); f.append('k', s.k); f.append('net', n);
      fetch(D.ajax, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
        if (j && j.success) {
          var l = j.data && j.data.link; li.innerHTML = esc(name) + ': <b>posted</b>' + (l ? ' · <a href="' + esc(l) + '" target="_blank" rel="noopener">View post</a>' : '');
          var box = dlg.querySelector('.dbs-net[data-net="' + n + '"] input'); if (box) { box.checked = false; box.disabled = true; }
        } else { li.innerHTML = esc(name) + ': <span class="dbs-fail">failed</span>, ' + esc((j && j.data && j.data.err) || 'no answer') + '. Tick it and press Post now to try again.'; }
      }).catch(function () { li.innerHTML = esc(name) + ': <span class="dbs-fail">no answer</span>. It may still have posted: reload before trying again.'; })
        .then(function () { if (--left === 0) now.disabled = false; });
    });
  });

  /* your own accounts: the site's new post in a new tab, the graphic copied to paste in (Facebook takes only the link, so it copies the text) */
  function png() {
    return blob().then(function (b) {
      return new Promise(function (ok, no) {
        var u = URL.createObjectURL(b), im = new Image();
        im.onload = function () { var c = document.createElement('canvas'); c.width = im.naturalWidth; c.height = im.naturalHeight; c.getContext('2d').drawImage(im, 0, 0); URL.revokeObjectURL(u); c.toBlob(function (p) { p ? ok(p) : no(); }, 'image/png'); };
        im.onerror = function () { URL.revokeObjectURL(u); no(); }; im.src = u;
      });
    });
  }
  dlg.querySelector('.dbs-own').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    var s = cur(), tx = s.text, u = D.url, net = b.dataset.net, lab = b.dataset.lab || (b.dataset.lab = b.textContent);
    var href = { x: 'https://x.com/intent/post?text=' + encodeURIComponent(tx) + '&url=' + encodeURIComponent(u), bsky: 'https://bsky.app/intent/compose?text=' + encodeURIComponent(tx + ' ' + u),
      th: 'https://www.threads.com/intent/post?text=' + encodeURIComponent(tx) + '&url=' + encodeURIComponent(u), fb: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(u) }[net];
    var w = window.open('about:blank', '_blank');  /* opened now, inside the click, so no popup blocker stops it */
    function go(t) { b.textContent = t; setTimeout(function () { b.textContent = lab; }, 3500); if (w) { try { w.opener = null; } catch (x) {} w.location = href; } else { window.open(href, '_blank', 'noopener'); } }
    if (net === 'fb') { copyText().then(function () { go('Text copied, paste it in'); }, function () { go(lab); }); return; }
    var p = (navigator.clipboard && window.ClipboardItem && window.isSecureContext) ? navigator.clipboard.write([new ClipboardItem({ 'image/png': png() })]) : Promise.reject();
    p.then(function () { go('Graphic copied, paste it in'); }, function () { dl.click(); go('Graphic downloaded, attach it'); });
  });

  if (location.hash === '#dbd-share') open();
})();
