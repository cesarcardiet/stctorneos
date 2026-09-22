import 'package:flutter/material.dart';

import '../theme/stc_theme.dart';

class StcFormField extends StatelessWidget {
  const StcFormField({
    super.key,
    required this.label,
    required this.value,
    this.maxLines = 1,
  });

  final String label;
  final String value;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xAA06101F),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: StcColors.border, width: 1.2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              color: StcColors.textMuted,
              fontSize: 10,
              letterSpacing: 1.1,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value.isEmpty ? '—' : value,
            maxLines: maxLines,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class StcFormRow extends StatelessWidget {
  const StcFormRow({
    super.key,
    required this.children,
    this.spacing = 10,
  });

  final List<Widget> children;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < children.length; i++) ...[
          if (i > 0) SizedBox(width: spacing),
          Expanded(child: children[i]),
        ],
      ],
    );
  }
}

class StcStepHeader extends StatelessWidget {
  const StcStepHeader({
    super.key,
    required this.step,
    required this.total,
    required this.description,
  });

  final int step;
  final int total;
  final String description;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: StcColors.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: StcColors.border.withValues(alpha: 0.6)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'PASO $step DE $total',
            style: const TextStyle(
              color: StcColors.textMuted,
              fontSize: 11,
              letterSpacing: 1.2,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          Text(description, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    );
  }
}

class StcStatusBadge extends StatelessWidget {
  const StcStatusBadge({
    super.key,
    required this.label,
    this.color = const Color(0xFF00C853),
    this.icon,
  });

  final String label;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.7)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 4),
          ],
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.6,
            ),
          ),
        ],
      ),
    );
  }
}

class StcPhotoPicker extends StatelessWidget {
  const StcPhotoPicker({
    super.key,
    this.photoUrl,
    this.placeholder = 'FOTO OPCIONAL',
    this.square = false,
    this.onTap,
  });

  final String? photoUrl;
  final String placeholder;
  final bool square;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final shape = square ? BoxShape.rectangle : BoxShape.circle;
    final radius = square ? BorderRadius.circular(16) : null;

    final picker = Stack(
      clipBehavior: Clip.none,
      children: [
        Container(
          width: square ? 96 : 110,
          height: square ? 96 : 110,
          decoration: BoxDecoration(
            shape: shape,
            borderRadius: radius,
            border: Border.all(color: StcColors.cyan, width: 2),
            color: const Color(0x66081224),
            image: photoUrl != null && photoUrl!.isNotEmpty
                ? DecorationImage(
                    image: NetworkImage(photoUrl!),
                    fit: BoxFit.cover,
                  )
                : null,
          ),
          alignment: Alignment.center,
          child: photoUrl == null || photoUrl!.isEmpty
              ? Text(
                  placeholder,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0.8,
                  ),
                )
              : null,
        ),
        Positioned(
          right: square ? -4 : 0,
          bottom: square ? -4 : 0,
          child: Container(
            width: 34,
            height: 34,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: StcColors.cyan,
              border: Border.all(color: StcColors.background, width: 2),
            ),
            child: const Icon(Icons.photo_camera, color: Colors.white, size: 18),
          ),
        ),
      ],
    );

    if (onTap == null) return picker;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(square ? 16 : 999),
      child: picker,
    );
  }
}

class StcDropdownField extends StatelessWidget {
  const StcDropdownField({
    super.key,
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final String label;
  final String? value;
  final List<String> items;
  final ValueChanged<String?> onChanged;

  @override
  Widget build(BuildContext context) {
    return InputDecorator(
      decoration: InputDecoration(
        isDense: true,
        filled: true,
        fillColor: const Color(0xAA06101F),
        labelText: label.toUpperCase(),
        labelStyle: const TextStyle(
          color: StcColors.textMuted,
          fontSize: 10,
          letterSpacing: 1.1,
          fontWeight: FontWeight.w600,
        ),
        contentPadding: const EdgeInsets.fromLTRB(14, 16, 14, 4),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: StcColors.border, width: 1.2),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: BorderSide(color: StcColors.cyan.withValues(alpha: 0.85), width: 1.4),
        ),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: value != null && items.contains(value) ? value : null,
          isExpanded: true,
          hint: Text(
            items.isEmpty ? 'Sin opciones' : 'Seleccionar',
            style: const TextStyle(color: StcColors.textMuted, fontSize: 15, fontWeight: FontWeight.w600),
          ),
          dropdownColor: StcColors.surface,
          style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w600),
          items: items
              .map((item) => DropdownMenuItem<String>(value: item, child: Text(item)))
              .toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }
}

String tournamentStatusLabel(String? status) {
  return switch (status) {
    'in_progress' => 'ACTIVO',
    'registration' => 'INSCRIPCIÓN',
    'preparation' => 'PRÓXIMO',
    'finished' => 'HISTÓRICO',
    'archived' => 'HISTÓRICO',
    _ => 'PRÓXIMO',
  };
}

Color tournamentStatusColor(String? status) {
  return switch (status) {
    'in_progress' => const Color(0xFF2EFF94),
    'registration' => StcColors.cyan,
    'preparation' => StcColors.gold,
    'finished' => StcColors.primaryBlue,
    'archived' => StcColors.primaryBlue,
    _ => StcColors.primaryBlue,
  };
}
