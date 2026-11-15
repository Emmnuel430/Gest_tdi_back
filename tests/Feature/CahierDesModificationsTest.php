<?php

namespace Tests\Feature;

use App\Jobs\SendLibraryResourcesJob;
use App\Mail\OrderConfirm;
use App\Mail\PrayerRequestValidated;
use App\Mail\TsedakaThankYou;
use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\Page;
use App\Models\PrayerRequest;
use App\Models\Section;
use App\Models\Subsection;
use App\Models\Tsedaka;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CahierDesModificationsTest extends TestCase
{
    use RefreshDatabase;

    private string $paystackSecret = 'sk_test_cahier_modifications';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => $this->paystackSecret]);
    }

    private function sendSignedWebhook(array $payload)
    {
        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha512', $rawBody, $this->paystackSecret);

        return $this->call(
            'POST',
            '/api/payments/webhook',
            [],
            [],
            [],
            [
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawBody
        );
    }

    /**
     * TÂCHE 01 — Cas 1 : Achat d'un produit de la boutique avec paiement réussi -> email envoyé
     */
    public function test_tache_01_boutique_product_purchase_sends_confirmation_email()
    {
        Mail::fake();

        $page = Page::create(['title' => 'Boutique', 'slug' => 'boutique', 'template' => 'boutique']);
        $section = Section::create(['page_id' => $page->id, 'title' => 'Articles', 'order' => 1]);
        $product = Subsection::create([
            'section_id' => $section->id,
            'title' => 'Chofar Artisanal',
            'type' => 'produit',
            'prix' => 12000,
            'order' => 1,
        ]);

        $reference = 'BOUTIQUE_REF_' . uniqid();

        $response = $this->sendSignedWebhook([
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 2400000, // 24 000 FCFA
                'currency' => 'XOF',
                'status' => 'success',
                'customer' => ['email' => 'acheteur.boutique@test.com'],
                'metadata' => [
                    'type' => 'cart',
                    'nom' => 'Kouassi',
                    'prenom' => 'Marc',
                    'numero' => '0701020304',
                    'commune' => 'Cocody',
                    'total_items' => 2,
                    'custom_fields' => [
                        'cart_details' => [
                            [
                                'product_id' => $product->id,
                                'title' => $product->title,
                                'price' => 12000,
                                'quantity' => 2,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);

        $order = Order::where('reference', $reference)->first();
        $this->assertNotNull($order);
        $this->assertEquals('sent', $order->resource_delivery_status);
        $this->assertEquals('acheteur.boutique@test.com', $order->resource_recipient_email);

        Mail::assertSent(OrderConfirm::class, function (OrderConfirm $mail) use ($order) {
            return $mail->hasTo('acheteur.boutique@test.com')
                && $mail->order->id === $order->id
                && empty($mail->resources);
        });
    }

    /**
     * TÂCHE 01 — Cas 2 : Achat d'une ressource de la bibliothèque avec paiement réussi -> email envoyé avec lien
     */
    public function test_tache_01_library_resource_purchase_sends_email_with_resource_link()
    {
        Mail::fake();

        $page = Page::create(['title' => 'Bibliothèque', 'slug' => 'bibliotheque', 'template' => 'boutique']);
        $section = Section::create(['page_id' => $page->id, 'title' => 'Ebooks', 'order' => 1]);
        $ebook = Subsection::create([
            'section_id' => $section->id,
            'title' => 'Guide de la Paracha PDF',
            'type' => 'ressource',
            'link' => 'https://drive.google.com/file/d/ebook-paracha-123/view',
            'prix' => 5000,
            'order' => 1,
        ]);

        $reference = 'BIBLIO_REF_' . uniqid();

        $response = $this->sendSignedWebhook([
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 500000, // 5 000 FCFA
                'currency' => 'XOF',
                'status' => 'success',
                'customer' => ['email' => 'lecteur.biblio@test.com'],
                'metadata' => [
                    'type' => 'cart',
                    'nom' => 'Bamba',
                    'prenom' => 'Sarah',
                    'numero' => '0705060708',
                    'commune' => 'Plateau',
                    'total_items' => 1,
                    'custom_fields' => [
                        'cart_details' => [
                            [
                                'product_id' => $ebook->id,
                                'title' => $ebook->title,
                                'price' => 5000,
                                'quantity' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);

        $order = Order::where('reference', $reference)->first();
        $this->assertNotNull($order);
        $this->assertEquals('sent', $order->resource_delivery_status);

        Mail::assertSent(OrderConfirm::class, function (OrderConfirm $mail) use ($ebook) {
            return $mail->hasTo('lecteur.biblio@test.com')
                && count($mail->resources) === 1
                && $mail->resources[0]['title'] === $ebook->title
                && $mail->resources[0]['link'] === $ebook->link;
        });
    }

    /**
     * TÂCHE 01 — Cas 3 & Cas 4 : Paiement échoué -> aucun email ; Paiement confirmé deux fois -> aucun email en double
     */
    public function test_tache_01_failed_payment_sends_no_email_and_duplicate_confirmation_sends_only_once()
    {
        Mail::fake();

        // Cas 3 : Paiement échoué
        $failedRef = 'FAILED_REF_' . uniqid();
        $failedResp = $this->sendSignedWebhook([
            'event' => 'charge.success',
            'data' => [
                'reference' => $failedRef,
                'amount' => 500000,
                'status' => 'failed',
                'customer' => ['email' => 'echec@test.com'],
                'metadata' => ['type' => 'cart'],
            ],
        ]);

        $failedResp->assertStatus(200)->assertJsonPath('status', 'not_success');
        $this->assertDatabaseMissing('orders', ['reference' => $failedRef]);
        Mail::assertNothingSent();

        // Cas 4 : Anti-doublon (webhook + verify + ré-exécution du job)
        $page = Page::create(['title' => 'Boutique', 'slug' => 'boutique-2', 'template' => 'boutique']);
        $section = Section::create(['page_id' => $page->id, 'title' => 'Livres', 'order' => 1]);
        $product = Subsection::create([
            'section_id' => $section->id,
            'title' => 'Livre Kabbale',
            'type' => 'produit',
            'prix' => 8000,
            'order' => 1,
        ]);

        $reference = 'DEDUP_REF_' . uniqid();
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 800000,
                'currency' => 'XOF',
                'status' => 'success',
                'customer' => ['email' => 'unique@test.com'],
                'metadata' => [
                    'type' => 'cart',
                    'nom' => 'Cohen',
                    'prenom' => 'Eli',
                    'custom_fields' => [
                        'cart_details' => [
                            ['product_id' => $product->id, 'title' => $product->title, 'price' => 8000, 'quantity' => 1],
                        ],
                    ],
                ],
            ],
        ];

        $this->sendSignedWebhook($payload)->assertStatus(200);
        $this->sendSignedWebhook($payload)->assertStatus(200)->assertJsonPath('status', 'already_processed');
        $this->getJson("/api/payments/verify/{$reference}")->assertStatus(200)->assertJsonPath('message', 'Déjà traité');

        $order = Order::where('reference', $reference)->firstOrFail();
        SendLibraryResourcesJob::dispatchAfterResponse($order->fresh());

        Mail::assertSent(OrderConfirm::class, 1);
    }

    /**
     * TÂCHE 02 — Cas 1 à 4 : Enregistrement et affichage des demandes de prière et des Tsedakas dans le back-office
     */
    public function test_tache_02_prayer_requests_and_tsedakas_are_persisted_and_visible_in_backoffice()
    {
        Mail::fake();

        $admin = User::create([
            'name' => 'Admin Backoffice',
            'pseudo' => 'admin_bo',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);
        Sanctum::actingAs($admin);

        // 1. Paiement d'une demande de prière (3 000 FCFA) + Tsedaka en bas de formulaire (2 000 FCFA)
        $prayerRef = 'PR_BO_' . uniqid();
        $this->sendSignedWebhook([
            'event' => 'charge.success',
            'data' => [
                'reference' => $prayerRef,
                'amount' => 500000, // 5 000 FCFA (3 000 prière + 2 000 tsedaka)
                'currency' => 'XOF',
                'status' => 'success',
                'customer' => ['email' => 'fidele@test.com'],
                'metadata' => [
                    'type' => 'prayer-request',
                    'nom' => 'Yao',
                    'prenom' => 'Jean',
                    'numero' => '0708091011',
                    'custom_fields' => [
                        'objet' => 'Protection familiale',
                        'message' => 'Soumettez-nous votre préoccupation détaillée',
                        'montant' => 3000,
                        'montant_tsedaka' => 2000,
                    ],
                ],
            ],
        ])->assertStatus(200);

        // 2. Paiement d'un don Tsedaka direct (4 500 FCFA)
        $tsedakaRef = 'TS_BO_' . uniqid();
        $this->sendSignedWebhook([
            'event' => 'charge.success',
            'data' => [
                'reference' => $tsedakaRef,
                'amount' => 450000, // 4 500 FCFA
                'currency' => 'XOF',
                'status' => 'success',
                'customer' => ['email' => 'donateur@test.com'],
                'metadata' => [
                    'type' => 'tsedaka',
                    'nom' => 'Konan',
                    'prenom' => 'Aya',
                    'numero' => '0102030405',
                    'custom_fields' => [
                        'montant' => 4500,
                        'anonymous' => false,
                        'message' => 'Soutien aux œuvres de la communauté',
                    ],
                ],
            ],
        ])->assertStatus(200);

        // Vérification dans l'API Back-Office : Demandes de prière
        $prayersResp = $this->getJson('/api/prayer-requests');
        $prayersResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.nom', 'Yao')
            ->assertJsonPath('data.0.prenom', 'Jean')
            ->assertJsonPath('data.0.objet', 'Protection familiale')
            ->assertJsonPath('data.0.message', 'Soumettez-nous votre préoccupation détaillée')
            ->assertJsonPath('data.0.transactions.0.reference', $prayerRef);

        // Vérification dans l'API Back-Office : Tsedakas (le don direct + la Tsedaka issue du formulaire de prière)
        $tsedakasResp = $this->getJson('/api/tsedakas');
        $tsedakasResp->assertStatus(200)
            ->assertJsonPath('success', true);

        $tsedakaReferences = collect($tsedakasResp->json('data'))->pluck('reference')->all();
        $this->assertContains($tsedakaRef, $tsedakaReferences);
        $this->assertContains($prayerRef . '-TSEDAKA', $tsedakaReferences);

        Mail::assertSent(PrayerRequestValidated::class);
        Mail::assertSent(TsedakaThankYou::class);
    }

    /**
     * TÂCHE 03 — Cas 1 à 5 : Validation stricte du montant minimum de 2 000 FCFA pour Prière et Don
     */
    public function test_tache_03_minimum_amount_2000_fcfa_validation_for_prayer_and_tsedaka()
    {
        $capturedAmounts = [];
        Http::fake(function ($request) use (&$capturedAmounts) {
            $data = $request->data();
            $capturedAmounts[] = $data['amount'] ?? null;
            return Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/test-min-2000'],
            ], 200);
        });

        // Cas 1 : Montant 1000 -> refusé (Prière & Don)
        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'objet' => 'Demande',
            'message' => 'Ma préoccupation',
            'montant' => 1000,
            'totalPrice' => 1000,
            'type' => 'prayer-request',
        ])->assertStatus(422);

        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'montant' => 1000,
            'totalPrice' => 1000,
            'anonymous' => false,
            'type' => 'tsedaka',
        ])->assertStatus(422);

        // Cas 2 : Montant 1999 -> refusé (Prière & Don)
        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'objet' => 'Demande',
            'message' => 'Ma préoccupation',
            'montant' => 1999,
            'totalPrice' => 1999,
            'type' => 'prayer-request',
        ])->assertStatus(422);

        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'montant' => 1999,
            'totalPrice' => 1999,
            'anonymous' => false,
            'type' => 'tsedaka',
        ])->assertStatus(422);

        // Cas 3 : Montant 2000 -> accepté (Prière & Don)
        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'objet' => 'Demande',
            'message' => 'Ma préoccupation',
            'montant' => 2000,
            'totalPrice' => 2000,
            'type' => 'prayer-request',
        ])->assertStatus(200);

        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'montant' => 2000,
            'totalPrice' => 2000,
            'anonymous' => false,
            'type' => 'tsedaka',
        ])->assertStatus(200);

        // Cas 4 : Montant supérieur à 2000 (ex: Prière 3500 + Tsedaka 2500 = 6000) -> accepté
        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'objet' => 'Demande',
            'message' => 'Ma préoccupation',
            'montant' => 3500,
            'montant_tsedaka' => 2500,
            'totalPrice' => 6000,
            'type' => 'prayer-request',
        ])->assertStatus(200);

        // Vérifier que les montants transmis à Paystack (en centimes) sont exacts : 2000*100, 2000*100, 6000*100
        $this->assertEquals([200000, 200000, 600000], $capturedAmounts);

        // Cas 5 : Tentative de contournement (totalPrice différent de montant + montant_tsedaka ou négatif) -> rejetée
        $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'objet' => 'Demande',
            'message' => 'Ma préoccupation',
            'montant' => 5000,
            'totalPrice' => 2000,
            'type' => 'prayer-request',
        ])->assertStatus(422);
    }

    /**
     * TÂCHE 06 — Compteur de visiteurs uniques (pas d'incrémentation abusive au rafraîchissement)
     */
    public function test_tache_06_unique_visitor_tracking_deduplicates_same_visitor()
    {
        // Premier appel d'un visiteur
        $this->postJson('/api/track', ['visitor_id' => 'visitor-alpha-123'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        // Second appel du même visiteur (rafraîchissement ou changement de page)
        $this->postJson('/api/track', ['visitor_id' => 'visitor-alpha-123'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'already_tracked');

        $this->assertEquals(1, DailyVisit::sum('count'));

        // Appel d'un second visiteur distinct
        $this->postJson('/api/track', ['visitor_id' => 'visitor-beta-456'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        $this->assertEquals(2, DailyVisit::sum('count'));
    }
}
