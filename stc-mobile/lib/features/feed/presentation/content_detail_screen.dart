import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../shared/navigation/stc_navigation.dart';
import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_empty_state.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class ContentDetailScreen extends ConsumerStatefulWidget {
  const ContentDetailScreen({super.key, required this.slug});

  final String slug;

  @override
  ConsumerState<ContentDetailScreen> createState() => _ContentDetailScreenState();
}

class _ContentDetailScreenState extends ConsumerState<ContentDetailScreen> {
  Map<String, dynamic>? _post;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final post = await ref.read(catalogRepositoryProvider).fetchContentBySlug(widget.slug);
      if (mounted) {
        setState(() {
          _post = post;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _sharePlaque({required bool whatsapp}) async {
    final post = _post;
    final title = post?['title'] as String? ?? 'Placa oficial';
    final link = 'https://stctorneos.com/content/${widget.slug}';
    final text = '$title\n$link';
    final uri = whatsapp
        ? Uri.parse('https://wa.me/?text=${Uri.encodeComponent(text)}')
        : Uri(
            scheme: 'mailto',
            queryParameters: {
              'subject': title,
              'body': text,
            },
          );
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(whatsapp ? 'No se pudo abrir WhatsApp.' : 'No se pudo abrir el correo.')),
      );
    }
  }

  String _formatDate(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    final dt = DateTime.tryParse(raw)?.toLocal();
    if (dt == null) return '';
    return DateFormat("EEEE d 'de' MMMM yyyy", 'es').format(dt);
  }

  @override
  Widget build(BuildContext context) {
    final post = _post;
    final isPlaque = post?['type'] == 'plaque';
    final cover = post?['cover'] as String?;

    return StcBackScope(
      fallback: '/content',
      child: Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(
                title: isPlaque ? 'PLACA OFICIAL' : 'COMUNICADO',
                onBack: () => stcGoBack(context, fallback: '/content'),
              ),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : post == null
                        ? const Center(
                            child: StcEmptyState(
                              kind: StcEmptyKind.noData,
                              title: 'No encontrado',
                              message: 'Este contenido no está disponible.',
                            ),
                          )
                        : ListView(
                            padding: const EdgeInsets.fromLTRB(22, 8, 22, 24),
                            children: [
                              if (cover != null && cover.isNotEmpty)
                                ClipRRect(
                                  borderRadius: BorderRadius.circular(16),
                                  child: ColoredBox(
                                    color: const Color(0xFF071428),
                                    child: Image.network(
                                      cover,
                                      height: isPlaque ? null : 200,
                                      width: double.infinity,
                                      fit: BoxFit.contain,
                                      errorBuilder: (_, __, ___) => const SizedBox.shrink(),
                                    ),
                                  ),
                                ),
                              if (cover != null && cover.isNotEmpty) const SizedBox(height: 16),
                              StcSurfaceCard(
                                padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      (post['type_label'] as String? ?? 'Contenido').toUpperCase(),
                                      style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10),
                                    ),
                                    const SizedBox(height: 10),
                                    Text(
                                      post['title'] as String? ?? '',
                                      style: TextStyle(
                                        color: Colors.white,
                                        fontWeight: FontWeight.w900,
                                        fontSize: isPlaque ? 22 : 18,
                                        height: 1.15,
                                      ),
                                    ),
                                    if ((post['published_at'] as String?)?.isNotEmpty == true) ...[
                                      const SizedBox(height: 8),
                                      Text(
                                        _formatDate(post['published_at'] as String?),
                                        style: const TextStyle(color: StcColors.primaryBlue, fontSize: 10, fontWeight: FontWeight.w700),
                                      ),
                                    ],
                                    const SizedBox(height: 14),
                                    Text(
                                      post['body'] as String? ?? post['summary'] as String? ?? '',
                                      style: const TextStyle(color: StcColors.textBody, fontSize: 12, height: 1.45),
                                    ),
                                    if (isPlaque) ...[
                                      const SizedBox(height: 16),
                                      StcSecondaryButton(
                                        label: 'Compartir por WhatsApp',
                                        onPressed: () => _sharePlaque(whatsapp: true),
                                      ),
                                      const SizedBox(height: 8),
                                      StcSecondaryButton(
                                        label: 'Compartir por correo',
                                        onPressed: () => _sharePlaque(whatsapp: false),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            ],
                          ),
              ),
            ],
          ),
        ),
      ),
    ),
    );
  }
}
