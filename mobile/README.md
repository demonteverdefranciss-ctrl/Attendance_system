# Parent Mobile App (Flutter)

Cross-platform parent app for the attendance system. Consumes `/api/v1` only (Sanctum bearer tokens).

## Features

- Parent login (`parent01` / `Parent@123` in seeded data)
- Dashboard: children count, unread notifications
- Child attendance history + summary analytics
- Notifications list + mark as read
- Child enrollment request by LRN (teacher verification workflow)

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
| POST | `/parent/enrollment-requests` | Submit LRN request |

## Push notifications (optional)

Backend supports `POST /auth/device-token`. To enable FCM push on the phone:

1. Create a Firebase project at https://console.firebase.google.com
2. Add Android app with package `edu.pnc.attendance.attendance_parent`
3. Download `google-services.json` → `android/app/google-services.json`
4. Add `FCM_SERVER_KEY` to Railway env vars

In-app notifications (pull to refresh) work without Firebase.
