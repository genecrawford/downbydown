<?php
/**
 * The Share pack tab (2.27.0, SCOPE §68; laid out as a week calendar in 2.45.0, SCOPE §87). Three views under one tab:
 *   Schedule            the week Tuesday to Monday, 9:00 AM and 5:00 PM, one card a post with a mark per network; a card opens the post below it
 *   Keys to the game    one row a game, opened when needed (these aren't on the schedule)
 *   Accounts and email  one row a network, its credentials folded, then the by-hand accounts and the Tuesday email
 * The page only reads the posting records and offers the same actions; every form keeps its field names, so what posts, when and where don't change.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The email's state in one line: [ dot class, text, Try-again job key or '' ]. */
function dbd_pack_mail_line() {
	if ( (string) DBD_Email::get( 'key' ) === '' ) { return array( 'bad', 'The Tuesday email isn’t set up: Brevo needs its API key on Week in review.', '' ); }
	$ps = DBD_Pack_Mail::settings();
	if ( ! $ps['on'] ) { return array( 'bad', 'The Tuesday email is switched off.', '' ); }
	if ( ! $ps['to'] ) { return array( 'bad', 'The Tuesday email has no one to send to.', '' ); }
	$st = DBD_Pack_Mail::status(); $l = $st ? $st[ count( $st ) - 1 ] : null;
	if ( ! $l ) { return array( 'ok', 'Each new pack is emailed on its Tuesday at 7:00 AM to ' . count( $ps['to'] ) . ( count( $ps['to'] ) === 1 ? ' person.' : ' people.' ), '' ); }
	return array( $l[0], $l[1], $l[2] );
}

/* ------------------------------------------------------------------ what the calendar reads */

/** The four networks that post by themselves, in the order their marks show: key => array( name, mark, class ). */
function dbd_share_nets() {
	return array( 'ig' => array( 'Instagram', 'IG', 'DBD_Instagram' ), 'bs' => array( 'Bluesky', 'BS', 'DBD_Bluesky' ), 'x' => array( 'X', 'X', 'DBD_X' ), 'th' => array( 'Threads', 'TH', 'DBD_Threads' ) );
}
/** A time on the schedule's clock, whatever the site's time zone is. */
function dbd_share_et( $ts, $fmt ) { return wp_date( $fmt, (int) $ts, new DateTimeZone( DBD_Instagram::TZ ) ); }

/**
 * One network's state for one post: class (posted, scheduled, skipped, failed, idle), word for its mark, line for the post's table, View link, Try-again URL,
 * and when it's due. $kind: 'week' a post of a finished week, 'planned' a week still to come, 'extra' an item that only posts by hand or Auto publish.
 */
function dbd_share_state( $key, $net, $t, $kind = 'week' ) {
	$c = dbd_share_nets()[ $net ][2]; $j = $c::job( $key ); $on = (bool) $c::on(); $when = function ( $ts ) { return dbd_share_et( $ts, 'D g:i A' ); };
	$S = function ( $class, $word, $line, $link = '', $again = '', $at = 0 ) { return array( 'class' => $class, 'word' => $word, 'line' => $line, 'link' => $link, 'again' => $again, 'at' => (int) $at ); };
	if ( ! $j ) {
		if ( $kind === 'extra' ) { return $S( 'idle', 'Not posted', 'Not posted' ); }
		if ( ! $on ) { return $S( 'idle', 'Off', 'Weekly posting is off' ); }
		if ( $kind === 'planned' ) { return $S( 'scheduled', 'Planned', 'Planned for ' . $when( $t ) ); }
		return (int) $t > time() ? $S( 'scheduled', 'Not booked', 'Not booked yet' ) : $S( 'idle', 'Not posted', 'Not posted' );
	}
	$st = (string) ( $j['state'] ?? '' );
	if ( $st === 'sent' ) {
		$by = method_exists( $c, 'by_button' ) ? $c::by_button( $j ) : ! empty( $j['auto'] );
		return $S( 'posted', 'Posted', 'Posted ' . $when( $j['sent_at'] ?? $j['at'] ?? 0 ) . ( $by ? ' with Auto publish' : '' ), (string) ( $j['link'] ?? '' ) );
	}
	if ( $st === 'booked' ) {
		if ( ! empty( $j['auto'] ) ) { return $S( 'scheduled', 'Posting', 'Posting now', '', '', (int) ( $j['at'] ?? 0 ) ); }
		if ( ! $on ) { return $S( 'idle', 'Off', 'Weekly posting is off' ); }  /* its event will find the switch off and not post */
		return $S( 'scheduled', 'Scheduled', 'Scheduled ' . $when( $j['at'] ?? $t ), '', '', (int) ( $j['at'] ?? $t ) );
	}
	if ( strpos( $st, 'retrying' ) === 0 ) { return $S( 'scheduled', 'Retrying', 'Retrying: ' . substr( $st, 10 ), '', '', (int) ( $j['at'] ?? $t ) ); }
	if ( strpos( $st, 'failed' ) === 0 ) { return $S( 'failed', 'Failed', 'Didn’t post: ' . ( method_exists( $c, 'why' ) ? $c::why( $j ) : substr( $st, 8 ) ), '', $c::again_url( $key ) ); }
	if ( strpos( $st, 'skipped' ) === 0 || strpos( $st, 'not posted' ) === 0 ) { return $S( 'skipped', 'Skipped', ucfirst( $st ) ); }
	return $S( 'idle', 'Not posted', $st !== '' ? ucfirst( $st ) : 'Not posted' );
}

/** Week $n's posts in time order. A week still to come ($ready false) has its planned slots, named, with no graphic yet. */
function dbd_share_posts( $n, $ready ) {
	$plan = $ready ? DBD_Instagram::plan( $n ) : array(); $out = array(); $words = array( 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven' );
	$names = array( 'swing' => 'The biggest swing', 'snaps' => 'A usage riser', 'offense' => 'Best and worst offense', 'targets' => 'A target-share mover', 'leaders' => 'Week leaders', 'board' => 'League Leaders' );
	foreach ( DBD_Pack::BOARD as $sl => $b ) { $names[ $sl ] = (string) $b[0]; }
	$slots = $ready ? array_keys( $plan ) : array_values( array_filter( array_keys( DBD_Instagram::SLOTS ), function ( $s ) use ( $n ) { return ! isset( DBD_Tall::WEEKLY[ $s ] ) || DBD_Tall::weekly( $n ); } ) );
	foreach ( $slots as $sl ) {
		$t = DBD_Instagram::slot_time( $n, $sl ); $its = $plan[ $sl ] ?? array(); $it = $its[0] ?? null; $key = DBD_AutoPub::key( $n, $sl ); $st = array();
		if ( $sl === 'carousel' ) { $c = count( $its ); $name = 'Week ' . (int) $n . ' in ' . ( $c ? ( $words[ $c ] ?? (string) $c ) . ' charts' : 'charts' ); }
		else { $name = $it ? (string) $it['h'] : ( $names[ $sl ] ?? DBD_Instagram::label( $sl ) ); }
		foreach ( array_keys( dbd_share_nets() ) as $net ) { $st[ $net ] = dbd_share_state( $key, $net, $t, $ready ? 'week' : 'planned' ); }
		$out[] = array( 'slot' => $sl, 'name' => $name, 't' => $t, 'day' => (int) DBD_Instagram::SLOTS[ $sl ], 'eve' => isset( DBD_Tall::WEEKLY[ $sl ] ), 'items' => $its, 'it' => $it, 'pic' => $it ? DBD_Tall::preview( $n, $it ) : '', 'st' => $st );
	}
	usort( $out, function ( $a, $b ) { return $a['t'] <=> $b['t']; } );
	return $out;
}

/** The week's items that aren't on the calendar (Pass rushers, or one without a graphic): they post by hand or with the button. */
function dbd_share_extras( $n ) {
	$plan = DBD_Instagram::plan( $n ); $out = array();
	foreach ( DBD_Pack::items( $n ) as $it ) {
		$k = (string) $it['k']; if ( isset( $plan[ $k ] ) ) { continue; }
		$key = DBD_AutoPub::key( $n, $k ); $st = array();
		foreach ( array_keys( dbd_share_nets() ) as $net ) { $st[ $net ] = dbd_share_state( $key, $net, 0, 'extra' ); }
		$out[] = array( 'slot' => $k, 'name' => (string) $it['h'], 't' => 0, 'day' => -1, 'eve' => false, 'items' => array( $it ), 'it' => $it, 'pic' => DBD_Tall::preview( $n, $it ), 'st' => $st );
	}
	return $out;
}

/** The week after the newest pack, when it's a regular-season week with games on the schedule: its calendar shows before it finishes. */
function dbd_share_upcoming( $weeks ) {
	$top = $weeks ? (int) $weeks[0] : 0; $u = $top + 1;
	return $top && $u <= 18 && DBD_Data::week_games( $u ) ? $u : 0;
}

/** Which view to open: the address's, or where the last action's message belongs. */
function dbd_share_view() {
	$v = sanitize_key( (string) ( $_GET['view'] ?? '' ) );
	if ( in_array( $v, array( 'schedule', 'keys', 'accounts' ), true ) ) { return $v; }
	$p = (string) ( $_GET['pack'] ?? '' );
	return $p !== '' && ! in_array( $p, array( 'igposted', 'igagain', 'bsagain', 'xagain', 'thagain', 'igerror' ), true ) ? 'accounts' : 'schedule';
}
function dbd_share_url( $n, $view ) { return add_query_arg( array_filter( array( 'page' => 'down-by-down-share', 'week' => $n ?: null, 'view' => $view === 'schedule' ? null : $view ) ), admin_url( 'admin.php' ) ); }

/** The line at the top: what posts next, when and where, across the newest pack. */
function dbd_share_next( $up ) {
	$n = DBD_Pack::newest(); if ( ! $n ) { return ''; } $best = null; $now = time();
	foreach ( dbd_share_posts( $n, true ) as $p ) { foreach ( $p['st'] as $s ) { if ( $s['class'] === 'scheduled' && $s['at'] && ( ! $best || $s['at'] < $best[0] ) ) { $best = array( $s['at'], $p ); } } }
	if ( ! $best ) { return '<b>' . esc_html( 'Nothing else is scheduled for Week ' . $n . '.' ) . '</b>' . ( $up ? ' ' . esc_html( 'Week ' . $up . '’s posts book when its games finish.' ) : '' ); }
	$nets = array(); foreach ( $best[1]['st'] as $k => $s ) { if ( $s['class'] === 'scheduled' && $s['at'] && abs( $s['at'] - $best[0] ) <= 300 ) { $nets[] = $k; } }
	$day = dbd_share_et( $best[0], 'Y-m-d' ); $today = dbd_share_et( $now, 'Y-m-d' ); $tom = ( new DateTime( $today . ' 12:00:00', new DateTimeZone( DBD_Instagram::TZ ) ) )->modify( '+1 day' )->format( 'Y-m-d' );
	$word = $day === $today ? 'today' : ( $day === $tom ? 'tomorrow' : dbd_share_et( $best[0], 'l' ) );
	$lead = $best[0] <= $now ? 'Next: posting now.' : 'Next: ' . $word . ' at ' . dbd_share_et( $best[0], 'g:i A' ) . '.';
	return '<b>' . esc_html( $lead ) . '</b> ' . esc_html( $best[1]['name'] . ' goes to ' . DBD_AutoPub::names( $nets ) . '.' );
}

/** Anything that needs a look: a failed post, a token running out, the email off. Each is array( dot, text, Try-again URL, needs Accounts ). */
function dbd_share_alerts( $n ) {
	$out = array();
	foreach ( dbd_share_nets() as $N ) { $c = $N[2]; $l = $c::line( $n ); if ( $l && $l[0] === 'bad' ) { $out[] = array( 'bad', (string) $l[1], $l[3] !== '' ? $c::again_url( $l[3] ) : '', $l[3] === '' ); } }
	foreach ( array( 'DBD_Instagram', 'DBD_Threads' ) as $c ) { if ( ( $w = (string) $c::warning() ) !== '' ) { $out[] = array( 'warn', $w, '', true ); } }
	$ml = dbd_pack_mail_line(); if ( $ml[0] === 'bad' ) { $out[] = array( 'bad', $ml[1], $ml[2] !== '' ? DBD_Pack_Mail::again_url( $ml[2] ) : '', $ml[2] === '' ); }
	return $out;
}

/* ------------------------------------------------------------------ the pieces every post uses */

/** The tab's own notices: saved, sent, going again, or what went wrong. */
function dbd_pack_notices() {
	if ( ! isset( $_GET['pack'] ) ) { return; }
	$pk = (string) $_GET['pack']; $pn = (int) ( $_GET['pack_n'] ?? 0 );
	if ( $pk === 'igposted' ) { $x = array( 'success', 'Posted to Instagram.' ); } elseif ( $pk === 'igagain' ) { $x = array( 'success', 'Instagram is trying that post again.' ); } elseif ( $pk === 'bsagain' ) { $x = array( 'success', 'Bluesky is trying that post again.' ); } elseif ( $pk === 'xagain' ) { $x = array( 'success', 'X is trying that post again.' ); } elseif ( $pk === 'thagain' ) { $x = array( 'success', 'Threads is trying that post again.' ); }
	elseif ( $pk === 'igerror' || $pk === 'saveerr' ) { $x = array( 'error', sanitize_text_field( wp_unslash( rawurldecode( (string) ( $_GET['pack_err'] ?? '' ) ) ) ) ); }  /* not the email's wording (Greptile, PR #144) */
	else {
	$x = $pk === 'saved' ? array( 'success', 'Settings saved.' ) : ( $pk === 'sent' ? array( 'success', 'The pack went to ' . $pn . ( $pn === 1 ? ' person.' : ' people.' ) ) : ( $pk === 'again' ? array( 'success', 'The pack email is going again.' ) : array( 'error', 'The pack email didn’t send: ' . sanitize_text_field( wp_unslash( rawurldecode( (string) ( $_GET['pack_err'] ?? '' ) ) ) ) ) ) );
	}
	echo '<div class="notice notice-' . esc_attr( $x[0] ) . ' inline sp-note"><p>' . esc_html( $x[1] ) . '</p></div>';
}

/**
 * The Post now button (Auto publish, SCOPE §81) and its ask (a details box, so it asks without JavaScript too). It names the networks it would post to;
 * with every network done it reads Published. $lines adds a line per network, for a keys side, which has no status table of its own.
 */
function dbd_pack_auto( $n, $it, $lines = false ) {
	$slot = DBD_AutoPub::slot( $n, $it ); if ( $slot === '' ) { return ''; }
	$st = DBD_AutoPub::states( $n, $slot, $it ); if ( ! $st ) { return ''; }
	$ready = DBD_AutoPub::ready( $n, $slot, $it ); $nets = DBD_AutoPub::nets();
	$names = DBD_AutoPub::names( $ready );
	$o = '';
	if ( $ready ) {
		$o .= '<details class="sp-apd"><summary class="button button-primary">' . esc_html( 'Post now to ' . $names ) . '</summary><div class="sp-ask"><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="dbd_auto_publish"><input type="hidden" name="week" value="' . (int) $n . '"><input type="hidden" name="slot" value="' . esc_attr( $slot ) . '">' . wp_nonce_field( 'dbd_ap_' . (int) $n . '_' . $slot, '_wpnonce', true, false )
			. '<p><b>' . esc_html( 'Post “' . DBD_AutoPub::name( $it ) . '” to ' . $names . ' now?' ) . '</b> It goes out right away.</p><button class="button button-primary sp-apgo">Post it now</button> <button type="button" class="button sp-apx">Cancel</button></form></div></details>';
	} elseif ( $nets && ! array_filter( $nets, function ( $x ) use ( $st ) { return ( $st[ $x ][0] ?? '' ) !== 'done'; } ) ) {
		$o .= '<button type="button" class="button" disabled>Published</button>';
	}
	$ln = '';
	if ( $lines ) { foreach ( $st as $s ) { if ( $s[1] !== '' ) { $ln .= ( $ln !== '' ? '<br>' : '' ) . '<span class="dbd-dot ' . esc_attr( $s[3] ) . '"></span>' . esc_html( $s[1] ) . ( $s[2] !== '' ? ' <a href="' . esc_url( $s[2] ) . '" target="_blank" rel="noopener">View</a>' : '' ); } } }
	if ( $o === '' && $ln === '' ) { return ''; }
	return '<div class="sp-ap">' . $o . ( $ln !== '' ? '<p class="sp-aps">' . $ln . '</p>' : '' ) . '</div>';
}

/** One place to post: the site's name as the label, and the full phrase for screen readers. */
function dbd_pack_btn( $cls, $label, $aria, $href = '', $data = '' ) {
	if ( $href === '' && $data === '' ) { return ''; }
	$a = ' class="button ' . esc_attr( $cls ) . '" aria-label="' . esc_attr( $aria ) . '"' . ( $data !== '' ? ' data-t="' . esc_attr( $data ) . '"' : '' );
	return $href !== '' ? '<a' . $a . ' href="' . esc_url( $href ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . '</a>' : '<button type="button"' . $a . '>' . esc_html( $label ) . '</button>';
}

/** The row of places to post, always in this order: r/ours, r/the team's (keys), X, Bluesky, Facebook, Instagram, Graphic (keys, before kickoff), then Threads. */
function dbd_pack_row( $it, $link, $team_sub = '', $square = false, $n = 0 ) {
	$o = ''; $sub = DBD_Pack::our_sub(); $t = DBD_Pack::social( $it );
	if ( $sub !== '' ) { $o .= dbd_pack_btn( 'sp-rd', 'r/' . $sub, 'Post to r/' . $sub, DBD_Pack::reddit_url( $it['title'], $link ) ); }
	if ( $team_sub !== '' && ( $ts = DBD_Pack::sanitize_sub( $team_sub ) ) !== '' ) { $o .= dbd_pack_btn( 'sp-rd', 'r/' . $ts, 'Post to r/' . $ts, DBD_Pack::reddit_url( $it['title'], $link, $ts ) ); }
	$o .= dbd_pack_btn( 'sp-x', 'X', 'Post on X', DBD_Pack::x_url( $t['x'] ) );
	$o .= dbd_pack_btn( 'sp-bs', 'Bluesky', 'Post on Bluesky', DBD_Pack::bsky_url( $t['bsky'] ) );
	if ( ( $fu = DBD_Pack::fb_url( $it ) ) !== '' ) { $o .= dbd_pack_btn( 'sp-fb', 'Facebook', 'Share on Facebook: copies the text, then opens the share box', $fu, DBD_Pack::fb_text( $it ) ); }  /* pack.js copies data-t */
	if ( ( $cap = DBD_Pack::ig_caption( $it ) ) !== '' ) { $o .= dbd_pack_btn( 'sp-cp sp-ig', 'Instagram caption', 'Copy for Instagram: the caption', '', $cap );
		if ( $square && $n && ! empty( $it['sq'] ) ) { $o .= '<a class="button sp-ig" href="' . esc_url( DBD_Tall::preview( $n, $it ) ) . '" download="' . esc_attr( DBD_Tall::file_name( $n, $it ) ) . '" aria-label="Download the graphic">Graphic</a>'; } }
	$o .= dbd_pack_btn( 'sp-th', 'Threads', 'Post on Threads', DBD_Pack::th_url( $it ) );
	return '<div class="sp-row">' . $o . '</div>';
}

/** One text with its Copy button; X and Bluesky shows its length against 280. */
function dbd_pack_field( $lab, $text, $count = false, $rows = 2 ) {
	$n = $count ? DBD_Pack::xlen( $text ) : 0;
	return '<div class="sp-f"><div class="sp-fh"><b>' . esc_html( $lab ) . '</b>' . ( $count ? '<span class="cnt' . ( $n > DBD_Pack::X_MAX ? ' over' : '' ) . '">' . (int) $n . ' of ' . DBD_Pack::X_MAX . '</span>' : '' )
		. '<button type="button" class="button button-small sp-cp">Copy</button></div><textarea readonly rows="' . (int) $rows . '" class="large-text">' . esc_textarea( $text ) . '</textarea></div>';
}

/** The four texts for an item or a keys side, folded away. */
function dbd_pack_texts( $it, $pre = '' ) {
	$o = dbd_pack_field( $pre . 'Reddit title', $it['title'] ) . dbd_pack_field( $pre . 'Reddit first comment, with the link', $it['comment'] ) . dbd_pack_field( $pre . 'X and Bluesky', $it['post'], true );
	if ( ( $cap = DBD_Pack::ig_caption( $it ) ) !== '' ) { $o .= dbd_pack_field( $pre . 'Instagram caption', $cap, false, 5 ); }
	return $o;
}

/* ------------------------------------------------------------------ the Schedule view */

function dbd_share_marks( $p ) {
	$o = '<span class="sp-marks">';
	foreach ( dbd_share_nets() as $k => $N ) { $o .= '<span class="sp-m ' . esc_attr( $p['st'][ $k ]['class'] ) . '" title="' . esc_attr( $N[0] . ': ' . $p['st'][ $k ]['line'] ) . '">' . esc_html( $N[1] ) . '</span>'; }
	return $o . '</span>';
}

/** A post's card: a link to its panel, so it works without JavaScript too. */
function dbd_share_card( $p, $on, $place = true ) {
	$words = array(); foreach ( dbd_share_nets() as $k => $N ) { $words[] = $N[0] . ' ' . $p['st'][ $k ]['word']; }
	$aria = ( $p['t'] ? dbd_share_et( $p['t'], 'l g:i A' ) . ', ' : '' ) . $p['name'] . '. ' . implode( ', ', $words );
	$img = $p['pic'] !== '' ? '<span class="sp-thumb"><img src="' . esc_url( $p['pic'] ) . '" width="1080" height="1350" alt="" loading="lazy"></span>' : '<span class="sp-thumb empty">After the games</span>';
	$style = $place ? ' style="--c:' . (int) ( $p['day'] + 2 ) . ';--r:' . ( $p['eve'] ? 3 : 2 ) . '"' : '';
	return '<a class="sp-post" href="#sp-' . esc_attr( $p['slot'] ) . '" data-slot="' . esc_attr( $p['slot'] ) . '"' . $style . ' aria-label="' . esc_attr( $aria ) . '"' . ( $on ? ' aria-current="true"' : '' ) . '>' . $img
		. '<span class="sp-pi">' . ( $p['t'] ? '<span class="sp-ptime">' . esc_html( dbd_share_et( $p['t'], 'g:i A' ) ) . '</span>' : '' ) . '<span class="sp-pn">' . esc_html( $p['name'] ) . '</span>' . dbd_share_marks( $p ) . '</span></a>';
}

function dbd_share_calendar( $n, $posts, $sel ) {
	$eve = (bool) array_filter( $posts, function ( $p ) { return $p['eve']; } ); $today = dbd_share_et( time(), 'Y-m-d' ); $tz = new DateTimeZone( DBD_Instagram::TZ );
	$start = new DateTime( '@' . DBD_Instagram::slot_time( $n, 'carousel' ) ); $start->setTimezone( $tz ); $heads = ''; $col = '';
	for ( $i = 0; $i < 7; $i++ ) {
		$d = clone $start; if ( $i ) { $d->modify( '+' . $i . ' days' ); } $is = $d->format( 'Y-m-d' ) === $today;
		if ( $is ) { $col = '<div class="sp-todaycol" style="--c:' . ( $i + 2 ) . '"></div>'; }
		$heads .= '<div class="sp-dayhead' . ( $is ? ' today' : '' ) . '" style="--c:' . ( $i + 2 ) . ';--r:1"><span class="d">' . esc_html( $d->format( 'D' ) ) . '</span><span class="n">' . esc_html( $d->format( 'j' ) ) . '</span></div>';
		foreach ( $posts as $p ) { if ( $p['day'] === $i ) { $heads .= dbd_share_card( $p, $p['slot'] === $sel ); } }
	}
	return '<div class="sp-cal" style="--sp-rows:' . ( $eve ? 2 : 1 ) . '">' . $col . '<div class="sp-rowline" style="--r:2"></div>' . ( $eve ? '<div class="sp-rowline" style="--r:3"></div>' : '' )
		. '<div class="sp-timelab" style="--c:1;--r:2">9:00 AM</div>' . ( $eve ? '<div class="sp-timelab" style="--c:1;--r:3">5:00 PM</div>' : '' ) . $heads . '</div>';
}

/** What the marks mean, listing Not posted only when the week has one. */
function dbd_share_legend( $posts ) {
	$idle = false; foreach ( $posts as $p ) { foreach ( $p['st'] as $s ) { if ( $s['class'] === 'idle' ) { $idle = true; } } }
	$o = ''; foreach ( array_merge( array( 'posted' => 'Posted', 'scheduled' => 'Scheduled', 'skipped' => 'Skipped', 'failed' => 'Failed' ), $idle ? array( 'idle' => 'Not posted' ) : array() ) as $c => $w ) { $o .= '<span><span class="sp-m ' . $c . '" aria-hidden="true">IG</span>' . $w . '</span>'; }
	return '<div class="sp-legend">' . $o . '</div>';
}

/** One post's panel: its graphic, where it goes, and every way to post it. */
function dbd_share_detail( $n, $p, $on, $ready ) {
	$it = $p['it']; $slot = $p['slot']; $car = $slot === 'carousel';
	if ( $it && $p['pic'] !== '' ) {
		$fig = '<a class="sp-card" href="' . esc_url( $it['link'] ) . '" target="_blank" rel="noopener" aria-label="Open the page"><img src="' . esc_url( $p['pic'] ) . '" width="1080" height="1350" alt="" loading="lazy"></a>';
		if ( ! $car ) { $fig .= '<p class="sp-dl"><a href="' . esc_url( $p['pic'] ) . '" download="' . esc_attr( DBD_Tall::file_name( $n, $it ) ) . '">Download graphic</a><a href="' . esc_url( DBD_Card::url( $it['card'] ) ) . '" download="' . esc_attr( preg_replace( '/\.jpg$/', '.png', $it['file'] ) ) . '">Download link card</a></p>'; }
	} else { $fig = '<div class="sp-noimg">The graphic appears after the games.</div>'; }
	$rows = '';
	foreach ( dbd_share_nets() as $k => $N ) {
		$s = $p['st'][ $k ];
		$act = $s['link'] !== '' ? '<a href="' . esc_url( $s['link'] ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( 'View on ' . $N[0] ) . '">View</a>' : ( $s['again'] !== '' ? '<a class="button button-small" href="' . esc_url( $s['again'] ) . '">Try again</a>' : '' );
		$rows .= '<tr><th scope="row">' . esc_html( $N[0] ) . '</th><td><span class="sp-sd ' . esc_attr( $s['class'] ) . '"></span>' . esc_html( $s['line'] ) . '</td><td>' . $act . '</td></tr>';
	}
	$when = $p['t'] ? dbd_share_et( $p['t'], 'l, F j' ) . ' at ' . dbd_share_et( $p['t'], 'g:i A' ) : 'Not on the schedule';
	$text = $ready ? ( $car ? 'The week’s charts together, as a carousel on Instagram and a thread on the others.' : (string) $it['title'] ) : 'Its graphic and text appear once Week ' . (int) $n . '’s games are in, around Tuesday morning.';
	$o = '<section class="sp-d' . ( $on ? ' on' : '' ) . '" id="sp-' . esc_attr( $slot ) . '" data-slot="' . esc_attr( $slot ) . '" aria-label="' . esc_attr( $p['name'] ) . '"><figure>' . $fig . '</figure><div class="sp-dm"><p class="sp-when">' . esc_html( $when ) . '</p><h3>' . esc_html( $p['name'] ) . '</h3><p class="sp-tk">' . esc_html( $text ) . '</p>';
	if ( $car && $p['items'] ) { $o .= '<ol class="sp-charts">'; foreach ( $p['items'] as $x ) { $o .= '<li><a href="#sp-' . esc_attr( $x['k'] ) . '">' . esc_html( $x['h'] ) . '</a></li>'; } $o .= '</ol>'; }
	$o .= '<h4>Where it goes</h4><table class="sp-nets">' . $rows . '</table>';
	if ( $ready && $car ) { $o .= '<details class="sp-tx"><summary>Texts to copy</summary>' . dbd_pack_field( 'Instagram caption', DBD_Instagram::carousel_caption( $n, $p['items'] ), false, 6 ) . '</details>'; }
	if ( $ready && ! $car ) {
		$also = array_values( array_filter( (array) $it['fits'] ) );
		$o .= dbd_pack_auto( $n, $it ) . '<h4>Post by hand</h4>' . dbd_pack_row( $it, DBD_Pack::share_link( $it ) )
			. '<p class="sp-help">Reddit, X, Bluesky and Threads open with the post filled in. Facebook copies the text and opens its share box: choose Share to a group, then paste. Instagram copies the caption: post it with the graphic.</p>'
			. ( $also ? '<p class="sp-fits">Also fits ' . esc_html( implode( ', ', $also ) ) . ': post there by hand with the texts below.</p>' : '' )
			. '<details class="sp-tx"><summary>Texts to copy</summary>' . dbd_pack_texts( $it ) . '</details>';
	}
	return $o . '</div></section>';
}

function dbd_share_schedule( $n, $ready, $up ) {
	$posts = dbd_share_posts( $n, $ready ); $extras = $ready ? dbd_share_extras( $n ) : array(); $sel = '';
	foreach ( $posts as $p ) { foreach ( $p['st'] as $s ) { if ( $sel === '' && $s['class'] === 'scheduled' ) { $sel = $p['slot']; } } }
	if ( $sel === '' ) { $today = dbd_share_et( time(), 'Y-m-d' ); foreach ( $posts as $p ) { if ( dbd_share_et( $p['t'], 'Y-m-d' ) === $today ) { $sel = $p['slot']; break; } } }
	if ( $sel === '' && $posts ) { $sel = $posts[0]['slot']; }
	if ( $sel === '' && $extras ) { $sel = $extras[0]['slot']; }
	echo '<p class="sp-next">' . dbd_share_next( $up ) . '</p>';  /* escaped where it's built */
	foreach ( dbd_share_alerts( $n ) as $a ) {
		echo '<p class="sp-alert"><span class="dbd-dot ' . esc_attr( $a[0] ) . '"></span>' . esc_html( $a[1] ) . ( $a[2] !== '' ? ' <a class="button button-small" href="' . esc_url( $a[2] ) . '">Try again</a>' : '' ) . ( $a[3] ? ' <a href="' . esc_url( dbd_share_url( $n, 'accounts' ) ) . '">Open Accounts and email</a>' : '' ) . '</p>';
	}
	if ( ! $ready ) { echo '<p class="sp-wait">' . esc_html( 'Week ' . $n . '’s posts are planned. Graphics and text fill in when its games finish, around Tuesday morning.' ) . '</p>'; }
	if ( $posts ) { echo dbd_share_calendar( $n, $posts, $sel ) . dbd_share_legend( array_merge( $posts, $extras ) ); }  /* escaped where they're built */
	if ( $extras ) {
		echo '<h3 class="sp-exh">Also ready, not on the schedule</h3><div class="sp-extras">';
		foreach ( $extras as $p ) { echo dbd_share_card( $p, $p['slot'] === $sel, false ); }
		echo '</div>';
	}
	foreach ( array_merge( $posts, $extras ) as $p ) { echo dbd_share_detail( $n, $p, $p['slot'] === $sel, $ready ); }
}

/* ------------------------------------------------------------------ the playoff posts (SCOPE §89), folded away under the calendar */

/** The eleven playoff posts in time order, each shaped like a calendar post, with 'n' (its playoff week) and 'wait' (what it waits for). */
function dbd_share_po_posts() {
	$out = array(); $on = DBD_Playoffs::on();
	foreach ( DBD_PoPosts::PLAN as $n => $slots ) {
		foreach ( $slots as $sl ) {
			$it = DBD_PoPosts::item( $n, $sl ); $t = DBD_PoPosts::time( $n, $sl ); $key = DBD_AutoPub::key( $n, $sl ); $st = array();
			foreach ( dbd_share_nets() as $net => $N ) {
				$c = $N[2];
				$st[ $net ] = ! $on && ! $c::job( $key ) ? array( 'class' => 'idle', 'word' => 'Off', 'line' => 'The Playoffs switch is off', 'link' => '', 'again' => '', 'at' => 0 )
					: ( ! $t ? array( 'class' => 'scheduled', 'word' => 'Planned', 'line' => 'Planned', 'link' => '', 'again' => '', 'at' => 0 ) : dbd_share_state( $key, $net, $t, $it ? 'week' : 'planned' ) );
			}
			$out[] = array( 'n' => $n, 'slot' => 'po' . $n . '-' . $sl, 'name' => $it ? (string) $it['h'] : DBD_PoPosts::name( $n, $sl ), 't' => $t, 'day' => -1, 'eve' => false,
				'items' => $it ? array( $it ) : array(), 'it' => $it, 'pic' => $it ? DBD_Tall::preview( $n, $it ) : '', 'st' => $st, 'wait' => $it ? '' : DBD_PoPosts::waiting( $n, $sl ) );
		}
	}
	usort( $out, function ( $a, $b ) { return $a['t'] <=> $b['t']; } );
	return $out;
}

/** A playoff post's card: its date and time, since the list spans five weeks. */
function dbd_share_po_card( $p ) {
	$words = array(); foreach ( dbd_share_nets() as $k => $N ) { $words[] = $N[0] . ' ' . $p['st'][ $k ]['word']; }
	$when = $p['t'] ? dbd_share_et( $p['t'], 'D, M j' ) . ', ' . dbd_share_et( $p['t'], 'g:i A' ) : 'Date to come';
	$img = $p['pic'] !== '' ? '<span class="sp-thumb"><img src="' . esc_url( $p['pic'] ) . '" width="1080" height="1350" alt="" loading="lazy"></span>' : '<span class="sp-thumb empty">Not ready yet</span>';
	return '<a class="sp-post" href="#sp-' . esc_attr( $p['slot'] ) . '" data-slot="' . esc_attr( $p['slot'] ) . '" aria-label="' . esc_attr( $when . ', ' . $p['name'] . '. ' . implode( ', ', $words ) ) . '">' . $img
		. '<span class="sp-pi"><span class="sp-ptime">' . esc_html( $when ) . '</span><span class="sp-pn">' . esc_html( $p['name'] ) . '</span>' . dbd_share_marks( $p ) . '</span></a>';
}

/** A playoff post that isn't ready: when it goes, what it waits for, and where it goes. */
function dbd_share_po_wait( $p ) {
	$rows = ''; foreach ( dbd_share_nets() as $k => $N ) { $s = $p['st'][ $k ]; $rows .= '<tr><th scope="row">' . esc_html( $N[0] ) . '</th><td><span class="sp-sd ' . esc_attr( $s['class'] ) . '"></span>' . esc_html( $s['line'] ) . '</td><td></td></tr>'; }
	$when = $p['t'] ? dbd_share_et( $p['t'], 'l, F j' ) . ' at ' . dbd_share_et( $p['t'], 'g:i A' ) : 'Date to come';
	return '<section class="sp-d" id="sp-' . esc_attr( $p['slot'] ) . '" data-slot="' . esc_attr( $p['slot'] ) . '" aria-label="' . esc_attr( $p['name'] ) . '"><figure><div class="sp-noimg">The graphic appears when the post is ready.</div></figure>'
		. '<div class="sp-dm"><p class="sp-when">' . esc_html( $when ) . '</p><h3>' . esc_html( $p['name'] ) . '</h3><p class="sp-tk">' . esc_html( $p['wait'] ) . '</p><h4>Where it goes</h4><table class="sp-nets">' . $rows . '</table></div></section>';
}

/** The section's one line: off, or what's next, or how many are out. */
function dbd_share_po_line( $posts ) {
	$ts = array_values( array_filter( array_column( $posts, 't' ) ) ); $range = $ts ? dbd_share_et( min( $ts ), 'F j' ) . ' to ' . dbd_share_et( max( $ts ), 'F j' ) : '';
	if ( ! DBD_Playoffs::on() ) { return 'Eleven posts' . ( $range !== '' ? ', ' . $range : '' ) . '. The Playoffs switch is off, so none will post.'; }
	$next = null; $out = 0;
	foreach ( $posts as $p ) {
		$all = true; foreach ( $p['st'] as $s ) { if ( $s['class'] !== 'posted' ) { $all = false; } if ( $s['class'] === 'scheduled' && $s['at'] && ( ! $next || $s['at'] < $next[0] ) ) { $next = array( $s['at'], $p['name'] ); } }
		if ( $all ) { $out++; }
	}
	if ( $next ) { return 'Next: ' . $next[1] . ', ' . dbd_share_et( $next[0], 'l, F j' ) . ' at ' . dbd_share_et( $next[0], 'g:i A' ) . '.' . ( $out ? ' ' . $out . ' of ' . count( $posts ) . ' posted.' : '' ); }
	return $out ? $out . ' of ' . count( $posts ) . ' posted.' : 'Eleven posts' . ( $range !== '' ? ', ' . $range : '' ) . '. Each one books when its games are in.';
}

/** The folded section: closed until the regular season ends (or a playoff post's button brings you back), so it's out of the way until then. */
function dbd_share_playoffs() {
	if ( DBD_Data::archived() ) { return; }
	$posts = dbd_share_po_posts(); $on = DBD_Playoffs::on(); $lk = DBD_Tall::look( DBD_PoPosts::WEEKS[0] );
	$open = ! empty( $_GET['pop'] ) || DBD_PoPosts::item( 19, 'pset' ) !== null;
	echo '<details class="sp-po" id="sp-po"' . ( $open ? ' open' : '' ) . '><summary><span class="sp-poh">Playoff posts</span><span class="sp-pos">' . esc_html( dbd_share_po_line( $posts ) ) . '</span><span class="sp-gt"><span class="o">Open</span><span class="x">Close</span></span></summary><div class="sp-pob">';
	echo '<p class="sp-intro">A preview before each round, the bracket after it, and three posts before the Super Bowl, at noon Eastern on the networks that post by themselves. Each one is booked when its games are in, and only while the Playoffs switch is on.'
		. ( $on ? '' : ' <a href="' . esc_url( dbd_admin_url( 'po' ) ) . '">Open the Playoffs switch</a>' ) . '</p>';
	echo '<form class="sp-look sp-polook" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="dbd_pack_look"><input type="hidden" name="week" value="' . (int) DBD_PoPosts::WEEKS[0] . '"><input type="hidden" name="po" value="1">'
		. wp_nonce_field( 'dbd_pack_look_' . DBD_PoPosts::WEEKS[0], '_wpnonce', true, false )
		. '<span>Playoff graphics</span><button class="button" name="look" value="black" aria-pressed="' . ( $lk === 'black' ? 'true' : 'false' ) . '">Black</button><button class="button" name="look" value="paper" aria-pressed="' . ( $lk === 'paper' ? 'true' : 'false' ) . '">Paper</button></form>';
	echo '<div class="sp-extras sp-polist">'; foreach ( $posts as $p ) { echo dbd_share_po_card( $p ); } echo '</div>';  /* escaped where they're built */
	foreach ( $posts as $p ) { echo $p['it'] ? dbd_share_detail( $p['n'], $p, false, true ) : dbd_share_po_wait( $p ); }
	echo '</div></details>';
}

/** The week's arrows, its dates, a jump to any week, and its Black or Paper look. */
function dbd_share_weekbar( $n, $all, $weeks, $view ) {
	$asc = $all; sort( $asc ); $i = array_search( $n, $asc, true ); $prev = $i > 0 ? $asc[ $i - 1 ] : 0; $next = $i < count( $asc ) - 1 ? $asc[ $i + 1 ] : 0;
	$arrow = function ( $w, $ch, $lab ) use ( $view ) { return $w ? '<a class="button" href="' . esc_url( dbd_share_url( $w, $view ) ) . '" aria-label="' . esc_attr( $lab ) . '">' . $ch . '</a>' : '<span class="button disabled" aria-hidden="true">' . $ch . '</span>'; };
	$a = DBD_Instagram::slot_time( $n, 'carousel' ); $b = DBD_Instagram::slot_time( $n, 'board' ); $lk = DBD_Tall::look( $n );
	?>
	<div class="sp-weekbar">
		<div class="sp-step"><?php echo $arrow( $prev, '‹', 'Previous week' ); ?><h2><?php echo esc_html( 'Week ' . $n ); ?></h2><?php echo $arrow( $next, '›', 'Next week' ); ?></div>
		<span class="sp-range"><?php echo esc_html( dbd_share_et( $a, 'M j' ) . ' to ' . dbd_share_et( $b, 'M j' ) ); ?></span>
		<form class="sp-jump" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>"><input type="hidden" name="page" value="down-by-down-share"><?php if ( $view !== 'schedule' ) { echo '<input type="hidden" name="view" value="' . esc_attr( $view ) . '">'; } ?>
			<select name="week" aria-label="Go to a week" onchange="this.form.submit()"><?php foreach ( $all as $w ) { echo '<option value="' . (int) $w . '"' . ( $w === $n ? ' selected' : '' ) . '>Week ' . (int) $w . ( in_array( $w, $weeks, true ) ? '' : ' (coming up)' ) . '</option>'; } ?></select>
			<noscript><button class="button">Show</button></noscript></form>
		<form class="sp-look" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dbd_pack_look"><input type="hidden" name="week" value="<?php echo (int) $n; ?>"><input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>"><?php wp_nonce_field( 'dbd_pack_look_' . $n ); ?>
			<span>Graphics</span><button class="button" name="look" value="black" aria-pressed="<?php echo $lk === 'black' ? 'true' : 'false'; ?>">Black</button><button class="button" name="look" value="paper" aria-pressed="<?php echo $lk === 'paper' ? 'true' : 'false'; ?>">Paper</button></form>
	</div>
	<?php
}

/* ------------------------------------------------------------------ the Keys to the game view */

function dbd_share_keys( $n, $ready ) {
	$keys = $ready ? DBD_Pack::keys( $n ) : array();
	if ( ! $keys ) { echo '<p class="sp-wait">' . esc_html( $ready ? 'No keys to the game for Week ' . ( $n + 1 ) . '.' : 'The keys for Week ' . ( $n + 1 ) . ' appear after Week ' . $n . '’s games.' ) . '</p>'; return; }
	$nk = function ( $t ) { return DBD_Data::team( $t )['nick'] ?? $t; };
	echo '<h3 class="sp-kh">' . esc_html( 'Keys to the game, Week ' . ( $n + 1 ) ) . '</h3><p class="sp-intro">Each team’s biggest edge in its next game, for its own fans. These aren’t on the schedule, so post the ones you want before kickoff. Reddit, X, Bluesky and Threads open with the post filled in.</p><div class="sp-games">';
	$first = 0; foreach ( $keys as $i => $kg ) { if ( ! DBD_Pack::started( $kg['g'] ) ) { $first = $i; break; } }  /* the next game still worth posting */
	foreach ( $keys as $i => $kg ) {
		$g = $kg['g']; $img = DBD_Card::url( $kg['card'] ); $off = DBD_Pack::started( $g );
		echo '<details class="sp-game"' . ( $i === $first ? ' open' : '' ) . '><summary><span class="sp-gn">' . esc_html( $nk( $g['away'] ) . ' at ' . $nk( $g['home'] ) ) . '</span>' . ( $off ? '<span class="sp-goff">Kicked off</span>' : '' ) . '<span class="sp-gt"><span class="o">Open</span><span class="x">Close</span></span></summary>'
			. '<p class="sp-gl"><a href="' . esc_url( $kg['link'] ) . '" target="_blank" rel="noopener">Open the game</a><a href="' . esc_url( $img ) . '" download="' . esc_attr( $kg['file'] ) . '">Download the game card</a></p><div class="sp-sides">';
		foreach ( $kg['sides'] as $sd ) {
			echo '<div class="sp-ks" id="' . esc_attr( 'sp-' . DBD_AutoPub::slot( $n, $sd ) ) . '"><div class="sp-kt">' . DBD_Marks::tile_svg( $sd['t'], 24 ) . '<div><b>' . esc_html( $nk( $sd['t'] ) . ' offense.' ) . '</b> ' . esc_html( rtrim( $sd['head'], '.' ) ) . '. <span>' . esc_html( $sd['say'] ) . '</span></div></div>'
				. dbd_pack_auto( $n, $sd + array( 'card' => $kg['card'] ), true ) . dbd_pack_row( $sd, $sd['link'], $sd['fits'], ! $off, $n )  /* ours, then the team's own subreddit (2.29.1) */
				. '<details class="sp-tx"><summary>Texts to copy</summary>' . dbd_pack_texts( $sd ) . '</details></div>';
		}
		echo '</div></details>';
	}
	echo '</div>';
}

/* ------------------------------------------------------------------ the Accounts and email view */

/** Every setting, in one form with one Save (SCOPE §74): each field keeps its name. $n is the week the page came back to. */
function dbd_share_accounts( $n, $weeks ) {
	$ps = DBD_Pack_Mail::settings(); $key = (string) DBD_Email::get( 'key' ) !== ''; $aps = DBD_AutoPub::settings(); $saveerr = (string) ( $_GET['pack'] ?? '' ) === 'saveerr'; $pn = in_array( $n, $weeks, true ) ? $n : ( $weeks[0] ?? 0 );
	$acc = function ( $id, $name, $value, $pre = '', $note = '' ) {
		return '<label for="' . esc_attr( $id ) . '">Account</label><div><span class="sp-pre">' . ( $pre !== '' ? '<span>' . esc_html( $pre ) . '</span>' : '' ) . '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" maxlength="300"></span>' . ( $note !== '' ? '<p class="description">' . esc_html( $note ) . '</p>' : '' ) . '</div>';
	};
	$pw = function ( $id, $name, $label, $ph, $after = '' ) { return '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><div><input type="password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="" autocomplete="off" placeholder="' . esc_attr( $ph ) . '">' . $after . '</div>'; };
	$off = function ( $name, $what ) { return '<label class="description sp-disc"><input type="checkbox" name="' . esc_attr( $name ) . '" value="1"> Disconnect (forget ' . esc_html( $what ) . ')</label>'; };

	/* Instagram */
	$it = DBD_Instagram::token(); $ic = array( '', 'Not connected' ); $note = '';
	if ( $it ) {
		$who = '@' . esc_html( $it['username'] !== '' ? $it['username'] : $it['user_id'] );
		if ( DBD_Instagram::lasting( $it ) ) {  /* a Unified-login token never runs out; it's checked every week (2.34.1) */
			$ic = empty( $it['err'] ) ? array( 'ok', 'Connected' ) : array( 'bad', 'Needs a new token' );
			$note = '<p class="description">' . ( empty( $it['err'] ) ? '<span class="dbd-dot ok"></span>Connected as ' . $who . '. This token doesn’t run out; it’s checked every week.' : '<span class="dbd-dot bad"></span>Connected as ' . $who . ', but Instagram stopped taking the token: ' . esc_html( $it['err'] ) . ' Paste a new one.' ) . '</p>';
		} else {
			$ic = DBD_Instagram::connected() ? ( empty( $it['err'] ) ? array( 'ok', 'Connected' ) : array( 'warn', 'Renewal failed' ) ) : array( 'bad', 'Token ran out' );
			$note = '<p class="description">' . ( DBD_Instagram::connected() ? '<span class="dbd-dot ok"></span>Connected as ' . $who . '. Good until ' . esc_html( wp_date( 'M j, Y', (int) $it['expires'] ) ) . '; renewed every week.' : '<span class="dbd-dot bad"></span>The token ran out on ' . esc_html( wp_date( 'M j', (int) $it['expires'] ) ) . '. Paste a new one.' )
				. ( ! empty( $it['err'] ) ? ' <br><span class="dbd-dot warn"></span>The last renewal failed: ' . esc_html( $it['err'] ) : '' ) . '</p>';
		}
		$note .= $off( 'dbd_ig_disconnect', 'the token' );
	} else {
		$note = '<p class="description">In your Meta app (Instagram, API setup with Unified login), make a configuration with @' . esc_html( DBD_Pack::our_ig() !== '' ? DBD_Pack::our_ig() : 'your account' ) . ', then Generate token and paste it here. It’s checked when you save, and never shown again.</p>';
	}
	$ig_f = $acc( 'dbd-pack-ig', DBD_Pack::OPT_IG, DBD_Pack::our_ig(), '@' )
		. '<label for="dbd-ig-acc">Instagram account ID</label><div><input type="text" id="dbd-ig-acc" name="' . esc_attr( DBD_Instagram::OPT_SET ) . '[account]" value="' . esc_attr( DBD_Instagram::settings()['account'] ) . '" inputmode="numeric" autocomplete="off"><p class="description">The number under the account in your Meta app. Needed for a token that starts EAA.</p></div>'
		. $pw( 'dbd-ig-token', 'dbd_ig_token_new', 'Access token', $it ? 'Paste a new token to replace it' : 'Paste the token from your Meta app', $note );
	if ( $pn && DBD_Instagram::on() && DBD_Instagram::next_key() !== '' ) { $ig_f .= '<span>Post the next one</span><div><button class="button" form="sp-ig-now">Post the next one now</button><p class="description">Posts Instagram’s first post not out yet, right away.</p></div>'; }

	/* Bluesky */
	$bc = DBD_Bluesky::conn();
	$bs_f = $acc( 'dbd-pack-bsky', DBD_Pack::OPT_BSKY, DBD_Pack::our_bsky() )
		. $pw( 'dbd-bsky-pass', 'dbd_bsky_pass_new', 'App password', $bc ? 'Paste a new app password to replace it' : 'Paste an app password from Bluesky',
			$bc ? '<p class="description"><span class="dbd-dot ok"></span>Connected as @' . esc_html( $bc['handle'] ) . '. Delete the app password in Bluesky to cut the plugin off.</p>' . $off( 'dbd_bsky_disconnect', 'the app password' )
				: '<p class="description">Make one in Bluesky: Settings → Privacy and security → App passwords → Add. It’s checked when you save, and never shown again.</p>' );

	/* X */
	$xc = DBD_X::conn();
	$x_f = $acc( 'dbd-pack-x', DBD_Pack::OPT_X, DBD_Pack::our_x(), '@', 'Also named in every page’s share tags.' )
		. '<span>Graphics</span><div><label><input type="checkbox" name="' . esc_attr( DBD_X::OPT_SET ) . '[img]" value="1"' . ( DBD_X::settings()['img'] ? ' checked' : '' ) . '> Attach the graphic to each X post</label><p class="description">Off, X posts stay text and link. X’s price list doesn’t show what an image upload costs, so check the first one on the usage page in the X console.</p></div>';
	foreach ( DBD_X::KEYS as $f => $lab ) {
		$after = '';
		if ( $f === 'as' ) {
			$after = $xc ? '<p class="description"><span class="dbd-dot ok"></span>Connected as @' . esc_html( $xc['username'] ) . '. Revoke the keys in the X Developer Console to cut the plugin off.</p>' . $off( 'dbd_x_disconnect', 'the keys' )
				: '<p class="description">In the X Developer Console: your app, then User authentication settings, Read and write; then Keys and tokens, and generate the access token and secret while signed in as @' . esc_html( DBD_Pack::our_x() !== '' ? DBD_Pack::our_x() : 'your account' ) . '. Paste all four together. They’re checked when you save, and never shown again.</p>';
		}
		$x_f .= $pw( 'dbd-x-' . $f, 'dbd_x_' . $f . '_new', $lab, $xc ? 'Paste a new one to replace it' : 'Paste from the X Developer Console', $after );
	}
	if ( $xc ) { $xm = DBD_X::month(); $x_f .= '<span>This month</span><div class="sp-plain">' . esc_html( $xm . ( $xm === 1 ? ' post' : ' posts' ) . ', about $' . number_format( $xm * DBD_X::PRICE, 2 ) . ' at X’s price.' ) . ' <span class="description">Your credit balance is in the X Developer Console.</span></div>'; }

	/* Threads */
	$tc = DBD_Threads::conn(); $tcs = array( '', 'Not connected' );
	if ( $tc ) {
		$tcs = DBD_Threads::connected() ? ( empty( $tc['err'] ) ? array( 'ok', 'Connected' ) : array( 'warn', 'Renewal failed' ) ) : array( 'bad', 'Token ran out' );
		$tn = '<p class="description">' . ( DBD_Threads::connected() ? '<span class="dbd-dot ' . ( empty( $tc['err'] ) ? 'ok' : 'warn' ) . '"></span>Connected as @' . esc_html( $tc['username'] ) . '. Good until ' . esc_html( wp_date( 'M j, Y', (int) $tc['expires'] ) ) . '; renewed every week.' . ( ! empty( $tc['err'] ) ? ' The last renewal failed: ' . esc_html( $tc['err'] ) : '' ) : '<span class="dbd-dot bad"></span>The token ran out on ' . esc_html( wp_date( 'M j', (int) $tc['expires'] ) ) . '. Paste a new one.' ) . '</p>' . $off( 'dbd_th_disconnect', 'the token' );
	} else {
		$tn = '<p class="description">In the Meta app, Use cases, then Access the Threads API, Customize, Settings: copy the Threads app secret, and under User Token Generator, Generate an access token for @' . esc_html( DBD_Pack::our_th() !== '' ? DBD_Pack::our_th() : 'your account' ) . ' (a Threads Tester with threads_basic, threads_content_publish and threads_manage_replies). Paste both here. The token lasts 60 days and is renewed every week. Never shown again.</p>';
	}
	$th_f = $acc( 'dbd-pack-th', DBD_Pack::OPT_TH, DBD_Pack::our_th(), '@', 'The Threads profile’s name; the Instagram account’s by default.' )
		. $pw( 'dbd-th-secret', 'dbd_th_secret_new', 'Threads App secret', $tc ? 'Kept; paste a new one to replace it' : 'From the Meta app’s Threads settings' )
		. $pw( 'dbd-th-token', 'dbd_th_token_new', 'Access token', $tc ? 'Paste a new token to replace it' : 'From the Meta app’s User Token Generator', $tn );

	$rows = array(
		'ig' => array( DBD_Pack::our_ig() !== '' ? '@' . DBD_Pack::our_ig() : '', $ic, DBD_Instagram::OPT_SET, DBD_Instagram::settings()['on'], $ig_f ),
		'bs' => array( DBD_Pack::our_bsky() !== '' ? '@' . DBD_Pack::our_bsky() : '', $bc ? array( 'ok', 'Connected' ) : array( '', 'Not connected' ), DBD_Bluesky::OPT_SET, DBD_Bluesky::settings()['on'], $bs_f ),
		'x'  => array( DBD_Pack::our_x() !== '' ? '@' . DBD_Pack::our_x() : '', $xc ? array( 'ok', 'Connected' ) : array( '', 'Not connected' ), DBD_X::OPT_SET, DBD_X::settings()['on'], $x_f ),
		'th' => array( DBD_Pack::our_th() !== '' ? '@' . DBD_Pack::our_th() : '', $tcs, DBD_Threads::OPT_SET, DBD_Threads::settings()['on'], $th_f ),
	);
	?>
	<form id="dbd-pack-settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dbd_pack_settings"><input type="hidden" name="week" value="<?php echo (int) $n; ?>"><?php wp_nonce_field( 'dbd_pack_settings' ); ?>
	<p class="sp-intro">Weekly schedule posts at the calendar’s times, with Tuesday’s post as a carousel on Instagram and a thread on the others. Auto publish is the Post now button on each item. Reddit and Facebook can’t post on their own.</p>
	<section class="sp-panel"><h3>Posting accounts</h3>
		<table class="sp-acct"><thead><tr><th scope="col">Network</th><th scope="col">Account</th><th scope="col">Connection</th><th scope="col">Weekly schedule</th><th scope="col">Auto publish</th><th scope="col"><span class="screen-reader-text">Change</span></th></tr></thead><tbody>
		<?php foreach ( $rows as $k => $r ) : $N = dbd_share_nets()[ $k ]; $open = $saveerr || $r[1][0] !== 'ok'; ?>
			<tr class="sp-arow"><th scope="row"><?php echo esc_html( $N[0] ); ?></th><td class="sp-handle"><?php echo esc_html( $r[0] !== '' ? $r[0] : 'Not set' ); ?></td><td><span class="dbd-dot <?php echo esc_attr( $r[1][0] ); ?>"></span><?php echo esc_html( $r[1][1] ); ?></td>
				<td><label><input type="checkbox" name="<?php echo esc_attr( $r[2] ); ?>[on]" value="1"<?php echo $r[3] ? ' checked' : ''; ?> aria-label="<?php echo esc_attr( $N[0] . ' weekly schedule' ); ?>"> <span class="sp-lab">Weekly schedule</span><span class="sp-onl">On</span></label></td>
				<td><label><input type="checkbox" name="<?php echo esc_attr( DBD_AutoPub::OPT ); ?>[<?php echo esc_attr( $k ); ?>]" value="1"<?php echo $aps[ $k ] ? ' checked' : ''; ?> aria-label="<?php echo esc_attr( $N[0] . ' Auto publish' ); ?>"> <span class="sp-lab">Auto publish</span><span class="sp-onl">On</span></label></td>
				<td class="sp-chgc"><button type="button" class="button sp-chg" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="sp-edit-<?php echo esc_attr( $k ); ?>" aria-label="<?php echo esc_attr( 'Change ' . $N[0] ); ?>"><?php echo $open ? 'Close' : 'Change'; ?></button></td></tr>
			<tr class="sp-edit<?php echo $open ? ' open' : ''; ?>" id="sp-edit-<?php echo esc_attr( $k ); ?>"><td colspan="6"><div class="sp-fg"><?php echo $r[4]; /* escaped where it's built */ ?></div></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</section>
	<section class="sp-panel"><h3>Posted by hand</h3><div class="sp-pb"><div class="sp-fg">
		<label for="dbd-pack-sub">Reddit</label><div><span class="sp-pre"><span>r/</span><input type="text" id="dbd-pack-sub" name="<?php echo esc_attr( DBD_Pack::OPT_SUB ); ?>" value="<?php echo esc_attr( DBD_Pack::our_sub() ); ?>" maxlength="300"></span></div>
		<label for="dbd-pack-fb">Facebook group</label><div><input type="text" id="dbd-pack-fb" name="<?php echo esc_attr( DBD_Pack::OPT_FB ); ?>" value="<?php echo esc_attr( DBD_Pack::our_fb() ); ?>" maxlength="300"><?php if ( DBD_Pack::our_fb() !== '' ) { echo '<p class="description"><a href="' . esc_url( DBD_Pack::our_fb() ) . '" target="_blank" rel="noopener">Open the group</a></p>'; } ?></div>
		<span></span><p class="description">Clear any account to hide its button.</p>
	</div></div></section>
	<section class="sp-panel"><h3>The Tuesday email</h3><div class="sp-pb"><div class="sp-fg">
		<span>Send it</span><div><label><input type="checkbox" name="<?php echo esc_attr( DBD_Pack_Mail::OPT_SET ); ?>[on]" value="1"<?php echo $ps['on'] ? ' checked' : ''; ?>> Email each week’s pack, Tuesday 7:00 AM Eastern</label>
			<?php if ( ! $key ) { echo '<p class="description"><span class="dbd-dot bad"></span>Brevo isn’t set up. <a href="' . esc_url( dbd_admin_url( 'wk' ) ) . '">Add the API key on Week in review</a>.</p>'; } ?></div>
		<label for="dbd-pack-to">Send to</label><div><textarea id="dbd-pack-to" name="<?php echo esc_attr( DBD_Pack_Mail::OPT_SET ); ?>[to]" rows="3"><?php echo esc_textarea( implode( "\n", $ps['to'] ) ); ?></textarea>
			<p class="description">One address per line, up to <?php echo (int) DBD_Pack_Mail::MAX_TO; ?>. Each person gets their own copy.</p></div>
		<?php if ( $key ) : $st = DBD_Pack_Mail::status(); ?>
		<span>Last sends</span><div class="sp-plain"><?php
			if ( ! $st ) { echo 'None yet.'; }
			foreach ( $st as $i => $l ) { echo ( $i ? '<br>' : '' ) . '<span class="dbd-dot ' . esc_attr( $l[0] ) . '"></span>' . esc_html( $l[1] ) . ( $l[2] !== '' ? ' <a class="button button-small" href="' . esc_url( DBD_Pack_Mail::again_url( $l[2] ) ) . '">Try again</a>' : '' ); } ?></div>
		<?php if ( $ps['to'] && $pn ) : ?>
		<span></span><div><button class="button" form="sp-mail-now"><?php echo esc_html( 'Send the Week ' . $pn . ' pack now' ); ?></button><p class="description"><?php echo esc_html( 'To ' . count( $ps['to'] ) . ( count( $ps['to'] ) === 1 ? ' person' : ' people' ) . ', right away. Save any change to the list first.' ); ?></p></div>
		<?php endif; endif; ?>
	</div></div></section>
	<div class="sp-save"><button class="button button-primary">Save settings</button></div>
	</form>
	<?php if ( $pn && DBD_Instagram::on() && DBD_Instagram::next_key() !== '' ) : ?><form id="sp-ig-now" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dbd_ig_now"><?php wp_nonce_field( 'dbd_ig_now' ); ?></form><?php endif; ?>
	<?php if ( $key && $ps['to'] && $pn ) : ?><form id="sp-mail-now" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="dbd_pack_now"><input type="hidden" name="week" value="<?php echo (int) $pn; ?>"><?php wp_nonce_field( 'dbd_pack_now' ); ?></form><?php endif; ?>
	<?php
}

/* ------------------------------------------------------------------ the tab */

function dbd_tab_share() {
	$weeks = array_map( 'intval', (array) DBD_Pack::weeks() ); $view = dbd_share_view(); $up = dbd_share_upcoming( $weeks ); $all = $up ? array_merge( array( $up ), $weeks ) : $weeks;
	$want = (int) ( $_GET['week'] ?? 0 ); $n = in_array( $want, $all, true ) ? $want : (int) ( $weeks[0] ?? 0 ); $ready = in_array( $n, $weeks, true );
	echo '<div class="sp" id="sp-page"><script>document.getElementById("sp-page").classList.add("js")</script>';
	dbd_pack_notices();
	echo '<nav class="sp-views" aria-label="Share pack views">';
	foreach ( array( 'schedule' => 'Schedule', 'keys' => 'Keys to the game', 'accounts' => 'Accounts and email' ) as $v => $lab ) { echo '<a href="' . esc_url( dbd_share_url( $n, $v ) ) . '"' . ( $v === $view ? ' aria-current="page"' : '' ) . '>' . esc_html( $lab ) . '</a>'; }
	echo '</nav>';
	if ( $view === 'accounts' ) { dbd_share_accounts( $n, $weeks ); }
	elseif ( ! $n ) { echo '<p class="sp-wait">The pack starts after Week 1’s games are all in.</p>'; }
	else {
		dbd_share_weekbar( $n, $all, $weeks, $view );
		if ( $view === 'keys' ) { dbd_share_keys( $n, $ready ); } else { dbd_share_schedule( $n, $ready, $up ); dbd_share_playoffs(); }
	}
	echo '</div>';
}
