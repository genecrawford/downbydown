/* The playoffs (SCOPE §88): the bracket's chalk routes, and following one team's run on the bracket and the home hero. */
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

	function draw() { document.querySelectorAll('[data-bracket]').forEach(routes); }

	function follow(e) {
		if (!e.target.closest) return;
		var f = e.target.closest('[data-bracket]');
		if (f) { var r = e.target.closest('.rw[data-t]'); mark(f, r ? r.dataset.t : (f.dataset.champ || null)); return; }
		var h = e.target.closest('[data-pofield]');
		if (h) { var p = e.target.closest('.po-pc'); mark(h, p ? p.dataset.t : null); }
	}
	function leave(e) {
		if (!e.target.closest) return;
		var box = e.target.closest('[data-bracket],[data-pofield]');
		if (box && !(e.relatedTarget && box.contains(e.relatedTarget))) mark(box, box.dataset.champ || null);
	}

	document.addEventListener('mouseover', follow); document.addEventListener('focusin', follow); document.addEventListener('mouseout', leave);
	addEventListener('resize', draw); addEventListener('load', draw); draw();
	if (document.fonts && document.fonts.ready) document.fonts.ready.then(draw);
})();
