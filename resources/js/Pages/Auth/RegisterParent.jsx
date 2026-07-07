import { useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function RegisterParent() {
    const { assetBase } = usePage().props;
    const logoUrl = `${assetBase}/branding/bigaa-logo.png`;
    const backgroundUrl = `${assetBase}/branding/login-background.png`;

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirm, setShowConfirm] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        first_name: '',
        last_name: '',
        phone: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('register.parent.store'));
    };

    const field = (id, label, type = 'text', extra = {}) => (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-blue-900">
                {label}
            </label>
            <input
                id={id}
                type={type}
                value={data[id]}
                onChange={(e) => setData(id, e.target.value)}
                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                {...extra}
            />
            {errors[id] && <p className="mt-1 text-sm text-red-600">{errors[id]}</p>}
        </div>
    );

    return (
        <>
            <Head title="Parent Registration" />

            <div
                className="relative min-h-screen flex items-center justify-center p-6 bg-cover bg-center bg-no-repeat"
                style={{ backgroundImage: `url('${backgroundUrl}')` }}
            >
                <div className="absolute inset-0 bg-gradient-to-br from-white/90 via-blue-50/85 to-blue-900/40" />

                <div className="relative z-10 w-full max-w-lg">
                    <div className="overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-blue-100">
                        <div className="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-5 text-center text-white">
                            <img
                                src={logoUrl}
                                alt="Bigaa Elementary School"
                                className="mx-auto mb-3 h-20 w-20 rounded-full bg-white p-1 shadow-md ring-4 ring-white/30 object-contain"
                            />
                            <h1 className="text-xl font-bold tracking-wide">Parent Registration</h1>
                            <p className="mt-1 text-sm text-blue-100">Create your guardian account</p>
                        </div>

                        <div className="p-8">
                            <p className="mb-5 text-center text-sm text-gray-500">
                                After registering, submit your child&apos;s LRN on the parent dashboard for teacher verification.
                            </p>

                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {field('first_name', 'First Name', 'text', { autoFocus: true })}
                                    {field('last_name', 'Last Name')}
                                </div>
                                {field('phone', 'Phone (optional)', 'tel')}
                                {field('username', 'Username', 'text', { autoComplete: 'username' })}
                                {field('email', 'Email (optional)', 'email', { autoComplete: 'email' })}

                                <div>
                                    <label htmlFor="password" className="block text-sm font-medium text-blue-900">
                                        Password
                                    </label>
                                    <div className="mt-1 flex rounded-lg border border-gray-300 bg-white shadow-sm focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600">
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            autoComplete="new-password"
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="block w-full rounded-l-lg border-0 focus:ring-0"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((v) => !v)}
                                            className="rounded-r-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50"
                                        >
                                            {showPassword ? 'Hide' : 'Show'}
                                        </button>
                                    </div>
                                    {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
                                </div>

                                <div>
                                    <label htmlFor="password_confirmation" className="block text-sm font-medium text-blue-900">
                                        Confirm Password
                                    </label>
                                    <div className="mt-1 flex rounded-lg border border-gray-300 bg-white shadow-sm focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600">
                                        <input
                                            id="password_confirmation"
                                            type={showConfirm ? 'text' : 'password'}
                                            value={data.password_confirmation}
                                            autoComplete="new-password"
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            className="block w-full rounded-l-lg border-0 focus:ring-0"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowConfirm((v) => !v)}
                                            className="rounded-r-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50"
                                        >
                                            {showConfirm ? 'Hide' : 'Show'}
                                        </button>
                                    </div>
                                    {errors.password_confirmation && (
                                        <p className="mt-1 text-sm text-red-600">{errors.password_confirmation}</p>
                                    )}
                                </div>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:opacity-50"
                                >
                                    {processing ? 'Creating account…' : 'Create Parent Account'}
                                </button>
                            </form>

                            <p className="mt-5 text-center text-sm text-gray-600">
                                Already have an account?{' '}
                                <Link href={route('login')} className="font-semibold text-blue-700 hover:underline">
                                    Sign in
                                </Link>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
