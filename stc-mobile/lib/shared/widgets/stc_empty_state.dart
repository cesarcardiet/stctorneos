import 'package:flutter/material.dart';

import '../theme/stc_theme.dart';
import 'stc_auth_scaffold.dart';

enum StcEmptyKind {
  noData,
  noPermission,
  offline,
}

class StcEmptyState extends StatelessWidget {
  const StcEmptyState({
    super.key,
    required this.kind,
    this.title,
    this.message,
    this.actionLabel,
    this.onAction,
  });

  final StcEmptyKind kind;
  final String? title;
  final String? message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final defaults = switch (kind) {
      StcEmptyKind.noData => (
          'Sin datos',
          'Todavía no hay información para mostrar acá.',
          Icons.inbox_outlined,
        ),
      StcEmptyKind.noPermission => (
          'Sin permisos',
          'Tu rol no tiene acceso a esta sección.',
          Icons.lock_outline,
        ),
      StcEmptyKind.offline => (
          'Sin conexión',
          'No pudimos conectar con STC Torneos. Revisá tu internet e intentá de nuevo.',
          Icons.wifi_off,
        ),
    };

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(defaults.$3, color: StcColors.cyan, size: 48),
            const SizedBox(height: 16),
            Text(
              title ?? defaults.$1,
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18),
            ),
            const SizedBox(height: 8),
            Text(
              message ?? defaults.$2,
              textAlign: TextAlign.center,
              style: const TextStyle(color: StcColors.textMuted, height: 1.4),
            ),
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 18),
              StcPrimaryButton(label: actionLabel!, onPressed: onAction),
            ],
          ],
        ),
      ),
    );
  }
}
