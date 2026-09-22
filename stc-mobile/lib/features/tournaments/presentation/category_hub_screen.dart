import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_app_drawer.dart';
import '../../../shared/widgets/stc_browse_scaffold.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class CategoryHubScreen extends ConsumerStatefulWidget {
  const CategoryHubScreen({
    super.key,
    required this.tournamentId,
    required this.categoryId,
  });

  final int tournamentId;
  final int categoryId;

  @override
  ConsumerState<CategoryHubScreen> createState() => _CategoryHubScreenState();
}

class _CategoryHubScreenState extends ConsumerState<CategoryHubScreen> {
  Map<String, dynamic>? _tournament;
  Map<String, dynamic>? _category;
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
      final detail = await repo.fetchCategory(widget.categoryId);
      if (mounted) {
        setState(() {
          _tournament = tournament;
          _category = detail;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final categoryName = _category?['name'] as String? ?? 'Categoría';
    final tournamentName = _tournament?['name'] as String? ?? 'Torneo';
    final modality = _category?['modality'] as String? ?? '';
    final format = _category?['format'] as String? ?? '';
    final branch = _category?['branch'] as String? ?? '';
    final teamCount = (_category?['teams'] as List<dynamic>? ?? []).length;

    return StcBrowseScaffold(
      headerTitle: 'CATEGORÍA',
      onBack: () => stcGoBack(context, fallback: '/tournaments/${widget.tournamentId}/categories'),
      bottomNavIndex: 0,
      tournamentId: widget.tournamentId,
      menuContext: StcMenuContext(
        tournamentId: widget.tournamentId,
        tournamentName: tournamentName,
        categoryId: widget.categoryId,
        categoryName: categoryName,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
          : RefreshIndicator(
              color: StcColors.cyan,
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
                children: [
                  StcSurfaceCard(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(categoryName.toUpperCase(), style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 14)),
                        const SizedBox(height: 4),
                        Text(tournamentName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                        if (branch.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Text(branch, style: const TextStyle(color: StcColors.textBody, fontSize: 10)),
                        ],
                        if (modality.isNotEmpty || format.isNotEmpty) ...[
                          const SizedBox(height: 8),
                          Text([modality, format].where((s) => s.isNotEmpty).join(' · '), style: const TextStyle(color: StcColors.textMuted, fontSize: 10)),
                        ],
                        const SizedBox(height: 10),
                        Text('$teamCount equipos', style: const TextStyle(color: StcColors.primaryBlue, fontWeight: FontWeight.w800, fontSize: 10)),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text('OPERAR', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
                  const SizedBox(height: 12),
                  StcHubAccessGrid(
                    onFixture: () => stcOpenCategorySection(context, widget.tournamentId, widget.categoryId, 'fixture'),
                    onStandings: () => stcOpenCategorySection(context, widget.tournamentId, widget.categoryId, 'standings'),
                    onTeams: () => stcOpenCategorySection(context, widget.tournamentId, widget.categoryId, 'teams'),
                    onVenues: () => stcOpenCategorySection(context, widget.tournamentId, widget.categoryId, 'fixture'),
                  ),
                  const SizedBox(height: 14),
                  _LinkRow(
                    label: 'Goleadores',
                    onTap: () => stcOpenCategorySection(context, widget.tournamentId, widget.categoryId, 'scorers'),
                  ),
                  _LinkRow(
                    label: 'Fair Play',
                    onTap: () => stcOpenCategorySection(context, widget.tournamentId, widget.categoryId, 'fair-play'),
                  ),
                  _LinkRow(
                    label: 'Eliminatorias',
                    onTap: () => stcOpenCategorySection(context, widget.tournamentId, widget.categoryId, 'brackets'),
                  ),
                  const SizedBox(height: 14),
                  _LinkRow(
                    label: 'Novedades del torneo',
                    onTap: () => stcOpenContentList(context, tournamentId: widget.tournamentId),
                  ),
                ],
              ),
            ),
    );
  }
}

class _LinkRow extends StatelessWidget {
  const _LinkRow({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: StcSurfaceCard(
        onTap: onTap,
        padding: const EdgeInsets.fromLTRB(15, 13, 15, 13),
        child: Row(
          children: [
            Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11))),
            const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}
