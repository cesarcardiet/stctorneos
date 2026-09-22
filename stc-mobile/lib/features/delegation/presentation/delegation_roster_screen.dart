import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_form_field.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../profile/profile_providers.dart';

class DelegationTeamsListScreen extends ConsumerStatefulWidget {
  const DelegationTeamsListScreen({super.key});

  @override
  ConsumerState<DelegationTeamsListScreen> createState() => _DelegationTeamsListScreenState();
}

class _DelegationTeamsListScreenState extends ConsumerState<DelegationTeamsListScreen> {
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
      final workspace = await ref.read(accountPortalRepositoryProvider).fetchWorkspace();
      if (mounted) {
        setState(() {
          _teams = (workspace['teams'] as List<dynamic>? ?? [])
              .map((item) => Map<String, dynamic>.from(item as Map))
              .toList();
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'MIS EQUIPOS', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : RefreshIndicator(
                        color: StcColors.cyan,
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                          children: [
                            const Text(
                              'TODOS LOS EQUIPOS',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Equipos de tu delegación',
                              style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 18),
                            if (_teams.isEmpty)
                              const StcInfoCard(title: 'Sin equipos', body: 'Todavía no hay equipos vinculados a tu delegación.')
                            else
                              ..._teams.map((team) => Padding(
                                    padding: const EdgeInsets.only(bottom: 8),
                                    child: _TeamRowCard(
                                      team: team,
                                      onTap: () => context.push('/workspace/teams/${team['id']}'),
                                    ),
                                  )),
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

class DelegationRosterScreen extends ConsumerStatefulWidget {
  const DelegationRosterScreen({super.key, this.teamId});

  final int? teamId;

  @override
  ConsumerState<DelegationRosterScreen> createState() => _DelegationRosterScreenState();
}

class _DelegationRosterScreenState extends ConsumerState<DelegationRosterScreen> {
  Map<String, dynamic>? _data;
  List<Map<String, dynamic>> _teams = [];
  int? _selectedTeamId;
  bool _loading = true;
  final _search = TextEditingController();

  @override
  void initState() {
    super.initState();
    _selectedTeamId = widget.teamId;
    _search.addListener(() => setState(() {}));
    _bootstrap();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> _uniqueTeams(List<Map<String, dynamic>> teams) {
    final seen = <int>{};
    return teams.where((team) => seen.add(team['id'] as int)).toList();
  }

  Future<void> _bootstrap() async {
    setState(() => _loading = true);
    try {
      final workspace = await ref.read(accountPortalRepositoryProvider).fetchWorkspace();
      final teams = _uniqueTeams((workspace['teams'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList());
      if (mounted) {
        setState(() => _teams = teams);
      }
      if (_selectedTeamId != null) {
        await _loadRoster(_selectedTeamId!);
      } else if (mounted) {
        setState(() => _loading = false);
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _loadRoster(int teamId) async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(accountPortalRepositoryProvider).fetchWorkspaceTeamPlayers(teamId);
      if (mounted) {
        setState(() {
          _data = data;
          _selectedTeamId = teamId;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<Map<String, dynamic>> get _filteredTeams {
    final query = _search.text.trim().toLowerCase();
    if (query.isEmpty) return _teams;
    return _teams.where((team) {
      final haystack = [
        team['name'],
        team['category'],
        team['tournament_name'],
        team['delegation_name'],
      ].whereType<String>().join(' ').toLowerCase();
      return haystack.contains(query);
    }).toList();
  }

  Map<String, Map<String, List<Map<String, dynamic>>>> get _groupedTeams {
    final grouped = <String, Map<String, List<Map<String, dynamic>>>>{};
    for (final team in _filteredTeams) {
      final tournament = team['tournament_name'] as String? ?? 'Sin torneo';
      final category = team['category'] as String? ?? 'Sin categoría';
      grouped.putIfAbsent(tournament, () => {});
      grouped[tournament]!.putIfAbsent(category, () => []);
      grouped[tournament]![category]!.add(team);
    }
    return grouped;
  }

  @override
  Widget build(BuildContext context) {
    final team = _data?['team'] as Map<String, dynamic>?;
    final players = (_data?['players'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final rosterEditable = team?['roster_editable'] as bool? ?? false;
    final showingList = widget.teamId == null && _selectedTeamId == null;

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(
                title: 'BUENA FE',
                onBack: () {
                  if (widget.teamId == null && _selectedTeamId != null) {
                    setState(() {
                      _selectedTeamId = null;
                      _data = null;
                    });
                    return;
                  }
                  context.pop();
                },
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : showingList
                        ? _buildTeamPicker()
                        : _buildRoster(team, players, rosterEditable),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTeamPicker() {
    final grouped = _groupedTeams;
    return RefreshIndicator(
      color: StcColors.cyan,
      onRefresh: _bootstrap,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
        children: [
          const Text('LISTA DE BUENA FE', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
          const SizedBox(height: 4),
          const Text('Elegí el equipo de su torneo y categoría', style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600)),
          const SizedBox(height: 14),
          TextField(
            controller: _search,
            style: const TextStyle(color: Colors.white, fontSize: 14),
            decoration: InputDecoration(
              hintText: 'Buscar equipo, categoría o torneo',
              hintStyle: const TextStyle(color: StcColors.textMuted, fontSize: 13),
              prefixIcon: const Icon(Icons.search, color: StcColors.cyan, size: 20),
              filled: true,
              fillColor: StcColors.surface,
              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: StcColors.borderSoft)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: StcColors.borderSoft)),
            ),
          ),
          const SizedBox(height: 16),
          if (grouped.isEmpty)
            const StcInfoCard(title: 'Sin equipos', body: 'No hay equipos de tu delegación para esa búsqueda.')
          else
            ...grouped.entries.expand((tournament) sync* {
              yield Padding(
                padding: const EdgeInsets.only(top: 6, bottom: 8),
                child: Text(tournament.key.toUpperCase(), style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
              );
              for (final category in tournament.value.entries) {
                yield Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Text(category.key, style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700, fontSize: 11)),
                );
                for (final item in category.value) {
                  yield Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: _TeamRowCard(team: item, onTap: () => _loadRoster(item['id'] as int)),
                  );
                }
              }
            }),
        ],
      ),
    );
  }

  Widget _buildRoster(Map<String, dynamic>? team, List<Map<String, dynamic>> players, bool rosterEditable) {
    final categoryId = team?['category_id'] as int?;
    final tournamentName = team?['tournament_name'] as String?;
    final categoryName = team?['category'] as String?;

    return RefreshIndicator(
      color: StcColors.cyan,
      onRefresh: () => _selectedTeamId == null ? _bootstrap() : _loadRoster(_selectedTeamId!),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
        children: [
          const Text('LISTA DE BUENA FE', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
          const SizedBox(height: 4),
          Text(team?['name'] as String? ?? 'Plantel del equipo', style: const TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600)),
          if (tournamentName != null || categoryName != null)
            Text(
              [tournamentName, categoryName].whereType<String>().where((value) => value.isNotEmpty).join(' · '),
              style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
            ),
          const SizedBox(height: 18),
          if (team?['roster_lock_reason'] != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: StcInfoCard(title: 'Plantel bloqueado', body: team!['roster_lock_reason'] as String),
            ),
          if (players.isEmpty)
            const StcInfoCard(title: 'Sin jugadores', body: 'Agregá jugadores para completar la lista de buena fe.')
          else
            ...players.map((player) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: _PlayerRosterCard(
                    player: player,
                    onTap: () {
                      final playerCategory = player['category_id'] as int? ?? categoryId;
                      final teamId = player['team_id'] as int? ?? _selectedTeamId;
                      if (playerCategory == null || teamId == null) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Este jugador no tiene categoría asignada.')),
                        );
                        return;
                      }
                      context.push('/players/new?player=${player['id']}&category=$playerCategory&team=$teamId');
                    },
                  ),
                )),
          if (rosterEditable && categoryId != null) ...[
            const SizedBox(height: 12),
            StcPrimaryButton(
              label: 'Agregar jugador',
              onPressed: () => context.push('/players/new?team=$_selectedTeamId&category=$categoryId'),
            ),
          ],
        ],
      ),
    );
  }
}

class DelegationInscriptionsScreen extends ConsumerStatefulWidget {
  const DelegationInscriptionsScreen({
    super.key,
    this.categoryId,
    this.teamId,
  });

  final int? categoryId;
  final int? teamId;

  @override
  ConsumerState<DelegationInscriptionsScreen> createState() => _DelegationInscriptionsScreenState();
}

class _DelegationInscriptionsScreenState extends ConsumerState<DelegationInscriptionsScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(accountPortalRepositoryProvider).fetchWorkspaceInscriptions(
            categoryId: widget.categoryId,
            teamId: widget.teamId,
          );
      if (mounted) {
        setState(() {
          _data = data;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final waiting = (_data?['waiting'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final rejected = (_data?['rejected'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
    final category = _data?['category'] as Map<String, dynamic>?;

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'SEGUIMIENTO', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : RefreshIndicator(
                        color: StcColors.cyan,
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
                          children: [
                            const Text(
                              'SEGUIMIENTO DE FICHAS',
                              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              category?['name'] as String? ?? 'Estado de inscripciones',
                              style: const TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                            const SizedBox(height: 18),
                            _InscriptionBand(
                              title: 'ESPERANDO APROBACIÓN',
                              count: _data?['waiting_count'] as int? ?? waiting.length,
                              players: waiting,
                              tone: const Color(0xFFFFB300),
                            ),
                            const SizedBox(height: 14),
                            _InscriptionBand(
                              title: 'RECHAZADAS',
                              count: _data?['rejected_count'] as int? ?? rejected.length,
                              players: rejected,
                              tone: const Color(0xFFFF5252),
                            ),
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

class _TeamRowCard extends StatelessWidget {
  const _TeamRowCard({required this.team, required this.onTap});

  final Map<String, dynamic> team;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final open = team['roster_editable'] as bool? ?? false;

    return StcSurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Row(
        children: [
          StcTeamShield.fromTeam(team: team, size: 36),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  (team['name'] as String? ?? 'Equipo').toUpperCase(),
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12),
                ),
                Text(
                  '${team['tournament_name'] ?? '—'} · ${team['category'] ?? '—'}',
                  style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                ),
                Text(
                  team['registration_label'] as String? ?? '—',
                  style: TextStyle(color: open ? StcColors.cyan : StcColors.textMuted, fontSize: 10, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class _PlayerRosterCard extends StatelessWidget {
  const _PlayerRosterCard({required this.player, required this.onTap});

  final Map<String, dynamic> player;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final progress = player['progress'] as Map<String, dynamic>? ?? {};

    return StcSurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Row(
        children: [
          StcPhotoPicker(photoUrl: player['photo'] as String?, placeholder: 'FOTO', square: true),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  player['name'] as String? ?? 'Jugador',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14),
                ),
                Text(
                  '${player['position'] ?? '—'} · #${player['jersey_number'] ?? '—'}',
                  style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                ),
                Text(
                  progress['review'] as String? ?? player['status_label'] as String? ?? '—',
                  style: const TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w600),
                ),
              ],
            ),
          ),
          const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class _InscriptionBand extends StatelessWidget {
  const _InscriptionBand({
    required this.title,
    required this.count,
    required this.players,
    required this.tone,
  });

  final String title;
  final int count;
  final List<Map<String, dynamic>> players;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: StcColors.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: tone.withValues(alpha: 0.6)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: TextStyle(color: tone, fontWeight: FontWeight.w800, fontSize: 11)),
          const SizedBox(height: 4),
          Text('$count ${count == 1 ? 'ficha' : 'fichas'}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18)),
          const SizedBox(height: 12),
          if (players.isEmpty)
            const Text('Sin fichas en esta banda.', style: TextStyle(color: StcColors.textMuted))
          else
            ...players.map((player) {
              final categoryId = player['category_id'] as int?;
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: StcSurfaceCard(
                  onTap: categoryId == null
                      ? null
                      : () => context.push('/players/new?player=${player['id']}&category=$categoryId&team=${player['team_id']}'),
                  padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(player['name'] as String? ?? 'Jugador', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                            Text(
                              '${player['team_name'] ?? '—'} · ${player['status_label'] ?? '—'}',
                              style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                            ),
                          ],
                        ),
                      ),
                      if (player['can_edit'] == true)
                        const Text('Editar', style: TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
              );
            }),
        ],
      ),
    );
  }
}
