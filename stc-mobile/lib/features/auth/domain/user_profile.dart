class UserProfile {
  UserProfile({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.status,
    required this.role,
    required this.roleSlug,
    required this.scope,
    required this.roles,
    required this.permissions,
    required this.capabilities,
    required this.capabilityLines,
    required this.navigation,
    required this.tournaments,
    required this.delegations,
    required this.context,
    this.player,
    this.favoriteTeam,
    required this.assignedCategories,
    required this.tutorPlayers,
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String status;
  final String role;
  final String? roleSlug;
  final String? scope;
  final List<Map<String, dynamic>> roles;
  final List<String> permissions;
  final List<Map<String, dynamic>> capabilities;
  final List<String> capabilityLines;
  final List<Map<String, dynamic>> navigation;
  final List<Map<String, dynamic>> tournaments;
  final List<Map<String, dynamic>> delegations;
  final Map<String, dynamic> context;
  final Map<String, dynamic>? player;
  final Map<String, dynamic>? favoriteTeam;
  final List<Map<String, dynamic>> assignedCategories;
  final List<Map<String, dynamic>> tutorPlayers;

  factory UserProfile.fromJson(Map<String, dynamic> json) {
    return UserProfile(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String?,
      status: json['status'] as String? ?? 'pending',
      role: json['role'] as String? ?? 'Sin rol',
      roleSlug: json['role_slug'] as String?,
      scope: json['scope'] as String?,
      roles: (json['roles'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      permissions: (json['permissions'] as List<dynamic>? ?? [])
          .map((item) => item.toString())
          .toList(),
      capabilities: (json['capabilities'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      capabilityLines: (json['capability_lines'] as List<dynamic>? ?? [])
          .map((item) => item.toString())
          .toList(),
      navigation: (json['navigation'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      tournaments: (json['tournaments'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      delegations: (json['delegations'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      context: Map<String, dynamic>.from(json['context'] as Map? ?? {}),
      player: json['player'] == null
          ? null
          : Map<String, dynamic>.from(json['player'] as Map),
      favoriteTeam: json['favorite_team'] == null
          ? null
          : Map<String, dynamic>.from(json['favorite_team'] as Map),
      assignedCategories: (json['assigned_categories'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
      tutorPlayers: (json['tutor_players'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList(),
    );
  }
}

class InvitationPreview {
  InvitationPreview({
    required this.email,
    required this.name,
    required this.mode,
    required this.roleName,
    required this.roleSlug,
    required this.scopeLabel,
    required this.tournamentName,
  });

  final String email;
  final String name;
  final String mode;
  final String? roleName;
  final String? roleSlug;
  final String? scopeLabel;
  final String? tournamentName;

  factory InvitationPreview.fromJson(Map<String, dynamic> json) {
    final role = json['role'] as Map<String, dynamic>?;
    final scope = json['scope'] as Map<String, dynamic>?;
    final tournament = scope?['tournament'] as Map<String, dynamic>?;

    return InvitationPreview(
      email: json['email'] as String? ?? '',
      name: json['name'] as String? ?? '',
      mode: json['mode'] as String? ?? 'register',
      roleName: role?['name'] as String?,
      roleSlug: role?['slug'] as String?,
      scopeLabel: scope?['label'] as String?,
      tournamentName: tournament?['name'] as String?,
    );
  }
}

class AuthSession {
  AuthSession({
    required this.token,
    required this.user,
  });

  final String token;
  final UserProfile user;
}
