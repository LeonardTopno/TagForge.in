# Jewellery Tag Printer

Multi-tenant jewellery tag printing app for shops using a browser-based first printing flow.

## Stack

- Backend: FastAPI, SQLAlchemy, PostgreSQL (Docker) or SQLite (local)
- Frontend: React, Vite, TypeScript
- Printing v1: browser print dialog with millimetre-based CSS

## First Milestone

A shop can register, create jewellery weight tags, preview a printable tag, save it, reprint it, and tune print layout settings for the TVS LP 46 NEO.

No barcode is included in this version, per project requirement.

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

## Billing

New shops receive `FREE_REGISTRATION_CREDITS` on registration. Creating or reprinting tags spends credits unless an unlimited plan is active.

Seeded plans:

- Starter: ₹1000 for 500 credits, 90 days
- Growth: ₹1500 for 750 credits, 90 days
- Pro Annual: ₹10000 unlimited printing for 365 days

Local purchases can be confirmed in the app for testing. Replace that path with Razorpay before production.

## Notes

- Browser printing is intentionally the first implementation because the hosted app cannot directly access a USB printer in a shop.
- Tag size, font, and offsets are stored per shop in settings so the layout can be calibrated on a TVS LP 46 NEO.
- A future Windows print agent can be added after the physical label dimensions and offsets are proven.
