/* Down by Down: the Players index. The server renders one set of rows; this takes the inline index JSON and
   re-renders on team, position and search changes without a round trip. Config in DBD_PLIST. */
(function () {
  var C = window.DBD_PLIST, root = document.querySelector('.dbd-view-players'), src = document.getElementById('dbd-pindex');
  if (!C || !root || !src) return;
  window.DBD_TEAMS = C.teams;
  var ALL; try { ALL = JSON.parse(src.textContent || '[]'); } catch (e) { return; }
  var st = { team: C.team || '', pos: C.pos || '', q: C.q || '' }, sort = { key: '', dir: -1 };
  var LABEL = { snap: 'snap share', use: 'targets and attempts', rec: 'receptions', car: 'carries', yds: 'yards', td: 'touchdowns', epa: 'EPA', name: 'name', team: 'team', pos: 'position', st: 'status' };
  var val = function (p, k) { switch (k) { case 'name': return p.name.toLowerCase(); case 'team': return p.team || ''; case 'pos': return p.pos || ''; case 'st': return p.st || ''; case 'use': return (p.pos === 'QB' ? p.att : p.tgt) | 0; case 'epa': return +p.epa || 0; default: return p[k] | 0; } };
  var $ = function (s) { return root.querySelector(s); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var url = function (args) { var u = C.base + (C.base.indexOf('?') >= 0 ? '&' : '?') + 'view=players'; Object.keys(args).forEach(function (k) { if (args[k]) u += '&' + k + '=' + encodeURIComponent(args[k]); }); return u; };
  var purl = function (id) { return C.base + (C.base.indexOf('?') >= 0 ? '&' : '?') + 'player=' + encodeURIComponent(id); };
  var pos = $('#dbd-pos'), pick = $('#dbd-teampick'), q = $('#dbd-pq');

  function slidePill(bar, tab, animate) { var pill = bar.querySelector('.t-tabs-pill'); if (!pill || !tab) return; var go = function () { pill.style.transform = 'translateX(' + tab.offsetLeft + 'px)'; pill.style.width = tab.offsetWidth + 'px'; }; if (animate) { go(); return; } var prev = pill.style.transition; pill.style.transition = 'none'; go(); void pill.offsetWidth; pill.style.transition = prev; }
  requestAnimationFrame(function () { slidePill(pos, pos.querySelector('[aria-selected="true"]'), false); });
  window.addEventListener('resize', function () { slidePill(pos, pos.querySelector('[aria-selected="true"]'), false); });

  function status(p) { var s = p.st || ''; if (!s) return ''; var cls = /out|did not/i.test(s) ? ' o' : /questionable|limited|doubtful/i.test(s) ? ' q' : ''; var lbl = /did not/i.test(s) ? 'DNP' : /limited/i.test(s) ? 'Limited' : /full/i.test(s) ? 'Full' : s; return '<span class="dbd-st' + cls + '">' + esc(lbl + (p.what ? ' · ' + p.what : '')) + '</span>'; }
  function row(p) { var h = purl(p.id); return '<tr class="dbd-trow" data-href="' + esc(h) + '"><td class="dbd-nm"><span class="dbd-nmi">' + window.dbdPlayer(p.team, p.num, 34, p.photo, !!C.heads) + '<a href="' + esc(h) + '">' + esc(p.name) + '</a>' + (p.slot ? '<small>' + esc(p.slot) + '</small>' : '') + '</span></td><td class="dbd-ptm">' + window.dbdMark(p.team, 20) + '</td><td class="dbd-ppos">' + esc(p.pos) + '</td><td class="dbd-psnap"><span class="dbd-sb"><i style="--w:' + (p.snap | 0) + '%"></i></span>' + (p.snap | 0) + '%</td><td>' + ((p.pos === 'QB' ? p.att : p.tgt) || '') + '</td><td>' + (p.rec || '') + '</td><td>' + (p.car || '') + '</td><td>' + (p.yds | 0) + '</td><td>' + (p.td || '') + '</td><td class="' + (p.epa >= 0 ? 'pos' : 'neg') + '">' + (p.epa > 0 ? '+' : '') + (+p.epa).toFixed(1) + '</td><td>' + status(p) + '</td></tr>'; }

  function draw() {
    var needle = st.q.trim().toLowerCase();
    var rows = ALL.filter(function (p) { return (!st.team || needle || p.team === st.team) && (!st.pos || p.pos === st.pos || (st.pos === 'RB' && p.pos === 'FB')) && (!needle || p.name.toLowerCase().indexOf(needle) >= 0 || String(p.num) === needle || (p.team || '').toLowerCase() === needle || (p.col || '').toLowerCase().indexOf(needle) >= 0); });
    if (sort.key) rows.sort(function (a, b) { var x = val(a, sort.key), y = val(b, sort.key); return (x < y ? -1 : x > y ? 1 : 0) * sort.dir; });
    var cap = st.team || needle ? 0 : 60; if (cap && rows.length > cap) rows = rows.slice(0, cap);
    var by = sort.key ? LABEL[sort.key] : 'snap share';
    $('#dbd-plist tbody').innerHTML = rows.length ? rows.map(row).join('') : '<tr><td colspan="11" class="dbd-note">No players match.</td></tr>';
    var t = st.team && C.teams[st.team];
    $('#dbd-plab').innerHTML = needle ? '<b>Search</b> · ' + esc(st.q) + ' · all teams' : t ? '<b>' + esc(t.nick) + '</b> · sorted by ' + by : '<b>League leaders</b> · top ' + rows.length + ' by ' + by;
    root.querySelectorAll('#dbd-plist th[data-sort]').forEach(function (h) { if (h.dataset.sort === sort.key) h.setAttribute('aria-sort', sort.dir === 1 ? 'ascending' : 'descending'); else h.removeAttribute('aria-sort'); });
    pick.querySelectorAll('.dbd-mkbtn').forEach(function (b) { b.classList.toggle('on', (b.dataset.team || '') === st.team); });
    pos.querySelectorAll('.t-tab').forEach(function (b) { b.setAttribute('aria-selected', (b.dataset.v || '') === st.pos ? 'true' : 'false'); });
    slidePill(pos, pos.querySelector('[aria-selected="true"]'), true);
    try { history.replaceState(null, '', url({ team: st.team, pos: st.pos, q: st.q })); } catch (e) {}
  }
  var thead = $('#dbd-plist thead'); if (thead) thead.addEventListener('click', function (e) { var h = e.target.closest('th[data-sort]'); if (!h) return; var k = h.dataset.sort; if (sort.key === k) sort.dir *= -1; else { sort.key = k; sort.dir = (k === 'name' || k === 'team' || k === 'pos' || k === 'st') ? 1 : -1; } draw(); });
  pick.addEventListener('click', function (e) { var b = e.target.closest('.dbd-mkbtn'); if (!b) return; e.preventDefault(); st.team = b.dataset.team || ''; draw(); });
  pos.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; e.preventDefault(); st.pos = b.dataset.v || ''; draw(); });
  draw();
  var tm; q.addEventListener('input', function () { clearTimeout(tm); tm = setTimeout(function () { st.q = q.value; draw(); }, 120); });
})();
