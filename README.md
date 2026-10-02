# Agate Beach Golf Course website

A static marketing site for Agate Beach Golf Course (Newport, Oregon), rebuilt from the
content and photography of <https://agatebeachgolf.net/>. Plain HTML, one stylesheet and one
small JavaScript file. No build step.

## Pages

| File | Purpose |
| --- | --- |
| `index.html` | Home: hero, about, signature hole 8, services, gallery, coffee shop, rates, map and Facebook |
| `course.html` | Scorecard, rules and etiquette |
| `rates.html` | Green fees, carts, rentals and driving range |
| `pro-shop.html` | Pro shop information |
| `coffee-shop.html` | The Clubhouse cafe and Good Dog menu |
| `about.html` | History of the course and the Martin family |
| `contact.html` | Contact details, message form, map and Facebook |

## Structure

```
assets/
  css/styles.css   design system and page styles
  js/main.js       mobile nav, sticky header, reveal-on-scroll, Facebook widget sizing, mailto form
  images/          optimized photos, menu images, scorecard and logo
```

## Embeds

- Google Maps: keyless `maps.google.com/maps?...&output=embed` iframe.
- Facebook: the Page plugin for `facebook.com/Agatebeachgolf`, sized by `main.js` (`[data-fb-page]`).

## Notes

- The contact form opens the visitor's email client (`mailto:`) because the site is static.
- Tee times and the customer portal link to ForeUP.
- Update rates in `rates.html` and `index.html` when prices change.

## Run locally

```
python -m http.server 8000
```

Then open <http://localhost:8000>. The site can be hosted as-is on GitHub Pages.