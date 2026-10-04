import 'package:flutter/material.dart';

import 'screens/home_screen.dart';
import 'screens/login_screen.dart';
import 'screens/teacher/teacher_home_screen.dart';
import 'services/api_client.dart';
import 'services/session_service.dart';
import 'services/theme_service.dart';

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
  final _themeService = ThemeService();
  bool _ready = false;
  bool _loggedIn = false;
  String _role = 'parent';
  ThemePreset _themePreset = ThemePreset.defaultTheme;
  double _fontScale = 1.0;
  bool _highContrast = false;
  bool _contentManagementEnabled = true;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final loggedIn = await _session.restore();
    final role = loggedIn ? await _session.userRole() : null;
    final theme = await _themeService.load();
    final fontScale = await _themeService.loadFontScale();
    final highContrast = await _themeService.loadHighContrast();
    setState(() {
      _loggedIn = loggedIn;
      _role = role ?? 'parent';
      _themePreset = theme;
      _fontScale = fontScale;
      _highContrast = highContrast;
      _ready = true;
    });
  }

  void _onLoggedIn(String role) => setState(() {
    _loggedIn = true;
    _role = role;
  });

  void _onLoggedOut() => setState(() => _loggedIn = false);

  Future<void> _onThemeChanged(ThemePreset theme) async {
    setState(() => _themePreset = theme);
    await _themeService.save(theme);
  }

  Future<void> _onFontScaleChanged(double scale) async {
    setState(() => _fontScale = scale);
    await _themeService.saveFontScale(scale);
  }

  Future<void> _onHighContrastChanged(bool enabled) async {
    setState(() => _highContrast = enabled);
    await _themeService.saveHighContrast(enabled);
  }

  void _onContentManagementChanged(bool enabled) => setState(() {
    _contentManagementEnabled = enabled;
  });

  ThemeData _buildTheme() {
    final isDark = _themePreset == ThemePreset.dark;
    final primary = switch (_themePreset) {
      ThemePreset.facebook => const Color(0xFF1877F2),
      ThemePreset.youtube => const Color(0xFFE00000),
      _ => const Color(0xFF1D4ED8),
    };
    final surface = isDark
        ? const Color(0xFF162235)
        : (_highContrast ? Colors.white : Colors.white);
    final scaffold = isDark
        ? const Color(0xFF0B1220)
        : (_highContrast ? const Color(0xFFF9FAFB) : const Color(0xFFF3F4F6));
    final onSurface = isDark
        ? const Color(0xFFF8FAFC)
        : (_highContrast ? const Color(0xFF111827) : const Color(0xFF1F2937));
    final border = isDark
        ? const Color(0xFF334155)
        : (_highContrast ? const Color(0xFFCBD5E1) : const Color(0xFFE5E7EB));

    return ThemeData(
      colorScheme: ColorScheme.fromSeed(
        seedColor: primary,
        brightness: isDark ? Brightness.dark : Brightness.light,
        primary: primary,
        surface: surface,
      ),
      scaffoldBackgroundColor: scaffold,
      appBarTheme: AppBarTheme(
        backgroundColor: isDark ? const Color(0xFF101B2D) : surface,
        foregroundColor: isDark
            ? const Color(0xFFF8FAFC)
            : const Color(0xFF1E3A8A),
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        color: surface,
        margin: const EdgeInsets.symmetric(vertical: 6),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(color: border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? const Color(0xFF0F172A) : Colors.white,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      ),
      useMaterial3: true,
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          side: BorderSide(color: primary.withValues(alpha: 0.45)),
          backgroundColor: primary.withValues(alpha: isDark ? 0.18 : 0.07),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: primary),
      ),
      textTheme: ThemeData(
        brightness: isDark ? Brightness.dark : Brightness.light,
      ).textTheme.apply(bodyColor: onSurface, displayColor: onSurface),
    ).copyWith(
      textTheme: ThemeData(
        brightness: isDark ? Brightness.dark : Brightness.light,
      ).textTheme.apply(bodyColor: onSurface, displayColor: onSurface),
    );
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
      theme: _buildTheme(),
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(
          context,
        ).copyWith(textScaler: TextScaler.linear(_fontScale)),
        child: child!,
      ),
      home: _loggedIn
          ? (_role == 'teacher'
                ? TeacherHomeScreen(
                    api: _api,
                    session: _session,
                    onLogout: _onLoggedOut,
                    theme: _themePreset,
                    onThemeChanged: _onThemeChanged,
                    fontScale: _fontScale,
                    highContrast: _highContrast,
                    onFontScaleChanged: _onFontScaleChanged,
                    onHighContrastChanged: _onHighContrastChanged,
                    contentManagementEnabled: _contentManagementEnabled,
                    onContentManagementChanged: _onContentManagementChanged,
                  )
                : HomeScreen(
                    api: _api,
                    session: _session,
                    onLogout: _onLoggedOut,
                    theme: _themePreset,
                    onThemeChanged: _onThemeChanged,
                    fontScale: _fontScale,
                    highContrast: _highContrast,
                    onFontScaleChanged: _onFontScaleChanged,
                    onHighContrastChanged: _onHighContrastChanged,
                    contentManagementEnabled: _contentManagementEnabled,
                    onContentManagementChanged: _onContentManagementChanged,
                  ))
          : LoginScreen(api: _api, session: _session, onLogin: _onLoggedIn),
    );
  }
}

// Kept for widget tests.
typedef AttendanceParentApp = AttendanceApp;
