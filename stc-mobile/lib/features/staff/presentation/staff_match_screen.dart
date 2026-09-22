import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../staff_providers.dart';

class StaffMatchScreen extends ConsumerStatefulWidget {
  const StaffMatchScreen({super.key, required this.matchId});

  final int matchId;

  @override
  ConsumerState<StaffMatchScreen> createState() => _StaffMatchScreenState();
}

class _StaffMatchScreenState extends ConsumerState<StaffMatchScreen> with SingleTickerProviderStateMixin {
  Map<String, dynamic>? _payload;
  bool _loading = true;
  bool _offline = false;
  bool _saving = false;
  late TabController _tabs;
  final _minuteController = TextEditingController();
  final _notesController = TextEditingController();
  String _eventType = 'goal';
  int? _teamId;
  int? _playerId;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 4, vsync: this);
    _tabs.addListener(() {
      if (mounted) setState(() {});
    });
    _load();
  }

  @override
  void dispose() {
    _tabs.dispose();
    _minuteController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _offline = false;
    });
    try {
      final payload = await ref.read(staffRepositoryProvider).fetchMatch(widget.matchId);
      final sheet = payload['sheet'] as Map<String, dynamic>? ?? {};
      if (mounted) {
        setState(() {
          _payload = payload;
          _notesController.text = sheet['notes'] as String? ?? '';
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

  List<Map<String, dynamic>> _playersForTeam(int? teamId) {
    if (teamId == null || _payload == null) return [];
    final players = _payload!['players'] as Map<String, dynamic>? ?? {};
    final match = _payload!['match'] as Map<String, dynamic>? ?? {};
    final home = match['home'] as Map<String, dynamic>?;
    final key = teamId == home?['id'] ? 'home' : 'away';
    return (players[key] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();
  }

  Future<void> _submitEvent() async {
    final teamId = _teamId;
    if (teamId == null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Elegí un equipo')));
      return;
    }
    setState(() => _saving = true);
    try {
      await ref.read(staffRepositoryProvider).addEvent(
            matchId: widget.matchId,
            type: _eventType,
            teamId: teamId,
            playerId: _playerId,
            minute: int.tryParse(_minuteController.text.trim()),
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Evento registrado')));
        await _load();
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _saveReport() async {
    setState(() => _saving = true);
    try {
      await ref.read(staffRepositoryProvider).updateReport(
            matchId: widget.matchId,
            notes: _notesController.text.trim().isEmpty ? null : _notesController.text.trim(),
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Informe guardado')));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final payload = _payload;
    final match = payload?['match'] as Map<String, dynamic>?;
    final sheet = payload?['sheet'] as Map<String, dynamic>?;
    final canOperate = payload?['can_operate'] == true;
    final locked = sheet?['locked'] == true;

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'PLANILLA', onBack: () => context.pop()),
              if (match != null)
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 0, 18, 8),
                  child: Text(
                    (match['title'] as String? ?? 'Partido').toUpperCase(),
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13),
                  ),
                ),
              TabBar(
                controller: _tabs,
                indicatorColor: StcColors.cyan,
                labelColor: StcColors.cyan,
                unselectedLabelColor: StcColors.textMuted,
                tabs: const [
                  Tab(text: 'Resumen'),
                  Tab(text: 'Eventos'),
                  Tab(text: 'Disciplina'),
                  Tab(text: 'Informe'),
                ],
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : _offline
                        ? StcEmptyState(kind: StcEmptyKind.offline, actionLabel: 'Reintentar', onAction: _load)
                        : payload == null
                            ? const StcEmptyState(kind: StcEmptyKind.noPermission)
                            : TabBarView(
                                controller: _tabs,
                                children: [
                                  _SummaryTab(match: match!, sheet: sheet ?? {}),
                                  _EventsTab(sheet: sheet ?? {}, canOperate: canOperate && !locked),
                                  _DisciplineTab(discipline: (payload['discipline'] as List<dynamic>? ?? [])
                                      .map((item) => Map<String, dynamic>.from(item as Map))
                                      .toList()),
                                  _ReportTab(
                                    controller: _notesController,
                                    canOperate: canOperate && !locked,
                                    saving: _saving,
                                    onSave: _saveReport,
                                  ),
                                ],
                              ),
              ),
              if (canOperate && !locked && _tabs.index == 1)
                Padding(
                  padding: const EdgeInsets.fromLTRB(18, 0, 18, 16),
                  child: _EventForm(
                    match: match ?? {},
                    eventType: _eventType,
                    teamId: _teamId,
                    playerId: _playerId,
                    minuteController: _minuteController,
                    saving: _saving,
                    players: _playersForTeam(_teamId),
                    onTypeChanged: (value) => setState(() => _eventType = value),
                    onTeamChanged: (value) => setState(() {
                      _teamId = value;
                      _playerId = null;
                    }),
                    onPlayerChanged: (value) => setState(() => _playerId = value),
                    onSubmit: _submitEvent,
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SummaryTab extends StatelessWidget {
  const _SummaryTab({required this.match, required this.sheet});

  final Map<String, dynamic> match;
  final Map<String, dynamic> sheet;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        StcSurfaceCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                (match['status_label'] as String? ?? 'Estado').toUpperCase(),
                style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10),
              ),
              const SizedBox(height: 8),
              Text(
                match['score'] as String? ?? '0 - 0',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 28),
              ),
              const SizedBox(height: 8),
              Text(
                'Planilla: ${sheet['status_label'] as String? ?? 'Borrador'}',
                style: const TextStyle(color: StcColors.textBody, fontSize: 11),
              ),
              if (match['field'] != null) ...[
                const SizedBox(height: 6),
                Text('Cancha: ${match['field']}', style: const TextStyle(color: StcColors.textMuted, fontSize: 11)),
              ],
              if (match['referee'] != null) ...[
                const SizedBox(height: 6),
                Text('Árbitro: ${match['referee']}', style: const TextStyle(color: StcColors.textMuted, fontSize: 11)),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _EventsTab extends StatelessWidget {
  const _EventsTab({required this.sheet, required this.canOperate});

  final Map<String, dynamic> sheet;
  final bool canOperate;

  @override
  Widget build(BuildContext context) {
    final events = (sheet['events'] as List<dynamic>? ?? [])
        .map((item) => Map<String, dynamic>.from(item as Map))
        .toList();

    if (events.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(18),
        children: [
          StcEmptyState(
            kind: StcEmptyKind.noData,
            message: canOperate ? 'Todavía no hay eventos cargados. Usá el formulario inferior.' : 'No hay eventos publicados en la planilla.',
          ),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(18),
      itemCount: events.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (_, index) {
        final event = events[index];
        return StcSurfaceCard(
          child: Row(
            children: [
              Text(
                event['minute']?.toString() ?? '—',
                style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w900, fontSize: 16),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(event['label'] as String? ?? '', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                    Text(
                      [event['team_name'], event['player_name']].whereType<String>().where((v) => v.isNotEmpty).join(' · '),
                      style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _DisciplineTab extends StatelessWidget {
  const _DisciplineTab({required this.discipline});

  final List<Map<String, dynamic>> discipline;

  @override
  Widget build(BuildContext context) {
    if (discipline.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(18),
        children: const [
          StcEmptyState(kind: StcEmptyKind.noData, message: 'No hay tarjetas ni sanciones registradas.'),
        ],
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(18),
      itemCount: discipline.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (_, index) {
        final item = discipline[index];
        return StcSurfaceCard(
          child: ListTile(
            dense: true,
            title: Text(item['label'] as String? ?? '', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
            subtitle: Text(
              '${item['minute'] ?? '—'}′ · ${item['team_name'] ?? ''} · ${item['player_name'] ?? ''}',
              style: const TextStyle(color: StcColors.textMuted, fontSize: 11),
            ),
          ),
        );
      },
    );
  }
}

class _ReportTab extends StatelessWidget {
  const _ReportTab({
    required this.controller,
    required this.canOperate,
    required this.saving,
    required this.onSave,
  });

  final TextEditingController controller;
  final bool canOperate;
  final bool saving;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(18),
      children: [
        const Text(
          'INFORME ARBITRAL',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16),
        ),
        const SizedBox(height: 12),
        if (!canOperate)
          const StcEmptyState(kind: StcEmptyKind.noPermission, message: 'Solo el staff autorizado puede editar el informe.')
        else ...[
          TextField(
            controller: controller,
            maxLines: 10,
            style: const TextStyle(color: Colors.white),
            decoration: const InputDecoration(
              hintText: 'Observaciones, incidentes y cierre del partido…',
              hintStyle: TextStyle(color: StcColors.textMuted),
              filled: true,
              fillColor: StcColors.card,
              border: OutlineInputBorder(borderSide: BorderSide.none, borderRadius: BorderRadius.all(Radius.circular(14))),
            ),
          ),
          const SizedBox(height: 16),
          StcPrimaryButton(label: 'Guardar informe', loading: saving, onPressed: onSave),
        ],
      ],
    );
  }
}

class _EventForm extends StatelessWidget {
  const _EventForm({
    required this.match,
    required this.eventType,
    required this.teamId,
    required this.playerId,
    required this.minuteController,
    required this.saving,
    required this.players,
    required this.onTypeChanged,
    required this.onTeamChanged,
    required this.onPlayerChanged,
    required this.onSubmit,
  });

  final Map<String, dynamic> match;
  final String eventType;
  final int? teamId;
  final int? playerId;
  final TextEditingController minuteController;
  final bool saving;
  final List<Map<String, dynamic>> players;
  final ValueChanged<String> onTypeChanged;
  final ValueChanged<int?> onTeamChanged;
  final ValueChanged<int?> onPlayerChanged;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final home = match['home'] as Map<String, dynamic>?;
    final away = match['away'] as Map<String, dynamic>?;

    return StcSurfaceCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('NUEVO EVENTO', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10)),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            value: eventType,
            dropdownColor: StcColors.card,
            style: const TextStyle(color: Colors.white),
            decoration: const InputDecoration(labelText: 'Tipo', labelStyle: TextStyle(color: StcColors.textMuted)),
            items: const [
              DropdownMenuItem(value: 'goal', child: Text('Gol')),
              DropdownMenuItem(value: 'assist', child: Text('Asistencia')),
              DropdownMenuItem(value: 'yellow', child: Text('Amarilla')),
              DropdownMenuItem(value: 'red', child: Text('Roja')),
              DropdownMenuItem(value: 'substitution', child: Text('Cambio')),
            ],
            onChanged: (value) => onTypeChanged(value ?? 'goal'),
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<int>(
            value: teamId,
            dropdownColor: StcColors.card,
            style: const TextStyle(color: Colors.white),
            decoration: const InputDecoration(labelText: 'Equipo', labelStyle: TextStyle(color: StcColors.textMuted)),
            items: [
              if (home != null) DropdownMenuItem(value: home['id'] as int?, child: Text(home['name'] as String? ?? 'Local')),
              if (away != null) DropdownMenuItem(value: away['id'] as int?, child: Text(away['name'] as String? ?? 'Visitante')),
            ],
            onChanged: onTeamChanged,
          ),
          if (players.isNotEmpty) ...[
            const SizedBox(height: 8),
            DropdownButtonFormField<int>(
              value: playerId,
              dropdownColor: StcColors.card,
              style: const TextStyle(color: Colors.white),
              decoration: const InputDecoration(labelText: 'Jugador (opcional)', labelStyle: TextStyle(color: StcColors.textMuted)),
              items: players
                  .map((player) => DropdownMenuItem(
                        value: player['id'] as int?,
                        child: Text(player['name'] as String? ?? ''),
                      ))
                  .toList(),
              onChanged: onPlayerChanged,
            ),
          ],
          const SizedBox(height: 8),
          TextField(
            controller: minuteController,
            keyboardType: TextInputType.number,
            style: const TextStyle(color: Colors.white),
            decoration: const InputDecoration(
              labelText: 'Minuto',
              labelStyle: TextStyle(color: StcColors.textMuted),
            ),
          ),
          const SizedBox(height: 12),
          StcPrimaryButton(label: 'Registrar evento', loading: saving, onPressed: onSubmit),
        ],
      ),
    );
  }
}
