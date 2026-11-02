"""Season archive (SCOPE §44). data/ is the current season; a finished season lives in data/archive/{season}/ in the same layout.
data/career/ (the build's cache of past seasons' totals, never synced) stays out of every archive.

  python3 tools/archive.py freeze <data> <new season>   before building <new season>: if data/ holds an earlier season that has no
                                                       archive yet, move it to data/archive/{that season}/ and leave data/ ready
  python3 tools/archive.py place <built> <data>        put a season built into <built> (tools/build_data.py output) into the archive
  python3 tools/archive.py index <data>                write the archive list into data/manifest.json: {"archive": {"2025": sha1}}
"""
import hashlib, json, os, shutil, sys

KEEP_OUT = ('archive', 'career')  # top-level folders that never go into an archived season


def sha1(p):
    return hashlib.sha1(open(p, 'rb').read()).hexdigest()


def season_manifest(d):
    """Rewrite d/manifest.json so its file list matches what is in d (no career cache, no archive), hashes and all."""
    m = json.load(open(f'{d}/manifest.json'))
    files = {}
    for root, dirs, names in os.walk(d):
        rel_root = os.path.relpath(root, d).replace(os.sep, '/')
        if rel_root == '.':
            dirs[:] = [x for x in dirs if x not in KEEP_OUT]
        for n in sorted(names):
            if n == 'manifest.json' or not n.endswith('.json'):
                continue
            p = os.path.join(root, n)
            files[os.path.relpath(p, d).replace(os.sep, '/')] = sha1(p)
    m['files'] = files
    m.pop('archive', None)
    m['archived'] = True
    json.dump(m, open(f'{d}/manifest.json', 'w'), indent=1)
    return m


def index(data):
    """The root manifest lists every archived season with its manifest's hash, so the site knows what exists and when one changed."""
    mp = f'{data}/manifest.json'
    if not os.path.exists(mp):
        return {}
    m = json.load(open(mp))
    arch = {}
    base = f'{data}/archive'
    if os.path.isdir(base):
        for s in sorted(os.listdir(base)):
            if s.isdigit() and os.path.exists(f'{base}/{s}/manifest.json'):
                arch[s] = sha1(f'{base}/{s}/manifest.json')
    m['archive'] = arch
    json.dump(m, open(mp, 'w'), indent=1)
    return arch


def freeze(data, new_season):
    mp = f'{data}/manifest.json'
    if not os.path.exists(mp):
        print('freeze: no data yet'); return False
    old = int(json.load(open(mp)).get('season') or 0)
    if not old or old >= int(new_season):
        print(f'freeze: data/ holds {old}, building {new_season}: nothing to archive'); return False
    dest = f'{data}/archive/{old}'
    if os.path.exists(dest):
        print(f'freeze: {old} is already archived; clearing data/ for {new_season}')
    else:
        os.makedirs(dest)
        for n in os.listdir(data):
            if n in KEEP_OUT:
                continue
            src = os.path.join(data, n)
            (shutil.copytree if os.path.isdir(src) else shutil.copy2)(src, os.path.join(dest, n))
        m = season_manifest(dest)
        print(f'freeze: archived {old} ({len(m["files"])} files) into {dest}')
    for n in os.listdir(data):  # the new season starts from a clean folder: a week 20 file from last season must not linger
        if n in KEEP_OUT:
            continue
        p = os.path.join(data, n)
        shutil.rmtree(p) if os.path.isdir(p) else os.remove(p)
    return True


def place(built, data):
    m = json.load(open(f'{built}/manifest.json'))
    s = int(m['season'])
    dest = f'{data}/archive/{s}'
    cur = json.load(open(f'{data}/manifest.json')).get('season') if os.path.exists(f'{data}/manifest.json') else None
    if cur and int(cur) == s:
        sys.exit(f'place: {s} is the current season; it is archived by freeze when the next one starts')
    if os.path.exists(dest):
        shutil.rmtree(dest)
    shutil.copytree(built, dest, ignore=lambda d, names: [x for x in names if os.path.relpath(os.path.join(d, x), built) in KEEP_OUT])
    m = season_manifest(dest)
    print(f'place: {s} archived ({len(m["files"])} files) into {dest}')
    return s


if __name__ == '__main__':
    cmd = sys.argv[1] if len(sys.argv) > 1 else ''
    if cmd == 'freeze':
        freeze(sys.argv[2], sys.argv[3]); index(sys.argv[2])
    elif cmd == 'place':
        place(sys.argv[2], sys.argv[3]); print('index:', index(sys.argv[3]))
    elif cmd == 'index':
        print('index:', index(sys.argv[2]))
    else:
        sys.exit(__doc__)
