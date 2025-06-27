<?php

namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\PrayerRequest;

class PrayerRequestValidated extends Mailable
{
    use Queueable, SerializesModels;

    public $prayer;

    public function __construct(PrayerRequest $prayer)
    {
        $this->prayer = $prayer;
    }

    public function build()
    {
        return $this
            ->subject("Votre demande de prière a été acceptée")
            ->view('emails.prayers.validated')
            ->text('emails.prayers.validated_plain');
    }
}
