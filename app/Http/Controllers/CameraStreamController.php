<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CameraStreamController extends Controller
{
    /**
     * Proxy the local MJPEG stream for browser embedding (same-origin).
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $url = config('camera.stream_url');
        abort_unless($url, 404);

        return response()->stream(function () use ($url) {
            try {
                $response = Http::timeout(0)
                    ->withOptions(['stream' => true])
                    ->get($url);

                if (! $response->successful()) {
                    return;
                }

                $body = $response->toPsrResponse()->getBody();

                while (! $body->eof()) {
                    echo $body->read(8192);

                    if (connection_aborted()) {
                        break;
                    }

                    flush();
                }
            } catch (\Throwable) {
                return;
            }
        }, 200, [
            'Content-Type' => 'multipart/x-mixed-replace; boundary=frame',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
