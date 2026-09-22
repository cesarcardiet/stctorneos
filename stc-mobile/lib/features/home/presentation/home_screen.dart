import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/network/dio_client.dart';
import '../../../shared/data/catalog_repository.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/widgets/stc_bottom_nav.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';

final catalogRepositoryProvider = Provider<CatalogRepository>((ref) {
  return CatalogRepository(ref.watch(dioClientProvider));
});

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});

  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen> {
  Map<String, dynamic>? _homeData;
  List<Map<String, dynamic>> _standings = [];
  List<Map<String, dynamic>> _news = [];
  Map<String, dynamic>? _topScorer;
  List<Map<String, dynamic>> _featuredMatches = [];
  int? _tournamentId;
  int _unreadNotifications = 0;
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
      final home = await repo.fetchHome();
      List<Map<String, dynamic>> standings = [];
      List<Map<String, dynamic>> featuredMatches = [];
      Map<String, dynamic>? topScorer;
      int? tournamentId;

      final live = (home['live_matches'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
      final upcoming = (home['upcoming'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
      final seen = <int>{};
      for (final match in [...live, ...upcoming]) {
        final id = match['id'] as int?;
        if (id == null || seen.contains(id)) continue;
        seen.add(id);
        featuredMatches.add(match);
      }

      final tournament = home['tournament'] as Map<String, dynamic>?;
      if (tournament != null) {
        tournamentId = tournament['id'] as int?;
        if (featuredMatches.length < 2 && tournamentId != null) {
          try {
            final allMatches = await repo.fetchMatches(tournamentId: tournamentId);
            for (final match in allMatches) {
              final id = match['id'] as int?;
              if (id == null || seen.contains(id)) continue;
              final status = match['status'] as String? ?? '';
              if (status == 'live' || status == 'scheduled' || status == 'finished' || status == 'validated') {
                seen.add(id);
                featuredMatches.add(match);
                if (featuredMatches.length >= 2) break;
              }
            }
          } catch (_) {}
        }

        try {
          final detail = await repo.fetchTournament(tournamentId!);
          final categories = (detail['categories'] as List<dynamic>? ?? []);
          if (categories.isNotEmpty) {
            final categoryId = categories.first['id'];
            if (categoryId is int) {
              try {
                final standingsData = await repo.fetchStandings(categoryId);
                final groups = standingsData['groups'] as List<dynamic>? ?? [];
                if (groups.isNotEmpty) {
                  standings = (groups.first['rows'] as List<dynamic>? ?? [])
                      .cast<Map<String, dynamic>>()
                      .take(3)
                      .toList();
                }
              } catch (_) {}

              try {
                final rankings = await repo.fetchRankings(categoryId);
                final rankingGroups = rankings['rankings'] as List<dynamic>? ?? [];
                for (final group in rankingGroups) {
                  final map = Map<String, dynamic>.from(group as Map);
                  if ((map['title'] as String? ?? '').toLowerCase().contains('gol')) {
                    final players = map['players'] as List<dynamic>? ?? [];
                    if (players.isNotEmpty) {
                      topScorer = Map<String, dynamic>.from(players.first as Map);
                    }
                    break;
                  }
                }
              } catch (_) {}
            }
          }
        } catch (_) {}
      }

      final news = (home['news'] as List<dynamic>? ?? [])
          .map((item) => Map<String, dynamic>.from(item as Map))
          .toList();

      var unread = 0;
      if (ref.read(authControllerProvider).isAuthenticated) {
        try {
          final notifications = await repo.fetchNotifications();
          unread = notifications.where((item) => item['read'] != true).length;
        } catch (_) {}
      }

      if (mounted) {
        setState(() {
          _homeData = home;
          _standings = standings;
          _news = news;
          _topScorer = topScorer;
          _featuredMatches = featuredMatches.take(2).toList();
          _tournamentId = tournamentId;
          _unreadNotifications = unread;
          _loading = false;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error.toString();
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final tournament = _homeData?['tournament'] as Map<String, dynamic>?;
    final live = (_homeData?['live_matches'] as List<dynamic>? ?? []).cast<Map<String, dynamic>>();
    final featuredMatches = _featuredMatches;
    final auth = ref.watch(authControllerProvider);
    final roleShortcuts = auth.isAuthenticated
        ? auth.user!.navigation.where((item) {
            final key = item['key'] as String? ?? '';
            final enabled = item['enabled'] as bool? ?? true;
            final route = item['route'] as String?;
            return enabled &&
                route != null &&
                !{'home', 'favorites', 'notifications'}.contains(key);
          }).take(4).toList()
        : <Map<String, dynamic>>[];

    return StcHomeBackScope(
      child: Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcHomeHeader(
                unreadCount: _unreadNotifications,
                onNotificationsTap: () {
                  if (ref.read(authControllerProvider).isAuthenticated) {
                    context.push('/notifications');
                  } else {
                    context.go('/login');
                  }
                },
              ),
              Expanded(
                child: RefreshIndicator(
                  color: StcColors.cyan,
                  onRefresh: _load,
                  child: _loading
                      ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                      : _error != null
                          ? ListView(
                              physics: const AlwaysScrollableScrollPhysics(),
                              children: [
                                StcEmptyState(
                                  kind: StcEmptyKind.offline,
                                  message: 'No pudimos cargar el inicio.',
                                  actionLabel: 'Reintentar',
                                  onAction: _load,
                                ),
                              ],
                            )
                          : ListView(
                          padding: const EdgeInsets.fromLTRB(18, 0, 18, 12),
                          children: [
                            FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: Alignment.centerLeft,
                              child: Text(
                                'VIVÍ EL TORNEO',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 28,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 1.1,
                                ),
                              ),
                            ),
                            const Text('en tiempo real', style: TextStyle(color: StcColors.cyan, fontSize: 12, fontWeight: FontWeight.w600)),
                            if (roleShortcuts.isNotEmpty) ...[
                              const SizedBox(height: 16),
                              const Text('TU ACCESO RÁPIDO', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10)),
                              const SizedBox(height: 8),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: roleShortcuts.map((item) {
                                  final label = item['label'] as String? ?? 'Ir';
                                  final route = item['route'] as String?;
                                  return StcQuickChip(
                                    label: label,
                                    onTap: route == null ? () {} : () => context.push(route),
                                  );
                                }).toList(),
                              ),
                            ],
                            const SizedBox(height: 16),
                            if (tournament != null)
                              _FeaturedTournamentCard(
                                tournament: tournament,
                                liveCount: live.length,
                                onTap: () => stcOpenTournament(context, tournament['id'] as int),
                              ),
                            const SizedBox(height: 18),
                            Row(
                              children: [
                                const Text('PARTIDOS DEL TORNEO', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
                                const Spacer(),
                                TextButton(
                                  onPressed: () {
                                    if (_tournamentId != null) {
                                      stcOpenTournamentSection(context, _tournamentId!, 'fixture');
                                    } else {
                                      context.push('/tournaments');
                                    }
                                  },
                                  child: const Text('Ver todos ›', style: TextStyle(color: StcColors.cyan, fontSize: 10, fontWeight: FontWeight.w600)),
                                ),
                              ],
                            ),
                            const SizedBox(height: 8),
                            if (featuredMatches.isEmpty)
                              const StcEmptyState(kind: StcEmptyKind.noData, message: 'Todavía no hay partidos publicados para este torneo.')
                            else
                              ...featuredMatches.map((m) => Padding(
                                    padding: const EdgeInsets.only(bottom: 10),
                                    child: _HomeMatchCard(
                                      match: m,
                                      onTap: () {
                                        final id = m['id'] as int?;
                                        if (id != null) stcOpenMatch(context, id);
                                      },
                                    ),
                                  )),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: _MiniStandingsCard(
                                    rows: _standings,
                                    onTap: _tournamentId != null
                                        ? () => stcOpenTournamentSection(context, _tournamentId!, 'standings')
                                        : () => context.push('/tournaments'),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: _TopScorerCard(
                                    scorer: _topScorer,
                                    onTap: _tournamentId != null
                                        ? () => stcOpenTournamentSection(context, _tournamentId!, 'scorers')
                                        : () => context.push('/tournaments'),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 16),
                            if (_news.isNotEmpty) ...[
                              const SizedBox(height: 16),
                              StcNewsCard(
                                title: _news.first['title'] as String? ?? 'Novedades',
                                summary: _news.first['summary'] as String? ?? '',
                                onRead: () {
                                  final slug = _news.first['slug'] as String?;
                                  if (slug != null && slug.isNotEmpty) {
                                    stcOpenContentDetail(context, slug);
                                  } else {
                                    stcOpenContentList(context, tournamentId: _tournamentId);
                                  }
                                },
                              ),
                            ],
                            const SizedBox(height: 16),
                            _QuickAccessRow(
                              tournamentId: _tournamentId,
                              onTournaments: () => context.push('/tournaments'),
                            ),
                          ],
                        ),
                ),
              ),
              StcBottomNav(currentIndex: stcNavIndexForPath('/home'), tournamentId: _tournamentId),
            ],
          ),
        ),
      ),
    ),
    );
  }
}

class _FeaturedTournamentCard extends StatelessWidget {
  const _FeaturedTournamentCard({
    required this.tournament,
    required this.liveCount,
    required this.onTap,
  });

  final Map<String, dynamic> tournament;
  final int liveCount;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return StcSurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(17, 15, 17, 15),
      child: SizedBox(
        height: 92,
        child: Stack(
          children: [
            Padding(
              padding: const EdgeInsets.only(right: 72),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    (tournament['name'] as String? ?? '').toUpperCase(),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 13),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Fixture, resultados, tablas y jugadores destacados en un solo lugar.',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: StcColors.textBody, fontSize: 11, height: 1.35),
                  ),
                  const Spacer(),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                    decoration: BoxDecoration(
                      color: liveCount > 0 ? StcColors.liveRed : StcColors.surfaceInner,
                      borderRadius: BorderRadius.circular(11),
                      border: Border.all(
                        color: liveCount > 0
                            ? StcColors.liveRed.withValues(alpha: 0.8)
                            : StcColors.primaryBlue.withValues(alpha: 0.45),
                      ),
                    ),
                    child: Text(
                      liveCount > 0 ? 'EN VIVO $liveCount' : 'TORNEO ACTIVO',
                      style: TextStyle(
                        color: liveCount > 0 ? Colors.white : StcColors.cyan,
                        fontWeight: FontWeight.w800,
                        fontSize: 8,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            Positioned(
              right: 0,
              top: 4,
              child: Container(
                width: 66,
                height: 66,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: StcColors.surfaceInner,
                  border: Border.all(color: StcColors.cyan.withValues(alpha: 0.65)),
                ),
                child: const Text('🏆', style: TextStyle(fontSize: 28)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _HomeMatchCard extends StatelessWidget {
  const _HomeMatchCard({required this.match, required this.onTap});

  final Map<String, dynamic> match;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final home = match['home'] as Map<String, dynamic>?;
    final away = match['away'] as Map<String, dynamic>?;
    final isLive = match['status'] == 'live';
    final scheduled = match['scheduled_at'] as String?;
    String timeLabel = '';
    if (scheduled != null) {
      final dt = DateTime.tryParse(scheduled);
      if (dt != null) timeLabel = DateFormat('HH:mm').format(dt.toLocal());
    }

    final homeName = home?['name'] as String? ?? 'Local';
    final awayName = away?['name'] as String? ?? 'Visitante';

    return StcSurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(15, 17, 12, 17),
      child: SizedBox(
        height: 54,
        child: Stack(
          alignment: Alignment.center,
          children: [
            Row(
              children: [
                StcTeamShield.fromTeam(team: home ?? {'name': homeName}, size: 42),
                const Spacer(),
                StcTeamShield.fromTeam(team: away ?? {'name': awayName}, size: 42),
              ],
            ),
            Positioned(
              left: 50,
              top: 0,
              child: Text(homeName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10)),
            ),
            Positioned(
              right: 50,
              top: 0,
              child: Text(awayName, textAlign: TextAlign.right, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10)),
            ),
            Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  isLive ? (match['score'] as String? ?? '0 - 0') : 'VS',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 26, height: 1),
                ),
                Text(
                  isLive
                      ? 'EN VIVO · ${match['minute'] ?? 0}\''
                      : (timeLabel.isNotEmpty ? '$timeLabel · ${match['field'] ?? ''}' : ''),
                  style: TextStyle(
                    color: isLive ? StcColors.liveRed : StcColors.cyan,
                    fontSize: 9,
                    fontWeight: FontWeight.w800,
                    height: 1,
                  ),
                ),
              ],
            ),
            const Positioned(right: 0, child: Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800))),
          ],
        ),
      ),
    );
  }
}

class _MiniStandingsCard extends StatelessWidget {
  const _MiniStandingsCard({required this.rows, required this.onTap});

  final List<Map<String, dynamic>> rows;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return StcSurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(13, 11, 13, 11),
      child: SizedBox(
        height: 100,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('TABLA', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
            const SizedBox(height: 8),
            if (rows.isEmpty)
              const Text('Sin datos', style: TextStyle(color: StcColors.textBody, fontSize: 10))
            else
              ...rows.map((row) {
                final team = row['team'] as Map<String, dynamic>?;
                final rawName = team?['name'] as String? ?? '';
                final name = rawName.length > 12 ? rawName.substring(0, 12) : rawName.padRight(12);
                return Text(
                  '${row['position']}  $name  ${row['points']} pts',
                  style: const TextStyle(color: StcColors.textBody, fontSize: 10, fontWeight: FontWeight.w600),
                );
              }),
            const Spacer(),
            const Text('VER TABLA', style: TextStyle(color: StcColors.primaryBlue, fontSize: 9, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _TopScorerCard extends StatelessWidget {
  const _TopScorerCard({this.scorer, required this.onTap});

  final Map<String, dynamic>? scorer;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final photo = scorer?['photo'] as String?;

    return StcSurfaceCard(
      onTap: onTap,
      padding: const EdgeInsets.fromLTRB(13, 11, 13, 11),
      child: SizedBox(
        height: 100,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('GOLEADORES', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
            const SizedBox(height: 6),
            if (scorer == null)
              const Text('Sin datos', style: TextStyle(color: StcColors.textBody, fontSize: 10))
            else
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.6)),
                      image: photo != null && photo.isNotEmpty
                          ? DecorationImage(image: NetworkImage(photo), fit: BoxFit.cover)
                          : null,
                      color: StcColors.surfaceInner,
                    ),
                    alignment: Alignment.center,
                    child: photo == null || photo.isEmpty
                        ? Text((scorer!['name'] as String? ?? '?')[0], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800))
                        : null,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(scorer!['name'] as String? ?? '', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10)),
                        Text('${scorer!['value'] ?? 0} goles', style: const TextStyle(color: StcColors.cyan, fontSize: 10, fontWeight: FontWeight.w600)),
                      ],
                    ),
                  ),
                ],
              ),
            const Spacer(),
            const Text('VER TODOS', style: TextStyle(color: StcColors.primaryBlue, fontSize: 9, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _QuickAccessRow extends StatelessWidget {
  const _QuickAccessRow({required this.onTournaments, this.tournamentId});

  final VoidCallback onTournaments;
  final int? tournamentId;

  @override
  Widget build(BuildContext context) {
    void goFixture() {
      if (tournamentId != null) {
        stcOpenTournamentSection(context, tournamentId!, 'fixture');
      } else {
        onTournaments();
      }
    }

    void goStandings() {
      if (tournamentId != null) {
        stcOpenTournamentSection(context, tournamentId!, 'standings');
      } else {
        onTournaments();
      }
    }

    return StcSurfaceCard(
      padding: const EdgeInsets.fromLTRB(13, 11, 13, 11),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('ACCESOS RÁPIDOS', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
          const SizedBox(height: 8),
          Wrap(
            spacing: 4,
            children: [
              _QuickLink(label: 'Fixture', onTap: goFixture),
              const Text('·', style: TextStyle(color: Colors.white)),
              _QuickLink(label: 'Tablas', onTap: goStandings),
              const Text('·', style: TextStyle(color: Colors.white)),
              _QuickLink(label: 'Equipos', onTap: () {
                if (tournamentId != null) {
                  stcOpenTournamentSection(context, tournamentId!, 'teams');
                } else {
                  onTournaments();
                }
              }),
              const Text('·', style: TextStyle(color: Colors.white)),
              _QuickLink(label: 'Sedes', onTap: goFixture),
            ],
          ),
        ],
      ),
    );
  }
}

class _QuickLink extends StatelessWidget {
  const _QuickLink({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 12)),
    );
  }
}
