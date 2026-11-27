/* The playoffs (SCOPE §88): following one team's run on the bracket. 2.55.0 (SCOPE §97): the playoffs page draws the home page's
   bracket too, so the old bracket's routes are gone.
   2.50.0 (SCOPE §92): the home page's bracket hero draws its chalk from data-ln (card to card, or into the Super Bowl from above), rises
   in round by round once, and follows a team the same way. */
(function () {
	function mark(box, t) {
		box.classList.toggle('hl', !!t);
		box.querySelectorAll('.on').forEach(function (x) { x.classList.remove('on'); });
		if (t) box.querySelectorAll('[data-t="' + t + '"]').forEach(function (x) { x.classList.add('on'); });
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
	/* a nickname too long for its card shrinks to fit, down to three quarters of its size; past that it ends in an ellipsis (2.55.1) */
	function fit(f) {
		[].forEach.call(f.querySelectorAll('.po-r .nm'), function (n) {
			n.style.fontSize = '';
			if (n.scrollWidth <= n.clientWidth + .5) return;
			var base = parseFloat(getComputedStyle(n).fontSize), k = Math.max(.75, n.clientWidth / n.scrollWidth);
			n.style.fontSize = (base * k).toFixed(2) + 'px';
		});
	}
	function hero() {
		document.querySelectorAll('[data-pob]').forEach(function (f) {
			fit(f); chalk(f);
			if (!f.classList.contains('go')) { void f.offsetWidth; f.classList.add('go'); setTimeout(function () { f.classList.add('done'); }, 2600); }  /* the end state, even where the animations don't run */
		});
	}
	function draw() { hero(); }

	function follow(e) {
		if (!e.target.closest) return;
		var b = e.target.closest('[data-pob]');
		if (b) { var q = e.target.closest('.po-r[data-t]'); mark(b, q ? q.dataset.t : null); return; }
		var h = e.target.closest('[data-pofield]');
		if (h) { var p = e.target.closest('.po-pc'); mark(h, p ? p.dataset.t : null); }
	}
	function leave(e) {
		if (!e.target.closest) return;
		var box = e.target.closest('[data-pofield],[data-pob]');
		if (box && !(e.relatedTarget && box.contains(e.relatedTarget))) mark(box, box.dataset.champ || null);
	}

	document.addEventListener('mouseover', follow); document.addEventListener('focusin', follow); document.addEventListener('mouseout', leave);
	addEventListener('resize', draw); addEventListener('load', draw); draw();
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(draw);
})();
