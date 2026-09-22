import 'package:flutter/material.dart';

import 'package:flutter/services.dart';

import 'package:go_router/go_router.dart';



void stcGoBack(BuildContext context, {String fallback = '/home'}) {

  if (context.canPop()) {

    context.pop();

  } else {

    context.go(fallback);

  }

}



/// Intercepta el botón atrás del teléfono: vuelve atrás en la app sin cerrarla.

class StcBackScope extends StatelessWidget {

  const StcBackScope({

    super.key,

    required this.child,

    this.fallback = '/home',

  });



  final Widget child;

  final String fallback;



  @override

  Widget build(BuildContext context) {

    return PopScope(

      canPop: false,

      onPopInvokedWithResult: (didPop, result) {

        if (didPop) return;

        stcGoBack(context, fallback: fallback);

      },

      child: child,

    );

  }

}



/// En Inicio: atrás pide confirmación; doble toque en 2 s cierra la app.

class StcHomeBackScope extends StatefulWidget {

  const StcHomeBackScope({super.key, required this.child});



  final Widget child;



  @override

  State<StcHomeBackScope> createState() => _StcHomeBackScopeState();

}



class _StcHomeBackScopeState extends State<StcHomeBackScope> {

  DateTime? _lastBackPress;



  @override

  Widget build(BuildContext context) {

    return PopScope(

      canPop: false,

      onPopInvokedWithResult: (didPop, result) {

        if (didPop) return;

        if (context.canPop()) {

          context.pop();

          return;

        }

        final now = DateTime.now();

        if (_lastBackPress != null &&

            now.difference(_lastBackPress!) < const Duration(seconds: 2)) {

          SystemNavigator.pop();

          return;

        }

        _lastBackPress = now;

        ScaffoldMessenger.of(context).showSnackBar(

          const SnackBar(

            content: Text('Presioná atrás otra vez para salir'),

            duration: Duration(seconds: 2),

          ),

        );

      },

      child: widget.child,

    );

  }

}



void stcOpenContentList(BuildContext context, {int? tournamentId}) {

  final query = tournamentId != null ? '?tournament=$tournamentId' : '';

  context.push('/content$query');

}



void stcOpenContentDetail(BuildContext context, String slug) {

  context.push('/content/$slug');

}



void stcOpenMatch(BuildContext context, int matchId) {

  context.push('/matches/$matchId');

}



void stcOpenTeam(BuildContext context, int teamId, {int? tournamentId}) {

  final query = tournamentId != null ? '?tournament=$tournamentId' : '';

  context.push('/teams/$teamId$query');

}



void stcOpenPlayer(BuildContext context, int playerId) {

  context.push('/players/$playerId');

}



void stcOpenTournament(BuildContext context, int tournamentId) {
  context.push('/tournaments/$tournamentId/categories');
}

void stcOpenTournamentSection(BuildContext context, int tournamentId, String section) {
  context.push('/tournaments/$tournamentId/$section');
}

void stcOpenCategorySection(
  BuildContext context,
  int tournamentId,
  int categoryId,
  String section,
) {
  context.push('/tournaments/$tournamentId/$section?category=$categoryId');
}

void stcOpenCategoryHub(BuildContext context, int tournamentId, int categoryId) {
  context.push('/tournaments/$tournamentId/categories/$categoryId');
}

void stcOpenTournamentHub(BuildContext context, int tournamentId) {
  context.push('/tournaments/$tournamentId/hub');
}

String stcCategoryQuery(int? categoryId) =>
    categoryId != null ? '?category=$categoryId' : '';

