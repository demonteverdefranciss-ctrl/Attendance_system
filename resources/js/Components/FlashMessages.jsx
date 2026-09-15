import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

function firstValidationError(errors) {
    const entries = Object.entries(errors ?? {});
    const photo = entries.find(([key]) => key === 'photos' || key.startsWith('photos.'));
    const ordered = photo ? [photo, ...entries.filter((entry) => entry !== photo)] : entries;

    for (const [, value] of ordered) {
        if (typeof value === 'string' && value.trim()) {
            return value;
        }
        if (Array.isArray(value) && value[0]) {
            return String(value[0]);
        }
    }

    return null;
}

/**
 * Shows success / error / warning after saves, and a fail banner when validation errors appear.
 */
export default function FlashMessages({ className = '' }) {
    const page = usePage();
    const [visible, setVisible] = useState(null);
    const [visitKey, setVisitKey] = useState(0);

    useEffect(() => router.on('finish', () => setVisitKey((k) => k + 1)), []);

    useEffect(() => {
        const flash = page.props.flash ?? {};
        const errors = page.props.errors ?? {};
        const validationFailed = Object.keys(errors).length > 0;

        let message = null;
        if (flash.success) {
            message = { type: 'success', text: flash.success };
        } else if (flash.error) {
            message = { type: 'error', text: flash.error };
        } else if (flash.warning) {
            message = { type: 'warning', text: flash.warning };
        } else if (validationFailed) {
            const detail = firstValidationError(errors);
            message = {
                type: 'error',
                text: detail || 'Save failed. Please fix the highlighted fields and try again.',
            };
        }

        setVisible(message);
        if (!message) return undefined;

        const timer = window.setTimeout(() => setVisible(null), 6000);
        return () => window.clearTimeout(timer);
    }, [visitKey, page.props.flash, page.props.errors]);

    if (!visible) return null;

    const styles = {
        success: 'bg-emerald-600 text-white ring-emerald-700',
        error: 'bg-rose-600 text-white ring-rose-700',
        warning: 'bg-amber-500 text-white ring-amber-600',
    }[visible.type];

    const label = {
        success: 'Success',
        error: 'Failed',
        warning: 'Notice',
    }[visible.type];

    return (
        <div
            className={`pointer-events-none fixed inset-x-0 top-3 z-[100] flex justify-center px-4 sm:justify-end sm:px-6 ${className}`}
            role="status"
            aria-live="polite"
        >
            <div
                className={`pointer-events-auto flex max-w-lg items-start gap-3 rounded-xl px-4 py-3 text-sm shadow-lg ring-1 ${styles}`}
            >
                <div className="min-w-0 flex-1">
                    <p className="font-semibold">{label}</p>
                    <p className="mt-0.5 break-words opacity-95">{visible.text}</p>
                </div>
                <button
                    type="button"
                    onClick={() => setVisible(null)}
                    className="shrink-0 rounded-md bg-white/15 px-2 py-0.5 text-xs font-semibold hover:bg-white/25"
                >
                    Dismiss
                </button>
            </div>
        </div>
    );
}
