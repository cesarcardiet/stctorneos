import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import 'auth_controller.dart';

class PendingAccountScreen extends ConsumerWidget {
  const PendingAccountScreen({super.key, this.email});

  final String? email;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 8),
          Text(
            'CUENTA PENDIENTE',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 8),
          const Text(
            'Tu acceso staff necesita aprobación del organizador.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          const Center(
            child: StcCircleIcon(
              icon: Icons.hourglass_top,
              borderColor: StcColors.goldBorder,
            ),
          ),
          const SizedBox(height: 24),
          StcInfoCard(
            title: 'EN REVISIÓN',
            titleColor: StcColors.gold,
            borderColor: StcColors.goldBorder,
            body:
                'El organizador va a validar tu rol, club y permisos asignados.${email != null ? '\n\nCorreo: $email' : ''}',
            footer: 'Tiempo estimado: dentro del día del torneo.',
          ),
          const SizedBox(height: 24),
          StcPrimaryButton(
            label: 'Ver estado',
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text(
                    'Tu cuenta sigue pendiente. El organizador debe aprobarla desde la web.',
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 12),
          StcSecondaryButton(
            label: 'Volver al login',
            onPressed: () => context.go('/login'),
          ),
          const SizedBox(height: 18),
          const Text(
            'Si necesitás ayuda, escribile al delegado o a la organización.',
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
