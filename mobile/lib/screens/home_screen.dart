import 'package:flutter/material.dart';

import '../services/api_client.dart';
import '../services/session_service.dart';
import 'child_detail_screen.dart';
import 'enrollment_screen.dart';
import 'notifications_screen.dart';
import 'parent_biometric_screen.dart';
import 'parent_excuse_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.api,
    required this.session,
    required this.onLogout,
  });

  final ApiClient api;
  final SessionService session;
  final VoidCallback onLogout;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  String _userName = 'Parent';
  int _childrenCount = 0;
  int _unread = 0;
  String _notifyPref = 'push';
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
      final dash = await widget.api.get('/parent/dashboard');
      final students = await widget.api.get('/students');
      final dashData = dash['data'] as Map<String, dynamic>;
      final studentList = (students['data'] as List).cast<Map<String, dynamic>>();

      setState(() {
        _userName = name ?? 'Parent';
        _childrenCount = dashData['children_count'] as int? ?? studentList.length;
        _unread = dashData['unread_notifications'] as int? ?? 0;
        _notifyPref = dashData['notify_pref'] as String? ?? 'push';
        _students = studentList;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _saveNotifyPref(String pref) async {
    try {
      await widget.api.post('/parent/notification-preference', {'notify_pref': pref});
      setState(() => _notifyPref = pref);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(pref == 'push' ? 'Push notifications on.' : 'Push notifications off.')),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _logout() async {
    await widget.session.logout();
    if (!mounted) return;
    widget.onLogout();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Parent Dashboard'),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () async {
              await Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => NotificationsScreen(
                    api: widget.api,
                    onOpenExcuseLetters: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ParentExcuseScreen(api: widget.api),
                        ),
                      );
                    },
                  ),
                ),
              );
              _load();
            },
            icon: Badge(
              isLabelVisible: _unread > 0,
              label: Text('$_unread'),
              child: const Icon(Icons.notifications_outlined),
            ),
          ),
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
                      _StatCard(label: 'Children', value: '$_childrenCount'),
                      const SizedBox(width: 12),
                      _StatCard(label: 'Unread', value: '$_unread'),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Card(
                    child: SwitchListTile(
                      title: const Text('Push notifications'),
                      subtitle: const Text('Attendance alerts for your children'),
                      value: _notifyPref == 'push',
                      onChanged: (on) => _saveNotifyPref(on ? 'push' : 'none'),
                    ),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                  ],
                  const SizedBox(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('My Children', style: Theme.of(context).textTheme.titleMedium),
                      TextButton.icon(
                        onPressed: () async {
                          await Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => EnrollmentScreen(api: widget.api),
                            ),
                          );
                          _load();
                        },
                        icon: const Icon(Icons.person_add_alt_1),
                        label: const Text('Enroll child'),
                      ),
                    ],
                  ),
                  TextButton.icon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ParentExcuseScreen(api: widget.api),
                      ),
                    ),
                    icon: const Icon(Icons.mail_outline),
                    label: const Text('Explanation letters'),
                  ),
                  TextButton.icon(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => ParentBiometricScreen(api: widget.api),
                      ),
                    ),
                    icon: const Icon(Icons.face_retouching_natural),
                    label: const Text('Face photos'),
                  ),
                  if (_students.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No linked children yet. Submit an LRN for teacher verification.'),
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
