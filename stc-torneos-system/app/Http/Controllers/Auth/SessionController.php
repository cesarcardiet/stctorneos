<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AdminNavigation;
use App\Support\PlayerCredentials;
use App\Support\TutorCredentials;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route(auth()->user()->defaultHomeRoute());
        }

        $defaultEmail = old('email', 'admin@stctorneos.demo');

        $demoAccounts = AdminNavigation::demoAccounts();

        return view('auth.login', [
            'playerDefaultPassword' => PlayerCredentials::defaultPassword(),
            'tutorDefaultPassword' => TutorCredentials::defaultPassword(),
            'defaultEmail' => $defaultEmail,
            'defaultPassword' => AdminNavigation::demoPasswordFor($defaultEmail),
            'demoEmails' => collect($demoAccounts)->map(fn (array $account) => $account[2])->values()->all(),
            'demoPasswordMap' => collect($demoAccounts)->mapWithKeys(
                fn (array $account) => [$account[2] => AdminNavigation::demoPasswordFor($account[2])]
            )->all(),
            'quickProfiles' => [
                'Admin' => 'admin@stctorneos.demo',
                'Delegado' => 'delegado@stctorneos.demo',
                'Árbitro' => 'coordinador@stctorneos.demo',
            ],
            'showQuickProfiles' => true,
            'activeQuickEmail' => $defaultEmail,
        ]);
    }
}
