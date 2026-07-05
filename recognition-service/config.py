"""Central configuration for the recognition service (loaded from .env)."""
import os

from dotenv import load_dotenv

load_dotenv()

API_BASE_URL = os.getenv("API_BASE_URL", "http://localhost/attendance_system/public/api/v1")
CAMERA_ID = os.getenv("CAMERA_ID", "1")
DEVICE_KEY = os.getenv("DEVICE_KEY", "demo-device-key-12345")
EVENT_TYPE_HINT = os.getenv("EVENT_TYPE_HINT", "").strip().lower() or None

VIDEO_SOURCE = os.getenv("VIDEO_SOURCE", "0")

LBPH_THRESHOLD = float(os.getenv("LBPH_THRESHOLD", "70"))
MIN_CONSEC_FRAMES = int(os.getenv("MIN_CONSEC_FRAMES", "5"))
COOLDOWN_SECONDS = int(os.getenv("COOLDOWN_SECONDS", "300"))
SAMPLES_PER_STUDENT = int(os.getenv("SAMPLES_PER_STUDENT", "20"))
SHOW_WINDOW = os.getenv("SHOW_WINDOW", "1") == "1"

FACE_SIZE = (200, 200)

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_DIR = os.path.join(BASE_DIR, "dataset")
MODEL_DIR = os.path.join(BASE_DIR, "models")
MODEL_PATH = os.path.join(MODEL_DIR, "lbph.yml")
LABELS_PATH = os.path.join(MODEL_DIR, "labels.json")


def resolved_video_source():
    """Webcam index (int) or RTSP/file string."""
    return int(VIDEO_SOURCE) if VIDEO_SOURCE.isdigit() else VIDEO_SOURCE
