import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_bottom_nav.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class TeamProfileScreen extends ConsumerStatefulWidget {
  const TeamProfileScreen({super.key, required this.teamId, this.tournamentId});

  final int teamId;
  final int? tournamentId;

  @override
  ConsumerState<TeamProfileScreen> createState() => _TeamProfileScreenState();
}

class _TeamProfileScreenState extends ConsumerState<TeamProfileScreen> {
  Map<String, dynamic>? _team;
  Map<String, dynamic>? _standing;
  Map<String, dynamic>? _nextMatch;
  List<String> _form = [];
  String? _modality;
  bool _showFullRoster = false;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repo = ref.read(catalogRepositoryProvider);
      final team = await repo.fetchTeam(widget.teamId);
      final categoryName = team['category'] as String?;

      Map<String, dynamic>? standing;
      Map<String, dynamic>? nextMatch;
      var form = <String>[];
      String? modality;

      await Future.wait([
        () async {
          try {
            standing = await repo.fetchTeamStandingRow(
              widget.teamId,
              categoryName,
              tournamentId: widget.tournamentId,
            );
          } catch (_) {}
        }(),
        () async {
          try {
            nextMatch = await repo.fetchNextMatchForTeam(
              widget.teamId,
              tournamentId: widget.tournamentId,
            );
          } catch (_) {}
        }(),
        () async {
          try {
            form = await repo.fetchTeamForm(
              widget.teamId,
              tournamentId: widget.tournamentId,
            );
          } catch (_) {}
        }(),
        () async {
          if (widget.tournamentId == null || categoryName == null) return;
          try {
            final tournament = await repo.fetchTournament(widget.tournamentId!);
            for (final category in (tournament['categories'] as List<dynamic>? ?? [])) {
              final cat = category as Map<String, dynamic>;
              if (cat['name'] == categoryName) {
                final detail = await repo.fetchCategory(cat['id'] as int);
                modality = detail['modality'] as String?;
                break;
              }
            }
          } catch (_) {}
        }(),
      ]);

      if (mounted) {
        setState(() {
          _team = team;
          _standing = standing;
          _nextMatch = nextMatch;
          _form = form;
          _modality = modality;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _loading = false;
          _team = null;
          _error = 'No pudimos cargar el perfil del equipo.';
        });
      }
    }
  }

  List<Map<String, dynamic>> get _players {
    final list = (_team?['players'] as List<dynamic>? ?? [])
        .map((p) => Map<String, dynamic>.from(p as Map))
        .toList();
    list.sort((a, b) => ((a['jersey'] as num?) ?? 999).compareTo((b['jersey'] as num?) ?? 999));
    return list;
  }

  @override
  Widget build(BuildContext context) {
    final visiblePlayers = _showFullRoster ? _players : _players.take(5).toList();

    return StcBackScope(
      child: Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(
                title: 'PERFIL DEL EQUIPO',
                onBack: () => stcGoBack(context),
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : _team == null
                        ? StcEmptyState(
                            kind: StcEmptyKind.offline,
                            message: _error ?? 'Equipo no encontrado.',
                            actionLabel: 'Reintentar',
                            onAction: _load,
                          )
                        : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(17, 8, 17, 12),
                              children: [
                                StcTeamHeroCard(team: _team!, modality: _modality),
                                const SizedBox(height: 18),
                                StcTeamStatsBar(
                                  played: _standing?['played'] as int? ?? 0,
                                  points: _standing?['points'] as int? ?? 0,
                                  gf: _standing?['gf'] as int? ?? 0,
                                  gc: _standing?['ga'] as int? ?? 0,
                                  form: _form,
                                ),
                                const SizedBox(height: 18),
                                const Text('PLANTEL', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
                                const SizedBox(height: 12),
                                StcSurfaceCard(
                                  padding: const EdgeInsets.fromLTRB(15, 8, 15, 12),
                                  child: Column(
                                    children: [
                                      if (_players.isEmpty)
                                        const Padding(
                                          padding: EdgeInsets.symmetric(vertical: 16),
                                          child: Text('Sin jugadores publicados', style: TextStyle(color: StcColors.textBody, fontSize: 10)),
                                        )
                                      else
                                        ...visiblePlayers.map(
                                          (player) => StcRosterRow(
                                            player: player,
                                            onTap: () {
                                              final id = player['id'] as int?;
                                              if (id != null) stcOpenPlayer(context, id);
                                            },
                                          ),
                                        ),
                                      if (_players.length > 5)
                                        Padding(
                                          padding: const EdgeInsets.only(top: 8),
                                          child: InkWell(
                                            onTap: () => setState(() => _showFullRoster = !_showFullRoster),
                                            borderRadius: BorderRadius.circular(12),
                                            child: Container(
                                              height: 28,
                                              alignment: Alignment.center,
                                              decoration: BoxDecoration(
                                                color: StcColors.surface,
                                                borderRadius: BorderRadius.circular(12),
                                                border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.85)),
                                              ),
                                              child: Text(
                                                _showFullRoster ? 'VER MENOS' : 'VER PLANTEL COMPLETO',
                                                style: const TextStyle(color: StcColors.primaryBlue, fontSize: 10.5, fontWeight: FontWeight.w800),
                                              ),
                                            ),
                                          ),
                                        ),
                                    ],
                                  ),
                                ),
                                if (_nextMatch != null) ...[
                                  const SizedBox(height: 18),
                                  const Text('PRÓXIMO PARTIDO', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
                                  const SizedBox(height: 12),
                                  StcNextMatchCard(
                                    match: _nextMatch!,
                                    teamId: widget.teamId,
                                    onTap: () {
                                      final id = _nextMatch!['id'] as int?;
                                      if (id != null) stcOpenMatch(context, id);
                                    },
                                  ),
                                ],
                              ],
                            ),
                          ),
              ),
              StcBottomNav(currentIndex: stcNavIndexForPath('/teams/${widget.teamId}')),
            ],
          ),
        ),
      ),
    ),
    );
  }
}
