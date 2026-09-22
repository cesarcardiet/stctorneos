import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

/// Compatibilidad con rutas antiguas.
class InvitationScreen extends StatelessWidget {
  const InvitationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (context.mounted) context.go('/register/invitation');
    });
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}
