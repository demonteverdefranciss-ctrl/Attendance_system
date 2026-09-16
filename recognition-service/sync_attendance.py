"""Inspect or drain the attendance outbox without loading the camera/models.

python sync_attendance.py --status
python sync_attendance.py --once
python sync_attendance.py               # keep retrying until Ctrl+C
"""
import argparse
import json
import time
import config
from api_client import post_recognition
from offline_queue import OfflineQueue, sync_once


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--status', action='store_true')
    parser.add_argument('--once', action='store_true')
    args = parser.parse_args()
    queue = OfflineQueue(config.OFFLINE_QUEUE_PATH, f'{config.API_BASE_URL}|{config.CAMERA_ID}')
    if args.status:
        print(json.dumps(queue.counts(), indent=2))
        with queue.connect() as db:
            for row in db.execute("SELECT client_uuid, status, attempts, last_error FROM events WHERE namespace=? AND status!='synced' ORDER BY id LIMIT 20", (queue.namespace,)):
                print(dict(row))
        return
    while True:
        sync_once(queue, post_recognition)
        print(queue.counts(), flush=True)
        if args.once:
            return
        time.sleep(15)


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        print('Stopped. Pending attendance remains on disk.')
