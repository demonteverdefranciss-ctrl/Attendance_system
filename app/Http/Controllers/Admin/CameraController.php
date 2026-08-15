<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Camera;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CameraController extends Controller
{
    public function index(): Response
    {
        $cameras = Camera::with(['sections:id,camera_id,name,grade_level'])
            ->withCount('sections')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Cameras/Index', ['cameras' => $cameras]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Cameras/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);
        $deviceKey = $this->resolvedDeviceKey($data['device_key'] ?? null);

        Camera::create([
            'name' => $data['name'],
            'location' => $data['location'] ?: null,
            'rtsp_url' => $data['rtsp_url'] ?: null,
            'api_key_hash' => Hash::make($deviceKey),
            'is_active' => $data['is_active'],
        ]);

        return redirect()->route('admin.cameras.index')
            ->with('success', 'Camera added. Copy the device key into the school PC .env now — it will not be shown again.')
            ->with('device_key', $deviceKey);
    }

    public function edit(Camera $camera): Response
    {
        return Inertia::render('Admin/Cameras/Form', ['camera' => $camera]);
    }

    public function update(Request $request, Camera $camera): RedirectResponse
    {
        $data = $this->validateData($request, $camera);

        $payload = [
            'name' => $data['name'],
            'location' => $data['location'] ?: null,
            'rtsp_url' => $data['rtsp_url'] ?: null,
            'is_active' => $data['is_active'],
        ];

        $rotatedKey = null;
        if (! empty($data['device_key'])) {
            $rotatedKey = $data['device_key'];
            $payload['api_key_hash'] = Hash::make($rotatedKey);
        }

        $camera->update($payload);

        $redirect = redirect()->route('admin.cameras.index')
            ->with('success', $rotatedKey
                ? 'Camera updated. Copy the new device key into the school PC .env now — it will not be shown again.'
                : 'Camera updated successfully.');

        return $rotatedKey
            ? $redirect->with('device_key', $rotatedKey)
            : $redirect;
    }

    public function destroy(Camera $camera): RedirectResponse
    {
        $camera->delete();

        return redirect()->route('admin.cameras.index')->with('success', 'Camera deleted. Assigned sections now have no camera.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?Camera $camera = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'rtsp_url' => ['nullable', 'string', 'max:255'],
            'device_key' => ['nullable', 'string', 'min:8', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function resolvedDeviceKey(?string $provided): string
    {
        $key = is_string($provided) ? trim($provided) : '';

        return $key !== '' ? $key : Str::password(32, symbols: false);
    }
}
