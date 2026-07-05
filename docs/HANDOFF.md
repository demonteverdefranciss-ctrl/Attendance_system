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
- **Mobile (not built yet):** Flutter, REST-only.
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

## What's DONE (Phases 0–8)

0. Laravel + Inertia/React + Vite/Tailwind + Ziggy setup.
1. DB: 15 domain tables via migrations + `DatabaseSeeder` (roles, admin/teacher01/parent01, sample section/students/guardian, demo camera).
2. Auth + RBAC: session login (username), `RoleMiddleware` (`role:` alias), role dashboards, rate-limiting, `is_active` check.
3. Admin CRUD: teachers, guardians, sections, students (M:N guardians), schedules. Teachers/guardians auto-create linked user accounts.
4. Attendance: `AttendanceService` (open/close/mark, dup-safe), `attendance:manage-sessions` command (auto open/close), teacher marking UI.
5. REST API (Sanctum): auth, students (role-scoped), attendance incl. device-authenticated idempotent `POST /api/v1/attendance/recognitions`, notifications, analytics.
6a. Python `recognition-service/` (LBPH): enroll/train/recognize + `test_api.py` (camera-less demo). Verified end-to-end.
8. Analytics + reports: `AnalyticsService`, Chart.js dashboards (admin/teacher), `ReportController` with CSV + PDF (dompdf), Reports page.

## Test logins (seeded — CHANGE IN PROD)

`admin/Admin@123`, `teacher01/Teacher@123`, `parent01/Parent@123`. Recognition device key: `demo-device-key-12345`.

## Deployment state (Railway)

- Live at `https://attendancesystem-production-c52b.up.railway.app` (set `APP_URL` to this in Railway Variables).
- MySQL service linked via `${{MySQL.MYSQLHOST}}` etc. Migrations run on boot.
- Run `php artisan db:seed --force` once in the Railway Console to create the admin.
- `Dockerfile` + `docker/entrypoint.sh` serve via `php artisan serve` on `$PORT`.

## Conventions / gotchas (learned the hard way)

- **Always use Ziggy `route('name')`** in React for links AND form posts — never hardcode `/login` etc. (app runs in a subdirectory locally; hardcoded paths 404).
- Local subdirectory needs `ASSET_URL=/attendance_system/public` in `.env` (already set); production leaves it unset.
- When aggregating attendance, `attendance_sessions` ALSO has a `status` column — qualify `attendance_records.status` to avoid ambiguous-column SQL errors.
- Recognition/API attendance is deduped by `unique(session_id, student_id)` + `client_uuid` idempotency.
- Follow the owner's workflow: **build one module at a time, explain files, wait for confirmation before the next.** Keep it achievable for a BSIT capstone.

## REMAINING WORK (do these next)

Mapped to the 5 specific objectives (see PROGRESS_REPORT.md). Suggested order:

1. **Time-out recording** (finishes part of Objective 3): add time-out to attendance (schema already only tracks `time_in`; add `time_out` + UI + recognition support).
2. **Phase 7 — Parent Notifications (FCM)** (completes Objective 1, most of Objective 3): `NotificationService` (channel-abstracted, FCM first), queued jobs, trigger on attendance events (arrival/absent/late), log in `notifications` table, `/api/v1/auth/device-token` already exists for FCM tokens.
3. **Phase 9 — Audit logs + security hardening** (Objective 4): populate `audit_logs`, encrypt facial data at rest, consent/retention workflow (RA 10173).
4. **Phase 6b — ArcFace + liveness + offline buffer** (upgrades recognition accuracy/anti-spoofing).
5. **Phase 10 — Flutter parent/teacher app** (Objective 2): consumes `/api/v1` only.
6. **Phase 11/12 — production hardening, UAT, ISO 25010 evaluation** (Objective 5).

Start by reading the codebase and `docs/PHASES.md`, confirm the plan for the chosen task, then implement one module and verify it (the owner tests via the browser + HTTP).
