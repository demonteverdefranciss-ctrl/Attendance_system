# ISO/IEC 25010 Evaluation Plan

**Project:** Cross-Platform Student Attendance Management System  
**Standard:** ISO/IEC 25010 (System and Software Quality Models)  
**Respondents:** Teachers, Parents, IT Experts  
**Report Date:** July 7, 2026

---

## 1. Purpose

Evaluate the developed system against ISO 25010 quality characteristics to satisfy **Capstone Objective 5**.

---

## 2. Evaluation design

| Item | Detail |
|------|--------|
| Instrument | 5-point Likert scale (1 = Strongly Disagree, 5 = Strongly Agree) |
| Respondent groups | Teachers (n≥5), Parents (n≥5), IT Experts (n≥3) |
| Mode | Google Form or printed questionnaire during UAT |
| Analysis | Mean score per characteristic; compare across groups |

---

## 3. Quality characteristics and sample items

### Functional suitability
- The system correctly records student attendance (present, late, absent, excused).
- Time-in and time-out are recorded accurately.
- Parent notifications are delivered for attendance events.

### Performance efficiency
- The web dashboard loads within an acceptable time on school internet.
- Attendance marking responds quickly during class.

### Compatibility
- The parent mobile app works on Android devices used by guardians.
- The system runs on the deployed cloud URL without broken links.

### Usability
- Teachers can mark attendance without extensive training.
- Parents can view their child's attendance easily.
- Error messages (login failures, invalid LRN) are understandable.

### Reliability
- Duplicate attendance entries are prevented for the same session.
- The system remains available during normal school hours.

### Security
- Users only access data allowed by their role (admin/teacher/parent).
- Biometric consent is enforced before face recognition attendance.
- Sensitive actions are recorded in audit logs.

### Maintainability
- Modules (attendance, notifications, API) are organized and documented.
- Deployment steps are documented for Railway.

### Portability
- The system is accessible via web browser and parent mobile app.
- API supports mobile clients without direct database access.

---

## 4. Scoring template

| Characteristic | Teacher mean | Parent mean | IT Expert mean | Overall |
|----------------|-------------|-------------|--------------|---------|
| Functional suitability | | | | |
| Performance efficiency | | | | |
| Compatibility | | | | |
| Usability | | | | |
| Reliability | | | | |
| Security | | | | |
| Maintainability | | | | |
| Portability | | | | |

**Interpretation guide:** 4.0–5.0 = Excellent · 3.0–3.9 = Good · 2.0–2.9 = Fair · Below 2.0 = Needs improvement

---

## 5. Procedure

1. Conduct UAT sessions with teachers and parents using test accounts.
2. Distribute questionnaires after hands-on use (15–20 minutes per respondent).
3. IT experts review architecture, security checklist (`docs/RA10173_COMPLIANCE.md`), and API design.
4. Tabulate results in the capstone Chapter 4 / Results section.
5. Document limitations and recommended improvements.

---

## 6. Evidence to attach

- Screenshots of dashboards (admin, teacher, parent)
- Sample audit log entries
- Parent mobile app screens
- Railway deployment URL uptime during test week
- Notification delivery samples (FCM or in-app log)
