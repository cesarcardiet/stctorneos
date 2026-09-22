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

class BracketsScreen extends ConsumerStatefulWidget {
  const BracketsScreen({super.key, required this.tournamentId, this.categoryId});

  final int tournamentId;
  final int? categoryId;

  @override
  ConsumerState<BracketsScreen> createState() => _BracketsScreenState();
}

class _BracketsScreenState extends ConsumerState<BracketsScreen> {
  Map<String, dynamic>? _tournament;
  String? _categoryName;
  List<Map<String, dynamic>> _brackets = [];
  int _tabIndex = 3;
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
      var categories = (tournament['categories'] as List<dynamic>? ?? []);
      String? categoryName;
      if (widget.categoryId != null) {
        categories = categories.where((c) => (c as Map)['id'] == widget.categoryId).toList();
        if (categories.isNotEmpty) {
          categoryName = (categories.first as Map)['name'] as String?;
        }
      }
      final brackets = <Map<String, dynamic>>[];

      for (final category in categories) {
        final categoryId = (category as Map)['id'] as int;
        final data = await repo.fetchBrackets(categoryId);
        for (final stage in (data['brackets'] as List<dynamic>? ?? [])) {
          final map = Map<String, dynamic>.from(stage as Map);
          map['category_name'] = category['name'];
          brackets.add(map);
        }
      }

      if (mounted) {
        setState(() {
          _tournament = tournament;
          _categoryName = categoryName;
          _brackets = brackets;
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
    } else if (index == 2) {
      context.push('/tournaments/${widget.tournamentId}/standings$query');
    }
  }

  @override
  Widget build(BuildContext context) {
    final tournamentName = _tournament?['name'] as String? ?? 'Torneo';

    return StcBrowseScaffold(
      headerTitle: 'ELIMINATORIAS',
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
                          Text(_categoryName!.toUpperCase(), style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 12)),
                        const Text(
                          'CRUCES ELIMINATORIOS',
                          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'Fases finales del torneo',
                          style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                        ),
                        const SizedBox(height: 18),
                        if (_brackets.isEmpty)
                          const StcInfoCard(
                            title: 'Sin cruces publicados',
                            body: 'Cuando se definan las eliminatorias aparecerán aquí.',
                          )
                        else
                          ..._brackets.expand((stage) sync* {
                            final stageName = stage['stage'] as String? ?? 'Fase';
                            final categoryName = stage['category_name'] as String? ?? '';
                            final matches = (stage['matches'] as List<dynamic>? ?? [])
                                .map((m) => Map<String, dynamic>.from(m as Map))
                                .toList();

                            yield Padding(
                              padding: const EdgeInsets.only(bottom: 14),
                              child: StcFixtureRoundHeader(
                                roundLabel: stageName.toUpperCase(),
                                dateLabel: categoryName.isNotEmpty ? 'Categoría $categoryName' : 'Eliminatorias',
                                liveCount: matches.where((m) => m['status'] == 'live').length,
                              ),
                            );
                            for (final match in matches) {
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
