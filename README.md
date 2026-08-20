# Jewellery Tag Printer

Multi-tenant jewellery tag printing app for shops using a **TVS LP 46 NEO browser-print workflow**.

## Stack

- Backend: FastAPI, SQLAlchemy, PostgreSQL (Docker) or SQLite (local)
- Frontend: React, Vite, TypeScript
- Printing v1: TVS LP 46 NEO via the browser print dialog and millimetre CSS
- UI: responsive layout for phone, tablet, and desktop; tested in modern Chromium, Firefox, and Safari

## First Milestone

A shop can register, create jewellery weight tags, preview a single-side hang tag at millimetre size, save, reprint, and tune print layout for the TVS LP 46 NEO.

The printable face shows weights on the left, a centre fold mark, and item plus Code 128 barcode on the right. Fold the tag so the sticker backs meet. One browser print page — not direct USB.

## Printer

**TVS LP 46 NEO** (TVS Electronics · LP 46 NEO) — 203 DPI desktop label printer for jewellery barbell hang tags.

Printing uses the **browser print dialog** on the counter PC or tablet where the printer is installed — not direct USB from the hosted app. When the dialog opens, select **TVS LP 46 NEO** as the printer. Use scale **100%**, minimum margins, and no headers or footers.

Tag paper: white glossy synthetic jewellery barbell tags (thermal transfer ribbon). Default label size in the app: **80 × 18 mm**.

## Print Preview

On **Create Tag**, the **Actual Preview** panel shows the hang tag at the shop’s millimetre size before printing. The caption reads `{width} x {height} mm · single side, fold at centre`.

The preview matches what prints on one browser page:

| Area | Content |
| --- | --- |
| Left panel | Grs.Wt, Stn.Wt, Nt.Wt |
| Centre | Dashed fold mark — fold here after printing so sticker backs meet |
| Right panel | Category + item name (e.g. GOLD RING), Code 128 barcode, tag number |

The barbell neck and tail to the right of the white face are structural only and are not printed. **Save & Print** and **Test Print** send this preview to the browser print dialog. Tune width, height, font, and X/Y offsets under **Settings → Tag Settings** until preview and physical tag align.

## Printing (TVS LP 46 NEO)

This app is built for the **TVS LP 46 NEO** label printer (see [Printer](#printer) and [Print Preview](#print-preview) above).

Printing goes through the **browser print dialog** — not direct USB from the hosted app. When you print, choose **TVS LP 46 NEO** in that dialog. One page prints the full tag face shown in Actual Preview. Fold on the centre line so the adhesive backs meet.

Tag size and offsets are set in **millimetres** under **Settings → Tag Settings** (width, height, font, horizontal and vertical offsets). The screen also shows the target printer and tag paper type. Default starting size is **80 × 18 mm**. Adjust values and test-print on the TVS LP 46 NEO until the preview matches the physical label.

## Docs

Open these in a browser from the repo:

- `docs/application.html` — product overview
- `docs/technical.html` — stack, API, data model
- `docs/progress.html` — done, next, and application-flow diagram

## Configuration

Copy `backend/.env.example` to `backend/.env` and adjust as needed.

| Variable | Default | Purpose |
| --- | --- | --- |
| `DATABASE_URL` | `sqlite+aiosqlite:///./tag_printer.db` | Database connection |
| `ALLOWED_ORIGINS` | `http://localhost:5173,http://127.0.0.1:5173` | CORS origins |
| `SECRET_KEY` | `change-this-before-production` | JWT signing key |
| `TAG_PRICE_INR` | `2` | Displayed rupee price per tag |
| `FREE_REGISTRATION_CREDITS` | `15` | Credits granted when a shop registers |

Frontend API base URL defaults to `http://localhost:8000`. Override with `VITE_API_BASE_URL`.

## Run With Docker

```bash
cp backend/.env.example backend/.env
docker compose up --build
```

Open:

- Frontend: http://localhost:5173
- Admin portal: http://localhost:5173/admin-portal
- Backend API: http://localhost:8000/docs

## Run Without Docker

Backend:

```bash
cd backend
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env
uvicorn app.main:app --reload
```

Frontend:

```bash
cd frontend
npm install
npm run dev
```

## Deployment

Docker Compose in this repo is set up for **local development** (Vite dev server, Uvicorn `--reload`, bind mounts). For production, run Postgres plus a built frontend and a non-reload API behind HTTPS.

### Checklist

1. **Postgres** — use a managed database or the Compose `postgres` service with a persistent volume.
2. **Backend** — set `APP_ENV=production`, a strong `SECRET_KEY`, and `DATABASE_URL` pointing at Postgres.
3. **CORS** — set `ALLOWED_ORIGINS` to your public frontend origin(s), e.g. `https://tags.example.com`.
4. **Frontend** — build with the public API URL baked in via `VITE_API_BASE_URL`.
5. **Uploads** — persist `backend/uploads/` (shop logos) on disk or replace with object storage later.
6. **TLS** — terminate HTTPS at Nginx, Caddy, or your cloud load balancer.

### Build frontend

`VITE_API_BASE_URL` is read at **build time**. Set it to the URL shops will use for the API (same host with `/api` or a separate API subdomain).

```bash
cd frontend
npm ci
VITE_API_BASE_URL=https://api.example.com npm run build
```

Serve `frontend/dist/` as a static site. The SPA uses path-based routes (`/admin-portal`), so configure the web server to fall back to `index.html` for unknown paths.

Example Nginx location blocks:

```nginx
location / {
  root /var/www/tag-printer;
  try_files $uri $uri/ /index.html;
}
```

### Run backend (production)

```bash
cd backend
python -m venv .venv
source .venv/bin/activate   # Windows: .venv\Scripts\activate
pip install -r requirements.txt
cp .env.example .env        # then edit for production
uvicorn app.main:app --host 0.0.0.0 --port 8000
```

Use a process manager (systemd, supervisord) or container orchestration instead of running Uvicorn directly on a public host. Do **not** use `--reload` in production.

Production `backend/.env` example:

```env
APP_ENV=production
DATABASE_URL=postgresql+asyncpg://user:password@db-host:5432/tag_printer
ALLOWED_ORIGINS=https://tags.example.com
SECRET_KEY=<long-random-string>
TAG_PRICE_INR=2
FREE_REGISTRATION_CREDITS=15
```

### Docker notes

To deploy with Compose, copy `backend/.env.example` to `backend/.env`, set production values, and adjust the stack:

- Point `DATABASE_URL` at the `postgres` service (as in `docker-compose.yml`).
- Remove `--reload` from `backend/Dockerfile` and use a production CMD.
- Replace the `frontend` dev service with a multi-stage image that runs `npm run build` and serves `dist/` (Nginx or `vite preview` is only for smoke tests).
- Mount a named volume at `backend/uploads` so logos survive restarts.
- Set `VITE_API_BASE_URL` in the frontend **build** stage to the URL browsers will call.

### AWS pilot deploy

Lean layout for a **1–5 shop pilot** on AWS Free plan credits (~$200 / 6 months). Expect **~$10–12/month** AWS spend if you avoid RDS and NAT Gateway — enough headroom for the full 6-month window at low traffic.

Printing still happens in the shop browser (TVS LP 46 NEO). AWS only hosts the web app and API.

#### Architecture

```text
Shop browser
  ├─ https://tags.example.com     → CloudFront → S3 (frontend dist)
  └─ https://api.example.com      → EC2 t4g.micro (FastAPI + /uploads)
                                        └─ Neon Postgres (free, outside AWS)
```

| Layer | Service | Notes |
| --- | --- | --- |
| Frontend | **S3 + CloudFront** | SPA fallback to `index.html` for `/admin-portal` |
| API | **EC2 `t4g.micro`** (Mumbai `ap-south-1`) | Uvicorn via systemd; Nginx optional for TLS |
| Database | **[Neon](https://neon.tech) free Postgres** | Cheaper than RDS for a pilot; use `postgresql+asyncpg://` |
| Logos | **EBS volume** on EC2 | Mount at `backend/uploads/` — no code changes |
| DNS | Route 53 or GoDaddy | `tags.` → CloudFront, `api.` → EC2 Elastic IP |
| TLS | ACM on CloudFront + Nginx/Certbot on API | HTTPS required before real shop use |

**Cheaper variant:** host the frontend on [Cloudflare Pages](https://pages.cloudflare.com) (free) and run only EC2 on AWS (~$8–11/month).

#### Estimated cost (pilot traffic)

| Item | ~USD/month |
| --- | --- |
| EC2 `t4g.micro` (24/7) | $7–9 |
| EBS 10–20 GB | $1–2 |
| S3 + CloudFront | $1–3 |
| Neon Postgres | $0 (not AWS) |
| **AWS subtotal** | **~$10–14** |

At ~$12/month, $200 credits cover **well over 6 months** for a small pilot. Set billing alarms at $25, $50, and $100.

**Avoid on the pilot:** RDS (~$15–25/mo extra), NAT Gateway (~$30+/mo), unattached Elastic IPs, oversized instances.

#### Deploy checklist

1. Create a **Neon** database; copy the connection string.
2. Launch **EC2 `t4g.micro`** (Ubuntu 24.04, `ap-south-1`), attach a **10–20 GB EBS** volume for uploads.
3. Security group: allow **443** (and **80** for Certbot) from the internet; restrict Neon to the EC2 public IP.
4. On EC2: clone repo, install Python 3.12, `pip install -r backend/requirements.txt`, run Uvicorn with systemd on port 8000.
5. Mount EBS at `/app/uploads` (or symlink to `backend/uploads`).
6. Build frontend with the public API URL, upload `dist/` to S3, front with CloudFront.
7. Point DNS: `tags.example.com` → CloudFront, `api.example.com` → EC2 Elastic IP.
8. Verify `GET https://api.example.com/health` returns OK.

#### Environment variables

Backend `backend/.env` on EC2:

```env
APP_ENV=production
DATABASE_URL=postgresql+asyncpg://USER:PASSWORD@ep-xxx.ap-southeast-1.aws.neon.tech/tag_printer?sslmode=require
ALLOWED_ORIGINS=https://tags.example.com
SECRET_KEY=<long-random-string>
TAG_PRICE_INR=2
FREE_REGISTRATION_CREDITS=15
```

Frontend build (run locally or in CI before uploading to S3):

```bash
cd frontend
npm ci
VITE_API_BASE_URL=https://api.example.com npm run build
```

Upload the contents of `frontend/dist/` to the S3 bucket behind CloudFront.

#### CloudFront SPA routing

Configure custom error responses so client-side routes work:

- **403** → `/index.html` with response code **200**
- **404** → `/index.html` with response code **200**

#### After the pilot

- **Upgrade** AWS to a paid plan and keep the same stack (~$10–15/mo lean), or
- **Migrate** to Render / Railway + Neon + Cloudflare Pages, or
- **Move logos to S3** when you need EC2 replacements without EBS snapshots.

Open ports:

| Service | Dev port | Production |
| --- | --- | --- |
| Frontend | 5173 | 443 (HTTPS) |
| Backend API | 8000 | 443 or internal only |
| Postgres | 5432 | internal only |

### After deploy

- API docs: `https://api.example.com/docs`
- Shop app: `https://tags.example.com`
- Admin portal: `https://tags.example.com/admin-portal`
- Health check: `GET /health`

Shops still print through the **browser** on the counter PC or tablet where the TVS LP 46 NEO is installed. Deployment hosts the web app and API; it does not replace local browser printing.

## Billing

New shops receive `FREE_REGISTRATION_CREDITS` on registration. Creating a tag spends 1 credit unless an unlimited plan is active. Printing and reprinting do not spend credits.

Seeded plans:

- Starter: ₹1000 for 500 credits, 90 days
- Growth: ₹1500 for 750 credits, 90 days
- Pro Annual: ₹10000 unlimited printing for 365 days

Local purchases can be confirmed in the app for testing. Replace that path with Razorpay before production.

## Notes

- **Printer:** TVS LP 46 NEO (TVS Electronics). Select it in the browser print dialog — no direct USB path from the web app.
- **Print Preview:** Create Tag → **Actual Preview** shows the single-side layout at millimetre size before you print.
- **Single-side print, fold at centre** — weights and barcode print on one face; fold on the dashed centre mark.
- Calibrate layout in **Settings → Tag Settings**: millimetre width, height (default 18 mm), font, and X/Y offsets. Reference hardware notes are in `docs/application.html`.
- Create Tag uses item catalogue + Gold/Silver, gross and stone weights; net weight = gross − stone.
- Each shop keeps an item catalogue (Ring, Chain, and so on) used as the Create Tag dropdown.
- Shop logos are uploaded in Settings and shown in the sidebar. They are not printed on the hang tag.
- The shop UI uses a collapsible menu on narrow screens, scrollable tables, and touch-friendly controls.
- A future Windows print agent can be added after the physical label dimensions and offsets are proven.
