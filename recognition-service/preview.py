"""Scale camera frames for on-screen preview without affecting recognition."""
import os

import cv2

DISPLAY_MAX_WIDTH = int(os.getenv("DISPLAY_MAX_WIDTH", "1280"))


def for_display(frame, max_width=DISPLAY_MAX_WIDTH):
    """Fit wide RTSP frames on screen; processing still uses the full frame."""
    height, width = frame.shape[:2]
    if width <= max_width:
        return frame
    scale = max_width / width
    return cv2.resize(
        frame,
        (max_width, int(height * scale)),
        interpolation=cv2.INTER_AREA,
    )
