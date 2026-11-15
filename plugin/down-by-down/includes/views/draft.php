<?php
/** The draft board (SCOPE §63): every pick of one draft, filterable by round, position and team, every column sortable (assets/boards.js). */
if ( ! defined( 'ABSPATH' ) ) { exit; }
DBD_Data::use_season( null );  /* the boards span every year: links and the switcher stay in the current season even under an archived address (Greptile, PR #112) */
$r = DBD_Router::$route; $Y = (int) $r['y']; $B = DBD_Boards::get( 'draft', $Y );
echo DBD_Router::topbar( array( 'draft' => DBD_Router::url( array( 'view' => 'draft' ) ), (string) $Y => null ) );
echo '<main class="dbd-content dbd-boards">';
if ( ! $B || ! $B['rows'] ) { echo '<div class="dbd-empty"><h2>No picks yet</h2><p>This draft fills in over draft weekend.</p></div></main>'; return; }
$rows = $B['rows']; $T = DBD_Data::teams(); $ys = DBD_Boards::years( 'draft' ); $newest = $Y === ( $ys[0] ?? $Y );
$nm = function ( $a ) use ( $T ) { return $T[ $a ]['nick'] ?? $a; };
$cnt = function ( $k ) use ( $rows ) { $c = array(); foreach ( $rows as $x ) { $v = (string) $x[ $k ]; $c[ $v ] = ( $c[ $v ] ?? 0 ) + 1; } arsort( $c ); return $c; };
$pos = $cnt( 'pos' ); $sch = $cnt( 'sc' ); $fr = $cnt( 'f' ); unset( $sch[''] );
$first = $rows[0]; $mp = array_key_first( $pos ); $ms = array_key_first( $sch ); $mt = array_key_first( $fr );
$played = count( array_filter( $rows, function ( $x ) { return (int) $x['g'] > 0; } ) );
$rounds = array_values( array_unique( array_map( function ( $x ) { return (int) $x['r']; }, $rows ) ) ); sort( $rounds );
$ORDER = array( 'QB', 'RB', 'WR', 'TE', 'OT', 'G', 'C', 'DE', 'DT', 'LB', 'CB', 'S', 'K', 'P', 'LS' );
uksort( $pos, function ( $a, $b ) use ( $ORDER ) { $i = array_search( $a, $ORDER, true ); $j = array_search( $b, $ORDER, true ); return ( $i === false ? 99 : $i ) <=> ( $j === false ? 99 : $j ) ?: strcmp( $a, $b ); } );
$so = $newest ? 'So far' : 'Career';

/* ---- the head: a sentence from the data, and the year switcher */
$say = 'The ' . esc_html( $nm( $first['f'] ) ) . ' took <b>' . esc_html( $first['n'] ) . '</b> first. ' . esc_html( $mp ) . 's went most often (<b>' . (int) $pos[ $mp ] . '</b>)'
	. ( $ms ? ', and <b>' . esc_html( $ms ) . '</b> sent the most players (' . (int) $sch[ $ms ] . ')' : '' ) . '. The ' . esc_html( $nm( $mt ) ) . ' made the most picks (' . (int) $fr[ $mt ] . '). '
	. ( $newest ? '<b>' . $played . '</b> of the ' . count( $rows ) . ' have played a snap so far.' : '<b>' . $played . '</b> of the ' . count( $rows ) . ' played in the NFL.' );
echo '<div class="bd-head"><div><p class="dbd-kicker">The ' . $Y . ' NFL Draft · ' . count( $rows ) . ' picks · ' . count( $rounds ) . ' rounds</p><h1>The <em>' . $Y . '</em> draft</h1><p class="bd-say">' . $say . '</p></div>' . DBD_Boards::controls( 'draft', $Y ) . '</div>';

/* ---- filters (the §61 panel): round and position chips, a team list; boards.js applies them and writes the count */
$chip = function ( $v, $label, $on = false ) { return '<button class="bd-chip" type="button" data-v="' . esc_attr( $v ) . '" aria-pressed="' . ( $on ? 'true' : 'false' ) . '">' . $label . '</button>'; };
echo '<section class="dbd-panel bd-filters"><div class="dbd-ph"><span class="dbd-lab"><b>Filter the picks</b> · <span class="bd-count" data-unit="picks"></span></span><button class="dbd-reset" type="button" data-reset hidden>Reset</button></div>';
echo '<div class="fr"><span class="fl">Round</span><div class="bd-chips" data-filter="r">' . $chip( '', 'All', true ) . implode( '', array_map( function ( $x ) use ( $chip ) { return $chip( (string) $x, 'Round ' . $x ); }, $rounds ) ) . '</div></div>';
echo '<div class="fr"><span class="fl">Position</span><div class="bd-chips" data-filter="pos">' . $chip( '', 'All', true ); foreach ( $pos as $p => $c ) { echo $chip( $p, esc_html( $p ) . ' <small>' . (int) $c . '</small>' ); } echo '</div></div>';
$tn = array(); foreach ( $fr as $a => $c ) { $tn[ $a ] = $T[ $a ]['name'] ?? $a; } asort( $tn );
echo '<div class="fr"><span class="fl">Team</span><div><select data-filter="f" aria-label="Team"><option value="">All teams</option>'; foreach ( $tn as $a => $n ) { echo '<option value="' . esc_attr( $a ) . '">' . esc_html( $n ) . ' (' . (int) $fr[ $a ] . ')</option>'; } echo '</select></div></div></section>';

/* ---- every pick, by round */
echo '<div class="bd-grid"><section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>Every pick</b> · click a column to sort</span><span class="dbd-badge">' . ( $Y >= 2000 ? 'Combine: 0 to 100 against his position' : 'The combine data starts in 2000' ) . '</span></div><div class="bd-tw"><table class="bd-t">';
echo '<thead><tr><th data-k="p" class="n">Pick</th><th data-k="f">Team</th><th data-k="n">Player</th><th data-k="pos">Pos</th><th data-k="sc">School</th><th class="n" data-k="cs">Combine</th><th data-k="w">' . $so . '</th></tr></thead><tbody>';
$cur = null;
foreach ( $rows as $x ) {
	if ( (int) $x['r'] !== $cur ) { $cur = (int) $x['r']; echo '<tr class="rd" data-rd="' . $cur . '"><td colspan="7">Round ' . $cur . '</td></tr>'; }
	$name = $x['pg'] && $x['id'] ? '<a href="' . esc_url( DBD_Router::url( array( 'player' => $x['id'] ) ) ) . '">' . esc_html( $x['n'] ) . '</a>' : '<b>' . esc_html( $x['n'] ) . '</b>';
	$hon = ( $x['hof'] ? ' <span class="bd-hof" title="Pro Football Hall of Fame">HOF</span>' : '' );
	$ln = DBD_Boards::line( $x ); $cs = $x['cs'];
	echo '<tr data-p="' . (int) $x['p'] . '" data-r="' . (int) $x['r'] . '" data-pos="' . esc_attr( $x['pos'] ) . '" data-f="' . esc_attr( $x['f'] ) . '" data-n="' . esc_attr( $x['n'] ) . '" data-sc="' . esc_attr( $x['sc'] ) . '" data-cs="' . ( $cs === null ? '' : (int) $cs ) . '" data-w="' . DBD_Boards::weight( $x ) . '">'
		. '<td class="pk n">' . (int) $x['p'] . '<small>Rd ' . (int) $x['r'] . '</small></td><td class="tm">' . DBD_Boards::logo( $x['f'] ) . esc_html( $x['t'] ) . '</td><td class="nm">' . $name . $hon . '</td><td>' . esc_html( $x['pos'] ) . '</td><td>' . esc_html( $x['sc'] ) . '</td>'
		. '<td class="n">' . ( $cs === null ? '<span class="bd-sc">–</span>' : '<span class="bd-sc" style="' . esc_attr( DBD_Boards::heat( $cs ) ) . '">' . (int) $cs . '</span>' ) . '</td>'
		. '<td class="so' . ( $ln === '' ? ' none' : '' ) . '">' . ( $ln === '' ? ( $newest ? 'hasn\'t played' : 'didn\'t play' ) : esc_html( $ln ) ) . ( (int) $x['pb'] ? ' <span class="bd-pb">' . (int) $x['pb'] . 'x Pro Bowl</span>' : '' ) . '</td></tr>';
}
echo '</tbody></table></div><p class="dbd-note">Picks, schools and each player\'s numbers from nflverse\'s draft data (Pro Football Reference)' . ( $newest ? ', updated through the season' : ', over his whole career' ) . '. The combine score averages his percentiles against every player at his position tested since 2000; a dash means he didn\'t test at the combine' . ( $Y < 2000 ? ', and the data starts in 2000' : '' ) . '. Teams are as they were that year, with today\'s logo. A linked name has a player page.</p></section>';

/* ---- beside it: the class's leaders, by position, by school */
$top = function ( $f, $k, $n, $fmt ) use ( $rows ) { $a = array_values( array_filter( $rows, function ( $x ) use ( $f, $k ) { return $f( $x ) && (float) $x[ $k ] > 0; } ) ); usort( $a, function ( $p, $q ) use ( $k ) { return (float) $q[ $k ] <=> (float) $p[ $k ]; } ); return array_slice( $a, 0, $n ); };
$li = function ( $a, $k, $fmt ) { $o = ''; foreach ( $a as $x ) { $o .= '<li><span>' . esc_html( $x['n'] ) . '<small>' . esc_html( $x['pos'] . ' · ' . $x['t'] . ' · pick ' . $x['p'] ) . '</small></span><b>' . esc_html( $fmt( $x[ $k ] ) ) . '</b></li>'; } return $o; };
$int = function ( $v ) { return number_format( (int) $v ); };
$lists = array( array( 'Passing yards', $top( function ( $x ) { return $x['cat'] === 'QB'; }, 'py', 3, $int ), 'py', $int ), array( 'Receiving yards', $top( function ( $x ) { return in_array( $x['cat'], array( 'WR', 'TE', 'RB' ), true ); }, 'recy', 5, $int ), 'recy', $int ),
	array( 'Rushing yards', $top( function ( $x ) { return in_array( $x['cat'], array( 'RB', 'QB' ), true ); }, 'ry', 3, $int ), 'ry', $int ), array( 'Sacks', $top( function ( $x ) { return $x['side'] === 'D'; }, 'sk', 3, $int ), 'sk', function ( $v ) { return (string) ( 0 + $v ); } ),
	array( 'Interceptions', $top( function ( $x ) { return $x['side'] === 'D'; }, 'int', 3, $int ), 'int', $int ) );
$lh = ''; foreach ( $lists as $L ) { if ( $L[1] ) { $lh .= '<h4 class="bd-h4">' . $L[0] . '</h4><ul class="bd-list">' . $li( $L[1], $L[2], $L[3] ) . '</ul>'; } }
echo '<aside class="bd-side">';
if ( $lh ) { echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>' . ( $newest ? 'Early returns' : 'Class leaders' ) . '</b> · ' . ( $newest ? 'the class so far' : 'over their careers' ) . '</span></div><div class="dbd-pb">' . $lh . '</div></section>'; }
$mx = max( $pos ); $bars = ''; $pp = $pos; arsort( $pp ); foreach ( array_slice( $pp, 0, 10, true ) as $p => $c ) { $bars .= '<div class="bd-bar"><span>' . esc_html( $p ) . '</span><i style="width:' . round( 100 * $c / $mx ) . '%"></i><b>' . (int) $c . '</b></div>'; }
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>By position</b></span></div><div class="dbd-pb"><div class="bd-bars">' . $bars . '</div></div></section>';
$sl = ''; foreach ( array_slice( $sch, 0, 8, true ) as $s => $c ) { $sl .= '<li><span>' . esc_html( $s ) . '</span><b>' . (int) $c . '</b></li>'; }
echo '<section class="dbd-panel"><div class="dbd-ph"><span class="dbd-lab"><b>By school</b></span></div><div class="dbd-pb"><ul class="bd-list">' . $sl . '</ul></div></section></aside></div>';
echo DBD_Boards::next_link( 'draft', $Y );  /* the combine, big and obvious (2.23.1) */
echo '</main>';
