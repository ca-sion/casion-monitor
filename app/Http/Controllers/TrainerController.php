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

        // Si le formateur n'est pas trouvé, rediriger ou retourner une erreur
        if (! $trainer) {
            // Gérer le cas où le formateur n'est pas authentifié
            // Par exemple, rediriger vers la page de connexion ou afficher une erreur
            abort(403, 'Accès non autorisé'); // Ou return redirect()->route('login');
        }

        $athletes = $trainer->athletes; // Récupère tous les athlètes associés à cet entraîneur

        // Définir les types de métriques à afficher sur le tableau de bord
        // Cette liste doit correspondre à celles que vous voulez voir dans le tableau.
        // J'ai inclus quelques métriques numériques et non numériques pour l'exemple.
        $dashboardMetricTypes = [
            MetricType::MORNING_HRV,
            MetricType::POST_SESSION_SUBJECTIVE_FATIGUE,
            MetricType::MORNING_GENERAL_FATIGUE,
            MetricType::MORNING_SLEEP_QUALITY,
            MetricType::POST_SESSION_SESSION_LOAD,
            MetricType::MORNING_BODY_WEIGHT_KG, // Ajout de cette métrique si elle est pertinente
            // MetricType::MORNING_PAIN, // Exemple de métrique 'note' que l'on peut ajouter, mais qui affichera 'N/A' pour les tendances
        ];

        // Convertir les enums en valeurs de chaîne pour le service
        $dashboardMetricTypeValues = array_map(fn ($mt) => $mt->value, $dashboardMetricTypes);

        // Définir la période pour l'aperçu des tendances (ex: 'last_30_days', 'last_7_days', 'all_time')
        // Vous pourriez rendre cela configurable via la requête si vous voulez des filtres dynamiques.
        $period = request()->input('period', 'last_30_days'); // Période par défaut, mais peut être surchargée par la requête

        $athletesOverviewData = $this->metricStatisticsService->getOverviewMetricsForAthletes(
            $athletes, // Utiliser la collection d'athlètes récupérée
            $dashboardMetricTypeValues,
            $period
        );

        $data = [
            'trainer' => $trainer,
            // Renommer 'athletes' en 'athletes_overview_data' pour correspondre à la vue
            'athletes_overview_data' => $athletesOverviewData,
            'dashboard_metric_types' => $dashboardMetricTypes, // Passer les objets Enum MetricType à la vue
            'period_label'           => $period, // Pour afficher la période dans la vue
            // Les deux lignes ci-dessous sont pour un tableau de bord plus avancé avec filtres dynamiques,
            // mais ne sont pas strictement nécessaires pour la version actuelle de la vue si vous ne les utilisez pas.
            // 'available_metric_types' => collect(MetricType::cases())->map(fn ($enum) => ['value' => $enum->value, 'label' => $enum->getLabel()]),
            // 'filters_applied'        => ['metric_types' => $selectedMetricTypeValues, 'period' => $period],
        ];

        if (request()->expectsJson()) {
            return response()->json($data);
        }

        return view('trainers.dashboard', $data);
    }

    public function athlete(string $hash, Athlete $athlete): View
    {
        $trainer = Auth::guard('trainer')->user();

        return view('trainers.athlete', [
            'trainer' => $trainer,
            'athlete' => $athlete,
        ]);
    }
}
