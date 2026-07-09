import axios from 'axios';

const LABELS = {
    running: { text: 'Face recognition running', className: 'bg-green-50 text-green-700' },
    stopped: { text: 'Face recognition stopped', className: 'bg-amber-50 text-amber-800' },
    unavailable: { text: 'Face recognition not available on this server', className: 'bg-gray-50 text-gray-600' },
};

export default function RecognitionStatus({ enabled, status, onStart, starting }) {
    if (!enabled) {
        return null;
    }

    const label = LABELS[status] ?? LABELS.stopped;

    return (
        <div className={`flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-gray-200 ${label.className}`}>
            <div className="flex items-center gap-2">
                {status === 'running' && (
                    <span className="h-2 w-2 animate-pulse rounded-full bg-green-500" aria-hidden />
                )}
                <span>{label.text}</span>
            </div>
            {status === 'stopped' && onStart && (
                <button
                    type="button"
                    onClick={onStart}
                    disabled={starting}
                    className="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    {starting ? 'Starting…' : 'Start recognition'}
                </button>
            )}
        </div>
    );
}

export async function fetchRecognitionStatus() {
    const { data } = await axios.get(route('teacher.recognition.status'));

    return data;
}

export async function startRecognition() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const { data } = await axios.post(
        route('teacher.recognition.start'),
        {},
        token ? { headers: { 'X-CSRF-TOKEN': token } } : undefined,
    );

    return data;
}
