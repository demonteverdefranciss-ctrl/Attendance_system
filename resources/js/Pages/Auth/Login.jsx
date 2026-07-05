import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function Login({ status }) {
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

            <div className="min-h-screen flex items-center justify-center bg-gray-100 p-6">
                <div className="w-full max-w-md">
                    <div className="bg-white rounded-2xl shadow-sm ring-1 ring-gray-200 p-8">
                        <div className="text-center mb-6">
                            <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white text-2xl">
                                🎓
                            </div>
                            <h1 className="text-xl font-bold text-gray-900">Welcome Back</h1>
                            <p className="text-sm text-gray-500">
                                Sign in to the Attendance System
                            </p>
                        </div>

                        {status && (
                            <div className="mb-4 rounded-lg bg-green-50 px-4 py-2 text-sm text-green-700">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label htmlFor="username" className="block text-sm font-medium text-gray-700">
                                    Username
                                </label>
                                <input
                                    id="username"
                                    type="text"
                                    value={data.username}
                                    autoFocus
                                    autoComplete="username"
                                    onChange={(e) => setData('username', e.target.value)}
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                />
                                {errors.username && (
                                    <p className="mt-1 text-sm text-red-600">{errors.username}</p>
                                )}
                            </div>

                            <div>
                                <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                                    Password
                                </label>
                                <div className="mt-1 flex rounded-lg border border-gray-300 bg-white shadow-sm focus-within:border-blue-500 focus-within:ring-1 focus-within:ring-blue-500">
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
                                        className="rounded-r-lg px-3 text-sm font-medium text-gray-600 hover:bg-gray-50"
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
                                    className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                />
                                Remember me
                            </label>

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                {processing ? 'Signing in…' : 'Sign In'}
                            </button>
                        </form>
                    </div>

                    <p className="mt-4 text-center text-xs text-gray-400">
                        Bigaa Elementary School · Attendance System
                    </p>
                </div>
            </div>
        </>
    );
}
