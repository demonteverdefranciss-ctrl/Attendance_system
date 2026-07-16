# Developer Handoff / AI Agent Brief

> Paste this to your AI assistant (Cursor) to continue building the project.
> Read `docs/PHASES.md`, `docs/DEPLOYMENT.md`, and `docs/PROGRESS_REPORT.md` for detail.

## Project

BSIT capstone: **Cross-Platform Student Attendance Management System with Facial
Recognition, Parent Notification, and Analytics for Grade 6 Pupils of Bigaa
Elementary School** (Pamantasan ng Cabuyao). Roles: **Administrator, Teacher,
Parent**. Repo: `demonteverdefranciss-ctrl/Attendance_system` (branch `main`).

## Stack (locked)

- **Backend:** Laravel 12 (PHP 8.2), MVC.
- **Web frontend:** Inertia.js + React + Vite + **Tailwind CSS** (NOT Bootstrap). Ziggy for `route()`.
- **API:** Laravel Sanctum tokens (`/api/v1`) for the mobile app + a device-key middleware for the recognition node.
- **DB:** MySQL 8 (`attendance_system`).
- **Recognition:** separate Python service in `recognition-service/` (OpenCV LBPH).
- **Mobile:** Flutter parent app in `mobile/` (REST-only via Sanctum).
- **Deploy:** Docker → Railway (live). Container serves via `php artisan serve` (Apache was dropped due to MPM issues).

## Local environment (Windows + XAMPP) — tools are NOT on PATH

- PHP: `C:\xampp\php\php.exe` (8.2.12)
- Composer: `C:\xampp\php\composer.phar` → run as `php composer.phar ...`
- MySQL CLI: `C:\xampp\mysql\bin\mysql.exe` (user `root`, no password)
- Node/npm: `C:\Program Files\nodejs\` (v24). **Prepend to PATH before `npm` or esbuild build scripts fail:** `$env:PATH = "C:\Program Files\nodejs;$env:PATH"`
- Python (recognition): `recognition-service\.venv\Scripts\python.exe`
- App URL (local): `http://localhost/attendance_system/public/`
- After ANY React/JS change: `npm run build` (or `npm run dev` for HMR)
- Auto attendance sessions require the scheduler: `php artisan schedule:work`

## Cloud / Linux agent environment (Cursor)

- PHP: 8.3.x (`php` on PATH), Composer 2.x
- Node: 22.x / npm 10+
- MySQL 8: database `attendance_system`, user `attendance` / `attendance` (local agent only)
- App URL: `http://127.0.0.1:8000` via `php artisan serve` — leave `ASSET_URL` unset
- Setup: `composer install` → copy `.env` → `php artisan key:generate` → `php artisan migrate --seed` → `npm install` → `npm run build`
- Stack versions (locked via `composer.lock` / `package-lock.json`): Laravel **12.64+**, Inertia React **3.6+**, Vite **7.3+**, Tailwind **4.3+**, React **19.2+**

## What's DONE (Phases 0–9 partial, 10 scaffold)

0. Laravel + Inertia/React + Vite/Tailwind + Ziggy setup.
1. DB: 15+ domain tables via migrations + `DatabaseSeeder`.
2. Auth + RBAC: session login (username), `RoleMiddleware`, role dashboards.
3. Admin CRUD: teachers, guardians, sections, students, schedules.
4. Attendance: `AttendanceService`, auto sessions, teacher marking, **time-out recording**.
5. REST API (Sanctum): auth, students, attendance (device ingest + idempotency), notifications, analytics, **parent mobile endpoints**.
6a. Python `recognition-service/` (LBPH): enroll/train/recognize pipeline verified.
    - **Tapo C220 IP camera** integrated via RTSP (`VIDEO_SOURCE` in `recognition-service/.env`).
    - **Session-gated capture:** `recognize.py` polls `GET /api/v1/attendance/sessions/open`
      (device-authenticated) and only runs the camera while a session is open
      (`SESSION_POLL_SECONDS`, 0 = always on).
    - **Web camera preview:** `stream_server.py` serves MJPEG on `STREAM_PORT` (one shared
      RTSP connection with recognition); Laravel proxies it at `/camera/stream`
      (`CAMERA_STREAM_URL` in `.env`, local site only) and shows it on the teacher Mark page.
    - **Live updates:** teacher Mark page auto-refreshes records every 5 s while a session is open.
    - **Parent photo enrollment:** parents upload 1–3 face photos + RA 10173 consent;
      teachers approve under **Biometric Photos**; school PC runs `python sync_enrollment.py`.
7. Parent Notifications (FCM): `NotificationService`, queued jobs, parent dashboard controls.
8. Analytics + reports: Chart.js dashboards, CSV + PDF exports.
9. Security (partial): audit logs + admin viewer, security headers, biometric consent/purge/encryption, RA 10173 checklist.
10. Flutter parent app scaffold in `mobile/` (login, children, attendance, notifications, enrollment).

## Test logins (seeded — CHANGE IN PROD)

| Role | Username | Password | CRUD / access |
|------|----------|----------|----------------|
| Admin | `crud_admin` | `Crud@123` | Full CRUD: teachers, students, parents, sections, schedules |
| Teacher | `crud_teacher` | `Crud@123` | Attendance + enrollment request approval |
| Parent | `crud_parent` | `Crud@123` | View children, notifications, enrollment requests |

Legacy accounts: `admin/Admin@123`, `teacher01/Teacher@123`, `parent01/Parent@123`.

Refresh demo accounts anytime: `php artisan accounts:seed-demo`

> Students are records only (no login). Manage them under **Admin → Students**.

## Deployment state (Railway)

- Live at `https://attendancesystem-production-c52b.up.railway.app` (set `APP_URL` to this in Railway Variables).
- MySQL service linked via `${{MySQL.MYSQLHOST}}` etc. Migrations run on boot.
- Run `php artisan db:seed --force` once in the Railway Console to create the admin.
- `Dockerfile` + `docker/entrypoint.sh` serve via `php artisan serve` on `$PORT`.

## Conventions / gotchas

- **Always use Ziggy `route('name')`** in React — never hardcode paths like `/login`.
- Local subdirectory needs `ASSET_URL=/attendance_system/public`; production leaves it unset.
- Qualify `attendance_records.status` in SQL joins (sessions also have `status`).
- Recognition ingest is idempotent via `client_uuid`.
- The Tapo camera allows only a couple of RTSP clients — never open a second
  `VideoCapture` while `recognize.py` runs (the MJPEG preview reuses its frames).
- Live camera preview in the browser works only on the local site (camera is LAN-only).
- Flutter API base URL: see `mobile/README.md`.

## REMAINING WORK

1. **Phase 6b** — ArcFace + liveness + offline buffer (recognition upgrade).
2. **Phase 10** — Complete Flutter app (FCM device token, polish, store build).
3. **Phase 11** — Offline sync drill on recognition node.
4. **Phase 12** — UAT (`docs/UAT_PLAN.md`), ISO 25010 evaluation (`docs/ISO25010_EVALUATION.md`), defense docs.
5. **Analytics enhancements (noted 2026-07-16)** — build on existing summary / trend / per-section / CSV+PDF:
   - **Next 3 (priority):** (a) at-risk students table (rate &lt; 80%), (b) per-student attendance page with trend chart, (c) face vs manual method chart on reports.
   - **Also useful:** chronic absenteeism list, late-arrivals ranking, perfect attendance, monthly/quarterly comparison, time-in heatmap, average stay duration.
   - **Recognition-related:** method breakdown, recognition success vs unknown/failed posts, biometric enrollment coverage.
   - **Parent/mobile:** weekly child summary, attendance streaks, missed-day alert history.

Start by reading the codebase and `docs/PHASES.md`, confirm the plan for the chosen task, then implement one module and verify it (the owner tests via the browser + HTTP).
