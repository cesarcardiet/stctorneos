import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../../core/storage/session_store.dart';
import '../data/auth_repository.dart';
import '../domain/profile_completion.dart';
import '../domain/user_profile.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    client: ref.watch(dioClientProvider),
    tokenStore: ref.watch(tokenStoreProvider),
  );
});

class AuthState {
  const AuthState({
    this.user,
    this.loading = false,
    this.initialized = false,
    this.guestMode = false,
    this.profileCompleted = false,
    this.error,
  });

  final UserProfile? user;
  final bool loading;
  final bool initialized;
  final bool guestMode;
  final bool profileCompleted;
  final String? error;

  bool get isAuthenticated => user != null;
  bool get canBrowse => isAuthenticated || guestMode;

  AuthState copyWith({
    UserProfile? user,
    bool? loading,
    bool? initialized,
    bool? guestMode,
    bool? profileCompleted,
    String? error,
    bool clearUser = false,
    bool clearError = false,
  }) {
    return AuthState(
      user: clearUser ? null : user ?? this.user,
      loading: loading ?? this.loading,
      initialized: initialized ?? this.initialized,
      guestMode: guestMode ?? this.guestMode,
      profileCompleted: profileCompleted ?? this.profileCompleted,
      error: clearError ? null : error ?? this.error,
    );
  }
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._repository, this._sessionStore) : super(const AuthState());

  final AuthRepository _repository;
  final SessionStore _sessionStore;

  Future<bool> _resolveProfileCompleted(UserProfile user) async {
    if (await _sessionStore.isProfileCompletedFor(user.email)) {
      return true;
    }
    if (!profileNeedsCompletion(user)) {
      await _sessionStore.setProfileCompletedFor(user.email, true);
      return true;
    }
    return false;
  }

  Future<void> bootstrap() async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final guestMode = await _sessionStore.isGuestMode();
      final user = guestMode ? null : await _repository.restoreSession();
      final profileCompleted = user != null ? await _resolveProfileCompleted(user) : false;
      state = state.copyWith(
        user: user,
        guestMode: guestMode && user == null,
        profileCompleted: profileCompleted,
        loading: false,
        initialized: true,
        clearUser: user == null,
      );
    } catch (error) {
      state = state.copyWith(
        loading: false,
        initialized: true,
        clearUser: true,
        error: error.toString(),
      );
    }
  }

  Future<void> enterGuestMode() async {
    await _repository.logout();
    await _sessionStore.setGuestMode(true);
    state = state.copyWith(clearUser: true, guestMode: true, clearError: true);
  }

  Future<void> exitGuestMode() async {
    await _sessionStore.setGuestMode(false);
    state = state.copyWith(guestMode: false);
  }

  Future<void> login(
    String email,
    String password, {
    bool rememberMe = true,
  }) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      await _sessionStore.setGuestMode(false);
      final session = await _repository.login(email: email, password: password);
      await _sessionStore.setRememberMe(enabled: rememberMe, email: email);
      final profileCompleted = await _resolveProfileCompleted(session.user);
      state = state.copyWith(
        user: session.user,
        guestMode: false,
        profileCompleted: profileCompleted,
        loading: false,
      );
    } catch (error) {
      state = state.copyWith(loading: false, error: error.toString());
      rethrow;
    }
  }

  Future<void> logout() async {
    await _repository.logout();
    await _sessionStore.clearSessionFlags();
    state = state.copyWith(clearUser: true, guestMode: false, profileCompleted: false, clearError: true);
  }

  Future<({bool enabled, String? email})> rememberMeSettings() {
    return _sessionStore.rememberMeSettings();
  }

  Future<InvitationPreview> previewInvitation(String email, String code) {
    return _repository.previewInvitation(
      email: email,
      invitationCode: code,
    );
  }

  Future<void> acceptInvitation({
    required String email,
    required String code,
    required String password,
    String? name,
  }) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      await _sessionStore.setGuestMode(false);
      final session = await _repository.acceptInvitation(
        email: email,
        invitationCode: code,
        password: password,
        name: name,
      );
      final profileCompleted = await _resolveProfileCompleted(session.user);
      state = state.copyWith(
        user: session.user,
        guestMode: false,
        profileCompleted: profileCompleted,
        loading: false,
      );
    } catch (error) {
      state = state.copyWith(loading: false, error: error.toString());
      rethrow;
    }
  }

  Future<void> registerSpectator({
    required String name,
    required String email,
    required String password,
  }) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      await _sessionStore.setGuestMode(false);
      final session = await _repository.registerSpectator(
        name: name,
        email: email,
        password: password,
      );
      final profileCompleted = await _resolveProfileCompleted(session.user);
      state = state.copyWith(
        user: session.user,
        guestMode: false,
        profileCompleted: profileCompleted,
        loading: false,
      );
    } catch (error) {
      state = state.copyWith(loading: false, error: error.toString());
      rethrow;
    }
  }

  Future<void> updateProfile({String? name, String? phone}) async {
    state = state.copyWith(loading: true, clearError: true);
    try {
      final user = await _repository.updateProfile(name: name, phone: phone);
      await _sessionStore.setProfileCompletedFor(user.email, true);
      state = state.copyWith(user: user, profileCompleted: true, loading: false);
    } catch (error) {
      state = state.copyWith(loading: false, error: error.toString());
      rethrow;
    }
  }

  Future<void> markProfileCompleted() async {
    final email = state.user?.email;
    if (email != null) {
      await _sessionStore.setProfileCompletedFor(email, true);
    }
    state = state.copyWith(profileCompleted: true);
  }

  Future<void> refreshUser() async {
    final user = await _repository.me();
    state = state.copyWith(user: user);
  }

  Future<void> forgotPassword(String email) async {
    await _repository.forgotPassword(email);
  }

  Future<void> resetPassword({
    required String email,
    required String token,
    required String password,
  }) async {
    await _repository.resetPassword(email: email, token: token, password: password);
  }
}

final authControllerProvider =
    StateNotifierProvider<AuthController, AuthState>((ref) {
  return AuthController(
    ref.watch(authRepositoryProvider),
    ref.watch(sessionStoreProvider),
  );
});
