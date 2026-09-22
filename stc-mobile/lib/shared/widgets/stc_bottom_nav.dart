import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/stc_theme.dart';

class StcBottomNav extends StatelessWidget {
  const StcBottomNav({
    super.key,
    required this.currentIndex,
    this.tournamentId,
    this.categoryId,
  });

  final int currentIndex;
  final int? tournamentId;
  final int? categoryId;

  static const _items = [
    _NavItem('Torneos', '/tournaments', Icons.emoji_events_outlined, Icons.emoji_events_rounded),
    _NavItem('Fixture', '/tournaments', Icons.calendar_month_outlined, Icons.calendar_month_rounded),
    _NavItem('En vivo', '/live', Icons.sensors_outlined, Icons.sensors_rounded),
    _NavItem('Tablas', '/tournaments', Icons.leaderboard_outlined, Icons.leaderboard_rounded),
    _NavItem('Más', '/profile', Icons.menu_outlined, Icons.menu_rounded),
  ];

  String _routeFor(int index) {
    final catQuery = categoryId != null ? '?category=$categoryId' : '';
    if (tournamentId != null) {
      if (index == 1) return '/tournaments/$tournamentId/fixture$catQuery';
      if (index == 3) return '/tournaments/$tournamentId/standings$catQuery';
    }
    return _items[index].route;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(13, 0, 13, 12),
      height: 66,
      decoration: BoxDecoration(
        color: StcColors.navBarBg,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: StcColors.borderSoft.withValues(alpha: 0.45)),
      ),
      child: Row(
        children: List.generate(_items.length, (index) {
          final item = _items[index];
          final active = index == currentIndex;
          return Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 5),
              child: Material(
                color: active ? StcColors.navActiveBg : StcColors.surfaceInner,
                borderRadius: BorderRadius.circular(17),
                child: InkWell(
                  onTap: () => context.push(_routeFor(index)),
                  borderRadius: BorderRadius.circular(17),
                  child: Container(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(17),
                      border: Border.all(
                        color: active
                            ? StcColors.primaryBlue.withValues(alpha: 0.85)
                            : StcColors.primaryBlue.withValues(alpha: 0.12),
                      ),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          active ? item.activeIcon : item.icon,
                          size: 16,
                          color: active ? StcColors.primaryBlue : StcColors.textMuted,
                        ),
                        const SizedBox(height: 2),
                        FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(
                            item.label,
                            maxLines: 1,
                            style: TextStyle(
                              color: active ? StcColors.primaryBlue : StcColors.textMuted,
                              fontSize: 8,
                              fontWeight: FontWeight.w600,
                              height: 1.1,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }
}

class _NavItem {
  const _NavItem(this.label, this.route, this.icon, this.activeIcon);

  final String label;
  final String route;
  final IconData icon;
  final IconData activeIcon;
}

int stcNavIndexForPath(String path) {
  if (path.startsWith('/profile') ||
      path.startsWith('/favorites') ||
      path.startsWith('/content') ||
      path.startsWith('/tutor/') ||
      path.startsWith('/player/portal') ||
      path.startsWith('/workspace/') ||
      path == '/workspace') {
    return 4;
  }
  if (path.startsWith('/live')) return 2;
  if (path.contains('/standings') || path.contains('/scorers') || path.contains('/fair-play')) return 3;
  if (path.contains('/fixture') || path.startsWith('/matches')) return 1;
  if (path.startsWith('/tournaments')) return 0;
  if (path.startsWith('/home')) return 0;
  return 0;
}
