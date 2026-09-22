import '../../../core/errors/app_exception.dart';
import '../../../core/network/dio_client.dart';
import '../../../core/storage/token_store.dart';
import '../../../shared/models/api_response.dart';
import '../domain/user_profile.dart';

class AuthRepository {
  AuthRepository({
    required DioClient client,
    required TokenStore tokenStore,
  })  : _client = client,
        _tokenStore = tokenStore;

  final DioClient _client;
  final TokenStore _tokenStore;

  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    final json = await _client.postJson('/auth/login', data: {
      'email': email.trim(),
      'password': password,
      'device_name': 'stc-mobile',
    });

    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );

    if (!response.ok || response.data == null) {
      throw AppException(response.message ?? 'No pudimos iniciar sesión.');
    }

    final token = response.data!['token'] as String;
    final user = UserProfile.fromJson(
      Map<String, dynamic>.from(response.data!['user'] as Map),
    );

    await _tokenStore.save(token);
    return AuthSession(token: token, user: user);
  }

  Future<UserProfile?> restoreSession() async {
    final token = await _tokenStore.read();
    if (token == null || token.isEmpty) {
      return null;
    }

    try {
      return await me();
    } catch (_) {
      await _tokenStore.clear();
      return null;
    }
  }

  Future<UserProfile> me() async {
    final json = await _client.getJson('/me');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );

    if (!response.ok || response.data == null) {
      throw AppException(response.message ?? 'No pudimos cargar tu perfil.');
    }

    return UserProfile.fromJson(response.data!);
  }

  Future<void> logout() async {
    try {
      await _client.postJson('/auth/logout');
    } finally {
      await _tokenStore.clear();
    }
  }

  Future<InvitationPreview> previewInvitation({
    required String email,
    required String invitationCode,
  }) async {
    final json = await _client.postJson('/auth/invitations/preview', data: {
      'email': email.trim(),
      'invitation_code': invitationCode.trim(),
    });

    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );

    if (!response.ok || response.data == null) {
      throw AppException(response.message ?? 'Invitación inválida.');
    }

    return InvitationPreview.fromJson(response.data!);
  }

  Future<AuthSession> acceptInvitation({
    required String email,
    required String invitationCode,
    required String password,
    String? name,
  }) async {
    final json = await _client.postJson('/auth/invitations/accept', data: {
      if (name != null && name.trim().isNotEmpty) 'name': name.trim(),
      'email': email.trim(),
      'invitation_code': invitationCode.trim(),
      'password': password,
      'password_confirmation': password,
      'device_name': 'stc-mobile',
    });

    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );

    if (!response.ok || response.data == null) {
      throw AppException(response.message ?? 'No pudimos activar la cuenta.');
    }

    final token = response.data!['token'] as String;
    final user = UserProfile.fromJson(
      Map<String, dynamic>.from(response.data!['user'] as Map),
    );

    await _tokenStore.save(token);
    return AuthSession(token: token, user: user);
  }

  Future<void> resetPassword({
    required String email,
    required String token,
    required String password,
  }) async {
    final json = await _client.postJson('/auth/reset-password', data: {
      'email': email.trim(),
      'token': token.trim(),
      'password': password,
      'password_confirmation': password,
    });

    final response = ApiResponse<dynamic>.fromJson(json, null);
    if (!response.ok) {
      throw AppException(response.message ?? 'No pudimos restablecer la contraseña.');
    }
  }

  Future<void> forgotPassword(String email) async {
    final json = await _client.postJson('/auth/forgot-password', data: {
      'email': email.trim(),
    });

    final response = ApiResponse<dynamic>.fromJson(json, null);
    if (!response.ok) {
      throw AppException(response.message ?? 'No pudimos enviar el enlace.');
    }
  }

  Future<AuthSession> registerSpectator({
    required String name,
    required String email,
    required String password,
  }) async {
    final json = await _client.postJson('/auth/register/spectator', data: {
      'name': name.trim(),
      'email': email.trim(),
      'password': password,
      'password_confirmation': password,
      'device_name': 'stc-mobile',
    });

    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );

    if (!response.ok || response.data == null) {
      throw AppException(response.message ?? 'No pudimos crear la cuenta.');
    }

    final token = response.data!['token'] as String;
    final user = UserProfile.fromJson(
      Map<String, dynamic>.from(response.data!['user'] as Map),
    );

    await _tokenStore.save(token);
    return AuthSession(token: token, user: user);
  }

  Future<UserProfile> updateProfile({
    String? name,
    String? phone,
  }) async {
    final json = await _client.patchJson('/me', data: {
      if (name != null) 'name': name.trim(),
      if (phone != null) 'phone': phone.trim(),
    });

    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );

    if (!response.ok || response.data == null) {
      throw AppException(response.message ?? 'No pudimos guardar tu perfil.');
    }

    return UserProfile.fromJson(response.data!);
  }
}
