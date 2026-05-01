<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * LIST (pagination + filtres)
     */
    public function index(Request $request)
    {
        $query = Transaction::query();

        // Filtres optionnels
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // tri récent par défaut
        $transactions = $query
            ->latest()
            ->paginate(15);

        return response()->json($transactions);
    }

    /**
     * CHANGE STATUS (refund ou correction)
     */
    public function changeStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:success,failed,refunded'
        ]);

        $transaction = Transaction::findOrFail($id);

        // Optionnel : empêcher modification si déjà remboursé
        if ($transaction->status === 'refunded') {
            return response()->json([
                'message' => 'Transaction déjà remboursée'
            ], 400);
        }

        $transaction->update([
            'status' => $request->status
        ]);

        return response()->json([
            'message' => 'Statut mis à jour',
            'transaction' => $transaction
        ]);
    }

    /**
     * STATS / CHARTS
     */
    public function stats()
    {
        // Revenus par type
        $revenueByType = Transaction::select(
            'type',
            DB::raw('SUM(amount) as total')
        )
            ->where('status', 'success')
            ->groupBy('type')
            ->get();

        // Répartition (count)
        $countByType = Transaction::select(
            'type',
            DB::raw('COUNT(*) as total')
        )
            ->groupBy('type')
            ->get();

        // Revenus par mois + type
        $revenueOverTime = Transaction::select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            'type',
            DB::raw('SUM(amount) as total')
        )
            ->whereBetween('created_at', [
                now()->subYear(),
                now()
            ])
            ->where('status', 'success')
            ->groupBy('month', 'type')
            ->orderBy('month', 'asc')
            ->get();

        // revenu total
        $totalRevenue = Transaction::success()->sum('amount');

        return response()->json([
            'revenue_by_type' => $revenueByType,
            'count_by_type' => $countByType,
            'revenue_over_time' => $revenueOverTime,

            'total_revenue' => $totalRevenue,
        ]);
    }
}
