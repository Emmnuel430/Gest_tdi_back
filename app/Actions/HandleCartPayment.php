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

        dispatch(function () use ($order, $customFields, $transaction) {
            $resources = [];
            foreach ($customFields['cart_details'] ?? [] as $item) {
                $sub = Subsection::find($item['product_id']);
                if ($sub && $sub->type === 'ressource' && $sub->link) {
                    $resources[] = ['title' => $sub->title, 'link' => $sub->link];
                }
            }
            try {
                Mail::to($order->email)->send(new OrderConfirm($order, $resources, $transaction));
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        });
    }
}
