<?php

namespace Tests\Feature;

use App\Mail\PrayerRequestValidated;
use App\Models\Adherent;
use App\Models\Content;
use App\Models\GalerieDossier;
use App\Models\GalerieImage;
use App\Models\Media;
use App\Models\Order;
use App\Models\Page;
use App\Models\PrayerRequest;
use App\Models\Section;
use App\Models\SubscriptionPlan;
use App\Models\Subsection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicalDebtNonRegressionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dette #01 : Rejet de toute falsification du prix total côté client dans /api/payments/initiate
     */
    public function test_payment_initiate_rejects_tampered_cart_price_and_accepts_exact_server_price()
    {
        $page = Page::create(['title' => 'Boutique', 'slug' => 'boutique', 'template' => 'boutique']);
        $section = Section::create(['page_id' => $page->id, 'title' => 'Livres', 'order' => 1]);
        $product = Subsection::create([
            'section_id' => $section->id,
            'title' => 'Siddour',
            'type' => 'produit',
            'prix' => 15000,
            'order' => 1,
        ]);

        // Tentative de fraude : achat de 2 articles à 15 000 XOF en envoyant totalPrice = 100
        $tamperedResponse = $this->postJson('/api/payments/initiate', [
            'nom' => 'Client Test',
            'email' => 'client@test.com',
            'numero' => '0700000000',
            'commune' => 'Cocody',
            'type' => 'cart',
            'totalPrice' => 100,
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $tamperedResponse->assertStatus(422)
            ->assertJsonFragment(['message' => 'Montant du paiement invalide ou falsifié.']);

        // Requête légitime : 2 * 15 000 = 30 000 XOF
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/valid-session'],
            ], 200),
        ]);

        $validResponse = $this->postJson('/api/payments/initiate', [
            'nom' => 'Client Test',
            'email' => 'client@test.com',
            'numero' => '0700000000',
            'commune' => 'Cocody',
            'type' => 'cart',
            'totalPrice' => 30000,
            'cart' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $validResponse->assertStatus(200)
            ->assertJsonPath('authorization_url', 'https://checkout.paystack.com/valid-session');
    }

    /**
     * Dette #02 : Aucun hash de mot de passe ne doit être envoyé dans les métadonnées Paystack
     */
    public function test_subscription_initiation_never_sends_password_hash_to_paystack()
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Membre Actif',
            'billing_type' => 'one_time',
            'price' => 25000,
            'duration_months' => 12,
        ]);

        $capturedPayload = null;
        Http::fake(function ($request) use (&$capturedPayload) {
            $capturedPayload = $request->data();
            return Http::response([
                'status' => true,
                'data' => ['authorization_url' => 'https://checkout.paystack.com/sub-test'],
            ], 200);
        });

        $response = $this->postJson('/api/payments/initiate', [
            'nom' => 'Cohen',
            'prenom' => 'David',
            'email' => 'david@test.com',
            'numero' => '0700000001',
            'password' => 'SecretPass123!',
            'subscription_plan_id' => $plan->id,
            'payment_step' => 'full',
            'type' => 'subscription',
            'totalPrice' => 25000,
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($capturedPayload);
        $this->assertArrayNotHasKey('password', $capturedPayload['metadata'] ?? []);

        $reference = $response->json('reference');
        $this->assertNotNull(Cache::get("pending_sub_pwd_{$reference}"));
    }

    /**
     * Dette #03 : Contrôle RBAC sur la suppression d'utilisateurs et interdiction d'auto-suppression
     */
    public function test_user_deletion_enforces_rbac_and_prevents_self_deletion()
    {
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'pseudo' => 'root_admin',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        $staffUser = User::create([
            'name' => 'Staff User',
            'pseudo' => 'staff_member',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        $targetUser = User::create([
            'name' => 'Target User',
            'pseudo' => 'target_member',
            'password' => Hash::make('password123'),
            'role' => 'staff',
        ]);

        // 1. Un utilisateur non-admin (staff) ne peut pas supprimer un compte même en passant ?user_id=1
        Sanctum::actingAs($staffUser);
        $forbidden = $this->deleteJson("/api/delete_user/{$targetUser->id}?user_id={$superAdmin->id}");
        $forbidden->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $targetUser->id]);

        // 2. Un super_admin ne peut pas supprimer son propre compte
        Sanctum::actingAs($superAdmin);
        $selfDelete = $this->deleteJson("/api/delete_user/{$superAdmin->id}");
        $selfDelete->assertStatus(422);

        // 3. Un super_admin peut supprimer un autre compte légitimement
        $deleted = $this->deleteJson("/api/delete_user/{$targetUser->id}");
        $deleted->assertStatus(200)->assertJsonPath('status', 'deleted');
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);
    }

    /**
     * Dette #06 : Filtrage des sous-sections programmées dans le futur sur GET /api/page/{id}
     */
    public function test_public_page_by_id_hides_scheduled_future_subsections()
    {
        $page = Page::create(['title' => 'Accueil', 'slug' => 'accueil', 'template' => 'accueil']);
        $section = Section::create(['page_id' => $page->id, 'title' => 'Actualités', 'order' => 1]);

        $publishedSub = Subsection::create([
            'section_id' => $section->id,
            'title' => 'Article Publié',
            'type' => 'classique',
            'publish_at' => now()->subHour(),
            'order' => 1,
        ]);

        $futureSub = Subsection::create([
            'section_id' => $section->id,
            'title' => 'Article Secret Futur',
            'type' => 'classique',
            'publish_at' => now()->addDays(5),
            'order' => 2,
        ]);

        // Appel public non authentifié
        $publicResponse = $this->getJson("/api/page/{$page->id}");
        $publicResponse->assertStatus(200);
        $subsectionIds = collect($publicResponse->json('sections.0.subsections'))->pluck('id')->all();

        $this->assertContains($publishedSub->id, $subsectionIds);
        $this->assertNotContains($futureSub->id, $subsectionIds);
    }

    /**
     * Dette #07 & #09 : Enregistrement de prières avec le même objet et route POST /prayer-requests/{id}/validate
     */
    public function test_duplicate_prayer_subject_is_not_dropped_and_validate_route_works()
    {
        Mail::fake();

        $prayer1 = PrayerRequest::create([
            'nom' => 'Lévy',
            'prenom' => 'Sarah',
            'email' => 'sarah@test.com',
            'objet' => 'Prière de guérison',
            'message' => 'Première demande',
            'is_validated' => false,
        ]);

        $admin = User::create([
            'name' => 'Admin',
            'pseudo' => 'admin_prayer',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);

        Sanctum::actingAs($admin);

        // Validation via la route Dette #09
        $validateResp = $this->postJson("/api/prayer-requests/{$prayer1->id}/validate");
        $validateResp->assertStatus(200)->assertJsonPath('success', true);
        $this->assertDatabaseHas('prayer_requests', [
            'id' => $prayer1->id,
            'is_validated' => true,
        ]);
        Mail::assertSent(PrayerRequestValidated::class);

        // Dette #07 : Seconde demande de prière avec le même email et le même objet via webhook Paystack
        $secret = 'test_secret_prayer';
        config(['services.paystack.secret_key' => $secret]);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PR_SECOND_' . uniqid(),
                'amount' => 500000,
                'currency' => 'XOF',
                'status' => 'success',
                'customer' => ['email' => 'sarah@test.com'],
                'metadata' => [
                    'type' => 'prayer-request',
                    'nom' => 'Lévy',
                    'prenom' => 'Sarah',
                    'numero' => '07070707',
                    'custom_fields' => [
                        'objet' => 'Prière de guérison',
                        'message' => 'Seconde demande un mois plus tard',
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha512', $rawBody, $secret);

        $webhookResp = $this->call(
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

        $webhookResp->assertStatus(200);
        $this->assertEquals(
            2,
            PrayerRequest::where('email', 'sarah@test.com')->where('objet', 'Prière de guérison')->count()
        );
    }

    /**
     * Dette #10, #11 & #12 : Pagination des endpoints, visibilité de la galerie publique et protection des médias
     */
    public function test_pagination_gallery_visibility_and_media_deletion_guard()
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Admin',
            'pseudo' => 'admin_media',
            'password' => Hash::make('password123'),
            'role' => 'super_admin',
        ]);
        Sanctum::actingAs($admin);

        // Dette #11 : Galerie publique filtre les dossiers et images non visibles
        $visibleDossier = GalerieDossier::create(['nom' => 'Fête Publique', 'is_visible' => true]);
        $hiddenDossier = GalerieDossier::create(['nom' => 'Archive Privée', 'is_visible' => false]);

        $media = Media::create([
            'file_name' => 'photo.jpg',
            'file_path' => 'galerie/photo.jpg',
            'file_type' => 'image/jpeg',
            'hash' => md5('photo1'),
        ]);

        GalerieImage::create([
            'dossier_id' => $visibleDossier->id,
            'media_id' => $media->id,
            'titre' => 'Photo 1',
            'is_visible' => true,
            'ordre' => 1,
        ]);

        $publicGallery = $this->getJson('/api/public/galerie/dossiers');
        $publicGallery->assertStatus(200);
        $returnedNames = collect($publicGallery->json('data'))->pluck('nom')->all();
        $this->assertContains('Fête Publique', $returnedNames);
        $this->assertNotContains('Archive Privée', $returnedNames);

        // Dette #12 : Media rattaché à 1 seul dossier ne doit PAS pouvoir être supprimé par deleteMedia
        $deleteBlocked = $this->deleteJson("/api/media/{$media->id}");
        $deleteBlocked->assertStatus(400);
        $this->assertDatabaseHas('media', ['id' => $media->id]);

        // Dette #10 : Pagination sur /api/orders et /api/contents
        Order::create([
            'reference' => 'ORD_1',
            'nom' => 'Client 1',
            'email' => 'c1@test.com',
        ]);
        Content::create([
            'title' => 'Cours 1',
            'type' => 'cours',
            'is_public' => true,
        ]);

        $ordersResp = $this->getJson('/api/orders?per_page=10');
        $ordersResp->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);

        $contentsResp = $this->getJson('/api/contents?per_page=10');
        $contentsResp->assertStatus(200)
            ->assertJsonStructure(['current_page', 'data', 'total']);
    }

    /**
     * Dette #04, #05 & #23 : Rate limiting sur /api/login, origines CORS explicites, et cookie HttpOnly Sanctum
     */
    public function test_auth_cookie_cors_and_login_rate_limiting()
    {
        // Dette #05 : Aucune origine wildcard '*' lorsque supports_credentials = true
        $allowedOrigins = config('cors.allowed_origins');
        $this->assertNotContains('*', $allowedOrigins);
        $this->assertTrue(config('cors.supports_credentials'));

        $admin = User::create([
            'name' => 'Admin Cookie',
            'pseudo' => 'cookie_admin',
            'password' => Hash::make('ValidPass123!'),
            'role' => 'super_admin',
        ]);

        // Dette #23 : Login émet un cookie HttpOnly auth_token
        $loginResp = $this->postJson('/api/login', [
            'pseudo' => 'cookie_admin',
            'password' => 'ValidPass123!',
        ]);
        $loginResp->assertStatus(200)
            ->assertCookie('auth_token');

        $token = $loginResp->json('token');

        // Dette #23 : AttachTokenFromCookie permet d'authentifier /api/user via le cookie seul
        $meResp = $this->withCredentials()
            ->withUnencryptedCookie('auth_token', $token)
            ->getJson('/api/user');
        $meResp->assertStatus(200)
            ->assertJsonPath('user.id', $admin->id);

        // Dette #04 : Rate limiting (throttle:5,1) bloque au-delà de 5 tentatives / minute
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/adherent/login', [
                'email' => 'brute@test.com',
                'password' => 'wrong',
            ]);
        }

        $throttled = $this->postJson('/api/adherent/login', [
            'email' => 'brute@test.com',
            'password' => 'wrong',
        ]);
        $throttled->assertStatus(429);
    }
}
