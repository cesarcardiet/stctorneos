import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_field.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';

enum RegisterPath { spectator, invitation, staff }

class RegisterChooseTypeScreen extends StatefulWidget {
  const RegisterChooseTypeScreen({super.key});

  @override
  State<RegisterChooseTypeScreen> createState() => _RegisterChooseTypeScreenState();
}

class _RegisterChooseTypeScreenState extends State<RegisterChooseTypeScreen> {
  RegisterPath _selected = RegisterPath.invitation;

  void _continue() {
    switch (_selected) {
      case RegisterPath.spectator:
        context.go('/register/public');
      case RegisterPath.invitation:
        context.go('/register/invitation');
      case RegisterPath.staff:
        context.go('/register/staff');
    }
  }

  @override
  Widget build(BuildContext context) {
    return StcAuthScaffold(
      showLogo: false,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          StcBackButton(onPressed: () => context.go('/landing')),
          const SizedBox(height: 8),
          Center(
            child: Image.asset('assets/images/stc_logo.png', height: 92, errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan, size: 64)),
          ),
          const SizedBox(height: 16),
          const Text(
            'CREAR CUENTA',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.cyan, fontSize: 25, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 6),
          const Text(
            'Elegí cómo querés ingresar al ecosistema STC',
            textAlign: TextAlign.center,
            style: TextStyle(color: StcColors.textBody, fontSize: 12, fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 24),
          StcRoleOptionCard(
            icon: '👁',
            title: 'SOY ESPECTADOR',
            description: 'Ver fixtures, resultados, noticias y estadísticas del torneo.',
            accent: StcColors.primaryBlue,
            selected: _selected == RegisterPath.spectator,
            onTap: () => setState(() => _selected = RegisterPath.spectator),
          ),
          const SizedBox(height: 12),
          StcRoleOptionCard(
            icon: '✉',
            title: 'TENGO INVITACIÓN',
            description: 'Soy padre, tutor o jugador. Ingreso con el código del delegado.',
            accent: StcColors.cyan,
            selected: _selected == RegisterPath.invitation,
            onTap: () => setState(() => _selected = RegisterPath.invitation),
          ),
          const SizedBox(height: 12),
          StcRoleOptionCard(
            icon: '⚙',
            title: 'ACCESO STAFF',
            description: 'Delegado, árbitro u operador. Requiere código administrativo.',
            accent: const Color(0xFF2EFF94),
            selected: _selected == RegisterPath.staff,
            onTap: () => setState(() => _selected = RegisterPath.staff),
          ),
          const SizedBox(height: 16),
          const StcInfoCard(
            title: '🔒  Tus datos quedan protegidos por rol y alcance del torneo.',
            body: 'Podrás completar tu perfil después de crear la cuenta.',
            titleColor: StcColors.textBody,
            borderColor: StcColors.borderSoft,
          ),
          const SizedBox(height: 16),
          StcPrimaryButton(label: 'Continuar', onPressed: _continue),
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
    );
  }
}
