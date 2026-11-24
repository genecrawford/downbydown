/* The playoffs (SCOPE §88): the bracket's chalk routes, and following one team's run on the bracket and the home hero.
   2.50.0 (SCOPE §92): the home page's bracket hero draws its chalk from data-ln (card to card, or into the Super Bowl from above), rises
   in round by round once, and follows a team the same way. */
(function () {
	var NEXT = { WC: 'DIV', DIV: 'CON', CON: 'SB' };

	function mark(box, t) {
		box.classList.toggle('hl', !!t);
		box.querySelectorAll('.on').forEach(function (x) { x.classList.remove('on'); });
		if (t) box.querySelectorAll('[data-t="' + t + '"]').forEach(function (x) { x.classList.add('on'); });
	}

	function routes(f) {
		if (!f.offsetWidth) return;
		var svg = f.querySelector('.routes'), B = f.getBoundingClientRect(), mid = B.width / 2, h = '';
		svg.setAttribute('viewBox', '0 0 ' + B.width + ' ' + B.height);
		function link(from, t, round) {
			var to = f.querySelector('.rw[data-t="' + t + '"][data-r="' + round + '"]'); if (!to) return;
			var a = from.closest('.g').getBoundingClientRect(), b = (to.closest('.g') || to.closest('.sbbox')).getBoundingClientRect(), ra = from.getBoundingClientRect(), rb = to.getBoundingClientRect();
			var left = a.left - B.left < mid, x1 = (left ? a.right : a.left) - B.left, y1 = ra.top + ra.height / 2 - B.top, x2 = (left ? b.left : b.right) - B.left, y2 = rb.top + rb.height / 2 - B.top, dx = (x2 - x1) * .55;
			h += '<path data-t="' + t + '" d="M' + x1 + ' ' + y1 + 'C' + (x1 + dx) + ' ' + y1 + ' ' + (x2 - dx) + ' ' + y2 + ' ' + x2 + ' ' + y2 + '"/><circle data-t="' + t + '" cx="' + x2 + '" cy="' + y2 + '" r="3.5"/>';
		}
		f.querySelectorAll('.rw[data-bye]').forEach(function (x) { link(x, x.dataset.t, 'DIV'); });
		f.querySelectorAll('.rw.w[data-r]').forEach(function (x) { if (NEXT[x.dataset.r]) link(x, x.dataset.t, NEXT[x.dataset.r]); });
		svg.innerHTML = h;
		mark(f, f.dataset.champ || null);
	}

	/* the home page's bracket: each line [ from card, from team, to card, to team, class, team, side, h across or d down ] */
	function chalk(f) {
		var svg = f.querySelector('svg.po-ch'); if (!svg) return; var L; try { L = JSON.parse(svg.getAttribute('data-ln') || '[]'); } catch (e) { return; }
		if (!f.offsetWidth || getComputedStyle(svg).display === 'none') { svg.innerHTML = ''; return; }
		var B = f.getBoundingClientRect(), h = '';
		function box(id, t) { var c = f.querySelector('#pob-' + id); if (!c) return null; var r = t ? c.querySelector('.po-r[data-t="' + t + '"]') : null; return (r || c).getBoundingClientRect(); }
		L.forEach(function (l) {
			var a = box(l[0], l[1]), b = box(l[2], l[3]); if (!a || !b) return; var left = l[6] === 'l', d;
			var x1 = (left ? a.right : a.left) - B.left, y1 = a.top + a.height / 2 - B.top;
			if (l[7] === 'd') { d = 'M' + x1 + ' ' + y1 + 'H' + B.width * (left ? .485 : .515) + 'V' + (b.top - B.top); }
			else { var x2 = (left ? b.left : b.right) - B.left, y2 = b.top + b.height / 2 - B.top, mx = (x1 + x2) / 2; d = 'M' + x1 + ' ' + y1 + 'H' + mx + 'V' + y2 + 'H' + x2; }
			var dl = /-(bye|wc)/.test(l[0]) ? (l[4] === 'p' ? .35 : .55) : /-dv/.test(l[0]) ? (l[4] === 'p' ? .6 : .85) : 1.05;
			h += '<path class="' + l[4] + '"' + (l[5] ? ' data-t="' + l[5] + '"' : '') + ' style="--dl:' + dl + 's" d="' + d + '"/>';
		});
		svg.setAttribute('viewBox', '0 0 ' + B.width + ' ' + B.height); svg.innerHTML = h;
		[].forEach.call(svg.querySelectorAll('path'), function (p) { p.style.setProperty('--len', Math.ceil(p.getTotalLength()) + 1); });
	}
	function hero() {
		document.querySelectorAll('[data-pob]').forEach(function (f) {
			chalk(f);
			if (!f.classList.contains('go')) { void f.offsetWidth; f.classList.add('go'); setTimeout(function () { f.classList.add('done'); }, 2600); }  /* the end state, even where the animations don't run */
		});
	}
	function draw() { document.querySelectorAll('[data-bracket]').forEach(routes); hero(); }

	function follow(e) {
		if (!e.target.closest) return;
		var f = e.target.closest('[data-bracket]');
		if (f) { var r = e.target.closest('.rw[data-t]'); mark(f, r ? r.dataset.t : (f.dataset.champ || null)); return; }
		var b = e.target.closest('[data-pob]');
		if (b) { var q = e.target.closest('.po-r[data-t]'); mark(b, q ? q.dataset.t : null); return; }
		var h = e.target.closest('[data-pofield]');
		if (h) { var p = e.target.closest('.po-pc'); mark(h, p ? p.dataset.t : null); }
	}
	function leave(e) {
		if (!e.target.closest) return;
		var box = e.target.closest('[data-bracket],[data-pofield],[data-pob]');
		if (box && !(e.relatedTarget && box.contains(e.relatedTarget))) mark(box, box.dataset.champ || null);
	}

	document.addEventListener('mouseover', follow); document.addEventListener('focusin', follow); document.addEventListener('mouseout', leave);
	addEventListener('resize', draw); addEventListener('load', draw); draw();
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(draw);
})();
