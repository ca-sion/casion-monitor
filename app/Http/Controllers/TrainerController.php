<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Feedback;
use App\Enums\MetricType;
use Illuminate\View\View;
use App\Enums\FeedbackType;
use Illuminate\Support\Carbon;
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

        $period = request()->input('period', 'last_60_days');

        $athletesOverviewData = $athletes->map(function ($athlete) use ($dashboardMetricTypes, $period) {
            $metricsDataForDashboard = [];
            foreach ($dashboardMetricTypes as $metricType) {
                // Utilise le même service pour les données de résumé de chaque athlète
                $metricsDataForDashboard[$metricType->value] = $this->metricStatisticsService->getDashboardMetricData($athlete, $metricType, $period);
            }
            // Attache les données agrégées directement à l'objet Athlete pour un accès facile dans la vue
            $athlete->metricsDataForDashboard = $metricsDataForDashboard;

            // Alertes et le cycle menstruel
            $athlete->alerts = $this->metricStatisticsService->getAthleteAlerts($athlete, 'last_60_days');
            $athlete->menstrualCycleInfo = $this->metricStatisticsService->deduceMenstrualCyclePhase($athlete);

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

        $period = request()->input('period', 'last_60_days');
        $selectedMetricType = request()->input('metric_type');

        // Définir les types de métriques pour le dashboard (cartes individuelles)
        $dashboardMetricTypes = [
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
        ];

        $dashboard_metrics_data = [];
        foreach ($dashboardMetricTypes as $metricType) {
            $dashboard_metrics_data[$metricType->value] = $this->metricStatisticsService->getDashboardMetricData($athlete, $metricType, $period);
        }

        // Alertes et le cycle menstruel
        $alerts = $this->metricStatisticsService->getAthleteAlerts($athlete, $period);
        $menstrualCycleInfo = $this->metricStatisticsService->deduceMenstrualCyclePhase($athlete);

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
        $dailyMetricsGroupedByDate = $this->metricStatisticsService->getLatestMetricsGroupedByDate($athlete, 50); // Utilise la même variable que le dashboard athlète

        // Traiter l'historique des métriques pour la préparation du tableau
        $processedDailyMetrics = $dailyMetricsGroupedByDate->map(function ($metricDates, $date) use ($displayTableMetricTypes) {
            $rowData = [
                'date'           => \Carbon\Carbon::parse($date)->locale('fr_CH')->isoFormat('L'), // Renommé pour correspondre à la vue dashboard
                'metrics'        => [],
                'edit_link'      => null,
            ];

            foreach ($displayTableMetricTypes as $metricType) {
                $metric = $metricDates->where('metric_type', $metricType->value)->first();
                $rowData['metrics'][$metricType->value] = $metric ? $this->metricStatisticsService->formatMetricValue($metric->{$metricType->getValueColumn()}, $metricType) : 'N/A';
            }

            $firstMetricOfDay = $metricDates->first();
            if ($firstMetricOfDay && isset($firstMetricOfDay->metadata['edit_link'])) {
                $rowData['edit_link'] = $firstMetricOfDay->metadata['edit_link'];
            } elseif ($firstMetricOfDay) {
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

        // Options de période pour le sélecteur du graphique et le sélecteur principal
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
            ->filter(fn ($mt) => $mt->getValueColumn() !== 'note')
            ->mapWithKeys(fn ($mt) => [$mt->value => $mt->getLabel()])
            ->toArray();

        return view('trainers.athlete', [
            'trainer'                          => $trainer,
            'athlete'                          => $athlete,
            'dashboard_metrics_data'           => $dashboard_metrics_data, // Ajouté pour les cartes de métriques
            'alerts'                           => $alerts, // Ajouté pour la section alertes
            'menstrualCycleInfo'               => $menstrualCycleInfo, // Ajouté pour le cycle menstruel
            'daily_metrics_grouped_by_date'    => $processedDailyMetrics, // Renommé pour correspondre à la vue dashboard
            'display_table_metric_types'       => $displayTableMetricTypes,
            'chart_data'                       => $chartData,
            'chart_metric_type'                => $chartMetricType,
            'period_label'                     => $period,
            'period_options'                   => $periodOptions,
            'available_metric_types_for_chart' => $availableMetricTypesForChart,
        ]);
    }

    public function feedbacks(): View|JsonResponse
    {
        $trainer = Auth::guard('trainer')->user();

        if (! $trainer) {
            abort(403, 'Accès non autorisé');
        }

        // Récupérer les paramètres de filtre et de pagination
        $filterType = request()->input('filter_type');
        $filterCategory = request()->input('filter_category');
        $period = request()->input('period', 'last_15_days');
        $filterAthleteId = request()->input('athlete_id');
        $page = request()->input('page', 1);
        $perPage = 10;

        // Récupérer les athlètes de cet entraîneur
        $trainerAthletes = $trainer->athletes; // Collection d'athlètes de l'entraîneur

        // Commencer la requête des feedbacks
        $query = Feedback::query()
            ->whereIn('athlete_id', $trainerAthletes->pluck('id')) // Filtrer les feedbacks pour les athlètes de cet entraîneur
            ->with(['athlete', 'trainer']); // Charger l'athlète et le créateur (entraîneur ou athlète) du feedback

        // Appliquer le filtre par athlète si spécifié
        if ($filterAthleteId && $trainerAthletes->contains('id', $filterAthleteId)) { // Vérifier que l'athlète appartient bien à l'entraîneur
            $query->where('athlete_id', $filterAthleteId);
        }

        // Appliquer le filtre par type si spécifié
        if ($filterType && FeedbackType::tryFrom($filterType)) {
            $query->where('type', $filterType);
        }

        // Appliquer le filtre par catégorie (session ou compétition)
        if ($filterCategory) {
            $sessionTypes = [
                FeedbackType::PRE_SESSION_GOALS->value,
                FeedbackType::POST_SESSION_FEEDBACK->value,
                FeedbackType::POST_SESSION_SENSATION->value,
            ];
            $competitionTypes = [
                FeedbackType::PRE_COMPETITION_GOALS->value,
                FeedbackType::POST_COMPETITION_FEEDBACK->value,
                FeedbackType::POST_COMPETITION_SENSATION->value,
            ];

            if ($filterCategory === 'session') {
                $query->whereIn('type', $sessionTypes);
            } elseif ($filterCategory === 'competition') {
                $query->whereIn('type', $competitionTypes);
            }
        }

        // Gérer la période d'affichage
        $limitDate = null;
        $periodMap = [
            'last_7_days'   => 7,
            'last_15_days'  => 15,
            'last_30_days'  => 30,
            'last_90_days'  => 90,
            'last_6_months' => 180,
            'last_year'     => 365,
            'all_time'      => null,
        ];

        $periodOptionsForView = [
            'last_7_days'   => '7 derniers jours',
            'last_15_days'  => '15 derniers jours',
            'last_30_days'  => '30 derniers jours',
            'last_90_days'  => '90 derniers jours',
            'last_6_months' => '6 derniers mois',
            'last_year'     => 'Dernière année',
            'all_time'      => 'Depuis le début',
        ];

        if (isset($periodMap[$period]) && $periodMap[$period] !== null) {
            $limitDate = Carbon::now()->subDays($periodMap[$period])->startOfDay();
            $query->where('date', '>=', $limitDate);
        }

        // Ordonner les feedbacks et paginer
        $feedbacksPaginator = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        // Regrouper les feedbacks par date (pour l'affichage)
        $groupedFeedbacks = $feedbacksPaginator->groupBy(function ($feedback) {
            return Carbon::parse($feedback->date)->format('Y-m-d');
        });

        $data = [
            'trainer'                => $trainer,
            'groupedFeedbacks'       => $groupedFeedbacks,
            'feedbackTypes'          => FeedbackType::cases(),
            'periodOptions'          => $periodOptionsForView,
            'currentPeriod'          => $period,
            'currentFilterType'      => $filterType,
            'currentFilterCategory'  => $filterCategory,
            'currentFilterAthleteId' => $filterAthleteId,
            'trainerAthletes'        => $trainerAthletes,
            'feedbacksPaginator'     => $feedbacksPaginator,
        ];

        if (request()->expectsJson()) {
            return response()->json($data);
        }

        return view('trainers.feedbacks', $data);
    }
}
