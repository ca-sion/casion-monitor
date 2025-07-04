<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\MetricStatisticsService; // Assurez-vous d'importer le service

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

        // Si l'athlète n'est pas trouvé, rediriger ou retourner une erreur
        if (! $athlete) {
            abort(403, 'Accès non autorisé');
        }

        // Définir les types de métriques clés à afficher sur le tableau de bord de l'athlète
        $dashboardMetricTypes = [
            MetricType::MORNING_HRV,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::MORNING_GENERAL_FATIGUE,
            // MetricType::MORNING_SLEEP_QUALITY,
            // MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::MORNING_BODY_WEIGHT_KG,
        ];

        // Convertir les enums en valeurs de chaîne pour le service
        $dashboardMetricTypeValues = array_map(fn ($mt) => $mt->value, $dashboardMetricTypes);

        // Récupérer la période depuis la requête ou utiliser 'last_30_days' par défaut
        $period = $request->input('period', 'last_30_days');

        // Récupérer les données agrégées pour cet athlète
        // Nous passons l'athlète dans une collection pour utiliser le même service que le contrôleur du coach
        $overviewDataCollection = $this->metricStatisticsService->getOverviewMetricsForAthletes(
            collect([$athlete]), // Wrap the single athlete in a collection
            $dashboardMetricTypeValues,
            $period
        );

        // Extraire les données spécifiques à l'athlète actuel
        $athleteMetricsData = $overviewDataCollection[$athlete->id] ?? [];

        // Définir les options de période pour le sélecteur dans la vue
        $periodOptions = [
            'last_7_days'   => '7 derniers jours',
            'last_14_days'   => '14 derniers jours',
            'last_30_days'  => '30 derniers jours',
            'last_90_days'  => '90 derniers jours',
            'last_6_months' => '6 derniers mois',
            'last_year'     => 'Dernière année',
            'all_time'      => 'Depuis le début',
        ];

        $data = [
            'athlete'                => $athlete,
            'metrics_data'           => $athleteMetricsData, // Données agrégées pour l'athlète
            'dashboard_metric_types' => $dashboardMetricTypes, // Les objets Enum MetricType
            'period_label'           => $period, // La valeur de la période sélectionnée
            'period_options'         => $periodOptions, // Les options pour le sélecteur
        ];

        if ($request->expectsJson()) {
            return response()->json($data);
        }
        
        return view('athletes.dashboard', $data); //
    }
}
