import 'package:flutter/material.dart';

import '../../services/api_client.dart';

class TeacherEnrollmentScreen extends StatefulWidget {
  const TeacherEnrollmentScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TeacherEnrollmentScreen> createState() => _TeacherEnrollmentScreenState();
}

class _TeacherEnrollmentScreenState extends State<TeacherEnrollmentScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _requests = [];
  List<Map<String, dynamic>> _sections = [];

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
      final response = await widget.api.get('/teacher/enrollment-requests');
      final data = response['data'] as Map<String, dynamic>;
      setState(() {
        _requests = (data['requests'] as List).cast<Map<String, dynamic>>();
        _sections = (data['sections'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _approve(Map<String, dynamic> request) async {
    int? sectionId;
    if (request['is_new_student'] == true) {
      sectionId = await _pickSection();
      if (sectionId == null) return;
    }

    try {
      final body = <String, dynamic>{};
      if (sectionId != null) {
        body['section_id'] = sectionId;
      }
      await widget.api.post('/teacher/enrollment-requests/${request['id']}/approve', body);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enrollment approved.')),
      );
      _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _reject(Map<String, dynamic> request) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Reject request?'),
        content: Text('Reject enrollment for ${request['student']}?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Reject')),
        ],
      ),
    );
    if (confirm != true) return;

    try {
      await widget.api.post('/teacher/enrollment-requests/${request['id']}/reject');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enrollment rejected.')),
      );
      _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<int?> _pickSection() async {
    if (_sections.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No sections available for assignment.')),
      );
      return null;
    }

    return showDialog<int>(
      context: context,
      builder: (ctx) => SimpleDialog(
        title: const Text('Assign section'),
        children: _sections
            .map(
              (section) => SimpleDialogOption(
                onPressed: () => Navigator.pop(ctx, section['id'] as int),
                child: Text(section['label'] as String),
              ),
            )
            .toList(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Enrollment requests')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_requests.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No pending enrollment requests.'),
                    ),
                  ..._requests.map((request) {
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              request['student'] as String? ?? 'Unknown',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            const SizedBox(height: 4),
                            Text('LRN: ${request['lrn']}'),
                            Text('Section: ${request['section']}'),
                            if (request['guardian'] != null)
                              Text('Parent: ${request['guardian']}'),
                            if (request['relationship'] != null)
                              Text('Relationship: ${request['relationship']}'),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                FilledButton(
                                  onPressed: () => _approve(request),
                                  child: const Text('Approve'),
                                ),
                                const SizedBox(width: 8),
                                OutlinedButton(
                                  onPressed: () => _reject(request),
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
