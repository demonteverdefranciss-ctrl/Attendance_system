import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:flutter_svg/flutter_svg.dart';

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
  bool _showGuide = true;

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
        const SnackBar(
          content: Text('Select 1–3 photos and acknowledge consent.'),
        ),
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
        fields: {'student_id': '$studentId', 'consent_acknowledged': '1'},
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
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
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
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              const Expanded(
                                child: Text(
                                  'Photo upload walkthrough',
                                  style: TextStyle(fontWeight: FontWeight.w700),
                                ),
                              ),
                              TextButton(
                                onPressed: () =>
                                    setState(() => _showGuide = !_showGuide),
                                child: Text(
                                  _showGuide ? 'Hide steps' : 'Show steps',
                                ),
                              ),
                            ],
                          ),
                          Semantics(
                            image: true,
                            label:
                                'Photo example: use a clear, front-facing head-and-shoulders portrait; avoid distant or full-body photos.',
                            child: SvgPicture.asset(
                              'assets/illustrations/face-photo-guide.svg',
                              fit: BoxFit.contain,
                              width: double.infinity,
                            ),
                          ),
                          if (_showGuide) ...[
                            const SizedBox(height: 8),
                            _WalkthroughStep(
                              number: '1',
                              title: 'Choose a clear front-facing photo',
                              description:
                                  'Use a portrait where the face is visible and the background is simple.',
                            ),
                            const SizedBox(height: 8),
                            _WalkthroughStep(
                              number: '2',
                              title: 'Avoid full-body or blurry shots',
                              description:
                                  'Photos with no face, sunglasses, or poor lighting may be rejected.',
                            ),
                            const SizedBox(height: 8),
                            _WalkthroughStep(
                              number: '3',
                              title: 'Review consent and submit',
                              description:
                                  'Confirm the consent notice and send the photo for teacher review.',
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Upload 1–3 photos of your child\'s face (JPEG/PNG). Full-body pictures and photos with no face are rejected. A teacher then confirms it is the correct student.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: Colors.grey.shade700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  if (_error != null)
                    Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_children.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text(
                        'No linked children yet. Enroll a child first.',
                      ),
                    ),
                  ..._children.map((child) {
                    final id = child['id'] as int;
                    final submission =
                        child['biometric_submission'] as Map<String, dynamic>?;
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
                                'Status: ${submission['enrollment_status'] ?? submission['status']} · ${submission['created_at'] ?? ''}',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                              if (submission['system_validated'] == true)
                                Text(
                                  'System validated',
                                  style: TextStyle(
                                    color: Colors.blue.shade700,
                                    fontSize: 12,
                                  ),
                                ),
                              if (submission['notes'] != null)
                                Text(
                                  'Teacher note: ${submission['notes']}',
                                  style: TextStyle(
                                    color: Colors.grey.shade700,
                                    fontSize: 12,
                                  ),
                                ),
                            ],
                            if (!canUpload)
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(
                                  submission?['status'] == 'approved'
                                      ? 'Photos approved. The school will import them for face enrollment.'
                                      : 'The system already accepted these photos. Waiting for teacher confirmation.',
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              )
                            else ...[
                              const SizedBox(height: 8),
                              OutlinedButton.icon(
                                onPressed: uploading
                                    ? null
                                    : () => _pickPhotos(id),
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
                                    : (v) => setState(
                                        () => _consent[id] = v ?? false,
                                      ),
                                controlAffinity:
                                    ListTileControlAffinity.leading,
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
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : const Text('Submit photos'),
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

class _WalkthroughStep extends StatelessWidget {
  const _WalkthroughStep({
    required this.number,
    required this.title,
    required this.description,
  });

  final String number;
  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 28,
          height: 28,
          decoration: BoxDecoration(
            color: Theme.of(context).colorScheme.primaryContainer,
            borderRadius: BorderRadius.circular(14),
          ),
          child: Center(
            child: Text(
              number,
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: Theme.of(context).colorScheme.primary,
              ),
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.w600)),
              const SizedBox(height: 2),
              Text(
                description,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
