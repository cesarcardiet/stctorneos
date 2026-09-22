import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_exception.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/status_views.dart';
import 'auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscure = true;
  bool _rememberMe = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    Future.microtask(_loadRememberMe);
  }

  Future<void> _loadRememberMe() async {
    final settings = await ref.read(authControllerProvider.notifier).rememberMeSettings();
    if (!mounted) return;
    setState(() {
      _rememberMe = settings.enabled;
      if (settings.email != null && settings.email!.isNotEmpty) {
        _emailController.text = settings.email!;
      }
    });
  }

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _error = null);

    try {
      await ref.read(authControllerProvider.notifier).login(
            _emailController.text,
            _passwordController.text,
            rememberMe: _rememberMe,
          );
      if (!mounted) return;
      final auth = ref.read(authControllerProvider);
      context.go(auth.profileCompleted ? '/home' : '/complete-profile');
    } on AppException catch (error) {
      if (error.statusCode == 403 &&
          error.message.toLowerCase().contains('pendiente')) {
        if (mounted) {
          context.go(
            '/pending-account?email=${Uri.encodeComponent(_emailController.text.trim())}',
          );
        }
        return;
      }
      setState(() => _error = error.message);
    } catch (error) {
      setState(() => _error = error.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final loading = ref.watch(authControllerProvider).loading;

    return StcAuthScaffold(
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: 16),
            Text(
              'INICIAR SESIÓN',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineMedium,
            ),
            const SizedBox(height: 8),
            const Text(
              'Ingresá con el correo y contraseña de tu invitación STC.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 28),
            TextFormField(
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                labelText: 'CORREO',
                hintText: 'tu.correo@ejemplo.com',
              ),
              validator: (value) =>
                  value == null || value.trim().isEmpty ? 'Ingresá tu correo' : null,
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: _passwordController,
              obscureText: _obscure,
              decoration: InputDecoration(
                labelText: 'CONTRASEÑA',
                suffixIcon: IconButton(
                  onPressed: () => setState(() => _obscure = !_obscure),
                  icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
                ),
              ),
              validator: (value) =>
                  value == null || value.isEmpty ? 'Ingresá tu contraseña' : null,
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                SizedBox(
                  height: 28,
                  width: 28,
                  child: Checkbox(
                    value: _rememberMe,
                    activeColor: StcColors.cyan,
                    side: const BorderSide(color: StcColors.textMuted),
                    onChanged: (value) => setState(() => _rememberMe = value ?? true),
                  ),
                ),
                const Expanded(
                  child: Text(
                    'Recordarme en este dispositivo',
                    style: TextStyle(color: StcColors.textBody, fontSize: 13),
                  ),
                ),
                TextButton(
                  onPressed: () => context.go('/forgot-password'),
                  child: const Text('Recuperar clave', style: TextStyle(color: StcColors.cyan)),
                ),
              ],
            ),
            if (_error != null) ...[
              ErrorBanner(message: _error!),
              const SizedBox(height: 12),
            ],
            StcPrimaryButton(
              label: 'Ingresar',
              loading: loading,
              onPressed: _submit,
            ),
            const SizedBox(height: 12),
            StcSecondaryButton(
              label: 'Tengo invitación',
              onPressed: () => context.go('/register/invitation'),
            ),
          ],
        ),
      ),
    );
  }
}
