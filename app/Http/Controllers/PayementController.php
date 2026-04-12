<?php
namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PayementController extends Controller
{
    public function initiate(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'totalPrice' => 'required|numeric',
            'cart' => 'required|array',
            'type' => 'required|string',
        ]);

        // Paystack peut aussi générer une référence
        // 👉 MAIS je forces → c’est mieux
        $reference = Str::uuid()->toString();

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $request->email,
                'amount' => $request->totalPrice * 100,
                'currency' => 'XOF',
                'reference' => $reference,
                'callback_url' => config('app.frontend_url') . '/payment/callback',

                // IMPORTANT : stocker ton payload
                'metadata' => [
                    'nom' => $request->nom,
                    'numero' => $request->numero,
                    'commune' => $request->commune,
                    'cart' => $request->cart,
                    'type' => $request->type,
                    'totalItems' => $request->totalItems,
                ]
            ]);

        if (!$response->successful()) {
            return response()->json(['error' => 'Erreur Paystack'], 500);
        }

        return response()->json([
            'authorization_url' => $response['data']['authorization_url'],
            'reference' => $reference,
        ]);
    }

    public function initiateMobileMoney(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'totalPrice' => 'required|numeric',
            'numero' => 'required|string',
            'provider' => 'required|string', // mtn, vodafone, etc
        ]);

        $response = Http::withToken(config('services.paystack.secret_key'))
            ->post('https://api.paystack.co/charge', [
                'amount' => $request->totalPrice * 100,
                'email' => $request->email,
                'currency' => 'XOF', // ⚠️ dépend du pays Paystack

                'mobile_money' => [
                    'phone' => $request->numero,
                    'provider' => $request->provider
                ]
            ]);

        if (!$response->successful()) {
            return response()->json([
                'error' => 'Erreur Mobile Money',
                'details' => $response->json()
            ], 400);
        }

        return response()->json($response->json());
    }

    public function verify($reference)
    {
        $response = Http::withToken(config('services.paystack.secret_key'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if (!$response->successful()) {
            return response()->json(['error' => 'Verification failed'], 500);
        }

        $body = $response->json();

        if (!$body['status']) {
            return response()->json(['error' => 'Transaction invalide'], 400);
        }

        $data = $body['data'];

        if ($data['status'] !== 'success') {
            return response()->json(['error' => 'Paiement non validé'], 400);
        }

        return response()->json([
            'message' => 'Paiement confirmé',
            'reference' => $reference,
            'status' => 'ok'
        ]);
    }

    public function webhook(Request $request)
    {
        $signature = $request->header('x-paystack-signature');
        $secret = config('services.paystack.secret_key');

        // 🔐 Vérification signature
        $computedSignature = hash_hmac('sha512', $request->getContent(), $secret);

        if ($signature !== $computedSignature) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $event = $request->all();
        Log::info('Paystack webhook', $event);

        // 🎯 On ne traite que les paiements réussis
        if ($event['event'] !== 'charge.success') {
            return response()->json(['status' => 'ignored']);
        }

        $data = $event['data'];

        // 🔥 Sécurité minimale
        if ($data['status'] !== 'success') {
            return response()->json(['status' => 'not success']);
        }

        if ($data['currency'] !== 'XOF') {
            return response()->json(['status' => 'invalid currency']);
        }

        // 🔥 Anti doublon
        $existing = Order::where('reference', $data['reference'])->first();
        if ($existing) {
            return response()->json(['status' => 'already processed']);
        }

        // 🔥 Récupération metadata
        $metadata = $data['metadata'] ?? [];

        // 🔥 Sécurité supplémentaire (optionnelle mais propre)
        if (!isset($metadata['type'])) {
            return response()->json(['status' => 'missing type']);
        }

        // ✅ Création commande
        Order::create([
            'reference' => $data['reference'],
            'amount' => $data['amount'] / 100,
            'currency' => $data['currency'],
            'status' => 'paid',

            'nom' => $metadata['nom'] ?? null,
            'email' => $data['customer']['email'] ?? null,
            'numero' => $metadata['numero'] ?? null,

            'type' => $metadata['type'],

            'commune' => $metadata['commune'] ?? null,
            'total_items' => $metadata['totalItems'] ?? null,

            'metadata' => $metadata,
        ]);

        // 🎯 Actions métier selon type
        switch ($metadata['type']) {

            case 'cart':
                // 👉 envoyer ebook / traiter panier
                break;

            case 'prayer':
                // 👉 enregistrer demande de prière / notifier
                break;

            case 'subscription':
                // 👉 activer abonnement
                break;
        }

        return response()->json(['status' => 'success']);
    }
}