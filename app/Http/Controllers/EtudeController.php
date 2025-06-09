<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Etude;
use Illuminate\Http\Request;

class EtudeController extends Controller
{
    public function addEtude(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'verset' => 'nullable|string',
            'texte' => 'required|string',
        ]);

        $etude = Etude::create([
            'titre' => $request->titre,
            'verset' => $request->verset,
            'texte' => $request->texte,
        ]);

        return response()->json([
            'message' => 'Étude ajoutée avec succès',
            'etude' => $etude
        ], 201);
    }

    public function listeEtude()
    {
        $etudes = Etude::all();
        return response()->json($etudes);
    }

    public function updateEtude(Request $request, $id)
    {
        $etude = Etude::findOrFail($id);

        $request->validate([
            'titre' => 'sometimes|string|max:255',
            'verset' => 'nullable|string',
            'texte' => 'sometimes|string',
        ]);

        $etude->update($request->only(['titre', 'verset', 'texte']));

        return response()->json([
            'message' => 'Étude mise à jour',
            'etude' => $etude
        ]);
    }

    public function deleteEtude($id)
    {
        $etude = Etude::findOrFail($id);
        $etude->delete();

        return response()->json([
            'message' => 'Étude supprimée',
            'statut' => 'deleted'
        ]);
    }
}

