import 'package:flutter/material.dart';

import '../../services/api_client.dart';

class TeacherMarkScreen extends StatefulWidget {
  const TeacherMarkScreen({
    super.key,
    required this.api,
    required this.sessionId,
  });

  final ApiClient api;
  final int sessionId;

  @override
  State<TeacherMarkScreen> createState() => _TeacherMarkScreenState();
}

class _TeacherMarkScreenState extends State<TeacherMarkScreen> {
  bool _loading = true;
  bool _saving = false;
  String? _error;
  Map<String, dynamic> _session = {};
  List<Map<String, dynamic>> _students = [];
  final Map<int, String> _statuses = {};

  static const _statusesList = ['present', 'late', 'absent', 'excused'];

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
      final response = await widget.api.get('/teacher/attendance/${widget.sessionId}');
      final data = response['data'] as Map<String, dynamic>;
      final students = (data['students'] as List).cast<Map<String, dynamic>>();
      final records = Map<String, dynamic>.from(data['records'] as Map? ?? {});

      final statuses = <int, String>{};
      for (final student in students) {
        final id = student['id'] as int;
        final record = records['$id'] as Map<String, dynamic>?;
        statuses[id] = record?['status'] as String? ?? 'absent';
      }

      setState(() {
        _session = data['session'] as Map<String, dynamic>;
        _students = students;
        _statuses
          ..clear()
          ..addAll(statuses);
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      final records = <String, String>{};
      for (final entry in _statuses.entries) {
        records['${entry.key}'] = entry.value;
      }
      await widget.api.post('/teacher/attendance/${widget.sessionId}/records', {
        'records': records,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Attendance saved.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _recordTimeOut(int studentId) async {
    try {
      await widget.api.post(
        '/teacher/attendance/${widget.sessionId}/students/$studentId/time-out',
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Time-out recorded.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _closeSession() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Close session?'),
        content: const Text(
          'Unmarked students will be recorded absent. Face recognition will stop when no sessions are open.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Close')),
        ],
      ),
    );
    if (confirm != true) return;

    try {
      await widget.api.post('/teacher/attendance/${widget.sessionId}/close');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Session closed.')),
      );
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Color _statusColor(String status) {
    return switch (status) {
      'present' => Colors.green,
      'late' => Colors.orange,
      'excused' => Colors.blue,
      _ => Colors.red,
    };
  }

  @override
  Widget build(BuildContext context) {
    final isOpen = _session['status'] == 'open';
    final title = _session['section'] != null
        ? '${_session['grade_level']} - ${_session['section']}'
        : 'Mark attendance';

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        actions: [
          if (isOpen)
            TextButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Save'),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : Column(
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.all(12),
                    child: Text(_error!, style: const TextStyle(color: Colors.red)),
                  ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: Row(
                    children: [
                      Chip(
                        label: Text(isOpen ? 'Session open' : 'Session closed'),
                        backgroundColor: isOpen ? Colors.green.shade50 : Colors.grey.shade200,
                      ),
                      const Spacer(),
                      if (isOpen)
                        FilledButton.tonal(
                          onPressed: _closeSession,
                          child: const Text('Close session'),
                        ),
                    ],
                  ),
                ),
                Expanded(
                  child: RefreshIndicator(
                    onRefresh: _load,
                    child: ListView.builder(
                      padding: const EdgeInsets.all(16),
                      itemCount: _students.length,
                      itemBuilder: (context, index) {
                        final student = _students[index];
                        final id = student['id'] as int;
                        final name = '${student['first_name']} ${student['last_name']}';
                        final status = _statuses[id] ?? 'absent';

                        return Card(
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(name, style: Theme.of(context).textTheme.titleSmall),
                                const SizedBox(height: 8),
                                Wrap(
                                  spacing: 6,
                                  children: _statusesList.map((value) {
                                    final selected = status == value;
                                    return ChoiceChip(
                                      label: Text(value),
                                      selected: selected,
                                      selectedColor: _statusColor(value).withValues(alpha: 0.25),
                                      onSelected: isOpen
                                          ? (v) => setState(() => _statuses[id] = value)
                                          : null,
                                    );
                                  }).toList(),
                                ),
                                if (isOpen && (status == 'present' || status == 'late')) ...[
                                  const SizedBox(height: 8),
                                  TextButton.icon(
                                    onPressed: () => _recordTimeOut(id),
                                    icon: const Icon(Icons.logout, size: 18),
                                    label: const Text('Record time-out'),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ),
              ],
            ),
    );
  }
}
