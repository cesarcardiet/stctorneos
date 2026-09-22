import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class SessionStore {
  SessionStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _profileCompletedPrefix = 'stc_profile_completed_';
  static const _legacyProfileCompletedKey = 'stc_profile_completed';
  static const _guestModeKey = 'stc_guest_mode';
  static const _rememberMeKey = 'stc_remember_me';
  static const _rememberEmailKey = 'stc_remember_email';

  final FlutterSecureStorage _storage;

  String _profileKey(String email) =>
      '$_profileCompletedPrefix${email.trim().toLowerCase()}';

  Future<void> setProfileCompletedFor(String email, bool value) async {
    await _storage.write(key: _profileKey(email), value: value ? '1' : '0');
  }

  Future<bool> isProfileCompletedFor(String email) async {
    final key = _profileKey(email);
    final value = await _storage.read(key: key);
    if (value == '1') return true;
    // Migración desde flag global viejo.
    final legacy = await _storage.read(key: _legacyProfileCompletedKey);
    if (legacy == '1') {
      await setProfileCompletedFor(email, true);
      await _storage.delete(key: _legacyProfileCompletedKey);
      return true;
    }
    return false;
  }

  Future<void> setGuestMode(bool value) async {
    await _storage.write(key: _guestModeKey, value: value ? '1' : '0');
  }

  Future<bool> isGuestMode() async {
    return (await _storage.read(key: _guestModeKey)) == '1';
  }

  Future<void> setRememberMe({required bool enabled, String? email}) async {
    await _storage.write(key: _rememberMeKey, value: enabled ? '1' : '0');
    if (enabled && email != null && email.trim().isNotEmpty) {
      await _storage.write(key: _rememberEmailKey, value: email.trim().toLowerCase());
    } else {
      await _storage.delete(key: _rememberEmailKey);
    }
  }

  Future<({bool enabled, String? email})> rememberMeSettings() async {
    final enabled = (await _storage.read(key: _rememberMeKey)) != '0';
    final email = await _storage.read(key: _rememberEmailKey);
    return (enabled: enabled, email: email);
  }

  Future<void> clearSessionFlags() async {
    await _storage.delete(key: _guestModeKey);
  }
}

final sessionStoreProvider = Provider<SessionStore>((ref) => SessionStore());
