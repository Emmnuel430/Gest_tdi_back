<?php
namespace App\Http\Controllers;

use App\Actions\HandleCartPayment;
use App\Actions\HandlePrayerRequest;
use App\Actions\HandleSubscription;
use App\Actions\HandleTsedaka;
use App\Http\Requests\InitiatePaymentRequest;
use App\Models\Adherent;
use App\Models\SubscriptionPlan;
use App\Models\Subsection;
use App\Models\Transaction;
use App\Models\WebhookEvent;
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
            'cart' => [
                'cart_details' => collect($request->cart ?? [])->map(function ($item) {
                    $sub = Subsection::find($item['product_id'] ?? null);
                    return [
                        'product_id' => (int) ($item['product_id'] ?? 0),
                        'title' => $sub ? $sub->title : ($item['title'] ?? 'Article'),
                        'price' => $sub && $sub->prix !== null ? (float) $sub->prix : (float) ($item['price'] ?? 0),
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'type' => $sub ? $sub->type : ($item['type'] ?? null),
                        'image' => $item['image'] ?? ($sub && $sub->image ? asset('storage/' . $sub->image) : null),
                        'itemFrom' => $item['itemFrom'] ?? null,
                    ];
                })->values()->all(),
            ],
            'prayer-request' => [
                'objet' => $request->objet,
                'message' => $request->message,
                'montant' => (float) $request->montant,
                'montant_tsedaka' => (float) ($request->montant_tsedaka ?? 0),
            ],
            'subscription' => $request->only(['subscription_plan_id', 'payment_step', 'payment_index']),
            'tsedaka' => [
                'message' => $request->message,
                'montant' => (float) $request->montant,
                'anonymous' => filter_var($request->anonymous ?? false, FILTER_VALIDATE_BOOLEAN),
            ],
            default => [],
        };
    }

    /**
     * Dette #01 : Recalcul strict côté serveur du montant attendu selon le type d'opération.
     */
    private function calculateExpectedAmount(InitiatePaymentRequest $request, string $paymentStep): float
    {
        return match ($request->type) {
            'cart' => (function () use ($request) {
                $cartItems = collect($request->cart ?? []);
                $productIds = $cartItems->pluck('product_id')->unique()->all();
                $subsections = Subsection::whereIn('id', $productIds)->get()->keyBy('id');

                $total = 0.0;
                foreach ($cartItems as $item) {
                    $sub = $subsections->get($item['product_id']);
                    if (!$sub || $sub->prix === null || (float) $sub->prix <= 0) {
                        throw new \InvalidArgumentException("Article invalide ou prix indisponible.");
                    }
                    $total += ((float) $sub->prix) * ((int) $item['quantity']);
                }
                return $total;
            })(),
            'subscription' => (function () use ($request, $paymentStep) {
                $plan = SubscriptionPlan::findOrFail($request->subscription_plan_id);
                if ($plan->billing_type === 'hybrid') {
                    return $paymentStep === 'installment'
                        ? (float) $plan->monthly_price
                        : (float) $plan->registration_fee;
                }
                return (float) $plan->price;
            })(),
            'prayer-request' => (function () use ($request) {
                $montant = (float) ($request->montant ?? 0);
                $montantTsedaka = (float) ($request->montant_tsedaka ?? 0);
                if ($montant < 2000 || $montantTsedaka < 0) {
                    throw new \InvalidArgumentException("Le montant minimum pour une demande de prière est de 2 000 FCFA.");
                }
                return $montant + $montantTsedaka;
            })(),
            'tsedaka' => (function () use ($request) {
                $montant = (float) ($request->montant ?? 0);
                if ($montant < 2000) {
                    throw new \InvalidArgumentException("Le montant minimum pour un don (Tsedaka) est de 2 000 FCFA.");
                }
                return $montant;
            })(),
            default => 0.0,
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
            $expectedAmount = $this->calculateExpectedAmount($request, $paymentStep);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 422);
        }

        if ($expectedAmount <= 0 || abs(((float) $request->totalPrice) - $expectedAmount) > 0.01) {
            return response()->json([
                'message' => 'Montant du paiement invalide ou falsifié.'
            ], 422);
        }

        try {
            return DB::transaction(function () use ($request, $expectedAmount) {

                $reference = Str::uuid()->toString();

                // 🔒 Dette #02 : Ne jamais transmettre de hash de mot de passe à Paystack
                if ($request->filled('password') && $request->password !== 'null') {
                    $hashedPassword = bcrypt($request->password);
                    Cache::put("pending_sub_pwd_{$reference}", $hashedPassword, now()->addHours(24));
                    Cache::put("pending_sub_pwd_email_" . md5(strtolower($request->email)), $hashedPassword, now()->addHours(24));
                }

                $customFields = $this->getCustomFields($request);

                $metadata = array_merge(
                    $request->safe()->only(['email', 'type', 'numero']),
                    [
                        'nom' => ($request->type === 'tsedaka' && $request->anonymous) ? 'Anonyme' : $request->nom,
                        'prenom' => ($request->type === 'tsedaka' && $request->anonymous) ? 'Donateur' : $request->prenom,
                        'commune' => $request->commune ?? null,
                        'total_items' => $request->totalItems ?? null,
                        'custom_fields' => $customFields,
                    ]
                );

                if ($request->type === 'subscription') {
                    $metadata['subscription_plan_id'] = $request->subscription_plan_id;
                    $metadata['payment_step'] = $request->payment_step;
                    $metadata['payment_index'] = $request->payment_index;
                }

                $callbackUrl = $request->callback_url ?? config('app.frontend_url') . '/payment/callback';
                $response = Http::withToken(config('services.paystack.secret_key'))
                    ->post('https://api.paystack.co/transaction/initialize', [
                        'email' => $request->email,
                        'amount' => (int) round($expectedAmount * 100),
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

    /**
     * Traite de manière idempotente et transactionnelle un paiement Paystack confirmé.
     * Utilisé à la fois par verify() et webhook().
     */
    private function processSuccessfulPayment(array $data)
    {
        $reference = $data['reference'];
        $metadata = $data['metadata'] ?? [];
        $type = $metadata['type'] ?? 'unknown';

        // 🛡️ Anti-doublon : vérifier si déjà traité
        $existing = Transaction::where('reference', $reference)->first();
        if ($existing) {
            return [
                'status' => 'ok',
                'message' => 'Déjà traité',
                'reference' => $reference,
                'type' => $existing->type,
            ];
        }

        return DB::transaction(function () use ($data, $metadata, $type, $reference) {
            $transaction = Transaction::create([
                'reference' => $reference,
                'amount' => $data['amount'] / 100,
                'currency' => $data['currency'] ?? 'XOF',
                'status' => 'success',
                'nom' => trim(
                    ($metadata['nom'] ?? '') . ' ' . ($metadata['prenom'] ?? '')
                ) ?: 'Client N/A',
                'email' => $data['customer']['email'] ?? null,
                'numero' => $metadata['numero'] ?? null,
                'type' => $type,
            ]);

            $actions = [
                'cart' => HandleCartPayment::class,
                'prayer-request' => HandlePrayerRequest::class,
                'subscription' => HandleSubscription::class,
                'tsedaka' => HandleTsedaka::class,
            ];

            if (isset($actions[$type])) {
                $result = app($actions[$type])->execute($transaction, $metadata);

                if (is_array($result) && ($result['status'] ?? null) === 'error') {
                    return $result;
                }
            } else {
                Log::warning("Paystack : Type de paiement non géré : {$type}", [
                    'reference' => $reference,
                    'metadata' => $metadata
                ]);
            }

            return [
                'status' => 'ok',
                'reference' => $reference,
                'type' => $type,
            ];
        });
    }

    public function verify($reference)
    {
        // 🔒 Verrou de sécurité (évite les race conditions avec webhook)
        return Cache::lock("payment_{$reference}", 15)->get(function () use ($reference) {

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

                $result = $this->processSuccessfulPayment($data);

                if (is_array($result) && ($result['status'] ?? null) === 'error') {
                    return response()->json([
                        'message' => $result['message']
                    ], $result['code'] ?? 400);
                }

                return response()->json($result);

            } catch (\Exception $e) {
                Log::error("Erreur Paystack verify: " . $e->getMessage());
                return response()->json(['error' => 'Erreur serveur'], 500);
            }
        });
    }

    public function webhook(Request $request)
    {
        $event = $request->all();
        $eventType = $event['event'] ?? 'unknown';
        $data = $event['data'] ?? [];
        $reference = $data['reference'] ?? null;

        Log::info('Paystack webhook reçu', [
            'event' => $eventType,
            'reference' => $reference,
        ]);

        // Audit et traçabilité de l'événement (en purgeant tout champ sensible éventuel)
        $webhookEvent = null;
        if ($reference) {
            $sanitizedEvent = $event;
            unset($sanitizedEvent['data']['metadata']['password']);

            $webhookEvent = WebhookEvent::firstOrCreate(
                [
                    'provider' => 'paystack',
                    'reference' => $reference,
                    'event_type' => $eventType,
                ],
                [
                    'event_id' => $event['id'] ?? null,
                    'payload' => $sanitizedEvent,
                    'status' => 'received',
                ]
            );
        }

        // On ne traite que les paiements réussis
        if ($eventType !== 'charge.success') {
            return response()->json(['status' => 'ignored'], 200);
        }

        if (($data['status'] ?? '') !== 'success') {
            return response()->json(['status' => 'not_success'], 200);
        }

        if (!$reference) {
            return response()->json(['error' => 'Missing reference'], 400);
        }

        // 🔒 Verrou concurrentiel pour synchroniser avec verify()
        return Cache::lock("payment_{$reference}", 15)->get(function () use ($data, $reference, $webhookEvent) {
            if (Transaction::where('reference', $reference)->exists()) {
                if ($webhookEvent) {
                    $webhookEvent->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                    ]);
                }
                return response()->json(['status' => 'already_processed'], 200);
            }

            try {
                $result = $this->processSuccessfulPayment($data);

                if ($webhookEvent) {
                    $webhookEvent->update([
                        'status' => 'processed',
                        'processed_at' => now(),
                    ]);
                }

                return response()->json($result, 200);
            } catch (\Throwable $e) {
                Log::error("Erreur exécution Paystack webhook : " . $e->getMessage(), [
                    'reference' => $reference,
                ]);

                if ($webhookEvent) {
                    $webhookEvent->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }

                return response()->json(['error' => 'Internal processing error'], 500);
            }
        });
    }
}
