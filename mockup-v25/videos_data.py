"""SCOPE §51 mock data: the Vikings' channel, the NFL channel filtered to the Vikings, and the Week 9 game-recap playlist,
read from YouTube's public feeds exactly as the plugin would. Writes videos.json (not committed)."""
import json, re, time, urllib.request, html
from datetime import datetime
UA = {'User-Agent': 'Mozilla/5.0'}
def feed(q):
    x = urllib.request.urlopen(urllib.request.Request('https://www.youtube.com/feeds/videos.xml?' + q, headers=UA), timeout=20).read().decode()
    out = []
    for e in re.findall(r'<entry>(.*?)</entry>', x, re.S):
        vid = re.search(r'<yt:videoId>([^<]+)', e).group(1)
        link = re.search(r'<link rel="alternate" href="([^"]+)"', e).group(1)
        t = html.unescape(re.search(r'<title>([^<]*)', e).group(1))
        ts = int(datetime.fromisoformat(re.search(r'<published>([^<]+)', e).group(1)).timestamp())
        v = re.search(r'views="(\d+)"', e)
        out.append({'id': vid, 'title': t, 'ts': ts, 'short': '/shorts/' in link, 'views': int(v.group(1)) if v else 0})
    return out
team = feed('channel_id=UCcsw_KrB_wg5lQ5nXWR_LFA')
nfl = [v for v in feed('channel_id=UCDVYQ4Zhbm3S2dlz7P1GBDg') if re.search(r'\bVikings\b|\bMinnesota\b', v['title'])]
recap = feed('playlist_id=PLV3aoc7YohIk')
hl = next((v for v in recap if 'Bills' in v['title'] and 'Vikings' in v['title']), None)
json.dump({'built': int(time.time()), 'team': team, 'nfl': nfl, 'highlight': hl, 'recap': recap}, open('videos.json', 'w'), indent=1)
print(len(team), 'team videos,', sum(v['short'] for v in team), 'shorts;', len(nfl), 'NFL Vikings videos; highlight:', hl and hl['title'])
