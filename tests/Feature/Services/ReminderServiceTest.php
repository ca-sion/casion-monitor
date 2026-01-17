<?php

use App\Models\Athlete;
use App\Models\Metric;
use App\Enums\MetricType;
use App\Services\ReminderService;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reminderService = app(ReminderService::class);
});

it('correctly identifies if monthly metric is filled', function () {
    $athlete = Athlete::factory()->create();
    $date = Carbon::parse('2025-01-15');

    expect($this->reminderService->hasFilledMonthlyMetric($athlete, $date))->toBeFalse();

    Metric::create([
        'athlete_id' => $athlete->id,
        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
        'date' => $date->copy()->startOfMonth(),
        'value' => 75.5,
    ]);

    expect($this->reminderService->hasFilledMonthlyMetric($athlete, $date))->toBeTrue();
});

it('should show monthly metric alert when metric is missing', function () {
    $athlete = Athlete::factory()->create();
    
    // For current month
    expect($this->reminderService->shouldShowMonthlyMetricAlert($athlete))->toBeTrue();

    Metric::create([
        'athlete_id' => $athlete->id,
        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
        'date' => now()->startOfMonth(),
        'value' => 75.5,
    ]);

    expect($this->reminderService->shouldShowMonthlyMetricAlert($athlete))->toBeFalse();
});

it('gets athletes needing monthly reminder', function () {
    $athlete1 = Athlete::factory()->create(['first_name' => 'Athlete 1']);
    $athlete2 = Athlete::factory()->create(['first_name' => 'Athlete 2']);
    
    $date = Carbon::parse('2025-02-15');

    // Initially both need reminder
    $needing = $this->reminderService->getAthletesNeedingMonthlyReminder($date);
    expect($needing)->toHaveCount(2);

    // Fill for athlete 1
    Metric::create([
        'athlete_id' => $athlete1->id,
        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
        'date' => $date->copy()->startOfMonth(),
        'value' => 70.0,
    ]);

    $needing = $this->reminderService->getAthletesNeedingMonthlyReminder($date);
    expect($needing)->toHaveCount(1);
    expect($needing->first()->id)->toBe($athlete2->id);
});
