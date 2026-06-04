# Regal Markets — Production Deployment (VPS + domain)

Targets:
- **Frontend** → `https://regalmarkets.net` (Next.js via PM2, Nginx reverse proxy)
- **API** → `https://api.regalmarkets.net` (Laravel 9 via PHP-FPM)
- Server: Ubuntu VPS (e.g. `152.42.175.61`). Additive & idempotent — does **not** touch existing sites.

## Step 1 — DNS (do this first)
At your domain registrar / DNS provider for `regalmarkets.net`, add **A records** → your server IP:

| Type | Name  | Value           |
|------|-------|-----------------|
| A    | `@`   | `152.42.175.61` |
| A    | `www` | `152.42.175.61` |
| A    | `api` | `152.42.175.61` |

Wait for propagation (check: `dig +short regalmarkets.net` returns the IP). SSL needs this pointed first.

## Step 2 — Get the code onto the server
SSH into the VPS (as you normally do), then clone the repo:
```bash
sudo mkdir -p /var/www/regalmarkets && sudo chown -R "$USER" /var/www/regalmarkets
git clone https://github.com/PAPARICH669/regalmarkets.net.git /var/www/regalmarkets
# (private repo: authenticate with your GitHub username + a Personal Access Token when prompted)
```

## Step 3 — Run the one-command deploy
```bash
cd /var/www/regalmarkets
sudo EMAIL=you@example.com DB_PASSWORD='choose-a-strong-db-pass' bash deploy/deploy.sh
```
That installs PHP 8.2 + Node 20 + Nginx + MariaDB + PM2 + Certbot, creates the DB, configures
production `.env`, runs `migrate --seed`, builds the frontend, starts PM2, writes both Nginx server
blocks, adds the scheduler cron, and obtains SSL for all three hostnames.

If DNS isn't pointed yet, add `SKIP_SSL=1` to bring the sites up on HTTP first, then later:
```bash
sudo certbot --nginx -d regalmarkets.net -d www.regalmarkets.net -d api.regalmarkets.net -m you@example.com --agree-tos -n --redirect
```

## Step 4 — Verify
- `https://api.regalmarkets.net/api/public-settings` → JSON.
- `https://regalmarkets.net` → login screen with the logo.
- Log in `admin` / `password` → **change the admin password immediately**.

## Updating later
```bash
cd /var/www/regalmarkets && bash deploy/update.sh
```

## Notes / security
- The deploy uses a dedicated DB user (`regal`), not root.
- `config:cache` is enabled; re-run `update.sh` after `.env` changes.
- CORS: `config/cors.php` allows the API paths; tokens are Bearer so `*` origin is safe. Lock to
  `https://regalmarkets.net` for production if you prefer.
- After first login, rotate the admin password and the server root password; prefer key-only SSH.
- Scheduler runs `roi:run` 07:05, `rank:update` 07:10, `maintenance:sync` 00:00 & 07:00 (KL time).
