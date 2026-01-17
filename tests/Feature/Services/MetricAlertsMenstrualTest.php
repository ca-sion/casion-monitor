<?php

use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Carbon;
use App\Services\MetricAlertsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->alertsService = app(MetricAlertsService::class);
});

it('does not show menstrual alerts for men', function () {
    $athlete = Athlete::factory()->create(['gender' => 'm']);

    // Last J1 100 days ago (would be amenorrhea for a woman)
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
        'date'        => now()->subDays(100),
        'value'       => 1,
    ]);
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
        'date'        => now()->subDays(128),
        'value'       => 1,
    ]);

    $alerts = $this->alertsService->getAlerts($athlete, collect(), collect(), ['include_alerts' => ['menstrual']]);

    expect($alerts)->toBeEmpty();
});

it('shows amenorrhea alert for women when applicable', function () {
    $athlete = Athlete::factory()->create(['gender' => 'w']);

    Carbon::setTestNow('2026-01-20');

    // Avg cycle 28, last J1 100 days ago
    $j1_1 = Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
        'date'        => Carbon::now()->subDays(100),
        'value'       => 1,
    ]);
    $j1_2 = Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
        'date'        => Carbon::now()->subDays(128),
        'value'       => 1,
    ]);

    $metrics = collect([$j1_1, $j1_2]);

    $alerts = $this->alertsService->getAlerts($athlete, $metrics, collect(), ['include_alerts' => ['menstrual']]);

    $found = false;
    foreach ($alerts as $alert) {
        if (str_contains($alert['message'], 'Aménorrhée') || str_contains($alert['message'], 'irrégulier')) {
            $found = true;
            expect($alert['type'])->toBe('danger');
        }
    }
    expect($found)->toBeTrue();
});

it('shows phase specific fatigue correlation alert', function () {
    $athlete = Athlete::factory()->create(['gender' => 'w']);
    Carbon::setTestNow('2026-01-17');

    // Phase Menstruelle (Day 3)
    $j1_1 = Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
        'date'        => '2026-01-15',
        'value'       => 1,
    ]);
    $j1_2 = Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD->value,
        'date'        => '2025-12-18',
        'value'       => 1,
    ]);

    // High fatigue during menstrual phase (7/10)
    $fatigue = Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_GENERAL_FATIGUE->value,
        'date'        => '2026-01-17',
        'value'       => 8,
    ]);

    $metrics = collect([$j1_1, $j1_2, $fatigue]);

    $alerts = $this->alertsService->getAlerts($athlete, $metrics, collect(), ['include_alerts' => ['menstrual']]);

    $found = false;
    foreach ($alerts as $alert) {
        if (str_contains($alert['message'], 'Fatigue élevée') && str_contains($alert['message'], 'phase menstruelle')) {
            $found = true;
            expect($alert['type'])->toBe('info');
        }
    }
    expect($found)->toBeTrue();
});
