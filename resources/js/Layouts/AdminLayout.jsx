import AppSidebarLayout from '@/Components/AppSidebarLayout';

const nav = [
    { label: 'Dashboard', route: 'admin.dashboard' },
    { label: 'Students', route: 'admin.students.index' },
    { label: 'Teachers', route: 'admin.teachers.index' },
    { label: 'Parents / Guardians', route: 'admin.guardians.index' },
    { label: 'Sections', route: 'admin.sections.index' },
    { label: 'Schedules', route: 'admin.schedules.index' },
    { label: 'Audit Logs', route: 'admin.audit-logs.index' },
    { label: 'Reports', route: 'reports.index' },
];

export default function AdminLayout({ title, actions, children }) {
    return (
        <AppSidebarLayout nav={nav} title={title} actions={actions}>
            {children}
        </AppSidebarLayout>
    );
}
