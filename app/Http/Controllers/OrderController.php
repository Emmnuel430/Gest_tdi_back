<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

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
        ]);

        $order = Order::findOrFail($id);

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
        $order->status = $request->status;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Statut mis à jour',
            'data' => $order
        ]);
    }
}
