<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Session max duration (minutes)
    |--------------------------------------------------------------------------
    |
    | Any open session (schedule or manual) is closed automatically this many
    | minutes after opened_at. Default 360 = 6 hours. Set to 0 to disable.
    | Schedule windows still close at end_time if that comes first.
    |
    */
    'session_max_minutes' => (int) env('ATTENDANCE_SESSION_MAX_MINUTES', 360),

    'adhoc_session_max_minutes' => (int) env('ATTENDANCE_SESSION_MAX_MINUTES', 360),

    /*
    | Camera/face arrival after this many minutes from session opened_at is late.
    | Set to 0 to disable the session-start grace period.
    */
    'late_after_minutes' => (int) env('ATTENDANCE_LATE_AFTER_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | TEMPORARY — test clear button (remove after development)
    |--------------------------------------------------------------------------
    |
    | When true, teachers get a "Clear today's attendance" button used to wipe
    | today's sessions/records for retesting face recognition. Set to false
    | (or delete the feature) before final handover.
    |
    */
    'test_clear_enabled' => (bool) env('ATTENDANCE_TEST_CLEAR_ENABLED', true),

];
