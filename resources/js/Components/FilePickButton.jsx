import { useEffect, useState } from 'react';

function PdfMark({ className = 'h-10 w-10' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} aria-hidden="true">
            <rect x="10" y="6" width="24" height="32" rx="3" fill="#FEF2F2" stroke="#DC2626" strokeWidth="2" />
            <path d="M34 14V6l8 8h-8z" fill="#FECACA" stroke="#DC2626" strokeWidth="2" strokeLinejoin="round" />
            <rect x="14" y="24" width="20" height="9" rx="1.5" fill="#DC2626" />
            <text x="24" y="31" textAnchor="middle" fontSize="7" fontWeight="700" fill="#fff">
                PDF
            </text>
        </svg>
    );
}

function PhotoMark({ className = 'h-10 w-10' }) {
    return (
        <svg viewBox="0 0 48 48" className={className} fill="none" aria-hidden="true">
            <rect x="6" y="10" width="36" height="28" rx="4" fill="#EFF6FF" stroke="#2563EB" strokeWidth="2" />
            <circle cx="16" cy="20" r="3.5" fill="#93C5FD" stroke="#2563EB" strokeWidth="1.5" />
            <path d="M10 34l10-10 7 7 5-5 10 8H10z" fill="#93C5FD" stroke="#2563EB" strokeWidth="1.5" strokeLinejoin="round" />
        </svg>
    );
}

function fileList(value, multiple) {
    if (multiple) {
        return Array.isArray(value) ? value : [];
    }
    return value ? [value] : [];
}

export default function FilePickButton({
    accept,
    multiple = false,
    kind = 'photo',
    label,
    hint,
    value,
    onChange,
}) {
    const files = fileList(value, multiple);
    const selected = files.length > 0;
    const [previews, setPreviews] = useState([]);

    useEffect(() => {
        const current = fileList(value, multiple);
        if (kind !== 'photo' || current.length === 0) {
            setPreviews([]);
            return undefined;
        }

        const urls = current.map((file) => URL.createObjectURL(file));
        setPreviews(urls);

        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [kind, multiple, value]);

    const handleChange = (e) => {
        const next = Array.from(e.target.files || []);
        if (multiple) {
            onChange(next.slice(0, 3));
            return;
        }
        onChange(next[0] || null);
        e.target.value = '';
    };

    return (
        <label
            className={`relative flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl px-3 py-4 text-center ring-1 transition ${
                selected
                    ? 'bg-blue-50 ring-2 ring-blue-600'
                    : 'bg-white ring-gray-200 hover:bg-gray-50 hover:ring-blue-300'
            }`}
        >
            <input
                type="file"
                accept={accept}
                multiple={multiple}
                onChange={handleChange}
                className="absolute inset-0 z-10 cursor-pointer opacity-0"
                aria-label={label}
            />
            {kind === 'pdf' ? <PdfMark /> : <PhotoMark />}
            <span className="text-sm font-semibold text-gray-900">{label}</span>
            {hint && !selected ? <span className="text-xs text-gray-500">{hint}</span> : null}
            {selected ? (
                <span className="relative z-20 max-w-full truncate text-xs font-medium text-blue-700">
                    {multiple ? `${files.length} photo${files.length === 1 ? '' : 's'} selected` : files[0].name}
                </span>
            ) : null}
            {previews.length > 0 ? (
                <span className="relative z-20 flex flex-wrap justify-center gap-1">
                    {previews.map((url) => (
                        <img key={url} src={url} alt="" className="h-12 w-12 rounded-md object-cover ring-1 ring-blue-100" />
                    ))}
                </span>
            ) : null}
        </label>
    );
}
