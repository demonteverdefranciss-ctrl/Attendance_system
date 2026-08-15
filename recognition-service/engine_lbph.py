"""OpenCV LBPH matcher (Phase 6a)."""
import os

import cv2

import config
from engine import Detection, prepare_detection_frame
from api_client import lbph_distance_to_confidence


class LbphEngine:
    name = "lbph"

    def __init__(self):
        self._recognizer = None
        self._cascade = None

    def ready(self):
        return os.path.exists(config.MODEL_PATH)

    def missing_message(self):
        return "No trained LBPH model. Run enroll.py then train.py first."

    def load(self):
        self._recognizer = cv2.face.LBPHFaceRecognizer_create()
        self._recognizer.read(config.MODEL_PATH)
        self._cascade = cv2.CascadeClassifier(
            cv2.data.haarcascades + "haarcascade_frontalface_default.xml"
        )

    def identify(self, frame):
        detect_frame, scale = prepare_detection_frame(frame)
        gray_small = cv2.cvtColor(detect_frame, cv2.COLOR_BGR2GRAY)
        faces = self._cascade.detectMultiScale(
            gray_small,
            1.1,
            5,
            minSize=(config.MIN_FACE_SIZE, config.MIN_FACE_SIZE),
        )
        gray_full = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
        detections = []

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

            face_roi = gray_full[y : y + h, x : x + w]
            label, distance = self._recognizer.predict(cv2.resize(face_roi, config.FACE_SIZE))
            matched = distance <= config.LBPH_THRESHOLD
            confidence = lbph_distance_to_confidence(distance)
            detections.append(
                Detection(
                    x=x,
                    y=y,
                    w=w,
                    h=h,
                    student_id=int(label) if matched else None,
                    matched=matched,
                    confidence=confidence,
                    label=f"#{label} ({distance:.0f})" if matched else "unknown",
                )
            )

        return detections
