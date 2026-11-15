<?php
namespace App\Actions;

use App\Models\{Order, Subsection};
use App\Mail\OrderConfirm;
use Illuminate\Support\Facades\{Mail, Log};

class HandleCartPayment
{
    public function execute($transaction, $metadata)
    {
        $customFields = $metadata['custom_fields'] ?? [];

        $order = Order::create([
            'reference' => $transaction->reference,
            'nom' => $transaction->nom,
            'email' => $transaction->email,
            'numero' => $transaction->numero,
            'commune' => $metadata['commune'] ?? null,
            'total_items' => $metadata['total_items'] ?? null,
            'metadata' => $customFields,
        ]);

        $transaction->transactionable()->associate($order)->save();

        // Envoi immédiat après la réponse HTTP (sans dépendre d'un démon queue:work externe)
        \App\Jobs\SendLibraryResourcesJob::dispatchAfterResponse($order);
    }
}
