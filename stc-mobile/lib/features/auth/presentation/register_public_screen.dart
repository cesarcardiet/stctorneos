import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_exception.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_field.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/status_views.dart';
import 'auth_controller.dart';

class RegisterPublicScreen extends ConsumerStatefulWidget {
  const RegisterPublicScreen({super.key});

  @override
  ConsumerState<RegisterPublicScreen> createState() => _RegisterPublicScreenState();
}

class _RegisterPublicScreenState extends ConsumerState<RegisterPublicScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_passwordController.text != _confirmController.text) {
      setState(() => _error = 'Las contraseñas no coinciden.');
      return;
    }

    setState(() => _error = null);

    try {
      await ref.read(authControllerProvider.notifier).registerSpectator(
            name: _nameController.text,
            email: _emailController.text,
            password: _passwordController.text,
          );
      if (mounted) context.go('/complete-profile');
    } on AppException catch (error) {
      setState(() => _error = error.message);
    } catch (error) {
      setState(() => _error = error.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final loading = ref.watch(authControllerProvider).loading;

    return StcAuthScaffold(
      showLogo: false,
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            StcBackButton(onPressed: () => context.go('/register')),
            const SizedBox(height: 8),
            Center(
              child: Image.asset('assets/images/stc_logo.png', height: 90, errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 64)),
            ),
            const SizedBox(height: 16),
            const Text(
              'REGISTRO PÚBLICO',
              textAlign: TextAlign.center,
              style: TextStyle(color: StcColors.cyan, fontSize: 22, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            const Text(
              'Creá tu cuenta para seguir torneos, resultados y estadísticas.',
              textAlign: TextAlign.center,
              style: TextStyle(color: StcColors.textBody, fontSize: 12),
            ),
            const SizedBox(height: 20),
            StcAuthField(
              label: 'Nombre completo',
              controller: _nameController,
              hint: 'Juan Martín Gómez',
              validator: (v) => v == null || v.trim().isEmpty ? 'Ingresá tu nombre' : null,
            ),
            const SizedBox(height: 12),
            StcAuthField(
              label: 'Email',
              controller: _emailController,
              hint: 'correo@ejemplo.com',
              keyboardType: TextInputType.emailAddress,
              validator: (v) => v == null || !v.contains('@') ? 'Ingresá un correo válido' : null,
            ),
            const SizedBox(height: 12),
            StcAuthField(
              label: 'Contraseña',
              controller: _passwordController,
              hint: 'Mínimo 8 caracteres',
              obscureText: true,
              validator: (v) => v == null || v.length < 8 ? 'Mínimo 8 caracteres' : null,
            ),
            const SizedBox(height: 12),
            StcAuthField(
              label: 'Confirmar contraseña',
              controller: _confirmController,
              hint: 'Repetí tu contraseña',
              obscureText: true,
            ),
            const SizedBox(height: 16),
            const StcInfoCard(
              title: '🔐  Tu cuenta inicia como espectador.',
              body: 'Luego podrás vincularte como jugador, padre o staff con un código.',
              titleColor: StcColors.textBody,
              borderColor: StcColors.borderSoft,
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              ErrorBanner(message: _error!),
            ],
            const SizedBox(height: 16),
            StcPrimaryButton(label: 'Crear cuenta', loading: loading, onPressed: _submit),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => context.go('/login'),
              child: const Text(
                '¿Ya tenés cuenta?  INICIAR SESIÓN',
                style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
