"""The NFL's tiebreaking procedures (SCOPE §100): division order and conference seeds 1 to 16, with the step that broke each tie.

    s = Season(teams, games)   # teams: {abbr: {'conf', 'div'}}; games: [(home, away, home_score, away_score)], regular-season finals only
    s.division('AFC North')     # [(abbr, why)]
    s.conference('AFC')         # [(abbr, why)], seeds 1 to 16 in order
    why: None, or {'s': step, 'o': [the clubs it finished ahead of in that tie]}

Steps (NFL rules):
  Division, 2 clubs: head to head, division record, common games, conference record, strength of victory, strength of schedule,
    net points in common games, net points, coin toss. 3+ clubs: the same; once a club drops out, the rest start again at step 1.
  Wild card and seeding: division ties first (only the top club left in each division takes part), then head to head (2 clubs: if
    they played; 3+: only a sweep, a club that beat or lost to every other), conference record, common games (minimum 4),
    strength of victory, strength of schedule, net points in conference games, net points, coin toss.
The combined offense and defense rankings (division steps 7 and 8, wild card steps 6 and 7) are not computed; net points follow
strength of schedule. Checked against ESPN's standings on every place of the 2025 final table and 2026 through Week 12.
"""
from fractions import Fraction as F

STEPS_DIV = ('h2h', 'div', 'common', 'conf', 'sov', 'sos', 'npc', 'np', 'coin')
STEPS_WC = ('conf', 'common', 'sov', 'sos', 'npconf', 'np', 'coin')


class Season:
    def __init__(self, teams, games):
        self.T = teams
        self.res = {a: [] for a in teams}  # per club: (opponent, result 1 / ½ / 0, points for, points against)
        for h, a, hs, as_ in games:
            if h not in self.res or a not in self.res:
                continue
            r = F(1) if hs > as_ else (F(0) if hs < as_ else F(1, 2))
            self.res[h].append((a, r, hs, as_))
            self.res[a].append((h, 1 - r, as_, hs))
        self._rec = {}

    # ---- records
    def rec(self, a, opps=None):
        if opps is None and a in self._rec:
            return self._rec[a]
        w = l = t = 0
        for o, r, _, _ in self.res[a]:
            if opps is not None and o not in opps:
                continue
            if r == 1: w += 1
            elif r == 0: l += 1
            else: t += 1
        if opps is None:
            self._rec[a] = (w, l, t)
        return w, l, t

    @staticmethod
    def pct(wlt):
        w, l, t = wlt
        g = w + l + t
        return F(2 * w + t, 2 * g) if g else F(0)

    def wp(self, a, opps=None):
        return self.pct(self.rec(a, opps))

    def same_div(self, a):
        return {x for x in self.T if self.T[x]['div'] == self.T[a]['div'] and x != a}

    def same_conf(self, a):
        return {x for x in self.T if self.T[x]['conf'] == self.T[a]['conf'] and x != a}

    def common(self, S):
        sets = [set(o for o, *_ in self.res[a]) for a in S]
        return (set.intersection(*sets) - set(S)) if sets else set()

    def combined(self, opps):
        tw = [self.rec(o) for o in opps]
        return self.pct(tuple(map(sum, zip(*tw)))) if tw else F(0)

    def sov(self, a):  # the combined record of the clubs it beat, once per win
        return self.combined([o for o, r, *_ in self.res[a] if r == 1])

    def sos(self, a):  # the combined record of every opponent, once per game
        return self.combined([o for o, *_ in self.res[a]])

    def net(self, a, opps=None):
        return sum(pf - pa for o, r, pf, pa in self.res[a] if opps is None or o in opps)

    # ---- one step: {club: value}, higher is better; None when the step doesn't apply to these clubs
    def step(self, name, S, kind):
        if name == 'h2h':
            if kind == 'wc' and not any(o == S[1] for o, *_ in self.res[S[0]]):
                return None
            return {a: self.wp(a, set(S) - {a}) for a in S}
        if name == 'sweep':
            out = {}
            for a in S:
                ga = [(o, r) for o, r, *_ in self.res[a] if o in S and o != a]
                if {o for o, _ in ga} != set(S) - {a}:
                    out[a] = 0
                    continue
                out[a] = 1 if all(r == 1 for _, r in ga) else (-1 if all(r == 0 for _, r in ga) else 0)
            return out if any(out.values()) else None
        if name == 'div':
            return {a: self.wp(a, self.same_div(a)) for a in S}
        if name == 'common':
            c = self.common(S)
            if not c or (kind == 'wc' and any(sum(self.rec(a, c)) < 4 for a in S)):
                return None
            return {a: self.wp(a, c) for a in S}
        if name == 'conf':
            return {a: self.wp(a, self.same_conf(a)) for a in S}
        if name == 'sov':
            return {a: self.sov(a) for a in S}
        if name == 'sos':
            return {a: self.sos(a) for a in S}
        if name == 'npc':
            c = self.common(S)
            return {a: self.net(a, c) for a in S} if c else None
        if name == 'npconf':
            return {a: self.net(a, self.same_conf(a)) for a in S}
        if name == 'np':
            return {a: self.net(a) for a in S}
        if name == 'coin':  # a stand-in for the toss: alphabetical, and said so
            return {a: -i for i, a in enumerate(sorted(S))}
        raise ValueError(name)

    def top(self, S, kind):
        """The club that wins a tie among S, and the step that decided it."""
        S = sorted(S)
        while len(S) > 1:
            steps = STEPS_DIV if kind == 'div' else (('h2h',) if len(S) == 2 else ('sweep',)) + STEPS_WC
            for name in steps:
                v = self.step(name, S, kind)
                if v is None:
                    continue
                best = max(v.values())
                keep = [a for a in S if v[a] == best]
                if name == 'sweep' and best <= 0:  # nobody swept: a club that lost to all the others drops out
                    keep = [a for a in S if v[a] >= 0]
                if len(keep) < len(S):
                    if len(keep) == 1:
                        return keep[0], name
                    S = keep  # start again at step 1 with the clubs left
                    break
        return S[0], None

    @staticmethod
    def _why(pick, step, tied):
        return {'s': step, 'o': sorted(x for x in tied if x != pick)} if step else None

    # ---- division order
    def division(self, div):
        left = [a for a in self.T if self.T[a]['div'] == div]
        out = []
        while left:
            best = max(self.wp(a) for a in left)
            tied = [a for a in left if self.wp(a) == best]
            if len(tied) == 1:
                out.append((tied[0], None))
            else:
                a, step = self.top(tied, 'div')
                out.append((a, self._why(a, step, tied)))
            left.remove(out[-1][0])
        return out

    # ---- conference seeds 1 to 16: the four division winners, then everyone else
    def conference(self, conf):
        divs = sorted({t['div'] for t in self.T.values() if t['conf'] == conf})
        ranks = {d: self.division(d) for d in divs}
        dwhy = {a: w for d in divs for a, w in ranks[d]}
        out = []
        for pool in ([ranks[d][0][0] for d in divs], [a for d in divs for a, _ in ranks[d][1:]]):
            left = list(pool)
            while left:
                best = max(self.wp(a) for a in left)
                tied = [a for a in left if self.wp(a) == best]
                heads = [next(a for a, _ in ranks[d] if a in tied) for d in divs if any(a in tied for a, _ in ranks[d])]
                if len(heads) == 1:
                    pick = heads[0]
                    why = dwhy.get(pick) if len(tied) > 1 else None  # level only with clubs from its own division
                else:
                    pick, step = self.top(heads, 'wc')
                    why = self._why(pick, step, tied)
                out.append((pick, why))
                left.remove(pick)
        return out
