import 'package:flutter/material.dart';

import '../services/api_client.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
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
      final response = await widget.api.get('/notifications');
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

  Future<void> _markRead(int id) async {
    await widget.api.post('/notifications/$id/read');
    _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Notifications')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_items.isEmpty) const Text('No notifications yet.'),
                  ..._items.map((n) {
                    final read = n['read_at'] != null;
                    return Card(
                      child: ListTile(
                        title: Text(n['title']?.toString() ?? 'Attendance update'),
                        subtitle: Text(n['body']?.toString() ?? ''),
                        trailing: read
                            ? const Icon(Icons.done, color: Colors.green)
                            : TextButton(
                                onPressed: () => _markRead(n['id'] as int),
                                child: const Text('Read'),
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
