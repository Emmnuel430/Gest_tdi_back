<?php

namespace App\Actions;

use App\Models\{Adherent, Content, SubscriptionPlan, Subscription};
use App\Mail\SubscriptionWelcomeMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class HandleSubscription
{

    public function execute($transaction, $metadata)
    {
        $customFields = $metadata['custom_fields'] ?? [];
        $plan = SubscriptionPlan::findOrFail($customFields['subscription_plan_id']);

        $adherent = Adherent::firstOrCreate(
            ['email' => $metadata['email']],
            [
                'nom' => $metadata['nom'],
                'prenom' => $metadata['prenom'],
                'contact' => $metadata['numero'],
                'password' => $metadata['password'], // deja hachée
                'is_active' => true,
            ]
        );

        $subscription = $adherent->subscriptions()
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'active')
            ->first() ?? Subscription::createFromPlan($adherent, $plan);

        // AJOUT : On vide tout le cache lié aux contenus de cet utilisateur
        // Suppression manuelle des clés car le driver 'file' ne supporte pas les tags
        foreach (Content::getAvailableTypes() as $type) {
            Cache::forget("contents_user_{$adherent->id}_type_{$type}");
        }

        // Mise à jour transaction
        $transaction->payment_step = in_array($customFields['payment_step'] ?? 'full', ['registration', 'monthly', 'full'])
            ? $customFields['payment_step'] : 'full';

        $transaction->payment_index = is_numeric($customFields['payment_index'] ?? null)
            ? (int) $customFields['payment_index'] : null;

        $transaction->transactionable()->associate($subscription)->save();

        dispatch(fn() => Mail::to($adherent->email)->send(new SubscriptionWelcomeMail($adherent, $plan, $subscription)));
    }
}
