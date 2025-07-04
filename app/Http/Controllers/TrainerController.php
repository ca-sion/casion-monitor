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

        $period = request()->input('period', 'last_30_days'); // Permet de filtrer les données du graphique
        $selectedMetricType = request()->input('metric_type'); // Permet de sélectionner une métrique spécifique pour le graphique détaillé

        // Définir les types de métriques à afficher dans le tableau
        $displayTableMetricTypes = [
            MetricType::MORNING_HRV,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_BODY_WEIGHT_KG,
            MetricType::MORNING_MOOD_WELLBEING,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            // Ajoutez d'autres types de métriques si nécessaire pour les colonnes du tableau
        ];

        // Récupérer l'historique des métriques pour le tableau (peut être paginé)
        $dailyMetricsHistory = $this->metricStatisticsService->getLatestMetricsGroupedByDate($athlete, 50);

        // Traiter l'historique des métriques pour la préparation du tableau
        $processedDailyMetrics = $dailyMetricsHistory->map(function ($metricDates, $date) use ($displayTableMetricTypes) {
            $rowData = [
                'date_formatted' => \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('L'),
                'day_of_week'    => \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('dddd'),
                'metrics'        => [],
                'edit_link'      => null,
            ];

            foreach ($displayTableMetricTypes as $metricType) {
                $metric = $metricDates->where('metric_type', $metricType->value)->first();
                $rowData['metrics'][$metricType->value] = $metric ? $this->metricStatisticsService->formatMetricValue($metric->{$metricType->getValueColumn()}, $metricType) : 'N/A';
            }
            
            // Assuming the edit link is associated with any metric entry for that day, or a specific "daily check-in" metric
            // For simplicity, we'll try to get it from the first available metric for the day.
            // In a real app, you might have a dedicated daily entry record or a more robust way to generate this link.
            $firstMetricOfDay = $metricDates->first();
            if ($firstMetricOfDay && isset($firstMetricOfDay->metadata['edit_link'])) { // Assuming edit_link might be in metadata
                 $rowData['edit_link'] = $firstMetricOfDay->metadata['edit_link'];
            } elseif ($firstMetricOfDay) {
                // Fallback: if no specific edit_link in metadata, create a generic one based on date and athlete.
                // This would need a route that allows editing metrics for a specific date.
                // Example: route('metrics.edit_daily', ['athlete' => $athlete->id, 'date' => $date]);
                // For now, setting to a placeholder or null if no concrete link.
                $rowData['edit_link'] = '#'; // Placeholder
            }


            return $rowData;
        });

        // Préparer les données pour le graphique détaillé d'une métrique spécifique
        $chartMetricType = null;
        $chartData = ['labels' => [], 'data' => [], 'unit' => null, 'label' => null];
        
        if ($selectedMetricType && MetricType::tryFrom($selectedMetricType)) {
            $chartMetricType = MetricType::tryFrom($selectedMetricType);
            $metricsForChart = $this->metricStatisticsService->getAthleteMetrics($athlete, ['metric_type' => $selectedMetricType, 'period' => $period]);
            $chartData = $this->metricStatisticsService->prepareChartDataForSingleMetric($metricsForChart, $chartMetricType);
        } else {
            // Par défaut, afficher le HRV si aucune métrique n'est sélectionnée pour le graphique
            $chartMetricType = MetricType::MORNING_HRV;
            $metricsForChart = $this->metricStatisticsService->getAthleteMetrics($athlete, ['metric_type' => MetricType::MORNING_HRV->value, 'period' => $period]);
            $chartData = $this->metricStatisticsService->prepareChartDataForSingleMetric($metricsForChart, MetricType::MORNING_HRV);
        }


        // Options de période pour le sélecteur du graphique
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

        // Types de métriques disponibles pour la sélection du graphique
        $availableMetricTypesForChart = collect(MetricType::cases())
            ->filter(fn($mt) => $mt->getValueColumn() !== 'note') // Filtrer les métriques non numériques pour les graphiques simples
            ->mapWithKeys(fn($mt) => [$mt->value => $mt->getLabel()])
            ->toArray();


        return view('trainers.athlete', [
            'trainer'                    => $trainer,
            'athlete'                    => $athlete,
            'daily_metrics_history'      => $processedDailyMetrics,
            'display_table_metric_types' => $displayTableMetricTypes,
            'chart_data'                 => $chartData,
            'chart_metric_type'          => $chartMetricType, // The MetricType object for the chart
            'period_label'               => $period,
            'period_options'             => $periodOptions,
            'available_metric_types_for_chart' => $availableMetricTypesForChart,
        ]);
    }
}