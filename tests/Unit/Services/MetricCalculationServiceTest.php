<?php

use App\Enums\MetricType;
use App\Models\Metric;
use App\Services\MetricCalculationService;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->service = new MetricCalculationService();
});

it('calculates SBM correctly without sleep duration', function () {
    $metrics = collect([
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 8]),
        new Metric(['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 2]), // (10-2) = 8
        new Metric(['metric_type' => MetricType::MORNING_PAIN, 'value' => 0]), // (10-0) = 10
        new Metric(['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 9]),
    ]);

    $sbm = $this->service->calculateSbmForCollection($metrics);

    // Sum = 8 + 8 + 10 + 9 = 35
    // Max = 40
    // SBM = (35/40) * 10 = 8.75 -> 8.8
    expect($sbm)->toBe(8.8);
});

it('calculates SBM correctly with good sleep duration', function () {
    $metrics = collect([
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 8]),
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_DURATION, 'value' => 8]), // 10 pts
        new Metric(['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 2]), // 8 pts
        new Metric(['metric_type' => MetricType::MORNING_PAIN, 'value' => 0]), // 10 pts
        new Metric(['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 9]), // 9 pts
    ]);

    $sbm = $this->service->calculateSbmForCollection($metrics);

    // Sum = 8 + 10 + 8 + 10 + 9 = 45
    // Max = 50
    // SBM = (45/50) * 10 = 9.0
    expect($sbm)->toBe(9.0);
});

it('applies penalty for insufficient sleep duration', function (float $hours, float $expectedSbm) {
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
    '7h (-0.5 penalty)' => [7.0, 9.0], // Sum: 10 + 7.5 + 10 + 10 + 10 = 47.5. SBM: 47.5/50*10 = 9.5. 9.5 - 0.5 = 9.0
    '6.5h (-1.0 penalty)' => [6.5, 8.3], // Sum: 10 + 6.25 + 10 + 10 + 10 = 46.25. SBM: 46.25/50*10 = 9.25. 9.25 - 1.0 = 8.25 -> 8.3
]);

it('checks detailed penalties for sleep duration', function () {
    // Case < 5h: -4.0
    $metrics = collect([
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 10]),
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_DURATION, 'value' => 4]), // 0 pts
        new Metric(['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 0]),
        new Metric(['metric_type' => MetricType::MORNING_PAIN, 'value' => 0]),
        new Metric(['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 10]),
    ]);
    // Sum = 10 + 0 + 10 + 10 + 10 = 40. SBM = 40/50 * 10 = 8.0.
    // Penalty < 5h = -4.0. 8.0 - 4.0 = 4.0
    expect($this->service->calculateSbmForCollection($metrics))->toBe(4.0);

    // Case < 6h: -2.0
    $metrics = collect([
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_QUALITY, 'value' => 10]),
        new Metric(['metric_type' => MetricType::MORNING_SLEEP_DURATION, 'value' => 5.5]), // (5.5-4)*2.5 = 3.75 pts
        new Metric(['metric_type' => MetricType::MORNING_GENERAL_FATIGUE, 'value' => 0]),
        new Metric(['metric_type' => MetricType::MORNING_PAIN, 'value' => 0]),
        new Metric(['metric_type' => MetricType::MORNING_MOOD_WELLBEING, 'value' => 10]),
    ]);
    // Sum = 10 + 3.75 + 10 + 10 + 10 = 43.75. SBM = 43.75/50 * 10 = 8.75.
    // Penalty < 6h = -2.0. 8.75 - 2.0 = 6.75 -> 6.8
    expect($this->service->calculateSbmForCollection($metrics))->toBe(6.8);
});