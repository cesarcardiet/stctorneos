import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_browse_scaffold.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../home/presentation/home_screen.dart';

class TournamentsScreen extends ConsumerStatefulWidget {
  const TournamentsScreen({super.key});

  @override
  ConsumerState<TournamentsScreen> createState() => _TournamentsScreenState();
}

class _TournamentsScreenState extends ConsumerState<TournamentsScreen> {
  List<Map<String, dynamic>> _tournaments = [];
  Map<int, int> _teamCounts = {};
  String _filter = 'Argentina';
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final repo = ref.read(catalogRepositoryProvider);
      final data = await repo.fetchTournaments();
      final counts = await repo.fetchTeamCounts(data);
      if (mounted) {
        setState(() {
          _tournaments = data;
          _teamCounts = counts;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _filtered {
    if (_filter == 'Brasil') {
      return _tournaments.where((t) => (t['country'] as String? ?? '').toLowerCase().contains('brasil')).toList();
    }
    if (_filter == 'Internacional') {
      return _tournaments.where((t) {
        final c = (t['country'] as String? ?? '').toLowerCase();
        return !c.contains('argentina') && !c.contains('brasil') && c.isNotEmpty;
      }).toList();
    }
    return _tournaments.where((t) {
      final c = (t['country'] as String? ?? 'argentina').toLowerCase();
      return c.contains('argentina') || c.isEmpty;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return StcBrowseScaffold(
      headerTitle: 'TORNEOS',
      showBack: false,
      isHomeEntry: true,
      bottomNavIndex: 0,
      body: RefreshIndicator(
        color: StcColors.cyan,
        onRefresh: _load,
        child: _loading
            ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
            : ListView(
                padding: const EdgeInsets.fromLTRB(23, 8, 23, 12),
                children: [
                  const Text(
                    'Elegí el torneo que querés consultar',
                    style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Después elegís la categoría, equipos, fixture y tablas.',
                    style: TextStyle(color: StcColors.textBody, fontSize: 10.5, height: 1.35),
                  ),
                  const SizedBox(height: 18),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        StcFilterChip(
                          label: 'Argentina',
                          selected: _filter == 'Argentina',
                          onTap: () => setState(() => _filter = 'Argentina'),
                          width: 92,
                        ),
                        StcFilterChip(
                          label: 'Brasil',
                          selected: _filter == 'Brasil',
                          onTap: () => setState(() => _filter = 'Brasil'),
                          width: 78,
                        ),
                        StcFilterChip(
                          label: 'Internacional',
                          selected: _filter == 'Internacional',
                          onTap: () => setState(() => _filter = 'Internacional'),
                          width: 118,
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 22),
                  if (_filtered.isEmpty)
                    const StcInfoCard(title: 'Sin torneos', body: 'No hay torneos publicados para este filtro.')
                  else
                    ..._filtered.map((t) {
                      final id = t['id'] as int;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 22),
                        child: StcTournamentListCard(
                          tournament: t,
                          teamCount: _teamCounts[id],
                          onTap: () => stcOpenTournament(context, id),
                        ),
                      );
                    }),
                ],
              ),
      ),
    );
  }
}
