"""The playoff seeds (SCOPE §88) and the standings (SCOPE §100).

    python3 tools/build_playoffs.py <data dir> <season>

playoffs.json: {season, seeds: {team: {seed, conf, rec}}} from nflverse's standings, which apply the NFL's tiebreakers. Written only
when both conferences have seeds 1 to 7. A failed download or a partial table leaves the file that is there, so a bad night can't
blank the bracket. No timestamp, so the file changes only when a seed does.

standings.json: the conference order 1 to 16 and the division order, from our own tiebreaker engine (tools/tiebreak.py), with the
step that broke each tie, and the NFL's clinch and elimination marks from ESPN's standings feed. See standings().
"""
import csv
import io
import json
import os
import sys
import urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from tiebreak import Season  # noqa: E402

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
    ok = seeds_file(out, season, text)
    try:
        standings(out, season)
    except Exception as e:  # the standings never stop the build; the last file stays
        print('standings: kept the last file,', e)
    return ok


def seeds_file(out, season, text=None):
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


# ---------------------------------------------------------------- standings.json (SCOPE §100)
ESPN = 'https://site.api.espn.com/apis/v2/sports/football/nfl/standings?season=%s'
ESPN_ABBR = {'LAR': 'LA', 'WSH': 'WAS'}
MARK_UP = {'x': 1, 'y': 2, 'z': 3, '*': 4}  # a mark only moves up; 'e' stays once given


def espn(season, raw=None):
    """{team: (seed, mark)} from ESPN's standings, or {} when the feed can't be read."""
    try:
        if raw is None:
            req = urllib.request.Request(ESPN % season, headers={'User-Agent': 'DownByDown/2 data build', 'Accept': 'application/json'})  # ESPN refuses an agent with a URL in it (403)
            with urllib.request.urlopen(req, timeout=30) as res:
                raw = res.read().decode('utf-8')
        out = {}
        for c in json.loads(raw).get('children', []):
            for e in c.get('standings', {}).get('entries', []):
                st = {x.get('name'): x.get('displayValue') for x in e.get('stats', [])}
                a = e['team']['abbreviation']; a = ESPN_ABBR.get(a, a)
                m = (st.get('clincher') or '').strip()
                out[a] = (num(st.get('playoffSeed')), m if m in MARK_UP or m == 'e' else '')
        return out
    except Exception as e:
        print('standings: ESPN feed not read,', e)
        return {}


def standings(out, season, raw=None):
    """Writes standings.json:
        {season, through, conf: {AFC: [[team, why], …16]}, div: {'AFC East': [[team, why], …]}, marks: {team: code}, since: {team: week}, checked}
    why = None or {s: step, o: [teams]} (tools/tiebreak.py). The order is ours; it is checked against ESPN's seeds (all 16 a conference)
    or, without them, nflverse's seeds 1 to 7. When a check fails, ESPN's order is used if there is one and the reason lines are left out.
    Marks are ESPN's; a mark is never taken away (x → y/z → * only moves up, e stays), so a feed that hiccups can't unmark a team.
    A mark that contradicts the order (z not leading its division, * not the 1 seed, e inside the top 7) is dropped and logged."""
    teams = json.load(open(os.path.join(out, 'teams.json'), encoding='utf-8'))
    sched = json.load(open(os.path.join(out, 'schedule.json'), encoding='utf-8'))
    T = {a: {'conf': t['conf'], 'div': t['div']} for a, t in teams.items() if t.get('conf') in ('AFC', 'NFC') and t.get('div')}
    G = [(g['home'], g['away'], g['home_score'], g['away_score']) for w in sched.get('weeks', {}).values() for g in w
         if g.get('game_type', 'REG') == 'REG' and g.get('status') == 'final' and g.get('home_score') is not None and g.get('away_score') is not None]
    through = int(sched.get('through_week') or 0)
    S = Season(T, G)
    conf = {c: S.conference(c) for c in ('AFC', 'NFC')}
    E = espn(season, raw)
    checked = 'none'
    if len(E) == len(T) and all(E[a][0] for a in T if a in E):
        bad = [(a, i + 1, E.get(a, (None,))[0]) for c in conf for i, (a, _) in enumerate(conf[c]) if E.get(a, (None,))[0] != i + 1]
        if bad:
            print('standings: our order differs from ESPN\'s at', bad[:6], '- using ESPN\'s order without reasons')
            conf = {c: [(a, None) for a in sorted((a for a in T if T[a]['conf'] == c), key=lambda a: E[a][0])] for c in conf}
            checked = 'espn-order'
        else:
            checked = 'espn'
    else:
        try:
            nv = json.load(open(os.path.join(out, 'playoffs.json'), encoding='utf-8'))
            nv = nv.get('seeds', {}) if nv.get('season') == int(season) else {}
        except Exception:
            nv = {}
        if nv:
            bad = [a for c in conf for i, (a, _) in enumerate(conf[c][:7]) if (nv.get(a) or {}).get('seed') != i + 1]
            if bad:
                print('standings: our seeds 1 to 7 differ from nflverse\'s at', bad, '- reasons left out')
                conf = {c: [(a, None) for a, _ in conf[c]] for c in conf}
                checked = 'nflverse-differs'
            else:
                checked = 'nflverse'
    # the division order follows the conference order (the NFL breaks same-division ties first, so they agree)
    pos = {a: i for c in conf for i, (a, _) in enumerate(conf[c])}
    dwhy = {} if checked in ('espn-order', 'nflverse-differs') else {a: w for d in sorted({t['div'] for t in T.values()}) for a, w in S.division(d)}
    div = {d: [[a, dwhy.get(a)] for a in sorted((a for a in T if T[a]['div'] == d), key=lambda a: pos[a])] for d in sorted({t['div'] for t in T.values()})}
    if not G:  # before Week 1 every club is level; there is nothing to explain
        conf = {c: [(a, None) for a, _ in conf[c]] for c in conf}
        div = {d: [[a, None] for a, _ in rows] for d, rows in div.items()}
    # marks: the last file's, moved up by today's feed, checked against the order
    path = os.path.join(out, 'standings.json')
    try:
        old = json.load(open(path, encoding='utf-8'))
        if old.get('season') != int(season):
            old = {}
    except Exception:
        old = {}
    marks, since = dict(old.get('marks') or {}), dict(old.get('since') or {})
    for a, (_, m) in E.items():
        if a not in T or not m:
            continue
        o = marks.get(a, '')
        if o == 'e' or (o and m != 'e' and MARK_UP.get(m, 0) <= MARK_UP.get(o, 0)):
            continue
        if o and m == 'e':
            print('standings: ESPN marks', a, 'eliminated after', o, '- keeping', o)
            continue
        marks[a] = m; since[a] = through
    seed = {a: i + 1 for c in conf for i, (a, _) in enumerate(conf[c])}
    for a, m in list(marks.items()):
        lead = div[T[a]['div']][0][0] == a
        if (m == 'z' and not lead) or (m == '*' and seed.get(a) != 1) or (m == 'e' and seed.get(a, 99) <= 7):
            print('standings: dropped mark', m, 'for', a, '(seed', seed.get(a), ', division leader' if lead else '', ')')
            marks.pop(a); since.pop(a, None)
    doc = {'season': int(season), 'through': through, 'checked': checked,
           'conf': {c: [[a, w] for a, w in conf[c]] for c in ('AFC', 'NFC')}, 'div': div,
           'marks': dict(sorted(marks.items())), 'since': dict(sorted(since.items()))}
    if old and {k: v for k, v in old.items()} == doc:
        print('standings: unchanged')
        return True
    with open(path, 'w', encoding='utf-8') as f:
        json.dump(doc, f, separators=(',', ':'))
    print('standings:', season, 'through week', through, '·', checked, '·', len(marks), 'marks')
    return True


if __name__ == '__main__':
    sys.exit(0 if build(sys.argv[1], sys.argv[2]) else 1)
