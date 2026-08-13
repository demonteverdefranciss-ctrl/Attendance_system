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
    try {
      final path = approve
          ? '/teacher/excuse-requests/$id/approve'
          : '/teacher/excuse-requests/$id/reject';
      await widget.api.post(path);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(approve ? 'Approved (excused).' : 'Rejected.')),
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
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_items.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No pending explanation letters.'),
                    ),
                  ..._items.map((item) {
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item['student']?.toString() ?? 'Student',
                                style: Theme.of(context).textTheme.titleMedium),
                            Text('LRN: ${item['lrn']} · ${item['section']}'),
                            Text('Parent: ${item['guardian'] ?? '—'}'),
                            const SizedBox(height: 8),
                            if ((item['letter_body'] as String?)?.isNotEmpty == true)
                              Text(item['letter_body'].toString(),
                                  style: const TextStyle(fontStyle: FontStyle.italic))
                            else
                              const Text('No typed letter — a PDF was uploaded.',
                                  style: TextStyle(fontStyle: FontStyle.italic)),
                            if (item['has_pdf'] == true) const Text('PDF letter attached.'),
                            if (item['has_photo'] == true) const Text('Supporting photo attached.'),
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
