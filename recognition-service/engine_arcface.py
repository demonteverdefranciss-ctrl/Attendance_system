"""
ArcFace-style recognition using OpenCV SFace (trained with ArcFace loss) + YuNet.

No extra pip packages. ONNX models download into models/ on first use.
"""
import json
import os

import cv2
import numpy as np
import requests

import config
from engine import Detection, prepare_detection_frame

YUNET_URL = "https://github.com/opencv/opencv_zoo/raw/main/models/face_detection_yunet/face_detection_yunet_2023mar.onnx"
SFACE_URL = "https://github.com/opencv/opencv_zoo/raw/main/models/face_recognition_sface/face_recognition_sface_2021dec.onnx"
COSINE = getattr(cv2, "FaceRecognizerSF_FR_COSINE", getattr(cv2.FaceRecognizerSF, "FR_COSINE", 0))


def _download(url, dest):
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    print(f"Downloading {os.path.basename(dest)} …")
    resp = requests.get(url, timeout=120, stream=True, headers={"User-Agent": "BigaaES-Attendance"})
    resp.raise_for_status()
    tmp = dest + ".part"
    with open(tmp, "wb") as fh:
        for chunk in resp.iter_content(chunk_size=1024 * 256):
            if chunk:
                fh.write(chunk)
    os.replace(tmp, dest)


def ensure_models():
    if not os.path.isfile(config.YUNET_MODEL_PATH):
        _download(YUNET_URL, config.YUNET_MODEL_PATH)
    if not os.path.isfile(config.SFACE_MODEL_PATH):
        _download(SFACE_URL, config.SFACE_MODEL_PATH)


def _read_bgr(path):
    img = cv2.imread(path, cv2.IMREAD_COLOR)
    if img is not None:
        return img
    gray = cv2.imread(path, cv2.IMREAD_GRAYSCALE)
    if gray is None:
        return None
    return cv2.cvtColor(gray, cv2.COLOR_GRAY2BGR)


def _pad_scene(img):
    """Give tight enrollment crops enough margin for YuNet."""
    h, w = img.shape[:2]
    canvas = np.full((h * 3, w * 3, 3), 140, dtype=np.uint8)
    canvas[h : 2 * h, w : 2 * w] = img
    return canvas


def _detect_faces(detector, frame):
    h, w = frame.shape[:2]
    detector.setInputSize((w, h))
    _ok, faces = detector.detect(frame)
    if faces is None or len(faces) == 0:
        return []
    return faces


def _largest(faces):
    return max(faces, key=lambda f: float(f[2]) * float(f[3]))


class ArcFaceEngine:
    name = "arcface"

    def __init__(self):
        self._detector = None
        self._recognizer = None
        self._ids = np.array([], dtype=np.int32)
        self._embeddings = np.zeros((0, 128), dtype=np.float32)

    def ready(self):
        return os.path.isfile(config.ARCFACE_GALLERY_PATH)

    def missing_message(self):
        return "No ArcFace gallery. Run enroll.py then train.py with RECOGNITION_ENGINE=arcface."

    def load(self):
        ensure_models()
        self._detector = cv2.FaceDetectorYN.create(
            config.YUNET_MODEL_PATH,
            "",
            (320, 320),
            score_threshold=0.7,
            nms_threshold=0.3,
            top_k=5000,
        )
        self._recognizer = cv2.FaceRecognizerSF.create(config.SFACE_MODEL_PATH, "")
        data = np.load(config.ARCFACE_GALLERY_PATH, allow_pickle=False)
        self._ids = data["ids"]
        self._embeddings = data["embeddings"]

    def _feature(self, frame, face_row):
        aligned = self._recognizer.alignCrop(frame, face_row)
        feat = self._recognizer.feature(aligned)
        return np.asarray(feat, dtype=np.float32).reshape(-1)

    def _match(self, feat):
        if self._embeddings.size == 0:
            return None, 0.0
        scores = np.array(
            [
                self._recognizer.match(feat, other, COSINE)
                for other in self._embeddings
            ],
            dtype=np.float32,
        )
        best = int(np.argmax(scores))
        return int(self._ids[best]), float(scores[best])

    def identify(self, frame):
        detect_frame, scale = prepare_detection_frame(frame)
        faces = _detect_faces(self._detector, detect_frame)
        detections = []

        for face in faces:
            x, y, w, h = [int(v) for v in face[:4]]
            if scale != 1.0:
                x, y, w, h = int(x / scale), int(y / scale), int(w / scale), int(h / scale)

            feat = self._feature(detect_frame, face)
            student_id, score = self._match(feat)
            matched = student_id is not None and score >= config.ARCFACE_THRESHOLD
            detections.append(
                Detection(
                    x=max(0, x),
                    y=max(0, y),
                    w=max(1, w),
                    h=max(1, h),
                    student_id=student_id if matched else None,
                    matched=matched,
                    confidence=max(0.0, min(1.0, score)),
                    label=f"#{student_id} ({score:.2f})" if matched else f"unknown ({score:.2f})",
                )
            )

        return detections


def build_gallery():
    """Build models/arcface_gallery.npz from dataset/<student_id>/*.png."""
    if not os.path.isdir(config.DATASET_DIR):
        print("No dataset directory found. Run enroll.py first.")
        return False

    ensure_models()
    detector = cv2.FaceDetectorYN.create(
        config.YUNET_MODEL_PATH,
        "",
        (320, 320),
        score_threshold=0.6,
        nms_threshold=0.3,
        top_k=5000,
    )
    recognizer = cv2.FaceRecognizerSF.create(config.SFACE_MODEL_PATH, "")

    ids = []
    embeddings = []
    student_dirs = [
        d
        for d in os.listdir(config.DATASET_DIR)
        if os.path.isdir(os.path.join(config.DATASET_DIR, d)) and d.isdigit()
    ]

    for sid in student_dirs:
        folder = os.path.join(config.DATASET_DIR, sid)
        feats = []
        for name in os.listdir(folder):
            path = os.path.join(folder, name)
            if os.path.isdir(path):
                continue
            img = _read_bgr(path)
            if img is None:
                continue
            scene = img
            faces = _detect_faces(detector, scene)
            if not len(faces):
                scene = _pad_scene(img)
                faces = _detect_faces(detector, scene)
            if not len(faces):
                continue
            aligned = recognizer.alignCrop(scene, _largest(faces))
            feat = np.asarray(recognizer.feature(aligned), dtype=np.float32).reshape(-1)
            feats.append(feat)

        if not feats:
            print(f"  [WARN] no usable face for student {sid}")
            continue

        mean = np.mean(np.stack(feats), axis=0)
        norm = np.linalg.norm(mean)
        if norm > 0:
            mean = mean / norm
        ids.append(int(sid))
        embeddings.append(mean.astype(np.float32))
        print(f"  student {sid}: {len(feats)} embedding(s)")

    if not ids:
        print("No ArcFace embeddings. Enroll students with visible faces first.")
        return False

    os.makedirs(config.MODEL_DIR, exist_ok=True)
    np.savez(
        config.ARCFACE_GALLERY_PATH,
        ids=np.array(ids, dtype=np.int32),
        embeddings=np.stack(embeddings),
    )
    with open(config.LABELS_PATH, "w", encoding="utf-8") as fh:
        json.dump(
            {
                "engine": "arcface",
                "student_ids": ids,
                "samples": len(ids),
            },
            fh,
            indent=2,
        )

    print(f"ArcFace gallery saved to {config.ARCFACE_GALLERY_PATH} ({len(ids)} student(s)).")
    return True
