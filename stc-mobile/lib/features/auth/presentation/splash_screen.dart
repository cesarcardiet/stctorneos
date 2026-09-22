import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/widgets/status_views.dart';
import 'auth_controller.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(_bootstrap);
  }

  Future<void> _bootstrap() async {
    try {
      await ref.read(authControllerProvider.notifier).bootstrap().timeout(
        const Duration(seconds: 12),
        onTimeout: () {},
      );
    } catch (_) {}
    if (!mounted) return;

    final auth = ref.read(authControllerProvider);
    if (auth.isAuthenticated) {
      context.go(auth.profileCompleted ? '/tournaments' : '/complete-profile');
    } else if (auth.guestMode) {
      context.go('/tournaments');
    } else {
      context.go('/landing');
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: LoadingView(message: 'Restaurando sesión...'),
    );
  }
}
