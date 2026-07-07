import { useState } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Login({ status }) {
    const { assetBase } = usePage().props;
    const logoUrl = `${assetBase}/branding/bigaa-logo.png`;
    const backgroundUrl = `${assetBase}/branding/login-background.png`;

    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        username: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    return (
        <>
            <Head title="Login" />

            <div
                className="relative min-h-screen flex items-center justify-center p-6 bg-cover bg-center bg-no-repeat"
                style={{ backgroundImage: `url('${backgroundUrl}')` }}
            >
                <div className="absolute inset-0 bg-gradient-to-br from-white/90 via-blue-50/85 to-blue-900/40" />

                <div className="relative z-10 w-full max-w-md">
                    <div className="overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-blue-100">
                        <div className="bg-gradient-to-r from-blue-700 to-blue-600 px-8 py-5 text-center text-white">
                            <img
                                src={logoUrl}
                                alt="Bigaa Elementary School"
                                className="mx-auto mb-3 h-24 w-24 rounded-full bg-white p-1 shadow-md ring-4 ring-white/30 object-contain"
                            />
                            <h1 className="text-xl font-bold tracking-wide">Bigaa Elementary School</h1>
                            <p className="mt-1 text-sm text-blue-100">Attendance Management System</p>
                        </div>

                        <div className="p-8">
                            <div className="mb-6 text-center">
                                <h2 className="text-lg font-semibold text-blue-900">Welcome Back</h2>
                                <p className="text-sm text-gray-500">Sign in with your school account</p>
                            </div>

                            {status && (
                                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
                                    {status}
                                </div>
                            )}

                            <form onSubmit={submit} className="space-y-4">
                                <div>
                                    <label htmlFor="username" className="block text-sm font-medium text-blue-900">
                                        Username
                                    </label>
                                    <input
                                        id="username"
                                        type="text"
                                        value={data.username}
                                        autoFocus
                                        autoComplete="username"
                                        onChange={(e) => setData('username', e.target.value)}
                                        className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                                    />
                                    {errors.username && (
                                        <p className="mt-1 text-sm text-red-600">{errors.username}</p>
                                    )}
                                </div>

                                <div>
                                    <label htmlFor="password" className="block text-sm font-medium text-blue-900">
                                        Password
                                    </label>
                                    <div className="mt-1 flex rounded-lg border border-gray-300 bg-white shadow-sm focus-within:border-blue-600 focus-within:ring-1 focus-within:ring-blue-600">
                                        <input
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                            autoComplete="current-password"
                                            onChange={(e) => setData('password', e.target.value)}
                                            className="block w-full rounded-l-lg border-0 focus:ring-0"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setShowPassword((prev) => !prev)}
                                            className="rounded-r-lg px-3 text-sm font-medium text-blue-700 hover:bg-blue-50"
                                        >
                                            {showPassword ? 'Hide' : 'Show'}
                                        </button>
                                    </div>
                                    {errors.password && (
                                        <p className="mt-1 text-sm text-red-600">{errors.password}</p>
                                    )}
                                </div>

                                <label className="flex items-center gap-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) => setData('remember', e.target.checked)}
                                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-600"
                                    />
                                    Remember me
                                </label>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:opacity-50"
                                >
                                    {processing ? 'Signing in…' : 'Sign In'}
                                </button>
                            </form>
                        </div>
                    </div>

                    <p className="mt-4 text-center text-xs font-medium text-white drop-shadow">
                        Cabuyao, Laguna · Pamantasan ng Cabuyao Capstone Project
                    </p>
                </div>
            </div>
        </>
    );
}
