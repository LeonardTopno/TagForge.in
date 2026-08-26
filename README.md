# TagForge

**Print tags. Run your shop.**

Multi-tenant jewellery tag printing for shops — **TVS LP 46 NEO** browser-print workflow.

Product site: [https://tagforge.in](https://tagforge.in)

This copy is the **PHP, MySQL, HTML, CSS, JavaScript, and Bootstrap** edition for **shared hosting** (cPanel, Plesk, and similar).

The previous FastAPI + React + PostgreSQL/SQLite stack is kept in [`legacy-fastapi-react/`](legacy-fastapi-react/).

## Brand

| | |
| --- | --- |
| Name | **TagForge** |
| Tagline | **Print tags. Run your shop.** |
| Domain | [tagforge.in](https://tagforge.in) |
| Version | **1.0.0** |

## Production domains

| Purpose | URL | Entry |
| --- | --- | --- |
| Shop app | https://tagforge.in | `index.php` |
| Mobile app | https://app.tagforge.in | `app.php` |
| Admin portal | https://admin.tagforge.in | `admin.php` |
| API | https://api.tagforge.in | `api.php` |

Point all hostnames at the **same document root**. Host routing lives in `.htaccess` + `includes/hosts.php`.

- Full deploy guide: [`docs/deployment.html`](docs/deployment.html)
- DNS / host map: [`docs/domains.html`](docs/domains.html)

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

| Dimension | Measured |
| --- | --- |
| Printable length (face) | **64 mm** (6.4 cm) |
| Fold line | **32 mm** (3.2 cm) — centre |
| Tag height | **18 mm** (app default) |
| Neck / tail | Structural on die-cut; not printed |

**Suggested starting values in Tag Settings:** width **64 mm**, height **18 mm**, font **7–8 pt**. Adjust X/Y offsets after a test print until preview and physical tag match.

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
| `tag_price_inr` | `0` | Optional display-only per-tag rate |
| `free_registration_credits` | `20` | Free tags granted when a shop registers |
| `free_registration_validity_days` | `2` | Free pack validity in days |
| `monthly_plan_price_inr` | `599` | Monthly Unlimited price |
| `app_name` | `TagForge` | Product name in UI and mail |
| `app_tagline` | `Print tags. Run your shop.` | Primary brand tagline |
| `razorpay_key_id` / `razorpay_key_secret` | | Razorpay API keys |
| `app_url` / `urls.shop` | Shop canonical URL (emails, resets) |
| `urls.admin` / `urls.app` / `urls.api` | Admin, mobile, and API public URLs |
| `hosts.*` | Hostnames mapped to each surface |
| `cookie_domain` | `.tagforge.in` in production |
| `cors_origins` | Extra allowed browser origins for the API |
| `mail_from` | `noreply@example.com` | From address for PHP `mail()` |
| `mail_from_name` | `TagForge` | From display name |
| `mail_debug` | `false` | When true, forgot-password also returns `reset_url` for local testing |

## App URLs

| Page | Path / host |
| --- | --- |
| Installer | `/install.php` |
| Shop app | `/` or https://tagforge.in |
| Mobile entry | `/app.php` or https://app.tagforge.in |
| Admin portal | `/admin.php` or https://admin.tagforge.in |
| JSON API | `/api.php?r=...` or https://api.tagforge.in |
| Health | `/api.php?r=health` |

Sessions (cookies) replace JWT. On production, `cookie_domain=.tagforge.in` shares login across shop / app / admin / api.

## Billing

New shops receive **20 free tags valid for 2 days**. Creating a tag spends 1 free tag credit unless an unlimited plan is active. Printing and reprinting do not spend credits.

After the free pack is used up or expires, shops buy **Monthly Unlimited** at **₹599 per month** via **Razorpay**, and can choose **1–24 months** at checkout (total = ₹599 × months).

Configure Razorpay in `includes/config.php`:

| Key | Purpose |
| --- | --- |
| `razorpay_key_id` | Razorpay Key Id (test or live) |
| `razorpay_key_secret` | Razorpay Key Secret |
| `monthly_plan_price_inr` | Monthly price (default `599`) |
| `free_registration_credits` | Free tags on signup (default `20`) |
| `free_registration_validity_days` | Free pack validity (default `2`) |

If Razorpay keys are blank, Credits still works in **local test mode** (plan activates without Checkout).

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
