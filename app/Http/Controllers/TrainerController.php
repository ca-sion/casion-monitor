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

        $athletes = $trainer->athletes; // Récupère tous les athlètes associés à cet entraîneur

        // Récupérer les filtres de la requête
        $selectedMetricTypeValues = request()->input('metric_types', [MetricType::MORNING_BODY_WEIGHT_KG->value, MetricType::MORNING_HRV->value]); // Valeurs par défaut
        $period = request()->input('period', 'last_30_days'); // Période par défaut

        $overviewData = $this->metricStatisticsService->getOverviewMetricsForAthletes($athletes, $selectedMetricTypeValues, $period);

        $data = [
            'trainer'                => $trainer,
            'athletes'               => $overviewData,
            'available_metric_types' => collect(MetricType::cases())->map(fn ($enum) => ['value' => $enum->value, 'label' => $enum->getLabel()]),
            'filters_applied'        => [
                'metric_types' => $selectedMetricTypeValues,
                'period'       => $period,
            ],
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
