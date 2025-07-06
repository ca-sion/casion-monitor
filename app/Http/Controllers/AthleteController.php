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
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_BODY_WEIGHT_KG,
            // MetricType::MORNING_SLEEP_QUALITY,
            // MetricType::POST_SESSION_SESSION_LOAD,
        ];

        $period = $request->input('period', 'last_30_days');
        $days = match ($period) {
            'last_7_days'   => 7,
            'last_14_days'  => 14,
            'last_30_days'  => 30,
            'last_90_days'  => 90,
            'last_6_months' => 180,
            'last_year'     => 365,
            'all_time'      => 500,
            default         => 50
        };

        $metricsDataForDashboard = [];
        foreach ($dashboardMetricTypes as $metricType) {
            $metricsDataForDashboard[$metricType->value] = $this->metricStatisticsService->getDashboardMetricData($athlete, $metricType, $period);
        }

        $dailyMetricsHistory = $this->metricStatisticsService->getLatestMetricsGroupedByDate($athlete, $days);

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

    public function feedbacks(Request $request): View|JsonResponse
    {
        $athlete = Auth::guard('athlete')->user();

        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        // Récupérer les paramètres de filtre et de pagination
        $filterType = $request->input('filter_type'); // Exemple: 'pre_session_goals', 'post_competition_feedback'
        $filterCategory = $request->input('filter_category'); // Exemple: 'session' ou 'competition'
        $period = $request->input('period', 'last_15_days'); // Nouvelle option pour la période
        $page = $request->input('page', 1); // Pour la pagination
        $perPage = 10; // Nombre de feedbacks par page

        $query = $athlete->feedbacks()->with('trainer');

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
        // Gérer la période d'affichage
        $limitDate = null;
        $periodMap = [ // Utilisation d'une map pour les jours réels
            'last_7_days'   => 7,
            'last_15_days'  => 15,
            'last_30_days'  => 30,
            'last_90_days'  => 90,
            'last_6_months' => 180,
            'last_year'     => 365,
            'all_time'      => null,
        ];

        // Définir les options pour la vue avec les labels directement
        $periodOptionsForView = [
            'last_7_days'   => '7 derniers jours',
            'last_15_days'  => '15 derniers jours',
            'last_30_days'  => '30 derniers jours',
            'last_90_days'  => '90 derniers jours',
            'last_6_months' => '6 derniers mois',
            'last_year'     => 'Dernière année',
            'all_time'      => 'Depuis le début',
        ];

        // Utilisez $periodMap pour la logique de la date limite
        if (isset($periodMap[$period]) && $periodMap[$period] !== null) {
            $limitDate = Carbon::now()->subDays($periodMap[$period])->startOfDay();
            $query->where('date', '>=', $limitDate);
        }

        // Ordonner les feedbacks et paginer
        $feedbacks = $query->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page); // Utilisation de paginate

        // Regrouper les feedbacks par date (après pagination si nécessaire, ou alors sur le résultat paginé)
        // Note: Le regroupement après pagination peut entraîner des dates incomplètes sur les limites de pages.
        // Si vous voulez un regroupement parfait, il faudrait regrouper d'abord puis paginer les groupes.
        // Pour l'instant, on regroupe les résultats de la page courante.
        $groupedFeedbacks = $feedbacks->groupBy(function ($feedback) {
            return \Carbon\Carbon::parse($feedback->date)->format('Y-m-d');
        });

        $data = [
            'athlete'               => $athlete,
            'groupedFeedbacks'      => $groupedFeedbacks,
            'feedbackTypes'         => FeedbackType::cases(), // Garder pour le sélecteur de type
            'periodOptions'         => $periodOptionsForView, // Pour l'affichage des options dans la vue
            'currentPeriod'         => $period,
            'currentFilterType'     => $filterType,
            'currentFilterCategory' => $filterCategory,
            'feedbacksPaginator'    => $feedbacks, // Passer l'objet paginator pour les liens de pagination
        ];

        if ($request->expectsJson()) {
            return response()->json($data);
        }

        return view('athletes.feedbacks', $data);
    }
}
