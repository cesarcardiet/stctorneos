import 'package:dio/dio.dart';

import '../../../core/network/dio_client.dart';
import '../../../shared/models/api_response.dart';

class TutorFichaRepository {
  TutorFichaRepository(this._client);

  final DioClient _client;

  Future<Map<String, dynamic>> fetchFicha(int playerId) async {
    final json = await _client.getJson('/tutor/players/$playerId/ficha');
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }

  Future<Map<String, dynamic>> submitFicha({
    required int playerId,
    required Map<String, dynamic> fields,
    Map<String, String> documentPaths = const {},
  }) async {
    final form = FormData.fromMap(fields);

    for (final entry in documentPaths.entries) {
      form.files.add(
        MapEntry(
          'document_files[${entry.key}]',
          await MultipartFile.fromFile(entry.value),
        ),
      );
    }

    final json = await _client.postMultipart('/tutor/players/$playerId/ficha', form);
    final response = ApiResponse<Map<String, dynamic>>.fromJson(
      json,
      (data) => Map<String, dynamic>.from(data as Map),
    );
    return response.data ?? {};
  }
}
