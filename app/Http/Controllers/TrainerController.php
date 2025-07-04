<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\MetricStatisticsService;

class TrainerController extends Controller
{
    protected $metricStatisticsService;

    public function __construct(MetricStatisticsService $metricStatisticsService)
    {
        $this->metricStatisticsService = $metricStatisticsService;
    }

    public function dashboard(): View|JsonResponse
    {
        $trainer = Auth::guard('trainer')->user();

        if (! $trainer) {
            abort(403, 'Accès non autorisé');
        }

        $athletes = $trainer->athletes;

        $dashboardMetricTypes = [
            MetricType::MORNING_HRV,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        $period = request()->input('period', 'last_30_days');

        $athletesOverviewData = $athletes->map(function ($athlete) use ($dashboardMetricTypes, $period) {
            $metricsDataForDashboard = [];
            foreach ($dashboardMetricTypes as $metricType) {
                // Utilise le même service pour les données de résumé de chaque athlète
                $metricsDataForDashboard[$metricType->value] = $this->metricStatisticsService->getDashboardMetricData($athlete, $metricType, $period);
            }
            // Attache les données agrégées directement à l'objet Athlete pour un accès facile dans la vue
            $athlete->metricsDataForDashboard = $metricsDataForDashboard;
            return $athlete;
        });

        $periodOptions = [
            'last_7_days'   => '7 derniers jours',
            'last_14_days'  => '14 derniers jours',
            'last_30_days'  => '30 derniers jours',
            'last_60_days'  => '60 derniers jours',
            'last_90_days'  => '90 derniers jours',
            'last_6_months' => '6 derniers mois',
            'last_year'     => 'Dernière année',
            'all_time'      => 'Depuis le début',
        ];
        // Vous pourriez ajouter d'autres périodes personnalisées ici, comme 'custom:2024-01-01,2024-03-31'

        $data = [
            'trainer'                => $trainer,
            'athletes_overview_data' => $athletesOverviewData,
            'dashboard_metric_types' => $dashboardMetricTypes,
            'period_label'           => $period,
            'period_options'         => $periodOptions,
        ];

        if (request()->expectsJson()) {
            return response()->json($data);
        }

        return view('trainers.dashboard', $data);
    }

    public function athlete(string $hash, Athlete $athlete): View
    {
        $trainer = Auth::guard('trainer')->user();

        // Verify trainer has access to this athlete
        if (! $trainer->athletes->contains($athlete)) {
            abort(403, 'Accès non autorisé à cet athlète.');
        }

        // Get daily metrics history for the specific athlete
        $dailyMetricsHistory = $this->metricStatisticsService->getLatestMetricsGroupedByDate($athlete, 50);

        // Define the specific metric types to display in the trainer's athlete view table
        $displayTableMetricTypes = [
            MetricType::MORNING_HRV,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            // Ajoutez d'autres types de métriques si nécessaire pour les colonnes du tableau
        ];

        // Process daily metrics history to prepare for the table
        $processedDailyMetrics = $dailyMetricsHistory->map(function ($metricDates, $date) use ($displayTableMetricTypes) {
            $rowData = [
                'date_formatted' => \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('L'),
                'day_of_week'    => \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('dddd'),
                'metrics'        => [],
                'edit_link'      => null,
            ];

            foreach ($displayTableMetricTypes as $metricType) {
                $metric = $metricDates->where('metric_type', $metricType->value)->first();
                // Utilisation de l'accessor 'data' avec 'formatted_value'
                $rowData['metrics'][$metricType->value] = $metric ? $metric->data->formatted_value : 'N/A';
            }
            
            if ($metricDates->first()) {
                $rowData['edit_link'] = $metricDates->first()->data->edit_link;
            }

            return $rowData;
        });


        return view('trainers.athlete', [
            'trainer'                 => $trainer,
            'athlete'                 => $athlete,
            'daily_metrics_history'   => $processedDailyMetrics,
            'display_table_metric_types' => $displayTableMetricTypes,
        ]);
    }
}