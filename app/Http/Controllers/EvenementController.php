<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;

use App\Models\Evenement;
use Illuminate\Http\Request;

class EvenementController extends Controller
{
    // Ajouter un événement
    public function addEvenement(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
        ]);

        $evenement = Evenement::create([
            'titre' => $request->titre,
            'description' => $request->description,
            'date' => $request->date,
        ]);

        return response()->json(['message' => 'Événement ajouté avec succès', 'evenement' => $evenement], 201);
    }

    // Lister tous les événements
    public function listeEvenement()
    {
        $evenements = Evenement::all();
        return response()->json($evenements);
    }

    // Mettre à jour un événement
    public function updateEvenement(Request $request, $id)
    {
        $evenement = Evenement::findOrFail($id);

        $request->validate([
            'titre' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'date' => 'sometimes|date',
        ]);

        $evenement->update($request->only(['titre', 'description', 'date']));

        return response()->json(['message' => 'Événement mis à jour', 'evenement' => $evenement]);
    }

    // Supprimer un événement
    public function deleteEvenement($id)
    {
        $evenement = Evenement::findOrFail($id);
        $evenement->delete();

        return response()->json(['message' => 'Événement supprimé', 'statut' => 'deleted']);
    }
}

