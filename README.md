# Regal Markets — Premium USDT Investment & Networking Platform

A full-stack networking investment platform: 200% ROI investment packages (daily payout),
unilevel sponsor bonus, an **unlimited-level differential matching bonus engine**, a 5-tier rank
system, a 3-wallet ledger, deposit/withdrawal/transfer/reinvest flows with admin approval, a daily
maintenance window, and a luxury dark/gold UI for both members and admins.

- **Backend:** Laravel 9 API (Sanctum token auth) · MySQL/MariaDB · `Asia/Kuala_Lumpur` · USDT
- **Frontend:** Next.js 15 (App Router) · React 19 · Tailwind CSS v4 · Recharts
- **Currency:** USDT (`decimal(18,8)` precision, bcmath math)

> **Stack note.** The spec requested Laravel 12 + Next.js 15. This machine runs **PHP 8.0.30**
> (Laravel 12/11/10 require PHP ≥ 8.1–8.2), so the backend targets **Laravel 9** — functionally
> identical for everything here (Sanctum, Eloquent, services, scheduler). The frontend is pinned to
> **Next.js 15** as requested. See `docs/SETUP.md`.

```
REGALMARKETS.NET/
├─ backend/      Laravel 9 API + business engines + migrations + seeders + tests
├─ frontend/     Next.js 15 app (landing, auth, member dashboard, admin panel)
├─ docs/         API.md · BUSINESS_LOGIC.md · SETUP.md
└─ tools/        bundled composer.phar
```

---

## Quick start

Prereqs: PHP 8.0+ with `pdo_mysql`, `bcmath`, `mbstring`; Composer; Node 18.18+/20+; MySQL/MariaDB.
(On this machine: XAMPP PHP at `C:\xampp\php`, MariaDB at `C:\xampp\mysql`, Node at `C:\Program Files\nodejs`.)

### 1. Database
```sql
CREATE DATABASE regal_markets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Backend (Laravel 9 API)
```bash
cd backend
php ../tools/composer.phar install         # composer install
# .env is preconfigured (DB=regal_markets, APP_TIMEZONE=Asia/Kuala_Lumpur). Adjust DB creds if needed.
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve                          # http://localhost:8000
```
> If port 8000 is taken, run `php artisan serve --port=8001` and set the frontend
> `NEXT_PUBLIC_API_URL` to match.

### 3. Frontend (Next.js 15)
```bash
cd frontend
npm install
# .env.local sets NEXT_PUBLIC_API_URL=http://localhost:8000/api
npm run dev                                # http://localhost:3000
```

### 4. Scheduler (ROI, ranks, maintenance)
On Windows, add a Task Scheduler task running every minute:
```
C:\xampp\php\php.exe C:\Users\HP\Desktop\REGALMARKETS.NET\backend\artisan schedule:run
```
or run a worker in a terminal: `php artisan schedule:work`. This fires:
`maintenance:sync` (00:00 & 07:00), `roi:run` (07:05), `rank:update` (07:10) — all KL time.

Run any engine manually too:
```bash
php artisan roi:run            # pay today's ROI + matching rollup
php artisan roi:run --date=2026-06-01
php artisan rank:update        # recompute ranks
php artisan maintenance:sync   # open/close the maintenance window
```

---

## Demo accounts (password: `password`)

Login accepts **username or email**.

| Role         | Username      | Email                       |
|--------------|---------------|-----------------------------|
| Admin        | `admin`       | admin@regalmarkets.net      |
| Group Leader | `groupleader` | gl@regalmarkets.net         |
| Team Leader  | `teamleader`  | tl@regalmarkets.net         |
| Senior       | `senior`      | senior@regalmarkets.net     |
| Fan          | `fan`         | fan@regalmarkets.net        |
| Member       | `member`      | member@regalmarkets.net     |

The seeder builds an 18-member network with approved deposits, active packages, 6 days of ROI
history, sponsor + matching bonus logs, and pending deposits/withdrawals for the admin queues.

---

## Verified

- `php artisan test` → **9/9 passing**, including the exact matching example
  (SENIOR 8% / TEAM LEADER 4% / GROUP LEADER 4%), same-rank stop, 5-level sponsor, 200% ROI cap,
  and wallet rules (E→A allowed, daily withdrawal cap, hold/refund).
- `npm run build` → all 26 routes compile.
- API smoke-tested over HTTP (login → dashboard).

See `docs/` for the API reference, the business-logic deep-dive (engines), and full setup notes.

## Security

Sanctum bearer tokens, per-route rate limiting (`throttle:10,1` on auth), IP + login history,
admin audit logs, append-only `wallet_transactions` ledger, frozen-member middleware, maintenance
middleware, 2FA-ready user schema, server-side validation on every money path.

> Local-dev advisories: the installed `next@15.5.4` and `recharts@2.x` have upstream advisories —
> bump to the latest patched versions before production (`npm i next@latest recharts@latest`).
