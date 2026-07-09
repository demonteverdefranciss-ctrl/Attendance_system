<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\RecognitionProcessService;
use Illuminate\Http\JsonResponse;

class RecognitionController extends Controller
{
    public function status(RecognitionProcessService $recognition): JsonResponse
    {
        return response()->json($recognition->snapshot());
    }

    public function start(RecognitionProcessService $recognition): JsonResponse
    {
        if (! $recognition->isEnabled()) {
            return response()->json([
                'enabled' => false,
                'status' => 'unavailable',
                'message' => 'Face recognition is not managed on this server.',
            ], 403);
        }

        $ok = $recognition->ensureRunning();

        return response()->json([
            ...$recognition->snapshot(),
            'started' => $ok,
            'message' => $ok
                ? 'Face recognition is running.'
                : 'Could not start face recognition. Check that the school PC has Python and the camera configured.',
        ], $ok ? 200 : 500);
    }
}
