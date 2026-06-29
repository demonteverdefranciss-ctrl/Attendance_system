# Development Roadmap — Phase Descriptions

**Project:** Cross-Platform Student Attendance Management System with Facial Recognition, Parent Notification, and Attendance Analytics for Grade 6 Pupils of Bigaa Elementary School.

**Stack:** Laravel 12 (PHP 8.2) · Inertia.js + React + Vite + Tailwind CSS · MySQL 8 · Sanctum (REST API) · Python recognition service · Flutter (mobile).

**Legend:** ✅ Done · 🔜 Planned · ⏳ In progress

| Phase | Title | Status |
|------:|-------|:------:|
| 0 | Project Setup | ✅ |
| 1 | Database & Migrations | ✅ |
| 2 | Authentication & RBAC | ✅ |
| 3 | Core CRUD (Admin) | ✅ |
| 4 | Attendance (Sessions & Marking) | ✅ |
| 5 | REST API (Sanctum) | 🔜 |
| 6a | Facial Recognition — LBPH demo | 🔜 |
| 6b | Facial Recognition — ArcFace + Liveness | 🔜 |
| 7 | Parent Notifications (FCM) | 🔜 |
| 8 | Analytics, Reports & Dashboards | 🔜 |
| 9 | Audit Logs & Security Hardening | 🔜 |
| 10 | Flutter Mobile App | 🔜 |
| 11 | Deployment & Offline Sync | 🔜 |
| 12 | UAT, Documentation & Defense | 🔜 |

---

## Phase 0 — Project Setup ✅

**Objective:** Stand up the Laravel + Inertia React foundation and connect to MySQL.

**Delivered:**
- Laravel 12 scaffolded; old raw-PHP app archived to `_legacy/` (DB backup at `_legacy/old_db_backup.sql`).
- Inertia.js + React + Vite + Tailwind wired (`resources/js/app.jsx`, `resources/views/app.blade.php`).
- `.env` pointed at MySQL `attendance_system`, timezone `Asia/Manila`.
- Ziggy installed for `route()` in React (subdirectory-safe URLs).

**Tooling installed:** Composer (`C:\xampp\php\composer.phar`), Node.js 24 LTS, `zip` PHP extension.

**Test:** App serves HTTP 200 at `http://localhost/attendance_system/public/`.

---

## Phase 1 — Database & Migrations ✅

**Objective:** Normalized schema for the whole system.

**Delivered (15 domain tables + Laravel defaults):** `roles`, `users` (extended: role_id, username, is_active), `teachers`, `guardians`, `students`, `student_guardian` (M:N), `sections`, `schedules`, `cameras`, `attendance_sessions`, `attendance_records` (unique `session_id+student_id`), `face_data`, `notifications`, `analytics_daily`, `audit_logs`.

**Seed data:** 3 roles, admin/teacher01/parent01 accounts, 1 section, 3 students, 1 guardian link.

**Test:** `php artisan migrate:fresh --seed`; all FKs, indexes, and the dedup constraint verified.

---

## Phase 2 — Authentication & RBAC ✅

**Objective:** Secure username/password login with role-based access (no public registration).

**Delivered:**
- Models `Role`, `User` (`hasRole()`); `LoginRequest` (rate-limit 5/attempt, `is_active` block).
- `AuthenticatedSessionController` (session regeneration on login), `RoleMiddleware` (`role:` alias).
- Role dispatcher → `admin` / `teacher` / `parent` dashboards.
- React: `Pages/Auth/Login.jsx`, `Layouts/AuthenticatedLayout.jsx`, role dashboards.

**Test:** valid/invalid login, role redirects, teacher→admin = 403, guest→login.

**Default logins (change before real use):** `admin/Admin@123`, `teacher01/Teacher@123`, `parent01/Parent@123`.

---

## Phase 3 — Core CRUD (Admin) ✅

**Objective:** Admin management of all master data.

**Delivered:**
- Eloquent models: `Teacher`, `Guardian`, `Section`, `Student`, `Schedule`.
- Resource controllers in `app/Http/Controllers/Admin/` (teachers/guardians also create a linked login account in a transaction; delete cascades).
- Students ↔ guardians many-to-many (first selected = primary contact).
- React: `AdminLayout` (sidebar), reusable `TextField`/`SelectField`/`DataTable`, Index + Form pages per resource.

**Test:** create/list/validate verified; RBAC intact.

> Note: lists use `->get()` (no pagination yet) — fine at Grade-6 scale.

---

## Phase 4 — Attendance (Sessions & Marking) ✅

**Objective:** Record attendance, auto-activate scheduled windows, prevent duplicates.

**Delivered:**
- Models `AttendanceSession`, `AttendanceRecord`.
- `AttendanceService`: `openSession`, `closeSession` (unmarked → absent), `mark` (duplicate-safe upsert), `statusForArrival` (late logic).
- Console command `attendance:manage-sessions` (auto-open/close), scheduled every minute.
- Teacher UI: `Teacher\AttendanceController` (section-ownership guard), `TeacherLayout`, `Attendance/Index` + `Attendance/Mark`.

**Test:** manual mark (present/late/absent/excused), duplicate-safe re-mark, close + absent-fill, auto-open via command, RBAC.

> Operational: auto-open requires the scheduler running — `php artisan schedule:work` (dev) or cron `schedule:run` (prod). Manual "Open Attendance" works regardless.

---

## Phase 5 — REST API (Sanctum) 🔜

**Objective:** One versioned API (`/api/v1`) for the Flutter app and the recognition node.

**Scope:**
- Sanctum bearer tokens (login/logout/me, register FCM device token).
- Device API-key auth (per-camera) for the recognition node.
- Endpoints: auth, students, attendance (incl. `POST /attendance/recognitions` ingest with `client_uuid` idempotency), sessions, analytics, notifications, reports.
- API Resources (JSON transformers), validation, rate limiting.

**Test:** Postman/HTTP — token auth, RBAC on API, idempotent recognition ingest.

**Dependencies:** Phases 2 & 4.

---

## Phase 6a — Facial Recognition: LBPH Demo 🔜

**Objective:** Fast working prototype matching the original pipeline.

**Scope (Python service `recognition-service/`):** enroll faces (Haar/MTCNN detect → LBPH train), recognize from Tapo RTSP, push to `POST /api/v1/attendance/recognitions`.

**Test:** recognizes enrolled students in a demo; attendance appears in the dashboard.

**Dependencies:** Phase 5.

---

## Phase 6b — Facial Recognition: ArcFace + Liveness 🔜

**Objective:** Production-grade recognition.

**Scope:** Embedding-based recognition (ArcFace/InsightFace), cosine-similarity matching + margin, anti-spoofing/liveness, temporal persistence (N frames), confidence thresholds, local SQLite buffer for offline store-and-forward.

**Test:** accuracy on a held-out set, anti-spoof check, internet-outage drill (buffer → sync).

**Dependencies:** Phase 6a.

---

## Phase 7 — Parent Notifications (FCM) 🔜

**Objective:** Notify guardians on arrival/absence.

**Scope:** `NotificationService` (channel-abstracted; FCM implemented, Email/SMS pluggable later), queued jobs + retry, logged in `notifications`, per-guardian preference + quiet hours.

**Test:** push received on attendance event; failures retried and logged.

**Dependencies:** Phases 4 & 5.

---

## Phase 8 — Analytics, Reports & Dashboards 🔜

**Objective:** Insight + exports.

**Scope:** `analytics_daily` aggregation job, Chart.js dashboards (per section/student), attendance reports (PDF/CSV), live teacher dashboard via API polling.

**Test:** aggregates match raw data; exports generate correctly.

**Dependencies:** Phases 4 & 5.

---

## Phase 9 — Audit Logs & Security Hardening 🔜

**Objective:** Traceability + a security pass.

**Scope:** audit logging (login, edits, attendance overrides) to `audit_logs`; security review — HTTPS, secure cookies, least-privilege DB user, secrets in env, rate limits, biometric data-at-rest + RA 10173 consent workflow.

**Test:** audit entries recorded; security checklist passed.

**Dependencies:** all prior phases.

---

## Phase 10 — Flutter Mobile App 🔜

**Objective:** Parent/teacher mobile access (REST-only — never touches MySQL directly).

**Scope:** login (Sanctum), attendance history, push notifications (FCM), teacher quick views.

**Test:** on-device + integration tests against `/api/v1`.

**Dependencies:** Phases 5 & 7.

---

## Phase 11 — Deployment & Offline Sync 🔜

**Objective:** Production architecture.

**Scope:** cloud VPS (Nginx + PHP-FPM + MySQL + queue worker), HTTPS, recognition node on school LAN pushing over HTTPS with a device key, store-and-forward sync, pull-the-plug outage drill.

**Test:** live end-to-end; attendance survives an internet outage and syncs on reconnect.

**Dependencies:** Phases 5, 6b, 7.

---

## Phase 12 — UAT, Documentation & Defense 🔜

**Objective:** Capstone-ready.

**Scope:** user acceptance testing with teachers/parents, user manual, ERD/architecture diagrams, capstone documentation, defense prep.

**Dependencies:** all prior phases.

---

_Last updated: 2026-06-29 — Phases 0–4 complete._
