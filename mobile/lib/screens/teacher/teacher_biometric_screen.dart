import 'dart:typed_data';

import 'package:flutter/material.dart';

import '../../services/api_client.dart';

class TeacherBiometricScreen extends StatefulWidget {
  const TeacherBiometricScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<TeacherBiometricScreen> createState() => _TeacherBiometricScreenState();
}

class _TeacherBiometricScreenState extends State<TeacherBiometricScreen> {
  bool _loading = true;
  String? _error;
  List<Map<String, dynamic>> _submissions = [];

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
      final response = await widget.api.get('/teacher/biometric-submissions');
      setState(() {
        _submissions = (response['data'] as List).cast<Map<String, dynamic>>();
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _approve(int id) async {
    try {
      await widget.api.post('/teacher/biometric-submissions/$id/approve');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Photos approved.')),
      );
      _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  Future<void> _reject(int id) async {
    try {
      await widget.api.post('/teacher/biometric-submissions/$id/reject');
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Submission rejected.')),
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
      appBar: AppBar(title: const Text('Biometric photos')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_submissions.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No pending biometric submissions.'),
                    ),
                  ..._submissions.map((submission) {
                    final photos = (submission['photos'] as List).cast<Map<String, dynamic>>();
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              submission['student'] as String? ?? 'Student',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text('LRN: ${submission['lrn']}'),
                            Text('Section: ${submission['section']}'),
                            if (submission['guardian'] != null)
                              Text('Parent: ${submission['guardian']}'),
                            const SizedBox(height: 8),
                            SizedBox(
                              height: 100,
                              child: ListView.separated(
                                scrollDirection: Axis.horizontal,
                                itemCount: photos.length,
                                separatorBuilder: (_, _) => const SizedBox(width: 8),
                                itemBuilder: (context, index) {
                                  final photo = photos[index];
                                  return _AuthPhotoThumb(
                                    api: widget.api,
                                    photoId: photo['id'] as int,
                                  );
                                },
                              ),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                FilledButton(
                                  onPressed: () => _approve(submission['id'] as int),
                                  child: const Text('Approve'),
                                ),
                                const SizedBox(width: 8),
                                OutlinedButton(
                                  onPressed: () => _reject(submission['id'] as int),
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

class _AuthPhotoThumb extends StatefulWidget {
  const _AuthPhotoThumb({required this.api, required this.photoId});

  final ApiClient api;
  final int photoId;

  @override
  State<_AuthPhotoThumb> createState() => _AuthPhotoThumbState();
}

class _AuthPhotoThumbState extends State<_AuthPhotoThumb> {
  Uint8List? _bytes;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final bytes = await widget.api.getBytes('/teacher/biometric-photos/${widget.photoId}/file');
      if (!mounted) return;
      setState(() => _bytes = Uint8List.fromList(bytes));
    } catch (_) {
      if (!mounted) return;
      setState(() => _failed = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_failed) {
      return Container(
        width: 100,
        color: Colors.grey.shade200,
        alignment: Alignment.center,
        child: const Icon(Icons.broken_image_outlined),
      );
    }
    if (_bytes == null) {
      return const SizedBox(
        width: 100,
        child: Center(child: CircularProgressIndicator()),
      );
    }
    return ClipRRect(
      borderRadius: BorderRadius.circular(8),
      child: Image.memory(_bytes!, width: 100, height: 100, fit: BoxFit.cover),
    );
  }
}
