# Offline attendance

The school PC saves every authorized recognition in SQLite **before** uploading.
The Tapo camera still needs power and a working local network connection to the
PC. Internet and a camera SD card are not required for the local live stream.
The PC must stay running for recognition; events already saved survive restarts.

## Deployment and startup

1. Deploy the Laravel changes first. Run `php artisan migrate --force` on the
   backend (the existing Railway entrypoint already runs migrations on startup).
   The new `recognition_events` table stores permanent event receipts.
2. Update the recognition service on the school PC. Its existing Python
   environment already includes the dependencies; SQLite is built into Python.
3. Set `EVENT_TYPE_HINT=in` for an entrance camera, or `out` for an exit camera.
   Empty defaults to **in** in the updated recognition loop. Repeated entrance
   scans no longer become departures. For a single camera, teachers can use the
   existing manual time-out action; closing the session still sets automatic
   time-outs. Do not change camera roles while events are pending.
4. Start `python recognize.py` and open a session with internet available once.
   The device caches the session ID, permitted students, and expiry. Subsequent
   offline restarts can use that cache only while it remains valid.

## Behavior during an outage

- Saved events retain their UUID, session, capture time, and direction across retries.
- One background worker attempts synchronization immediately, then every 15 seconds.
- Network/server/authentication failures remain pending. Invalid requests are
  retained as rejected for review. The API must acknowledge success before a row
  becomes synced. Retry order preserves arrivals before departures on one device.
- The preview displays pending/rejected counts, including while waiting for a session.
- Successful session checks replace the cache. Closed sessions and rejected device
  credentials clear authorization. Offline authorization expires at the earlier
  schedule end or session duration (six hours if no limit is configured).
- `O` can turn on the camera preview but cannot authorize an unknown/expired
  student session. `C` releases the camera; the upload worker can keep working.
- The cloud website cannot report a live offline queue while it cannot reach the
  school PC. Check the local preview or the commands below.

## Inspect and synchronize without the camera

From `recognition-service`:

```powershell
python check_connection.py
python sync_attendance.py --status
python sync_attendance.py --once
python sync_attendance.py
```

`check_connection.py` checks the configured backend and session roster without
sending attendance. The website you open and `API_BASE_URL` must refer to the same
server. If the server cannot be reached before any session has been cached,
attendance cannot start offline. Restore access, open the section's session, and
restart recognition. The `O` key enables preview only; it does not authorize a
session. If the diagnostic reports an older backend, deploy the offline update
and its migration to the branch Railway actually uses.

The last command keeps retrying until Ctrl+C, without loading models or video.
Status lists counts and up to 20 pending/rejected event IDs and error codes.
Fix connection/device credentials for pending failures. Rejected events require
teacher review; they are not retried automatically or silently converted to attendance.

## Server safeguards

- Device authentication, camera/section coverage, active students and consent
  remain required. Events older than seven days or over two minutes in the future
  are rejected. The PC clock must be correct.
- Captures must fall within the original session, including its actual close time.
  An early teacher closure unknown to an offline PC can therefore cause a rejection.
- Stable UUID receipts prevent retries from generating a departure or a second
  notification. Session locks coordinate uploads and automatic session closure.
- Teacher-reviewed attendance is preserved. A delayed arrival can correct an
  automatic absence; earlier absence notifications cannot be recalled. Teachers
  should review any explanation-letter requests already generated during the outage.
- Delayed arrival messages include the original date/time and explain the delay.
  Push delivery still depends on the project's existing notification configuration.
- Exit events need an earlier accepted arrival. Across separate offline devices,
  synchronize entrance events first; out-of-order departures may need review.

## Storage

Local data lives in `offline-data/attendance.sqlite3` and is excluded from Git.
It contains student IDs and session rosters, not video or face snapshots. Protect
the school PC account and backups. Synced local rows are removed after 30 days;
pending/rejected rows are retained. Server event receipts are retained for replay
protection. Do not delete the queue or change the backend URL/camera ID with a
backlog: each queue is scoped to that destination and camera.

## Verification

```powershell
python -m unittest discover -s . -p test_offline_queue.py -v
```

From the project root:

```powershell
php artisan test --filter=OfflineRecognitionTest
```

For a hardware check, use a test student/session: disconnect only the router's
internet connection, confirm the local preview still works and the pending count
increases, then restore internet and verify the original time and one notification.
Repeat after restarting the recognition process. Do not power off the router.
