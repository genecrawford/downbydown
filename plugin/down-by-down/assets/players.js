/* Down by Down: the Players index. The server renders one set of rows; this takes the inline index JSON and
   re-renders on team, position and search changes without a round trip. Config in DBD_PLIST. */
(function () {
  var C = window.DBD_PLIST, root = document.querySelector('.dbd-view-players'), src = document.getElementById('dbd-pindex');
  if (!C || !root || !src) return;
  window.DBD_TEAMS = C.teams;
  var ALL; try { ALL = JSON.parse(src.textContent || '[]'); } catch (e) { return; }
  var st = { team: C.team || '', pos: C.pos || '', q: C.q || '' }, sort = { key: '', dir: -1 }, PAGE = 50, lim = PAGE;  /* SCOPE §31: 50 rows, then more */
  var byId = {}; ALL.forEach(function (p) { byId[p.id] = p; });
  var LABEL = { snap: 'snap share', use: 'targets and attempts', rec: 'receptions', car: 'carries', yds: 'yards', td: 'touchdowns', epa: 'EPA', fp: 'fantasy points a game', name: 'name', team: 'team', pos: 'position', st: 'status' };
  var val = function (p, k) { switch (k) { case 'name': return p.name.toLowerCase(); case 'team': return p.team || ''; case 'pos': return p.pos || ''; case 'st': return p.st || ''; case 'use': return (p.pos === 'QB' ? p.att : p.tgt) | 0; case 'epa': return +p.epa || 0; case 'fp': return p.fp ? +p.fp[FXI[fx()]] : -1; default: return p[k] | 0; } };
  var FXI = { std: 0, half: 1, ppr: 2 }, FXK = ['std', 'half', 'ppr'], fx = function () { return (window.dbdFx && window.dbdFx()) || 'half'; };  /* SCOPE §34: the remembered scoring format, from dbd-site.js */
  function fpCell(p) { if (!p.fp) return '<td class="dbd-pfp" data-v="">–</td>'; return '<td class="dbd-pfp" data-fv="' + p.fp.join(',') + '" data-v="' + p.fp[FXI[fx()]] + '">' + FXK.map(function (k, i) { var rk = p.fr && p.fr[i]; return '<span data-fx="' + k + '">' + (+p.fp[i]).toFixed(1) + (rk ? '<small>' + esc((p.fpos || '') + rk) + '</small>' : '') + '</span>'; }).join('') + '</td>'; }
  var $ = function (s) { return root.querySelector(s); };
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var url = function (args) { var u = C.base + (C.base.indexOf('?') >= 0 ? '&' : '?') + 'view=players'; Object.keys(args).forEach(function (k) { if (args[k]) u += '&' + k + '=' + encodeURIComponent(args[k]); }); return u; };
  var purl = function (id) { return C.base + (C.base.indexOf('?') >= 0 ? '&' : '?') + 'player=' + encodeURIComponent(id); };
  var pos = $('#dbd-pos'), pick = $('#dbd-teampick'), q = $('#dbd-pq');

  function slidePill(bar, tab, animate) { var pill = bar.querySelector('.t-tabs-pill'); if (!pill || !tab) return; var go = function () { pill.style.transform = 'translateX(' + tab.offsetLeft + 'px)'; pill.style.width = tab.offsetWidth + 'px'; }; if (animate) { go(); return; } var prev = pill.style.transition; pill.style.transition = 'none'; go(); void pill.offsetWidth; pill.style.transition = prev; }
  requestAnimationFrame(function () { slidePill(pos, pos.querySelector('[aria-selected="true"]'), false); });
  window.addEventListener('resize', function () { slidePill(pos, pos.querySelector('[aria-selected="true"]'), false); });

  function status(p) { var s = p.st || ''; if (!s) return ''; var cls = /out|did not/i.test(s) ? ' o' : /questionable|limited|doubtful/i.test(s) ? ' q' : ''; var lbl = /did not/i.test(s) ? 'DNP' : /limited/i.test(s) ? 'Limited' : /full/i.test(s) ? 'Full' : s; return '<span class="dbd-st' + cls + '">' + esc(lbl + (p.what ? ' · ' + p.what : '')) + '</span>'; }
  function stdot(p) { var s = p.st || ''; if (!s) return ''; var cls = /out|did not/i.test(s) ? ' o' : /questionable|limited|doubtful/i.test(s) ? ' q' : ''; var lbl = /did not/i.test(s) ? 'DNP' : /limited/i.test(s) ? 'Limited' : /full/i.test(s) ? 'Full' : s; return '<i class="dbd-stdot' + cls + '" data-tip="' + esc(lbl + (p.what ? ' · ' + p.what : '')) + '" tabindex="0" aria-label="' + esc(lbl) + '"></i>'; }  /* phones: the Status column is hidden, the dot carries it */
  function row(p) { var h = purl(p.id); return '<tr class="dbd-trow" data-href="' + esc(h) + '"><td class="dbd-nm"><span class="dbd-nmi">' + window.dbdPlayer(p.team, p.num, 34, p.photo, !!C.heads) + '<a href="' + esc(h) + '">' + esc(p.name) + '</a>' + (p.slot ? '<small>' + esc(p.slot) + '</small>' : '') + stdot(p) + '</span></td><td class="dbd-ptm">' + window.dbdMark(p.team, 20) + '</td><td class="dbd-ppos">' + esc(p.pos) + '</td><td class="dbd-psnap"><span class="dbd-sb"><i style="--w:' + (p.snap | 0) + '%"></i></span>' + (p.snap | 0) + '%</td><td>' + ((p.pos === 'QB' ? p.att : p.tgt) || '') + '</td><td>' + (p.rec || '') + '</td><td>' + (p.car || '') + '</td><td>' + (p.yds | 0) + '</td><td>' + (p.td || '') + '</td><td class="' + (p.epa >= 0 ? 'pos' : 'neg') + '">' + (p.epa > 0 ? '+' : '') + (+p.epa).toFixed(1) + '</td>' + fpCell(p) + '<td>' + status(p) + '</td></tr>'; }

  var PT = [null, 'dbd-t2', null, 'dbd-t2', null, 'dbd-t2', 'dbd-t2', null, null, null, null, 'dbd-t3'];  /* column tiers, same as the PHP header (SCOPE §20) */
  function tierize(html, tiers) {
    return html.replace(/<tr\b[^>]*>[\s\S]*?<\/tr>/g, function (r) { var i = -1; return r.replace(/<t([dh])\b([^>]*)>/g, function (m, tag, attrs) { i++; var t = tiers[i]; if (!t) return m; var cm = attrs.match(/\bclass="([^"]*)"/); attrs = cm ? attrs.replace(cm[0], 'class="' + t + ' ' + cm[1] + '"') : attrs + ' class="' + t + '"'; return '<t' + tag + attrs + '>'; }); });
  }
  function draw() {
    var needle = st.q.trim().toLowerCase();
    var posOk = function (p) { return !st.pos || p.pos === st.pos || (st.pos === 'RB' && p.pos === 'FB'); };
    var qOk = function (p) { return !needle || p.name.toLowerCase().indexOf(needle) >= 0 || String(p.num) === needle || (p.team || '').toLowerCase() === needle || (p.col || '').toLowerCase().indexOf(needle) >= 0; };
    var rows = ALL.filter(function (p) { return (!st.team || needle || p.team === st.team) && posOk(p) && qOk(p); });
    if (sort.key) rows.sort(function (a, b) { var x = val(a, sort.key), y = val(b, sort.key); return (x < y ? -1 : x > y ? 1 : 0) * sort.dir; });
    var by = sort.key ? LABEL[sort.key] : 'snap share';
    $('#dbd-plist tbody').innerHTML = rows.length ? tierize(rows.slice(0, lim).map(row).join(''), PT) : '<tr><td colspan="12" class="dbd-note">No players match.</td></tr>';
    var more = $('#dbd-plmore'), left = rows.length - Math.min(lim, rows.length);
    if (more) { more.hidden = left <= 0; more.querySelector('.n').textContent = 'Show ' + Math.min(PAGE, left) + ' more'; more.querySelector('.all').textContent = 'Show all ' + rows.length; more.querySelector('.of').textContent = Math.min(lim, rows.length) + ' of ' + rows.length; }
    var t = st.team && C.teams[st.team];
    $('#dbd-plab').innerHTML = needle ? '<b>Search</b> · ' + esc(st.q) + ' · all teams' : t ? '<b>' + esc(t.nick) + '</b> · sorted by ' + by : '<b>Every player</b> · ' + rows.length + ' by ' + by;
    root.querySelectorAll('#dbd-plist th[data-sort]').forEach(function (h) { if (h.dataset.sort === sort.key) h.setAttribute('aria-sort', sort.dir === 1 ? 'ascending' : 'descending'); else h.removeAttribute('aria-sort'); });
    pick.querySelectorAll('.dbd-mkbtn').forEach(function (b) { b.classList.toggle('on', (b.dataset.team || '') === st.team); });
    pos.querySelectorAll('.t-tab').forEach(function (b) { b.setAttribute('aria-selected', (b.dataset.v || '') === st.pos ? 'true' : 'false'); });
    slidePill(pos, pos.querySelector('[aria-selected="true"]'), true);
    try { history.replaceState(null, '', url({ pt: st.team, pos: st.pos, q: st.q })); } catch (e) {}  /* pt=, not team=: ?view=players&team= is the team's own page (1.9.0) */
    /* movers (SCOPE §22): the team tile marks its rows, position and search dim the rest */
    root.querySelectorAll('#dbd-mv [data-id]').forEach(function (el) {
      el.classList.toggle('hi', !!st.team && el.dataset.team === st.team);
      var p = byId[el.dataset.id] || { name: '', num: '', team: el.dataset.team, pos: el.dataset.pos, col: '' };
      el.classList.toggle('dim', !(posOk(p) && qOk(p)));
    });
  }
  var thead = $('#dbd-plist thead'); if (thead) thead.addEventListener('click', function (e) { var h = e.target.closest('th[data-sort]'); if (!h) return; var k = h.dataset.sort; if (sort.key === k) sort.dir *= -1; else { sort.key = k; sort.dir = (k === 'name' || k === 'team' || k === 'pos' || k === 'st') ? 1 : -1; } lim = PAGE; draw(); });
  pick.addEventListener('click', function (e) { var b = e.target.closest('.dbd-mkbtn'); if (!b) return; e.preventDefault(); st.team = b.dataset.team || ''; lim = PAGE; draw(); });
  pos.addEventListener('click', function (e) { var b = e.target.closest('.t-tab'); if (!b) return; e.preventDefault(); st.pos = b.dataset.v || ''; lim = PAGE; draw(); });
  /* the list grows on request: a page of 50, or all of it; a new filter or sort starts again at 50 */
  var tw = $('#dbd-plist') && $('#dbd-plist').closest('.dbd-tw');
  if (tw) { var mb = document.createElement('div'); mb.className = 'dbd-plmore'; mb.id = 'dbd-plmore'; mb.innerHTML = '<span class="of"></span><button type="button" class="n"></button><button type="button" class="all"></button>'; tw.after(mb);
    mb.querySelector('.n').addEventListener('click', function () { lim += PAGE; draw(); }); mb.querySelector('.all').addEventListener('click', function () { lim = Infinity; draw(); }); draw(); }
  draw();
  document.addEventListener('dbd:fx', function () { if (sort.key === 'fp') draw(); });  /* a new format re-sorts a fantasy sort */
  var tm; q.addEventListener('input', function () { clearTimeout(tm); tm = setTimeout(function () { st.q = q.value; lim = PAGE; draw(); }, 120); });
})();
