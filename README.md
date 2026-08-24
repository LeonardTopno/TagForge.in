# Jewellery Tag Printer

Multi-tenant jewellery tag printing app for shops using a **TVS LP 46 NEO browser-print workflow**.

This copy is the **PHP, MySQL, HTML, CSS, JavaScript, and Bootstrap** edition for **shared hosting** (cPanel, Plesk, and similar).

The previous FastAPI + React + PostgreSQL/SQLite stack is kept in [`legacy-fastapi-react/`](legacy-fastapi-react/).

## Stack

- PHP 7.4+ with PDO MySQL
- MySQL 5.7+ / MariaDB 10.3+
- HTML, CSS, JavaScript
- Bootstrap 5 and Bootstrap Icons (CDN)
- Printing: TVS LP 46 NEO via the browser print dialog and millimetre CSS

## What a shop can do

1. **Register or sign in** — new shops receive free tag credits.
2. **Create a jewellery tag** — pick an item from the shop catalogue, enter Gold/Silver, weights, and purity. Net weight is calculated automatically. Creating a tag spends **1 credit**.
3. **Preview and print** — Actual Preview shows the hang tag at millimetre size. One browser page prints the full face: weights left, fold mark centre, item and barcode right. Fold the tag so the sticker backs meet. Print and reprint do **not** spend credits.
4. **Search history** — find saved tags and reprint without re-entering weights.
5. **Settings** — three sections in the sidebar:
   - **Shop Settings** — name, address, phone, GST No, and logo upload (shown in the sidebar, not on the tag)
   - **Tag Settings** — prefix, millimetre width/height, font, X/Y offsets, and printer calibration help
   - **Item Settings** — jewellery item catalogue used on Create Tag

## Printer — TVS LP 46 NEO

| Specification | Value |
| --- | --- |
| Brand / model | TVS Electronics LP 46 NEO |
| Resolution | 203 DPI (~48 characters per line) |
| Print speed | 6 ips (150 mm/s) |
| Interface | USB |
| Sensors | Movable black mark and gap sensor |
| Ribbon | Thermal transfer (up to 300 m; 1/2" or 1" core) |
| Max print width | ~104 mm (4 inch class) |

Printing uses the **browser print dialog** on the counter PC or tablet where the printer is installed — not direct USB from the hosted app. When the dialog opens, select **TVS LP 46 NEO**. Use scale **100%**, minimum margins, and no headers or footers.

## Tag paper

- **Type:** white glossy synthetic jewellery **barbell tags** (rat-tail / dumbbell style)
- **Print method:** thermal transfer with ribbon (not direct thermal)
- **Roll format:** two-up — two tags side by side on the liner (~75–80 mm liner width)
- **Structure:** printable face → narrow neck/bridge → adhesive tail (wraps around jewellery)

### Tag size (reference: Bin Ismail Gold)

| Dimension | Measured / estimated |
| --- | --- |
| Total tag width (face + neck + tail) | ~72–80 mm |
| Tag height | ~15–18 mm (app default **18 mm**) |
| Printable face | ~50–55 mm × ~15–18 mm |
| Neck / bridge | ~3–5 mm (not printed) |
| Tail | ~15–22 mm (not printed) |

**Suggested starting values in Tag Settings:** width **80 mm**, height **18 mm**, font **7–8 pt**. Adjust X/Y offsets after a test print until preview and physical tag match.

## Print preview layout

On **Create Tag**, the **Actual Preview** panel shows the hang tag at the shop’s millimetre size before printing.

| Area | Content |
| --- | --- |
| Left panel | Grs.Wt, Stn.Wt, Nt.Wt |
| Centre | Dashed fold mark — fold here after printing so sticker backs meet |
| Right panel | Category + item name (e.g. GOLD RING), Code 128 barcode, tag number |

The barbell neck and tail to the right of the white face are structural only and are not printed.

## Shared hosting (cPanel)

1. In cPanel, create a MySQL database and a user, and grant the user **ALL PRIVILEGES** on that database.
2. Upload this project to `public_html` (or a subdirectory such as `public_html/tags`).
3. Make sure `includes/` and `uploads/` are writable (755 or 775).
4. Visit `https://your-domain/install.php`.
5. Enter the MySQL host (usually `localhost`), database name, user, and password. Optionally create an admin portal account.
6. After a successful install, **delete `install.php`**.
7. Open the shop app at `index.php` and the admin portal at `admin.php`.

Do not upload `legacy-fastapi-react/` to the web root if you can avoid it. It is a source backup, not part of the hosted app. The included `.htaccess` blocks web access to that folder, `includes/`, and `sql/`.

### Local PHP run

Create a MySQL database, copy `includes/config.example.php` to `includes/config.php`, edit the credentials, then either run `install.php` in the browser or:

```bash
php -S localhost:8080
```

Open http://localhost:8080/install.php

## Configuration

Installer writes `includes/config.php`. You can also copy `includes/config.example.php`.

| Key | Default | Purpose |
| --- | --- | --- |
| `db_host` | `localhost` | MySQL host |
| `db_name` | `tag_printer` | MySQL database |
| `db_user` | | MySQL user |
| `db_pass` | | MySQL password |
| `secret_key` | `change-this-before-production` | Session signing |
| `tag_price_inr` | `2` | Displayed rupee price per tag |
| `free_registration_credits` | `15` | Credits granted when a shop registers |

## App URLs

| Page | Path |
| --- | --- |
| Installer | `/install.php` |
| Shop app | `/index.php` |
| Admin portal | `/admin.php` |
| JSON API | `/api.php?r=...` |
| Health | `/api.php?r=health` |

Sessions (cookies) replace JWT. Login is same-origin; no separate API host is required.

## Billing

New shops receive `free_registration_credits` on registration. **Creating a tag spends 1 credit** unless an unlimited plan is active. Printing and reprinting do not spend credits.

Seeded plans:

- Starter: ₹1000 for 500 credits, 90 days
- Growth: ₹1500 for 750 credits, 90 days
- Pro Annual: ₹10000 unlimited printing for 365 days

Local purchases can be confirmed in the app for testing. Replace that path with Razorpay before production.

## Project layout

```
index.php              Shop SPA shell
admin.php              Admin portal shell
api.php                JSON API router
install.php            One-time MySQL installer (delete after use)
includes/              PHP bootstrap, schema, helpers, layout
assets/css/app.css     Shop and admin styles (print CSS included)
assets/js/             app.js, api.js, admin.js, barcode.js
uploads/logos/         Shop logos (gitignored contents)
docs/                  Product, technical, and progress HTML overviews
legacy-fastapi-react/  Previous FastAPI + React stack (reference only)
```

## Docs

Open these in a browser from the repo:

- [`docs/application.html`](docs/application.html) — product overview, printer and tag media specs
- [`docs/technical.html`](docs/technical.html) — data model and API
- [`docs/progress.html`](docs/progress.html) — done, next, and application-flow diagram

## Notes

- **Printer:** TVS LP 46 NEO. Select it in the browser print dialog — no direct USB path from the web app.
- **Print Preview:** Create Tag → **Actual Preview** shows the single-side layout at millimetre size before you print.
- Calibrate layout in **Settings → Tag Settings**: millimetre width, height (default 18 mm), font, and X/Y offsets.
- Shop logos are uploaded in **Settings → Shop Settings** and shown in the sidebar. They are not printed on the hang tag.
- Each shop keeps an item catalogue (Ring, Chain, and so on) under **Settings → Item Settings**.
- The previous Python/React app remains in `legacy-fastapi-react/` for reference.
