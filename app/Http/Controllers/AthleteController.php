<?php

namespace App\Http\Controllers;

use App\Enums\MetricType;
use Illuminate\View\View;
use App\Enums\FeedbackType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::MORNING_MOOD_WELLBEING,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        $period = $request->input('period', 'last_30_days');
        $days = match ($period) {
            'last_7_days'   => 7,
            'last_14_days'  => 14,
            'last_30_days'  => 30,
            'last_90_days'  => 90,
            'last_6_months' => 180,
            'last_year'     => 365,
            'all_time'      => 500, // A large number to signify "all time" for daily metrics history
            default         => 50
        };

        $metricsDataForDashboard = [];
        foreach ($dashboardMetricTypes as $metricType) {
            $metricsDataForDashboard[$metricType->value] = $this->metricStatisticsService->getDashboardMetricData($athlete, $metricType, $period);
        }

        // Fetch alerts for the athlete
        $alerts = $this->metricStatisticsService->getAthleteAlerts($athlete, 'last_60_days');

        // Fetch menstrual cycle info if applicable
        $menstrualCycleInfo = null;
        if ($athlete->gender->value === 'w') {
            $menstrualCycleInfo = $this->metricStatisticsService->deduceMenstrualCyclePhase($athlete);
        }

        // --- Préparation des données pour le tableau des métriques quotidiennes (maximum de calculs dans le contrôleur) ---
        $dailyMetricsGroupedByDate = $this->metricStatisticsService->getLatestMetricsGroupedByDate($athlete, $days);

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
            // Ajoutez d'autres types de métriques si nécessaire pour le tableau détaillé
        ];

        $processedDailyMetricsForTable = collect([]);

        foreach ($dailyMetricsGroupedByDate as $date => $metricsOnDate) {
            $currentDate = Carbon::parse($date);
            $rowData = [
                'date'      => $currentDate->locale('fr_CH')->isoFormat('L'), // Formatage de la date ici
                'metrics'   => [],
                'edit_link' => route('athletes.metrics.daily.form', ['hash' => $athlete->hash, 'd' => $currentDate->format('Y-m-d')]), // Lien d'édition par jour
            ];

            foreach ($displayTableMetricTypes as $metricType) {
                $metric = $metricsOnDate->where('metric_type', $metricType->value)->first();
                if ($metric) {
                    // Formatage de la valeur de la métrique ici
                    $rowData['metrics'][$metricType->value] = $this->metricStatisticsService->formatMetricValue($metric->{$metricType->getValueColumn()}, $metricType);
                } else {
                    $rowData['metrics'][$metricType->value] = 'N/A';
                }
            }
            $processedDailyMetricsForTable->put($date, $rowData);
        }

        $periodOptions = [
            'last_7_days'   => '7 derniers jours',
            'last_14_days'  => '14 derniers jours',
            'last_30_days'  => '30 derniers jours',
            'last_90_days'  => '90 derniers jours',
            'last_6_months' => '6 derniers mois',
            'last_year'     => 'Dernière année',
            'all_time'      => 'Depuis le début',
        ];

        $currentWeekStartDate = \Carbon\Carbon::now()->startOfWeek(\Carbon\Carbon::MONDAY);
        $trainingPlanWeek = $this->metricStatisticsService->getTrainingPlanWeekForAthlete($athlete, $currentWeekStartDate);

        $data = [
            'athlete'                       => $athlete,
            'dashboard_metrics_data'        => $metricsDataForDashboard,
            'alerts'                        => $alerts,
            'menstrualCycleInfo'            => $menstrualCycleInfo,
            'period_label'                  => $period,
            'period_options'                => $periodOptions,
            'daily_metrics_grouped_by_date' => $processedDailyMetricsForTable, // Renommé et pré-traité
            'display_table_metric_types'    => $displayTableMetricTypes,
            'weekly_planned_volume'         => $trainingPlanWeek->volume_planned ?? 0,
            'weekly_planned_intensity'      => $trainingPlanWeek->intensity_planned ?? 0,
        ];

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('athletes.dashboard', $data);
    }

    public function feedbacks(Request $request): View|JsonResponse
    {
        $athlete = Auth::guard('athlete')->user();

        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        $filterType = $request->input('filter_type');
        $filterCategory = $request->input('filter_category');
        $period = $request->input('period', 'last_15_days');
        $page = $request->input('page', 1);
        $perPage = 10;

        $query = $athlete->feedbacks()->with('trainer');

        if ($filterType && FeedbackType::tryFrom($filterType)) {
            $query->where('type', $filterType);
        }

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

        $feedbacks = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $groupedFeedbacks = $feedbacks->groupBy(function ($feedback) {
            return \Carbon\Carbon::parse($feedback->date)->format('Y-m-d');
        });

        $data = [
            'athlete'               => $athlete,
            'groupedFeedbacks'      => $groupedFeedbacks,
            'feedbackTypes'         => FeedbackType::cases(),
            'periodOptions'         => $periodOptionsForView,
            'currentPeriod'         => $period,
            'currentFilterType'     => $filterType,
            'currentFilterCategory' => $filterCategory,
            'feedbacksPaginator'    => $feedbacks,
        ];

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('athletes.feedbacks', $data);
    }
}
