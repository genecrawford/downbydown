/* Down by Down team marks for the explorer: the jersey tile and the pennant, generated from a team's two colors
   and abbreviation. Mirrors includes/class-dbd-marks.php exactly. */
(function () {
  function teams() { return window.DBD_TEAMS || (window.DBD_TEAM && window.DBD_TEAM.teams) || {}; }
  function lum(hex) { var c = [1, 3, 5].map(function (i) { var v = parseInt(hex.slice(i, i + 2), 16) / 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }); return .2126 * c[0] + .7152 * c[1] + .0722 * c[2]; }
  function contrast(a, b) { var x = lum(a), y = lum(b), h = Math.max(x, y), l = Math.min(x, y); return (h + .05) / (l + .05); }
  function ok(c) { return /^#[0-9a-fA-F]{6}$/.test(c || ''); }
  function ink(c1, c2) { return contrast(c2, c1) >= 3 ? c2 : contrast('#ffffff', c1) >= 3 ? '#ffffff' : '#121417'; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  var F = 'font-family="Tourney,Arial Black,sans-serif" font-weight="800"';
  /* the real logo over the drawn mark when logos are on (SCOPE §28): the mark shows until the image loads; a failed image removes itself */
  function withLogo(svg, abbr, w, h) {
    var t = teams()[abbr], u = t && t.logo;
    if (!window.DBD_LOGOS_ON || !svg || !u || !/^https:\/\/[^"'<>\s]+$/.test(u)) return svg;
    var sc = Math.max(0.7, Math.min(1.3, +t.logo_s || 1));  /* optical scale from the build (1.4.1) */
    return '<span class="dbd-lgw" style="width:' + w + 'px;height:' + h + 'px;--lgs:' + sc.toFixed(2) + '">' + svg + '<img class="dbd-logo" src="' + esc(u) + '" alt="' + esc(t.name) + '" width="' + h + '" height="' + h + '" loading="lazy" decoding="async" referrerpolicy="no-referrer" onload="this.previousElementSibling.style.visibility=\'hidden\'" onerror="this.remove()"></span>';
  }
  window.dbdMark = function (abbr, size) { var s = size || 24; return withLogo(markSvg(abbr, s), abbr, s, s); };
  function markSvg(abbr, size) {
    var t = teams()[abbr]; if (!t) return '';
    var c1 = ok(t.c1) ? t.c1 : '#161e36', c2 = ok(t.c2) ? t.c2 : '#cb2026', s = size || 24, fs = abbr.length > 2 ? 13 : 16;
    return '<svg class="dbd-mk" width="' + s + '" height="' + s + '" viewBox="0 0 40 40" role="img" aria-label="' + esc(t.name) + '"><rect width="40" height="40" rx="4" fill="' + c1 + '"/><rect y="33" width="40" height="7" fill="' + c2 + '"/><text x="20" y="25" text-anchor="middle" font-size="' + fs + '" ' + F + ' fill="' + ink(c1, c2) + '">' + esc(abbr) + '</text></svg>';
  }
  window.dbdJersey = function (abbr, num, size) {
    var t = teams()[abbr]; if (!t) return '';
    var c1 = ok(t.c1) ? t.c1 : '#161e36', c2 = ok(t.c2) ? t.c2 : '#cb2026', s = size || 40, n = num == null ? '' : String(num), fs = n.length > 1 ? 19 : 21, k = ink(c1, c2);
    return '<svg class="dbd-mk dbd-jersey" width="' + s + '" height="' + s + '" viewBox="0 0 40 40" role="img" aria-label="' + esc(t.nick + ' #' + n) + '"><rect width="40" height="40" rx="5" fill="' + c1 + '"/><rect x="2.5" y="2.5" width="35" height="35" rx="3.5" fill="none" stroke="' + k + '" stroke-opacity=".5" stroke-width="1.5"/><text x="20" y="27" text-anchor="middle" font-size="' + fs + '" ' + F + ' fill="' + k + '">' + esc(n) + '</text></svg>';
  };
  window.dbdPlayer = function (abbr, num, size, photo, allow) {
    var tile = window.dbdJersey(abbr, num, size), t = teams()[abbr];
    if (!tile || !allow || !photo || !/^https:\/\/[^"'<>\s]+$/.test(photo)) return tile;
    var s = size || 40, c1 = ok(t.c1) ? t.c1 : '#161e36', n = s * 2;
    var src = photo.replace(/^(https:\/\/static\.www\.nfl\.com\/image\/upload\/)f_auto,q_auto\//, '$1f_auto,q_auto,w_' + n + ',h_' + n + ',c_fill,g_face/');  /* face-cropped square at 2x, about 6 KB instead of 4 MB */
    return '<span class="dbd-head" style="width:' + s + 'px;height:' + s + 'px">' + tile + '<img src="' + esc(src) + '" alt="" width="' + s + '" height="' + s + '" loading="lazy" decoding="async" referrerpolicy="no-referrer" style="background:' + c1 + '" onerror="this.remove()"></span>';
  };
  window.dbdPennant = function (abbr, height) { var h = height || 40; return withLogo(pennantSvg(abbr, h), abbr, Math.round(h * 1.6), h); };
  function pennantSvg(abbr, height) {
    var t = teams()[abbr]; if (!t) return '';
    var c1 = ok(t.c1) ? t.c1 : '#161e36', c2 = ok(t.c2) ? t.c2 : '#cb2026', h = height || 40, w = Math.round(h * 1.6), fs = abbr.length > 2 ? 13 : 15;
    return '<svg class="dbd-mk dbd-pn" width="' + w + '" height="' + h + '" viewBox="0 0 64 40" role="img" aria-label="' + esc(t.name) + '"><polygon points="0,0 64,20 0,40" fill="' + c1 + '"/><polygon points="0,0 9,2.8 9,37.2 0,40" fill="' + c2 + '"/><text x="14" y="25.5" font-size="' + fs + '" ' + F + ' fill="' + ink(c1, c2) + '">' + esc(abbr) + '</text></svg>';
  }
})();
