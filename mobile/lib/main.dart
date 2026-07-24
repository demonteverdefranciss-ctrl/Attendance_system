import 'package:flutter/material.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/teacher/teacher_home_screen.dart';
import 'services/api_client.dart';
import 'services/session_service.dart';

void main() {
  runApp(const AttendanceApp());
}

class AttendanceApp extends StatefulWidget {
  const AttendanceApp({super.key});

  @override
  State<AttendanceApp> createState() => _AttendanceAppState();
}

class _AttendanceAppState extends State<AttendanceApp> {
  final _api = ApiClient();
  late final _session = SessionService(_api);
  bool _ready = false;
  bool _loggedIn = false;
  String _role = 'parent';

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final loggedIn = await _session.restore();
    final role = loggedIn ? await _session.userRole() : null;
    setState(() {
      _loggedIn = loggedIn;
      _role = role ?? 'parent';
      _ready = true;
    });
  }

  void _onLoggedIn(String role) => setState(() {
        _loggedIn = true;
        _role = role;
      });

  void _onLoggedOut() => setState(() => _loggedIn = false);

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
          ? (_role == 'teacher'
              ? TeacherHomeScreen(
                  api: _api,
                  session: _session,
                  onLogout: _onLoggedOut,
                )
              : HomeScreen(
                  api: _api,
                  session: _session,
                  onLogout: _onLoggedOut,
                ))
          : LoginScreen(
              api: _api,
              session: _session,
              onLogin: _onLoggedIn,
            ),
    );
  }
}

// Kept for widget tests.
typedef AttendanceParentApp = AttendanceApp;
