import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_bottom_nav.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class MatchDetailScreen extends ConsumerStatefulWidget {
  const MatchDetailScreen({super.key, required this.matchId});

  final int matchId;

  @override
  ConsumerState<MatchDetailScreen> createState() => _MatchDetailScreenState();
}

class _MatchDetailScreenState extends ConsumerState<MatchDetailScreen> {
  Map<String, dynamic>? _match;
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
      final match = await repo.fetchMatch(widget.matchId);
      if (mounted) {
        setState(() {
          _match = match;
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

  int? get _tournamentId {
    final direct = _match?['tournament_id'];
    if (direct is int) return direct;

    final category = _match?['category'];
    if (category is Map<String, dynamic>) {
      final nested = category['tournament_id'] ?? category['tournament']?['id'];
      if (nested is int) return nested;
      return int.tryParse('$nested');
    }

    // En la API pública, category suele venir como String (nombre de categoría).
    return null;
  }

  List<Map<String, dynamic>> _lineupFor(int? teamId, {required bool starters}) {
    if (teamId == null) return [];
    return (_match?['lineups'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .where((row) => row['team_id'] == teamId && row['starter'] == starters && row['player'] is Map)
        .map((row) => Map<String, dynamic>.from(row['player'] as Map))
        .toList();
  }

  @override
  Widget build(BuildContext context) {
    final home = _match?['home'] as Map<String, dynamic>?;
    final away = _match?['away'] as Map<String, dynamic>?;
    final homeName = (home?['name'] as String? ?? 'LOCAL').toUpperCase();
    final awayName = (away?['name'] as String? ?? 'VISITANTE').toUpperCase();
    final score = _match?['score'] as String? ?? '0 - 0';
    final status = _match?['status'] as String?;
    final statusLabel = (_match?['status_label'] as String? ?? 'PROGRAMADO').toUpperCase();
    final category = _match?['category'] as String? ?? '';
    final stage = _match?['stage'] as String? ?? '';
    final tournament = _match?['tournament'] as String? ?? '';
    final scheduled = _match?['scheduled_at'] as String?;
    final field = _match?['field'] as String? ?? '';

    String metaLine = '';
    if (scheduled != null) {
      final dt = DateTime.tryParse(scheduled)?.toLocal();
      if (dt != null) {
        metaLine = '${DateFormat('d MMM', 'es').format(dt).toUpperCase()} · ${DateFormat('HH:mm').format(dt)}';
        if (field.isNotEmpty) metaLine += ' · ${field.toUpperCase()}';
      }
    }

    final contextLine = [
      if (tournament.isNotEmpty) tournament,
      if (category.isNotEmpty) category,
      if (stage.isNotEmpty) stage,
    ].join(' · ');

    return StcBackScope(
      child: Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(
                title: 'FICHA DEL PARTIDO',
                onBack: () => stcGoBack(context),
              ),
              if (contextLine.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 0, 18, 10),
                  child: Column(
                    children: [
                      Text(
                        contextLine,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: StcColors.cyan, fontSize: 11, fontWeight: FontWeight.w700, height: 1.35),
                      ),
                      if (metaLine.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(
                          metaLine,
                          textAlign: TextAlign.center,
                          style: const TextStyle(color: StcColors.textMuted, fontSize: 10, fontWeight: FontWeight.w600),
                        ),
                      ],
                    ],
                  ),
                ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : _error != null
                        ? StcEmptyState(
                            kind: StcEmptyKind.offline,
                            message: 'No pudimos cargar la ficha del partido.',
                            actionLabel: 'Reintentar',
                            onAction: _load,
                          )
                        : _match == null
                            ? StcEmptyState(
                                kind: StcEmptyKind.noData,
                                message: 'Partido no encontrado.',
                                actionLabel: 'Volver',
                                onAction: () => stcGoBack(context),
                              )
                            : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(17, 8, 17, 12),
                              children: [
                                StcSurfaceCard(
                                  padding: const EdgeInsets.fromLTRB(17, 19, 17, 16),
                                  child: Column(
                                    children: [
                                      Row(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          Column(
                                            children: [
                                              StcTeamShield.fromTeam(team: home ?? {'name': homeName}, size: 72),
                                              const SizedBox(height: 8),
                                              SizedBox(
                                                width: 95,
                                                child: Text(homeName, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10)),
                                              ),
                                            ],
                                          ),
                                          Expanded(
                                            child: Column(
                                              children: [
                                                const SizedBox(height: 8),
                                                Text(
                                                  status == 'scheduled' ? 'VS' : score,
                                                  style: TextStyle(
                                                    color: Colors.white,
                                                    fontWeight: FontWeight.w800,
                                                    fontSize: status == 'scheduled' ? 36 : 46,
                                                    height: 1,
                                                  ),
                                                ),
                                                const SizedBox(height: 8),
                                                Container(
                                                  height: 26,
                                                  padding: const EdgeInsets.symmetric(horizontal: 16),
                                                  alignment: Alignment.center,
                                                  decoration: BoxDecoration(
                                                    color: StcColors.surfaceInner,
                                                    borderRadius: BorderRadius.circular(13),
                                                    border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.8)),
                                                  ),
                                                  child: Text(statusLabel, style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800)),
                                                ),
                                              ],
                                            ),
                                          ),
                                          Column(
                                            children: [
                                              StcTeamShield.fromTeam(team: away ?? {'name': awayName}, size: 72),
                                              const SizedBox(height: 8),
                                              SizedBox(
                                                width: 95,
                                                child: Text(awayName, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10)),
                                              ),
                                            ],
                                          ),
                                        ],
                                      ),
                                      if (metaLine.isNotEmpty) ...[
                                        const SizedBox(height: 12),
                                        Container(
                                          width: double.infinity,
                                          padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 10),
                                          decoration: BoxDecoration(
                                            color: StcColors.surfaceInner,
                                            borderRadius: BorderRadius.circular(10),
                                            border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.25)),
                                          ),
                                          child: Text(
                                            metaLine,
                                            textAlign: TextAlign.center,
                                            style: const TextStyle(color: StcColors.textBody, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.3),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                                const SizedBox(height: 16),
                                Row(
                                  children: [
                                    Expanded(child: _LineupMiniCard(title: homeName, players: _lineupFor(home?['id'] as int?, starters: true))),
                                    const SizedBox(width: 14),
                                    Expanded(child: _LineupMiniCard(title: awayName, players: _lineupFor(away?['id'] as int?, starters: true))),
                                  ],
                                ),
                                const SizedBox(height: 16),
                                StcSurfaceCard(
                                  padding: const EdgeInsets.fromLTRB(17, 11, 17, 11),
                                  child: Row(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Expanded(
                                        child: _BenchColumn(title: 'SUPLENTES', players: [
                                          ..._lineupFor(home?['id'] as int?, starters: false),
                                          ..._lineupFor(away?['id'] as int?, starters: false),
                                        ]),
                                      ),
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            const Text('CUERPO TÉCNICO', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10)),
                                            const SizedBox(height: 8),
                                            Text(
                                              _match?['referee'] != null ? 'Árbitro: ${_match!['referee']}' : 'Sin datos publicados',
                                              style: const TextStyle(color: StcColors.textBody, fontSize: 9, height: 1.4),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                if (status == 'finished' || status == 'validated') ...[
                                  const SizedBox(height: 16),
                                  StcSecondaryButton(
                                    label: 'Ver placas oficiales',
                                    onPressed: () => context.push('/content/plaques'),
                                  ),
                                ],
                                if (_match?['notes'] != null && (_match!['notes'] as String).isNotEmpty) ...[
                                  const SizedBox(height: 16),
                                  StcSurfaceCard(
                                    padding: const EdgeInsets.all(15),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text('NOTAS', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
                                        const SizedBox(height: 8),
                                        Text(_match!['notes'] as String, style: const TextStyle(color: StcColors.textBody, fontSize: 10, height: 1.35)),
                                      ],
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
              ),
              StcBottomNav(
                currentIndex: stcNavIndexForPath('/matches/${widget.matchId}'),
                tournamentId: _tournamentId,
              ),
            ],
          ),
        ),
      ),
    ),
    );
  }
}

class _LineupMiniCard extends StatelessWidget {
  const _LineupMiniCard({required this.title, required this.players});

  final String title;
  final List<Map<String, dynamic>> players;

  static const _rows = [
    ('Delantero', 0.16),
    ('Mediocampista', 0.40),
    ('Defensor', 0.64),
    ('Arquero', 0.84),
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 220,
      decoration: BoxDecoration(
        color: const Color(0xFF041018),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: StcColors.borderSoft),
      ),
      child: Column(
        children: [
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6),
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(8, 6, 8, 8),
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final width = constraints.maxWidth;
                  final height = constraints.maxHeight;
                  return ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: CustomPaint(
                      painter: _PitchPainter(),
                      child: SizedBox(
                        width: width,
                        height: height,
                        child: players.isEmpty
                            ? const Center(
                                child: Text(
                                  'Sin XI',
                                  style: TextStyle(color: Colors.white70, fontSize: 10, fontWeight: FontWeight.w700),
                                ),
                              )
                            : Stack(
                                children: [
                                  for (final (zone, y) in _rows) ..._dotsFor(zone, y, width, height),
                                ],
                              ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _dotsFor(String zone, double yFactor, double width, double height) {
    final row = players.where((player) => (player['position'] as String? ?? 'Mediocampista') == zone).toList();
    if (row.isEmpty) return [];
    return [
      for (var i = 0; i < row.length; i++)
        Positioned(
          left: ((i + 1) / (row.length + 1)) * width - 11,
          top: (yFactor * height) - 11,
          child: _PlayerDot('${row[i]['jersey'] ?? (i + 1)}'),
        ),
    ];
  }
}

class _PitchPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final grass = Paint()
      ..shader = const LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [Color(0xFF0B3D14), Color(0xFF126B1C), Color(0xFF0A3A12)],
      ).createShader(Offset.zero & size);
    canvas.drawRect(Offset.zero & size, grass);

    final line = Paint()
      ..color = Colors.white.withValues(alpha: 0.55)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.2;

    final inset = Rect.fromLTWH(4, 4, size.width - 8, size.height - 8);
    canvas.drawRRect(RRect.fromRectAndRadius(inset, const Radius.circular(4)), line);

    // Halfway line
    canvas.drawLine(
      Offset(inset.left, inset.center.dy),
      Offset(inset.right, inset.center.dy),
      line,
    );

    // Center circle
    canvas.drawCircle(inset.center, inset.shortestSide * 0.14, line);
    final centerDot = Paint()..color = Colors.white.withValues(alpha: 0.55);
    canvas.drawCircle(inset.center, 2.2, centerDot);

    // Penalty boxes
    final boxH = inset.height * 0.18;
    final boxW = inset.width * 0.62;
    final topBox = Rect.fromCenter(center: Offset(inset.center.dx, inset.top + boxH / 2), width: boxW, height: boxH);
    final bottomBox = Rect.fromCenter(center: Offset(inset.center.dx, inset.bottom - boxH / 2), width: boxW, height: boxH);
    canvas.drawRect(topBox, line);
    canvas.drawRect(bottomBox, line);

    final goalW = inset.width * 0.28;
    final goalH = inset.height * 0.07;
    canvas.drawRect(
      Rect.fromCenter(center: Offset(inset.center.dx, inset.top + goalH / 2), width: goalW, height: goalH),
      line,
    );
    canvas.drawRect(
      Rect.fromCenter(center: Offset(inset.center.dx, inset.bottom - goalH / 2), width: goalW, height: goalH),
      line,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _BenchColumn extends StatelessWidget {
  const _BenchColumn({required this.title, required this.players});

  final String title;
  final List<Map<String, dynamic>> players;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10)),
        const SizedBox(height: 8),
        if (players.isEmpty)
          const Text('Sin suplentes publicados.', style: TextStyle(color: StcColors.textBody, fontSize: 9, height: 1.4))
        else
          ...players.take(8).map((player) {
            final jersey = player['jersey'];
            final name = player['name'] as String? ?? 'Jugador';
            return Text(
              '${jersey ?? '—'}  $name',
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: StcColors.textBody, fontSize: 9, height: 1.4),
            );
          }),
      ],
    );
  }
}

class _PlayerDot extends StatelessWidget {
  const _PlayerDot(this.number);

  final String number;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 22,
      height: 22,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: StcColors.liveRed,
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.85)),
      ),
      child: Text(number, style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800)),
    );
  }
}
