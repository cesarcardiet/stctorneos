import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_form_field.dart'; // StcStatusBadge, StcFormField, StcPhotoPicker
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../profile_providers.dart';

class TutorPlayersScreen extends ConsumerStatefulWidget {
  const TutorPlayersScreen({super.key});

  @override
  ConsumerState<TutorPlayersScreen> createState() => _TutorPlayersScreenState();
}

class _TutorPlayersScreenState extends ConsumerState<TutorPlayersScreen> {
  List<Map<String, dynamic>> _players = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final players = await ref.read(accountPortalRepositoryProvider).fetchTutorPlayers();
      if (mounted) {
        setState(() {
          _players = players;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'MIS JUGADORES', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : RefreshIndicator(
                        color: StcColors.cyan,
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                          children: [
                            const Text(
                              'JUGADORES A CARGO',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Completá o revisá la ficha de cada jugador',
                              style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 12),
                            const StcInfoCard(
                              title: 'Ficha en dos capas',
                              body: 'El delegado carga el perfil deportivo. Vos completás datos personales, médicos, documentación y autorizaciones.',
                            ),
                            const SizedBox(height: 18),
                            if (_players.isEmpty)
                              const StcInfoCard(
                                title: 'Sin jugadores vinculados',
                                body: 'Cuando aceptes una invitación familiar, verás la ficha acá.',
                              )
                            else
                              ..._players.map((player) => Padding(
                                    padding: const EdgeInsets.only(bottom: 10),
                                    child: _TutorPlayerCard(
                                      player: player,
                                      onOpen: () => context.push('/tutor/players/${player['id']}'),
                                      onFicha: () => context.push('/tutor/players/${player['id']}/ficha'),
                                    ),
                                  )),
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

class TutorPlayerDetailScreen extends ConsumerStatefulWidget {
  const TutorPlayerDetailScreen({super.key, required this.playerId});

  final int playerId;

  @override
  ConsumerState<TutorPlayerDetailScreen> createState() => _TutorPlayerDetailScreenState();
}

class _TutorPlayerDetailScreenState extends ConsumerState<TutorPlayerDetailScreen> {
  Map<String, dynamic>? _player;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final player = await ref.read(accountPortalRepositoryProvider).fetchTutorPlayer(widget.playerId);
      if (mounted) {
        setState(() {
          _player = player;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final player = _player;

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'FICHA FAMILIAR', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : player == null
                        ? const Center(child: Text('No se pudo cargar la ficha.', style: TextStyle(color: StcColors.textMuted)))
                        : SingleChildScrollView(
                            padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                Row(
                                  children: [
                                    StcPhotoPicker(
                                      photoUrl: player['photo'] as String?,
                                      placeholder: 'FOTO',
                                      square: true,
                                    ),
                                    const SizedBox(width: 14),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            player['name'] as String? ?? '—',
                                            style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900),
                                          ),
                                          Text(
                                            '${player['category'] ?? '—'} · ${player['club'] ?? '—'}',
                                            style: const TextStyle(color: StcColors.cyan),
                                          ),
                                          const SizedBox(height: 6),
                                          StcStatusBadge(
                                            label: (player['status_label'] as String? ?? 'PENDIENTE').toUpperCase(),
                                            icon: Icons.info_outline,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 18),
                                const Text('PROGRESO DE FICHA', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                const SizedBox(height: 10),
                                ..._progressRows(player['progress'] as Map<String, dynamic>? ?? {}),
                                const SizedBox(height: 18),
                                const Text('DOCUMENTACIÓN', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                const SizedBox(height: 10),
                                ...((player['documents'] as List<dynamic>? ?? []).map((doc) {
                                  final item = Map<String, dynamic>.from(doc as Map);
                                  return Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: StcFormField(
                                      label: item['type'] as String? ?? 'Documento',
                                      value: item['status_label'] as String? ?? '—',
                                    ),
                                  );
                                })),
                                const SizedBox(height: 12),
                                StcPrimaryButton(
                                  label: player['ficha_locked'] == true ? 'Ver ficha enviada' : 'Completar ficha',
                                  onPressed: () => context.push('/tutor/players/${widget.playerId}/ficha'),
                                ),
                                const SizedBox(height: 10),
                                StcSecondaryButton(
                                  label: 'Ver credencial pública',
                                  onPressed: () => context.push('/players/${player['id']}'),
                                ),
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

  List<Widget> _progressRows(Map<String, dynamic> progress) {
    const labels = {
      'profile': 'Perfil deportivo',
      'guardian': 'Tutor / autorizaciones',
      'documents': 'Documentación',
      'review': 'Revisión organización',
    };

    return labels.entries.map((entry) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: StcFormField(
          label: entry.value,
          value: progress[entry.key] as String? ?? '—',
        ),
      );
    }).toList();
  }
}

class _TutorPlayerCard extends StatelessWidget {
  const _TutorPlayerCard({
    required this.player,
    required this.onOpen,
    required this.onFicha,
  });

  final Map<String, dynamic> player;
  final VoidCallback onOpen;
  final VoidCallback onFicha;

  @override
  Widget build(BuildContext context) {
    final progress = player['progress'] as Map<String, dynamic>? ?? {};
    final review = progress['review'] as String? ?? '—';

    final actionLabel = player['action_label'] as String? ?? (player['ficha_locked'] == true ? 'Ver ficha enviada' : 'Completar ficha');

    return StcSurfaceCard(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InkWell(
            onTap: onOpen,
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
                      Text(
                        player['name'] as String? ?? 'Jugador',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14),
                      ),
                      Text(
                        '${player['category'] ?? '—'} · ${player['club'] ?? '—'}',
                        style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Estado: $review',
                        style: const TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
                const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
              ],
            ),
          ),
          const SizedBox(height: 10),
          StcPrimaryButton(label: actionLabel, onPressed: onFicha),
        ],
      ),
    );
  }
}
