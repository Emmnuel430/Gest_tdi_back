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
        $type = $request->query('type', 'all');

        // Log::info('=== START ===', [
        //     'user_id' => $adherent->id,
        //     'type' => $type,
        // ]);

        return (function () use ($adherent, $request, $type) {

            $subscription = $adherent->activeSubscription()->with('plan')->first();

            // Log::info('Subscription', [
            //     'exists' => !!$subscription,
            //     'data' => $subscription
            // ]);

            $activePlanIds = $subscription ? [$subscription->subscription_plan_id] : [];

            // Log::info('Active Plans', [
            //     'ids' => $activePlanIds
            // ]);

            $isAStudentPlan = ($subscription && $subscription->plan)
                ? $subscription->plan->is_student_plan
                : false;

            // Log::info('Is Student Plan', [
            //     'value' => $isAStudentPlan
            // ]);

            // TEST DB DIRECT
            // Log::info('TOTAL contents', [
            //     'count' => Content::count()
            // ]);

            // Log::info('TOTAL cours', [
            //     'count' => Content::where('type', 'cours')->count()
            // ]);

            $query = Content::with('plans');

            // 🔍 AVANT FILTRE
            // Log::info('Avant filtres', [
            //     'count' => (clone $query)->count()
            // ]);

            // VISIBILITÉ
            $query->where(function ($q) use ($activePlanIds, $isAStudentPlan) {
                $q->where('is_public', true);

                if (!empty($activePlanIds)) {
                    $q->orWhereHas('plans', function ($q2) use ($activePlanIds) {
                        $q2->whereIn('subscription_plans.id', $activePlanIds); // Précise la table
                    });
                }

                if ($isAStudentPlan) {
                    $q->orWhere('is_student_only', true);
                }
            });


            // Log::info('Après visibilité', [
            //     'count' => (clone $query)->count()
            // ]);
            // Log::info('IDs après visibilité', ['ids' => (clone $query)->pluck('id', 'type')]);

            // TYPE
            if ($type !== 'all') {
                $query->where('type', $type);
            }

            // Log::info('Après type', [
            //     'type' => $type,
            //     'count' => (clone $query)->count()
            // ]);

            // PUBLISH
            $query->where(function ($q) {
                $q->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', now());
            });

            // Log::info('Après publish_at', [
            //     'count' => (clone $query)->count()
            // ]);

            $results = $query->latest()->get();

            // Log::info('FINAL RESULT', [
            //     'count' => $results->count(),
            //     'ids' => $results->pluck('id')
            // ]);

            return $results;
        })();
    }


}
