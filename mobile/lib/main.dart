import 'package:flutter/material.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'services/api_client.dart';
import 'services/session_service.dart';

void main() {
  runApp(const AttendanceParentApp());
}

class AttendanceParentApp extends StatefulWidget {
  const AttendanceParentApp({super.key});

  @override
  State<AttendanceParentApp> createState() => _AttendanceParentAppState();
}

class _AttendanceParentAppState extends State<AttendanceParentApp> {
  final _api = ApiClient();
  late final _session = SessionService(_api);
  bool _ready = false;
  bool _loggedIn = false;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final loggedIn = await _session.restore();
    setState(() {
      _loggedIn = loggedIn;
      _ready = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!_ready) {
      return const MaterialApp(
        home: Scaffold(body: Center(child: CircularProgressIndicator())),
      );
    }

    return MaterialApp(
      title: 'Bigaa ES Attendance',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF1D4ED8),
          brightness: Brightness.light,
          primary: const Color(0xFF1D4ED8),
          surface: Colors.white,
        ),
        scaffoldBackgroundColor: Colors.white,
        useMaterial3: true,
      ),
      home: _loggedIn
          ? HomeScreen(api: _api, session: _session)
          : LoginScreen(api: _api, session: _session),
    );
  }
}
