import 'package:flutter/material.dart';

import '../services/api_client.dart';
import '../services/session_service.dart';
import 'child_detail_screen.dart';
import 'enrollment_screen.dart';
import 'notifications_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key, required this.api, required this.session});

  final ApiClient api;
  final SessionService session;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  String _userName = 'Parent';
  int _childrenCount = 0;
  int _unread = 0;
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

  Future<void> _logout() async {
    await widget.session.logout();
    if (!mounted) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Parent Dashboard'),
        actions: [
          IconButton(
            tooltip: 'Notifications',
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => NotificationsScreen(api: widget.api),
              ),
            ),
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
