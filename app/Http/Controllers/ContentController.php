<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ContentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:formation,cours,evenement',
            'access_level' => 'required|in:standard,premium',
            'content' => 'nullable|string',
            'lien' => 'nullable|url',
        ]);

        $content = Content::create($validated);

        return response()->json(['message' => 'Contenu ajouté', 'data' => $content]);
    }

    public function index(Request $request)
    {
        return Content::orderByDesc('created_at')->get();
    }

    public function destroy($id)
    {
        $content = Content::findOrFail($id);
        $content->delete();
        return response()->json(['status' => 'deleted', 'message' => 'Adhérent supprimé avec succès']);
    }

    public function show($id)
    {
        $content = Content::findOrFail($id);
        return response()->json(['data' => $content]);
    }

    public function byType(Request $request)
    {

        $adherent = auth()->user();

        $accessLevels = match ($adherent->statut) {
            'premium' => ['standard', 'premium'],
            default => ['standard'],
        };

        $query = Content::whereIn('access_level', $accessLevels);

        // Si un type est fourni, filtrer dessus
        if ($request->has('type')) {

            $query->where('type', $request->type);
        }


        $result = $query->get();



        return response()->json($result);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:formation,cours,evenement',
            'access_level' => 'required|in:standard,premium',
            'content' => 'nullable|string',
            'lien' => 'nullable|url',
        ]);

        $content = Content::findOrFail($id);
        $content->update($validated);

        return response()->json(['message' => 'Contenu mis à jour avec succès', 'data' => $content]);
    }


}
