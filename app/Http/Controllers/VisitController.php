<?php

namespace App\Http\Controllers;

use App\Models\DailyVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VisitController extends Controller
{
    // Stats globales
    public function stats(Request $request)
    {
        $period = $request->query('period', 'week');
        $today = now()->toDateString();

        // Valeurs communes
        $total = DailyVisit::sum('count');
        $currentToday = DailyVisit::where('date', $today)->value('count') ?? 0;

        $labels = [];
        $counts = [];

        switch ($period) {
            case 'day':
                $data = DailyVisit::whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->orderBy('date')->get();
                $labels = $data->map(fn($v) => \Carbon\Carbon::parse($v->date)->format('d-m'))->toArray();
                $counts = $data->pluck('count')->toArray();
                break;

            case 'month':
                // UNE SEULE REQUÊTE : Groupement par mois de l'année en cours
                $data = DailyVisit::selectRaw('MONTH(date) as month, SUM(count) as total')
                    ->whereYear('date', now()->year)
                    ->groupBy('month')
                    ->orderBy('month')
                    ->get();

                $labels = $data->map(fn($v) => \Carbon\Carbon::create()->month($v->month)->translatedFormat('F'))->toArray();
                $counts = $data->pluck('total')->toArray();
                break;

            case 'year':
                // UNE SEULE REQUÊTE : Groupement par année sur les 5 dernières années
                $data = DailyVisit::selectRaw('YEAR(date) as year, SUM(count) as total')
                    ->where('date', '>=', now()->subYears(5)->startOfYear())
                    ->groupBy('year')
                    ->orderBy('year')
                    ->get();

                $labels = $data->pluck('year')->toArray();
                $counts = $data->pluck('total')->toArray();
                break;

            case 'week':
            default:
                $startOfMonth = now()->startOfMonth();
                $endOfMonth = now()->endOfMonth();

                // ⚡ 1 seule requête SQL pour tout le mois au lieu de N requêtes dans la boucle
                $dailyVisits = DailyVisit::whereBetween('date', [
                    $startOfMonth->toDateString(),
                    $endOfMonth->toDateString()
                ])->pluck('count', 'date');

                $current = $startOfMonth->copy();

                while ($current <= $endOfMonth) {
                    $weekEnd = $current->copy()->endOfWeek()->min($endOfMonth);
                    $labels[] = $current->format('d/m') . ' → ' . $weekEnd->format('d/m');

                    $weekSum = 0;
                    $dayIter = $current->copy();
                    while ($dayIter <= $weekEnd) {
                        $weekSum += $dailyVisits[$dayIter->toDateString()] ?? 0;
                        $dayIter->addDay();
                    }

                    $counts[] = $weekSum;
                    $current = $weekEnd->copy()->addDay();
                }
                break;


        }

        return response()->json([
            'today' => $currentToday,
            'total' => $total,
            'labels' => $labels,
            'counts' => $counts,
            'period' => $period
        ]);
    }


    // Données pour graphique (optimisées et bornées aux 12 derniers mois)
    public function chart(Request $request)
    {
        $months = $request->integer('months', 12);
        $data = DailyVisit::where('date', '>=', now()->subMonths($months)->toDateString())
            ->orderBy('date', 'asc')
            ->get(['date', 'count']);

        return response()->json($data);
    }


    public function track(Request $request)
    {
        $today = now()->toDateString();

        // Identifiant visiteur unique (visitor_id client ou empreinte IP + User-Agent)
        $visitorId = $request->input('visitor_id');
        $identifier = $visitorId
            ? "vid:{$visitorId}"
            : (($request->ip() ?? '0.0.0.0') . '|' . ($request->userAgent() ?? 'unknown'));
        $cacheKey = "visit_" . md5($identifier) . "_{$today}";

        // ✅ 1. Check cache AVANT DB (ultra important perf : 1 seul comptage par visiteur unique et par jour)
        if (Cache::has($cacheKey)) {
            return response()->json(['status' => 'already_tracked']);
        }

        // ✅ 2. Lock pour éviter race condition (best practice Laravel)
        Cache::lock("create_visit_record_{$today}", 5)->get(function () use ($today) {
            DailyVisit::firstOrCreate(['date' => $today]);
        });

        // ✅ 3. Increment propre
        DailyVisit::where('date', $today)->increment('count');

        // ✅ 4. Marquer comme visité jusqu’à minuit
        Cache::put($cacheKey, true, now()->endOfDay());

        return response()->json(['status' => 'ok']);
    }
}
