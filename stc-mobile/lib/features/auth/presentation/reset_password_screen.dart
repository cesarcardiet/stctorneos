import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_field.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/status_views.dart';
import 'auth_controller.dart';

class ResetPasswordScreen extends ConsumerStatefulWidget {
  const ResetPasswordScreen({
    super.key,
    required this.email,
    required this.token,
  });

  final String email;
  final String token;

  @override
  ConsumerState<ResetPasswordScreen> createState() => _ResetPasswordScreenState();
}

class _ResetPasswordScreenState extends ConsumerState<ResetPasswordScreen> {
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final password = _passwordController.text;
    final confirm = _confirmController.text;

    if (password.length < 8) {
      setState(() => _error = 'La contraseña debe tener al menos 8 caracteres.');
      return;
    }
    if (password != confirm) {
      setState(() => _error = 'Las contraseñas no coinciden.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await ref.read(authControllerProvider.notifier).resetPassword(
            email: widget.email,
            token: widget.token,
            password: password,
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Contraseña actualizada. Ya podés ingresar.')),
        );
        context.go('/login');
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'NUEVA CLAVE',
            style: TextStyle(
              color: Colors.white,
              fontSize: 26,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 4),
          const Text('Restablecer acceso', style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w700)),
          const SizedBox(height: 18),
          Text(
            widget.email,
            style: const TextStyle(color: StcColors.textMuted, fontSize: 12),
          ),
          const SizedBox(height: 16),
          StcAuthField(
            label: 'Nueva contraseña',
            controller: _passwordController,
            hint: 'Mínimo 8 caracteres',
            obscureText: true,
          ),
          const SizedBox(height: 10),
          StcAuthField(
            label: 'Confirmar contraseña',
            controller: _confirmController,
            hint: 'Repetí la clave',
            obscureText: true,
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            ErrorBanner(message: _error!),
          ],
          const SizedBox(height: 18),
          StcPrimaryButton(label: 'Guardar contraseña', loading: _loading, onPressed: _submit),
          const SizedBox(height: 10),
          TextButton(
            onPressed: () => context.go('/login'),
            child: const Text('Volver al login', style: TextStyle(color: StcColors.cyan)),
          ),
        ],
      ),
    );
  }
}
