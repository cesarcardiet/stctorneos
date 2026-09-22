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

class PlaquesListScreen extends ConsumerStatefulWidget {
  const PlaquesListScreen({super.key, this.tournamentId});

  final int? tournamentId;

  @override
  ConsumerState<PlaquesListScreen> createState() => _PlaquesListScreenState();
}

class _PlaquesListScreenState extends ConsumerState<PlaquesListScreen> {
  List<Map<String, dynamic>> _posts = [];
  bool _loading = true;
  bool _offline = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _offline = false;
    });
    try {
      final posts = await ref.read(catalogRepositoryProvider).fetchContent(
            tournamentId: widget.tournamentId,
            type: 'plaque',
          );
      if (mounted) {
        setState(() {
          _posts = posts;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _offline = true;
        });
      }
    }
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
              StcPublicHeader(title: 'PLACAS OFICIALES', onBack: () => stcGoBack(context)),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : _offline
                        ? StcEmptyState(kind: StcEmptyKind.offline, actionLabel: 'Reintentar', onAction: _load)
                        : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                              children: [
                                const Text(
                                  'RESULTADOS OFICIALES',
                                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                                ),
                                const SizedBox(height: 6),
                                const Text(
                                  'Placas publicadas desde planillas cerradas',
                                  style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                                ),
                                const SizedBox(height: 18),
                                if (_posts.isEmpty)
                                  const StcEmptyState(
                                    kind: StcEmptyKind.noData,
                                    message: 'Todavía no hay placas publicadas para este torneo.',
                                  )
                                else
                                  ..._posts.map((post) {
                                    final slug = post['slug'] as String? ?? '';
                                    final cover = post['cover'] as String?;
                                    return Padding(
                                      padding: const EdgeInsets.only(bottom: 12),
                                      child: StcSurfaceCard(
                                        onTap: slug.isNotEmpty ? () => context.push('/content/$slug') : null,
                                        padding: EdgeInsets.zero,
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.stretch,
                                          children: [
                                            if (cover != null && cover.isNotEmpty)
                                              ClipRRect(
                                                borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                                                child: ColoredBox(
                                                  color: const Color(0xFF071428),
                                                  child: Image.network(
                                                    cover,
                                                    height: 280,
                                                    width: double.infinity,
                                                    fit: BoxFit.contain,
                                                    errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                                                  ),
                                                ),
                                              ),
                                            Padding(
                                              padding: const EdgeInsets.fromLTRB(15, 13, 15, 13),
                                              child: Column(
                                                crossAxisAlignment: CrossAxisAlignment.start,
                                                children: [
                                                  const Text(
                                                    'PLACA OFICIAL',
                                                    style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 9),
                                                  ),
                                                  const SizedBox(height: 8),
                                                  Text(
                                                    post['title'] as String? ?? '',
                                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 14),
                                                  ),
                                                  const SizedBox(height: 6),
                                                  Text(
                                                    post['summary'] as String? ?? '',
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
