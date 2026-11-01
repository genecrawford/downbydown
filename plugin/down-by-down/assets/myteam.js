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
  var url = function (list) { var b = C.base + (C.base.indexOf('?') >= 0 ? '&' : '?'); return list.length ? b + 'myteam=' + list.join('%2C') : b + 'view=myteam'; };  /* %2C: WordPress redirects a bare comma to it, an extra round trip */
  var empty = function () { return C.base + (C.base.indexOf('?') >= 0 ? '&' : '?') + 'myteam=none'; };  /* an emptied shared team stays empty instead of restoring the saved one (Greptile, PR #40) */
  var shared = false;  /* a shared team being viewed: edits stay in the address until the reader saves it (Greptile, PR #40) */
  var go = function (list) { if (!shared) save(list); location.href = shared && !list.length ? empty() : url(list); };
  var same = function (a, b) { return a.length === b.length && a.slice().sort().join() === b.slice().sort().join(); };
  var stored = read();

  /* an empty address restores the saved team; a given one is saved unless it is someone else's */
  if (!C.given) { if (stored.length) { location.replace(url(stored)); return; } }
  else if (ids.length && (!stored.length || same(stored, ids))) save(ids);
  else if (stored.length && !same(stored, ids)) {
    /* someone else's team, or an emptied one: shown as it is, and the saved team is kept until the reader chooses (Greptile, PR #40) */
    shared = true;
    var bar = document.createElement('div'); bar.className = 'dbd-panel mt-shared';
    bar.innerHTML = '<p><b>' + (ids.length ? 'This is a shared team.' : 'This team is empty.') + '</b> Your own saved team (' + stored.length + ' player' + (stored.length === 1 ? '' : 's') + ') is kept until you choose.</p><span>' + (ids.length ? '<button type="button" class="dbd-cbtn mt-keep">Save this as my team</button>' : '') + '<a class="dbd-cbtn" href="' + url(stored) + '">Open my team</a></span>';
    var main = root.querySelector('main'), title = main && main.querySelector('.dbd-ttitle');
    if (title) title.after(bar);
    var keep = bar.querySelector('.mt-keep'); if (keep) keep.addEventListener('click', function () { save(ids); stored = ids.slice(); shared = false; bar.remove(); });
  }

  /* search: name, team or number, best scorers first, players already on the team left out. The main box takes any position;
     an empty slot's box (opened by its +) takes only the positions that slot holds. */
  var src = document.getElementById('mt-index'), ALL = []; try { ALL = JSON.parse(src ? src.textContent : '[]'); } catch (e) {}
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var uid = 0;
  function wire(q, list, posOk, onEmptyBlur) {
    var hits = [], cur = -1, pre = 'mt-o' + (uid++) + '-';
    function draw() {
      var n = (q.value || '').trim().toLowerCase(); cur = -1;
      if (!n) { hits = []; list.hidden = true; list.innerHTML = ''; return; }
      hits = ALL.filter(function (p) { return ids.indexOf(p.id) < 0 && (!posOk || posOk.indexOf(p.pos) >= 0) && (p.name.toLowerCase().indexOf(n) >= 0 || (p.team || '').toLowerCase() === n || String(p.num) === n); })
        .sort(function (a, b) { return (b.fp || 0) - (a.fp || 0); }).slice(0, 8);
      list.innerHTML = hits.length ? hits.map(function (p, i) { return '<li role="option" id="' + pre + i + '" data-i="' + i + '">' + (window.dbdMark ? window.dbdMark(p.team, 20) : '') + '<b>' + esc(p.name) + '</b><small>' + esc(p.team + ' · ' + p.pos + (p.fp != null ? ' · ' + p.fp.toFixed(1) + ' half-PPR' : '')) + '</small></li>'; }).join('') : '<li class="none">No players match</li>';
      list.hidden = false;
    }
    function pick(i) {
      var p = hits[i]; if (!p) return;
      if (ids.length >= C.max) { q.value = ''; hits = []; list.innerHTML = '<li class="none">My team holds ' + C.max + ' players: remove one first</li>'; list.hidden = false; return; }
      go(ids.concat([p.id]));
    }
    function mark() { list.querySelectorAll('li[data-i]').forEach(function (li) { var on = +li.dataset.i === cur; li.classList.toggle('on', on); if (on) q.setAttribute('aria-activedescendant', li.id); }); }
    q.addEventListener('input', draw);
    q.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' && hits.length) { cur = Math.min(hits.length - 1, cur + 1); mark(); e.preventDefault(); }
      else if (e.key === 'ArrowUp' && hits.length) { cur = Math.max(0, cur - 1); mark(); e.preventDefault(); }
      else if (e.key === 'Enter') { e.preventDefault(); if (!list.hidden && hits.length) pick(cur < 0 ? 0 : cur); }  /* nothing shown, nothing added */
      else if (e.key === 'Escape') { list.hidden = true; if (onEmptyBlur) { q.value = ''; onEmptyBlur(true); } }
    });
    list.addEventListener('mousedown', function (e) { var li = e.target.closest('li[data-i]'); if (li) { e.preventDefault(); pick(+li.dataset.i); } });
    q.addEventListener('blur', function () { setTimeout(function () { list.hidden = true; if (onEmptyBlur && !q.value.trim()) onEmptyBlur(); }, 150); });
  }
  var q = document.getElementById('mt-q'), list = document.getElementById('mt-sug');
  if (q && list) wire(q, list, null, null);

  /* empty slots (SCOPE §36 addendum): the + opens that slot's search in the row; Escape, or leaving it empty, puts the + back */
  root.querySelectorAll('.mt-row.empty[data-pos]').forEach(function (row) {
    var btn = row.querySelector('.mt-slotadd'), lab = row.querySelector('.mt-slot > .m'), box = row.querySelector('.mt-slotq'), inp = row.querySelector('.mt-slotin'), sug = box && box.querySelector('.mt-sug');
    if (!btn || !box || !inp || !sug) return;
    var close = function (refocus) { if (box.hidden) return; box.hidden = true; btn.hidden = false; if (lab) lab.hidden = false; btn.setAttribute('aria-expanded', 'false'); row.classList.remove('open'); if (refocus) btn.focus(); };  /* Escape hands focus back to the + (Greptile, PR #41) */
    wire(inp, sug, row.dataset.pos.split(','), close);
    btn.addEventListener('click', function () { btn.hidden = true; if (lab) lab.hidden = true; box.hidden = false; btn.setAttribute('aria-expanded', 'true'); row.classList.add('open'); inp.focus(); });
  });

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
