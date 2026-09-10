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

    /*
    |--------------------------------------------------------------------------
    | Default matcher until a teacher picks one in Attendance
    |--------------------------------------------------------------------------
    */
    'engine' => env('RECOGNITION_ENGINE', 'arcface'),

    /*
    |--------------------------------------------------------------------------
    | Parent enrollment photo validation
    |--------------------------------------------------------------------------
    |
    | auto     = OpenCV checks when Python is available; otherwise size checks
    | required = reject uploads if the Python validator cannot run
    | off      = skip face checks (not for defense demos)
    |
    */
    'photo_validation' => env('FACE_PHOTO_VALIDATION', 'auto'),
];
