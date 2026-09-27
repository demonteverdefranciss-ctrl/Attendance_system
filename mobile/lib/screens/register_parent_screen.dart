import 'package:flutter/material.dart';
import '../services/api_client.dart';

class RegisterParentScreen extends StatefulWidget {
  const RegisterParentScreen({super.key, required this.api});
  final ApiClient api;

  @override
  State<RegisterParentScreen> createState() => _RegisterParentScreenState();
}

class _RegisterParentScreenState extends State<RegisterParentScreen> {
  final _form = GlobalKey<FormState>();
  final _fields = {
    for (final key in [
      'first_name',
      'last_name',
      'phone',
      'email',
      'username',
      'password',
      'password_confirmation',
    ])
      key: TextEditingController(),
  };
  bool _busy = false;
  bool _showPassword = false;
  String? _error;

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = <String, dynamic>{};
      for (final entry in _fields.entries) {
        final value = entry.key.startsWith('password')
            ? entry.value.text
            : entry.value.text.trim();
        data[entry.key] = value.isEmpty ? null : value;
      }
      await widget.api.post('/auth/register/parent', data);
      if (!mounted) return;
      Navigator.of(context).pop(_fields['username']!.text.trim());
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e is ApiException
            ? e.message
            : 'Unable to connect. Please try again.';
      });
    }
  }

  Widget _field(
    String key,
    String label, {
    bool optional = false,
    TextInputType? keyboard,
  }) {
    final password = key.startsWith('password');
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: TextFormField(
        controller: _fields[key],
        enabled: !_busy,
        obscureText: password && !_showPassword,
        autocorrect: !password && key != 'username' && key != 'email',
        enableSuggestions: !password,
        keyboardType: keyboard,
        textInputAction: key == 'password_confirmation'
            ? TextInputAction.done
            : TextInputAction.next,
        onFieldSubmitted: key == 'password_confirmation'
            ? (_) {
                if (!_busy) _submit();
              }
            : null,
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
        ),
        validator: (value) {
          final text = password ? (value ?? '') : (value ?? '').trim();
          if (!optional && text.isEmpty) return '$label is required';
          if (key == 'password' && text.length < 8) {
            return 'Use at least 8 characters';
          }
          if (key == 'password_confirmation' &&
              text != _fields['password']!.text) {
            return 'Passwords do not match';
          }
          return null;
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(title: const Text('Parent registration')),
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 480),
                child: Form(
                  key: _form,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Image.asset('assets/branding/bigaa_logo.png', height: 72),
                      const SizedBox(height: 16),
                      Text(
                        'Create your parent account',
                        style: Theme.of(context).textTheme.titleLarge,
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'After signing in, use Enrollment to register your child for teacher verification.',
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 24),
                      _field('first_name', 'First name'),
                      _field('last_name', 'Last name'),
                      _field(
                        'phone',
                        'Phone (optional)',
                        optional: true,
                        keyboard: TextInputType.phone,
                      ),
                      _field(
                        'email',
                        'Email (optional)',
                        optional: true,
                        keyboard: TextInputType.emailAddress,
                      ),
                      _field('username', 'Username'),
                      _field('password', 'Password'),
                      _field('password_confirmation', 'Confirm password'),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        title: const Text('Show passwords'),
                        value: _showPassword,
                        onChanged: _busy
                            ? null
                            : (value) => setState(
                                () => _showPassword = value ?? false,
                              ),
                      ),
                      if (_error != null)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: Text(
                            _error!,
                            style: TextStyle(
                              color: Theme.of(context).colorScheme.error,
                            ),
                          ),
                        ),
                      FilledButton(
                        onPressed: _busy ? null : _submit,
                        child: _busy
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text('Create account'),
                      ),
                      TextButton(
                        onPressed: _busy
                            ? null
                            : () => Navigator.of(context).pop(),
                        child: const Text('Already registered? Sign in'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
