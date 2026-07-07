# Parent Mobile App (Flutter)

Cross-platform parent app for the attendance system. Consumes `/api/v1` only (Sanctum bearer tokens).

## Features (Phase 10 scaffold)

- Parent login (`parent01` / `Parent@123` in seeded data)
- Dashboard: children count, unread notifications
- Child attendance history + summary analytics
- Notifications list + mark as read
- Child enrollment request by LRN (teacher verification workflow)

## Setup

```powershell
cd mobile
flutter pub get
```

## Run

Production API (Railway):

```powershell
flutter run
```

Local Laravel API:

```powershell
flutter run --dart-define=API_BASE_URL=http://localhost/attendance_system/public/api/v1
```

## Build APK (Android)

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://attendancesystem-production-c52b.up.railway.app/api/v1
```

Output: `mobile/build/app/outputs/flutter-apk/app-release.apk`

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

## Next steps

- FCM push notification registration (`POST /auth/device-token`)
- Teacher quick-view screens (optional)
- On-device integration tests against `/api/v1`
