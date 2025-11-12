<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use Illuminate\Support\Carbon;
use App\Services\ReportService;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function showDailyReport(Athlete $athlete, ReportService $reportService)
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }
        
        // 1. Définir la date du rapport
        $endDate = Carbon::today(); 
        
        // 2. Générer le rapport structuré
        $reportData = $reportService->generateReport($athlete, 'daily', $endDate);
        
        // 3. Passer les données à la vue
        return view('reports.daily', [
            'athlete' => $athlete,
            'report' => $reportData,
        ]);
    }

    public function showWeeklyReport(Athlete $athlete, ReportService $reportService)
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }
        
        // 1. Définir la date du rapport
        $endDate = Carbon::today(); 
        
        // 2. Générer le rapport structuré
        $reportData = $reportService->generateReport($athlete, 'weekly', $endDate);
        
        // 3. Passer les données à la vue
        return view('reports.weekly', [
            'athlete' => $athlete,
            'report' => $reportData,
        ]);
    }

    public function showMonthlyReport(Athlete $athlete, ReportService $reportService)
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }
        
        // 1. Définir la date du rapport
        $endDate = Carbon::today(); 
        
        // 2. Générer le rapport structuré
        $reportData = $reportService->generateReport($athlete, 'monthly', $endDate);
        
        // 3. Passer les données à la vue
        return view('reports.monthly', [
            'athlete' => $athlete,
            'report' => $reportData,
        ]);
    }

    public function showBiannualReport(Athlete $athlete, ReportService $reportService)
    {

        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }
        
        // 1. Définir la date du rapport
        $endDate = Carbon::today(); 
        
        // 2. Générer le rapport structuré
        $reportData = $reportService->generateReport($athlete, 'biannual', $endDate);
        
        // 3. Passer les données à la vue
        return view('reports.biannual', [
            'athlete' => $athlete,
            'report' => $reportData,
        ]);
    }
}
