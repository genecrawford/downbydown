Made-up YouTube feeds and an NFL playlists page for the videos harness (SCOPE §51): `php tools/view_harness.php data videos tools/fixtures/videos`
with DBD_HARNESS_NOW=1793728800. Titles are invented ("Test: …") apart from the recap format, so no real video titles sit in the public repo.
A feed key's file is yt-{TEAM or NFL}.xml; a missing file reads as an empty feed, a key in fail.txt as a failed fetch, prev.json is the last pass's stored lists.
playlists.html stands in for the NFL's playlists page; pl-{playlist id}.xml is a playlist's feed.
