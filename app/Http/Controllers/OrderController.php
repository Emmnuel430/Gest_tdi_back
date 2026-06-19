<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::with([
            'transactions' => function ($query) {
                $query->latest();
            }
        ])->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    public function changeStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,delivered,canceled',

            'delivery_details' => 'nullable|array',

            'delivery_details.*.product_id'
            => 'required_with:delivery_details|integer',

            'delivery_details.*.delivered_quantity'
            => 'required_with:delivery_details|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $id) {

            $order = Order::with('transactions')->findOrFail($id);

            $allowedTransitions = [
                'pending' => ['delivered', 'canceled'],
                'delivered' => [],
                'canceled' => [],
            ];

            if (!in_array($request->status, $allowedTransitions[$order->status])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transition de statut invalide'
                ], 422);
            }

            if ($request->status === 'delivered') {
                $metadata = $order->metadata ?? [];

                $metadata['delivery_details'] = collect(
                    $request->delivery_details
                )
                    ->filter(fn($item) => $item['delivered_quantity'] > 0)
                    ->values()
                    ->toArray();

                $order->metadata = $metadata;
            }
            $order->status = $request->status;
            $order->save();

            // CAS METIER IMPORTANT
            if ($request->status === 'canceled') {
                $order->transactions()->update([
                    'status' => 'refunded'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Statut mis à jour',
                'data' => $order->load('transactions')
            ]);
        });
    }

    public function updateDeliveryDetails(
        Request $request,
        $id
    ) {
        $request->validate([
            'delivery_details' => 'required|array',

            'delivery_details.*.product_id'
            => 'required|integer',

            'delivery_details.*.delivered_quantity'
            => 'required|integer|min:0',
        ]);

        return DB::transaction(function () use ($request, $id) {

            $order = Order::findOrFail($id);

            $metadata = $order->metadata ?? [];

            $cartDetails = collect(
                $metadata['cart_details'] ?? []
            );

            foreach ($request->delivery_details as $item) {

                $orderedProduct = $cartDetails->first(
                    fn($cartItem) =>
                    (int) $cartItem['product_id']
                    ===
                    (int) $item['product_id']
                );

                if (!$orderedProduct) {

                    return response()->json([
                        'success' => false,
                        'message' =>
                            "Le produit {$item['product_id']} n'existe pas dans la commande."
                    ], 422);
                }

                $orderedQuantity =
                    (int) $orderedProduct['quantity'];

                if (
                    (int) $item['delivered_quantity']
                    >
                    $orderedQuantity
                ) {

                    return response()->json([
                        'success' => false,
                        'message' =>
                            "La quantité livrée du produit {$item['product_id']} dépasse la quantité commandée."
                    ], 422);
                }
            }

            $current = collect(
                $metadata['delivery_details'] ?? []
            )->keyBy('product_id');

            foreach ($request->delivery_details as $item) {

                $current->put(
                    $item['product_id'],
                    [
                        'product_id' => $item['product_id'],
                        'delivered_quantity' => $item['delivered_quantity'],
                    ]
                );
            }

            $metadata['delivery_details'] = $current
                ->filter(
                    fn($item) =>
                    $item['delivered_quantity'] > 0
                )
                ->values()
                ->toArray();

            $order->metadata = $metadata;

            $order->save();

            return response()->json([
                'success' => true,
                'message' =>
                    'Détails de livraison mis à jour',
                'data' => $order
            ]);
        });
    }
}
