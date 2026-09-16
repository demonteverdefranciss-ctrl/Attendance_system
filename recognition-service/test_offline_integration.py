"""Recognition wiring checks with fake HTTP and no camera access."""
from unittest.mock import patch
import unittest
import test_offline_queue as queue_tests
import recognize


class IntegrationTests(unittest.TestCase):
    def setUp(self):
        queue_tests.QueueTests.setUp(self)
        event_type = patch.object(recognize.config, "EVENT_TYPE_HINT", "in")
        event_type.start()
        self.addCleanup(event_type.stop)
        self.patch = patch.object(recognize, 'outbox', self.queue)
        self.patch.start()
        self.addCleanup(self.patch.stop)

    def test_record_commits_without_contacting_server(self):
        with patch.object(recognize, 'post_recognition') as post:
            recognize.record(10, .9)
            self.assertEqual(self.queue.counts(), {'pending': 1})
            post.assert_not_called()

    def test_lost_connection_uses_valid_cached_session(self):
        with patch.object(recognize, 'get_open_sessions', side_effect=ConnectionError):
            opened, reached, _ = recognize.session_is_open()
        self.assertTrue(opened)
        self.assertFalse(reached)

    def test_authentication_rejection_clears_cache(self):
        with patch.object(recognize, 'get_open_sessions') as get:
            get.return_value.status_code = 401
            get.return_value.text = 'unauthorized'
            opened, reached, _ = recognize.session_is_open(previous=True)
        self.assertFalse(opened)
        self.assertEqual(self.queue.sessions(), [])

    def test_successful_closed_session_clears_cache(self):
        with patch.object(recognize, 'get_open_sessions') as get:
            get.return_value.status_code = 200
            get.return_value.json.return_value = {'success': True, 'data': {'open': False, 'sessions': []}}
            opened, reached, _ = recognize.session_is_open(previous=True)
        self.assertFalse(opened)
        self.assertTrue(reached)
        self.assertEqual(self.queue.sessions(), [])

    def test_capture_without_session_does_not_claim_saved(self):
        self.queue.cache_sessions([])
        recognize.record(10, .9)
        self.assertEqual(self.queue.counts(), {})
        self.assertIn('NOT SAVED', recognize.current_post_status())
