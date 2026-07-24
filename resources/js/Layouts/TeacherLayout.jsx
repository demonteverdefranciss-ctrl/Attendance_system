import AppSidebarLayout from '@/Components/AppSidebarLayout';

const nav = [
    { label: 'Dashboard', route: 'teacher.dashboard' },
    { label: 'Mark Attendance', route: 'teacher.attendance.index' },
    { label: 'Enrollment Requests', route: 'teacher.enrollment-requests.index' },
    { label: 'Explanation Letters', route: 'teacher.excuse-requests.index' },
    { label: 'Biometric Photos', route: 'teacher.biometric-photos.index' },
    { label: 'Reports', route: 'reports.index' },
];

export default function TeacherLayout({ title, actions, children }) {
    return (
        <AppSidebarLayout nav={nav} title={title} actions={actions}>
            {children}
        </AppSidebarLayout>
    );
}
