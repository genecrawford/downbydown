"""Build the self-contained style guide.

Reads styleguide/src.html (which references the plugin's CSS, marks.js, the logo and the team data by relative
path so it works inside the repo) and writes styleguide/index.html with all of that inlined, so the one file can
be uploaded anywhere, including https://downbydown.com/styleguide/.

Usage: python3 tools/build_styleguide.py
"""
import json, os, re
root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
rd = lambda *p: open(os.path.join(root, *p), encoding='utf-8').read()
src = rd('styleguide', 'src.html')
css = rd('plugin', 'down-by-down', 'assets', 'dbd.css') + '\n' + rd('plugin', 'down-by-down', 'assets', 'transitions.css')
marks = rd('plugin', 'down-by-down', 'assets', 'marks.js')
teams = json.load(open(os.path.join(root, 'data', 'teams.json')))
logo = rd('Logo', 'dxd-logo.svg')
logo = re.sub(r'<\?xml[^>]*\?>\s*', '', logo).replace('<svg ', '<svg style="height:40px;width:auto;display:block;padding:4px 6px" ', 1)

out = src
out = re.sub(r'<link rel="stylesheet" href="\.\./plugin/down-by-down/assets/dbd\.css">\s*<link rel="stylesheet" href="\.\./plugin/down-by-down/assets/transitions\.css">',
             lambda m: '<style>\n' + css.replace('</', '<\\/') + '\n</style>', out)
out = re.sub(r'<img src="\.\./Logo/dxd-logo\.svg"[^>]*>', lambda m: logo, out)
start = out.index('<script src="../data/teams.js"></script>')
end = out.index('</script>', out.index('<script>', start)) + len('</script>')
inline = ('<script>window.DBD_TEAMS=' + json.dumps(teams, separators=(',', ':')) + ';</script>\n'
          '<script>\n' + marks + '\n</script>\n'
          '<script>\n(function(){var m=window.dbdMark,p=window.dbdPennant;\n'
          "document.getElementById('tiles16').innerHTML=['MIN','KC','PIT','SEA'].map(function(a){return m(a,18)}).join(' ');\n"
          "document.getElementById('tiles24').innerHTML=['MIN','KC','PIT','SEA'].map(function(a){return m(a,24)}).join(' ');\n"
          "document.getElementById('tiles34').innerHTML=['MIN','KC','PIT','SEA'].map(function(a){return m(a,34)}).join(' ');\n"
          "document.getElementById('pen40').innerHTML=p('MIN',40)+' '+p('DET',40);\n"
          "document.getElementById('pen48').innerHTML=p('KC',48);\n"
          "document.getElementById('all32').innerHTML=Object.keys(window.DBD_TEAMS).sort().map(function(a){return '<div>'+m(a,40)+'<br>'+a+'</div>'}).join('');\n"
          '})();\n</script>')
out = out[:start] + inline + out[end:]
assert '../' not in re.sub(r'<style>.*?</style>', '', out, flags=re.S), 'a relative path survived'
open(os.path.join(root, 'styleguide', 'index.html'), 'w', encoding='utf-8').write(out)
print('styleguide/index.html', len(out), 'bytes, self-contained')
