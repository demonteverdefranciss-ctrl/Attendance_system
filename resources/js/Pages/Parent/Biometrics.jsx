import { Head, usePage } from '@inertiajs/react';
import ParentLayout from '@/Layouts/ParentLayout';
import { ChildBiometricUpload } from '@/Pages/Parent/shared';

export default function BiometricsIndex({ children = [] }) {
    const { assetBase } = usePage().props;

    return (
        <ParentLayout title="Biometric Face Photos">
            <Head title="Biometric Photos" />

            <p className="mb-4 text-sm text-gray-500">
                Upload a photo of your child&apos;s face — not a full-body photo, screenshot, or unrelated
                picture. The system rejects photos without a face. A teacher then confirms it is the correct
                student (RA 10173 consent required).
            </p>

            <section className="mb-4 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-200">
                <h2 className="text-sm font-semibold text-gray-900">Sample biometric photos</h2>
                <p className="mt-1 text-xs text-gray-500">
                    Use clear photos showing the front and both sides of your child&apos;s face, with good lighting.
                </p>
                <img
                    src={`${assetBase || ''}/illustrations/biometric_photo_sample.png`}
                    alt="Examples of a clear front-view photo and left and right face angles, with guidance on visibility and lighting."
                    className="mt-3 h-auto w-full rounded-lg border border-gray-200 bg-slate-50"
                />
            </section>

            {children.length === 0 ? (
                <div className="rounded-xl bg-white p-8 text-center text-sm text-gray-400 shadow-sm ring-1 ring-gray-200">
                    Link a child first using Enrollment.
                </div>
            ) : (
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {children.map((child) => (
                        <ChildBiometricUpload key={child.id} child={child} />
                    ))}
                </div>
            )}
        </ParentLayout>
    );
}
