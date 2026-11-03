"""Refit the field goal make curve (SCOPE §47): a logistic on distance, z = (yards - 40) / 10, over every field goal try
of the given seasons' play-by-play (a block counts as a miss). Prints the two numbers for K_CURVE in build_data.py.
Run once a year, when a season is archived, on the three seasons before the new one:

  python3 tools/kick_curve.py pbp2023.csv.gz pbp2024.csv.gz pbp2025.csv.gz

Standard library only. 2023-2025 (3,413 tries) gives 2.1024, -0.9952: 96% at 30 yards, 89% at 40, 75% at 50, 53% at 60."""
import csv, gzip, math, sys

X = []
for f in sys.argv[1:]:
    for r in csv.DictReader(gzip.open(f, 'rt')):
        if r.get('field_goal_attempt') == '1' and r.get('kick_distance'):
            X.append(((float(r['kick_distance']) - 40) / 10, 1 if r['field_goal_result'] == 'made' else 0))
a = b = 0.0
for _ in range(50):  # Newton's method on the two-parameter log-likelihood
    ga = gb = haa = hab = hbb = 0.0
    for z, y in X:
        p = 1 / (1 + math.exp(-(a + b * z))); w = p * (1 - p)
        ga += y - p; gb += (y - p) * z; haa += w; hab += w * z; hbb += w * z * z
    det = haa * hbb - hab * hab
    da = (hbb * ga - hab * gb) / det; db = (haa * gb - hab * ga) / det
    a += da; b += db
    if abs(da) + abs(db) < 1e-10: break
print(f'{len(X)} tries: K_CURVE = ({a:.4f}, {b:.4f})')
print(' '.join(f'{d}:{100 / (1 + math.exp(-(a + b * (d - 40) / 10))):.0f}%' for d in (20, 30, 40, 50, 60, 65)))
