<?php

namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\PrayerRequest;

class SubscriptionWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adherent;
    public $plan;
    public $subscription;

    public function __construct($adherent, $plan, $subscription)
    {
        $this->adherent = $adherent;
        $this->plan = $plan;
        $this->subscription = $subscription;
    }

    public function build()
    {
        return $this->subject('Bienvenue ! Votre abonnement est activé 🎉')
            ->view('emails.subscriptions.subscription-welcome');
    }
}
