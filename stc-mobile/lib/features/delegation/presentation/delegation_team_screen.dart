import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_form_field.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../profile/profile_providers.dart';

class DelegationTeamScreen extends ConsumerStatefulWidget {
  const DelegationTeamScreen({super.key, required this.teamId});

  final int teamId;

  @override
  ConsumerState<DelegationTeamScreen> createState() => _DelegationTeamScreenState();
}

class _DelegationTeamScreenState extends ConsumerState<DelegationTeamScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(accountPortalRepositoryProvider).fetchWorkspaceTeam(widget.teamId);
      if (mounted) {
        setState(() {
          _data = data;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final team = _data?['team'] as Map<String, dynamic>?;
    final category = _data?['category'] as Map<String, dynamic>?;
    final rosterEditable = team?['roster_editable'] as bool? ?? false;

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'MI EQUIPO', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : team == null
                        ? const Center(child: Text('No se pudo cargar el equipo.', style: TextStyle(color: StcColors.textMuted)))
                        : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                              children: [
                                Row(
                                  children: [
                                    StcTeamShield.fromTeam(team: team, size: 52),
                                    const SizedBox(width: 14),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            (team['name'] as String? ?? '—').toUpperCase(),
                                            style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900),
                                          ),
                                          Text(
                                            '${category?['name'] ?? team['category'] ?? '—'} · ${team['tournament_name'] ?? '—'}',
                                            style: const TextStyle(color: StcColors.cyan),
                                          ),
                                          const SizedBox(height: 6),
                                          StcStatusBadge(
                                            label: (team['registration_label'] as String? ?? '—').toUpperCase(),
                                            icon: rosterEditable ? Icons.lock_open : Icons.lock_outline,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                                if (team['roster_lock_reason'] != null) ...[
                                  const SizedBox(height: 12),
                                  StcInfoCard(
                                    title: 'Plantel bloqueado',
                                    body: team['roster_lock_reason'] as String,
                                  ),
                                ],
                                const SizedBox(height: 18),
                                StcFormRow(children: [
                                  StcFormField(label: 'Jugadores', value: '${_data?['players_count'] ?? 0}'),
                                  StcFormField(label: 'Delegación', value: team['delegation_name'] as String? ?? '—'),
                                ]),
                                const SizedBox(height: 14),
                                StcPrimaryButton(
                                  label: 'Lista de buena fe',
                                  onPressed: () => context.push('/workspace/teams/${widget.teamId}/roster'),
                                ),
                                const SizedBox(height: 10),
                                StcSecondaryButton(
                                  label: 'Seguimiento de fichas',
                                  onPressed: () => context.push(
                                    '/workspace/inscriptions?team=${widget.teamId}${category?['id'] != null ? '&category=${category!['id']}' : ''}',
                                  ),
                                ),
                                if (rosterEditable) ...[
                                  const SizedBox(height: 10),
                                  StcSecondaryButton(
                                    label: 'Agregar jugador',
                                    onPressed: () => context.push(
                                      '/players/new?team=${widget.teamId}${category?['id'] != null ? '&category=${category!['id']}' : ''}',
                                    ),
                                  ),
                                ],
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
