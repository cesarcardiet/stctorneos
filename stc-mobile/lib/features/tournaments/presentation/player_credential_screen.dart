import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_bottom_nav.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../home/presentation/home_screen.dart';

class PlayerCredentialScreen extends ConsumerStatefulWidget {
  const PlayerCredentialScreen({super.key, required this.playerId});

  final int playerId;

  @override
  ConsumerState<PlayerCredentialScreen> createState() => _PlayerCredentialScreenState();
}

class _PlayerCredentialScreenState extends ConsumerState<PlayerCredentialScreen> {
  Map<String, dynamic>? _player;
  String _tournamentName = 'STC Torneos';
  String _categoryLabel = '-';
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
      final player = await repo.fetchPlayer(widget.playerId);
      final team = player['team'] as Map<String, dynamic>?;
      final categoryName = team?['category'] as String? ?? '';

      var tournamentName = 'STC Torneos';
      var categoryLabel = categoryName;

      if (categoryName.isNotEmpty) {
        final tournaments = await repo.fetchTournaments();
        for (final tournament in tournaments) {
          final detail = await repo.fetchTournament(tournament['id'] as int);
          for (final category in (detail['categories'] as List<dynamic>? ?? [])) {
            final cat = category as Map<String, dynamic>;
            if (cat['name'] == categoryName) {
              tournamentName = detail['name'] as String? ?? tournamentName;
              final catDetail = await repo.fetchCategory(cat['id'] as int);
              final birthYear = catDetail['birth_year'];
              if (birthYear != null) categoryLabel = '$birthYear';
              break;
            }
          }
        }
      }

      if (mounted) {
        setState(() {
          _player = player;
          _tournamentName = tournamentName;
          _categoryLabel = categoryLabel.isNotEmpty ? categoryLabel : categoryName;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  String get _credentialCode {
    final year = DateTime.now().year;
    final category = _categoryLabel.replaceAll(RegExp(r'[^0-9]'), '');
    return 'STC-$year-${category.isNotEmpty ? category : '0000'}-${widget.playerId.toString().padLeft(4, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(
                title: 'CREDENCIAL STC',
                onBack: () => context.pop(),
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : _player == null
                        ? const Center(child: Text('Jugador no encontrado', style: TextStyle(color: StcColors.textBody)))
                        : RefreshIndicator(
                            color: StcColors.cyan,
                            onRefresh: _load,
                            child: ListView(
                              padding: const EdgeInsets.fromLTRB(27, 8, 27, 12),
                              children: [
                                StcCredentialCard(
                                  player: _player!,
                                  tournamentName: _tournamentName,
                                  categoryLabel: _categoryLabel,
                                  credentialCode: _credentialCode,
                                ),
                                const SizedBox(height: 16),
                                StcGradientButton(
                                  label: 'DESCARGAR CREDENCIAL',
                                  onTap: () {
                                    ScaffoldMessenger.of(context).showSnackBar(
                                      const SnackBar(content: Text('Descarga disponible próximamente en el dispositivo.')),
                                    );
                                  },
                                ),
                              ],
                            ),
                          ),
              ),
              const StcBottomNav(currentIndex: 4),
            ],
          ),
        ),
      ),
    );
  }
}
