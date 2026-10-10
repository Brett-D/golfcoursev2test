# Agate Beach Golf Course website

A static marketing site for Agate Beach Golf Course (Newport, Oregon), rebuilt from the
content and photography of <https://agatebeachgolf.net/>. Plain HTML, one stylesheet and one
small JavaScript file. No build step.

## Pages

| File | Purpose |
| --- | --- |
| `index.html` | Home: hero, announcements, weather, about, signature hole 8, services, gallery, cafe (with specials), rates, map and Facebook |
| `course.html` | Hole-by-hole card stack, course map, printed yardage/handicap table, rules and etiquette |
| `scorecard.html` | Digital scorecard with a layout picture of every hole (the yardage table at the bottom feeds it) |
| `rates.html` | Green fees, carts, rentals and driving range |
| `pro-shop.html` | Pro shop information |
| `cafe.html` | The Clubhouse cafe: hours, specials, the breakfast & lunch menu and the Good Dog menu |
| `about.html` | History of the course and the Martin family |
| `contact.html` | Contact details, message form, map and Facebook |
| `ownerpage/` | Private settings page (prices, cafe menu and specials, colors); not linked from the site, see [Owner page](#owner-page) |

## Structure

```
assets/
  .htaccess        cache times for the site's own files (Apache/LiteSpeed hosting)
  css/styles.css   design system and page styles
  js/main.js       mobile nav, parallax, rates/cafe/notices loaders, weather, Facebook widget sizing, contact form
  js/hole-viewer.js   shared full-size hole picture viewer (carousel and scorecard)
  js/hole-carousel.js hole card stack on the course page (par/yards read from the table on that page)
  js/scorecard.js  digital scorecard on scorecard.html (reads yardage/par/handicap from the table on that page)
  video/           home hero loop (hero.mp4 + hero.webm; poster is images/hero-poster.jpg)
  images/          optimized photos, scorecard, map and logos
  images/holes/vector/  hole-1..9.svg, the illustrated hole graphics used on the course page and the scorecard
  tools/make-hole-svgs.py  rebuilds those graphics from the printed scorecard PDF (see the notes at the top of the file)
data/
  .htaccess        tells browsers and Cloudflare to check these files for changes every time
  rates.json       golf rates shown on the Home and Golf Rates pages
  cafe.json        cafe hours, specials, breakfast & lunch menu and Good Dog menu
  theme.css        website colors chosen on the owner page (loaded by every page; empty = colors in styles.css)
  theme.json       the same colors, read by the owner page (created on the first color save)
  notices.json     saved copy of the ForeUp notices (kept up to date by notices.php)
  backups/         earlier copies of the files above, made by the owner page (on the server only)
ownerpage/
  index.php        the owner page (agatebeachgolf.net/ownerpage)
  password.example.php  template for password.php, which holds the password on the server only
notices.php       reads the ForeUp notices on PHP hosting (Bluehost)
htaccess-additions.txt  snippet to paste into Bluehost's public_html/.htaccess (not uploaded as a file)
```

## Owner page

<https://agatebeachgolf.net/ownerpage> is a private page for changing the website without editing files. Nothing on the site links to it; the password is what protects it.

- **Prices**: every rate card (title, subtitle, lines and prices), which cards also show on the home page, and the note under the cards. Saving sets the "Updated" date to today.
- **Cafe**: the hours (each day open or closed with opening and closing times, a heading and a note; shown as a week strip under the "View the Menu" and "Call" buttons, with "Open now / Closed now" worked out in Oregon time) and **closed dates** for breaks, holidays or events (from/through dates and an optional reason; the Cafe page announces them up to 30 days ahead, marks those days "Closed", says when the cafe reopens, and drops them once they're over), the specials (heading, items with prices, small print, and when they show: until turned off, for one chosen week, or from one date to another; weekly and dated specials appear and come down on their own), the breakfast & lunch menu (sections, items, sizes, prices, descriptions) and the Good Dog menu (names, prices, toppings, "build your own"). Specials can also show in the Cafe section of the home page.
- **Colors**: ready-made themes or each color picked by hand, with a live preview of the home page. Saving changes every page.

Changes are live as soon as they are saved. Each save keeps the previous file in `data/backups/` (the newest 30 of each), so a mistake can be undone by copying a backup back over the file in File Manager.

**Setting the password** (once, and again whenever you want to change it): in Bluehost File Manager open `public_html/ownerpage`, copy `password.example.php` to `password.php`, edit `password.php` and put the password between the quotes in place of `CHANGE-ME` (at least 8 characters). Visitors can't read it: the server runs `.php` files instead of showing them. `password.php` is listed in `.gitignore`, so it never goes to GitHub; don't upload one from this folder. Until it is set, the owner page only shows these steps.

Security: after 5 wrong passwords from one place (or 30 from anywhere) signing in is paused for 15 minutes; you're signed out after 4 hours without using the page; the page isn't shown to search engines. It needs PHP, so on GitHub Pages it doesn't work (the rest of the site does).

**Important when uploading**: once the owner page has been used, the server has the real `data/rates.json`, `data/cafe.json`, `data/theme.css` and `data/theme.json`. Don't upload those files from this folder afterwards, or the owner's changes are replaced. If you want them in GitHub, download them from the server first.

## Editing prices

The easiest way is the [owner page](#owner-page). The rates live in [`data/rates.json`](data/rates.json), which can also be edited by hand (then upload it); no HTML changes are needed.

- `updated`: the date shown as "Updated ..." above the rates.
- `range_note`: the sentence under the rate cards.
- `cards`: one entry per rate card, shown in order. Each has a `title`, a `subtitle` and a list of `items` (`label` and `price`). Prices are plain text, so `"$24"` or `"Free"` both work.
- `"home": true` on a card also shows it on the home page. An `id` makes the card linkable (the range card uses `range`).
- Keep the JSON valid (double quotes, commas between entries). If it can't be read, the site shows a message asking visitors to call the pro shop.

## Course notices

The notices under the **Announcements** heading below the home page banner are the welcome message from the ForeUp booking page. The heading hides along with them when there are none. ForeUp does not let other sites read it from the browser, so [notices.php](notices.php) reads it on the server and answers with JSON. It saves the result to [data/notices.json](data/notices.json) and only asks ForeUp again once that copy is more than 20 minutes old (and, if ForeUp is down, keeps serving the last saved copy). Edit the message in ForeUp as usual; it shows up on the site within about 20 minutes. The banner hides itself when the message is empty.

Upload `notices.php` next to `index.html` and make sure the `data/` folder is writable by PHP (the Bluehost default). Hosts without PHP, such as GitHub Pages, simply show the saved `data/notices.json` and never update it.

## Weather

The home page shows the current conditions at Newport airport (station KONP), a short forecast for the course and today's sunrise and sunset. The weather comes straight from the National Weather Service (`api.weather.gov`; free, no account or key, fine for business use) and refreshes every 20 minutes. Sunrise and sunset are worked out in `main.js`, so they show even if the weather service is down.

## Embeds

- Google Maps: keyless `maps.google.com/maps?...&output=embed` iframe.
- Facebook: the Page plugin for `facebook.com/Agatebeachgolf`, sized by `main.js` (`[data-fb-page]`).

## Notes

- The contact form opens the visitor's email client (`mailto:`) because the site is static.
- Tee times and the customer portal link to ForeUP.
- Rates and the cafe menu are loaded with `fetch`, so open the site through a web server or GitHub Pages rather than double-clicking the HTML files.

## Run locally

```
python -m http.server 8000
```

Then open <http://localhost:8000>. The site can be hosted as-is on GitHub Pages or any web host. On PHP hosting such as Bluehost, add [notices.php](notices.php) so the course notices stay current.

## Publishing changes (Bluehost)

Cloudflare and browsers keep copies of the CSS and scripts for up to 24 hours and of the pages for 2 hours. To make a change show up straight away:

1. Run `powershell -ExecutionPolicy Bypass -File tools\bump-version.ps1`. It gives the CSS, scripts and card pictures a new `?v=` number, so they count as new files.
2. Upload every changed file. The version bump changes every `.html` page and `assets/js/*.js`, so upload those too. Don't upload `data/rates.json`, `data/cafe.json` or `data/theme.*` once the owner page is in use (see [Owner page](#owner-page)).
3. If a page itself still looks old, clear the cache in Bluehost (Websites, Settings, Performance, Clear Cache) and hard refresh.

## Contact form

The Contact page form posts to [contact.php](contact.php), which emails the message straight to teeoff@agatebeachgolf.net (no email app needed). It needs PHP hosting such as Bluehost: upload `contact.php` next to the pages. The messages arrive from teeoff@agatebeachgolf.net with the visitor's address in Reply-To, so hitting Reply answers them. If a message does not arrive, check the spam folder first.

Spam protection (all in `contact.php`, with the limits as constants at the top): a hidden trap field and a minimum time on the page; a filter that quietly drops messages with more than 2 links or typical spam phrases (two phrases, or one plus a link); a daily limit of 50 messages across all visitors, after which the page asks people to phone; at most 5 messages per visitor per hour; and a quick "what is two plus five?" question once a visitor has already sent 2 messages in the last hour. Dropped spam is not emailed and the sender is told it was sent. If a real message is ever blocked, change `MAX_LINKS`, `SPAM_PHRASES` or the other limits there.
Where PHP is not available (for example GitHub Pages) the form falls back to opening the visitor's email app, as before. To change where messages go, edit `TO_ADDRESS` at the top of `contact.php`.
