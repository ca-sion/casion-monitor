<?php

use App\Models\Metric;
use App\Enums\MetricType;
use App\Services\MetricCalculationService;

beforeEach(function () {
    $this->service = new MetricCalculationService;
});

it('calculates SBM correctly without sleep duration (weighted average)', function () {
    $metrics = collect([
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 8]),   // 8 * 1.5 = 12
        new Metric(['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 2]), // (10-2)=8 * 1.0 = 8
        new Metric(['metric_type' => MetricType::MORNING_PAIN, 'value' => 0]),            // (10-0)=10 * 1.0 = 10
        new Metric(['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 9]),  // 9 * 1.5 = 13.5
    ]);

    // Total Weight: 1.5 + 1.0 + 1.0 + 1.5 = 5.0
    // Weighted Sum: 12 + 8 + 10 + 13.5 = 43.5
    // SBM: 43.5 / 5.0 = 8.7

    $sbm = $this->service->calculateSbmForCollection($metrics);
    expect($sbm)->toBe(8.7);
});

it('calculates SBM correctly with good sleep duration (weighted average)', function () {
    $metrics = collect([
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 8]),   // 12 (w=1.5)
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_DURATION, 'value' => 8]),  // 8h -> 10pts * 1.0 = 10
        new Metric(['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 2]), // 8 (w=1.0)
        new Metric(['metric_type' => MetricType::MORNING_PAIN, 'value' => 0]),            // 10 (w=1.0)
        new Metric(['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 9]),  // 13.5 (w=1.5)
    ]);

    // Total Weight: 1.5 + 1.0 + 1.0 + 1.0 + 1.5 = 6.0
    // Weighted Sum: 12 + 10 + 8 + 10 + 13.5 = 53.5
    // SBM: 53.5 / 6.0 = 8.916... -> 8.9
    // No penalty for 8h sleep.

    $sbm = $this->service->calculateSbmForCollection($metrics);
    expect($sbm)->toBe(8.9);
});

it('calculates SBM correctly with lower sleep duration (weighted average + penalty)', function (float $hours, float $expectedSbm) {
    // Base Metrics (Perfect scores except duration)
    // Quality: 10 (w=1.5) -> 15
    // Fatigue: 0 -> 10 (w=1.0) -> 10
    // Pain: 0 -> 10 (w=1.0) -> 10
    // Mood: 10 (w=1.5) -> 15
    // Total Weight: 6.0

    $metrics = collect([
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 10]),
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_DURATION, 'value' => $hours]),
        new Metric(['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 0]),
        new Metric(['metric_type' => MetricType::MORNING_PAIN, 'value' => 0]),
        new Metric(['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 10]),
    ]);

    $sbm = $this->service->calculateSbmForCollection($metrics);

    expect($sbm)->toBe($expectedSbm);
})->with([
    '7h sleep (-0.5 penalty)' => [7.0, 9.1],
    // Duration Score: (7-4)*2.5 = 7.5
    // Sum: 15 + 7.5 + 10 + 10 + 15 = 57.5
    // Base SBM: 57.5 / 6 = 9.5833
    // Penalty: -1.0 (Wait, logic is: <8h -> -0.5, <7h -> -1.0)
    // 7.0 is NOT < 7.0, so it falls into elseif($sleepDuration < 8) -> -0.5.
    // Result: 9.5833 - 0.5 = 9.0833 -> 9.1

    '6.5h sleep (-1.0 penalty)' => [6.5, 8.4],
    // Duration Score: (6.5-4)*2.5 = 6.25
    // Sum: 15 + 6.25 + 10 + 10 + 15 = 56.25
    // Base SBM: 56.25 / 6 = 9.375
    // Penalty: <7h -> -1.0
    // Result: 9.375 - 1.0 = 8.375 -> 8.4

    '4h sleep (-4.0 penalty)' => [4.0, 4.3],
    // Duration Score: 0
    // Sum: 15 + 0 + 10 + 10 + 15 = 50.0
    // Base SBM: 50.0 / 6 = 8.333
    // Penalty: <5h -> -4.0
    // Result: 8.333 - 4.0 = 4.333 -> 4.3

    '5.5h sleep (-2.0 penalty)' => [5.5, 7.0],
    // Duration Score: (5.5-4)*2.5 = 3.75
    // Sum: 15 + 3.75 + 10 + 10 + 15 = 53.75
    // Base SBM: 53.75 / 6 = 8.958
    // Penalty: <6h -> -2.0
    // Result: 8.958 - 2.0 = 6.958 -> 7.0
]);
