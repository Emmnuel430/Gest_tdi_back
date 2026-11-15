<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCompletedMail extends Mailable
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
        return $this->subject('Abonnement soldé — Merci')
            ->view('emails.subscriptions.subscription-completed');
    }
}
