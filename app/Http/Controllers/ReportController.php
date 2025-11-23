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
        $reports = [
            'daily'    => $sectionForGlossary = resolve(ReportService::class)->generateReport($athlete, 'daily', $endDate),
            'weekly'   => resolve(ReportService::class)->generateReport($athlete, 'weekly', $endDate),
            'monthly'  => resolve(ReportService::class)->generateReport($athlete, 'monthly', $endDate),
            'biannual' => resolve(ReportService::class)->generateReport($athlete, 'biannual', $endDate),
        ];
        $glossary = $sectionForGlossary['glossary'];

        // 3. Passer les données à la vue
        return view('reports.show', [
            'athlete'  => $athlete,
            'reports'  => $reports,
            'glossary' => $glossary,
            'endDate'  => $endDate,
        ]);
    }

    public function ai()
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        // 1. Définir les dates
        $period = request()->query('period', '40d');
        $endDate = Carbon::today();
        $startDate = match ($period) {
            '2m'    => Carbon::today()->subMonths(2),
            '3m'    => Carbon::today()->subMonths(3),
            '6m'    => Carbon::today()->subMonths(6),
            '1y'    => Carbon::today()->subYear(),
            '2y'    => Carbon::today()->subYears(2),
            default => Carbon::today()->subDays(40),
        };

        // 2. Générer les CSV
        $csvMetrics = $athlete->metrics
            ->where('date', '>=', $startDate)
            ->sortBy('date')
            ->map(fn ($metric) => $metric->date->toDateString().','.$metric->metric_type->value.','.$metric->value)
            ->prepend('date;type;value')
            ->implode('
');
        $csvCalculatedMetrics = $athlete->calculatedMetrics
            ->where('date', '>=', $startDate)
            ->sortBy('date')
            ->map(fn ($metric) => $metric->date->toDateString().','.$metric->type->value.','.$metric->value)
            ->prepend('date;type;value')
            ->implode('
');
        $csvFeedbacks = $athlete->feedbacks
            ->where('date', '>=', $startDate)
            ->sortBy('date')
            ->map(fn ($metric) => $metric->date->toDateString().','.$metric->author_type.','.$metric->content)
            ->prepend('date;author_type;content')
            ->implode('
');

        // 3. Passer les données à la vue
        return view('reports.ai', [
            'athlete'              => $athlete,
            'startDate'            => $startDate,
            'endDate'              => $endDate,
            'csvMetrics'           => $csvMetrics,
            'csvCalculatedMetrics' => $csvCalculatedMetrics,
            'csvFeedbacks'         => $csvFeedbacks,
            'downloadUrl'          => route('athletes.reports.ai.csv', ['hash' => $athlete->hash, 'period' => $period]),
            'period'               => $period,
        ]);
    }

    public function aiCsvDownload()
    {
        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        $period = request()->query('period', '6m'); // Default to 6 months
        $endDate = Carbon::today();
        $startDate = match ($period) {
            '40d'   => Carbon::today()->subDays(40),
            '2m'    => Carbon::today()->subMonths(2),
            '3m'    => Carbon::today()->subMonths(3),
            '6m'    => Carbon::today()->subMonths(6),
            '1y'    => Carbon::today()->subYear(),
            '2y'    => Carbon::today()->subYears(2),
            default => Carbon::today()->subMonths(6), // Default to 6 months
        };

        $rows = ['date;category;type;value;author_type;content'];

        // Add metrics
        foreach ($athlete->metrics->where('date', '>=', $startDate)->sortBy('date') as $metric) {
            $rows[] = implode(';', [
                $metric->date->toDateString(),
                'metric',
                $metric->metric_type->value,
                $metric->value,
                '', // author_type (not applicable)
                '', // content (not applicable)
            ]);
        }

        // Add calculated metrics
        foreach ($athlete->calculatedMetrics->where('date', '>=', $startDate)->sortBy('date') as $metric) {
            $rows[] = implode(';', [
                $metric->date->toDateString(),
                'calculated_metric',
                $metric->type->value,
                $metric->value,
                '', // author_type (not applicable)
                '', // content (not applicable)
            ]);
        }

        // Add feedbacks
        foreach ($athlete->feedbacks->where('date', '>=', $startDate)->sortBy('date') as $feedback) {
            $rows[] = implode(';', [
                $feedback->date->toDateString(),
                'feedback',
                '', // type (not applicable)
                '', // value (not applicable)
                $feedback->author_type,
                str_replace(';', ',', $feedback->content), // Replace semicolons in content to avoid breaking CSV
            ]);
        }

        $csvContent = implode("\n", $rows);

        $filename = 'report-'.str($athlete->name)->slug('_').'-'.Carbon::now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
