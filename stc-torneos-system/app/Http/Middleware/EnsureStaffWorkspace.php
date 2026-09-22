<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isPlayerAccount() && ! $request->isMethodSafe()) {
            abort(403, 'No podés modificar datos desde tu cuenta de jugador.');
        }

        if ($request->user()?->isTutorAccount() && ! $request->isMethodSafe()) {
            abort(403, 'Como tutor solo podés consultar información. Pedí cambios al administrador o delegado.');
        }

        return $next($request);
    }
}
