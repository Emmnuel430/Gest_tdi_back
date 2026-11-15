<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaystackSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signature = $request->header('x-paystack-signature');
        $secret = config('services.paystack.secret_key');

        if (!$signature || !$secret) {
            Log::warning('Paystack Webhook rejeté : Signature ou clé secrète manquante', [
                'ip' => $request->ip(),
                'has_signature' => !empty($signature),
                'has_secret' => !empty($secret),
            ]);

            return response()->json(['error' => 'Signature or secret missing'], Response::HTTP_UNAUTHORIZED);
        }

        // Calcul du HMAC SHA512 sur le contenu brut (Raw Content)
        $computedSignature = hash_hmac('sha512', $request->getContent(), $secret);

        if (!hash_equals($signature, $computedSignature)) {
            Log::warning('Paystack Webhook rejeté : Signature HMAC non concordante', [
                'ip' => $request->ip(),
                'provided_signature' => $signature,
            ]);

            return response()->json(['error' => 'Invalid signature'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
