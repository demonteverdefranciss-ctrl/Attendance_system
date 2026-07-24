# Development Roadmap — Phase Descriptions

**Project:** Cross-Platform Student Attendance Management System with Facial Recognition, Parent Notification, and Attendance Analytics for Grade 6 Pupils of Bigaa Elementary School.

**Stack:** Laravel 12 · Inertia.js + React · MySQL 8 · Sanctum API · Python recognition · Flutter mobile.

**Legend:** ✅ Done · 🔜 Planned · ⏳ In progress

| Phase | Title | Status |
|------:|-------|:------:|
| 0 | Project Setup | ✅ |
| 1 | Database & Migrations | ✅ |
| 2 | Authentication & RBAC | ✅ |
| 3 | Core CRUD (Admin) | ✅ |
| 4 | Attendance (Sessions & Marking) | ✅ |
| 5 | REST API (Sanctum) | ✅ |
| 6a | Facial Recognition — LBPH demo | ✅ |
| 6b | Facial Recognition — ArcFace + Liveness | 🔜 |
| 7 | Parent Notifications (FCM) | ✅ |
| 8 | Analytics, Reports & Dashboards | ✅ |
| 9 | Audit Logs & Security Hardening | ⏳ |
| 10 | Flutter Mobile App | ⏳ |
| 11 | Deployment & Offline Sync | ⏳ |
| 12 | UAT, Documentation & Defense | 🔜 |

---

## Phase 6a — Facial Recognition (LBPH) ✅ — camera integration update (2026-07-08)

**Delivered:**
- Tapo C220 IP camera connected over RTSP; enroll/train/recognize verified end to end against production (Railway)
- **Session-gated camera:** recognition node polls a device-authenticated endpoint
  (`GET /api/v1/attendance/sessions/open`) and runs the camera only while a
  teacher has an open attendance session (privacy: biometric processing limited
  to the attendance window)
- **Browser camera preview:** MJPEG stream server + Laravel `/camera/stream` proxy,
  embedded on the teacher Mark Attendance page (local site)
- **Realtime dashboard:** Mark Attendance page auto-refreshes every 5 s while a
  session is open, so camera recognitions appear without manual reload

---

## Phase 9 — Audit Logs & Security Hardening ⏳

**Delivered:**
- Centralized `AuditService` + admin audit log viewer
- Security headers middleware + production session cookie defaults
- Biometric consent enforcement on recognition API
- `biometric:purge-stale` retention command (scheduled weekly)
- `EncryptedEmbedding` cast + `biometric:encrypt-embeddings` command
- `docs/RA10173_COMPLIANCE.md`

**Remaining:** recognition-node dataset purge automation, formal PIA document.

---

## Phase 10 — Flutter Mobile App ⏳

**Delivered (scaffold):**
- `mobile/` Flutter project — parent login, dashboard, child attendance, notifications, enrollment requests
- API parent endpoints: `/api/v1/parent/*`

**Remaining:** FCM device token registration, UI polish, release APK testing, optional teacher views.

---

## Phase 12 — UAT, Documentation & Defense 🔜

**Prepared:**
- `docs/UAT_PLAN.md` — test scenarios and sign-off template
- `docs/ISO25010_EVALUATION.md` — evaluation instrument for Objective 5

**Remaining:** conduct UAT, tabulate results, finalize capstone manuscript chapters.

---

## Planned analytics enhancements (noted 2026-07-16)

Existing: summary counts, daily trend, per-section rates, student API summary, CSV/PDF reports.

**Priority next:**
1. ~~At-risk students table (attendance rate &lt; 80%)~~ ✅
2. ~~Per-student attendance page with trend chart~~ ✅ (`reports/students/{id}`)
3. ~~Face vs manual marking method chart on reports~~ ✅

**Backlog:** chronic absenteeism, late ranking, perfect attendance, monthly comparison, time-in heatmap, stay duration, recognition reliability metrics, biometric coverage, parent weekly summary / streaks.

---

## Capstone adviser recommendations (noted 2026-07-24) — not implemented yet

Adviser asked for stronger chronic-attendance handling. Snapshot vs current system:

| Recommendation | Status now | Suggested later |
|----------------|------------|-----------------|
| Notify when student is often absent + possible penalty | Partial: per-day absent notifs + **3 consecutive absent/late** → explanation letter | Separate chronic-absent rules (e.g. monthly count / rate); **penalty as school-policy guidance + teacher-chosen action**, not auto-punish |
| If often late — possible actions | Partial: late notifs + same 3-streak letter flow | Separate late streak / ranking; teacher action list (warn → conference → guidance note) |
| Parent upload **proof** of absenteeism with **complete details** | Partial: text **explanation letter** + teacher accept/reject → excused | File upload (JPG/PDF), structured fields (reason type, dates, medical details), mirror biometric submit/review pattern |

**Already shipped (related):** `attendance_excuse_requests`, parent letter UI, teacher Explanation Letters approve/reject → mark `excused`, parent push/in-app alert on streak.

**Do not auto-apply penalties** in code for Grade 6 / DepEd context — keep as documented policy + optional teacher workflow.

---

## Session timeout idea (noted 2026-07-24) — implemented for ad-hoc

**Done:** Ad-hoc (manual Open) sessions auto-close after `ATTENDANCE_ADHOC_SESSION_MAX_MINUTES` (default **30**).
Schedule-based sessions still close at schedule `end_time`. Laravel `schedule:work` runs in the Docker entrypoint so Railway processes this every minute.

Camera on the school PC follows website open/close via `SESSION_POLL_SECONDS` + `run_recognition.ps1`.

---

_Last updated: 2026-07-24 — adviser recommendations + 30-min auto session close idea logged; implement only when owner requests._
