<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AttachTokenFromCookie
{
    /**
     * Dette #23 : Permet l'authentification Sanctum via cookie HttpOnly
     * tout en conservant la rétrocompatibilité avec l'en-tête Authorization Bearer.
     */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->bearerToken() && $request->hasCookie('auth_token')) {
            $token = $request->cookie('auth_token');
            if (is_string($token) && $token !== '') {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
