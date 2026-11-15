<?php

namespace App\Http\Controllers;

use App\Models\Content;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
                        'message' => 'Veuillez sélectionner au moins un plan.',
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

        if (! empty($planIds)) {
            $content->plans()->sync($planIds);
        }

        return response()->json([
            'message' => 'Contenu ajouté !',
            'data' => $content->load('plans'),
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
            'publish_at' => ['nullable', 'date', 'after_or_equal:'.$minDate],

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
                        'message' => 'Veuillez sélectionner au moins un plan.',
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

        $content->plans()->sync($planIds);

        return response()->json([
            'message' => 'Contenu mis à jour avec succès !',
            'data' => $content->load('plans'),
        ]);
    }

    /**
     * Dette #10 : Pagination sur la liste des contenus.
     */
    public function index(Request $request)
    {
        $query = Content::with('plans')->latest();

        if ($request->has('page') || $request->has('per_page')) {
            return $query->paginate($request->integer('per_page', 25));
        }

        return $query->paginate($request->integer('per_page', 25));
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
        $type = $request->query('type', 'all');

        $subscription = $adherent->activeSubscription()->with('plan')->first();
        $activePlanIds = $subscription ? [$subscription->subscription_plan_id] : [];
        $isAStudentPlan = ($subscription && $subscription->plan)
            ? $subscription->plan->is_student_plan
            : false;

        $query = Content::with('plans');

        $query->where(function ($q) use ($activePlanIds, $isAStudentPlan) {
            $q->where('is_public', true);

            if (! empty($activePlanIds)) {
                $q->orWhereHas('plans', function ($q2) use ($activePlanIds) {
                    $q2->whereIn('subscription_plans.id', $activePlanIds);
                });
            }

            if ($isAStudentPlan) {
                $q->orWhere('is_student_only', true);
            }
        });

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $query->where(function ($q) {
            $q->whereNull('publish_at')
                ->orWhere('publish_at', '<=', now());
        });

        return $query->latest()->get();
    }
}
