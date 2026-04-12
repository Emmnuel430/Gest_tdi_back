<?php

namespace App\Http\Controllers;

use App\Models\GalerieDossier;
use App\Models\GalerieImage;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GalerieImageController extends Controller
{
    public function attach(Request $request)
    {
        $request->validate([
            'dossier_id' => 'required|exists:galerie_dossiers,id',
            'media_ids' => 'required|array',
            'media_ids.*' => 'exists:media,id',
        ]);

        $lastOrder = GalerieImage::where('dossier_id', $request->dossier_id)
            ->max('ordre') ?? 0;

        // Récupérer tous les médias d'un coup
        $medias = Media::whereIn('id', $request->media_ids)
            ->pluck('file_name', 'id'); // [id => file_name]

        $insertData = [];
        $increment = 1; // pour l'ordre

        foreach ($request->media_ids as $mediaId) {
            // vérifier doublon
            $exists = GalerieImage::where('dossier_id', $request->dossier_id)
                ->where('media_id', $mediaId)
                ->exists();

            if ($exists)
                continue;

            $insertData[] = [
                'dossier_id' => $request->dossier_id,
                'media_id' => $mediaId,
                'titre' => $medias[$mediaId] ?? 'Sans titre', // ici
                'ordre' => $lastOrder + $increment,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $increment++;
        }

        GalerieImage::insert($insertData);

        return response()->json(['success' => true]);
    }
    // ImagesByDossier
    public function getImagesByDossier($dossierId)
    {
        $dossier = GalerieDossier::with([
            'images' => function ($query) {
                $query->with('media')->orderBy('ordre');
            }
        ])->findOrFail($dossierId);

        return response()->json([
            'dossier' => $dossier->only([
                'id',
                'nom',
                'description',
                'is_visible'
            ]),
            'images' => $dossier->images
        ]);
    }

    // Seulement les infos
    public function update(Request $request, $id)
    {
        $image = GalerieImage::findOrFail($id);

        $request->validate([
            'titre' => 'nullable|string',
            'is_visible' => 'boolean',
        ]);

        $image->update($request->only('titre', 'is_visible'));

        return response()->json($image->load('media'));
    }

    // Retirer du dossier
    public function delete($id)
    {
        $image = GalerieImage::findOrFail($id);
        $image->delete();

        return response()->json(['message' => 'Image supprimée du dossier']);
    }

    public function deleteMultiple(Request $request)
    {
        $ids = $request->ids;
        GalerieImage::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => count($request->ids) . ' image(s) supprimée(s)'
        ]);
    }

    public function toggle($id)
    {
        $image = GalerieImage::findOrFail($id);
        $image->is_visible = !$image->is_visible;
        $image->save();

        return response()->json([
            'message' => 'Visibilité de l\'image changé !',
            'data' => $image
        ]);
    }

    public function reorderImages(Request $request)
    {
        $request->validate([
            '*.id' => 'required|exists:galerie_images,id',
            '*.ordre' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {

            foreach ($request->all() as $item) {
                GalerieImage::where('id', $item['id'])
                    ->update(['ordre' => $item['ordre']]);
            }

            DB::commit();

            return response()->json(['message' => 'Ordre mis à jour']);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'error' => 'Erreur reorder',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

}

