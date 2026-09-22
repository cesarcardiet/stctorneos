import '../../../core/network/dio_client.dart';
import '../../../shared/models/api_response.dart';

class StaffRepository {
  StaffRepository(this._client);

  final DioClient _client;

  Future<List<Map<String, dynamic>>> fetchMatches({String scope = 'today'}) async {
    final json = await _client.getJson('/staff/matches', query: {'scope': scope});
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return (response.data?['matches'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<Map<String, dynamic>> fetchMatch(int matchId) async {
    final json = await _client.getJson('/staff/matches/$matchId');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<void> addEvent({
    required int matchId,
    required String type,
    required int teamId,
    int? playerId,
    int? minute,
  }) async {
    await _client.postJson('/staff/matches/$matchId/events', data: {
      'type': type,
      'team_id': teamId,
      if (playerId != null) 'player_id': playerId,
      if (minute != null) 'minute': minute,
    });
  }

  Future<void> updateReport({required int matchId, required String? notes}) async {
    await _client.postJson('/staff/matches/$matchId/report', data: {
      'notes': notes,
    });
  }
}
