<?php
namespace App\Http\Controllers;

use App\Actions\HandleCartPayment;
use App\Actions\HandlePrayerRequest;
use App\Actions\HandleSubscription;
use App\Actions\HandleTsedaka;
use App\Http\Requests\InitiatePaymentRequest;
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
    private function getCustomFields($request)
    {
        return match ($request->type) {
            'cart' => ['cart_details' => $request->cart],
            'prayer-request' => $request->only(['objet', 'message']),
            'subscription' => $request->only(['subscription_plan_id', 'payment_step', 'payment_index']),
            'tsedaka' => $request->only(['message', 'montant', 'anonymous']),
            default => [],
        };
    }

    public function initiate(InitiatePaymentRequest $request)
    {
        $paymentStep = $request->payment_step ?? 'full';

        if ($request->type === 'subscription') {

            $exists = Adherent::where('email', $request->email)->exists();

            if (in_array($paymentStep, ['registration', 'full']) && $exists) {
                return response()->json([
                    'message' => 'Compte déjà existant.'
                ], 422);
            }
        }

        try {
            return DB::transaction(function () use ($request) {

                $reference = Str::uuid()->toString();

                $metadata = array_merge(
                    $request->safe()->only(['email', 'type', 'numero']),
                    [
                        'password' => $request->password ? bcrypt($request->password) : null,
                        'nom' => ($request->type === 'tsedaka' && $request->anonymous) ? 'Anonyme' : $request->nom,
                        'prenom' => ($request->type === 'tsedaka' && $request->anonymous) ? 'Donateur' : $request->prenom,
                        'custom_fields' => $this->getCustomFields($request)
                    ]
                );

                $callbackUrl = $request->callback_url ?? config('app.frontend_url') . '/payment/callback';
                $response = Http::withToken(config('services.paystack.secret_key'))
                    ->post('https://api.paystack.co/transaction/initialize', [
                        'email' => $request->email,
                        'amount' => $request->totalPrice * 100,
                        'currency' => 'XOF',
                        'reference' => $reference,
                        'callback_url' => $callbackUrl,
                        'metadata' => $metadata
                    ]);

                if (!$response->successful()) {
                    throw new \Exception('Paystack Error');
                }

                return response()->json([
                    'authorization_url' => $response['data']['authorization_url'],
                    'reference' => $reference,
                ]);
            });

        } catch (\Throwable $e) {

            \Log::error('Payment initiate error: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur initiation'
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

                    $result = app($actions[$type])->execute($transaction, $metadata);

                    if (is_array($result) && ($result['status'] ?? null) === 'error') {
                        return response()->json([
                            'message' => $result['message']
                        ], $result['code'] ?? 400);
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