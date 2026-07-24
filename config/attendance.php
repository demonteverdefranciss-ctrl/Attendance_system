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

];
