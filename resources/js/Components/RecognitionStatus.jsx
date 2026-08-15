import axios from 'axios';

const LABELS = {
    running: { text: 'Face recognition running', className: 'bg-green-50 text-green-700' },
    stopped: { text: 'Face recognition stopped', className: 'bg-amber-50 text-amber-800' },
    unavailable: { text: 'Face recognition not available on this server', className: 'bg-gray-50 text-gray-600' },
};

const ENGINES = [
    { id: 'lbph', label: 'LBPH' },
    { id: 'arcface', label: 'ArcFace' },
];

export default function RecognitionStatus({ enabled, status, engine = 'lbph', onStart, onEngineChange, starting, switching }) {
    const label = LABELS[status] ?? LABELS.stopped;

    const switcher = (
        <div className="flex rounded-lg bg-white p-0.5 ring-1 ring-gray-200">
            {ENGINES.map((item) => {
                const active = (engine || 'lbph') === item.id;
                return (
                    <button
                        key={item.id}
                        type="button"
                        disabled={switching}
                        onClick={() => onEngineChange?.(item.id)}
                        className={`rounded-md px-3 py-1 text-xs font-semibold ${
                            active ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50'
                        } disabled:opacity-50`}
                    >
                        {item.label}
                    </button>
                );
            })}
        </div>
    );

    if (!enabled) {
        return (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-blue-50 px-4 py-3 text-sm text-blue-900 ring-1 ring-blue-200">
                <p>
                    Face recognition runs on the school PC. Choose the matcher here — the camera follows this setting.
                </p>
                {switcher}
            </div>
        );
    }

    return (
        <div className={`flex flex-wrap items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-gray-200 ${label.className}`}>
            <div className="flex items-center gap-2">
                {status === 'running' && (
                    <span className="h-2 w-2 animate-pulse rounded-full bg-green-500" aria-hidden />
                )}
                <span>{label.text}</span>
            </div>
            <div className="flex flex-wrap items-center gap-2">
                {switcher}
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

export async function setRecognitionEngine(engine) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const { data } = await axios.post(
        route('teacher.recognition.engine'),
        { engine },
        token ? { headers: { 'X-CSRF-TOKEN': token } } : undefined,
    );

    return data;
}
