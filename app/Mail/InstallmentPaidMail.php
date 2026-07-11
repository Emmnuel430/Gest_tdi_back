<?php

namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InstallmentPaidMail extends Mailable
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
        return $this->subject('Paiement reçu — mensualité enregistrée')
            ->view('emails.subscriptions.installment-paid');
    }
}
