<?php

namespace App\Http\Controllers;

use App\Mail\PrayerRequestValidated;
use App\Models\PrayerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PrayerRequestController extends Controller
{
    /**
     * Dette #10 : Support de la pagination tout en conservant la clé `data` attendue par le front-office.
     */
    public function index(Request $request)
    {
        $query = PrayerRequest::with([
            'transactions' => function ($query) {
                $query->latest();
            }
        ])->latest();

        $paginator = $query->paginate($request->integer('per_page', 25));

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Dette #09 : Implémentation de la méthode validatePrayerRequest appelée par POST /prayer-requests/{id}/validate.
     */
    public function validatePrayerRequest(Request $request, $id)
    {
        return DB::transaction(function () use ($id) {
            $prayer = PrayerRequest::findOrFail($id);

            if (!$prayer->is_validated) {
                $prayer->is_validated = true;
                $prayer->save();

                if ($prayer->email) {
                    dispatch(function () use ($prayer) {
                        try {
                            Mail::to($prayer->email)->send(new PrayerRequestValidated($prayer));
                        } catch (\Throwable $e) {
                            Log::warning('Validation email failed for prayer request #' . $prayer->id . ': ' . $e->getMessage());
                        }
                    });
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Demande de prière validée avec succès.',
                'data' => $prayer->load('transactions'),
            ]);
        });
    }

    public function destroy($id)
    {
        $prayer = PrayerRequest::findOrFail($id);
        $prayer->delete();
        return response()->json(['status' => 'deleted', 'message' => 'Demande de prière supprimée avec succès']);
    }
}
