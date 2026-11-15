<?php

namespace App\Actions;

use App\Mail\PrayerRequestValidated;
use App\Models\PrayerRequest;
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
        if (array_is_list($customFields)) {
            $normalized = [];
            foreach ($customFields as $field) {
                if (is_array($field) && isset($field['variable_name'])) {
                    $normalized[$field['variable_name']] = $field['value'] ?? null;
                }
            }
            $customFields = $normalized;
        }

        $nom = $metadata['nom'] ?? $customFields['nom'] ?? 'Anonyme';
        $prenom = $metadata['prenom'] ?? $customFields['prenom'] ?? '';
        $objet = $customFields['objet'] ?? $metadata['objet'] ?? 'Demande de prière';
        $message = $customFields['message'] ?? $metadata['message'] ?? '';

        $prayer = PrayerRequest::create([
            'nom' => $nom,
            'prenom' => $prenom,
            'email' => $transaction->email ?? ($metadata['email'] ?? ''),
            'numero' => $transaction->numero ?? ($metadata['numero'] ?? null),
            'objet' => $objet,
            'message' => $message,
            'is_validated' => true,
        ]);

        $transaction->transactionable()->associate($prayer)->save();

        // Si le fidèle a également renseigné un montant de Tsedaka en bas du formulaire de demande de prière
        $montantTsedaka = (float) ($customFields['montant_tsedaka'] ?? $metadata['montant_tsedaka'] ?? 0);
        if ($montantTsedaka > 0) {
            $tsedakaRef = $transaction->reference.'-TSEDAKA';
            if (! \App\Models\Tsedaka::where('reference', $tsedakaRef)->exists()) {
                \App\Models\Tsedaka::create([
                    'reference' => $tsedakaRef,
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $prayer->email,
                    'numero' => $prayer->numero,
                    'montant' => (int) round($montantTsedaka),
                    'anonymous' => false,
                    'message' => "Tsedaka associée à la demande de prière : {$objet}",
                ]);
            }
        }

        dispatch(function () use ($prayer) {
            try {
                if ($prayer->email) {
                    Mail::to($prayer->email)->send(new PrayerRequestValidated($prayer));
                }
            } catch (\Throwable $e) {
                Log::warning('Prayer request confirmation mail failed: '.$e->getMessage());
            }
        })->afterResponse();
    }
}
