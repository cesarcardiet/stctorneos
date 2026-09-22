<?php

use App\Http\Controllers\Api\AccountPortalController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\InvitationAuthController;
use App\Http\Controllers\Api\PasswordResetApiController;
use App\Http\Controllers\Api\PlayerRosterController;
use App\Http\Controllers\Api\TutorFichaApiController;
use App\Http\Controllers\Api\StaffMobileController;
use App\Http\Controllers\Api\WorkspaceMobileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
        Route::post('/auth/invitations/preview', [InvitationAuthController::class, 'preview']);
        Route::post('/auth/invitations/accept', [InvitationAuthController::class, 'accept']);
        Route::post('/auth/forgot-password', [PasswordResetApiController::class, 'forgot']);
        Route::post('/auth/reset-password', [PasswordResetApiController::class, 'reset']);
        Route::post('/auth/register/spectator', [AuthController::class, 'registerSpectator']);
    });

    Route::get('/home', [CatalogController::class, 'home']);
    Route::get('/tournaments', [CatalogController::class, 'tournaments']);
    Route::get('/tournaments/{tournament}', [CatalogController::class, 'tournament']);
    Route::get('/categories/{category}', [CatalogController::class, 'category']);
    Route::get('/matches', [CatalogController::class, 'matches']);
    Route::get('/matches/{match}', [CatalogController::class, 'match']);
    Route::get('/teams/{team}', [CatalogController::class, 'team']);
    Route::get('/players/{player}', [CatalogController::class, 'player']);

    Route::get('/categories/{category}/standings', [BoardController::class, 'standings']);
    Route::get('/categories/{category}/rankings', [BoardController::class, 'rankings']);
    Route::get('/categories/{category}/fair-play', [BoardController::class, 'fairPlay']);
    Route::get('/categories/{category}/brackets', [BoardController::class, 'brackets']);

    Route::get('/content', [FeedController::class, 'content']);
    Route::get('/content/{post:slug}', [FeedController::class, 'showContent']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/me', [AuthController::class, 'updateProfile']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/notifications', [FeedController::class, 'notifications']);
        Route::get('/favorites', [FeedController::class, 'favorites']);
        Route::post('/favorites/teams/{team}', [FeedController::class, 'toggleTeam']);
        Route::post('/favorites/matches/{match}', [FeedController::class, 'toggleMatch']);

        Route::get('/roster/context', [PlayerRosterController::class, 'context']);
        Route::get('/categories/{category}/players/{player}/roster', [PlayerRosterController::class, 'show']);
        Route::post('/categories/{category}/players', [PlayerRosterController::class, 'store']);
        Route::patch('/categories/{category}/players/{player}', [PlayerRosterController::class, 'update']);
        Route::post('/categories/{category}/players/{player}/documents', [PlayerRosterController::class, 'uploadDocument']);
        Route::post('/categories/{category}/players/{player}/invite-guardian', [PlayerRosterController::class, 'inviteGuardian']);

        Route::get('/tutor/players', [AccountPortalController::class, 'tutorPlayers']);
        Route::get('/tutor/players/{player}', [AccountPortalController::class, 'tutorPlayer']);
        Route::get('/tutor/players/{player}/ficha', [TutorFichaApiController::class, 'show']);
        Route::post('/tutor/players/{player}/ficha', [TutorFichaApiController::class, 'submit']);
        Route::get('/player/portal', [AccountPortalController::class, 'playerPortal']);
        Route::get('/workspace', [AccountPortalController::class, 'workspace']);
        Route::get('/workspace/teams/{team}', [WorkspaceMobileController::class, 'team']);
        Route::get('/workspace/teams/{team}/players', [WorkspaceMobileController::class, 'teamPlayers']);
        Route::get('/workspace/inscriptions', [WorkspaceMobileController::class, 'inscriptions']);

        Route::get('/staff/matches', [StaffMobileController::class, 'matches']);
        Route::get('/staff/matches/{match}', [StaffMobileController::class, 'show']);
        Route::post('/staff/matches/{match}/events', [StaffMobileController::class, 'addEvent']);
        Route::post('/staff/matches/{match}/report', [StaffMobileController::class, 'updateReport']);
    });
});
