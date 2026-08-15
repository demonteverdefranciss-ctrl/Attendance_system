"""Recognition engine switch: LBPH (default) or ArcFace."""
from dataclasses import dataclass

import cv2

import config


@dataclass
class Detection:
    x: int
    y: int
    w: int
    h: int
    student_id: int | None
    matched: bool
    confidence: float
    label: str


def prepare_detection_frame(frame):
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


def load_engine(name=None):
    chosen = (name or config.RECOGNITION_ENGINE or "lbph").strip().lower()
    if chosen == "arcface":
        from engine_arcface import ArcFaceEngine

        return ArcFaceEngine()

    from engine_lbph import LbphEngine

    return LbphEngine()
