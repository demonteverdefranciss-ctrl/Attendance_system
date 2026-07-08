<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Camera MJPEG stream URL
    |--------------------------------------------------------------------------
    |
    | URL of the recognition-service MJPEG server (stream_server.py). The Laravel
    | app proxies this for authenticated teachers/admins so the browser can show
    | a live preview. Only reachable on the school LAN / local PC.
    |
    | Example: http://127.0.0.1:5050/stream
    |
    */
    'stream_url' => env('CAMERA_STREAM_URL'),
];
