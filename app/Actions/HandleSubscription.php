<?php

namespace App\Actions;

use App\Mail\InstallmentPaidMail;
use App\Mail\SubscriptionCompletedMail;
use App\Mail\SubscriptionRenewedMail;
use App\Mail\SubscriptionWelcomeMail;
use App\Models\Adherent;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class HandleSubscription
{
    private function error($message, $code = 400)
    {
        return [
            'status' => 'error',
            'message' => $message,
            'code' => $code,
        ];
    }

    /*
        registration => Paiement inscription (étudiant)
        installment => Paiement d'une mensualité (étudiant)
        monthly => Paiement mensuel (abonnement classique)
        full => Paiement complet (abonnement classique, ou one-time payment)
    */
    public function execute($transaction, $metadata)
    {
        // Étape 1 : récupérer le plan acheté
        $custom = $metadata['custom_fields'] ?? [];
        $planId = $custom['subscription_plan_id'] ?? $metadata['subscription_plan_id'] ?? null;
        $plan = SubscriptionPlan::find($planId);

        if (! $plan) {
            return $this->error('Plan introuvable');
        }

        // Étape 2 : déterminer quel type de paiement c'est
        $paymentStep = $custom['payment_step'] ?? $metadata['payment_step'] ?? 'full';

        // Étape 3 : retrouver l'adhérent
        $adherent = Adherent::where('email', $metadata['email'])->first();

        // *S'il n'existe pas, on le crée proprement avec toutes les infos (sans exposer le hash à Paystack)
        if (! $adherent) {
            $cachedPassword = Cache::pull("pending_sub_pwd_{$transaction->reference}")
                ?? Cache::pull('pending_sub_pwd_email_'.md5(strtolower($metadata['email'] ?? '')))
                ?? ($metadata['password'] ?? bcrypt(Str::random(16)));

            $adherent = Adherent::create([
                'email' => $metadata['email'],
                'nom' => $metadata['nom'],
                'prenom' => $metadata['prenom'],
                'contact' => $metadata['numero'] ?? null,
                'password' => $cachedPassword,
                'is_active' => true,
            ]);
        }
        // *S'il existe déjà, on l'update et ON NE TOUCHE PAS au password
        else {
            $adherent->update([
                'nom' => $metadata['nom'],
                'prenom' => $metadata['prenom'],
                'contact' => $metadata['numero'] ?? $adherent->contact,
            ]);
        }

        // Étape 4 : vérifier que le compte est actif
        if (! $adherent->is_active) {
            return $this->error('Compte désactivé', 403);
        }

        // Étape 5 : rechercher un abonnement actif
        $subscription = $adherent->subscriptions()
            ->where('subscription_plan_id', $plan->id)
            ->where('status', 'active')
            ->first();

        // REGISTRATION
        if ($paymentStep === 'registration') {
            if ($subscription) {
                return $this->error('Abonnement déjà existant');
            }

            $subscription = Subscription::createFromPlan($adherent, $plan);

            $subscription->update([
                'next_payment_at' => now()->addMonth(),
            ]);
            // --- Envoi du mail de bienvenue après la création du premier paiement (registration)
            try {
                Mail::to($adherent->email)->send(new SubscriptionWelcomeMail($adherent, $plan, $subscription));
            } catch (\Throwable $e) {
                // Ne pas faire échouer le flux de paiement pour une erreur d'envoi de mail
                \Log::warning('Welcome mail failed: '.$e->getMessage());
            }
        }

        // INSTALLMENT (étudiant)
        elseif ($paymentStep === 'installment') {
            if (! $subscription) {
                return $this->error('Aucun abonnement actif');
            }
            if (! $plan->is_student_plan) {
                return $this->error('Plan non étudiant');
            }

            if ($subscription->remaining_months <= 0) {
                return $this->error('Mensualités déjà soldées');
            }

            // 1. Calcul de la nouvelle date (Logique identique au Monthly)
            $baseDate = $subscription->next_payment_at && $subscription->next_payment_at > now()
                ? \Carbon\Carbon::parse($subscription->next_payment_at)
                : now();

            $newDate = $baseDate->copy()->addMonth();

            // On ne peut pas dépasser la date de fin de l'abonnement (ends_at)
            $maxNextPayment = \Carbon\Carbon::parse($subscription->ends_at)
                ->copy()
                ->subMonth();

            if ($newDate->greaterThan($maxNextPayment)) {
                $newDate = $maxNextPayment;
            }
            // 2. Décrémentation et mise à jour
            $subscription->decrement('remaining_months');

            $subscription->update([
                'next_payment_at' => $newDate,
                // On ne change pas le status ici, sauf si c'est fini
            ]);

            // --- Notification: paiement d'une mensualité (installment)
            try {
                Mail::to($adherent->email)->send(new InstallmentPaidMail($adherent, $plan, $subscription));
            } catch (\Throwable $e) {
                \Log::warning('Installment mail failed: '.$e->getMessage());
            }

            // 3. Finalisation si c'était la dernière traite
            if ($subscription->remaining_months <= 0) {
                $subscription->update([
                    'status' => 'completed',
                    'next_payment_at' => null,
                    'ends_at' => $newDate, // La fin de l'abonnement est la fin du dernier mois payé
                ]);
                // --- Notification: toutes les mensualités sont soldées
                try {
                    Mail::to($adherent->email)->send(new SubscriptionCompletedMail($adherent, $plan, $subscription));
                } catch (\Throwable $e) {
                    \Log::warning('Installment completion mail failed: '.$e->getMessage());
                }
            }
        }

        // MONTHLY
        elseif ($paymentStep === 'monthly') {
            if (! $subscription) {
                return $this->error('Aucun abonnement actif');
            }

            $baseDate = $subscription->next_payment_at?->isFuture()
                ? $subscription->next_payment_at
                : now();

            $newDate = $baseDate->copy()->addMonth();

            $subscription->update([
                'ends_at' => $newDate,
                'next_payment_at' => $newDate,
            ]);
            // --- Notification: renouvellement mensuel
            try {
                Mail::to($adherent->email)->send(new SubscriptionRenewedMail($adherent, $plan, $subscription));
            } catch (\Throwable $e) {
                \Log::warning('Monthly renewal mail failed: '.$e->getMessage());
            }
        }

        // FULL
        elseif ($paymentStep === 'full') {
            if ($subscription) {
                return $this->error('Abonnement déjà existant');
            }

            $subscription = Subscription::createFromPlan($adherent, $plan);

            $subscription->update([
                'status' => 'active',
                'remaining_months' => 0,
                'next_payment_at' => null,
            ]);

            // --- Envoi du mail de bienvenue après paiement complet (full)
            try {
                Mail::to($adherent->email)->send(new SubscriptionWelcomeMail($adherent, $plan, $subscription));
            } catch (\Throwable $e) {
                \Log::warning('Welcome mail (full) failed: '.$e->getMessage());
            }
        }

        // Transaction link
        $transaction->update([
            'payment_step' => $paymentStep,
            'payment_index' => is_numeric($custom['payment_index'] ?? null)
                ? (int) $custom['payment_index']
                : null,
        ]);

        $transaction->transactionable()->associate($subscription)->save();

        return [
            'status' => 'success',
            'subscription_id' => $subscription->id,
        ];
    }
}
