/* The site header, [dbd_block type="nav"] (2.53.0, SCOPE §95): the group panels, the phone menu, and search as you type. */
(function () {
	'use strict';
	var heads = document.querySelectorAll('.dxn');
	if (!heads.length) { return; }
	var fine = window.matchMedia ? window.matchMedia('(hover:hover) and (pointer:fine)') : null;

	function phone(h) { return getComputedStyle(h.querySelector('.dxn-burger')).display !== 'none'; }
	function setItem(li, on) {
		li.classList.toggle('open', on);
		li.querySelector('.dxn-top').setAttribute('aria-expanded', on ? 'true' : 'false');
	}
	function closeAll(h, except) { h.querySelectorAll('.dxn-item.open').forEach(function (li) { if (li !== except) { setItem(li, false); } }); }

	heads.forEach(function (h) {
		h.classList.add('dxn-js');
		var b = h.querySelector('.dxn-burger'), timer = 0;
		function menu(on) {
			h.classList.toggle('dxn-open', on);
			b.setAttribute('aria-expanded', on ? 'true' : 'false');
			b.setAttribute('aria-label', on ? 'Close menu' : 'Menu');
		}
		b.addEventListener('click', function () { menu(!h.classList.contains('dxn-open')); });
		h.querySelectorAll('.dxn-item').forEach(function (li) {
			var t = li.querySelector('.dxn-top');
			t.addEventListener('click', function () { var on = !li.classList.contains('open'); closeAll(h, li); setItem(li, on); });
			li.addEventListener('mouseenter', function () {
				if (phone(h) || !fine || !fine.matches) { return; }
				clearTimeout(timer); timer = setTimeout(function () { closeAll(h, li); setItem(li, true); }, 140);
			});
			li.addEventListener('mouseleave', function () { clearTimeout(timer); });  /* only a sustained hover opens a group (Greptile, PR #167) */
		});
		h.addEventListener('mouseenter', function () { clearTimeout(timer); });
		h.addEventListener('mouseleave', function () {
			if (phone(h) || !fine || !fine.matches) { return; }
			clearTimeout(timer); timer = setTimeout(function () { if (!h.contains(document.activeElement)) { closeAll(h); } }, 260);
		});
		/* a link followed closes everything (a same-page anchor like Standings would otherwise leave the panel open) */
		h.querySelectorAll('.dxn-panel a').forEach(function (a) { a.addEventListener('click', function () { closeAll(h); menu(false); }); });
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') { return; }
			var li = h.querySelector('.dxn-item.open');
			if (li && !phone(h)) { setItem(li, false); li.querySelector('.dxn-top').focus(); }
			else if (h.classList.contains('dxn-open')) { menu(false); b.focus(); }
		});
		document.addEventListener('click', function (e) { if (h.contains(e.target)) { return; } closeAll(h); if (phone(h)) { menu(false); } });
		/* keyboard: leaving an open panel with Tab closes it */
		h.addEventListener('focusout', function (e) {
			if (phone(h)) { return; }
			var li = e.target.closest && e.target.closest('.dxn-item');
			if (li && li.classList.contains('open') && !(e.relatedTarget && li.contains(e.relatedTarget))) { setItem(li, false); }
		});
	});

	/* search: teams from the Teams panel, players from GET dbd/v1/find (fetched once, on first use) */
	var players = null, loading = null;
	function load(url) {
		if (players || loading) { return loading; }
		loading = fetch(url, { credentials: 'omit' }).then(function (r) { return r.ok ? r.json() : []; }).catch(function () { return []; })
			.then(function (rows) { players = Array.isArray(rows) ? rows : []; return players; });
		return loading;
	}
	function norm(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
	function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
	var teams = [], tiles = {};
	var first = heads[0];
	first.querySelectorAll('.dxn-div a[data-t]').forEach(function (a) {
		teams.push({ t: a.getAttribute('data-t'), n: a.getAttribute('data-n'), k: a.getAttribute('data-k'), u: a.href });
		var tile = a.querySelector('.dxn-tile'); if (tile) { tiles[a.getAttribute('data-t')] = tile.outerHTML; }
	});
	function tileOf(abbr) { return tiles[abbr] || ''; }
	function find(q) {
		q = norm(q.trim()); if (!q) { return []; }
		var tm = [], pm = [];
		teams.forEach(function (t) {
			var n = norm(t.n), k = norm(t.k), a = t.t.toLowerCase();
			var s = a === q ? 0 : (k.indexOf(q) === 0 || n.indexOf(q) === 0) ? 1 : n.indexOf(' ' + q) >= 0 ? 2 : -1;
			if (s >= 0) { tm.push([s, t]); }
		});
		(players || []).forEach(function (p) {
			var n = norm(p[0]); var s = n.indexOf(q) === 0 ? 1 : n.indexOf(' ' + q) >= 0 ? 2 : -1;
			if (s >= 0) { pm.push([s, p]); }
		});
		tm.sort(function (a, b) { return a[0] - b[0]; });
		pm.sort(function (a, b) { return a[0] - b[0] || a[1][0].localeCompare(b[1][0]); });
		var out = [];
		tm.slice(0, 3).forEach(function (x) { var t = x[1]; out.push('<a href="' + esc(t.u) + '">' + tileOf(t.t) + esc(t.n) + '<small>Team</small></a>'); });
		pm.slice(0, 8 - out.length).forEach(function (x) { var p = x[1]; out.push('<a href="' + esc(p[3]) + '">' + tileOf(p[1]) + esc(p[0]) + '<small>' + esc(p[2]) + ' · ' + esc(p[1]) + '</small></a>'); });
		return out;
	}
	document.querySelectorAll('.dxn-search').forEach(function (f) {
		var i = f.querySelector('input[type=search]'), u = f.querySelector('.dxn-sugg'), sel = -1, url = f.getAttribute('data-find');
		function hide() { sel = -1; u.hidden = true; i.setAttribute('aria-expanded', 'false'); i.removeAttribute('aria-activedescendant'); }
		function draw() {
			sel = -1;
			if (!i.value.trim()) { hide(); return; }
			var r = find(i.value);
			u.innerHTML = r.length ? r.map(function (x, k) { return '<li role="option" id="' + u.id + '-' + k + '" aria-selected="false">' + x + '</li>'; }).join('')
				: '<li class="dxn-none">' + (players ? 'No player or team by that name. Press Enter to search all players.' : 'Loading players…') + '</li>';
			u.hidden = false; i.setAttribute('aria-expanded', 'true');
		}
		function mark() {
			u.querySelectorAll('li[role=option]').forEach(function (li, k) { li.setAttribute('aria-selected', k === sel ? 'true' : 'false'); });
			if (sel >= 0) { i.setAttribute('aria-activedescendant', u.id + '-' + sel); } else { i.removeAttribute('aria-activedescendant'); }
		}
		i.addEventListener('focus', function () { load(url); });
		i.addEventListener('input', function () { draw(); if (!players) { load(url).then(function () { if (document.activeElement === i) { draw(); } }); } });
		i.addEventListener('keydown', function (e) {
			var n = u.querySelectorAll('li[role=option]').length;
			if (e.key === 'ArrowDown' && n) { e.preventDefault(); sel = Math.min(n - 1, sel + 1); mark(); }
			else if (e.key === 'ArrowUp' && n) { e.preventDefault(); sel = Math.max(-1, sel - 1); mark(); }
			else if (e.key === 'Enter' && sel >= 0 && !u.hidden) { e.preventDefault(); window.location.href = u.querySelectorAll('li[role=option] a')[sel].href; }
			else if (e.key === 'Escape' && !u.hidden) { e.stopPropagation(); hide(); }
		});
		document.addEventListener('click', function (e) { if (!f.contains(e.target)) { hide(); } });
		f.addEventListener('focusout', function (e) { if (!(e.relatedTarget && f.contains(e.relatedTarget))) { hide(); } });  /* Tab away closes the list; Tab through its links keeps it (Greptile, PR #167) */
	});
})();
