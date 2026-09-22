import 'package:flutter/material.dart';

import '../theme/stc_theme.dart';

/// Contenedor estándar de cards según tokens Figma (10.x).
class StcSurfaceCard extends StatelessWidget {
  const StcSurfaceCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(14),
    this.borderColor,
    this.onTap,
  });

  final Widget child;
  final EdgeInsets padding;
  final Color? borderColor;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final card = Container(
      padding: padding,
      decoration: BoxDecoration(
        color: StcColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor ?? StcColors.borderSoft),
        boxShadow: const [
          BoxShadow(
            color: Color(0x330059FF),
            blurRadius: 10,
            spreadRadius: 0,
          ),
        ],
      ),
      child: child,
    );

    if (onTap == null) return card;
    return InkWell(onTap: onTap, borderRadius: BorderRadius.circular(14), child: card);
  }
}
