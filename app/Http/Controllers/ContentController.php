<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Carbon\Carbon;
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
            'publish_at' => 'nullable|date',
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

        $accessLevels = $adherent->statut === 'premium'
            ? ['standard', 'premium']
            : ['standard'];

        $now = now();

        $contents = Content::whereIn('access_level', $accessLevels)
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->where(function ($q) use ($now) {
                $q->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', $now);
            })
            ->get();

        return response()->json($contents);
    }
    public function update(Request $request, $id)
    {
        $minDate = Carbon::now()->addMinutes(5);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:formation,cours,evenement',
            'access_level' => 'required|in:standard,premium',
            'content' => 'nullable|string',
            'lien' => 'nullable|url',
            'publish_at' => ['nullable', 'date', 'after_or_equal:' . $minDate],

        ]);

        $content = Content::findOrFail($id);
        $content->update($validated);

        return response()->json(['message' => 'Contenu mis à jour avec succès', 'data' => $content]);
    }


}
