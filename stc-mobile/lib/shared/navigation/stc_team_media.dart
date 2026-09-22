/// Escudo del equipo (como en la web). Nunca usar [flag] como avatar.
String? stcTeamShieldUrl(Map<String, dynamic>? team) {
  if (team == null) return null;
  final shield = team['shield'] as String?;
  if (shield != null && shield.isNotEmpty) return shield;
  return null;
}
