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

_Last updated: 2026-07-08 — Phases 0–8 complete; 9–10 in progress; Tapo camera + session-gated recognition + realtime attendance live._
