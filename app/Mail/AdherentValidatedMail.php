<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Adherent;

class AdherentValidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $adherent;
    public function __construct($adherent)
    {
        $this->adherent = $adherent;
    }

    public function build()
    {
        return $this->subject('Votre compte est activé')
            ->view('emails.adherents.adherent_validated');
    }
}
