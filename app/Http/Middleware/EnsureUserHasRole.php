<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserHasRole
{
    /**
     * Dette #03 : Contrôle d'accès basé sur les rôles (RBAC).
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();

        if (! $user || ! isset($user->role) || ! in_array($user->role, $roles, true)) {
            return response()->json([
                'error' => 'Accès refusé. Privilèges insuffisants.',
                'status' => 'forbidden',
            ], 403);
        }

        return $next($request);
    }
}
