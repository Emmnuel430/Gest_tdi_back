<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdherent
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!auth()->check() || !auth()->user() instanceof \App\Models\Adherent) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if (!auth()->user()->is_active) {
            return response()->json(['message' => 'Compte adhérent désactivé'], 403);
        }

        return $next($request);
    }
}
