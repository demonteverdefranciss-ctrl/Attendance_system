import { useState } from 'react';

export default function TeacherReviewActions({ onAccept, onReject, acceptLabel = 'Accept' }) {
    const [notes, setNotes] = useState('');

    return (
        <div className="mt-3 space-y-2">
            <input
                type="text"
                value={notes}
                maxLength={500}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Note (optional)"
                className="w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
            />
            <div className="flex flex-wrap gap-2">
                <button
                    type="button"
                    onClick={() => onAccept(notes.trim())}
                    className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                >
                    {acceptLabel}
                </button>
                <button
                    type="button"
                    onClick={() => onReject(notes.trim())}
                    className="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                >
                    Reject
                </button>
            </div>
        </div>
    );
}
