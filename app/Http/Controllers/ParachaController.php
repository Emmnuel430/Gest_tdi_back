<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Storage;
use App\Models\Parachiot;

class ParachaController extends Controller
{
    public function addParacha(Request $request)
    {
        $request->validate([
            'titre' => 'required|string|max:255',
            'resume' => 'nullable|string',
            'contenu' => 'required|string',
            'date_lecture' => 'required|date',
            'fichier' => 'nullable|file|mimes:pdf,doc,docx,mp4,mov,avi|max:102400' // 100 Mo max, // max 5MB
        ]);

        $filePath = null;
        if ($request->hasFile('fichier')) {
            $filePath = $request->file('fichier')->store('parachiot', 'public');
        }

        $paracha = Parachiot::create([
            'titre' => $request->titre,
            'resume' => $request->resume,
            'contenu' => $request->contenu,
            'date_lecture' => $request->date_lecture,
            'fichier' => $filePath ? Storage::url($filePath) : null,
        ]);

        return response()->json(['message' => 'Parachiot ajoutée avec succès', 'paracha' => $paracha], 201);
    }

    public function listeParachiot()
    {
        $parachiot = Parachiot::all();
        return response()->json($parachiot);
    }

    public function updateParacha(Request $request, $id)
    {
        $paracha = Parachiot::findOrFail($id);

        $request->validate([
            'titre' => 'sometimes|string|max:255',
            'resume' => 'nullable|string',
            'contenu' => 'sometimes|string',
            'date_lecture' => 'sometimes|date',
            'fichier' => 'nullable|file|mimes:pdf,doc,docx,mp4,mov,avi|max:102400' // 100 Mo max,
        ]);

        $data = $request->only(['titre', 'resume', 'contenu', 'date_lecture']);

        if ($request->hasFile('fichier')) {
            // Supprimer l'ancien fichier si existant
            if ($paracha->fichier && Storage::exists(str_replace('/storage/', '', $paracha->fichier))) {
                Storage::delete(str_replace('/storage/', '', $paracha->fichier));
            }

            $filePath = $request->file('fichier')->store('parachiot', 'public');
            $data['fichier'] = Storage::url($filePath);
        }

        $paracha->update($data);

        return response()->json(['message' => 'Parachiot mise à jour', 'paracha' => $paracha]);
    }

    public function deleteParacha($id)
    {
        $paracha = Parachiot::findOrFail($id);

        // Supprimer le fichier associé s’il existe
        if ($paracha->fichier && Storage::exists(str_replace('/storage/', '', $paracha->fichier))) {
            Storage::delete(str_replace('/storage/', '', $paracha->fichier));
        }

        $paracha->delete();

        return response()->json(['message' => 'Parachiot supprimée', 'statut' => 'deleted']);
    }
}

