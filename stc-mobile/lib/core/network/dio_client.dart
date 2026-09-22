import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../config/env.dart';
import '../errors/app_exception.dart';
import '../storage/token_store.dart';

typedef UnauthorizedHandler = Future<void> Function();

class DioClient {
  DioClient({
    required TokenStore tokenStore,
    UnauthorizedHandler? onUnauthorized,
  })  : _tokenStore = tokenStore,
        _onUnauthorized = onUnauthorized {
    _dio = Dio(
      BaseOptions(
        baseUrl: Env.apiBaseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {'Accept': 'application/json'},
      ),
    );

    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStore.read();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
        onError: (error, handler) async {
          if (error.response?.statusCode == 401) {
            await _tokenStore.clear();
            await _onUnauthorized?.call();
          }
          handler.next(error);
        },
      ),
    );
  }

  late final Dio _dio;
  final TokenStore _tokenStore;
  final UnauthorizedHandler? _onUnauthorized;

  Dio get dio => _dio;

  Future<Map<String, dynamic>> getJson(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    try {
      final response = await _dio.get<Map<String, dynamic>>(path, queryParameters: query);
      return response.data ?? {};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  Future<Map<String, dynamic>> postJson(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(path, data: data);
      return response.data ?? {};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  Future<Map<String, dynamic>> postMultipart(
    String path,
    FormData data,
  ) async {
    try {
      final response = await _dio.post<Map<String, dynamic>>(path, data: data);
      return response.data ?? {};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  Future<Map<String, dynamic>> patchJson(
    String path, {
    Map<String, dynamic>? data,
  }) async {
    try {
      final response = await _dio.patch<Map<String, dynamic>>(path, data: data);
      return response.data ?? {};
    } on DioException catch (error) {
      throw _mapError(error);
    }
  }

  AppException _mapError(DioException error) {
    final response = error.response;
    final data = response?.data;
    if (data is Map<String, dynamic>) {
      var message = data['message'] as String? ??
          'No pudimos conectar con STC Torneos.';
      if (response?.statusCode == 405 &&
          message.contains('PATCH method is not supported')) {
        message =
            'El servidor aún no tiene la actualización para guardar perfil. '
            'Hay que desplegar la última versión del backend en stctorneos.com.';
      }
      return AppException(
        message,
        statusCode: response?.statusCode,
        errors: data['errors'] is Map<String, dynamic>
            ? data['errors'] as Map<String, dynamic>
            : null,
      );
    }

    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.connectionError) {
      return AppException('No pudimos conectar con STC Torneos.');
    }

    return AppException('Ocurrió un error inesperado.');
  }
}

final tokenStoreProvider = Provider<TokenStore>((ref) => TokenStore());

final dioClientProvider = Provider<DioClient>((ref) {
  return DioClient(tokenStore: ref.watch(tokenStoreProvider));
});
