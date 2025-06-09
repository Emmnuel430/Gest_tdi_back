<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Fondement;
use Illuminate\Http\Request;

class FondementController extends Controller
{
    public function addFondement(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'texte' => 'required|string',
        ]);

        $fondement = Fondement::create([
            'titre' => $request->titre,
            'texte' => $request->texte,
        ]);

        return response()->json([
            'message' => 'Fondement ajouté avec succès',
            'fondement' => $fondement
        ], 201);
    }

    public function listeFondement()
    {
        $fondements = Fondement::all();
        return response()->json($fondements);
    }

    public function updateFondement(Request $request, $id)
    {
        $fondement = Fondement::findOrFail($id);

        $request->validate([
            'titre' => 'sometimes|string|max:255',
            'texte' => 'sometimes|string',
        ]);

        $fondement->update($request->only(['titre', 'texte']));

        return response()->json([
            'message' => 'Fondement mis à jour',
            'fondement' => $fondement
        ]);
    }

    public function deleteFondement($id)
    {
        $fondement = Fondement::findOrFail($id);
        $fondement->delete();

        return response()->json([
            'message' => 'Fondement supprimé',
            'statut' => 'deleted'
        ]);
    }
}

