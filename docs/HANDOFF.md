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

## What's DONE (Phases 0–9 partial, 10 scaffold)

0. Laravel + Inertia/React + Vite/Tailwind + Ziggy setup.
1. DB: 15+ domain tables via migrations + `DatabaseSeeder`.
2. Auth + RBAC: session login (username), `RoleMiddleware`, role dashboards.
3. Admin CRUD: teachers, guardians, sections, students, schedules.
4. Attendance: `AttendanceService`, auto sessions, teacher marking, **time-out recording**.
5. REST API (Sanctum): auth, students, attendance (device ingest + idempotency), notifications, analytics, **parent mobile endpoints**.
6a. Python `recognition-service/` (LBPH): enroll/train/recognize pipeline verified.
7. Parent Notifications (FCM): `NotificationService`, queued jobs, parent dashboard controls.
8. Analytics + reports: Chart.js dashboards, CSV + PDF exports.
9. Security (partial): audit logs + admin viewer, security headers, biometric consent/purge/encryption, RA 10173 checklist.
10. Flutter parent app scaffold in `mobile/` (login, children, attendance, notifications, enrollment).

## Test logins (seeded — CHANGE IN PROD)

`admin/Admin@123`, `teacher01/Teacher@123`, `parent01/Parent@123`. Recognition device key: `demo-device-key-12345`.

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
- Flutter API base URL: see `mobile/README.md`.

## REMAINING WORK

1. **Phase 6b** — ArcFace + liveness + offline buffer (recognition upgrade).
2. **Phase 10** — Complete Flutter app (FCM device token, polish, store build).
3. **Phase 11** — Offline sync drill on recognition node.
4. **Phase 12** — UAT (`docs/UAT_PLAN.md`), ISO 25010 evaluation (`docs/ISO25010_EVALUATION.md`), defense docs.

Start by reading the codebase and `docs/PHASES.md`, confirm the plan for the chosen task, then implement one module and verify it (the owner tests via the browser + HTTP).
