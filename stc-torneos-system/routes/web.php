<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\DelegationController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\FieldController;
use App\Http\Controllers\Admin\FixtureController;
use App\Http\Controllers\Admin\MatchSheetController;
use App\Http\Controllers\Admin\PlayerController;
use App\Http\Controllers\Admin\RosterReviewController;
use App\Http\Controllers\Admin\ResultController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\TeamController;
use App\Http\Controllers\Admin\TournamentController;
use App\Http\Controllers\Auth\AccessController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\GuardianFichaController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\RosterShareController;
use App\Http\Controllers\Workspace\ActionController as WorkspaceActionController;
use App\Http\Controllers\Workspace\CategoryController as WorkspaceCategoryController;
use App\Http\Controllers\Workspace\CategoryReportController as WorkspaceCategoryReportController;
use App\Http\Controllers\Workspace\GateController as WorkspaceGateController;
use App\Http\Controllers\Workspace\PlayerPortalController;
use App\Models\User;
use App\Support\DemoAccountLogin;
use App\Support\PlayerCredentials;
use App\Support\TutorCredentials;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('workspace.home');
    }

    return redirect()->route('login');
});

Route::get('/login', function () {
    if (Auth::check()) {
        return redirect()->route('workspace.home');
    }

    return view('auth.login');
})->name('login');

Route::post('/login', function (Request $request) {
    $validated = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required', 'string'],
    ]);

    $email = DemoAccountLogin::normalizeEmail((string) $validated['email']);
    $password = DemoAccountLogin::normalizePassword((string) $validated['password']);

    $authenticated = false;

    if (DemoAccountLogin::isDemoEmail($email)) {
        $demoLogin = DemoAccountLogin::resolve($email, $password);
        if ($demoLogin) {
            Auth::login($demoLogin['user'], $request->boolean('remember'));
            $authenticated = true;
        }
    }

    if (! $authenticated) {
        $authenticated = Auth::attempt([
            'email' => $email,
            'password' => $password,
        ], $request->boolean('remember'));
    }

    if (! $authenticated) {
        $user = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();

        if ($user
            && $user->status === 'active'
            && Hash::check($password, $user->getAuthPassword())) {
            Auth::login($user, $request->boolean('remember'));
            $authenticated = true;
        }
    }

    if (! $authenticated) {
        $message = 'Las credenciales no coinciden. Revisá correo y clave.';

        if ($email === 'tutor@stctorneos.demo' && $password === 'stcdemo') {
            $message = 'El tutor no usa stcdemo. Probá con la clave '.TutorCredentials::defaultPassword().'.';
        } elseif ($email === 'tutor@stctorneos.demo') {
            $message = 'Las credenciales no coinciden. Para el tutor demo usá '.TutorCredentials::defaultPassword().'.';
        } elseif ($email === 'jugador@stctorneos.demo' && $password === TutorCredentials::defaultPassword()) {
            $message = 'El jugador demo no usa stctutor. Probá con la clave stcdemo.';
        } elseif ($email === 'jugador@stctorneos.demo' && $password === PlayerCredentials::defaultPassword()) {
            $message = 'El jugador demo no usa stcjugador. Probá con la clave stcdemo.';
        } else {
            $existing = User::query()->whereRaw('LOWER(TRIM(email)) = ?', [$email])->first();
            if ($existing?->isPlayerAccount() && ! DemoAccountLogin::isDemoEmail($email)) {
                if ($password === 'stcdemo') {
                    $message = 'Este jugador no usa stcdemo. Probá con la clave '.PlayerCredentials::defaultPassword().'.';
                } else {
                    $message = 'Las credenciales no coinciden. Jugadores de ficha: clave inicial '.PlayerCredentials::defaultPassword().'.';
                }
            }
        }

        return back()
            ->withErrors(['email' => $message])
            ->onlyInput('email');
    }

    $authenticated = $request->user();
    if ($authenticated->status !== 'active') {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = match ($authenticated->status) {
            'pending' => 'Tu acceso todavía está pendiente de aprobación.',
            'suspended' => 'Tu acceso está suspendido. Consultá con un administrador.',
            'revoked' => 'Tu acceso fue revocado. Consultá con un administrador.',
            default => 'Tu usuario no tiene acceso activo al Admin Web.',
        };

        return back()->withErrors(['email' => $message])->onlyInput('email');
    }

    $request->session()->regenerate();
    $request->user()->forceFill(['last_login_at' => now()])->save();

    if ($request->user()->isPlayerAccount()) {
        return redirect()->route('workspace.player.home');
    }

    if ($request->user()->isTutorAccount()) {
        return redirect()->route('workspace.tutor.home');
    }

    if ($request->user()->restrictsToAssignedClub()) {
        return redirect()->route('workspace.home');
    }

    return redirect()->route('workspace.home');
})->name('login.perform');

Route::get('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout.demo');

Route::get('/registro', [AccessController::class, 'showRegister'])->name('register');
Route::post('/registro', [AccessController::class, 'register'])->name('register.perform');

Route::get('/invitacion/{token}', [InvitationController::class, 'show'])->name('invitations.show');
Route::post('/invitacion/{token}', [InvitationController::class, 'accept'])->name('invitations.accept');

Route::get('/recuperar-clave', [AccessController::class, 'showForgotPassword'])->name('password.request');
Route::post('/recuperar-clave', [AccessController::class, 'sendForgotPassword'])->name('password.email');
Route::get('/restablecer-clave/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
Route::post('/restablecer-clave', [PasswordResetController::class, 'update'])->name('password.update');

Route::get('/ficha/{token}', [GuardianFichaController::class, 'show'])->name('ficha.show');
Route::post('/ficha/{token}', [GuardianFichaController::class, 'update'])->name('ficha.update');
Route::post('/ficha/{token}/verificar-email', [GuardianFichaController::class, 'checkEmail'])->name('ficha.check-email');

Route::get('/plantel/{token}', [RosterShareController::class, 'show'])->name('plantel.show');
Route::post('/plantel/{token}', [RosterShareController::class, 'store'])->name('plantel.store');

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'admin.web', 'permission:dashboard.view'])->name('dashboard');

Route::middleware(['auth', 'permission:dashboard.view'])->prefix('operacion')->name('workspace.')->group(function () {
    Route::get('/salud', function () {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'env' => app()->environment(),
            'time' => now()->toIso8601String(),
        ]);
    })->name('health');
    Route::get('/cuenta', [WorkspaceGateController::class, 'account'])->name('account');
    Route::patch('/cuenta/clave', [WorkspaceGateController::class, 'updatePassword'])->name('account.password');

    Route::middleware('tutor.account')->prefix('mis-jugadores')->name('tutor.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Workspace\TutorPortalController::class, 'home'])->name('home');
        Route::get('/{player}/ficha', [\App\Http\Controllers\Workspace\TutorPortalController::class, 'ficha'])->name('ficha');
        Route::post('/{player}/ficha', [\App\Http\Controllers\Workspace\TutorPortalController::class, 'updateFicha'])->name('ficha.update');
    });

    Route::middleware('player.account')->prefix('mi-ficha')->name('player.')->group(function () {
        Route::get('/', [PlayerPortalController::class, 'home'])->name('home');
        Route::get('/estadisticas', [PlayerPortalController::class, 'stats'])->name('stats');
        Route::get('/datos', [PlayerPortalController::class, 'ficha'])->name('ficha');
        Route::get('/documentos', [PlayerPortalController::class, 'documents'])->name('documents');
        Route::get('/descargar', [PlayerPortalController::class, 'export'])->name('export');
        Route::get('/documentos/{document}/descargar', [PlayerPortalController::class, 'downloadDocument'])->name('documents.download');
        Route::get('/credencial', [PlayerPortalController::class, 'credential'])->name('credential');
    });

    Route::middleware('staff.workspace')->group(function () {
    Route::get('/', [WorkspaceGateController::class, 'home'])->name('home');
    Route::post('/torneos', [WorkspaceGateController::class, 'storeTournament'])->name('tournaments.store');
    Route::get('/mi-club', [WorkspaceGateController::class, 'myClub'])->name('my-club');
    Route::get('/torneos/{tournament}', [WorkspaceGateController::class, 'tournament'])->name('tournaments.show');
    Route::post('/torneos/{tournament}/categorias', [WorkspaceGateController::class, 'storeCategory'])->name('tournaments.categories.store');
    Route::patch('/torneos/{tournament}/categorias/orden', [WorkspaceGateController::class, 'reorderCategories'])->name('tournaments.categories.reorder');
    Route::patch('/torneos/{tournament}/categorias/{category}', [WorkspaceGateController::class, 'updateCategory'])->name('tournaments.categories.update');
    Route::delete('/torneos/{tournament}/categorias/{category}', [WorkspaceGateController::class, 'destroyCategory'])->name('tournaments.categories.destroy');
    Route::get('/delegaciones/escudo-conocido', [WorkspaceGateController::class, 'lookupKnownClubLogo'])->name('clubs.known-logo');
    Route::get('/torneos/{tournament}/delegaciones', [WorkspaceGateController::class, 'clubs'])->name('tournaments.clubs');
    Route::post('/torneos/{tournament}/delegaciones', [WorkspaceActionController::class, 'addTournamentClub'])->name('tournaments.clubs.store');
    Route::get('/torneos/{tournament}/delegaciones/{delegation}', [WorkspaceGateController::class, 'club'])->name('tournaments.clubs.show');
    Route::patch('/torneos/{tournament}/delegaciones/{delegation}', [WorkspaceActionController::class, 'updateTournamentClub'])->name('tournaments.clubs.update');
    Route::patch('/torneos/{tournament}/delegaciones/{delegation}/escudo', [WorkspaceActionController::class, 'updateTournamentClubShield'])->name('tournaments.clubs.shield');
    Route::post('/torneos/{tournament}/delegaciones/{delegation}/delegado', [WorkspaceActionController::class, 'assignTournamentClubDelegate'])->name('tournaments.clubs.delegate');
    Route::delete('/torneos/{tournament}/delegaciones/{delegation}', [WorkspaceActionController::class, 'destroyTournamentClub'])->name('tournaments.clubs.destroy');
    Route::post('/torneos/{tournament}/equipos', [WorkspaceActionController::class, 'addTournamentTeam'])->name('tournaments.teams.store');
    Route::delete('/torneos/{tournament}', [WorkspaceGateController::class, 'destroyTournament'])->name('tournaments.destroy');
    Route::patch('/torneos/{tournament}/inscripciones', [WorkspaceGateController::class, 'toggleRegistrations'])->name('tournaments.registrations');
    Route::patch('/torneos/{tournament}/imagen', [WorkspaceActionController::class, 'updateTournamentBanner'])->name('tournaments.banner');
    Route::get('/categorias', [WorkspaceGateController::class, 'directory'])->name('categories.directory');
    Route::get('/categorias/{category}', [WorkspaceCategoryController::class, 'home'])->name('categories.home');
    Route::get('/categorias/{category}/clasificacion', [WorkspaceCategoryController::class, 'standings'])->name('categories.standings');
    Route::get('/categorias/{category}/clasificacion/imprimir', [WorkspaceCategoryController::class, 'printStandings'])->name('categories.standings.print');
    Route::get('/categorias/{category}/cruces', [WorkspaceCategoryController::class, 'brackets'])->name('categories.brackets');
    Route::get('/categorias/{category}/fair-play', [WorkspaceCategoryController::class, 'fairPlay'])->name('categories.fairplay');
    Route::get('/categorias/{category}/fixture', [WorkspaceCategoryController::class, 'fixture'])->name('categories.fixture');
    Route::get('/categorias/{category}/rankings', [WorkspaceCategoryController::class, 'rankings'])->name('categories.rankings');
    Route::get('/categorias/{category}/media', [WorkspaceCategoryController::class, 'media'])->name('categories.media');
    Route::get('/categorias/{category}/configuracion', [WorkspaceCategoryController::class, 'settings'])->name('categories.settings');
    Route::get('/categorias/{category}/reportes/equipos', [WorkspaceCategoryReportController::class, 'teams'])->name('categories.reports.teams');
    Route::get('/categorias/{category}/reportes/jugadores', [WorkspaceCategoryReportController::class, 'players'])->name('categories.reports.players');
    Route::get('/categorias/{category}/reportes/carnet', [WorkspaceCategoryReportController::class, 'credentials'])->name('categories.reports.credentials');
    Route::get('/categorias/{category}/reportes/acta', [WorkspaceCategoryReportController::class, 'acta'])->name('categories.reports.acta');
    Route::get('/categorias/{category}/equipos', [WorkspaceCategoryController::class, 'teams'])->name('categories.teams');
    Route::get('/categorias/{category}/equipos/{team}', [WorkspaceCategoryController::class, 'team'])->name('categories.teams.show');
    Route::get('/categorias/{category}/jugadores', [WorkspaceCategoryController::class, 'players'])->name('categories.players');
    Route::get('/categorias/{category}/jugadores/{player}', [WorkspaceCategoryController::class, 'player'])->name('categories.players.show');
    Route::get('/categorias/{category}/jugadores/{player}/editar', [WorkspaceCategoryController::class, 'playerEdit'])->name('categories.players.edit');
    Route::get('/categorias/{category}/partidos/{match}', [WorkspaceCategoryController::class, 'match'])->name('categories.matches.show');
    Route::get('/categorias/{category}/partidos/{match}/planilla', [WorkspaceCategoryController::class, 'matchPlanilla'])->name('categories.matches.planilla');
    Route::get('/categorias/{category}/planillas', [WorkspaceCategoryController::class, 'planillasHub'])->name('categories.planillas');
    Route::get('/categorias/{category}/planillas/descargar', [WorkspaceCategoryController::class, 'planillasDownload'])->name('categories.planillas.download');
    Route::get('/categorias/{category}/documentacion', [WorkspaceCategoryController::class, 'documents'])->name('categories.documents');
    Route::get('/categorias/{category}/inscripciones', [WorkspaceCategoryController::class, 'inscriptions'])->name('categories.inscriptions');
    Route::post('/categorias/{category}/inscripciones/{player}/revisar', [WorkspaceActionController::class, 'reviewInscription'])->name('categories.inscriptions.review');
    Route::get('/categorias/{category}/personas', [WorkspaceCategoryController::class, 'people'])->name('categories.people');
    Route::get('/categorias/{category}/historial', [WorkspaceCategoryController::class, 'history'])->name('categories.history');
    Route::get('/categorias/{category}/delegaciones', [WorkspaceCategoryController::class, 'clubs'])->name('categories.clubs');
    Route::get('/categorias/{category}/delegaciones/{delegation}', [WorkspaceCategoryController::class, 'club'])->name('categories.clubs.show');
    Route::post('/categorias/{category}/clubes', [WorkspaceActionController::class, 'addClub'])->name('categories.clubs.store');
    Route::patch('/categorias/{category}/delegaciones/{delegation}', [WorkspaceActionController::class, 'updateClub'])->name('categories.clubs.update');
    Route::patch('/categorias/{category}/delegaciones/{delegation}/escudo', [WorkspaceActionController::class, 'updateClubShield'])->name('categories.clubs.shield');
    Route::post('/categorias/{category}/delegaciones/{delegation}/delegado', [WorkspaceActionController::class, 'assignClubDelegate'])->name('categories.clubs.delegate');
    Route::delete('/categorias/{category}/delegaciones/{delegation}', [WorkspaceActionController::class, 'destroyClub'])->name('categories.clubs.destroy');
    Route::post('/categorias/{category}/equipos', [WorkspaceActionController::class, 'addTeam'])->name('categories.teams.store');
    Route::patch('/categorias/{category}/equipos/{team}', [WorkspaceActionController::class, 'updateTeam'])->name('categories.teams.update');
    Route::patch('/categorias/{category}/equipos/{team}/escudo', [WorkspaceActionController::class, 'updateTeamShield'])->name('categories.teams.shield');
    Route::delete('/categorias/{category}/equipos/{team}', [WorkspaceActionController::class, 'destroyTeam'])->name('categories.teams.destroy');
    Route::post('/categorias/{category}/equipos/{team}/staff', [WorkspaceActionController::class, 'addStaff'])->name('categories.teams.staff');
    Route::post('/categorias/{category}/equipos/{team}/enlace-plantel', [WorkspaceActionController::class, 'generateRosterLink'])->name('categories.teams.roster-link');
    Route::post('/categorias/{category}/equipos/{team}/enlace-plantel/regenerar', [WorkspaceActionController::class, 'regenerateRosterLink'])->name('categories.teams.roster-link.regenerate');
    Route::patch('/categorias/{category}/equipos/{team}/enlace-plantel/invalidar', [WorkspaceActionController::class, 'invalidateRosterLink'])->name('categories.teams.roster-link.invalidate');
    Route::post('/categorias/{category}/jugadores', [WorkspaceActionController::class, 'addPlayer'])->name('categories.players.store');
    Route::patch('/categorias/{category}/jugadores/{player}', [WorkspaceActionController::class, 'updatePlayer'])->name('categories.players.update');
    Route::post('/categorias/{category}/jugadores/{player}/invitar-tutor', [WorkspaceActionController::class, 'inviteGuardian'])->name('categories.players.invite');
    Route::post('/categorias/{category}/jugadores/{player}/invitar-tutor/regenerar', [WorkspaceActionController::class, 'regenerateGuardianInvite'])->name('categories.players.invite.regenerate');
    Route::patch('/categorias/{category}/jugadores/{player}/invitar-tutor/invalidar', [WorkspaceActionController::class, 'invalidateGuardianInvite'])->name('categories.players.invite.invalidate');
    Route::post('/categorias/{category}/jugadores/{player}/documentos', [WorkspaceActionController::class, 'uploadPlayerDocument'])->name('categories.players.documents');
    Route::delete('/categorias/{category}/jugadores/{player}', [WorkspaceActionController::class, 'destroyPlayer'])->name('categories.players.destroy');
    Route::post('/categorias/{category}/partidos', [WorkspaceActionController::class, 'addMatch'])->name('categories.matches.store');
    Route::post('/categorias/{category}/fixture/generar', [WorkspaceActionController::class, 'generateFixture'])->name('categories.fixture.generate');
    Route::patch('/categorias/{category}/fixture/publicar', [WorkspaceActionController::class, 'publishFixture'])->name('categories.fixture.publish');
    Route::patch('/categorias/{category}/partidos/{match}/publicar', [WorkspaceActionController::class, 'publishMatch'])->name('categories.matches.publish');
    Route::patch('/categorias/{category}/partidos/{match}/observar', [WorkspaceActionController::class, 'observeMatch'])->name('categories.matches.observe');
    Route::delete('/categorias/{category}/partidos/{match}', [WorkspaceActionController::class, 'destroyMatch'])->name('categories.matches.destroy');
    Route::delete('/categorias/{category}/partidos', [WorkspaceActionController::class, 'destroyAllMatches'])->name('categories.matches.destroy-all');
    Route::patch('/categorias/{category}/partidos/{match}/horario', [WorkspaceActionController::class, 'updateMatchSchedule'])->name('categories.matches.schedule');
    Route::patch('/categorias/{category}/partidos/{match}/resultado', [WorkspaceActionController::class, 'saveMatchResult'])->name('categories.matches.result');
    Route::post('/categorias/{category}/partidos/{match}/eventos', [WorkspaceActionController::class, 'addSheetEvent'])->name('categories.matches.events');
    Route::post('/categorias/{category}/partidos/{match}/incidencias', [WorkspaceActionController::class, 'addSheetIncident'])->name('categories.matches.incidents');
    Route::delete('/categorias/{category}/partidos/{match}/incidencias/{incident}', [WorkspaceActionController::class, 'destroySheetIncident'])->name('categories.matches.incidents.destroy');
    Route::post('/categorias/{category}/partidos/{match}/cerrar', [WorkspaceActionController::class, 'closeSheet'])->name('categories.matches.close');
    Route::post('/categorias/{category}/partidos/{match}/reabrir', [WorkspaceActionController::class, 'reopenMatch'])->name('categories.matches.reopen');
    Route::patch('/categorias/{category}/documentos/{document}', [WorkspaceActionController::class, 'reviewDocument'])->name('categories.documents.review');
    Route::post('/categorias/{category}/sitios', [WorkspaceActionController::class, 'addField'])->name('categories.fields.store');
    Route::post('/categorias/{category}/personas', [WorkspaceActionController::class, 'addPerson'])->name('categories.people.store');
    Route::post('/categorias/{category}/media', [WorkspaceActionController::class, 'addMedia'])->name('categories.media.store');
    Route::post('/categorias/{category}/encuestas', [WorkspaceActionController::class, 'storePoll'])->name('categories.polls.store');
    Route::patch('/categorias/{category}/encuestas/{categoryPoll}', [WorkspaceActionController::class, 'updatePoll'])->name('categories.polls.update');
    Route::delete('/categorias/{category}/encuestas/{categoryPoll}', [WorkspaceActionController::class, 'destroyPoll'])->name('categories.polls.destroy');
    Route::post('/categorias/{category}/encuestas/{categoryPoll}/votar', [WorkspaceActionController::class, 'votePoll'])->name('categories.polls.vote');
    Route::post('/categorias/{category}/equipos/grupo', [WorkspaceActionController::class, 'moveTeamGroup'])->name('categories.teams.group');
    Route::post('/categorias/{category}/equipos/orden', [WorkspaceActionController::class, 'reorderTeams'])->name('categories.teams.reorder');
    Route::post('/categorias/{category}/partidos/orden', [WorkspaceActionController::class, 'reorderMatches'])->name('categories.matches.reorder');
    Route::post('/categorias/{category}/grupos/orden', [WorkspaceActionController::class, 'reorderGroups'])->name('categories.groups.reorder');
    Route::post('/categorias/{category}/configuracion', [WorkspaceActionController::class, 'updateSettings'])->name('categories.settings.update');
    Route::patch('/categorias/{category}/imagen', [WorkspaceActionController::class, 'updateCategoryBanner'])->name('categories.banner');
    });
});
Route::get('/admin/buscar/sugerencias', [SearchController::class, 'suggest'])->middleware(['auth', 'admin.web', 'permission:dashboard.view'])->name('admin.search.suggest');
Route::get('/admin/buscar', SearchController::class)->middleware(['auth', 'admin.web', 'permission:dashboard.view'])->name('admin.search');

Route::middleware(['auth', 'admin.web'])->group(function () {
Route::middleware(['auth', 'permission:roles.manage'])->prefix('admin/roles')->name('admin.roles.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('index');
    Route::get('/{role}/editar', [\App\Http\Controllers\Admin\RoleController::class, 'edit'])->name('edit');
    Route::put('/{role}', [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('update');
});

Route::middleware(['auth', 'permission:users.manage'])->prefix('admin/usuarios')->name('admin.users.')->group(function () {
    Route::get('/', [UserController::class, 'index'])->name('index');
    Route::get('/crear', [UserController::class, 'create'])->name('create');
    Route::post('/', [UserController::class, 'store'])->name('store');
    Route::get('/{user}', [UserController::class, 'show'])->name('show');
    Route::get('/{user}/credencial', [UserController::class, 'credential'])->name('credential');
    Route::get('/{user}/editar', [UserController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UserController::class, 'update'])->name('update');
    Route::patch('/{user}/estado', [UserController::class, 'updateStatus'])->name('status');
    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:tournaments.manage'])->prefix('admin/torneos')->name('admin.tournaments.')->group(function () {
    Route::get('/', [TournamentController::class, 'index'])->name('index');
    Route::get('/crear', [TournamentController::class, 'create'])->name('create');
    Route::post('/', [TournamentController::class, 'store'])->name('store');
    Route::get('/{tournament}', [TournamentController::class, 'show'])->name('show');
    Route::get('/{tournament}/editar', [TournamentController::class, 'edit'])->name('edit');
    Route::put('/{tournament}', [TournamentController::class, 'update'])->name('update');
    Route::post('/{tournament}/duplicar', [TournamentController::class, 'duplicate'])->name('duplicate');
    Route::patch('/{tournament}/publicar', [TournamentController::class, 'publish'])->name('publish');
    Route::patch('/{tournament}/finalizar', [TournamentController::class, 'finish'])->name('finish');
    Route::patch('/{tournament}/inscripciones', [TournamentController::class, 'toggleRegistrations'])->name('registrations');
    Route::patch('/{tournament}/archivar', [TournamentController::class, 'archive'])->name('archive');
    Route::delete('/{tournament}', [TournamentController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:tournaments.manage'])->prefix('admin/categorias')->name('admin.categories.')->group(function () {
    Route::get('/', [CategoryController::class, 'index'])->name('index');
    Route::get('/crear', [CategoryController::class, 'create'])->name('create');
    Route::post('/', [CategoryController::class, 'store'])->name('store');
    Route::get('/{category}', [CategoryController::class, 'show'])->name('show');
    Route::get('/{category}/clasificacion', [CategoryController::class, 'competition'])->name('competition');
    Route::get('/{category}/rankings', [CategoryController::class, 'rankings'])->name('rankings');
    Route::get('/{category}/editar', [CategoryController::class, 'edit'])->name('edit');
    Route::patch('/{category}/estado', [CategoryController::class, 'updateStatus'])->name('status');
    Route::patch('/{category}/inscripciones', [CategoryController::class, 'toggleRegistrations'])->name('registrations');
    Route::post('/{category}/duplicar', [CategoryController::class, 'duplicate'])->name('duplicate');
    Route::put('/{category}', [CategoryController::class, 'update'])->name('update');
    Route::delete('/{category}', [CategoryController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:delegations.manage'])->prefix('admin/delegaciones')->name('admin.delegations.')->group(function () {
    Route::get('/', [DelegationController::class, 'index'])->name('index');
    Route::get('/crear', [DelegationController::class, 'create'])->name('create');
    Route::post('/', [DelegationController::class, 'store'])->name('store');
    Route::get('/{delegation}', [DelegationController::class, 'show'])->name('show');
    Route::get('/{delegation}/credencial', [DelegationController::class, 'credential'])->name('credential');
    Route::get('/{delegation}/editar', [DelegationController::class, 'edit'])->name('edit');
    Route::patch('/{delegation}/estado', [DelegationController::class, 'updateStatus'])->name('status');
    Route::put('/{delegation}', [DelegationController::class, 'update'])->name('update');
    Route::delete('/{delegation}', [DelegationController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:delegations.manage'])->prefix('admin/equipos')->name('admin.teams.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('index');
    Route::get('/crear', [TeamController::class, 'create'])->name('create');
    Route::post('/', [TeamController::class, 'store'])->name('store');
    Route::get('/{team}', [TeamController::class, 'show'])->name('show');
    Route::get('/{team}/editar', [TeamController::class, 'edit'])->name('edit');
    Route::get('/{team}/cuerpo-tecnico/crear', [TeamController::class, 'createStaff'])->name('staff.create');
    Route::post('/{team}/cuerpo-tecnico', [TeamController::class, 'storeStaff'])->name('staff.store');
    Route::get('/{team}/cuerpo-tecnico/{staff}/editar', [TeamController::class, 'editStaff'])->name('staff.edit');
    Route::put('/{team}/cuerpo-tecnico/{staff}', [TeamController::class, 'updateStaff'])->name('staff.update');
    Route::patch('/{team}/cuerpo-tecnico/{staff}/retirar', [TeamController::class, 'withdrawStaff'])->name('staff.withdraw');
    Route::get('/{team}/cuerpo-tecnico/{staff}/credencial', [TeamController::class, 'credential'])->name('staff.credential');
    Route::patch('/{team}/estado', [TeamController::class, 'updateStatus'])->name('status');
    Route::put('/{team}', [TeamController::class, 'update'])->name('update');
    Route::delete('/{team}', [TeamController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:players.approve'])->prefix('admin/revision')->name('admin.review.')->group(function () {
    Route::get('/', [RosterReviewController::class, 'index'])->name('index');
    Route::get('/{category}', [RosterReviewController::class, 'category'])->name('category');
    Route::get('/{category}/equipos/{team}', [RosterReviewController::class, 'team'])->name('team');
});

Route::middleware(['auth', 'permission:players.approve'])->prefix('admin/jugadores')->name('admin.players.')->group(function () {
    Route::get('/', [PlayerController::class, 'index'])->name('index');
    Route::get('/crear', [PlayerController::class, 'create'])->name('create');
    Route::get('/exportar', [PlayerController::class, 'export'])->name('export');
    Route::post('/', [PlayerController::class, 'store'])->name('store');
    Route::post('/validar', [PlayerController::class, 'validateRoster'])->name('validate');
    Route::patch('/lista', [PlayerController::class, 'updateRoster'])->name('roster');
    Route::get('/{player}', [PlayerController::class, 'show'])->name('show');
    Route::get('/{player}/credencial', [PlayerController::class, 'credential'])->name('credential');
    Route::post('/{player}/invitar-tutor', [PlayerController::class, 'inviteGuardian'])->name('invite');
    Route::post('/{player}/invitar-tutor/regenerar', [PlayerController::class, 'regenerateInvite'])->name('invite.regenerate');
    Route::patch('/{player}/invitar-tutor/invalidar', [PlayerController::class, 'invalidateInvite'])->name('invite.invalidate');
    Route::get('/{player}/editar', [PlayerController::class, 'edit'])->name('edit');
    Route::patch('/{player}/estado', [PlayerController::class, 'updateStatus'])->name('status');
    Route::patch('/{player}/revision', [PlayerController::class, 'review'])->name('review');
    Route::patch('/{player}/documentos/{document}', [PlayerController::class, 'reviewDocument'])->name('documents.review');
    Route::patch('/{player}/habilitar', [PlayerController::class, 'enable'])->name('enable');
    Route::put('/{player}', [PlayerController::class, 'update'])->name('update');
    Route::delete('/{player}', [PlayerController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:players.approve'])->prefix('admin/documentacion')->name('admin.documents.')->group(function () {
    Route::get('/', [DocumentController::class, 'index'])->name('index');
    Route::get('/exportar', [DocumentController::class, 'export'])->name('export');
    Route::get('/requisitos', [DocumentController::class, 'requirements'])->name('requirements');
    Route::post('/requisitos', [DocumentController::class, 'storeRequirement'])->name('requirements.store');
    Route::post('/requisitos/aplicar', [DocumentController::class, 'applyRequirements'])->name('requirements.apply');
    Route::put('/requisitos/{requirement}', [DocumentController::class, 'updateRequirement'])->name('requirements.update');
    Route::delete('/requisitos/{requirement}', [DocumentController::class, 'destroyRequirement'])->name('requirements.destroy');
    Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
    Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
    Route::patch('/{document}/estado', [DocumentController::class, 'updateStatus'])->name('status');
    Route::patch('/{document}/habilitar', [DocumentController::class, 'enablePlayer'])->name('enable');
});

Route::middleware(['auth', 'permission:tournaments.manage'])->prefix('admin/campos')->name('admin.fields.')->group(function () {
    Route::get('/', [FieldController::class, 'index'])->name('index');
    Route::get('/crear', [FieldController::class, 'create'])->name('create');
    Route::post('/', [FieldController::class, 'store'])->name('store');
    Route::get('/{field}', [FieldController::class, 'show'])->name('show');
    Route::get('/{field}/editar', [FieldController::class, 'edit'])->name('edit');
    Route::put('/{field}', [FieldController::class, 'update'])->name('update');
    Route::patch('/{field}/estado', [FieldController::class, 'updateStatus'])->name('status');
    Route::delete('/{field}', [FieldController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:matches.manage'])->prefix('admin/fixture')->name('admin.fixture.')->group(function () {
    Route::get('/', [FixtureController::class, 'index'])->name('index');
    Route::get('/crear', [FixtureController::class, 'create'])->name('create');
    Route::get('/jornada', [FixtureController::class, 'jornada'])->name('jornada');
    Route::get('/arbitros', [FixtureController::class, 'officialsAgenda'])->name('officials');
    Route::get('/generar', [FixtureController::class, 'generate'])->name('generate');
    Route::post('/generar', [FixtureController::class, 'generateStore'])->name('generate.store');
    Route::patch('/publicar', [FixtureController::class, 'publish'])->name('publish');
    Route::post('/', [FixtureController::class, 'store'])->name('store');
    Route::get('/{match}', [FixtureController::class, 'show'])->name('show');
    Route::get('/{match}/editar', [FixtureController::class, 'edit'])->name('edit');
    Route::put('/{match}', [FixtureController::class, 'update'])->name('update');
    Route::patch('/{match}/estado', [FixtureController::class, 'updateStatus'])->name('status');
    Route::patch('/{match}/publicar', [FixtureController::class, 'publishMatch'])->name('publish.match');
    Route::patch('/{match}/observar', [FixtureController::class, 'observeMatch'])->name('observe');
    Route::post('/{match}/penales', [FixtureController::class, 'storePenalty'])->name('penalties.store');
    Route::delete('/{match}/penales/{kick}', [FixtureController::class, 'destroyPenalty'])->name('penalties.destroy');
    Route::post('/{match}/titulares', [FixtureController::class, 'storeLineup'])->name('lineups.store');
    Route::patch('/{match}/reabrir', [FixtureController::class, 'reopen'])->name('reopen');
    Route::delete('/{match}', [FixtureController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:match_sheets.manage'])->prefix('admin/planillas')->name('admin.sheets.')->group(function () {
    Route::get('/', [MatchSheetController::class, 'index'])->name('index');
    Route::get('/crear', [MatchSheetController::class, 'create'])->name('create');
    Route::post('/', [MatchSheetController::class, 'store'])->name('store');
    Route::get('/{sheet}', [MatchSheetController::class, 'show'])->name('show');
    Route::get('/{sheet}/datos', [MatchSheetController::class, 'datos'])->name('datos');
    Route::put('/{sheet}/datos', [MatchSheetController::class, 'updateDatos'])->name('datos.update');
    Route::get('/{sheet}/eventos', [MatchSheetController::class, 'eventos'])->name('eventos');
    Route::post('/{sheet}/eventos', [MatchSheetController::class, 'storeEvento'])->name('eventos.store');
    Route::delete('/{sheet}/eventos/{event}', [MatchSheetController::class, 'destroyEvento'])->name('eventos.destroy');
    Route::get('/{sheet}/incidencias', [MatchSheetController::class, 'incidencias'])->name('incidencias');
    Route::post('/{sheet}/incidencias', [MatchSheetController::class, 'storeIncidencia'])->name('incidencias.store');
    Route::patch('/{sheet}/firmas/{signature}', [MatchSheetController::class, 'sign'])->name('firmas.sign');
    Route::get('/{sheet}/cierre', [MatchSheetController::class, 'cierre'])->name('cierre');
    Route::post('/{sheet}/publicar', [MatchSheetController::class, 'publish'])->name('publish');
    Route::post('/{sheet}/borrador', [MatchSheetController::class, 'draft'])->name('draft');
    Route::patch('/{sheet}/observar', [MatchSheetController::class, 'observe'])->name('observe');
    Route::get('/{sheet}/placa', [MatchSheetController::class, 'placa'])->name('placa');
    Route::get('/{sheet}/pdf', [MatchSheetController::class, 'pdf'])->name('pdf');
});

Route::middleware(['auth', 'permission:matches.manage,match_sheets.manage'])->prefix('admin/resultados')->name('admin.results.')->group(function () {
    Route::get('/', [ResultController::class, 'index'])->name('index');
    Route::get('/tablas', [ResultController::class, 'standings'])->name('standings');
    Route::get('/cruces', [ResultController::class, 'brackets'])->name('brackets');
    Route::get('/fair-play', [ResultController::class, 'fairplay'])->name('fairplay');
    Route::get('/rankings', [ResultController::class, 'rankings'])->name('rankings');
    Route::get('/sanciones', [ResultController::class, 'sanctions'])->name('sanctions');
    Route::post('/sanciones', [ResultController::class, 'storeSanction'])->name('sanctions.store');
    Route::patch('/sanciones/{sanction}', [ResultController::class, 'updateSanction'])->name('sanctions.update');
    Route::get('/rating', [ResultController::class, 'rating'])->name('rating');
    Route::get('/equipo-fecha', [ResultController::class, 'teamOfRound'])->name('team');
    Route::post('/equipo-fecha', [ResultController::class, 'approveTeamOfRound'])->name('team.approve');
    Route::patch('/equipo-fecha/{selection}', [ResultController::class, 'replaceTeamOfRound'])->name('team.replace');
    Route::patch('/fair-play', [ResultController::class, 'updateFairPlay'])->name('fairplay.update');
    Route::patch('/publicar', [ResultController::class, 'publish'])->name('publish');
    Route::patch('/{match}/publicar', [ResultController::class, 'publishMatch'])->name('publish.match');
    Route::patch('/{match}/observar', [ResultController::class, 'observeMatch'])->name('observe');
});

Route::middleware(['auth', 'permission:communications.manage'])->prefix('admin/comunicaciones')->name('admin.communications.')->group(function () {
    Route::get('/', [CommunicationController::class, 'index'])->name('index');
    Route::get('/contenido/crear', [CommunicationController::class, 'create'])->name('create');
    Route::post('/contenido', [CommunicationController::class, 'store'])->name('store');
    Route::get('/notificaciones', [CommunicationController::class, 'notifications'])->name('notifications');
    Route::get('/notificaciones/crear', [CommunicationController::class, 'createNotification'])->name('notifications.create');
    Route::post('/notificaciones', [CommunicationController::class, 'storeNotification'])->name('notifications.store');
    Route::get('/notificaciones/{appNotification}', [CommunicationController::class, 'showNotification'])->name('notifications.show');
    Route::get('/notificaciones/{appNotification}/editar', [CommunicationController::class, 'editNotification'])->name('notifications.edit');
    Route::put('/notificaciones/{appNotification}', [CommunicationController::class, 'updateNotification'])->name('notifications.update');
    Route::delete('/notificaciones/{appNotification}', [CommunicationController::class, 'destroyNotification'])->name('notifications.destroy');
    Route::post('/notificaciones/{appNotification}/enviar', [CommunicationController::class, 'sendNotification'])->name('notifications.send');
    Route::get('/favoritos', [CommunicationController::class, 'favorites'])->name('favorites');
    Route::get('/placas', [CommunicationController::class, 'plaques'])->name('plaques');
    Route::post('/placas/generar', [CommunicationController::class, 'generatePlaque'])->name('plaques.generate');
    Route::get('/placas/{post}', [CommunicationController::class, 'plaque'])->name('plaque');
    Route::get('/contenido/{post}', [CommunicationController::class, 'show'])->name('show');
    Route::get('/contenido/{post}/editar', [CommunicationController::class, 'edit'])->name('edit');
    Route::put('/contenido/{post}', [CommunicationController::class, 'update'])->name('update');
    Route::patch('/contenido/{post}/estado', [CommunicationController::class, 'updateStatus'])->name('status');
    Route::delete('/contenido/{post}', [CommunicationController::class, 'destroy'])->name('destroy');
});

Route::middleware(['auth', 'permission:audit.view'])->prefix('admin/auditoria')->name('admin.audit.')->group(function () {
    Route::get('/', [AuditController::class, 'index'])->name('index');
    Route::get('/exportar', [AuditController::class, 'export'])->name('export');
    Route::get('/{log}', [AuditController::class, 'show'])->name('show');
});
});
