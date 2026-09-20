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
  String _period = 'all';
  int _loadVersion = 0;
  String? _error;
  Map<String, dynamic>? _summary;
  List<Map<String, dynamic>> _records = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final version = ++_loadVersion;
    final period = _period;
    setState(() {
      _loading = true;
      _error = null;
      _summary = null;
      _records = [];
    });

    try {
      final results = await Future.wait([
        widget.api.get('/analytics/student/${widget.studentId}?period=$period'),
        widget.api.get(
          '/students/${widget.studentId}/attendance?period=$period',
        ),
      ]);
      if (!mounted || version != _loadVersion) return;
      final summary = results[0];
      final attendance = results[1];
      setState(() {
        _summary = summary['data'] as Map<String, dynamic>;
        _records = (attendance['data'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted || version != _loadVersion) return;
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
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Wrap(
              spacing: 8,
              children: [
                for (final option in const {
                  'all': 'All time',
                  'week': '1 week',
                  'month': '1 month',
                }.entries)
                  ChoiceChip(
                    label: Text(option.value),
                    selected: _period == option.key,
                    onSelected: (_) {
                      setState(() => _period = option.key);
                      _load();
                    },
                  ),
              ],
            ),
            const Text(
              '1 week: last 7 days. 1 month: last 30 days, including today.',
            ),
            const SizedBox(height: 16),
            if (_loading) const LinearProgressIndicator(),
            if (_error != null)
              Text(_error!, style: const TextStyle(color: Colors.red)),
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
            Text(
              'Recent records',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            if (!_loading && _error == null && _records.isEmpty)
              const Text('No attendance records for this period.'),
            ..._records.map(
              (r) => Card(
                child: ListTile(
                  title: Text('${r['date']} · ${r['status']}'),
                  subtitle: Text(
                    'In: ${r['time_in'] ?? '—'} · Out: ${r['time_out'] ?? '—'}',
                  ),
                ),
              ),
            ),
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
