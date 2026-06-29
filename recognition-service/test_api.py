"""
Push a single recognition event WITHOUT a camera — useful to demo the full
pipeline (Python -> API -> DB -> dashboard) and to verify device credentials.

Usage: python test_api.py <student_id> [confidence]
"""
import sys
import uuid
from datetime import datetime

from api_client import post_recognition


def main():
    if len(sys.argv) < 2:
        print("Usage: python test_api.py <student_id> [confidence]")
        return

    student_id = int(sys.argv[1])
    confidence = float(sys.argv[2]) if len(sys.argv) > 2 else 0.9

    resp = post_recognition(
        student_id,
        confidence=confidence,
        captured_at=datetime.now().astimezone().isoformat(),
        client_uuid=str(uuid.uuid4()),
    )
    print("HTTP", resp.status_code)
    print(resp.text)


if __name__ == "__main__":
    main()
