/* Down by Down team marks: the jersey tile. Generated from a team's two colors and abbreviation, nothing else.
   Production: the same function in PHP (server views) and JS (explorer), inline SVG, no image files. */
(function(){
  function lum(hex){var c=[1,3,5].map(function(i){var v=parseInt(hex.slice(i,i+2),16)/255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4);});return .2126*c[0]+.7152*c[1]+.0722*c[2];}
  function contrast(a,b){var x=lum(a),y=lum(b),h=Math.max(x,y),l=Math.min(x,y);return (h+.05)/(l+.05);}
  function ink(c1,c2){return contrast(c2,c1)>=3?c2:contrast('#ffffff',c1)>=3?'#ffffff':'#121417';}
  window.dbdMark=function(abbr,size){
    var t=(window.DBD_TEAMS||{})[abbr]; if(!t) return '';
    var s=size||24, fs=abbr.length>2?13:16;
    return '<svg class="mk" width="'+s+'" height="'+s+'" viewBox="0 0 40 40" role="img" aria-label="'+t.name+'">'+
      '<rect width="40" height="40" rx="4" fill="'+t.c1+'"/><rect y="33" width="40" height="7" fill="'+t.c2+'"/>'+
      '<text x="20" y="25" text-anchor="middle" font-size="'+fs+'" font-family="Tourney,Arial Black,sans-serif" font-weight="800" fill="'+ink(t.c1,t.c2)+'">'+abbr+'</text></svg>';
  };
  /* the pennant: hero mark next to a team name on the explorer and matchup titles */
  window.dbdPennant=function(abbr,height){
    var t=(window.DBD_TEAMS||{})[abbr]; if(!t) return '';
    var h=height||40, w=Math.round(h*1.6), fs=abbr.length>2?13:15;
    return '<svg class="mk pn" width="'+w+'" height="'+h+'" viewBox="0 0 64 40" role="img" aria-label="'+t.name+'">'+
      '<polygon points="0,0 64,20 0,40" fill="'+t.c1+'"/><polygon points="0,0 9,2.8 9,37.2 0,40" fill="'+t.c2+'"/>'+
      '<text x="14" y="25.5" font-size="'+fs+'" font-family="Tourney,Arial Black,sans-serif" font-weight="800" fill="'+ink(t.c1,t.c2)+'">'+abbr+'</text></svg>';
  };
})();
