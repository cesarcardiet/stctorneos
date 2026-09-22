import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_app_drawer.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_browse_scaffold.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class FairPlayScreen extends ConsumerStatefulWidget {
  const FairPlayScreen({super.key, required this.tournamentId, this.categoryId});

  final int tournamentId;
  final int? categoryId;

  @override
  ConsumerState<FairPlayScreen> createState() => _FairPlayScreenState();
}

class _FairPlayScreenState extends ConsumerState<FairPlayScreen> {
  Map<String, dynamic>? _tournament;
  String? _categoryName;
  List<Map<String, dynamic>> _rows = [];
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
      final rows = <Map<String, dynamic>>[];

      for (final category in categories) {
        final categoryId = (category as Map)['id'] as int;
        final data = await repo.fetchFairPlay(categoryId);
        for (final row in (data['rows'] as List<dynamic>? ?? [])) {
          final map = Map<String, dynamic>.from(row as Map);
          map['category_name'] = category['name'];
          rows.add(map);
        }
      }

      rows.sort((a, b) => ((a['points'] ?? 0) as num).compareTo((b['points'] ?? 0) as num));

      if (mounted) {
        setState(() {
          _tournament = tournament;
          _categoryName = categoryName;
          _rows = rows;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final tournamentName = _tournament?['name'] as String? ?? 'Torneo';

    return StcBrowseScaffold(
      headerTitle: 'FAIR PLAY',
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
                  const Text('FAIR PLAY', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24)),
                  const SizedBox(height: 4),
                  const Text('Ranking de conducta deportiva', style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 18),
                  if (_rows.isEmpty)
                    const StcInfoCard(title: 'Sin datos', body: 'Todavía no hay ranking Fair Play publicado.')
                  else
                    StcSurfaceCard(
                      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                      child: Column(
                        children: [
                          const Row(
                            children: [
                              SizedBox(width: 28, child: Text('#', style: TextStyle(color: StcColors.cyan, fontSize: 9, fontWeight: FontWeight.w800))),
                              Expanded(child: Text('EQUIPO', style: TextStyle(color: StcColors.cyan, fontSize: 9, fontWeight: FontWeight.w800))),
                              SizedBox(width: 28, child: Text('TA', textAlign: TextAlign.center, style: TextStyle(color: StcColors.cyan, fontSize: 9, fontWeight: FontWeight.w800))),
                              SizedBox(width: 28, child: Text('TR', textAlign: TextAlign.center, style: TextStyle(color: StcColors.cyan, fontSize: 9, fontWeight: FontWeight.w800))),
                              SizedBox(width: 36, child: Text('PTS', textAlign: TextAlign.right, style: TextStyle(color: StcColors.cyan, fontSize: 9, fontWeight: FontWeight.w800))),
                            ],
                          ),
                          const SizedBox(height: 8),
                          ..._rows.asMap().entries.map((entry) {
                            final row = entry.value;
                            final team = row['team'] as Map<String, dynamic>?;
                            final name = team?['name'] as String? ?? 'Equipo';
                            final teamId = team?['id'] as int?;
                            return InkWell(
                              onTap: teamId != null ? () => stcOpenTeam(context, teamId, tournamentId: widget.tournamentId) : null,
                              child: Padding(
                                padding: const EdgeInsets.symmetric(vertical: 8),
                                child: Row(
                                  children: [
                                    SizedBox(
                                      width: 28,
                                      child: Text('${row['position'] ?? entry.key + 1}', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
                                    ),
                                    Expanded(
                                      child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: StcColors.textBody, fontSize: 10, fontWeight: FontWeight.w600)),
                                    ),
                                    SizedBox(
                                      width: 28,
                                      child: Text('${row['yellows'] ?? 0}', textAlign: TextAlign.center, style: const TextStyle(color: StcColors.textBody, fontSize: 10)),
                                    ),
                                    SizedBox(
                                      width: 28,
                                      child: Text('${row['reds'] ?? 0}', textAlign: TextAlign.center, style: const TextStyle(color: StcColors.textBody, fontSize: 10)),
                                    ),
                                    SizedBox(
                                      width: 36,
                                      child: Text('${row['points'] ?? 0}', textAlign: TextAlign.right, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          }),
                        ],
                      ),
                    ),
                ],
              ),
            ),
    );
  }
}
