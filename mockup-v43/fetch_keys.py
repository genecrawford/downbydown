"""Fetch each Week 11 game's 'Biggest edge' read for both offenses from the live site, into _keys.json (run once)."""
import json, re, urllib.request, html as H
S = json.load(open('../data/schedule.json'))
out = {}
for g in S['weeks']['11']:
    for side in ('away', 'home'):
        url = 'https://downbydown.com/?game=' + g['id'] + ('&side=home' if side == 'home' else '') + '&nc=sp43'
        h = urllib.request.urlopen(urllib.request.Request(url, headers={'User-Agent': 'dbd-mock'}), timeout=30).read().decode()
        i = h.find('dbd-takes'); s = h[i:i + 4000]
        m = re.search(r'Biggest edge.*?<h3>(.*?)</h3><p>(.*?)</p>', s, re.S)
        if m: out[g['id'] + ':' + g[side]] = [H.unescape(re.sub('<.*?>', '', m.group(1))), H.unescape(re.sub('<.*?>', '', m.group(2)))]
json.dump(out, open('_keys.json', 'w'), indent=1); print(len(out))
