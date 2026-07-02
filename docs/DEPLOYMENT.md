# Deployment Guide (put the app online)

The repo ships a **Dockerfile**, so it can be deployed to any Docker-friendly
host. This guide uses **Railway** (easiest for Laravel + MySQL); a **Render**
note is at the bottom.

> The Python `recognition-service/` is **not** deployed — it stays on the school
> PC and points its `API_BASE_URL` at the online app. The Flutter app (later)
> also talks to this online API.

---

## A. Deploy on Railway (recommended)

### 1. Create the project
1. Go to <https://railway.app> → sign in with GitHub.
2. **New Project → Deploy from GitHub repo** → pick `Attendance_system`.
3. Railway detects the `Dockerfile` and starts building.

### 2. Add a MySQL database
- In the project: **New → Database → Add MySQL**.

### 3. Set environment variables (web service → Variables)
Generate an app key locally first:
```bash
php artisan key:generate --show      # copy the "base64:..." value
```
Then add these variables:

| Key | Value |
|-----|-------|
| `APP_NAME` | `Attendance System` |
| `APP_ENV` | `production` |
| `APP_KEY` | *(the base64 key you generated)* |
| `APP_DEBUG` | `false` |
| `APP_TIMEZONE` | `Asia/Manila` |
| `APP_URL` | *(your Railway URL, set after step 4)* |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `SESSION_SECURE_COOKIE` | `true` |

> Do **NOT** set `ASSET_URL` in production — leaving it empty makes assets load
> from `/build` at the domain root (correct when not in a subdirectory).
> The `${{MySQL.*}}` syntax references the MySQL service's variables.

### 4. Get a public URL
- Web service → **Settings → Networking → Generate Domain**.
- Copy that URL into the `APP_URL` variable, then **redeploy**.

### 5. Seed the first admin (one time)
Migrations run automatically on boot. To create the admin + sample data:
- Web service → **Shell** (or `railway run`):
```bash
php artisan db:seed --force
```

### 6. Log in and secure it
- Open your URL → log in as `admin / Admin@123`.
- **Immediately change the admin password** (and remove/disable the sample
  teacher/parent accounts) — the seeded passwords are public in this repo.

---

## B. Deploy on Render (alternative)

1. <https://render.com> → **New → Web Service → Build from a repo** → pick the repo.
2. Environment: **Docker** (Render auto-detects the `Dockerfile`).
3. **Database:** Render's free managed DB is **PostgreSQL, not MySQL**. Either:
   - use an external MySQL (Railway MySQL, Aiven, or Clever Cloud free tier) and
     point the `DB_*` vars at it, **or**
   - switch `DB_CONNECTION=pgsql` and use Render's free Postgres (the migrations
     are portable).
4. Set the same environment variables as the Railway table above.
5. Deploy, then run `php artisan db:seed --force` from the Render shell.

---

## Notes
- First deploy builds the Docker image (installs PHP/Node deps and runs
  `npm run build`) — this takes a few minutes.
- The container runs `php artisan config:cache` and `php artisan migrate --force`
  on every boot (see `docker/entrypoint.sh`).
- Free tiers may **sleep on inactivity** (first request after idle is slow) and
  have limited hours/credits — fine for a capstone demo.
- Point the recognition service at production by setting, in
  `recognition-service/.env`: `API_BASE_URL=https://<your-domain>/api/v1`.
