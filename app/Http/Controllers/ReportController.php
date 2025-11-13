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

        $reportData = $reportDailyData;
        $reportData['sections'] += $reportWeeklyData['sections'];
        $reportData['sections'] += $reportMonthlyData['sections'];
        $reportData['glossary'] += $reportWeeklyData['glossary'];
        $reportData['glossary'] += $reportMonthlyData['glossary'];
        
        // 3. Passer les données à la vue
        return view('reports.show', [
            'athlete' => $athlete,
            'report' => $reportData,
        ]);
    }

    public function showMonthlyReport()
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }
        
        // 1. Définir la date du rapport
        $endDate = Carbon::today(); 
        
        // 2. Générer le rapport structuré
        $reportData = resolve(ReportService::class)->generateReport($athlete, 'monthly', $endDate);
        
        // 3. Passer les données à la vue
        return view('reports.monthly', [
            'athlete' => $athlete,
            'report' => $reportData,
        ]);
    }

    public function showBiannualReport()
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }
        
        // 1. Définir la date du rapport
        $endDate = Carbon::today(); 
        
        // 2. Générer le rapport structuré
        $reportData = resolve(ReportService::class)->generateReport($athlete, 'biannual', $endDate);
        
        // 3. Passer les données à la vue
        return view('reports.biannual', [
            'athlete' => $athlete,
            'report' => $reportData,
        ]);
    }
}
