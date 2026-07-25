import 'package:flutter/material.dart';

import '../services/api_client.dart';

class EnrollmentScreen extends StatefulWidget {
  const EnrollmentScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<EnrollmentScreen> createState() => _EnrollmentScreenState();
}

class _EnrollmentScreenState extends State<EnrollmentScreen> {
  final _formKey = GlobalKey<FormState>();
  final _lrn = TextEditingController();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _gradeLevel = TextEditingController();
  final _relationship = TextEditingController();
  String? _gender;
  bool _loading = false;
  bool _listLoading = true;
  String? _error;
  String? _success;
  List<Map<String, dynamic>> _requests = [];

  @override
  void initState() {
    super.initState();
    _loadRequests();
  }

  @override
  void dispose() {
    _lrn.dispose();
    _firstName.dispose();
    _lastName.dispose();
    _gradeLevel.dispose();
    _relationship.dispose();
    super.dispose();
  }

  Future<void> _loadRequests() async {
    setState(() => _listLoading = true);
    try {
      final response = await widget.api.get('/parent/enrollment-requests');
      setState(() {
        _requests = (response['data'] as List).cast<Map<String, dynamic>>();
        _listLoading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _listLoading = false;
      });
    }
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _loading = true;
      _error = null;
      _success = null;
    });

    try {
      await widget.api.post('/parent/enrollment-requests', {
        'lrn': _lrn.text.trim(),
        'first_name': _firstName.text.trim(),
        'last_name': _lastName.text.trim(),
        'gender': _gender,
        'grade_level': _gradeLevel.text.trim().isEmpty ? null : _gradeLevel.text.trim(),
        'relationship':
            _relationship.text.trim().isEmpty ? null : _relationship.text.trim(),
      });
      _lrn.clear();
      _firstName.clear();
      _lastName.clear();
      _gradeLevel.clear();
      _relationship.clear();
      setState(() {
        _gender = null;
        _success = 'Request submitted for teacher verification.';
        _loading = false;
      });
      _loadRequests();
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
      appBar: AppBar(title: const Text('Enroll Child')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Submit your child’s full details for teacher verification before linking.',
          ),
          const SizedBox(height: 16),
          Form(
            key: _formKey,
            child: Column(
              children: [
                TextFormField(
                  controller: _lrn,
                  decoration: const InputDecoration(
                    labelText: 'Student LRN *',
                    border: OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'LRN required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _firstName,
                  decoration: const InputDecoration(
                    labelText: 'First name *',
                    border: OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'First name required' : null,
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _lastName,
                  decoration: const InputDecoration(
                    labelText: 'Last name *',
                    border: OutlineInputBorder(),
                  ),
                  validator: (v) =>
                      (v == null || v.trim().isEmpty) ? 'Last name required' : null,
                ),
                const SizedBox(height: 12),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Gender (optional)',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
                const SizedBox(height: 6),
                SegmentedButton<String>(
                  emptySelectionAllowed: true,
                  segments: const [
                    ButtonSegment(value: 'male', label: Text('Male')),
                    ButtonSegment(value: 'female', label: Text('Female')),
                  ],
                  selected: _gender == null ? <String>{} : {_gender!},
                  onSelectionChanged: (set) {
                    setState(() => _gender = set.isEmpty ? null : set.first);
                  },
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _gradeLevel,
                  decoration: const InputDecoration(
                    labelText: 'Grade level (optional)',
                    border: OutlineInputBorder(),
                    hintText: 'e.g. Grade 6',
                  ),
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _relationship,
                  decoration: const InputDecoration(
                    labelText: 'Relationship (optional)',
                    border: OutlineInputBorder(),
                    hintText: 'e.g. Mother, Father, Guardian',
                  ),
                ),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: const TextStyle(color: Colors.red)),
                ],
                if (_success != null) ...[
                  const SizedBox(height: 12),
                  Text(_success!, style: const TextStyle(color: Colors.green)),
                ],
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Submit request'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          Text('Your requests', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          if (_listLoading) const Center(child: CircularProgressIndicator()),
          if (!_listLoading && _requests.isEmpty) const Text('No requests yet.'),
          ..._requests.map(
            (r) => Card(
              child: ListTile(
                title: Text(r['student']?.toString() ?? 'LRN ${r['lrn']}'),
                subtitle: Text('${r['status']} · ${r['created_at'] ?? ''}'),
                trailing: r['notes'] != null ? const Icon(Icons.note_alt_outlined) : null,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
