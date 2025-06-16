<?php
namespace App\Http\Controllers;

use App\Models\Layout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LayoutController extends Controller
{
    // Lister tous les layouts
    public function index()
    {
        return response()->json(Layout::all());
    }

    // Afficher un seul layout
    public function show($id)
    {
        $layout = Layout::findOrFail($id);
        return response()->json($layout);
    }

    // Créer un nouveau layout
    public function store(Request $request)
    {
        $validated = $request->validate([
            'affiche_titre' => 'nullable|string|max:255',
            'affiche_lien' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|max:2048',
            'actif' => 'boolean',
        ]);

        $layout = Layout::create($validated);

        // Gérer l’image
        if ($request->hasFile('main_image')) {
            $imagePath = $request->file('main_image')->store("layouts/layout_$layout->id", 'public');
            $layout->update(['affiche_image' => $imagePath]);
        }

        return response()->json($layout, 201);
    }

    // Mettre à jour un layout
    public function update(Request $request, $id)
    {
        $layout = Layout::findOrFail($id);

        $validated = $request->validate([
            'affiche_titre' => 'nullable|string|max:255',
            'affiche_lien' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|max:2048',
            'actif' => 'boolean',
        ]);

        $layout->update($validated);

        // Gérer nouvelle image
        if ($request->hasFile('main_image')) {
            // Supprimer l'ancienne si elle existe
            if ($layout->affiche_image) {
                Storage::disk('public')->delete($layout->affiche_image);
            }

            $imagePath = $request->file('main_image')->store("layouts/layout_$layout->id", 'public');
            $layout->update(['affiche_image' => $imagePath]);
        }

        return response()->json($layout);
    }

    // Supprimer un layout
    public function destroy($id)
    {
        $layout = Layout::findOrFail($id);

        // Supprimer image si elle existe
        if ($layout->affiche_image) {
            Storage::disk('public')->delete($layout->affiche_image);
        }

        $layout->delete();

        return response()->json(['statut' => 'deleted', 'message' => 'Layout supprimé.']);
    }
}

