import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../shared/widgets/status_views.dart';
import '../../auth/domain/profile_completion.dart';
import '../../auth/presentation/auth_controller.dart';
import 'role_profile_views.dart';

class CompleteProfileScreen extends ConsumerStatefulWidget {
  const CompleteProfileScreen({super.key});

  @override
  ConsumerState<CompleteProfileScreen> createState() => _CompleteProfileScreenState();
}

class _CompleteProfileScreenState extends ConsumerState<CompleteProfileScreen> {
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  String? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final user = ref.read(authControllerProvider).user;
      if (user == null) return;
      _nameController.text = user.name;
      _phoneController.text = user.phone ?? '';
      if (!profileNeedsCompletion(user)) {
        await ref.read(authControllerProvider.notifier).markProfileCompleted();
        if (mounted) context.go('/home');
      }
    });
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
      if (mounted) context.go('/home');
    } catch (error) {
      setState(() => _error = error.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).user;
    final loading = ref.watch(authControllerProvider).loading;

    if (user == null) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    return Scaffold(
      body: Column(
        children: [
          Expanded(
            child: RoleProfileView(
              user: user,
              saving: loading,
              onSave: _save,
              nameController: _nameController,
              phoneController: _phoneController,
            ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(16),
              child: ErrorBanner(message: _error!),
            ),
        ],
      ),
    );
  }
}
