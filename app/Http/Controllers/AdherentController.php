<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Models\Adherent;
use App\Mail\AdherentCredentials;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AdherentController extends Controller
{
    public function store(Request $request)
    {
        // \Log::info('Requête de création d’adhérent reçue', ['request' => $request->all()]);
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:adherents',
            'contact' => 'nullable|string',
            'moyen_paiement' => 'required|string',
            'preuve_paiement' => 'required|file|mimes:jpg,png,jpeg,pdf|max:2048',
            'statut' => 'required|in:standard,premium',
            'abonnement_type' => 'required|in:hebdomadaire,mensuel,annuel',
        ]);

        if ($request->hasFile('preuve_paiement')) {
            $validated['preuve_paiement'] = $request->file('preuve_paiement')->store('adherents', 'public');
        }

        $adherent = Adherent::create($validated);

        return response()->json([
            'message' => 'Adhésion enregistrée avec succès.',
            'data' => $adherent
        ]);
    }


    public function index()
    {
        return Adherent::orderByDesc('created_at')->get();
    }

    public function destroy($id)
    {
        $adherent = Adherent::findOrFail($id);
        $adherent->delete();
        return response()->json(['status' => 'deleted', 'message' => 'Adhérent supprimé avec succès']);
    }

    public function validateAdherent($id)
    {
        $adherent = Adherent::findOrFail($id);

        if ($adherent->is_validated) {
            return response()->json(['message' => 'Adhérent déjà validé'], 400);
        }

        // Calcul de la date d'expiration
        $expiration = match ($adherent->abonnement_type) {
            'hebdomadaire' => Carbon::now()->addWeek(),
            'mensuel' => Carbon::now()->addMonth(),
            'annuel' => Carbon::now()->addYear(),
        };

        // Générer pseudo et mot de passe
        $pseudo = Str::lower(Str::slug($adherent->prenom . '-' . Str::random(4)));
        $password = Str::random(8); // clair pour email
        $adherent->pseudo = $pseudo;
        $adherent->password = $password;
        $adherent->is_validated = true;
        $adherent->abonnement_expires_at = $expiration;
        $adherent->save();

        try {
            // Envoi du mail
            Mail::to($adherent->email)->send(new AdherentCredentials($adherent, $password));
        } catch (\Exception $e) {
            Log::error('Échec envoi mail : ' . $e->getMessage());
            return response()->json(['message' => 'Adhérent validé, mais erreur lors de l’envoi du mail'], 200);
        }

        return response()->json(['message' => 'Adhérent validé, Abonnement activé et email envoyé']);
    }


    public function update(Request $request, $id)
    {
        $adherent = Adherent::findOrFail($id);

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            // 'email' => 'required|email|unique:adherents,email,' . $adherent->id,
            'contact' => 'nullable|string',
            'statut' => 'required|in:standard,premium',
            'abonnement_type' => 'nullable|in:hebdomadaire,mensuel,annuel',
            'abonnement_expires_at' => 'nullable|date',
            'is_validated' => 'nullable|boolean',
        ]);
        // --- Vérification si pseudo ou mot de passe ont changé
        // $newPseudo = !empty($validated['pseudo']);
        // $pseudoChanged = $newPseudo && $newPseudo !== $adherent->pseudo;

        // $passwordChanged = !empty($validated['password']);


        // // Enregistrement du mot de passe en clair (pour email uniquement)
        // $passwordClair = $validated['password'] ?? null;

        // // 🔒 Si un mot de passe est fourni, on le hash
        // if ($passwordChanged) {
        //     $validated['password'];
        // } else {
        //     unset($validated['password']); // sinon ne pas l’écraser
        // }

        // // 🔒 Si un pseudo est fourni
        // if ($newPseudo) {
        //     $validated['pseudo'] = $newPseudo;
        // } else {
        //     unset($validated['pseudo']);
        // }

        // --- Envoi du mail si nécessaire
        // if ($pseudoChanged || $passwordChanged) {
        //     try {
        //         Mail::to($adherent->email)->send(new AdherentCredentials($adherent, $passwordClair));
        //     } catch (\Exception $e) {
        //         \Log::error('Échec envoi mail MAJ adhérent : ' . $e->getMessage());
        //         return response()->json([
        //             'message' => 'Adhérent mis à jour, mais le mail n’a pas pu être envoyé.'
        //         ], 200);
        //     }
        // }

        // 🧠 Mise à jour
        $adherent->update($validated);

        return response()->json([
            'message' => 'Adhérent mis à jour avec succès',
            'data' => $adherent
        ]);
    }

    public function show($id)
    {
        $adherent = Adherent::findOrFail($id);
        return response()->json(['data' => $adherent]);
    }

    public function login(Request $request)
    {

        // Recherche l'utilisateur en fonction du pseudo fourni.
        $adherent = Adherent::where('pseudo', $request->pseudo)->first();

        // Vérifie si l'utilisateur existe et si le mot de passe est correct.
        if (!$adherent || $request->password !== $adherent->password) {
            return response()->json(['error' => 'Pseudo ou mot de passe incorrect'], 401);
        }

        // Optionnel : vérifier si l'adhérent est validé
        if (!$adherent->is_validated) {
            return response()->json(['message' => 'Ce compte n\'est pas valide.'], 403);
        }

        $token = $adherent->createToken('auth_token')->plainTextToken;

        $adherentData = $adherent->only([
            'id',
            'nom',
            'prenom',
            'email',
            'contact',
            'pseudo',
            'statut',
            'abonnement_type',
            'abonnement_expires_at',
        ]);

        return response()->json(['token' => $token, 'adherent' => $adherentData]);
    }

}
