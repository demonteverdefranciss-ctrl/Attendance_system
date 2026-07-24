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

> **Critical:** Do **NOT** set `ASSET_URL` in Railway production variables.
> If it exists, delete it and redeploy — it breaks login branding and built assets.
> Only use `ASSET_URL=/attendance_system/public` in your **local** `.env`.
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
- The container waits for MySQL, runs migrations, then starts `php artisan serve`
  on `$PORT` (see `docker/entrypoint.sh`). `railway.toml` enables `/up` healthchecks
  (300s timeout) and auto-restart on failure.
- If the site shows **upstream error**, open Railway → web service → **Restart**,
  and confirm the **MySQL** service is still running (trial/credit issues stop both).
- Free/hobby tiers may sleep or run out of credits — first request after idle can
  be slow or fail until restart.
- Point the recognition service at production by setting, in
  `recognition-service/.env`: `API_BASE_URL=https://<your-domain>/api/v1`.
- Recognition on the school PC only opens the camera while a teacher has an
  **open attendance session**. If Railway is down or no session is open, the
  camera stays off even when the Tapo IP is correct.

---

## C. Backup Railway MySQL (before trial ends)

Your school PC can dump Railway’s database with XAMPP `mysqldump`.

1. Railway → **MySQL** service → **Settings → Networking** → enable **Public Networking / TCP Proxy**.
2. Copy the **public host** and **port**.
3. MySQL service → **Variables** → copy `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE`.
4. On the school PC (PowerShell):

```powershell
cd C:\xampp\htdocs\attendance_system
.\scripts\backup-railway-mysql.ps1
```

Paste host/port/user/password/database when asked.  
Output file: `backups/railway_attendance_YYYYMMDD_HHMMSS.sql` (gitignored).

Optional (no prompts):

```powershell
$env:RAILWAY_MYSQL_HOST="...."
$env:RAILWAY_MYSQL_PORT="...."
$env:RAILWAY_MYSQL_USER="...."
$env:RAILWAY_MYSQL_PASSWORD="...."
$env:RAILWAY_MYSQL_DATABASE="railway"
.\scripts\backup-railway-mysql.ps1
```

Keep the `.sql` file on a USB drive / Google Drive. Do **not** commit it to GitHub (student data).
