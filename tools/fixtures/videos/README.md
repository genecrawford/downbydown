Made-up YouTube feeds and an NFL playlists page for the videos harness (SCOPE §51): `php tools/view_harness.php data videos tools/fixtures/videos`
with DBD_HARNESS_NOW=1793728800. Titles are invented ("Test: …") apart from the recap format, so no real video titles sit in the public repo.
A feed key's file is yt-{TEAM or NFL}.xml; a missing file reads as an empty feed, a key in fail.txt as a failed fetch, prev.json is the last pass's stored lists.
playlists.html stands in for the NFL's playlists page; pl-{playlist id}.xml is a playlist's feed.

2.14.0 (SCOPE §54): pl-PLprev07xxxx.xml is the Week 7 preview playlist, pl-PLplayers07x.xml the Week 7 player highlights (a player, a team defense, an unknown name, a wrong week). Week 6's player playlist ("Highlight", singular, as the NFL titled Week 8's) has no file, so it reads as failed.
