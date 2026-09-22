import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_app_drawer.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_browse_scaffold.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../home/presentation/home_screen.dart';

class FixtureScreen extends ConsumerStatefulWidget {
  const FixtureScreen({super.key, required this.tournamentId, this.categoryId});

  final int tournamentId;
  final int? categoryId;

  @override
  ConsumerState<FixtureScreen> createState() => _FixtureScreenState();
}

class _FixtureScreenState extends ConsumerState<FixtureScreen> {
  Map<String, dynamic>? _tournament;
  String? _categoryName;
  List<Map<String, dynamic>> _matches = [];
  String _viewMode = 'date';
  int _tabIndex = 1;
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
      var matches = await repo.fetchMatches(tournamentId: widget.tournamentId);
      String? categoryName;
      if (widget.categoryId != null) {
        final category = await repo.fetchCategory(widget.categoryId!);
        categoryName = category['name'] as String?;
        if (categoryName != null) {
          matches = matches.where((m) => m['category'] == categoryName).toList();
        }
      }
      if (mounted) {
        setState(() {
          _tournament = tournament;
          _categoryName = categoryName;
          _matches = matches;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<_FixtureGroup> get _groups {
    final map = <String, _FixtureGroup>{};
    for (final match in _matches) {
      final round = match['round']?.toString();
      final stage = match['stage'] as String?;
      final scheduled = match['scheduled_at'] as String?;
      final dt = scheduled != null ? DateTime.tryParse(scheduled)?.toLocal() : null;

      String key;
      String roundLabel;
      String dateLabel;
      if (_viewMode == 'round' && round != null && round.isNotEmpty) {
        key = 'round-$round';
        roundLabel = 'RONDA $round';
        dateLabel = stage ?? 'Jornada $round';
      } else if (dt != null) {
        key = DateFormat('yyyy-MM-dd').format(dt);
        roundLabel = stage?.toUpperCase() ?? 'JORNADA';
        dateLabel = DateFormat("EEEE d 'de' MMMM", 'es').format(dt);
      } else {
        key = 'other';
        roundLabel = stage?.toUpperCase() ?? 'PARTIDOS';
        dateLabel = 'Sin fecha';
      }

      map.putIfAbsent(key, () => _FixtureGroup(roundLabel: roundLabel, dateLabel: dateLabel));
      map[key]!.matches.add(match);
    }

    final groups = map.values.toList();
    for (final group in groups) {
      group.liveCount = group.matches.where((m) => m['status'] == 'live').length;
    }
    return groups;
  }

  void _onTabChanged(int index) {
    setState(() => _tabIndex = index);
    final query = stcCategoryQuery(widget.categoryId);
    if (index == 2) {
      context.push('/tournaments/${widget.tournamentId}/standings$query');
    } else if (index == 3) {
      context.push('/tournaments/${widget.tournamentId}/brackets$query');
    }
  }

  @override
  Widget build(BuildContext context) {
    final tournamentName = _tournament?['name'] as String? ?? 'Torneo';

    return StcBrowseScaffold(
      headerTitle: 'FIXTURE',
      onBack: () {
        if (widget.categoryId != null) {
          stcOpenCategoryHub(context, widget.tournamentId, widget.categoryId!);
        } else {
          stcGoBack(context, fallback: '/tournaments/${widget.tournamentId}/categories');
        }
      },
      bottomNavIndex: 1,
      tournamentId: widget.tournamentId,
      menuContext: StcMenuContext(
        tournamentId: widget.tournamentId,
        tournamentName: tournamentName,
        categoryId: widget.categoryId,
        categoryName: _categoryName,
      ),
      body: Column(
        children: [
          StcSectionTabs(
            tabs: const ['Resumen', 'Partidos', 'Clasificaciones', 'Eliminatorias'],
            activeIndex: _tabIndex,
            onChanged: _onTabChanged,
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                : RefreshIndicator(
                    color: StcColors.cyan,
                    onRefresh: _load,
                    child: ListView(
                      padding: const EdgeInsets.fromLTRB(17, 16, 17, 8),
                      children: [
                        if (_categoryName != null)
                          Padding(
                            padding: const EdgeInsets.only(bottom: 8),
                            child: Text(_categoryName!.toUpperCase(), style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 12)),
                          ),
                        SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              StcWhiteFilterChip(
                                label: 'Por fecha',
                                selected: _viewMode == 'date',
                                onTap: () => setState(() => _viewMode = 'date'),
                                width: 88,
                              ),
                              StcWhiteFilterChip(
                                label: 'Por jornada',
                                selected: _viewMode == 'round',
                                onTap: () => setState(() => _viewMode = 'round'),
                                width: 102,
                              ),
                              StcWhiteFilterChip(
                                label: 'Por equipo',
                                selected: _viewMode == 'team',
                                onTap: () => setState(() => _viewMode = 'team'),
                                width: 100,
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                        if (_matches.isEmpty)
                          const StcInfoCard(title: 'Sin partidos', body: 'Todavía no hay partidos publicados para este torneo.')
                        else
                          ..._groups.expand((group) sync* {
                            yield Padding(
                              padding: const EdgeInsets.only(bottom: 14),
                              child: StcFixtureRoundHeader(
                                roundLabel: group.roundLabel,
                                dateLabel: group.dateLabel,
                                liveCount: group.liveCount,
                              ),
                            );
                            for (final match in group.matches) {
                              yield Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: StcFixtureMatchRow(
                                  match: match,
                                  onTap: () {
                                    final id = match['id'] as int?;
                                    if (id != null) stcOpenMatch(context, id);
                                  },
                                ),
                              );
                            }
                          }),
                      ],
                    ),
                  ),
          ),
        ],
      ),
    );
  }
}

class _FixtureGroup {
  _FixtureGroup({required this.roundLabel, required this.dateLabel});

  final String roundLabel;
  final String dateLabel;
  final List<Map<String, dynamic>> matches = [];
  int liveCount = 0;
}
