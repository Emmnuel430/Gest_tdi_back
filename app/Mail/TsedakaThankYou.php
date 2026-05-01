<?php

namespace App\Mail;

use App\Models\Tsedaka;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TsedakaThankYou extends Mailable
{
    use Queueable, SerializesModels;

    public $tsedaka;

    public function __construct(Tsedaka $tsedaka)
    {
        $this->tsedaka = $tsedaka;
    }

    public function build()
    {
        return $this
            ->subject("Merci pour votre don 🙏")
            ->view('emails.tsedaka.thankyou')
            ->text('emails.tsedaka.thankyou_plain');
    }
}
