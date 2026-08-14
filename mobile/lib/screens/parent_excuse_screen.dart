import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

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
  final Map<int, String> _modes = {};
  final Map<int, String> _pdfPaths = {};
  final Map<int, String> _pdfNames = {};
  final Map<int, String> _photoPaths = {};
  final Map<int, String> _photoNames = {};
  final ImagePicker _images = ImagePicker();

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

  String _modeFor(int id) => _modes[id] ?? 'text';

  Future<void> _pickPdf(int id) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: const ['pdf'],
    );
    final file = result?.files.single;
    if (file?.path == null) return;
    setState(() {
      _pdfPaths[id] = file!.path!;
      _pdfNames[id] = file.name;
    });
  }

  Future<void> _pickPhoto(int id) async {
    final file = await _images.pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (file == null) return;
    setState(() {
      _photoPaths[id] = file.path;
      _photoNames[id] = file.name;
    });
  }

  Future<void> _submit(int id) async {
    final mode = _modeFor(id);
    final body = _controllerFor(id).text.trim();
    final pdfPath = _pdfPaths[id];
    final photoPath = _photoPaths[id];

    if (mode == 'text' && body.length < 10) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Write at least 10 characters, or upload a PDF instead.')),
      );
      return;
    }
    if (mode == 'pdf' && pdfPath == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a PDF letter.')),
      );
      return;
    }

    final fields = <String, String>{};
    final files = <String, String>{};
    if (mode == 'text') {
      fields['letter_body'] = body;
    } else {
      files['letter_pdf'] = pdfPath!;
    }
    if (photoPath != null) {
      files['photo'] = photoPath;
    }

    try {
      if (files.isEmpty) {
        await widget.api.post('/parent/excuse-requests/$id', Map<String, dynamic>.from(fields));
      } else {
        await widget.api.postMultipart(
          '/parent/excuse-requests/$id',
          fields: fields,
          files: files,
        );
      }
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

  String _statusLabel(String status) {
    return switch (status) {
      'awaiting_letter' => 'Needs letter',
      'pending' => 'Pending review',
      'approved' => 'Approved',
      'rejected' => 'Rejected',
      _ => status,
    };
  }

  Color _statusColor(String status) {
    return switch (status) {
      'awaiting_letter' => Colors.amber.shade800,
      'pending' => Colors.blue.shade700,
      'approved' => Colors.green.shade700,
      'rejected' => Colors.red.shade700,
      _ => Colors.grey.shade700,
    };
  }

  Color _statusBg(String status) {
    return switch (status) {
      'awaiting_letter' => Colors.amber.shade50,
      'pending' => Colors.blue.shade50,
      'approved' => Colors.green.shade50,
      'rejected' => Colors.red.shade50,
      _ => Colors.grey.shade100,
    };
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
                  Text(
                    'After 3 consecutive absences or late marks, you must submit an explanation letter. '
                    'A teacher can accept it to mark those days excused.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(color: Colors.grey.shade700),
                  ),
                  const SizedBox(height: 12),
                  if (_error != null) Text(_error!, style: const TextStyle(color: Colors.red)),
                  if (_items.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Text('No explanation letter requests yet.'),
                    ),
                  ..._items.map((item) {
                    final id = item['id'] as int;
                    final status = item['status'] as String? ?? '';
                    final streak = (item['streak_summary'] as List?)?.cast<Map<String, dynamic>>() ?? [];
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    item['student']?.toString() ?? 'Student',
                                    style: Theme.of(context).textTheme.titleMedium,
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: _statusBg(status),
                                    borderRadius: BorderRadius.circular(999),
                                  ),
                                  child: Text(
                                    _statusLabel(status),
                                    style: TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w600,
                                      color: _statusColor(status),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Streak: ${item['streak_count'] ?? 0} consecutive absent/late',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            if (streak.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 6,
                                runSpacing: 6,
                                children: streak.map((row) {
                                  return Chip(
                                    label: Text(
                                      '${row['date']}: ${row['status']}',
                                      style: const TextStyle(fontSize: 11),
                                    ),
                                    visualDensity: VisualDensity.compact,
                                    materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                  );
                                }).toList(),
                              ),
                            ],
                            if (status == 'awaiting_letter') ...[
                              const SizedBox(height: 8),
                              TextField(
                                controller: _controllerFor(id),
                                maxLines: 4,
                                maxLength: 2000,
                                decoration: const InputDecoration(
                                  border: OutlineInputBorder(),
                                  hintText: 'Explain why your child was absent or late…',
                                ),
                              const SizedBox(height: 8),
                              OutlinedButton.icon(
                                onPressed: () => _pickPhoto(id),
                                icon: const Icon(Icons.photo),
                                label: Text(_photoNames[id] ?? 'Add photo (optional)'),
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
                                child: Text(
                                  item['letter_body'].toString(),
                                  style: const TextStyle(fontStyle: FontStyle.italic),
                                ),
                              ),
                            if (item['notes'] != null)
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(
                                  'Teacher note: ${item['notes']}',
                                  style: TextStyle(color: Colors.grey.shade700),
                                ),
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
