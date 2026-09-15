<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\RecognitionProcessService;
use App\Support\RecognitionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function updateEngine(Request $request, RecognitionProcessService $recognition): JsonResponse
    {
        $data = $request->validate([
            'engine' => ['required', 'in:lbph,arcface'],
        ]);

        $engine = RecognitionEngine::set($data['engine']);

        return response()->json([
            ...$recognition->snapshot(),
            'engine' => $engine,
            'message' => $engine === 'arcface'
                ? 'Camera matcher set to ArcFace. The school PC will switch on the next check.'
                : 'Camera matcher set to LBPH. The school PC will switch on the next check.',
        ]);
    }
}
