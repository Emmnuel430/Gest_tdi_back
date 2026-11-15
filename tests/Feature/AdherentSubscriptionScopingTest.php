<?php

namespace Tests\Feature;

use App\Models\Adherent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdherentSubscriptionScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_never_returns_completed_subscription_of_another_adherent()
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Plan Test',
            'billing_type' => 'one_time',
            'price' => 10000,
            'duration_months' => 1,
        ]);

        // Adhérent A : aucun abonnement
        $adherentA = Adherent::create([
            'nom' => 'Adhérent',
            'prenom' => 'A',
            'email' => 'a@test.com',
            'is_active' => true,
        ]);

        // Adhérent B : abonnement 'completed'
        $adherentB = Adherent::create([
            'nom' => 'Adhérent',
            'prenom' => 'B',
            'email' => 'b@test.com',
            'is_active' => true,
        ]);

        Subscription::create([
            'adherent_id' => $adherentB->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'completed',
            'starts_at' => now()->subDays(60),
            'ends_at' => now()->subDays(30),
        ]);

        // Vérification cruciale : adherentA ne doit PAS hériter de l'abonnement de adherentB
        $this->assertNull($adherentA->activeSubscription);
    }
}
