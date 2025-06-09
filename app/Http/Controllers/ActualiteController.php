<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Actualite;
use Illuminate\Http\Request;

class ActualiteController extends Controller
{
    public function addActualite(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
        ]);

        $actualite = Actualite::create([
            'titre' => $request->titre,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Actualité ajoutée avec succès',
            'actualite' => $actualite
        ], 201);
    }

    public function listeActualite()
    {
        $actualites = Actualite::all();
        return response()->json($actualites);
    }

    public function updateActualite(Request $request, $id)
    {
        $actualite = Actualite::findOrFail($id);

        $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
        ]);

        $actualite->update($request->only(['titre', 'description']));

        return response()->json([
            'message' => 'Actualité mise à jour',
            'actualite' => $actualite
        ]);
    }

    public function deleteActualite($id)
    {
        $actualite = Actualite::findOrFail($id);
        $actualite->delete();

        return response()->json([
            'message' => 'Actualité supprimée',
            'statut' => 'deleted'
        ]);
    }
}

