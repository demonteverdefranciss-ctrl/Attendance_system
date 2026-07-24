import { Head } from '@inertiajs/react';
import ParentLayout from '@/Layouts/ParentLayout';
import { ChildBiometricUpload } from '@/Pages/Parent/shared';

export default function BiometricsIndex({ children = [] }) {
    return (
        <ParentLayout title="Biometric Face Photos">
            <Head title="Biometric Photos" />

            <p className="mb-4 text-sm text-gray-500">
                Upload your child&apos;s photos for teacher-approved face enrollment (RA 10173 consent required).
            </p>

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
