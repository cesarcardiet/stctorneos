import '../../../core/network/dio_client.dart';
import '../../../shared/models/api_response.dart';

class HomeRepository {
  HomeRepository(this._client);

  final DioClient _client;

  Future<Map<String, dynamic>> fetchHome() async {
    final json = await _client.getJson('/home');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );

    return response.data ?? {};
  }
}
