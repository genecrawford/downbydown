"""Builds the two mock pages from the saved live page: before.html (1.6.0 as it is) and after.html (SCOPE §31)."""
root = open('src/allen-root.html').read(); js = open('src/allen-root.js.html').read()
head = ('<!doctype html><html class="no-js"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>%s</title>'
        '<link href="https://fonts.googleapis.com/css2?family=Tourney:wght@800&family=Barlow+Condensed:wght@500;600;700&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap" rel="stylesheet">'
        '<link rel="stylesheet" href="assets/dbd.css"><link rel="stylesheet" href="assets/transitions.css"><link rel="stylesheet" href="assets/v8.css"></head>')
bar = ('<div class="mk-bar"><b>Mock · readability and scanning</b><span>Josh Allen\'s live 1.6.0 page. Same data, regrouped and restyled.</span>'
       '<a href="before.html"%s>Before (live today)</a><a href="after.html"%s>After</a>%s</div>')
tail = '<script src="assets/dbd-site.js"></script><script src="assets/marks.js"></script>%s<script src="assets/player.js"></script>'
tog = '<label><input type="checkbox" id="mk-toggle" checked> Show outlines</label>'
open('before.html', 'w').write(head % 'Before · Josh Allen' + '<body class="dbd-app">' + bar % (' aria-current="page"', '', '') + '<div class="entry-content">' + root + '</div>' + js + tail % '' + '</body></html>')
open('after.html', 'w').write(head % 'After · Josh Allen' + '<body class="dbd-app v8">' + bar % ('', ' aria-current="page"', tog) + '<div class="entry-content">' + root + '</div>' + js + tail % '<script src="assets/v8.js"></script>'
      + '<script>document.getElementById("mk-toggle").addEventListener("change",function(e){document.body.classList.toggle("mk-off",!e.target.checked);});</script></body></html>')
print('ok')
