import 'package:flutter/material.dart';

import '../navigation/stc_navigation.dart';
import '../theme/stc_theme.dart';
import 'stc_app_drawer.dart';
import 'stc_bottom_nav.dart';
import 'stc_public_widgets.dart';

class StcBrowseScaffold extends StatelessWidget {
  const StcBrowseScaffold({
    super.key,
    required this.body,
    this.menuContext = const StcMenuContext(),
    this.headerTitle,
    this.onBack,
    this.showBack = true,
    this.bottomNavIndex,
    this.tournamentId,
    this.showBottomNav = true,
    this.isHomeEntry = false,
  });

  final Widget body;
  final StcMenuContext menuContext;
  final String? headerTitle;
  final VoidCallback? onBack;
  final bool showBack;
  final int? bottomNavIndex;
  final int? tournamentId;
  final bool showBottomNav;
  final bool isHomeEntry;

  @override
  Widget build(BuildContext context) {
    final scaffold = Scaffold(
        backgroundColor: StcColors.background,
        drawer: StcAppDrawer(context: menuContext),
        body: StcPublicBackground(
          child: SafeArea(
            child: Column(
              children: [
                if (headerTitle != null)
                  StcPublicHeader(
                    title: headerTitle!,
                    showBack: showBack,
                    onBack: onBack,
                    showMenu: true,
                  ),
                Expanded(child: body),
                if (showBottomNav && bottomNavIndex != null)
                  StcBottomNav(
                    currentIndex: bottomNavIndex!,
                    tournamentId: tournamentId ?? menuContext.tournamentId,
                    categoryId: menuContext.categoryId,
                  ),
              ],
            ),
          ),
        ),
    );

    if (isHomeEntry) {
      return StcHomeBackScope(child: scaffold);
    }
    return StcBackScope(fallback: '/tournaments', child: scaffold);
  }
}
