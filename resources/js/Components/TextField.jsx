export default function TextField({ label, type = 'text', value, onChange, error, hint, ...props }) {
    return (
        <div>
            {label && (
                <label className="block text-sm font-medium text-gray-700">{label}</label>
            )}
            <input
                type={type}
                value={value ?? ''}
                onChange={onChange}
                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                {...props}
            />
            {hint && !error && <p className="mt-1 text-xs text-gray-500">{hint}</p>}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
