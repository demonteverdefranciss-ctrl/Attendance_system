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
                    final mode = _modeFor(id);
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
                              SegmentedButton<String>(
                                segments: const [
                                  ButtonSegment(value: 'text', label: Text('Type letter')),
                                  ButtonSegment(value: 'pdf', label: Text('Upload PDF')),
                                ],
                                selected: {mode},
                                onSelectionChanged: (value) {
                                  setState(() => _modes[id] = value.first);
                                },
                              ),
                              const SizedBox(height: 8),
                              if (mode == 'text')
                                TextField(
                                  controller: _controllerFor(id),
                                  maxLines: 4,
                                  decoration: const InputDecoration(
                                    border: OutlineInputBorder(),
                                    hintText: 'Explain why your child was absent or late…',
                                  ),
                                )
                              else
                                OutlinedButton.icon(
                                  onPressed: () => _pickPdf(id),
                                  icon: const Icon(Icons.picture_as_pdf),
                                  label: Text(_pdfNames[id] ?? 'Choose PDF'),
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
                            if (status != 'awaiting_letter') ...[
                              if (item['letter_body'] != null)
                                Padding(
                                  padding: const EdgeInsets.only(top: 8),
                                  child: Text(item['letter_body'].toString()),
                                ),
                              if (item['has_pdf'] == true)
                                const Padding(
                                  padding: EdgeInsets.only(top: 8),
                                  child: Text('PDF letter submitted.'),
                                ),
                              if (item['has_photo'] == true)
                                const Text('Supporting photo submitted.'),
                            ],
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
