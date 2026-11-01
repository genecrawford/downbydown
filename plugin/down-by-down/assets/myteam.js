/* Down by Down: My team (SCOPE §36). The team is the address (?myteam=id,id,…), which the server renders; this keeps a copy on the
   device (localStorage dbd-myteam), restores it when the address is empty, and handles search, add, remove, copy and clear.
   A shared link that differs from your saved team is shown as it is, with a choice to save it, so opening a friend's team never
   overwrites yours. Config in DBD_MT. */
(function () {
  var C = window.DBD_MT, root = document.querySelector('.dbd-view-myteam');
  if (!C || !root) return;
  window.DBD_TEAMS = C.teams;
  var KEY = 'dbd-myteam', ids = (C.ids || []).slice();
  var read = function () { try { var v = JSON.parse(localStorage.getItem(KEY) || '[]'); return Array.isArray(v) ? v.filter(function (x) { return /^\d{2}-\d{7}$/.test(x); }) : []; } catch (e) { return []; } };
  var save = function (list) { try { if (list.length) localStorage.setItem(KEY, JSON.stringify(list)); else localStorage.removeItem(KEY); } catch (e) {} };
  var url = function (list) { var b = C.base + (C.base.indexOf('?') >= 0 ? '&' : '?'); return list.length ? b + 'myteam=' + list.join(',') : b + 'view=myteam'; };
  var go = function (list) { save(list); location.href = url(list); };
  var same = function (a, b) { return a.length === b.length && a.slice().sort().join() === b.slice().sort().join(); };
  var stored = read();

  /* an empty address restores the saved team; a given one is saved unless it is someone else's */
  if (!C.given) { if (stored.length) { location.replace(url(stored)); return; } }
  else if (ids.length) {
    if (!stored.length || same(stored, ids)) save(ids);
    else {
      var bar = document.createElement('div'); bar.className = 'dbd-panel mt-shared';
      bar.innerHTML = '<p><b>This is a shared team.</b> Your own saved team (' + stored.length + ' player' + (stored.length === 1 ? '' : 's') + ') is kept until you choose.</p><span><button type="button" class="dbd-cbtn mt-keep">Save this as my team</button><a class="dbd-cbtn" href="' + url(stored) + '">Open my team</a></span>';
      var main = root.querySelector('main'), title = main && main.querySelector('.dbd-ttitle');
      if (title) title.after(bar);
      bar.querySelector('.mt-keep').addEventListener('click', function () { save(ids); stored = ids.slice(); bar.remove(); });
    }
  }

  /* search: name, team or number, best scorers first, players already on the team left out */
  var src = document.getElementById('mt-index'), ALL = []; try { ALL = JSON.parse(src ? src.textContent : '[]'); } catch (e) {}
  var q = document.getElementById('mt-q'), list = document.getElementById('mt-sug'), hits = [], cur = -1;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function draw() {
    var n = (q.value || '').trim().toLowerCase(); cur = -1;
    if (!n) { list.hidden = true; list.innerHTML = ''; return; }
    hits = ALL.filter(function (p) { return ids.indexOf(p.id) < 0 && (p.name.toLowerCase().indexOf(n) >= 0 || (p.team || '').toLowerCase() === n || String(p.num) === n); })
      .sort(function (a, b) { return (b.fp || 0) - (a.fp || 0); }).slice(0, 8);
    list.innerHTML = hits.length ? hits.map(function (p, i) { return '<li role="option" id="mt-o' + i + '" data-i="' + i + '">' + (window.dbdMark ? window.dbdMark(p.team, 20) : '') + '<b>' + esc(p.name) + '</b><small>' + esc(p.team + ' · ' + p.pos + (p.fp != null ? ' · ' + p.fp.toFixed(1) + ' half-PPR' : '')) + '</small></li>'; }).join('') : '<li class="none">No players match</li>';
    list.hidden = false;
  }
  function pick(i) {
    var p = hits[i]; if (!p) return;
    if (ids.length >= C.max) { q.value = ''; list.innerHTML = '<li class="none">My team holds ' + C.max + ' players: remove one first</li>'; list.hidden = false; return; }
    go(ids.concat([p.id]));
  }
  function mark() { list.querySelectorAll('li[data-i]').forEach(function (li) { var on = +li.dataset.i === cur; li.classList.toggle('on', on); if (on) q.setAttribute('aria-activedescendant', li.id); }); }
  if (q && list) {
    q.addEventListener('input', draw);
    q.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' && hits.length) { cur = Math.min(hits.length - 1, cur + 1); mark(); e.preventDefault(); }
      else if (e.key === 'ArrowUp' && hits.length) { cur = Math.max(0, cur - 1); mark(); e.preventDefault(); }
      else if (e.key === 'Enter') { pick(cur < 0 ? 0 : cur); e.preventDefault(); }
      else if (e.key === 'Escape') { list.hidden = true; }
    });
    list.addEventListener('mousedown', function (e) { var li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });
    q.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; }, 150); });
  }

  /* remove, copy, clear */
  root.addEventListener('click', function (e) {
    var rm = e.target.closest('.mt-rm'); if (rm) { e.preventDefault(); go(ids.filter(function (x) { return x !== rm.dataset.id; })); return; }
    if (e.target.closest('#mt-copy')) {
      var b = e.target.closest('#mt-copy'), done = function () { b.textContent = 'Link copied'; setTimeout(function () { b.textContent = 'Copy link'; }, 1800); };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url(ids)).then(done, function () { window.prompt('Copy this link', url(ids)); });
      else window.prompt('Copy this link', url(ids));
      return;
    }
    if (e.target.closest('#mt-clear')) { if (window.confirm('Remove every player from My team on this device?')) { save([]); location.href = url([]); } }
  });
})();
