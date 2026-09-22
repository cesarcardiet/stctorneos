/// Escudo del equipo (como en la web). Nunca usar [flag] como avatar.
String? stcTeamShieldUrl(Map<String, dynamic>? team) {
  if (team == null) return null;
  for (final key in const ['shield', 'logo', 'image']) {
    final value = team[key];
    if (value is String && value.trim().isNotEmpty) {
      return value.trim();
    }
  }
  return null;
}

/// Banner/logo de categoría con fallback al torneo.
String? stcCategoryImageUrl(Map<String, dynamic>? category) {
  if (category == null) return null;
  final image = category['image'];
  if (image is String && image.trim().isNotEmpty) {
    return image.trim();
  }
  final tournament = category['tournament'];
  if (tournament is Map<String, dynamic>) {
    final logo = tournament['logo'];
    if (logo is String && logo.trim().isNotEmpty) {
      return logo.trim();
    }
  }
  return null;
}
