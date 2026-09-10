"""
Scan-stage face validation (before matching).

A detected box is not enough to recognize or record attendance. This module
checks whether the crop is usable: size, brightness, sharpness, and (when
landmarks exist) a coarse frontal pose.
"""
import cv2
import numpy as np

import config

REASON_LABELS = {
    "OK": "valid",
    "NO_FACE": "no face crop",
    "TOO_SMALL": "too small",
    "BLURRY": "blurry",
    "TOO_DARK": "too dark",
    "TOO_BRIGHT": "too bright",
    "NOT_FRONTAL": "not frontal",
}


def reason_label(code):
    return REASON_LABELS.get(code, str(code).replace("_", " ").lower())


def crop_bgr(frame, x, y, w, h):
    height, width = frame.shape[:2]
    x = max(0, int(x))
    y = max(0, int(y))
    w = max(0, min(int(w), width - x))
    h = max(0, min(int(h), height - y))
    if w <= 0 or h <= 0:
        return None
    return frame[y : y + h, x : x + w]


def _is_frontal(landmarks, face_w, face_h):
    """Reject strong profile / collapsed landmarks. Lenient on slight angles."""
    if landmarks is None or len(landmarks) < 10 or face_w <= 0 or face_h <= 0:
        return True

    re_x, re_y, le_x, le_y, n_x, _n_y = [float(v) for v in list(landmarks)[:6]]
    eye_dx = abs(le_x - re_x)
    if eye_dx < face_w * 0.15:
        return False
    if abs(le_y - re_y) > face_h * 0.40:
        return False
    xmin, xmax = min(le_x, re_x), max(le_x, re_x)
    pad = face_w * 0.15
    if n_x < xmin - pad or n_x > xmax + pad:
        return False
    return True


def validate_detected_face(roi_bgr, face_w, face_h, landmarks=None, det_score=None):
    """
    Return (ok, reason_code). Matching must not run when ok is False.
    """
    if not config.FACE_VALIDATION:
        return True, "OK"

    if roi_bgr is None or getattr(roi_bgr, "size", 0) == 0:
        return False, "NO_FACE"

    if min(int(face_w), int(face_h)) < config.MIN_FACE_SIZE:
        return False, "TOO_SMALL"

    gray = roi_bgr if len(roi_bgr.shape) == 2 else cv2.cvtColor(roi_bgr, cv2.COLOR_BGR2GRAY)
    mean = float(np.mean(gray))
    if mean < config.FACE_BRIGHTNESS_MIN:
        return False, "TOO_DARK"
    if mean > config.FACE_BRIGHTNESS_MAX:
        return False, "TOO_BRIGHT"

    if min(gray.shape[:2]) >= 40:
        variance = float(cv2.Laplacian(gray, cv2.CV_64F).var())
        if variance < config.FACE_BLUR_MIN_VARIANCE:
            return False, "BLURRY"

    if config.FACE_FRONTAL_CHECK and not _is_frontal(landmarks, face_w, face_h):
        return False, "NOT_FRONTAL"

    if det_score is not None and det_score < 0.55:
        return False, "NO_FACE"

    return True, "OK"
