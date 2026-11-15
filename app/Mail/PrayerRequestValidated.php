<?php

namespace App\Mail;

use App\Models\PrayerRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

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
            ->subject('Votre demande de prière a été reçue')
            ->view('emails.prayers.validated')
            ->text('emails.prayers.validated_plain');
    }
}
