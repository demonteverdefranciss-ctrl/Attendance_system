"""
Push a single recognition event WITHOUT a camera — useful to demo the full
pipeline (Python -> API -> DB -> dashboard) and to verify device credentials.

Usage:
  python test_api.py <student_id> [confidence] [event_type]
  python test_api.py <student_id> [confidence] --double [pause_seconds]
"""
import sys
import uuid
import time
from datetime import datetime

from api_client import post_recognition


def send_event(student_id, confidence, event_type=None):
    return post_recognition(
        student_id,
        confidence=confidence,
        captured_at=datetime.now().astimezone().isoformat(),
        client_uuid=str(uuid.uuid4()),
        event_type=event_type,
    )


def main():
    if len(sys.argv) < 2:
        print("Usage:")
        print("  python test_api.py <student_id> [confidence] [event_type]")
        print("  python test_api.py <student_id> [confidence] --double [pause_seconds]")
        return

    student_id = int(sys.argv[1])
    confidence = float(sys.argv[2]) if len(sys.argv) > 2 and not sys.argv[2].startswith("--") else 0.9
    args = sys.argv[3:] if len(sys.argv) > 3 else []
    if len(sys.argv) > 2 and sys.argv[2].startswith("--"):
        args = sys.argv[2:]

    if "--double" in args:
        pause = 2
        idx = args.index("--double")
        if idx + 1 < len(args):
            try:
                pause = int(args[idx + 1])
            except ValueError:
                pass

        first = send_event(student_id, confidence)
        print("FIRST HTTP", first.status_code)
        print(first.text)
        time.sleep(pause)
        second = send_event(student_id, confidence)
        print("SECOND HTTP", second.status_code)
        print(second.text)
        return

    event_type = args[0] if args and args[0] in ("in", "out") else None
    resp = send_event(student_id, confidence, event_type=event_type)
    print("HTTP", resp.status_code)
    print(resp.text)


if __name__ == "__main__":
    main()
