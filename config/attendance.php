<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ad-hoc session max duration (minutes)
    |--------------------------------------------------------------------------
    |
    | Teacher "Open session" without a schedule is closed automatically after
    | this many minutes. Schedule-based sessions still close at schedule end.
    | Set to 0 to disable the duration timeout.
    |
    */
    'adhoc_session_max_minutes' => (int) env('ATTENDANCE_ADHOC_SESSION_MAX_MINUTES', 30),

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
