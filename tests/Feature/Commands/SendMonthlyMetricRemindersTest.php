<?php

use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SendMonthlyMetricReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sends monthly reminders to athletes who havent filled metrics', function () {
    Notification::fake();

    $athleteWithMetric = Athlete::factory()->create();
    $athleteWithoutMetric = Athlete::factory()->create(['telegram_chat_id' => '123456789']);

    // Fill metric for one athlete
    Metric::create([
        'athlete_id'  => $athleteWithMetric->id,
        'metric_type' => MetricType::MORNING_BODY_WEIGHT_KG->value,
        'date'        => now()->startOfMonth(),
        'value'       => 70.0,
    ]);

    $this->artisan('reminders:monthly')
        ->expectsOutput('Checking for athletes who haven\'t filled their monthly metrics...')
        ->expectsOutput('Sent 1 monthly metric reminders.')
        ->assertExitCode(0);

    Notification::assertSentTo($athleteWithoutMetric, SendMonthlyMetricReminder::class);
    Notification::assertNotSentTo($athleteWithMetric, SendMonthlyMetricReminder::class);
});
