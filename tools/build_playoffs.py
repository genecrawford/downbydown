"""The playoff seeds (SCOPE §88): playoffs.json for the season, from nflverse's standings, which apply the NFL's tiebreakers.

    python3 tools/build_playoffs.py <data dir> <season>

Writes {season, seeds: {team: {seed, conf, rec}}} only when both conferences have seeds 1 to 7. A failed download or a partial
table leaves the file that is there, so a bad night can't blank the bracket. No timestamp, so the file changes only when a seed does.
"""
import csv
import io
import json
import os
import sys
import urllib.request

URL = 'https://raw.githubusercontent.com/nflverse/nfldata/master/data/standings.csv'


def num(v):
    try:
        return int(float(v))
    except (TypeError, ValueError):
        return None


def seeds(rows, season):
    out = {}
    for r in rows:
        if str(r.get('season')) != str(season):
            continue
        s = num(r.get('seed'))
        if s is None or r.get('conf') not in ('AFC', 'NFC') or not r.get('team'):
            continue
        ties = num(r.get('ties')) or 0
        out[r['team']] = {'seed': s, 'conf': r['conf'], 'rec': '%d-%d%s' % (num(r.get('wins')) or 0, num(r.get('losses')) or 0, '-%d' % ties if ties else '')}
    for c in ('AFC', 'NFC'):
        if sorted(x['seed'] for x in out.values() if x['conf'] == c) != list(range(1, 8)):
            return None
    return out


def build(out, season, text=None):
    try:
        if text is None:
            with urllib.request.urlopen(URL, timeout=30) as res:
                text = res.read().decode('utf-8')
        got = seeds(list(csv.DictReader(io.StringIO(text))), season)
    except Exception as e:
        print('playoff seeds: kept the last file,', e)
        return False
    if not got:
        print('playoff seeds: no full set for', season, 'yet')
        return False
    with open(os.path.join(out, 'playoffs.json'), 'w', encoding='utf-8') as f:
        json.dump({'season': int(season), 'seeds': dict(sorted(got.items()))}, f, separators=(',', ':'))
    print('playoff seeds:', season, len(got), 'teams')
    return True


if __name__ == '__main__':
    sys.exit(0 if build(sys.argv[1], sys.argv[2]) else 1)
