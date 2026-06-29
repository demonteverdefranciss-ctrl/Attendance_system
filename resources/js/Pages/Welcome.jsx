import { Head } from '@inertiajs/react';

export default function Welcome({ appName, laravelVersion, phpVersion }) {
    return (
        <>
            <Head title="Welcome" />

            <div className="min-h-screen bg-gray-50 flex items-center justify-center p-6">
                <div className="w-full max-w-xl rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 p-8 text-center">
                    <div className="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-full bg-blue-600 text-white text-2xl">
                        🎓
                    </div>

                    <h1 className="text-2xl font-bold text-gray-900">
                        {appName}
                    </h1>
                    <p className="mt-2 text-gray-500">
                        Inertia + React frontend is up and running.
                    </p>

                    <div className="mt-6 grid grid-cols-2 gap-3 text-sm">
                        <div className="rounded-lg bg-gray-50 p-3">
                            <div className="text-gray-400">Laravel</div>
                            <div className="font-semibold text-gray-800">
                                {laravelVersion}
                            </div>
                        </div>
                        <div className="rounded-lg bg-gray-50 p-3">
                            <div className="text-gray-400">PHP</div>
                            <div className="font-semibold text-gray-800">
                                {phpVersion}
                            </div>
                        </div>
                    </div>

                    <p className="mt-6 text-xs text-gray-400">
                        Next phase: authentication &amp; role-based dashboards.
                    </p>
                </div>
            </div>
        </>
    );
}
