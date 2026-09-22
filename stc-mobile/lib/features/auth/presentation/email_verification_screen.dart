import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import 'auth_controller.dart';

class EmailVerificationScreen extends ConsumerStatefulWidget {
  const EmailVerificationScreen({super.key, required this.email});

  final String email;

  @override
  ConsumerState<EmailVerificationScreen> createState() =>
      _EmailVerificationScreenState();
}

class _EmailVerificationScreenState
    extends ConsumerState<EmailVerificationScreen> {
  final _codeController = TextEditingController();
  bool _loading = false;

  Future<void> _confirm() async {
    setState(() => _loading = true);
    try {
      await ref.read(authControllerProvider.notifier).bootstrap();
      if (mounted) context.go('/complete-profile');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 8),
          Text(
            'VERIFICÁ TU EMAIL',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.headlineMedium,
          ),
          const SizedBox(height: 8),
          const Text(
            'Tu cuenta quedó activa con la invitación. Confirmá para continuar.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          const Center(child: StcCircleIcon(icon: Icons.mail_outline)),
          const SizedBox(height: 24),
          const Text('Código enviado a', textAlign: TextAlign.center),
          const SizedBox(height: 6),
          Text(
            widget.email,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w800,
              fontSize: 18,
            ),
          ),
          const SizedBox(height: 20),
          TextField(
            controller: _codeController,
            textAlign: TextAlign.center,
            keyboardType: TextInputType.number,
            inputFormatters: [
              FilteringTextInputFormatter.digitsOnly,
              LengthLimitingTextInputFormatter(6),
            ],
            style: const TextStyle(
              fontSize: 28,
              letterSpacing: 12,
              fontWeight: FontWeight.w700,
            ),
            decoration: const InputDecoration(
              hintText: '000000',
              hintStyle: TextStyle(letterSpacing: 12, color: StcColors.textMuted),
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Por ahora la invitación valida tu correo. Este paso confirma el acceso en la app.',
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 24),
          StcPrimaryButton(
            label: 'Confirmar email',
            loading: _loading,
            onPressed: _confirm,
          ),
          const SizedBox(height: 12),
          StcSecondaryButton(
            label: 'Reenviar código',
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text(
                    'Si necesitás un código nuevo, pedile al organizador una invitación.',
                  ),
                ),
              );
            },
          ),
          const SizedBox(height: 16),
          const Text(
            'Revisá spam o correo no deseado si no lo ves.',
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }
}
