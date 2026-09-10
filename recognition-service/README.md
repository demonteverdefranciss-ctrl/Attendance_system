# Recognition Service

Python service that runs on the school PC: it watches the camera, recognizes
enrolled students, and posts attendance to `POST /api/v1/attendance/recognitions`.

**Engine switch** (in `.env`):

| `RECOGNITION_ENGINE` | Matcher |
|---|---|
| `lbph` (default) | OpenCV LBPH — current live setup |
| `arcface` | OpenCV SFace (ArcFace-trained) + YuNet detector |

LBPH stays the default. To switch, set `RECOGNITION_ENGINE=arcface`, run
`python train.py` once (downloads ONNX models + builds a gallery from existing
enrollment photos), then `python recognize.py`. Set it back to `lbph` to return
to the old matcher. Do not run both at once.

## Pipeline

A detected face is not attendance. The live loop is:

```
1. Face Detection     — YuNet (ArcFace) or Haar (LBPH)
2. Face Validation    — size, blur, brightness, coarse frontal pose
3. Face Matching      — 128-D descriptor vs enrolled gallery
4. Identity Validation — threshold + lookalike margin + N consecutive frames
5. Attendance Validation — open session, consent, active student,
                           assigned camera, duplicate cooldown
6. Record             — Present / Late / time-out
```

Invalid faces never go to matching. Below-threshold, lookalike, or duplicate
scans are not recorded. The camera overlay shows which stage passed or failed.

```
Camera (webcam / Tapo RTSP)
  -> DETECT → VALIDATE → MATCH → IDENTITY
  -> POST /api/v1/attendance/recognitions  (X-Camera-Id + X-Device-Key)
  -> backend attendance checks → record
```

## Algorithm (how a face becomes a match)

The system does not decide by “they look like that student.” It compares numbers.

1. A valid face is turned into a **128-dimension descriptor**.
2. That descriptor is compared with every enrolled student’s stored descriptor.
3. **ArcFace** uses cosine similarity (higher is closer). A match needs **0.36 or above**.
4. **LBPH** uses distance (lower is closer). A match needs **70 or below**.
5. If the **best** and **second-best** students are too close, the result is **lookalike / uncertain** and **no attendance** is recorded.

Defense answer for “what if they have a kamukha?”: the algorithm compares numerical descriptors. If two identities pass the threshold or sit within the margin, the system refuses to guess.

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
| `RECOGNITION_ENGINE` | `lbph` (default) or `arcface` |
| `ARCFACE_THRESHOLD` | Cosine similarity to accept (ArcFace only, ~0.36) |
| `ARCFACE_MIN_MARGIN` | Minimum gap vs runner-up; closer = lookalike, no attendance |
| `LBPH_THRESHOLD` | Max LBPH distance to accept (lower = stricter, ~70 typical) |
| `LBPH_MIN_MARGIN` | Minimum LBPH gap vs runner-up to accept a unique identity |
| `MIN_CONSEC_FRAMES` | Consecutive confident frames required before recording |
| `COOLDOWN_SECONDS` | Per-student gap to avoid duplicate posts |
| `SAMPLES_PER_STUDENT` | Face samples captured during enrollment |
| `MIN_FACE_SIZE` | Minimum face box (pixels) before matching |
| `FACE_VALIDATION` | `1` to reject unusable faces before matching |
| `FACE_BLUR_MIN_VARIANCE` | Laplacian sharpness floor (lower = more lenient) |
| `FACE_BRIGHTNESS_MIN` / `MAX` | Reject faces that are too dark or washed out |
| `FACE_FRONTAL_CHECK` | `1` to reject strong profile / collapsed landmarks |
| `SHOW_WINDOW` | `1` to show a preview window, `0` for headless |

## Notes & limitations

- **LBPH** is sensitive to lighting/pose and must be **retrained** to add students.
- **ArcFace** uses the same enrollment photos; it is more stable across lighting
  and pose. First `train.py` with `arcface` needs internet to download YuNet/SFace
  models into `models/`.
- Recording only succeeds when an **attendance session is open** for the
  student's section (auto-opened by schedule, or opened by the teacher).
- Attendance is **deduplicated** by the backend (`unique(session, student)`),
  so re-recognitions are harmless.
- No anti-spoofing / liveness yet.
