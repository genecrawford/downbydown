"""SCOPE §54 mock data: the NFL's weekly playlists, read from YouTube's public feeds as the plugin would. Writes feeds.json (not committed)."""
import json, re, urllib.request, html
from datetime import datetime
def feed(pid):
    x = urllib.request.urlopen(urllib.request.Request('https://www.youtube.com/feeds/videos.xml?playlist_id=' + pid, headers={'User-Agent': 'Mozilla/5.0'}), timeout=20).read().decode()
    return [{'id': re.search(r'<yt:videoId>([^<]+)', e).group(1), 'title': html.unescape(re.search(r'<title>([^<]*)', e).group(1)),
             'ts': int(datetime.fromisoformat(re.search(r'<published>([^<]+)', e).group(1)).timestamp())} for e in re.findall(r'<entry>(.*?)</entry>', x, re.S)]
out = {'preview10': feed('PLaoTWqslRMSU'), 'recap9': feed('PLV3aoc7YohIk'), 'recap8': feed('PLVR_WxzgKERw'), 'players9': feed('PLHPDOj9E1XDY')}
json.dump(out, open('feeds.json', 'w'), indent=1)
print({k: len(v) for k, v in out.items()})
