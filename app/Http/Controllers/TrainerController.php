<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Models\Feedback;
use App\Enums\MetricType;
use Illuminate\View\View;
use App\Enums\FeedbackType;
use Illuminate\Support\Carbon;
use App\Services\MetricService;
use Illuminate\Http\JsonResponse;
use App\Enums\CalculatedMetricType;
use Illuminate\Support\Facades\Auth;

class TrainerController extends Controller
{
    protected MetricService $metricService;

    public function __construct(MetricService $metricService)
    {
        $this->metricService = $metricService;
    }

    public function dashboard(): View|JsonResponse
    {
        $trainer = Auth::guard('trainer')->user();

        if (! $trainer) {
            abort(403, 'Accès non autorisé');
        }

        $period = request()->input('period', 'last_60_days');
        $showInfoAlerts = filter_var(request()->input('show_info_alerts', false), FILTER_VALIDATE_BOOLEAN);
        $showMenstrualCycle = filter_var(request()->input('show_menstrual_cycle', false), FILTER_VALIDATE_BOOLEAN);
        $showChartAndAvg = filter_var(request()->input('show_chart_and_avg', false), FILTER_VALIDATE_BOOLEAN);

        // Définir les types de métriques "brutes" à afficher
        $metricTypes = [
            // MetricType::MORNING_HRV,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_PAIN,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        // Définir les types de métriques "calculées" à afficher
        $calculatedMetricTypes = [
            CalculatedMetricType::SBM,
            CalculatedMetricType::READINESS_SCORE,
            CalculatedMetricType::ACWR,
            // CalculatedMetricType::CIH_NORMALIZED,
            // CalculatedMetricType::RATIO_CIH_NORMALIZED_CPH,
        ];

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

        // Appel unique pour avoir les données en une seule fois
        $athletesOverviewData = $this->metricService->getAthletesData($trainer->athletes, [
            'period'                       => 'last_60_days',
            'metric_types'                 => $metricTypes,
            'calculated_metrics'           => $calculatedMetricTypes,
            'include_dashboard_metrics'    => true,
            'include_weekly_metrics'       => true,
            'include_latest_daily_metrics' => true,
            'include_alerts'               => ['general', 'charge', 'menstrual'],
            'include_menstrual_cycle'      => $showMenstrualCycle,
            'include_readiness_status'     => true,
        ]);

        $hasAlerts = $athletesOverviewData->some(fn ($athlete) => ! empty($athlete->alerts));

        $data = [
            'trainer'                 => $trainer,
            'athletes_overview_data'  => $athletesOverviewData,
            'dashboard_metric_types'  => $metricTypes,
            'calculated_metric_types' => $calculatedMetricTypes,
            'period_label'            => $period,
            'period_options'          => $periodOptions,
            'show_info_alerts'        => $showInfoAlerts,
            'show_menstrual_cycle'    => $showMenstrualCycle,
            'show_chart_and_avg'      => $showChartAndAvg,
            'has_alerts'              => $hasAlerts,
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

        // Charger les blessures de l'athlète
        $athlete->load('injuries');

        $period = request()->input('period', 'last_60_days');
        $selectedMetricType = request()->input('metric_type');

        // Définir les types de métriques "brutes" à afficher dans les cartes individuelles
        $metricTypes = [
            MetricType::MORNING_HRV,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_MOOD_WELLBEING,
            MetricType::MORNING_PAIN,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        // Définir les types de métriques à afficher dans le tableau des données quotidiennes
        $displayTableMetricTypes = [
            MetricType::MORNING_HRV,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_MOOD_WELLBEING,
            MetricType::MORNING_PAIN,
            MetricType::MORNING_PAIN_LOCATION,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::POST_SESSION_PAIN,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        // Définir les types de métriques à sélectionner pour le graphique
        $availableMetricTypesForChart = collect(MetricType::cases())
            ->filter(fn ($mt) => $mt->getValueColumn() !== 'note')
            ->mapWithKeys(fn ($mt) => [$mt->value => $mt->getLabel()])
            ->toArray();

        // Déterminer le type de métrique pour le graphique détaillé
        $chartMetricType = MetricType::tryFrom($selectedMetricType) ?? MetricType::MORNING_HRV;

        // Préparer les options pour l'appel unique à getAthletesData
        $options = [
            'period'                       => $period,
            'metric_types'                 => $metricTypes, // Pour les cartes du dashboard
            'include_dashboard_metrics'    => true,
            'include_latest_daily_metrics' => true, // Pour le tableau des données quotidiennes
            'include_alerts'               => ['general', 'charge', 'menstrual'],
            'include_menstrual_cycle'      => true,
            'include_readiness_status'     => true,
            'chart_metric_type'            => $chartMetricType->value, // Pour le graphique détaillé
            'chart_period'                 => $period, // Utiliser la même période pour le graphique
        ];

        // Appel unique pour avoir toutes les données de l'athlète
        $athleteData = $this->metricService->getAthletesData(collect([$athlete]), $options)->first();

        // Extraire les données de l'athlète enrichi
        $dashboard_metrics_data = $athleteData->dashboard_metrics_data ?? [];
        $alerts = $athleteData->alerts ?? [];
        $menstrualCycleInfo = $athleteData->menstrual_cycle_info ?? null;
        $readinessStatus = $athleteData->readiness_status ?? null;
        $latestDailyMetrics = $athleteData->latest_daily_metrics ?? collect();
        $chartData = data_get($dashboard_metrics_data, $chartMetricType->value.'.chart_data') ?? ['labels' => [], 'data' => [], 'unit' => null, 'label' => null];

        // Traiter l'historique des métriques pour la préparation du tableau
        $processedDailyMetrics = $this->metricService->prepareDailyMetricsForTableView(
            $latestDailyMetrics,
            $displayTableMetricTypes,
            $athleteData,
            true
        );

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

        return view('trainers.athlete', [
            'trainer'                          => $trainer,
            'athlete'                          => $athlete,
            'dashboard_metrics_data'           => $dashboard_metrics_data,
            'alerts'                           => $alerts,
            'menstrualCycleInfo'               => $menstrualCycleInfo,
            'readinessStatus'                  => $readinessStatus,
            'daily_metrics_grouped_by_date'    => $processedDailyMetrics,
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
