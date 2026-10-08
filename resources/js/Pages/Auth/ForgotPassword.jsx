import { useEffect, useState } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function ForgotPassword({ status, statusType, identifier = '', codeSent = false }) {
    const { assetBase } = usePage().props;
    const backgroundUrl = `${assetBase}/branding/login-background.png`;
    const requestForm = useForm({ identifier });
    const resetForm = useForm({
        identifier,
        code: '',
        password: '',
        password_confirmation: '',
    });
    const [resendSeconds, setResendSeconds] = useState(0);
    const [resendVersion, setResendVersion] = useState(0);

    useEffect(() => {
        if (!codeSent) {
            setResendSeconds(0);
            return undefined;
        }

        setResendSeconds(10);
        const timer = setInterval(() => {
            setResendSeconds((seconds) => Math.max(0, seconds - 1));
        }, 1000);

        return () => clearInterval(timer);
    }, [codeSent, resendVersion]);

    const requestCode = (event) => {
        event.preventDefault();
        resetForm.setData('identifier', requestForm.data.identifier);
        requestForm.post(route('password.email'), {
            onSuccess: () => resetForm.setData('identifier', requestForm.data.identifier),
        });
    };

    const resetPassword = (event) => {
        event.preventDefault();
        resetForm.post(route('password.update'));
    };

    const resendCode = () => {
        requestForm.post(route('password.email'), {
            preserveScroll: true,
            onSuccess: () => {
                resetForm.setData('identifier', requestForm.data.identifier);
                setResendVersion((version) => version + 1);
            },
        });
    };

    return (
        <>
            <Head title="Forgot Password" />
            <div
                className="relative flex min-h-screen items-center justify-center bg-cover bg-center bg-no-repeat p-6"
                style={{ backgroundImage: `url('${backgroundUrl}')` }}
            >
                <div className="absolute inset-0 bg-gradient-to-br from-white/90 via-blue-50/85 to-blue-900/40" />
                <div className="relative z-10 w-full max-w-md rounded-xl bg-white p-8 shadow-lg ring-1 ring-blue-100">
                    <h1 className="text-xl font-bold text-blue-900">Reset your password</h1>
                    <p className="mt-2 text-sm text-gray-600">
                        Enter your username or email. We will send a code to the email registered to your account.
                    </p>

                    {status && (
                        <div
                            role={statusType === 'warning' ? 'alert' : 'status'}
                            className={`mt-4 rounded-md border p-3 text-sm ${statusType === 'warning' ? 'border-red-300 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'}`}
                        >
                            {status}
                        </div>
                    )}

                    {!codeSent && <form onSubmit={requestCode} className="mt-6 space-y-4">
                        <div>
                            <label htmlFor="identifier" className="block text-sm font-medium text-blue-900">Username or email</label>
                            <input
                                id="identifier"
                                value={requestForm.data.identifier}
                                onChange={(event) => requestForm.setData('identifier', event.target.value)}
                                autoComplete="username"
                                required
                                className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                            />
                            {requestForm.errors.identifier && <p className="mt-1 text-sm text-red-600">{requestForm.errors.identifier}</p>}
                        </div>
                        <button
                            type="submit"
                            disabled={requestForm.processing}
                            className="w-full rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                        >
                            {requestForm.processing ? 'Sending…' : 'Send reset code'}
                        </button>
                    </form>}

                    {codeSent && <form onSubmit={resetPassword} className="mt-8 space-y-4 border-t border-gray-200 pt-6">
                            <div>
                                <div className="flex items-center justify-between gap-3">
                                    <label htmlFor="code" className="block text-sm font-medium text-blue-900">6-digit code</label>
                                    {resendSeconds > 0 ? (
                                        <span className="text-xs text-gray-500">Resend code in {resendSeconds}s</span>
                                    ) : (
                                        <button
                                            type="button"
                                            onClick={resendCode}
                                            disabled={requestForm.processing}
                                            className="text-xs font-semibold text-blue-700 hover:underline disabled:opacity-50"
                                        >
                                            {requestForm.processing ? 'Sending…' : 'Resend code'}
                                        </button>
                                    )}
                                </div>
                                <input
                                    id="code"
                                    value={resetForm.data.code}
                                    onChange={(event) => resetForm.setData('code', event.target.value.replace(/\D/g, '').slice(0, 6))}
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    required
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                                />
                                {resetForm.errors.code && <p className="mt-1 text-sm text-red-600">{resetForm.errors.code}</p>}
                            </div>
                            <div>
                                <label htmlFor="password" className="block text-sm font-medium text-blue-900">New password</label>
                                <input
                                    id="password"
                                    type="password"
                                    value={resetForm.data.password}
                                    onChange={(event) => resetForm.setData('password', event.target.value)}
                                    autoComplete="new-password"
                                    required
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                                />
                                {resetForm.errors.password && <p className="mt-1 text-sm text-red-600">{resetForm.errors.password}</p>}
                            </div>
                            <div>
                                <label htmlFor="password_confirmation" className="block text-sm font-medium text-blue-900">Confirm new password</label>
                                <input
                                    id="password_confirmation"
                                    type="password"
                                    value={resetForm.data.password_confirmation}
                                    onChange={(event) => resetForm.setData('password_confirmation', event.target.value)}
                                    autoComplete="new-password"
                                    required
                                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                                />
                            </div>
                            <button
                                type="submit"
                                disabled={resetForm.processing}
                                className="w-full rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white hover:bg-blue-800 disabled:opacity-50"
                            >
                                {resetForm.processing ? 'Updating…' : 'Change password'}
                            </button>
                    </form>}

                    {codeSent && (
                        <p className="mt-4 text-center text-sm">
                            <Link href={route('password.request')} className="font-semibold text-blue-700 hover:underline">
                                Use a different username or email
                            </Link>
                        </p>
                    )}

                    <p className="mt-6 text-center text-sm text-gray-600">
                        <Link href={route('login')} className="font-semibold text-blue-700 hover:underline">Back to sign in</Link>
                    </p>
                </div>
            </div>
        </>
    );
}