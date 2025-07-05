<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\MetricStatisticsService;

class AthleteController extends Controller
{
    protected $metricStatisticsService;

    public function __construct(MetricStatisticsService $metricStatisticsService)
    {
        $this->metricStatisticsService = $metricStatisticsService;
    }

    public function dashboard(Request $request): View|JsonResponse
    {
        $athlete = Auth::guard('athlete')->user();

        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        $dashboardMetricTypes = [
            MetricType::MORNING_HRV,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_BODY_WEIGHT_KG,
            // MetricType::MORNING_SLEEP_QUALITY,
            // MetricType::POST_SESSION_SESSION_LOAD,
        ];

        $period = $request->input('period', 'last_30_days');
        $days = match($period) {
            'last_7_days' => 7,
            'last_14_days' => 14, 
            'last_30_days' => 30,
            'last_90_days' => 90,
            'last_6_months' => 180,
            'last_year' => 365,
            'all_time' => 500,
            default => 50
        };

        $metricsDataForDashboard = [];
        foreach ($dashboardMetricTypes as $metricType) {
            $metricsDataForDashboard[$metricType->value] = $this->metricStatisticsService->getDashboardMetricData($athlete, $metricType, $period);
        }

        $dailyMetricsHistory = $this->metricStatisticsService->getLatestMetricsGroupedByDate($athlete, $days);

        $periodOptions = [
            'last_7_days'    => '7 derniers jours',
            'last_14_days'   => '14 derniers jours',
            'last_30_days'   => '30 derniers jours',
            'last_90_days'   => '90 derniers jours',
            'last_6_months'  => '6 derniers mois',
            'last_year'      => 'Dernière année',
            'all_time'       => 'Depuis le début',
        ];

        $data = [
            'athlete'                => $athlete,
            'dashboard_metrics_data' => $metricsDataForDashboard, // Données agrégées pour le tableau de bord
            'period_label'           => $period,
            'period_options'         => $periodOptions,
            'daily_metrics_history'  => $dailyMetricsHistory,
            'dashboard_metric_types' => $dashboardMetricTypes, // Garder pour les entêtes de tableau
        ];

        if ($request->expectsJson()) {
            return response()->json($data);
        }
        
        return view('athletes.dashboard', $data);
    }
}