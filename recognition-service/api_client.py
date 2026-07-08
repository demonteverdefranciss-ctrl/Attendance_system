"""Thin client for the backend recognition-ingest endpoint."""
import uuid

import requests

import config


def device_headers():
    return {
        "X-Camera-Id": str(config.CAMERA_ID),
        "X-Device-Key": config.DEVICE_KEY,
        "Accept": "application/json",
    }


def post_recognition(student_id, confidence=None, captured_at=None, client_uuid=None, event_type=None, timeout=10):
    """
    POST a recognition event to the backend (device-authenticated).

    Returns the requests.Response. The backend is idempotent on client_uuid,
    so re-sending the same uuid will not create a duplicate record.
    """
    url = f"{config.API_BASE_URL}/attendance/recognitions"
    headers = device_headers()
    payload = {
        "student_id": int(student_id),
        "client_uuid": client_uuid or str(uuid.uuid4()),
    }
    if confidence is not None:
        payload["confidence"] = round(float(confidence), 4)
    if captured_at is not None:
        payload["captured_at"] = captured_at
    if event_type in ("in", "out"):
        payload["event_type"] = event_type

    return requests.post(url, json=payload, headers=headers, timeout=timeout)


def get_open_sessions(timeout=10):
    """GET whether any attendance session is open today (device-authenticated)."""
    url = f"{config.API_BASE_URL}/attendance/sessions/open"
    return requests.get(url, headers=device_headers(), timeout=timeout)


def get_approved_biometric_submissions(timeout=30):
    """List parent-uploaded photos approved by a teacher and not yet synced."""
    url = f"{config.API_BASE_URL}/biometric/approved"
    return requests.get(url, headers=device_headers(), timeout=timeout)


def download_biometric_photo(photo_id, timeout=30):
    url = f"{config.API_BASE_URL}/biometric/photos/{photo_id}/file"
    return requests.get(url, headers=device_headers(), timeout=timeout)


def mark_biometric_submission_synced(submission_id, timeout=15):
    url = f"{config.API_BASE_URL}/biometric/submissions/{submission_id}/synced"
    return requests.post(url, headers=device_headers(), timeout=timeout)


def lbph_distance_to_confidence(distance):
    """Map an LBPH distance (0 = identical, grows worse) to a 0..1 score."""
    return max(0.0, min(1.0, 1.0 - distance / 100.0))
