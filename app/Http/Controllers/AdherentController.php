<?php

namespace App\Http\Controllers;


use App\Http\Requests\StoreProfileRequest;
use App\Http\Resources\AdherentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Models\Adherent;
use App\Mail\AdherentValidatedMail;
use Illuminate\Support\Facades\Log;

class AdherentController extends Controller
{
    public function index()
    {
        return Adherent::with([
            'activeSubscription.plan',
            'activeSubscription.transactions'
        ])
            ->orderByDesc('created_at')
            ->get();
    }

    public function destroy($id)
    {
        $adherent = Adherent::findOrFail($id);
        $adherent->delete();
        return response()->json(['status' => 'deleted', 'message' => 'Adhérent supprimé avec succès']);
    }

    public function toggleValidateAdherent($id)
    {
        $adherent = Adherent::findOrFail($id);

        // toggle
        $adherent->is_active = !$adherent->is_active;
        $adherent->save();

        // 📩 mail uniquement si validé
        if ($adherent->is_active) {
            dispatch(function () use ($adherent) {
                try {
                    Mail::to($adherent->email)->send(
                        new AdherentValidatedMail($adherent)
                    );
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                }
            });
        }

        return response()->json([
            'message' => $adherent->is_active
                ? 'Compte activé'
                : 'Compte désactivé',
            'data' => $adherent
        ]);
    }

    public function update(Request $request, $id)
    {
        $adherent = Adherent::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:adherents,email,' . $adherent->id,
            'contact' => 'nullable|string',
        ]);

        // 🧠 Mise à jour
        $adherent->update($validated);

        return response()->json([
            'message' => 'Adhérent mis à jour avec succès',
            'data' => $adherent
        ]);
    }

    public function show($id)
    {
        $adherent = Adherent::with('profile')->findOrFail($id);
        return response()->json(['data' => $adherent]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required',
            'password' => 'required',
        ]);

        $adherent = Adherent::where('email', $request->email)->first();

        if (!$adherent || !Hash::check($request->password, $adherent->password)) {
            return response()->json(['error' => 'Email ou mot de passe incorrect'], 401);
        }

        if (!$adherent->is_active) {
            return response()->json(['message' => 'Ce compte n\'est pas valide ou a été désactivé.'], 403);
        }
        $adherent->load('activeSubscription.plan');

        $token = $adherent->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'adherent' => new AdherentResource($adherent)

        ]);
    }

    public function updateProfile(StoreProfileRequest $request)
    {
        $adherent = auth()->user();

        DB::transaction(function () use ($adherent, $request) {
            $data = $request->validated();

            $adherent->profile()->updateOrCreate(
                ['adherent_id' => $adherent->id],
                $data
            );

            if ($request->boolean('is_final_step')) {
                $adherent->update([
                    'profile_completed' => true
                ]);
            }
        });

        // 🔥 Important : reload relation
        $adherent->load('profile');

        return response()->json([
            'message' => 'Progression enregistrée',
            'profile_completed' => $adherent->profile_completed,
            'profile' => $adherent->profile
        ]);
    }

    public function me(Request $request)
    {
        $adherent = $request->user()->load(['profile', 'activeSubscription.plan']);

        return response()->json([
            'adherent' => new AdherentResource($adherent)
        ]);
    }

}
