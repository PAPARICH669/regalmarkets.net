# Regal Markets — Setup (Windows)

## Toolchain on this machine
| Tool | Location | Version |
|------|----------|---------|
| PHP | `C:\xampp\php\php.exe` | 8.0.30 |
| MariaDB | `C:\xampp\mysql\bin` | 10.4.32 (MySQL-compatible) |
| Node/npm | `C:\Program Files\nodejs` | Node 24 / npm 11 |
| Composer | `tools/composer.phar` (bundled) | 2.10 |

These aren't on the system PATH for non-interactive shells. Either add them to PATH, or prefix
commands with the full path / a session PATH:
```powershell
$env:Path = "C:\xampp\php;C:\Program Files\nodejs;" + $env:Path
```

### Why Laravel 9 (not 12)
Laravel 12/11/10 require PHP ≥ 8.1–8.2; this machine has PHP **8.0.30**, so Laravel 9 (PHP ^8.0.2)
is the highest version that installs and runs. The business logic, Sanctum auth, Eloquent,
scheduler and migrations are equivalent. To use Laravel 12 later, install PHP 8.2+ and
`composer create-project laravel/laravel:^12` — the `app/` services/migrations port over with only
namespace/kernel-structure tweaks.

## 1. MySQL / MariaDB
Start it (XAMPP control panel, or `C:\xampp\mysql\bin\mysqld.exe`), then:
```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE regal_markets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```
Default `.env` uses `root` with no password (XAMPP default). Edit `backend/.env` if yours differs.

## 2. Backend
```powershell
cd C:\Users\HP\Desktop\REGALMARKETS.NET\backend
php ..\tools\composer.phar install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
php artisan serve            # http://localhost:8000
```
Run tests: `php artisan test` (uses in-memory SQLite, configured in `phpunit.xml`).

If `8000` is busy: `php artisan serve --port=8001` and update `frontend/.env.local`
(`NEXT_PUBLIC_API_URL=http://localhost:8001/api`).

## 3. Frontend
```powershell
cd C:\Users\HP\Desktop\REGALMARKETS.NET\frontend
npm install
npm run dev                  # http://localhost:3000
```
`.env.local` controls the API URL and app name.

## 4. Scheduler (Windows Task Scheduler)
Create a task that runs **every 1 minute**:
- Program: `C:\xampp\php\php.exe`
- Arguments: `C:\Users\HP\Desktop\REGALMARKETS.NET\backend\artisan schedule:run`
- Start in: `C:\Users\HP\Desktop\REGALMARKETS.NET\backend`

Or, for development, run a long-lived worker: `php artisan schedule:work`.

This drives: `maintenance:sync` at 00:00 & 07:00, `roi:run` at 07:05, `rank:update` at 07:10
(Asia/Kuala_Lumpur).

## Troubleshooting
- **`Could not open input file: composer.phar`** — use the full path `tools\composer.phar`.
- **Advisory-blocked installs** — the bundled composer ignores advisory blocks
  (`composer config --global policy.advisories.block false` if you use your own).
- **CORS** — `config/cors.php` allows `api/*`; set `FRONTEND_URL`/`SANCTUM_STATEFUL_DOMAINS` in
  `.env` if you change ports/hosts.
- **ROI shows nothing** — run `php artisan roi:run` (the seeder already ran 6 days of history).
