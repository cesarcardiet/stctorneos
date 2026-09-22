import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_app_drawer.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_browse_scaffold.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class ScorersScreen extends ConsumerStatefulWidget {
  const ScorersScreen({super.key, required this.tournamentId, this.categoryId});

  final int tournamentId;
  final int? categoryId;

  @override
  ConsumerState<ScorersScreen> createState() => _ScorersScreenState();
}

class _ScorersScreenState extends ConsumerState<ScorersScreen> {
  Map<String, dynamic>? _tournament;
  String? _categoryName;
  List<Map<String, dynamic>> _categories = [];
  List<Map<String, dynamic>> _scorers = [];
  String _filter = 'general';
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
      final tournament = await repo.fetchTournament(widget.tournamentId);
      var categories = (tournament['categories'] as List<dynamic>? ?? [])
          .map((c) => Map<String, dynamic>.from(c as Map))
          .toList();

      String? categoryName;
      if (widget.categoryId != null) {
        categories = categories.where((c) => c['id'] == widget.categoryId).toList();
        if (categories.isNotEmpty) {
          categoryName = categories.first['name'] as String?;
          _filter = categoryName ?? 'general';
        }
      }

      var scorers = <Map<String, dynamic>>[];
      for (final category in categories) {
        if (_filter != 'general' && category['name'] != _filter) continue;
        final rankings = await repo.fetchRankings(category['id'] as int);
        final groups = rankings['rankings'] as List<dynamic>? ?? [];
        for (final group in groups) {
          final map = Map<String, dynamic>.from(group as Map);
          if ((map['title'] as String? ?? '').toLowerCase().contains('gol')) {
            final players = (map['players'] as List<dynamic>? ?? [])
                .map((p) {
                  final player = Map<String, dynamic>.from(p as Map);
                  player['category'] = category['name'];
                  return player;
                })
                .toList();
            scorers.addAll(players);
          }
        }
      }

      scorers.sort((a, b) => ((b['value'] ?? 0) as num).compareTo((a['value'] ?? 0) as num));

      if (mounted) {
        setState(() {
          _tournament = tournament;
          _categoryName = categoryName;
          _categories = categories;
          _scorers = scorers;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _setFilter(String filter) async {
    setState(() => _filter = filter);
    await _load();
  }

  Future<void> _openPlayer(Map<String, dynamic> scorer) async {
    final name = scorer['name'] as String? ?? '';
    final team = scorer['team'];
    final teamName = team is Map ? team['name'] as String? : team as String?;
    final playerId = scorer['id'] as int? ??
        await ref.read(catalogRepositoryProvider).findPlayerId(
              name: name,
              teamName: teamName,
              tournamentId: widget.tournamentId,
            );
    if (!mounted) return;
    if (playerId != null) {
      stcOpenPlayer(context, playerId);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No se encontró la credencial de este jugador.')),
      );
    }
  }

  void _openFairPlay() {
    final query = stcCategoryQuery(widget.categoryId);
    context.push('/tournaments/${widget.tournamentId}/fair-play$query');
  }

  @override
  Widget build(BuildContext context) {
    final featured = _scorers.isNotEmpty ? _scorers.first : null;
    final rest = _scorers.length > 1 ? _scorers.sublist(1) : <Map<String, dynamic>>[];
    final tournamentName = _tournament?['name'] as String? ?? 'Torneo';

    return StcBrowseScaffold(
      headerTitle: 'GOLEADORES',
      onBack: () {
        if (widget.categoryId != null) {
          stcOpenCategoryHub(context, widget.tournamentId, widget.categoryId!);
        } else {
          stcGoBack(context, fallback: '/tournaments/${widget.tournamentId}/categories');
        }
      },
      bottomNavIndex: 3,
      tournamentId: widget.tournamentId,
      menuContext: StcMenuContext(
        tournamentId: widget.tournamentId,
        tournamentName: tournamentName,
        categoryId: widget.categoryId,
        categoryName: _categoryName,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
          : RefreshIndicator(
              color: StcColors.cyan,
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(23, 8, 23, 12),
                children: [
                  if (_categoryName != null)
                    Text(_categoryName!.toUpperCase(), style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 12)),
                  const Text('GOLEADORES', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24)),
                  const SizedBox(height: 4),
                  const Text('Los máximos artilleros del torneo', style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 18),
                  if (widget.categoryId == null)
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          StcWhiteFilterChip(label: 'General', selected: _filter == 'general', onTap: () => _setFilter('general'), width: 84),
                          ..._categories.take(2).map((c) {
                            final name = c['name'] as String? ?? '';
                            return StcWhiteFilterChip(
                              label: name,
                              selected: _filter == name,
                              onTap: () => _setFilter(name),
                              width: 78,
                            );
                          }),
                          StcWhiteFilterChip(label: 'Fair Play', selected: false, onTap: _openFairPlay, width: 92),
                        ],
                      ),
                    ),
                  const SizedBox(height: 22),
                  if (featured == null)
                    const StcInfoCard(title: 'Sin goleadores', body: 'Todavía no hay rankings de goles publicados.')
                  else ...[
                    StcFeaturedScorerCard(scorer: featured, rank: 1, onTap: () => _openPlayer(featured)),
                    const SizedBox(height: 16),
                    if (rest.isNotEmpty)
                      StcSurfaceCard(
                        padding: const EdgeInsets.fromLTRB(15, 8, 15, 8),
                        child: Column(
                          children: [
                            for (var i = 0; i < rest.length; i++)
                              StcScorerRankRow(scorer: rest[i], rank: i + 2, onTap: () => _openPlayer(rest[i])),
                          ],
                        ),
                      ),
                  ],
                ],
              ),
            ),
    );
  }
}
