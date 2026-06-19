<?php
namespace App\Actions;

use App\Models\Tsedaka;
use App\Mail\TsedakaThankYou;
use Illuminate\Support\Facades\Mail;

class HandleTsedaka
{
    public function execute($transaction, $metadata)
    {
        $customFields = $metadata['custom_fields'] ?? [];

        $tsedaka = Tsedaka::create([
            'reference' => $transaction->reference,
            'nom' => $metadata['nom'] ?? null,
            'prenom' => $metadata['prenom'] ?? null,
            'email' => $transaction->email,
            'numero' => $transaction->numero,
            'montant' => $customFields['montant'] ?? 0,
            'anonymous' => filter_var($customFields['anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'message' => $customFields['message'] ?? null,
        ]);

        // 🔗 polymorph (si utilisé)
        $transaction->transactionable()->associate($tsedaka)->save();
        dispatch(
            fn() =>
            Mail::to($tsedaka->email)
                ->send(new TsedakaThankYou($tsedaka))
        );
    }
}
