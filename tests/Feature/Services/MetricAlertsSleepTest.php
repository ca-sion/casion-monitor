<?php

use App\Models\Athlete;
use App\Models\Metric;
use App\Enums\MetricType;
use App\Services\MetricAlertsService;
use Carbon\Carbon;

it('generates alerts for low sleep duration', function () {
    $athlete = Athlete::factory()->create();
    $service = resolve(MetricAlertsService::class);

    // 1. Case: Critical low sleep duration (< 6h)
    $metric = Metric::create([
        'athlete_id' => $athlete->id,
        'metric_type' => MetricType::MORNING_SLEEP_DURATION,
        'value' => 5.5,
        'date' => Carbon::today(),
    ]);

    $metrics = collect([$metric]);

    $alerts = $service->checkAllAlerts($athlete, $metrics);
    
    $sleepAlert = collect($alerts)->firstWhere(fn($a) => str_contains($a['message'], 'Durée de sommeil très faible'));
    expect($sleepAlert)->not->toBeNull();
    expect($sleepAlert['type'])->toBe('danger');

    // 2. Case: Low average sleep duration (< 7h) over 7 days
    $metrics = collect();
    for ($i = 0; $i < 7; $i++) {
        $metrics->push(Metric::create([
            'athlete_id' => $athlete->id,
            'metric_type' => MetricType::MORNING_SLEEP_DURATION,
            'value' => 6.5,
            'date' => Carbon::today()->subDays($i),
        ]));
    }

    $alerts = $service->checkAllAlerts($athlete, $metrics);
    $avgSleepAlert = collect($alerts)->firstWhere(fn($a) => str_contains($a['message'], 'Moyenne de sommeil faible'));
    expect($avgSleepAlert)->not->toBeNull();
    expect($avgSleepAlert['type'])->toBe('warning');
});