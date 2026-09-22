<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminWebAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName() ?? '';

        if (
            $user
            && ($user->isPlayerAccount() || $user->isTutorAccount())
            && ($routeName === 'dashboard' || str_starts_with($routeName, 'admin.'))
        ) {
            return redirect()
                ->route('workspace.home')
                ->with('status', 'Tu acceso es por Operación.');
        }

        if (
            $user
            && $user->restrictsToAssignedClub()
            && ($routeName === 'dashboard' || str_starts_with($routeName, 'admin.'))
        ) {
            return redirect()
                ->route('workspace.home')
                ->with('status', 'Tu acceso es por Operación y solo para tu club.');
        }

        return $next($request);
    }
}
