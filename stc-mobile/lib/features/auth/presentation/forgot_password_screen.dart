import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/status_views.dart';
import 'auth_controller.dart';

class ForgotPasswordScreen extends ConsumerStatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  ConsumerState<ForgotPasswordScreen> createState() =>
      _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends ConsumerState<ForgotPasswordScreen> {
  final _emailController = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final email = _emailController.text.trim();
    if (email.isEmpty) {
      setState(() => _error = 'Ingresá tu correo.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await ref.read(authControllerProvider.notifier).forgotPassword(email);
      if (mounted) {
        context.go('/forgot-password/sent?email=${Uri.encodeComponent(email)}');
      }
    } catch (error) {
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return StcAuthScaffold(
      topRight: Column(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Text(
            'RECUPERAR ACCESO',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  letterSpacing: 1.2,
                  fontWeight: FontWeight.w800,
                ),
          ),
          const Text('Cuenta STC', style: TextStyle(color: StcColors.cyan)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 12),
          const Center(child: StcCircleIcon(icon: Icons.lock_outline)),
          const SizedBox(height: 20),
          Text('¿No podés entrar?', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          const Text(
            'Ingresá tu correo y te mandamos un enlace para volver a entrar a tu cuenta.',
          ),
          const SizedBox(height: 24),
          TextField(
            controller: _emailController,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(
              labelText: 'CORREO REGISTRADO',
              hintText: 'tu.correo@ejemplo.com',
            ),
          ),
          const SizedBox(height: 16),
          const StcInfoCard(
            title: 'Te vamos a enviar un link seguro',
            body:
                'Si tu cuenta es staff, el organizador también puede revisar o reactivar tu acceso.',
            footer: 'El enlace vence por seguridad.',
          ),
          if (_error != null) ...[
            const SizedBox(height: 16),
            ErrorBanner(message: _error!),
          ],
          const SizedBox(height: 24),
          StcPrimaryButton(
            label: 'Enviar enlace',
            loading: _loading,
            onPressed: _submit,
          ),
          const SizedBox(height: 12),
          StcSecondaryButton(
            label: 'Volver al login',
            onPressed: () => context.go('/login'),
          ),
          const SizedBox(height: 18),
          const Text(
            '¿Necesitás ayuda? Contactá al organizador del torneo.',
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
