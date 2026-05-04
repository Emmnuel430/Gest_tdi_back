<?php

namespace App\Actions;

use App\Models\{Adherent, Content, SubscriptionPlan, Subscription};
use App\Mail\SubscriptionWelcomeMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

class HandleSubscription
{
    private function error($message, $code = 400)
    {
        return [
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ];
    }

    public function execute($transaction, $metadata)
    {
        $custom = $metadata['custom_fields'] ?? [];

        $plan = SubscriptionPlan::find($custom['subscription_plan_id'] ?? null);

        if (!$plan) {
            return $this->error('Plan introuvable');
        }

        $paymentStep = $custom['payment_step'] ?? 'full';

        // 1. On cherche l'adhérent
        $adherent = Adherent::where('email', $metadata['email'])->first();

        // 2. S'il n'existe pas, on le crée proprement avec toutes les infos
        if (!$adherent) {
            $adherent = Adherent::create([
                'email' => $metadata['email'],
                'nom' => $metadata['nom'],
                'prenom' => $metadata['prenom'],
                'contact' => $metadata['numero'],
                'password' => $metadata['password'], // Ici le password est requis par tes rules front
                'is_active' => true,
            ]);
        }
        // 3. S'il existe déjà, on NE TOUCHE PAS au password
        else {
            $adherent->update([
                'nom' => $metadata['nom'],
                'prenom' => $metadata['prenom'],
                'contact' => $metadata['numero'],
            ]);
        }


        // compte désactivé
        if (!$adherent->is_active) {
            return $this->error('Compte désactivé', 403);
        }

        $subscription = $adherent->subscriptions()
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'active')
            ->first();

        /**
         * =========================
         * REGISTRATION
         * =========================
         */
        if ($paymentStep === 'registration') {

            if ($subscription) {
                return $this->error('Abonnement déjà existant');
            }

            $subscription = Subscription::createFromPlan($adherent, $plan);

            $subscription->update([
                'next_payment_at' => now()->addMonth()
            ]);
        }

        /**
         * =========================
         * INSTALLMENT (étudiant)
         * =========================
         */ elseif ($paymentStep === 'installment') {
            if (!$subscription)
                return $this->error('Aucun abonnement actif');
            if (!$plan->is_student_plan)
                return $this->error('Plan non étudiant');

            if ($subscription->remaining_months <= 0) {
                return $this->error('Mensualités déjà soldées');
            }

            // 1. Calcul de la nouvelle date (Logique identique au Monthly)
            $baseDate = $subscription->next_payment_at && $subscription->next_payment_at > now()
                ? \Carbon\Carbon::parse($subscription->next_payment_at)
                : now();

            $newDate = $baseDate->copy()->addMonth();

            // 2. Décrémentation et mise à jour
            $subscription->decrement('remaining_months');

            $subscription->update([
                'next_payment_at' => $newDate,
                // On ne change pas le status ici, sauf si c'est fini
            ]);

            // 3. Finalisation si c'était la dernière traite
            if ($subscription->remaining_months <= 0) {
                $subscription->update([
                    'status' => 'completed',
                    'next_payment_at' => null,
                    'ends_at' => $newDate // La fin de l'abonnement est la fin du dernier mois payé
                ]);
            }
        }


        /**
         * =========================
         * MONTHLY
         * =========================
         */ elseif ($paymentStep === 'monthly') {

            if (!$subscription) {
                return $this->error('Aucun abonnement actif');
            }

            $baseDate = $subscription->next_payment_at?->isFuture()
                ? $subscription->next_payment_at
                : now();

            $newDate = $baseDate->copy()->addMonth();

            $subscription->update([
                'ends_at' => $newDate,
                'next_payment_at' => $newDate
            ]);
        }

        /**
         * =========================
         * FULL
         * =========================
         */ elseif ($paymentStep === 'full') {

            if ($subscription) {
                return $this->error('Abonnement déjà existant');
            }

            $subscription = Subscription::createFromPlan($adherent, $plan);

            $subscription->update([
                'status' => 'completed',
                'remaining_months' => 0,
                'next_payment_at' => null
            ]);
        }

        /**
         * 🎯 Transaction link
         */
        $transaction->update([
            'payment_step' => $paymentStep,
            'payment_index' => is_numeric($custom['payment_index'] ?? null)
                ? (int) $custom['payment_index']
                : null,
        ]);

        $transaction->transactionable()->associate($subscription)->save();

        return [
            'status' => 'success',
            'subscription_id' => $subscription->id
        ];
    }
}
