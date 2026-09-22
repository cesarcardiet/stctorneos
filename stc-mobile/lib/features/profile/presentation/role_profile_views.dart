import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_auth_field.dart';
import '../../../shared/widgets/stc_form_field.dart';
import '../../auth/domain/user_profile.dart';

class RoleProfileView extends StatelessWidget {
  const RoleProfileView({
    super.key,
    required this.user,
    this.onSave,
    this.saving = false,
    this.nameController,
    this.phoneController,
  });

  final UserProfile user;
  final VoidCallback? onSave;
  final bool saving;
  final TextEditingController? nameController;
  final TextEditingController? phoneController;

  @override
  Widget build(BuildContext context) {
    return switch (user.roleSlug) {
      'delegado' => _DelegadoProfileView(
          user: user,
          onSave: onSave,
          saving: saving,
          nameController: nameController,
          phoneController: phoneController,
        ),
      'tutor' => _TutorProfileView(
          user: user,
          onSave: onSave,
          saving: saving,
          nameController: nameController,
          phoneController: phoneController,
        ),
      'jugador' => _JugadorProfileView(user: user, onSave: onSave, saving: saving),
      'consulta' => _ConsultaProfileView(
          user: user,
          onSave: onSave,
          saving: saving,
          nameController: nameController,
          phoneController: phoneController,
        ),
      'arbitro' ||
      'asistente-arbitro' ||
      'asistente-mesa' ||
      'coordinador' ||
      'admin-torneo' ||
      'super-admin' =>
        _StaffProfileView(
          user: user,
          onSave: onSave,
          saving: saving,
          nameController: nameController,
          phoneController: phoneController,
        ),
      _ => _SpectatorProfileView(
          user: user,
          onSave: onSave,
          saving: saving,
          nameController: nameController,
          phoneController: phoneController,
        ),
    };
  }
}

class _SpectatorProfileView extends StatelessWidget {
  const _SpectatorProfileView({
    required this.user,
    this.onSave,
    this.saving = false,
    this.nameController,
    this.phoneController,
  });

  final UserProfile user;
  final VoidCallback? onSave;
  final bool saving;
  final TextEditingController? nameController;
  final TextEditingController? phoneController;

  @override
  Widget build(BuildContext context) {
    final tournament = user.tournaments.isNotEmpty ? user.tournaments.first : null;
    final favoriteClub = user.favoriteTeam?['name'] as String?;
    final club = favoriteClub ?? (user.delegations.isNotEmpty ? user.delegations.first['name'] as String? : null);

    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('COMPLETÁ TU PERFIL', style: TextStyle(
            color: Colors.white,
            fontSize: 26,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.2,
          )),
          const SizedBox(height: 4),
          const Text('Público / Espectador', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          const StcStepHeader(
            step: 1,
            total: 2,
            description: 'Tus datos básicos para seguir torneos y recibir novedades.',
          ),
          const SizedBox(height: 20),
          Center(child: StcPhotoPicker(placeholder: 'FOTO\nOPCIONAL')),
          const SizedBox(height: 22),
          if (nameController != null)
            StcAuthField(label: 'Nombre visible', controller: nameController!, hint: user.name)
          else
            StcFormField(label: 'Nombre visible', value: user.name),
          if (phoneController != null) ...[
            const SizedBox(height: 10),
            StcAuthField(
              label: 'Teléfono',
              controller: phoneController!,
              hint: user.phone ?? '+54 9 11 0000-0000',
              keyboardType: TextInputType.phone,
            ),
          ],
          const SizedBox(height: 10),
          StcFormRow(children: [
            StcFormField(label: 'País', value: tournament?['country'] as String? ?? 'Argentina'),
            StcFormField(label: 'Provincia', value: tournament?['city'] as String? ?? 'Buenos Aires'),
          ]),
          const SizedBox(height: 10),
          StcFormField(label: 'Club favorito', value: club ?? '—'),
          const SizedBox(height: 10),
          StcFormField(label: 'Torneo que seguís', value: tournament?['name'] as String? ?? '—'),
          const SizedBox(height: 14),
          StcInfoCard(
            title: '✓ Notificaciones activadas',
            body: 'Te avisamos resultados, fixture y novedades importantes.',
            borderColor: const Color(0xFF00C853),
            titleColor: const Color(0xFF00C853),
          ),
          const SizedBox(height: 18),
          StcPrimaryButton(label: 'Guardar y continuar', loading: saving, onPressed: onSave ?? () => context.go('/home')),
          const SizedBox(height: 10),
          const Text(
            'Después podés editar estos datos desde tu perfil.',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.textMuted, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _DelegadoProfileView extends StatelessWidget {
  const _DelegadoProfileView({
    required this.user,
    this.onSave,
    this.saving = false,
    this.nameController,
    this.phoneController,
  });

  final UserProfile user;
  final VoidCallback? onSave;
  final bool saving;
  final TextEditingController? nameController;
  final TextEditingController? phoneController;

  @override
  Widget build(BuildContext context) {
    final club = user.delegations.isNotEmpty ? user.delegations.first : null;
    final tournamentsLabel = user.tournaments.isNotEmpty
        ? user.tournaments.map((item) => item['name'] as String? ?? '').where((name) => name.isNotEmpty).join(' · ')
        : null;
    final categoriesLabel = user.assignedCategories.isNotEmpty
        ? user.assignedCategories.map((item) => item['name'] as String? ?? '').where((name) => name.isNotEmpty).join(', ')
        : (user.context['category'] as Map<String, dynamic>?)?['name'] as String? ?? '—';

    return StcAuthScaffold(
      showLogo: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: const Text('PERFIL DELEGADO', style: TextStyle(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.2,
            )),
          ),
          const SizedBox(height: 4),
          const Text('Gestión de club', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          Row(
            children: [
              StcCircleIcon(icon: Icons.person, borderColor: StcColors.cyan),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name.toUpperCase(),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(user.role, style: const TextStyle(color: StcColors.cyan)),
                    const SizedBox(height: 6),
                    if (user.status == 'active')
                      const StcStatusBadge(label: 'VALIDADO', icon: Icons.check),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          if (nameController != null)
            StcAuthField(label: 'Nombre y apellido', controller: nameController!, hint: user.name)
          else
            StcFormField(label: 'Nombre y apellido', value: user.name),
          const SizedBox(height: 10),
          StcFormRow(children: [
            const StcFormField(label: 'DNI', value: '—'),
            phoneController != null
                ? StcAuthField(label: 'Teléfono', controller: phoneController!, hint: user.phone ?? '+54 9 11 0000-0000', keyboardType: TextInputType.phone)
                : StcFormField(label: 'Teléfono', value: user.phone ?? '—'),
          ]),
          const SizedBox(height: 10),
          StcFormField(label: 'Email', value: user.email),
          const SizedBox(height: 10),
          StcFormField(label: 'Club / Institución', value: club?['name'] as String? ?? '—'),
          const SizedBox(height: 10),
          StcFormRow(children: [
            StcFormField(label: 'Cargo', value: user.role),
            StcFormField(label: 'Categorías a cargo', value: categoriesLabel),
          ]),
          const SizedBox(height: 14),
          StcInfoCard(
            title: 'ALCANCE ASIGNADO',
            body: user.scope ?? 'Jugadores, documentación, fixture y comunicaciones del club.',
            footer: tournamentsLabel != null
                ? (user.tournaments.length > 1
                    ? 'Torneos: $tournamentsLabel'
                    : 'Torneo: $tournamentsLabel')
                : null,
          ),
          const SizedBox(height: 18),
          if (user.roleSlug == 'delegado' || user.permissions.contains('delegations.manage')) ...[
            StcSecondaryButton(
              label: 'Mi delegación',
              onPressed: () => context.push('/workspace'),
            ),
            const SizedBox(height: 10),
            StcSecondaryButton(
              label: 'Agregar jugador',
              onPressed: () => context.push('/players/new'),
            ),
            const SizedBox(height: 10),
          ],
          StcPrimaryButton(label: 'Guardar perfil', loading: saving, onPressed: onSave ?? () => context.go('/home')),
          const SizedBox(height: 10),
          const Text(
            'Tus acciones quedan registradas para auditoría del torneo.',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.textMuted, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _StaffProfileView extends StatelessWidget {
  const _StaffProfileView({
    required this.user,
    this.onSave,
    this.saving = false,
    this.nameController,
    this.phoneController,
  });

  final UserProfile user;
  final VoidCallback? onSave;
  final bool saving;
  final TextEditingController? nameController;
  final TextEditingController? phoneController;

  @override
  Widget build(BuildContext context) {
    final tournament = user.tournaments.isNotEmpty ? user.tournaments.first : null;
    final categoriesLabel = user.assignedCategories.isNotEmpty
        ? user.assignedCategories.map((item) => item['name'] as String? ?? '').where((name) => name.isNotEmpty).join(', ')
        : '—';
    final capabilities = user.capabilityLines.isNotEmpty
        ? user.capabilityLines.map((line) => '• $line').join('\n')
        : 'Acceso operativo según rol asignado.';

    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            _staffTitle(user.roleSlug).toUpperCase(),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 4),
          Text(user.role, style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          Row(
            children: [
              StcCircleIcon(icon: _staffIcon(user.roleSlug)),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(user.name.toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
                    Text(user.email, style: const TextStyle(color: StcColors.textMuted, fontSize: 12)),
                    const SizedBox(height: 6),
                    if (user.status == 'active') const StcStatusBadge(label: 'VALIDADO', icon: Icons.check),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          if (nameController != null)
            StcAuthField(label: 'Nombre y apellido', controller: nameController!, hint: user.name)
          else
            StcFormField(label: 'Nombre y apellido', value: user.name),
          const SizedBox(height: 10),
          StcFormRow(children: [
            phoneController != null
                ? StcAuthField(label: 'Teléfono', controller: phoneController!, hint: user.phone ?? '+54 9 11 0000-0000', keyboardType: TextInputType.phone)
                : StcFormField(label: 'Teléfono', value: user.phone ?? '—'),
            StcFormField(label: 'Alcance', value: user.scope ?? '—'),
          ]),
          if (categoriesLabel != '—') ...[
            const SizedBox(height: 10),
            StcFormField(label: 'Categorías asignadas', value: categoriesLabel),
          ],
          const SizedBox(height: 14),
          StcInfoCard(
            title: 'CAPACIDADES',
            body: capabilities,
            footer: tournament != null ? 'Torneo: ${tournament['name']}' : null,
          ),
          if (user.roleSlug == 'arbitro' ||
              user.roleSlug == 'asistente-arbitro' ||
              user.roleSlug == 'asistente-mesa' ||
              user.roleSlug == 'coordinador' ||
              user.roleSlug == 'admin-torneo') ...[
            const SizedBox(height: 14),
            StcSecondaryButton(
              label: 'Abrir partidos staff',
              onPressed: () => context.push('/staff/matches'),
            ),
          ],
          const SizedBox(height: 18),
          StcPrimaryButton(label: 'Guardar perfil', loading: saving, onPressed: onSave ?? () => context.go('/home')),
        ],
      ),
    );
  }
}

class _ConsultaProfileView extends StatelessWidget {
  const _ConsultaProfileView({
    required this.user,
    this.onSave,
    this.saving = false,
    this.nameController,
    this.phoneController,
  });

  final UserProfile user;
  final VoidCallback? onSave;
  final bool saving;
  final TextEditingController? nameController;
  final TextEditingController? phoneController;

  @override
  Widget build(BuildContext context) {
    final tournament = user.tournaments.isNotEmpty ? user.tournaments.first : null;
    final capabilities = user.capabilityLines.isNotEmpty
        ? user.capabilityLines.map((line) => '• $line').join('\n')
        : 'Consulta de información pública y tablas del torneo.';

    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('PERFIL CONSULTA', style: TextStyle(
            color: Colors.white,
            fontSize: 26,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.2,
          )),
          const SizedBox(height: 4),
          const Text('Acceso de lectura', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          if (nameController != null)
            StcAuthField(label: 'Nombre visible', controller: nameController!, hint: user.name)
          else
            StcFormField(label: 'Nombre visible', value: user.name),
          const SizedBox(height: 10),
          if (phoneController != null)
            StcAuthField(label: 'Teléfono', controller: phoneController!, hint: user.phone ?? '+54 9 11 0000-0000', keyboardType: TextInputType.phone)
          else
            StcFormField(label: 'Teléfono', value: user.phone ?? '—'),
          const SizedBox(height: 10),
          StcFormField(label: 'Email', value: user.email),
          const SizedBox(height: 10),
          StcFormField(label: 'Torneo', value: tournament?['name'] as String? ?? '—'),
          const SizedBox(height: 14),
          StcInfoCard(title: 'ALCANCE DE CONSULTA', body: capabilities),
          const SizedBox(height: 18),
          StcPrimaryButton(label: 'Guardar perfil', loading: saving, onPressed: onSave ?? () => context.go('/home')),
        ],
      ),
    );
  }
}

class _TutorProfileView extends StatelessWidget {
  const _TutorProfileView({
    required this.user,
    this.onSave,
    this.saving = false,
    this.nameController,
    this.phoneController,
  });

  final UserProfile user;
  final VoidCallback? onSave;
  final bool saving;
  final TextEditingController? nameController;
  final TextEditingController? phoneController;

  @override
  Widget build(BuildContext context) {
    final linked = user.tutorPlayers.isNotEmpty ? user.tutorPlayers.first : null;

    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('PADRE / TUTOR', style: TextStyle(
            color: Colors.white,
            fontSize: 26,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.2,
          )),
          const SizedBox(height: 4),
          const Text('Vinculación familiar', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
          const SizedBox(height: 14),
          const StcStepHeader(
            step: 2,
            total: 2,
            description: 'Completá tus datos para acompañar al jugador y recibir avisos.',
          ),
          const SizedBox(height: 18),
          if (nameController != null)
            StcAuthField(label: 'Nombre y apellido', controller: nameController!, hint: user.name)
          else
            StcFormField(label: 'Nombre y apellido', value: user.name),
          const SizedBox(height: 10),
          StcFormRow(children: [
            const StcFormField(label: 'DNI', value: '—'),
            phoneController != null
                ? StcAuthField(label: 'Teléfono', controller: phoneController!, hint: user.phone ?? '+54 9 11 0000-0000', keyboardType: TextInputType.phone)
                : StcFormField(label: 'Teléfono', value: user.phone ?? '—'),
          ]),
          const SizedBox(height: 10),
          StcFormField(label: 'Email', value: user.email),
          const SizedBox(height: 10),
          StcFormRow(children: [
            StcFormField(label: 'Parentesco', value: linked?['relationship'] as String? ?? 'Tutor'),
            StcFormField(label: 'Provincia', value: user.tournaments.isNotEmpty ? (user.tournaments.first['city'] as String? ?? '—') : '—'),
          ]),
          const SizedBox(height: 16),
          if (linked != null) _LinkedPlayerCard(player: linked) else _LinkedPlayerPlaceholder(),
          const SizedBox(height: 14),
          const StcInfoCard(
            title: '✓ Autorizaciones y documentación',
            body: 'Vas a poder ver privacidad, fichas médicas y avisos importantes.',
            borderColor: Color(0xFF00C853),
            titleColor: Color(0xFF00C853),
          ),
          const SizedBox(height: 14),
          StcSecondaryButton(
            label: 'Ver mis jugadores',
            onPressed: () => context.push('/tutor/players'),
          ),
          const SizedBox(height: 18),
          StcPrimaryButton(label: 'Guardar y continuar', loading: saving, onPressed: onSave ?? () => context.go('/home')),
          const SizedBox(height: 10),
          const Text(
            'Tus datos solo son visibles para organización autorizada.',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.textMuted, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _JugadorProfileView extends StatelessWidget {
  const _JugadorProfileView({
    required this.user,
    this.onSave,
    this.saving = false,
  });

  final UserProfile user;
  final VoidCallback? onSave;
  final bool saving;

  @override
  Widget build(BuildContext context) {
    final p = user.player ?? {};
    final firstName = p['first_name'] as String? ?? user.name.split(' ').first;
    final lastName = p['last_name'] as String? ?? user.name.split(' ').skip(1).join(' ');
    final age = p['age'];
    final nationality = p['nationality'] as String? ?? 'Argentina';

    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('PERFIL JUGADOR', style: TextStyle(
            color: Colors.white,
            fontSize: 26,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.2,
          )),
          const SizedBox(height: 4),
          const Text('Datos deportivos', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              StcPhotoPicker(
                photoUrl: p['photo'] as String?,
                placeholder: 'FOTO',
                square: true,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(firstName.toUpperCase(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                    Text(lastName.toUpperCase(), style: const TextStyle(color: StcColors.cyan, fontSize: 22, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text('$nationality${age != null ? ' · $age años' : ''}', style: const TextStyle(color: StcColors.textMuted)),
                    const SizedBox(height: 8),
                    const StcStatusBadge(label: 'PÚBLICO', icon: Icons.check),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          StcFormRow(children: [
            StcFormField(label: 'Nombre', value: firstName),
            StcFormField(label: 'Apellido', value: lastName),
          ]),
          const SizedBox(height: 10),
          StcFormRow(children: [
            StcFormField(label: 'Edad', value: age != null ? '$age años' : '—'),
            StcFormField(label: 'Nacionalidad', value: nationality),
          ]),
          const SizedBox(height: 10),
          StcFormField(label: 'Club / Escuela', value: p['club'] as String? ?? '—'),
          const SizedBox(height: 10),
          StcFormRow(children: [
            StcFormField(label: 'Categoría', value: p['category'] as String? ?? '—'),
            StcFormField(label: 'Número camiseta', value: '${p['jersey'] ?? '—'}'),
          ]),
          const SizedBox(height: 10),
          StcFormRow(children: [
            StcFormField(label: 'Posición principal', value: p['position'] as String? ?? '—'),
            StcFormField(label: 'Pie hábil', value: p['preferred_foot'] as String? ?? '—'),
          ]),
          const SizedBox(height: 10),
          StcFormRow(children: [
            StcFormField(label: 'Altura', value: p['height'] != null ? '${p['height']} cm' : '—'),
            StcFormField(label: 'Biografía', value: p['notes'] as String? ?? '—', maxLines: 2),
          ]),
          const SizedBox(height: 14),
          const StcInfoCard(
            title: 'La fecha de nacimiento no es pública.',
            body: 'Se usa solo para categoría y documentación privada.',
          ),
          const SizedBox(height: 14),
          StcSecondaryButton(
            label: 'Abrir mi ficha',
            onPressed: () => context.push('/player/portal'),
          ),
          const SizedBox(height: 18),
          StcPrimaryButton(label: 'Siguiente', loading: saving, onPressed: onSave ?? () => context.go('/home')),
          const SizedBox(height: 10),
          const Text(
            'Continuar a Documentación privada',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.textMuted, fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _LinkedPlayerCard extends StatelessWidget {
  const _LinkedPlayerCard({required this.player});

  final Map<String, dynamic> player;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: StcColors.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: StcColors.cyan, width: 1.4),
      ),
      child: Row(
        children: [
          StcPhotoPicker(
            photoUrl: player['photo'] as String?,
            placeholder: 'FOTO',
            square: true,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Expanded(
                      child: Text('JUGADOR A CARGO', style: TextStyle(color: StcColors.cyan, fontSize: 10, fontWeight: FontWeight.w800)),
                    ),
                    StcStatusBadge(label: 'Vinculado', icon: Icons.check),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  player['name'] as String? ?? '—',
                  style: const TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800),
                ),
                Text(
                  'Categoría ${player['category'] ?? '—'} · ${player['club'] ?? '—'}',
                  style: const TextStyle(color: StcColors.textMuted, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LinkedPlayerPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: StcColors.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: StcColors.border),
      ),
      child: const Text(
        'Sin jugador vinculado todavía. Completá la ficha desde el link de invitación.',
        style: TextStyle(color: StcColors.textMuted),
      ),
    );
  }
}

String _staffTitle(String? roleSlug) {
  return switch (roleSlug) {
    'arbitro' => 'Perfil árbitro',
    'asistente-arbitro' => 'Asistente arbitral',
    'asistente-mesa' => 'Mesa de control',
    'coordinador' => 'Perfil coordinador',
    'admin-torneo' => 'Admin torneo',
    'super-admin' => 'Super admin',
    _ => 'Perfil staff',
  };
}

IconData _staffIcon(String? roleSlug) {
  return switch (roleSlug) {
    'arbitro' => Icons.sports,
    'asistente-arbitro' => Icons.sports_soccer,
    'asistente-mesa' => Icons.table_chart_outlined,
    'coordinador' => Icons.groups_outlined,
    'admin-torneo' => Icons.admin_panel_settings_outlined,
    'super-admin' => Icons.security,
    _ => Icons.badge_outlined,
  };
}
