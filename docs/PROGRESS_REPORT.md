# CAPSTONE PROJECT PROGRESS REPORT

**Cross-Platform Student Attendance Management System with Facial Recognition, Parent Notification, and Analytics for Grade 6 Pupils of Bigaa Elementary School**

Pamantasan ng Cabuyao — College of Computing Studies

**Report Date:** July 7, 2026  
**Development Methodology:** Agile (iterative sprints)  
**Overall Development Progress:** approximately **78%**

---

## II. Progress Relative to the Specific Objectives

**Objective 1 — Centralized attendance system with facial recognition, RBAC, monitoring, reporting, and parent notifications.**  
*Status: **Substantially achieved.*** Role-based web system, facial-recognition attendance pipeline, analytics/reporting, FCM parent notifications, and enrollment verification workflow are implemented.

**Objective 2 — Cross-platform system (teacher recognition app, parent mobile, web dashboards).**  
*Status: **Partially achieved → advancing.*** Admin and teacher web dashboards are complete; Python recognition service is functional; **Flutter parent app scaffold** (`mobile/`) consumes the REST API for login, attendance viewing, and notifications.

**Objective 3 — Automated attendance tracking (time-in/out, status, absenteeism, reports, real-time parent notifications).**  
*Status: **Substantially achieved.*** Time-in and **time-out** recording, status classification, duplicate prevention, report exports, and parent notifications are implemented.

**Objective 4 — Secure management of attendance and facial data (RA 10173).**  
*Status: **Substantially achieved.*** Audit logging, security headers, biometric consent enforcement, retention purge, **encrypted embeddings at rest**, and compliance checklist documented.

**Objective 5 — ISO 25010 evaluation by teachers, parents, and IT experts.**  
*Status: **Prepared, not yet conducted.*** UAT plan and ISO 25010 evaluation instrument are drafted (`docs/UAT_PLAN.md`, `docs/ISO25010_EVALUATION.md`).

---

## IV. Modules Completed to Date

1. Project setup, database schema, authentication & RBAC  
2. Admin CRUD (students, teachers, guardians, sections, schedules)  
3. Attendance management with auto sessions, time-out, teacher UI  
4. REST API (Sanctum) + device-authenticated recognition ingest  
5. Python LBPH recognition service (prototype)  
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

The project has a functional, deployed system with web dashboards, API, facial-recognition prototype, parent notifications, security controls, and a mobile parent client scaffold. Approximately **78%** of development is complete. Remaining work focuses on recognition upgrades, mobile polish, formal evaluation, and documentation — building on the established foundation without requiring rework.
