"""CI helper (SCOPE §64): the page's JSON-LD has a block of the given @type that mentions the given text. Prints what it found."""
import json, re, sys
path, kind, want = sys.argv[1:4]
html = open(path).read()
blocks = [json.loads(b) for b in re.findall(r'<script type="application/ld\+json">(.*?)</script>', html, re.S)]
hit = [b for b in blocks if b.get('@type') == kind]
if not hit:
    sys.exit('%s: no %s JSON-LD (found %s)' % (path, kind, [b.get('@type') for b in blocks]))
b = hit[0]
if want not in json.dumps(b, ensure_ascii=False):
    sys.exit('%s: the %s JSON-LD does not mention %s' % (path, kind, want))
if kind == 'ItemList':
    items = b['itemListElement']
    if b['numberOfItems'] != len(items) or [i['position'] for i in items[:3]] != [1, 2, 3]:
        sys.exit('%s: the ItemList count or order is off' % path)
    print('%s: ItemList of %d, first %s' % (path, len(items), items[0]['item']['name']))
else:
    print('%s: %s with %s' % (path, kind, want))
