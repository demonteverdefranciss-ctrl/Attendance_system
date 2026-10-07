const paths = {
    dashboard: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    students: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75',
    teachers: 'M4 3h16v12H4zM8 21l4-6 4 6M8 7h8M8 11h4',
    guardians: 'M12 21s-9-5-9-12a5 5 0 0 1 9-3 5 5 0 0 1 9 3c0 7-9 12-9 12z',
    sections: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    cameras: 'M3 5h12v14H3zM15 10l6-4v12l-6-4',
    schedules: 'M12 8v4l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0',
    calendar: 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01',
    'no-class-days': 'M8 2v4m8-4v4M3 10h18M3 4h18v17H3zM9 14l6 4m0-4-6 4',
    'audit-logs': 'M9 3h6v4H9zM9 5H5v16h14V5h-4M8 12h8M8 16h8',
    archive: 'M3 3h18v5H3zM5 8v13h14V8M10 12h4',
    reports: 'M4 3v18h17M8 17v-5M13 17V8M18 17V5',
    attendance: 'M8 2v4m8-4v4M3 10h18M3 4h18v17H3zM8 15l3 3 5-5',
    enrollment: 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M3 21v-2a6 6 0 0 1 9-5M18 14v8m-4-4h8',
    'excuse-requests': 'M3 5h18v14H3zM3 6l9 7 9-7',
    notifications: 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
    biometrics: 'M8 4H4v4m12-4h4v4M4 16v4h4m12-4v4h-4M9 10h.01M15 10h.01M9 15q3 3 6 0',
};

export default function SidebarIcon({ destination }) {
    let name = destination === 'reports.index' ? 'reports' : destination.split('.')[1];
    if (destination === 'schedules.calendar') name = 'calendar';
    if (name === 'parents') name = 'guardians';
    if (name === 'biometric-photos') name = 'biometrics';
    if (name === 'enrollment-requests') name = 'enrollment';
    return <svg aria-hidden="true" className="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d={paths[name] || paths.dashboard} /></svg>;
}
