import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import TextField from '@/Components/TextField';

export default function CameraForm({ camera }) {
    const editing = !!camera;
    const { data, setData, post, put, processing, errors } = useForm({
        name: camera?.name ?? '',
        location: camera?.location ?? '',
        rtsp_url: camera?.rtsp_url ?? '',
        device_key: '',
        is_active: camera?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        editing
            ? put(route('admin.cameras.update', camera.id))
            : post(route('admin.cameras.store'));
    };

    return (
        <AdminLayout title={editing ? 'Edit Camera' : 'Add Camera'}>
            <Head title={editing ? 'Edit Camera' : 'Add Camera'} />

            <form onSubmit={submit} className="max-w-2xl space-y-5 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-200">
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <TextField label="Name" value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} placeholder="e.g. Grade 6 door" />
                    <TextField label="Location" value={data.location} onChange={(e) => setData('location', e.target.value)} error={errors.location} placeholder="e.g. Mabini classroom" />
                    <div className="sm:col-span-2">
                        <TextField
                            label="RTSP URL (optional)"
                            value={data.rtsp_url}
                            onChange={(e) => setData('rtsp_url', e.target.value)}
                            error={errors.rtsp_url}
                            placeholder="rtsp://..."
                        />
                    </div>
                    <div className="sm:col-span-2">
                        <TextField
                            label={editing ? 'New device key (leave blank to keep)' : 'Device key (leave blank to auto-generate)'}
                            value={data.device_key}
                            onChange={(e) => setData('device_key', e.target.value)}
                            error={errors.device_key}
                            placeholder="Used by the school PC recognition program"
                        />
                    </div>
                </div>

                <label className="flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={!!data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                    />
                    Active
                </label>
                {errors.is_active && <p className="-mt-3 text-sm text-red-600">{errors.is_active}</p>}

                <p className="text-xs text-gray-500">
                    After saving, assign this camera on the section form. Each school PC that runs that camera uses
                    its Camera ID and device key in <code>recognition-service/.env</code>.
                </p>

                <div className="flex items-center gap-3">
                    <button type="submit" disabled={processing} className="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-50">
                        {editing ? 'Update' : 'Save'}
                    </button>
                    <Link href={route('admin.cameras.index')} className="text-sm text-gray-500 hover:underline">Cancel</Link>
                </div>
            </form>
        </AdminLayout>
    );
}
