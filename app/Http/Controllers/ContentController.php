<?php

namespace App\Http\Controllers;

use App\Models\Content;
use App\Models\SubscriptionPlan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ContentController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:formation,cours,evenement',
            'content' => 'nullable|string',
            'lien' => 'nullable|url',
            'publish_at' => 'nullable|date',

            'visibility' => 'required|in:public,students,plans',

            'plan_ids' => 'nullable|array',
            'plan_ids.*' => 'exists:subscription_plans,id',
        ]);

        // séparer relation
        $planIds = $validated['plan_ids'] ?? [];

        $isPublic = false;
        $isStudentOnly = false;

        switch ($validated['visibility']) {
            case 'public':
                $isPublic = true;
                $planIds = [];
                break;

            case 'students':
                $isStudentOnly = true;
                $planIds = [];
                break;

            case 'plans':
                if (empty($planIds)) {
                    return response()->json([
                        'message' => 'Veuillez sélectionner au moins un plan.'
                    ], 422);
                }
                $isPublic = false;
                $isStudentOnly = false;
                break;
        }

        $data = [
            'title' => $validated['title'],
            'type' => $validated['type'],
            'content' => $validated['content'] ?? null,
            'lien' => $validated['lien'] ?? null,
            'publish_at' => $validated['publish_at'] ?? null,
            'is_public' => $isPublic,
            'is_student_only' => $isStudentOnly,
        ];

        $content = Content::create($data);

        // sync pivot
        if (!empty($planIds)) {
            $content->plans()->sync($planIds);
        }

        return response()->json([
            'message' => 'Contenu ajouté !',
            'data' => $content->load('plans')
        ]);
    }

    public function update(Request $request, $id)
    {
        $minDate = Carbon::now()->addMinutes(5);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:formation,cours,evenement',
            'content' => 'nullable|string',
            'lien' => 'nullable|url',
            'publish_at' => ['nullable', 'date', 'after_or_equal:' . $minDate],

            'visibility' => 'required|in:public,students,plans',

            'plan_ids' => 'nullable|array',
            'plan_ids.*' => 'exists:subscription_plans,id',
        ]);

        $planIds = $validated['plan_ids'] ?? [];
        $isPublic = false;
        $isStudentOnly = false;

        switch ($validated['visibility']) {
            case 'public':
                $isPublic = true;
                $planIds = [];
                break;

            case 'students':
                $isStudentOnly = true;
                $planIds = [];
                break;

            case 'plans':
                if (empty($planIds)) {
                    return response()->json([
                        'message' => 'Veuillez sélectionner au moins un plan.'
                    ], 422);
                }
                $isPublic = false;
                $isStudentOnly = false;
                break;
        }

        $data = [
            'title' => $validated['title'],
            'type' => $validated['type'],
            'content' => $validated['content'] ?? null,
            'lien' => $validated['lien'] ?? null,
            'publish_at' => $validated['publish_at'] ?? null,
            'is_public' => $isPublic,
            'is_student_only' => $isStudentOnly,
        ];

        $content = Content::findOrFail($id);
        $content->update($data);

        // sync (important : même si vide → detach)
        $content->plans()->sync($planIds);

        return response()->json([
            'message' => 'Contenu mis à jour avec succès !',
            'data' => $content->load('plans')
        ]);
    }

    public function index()
    {
        return Content::with('plans')
            ->latest()
            ->get();
    }

    public function show($id)
    {
        $content = Content::with('plans')->findOrFail($id);
        return response()->json(['data' => $content]);
    }

    public function destroy($id)
    {
        $content = Content::findOrFail($id);
        $content->delete();
        return response()->json(['status' => 'deleted', 'message' => 'Contenu supprimé avec succès !']);
    }

    public function byType(Request $request)
    {
        $adherent = auth()->user();
        $now = now();
        $type = $request->type ?? 'all';


        $cacheKey = "contents_user_{$adherent->id}_type_{$type}";

        $contents = Cache::tags(["user_contents_{$adherent->id}"])->remember($cacheKey, 3600 * 24, function () use ($adherent, $request, $now) {
            // récupérer les plans actifs du user
            $activePlanIds = $adherent->activeSubscriptions()
                ->pluck('subscription_plan_id');

            // check si l'un de ces plans est  un plan étudiant
            $isAStudentPlan = SubscriptionPlan::whereIn('id', $activePlanIds)
                ->where('is_student_plan', true)
                ->exists();

            return Content::with('plans')
                ->where(function ($query) use ($activePlanIds, $isAStudentPlan) {
                    // marqué comme public
                    $query->where('is_public', true)

                        // réservé aux étudiants
                        ->orWhere(function ($q) use ($isAStudentPlan) {
                        if ($isAStudentPlan) {
                            $q->where('is_student_only', true);
                        }
                    })

                        // explicitement lié à l'un des plans actifs de l'utilisateur
                        ->orWhereHas('plans', function ($q) use ($activePlanIds) {
                        $q->whereIn('subscription_plan_id', $activePlanIds);
                    });

                })
                // Filtre par type : ['formation', 'cours']
                ->when($request->type, fn($q) => $q->where('type', $request->type))
                // exclut les contenus non encore publiés
                ->where(function ($q) use ($now) {
                    $q->whereNull('publish_at')
                        ->orWhere('publish_at', '<=', $now);
                })
                ->latest()
                ->get();
        });

        return response()->json($contents);
    }
}
