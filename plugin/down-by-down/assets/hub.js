/* The hub's My team card (SCOPE §41): with a team saved on this device (dbd-myteam, as My team keeps it), draw each player with
   this week's opponent. The player list (players/mini.json, about 40 KB) is fetched only when a team exists. */
(function () {
  var C = window.DBD_HUB || {}, card = document.getElementById('hb-mt'); if (!card || !C.mini) return;
  var ids; try { ids = JSON.parse(localStorage.getItem('dbd-myteam') || '[]'); } catch (e) { ids = []; }
  ids = Array.isArray(ids) ? ids.filter(function (x) { return /^\d{2}-\d{7}$/.test(x); }) : [];
  if (!ids.length) return;
  if (C.teams && !window.DBD_TEAMS) window.DBD_TEAMS = C.teams;
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var PH = 'https://static.www.nfl.com/image/upload/f_auto,q_auto,w_48,h_48,c_fill,g_face/league/';
  var head = function (p) {
    var tile = window.dbdJersey ? window.dbdJersey(p[1], p[3], 24) : '';
    if (!C.heads || !p[4]) return tile;
    var src = /^https:\/\//.test(p[4]) ? p[4] : PH + p[4], t = (C.teams || {})[p[1]] || {};
    if (!/^https:\/\/[^"'<>\s]+$/.test(src)) return tile;
    return '<span class="dbd-head" style="width:24px;height:24px">' + tile + '<img src="' + esc(src) + '" alt="" width="24" height="24" loading="lazy" referrerpolicy="no-referrer" style="background:' + esc(t.c1 || '#161e36') + '" onerror="this.remove()"></span>';
  };
  fetch(C.mini, { credentials: 'omit' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (M) {
    if (!M) return;
    var rows = ids.filter(function (id) { return M[id]; }).slice(0, C.max || 20).map(function (id) {
      var p = M[id], o = (C.opp || {})[p[1]];
      var st = !o ? 'bye' : o[0] + ' ' + o[1] + (o[2] ? ' · final' : '');
      return '<li><span class="who">' + head(p) + '<span class="nm"><a href="' + esc(C.base + (C.base.indexOf('?') < 0 ? '?' : '&') + 'player=' + id) + '">' + esc(p[0]) + '</a><small>' + esc(p[1] + ' · ' + p[2]) + '</small></span></span><span class="hb-mto' + (o ? '' : ' bye') + '">' + esc(st) + '</span></li>';
    });
    if (!rows.length) return;
    var box = card.querySelector('.hb-mte'), sub = card.querySelector('.hb-mts');
    if (sub) sub.textContent = rows.length + ' player' + (rows.length === 1 ? '' : 's') + ' · ' + (C.week || 'this week');
    var ol = document.createElement('ol'); ol.className = 'ld-list hb-mtl'; ol.innerHTML = rows.join('');
    var more = document.createElement('a'); more.className = 'hb-more'; more.href = C.myteam; more.textContent = 'Open My team →';
    if (box) box.replaceWith(ol); card.appendChild(more);
  }).catch(function () {});
})();
