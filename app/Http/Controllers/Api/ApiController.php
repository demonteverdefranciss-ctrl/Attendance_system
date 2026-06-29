<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    /**
     * Consistent success envelope.
     */
    protected function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'error' => null,
        ], $status);
    }

    /**
     * Consistent error envelope.
     */
    protected function fail(string $message, string $code = 'ERROR', int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => null,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
