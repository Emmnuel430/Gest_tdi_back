<?php

namespace App\Http\Controllers;

use App\Models\GalerieDossier;
use Illuminate\Http\Request;

class GalerieDossierController extends Controller
{
    private function generateUniqueName($nom)
    {
        $original = $nom;
        $counter = 1;

        while (GalerieDossier::where('nom', $nom)->exists()) {
            $nom = $original . ' (' . $counter . ')';
            $counter++;
        }

        return $nom;
    }

    public function store(Request $request)
    {
        $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        // 🔥 générer nom unique
        $nom = $this->generateUniqueName($request->nom);

        $dossier = GalerieDossier::create([
            'nom' => $nom,
            'description' => $request->description,
        ]);
        return response()->json([
            'message' => 'Dossier créé avec succès',
            'data' => $dossier
        ], 201);
    }

    public function index()
    {
        $dossiers = GalerieDossier::withCount('images')->latest()->get();

        return response()->json($dossiers);
    }

    public function show($id)
    {
        $dossier = GalerieDossier::findOrFail($id);

        return response()->json($dossier);
    }

    public function update(Request $request, $id)
    {
        $dossier = GalerieDossier::findOrFail($id);

        $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_visible' => 'boolean',
        ]);

        $dossier->update($request->only('nom', 'description', 'is_visible'));

        return response()->json($dossier);
    }

    public function delete($id)
    {
        $dossier = GalerieDossier::findOrFail($id);
        $dossier->delete();

        return response()->json(['message' => 'Dossier supprimé']);
    }

    public function deleteMultiple(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:galerie_dossiers,id',
        ]);

        GalerieDossier::whereIn('id', $request->ids)->delete();

        return response()->json([
            'message' => count($request->ids) . ' dossier(s) supprimé(s)'
        ]);
    }

    public function toggleDossier($id)
    {
        $dossier = GalerieDossier::findOrFail($id);
        $dossier->is_visible = !$dossier->is_visible;
        $dossier->save();

        return response()->json([
            'message' => 'Visibilité du dossier changé !',
            'data' => $dossier
        ]);
    }
}