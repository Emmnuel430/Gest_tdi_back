<?php
namespace App\Actions;

use App\Models\PrayerRequest;
use App\Mail\PrayerRequestValidated;
use Illuminate\Support\Facades\Mail;

class HandlePrayerRequest
{
    public function execute($transaction, $metadata)
    {
        $customFields = $metadata['custom_fields'] ?? [];

        $already = PrayerRequest::where('email', $transaction->email)
            ->where('objet', $customFields['objet'] ?? null)
            ->exists();

        if (!$already) {
            $prayer = PrayerRequest::create([
                'nom' => $metadata['nom'] ?? null,
                'prenom' => $metadata['prenom'] ?? null,
                'email' => $transaction->email,
                'objet' => $customFields['objet'] ?? null,
                'message' => $customFields['message'] ?? null,
                'is_validated' => true,
            ]);

            $transaction->transactionable()->associate($prayer)->save();

            dispatch(fn() => Mail::to($prayer->email)->send(new PrayerRequestValidated($prayer)));
        }
    }
}
