/* The Growth tab and the Overview's Growth box (SCOPE §65). Asks Site Kit for Analytics and Search Console numbers through its own REST
   routes, as the signed-in user (the way Site Kit's dashboard reads them), and fills the boxes the page drew. Each answer is kept in
   sessionStorage for an hour. Every figure is the last 28 days (ending yesterday) against the 28 before. No dependencies. */
(function () {
  var C = window.DBD_GROWTH; if (!C) return;
  var $ = function (k) { return document.querySelector('[data-g="' + k + '"]'); };
  if (!$('sources') && !$('ov-visits')) return;

  /* ---- dates */
  var day = function (d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); };
  var back = function (d, n) { var x = new Date(d.getFullYear(), d.getMonth(), d.getDate()); x.setDate(x.getDate() - n); return x; };
  var N = C.days || 28, end = back(new Date(), 1), start = back(end, N - 1), pEnd = back(start, 1), pStart = back(pEnd, N - 1);
  var cur = { startDate: day(start), endDate: day(end) }, prev = { startDate: day(pStart), endDate: day(pEnd) };
  var short = function (d) { return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }); };

  /* ---- Site Kit */
  var qs = function (o, pre) {
    return Object.keys(o).map(function (k) {
      var v = o[k], key = pre ? pre + '[' + k + ']' : k;
      return (v && typeof v === 'object') ? qs(v, key) : encodeURIComponent(key) + '=' + encodeURIComponent(v);
    }).join('&');
  };
  var ask = function (path, params) {
    var url = C.rest + path + '?' + qs(params), ck = 'dbd-g:' + C.v + ':' + url;
    try { var hit = JSON.parse(sessionStorage.getItem(ck) || 'null'); if (hit && Date.now() - hit.t < 3600e3) return Promise.resolve(hit.d); } catch (e) {}
    return fetch(url, { credentials: 'same-origin', headers: { 'X-WP-Nonce': C.nonce, 'Accept': 'application/json' } }).then(function (r) {
      return r.json().catch(function () { return null; }).then(function (j) {
        if (!r.ok) throw new Error((j && j.message) || ('Site Kit answered ' + r.status));
        try { sessionStorage.setItem(ck, JSON.stringify({ t: Date.now(), d: j })); } catch (e) {}
        return j;
      });
    });
  };
  var ga = function (p, range) { var o = { metrics: p.metrics }; if (p.dimensions) o.dimensions = p.dimensions; if (p.limit) o.limit = p.limit; if (p.orderby) o.orderby = p.orderby; o.startDate = range.startDate; o.endDate = range.endDate; return ask('analytics-4/data/report', o); };
  var sc = function (p, range) { var o = { startDate: range.startDate, endDate: range.endDate }; if (p.dimensions) o.dimensions = p.dimensions; if (p.limit) o.limit = p.limit; return ask('search-console/data/searchanalytics', o); };
  var rows = function (rep) { return ((rep && rep.rows) || []).map(function (r) { return { d: (r.dimensionValues || []).map(function (x) { return x.value; }), m: (r.metricValues || []).map(function (x) { return +x.value || 0; }) }; }); };

  /* ---- formatting */
  var esc = function (s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var num = function (n) { return Math.round(n).toLocaleString(); };
  var chg = function (a, b) {
    if (!b) return a ? '<span class="up">new</span>' : '<span class="flat">±0%</span>';
    var p = Math.round((a - b) / b * 100);
    return p === 0 ? '<span class="flat">±0%</span>' : '<span class="' + (p > 0 ? 'up' : 'down') + '">' + (p > 0 ? '+' : '−') + Math.abs(p) + '%</span>';
  };
  var fail = function (el, err, colspan) {
    if (!el) return;
    var t = '<span class="dbd-red">Site Kit didn’t answer: ' + esc(err && err.message || err) + '</span> The full report is a click away below.';
    el.innerHTML = colspan ? '<tr><td colspan="' + colspan + '">' + t + '</td></tr>' : '<p class="dbd-in">' + t + '</p>';
  };
  var path = function (u) { try { var x = new URL(u, C.home); return x.pathname + x.search; } catch (e) { return u; } };

  /* ---- visits by source */
  var CH = { 'Organic Search': 'Search', 'Direct': 'Direct', 'Organic Social': 'Social', 'Email': 'Email', 'Referral': 'Referral' };
  var ORDER = ['Search', 'Direct', 'Social', 'Email', 'Referral', 'Other'];
  var byChannel = function (rep) { var o = {}; rows(rep).forEach(function (r) { var k = CH[r.d[0]] || 'Other'; o[k] = (o[k] || 0) + r.m[0]; }); return o; };
  var chParams = { metrics: 'sessions', dimensions: 'sessionDefaultChannelGroup', limit: 50 };
  var visits = Promise.all([ga(chParams, cur), ga(chParams, prev)]).then(function (x) { return { a: byChannel(x[0]), b: byChannel(x[1]) }; });

  /* ---- Google search totals */
  var tot = function (rs) { var r = (rs && rs[0]) || {}; return { clicks: +r.clicks || 0, imp: +r.impressions || 0, ctr: +r.ctr || 0, pos: +r.position || 0 }; };
  var search = Promise.all([sc({}, cur), sc({}, prev)]).then(function (x) { return { a: tot(x[0]), b: tot(x[1]) }; });

  /* ---- the Overview box */
  if ($('ov-visits')) {
    visits.then(function (v) {
      var a = 0, b = 0; Object.keys(v.a).forEach(function (k) { a += v.a[k]; }); Object.keys(v.b).forEach(function (k) { b += v.b[k]; });
      $('ov-visits').innerHTML = num(a) + ' ' + chg(a, b) + (a ? ', ' + Math.round(100 * (v.a.Search || 0) / a) + '% from search' : '');
    }, function (e) { $('ov-visits').innerHTML = '<span class="dbd-red">' + esc(e.message) + '</span>'; });
    search.then(function (s) { $('ov-clicks').innerHTML = num(s.a.clicks) + ' ' + chg(s.a.clicks, s.b.clicks); },
      function (e) { $('ov-clicks').innerHTML = '<span class="dbd-red">' + esc(e.message) + '</span>'; });
  }
  if (!$('sources')) return;

  $('range').textContent = short(start) + ' – ' + short(end) + ', against ' + short(pStart) + ' – ' + short(pEnd);

  visits.then(function (v) {
    var a = 0, b = 0, max = 0;
    ORDER.forEach(function (k) { a += v.a[k] || 0; b += v.b[k] || 0; max = Math.max(max, v.a[k] || 0); });
    var h = ORDER.filter(function (k) { return k !== 'Other' || v.a.Other || v.b.Other; }).map(function (k) {
      var x = v.a[k] || 0;
      return '<tr><td>' + k + '</td><td class="w hide-sm"><span class="bar-c"><i style="width:' + (max ? Math.round(100 * x / max) : 0) + '%"></i></span></td><td class="n">' + num(x) + '</td><td class="n">' + chg(x, v.b[k] || 0) + '</td></tr>';
    }).join('');
    $('sources').innerHTML = '<table class="g-t"><thead><tr><th>Source</th><th class="hide-sm"></th><th class="n">Visits</th><th class="n">Change</th></tr></thead><tbody>' + h
      + '<tr><td><b>All</b></td><td class="hide-sm"></td><td class="n"><b>' + num(a) + '</b></td><td class="n"><b>' + chg(a, b) + '</b></td></tr></tbody></table>';
  }, function (e) { fail($('sources'), e); });

  search.then(function (s) {
    var pts = Math.round((s.a.ctr - s.b.ctr) * 1000) / 10, pos = s.a.pos && s.b.pos ? Math.round((s.b.pos - s.a.pos) * 10) / 10 : 0;
    $('search').innerHTML = '<tr><td>Clicks</td><td>' + num(s.a.clicks) + ' ' + chg(s.a.clicks, s.b.clicks) + '</td></tr>'
      + '<tr><td>Impressions</td><td>' + num(s.a.imp) + ' ' + chg(s.a.imp, s.b.imp) + '</td></tr>'
      + '<tr><td>Click rate</td><td>' + (Math.round(s.a.ctr * 1000) / 10) + '% ' + (s.b.imp ? '<span class="' + (pts > 0 ? 'up' : pts < 0 ? 'down' : 'flat') + '">' + (pts > 0 ? '+' : pts < 0 ? '−' : '±') + Math.abs(pts) + ' pts</span>' : '') + '</td></tr>'
      + '<tr><td>Avg. position</td><td>' + (s.a.pos ? (Math.round(s.a.pos * 10) / 10) : '–') + (s.b.pos ? ' <span class="' + (pos > 0 ? 'up' : pos < 0 ? 'down' : 'flat') + '">from ' + (Math.round(s.b.pos * 10) / 10) + '</span>' : '') + '</td></tr>';
  }, function (e) { fail($('search'), e, 2); });

  /* ---- shares and signups: the events this plugin sends. The method breakdown needs Analytics' "method" dimension; without it, totals. */
  var ev = function (rep) { var o = {}; rows(rep).forEach(function (r) { var n = r.d[0], m = r.d.length > 1 ? r.d[1] : ''; if (n !== 'share' && n !== 'sign_up' && n !== 'generate_lead') return; o[n] = o[n] || { all: 0 }; o[n].all += r.m[0]; if (m && m !== '(not set)') o[n][m] = (o[n][m] || 0) + r.m[0]; }); return o; };
  ga({ metrics: 'eventCount', dimensions: 'eventName,method', limit: 200 }, cur).catch(function () { return ga({ metrics: 'eventCount', dimensions: 'eventName', limit: 200 }, cur); }).then(function (rep) {
    var o = ev(rep), s = o.share || { all: 0 }, u = o.sign_up || { all: 0 }, g = o.generate_lead || { all: 0 };
    var parts = [['share_sheet', 'share sheet'], ['image', 'card image'], ['copy_link', 'copied links']].filter(function (x) { return s[x[0]]; }).map(function (x) { return num(s[x[0]]) + ' ' + x[1]; });
    $('shares').innerHTML = num(s.all) + (parts.length ? ': ' + parts.join(', ') : '') + (s.all ? '' : ' <span class="description">(counted from version 2.25.0)</span>');
    $('signups').innerHTML = num(u.all) + ' confirmed on the site' + (g.all ? ', from ' + num(g.all) + ' form' + (g.all === 1 ? '' : 's') + ' sent' : '') + (u.all || g.all ? '' : ' <span class="description">(counted from version 2.25.0)</span>');  /* forms sent: SCOPE §66 */
  }, function (e) { $('shares').innerHTML = $('signups').innerHTML = '<span class="dbd-red">' + esc(e.message) + '</span>'; });

  /* ---- titles to tune: pages Google showed at least 100 times with a click rate under the site's, the most seen first */
  var tuneText = '';
  Promise.all([sc({ dimensions: 'page', limit: 1000 }, cur), search]).then(function (x) {
    var site = x[1].a.ctr;
    var list = (x[0] || []).filter(function (r) { return r.impressions >= 100 && r.ctr < site; }).sort(function (a, b) { return b.impressions - a.impressions; }).slice(0, 5);
    if (!list.length) { $('tune').innerHTML = '<tr><td colspan="4">Nothing yet: no page Google showed at least 100 times is below the site’s click rate.</td></tr>'; return; }
    var parse = new DOMParser();
    return Promise.all(list.map(function (r) {
      var p = path(r.keys[0]);
      return fetch(p, { credentials: 'omit' }).then(function (res) { return res.ok ? res.text() : ''; }).then(function (t) {
        var d = t ? parse.parseFromString(t, 'text/html') : null; return d && d.title ? d.title.replace(/\s*\|\s*Down by Down\s*$/, '') : '';
      }, function () { return ''; }).then(function (title) { return { p: p, t: title, r: r }; });
    })).then(function (L) {
      $('tune').innerHTML = L.map(function (x) {
        return '<tr><td class="pg"><a href="' + esc(x.p) + '" target="_blank" rel="noopener">' + esc(x.p) + '</a>' + (x.t ? '<small>' + esc(x.t) + '</small>' : '') + '</td><td class="n">' + num(x.r.impressions) + '</td><td class="n">' + num(x.r.clicks) + '</td><td class="n down">' + (Math.round(x.r.ctr * 1000) / 10) + '%</td></tr>';
      }).join('');
      tuneText = 'Down by Down, titles to tune (' + short(start) + ' – ' + short(end) + '; the site\'s click rate is ' + (Math.round(site * 1000) / 10) + '%):\n'
        + L.map(function (x) { return x.p + ' | ' + (x.t || '(no title)') + ' | seen ' + x.r.impressions + ', clicked ' + x.r.clicks + ', ' + (Math.round(x.r.ctr * 1000) / 10) + '%'; }).join('\n');
      var b = $('copy'); if (b) b.disabled = false;
    });
  }).catch(function (e) { fail($('tune'), e, 4); });
  var cb = $('copy');
  if (cb) cb.addEventListener('click', function () {
    if (!tuneText) return; var done = function () { var t0 = cb.textContent; cb.textContent = 'Copied'; setTimeout(function () { cb.textContent = t0; }, 1600); };
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(tuneText).then(done, function () { window.prompt('Copy this', tuneText); });
    else window.prompt('Copy this', tuneText);
  });

  /* ---- top landing pages, with the change from the 28 days before */
  var lp = function (n) { return { metrics: 'sessions', dimensions: 'landingPage', limit: n, orderby: [{ metric: { metricName: 'sessions' }, desc: true }] }; };
  Promise.all([ga(lp(12), cur), ga(lp(500), prev)]).then(function (x) {
    var before = {}; rows(x[1]).forEach(function (r) { before[r.d[0]] = r.m[0]; });
    var L = rows(x[0]).filter(function (r) { return r.d[0] && r.d[0] !== '(not set)'; }).slice(0, 10);
    $('landing').innerHTML = L.length ? L.map(function (r) {
      return '<tr><td class="pg"><a href="' + esc(r.d[0]) + '" target="_blank" rel="noopener">' + esc(r.d[0]) + '</a></td><td class="n">' + num(r.m[0]) + '</td><td class="n">' + chg(r.m[0], before[r.d[0]] || 0) + '</td></tr>';
    }).join('') : '<tr><td colspan="3">No visits in these 28 days.</td></tr>';
  }, function (e) { fail($('landing'), e, 3); });
})();
