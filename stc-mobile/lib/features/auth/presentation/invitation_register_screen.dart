import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../core/errors/app_exception.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_field.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/status_views.dart';
import '../../auth/domain/user_profile.dart';
import 'auth_controller.dart';

enum InvitationRegisterVariant { family, staff }

class InvitationRegisterScreen extends ConsumerStatefulWidget {
  const InvitationRegisterScreen({super.key, required this.variant});

  final InvitationRegisterVariant variant;

  @override
  ConsumerState<InvitationRegisterScreen> createState() => _InvitationRegisterScreenState();
}

class _InvitationRegisterScreenState extends ConsumerState<InvitationRegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _codeController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _nameController = TextEditingController();

  InvitationPreview? _preview;
  bool _loading = false;
  String? _error;

  bool get _isStaff => widget.variant == InvitationRegisterVariant.staff;

  @override
  void dispose() {
    _codeController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _nameController.dispose();
    super.dispose();
  }

  Future<void> _validate() async {
    if (_codeController.text.trim().isEmpty || _emailController.text.trim().isEmpty) {
      setState(() => _error = 'Ingresá código y correo.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final preview = await ref.read(authControllerProvider.notifier).previewInvitation(
            _emailController.text,
            _codeController.text,
          );
      setState(() {
        _preview = preview;
        _nameController.text = preview.name;
        _loading = false;
      });
    } catch (error) {
      setState(() {
        _error = error.toString();
        _loading = false;
        _preview = null;
      });
    }
  }

  Future<void> _activate() async {
    if (_preview == null) {
      await _validate();
      return;
    }

    if (_passwordController.text.length < 8) {
      setState(() => _error = 'La contraseña debe tener al menos 8 caracteres.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await ref.read(authControllerProvider.notifier).acceptInvitation(
            email: _emailController.text,
            code: _codeController.text,
            password: _passwordController.text,
            name: _preview!.mode == 'register' ? _nameController.text : null,
          );

      if (!mounted) return;

      final user = ref.read(authControllerProvider).user;
      if (user?.status == 'pending') {
        context.go('/pending-account?email=${Uri.encodeComponent(_emailController.text.trim())}');
      } else {
        context.go('/complete-profile');
      }
    } on AppException catch (error) {
      if (error.statusCode == 403 && error.message.toLowerCase().contains('pendiente')) {
        if (mounted) {
          context.go('/pending-account?email=${Uri.encodeComponent(_emailController.text.trim())}');
        }
        return;
      }
      setState(() {
        _error = error.message;
        _loading = false;
      });
    } catch (error) {
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final accent = _isStaff ? const Color(0xFF2EFF94) : StcColors.cyan;

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
              child: Image.asset('assets/images/stc_logo.png', height: 90, errorBuilder: (_, __, ___) => Icon(Icons.shield, color: accent, size: 64)),
            ),
            const SizedBox(height: 12),
            Text(
              _isStaff ? 'ACCESO STAFF' : 'CÓDIGO DE INVITACIÓN',
              textAlign: TextAlign.center,
              style: TextStyle(color: accent, fontSize: 22, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 6),
            Text(
              _isStaff
                  ? 'Delegados, árbitros y asistentes de cancha ingresan con código autorizado.'
                  : 'Ingresá el código que te pasó tu delegado, club o referente del torneo.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: StcColors.textBody, fontSize: 12),
            ),
            const SizedBox(height: 20),
            StcAuthField(
              label: _isStaff ? 'Código staff' : 'Código de invitación',
              controller: _codeController,
              hint: _isStaff ? 'STC-STAFF-2026' : 'STC-2026-AB12C3',
              focused: _preview == null,
              onChanged: (_) {
                if (_preview != null) setState(() => _preview = null);
              },
            ),
            const SizedBox(height: 12),
            StcAuthField(
              label: _isStaff ? 'Email institucional' : 'Correo electrónico',
              controller: _emailController,
              hint: _isStaff ? 'admin@club.com' : 'tu.correo@ejemplo.com',
              keyboardType: TextInputType.emailAddress,
              onChanged: (_) {
                if (_preview != null) setState(() => _preview = null);
              },
            ),
            const SizedBox(height: 12),
            if (_preview?.mode == 'register') ...[
              StcAuthField(
                label: 'Nombre completo',
                controller: _nameController,
                hint: 'Nombre y apellido',
              ),
              const SizedBox(height: 12),
            ],
            StcAuthField(
              label: 'Contraseña',
              controller: _passwordController,
              hint: 'Mínimo 8 caracteres',
              obscureText: true,
            ),
            const SizedBox(height: 16),
            if (_preview != null)
              StcDetectedInvitationCard(
                staff: _isStaff,
                title: _preview!.scopeLabel ?? _preview!.tournamentName ?? 'Invitación STC',
                subtitle: 'Rol: ${_preview!.roleName ?? 'Por confirmar'}',
                footer: _preview!.tournamentName != null ? 'Torneo: ${_preview!.tournamentName}' : null,
              )
            else
              StcInfoCard(
                title: _isStaff ? '¿No tenés código staff?' : '¿No tenés código?',
                body: _isStaff
                    ? 'Solo el organizador puede generar o revocar códigos staff.'
                    : 'Pedíselo al delegado del club o al organizador del torneo.',
                borderColor: StcColors.borderSoft,
                titleColor: Colors.white,
              ),
            if (_isStaff && _preview != null) ...[
              const SizedBox(height: 12),
              const StcInfoCard(
                title: '⚠ Acceso administrativo',
                body: 'Solo el organizador puede generar o revocar códigos staff.',
                borderColor: StcColors.goldBorder,
                titleColor: StcColors.gold,
              ),
            ],
            if (_error != null) ...[
              const SizedBox(height: 12),
              ErrorBanner(message: _error!),
            ],
            const SizedBox(height: 16),
            StcPrimaryButton(
              label: _preview == null ? 'Validar y continuar' : (_isStaff ? 'Activar acceso' : 'Activar cuenta'),
              loading: _loading,
              onPressed: _preview == null ? _validate : _activate,
            ),
            const SizedBox(height: 10),
            Text(
              _isStaff
                  ? 'Al activar, aceptás las reglas del torneo y auditoría de acciones.'
                  : 'Tu acceso queda asociado al torneo STC correspondiente.',
              textAlign: TextAlign.center,
              style: const TextStyle(color: StcColors.textMuted, fontSize: 10),
            ),
          ],
        ),
      ),
    );
  }
}
