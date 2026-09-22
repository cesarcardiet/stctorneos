import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_bottom_nav.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../home/presentation/home_screen.dart';

class LiveScreen extends ConsumerStatefulWidget {
  const LiveScreen({super.key});

  @override
  ConsumerState<LiveScreen> createState() => _LiveScreenState();
}

class _LiveScreenState extends ConsumerState<LiveScreen> {
  List<Map<String, dynamic>> _liveMatches = [];
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
      final home = await repo.fetchHome();
      final fromHome = (home['live_matches'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();
      final fromApi = await repo.fetchMatches(status: 'live');
      final seen = <int>{};
      final merged = <Map<String, dynamic>>[];

      for (final match in [...fromHome, ...fromApi]) {
        final id = match['id'] as int?;
        if (id == null || seen.contains(id)) continue;
        seen.add(id);
        merged.add(match);
      }

      if (mounted) {
        setState(() {
          _liveMatches = merged;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
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
              StcPublicHeader(title: 'EN VIVO', onBack: () => stcGoBack(context)),
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
                              'PARTIDOS EN VIVO',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              _liveMatches.isEmpty
                                  ? 'No hay partidos en curso'
                                  : '${_liveMatches.length} partido${_liveMatches.length == 1 ? '' : 's'} en curso',
                              style: const TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 18),
                            if (_liveMatches.isEmpty)
                              const StcEmptyState(
                                kind: StcEmptyKind.noData,
                                title: 'Sin transmisión activa',
                                message: 'Cuando haya partidos en vivo aparecerán aquí con marcador y minuto.',
                              )
                            else
                              ..._liveMatches.map((match) {
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: StcFixtureMatchRow(
                                    match: match,
                                    onTap: () {
                                      final id = match['id'] as int?;
                                      if (id != null) stcOpenMatch(context, id);
                                    },
                                  ),
                                );
                              }),
                          ],
                        ),
                      ),
              ),
              StcBottomNav(currentIndex: stcNavIndexForPath('/live')),
            ],
          ),
        ),
      ),
    ),
    );
  }
}
