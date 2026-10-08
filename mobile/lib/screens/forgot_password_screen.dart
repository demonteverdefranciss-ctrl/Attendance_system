import 'dart:async';

import 'package:flutter/material.dart';

import '../services/api_client.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, required this.api});

  final ApiClient api;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _identifier = TextEditingController();
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _codeSent = false;
  bool _loading = false;
  int _resendSeconds = 0;
  Timer? _resendTimer;
  String? _message;
  String? _error;

  @override
  void dispose() {
    _resendTimer?.cancel();
    _identifier.dispose();
    _code.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _sendCode() async {
    if (!_formKey.currentState!.validate()) return;
    await _requestCode();
  }

  Future<void> _resendCode() async {
    if (_loading || _resendSeconds > 0) return;
    _code.clear();
    await _requestCode();
  }

  Future<void> _requestCode() async {
    setState(() {
      _loading = true;
      _error = null;
      _message = null;
    });

    try {
      final result = await widget.api.post('/auth/forgot-password', {
        'identifier': _identifier.text.trim(),
      });
      if (!mounted) return;
      final data = result['data'] as Map<String, dynamic>;
      setState(() {
        _codeSent = data['status'] == 'sent';
        _message = data['message'] as String?;
        _loading = false;
        if (_codeSent) _resendSeconds = 10;
      });
      if (_codeSent) _startResendCountdown();
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    }
  }

  void _startResendCountdown() {
    _resendTimer?.cancel();
    _resendTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }

      setState(() {
        if (_resendSeconds <= 1) {
          _resendSeconds = 0;
          timer.cancel();
        } else {
          _resendSeconds--;
        }
      });
    });
  }

  Future<void> _resetPassword() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final result = await widget.api.post('/auth/reset-password', {
        'identifier': _identifier.text.trim(),
        'code': _code.text.trim(),
        'password': _password.text,
        'password_confirmation': _confirmation.text,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(result['data']['message'] as String)),
      );
      Navigator.of(context).pop();
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset(
            'assets/branding/login_background.png',
            fit: BoxFit.cover,
          ),
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xE6FFFFFF),
                  Color(0xD9EFF6FF),
                  Color(0x661E3A8A),
                ],
              ),
            ),
          ),
          SafeArea(
            child: Column(
              children: [
                Align(
                  alignment: Alignment.centerLeft,
                  child: IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.arrow_back),
                    tooltip: 'Back to sign in',
                  ),
                ),
                Expanded(
                  child: Center(
                    child: SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 420),
                        child: Card(
                          elevation: 8,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(20),
                            side: const BorderSide(color: Color(0xFFDBEAFE)),
                          ),
                          clipBehavior: Clip.antiAlias,
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Form(
                              key: _formKey,
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  const Text(
                                    'Reset password',
                                    style: TextStyle(
                                      color: Color(0xFF1E3A8A),
                                      fontSize: 20,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  const Text(
                                    'Enter your username or email. The code will be sent to the email registered to your account.',
                                  ),
                                  const SizedBox(height: 20),
                                  if (!_codeSent) ...[
                                    TextFormField(
                                      controller: _identifier,
                                      decoration: const InputDecoration(
                                        labelText: 'Username or email',
                                        border: OutlineInputBorder(),
                                      ),
                                      validator: (value) =>
                                          value == null || value.trim().isEmpty
                                          ? 'Enter your username or email'
                                          : null,
                                    ),
                                    const SizedBox(height: 12),
                                  ],
                                  if (_codeSent) ...[
                                    Row(
                                      mainAxisAlignment:
                                          MainAxisAlignment.spaceBetween,
                                      children: [
                                        const Text(
                                          '6-digit code',
                                          style: TextStyle(
                                            fontWeight: FontWeight.w500,
                                          ),
                                        ),
                                        TextButton(
                                          onPressed:
                                              _loading || _resendSeconds > 0
                                              ? null
                                              : _resendCode,
                                          child: Text(
                                            _resendSeconds > 0
                                                ? 'Resend in ${_resendSeconds}s'
                                                : 'Resend code',
                                          ),
                                        ),
                                      ],
                                    ),
                                    TextFormField(
                                      controller: _code,
                                      keyboardType: TextInputType.number,
                                      maxLength: 6,
                                      decoration: const InputDecoration(
                                        labelText: '6-digit code',
                                        border: OutlineInputBorder(),
                                        counterText: '',
                                      ),
                                      validator: (value) =>
                                          value == null || value.length != 6
                                          ? 'Enter the 6-digit code'
                                          : null,
                                    ),
                                    const SizedBox(height: 12),
                                    TextFormField(
                                      controller: _password,
                                      obscureText: true,
                                      decoration: const InputDecoration(
                                        labelText: 'New password',
                                        border: OutlineInputBorder(),
                                      ),
                                      validator: (value) =>
                                          value == null || value.length < 8
                                          ? 'Use at least 8 characters'
                                          : null,
                                    ),
                                    const SizedBox(height: 12),
                                    TextFormField(
                                      controller: _confirmation,
                                      obscureText: true,
                                      decoration: const InputDecoration(
                                        labelText: 'Confirm new password',
                                        border: OutlineInputBorder(),
                                      ),
                                      validator: (value) =>
                                          value != _password.text
                                          ? 'Passwords do not match'
                                          : null,
                                    ),
                                  ],
                                  if (_message != null) ...[
                                    const SizedBox(height: 12),
                                    Container(
                                      padding: const EdgeInsets.all(12),
                                      decoration: BoxDecoration(
                                        color: _codeSent
                                            ? Colors.green.shade50
                                            : Colors.red.shade50,
                                        border: Border.all(
                                          color: _codeSent
                                              ? Colors.green.shade300
                                              : Colors.red.shade300,
                                        ),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: Row(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Icon(
                                            _codeSent
                                                ? Icons.mark_email_read_outlined
                                                : Icons.error_outline_rounded,
                                            color: _codeSent
                                                ? Colors.green.shade800
                                                : Colors.red.shade800,
                                          ),
                                          const SizedBox(width: 8),
                                          Expanded(
                                            child: Column(
                                              crossAxisAlignment:
                                                  CrossAxisAlignment.start,
                                              children: [
                                                if (_codeSent)
                                                  const Text(
                                                    'Code sent',
                                                    style: TextStyle(
                                                      fontWeight:
                                                          FontWeight.bold,
                                                    ),
                                                  ),
                                                Text(_message!),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                  if (_error != null) ...[
                                    const SizedBox(height: 12),
                                    Text(
                                      _error!,
                                      style: const TextStyle(color: Colors.red),
                                    ),
                                  ],
                                  const SizedBox(height: 20),
                                  FilledButton(
                                    onPressed: _loading
                                        ? null
                                        : (_codeSent
                                              ? _resetPassword
                                              : _sendCode),
                                    child: _loading
                                        ? const SizedBox(
                                            width: 20,
                                            height: 20,
                                            child: CircularProgressIndicator(
                                              strokeWidth: 2,
                                            ),
                                          )
                                        : Text(
                                            _codeSent
                                                ? 'Change password'
                                                : 'Send reset code',
                                          ),
                                  ),
                                  if (_codeSent)
                                    TextButton(
                                      onPressed: _loading
                                          ? null
                                          : () => setState(() {
                                              _codeSent = false;
                                              _code.clear();
                                              _password.clear();
                                              _confirmation.clear();
                                              _message = null;
                                              _error = null;
                                            }),
                                      child: const Text(
                                        'Use a different username or email',
                                      ),
                                    ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
