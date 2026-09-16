import json
import tempfile
import unittest
from datetime import datetime, timedelta, timezone
from pathlib import Path
from offline_queue import OfflineQueue, sync_once


class Response:
    def __init__(self, status=200, success=True):
        self.status_code = status
        self.success = success

    def json(self):
        return {'success': self.success, 'error': {'code': 'TEST'}}


class QueueTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.path = Path(self.temp.name) / 'queue.sqlite3'
        self.queue = OfflineQueue(self.path, 'test-api|1')
        self.now = datetime.now(timezone.utc)
        self.queue.cache_sessions([{'id': 1, 'student_ids': [10],
            'opened_at': (self.now - timedelta(hours=1)).isoformat(),
            'expected_close_at': (self.now + timedelta(hours=1)).isoformat()}])

    def test_saved_capture_and_session_survive_restart(self):
        event = self.queue.enqueue(10, .9)
        restarted = OfflineQueue(self.path, 'test-api|1')
        self.assertEqual(restarted.pending()[0]['client_uuid'], event)
        self.assertEqual(len(restarted.sessions()), 1)

    def test_network_failure_keeps_original_id_and_timestamp(self):
        event = self.queue.enqueue(10, .9)
        original = self.queue.pending()[0]['payload']
        def fail(**kwargs):
            raise ConnectionError('secret URL must not be saved')
        sync_once(self.queue, fail)
        with self.queue.connect() as db:
            row = db.execute('SELECT * FROM events').fetchone()
            self.assertEqual(row['status'], 'pending')
            self.assertEqual(row['payload'], original)
            self.assertEqual(row['last_error'], 'ConnectionError')
            db.execute('UPDATE events SET next_attempt=0')
        sent = []
        sync_once(self.queue, lambda **kw: sent.append(kw) or Response())
        self.assertEqual(sent[0]['client_uuid'], event)
        self.assertEqual(self.queue.counts(), {'synced': 1})

    def test_restart_deduplication(self):
        first = self.queue.enqueue(10, .9)
        self.assertEqual(OfflineQueue(self.path, 'test-api|1').enqueue(10, .9), first)

    def test_expired_and_unknown_student_are_not_queued(self):
        with self.assertRaises(ValueError):
            self.queue.enqueue(11, .9)
        with self.assertRaises(ValueError):
            self.queue.enqueue(10, .9, now=self.now + timedelta(hours=2))

    def test_other_camera_or_server_cannot_upload_queue(self):
        self.queue.enqueue(10, .9)
        self.assertEqual(OfflineQueue(self.path, 'different-api|1').pending(), [])

    def test_arrival_failure_blocks_departure_until_retry(self):
        self.queue.enqueue(10, .9, 'in')
        self.queue.enqueue(10, .9, 'out')
        sent = []
        sync_once(self.queue, lambda **kw: sent.append(kw) or Response(503, False))
        self.assertEqual(len(sent), 1)
        self.assertEqual(self.queue.counts(), {'pending': 2})

    def test_rejected_events_are_retained_and_not_retried(self):
        self.queue.enqueue(10, .9)
        sync_once(self.queue, lambda **kw: Response(422, False))
        self.assertEqual(self.queue.counts(), {'rejected': 1})
        self.assertEqual(self.queue.pending(), [])

    def test_http_success_without_ack_is_not_synced(self):
        self.queue.enqueue(10, .9)
        sync_once(self.queue, lambda **kw: Response(200, False))
        self.assertEqual(self.queue.counts(), {'pending': 1})

    def test_cleanup_never_deletes_unsynced_data(self):
        self.queue.enqueue(10, .9)
        self.queue.cleanup(days=-1)
        self.assertEqual(self.queue.counts(), {'pending': 1})


if __name__ == '__main__':
    unittest.main()
