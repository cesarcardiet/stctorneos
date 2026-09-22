import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../navigation/stc_navigation.dart';
import '../navigation/stc_team_media.dart';
import '../theme/stc_theme.dart';
import 'stc_form_field.dart';
import 'stc_surface.dart';

class StcPublicHeader extends StatelessWidget {
  const StcPublicHeader({
    super.key,
    required this.title,
    this.onBack,
    this.showBack = true,
    this.showMenu = false,
  });

  final String title;
  final VoidCallback? onBack;
  final bool showBack;
  final bool showMenu;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 4, 8, 0),
      child: Row(
        children: [
          if (showMenu)
            IconButton(
              onPressed: () => Scaffold.of(context).openDrawer(),
              icon: const Icon(Icons.menu_rounded, color: Colors.white, size: 24),
            )
          else if (showBack)
            IconButton(
              onPressed: onBack ?? () => stcGoBack(context),
              icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 20),
            )
          else
            const SizedBox(width: 12),
          Image.asset(
            'assets/images/stc_logo.png',
            height: 42,
            errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 36),
          ),
          Expanded(
            child: Text(
              title,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w800,
                fontSize: 15,
                letterSpacing: 0.6,
              ),
            ),
          ),
          const SizedBox(width: 52),
        ],
      ),
    );
  }
}

class StcHomeHeader extends StatelessWidget {
  const StcHomeHeader({super.key, this.onNotificationsTap, this.unreadCount = 0});

  final VoidCallback? onNotificationsTap;
  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 4),
      child: Row(
        children: [
          const Text('☰', style: TextStyle(color: Colors.white, fontSize: 24)),
          Expanded(
            child: Center(
              child: Image.asset(
                'assets/images/stc_logo.png',
                height: 58,
                errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 48),
              ),
            ),
          ),
          InkWell(
            onTap: onNotificationsTap,
            borderRadius: BorderRadius.circular(20),
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                const Padding(
                  padding: EdgeInsets.all(4),
                  child: Text('🔔', style: TextStyle(fontSize: 19)),
                ),
                if (unreadCount > 0)
                  Positioned(
                    right: -2,
                    top: -2,
                    child: Container(
                      width: 16,
                      height: 16,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: StcColors.primaryBlue,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.65)),
                      ),
                      child: Text(
                        unreadCount > 9 ? '9+' : '$unreadCount',
                        style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class StcFilterChip extends StatelessWidget {
  const StcFilterChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.width,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final double? width;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: width,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            gradient: selected
                ? const LinearGradient(colors: [Color(0xFF054DFF), Color(0xFF00C7FF)])
                : null,
            color: selected ? null : StcColors.surface,
            border: Border.all(
              color: selected ? StcColors.cyan.withValues(alpha: 0.85) : StcColors.primaryBlue.withValues(alpha: 0.85),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? Colors.white : StcColors.primaryBlue,
              fontSize: 10,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ),
    );
  }
}

class StcTournamentStatusPill extends StatelessWidget {
  const StcTournamentStatusPill({super.key, required this.status});

  final String? status;

  @override
  Widget build(BuildContext context) {
    final label = tournamentStatusLabel(status);
    final color = tournamentStatusColor(status);
    return Container(
      width: 74,
      height: 24,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.45)),
      ),
      child: Text(
        label,
        style: const TextStyle(color: Colors.white, fontSize: 7, fontWeight: FontWeight.w800, letterSpacing: 0.4),
      ),
    );
  }
}

Color tournamentCardBorderColor(String? status) {
  return status == 'in_progress'
      ? const Color(0x8C2EFF94)
      : StcColors.borderSoft;
}

class StcTournamentListCard extends StatelessWidget {
  const StcTournamentListCard({
    super.key,
    required this.tournament,
    required this.onTap,
    this.teamCount,
  });

  final Map<String, dynamic> tournament;
  final VoidCallback onTap;
  final int? teamCount;

  @override
  Widget build(BuildContext context) {
    final status = tournament['status'] as String?;
    final starts = tournament['starts_at'] as String?;
    final ends = tournament['ends_at'] as String?;
    final logo = tournament['logo'] as String?;
    String dates = '';
    if (starts != null) {
      final s = DateTime.tryParse(starts);
      final e = ends != null ? DateTime.tryParse(ends) : null;
      if (s != null) {
        dates = DateFormat('d', 'es').format(s);
        if (e != null) dates += ' al ${DateFormat('d MMM', 'es').format(e)}';
        else dates = '${DateFormat('d MMM', 'es').format(s)}';
      }
    }
    if (status == 'finished' || status == 'archived') {
      dates = '${tournament['city'] ?? ''} · Finalizado';
    } else if (status == 'registration') {
      dates = '${tournament['city'] ?? ''} · Preventa';
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        height: 96,
        padding: const EdgeInsets.fromLTRB(15, 17, 12, 17),
        decoration: BoxDecoration(
          color: StcColors.surface,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: tournamentCardBorderColor(status)),
          boxShadow: const [BoxShadow(color: Color(0x290059FF), blurRadius: 9)],
        ),
        child: Row(
          children: [
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(
                color: StcColors.surfaceInner,
                borderRadius: BorderRadius.circular(15),
                border: Border.all(color: tournamentCardBorderColor(status)),
                image: logo != null && logo.isNotEmpty
                    ? DecorationImage(image: NetworkImage(logo), fit: BoxFit.contain)
                    : null,
              ),
              alignment: Alignment.center,
              child: logo == null || logo.isEmpty
                  ? const Text('STC', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10))
                  : null,
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    tournament['name'] as String? ?? '',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    dates.isNotEmpty ? dates : (tournament['city'] as String? ?? ''),
                    style: const TextStyle(color: StcColors.textBody, fontSize: 10),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    teamCount != null ? '$teamCount equipos' : (tournament['edition'] as String? ?? ''),
                    style: const TextStyle(color: StcColors.textMuted, fontSize: 9, fontWeight: FontWeight.w600),
                  ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                StcTournamentStatusPill(status: status),
                const Spacer(),
                const Text('›', style: TextStyle(color: StcColors.cyan, fontSize: 18, fontWeight: FontWeight.w800)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class StcHubHeroCard extends StatelessWidget {
  const StcHubHeroCard({
    super.key,
    required this.tournament,
    required this.liveCount,
    this.categoryLabel,
  });

  final Map<String, dynamic> tournament;
  final int liveCount;
  final String? categoryLabel;

  @override
  Widget build(BuildContext context) {
    final name = tournament['name'] as String? ?? '';
    final parts = name.split(' ');
    final year = parts.isNotEmpty && RegExp(r'^\d{4}$').hasMatch(parts.last) ? parts.removeLast() : '';
    final title = parts.join(' ').toUpperCase();
    final logo = tournament['logo'] as String?;

    return StcSurfaceCard(
      padding: const EdgeInsets.fromLTRB(17, 19, 17, 19),
      child: SizedBox(
        height: 104,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 90,
              height: 82,
              child: logo != null && logo.isNotEmpty
                  ? Image.network(logo, fit: BoxFit.contain, errorBuilder: (_, __, ___) => Image.asset('assets/images/stc_logo.png'))
                  : Image.asset('assets/images/stc_logo.png', fit: BoxFit.contain, errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 48)),
            ),
            const SizedBox(width: 18),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 17)),
                  if (year.isNotEmpty)
                    Text(year, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 26, height: 1)),
                  const SizedBox(height: 4),
                  Text(
                    categoryLabel ?? '${tournament['edition'] ?? ''} · ${tournament['city'] ?? ''}',
                    style: const TextStyle(color: StcColors.textBody, fontSize: 11, fontWeight: FontWeight.w600),
                  ),
                  if (liveCount > 0) ...[
                    const SizedBox(height: 8),
                    Container(
                      height: 22,
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: StcColors.liveRed,
                        borderRadius: BorderRadius.circular(11),
                        border: Border.all(color: StcColors.liveRed.withValues(alpha: 0.8)),
                      ),
                      child: Text(
                        'EN VIVO $liveCount',
                        style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class StcHubStatsBar extends StatelessWidget {
  const StcHubStatsBar({
    super.key,
    required this.teams,
    required this.matches,
    required this.players,
    required this.venues,
  });

  final int teams;
  final int matches;
  final int players;
  final int venues;

  @override
  Widget build(BuildContext context) {
    return StcSurfaceCard(
      padding: const EdgeInsets.symmetric(vertical: 15),
      child: Row(
        children: [
          _StatColumn(value: '$teams', label: 'Equipos'),
          _StatColumn(value: '$matches', label: 'Partidos'),
          _StatColumn(value: '$players', label: 'Jugadores'),
          _StatColumn(value: '$venues', label: 'Sedes'),
        ],
      ),
    );
  }
}

class _StatColumn extends StatelessWidget {
  const _StatColumn({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
          const SizedBox(height: 6),
          Text(label, style: const TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class StcHubAccessGrid extends StatelessWidget {
  const StcHubAccessGrid({
    super.key,
    this.onFixture,
    this.onStandings,
    this.onTeams,
    this.onVenues,
  });

  final VoidCallback? onFixture;
  final VoidCallback? onStandings;
  final VoidCallback? onTeams;
  final VoidCallback? onVenues;

  @override
  Widget build(BuildContext context) {
    return StcSurfaceCard(
      padding: const EdgeInsets.all(15),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(child: _AccessButton(emoji: '📅', label: 'Fixture', onTap: onFixture)),
              const SizedBox(width: 14),
              Expanded(child: _AccessButton(emoji: '🏆', label: 'Tablas', onTap: onStandings)),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(child: _AccessButton(emoji: '🛡', label: 'Equipos', onTap: onTeams)),
              const SizedBox(width: 14),
              Expanded(child: _AccessButton(emoji: '📍', label: 'Sedes', onTap: onVenues)),
            ],
          ),
        ],
      ),
    );
  }
}

class _AccessButton extends StatelessWidget {
  const _AccessButton({required this.emoji, required this.label, this.onTap});

  final String emoji;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(13),
      child: Container(
        height: 58,
        padding: const EdgeInsets.symmetric(horizontal: 13),
        decoration: BoxDecoration(
          color: StcColors.surfaceInner,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.35)),
        ),
        child: Row(
          children: [
            Text(emoji, style: const TextStyle(fontSize: 18)),
            const SizedBox(width: 10),
            Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}

class StcNewsCard extends StatelessWidget {
  const StcNewsCard({
    super.key,
    required this.title,
    required this.summary,
    this.onRead,
  });

  final String title;
  final String summary;
  final VoidCallback? onRead;

  @override
  Widget build(BuildContext context) {
    return StcSurfaceCard(
      padding: const EdgeInsets.fromLTRB(15, 13, 15, 13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('NOVEDADES', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11)),
          const SizedBox(height: 12),
          Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
          const SizedBox(height: 6),
          Text(summary, style: const TextStyle(color: StcColors.textBody, fontSize: 10, height: 1.35)),
          const SizedBox(height: 14),
          InkWell(
            onTap: onRead,
            borderRadius: BorderRadius.circular(12),
            child: Container(
              height: 30,
              width: 150,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: StcColors.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.85)),
              ),
              child: const Text(
                'VER COMUNICADO',
                style: TextStyle(color: StcColors.primaryBlue, fontSize: 10.5, fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class StcPublicBackground extends StatelessWidget {
  const StcPublicBackground({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0xFF0A1B34), Color(0xFF020714), Color(0xFF010204)],
            ),
          ),
        ),
        child,
      ],
    );
  }
}

class StcTournamentContextHeader extends StatelessWidget {
  const StcTournamentContextHeader({
    super.key,
    required this.tournament,
    this.onBack,
    this.showBell = true,
  });

  final Map<String, dynamic> tournament;
  final VoidCallback? onBack;
  final bool showBell;

  @override
  Widget build(BuildContext context) {
    final name = (tournament['name'] as String? ?? 'STC TORNEO').toUpperCase();
    final parts = name.split(' ');
    final year = parts.isNotEmpty && RegExp(r'^\d{4}$').hasMatch(parts.last) ? parts.removeLast() : '';
    final title = parts.join(' ');

    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 4, 12, 0),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconButton(
            onPressed: onBack ?? () => stcGoBack(context),
            icon: const Text('‹', style: TextStyle(color: Colors.white, fontSize: 28, height: 1)),
          ),
          Image.asset(
            'assets/images/stc_logo.png',
            height: 42,
            errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 36),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title.isNotEmpty ? title : name,
                  style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 12),
                ),
                if (year.isNotEmpty)
                  Text(year, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 20, height: 1.1)),
              ],
            ),
          ),
          if (showBell) const Text('🔔', style: TextStyle(fontSize: 19)),
        ],
      ),
    );
  }
}

class StcSectionTabs extends StatelessWidget {
  const StcSectionTabs({
    super.key,
    required this.tabs,
    required this.activeIndex,
    this.onChanged,
  });

  final List<String> tabs;
  final int activeIndex;
  final ValueChanged<int>? onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 54,
      decoration: BoxDecoration(
        color: const Color(0xFF020508),
        border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.28)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 21),
        child: Row(
          children: List.generate(tabs.length, (index) {
            final active = index == activeIndex;
            return GestureDetector(
              onTap: onChanged == null ? null : () => onChanged!(index),
              child: Padding(
                padding: EdgeInsets.only(right: index < tabs.length - 1 ? 20 : 0),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      tabs[index],
                      style: TextStyle(
                        color: active ? Colors.white : StcColors.textMuted,
                        fontSize: 10,
                        fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Container(
                      height: 3,
                      width: active ? 70 : 0,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(2),
                      ),
                    ),
                  ],
                ),
              ),
            );
          }),
        ),
      ),
    );
  }
}

class StcQuickChip extends StatelessWidget {
  const StcQuickChip({super.key, required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: StcColors.card,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: StcColors.cyan.withValues(alpha: 0.55)),
        ),
        child: Text(
          label.toUpperCase(),
          style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800),
        ),
      ),
    );
  }
}

class StcWhiteFilterChip extends StatelessWidget {
  const StcWhiteFilterChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.width,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;
  final double? width;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: width,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            color: selected ? Colors.white : StcColors.surface,
            border: Border.all(
              color: selected ? Colors.white.withValues(alpha: 0.95) : StcColors.primaryBlue.withValues(alpha: 0.38),
            ),
          ),
          child: Text(
            label,
            style: TextStyle(
              color: selected ? StcColors.background : StcColors.textBody,
              fontSize: 9,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ),
    );
  }
}

class StcTeamShield extends StatelessWidget {
  const StcTeamShield({
    super.key,
    required this.name,
    this.logo,
    this.size = 28,
  });

  StcTeamShield.fromTeam({
    super.key,
    required Map<String, dynamic> team,
    this.size = 28,
  })  : name = team['name'] as String? ?? '',
        logo = stcTeamShieldUrl(team);

  final String name;
  final String? logo;
  final double size;

  static const _fallbackAsset = 'assets/images/stc_logo.png';

  @override
  Widget build(BuildContext context) {
    final initial = name.isNotEmpty ? name[0].toUpperCase() : '?';
    final hasLogo = logo != null && logo!.trim().isNotEmpty;

    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: const Color(0xFF0A1528),
        border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.55)),
        boxShadow: [
          BoxShadow(
            color: StcColors.primaryBlue.withValues(alpha: 0.18),
            blurRadius: size * 0.25,
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      alignment: Alignment.center,
      child: hasLogo
          ? Padding(
              padding: EdgeInsets.all(size * 0.12),
              child: Image.network(
                logo!,
                fit: BoxFit.contain,
                filterQuality: FilterQuality.medium,
                errorBuilder: (_, __, ___) => Image.asset(
                  _fallbackAsset,
                  fit: BoxFit.contain,
                  errorBuilder: (_, __, ___) => _InitialMark(initial: initial, size: size),
                ),
              ),
            )
          : Padding(
              padding: EdgeInsets.all(size * 0.18),
              child: Image.asset(
                _fallbackAsset,
                fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => _InitialMark(initial: initial, size: size),
              ),
            ),
    );
  }
}

class _InitialMark extends StatelessWidget {
  const _InitialMark({required this.initial, required this.size});

  final String initial;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Text(
      initial,
      style: TextStyle(
        color: StcColors.cyan,
        fontWeight: FontWeight.w800,
        fontSize: size * 0.38,
      ),
    );
  }
}

class StcFixtureRoundHeader extends StatelessWidget {
  const StcFixtureRoundHeader({
    super.key,
    required this.roundLabel,
    required this.dateLabel,
    this.liveCount = 0,
  });

  final String roundLabel;
  final String dateLabel;
  final int liveCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF071833), Color(0xFF03060C)],
        ),
        border: Border.all(color: StcColors.cyan.withValues(alpha: 0.55), width: 1.2),
        boxShadow: const [
          BoxShadow(color: Color(0x4400D1FF), blurRadius: 12, spreadRadius: 0),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 3,
            height: 34,
            decoration: BoxDecoration(
              color: StcColors.cyan,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  roundLabel.toUpperCase(),
                  style: const TextStyle(
                    color: StcColors.cyan,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                    letterSpacing: 0.6,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  dateLabel,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          if (liveCount > 0)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: StcColors.liveRed.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: StcColors.liveRed.withValues(alpha: 0.7)),
              ),
              child: Text(
                'EN VIVO $liveCount',
                style: const TextStyle(color: StcColors.liveRed, fontSize: 10, fontWeight: FontWeight.w800),
              ),
            ),
        ],
      ),
    );
  }
}

class StcFixtureMatchRow extends StatelessWidget {
  const StcFixtureMatchRow({
    super.key,
    required this.match,
    this.onTap,
  });

  final Map<String, dynamic> match;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final home = match['home'] as Map<String, dynamic>?;
    final away = match['away'] as Map<String, dynamic>?;
    final homeName = home?['name'] as String? ?? 'Local';
    final awayName = away?['name'] as String? ?? 'Visitante';
    final isLive = match['status'] == 'live';
    final scheduled = match['scheduled_at'] as String?;
    final field = match['field'] as String? ?? '';

    String timePrimary = '';
    String? timeSecondary;
    if (scheduled != null) {
      final dt = DateTime.tryParse(scheduled)?.toLocal();
      if (dt != null) {
        final now = DateTime.now();
        final isToday = dt.year == now.year && dt.month == now.month && dt.day == now.day;
        if (isToday) {
          timePrimary = DateFormat('h:mm a', 'es').format(dt).toLowerCase();
        } else {
          timePrimary = DateFormat('dd.MM', 'es').format(dt);
          timeSecondary = DateFormat('h:mm a', 'es').format(dt).toLowerCase();
        }
      }
    }

    String statusLabel;
    Color statusColor;
    if (isLive) {
      statusLabel = 'EN VIVO';
      statusColor = StcColors.liveRed;
    } else if (timeSecondary == null && timePrimary.isNotEmpty && !timePrimary.contains('.')) {
      statusLabel = 'HOY';
      statusColor = StcColors.cyan;
    } else if (field.isNotEmpty) {
      final short = field.toUpperCase().replaceFirst(RegExp(r'^CANCHA\s*', caseSensitive: false), 'CAN ');
      statusLabel = short.length > 10 ? short.substring(0, 10) : short;
      statusColor = StcColors.cyan;
    } else {
      statusLabel = match['status_label'] as String? ?? '';
      statusColor = StcColors.cyan;
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: StcSurfaceCard(
        padding: const EdgeInsets.fromLTRB(12, 10, 10, 10),
        child: SizedBox(
          height: 64,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              SizedBox(
                width: 52,
                child: timeSecondary == null
                    ? Text(timePrimary, style: const TextStyle(color: StcColors.textMuted, fontSize: 11, fontWeight: FontWeight.w700))
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(timePrimary, style: const TextStyle(color: StcColors.textMuted, fontSize: 11, fontWeight: FontWeight.w700)),
                          Text(timeSecondary, style: const TextStyle(color: StcColors.textMuted, fontSize: 10, fontWeight: FontWeight.w600)),
                        ],
                      ),
              ),
              Expanded(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _FixtureTeamLine(team: home ?? {'name': homeName}, name: homeName),
                    const SizedBox(height: 8),
                    _FixtureTeamLine(team: away ?? {'name': awayName}, name: awayName, muted: true),
                  ],
                ),
              ),
              if (statusLabel.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: Text(statusLabel, style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w800)),
                ),
              Container(
                width: 30,
                height: 30,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: StcColors.surfaceInner,
                  borderRadius: BorderRadius.circular(15),
                  border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.28)),
                ),
                child: const Text('🔔', style: TextStyle(fontSize: 12)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FixtureTeamLine extends StatelessWidget {
  const _FixtureTeamLine({
    required this.team,
    required this.name,
    this.muted = false,
  });

  final Map<String, dynamic> team;
  final String name;
  final bool muted;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        StcTeamShield.fromTeam(team: team, size: 24),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: muted ? StcColors.textBody : Colors.white,
              fontSize: 12,
              fontWeight: muted ? FontWeight.w600 : FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}

class StcStandingsTable extends StatelessWidget {
  const StcStandingsTable({
    super.key,
    required this.title,
    required this.rows,
    this.qualifyTop = 4,
    this.ruleLabel,
    this.onTeamTap,
  });

  final String title;
  final List<Map<String, dynamic>> rows;
  final int qualifyTop;
  final String? ruleLabel;
  final void Function(Map<String, dynamic> team)? onTeamTap;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        StcSurfaceCard(
          padding: const EdgeInsets.fromLTRB(15, 15, 15, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 18),
              const Row(
                children: [
                  SizedBox(width: 30, child: Text('POS', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800))),
                  SizedBox(width: 36),
                  Expanded(child: Text('EQUIPO', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800))),
                  SizedBox(width: 24, child: Text('P', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800))),
                  SizedBox(width: 36, child: Text('DIFF', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800))),
                  SizedBox(width: 28, child: Text('PTS', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800))),
                ],
              ),
              const SizedBox(height: 14),
              ...rows.asMap().entries.map((entry) {
                final row = entry.value;
                final pos = row['position'] as int? ?? entry.key + 1;
                final team = row['team'] as Map<String, dynamic>?;
                final name = team?['name'] as String? ?? '';
                final played = row['played'] ?? row['pj'] ?? 0;
                final diff = row['goal_diff'] ?? row['gd'] ?? row['dg'] ?? 0;
                final points = row['points'] ?? row['pts'] ?? 0;
                final qualified = pos <= qualifyTop;
                return Column(
                  children: [
                    if (entry.key > 0)
                      Container(height: 1, margin: const EdgeInsets.only(bottom: 12), color: StcColors.primaryBlue.withValues(alpha: 0.12)),
                    InkWell(
                      onTap: team != null && onTeamTap != null ? () => onTeamTap!(team!) : null,
                      borderRadius: BorderRadius.circular(8),
                      child: Row(
                      children: [
                        SizedBox(
                          width: 30,
                          child: Text(
                            '$pos',
                            style: TextStyle(
                              color: qualified ? StcColors.cyan : StcColors.textMuted,
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        StcTeamShield.fromTeam(team: team ?? const {}, size: 28),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
                        ),
                        SizedBox(width: 24, child: Text('$played', style: const TextStyle(color: StcColors.textBody, fontSize: 10, fontWeight: FontWeight.w800))),
                        SizedBox(
                          width: 36,
                          child: Text(
                            diff is int && diff >= 0 ? '+$diff' : '$diff',
                            style: const TextStyle(color: StcColors.textBody, fontSize: 10, fontWeight: FontWeight.w800),
                          ),
                        ),
                        SizedBox(width: 28, child: Text('$points', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800))),
                      ],
                    ),
                    ),
                    const SizedBox(height: 12),
                  ],
                );
              }),
            ],
          ),
        ),
        if (ruleLabel != null) ...[
          const SizedBox(height: 14),
          Container(
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: StcColors.surface,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0x802EFF94)),
              boxShadow: const [BoxShadow(color: Color(0x290059FF), blurRadius: 9)],
            ),
            child: Text(ruleLabel!, style: const TextStyle(color: Color(0xFF2EFF94), fontSize: 9, fontWeight: FontWeight.w800)),
          ),
        ],
      ],
    );
  }
}

class StcFeaturedScorerCard extends StatelessWidget {
  const StcFeaturedScorerCard({super.key, required this.scorer, required this.rank, this.onTap});

  final Map<String, dynamic> scorer;
  final int rank;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final photo = scorer['photo'] as String?;
    final team = scorer['team'] as Map<String, dynamic>?;
    final teamName = team?['name'] as String? ?? scorer['team_name'] as String? ?? '';
    final category = scorer['category'] as String? ?? '';

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: StcSurfaceCard(
      padding: const EdgeInsets.fromLTRB(17, 19, 17, 19),
      child: SizedBox(
        height: 94,
        child: Row(
          children: [
            Container(
              width: 78,
              height: 78,
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
                  ? Text((scorer['name'] as String? ?? '?')[0], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24))
                  : null,
            ),
            const SizedBox(width: 14),
            Text('$rank', style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 28)),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    (scorer['name'] as String? ?? '').toUpperCase(),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14),
                  ),
                  Text(
                    [teamName, category].where((s) => s.isNotEmpty).join(' · '),
                    style: const TextStyle(color: StcColors.textMuted, fontSize: 9),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text('${scorer['value'] ?? scorer['goals'] ?? 0}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 32, height: 1)),
                      const SizedBox(width: 6),
                      const Padding(
                        padding: EdgeInsets.only(bottom: 6),
                        child: Text('GOLES', style: TextStyle(color: StcColors.cyan, fontSize: 9, fontWeight: FontWeight.w800)),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            StcTeamShield.fromTeam(team: team ?? {'name': teamName}, size: 28),
          ],
        ),
      ),
    ),
    );
  }
}

class StcScorerRankRow extends StatelessWidget {
  const StcScorerRankRow({super.key, required this.scorer, required this.rank, this.onTap});

  final Map<String, dynamic> scorer;
  final int rank;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final photo = scorer['photo'] as String?;
    final team = scorer['team'] as Map<String, dynamic>?;
    final teamName = team?['name'] as String? ?? scorer['team_name'] as String? ?? '';
    final goals = scorer['value'] ?? scorer['goals'] ?? 0;
    final topThree = rank <= 3;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          SizedBox(
            width: 24,
            child: Text(
              '$rank',
              style: TextStyle(
                color: topThree ? StcColors.cyan : StcColors.textMuted,
                fontWeight: FontWeight.w800,
                fontSize: 12,
              ),
            ),
          ),
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.4)),
              image: photo != null && photo.isNotEmpty
                  ? DecorationImage(image: NetworkImage(photo), fit: BoxFit.cover)
                  : null,
              color: StcColors.surfaceInner,
            ),
            alignment: Alignment.center,
            child: photo == null || photo.isEmpty
                ? Text((scorer['name'] as String? ?? '?')[0], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800))
                : null,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(scorer['name'] as String? ?? '', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
                Text(teamName, style: const TextStyle(color: StcColors.textMuted, fontSize: 9)),
              ],
            ),
          ),
          Text('$goals', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
          const SizedBox(width: 4),
          const Padding(
            padding: EdgeInsets.only(top: 6),
            child: Text('goles', style: TextStyle(color: StcColors.cyan, fontSize: 7.5, fontWeight: FontWeight.w800)),
          ),
          const SizedBox(width: 12),
          StcTeamShield.fromTeam(team: team ?? {'name': teamName}, size: 28),
        ],
      ),
    ),
    );
  }
}

class StcTeamHeroCard extends StatelessWidget {
  const StcTeamHeroCard({
    super.key,
    required this.team,
    this.modality,
  });

  final Map<String, dynamic> team;
  final String? modality;

  @override
  Widget build(BuildContext context) {
    final name = (team['name'] as String? ?? '').toUpperCase();
    final delegation = team['delegation'] as String? ?? '';
    final city = team['city'] as String? ?? '';
    final category = team['category'] as String? ?? '';
    final subtitle = [if (delegation.isNotEmpty) delegation, if (city.isNotEmpty) 'Sede $city'].join(' · ');

    return StcSurfaceCard(
      padding: const EdgeInsets.fromLTRB(15, 17, 15, 17),
      child: SizedBox(
        height: 92,
        child: Row(
          children: [
            StcTeamShield.fromTeam(team: team, size: 86),
            const SizedBox(width: 20),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
                  if (subtitle.isNotEmpty)
                    Text(subtitle, style: const TextStyle(color: StcColors.textBody, fontSize: 11, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      if (category.isNotEmpty) _TeamTag(label: 'Categoría $category'),
                      if (category.isNotEmpty && modality != null) const SizedBox(width: 8),
                      if (modality != null) _TeamTag(label: modality!),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _TeamTag extends StatelessWidget {
  const _TeamTag({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 24,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: StcColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.45)),
      ),
      child: Text(label, style: const TextStyle(color: StcColors.primaryBlue, fontSize: 7.5, fontWeight: FontWeight.w800)),
    );
  }
}

class StcTeamStatsBar extends StatelessWidget {
  const StcTeamStatsBar({
    super.key,
    required this.played,
    required this.points,
    required this.gf,
    required this.gc,
    this.form = const [],
  });

  final int played;
  final int points;
  final int gf;
  final int gc;
  final List<String> form;

  @override
  Widget build(BuildContext context) {
    return StcSurfaceCard(
      padding: const EdgeInsets.fromLTRB(12, 14, 12, 12),
      child: Column(
        children: [
          Row(
            children: [
              _StatCell(value: '$played', label: 'PJ'),
              _StatCell(value: '$points', label: 'PTS'),
              _StatCell(value: '$gf', label: 'GF'),
              _StatCell(value: '$gc', label: 'GC'),
            ],
          ),
          if (form.isNotEmpty) ...[
            const SizedBox(height: 12),
            Row(
              children: [
                const Text('FORMA', style: TextStyle(color: StcColors.textBody, fontSize: 9, fontWeight: FontWeight.w800)),
                const SizedBox(width: 12),
                ...form.map((result) {
                  final color = switch (result) {
                    'G' => const Color(0xFF2EFF94),
                    'E' => StcColors.gold,
                    _ => StcColors.liveRed,
                  };
                  return Padding(
                    padding: const EdgeInsets.only(right: 10),
                    child: Container(
                      width: 24,
                      height: 24,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(12)),
                      child: Text(result, style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800)),
                    ),
                  );
                }),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _StatCell extends StatelessWidget {
  const _StatCell({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: StcColors.textMuted, fontSize: 9, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class StcRosterRow extends StatelessWidget {
  const StcRosterRow({super.key, required this.player, this.onTap});

  final Map<String, dynamic> player;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final photo = player['photo'] as String?;
    final name = (player['name'] as String? ?? '').toUpperCase();
    final position = (player['position'] as String? ?? '').toUpperCase();
    final jersey = player['jersey'];
    final status = (player['status'] as String? ?? '').toLowerCase();
    final enabled = status.contains('habil') || status.contains('apto') || status.isEmpty;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 8),
        child: Row(
          children: [
            SizedBox(
              width: 24,
              child: Text('$jersey', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14)),
            ),
            const SizedBox(width: 8),
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.4)),
                image: photo != null && photo.isNotEmpty ? DecorationImage(image: NetworkImage(photo), fit: BoxFit.cover) : null,
                color: StcColors.surfaceInner,
              ),
              alignment: Alignment.center,
              child: photo == null || photo.isEmpty
                  ? Text(name.isNotEmpty ? name[0] : '?', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10))
                  : null,
            ),
            const SizedBox(width: 12),
            Expanded(child: Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 9.5))),
            Text(position, style: const TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800)),
            const SizedBox(width: 8),
            if (enabled) const Text('✓', style: TextStyle(color: StcColors.primaryBlue, fontSize: 10, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class StcNextMatchCard extends StatelessWidget {
  const StcNextMatchCard({super.key, required this.match, required this.teamId, this.onTap});

  final Map<String, dynamic> match;
  final int teamId;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final home = match['home'] as Map<String, dynamic>?;
    final away = match['away'] as Map<String, dynamic>?;
    final isHome = home?['id'] == teamId;
    final myTeam = isHome ? home : away;
    final rival = isHome ? away : home;
    final scheduled = match['scheduled_at'] as String?;
    var meta = '';
    if (scheduled != null) {
      final dt = DateTime.tryParse(scheduled)?.toLocal();
      if (dt != null) {
        meta = '${DateFormat('d MMM', 'es').format(dt).toUpperCase()} · ${DateFormat('HH:mm').format(dt)}';
        final field = match['field'] as String?;
        if (field != null && field.isNotEmpty) meta += ' · $field';
      }
    }

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: StcSurfaceCard(
        padding: const EdgeInsets.fromLTRB(15, 17, 15, 12),
        child: Column(
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                StcTeamShield.fromTeam(team: myTeam ?? const {}, size: 50),
                const Text('VS', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 20)),
                StcTeamShield.fromTeam(team: rival ?? const {}, size: 50),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                Expanded(child: Text((myTeam?['name'] as String? ?? '').toUpperCase(), textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800))),
                Expanded(flex: 2, child: Text(meta, textAlign: TextAlign.center, style: const TextStyle(color: StcColors.primaryBlue, fontSize: 8, fontWeight: FontWeight.w800))),
                Expanded(child: Text((rival?['name'] as String? ?? '').toUpperCase(), textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800))),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class StcCredentialCard extends StatelessWidget {
  const StcCredentialCard({
    super.key,
    required this.player,
    required this.tournamentName,
    required this.categoryLabel,
    required this.credentialCode,
  });

  final Map<String, dynamic> player;
  final String tournamentName;
  final String categoryLabel;
  final String credentialCode;

  @override
  Widget build(BuildContext context) {
    final fullName = player['name'] as String? ?? '';
    final parts = fullName.split(' ');
    final firstName = parts.isNotEmpty ? parts.first.toUpperCase() : '';
    final lastName = parts.length > 1 ? parts.sublist(1).join(' ').toUpperCase() : '';
    final photo = player['photo'] as String?;
    final team = player['team'] as Map<String, dynamic>?;
    final teamName = team?['name'] as String? ?? '';
    final jersey = player['jersey']?.toString() ?? '-';
    final status = player['status'] as String? ?? 'Habilitado';
    final enabled = status.toLowerCase().contains('habil') || status.toLowerCase().contains('apto');

    return Container(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: StcColors.cyan.withValues(alpha: 0.55)),
        boxShadow: const [BoxShadow(color: Color(0x2E0059FF), blurRadius: 10)],
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            const Color(0xF2051426),
            StcColors.surface,
            const Color(0xF2051F33),
          ],
        ),
      ),
      padding: const EdgeInsets.fromLTRB(20, 19, 20, 16),
      child: Column(
        children: [
          Image.asset('assets/images/stc_logo.png', height: 92, errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 72)),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 122,
                height: 138,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: StcColors.primaryBlue.withValues(alpha: 0.65)),
                  image: photo != null && photo.isNotEmpty ? DecorationImage(image: NetworkImage(photo), fit: BoxFit.cover) : null,
                  color: StcColors.surfaceInner,
                ),
                alignment: Alignment.center,
                child: photo == null || photo.isEmpty ? Text(firstName.isNotEmpty ? firstName[0] : '?', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 36)) : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(firstName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24, height: 1.05)),
                    if (lastName.isNotEmpty) Text(lastName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 24, height: 1.05)),
                    const SizedBox(height: 8),
                    const Text('Jugador', style: TextStyle(color: StcColors.textBody, fontSize: 11)),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        StcTeamShield.fromTeam(team: team ?? {'name': teamName}, size: 42),
                        const SizedBox(width: 8),
                        Expanded(child: Text(teamName, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12))),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Container(
            height: 72,
            decoration: BoxDecoration(
              color: StcColors.surfaceInner,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: StcColors.borderSoft),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text('CATEGORÍA', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800)),
                      Text(categoryLabel, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
                    ],
                  ),
                ),
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text('N°', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800)),
                      Text(jersey, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Container(
            height: 78,
            padding: const EdgeInsets.fromLTRB(17, 12, 17, 12),
            decoration: BoxDecoration(
              color: StcColors.surfaceInner,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: StcColors.borderSoft),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const SizedBox(width: 70, child: Text('TORNEO', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800))),
                    Expanded(child: Text(tournamentName, style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w600))),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    const SizedBox(width: 70, child: Text('ESTADO', style: TextStyle(color: StcColors.textMuted, fontSize: 8, fontWeight: FontWeight.w800))),
                    Text(
                      enabled ? '${status.toUpperCase()} ✓' : status.toUpperCase(),
                      style: TextStyle(color: enabled ? StcColors.primaryBlue : StcColors.liveRed, fontSize: 15, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Container(
            width: 138,
            height: 92,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: StcColors.cyan.withValues(alpha: 0.45)),
            ),
            child: Stack(
              alignment: Alignment.center,
              children: [
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: List.generate(24, (i) {
                    return Container(
                      width: 10,
                      height: 10,
                      decoration: BoxDecoration(
                        color: i.isEven ? StcColors.primaryBlue : const Color(0xFF050B17),
                        borderRadius: BorderRadius.circular(1),
                      ),
                    );
                  }),
                ),
                const Text('STC', style: TextStyle(color: Color(0xFF050B17), fontWeight: FontWeight.w800, fontSize: 17)),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Text(credentialCode, style: const TextStyle(color: StcColors.textBody, fontSize: 9, fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class StcGradientButton extends StatelessWidget {
  const StcGradientButton({super.key, required this.label, this.onTap});

  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        height: 30,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          gradient: const LinearGradient(colors: [Color(0xFF054DFF), Color(0xFF00C7FF)]),
          border: Border.all(color: StcColors.cyan.withValues(alpha: 0.85)),
        ),
        child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 10.5)),
      ),
    );
  }
}
