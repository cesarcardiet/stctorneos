import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class FavoritesScreen extends ConsumerStatefulWidget {
  const FavoritesScreen({super.key});

  @override
  ConsumerState<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends ConsumerState<FavoritesScreen> {
  List<Map<String, dynamic>> _teams = [];
  List<Map<String, dynamic>> _matches = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(catalogRepositoryProvider).fetchFavorites();
      if (mounted) {
        setState(() {
          _teams = (data['teams'] as List<dynamic>? ?? [])
              .map((item) => Map<String, dynamic>.from(item as Map))
              .toList();
          _matches = (data['matches'] as List<dynamic>? ?? [])
              .map((item) => Map<String, dynamic>.from(item as Map))
              .toList();
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
              StcPublicHeader(title: 'FAVORITOS', onBack: () => stcGoBack(context)),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : RefreshIndicator(
                        color: StcColors.cyan,
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
                          children: [
                            const Text(
                              'TUS FAVORITOS',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Equipos y partidos que seguís',
                              style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 18),
                            const Text('EQUIPOS', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                            const SizedBox(height: 10),
                            if (_teams.isEmpty)
                              const StcEmptyState(kind: StcEmptyKind.noData, message: 'Marcá un equipo como favorito desde su ficha.')
                            else
                              ..._teams.map((team) {
                                final name = team['name'] as String? ?? 'Equipo';
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 8),
                                  child: StcSurfaceCard(
                                    onTap: () => context.go('/teams/${team['id']}'),
                                    padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                                    child: Row(
                                      children: [
                                        StcTeamShield.fromTeam(team: team, size: 36),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Text(
                                            name,
                                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12),
                                          ),
                                        ),
                                        const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
                                      ],
                                    ),
                                  ),
                                );
                              }),
                            const SizedBox(height: 18),
                            const Text('PARTIDOS', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                            const SizedBox(height: 10),
                            if (_matches.isEmpty)
                              const StcEmptyState(kind: StcEmptyKind.noData, message: 'Guardá partidos para seguirlos más fácil.')
                            else
                              ..._matches.map((match) {
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: StcFixtureMatchRow(
                                    match: match,
                                    onTap: () {
                                      final id = match['id'] as int?;
                                      if (id != null) stcOpenMatch(context, id);
                                    },
                                  ),
                                );
                              }),
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
