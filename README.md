# Agate Beach Golf Course — website

A responsive, professional marketing site for Agate Beach Golf Course (Newport, Oregon),
rebuilt from the content of <https://agatebeachgolf.net/>. It is a dependency-free static
site: plain HTML, one stylesheet and three small vanilla-JS files.

## Pages

| File | Purpose |
| --- | --- |
| `index.html` | Home — hero, course highlights, facilities, plan-your-visit |
| `course.html` | Course overview, scorecard, practice area and etiquette |
| `rates.html` | Green fees, rentals, passes and a tee time request form |
| `pro-shop.html` | Pro shop storefront with category filters and a shopping bag |
| `coffee-shop.html` | The café — hours, full menu and group catering |
| `contact.html` | Address, hours, directions and a contact form |

## Structure

```
assets/
  css/styles.css   mobile-first stylesheet
  js/cart.js       localStorage-backed cart store (ids + quantities only)
  js/main.js       mobile nav toggle, active link, cart badge
  js/shop.js       pro shop catalogue, filtering and bag rendering
  img/*.svg        logo, scenic artwork and product illustrations
```

## Responsive behaviour

The layout is mobile-first and verified from 390 px phones to 1366 px desktops:
a hamburger menu below 900 px switches to a horizontal navigation bar above it,
grids collapse from four/three columns to one, and wide tables scroll horizontally
inside their container instead of breaking the page.

## Pro shop storefront

`shop.js` holds the product catalogue. Adding, editing or removing an item is a matter of
editing that array — each entry needs an `id`, `name`, `price`, `category`, `image` and
`description`. The bag persists in `localStorage` and stores only product ids and
quantities; names and prices are always resolved from the catalogue at render time, and all
rendered text is set via `textContent`. "Reserve for pickup" is a front-end confirmation
only — no payment processing is wired up.

## Running locally

Any static file server works, for example:

```bash
python3 -m http.server 8000
```

Then open <http://localhost:8000/>.

## Publishing to GitHub Pages

The site is static and every link is relative, so it can be served from GitHub Pages as-is —
including from a project sub-path such as `https://<user>.github.io/golfcoursev2test/`.

1. Merge this branch into the default branch (`main`) so the site files are published from there.
2. In the repository, go to **Settings → Pages → Build and deployment** and set
   **Source** to *Deploy from a branch*, **Branch** to `main` and the folder to `/ (root)`, then save.
   (To publish without merging, pick this branch instead of `main`.)
3. Wait for the `pages-build-deployment` workflow to finish, then open
   <https://brett-d.github.io/golfcoursev2test/>.

The empty `.nojekyll` file at the repository root tells Pages to serve the files verbatim
instead of running them through Jekyll.

For a custom domain, add a `CNAME` file at the root containing the domain and point the
domain's DNS at GitHub Pages.

## Notes

* Imagery is original SVG artwork created for this site (coastal course scenes, clubhouse,
  café and product illustrations) so the site ships without external asset dependencies.
  Swap the files in `assets/img/` for photography when it is available.
* Rates, menu prices and scorecard yardages are representative and should be confirmed
  against current pro shop pricing before publishing.
