"""
Live recognition loop: read the video source, identify enrolled students, and
push attendance to the backend.

Validation safeguards before recording:
  * LBPH distance must be within LBPH_THRESHOLD (confidence gate)
  * the same student must be seen for MIN_CONSEC_FRAMES consecutive frames
  * a per-student COOLDOWN_SECONDS prevents duplicate posts
The backend additionally enforces an open session + unique(session, student).
"""
import os
import threading
import time
import uuid
from datetime import datetime

import cv2

import config
from api_client import get_open_sessions, lbph_distance_to_confidence, post_recognition
from preview import for_display

LOCK_FILE = os.path.join(config.BASE_DIR, ".recognize.lock")

# Latest post status for the Recognize window overlay (thread-safe enough for display).
_post_status = {"text": "", "until": 0.0}
_post_status_lock = threading.Lock()


def set_post_status(text, seconds=4.0):
    with _post_status_lock:
        _post_status["text"] = text
        _post_status["until"] = time.time() + seconds


def current_post_status():
    with _post_status_lock:
        if time.time() > _post_status["until"]:
            return ""
        return _post_status["text"]


def record(student_id, distance):
    """Post attendance in a background thread so RTSP reading stays live."""
    confidence = lbph_distance_to_confidence(distance)
    captured_at = datetime.now().astimezone().isoformat()
    client_uuid = str(uuid.uuid4())
    set_post_status(f"Posting #{student_id}…", seconds=30.0)
    print(f"[…] posting student {student_id}…")

    def _post():
        try:
            resp = post_recognition(
                student_id,
                confidence=confidence,
                captured_at=captured_at,
                client_uuid=client_uuid,
                event_type=config.EVENT_TYPE_HINT,
            )
            if resp.status_code in (200, 201):
                mode = "recorded"
                try:
                    payload = resp.json().get("data", {})
                    if payload.get("time_out"):
                        mode = "time-out"
                    elif payload.get("time_in"):
                        mode = "time-in"
                except Exception:
                    pass
                print(f"[OK]  student {student_id} {mode} (conf={confidence:.2f})")
                set_post_status(f"OK #{student_id} {mode}", seconds=5.0)
            else:
                print(f"[WARN] student {student_id}: HTTP {resp.status_code} {resp.text[:200]}")
                set_post_status(f"Failed #{student_id} HTTP {resp.status_code}", seconds=6.0)
        except Exception as exc:  # network/offline — Phase 6b adds a local buffer
            print(f"[ERR] student {student_id}: {exc}")
            set_post_status(f"Network error #{student_id}", seconds=6.0)

    threading.Thread(target=_post, daemon=True).start()


def open_video_capture():
    """Open RTSP/webcam with a small buffer for lower latency."""
    # Prefer TCP + low-delay decode for IP cameras (ignored for webcam indexes).
    os.environ.setdefault(
        "OPENCV_FFMPEG_CAPTURE_OPTIONS",
        "rtsp_transport;tcp|fflags;nobuffer|flags;low_delay|max_delay;0",
    )
    cap = cv2.VideoCapture(config.resolved_video_source(), cv2.CAP_FFMPEG)
    if not cap.isOpened():
        cap = cv2.VideoCapture(config.resolved_video_source())
    try:
        cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
    except Exception:
        pass
    return cap


def read_latest_frame(cap):
    """Discard queued frames and return the newest one."""
    ok, frame = False, None
    skips = max(0, config.FRAME_SKIP)
    for _ in range(skips):
        cap.grab()
    ok, frame = cap.read()
    return ok, frame


publish_frame = None  # set when the web preview is enabled


def maybe_start_stream_server():
    """Serve the MJPEG web preview using OUR frames (one shared RTSP connection)."""
    port = os.getenv("STREAM_PORT", "").strip()
    if not port:
        return

    global publish_frame
    from stream_server import publish_frame as _publish, run_server

    publish_frame = _publish
    threading.Thread(
        target=lambda: run_server(port=int(port), capture=False),
        daemon=True,
    ).start()


def session_is_open(previous=True):
    """Ask the backend whether any attendance session is open today.

    On network/API errors, keep the previous state so Railway timeouts do not
    drop the live camera feed.
    """
    try:
        resp = get_open_sessions()
        if resp.status_code == 200:
            return bool(resp.json().get("data", {}).get("open"))
        print(f"[WARN] session check: HTTP {resp.status_code} {resp.text[:120]}")
    except Exception as exc:
        print(f"[WARN] session check failed (keeping previous state): {exc}")
    return previous


def check_session_async(state):
    """Refresh session_open in the background so the RTSP loop never blocks."""
    def _check():
        state["session_open"] = session_is_open(previous=state["session_open"])
        state["checking"] = False

    if state.get("checking"):
        return
    state["checking"] = True
    threading.Thread(target=_check, daemon=True).start()


def acquire_lock():
    if os.path.exists(LOCK_FILE):
        print("ERROR: recognition is already running.")
        print("Stop the other copy first (Ctrl+C in its terminal), or delete:")
        print(LOCK_FILE)
        return False

    with open(LOCK_FILE, "w", encoding="utf-8") as fh:
        fh.write(str(os.getpid()))
    return True


def release_lock():
    try:
        os.remove(LOCK_FILE)
    except OSError:
        pass


def prepare_detection_frame(frame):
    """Downscale wide frames for faster/more stable Haar detection."""
    height, width = frame.shape[:2]
    max_width = max(1, config.PROCESS_MAX_WIDTH)
    if width <= max_width:
        return frame, 1.0

    scale = max_width / width
    resized = cv2.resize(
        frame,
        (max_width, int(height * scale)),
        interpolation=cv2.INTER_AREA,
    )
    return resized, scale


def main():
    if not os.path.exists(config.MODEL_PATH):
        print("No trained model. Run enroll.py then train.py first.")
        return

    if not acquire_lock():
        return

    maybe_start_stream_server()

    recognizer = cv2.face.LBPHFaceRecognizer_create()
    recognizer.read(config.MODEL_PATH)
    cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")

    session_gated = config.SESSION_POLL_SECONDS > 0

    cap = None
    consecutive = {}   # student_id -> consecutive confident frames
    last_posted = {}   # student_id -> epoch seconds
    session_state = {
        "session_open": session_is_open(previous=True) if session_gated else True,
        "checking": False,
    }
    last_session_check = time.time()

    if session_gated:
        print(f"Session-gated mode: camera runs only while a session is open "
              f"(checking every {config.SESSION_POLL_SECONDS}s). Press Ctrl+C to quit.")
    else:
        print("Recognizing. Press q to quit.")

    while True:
        # Re-check the backend periodically so the camera follows open/close.
        if session_gated and time.time() - last_session_check >= config.SESSION_POLL_SECONDS:
            check_session_async(session_state)
            last_session_check = time.time()

        session_open = session_state["session_open"]

        if not session_open:
            if cap is not None:
                cap.release()
                cap = None
                cv2.destroyAllWindows()
                consecutive.clear()
                if publish_frame is not None:
                    from stream_server import clear_frame
                    clear_frame()
                print("Session closed - camera released. Waiting for the next session...")
            time.sleep(1)
            continue

        if cap is None:
            print("Open session found - starting camera...")
            cap = open_video_capture()
            if not cap.isOpened():
                print("ERROR: cannot open video source", config.VIDEO_SOURCE)
                cap = None
                time.sleep(5)
                continue
            if config.SHOW_WINDOW:
                cv2.namedWindow("Recognize", cv2.WINDOW_NORMAL)

        ok, frame = read_latest_frame(cap)
        if not ok:
            print("[WARN] lost the video stream - reconnecting...")
            cap.release()
            cap = None
            time.sleep(2)
            continue

        if publish_frame is not None:
            publish_frame(frame)

        detect_frame, scale = prepare_detection_frame(frame)
        gray_small = cv2.cvtColor(detect_frame, cv2.COLOR_BGR2GRAY)
        faces = cascade.detectMultiScale(
            gray_small,
            1.1,
            5,
            minSize=(config.MIN_FACE_SIZE, config.MIN_FACE_SIZE),
        )
        gray_full = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        seen = set()

        for (x, y, w, h) in faces:
            if scale != 1.0:
                x = int(x / scale)
                y = int(y / scale)
                w = int(w / scale)
                h = int(h / scale)

            x = max(0, x)
            y = max(0, y)
            w = min(w, gray_full.shape[1] - x)
            h = min(h, gray_full.shape[0] - y)
            if w <= 0 or h <= 0:
                continue

            face_roi = gray_full[y:y + h, x:x + w]
            label, distance = recognizer.predict(cv2.resize(face_roi, config.FACE_SIZE))
            matched = distance <= config.LBPH_THRESHOLD

            if config.SHOW_WINDOW:
                color = (0, 255, 0) if matched else (0, 0, 255)
                text = f"#{label} ({distance:.0f})" if matched else "unknown"
                cv2.rectangle(frame, (x, y), (x + w, y + h), color, 2)
                cv2.putText(frame, text, (x, y - 8), cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)

            if matched:
                seen.add(label)
                consecutive[label] = consecutive.get(label, 0) + 1
                if consecutive[label] >= config.MIN_CONSEC_FRAMES:
                    now = time.time()
                    if now - last_posted.get(label, 0) >= config.COOLDOWN_SECONDS:
                        record(label, distance)
                        last_posted[label] = now
                    consecutive[label] = 0

        for sid in list(consecutive.keys()):
            if sid not in seen:
                consecutive[sid] = 0

        if config.SHOW_WINDOW:
            status = current_post_status()
            if status:
                cv2.putText(
                    frame,
                    status,
                    (10, frame.shape[0] - 20),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.7,
                    (0, 255, 255),
                    2,
                )
            cv2.imshow("Recognize", for_display(frame))
            if cv2.waitKey(1) & 0xFF == ord("q"):
                break

    if cap is not None:
        cap.release()
    cv2.destroyAllWindows()
    release_lock()


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nStopped.")
    finally:
        release_lock()
