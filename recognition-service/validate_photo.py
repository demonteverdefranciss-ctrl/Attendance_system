"""
Validate a parent enrollment photo BEFORE teacher review.

Usage:
  python validate_photo.py <image_path>

Stdout is one JSON object:
  {"ok": true/false, "reason": "...", "faces": N, "descriptor": [...] or null, "message": "..."}
"""
import json
import os
import sys

import cv2
import numpy as np

import config

MESSAGES = {
    "OK": "Face is valid and usable for recognition.",
    "NO_FACE": "No face was detected. Upload a clear, front-facing photo of the student — not an object, screenshot, or full-body photo.",
    "MULTIPLE_FACES": "More than one face was found. Recapture a photo with only the student.",
    "TOO_SMALL": "The face is too small or too far away. Move closer and recapture.",
    "NOT_CLOSE_UP": "This looks like a full-body or distant photo. Recapture a closer photo of the student's face.",
    "BLURRY": "The photo is too blurry. Hold the phone still and recapture in better light.",
    "TOO_DARK": "The photo is too dark. Recapture in better lighting.",
    "TOO_BRIGHT": "The photo is too bright or washed out. Recapture in even lighting.",
    "NOT_FRONTAL": "The face is turned away. Have the student look straight at the camera.",
    "INVALID_IMAGE": "This file could not be read as a photo. Upload a JPEG or PNG.",
}

# Enrollment photos must show a face, not a distant full-body shot.
# Keep this looser than live-camera gates so normal parent phone photos pass.
MIN_FACE_HEIGHT_RATIO = 0.14
MIN_FACE_WIDTH_RATIO = 0.10


def _apply_exif(img, path):
    try:
        from PIL import Image, ImageOps

        transposed = ImageOps.exif_transpose(Image.open(path))
        if transposed is None:
            return img
        rgb = np.array(transposed.convert("RGB"))
        return cv2.cvtColor(rgb, cv2.COLOR_RGB2BGR)
    except Exception:
        return img


def _read_bgr(path):
    img = cv2.imread(path, cv2.IMREAD_COLOR)
    if img is None:
        try:
            data = np.fromfile(path, dtype=np.uint8)
            img = cv2.imdecode(data, cv2.IMREAD_COLOR)
        except OSError:
            img = None
    if img is None:
        gray = cv2.imread(path, cv2.IMREAD_GRAYSCALE)
        if gray is None:
            return None
        img = cv2.cvtColor(gray, cv2.COLOR_GRAY2BGR)
    return _apply_exif(img, path)


def _primary_face(faces):
    if not faces:
        return None, 0
    ranked = sorted(
        faces,
        key=lambda row: (int(row["w"]) * int(row["h"]), row.get("score") or 0),
        reverse=True,
    )
    best = ranked[0]
    best_area = max(1, int(best["w"]) * int(best["h"]))
    for other in ranked[1:]:
        area = int(other["w"]) * int(other["h"])
        if area >= best_area * 0.55:
            return None, len(faces)
    return best, 1


def _haar_faces(img):
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
    boxes = cascade.detectMultiScale(gray, 1.1, 3, minSize=(48, 48))
    rows = []
    for (x, y, w, h) in boxes:
        rows.append({"x": int(x), "y": int(y), "w": int(w), "h": int(h), "landmarks": None, "score": None})
    return rows


def _yunet_faces(img):
    if not os.path.isfile(config.YUNET_MODEL_PATH):
        return []
    detector = cv2.FaceDetectorYN.create(
        config.YUNET_MODEL_PATH,
        "",
        (img.shape[1], img.shape[0]),
        score_threshold=0.45,
        nms_threshold=0.3,
        top_k=5000,
    )
    detector.setInputSize((img.shape[1], img.shape[0]))
    _ok, faces = detector.detect(img)
    if faces is None or len(faces) == 0:
        return []
    rows = []
    for face in faces:
        x, y, w, h = [int(v) for v in face[:4]]
        landmarks = face[4:14] if len(face) >= 14 else None
        score = float(face[14]) if len(face) >= 15 else None
        rows.append({"x": x, "y": y, "w": w, "h": h, "landmarks": landmarks, "score": score, "raw": face})
    return rows


def _descriptor(img, face_row):
    if not os.path.isfile(config.SFACE_MODEL_PATH) or face_row.get("raw") is None:
        return None
    recognizer = cv2.FaceRecognizerSF.create(config.SFACE_MODEL_PATH, "")
    aligned = recognizer.alignCrop(img, face_row["raw"])
    feat = np.asarray(recognizer.feature(aligned), dtype=np.float32).reshape(-1)
    norm = np.linalg.norm(feat)
    if norm > 0:
        feat = feat / norm
    return [float(v) for v in feat.tolist()]


def validate_image(path):
    img = _read_bgr(path)
    if img is None:
        return {
            "ok": False,
            "reason": "INVALID_IMAGE",
            "faces": 0,
            "descriptor": None,
            "message": MESSAGES["INVALID_IMAGE"],
        }

    faces = _yunet_faces(img)
    if not faces:
        faces = _haar_faces(img)

    face, face_count = _primary_face(faces)
    if face is None and face_count == 0:
        return {
            "ok": False,
            "reason": "NO_FACE",
            "faces": 0,
            "descriptor": None,
            "message": MESSAGES["NO_FACE"],
        }
    if face is None:
        return {
            "ok": False,
            "reason": "MULTIPLE_FACES",
            "faces": face_count,
            "descriptor": None,
            "message": MESSAGES["MULTIPLE_FACES"],
        }

    img_h, img_w = img.shape[:2]
    if face["h"] < img_h * MIN_FACE_HEIGHT_RATIO or face["w"] < img_w * MIN_FACE_WIDTH_RATIO:
        return {
            "ok": False,
            "reason": "NOT_CLOSE_UP",
            "faces": 1,
            "descriptor": None,
            "message": MESSAGES["NOT_CLOSE_UP"],
        }

    descriptor = None
    try:
        descriptor = _descriptor(img, face)
    except Exception:
        descriptor = None

    return {
        "ok": True,
        "reason": "OK",
        "faces": 1,
        "descriptor": descriptor,
        "message": MESSAGES["OK"],
    }


def main():
    if len(sys.argv) < 2:
        json.dump(
            {
                "ok": False,
                "reason": "INVALID_IMAGE",
                "faces": 0,
                "descriptor": None,
                "message": "Missing image path.",
            },
            sys.stdout,
        )
        return 2

    result = validate_image(sys.argv[1])
    json.dump(result, sys.stdout)
    return 0 if result.get("ok") else 1


if __name__ == "__main__":
    raise SystemExit(main())
