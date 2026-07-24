import 'package:flutter/material.dart';

import '../../services/api_client.dart';

class ParentExcuseScreen extends StatefulWidget {
  const ParentExcuseScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ParentExcuseScreen> createState() => _ParentExcuseScreenState();
}

class _ParentExcuseScreenState extends State<ParentExcuseScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _items = [];
  final Map<int, TextEditingController> _letters = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    for (final c in _letters.values) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await widget.api.get('/parent/excuse-requests');
      final items = (response['data'] as List).cast<Map<String, dynamic>>();
      setState(() {
        _items = items;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  TextEditingController _controllerFor(int id) {
    return _letters.putIfAbsent(id, TextEditingController.new);
  }

  Future<void> _submit(int id) async {
    final body = _controllerFor(id).text.trim();
    if (body.length < 10) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Write at least 10 characters.')),
      );
      return;
    }
    try {
      await widget.api.post('/parent/excuse-requests/$id', {'letter_body': body});
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Explanation letter submitted.')),
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
                      child: Text('No explanation letter requests yet.'),
                    ),
                  ..._items.map((item) {
                    final id = item['id'] as int;
                    final status = item['status'] as String? ?? '';
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(item['student']?.toString() ?? 'Student',
                                style: Theme.of(context).textTheme.titleMedium),
                            Text('Status: $status'),
                            Text('Streak: ${item['streak_count']} consecutive absent/late'),
                            if (status == 'awaiting_letter') ...[
                              const SizedBox(height: 8),
                              TextField(
                                controller: _controllerFor(id),
                                maxLines: 4,
                                decoration: const InputDecoration(
                                  border: OutlineInputBorder(),
                                  hintText: 'Explain why your child was absent or late…',
                                ),
                              ),
                              const SizedBox(height: 8),
                              FilledButton(
                                onPressed: () => _submit(id),
                                child: const Text('Submit letter'),
                              ),
                            ],
                            if (item['letter_body'] != null && status != 'awaiting_letter')
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(item['letter_body'].toString()),
                              ),
                            if (item['notes'] != null)
                              Text('Teacher note: ${item['notes']}',
                                  style: const TextStyle(color: Colors.grey)),
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
