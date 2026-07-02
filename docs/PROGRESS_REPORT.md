# CAPSTONE PROJECT PROGRESS REPORT

**Cross-Platform Student Attendance Management System with Facial Recognition, Parent Notification, and Analytics for Grade 6 Pupils of Bigaa Elementary School**

Pamantasan ng Cabuyao — College of Computing Studies
City of Cabuyao, Laguna
Bachelor of Science in Information Technology

**Student Researchers:** Demonteverde, Francis A.; Dela Torre, Markanthony A.; Molinyawe, Russel A.; Sexiona, Reynalin P.

**Report Date:** July 2, 2026
**Development Methodology:** Agile (iterative sprints)
**Overall Development Progress:** approximately 54%

---

## I. Introduction

This report documents the current development status of the capstone project
following the approval of the Chapters 1–3 manuscript. Development is being carried
out using the Agile methodology described in Chapter III, with the system built on
a modern web stack (Laravel 12, Inertia.js with React, and MySQL) and a dedicated
Python facial-recognition service using OpenCV. The following sections report
progress against the study's specific objectives and against the six Agile
development phases.

---

## II. Progress Relative to the Specific Objectives

**Objective 1 — Design a centralized attendance management system supporting
facial-recognition-based recording, role-based access, monitoring, reporting, and
parent notifications.**
*Status: Substantially achieved.* A centralized system with role-based access for
Administrator, Teacher, and Parent has been implemented. Facial-recognition-based
attendance recording and attendance monitoring are functional. Reporting and
parent notifications are in progress (scheduled in the reporting and notification
sprints).

**Objective 2 — Develop a cross-platform system: a facial-recognition attendance
application for teachers, a mobile-accessible platform for parents, and web-based
dashboards for teachers and administrators.**
*Status: Partially achieved.* Web-based dashboards for teachers and administrators
are complete, and the facial-recognition attendance application (Python service)
is functional as a prototype. The parent mobile application (Flutter) is scheduled
for a later sprint; the REST API it will consume is already implemented.

**Objective 3 — Implement automated attendance tracking (time-in/time-out
recording, attendance status classification, absenteeism monitoring, report
generation, and real-time parent notifications).**
*Status: Partially achieved.* Time-in recording, attendance status classification
(present, late, absent, excused), and absenteeism monitoring are implemented, with
automatic late detection and duplicate prevention. Time-out recording, report
generation, and real-time notifications are in progress.

**Objective 4 — Ensure secure management of student attendance records and facial
data in compliance with the Data Privacy Act of 2012 (RA 10173).**
*Status: In progress.* Security measures implemented include hashed passwords,
role-based authorization, prepared statements, session protection, login
rate-limiting, and a per-student biometric-consent field. Comprehensive audit
logging and the full security-hardening pass are scheduled in a dedicated sprint.

**Objective 5 — Evaluate the system using ISO 25010 quality standards through
assessments by teachers, parents, and IT experts.**
*Status: Not yet started.* System evaluation will be conducted after the core
features are finalized, during the user-acceptance-testing stage.

---

## III. Progress by Agile Development Phase

| Phase | Description | Status |
|-------|-------------|--------|
| Requirements Gathering & Analysis | Stakeholder needs, existing manual process (School Forms 2 & 4) | Completed |
| Design | System architecture, database (ERD), UI, and process flows | Completed |
| Development | Iterative implementation of system modules | In progress (~54%) |
| Testing | Functional verification per module; ISO 25010 evaluation pending | Partial |
| Review | Continuous review with stakeholder feedback | Ongoing |
| Deployment | Cloud hosting and on-site recognition node | In progress |

---

## IV. Modules Completed to Date

1. **Project Setup & Environment** — Laravel 12 with Inertia.js + React + Vite;
   MySQL database; version control (GitHub).
2. **Database Design** — Normalized schema of 15 tables (users, roles, teachers,
   guardians, students, sections, schedules, attendance sessions and records,
   face data, notifications, analytics, audit logs, cameras) with integrity
   constraints and duplicate-attendance prevention.
3. **User Authentication & Role-Based Access Control** — Secure login for the three
   user roles with access restrictions and account safeguards.
4. **Records Management (Administrator)** — Full management of students, teachers,
   parents/guardians, sections, and class schedules.
5. **Attendance Management** — Attendance sessions with automatic activation based
   on class schedules, teacher marking, status classification, and late detection.
6. **REST Application Programming Interface (API)** — A versioned, secured API
   serving the mobile application and the facial-recognition node, including a
   device-authenticated attendance-ingest endpoint.
7. **Facial Recognition Service (Prototype)** — A Python/OpenCV (LBPH) service that
   enrolls faces, recognizes pupils, and records attendance through the API; the
   full recognition-to-record pipeline has been verified.

---

## V. Testing Conducted

Each completed module was verified through functional testing during development,
covering authentication and access control, records management with input
validation, attendance recording (including duplicate handling and automatic
session activation), API authentication and data-access restrictions, and the
end-to-end facial-recognition attendance pipeline. Formal evaluation using ISO
25010 quality standards, together with respondent-based assessment, will be
conducted in a later stage as specified in the study.

---

## VI. Remaining Work

1. Upgrade the facial-recognition module to an embedding-based model (ArcFace) with
   anti-spoofing (liveness detection) and an offline synchronization buffer.
2. Implement real-time parent notifications.
3. Implement attendance analytics, report generation, and dashboard visualizations.
4. Complete audit logging and the full security-hardening review.
5. Develop the parent mobile application (Flutter).
6. Complete production deployment and offline synchronization.
7. Conduct user acceptance testing and ISO 25010 evaluation, and finalize
   documentation.

---

## VII. Risks and Considerations

1. **Recognition accuracy.** The current LBPH model serves as a working prototype;
   improved classroom accuracy and anti-spoofing will be addressed through the
   planned model upgrade.
2. **Data privacy.** As the system processes minors' biometric data, parental
   consent and compliance with RA 10173 are incorporated into the design and must
   be observed during deployment.
3. **Connectivity.** An offline store-and-forward strategy is planned so that
   attendance is not lost during internet interruptions at the school site.

---

## VIII. Summary

The project has completed its requirements, design, and core development work,
resulting in a functional, secure web-based system with role-based access, complete
records and attendance management, a working REST API, and a verified
facial-recognition attendance pipeline. Approximately 54% of total development is
complete. The remaining work — advanced recognition, notifications, analytics and
reporting, the mobile application, deployment, and formal evaluation — builds upon
the established foundation without requiring rework.
