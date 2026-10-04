import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

enum ThemePreset { defaultTheme, dark, facebook, youtube }

extension ThemePresetDetails on ThemePreset {
  String get storageValue => switch (this) {
    ThemePreset.defaultTheme => 'default',
    ThemePreset.dark => 'dark',
    ThemePreset.facebook => 'facebook',
    ThemePreset.youtube => 'youtube',
  };

  String get label => switch (this) {
    ThemePreset.defaultTheme => 'Default',
    ThemePreset.dark => 'Dark mode',
    ThemePreset.facebook => 'Facebook blue',
    ThemePreset.youtube => 'YouTube red',
  };

  Color get previewColor => switch (this) {
    ThemePreset.defaultTheme => const Color(0xFF1D4ED8),
    ThemePreset.dark => const Color(0xFF162235),
    ThemePreset.facebook => const Color(0xFF1877F2),
    ThemePreset.youtube => const Color(0xFFFF0000),
  };
}

class ThemeService {
  static const _themeKey = 'app_theme';
  static const _fontScaleKey = 'app_font_scale';
  static const _highContrastKey = 'app_high_contrast';

  Future<ThemePreset> load() async {
    final prefs = await SharedPreferences.getInstance();
    final saved = prefs.getString(_themeKey);
    return ThemePreset.values.firstWhere(
      (theme) => theme.storageValue == saved,
      orElse: () => ThemePreset.defaultTheme,
    );
  }

  Future<double> loadFontScale() async {
    final prefs = await SharedPreferences.getInstance();
    final value = prefs.getDouble(_fontScaleKey);
    return value == null ? 1.0 : value.clamp(0.9, 1.4).toDouble();
  }

  Future<bool> loadHighContrast() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getBool(_highContrastKey) ?? false;
  }

  Future<void> save(ThemePreset theme) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_themeKey, theme.storageValue);
  }

  Future<void> saveFontScale(double scale) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setDouble(_fontScaleKey, scale.clamp(0.9, 1.4).toDouble());
  }

  Future<void> saveHighContrast(bool enabled) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_highContrastKey, enabled);
  }
}
