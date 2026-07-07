class ApiConfig {
  /// Production Railway URL. Override at build time with:
  /// flutter run --dart-define=API_BASE_URL=https://your-app.up.railway.app/api/v1
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://attendancesystem-production-c52b.up.railway.app/api/v1',
  );
}
