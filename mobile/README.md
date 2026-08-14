# Bigaa ES Attendance App (Flutter)

Cross-platform mobile app for parents and teachers. Consumes `/api/v1` only (Sanctum bearer tokens).

## Features

### Parent
- Login (`parent01` / `Parent@123` in seeded data)
- Dashboard: children count, unread notifications, push preference toggle
- Child attendance history + summary analytics
- Notifications list + mark as read (opens explanation letters for streak alerts)
- Child enrollment request (LRN + name + optional details; teacher verification)
- Explanation letters after 3 consecutive absent/late days
- Face photo upload for biometric enrollment (teacher review)

### Teacher
- Login (`teacher01` / `Teacher@123` in seeded data)
- Dashboard: sections, students, attendance rate, pending tasks
- Attendance: open today's session, mark present/late/absent/excused, record time-out, close session
- Enrollment requests: approve or reject parent LRN links
- Explanation letters: accept (excuse records) or reject with optional notes
- Biometric photos: review and approve/reject parent submissions
- Student list with attendance detail view

## Android device not detected?

**You need the Android SDK.** See **`ANDROID_SETUP.md`** for the full step-by-step guide.

Quick check:
```powershell
flutter doctor
```
If it says `Unable to locate Android SDK`, install Android Studio first.

```powershell
.\setup_android.ps1
```

## Setup

```powershell
cd mobile
flutter pub get
```

## Run on phone (USB)

1. Enable USB debugging on your Android phone (see `ANDROID_SETUP.md`)
2. Connect via USB
3. Run:

```powershell
flutter run
```

Production API (Railway) is the default.

Local Laravel API:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2/attendance_system/public/api/v1
```
>Use your PC's LAN IP instead of `10.0.2.2` on a physical device, e.g. `http://192.168.1.5/attendance_system/public/api/v1`

## Build APK (Android)

```powershell
flutter build apk --release
```

Output: `build/app/outputs/flutter-apk/app-release.apk`

Copy to phone and install (enable install from unknown sources).

## API endpoints used

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/auth/login` | Login |
| POST | `/auth/logout` | Logout |
| GET | `/parent/dashboard` | Stats |
| GET | `/students` | Linked children |
| GET | `/students/{id}/attendance` | Attendance records |
| GET | `/analytics/student/{id}` | Summary counts |
| GET | `/notifications` | Notification list |
| POST | `/notifications/{id}/read` | Mark read |
| GET | `/parent/enrollment-requests` | Request status |
| POST | `/parent/enrollment-requests` | Submit enrollment (LRN + names) |
| POST | `/parent/notification-preference` | Toggle push / none |
| GET | `/parent/excuse-requests` | Explanation letter list |
| POST | `/parent/excuse-requests/{id}` | Submit letter body |
| GET | `/parent/children` | Children + biometric submission status |
| POST | `/parent/biometric-photos` | Multipart face photo upload |
| GET | `/teacher/excuse-requests` | Pending letters to review |
| POST | `/teacher/excuse-requests/{id}/approve` | Accept (excuse records) |
| POST | `/teacher/excuse-requests/{id}/reject` | Reject letter |

## Push notifications (optional)

Backend supports `POST /auth/device-token`. To enable FCM push on the phone:

1. Create a Firebase project at https://console.firebase.google.com
2. Add Android app with package `edu.pnc.attendance.attendance_parent`
3. Download `google-services.json` → `android/app/google-services.json`
4. Add `FCM_SERVER_KEY` to Railway env vars

In-app notifications (pull to refresh) work without Firebase.
