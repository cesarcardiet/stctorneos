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

class CategoriesScreen extends ConsumerStatefulWidget {
  const CategoriesScreen({super.key, required this.tournamentId});

  final int tournamentId;

  @override
  ConsumerState<CategoriesScreen> createState() => _CategoriesScreenState();
}

class _CategoriesScreenState extends ConsumerState<CategoriesScreen> {
  Map<String, dynamic>? _tournament;
  String _branchFilter = '';
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final tournament = await ref.read(catalogRepositoryProvider).fetchTournament(widget.tournamentId);
      if (mounted) {
        setState(() {
          _tournament = tournament;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _categories {
    final list = (_tournament?['categories'] as List<dynamic>? ?? [])
        .map((c) => Map<String, dynamic>.from(c as Map))
        .toList();
    if (_branchFilter.isEmpty) return list;
    return list.where((c) => (c['branch'] as String? ?? '') == _branchFilter).toList();
  }

  Set<String> get _branches {
    return (_tournament?['categories'] as List<dynamic>? ?? [])
        .map((c) => (c as Map)['branch'] as String? ?? '')
        .where((b) => b.isNotEmpty)
        .toSet();
  }

  @override
  Widget build(BuildContext context) {
    final tournamentName = _tournament?['name'] as String? ?? 'Torneo';
    final tournamentLogo = _tournament?['logo'] as String?;

    return StcBrowseScaffold(
      headerTitle: 'CATEGORÍAS',
      onBack: () => stcGoBack(context, fallback: '/tournaments'),
      bottomNavIndex: 0,
      tournamentId: widget.tournamentId,
      menuContext: StcMenuContext(
        tournamentId: widget.tournamentId,
        tournamentName: tournamentName,
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
          : RefreshIndicator(
              color: StcColors.cyan,
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
                children: [
                  Row(
                    children: [
                      if (tournamentLogo != null && tournamentLogo.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(right: 10),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(10),
                            child: Image.network(
                              tournamentLogo,
                              width: 42,
                              height: 42,
                              fit: BoxFit.contain,
                              errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                            ),
                          ),
                        ),
                      Expanded(
                        child: Text(
                          tournamentName.toUpperCase(),
                          style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Elegí una categoría. Después ves equipos, fixture y tablas.',
                    style: TextStyle(color: StcColors.textBody, fontSize: 11, height: 1.35),
                  ),
                  if (_branches.length > 1) ...[
                    const SizedBox(height: 16),
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          StcWhiteFilterChip(
                            label: 'Todas',
                            selected: _branchFilter.isEmpty,
                            onTap: () => setState(() => _branchFilter = ''),
                            width: 72,
                          ),
                          ..._branches.map(
                            (branch) => Padding(
                              padding: const EdgeInsets.only(left: 8),
                              child: StcWhiteFilterChip(
                                label: branch,
                                selected: _branchFilter == branch,
                                onTap: () => setState(() => _branchFilter = branch),
                                width: 88,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  const SizedBox(height: 18),
                  if (_categories.isEmpty)
                    const StcInfoCard(title: 'Sin categorías', body: 'Este torneo todavía no tiene categorías publicadas.')
                  else
                    ..._categories.map((category) {
                      final id = category['id'] as int;
                      final name = category['name'] as String? ?? 'Categoría';
                      final modality = category['modality'] as String? ?? '';
                      final format = category['format'] as String? ?? '';
                      final branch = category['branch'] as String? ?? '';
                      final image = category['image'] as String?;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: StcSurfaceCard(
                          onTap: () => stcOpenCategoryHub(context, widget.tournamentId, id),
                          padding: const EdgeInsets.fromLTRB(15, 14, 15, 14),
                          child: Row(
                            children: [
                              Container(
                                width: 52,
                                height: 52,
                                decoration: BoxDecoration(
                                  color: StcColors.surfaceInner,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.45)),
                                ),
                                alignment: Alignment.center,
                                clipBehavior: Clip.antiAlias,
                                child: image != null && image.isNotEmpty
                                    ? Image.network(
                                        image,
                                        width: 52,
                                        height: 52,
                                        fit: BoxFit.contain,
                                        errorBuilder: (_, __, ___) => const Icon(Icons.category_outlined, color: StcColors.cyan, size: 26),
                                      )
                                    : const Icon(Icons.category_outlined, color: StcColors.cyan, size: 26),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
                                    if (branch.isNotEmpty)
                                      Text(branch, style: const TextStyle(color: StcColors.cyan, fontSize: 10, fontWeight: FontWeight.w600)),
                                    Text(
                                      [modality, format].where((s) => s.isNotEmpty).join(' · '),
                                      style: const TextStyle(color: StcColors.textBody, fontSize: 10),
                                    ),
                                  ],
                                ),
                              ),
                              const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
                            ],
                          ),
                        ),
                      );
                    }),
                ],
              ),
            ),
    );
  }
}
