import 'package:flutter/material.dart';
import '../services/api_client.dart';
import '../screens/student_directory_screen.dart';
import '../screens/no_class_days_screen.dart';
import '../screens/enrollment_screen.dart';
import '../screens/parent_excuse_screen.dart';
import '../screens/parent_biometric_screen.dart';
import '../screens/notifications_screen.dart';
import '../screens/teacher/teacher_attendance_screen.dart';
import '../screens/teacher/teacher_enrollment_screen.dart';
import '../screens/teacher/teacher_excuse_screen.dart';
import '../screens/teacher/teacher_biometric_screen.dart';

class SchoolNavigation extends StatelessWidget {
  const SchoolNavigation({
    super.key,
    required this.api,
    required this.teacher,
    required this.onReturn,
  });
  final ApiClient api;
  final bool teacher;
  final VoidCallback onReturn;
  @override
  Widget build(BuildContext context) {
    void open(Widget screen) {
      final navigator = Navigator.of(context);
      navigator.pop();
      navigator
          .push(MaterialPageRoute<void>(builder: (_) => screen))
          .then((_) => onReturn());
    }

    Widget entry(String title, IconData icon, Widget screen) => ListTile(
      leading: Icon(icon),
      title: Text(title),
      onTap: () => open(screen),
    );
    return Drawer(
      backgroundColor: const Color(0xFFDBEAFE),
      child: SafeArea(
        child: ListView(
          children: [
            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Image.asset(
                    'assets/branding/bigaa_logo.png',
                    width: 72,
                    height: 72,
                    semanticLabel: 'School logo',
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Bigaa Elementary School',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF1D4ED8),
                    ),
                  ),
                  Text(teacher ? 'Teacher portal' : 'Parent portal'),
                ],
              ),
            ),
            ListTile(
              selected: true,
              leading: const Icon(Icons.dashboard_outlined),
              title: const Text('Dashboard'),
              onTap: () => Navigator.pop(context),
            ),
            entry(
              teacher ? 'Mark attendance' : 'Attendance',
              Icons.fact_check_outlined,
              teacher
                  ? TeacherAttendanceScreen(api: api)
                  : StudentDirectoryScreen(api: api),
            ),
            entry(
              'No-class days',
              Icons.event_busy_outlined,
              NoClassDaysScreen(api: api),
            ),
            entry(
              teacher ? 'Enrollment requests' : 'Enrollment',
              Icons.person_add_alt_1,
              teacher
                  ? TeacherEnrollmentScreen(api: api)
                  : EnrollmentScreen(api: api),
            ),
            entry(
              'Explanation letters',
              Icons.mail_outline,
              teacher
                  ? TeacherExcuseScreen(api: api)
                  : ParentExcuseScreen(api: api),
            ),
            entry(
              'Biometric photos',
              Icons.face_outlined,
              teacher
                  ? TeacherBiometricScreen(api: api)
                  : ParentBiometricScreen(api: api),
            ),
            if (teacher)
              entry(
                'Student reports',
                Icons.assessment_outlined,
                StudentDirectoryScreen(api: api, reports: true),
              ),
            if (!teacher)
              entry(
                'Notifications',
                Icons.notifications_outlined,
                NotificationsScreen(
                  api: api,
                  onOpenExcuseLetters: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => ParentExcuseScreen(api: api),
                      ),
                    );
                  },
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class SchoolWelcome extends StatelessWidget {
  const SchoolWelcome({super.key, required this.name, required this.role});
  final String name;
  final String role;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(20),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFF1E3A8A), Color(0xFF2563EB)],
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      children: [
        CircleAvatar(
          radius: 30,
          backgroundColor: Colors.white,
          child: Padding(
            padding: const EdgeInsets.all(3),
            child: Image.asset('assets/branding/bigaa_logo.png'),
          ),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Welcome, $name',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 22,
                  fontWeight: FontWeight.bold,
                ),
              ),
              Text(
                '$role • Bigaa Elementary School',
                style: const TextStyle(color: Color(0xFFDBEAFE)),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}
