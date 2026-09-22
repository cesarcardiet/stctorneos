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

class TeamsListScreen extends ConsumerStatefulWidget {
  const TeamsListScreen({super.key, required this.tournamentId, this.categoryId});

  final int tournamentId;
  final int? categoryId;

  @override
  ConsumerState<TeamsListScreen> createState() => _TeamsListScreenState();
}

class _TeamsListScreenState extends ConsumerState<TeamsListScreen> {
  Map<String, dynamic>? _tournament;
  String? _categoryName;
  List<Map<String, dynamic>> _teams = [];
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
      var teams = await repo.fetchTeamsForTournament(widget.tournamentId);
      String? categoryName;
      if (widget.categoryId != null) {
        final category = await repo.fetchCategory(widget.categoryId!);
        categoryName = category['name'] as String?;
        if (categoryName != null) {
          teams = teams.where((t) => t['category'] == categoryName).toList();
        }
      }
      if (mounted) {
        setState(() {
          _tournament = tournament;
          _categoryName = categoryName;
          _teams = teams;
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
      headerTitle: 'EQUIPOS',
      onBack: () {
        if (widget.categoryId != null) {
          stcOpenCategoryHub(context, widget.tournamentId, widget.categoryId!);
        } else {
          stcGoBack(context, fallback: '/tournaments/${widget.tournamentId}/categories');
        }
      },
      bottomNavIndex: 0,
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
              child: _teams.isEmpty
                  ? ListView(
                      padding: const EdgeInsets.all(23),
                      children: const [StcInfoCard(title: 'Sin equipos', body: 'Todavía no hay equipos publicados para este torneo.')],
                    )
                  : ListView.builder(
                      padding: const EdgeInsets.fromLTRB(17, 8, 17, 12),
                      itemCount: _teams.length,
                      itemBuilder: (context, index) {
                        final team = _teams[index];
                        final name = team['name'] as String? ?? '';
                        final category = team['category'] as String? ?? '';
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: InkWell(
                            onTap: () => stcOpenTeam(context, team['id'] as int, tournamentId: widget.tournamentId),
                            borderRadius: BorderRadius.circular(14),
                            child: StcSurfaceCard(
                              padding: const EdgeInsets.fromLTRB(15, 14, 15, 14),
                              child: Row(
                                children: [
                                  StcTeamShield.fromTeam(team: team, size: 48),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(name.toUpperCase(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
                                        if (category.isNotEmpty)
                                          Text('Categoría $category', style: const TextStyle(color: StcColors.textBody, fontSize: 10)),
                                      ],
                                    ),
                                  ),
                                  const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
                                ],
                              ),
                            ),
                          ),
                        );
                      },
                    ),
            ),
    );
  }
}
