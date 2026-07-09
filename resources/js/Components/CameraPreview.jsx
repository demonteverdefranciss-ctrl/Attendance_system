import { useState } from 'react';

export default function CameraPreview({ streamUrl, recognitionEnabled = false, sessionOpen = true }) {
    const [error, setError] = useState(false);

    if (!streamUrl) {
        return (
            <div className="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-gray-200">
                Live camera preview is not configured on this server.
                {recognitionEnabled
                    ? ' Open an attendance session on the school PC to start face recognition automatically.'
                    : ' Set CAMERA_STREAM_URL in the Laravel .env on the school PC.'}
            </div>
        );
    }

    if (!sessionOpen) {
        return (
            <div className="rounded-xl bg-gray-50 p-4 text-sm text-gray-500 ring-1 ring-gray-200">
                Camera is off while the session is <strong>closed</strong>. Re-open attendance to start the live
                camera and face recognition.
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
                    Cannot load the camera stream. Make sure face recognition is running on the school PC
                    (open an attendance session or click Start recognition).
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
