import '../../../core/network/dio_client.dart';
import '../../../shared/models/api_response.dart';

class AccountPortalRepository {
  AccountPortalRepository(this._client);

  final DioClient _client;

  Future<List<Map<String, dynamic>>> fetchTutorPlayers() async {
    final json = await _client.getJson('/tutor/players');
    final response = ApiResponse<List<dynamic>>.fromJson(json, (data) => data as List);
    return (response.data ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> fetchTutorPlayer(int playerId) async {
    final json = await _client.getJson('/tutor/players/$playerId');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchPlayerPortal() async {
    final json = await _client.getJson('/player/portal');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchWorkspace() async {
    final json = await _client.getJson('/workspace');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchWorkspaceTeam(int teamId) async {
    final json = await _client.getJson('/workspace/teams/$teamId');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchWorkspaceTeamPlayers(int teamId) async {
    final json = await _client.getJson('/workspace/teams/$teamId/players');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> fetchWorkspaceInscriptions({
    int? categoryId,
    int? teamId,
  }) async {
    final params = <String, String>{};
    if (categoryId != null) params['category_id'] = '$categoryId';
    if (teamId != null) params['team_id'] = '$teamId';

    final json = await _client.getJson(
      '/workspace/inscriptions',
      query: params.isEmpty ? null : params,
    );
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }
}
