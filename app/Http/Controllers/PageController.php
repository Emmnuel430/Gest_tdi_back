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
        return Page::with('sections.subsections')->get();
    }

    // ➕ Création complète : page + sections + sous-sections
    public function store(Request $request)
    {
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        DB::beginTransaction();

        try {
            // Création de la page sans l'image principale
            $page = Page::create([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'template' => $request->template,
                'main_image' => null, // temporaire
                'slug' => Str::slug($request->title),
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
                        'content' => $cleanHtml,
                        'date' => $subsectionData['date'] === 'null' || empty($subsectionData['date']) ? null : $subsectionData['date'],
                        'prix' => $subsectionData['prix'] ?? null,
                        'image' => $subImagePath,
                        'order' => $subsectionData['order'] ?? 1,
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
        // \Log::info($request->all());

        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        DB::beginTransaction();

        try {
            $page = Page::with('sections.subsections')->findOrFail($id);
            $pageId = $page->id;

            // Gérer l'image principale
            if ($request->hasFile('main_image') && $page->main_image) {
                Storage::disk('public')->delete($page->main_image);
            }
            $mainImagePath = $request->hasFile('main_image')
                ? $request->file('main_image')->store("pages/page_$pageId/main", 'public')
                : $page->main_image;

            $page->update([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'template' => $request->template,
                'main_image' => $mainImagePath,
                'slug' => Str::slug($request->title),
            ]);

            $sectionIdsToKeep = [];
            $subsectionIdsToKeep = [];

            foreach ($request->input('sections', []) as $i => $sectionData) {
                $section = isset($sectionData['id'])
                    ? $page->sections()->where('id', $sectionData['id'])->first()
                    : null;

                // Image section
                $sectionImagePath = $section->image ?? null;
                if ($request->hasFile("sections.$i.image")) {
                    if ($sectionImagePath) {
                        Storage::disk('public')->delete($sectionImagePath);
                    }
                    $sectionImagePath = $request->file("sections.$i.image")->store("pages/page_$pageId/sections", 'public');
                }

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

                foreach ($sectionData['subsections'] ?? [] as $j => $subsectionData) {
                    $sub = isset($subsectionData['id'])
                        ? $section->subsections()->where('id', $subsectionData['id'])->first()
                        : null;

                    $subImagePath = $sub->image ?? null;
                    if ($request->hasFile("sections.$i.subsections.$j.image")) {
                        if ($subImagePath) {
                            Storage::disk('public')->delete($subImagePath);
                        }
                        $subImagePath = $request->file("sections.$i.subsections.$j.image")->store("pages/page_$pageId/subsections", 'public');
                    }

                    if ($sub) {
                        $cleanHtml = $purifier->purify($subsectionData['content'] ?? '');
                        $sub->update([
                            'title' => $subsectionData['title'],
                            'content' => $cleanHtml,
                            'date' => empty($subsectionData['date']) || $subsectionData['date'] === 'null' ? null : $subsectionData['date'],
                            'prix' => $subsectionData['prix'] ?? null,
                            'image' => $subImagePath,
                            'order' => $subsectionData['order'] ?? 1,
                        ]);
                    } else {
                        $cleanHtml = $purifier->purify($subsectionData['content'] ?? '');
                        $sub = $section->subsections()->create([
                            'title' => $subsectionData['title'],
                            'content' => $cleanHtml,
                            'date' => empty($subsectionData['date']) || $subsectionData['date'] === 'null' ? null : $subsectionData['date'],
                            'prix' => $subsectionData['prix'] ?? null,
                            'image' => $subImagePath,
                            'order' => $subsectionData['order'] ?? 1,
                        ]);
                    }

                    $subsectionIdsToKeep[] = $sub->id;
                }
            }

            // Supprimer les sections et sous-sections non présentes
            $page->sections()->whereNotIn('id', $sectionIdsToKeep)->each(function ($section) {
                if ($section->image)
                    Storage::disk('public')->delete($section->image);
                $section->subsections->each(function ($sub) {
                    if ($sub->image)
                        Storage::disk('public')->delete($sub->image);
                    $sub->delete();
                });
                $section->delete();
            });

            $page->touch(); // Met à jour updated_at
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
        $page = Page::with('sections.subsections')->where('slug', $slug)->firstOrFail();
        return response()->json($page);
    }

    // 🔎 Afficher une page par id (pour le front)
    public function get($id)
    {
        $page = Page::with('sections.subsections')->findOrFail($id);
        return response()->json($page);
    }

}
