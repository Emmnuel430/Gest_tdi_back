<?php
namespace App\Http\Controllers;

use App\Actions\HandleCartPayment;
use App\Actions\HandlePrayerRequest;
use App\Actions\HandleSubscription;
use App\Actions\HandleTsedaka;
use App\Models\Adherent;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
            'type' => 'required|string|in:cart,prayer-request,subscription,tsedaka',

            // uniquement si cart
            'cart' => 'required_if:type,cart|array',

            // uniquement si prière
            'objet' => 'required_if:type,prayer-request|string',

            // subscription
            'subscription_plan_id' => 'required_if:type,subscription|exists:subscription_plans,id',
            'password' => 'required_if:type,subscription|string|min:6',

            'message' => 'nullable|string',
            'montant' => 'required_if:type,tsedaka|numeric|min:100',
            'anonymous' => 'required_if:type,tsedaka|boolean',
        ]);

        try {
            return DB::transaction(function () use ($request) {

                // 1. Vérif email uniquement pour subscription
                if ($request->type === 'subscription') {
                    $exists = Adherent::where('email', $request->email)->exists();

                    if ($exists) {
                        return response()->json([
                            'message' => 'Un compte avec cet email existe déjà.'
                        ], 422);
                    }
                }

                // 2. Génération référence
                $reference = Str::uuid()->toString();

                // 3. Custom fields
                $customFields = match ($request->type) {
                    'cart' => [
                        'cart_details' => $request->cart,
                    ],
                    'prayer-request' => [
                        'objet' => $request->objet,
                        'message' => $request->message,
                    ],
                    'subscription' => [
                        'subscription_plan_id' => $request->subscription_plan_id,
                        'payment_step' => $request->payment_step,
                        'payment_index' => $request->payment_index,
                    ],
                    'tsedaka' => [
                        'message' => $request->message,
                        'montant' => $request->montant,
                        'anonymous' => $request->anonymous,
                    ],
                    default => [],
                };

                // 4. Metadata
                $metadata = [
                    'nom' => $request->anonymous ? 'Anonyme' : $request->nom,
                    'prenom' => $request->anonymous ? 'Donateur' : $request->prenom,
                    'email' => $request->email,
                    'numero' => $request->numero,
                    'type' => $request->type,

                    // attention sécurité
                    'password' => $request->password ? bcrypt($request->password) : null,
                    'custom_fields' => $customFields,
                ];

                if ($request->type === 'cart') {
                    $metadata['total_items'] = $request->totalItems;
                    $metadata['commune'] = $request->commune;
                }

                // 5. Appel Paystack
                $response = Http::withToken(config('services.paystack.secret_key'))
                    ->post('https://api.paystack.co/transaction/initialize', [
                        'email' => $request->email,
                        'amount' => $request->totalPrice * 100,
                        'currency' => 'XOF',
                        'reference' => $reference,
                        'callback_url' => config('app.frontend_url') . '/payment/callback',
                        'metadata' => $metadata
                    ]);

                if (!$response->successful()) {
                    throw new \Exception('Erreur Paystack');
                }

                return response()->json([
                    'authorization_url' => $response['data']['authorization_url'],
                    'reference' => $reference,
                ]);
            });

        } catch (\Throwable $e) {
            \Log::error('Payment initiate error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l’initiation du paiement'
            ], 500);
        }
    }

    public function verify($reference)
    {
        // 🔒 1. Verrou de sécurité (évite les race conditions)
        return Cache::lock("payment_{$reference}", 10)->get(function () use ($reference) {

            if (Transaction::where('reference', $reference)->exists()) {
                return response()->json(['message' => 'Déjà traité', 'status' => 'ok']);
            }

            try {
                $response = Http::withToken(config('services.paystack.secret_key'))
                    ->get("https://api.paystack.co/transaction/verify/{$reference}");

                if (!$response->successful() || !$response->json('status')) {
                    return response()->json(['error' => 'Échec de vérification Paystack'], 400);
                }

                $data = $response->json('data');

                if ($data['status'] !== 'success') {
                    return response()->json(['error' => 'Paiement non validé'], 400);
                }

                return DB::transaction(function () use ($data) {
                    $metadata = $data['metadata'] ?? [];
                    $type = $metadata['type'] ?? 'unknown';

                    // Création de la transaction de base
                    $transaction = Transaction::create([
                        'reference' => $data['reference'],
                        'amount' => $data['amount'] / 100,
                        'currency' => $data['currency'],
                        'status' => 'success',
                        'nom' => trim(
                            ($metadata['nom'] ?? '') . ' ' . ($metadata['prenom'] ?? '')
                        ) ?: 'Client N/A',
                        'email' => $data['customer']['email'] ?? null,
                        'numero' => $metadata['numero'] ?? null,
                        'type' => $type,
                    ]);

                    // Mapping des actions
                    $actions = [
                        'cart' => HandleCartPayment::class,
                        'prayer-request' => HandlePrayerRequest::class,
                        'subscription' => HandleSubscription::class,
                        'tsedaka' => HandleTsedaka::class,
                    ];

                    if (array_key_exists($type, $actions)) {
                        app($actions[$type])->execute($transaction, $metadata);
                    }

                    return response()->json(['status' => 'ok', 'reference' => $data['reference']]);
                });

            } catch (\Exception $e) {
                Log::error("Erreur Paystack: " . $e->getMessage());
                return response()->json(['error' => 'Erreur serveur'], 500);
            }
        });
    }


    // public function webhook(Request $request)
    // {
    //     $signature = $request->header('x-paystack-signature');
    //     $secret = config('services.paystack.secret_key');

    //     // 🔐 Vérification signature
    //     $computedSignature = hash_hmac('sha512', $request->getContent(), $secret);

    //     if ($signature !== $computedSignature) {
    //         return response()->json(['error' => 'Invalid signature'], 401);
    //     }

    //     $event = $request->all();
    //     Log::info('Paystack webhook', $event);

    //     // 🎯 On ne traite que les paiements réussis
    //     if ($event['event'] !== 'charge.success') {
    //         return response()->json(['status' => 'ignored']);
    //     }

    //     $data = $event['data'];

    //     // Sécurité minimale
    //     if ($data['status'] !== 'success') {
    //         return response()->json(['status' => 'not success']);
    //     }

    //     if ($data['currency'] !== 'XOF') {
    //         return response()->json(['status' => 'invalid currency']);
    //     }

    //     // Anti doublon
    //     $existing = Order::where('reference', $data['reference'])->first();
    //     if ($existing) {
    //         return response()->json(['status' => 'already processed']);
    //     }

    //     // Récupération metadata
    //     $metadata = $data['metadata'] ?? [];

    //     // Sécurité supplémentaire (optionnelle mais propre)
    //     if (!isset($metadata['type'])) {
    //         return response()->json(['status' => 'missing type']);
    //     }

    //     // ✅ Création commande
    //     Order::create([
    //         'reference' => $data['reference'],
    //         'amount' => $data['amount'] / 100,
    //         'currency' => $data['currency'],
    //         'status' => 'paid',

    //         'nom' => $metadata['nom'] ?? null,
    //         'email' => $data['customer']['email'] ?? null,
    //         'numero' => $metadata['numero'] ?? null,

    //         'type' => $metadata['type'],

    //         'commune' => $metadata['commune'] ?? null,
    //         'total_items' => $metadata['totalItems'] ?? null,

    //         'metadata' => $metadata,
    //     ]);

    //     // 🎯 Actions métier selon type
    //     switch ($metadata['type']) {

    //         case 'cart':
    //             // 👉 envoyer ebook / traiter panier
    //             break;

    //         case 'prayer':
    //             // 👉 enregistrer demande de prière / notifier
    //             break;

    //         case 'subscription':
    //             // 👉 activer abonnement
    //             break;
    //     }

    //     return response()->json(['status' => 'success']);
    // }
}