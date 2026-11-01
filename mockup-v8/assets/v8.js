/* Mock for SCOPE §31 (readability and scanning). Rearranges the live 1.6.0 player page in the browser before player.js runs:
   nothing new is computed, every number on the page is still there; it is regrouped, folded and restyled. */
(function () {
  var root = document.querySelector('.dbd-player'); if (!root) return;
  var $ = function (s, r) { return (r || root).querySelector(s); }, $$ = function (s, r) { return Array.prototype.slice.call((r || root).querySelectorAll(s)); };
  var panelBy = function (title) { return $$('.dbd-panel').filter(function (p) { var b = p.querySelector('.dbd-ph .dbd-lab b'); return b && b.textContent.trim() === title; })[0]; };
  var firstSentence = function (el) { if (!el) return ''; var h = el.innerHTML; var m = h.match(/^(.*?[.!?])(\s|$)/); return m ? m[1] : h; };
  var el = function (tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; };

  /* 1. The short version: one line per band, taken from the reads the page already writes */
  var lines = [
    ['How good', firstSentence($('.dbd-ep-say')), '#dbd-good'],
    ['Where it comes from', ($('.dbd-ep-say') ? ($('.dbd-ep-say').innerHTML.split(/(?<=[.])\s/)[1] || '') : ''), '#dbd-used'],
    ['How he\'s used', firstSentence($('.dbd-fp-say')), '#dbd-used'],
    ['Lately', firstSentence($('.dbd-tr-say')), '#dbd-weeks'],
    ['This week', firstSentence($('.dbd-week-say')), '#dbd-weeks']
  ].filter(function (l) { return l[1]; });
  var sv = el('section', 'dbd-panel v8-short mk-new');
  sv.setAttribute('data-mk', 'New · the short version');
  sv.innerHTML = '<div class="dbd-ph"><span class="dbd-lab"><b>The short version</b></span></div><ul>' + lines.map(function (l) { return '<li><a href="' + l[2] + '">' + l[0] + '</a><span>' + l[1] + '</span></li>'; }).join('') + '</ul>';
  $('.dbd-tiles').after(sv);

  /* 2. Scorecard: Where he ranks and Season stats were the same stats twice; one table, value, rank and the league bar together */
  var whr = panelBy('Where he ranks'), ss = panelBy('Season stats');
  if (whr && ss) {
    var bars = {}; $$('.dbd-pct .row', whr).forEach(function (r) { var l = r.querySelector('.l'); var name = l.childNodes[0].textContent.trim(); bars[name] = r; });
    var rows = $$('.dbd-sstat tr', ss).filter(function (tr) { return tr.children[2] && tr.children[2].textContent.trim(); }).map(function (tr) {  /* unranked counting stats already sit on the hero tiles */
      var td = tr.children, name = td[0].textContent.trim(), b = bars[name];
      var bar = b ? b.querySelector('.bar').outerHTML : '<div class="bar"></div>', pc = b ? b.querySelector('.g') : null, sm = b && b.querySelector('.l small') ? b.querySelector('.l small').textContent : '';
      return '<tr' + (tr.className ? ' class="' + tr.className + '"' : '') + '><td class="n">' + name + (sm ? '<small>' + sm + '</small>' : '') + '</td><td class="v">' + td[1].innerHTML + '</td><td class="rk">' + td[2].innerHTML + '</td><td class="b">' + bar + '</td><td class="g' + (pc ? ' ' + pc.className.replace('g', '').trim() : '') + '">' + (pc ? pc.textContent : '') + '</td></tr>';
    });
    var half = Math.ceil(rows.length / 2);
    var sc = el('section', 'dbd-panel v8-score mk-new');
    sc.setAttribute('data-mk', 'Merged · Where he ranks + Season stats');
    sc.innerHTML = whr.querySelector('.dbd-ph').outerHTML.replace('<b>Where he ranks</b>', '<b>Scorecard</b>') +
      '<div class="v8-score2"><table class="dbd-stat"><thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead><tbody>' + rows.slice(0, half).join('') + '</tbody></table>' +
      '<table class="dbd-stat"><thead><tr><th>Stat</th><th>Value</th><th>Rank</th><th>League</th><th>Pctl</th></tr></thead><tbody>' + rows.slice(half).join('') + '</tbody></table></div>' +
      (whr.querySelector('.dbd-note') ? whr.querySelector('.dbd-note').outerHTML : '');
    var two = whr.parentElement; two.replaceWith(sc);
  }

  /* 3. Bands become one panel each, with tabs; every panel is still rendered, the tab only picks which one shows */
  var tabbed = function (bandId, title, specs) {
    var band = document.getElementById(bandId); if (!band) return;
    var box = el('section', 'dbd-panel v8-tabbed mk-new'); box.setAttribute('data-mk', 'Tabs · ' + specs.length + ' panels in one');
    var bar = el('div', 'dbd-seg t-tabs v8-tabs'); bar.setAttribute('role', 'tablist'); bar.innerHTML = '<span class="t-tabs-pill" aria-hidden="true"></span>';
    var head = el('div', 'dbd-ph v8-tabh'); head.appendChild(bar); box.appendChild(head);  /* the band heading above already names it */
    specs.forEach(function (sp, i) {
      var p = typeof sp[1] === 'string' ? panelBy(sp[1]) : sp[1]; if (!p) return;
      var holder = p.parentElement && p.parentElement.classList.contains('dbd-two') ? p.parentElement : null;
      var b = el('button', 't-tab', sp[0]); b.type = 'button'; b.setAttribute('role', 'tab'); b.setAttribute('aria-selected', i ? 'false' : 'true'); bar.appendChild(b);
      var lab = p.querySelector('.dbd-ph .dbd-lab'); if (lab) { var bt = lab.querySelector('b'); if (bt) { bt.remove(); lab.innerHTML = lab.innerHTML.replace(/^\s*·\s*/, ''); } }
      p.classList.add('v8-pane'); p.hidden = !!i; p.removeAttribute('hidden'); if (i) p.setAttribute('hidden', '');
      box.appendChild(p); if (holder && !holder.querySelector('.dbd-panel')) holder.remove();
      b.addEventListener('click', function () { $$('.t-tab', bar).forEach(function (x) { x.setAttribute('aria-selected', x === b ? 'true' : 'false'); }); $$('.v8-pane', box).forEach(function (x) { x.hidden = x !== p; }); pill(b, true); window.dispatchEvent(new Event('resize')); });
    });
    var pill = function (b, anim) { var pl = bar.querySelector('.t-tabs-pill'); if (!b || !pl) return; if (!anim) pl.style.transition = 'none'; pl.style.transform = 'translateX(' + b.offsetLeft + 'px)'; pl.style.width = b.offsetWidth + 'px'; if (!anim) { void pl.offsetWidth; pl.style.transition = ''; } };
    band.querySelector('.dbd-bandh').after(box);
    requestAnimationFrame(function () { pill(bar.querySelector('[aria-selected="true"]'), false); });
    window.addEventListener('resize', function () { pill(bar.querySelector('[aria-selected="true"]'), false); });
  };
  var week = $('.dbd-week');
  tabbed('dbd-used', 'How he\'s used', [['Playstyle', 'Playstyle'], ['Where his EPA comes from', 'Where his EPA comes from'], ['Depth by direction', 'Depth by direction'], ['On the field', $('#dbd-pmap-panel')], ['Situations', 'When he gets it']]);
  if (week) { var wb = document.getElementById('dbd-weeks'); wb.querySelector('.dbd-bandh').after(week); }
  tabbed('dbd-weeks', 'Week by week', [['This week', week], ['Game log', 'Game log'], ['The trend', 'The shape of his season'], ['Defenses faced', 'Who he\'s faced']]);

  /* 4. Methods notes fold under "How this is measured"; the reads stay open */
  $$('.dbd-panel .dbd-note').forEach(function (n) {
    if (n.textContent.trim().split(/\s+/).length < 14 || n.closest('details')) return;
    var d = el('details', 'v8-how'); d.innerHTML = '<summary>How this is measured</summary>'; n.before(d); d.appendChild(n);
  });

  /* 5. Wide tables: the connection shows its core columns; the rest behind a toggle (it is drawn later by player.js) */
  var cx = $('#dbd-cx');
  if (cx) { var t = el('button', 'v8-more', 'All columns'); t.type = 'button'; t.addEventListener('click', function () { cx.classList.toggle('v8-all'); t.textContent = cx.classList.contains('v8-all') ? 'Core columns' : 'All columns'; }); cx.querySelector('.dbd-ph').appendChild(t); }

  /* jump nav follows the new order */
  var jn = $('.dbd-jump'); if (jn) { var w = jn.querySelector('a[href="#dbd-weeks"]'); if (w) w.textContent = 'Week by week'; }
})();
