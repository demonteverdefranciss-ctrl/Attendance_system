import { useState } from 'react';

export default function CameraPreview({ streamUrl }) {
    const [error, setError] = useState(false);

    if (!streamUrl) {
        return (
            <div className="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-gray-200">
                Live camera preview is not configured. On the school PC, run{' '}
                <code className="rounded bg-gray-100 px-1">python stream_server.py</code> in{' '}
                <code className="rounded bg-gray-100 px-1">recognition-service</code> and set{' '}
                <code className="rounded bg-gray-100 px-1">CAMERA_STREAM_URL</code> in the Laravel{' '}
                <code className="rounded bg-gray-100 px-1">.env</code>.
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl bg-black ring-1 ring-gray-200">
            <div className="flex items-center justify-between bg-gray-900 px-3 py-2 text-xs text-gray-300">
                <span>Live camera</span>
                <span className="rounded-full bg-red-600 px-2 py-0.5 font-semibold uppercase tracking-wide text-white">
                    Live
                </span>
            </div>
            {error ? (
                <div className="p-6 text-center text-sm text-gray-300">
                    Cannot load the camera stream. Make sure{' '}
                    <code className="rounded bg-gray-800 px-1">stream_server.py</code> is running on the
                    school PC and you are using the local site (not Railway).
                </div>
            ) : (
                <img
                    src={streamUrl}
                    alt="Live camera feed"
                    className="max-h-[480px] w-full object-contain"
                    onError={() => setError(true)}
                />
            )}
        </div>
    );
}
