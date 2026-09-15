# Backup progress + redeploy on Railway

Use this when the Railway trial is ending, credits are low, or you need to recreate the project later.

There are **two** things to keep:

| What | Where it lives | Why |
|------|----------------|-----|
| **Code** | GitHub `main` | Dockerfile + app for the next deploy |
| **Data** | Local `.sql` backup (USB / Drive) | Students, attendance, parents, cameras — **not** in Git |

Do **not** commit the `.sql` file (student personal data).

---

## A. Before Railway dies — do this now

### 1) Save the code on GitHub

1. Merge open PRs into `main` (including parent nav / session reports if still open).
2. Confirm `main` has your latest commit on GitHub.

Recognition (`recognition-service/`) stays on the school PC — it is already in the repo but not deployed to Railway.

### 2) Backup the Railway MySQL database

1. Railway dashboard → **MySQL** service → **Settings → Networking**  
   Enable **Public Networking / TCP Proxy**.
2. Copy the **public host** and **port**.
3. MySQL → **Variables** → copy `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` (often `railway`).
4. On the **school PC** (PowerShell):

```powershell
cd C:\xampp\htdocs\attendance_system
git pull origin main

.\scripts\backup-railway-mysql.ps1
```

Paste host / port / user / password / database when asked.

5. Find the file:

`C:\xampp\htdocs\attendance_system\backups\railway_attendance_YYYYMMDD_HHMMSS.sql`

6. Copy that file to:
   - a USB stick, and/or  
   - Google Drive / OneDrive  

Keep at least **two** copies.

### 3) Optional — also dump into local XAMPP

```powershell
$env:RESTORE_LOCAL="1"
$env:BACKUP_SQL="C:\xampp\htdocs\attendance_system\backups\railway_attendance_YYYYMMDD_HHMMSS.sql"
$env:LOCAL_MYSQL_DATABASE="attendance_system"
.\scripts\restore-railway-mysql.ps1
```

(Create the local DB first in phpMyAdmin if needed.)

### 4) Screenshot / note Railway env vars

From the **web** service Variables, write down (or screenshot somewhere private):

- `APP_KEY`
- `APP_URL`
- `APP_TIMEZONE` (`Asia/Manila`)
- Any custom keys you set (`ATTENDANCE_*`, etc.)

You can generate a new `APP_KEY` on redeploy, but keeping the old one avoids invalidating encrypted data / sessions.

---

## B. Redeploy later on a new Railway plan

1. Railway → **New Project** → **Deploy from GitHub** → `Attendance_system` → branch **`main`**.
2. Add a **MySQL** plugin/service to the same project.
3. On the **web** service, set variables (same as `docs/DEPLOYMENT.md`):

| Variable | Value |
|----------|--------|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | *(saved key, or run `php artisan key:generate --show` once)* |
| `APP_TIMEZONE` | `Asia/Manila` |
| `APP_URL` | *(set after first deploy URL exists)* |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | `${{MySQL.MYSQLHOST}}` |
| `DB_PORT` | `${{MySQL.MYSQLPORT}}` |
| `DB_DATABASE` | `${{MySQL.MYSQLDATABASE}}` |
| `DB_USERNAME` | `${{MySQL.MYSQLUSER}}` |
| `DB_PASSWORD` | `${{MySQL.MYSQLPASSWORD}}` |
| `SESSION_DRIVER` | `database` |
| `SESSION_SECURE_COOKIE` | `true` |
| `QUEUE_CONNECTION` | `database` |
| `LOG_CHANNEL` | `stderr` |

Do **not** set `ASSET_URL`.

4. Deploy once so migrations create empty tables.
5. Enable MySQL **Public Networking** again.
6. Restore your backup:

```powershell
cd C:\xampp\htdocs\attendance_system
$env:RAILWAY_MYSQL_HOST="...."
$env:RAILWAY_MYSQL_PORT="...."
$env:RAILWAY_MYSQL_USER="...."
$env:RAILWAY_MYSQL_PASSWORD="...."
$env:RAILWAY_MYSQL_DATABASE="railway"
$env:BACKUP_SQL="C:\path\to\railway_attendance_YYYYMMDD_HHMMSS.sql"
.\scripts\restore-railway-mysql.ps1
```

7. Set `APP_URL` to the new Railway URL → **Redeploy**.
8. On the school PC, update `recognition-service/.env`:

```env
API_BASE_URL=https://YOUR-NEW-RAILWAY-URL/api/v1
```

Keep the same `CAMERA_ID` / `DEVICE_KEY` that exist in the restored `cameras` table (or re-seed).

9. Run recognition again:

```powershell
cd C:\xampp\htdocs\attendance_system\recognition-service
.\run_recognition.ps1
```

---

## C. Quick checklist

- [ ] PR(s) merged → `main` on GitHub  
- [ ] Railway MySQL public networking on  
- [ ] `backup-railway-mysql.ps1` ran successfully  
- [ ] `.sql` copied to USB + cloud Drive  
- [ ] `APP_KEY` / important env vars noted privately  
- [ ] (Later) New Railway project from GitHub `main`  
- [ ] (Later) Restore `.sql` with `restore-railway-mysql.ps1`  
- [ ] (Later) Update `APP_URL` + recognition `API_BASE_URL`  

Scripts:

- [`scripts/backup-railway-mysql.ps1`](../scripts/backup-railway-mysql.ps1)  
- [`scripts/restore-railway-mysql.ps1`](../scripts/restore-railway-mysql.ps1)  
