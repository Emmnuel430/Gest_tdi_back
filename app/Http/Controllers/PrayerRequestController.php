<?php

namespace App\Http\Controllers;

use App\Models\PrayerRequest;

class PrayerRequestController extends Controller
{
    public function index()
    {
        $prayers = PrayerRequest::with([
            'transactions' => function ($query) {
                $query->latest();
            }
        ])->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $prayers
        ]);
    }

    public function destroy($id)
    {
        $prayer = PrayerRequest::findOrFail($id);
        $prayer->delete();
        return response()->json(['status' => 'deleted', 'message' => 'Adhérent supprimé avec succès']);
    }

}
