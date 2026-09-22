import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_bottom_nav.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class TournamentHubScreen extends ConsumerStatefulWidget {
  const TournamentHubScreen({super.key, required this.tournamentId});

  final int tournamentId;

  @override
  ConsumerState<TournamentHubScreen> createState() => _TournamentHubScreenState();
}

class _TournamentHubScreenState extends ConsumerState<TournamentHubScreen> {
  Map<String, dynamic>? _tournament;
  Map<String, int>? _stats;
  List<Map<String, dynamic>> _news = [];
  bool _loading = true;
  bool _loadingExtras = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = ref.read(catalogRepositoryProvider);
      final tournament = await repo.fetchTournament(widget.tournamentId);

      if (mounted) {
        setState(() {
          _tournament = tournament;
          _loading = false;
        });
      }

      await _loadExtras();
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = 'No pudimos cargar el hub del torneo.';
        });
      }
    }
  }

  Future<void> _loadExtras() async {
    if (!mounted) return;
    setState(() => _loadingExtras = true);
    try {
      final repo = ref.read(catalogRepositoryProvider);
      final results = await Future.wait([
        repo.fetchTournamentStats(widget.tournamentId),
        repo.fetchHome(),
      ]);
      final stats = results[0] as Map<String, int>;
      final home = Map<String, dynamic>.from(results[1] as Map);
      final news = (home['news'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();

      if (mounted) {
        setState(() {
          _stats = stats;
          _news = news;
          _loadingExtras = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loadingExtras = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categories = (_tournament?['categories'] as List<dynamic>? ?? []);
    final categoryLabel = categories.isNotEmpty
        ? '${_tournament?['edition'] ?? 'Santa Teresita Cup'} · Categoría ${(categories.first as Map)['name']}'
        : null;

    return StcBackScope(
      child: Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'HUB DEL TORNEO', onBack: () => stcGoBack(context, fallback: '/home')),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : _error != null
                        ? Center(
                            child: StcEmptyState(
                              kind: StcEmptyKind.offline,
                              message: _error!,
                              actionLabel: 'Reintentar',
                              onAction: _load,
                            ),
                          )
                    : RefreshIndicator(
                        color: StcColors.cyan,
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(17, 8, 17, 12),
                          children: [
                            if (_tournament != null)
                              StcHubHeroCard(
                                tournament: _tournament!,
                                liveCount: _stats?['live'] ?? 0,
                                categoryLabel: categoryLabel,
                              ),
                            const SizedBox(height: 20),
                            if (_loadingExtras && _stats == null)
                              const Padding(
                                padding: EdgeInsets.symmetric(vertical: 12),
                                child: Center(child: CircularProgressIndicator(color: StcColors.cyan)),
                              )
                            else if (_stats != null)
                              StcHubStatsBar(
                                teams: _stats!['teams'] ?? 0,
                                matches: _stats!['matches'] ?? 0,
                                players: _stats!['players'] ?? 0,
                                venues: _stats!['venues'] ?? 0,
                              ),
                            const SizedBox(height: 18),
                            const Text(
                              'ACCESOS DEL TORNEO',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13),
                            ),
                            const SizedBox(height: 12),
                            StcHubAccessGrid(
                              onFixture: () => stcOpenTournamentSection(context, widget.tournamentId, 'fixture'),
                              onStandings: () => stcOpenTournamentSection(context, widget.tournamentId, 'standings'),
                              onTeams: () => stcOpenTournamentSection(context, widget.tournamentId, 'teams'),
                              onVenues: () => stcOpenTournamentSection(context, widget.tournamentId, 'fixture'),
                            ),
                            const SizedBox(height: 18),
                            if (_news.isNotEmpty)
                              StcNewsCard(
                                title: _news.first['title'] as String? ?? 'Novedades',
                                summary: _news.first['summary'] as String? ?? '',
                                onRead: () {
                                  final slug = _news.first['slug'] as String?;
                                  if (slug != null && slug.isNotEmpty) {
                                    stcOpenContentDetail(context, slug);
                                  } else {
                                    stcOpenContentList(context, tournamentId: widget.tournamentId);
                                  }
                                },
                              ),
                            const SizedBox(height: 10),
                            StcSurfaceCard(
                              onTap: () => stcOpenContentList(context, tournamentId: widget.tournamentId),
                              padding: const EdgeInsets.fromLTRB(15, 13, 15, 13),
                              child: const Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      'Ver todas las novedades y placas',
                                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11),
                                    ),
                                  ),
                                  Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
              ),
              StcBottomNav(
                currentIndex: stcNavIndexForPath('/tournaments/${widget.tournamentId}'),
                tournamentId: widget.tournamentId,
              ),
            ],
          ),
        ),
      ),
    ),
    );
  }
}
