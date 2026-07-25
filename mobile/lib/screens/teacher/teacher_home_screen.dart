import 'package:flutter/material.dart';

import '../../services/api_client.dart';
import '../../services/session_service.dart';
import '../child_detail_screen.dart';
import 'teacher_attendance_screen.dart';
import 'teacher_biometric_screen.dart';
import 'teacher_enrollment_screen.dart';
import 'teacher_excuse_screen.dart';

class TeacherHomeScreen extends StatefulWidget {
  const TeacherHomeScreen({
    super.key,
    required this.api,
    required this.session,
    required this.onLogout,
  });

  final ApiClient api;
  final SessionService session;
  final VoidCallback onLogout;

  @override
  State<TeacherHomeScreen> createState() => _TeacherHomeScreenState();
}

class _TeacherHomeScreenState extends State<TeacherHomeScreen> {
  bool _loading = true;
  String? _error;
  String _userName = 'Teacher';
  Map<String, dynamic> _dash = {};
  List<Map<String, dynamic>> _students = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final name = await widget.session.userName();
      final dash = await widget.api.get('/teacher/dashboard');
      final students = await widget.api.get('/students');

      setState(() {
        _userName = name ?? 'Teacher';
        _dash = dash['data'] as Map<String, dynamic>;
        _students = (students['data'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _logout() async {
    await widget.session.logout();
    if (!mounted) return;
    widget.onLogout();
  }

  @override
  Widget build(BuildContext context) {
    final summary = _dash['summary'] as Map<String, dynamic>? ?? {};

    return Scaffold(
      appBar: AppBar(
        title: const Text('Teacher Dashboard'),
        actions: [
          IconButton(onPressed: _logout, icon: const Icon(Icons.logout)),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text('Welcome, $_userName', style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      _StatCard(label: 'Sections', value: '${_dash['sections_count'] ?? 0}'),
                      const SizedBox(width: 12),
                      _StatCard(label: 'Students', value: '${_dash['students_count'] ?? 0}'),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      _StatCard(label: 'Open sessions', value: '${_dash['open_sessions'] ?? 0}'),
                      const SizedBox(width: 12),
                      _StatCard(
                        label: 'Attendance rate',
                        value: '${summary['rate'] ?? 0}%',
                      ),
                    ],
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                  ],
                  const SizedBox(height: 20),
                  Text('Quick actions', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  _ActionCard(
                    icon: Icons.fact_check_outlined,
                    title: 'Attendance',
                    subtitle: 'Open sessions and mark students',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => TeacherAttendanceScreen(api: widget.api),
                      ),
                    ),
                  ),
                  _ActionCard(
                    icon: Icons.person_add_alt_1,
                    title: 'Enrollment requests',
                    subtitle: '${_dash['pending_enrollment'] ?? 0} pending',
                    badge: (_dash['pending_enrollment'] as int? ?? 0) > 0
                        ? '${_dash['pending_enrollment']}'
                        : null,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => TeacherEnrollmentScreen(api: widget.api),
                      ),
                    ),
                  ),
                  _ActionCard(
                    icon: Icons.mail_outline,
                    title: 'Explanation letters',
                    subtitle: '${_dash['pending_excuse'] ?? 0} pending',
                    badge: (_dash['pending_excuse'] as int? ?? 0) > 0
                        ? '${_dash['pending_excuse']}'
                        : null,
                    onTap: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => TeacherExcuseScreen(api: widget.api),
                        ),
                      );
                      _load();
                    },
                  ),
                  _ActionCard(
                    icon: Icons.face_retouching_natural,
                    title: 'Biometric photos',
                    subtitle: '${_dash['pending_biometric'] ?? 0} pending review',
                    badge: (_dash['pending_biometric'] as int? ?? 0) > 0
                        ? '${_dash['pending_biometric']}'
                        : null,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => TeacherBiometricScreen(api: widget.api),
                      ),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text('My students', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  if (_students.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 16),
                      child: Text('No students assigned to your sections yet.'),
                    ),
                  ..._students.map((student) {
                    final name = '${student['first_name']} ${student['last_name']}';
                    return Card(
                      child: ListTile(
                        title: Text(name),
                        subtitle: Text(student['section']?.toString() ?? 'No section'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => ChildDetailScreen(
                              api: widget.api,
                              studentId: student['id'] as int,
                              studentName: name,
                            ),
                          ),
                        ),
                      ),
                    );
                  }),
                ],
              ),
            ),
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.bodySmall),
              const SizedBox(height: 4),
              Text(value, style: Theme.of(context).textTheme.headlineSmall),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  const _ActionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.badge,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: Icon(icon, color: Theme.of(context).colorScheme.primary),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: badge != null
            ? CircleAvatar(
                radius: 12,
                child: Text(badge!, style: const TextStyle(fontSize: 11)),
              )
            : const Icon(Icons.chevron_right),
        onTap: onTap,
      ),
    );
  }
}
