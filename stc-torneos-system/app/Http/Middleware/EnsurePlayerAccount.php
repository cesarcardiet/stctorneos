<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlayerAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user?->isPlayerAccount(), 403);
        abort_unless($user->linkedPlayer(), 403, 'Tu cuenta no está vinculada a una ficha de jugador.');

        return $next($request);
    }
}
