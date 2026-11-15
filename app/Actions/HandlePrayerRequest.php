<?php
namespace App\Actions;

use App\Models\PrayerRequest;
use App\Mail\PrayerRequestValidated;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HandlePrayerRequest
{
    /**
     * Dette #07 : Toujours enregistrer la demande de prière liée à une transaction unique validée
     * (sans bloquer un fidèle soumettant plusieurs fois un même objet de prière).
     */
    public function execute($transaction, $metadata)
    {
        if ($transaction->transactionable_id !== null) {
            return;
        }

        $customFields = $metadata['custom_fields'] ?? [];

        $prayer = PrayerRequest::create([
            'nom' => $metadata['nom'] ?? null,
            'prenom' => $metadata['prenom'] ?? null,
            'email' => $transaction->email,
            'numero' => $transaction->numero,
            'objet' => $customFields['objet'] ?? null,
            'message' => $customFields['message'] ?? null,
            'is_validated' => true,
        ]);

        $transaction->transactionable()->associate($prayer)->save();

        dispatch(function () use ($prayer) {
            try {
                Mail::to($prayer->email)->send(new PrayerRequestValidated($prayer));
            } catch (\Throwable $e) {
                Log::warning('Prayer request confirmation mail failed: ' . $e->getMessage());
            }
        });
    }
}
