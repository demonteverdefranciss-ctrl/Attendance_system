"""OpenCV LBPH matcher (Phase 6a)."""
import os

import cv2

import config
from engine import Detection, prepare_detection_frame
from api_client import lbph_distance_to_confidence
from face_validation import crop_bgr, reason_label, validate_detected_face
from identity_match import decide_distance


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

    def _ranked_matches(self, face_roi):
        """Return [(student_id, distance), ...] lowest distance first.

        Uses OpenCV's collector when available so a lookalike (runner-up)
        can be compared. Falls back to a single predict() result.
        """
        resized = cv2.resize(face_roi, config.FACE_SIZE)
        collect = getattr(self._recognizer, "predict_collect", None)
        create = getattr(cv2.face, "StandardCollector_create", None)
        if collect is not None and create is not None:
            try:
                collector = create()
                collect(resized, collector)
                try:
                    raw = collector.getResults(True)
                except TypeError:
                    raw = collector.getResults()
                by_id = {}
                for item in raw or []:
                    if isinstance(item, (tuple, list)) and len(item) >= 2:
                        sid, dist = int(item[0]), float(item[1])
                    else:
                        continue
                    if sid not in by_id or dist < by_id[sid]:
                        by_id[sid] = dist
                if by_id:
                    return sorted(by_id.items(), key=lambda pair: pair[1])
            except Exception:
                pass

        label, distance = self._recognizer.predict(resized)
        return [(int(label), float(distance))]

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

            roi = crop_bgr(frame, x, y, w, h)
            ok, reason = validate_detected_face(roi, w, h)
            if not ok:
                detections.append(
                    Detection(
                        x=x,
                        y=y,
                        w=w,
                        h=h,
                        student_id=None,
                        matched=False,
                        confidence=0.0,
                        label=f"invalid: {reason_label(reason)}",
                        stage="invalid",
                        reason=reason,
                    )
                )
                continue

            face_roi = gray_full[y : y + h, x : x + w]
            ranked = self._ranked_matches(face_roi)
            if not ranked:
                continue
            best_id, best_dist = ranked[0]
            rival_id, rival_dist = (ranked[1] if len(ranked) > 1 else (None, 9999.0))
            matched, reason = decide_distance(
                best_id,
                best_dist,
                rival_id,
                rival_dist,
                config.LBPH_THRESHOLD,
                config.LBPH_MIN_MARGIN,
            )
            confidence = lbph_distance_to_confidence(best_dist)
            if reason == "LOOKALIKE":
                label = f"lookalike #{best_id}/#{rival_id} ({best_dist:.0f}/{rival_dist:.0f})"
            elif matched:
                label = f"#{best_id} ({best_dist:.0f})"
            else:
                label = "unknown"
            detections.append(
                Detection(
                    x=x,
                    y=y,
                    w=w,
                    h=h,
                    student_id=int(best_id) if matched else None,
                    matched=matched,
                    confidence=confidence,
                    label=label,
                    stage="matched" if matched else "unknown",
                    reason=reason,
                    rival_id=int(rival_id) if reason == "LOOKALIKE" and rival_id is not None else None,
                )
            )

        return detections
