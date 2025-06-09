<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Conseiller;
use Illuminate\Support\Facades\Storage;

class ConseillerController extends Controller
{
    public function addConseiller(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'role' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // Stocker l'image dans le dossier "conseillers"
        $photoPath = $request->file('photo')->store('conseillers', 'public');

        $conseiller = Conseiller::create([
            'nom' => $request->nom,
            'prenom' => $request->prenom,
            'photo' => Storage::url($photoPath),
            'role' => $request->role,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'Conseiller ajouté avec succès', 'conseiller' => $conseiller], 201);
    }

    public function listeConseillers()
    {
        $conseillers = Conseiller::all();
        return response()->json($conseillers);
    }

    public function updateConseiller(Request $request, $id)
    {
        $conseiller = Conseiller::findOrFail($id);

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'prenom' => 'sometimes|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'role' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $data = $request->only(['nom', 'prenom', 'role', 'description']);

        if ($request->hasFile('photo')) {
            // Supprimer l'ancienne photo si nécessaire
            if ($conseiller->photo && Storage::exists(str_replace('/storage/', '', $conseiller->photo))) {
                Storage::delete(str_replace('/storage/', '', $conseiller->photo));
            }

            $photoPath = $request->file('photo')->store('conseillers', 'public');
            $data['photo'] = Storage::url($photoPath);
        }

        $conseiller->update($data);

        return response()->json(['message' => 'Conseiller mis à jour', 'conseiller' => $conseiller]);
    }

    public function deleteConseiller($id)
    {
        $conseiller = Conseiller::findOrFail($id);

        // Supprimer la photo liée si elle existe
        if ($conseiller->photo && Storage::exists(str_replace('/storage/', '', $conseiller->photo))) {
            Storage::delete(str_replace('/storage/', '', $conseiller->photo));
        }

        $conseiller->delete();

        return response()->json(['message' => 'Conseiller supprimé', 'statut' => 'deleted']);
    }
}

