<?php

namespace App\Http\Controllers;

use App\Models\GalerieDossier;
use App\Models\GalerieImage;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function deleteMedia($id)
    {
        DB::beginTransaction();

        try {

            $media = Media::findOrFail($id);

            // 🔍 Vérifier utilisation
            $usageCount = $media->galerieImages()->count();

            if ($usageCount > 1) {
                return response()->json([
                    'error' => 'Ce media est utilisé dans des dossiers',
                ], 400);
            }

            // supprimer relation restante
            $media->galerieImages()->delete();

            // 🧹 supprimer fichier physique
            Storage::disk('public')->delete($media->file_path);

            // 🗑 supprimer en base
            $media->delete();

            DB::commit();

            return response()->json([
                'message' => 'Media supprimé définitivement',
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Erreur suppression media',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function forceDeleteMedia(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:media,id', // On vérifie que les IDs existent
        ]);

        DB::beginTransaction();

        try {
            // 1. Récupérer tous les médias pour avoir accès aux chemins de fichiers
            $medias = Media::whereIn('id', $request->ids)->get();

            foreach ($medias as $media) {
                // 2. Supprimer les relations (galerie_images)
                $media->galerieImages()->delete();

                // 3. Supprimer le fichier physique
                Storage::disk('public')->delete($media->file_path);

                // 4. Supprimer l'entrée en base (forceDelete si SoftDelete utilisé)
                $media->delete();
            }

            DB::commit();

            return response()->json([
                'message' => count($request->ids) . ' média(s) supprimé(s) avec succès',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erreur lors de la suppression groupée',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function listMedia(Request $request)
    {
        $media = Media::withCount('galerieImages')->latest()->paginate(20);
        return response()->json($media);
    }

    public function store(Request $request)
    {
        $request->validate([
            'images' => 'required',
            'images.*' => 'image|max:3072',
        ]);

        $files = $request->file('images');
        if (!is_array($files))
            $files = [$files];

        $results = [];

        foreach ($files as $file) {
            $hash = md5_file($file->getRealPath());

            $media = Media::firstOrCreate(
                ['hash' => $hash],
                [
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $file->store('galerie', 'public'),
                    'file_type' => $file->getMimeType(),
                ]
            );

            $results[] = $media;
        }

        return response()->json($results);
    }
}