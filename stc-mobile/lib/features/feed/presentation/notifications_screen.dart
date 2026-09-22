import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../shared/theme/stc_theme.dart';
import '../../../shared/widgets/stc_auth_scaffold.dart';
import '../../../shared/widgets/stc_public_widgets.dart';
import '../../../shared/widgets/stc_surface.dart';
import '../../home/presentation/home_screen.dart';

class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends ConsumerState<NotificationsScreen> {
  List<Map<String, dynamic>> _items = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final items = await ref.read(catalogRepositoryProvider).fetchNotifications();
      if (mounted) {
        setState(() {
          _items = items;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _formatDate(String? raw) {
    if (raw == null || raw.isEmpty) return '';
    final dt = DateTime.tryParse(raw)?.toLocal();
    if (dt == null) return '';
    return DateFormat("d MMM · HH:mm", 'es').format(dt);
  }

  @override
  Widget build(BuildContext context) {
    final unread = _items.where((item) => item['read'] != true).length;

    return Scaffold(
      backgroundColor: StcColors.background,
      body: StcPublicBackground(
        child: SafeArea(
          child: Column(
            children: [
              StcPublicHeader(title: 'NOTIFICACIONES', onBack: () => context.pop()),
              Expanded(
                child: _loading
                    ? const Center(child: CircularProgressIndicator(color: StcColors.cyan))
                    : RefreshIndicator(
                        color: StcColors.cyan,
                        onRefresh: _load,
                        child: ListView(
                          padding: const EdgeInsets.fromLTRB(18, 12, 18, 12),
                          children: [
                            Row(
                              children: [
                                const Expanded(
                                  child: Text(
                                    'TUS AVISOS',
                                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 22),
                                  ),
                                ),
                                if (unread > 0)
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: StcColors.primaryBlue,
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: Text(
                                      '$unread nuevas',
                                      style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800),
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 16),
                            if (_items.isEmpty)
                              const StcInfoCard(
                                title: 'Sin notificaciones',
                                body: 'Cuando el torneo publique avisos los verás aquí.',
                              )
                            else
                              ..._items.map((item) {
                                final read = item['read'] == true;
                                return Padding(
                                  padding: const EdgeInsets.only(bottom: 10),
                                  child: StcSurfaceCard(
                                    padding: const EdgeInsets.fromLTRB(15, 13, 15, 13),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                item['title'] as String? ?? 'Aviso',
                                                style: TextStyle(
                                                  color: Colors.white,
                                                  fontWeight: FontWeight.w800,
                                                  fontSize: 12,
                                                  fontStyle: read ? FontStyle.normal : FontStyle.italic,
                                                ),
                                              ),
                                            ),
                                            if (!read)
                                              Container(
                                                width: 8,
                                                height: 8,
                                                decoration: const BoxDecoration(
                                                  color: StcColors.cyan,
                                                  shape: BoxShape.circle,
                                                ),
                                              ),
                                          ],
                                        ),
                                        const SizedBox(height: 6),
                                        Text(
                                          item['body'] as String? ?? '',
                                          style: const TextStyle(color: StcColors.textBody, fontSize: 10, height: 1.35),
                                        ),
                                        const SizedBox(height: 10),
                                        Text(
                                          '${item['channel'] as String? ?? 'App'} · ${_formatDate(item['sent_at'] as String?)}',
                                          style: const TextStyle(color: StcColors.cyan, fontSize: 9, fontWeight: FontWeight.w600),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              }),
                          ],
                        ),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
