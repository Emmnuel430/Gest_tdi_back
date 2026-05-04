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
}
