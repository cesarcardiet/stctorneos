class ApiResponse<T> {
  ApiResponse({
    required this.ok,
    this.message,
    this.data,
    this.errors,
  });

  final bool ok;
  final String? message;
  final T? data;
  final Map<String, dynamic>? errors;

  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(Object? json)? mapper,
  ) {
    return ApiResponse(
      ok: json['ok'] == true,
      message: json['message'] as String?,
      data: mapper != null ? mapper(json['data']) : json['data'] as T?,
      errors: json['errors'] is Map<String, dynamic>
          ? json['errors'] as Map<String, dynamic>
          : null,
    );
  }
}
