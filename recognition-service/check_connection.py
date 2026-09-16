"""Read-only backend/session diagnostics. Never posts attendance or prints credentials."""
from urllib.parse import urlsplit

import config
from api_client import get_open_sessions


def main():
    url = urlsplit(config.API_BASE_URL)
    print(f'Backend: {url.scheme}://{url.hostname}{url.path}')
    print(f'Camera: {config.CAMERA_ID}')
    try:
        response = get_open_sessions(timeout=20)
    except Exception as exc:
        print(f'Connection failed: {type(exc).__name__}. Verify the website URL and Railway service status.')
        return 1
    print(f'HTTP {response.status_code}')
    if response.status_code in (401, 403):
        print('Device access denied. Check that this camera is active and its device key matches this server.')
        return 1
    if response.status_code != 200:
        print('Server error. Check Railway deployment logs and database availability.')
        return 1
    try:
        body = response.json()
        payload = body.get('data') or {}
        sessions = payload.get('sessions')
        if body.get('success') is not True or not isinstance(sessions, list):
            print('Backend update required: the API does not provide the offline session roster.')
            return 1
        if not sessions:
            print('No open session for this camera. Open the correct section on this same website.')
            return 1
        for session in sessions:
            print(f"Session {session['id']}: {len(session['student_ids'])} eligible students; expires {session['expected_close_at']}")
        print('Session API is ready. Restart recognition to download and cache the sessions.')
        return 0
    except (ValueError, TypeError, KeyError, AttributeError):
        print('Invalid API response. Check the configured URL and deploy the matching backend version.')
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
