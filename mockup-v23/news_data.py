"""SCOPE §49 mock data: fetch the 32 team-site feeds and four league feeds, match stories to teams and players.
Run from the repo root: python3 mockup-v23/news_data.py  -> mockup-v23/news.json"""
import json, re, urllib.request, email.utils, datetime, html, concurrent.futures as cf
UA = 'Mozilla/5.0 (compatible; DownByDown/1.0; +https://downbydown.com/)'
T = json.load(open('data/teams.json'))
SITES = dict(ARI='azcardinals.com', ATL='atlantafalcons.com', BAL='baltimoreravens.com', BUF='buffalobills.com', CAR='panthers.com', CHI='chicagobears.com',
  CIN='bengals.com', CLE='clevelandbrowns.com', DAL='dallascowboys.com', DEN='denverbroncos.com', DET='detroitlions.com', GB='packers.com', HOU='houstontexans.com',
  IND='colts.com', JAX='jaguars.com', KC='chiefs.com', LV='raiders.com', LAC='chargers.com', LA='therams.com', MIA='miamidolphins.com', MIN='vikings.com',
  NE='patriots.com', NO='neworleanssaints.com', NYG='giants.com', NYJ='newyorkjets.com', PHI='philadelphiaeagles.com', PIT='steelers.com', SF='49ers.com',
  SEA='seahawks.com', TB='buccaneers.com', TEN='tennesseetitans.com', WAS='commanders.com')
LEAGUE = {'CBS Sports': 'https://www.cbssports.com/rss/headlines/nfl/', 'ESPN': 'https://www.espn.com/espn/rss/nfl/news',
  'ProFootballTalk': 'https://profootballtalk.nbcsports.com/feed/', 'Yahoo Sports': 'https://sports.yahoo.com/nfl/rss/'}
def get(u):
    try:
        with urllib.request.urlopen(urllib.request.Request(u, headers={'User-Agent': UA}), timeout=20) as r: return r.read().decode('utf-8', 'replace')
    except Exception as ex: return ''
def items(x):
    out = []
    for it in re.findall(r'<item\b.*?</item>', x, re.S):
        t = re.search(r'<title>(.*?)</title>', it, re.S); l = re.search(r'<link>(.*?)</link>', it, re.S); d = re.search(r'<pubDate>(.*?)</pubDate>', it, re.S)
        if not (t and l): continue
        clean = lambda s: html.unescape(re.sub(r'<!\[CDATA\[|\]\]>', '', s)).strip()
        try: ts = int(email.utils.parsedate_to_datetime(clean(d.group(1))).timestamp()) if d else 0
        except Exception: ts = 0
        out.append({'title': clean(t.group(1)), 'url': clean(l.group(1)), 'ts': ts})
    return out
with cf.ThreadPoolExecutor(12) as ex:
    team_raw = dict(zip(SITES, ex.map(lambda t: get(f'https://www.{SITES[t]}/rss/news'), SITES)))
    league_raw = dict(zip(LEAGUE, ex.map(get, LEAGUE.values())))
now = int(datetime.datetime.now(datetime.timezone.utc).timestamp()); WEEK = 7 * 86400
P = json.load(open('data/players/index.json'))['players']
names = {}
for p in P: names.setdefault(p['name'], []).append(p)
names = {n: v[0] for n, v in names.items() if len(v) == 1 and len(n) > 7}
COLLEGE = r'(?<!Nittany )(?<!Penn State )(?<!Baylor )(?<!Pitt )(?<!Florida )(?<!Louisville )(?<!Winnipeg )(?<!Boston College )(?<!Colorado State )(?<!St\. Louis )(?<!Texas Southern )'
nick = {t: re.compile(COLLEGE + r'\b(' + re.escape(T[t]['nick']) + '|' + re.escape(T[t]['name']) + r')\b') for t in T}
def players_in(title): return [{'name': n, 'slug': p['slug'], 'team': p['team']} for n, p in names.items() if n in title][:2]
news = {t: {'site': SITES[t], 'official': [], 'league': []} for t in T}
for t, x in team_raw.items():
    for it in items(x)[:30]:
        if now - it['ts'] > WEEK: continue
        news[t]['official'].append({**it, 'src': SITES[t][0].upper() + SITES[t][1:], 'who': [q for q in players_in(it['title']) if q['team'] == t]})
seen = set()
for src, x in league_raw.items():
    for it in items(x):
        key = re.sub(r'\W+', '', it['title'].lower())
        if now - it['ts'] > WEEK or it['url'] in seen or key in seen: continue
        seen.add(it['url']); seen.add(key); who = players_in(it['title'])
        ts = {t for t in T if nick[t].search(it['title'])} | {q['team'] for q in who}
        if not ts or len(ts) > 3: continue
        for t in ts: news[t]['league'].append({**it, 'src': src, 'who': [q for q in who if q['team'] == t]})
for t in news:
    for k in ('official', 'league'): news[t][k].sort(key=lambda i: -i['ts'])
json.dump({'built': now, 'news': news}, open('mockup-v23/news.json', 'w'), indent=1)
print('teams with official:', sum(bool(v['official']) for v in news.values()), '| league stories matched:', sum(len(v['league']) for v in news.values()))
for t in ('MIN', 'DET', 'DAL'): print(t, len(news[t]['official']), len(news[t]['league']), [i['title'][:60] for i in news[t]['league'][:3]])
