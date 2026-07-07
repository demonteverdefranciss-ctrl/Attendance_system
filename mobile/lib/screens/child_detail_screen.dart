import 'package:flutter/material.dart';

import '../services/api_client.dart';

class ChildDetailScreen extends StatefulWidget {
  const ChildDetailScreen({
    super.key,
    required this.api,
    required this.studentId,
    required this.studentName,
  });

  final ApiClient api;
  final int studentId;
  final String studentName;

  @override
  State<ChildDetailScreen> createState() => _ChildDetailScreenState();
}

class _ChildDetailScreenState extends State<ChildDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _summary;
  List<Map<String, dynamic>> _records = [];

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
      final summary = await widget.api.get('/analytics/student/${widget.studentId}');
      final attendance = await widget.api.get('/students/${widget.studentId}/attendance');
      setState(() {
        _summary = summary['data'] as Map<String, dynamic>;
        _records = (attendance['data'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.studentName)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_summary != null) ...[
                    Text('Attendance rate: ${_summary!['attendance_rate']}%'),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _Chip(label: 'Present', value: '${_summary!['present']}'),
                        _Chip(label: 'Late', value: '${_summary!['late']}'),
                        _Chip(label: 'Absent', value: '${_summary!['absent']}'),
                        _Chip(label: 'Excused', value: '${_summary!['excused']}'),
                      ],
                    ),
                  ],
                  const SizedBox(height: 20),
                  Text('Recent records', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 8),
                  if (_records.isEmpty)
                    const Text('No attendance records yet.'),
                  ..._records.map((r) => Card(
                        child: ListTile(
                          title: Text('${r['date']} · ${r['status']}'),
                          subtitle: Text(
                            'In: ${r['time_in'] ?? '—'} · Out: ${r['time_out'] ?? '—'}',
                          ),
                        ),
                      )),
                ],
              ),
            ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Chip(label: Text('$label: $value'));
  }
}
