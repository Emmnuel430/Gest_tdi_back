<?php
namespace App\Http\Controllers;

use App\Mail\OrderConfirm;
use App\Mail\OrderResourceSend;
use App\Mail\PrayerRequestValidated;
use App\Models\Order;
use App\Models\PrayerRequest;
use App\Models\Subsection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PayementController extends Controller
{
    public function initiate(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'totalPrice' => 'required|numeric',
            'type' => 'required|string|in:cart,prayer-request',

            // uniquement si cart
            'cart' => 'required_if:type,cart|array',

            // uniquement si prière
            'objet' => 'required_if:type,prayer-request|string',
            'message' => 'required_if:type,prayer-request|string',
        ]);
        $reference = Str::uuid()->toString();

        $customFields = match ($request->type) {
            'cart' => [
                'cart_details' => $request->cart,
            ],
            'prayer-request' => [
                'objet' => $request->objet,
                'message' => $request->message,
            ],
            default => [],
        };

        // Log::info('Custom fields', $customFields);

        $metadata = [
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'numero' => $request->numero,
            'type' => $request->type,
            'commune' => $request->commune,
            'custom_fields' => $customFields,
        ];

        if ($request->type === 'cart') {
            $metadata['total_items'] = $request->totalItems;
        }

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
            return response()->json(['error' => 'Erreur Paystack'], 500);
        }

        return response()->json([
            'authorization_url' => $response['data']['authorization_url'],
            'reference' => $reference,
        ]);
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

        // 🔒 Vérifier si déjà traité
        $existingOrder = Order::where('reference', $reference)->first();

        if ($existingOrder) {
            return response()->json([
                'message' => 'Déjà traité',
                'reference' => $reference,
                'status' => 'ok'
            ]);
        }

        $metadata = $data['metadata'] ?? [];
        $customFields = $metadata['custom_fields'] ?? [];

        // Log::info('Verify Custom fields', $customFields);

        $type = in_array($metadata['type'] ?? null, ['cart', 'prayer-request', 'subscription'])
            ? $metadata['type']
            : 'unknown';

        $order = Order::create([
            'reference' => $data['reference'],
            'amount' => $data['amount'] / 100,
            'currency' => $data['currency'],
            'status' => 'paid',

            // Champs Identité
            'nom' => $metadata['nom'] ?? "Client N/A",
            'email' => $data['customer']['email'] ?? null,
            'numero' => $metadata['numero'] ?? null,

            // Type pour la logique métier (Tri/Scope)
            'type' => $type,

            // Champs spécifiques (optionnels en colonnes pour stats rapides)
            'commune' => $metadata['commune'] ?? null,
            'total_items' => $metadata['total_items'] ?? null,

            // On stocke TOUT le bloc custom_fields dans la colonne JSON metadata
            'metadata' => $customFields,
        ]);

        // 🎯 Actions métier
        switch ($type) {
            case 'cart':

                $resources = [];

                foreach ($customFields['cart_details'] ?? [] as $item) {
                    $subsection = Subsection::find($item['product_id']);

                    if (!$subsection)
                        continue;

                    // 🎯 Ressource
                    if ($subsection->type === 'ressource' && $subsection->link) {
                        $resources[] = [
                            'title' => $subsection->title,
                            'link' => $subsection->link,
                        ];
                    }
                }

                try {
                    Mail::to($order->email)->send(
                        new OrderConfirm($order, $resources) // UN SEUL MAIL
                    );
                } catch (\Exception $e) {
                    Log::error('Erreur envoi mail : ' . $e->getMessage());
                    return response()->json([
                        'message' => 'Commande validée, mais erreur lors de l’envoi du mail'
                    ], 200);
                }

                break;

            case 'prayer-request':
                $already = PrayerRequest::where('email', $data['customer']['email'])
                    ->where('objet', $customFields['objet'] ?? null)
                    ->latest()
                    ->first();

                if (!$already) {
                    $prayer = PrayerRequest::create([
                        'nom' => $metadata['nom'] ?? null,
                        'prenom' => $metadata['prenom'] ?? null,
                        'email' => $data['customer']['email'] ?? null,
                        'objet' => $customFields['objet'] ?? null,
                        'message' => $customFields['message'] ?? null,
                        'is_validated' => true,
                    ]);

                    try {
                        Mail::to($prayer->email)->send(new PrayerRequestValidated($prayer));
                    } catch (\Exception $e) {
                        Log::error('Erreur envoi mail : ' . $e->getMessage());
                        return response()->json(['message' => 'Demande validée, mais erreur mail'], 200);
                    }
                }

                break;
            case 'subscription':
                // 👉 activer abonnement
                // Subscription::create([...]);
                break;
        }

        return response()->json([
            'message' => 'Paiement confirmé',
            'reference' => $reference,
            'status' => 'ok'
        ]);
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

    //     // 🔥 Sécurité minimale
    //     if ($data['status'] !== 'success') {
    //         return response()->json(['status' => 'not success']);
    //     }

    //     if ($data['currency'] !== 'XOF') {
    //         return response()->json(['status' => 'invalid currency']);
    //     }

    //     // 🔥 Anti doublon
    //     $existing = Order::where('reference', $data['reference'])->first();
    //     if ($existing) {
    //         return response()->json(['status' => 'already processed']);
    //     }

    //     // 🔥 Récupération metadata
    //     $metadata = $data['metadata'] ?? [];

    //     // 🔥 Sécurité supplémentaire (optionnelle mais propre)
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