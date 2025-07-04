<?php

namespace App\Http\Controllers;

use App\Models\Athlete;
use App\Enums\MetricType; // Import de l'énumération
use App\Services\MetricStatisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AthleteMetricController extends Controller
{
    protected $metricStatisticsService;

    public function __construct(MetricStatisticsService $metricStatisticsService)
    {
        $this->metricStatisticsService = $metricStatisticsService;
    }

    /**
     * Affiche les statistiques d'une métrique spécifique pour un athlète.
     * Utilisation pour un graphique d'une seule série (un seul type de métrique).
     *
     * @param Request $request
     * @param Athlete $athlete
     * @param string $metricTypeValue Le nom de l'énumération MetricType (ex: 'morning_body_weight_kg')
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSingleMetricStatistics(Request $request, Athlete $athlete, string $metricTypeValue)
    {
        $metricType = MetricType::tryFrom($metricTypeValue);

        if (!$metricType) {
            return response()->json(['error' => 'Type de métrique non valide.'], 404);
        }

        $filters = $request->only(['period']); // 'metric_type' est déjà dans l'URL

        // Récupère toutes les métriques pour ce type et période
        $metrics = $this->metricStatisticsService->getAthleteMetrics($athlete, array_merge($filters, ['metric_type' => $metricTypeValue]));

        $chartData = $this->metricStatisticsService->prepareChartDataForSingleMetric($metrics, $metricType);
        $trends = $this->metricStatisticsService->getMetricTrends($athlete, $metricType);

        return response()->json([
            'athlete' => $athlete->only('id', 'name'),
            'metric_type_info' => [
                'value' => $metricType->value,
                'label' => $metricType->getLabel(),
                'unit' => $metricType->getUnit(),
                'scale' => $metricType->getScale(),
                'scale_hint' => $metricType->getScaleHint(),
                'value_column' => $metricType->getValueColumn(),
            ],
            'chart_data' => $chartData,
            'trends' => $trends,
            'filters_applied' => $filters,
        ]);
    }

    /**
     * Affiche les statistiques de plusieurs métriques pour un athlète sur un même graphique.
     * Permet de comparer plusieurs types de métriques.
     *
     * @param Request $request
     * @param Athlete $athlete
     * @return \Illuminate\Http\JsonResponse
     */
    public function showMultipleMetricStatistics(Request $request, Athlete $athlete)
    {
        $selectedMetricTypeValues = $request->input('metric_types', []); // Attendu comme un tableau de string
        $period = $request->input('period');

        $metricTypes = collect($selectedMetricTypeValues)
            ->map(fn ($value) => MetricType::tryFrom($value))
            ->filter() // Supprime les valeurs non valides
            ->unique()
            ->values()
            ->toArray();

        if (empty($metricTypes)) {
            return response()->json(['error' => 'Aucun type de métrique valide sélectionné.'], 400);
        }

        // Pour récupérer toutes les métriques pertinentes d'un coup, filtrez d'abord par athlète et période.
        // Ensuite, filtrez la collection en mémoire par les types de métriques sélectionnés pour prepareChartDataForMultipleMetrics.
        $allMetricsForPeriod = $this->metricStatisticsService->getAthleteMetrics($athlete, ['period' => $period]);

        // Filtrer la collection pour ne garder que les métriques des types sélectionnés
        $filteredMetrics = $allMetricsForPeriod->filter(fn ($metric) => in_array($metric->metric_type, $metricTypes, true));

        $chartData = $this->metricStatisticsService->prepareChartDataForMultipleMetrics($filteredMetrics, $metricTypes);

        return response()->json([
            'athlete' => $athlete->only('id', 'name'),
            'chart_data' => $chartData,
            'filters_applied' => [
                'metric_types' => array_map(fn ($mt) => $mt->value, $metricTypes),
                'period' => $period,
            ],
        ]);
    }


    /**
     * Retourne la liste de tous les MetricTypes disponibles pour les filtres.
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableMetricTypes()
    {
        $metricTypes = collect(MetricType::cases())->map(fn ($enum) => [
            'value' => $enum->value,
            'label' => $enum->getLabel(),
            'description' => $enum->getDescription(),
            'unit' => $enum->getUnit(),
            'scale' => $enum->getScale(),
            'scale_hint' => $enum->getScaleHint(),
            'value_column' => $enum->getValueColumn(),
        ])->toArray();

        return response()->json(['metric_types' => $metricTypes]);
    }
}