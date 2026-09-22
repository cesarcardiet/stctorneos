import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'router.dart';
import 'shared/theme/stc_theme.dart';

class StcApp extends ConsumerWidget {
  const StcApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);

    return MaterialApp.router(
      title: 'STC Torneos',
      debugShowCheckedModeBanner: false,
      theme: StcTheme.dark(),
      routerConfig: router,
    );
  }
}
