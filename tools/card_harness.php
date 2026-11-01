<?php
/**
 * Render one share card outside WordPress, from the repo's data files, to prove the GD code draws.
 *   php tools/card_harness.php <data dir> <gsis id> <out.png>
 */
if ( php_sapi_name() !== 'cli' ) { exit( 1 ); }
list( , $data, $id, $out ) = $argv + array( null, 'data', '00-0036322', 'card.png' );
define( 'ABSPATH', __DIR__ ); define( 'DBD_VERSION', 'harness' );
$plugin = __DIR__ . '/../plugin/down-by-down/';
require $plugin . 'includes/class-dbd-marks.php';
require $plugin . 'includes/class-dbd-grades.php';
require $plugin . 'includes/class-dbd-card.php';
$j = function ( $rel ) use ( $data ) { $f = rtrim( $data, '/' ) . '/' . $rel; if ( ! is_readable( $f ) ) { fwrite( STDERR, "missing $f\n" ); exit( 2 ); } return json_decode( file_get_contents( $f ), true ); };
$idx = $j( 'players/index.json' ); $row = null; foreach ( $idx['players'] as $p ) { if ( $p['id'] === $id ) { $row = $p; break; } }
if ( ! $row ) { fwrite( STDERR, "no player $id in the index\n" ); exit( 2 ); }
$teamf = $j( 'players/' . $row['team'] . '.json' ); $rec = null; foreach ( $teamf['players'] as $p ) { if ( $p['id'] === $id ) { $rec = $p; break; } }
$teams = $j( 'teams.json' ); $league = $j( 'players/league.json' ); $grp = DBD_Grades::group_of( $row['pos'] ); $gp = DBD_Grades::pool_of( $row['pos'] ); $gi = $league['groups'][ $gp ] ?? $league['groups'][ $grp ] ?? array();  /* SCOPE §38: the pool, or the combined group in an older file */
if ( ! DBD_Card::available() ) { fwrite( STDERR, "GD with FreeType is not available\n" ); exit( 3 ); }
$spec = DBD_Card::spec( $rec, $row, $teams[ $row['team'] ] ?? array(), $gi, $idx['season'], $idx['through'] );
$photo = ! empty( $rec['photo'] ) ? @file_get_contents( $rec['photo'], false, stream_context_create( array( 'http' => array( 'timeout' => 10 ) ) ) ) : null;
$tl = getenv( 'DBD_HARNESS_LOGOS' ) && ! empty( $teams[ $row['team'] ]['logo'] ) ? @file_get_contents( $teams[ $row['team'] ]['logo'] ) : null;
$im = DBD_Card::draw( $spec, array( 'fonts' => $plugin . 'assets/fonts/', 'logo' => $plugin . 'assets/card-logo.png', 'photo' => $photo ?: null, 'team_logo' => $tl ?: null ) );
if ( ! $im ) { fwrite( STDERR, "draw() returned nothing\n" ); exit( 4 ); }
imagepng( $im, $out, 6 ); imagedestroy( $im );
$sz = getimagesize( $out ); echo "wrote $out {$sz[0]}x{$sz[1]} for {$rec['name']} (" . ( $photo ? 'headshot' : 'no headshot' ) . ")\n";
if ( $sz[0] !== 1200 || $sz[1] !== 630 ) { exit( 5 ); }
