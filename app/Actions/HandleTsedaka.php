<?php

namespace App\Actions;

use App\Mail\TsedakaThankYou;
use App\Models\Tsedaka;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HandleTsedaka
{
    public function execute($transaction, $metadata)
    {
        if (Tsedaka::where('reference', $transaction->reference)->exists()) {
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

        $anonymous = filter_var($customFields['anonymous'] ?? $metadata['anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $montant = (int) round((float) ($customFields['montant'] ?? $metadata['montant'] ?? $transaction->amount ?? 0));

        $tsedaka = Tsedaka::create([
            'reference' => $transaction->reference,
            'nom' => $anonymous ? 'Anonyme' : ($metadata['nom'] ?? $customFields['nom'] ?? 'Anonyme'),
            'prenom' => $anonymous ? 'Donateur' : ($metadata['prenom'] ?? $customFields['prenom'] ?? ''),
            'email' => $transaction->email ?? ($metadata['email'] ?? ''),
            'numero' => $transaction->numero ?? ($metadata['numero'] ?? null),
            'montant' => $montant,
            'anonymous' => $anonymous,
            'message' => $customFields['message'] ?? $metadata['message'] ?? null,
        ]);

        // 🔗 polymorph
        $transaction->transactionable()->associate($tsedaka)->save();

        dispatch(function () use ($tsedaka) {
            try {
                if ($tsedaka->email) {
                    Mail::to($tsedaka->email)->send(new TsedakaThankYou($tsedaka));
                }
            } catch (\Throwable $e) {
                Log::warning('Tsedaka confirmation mail failed: '.$e->getMessage());
            }
        })->afterResponse();
    }
}
