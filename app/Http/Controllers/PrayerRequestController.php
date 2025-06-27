<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PrayerRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Mail\PrayerRequestValidated;
use Illuminate\Support\Facades\Mail;

class PrayerRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email',
            'objet' => 'required|string',
            'message' => 'required|string',
            'moyen_paiement' => 'required|string',
            'preuve_paiement' => 'required|file|mimes:jpg,png,jpeg,pdf|max:2048',
        ]);

        if ($request->hasFile('preuve_paiement')) {
            $path = $request->file('preuve_paiement')->store('prieres', 'public');
            $validated['preuve_paiement'] = $path;
        }

        $priere = PrayerRequest::create($validated);

        return response()->json(['message' => 'Demande enregistrée', 'data' => $priere]);
    }

    public function index()
    {
        return PrayerRequest::orderByDesc('created_at')->get();
    }

    public function destroy($id)
    {
        $prayer = PrayerRequest::findOrFail($id);
        $prayer->delete();
        return response()->json(['status' => 'deleted', 'message' => 'Adhérent supprimé avec succès']);
    }

    public function validatePrayerRequest($id)
    {
        $prayer = PrayerRequest::findOrFail($id);

        if ($prayer->is_validated) {
            return response()->json(['message' => 'Demande de prière déjà validée'], 400);
        }

        $prayer->is_validated = true;
        $prayer->save();

        try {
            Mail::to($prayer->email)->send(new PrayerRequestValidated($prayer));
        } catch (\Exception $e) {
            Log::error('Erreur envoi mail : ' . $e->getMessage());
            return response()->json(['message' => 'Demande validée, mais erreur mail'], 200);
        }

        return response()->json(['message' => 'Demande validée et email envoyé']);
    }
}
