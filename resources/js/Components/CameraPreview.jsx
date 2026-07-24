import { useEffect, useState } from 'react';

export default function CameraPreview({ streamUrl, recognitionEnabled = false, sessionOpen = true }) {
    const [error, setError] = useState(false);
    const [streamKey, setStreamKey] = useState(0);

    // After Close → Re-open, remount the <img> so a prior stream error does not stick.
    useEffect(() => {
        if (sessionOpen && streamUrl) {
            setError(false);
            setStreamKey((k) => k + 1);
        }
    }, [sessionOpen, streamUrl]);

    if (!streamUrl) {
        return (
            <div className="rounded-xl bg-amber-50 p-4 text-sm text-amber-950 ring-1 ring-amber-200">
                <p className="font-medium">Browser live preview is not available on this cloud site.</p>
                <p className="mt-1 text-amber-900/90">
                    Face recognition runs on the <strong>school PC</strong>. Keep{' '}
                    <code className="rounded bg-amber-100 px-1">run_recognition.ps1</code> running there —
                    opening this session turns the camera on; closing turns it off. Marks still appear in the
                    table below (live updates every 2s).
                </p>
                {recognitionEnabled && (
                    <p className="mt-1 text-amber-900/80">
                        Optional browser preview: set <code className="rounded bg-amber-100 px-1">CAMERA_STREAM_URL</code>{' '}
                        when using the local school-PC website.
                    </p>
                )}
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
                <div className="space-y-3 p-6 text-center text-sm text-gray-300">
                    <p>
                        Cannot load the camera stream. Make sure face recognition is running on the school PC
                        (<code className="mx-1 rounded bg-gray-800 px-1">run_recognition.ps1</code>
                        and this session is open.
                    </p>
                    <button
                        type="button"
                        onClick={() => {
                            setError(false);
                            setStreamKey((k) => k + 1);
                        }}
                        className="rounded-lg bg-gray-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-600"
                    >
                        Retry preview
                    </button>
                </div>
            ) : (
                <img
                    key={streamKey}
                    src={streamUrl}
                    alt="Live camera feed"
                    className="max-h-[480px] w-full object-contain"
                    onError={() => setError(true)}
                />
            )}
        </div>
    );
}
