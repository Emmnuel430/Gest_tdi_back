<?php

namespace Tests\Feature;

use App\Mail\TsedakaThankYou;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaystackWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_request_without_signature()
    {
        $response = $this->postJson('/api/payments/webhook', [
            'event' => 'charge.success',
            'data' => ['reference' => 'TEST_REF_123'],
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_rejects_request_with_invalid_signature()
    {
        config(['services.paystack.secret_key' => 'secret_test_key']);

        $response = $this->call(
            'POST',
            '/api/payments/webhook',
            [],
            [],
            [],
            ['HTTP_X_PAYSTACK_SIGNATURE' => 'invalid_signature_hash'],
            json_encode(['event' => 'charge.success'])
        );

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_valid_signature_and_processes_tsedaka_donation()
    {
        Mail::fake();

        $secret = 'test_secret_key_12345';
        config(['services.paystack.secret_key' => $secret]);

        $reference = 'TS_TEST_'.uniqid();
        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 100000, // 1000 XOF
                'currency' => 'XOF',
                'status' => 'success',
                'customer' => ['email' => 'donor@test.com'],
                'metadata' => [
                    'type' => 'tsedaka',
                    'nom' => 'Kouame',
                    'prenom' => 'Michel',
                    'numero' => '07070707',
                    'custom_fields' => [
                        'montant' => 1000,
                        'anonymous' => false,
                        'message' => 'Pour la communauté',
                    ],
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $signature = hash_hmac('sha512', $rawBody, $secret);

        $response = $this->call(
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

        $response->assertStatus(200);

        // Vérification de la création de transaction et du don
        $this->assertDatabaseHas('transactions', [
            'reference' => $reference,
            'status' => 'success',
            'type' => 'tsedaka',
        ]);

        $this->assertDatabaseHas('tsedakas', [
            'reference' => $reference,
            'email' => 'donor@test.com',
            'montant' => 1000,
        ]);

        Mail::assertSent(TsedakaThankYou::class);
    }
}
