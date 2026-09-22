import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import 'auth_controller.dart';

class LandingScreen extends ConsumerWidget {
  const LandingScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return StcAuthScaffold(
      showLogo: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Align(
            alignment: Alignment.centerLeft,
            child: IconButton(
              onPressed: () => context.push('/login'),
              icon: const Text('☰', style: TextStyle(color: Colors.white, fontSize: 24)),
            ),
          ),
          const SizedBox(height: 8),
          Center(
            child: Image.asset(
              'assets/images/stc_logo.png',
              height: 116,
              errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 80),
            ),
          ),
          const SizedBox(height: 40),
          const Text(
            'VIVÍ EL FÚTBOL',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900, letterSpacing: 1.2),
          ),
          const Text(
            'COMO NUNCA',
            textAlign: TextAlign.center,
            style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: 1.4),
          ),
          const SizedBox(height: 12),
          const Text(
            'Torneos, resultados y experiencias\nen un solo lugar',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.textBody, height: 1.4),
          ),
          const SizedBox(height: 36),
          StcPrimaryButton(label: 'Ingresar', onPressed: () => context.go('/login')),
          const SizedBox(height: 12),
          StcSecondaryButton(label: 'Crear cuenta', onPressed: () => context.go('/register')),
          const SizedBox(height: 16),
          TextButton(
            onPressed: () async {
              await ref.read(authControllerProvider.notifier).enterGuestMode();
              if (context.mounted) context.go('/tournaments');
            },
            child: const Text(
              'Explorar sin registrarme',
              style: TextStyle(color: StcColors.textBody, fontWeight: FontWeight.w600),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Argentina · Brasil · Internacional',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.textMuted, fontSize: 12),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}
