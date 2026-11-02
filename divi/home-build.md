# The marketing home in Divi (plugin 2.1.0, SCOPE §45)

The mock is `mockup-v20/home.html`. The design is yours; these are the pieces the plugin gives you and the order to put them in.

## Before you start (in this order)

1. **Upload 2.1.0.** While `[down_by_down]` is still on the front page, nothing changes.
2. **Make the hub's new page.** Create a page "This week" at `/this-week/`.
   - Put `[down_by_down]` on it.
   - Give it the same layout and sidebar the Home page has now.
3. **Take the shortcode off the Home page.** Home becomes the marketing page; `/this-week/` is now the app.
4. **Check the setting.** Open **Settings › Down by Down**.
   - **Paths at the site root** should be on (it is by default).
   - Click the test link: `/team/min/` should open the Vikings.
5. **Point the sidebar's "Slate" link at `/this-week/`.**

## Section classes

In each Divi section's Advanced › CSS Class:

- **`dbd-turf`**: the field. It gives the section a green background with yard lines and hash marks, white type, and a white bottom stripe.
  - Wrap "drawn on the field." in the H1 as `<span class="dbd-u">drawn on the field.</span>` for the red underline.
- **`dbd-endzone`**: the close. It gives a red background with painted stripes, and the hollow DOWN × DOWN wordmark comes with the class.
  - Put `<span class="dbd-td">Touchdown</span>` in a Text module above the closing line.

## The blocks

Put each one in a Code or Text module. Every block draws this week's data and changes on its own each week.

| Section | Shortcodes |
|---|---|
| Hero (`dbd-turf`), two columns | left: H1, lede, two buttons · right: `[dbd_block type="hero-map"]` |
| Hero, full-width row under it | `[dbd_block type="yards"]` |
| Hero, last row | `[dbd_block type="games"]` then `[dbd_block type="counts"]` |
| What happened this week | `[dbd_block type="down" down="1" dist="10" spot="Own 25"]`, heading, `[dbd_block type="week-highlights"]` |
| Every matchup, read for you | `[dbd_block type="down" down="2" dist="2" spot="Own 33"]`, heading, copy, button · `[dbd_block type="matchup"]` |
| Every player, in context | `[dbd_block type="down" down="1" dist="10" spot="Own 41"]`, heading, copy · `[dbd_block type="player-card"]` and `[dbd_block type="movers"]` |
| Fantasy with the why | `[dbd_block type="down" down="2" dist="1" spot="Midfield"]`, heading, copy · `[dbd_block type="fantasy"]` and `[dbd_block type="leaders" boards="ppr"]` |
| Who is actually good | `[dbd_block type="down" down="1" dist="10" spot="Opp 38"]`, heading · `[dbd_block type="leaders"]` |
| Start with your team | `[dbd_block type="down" down="2" dist="3" spot="Opp 31"]`, heading · `[dbd_block type="teams"]` |
| The newsletter | `[dbd_block type="down" down="1" dist="10" spot="Opp 17"]`, heading, copy, Divi **Email Optin** · `[dbd_block type="newsletter-preview"]` |
| Questions | `[dbd_block type="down" down="1" dist="Goal" spot="Opp 6"]`, heading · `[dbd_block type="faq"]` |
| Close (`dbd-endzone`) | `<span class="dbd-td">Touchdown</span>`, heading, two buttons |

**Options**
- `hero-map` takes `player="00-0036322"` to pin a player; without it, it shows the week's most-targeted player.
- `player-card` takes `player="…"`; without it, it shows the week's biggest target-share riser.
- `leaders` takes `boards="wr.tshare,wr.epat,qb.epadb"` (the default) or `ppr`, and `top="5"`.

## Links for buttons

`[dbd_url to="…"]` prints an app link for a Text or Code module. The `to` values are:

- `hub` (`/this-week/`)
- `teams`
- `players`
- `myteam`
- `matchups`
- `leaders`
- `week` (the latest week in review)
- `season` (the latest finished season, `/2025/`)

For Divi's own button URL fields, type the path: `/this-week/`, `/teams/`, `/my-team/`, `/matchups/`, `/week/`.

## The newsletter

- **Sign-ups:** Divi's Email Optin module connects to Mailchimp, Brevo, Kit and others. The plugin stores no addresses.
- **The weekly email:** point your provider's RSS-to-email campaign at `https://downbydown.com/feed/week-in-review/`. It gets one item per finished week, dated the morning after the week's last game, so a Tuesday send picks it up. Mailchimp's RSS campaigns and Kit's RSS automation both do this; check Brevo's RSS support before relying on it.

## Search

The plugin writes the home page's head tags, unless an SEO plugin is active:
- the title "Down by Down: every NFL snap, drawn on the field | Free NFL charts, matchups and fantasy"
- the description
- the share image (the hub's card)
- WebSite and Organization data

The `faq` block carries the FAQPage data. Keep one H1, in the hero.
