<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Calendar holiday sync
    |--------------------------------------------------------------------------
    |
    | Holidays are pulled into no_class_days so attendance sessions do not
    | auto-open on those dates (weekends are always skipped separately).
    | Default feed: Google's public "Holidays in Philippines" calendar (ICS).
    | Optional: set GOOGLE_CALENDAR_API_KEY + GOOGLE_CALENDAR_ID to use the API,
    | or GOOGLE_CALENDAR_ICS_URL for a custom public calendar ICS.
    |
    */
    'google_sync_enabled' => (bool) env('GOOGLE_CALENDAR_SYNC_ENABLED', true),

    'google_calendar_id' => env(
        'GOOGLE_CALENDAR_ID',
        'en.philippines.official#holiday@group.v.calendar.google.com'
    ),

    'google_calendar_api_key' => env('GOOGLE_CALENDAR_API_KEY'),

    'google_calendar_ics_url' => env('GOOGLE_CALENDAR_ICS_URL'),

    /*
    | How far ahead (and behind) to keep Google-synced holidays.
    */
    'sync_past_days' => (int) env('GOOGLE_CALENDAR_SYNC_PAST_DAYS', 30),

    'sync_future_days' => (int) env('GOOGLE_CALENDAR_SYNC_FUTURE_DAYS', 400),

];
