# Jewellery Tag Printer

Multi-tenant jewellery tag printing app for shops using a **TVS LP 46 NEO browser-print workflow**.

## Stack

- Backend: FastAPI, SQLAlchemy, PostgreSQL (Docker) or SQLite (local)
- Frontend: React, Vite, TypeScript
- Printing v1: TVS LP 46 NEO via the browser print dialog and millimetre CSS
- UI: responsive layout for phone, tablet, and desktop; tested in modern Chromium, Firefox, and Safari

## First Milestone

A shop can register, create jewellery weight tags, preview front and back hang-tag faces at millimetre size, save, reprint, and tune print layout for the TVS LP 46 NEO.

The back face encodes the tag number as Code 128. Print sends front then back as two browser pages.

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

- Browser printing is intentionally the first implementation because the hosted app cannot directly access a USB printer in a shop.
- Tag size, font, and offsets are stored per shop in settings so the layout can be calibrated on a TVS LP 46 NEO.
- Each shop keeps an item catalogue (Ring, Chain, and so on) used as the Create Tag dropdown.
- Shop logos are uploaded in Settings and shown in the sidebar. They are not printed on the hang tag.
- The shop UI uses a collapsible menu on narrow screens, scrollable tables, and touch-friendly controls.
- A future Windows print agent can be added after the physical label dimensions and offsets are proven.
