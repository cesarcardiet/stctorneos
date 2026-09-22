import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';

class ForgotPasswordSentScreen extends StatelessWidget {
  const ForgotPasswordSentScreen({super.key, required this.email});

  final String email;

  @override
  Widget build(BuildContext context) {
    return StcAuthScaffold(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const SizedBox(height: 24),
          const Center(child: StcCircleIcon(icon: Icons.mark_email_read_outlined)),
          const SizedBox(height: 24),
          Text('REVISÁ TU CORREO', style: Theme.of(context).textTheme.headlineMedium),
          const SizedBox(height: 12),
          Text('Enviamos un enlace seguro a $email'),
          const SizedBox(height: 20),
          const StcInfoCard(
            title: 'Próximo paso',
            body:
                'Abrí el correo desde tu teléfono y seguí el enlace para definir una nueva contraseña.',
            footer: 'Si no lo ves, revisá spam o correo no deseado.',
          ),
          const SizedBox(height: 24),
          StcPrimaryButton(
            label: 'Volver al login',
            onPressed: () => context.go('/login'),
          ),
        ],
      ),
    );
  }
}
