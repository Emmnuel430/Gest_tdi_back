<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Subsection;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PageController extends Controller
{
    // 🔍 Liste des pages avec relations (Dette #10 : Support pagination + filtrage public)
    public function index(Request $request)
    {
        $isAdmin = auth('sanctum')->check();
        $now = now();

        $query = Page::with([
            'sections.subsections' => function ($q) use ($isAdmin, $now) {
                if (! $isAdmin) {
                    $q->where(function ($subQ) use ($now) {
                        $subQ->whereNull('publish_at')
                             ->orWhere('publish_at', '<=', $now);
                    });
                }
            },
        ])->orderByRaw('`order` IS NULL, `order` ASC');

        if ($request->has('page') || $request->has('per_page')) {
            return $query->paginate($request->integer('per_page', 25));
        }

        return $query->get();
    }

    // ➕ Création complète : page + sections + sous-sections
    public function store(Request $request)
    {
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        DB::beginTransaction();

        try {
            $maxOrder = Page::max('order') ?? 0;
            $order = (int) $request->order;
            $order = max(1, min($order ?: $maxOrder + 1, $maxOrder + 1));

            if ($order <= $maxOrder) {
                Page::where('order', '>=', $order)->increment('order');
            }

            $page = Page::create([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'template' => $request->template,
                'main_image' => null,
                'slug' => Str::slug($request->title),
                'order' => $order,
            ]);

            $pageId = $page->id;

            if ($request->hasFile('main_image')) {
                $mainImagePath = $request->file('main_image')->store("pages/page_$pageId/main", 'public');
                $page->update(['main_image' => $mainImagePath]);
            }

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
                    'is_visible' => isset($sectionData['is_visible'])
                        ? filter_var($sectionData['is_visible'], FILTER_VALIDATE_BOOLEAN)
                        : true,
                ]);

                foreach ($sectionData['subsections'] ?? [] as $j => $subsectionData) {
                    $subImagePath = null;
                    if ($request->hasFile("sections.$i.subsections.$j.image")) {
                        $subImagePath = $request->file("sections.$i.subsections.$j.image")->store("pages/page_$pageId/subsections", 'public');
                    }

                    $cleanHtml = $purifier->purify($subsectionData['content'] ?? '');

                    $section->subsections()->create([
                        'title' => $subsectionData['title'],
                        'type' => $subsectionData['type'] ?? 'classique',
                        'content' => $cleanHtml,
                        'date' => ($subsectionData['date'] ?? null) === 'null' || empty($subsectionData['date']) ? null : $subsectionData['date'],
                        'prix' => $subsectionData['prix'] ?? null,
                        'image' => $subImagePath,
                        'link' => $subsectionData['link'] ?? null,
                        'order' => $subsectionData['order'] ?? 1,
                        'publish_at' => $subsectionData['publish_at'] ?? null,
                    ]);
                }
            }

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
        $config = HTMLPurifier_Config::createDefault();
        $purifier = new HTMLPurifier($config);
        DB::beginTransaction();

        try {
            $page = Page::with('sections.subsections')->findOrFail($id);
            $pageId = $page->id;

            if ($request->input('delete_main_image') === '1' && $page->main_image) {
                Storage::disk('public')->delete($page->main_image);
                $mainImagePath = null;
            } else {
                if ($request->hasFile('main_image') && $page->main_image) {
                    Storage::disk('public')->delete($page->main_image);
                }
                $mainImagePath = $request->hasFile('main_image')
                    ? $request->file('main_image')->store("pages/page_$pageId/main", 'public')
                    : $page->main_image;
            }

            $page->update([
                'title' => $request->title,
                'subtitle' => $request->subtitle,
                'template' => $request->template,
                'main_image' => $mainImagePath,
                'slug' => Str::slug($request->title),
                'order' => $request->order ?? null,
            ]);

            $sectionIdsToKeep = [];
            foreach ($request->input('sections', []) as $i => $sectionData) {
                $section = isset($sectionData['id'])
                    ? $page->sections()->where('id', $sectionData['id'])->first()
                    : null;

                $sectionImagePath = $section->image ?? null;

                if ($request->input("sections.$i.delete_image") === '1') {
                    if ($sectionImagePath) {
                        Storage::disk('public')->delete($sectionImagePath);
                    }
                    $sectionImagePath = null;
                }

                if ($request->hasFile("sections.$i.image")) {
                    if ($sectionImagePath) {
                        Storage::disk('public')->delete($sectionImagePath);
                    }
                    $sectionImagePath = $request->file("sections.$i.image")->store("pages/page_$pageId/sections", 'public');
                }

                $isVisible = isset($sectionData['is_visible'])
                    ? filter_var($sectionData['is_visible'], FILTER_VALIDATE_BOOLEAN)
                    : ($section ? $section->is_visible : true);

                if ($section) {
                    $section->update([
                        'title' => $sectionData['title'],
                        'subtitle' => $sectionData['subtitle'] ?? '',
                        'image' => $sectionImagePath,
                        'order' => $sectionData['order'] ?? 1,
                        'is_visible' => $isVisible,
                    ]);
                } else {
                    $section = $page->sections()->create([
                        'title' => $sectionData['title'],
                        'subtitle' => $sectionData['subtitle'] ?? '',
                        'image' => $sectionImagePath,
                        'order' => $sectionData['order'] ?? 1,
                        'is_visible' => $isVisible,
                    ]);
                }

                $sectionIdsToKeep[] = $section->id;

                foreach ($sectionData['subsections'] ?? [] as $j => $subsectionData) {
                    $sub = isset($subsectionData['id'])
                        ? $section->subsections()->where('id', $subsectionData['id'])->first()
                        : null;

                    $subImagePath = $sub->image ?? null;

                    if ($request->input("sections.$i.subsections.$j.delete_image") === '1') {
                        if ($subImagePath) {
                            Storage::disk('public')->delete($subImagePath);
                        }
                        $subImagePath = null;
                    }

                    if ($request->hasFile("sections.$i.subsections.$j.image")) {
                        if ($subImagePath) {
                            Storage::disk('public')->delete($subImagePath);
                        }
                        $subImagePath = $request->file("sections.$i.subsections.$j.image")->store("pages/page_$pageId/subsections", 'public');
                    }

                    $cleanHtml = $purifier->purify($subsectionData['content'] ?? '');

                    if ($sub) {
                        $sub->update([
                            'title' => $subsectionData['title'],
                            'type' => $subsectionData['type'] ?? 'classique',
                            'content' => $cleanHtml,
                            'date' => empty($subsectionData['date']) || $subsectionData['date'] === 'null' ? null : $subsectionData['date'],
                            'prix' => $subsectionData['prix'] ?? null,
                            'image' => $subImagePath,
                            'link' => $subsectionData['link'] ?? null,
                            'order' => $subsectionData['order'] ?? 1,
                            'publish_at' => $subsectionData['publish_at'] ?? null,
                        ]);
                    } else {
                        $sub = $section->subsections()->create([
                            'title' => $subsectionData['title'],
                            'type' => $subsectionData['type'] ?? 'classique',
                            'content' => $cleanHtml,
                            'date' => empty($subsectionData['date']) || $subsectionData['date'] === 'null' ? null : $subsectionData['date'],
                            'prix' => $subsectionData['prix'] ?? null,
                            'image' => $subImagePath,
                            'link' => $subsectionData['link'] ?? null,
                            'order' => $subsectionData['order'] ?? 1,
                            'publish_at' => $subsectionData['publish_at'] ?? null,
                        ]);
                    }

                    $subsectionIdsToKeep[] = $sub->id;
                }
            }

            $page->sections()->whereNotIn('id', $sectionIdsToKeep)->each(function ($section) {
                if ($section->image) {
                    Storage::disk('public')->delete($section->image);
                }

                $section->subsections->each(function ($sub) {
                    if ($sub->image) {
                        Storage::disk('public')->delete($sub->image);
                    }
                    $sub->delete();
                });

                $section->delete();
            });

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

            $page->touch();
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
        Storage::disk('public')->deleteDirectory("pages/page_{$page->id}");
        $page->delete();

        return response()->json(['message' => 'Page supprimée avec succès', 'status' => 'deleted']);
    }

    // 🔎 Afficher une page par slug (pour le front)
    public function show($slug)
    {
        $now = now();

        $page = Page::with([
            'sections.subsections' => function ($query) use ($now) {
                $query->where(function ($subQ) use ($now) {
                    $subQ->whereNull('publish_at')
                         ->orWhere('publish_at', '<=', $now);
                });
            },
        ])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json($page);
    }

    // 🔎 Afficher une page par id (Dette #06 : Filtrage strict de publish_at pour tout appel non-admin)
    public function get($id)
    {
        $isAdmin = auth('sanctum')->check();
        $now = now();

        $page = Page::with([
            'sections.subsections' => function ($query) use ($isAdmin, $now) {
                if (! $isAdmin) {
                    $query->where(function ($subQ) use ($now) {
                        $subQ->whereNull('publish_at')
                             ->orWhere('publish_at', '<=', $now);
                    });
                }
            },
        ])->findOrFail($id);

        return response()->json($page);
    }
}
