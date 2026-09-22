import 'package:flutter/material.dart';

import '../theme/stc_theme.dart';

class StcAuthScaffold extends StatelessWidget {
  const StcAuthScaffold({
    super.key,
    required this.child,
    this.showLogo = true,
    this.topRight,
  });

  final Widget child;
  final bool showLogo;
  final Widget? topRight;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Color(0xFF0A1B34),
                  Color(0xFF020714),
                  Color(0xFF01040C),
                ],
              ),
            ),
          ),
          Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Colors.black.withValues(alpha: 0.15),
                  Colors.black.withValues(alpha: 0.55),
                ],
              ),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      if (showLogo)
                        Image.asset(
                          'assets/images/stc_logo.png',
                          height: 54,
                          errorBuilder: (_, __, ___) => const Icon(
                            Icons.shield,
                            color: StcColors.cyan,
                            size: 48,
                          ),
                        ),
                      const Spacer(),
                      if (topRight != null) topRight!,
                    ],
                  ),
                  const SizedBox(height: 12),
                  Expanded(
                    child: SingleChildScrollView(
                      child: child,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class StcPrimaryButton extends StatelessWidget {
  const StcPrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.loading = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: const LinearGradient(
          colors: [StcColors.cyan, StcColors.cyanDark],
        ),
        boxShadow: [
          BoxShadow(
            color: StcColors.cyan.withValues(alpha: 0.35),
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: ElevatedButton(
        style: ElevatedButton.styleFrom(
          backgroundColor: Colors.transparent,
          shadowColor: Colors.transparent,
          minimumSize: const Size.fromHeight(54),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        ),
        onPressed: loading ? null : onPressed,
        child: loading
            ? const SizedBox(
                height: 22,
                width: 22,
                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
              )
            : Text(
                label.toUpperCase(),
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.1,
                  color: Colors.white,
                ),
              ),
      ),
    );
  }
}

class StcSecondaryButton extends StatelessWidget {
  const StcSecondaryButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size.fromHeight(54),
        side: const BorderSide(color: Colors.white54, width: 1.4),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        foregroundColor: Colors.white,
      ),
      onPressed: onPressed,
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(fontWeight: FontWeight.w800, letterSpacing: 1.1),
      ),
    );
  }
}

class StcInfoCard extends StatelessWidget {
  const StcInfoCard({
    super.key,
    required this.title,
    required this.body,
    this.footer,
    this.borderColor = StcColors.border,
    this.titleColor = StcColors.cyan,
  });

  final String title;
  final String body;
  final String? footer;
  final Color borderColor;
  final Color titleColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: StcColors.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: borderColor),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(
              color: titleColor,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.8,
            ),
          ),
          const SizedBox(height: 8),
          Text(body, style: Theme.of(context).textTheme.bodyMedium),
          if (footer != null) ...[
            const SizedBox(height: 8),
            Text(footer!, style: Theme.of(context).textTheme.bodySmall),
          ],
        ],
      ),
    );
  }
}

class StcCircleIcon extends StatelessWidget {
  const StcCircleIcon({
    super.key,
    required this.icon,
    this.borderColor = StcColors.cyan,
  });

  final IconData icon;
  final Color borderColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 92,
      height: 92,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: borderColor, width: 2),
        color: const Color(0x66081224),
      ),
      child: Icon(icon, color: Colors.white, size: 40),
    );
  }
}
