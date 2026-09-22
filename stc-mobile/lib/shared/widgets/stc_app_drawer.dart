import 'package:flutter/material.dart';

import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:go_router/go_router.dart';



import '../../features/auth/presentation/auth_controller.dart';

import '../navigation/stc_navigation.dart';

import '../theme/stc_theme.dart';



class StcMenuContext {

  const StcMenuContext({

    this.tournamentId,

    this.tournamentName,

    this.categoryId,

    this.categoryName,

  });



  final int? tournamentId;

  final String? tournamentName;

  final int? categoryId;

  final String? categoryName;



  bool get hasTournament => tournamentId != null;

  bool get hasCategory => categoryId != null;

}



class StcAppDrawer extends ConsumerWidget {

  const StcAppDrawer({super.key, this.context = const StcMenuContext()});



  final StcMenuContext context;



  @override

  Widget build(BuildContext widgetContext, WidgetRef ref) {

    final auth = ref.watch(authControllerProvider);

    final ctx = context;



    return Drawer(

      backgroundColor: const Color(0xFF071428),

      child: SafeArea(

        child: ListView(

          padding: const EdgeInsets.fromLTRB(12, 8, 12, 24),

          children: [

            Padding(

              padding: const EdgeInsets.fromLTRB(8, 8, 8, 16),

              child: Row(

                children: [

                  Image.asset('assets/images/stc_logo.png', height: 36, errorBuilder: (_, __, ___) => const Icon(Icons.shield, color: StcColors.cyan)),

                  const SizedBox(width: 10),

                  const Expanded(

                    child: Text('STC TORNEOS', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 14)),

                  ),

                ],

              ),

            ),

            if (ctx.hasCategory) ...[

              _item('Torneos', 'Listado', Icons.emoji_events_outlined, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/tournaments');

              }),

              _item('Categorías', ctx.tournamentName ?? 'Torneo', Icons.category_outlined, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/tournaments/${ctx.tournamentId}/categories');

              }),

              if (ctx.categoryName != null)

                _contextTile(ctx.categoryName!, subtitle: ctx.tournamentName),

              _section('CATEGORÍA'),

              _item('Equipos', 'Planteles', Icons.groups_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenCategorySection(widgetContext, ctx.tournamentId!, ctx.categoryId!, 'teams');

              }),

              _section('OPERAR'),

              _item('Clasificación', 'Tablas', Icons.leaderboard_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenCategorySection(widgetContext, ctx.tournamentId!, ctx.categoryId!, 'standings');

              }),

              _item('Fixture', 'Partidos', Icons.calendar_month_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenCategorySection(widgetContext, ctx.tournamentId!, ctx.categoryId!, 'fixture');

              }),

              _item('Goleadores', 'Ranking', Icons.sports_soccer_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenCategorySection(widgetContext, ctx.tournamentId!, ctx.categoryId!, 'scorers');

              }),

              _item('Fair Play', 'Disciplina', Icons.gavel_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenCategorySection(widgetContext, ctx.tournamentId!, ctx.categoryId!, 'fair-play');

              }),

              _item('Eliminatorias', 'Cruces', Icons.account_tree_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenCategorySection(widgetContext, ctx.tournamentId!, ctx.categoryId!, 'brackets');

              }),

            ] else if (ctx.hasTournament) ...[

              _item('← Torneos', 'Volver al listado', Icons.arrow_back, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/tournaments');

              }),

              if (ctx.tournamentName != null) _contextTile(ctx.tournamentName!, subtitle: 'Torneo'),

              _section('TORNEO'),

              _item('Categorías', 'Elegí una', Icons.category_outlined, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/tournaments/${ctx.tournamentId}/categories');

              }),

              _item('Hub del torneo', 'Resumen', Icons.emoji_events_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenTournamentHub(widgetContext, ctx.tournamentId!);

              }),

              _item('Novedades', 'Comunicados', Icons.newspaper_outlined, () {

                Navigator.pop(widgetContext);

                stcOpenContentList(widgetContext, tournamentId: ctx.tournamentId);

              }),

            ] else ...[

              _section('TORNEOS'),

              _item('Elegir torneo', 'Listado completo', Icons.emoji_events_outlined, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/tournaments');

              }),

            ],

            _section('EXPLORAR'),

            _item('En vivo', 'Partidos ahora', Icons.sensors, () {

              Navigator.pop(widgetContext);

              widgetContext.push('/live');

            }),

            _item('Novedades', 'Comunicados y placas', Icons.article_outlined, () {

              Navigator.pop(widgetContext);

              stcOpenContentList(widgetContext, tournamentId: ctx.tournamentId);

            }),

            _item('Placas oficiales', 'Resultados', Icons.photo_outlined, () {

              Navigator.pop(widgetContext);

              widgetContext.push('/content/plaques${ctx.tournamentId != null ? '?tournament=${ctx.tournamentId}' : ''}');

            }),

            if (auth.isAuthenticated) ...[

              _section('MI CUENTA'),

              _item('Perfil', 'Datos y accesos', Icons.person_outline, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/profile');

              }),

              if (auth.user!.navigation.any((n) => (n['key'] as String?) == 'workspace'))

                _item('Mi delegación', 'Equipos y fichas', Icons.badge_outlined, () {

                  Navigator.pop(widgetContext);

                  widgetContext.push('/workspace');

                }),

              _item('Favoritos', 'Equipos y partidos', Icons.favorite_border, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/favorites');

              }),

              _item('Notificaciones', 'Avisos', Icons.notifications_outlined, () {

                Navigator.pop(widgetContext);

                widgetContext.push('/notifications');

              }),

            ] else ...[

              _section('CUENTA'),

              _item('Ingresar', 'Accedé a tu rol', Icons.login, () {

                Navigator.pop(widgetContext);

                widgetContext.go('/login');

              }),

            ],

          ],

        ),

      ),

    );

  }



  Widget _section(String title) {

    return Padding(

      padding: const EdgeInsets.fromLTRB(8, 12, 8, 6),

      child: Text(title, style: const TextStyle(color: StcColors.cyan, fontWeight: FontWeight.w800, fontSize: 10, letterSpacing: 0.8)),

    );

  }



  Widget _contextTile(String title, {String? subtitle}) {

    return ListTile(

      dense: true,

      title: Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),

      subtitle: subtitle != null ? Text(subtitle, style: const TextStyle(color: StcColors.textMuted, fontSize: 10)) : null,

    );

  }



  Widget _item(String title, String subtitle, IconData icon, VoidCallback onTap) {

    return ListTile(

      leading: Icon(icon, color: StcColors.primaryBlue, size: 22),

      title: Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),

      subtitle: Text(subtitle, style: const TextStyle(color: StcColors.textMuted, fontSize: 10)),

      onTap: onTap,

      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),

    );

  }

}


