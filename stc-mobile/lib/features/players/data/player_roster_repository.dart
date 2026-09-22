import 'package:dio/dio.dart';

import '../../../core/network/dio_client.dart';
import '../../../shared/models/api_response.dart';

class PlayerRosterRepository {
  PlayerRosterRepository(this._client);

  final DioClient _client;

  Future<Map<String, dynamic>> fetchContext() async {
    final json = await _client.getJson('/roster/context');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchPlayerRoster({
    required int categoryId,
    required int playerId,
  }) async {
    final json = await _client.getJson('/categories/$categoryId/players/$playerId/roster');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> createPlayer({
    required int categoryId,
    required Map<String, dynamic> payload,
  }) async {
    final json = await _client.postJson('/categories/$categoryId/players', data: payload);
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> updatePlayer({
    required int categoryId,
    required int playerId,
    required Map<String, dynamic> payload,
  }) async {
    final json = await _client.patchJson('/categories/$categoryId/players/$playerId', data: payload);
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> uploadDocument({
    required int categoryId,
    required int playerId,
    required String type,
    required String filePath,
  }) async {
    final form = FormData.fromMap({
      'type': type,
      'file': await MultipartFile.fromFile(filePath),
    });
    final json = await _client.postMultipart(
      '/categories/$categoryId/players/$playerId/documents',
      form,
    );
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> inviteGuardian({
    required int categoryId,
    required int playerId,
    required String email,
  }) async {
    final json = await _client.postJson(
      '/categories/$categoryId/players/$playerId/invite-guardian',
      data: {'email': email},
    );
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }
}
