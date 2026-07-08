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


def record(student_id, distance):
    confidence = lbph_distance_to_confidence(distance)
    try:
        resp = post_recognition(
            student_id,
            confidence=confidence,
            captured_at=datetime.now().astimezone().isoformat(),
            client_uuid=str(uuid.uuid4()),
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
        else:
            print(f"[WARN] student {student_id}: HTTP {resp.status_code} {resp.text[:200]}")
    except Exception as exc:  # network/offline — Phase 6b adds a local buffer
        print(f"[ERR] student {student_id}: {exc}")


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


def session_is_open():
    """Ask the backend whether any attendance session is open today."""
    try:
        resp = get_open_sessions()
        if resp.status_code == 200:
            return bool(resp.json().get("data", {}).get("open"))
        print(f"[WARN] session check: HTTP {resp.status_code} {resp.text[:120]}")
    except Exception as exc:
        print(f"[WARN] session check failed: {exc}")
    return False


def main():
    if not os.path.exists(config.MODEL_PATH):
        print("No trained model. Run enroll.py then train.py first.")
        return

    maybe_start_stream_server()

    recognizer = cv2.face.LBPHFaceRecognizer_create()
    recognizer.read(config.MODEL_PATH)
    cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")

    session_gated = config.SESSION_POLL_SECONDS > 0

    cap = None
    consecutive = {}   # student_id -> consecutive confident frames
    last_posted = {}   # student_id -> epoch seconds
    session_open = not session_gated
    last_session_check = 0.0

    if session_gated:
        print(f"Session-gated mode: camera runs only while a session is open "
              f"(checking every {config.SESSION_POLL_SECONDS}s). Press Ctrl+C to quit.")
    else:
        print("Recognizing. Press q to quit.")

    while True:
        # Re-check the backend periodically so the camera follows open/close.
        if session_gated and time.time() - last_session_check >= config.SESSION_POLL_SECONDS:
            session_open = session_is_open()
            last_session_check = time.time()

        if not session_open:
            if cap is not None:
                cap.release()
                cap = None
                cv2.destroyAllWindows()
                consecutive.clear()
                print("Session closed - camera released. Waiting for the next session...")
            time.sleep(1)
            continue

        if cap is None:
            print("Open session found - starting camera...")
            cap = cv2.VideoCapture(config.resolved_video_source())
            if not cap.isOpened():
                print("ERROR: cannot open video source", config.VIDEO_SOURCE)
                cap = None
                time.sleep(5)
                continue
            if config.SHOW_WINDOW:
                cv2.namedWindow("Recognize", cv2.WINDOW_NORMAL)

        ok, frame = cap.read()
        if not ok:
            print("[WARN] lost the video stream - reconnecting...")
            cap.release()
            cap = None
            time.sleep(2)
            continue

        if publish_frame is not None:
            publish_frame(frame)

        gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        faces = cascade.detectMultiScale(gray, 1.1, 5, minSize=(80, 80))
        seen = set()

        for (x, y, w, h) in faces:
            label, distance = recognizer.predict(cv2.resize(gray[y:y + h, x:x + w], config.FACE_SIZE))
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
            cv2.imshow("Recognize", for_display(frame))
            if cv2.waitKey(1) & 0xFF == ord("q"):
                break

    if cap is not None:
        cap.release()
    cv2.destroyAllWindows()


if __name__ == "__main__":
    main()
