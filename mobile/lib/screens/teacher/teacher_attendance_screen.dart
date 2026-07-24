import 'package:flutter/material.dart';

import '../../services/api_client.dart';
import 'teacher_mark_screen.dart';

class TeacherAttendanceScreen extends StatefulWidget {
  const TeacherAttendanceScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TeacherAttendanceScreen> createState() => _TeacherAttendanceScreenState();
}

class _TeacherAttendanceScreenState extends State<TeacherAttendanceScreen> {
  bool _loading = true;
  String? _error;
  String _today = '';
  List<Map<String, dynamic>> _rows = [];

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
      final response = await widget.api.get('/teacher/attendance');
      final data = response['data'] as Map<String, dynamic>;
      setState(() {
        _today = data['today'] as String? ?? '';
        _rows = (data['rows'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _openSession(int sectionId) async {
    try {
      final response = await widget.api.post('/teacher/attendance/open', {
        'section_id': sectionId,
      });
      final data = response['data'] as Map<String, dynamic>;
      final sessionId = data['session_id'] as int;
      if (!mounted) return;
      final message = data['message'] as String?;
      if (message != null && message.isNotEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
      }
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => TeacherMarkScreen(api: widget.api, sessionId: sessionId),
        ),
      );
      _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Attendance')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text('Today: $_today', style: Theme.of(context).textTheme.titleMedium),
                  if (_error != null) ...[
                    const SizedBox(height: 8),
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                  ],
                  const SizedBox(height: 12),
                  if (_rows.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No sections assigned to your account.'),
                    ),
                  ..._rows.map((row) {
                    final section = row['section'] as Map<String, dynamic>;
                    final session = row['session'] as Map<String, dynamic>?;
                    final sectionId = section['id'] as int;
                    final label =
                        '${section['grade_level']} - ${section['name']} (${section['students_count']} students)';
                    final isOpen = session?['status'] == 'open';
                    final sessionId = session?['id'] as int?;

                    return Card(
                      child: ListTile(
                        title: Text(label),
                        subtitle: session == null
                            ? const Text('No session today')
                            : Text(
                                isOpen
                                    ? 'Open — ${session['present_count']} present, ${session['absent_count']} absent'
                                    : 'Closed — ${session['present_count']} present',
                              ),
                        trailing: isOpen
                            ? const Chip(label: Text('Open'))
                            : FilledButton(
                                onPressed: () => _openSession(sectionId),
                                child: const Text('Open'),
                              ),
                        onTap: sessionId == null
                            ? null
                            : () async {
                                await Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => TeacherMarkScreen(
                                      api: widget.api,
                                      sessionId: sessionId,
                                    ),
                                  ),
                                );
                                _load();
                              },
                      ),
                    );
                  }),
                ],
              ),
            ),
    );
  }
}
