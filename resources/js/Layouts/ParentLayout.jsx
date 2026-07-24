import AppSidebarLayout from '@/Components/AppSidebarLayout';

const nav = [
    { label: 'Dashboard', route: 'parent.dashboard' },
    { label: 'Biometric Photos', route: 'parent.biometrics.index' },
    { label: 'Enrollment', route: 'parent.enrollment.index' },
    { label: 'Explanation Letters', route: 'parent.excuse-requests.index' },
    { label: 'Notifications', route: 'parent.notifications.index' },
];

export default function ParentLayout({ title, actions, children }) {
    return (
        <AppSidebarLayout nav={nav} title={title} actions={actions}>
            {children}
        </AppSidebarLayout>
    );
}
