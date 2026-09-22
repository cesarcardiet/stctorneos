class Env {
  /// API de producción STC Torneos.
  /// Para pruebas locales: --dart-define=API_BASE_URL=http://10.0.2.2:8002/api/v1
  static const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://stctorneos.com/api/v1',
  );
}
