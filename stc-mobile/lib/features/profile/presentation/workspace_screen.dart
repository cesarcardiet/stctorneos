import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_form_field.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../profile_providers.dart';

class WorkspaceScreen extends ConsumerStatefulWidget {
  const WorkspaceScreen({super.key});

  @override
  ConsumerState<WorkspaceScreen> createState() => _WorkspaceScreenState();
}

class _WorkspaceScreenState extends ConsumerState<WorkspaceScreen> {
  Map<String, dynamic>? _workspace;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final workspace = await ref.read(accountPortalRepositoryProvider).fetchWorkspace();
      if (mounted) {
        setState(() {
          _workspace = workspace;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final workspace = _workspace;
    final summary = workspace?['summary'] as Map<String, dynamic>? ?? {};
    final portfolio = (workspace?['portfolio'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final teams = (workspace?['teams'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final capabilityLines = (workspace?['capability_lines'] as List<dynamic>? ?? [])
        .map((item) => item.toString())
        .toList();

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'MI DELEGACIÓN', onBack: () => stcGoBack(context, fallback: '/profile')),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : workspace == null
                        ? const Center(child: Text('No se pudo cargar la delegación.', style: TextStyle(color: StcColors.textMuted)))
                        : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                              children: [
                                Text(
                                  (workspace['scope'] as String? ?? workspace['role'] as String? ?? 'DELEGACIÓN').toUpperCase(),
                                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                                ),
                                const SizedBox(height: 4),
                                const Text(
                                  'Resumen de tu club y equipos',
                                  style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                                ),
                                const SizedBox(height: 18),
                                StcFormRow(children: [
                                  StcFormField(label: 'Torneos', value: '${summary['tournaments_count'] ?? portfolio.length}'),
                                  StcFormField(label: 'Equipos', value: '${summary['teams_count'] ?? teams.length}'),
                                ]),
                                const SizedBox(height: 10),
                                StcFormRow(children: [
                                  StcFormField(label: 'Jugadores', value: '${workspace['players_count'] ?? 0}'),
                                  StcFormField(label: 'Inscrip. abiertas', value: '${summary['open_teams_count'] ?? 0}'),
                                ]),
                                const SizedBox(height: 14),
                                if (capabilityLines.isNotEmpty)
                                  StcInfoCard(
                                    title: 'TUS ACCIONES',
                                    body: capabilityLines.map((line) => '• $line').join('\n'),
                                  ),
                                const SizedBox(height: 18),
                                const Text('DELEGACIONES', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                const SizedBox(height: 10),
                                if (portfolio.isEmpty)
                                  const StcInfoCard(title: 'Sin delegaciones', body: 'Pedile al organizador que te vincule a un club.')
                                else
                                  ...portfolio.map((entry) {
                                    final club = entry['club'] as Map<String, dynamic>? ?? {};
                                    final tournament = entry['tournament'] as Map<String, dynamic>?;
                                    return Padding(
                                      padding: const EdgeInsets.only(bottom: 8),
                                      child: StcSurfaceCard(
                                        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                                        child: Row(
                                          children: [
                                            StcTeamShield(name: club['name'] as String? ?? 'Club', logo: club['logo'] as String?, size: 40),
                                            const SizedBox(width: 12),
                                            Expanded(
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  Text(club['name'] as String? ?? 'Club', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
                                                  Text(
                                                    '${tournament?['name'] ?? '—'} · ${entry['teams_count'] ?? 0} equipos',
                                                    style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                                                  ),
                                                  Text(
                                                    club['origin'] as String? ?? '—',
                                                    style: const TextStyle(color: StcColors.cyan, fontSize: 10),
                                                  ),
                                                ],
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    );
                                  }),
                                const SizedBox(height: 18),
                                Row(
                                  children: [
                                    const Expanded(
                                      child: Text('EQUIPOS', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                    ),
                                    TextButton(
                                      onPressed: () => context.push('/workspace/teams'),
                                      child: const Text('Ver todos', style: TextStyle(color: StcColors.cyan, fontSize: 11)),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                if (teams.isEmpty)
                                  const StcEmptyState(kind: StcEmptyKind.noData, message: 'Todavía no hay equipos vinculados.')
                                else
                                  ...teams.take(5).map((team) => Padding(
                                        padding: const EdgeInsets.only(bottom: 8),
                                        child: _TeamRowCard(
                                          team: team,
                                          onTap: () => context.push('/workspace/teams/${team['id']}'),
                                        ),
                                      )),
                                const SizedBox(height: 14),
                                StcPrimaryButton(label: 'Mis equipos', onPressed: () => context.push('/workspace/teams')),
                                const SizedBox(height: 10),
                                StcSecondaryButton(label: 'Lista de buena fe', onPressed: () => context.push('/workspace/roster')),
                                const SizedBox(height: 10),
                                StcSecondaryButton(label: 'Seguimiento de fichas', onPressed: () => context.push('/workspace/inscriptions')),
                                const SizedBox(height: 10),
                                StcSecondaryButton(label: 'Agregar jugador', onPressed: () => context.push('/players/new')),
                              ],
                            ),
                          ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TeamRowCard extends StatelessWidget {
  const _TeamRowCard({required this.team, required this.onTap});

  final Map<String, dynamic> team;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final open = team['roster_editable'] as bool? ?? false;

    return StcSurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Row(
        children: [
          StcTeamShield.fromTeam(team: team, size: 36),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  (team['name'] as String? ?? 'Equipo').toUpperCase(),
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12),
                ),
                Text(
                  '${team['tournament_name'] ?? '—'} · ${team['category'] ?? '—'}',
                  style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                ),
                Text(
                  team['registration_label'] as String? ?? '—',
                  style: TextStyle(color: open ? StcColors.cyan : StcColors.textMuted, fontSize: 10, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}
