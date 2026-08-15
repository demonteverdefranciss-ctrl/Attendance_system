"""
Live recognition loop: read the video source, identify enrolled students, and
push attendance to the backend.

Validation safeguards before recording:
  * the engine (LBPH or ArcFace) must accept the match
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
import numpy as np

import config
from api_client import get_open_sessions, post_recognition
from engine import load_engine
from preview import for_display

LOCK_FILE = os.path.join(config.BASE_DIR, ".recognize.lock")

_post_status = {"text": "", "until": 0.0}
_post_status_lock = threading.Lock()
_hud = {"line": "Starting…", "ok": False}
_hud_lock = threading.Lock()


def set_post_status(text, seconds=4.0):
    with _post_status_lock:
        _post_status["text"] = text
        _post_status["until"] = time.time() + seconds


def current_post_status():
    with _post_status_lock:
        if time.time() > _post_status["until"]:
            return ""
        return _post_status["text"]


def set_hud(line, ok=False):
    with _hud_lock:
        _hud["line"] = line
        _hud["ok"] = ok


def current_hud():
    with _hud_lock:
        return _hud["line"], _hud["ok"]


class FrameGrabber:
    """Read RTSP/webcam on a background thread so the UI never freezes."""

    def __init__(self):
        self._cap = None
        self._lock = threading.Lock()
        self._frame = None
        self._ok = False
        self._running = False
        self._thread = None
        self._open_error = None

    @property
    def is_open(self):
        return self._running and self._cap is not None

    def start(self):
        if self._running:
            return True
        self._open_error = None
        set_hud("Connecting to camera…", ok=False)
        os.environ.setdefault(
            "OPENCV_FFMPEG_CAPTURE_OPTIONS",
            "rtsp_transport;tcp|fflags;nobuffer|flags;low_delay|max_delay;500000",
        )
        source = config.resolved_video_source()
        cap = cv2.VideoCapture(source, cv2.CAP_FFMPEG)
        if not cap.isOpened():
            cap = cv2.VideoCapture(source)
        if not cap.isOpened():
            self._open_error = f"cannot open video source {config.VIDEO_SOURCE}"
            set_hud("Camera offline — retrying…", ok=False)
            return False
        try:
            cap.set(cv2.CAP_PROP_BUFFERSIZE, 1)
        except Exception:
            pass
        self._cap = cap
        self._running = True
        self._thread = threading.Thread(target=self._loop, daemon=True)
        self._thread.start()
        set_hud("Camera live", ok=True)
        return True

    def _loop(self):
        skips = max(0, config.FRAME_SKIP)
        while self._running and self._cap is not None:
            try:
                for _ in range(skips):
                    self._cap.grab()
                ok, frame = self._cap.read()
            except Exception:
                ok, frame = False, None
            with self._lock:
                self._ok = bool(ok)
                if ok:
                    self._frame = frame
            if not ok:
                time.sleep(0.05)

    def read(self):
        with self._lock:
            if not self._ok or self._frame is None:
                return False, None
            return True, self._frame.copy()

    def stop(self):
        self._running = False
        thread = self._thread
        if thread is not None and thread.is_alive():
            thread.join(timeout=2.0)
        self._thread = None
        if self._cap is not None:
            try:
                self._cap.release()
            except Exception:
                pass
            self._cap = None
        with self._lock:
            self._ok = False
            self._frame = None


def record(student_id, confidence, session_state=None):
    """Post attendance in a background thread so RTSP reading stays live."""
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
                timeout=8,
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
                err_code = None
                try:
                    err_code = (resp.json().get("error") or {}).get("code")
                except Exception:
                    pass
                print(f"[WARN] student {student_id}: HTTP {resp.status_code} {resp.text[:200]}")
                set_post_status(f"Failed #{student_id} HTTP {resp.status_code}", seconds=6.0)
                # Backend says no open session → turn camera off immediately.
                if err_code == "NO_SESSION" and session_state is not None:
                    session_state["session_open"] = False
                    session_state["force_open"] = False
                    set_hud("Session closed — camera off", ok=False)
                    print("[INFO] NO_SESSION from API — releasing camera.")
        except Exception as exc:
            print(f"[ERR] student {student_id}: {exc}")
            set_post_status(f"Network error #{student_id}", seconds=6.0)

    threading.Thread(target=_post, daemon=True).start()


def should_quit(window_name="Recognize"):
    """True if user pressed q/Esc or closed the OpenCV window.

    Returns (quit, key) so callers can handle force-open / force-close keys.
    """
    key = cv2.waitKey(1) & 0xFF
    if key in (ord("q"), 27):
        return True, key
    try:
        if cv2.getWindowProperty(window_name, cv2.WND_PROP_VISIBLE) < 1:
            return True, key
    except cv2.error:
        return True, key
    return False, key


publish_frame = None


def maybe_start_stream_server():
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


def session_is_open(previous=False, timeout=8):
    """Ask the backend whether any attendance session is open.

    Returns (open_now, reached_api, engine_name).
    A successful `open: false` always turns the camera off.
    Network errors keep the previous state so a brief Railway blip does not
    flicker the camera.
    """
    try:
        resp = get_open_sessions(timeout=timeout)
        if resp.status_code == 200:
            payload = resp.json().get("data", {}) or {}
            open_now = bool(payload.get("open"))
            engine_name = payload.get("engine")
            if engine_name not in ("lbph", "arcface"):
                engine_name = None
            if open_now:
                set_hud("Session open · camera live", ok=True)
            else:
                set_hud("Session closed — camera off", ok=False)
                if previous:
                    print("[INFO] Backend reports no open session — releasing camera.")
            return open_now, True, engine_name
        print(f"[WARN] session check: HTTP {resp.status_code} {resp.text[:120]}")
        set_hud(f"API HTTP {resp.status_code} — keeping last state", ok=previous)
    except Exception as exc:
        print(f"[WARN] session check failed (keeping previous state): {exc}")
        set_hud("Railway unreachable — press O to force camera ON", ok=False)
    return previous, False, None


def check_session_async(state, timeout=8):
    def _check():
        try:
            open_now, reached, engine_name = session_is_open(
                previous=bool(state["session_open"]),
                timeout=timeout,
            )
            state["session_open"] = open_now
            state["api_ok"] = reached
            if engine_name:
                state["wanted_engine"] = engine_name
            # Website Close always wins over a previous manual O override.
            if reached and not open_now:
                state["force_open"] = False
        except Exception:
            state["api_ok"] = False
        finally:
            state["checking"] = False

    if state.get("checking"):
        return
    state["checking"] = True
    threading.Thread(target=_check, daemon=True).start()


def switch_engine(current, wanted_name):
    if not wanted_name or wanted_name == current.name:
        return current
    candidate = load_engine(wanted_name)
    if not candidate.ready():
        print(f"[WARN] teacher asked for {wanted_name} but it is not trained. Staying on {current.name}.")
        set_hud(f"{wanted_name.upper()} not trained — using {current.name.upper()}", ok=False)
        return current
    try:
        candidate.load()
    except Exception as exc:
        print(f"[WARN] could not switch to {wanted_name}: {exc}")
        return current
    print(f"[INFO] matcher is now {candidate.name}")
    set_hud(f"Matcher: {candidate.name.upper()}", ok=True)
    return candidate


def acquire_lock():
    if os.path.exists(LOCK_FILE):
        try:
            old_pid = int(open(LOCK_FILE, encoding="utf-8").read().strip())
        except (OSError, ValueError):
            old_pid = None
        if old_pid is not None:
            try:
                os.kill(old_pid, 0)
            except OSError:
                # Stale lock from a dead process.
                try:
                    os.remove(LOCK_FILE)
                except OSError:
                    pass
            else:
                print("ERROR: recognition is already running (PID %s)." % old_pid)
                print("Close that window (q / Esc / X) or stop the other process.")
                return False
        else:
            try:
                os.remove(LOCK_FILE)
            except OSError:
                pass

    with open(LOCK_FILE, "w", encoding="utf-8") as fh:
        fh.write(str(os.getpid()))
    return True


def release_lock():
    try:
        os.remove(LOCK_FILE)
    except OSError:
        pass


def prepare_detection_frame(frame):
    from engine import prepare_detection_frame as _prepare

    return _prepare(frame)


def draw_hud(frame):
    line, ok = current_hud()
    color = (80, 200, 80) if ok else (80, 180, 255)
    cv2.rectangle(frame, (0, 0), (frame.shape[1], 36), (20, 20, 20), -1)
    cv2.putText(frame, line[:70], (10, 24), cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 2)
    status = current_post_status()
    if status:
        cv2.putText(
            frame,
            status,
            (10, frame.shape[0] - 16),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.65,
            (0, 255, 255),
            2,
        )


def idle_frame(message, hint="q / Esc / close window to quit"):
    img = np.zeros((360, 640, 3), dtype=np.uint8)
    cv2.putText(img, message[:48], (24, 150), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (220, 220, 220), 2)
    cv2.putText(img, hint[:55], (24, 200), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (150, 150, 150), 1)
    cv2.putText(
        img,
        "O = force camera ON   C = force camera OFF",
        (24, 240),
        cv2.FONT_HERSHEY_SIMPLEX,
        0.5,
        (100, 200, 255),
        1,
    )
    draw_hud(img)
    return img


def main():
    engine = load_engine()
    print(f"Recognition engine: {engine.name}")
    if not engine.ready():
        print(engine.missing_message())
        return

    if not acquire_lock():
        return

    maybe_start_stream_server()

    try:
        engine.load()
    except Exception as exc:
        print(f"ERROR: could not load {engine.name} engine: {exc}")
        release_lock()
        return

    session_gated = config.SESSION_POLL_SECONDS > 0
    grabber = FrameGrabber()
    consecutive = {}
    last_posted = {}
    # Start closed until the API confirms an open session (or user presses O).
    session_state = {
        "session_open": False if session_gated else True,
        "checking": False,
        "force_open": False,  # manual override when Railway is unreachable
    }
    last_session_check = 0.0
    last_cam_retry = 0.0
    no_frame_since = None

    if config.SHOW_WINDOW:
        cv2.namedWindow("Recognize", cv2.WINDOW_NORMAL)

    if session_gated:
        print(
            "Session-gated mode: camera runs only while a session is open "
            f"(checking every {config.SESSION_POLL_SECONDS}s)."
        )
        print("Keys: O = force camera ON | C = force OFF | q/Esc = quit")
        set_hud("Checking Railway for open session…", ok=False)
        # Blocking first check with a longer timeout so a slow Railway still works.
        open_now, _, api_engine = session_is_open(previous=False, timeout=15)
        session_state["session_open"] = open_now
        if api_engine:
            session_state["wanted_engine"] = api_engine
            engine = switch_engine(engine, api_engine)
        last_session_check = time.time()
    else:
        print("Recognizing (camera always on). Keys: q/Esc = quit")
        set_hud("Camera always-on mode", ok=True)

    def handle_keys(key):
        """Apply O/C overrides. Returns True if the main loop should quit."""
        if key in (ord("q"), 27):
            return True
        if key in (ord("o"), ord("O")):
            session_state["force_open"] = True
            session_state["session_open"] = True
            set_hud("Forced ON (press C to turn off)", ok=True)
            print("[INFO] Forced camera ON (O).")
        elif key in (ord("c"), ord("C")):
            session_state["force_open"] = False
            session_state["session_open"] = False
            set_hud("Forced OFF — waiting for session", ok=False)
            print("[INFO] Forced camera OFF (C).")
        return False

    try:
        while True:
            now = time.time()
            # Poll often so Open/Close on the website turns the camera on/off quickly.
            if session_gated:
                poll_every = 3
            else:
                poll_every = 15

            if now - last_session_check >= poll_every:
                # Longer timeout while waiting to open; shorter while already open.
                to = 12 if not session_state["session_open"] else 5
                check_session_async(session_state, timeout=to)
                last_session_check = now

            wanted = session_state.get("wanted_engine")
            if wanted and wanted != engine.name:
                engine = switch_engine(engine, wanted)

            session_open = True
            if session_gated:
                session_open = session_state["session_open"] or session_state["force_open"]

            if not session_open:
                if grabber.is_open:
                    grabber.stop()
                    consecutive.clear()
                    if publish_frame is not None:
                        from stream_server import clear_frame
                        clear_frame()
                    print("Session closed - camera released. Waiting for the next session...")
                    set_hud("Session closed — camera off", ok=False)

                if config.SHOW_WINDOW:
                    cv2.imshow(
                        "Recognize",
                        idle_frame(
                            "Waiting for open session…",
                            "If Railway is slow: press O to force camera ON",
                        ),
                    )
                    quit_now, key = should_quit("Recognize")
                    if handle_keys(key) or quit_now:
                        break
                else:
                    time.sleep(0.3)
                continue

            if not grabber.is_open:
                if now - last_cam_retry < 2.0:
                    if config.SHOW_WINDOW:
                        cv2.imshow("Recognize", idle_frame("Connecting to camera…"))
                        quit_now, key = should_quit("Recognize")
                        if handle_keys(key) or quit_now:
                            break
                    else:
                        time.sleep(0.2)
                    continue
                last_cam_retry = now
                print("Starting camera...")
                if not grabber.start():
                    print("ERROR:", grabber._open_error or "cannot open camera")
                    if config.SHOW_WINDOW:
                        cv2.imshow("Recognize", idle_frame("Camera offline — retrying…"))
                        quit_now, key = should_quit("Recognize")
                        if handle_keys(key) or quit_now:
                            break
                    continue

            ok, frame = grabber.read()
            if not ok:
                if no_frame_since is None:
                    no_frame_since = now
                elif now - no_frame_since > 4.0:
                    print("[WARN] lost the video stream - reconnecting...")
                    grabber.stop()
                    no_frame_since = None
                    set_hud("Reconnecting camera…", ok=False)
                if config.SHOW_WINDOW:
                    cv2.imshow("Recognize", idle_frame("Waiting for camera frames…"))
                    quit_now, key = should_quit("Recognize")
                    if handle_keys(key) or quit_now:
                        break
                else:
                    time.sleep(0.05)
                continue

            no_frame_since = None
            matcher = engine.name.upper()
            if session_state.get("force_open"):
                set_hud(f"Forced ON · {matcher} (C = off)", ok=True)
            else:
                set_hud(f"Session open · {matcher}", ok=True)

            if publish_frame is not None:
                publish_frame(frame)

            detections = engine.identify(frame)
            seen = set()

            for det in detections:
                if config.SHOW_WINDOW:
                    color = (0, 255, 0) if det.matched else (0, 0, 255)
                    cv2.rectangle(frame, (det.x, det.y), (det.x + det.w, det.y + det.h), color, 2)
                    cv2.putText(
                        frame,
                        det.label,
                        (det.x, max(20, det.y - 8)),
                        cv2.FONT_HERSHEY_SIMPLEX,
                        0.6,
                        color,
                        2,
                    )

                if det.matched and det.student_id is not None:
                    sid = det.student_id
                    seen.add(sid)
                    consecutive[sid] = consecutive.get(sid, 0) + 1
                    if consecutive[sid] >= config.MIN_CONSEC_FRAMES:
                        if now - last_posted.get(sid, 0) >= config.COOLDOWN_SECONDS:
                            record(sid, det.confidence, session_state=session_state)
                            last_posted[sid] = now
                        consecutive[sid] = 0

            for sid in list(consecutive.keys()):
                if sid not in seen:
                    consecutive[sid] = 0

            if config.SHOW_WINDOW:
                draw_hud(frame)
                cv2.imshow("Recognize", for_display(frame))
                quit_now, key = should_quit("Recognize")
                if handle_keys(key) or quit_now:
                    break
            else:
                time.sleep(0.01)

    finally:
        grabber.stop()
        cv2.destroyAllWindows()
        release_lock()


if __name__ == "__main__":
    try:
        main()
    except KeyboardInterrupt:
        print("\nStopped.")
    finally:
        release_lock()
