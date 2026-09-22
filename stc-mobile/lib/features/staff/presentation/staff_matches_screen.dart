import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../staff_providers.dart';

class StaffMatchesScreen extends ConsumerStatefulWidget {
  const StaffMatchesScreen({super.key, this.initialScope = 'today'});

  final String initialScope;

  @override
  ConsumerState<StaffMatchesScreen> createState() => _StaffMatchesScreenState();
}

class _StaffMatchesScreenState extends ConsumerState<StaffMatchesScreen> {
  late String _scope;
  List<Map<String, dynamic>> _matches = [];
  bool _loading = true;
  bool _offline = false;

  @override
  void initState() {
    super.initState();
    _scope = widget.initialScope;
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _offline = false;
    });
    try {
      final matches = await ref.read(staffRepositoryProvider).fetchMatches(scope: _scope);
      if (mounted) {
        setState(() {
          _matches = matches;
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

  Future<void> _setScope(String scope) async {
    setState(() => _scope = scope);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'STAFF', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : _offline
                        ? StcEmptyState(
                            kind: StcEmptyKind.offline,
                            actionLabel: 'Reintentar',
                            onAction: _load,
                          )
                        : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                              children: [
                                const Text(
                                  'PARTIDOS',
                                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24),
                                ),
                                const SizedBox(height: 4),
                                const Text(
                                  'Planillas, eventos e informe arbitral',
                                  style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                                ),
                                const SizedBox(height: 14),
                                SingleChildScrollView(
                                  scrollDirection: Axis.horizontal,
                                  child: Row(
                                    children: [
                                      StcWhiteFilterChip(label: 'Hoy', selected: _scope == 'today', onTap: () => _setScope('today'), width: 64),
                                      StcWhiteFilterChip(label: 'En vivo', selected: _scope == 'live', onTap: () => _setScope('live'), width: 78),
                                      StcWhiteFilterChip(label: 'Asignados', selected: _scope == 'mine', onTap: () => _setScope('mine'), width: 92),
                                      StcWhiteFilterChip(label: 'Todos', selected: _scope == 'all', onTap: () => _setScope('all'), width: 72),
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 18),
                                if (_matches.isEmpty)
                                  const StcEmptyState(kind: StcEmptyKind.noData, message: 'No hay partidos para este filtro.')
                                else
                                  ..._matches.map((match) {
                                    return Padding(
                                      padding: const EdgeInsets.only(bottom: 10),
                                      child: StcFixtureMatchRow(
                                        match: match,
                                        onTap: () => context.push('/staff/matches/${match['id']}'),
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
    );
  }
}
