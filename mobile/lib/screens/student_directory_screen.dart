import 'package:flutter/material.dart';
import '../services/api_client.dart';
import 'child_detail_screen.dart';

class StudentDirectoryScreen extends StatefulWidget {
  const StudentDirectoryScreen({
    super.key,
    required this.api,
    this.reports = false,
  });
  final ApiClient api;
  final bool reports;
  @override
  State<StudentDirectoryScreen> createState() => _StudentDirectoryScreenState();
}

class _StudentDirectoryScreenState extends State<StudentDirectoryScreen> {
  List<Map<String, dynamic>> _students = [];
  bool _loading = true;
  String? _error;
  String _query = '';
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
      final result = await widget.api.get('/students');
      if (!mounted) {
        return;
      }
      setState(() {
        _students = (result['data'] as List).cast<Map<String, dynamic>>();
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _error = 'Unable to load students. Please try again.';
      });
    } finally {
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final students = _students.where(
      (s) => '${s['first_name']} ${s['last_name']} ${s['lrn']} ${s['section']}'
          .toLowerCase()
          .contains(_query.toLowerCase()),
    );
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.reports ? 'Student reports' : 'Attendance'),
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              widget.reports
                  ? 'Select a student to view attendance totals, rate, and recent records.'
                  : 'Select your child to view attendance and time-in / time-out records.',
            ),
            const SizedBox(height: 16),
            TextField(
              decoration: const InputDecoration(
                labelText: 'Search name, LRN, or section',
                prefixIcon: Icon(Icons.search),
              ),
              onChanged: (value) => setState(() => _query = value),
            ),
            const SizedBox(height: 16),
            if (_loading)
              const Center(child: CircularProgressIndicator())
            else if (_error != null) ...[
              Text(_error!),
              TextButton(onPressed: _load, child: const Text('Retry')),
            ] else if (students.isEmpty)
              const Text('No matching students.')
            else
              ...students.map((student) {
                final name = '${student['first_name']} ${student['last_name']}';
                return Card(
                  child: ListTile(
                    leading: const CircleAvatar(
                      child: Icon(Icons.person_outline),
                    ),
                    title: Text(name),
                    subtitle: Text(
                      '${student['section'] ?? 'No section'} • LRN: ${student['lrn'] ?? '—'}',
                    ),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute<void>(
                        builder: (_) => ChildDetailScreen(
                          api: widget.api,
                          studentId: student['id'] as int,
                          studentName: name,
                        ),
                      ),
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
