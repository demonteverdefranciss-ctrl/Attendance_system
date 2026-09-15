import 'package:flutter/material.dart';

import '../../services/api_client.dart';

class TeacherExcuseScreen extends StatefulWidget {
  const TeacherExcuseScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TeacherExcuseScreen> createState() => _TeacherExcuseScreenState();
}

class _TeacherExcuseScreenState extends State<TeacherExcuseScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];

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
      final response = await widget.api.get('/teacher/excuse-requests');
      setState(() {
        _items = (response['data'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _review(int id, bool approve) async {
    final controller = TextEditingController();
    final notes = await showDialog<String?>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(approve ? 'Accept letter' : 'Reject letter'),
        content: TextField(
          controller: controller,
          maxLines: 3,
          maxLength: 500,
          decoration: InputDecoration(
            border: const OutlineInputBorder(),
            hintText: approve
                ? 'Optional note for the parent'
                : 'Optional rejection reason for the parent',
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, controller.text.trim()),
            child: Text(approve ? 'Accept' : 'Reject'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (notes == null) return;

    try {
      final path = approve
          ? '/teacher/excuse-requests/$id/approve'
          : '/teacher/excuse-requests/$id/reject';
      await widget.api.post(path, {'notes': notes.isEmpty ? null : notes});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            approve ? 'Approved (records marked excused).' : 'Rejected.',
          ),
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
      appBar: AppBar(title: const Text('Explanation letters')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    'Parents may explain any absence. Letters after 3 consecutive absences are a warning. '
                    'Accepting excuses those attendance records.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey.shade700),
                  ),
                  const SizedBox(height: 12),
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_items.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No pending explanation letters.'),
                    ),
                  ..._items.map((item) {
                    final streak = (item['streak_summary'] as List?)?.cast<Map<String, dynamic>>() ?? [];
                    final phone = item['guardian_phone']?.toString();
                    final required = item['is_required'] == true;
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item['student']?.toString() ?? 'Student',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text('LRN: ${item['lrn']} · ${item['section']}'),
                            Text(
                              'Parent: ${item['guardian'] ?? '—'}'
                              '${phone != null && phone.isNotEmpty ? ' · $phone' : ''}',
                            ),
                            Text(
                              required
                                  ? 'Warning: 3 consecutive absences · ${item['streak_count'] ?? 0} days · Submitted: ${item['submitted_at'] ?? '—'}'
                                  : 'Optional explanation · ${item['streak_count'] ?? 0} day(s) · Submitted: ${item['submitted_at'] ?? '—'}',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: required ? const Color(0xFF991B1B) : null,
                                    fontWeight: required ? FontWeight.w700 : null,
                                  ),
                            ),
                            if (streak.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: streak.map((row) {
                                  return Chip(
                                    label: Text(
                                      '${row['date']}: ${row['status']}',
                                      style: const TextStyle(fontSize: 11),
                                    ),
                                    visualDensity: VisualDensity.compact,
                                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                  );
                                }).toList(),
                              ),
                            ],
                            const SizedBox(height: 8),
                            Text(
                              item['letter_body']?.toString() ?? '',
                              style: const TextStyle(fontStyle: FontStyle.italic),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                FilledButton(
                                  onPressed: () => _review(item['id'] as int, true),
                                  child: const Text('Accept'),
                                ),
                                const SizedBox(width: 8),
                                OutlinedButton(
                                  onPressed: () => _review(item['id'] as int, false),
                                  child: const Text('Reject'),
                                ),
                              ],
                            ),
                          ],
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
