import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class ContentListScreen extends ConsumerStatefulWidget {
  const ContentListScreen({super.key, this.tournamentId});

  final int? tournamentId;

  @override
  ConsumerState<ContentListScreen> createState() => _ContentListScreenState();
}

class _ContentListScreenState extends ConsumerState<ContentListScreen> {
  List<Map<String, dynamic>> _posts = [];
  String _filter = 'all';
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final posts = await ref.read(catalogRepositoryProvider).fetchContent(
            tournamentId: widget.tournamentId,
            type: _filter == 'all' ? null : _filter,
          );
      if (mounted) {
        setState(() {
          _posts = posts;
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

  String _formatDate(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    final dt = DateTime.tryParse(raw)?.toLocal();
    if (dt == null) return '';
    return DateFormat("d MMM yyyy", 'es').format(dt);
  }

  @override
  Widget build(BuildContext context) {
    return StcBackScope(
      child: Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'NOVEDADES', onBack: () => stcGoBack(context)),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : RefreshIndicator(
                        color: StcColors.cyan,
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
                          children: [
                            const Text(
                              'COMUNICADOS Y PLACAS',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                            ),
                            const SizedBox(height: 14),
                            SingleChildScrollView(
                              scrollDirection: Axis.horizontal,
                              child: Row(
                                children: [
                                  StcWhiteFilterChip(label: 'Todos', selected: _filter == 'all', onTap: () => _setFilter('all'), width: 72),
                                  StcWhiteFilterChip(label: 'Novedades', selected: _filter == 'news', onTap: () => _setFilter('news'), width: 96),
                                  StcWhiteFilterChip(label: 'Placas', selected: _filter == 'plaque', onTap: () => _setFilter('plaque'), width: 78),
                                ],
                              ),
                            ),
                            const SizedBox(height: 18),
                            if (_posts.isEmpty)
                              StcEmptyState(
                                kind: StcEmptyKind.noData,
                                title: 'Sin contenido',
                                message: 'Todavía no hay comunicados publicados.',
                                actionLabel: _filter == 'plaque' ? null : 'Ver placas',
                                onAction: _filter == 'plaque' ? null : () => context.push('/content/plaques'),
                              )
                            else
                              ..._posts.map((post) {
                                final slug = post['slug'] as String? ?? '';
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: StcSurfaceCard(
                                    onTap: slug.isNotEmpty ? () => stcOpenContentDetail(context, slug) : null,
                                    padding: const EdgeInsets.fromLTRB(15, 13, 15, 13),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          (post['type_label'] as String? ?? 'Contenido').toUpperCase(),
                                          style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 9),
                                        ),
                                        const SizedBox(height: 8),
                                        Text(
                                          post['title'] as String? ?? '',
                                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12),
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          post['summary'] as String? ?? '',
                                          maxLines: 3,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(color: StcColors.textBody, fontSize: 10, height: 1.35),
                                        ),
                                        const SizedBox(height: 10),
                                        Text(
                                          _formatDate(post['published_at'] as String?),
                                          style: const TextStyle(color: StcColors.primaryBlue, fontSize: 9, fontWeight: FontWeight.w700),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              }),
                          ],
                        ),
                      ),
              ),
            ],
          ),
        ),
      ),
    ),
    );
  }
}
