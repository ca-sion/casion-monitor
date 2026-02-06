<?php

use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use App\Models\CalculatedMetric;
use App\Enums\CalculatedMetricType;
use App\Services\MetricReadinessService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->service = resolve(MetricReadinessService::class);
    $this->athlete = Athlete::factory()->create();
});

it('returns null score when no metrics are provided (Fixed Flaw)', function () {
    $allMetrics = collect();

    $result = $this->service->calculateOverallReadinessScore($this->athlete, $allMetrics);

    expect($result['readiness_score'])->toBeNull();
    expect($result['confidence_index'])->toBe(0);
});

it('calculates score based on a single pillar (SBM) with redistribution', function () {

    // SBM of 8/10 => 80 points.

    // If only SBM is present, it should be 100% of the weight for the score.

    CalculatedMetric::create([

        'athlete_id' => $this->athlete->id,

        'date'       => now()->startOfDay(),

        'type'       => CalculatedMetricType::SBM,

        'value'      => 8,

    ]);



    // Create one raw metric to simulate SBM source (e.g. Sleep Quality)

    Metric::create([

        'athlete_id' => $this->athlete->id,

        'metric_type' => MetricType::MORNING_SLEEP_QUALITY,

        'value' => 8,

        'date' => now()->startOfDay(),

    ]);



    $allMetrics = Metric::where('athlete_id', $this->athlete->id)->get();

    $result = $this->service->calculateOverallReadinessScore($this->athlete, $allMetrics);



    expect($result['readiness_score'])->toBe(80);

    // Confidence: 1 raw metric out of 8 = 12.5% -> 13%

    expect($result['confidence_index'])->toBe(13); 

});



it('calculates score with multiple pillars', function () {

    // Pillar Subjective (35%): SBM 8 => 80 pts

    // Pillar Immediate (40%): Energy 10, Legs 10 => 100 pts

    // Total Weights Available: 0.35 + 0.40 = 0.75

    // Final Score: (80 * (0.35/0.75)) + (100 * (0.40/0.75))

    // Score: (80 * 0.466) + (100 * 0.533) = 37.33 + 53.33 = 90.66 -> 91

    

    CalculatedMetric::create([

        'athlete_id' => $this->athlete->id,

        'date' => now()->startOfDay(),

        'type' => CalculatedMetricType::SBM,

        'value' => 8,

    ]);

    

    // Raw metrics for Immediate pillar

    Metric::create([

        'athlete_id' => $this->athlete->id,

        'metric_type' => MetricType::PRE_SESSION_ENERGY_LEVEL,

        'value' => 10,

        'date' => now()->startOfDay(),

    ]);



    Metric::create([

        'athlete_id' => $this->athlete->id,

        'metric_type' => MetricType::PRE_SESSION_LEG_FEEL,

        'value' => 10,

        'date' => now()->startOfDay(),

    ]);



    // Raw metric for SBM pillar (to simulate partial completion)

    Metric::create([

        'athlete_id' => $this->athlete->id,

        'metric_type' => MetricType::MORNING_SLEEP_QUALITY,

        'value' => 8,

        'date' => now()->startOfDay(),

    ]);

    

    $allMetrics = Metric::where('athlete_id', $this->athlete->id)->get();

    $result = $this->service->calculateOverallReadinessScore($this->athlete, $allMetrics);

    

    expect($result['readiness_score'])->toBe(91);

    // Confidence: 3 raw metrics (Sleep, Energy, Legs) out of 8 = 37.5% -> 38%

    expect($result['confidence_index'])->toBe(38);

});



it('applies the safety cap (veto) for severe pain', function () {
    // Perfect scores everywhere
    CalculatedMetric::create([
        'athlete_id' => $this->athlete->id,
        'date'       => now()->startOfDay(),
        'type'       => CalculatedMetricType::SBM,
        'value'      => 10,
    ]);

    // BUT severe pain
    Metric::create([
        'athlete_id'  => $this->athlete->id,
        'metric_type' => MetricType::MORNING_PAIN,
        'value'       => 9,
        'date'        => now()->startOfDay(),
    ]);

    $allMetrics = Metric::where('athlete_id', $this->athlete->id)->get();
    $result = $this->service->calculateOverallReadinessScore($this->athlete, $allMetrics);

    // Even if SBM is 100, the cap should block it at 40
    expect($result['readiness_score'])->toBeLessThanOrEqual(40);
});

it('handles HRV drops correctly in the physio pillar', function () {
    // 7 days avg = 100. Today = 80. Drop = 20%.
    // Score = 100 + (-20 * 5) = 0.

    for ($i = 1; $i <= 7; $i++) {
        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_HRV,
            'value'       => 100,
            'date'        => now()->subDays($i)->startOfDay(),
        ]);
    }

    Metric::create([
        'athlete_id'  => $this->athlete->id,
        'metric_type' => MetricType::MORNING_HRV,
        'value'       => 80,
        'date'        => now()->startOfDay(),
    ]);

    $allMetrics = Metric::where('athlete_id', $this->athlete->id)->get();
    $result = $this->service->calculateOverallReadinessScore($this->athlete, $allMetrics);

    // Physio pillar (25% weight) is 0.
    // Confidence index 25.
    // Score should be 0 since it's the only pillar.
    expect($result['readiness_score'])->toBe(0);
});

it('returns neutral status when score is null', function () {

    $allMetrics = collect();

    $status = $this->service->getAthleteReadinessStatus($this->athlete, $allMetrics);

    // With 0 metrics, missingCount will be 4 (>3), so it will be neutral via the first check

    expect($status['level'])->toBe('neutral');

    expect($status['readiness_score'])->toBe('n/a');

});

it('returns neutral status when some metrics are present but score is null', function () {

    // Only Weight (not in essential) => missingCount will be 4

    Metric::create([

        'athlete_id' => $this->athlete->id,

        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG,

        'value' => 75,

        'date' => now()->startOfDay(),

    ]);

    $allMetrics = Metric::where('athlete_id', $this->athlete->id)->get();

    $status = $this->service->getAthleteReadinessStatus($this->athlete, $allMetrics);

    expect($status['level'])->toBe('neutral');

});
