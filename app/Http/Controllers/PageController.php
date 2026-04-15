<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Section;
use App\Models\Subsection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use HTMLPurifier;
use HTMLPurifier_Config;


class PageController extends Controller
{
    // 🔍 Liste des pages avec relations
    public function index()
    {
        return Page::with(['sections.subsections'])
            ->orderByRaw('`order` IS NULL, `order` ASC') // 👈 NULLs à la fin
            ->get();
    }


    // ➕ Création complète : page + sections + sous-sections
    public function store(Request $request)
    {
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        DB::beginTransaction();

        try {
            // Création de la page sans l'image principale
            // 🧠 Gestion de l'ordre
            $maxOrder = Page::max('order') ?? 0;

            // Nettoyage + sécurisation
            $order = (int) $request->order;

            // Clamp (très important)
            $order = max(1, min($order ?: $maxOrder + 1, $maxOrder + 1));

            if ($order <= $maxOrder) {
                Page::where('order', '>=', $order)->increment('order');
            }

            // Création page
            $page = Page::create([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'template' => $request->template,
                'main_image' => null,
                'slug' => Str::slug($request->title),
                'order' => $order,
            ]);


            $pageId = $page->id;

            // Image principale
            if ($request->hasFile('main_image')) {
                $mainImagePath = $request->file('main_image')->store("pages/page_$pageId/main", 'public');
                $page->update(['main_image' => $mainImagePath]);
            }

            // Sections et sous-sections
            foreach ($request->input('sections', []) as $i => $sectionData) {
                $sectionImagePath = null;
                if ($request->hasFile("sections.$i.image")) {
                    $sectionImagePath = $request->file("sections.$i.image")->store("pages/page_$pageId/sections", 'public');
                }

                $section = $page->sections()->create([
                    'title' => $sectionData['title'],
                    'subtitle' => $sectionData['subtitle'] ?? '',
                    'image' => $sectionImagePath,
                    'order' => $sectionData['order'] ?? 1,
                ]);

                foreach ($sectionData['subsections'] ?? [] as $j => $subsectionData) {
                    $subImagePath = null;
                    if ($request->hasFile("sections.$i.subsections.$j.image")) {
                        $subImagePath = $request->file("sections.$i.subsections.$j.image")->store("pages/page_$pageId/subsections", 'public');
                    }

                    $cleanHtml = $purifier->purify($subsectionData['content'] ?? '');

                    $section->subsections()->create([
                        'title' => $subsectionData['title'],
                        'type' => $subsectionData['type'],
                        'content' => $cleanHtml,
                        'date' => $subsectionData['date'] === 'null' || empty($subsectionData['date']) ? null : $subsectionData['date'],
                        'prix' => $subsectionData['prix'] ?? null,
                        'image' => $subImagePath,
                        'link' => $subsectionData['link'],
                        'order' => $subsectionData['order'] ?? 1,
                        'publish_at' => $subsectionData['publish_at'] ?? null,
                    ]);
                }
            }

            // À la fin, juste avant commit
            $page->touch();

            DB::commit();
            return response()->json(['message' => 'Page créée avec succès'], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ✏️ Mise à jour d’une page complète (sections & sous-sections comprises)

    public function update(Request $request, $id)
    {
        // Configuration de HTMLPurifier pour nettoyer le HTML des sous-sections
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        DB::beginTransaction();

        try {
            // Récupération de la page avec ses sections et sous-sections
            $page = Page::with('sections.subsections')->findOrFail($id);
            $pageId = $page->id;

            /*
            |--------------------------------------------------------------------------
            | 1. Gestion de l'image principale de la page
            |--------------------------------------------------------------------------
            */
            // Suppression demandée depuis le front
            if ($request->input('delete_main_image') === "1" && $page->main_image) {
                Storage::disk('public')->delete($page->main_image);
                $mainImagePath = null;
            } else {
                // Gérer remplacement image principale si fournie
                if ($request->hasFile('main_image') && $page->main_image) {
                    Storage::disk('public')->delete($page->main_image);
                }
                $mainImagePath = $request->hasFile('main_image')
                    ? $request->file('main_image')->store("pages/page_$pageId/main", 'public')
                    : $page->main_image;
            }

            // Mise à jour des informations principales de la page
            $page->update([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'template' => $request->template,
                'main_image' => $mainImagePath,
                'slug' => Str::slug($request->title),
                'order' => $request->order ?? null,
            ]);

            $sectionIdsToKeep = [];      // Liste des sections conservées
            /*
            |--------------------------------------------------------------------------
            | 2. Parcours des sections envoyées par le frontend
            |--------------------------------------------------------------------------
            */
            foreach ($request->input('sections', []) as $i => $sectionData) {
                // Récupère une section existante ou null
                $section = isset($sectionData['id'])
                    ? $page->sections()->where('id', $sectionData['id'])->first()
                    : null;

                // Chemin de l'image de section (ancienne ou à remplacer)
                $sectionImagePath = $section->image ?? null;

                // Suppression de l'image si demandé par le frontend
                if ($request->input("sections.$i.delete_image") === "1") {
                    if ($sectionImagePath) {
                        Storage::disk('public')->delete($sectionImagePath);
                    }
                    $sectionImagePath = null;
                }

                // Upload d'une nouvelle image si fournie
                if ($request->hasFile("sections.$i.image")) {
                    if ($sectionImagePath) {
                        Storage::disk('public')->delete($sectionImagePath);
                    }
                    $sectionImagePath = $request->file("sections.$i.image")->store("pages/page_$pageId/sections", 'public');
                }

                // Mise à jour ou création de la section
                if ($section) {
                    $section->update([
                        'title' => $sectionData['title'],
                        'subtitle' => $sectionData['subtitle'] ?? '',
                        'image' => $sectionImagePath,
                        'order' => $sectionData['order'] ?? 1,
                    ]);
                } else {
                    $section = $page->sections()->create([
                        'title' => $sectionData['title'],
                        'subtitle' => $sectionData['subtitle'] ?? '',
                        'image' => $sectionImagePath,
                        'order' => $sectionData['order'] ?? 1,
                    ]);
                }

                $sectionIdsToKeep[] = $section->id;

                /*
                |--------------------------------------------------------------------------
                | 3. Sous-sections liées à cette section
                |--------------------------------------------------------------------------
                */
                foreach ($sectionData['subsections'] ?? [] as $j => $subsectionData) {
                    $sub = isset($subsectionData['id'])
                        ? $section->subsections()->where('id', $subsectionData['id'])->first()
                        : null;

                    $subImagePath = $sub->image ?? null;

                    // Suppression de l'image si demandée
                    if ($request->input("sections.$i.subsections.$j.delete_image") === "1") {
                        if ($subImagePath) {
                            Storage::disk('public')->delete($subImagePath);
                        }
                        $subImagePath = null;
                    }

                    // Upload nouvelle image de sous-section
                    if ($request->hasFile("sections.$i.subsections.$j.image")) {
                        if ($subImagePath) {
                            Storage::disk('public')->delete($subImagePath);
                        }
                        $subImagePath = $request->file("sections.$i.subsections.$j.image")->store("pages/page_$pageId/subsections", 'public');
                    }

                    // Nettoyage du HTML (éditeur richtext)
                    $cleanHtml = $purifier->purify($subsectionData['content'] ?? '');

                    // Mise à jour ou création de la sous-section
                    if ($sub) {
                        $sub->update([
                            'title' => $subsectionData['title'],
                            'type' => $subsectionData['type'],
                            'content' => $cleanHtml,
                            'date' => empty($subsectionData['date']) || $subsectionData['date'] === 'null' ? null : $subsectionData['date'],
                            'prix' => $subsectionData['prix'] ?? null,
                            'image' => $subImagePath,
                            'link' => $subsectionData['link'],
                            'order' => $subsectionData['order'] ?? 1,
                            'publish_at' => $subsectionData['publish_at'] ?? null,
                        ]);
                    } else {
                        $sub = $section->subsections()->create([
                            'title' => $subsectionData['title'],
                            'type' => $subsectionData['type'],
                            'content' => $cleanHtml,
                            'date' => empty($subsectionData['date']) || $subsectionData['date'] === 'null' ? null : $subsectionData['date'],
                            'prix' => $subsectionData['prix'] ?? null,
                            'image' => $subImagePath,
                            'link' => $subsectionData['link'],
                            'order' => $subsectionData['order'] ?? 1,
                            'publish_at' => $subsectionData['publish_at'] ?? null,
                        ]);
                    }

                    $subsectionIdsToKeep[] = $sub->id;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 4. Suppression des sections non conservées
            |--------------------------------------------------------------------------
            */
            $page->sections()->whereNotIn('id', $sectionIdsToKeep)->each(function ($section) {
                // Supprimer image de section
                if ($section->image)
                    Storage::disk('public')->delete($section->image);

                // Supprimer toutes les sous-sections de cette section
                $section->subsections->each(function ($sub) {
                    if ($sub->image)
                        Storage::disk('public')->delete($sub->image);
                    $sub->delete();
                });

                $section->delete();
            });

            /*
            |--------------------------------------------------------------------------
            | 5. Suppression explicite de sous-sections (via deleted_subsections[])
            |--------------------------------------------------------------------------
            */
            if ($request->has('deleted_subsections')) {
                foreach ($request->input('deleted_subsections') as $deletedId) {
                    $sub = Subsection::find($deletedId);
                    if ($sub) {
                        if ($sub->image) {
                            Storage::disk('public')->delete($sub->image);
                        }
                        $sub->delete();
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | 6. Finalisation
            |--------------------------------------------------------------------------
            */
            $page->touch(); // Mise à jour du timestamp `updated_at`
            DB::commit();

            return response()->json(['message' => 'Page mise à jour avec succès']);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ❌ Suppression complète
    public function destroy($id)
    {
        $page = Page::findOrFail($id);
        // Supprimer le dossier entier
        Storage::disk('public')->deleteDirectory("pages/page_{$page->id}");
        $page->delete();
        return response()->json(['message' => 'Page supprimée avec succès', 'status' => "deleted"]);
    }

    // 🔎 Afficher une page par slug (pour le front)
    public function show($slug)
    {
        $now = now();

        $page = Page::with([
            'sections.subsections' => function ($query) use ($now) {
                $query->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', $now);
            }
        ])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($page);
    }


    // 🔎 Afficher une page par id (pour le front-office)
    public function get($id)
    {
        $page = Page::with('sections.subsections')->findOrFail($id);
        return response()->json($page);
    }

}
