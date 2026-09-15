import 'package:flutter/material.dart';
import '../services/api_client.dart';

class NoClassDaysScreen extends StatefulWidget {
  const NoClassDaysScreen({super.key, required this.api});
  final ApiClient api;
  @override
  State<NoClassDaysScreen> createState() => _NoClassDaysScreenState();
}

class _NoClassDaysScreenState extends State<NoClassDaysScreen> {
  Map<String, dynamic> _days = {};
  bool _loading = true;
  String? _error;
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
      final response = await widget.api.get('/no-class-days');
      if (!mounted) return;
      setState(() => _days = response['data'] as Map<String, dynamic>);
    } catch (_) {
      if (!mounted) return;
      setState(
        () => _error = 'Unable to load no-class days. Please try again.',
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('No-class days')),
    body: RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'School holidays and class suspensions',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 8),
          const Text(
            'These dates follow the school calendar shown on the web portal.',
          ),
          const SizedBox(height: 20),
          if (_loading)
            const Center(child: CircularProgressIndicator())
          else if (_error != null) ...[
            Text(_error!),
            TextButton(onPressed: _load, child: const Text('Retry')),
          ] else ...[
            for (final group in ['upcoming', 'recent']) ...[
              Text(
                group == 'upcoming' ? 'Upcoming' : 'Recent',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              if ((_days[group] as List? ?? []).isEmpty)
                const Padding(
                  padding: EdgeInsets.only(bottom: 16),
                  child: Text('No no-class days listed.'),
                ),
              for (final day in (_days[group] as List? ?? []))
                Card(
                  child: ListTile(
                    leading: const Icon(Icons.event_busy_outlined),
                    title: Text('${day['name']}'),
                    subtitle: Text('${day['weekday']}, ${day['label']}'),
                    trailing: day['date'] == _days['today']
                        ? const Chip(label: Text('Today'))
                        : null,
                  ),
                ),
              const SizedBox(height: 16),
            ],
          ],
        ],
      ),
    ),
  );
}
