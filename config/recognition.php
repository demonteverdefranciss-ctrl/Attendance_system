<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Manage recognition process from the web app
    |--------------------------------------------------------------------------
    |
    | When enabled, Laravel can start the local Python recognition node when a
    | teacher opens attendance (no manual terminal). Disable on Railway — the
    | camera and Python service only run on the school LAN PC.
    |
    */
    'manage_enabled' => env('RECOGNITION_MANAGE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Recognition service directory
    |--------------------------------------------------------------------------
    */
    'service_dir' => env('RECOGNITION_SERVICE_DIR', base_path('recognition-service')),

    /*
    |--------------------------------------------------------------------------
    | Python executable (optional)
    |--------------------------------------------------------------------------
    |
    | Defaults to recognition-service/.venv/Scripts/python.exe on Windows.
    |
    */
    'python' => env('RECOGNITION_PYTHON'),
];
