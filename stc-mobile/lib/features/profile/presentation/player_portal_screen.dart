import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_form_field.dart'; // StcStatusBadge, StcFormField, StcPhotoPicker
import '../../../shared/widgets/stc_public_widgets.dart';
import '../profile_providers.dart';

class PlayerPortalScreen extends ConsumerStatefulWidget {
  const PlayerPortalScreen({super.key});

  @override
  ConsumerState<PlayerPortalScreen> createState() => _PlayerPortalScreenState();
}

class _PlayerPortalScreenState extends ConsumerState<PlayerPortalScreen> {
  Map<String, dynamic>? _portal;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final portal = await ref.read(accountPortalRepositoryProvider).fetchPlayerPortal();
      if (mounted) {
        setState(() {
          _portal = portal;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final portal = _portal;
    final player = portal?['player'] as Map<String, dynamic>?;
    final stats = portal?['stats'] as Map<String, dynamic>? ?? {};
    final events = (portal?['recent_events'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final documents = (portal?['documents'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'MI FICHA', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : player == null
                        ? const Center(child: Text('No encontramos tu ficha.', style: TextStyle(color: StcColors.textMuted)))
                        : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
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
                                            (player['name'] as String? ?? '—').toUpperCase(),
                                            style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900),
                                          ),
                                          Text(
                                            '${player['category'] ?? '—'} · ${player['club'] ?? '—'}',
                                            style: const TextStyle(color: StcColors.cyan),
                                          ),
                                          const SizedBox(height: 6),
                                          StcStatusBadge(
                                            label: (player['status_label'] as String? ?? 'ACTIVO').toUpperCase(),
                                            icon: Icons.check,
                                          ),
                                        ],
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 18),
                                StcFormRow(children: [
                                  StcFormField(label: 'Posición', value: player['position'] as String? ?? '—'),
                                  StcFormField(label: 'Camiseta', value: '${player['jersey'] ?? '—'}'),
                                ]),
                                const SizedBox(height: 10),
                                StcFormField(label: 'Torneo', value: player['tournament'] as String? ?? '—'),
                                const SizedBox(height: 18),
                                const Text('ESTADÍSTICAS', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                const SizedBox(height: 10),
                                StcFormRow(children: [
                                  StcFormField(label: 'Partidos', value: '${stats['matches'] ?? 0}'),
                                  StcFormField(label: 'Goles', value: '${stats['goals'] ?? 0}'),
                                ]),
                                const SizedBox(height: 10),
                                StcFormRow(children: [
                                  StcFormField(label: 'Tarjetas', value: '${stats['cards'] ?? 0}'),
                                  StcFormField(label: 'Minutos', value: '${stats['minutes'] ?? 0}'),
                                ]),
                                const SizedBox(height: 18),
                                const Text('ÚLTIMOS EVENTOS', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                const SizedBox(height: 10),
                                if (events.isEmpty)
                                  const StcInfoCard(title: 'Sin eventos recientes', body: 'Tus goles, tarjetas y participaciones aparecerán acá.')
                                else
                                  ...events.map((event) => Padding(
                                        padding: const EdgeInsets.only(bottom: 8),
                                        child: StcFormField(
                                          label: event['match'] as String? ?? 'Partido',
                                          value: '${event['minute'] ?? '—'}\' · ${event['label'] ?? event['type'] ?? '—'}',
                                        ),
                                      )),
                                const SizedBox(height: 18),
                                const Text('DOCUMENTACIÓN PRIVADA', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                const SizedBox(height: 10),
                                if (documents.isEmpty)
                                  const StcInfoCard(title: 'Sin documentos cargados', body: 'Completá la documentación con tu tutor o delegado.')
                                else
                                  ...documents.map((doc) => Padding(
                                        padding: const EdgeInsets.only(bottom: 8),
                                        child: StcFormField(
                                          label: doc['type'] as String? ?? 'Documento',
                                          value: doc['status_label'] as String? ?? '—',
                                        ),
                                      )),
                                const SizedBox(height: 14),
                                StcPrimaryButton(
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
}
