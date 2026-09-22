import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_app_drawer.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_browse_scaffold.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../home/presentation/home_screen.dart';

class StandingsScreen extends ConsumerStatefulWidget {
  const StandingsScreen({super.key, required this.tournamentId, this.categoryId});

  final int tournamentId;
  final int? categoryId;

  @override
  ConsumerState<StandingsScreen> createState() => _StandingsScreenState();
}

class _StandingsScreenState extends ConsumerState<StandingsScreen> {
  Map<String, dynamic>? _tournament;
  String? _categoryName;
  List<Map<String, dynamic>> _groups = [];
  String _sideFilter = 'all';
  int _tabIndex = 2;
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
      final allCategories = (tournament['categories'] as List<dynamic>? ?? []);
      final categories = widget.categoryId != null
          ? allCategories.where((c) => (c as Map)['id'] == widget.categoryId).toList()
          : allCategories;
      String? categoryName;
      if (widget.categoryId != null && categories.isNotEmpty) {
        categoryName = (categories.first as Map)['name'] as String?;
      }
      final groups = <Map<String, dynamic>>[];

      for (final category in categories) {
        final categoryId = (category as Map)['id'] as int;
        final catName = category['name'] as String? ?? '';
        final standings = await repo.fetchStandings(categoryId);
        final standingsGroups = standings['groups'] as List<dynamic>? ?? [];
        for (final group in standingsGroups) {
          final map = Map<String, dynamic>.from(group as Map);
          map['category_name'] = catName;
          groups.add(map);
        }
      }

      if (mounted) {
        setState(() {
          _tournament = tournament;
          _categoryName = categoryName;
          _groups = groups;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _onTabChanged(int index) {
    setState(() => _tabIndex = index);
    final query = stcCategoryQuery(widget.categoryId);
    if (index == 1) {
      context.push('/tournaments/${widget.tournamentId}/fixture$query');
    } else if (index == 3) {
      context.push('/tournaments/${widget.tournamentId}/brackets$query');
    }
  }

  @override
  Widget build(BuildContext context) {
    final tournamentName = _tournament?['name'] as String? ?? 'Torneo';

    return StcBrowseScaffold(
      headerTitle: 'TABLAS',
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
                              StcWhiteFilterChip(label: 'Todos', selected: _sideFilter == 'all', onTap: () => setState(() => _sideFilter = 'all'), width: 70),
                              StcWhiteFilterChip(label: 'Local', selected: _sideFilter == 'local', onTap: () => setState(() => _sideFilter = 'local'), width: 70),
                              StcWhiteFilterChip(label: 'Visitante', selected: _sideFilter == 'away', onTap: () => setState(() => _sideFilter = 'away'), width: 92),
                            ],
                          ),
                        ),
                        const SizedBox(height: 20),
                        if (_groups.isEmpty)
                          const StcInfoCard(title: 'Sin tablas', body: 'Todavía no hay posiciones publicadas para este torneo.')
                        else
                          ..._groups.map((group) {
                            final groupName = group['group'] as String? ?? group['name'] as String? ?? 'General';
                            final categoryName = group['category_name'] as String? ?? '';
                            final rows = (group['rows'] as List<dynamic>? ?? [])
                                .map((row) => Map<String, dynamic>.from(row as Map))
                                .toList();
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 18),
                              child: StcStandingsTable(
                                title: '$tournamentName · $categoryName · $groupName',
                                rows: rows,
                                ruleLabel: rows.length >= 4 ? 'Los primeros 4 clasifican a playoffs' : null,
                                onTeamTap: (team) => stcOpenTeam(context, team['id'] as int, tournamentId: widget.tournamentId),
                              ),
                            );
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
