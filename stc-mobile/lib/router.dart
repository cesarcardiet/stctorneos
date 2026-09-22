import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import 'features/auth/presentation/auth_controller.dart';
import 'features/auth/presentation/email_verification_screen.dart';
import 'features/auth/presentation/forgot_password_screen.dart';
import 'features/auth/presentation/forgot_password_sent_screen.dart';
import 'features/auth/presentation/invitation_register_screen.dart';
import 'features/auth/presentation/landing_screen.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/auth/presentation/pending_account_screen.dart';
import 'features/auth/presentation/register_choose_type_screen.dart';
import 'features/auth/presentation/register_public_screen.dart';
import 'features/auth/presentation/reset_password_screen.dart';
import 'features/auth/presentation/splash_screen.dart';
import 'features/feed/presentation/content_detail_screen.dart';
import 'features/feed/presentation/content_list_screen.dart';
import 'features/feed/presentation/plaques_list_screen.dart';
import 'features/feed/presentation/favorites_screen.dart';
import 'features/feed/presentation/live_screen.dart';
import 'features/feed/presentation/notifications_screen.dart';
import 'features/home/presentation/home_screen.dart';
import 'features/invitations/presentation/invitation_screen.dart';
import 'features/delegation/presentation/delegation_roster_screen.dart';
import 'features/delegation/presentation/delegation_team_screen.dart';
import 'features/players/presentation/new_player_wizard_screen.dart';
import 'features/profile/presentation/complete_profile_screen.dart';
import 'features/profile/presentation/player_portal_screen.dart';
import 'features/profile/presentation/profile_screen.dart';
import 'features/profile/presentation/tutor_players_screen.dart';
import 'features/tutor/presentation/tutor_ficha_wizard_screen.dart';
import 'features/profile/presentation/workspace_screen.dart';
import 'features/staff/presentation/staff_match_screen.dart';
import 'features/staff/presentation/staff_matches_screen.dart';
import 'features/tournaments/presentation/brackets_screen.dart';
import 'features/tournaments/presentation/categories_screen.dart';
import 'features/tournaments/presentation/category_hub_screen.dart';
import 'features/tournaments/presentation/fair_play_screen.dart';
import 'features/tournaments/presentation/fixture_screen.dart';
import 'features/tournaments/presentation/match_detail_screen.dart';
import 'features/tournaments/presentation/player_credential_screen.dart';
import 'features/tournaments/presentation/scorers_screen.dart';
import 'features/tournaments/presentation/standings_screen.dart';
import 'features/tournaments/presentation/team_profile_screen.dart';
import 'features/tournaments/presentation/teams_list_screen.dart';
import 'features/tournaments/presentation/tournament_hub_screen.dart';
import 'features/tournaments/presentation/tournaments_screen.dart';

class _AuthRefreshNotifier extends ChangeNotifier {
  _AuthRefreshNotifier(this._ref) {
    _ref.listen<AuthState>(authControllerProvider, (_, __) {
      notifyListeners();
    });
  }

  final Ref _ref;
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _AuthRefreshNotifier(ref);

  return GoRouter(
    initialLocation: '/',
    refreshListenable: refresh,
    redirect: (context, state) {
      final auth = ref.read(authControllerProvider);
      final path = state.uri.path;

      const publicPaths = {
        '/',
        '/landing',
        '/login',
        '/register',
        '/register/public',
        '/register/invitation',
        '/register/staff',
        '/invitation',
        '/forgot-password',
        '/forgot-password/sent',
        '/reset-password',
        '/verify-email',
        '/pending-account',
      };

      const authOnlyPaths = {
        '/notifications',
        '/favorites',
        '/players/new',
        '/tutor/players',
        '/player/portal',
        '/workspace',
        '/staff/matches',
      };

      if ((path.startsWith('/tutor/players') ||
              path == '/player/portal' ||
              path.startsWith('/workspace') ||
              path.startsWith('/staff')) &&
          !auth.isAuthenticated) {
        return '/login';
      }

      final guestAllowed = path == '/home' ||
          path == '/live' ||
          path.startsWith('/tournaments') ||
          path.startsWith('/matches') ||
          path.startsWith('/teams') ||
          path.startsWith('/players') ||
          path.startsWith('/content');

      if (authOnlyPaths.contains(path) && !auth.isAuthenticated) {
        return '/login';
      }

      if (!auth.initialized && path != '/') {
        return '/';
      }

      if (auth.canBrowse && guestAllowed) {
        return null;
      }

      if (auth.isAuthenticated &&
          !auth.profileCompleted &&
          path != '/complete-profile' &&
          path != '/profile' &&
          !publicPaths.contains(path)) {
        return '/complete-profile';
      }

      if (auth.initialized &&
          auth.isAuthenticated &&
          publicPaths.contains(path) &&
          path != '/' &&
          path != '/verify-email' &&
          path != '/complete-profile') {
        return auth.profileCompleted ? '/home' : '/complete-profile';
      }

      if (auth.initialized &&
          !auth.canBrowse &&
          (path == '/home' ||
              path == '/live' ||
              path.startsWith('/tournaments') ||
              path.startsWith('/matches') ||
              path.startsWith('/teams') ||
              path.startsWith('/players') ||
              path.startsWith('/content'))) {
        return '/landing';
      }

      if (auth.guestMode && path == '/profile') {
        return '/login';
      }

      return null;
    },
    routes: [
      GoRoute(path: '/', builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/landing', builder: (_, __) => const LandingScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(path: '/register', builder: (_, __) => const RegisterChooseTypeScreen()),
      GoRoute(path: '/register/public', builder: (_, __) => const RegisterPublicScreen()),
      GoRoute(
        path: '/register/invitation',
        builder: (_, __) => const InvitationRegisterScreen(variant: InvitationRegisterVariant.family),
      ),
      GoRoute(
        path: '/register/staff',
        builder: (_, __) => const InvitationRegisterScreen(variant: InvitationRegisterVariant.staff),
      ),
      GoRoute(path: '/invitation', builder: (_, __) => const InvitationScreen()),
      GoRoute(path: '/forgot-password', builder: (_, __) => const ForgotPasswordScreen()),
      GoRoute(
        path: '/forgot-password/sent',
        builder: (context, state) => ForgotPasswordSentScreen(
          email: state.uri.queryParameters['email'] ?? '',
        ),
      ),
      GoRoute(
        path: '/reset-password',
        builder: (context, state) => ResetPasswordScreen(
          email: state.uri.queryParameters['email'] ?? '',
          token: state.uri.queryParameters['token'] ?? '',
        ),
      ),
      GoRoute(
        path: '/verify-email',
        builder: (context, state) => EmailVerificationScreen(
          email: state.uri.queryParameters['email'] ?? '',
        ),
      ),
      GoRoute(
        path: '/pending-account',
        builder: (context, state) => PendingAccountScreen(
          email: state.uri.queryParameters['email'],
        ),
      ),
      GoRoute(path: '/complete-profile', builder: (_, __) => const CompleteProfileScreen()),
      GoRoute(path: '/home', builder: (_, __) => const HomeScreen()),
      GoRoute(path: '/live', builder: (_, __) => const LiveScreen()),
      GoRoute(path: '/notifications', builder: (_, __) => const NotificationsScreen()),
      GoRoute(path: '/favorites', builder: (_, __) => const FavoritesScreen()),
      GoRoute(
        path: '/content',
        builder: (context, state) => ContentListScreen(
          tournamentId: int.tryParse(state.uri.queryParameters['tournament'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/content/plaques',
        builder: (context, state) => PlaquesListScreen(
          tournamentId: int.tryParse(state.uri.queryParameters['tournament'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/content/:slug',
        builder: (context, state) => ContentDetailScreen(
          slug: state.pathParameters['slug']!,
        ),
      ),
      GoRoute(
        path: '/staff/matches',
        builder: (context, state) => StaffMatchesScreen(
          initialScope: state.uri.queryParameters['scope'] ?? 'today',
        ),
      ),
      GoRoute(
        path: '/staff/matches/:id',
        builder: (context, state) => StaffMatchScreen(
          matchId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(path: '/tournaments', builder: (_, __) => const TournamentsScreen()),
      GoRoute(
        path: '/tournaments/:id/categories/:categoryId',
        builder: (context, state) => CategoryHubScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
          categoryId: int.parse(state.pathParameters['categoryId']!),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id/categories',
        builder: (context, state) => CategoriesScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id/hub',
        builder: (context, state) => TournamentHubScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id',
        redirect: (context, state) => '/tournaments/${state.pathParameters['id']}/categories',
      ),
      GoRoute(
        path: '/tournaments/:id/fixture',
        builder: (context, state) => FixtureScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id/standings',
        builder: (context, state) => StandingsScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id/scorers',
        builder: (context, state) => ScorersScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id/brackets',
        builder: (context, state) => BracketsScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id/fair-play',
        builder: (context, state) => FairPlayScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/matches/:id',
        builder: (context, state) => MatchDetailScreen(
          matchId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/tournaments/:id/teams',
        builder: (context, state) => TeamsListScreen(
          tournamentId: int.parse(state.pathParameters['id']!),
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/teams/:id',
        builder: (context, state) {
          final tournamentParam = state.uri.queryParameters['tournament'];
          return TeamProfileScreen(
            teamId: int.parse(state.pathParameters['id']!),
            tournamentId: tournamentParam != null ? int.tryParse(tournamentParam) : null,
          );
        },
      ),
      GoRoute(
        path: '/players/new',
        builder: (context, state) => NewPlayerWizardScreen(
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
          teamId: int.tryParse(state.uri.queryParameters['team'] ?? ''),
          playerId: int.tryParse(state.uri.queryParameters['player'] ?? ''),
        ),
      ),
      GoRoute(
        path: '/players/:id',
        builder: (context, state) => PlayerCredentialScreen(
          playerId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(path: '/profile', builder: (_, __) => const ProfileScreen()),
      GoRoute(path: '/tutor/players', builder: (_, __) => const TutorPlayersScreen()),
      GoRoute(
        path: '/tutor/players/:id',
        builder: (context, state) => TutorPlayerDetailScreen(
          playerId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/tutor/players/:id/ficha',
        builder: (context, state) => TutorFichaWizardScreen(
          playerId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(path: '/player/portal', builder: (_, __) => const PlayerPortalScreen()),
      GoRoute(path: '/workspace', builder: (_, __) => const WorkspaceScreen()),
      GoRoute(path: '/workspace/teams', builder: (_, __) => const DelegationTeamsListScreen()),
      GoRoute(
        path: '/workspace/teams/:id',
        builder: (context, state) => DelegationTeamScreen(
          teamId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(
        path: '/workspace/teams/:id/roster',
        builder: (context, state) => DelegationRosterScreen(
          teamId: int.parse(state.pathParameters['id']!),
        ),
      ),
      GoRoute(path: '/workspace/roster', builder: (_, __) => const DelegationRosterScreen()),
      GoRoute(
        path: '/workspace/inscriptions',
        builder: (context, state) => DelegationInscriptionsScreen(
          categoryId: int.tryParse(state.uri.queryParameters['category'] ?? ''),
          teamId: int.tryParse(state.uri.queryParameters['team'] ?? ''),
        ),
      ),
    ],
  );
});
