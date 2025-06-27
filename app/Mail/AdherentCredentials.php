<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Adherent;

class AdherentCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public $adherent;
    public $passwordClair;

    public function __construct(Adherent $adherent, $passwordClair)
    {
        $this->adherent = $adherent;
        $this->passwordClair = $passwordClair;
    }

    public function build()
    {
        return $this->subject("Vos identifiants d'accès à la plateforme")
            ->view('emails.adherents.credentials')
            ->text('emails.adherents.credentials_plain');
    }
}
