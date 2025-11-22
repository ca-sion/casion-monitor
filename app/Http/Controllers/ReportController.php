<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use App\Services\ReportService;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function showReport()
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        // 1. Définir la date du rapport
        $endDate = Carbon::today();

        // 2. Générer le rapport structuré
        $reportDailyData = resolve(ReportService::class)->generateReport($athlete, 'daily', $endDate);
        $reportWeeklyData = resolve(ReportService::class)->generateReport($athlete, 'weekly', $endDate);
        $reportMonthlyData = resolve(ReportService::class)->generateReport($athlete, 'monthly', $endDate);
        $reportBiannualData = resolve(ReportService::class)->generateReport($athlete, 'biannual', $endDate);

        $reportData = $reportDailyData;
        $reportData['sections'] += $reportWeeklyData['sections'];
        $reportData['sections'] += $reportMonthlyData['sections'];
        $reportData['sections'] += $reportBiannualData['sections'];
        $reportData['glossary'] += $reportWeeklyData['glossary'];
        $reportData['glossary'] += $reportMonthlyData['glossary'];
        $reportData['glossary'] += $reportBiannualData['glossary'];

        // 3. Passer les données à la vue
        return view('reports.show', [
            'athlete' => $athlete,
            'report'  => $reportData,
        ]);
    }

    public function ai()
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        // 1. Définir les dates
        $startDate = Carbon::today()->subMonths(2);
        $endDate = Carbon::today();

        // 2. Générer les CSV
        $csvMetrics = $athlete->metrics
            ->where('date', '>=', $startDate)
            ->sortBy('date')
            ->map(fn($metric) => $metric->date->toDateString().','.$metric->metric_type->value.','.$metric->value)
            ->prepend('date;type;value')
            ->implode('
');
        $csvCalculatedMetrics = $athlete->calculatedMetrics
            ->where('date', '>=', $startDate)
            ->sortBy('date')
            ->map(fn($metric) => $metric->date->toDateString().','.$metric->type->value.','.$metric->value)
            ->prepend('date;type;value')
            ->implode('
');
        $csvFeedbacks = $athlete->feedbacks
            ->where('date', '>=', $startDate)
            ->sortBy('date')
            ->map(fn($metric) => $metric->date->toDateString().','.$metric->author_type.','.$metric->content)
            ->prepend('date;author_type;content')
            ->implode('
');

        // 3. Passer les données à la vue
        return view('reports.ai', [
            'athlete' => $athlete,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'csvMetrics'  => $csvMetrics,
            'csvCalculatedMetrics' => $csvCalculatedMetrics,
            'csvFeedbacks' => $csvFeedbacks,
        ]);
    }
}
