import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_bottom_nav.dart';
import '../../../shared/widgets/status_views.dart';
import '../../auth/presentation/auth_controller.dart';
import 'role_profile_views.dart';

class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _syncControllers());
  }

  void _syncControllers() {
    final user = ref.read(authControllerProvider).user;
    _nameController.text = user?.name ?? '';
    _phoneController.text = user?.phone ?? '';
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _error = null);
    try {
      await ref.read(authControllerProvider.notifier).updateProfile(
            name: _nameController.text.trim(),
            phone: _phoneController.text.trim().isEmpty ? null : _phoneController.text.trim(),
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Perfil actualizado')),
        );
      }
    } catch (error) {
      setState(() => _error = error.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final user = auth.user;

    if (user == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    if (_nameController.text.isEmpty && user.name.isNotEmpty) {
      _nameController.text = user.name;
      _phoneController.text = user.phone ?? '';
    }

    final navItems = user.navigation.where((item) {
      final enabled = item['enabled'] as bool? ?? true;
      final route = item['route'] as String?;
      final key = item['key'] as String?;
      return enabled && route != null && key != 'home';
    }).toList();

    return Scaffold(
      backgroundColor: StcColors.background,
      body: SafeArea(
        child: Column(
        children: [
          Expanded(
            child: RoleProfileView(
              user: user,
              saving: auth.loading,
              onSave: _save,
              nameController: _nameController,
              phoneController: _phoneController,
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 0, 18, 8),
              child: ErrorBanner(message: _error!),
            ),
          if (navItems.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 0, 18, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'ACCESOS RÁPIDOS',
                    style: TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 11),
                  ),
                  const SizedBox(height: 8),
                  ...navItems.map((item) {
                    final label = item['label'] as String? ?? 'Ir';
                    final route = item['route'] as String?;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: StcSecondaryButton(
                        label: label,
                        onPressed: route == null ? () {} : () => context.push(route),
                      ),
                    );
                  }),
                ],
              ),
            ),
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: StcSecondaryButton(
              label: 'Cerrar sesión',
              onPressed: () async {
                await ref.read(authControllerProvider.notifier).logout();
                if (context.mounted) context.go('/login');
              },
            ),
          ),
          StcBottomNav(currentIndex: stcNavIndexForPath('/profile')),
        ],
        ),
      ),
    );
  }
}
