<?php

namespace App\Http\Controllers;

use App\Enums\MetricType;
use App\Enums\CalculatedMetric;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\MetricService;
use App\Models\TrainingPlanWeek;
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
            MetricType::MORNING_PAIN_LOCATION,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::POST_SESSION_PERFORMANCE_FEEL,
            MetricType::PRE_SESSION_ENERGY_LEVEL,
            MetricType::PRE_SESSION_LEG_FEEL,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        // Définir les types de métriques "calculées" à afficher
        $calculatedMetricTypes = [
            CalculatedMetric::SBM,
            CalculatedMetric::RATIO_CIH_NORMALIZED_CPH,
        ];

        // Préparer les options pour l'appel unique à getAthletesData
        $options = [
            'period'                       => $period,
            'metric_types'                 => $dashboardMetricTypes,
            'calculated_metrics'           => $calculatedMetricTypes,
            'include_dashboard_metrics'    => true,
            'include_latest_daily_metrics' => true,
            'include_alerts'               => ['general', 'charge', 'readiness', 'menstrual'],
            'include_menstrual_cycle'      => true,
            'include_readiness_status'     => true,
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
        $gamificationData = $athlete->metadata['gamification'] ?? null;

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

        // Préparer les données pour le graphique hebdomadaire combiné
        $sbmData = $athleteData->weekly_metrics_data['sbm']['chart_data'] ?? ['labels' => [], 'data' => []];
        $ratioData = $athleteData->weekly_metrics_data['ratio_cih_normalized_cph']['chart_data'] ?? ['labels' => [], 'data' => []];

        $combinedWeeklyChartData = [];
        if (! empty($sbmData['labels'])) {
            foreach ($sbmData['labels'] as $index => $label) {
                $combinedWeeklyChartData[] = [
                    'label' => $label,
                    'sbm' => $sbmData['data'][$index] ?? null,
                    'ratio' => $ratioData['data'][$index] ?? null,
                ];
            }
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

        $data = [
            'athlete'                       => $athlete,
            'dashboard_metrics_data'        => $metricsDataForDashboard,
            'alerts'                        => $alerts,
            'menstrualCycleInfo'            => $menstrualCycleInfo,
            'readinessStatus'               => $readinessStatus,
            'gamificationData'              => $gamificationData,
            'period_label'                  => $period,
            'period_options'                => $periodOptions,
            'daily_metrics_grouped_by_date' => $processedDailyMetricsForTable,
            'display_table_metric_types'    => $displayTableMetricTypes,
            'weekly_planned_volume'         => $weeklyPlannedVolume,
            'weekly_planned_intensity'      => $weeklyPlannedIntensity,
            'healthEvents'             => $athlete->healthEvents()->limit(12)->orderBy('date', 'desc')->get(),
            'last_days_feedbacks'           => $lastSevenDaysFeedbacks,
            'today_feedbacks'               => $todaysFeedbacks,
            'combinedWeeklyChartData'       => $combinedWeeklyChartData,
        ];

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('athletes.dashboard', $data);
    }

    public function journal(Request $request): View|JsonResponse
    {
        $athlete = Auth::guard('athlete')->user();
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        $period = $request->input('period', 'last_30_days');
        $page = $request->input('page', 1);
        $perPage = 15;

        // 1. Définir la plage de dates
        $periodMap = [
            'last_7_days'  => 7, 'last_15_days' => 15, 'last_30_days' => 30,
            'last_90_days' => 90, 'last_6_months' => 180, 'last_year' => 365,
        ];
        $startDate = isset($periodMap[$period]) ? now()->subDays($periodMap[$period])->startOfDay() : null;

        // 2. Récupérer et formater toutes les données dans un array PHP simple
        $allTimelineItems = [];

        // Feedbacks
        $feedbacks = $athlete->feedbacks()->with('trainer')
            ->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
            ->get();
        foreach ($feedbacks as $item) {
            $allTimelineItems[] = ['date' => $item->date, 'type' => 'feedback', 'data' => $item];
        }

        // Blessures
        $injuries = $athlete->injuries()
            ->when($startDate, fn ($q) => $q->where('start_date', '>=', $startDate))
            ->get();
        foreach ($injuries as $item) {
            $allTimelineItems[] = ['date' => $item->start_date, 'type' => 'injury', 'data' => $item];
        }

        // Protocoles de récupération
        $healthEvents = $athlete->healthEvents()
            ->when($startDate, fn ($q) => $q->where('date', '>=', $startDate))
            ->get();
        foreach ($healthEvents as $item) {
            $allTimelineItems[] = ['date' => $item->date, 'type' => 'health_event', 'data' => $item];
        }

        // Métriques quotidiennes
        $metricData = $this->metricService->getAthletesData(collect([$athlete]), [
            'period'                       => $period,
            'include_latest_daily_metrics' => true,
        ])->first();

        if (! empty($metricData->latest_daily_metrics)) {
            foreach ($metricData->latest_daily_metrics as $date => $metricsForDay) {
                $allTimelineItems[] = [
                    'date' => Carbon::parse($date),
                    'type' => 'daily_metrics',
                    'data' => $metricsForDay,
                ];
            }
        }

        // Semaines du plan d'entraînement (si un lundi)
        // if ($athlete->currentTrainingPlan) {
        //     $trainingPlanWeeks = TrainingPlanWeek::where('training_plan_id', $athlete->currentTrainingPlan->id)
        //         ->when($startDate, fn ($q) => $q->where('start_date', '>=', $startDate))
        //         ->get();

        //     foreach ($trainingPlanWeeks as $week) {
        //         $weekStartDate = Carbon::parse($week->start_date);
        //         if ($weekStartDate->isMonday()) {
        //             $allTimelineItems[] = [
        //                 'date' => $weekStartDate,
        //                 'type' => 'training_plan_week',
        //                 'data' => $week,
        //             ];
        //         }
        //     }
        // }

        // 3. Trier tous les éléments par date, puis les grouper
        $groupedItems = collect($allTimelineItems)
            ->sortByDesc(fn ($item) => Carbon::parse($item['date']))
            ->groupBy(fn ($item) => Carbon::parse($item['date'])->format('Y-m-d'));

        // 4. Paginer les jours groupés. $perPage définit le nombre de jours par page.
        $paginatedGroups = new \Illuminate\Pagination\LengthAwarePaginator(
            $groupedItems->forPage($page, $perPage),
            $groupedItems->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $periodOptions = [
            'last_7_days'   => '7 derniers jours', 'last_15_days' => '15 derniers jours',
            'last_30_days'  => '30 derniers jours', 'last_90_days' => '90 derniers jours',
            'last_6_months' => '6 derniers mois', 'last_year' => 'Dernière année',
            'all_time'      => 'Depuis le début',
        ];

        $data = [
            'athlete'           => $athlete,
            'groupedItems'      => $paginatedGroups,
            'timelinePaginator' => $paginatedGroups,
            'periodOptions'     => $periodOptions,
            'currentPeriod'     => $period,
        ];
        // dd($groupedItems);

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('athletes.journal', $data);
    }
}
