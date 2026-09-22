import 'package:flutter/material.dart';

class StcColors {
  static const background = Color(0xFF010204);
  static const card = Color(0xCC071326);
  static const surface = Color(0xFF03060C);
  static const surfaceInner = Color(0xFF050C17);
  static const cyan = Color(0xFF00D1FF);
  static const cyanDark = Color(0xFF056BFF);
  static const primaryBlue = Color(0xFF056BFF);
  static const liveRed = Color(0xFFFF3847);
  static const gold = Color(0xFFFFB82E);
  static const textMuted = Color(0xFF7A8FA8);
  static const textBody = Color(0xFFDBE8F5);
  static const border = Color(0xFF056BFF);
  static const borderSoft = Color(0x8C056BFF);
  static const goldBorder = Color(0xFFB8860B);
  static const navActiveBg = Color(0xFF05244D);
  static const navBarBg = Color(0xFF02060C);
}

class StcTheme {
  static ThemeData dark() {
    return ThemeData(
      brightness: Brightness.dark,
      scaffoldBackgroundColor: StcColors.background,
      colorScheme: const ColorScheme.dark(
        primary: StcColors.cyan,
        surface: StcColors.card,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xAA06101F),
        labelStyle: const TextStyle(
          color: StcColors.textMuted,
          fontSize: 11,
          letterSpacing: 1.2,
        ),
        hintStyle: const TextStyle(color: StcColors.textMuted),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: StcColors.border, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: StcColors.cyan, width: 1.6),
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
      ),
      textTheme: const TextTheme(
        headlineMedium: TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w800,
          letterSpacing: 1.5,
          fontSize: 28,
        ),
        titleMedium: TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w700,
          fontSize: 18,
        ),
        bodyMedium: TextStyle(color: Color(0xFFD7E4F1), height: 1.45),
        bodySmall: TextStyle(color: StcColors.textMuted, height: 1.4),
      ),
    );
  }
}
