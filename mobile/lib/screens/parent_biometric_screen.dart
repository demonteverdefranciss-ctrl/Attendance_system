import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../services/api_client.dart';

class ParentBiometricScreen extends StatefulWidget {
  const ParentBiometricScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ParentBiometricScreen> createState() => _ParentBiometricScreenState();
}

class _ParentBiometricScreenState extends State<ParentBiometricScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _children = [];
  final Map<int, List<XFile>> _picked = {};
  final Map<int, bool> _consent = {};
  final Map<int, bool> _uploading = {};
  final _picker = ImagePicker();

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
      final response = await widget.api.get('/parent/children');
      setState(() {
        _children = (response['data'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _pickPhotos(int studentId) async {
    final files = await _picker.pickMultiImage(imageQuality: 85);
    if (files.isEmpty) return;
    setState(() {
      _picked[studentId] = files.take(3).toList();
    });
  }

  Future<void> _submit(int studentId) async {
    final files = _picked[studentId] ?? [];
    final consent = _consent[studentId] ?? false;
    if (files.isEmpty || !consent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Select 1–3 photos and acknowledge consent.')),
      );
      return;
    }

    setState(() => _uploading[studentId] = true);
    try {
      final multipartFiles = <http.MultipartFile>[];
      for (var i = 0; i < files.length; i++) {
        multipartFiles.add(
          await http.MultipartFile.fromPath(
            'photos[$i]',
            files[i].path,
            filename: files[i].name,
          ),
        );
      }

      await widget.api.postMultipart(
        '/parent/biometric-photos',
        fields: {
          'student_id': '$studentId',
          'consent_acknowledged': '1',
        },
        multipartFiles: multipartFiles,
      );

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Photos submitted for teacher review.')),
      );
      setState(() {
        _picked.remove(studentId);
        _consent[studentId] = false;
      });
      _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _uploading[studentId] = false);
    }
  }

  bool _canUpload(Map<String, dynamic>? submission) {
    if (submission == null) return true;
    return submission['status'] == 'rejected';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Face photos')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  Text(
                    'Upload 1–3 clear front-facing photos (JPEG/PNG). A teacher must approve them before face enrollment.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey.shade700),
                  ),
                  const SizedBox(height: 12),
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_children.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No linked children yet. Enroll a child first.'),
                    ),
                  ..._children.map((child) {
                    final id = child['id'] as int;
                    final submission = child['biometric_submission'] as Map<String, dynamic>?;
                    final canUpload = _canUpload(submission);
                    final files = _picked[id] ?? [];
                    final uploading = _uploading[id] ?? false;

                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              child['name']?.toString() ?? 'Student',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(
                              'LRN ${child['lrn']} · ${child['section']}',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            const SizedBox(height: 6),
                            Text(
                              child['consent_biometric'] == true
                                  ? 'Consent on file'
                                  : 'No consent yet',
                              style: TextStyle(
                                fontSize: 12,
                                color: child['consent_biometric'] == true
                                    ? Colors.green.shade700
                                    : Colors.grey.shade700,
                              ),
                            ),
                            if (submission != null) ...[
                              const SizedBox(height: 8),
                              Text(
                                'Status: ${submission['status']} · ${submission['created_at'] ?? ''}',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              if (submission['notes'] != null)
                                Text(
                                  'Teacher note: ${submission['notes']}',
                                  style: TextStyle(color: Colors.grey.shade700, fontSize: 12),
                                ),
                            ],
                            if (!canUpload)
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(
                                  submission?['status'] == 'approved'
                                      ? 'Photos approved. The school will import them for face enrollment.'
                                      : 'Your submission is pending teacher review.',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              )
                            else ...[
                              const SizedBox(height: 8),
                              OutlinedButton.icon(
                                onPressed: uploading ? null : () => _pickPhotos(id),
                                icon: const Icon(Icons.photo_library_outlined),
                                label: Text(
                                  files.isEmpty
                                      ? 'Choose photos'
                                      : '${files.length} photo(s) selected',
                                ),
                              ),
                              CheckboxListTile(
                                contentPadding: EdgeInsets.zero,
                                value: _consent[id] ?? false,
                                onChanged: uploading
                                    ? null
                                    : (v) => setState(() => _consent[id] = v ?? false),
                                controlAffinity: ListTileControlAffinity.leading,
                                title: const Text(
                                  'I consent to the collection and use of my child’s biometric data (face photos) for school attendance under RA 10173.',
                                  style: TextStyle(fontSize: 12),
                                ),
                              ),
                              FilledButton(
                                onPressed: uploading ? null : () => _submit(id),
                                child: uploading
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(strokeWidth: 2),
                                      )
                                    : const Text('Submit photos for review'),
                              ),
                            ],
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
