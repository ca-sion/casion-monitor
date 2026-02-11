<?php

use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Carbon;
use App\Services\ReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->reminderService = app(ReminderService::class);
});

it('correctly identifies if monthly metric is filled', function () {
    $athlete = Athlete::factory()->create();
    $date = Carbon::parse('2025-01-15');

    expect($this->reminderService->hasFilledMonthlyMetric($athlete, $date))->toBeFalse();

    // Remplir la charge mentale
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MENTAL_LOAD->value,
        'date'        => $date->copy()->startOfMonth(),
        'value'       => 5,
    ]);

    expect($this->reminderService->hasFilledMonthlyMetric($athlete, $date))->toBeFalse();

    // Remplir la motivation
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MOTIVATION->value,
        'date'        => $date->copy()->startOfMonth(),
        'value'       => 8,
    ]);

    // Le poids est actif par défaut dans les préférences, donc toujours false
    expect($this->reminderService->hasFilledMonthlyMetric($athlete, $date))->toBeFalse();

    // Remplir le poids
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
        'date'        => $date->copy()->startOfMonth(),
        'value'       => 75.5,
    ]);

    expect($this->reminderService->hasFilledMonthlyMetric($athlete, $date))->toBeTrue();
});

it('should show monthly metric alert when metric is missing', function () {
    $athlete = Athlete::factory()->create();

    // Initialement vrai car rien n'est rempli
    expect($this->reminderService->shouldShowMonthlyMetricAlert($athlete))->toBeTrue();

    // Remplir tout
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MENTAL_LOAD->value,
        'date'        => now()->startOfMonth(),
        'value'       => 5,
    ]);
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MOTIVATION->value,
        'date'        => now()->startOfMonth(),
        'value'       => 8,
    ]);
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
        'date'        => now()->startOfMonth(),
        'value'       => 75.5,
    ]);

    expect($this->reminderService->shouldShowMonthlyMetricAlert($athlete))->toBeFalse();
});

it('gets athletes needing monthly reminder', function () {
    $athlete1 = Athlete::factory()->create(['first_name' => 'Athlete 1']);
    $athlete2 = Athlete::factory()->create(['first_name' => 'Athlete 2']);

    $date = Carbon::parse('2025-02-15');

    // Initialement les deux ont besoin du rappel
    $needing = $this->reminderService->getAthletesNeedingMonthlyReminder($date);
    expect($needing)->toHaveCount(2);

    // Remplir pour athlete 1 (les 3 métriques)
    Metric::create([
        'athlete_id'  => $athlete1->id,
        'metric_type' => MetricType::MONTHLY_MENTAL_LOAD->value,
        'date'        => $date->copy()->startOfMonth(),
        'value'       => 5,
    ]);
    Metric::create([
        'athlete_id'  => $athlete1->id,
        'metric_type' => MetricType::MONTHLY_MOTIVATION->value,
        'date'        => $date->copy()->startOfMonth(),
        'value'       => 8,
    ]);
    Metric::create([
        'athlete_id'  => $athlete1->id,
        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
        'date'        => $date->copy()->startOfMonth(),
        'value'       => 70.0,
    ]);

    $needing = $this->reminderService->getAthletesNeedingMonthlyReminder($date);
    expect($needing)->toHaveCount(1);
    expect($needing->first()->id)->toBe($athlete2->id);
});

it('does not show monthly metric alert if everything is filled but weight is disabled', function () {
    $athlete = Athlete::factory()->create([
        'preferences' => ['track_monthly_weight' => false],
    ]);

    // Remplir uniquement les métriques psychologiques
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MENTAL_LOAD->value,
        'date'        => now()->startOfMonth(),
        'value'       => 5,
    ]);
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MOTIVATION->value,
        'date'        => now()->startOfMonth(),
        'value'       => 8,
    ]);

    expect($this->reminderService->shouldShowMonthlyMetricAlert($athlete))->toBeFalse();
});

it('still shows monthly metric alert if psychological metrics are missing even if weight is disabled', function () {
    $athlete = Athlete::factory()->create([
        'preferences' => ['track_monthly_weight' => false],
    ]);

    // Manque la motivation
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MENTAL_LOAD->value,
        'date'        => now()->startOfMonth(),
        'value'       => 5,
    ]);

    expect($this->reminderService->shouldShowMonthlyMetricAlert($athlete))->toBeTrue();
});

it('excludes athletes from monthly reminders if everything is filled and weight is disabled', function () {
    $athlete = Athlete::factory()->create([
        'preferences' => ['track_monthly_weight' => false],
    ]);

    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MENTAL_LOAD->value,
        'date'        => now()->startOfMonth(),
        'value'       => 5,
    ]);
    Metric::create([
        'athlete_id'  => $athlete->id,
        'metric_type' => MetricType::MONTHLY_MOTIVATION->value,
        'date'        => now()->startOfMonth(),
        'value'       => 8,
    ]);

    $needing = $this->reminderService->getAthletesNeedingMonthlyReminder(now());
    expect($needing->contains($athlete))->toBeFalse();
});
