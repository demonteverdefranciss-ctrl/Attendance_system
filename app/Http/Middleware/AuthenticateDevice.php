<?php

namespace App\Http\Middleware;

use App\Models\Camera;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Machine-to-machine auth for the recognition node.
 * Expects headers: X-Camera-Id and X-Device-Key.
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->header('X-Camera-Id');
        $key = $request->header('X-Device-Key');

        $unauthorized = response()->json([
            'success' => false,
            'data' => null,
            'error' => ['code' => 'DEVICE_UNAUTHORIZED', 'message' => 'Invalid device credentials.'],
        ], 401);

        if (! $id || ! $key) {
            return $unauthorized;
        }

        $camera = Camera::where('id', $id)->where('is_active', true)->first();

        if (! $camera || ! Hash::check($key, $camera->api_key_hash)) {
            return $unauthorized;
        }

        $request->attributes->set('camera', $camera);

        return $next($request);
    }
}
