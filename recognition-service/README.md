# Recognition Service (Phase 6a — LBPH demo)

Python service that runs on the school PC: it watches the camera, recognizes
enrolled students with **OpenCV LBPH**, and posts attendance to the backend's
device-authenticated endpoint `POST /api/v1/attendance/recognitions`.

> This is the **Phase 6a demo**. Phase 6b upgrades recognition to ArcFace
> embeddings + liveness and adds an offline store-and-forward buffer.

## Pipeline

```
Camera (webcam / Tapo RTSP)
  -> detect faces (Haar cascade)
  -> recognize (LBPH)  -> confidence gate + N consecutive frames + cooldown
  -> POST /api/v1/attendance/recognitions  (X-Camera-Id + X-Device-Key)
  -> backend finds the open session, dedupes, records attendance
```

## Setup

```bash
cd recognition-service
python -m venv .venv
.venv\Scripts\activate            # Windows
pip install -r requirements.txt
copy .env.example .env            # then edit values
```

### Register a camera (device credentials)

The backend authenticates the node with a camera id + device key. Create one
(adjust the key), then put the id/key in `.env`:

```bash
php artisan tinker --execute="echo App\Models\Camera::create(['name'=>'Main Entrance','api_key_hash'=>Hash::make('demo-device-key-12345'),'is_active'=>true])->id;"
```

`migrate:fresh --seed` also creates a demo camera with key `demo-device-key-12345`.

## Usage

```bash
# 1) Enroll a student (use their backend student id). Webcam:
python enroll.py 1
#    ...or import from a folder of photos:
python enroll.py 1 --images C:\photos\ana

# 2) Train the model (re-run whenever you enroll someone new)
python train.py

# 3) Run live recognition (pushes attendance during open sessions)
python recognize.py

# No camera? Demo the pipeline by posting a recognition directly:
python test_api.py 1
```

## Configuration (`.env`)

| Key | Meaning |
|-----|---------|
| `API_BASE_URL` | Backend API base, e.g. `http://localhost/attendance_system/public/api/v1` |
| `CAMERA_ID` / `DEVICE_KEY` | Device credentials matching a `cameras` row |
| `VIDEO_SOURCE` | Webcam index (`0`) or Tapo RTSP URL |
| `LBPH_THRESHOLD` | Max LBPH distance to accept (lower = stricter, ~70 typical) |
| `MIN_CONSEC_FRAMES` | Consecutive confident frames required before recording |
| `COOLDOWN_SECONDS` | Per-student gap to avoid duplicate posts |
| `SAMPLES_PER_STUDENT` | Face samples captured during enrollment |
| `SHOW_WINDOW` | `1` to show a preview window, `0` for headless |

## Notes & limitations (LBPH)

- LBPH is sensitive to lighting/pose and must be **retrained** to add students.
- Recording only succeeds when an **attendance session is open** for the
  student's section (auto-opened by schedule, or opened by the teacher).
- Attendance is **deduplicated** by the backend (`unique(session, student)`),
  so re-recognitions are harmless.
- No anti-spoofing yet — that arrives in Phase 6b.
