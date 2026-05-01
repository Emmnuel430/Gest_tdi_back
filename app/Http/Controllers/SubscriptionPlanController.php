<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionPlanRequest;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionPlanController extends Controller
{
    public function store(StoreSubscriptionPlanRequest $request)
    {
        $data = $request->validated();

        // nettoyage logique
        switch ($data['billing_type']) {

            case 'monthly':
                $data['registration_fee'] = null;
                $data['monthly_price'] = null;
                $data['total_payments'] = null;
                break;

            case 'one_time':
                $data['registration_fee'] = null;
                $data['monthly_price'] = null;
                $data['total_payments'] = null;
                break;

            case 'hybrid':
                $data['price'] = null;
                $data['duration_months'] = null;
                break;
        }

        $plan = SubscriptionPlan::create($data);

        return response()->json([
            'message' => 'Plan créé',
            'data' => $plan
        ], 201);
    }

    public function update(StoreSubscriptionPlanRequest $request, $id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        $data = $request->validated();
        Log::info($data);

        /*
        |--------------------------------------------------------------------------
        | Nettoyage logique selon type
        |--------------------------------------------------------------------------
        */

        switch ($data['billing_type']) {

            case 'monthly':
                $data['registration_fee'] = null;
                $data['monthly_price'] = null;
                $data['total_payments'] = null;
                break;

            case 'one_time':
                $data['registration_fee'] = null;
                $data['monthly_price'] = null;
                $data['total_payments'] = null;
                break;

            case 'hybrid':
                $data['price'] = null;
                $data['duration_months'] = null;
                break;
        }
        Log::info($plan);
        $plan->update($data);

        return response()->json([
            'message' => 'Plan mis à jour avec succès',
            'data' => $plan
        ]);
    }

    public function index(Request $request)
    {
        $query = SubscriptionPlan::query();

        // 🔍 filtre optionnel
        if ($request->has('billing_type')) {
            $query->where('billing_type', $request->billing_type);
        }

        // 🔄 tri
        $plans = $query->latest()->paginate(10);

        return response()->json([
            'data' => $plans->items(),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'total' => $plans->total(),
            ]
        ]);
    }

    public function indexPublic(Request $request)
    {
        return SubscriptionPlan::all();
    }

    public function show($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        return response()->json($plan);
    }

    public function destroy($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);

        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'message' => 'Impossible de supprimer un plan déjà utilisé'
            ], 400);
        }

        $plan->delete();

        return response()->json([
            'message' => 'Plan supprimé avec succès'
        ]);
    }
}
