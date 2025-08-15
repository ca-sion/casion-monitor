<?php

namespace App\Http\Controllers;

use App\Enums\MetricType;
use Illuminate\View\View;
use App\Enums\FeedbackType;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\MetricService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AthleteController extends Controller
{
    protected MetricService $metricService;

    public function __construct(MetricService $metricService)
    {
        $this->metricService = $metricService;
    }

    public function dashboard(Request $request): View|JsonResponse
    {
        $athlete = Auth::guard('athlete')->user();

        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        $period = $request->input('period', 'last_30_days');

        // Définir les types de métriques "brutes" à afficher dans les cartes individuelles
        $dashboardMetricTypes = [
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
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        // Préparer les options pour l'appel unique à getAthletesData
        $options = [
            'period'                       => $period,
            'metric_types'                 => $dashboardMetricTypes,
            'include_dashboard_metrics'    => true,
            'include_latest_daily_metrics' => true,
            'include_alerts'               => ['general', 'charge', 'readiness', 'menstrual'],
            'include_menstrual_cycle'      => true,
            'include_readiness_status'     => false,
            'include_weekly_metrics'       => true, // Pour les métriques hebdomadaires (volume, intensité)
        ];

        // Appel unique pour avoir toutes les données de l'athlète
        $athleteData = $this->metricService->getAthletesData(collect([$athlete]), $options)->first();

        // Extraire les données de l'athlète enrichi
        $metricsDataForDashboard = $athleteData->dashboard_metrics_data ?? [];
        $alerts = $athleteData->alerts ?? [];
        $menstrualCycleInfo = $athleteData->menstrual_cycle_info ?? null;
        $readinessStatus = $athleteData->readiness_status ?? null;
        $latestDailyMetrics = $athleteData->latest_daily_metrics ?? collect();
        $weeklyMetricsData = collect($athleteData->weekly_metrics_data ?? []);

        // Récupérer le volume et l'intensité planifiés pour la semaine en cours
        $currentTrainingPlanWeek = $athlete->currentTrainingPlanWeek;
        $weeklyPlannedVolume = $currentTrainingPlanWeek->volume_planned ?? 0;
        $weeklyPlannedIntensity = $currentTrainingPlanWeek->intensity_planned ?? 0;

        // Récupérer les feedbacks de la semaine
        $lastSevenDaysFeedbacks = $athlete->feedbacks()
            ->with('trainer')
            ->whereBetween('date', [now()->subDays(7)->startOfDay(), now()->endOfDay()])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        $todaysFeedbacks = $lastSevenDaysFeedbacks->filter(function ($feedback) {
            return $feedback->date->isToday();
        });
        $lastSevenDaysFeedbacks = $lastSevenDaysFeedbacks->filter(function ($feedback) {
            return $feedback->date->isBefore(now()->startOfDay());
        });

        // Traiter l'historique des métriques pour la préparation du tableau
        $processedDailyMetricsForTable = $this->metricService->prepareDailyMetricsForTableView(
            $latestDailyMetrics,
            $displayTableMetricTypes,
            $athlete,
            false
        );

        $periodOptions = [
            'last_7_days'   => '7 derniers jours',
            'last_14_days'  => '14 derniers jours',
            'last_30_days'  => '30 derniers jours',
            'last_90_days'  => '90 derniers jours',
            'last_6_months' => '6 derniers mois',
            'last_year'     => 'Dernière année',
            'all_time'      => 'Depuis le début',
        ];

        $data = [
            'athlete'                       => $athlete,
            'dashboard_metrics_data'        => $metricsDataForDashboard,
            'alerts'                        => $alerts,
            'menstrualCycleInfo'            => $menstrualCycleInfo,
            'readinessStatus'               => $readinessStatus,
            'period_label'                  => $period,
            'period_options'                => $periodOptions,
            'daily_metrics_grouped_by_date' => $processedDailyMetricsForTable,
            'display_table_metric_types'    => $displayTableMetricTypes,
            'weekly_planned_volume'         => $weeklyPlannedVolume,
            'weekly_planned_intensity'      => $weeklyPlannedIntensity,
            'recoveryProtocols'             => $athlete->recoveryProtocols()->orderBy('date', 'desc')->get(),
            'last_days_feedbacks'           => $lastSevenDaysFeedbacks,
            'today_feedbacks'               => $todaysFeedbacks,
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
