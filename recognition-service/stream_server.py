"""
MJPEG HTTP preview for the Tapo / RTSP source (browser-friendly).

Browsers cannot play RTSP directly. This server reads the same VIDEO_SOURCE as
recognize.py and exposes multipart JPEG frames at /stream.

Usage:
  python stream_server.py
  # or set STREAM_PORT in .env and run recognize.py (starts stream in background)
"""
import os
import threading
import time
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import cv2

import config
from preview import for_display

_buffer_lock = threading.Lock()
_latest_jpeg = None
_capture_thread = None


def _capture_loop():
    cap = cv2.VideoCapture(config.resolved_video_source())
    if not cap.isOpened():
        print("ERROR: stream_server cannot open video source", config.VIDEO_SOURCE)
        return

    while True:
        ok, frame = cap.read()
        if not ok:
            time.sleep(0.1)
            continue

        ok, jpeg = cv2.imencode(".jpg", for_display(frame), [int(cv2.IMWRITE_JPEG_QUALITY), 80])
        if not ok:
            continue

        global _latest_jpeg
        with _buffer_lock:
            _latest_jpeg = jpeg.tobytes()


def start_capture():
    global _capture_thread
    if _capture_thread is not None and _capture_thread.is_alive():
        return

    _capture_thread = threading.Thread(target=_capture_loop, daemon=True)
    _capture_thread.start()


class MJPEGHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        return

    def do_GET(self):
        path = self.path.split("?", 1)[0].rstrip("/") or "/"
        if path not in ("/stream", "/"):
            self.send_error(404)
            return

        self.send_response(200)
        self.send_header("Content-Type", "multipart/x-mixed-replace; boundary=frame")
        self.send_header("Cache-Control", "no-cache, no-store, must-revalidate")
        self.send_header("Pragma", "no-cache")
        self.end_headers()

        try:
            while True:
                with _buffer_lock:
                    jpeg = _latest_jpeg

                if jpeg:
                    self.wfile.write(b"--frame\r\nContent-Type: image/jpeg\r\n\r\n")
                    self.wfile.write(jpeg)
                    self.wfile.write(b"\r\n")

                time.sleep(0.05)
        except (BrokenPipeError, ConnectionResetError, OSError):
            return


def run_server(host="0.0.0.0", port=5050):
    start_capture()
    server = ThreadingHTTPServer((host, port), MJPEGHandler)
    print(f"Camera web preview: http://127.0.0.1:{port}/stream")
    server.serve_forever()


if __name__ == "__main__":
    run_server(port=int(os.getenv("STREAM_PORT", "5050")))
