<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\Synagogue;
use Illuminate\Http\Request;

class SynagogueController extends Controller
{
    public function addSynagogue(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'localisation' => 'required|string',
            'horaires' => 'required|string',
        ]);

        $synagogue = Synagogue::create([
            'nom' => $request->nom,
            'localisation' => $request->localisation,
            'horaires' => $request->horaires,
        ]);

        return response()->json(['message' => 'Synagogue ajoutée avec succès', 'synagogue' => $synagogue], 201);
    }

    public function listeSynagogue()
    {
        $synagogues = Synagogue::all();
        return response()->json($synagogues);
    }

    public function updateSynagogue(Request $request, $id)
    {
        $synagogue = Synagogue::findOrFail($id);

        $request->validate([
            'nom' => 'sometimes|string|max:255',
            'localisation' => 'sometimes|string',
            'horaires' => 'sometimes|string',
        ]);

        $synagogue->update($request->only(['nom', 'localisation', 'horaires']));

        return response()->json(['message' => 'Synagogue mise à jour', 'synagogue' => $synagogue]);
    }

    public function deleteSynagogue($id)
    {
        $synagogue = Synagogue::findOrFail($id);
        $synagogue->delete();

        return response()->json(['message' => 'Synagogue supprimée', 'statut' => 'deleted']);
    }
}

