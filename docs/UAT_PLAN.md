# User Acceptance Testing (UAT) Plan

**Project:** Attendance System with Facial Recognition — Bigaa Elementary School  
**Date:** July 7, 2026

---

## 1. Objectives

- Verify core workflows with representative users before defense
- Collect feedback for ISO 25010 evaluation
- Confirm production deployment stability on Railway

---

## 2. Participants

| Role | Count | Test account |
|------|------:|--------------|
| Administrator | 1 | `admin` / `Admin@123` |
| Teacher | 2 | `teacher01` / `Teacher@123` |
| Parent | 2 | `parent01` / `Parent@123` |
| IT Expert | 1–2 | Reviewer (no login required) |

> Change all passwords before real school deployment.

---

## 3. Test environment

- **Production:** `https://attendancesystem-production-c52b.up.railway.app`
- **Parent mobile:** `mobile/` Flutter app (APK or `flutter run`)
- **Recognition node (optional):** `recognition-service/` on school LAN

---

## 4. Test scenarios

### A. Authentication & RBAC
| # | Steps | Expected |
|---|-------|----------|
| A1 | Login as admin, teacher, parent | Each lands on correct dashboard |
| A2 | Teacher opens admin URL | 403 Forbidden |
| A3 | Invalid password 5× | Rate limit message |

### B. Attendance (Teacher)
| # | Steps | Expected |
|---|-------|----------|
| B1 | Open attendance session, mark present/late/absent | Records saved |
| B2 | Mark same student twice | Duplicate-safe update |
| B3 | Record time-out | `time_out` populated |
| B4 | Close session | Unmarked students → absent |

### C. Parent experience (Web + Mobile)
| # | Steps | Expected |
|---|-------|----------|
| C1 | View linked children | List matches `student_guardian` |
| C2 | Submit LRN enrollment request | Status = pending |
| C3 | Teacher approves request | Child appears on parent dashboard |
| C4 | Receive notification on attendance | Notification visible + mark read |
| C5 | Mobile app login + child attendance | Same data as web API |

### D. Admin & reporting
| # | Steps | Expected |
|---|-------|----------|
| D1 | CRUD student with biometric consent | Consent flag saved |
| D2 | Export attendance CSV/PDF | File downloads |
| D3 | View Audit Logs | Login/attendance/enrollment entries visible |

### E. Security & privacy
| # | Steps | Expected |
|---|-------|----------|
| E1 | Recognition API without consent | `NO_BIOMETRIC_CONSENT` |
| E2 | Revoke consent on student | Face data purged |
| E3 | `biometric:purge-stale --dry-run` | Reports eligible rows only |

### F. Facial recognition (optional demo)
| # | Steps | Expected |
|---|-------|----------|
| F1 | Enroll student in `recognition-service` | Dataset created |
| F2 | Recognize at camera | Attendance ingested via API |

---

## 5. Pass/fail criteria

- **Pass:** ≥ 90% of critical scenarios (A, B, C, D) succeed without blocking defects
- **Conditional pass:** Minor UI issues documented with workaround
- **Fail:** Data loss, security bypass, or attendance not recorded

---

## 6. UAT sign-off template

| Tester | Role | Date | Pass/Fail | Notes |
|--------|------|------|-----------|-------|
| | Teacher | | | |
| | Parent | | | |
| | Admin | | | |
| | IT Expert | | | |

---

## 7. Post-UAT actions

1. Log defects in GitHub Issues
2. Update `docs/PROGRESS_REPORT.md` with UAT results
3. Complete ISO 25010 questionnaires (`docs/ISO25010_EVALUATION.md`)
4. Prepare defense demo script (15-minute flow)
