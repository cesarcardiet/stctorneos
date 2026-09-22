import 'package:flutter/material.dart';

import '../theme/stc_theme.dart';

class StcBackButton extends StatelessWidget {
  const StcBackButton({super.key, this.onPressed});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: Alignment.centerLeft,
      child: Material(
        color: StcColors.surface,
        borderRadius: BorderRadius.circular(10),
        child: InkWell(
          onTap: onPressed ?? () => Navigator.of(context).maybePop(),
          borderRadius: BorderRadius.circular(10),
          child: Container(
            height: 24,
            padding: const EdgeInsets.symmetric(horizontal: 8),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: StcColors.borderSoft),
            ),
            alignment: Alignment.center,
            child: const Text(
              '‹ VOLVER',
              style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w800),
            ),
          ),
        ),
      ),
    );
  }
}

class StcAuthField extends StatelessWidget {
  const StcAuthField({
    super.key,
    required this.label,
    required this.controller,
    this.hint,
    this.obscureText = false,
    this.keyboardType,
    this.focused = false,
    this.validator,
    this.onChanged,
    this.suffix,
  });

  final String label;
  final TextEditingController controller;
  final String? hint;
  final bool obscureText;
  final TextInputType? keyboardType;
  final bool focused;
  final String? Function(String?)? validator;
  final ValueChanged<String>? onChanged;
  final Widget? suffix;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      obscureText: obscureText,
      keyboardType: keyboardType,
      validator: validator,
      onChanged: onChanged,
      style: const TextStyle(color: StcColors.textBody, fontSize: 11),
      decoration: InputDecoration(
        isDense: true,
        filled: true,
        fillColor: StcColors.surfaceInner,
        labelText: label.toUpperCase(),
        labelStyle: const TextStyle(
          color: StcColors.textMuted,
          fontSize: 7.5,
          fontWeight: FontWeight.w600,
          letterSpacing: 0.6,
        ),
        hintText: hint,
        hintStyle: const TextStyle(color: StcColors.textMuted, fontSize: 11),
        contentPadding: const EdgeInsets.fromLTRB(13, 18, 13, 12),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(
            color: focused ? StcColors.cyan.withValues(alpha: 0.85) : StcColors.primaryBlue.withValues(alpha: 0.25),
            width: focused ? 1.4 : 1,
          ),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: StcColors.cyan.withValues(alpha: 0.85), width: 1.4),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: StcColors.liveRed),
        ),
        suffixIcon: suffix,
      ),
    );
  }
}

class StcRoleOptionCard extends StatelessWidget {
  const StcRoleOptionCard({
    super.key,
    required this.icon,
    required this.title,
    required this.description,
    required this.accent,
    required this.selected,
    required this.onTap,
  });

  final String icon;
  final String title;
  final String description;
  final Color accent;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: StcColors.surface,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          height: 96,
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 19),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: selected ? accent.withValues(alpha: 0.9) : accent.withValues(alpha: 0.45)),
            boxShadow: const [
              BoxShadow(color: Color(0x380059FF), blurRadius: 12),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 54,
                height: 54,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: StcColors.surfaceInner,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: accent.withValues(alpha: 0.75)),
                ),
                child: Text(icon, style: const TextStyle(fontSize: 20)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
                    const SizedBox(height: 4),
                    Text(description, style: const TextStyle(color: StcColors.textMuted, fontSize: 10, height: 1.3)),
                  ],
                ),
              ),
              Text('›', style: TextStyle(color: accent, fontSize: 26, fontWeight: FontWeight.w800)),
            ],
          ),
        ),
      ),
    );
  }
}

class StcDetectedInvitationCard extends StatelessWidget {
  const StcDetectedInvitationCard({
    super.key,
    required this.title,
    required this.subtitle,
    this.footer,
    this.staff = false,
  });

  final String title;
  final String subtitle;
  final String? footer;
  final bool staff;

  @override
  Widget build(BuildContext context) {
    final accent = staff ? const Color(0xFF2EFF94) : const Color(0xFF2EFF94);
    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: StcColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: accent.withValues(alpha: staff ? 0.45 : 0.45)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            staff ? '✓ ROL DETECTADO' : '✓ INVITACIÓN ENCONTRADA',
            style: TextStyle(color: accent, fontWeight: FontWeight.w800, fontSize: 10),
          ),
          const SizedBox(height: 8),
          Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
          const SizedBox(height: 4),
          Text(subtitle, style: const TextStyle(color: StcColors.textMuted, fontSize: 10)),
          if (footer != null) ...[
            const SizedBox(height: 4),
            Text(footer!, style: const TextStyle(color: StcColors.textMuted, fontSize: 10)),
          ],
        ],
      ),
    );
  }
}
