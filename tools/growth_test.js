/* growth.js against canned Site Kit answers (2.25.0, SCOPE §65; Greptile, PR #119). Runs the script in a stand-in page with a fake fetch,
   then checks the requests it made (routes and arguments) and the figures it drew. A second run has Site Kit refuse every read and
   checks each box says so. Usage: node tools/growth_test.js plugin/down-by-down/assets/growth.js */
const fs = require('fs'), vm = require('vm');
const src = fs.readFileSync(process.argv[2] || 'plugin/down-by-down/assets/growth.js', 'utf8');
let fails = 0; const t = (what, ok, got) => { if (!ok) fails++; console.log((ok ? '  ok   ' : '  FAIL ') + what + (ok ? '' : ': ' + got)); };

const ga = (rows) => ({ rows: rows.map(([d, m]) => ({ dimensionValues: d.map((v) => ({ value: v })), metricValues: m.map((v) => ({ value: String(v) })) })) });
const range = (q) => q.get('startDate') + '..' + q.get('endDate');

function run(refuse) {
  const els = {}, keys = ['range', 'sources', 'search', 'shares', 'signups', 'tune', 'copy', 'landing', 'ov-visits', 'ov-clicks'];
  keys.forEach((k) => { els[k] = { innerHTML: '', textContent: '', disabled: true, addEventListener() {} }; });
  const reqs = []; let cur = null;
  const answer = (u) => {
    const q = u.searchParams, p = u.pathname, r = range(q);
    if (!cur) cur = r;  /* the first range asked is the current one */
    const now = r === cur;
    if (p.endsWith('analytics-4/data/report')) {
      const d = q.get('dimensions');
      if (d === 'sessionDefaultChannelGroup') return now ? ga([[['Organic Search'], [2318]], [['Direct'], [946]], [['Organic Social'], [402]], [['Email'], [214]], [['Referral'], [118]], [['Unassigned'], [10]]]) : ga([[['Organic Search'], [1770]], [['Direct'], [876]], [['Organic Social'], [457]], [['Email'], [130]], [['Referral'], [118]]]);
      if (d === 'eventName,method') return ga([[['share', 'share_sheet'], [38]], [['share', 'image'], [17]], [['share', 'copy_link'], [8]], [['sign_up', 'email'], [41]], [['page_view', '(not set)'], [9000]]]);
      if (d === 'landingPage') return now ? ga([[['/'], [612]], [['(not set)'], [50]], [['/draft/2026/'], [241]]]) : ga([[['/'], [546]]]);
    }
    if (p.endsWith('search-console/data/searchanalytics')) {
      if (q.get('dimensions') === 'page') return [{ keys: ['https://example.test/team/kc/defense/'], clicks: 31, impressions: 6210, ctr: 0.005, position: 9 }, { keys: ['https://example.test/'], clicks: 900, impressions: 9000, ctr: 0.1, position: 3 }, { keys: ['https://example.test/tiny/'], clicks: 0, impressions: 40, ctr: 0, position: 50 }];
      return now ? [{ clicks: 2107, impressions: 88430, ctr: 0.0238, position: 18.6 }] : [{ clicks: 1633, impressions: 61410, ctr: 0.0266, position: 22.1 }];
    }
    return null;
  };
  const fetch = (url, opt) => {
    const u = new URL(url, 'https://example.test/wp-admin/');
    if (!u.pathname.includes('google-site-kit')) {  /* a page's title for Titles to tune */
      return Promise.resolve({ ok: true, text: () => Promise.resolve('<title>Chiefs defense 2026: stats | Down by Down</title>') });
    }
    reqs.push({ u, nonce: opt && opt.headers && opt.headers['X-WP-Nonce'] });
    const body = refuse ? { code: 'missing_required_scopes', message: 'You’ll need to grant Site Kit permission' } : answer(u);
    return Promise.resolve({ ok: !refuse && body !== null, status: refuse ? 403 : 200, json: () => Promise.resolve(body) });
  };
  const store = {};
  const ctx = {
    window: { DBD_GROWTH: { rest: 'https://example.test/wp-json/google-site-kit/v1/modules/', nonce: 'N1', home: 'https://example.test/', days: 28, v: 'test' } },
    document: { querySelector: (s) => { const m = /data-g="([^"]+)"/.exec(s); return m ? els[m[1]] || null : null; } },
    sessionStorage: { getItem: (k) => store[k] || null, setItem: (k, v) => { store[k] = v; } },
    fetch, URL, Date, Math, JSON, Promise, Object, String, encodeURIComponent,
    DOMParser: function () { this.parseFromString = (h) => ({ title: (/<title>([^<]*)<\/title>/.exec(h) || [])[1] || '' }); },
    navigator: {},
  };
  ctx.window.document = ctx.document;
  vm.runInNewContext(src, ctx);
  return new Promise((res) => setTimeout(() => res({ els, reqs }), 50));
}

(async () => {
  const { els, reqs } = await run(false);
  const has = (p, f) => reqs.some((r) => r.u.pathname.endsWith(p) && f(r.u.searchParams));
  t('every Site Kit read carries the nonce', reqs.length > 0 && reqs.every((r) => r.nonce === 'N1'), reqs.length);
  t('channels asked for both 28-day windows', reqs.filter((r) => r.u.searchParams.get('dimensions') === 'sessionDefaultChannelGroup').length === 2);
  t('landing pages ordered by sessions, biggest first', has('analytics-4/data/report', (q) => q.get('dimensions') === 'landingPage' && q.get('orderby[0][metric][metricName]') === 'sessions' && q.get('orderby[0][desc]') === 'true'));
  t('search totals asked with no dimension', has('search-console/data/searchanalytics', (q) => !q.get('dimensions') && q.get('startDate')));
  const sp = reqs.find((r) => r.u.searchParams.get('dimensions') === 'page');
  t('the 28-day windows are 28 days each and back to back', (() => {
    const ch = reqs.filter((r) => r.u.searchParams.get('dimensions') === 'sessionDefaultChannelGroup').map((r) => [new Date(r.u.searchParams.get('startDate')), new Date(r.u.searchParams.get('endDate'))]);
    const len = (x) => Math.round((x[1] - x[0]) / 864e5) + 1; return ch.length === 2 && len(ch[0]) === 28 && len(ch[1]) === 28 && Math.round((ch[0][0] - ch[1][1]) / 864e5) === 1;
  })());
  t('sources: Search 2,318 +31%', /Search<\/td>.*?2,318<\/td><td class="n"><span class="up">\+31%/.test(els.sources.innerHTML), els.sources.innerHTML.slice(0, 300));
  t('sources: Social down 12%', /Social<\/td>.*?402<\/td><td class="n"><span class="down">−12%/.test(els.sources.innerHTML));
  t('sources: Unassigned shows as Other, new', /Other<\/td>.*?10<\/td><td class="n"><span class="up">new/.test(els.sources.innerHTML));
  t('sources: the All row', /<b>All<\/b>.*?<b>4,008<\/b>/.test(els.sources.innerHTML));
  t('search: clicks +29%', /Clicks<\/td><td>2,107 <span class="up">\+29%/.test(els.search.innerHTML), els.search.innerHTML);
  t('search: click rate down 0.3 pts', /2\.4% <span class="down">−0\.3 pts/.test(els.search.innerHTML));
  t('search: position from 22.1, better', /18\.6 <span class="up">from 22\.1/.test(els.search.innerHTML));
  t('shares broken down by method', els.shares.innerHTML === '63: 38 share sheet, 17 card image, 8 copied links', els.shares.innerHTML);
  t('signups', els.signups.innerHTML === '41 confirmed on the site', els.signups.innerHTML);
  t('titles to tune: the low-rate page with its title, not the good one or the tiny one', /team\/kc\/defense\/.*Chiefs defense 2026: stats<\/small>.*6,210.*0\.5%/.test(els.tune.innerHTML) && !/tiny/.test(els.tune.innerHTML) && !/>\/<\/a>/.test(els.tune.innerHTML), els.tune.innerHTML);
  t('the copy button is ready', els.copy.disabled === false);
  t('landing: (not set) left out, new page marked new', /\/draft\/2026\/.*new/.test(els.landing.innerHTML) && !/not set/.test(els.landing.innerHTML), els.landing.innerHTML);
  t('overview: visits with search share', /^4,008 <span class="up">\+20%<\/span>, 58% from search$/.test(els['ov-visits'].innerHTML), els['ov-visits'].innerHTML);
  t('overview: search clicks', /^2,107 <span class="up">\+29%/.test(els['ov-clicks'].innerHTML));
  t('the date range is drawn', / – .*, against /.test(els.range.textContent), els.range.textContent);

  const r2 = await run(true), e = r2.els;
  ['sources', 'search', 'tune', 'landing'].forEach((k) => t('refused: ' + k + ' says Site Kit didn’t answer', /Site Kit didn’t answer: You’ll need to grant Site Kit permission/.test(e[k].innerHTML), e[k].innerHTML));
  t('refused: shares show the reason', /grant Site Kit permission/.test(e.shares.innerHTML));
  t('refused: overview shows the reason', /grant Site Kit permission/.test(e['ov-visits'].innerHTML) && /grant Site Kit permission/.test(e['ov-clicks'].innerHTML));
  console.log('growth.js: ' + fails + ' failed'); process.exit(fails ? 1 : 0);
})();
