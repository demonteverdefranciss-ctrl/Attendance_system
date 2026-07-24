# CAPSTONE PROJECT PROGRESS REPORT

**Cross-Platform Student Attendance Management System with Facial Recognition, Parent Notification, and Analytics for Grade 6 Pupils of Bigaa Elementary School**

Pamantasan ng Cabuyao — College of Computing Studies

**Report Date:** July 24, 2026  
**Development Methodology:** Agile (iterative sprints)  
**Overall Development Progress:** approximately **80%** (equal-weight average of Objectives 1–5)

---

## II. Progress Relative to the Specific Objectives

| Objective | Description | Status | Completion |
|----------:|-------------|--------|----------:|
| 1 | Centralized attendance system with facial recognition, RBAC, monitoring, reporting, and parent notifications | Substantially achieved | **95%** |
| 2 | Cross-platform system (teacher recognition app, parent mobile, web dashboards) | Substantially achieved | **90%** |
| 3 | Automated attendance tracking (time-in/out, status, absenteeism, reports, real-time parent notifications) | Substantially achieved | **93%** |
| 4 | Secure management of attendance and facial data (RA 10173) | Substantially achieved | **85%** |
| 5 | ISO 25010 evaluation by teachers, parents, and IT experts | Prepared, not yet conducted | **25%** |
| — | **Overall (equal weight)** | — | **80%** |

**Scoring notes:** Each objective is estimated from delivered features vs remaining gaps. Objective 5 remains low until UAT and ISO 25010 surveys are actually administered and tabulated.

**Objective 1 — Centralized attendance system with facial recognition, RBAC, monitoring, reporting, and parent notifications.**  
*Status: **Substantially achieved (95%).*** Role-based web system, facial-recognition attendance pipeline, analytics/reporting, FCM parent notifications, and enrollment verification workflow are implemented.

**Objective 2 — Cross-platform system (teacher recognition app, parent mobile, web dashboards).**  
*Status: **Substantially achieved (90%).*** Admin/teacher/parent web dashboards; Python recognition service with Tapo camera; Flutter app for parents and teachers (APK built). Remaining: in-app FCM push and optional recognition upgrades.

**Objective 3 — Automated attendance tracking (time-in/out, status, absenteeism, reports, real-time parent notifications).**  
*Status: **Substantially achieved (93%).*** Time-in/out, status classification, duplicate prevention, report exports, and parent notifications are implemented. Session-gated camera and near-realtime mark page refresh are in place.

**Objective 4 — Secure management of attendance and facial data (RA 10173).**  
*Status: **Substantially achieved (85%).*** Audit logging, security headers, biometric consent, retention purge, encrypted embeddings, and compliance checklist documented. Remaining: formal PIA document.

**Objective 5 — ISO 25010 evaluation by teachers, parents, and IT experts.**  
*Status: **Prepared, not yet conducted (25%).*** UAT plan and ISO 25010 evaluation instrument are drafted (`docs/UAT_PLAN.md`, `docs/ISO25010_EVALUATION.md`). Remaining: conduct evaluation and tabulate results.

---

## IV. Modules Completed to Date

1. Project setup, database schema, authentication & RBAC  
2. Admin CRUD (students, teachers, guardians, sections, schedules)  
3. Attendance management with auto sessions, time-out, teacher UI  
4. REST API (Sanctum) + device-authenticated recognition ingest  
5. Python LBPH recognition service with Tapo C220 IP camera (RTSP), session-gated capture, and browser camera preview  
6. Analytics dashboards + CSV/PDF reports  
7. Parent notifications (FCM) + parent dashboard  
8. Parent–child enrollment request workflow with teacher verification  
9. Audit logs + admin viewer + security hardening (partial)  
10. Flutter parent mobile app (initial release scaffold)

---

## VI. Remaining Work

1. Upgrade recognition to ArcFace + liveness + offline buffer (Phase 6b)  
2. Complete Flutter app (FCM push, release testing)  
3. Conduct UAT and ISO 25010 evaluation (Phase 12)  
4. Finalize capstone documentation and defense materials  

---

## VIII. Summary

The project has a functional system with web dashboards, API, facial-recognition prototype, parent notifications, security controls, and a Flutter parent/teacher mobile client. Approximately **80%** of development is complete under equal-weight scoring of the five specific objectives. The largest remaining gap is **Objective 5** (formal UAT / ISO 25010 evaluation), plus hosting stability and documentation for defense.
