<?php

namespace App\Http\Controllers;

use App\Models\Mot;
use Illuminate\Http\Request;

class MotController extends Controller
{
    public function addMot(Request $request)
    {
        $request->validate([
            'nom_rabbi' => 'required|string|max:255',
            'texte' => 'required|string',
        ]);

        $mot = Mot::create([
            'nom_rabbi' => $request->nom_rabbi,
            'texte' => $request->texte,
        ]);

        return response()->json(['message' => 'Mot ajouté avec succès', 'mot' => $mot], 201);
    }

    public function listeMot()
    {
        $mots = Mot::all();
        return response()->json($mots);
    }

    public function updateMot(Request $request, $id)
    {
        $mot = Mot::findOrFail($id);

        $request->validate([
            'nom_rabbi' => 'sometimes|string|max:255',
            'texte' => 'sometimes|string',
        ]);

        $mot->update($request->only(['nom_rabbi', 'texte']));

        return response()->json(['message' => 'Mot mis à jour', 'mot' => $mot]);
    }

    public function deleteMot($id)
    {
        $mot = Mot::findOrFail($id);
        $mot->delete();

        return response()->json(['message' => 'Mot supprimé', 'statut' => 'deleted']);
    }
}

