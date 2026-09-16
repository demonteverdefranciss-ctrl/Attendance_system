"""Durable attendance outbox. No camera, network, or third-party dependencies."""
import json
import sqlite3
import time
import uuid
from contextlib import contextmanager
from pathlib import Path
from datetime import datetime


class OfflineQueue:
    def __init__(self, path, namespace):
        self.path = str(path)
        self.namespace = namespace
        Path(path).parent.mkdir(parents=True, exist_ok=True)
        with self.connect() as db:
            db.execute('PRAGMA journal_mode=WAL')
            db.executescript('''
                CREATE TABLE IF NOT EXISTS events (
                    id INTEGER PRIMARY KEY, namespace TEXT NOT NULL,
                    client_uuid TEXT NOT NULL UNIQUE, payload TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'pending', attempts INTEGER DEFAULT 0,
                    next_attempt REAL DEFAULT 0, last_error TEXT, synced_at REAL
                );
                CREATE INDEX IF NOT EXISTS pending_events ON events(namespace, status, id);
                CREATE TABLE IF NOT EXISTS session_cache (
                    namespace TEXT PRIMARY KEY, payload TEXT NOT NULL
                );
            ''')

    @contextmanager
    def connect(self):
        db = sqlite3.connect(self.path, timeout=10)
        db.row_factory = sqlite3.Row
        db.execute('PRAGMA synchronous=FULL')
        try:
            with db:
                yield db
        finally:
            db.close()

    def cache_sessions(self, sessions):
        with self.connect() as db:
            db.execute('INSERT OR REPLACE INTO session_cache VALUES (?, ?)',
                       (self.namespace, json.dumps(sessions)))

    def sessions(self, now=None):
        now = time.time() if now is None else now
        with self.connect() as db:
            row = db.execute('SELECT payload FROM session_cache WHERE namespace=?',
                             (self.namespace,)).fetchone()
        sessions = json.loads(row['payload']) if row else []
        return [s for s in sessions if
                datetime.fromisoformat(s['opened_at']).timestamp() <= now <
                datetime.fromisoformat(s['expected_close_at']).timestamp()]

    def enqueue(self, student_id, confidence, event_type='in', now=None, cooldown=300):
        if event_type not in ('in', 'out'):
            raise ValueError('Event type must be in or out')
        now = datetime.now().astimezone() if now is None else now
        sessions = [s for s in self.sessions(now.timestamp()) if student_id in s['student_ids']]
        if len(sessions) != 1:
            raise ValueError('No valid cached session for this student. Connect to the server to open a session.')
        session_id = sessions[0]['id']
        # Persist deduplication across restarts and serialize concurrent captures.
        with self.connect() as db:
            db.execute('BEGIN IMMEDIATE')
            rows = db.execute('SELECT payload FROM events WHERE namespace=? AND status != ? ORDER BY id DESC',
                              (self.namespace, 'rejected')).fetchall()
            for row in rows:
                old = json.loads(row['payload'])
                if old['student_id'] == student_id and old['session_id'] == session_id and old['event_type'] == event_type:
                    if now.timestamp() - datetime.fromisoformat(old['captured_at']).timestamp() < cooldown:
                        return old['client_uuid']
                    break
            event_id = str(uuid.uuid4())
            payload = dict(student_id=student_id, confidence=confidence, event_type=event_type,
                           captured_at=now.isoformat(), client_uuid=event_id, session_id=session_id)
            db.execute('INSERT INTO events(namespace, client_uuid, payload) VALUES (?, ?, ?)',
                       (self.namespace, event_id, json.dumps(payload)))
        return event_id

    def pending(self, limit=50):
        with self.connect() as db:
            # Preserve capture order: do not skip a retrying arrival to send its departure.
            rows = db.execute("SELECT * FROM events WHERE namespace=? AND status='pending' ORDER BY id LIMIT ?",
                              (self.namespace, limit)).fetchall()
        ready = []
        for row in rows:
            if row['next_attempt'] > time.time():
                break
            ready.append(dict(row))
        return ready

    def synced(self, event_id):
        with self.connect() as db:
            db.execute("UPDATE events SET status='synced', synced_at=?, last_error=NULL WHERE client_uuid=? AND namespace=?",
                       (time.time(), event_id, self.namespace))

    def failed(self, event_id, error, permanent=False):
        with self.connect() as db:
            db.execute('''UPDATE events SET attempts=attempts+1, status=?, last_error=?,
                          next_attempt=? WHERE client_uuid=? AND namespace=? AND status='pending' ''',
                       ('rejected' if permanent else 'pending', error[:300], time.time() + 15,
                        event_id, self.namespace))

    def counts(self):
        with self.connect() as db:
            return dict(db.execute('SELECT status, COUNT(*) FROM events WHERE namespace=? GROUP BY status',
                                   (self.namespace,)).fetchall())

    def cleanup(self, days=30):
        with self.connect() as db:
            db.execute("DELETE FROM events WHERE namespace=? AND status='synced' AND synced_at<?",
                       (self.namespace, time.time() - days * 86400))


def sync_once(queue, send):
    """A single worker owns uploads. Only an explicit server acknowledgement is success."""
    for row in queue.pending():
        payload = json.loads(row['payload'])
        try:
            response = send(**payload, timeout=8)
            body = response.json()
            if response.status_code in (200, 201) and body.get('success') is True:
                queue.synced(row['client_uuid'])
            else:
                permanent = response.status_code in (400, 403, 404, 409, 422)
                code = (body.get('error') or {}).get('code', 'REQUEST_REJECTED')
                queue.failed(row['client_uuid'], f'HTTP {response.status_code}: {code}', permanent)
                if not permanent:
                    break
        except Exception as exc:
            # Do not persist exception text: request URLs may contain credentials.
            queue.failed(row['client_uuid'], type(exc).__name__)
            break
    queue.cleanup()
