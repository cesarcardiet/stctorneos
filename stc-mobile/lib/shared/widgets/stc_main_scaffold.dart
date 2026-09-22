import 'package:flutter/material.dart';

import '../theme/stc_theme.dart';
import 'stc_bottom_nav.dart';

class StcMainScaffold extends StatelessWidget {
  const StcMainScaffold({
    super.key,
    required this.body,
    required this.navIndex,
    this.showBack = false,
    this.title,
    this.actions,
    this.onBack,
  });

  final Widget body;
  final int navIndex;
  final bool showBack;
  final String? title;
  final List<Widget>? actions;
  final VoidCallback? onBack;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StcColors.background,
      body: Stack(
        fit: StackFit.expand,
        children: [
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Color(0xFF0A1B34), Color(0xFF020714), Color(0xFF01040C)],
              ),
            ),
          ),
          SafeArea(
            child: Column(
              children: [
                if (showBack || title != null || actions != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(8, 4, 8, 0),
                    child: Row(
                      children: [
                        if (showBack)
                          IconButton(
                            onPressed: onBack ?? () => Navigator.of(context).maybePop(),
                            icon: const Icon(Icons.arrow_back, color: Colors.white),
                          )
                        else
                          const SizedBox(width: 8),
                        if (title != null)
                          Expanded(
                            child: Text(
                              title!,
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1.1,
                              ),
                            ),
                          )
                        else
                          const Spacer(),
                        ...?actions,
                      ],
                    ),
                  ),
                Expanded(child: body),
                StcBottomNav(currentIndex: navIndex),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
