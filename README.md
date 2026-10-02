# Agate Beach Golf Course website

A static marketing site for Agate Beach Golf Course (Newport, Oregon), rebuilt from the
content and photography of <https://agatebeachgolf.net/>. Plain HTML, one stylesheet and one
small JavaScript file. No build step.

## Pages

| File | Purpose |
| --- | --- |
| `index.html` | Home: hero, about, signature hole 8, services, gallery, coffee shop, rates, map and Facebook |
| `course.html` | Scorecard, digital scorecard, rules and etiquette |
| `rates.html` | Green fees, carts, rentals and driving range |
| `pro-shop.html` | Pro shop information |
| `coffee-shop.html` | The Clubhouse cafe and Good Dog menu |
| `about.html` | History of the course and the Martin family |
| `contact.html` | Contact details, message form, map and Facebook |

## Structure

```
assets/
  css/styles.css   design system and page styles
  js/main.js       mobile nav, parallax, rates loader, Facebook widget sizing, mailto form
  js/scorecard.js  digital scorecard on the course page (reads yardage/par from the printed table)
  images/          optimized photos, scorecard, map and logos
data/
  rates.json       golf rates shown on the Home and Golf Rates pages
```

## Editing prices

All golf rates live in [`data/rates.json`](data/rates.json). Edit that file (or replace it with a new one) and commit it; no HTML changes are needed.

- `updated`: the date shown as "Updated ..." above the rates.
- `range_note`: the sentence under the rate cards.
- `cards`: one entry per rate card, shown in order. Each has a `title`, a `subtitle` and a list of `items` (`label` and `price`). Prices are plain text, so `"$24"` or `"Free"` both work.
- `"home": true` on a card also shows it on the home page. An `id` makes the card linkable (the range card uses `range`).
- Keep the JSON valid (double quotes, commas between entries). If it can't be read, the site shows a message asking visitors to call the pro shop.

## Embeds

- Google Maps: keyless `maps.google.com/maps?...&output=embed` iframe.
- Facebook: the Page plugin for `facebook.com/Agatebeachgolf`, sized by `main.js` (`[data-fb-page]`).

## Notes

- The contact form opens the visitor's email client (`mailto:`) because the site is static.
- Tee times and the customer portal link to ForeUP.
- Rates are loaded with `fetch`, so open the site through a web server or GitHub Pages rather than double-clicking the HTML files.

## Run locally

```
python -m http.server 8000
```

Then open <http://localhost:8000>. The site can be hosted as-is on GitHub Pages.