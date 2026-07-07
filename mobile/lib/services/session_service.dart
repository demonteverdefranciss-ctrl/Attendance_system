import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

class SessionService {
  SessionService(this._api);

  static const _tokenKey = 'auth_token';
  static const _nameKey = 'user_name';

  final ApiClient _api;

  Future<bool> restore() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString(_tokenKey);
    if (token == null || token.isEmpty) {
      return false;
    }
    _api.setToken(token);
    return true;
  }

  Future<void> login(String username, String password) async {
    final response = await _api.post('/auth/login', {
      'username': username,
      'password': password,
    });
    final data = response['data'] as Map<String, dynamic>;
    final token = data['token'] as String;
    final user = data['user'] as Map<String, dynamic>;

    if (user['role'] != 'parent') {
      throw ApiException('This app is for parent accounts only.');
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_tokenKey, token);
    await prefs.setString(_nameKey, user['name'] as String? ?? username);
    _api.setToken(token);
  }

  Future<void> logout() async {
    try {
      await _api.post('/auth/logout');
    } catch (_) {
      // Ignore network errors during logout.
    }
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_tokenKey);
    await prefs.remove(_nameKey);
    _api.setToken(null);
  }

  Future<String?> userName() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_nameKey);
  }
}
