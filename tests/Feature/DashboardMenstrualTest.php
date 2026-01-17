<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Metric;
use App\Models\Athlete;
use App\Enums\MetricType;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardMenstrualTest extends TestCase
{
    use RefreshDatabase;

    protected Athlete $athlete;

    protected function setUp(): void
    {
        parent::setUp();
        $this->athlete = Athlete::factory()->create(['gender' => 'w', 'first_name' => 'Jane']);
    }

    #[Test]
    public function it_displays_menstrual_cycle_card_for_women()
    {
        Carbon::setTestNow('2026-01-20');

        // Setup a regular cycle
        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD,
            'date'        => '2026-01-15',
            'value'       => 1,
        ]);
        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD,
            'date'        => '2025-12-18',
            'value'       => 1,
        ]);

        $response = $this->actingAs($this->athlete, 'athlete')
            ->get(route('athletes.dashboard', ['hash' => $this->athlete->hash]));

        $response->assertStatus(200);
        $response->assertSee('Phase : Folliculaire');
        $response->assertSee('Jour 6');
        $response->assertSee('Dernier J1:');
        $response->assertSee('15.01.2026');
    }

    #[Test]
    public function it_does_not_display_menstrual_cycle_card_for_men()
    {
        $maleAthlete = Athlete::factory()->create(['gender' => 'm', 'first_name' => 'John']);

        $response = $this->actingAs($maleAthlete, 'athlete')
            ->get(route('athletes.dashboard', ['hash' => $maleAthlete->hash]));

        $response->assertStatus(200);
        $response->assertDontSee('Phase :');
        $response->assertDontSee('Mettre à jour mes dates de règles');
    }

    #[Test]
    public function it_shows_menstrual_reminder_callout()
    {
        Carbon::setTestNow('2026-01-20');

        // Suppose ReminderService says we need a reminder
        // The dashboard check is: if ($menstrualReminder ?? false)

        // To trigger it via real service, we'd need to set up the data.
        // Let's see ReminderService::getMenstrualReminderStatus

        // Actually, let's just test that IF it's there, it shows up.
        // But better test the real logic.

        // ReminderService::getMenstrualReminderStatus returns an array with 'message', 'color', etc.
        // It shows up if we are close to the next period.

        // Last J1: 2025-12-23 (28 days ago)
        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD,
            'date'        => '2025-12-23',
            'value'       => 1,
        ]);
        Metric::create([
            'athlete_id'  => $this->athlete->id,
            'metric_type' => MetricType::MORNING_FIRST_DAY_PERIOD,
            'date'        => '2025-11-25',
            'value'       => 1,
        ]);

        $response = $this->actingAs($this->athlete, 'athlete')
            ->get(route('athletes.dashboard', ['hash' => $this->athlete->hash]));

        $response->assertStatus(200);
        // It should see a reminder callout
        $response->assertSee('Noter mon J1');
    }
}
